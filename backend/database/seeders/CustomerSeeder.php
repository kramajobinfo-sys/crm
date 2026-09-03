<?php
namespace Database\Seeders;

use App\Models\Company;
use App\Models\Contact;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\TimelineActivity;
use App\Models\User;
use Illuminate\Database\Seeder;

class CustomerSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::where('code', 'KRAMA')->first();
        if (!$company) return;

        // Seeders run unauthenticated, so BelongsToCompany's creating hook never fires:
        // company_id must be explicit on every row, and customer_no cannot come from
        // CustomerService::nextCustomerNo() (which reads auth()).
        $cid = $company->id;

        $owners = User::where('company_id', $cid)
            ->whereIn('email', ['sales.mgr@krama.local', 'sales@krama.local'])
            ->pluck('id', 'email');
        $salesMgr = $owners['sales.mgr@krama.local'] ?? null;
        $sales    = $owners['sales@krama.local'] ?? $salesMgr;

        $groups = [];
        foreach ([
            ['Retail',     'RETAIL', 0,  30],
            ['Wholesale',  'WHOLE',  7.5, 45],
            ['Key Account','KEY',    12,  60],
            ['Government', 'GOV',    5,   90],
        ] as [$name, $code, $discount, $terms]) {
            $groups[$code] = CustomerGroup::updateOrCreate(
                ['company_id' => $cid, 'code' => $code],
                ['name' => $name, 'discount_percent' => $discount,
                 'payment_terms_days' => $terms, 'is_active' => true]
            );
        }

        $rows = [
            ['CUST-00001','company','KEY','Al Futtaim Interiors','Al Futtaim Interiors LLC','procurement@alfuttaim.example','+971 4 501 2200','AED',250000,'active',$salesMgr,
                'Dubai','United Arab Emirates', [['Ahmed Al Mansoori','Procurement Lead','ahmed@alfuttaim.example',true],['Sara Nasser','Accounts Payable','sara@alfuttaim.example',false]]],
            ['CUST-00002','company','WHOLE','Gulf Home Supplies','Gulf Home Supplies FZE','orders@gulfhome.example','+971 6 552 8890','AED',120000,'active',$sales,
                'Sharjah','United Arab Emirates', [['Omar Farouk','Buyer','omar@gulfhome.example',true]]],
            ['CUST-00003','company','RETAIL','Nadia Living Store',null,'hello@nadialiving.example','+971 2 445 1180','AED',30000,'active',$sales,
                'Abu Dhabi','United Arab Emirates', [['Nadia Kamal','Owner','nadia@nadialiving.example',true]]],
            ['CUST-00004','individual','RETAIL','Layla Haddad',null,'layla.haddad@example.com','+971 50 118 4402','AED',5000,'active',$sales,
                'Dubai','United Arab Emirates', []],
            ['CUST-00005','company','GOV','Ministry of Works','Ministry of Works — Procurement','tenders@mow.example','+971 4 900 1000','AED',500000,'on_hold',$salesMgr,
                'Dubai','United Arab Emirates', [['Khalid Nasser','Tender Officer','khalid@mow.example',true]]],
            ['CUST-00006','company','WHOLE','Desert Rose Furnishing','Desert Rose Furnishing LLC','ap@desertrose.example','+971 4 337 7712','AED',80000,'blocked',$sales,
                'Dubai','United Arab Emirates', [['Mariam Saleh','Finance','mariam@desertrose.example',true]]],
            ['CUST-00007','company','RETAIL','Yusuf Trading',null,'yusuf@yusuftrading.example','+971 55 220 9987','AED',15000,'active',$sales,
                'Ajman','United Arab Emirates', [['Yusuf Rahman','Proprietor','yusuf@yusuftrading.example',true]]],
            ['CUST-00008','company','KEY','Emaar Hospitality','Emaar Hospitality Group','supply@emaarhg.example','+971 4 366 1234','AED',400000,'active',$salesMgr,
                'Dubai','United Arab Emirates', [['Rania Doust','Category Manager','rania@emaarhg.example',true],['Tariq Mansour','Logistics','tariq@emaarhg.example',false]]],
        ];

        foreach ($rows as [$no,$type,$groupCode,$name,$legal,$email,$phone,$ccy,$credit,$status,$ownerId,$city,$country,$contacts]) {
            $customer = Customer::updateOrCreate(
                ['company_id' => $cid, 'customer_no' => $no],
                [
                    'type' => $type,
                    'group_id' => $groups[$groupCode]->id,
                    'owner_id' => $ownerId,
                    'name' => $name,
                    'legal_name' => $legal,
                    'email' => $email,
                    'phone' => $phone,
                    'currency' => $ccy,
                    'credit_limit' => $credit,
                    'status' => $status,
                    'tax_id' => 'TRN'.str_pad((string) crc32($no), 9, '0', STR_PAD_LEFT),
                ]
            );

            // Idempotent: rebuild this customer's children instead of appending on re-run.
            $customer->contacts()->forceDelete();
            foreach ($contacts as [$cn, $ct, $ce, $primary]) {
                Contact::create([
                    'company_id' => $cid, 'customer_id' => $customer->id,
                    'name' => $cn, 'title' => $ct, 'email' => $ce, 'is_primary' => $primary,
                ]);
            }

            $customer->addresses()->delete();
            $customer->addresses()->create([
                'company_id' => $cid, 'type' => 'billing', 'label' => 'Head office',
                'line1' => 'Office '.random_int(100, 999).', Business Bay',
                'city' => $city, 'country' => $country, 'is_default' => true,
            ]);

            $customer->timeline()->delete();
            TimelineActivity::create([
                'company_id' => $cid, 'subject_type' => Customer::class, 'subject_id' => $customer->id,
                'user_id' => $ownerId, 'type' => 'system', 'title' => 'Customer created',
                'occurred_at' => now()->subDays(random_int(5, 120)),
            ]);
            TimelineActivity::create([
                'company_id' => $cid, 'subject_type' => Customer::class, 'subject_id' => $customer->id,
                'user_id' => $ownerId, 'type' => 'call', 'title' => 'Call logged',
                'body' => 'Introductory call — discussed catalogue and lead times.',
                'occurred_at' => now()->subDays(random_int(1, 30)),
            ]);
        }

        $this->command?->info('Seeded '.count($rows).' customers in '.count($groups).' groups.');
    }
}
