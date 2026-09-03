<?php
namespace Database\Seeders;

use App\Models\Campaign;
use App\Models\CampaignMessage;
use App\Models\Company;
use App\Models\Customer;
use App\Models\EmailAccount;
use App\Models\EmailTemplate;
use App\Models\SmsProvider;
use App\Models\User;
use Illuminate\Database\Seeder;

class MarketingSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::where('code', 'KRAMA')->first();
        if (!$company) return;
        $cid = $company->id;

        $mgr = User::where('company_id', $cid)->where('email', 'sales.mgr@krama.local')->value('id');

        $sms = SmsProvider::updateOrCreate(
            ['company_id' => $cid, 'name' => 'Unifonic'],
            ['provider' => 'unifonic', 'sender_id' => 'KRAMA', 'is_default' => true, 'is_active' => true]
        );
        $account = EmailAccount::withoutGlobalScopes()->where('company_id', $cid)->where('is_default', true)->first();
        $welcome = EmailTemplate::withoutGlobalScopes()->where('company_id', $cid)->where('code', 'WELCOME')->first();

        // A sent email campaign to active customers (materialise recipients so stats are real).
        $sent = Campaign::updateOrCreate(
            ['company_id' => $cid, 'name' => 'Autumn collection launch'],
            ['type' => 'email', 'status' => 'sent', 'subject' => 'Discover our autumn collection',
             'body' => '<p>New arrivals are here — explore our autumn collection.</p>',
             'email_template_id' => $welcome?->id, 'email_account_id' => $account?->id,
             'audience' => ['source' => 'customers', 'filters' => ['status' => 'active']],
             'sent_at' => now()->subDays(6), 'created_by' => $mgr]
        );
        $this->materialise($sent, $cid, 'email');

        // Reset counters/materialised rows explicitly so a re-run after a demo launch is clean.
        $unsent = ['recipients_count' => 0, 'sent_count' => 0, 'opened_count' => 0,
                   'clicked_count' => 0, 'failed_count' => 0, 'sent_at' => null];

        // A draft SMS campaign to hot leads.
        $draft = Campaign::updateOrCreate(
            ['company_id' => $cid, 'name' => 'Flash sale SMS blast'],
            array_merge($unsent, ['type' => 'sms', 'status' => 'draft', 'body' => 'Krama flash sale: 20% off this weekend only!',
             'sms_provider_id' => $sms->id,
             'audience' => ['source' => 'leads', 'filters' => ['rating' => 'hot']],
             'created_by' => $mgr])
        );
        $draft->recipients()->delete();
        $draft->messages()->delete();

        // A scheduled email campaign to a customer group.
        Campaign::updateOrCreate(
            ['company_id' => $cid, 'name' => 'VIP preview invitation'],
            array_merge($unsent, ['type' => 'email', 'status' => 'scheduled', 'subject' => 'You are invited to our VIP preview',
             'body' => '<p>As a valued customer, you are invited to a private preview.</p>',
             'email_account_id' => $account?->id,
             'audience' => ['source' => 'customers', 'filters' => []],
             'scheduled_at' => now()->addDays(3), 'created_by' => $mgr])
        );

        $this->command?->info('Seeded 1 SMS provider, 3 campaigns (1 sent with recipients).');
    }

    /** Materialise a sent campaign's recipients + messages from active customers with email. */
    private function materialise(Campaign $campaign, int $cid, string $type): void
    {
        $campaign->recipients()->delete();
        $campaign->messages()->delete();
        $rows = Customer::withoutGlobalScopes()->where('company_id', $cid)->where('status', 'active')
            ->whereNotNull('email')->get(['id', 'name', 'email', 'phone']);
        $sentCount = 0; $opened = 0;
        foreach ($rows as $i => $c) {
            $isOpened = $i % 2 === 0;   // half opened, for a realistic open rate
            $recipient = $campaign->recipients()->create([
                'company_id' => $cid, 'recipient_type' => Customer::class, 'recipient_id' => $c->id,
                'name' => $c->name, 'email' => $c->email, 'phone' => $c->phone,
                'status' => $isOpened ? 'opened' : 'sent',
                'sent_at' => now()->subDays(6), 'opened_at' => $isOpened ? now()->subDays(6)->addHours(3) : null,
            ]);
            CampaignMessage::create([
                'company_id' => $cid, 'campaign_id' => $campaign->id, 'campaign_recipient_id' => $recipient->id,
                'channel' => $type, 'to_address' => $c->email, 'subject' => $campaign->subject, 'body' => $campaign->body,
                'status' => 'sent', 'message_id' => sprintf('<%s@krama.local>', bin2hex(random_bytes(8))), 'sent_at' => now()->subDays(6),
            ]);
            $sentCount++; $opened += $isOpened ? 1 : 0;
        }
        $campaign->forceFill([
            'recipients_count' => $sentCount, 'sent_count' => $sentCount,
            'opened_count' => $opened, 'clicked_count' => intdiv($opened, 2),
        ])->save();
    }
}
