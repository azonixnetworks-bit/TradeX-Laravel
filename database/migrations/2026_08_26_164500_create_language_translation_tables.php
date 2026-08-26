<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'language_profiles',
            function (Blueprint $table): void {
                $table->id();
                $table->string('code', 10)->unique();
                $table->string('english_name', 80);
                $table->string('native_name', 120);
                $table->string('direction', 3)->default('LTR');
                $table->boolean('is_source')->default(false);
                $table->boolean(
                    'is_translation_enabled'
                )->default(true);
                $table->unsignedBigInteger('version')->default(0);
                $table->foreignId(
                    'updated_by_admin_id'
                )->nullable();
                $table->timestamps();

                $table->foreign(
                    'updated_by_admin_id',
                    'language_profile_admin_fk'
                )
                    ->references('id')
                    ->on('admin_users')
                    ->nullOnDelete();

                $table->index(
                    [
                        'is_translation_enabled',
                        'code',
                    ],
                    'language_profile_enabled_idx'
                );
            }
        );

        Schema::create(
            'translation_keys',
            function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->string('group_name', 60);
                $table->string('translation_key', 180);
                $table->text('source_text');
                $table->char('source_sha256', 64);
                $table->unsignedBigInteger('version')->default(0);
                $table->foreignId(
                    'created_by_admin_id'
                )->nullable();
                $table->foreignId(
                    'updated_by_admin_id'
                )->nullable();
                $table->timestamps();

                $table->unique(
                    [
                        'group_name',
                        'translation_key',
                    ],
                    'translation_key_group_key_uq'
                );

                $table->index(
                    [
                        'group_name',
                        'updated_at',
                    ],
                    'translation_key_group_idx'
                );

                $table->foreign(
                    'created_by_admin_id',
                    'translation_key_created_admin_fk'
                )
                    ->references('id')
                    ->on('admin_users')
                    ->nullOnDelete();

                $table->foreign(
                    'updated_by_admin_id',
                    'translation_key_updated_admin_fk'
                )
                    ->references('id')
                    ->on('admin_users')
                    ->nullOnDelete();
            }
        );

        Schema::create(
            'translation_values',
            function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();

                $table->foreignId(
                    'translation_key_id'
                );

                $table->string(
                    'language_code',
                    10
                );

                $table->text(
                    'translated_text'
                )->nullable();

                $table->char(
                    'source_sha256_at_translation',
                    64
                )->nullable();

                $table->char(
                    'translation_sha256',
                    64
                )->nullable();

                $table->string(
                    'status',
                    20
                )->default('DRAFT');

                $table->unsignedBigInteger(
                    'version'
                )->default(0);

                $table->foreignId(
                    'updated_by_admin_id'
                )->nullable();

                $table->foreignId(
                    'reviewed_by_admin_id'
                )->nullable();

                $table->timestamp(
                    'reviewed_at'
                )->nullable();

                $table->timestamps();

                $table->unique(
                    [
                        'translation_key_id',
                        'language_code',
                    ],
                    'translation_value_key_language_uq'
                );

                $table->index(
                    [
                        'language_code',
                        'status',
                    ],
                    'translation_value_language_status_idx'
                );

                $table->foreign(
                    'translation_key_id',
                    'translation_value_key_fk'
                )
                    ->references('id')
                    ->on('translation_keys')
                    ->restrictOnDelete();

                $table->foreign(
                    'language_code',
                    'translation_value_language_fk'
                )
                    ->references('code')
                    ->on('language_profiles')
                    ->restrictOnDelete();

                $table->foreign(
                    'updated_by_admin_id',
                    'translation_value_updated_admin_fk'
                )
                    ->references('id')
                    ->on('admin_users')
                    ->nullOnDelete();

                $table->foreign(
                    'reviewed_by_admin_id',
                    'translation_value_reviewed_admin_fk'
                )
                    ->references('id')
                    ->on('admin_users')
                    ->nullOnDelete();
            }
        );

        $now = now();

        DB::table(
            'language_profiles'
        )->insert([
            [
                'code' => 'en',
                'english_name' => 'English',
                'native_name' => 'English',
                'direction' => 'LTR',
                'is_source' => true,
                'is_translation_enabled' => true,
                'version' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'code' => 'ta',
                'english_name' => 'Tamil',
                'native_name' => 'தமிழ்',
                'direction' => 'LTR',
                'is_source' => false,
                'is_translation_enabled' => true,
                'version' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'code' => 'si',
                'english_name' => 'Sinhala',
                'native_name' => 'සිංහල',
                'direction' => 'LTR',
                'is_source' => false,
                'is_translation_enabled' => true,
                'version' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'code' => 'hi',
                'english_name' => 'Hindi',
                'native_name' => 'हिन्दी',
                'direction' => 'LTR',
                'is_source' => false,
                'is_translation_enabled' => true,
                'version' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'code' => 'ml',
                'english_name' => 'Malayalam',
                'native_name' => 'മലയാളം',
                'direction' => 'LTR',
                'is_source' => false,
                'is_translation_enabled' => true,
                'version' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'code' => 'te',
                'english_name' => 'Telugu',
                'native_name' => 'తెలుగు',
                'direction' => 'LTR',
                'is_source' => false,
                'is_translation_enabled' => true,
                'version' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'code' => 'ur',
                'english_name' => 'Urdu',
                'native_name' => 'اردو',
                'direction' => 'RTL',
                'is_source' => false,
                'is_translation_enabled' => true,
                'version' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'translation_values'
        );

        Schema::dropIfExists(
            'translation_keys'
        );

        Schema::dropIfExists(
            'language_profiles'
        );
    }
};
