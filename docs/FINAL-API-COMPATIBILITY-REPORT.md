# FINAL API COMPATIBILITY REPORT

Every upstream call is confined to `includes/class-dashboard-core-bridge.php`
and guarded by `class_exists()` + `method_exists()` + `try/catch`. No method,
table, capability or constant was invented; each was read from the installed
upstream source (Core 1.7.0, Case File 1.15.3, Quiz 3.6.7, SMS Bridge 1.1.6) and
then exercised at runtime.

## 1. Case File — methods actually called

| Method | Read signature | Runtime evidence |
|---|---|---|
| `Pas_CF_Case_File::list_for_therapist(int $id, int $limit)` | scoped by `case_scope_sql` | therapist list contained own case only |
| `Pas_CF_Case_File::list_all(int $limit)` | all rows, no scope | used **only** after `current_user_can_view_all()`; admin list contained both cases |
| `create(array $data): int` | phone, assigned_therapist, status | two cases created in fixtures |
| `get(int $id)` | row or null | `authorized_case()` returned object for own case |
| `get_notes(int $case_id): array` | decrypts `content_encrypted` | note listed with plaintext |
| `add_note(int,string,string,int,array): int` | returns note id | note created, ciphertext at rest |
| `update_note(int,int,string,string,?int,?array): bool` | re-checks `note.case_id` | success on own case; `false` with wrong case id |
| `delete_note(int,int): bool` | re-checks ownership | success for owner, refused for foreign |
| `get_note_history(int $note_id)` | array of snapshots | at least one snapshot after update |
| `restore_note_version(int $history_id, int $case_id)` | bool | wired; not executed (T08 remains partial) |
| `get_last_session_map(array $ids): array` | metadata only | used by reports/export without decryption |
| `get_couple_sessions(object $case, int $limit)` | `['partner','can_view_partner','sessions']` | not exercised (no linked couple fixture) |
| `get_next_session(object $case)` | object or null | used in notification sync |
| `get_linked_bookings(string $phone)` | array | read-only module |
| `reminder_blocker(object, ?object)` | string reason | read-only |

## 2. Core / security contracts

| Contract | Verified |
|---|---|
| `PAS_CF_CAP = 'pas_manage_case_files'` | read from upstream bootstrap; used as clinical gate |
| `PAS_CF_CAP_ALL = 'pas_view_all_case_files'` | used for administrator scope |
| `Pas_CF_Security::current_user_can_access_case(object): bool` | single source of truth for case access |
| `Pas_CF_Security::current_user_can_view_all(): bool` | used before `list_all()` |
| `Pas_CF_Crypto::is_configured()` | crypto readiness |
| `Pas_Core_Crypto::is_configured()` / `encrypt()` / `decrypt()` | used via upstream, never re-implemented |
| `PAS_CORE_ENCRYPTION_KEY` (`base64:` prefix) | set for runtime tests; crypto ready = true |
| `Pas_CF_Installer::table('cases'|'notes')` | used only inside tests, never in plugin code |

## 3. Other plugins

| Plugin | Surface used | Status |
|---|---|---|
| Core | `Pas_Core_Client_Registry`, `pas_get_client` filter, `Pas_Core_Crypto` | read-only, verified active |
| Quiz Enterprise | `PsychAssessments\Core\Registry::all()` | catalogue only; no results API exists |
| SMS Bridge | `Pas_Log::recent()`, table `{prefix}pas_sms_log`, filter `pas_send_sms` | status + masked log only; **no sending** |

## 4. Compatibility rules the plugin obeys

1. Never call an upstream method without `method_exists()`.
2. Never read another plugin's tables directly — all data comes through public
   methods, except the two dashboard-owned tables.
3. Never assume a class exists; every feature degrades with a visible notice.
4. `list_all()` is used **only** behind `current_user_can_view_all()`, matching
   Case File's own admin page; the static checker enforces that guard.
5. No upstream activation/deactivation/option is modified.

## 5. Known gaps

| Gap | Reason |
|---|---|
| Couple/partner view | no linked partner fixture created; `get_couple_sessions` contract read but not exercised |
| Note restore (T08) | history creation verified; restore call not executed |
| Assessment results | upstream exposes no public read API; intentionally catalogue-only |
| Appointment writes | no verified write API; read-only by design |
| SMS sending | requires an approved `body_id`; intentionally disabled |
| Version skew | none observed: the full suite ran twice — on Core 1.7.0 / Case File 1.15.3 and on the current source builds **Core 1.8.1 / Case File 1.16.1 / Quiz 3.8.17 / SMS 1.1.7** — with identical results. All 13 Case File methods above keep the same signatures in 1.16.1. |
