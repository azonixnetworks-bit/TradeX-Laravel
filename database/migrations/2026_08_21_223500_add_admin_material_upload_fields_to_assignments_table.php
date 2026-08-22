<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assignments', function (Blueprint $table): void {
            $table->string('material_disk', 50)->nullable();
            $table->string('material_path', 500)->nullable();
            $table->string('material_original_name', 255)->nullable();
            $table->string('material_mime_type', 120)->nullable();
            $table->unsignedBigInteger('material_size_bytes')->nullable();
            $table->char('material_sha256', 64)->nullable();
            $table->timestamp('material_uploaded_at')->nullable();

            $table->index(
                ['material_disk', 'material_uploaded_at'],
                'assignments_material_lookup_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::table('assignments', function (Blueprint $table): void {
            $table->dropIndex('assignments_material_lookup_idx');

            $table->dropColumn([
                'material_disk',
                'material_path',
                'material_original_name',
                'material_mime_type',
                'material_size_bytes',
                'material_sha256',
                'material_uploaded_at',
            ]);
        });
    }
};
