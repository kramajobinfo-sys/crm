<?php
namespace App\Http\Controllers\Api\V1\Marketing;

use App\Http\Controllers\Controller;
use App\Http\Requests\Marketing\StoreCampaignRequest;
use App\Http\Resources\CampaignResource;
use App\Models\Campaign;
use App\Models\EmailAccount;
use App\Models\EmailTemplate;
use App\Models\SmsProvider;
use App\Services\MarketingService;
use App\Services\SmsSender;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use RuntimeException;
use Illuminate\Database\QueryException;

class CampaignController extends Controller
{
    public function __construct(private readonly MarketingService $marketing) {}

    public function index(Request $request): JsonResponse
    {
        $f = $request->validate([
            'q' => 'nullable|string|max:191',
            'type' => 'nullable|string|in:email,sms',
            'status' => 'nullable|string|in:all,draft,scheduled,running,sent,paused,cancelled',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);
        return $this->paginated($this->marketing->paginate($f, (int) ($f['per_page'] ?? 25)), CampaignResource::class);
    }

    public function stats(): JsonResponse
    {
        return $this->success($this->marketing->stats());
    }

    public function meta(): JsonResponse
    {
        return $this->success([
            'templates' => EmailTemplate::where('is_active', true)->orderBy('name')->get(['id','name','subject','body_html']),
            'email_accounts' => EmailAccount::where('is_active', true)->orderBy('name')->get(['id','name','email_address','is_default']),
            'sms_providers' => $this->marketing->smsProviders()->map(fn ($p) => [
                'id' => $p->id, 'name' => $p->name, 'sender_id' => $p->sender_id, 'is_default' => (bool) $p->is_default,
            ]),
            'types' => Campaign::TYPES,
        ]);
    }

    public function preview(Request $request): JsonResponse
    {
        $data = $request->validate([
            'type' => 'required|string|in:email,sms',
            'audience' => 'required|array',
            'audience.source' => 'required|string|in:customers,leads',
            'audience.filters' => 'nullable|array',
        ]);
        return $this->success($this->marketing->previewAudience($data['audience'], $data['type']));
    }

    public function show(int $id): JsonResponse
    {
        return $this->success(new CampaignResource($this->marketing->find($id)));
    }

    public function store(StoreCampaignRequest $request): JsonResponse
    {
        return $this->success(new CampaignResource($this->marketing->create($request->validated())), 'Campaign created', 201);
    }

    public function update(StoreCampaignRequest $request, int $id): JsonResponse
    {
        $campaign = Campaign::findOrFail($id);
        try {
            $campaign = $this->marketing->update($campaign, $request->validated());
        } catch (QueryException $e) {
            throw $e;   // DB failure: a 500, not a business-rule 422 (never echo SQL)
        } catch (RuntimeException $e) {
            return $this->error($e->getMessage(), 422);
        }
        return $this->success(new CampaignResource($campaign), 'Campaign updated');
    }

    public function destroy(int $id): JsonResponse
    {
        Campaign::findOrFail($id)->delete();
        return $this->success(null, 'Campaign deleted');
    }

    public function launch(int $id): JsonResponse
    {
        $campaign = Campaign::findOrFail($id);
        try {
            $campaign = $this->marketing->launch($campaign);
        } catch (QueryException $e) {
            throw $e;   // DB failure: a 500, not a business-rule 422 (never echo SQL)
        } catch (RuntimeException $e) {
            return $this->error($e->getMessage(), 422);
        }
        return $this->success(new CampaignResource($campaign), 'Campaign launched');
    }

    public function recipients(int $id): JsonResponse
    {
        $campaign = Campaign::findOrFail($id);
        return $this->success($this->marketing->recipients($campaign)->map(fn ($r) => [
            'id' => $r->id, 'name' => $r->name, 'email' => $r->email, 'phone' => $r->phone,
            'status' => $r->status, 'sent_at' => $r->sent_at?->toIso8601String(),
        ]));
    }

    public function smsProviders(): JsonResponse
    {
        return $this->success(
            SmsProvider::orderByDesc('is_default')->orderBy('name')->get()->map(fn ($p) => $this->presentSmsProvider($p))->all()
        );
    }

    public function storeSmsProvider(Request $request): JsonResponse
    {
        $data = $this->validateSmsProvider($request);
        $p = SmsProvider::create($this->smsProviderPayload($data, null));
        if (!empty($data['is_default'])) SmsProvider::where('id', '!=', $p->id)->update(['is_default' => false]);
        return $this->success($this->presentSmsProvider($p), 'SMS provider added', 201);
    }

    public function updateSmsProvider(Request $request, int $id): JsonResponse
    {
        $p = SmsProvider::findOrFail($id);
        $data = $this->validateSmsProvider($request);
        $p->update($this->smsProviderPayload($data, $p));
        if (!empty($data['is_default'])) SmsProvider::where('id', '!=', $p->id)->update(['is_default' => false]);
        return $this->success($this->presentSmsProvider($p->fresh()));
    }

    public function destroySmsProvider(int $id): JsonResponse
    {
        SmsProvider::findOrFail($id)->delete();
        return $this->success(null, 'Deleted');
    }

    public function testSmsProvider(Request $request, int $id, SmsSender $sender): JsonResponse
    {
        $data = $request->validate(['to' => 'required|string|max:32']);
        $p = SmsProvider::findOrFail($id);
        try {
            $sender->send($p, $data['to'], 'Krama CRM: SMS provider test message.');
            return $this->success(['ok' => true], 'Test message sent to ' . $data['to']);
        } catch (\Throwable $e) {
            return $this->success(['ok' => false, 'error' => substr($e->getMessage(), 0, 200)], 'Test failed');
        }
    }

    private function validateSmsProvider(Request $request): array
    {
        return $request->validate([
            'name' => 'required|string|max:128',
            'provider' => ['required', Rule::in(['twilio', 'generic'])],
            'sender_id' => 'nullable|string|max:32',
            'is_default' => 'nullable|boolean',
            'is_active' => 'nullable|boolean',
            'config' => 'nullable|array',
            'config.channel' => ['nullable', Rule::in(['sms', 'whatsapp'])],
            'config.account_sid' => 'nullable|string|max:128',
            'config.auth_token' => 'nullable|string|max:255',
            'config.url' => 'nullable|string|max:255',
        ]);
    }

    private function smsProviderPayload(array $data, ?SmsProvider $existing): array
    {
        $config = $existing?->config ?? [];
        foreach ($data['config'] ?? [] as $k => $v) {
            if ($k === 'auth_token' && ($v === '' || $v === null)) continue; // blank secret keeps stored value
            $config[$k] = $v;
        }
        return [
            'company_id' => $existing->company_id ?? auth()->user()->company_id,
            'name' => $data['name'], 'provider' => $data['provider'],
            'sender_id' => $data['sender_id'] ?? null,
            'is_default' => $data['is_default'] ?? ($existing->is_default ?? false),
            'is_active' => $data['is_active'] ?? ($existing->is_active ?? true),
            'config' => $config,
        ];
    }

    private function presentSmsProvider(SmsProvider $p): array
    {
        $c = $p->config ?? [];
        return [
            'id' => $p->id, 'name' => $p->name, 'provider' => $p->provider,
            'sender_id' => $p->sender_id, 'channel' => $c['channel'] ?? 'sms',
            'is_default' => $p->is_default, 'is_active' => $p->is_active,
            'configured' => app(SmsSender::class)->isConfigured($p),
            'config' => [
                'channel' => $c['channel'] ?? 'sms',
                'account_sid' => $c['account_sid'] ?? null,
                'url' => $c['url'] ?? null,
                'has_auth_token' => filled($c['auth_token'] ?? null),
            ],
        ];
    }
}
