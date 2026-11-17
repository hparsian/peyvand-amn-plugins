# FINAL 44-ISSUES RECONCILIATION

Exactly 44 unique issues. Status vocabulary is restricted to the five allowed
values. "Verified" means static evidence in this repository; runtime is called
out separately.

| ID | Issue | Status | Evidence / reason |
|---|---|---|---|
| 1 | payvand-contact not audited | NOT APPLICABLE WITH EVIDENCE | no dependency in bridge |
| 2 | smtp-mailer not audited | NOT APPLICABLE WITH EVIDENCE | no dependency |
| 3 | 2FA plugin not audited | NOT APPLICABLE WITH EVIDENCE | no dependency |
| 4 | daily-feed not audited | NOT APPLICABLE WITH EVIDENCE | no dependency |
| 5 | content-writer not audited | NOT APPLICABLE WITH EVIDENCE | no dependency |
| 6 | SMS bridge contract unverified | PARTIALLY FIXED | filter + log read; no send |
| 7 | client portal identity unclear | FIXED BUT NOT FULLY VERIFIED | client_id + `pas_get_client`; runtime untested |
| 8 | `handle_export_csv` uninspected | FIXED BUT NOT FULLY VERIFIED | dashboard CSV reimplemented minimally, nonce+cap |
| 9 | `handle_print_case` uninspected | OPEN | printing intentionally not implemented |
| 10 | Core filter registry | FIXED BUT NOT FULLY VERIFIED | `pas_get_client` used per contract |
| 11 | registry rebuild complexity | NOT APPLICABLE WITH EVIDENCE | dashboard never rebuilds registry |
| 12 | quiz REST callbacks | PARTIALLY FIXED | catalogue via `Registry::all()` only |
| 13 | quiz AJAX security | NOT APPLICABLE WITH EVIDENCE | dashboard registers no quiz AJAX |
| 14 | SMS provider APIs | PARTIALLY FIXED | status + masked log only |
| 15 | portal OTP auth | OPEN | out of Phase Two scope |
| 16 | non-core DB schemas | FIXED AND VERIFIED | suite: zero foreign-table SQL |
| 17 | CSRF matrix | FIXED AND VERIFIED | per-action nonces + caps |
| 18 | export implementation | FIXED BUT NOT FULLY VERIFIED | streamed CSV; runtime untested |
| 19 | print implementation | OPEN | deferred |
| 20 | `psy_get_pending_quizzes_for_phone` | OPEN | no public API |
| 21 | `psy_get_client_name_for_phone` | OPEN | no public API |
| 22 | `zdp_get_intake_for_phone` | OPEN | no public API |
| 23 | `pas_lookup_known_client_name` | FIXED BUT NOT FULLY VERIFIED | via `pas_get_client` |
| 24 | `psy_get_completed_results_for_phone` | OPEN | no public API |
| 25 | `psy_get_assessment_scores_for_phone` | OPEN | intentionally not exposed |
| 26 | appointment API | PARTIALLY FIXED | read-only next session + bookings |
| 27 | booking conflict detection | OPEN | no write path |
| 28 | report authorization | FIXED AND VERIFIED | clinical cap + scoped listing |
| 29 | export minimisation | FIXED AND VERIFIED | no names/phones in CSV |
| 30 | audit trail | FIXED AND VERIFIED | write/read exercised on WordPress; ip_hash only |
| 31 | cases schema semantics | NOT APPLICABLE WITH EVIDENCE | API-only access |
| 32 | notes encryption | FIXED AND VERIFIED | ciphertext at rest; plaintext only via authorized read |
| 33 | note history/restore | FIXED BUT NOT FULLY VERIFIED | Core API used |
| 34 | audit-log table | FIXED AND VERIFIED | created by dbDelta; write/read verified |
| 35 | quiz portal schema | NOT APPLICABLE WITH EVIDENCE | no direct access |
| 36 | portal schema | NOT APPLICABLE WITH EVIDENCE | no direct access |
| 37 | appointment schema | NOT APPLICABLE WITH EVIDENCE | no direct access |
| 38 | dashboard integration map | FIXED AND VERIFIED | architecture + bridge |
| 39 | role/capability matrix | FIXED AND VERIFIED | `Permissions::matrix()` |
| 40 | dependency graph | FIXED AND VERIFIED | System Health checks |
| 41 | export authorization | FIXED AND VERIFIED | nonce + clinical cap |
| 42 | AJAX handler gaps | FIXED AND VERIFIED | 4 endpoints, guard+resource auth |
| 43 | export data minimisation | FIXED AND VERIFIED | metadata-only columns |
| 44 | `decrypt_or_plain` legacy | FIXED BUT NOT FULLY VERIFIED | no decrypt without key |

## Totals

Counted mechanically from the table above (`awk` on column 4), not hand-tallied.

| Status | Count |
|---|---|
| FIXED AND VERIFIED | 13 |
| FIXED BUT NOT FULLY VERIFIED | 7 |
| PARTIALLY FIXED | 4 |
| OPEN | 9 |
| NOT APPLICABLE WITH EVIDENCE | 11 |
| **Total** | **44** |

"FIXED AND VERIFIED" now includes runtime evidence where it exists: encryption
at rest (#32), the audit table (#30, #34) and access control were exercised on
WordPress 7.1.4 with the real Core and Case File plugins. Items still marked
unverified lack a MySQL engine, a browser, or an upstream flow that was not
exercised. See `FINAL-FUNCTIONAL-TEST-REPORT.md` for the exact checks.
