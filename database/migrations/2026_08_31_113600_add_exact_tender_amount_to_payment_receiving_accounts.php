<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (
            ! Schema::hasColumn(
                'payment_receiving_accounts',
                'tender_amount_decimal'
            )
        ) {
            Schema::table(
                'payment_receiving_accounts',
                function (
                    Blueprint $table
                ): void {
                    $table->string(
                        'tender_amount_decimal',
                        80
                    )
                        ->nullable()
                        ->after(
                            'tender_amount_minor'
                        );
                }
            );
        }

        DB::table(
            'payment_receiving_accounts'
        )
            ->select([
                'id',
                'tender_amount_minor',
                'amount_scale',
                'tender_amount_decimal',
            ])
            ->whereNull(
                'tender_amount_decimal'
            )
            ->whereNotNull(
                'tender_amount_minor'
            )
            ->orderBy(
                'id'
            )
            ->chunkById(
                200,
                function (
                    $rows
                ): void {
                    foreach (
                        $rows
                        as $row
                    ) {
                        $scale =
                            (int) (
                                $row
                                    ->amount_scale
                                ?? 0
                            );

                        if (
                            $scale < 0
                            || $scale > 18
                        ) {
                            continue;
                        }

                        $minor =
                            ltrim(
                                (string) $row
                                    ->tender_amount_minor,
                                '0'
                            );

                        if (
                            $minor === ''
                        ) {
                            $minor = '0';
                        }

                        if (
                            $scale === 0
                        ) {
                            $decimal =
                                $minor;
                        } else {
                            $digits =
                                str_pad(
                                    $minor,
                                    $scale + 1,
                                    '0',
                                    STR_PAD_LEFT
                                );

                            $decimal =
                                substr(
                                    $digits,
                                    0,
                                    -$scale
                                )
                                .'.'
                                .substr(
                                    $digits,
                                    -$scale
                                );
                        }

                        DB::table(
                            'payment_receiving_accounts'
                        )
                            ->where(
                                'id',
                                $row->id
                            )
                            ->update([
                                'tender_amount_decimal' =>
                                    $decimal,
                            ]);
                    }
                }
            );
    }

    public function down(): void
    {
        if (
            Schema::hasColumn(
                'payment_receiving_accounts',
                'tender_amount_decimal'
            )
        ) {
            Schema::table(
                'payment_receiving_accounts',
                function (
                    Blueprint $table
                ): void {
                    $table->dropColumn(
                        'tender_amount_decimal'
                    );
                }
            );
        }
    }
};
