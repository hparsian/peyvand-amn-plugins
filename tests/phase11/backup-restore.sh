#!/usr/bin/env bash
# فاز ۱۱ — آزمون بازیابی بکاپ (MariaDB/MySQL واقعی).
#
#   bash tests/phase11/backup-restore.sh wp_phase11 wp_phase11_rs
#
# ۱) dump با --single-transaction گرفته می‌شود،
# ۲) در یک دیتابیس تازه import می‌شود،
# ۳) شمارش ردیف‌های همه‌ی جدول‌ها و CHECKSUM چند جدول کلیدی مقایسه می‌شود،
# ۴) متن ساده‌ی بالینی نباید در فایل dump پیدا شود،
# ۵) یک ciphertext از دیتابیس بازیابی‌شده با فایل کلید رمزگشایی می‌شود.
#
# پیش‌نیاز: MYSQL_HOST/PORT/USER (پیش‌فرض 127.0.0.1:3307 root) و
# دسترسی به `mariadb`/`mariadb-dump` و مسیر WP نصب‌شده در $WP_PATH.

set -euo pipefail

SRC="${1:-wp_phase11}"
DST="${2:-wp_phase11_rs}"
HOST="${MYSQL_HOST:-127.0.0.1}"
PORT="${MYSQL_PORT:-3307}"
USER="${MYSQL_USER:-root}"
WP_PATH="${WP_PATH:-$HOME/phase11/web}"
DUMP="${DUMP:-/tmp/backup-restore.sql}"
PHP_BIN="${PHP_BIN:-php}"   # مسیر php با افزونهٔ sodium (روی هاست معمولاً همان php)

cli() { mariadb --no-defaults -u"$USER" -h"$HOST" -P"$PORT" "$@"; }

echo "== dump =="
mariadb-dump --no-defaults -u"$USER" -h"$HOST" -P"$PORT" \
    --single-transaction --routines --triggers "$SRC" > "$DUMP"
echo "dump_bytes=$(stat -c%s "$DUMP")"

echo "== restore into a fresh database =="
cli -e "DROP DATABASE IF EXISTS \`$DST\`; CREATE DATABASE \`$DST\` CHARACTER SET utf8mb4;"
cli "$DST" < "$DUMP"

echo "== table list =="
cli -N -e "SELECT table_name FROM information_schema.tables WHERE table_schema='$SRC' ORDER BY 1" > /tmp/br_src.txt
cli -N -e "SELECT table_name FROM information_schema.tables WHERE table_schema='$DST' ORDER BY 1" > /tmp/br_dst.txt
echo "tables_src=$(wc -l < /tmp/br_src.txt) tables_dst=$(wc -l < /tmp/br_dst.txt) diff=$(diff /tmp/br_src.txt /tmp/br_dst.txt | wc -l)"

echo "== row counts =="
mismatch=0
while read -r t; do
    a=$(cli -N -e "SELECT COUNT(*) FROM \`$SRC\`.\`$t\`")
    b=$(cli -N -e "SELECT COUNT(*) FROM \`$DST\`.\`$t\`")
    if [ "$a" != "$b" ]; then echo "COUNT MISMATCH $t $a vs $b"; mismatch=$((mismatch + 1)); fi
done < /tmp/br_src.txt
echo "row_count_mismatches=$mismatch"

echo "== checksum (key tables) =="
cli -N -e "CHECKSUM TABLE \`$SRC\`.wp_pas_cf_cases, \`$DST\`.wp_pas_cf_cases, \`$SRC\`.wp_pas_cf_notes, \`$DST\`.wp_pas_cf_notes, \`$SRC\`.wp_pas_cf_audit_log, \`$DST\`.wp_pas_cf_audit_log"

echo "== plaintext scan in the dump =="
if grep -qE "یادداشت-محرمانه|سابقه-بیماری" "$DUMP"; then
    echo "PLAINTEXT FOUND IN DUMP"
else
    echo "plaintext_hits=0"
fi

echo "== decrypt a ciphertext from the restored database =="
ENC=$(cli -N --skip-column-names -e "SELECT medical_history_encrypted FROM \`$DST\`.wp_pas_cf_cases WHERE CHAR_LENGTH(medical_history_encrypted)>10 ORDER BY id DESC LIMIT 1")
"$PHP_BIN" -r '
$keyfile = $argv[2];
$rawkey  = trim((string) require $keyfile);
$kb      = strpos($rawkey, "base64:") === 0 ? base64_decode(substr($rawkey, 7), true) : base64_decode($rawkey, true);
$raw     = base64_decode($argv[1], true);
$pt      = $kb && $raw ? sodium_crypto_secretbox_open(substr($raw, 24), substr($raw, 0, 24), $kb) : false;
echo "decrypt_from_restored_db=" . ($pt === false ? "FAILED" : "OK") . "\n";
$wrong = $raw ? sodium_crypto_secretbox_open(substr($raw, 24), substr($raw, 0, 24), random_bytes(32)) : false;
echo "restore_without_key=" . ($wrong === false ? "cannot_decrypt" : "LEAK") . "\n";
' "$ENC" "$WP_PATH/wp-content/pas-encryption-key.php"
