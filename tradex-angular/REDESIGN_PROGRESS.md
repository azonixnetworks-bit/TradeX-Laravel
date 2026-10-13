# TradeX Royal Obsidian Reconstruction

## Completed

- [x] Audited Angular project, routes, components and build scripts.
- [x] Confirmed TradingView, live Binance crypto bubbles, market clock, router links, native FAQ, contact links and platform redirects are isolated from presentation changes.
- [x] Created `codex/royal-obsidian-reconstruction` branch.
- [x] Added the Royal Obsidian token layer and shared premium components stylesheet.
- [x] Reconstructed shared navigation, footer, homepage, market pages, education, platform, legal, support and content-page visual surfaces through reusable CSS classes.

## Protected integrations

- TradingView embed and its symbol, region, timeframe, loading and retry behaviours.
- Binance REST/WebSocket crypto-bubble feed and collision simulation.
- Global market clock session calculation and interval updates.
- Existing Angular route map, links, platform authentication redirect, contact telephone link and legal copy.

## Validation

- [x] `npm run check`
- [x] `npm run build`
- [x] Desktop browser review of home and education routes
- [x] Route smoke test: all 17 public routes resolve to their expected page heading
- [x] Interactive-control smoke test: TradingView Stocks selector exposes CSE/India/International options and India switches to the configured CITYINDEX instrument
- [x] Browser console-error check
- [x] Responsive no-horizontal-overflow assertion at the available local browser viewport

## Blockers

- None currently.
