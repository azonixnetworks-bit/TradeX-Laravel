<?php

namespace App\Services\TradeIdeas;

use App\Models\TradeIdea;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Throwable;

class TradeIdeaShareCardService
{
    public const VARIANT_SQUARE = 'square';
    public const VARIANT_PORTRAIT = 'portrait';
    public const VARIANT_STORY = 'story';

    private const VARIANTS = [
        self::VARIANT_SQUARE => [
            'width' => 1080,
            'height' => 1080,
            'chart_height' => 350,
            'summary_lines' => 1,
        ],
        self::VARIANT_PORTRAIT => [
            'width' => 1080,
            'height' => 1350,
            'chart_height' => 555,
            'summary_lines' => 2,
        ],
        self::VARIANT_STORY => [
            'width' => 1080,
            'height' => 1920,
            'chart_height' => 860,
            'summary_lines' => 3,
        ],
    ];

    public function variants(): array
    {
        return array_keys(self::VARIANTS);
    }

    public function dimensions(string $variant): array
    {
        $variant = $this->normalizeVariant($variant);

        return [
            'width' => self::VARIANTS[$variant]['width'],
            'height' => self::VARIANTS[$variant]['height'],
        ];
    }

    public function renderSvg(
        TradeIdea $tradeIdea,
        User|string|null $referralCode = null,
        ?string $affiliateUrl = null,
        string $variant = self::VARIANT_PORTRAIT
    ): string {
        $variant = $this->normalizeVariant($variant);
        $layout = self::VARIANTS[$variant];

        $width = $layout['width'];
        $height = $layout['height'];
        $margin = 58;
        $contentWidth = $width - ($margin * 2);

        $tradeIdea->loadMissing('targets');

        $accent = $tradeIdea->direction === TradeIdea::DIRECTION_BUY
            ? '#2fd093'
            : '#ff6b75';

        $accentSoft = $tradeIdea->direction === TradeIdea::DIRECTION_BUY
            ? '#143c31'
            : '#47252a';

        $titleLines = $this->wrapText(
            $tradeIdea->title,
            34,
            2
        );

        $summaryLines = $this->wrapText(
            $tradeIdea->summary ?: 'Professional market idea shared through TradeX OS.',
            66,
            (int) $layout['summary_lines']
        );

        $chartTop = $variant === self::VARIANT_STORY
            ? 330
            : 300;

        $chartHeight = $layout['chart_height'];
        $chartBottom = $chartTop + $chartHeight;
        $metricsTop = $chartBottom + 34;

        $footerHeight = $variant === self::VARIANT_STORY
            ? 210
            : 185;

        $footerTop = $height - $footerHeight;
        $metricsBottom = $footerTop - 24;

        $chartImage = $this->chartImageDataUri($tradeIdea);

        $entry = $this->priceText(
            $tradeIdea->displayEntryZone()
        );

        $stop = $this->priceText(
            $tradeIdea->current_stop_loss
                ?? $tradeIdea->stop_loss
        );

        $targets = $tradeIdea
            ->targets
            ->take(5)
            ->values();

        $published = $tradeIdea->published_at
            ?->format('d M Y · H:i')
            ?? 'DRAFT PREVIEW';
        if ($referralCode instanceof User) {
            $user = $referralCode;

            $referralCode = $this->cleanSingleLine(
                $user->referral_code,
                40
            );

            if ($affiliateUrl === null) {
                $affiliateUrl = $this->configuredAffiliateUrl(
                    $referralCode
                );
            }
        }

        $referralCode = $this->cleanSingleLine(
            is_string($referralCode)
                ? $referralCode
                : null,
            40
        );

        $affiliateUrl = $this->cleanSingleLine(
            $affiliateUrl,
            500
        );

        $affiliateDisplay = $this->affiliateDisplay(
            $affiliateUrl
        );

        $svg = [];

        $svg[] = '<?xml version="1.0" encoding="UTF-8"?>';

        $svg[] = '<svg xmlns="http://www.w3.org/2000/svg" '
            .'xmlns:xlink="http://www.w3.org/1999/xlink" '
            .'width="'.$width.'" '
            .'height="'.$height.'" '
            .'viewBox="0 0 '.$width.' '.$height.'" '
            .'role="img" '
            .'aria-label="TradeX Trade Idea share card">';

        if ($affiliateUrl !== null) {
            $svg[] = '<metadata id="tradex-affiliate-url">'
                .$this->xml($affiliateUrl)
                .'</metadata>';
        }

        $svg[] = <<<'SVG'
<defs>
    <linearGradient id="bg" x1="0" y1="0" x2="1" y2="1">
        <stop offset="0%" stop-color="#090b0e"/>
        <stop offset="58%" stop-color="#11151a"/>
        <stop offset="100%" stop-color="#08090b"/>
    </linearGradient>
    <linearGradient id="gold" x1="0" y1="0" x2="1" y2="0">
        <stop offset="0%" stop-color="#b88b2e"/>
        <stop offset="50%" stop-color="#f0d083"/>
        <stop offset="100%" stop-color="#a8791f"/>
    </linearGradient>
    <filter id="softShadow" x="-20%" y="-20%" width="140%" height="140%">
        <feDropShadow dx="0" dy="18" stdDeviation="22" flood-color="#000000" flood-opacity=".40"/>
    </filter>
    <pattern id="grid" width="42" height="42" patternUnits="userSpaceOnUse">
        <path d="M 42 0 L 0 0 0 42" fill="none" stroke="#ffffff" stroke-opacity=".028" stroke-width="1"/>
    </pattern>
</defs>
SVG;

        $svg[] = '<rect width="'.$width.'" height="'.$height.'" fill="url(#bg)"/>';
        $svg[] = '<rect width="'.$width.'" height="'.$height.'" fill="url(#grid)"/>';
        $svg[] = '<circle cx="'.($width - 70).'" cy="70" r="240" fill="'.$accent.'" opacity=".045"/>';
        $svg[] = '<circle cx="40" cy="'.($height - 30).'" r="260" fill="#d8ad55" opacity=".035"/>';

        $svg[] = $this->text('TRADEX', $margin, 92, 40, '#f4f1e8', 800, 'start', '2.4');
        $svg[] = $this->text('OS', $margin + 167, 92, 40, '#d9b55f', 800);

        $svg[] = '<rect x="'.($width - 292).'" y="54" width="234" height="54" rx="27" '
            .'fill="#ffffff" fill-opacity=".055" stroke="#ffffff" stroke-opacity=".09"/>';

        $svg[] = $this->text('TRADE IDEA', $width - 175, 89, 21, '#e9e5dc', 800, 'middle', '2.2');

        $svg[] = $this->text(
            $this->xml($tradeIdea->symbol),
            $margin,
            170,
            58,
            '#ffffff',
            850
        );

        $svg[] = '<rect x="'.$margin.'" y="192" width="148" height="48" rx="24" '
            .'fill="'.$accentSoft.'" stroke="'.$accent.'" stroke-opacity=".38"/>';

        $svg[] = $this->text(
            $tradeIdea->direction,
            $margin + 74,
            224,
            22,
            $accent,
            850,
            'middle',
            '1.5'
        );

        $svg[] = '<rect x="'.($margin + 162).'" y="192" width="118" height="48" rx="24" '
            .'fill="#ffffff" fill-opacity=".045" stroke="#ffffff" stroke-opacity=".08"/>';

        $svg[] = $this->text(
            $this->xml($tradeIdea->timeframe),
            $margin + 221,
            224,
            20,
            '#d7d7d7',
            750,
            'middle'
        );

        $svg[] = $this->text(
            $this->xml($tradeIdea->category),
            $width - $margin,
            206,
            19,
            '#a9adb3',
            700,
            'end',
            '1.8'
        );

        $svg[] = $this->text(
            $this->xml($published),
            $width - $margin,
            235,
            16,
            '#777d84',
            500,
            'end'
        );

        $titleY = $variant === self::VARIANT_STORY
            ? 278
            : 270;

        foreach ($titleLines as $index => $line) {
            $svg[] = $this->text(
                $this->xml($line),
                $margin,
                $titleY + ($index * 39),
                27,
                '#dcdfe3',
                650
            );
        }

        $svg[] = '<rect x="'.$margin.'" y="'.$chartTop.'" '
            .'width="'.$contentWidth.'" height="'.$chartHeight.'" rx="28" '
            .'fill="#050607" stroke="#ffffff" stroke-opacity=".075" '
            .'filter="url(#softShadow)"/>';

        $clipId = 'chartClip';

        $svg[] = '<defs><clipPath id="'.$clipId.'">'
            .'<rect x="'.$margin.'" y="'.$chartTop.'" '
            .'width="'.$contentWidth.'" height="'.$chartHeight.'" rx="28"/>'
            .'</clipPath></defs>';

        if ($chartImage !== null) {
            $svg[] = '<image x="'.$margin.'" y="'.$chartTop.'" '
                .'width="'.$contentWidth.'" height="'.$chartHeight.'" '
                .'href="'.$chartImage.'" '
                .'preserveAspectRatio="xMidYMid meet" '
                .'clip-path="url(#'.$clipId.')"/>';

            $svg[] = '<rect x="'.$margin.'" y="'.$chartTop.'" '
                .'width="'.$contentWidth.'" height="'.$chartHeight.'" rx="28" '
                .'fill="none" stroke="#ffffff" stroke-opacity=".08"/>';
        } else {
            $svg[] = '<rect x="'.$margin.'" y="'.$chartTop.'" '
                .'width="'.$contentWidth.'" height="'.$chartHeight.'" rx="28" '
                .'fill="url(#grid)"/>';

            $svg[] = $this->text(
                'NO CHART IMAGE',
                $width / 2,
                $chartTop + ($chartHeight / 2) - 6,
                24,
                '#777d84',
                750,
                'middle',
                '2.4'
            );

            $svg[] = $this->text(
                'Trade structure remains available below',
                $width / 2,
                $chartTop + ($chartHeight / 2) + 34,
                16,
                '#555b62',
                500,
                'middle'
            );
        }

        $columnGap = 16;
        $half = ($contentWidth - $columnGap) / 2;

        $cardHeight = $variant === self::VARIANT_SQUARE
            ? 92
            : 104;

        $svg[] = $this->metricCard(
            $margin,
            $metricsTop,
            $half,
            $cardHeight,
            'ENTRY',
            $entry,
            '#ffffff'
        );

        $svg[] = $this->metricCard(
            $margin + $half + $columnGap,
            $metricsTop,
            $half,
            $cardHeight,
            'STOP LOSS',
            $stop,
            '#ff8a92'
        );

        $targetsTop = $metricsTop + $cardHeight + 16;

        if ($targets->isNotEmpty()) {
            $targetGap = 12;
            $targetWidth = ($contentWidth - ($targetGap * 2)) / 3;

            foreach ($targets as $index => $target) {
                $row = intdiv($index, 3);
                $column = $index % 3;

                $x = $margin
                    + ($column * ($targetWidth + $targetGap));

                $y = $targetsTop
                    + ($row * 78);

                $svg[] = $this->metricCard(
                    $x,
                    $y,
                    $targetWidth,
                    66,
                    $target->label ?: 'TP'.($index + 1),
                    $this->priceText($target->price),
                    $target->isHit()
                        ? '#2fd093'
                        : '#e1c679',
                    true
                );
            }
        } else {
            $svg[] = $this->text(
                'No optional take-profit targets supplied',
                $margin,
                $targetsTop + 34,
                17,
                '#626970',
                500
            );
        }

        $summaryTop = $targetsTop
            + (
                $targets->count() > 3
                    ? 175
                    : 94
            );

        if ($summaryTop < ($metricsBottom - 48)) {
            $svg[] = $this->text(
                'MARKET CONTEXT',
                $margin,
                $summaryTop,
                14,
                '#8e949b',
                800,
                'start',
                '2.0'
            );

            foreach ($summaryLines as $index => $line) {
                $svg[] = $this->text(
                    $this->xml($line),
                    $margin,
                    $summaryTop + 34 + ($index * 27),
                    18,
                    '#c1c5c9',
                    520
                );
            }
        }

        $svg[] = '<rect x="0" y="'.$footerTop.'" width="'.$width.'" height="'.$footerHeight.'" '
            .'fill="#050607" fill-opacity=".94"/>';

        $svg[] = '<rect x="'.$margin.'" y="'.$footerTop.'" width="'.$contentWidth.'" height="1" '
            .'fill="url(#gold)" opacity=".72"/>';

        $footerLabelY = $footerTop + 50;

        $svg[] = $this->text(
            'SHARED VIA TRADEX OS',
            $margin,
            $footerLabelY,
            14,
            '#8e949b',
            800,
            'start',
            '2.0'
        );

        if ($referralCode !== null) {
            $svg[] = $this->text(
                'REFERRAL · '.$this->xml($referralCode),
                $margin,
                $footerLabelY + 39,
                24,
                '#f0d083',
                800
            );
        } else {
            $svg[] = $this->text(
                'Build Professional Traders',
                $margin,
                $footerLabelY + 39,
                24,
                '#f0d083',
                750
            );
        }

        if ($affiliateDisplay !== null) {
            $svg[] = $this->text(
                $this->xml($affiliateDisplay),
                $width - $margin,
                $footerLabelY + 39,
                17,
                '#c4c7ca',
                600,
                'end'
            );
        }

        $svg[] = $this->text(
            'Trading involves risk. This is educational market analysis, not guaranteed profit.',
            $margin,
            $height - 34,
            14,
            '#6c7278',
            500
        );

        $svg[] = '</svg>';

        return implode("\n", $svg);
    }

    private function normalizeVariant(string $variant): string
    {
        $variant = strtolower(trim($variant));

        return array_key_exists($variant, self::VARIANTS)
            ? $variant
            : self::VARIANT_PORTRAIT;
    }

    private function chartImageDataUri(
        TradeIdea $tradeIdea
    ): ?string {
        if (! $tradeIdea->hasChartImage()) {
            return null;
        }

        $mime = strtolower(
            trim(
                (string) $tradeIdea->chart_image_mime_type
            )
        );

        if (! in_array(
            $mime,
            [
                'image/jpeg',
                'image/png',
                'image/webp',
            ],
            true
        )) {
            return null;
        }

        $disk = trim(
            (string) $tradeIdea->chart_image_disk
        );

        $path = trim(
            (string) $tradeIdea->chart_image_path
        );

        if ($disk === '' || $path === '') {
            return null;
        }

        try {
            $storage = Storage::disk($disk);

            if (! $storage->exists($path)) {
                return null;
            }

            $binary = $storage->get($path);

            if (
                $binary === ''
                || strlen($binary) > 12 * 1024 * 1024
            ) {
                return null;
            }

            return 'data:'.$mime.';base64,'
                .base64_encode($binary);
        } catch (Throwable) {
            return null;
        }
    }

    private function metricCard(
        float $x,
        float $y,
        float $width,
        float $height,
        string $label,
        string $value,
        string $valueColor,
        bool $compact = false
    ): string {
        $labelY = $y + ($compact ? 23 : 30);
        $valueY = $y + ($compact ? 50 : 70);
        $valueSize = $compact ? 19 : 25;

        return '<g>'
            .'<rect x="'.$x.'" y="'.$y.'" width="'.$width.'" height="'.$height.'" rx="18" '
            .'fill="#ffffff" fill-opacity=".035" stroke="#ffffff" stroke-opacity=".07"/>'
            .$this->text(
                $this->xml($label),
                $x + 18,
                $labelY,
                12,
                '#858c93',
                800,
                'start',
                '1.5'
            )
            .$this->text(
                $this->xml($value),
                $x + 18,
                $valueY,
                $valueSize,
                $valueColor,
                760
            )
            .'</g>';
    }

    private function text(
        string $text,
        float $x,
        float $y,
        int $size,
        string $color,
        int $weight = 500,
        string $anchor = 'start',
        string $letterSpacing = '0'
    ): string {
        return '<text '
            .'x="'.$x.'" '
            .'y="'.$y.'" '
            .'fill="'.$color.'" '
            .'font-family="Inter,Segoe UI,Arial,sans-serif" '
            .'font-size="'.$size.'" '
            .'font-weight="'.$weight.'" '
            .'text-anchor="'.$anchor.'" '
            .'letter-spacing="'.$letterSpacing.'">'
            .$text
            .'</text>';
    }

    private function wrapText(
        ?string $text,
        int $maxCharacters,
        int $maxLines
    ): array {
        $text = trim(
            preg_replace(
                '/\s+/u',
                ' ',
                (string) $text
            ) ?? ''
        );

        if ($text === '') {
            return [];
        }

        $words = preg_split(
            '/\s+/u',
            $text
        ) ?: [];

        $lines = [];
        $line = '';

        foreach ($words as $word) {
            $candidate = $line === ''
                ? $word
                : $line.' '.$word;

            if (mb_strlen($candidate) <= $maxCharacters) {
                $line = $candidate;
                continue;
            }

            if ($line !== '') {
                $lines[] = $line;
                $line = $word;
            } else {
                $lines[] = mb_substr(
                    $word,
                    0,
                    $maxCharacters
                );

                $line = mb_substr(
                    $word,
                    $maxCharacters
                );
            }

            if (count($lines) >= $maxLines) {
                break;
            }
        }

        if (
            $line !== ''
            && count($lines) < $maxLines
        ) {
            $lines[] = $line;
        }

        if (
            count($lines) === $maxLines
            && implode(' ', $lines) !== $text
        ) {
            $last = rtrim(
                $lines[$maxLines - 1],
                " ."
            );

            $lines[$maxLines - 1] = mb_substr(
                $last,
                0,
                max(
                    1,
                    $maxCharacters - 1
                )
            ).'…';
        }

        return array_slice(
            $lines,
            0,
            $maxLines
        );
    }

    private function priceText(mixed $value): string
    {
        if (
            $value === null
            || $value === ''
            || $value === '—'
        ) {
            return '—';
        }

        if (! is_numeric($value)) {
            return $this->cleanSingleLine(
                (string) $value,
                30
            ) ?? '—';
        }

        $formatted = number_format(
            (float) $value,
            10,
            '.',
            ''
        );

        $formatted = rtrim(
            rtrim(
                $formatted,
                '0'
            ),
            '.'
        );

        return $formatted === ''
            ? '0'
            : $formatted;
    }

    private function cleanSingleLine(
        ?string $value,
        int $maxLength
    ): ?string {
        $value = trim(
            preg_replace(
                '/\s+/u',
                ' ',
                (string) $value
            ) ?? ''
        );

        if ($value === '') {
            return null;
        }

        return mb_substr(
            $value,
            0,
            $maxLength
        );
    }

    private function configuredAffiliateUrl(
        ?string $referralCode
    ): ?string {
        if ($referralCode === null) {
            return null;
        }

        $baseUrl = trim(
            (string) config(
                'tradex_trade_ideas.share.affiliate_base_url',
                ''
            )
        );

        if ($baseUrl === '') {
            return null;
        }

        $separator = str_contains(
            $baseUrl,
            '?'
        )
            ? '&'
            : '?';

        return $baseUrl
            .$separator
            .'ref='
            .rawurlencode($referralCode);
    }

    private function affiliateDisplay(
        ?string $affiliateUrl
    ): ?string {
        if ($affiliateUrl === null) {
            return null;
        }

        $host = parse_url(
            $affiliateUrl,
            PHP_URL_HOST
        );

        $path = parse_url(
            $affiliateUrl,
            PHP_URL_PATH
        );

        if (! is_string($host)) {
            return $this->cleanSingleLine(
                $affiliateUrl,
                58
            );
        }

        $display = $host;

        if (
            is_string($path)
            && $path !== '/'
            && $path !== ''
        ) {
            $display .= rtrim(
                $path,
                '/'
            );
        }

        return $this->cleanSingleLine(
            $display,
            58
        );
    }

    private function xml(mixed $value): string
    {
        return htmlspecialchars(
            (string) $value,
            ENT_QUOTES | ENT_XML1,
            'UTF-8'
        );
    }
}