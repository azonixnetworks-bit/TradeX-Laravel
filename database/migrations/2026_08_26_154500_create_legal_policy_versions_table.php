<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'legal_policy_versions',
            function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();

                $table->string(
                    'policy_type',
                    60
                );

                $table->unsignedInteger(
                    'version_number'
                );

                $table->string(
                    'title',
                    180
                );

                $table->string(
                    'summary',
                    500
                )->nullable();

                $table->longText(
                    'content'
                );

                $table->char(
                    'content_sha256',
                    64
                );

                $table->string(
                    'status',
                    20
                )->default('DRAFT');

                $table->timestamp(
                    'effective_at'
                )->nullable();

                $table->timestamp(
                    'published_at'
                )->nullable();

                $table->unsignedBigInteger(
                    'revision_version'
                )->default(0);

                $table->foreignId(
                    'created_by_admin_id'
                )->nullable();

                $table->foreignId(
                    'updated_by_admin_id'
                )->nullable();

                $table->foreignId(
                    'published_by_admin_id'
                )->nullable();

                $table->timestamps();

                $table->unique(
                    [
                        'policy_type',
                        'version_number',
                    ],
                    'legal_policy_type_version_uq'
                );

                $table->index(
                    [
                        'policy_type',
                        'status',
                    ],
                    'legal_policy_type_status_idx'
                );

                $table->index(
                    [
                        'policy_type',
                        'effective_at',
                    ],
                    'legal_policy_effective_idx'
                );

                foreach (
                    [
                        'created_by_admin_id'
                            => 'legal_created_admin_fk',

                        'updated_by_admin_id'
                            => 'legal_updated_admin_fk',

                        'published_by_admin_id'
                            => 'legal_published_admin_fk',
                    ]
                    as $column => $name
                ) {
                    $table->foreign(
                        $column,
                        $name
                    )
                        ->references('id')
                        ->on('admin_users')
                        ->nullOnDelete();
                }
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'legal_policy_versions'
        );
    }
};
