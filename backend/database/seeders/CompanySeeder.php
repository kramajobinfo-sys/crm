<?php
namespace Database\Seeders;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Department;
use Illuminate\Database\Seeder;
class CompanySeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::updateOrCreate(['code'=>'KRAMA'], [
            'name'=>'Krama','legal_name'=>'Krama Holdings','tax_id'=>'KRAMA-000-001','base_currency'=>'USD',
            'primary_color'=>'#185FA5','default_language'=>'en','address_line1'=>'Head Office',
            'city'=>'Dubai','country'=>'United Arab Emirates','phone'=>'+971-4-000-0000',
            'email'=>'info@krama.local','website'=>'https://krama.local','is_active'=>true,
            'is_platform'=>true, // the Krama master org
        ]);
        $hq = Branch::updateOrCreate(['company_id'=>$company->id,'code'=>'HQ'],
            ['name'=>'Head Office','address'=>'Head Office','city'=>'Dubai','country'=>'United Arab Emirates']);
        $departments = [['Sales','SALES'],['Purchase','PURCH'],['Warehouse','WH'],['Accounting','ACC'],
            ['Customer Service','CS'],['Human Resources','HR'],['Management','MGMT']];
        foreach ($departments as [$name, $code])
            Department::updateOrCreate(['company_id'=>$company->id,'code'=>$code], ['name'=>$name,'branch_id'=>$hq->id]);
    }
}
