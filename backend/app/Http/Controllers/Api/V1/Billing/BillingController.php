<?php
namespace App\Http\Controllers\Api\V1\Billing;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\SubscriptionPayment;
use App\Services\BillingService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Member-facing subscription billing: browse priced plans, start a checkout with a payment method,
 * and poll a QR payment until it settles. Gated by the tenant's own settings permissions (billing is
 * intentionally NOT behind a plan feature gate — a company must always be able to subscribe/upgrade).
 */
class BillingController extends Controller
{
    use ApiResponse;

    public function __construct(private BillingService $billing) {}

    private function company(Request $request): Company
    {
        return Company::findOrFail($request->user()->company_id);
    }

    public function catalogue(Request $request): JsonResponse
    {
        $currency = strtoupper((string) $request->query('currency', 'USD'));
        return $this->success($this->billing->catalogue($this->company($request), $currency));
    }

    public function checkout(Request $request): JsonResponse
    {
        $data = $request->validate([
            'plan' => ['required', 'string', Rule::exists('plans', 'code')],
            'interval' => ['required', Rule::in(['monthly', 'yearly'])],
            'currency' => ['required', Rule::in(['USD', 'KHR'])],
            'method' => ['required', Rule::in(SubscriptionPayment::METHODS)],
        ]);
        try {
            $payment = $this->billing->checkout(
                $this->company($request), $data['plan'], $data['interval'], $data['currency'], $data['method'],
            );
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 422);
        }
        return $this->success($this->present($payment), 'Checkout started', 201);
    }

    public function payment(Request $request, int $id): JsonResponse
    {
        $payment = SubscriptionPayment::where('id', $id)->firstOrFail();  // company-scoped by trait
        $payment = $this->billing->refreshStatus($payment);
        return $this->success($this->present($payment));
    }

    public function subscription(Request $request): JsonResponse
    {
        $sub = $this->company($request)->activeSubscription();
        return $this->success($sub ? $this->billing->presentSubscription($sub) : null);
    }

    /**
     * Public ABA PayWay pushback (no auth). We never trust the callback body: we look the payment up
     * by its transaction id and independently re-query PayWay for the real status before activating.
     */
    public function abaCallback(Request $request): JsonResponse
    {
        $tranId = (string) ($request->input('tran_id') ?: $request->input('tranId'));
        if ($tranId !== '') {
            $payment = SubscriptionPayment::withoutGlobalScopes()
                ->where('provider', 'aba')->where('provider_ref', $tranId)->where('status', 'pending')->first();
            if ($payment) $this->billing->refreshStatus($payment);
        }
        return $this->success(null, 'OK');
    }

    private function present(SubscriptionPayment $p): array
    {
        return [
            'id' => $p->id,
            'subscription_id' => $p->subscription_id,
            'method' => $p->method,
            'provider' => $p->provider,
            'status' => $p->status,
            'currency' => $p->currency,
            'amount' => $p->amount,
            'qr_payload' => $p->qr_payload,
            'deeplink' => $p->deeplink,
            'expires_at' => optional($p->expires_at)->toIso8601String(),
            'paid_at' => optional($p->paid_at)->toIso8601String(),
            'instructions' => $p->method === 'cod' ? ($this->billing->settings()->cod_instructions ?? '') : null,
            // For QR methods that have no live gateway creds yet, tell the UI it's awaiting manual review.
            'awaiting_manual' => $p->status === 'pending' && $p->method !== 'cod' && !$p->qr_payload,
        ];
    }
}
