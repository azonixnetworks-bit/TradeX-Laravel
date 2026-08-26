<?php

namespace App\Services\Education;

use App\Exceptions\AuthApiException;
use App\Models\Assignment;
use App\Models\AuditLog;
use App\Models\Lesson;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AssignmentApiService
{
    public function __construct(
        private readonly EducationAccessService $access,
        private readonly EducationContentLocalizationService $localization
    ) {
    }

    public function forLesson(
        User $user,
        Lesson $lesson
    ): array {
        $lesson->loadMissing(
            'module.course'
        );

        $this->access
            ->assertAssignmentLessonAccess(
                $user,
                $lesson
            );

        $assignments =
            Assignment::query()
                ->published()
                ->forLesson(
                    $lesson
                )
                ->ordered()
                ->get();

        return [
            'lesson_uuid' =>
                $lesson->uuid,

            'items' =>
                $assignments
                    ->map(
                        fn (Assignment $assignment): array =>
                            $this->summary(
                                $user,
                                $assignment
                            )
                    )
                    ->values()
                    ->all(),

            'count' =>
                $assignments->count(),

            'submission_policy' =>
                $this->submissionPolicy(),
        ];
    }

    public function show(
        User $user,
        Assignment $assignment
    ): array {
        $assignment->loadMissing(
            'lesson.module.course'
        );

        $this->access
            ->assertAssignmentAccess(
                $user,
                $assignment
            );

        $localized =
            $this->localization
                ->assignment(
                    $user,
                    $assignment
                );

        $fields =
            $localized['fields'];

        return [
            'assignment' =>
                array_merge(
                    $this->summary(
                        $user,
                        $assignment,
                        $localized
                    ),
                    [
                        'heading' =>
                            $fields['heading'],

                        'description' =>
                            $fields['description'],

                        'reference_image' => [
                            'available' =>
                                $assignment
                                    ->hasReferenceImage(),

                            'direct_path_exposed' =>
                                false,
                        ],

                        'submission_instructions' =>
                            $fields[
                                'submission_instructions'
                            ],

                        'lesson_uuid' =>
                            $assignment
                                ->lesson
                                ->uuid,
                    ]
                ),

            'submission_policy' =>
                $this->submissionPolicy(),
        ];
    }

    public function prepareWhatsAppAction(
        User $user,
        Assignment $assignment,
        Request $request
    ): array {
        $assignment->loadMissing(
            'lesson.module.course'
        );

        $this->access
            ->assertAssignmentAccess(
                $user,
                $assignment
            );

        if (! $assignment->usesWhatsAppSubmission()) {
            throw new AuthApiException(
                409,
                'ASSIGNMENT_WHATSAPP_NOT_ENABLED',
                'WhatsApp submission is not enabled for this assignment.'
            );
        }

        $recipient =
            preg_replace(
                '/\D+/',
                '',
                (string) config(
                    'tradex_education.assignments.whatsapp_recipient',
                    ''
                )
            );

        $recipient =
            is_string($recipient)
                ? $recipient
                : '';

        $localized =
            $this->localization
                ->assignment(
                    $user,
                    $assignment
                );

        $message =
            $this->whatsAppMessage(
                $user,
                $assignment,
                $localized['fields']
            );

        $baseUrl =
            $recipient !== ''
                ? 'https://wa.me/'.$recipient
                : 'https://wa.me/';

        $deepLink =
            $baseUrl
            .'?text='
            .rawurlencode(
                $message
            );

        AuditLog::record(
            actorType: 'USER',
            actorId: $user->getKey(),
            action: 'ASSIGNMENT_WHATSAPP_ACTION_PREPARED',
            module: 'EDUCATION',
            targetType: 'ASSIGNMENT',
            targetId: $assignment->getKey(),
            newValues: [
                'assignment_uuid' =>
                    $assignment->uuid,

                'lesson_uuid' =>
                    $assignment
                        ->lesson
                        ->uuid,

                'submission_method' =>
                    Assignment::SUBMISSION_WHATSAPP,

                'recipient_configured' =>
                    $recipient !== '',

                'external_submission_only' =>
                    true,

                'delivery_verified_by_tradex' =>
                    false,
            ],
            ipAddress: $request->ip(),
            userAgent: $request->userAgent()
        );

        return [
            'assignment_uuid' =>
                $assignment->uuid,

            'submission_method' =>
                Assignment::SUBMISSION_WHATSAPP,

            'recipient_configured' =>
                $recipient !== '',

            'whatsapp_url' =>
                $deepLink,

            'prefilled_message' =>
                $message,

            'localization' =>
                $localized[
                    'localization'
                ],

            'metadata' => [
                'student_upload_to_tradex' =>
                    false,

                'external_submission_only' =>
                    true,

                'delivery_verified_by_tradex' =>
                    false,

                'submission_status_persisted' =>
                    false,

                'audit_event_recorded' =>
                    true,
            ],
        ];
    }

    public function downloadMaterial(
        User $user,
        Assignment $assignment,
        Request $request
    ): StreamedResponse {
        $assignment->loadMissing(
            'lesson.module.course'
        );

        $this->access
            ->assertAssignmentAccess(
                $user,
                $assignment
            );

        if (! $assignment->hasMaterial()) {
            throw new AuthApiException(
                404,
                'ASSIGNMENT_MATERIAL_NOT_AVAILABLE',
                'This assignment does not have protected material.'
            );
        }

        $disk =
            trim(
                (string) $assignment
                    ->material_disk
            );

        $path =
            ltrim(
                str_replace(
                    '\\',
                    '/',
                    trim(
                        (string) $assignment
                            ->material_path
                    )
                ),
                '/'
            );

        $expectedPrefix =
            'education/assignments/'
            .$assignment->uuid
            .'/materials/';

        if (
            $disk !== Assignment::MATERIAL_DISK
            || ! str_starts_with(
                $path,
                $expectedPrefix
            )
        ) {
            throw new AuthApiException(
                403,
                'ASSIGNMENT_MATERIAL_STORAGE_INVALID',
                'Protected assignment material storage is invalid.'
            );
        }

        $storage =
            Storage::disk(
                Assignment::MATERIAL_DISK
            );

        if (! $storage->exists($path)) {
            throw new AuthApiException(
                404,
                'ASSIGNMENT_MATERIAL_FILE_MISSING',
                'The protected assignment material is not available.'
            );
        }

        $downloadName =
            $this->downloadName(
                $assignment
            );

        AuditLog::record(
            actorType: 'USER',
            actorId: $user->getKey(),
            action: 'ASSIGNMENT_MATERIAL_DOWNLOAD_REQUESTED',
            module: 'EDUCATION',
            targetType: 'ASSIGNMENT',
            targetId: $assignment->getKey(),
            newValues: [
                'assignment_uuid' =>
                    $assignment->uuid,

                'lesson_uuid' =>
                    $assignment
                        ->lesson
                        ->uuid,

                'material_name' =>
                    $downloadName,

                'material_size_bytes' =>
                    $assignment
                        ->material_size_bytes,
            ],
            ipAddress: $request->ip(),
            userAgent: $request->userAgent()
        );

        return $storage->download(
            $path,
            $downloadName,
            [
                'Content-Type' =>
                    filled(
                        $assignment
                            ->material_mime_type
                    )
                        ? (string)
                            $assignment
                                ->material_mime_type
                        : 'application/octet-stream',

                'Cache-Control' =>
                    'private, no-store, no-cache, must-revalidate, max-age=0',

                'Pragma' =>
                    'no-cache',

                'Expires' =>
                    '0',

                'X-Content-Type-Options' =>
                    'nosniff',

                'X-TradeX-Content-Protection' =>
                    'assignment-material',
            ]
        );
    }

    private function summary(
        User $user,
        Assignment $assignment,
        ?array $localized = null
    ): array {
        $localized ??=
            $this->localization
                ->assignment(
                    $user,
                    $assignment
                );

        return [
            'uuid' =>
                $assignment->uuid,

            'title' =>
                $localized[
                    'fields'
                ][
                    'title'
                ],

            'is_required' =>
                $assignment->isRequired(),

            'submission_method' =>
                $assignment->submission_method,

            'has_external_submission' =>
                $assignment->hasExternalSubmission(),

            'published_at' =>
                $assignment
                    ->published_at
                    ?->toIso8601String(),

            'sort_order' =>
                $assignment->sort_order,

            'material' => [
                'available' =>
                    $assignment->hasMaterial(),

                'display_name' =>
                    $assignment
                        ->materialDisplayName(),

                'mime_type' =>
                    $assignment
                        ->material_mime_type,

                'size_bytes' =>
                    $assignment
                        ->material_size_bytes,

                'download_url' =>
                    $assignment->hasMaterial()
                        ? route(
                            'api.v1.education.assignments.material',
                            [
                                'assignment' =>
                                    $assignment->uuid,
                            ]
                        )
                        : null,
            ],

            'whatsapp' => [
                'enabled' =>
                    $assignment
                        ->usesWhatsAppSubmission(),

                'prepare_action_url' =>
                    $assignment
                        ->usesWhatsAppSubmission()
                        ? route(
                            'api.v1.education.assignments.whatsapp-action',
                            [
                                'assignment' =>
                                    $assignment->uuid,
                            ]
                        )
                        : null,
            ],

            'localization' =>
                $localized[
                    'localization'
                ],
        ];
    }

    private function whatsAppMessage(
        User $user,
        Assignment $assignment,
        array $fields
    ): string {
        $lines = [
            'TradeX Assignment Submission',
            '',
            'Assignment: '
                .$fields['title'],
            'Assignment ID: '
                .$assignment->uuid,
            'Student: '
                .$user->name,
            'Student Reference: '
                .$user->referral_code,
            '',
            'Please attach/send the completed assignment in WhatsApp.',
            'TradeX app does not upload or store the student answer file.',
        ];

        $instructions =
            trim(
                (string) (
                    $fields[
                        'submission_instructions'
                    ]
                    ?? ''
                )
            );

        if ($instructions !== '') {
            $lines[] = '';
            $lines[] = 'Instructions: '.$instructions;
        }

        return implode(
            "\n",
            $lines
        );
    }

    private function downloadName(
        Assignment $assignment
    ): string {
        $name =
            trim(
                (string) $assignment
                    ->materialDisplayName()
            );

        if ($name === '') {
            $name =
                'assignment-material';
        }

        $name =
            str_replace(
                [
                    '/',
                    '\\',
                    "\r",
                    "\n",
                ],
                '-',
                $name
            );

        return Str::limit(
            $name,
            180,
            ''
        );
    }

    private function submissionPolicy(): array
    {
        return [
            'student_upload_to_tradex' =>
                false,

            'external_submission_supported' =>
                true,

            'supported_external_method' =>
                Assignment::SUBMISSION_WHATSAPP,

            'whatsapp_delivery_verified_by_tradex' =>
                false,

            'submission_status_persisted' =>
                false,

            'audit_scope' =>
                'whatsapp_action_prepared_and_material_download_requested',
        ];
    }
}
