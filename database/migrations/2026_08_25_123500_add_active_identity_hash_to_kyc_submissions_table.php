<?php

use App\Models\KycSubmission;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table(
            'kyc_submissions',
            function (
                Blueprint $table
            ): void {
                $table->char(
                    'active_identity_hash',
                    64
                )
                    ->nullable()
                    ->after(
                        'identity_number_hash'
                    );
            }
        );

        /*
        | Backfill only states that currently own an active identity claim.
        | A duplicate here is intentionally surfaced when the unique index is
        | created instead of silently choosing one conflicting account.
        */
        DB::table(
            'kyc_submissions'
        )
            ->whereIn(
                'status',
                [
                    KycSubmission::STATUS_PENDING_REVIEW,
                    KycSubmission::STATUS_VERIFIED,
                ]
            )
            ->whereNotNull(
                'identity_number_hash'
            )
            ->update([
                'active_identity_hash' =>
                    DB::raw(
                        'identity_number_hash'
                    ),
            ]);

        Schema::table(
            'kyc_submissions',
            function (
                Blueprint $table
            ): void {
                $table->unique(
                    'active_identity_hash',
                    'kyc_submissions_active_identity_unique'
                );
            }
        );
    }

    public function down(): void
    {
        Schema::table(
            'kyc_submissions',
            function (
                Blueprint $table
            ): void {
                $table->dropUnique(
                    'kyc_submissions_active_identity_unique'
                );

                $table->dropColumn(
                    'active_identity_hash'
                );
            }
        );
    }
};
