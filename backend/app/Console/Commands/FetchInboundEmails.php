<?php
namespace App\Console\Commands;

use App\Models\EmailAccount;
use App\Services\ImapClient;
use App\Services\SmtpMailer;
use Illuminate\Console\Command;

/**
 * SalesInbox — pull new mail over IMAP for every account that has IMAP configured. Runs
 * unauthenticated (scheduler/console); ingestInbound keys everything off each account's own
 * company_id, never auth(). A per-account failure is reported and never aborts the others.
 * No-op in the dev stack (no account has IMAP config; Mailpit is SMTP-only).
 */
class FetchInboundEmails extends Command
{
    protected $signature = 'emails:fetch {--account= : Only this account id}';
    protected $description = 'Fetch inbound emails over IMAP into the CRM (SalesInbox)';

    public function handle(ImapClient $imap, SmtpMailer $mailer): int
    {
        $accounts = EmailAccount::withoutGlobalScopes()
            ->where('is_active', true)
            ->when($this->option('account'), fn ($q) => $q->whereKey((int) $this->option('account')))
            ->get()
            ->filter(fn ($a) => $mailer->isImapConfigured($a->config));

        if ($accounts->isEmpty()) {
            $this->info('No accounts with IMAP configured.');
            return self::SUCCESS;
        }

        foreach ($accounts as $account) {
            $r = $imap->fetch($account);
            $r['ok']
                ? $this->info("[{$account->name}] fetched {$r['ingested']} new message(s).")
                : $this->warn("[{$account->name}] {$r['message']}");
        }
        return self::SUCCESS;
    }
}
