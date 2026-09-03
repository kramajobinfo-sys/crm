<?php
namespace App\Services;

use App\Models\Campaign;
use App\Models\CampaignMessage;
use App\Models\Customer;
use App\Models\Lead;
use App\Models\SmsProvider;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class MarketingService
{
    public function paginate(array $f = [], int $perPage = 25): LengthAwarePaginator
    {
        return Campaign::query()
            ->with('creator:id,name')
            ->search($f['q'] ?? null)
            ->when(!empty($f['type']), fn ($q) => $q->where('type', $f['type']))
            ->when(!empty($f['status']) && $f['status'] !== 'all', fn ($q) => $q->where('status', $f['status']))
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    public function find(int $id): Campaign
    {
        return Campaign::with(['creator:id,name', 'template:id,name', 'emailAccount:id,name', 'smsProvider:id,name'])
            ->findOrFail($id);
    }

    public function create(array $data): Campaign
    {
        $data['created_by'] ??= auth()->id();
        $data['status'] = ($data['scheduled_at'] ?? null) ? 'scheduled' : 'draft';
        $campaign = Campaign::create($data);
        $campaign->forceFill(['recipients_count' => $this->resolveAudience($campaign)->count()])->save();
        return $this->find($campaign->id);
    }

    public function update(Campaign $campaign, array $data): Campaign
    {
        if (!$campaign->isEditable()) throw new RuntimeException('A campaign that has been sent cannot be edited.');
        $campaign->update($data);
        $campaign->forceFill(['recipients_count' => $this->resolveAudience($campaign)->count()])->save();
        return $this->find($campaign->id);
    }

    /** Count the current audience without materialising it (for the compose UI). */
    public function previewAudience(array $audience, string $type): array
    {
        $rows = $this->queryAudience($audience);
        $field = $type === 'sms' ? 'phone' : 'email';
        $reachable = $rows->filter(fn ($r) => filled($r[$field] ?? null))->count();
        return ['total' => $rows->count(), 'reachable' => $reachable];
    }

    /**
     * Launch: materialise recipients from the audience, create one message each, mark sent.
     * Structural only — no live email/SMS provider is contacted.
     */
    public function launch(Campaign $campaign): Campaign
    {
        if (!in_array($campaign->status, ['draft', 'scheduled'], true)) {
            throw new RuntimeException('Only a draft or scheduled campaign can be launched.');
        }
        $field = $campaign->type === 'sms' ? 'phone' : 'email';
        $rows = $this->resolveAudience($campaign)->filter(fn ($r) => filled($r[$field]));

        if ($rows->isEmpty()) throw new RuntimeException('No reachable recipients in this audience.');

        return DB::transaction(function () use ($campaign, $rows, $field) {
            $campaign->recipients()->delete();
            $campaign->messages()->delete();

            $sent = 0;
            foreach ($rows as $r) {
                $recipient = $campaign->recipients()->create([
                    'company_id' => $campaign->company_id,
                    'recipient_type' => $r['type'], 'recipient_id' => $r['id'],
                    'name' => $r['name'], 'email' => $r['email'] ?? null, 'phone' => $r['phone'] ?? null,
                    'status' => 'sent', 'sent_at' => now(),
                ]);
                CampaignMessage::create([
                    'company_id' => $campaign->company_id, 'campaign_id' => $campaign->id,
                    'campaign_recipient_id' => $recipient->id, 'channel' => $campaign->type,
                    'to_address' => $r[$field], 'subject' => $campaign->subject, 'body' => $campaign->body,
                    'status' => 'sent', 'message_id' => sprintf('<%s@krama.local>', bin2hex(random_bytes(8))), 'sent_at' => now(),
                ]);
                $sent++;
            }

            $campaign->forceFill([
                'status' => 'sent', 'sent_at' => now(),
                'recipients_count' => $sent, 'sent_count' => $sent, 'failed_count' => 0,
            ])->save();
            return $this->find($campaign->id);
        });
    }

    public function recipients(Campaign $campaign, int $limit = 100): Collection
    {
        return $campaign->recipients()->orderByDesc('id')->limit($limit)->get();
    }

    public function stats(): array
    {
        return [
            'total'     => Campaign::count(),
            'draft'     => Campaign::where('status', 'draft')->count(),
            'scheduled' => Campaign::where('status', 'scheduled')->count(),
            'sent'      => Campaign::where('status', 'sent')->count(),
            'recipients_reached' => (int) Campaign::sum('sent_count'),
            'avg_open_rate' => $this->avgOpenRate(),
        ];
    }

    private function avgOpenRate(): ?float
    {
        $sent = Campaign::where('status', 'sent')->where('sent_count', '>', 0)->get();
        if ($sent->isEmpty()) return null;
        return round($sent->avg(fn ($c) => $c->opened_count / max($c->sent_count, 1) * 100), 1);
    }

    // ---- audience --------------------------------------------------------

    private function resolveAudience(Campaign $campaign): Collection
    {
        return $this->queryAudience($campaign->audience ?? []);
    }

    /** Turn an audience spec into a uniform [type,id,name,email,phone] collection. */
    private function queryAudience(array $audience): Collection
    {
        $source = $audience['source'] ?? 'customers';
        $filters = $audience['filters'] ?? [];

        if ($source === 'leads') {
            return Lead::query()->open()
                ->when(!empty($filters['rating']), fn ($q) => $q->where('rating', $filters['rating']))
                ->when(!empty($filters['status_id']), fn ($q) => $q->where('status_id', $filters['status_id']))
                ->get(['id', 'name', 'email', 'phone'])
                ->map(fn ($l) => ['type' => Lead::class, 'id' => $l->id, 'name' => $l->name, 'email' => $l->email, 'phone' => $l->phone]);
        }

        return Customer::query()
            ->when(!empty($filters['status']), fn ($q) => $q->where('status', $filters['status']))
            ->when(!empty($filters['group_id']), fn ($q) => $q->where('group_id', $filters['group_id']))
            ->get(['id', 'name', 'email', 'phone', 'mobile'])
            ->map(fn ($c) => ['type' => Customer::class, 'id' => $c->id, 'name' => $c->name, 'email' => $c->email, 'phone' => $c->phone ?: $c->mobile]);
    }

    // ---- sms providers ---------------------------------------------------

    public function smsProviders(): Collection
    {
        return SmsProvider::orderByDesc('is_default')->orderBy('name')->get();
    }

    public function createSmsProvider(array $data): SmsProvider
    {
        if (!empty($data['is_default'])) SmsProvider::where('is_default', true)->update(['is_default' => false]);
        return SmsProvider::create($data);
    }
}
