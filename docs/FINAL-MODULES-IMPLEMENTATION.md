# FINAL MODULES IMPLEMENTATION

All eleven modules are registered in `PAS_Dashboard_Permissions::matrix()` and
implemented as `PAS_Dashboard_Module_*` subclasses. Access level is enforced
before instantiation.

| # | Module | File | Access | Data source | Writes |
|---|---|---|---|---|---|
| 1 | نمای کلی | `modules/overview-module.php` | dashboard | `list_for_therapist`, registry stats | none |
| 2 | مراجعان | `modules/patients-module.php` | dashboard | `sample_clients`, `get_client`, `get_client_by_identifier` | none |
| 3 | پرونده‌ها | `modules/case-files-module.php` | clinical | `get`, `get_notes`, `get_couple_sessions`, `get_note_history` | note create/update/delete/restore |
| 4 | آزمون‌ها | `modules/assessments-module.php` | dashboard | `Registry::all()` | none |
| 5 | نوبت‌ها | `modules/appointments-module.php` | dashboard | `get_next_session`, `get_linked_bookings` | none |
| 6 | گزارش‌ها | `modules/reports-module.php` | clinical | scoped cases + `get_last_session_map` + registry stats | CSV export |
| 7 | ارتباطات | `modules/communications-module.php` | dashboard | `has_filter('pas_send_sms')`, `Pas_Log::recent`, `reminder_blocker` | none |
| 8 | اعلان‌ها | `modules/notifications-module.php` | dashboard + feature | own table | mark read |
| 9 | سلامت سیستم | `modules/system-health-module.php` | manage | PHP/WP versions, class/method/file checks | none |
| 10 | تنظیمات | `modules/settings-module.php` | manage | dashboard option only | save settings |
| 11 | ممیزی | `modules/audit-module.php` | manage | own audit table | none |

## Module 3 — Case files (the sensitive one)

**List**: `list_for_therapist(get_current_user_id(), 500)` only. Never
`list_all()`. Last-session column uses one grouped metadata query
(`get_last_session_map`), not per-row note decryption.

**Detail**: `Security::authorized_case($id)` → `Pas_CF_Case_File::get($id)` +
`Pas_CF_Security::current_user_can_access_case($case)`. Unauthorized ids return
a generic error and an audit `denied` row.

**Notes**:
* Display only when `crypto_ready()`. Content is escaped and `nl2br`-ed.
* Create: `notes_writable()` (clinical cap + crypto + `enable_notes`) → nonce
  `pas_dashboard_note_save` → `add_note()`. Missing key returns `WP_Error`.
* Update: nonce `note_save`, note id + case id both validated; the Core API
  itself rejects a note whose `case_id` differs (IDOR guard).
* Delete: nonce `note_delete`, `notes_writable()`, Core API snapshots the
  current version into `note_history` before deleting.
* Restore: nonce `note_restore`, `restore_note_version($history_id,$case_id)`
  verifies the snapshot belongs to the case.

**Partner panel**: `get_couple_sessions($case, 60)`. Renders session metadata
(date, number, status, side) and the partner case id **only** when
`can_view_partner` is true. Otherwise an explicit refusal message; no counts,
no names.

## Honest scope limits

* Assessments: catalogue only. No public API exists to read results, and direct
  table access is prohibited, so results are intentionally not shown.
* Appointments: read-only. No verified write API for creating/rescheduling.
* Communications: no sending. `pas_send_sms` requires a pre-approved template
  id; inventing one would break the contract.
* Patients: registry exposes no paged search; UI shows latest 50 plus exact
  identifier lookup, and says so.
