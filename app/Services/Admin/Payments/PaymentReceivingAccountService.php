<?php

namespace App\Services\Admin\Payments;

use App\Exceptions\AuthApiException;
use App\Models\AdminUser;
use App\Models\AuditLog;
use App\Models\PaymentReceivingAccount;
use App\Services\Payments\ScaledDecimal;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class PaymentReceivingAccountService
{
    public function __construct(
        private readonly ScaledDecimal $decimal
    ) {
    }

    public function forAdmin(): Collection
    {
        return PaymentReceivingAccount::query()
            ->orderBy('type')
            ->orderBy('country_code')
            ->orderBy('display_order')
            ->orderBy('id')
            ->get();
    }

    public function create(
        AdminUser $admin,
        array $data,
        ?UploadedFile $qrImage,
        Request $request
    ): PaymentReceivingAccount {
        $uuid = (string) Str::uuid();
        $qr = $this->storeQr(
            $uuid,
            $qrImage
        );

        try {
            return DB::transaction(
                function () use (
                    $admin,
                    $data,
                    $request,
                    $uuid,
                    $qr
                ): PaymentReceivingAccount {
                    $account =
                        PaymentReceivingAccount::query()
                            ->create(
                                array_merge(
                                    $this
                                        ->normalizedAttributes(
                                            $data
                                        ),
                                    [
                                        'uuid' =>
                                            $uuid,
                                        'qr_disk' =>
                                            $qr['disk'],
                                        'qr_path' =>
                                            $qr['path'],
                                        'created_by_admin_id' =>
                                            $admin
                                                ->getKey(),
                                        'updated_by_admin_id' =>
                                            $admin
                                                ->getKey(),
                                    ]
                                )
                            );

                    $this->audit(
                        $admin,
                        $account,
                        'PAYMENT_RECEIVING_ACCOUNT_CREATED',
                        $request
                    );

                    return $account;
                },
                3
            );
        } catch (
            Throwable $exception
        ) {
            $this->deleteQr(
                $qr['disk'],
                $qr['path']
            );

            throw $exception;
        }
    }

    public function update(
        PaymentReceivingAccount $account,
        AdminUser $admin,
        array $data,
        ?UploadedFile $qrImage,
        Request $request
    ): PaymentReceivingAccount {
        $oldDisk =
            $account->qr_disk;

        $oldPath =
            $account->qr_path;

        $newQr =
            $this->storeQr(
                $account->uuid,
                $qrImage
            );

        $removeQr =
            (bool) (
                $data['remove_qr']
                ?? false
            );

        try {
            $updated =
                DB::transaction(
                    function () use (
                        $account,
                        $admin,
                        $data,
                        $request,
                        $newQr,
                        $removeQr
                    ): PaymentReceivingAccount {
                        $locked =
                            PaymentReceivingAccount::query()
                                ->whereKey(
                                    $account
                                        ->getKey()
                                )
                                ->lockForUpdate()
                                ->firstOrFail();

                        $attributes =
                            $this
                                ->normalizedAttributes(
                                    $data
                                );

                        if (
                            $newQr['path']
                            !== null
                        ) {
                            $attributes[
                                'qr_disk'
                            ] =
                                $newQr[
                                    'disk'
                                ];

                            $attributes[
                                'qr_path'
                            ] =
                                $newQr[
                                    'path'
                                ];
                        } elseif (
                            $removeQr
                        ) {
                            $attributes[
                                'qr_disk'
                            ] = null;

                            $attributes[
                                'qr_path'
                            ] = null;
                        }

                        $locked
                            ->forceFill(
                                array_merge(
                                    $attributes,
                                    [
                                        'updated_by_admin_id' =>
                                            $admin
                                                ->getKey(),
                                    ]
                                )
                            )
                            ->save();

                        $this->audit(
                            $admin,
                            $locked,
                            'PAYMENT_RECEIVING_ACCOUNT_UPDATED',
                            $request
                        );

                        return $locked
                            ->fresh();
                    },
                    3
                );
        } catch (
            Throwable $exception
        ) {
            $this->deleteQr(
                $newQr['disk'],
                $newQr['path']
            );

            throw $exception;
        }

        if (
            $newQr['path']
                !== null
            || $removeQr
        ) {
            $this->deleteQr(
                $oldDisk,
                $oldPath
            );
        }

        return $updated;
    }

    public function qrFile(
        PaymentReceivingAccount $account
    ): array {
        $disk = trim(
            (string) $account
                ->qr_disk
        );

        $path = str_replace(
            '\\',
            '/',
            trim(
                (string) $account
                    ->qr_path
            )
        );

        $configuredDisks =
            array_keys(
                (array) config(
                    'filesystems.disks',
                    []
                )
            );

        if (
            $disk === ''
            || ! in_array(
                $disk,
                $configuredDisks,
                true
            )
        ) {
            throw new RuntimeException(
                'Protected payment-account QR image is unavailable.'
            );
        }

        $expectedPrefix =
            'payments/receiving-accounts/'
            .$account->uuid
            .'/';

        if (
            $path === ''
            || str_starts_with(
                $path,
                '/'
            )
            || preg_match(
                '/^[A-Za-z]:\//',
                $path
            ) === 1
            || str_contains(
                $path,
                '../'
            )
            || str_contains(
                $path,
                '/..'
            )
            || ! str_starts_with(
                $path,
                $expectedPrefix
            )
        ) {
            throw new RuntimeException(
                'Protected payment-account QR image path is invalid.'
            );
        }

        if (
            ! Storage::disk(
                $disk
            )->exists(
                $path
            )
        ) {
            throw new RuntimeException(
                'Protected payment-account QR image was not found.'
            );
        }

        $extension =
            strtolower(
                (string) pathinfo(
                    $path,
                    PATHINFO_EXTENSION
                )
            );

        $extension =
            preg_match(
                '/^[a-z0-9]{1,10}$/',
                $extension
            ) === 1
                ? '.'.$extension
                : '';

        return [
            'disk' =>
                $disk,
            'path' =>
                $path,
            'name' =>
                'payment-account-qr-'
                .$account->uuid
                .$extension,
        ];
    }

    private function normalizedAttributes(
        array $data
    ): array {
        $type =
            strtoupper(
                trim(
                    (string) $data[
                        'type'
                    ]
                )
            );

        $scale =
            (int) $data[
                'amount_scale'
            ];

        $isBank =
            $type
            === PaymentReceivingAccount::TYPE_BANK;

        $coinSymbol =
            ! $isBank
                ? strtoupper(
                    trim(
                        (string) $data[
                            'coin_symbol'
                        ]
                    )
                )
                : null;

        $network =
            ! $isBank
                ? strtoupper(
                    trim(
                        (string) $data[
                            'network'
                        ]
                    )
                )
                : null;

        $normalizedAmount =
            $this->decimal
                ->normalize(
                    (string) $data[
                        'tender_amount'
                    ],
                    $scale
                );

        if (
            preg_match(
                '/[1-9]/',
                str_replace(
                    '.',
                    '',
                    $normalizedAmount
                )
            ) !== 1
        ) {
            throw new AuthApiException(
                422,
                'PAYMENT_AMOUNT_INVALID',
                'The configured payment amount must be greater than zero.'
            );
        }

        $isUsdtTrc20 =
            ! $isBank
            && $coinSymbol === 'USDT'
            && $network === 'TRC20';

        /*
         * Bank and the dedicated USDT/TRC20 compatibility flow still use the
         * minor-unit settlement ledger. Generic crypto supports exact
         * precision up to 18 decimals without forcing the value into BIGINT.
         */
        if (
            ($isBank || $isUsdtTrc20)
            && $scale > 8
        ) {
            throw new AuthApiException(
                422,
                'PAYMENT_MINOR_SCALE_UNSUPPORTED',
                'Bank and USDT TRC20 receiving accounts support up to 8 decimal places.'
            );
        }

        $minor =
            $scale <= 8
                ? $this->decimal
                    ->toMinor(
                        $normalizedAmount,
                        $scale
                    )
                : null;

        return [
            'type' =>
                $type,
            'display_name' =>
                trim(
                    (string) $data[
                        'display_name'
                    ]
                ),
            'country_code' =>
                strtoupper(
                    trim(
                        (string) $data[
                            'country_code'
                        ]
                    )
                ),
            'currency' =>
                strtoupper(
                    trim(
                        (string) $data[
                            'currency'
                        ]
                    )
                ),
            'tender_amount_minor' =>
                $minor,
            'tender_amount_decimal' =>
                $normalizedAmount,
            'amount_scale' =>
                $scale,

            'bank_name' =>
                $isBank
                    ? trim(
                        (string) $data[
                            'bank_name'
                        ]
                    )
                    : null,

            'bank_branch' =>
                $isBank
                    ? trim(
                        (string) $data[
                            'bank_branch'
                        ]
                    )
                    : null,

            'account_holder' =>
                $isBank
                    ? trim(
                        (string) $data[
                            'account_holder'
                        ]
                    )
                    : null,

            'account_number' =>
                $isBank
                    ? trim(
                        (string) $data[
                            'account_number'
                        ]
                    )
                    : null,

            'coin_name' =>
                ! $isBank
                    ? trim(
                        (string) $data[
                            'coin_name'
                        ]
                    )
                    : null,

            'coin_symbol' =>
                $coinSymbol,

            'network' =>
                $network,

            'wallet_address' =>
                ! $isBank
                    ? trim(
                        (string) $data[
                            'wallet_address'
                        ]
                    )
                    : null,

            'instructions' =>
                filled(
                    $data[
                        'instructions'
                    ]
                    ?? null
                )
                    ? trim(
                        (string) $data[
                            'instructions'
                        ]
                    )
                    : null,

            'is_active' =>
                (bool) $data[
                    'is_active'
                ],

            'display_order' =>
                (int) $data[
                    'display_order'
                ],
        ];
    }
    private function storeQr(
        string $uuid,
        ?UploadedFile $file
    ): array {
        if (
            ! $file
            instanceof UploadedFile
        ) {
            return [
                'disk' => null,
                'path' => null,
            ];
        }

        $disk = trim(
            (string) config(
                'tradex_payments.receiving_accounts.qr_disk',
                'local'
            )
        );

        if (
            $disk === ''
        ) {
            $disk =
                'local';
        }

        $extension =
            strtolower(
                (string) (
                    $file
                        ->guessExtension()
                    ?: $file
                        ->getClientOriginalExtension()
                    ?: 'bin'
                )
            );

        $path =
            'payments/receiving-accounts/'
            .$uuid
            .'/'
            .Str::uuid()
            .'.'
            .$extension;

        $stored =
            Storage::disk(
                $disk
            )->putFileAs(
                dirname(
                    $path
                ),
                $file,
                basename(
                    $path
                )
            );

        if (
            ! $stored
        ) {
            throw new RuntimeException(
                'The payment-account QR image could not be stored securely.'
            );
        }

        return [
            'disk' =>
                $disk,
            'path' =>
                $path,
        ];
    }

    private function deleteQr(
        ?string $disk,
        ?string $path
    ): void {
        if (
            blank(
                $disk
            )
            || blank(
                $path
            )
        ) {
            return;
        }

        try {
            Storage::disk(
                (string) $disk
            )->delete(
                (string) $path
            );
        } catch (
            Throwable
        ) {
        }
    }

    private function audit(
        AdminUser $admin,
        PaymentReceivingAccount $account,
        string $action,
        Request $request
    ): void {
        $metadata = [
            'account_uuid' =>
                $account->uuid,
            'type' =>
                $account->type,
            'display_name' =>
                $account
                    ->display_name,
            'country_code' =>
                $account
                    ->country_code,
            'currency' =>
                $account->currency,
            'tender_amount_minor' =>
                $account
                    ->tender_amount_minor
                    !== null
                        ? (int) $account
                            ->tender_amount_minor
                        : null,
            'tender_amount_decimal' =>
                (string) $account
                    ->tender_amount_decimal,
            'amount_scale' =>
                (int) $account
                    ->amount_scale,
            'is_active' =>
                (bool) $account
                    ->is_active,
            'display_order' =>
                (int) $account
                    ->display_order,
            'qr_configured' =>
                filled(
                    $account
                        ->qr_path
                ),
        ];

        if (
            $account->isBank()
        ) {
            $metadata[
                'bank_name'
            ] =
                $account
                    ->bank_name;

            $metadata[
                'bank_branch'
            ] =
                $account
                    ->bank_branch;
        } else {
            $metadata[
                'coin_symbol'
            ] =
                $account
                    ->coin_symbol;

            $metadata[
                'network'
            ] =
                $account
                    ->network;
        }

        AuditLog::record(
            actorType:
                'ADMIN',
            actorId:
                $admin
                    ->getKey(),
            action:
                $action,
            module:
                'PAYMENTS',
            targetType:
                'PAYMENT_RECEIVING_ACCOUNT',
            targetId:
                $account
                    ->getKey(),
            newValues:
                $metadata,
            ipAddress:
                $request
                    ->ip(),
            userAgent:
                $request
                    ->userAgent()
        );
    }
}
