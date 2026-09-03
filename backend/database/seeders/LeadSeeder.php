<?php
namespace Database\Seeders;

use App\Models\Company;
use App\Models\Lead;
use App\Models\LeadAssignmentRule;
use App\Models\LeadSource;
use App\Models\LeadStatus;
use App\Models\TimelineActivity;
use App\Models\User;
use App\Services\LeadScoringService;
use Illuminate\Database\Seeder;

class LeadSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::where('code', 'KRAMA')->first();
        if (!$company) return;

        // Unauthenticated: company_id must be explicit, and lead_no cannot come from
        // LeadService::nextLeadNo() (which reads auth()).
        $cid = $company->id;
        $scoring = app(LeadScoringService::class);

        $users = User::where('company_id', $cid)
            ->whereIn('email', ['sales.mgr@krama.local', 'sales@krama.local'])
            ->pluck('id', 'email');
        $mgr   = $users['sales.mgr@krama.local'] ?? null;
        $rep   = $users['sales@krama.local'] ?? $mgr;

        $sources = [];
        foreach ([
            ['Website form','WEB'], ['Referral','REF'], ['Trade show','SHOW'],
            ['Social media','SOCIAL'], ['Cold outreach','COLD'], ['WhatsApp enquiry','WA'],
        ] as [$name, $code]) {
            $sources[$code] = LeadSource::updateOrCreate(
                ['company_id' => $cid, 'code' => $code],
                ['name' => $name, 'is_active' => true]
            );
        }

        $statuses = [];
        foreach ([
            ['New','NEW','#3B82F6',10,true,false,false],
            ['Contacted','CONTACTED','#8B5CF6',20,false,false,false],
            ['Qualified','QUALIFIED','#0EA5E9',30,false,false,false],
            ['Proposal sent','PROPOSAL','#F59E0B',40,false,false,false],
            ['Won','WON','#10B981',50,false,true,false],
            ['Lost','LOST','#EF4444',60,false,false,true],
        ] as [$name,$code,$color,$sort,$isDefault,$isWon,$isLost]) {
            $statuses[$code] = LeadStatus::updateOrCreate(
                ['company_id' => $cid, 'code' => $code],
                ['name' => $name, 'color' => $color, 'sort_order' => $sort,
                 'is_default' => $isDefault, 'is_won' => $isWon, 'is_lost' => $isLost]
            );
        }

        // Priority order matters: the high-value rule must beat the catch-all.
        LeadAssignmentRule::updateOrCreate(
            ['company_id' => $cid, 'name' => 'High value to sales manager'],
            ['priority' => 10, 'strategy' => 'specific', 'assign_to_user_id' => $mgr,
             'conditions' => [['field' => 'estimated_value', 'op' => 'gte', 'value' => 50000]],
             'is_active' => true]
        );
        LeadAssignmentRule::updateOrCreate(
            ['company_id' => $cid, 'name' => 'Round robin (catch-all)'],
            ['priority' => 100, 'strategy' => 'round_robin',
             'round_robin_user_ids' => array_values(array_filter([$rep, $mgr])),
             'conditions' => [], 'is_active' => true]
        );

        // [no, name, company, title, email, phone, source, status, value, owner, closeDays, contactedDaysAgo]
        $rows = [
            ['LEAD-00001','Fatima Al Zahra','Zahra Interiors','Managing Director','fatima@zahra.example','+971 50 771 3300','REF','QUALIFIED',180000,$mgr,30,3],
            ['LEAD-00002','Bilal Haddad','Haddad Contracting','Procurement Manager','bilal@haddadco.example','+971 4 882 1140','SHOW','PROPOSAL',95000,$mgr,21,6],
            ['LEAD-00003','Aisha Rahman',null,null,'aisha.rahman@example.com','+971 55 330 8821','WEB','NEW',4500,$rep,60,null],
            ['LEAD-00004','Kareem Nasser','Nasser Hospitality','F&B Director','kareem@nasserhg.example','+971 2 667 9900','REF','CONTACTED',62000,$mgr,45,15],
            ['LEAD-00005','Huda Salem','Salem Retail Group','Category Buyer','huda@salemretail.example','+971 6 545 2210','SOCIAL','QUALIFIED',33000,$rep,40,9],
            ['LEAD-00006','Tarek Aziz',null,null,null,'+971 52 118 4471','COLD','NEW',800,$rep,90,null],
            ['LEAD-00007','Mona Farid','Farid Developments','Project Lead','mona@fariddev.example','+971 4 229 5570','SHOW','PROPOSAL',240000,$mgr,15,2],
            ['LEAD-00008','Sami Youssef','Youssef Trading','Owner','sami@yousseftrading.example','+971 50 662 1147','WA','CONTACTED',18000,$rep,50,40],
            ['LEAD-00009','Dalia Mansour','Mansour Hotels','Purchasing','dalia@mansourhotels.example','+971 4 771 0033','REF','LOST',55000,$mgr,null,120],
            ['LEAD-00010','Ziad Khalil',null,'Freelance architect','ziad.khalil@example.com',null,'WEB','NEW',12000,$rep,75,null],
        ];

        foreach ($rows as [$no,$name,$co,$title,$email,$phone,$srcCode,$statusCode,$value,$owner,$closeDays,$contactedAgo]) {
            $lead = Lead::updateOrCreate(
                ['company_id' => $cid, 'lead_no' => $no],
                [
                    'name' => $name, 'company_name' => $co, 'title' => $title,
                    'email' => $email, 'phone' => $phone,
                    'source_id' => $sources[$srcCode]->id,
                    'status_id' => $statuses[$statusCode]->id,
                    'owner_id' => $owner,
                    'estimated_value' => $value,
                    'currency' => 'AED',
                    'expected_close_date' => $closeDays ? now()->addDays($closeDays)->toDateString() : null,
                    'last_contacted_at' => $contactedAgo !== null ? now()->subDays($contactedAgo) : null,
                ]
            );

            // Score from the same rubric the app uses, so seeded data is consistent.
            $lead->loadMissing('status');
            $result = $scoring->evaluate($lead);
            $lead->forceFill(['score' => $result['score'], 'rating' => $result['rating']])->saveQuietly();

            $lead->timeline()->delete();
            TimelineActivity::create([
                'company_id' => $cid, 'subject_type' => Lead::class, 'subject_id' => $lead->id,
                'user_id' => $owner, 'type' => 'system', 'title' => 'Lead created',
                'occurred_at' => now()->subDays(random_int(10, 180)),
            ]);
            if ($contactedAgo !== null) {
                TimelineActivity::create([
                    'company_id' => $cid, 'subject_type' => Lead::class, 'subject_id' => $lead->id,
                    'user_id' => $owner, 'type' => 'call', 'title' => 'Call logged',
                    'body' => 'Discussed requirements and budget range.',
                    'occurred_at' => now()->subDays($contactedAgo),
                ]);
            }
        }

        $this->command?->info(
            'Seeded '.count($rows).' leads, '.count($sources).' sources, '.count($statuses).' statuses, 2 assignment rules.'
        );
    }
}
