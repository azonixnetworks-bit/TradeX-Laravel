<?php

namespace Tests\Concerns;

use App\Models\AdminUser;
use App\Models\Assignment;
use App\Models\Lesson;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

trait CreatesAssignmentContent
{
    protected function createAssignment(
        Lesson $lesson,
        array $overrides = [],
        bool $withMaterial = true
    ): Assignment {
        $admin =
            AdminUser::query()
                ->create([
                    'uuid' =>
                        (string) Str::uuid(),

                    'name' =>
                        'API-05 Test Admin',

                    'email' =>
                        'api05-'
                        .Str::lower(
                            Str::random(10)
                        )
                        .'@example.test',

                    'email_verified_at' =>
                        now(),

                    'password' =>
                        'TradeX-API05-Test-Password-123!',

                    'is_active' =>
                        true,

                    'failed_login_attempts' =>
                        0,
                ]);

        $uuid =
            (string) Str::uuid();

        $material = [
            'material_disk' =>
                null,

            'material_path' =>
                null,

            'material_original_name' =>
                null,

            'material_mime_type' =>
                null,

            'material_size_bytes' =>
                null,

            'material_sha256' =>
                null,

            'material_uploaded_at' =>
                null,
        ];

        if ($withMaterial) {
            $contents =
                'protected-api-05-assignment-material';

            $path =
                'education/assignments/'
                .$uuid
                .'/materials/'
                .Str::uuid()
                .'.pdf';

            Storage::disk(
                Assignment::MATERIAL_DISK
            )->put(
                $path,
                $contents
            );

            $material = [
                'material_disk' =>
                    Assignment::MATERIAL_DISK,

                'material_path' =>
                    $path,

                'material_original_name' =>
                    'assignment-question.pdf',

                'material_mime_type' =>
                    'application/pdf',

                'material_size_bytes' =>
                    strlen(
                        $contents
                    ),

                'material_sha256' =>
                    hash(
                        'sha256',
                        $contents
                    ),

                'material_uploaded_at' =>
                    now()->subMinute(),
            ];
        }

        return Assignment::query()
            ->create(
                array_merge(
                    [
                        'uuid' =>
                            $uuid,

                        'lesson_id' =>
                            $lesson->getKey(),

                        'title' =>
                            'API-05 Assignment',

                        'heading' =>
                            'Complete the market structure exercise',

                        'description' =>
                            'Answer the assignment and send the completed work through WhatsApp.',

                        'reference_image_path' =>
                            null,

                        'submission_method' =>
                            Assignment::SUBMISSION_WHATSAPP,

                        'submission_instructions' =>
                            'Send a clear PDF or image of your completed work.',

                        'is_required' =>
                            true,

                        'status' =>
                            Assignment::STATUS_PUBLISHED,

                        'published_at' =>
                            now()->subMinute(),

                        'sort_order' =>
                            1,

                        'created_by_admin_id' =>
                            $admin->getKey(),

                        'updated_by_admin_id' =>
                            $admin->getKey(),
                    ],
                    $material,
                    $overrides
                )
            );
    }
}
