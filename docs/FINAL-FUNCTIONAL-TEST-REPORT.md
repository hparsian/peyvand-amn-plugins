# FINAL FUNCTIONAL TEST REPORT (PHASE TWO)

Environment: PHP **8.3.32**, WordPress **7.1.4**, SQLite drop-in **1.8.0**, plus
the real **Core 1.7.0**, **Case File 1.15.3**, **Quiz 3.6.7**, **SMS Bridge
1.1.6**. `PAS_CORE_ENCRYPTION_KEY` configured. No MySQL server, no browser.

**Second full run on the current source builds** (Core **1.8.1**, Case File
**1.16.1**, Quiz **3.8.17**, SMS Bridge **1.1.7**, all built from `plugins/`)
produced identical results: smoke 48/48, integration 32/32, AJAX 9/9, render
26/26, uninstall 13/13, CSV 5/5, lint 32/32, unit 18/18, load 59/59, static
100/100, empty PHP error log.

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


## Round 3 — MariaDB + UI upgrade (2026-11-17)

Executed on **PHP 8.5.8, WordPress 7.1.4, MariaDB 11.8.10** with the upstream
Core 1.8.1 / Case File 1.16.1 / Quiz 3.8.17 / SMS 1.1.7 activated (`mysqli`,
real InnoDB tables, encryption key configured):

| Suite | Result |
|---|---|
| Smoke / activation surface | 48/48 |
| Clinical integration | 32/32 |
| AJAX authorization | 9/9 |
| Module rendering | 26/26 |
| Uninstall | 13/13 |
| CSV export | 4 rows, 1 neutralised cell, 0 raw, no phone column |
| `php -l` | 32/32 |
| Static + integration suite | 100/100 |

Two harness defects were found and fixed on this run: D16 (`fputcsv()` without
an explicit escape argument, deprecated from PHP 8.4) and D17 (smoke/uninstall
queried `sqlite_master`). After the fixes every suite above passed on MariaDB and
the same suites re-passed unchanged on the SQLite drop-in (48/32/9/26/13).

The presentation layer was upgraded afterwards (see `FINAL-UI-UPGRADE.md`);
render 26/26, lint 32/32 and the static suite were re-run green, and the page was
inspected in a logged-in desktop browser session
(`artifacts/dashboard-ui-overview.png`). Mobile widths remain unverified.

### Round 3b — responsive pass (360 / 768 px)

Six synthetic cases were created on the MariaDB site and the dashboard was
driven in a real Chromium session at 360, 768 and 1024 px. Three layout defects
were found and fixed (D18 off-screen skip link causing a ~10 000 px RTL
horizontal scroll, D19 clipped tables at 360 px, D20 3 px table overflow at
768 px). After the fixes, `documentElement.scrollWidth == clientWidth` on all
eleven modules at both 360 px and 768 px (22 checks, 0 overflow), tables render
as labelled cards on phones, and the 768 px table shows all seven columns with
no scrollbar. Screenshots: `artifacts/ui-360-after.png`,
`artifacts/ui-768-after.png`.

## Round 4 — PHP 7.4 (minimum supported version)

PHP **7.4.33** (CLI, mysqli/pdo_mysql/sqlite3/mbstring/json/curl/phar) was
installed next to the 8.3 and 8.5 runtimes.

| Check | Result |
|---|---|
| `php -l` all 32 plugin files | 32/32 |
| `tests/php/test-validation.php` | 18/18 |
| `tests/php/test-load.php` | 59/59 |
| Smoke / activation surface | 48/48 |
| Module rendering | 24/24 |
| Uninstall | 13/13 |
| Plugin error log | empty |
| Static suite | 100/100 |

The WordPress suites were necessarily run in **standalone mode**: Core 1.8.1 and
Case File 1.16.1 declare `Requires PHP: 8.1` and use `?->`, so activating them on
7.4 is a fatal parse error in upstream code (`class-admin.php` line 477) before
the dashboard loads. Clinical integration (32 checks), AJAX (9) and CSV export
therefore cannot be executed on 7.4 and remain verified on PHP 8.3/8.5 only.

Two test-harness defects surfaced in this pass and were fixed:

- **D21** `tests/wp/uninstall.php` was not idempotent: scenario B deletes the
  options and drops the tables, so a second run failed "settings survive" and
  "tables exist". The test now recreates its prerequisites. Verified by running
  it twice back to back on 7.4 (13/13 both times) and again on 8.5 with the
  upstream plugins active (13/13).
- **D22** `tests/wp/render.php` required `case_files` to be visible for a
  therapist, which is only true while Case File supplies
  `pas_manage_case_files`; without it the module is hidden by design. The check
  now asserts the correct expectation for each case.

After re-activating the four upstream plugins, the full suite was re-run on
PHP 8.5.8/MariaDB and stayed green: smoke 48, integration 32, AJAX 9, render 26,
uninstall 13, unit 18+59, static 100.
