<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('staff_kyc_documents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('staff_member_id')->constrained()->cascadeOnDelete();
            $table->string('document_type', 40);
            $table->string('storage_disk', 32)->default('private');
            $table->string('document_path', 500);
            $table->string('status', 30)->default('PENDING');
            $table->text('review_note')->nullable();
            $table->foreignId('reviewed_by_admin_id')->nullable()->constrained('admin_users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->unique(['staff_member_id', 'document_type']);
        });
    }

    public function down(): void { Schema::dropIfExists('staff_kyc_documents'); }
};
