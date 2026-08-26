<?php

namespace App\Services\Admin\Security;

use App\Models\AdminUser;
use App\Models\AuditLog;
use App\Services\Auth\TotpService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AdminTotpService
{
    public function __construct(
        private TotpService $totp
    ) {
    }

    public function startSetup(
        AdminUser $admin,
        Request $request
    ): void {
        DB::transaction(
            function () use (
                $admin,
                $request
            ): void {
                $locked =
                    AdminUser::query()
                        ->lockForUpdate()
                        ->findOrFail(
                            $admin->getKey()
                        );

                if (
                    $locked->hasConfirmedTotp()
                ) {
                    abort(
                        409,
                        'Admin TOTP is already enabled.'
                    );
                }

                $locked->forceFill([
                    'totp_secret' =>
                        $this->generateSecret(),

                    'totp_setup_at' =>
                        now(),

                    'totp_confirmed_at' =>
                        null,

                    'totp_last_used_step' =>
                        null,
                ])->save();

                AuditLog::record(
                    actorType:
                        'ADMIN',
                    actorId:
                        $locked->getKey(),
                    action:
                        'ADMIN_TOTP_SETUP_STARTED',
                    module:
                        'AUTH',
                    newValues: [
                        'setup_started' =>
                            true,
                    ],
                    ipAddress:
                        $request->ip(),
                    userAgent:
                        $request->userAgent()
                );
            },
            3
        );
    }

    public function confirmSetup(
        AdminUser $admin,
        string $code,
        Request $request
    ): void {
        DB::transaction(
            function () use (
                $admin,
                $code,
                $request
            ): void {
                $locked =
                    AdminUser::query()
                        ->lockForUpdate()
                        ->findOrFail(
                            $admin->getKey()
                        );

                if (
                    $locked->hasConfirmedTotp()
                ) {
                    abort(
                        409,
                        'Admin TOTP is already enabled.'
                    );
                }

                try {
                    $secret =
                        (string) $locked
                            ->totp_secret;
                } catch (\Throwable) {
                    throw ValidationException::withMessages([
                        'code' =>
                            'The TOTP setup state is unavailable. Start setup again.',
                    ]);
                }

                if (
                    blank($secret)
                    || $locked->totp_setup_at
                    === null
                ) {
                    throw ValidationException::withMessages([
                        'code' =>
                            'Start TOTP setup before confirming a code.',
                    ]);
                }

                $windowMinutes =
                    max(
                        1,
                        (int) config(
                            'tradex_admin_hardening.totp.setup_window_minutes',
                            15
                        )
                    );

                if (
                    $locked
                        ->totp_setup_at
                        ->lt(
                            now()->subMinutes(
                                $windowMinutes
                            )
                        )
                ) {
                    throw ValidationException::withMessages([
                        'code' =>
                            'TOTP setup expired. Start setup again.',
                    ]);
                }

                $step =
                    $this->matchingStep(
                        $secret,
                        $code
                    );

                if ($step === null) {
                    throw ValidationException::withMessages([
                        'code' =>
                            'The TOTP code is invalid or expired.',
                    ]);
                }

                if (
                    $locked
                        ->totp_last_used_step
                    !== null
                    && (int) $locked
                        ->totp_last_used_step
                    >= $step
                ) {
                    throw ValidationException::withMessages([
                        'code' =>
                            'This TOTP code has already been used.',
                    ]);
                }

                $locked->forceFill([
                    'totp_confirmed_at' =>
                        now(),

                    'totp_last_used_step' =>
                        $step,

                    'totp_setup_at' =>
                        null,
                ])->save();

                AuditLog::record(
                    actorType:
                        'ADMIN',
                    actorId:
                        $locked->getKey(),
                    action:
                        'ADMIN_TOTP_ENABLED',
                    module:
                        'AUTH',
                    newValues: [
                        'totp_enabled' =>
                            true,

                        'confirmed_at' =>
                            $locked
                                ->totp_confirmed_at
                                ?->toISOString(),
                    ],
                    ipAddress:
                        $request->ip(),
                    userAgent:
                        $request->userAgent()
                );
            },
            3
        );
    }

    public function consumeLoginCode(
        AdminUser $admin,
        string $code
    ): void {
        DB::transaction(
            function () use (
                $admin,
                $code
            ): void {
                $locked =
                    AdminUser::query()
                        ->lockForUpdate()
                        ->findOrFail(
                            $admin->getKey()
                        );

                if (
                    ! $locked
                        ->hasConfirmedTotp()
                ) {
                    throw ValidationException::withMessages([
                        'code' =>
                            'Admin TOTP setup is required.',
                    ]);
                }

                try {
                    $secret =
                        (string) $locked
                            ->totp_secret;
                } catch (\Throwable) {
                    throw ValidationException::withMessages([
                        'code' =>
                            'The admin TOTP factor is unavailable. Contact the system owner.',
                    ]);
                }

                $step =
                    $this->matchingStep(
                        $secret,
                        $code
                    );

                if ($step === null) {
                    throw ValidationException::withMessages([
                        'code' =>
                            'The TOTP code is invalid or expired.',
                    ]);
                }

                if (
                    $locked
                        ->totp_last_used_step
                    !== null
                    && (int) $locked
                        ->totp_last_used_step
                    >= $step
                ) {
                    throw ValidationException::withMessages([
                        'code' =>
                            'This TOTP code has already been used.',
                    ]);
                }

                $locked->forceFill([
                    'totp_last_used_step' =>
                        $step,
                ])->save();
            },
            3
        );
    }

    public function provisioningData(
        AdminUser $admin
    ): ?array {
        if (
            $admin->hasConfirmedTotp()
            || $admin->totp_setup_at === null
        ) {
            return null;
        }

        try {
            $secret =
                (string) $admin
                    ->totp_secret;
        } catch (\Throwable) {
            return null;
        }

        if (blank($secret)) {
            return null;
        }

        $windowMinutes =
            max(
                1,
                (int) config(
                    'tradex_admin_hardening.totp.setup_window_minutes',
                    15
                )
            );

        if (
            $admin
                ->totp_setup_at
                ->lt(
                    now()->subMinutes(
                        $windowMinutes
                    )
                )
        ) {
            return null;
        }

        $issuer =
            (string) config(
                'tradex_admin_hardening.totp.issuer',
                'TradeX OS Admin'
            );

        $digits =
            (int) config(
                'tradex_auth.totp.digits',
                6
            );

        $period =
            (int) config(
                'tradex_auth.totp.period_seconds',
                30
            );

        $label =
            rawurlencode(
                $issuer
                .':'
                .$admin->email
            );

        $uri =
            'otpauth://totp/'
            .$label
            .'?secret='
            .rawurlencode(
                $secret
            )
            .'&issuer='
            .rawurlencode(
                $issuer
            )
            .'&algorithm=SHA1'
            .'&digits='
            .$digits
            .'&period='
            .$period;

        return [
            'secret' =>
                $secret,

            'otpauth_uri' =>
                $uri,

            'issuer' =>
                $issuer,

            'account_name' =>
                $admin->email,

            'digits' =>
                $digits,

            'period_seconds' =>
                $period,
        ];
    }

    private function matchingStep(
        string $secret,
        string $code
    ): ?int {
        try {
            return $this
                ->totp
                ->matchingStep(
                    $secret,
                    trim($code)
                );
        } catch (\InvalidArgumentException) {
            return null;
        }
    }

    private function generateSecret(): string
    {
        return $this->base32Encode(
            random_bytes(20)
        );
    }

    private function base32Encode(
        string $binary
    ): string {
        $alphabet =
            'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

        $bits = '';

        foreach (
            unpack('C*', $binary)
            as $byte
        ) {
            $bits .= str_pad(
                decbin($byte),
                8,
                '0',
                STR_PAD_LEFT
            );
        }

        $encoded = '';

        foreach (
            str_split($bits, 5)
            as $chunk
        ) {
            if (
                strlen($chunk) < 5
            ) {
                $chunk =
                    str_pad(
                        $chunk,
                        5,
                        '0',
                        STR_PAD_RIGHT
                    );
            }

            $encoded .=
                $alphabet[
                    bindec($chunk)
                ];
        }

        return $encoded;
    }
}
