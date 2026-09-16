<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SiteController extends Controller
{
    public function home(Request $request): View
    {
        return $this->renderPage('home', $request);
    }

    public function page(Request $request): View
    {
        $key = (string) $request->route('seo_page');

        abort_unless(array_key_exists($key, config('seo.pages', [])), 404);

        return $this->renderPage($key, $request);
    }

    private function renderPage(string $key, Request $request): View
    {
        $pages = config('seo.pages', []);
        $page = $pages[$key];
        $baseUrl = rtrim((string) config('app.url'), '/');

        if ($baseUrl === '') {
            $baseUrl = $request->getSchemeAndHttpHost();
        }

        $canonical = $baseUrl.($page['path'] === '/' ? '' : $page['path']);
        $brand = config('seo.brand', []);
        $schema = $this->schema($key, $page, $brand, $baseUrl, $canonical);

        return view($key === 'home' ? 'site.home' : 'site.page', [
            'pageKey' => $key,
            'page' => $page,
            'pages' => $pages,
            'brand' => $brand,
            'canonical' => $canonical,
            'schema' => $schema,
        ]);
    }

    private function schema(
        string $key,
        array $page,
        array $brand,
        string $baseUrl,
        string $canonical,
    ): array {
        $organizationId = $baseUrl.'/#organization';
        $websiteId = $baseUrl.'/#website';
        $webpageId = $canonical.'#webpage';

        $graph = [
            [
                '@type' => 'Organization',
                '@id' => $organizationId,
                'name' => $brand['name'],
                'url' => $baseUrl.'/',
                'logo' => [
                    '@type' => 'ImageObject',
                    'url' => $baseUrl.'/admin/brand/tradex-logo.png',
                ],
                'slogan' => $brand['tagline'],
                'description' => $brand['description'],
            ],
            [
                '@type' => 'WebSite',
                '@id' => $websiteId,
                'url' => $baseUrl.'/',
                'name' => $brand['name'],
                'description' => $brand['description'],
                'publisher' => ['@id' => $organizationId],
                'inLanguage' => $brand['language'],
            ],
            [
                '@type' => 'WebPage',
                '@id' => $webpageId,
                'url' => $canonical,
                'name' => $page['title'],
                'description' => $page['description'],
                'isPartOf' => ['@id' => $websiteId],
                'about' => ['@id' => $organizationId],
                'inLanguage' => $brand['language'],
            ],
        ];

        if (in_array($key, ['home', 'features', 'trading-tools'], true)) {
            $graph[] = [
                '@type' => 'SoftwareApplication',
                '@id' => $baseUrl.'/#software',
                'name' => $brand['name'],
                'applicationCategory' => 'FinanceApplication',
                'operatingSystem' => 'Web',
                'url' => $baseUrl.'/',
                'description' => $brand['description'],
                'publisher' => ['@id' => $organizationId],
            ];
        }

        if ($key !== 'home') {
            $graph[] = [
                '@type' => 'BreadcrumbList',
                '@id' => $canonical.'#breadcrumb',
                'itemListElement' => [
                    [
                        '@type' => 'ListItem',
                        'position' => 1,
                        'name' => 'TradeX',
                        'item' => $baseUrl.'/',
                    ],
                    [
                        '@type' => 'ListItem',
                        'position' => 2,
                        'name' => $page['h1'],
                        'item' => $canonical,
                    ],
                ],
            ];
        }

        if (! empty($page['faq'])) {
            $graph[] = [
                '@type' => 'FAQPage',
                '@id' => $canonical.'#faq',
                'mainEntity' => array_map(
                    static fn (array $item): array => [
                        '@type' => 'Question',
                        'name' => $item['question'],
                        'acceptedAnswer' => [
                            '@type' => 'Answer',
                            'text' => $item['answer'],
                        ],
                    ],
                    $page['faq'],
                ),
            ];
        }

        return [
            '@context' => 'https://schema.org',
            '@graph' => $graph,
        ];
    }
}
