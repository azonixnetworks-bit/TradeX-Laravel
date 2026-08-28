<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'customer_onboarding_invitations',
            function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();

                $table->string('name', 120);
                $table->string('email')->index();
                $table->string('whatsapp_number', 30)->index();
                $table->char('country_code', 2)->index();
                $table->string('language_code', 10);

                $table->string(
                    'referral_code_input',
                    30
                )->nullable();

                $table->foreignId(
                    'referred_by_user_id'
                )
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();

                $table->string(
                    'status',
                    20
                )
                    ->default('PENDING')
                    ->index();

                /*
                 * Only an active PENDING invitation keeps this fingerprint.
                 * The nullable unique column closes the concurrent duplicate
                 * invitation race while allowing historical rows to coexist.
                 */
                $table->char(
                    'active_identity_hash',
                    64
                )
                    ->nullable()
                    ->unique();

                $table->foreignId(
                    'matched_user_id'
                )
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();

                $table->foreignId(
                    'created_by_admin_id'
                )
                    ->constrained('admin_users')
                    ->restrictOnDelete();

                $table->text(
                    'internal_note'
                )->nullable();

                $table->timestamp(
                    'expires_at'
                )->index();

                $table->timestamp(
                    'matched_at'
                )->nullable();

                $table->timestamps();

                $table->index([
                    'status',
                    'expires_at',
                ]);
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'customer_onboarding_invitations'
        );
    }
};
