<?php

namespace App\Http\Controllers\Admin\Education;

use App\Http\Controllers\Controller;
use App\Models\AdminUser;
use App\Models\Course;
use App\Models\CourseModule;
use App\Models\Lesson;
use App\Models\LessonVideo;
use App\Services\Education\ProtectedHlsMediaService;
use Illuminate\Http\Response;

class VideoPlaybackController extends Controller
{
    public function __construct(
        private readonly ProtectedHlsMediaService $protectedHls
    ) {
    }

    public function preview(
        Course $course,
        CourseModule $module,
        Lesson $lesson,
        LessonVideo $video
    ): Response {
        $admin = $this->authenticatedAdmin();

        $this->requirePermission(
            $admin,
            'education.view'
        );

        $this->requireVideoOwnership(
            $course,
            $module,
            $lesson,
            $video
        );

        $this->requireAdminPreviewReady(
            $video
        );

        abort_unless(
            $this->protectedHls->masterExists(
                $video
            ),
            404,
            'The protected HLS master manifest is missing from storage.'
        );

        $manifestUrl = route(
            'admin.education.courses.modules.lessons.videos.media',
            [
                'course' => $course,
                'module' => $module,
                'lesson' => $lesson,
                'video' => $video,
                'path' => $this->protectedHls->masterRelativePath(
                    $video
                ),
            ]
        );

        return response()
            ->view(
                'admin.education.videos.preview',
                compact(
                    'course',
                    'module',
                    'lesson',
                    'video',
                    'manifestUrl'
                )
            )
            ->header(
                'Cache-Control',
                'private, no-store, no-cache, must-revalidate, max-age=0'
            )
            ->header(
                'Pragma',
                'no-cache'
            )
            ->header(
                'Expires',
                '0'
            )
            ->header(
                'X-Frame-Options',
                'DENY'
            )
            ->header(
                'Referrer-Policy',
                'no-referrer'
            )
            ->header(
                'X-Content-Type-Options',
                'nosniff'
            );
    }

    public function media(
        Course $course,
        CourseModule $module,
        Lesson $lesson,
        LessonVideo $video,
        string $path
    ): Response {
        $admin = $this->authenticatedAdmin();

        $this->requirePermission(
            $admin,
            'education.view'
        );

        $this->requireVideoOwnership(
            $course,
            $module,
            $lesson,
            $video
        );

        $this->requireAdminPreviewReady(
            $video
        );

        return $this->protectedHls->responseFor(
            $video,
            $path,
            function (string $relativePath) use (
                $course,
                $module,
                $lesson,
                $video
            ): string {
                return route(
                    'admin.education.courses.modules.lessons.videos.media',
                    [
                        'course' => $course,
                        'module' => $module,
                        'lesson' => $lesson,
                        'video' => $video,
                        'path' => $relativePath,
                    ]
                );
            }
        );
    }

    private function requireAdminPreviewReady(
        LessonVideo $video
    ): void {
        abort_unless(
            $video->processing_status
                === LessonVideo::PROCESSING_READY,
            422,
            'Only a successfully processed video can be previewed.'
        );

        abort_unless(
            $video->hasHlsStream(),
            422,
            'This video does not have a protected HLS stream to preview.'
        );
    }

    private function requireVideoOwnership(
        Course $course,
        CourseModule $module,
        Lesson $lesson,
        LessonVideo $video
    ): void {
        abort_unless(
            (int) $module->course_id
                === (int) $course->id,
            404
        );

        abort_unless(
            (int) $lesson->course_module_id
                === (int) $module->id,
            404
        );

        abort_unless(
            (int) $video->lesson_id
                === (int) $lesson->id,
            404
        );
    }

    private function authenticatedAdmin(): AdminUser
    {
        $admin = auth(
            'admin'
        )->user();

        abort_unless(
            $admin instanceof AdminUser,
            401
        );

        return $admin;
    }

    private function requirePermission(
        AdminUser $admin,
        string $permission
    ): void {
        abort_unless(
            $admin->hasPermission(
                $permission
            ),
            403
        );
    }
}
