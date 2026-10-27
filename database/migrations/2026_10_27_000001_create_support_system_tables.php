<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_sequences', function (Blueprint $table): void {
            $table->string('name', 80)->primary();
            $table->unsignedBigInteger('value')->default(0);
            $table->timestamps();
        });

        Schema::create('support_departments', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name', 120);
            $table->string('slug', 120)->unique();
            $table->string('timezone', 64)->default('Asia/Colombo');
            $table->json('business_hours')->nullable();
            $table->boolean('enabled')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('support_categories', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('department_id')->nullable()->constrained('support_departments')->nullOnDelete();
            $table->string('slug', 120)->unique();
            $table->string('label', 160);
            $table->json('intake_schema')->nullable();
            $table->unsignedSmallInteger('schema_version')->default(1);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('enabled')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('support_sla_policies', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('department_id')->nullable()->constrained('support_departments')->cascadeOnDelete();
            $table->string('priority', 20);
            $table->unsignedInteger('first_response_minutes');
            $table->unsignedInteger('resolution_minutes');
            $table->boolean('enabled')->default(true);
            $table->timestamps();
            $table->unique(['department_id', 'priority'], 'support_sla_department_priority_unique');
        });

        Schema::create('support_tickets', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('public_reference', 32)->unique();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('department_id')->nullable()->constrained('support_departments')->nullOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('support_categories')->nullOnDelete();
            $table->foreignId('assigned_admin_id')->nullable()->constrained('admin_users')->nullOnDelete();
            $table->string('source', 20)->default('ticket');
            $table->string('subject', 180);
            $table->text('description');
            $table->json('intake_data')->nullable();
            $table->string('related_type', 80)->nullable();
            $table->string('related_reference', 120)->nullable();
            $table->string('requested_urgency', 20)->nullable();
            $table->string('priority', 20)->default('normal');
            $table->string('status', 32)->default('open');
            $table->string('idempotency_key', 100)->nullable();
            $table->timestamp('first_response_due_at')->nullable();
            $table->timestamp('resolution_due_at')->nullable();
            $table->timestamp('first_responded_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamp('reopened_at')->nullable();
            $table->unsignedInteger('resolution_cycle')->default(0);
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['user_id', 'idempotency_key'], 'support_ticket_user_idempotency_unique');
            $table->index(['status', 'priority', 'first_response_due_at'], 'support_ticket_queue_idx');
            $table->index(['assigned_admin_id', 'status'], 'support_ticket_assignee_idx');
            $table->index(['user_id', 'created_at'], 'support_ticket_user_idx');
        });

        Schema::create('support_ticket_messages', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('ticket_id')->constrained('support_tickets')->cascadeOnDelete();
            $table->string('sender_type', 20);
            $table->foreignId('sender_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('sender_admin_id')->nullable()->constrained('admin_users')->nullOnDelete();
            $table->string('visibility', 20)->default('public');
            $table->text('body');
            $table->string('client_idempotency_key', 100)->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            $table->unique(['ticket_id', 'sender_type', 'client_idempotency_key'], 'support_message_idempotency_unique');
            $table->index(['ticket_id', 'id'], 'support_message_cursor_idx');
            $table->index(['ticket_id', 'visibility', 'created_at'], 'support_message_visibility_idx');
        });

        Schema::create('support_ticket_attachments', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('ticket_id')->constrained('support_tickets')->cascadeOnDelete();
            $table->foreignId('message_id')->nullable()->constrained('support_ticket_messages')->cascadeOnDelete();
            $table->string('uploader_type', 20);
            $table->foreignId('uploader_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('uploader_admin_id')->nullable()->constrained('admin_users')->nullOnDelete();
            $table->string('disk', 40)->default('private');
            $table->string('path', 500);
            $table->string('original_name', 255);
            $table->string('mime_type', 120);
            $table->unsignedBigInteger('byte_size');
            $table->string('content_hash', 64);
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['ticket_id', 'created_at']);
            $table->index('content_hash');
        });

        Schema::create('support_chat_sessions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('ticket_id')->constrained('support_tickets')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('active_user_id')->nullable()->unique()->constrained('users')->nullOnDelete();
            $table->foreignId('accepted_by_admin_id')->nullable()->constrained('admin_users')->nullOnDelete();
            $table->string('state', 24)->default('queued');
            $table->string('idempotency_key', 100)->nullable();
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('last_customer_heartbeat_at')->nullable();
            $table->timestamp('last_agent_heartbeat_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->string('closure_reason', 120)->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'idempotency_key'], 'support_chat_user_idempotency_unique');
            $table->index(['state', 'queued_at'], 'support_chat_queue_idx');
        });

        Schema::create('support_agent_profiles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('admin_user_id')->unique()->constrained('admin_users')->cascadeOnDelete();
            $table->boolean('enabled')->default(true);
            $table->string('availability', 20)->default('offline');
            $table->unsignedSmallInteger('capacity')->default(3);
            $table->timestamp('heartbeat_at')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('support_agent_departments', function (Blueprint $table): void {
            $table->foreignId('agent_profile_id')->constrained('support_agent_profiles')->cascadeOnDelete();
            $table->foreignId('department_id')->constrained('support_departments')->cascadeOnDelete();
            $table->primary(['agent_profile_id', 'department_id']);
        });

        Schema::create('support_ticket_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('ticket_id')->constrained('support_tickets')->cascadeOnDelete();
            $table->string('event_type', 80);
            $table->string('actor_type', 20);
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->json('payload')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['ticket_id', 'created_at']);
        });

        Schema::create('support_feedback', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('ticket_id')->constrained('support_tickets')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('resolution_cycle');
            $table->unsignedTinyInteger('rating');
            $table->text('comment')->nullable();
            $table->timestamps();
            $table->unique(['ticket_id', 'resolution_cycle'], 'support_feedback_cycle_unique');
        });

        Schema::create('support_canned_replies', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('department_id')->nullable()->constrained('support_departments')->cascadeOnDelete();
            $table->string('title', 120);
            $table->text('body');
            $table->boolean('enabled')->default(true);
            $table->timestamps();
        });

        Schema::create('support_faqs', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('category_id')->nullable()->constrained('support_categories')->nullOnDelete();
            $table->string('question', 255);
            $table->text('answer');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('published')->default(false)->index();
            $table->timestamps();
        });

        Schema::create('support_notification_deliveries', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('ticket_id')->nullable()->constrained('support_tickets')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->foreignId('admin_user_id')->nullable()->constrained('admin_users')->cascadeOnDelete();
            $table->string('channel', 30);
            $table->string('template', 80);
            $table->string('deduplication_key', 160)->unique();
            $table->json('payload');
            $table->string('status', 24)->default('pending')->index();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_notification_deliveries');
        Schema::dropIfExists('support_faqs');
        Schema::dropIfExists('support_canned_replies');
        Schema::dropIfExists('support_feedback');
        Schema::dropIfExists('support_ticket_events');
        Schema::dropIfExists('support_agent_departments');
        Schema::dropIfExists('support_agent_profiles');
        Schema::dropIfExists('support_chat_sessions');
        Schema::dropIfExists('support_ticket_attachments');
        Schema::dropIfExists('support_ticket_messages');
        Schema::dropIfExists('support_tickets');
        Schema::dropIfExists('support_sla_policies');
        Schema::dropIfExists('support_categories');
        Schema::dropIfExists('support_departments');
        Schema::dropIfExists('support_sequences');
    }
};
