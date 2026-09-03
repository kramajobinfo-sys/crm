<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Module 6 — Email. Structural only: composing/sending persists an `emails` row (like chat
 * outbound) but no live SMTP is wired, so nothing is transmitted. Emails link polymorphically
 * to a customer/lead/deal and write a timeline entry on that record.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('email_accounts', function (Blueprint $t) {
            $t->id(); $t->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $t->string('name', 128);
            $t->string('email_address', 191);
            $t->string('from_name', 128)->nullable();
            $t->string('provider', 24)->default('smtp');        // smtp|gmail|outlook|ses
            $t->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();  // owner of a personal account
            $t->boolean('is_shared')->default(true);            // shared (team) vs personal
            $t->boolean('is_default')->default(false);
            $t->boolean('is_active')->default(true);
            $t->text('signature')->nullable();
            $t->timestamps();
            $t->unique(['company_id', 'email_address']);
        });

        Schema::create('email_templates', function (Blueprint $t) {
            $t->id(); $t->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $t->string('name', 128); $t->string('code', 32);
            $t->string('category', 48)->nullable();
            $t->string('subject', 255);
            $t->text('body_html');
            $t->boolean('is_active')->default(true);
            $t->timestamps();
            $t->unique(['company_id', 'code']);
        });

        Schema::create('emails', function (Blueprint $t) {
            $t->id(); $t->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $t->foreignId('email_account_id')->nullable()->constrained('email_accounts')->nullOnDelete();
            $t->string('direction', 12)->default('outbound');   // inbound|outbound
            $t->string('status', 12)->default('draft');         // draft|queued|sent|failed|received
            $t->string('from_address', 191)->nullable();
            $t->string('from_name', 128)->nullable();
            $t->json('to')->nullable();                         // [address, …]
            $t->json('cc')->nullable();
            $t->json('bcc')->nullable();
            $t->string('subject', 255)->nullable();
            $t->longText('body_html')->nullable();
            $t->foreignId('template_id')->nullable()->constrained('email_templates')->nullOnDelete();
            $t->string('message_id', 191)->nullable();
            $t->nullableMorphs('related');                      // customer / lead / deal
            $t->unsignedInteger('opens')->default(0);
            $t->unsignedInteger('clicks')->default(0);
            $t->timestamp('opened_at')->nullable();
            $t->timestamp('sent_at')->nullable();
            $t->timestamp('received_at')->nullable();
            $t->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();  // sender
            $t->timestamps(); $t->softDeletes();
            $t->index(['company_id', 'direction', 'status']);
            $t->index(['company_id', 'email_account_id']);
        });

        Schema::create('email_attachments', function (Blueprint $t) {
            $t->id(); $t->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $t->foreignId('email_id')->constrained('emails')->cascadeOnDelete();
            $t->string('disk', 32)->default('public');
            $t->string('path', 500);
            $t->string('name', 191); $t->string('mime', 128)->nullable();
            $t->unsignedBigInteger('size')->default(0);
            $t->timestamps();
            $t->index(['company_id', 'email_id']);
        });
    }
    public function down(): void {
        foreach (['email_attachments','emails','email_templates','email_accounts'] as $tbl) {
            Schema::dropIfExists($tbl);
        }
    }
};
