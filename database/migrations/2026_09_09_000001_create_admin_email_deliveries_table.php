<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_email_deliveries', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('admin_user_id')
                ->nullable()
                ->constrained('admin_users')
                ->nullOnDelete();
            $table->string('event_type', 64)->index();
            $table->string('recipient_email', 190);
            $table->string('recipient_name', 150)->nullable();
            $table->string('subject', 190);
            $table->string('mailer', 64);
            $table->string('dispatch_status', 32)->default('PENDING')->index();
            $table->string('delivery_status', 32)->default('UNCONFIRMED')->index();
            $table->unsignedSmallInteger('attempt_count')->default(0);
            $table->timestamp('sent_at')->nullable()->index();
            $table->timestamp('failed_at')->nullable()->index();
            $table->string('last_error_code', 100)->nullable();
            $table->string('last_error_message', 500)->nullable();
            $table->json('context')->nullable();
            $table->timestamps();

            $table->index(['admin_user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_email_deliveries');
    }
};
