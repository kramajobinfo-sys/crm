<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Zoho gap #8 — Knowledge Base ("Solutions"). Staff-authored articles, plus a customer-portal
 * self-service view of articles that are BOTH published AND public. Bodies are plain text
 * (rendered pre-wrap on the portal — no HTML sink); see docs/KB_SCOPE.md.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('kb_categories', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $t->string('name', 128);
            $t->string('code', 32);
            $t->boolean('is_active')->default(true);
            $t->timestamps();
            $t->unique(['company_id', 'code']);
        });

        Schema::create('kb_articles', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $t->foreignId('category_id')->nullable()->constrained('kb_categories')->nullOnDelete();
            $t->string('title', 191);
            $t->string('slug', 191);
            $t->text('body');                                   // plain text (rendered pre-wrap)
            $t->string('excerpt', 500)->nullable();
            $t->string('status', 16)->default('draft');         // draft | published
            $t->string('visibility', 16)->default('internal');  // internal | public
            $t->unsignedInteger('view_count')->default(0);
            $t->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('published_at')->nullable();
            $t->timestamps();
            $t->softDeletes();
            $t->unique(['company_id', 'slug']);
            $t->index(['company_id', 'status', 'visibility']);  // the portal double-gate
            $t->index(['company_id', 'category_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kb_articles');
        Schema::dropIfExists('kb_categories');
    }
};
