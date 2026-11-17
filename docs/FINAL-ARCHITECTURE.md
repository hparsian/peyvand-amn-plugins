# FINAL ARCHITECTURE — Peyvand Amn Unified Therapist Dashboard 2.0.0

## 1. Shape

Independent WordPress plugin. One admin entry (`pas-unified-dashboard`), one
router, eleven modules, one security layer, one integration bridge. No parallel
patient/case/appointment storage. No AI, no external AI service.

```
peyvand-amn-unified-dashboard/
├─ peyvand-amn-unified-dashboard.php   bootstrap + constants + lifecycle hooks
├─ uninstall.php                      opt-in removal of dashboard tables only
├─ includes/
│  ├─ class-dashboard-loader.php      singleton, requires, hooks, notices
│  ├─ class-dashboard-validation.php  all input coercion
│  ├─ class-dashboard-database.php    own tables (dbDelta, versioned)
│  ├─ class-dashboard-audit-log.php   append-only audit trail
│  ├─ class-dashboard-core-bridge.php the only contact point with other plugins
│  ├─ class-dashboard-security.php    auth, caps, resource access, nonces, gates
│  ├─ class-dashboard-permissions.php capability matrix + feature flags
│  ├─ class-dashboard-notifications.php dedupe + ownership
│  ├─ class-dashboard-export.php      nonce + cap + minimised CSV
│  ├─ class-dashboard-ajax.php        4 endpoints, per-endpoint guard
│  ├─ class-dashboard-rest.php        3 read-only routes, explicit permissions
│  ├─ class-dashboard-assets.php      scoped CSS/JS + nonce localisation
│  ├─ class-dashboard-router.php      menu + module dispatch + template
│  ├─ modules.php                     abstract module base
│  └─ modules/                        11 modules
├─ templates/                         layout, header, sidebar, footer
├─ assets/                            RTL CSS, vanilla+jQuery JS
├─ languages/                          fa_IR .pot
└─ readme.txt
```

## 2. Request/authorization flow

```
admin request
  → PAS_Dashboard_Router::render()
    → Security::require_dashboard()          (login + dashboard cap)
    → Permissions::visible_modules()         (matrix: dashboard|clinical|manage)
    → Permissions::can($module)              (cap + feature flag)
    → module->render()
        → Security::authorized_case($id)     (clinical cap + case ownership)
        → Core_Bridge::...                   (public APIs of other plugins)
```

Every write additionally passes a nonce bound to its own action name. Nonces
never substitute for capability or resource checks.

## 3. Data ownership

| Data | Owner | Dashboard access |
|---|---|---|
| clients / identifiers / conflicts | `pivand-amn-core` | public registry API + `pas_get_client` filter |
| cases / notes / note_history | `pivand-amn-case-file` | public static API only |
| assessments catalogue | `pivand-amn-quiz-enterprise` | `Registry::all()` (public) |
| SMS log | `pivand-amn-sms-bridge` | `Pas_Log::recent()` (public) |
| audit + notifications | dashboard | own tables only |

Two dashboard tables: `{prefix}pas_dashboard_audit`,
`{prefix}pas_dashboard_notifications`.

## 4. Failure behaviour

* Missing Core / Case File → module shows a warning, does not fatal.
* Missing encryption key → no note display, no note write, `WP_Error` from
  `add_note`, settings and health both say so.
* Missing SMS bridge → status "inactive", no send button.
* Missing quiz registry → catalogue warning.
* Unauthorized case → `WP_Error` + audit `denied`, never partial data.

## 5. UI

Persian RTL, Vazirmatn/Tahoma stack, responsive grid (sidebar collapses under
900px), keyboard-reachable controls, `prefers-reduced-motion` respected,
explicit empty/error/notice states. Assets load only on the dashboard screen.

## 6. Verified vs not verified

Static structure, endpoint wiring, forbidden-pattern absence, nonce and
escaping coverage: **executed** (`python3 tests/verify_plugin.py`, 100/100).
JavaScript parse: **executed** (`node --check`). PHP runtime, WordPress
activation, MySQL behaviour and browser rendering: **not executed** in this
sandbox (no PHP/WP runtime).
