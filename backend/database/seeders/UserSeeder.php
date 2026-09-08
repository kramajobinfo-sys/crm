<?php
namespace Database\Seeders;
use App\Models\Company;
use App\Models\Department;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\PermissionRegistrar;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::where('code','KRAMA')->firstOrFail();
        // Roles are per-company now — resolve them in the Krama tenant's team context.
        app(PermissionRegistrar::class)->setPermissionsTeamId($company->id);
        $depts = Department::where('company_id',$company->id)->pluck('id','code');
        $users = [
            ['Super Admin','admin@krama.local','Owner','MGMT'],
            ['CEO','ceo@krama.local','CEO','MGMT'],
            ['Sales Manager','sales.mgr@krama.local','Sales Manager','SALES'],
            ['Sales Staff','sales@krama.local','Sales Staff','SALES'],
            ['Purchase Manager','purchase@krama.local','Purchase Manager','PURCH'],
            ['Warehouse Staff','warehouse@krama.local','Warehouse','WH'],
            ['Accountant','accounts@krama.local','Accountant','ACC'],
            ['Customer Service','support@krama.local','Customer Service','CS'],
            ['HR Officer','hr@krama.local','HR','HR'],
        ];
        foreach ($users as [$name, $email, $role, $dept]) {
            $user = User::withoutGlobalScopes()->updateOrCreate(
                ['email'=>$email,'company_id'=>$company->id],
                ['name'=>$name,'password'=>Hash::make('password123'),'company_id'=>$company->id,
                 'department_id'=>$depts[$dept] ?? null,'language'=>'en','timezone'=>'Asia/Dubai',
                 'is_active'=>true,'email_verified_at'=>now()],
            );
            // Privilege flag is not mass-assignable — set it explicitly.
            $user->forceFill(['is_platform_admin'=>($email==='admin@krama.local')])->save();
            $user->syncRoles([$role]);
        }
        $this->command->info('');
        $this->command->info('  Seeded 9 users. Password for all: password123');
        $this->command->info('  Change all passwords after first login.');
    }
}
