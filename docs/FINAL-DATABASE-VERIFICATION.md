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

## 5. MySQL / MariaDB — NOT verified

The environment has **no MySQL server and no `mysqli` extension** (PHP has
`pdo_mysql` only, and WordPress requires `mysqli`), so the runtime tests used
the official SQLite drop-in. Consequently:

| Item | Status |
|---|---|
| `dbDelta` DDL on MySQL/MariaDB | unverified |
| `INSERT … ON DUPLICATE KEY UPDATE` semantics | unverified (MySQL-only syntax; SQLite path not exercised) |
| Index/unique enforcement on InnoDB | unverified |
| Charset/collation (`utf8mb4`) behaviour with Persian text | unverified |

**Required before production:** activate on a MySQL/MariaDB staging site, create
one note, confirm both tables, then delete and reinstall to check `dbDelta`
idempotency. Compare `SHOW CREATE TABLE` against §1.

## 6. Backup and rollback

The plugin adds tables only. Rollback = deactivate and delete the plugin
directory; both tables and all clinical data remain. To remove dashboard tables,
tick the opt-in setting before deleting, or run `uninstall.php`.
