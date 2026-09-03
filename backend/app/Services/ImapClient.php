<?php
namespace App\Services;

use App\Models\EmailAccount;
use Webklex\PHPIMAP\ClientManager;

/**
 * SalesInbox IMAP transport. Connects to an account's mailbox (creds in the encrypted
 * email_accounts.config), pulls messages newer than the stored high-water UID, and hands each to
 * EmailService::ingestInbound (which dedupes, matches and threads). Fetching only ever ADDS inbound
 * rows through that one path.
 *
 * NOTE: this cannot be exercised in the dev stack — Mailpit is SMTP-only and there is no IMAP
 * server. It is written against webklex/php-imap v6 and fully guarded so a run against a mailbox
 * with no/invalid IMAP config is a clean, reported no-op rather than a fatal. See docs/SALESINBOX_SCOPE.md.
 */
class ImapClient
{
    public function __construct(
        private readonly EmailService $emails,
        private readonly SmtpMailer $mailer,
    ) {}

    /** @return array{ok: bool, ingested: int, message?: string, account: string} */
    public function fetch(EmailAccount $account): array
    {
        $base = ['account' => $account->name, 'ingested' => 0];
        $config = $account->config ?? [];

        if (!$this->mailer->isImapConfigured($config)) {
            return $base + ['ok' => false, 'message' => 'IMAP is not configured on this account.'];
        }

        try {
            $client = (new ClientManager())->make([
                'host'          => $config['imap_host'],
                'port'          => (int) $config['imap_port'],
                'encryption'    => ($config['imap_encryption'] ?? '') ?: false,   // 'ssl' | 'tls' | false
                'validate_cert' => true,
                'username'      => $config['imap_username'],
                'password'      => $config['imap_password'],
                'protocol'      => 'imap',
            ]);
            $client->connect();

            $folder = $client->getFolder($config['imap_folder'] ?? 'INBOX');
            $lastUid = (int) ($account->imap_last_uid ?? 0);

            $messages = $folder->query()->all()->setFetchOrder('asc')->get();

            $ingested = 0;
            $maxUid = $lastUid;
            foreach ($messages as $message) {
                $uid = (int) $message->getUid();
                if ($uid <= $lastUid) continue;                 // already processed
                $this->emails->ingestInbound($this->normalise($message, $account), $account);
                $ingested++;
                $maxUid = max($maxUid, $uid);
            }

            if ($maxUid > $lastUid) $account->forceFill(['imap_last_uid' => $maxUid])->save();

            return $base + ['ok' => true, 'ingested' => $ingested];
        } catch (\Throwable $e) {
            return $base + ['ok' => false, 'message' => 'IMAP fetch failed: '.substr($e->getMessage(), 0, 200)];
        }
    }

    /** Normalise a webklex message into the shape EmailService::ingestInbound expects. */
    private function normalise($message, EmailAccount $account): array
    {
        $from = $message->getFrom()[0] ?? null;
        $to = collect($message->getTo() ?? [])->map(fn ($a) => $a->mail)->filter()->values()->all();

        return [
            'message_id'   => (string) $message->getMessageId() ?: null,
            'in_reply_to'  => (string) $message->getInReplyTo() ?: null,
            'from_address' => $from?->mail,
            'from_name'    => $from?->personal ?: null,
            'to'           => $to ?: [$account->email_address],
            'subject'      => (string) $message->getSubject() ?: null,
            'body_html'    => $message->getHTMLBody() ?: ($message->getTextBody() ? nl2br(e($message->getTextBody())) : null),
            'received_at'  => optional($message->getDate())->toDate() ?? now(),
        ];
    }
}
