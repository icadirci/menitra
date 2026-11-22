# Brand — Menitra

Product name **Menitra**, production domain **menitra.com** (renamed from Restano on 2026-11-22). Infrastructure names (Docker project and containers `restano-*`, databases `restano` / `restano_test`, `/srv/restano`, the `restano:cart:` localStorage key) intentionally keep `restano`.

Source of truth: the design canvas, page "Menitra Logo & İkon" (direction **B · Modül**).

## Mark

A 5 × 5 grid of QR modules forming an "M"; the saffron centre module is the plate on the table. 48-unit grid: tile corner 24 %, modules 5.2 with radius 1.4, step 6.8, saffron module radius 2.6 (circle).

- Inline SVG: `resources/views/components/ui/logo-mark.blade.php` (`<x-ui.logo-mark>`, also used by the panel logo `filament/brand-logo.blade.php`).
- 16 / 32 px are pixel-fitted, not scaled: `public/favicon.svg` (16-unit grid, `crispEdges`) and `public/favicon.ico` (16, 32, 48).
- PNGs in `public/`: `apple-touch-icon.png` (180, full bleed; iOS rounds it), `icon-192.png`, `icon-512.png`, `icon-maskable-512.png` (mark inside the Android safe zone), referenced by `site.webmanifest`. They were rasterised from the geometry above; if the mark changes, export them again from the canvas rather than editing them by hand.

## Wordmark

Bricolage Grotesque Bold 700, always lower case, letter spacing −5 %, line height 1. The dot of the i is the saffron module (dotless `ı` plus a 0.19 em square, radius 0.05 em). Horizontal lockup (`<x-ui.logo>`): wordmark size = mark height, gap = 30 % of the mark height. The wordmark is artwork (`aria-hidden`); the accessible name is `__('auth.brand')`.

## Colours

| Name | Hex | Token |
|---|---|---|
| Menitra green | `#1F4D3B` | `forest-700` |
| Saffron | `#E7A33E` | `saffron-500` |
| Ink | `#15191A` | `ink` |
| Paper | `#F6F5F1` | `paper` |

On green backgrounds the tile inverts (paper tile, green modules); on dark backgrounds the green tile stays. Don't stretch, rotate, recolour, add shadows or set the wordmark in capitals.

## Not done yet

- `og-image.png` (1200 × 630) from the canvas: needs the wordmark rendered as text, so it must be exported from the design tool; no page references an Open Graph image yet.
