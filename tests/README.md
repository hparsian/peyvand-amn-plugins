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
| Uninstall | `wp eval-file tests/wp/uninstall.php` | 13/13 |
| CSV export | `wp eval-file tests/wp/export.php > out.csv` | 5/5 shell assertions |
| JS syntax | `node --check` | pass |

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
- **`cleanup-fixtures.php`** removes any leftover synthetic cases/users.

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

- SQLite, not MySQL/MariaDB; no `mysqli` in the available PHP build.
- PHP 8.3 only (declared minimum is 7.4).
- No browser: RTL/mobile layout unverified.
- Partner/couple view and note restore are wired but not exercised.
- Multisite untested.
