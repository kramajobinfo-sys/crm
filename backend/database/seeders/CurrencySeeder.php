<?php
namespace Database\Seeders;
use App\Models\Currency;
use Illuminate\Database\Seeder;
class CurrencySeeder extends Seeder
{
    public function run(): void
    {
        $currencies = [
            ['code'=>'USD','name'=>'US Dollar','symbol'=>'$','exchange_rate'=>1.0,'is_base'=>true],
            ['code'=>'EUR','name'=>'Euro','symbol'=>'€','exchange_rate'=>0.92],
            ['code'=>'GBP','name'=>'British Pound','symbol'=>'£','exchange_rate'=>0.79],
            ['code'=>'AED','name'=>'UAE Dirham','symbol'=>'AED','exchange_rate'=>3.673],
            ['code'=>'SAR','name'=>'Saudi Riyal','symbol'=>'SAR','exchange_rate'=>3.75],
            ['code'=>'INR','name'=>'Indian Rupee','symbol'=>'₹','exchange_rate'=>83.5],
            ['code'=>'CNY','name'=>'Chinese Yuan','symbol'=>'¥','exchange_rate'=>7.25],
        ];
        foreach ($currencies as $c) Currency::updateOrCreate(['code'=>$c['code']], array_merge($c, ['last_updated_at'=>now()]));
    }
}
