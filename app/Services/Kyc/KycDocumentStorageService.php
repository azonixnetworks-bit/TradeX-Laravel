<?php

namespace App\Services\Kyc;

use App\Exceptions\KycApiException;
use App\Models\KycDocument;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class KycDocumentStorageService
{
    public function __construct(
        private readonly KycCountryPolicy
            $policy
    ) {
    }

    public function store(
        User $user,
        string $submissionUuid,
        string $documentKind,
        UploadedFile $file
    ): array {
        $kind = strtoupper(
            trim($documentKind)
        );

        $this->assertKnownKind(
            $kind
        );

        $mimeType = strtolower(
            trim(
                (string) (
                    $file->getMimeType()
                    ?: $file->getClientMimeType()
                )
            )
        );

        $allowedMimeTypes =
            $kind === KycDocument::KIND_SELFIE
                ? $this->policy
                    ->selfieMimeTypes()
                : $this->policy
                    ->identityMimeTypes();

        if (
            ! in_array(
                $mimeType,
                $allowedMimeTypes,
                true
            )
        ) {
            throw new KycApiException(
                422,
                'KYC_DOCUMENT_MIME_INVALID',
                'One of the KYC documents has an unsupported file type.'
            );
        }

        $maxBytes =
            $this->policy
                ->maxUploadKilobytes()
            * 1024;

        $sizeBytes = (int) $file->getSize();

        if (
            $sizeBytes <= 0
            || $sizeBytes > $maxBytes
        ) {
            throw new KycApiException(
                422,
                'KYC_DOCUMENT_SIZE_INVALID',
                'One of the KYC documents exceeds the permitted upload size.'
            );
        }

        $realPath = $file->getRealPath();

        if (
            ! is_string($realPath)
            || ! is_file($realPath)
        ) {
            throw new KycApiException(
                422,
                'KYC_DOCUMENT_INVALID',
                'One of the KYC documents could not be read.'
            );
        }

        $sha256 = hash_file(
            'sha256',
            $realPath
        );

        if (! is_string($sha256)) {
            throw new KycApiException(
                422,
                'KYC_DOCUMENT_HASH_FAILED',
                'One of the KYC documents could not be validated.'
            );
        }

        $disk = trim(
            (string) config(
                'tradex_kyc.storage.disk',
                'private'
            )
        );

        if (
            $disk === ''
            || strtolower($disk) === 'public'
        ) {
            throw new KycApiException(
                500,
                'KYC_STORAGE_MISCONFIGURED',
                'KYC private storage is not configured correctly.'
            );
        }

        if (
            ! is_array(
                config(
                    'filesystems.disks.'.$disk
                )
            )
        ) {
            throw new KycApiException(
                500,
                'KYC_STORAGE_DISK_NOT_FOUND',
                'KYC private storage is not configured correctly.'
            );
        }

        $directory = trim(
            (string) config(
                'tradex_kyc.storage.directory',
                'kyc'
            ),
            "/\\ \t\n\r\0\x0B"
        );

        if ($directory === '') {
            $directory = 'kyc';
        }

        $extension =
            $this->extensionForMimeType(
                $mimeType
            );

        $userSegment = filled(
            $user->uuid
        )
            ? (string) $user->uuid
            : (string) $user->getKey();

        $relativeDirectory =
            $directory
            .'/'.$userSegment
            .'/'.$submissionUuid;

        $fileName =
            strtolower($kind)
            .'-'.Str::uuid()
            .'.'.$extension;

        $stored = Storage::disk(
            $disk
        )->putFileAs(
            $relativeDirectory,
            $file,
            $fileName
        );

        if (! $stored) {
            throw new KycApiException(
                503,
                'KYC_DOCUMENT_STORAGE_FAILED',
                'The KYC document could not be stored securely.'
            );
        }

        return [
            'document_kind' => $kind,
            'storage_disk' => $disk,
            'storage_path' => $stored,
            'original_name' =>
                $this->safeOriginalName(
                    $file
                ),
            'mime_type' => $mimeType,
            'size_bytes' => $sizeBytes,
            'sha256' => $sha256,
        ];
    }

    public function cleanup(
        array $storedDocuments
    ): void {
        foreach (
            $storedDocuments
            as $document
        ) {
            $disk = (string) (
                $document['storage_disk']
                ?? ''
            );

            $path = (string) (
                $document['storage_path']
                ?? ''
            );

            if (
                $disk === ''
                || $path === ''
            ) {
                continue;
            }

            Storage::disk(
                $disk
            )->delete(
                $path
            );
        }
    }

    private function assertKnownKind(
        string $kind
    ): void {
        if (
            ! in_array(
                $kind,
                [
                    KycDocument::KIND_IDENTITY_FRONT,
                    KycDocument::KIND_IDENTITY_BACK,
                    KycDocument::KIND_SELFIE,
                ],
                true
            )
        ) {
            throw new KycApiException(
                422,
                'KYC_DOCUMENT_KIND_INVALID',
                'The KYC document kind is invalid.'
            );
        }
    }

    private function extensionForMimeType(
        string $mimeType
    ): string {
        return match ($mimeType) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'application/pdf' => 'pdf',
            default => 'bin',
        };
    }

    private function safeOriginalName(
        UploadedFile $file
    ): ?string {
        $name = trim(
            (string) $file
                ->getClientOriginalName()
        );

        if ($name === '') {
            return null;
        }

        return mb_substr(
            basename($name),
            0,
            255
        );
    }
}
