<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table(
            'customer_onboarding_invitations',
            function (Blueprint $table): void {
                $table->string(
                    'onboarding_stage',
                    32
                )
                    ->default('INVITE_CREATED')
                    ->index()
                    ->after('status');

                $table->char(
                    'activation_token_hash',
                    64
                )
                    ->nullable()
                    ->unique()
                    ->after('onboarding_stage');

                $table->timestamp(
                    'invited_at'
                )
                    ->nullable()
                    ->after('activation_token_hash');

                $table->timestamp(
                    'last_invited_at'
                )
                    ->nullable()
                    ->after('invited_at');

                $table->unsignedSmallInteger(
                    'invite_send_count'
                )
                    ->default(0)
                    ->after('last_invited_at');

                $table->timestamp(
                    'revoked_at'
                )
                    ->nullable()
                    ->after('matched_at');

                $table->foreignId(
                    'revoked_by_admin_id'
                )
                    ->nullable()
                    ->after('revoked_at')
                    ->constrained('admin_users')
                    ->nullOnDelete();

                $table->index([
                    'status',
                    'onboarding_stage',
                ], 'customer_onboarding_status_stage_idx');
            }
        );
    }

    public function down(): void
    {
        Schema::table(
            'customer_onboarding_invitations',
            function (Blueprint $table): void {
                $table->dropIndex(
                    'customer_onboarding_status_stage_idx'
                );

                $table->dropConstrainedForeignId(
                    'revoked_by_admin_id'
                );

                $table->dropColumn([
                    'onboarding_stage',
                    'activation_token_hash',
                    'invited_at',
                    'last_invited_at',
                    'invite_send_count',
                    'revoked_at',
                ]);
            }
        );
    }
};
