<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Admin-defined custom fields — the #1 platform gap vs Salesforce/Zoho. Admins define fields per
 * entity (custom_field_definitions); values are stored in a `custom_fields` JSON column on each
 * entity (keyed by definition key). JSON keeps reads join-free and avoids a schema change per field.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('custom_field_definitions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $t->string('entity', 24);                 // lead | customer | deal | contact
            $t->string('key', 64);                    // slug used in the JSON payload
            $t->string('label', 128);
            $t->string('type', 16);                   // text|textarea|number|date|select|checkbox|url|email
            $t->json('options')->nullable();          // for select: ["A","B",...]
            $t->boolean('required')->default(false);
            $t->string('help', 255)->nullable();
            $t->unsignedInteger('sort_order')->default(0);
            $t->boolean('is_active')->default(true);
            $t->timestamps();
            $t->unique(['company_id', 'entity', 'key']);
            $t->index(['company_id', 'entity', 'is_active']);
        });

        foreach (['leads', 'customers', 'deals', 'contacts'] as $table) {
            Schema::table($table, fn (Blueprint $t) => $t->json('custom_fields')->nullable());
        }
    }

    public function down(): void
    {
        foreach (['leads', 'customers', 'deals', 'contacts'] as $table) {
            Schema::table($table, fn (Blueprint $t) => $t->dropColumn('custom_fields'));
        }
        Schema::dropIfExists('custom_field_definitions');
    }
};
