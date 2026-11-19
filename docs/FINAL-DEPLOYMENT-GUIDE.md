# FINAL DEPLOYMENT GUIDE

## 1. Requirements

| Item | Minimum | Tested |
|---|---|---|
| WordPress | 6.x | 7.1.4 |
| PHP (dashboard alone) | 7.4 | 7.4.33, 8.3.32, 8.5.8 |
| PHP (with Core/Case File) | **8.1** — the upstream plugins declare 8.1 and use `?->` | 8.5.8 |
| Database | MySQL 5.7 / MariaDB 10.3 | MariaDB 11.8.10 (InnoDB) + SQLite drop-in |
| Required plugins | Core, Case File (both 8.1+) | Core 1.8.1, Case File 1.16.1 |
| Optional plugins | Quiz Enterprise, SMS Bridge | 3.8.17 / 1.1.7 |
| Encryption | `PAS_CORE_ENCRYPTION_KEY` (`base64:` 32 bytes) | configured |

Without Core/Case File the dashboard activates but degrades with notices; it
never fatals — verified on PHP 7.4 with all four upstream plugins deactivated
(smoke 48/48, render 24/24, uninstall 13/13). Note that the clinical plugins
themselves require PHP 8.1, so a PHP 7.4 host can only run the dashboard's
degraded, non-clinical surface.

## 2. Pre-deployment

1. **Back up** the database and `wp-content/plugins` (mandatory).
2. Confirm Core and Case File are active and `PAS_CORE_ENCRYPTION_KEY` is set.
3. Record the current case count: `SELECT COUNT(*) FROM {prefix}pas_cf_cases;`.
4. Install on staging first, never directly on production.

## 3. Install

```
# via WP admin: Plugins → Add New → Upload → peyvand-amn-unified-dashboard-2.4.0.zip
# or CLI:
wp plugin install /path/peyvand-amn-unified-dashboard-2.4.0.zip --activate
```

Activation creates two tables and grants `pas_dashboard_access` to the
administrator role and to any role already holding `pas_manage_case_files`.

## 4. Access model

| Role | Sees |
|---|---|
| Administrator | all cases, settings, health, audit, export |
| `pas_cf_therapist` (or any role with `pas_manage_case_files`) | own cases only; no settings/audit/health |
| Subscriber / no capability | dashboard denied |

Optional: grant `pas_dashboard_access` to another role to allow entry, but
clinical data still requires `pas_manage_case_files`.

## 5. Post-deployment verification (10 minutes)

1. Open **Dashboard → نمای کلی**; confirm counts are non-zero.
2. As administrator, open a case that is assigned to a *different* therapist —
   it must open (view-all). As that therapist, the same case must not appear.
3. Set the encryption key, create a test note, then check the database:
   `SELECT content_encrypted FROM {prefix}pas_cf_notes ORDER BY id DESC LIMIT 1;`
   must not contain the plaintext.
4. Download CSV from **گزارش‌ها**; confirm it opens in Excel, has a BOM, hides
   phone/name, and that no cell starts with `=`, `+`, `-` or `@`.
5. Log out and request `/wp-json/pas-dashboard/v1/summary` — expect 401/403.
6. Toggle "enable notes" off; confirm new notes are refused.
7. Deactivate and reactivate; confirm settings and tables survive.

## 6. Rollback

Deactivate and delete the plugin. Clinical data, notes and encryption keys are
untouched; only `{prefix}pas_dashboard_*` tables and the
`pas_dashboard_*` options remain (harmless). To remove them, enable
"delete data on uninstall" before deleting.

## 7. Operations

- **Notifications** refresh at most once per 15 minutes per user.
- **Audit** rows store `ip_hash` (md5 + AUTH_SALT), never the raw IP.
- **CSV** is limited to 500 authorized cases per export.
- **No SMS is sent** from the dashboard; sending remains in SMS Bridge.

## 8. Troubleshooting

| Symptom | Cause | Action |
|---|---|---|
| "کلید رمزنگاری تنظیم نشده" | no `PAS_CORE_ENCRYPTION_KEY` | define it in `wp-config.php` |
| Empty case list as admin | `PAS_CF_CAP_ALL` missing | grant `pas_view_all_case_files` |
| Menu missing for therapist | role lacks `pas_manage_case_files` | grant the Case File capability |
| Note form disabled | feature off / no key / no clinical cap | check settings and role |
| Export downloads nothing | no authorized cases | verify assignment |
