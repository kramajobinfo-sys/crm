<?php

namespace App\Jobs;

use App\Models\Campaign;
use App\Models\CampaignMessage;
use App\Models\CampaignRecipient;
use App\Models\ContactConsent;
use App\Models\EmailAccount;
use App\Services\SmtpMailer;
use App\Support\UnsubscribeToken;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

/**
 * Real delivery of one marketing EMAIL campaign message. Dispatched per recipient by
 * MarketingService::launch onto the "emails" queue. Idempotent (only acts on a 'queued'
 * message), re-checks opt-out at send time, and advances campaign counters exactly once.
 */
class SendCampaignMessage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    /** @var array<int,int> */
    public array $backoff = [60, 300, 900];

    public function __construct(public readonly int $messageId) {}

    public function handle(SmtpMailer $mailer): void
    {
        $msg = CampaignMessage::withoutGlobalScopes()->find($this->messageId);
        if (!$msg || $msg->status !== 'queued' || $msg->channel !== 'email') return;

        $campaign = Campaign::withoutGlobalScopes()->find($msg->campaign_id);
        if (!$campaign) return;

        // Opt-out may have happened between launch and send — honor it.
        if (ContactConsent::isSuppressed($msg->company_id, ['email', 'marketing'], $msg->to_address)) {
            $this->settle($msg, $campaign, 'suppressed');
            return;
        }

        $unsubUrl = route('unsubscribe', ['token' => UnsubscribeToken::encode($msg->company_id, $msg->to_address, $campaign->id)]);
        $html = $this->renderHtml($msg, $unsubUrl);
        $subject = $msg->subject ?: '(no subject)';
        // RFC 2369 + RFC 8058 one-click unsubscribe (Gmail/Outlook show a native Unsubscribe control).
        $headers = [
            'List-Unsubscribe' => '<' . $unsubUrl . '>',
            'List-Unsubscribe-Post' => 'List-Unsubscribe=One-Click',
        ];
        $account = $this->accountFor($campaign, $msg->company_id, $mailer);

        if ($account) {
            $mailer->sendRaw($account, $msg->to_address, $subject, $html, $headers);
        } else {
            // No account SMTP configured — fall back to the app's default mailer.
            Mail::html($html, function ($m) use ($msg, $subject, $headers) {
                $m->to($msg->to_address)->subject($subject);
                foreach ($headers as $name => $value) {
                    $m->getSymfonyMessage()->getHeaders()->addTextHeader($name, $value);
                }
            });
        }

        $this->settle($msg, $campaign, 'sent');
    }

    /** After the final retry — record the failure so the campaign reflects reality. */
    public function failed(\Throwable $e): void
    {
        $msg = CampaignMessage::withoutGlobalScopes()->find($this->messageId);
        if (!$msg || $msg->status !== 'queued') return;
        $msg->forceFill(['status' => 'failed'])->save();
        if ($msg->campaign_recipient_id) {
            CampaignRecipient::withoutGlobalScopes()->whereKey($msg->campaign_recipient_id)->update(['status' => 'failed']);
        }
        if ($campaign = Campaign::withoutGlobalScopes()->find($msg->campaign_id)) {
            $campaign->increment('failed_count');
            $this->maybeComplete($campaign);
        }
    }

    private function accountFor(Campaign $campaign, int $companyId, SmtpMailer $mailer): ?EmailAccount
    {
        $account = $campaign->email_account_id
            ? EmailAccount::withoutGlobalScopes()->find($campaign->email_account_id)
            : (EmailAccount::withoutGlobalScopes()->where('company_id', $companyId)->where('is_default', true)->first()
               ?? EmailAccount::withoutGlobalScopes()->where('company_id', $companyId)->where('is_active', true)->first());
        return ($account && $mailer->isConfigured($account->config)) ? $account : null;
    }

    private function renderHtml(CampaignMessage $msg, string $link): string
    {
        $raw = (string) $msg->body;
        $content = ($raw !== strip_tags($raw)) ? $raw : nl2br(e($raw));
        $footer = '<hr style="margin:24px 0;border:none;border-top:1px solid #E4E8EF">'
            . '<p style="font-size:12px;color:#8A94A6">You received this email as a contact of your account. '
            . '<a href="' . e($link) . '" style="color:#1D6FE0">Unsubscribe</a>.</p>';
        return '<div style="font-family:Inter,system-ui,-apple-system,sans-serif;font-size:14px;color:#101828;line-height:1.55">'
            . $content . $footer . '</div>';
    }

    private function settle(CampaignMessage $msg, Campaign $campaign, string $status): void
    {
        $msg->forceFill([
            'status' => $status,
            'sent_at' => $status === 'sent' ? now() : $msg->sent_at,
            'message_id' => $status === 'sent' ? sprintf('<%s@krama.local>', bin2hex(random_bytes(8))) : $msg->message_id,
        ])->save();
        if ($msg->campaign_recipient_id) {
            CampaignRecipient::withoutGlobalScopes()->whereKey($msg->campaign_recipient_id)
                ->update(['status' => $status, 'sent_at' => $status === 'sent' ? now() : null]);
        }
        if ($status === 'sent') $campaign->increment('sent_count');
        elseif ($status === 'suppressed') $campaign->increment('suppressed_count');
        $this->maybeComplete($campaign);
    }

    private function maybeComplete(Campaign $campaign): void
    {
        $pending = CampaignMessage::withoutGlobalScopes()
            ->where('campaign_id', $campaign->id)->where('status', 'queued')->exists();
        if (!$pending) {
            Campaign::withoutGlobalScopes()->whereKey($campaign->id)->update(['status' => 'sent', 'sent_at' => now()]);
        }
    }
}
