<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Module 11 — Marketing. Email/SMS campaigns over a customer/lead audience. Launching
 * materialises recipients and per-recipient messages and marks them sent (structural, like
 * Email/chat outbound) — no live provider is wired.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('sms_providers', function (Blueprint $t) {
            $t->id(); $t->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $t->string('name', 128);
            $t->string('provider', 24)->default('generic');     // twilio|nexmo|unifonic|generic
            $t->string('sender_id', 32)->nullable();
            $t->boolean('is_default')->default(false);
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });

        Schema::create('campaigns', function (Blueprint $t) {
            $t->id(); $t->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $t->string('name', 191);
            $t->string('type', 12)->default('email');           // email|sms
            $t->string('status', 16)->default('draft');         // draft|scheduled|running|sent|paused|cancelled
            $t->string('subject', 255)->nullable();             // email only
            $t->longText('body')->nullable();
            $t->foreignId('email_template_id')->nullable()->constrained('email_templates')->nullOnDelete();
            $t->foreignId('email_account_id')->nullable()->constrained('email_accounts')->nullOnDelete();
            $t->foreignId('sms_provider_id')->nullable()->constrained('sms_providers')->nullOnDelete();
            // Who to send to: {source: customers|leads, filters: {...}}
            $t->json('audience')->nullable();
            $t->timestamp('scheduled_at')->nullable();
            $t->timestamp('sent_at')->nullable();
            $t->unsignedInteger('recipients_count')->default(0);
            $t->unsignedInteger('sent_count')->default(0);
            $t->unsignedInteger('opened_count')->default(0);
            $t->unsignedInteger('clicked_count')->default(0);
            $t->unsignedInteger('failed_count')->default(0);
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps(); $t->softDeletes();
            $t->index(['company_id', 'status', 'type']);
        });

        Schema::create('campaign_recipients', function (Blueprint $t) {
            $t->id(); $t->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $t->foreignId('campaign_id')->constrained('campaigns')->cascadeOnDelete();
            $t->nullableMorphs('recipient');                     // customer / lead
            $t->string('name', 191)->nullable();
            $t->string('email', 191)->nullable();
            $t->string('phone', 32)->nullable();
            $t->string('status', 16)->default('pending');        // pending|sent|opened|clicked|bounced|failed
            $t->timestamp('sent_at')->nullable();
            $t->timestamp('opened_at')->nullable();
            $t->timestamps();
            $t->index(['company_id', 'campaign_id', 'status']);
        });

        Schema::create('campaign_messages', function (Blueprint $t) {
            $t->id(); $t->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $t->foreignId('campaign_id')->constrained('campaigns')->cascadeOnDelete();
            $t->foreignId('campaign_recipient_id')->constrained('campaign_recipients')->cascadeOnDelete();
            $t->string('channel', 12)->default('email');
            $t->string('to_address', 191)->nullable();          // email or phone
            $t->string('subject', 255)->nullable();
            $t->longText('body')->nullable();
            $t->string('status', 16)->default('queued');         // queued|sent|failed
            $t->string('message_id', 191)->nullable();
            $t->timestamp('sent_at')->nullable();
            $t->timestamps();
            $t->index(['company_id', 'campaign_id']);
        });
    }
    public function down(): void {
        foreach (['campaign_messages','campaign_recipients','campaigns','sms_providers'] as $tbl) {
            Schema::dropIfExists($tbl);
        }
    }
};
