# TradeX Royal Obsidian Luxury — Design Specification

## Shared design system

- Obsidian: `#06090F`; navy secondary: `#0B121C`; card: `#101923`.
- Champagne gold: `#C9A96B`; metallic gold: `#D8B875`.
- Platinum white: `#F5F0E6`; muted silver: `#A5ACB4`.
- Editorial headings use Cormorant Garamond; product UI uses Manrope.
- Layouts use dark cinematic imagery, restrained metallic borders, clear hierarchy and minimal, purposeful motion.

## Reference mapping

| Route | Primary reference |
| --- | --- |
| `/` | `01_Home_Full_Page.png` |
| `/platform` | `02_Platform_Full_Page.png` |
| `/education` | `03_Education_Full_Page.png` |
| `/market-intelligence` | `04_Market_Intelligence_Full_Page.png` |
| `/trade-ideas` | `05_Trade_Ideas_Full_Page.png` |
| `/copy-trading` | `06_Copy_Trading_Full_Page.png` |
| `/affiliate` | `07_Affiliate_Full_Page.png` |
| `/about` | `08_About_TradeX_Full_Page.png` |
| `/stocks` | `09_Stocks_Market_Full_Page.png` |
| `/deriv` | `10_Deriv_Market_Full_Page.png` |
| `/crypto` | `11_Crypto_Market_Full_Page.png` |
| `/forex` | `12_Forex_Market_Full_Page.png` |
| `/terms`, `/privacy`, `/risk-disclosure` | `13`–`15` matching legal references |

## Implementation rules

1. Use each reference only as a visual guide—never render full-page screenshots as website content.
2. Preserve Angular routing, TradingView, Binance market bubbles, market clock, platform URL, forms, links, and existing content.
3. Do not create or claim live market data; retain the existing real widget/data sources.
4. Build reusable HTML and CSS surfaces, keep keyboard focus visible and avoid horizontal overflow.
5. Reconstruct and validate one page before moving to the next requested page.
