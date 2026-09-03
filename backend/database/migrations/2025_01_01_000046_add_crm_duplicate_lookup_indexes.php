<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->index(['company_id', 'email'], 'leads_company_email_idx');
            $table->index(['company_id', 'phone'], 'leads_company_phone_idx');
            $table->index(['company_id', 'company_name'], 'leads_company_name_idx');
        });
        Schema::table('customers', function (Blueprint $table) {
            $table->index(['company_id', 'email'], 'customers_company_email_idx');
            $table->index(['company_id', 'phone'], 'customers_company_phone_idx');
            $table->index(['company_id', 'tax_id'], 'customers_company_tax_idx');
            $table->index(['company_id', 'name'], 'customers_company_name_idx');
        });
        Schema::table('contacts', function (Blueprint $table) {
            $table->index(['company_id', 'email'], 'contacts_company_email_idx');
            $table->index(['company_id', 'phone'], 'contacts_company_phone_idx');
            $table->index(['company_id', 'name'], 'contacts_company_name_idx');
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropIndex('leads_company_email_idx');
            $table->dropIndex('leads_company_phone_idx');
            $table->dropIndex('leads_company_name_idx');
        });
        Schema::table('customers', function (Blueprint $table) {
            $table->dropIndex('customers_company_email_idx');
            $table->dropIndex('customers_company_phone_idx');
            $table->dropIndex('customers_company_tax_idx');
            $table->dropIndex('customers_company_name_idx');
        });
        Schema::table('contacts', function (Blueprint $table) {
            $table->dropIndex('contacts_company_email_idx');
            $table->dropIndex('contacts_company_phone_idx');
            $table->dropIndex('contacts_company_name_idx');
        });
    }
};
