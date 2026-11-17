# FINAL DEFECT REGISTER

Defects found during implementation self-review and their fixes. All were fixed
before packaging; each fix was followed by the static suite
(`python3 tests/verify_plugin.py`, 100/100).

| ID | Defect | Severity | File | Fix | Regression check |
|---|---|---|---|---|---|
| D01 | Overview "پرونده" link pointed at the overview module, so `case_id` was ignored | Medium | `modules/overview-module.php` | link now targets `module=case_files` | suite: structure + link review |
| D02 | CSV export decrypted every case's notes just to count them | High (perf/privacy) | `class-dashboard-export.php` | replaced with `get_last_session_map` metadata | suite: forbidden/ownership |
| D03 | Reports looped `get_notes()` per case to total sessions | High (perf) | `modules/reports-module.php` | single metadata map; stat renamed "پرونده دارای جلسه" | suite |
| D04 | Settings save persisted the filtered settings array | Low | `modules/settings-module.php` | read raw `get_option` + defaults on save | suite |
| D05 | Note delete/restore did not honour `enable_notes` feature flag | Medium | `class-dashboard-ajax.php` | added `notes_writable()` guard to both | suite: ajax audit |
| D06 | Nonce read and settings checkboxes used raw superglobals | Low | `class-dashboard-security.php`, `modules/settings-module.php` | `sanitize_text_field(wp_unslash())`, `Validation::checkbox()` / `flag()` | suite: input audit |
| D07 | Notification sync ran on every dashboard load | Medium (perf) | `class-dashboard-router.php` | 15-minute per-user transient | suite |
| D08 | `wp_die()` passed `403` as the title argument | Low | `class-dashboard-security.php` | `array('response' => 403)` | suite |
| D09 | Existing installs with therapist roles could not see the menu | Medium | `class-dashboard-permissions.php`, `class-dashboard-database.php` | grant `pas_dashboard_access` to roles holding `pas_manage_case_files` on upgrade | suite |
| D10 | Header bell linked to a module the user may not see | Low | `templates/header.php` | link only when `Permissions::can('notifications')` | suite |
| D11 | Note/filter UI had no styling and lists lost markers | Low | `assets/css/dashboard-admin.css` | added `.pas-note*`, `.pas-list`, `.pas-filter-form`, mobile rules | visual: not executed |
| D12 | `Validation::id( '-5' )` returned `5`, so a negative identifier could be normalised onto a different record | Medium | `includes/class-dashboard-validation.php` | cast and reject non-positive values instead of `absint()` | unit test + load test (found by running the PHP suite) |

## Phase Two defects (found in independent re-audit + runtime testing)

| ID | Defect | Severity | File | Fix | Regression check |
|---|---|---|---|---|---|
| D13 | `cases()` always used `list_for_therapist()`, so an administrator with `PAS_CF_CAP_ALL` saw **zero** cases in Overview, Reports and CSV | High (functional) | `class-dashboard-core-bridge.php` | branch to `list_all()` only after `current_user_can_view_all()`; added `viewer_can_see_all_cases()` | integration +2 checks; real CSV now returns rows; static checker permits `list_all` only behind the view-all guard |
| D14 | CSV formula injection: a display name like `=2+2` was written verbatim and would execute in Excel | Medium (security) | `class-dashboard-export.php` | `csv_cell()` prefixes `'` for `= + - @ TAB CR`, applied to header and every row | 6 smoke checks + real exported file (`'=2+2`, zero raw `,=2+2`) |
| D15 | Opt-in uninstall left `pas_dashboard_version` behind | Low (hygiene) | `uninstall.php` | added `delete_option( 'pas_dashboard_version' )` | uninstall suite +2 checks |
| D16 | `fputcsv()` was called without the `$escape` argument; PHP 8.4 deprecates that and PHP 9 changes the default, so CSV export would emit notices and could change quoting on newer hosts | Low (forward compatibility) | `class-dashboard-export.php` | explicit `fputcsv( $out, $row, ',', '"', '' )` (RFC 4180 behaviour) for header and rows | export on PHP 8.5: zero `fputcsv` deprecations, 1 neutralised cell, 0 raw |
| D17 | `tests/wp/smoke.php` and `tests/wp/uninstall.php` read table names from `sqlite_master`, so the dbDelta assertions failed on MariaDB although the tables existed | Low (test portability) | `tests/wp/*` | `SHOW TABLES LIKE` via `$wpdb->prepare()` | smoke 48/48 and uninstall 13/13 on both MariaDB and the SQLite drop-in |
| D18 | Skip link hidden with `inset-inline-start: -9999px` created a ~10 000 px horizontal page scroll in RTL, shifting the whole admin | Medium (layout) | `assets/css/dashboard-admin.css` | hidden with `transform: translateY(-120%)` + `opacity: 0` + `pointer-events: none`, revealed on `:focus` | `scrollWidth == clientWidth` on all 11 modules at 360/768 px |
| D19 | Tables were clipped mid-column at 360 px and demanded blind horizontal scrolling | Medium (layout/mobile) | 11 module files + CSS | rows become labelled cards below 600 px: `data-label` on every cell (`esc_attr__()`), `thead` visually hidden, `td::before { content: attr(data-label) }` | 360 px screenshots, zero overflow on 11 modules |
| D20 | Table overflowed by 3 px at 768 px, producing a scrollbar | Low (layout) | `assets/css/dashboard-admin.css` | cell padding 10×11 px at ≤ 900 px | 768 px: wrapper 659 px, table fits, no scrollbar |

## Phase Two findings (not changed; documented)

| ID | Finding | Why not changed |
|---|---|---|
| F1 | `enable_notes` gates writing only; existing notes stay readable when it is off | the setting label says "enable recording"; gating reads would be a product decision |
| F2 | REST `/case/{id}/notes` returns decrypted text to any authorized clinical user | same data as the UI, capability- and resource-gated, audited per request |
| F3 | upstream `get()` decrypts before the access check | upstream behaviour; dashboard never emits data before authorization |
| F4 | Phase One reports describe the older 10-file v1.0.0 plugin | documentation only; superseded by the Phase Two reports |
| F5 | PHP 7.4, mobile-width browser matrix, multisite untested | environment limits (only PHP 8.3/8.5 and one desktop viewport available); listed as release conditions. MySQL/MariaDB and a desktop browser pass are now executed — see `FINAL-DATABASE-VERIFICATION.md` §5 and `FINAL-UI-UPGRADE.md` §4 |

## Open defects

No known critical or high-severity defect remains in the tested stack. Items F1–F3
are documented risks; F5 is the remaining release gate. See
`FINAL-RELEASE-READINESS.md` for the exact staging checks.
