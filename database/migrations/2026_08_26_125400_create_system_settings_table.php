<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'system_settings',
            function (Blueprint $table): void {
                $table->id();

                $table->string(
                    'setting_key',
                    120
                )->unique();

                $table->string(
                    'group_name',
                    40
                );

                $table->text(
                    'value'
                )->nullable();

                $table->string(
                    'value_type',
                    20
                );

                /*
                 * Optimistic concurrency version.
                 * A value of zero means the row is at its initial/default state.
                 */
                $table->unsignedBigInteger(
                    'version'
                )->default(0);

                $table->foreignId(
                    'updated_by_admin_id'
                )
                    ->nullable()
                    ->constrained(
                        'admin_users'
                    )
                    ->nullOnDelete();

                $table->timestamps();

                $table->index(
                    'group_name'
                );

                $table->index(
                    'updated_at'
                );
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'system_settings'
        );
    }
};
