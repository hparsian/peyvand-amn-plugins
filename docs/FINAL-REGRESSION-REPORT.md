# FINAL REGRESSION REPORT

## Method

The dashboard is additive. Regression risk comes from (a) modified existing
plugins, (b) shared option/table names, (c) global hook collisions.

## Findings

| Check | Evidence | Result |
|---|---|---|
| Existing plugin source modified | no writes outside `plugins/peyvand-amn-unified-dashboard/` | none |
| Options written | only `pas_dashboard_settings`, `pas_dashboard_db_version`, `pas_dashboard_version` (suite: option ownership) | no collision |
| Tables created | only `pas_dashboard_audit`, `pas_dashboard_notifications` | no collision |
| Hooks added | `admin_menu`, `admin_init`, `admin_enqueue_scripts`, `admin_post_*`, `wp_ajax_*`, `rest_api_init`, `plugins_loaded` (prio 20) | namespaced |
| Capabilities | adds `pas_dashboard_access`; reads `pas_manage_case_files`, `pas_view_all_case_files` | additive only |
| Reads from other plugins | public static APIs only | no schema dependency |
| Deactivation | clears own cron hook, flushes rewrite; no data touched | safe |
| Uninstall | preserves data unless opt-in flag | safe |

## Behavioural regressions to watch in staging

1. Case File admin still works after dashboard activation (shared cap class).
2. Client Portal OTP flow unaffected (dashboard never writes its tables).
3. Quiz Enterprise REST routes unaffected (separate namespace `psy/v1`).
4. SMS bridge credentials untouched (dashboard only reads log).
5. Admin menu order: dashboard uses position 3; verify no collision with theme
   or other plugin menus.

## Runtime regression evidence

| Check | Result |
|---|---|
| Activation alongside Core, Case File, Quiz and SMS on one site | pass, empty PHP error log |
| Case File's own tables and data after dashboard activation | untouched (dashboard never writes them) |
| Dashboard tables after Core/Case File activation | intact, `dbDelta` idempotent |
| Deactivate → activate | schema and capability restored, data intact |
| Test fixtures removed after integration run | 2 users + 2 cases + notes deleted |

Items 1–4 above remain staging checks; item 2 is partially covered because the
dashboard read real Case File rows without modifying them.
