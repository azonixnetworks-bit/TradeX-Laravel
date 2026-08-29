<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const REGISTRATION_INVITATION_FK =
        'urs_onboarding_invitation_fk';

    private const ONBOARDING_SESSION_FK =
        'onboard_legal_session_fk';

    private const ONBOARDING_POLICY_FK =
        'onboard_legal_policy_fk';

    public function up(): void
    {
        /*
         * MySQL limits identifiers to 64 characters. The default Laravel
         * foreign-key names for these long table/column combinations exceed
         * that limit, so every new FK in this migration uses a short explicit
         * name.
         *
         * This migration is also intentionally retry-safe because MySQL DDL
         * may auto-commit individual ALTER statements before a later statement
         * fails. A developer can therefore rerun `php artisan migrate` after
         * fixing a failed first attempt without manually deleting partial
         * columns or indexes.
         */

        if (! Schema::hasColumn(
            'user_registration_sessions',
            'source'
        )) {
            Schema::table(
                'user_registration_sessions',
                function (Blueprint $table): void {
                    $table->string('source', 20)
                        ->default('SELF_REGISTER')
                        ->after('language_code');
                }
            );
        }

        if (! Schema::hasColumn(
            'user_registration_sessions',
            'customer_onboarding_invitation_id'
        )) {
            Schema::table(
                'user_registration_sessions',
                function (Blueprint $table): void {
                    $table->unsignedBigInteger(
                        'customer_onboarding_invitation_id'
                    )
                        ->nullable()
                        ->after('source');
                }
            );
        }

        if (! Schema::hasColumn(
            'user_registration_sessions',
            'password_set_at'
        )) {
            Schema::table(
                'user_registration_sessions',
                function (Blueprint $table): void {
                    $table->timestamp('password_set_at')
                        ->nullable()
                        ->after('whatsapp_verified_at');
                }
            );
        }

        if (! Schema::hasIndex(
            'user_registration_sessions',
            'user_registration_sessions_source_index'
        )) {
            Schema::table(
                'user_registration_sessions',
                function (Blueprint $table): void {
                    $table->index(
                        'source',
                        'user_registration_sessions_source_index'
                    );
                }
            );
        }

        if (! Schema::hasIndex(
            'user_registration_sessions',
            'registration_source_status_idx'
        )) {
            Schema::table(
                'user_registration_sessions',
                function (Blueprint $table): void {
                    $table->index(
                        [
                            'source',
                            'status',
                        ],
                        'registration_source_status_idx'
                    );
                }
            );
        }

        if (! $this->foreignKeyExists(
            'user_registration_sessions',
            self::REGISTRATION_INVITATION_FK
        )) {
            Schema::table(
                'user_registration_sessions',
                function (Blueprint $table): void {
                    $table->foreign(
                        'customer_onboarding_invitation_id',
                        self::REGISTRATION_INVITATION_FK
                    )
                        ->references('id')
                        ->on('customer_onboarding_invitations')
                        ->nullOnDelete();
                }
            );
        }

        if (! Schema::hasColumn(
            'customer_onboarding_invitations',
            'activation_consumed_at'
        )) {
            Schema::table(
                'customer_onboarding_invitations',
                function (Blueprint $table): void {
                    $table->timestamp(
                        'activation_consumed_at'
                    )
                        ->nullable()
                        ->after('last_invited_at');
                }
            );
        }

        if (! Schema::hasTable(
            'onboarding_legal_policy_acceptances'
        )) {
            Schema::create(
                'onboarding_legal_policy_acceptances',
                function (Blueprint $table): void {
                    $table->id();
                    $table->uuid('uuid')->unique();

                    $table->unsignedBigInteger(
                        'user_registration_session_id'
                    );

                    $table->unsignedBigInteger(
                        'legal_policy_version_id'
                    );

                    $table->string('policy_type', 60);
                    $table->unsignedInteger(
                        'policy_version_number'
                    );
                    $table->char('content_sha256', 64);
                    $table->timestamp('accepted_at');
                    $table->string(
                        'accepted_ip',
                        45
                    )->nullable();
                    $table->string(
                        'accepted_user_agent',
                        1024
                    )->nullable();
                    $table->timestamps();

                    $table->unique(
                        [
                            'user_registration_session_id',
                            'legal_policy_version_id',
                        ],
                        'onboarding_legal_session_version_uq'
                    );

                    $table->index(
                        [
                            'user_registration_session_id',
                            'policy_type',
                        ],
                        'onboarding_legal_session_type_idx'
                    );

                    $table->foreign(
                        'user_registration_session_id',
                        self::ONBOARDING_SESSION_FK
                    )
                        ->references('id')
                        ->on('user_registration_sessions')
                        ->cascadeOnDelete();

                    $table->foreign(
                        'legal_policy_version_id',
                        self::ONBOARDING_POLICY_FK
                    )
                        ->references('id')
                        ->on('legal_policy_versions')
                        ->restrictOnDelete();
                }
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'onboarding_legal_policy_acceptances'
        );

        if (Schema::hasColumn(
            'customer_onboarding_invitations',
            'activation_consumed_at'
        )) {
            Schema::table(
                'customer_onboarding_invitations',
                function (Blueprint $table): void {
                    $table->dropColumn(
                        'activation_consumed_at'
                    );
                }
            );
        }

        if ($this->foreignKeyExists(
            'user_registration_sessions',
            self::REGISTRATION_INVITATION_FK
        )) {
            Schema::table(
                'user_registration_sessions',
                function (Blueprint $table): void {
                    $table->dropForeign(
                        self::REGISTRATION_INVITATION_FK
                    );
                }
            );
        }

        if (Schema::hasIndex(
            'user_registration_sessions',
            'registration_source_status_idx'
        )) {
            Schema::table(
                'user_registration_sessions',
                function (Blueprint $table): void {
                    $table->dropIndex(
                        'registration_source_status_idx'
                    );
                }
            );
        }

        if (Schema::hasIndex(
            'user_registration_sessions',
            'user_registration_sessions_source_index'
        )) {
            Schema::table(
                'user_registration_sessions',
                function (Blueprint $table): void {
                    $table->dropIndex(
                        'user_registration_sessions_source_index'
                    );
                }
            );
        }

        foreach (
            [
                'customer_onboarding_invitation_id',
                'source',
                'password_set_at',
            ] as $column
        ) {
            if (Schema::hasColumn(
                'user_registration_sessions',
                $column
            )) {
                Schema::table(
                    'user_registration_sessions',
                    function (Blueprint $table) use ($column): void {
                        $table->dropColumn($column);
                    }
                );
            }
        }
    }

    private function foreignKeyExists(
        string $table,
        string $foreignKeyName
    ): bool {
        foreach (Schema::getForeignKeys($table) as $foreignKey) {
            if (
                ($foreignKey['name'] ?? null)
                === $foreignKeyName
            ) {
                return true;
            }
        }

        return false;
    }
};
