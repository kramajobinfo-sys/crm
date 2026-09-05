<?php
namespace App\Services;

use App\Models\ContactConsent;
use App\Models\Email;
use App\Models\EmailAccount;
use App\Models\EmailTemplate;
use App\Models\TimelineActivity;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class EmailService
{
    public function __construct(private readonly SmtpMailer $mailer) {}

    /** Reuse the same short-alias map Activities uses for polymorphic links. */
    public const RELATED_MAP = ActivityService::RELATED_MAP;

    public function paginate(array $f = [], int $perPage = 25): LengthAwarePaginator
    {
        return Email::query()
            ->with(['account:id,name,email_address', 'user:id,name', 'related'])
            ->folder($f['folder'] ?? null)
            ->search($f['q'] ?? null)
            ->when(!empty($f['account_id']), fn ($q) => $q->where('email_account_id', $f['account_id']))
            ->when(!empty($f['related_type']) && !empty($f['related_id']), fn ($q) =>
                $q->where('related_type', self::RELATED_MAP[$f['related_type']] ?? $f['related_type'])
                  ->where('related_id', $f['related_id']))
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    public function find(int $id): Email
    {
        return Email::with(['account:id,name,email_address', 'user:id,name', 'template:id,name',
            'attachments', 'related'])->findOrFail($id);
    }

    /** Compose an outbound email; when $send is true, attempt real SMTP delivery right after
     *  the row is persisted (outside the transaction — this is network I/O, not something to
     *  hold a DB lock open for). */
    public function compose(array $data, bool $send): Email
    {
        $email = DB::transaction(function () use ($data, $send) {
            $this->normaliseRelated($data);

            $companyId = $data['company_id'] ?? auth()->user()?->company_id;
            $accountQuery = EmailAccount::query()
                ->when($companyId, fn ($query) => $query->where('company_id', $companyId));

            $account = !empty($data['email_account_id'])
                ? (clone $accountQuery)->find($data['email_account_id'])
                : (clone $accountQuery)->where('is_default', true)->first()
                    ?? (clone $accountQuery)->where('is_active', true)->first();

            $data['email_account_id'] = $account?->id;
            $data['from_address'] ??= $account?->email_address;
            $data['from_name'] ??= $account?->from_name ?? $account?->name;
            $data['direction'] = 'outbound';
            $data['user_id'] ??= auth()->id();
            $data['status'] = $send ? 'queued' : 'draft';

            return Email::create($data);
        });

        if ($send) $this->deliverAndFinalize($email);
        return $this->find($email->id);
    }

    public function update(Email $email, array $data): Email
    {
        $this->normaliseRelated($data);
        $email->update($data);
        return $this->find($email->id);
    }

    /** Send a previously saved draft, or retry a failed send. */
    public function send(Email $email): Email
    {
        if (!in_array($email->status, ['draft', 'failed'], true)) {
            return $this->find($email->id);
        }
        $email->forceFill(['status' => 'queued'])->save();
        $this->deliverAndFinalize($email);
        return $this->find($email->id);
    }

    private function deliverAndFinalize(Email $email): void
    {
        if ($email->to && ContactConsent::isSuppressed($email->company_id, ['email'], $email->to)) {
            $email->forceFill(['status' => 'failed', 'error' => 'Blocked: recipient has opted out of email (consent).'])->save();
            return;
        }
        $account = $email->email_account_id ? EmailAccount::find($email->email_account_id) : null;
        try {
            $this->mailer->deliver($email, $account);
            $email->forceFill([
                'status' => 'sent', 'sent_at' => now(), 'message_id' => $this->messageId(), 'error' => null,
            ])->save();
            $this->recordTimeline($email);
        } catch (\Throwable $e) {
            $email->forceFill(['status' => 'failed', 'error' => substr($e->getMessage(), 0, 500)])->save();
        }
    }

    public function stats(): array
    {
        return [
            'sent_this_month' => Email::where('direction', 'outbound')->where('status', 'sent')
                ->whereMonth('sent_at', now()->month)->whereYear('sent_at', now()->year)->count(),
            'drafts'   => Email::where('status', 'draft')->count(),
            'inbound'  => Email::where('direction', 'inbound')->count(),
            'templates'=> EmailTemplate::where('is_active', true)->count(),
            'open_rate' => $this->openRate(),
        ];
    }

    private function openRate(): ?float
    {
        $sent = Email::where('direction', 'outbound')->where('status', 'sent')->count();
        if ($sent === 0) return null;
        $opened = Email::where('direction', 'outbound')->where('status', 'sent')->where('opens', '>', 0)->count();
        return round($opened / $sent * 100, 1);
    }

    // ---- templates & accounts -------------------------------------------

    public function templates(): \Illuminate\Support\Collection
    {
        return EmailTemplate::orderBy('name')->get();
    }

    public function createTemplate(array $data): EmailTemplate { return EmailTemplate::create($data); }

    public function updateTemplate(EmailTemplate $tpl, array $data): EmailTemplate { $tpl->update($data); return $tpl; }

    public function accounts(): \Illuminate\Support\Collection
    {
        return EmailAccount::orderByDesc('is_default')->orderBy('name')->get();
    }

    public function createAccount(array $data): EmailAccount
    {
        if (!empty($data['is_default'])) EmailAccount::where('is_default', true)->update(['is_default' => false]);
        $data['config'] = $this->mailer->buildConfig($data['config'] ?? [], []);
        return EmailAccount::create($data);
    }

    public function updateAccount(EmailAccount $acc, array $data): EmailAccount
    {
        if (!empty($data['is_default'])) EmailAccount::where('id', '!=', $acc->id)->where('is_default', true)->update(['is_default' => false]);
        if (array_key_exists('config', $data)) {
            $data['config'] = $this->mailer->buildConfig($data['config'] ?? [], $acc->config ?? []);
        }
        $acc->update($data);
        return $acc;
    }

    /** Edit-form shape: account fields plus masked credential status (never secret values). */
    public function accountDetail(EmailAccount $acc): array
    {
        return [
            'id' => $acc->id, 'name' => $acc->name, 'email_address' => $acc->email_address,
            'from_name' => $acc->from_name, 'is_shared' => (bool) $acc->is_shared,
            'is_default' => (bool) $acc->is_default, 'is_active' => (bool) $acc->is_active,
            'signature' => $acc->signature,
            'configured' => $this->mailer->isConfigured($acc->config),
            'config' => $this->mailer->presentConfig($acc->config),
        ];
    }

    public function testAccount(EmailAccount $acc, ?string $to): array
    {
        return $this->mailer->testConnection($acc, $to);
    }

    public function mailerConfigured(EmailAccount $acc): bool
    {
        return $this->mailer->isConfigured($acc->config);
    }

    // ---- inbound (SalesInbox) -------------------------------------------

    /**
     * Ingest one received message. Idempotent by message_id (a re-fetch of the same mail returns
     * the existing row, never a duplicate). Matches the sender to a Contact→Customer / Customer /
     * Lead so the email lands on that record's timeline; a reply with no sender match inherits the
     * record of the message it answers (in_reply_to). $msg keys: message_id?, in_reply_to?,
     * from_address, from_name?, to?, cc?, subject?, body_html?, received_at?.
     */
    public function ingestInbound(array $msg, EmailAccount $account): Email
    {
        $companyId = $account->company_id;
        $messageId = $msg['message_id'] ?? null;

        if ($messageId) {
            $existing = Email::where('company_id', $companyId)->where('message_id', $messageId)->first();
            if ($existing) return $this->find($existing->id);
        }

        return DB::transaction(function () use ($msg, $account, $companyId, $messageId) {
            [$relatedType, $relatedId] = $this->matchSender($companyId, $msg['from_address'] ?? null);

            // No sender match? Inherit the record of the message this one replies to.
            if (!$relatedType && !empty($msg['in_reply_to'])) {
                $parent = Email::where('company_id', $companyId)->where('message_id', $msg['in_reply_to'])->first();
                if ($parent && $parent->related_type) { $relatedType = $parent->related_type; $relatedId = $parent->related_id; }
            }

            $email = Email::create([
                'company_id' => $companyId,
                'email_account_id' => $account->id,
                'direction' => 'inbound',
                'status' => 'received',
                'from_address' => $msg['from_address'] ?? null,
                'from_name' => $msg['from_name'] ?? null,
                'to' => $msg['to'] ?? [$account->email_address],
                'cc' => $msg['cc'] ?? null,
                'subject' => $msg['subject'] ?? null,
                'body_html' => $msg['body_html'] ?? null,
                'message_id' => $messageId,
                'in_reply_to' => $msg['in_reply_to'] ?? null,
                'related_type' => $relatedType,
                'related_id' => $relatedId,
                'received_at' => $msg['received_at'] ?? now(),
            ]);

            $this->recordTimeline($email);
            return $this->find($email->id);
        });
    }

    /** Resolve a sender address to [related_type, related_id]: Contact→its Customer, else Customer,
     *  else Lead. Returns [null, null] when nothing matches. */
    private function matchSender(int $companyId, ?string $email): array
    {
        $email = $email ? strtolower(trim($email)) : null;
        if (!$email) return [null, null];

        $contact = \App\Models\Contact::where('company_id', $companyId)
            ->whereRaw('LOWER(email) = ?', [$email])->whereNotNull('customer_id')->first();
        if ($contact) return [\App\Models\Customer::class, $contact->customer_id];

        $customer = \App\Models\Customer::where('company_id', $companyId)
            ->whereRaw('LOWER(email) = ?', [$email])->first();
        if ($customer) return [\App\Models\Customer::class, $customer->id];

        $lead = \App\Models\Lead::where('company_id', $companyId)
            ->whereRaw('LOWER(email) = ?', [$email])->first();
        if ($lead) return [\App\Models\Lead::class, $lead->id];

        return [null, null];
    }

    /** Resolve which account an inbound message belongs to — by a recipient matching an account's
     *  address, else the default/first active account. */
    public function resolveInboundAccount(array $recipients): ?EmailAccount
    {
        $addrs = array_map(fn ($a) => strtolower(trim($a)), array_filter($recipients));
        if ($addrs) {
            $acc = EmailAccount::whereIn(DB::raw('LOWER(email_address)'), $addrs)->first();
            if ($acc) return $acc;
        }
        return EmailAccount::where('is_default', true)->first() ?? EmailAccount::where('is_active', true)->first();
    }

    // ---- helpers --------------------------------------------------------

    private function normaliseRelated(array &$data): void
    {
        if (!array_key_exists('related_type', $data)) return;
        $alias = $data['related_type'];
        if ($alias === null || $alias === '') { $data['related_type'] = null; $data['related_id'] = null; return; }
        $data['related_type'] = self::RELATED_MAP[$alias] ?? $alias;
    }

    private function recordTimeline(Email $email): void
    {
        if (!$email->related_type || !$email->related_id) return;
        $related = $email->related()->first();
        if ($related) TimelineActivity::record($related, 'email', 'Email: '.($email->subject ?: '(no subject)'));
    }

    private function messageId(): string
    {
        return sprintf('<%s@krama.local>', bin2hex(random_bytes(12)));
    }
}
