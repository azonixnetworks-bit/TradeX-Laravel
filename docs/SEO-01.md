# SEO-01 — TradeX Public Search Foundation

## Purpose

SEO-01 creates a crawlable public TradeX entity while keeping administration, onboarding, customer, payment, API, and health surfaces out of search indexes.

The implementation is intentionally separated from the private administration UI and does not change RBAC, authentication, trading calculations, payment logic, or ADMIN-29 economic-news mutation rules.

## Public indexable surfaces

- `/`
- `/features`
- `/trading-tools`
- `/economic-calendar`
- `/market-news`
- `/trading-education`
- `/about`
- `/sitemap.xml`
- `/robots.txt`
- `/llms.txt`

## Technical SEO included

- Unique title and meta description per public page
- Canonical URLs based on `APP_URL`
- Open Graph and Twitter metadata
- Organization, WebSite, WebPage, SoftwareApplication, BreadcrumbList, and FAQPage JSON-LD where relevant
- XML sitemap
- Production-aware robots policy
- `X-Robots-Tag: noindex, nofollow, noarchive` on private surfaces and all non-production responses
- Google and Bing ownership-verification meta hooks
- Explicit OAI-SearchBot search-crawl policy
- Model-training crawler policy separated from search discovery
- Supplemental `llms.txt` entity summary
- Semantic headings, breadcrumbs, internal linking, accessible skip navigation, and mobile-first CSS

## Production environment requirements

Set `APP_URL` to the one canonical HTTPS origin before launch, for example `https://your-domain.example`. Do not deploy production with a localhost or staging `APP_URL`, because canonical, sitemap, Open Graph, and structured-data URLs derive from it.

Optional environment values:

```env
SEO_GOOGLE_SITE_VERIFICATION=
SEO_BING_SITE_VERIFICATION=
SEO_ALLOW_AI_SEARCH=true
SEO_ALLOW_MODEL_TRAINING=false
```

`SEO_ALLOW_AI_SEARCH=true` permits OAI-SearchBot on public pages while repeating private-path exclusions. `SEO_ALLOW_MODEL_TRAINING` is a separate policy choice and defaults to false.

## Launch checklist

1. Confirm production `APP_URL` is the final HTTPS domain.
2. Confirm `/robots.txt` returns the production policy, not `Disallow: /`.
3. Validate `/sitemap.xml` and submit it in Google Search Console and Bing Webmaster Tools.
4. Set Google/Bing verification tokens if meta verification is used.
5. Validate representative pages with Google's Rich Results / URL Inspection tools and Schema.org validation.
6. Confirm Cloudflare/WAF rules do not incorrectly block Googlebot, Bingbot, or OAI-SearchBot on public pages.
7. Verify `/admin/*`, `/onboarding/*`, `/customer/*`, `/payments/*`, `/api/*`, and `/up` return the `X-Robots-Tag` noindex policy.
8. Confirm `/admin/brand/*` remains crawlable because public structured data and social metadata reference official TradeX brand assets there.
9. Add IndexNow submission in the content-publishing phase when TradeX begins publishing frequently changing public news/articles.

## AI / GEO note

`llms.txt` is supplemental and is not treated as a replacement for crawlable HTML, robots.txt, sitemaps, canonical tags, high-quality content, or structured data. The canonical public pages remain the authoritative TradeX source.

Search engines and AI systems decide what they index, rank, cite, or recommend. SEO-01 improves the technical and entity signals under TradeX control but does not guarantee a ranking or recommendation.
