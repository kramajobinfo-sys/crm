<?php
namespace Database\Seeders;

use App\Models\Company;
use App\Models\Customer;
use App\Models\Email;
use App\Models\EmailAccount;
use App\Models\EmailTemplate;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Database\Seeder;

class EmailSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::where('code', 'KRAMA')->first();
        if (!$company) return;
        $cid = $company->id;

        $mgr = User::where('company_id', $cid)->where('email', 'sales.mgr@krama.local')->value('id');
        $rep = User::where('company_id', $cid)->where('email', 'sales@krama.local')->value('id');

        $sales = EmailAccount::updateOrCreate(
            ['company_id' => $cid, 'email_address' => 'sales@krama.example'],
            ['name' => 'Sales team', 'from_name' => 'Krama Sales', 'provider' => 'smtp',
             'is_shared' => true, 'is_default' => true, 'is_active' => true,
             'signature' => "Krama Home Center\nSales Team\n+971 4 000 0000"]
        );
        EmailAccount::updateOrCreate(
            ['company_id' => $cid, 'email_address' => 'support@krama.example'],
            ['name' => 'Support', 'from_name' => 'Krama Support', 'provider' => 'smtp',
             'is_shared' => true, 'is_default' => false, 'is_active' => true]
        );

        $templates = [
            ['Quote follow-up','FOLLOWUP','Sales','Following up on your quote {{quote_no}}',
             '<p>Dear {{name}},</p><p>Just following up on the quotation we sent. Happy to answer any questions.</p><p>Best regards,<br>Krama Sales</p>'],
            ['Welcome new customer','WELCOME','Onboarding','Welcome to Krama, {{name}}!',
             '<p>Dear {{name}},</p><p>Thank you for choosing Krama Home Center. We look forward to working with you.</p>'],
            ['Payment reminder','PAYMENT','Finance','Reminder: invoice {{invoice_no}} is due',
             '<p>Dear {{name}},</p><p>This is a friendly reminder that invoice {{invoice_no}} is due. Please arrange payment at your convenience.</p>'],
        ];
        foreach ($templates as [$name,$code,$cat,$subj,$body]) {
            EmailTemplate::updateOrCreate(
                ['company_id' => $cid, 'code' => $code],
                ['name' => $name, 'category' => $cat, 'subject' => $subj, 'body_html' => $body, 'is_active' => true]
            );
        }

        $cust = Customer::withoutGlobalScopes()->where('company_id', $cid)->orderBy('id')->first();
        $lead = Lead::withoutGlobalScopes()->where('company_id', $cid)->orderBy('id')->first();

        // Idempotency: no natural key on emails, so clear the company's demo mail first.
        Email::withoutGlobalScopes()->where('company_id', $cid)->forceDelete();

        // [direction, status, subject, toAddr, related, sentDaysAgo, opens, clicks]
        $rows = [
            ['outbound','sent','Your quotation from Krama','procurement@alfuttaim.example',$cust,3,2,1],
            ['outbound','sent','Re: bulk furniture order','orders@gulfhome.example',null,5,1,0],
            ['inbound','received','Question about delivery timeline','aisha.rahman@example.com',$lead,1,0,0],
            ['outbound','draft','Proposal for showroom refresh',null,$cust,null,0,0],
        ];
        foreach ($rows as [$dir,$status,$subj,$to,$rel,$daysAgo,$opens,$clicks]) {
            Email::create([
                'company_id' => $cid, 'email_account_id' => $sales->id, 'direction' => $dir, 'status' => $status,
                'from_address' => $dir === 'outbound' ? $sales->email_address : $to,
                'from_name' => $dir === 'outbound' ? $sales->from_name : null,
                'to' => $dir === 'outbound' ? array_filter([$to]) : [$sales->email_address],
                'subject' => $subj,
                'body_html' => '<p>'.$subj.'</p><p>Sample email body for the Krama CRM demo.</p>',
                'related_type' => $rel ? $rel::class : null, 'related_id' => $rel?->id,
                'opens' => $opens, 'clicks' => $clicks,
                'opened_at' => $opens > 0 ? now()->subDays($daysAgo ?? 0)->addHours(2) : null,
                'sent_at' => $status === 'sent' ? now()->subDays($daysAgo ?? 0) : null,
                'received_at' => $status === 'received' ? now()->subDays($daysAgo ?? 0) : null,
                'user_id' => $dir === 'outbound' ? $mgr : null,
                'message_id' => $status === 'sent' ? sprintf('<%s@krama.local>', bin2hex(random_bytes(8))) : null,
            ]);
        }

        $this->command?->info('Seeded 2 email accounts, '.count($templates).' templates, '.count($rows).' emails.');
    }
}
