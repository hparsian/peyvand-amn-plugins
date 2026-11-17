# FINAL UI UPGRADE — داشبورد یکپارچه درمانگر

**Version:** 2.0.0 (package unchanged, presentation upgraded)
**Scope:** presentation and interaction affordances only — no new feature, no
change to capability checks, nonces, queries or data flow.
**Verification:** `php -l` 32/32, static suite 100/100, module render 26/26 on
SQLite/PHP 8.3 and on MariaDB/PHP 8.5, plus a live admin session in a real
browser (screenshot: `artifacts/dashboard-ui-overview.png`).

## 1. What changed

| Area | Before | After |
|---|---|---|
| Design tokens | flat colours, one radius | full token set: ink/line/shadow scales, brand gradient, `--pas-ring` focus ring, `--pas-radius` / `-sm` |
| Header | plain white bar, text only | sticky translucent bar, gradient module icon tile, avatar chip with initial, notification pill with badge |
| Sidebar | text links | sticky nav with Dashicons per module, section label, active item gradient with shadow, crypto status dot |
| KPI cards | flat grey boxes | bordered cards with brand accent bar, 28 px tabular figures, lift on hover |
| Tables | plain borders | rounded container with shadow, sticky head, zebra rows, brand-tinted hover; **≤ 600 px each row becomes a labelled card** (`data-label` + `td::before`), so no horizontal scrolling |
| Forms | thin borders | 10 px padding, 3 px focus ring, custom checkbox accent, filter panel framed in a panel |
| Notices | flat tint | start-edge accent bar, tinted border, semantic colours |
| Pagination | rectangular links | pill links, filled current page |
| Notes | plain block | card with inset body surface, styled `<details>` history |
| Empty state | grey box | dashed surface with soft gradient |
| Accessibility | none | skip link to main content, `:focus-visible` rings on nav/buttons/toggle, `prefers-reduced-motion` honoured |
| Responsive | sidebar hidden < 900 px | same breakpoint, plus sticky header released, tighter table padding ≤ 900 px (fits 768 px exactly), stacked table cards and full-width header actions ≤ 600 px |

## 2. Markup changes (server-rendered, escaped)

- `templates/layout.php` — added `.pas-skip-link` (“پرش به محتوا”) pointing at
  the existing `#pas-main`.
- `templates/header.php` — module icon tile (`.pas-title-icon`), avatar chip
  (`.pas-avatar`, first grapheme of the display name via `mb_substr`), all output
  through `esc_html` / `esc_attr`.
- `templates/sidebar.php` — per-module Dashicon and a status dot for encryption;
  label list and version unchanged.
- `includes/modules.php` — added `PAS_Dashboard_Module::icon_for( $id )` and
  `icon()`; a pure presentation map with a safe fallback (`dashicons-marker`).

No capability, nonce, query or export logic was touched. Dashicons is enqueued
by WordPress in `wp-admin`, so no new asset or CDN dependency was introduced.

## 3. Security invariants preserved

- Module content is still escaped inside each module; the layout still prints it
  as before.
- The notification bell is still rendered only when
  `PAS_Dashboard_Permissions::can( 'notifications' )`.
- The sidebar renders only the modules already filtered by permissions in
  `PAS_Dashboard_Admin::nav()`.
- Icons come from a fixed allow-list keyed by module id, not from request data.
- No inline script, no `style` attribute from user input, no external font.

## 4. Visual verification (real browser, live MariaDB data)

Rendered in a live WordPress 7.1.4 + MariaDB 11.8.10 admin session with six
synthetic cases, at **1024, 768 and 360 px**.

| Width | Result |
|---|---|
| 1024 px | module icon tile, title/subtitle hierarchy, notification pill, avatar chip, side navigation with icons and active gradient, KPI cards, empty state — screenshots `artifacts/dashboard-ui-overview.png` |
| 768 px | sidebar collapses to the toggle, all seven table columns fit without a scrollbar, sticky head and zebra rows intact — `artifacts/ui-768-after.png` |
| 360 px | table rows render as labelled cards (شناسه/مراجع/وضعیت/درمانگر/آخرین جلسه/به‌روزرسانی/عملیات), action buttons full-width-safe, no page-level horizontal scroll — `artifacts/ui-360-after.png` |

Measured in the browser: `documentElement.scrollWidth == clientWidth` on every
one of the eleven modules at both 360 px and 768 px (22 checks, 0 overflow).

### Layout defects found in this pass and fixed

| ID | Symptom | Cause | Fix |
|---|---|---|---|
| D18 | Page scrolled horizontally ~10 000 px in RTL; the whole admin was shifted | `.pas-skip-link { inset-inline-start: -9999px }` — in RTL the inline start is the right edge, so the link was pushed 9999 px past it | hide the link with `transform: translateY(-120%)` + `opacity: 0` + `pointer-events: none`, revealed on `:focus`; no off-screen box is created |
| D19 | Table at 360 px was clipped mid-column (“درما…”) and required blind horizontal scrolling | fixed `min-width: 520px` table inside a scroll wrapper | ≤ 600 px: `thead` visually hidden, rows become cards, each `td` shows its column name via `::before { content: attr(data-label) }`; `data-label` is emitted with `esc_attr__()` in all eleven modules (47 cells) |
| D20 | Table produced a 3 px overflow (and therefore a scrollbar) at 768 px | 14 px cell padding × 7 columns exceeded the 659 px wrapper | cell padding reduced to 10×11 px at ≤ 900 px |

Still not verified: hover/focus visuals, the note form keyboard flow inside a
360 px viewport, and browsers other than Chromium.

## 5. Rollback

The upgrade touches three templates, one class file and one stylesheet.
Reverting those files restores the previous appearance; no option, table or
stored data is involved, so rollback is stateless.
