# FINAL DEPENDENCY REPORT

## Required / optional

| Dependency | Class or contract used | Required? | Missing behaviour |
|---|---|---|---|
| pivand-amn-core ≥ 1.8.1 | `Pas_Core_Client_Registry`, `Pas_Core_Crypto`, `Pas_Core_Jalali` | effectively yes | stat card zero, patients says unavailable, crypto false |
| pivand-amn-case-file ≥ 1.16.1 | `Pas_CF_Case_File`, `Pas_CF_Security`, `Pas_CF_Crypto` | effectively yes | case/appointment/report modules warn and stop |
| pivand-amn-quiz-enterprise | `PsychAssessments\Core\Registry::all()` | optional | catalogue warning |
| pivand-amn-sms-bridge | `pas_send_sms` filter, `Pas_Log::recent()` | optional | status inactive, no log table |
| pivand-amn-client-portal | `get_linked_bookings` via Case File | optional | empty bookings + note |

No Composer/npm dependency. No CDN. No external API.

## Verified signatures used

```php
Pas_CF_Case_File::list_for_therapist(int $therapist_id, int $limit = 200): array
Pas_CF_Case_File::get(int $case_id): ?object
Pas_CF_Case_File::get_couple_sessions(object $case, int $limit = 60): array
Pas_CF_Case_File::get_next_session(object $case): ?object
Pas_CF_Case_File::reminder_blocker(object $case, ?object $next): string
Pas_CF_Case_File::get_last_session_map(array $case_ids): array
Pas_CF_Case_File::get_notes(int $case_id): array
Pas_CF_Case_File::add_note(int, string, string, int, array): int
Pas_CF_Case_File::update_note(int, int, string, string): bool
Pas_CF_Case_File::delete_note(int, int): bool
Pas_CF_Case_File::get_note_history(int): array
Pas_CF_Case_File::restore_note_version(int, int): bool
Pas_CF_Case_File::get_linked_bookings(string $phone): array
Pas_CF_Security::current_user_can_access_case(object $case): bool
Pas_Core_Client_Registry::stats(): array
Pas_Core_Client_Registry::sample_clients(int): array
Pas_Core_Client_Registry::get_client(string): ?array
Pas_Core_Client_Registry::get_client_by_identifier(string,string): ?array
Pas_Core_Crypto::is_configured(): bool
Pas_Core_Jalali::to_jalali_string(string): string
apply_filters('pas_get_client', null, array $query)
apply_filters('pas_send_sms', null, string $mobile, int $body_id, array $params)
PsychAssessments\Core\Registry::all(): array<TestInterface>
Pas_Log::recent(int $limit): array
```

Each call is guarded by `class_exists` + `method_exists`, wrapped in
`try/catch (Throwable)` where the callee can throw (note writes, registry
iteration, Jalali conversion).

## Version safety

Dashboard declares `Requires PHP 7.4` and uses no PHP 8-only syntax. Core/quiz
files use typed properties (PHP 7.4+), compatible.
