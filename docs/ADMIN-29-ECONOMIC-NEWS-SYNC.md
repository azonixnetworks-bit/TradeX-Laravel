# ADMIN-29 — Automatic Economic Calendar Sync

## Purpose

ADMIN-29 supports a provider-backed economic calendar while preserving the existing manual administration and override controls.

The first production adapter is Trading Economics. Provider credentials are deployment secrets and are never stored in the economic news database tables, rendered in Admin, or returned by the mobile API.

## Runtime flow

1. Laravel scheduler invokes `tradex:economic-news:sync` at the configured interval.
2. The provider adapter requests the configured country/date window.
3. Provider events are normalized to UTC and imported by provider event id.
4. Only events at or above the configured importance threshold are imported. The default is importance 2 (medium) and 3 (high).
5. Provider-owned calendar facts are refreshed: schedule, impact, actual, forecast, previous/revised values, status and source payload.
6. Existing TradeX intelligence fields (`bias`, `confidence_score`, `reason`, `analysis`) are not overwritten on provider refresh.
7. Active manual overrides remain a separate overlay and therefore remain authoritative in effective intelligence.
8. Missing FOREX pair mappings are created from the TradeX screener catalog using the event currency. Automatic mappings start as `NEUTRAL` with confidence `0`; directional intelligence is intentionally not guessed from a generic actual-vs-forecast rule.
9. When a previously-known event receives its first actual value, an idempotent `RELEASE` live update is created. Provider revisions create idempotent `REVISION` updates.

## Environment variables

Add these to the deployment environment. Do not commit the real API key.

```dotenv
TRADEX_ECONOMIC_NEWS_SYNC_ENABLED=false
TRADEX_ECONOMIC_NEWS_PROVIDER=trading_economics
TRADEX_ECONOMIC_NEWS_SYNC_FREQUENCY_MINUTES=5
TRADEX_ECONOMIC_NEWS_LOOKBACK_DAYS=2
TRADEX_ECONOMIC_NEWS_LOOKAHEAD_DAYS=14
TRADEX_ECONOMIC_NEWS_MIN_IMPORTANCE=2
TRADEX_ECONOMIC_NEWS_AUTO_PUBLISH=false
TRADEX_ECONOMIC_NEWS_COUNTRIES="United States,Euro Area,United Kingdom,Japan,Australia,New Zealand,Canada,Switzerland"

TRADEX_TRADING_ECONOMICS_BASE_URL=https://api.tradingeconomics.com
TRADEX_TRADING_ECONOMICS_API_KEY=
TRADEX_TRADING_ECONOMICS_TIMEOUT_SECONDS=15
TRADEX_TRADING_ECONOMICS_CONNECT_TIMEOUT_SECONDS=5
```

`TRADEX_ECONOMIC_NEWS_AUTO_PUBLISH` intentionally defaults to `false`. This allows provider ingestion to be validated in Admin before customer/mobile publication is enabled.

## Deployment sequence

```bash
php artisan migrate
php artisan db:seed --class=EconomicNewsPermissionSeeder
php artisan optimize:clear
php artisan tradex:economic-news:sync
php artisan schedule:list
php artisan test --filter=EconomicNews
```

Production must also run Laravel's scheduler (normally one `schedule:run` invocation each minute or a continuously-running `schedule:work` process, depending on the deployment model).

After validating imported events in Admin, enable the scheduler switch:

```dotenv
TRADEX_ECONOMIC_NEWS_SYNC_ENABLED=true
```

Then clear cached configuration:

```bash
php artisan optimize:clear
```

## Manual sync

Default configured window:

```bash
php artisan tradex:economic-news:sync
```

Explicit UTC date window:

```bash
php artisan tradex:economic-news:sync --from=2026-09-16 --to=2026-09-20
```

## Source controls

The provider source is created automatically with code `TRADING_ECONOMICS` on first enabled sync. Admin may disable that source from Economic News source management; a disabled source stops external synchronization even when the global environment switch is enabled.

`public_config_json` stores only non-secret scope metadata such as countries and date-window settings. The provider API key remains in the deployment environment.

## Publication and licensing boundary

Provider ingestion and Admin review can operate with automatic publication disabled. Before enabling customer-facing redistribution, confirm that the active provider subscription/license permits the intended TradeX display and redistribution use. TradeX does not bypass provider access controls or scrape provider pages.

## Directional intelligence boundary

Economic releases are not universally directional from a simple `actual > forecast` comparison. For example, higher unemployment and higher policy rates have different economic interpretations. ADMIN-29 therefore auto-maps affected symbols but leaves direction neutral until a category-aware TradeX intelligence rule or reviewed analysis is applied.
