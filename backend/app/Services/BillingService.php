<?php
namespace App\Services;

use App\Models\BillingSetting;
use App\Models\Company;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\SubscriptionPayment;
use App\Services\Billing\AbaPayWayGateway;
use App\Services\Billing\BakongGateway;
use Illuminate\Support\Facades\DB;

/**
 * Subscription lifecycle: present the priced catalogue, take a member through checkout with one of the
 * three payment methods (COD / KHQR / ABA KHQR), reconcile the payment, and activate the plan.
 *
 * A payment becoming `paid` is the single event that activates a subscription — whether that came from
 * a gateway poll, a gateway callback, or an admin confirming manually. Activation is idempotent.
 */
class BillingService
{
    private const QR_TTL_MINUTES = 15;

    public function settings(): BillingSetting
    {
        return BillingSetting::singleton();
    }

    /** Priced catalogue for a currency, plus the company's current standing. */
    public function catalogue(Company $company, string $currency): array
    {
        $currency = in_array($currency, ['USD', 'KHR'], true) ? $currency : 'USD';
        $plans = Plan::where('is_active', true)->with('prices')->orderBy('sort_order')->get()
            ->map(fn (Plan $p) => [
                'code' => $p->code,
                'name' => $p->name,
                'description' => $p->description,
                'modules_count' => $p->features()->count(),
                'prices' => [
                    'monthly' => optional($p->priceFor('monthly', $currency))->amount,
                    'yearly' => optional($p->priceFor('yearly', $currency))->amount,
                ],
            ])->values();

        $active = $company->activeSubscription();
        return [
            'currency' => $currency,
            'plans' => $plans,
            'current_plan' => $company->plan?->code,
            'subscription' => $active ? $this->presentSubscription($active) : null,
            'methods' => $this->availableMethods(),
        ];
    }

    /** Which payment methods a member may use right now, with their live/manual status. */
    public function availableMethods(): array
    {
        $s = $this->settings();
        $out = [];
        foreach (['cod', 'khqr', 'aba_khqr'] as $m) {
            if (!$s->methodEnabled($m)) continue;
            $out[] = [
                'method' => $m,
                'live' => $m === 'cod' ? true : $s->methodConfigured($m),
                'instructions' => $m === 'cod' ? ($s->cod_instructions ?? '') : null,
            ];
        }
        return $out;
    }

    /**
     * Start a checkout: create the (pending) subscription + payment and, for QR methods, the QR itself.
     * @throws \InvalidArgumentException on an invalid plan/price/method
     */
    public function checkout(Company $company, string $planCode, string $interval, string $currency, string $method): SubscriptionPayment
    {
        $s = $this->settings();
        if (!$s->methodEnabled($method)) {
            throw new \InvalidArgumentException('This payment method is not available.');
        }
        $plan = Plan::where('code', $planCode)->where('is_active', true)->firstOrFail();
        $price = $plan->priceFor($interval, $currency);
        if (!$price || (float) $price->amount <= 0) {
            throw new \InvalidArgumentException('This plan has no price set for the chosen interval/currency yet.');
        }
        $amount = (float) $price->amount;

        return DB::transaction(function () use ($company, $plan, $interval, $currency, $method, $amount) {
            $sub = Subscription::create([
                'company_id' => $company->id,
                'plan_id' => $plan->id,
                'interval' => $interval,
                'currency' => $currency,
                'amount' => $amount,
                'status' => 'pending',
            ]);

            $payment = new SubscriptionPayment([
                'company_id' => $company->id,
                'subscription_id' => $sub->id,
                'plan_id' => $plan->id,
                'method' => $method,
                'currency' => $currency,
                'amount' => $amount,
                'status' => 'pending',
                'provider' => 'manual',
            ]);

            $bill = 'NPCRM-'.$sub->id.'-'.now()->format('ymdHis');
            if ($method === 'khqr') {
                $charge = app(BakongGateway::class)->createCharge($amount, $currency, $bill);
                $payment->provider = 'bakong';
                $payment->qr_payload = $charge['qr'];
                $payment->md5 = $charge['md5'];
                $payment->expires_at = now()->addMinutes(self::QR_TTL_MINUTES);
            } elseif ($method === 'aba_khqr') {
                $aba = app(AbaPayWayGateway::class);
                if ($aba->isConfigured()) {
                    $charge = $aba->createCharge($bill, $amount, $currency, $plan->name.' ('.$interval.')');
                    $payment->qr_payload = $charge['qr'];
                    $payment->deeplink = $charge['deeplink'];
                    $payment->provider_ref = $charge['ref'];
                }
                $payment->provider = 'aba';
                $payment->expires_at = now()->addMinutes(self::QR_TTL_MINUTES);
            }
            // COD: nothing to generate; awaits manual confirmation.

            $payment->save();
            return $payment;
        });
    }

    /** Poll the gateway for a pending QR payment and activate if it has settled. */
    public function refreshStatus(SubscriptionPayment $payment): SubscriptionPayment
    {
        if ($payment->status !== 'pending') return $payment;
        if ($payment->isExpired()) {
            $payment->update(['status' => 'canceled']);
            return $payment;
        }
        $paid = match ($payment->method) {
            'khqr' => $payment->md5 ? app(BakongGateway::class)->isPaid($payment->md5) : false,
            'aba_khqr' => $payment->provider_ref ? app(AbaPayWayGateway::class)->isPaid($payment->provider_ref) : false,
            default => false,
        };
        if ($paid) $this->markPaid($payment);
        return $payment->refresh();
    }

    /** Admin confirmation path (COD, or any method before gateway creds are live). */
    public function confirmManually(SubscriptionPayment $payment, ?int $adminId = null, ?string $note = null): SubscriptionPayment
    {
        $meta = $payment->meta ?? [];
        $meta['confirmed_by'] = $adminId;
        $payment->meta = $meta;
        if ($note) $payment->note = $note;
        $payment->save();
        $this->markPaid($payment);
        return $payment->refresh();
    }

    /** Flip a payment to paid and activate its subscription. Idempotent. */
    public function markPaid(SubscriptionPayment $payment): void
    {
        if ($payment->status === 'paid') return;
        DB::transaction(function () use ($payment) {
            $payment->update(['status' => 'paid', 'paid_at' => now()]);
            $sub = $payment->subscription()->firstOrFail();
            $this->activate($sub);
        });
    }

    /** Activate a subscription: open its period, provision the plan, retire any prior active sub. */
    public function activate(Subscription $sub): void
    {
        if ($sub->status === 'active') return;
        $company = Company::withoutGlobalScopes()->findOrFail($sub->company_id);

        // Retire the company's other active subscriptions.
        Subscription::withoutGlobalScopes()
            ->where('company_id', $company->id)->where('id', '!=', $sub->id)->where('status', 'active')
            ->update(['status' => 'canceled', 'canceled_at' => now()]);

        $start = now()->startOfDay();
        $end = $sub->interval === 'yearly' ? $start->copy()->addYear() : $start->copy()->addMonth();
        $sub->update([
            'status' => 'active',
            'started_at' => $sub->started_at ?? now(),
            'current_period_start' => $start->toDateString(),
            'current_period_end' => $end->toDateString(),
        ]);

        if ($sub->plan) {
            app(TenantProvisioner::class)->provisionPlan($company, $sub->plan->code);
        }
    }

    public function presentSubscription(Subscription $sub): array
    {
        return [
            'id' => $sub->id,
            'plan' => $sub->plan?->code,
            'plan_name' => $sub->plan?->name,
            'interval' => $sub->interval,
            'currency' => $sub->currency,
            'amount' => $sub->amount,
            'status' => $sub->status,
            'current_period_end' => optional($sub->current_period_end)->toDateString(),
        ];
    }
}
