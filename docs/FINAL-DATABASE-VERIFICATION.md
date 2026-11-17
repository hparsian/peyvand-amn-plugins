# FINAL DATABASE VERIFICATION

## 1. Ownership

The dashboard owns exactly two tables and never writes any other plugin's
schema:

```
{prefix}pas_dashboard_audit
  id bigint unsigned AI, user_id bigint unsigned, action varchar(64),
  object_type varchar(32), object_id bigint unsigned, result varchar(20),
  ip_hash char(32), created_at datetime
  PRIMARY KEY (id), KEY user_id, KEY action, KEY created_at

{prefix}pas_dashboard_notifications
  id bigint unsigned AI, user_id bigint unsigned, dedupe_key char(64),
  type varchar(40), title varchar(190), ref varchar(100),
  created_at datetime, read_at datetime NULL
  PRIMARY KEY (id), UNIQUE KEY dedupe_key, KEY user_unread (user_id, read_at)
```

Created with `dbDelta`, versioned by `PAS_DASHBOARD_DB_VERSION = 1.0.0`, and
gated by `PAS_Dashboard_Database::maybe_upgrade()`.

## 2. Executed evidence (WordPress 7.1.4, SQLite drop-in)

| Check | Result |
|---|---|
| Activation creates both tables | PASS (queried `sqlite_master`) |
| `pas_dashboard_db_version` stored as `1.0.0` | PASS |
| Both tables dropped on opt-in uninstall | PASS |
| Both tables survive default uninstall | PASS |
| Deactivate → activate recreates tables, data intact | PASS |
| Audit insert + read round-trip | PASS |
| Notification insert, unread count, per-user mark-read | PASS |
| Unique `dedupe_key` enforced | PASS (second insert collides by design) |
| Core WordPress tables untouched by uninstall | PASS (`wp_posts`, `wp_users`) |
| PHP error log during all DB operations | empty |

## 3. Query safety

| Pattern | Finding |
|---|---|
| Dynamic values | all via `$wpdb->prepare()` (`%d`, `%s`) |
| Table names | only `$wpdb->prefix . 'pas_dashboard_*'` constants |
| Foreign tables | zero SQL in plugin code |
| Destructive SQL | only `uninstall.php`, only own tables, only with opt-in |
| `SHOW TABLES LIKE` | parameterised via `prepare()` |
| Notification upsert | `INSERT … ON DUPLICATE KEY UPDATE` (MySQL-specific) |

## 4. Migration behaviour

- No `ALTER`, `DROP` or data rewrite on upgrade paths.
- `dbDelta` is idempotent; activation runs it once, `maybe_upgrade()` only when
  the stored version differs.
- Settings are merged with defaults on read and on save, so adding keys later
  does not wipe stored values.
- No production data, patient rows, notes or encryption keys are migrated or
  rewritten by this plugin.

## 5. MySQL / MariaDB — VERIFIED

Run on a real server: **MariaDB 11.8.10** (`127.0.0.1:3307`, InnoDB) with
**PHP 8.5.8 + mysqli**, WordPress 7.1.4 and the upstream Core 1.8.1 / Case File
1.16.1 / Quiz 3.8.17 / SMS 1.1.7 activated. No SQLite drop-in involved.

| Item | Result |
|---|---|
| `dbDelta` creates both tables on MariaDB/InnoDB | PASS — `SHOW TABLES` returns `wp_pas_dashboard_audit`, `wp_pas_dashboard_notifications` |
| `SHOW CREATE TABLE` matches §1 (PK, keys, unique `dedupe_key`) | PASS |
| `INSERT … ON DUPLICATE KEY UPDATE` in notification sync | PASS — exercised by the smoke suite on MariaDB; dedupe key collision handled by the upsert, no duplicate row |
| Unique `dedupe_key` enforced by InnoDB | PASS |
| `utf8mb4` Persian text round-trip (case status, notification title, audit action) | PASS — no mojibake in rendered output or CSV |
| Re-activation is idempotent (`dbDelta` second run, no duplicate columns/indexes) | PASS — deactivate → activate → same schema, existing rows intact |
| `table_exists()` using `SHOW TABLES LIKE` with `prepare()` | PASS |
| Audit insert/read, notification unread count, per-user mark-read | PASS (smoke + integration suites) |
| PHP error log during DB operations | empty for plugin code (only WP-CLI's own PHP 8.5 notices) |

### Test-harness portability fix (D17)

`tests/wp/smoke.php` and `tests/wp/uninstall.php` read table names from
`sqlite_master`, so on MariaDB the two “table created by dbDelta” assertions
failed even though the tables existed. Both now use
`$wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) )`, which works
on MySQL and on the SQLite drop-in. Re-run: smoke 48/48, uninstall 13/13 on
both engines.

### Still unverified on MySQL

- Multisite (`{prefix}_{blog_id}_` table fan-out).
- Very large audit tables (no index-bloat measurement beyond the schema check).
- MySQL 5.7 / 8.0 — only MariaDB 11.8 was available.

## 6. Backup and rollback

The plugin adds tables only. Rollback = deactivate and delete the plugin
directory; both tables and all clinical data remain. To remove dashboard tables,
tick the opt-in setting before deleting, or run `uninstall.php`.
