# TradeX OS — Angular public website

Professional responsive Angular redesign based on the supplied Laravel public website ZIP. Original company identity, phone number, education positioning and policy copy are preserved. This is a public frontend, not a replacement for the Laravel administration system.

## Run on Windows / PowerShell

Install Node.js 22.12+ or 24 LTS, then run from this folder:

```powershell
npm ci
npm start
```

Open `http://localhost:4200`.

```powershell
npm run build
```

Upload the **contents** of `dist/` to your public website document root. The compiled output is included in this package so it can be previewed without building. Angular client routes need a fallback to index.html; use the included `deployment/apache.htaccess` as `.htaccess` for a standalone cPanel document root.

## Pages

Home, Platform, Education, Market Intelligence, Forex, Crypto, Deriv, Stocks, Trade Ideas, Copy Trading, Affiliate Program, Planning Tools, About, FAQ, Contact, Terms, Privacy and Risk Disclosure. FAQ uses accessible native disclosure controls. Mobile navigation closes on route change. Market preview tabs and chart timeframes are interactive.

## Laravel integration

Set the existing platform login address in `src/app/config.ts`. The default is `https://cloudvideo.online/admin/client`; verify the login route in the full Laravel project before production. The uploaded ZIP does not include its admin route definitions.

Deploy Angular in a separate public document root/subdomain, or mount it in Laravel while retaining all admin and API routes. Do not replace the original Laravel `routes/web.php` with Angular source or a global SPA fallback. For a shared Laravel host, scope any index.html fallback to public frontend routes and preserve `/admin`, `/api`, authentication, webhooks and payment returns. The included Apache fallback is intended only for a standalone frontend document root.

## Charts and integrations

Every chart uses the TradingView Advanced Chart widget, which loads automatically on component mount. There are no illustrative charts or generated prices. The homepage, Market Intelligence, Forex, Crypto and Stocks pages share this component. Forex defaults to the verified IC Markets symbol `ICMARKETS:EURUSD`; Crypto uses `BITSTAMP:BTCUSD` and Stocks uses `SP:SPX`. No studies or indicators are loaded by default. Market tabs and 15m/1h/4h/1d controls reload the widget with the selected symbol and interval. A loading state, a 20-second timeout, retry action and direct TradingView link handle blocked/slow external scripts. Quotes can be delayed depending on the instrument and TradingView's available data. Deriv live quotes are not available through this public widget, so its page clearly directs users to authenticated provider data instead of displaying a substitute symbol.

The full visual theme follows the requested Institutional Gold direction: `#080A0D` backgrounds, `#15191F` surfaces, metallic gold accents, Manrope headings, Inter body text and responsive cards. Decorative red, blue and purple accents are not used. The header and footer wordmark X uses a bold metallic gold shimmer, with a static gold treatment for reduced-motion preferences. TradeX branding and original public content remain in place.
Newsletter remains Coming soon, consistent with the original disconnected form. Contact uses the original phone number. No new backend, account registration, email delivery, trading execution, payment processing or analytics are included. Source policies are migrated unchanged; this redesign does not validate their legal sufficiency.

## Structure

- `src/app/app.ts`: shared navigation/footer
- `src/app/home.ts`: homepage
- `src/app/page.ts`: content pages, FAQ and contact
- `src/app/pages-data.ts`: original public page copy
- `src/app/chart.ts`: chart preview and TradingView integration
- `src/app/config.ts`: platform login destination
- `src/styles.css`: responsive brand theme
- `public/brand`: original supplied branding
- `dist`: compiled production frontend

## Validation

On 2026-10-10, `npm ci`, `npm run check` and `npm run build` completed successfully. The production bundle was generated in `dist/`. Browser QA confirmed the desktop homepage, Crypto page and live EURUSD TradingView widget. Complete device-matrix and legal review are still recommended before production.
