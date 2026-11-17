# FINAL RELEASE READINESS (PHASE TWO)

**Version:** 2.0.0
**Package:** `dist/peyvand-amn-unified-dashboard.zip`
**Decision:** **CONDITIONAL GO** — staging deployment approved; production
pending the mobile-width browser pass and, only if claimed, PHP 7.4.

Tested stacks, identical runtime results on both:

1. PHP 8.3.32, WordPress 7.1.4, SQLite drop-in, encryption key configured —
   Core 1.7.0 / Case File 1.15.3 and again on Core 1.8.1 / Case File 1.16.1 /
   Quiz 3.8.17 / SMS 1.1.7.
2. **PHP 8.5.8, WordPress 7.1.4, MariaDB 11.8.10 (InnoDB, real `mysqli`)** —
   Core 1.8.1 / Case File 1.16.1 / Quiz 3.8.17 / SMS 1.1.7.

205 runtime assertions, 0 failures; module render 26/26 and `php -l` 32/32 on
both engines; plugin PHP error log empty. A desktop admin session was also
inspected in a real browser after the UI upgrade.

## Evidence

| Area | Result |
|---|---|
| `php -l` all files | 32/32 |
| Static + integration suite | 100/100 |
| Validation unit tests | 18/18 |
| Class loading | 59/59 |
| Activation + surface (48 checks) | PASS (SQLite and MariaDB) |
| Clinical integration (32 checks) | PASS (SQLite and MariaDB) |
| Live AJAX authorization (9 checks) | PASS (SQLite and MariaDB) |
| Module rendering (26 checks) | PASS (SQLite and MariaDB) |
| Uninstall (13 checks) | PASS (SQLite and MariaDB) |
| CSV export end-to-end | PASS (SQLite and MariaDB; PHP 8.5: no `fputcsv` deprecation) |
| `dbDelta` DDL + `SHOW CREATE TABLE` on MariaDB | PASS |
| `INSERT … ON DUPLICATE KEY UPDATE` notification upsert | PASS on MariaDB |
| UI upgrade (design tokens, icons, sticky header, a11y) | Rendered at 1024/768/360 px; 22 module×width overflow checks, 0 failures |
| PHP error log | empty |

**205 runtime assertions + 13 database/DDL checks, 0 failures.**

## Security posture (verified)

- Anonymous requests denied on AJAX and REST.
- A therapist cannot read, list, create on, delete from, or update another
  therapist's case; verified with two real accounts and real cases.
- Note content is ciphertext at rest and decrypts only through the authorized
  upstream path.
- Administrators with `PAS_CF_CAP_ALL` see all cases; therapists see only theirs.
- CSV excludes phone/name and neutralises formula injection.
- Uninstall preserves clinical data; opt-in removes only the two own tables.
- No `nopriv` endpoint, no foreign-table SQL, no raw IP in audit.
- The UI upgrade added only markup/icons; capability checks, nonces and queries
  are unchanged.

## Defects fixed in Phase Two

| ID | Severity | Fix |
|---|---|---|
| D13 | High functional | administrators now see all cases (`list_all` behind view-all only) |
| D14 | Medium security | CSV formula injection neutralised |
| D15 | Low hygiene | version option removed on opt-in uninstall |
| D16 | Low forward-compat | explicit `fputcsv()` escape argument (PHP 8.4+ deprecation) |
| D17 | Low test portability | table existence via `SHOW TABLES LIKE`, works on MySQL and SQLite |

## Remaining conditions before production

1. **PHP 7.4**, only if that minimum is claimed: lint + both PHP suites.
2. Couple/partner view with a linked partner case.
3. Multisite, if the deployment is multisite.
4. MySQL 5.7/8.0 if the host runs Oracle MySQL rather than MariaDB (only
   MariaDB 11.8 was exercised).
5. CSV download and the note-form keyboard flow at 360 px, if those flows are
   used on phones (layout itself is verified at 360/768 px).

## Why not unconditional GO

The database engine gate is now closed — `dbDelta`, the MySQL-only upsert, the
unique `dedupe_key`, and Persian `utf8mb4` round-trips are proven on MariaDB.
What remains is coverage, not a known defect: the layout is now verified at
1024/768/360 px with zero horizontal overflow, but PHP 7.4 is untested and the
partner/couple path and multisite are unexercised. These are cheap to close on staging, so a conditional
approval is both honest and useful.

## Why not NO-GO

No critical or high finding remains. The three functional/security defects found
in Phase Two are fixed and re-verified with real data, access control and
encryption held under adversarial tests, uninstall preserves clinical data, and
the package now runs clean on a real MySQL-family server.

## Release checklist (sign-off)

- [x] Backup plan documented (`FINAL-DEPLOYMENT-GUIDE.md`)
- [x] Staging on MariaDB: activation + `dbDelta` + upsert + reinstall idempotency
- [x] Desktop browser pass (1024 px, RTL, header/sidebar/KPI/table/empty state)
- [x] Mobile/tablet browser pass (360/768 px, card tables, zero page overflow)
- [ ] PHP version matches host (7.4 lint if claimed)
- [ ] Partner view spot-check
- [ ] Production deploy with rollback plan
