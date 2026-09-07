<?php
namespace App\Http\Controllers\Api\V1\Platform;

use App\Http\Controllers\Controller;
use App\Models\BillingSetting;
use App\Models\Plan;
use App\Models\PlanPrice;
use App\Models\SubscriptionPayment;
use App\Services\BillingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Platform console: configure the SaaS billing — payment-method toggles, gateway credentials, plan
 * pricing, and manual confirmation of pending payments. Platform admins only. Credentials are write-only
 * over the API (never returned); a blank credential field on update keeps the stored value.
 */
class BillingSettingController extends Controller
{
    public function __construct(private BillingService $billing) {}

    public function show(): JsonResponse
    {
        $s = BillingSetting::singleton();
        return $this->success([
            'enabled' => $s->enabled ?? ['cod' => true, 'khqr' => false, 'aba_khqr' => false],
            'cod_instructions' => $s->cod_instructions,
            'config' => $s->publicConfig(),
            'status' => [
                'khqr' => $s->methodConfigured('khqr'),
                'aba_khqr' => $s->methodConfigured('aba_khqr'),
            ],
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'enabled' => 'array',
            'enabled.cod' => 'boolean',
            'enabled.khqr' => 'boolean',
            'enabled.aba_khqr' => 'boolean',
            'cod_instructions' => 'nullable|string|max:2000',
            'khqr' => 'array',
            'khqr.merchant_name' => 'nullable|string|max:64',
            'khqr.merchant_city' => 'nullable|string|max:32',
            'khqr.bakong_id' => 'nullable|string|max:64',
            'khqr.api_base' => 'nullable|string|max:191',
            'khqr.api_token' => 'nullable|string|max:2000',
            'aba' => 'array',
            'aba.merchant_id' => 'nullable|string|max:64',
            'aba.base_url' => 'nullable|string|max:191',
            'aba.api_key' => 'nullable|string|max:2000',
            'aba.sandbox' => 'boolean',
        ]);

        $s = BillingSetting::singleton();
        $config = $s->config ?? [];

        if (array_key_exists('enabled', $data)) {
            $s->enabled = array_merge($s->enabled ?? [], $data['enabled']);
        }
        if (array_key_exists('cod_instructions', $data)) {
            $s->cod_instructions = $data['cod_instructions'];
        }

        // Merge non-secret fields always; only overwrite a secret when a non-empty value is supplied.
        foreach (['khqr' => ['merchant_name', 'merchant_city', 'bakong_id', 'api_base'],
                  'aba' => ['merchant_id', 'base_url']] as $group => $fields) {
            foreach ($fields as $f) {
                if (isset($data[$group][$f])) $config[$group][$f] = $data[$group][$f];
            }
        }
        if (isset($data['aba']['sandbox'])) $config['aba']['sandbox'] = (bool) $data['aba']['sandbox'];
        if (!empty($data['khqr']['api_token'])) $config['khqr']['api_token'] = $data['khqr']['api_token'];
        if (!empty($data['aba']['api_key'])) $config['aba']['api_key'] = $data['aba']['api_key'];

        $s->config = $config;
        $s->save();

        return $this->success(null, 'Billing settings saved');
    }

    /** Full pricing matrix for every plan (both intervals × both currencies). */
    public function plans(): JsonResponse
    {
        $plans = Plan::with('prices')->orderBy('sort_order')->get()->map(function (Plan $p) {
            $prices = [];
            foreach (['monthly', 'yearly'] as $interval) {
                foreach (['USD', 'KHR'] as $cur) {
                    $prices[$interval][$cur] = optional($p->priceFor($interval, $cur))->amount;
                }
            }
            return ['code' => $p->code, 'name' => $p->name, 'modules_count' => $p->features()->count(), 'prices' => $prices];
        });
        return $this->success($plans);
    }

    public function updatePrices(Request $request, string $code): JsonResponse
    {
        $plan = Plan::where('code', $code)->firstOrFail();
        $data = $request->validate([
            'prices' => 'required|array',
            'prices.*.interval' => ['required', Rule::in(['monthly', 'yearly'])],
            'prices.*.currency' => ['required', Rule::in(['USD', 'KHR'])],
            'prices.*.amount' => 'required|numeric|min:0',
        ]);
        foreach ($data['prices'] as $row) {
            PlanPrice::updateOrCreate(
                ['plan_id' => $plan->id, 'interval' => $row['interval'], 'currency' => $row['currency']],
                ['amount' => $row['amount'], 'is_active' => true],
            );
        }
        return $this->success(null, 'Prices updated');
    }

    /** Cross-tenant subscription payments, newest first; filter by status (default pending). */
    public function payments(Request $request): JsonResponse
    {
        $status = $request->query('status', 'pending');
        $q = SubscriptionPayment::withoutGlobalScopes()
            ->with(['plan:id,code,name', 'company:id,name,code'])
            ->when($status && $status !== 'all', fn ($w) => $w->where('status', $status))
            ->orderByDesc('id');
        return $this->paginated($q->paginate((int) $request->query('per_page', 25)));
    }

    public function confirmPayment(Request $request, int $id): JsonResponse
    {
        $payment = SubscriptionPayment::withoutGlobalScopes()->findOrFail($id);
        if ($payment->status === 'paid') {
            return $this->error('Payment is already confirmed.', 422);
        }
        $note = $request->validate(['note' => 'nullable|string|max:255'])['note'] ?? null;
        $this->billing->confirmManually($payment, $request->user()->id, $note);
        return $this->success(null, 'Payment confirmed and plan activated');
    }
}
