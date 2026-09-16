<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trading_floor_clients', function (Blueprint $table): void {
            $table->string('access_override_mode', 20)->default('AUTO')->after('status');
            $table->timestamp('access_override_until')->nullable()->after('access_override_mode');
            $table->text('access_override_reason')->nullable()->after('access_override_until');
            $table->unsignedBigInteger('access_override_by_admin_id')->nullable()->after('access_override_reason');

            $table->foreign('access_override_by_admin_id', 'tf_client_override_admin_fk')
                ->references('id')
                ->on('admin_users')
                ->nullOnDelete();
        });

        Schema::create('trading_floor_access_devices', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid');
            $table->string('device_code', 100);
            $table->string('name');
            $table->string('floor_code', 50)->nullable();
            $table->char('token_hash', 64);
            $table->string('status', 20)->default('ACTIVE');
            $table->timestamp('last_seen_at')->nullable();
            $table->string('last_ip', 45)->nullable();
            $table->unsignedBigInteger('created_by_admin_id')->nullable();
            $table->timestamps();

            $table->unique('uuid', 'tf_device_uuid_uq');
            $table->unique('device_code', 'tf_device_code_uq');
            $table->index(['status', 'floor_code'], 'tf_device_status_floor_idx');
            $table->foreign('created_by_admin_id', 'tf_device_admin_fk')
                ->references('id')
                ->on('admin_users')
                ->nullOnDelete();
        });

        Schema::create('trading_floor_access_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('commission_enforcement_mode', 30)->default('WARNING');
            $table->unsignedSmallInteger('block_after_overdue_days')->default(0);
            $table->boolean('allow_exit_when_blocked')->default(true);
            $table->boolean('require_active_client')->default(true);
            $table->boolean('enforce_floor_match')->default(false);
            $table->boolean('api_enabled')->default(true);
            $table->unsignedBigInteger('updated_by_admin_id')->nullable();
            $table->timestamps();

            $table->foreign('updated_by_admin_id', 'tf_setting_admin_fk')
                ->references('id')
                ->on('admin_users')
                ->nullOnDelete();
        });

        DB::table('trading_floor_access_settings')->insert([
            'commission_enforcement_mode' => 'WARNING',
            'block_after_overdue_days' => 0,
            'allow_exit_when_blocked' => true,
            'require_active_client' => true,
            'enforce_floor_match' => false,
            'api_enabled' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Schema::create('trading_floor_access_attempts', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid');
            $table->unsignedBigInteger('trading_floor_access_device_id');
            $table->string('device_code', 100);
            $table->string('device_event_id', 100);
            $table->unsignedBigInteger('trading_floor_client_id')->nullable();
            $table->unsignedBigInteger('trading_floor_access_credential_id')->nullable();
            $table->string('credential_type', 30);
            $table->char('credential_fingerprint', 64);
            $table->string('direction', 20);
            $table->string('decision', 20);
            $table->string('reason_code', 80);
            $table->string('reason', 255)->nullable();
            $table->timestamp('occurred_at');
            $table->json('response_json')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique('uuid', 'tf_attempt_uuid_uq');
            $table->unique(
                ['trading_floor_access_device_id', 'device_event_id'],
                'tf_attempt_device_event_uq'
            );
            $table->index(['trading_floor_client_id', 'occurred_at'], 'tf_attempt_client_time_idx');
            $table->index(['decision', 'occurred_at'], 'tf_attempt_decision_time_idx');
            $table->foreign('trading_floor_access_device_id', 'tf_attempt_device_fk')
                ->references('id')
                ->on('trading_floor_access_devices')
                ->cascadeOnDelete();
            $table->foreign('trading_floor_client_id', 'tf_attempt_client_fk')
                ->references('id')
                ->on('trading_floor_clients')
                ->nullOnDelete();
            $table->foreign('trading_floor_access_credential_id', 'tf_attempt_credential_fk')
                ->references('id')
                ->on('trading_floor_access_credentials')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trading_floor_access_attempts');
        Schema::dropIfExists('trading_floor_access_settings');
        Schema::dropIfExists('trading_floor_access_devices');

        Schema::table('trading_floor_clients', function (Blueprint $table): void {
            $table->dropForeign('tf_client_override_admin_fk');
            $table->dropColumn([
                'access_override_mode',
                'access_override_until',
                'access_override_reason',
                'access_override_by_admin_id',
            ]);
        });
    }
};
