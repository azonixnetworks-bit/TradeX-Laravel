<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('investment_agreements', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->foreignId('investment_id')
                ->constrained('investments')
                ->restrictOnDelete();

            $table->unsignedInteger('version');
            $table->string('status', 30)->index();

            $table->string('document_disk', 50)->nullable();
            $table->string('document_path', 500)->nullable();
            $table->string('document_mime_type', 100)->nullable();
            $table->unsignedBigInteger('document_size_bytes')->nullable();
            $table->char('document_sha256', 64)->nullable();

            $table->string('signed_document_disk', 50)->nullable();
            $table->string('signed_document_path', 500)->nullable();
            $table->string('signed_document_mime_type', 100)->nullable();
            $table->unsignedBigInteger('signed_document_size_bytes')->nullable();
            $table->char('signed_document_sha256', 64)->nullable();

            $table->timestamp('generated_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('signed_at')->nullable();
            $table->timestamp('verified_at')->nullable();

            $table->foreignId('generated_by_admin_id')
                ->nullable()
                ->constrained('admin_users')
                ->nullOnDelete();

            $table->foreignId('verified_by_admin_id')
                ->nullable()
                ->constrained('admin_users')
                ->nullOnDelete();

            $table->timestamps();

            $table->unique(
                ['investment_id', 'version'],
                'investment_agreements_investment_version_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('investment_agreements');
    }
};
