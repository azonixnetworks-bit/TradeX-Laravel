<?php

namespace App\Http\Controllers\Api\TradeJournal;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\TradeJournal\TradeJournalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TradeJournalScreenshotController extends Controller
{
    public function store(
        Request $request,
        string $uuid,
        string $type,
        TradeJournalService $journal
    ): JsonResponse {
        $user = $this->user($request);
        $type = $this->type($type);
        $entry = $journal->owned($user, $uuid);

        abort_if(in_array($entry->status, ['closed', 'cancelled'], true) && $type === 'before', 422, 'Before screenshot is read-only after completion.');
        abort_if($type === 'after' && $entry->status !== 'closed', 422, 'After screenshot can be uploaded only for a closed trade.');

        $max = (int) config('trade_journal.screenshots.max_kilobytes', 10240);
        $mimes = implode(',', (array) config('trade_journal.screenshots.mimes', ['jpg', 'jpeg', 'png', 'webp']));
        $validated = $request->validate([
            'user_id' => ['prohibited'],
            'image' => ['required', 'file', 'image', 'mimes:'.$mimes, 'max:'.$max],
        ]);

        $file = $validated['image'];
        $extension = strtolower($file->guessExtension() ?: 'jpg');
        $path = 'trade-journals/'.$user->getKey().'/'.$entry->uuid.'/'.$type.'/'.Str::uuid().'.'.$extension;
        $diskName = (string) config('trade_journal.screenshots.disk', 'private');
        $disk = Storage::disk($diskName);

        if (! $disk->put($path, $file->getContent())) {
            abort(500, 'Unable to store screenshot.');
        }

        $pathField = $type.'_screenshot_path';
        $mimeField = $type.'_screenshot_mime';
        $oldPath = $entry->{$pathField};

        $entry->{$pathField} = $path;
        $entry->{$mimeField} = $file->getMimeType();
        $entry->save();

        if (filled($oldPath) && $oldPath !== $path) {
            $disk->delete($oldPath);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'type' => $type,
                'available' => true,
            ],
        ], 201)->header('Cache-Control', 'private, no-store');
    }

    public function show(
        Request $request,
        string $uuid,
        string $type,
        TradeJournalService $journal
    ): StreamedResponse {
        $user = $this->user($request);
        $type = $this->type($type);
        $entry = $journal->owned($user, $uuid);
        $path = $entry->{$type.'_screenshot_path'};
        $mime = $entry->{$type.'_screenshot_mime'} ?: 'application/octet-stream';
        $disk = Storage::disk((string) config('trade_journal.screenshots.disk', 'private'));

        abort_unless(filled($path) && $disk->exists($path), 404);

        return $disk->response(
            $path,
            null,
            [
                'Content-Type' => $mime,
                'Cache-Control' => 'private, no-store, no-cache, max-age=0',
                'Pragma' => 'no-cache',
                'X-Content-Type-Options' => 'nosniff',
                'Content-Disposition' => 'inline',
            ]
        );
    }

    public function destroy(
        Request $request,
        string $uuid,
        string $type,
        TradeJournalService $journal
    ): JsonResponse {
        $user = $this->user($request);
        $type = $this->type($type);
        $entry = $journal->owned($user, $uuid);

        abort_if(in_array($entry->status, ['closed', 'cancelled'], true), 422, 'Completed journal screenshots are read-only.');

        $pathField = $type.'_screenshot_path';
        $mimeField = $type.'_screenshot_mime';
        $path = $entry->{$pathField};

        if (filled($path)) {
            Storage::disk((string) config('trade_journal.screenshots.disk', 'private'))->delete($path);
        }

        $entry->{$pathField} = null;
        $entry->{$mimeField} = null;
        $entry->save();

        return response()->json(['success' => true]);
    }

    private function type(string $type): string
    {
        abort_unless(in_array($type, ['before', 'after'], true), 404);
        return $type;
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401, 'Unauthenticated.');
        return $user;
    }
}
