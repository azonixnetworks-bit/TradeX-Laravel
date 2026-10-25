<?php

namespace App\Services\Profile;

use App\Models\AuditLog;
use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class ProfilePhotoService
{
    private const MIME_EXTENSIONS = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    public function store(User $user, UploadedFile $photo, Request $request): array
    {
        [$mimeType, $extension, $size, $sha256] = $this->inspect($photo);
        $directory = 'profile-photos/'.$user->uuid;
        $path = Storage::disk('private')->putFileAs(
            $directory,
            $photo,
            Str::uuid().'.'.$extension
        );

        if (! is_string($path) || $path === '') {
            throw new RuntimeException('Unable to store the profile photo securely.');
        }

        $oldPath = null;

        try {
            $profile = DB::transaction(function () use (
                $user,
                $path,
                $mimeType,
                $size,
                $sha256,
                $request,
                &$oldPath
            ): UserProfile {
                User::query()->whereKey($user->getKey())->lockForUpdate()->firstOrFail();

                $profile = UserProfile::query()->firstOrCreate([
                    'user_id' => $user->getKey(),
                ]);
                $profile = UserProfile::query()->whereKey($profile->getKey())->lockForUpdate()->firstOrFail();
                $oldPath = $profile->profile_photo_path;
                $oldVersion = (int) $profile->profile_photo_version;

                $profile->forceFill([
                    'profile_photo_path' => $path,
                    'profile_photo_mime_type' => $mimeType,
                    'profile_photo_size_bytes' => $size,
                    'profile_photo_sha256' => $sha256,
                    'profile_photo_version' => $oldVersion + 1,
                    'profile_photo_updated_at' => now(),
                ])->save();

                AuditLog::record(
                    'USER',
                    $user->getKey(),
                    'PROFILE_PHOTO_UPDATED',
                    'PROFILE',
                    'USER_PROFILE',
                    $profile->getKey(),
                    ['profile_photo_version' => $oldVersion],
                    [
                        'profile_photo_version' => $oldVersion + 1,
                        'mime_type' => $mimeType,
                        'size_bytes' => $size,
                        'sha256' => $sha256,
                    ],
                    $request->ip(),
                    $request->userAgent()
                );

                return $profile->fresh();
            }, 3);
        } catch (Throwable $exception) {
            Storage::disk('private')->delete($path);
            throw $exception;
        }

        if (is_string($oldPath) && $oldPath !== $path) {
            $this->deleteManagedPath($user, $oldPath);
        }

        return $this->metadata($profile);
    }

    public function remove(User $user, Request $request): array
    {
        $oldPath = null;

        $profile = DB::transaction(function () use ($user, $request, &$oldPath): UserProfile {
            User::query()->whereKey($user->getKey())->lockForUpdate()->firstOrFail();

            $profile = UserProfile::query()->firstOrCreate([
                'user_id' => $user->getKey(),
            ]);
            $profile = UserProfile::query()->whereKey($profile->getKey())->lockForUpdate()->firstOrFail();
            $oldPath = $profile->profile_photo_path;
            $oldVersion = (int) $profile->profile_photo_version;

            $profile->forceFill([
                'profile_photo_path' => null,
                'profile_photo_mime_type' => null,
                'profile_photo_size_bytes' => null,
                'profile_photo_sha256' => null,
                'profile_photo_version' => $oldVersion + 1,
                'profile_photo_updated_at' => now(),
            ])->save();

            AuditLog::record(
                'USER',
                $user->getKey(),
                'PROFILE_PHOTO_REMOVED',
                'PROFILE',
                'USER_PROFILE',
                $profile->getKey(),
                ['profile_photo_version' => $oldVersion],
                ['profile_photo_version' => $oldVersion + 1],
                $request->ip(),
                $request->userAgent()
            );

            return $profile->fresh();
        }, 3);

        if (is_string($oldPath)) {
            $this->deleteManagedPath($user, $oldPath);
        }

        return $this->metadata($profile);
    }

    public function metadata(?UserProfile $profile): array
    {
        $available = $profile !== null
            && is_string($profile->profile_photo_path)
            && $profile->profile_photo_path !== '';

        return [
            'available' => $available,
            'version' => (int) ($profile?->profile_photo_version ?? 0),
            'updated_at' => $profile?->profile_photo_updated_at?->toISOString(),
            'endpoint' => $available ? '/api/v1/profile/settings/photo' : null,
        ];
    }

    public function isManagedPath(User $user, string $path): bool
    {
        $normalized = ltrim(str_replace('\\', '/', trim($path)), '/');

        return str_starts_with($normalized, 'profile-photos/'.$user->uuid.'/')
            && ! str_contains($normalized, '../');
    }

    private function inspect(UploadedFile $photo): array
    {
        if (! $photo->isValid()) {
            throw new RuntimeException('The profile photo upload was not completed safely.');
        }

        $realPath = $photo->getRealPath();

        if (! is_string($realPath) || $realPath === '') {
            throw new RuntimeException('The uploaded profile photo is unavailable.');
        }

        $mimeType = (new \finfo(FILEINFO_MIME_TYPE))->file($realPath);
        $dimensions = @getimagesize($realPath);

        if (
            ! is_string($mimeType)
            || ! isset(self::MIME_EXTENSIONS[$mimeType])
            || ! is_array($dimensions)
            || ($dimensions['mime'] ?? null) !== $mimeType
        ) {
            throw new RuntimeException('The uploaded file is not a supported image.');
        }

        $size = filesize($realPath);
        $sha256 = hash_file('sha256', $realPath);

        if (! is_int($size) || $size < 1 || ! is_string($sha256)) {
            throw new RuntimeException('The profile photo could not be verified.');
        }

        return [$mimeType, self::MIME_EXTENSIONS[$mimeType], $size, $sha256];
    }

    private function deleteManagedPath(User $user, string $path): void
    {
        if ($this->isManagedPath($user, $path)) {
            Storage::disk('private')->delete($path);
        }
    }
}
