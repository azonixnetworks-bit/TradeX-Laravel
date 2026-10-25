<?php

namespace App\Http\Controllers\Api\Profile\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Profile\StoreProfilePhotoRequest;
use App\Models\User;
use App\Services\Profile\ProfilePhotoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProfilePhotoController extends Controller
{
    private const ALLOWED_MIME_TYPES = [
        'image/jpeg',
        'image/png',
        'image/webp',
    ];

    public function show(Request $request, ProfilePhotoService $service): StreamedResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401, 'Unauthenticated.');
        $profile = $user->profile()->first();

        abort_unless(
            $profile !== null
            && is_string($profile->profile_photo_path)
            && $service->isManagedPath($user, $profile->profile_photo_path)
            && in_array($profile->profile_photo_mime_type, self::ALLOWED_MIME_TYPES, true)
            && Storage::disk('private')->exists($profile->profile_photo_path),
            404,
            'Profile photo is unavailable.'
        );

        $etag = '"'.$profile->profile_photo_sha256.'"';

        if ($request->header('If-None-Match') === $etag) {
            return response()->stream(static function (): void {}, 304, [
                'ETag' => $etag,
                'Cache-Control' => 'private, max-age=3600, must-revalidate',
            ]);
        }

        $stream = Storage::disk('private')->readStream($profile->profile_photo_path);
        abort_unless(is_resource($stream), 404, 'Profile photo is unavailable.');

        return response()->stream(function () use ($stream): void {
            fpassthru($stream);
            fclose($stream);
        }, 200, [
            'Content-Type' => $profile->profile_photo_mime_type,
            'Content-Length' => (string) $profile->profile_photo_size_bytes,
            'Content-Disposition' => 'inline; filename="profile-photo"',
            'Cache-Control' => 'private, max-age=3600, must-revalidate',
            'ETag' => $etag,
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
        ]);
    }

    public function store(
        StoreProfilePhotoRequest $request,
        ProfilePhotoService $service
    ): JsonResponse {
        $user = $request->user();
        abort_unless($user instanceof User, 401, 'Unauthenticated.');
        $photo = $request->file('photo');
        abort_unless($photo instanceof UploadedFile, 422, 'A profile photo is required.');

        return response()->json([
            'success' => true,
            'data' => [
                'profile_photo' => $service->store($user, $photo, $request),
            ],
        ]);
    }

    public function destroy(Request $request, ProfilePhotoService $service): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401, 'Unauthenticated.');

        return response()->json([
            'success' => true,
            'data' => [
                'profile_photo' => $service->remove($user, $request),
            ],
        ]);
    }
}
