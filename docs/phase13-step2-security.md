# فاز ۱۳ — مرحلهٔ ۲: ممیزی امنیتی

تاریخ: ۱۴۰۵/۰۸/۲۲ · وضعیت: **PASS** (۴ یافته: ۱ Medium پذیرفته‌شده، ۳ Low) · هیچ آسیب‌پذیری Critical/High یافت نشد

## ۱. REST API — سطح حمله

۱۳ مسیر یکتا ثبت شده است (شمارش در یک پروسهٔ تازه؛ هر مسیر فقط یک‌بار ثبت می‌شود).

| مسیر | متد | permission_callback | محافظت واقعی |
| --- | --- | --- | --- |
| `/zdp/v1/panel-state` | GET | `Client_Panel::rest_permission_check` | کوکی + nonce REST |
| `/zdp/v1/booking-grid` | GET | `Booking::rest_permission_check` | کوکی + nonce REST |
| `/zdp/v1/send-otp` | POST | `OTP_Auth::rest_permission_check` | کوکی + nonce REST |
| `/zdp/v1/send-otp-email` | POST | همان | کوکی + nonce REST |
| `/zdp/v1/verify-otp` | POST | همان |کوکی + nonce REST |
| `/zdp/v1/verify-otp-email` | POST | همان | کوکی + nonce REST |
| `/psy/v1/quiz` | GET | closure (`token`) | توکن دعوت + rate-limit |
| `/psy/v1/submit` | POST | `__return_true` | توکن دعوت (`Psy_Quiz_Invitation_Manager::validate`) + rate-limit ۱۰ |
| `/psy/v1/draft` | GET/POST | `__return_true` | توکن دعوت + rate-limit ۳۰ |
| `/psy/v1/report/{id}` | GET | closure (`token`) | `hash_equals(access_token, token)` روی همان attempt + ادمین |
| `/psy/v1/report/{id}/pdf` | GET | closure (`token`) | همان `hash_equals` در `class-psy-pdf-export.php:89` |

**آزمون زندهٔ ناشناس** (بدون کوکی):

| درخواست | نتیجه | حکم |
| --- | --- | --- |
| `GET /zdp/v1/panel-state` | `403 zdp_rest_forbidden` | ✅ |
| `GET /zdp/v1/booking-grid` | `403` | ✅ |
| `POST /zdp/v1/send-otp` | `403` | ✅ |
| `GET /psy/v1/draft?token=ABCDEF123456` | `200 {"found":false}` — بدون افشای داده | ✅ |
| `POST /psy/v1/submit` با توکن جعلی | `403 لینک نامعتبر` | ✅ |
| `GET /psy/v1/report/1` | `401` | ✅ |
| `GET /psy/v1/report/1/pdf` | `401` | ✅ |
| `GET /psy/v1/quiz` بدون token | `400 missing token` | ✅ |

**آزمون CSRF:** درخواست `panel-state` با کوکی معتبر مراجع ولی **بدون** هدر `X-WP-Nonce` → `403 zdp_rest_forbidden`
(روتین `rest_cookie_check_errors`). ✅

## ۲. admin-post و فرم‌ها

- ۳۰+ اکشن `admin_post_*`. همهٔ هندلرها با الگوی `assert_can_manage()` / `Pas_CF_Security::require_access()`
  + `check_admin_referer(...)` محافظت می‌شوند (نمونه: `class-admin.php:1350`، `class-admin.php:673`).
- سه هندلر بدون nonce باقی مانده که هر سه توجیه‌پذیرند:
  - `Pas_2FA_Login_Gate::handle_challenge_submit` — پیش از ورود، توکن یک‌بارمصرف + سقف ۵ تلاش (SEC-4، Low).
  - `Booking::handle_zarinpal_callback` — بازگشت درگاه؛ اعتبارسنجی با `authority` و `verify_payment()`، نه nonce.
  - `Pas_Melipayamak::handle_send_sms_filter` — فیلتر `pas_send_sms` است، نه هندلر HTTP.
- هیچ اکشن `wp_ajax_*` بدون `check_ajax_referer` + `current_user_can` پیدا نشد
  (نمونه: `core/includes/class-admin.php:78` لوکاپ نام — nonce + `manage_options`/`PAS_CF_CAP`).

## ۳. IDOR و تفکیک درمانگر

اجرای `tests/phase11/idor-roles.php` روی همین نسخه:

```
TOTALS: {"PASS":19,"FAIL":0,"FINDING":0,"BLOCKED":0,"ACCEPTED":1}
```

شامل: پروندهٔ درمانگر دیگر (`403`, بدون افشا)، چاپ/حذف یادداشت دیگران (`403`، یادداشت سالم)،
ذخیرهٔ پروندهٔ دیگران (`403`, نام تغییر نکرد)، خروجی CSV محدود، ارتقای نقش (درمانگر → سلامت/پیامک/پنل: `403`)،
مراجع → پرونده‌ها (`403`)، گزارش آزمون بدون توکن (`401`).

## ۴. SQL، XSS، دسترسی فایل

| بررسی | نتیجه |
| --- | --- |
| `$wpdb` با `$_GET/$_POST` در همان خط | **۰ مورد** |
| `prepare()` در همهٔ کوئری‌های ورودی‌دار | ✅ (نمونه: `class-psy-rest-handler.php:278,319`) |
| خروجی مستقیم سوپرگلوبال | **۰ مورد** حساس؛ ۴ مورد یافت‌شده همگی `(int)` یا `esc_html(sanitize_text_field(...))` |
| دسترسی مستقیم به فایل PHP افزونه‌ها | همه `200` با بدنهٔ **۰ بایت** (اجرا بدون خروجی) |
| `wp-content/pas-encryption-key.php` | `200`, بدنه **۰ بایت** — کلید فقط از PHP خوانده می‌شود |
| گارد `WP_UNINSTALL_PLUGIN` در ۱۰ فایل `uninstall.php` | ✅ هر ۱۰ فایل دارد |

## ۵. رمزنگاری، OTP و 2FA

```
tests/phase11/encryption.php → TOTALS: PASS=9 FAIL=0
```
متن بالینی در DB رمزنگاری‌شده، nonce(24)+box(+16)، رد دستکاری، کلید بیرون از DB، سازگاری متن سادهٔ قدیمی.

- OTP: `hash_equals` برای مقایسه، TTL ۳۰۰ ثانیه، شمارندهٔ تلاش، rate-limit ارسال با `GET_LOCK`
  (`class-otp-auth.php:374-381, 737-739, 656`).
- 2FA: secret با `Pas_Core_Crypto::unwrap_decrypt()` رمزنگاری‌شده، سقف ۵ تلاش، کد بازیابی یک‌بارمصرف.

## ۶. یافته‌ها

| شناسه | یافته | شدت | مکان | راهکار | وضعیت |
| --- | --- | --- | --- | --- | --- |
| SEC-1 | کد OTP به‌صورت متن ساده در transient دیتابیس ذخیره می‌شود | **Low** | `client-portal/includes/class-otp-auth.php:380, 584` | ذخیرهٔ `wp_hash_password($code)` و مقایسه با `wp_check_password` | پذیرفته برای این فاز (TTL ۳۰۰s، نیازمند دسترسی مستقیم به DB) |
| SEC-2 | `pas_core_lookup_client_name` نام مراجع را برای هر درمانگر با دانستن شماره برمی‌گرداند | **Medium (پذیرفته)** | `core/includes/class-admin.php:76-104` | محدودکردن به مراجعین همان درمانگر یا نمایش ماسک‌شده | ریسک آگاهانه: لازمهٔ UX فرم پرونده؛ nonce+cap دارد. در گزارش نهایی به‌عنوان ریسک پذیرفته‌شده ثبت شد |
| SEC-3 | جدول لاگ پیامک شمارهٔ کامل موبایل را بدون ماسک نشان می‌دهد | **Low** | `sms-bridge/includes/class-pas-admin.php:187` | استفاده از `mask_mobile()` مانند `core/includes/class-center.php:299` | پیشنهاد برای نسخهٔ بعد |
| SEC-4 | فرم چالش 2FA nonce ندارد | **Low** | `2fa/includes/class-login-gate.php` | افزودن nonce مبتنی بر توکن چالش | ریسک پایین: توکن یک‌بارمصرف + سقف تلاش + cookieless flow |

هیچ یافتهٔ **Critical** یا **High** یافت نشد؛ بنابراین شرط «توقف انتشار» فعال نشد.

## ۷. شواهد قابل بازتولید

```bash
wp eval-file /tmp/rest-audit.php          # فهرست مسیرها + permission_callback
wp eval-file /tmp/route-once.php          # ۱۳ مسیر یکتا، بدون ثبت دوباره
curl "http://127.0.0.1:8090/?rest_route=/zdp/v1/panel-state"                      # 403
curl -X POST "http://127.0.0.1:8090/?rest_route=/psy/v1/submit" \
     -H 'Content-Type: application/json' -d '{"token":"bogus","answers":{"1":"a"}}' # 403
wp eval-file tests/phase11/idor-roles.php   # 19 PASS / 0 FAIL
wp eval-file tests/phase11/encryption.php   # 9 PASS / 0 FAIL
```

## ۸. حکم مرحله

**PASS** — کنترل دسترسی، nonce/CSRF، IDOR، SQLi/XSS و دسترسی فایل بدون نقص بحرانی است.
چهار یافته ثبت شد که یکی (SEC-2) ریسک پذیرفته‌شدهٔ طراحی و سه مورد دیگر Low هستند.
