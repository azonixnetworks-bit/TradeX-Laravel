<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {

            /*
            |--------------------------------------------------------------------------
            | Public Identifier
            |--------------------------------------------------------------------------
            */

            $table->uuid('uuid')
                ->nullable()
                ->unique()
                ->after('id');


            /*
            |--------------------------------------------------------------------------
            | WhatsApp
            |--------------------------------------------------------------------------
            */

            $table->string('whatsapp_number', 30)
                ->nullable()
                ->unique()
                ->after('email_verified_at');

            $table->timestamp('whatsapp_verified_at')
                ->nullable()
                ->after('whatsapp_number');


            /*
            |--------------------------------------------------------------------------
            | Referral
            |--------------------------------------------------------------------------
            */

            $table->string('referral_code', 30)
                ->nullable()
                ->unique()
                ->after('password');

            $table->unsignedBigInteger('referred_by_user_id')
                ->nullable()
                ->after('referral_code');


            /*
            |--------------------------------------------------------------------------
            | User Region / Language
            |--------------------------------------------------------------------------
            */

            $table->string('country_code', 2)
                ->nullable()
                ->after('referred_by_user_id');

            $table->string('language_code', 10)
                ->default('en')
                ->after('country_code');


            /*
            |--------------------------------------------------------------------------
            | Account Access
            |--------------------------------------------------------------------------
            */

            $table->string('account_type', 20)
                ->default('FREE')
                ->after('language_code');

            $table->string('status', 20)
                ->default('PENDING')
                ->after('account_type');


            /*
            |--------------------------------------------------------------------------
            | Account Lifecycle
            |--------------------------------------------------------------------------
            */

            $table->timestamp('premium_activated_at')
                ->nullable()
                ->after('status');

            $table->timestamp('suspended_at')
                ->nullable()
                ->after('premium_activated_at');

            $table->timestamp('blocked_at')
                ->nullable()
                ->after('suspended_at');

            $table->timestamp('last_login_at')
                ->nullable()
                ->after('blocked_at');

            $table->string('last_login_ip', 45)
                ->nullable()
                ->after('last_login_at');

            $table->softDeletes();


            /*
            |--------------------------------------------------------------------------
            | Indexes
            |--------------------------------------------------------------------------
            */

            $table->index('account_type');
            $table->index('status');
            $table->index('country_code');
            $table->index('language_code');
            $table->index('referred_by_user_id');

            $table->foreign('referred_by_user_id')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {

            $table->dropForeign([
                'referred_by_user_id',
            ]);

            $table->dropIndex([
                'account_type',
            ]);

            $table->dropIndex([
                'status',
            ]);

            $table->dropIndex([
                'country_code',
            ]);

            $table->dropIndex([
                'language_code',
            ]);

            $table->dropIndex([
                'referred_by_user_id',
            ]);

            $table->dropSoftDeletes();

            $table->dropColumn([
                'uuid',

                'whatsapp_number',
                'whatsapp_verified_at',

                'referral_code',
                'referred_by_user_id',

                'country_code',
                'language_code',

                'account_type',
                'status',

                'premium_activated_at',
                'suspended_at',
                'blocked_at',

                'last_login_at',
                'last_login_ip',
            ]);
        });
    }
};