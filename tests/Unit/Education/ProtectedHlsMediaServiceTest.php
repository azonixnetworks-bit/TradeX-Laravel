<?php

namespace Tests\Unit\Education;

use App\Models\LessonVideo;
use App\Services\Education\ProtectedHlsMediaService;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\TestCase;

class ProtectedHlsMediaServiceTest extends TestCase
{
    public function test_media_service_rejects_path_traversal_outside_hls_root(): void
    {
        Storage::fake(
            'private'
        );

        $video = new LessonVideo([
            'uuid' => '11111111-1111-4111-8111-111111111111',
            'storage_disk' => 'private',
            'hls_master_path' => 'education/videos/11111111-1111-4111-8111-111111111111/hls/master.m3u8',
        ]);

        $service = app(
            ProtectedHlsMediaService::class
        );

        $this->expectException(
            NotFoundHttpException::class
        );

        $service->responseFor(
            $video,
            '../source/source.mp4',
            fn (string $path): string => '/protected/'.$path
        );
    }

    public function test_media_service_rejects_double_encoded_path_traversal(): void
    {
        Storage::fake(
            'private'
        );

        $video = new LessonVideo([
            'uuid' => '22222222-2222-4222-8222-222222222222',
            'storage_disk' => 'private',
            'hls_master_path' => 'education/videos/22222222-2222-4222-8222-222222222222/hls/master.m3u8',
        ]);

        $service = app(
            ProtectedHlsMediaService::class
        );

        $this->expectException(
            NotFoundHttpException::class
        );

        $service->responseFor(
            $video,
            '%252e%252e/source/source.mp4',
            fn (string $path): string => '/protected/'.$path
        );
    }

    public function test_manifest_rejects_external_uri_instead_of_proxying_it(): void
    {
        Storage::fake(
            'private'
        );

        $video = new LessonVideo([
            'uuid' => '33333333-3333-4333-8333-333333333333',
            'storage_disk' => 'private',
            'hls_master_path' => 'education/videos/33333333-3333-4333-8333-333333333333/hls/master.m3u8',
        ]);

        Storage::disk(
            'private'
        )->put(
            $video->hls_master_path,
            "#EXTM3U\nhttps://evil.example.test/video.m3u8\n"
        );

        $service = app(
            ProtectedHlsMediaService::class
        );

        $this->expectException(
            NotFoundHttpException::class
        );

        $service->responseFor(
            $video,
            'master.m3u8',
            fn (string $path): string => '/protected/'.$path
        );
    }
}
