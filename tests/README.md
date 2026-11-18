# Test evidence — Peyvand Amn Unified Dashboard

All suites below were executed in the delivery sandbox (PHP 8.3.32, WordPress
7.1.4, SQLite drop-in 1.8.0, real Core/Case File/Quiz/SMS plugins).

| Suite | Command | Result |
|---|---|---|
| Static + integration | `python3 tests/verify_plugin.py` | 100/100 |
| PHP syntax | `php -l` on every file | 32/32 |
| Validation unit | `php tests/php/test-validation.php` | 18/18 |
| Class loading | `php tests/php/test-load.php` | 59/59 |
| Activation + surface | `wp eval-file tests/wp/smoke.php` | 48/48 |
| Clinical integration | `wp eval-file tests/wp/integration.php` | 32/32 |
| Live AJAX authorization | `wp eval-file tests/wp/ajax-permissions.php` | 9/9 |
| Module rendering | `wp eval-file tests/wp/render.php` | 26/26 |
| Partner/couple path | `wp eval-file tests/wp/partner.php` | 52/52 |
| Uninstall | `wp eval-file tests/wp/uninstall.php` | 13/13 |
| CSV export | `wp eval-file tests/wp/export.php > out.csv` | 5/5 shell assertions |
| JS syntax | `node --check` | pass |
| Jalali parity (standalone) | `php tests/php/test-jalali.php` | 21/21 — 55,152 روز، صفر اختلاف |
| Jalali + timezone (WP) | `wp eval-file tests/wp/jalali-timezone.php` | 10/10 |
| Client intake validation (WP) | `wp eval-file tests/wp/intake-validation.php` | 21/21 |

The whole table was executed a second time against builds made from the current
`plugins/` source — **Core 1.8.1, Case File 1.16.1, Quiz 3.8.17, SMS Bridge
1.1.7** — with identical results, so the dashboard is compatible with both the
pinned `dist/` packages and the newer source.

## Notes on the trickier suites

- **`ajax-permissions.php`** defines `DOING_AJAX` and replaces every `wp_die`
  handler with one that throws, so `wp_send_json` does not kill the process.
  It exercises anonymous access, invalid nonce, missing capability, own-case
  note creation/deletion, foreign-case refusal and malformed input.
- **`export.php`** runs in its own process because the CSV handler ends with
  `exit`; the shell then checks BOM, header, data rows, formula neutralisation
  and absence of the fixture phone number.
- **`integration.php`** creates two therapists and two cases through the real
  API, verifies isolation, encryption at rest, note lifecycle, REST denial and
  administrator scope, then deletes every fixture.
- **`partner.php`** links two real cases (A→B, bidirectional) through
  `partner_phone`, puts a note on each side, then checks the boundary from three
  identities: the owning therapist sees `can_view_partner=false`, no partner
  sessions, no partner row, no partner note, and the blocked-partner notice in
  the rendered dashboard; the partner's therapist gets the same denial in the
  other direction; the administrator sees both sides but never note text in the
  couple projection. It also exercises relink, self-link, unknown-phone and
  unlink, REST denial, and a static guard that the dashboard never calls
  upstream `get_partner()` directly. 52/52 on both MariaDB and SQLite.
- **`tests/php/test-jalali.php`** runs without WordPress. It loads the three
  Jalali converters (core, case file, client panel) and compares them for
  every day from 1300/01/01 to 1450/12/29 in both directions, then checks
  reference dates, Persian/Arabic digits, separators and invalid input. Any
  divergence fails the run, so an algorithm change cannot silently shift
  stored dates.
- **`tests/wp/intake-validation.php`** calls the private `Intake::validate()`
  through Reflection and asserts every rule of the client intake form: future
  birth dates are rejected (a real bug fixed in 1.13.2.1 — `diff()` is
  absolute, so age 0 slipped through), age > 120, invalid Jalali dates,
  Persian digits, required enums, children range, mobile format, address,
  confirmation, health options (invalid value rejected, details cleared on
  «خیر»), and the Jalali `form_date`. It skips cleanly when the client portal
  is not active.
- **`tests/wp/jalali-timezone.php`** proves inside WordPress that `wp_date()`
  returns site time while `date()` on a UTC server is off by the site offset
  (Tehran, +3:30), that `pas_jalali_datetime()` agrees with an independent
  Jalali calculation of `current_time()`, and that the content-writer
  scheduler accepts `1405/08/29 14:30` (Latin or Persian digits) as well as
  the legacy `datetime-local` value. It restores the original timezone option
  before exiting.

## Environment setup (reproducible)

```
curl -sSL https://dl.static-php.dev/static-php-cli/common/php-8.3.32-cli-linux-x86_64.tar.gz \
  | tar -xz -C ~/.local/php
curl -sSL https://wordpress.org/latest.tar.gz | tar -xz -C /tmp/wp --strip-components=1
unzip -q sqlite-database-integration.zip -d /tmp/wp/wp-content/plugins
cp /tmp/wp/wp-content/plugins/sqlite-database-integration/db.copy /tmp/wp/wp-content/db.php
# wp-config: DB_ENGINE=sqlite, DB_DIR=/tmp/wp/wp-content/database/, DB_FILE=.ht.sqlite
wp core install --path=/tmp/wp --url=http://127.0.0.1:8099 ...
wp plugin install dist/pivand-amn-core-1.7.0.zip dist/pivand-amn-case-file-1.15.3.zip --activate
wp plugin activate peyvand-amn-unified-dashboard
# PAS_CORE_ENCRYPTION_KEY = base64:<32 random bytes>
```

## Limits

Superseded by the later passes documented in `docs/FINAL-FUNCTIONAL-TEST-REPORT.md`:
MariaDB 11.8 (PHP 8.5), the 360/768 px browser matrix and PHP 7.4 standalone
runs are all executed; the partner/couple path is covered by `partner.php`.
What is still untested: note restore (only history creation is exercised),
multisite, and Oracle MySQL 8.0 (only MariaDB was exercised).
