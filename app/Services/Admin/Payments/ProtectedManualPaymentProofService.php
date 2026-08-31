<?php

namespace App\Services\Admin\Payments;

use App\Models\UsdtDeposit;
use DomainException;
use Illuminate\Support\Facades\Storage;

class ProtectedManualPaymentProofService
{
    public function usdtProof(UsdtDeposit $deposit): array
    {
        $deposit->loadMissing('user:id,uuid');

        $disk = trim((string) $deposit->proof_disk);
        $path = str_replace(
            '\\',
            '/',
            trim((string) $deposit->proof_path)
        );

        $configuredDisks = array_keys(
            (array) config('filesystems.disks', [])
        );

        if (
            $disk === ''
            || ! in_array($disk, $configuredDisks, true)
        ) {
            throw new DomainException(
                'Protected USDT proof is unavailable.'
            );
        }

        $expectedPrefix =
            'payments/crypto/'
            .$deposit->user->uuid
            .'/';

        if (
            $path === ''
            || str_starts_with($path, '/')
            || preg_match('/^[A-Za-z]:\//', $path) === 1
            || str_contains($path, '../')
            || str_contains($path, '/..')
            || ! str_starts_with($path, $expectedPrefix)
        ) {
            throw new DomainException(
                'Protected USDT proof ownership check failed.'
            );
        }

        if (! Storage::disk($disk)->exists($path)) {
            throw new DomainException(
                'Protected USDT proof file was not found.'
            );
        }

        $extension = strtolower(
            (string) pathinfo($path, PATHINFO_EXTENSION)
        );

        $extension =
            preg_match('/^[a-z0-9]{1,10}$/', $extension) === 1
                ? '.'.$extension
                : '';

        return [
            'disk' => $disk,
            'path' => $path,
            'name' =>
                'usdt-proof-'
                .$deposit->uuid
                .$extension,
        ];
    }
}
