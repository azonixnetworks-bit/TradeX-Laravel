<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')
            ->where('account_type', 'PREMIUM')
            ->orderBy('id')
            ->chunkById(
                200,
                function ($users): void {
                    foreach ($users as $user) {
                        $exists = DB::table(
                            'user_entitlements'
                        )
                            ->where(
                                'user_id',
                                $user->id
                            )
                            ->where(
                                'product_code',
                                'EDUCATION_PREMIUM'
                            )
                            ->exists();

                        if ($exists) {
                            continue;
                        }

                        /*
                         * ADMIN-25 legacy bridge:
                         * payment_id deliberately remains NULL.
                         * This preserves ADMIN-23's frozen rule that
                         * only payment-backed education entitlements
                         * consume the first-1,000 Copy Trading cohort.
                         */
                        DB::table(
                            'user_entitlements'
                        )->insert([
                            'uuid' =>
                                (string) Str::uuid(),
                            'user_id' =>
                                $user->id,
                            'product_code' =>
                                'EDUCATION_PREMIUM',
                            'status' =>
                                'ACTIVE',
                            'activated_at' =>
                                $user
                                    ->premium_activated_at
                                ?? $user->created_at
                                ?? now(),
                            'payment_id' =>
                                null,
                            'revoked_at' =>
                                null,
                            'revoke_reason' =>
                                null,
                            'created_at' =>
                                now(),
                            'updated_at' =>
                                now(),
                        ]);
                    }
                },
                'id'
            );
    }

    public function down(): void
    {
        /*
         * Intentionally non-destructive.
         * Legacy backfill rows are indistinguishable from valid
         * manual lifetime entitlements once production is running.
         */
    }
};
