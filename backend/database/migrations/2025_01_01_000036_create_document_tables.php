<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Zoho gap #9 — Documents library. A staff document library (collateral, contracts, templates),
 * separate from per-record `attachments`. Files live on the PRIVATE `local` disk and are reached
 * only through an authenticated streaming endpoint — never a public URL. See docs/DOCUMENTS_SCOPE.md.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('document_folders', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $t->string('name', 128);
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->index(['company_id', 'name']);
        });

        Schema::create('documents', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $t->foreignId('folder_id')->nullable()->constrained('document_folders')->nullOnDelete();
            $t->string('name', 191);
            $t->string('description', 500)->nullable();
            $t->string('disk', 24)->default('local');           // private disk; never 'public'
            $t->string('path', 512);
            $t->string('mime', 191)->nullable();
            $t->unsignedBigInteger('size')->default(0);
            $t->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            // No soft deletes: a deleted document removes its row and (best-effort) its file.
            $t->index(['company_id', 'folder_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documents');
        Schema::dropIfExists('document_folders');
    }
};
