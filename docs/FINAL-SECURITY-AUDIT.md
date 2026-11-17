# FINAL SECURITY AUDIT

Scope: all 32 PHP files of `plugins/peyvand-amn-unified-dashboard`, 3,506 lines.
Method: source review + executable static suite (`python3 tests/verify_plugin.py`,
100 checks).

## Controls verified statically

| Control | Evidence | Status |
|---|---|---|
| No `list_all()` | zero matches in plugin; bridge calls `list_for_therapist` | PASS |
| Case authorization before read | `Security::authorized_case()` used by case detail, AJAX note endpoints, REST notes route | PASS |
| Note ownership (IDOR) | case id and note id both validated; Core `update_note/delete_note` re-check `note.case_id` | PASS |
| Partner isolation | `can_view_partner` gate; refusal path renders no data | PASS |
| Capability checks server-side | matrix (`dashboard`/`clinical`/`manage`) enforced in router and REST permissions | PASS |
| Nonce per write | note save/delete/restore, notifications, export, settings — each own action | PASS |
| Nonce ≠ authorization | every nonce check is followed by capability/resource check | PASS |
| No `nopriv` endpoints | zero `wp_ajax_nopriv_*` / `admin_post_nopriv_*` | PASS |
| REST permissions | 3 routes, each `permission_callback`; no POST routes; no `__return_true` | PASS |
| Input handling | all superglobals flow through `PAS_Dashboard_Validation` / `wp_unslash` / nonce / `isset` | PASS |
| Output escaping | no unescaped `echo $var` (layout echo of module HTML is documented and internally escaped) | PASS |
| SQL | only own tables via `$wpdb` with `prepare()`; zero foreign-table SQL | PASS |
| Encryption fail-closed | `add_note` returns `WP_Error` without key; notes are not rendered without key | PASS |
| No secrets in code/logs | no `base64:` keys; audit stores ip hash, not ip | PASS |
| Destructive SQL | only `uninstall.php`, only own tables, only with explicit opt-in | PASS |
| Audit trail | `note.*`, `case.view`, `export.cases`, `settings.save`, `module.*`, `ajax denied` | PASS |

## Audit log content

Columns: `user_id, action, object_type, object_id, result, ip_hash, created_at`.
No note text, no assessment answers, no passwords, tokens or keys. Notification
titles use only `پرونده #<id>`.

## Runtime verification (WordPress 7.1.4, PHP 8.3.32, real Core + Case File)

| Control | How it was exercised | Result |
|---|---|---|
| Cross-therapist IDOR | two `pas_cf_therapist` users, one case each | foreign case → `pas_dashboard_forbidden`; not listed |
| Administrator scope | admin opens either case | allowed |
| Encryption at rest | note written via dashboard bridge | `content_encrypted` has no plaintext |
| Authorized decryption | owning therapist reads note | plaintext returned only for own case |
| Note ownership | update with other case's id | refused by Core API |
| REST authorization | real case ids, logged-in therapist | own 200, foreign 403 |
| Anonymous REST | logged-out request | 401/403 on `/summary` and `/case/{id}/notes` |
| AJAX surface | hook registration | 4 hooks, zero `nopriv` |
| Notification ownership | second user marks another's notification | refused; owner succeeds |
| Audit privacy | audit write | `ip_hash` only, no raw IP, no note text |
| Uninstall authorization | both opt-in states | no clinical data removed |

The PHP error log was empty after all runs.

## Residual risks

1. **Engine and browser not exercised.** MySQL/MariaDB, PHP 7.4 and a real
   browser were unavailable; runtime tests ran on WordPress/SQLite via WP-CLI.
   Live `admin-ajax` nonce rejection and CSV streaming were not invoked.
2. **`Pas_CF_Case_File::get()` decrypts before the ownership check** — the
   plugin's own public API does not expose a raw authorized fetch. The data is
   not emitted unless the check passes, but a stricter Core API would be better.
3. **REST `/case/{id}/notes` returns decrypted note content** to an authorized
   therapist. Audited, capability-gated, and same-origin cookies apply, but it
   widens the surface versus admin-only rendering.
4. **Notifications sync** runs on dashboard load (throttled 15 min/user), so it
   adds queries proportional to the user's first 60 cases.
5. **Reports export** streams up to 500 authorized cases; memory is bounded by
   row-by-row output, not buffered.

## Decision

No critical static or runtime finding. Access control, encryption and REST denial
were verified with two real therapists and two real cases. Production still
requires MySQL/MariaDB and a browser pass. Staging: approved. Production:
CONDITIONAL GO.
