# FINAL SECURITY VERIFICATION (PHASE TWO)

Plugin: `peyvand-amn-unified-dashboard` 2.0.0 — independent re-audit of the
actual 32-file tree, then runtime testing on WordPress 7.1.4 / PHP 8.3.32 with
the real **Core 1.7.0**, **Case File 1.15.3**, **Quiz 3.6.7** and **SMS Bridge
1.1.6** active.

**Decision: CONDITIONAL GO** — staging approved; production blocked only on
MySQL/MariaDB and browser verification (see §7).

## 1. Authentication and authorization

| Control | Implementation | Evidence | Result |
|---|---|---|---|
| Dashboard entry | `manage_options` OR `pas_dashboard_access` OR `PAS_CF_CAP` | `class-dashboard-security.php:18-25`; therapist smoke | PASS |
| Clinical data | `manage_options` OR `PAS_CF_CAP` | `can_view_clinical()`; live AJAX + REST | PASS |
| Settings/health/audit | `manage_options` only | `can_manage()`; render test hides them from therapist | PASS |
| Case authorization | `authorized_case()` → upstream `current_user_can_access_case()` | two therapists, two cases | PASS |
| Nonce is not authorization | every nonce check followed by capability + resource check | 4 AJAX handlers, 2 admin-post handlers | PASS |
| No unauthenticated endpoints | zero `wp_ajax_nopriv_*` / `admin_post_nopriv_*` | static scan + hook introspection | PASS |

## 2. Executed security tests

All fixtures were synthetic (two `pas_cf_therapist` users, two cases, one
subscriber); no real patient data. All fixtures deleted afterwards.

| # | Test | Result | Evidence |
|---|---|---|---|
| 1 | Unauthenticated AJAX denied | PASS | `wp_send_json` `success:false` (+401 status) |
| 2 | Anonymous REST denied | PASS | `/summary` and `/case/1/notes` → 401/403 |
| 3 | Invalid nonce denied | PASS | live AJAX call, `success:false` |
| 4 | Logged-in user without dashboard cap denied | PASS | subscriber with valid nonce → denied |
| 5 | Cross-therapist case read denied | PASS | `pas_dashboard_forbidden` |
| 6 | Cross-therapist case list isolation | PASS | foreign case absent from list |
| 7 | Note creation on own case | PASS | note id returned, ciphertext in DB |
| 8 | Note creation on foreign case | PASS | AJAX `success:false`, no row created |
| 9 | Note deletion on foreign case | PASS | AJAX refused |
| 10 | Note deletion on own case | PASS | AJAX success |
| 11 | Note update with mismatched `case_id` | PASS | upstream returns false → refused |
| 12 | Malformed input (`case_id=abc`, bad date) | PASS | refused |
| 13 | Encryption at rest | PASS | `content_encrypted` contains no plaintext |
| 14 | Decryption only via authorized path | PASS | owning therapist receives plaintext; other does not |
| 15 | CSV authorization | PASS | nonce + clinical cap; therapist sees own rows |
| 16 | CSV data minimisation | PASS | no phone, no client name (checked real file) |
| 17 | CSV formula injection | PASS (fixed) | `'=2+2` cell; zero raw `,=2+2` |
| 18 | Administrator sees all cases | PASS (fixed) | list includes both fixtures |
| 19 | Uninstall preserves data (default) | PASS | tables/options intact |
| 20 | Uninstall opt-in removes only own tables | PASS | core tables intact |
| 21 | Notification ownership | PASS | second user cannot mark another's read |
| 22 | Audit privacy | PASS | `ip_hash` only, no note text |
| 23 | Stored XSS in note body | PASS | `nl2br( esc_html( … ) )`; no raw echo in templates |
| 24 | SQL injection | PASS | all dynamic SQL via `prepare()`; only own tables |
| 25 | Disabled feature (notes) | PASS (write gate) | see §5 finding F1 |

## 3. Defects found and fixed in Phase Two

| ID | Severity | Defect | Fix | Regression proof |
|---|---|---|---|---|
| D13 | **High (functional)** | `cases()` always called `list_for_therapist()`, so an administrator with `PAS_CF_CAP_ALL` saw zero cases in Overview, Reports and CSV | branch to `list_all()` only after `current_user_can_view_all()` | integration +2 checks; CSV now returns rows |
| D14 | Medium (security) | CSV formula injection: a therapist display name such as `=2+2` was written verbatim and would execute in Excel | `csv_cell()` prefixes `'` for `= + - @ TAB CR` | 6 unit-level checks + real exported file |
| D15 | Low (hygiene) | opt-in uninstall left `pas_dashboard_version` behind | added `delete_option()` | uninstall suite +2 checks |

## 4. Non-defects verified (to avoid false positives)

| Candidate | Outcome |
|---|---|
| `wp_die( $msg, 403 )` in export/settings | **Not a defect** — WordPress core converts an integer second argument into `response => 403` |
| `Validation::checkbox()` | Strict: only `'1'` is accepted |
| Note body XSS | Escaped before `nl2br()` |
| `enable_notes` checkbox | Strict boolean, not presence-based |
| Export nonce | Same action name generated and verified (`export_cases`) |
| `$wpdb` raw table interpolation | Values are constants from `$wpdb->prefix`; user input always parameterised |

## 5. Residual risks (documented, not silently accepted)

| ID | Risk | Severity | Status |
|---|---|---|---|
| F1 | `enable_notes` gates **writing** only; existing notes remain readable when it is off. The label says "enable recording", so this is defensible but ambiguous | Low | Recommend clarifying the label or gating reads; **not changed** without product decision |
| F2 | REST `/case/{id}/notes` returns decrypted note text to any authorized clinical user (same data as UI) | Low | Audited per request; acceptable |
| F3 | Upstream `Pas_CF_Case_File::get()` decrypts before the access check; the dashboard never emits data before `authorized_case()` passes | Low | Upstream limitation, documented |
| F4 | Phase One reports describe the older 10-file v1.0.0 plugin (single AJAX action, a `nopriv` handler, no REST) and are **stale** | Documentation | Superseded by this report and `FINAL-API-COMPATIBILITY-REPORT.md` |
| F5 | MySQL/MariaDB, PHP 7.4 and browser not executed | Release gate | See §7 |
| F6 | Multisite not tested | Release gate | Single-site only |

## 6. What is proven vs not

**Proven on a real WordPress**, with real upstream plugins and real encrypted
data: activation, schema creation, capability grants, fail-closed behaviour,
anonymous denial, cross-therapist denial on read/write/delete, REST denial,
CSV authorization and minimisation, notification ownership, audit privacy,
uninstall data preservation.

**Not proven:** MySQL/MariaDB DDL and upsert semantics; browser/RTL layout; PHP
7.4 compatibility; multisite.

## 7. Release gate

**CONDITIONAL GO (staging).** Security and functional behaviour passed on the
tested stack. Production requires:

1. One activation + note CRUD on MySQL/MariaDB (production engine).
2. A browser pass at 360/768/1280 px, RTL, including the note form and CSV.
3. If PHP 7.4 is claimed, lint and run the PHP suites on 7.4.

Nothing in this report authorises production deployment on static checks alone.
