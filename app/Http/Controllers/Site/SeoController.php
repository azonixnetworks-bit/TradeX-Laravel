<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class SeoController extends Controller
{
    public function sitemap(Request $request): Response
    {
        $baseUrl = $this->baseUrl($request);
        $urls = collect(config('seo.pages', []))
            ->map(fn (array $page): array => [
                'loc' => $baseUrl.($page['path'] === '/' ? '/' : $page['path']),
                'changefreq' => $page['path'] === '/' ? 'weekly' : 'monthly',
                'priority' => $page['path'] === '/' ? '1.0' : '0.8',
            ])
            ->values();

        $xml = view('site.sitemap', ['urls' => $urls])->render();

        return response($xml, 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }

    public function robots(Request $request): Response
    {
        if (! app()->environment('production')) {
            $body = "User-agent: *\nDisallow: /\n";

            return response($body, 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
        }

        $baseUrl = $this->baseUrl($request);
        $privatePaths = "Disallow: /admin/\nDisallow: /customer/\nDisallow: /payments/\nDisallow: /api/\nDisallow: /up\n";

        $body = "User-agent: *\nAllow: /\n{$privatePaths}\n";

        if ((bool) config('seo.crawlers.allow_ai_search', true)) {
            $body .= "User-agent: OAI-SearchBot\nAllow: /\n{$privatePaths}\n";
        }

        $body .= "User-agent: GPTBot\n";
        $body .= (bool) config('seo.crawlers.allow_model_training', false)
            ? "Allow: /\n{$privatePaths}\n"
            : "Disallow: /\n";

        $body .= "\nSitemap: {$baseUrl}/sitemap.xml\n";

        return response($body, 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }

    public function llms(Request $request): Response
    {
        $baseUrl = $this->baseUrl($request);
        $pages = config('seo.pages', []);

        $lines = [
            '# TradeX',
            '',
            '> TradeX — The OS for Every Trader.',
            '',
            'TradeX is a trader workflow and market intelligence platform for planning, risk discipline, economic news awareness, education, and market analysis.',
            '',
            '## Official pages',
        ];

        foreach ($pages as $page) {
            $url = $baseUrl.($page['path'] === '/' ? '/' : $page['path']);
            $lines[] = '- ['.$page['h1'].']('.$url.'): '.$page['description'];
        }

        $lines = array_merge($lines, [
            '',
            '## Important product context',
            '- TradeX provides education, planning, analysis, and decision-support workflows.',
            '- Trading and investing involve risk; TradeX does not guarantee profits or market direction.',
            '- Economic-news bias, confidence, and volatility context are analytical inputs and can change as new information arrives.',
            '',
            '## Crawling',
            '- Canonical sitemap: '.$baseUrl.'/sitemap.xml',
            '- Crawler policy: '.$baseUrl.'/robots.txt',
            '',
            'This llms.txt file is a supplemental machine-readable summary. The canonical HTML pages and structured data remain authoritative.',
        ]);

        return response(implode("\n", $lines)."\n", 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }

    private function baseUrl(Request $request): string
    {
        $baseUrl = rtrim((string) config('app.url'), '/');

        return $baseUrl !== '' ? $baseUrl : $request->getSchemeAndHttpHost();
    }
}
