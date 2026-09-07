<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bulk CSV import for leads / contacts / customers. A batch tracks one uploaded file through
 * upload → mapping → preview → (queued) commit; import_rows keeps only the ERROR rows of a
 * committed run so a downloadable error report can be produced without storing the whole file.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('import_batches', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->string('entity', 16);                        // lead | contact | customer
            $t->string('filename', 255);
            $t->string('path', 512);                         // private-disk path to the uploaded CSV
            $t->enum('status', ['uploaded', 'processing', 'completed', 'failed'])->default('uploaded');
            $t->string('dedupe', 16)->default('skip');       // skip | update
            $t->json('mapping')->nullable();                 // { csvHeader: fieldKey }
            $t->unsignedInteger('total_rows')->default(0);
            $t->unsignedInteger('created_rows')->default(0);
            $t->unsignedInteger('updated_rows')->default(0);
            $t->unsignedInteger('skipped_rows')->default(0);
            $t->unsignedInteger('error_rows')->default(0);
            $t->string('error')->nullable();                 // fatal batch error, if any
            $t->timestamps();
            $t->index(['company_id', 'entity', 'status']);
        });

        Schema::create('import_rows', function (Blueprint $t) {
            $t->id();
            $t->foreignId('batch_id')->constrained('import_batches')->cascadeOnDelete();
            $t->foreignId('company_id')->constrained()->cascadeOnDelete();
            $t->unsignedInteger('row_number');
            $t->json('data')->nullable();                    // the mapped row values
            $t->json('errors')->nullable();                  // list of validation messages
            $t->timestamps();
            $t->index(['batch_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_rows');
        Schema::dropIfExists('import_batches');
    }
};
