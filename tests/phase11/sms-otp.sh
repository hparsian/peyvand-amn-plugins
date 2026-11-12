#!/usr/bin/env bash
# فاز ۱۱ — آزمون پیامک و OTP روی HTTP واقعی، با ترنسپورت شبیه‌سازی‌شده.
#
# چرا شبیه‌سازی: هیچ اعتبارنامه‌ی ملی‌پیامک در این محیط نیست، پس «ارسال
# واقعی به گوشی» قابل اثبات نیست (در گزارش BLOCKED علامت خورده). ولی کل
# مسیر خود افزونه — ساخت کد، فراخوانی پل پیامک، payload درخواست، ثبت لاگ،
# محدودیت نرخ، انقضا و قفل پس از تلاش‌های ناموفق — واقعی اجرا می‌شود.
#
# هارنس فقط لایه‌ی شبکه را می‌گیرد: فیلتر pre_http_request پاسخ موفق
# ملی‌پیامک را برمی‌گرداند و بدنه‌ی درخواست را در /tmp/p11-sms-out.log
# ثبت می‌کند. هیچ پیامکی ارسال نمی‌شود.
#
# اجرا: bash tests/phase11/sms-otp.sh [http://127.0.0.1:8090]

set -uo pipefail

SITE="${1:-http://127.0.0.1:8090}"
WP_PATH="${WP_PATH:-$HOME/phase11/web}"
WP="php $WP_PATH/../wp-cli.phar --path=$WP_PATH --allow-root"
# اجرای wp-cli با متغیر محیطی M (شماره موبایل) برای خواندن transient همان شماره
wpm() { local m="$1"; shift; M="$m" php "$WP_PATH/../wp-cli.phar" --path="$WP_PATH" --allow-root eval "$1" 2>/dev/null | tail -1; }
MU="$WP_PATH/wp-content/mu-plugins/p11-sms-sim.php"
SIM_FLAG=/tmp/p11-sms-sim.on
OUT=/tmp/p11-sms-out.log

PASS=0; FAIL=0
ok() { PASS=$((PASS+1)); printf 'PASS  | %-46s | %s\n' "$1" "$2"; }
no() { FAIL=$((FAIL+1)); printf 'FAIL  | %-46s | %s\n' "$1" "$2"; }

mkdir -p "$(dirname "$MU")"
cat > "$MU" <<'PHP'
<?php
if (!defined('ABSPATH')) { exit; }
if (!file_exists('/tmp/p11-sms-sim.on')) { return; }
add_filter('pre_http_request', function ($pre, $args, $url) {
    if (strpos($url, 'payamak-panel.com') === false) { return $pre; }
    file_put_contents('/tmp/p11-sms-out.log', json_encode(['url'=>$url,'body'=>$args['body'] ?? '','t'=>time()], JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND);
    return ['headers'=>['content-type'=>'application/json'],'body'=>json_encode(['Value'=>'sim','RetStatus'=>1,'StrRetStatus'=>'موفق']),'response'=>['code'=>200,'message'=>'OK'],'cookies'=>[],'filename'=>null];
}, 10, 3);
PHP
touch "$SIM_FLAG"; rm -f "$OUT"

NONCE=$($WP eval 'wp_set_current_user(0); echo wp_create_nonce("wp_rest");' 2>/dev/null | tail -1)
post() { curl -s -o /tmp/otp.json -w '%{http_code}' -X POST "$SITE/?rest_route=/zdp/v1/$1" \
    -H "X-WP-Nonce: $NONCE" -H 'Content-Type: application/json' -d "$2"; }
msg() { python3 -c "import json;d=json.load(open('/tmp/otp.json'));print(d.get('data',{}).get('message','?'))" 2>/dev/null; }
newmobile() { echo "0912$(printf '%07d' $((RANDOM*37)))"; }

echo "== ۱) ارسال کد با ترنسپورت شبیه‌سازی‌شده =="
M=$(newmobile)
code=$(post send-otp "{\"mobile\":\"$M\"}")
[ "$code" = "200" ] && ok 'send-otp returns success' "http=$code msg=$(msg)" || no 'send-otp returns success' "http=$code msg=$(msg)"

payload=$(tail -1 "$OUT" 2>/dev/null | python3 -c "import json,sys;d=json.load(sys.stdin);print(json.dumps(json.loads(d['body']),ensure_ascii=False))" 2>/dev/null)
echo "payload=$payload"
echo "$payload" | grep -q "\"to\": \"$M\"" && ok 'provider request targets the mobile' "to=$M" || no 'provider request targets the mobile' "$payload"
echo "$payload" | grep -q '"bodyId": 123456' && ok 'provider request carries the pattern id' 'bodyId=123456' || no 'provider request carries the pattern id' "$payload"

OTP=$(wpm "$M" 'echo get_transient("zdp_otp_" . getenv("M"));')
[ -n "$OTP" ] && ok 'code stored only after successful send' "len=${#OTP}" || no 'code stored only after successful send' 'empty'
echo "$payload" | grep -q "\"text\": \"$OTP\"" && ok 'sent text is the same code' "text=$OTP" || no 'sent text is the same code' "$payload"

echo "== ۲) کد اشتباه و سپس کد درست =="
code=$(post verify-otp "{\"mobile\":\"$M\",\"otp\":\"00000\"}")
[ "$code" = "400" ] && ok 'wrong code rejected' "http=$code msg=$(msg)" || no 'wrong code rejected' "http=$code"
code=$(post verify-otp "{\"mobile\":\"$M\",\"otp\":\"$OTP\"}")
[ "$code" = "200" ] && ok 'correct code logs the client in' "http=$code" || no 'correct code logs the client in' "http=$code msg=$(msg)"

echo "== ۳) محدودیت نرخ ارسال مجدد =="
code=$(post send-otp "{\"mobile\":\"$M\"}")
[ "$code" = "400" ] && ok 'resend within 60s is throttled' "http=$code" || no 'resend within 60s is throttled' "http=$code"

echo "== ۴) کد منقضی‌شده (بعد از ۶۵ ثانیه) =="
sleep 65
M2=$(newmobile); post send-otp "{\"mobile\":\"$M2\"}" >/dev/null
OTP2=$(wpm "$M2" 'echo get_transient("zdp_otp_" . getenv("M"));')
wpm "$M2" 'delete_transient("zdp_otp_" . getenv("M"));' >/dev/null
code=$(post verify-otp "{\"mobile\":\"$M2\",\"otp\":\"$OTP2\"}")
[ "$code" = "400" ] && ok 'expired code rejected' "http=$code msg=$(msg)" || no 'expired code rejected' "http=$code"

echo "== ۵) قفل پس از ۵ تلاش ناموفق =="
sleep 61
M3=$(newmobile); post send-otp "{\"mobile\":\"$M3\"}" >/dev/null
OTP3=$(wpm "$M3" 'echo get_transient("zdp_otp_" . getenv("M"));')
for i in 1 2 3 4 5; do post verify-otp "{\"mobile\":\"$M3\",\"otp\":\"1000$i\"}" >/dev/null; done
code=$(post verify-otp "{\"mobile\":\"$M3\",\"otp\":\"$OTP3\"}")
[ "$code" = "400" ] && ok 'code invalidated after 5 failed attempts' "http=$code msg=$(msg)" || no 'code invalidated after 5 failed attempts' "http=$code"
left=$(wpm "$M3" 'echo strlen((string) get_transient("zdp_otp_" . getenv("M")));')
[ "$left" = "0" ] && ok 'code removed from storage after lock' 'len=0' || no 'code removed from storage after lock' "len=$left"

echo "== ۶) شکست ارسال: کد ذخیره نمی‌شود =="
rm -f "$SIM_FLAG"
M4=$(newmobile); code=$(post send-otp "{\"mobile\":\"$M4\"}")
[ "$code" = "400" ] && ok 'send failure surfaces as an error' "http=$code msg=$(msg)" || no 'send failure surfaces as an error' "http=$code"
left=$(wpm "$M4" 'echo strlen((string) get_transient("zdp_otp_" . getenv("M")));')
[ "$left" = "0" ] && ok 'no orphan code when the provider fails' 'len=0' || no 'no orphan code when the provider fails' "len=$left"

touch "$SIM_FLAG"
echo "TOTALS: PASS=$PASS FAIL=$FAIL"
echo 'نکته: ارسال واقعی به گوشی در این محیط قابل اثبات نیست (اعتبارنامه‌ی ملی‌پیامک موجود نیست) — BLOCKED.'
