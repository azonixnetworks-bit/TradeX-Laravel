<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {

            $table->string('status_reason', 500)
                ->nullable()
                ->after('status');

            $table->timestamp('status_changed_at')
                ->nullable()
                ->after('status_reason');

            $table->unsignedBigInteger('status_changed_by_admin_id')
                ->nullable()
                ->after('status_changed_at');

            $table->foreign('status_changed_by_admin_id')
                ->references('id')
                ->on('admin_users')
                ->nullOnDelete();

            $table->index('status_changed_at');
            $table->index('status_changed_by_admin_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {

            $table->dropForeign([
                'status_changed_by_admin_id'
            ]);

            $table->dropIndex([
                'status_changed_at'
            ]);

            $table->dropIndex([
                'status_changed_by_admin_id'
            ]);

            $table->dropColumn([
                'status_reason',
                'status_changed_at',
                'status_changed_by_admin_id',
            ]);
        });
    }
};