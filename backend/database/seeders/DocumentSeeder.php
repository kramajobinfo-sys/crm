<?php
namespace Database\Seeders;

use App\Models\Company;
use App\Models\DocumentFolder;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Demo data for the Documents library (Zoho gap #9): folders only, no files. A seeder runs via
 * `docker compose exec` as ROOT, so any file/dir it writes under storage/app/private would be
 * root-owned and PHP-FPM (www-data) could not later write the same month's directory — breaking
 * real uploads. So we seed structure only; documents are uploaded through the app. Idempotent.
 */
class DocumentSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::where('code', 'KRAMA')->first();
        if (!$company) return;
        $cid = $company->id;
        $by = User::where('company_id', $cid)->value('id');

        foreach (['Contracts', 'Templates', 'Sales collateral'] as $name) {
            DocumentFolder::firstOrCreate(
                ['company_id' => $cid, 'name' => $name],
                ['created_by' => $by]
            );
        }
    }
}
