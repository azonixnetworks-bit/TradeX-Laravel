<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TradeIdea;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PublicTradeResultController extends Controller
{
    public function index(): JsonResponse
    {
        $results = TradeIdea::query()
            ->with('targets')
            ->where('status', TradeIdea::STATUS_CLOSED)
            ->where('show_on_website', true)
            ->whereIn('close_outcome', [
                TradeIdea::OUTCOME_WIN,
                TradeIdea::OUTCOME_LOSS,
                TradeIdea::OUTCOME_BREAKEVEN,
            ])
            ->whereNotNull('chart_image_path')
            ->whereNotNull('after_chart_image_path')
            ->orderByDesc('closed_at')
            ->limit(24)
            ->get()
            ->map(fn (TradeIdea $idea): array => [
                'uuid' => $idea->uuid,
                'title' => $idea->title,
                'summary' => $idea->summary,
                'analysis' => $idea->analysis,
                'category' => $idea->category,
                'symbol' => $idea->symbol,
                'instrument_name' => $idea->instrument_name,
                'direction' => $idea->direction,
                'timeframe' => $idea->timeframe,
                'entry_price_low' => $idea->entry_price_low,
                'entry_price_high' => $idea->entry_price_high,
                'entry_price_actual' => $idea->entry_price_actual,
                'stop_loss' => $idea->stop_loss,
                'targets' => $idea->targets->map(fn ($target): array => [
                    'label' => $target->label,
                    'price' => $target->price,
                    'hit_at' => $target->hit_at?->toIso8601String(),
                ])->values(),
                'outcome' => $idea->close_outcome,
                'close_price' => $idea->close_price,
                'result_note' => $idea->close_note,
                'published_at' => $idea->published_at?->toIso8601String(),
                'closed_at' => $idea->closed_at?->toIso8601String(),
                'before_image_url' => route('api.public.trade-results.image', [$idea, 'before']),
                'after_image_url' => route('api.public.trade-results.image', [$idea, 'after']),
            ]);

        return response()->json(['data' => $results]);
    }

    public function image(Request $request, TradeIdea $tradeIdea, string $stage): StreamedResponse
    {
        abort_unless($tradeIdea->isPublicWebsiteResult(), 404);
        abort_unless(in_array($stage, ['before', 'after'], true), 404);

        $disk = $stage === 'before'
            ? $tradeIdea->chart_image_disk
            : $tradeIdea->after_chart_image_disk;
        $path = $stage === 'before'
            ? $tradeIdea->chart_image_path
            : $tradeIdea->after_chart_image_path;
        $mime = $stage === 'before'
            ? $tradeIdea->chart_image_mime_type
            : $tradeIdea->after_chart_image_mime_type;

        abort_unless($disk === 'private' && $path && Storage::disk($disk)->exists($path), 404);

        return Storage::disk($disk)->response($path, null, [
            'Content-Type' => $mime ?: 'application/octet-stream',
            'Cache-Control' => 'public, max-age=3600',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
