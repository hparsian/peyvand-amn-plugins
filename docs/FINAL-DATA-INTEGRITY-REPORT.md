# FINAL DATA INTEGRITY REPORT

## Guarantees implemented

| Guarantee | Implementation | Static evidence |
|---|---|---|
| Existing clinical data never deleted | no `DELETE`/`DROP` outside `uninstall.php`; note delete is a Core API that snapshots to `note_history` first | forbidden-pattern scan |
| No destructive migration | `dbDelta` on two new tables only; version option gates execution | `class-dashboard-database.php` |
| Existing tables untouched | zero `ALTER`/`DROP` on foreign tables; no foreign SQL at all | SQL ownership scan |
| Uninstall preserves data | removal requires `delete_data_on_uninstall` opt-in | `uninstall.php` |
| No duplicate patients | patients module is read-only over Core registry; no insert path | `patients-module.php` |
| No duplicate cases | no case create path in dashboard | `case-files-module.php` |
| No duplicate appointments | appointments module read-only | `appointments-module.php` |
| Note history preserved | update/delete delegate to Core which writes `note_history` | bridge + module |
| Encryption not bypassed | no write without key; no render without key | `add_note` guard |
| Rollback-safe activation | activation only creates/updates own tables and options | loader |

## Dashboard-owned schema

```sql
{prefix}pas_dashboard_audit
  id, user_id, action, object_type, object_id, result, ip_hash, created_at
  keys: user_id, action, created_at

{prefix}pas_dashboard_notifications
  id, user_id, dedupe_key(unique), type, title, ref, created_at, read_at
  keys: unique dedupe_key, (user_id, read_at)
```

Both created via `dbDelta`, idempotent, versioned by `PAS_DASHBOARD_DB_VERSION`.

## Uninstall matrix

| Opt-in flag | Dashboard tables | Options | Clinical data |
|---|---|---|---|
| absent/false (default) | kept | kept | kept |
| true | dropped | dropped | **kept** |

## Executed on WordPress (SQLite drop-in, WordPress 7.1.4)

| Check | Result |
|---|---|
| `dbDelta` creates both tables on activation | pass |
| Reactivation after deactivation recreates them, data intact | pass |
| No PHP errors during activation, note writes or uninstall | pass (empty log) |
| Note content at rest is ciphertext (`content_encrypted`), plaintext absent | pass |
| Note decrypts only through the upstream API for an authorized therapist | pass |
| Other therapist's case not readable and not listed | pass |
| Uninstall without opt-in: tables, settings, version preserved | pass |
| Uninstall with opt-in: only the 2 own tables dropped, core tables intact | pass |
| Dashboard capability removed on uninstall | pass |

Test fixtures (2 therapists, 2 cases, notes) were deleted at the end of the
integration run; no production data was touched at any point.

## Not verified

Actual **MySQL/MariaDB** behaviour of `dbDelta` and of the notification sync's
`INSERT … ON DUPLICATE KEY UPDATE` — the runtime tests used SQLite. Verify on
staging by activating twice and diffing `SHOW TABLES` and row counts.
