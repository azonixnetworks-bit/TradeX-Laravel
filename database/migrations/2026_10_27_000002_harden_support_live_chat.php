<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('support_ticket_attachments', function (Blueprint $table): void {
            $table->string('client_idempotency_key', 64)->nullable()->after('content_hash');
            $table->unique(
                ['ticket_id', 'uploader_type', 'client_idempotency_key'],
                'support_attachment_idempotency_unique'
            );
        });

        Schema::table('support_chat_sessions', function (Blueprint $table): void {
            $table->index(
                ['state', 'last_customer_heartbeat_at'],
                'support_chat_customer_heartbeat_idx'
            );
            $table->index(
                ['state', 'last_agent_heartbeat_at'],
                'support_chat_agent_heartbeat_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::table('support_chat_sessions', function (Blueprint $table): void {
            $table->dropIndex('support_chat_customer_heartbeat_idx');
            $table->dropIndex('support_chat_agent_heartbeat_idx');
        });

        Schema::table('support_ticket_attachments', function (Blueprint $table): void {
            $table->dropUnique('support_attachment_idempotency_unique');
            $table->dropColumn('client_idempotency_key');
        });
    }
};
