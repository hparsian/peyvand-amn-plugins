# FINAL API INTEGRATION REPORT

## Principle

`PAS_Dashboard_Core_Bridge` is the only file that touches other plugins. It
never queries their tables; it calls documented public statics and filters, and
degrades to empty values when a class/method is absent. No method was invented.

## Integration map

| Consumer | Bridge method | Upstream API | Failure mode |
|---|---|---|---|
| Overview | `cases`, `next_session`, `client_stats` | `list_for_therapist`, `get_next_session`, `stats` | zeros + warning |
| Patients | `clients_sample`, `client_get`, `client_by_identifier` | registry statics | "registry unavailable" |
| Case files | `case_get`, `can_access_case`, `notes`, `add_note`, `update_note`, `delete_note`, `note_history`, `restore_note_version` | Case File statics + `Pas_CF_Security` | `WP_Error`/false, generic message |
| Partner panel | `couple_sessions` | `get_couple_sessions` | empty + `can_view_partner=false` |
| Appointments | `next_session`, `bookings` | `get_next_session`, `get_linked_bookings` | empty table |
| Reports | `cases`, `get_last_session_map`, `client_stats` | Case File + registry | zeros |
| Communications | `sms_available`, `sms_recent`, `reminder_blocker` | `has_filter`, `Pas_Log::recent`, Case File | inactive status |
| Assessments | `assessments` | `Registry::all()` | catalogue warning |
| System health | `has_*`, `crypto_ready` | class/function checks | reported as warning |

## Contracts deliberately not used

| Contract | Why not |
|---|---|
| `pas_send_sms` for sending | needs an approved template `body_id`; inventing one violates the contract and could send wrong clinical content |
| quiz result queries | no public read API; direct table access prohibited |
| portal booking writes | no verified write API |
| `list_all()` | returns every case; prohibited |
| `$wpdb` on `pas_cf_*` | ownership violation |

## Cross-plugin safety

* Capabilities are read, never redefined (`PAS_CF_CAP`, `PAS_CF_CAP_ALL`).
* Only additive cap: `pas_dashboard_access`.
* No filter removes or reorders upstream behaviour; dashboard adds none.
* REST namespace is isolated: `pas-dashboard/v1`.
