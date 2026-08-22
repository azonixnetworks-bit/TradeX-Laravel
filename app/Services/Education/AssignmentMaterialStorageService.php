<?php

namespace App\Services\Education;

use App\Models\Assignment;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class AssignmentMaterialStorageService
{
    private const ROOT_DIRECTORY = 'education/assignments';

    /**
     * Store an admin-provided assignment/question/reference material.
     *
     * Student answers are never handled by this service.
     *
     * @return array{
     *     disk:string,
     *     path:string,
     *     original_name:string,
     *     mime_type:string,
     *     size:int,
     *     sha256:string,
     *     uploaded_at:\Illuminate\Support\Carbon
     * }
     */
    public function store(
        UploadedFile $file,
        string $assignmentUuid
    ): array {
        if (! $file->isValid()) {
            throw ValidationException::withMessages([
                'material_file' =>
                    'The assignment material upload could not be completed.',
            ]);
        }

        if (! Str::isUuid($assignmentUuid)) {
            throw new RuntimeException(
                'Invalid assignment UUID for material storage.'
            );
        }

        $realPath = $file->getRealPath();

        if (
            $realPath === false
            || ! is_file($realPath)
        ) {
            throw ValidationException::withMessages([
                'material_file' =>
                    'The uploaded assignment material could not be read.',
            ]);
        }

        $extension = Str::lower(
            (string) $file->getClientOriginalExtension()
        );

        if ($extension === 'jpeg') {
            $extension = 'jpg';
        }

        $allowed = array_values(
            array_unique(
                array_map(
                    static fn (string $value): string =>
                        $value === 'jpeg'
                            ? 'jpg'
                            : Str::lower($value),
                    Assignment::MATERIAL_ALLOWED_EXTENSIONS
                )
            )
        );

        if (
            $extension === ''
            || ! in_array(
                $extension,
                $allowed,
                true
            )
        ) {
            throw ValidationException::withMessages([
                'material_file' =>
                    'Unsupported assignment material file type.',
            ]);
        }

        $sha256 = hash_file(
            'sha256',
            $realPath
        );

        if ($sha256 === false) {
            throw ValidationException::withMessages([
                'material_file' =>
                    'The assignment material integrity hash could not be generated.',
            ]);
        }

        $originalName = trim(
            $file->getClientOriginalName()
        );

        if ($originalName === '') {
            $originalName =
                'assignment-material.'.$extension;
        }

        $originalName = Str::limit(
            $originalName,
            255,
            ''
        );

        $mimeType = trim(
            (string) (
                $file->getMimeType()
                ?: $file->getClientMimeType()
                ?: 'application/octet-stream'
            )
        );

        $mimeType = Str::limit(
            $mimeType,
            120,
            ''
        );

        $size = $file->getSize();

        if (
            $size === false
            || $size < 0
        ) {
            $size = 0;
        }

        $directory =
            self::ROOT_DIRECTORY
            .'/'
            .$assignmentUuid
            .'/materials';

        $fileName =
            Str::uuid()->toString()
            .'.'
            .$extension;

        $storedPath = $file->storeAs(
            $directory,
            $fileName,
            Assignment::MATERIAL_DISK
        );

        if (
            ! is_string($storedPath)
            || trim($storedPath) === ''
        ) {
            throw ValidationException::withMessages([
                'material_file' =>
                    'TradeX could not store the assignment material in protected storage.',
            ]);
        }

        return [
            'disk' =>
                Assignment::MATERIAL_DISK,

            'path' =>
                str_replace(
                    '\\',
                    '/',
                    $storedPath
                ),

            'original_name' =>
                $originalName,

            'mime_type' =>
                $mimeType,

            'size' =>
                (int) $size,

            'sha256' =>
                $sha256,

            'uploaded_at' =>
                now(),
        ];
    }

    /**
     * Delete only material paths owned by this protected assignment namespace.
     */
    public function delete(
        ?string $disk,
        ?string $path
    ): bool {
        if (
            $disk === null
            || $path === null
            || trim($disk) === ''
            || trim($path) === ''
        ) {
            return true;
        }

        if ($disk !== Assignment::MATERIAL_DISK) {
            return false;
        }

        $normalizedPath = ltrim(
            str_replace(
                '\\',
                '/',
                trim($path)
            ),
            '/'
        );

        if (
            ! str_starts_with(
                $normalizedPath,
                self::ROOT_DIRECTORY.'/'
            )
        ) {
            return false;
        }

        $storage = Storage::disk(
            Assignment::MATERIAL_DISK
        );

        if (! $storage->exists($normalizedPath)) {
            return true;
        }

        return $storage->delete(
            $normalizedPath
        );
    }
}
