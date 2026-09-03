<?php
namespace Database\Seeders;

use App\Models\Company;
use App\Models\Customer;
use App\Models\SlaPolicy;
use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\TicketReply;
use App\Models\User;
use Illuminate\Database\Seeder;

class HelpdeskSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::where('code', 'KRAMA')->first();
        if (!$company) return;
        $cid = $company->id;

        $users = User::where('company_id', $cid)
            ->whereIn('email', ['support@krama.local', 'sales@krama.local'])
            ->pluck('id', 'email');
        $agent = $users['support@krama.local'] ?? null;
        $agent2 = $users['sales@krama.local'] ?? $agent;

        $cats = [];
        foreach ([['Product defect','DEFECT'],['Delivery issue','DELIVERY'],['Billing','BILLING'],['General enquiry','GENERAL']] as [$n,$c]) {
            $cats[$c] = TicketCategory::updateOrCreate(['company_id' => $cid, 'code' => $c], ['name' => $n, 'is_active' => true]);
        }

        // SLA policies per priority (minutes).
        $sla = [];
        foreach ([
            ['Urgent SLA','urgent',30,240], ['High SLA','high',60,480],
            ['Medium SLA','medium',240,1440], ['Low SLA','low',480,2880],
        ] as [$name,$prio,$fr,$res]) {
            $sla[$prio] = SlaPolicy::updateOrCreate(
                ['company_id' => $cid, 'priority' => $prio],
                ['name' => $name, 'first_response_minutes' => $fr, 'resolution_minutes' => $res, 'is_active' => true]
            );
        }

        $custByNo = Customer::withoutGlobalScopes()->where('company_id', $cid)->pluck('id', 'customer_no');

        // [no, subject, status, priority, category, customerNo, assignee, createdHoursAgo, firstReplied]
        $rows = [
            ['TKT-00001','Chair armrest arrived cracked','open','high','DEFECT','CUST-00001',$agent,20,true],
            ['TKT-00002','Delivery delayed past promised date','pending','urgent','DELIVERY','CUST-00002',$agent,50,true],
            ['TKT-00003','Invoice shows wrong VAT amount','open','medium','BILLING','CUST-00003',$agent2,8,true],
            ['TKT-00004','Question about bulk pricing','new','low','GENERAL','CUST-00007',null,2,false],
            ['TKT-00005','Sofa fabric colour mismatch','resolved','medium','DEFECT','CUST-00001',$agent,120,true],
            ['TKT-00006','Missing screws in flat-pack','new','high','DEFECT',null,$agent2,1,false],
        ];

        foreach ($rows as [$no,$subject,$status,$prio,$catCode,$custNo,$assignee,$hoursAgo,$firstReplied]) {
            $policy = $sla[$prio];
            $created = now()->subHours($hoursAgo);
            $ticket = Ticket::updateOrCreate(
                ['company_id' => $cid, 'ticket_no' => $no],
                [
                    'subject' => $subject, 'description' => $subject.'. Customer reported this via support.',
                    'status' => $status, 'priority' => $prio,
                    'category_id' => $cats[$catCode]->id,
                    'customer_id' => $custByNo[$custNo] ?? null,
                    'requester_name' => $custNo ? null : 'Walk-in customer',
                    'requester_email' => $custNo ? null : 'walkin@example.com',
                    'channel' => 'email', 'assigned_to' => $assignee, 'created_by' => $agent,
                    'sla_policy_id' => $policy->id,
                    'first_response_due_at' => $created->copy()->addMinutes($policy->first_response_minutes),
                    'due_at' => $created->copy()->addMinutes($policy->resolution_minutes),
                    'first_response_at' => $firstReplied ? $created->copy()->addMinutes(random_int(10, 90)) : null,
                    'resolved_at' => $status === 'resolved' ? $created->copy()->addHours(6) : null,
                ]
            );
            // Backdate creation so SLA/breaching states are realistic.
            $ticket->forceFill(['created_at' => $created])->saveQuietly();

            $ticket->replies()->delete();
            TicketReply::create([
                'company_id' => $cid, 'ticket_id' => $ticket->id, 'user_id' => $agent,
                'author_type' => 'system', 'is_internal' => true, 'body' => 'Ticket created via email.',
                'created_at' => $created, 'updated_at' => $created,
            ]);
            if ($firstReplied) {
                TicketReply::create([
                    'company_id' => $cid, 'ticket_id' => $ticket->id, 'user_id' => $assignee ?? $agent,
                    'author_type' => 'agent', 'is_internal' => false,
                    'body' => 'Thanks for reaching out — we are looking into this and will update you shortly.',
                    'created_at' => $created->copy()->addMinutes(45), 'updated_at' => $created->copy()->addMinutes(45),
                ]);
            }
        }

        $this->command?->info('Seeded '.count($cats).' categories, '.count($sla).' SLA policies, '.count($rows).' tickets.');
    }
}
