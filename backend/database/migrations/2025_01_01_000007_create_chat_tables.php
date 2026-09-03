<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('chat_channels', function (Blueprint $t) {
            $t->id(); $t->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $t->string('type', 24);                       // whatsapp|messenger|instagram|telegram|sms|webchat
            $t->string('name', 128);
            $t->string('external_account_id', 191)->nullable();
            $t->text('config')->nullable();               // encrypted provider credentials
            $t->boolean('is_active')->default(true);
            $t->timestamps();
            $t->unique(['company_id', 'type', 'external_account_id'], 'chat_channels_account_unique');
            $t->index(['company_id', 'type']);
        });
        Schema::create('chat_contacts', function (Blueprint $t) {
            $t->id(); $t->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $t->foreignId('channel_id')->constrained('chat_channels')->cascadeOnDelete();
            $t->string('external_user_id', 191);          // provider-side id (wa id, psid, chat id, msisdn…)
            $t->string('display_name', 191)->nullable();
            $t->string('avatar_url', 500)->nullable();
            $t->string('phone', 32)->nullable(); $t->string('email', 191)->nullable();
            // Links to Leads (M2) / Customers (M3). Deliberately unconstrained: those tables ship later.
            $t->string('linked_type', 191)->nullable(); $t->unsignedBigInteger('linked_id')->nullable();
            $t->timestamps();
            $t->unique(['channel_id', 'external_user_id'], 'chat_contacts_identity_unique');
            $t->index(['linked_type', 'linked_id']);
        });
        Schema::create('chat_conversations', function (Blueprint $t) {
            $t->id(); $t->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $t->foreignId('channel_id')->constrained('chat_channels')->cascadeOnDelete();
            $t->foreignId('contact_id')->constrained('chat_contacts')->cascadeOnDelete();
            $t->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $t->string('external_thread_id', 191)->nullable();
            $t->string('subject', 191)->nullable();
            $t->string('status', 16)->default('open');    // open|pending|snoozed|resolved|closed
            $t->string('priority', 16)->default('normal');// low|normal|high|urgent
            $t->json('tags')->nullable();
            $t->text('last_message_preview')->nullable();
            $t->timestamp('last_message_at')->nullable();
            $t->timestamp('last_inbound_at')->nullable(); // drives WhatsApp/Meta 24h service window
            $t->unsignedInteger('unread_count')->default(0);
            $t->timestamps(); $t->softDeletes();
            $t->index(['company_id', 'status', 'last_message_at']);
            $t->index(['assigned_to', 'status']);
        });
        Schema::create('chat_messages', function (Blueprint $t) {
            $t->id(); $t->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $t->foreignId('conversation_id')->constrained('chat_conversations')->cascadeOnDelete();
            $t->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete(); // agent, for outbound
            $t->string('direction', 12);                  // inbound|outbound|note
            $t->string('content_type', 24)->default('text'); // text|image|file|audio|video|template|system
            $t->text('body')->nullable();
            $t->string('external_message_id', 191)->nullable();
            $t->string('status', 16)->default('sent');    // queued|sent|delivered|read|failed
            $t->string('error', 500)->nullable();
            $t->json('meta')->nullable();
            $t->timestamp('sent_at')->nullable();
            $t->timestamps();
            $t->index(['conversation_id', 'created_at']);
            $t->index(['company_id', 'direction']);
        });
        Schema::create('chat_message_attachments', function (Blueprint $t) {
            $t->id(); $t->foreignId('message_id')->constrained('chat_messages')->cascadeOnDelete();
            $t->string('disk', 32)->default('public'); $t->string('path', 500);
            $t->string('name', 191); $t->string('mime', 128)->nullable();
            $t->unsignedBigInteger('size')->default(0); $t->timestamps();
        });
        Schema::create('chat_canned_responses', function (Blueprint $t) {
            $t->id(); $t->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $t->string('shortcut', 64); $t->string('title', 191); $t->text('body');
            $t->boolean('is_active')->default(true); $t->timestamps();
            $t->unique(['company_id', 'shortcut']);
        });
    }
    public function down(): void {
        foreach (['chat_canned_responses','chat_message_attachments','chat_messages',
            'chat_conversations','chat_contacts','chat_channels'] as $tbl) Schema::dropIfExists($tbl);
    }
};
