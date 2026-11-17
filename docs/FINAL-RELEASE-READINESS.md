# FINAL RELEASE READINESS (PHASE TWO)

**Version:** 2.0.0
**Package:** `dist/peyvand-amn-unified-dashboard.zip`
**Decision:** **CONDITIONAL GO** — staging deployment approved; production
blocked on MySQL/MariaDB and browser verification.

Tested stack: PHP 8.3.32, WordPress 7.1.4, SQLite drop-in, Core 1.7.0,
Case File 1.15.3, Quiz 3.6.7, SMS Bridge 1.1.6, encryption key configured.

## Evidence

| Area | Result |
|---|---|
| `php -l` all files | 32/32 |
| Static + integration suite | 100/100 |
| Validation unit tests | 18/18 |
| Class loading | 59/59 |
| Activation + surface (48 checks) | PASS |
| Clinical integration (32 checks) | PASS |
| Live AJAX authorization (9 checks) | PASS |
| Module rendering (26 checks) | PASS |
| Uninstall (13 checks) | PASS |
| CSV export end-to-end | PASS |
| PHP error log | empty |

**205 runtime assertions, 0 failures.**

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

## Defects fixed in Phase Two

| ID | Severity | Fix |
|---|---|---|
| D13 | High functional | administrators now see all cases (`list_all` behind view-all only) |
| D14 | Medium security | CSV formula injection neutralised |
| D15 | Low hygiene | version option removed on opt-in uninstall |

## Remaining conditions before production

1. **MySQL/MariaDB**: activate the exact ZIP on staging using MySQL, confirm
   `dbDelta` creates both tables, create/read/delete one note, reinstall to
   confirm idempotency, and check `SHOW CREATE TABLE`.
2. **Browser**: 360/768/1280 px, RTL, note form, CSV download, pagination.
3. **PHP 7.4** if that minimum is claimed: lint + both PHP suites.
4. Couple/partner view with a linked partner case.
5. Multisite, if the deployment is multisite.

## Why not unconditional GO

The clinical core is proven on the real plugins, but the production database
engine (MySQL/MariaDB) and a real browser were unavailable. `ON DUPLICATE KEY
UPDATE` in notification sync and `dbDelta` DDL are MySQL-specific and unverified
there; layout is unverified visually. Approving production on static checks
alone is explicitly not allowed.

## Why not NO-GO

No critical finding remains in the tested stack. The three defects found were
fixed and re-verified, access control and encryption held under adversarial
tests with real data, and data preservation on uninstall is proven.

## Release checklist (sign-off)

- [ ] Backup taken
- [ ] Staging on MySQL/MariaDB: activation + note CRUD + reinstall
- [ ] Browser pass (RTL, 3 widths, note form, CSV)
- [ ] PHP version matches host (7.4 lint if claimed)
- [ ] Partner view spot-check
- [ ] Production deploy with rollback plan (`FINAL-DEPLOYMENT-GUIDE.md`)
