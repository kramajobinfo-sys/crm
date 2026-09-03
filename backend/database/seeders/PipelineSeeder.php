<?php
namespace Database\Seeders;

use App\Models\Company;
use App\Models\Customer;
use App\Models\Deal;
use App\Models\DealProduct;
use App\Models\LostReason;
use App\Models\Pipeline;
use App\Models\PipelineStage;
use App\Models\TimelineActivity;
use App\Models\User;
use Illuminate\Database\Seeder;

class PipelineSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::where('code', 'KRAMA')->first();
        if (!$company) return;
        $cid = $company->id;

        // Unauthenticated: company_id must be explicit everywhere, and deal_no cannot
        // come from DealService::nextDealNo() (which reads auth()).
        $users = User::where('company_id', $cid)
            ->whereIn('email', ['sales.mgr@krama.local', 'sales@krama.local'])
            ->pluck('id', 'email');
        $mgr = $users['sales.mgr@krama.local'] ?? null;
        $rep = $users['sales@krama.local'] ?? $mgr;

        // Exactly ONE default pipeline: DashboardService::pipeline() groups stages by
        // company with no pipeline filter, so a second pipeline would merge the funnel.
        $pipeline = Pipeline::updateOrCreate(
            ['company_id' => $cid, 'code' => 'SALES'],
            ['name' => 'Sales pipeline', 'is_default' => true, 'is_active' => true, 'sort_order' => 0]
        );

        $stages = [];
        foreach ([
            ['Qualification','QUAL','#7F77DD',10,15,false,false],
            ['Needs analysis','NEEDS','#378ADD',20,30,false,false],
            ['Proposal','PROPOSAL','#0EA5E9',30,50,false,false],
            ['Negotiation','NEGO','#EF9F27',40,75,false,false],
            ['Won','WON','#10B981',50,100,true,false],
            ['Lost','LOST','#EF4444',60,0,false,true],
        ] as [$name,$code,$color,$order,$prob,$isWon,$isLost]) {
            $stages[$code] = PipelineStage::updateOrCreate(
                ['pipeline_id' => $pipeline->id, 'code' => $code],
                ['company_id' => $cid, 'name' => $name, 'color' => $color, 'order_index' => $order,
                 'probability' => $prob, 'is_won' => $isWon, 'is_lost' => $isLost]
            );
        }

        $reasons = [];
        foreach ([
            ['Price too high','PRICE',10], ['Chose competitor','COMPETITOR',20],
            ['No budget','NO_BUDGET',30], ['Bad timing','TIMING',40],
            ['No response','NO_RESPONSE',50], ['Not a fit','NOT_FIT',60],
        ] as [$name,$code,$sort]) {
            $reasons[$code] = LostReason::updateOrCreate(
                ['company_id' => $cid, 'code' => $code],
                ['name' => $name, 'sort_order' => $sort, 'is_active' => true]
            );
        }

        $custByNo = Customer::withoutGlobalScopes()->where('company_id', $cid)
            ->pluck('id', 'customer_no');

        // [no, title, customerNo, stageCode, amount, owner, closeDays, source, [ [name, qty, price, disc], ... ]]
        $rows = [
            ['DEAL-00001','Al Futtaim — office fit-out','CUST-00001','NEGO',180000,$mgr,20,'Referral',
                [['Executive desk (walnut)',12,4500,5],['Ergonomic chair',40,1200,10],['Meeting table 10-seat',3,8500,0]]],
            ['DEAL-00002','Gulf Home — bulk furniture order','CUST-00002','PROPOSAL',95000,$mgr,30,'Trade show',
                [['Modular sofa set',15,3800,8],['Coffee table',15,900,0]]],
            ['DEAL-00003','Nadia Living — showroom refresh','CUST-00003','NEEDS',33000,$rep,45,'Website',
                [['Display shelving unit',20,850,0],['Accent lighting',30,320,0]]],
            ['DEAL-00004','Layla Haddad — home study','CUST-00004','QUAL',6200,$rep,60,'Social media',
                [['Writing desk',1,2200,0],['Bookcase',2,1400,5]]],
            ['DEAL-00005','Emaar Hospitality — hotel lobby','CUST-00008','WON',240000,$mgr,-5,'Referral',
                [['Lobby lounge set',6,18000,10],['Reception counter',2,22000,0]]],
            ['DEAL-00006','Desert Rose — restaurant seating','CUST-00006','LOST',55000,$rep,null,'Cold outreach',
                [['Dining chair (oak)',80,420,0],['Banquette bench',12,1600,0]]],
            ['DEAL-00007','Ministry of Works — tender fit-out','CUST-00005','PROPOSAL',420000,$mgr,25,'Tender',
                [['Workstation cluster (6-pod)',20,9500,0],['Storage cabinet',60,780,5]]],
            ['DEAL-00008','Yusuf Trading — retail counters','CUST-00007','NEGO',28000,$rep,15,'Referral',
                [['Cashier counter',4,3200,0],['Product gondola',10,1500,0]]],
        ];

        foreach ($rows as [$no,$title,$custNo,$stageCode,$amount,$owner,$closeDays,$source,$lines]) {
            $stage = $stages[$stageCode];
            $status = $stage->is_won ? 'won' : ($stage->is_lost ? 'lost' : 'open');

            $deal = Deal::updateOrCreate(
                ['company_id' => $cid, 'deal_no' => $no],
                [
                    'title' => $title,
                    'pipeline_id' => $pipeline->id,
                    'stage_id' => $stage->id,
                    'customer_id' => $custByNo[$custNo] ?? null,
                    'owner_id' => $owner,
                    'amount' => $amount,
                    'currency' => 'AED',
                    'probability' => $stage->probability,
                    'status' => $status,
                    'source' => $source,
                    'expected_close_date' => $closeDays !== null ? now()->addDays($closeDays)->toDateString() : null,
                    'won_at' => $stage->is_won ? now()->subDays(5) : null,
                    'lost_at' => $stage->is_lost ? now()->subDays(10) : null,
                    'lost_reason_id' => $stage->is_lost ? ($reasons['PRICE']->id ?? null) : null,
                ]
            );

            $deal->products()->delete();
            $total = 0.0;
            foreach (array_values($lines) as $i => [$lname,$qty,$price,$disc]) {
                $lineTotal = DealProduct::compute($qty, $price, $disc);
                $total += $lineTotal;
                DealProduct::create([
                    'company_id' => $cid, 'deal_id' => $deal->id, 'name' => $lname,
                    'quantity' => $qty, 'unit_price' => $price, 'discount_pct' => $disc,
                    'line_total' => $lineTotal, 'sort_order' => $i,
                ]);
            }
            // Line items are authoritative — keep amount in step with them.
            $deal->forceFill(['amount' => round($total, 2)])->saveQuietly();

            $deal->timeline()->delete();
            TimelineActivity::create([
                'company_id' => $cid, 'subject_type' => Deal::class, 'subject_id' => $deal->id,
                'user_id' => $owner, 'type' => 'system', 'title' => 'Deal created',
                'occurred_at' => now()->subDays(random_int(15, 120)),
            ]);
        }

        $this->command?->info(
            'Seeded 1 pipeline, '.count($stages).' stages, '.count($reasons).' lost reasons, '.count($rows).' deals.'
        );
    }
}
