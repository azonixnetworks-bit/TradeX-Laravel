<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_orders', function (Blueprint $table): void {
            $table->index(
                ['user_id', 'created_at', 'id'],
                'pay_order_user_created_id_idx'
            );

            $table->index(
                ['user_id', 'provider', 'created_at'],
                'pay_order_user_provider_created_idx'
            );
        });

        Schema::table('affiliate_qualified_sales', function (Blueprint $table): void {
            $table->index(
                ['affiliate_user_id', 'qualified_at', 'id'],
                'aff_sales_user_qualified_id_idx'
            );

            $table->index(
                ['affiliate_user_id', 'referred_user_id', 'qualified_at'],
                'aff_sales_user_ref_qualified_idx'
            );
        });

        Schema::table('affiliate_commissions', function (Blueprint $table): void {
            $table->index(
                ['affiliate_user_id', 'rate_source', 'earned_at'],
                'aff_comm_user_rate_earned_idx'
            );
        });

        Schema::table('user_notification_receipts', function (Blueprint $table): void {
            $table->index(
                ['user_id', 'notification_message_id', 'read_at'],
                'usr_notif_user_msg_read_idx'
            );
        });

        Schema::table('notification_messages', function (Blueprint $table): void {
            $table->index(
                ['is_active', 'audience_access_level', 'published_at'],
                'notif_msg_active_audience_publish_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::table('notification_messages', function (Blueprint $table): void {
            $table->dropIndex('notif_msg_active_audience_publish_idx');
        });

        Schema::table('user_notification_receipts', function (Blueprint $table): void {
            $table->dropIndex('usr_notif_user_msg_read_idx');
        });

        Schema::table('affiliate_commissions', function (Blueprint $table): void {
            $table->dropIndex('aff_comm_user_rate_earned_idx');
        });

        Schema::table('affiliate_qualified_sales', function (Blueprint $table): void {
            $table->dropIndex('aff_sales_user_ref_qualified_idx');
            $table->dropIndex('aff_sales_user_qualified_id_idx');
        });

        Schema::table('payment_orders', function (Blueprint $table): void {
            $table->dropIndex('pay_order_user_provider_created_idx');
            $table->dropIndex('pay_order_user_created_id_idx');
        });
    }
};
