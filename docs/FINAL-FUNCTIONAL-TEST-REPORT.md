# FINAL FUNCTIONAL TEST REPORT (PHASE TWO)

Environment: PHP **8.3.32**, WordPress **7.1.4**, SQLite drop-in **1.8.0**, plus
the real **Core 1.7.0**, **Case File 1.15.3**, **Quiz 3.6.7**, **SMS Bridge
1.1.6**. `PAS_CORE_ENCRYPTION_KEY` configured. No MySQL server, no browser.

## Executed totals

| Suite | Command | Result |
|---|---|---|
| Static / integration | `python3 tests/verify_plugin.py` | **100 / 100** |
| PHP syntax | `php -l` × 32 | **32 / 32** |
| Validation unit | `tests/php/test-validation.php` | **18 / 18** |
| Class loading | `tests/php/test-load.php` | **59 / 59** |
| Activation + surface | `tests/wp/smoke.php` | **48 / 48** |
| Clinical integration | `tests/wp/integration.php` | **32 / 32** |
| Live AJAX authorization | `tests/wp/ajax-permissions.php` | **9 / 9** |
| Module rendering | `tests/wp/render.php` | **26 / 26** |
| Uninstall behaviour | `tests/wp/uninstall.php` | **13 / 13** |
| CSV export (real file) | `tests/wp/export.php` + shell assertions | 5 / 5 |
| JS syntax | `node --check` | pass |
| PHP error log | every run | empty |

**205 automated runtime assertions + 132 static/lint assertions, 0 failures.**

## Phase C coverage (security tests)

| # | Required test | Result |
|---|---|---|
| 1 | Unauthenticated access denial | PASS (AJAX 401, REST 401/403) |
| 2 | Unauthorized therapist denial | PASS (`pas_dashboard_forbidden`) |
| 3 | Cross-patient IDOR | PASS (foreign case not readable/listed) |
| 4 | Cross-therapist isolation | PASS (list scoped) |
| 5 | Note creation authorization | PASS (own only) |
| 6 | Note editing authorization | PASS (wrong case id refused) |
| 7 | Note deletion authorization | PASS (foreign refused, own allowed) |
| 8 | Note restoration authorization | partial — history verified, restore call not executed |
| 9 | Disabled feature enforcement | PASS for write gate; reads unaffected (F1) |
| 10 | AJAX nonce validation | PASS (live, invalid nonce denied) |
| 11 | REST permission enforcement | PASS (anonymous 401/403, therapist scoped) |
| 12 | CSV export authorization | PASS (nonce + clinical cap) |
| 13 | Sensitive data exclusion | PASS (no phone/name in real file) |
| 14 | Encryption integrity | PASS (ciphertext at rest) |
| 15 | Plaintext fallback prevention | PASS (no write/read without key) |
| 16 | SQL injection resistance | PASS (prepared statements only) |
| 17 | XSS resistance | PASS (escaped note body; no raw echoes) |
| 18 | Uninstall preservation | PASS (both modes) |

## Phase D coverage (11 modules)

All modules were rendered against real data: **11/11 for administrator**,
**8/8 permitted for therapist** (settings/audit/health hidden), no PHP notices.

| Module | Render | Permissions | Data / empty state |
|---|---|---|---|
| Overview | PASS | PASS | real counts |
| Patients | PASS | PASS | identifier search only |
| Case Files | PASS | PASS | notes + history rendered |
| Assessments | PASS | PASS | catalogue; empty state if plugin absent |
| Appointments | PASS | PASS | read-only |
| Reports | PASS | PASS | stats + CSV |
| Communications | PASS | PASS | masked log only |
| Notifications | PASS | PASS | per-user |
| System Health | PASS | PASS (admin) | dependency states |
| Settings | PASS | PASS (admin) | save path audited |
| Audit | PASS | PASS (admin) | own-table rows |

Not exercised visually: mobile breakpoints, RTL layout in a real browser,
pagination clicks, filters via UI. These remain **unverified**.

## Phase B environment

- PHP 8.3.32 static CLI installed in the user home.
- WordPress 7.1.4 + official SQLite drop-in (no MySQL server, no `mysqli`).
- Upstream plugins installed from `dist/` and activated.
- No production system was touched; all fixtures synthetic and deleted after
  each run (`tests/wp/cleanup-fixtures.php`).

## Remaining unexecuted tests

| Test | Reason |
|---|---|
| T08 note restore | only history creation exercised |
| T11 export without nonce (negative) | not invoked live |
| T13 partner/couple view | no linked partner fixture |
| T14/T15 handler-level settings/audit denial | module hidden, handler not invoked |
| T18 mobile/RTL | no browser |
| MySQL/MariaDB behaviour | no server, no `mysqli` |
| PHP 7.4 compatibility | only 8.3 available |
| Multisite | single site |

## Conclusion

All required functional and security behaviours that the environment can
exercise passed after fixing three real defects (D13–D15). The plugin activates,
degrades safely without dependencies, enforces access control on read and write,
protects encrypted data, and preserves data on uninstall. Browser, MySQL and
PHP 7.4 verification remain outstanding and are stated as such.
