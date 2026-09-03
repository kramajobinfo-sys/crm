<?php
namespace App\Services;

use App\Models\Email;
use App\Models\EmailAccount;
use Illuminate\Mail\Mailable;
use RuntimeException;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

/**
 * Real outbound delivery for the Email module, per email_account rather than one app-wide
 * mailer — each account's SMTP credentials live in its own encrypted `config` (same pattern as
 * chat_channels.config). Built on Laravel's MailManager::build() so a single credential set can
 * send an ad-hoc Mailable without a config/mail.php entry per tenant.
 */
class SmtpMailer
{
    /** Credential schema for the one provider actually wired: SMTP. `secret` fields are
     *  write-only (masked on read; blank on update means "keep the stored value"). */
    public function credentialFields(): array
    {
        return [
            ['key' => 'host', 'label' => 'SMTP host', 'required' => true],
            ['key' => 'port', 'label' => 'Port', 'required' => true],
            ['key' => 'encryption', 'label' => 'Encryption (tls, ssl, or blank for none)', 'required' => false],
            ['key' => 'username', 'label' => 'Username', 'required' => false],
            ['key' => 'password', 'label' => 'Password', 'required' => false, 'secret' => true],
        ];
    }

    /** IMAP credential schema (SalesInbox receive side). Kept separate from credentialFields so the
     *  send-side isConfigured() never demands IMAP settings. imap_password is write-only. */
    public function imapFields(): array
    {
        return [
            ['key' => 'imap_host', 'label' => 'IMAP host', 'required' => true],
            ['key' => 'imap_port', 'label' => 'IMAP port', 'required' => true],
            ['key' => 'imap_encryption', 'label' => 'IMAP encryption (ssl, tls, or blank)', 'required' => false],
            ['key' => 'imap_username', 'label' => 'IMAP username', 'required' => true],
            ['key' => 'imap_password', 'label' => 'IMAP password', 'required' => true, 'secret' => true],
            ['key' => 'imap_folder', 'label' => 'Folder (default INBOX)', 'required' => false],
        ];
    }

    /** SMTP + IMAP fields — what buildConfig/presentConfig operate over. */
    private function allFields(): array
    {
        return array_merge($this->credentialFields(), $this->imapFields());
    }

    /** Merge submitted config over the existing one — a blank secret keeps the stored value;
     *  a blank non-secret clears it. Mirrors ChannelService::buildConfig. */
    public function buildConfig(array $input, array $existing): array
    {
        $config = $existing;
        foreach ($this->allFields() as $f) {
            $key = $f['key'];
            if (!array_key_exists($key, $input)) continue;
            $value = is_string($input[$key]) ? trim($input[$key]) : $input[$key];
            if (($f['secret'] ?? false) && ($value === '' || $value === null)) continue;
            $config[$key] = $value;
        }
        return $config;
    }

    /** Read shape for the account editor — secrets are shown only as set/unset, never their value. */
    public function presentConfig(?array $config): array
    {
        $config ??= [];
        $out = [];
        foreach ($this->allFields() as $f) {
            $secret = (bool) ($f['secret'] ?? false);
            $out[$f['key']] = [
                'set' => filled($config[$f['key']] ?? null),
                'value' => $secret ? null : ($config[$f['key']] ?? null),
                'secret' => $secret,
            ];
        }
        return $out;
    }

    public function isConfigured(?array $config): bool
    {
        $config ??= [];
        foreach ($this->credentialFields() as $f) {
            if (($f['required'] ?? false) && empty($config[$f['key']])) return false;
        }
        return true;
    }

    /** True when the IMAP (receive) side is fully configured on this account. */
    public function isImapConfigured(?array $config): bool
    {
        $config ??= [];
        foreach ($this->imapFields() as $f) {
            if (($f['required'] ?? false) && empty($config[$f['key']])) return false;
        }
        return true;
    }

    /**
     * Actually opens a connection and sends a real message — not a structural field check like
     * the chat channel "test", since SMTP has no cheap live handshake without sending. Defaults
     * the test recipient to the account's own address.
     */
    public function testConnection(EmailAccount $account, ?string $to = null): array
    {
        if (!$this->isConfigured($account->config)) {
            return ['ok' => false, 'message' => 'SMTP host and port are required before testing.'];
        }
        try {
            $mailable = (new Mailable())
                ->from($account->email_address, $account->from_name ?: $account->name)
                ->to($to ?: $account->email_address)
                ->subject('Krama CRM — test connection')
                ->html('<p>This confirms the SMTP settings for "'.e($account->name).'" are working.</p>');
            $this->mailerFor($account)->send($mailable);
            return ['ok' => true, 'message' => 'Test email sent to '.($to ?: $account->email_address).'.'];
        } catch (\Throwable $e) {
            // Catches transport failures (auth/connection) *and* message-construction errors
            // (e.g. a malformed From/To) — this method must never let an exception escape, since
            // callers treat its return value as the whole answer, not a maybe-throws.
            return ['ok' => false, 'message' => $this->cleanMessage($e->getMessage())];
        }
    }

    /** Send a composed Email row for real. Throws on any failure — the caller (EmailService)
     *  decides how to record it; this method never mutates the Email row. */
    public function deliver(Email $email, ?EmailAccount $account): void
    {
        if (!$account) {
            throw new RuntimeException('No email account configured to send from.');
        }
        if (!$this->isConfigured($account->config)) {
            throw new RuntimeException('The "'.$account->name.'" account has no SMTP host/port configured.');
        }
        if (empty($email->to)) {
            throw new RuntimeException('No recipient address.');
        }

        $mailable = (new Mailable())
            ->from($account->email_address, $account->from_name ?: $account->name)
            ->to($email->to)
            ->subject($email->subject ?: '(no subject)')
            ->html($email->body_html ?: '');
        if (!empty($email->cc)) $mailable->cc($email->cc);
        if (!empty($email->bcc)) $mailable->bcc($email->bcc);

        try {
            $this->mailerFor($account)->send($mailable);
        } catch (TransportExceptionInterface $e) {
            throw new RuntimeException($this->cleanMessage($e->getMessage()), 0, $e);
        }
    }

    private function mailerFor(EmailAccount $account): \Illuminate\Mail\Mailer
    {
        $config = $account->config ?? [];
        // A blank/secret-skipped field is entirely absent from the array (see buildConfig), not
        // just falsy — ?? first before ?: so a missing key never raises an undefined-key notice.
        $scheme = (($config['encryption'] ?? '') ?: null) === 'ssl' ? 'smtps' : 'smtp';
        return app('mail.manager')->build([
            'transport' => 'smtp',
            'scheme' => $scheme,
            'host' => $config['host'] ?? null,
            'port' => (int) ($config['port'] ?? 587),
            'username' => ($config['username'] ?? '') ?: null,
            'password' => ($config['password'] ?? '') ?: null,
        ]);
    }

    /** Symfony's transport exceptions include a full response dump; keep just the useful part. */
    private function cleanMessage(string $message): string
    {
        return substr(explode("\n", $message)[0], 0, 300);
    }
}
