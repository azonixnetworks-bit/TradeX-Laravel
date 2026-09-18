<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trading_floor_client_documents', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid');
            $table->unsignedBigInteger('trading_floor_client_id');
            $table->string('document_type', 40);
            $table->string('title');
            $table->string('storage_path', 500);
            $table->string('original_name');
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('file_size');
            $table->string('status', 20)->default('ACTIVE');
            $table->timestamp('archived_at')->nullable();
            $table->text('archived_reason')->nullable();
            $table->unsignedBigInteger('uploaded_by_admin_id')->nullable();
            $table->unsignedBigInteger('archived_by_admin_id')->nullable();
            $table->timestamps();

            $table->unique('uuid', 'tf_doc_uuid_uq');
            $table->index(['trading_floor_client_id', 'status'], 'tf_doc_client_status_idx');
            $table->foreign('trading_floor_client_id', 'tf_doc_client_fk')
                ->references('id')->on('trading_floor_clients')->restrictOnDelete();
            $table->foreign('uploaded_by_admin_id', 'tf_doc_upload_admin_fk')
                ->references('id')->on('admin_users')->nullOnDelete();
            $table->foreign('archived_by_admin_id', 'tf_doc_archive_admin_fk')
                ->references('id')->on('admin_users')->nullOnDelete();
        });

        Schema::create('trading_floor_credential_replacements', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid');
            $table->unsignedBigInteger('trading_floor_client_id');
            $table->unsignedBigInteger('old_credential_id');
            $table->unsignedBigInteger('new_credential_id');
            $table->text('replacement_reason');
            $table->timestamp('replaced_at');
            $table->unsignedBigInteger('replaced_by_admin_id')->nullable();
            $table->timestamps();

            $table->unique('uuid', 'tf_replace_uuid_uq');
            $table->index(['trading_floor_client_id', 'replaced_at'], 'tf_replace_client_time_idx');
            $table->foreign('trading_floor_client_id', 'tf_replace_client_fk')
                ->references('id')->on('trading_floor_clients')->restrictOnDelete();
            $table->foreign('old_credential_id', 'tf_replace_old_cred_fk')
                ->references('id')->on('trading_floor_access_credentials')->restrictOnDelete();
            $table->foreign('new_credential_id', 'tf_replace_new_cred_fk')
                ->references('id')->on('trading_floor_access_credentials')->restrictOnDelete();
            $table->foreign('replaced_by_admin_id', 'tf_replace_admin_fk')
                ->references('id')->on('admin_users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trading_floor_credential_replacements');
        Schema::dropIfExists('trading_floor_client_documents');
    }
};
