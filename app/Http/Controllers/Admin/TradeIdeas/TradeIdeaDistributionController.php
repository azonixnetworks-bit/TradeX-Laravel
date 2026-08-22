<?php

namespace App\Http\Controllers\Admin\TradeIdeas;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\TradeIdeas\PreviewTradeIdeaShareCardRequest;
use App\Models\TradeIdea;
use App\Services\TradeIdeas\TradeIdeaShareCardService;
use Illuminate\Http\Response;
use Illuminate\View\View;

class TradeIdeaDistributionController extends Controller
{
    public function preview(
        PreviewTradeIdeaShareCardRequest $request,
        TradeIdea $tradeIdea,
        TradeIdeaShareCardService $shareCardService
    ): View {
        $validated = $request->validated();

        $tradeIdea->load(
            'targets'
        );

        $variant = (string) $validated['variant'];

        $dimensions = $shareCardService->dimensions(
            $variant
        );

        return view(
            'admin.trade_ideas.share_card',
            [
                'tradeIdea' => $tradeIdea,
                'variant' => $variant,
                'referralCode' => $validated['referral_code'] ?? null,
                'affiliateUrl' => $validated['affiliate_url'] ?? null,
                'dimensions' => $dimensions,
                'variants' => $shareCardService->variants(),
            ]
        );
    }

    public function svg(
        PreviewTradeIdeaShareCardRequest $request,
        TradeIdea $tradeIdea,
        TradeIdeaShareCardService $shareCardService
    ): Response {
        $validated = $request->validated();

        $tradeIdea->load(
            'targets'
        );

        $svg = $shareCardService->renderSvg(
            tradeIdea: $tradeIdea,
            referralCode: $validated['referral_code'] ?? null,
            affiliateUrl: $validated['affiliate_url'] ?? null,
            variant: (string) $validated['variant']
        );

        return response(
            $svg,
            200,
            [
                'Content-Type' => 'image/svg+xml; charset=UTF-8',
                'Cache-Control' => 'private, no-store, no-cache, must-revalidate, max-age=0',
                'Pragma' => 'no-cache',
                'Expires' => '0',
                'X-Content-Type-Options' => 'nosniff',
                'Content-Disposition' => 'inline; filename="tradex-trade-idea-'.$tradeIdea->id.'.svg"',
            ]
        );
    }
}
