<?php

namespace App\Services\TradeIdeas;

use App\Models\TradeIdea;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

class TradeIdeaShareCardService
{
    public function renderSvg(
        TradeIdea $tradeIdea,
        ?User $user = null
    ): string {
        $tradeIdea->loadMissing(
            'targets'
        );

        $referralCode = $user?->referral_code;

        $affiliateLink =
            $referralCode !== null
            && trim(
                (string) $referralCode
            ) !== ''
                ? $this->affiliateLink(
                    $referralCode
                )
                : null;

        $lines = [
            [
                'label' => 'MARKET',
                'value' => $tradeIdea->symbol
                    .' · '
                    .$tradeIdea->category
                    .' · '
                    .$tradeIdea->timeframe,
            ],
            [
                'label' => 'DIRECTION',
                'value' => $tradeIdea->direction,
            ],
        ];

        if (
            $tradeIdea->entry_price_low !== null
            || $tradeIdea->entry_price_high !== null
        ) {
            $lines[] = [
                'label' => 'ENTRY',
                'value' => $tradeIdea->displayEntryZone(),
            ];
        }

        if (
            $tradeIdea->current_stop_loss !== null
            || $tradeIdea->stop_loss !== null
        ) {
            $lines[] = [
                'label' => 'STOP LOSS',
                'value' => (string) (
                    $tradeIdea->current_stop_loss
                    ?? $tradeIdea->stop_loss
                ),
            ];
        }

        if (
            $tradeIdea->targets->isNotEmpty()
        ) {
            $lines[] = [
                'label' => 'TARGETS',
                'value' => $tradeIdea->targets
                    ->take(5)
                    ->map(
                        fn ($target): string =>
                            $target->label
                            .' '
                            .$target->price
                    )
                    ->implode(
                        ' · '
                    ),
            ];
        }

        if ($referralCode !== null) {
            $lines[] = [
                'label' => 'REFERRAL',
                'value' => $referralCode,
            ];
        }

        if ($affiliateLink !== null) {
            $lines[] = [
                'label' => 'JOIN',
                'value' => $affiliateLink,
            ];
        }

        $svgLines = [];
        $startY = 360;
        $step = 92;

        foreach (
            $lines
            as $index => $line
        ) {
            $y =
                $startY
                + ($index * $step);

            $svgLines[] =
                '<text x="80" y="'
                .$y
                .'" font-size="22" fill="#8f8f8f">'
                .$this->xml(
                    $line['label']
                )
                .'</text>';

            $svgLines[] =
                '<text x="80" y="'
                .($y + 38)
                .'" font-size="30" font-weight="700" fill="#f2f2f2">'
                .$this->xml(
                    mb_strimwidth(
                        (string) $line['value'],
                        0,
                        58,
                        '…'
                    )
                )
                .'</text>';
        }

        return '<?xml version="1.0" encoding="UTF-8"?>'
            .'<svg xmlns="http://www.w3.org/2000/svg" width="1080" height="1350" viewBox="0 0 1080 1350">'
            .'<rect width="1080" height="1350" fill="#090909"/>'
            .'<rect x="46" y="46" width="988" height="1258" rx="34" fill="#111111" stroke="#c5a052" stroke-width="2"/>'
            .'<text x="80" y="125" font-size="34" font-weight="700" fill="#d8b260">TRADEX</text>'
            .'<text x="80" y="175" font-size="24" fill="#8f8f8f">TRADE IDEA</text>'
            .'<text x="80" y="260" font-size="48" font-weight="700" fill="#ffffff">'
            .$this->xml(
                mb_strimwidth(
                    $tradeIdea->title,
                    0,
                    34,
                    '…'
                )
            )
            .'</text>'
            .implode(
                '',
                $svgLines
            )
            .'<text x="80" y="1260" font-size="21" fill="#757575">Educational trade idea · Manage risk responsibly</text>'
            .'</svg>';
    }

    public function cache(
        TradeIdea $tradeIdea,
        ?User $user = null
    ): string {
        $svg = $this->renderSvg(
            $tradeIdea,
            $user
        );

        $audienceIdentity =
            $user !== null
                ? (
                    $user->uuid
                    ?: $user->id
                    ?: 'unknown'
                )
                : null;

        $audienceKey =
            $user !== null
                ? 'user-'.$audienceIdentity
                : 'global';

        $hash = hash(
            'sha256',
            $svg
        );

        $path =
            'trade-ideas/'
            .$tradeIdea->uuid
            .'/share-cards/'
            .$audienceKey
            .'-'
            .$hash
            .'.svg';

        Storage::disk(
            config(
                'tradex_trade_ideas.share.card_disk',
                'private'
            )
        )->put(
            $path,
            $svg
        );

        return $path;
    }

    public function affiliateLink(
        string $referralCode
    ): string {
        $base = trim(
            (string) config(
                'tradex_trade_ideas.share.affiliate_base_url',
                ''
            )
        );

        if ($base === '') {
            $base = rtrim(
                (string) config(
                    'app.url'
                ),
                '/'
            );
        }

        $separator =
            str_contains(
                $base,
                '?'
            )
                ? '&'
                : '?';

        return $base
            .$separator
            .'ref='
            .rawurlencode(
                $referralCode
            );
    }

    private function xml(
        string $value
    ): string {
        return htmlspecialchars(
            $value,
            ENT_QUOTES
            | ENT_XML1,
            'UTF-8'
        );
    }
}
