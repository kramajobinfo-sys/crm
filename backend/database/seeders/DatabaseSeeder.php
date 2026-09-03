<?php
namespace Database\Seeders;
use Illuminate\Database\Seeder;
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([CurrencySeeder::class, CompanySeeder::class, RolePermissionSeeder::class, UserSeeder::class, CustomerSeeder::class, LeadSeeder::class, PipelineSeeder::class, ActivitySeeder::class, SalesSeeder::class, PriceBookSeeder::class, CreditSeeder::class, InventorySeeder::class, PurchaseSeeder::class, ProductSupplierSeeder::class, ManufacturingSeeder::class, HelpdeskSeeder::class, KbSeeder::class, DocumentSeeder::class, EmailSeeder::class, MarketingSeeder::class, ReportSeeder::class, ForecastSeeder::class, WorkflowSeeder::class, AiSeeder::class, HrSeeder::class, ChatSeeder::class]);
    }
}
