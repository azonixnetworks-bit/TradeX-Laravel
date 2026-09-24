<?php

namespace App\Http\Controllers;

use App\Models\ChannelVideo;
use App\Services\OurChannel\ChannelMediaSource;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ChannelMediaDeliveryController extends Controller
{
    public function __invoke(
        ChannelVideo $channelVideo,
        ChannelMediaSource $source
    ): StreamedResponse {
        abort_unless(
            $channelVideo->hasSourceMedia()
                && $source->exists($channelVideo),
            404
        );

        $stream = $source->open($channelVideo);
        $size = $source->size($channelVideo);
        $mime = $source->mime($channelVideo);
        $filename = $source->filename($channelVideo);

        return response()->stream(
            function () use ($stream): void {
                try {
                    fpassthru($stream);
                } finally {
                    if (is_resource($stream)) {
                        fclose($stream);
                    }
                }
            },
            200,
            [
                'Content-Type' => $mime,
                'Content-Length' => (string) $size,
                'Content-Disposition' => 'inline; filename="'.$filename.'"',
                'Cache-Control' => 'private, max-age=300',
                'X-Content-Type-Options' => 'nosniff',
            ]
        );
    }
}
