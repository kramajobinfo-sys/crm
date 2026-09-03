<?php
namespace App\Http\Controllers\Api\V1\Email;

use App\Http\Controllers\Controller;
use App\Http\Requests\Email\StoreEmailRequest;
use App\Http\Resources\EmailResource;
use App\Models\Email;
use App\Models\EmailAccount;
use App\Models\EmailTemplate;
use App\Services\EmailService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class EmailController extends Controller
{
    public function __construct(private readonly EmailService $emails) {}

    public function index(Request $request): JsonResponse
    {
        $f = $request->validate([
            'q' => 'nullable|string|max:191',
            'folder' => 'nullable|string|in:inbox,sent,drafts',
            'account_id' => 'nullable|integer',
            'related_type' => 'nullable|string|in:deal,lead,customer',
            'related_id' => 'nullable|integer',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);
        return $this->paginated($this->emails->paginate($f, (int) ($f['per_page'] ?? 25)), EmailResource::class);
    }

    public function stats(): JsonResponse
    {
        return $this->success($this->emails->stats());
    }

    public function meta(): JsonResponse
    {
        return $this->success([
            'accounts' => $this->emails->accounts()->map(fn ($a) => [
                'id' => $a->id, 'name' => $a->name, 'email_address' => $a->email_address,
                'from_name' => $a->from_name, 'is_default' => (bool) $a->is_default, 'signature' => $a->signature,
            ]),
            'templates' => $this->emails->templates()->map(fn ($t) => [
                'id' => $t->id, 'name' => $t->name, 'category' => $t->category,
                'subject' => $t->subject, 'body_html' => $t->body_html,
            ]),
            'related_types' => array_keys(EmailService::RELATED_MAP),
        ]);
    }

    public function show(int $id): JsonResponse
    {
        return $this->success(new EmailResource($this->emails->find($id)));
    }

    public function store(StoreEmailRequest $request): JsonResponse
    {
        $data = $request->validated();
        $send = (bool) ($data['send'] ?? false);
        unset($data['send']);
        $email = $this->emails->compose($data, $send);
        return $this->success(new EmailResource($email), $this->sendResultMessage($email, $send ? null : 'Draft saved'), 201);
    }

    public function update(StoreEmailRequest $request, int $id): JsonResponse
    {
        $email = Email::findOrFail($id);
        $data = $request->validated();
        unset($data['send']);
        return $this->success(new EmailResource($this->emails->update($email, $data)), 'Draft updated');
    }

    public function send(int $id): JsonResponse
    {
        $email = Email::findOrFail($id);
        $email = $this->emails->send($email);
        return $this->success(new EmailResource($email), $this->sendResultMessage($email));
    }

    /** compose()/send() persist the row either way — a failed delivery is not an HTTP error,
     *  it's a 201/200 whose body says status=failed. The envelope message must say so too. */
    private function sendResultMessage(Email $email, ?string $fallback = null): string
    {
        return match ($email->status) {
            'sent' => 'Email sent',
            'failed' => 'Could not send: '.($email->error ?: 'unknown error'),
            default => $fallback ?? 'Saved',
        };
    }

    public function destroy(int $id): JsonResponse
    {
        Email::findOrFail($id)->delete();
        return $this->success(null, 'Email deleted');
    }

    // ---- templates -------------------------------------------------------

    public function storeTemplate(Request $request): JsonResponse
    {
        $companyId = $request->user()->company_id;
        $data = $request->validate([
            'name' => 'required|string|max:128',
            'code' => ['required','string','max:32', Rule::unique('email_templates','code')->where('company_id',$companyId)],
            'category' => 'nullable|string|max:48',
            'subject' => 'required|string|max:255',
            'body_html' => 'required|string|max:100000',
        ]);
        $tpl = $this->emails->createTemplate($data);
        return $this->success(['id' => $tpl->id, 'name' => $tpl->name], 'Template created', 201);
    }

    public function updateTemplate(Request $request, int $id): JsonResponse
    {
        $tpl = EmailTemplate::findOrFail($id);
        $data = $request->validate([
            'name' => 'sometimes|string|max:128',
            'category' => 'nullable|string|max:48',
            'subject' => 'sometimes|string|max:255',
            'body_html' => 'sometimes|string|max:100000',
            'is_active' => 'nullable|boolean',
        ]);
        $this->emails->updateTemplate($tpl, $data);
        return $this->success(['id' => $tpl->id, 'name' => $tpl->name], 'Template updated');
    }

    public function destroyTemplate(int $id): JsonResponse
    {
        EmailTemplate::findOrFail($id)->delete();
        return $this->success(null, 'Template deleted');
    }

    // ---- accounts --------------------------------------------------------

    public function accounts(): JsonResponse
    {
        return $this->success($this->emails->accounts()->map(fn ($a) => [
            'id' => $a->id, 'name' => $a->name, 'email_address' => $a->email_address,
            'provider' => $a->provider, 'is_shared' => (bool) $a->is_shared,
            'is_default' => (bool) $a->is_default, 'is_active' => (bool) $a->is_active,
            'configured' => $this->emails->mailerConfigured($a),
        ]));
    }

    public function showAccount(int $id): JsonResponse
    {
        $acc = EmailAccount::findOrFail($id);
        return $this->success($this->emails->accountDetail($acc));
    }

    public function storeAccount(Request $request): JsonResponse
    {
        $data = $this->accountRules($request);
        $acc = $this->emails->createAccount($data);
        return $this->success(['id' => $acc->id, 'name' => $acc->name], 'Account added', 201);
    }

    public function updateAccount(Request $request, int $id): JsonResponse
    {
        $acc = EmailAccount::findOrFail($id);
        $data = $this->accountRules($request, $acc->id);
        $this->emails->updateAccount($acc, $data);
        return $this->success($this->emails->accountDetail($acc->fresh()), 'Account updated');
    }

    public function testAccount(Request $request, int $id): JsonResponse
    {
        $acc = EmailAccount::findOrFail($id);
        $data = $request->validate(['to' => 'nullable|email']);
        $result = $this->emails->testAccount($acc, $data['to'] ?? null);
        return $result['ok'] ? $this->success($result, $result['message']) : $this->error($result['message'], 422);
    }

    private function accountRules(Request $request, ?int $ignoreId = null): array
    {
        $companyId = $request->user()->company_id;
        return $request->validate([
            'name' => 'required|string|max:128',
            'email_address' => ['required','email','max:191',
                Rule::unique('email_accounts','email_address')->where('company_id',$companyId)->ignore($ignoreId)],
            'from_name' => 'nullable|string|max:128',
            'provider' => ['nullable', Rule::in(EmailAccount::PROVIDERS)],
            'is_shared' => 'nullable|boolean',
            'is_default' => 'nullable|boolean',
            'is_active' => 'nullable|boolean',
            'signature' => 'nullable|string|max:2000',
            'config' => 'nullable|array',
            'config.host' => 'nullable|string|max:191',
            'config.port' => 'nullable|integer|min:1|max:65535',
            'config.encryption' => ['nullable', Rule::in(['tls', 'ssl', ''])],
            'config.username' => 'nullable|string|max:191',
            'config.password' => 'nullable|string|max:191',
            // IMAP (SalesInbox receive side)
            'config.imap_host' => 'nullable|string|max:191',
            'config.imap_port' => 'nullable|integer|min:1|max:65535',
            'config.imap_encryption' => ['nullable', Rule::in(['tls', 'ssl', ''])],
            'config.imap_username' => 'nullable|string|max:191',
            'config.imap_password' => 'nullable|string|max:191',
            'config.imap_folder' => 'nullable|string|max:64',
        ]);
    }

    /**
     * Ingest one received message (SalesInbox). Provider-agnostic: a mail-provider inbound-parse
     * webhook, or a manual push, sends the parsed shape here. Idempotent by message_id.
     */
    public function inbound(Request $request): JsonResponse
    {
        $companyId = $request->user()->company_id;
        $data = $request->validate([
            'from_address' => 'required|email|max:191',
            'from_name'    => 'nullable|string|max:128',
            'to'           => 'nullable|array',
            'to.*'         => 'email',
            'cc'           => 'nullable|array',
            'cc.*'         => 'email',
            'subject'      => 'nullable|string|max:255',
            'body_html'    => 'nullable|string',
            'message_id'   => 'nullable|string|max:191',
            'in_reply_to'  => 'nullable|string|max:191',
            'received_at'  => 'nullable|date',
            'account_id'   => ['nullable','integer', Rule::exists('email_accounts','id')->where('company_id',$companyId)],
        ]);

        $account = !empty($data['account_id'])
            ? EmailAccount::findOrFail($data['account_id'])
            : $this->emails->resolveInboundAccount($data['to'] ?? []);
        if (!$account) return $this->error('No email account to receive into. Create one first.', 422);

        $email = $this->emails->ingestInbound($data, $account);
        return $this->success(new EmailResource($email), 'Message received', 201);
    }

    /** Pull new mail for one account over IMAP now (the manual trigger for the scheduled fetch). */
    public function fetch(int $id, \App\Services\ImapClient $imap): JsonResponse
    {
        $account = EmailAccount::findOrFail($id);
        $result = $imap->fetch($account);
        return $this->success($result, $result['ok']
            ? ($result['ingested'].' new message(s) fetched')
            : $result['message']);
    }
}
