<?php
namespace App\Jobs;

use App\Models\Campaign;
use App\Models\CampaignMessage;
use App\Models\CampaignRecipient;
use App\Models\ContactConsent;
use App\Models\SmsProvider;
use App\Services\SmsSender;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/** Real delivery of one marketing SMS/WhatsApp message. Dispatched per recipient by
 *  MarketingService::launch. Idempotent, re-checks opt-out, advances counters exactly once. */
class SendCampaignSms implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    /** @var array<int,int> */
    public array $backoff = [60, 300, 900];

    public function __construct(public readonly int $messageId) {}

    public function handle(SmsSender $sender): void
    {
        $msg = CampaignMessage::withoutGlobalScopes()->find($this->messageId);
        if (!$msg || $msg->status !== 'queued' || $msg->channel !== 'sms') return;

        $campaign = Campaign::withoutGlobalScopes()->find($msg->campaign_id);
        if (!$campaign) return;

        if (ContactConsent::isSuppressed($msg->company_id, ['sms', 'marketing'], null, $msg->to_address)) {
            $this->settle($msg, $campaign, 'suppressed');
            return;
        }

        $provider = $this->providerFor($campaign, $msg->company_id, $sender);
        if (!$provider) {                       // nothing configured to send with — record the failure
            $this->settle($msg, $campaign, 'failed');
            return;
        }

        $sender->send($provider, $msg->to_address, (string) $msg->body);
        $this->settle($msg, $campaign, 'sent');
    }

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

    private function providerFor(Campaign $campaign, int $companyId, SmsSender $sender): ?SmsProvider
    {
        $p = $campaign->sms_provider_id
            ? SmsProvider::withoutGlobalScopes()->find($campaign->sms_provider_id)
            : (SmsProvider::withoutGlobalScopes()->where('company_id', $companyId)->where('is_default', true)->first()
               ?? SmsProvider::withoutGlobalScopes()->where('company_id', $companyId)->where('is_active', true)->first());
        return ($p && $sender->isConfigured($p)) ? $p : null;
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
