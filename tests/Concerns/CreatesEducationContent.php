<?php

namespace Tests\Concerns;

use App\Models\Course;
use App\Models\CourseModule;
use App\Models\Lesson;
use App\Models\LessonVideo;
use Illuminate\Support\Facades\Storage;

trait CreatesEducationContent
{
    protected function createEducationTree(
        string $accessLevel = Course::ACCESS_FREE,
        bool $preview = false
    ): array {
        $course =
            Course::query()
                ->create([
                    'code' =>
                        'COURSE-'
                        .random_int(
                            1000,
                            9999
                        ),

                    'slug' =>
                        'course-'
                        .random_int(
                            1000,
                            9999
                        ),

                    'title' =>
                        'TradeX Course',

                    'short_description' =>
                        'Course summary',

                    'description' =>
                        'Course description',

                    'language_code' =>
                        'en',

                    'access_level' =>
                        $accessLevel,

                    'status' =>
                        Course::STATUS_PUBLISHED,

                    'published_at' =>
                        now()->subMinute(),

                    'sort_order' =>
                        1,

                    'is_featured' =>
                        true,
                ]);

        $module =
            CourseModule::query()
                ->create([
                    'course_id' =>
                        $course->getKey(),

                    'code' =>
                        'MODULE-1',

                    'slug' =>
                        'module-1',

                    'title' =>
                        'Module 1',

                    'short_description' =>
                        'Module summary',

                    'description' =>
                        'Module description',

                    'status' =>
                        CourseModule::STATUS_PUBLISHED,

                    'published_at' =>
                        now()->subMinute(),

                    'sort_order' =>
                        1,
                ]);

        $lesson =
            Lesson::query()
                ->create([
                    'course_module_id' =>
                        $module->getKey(),

                    'code' =>
                        'LESSON-1',

                    'slug' =>
                        'lesson-1',

                    'title' =>
                        'Lesson 1',

                    'heading' =>
                        'Lesson heading',

                    'short_description' =>
                        'Lesson summary',

                    'description' =>
                        'Protected lesson description',

                    'is_preview' =>
                        $preview,

                    'status' =>
                        Lesson::STATUS_PUBLISHED,

                    'published_at' =>
                        now()->subMinute(),

                    'sort_order' =>
                        1,
                ]);

        Storage::fake(
            'private'
        );

        $masterPath =
            'education/'
            .$lesson->uuid
            .'/master.m3u8';

        Storage::disk(
            'private'
        )->put(
            $masterPath,
            "#EXTM3U\n"
            ."#EXT-X-VERSION:3\n"
            ."#EXTINF:6.0,\n"
            ."segment0.ts\n"
            ."#EXT-X-ENDLIST\n"
        );

        Storage::disk(
            'private'
        )->put(
            'education/'
            .$lesson->uuid
            .'/segment0.ts',
            'fake-segment'
        );

        $sourcePath =
            'education/'
            .$lesson->uuid
            .'/source.mp4';

        Storage::disk(
            'private'
        )->put(
            $sourcePath,
            'fake-source-video'
        );

        $video =
            LessonVideo::query()
                ->create([
                    'lesson_id' =>
                        $lesson->getKey(),

                    'title' =>
                        'Lesson Video',

                    'short_description' =>
                        'Video summary',

                    'storage_disk' =>
                        'private',

                    'source_path' =>
                        $sourcePath,

                    'hls_master_path' =>
                        $masterPath,

                    'duration_seconds' =>
                        120,

                    'file_size_bytes' =>
                        17,

                    'mime_type' =>
                        'video/mp4',

                    'processing_status' =>
                        LessonVideo::PROCESSING_READY,

                    'processed_at' =>
                        now()->subMinute(),

                    'status' =>
                        LessonVideo::STATUS_PUBLISHED,

                    'published_at' =>
                        now()->subMinute(),

                    'is_downloadable' =>
                        false,

                    'sort_order' =>
                        1,
                ]);

        return [
            $course,
            $module,
            $lesson,
            $video,
        ];
    }
}