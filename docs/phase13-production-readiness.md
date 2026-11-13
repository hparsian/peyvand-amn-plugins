# فاز ۱۳ — گزارش نهایی آمادگی Production

تاریخ: ۱۴۰۵/۰۸/۲۲ · مبنا: `24b542c` + ۵ کامیت فاز ۱۳ · نسخهٔ جاری `case-file 1.16.1`
**حکم کلی: آماده برای Production، مشروط به اجرای سه اقدام عملیاتی پیش از انتشار.**

---

## ۱. خلاصهٔ اجرایی

| مرحله | موضوع | حکم | یافته‌ها |
| --- | --- | --- | --- |
| ۰ | تثبیت وضعیت و پشتیبان | **PASS** | — |
| ۱ | وابستگی‌ها | **PASS** | DEP-1 (Medium)، DEP-2 (Low)، DEP-3 (Low) |
| ۲ | امنیت | **PASS** | SEC-1..4 (۱ Medium پذیرفته + ۳ Low)؛ بدون Critical/High |
| ۳ | یکپارچگی داده | **PASS** | DATA-1 (Medium) |
| ۴ | عملکرد | **PASS** | PERF-1 (اصلاح شد)، PERF-2 (Low) |
| ۵ | خرابی و بازیابی | **PASS** | — |

هیچ آسیب‌پذیری **Critical** یا **High** یافت نشد؛ بنابراین شرط توقف انتشار فعال نگردید.

---

## ۲. فهرست کامل یافته‌ها

| شناسه | یافته | شدت | فایل و خط | راهکار اصلاح | تست قبل/بعد | نتیجه |
| --- | --- | --- | --- | --- | --- | --- |
| DEP-1 | هیچ افزونه‌ای هدر `Requires Plugins:` ندارد؛ حذف/خاموشی `core` و `ui-kit` در سطح وردپرس مسدود نمی‌شود | **Medium** | هدر همهٔ ۱۱ فایل اصلی افزونه | افزودن `Requires Plugins: pivand-amn-core` به ۸ افزونهٔ وابسته و `payvand-amn-ui-kit` به مصرف‌کننده‌های کیت | خاموش‌کردن core/ui-kit → امروز بدون Fatal و با ۴۰۳ تمیز؛ افزودن هدر فقط تجربهٔ نصب را امن‌تر می‌کند | PASS (با بدهی ثبت‌شده) |
| DEP-2 | رجیستری وابستگی فقط ۴ افزونه را می‌شناسد | **Low** | `core/includes/class-dependency-checker.php` (رجیستری) | تکمیل رجیستری برای ۱۱ افزونه | `Pas_Core_Dependency_Checker::get_all()` = ۴ ردیف | PASS |
| DEP-3 | `2fa`/`sms`/`smtp` وابستگی رسمی به `core` ندارند ولی از توکن/فونت آن استفاده می‌کنند | **Low** | همان | همان DEP-1 (فونت/توکن با fallback کار می‌کند) | خاموشی core → ۲۰۰ و بدون Warning | PASS |
| SEC-1 | کد OTP متن‌ساده در transient دیتابیس | **Low** | `client-portal/includes/class-otp-auth.php:380, 584` | ذخیره با `wp_hash_password()` | TTL ۳۰۰s + سقف تلاش + `hash_equals`؛ فقط با دسترسی مستقیم DB قابل خواندن | پذیرفته (Low) |
| SEC-2 | `ajax_lookup_client_name` نام مراجع را با دانستن شماره برای هر درمانگر برمی‌گرداند | **Medium (پذیرفته)** | `core/includes/class-admin.php:76-104` | محدودکردن به مراجعین همان درمانگر | nonce + `manage_options`/`PAS_CF_CAP` دارد؛ آزمون `idor-roles` → `ACCEPTED` | ریسک آگاهانه |
| SEC-3 | لاگ پیامک شمارهٔ کامل را بدون ماسک نمایش می‌دهد | **Low** | `sms-bridge/includes/class-pas-admin.php:187` | استفاده از `mask_mobile()` (نمونهٔ موجود در `core/includes/class-center.php:299`) | صفحه فقط با `manage_options` باز می‌شود؛ ۲۰۰ رکورد آخر نگه داشته می‌شود | پذیرفته (Low) |
| SEC-4 | فرم چالش 2FA nonce ندارد | **Low** | `2fa/includes/class-login-gate.php` (`handle_challenge_submit`) | nonce مبتنی بر توکن چالش | توکن یک‌بارمصرف + سقف ۵ تلاش + TTL؛ در جریان پیش از ورود nonce ممکن نیست | پذیرفته (Low) |
| DATA-1 | نوشتن‌های چندمرحله‌ای بدون تراکنش دیتابیسی | **Medium** | `case-file/includes/class-case-file.php` (add_note + note_history)، `client-portal/includes/class-booking.php` | `START TRANSACTION … COMMIT/ROLLBACK` یا ثبت جبرانی | `grep START TRANSACTION` = ۰؛ هیچ orphan/رکورد نیمه‌تمام در DB یافت نشد | بدهی فنی |
| PERF-1 | N+1 در لیست پرونده‌ها (یک کوئری کاربر برای هر ردیف) | **Medium** | `case-file/includes/class-case-file.php:992` + `class-admin.php:377, 1569` | پیش‌بارگذاری گروهی `login__in` + کش استاتیک (اعمال شد، نسخه 1.16.1) | کوئری صفحه: **۹۱ → ۵۶** (−۳۸٪)، کوئری `user_login`: **۴۵ → ۰**، رندر ستون OTP: ۴۳ ردیف بدون تغییر، Fatal=0 | **اصلاح شد / PASS** |
| PERF-2 | `SHOW TABLES LIKE` در ۷ نقطه بدون کش (در مرکز مدیریت ۹ بار) | **Low** | `class-compliance.php:208,240`، `class-data-audit.php:223`، `class-client-registry.php:1036`، `class-field-mapper.php:290`، `class-center.php:282`، `class-health.php:333`، `class-case-file.php:1016` | یک کش مشترک `Pas_Core_Table_Cache::exists()` | اثر اندازه‌گیری‌شده بخشی از ۲۶ms کل صفحه | بدهی فنی (کم‌اثر) |

**جمع:** ۰ Critical، ۰ High، ۳ Medium (۱ ریسک پذیرفته، ۱ بدهی فنی، ۱ اصلاح‌شده)، ۵ Low.

---

## ۳. شواهد قابل بازتولید (خلاصه)

```bash
# مرحله ۰ — وضعیت و پشتیبان
git status --porcelain                 # خالی
git log --oneline -1                   # 24b542c (مبنا)
sha256sum $HOME/phase13-backup/*       # مطابق docs/phase13-step0-baseline.md

# مرحله ۱ — وابستگی‌ها
wp eval-file /tmp/dep-matrix.php       # نسخه/فعال/رجیستری وابستگی
wp eval-file /tmp/assets-audit.php     # ۲۲۲ style / ۲۲۶ script، duplicate_src_groups=0
node $HOME/pwtool/isolation.mjs        # خاموشی UI Kit/Core بدون Fatal

# مرحله ۲ — امنیت
wp eval-file /tmp/route-once.php       # ۱۳ مسیر یکتا
curl "http://127.0.0.1:8090/?rest_route=/zdp/v1/panel-state"           # 403
wp eval-file tests/phase11/idor-roles.php   # 19 PASS / 0 FAIL / 1 ACCEPTED
wp eval-file tests/phase11/encryption.php   # 9 PASS / 0 FAIL

# مرحله ۳ — داده
wp eval-file /tmp/integrity.php        # orphan=0، duplicate=0
# آزمون مهاجرت ایزوله: کپی DB → حذف ستون‌ها → یک درخواست HTTP → ستون‌ها برگشت، ۳۹ یادداشت بدون تغییر

# مرحله ۴ — عملکرد
node $HOME/pwtool/perf.mjs             # TTFB/Load/درخواست/حجم
node $HOME/pwtool/perf2.mjs            # خط پایهٔ صفحات وردپرس
# شمارش کوئری با mu-plugin موقت: ۹۱ → ۵۶ روی لیست پرونده‌ها

# مرحله ۵ — خرابی/بازیابی
# چرخهٔ خاموش/روشن ۱۱ افزونه → f0 w0 در همهٔ صفحه‌ها
# rollback کد از آرشیو پشتیبان → 200/fatal=0 → بازگردانی نسخهٔ جاری
# بازیابی دامپ در DB ایزوله → ۳۷ جدول، شمارش‌های مطابق، داده خوانا
```

---

## ۴. سنجه‌های کلیدی (اندازه‌گیری‌شده، نه تخمینی)

| سنجه | مقدار |
| --- | --- |
| مسیر REST خانواده | ۱۳ مسیر یکتا، همه با permission_callback |
| اکشن admin-post | ۳۰+، همه با nonce + capability (جز ۳ استثنای مستند) |
| یافتهٔ SQLi/XSS | ۰ |
| IDOR | ۱۹ تست PASS، ۰ FAIL، ۱ ریسک پذیرفته |
| رمزنگاری | ۹ PASS، ۰ FAIL |
| orphan/duplicate در داده | ۰ |
| Fatal/Warning در ۱۱ سناریوی خاموشی | ۰ |
| پنل مراجع | ۲۵۶ms / ۱۴ درخواست / ۳۹۳KB |
| صفحات پیشخوان افزونه‌ها | ۸۸۲–۱۰۷۴ms / ۶۰–۶۴ درخواست / ۳.۴–۴.۰MB (سبک‌تر از صفحات پایهٔ وردپرس) |
| ApexCharts | فقط در صفحهٔ مرکز مدیریت (۵۲۷KB)، شرطی ✅ |
| درخواست خارجی | ۰ (به‌جز آواتار Gravatar خود وردپرس) |

---

## ۵. اقدامات لازم پیش از انتشار Production

1. **پشتیبان Production** با همین روش (`mariadb-dump` + آرشیو کد) و نگهداری بیرون از وب‌روت.
2. **افزودن هدر `Requires Plugins:`** (DEP-1) تا نصب/حذف نادرست هسته در محیط واقعی مسدود شود.
3. **کنترل کش صفحه** در Production: مسیرهای `admin-post.php`، `/panel/`، `wp-json` و صفحات دارای nonce
   باید از کش صفحه مستثنا باشند (Staging کش صفحه ندارد، بنابراین این سناریو آزموده نشده است).

موارد غیرمسدودکننده برای نسخهٔ بعد: DATA-1 (تراکنش)، PERF-2 (کش وجود جدول)، SEC-1/SEC-3 (سخت‌سازی OTP و ماسک شماره).

---

## ۶. محدودیت‌های صادقانهٔ این ممیزی

- آزمون روی **Staging** انجام شد؛ Production دست‌نخورده ماند (طبق قاعدهٔ فاز).
- `tests/phase11/migration-verify.php` به فیکسچرهای ساختگی فاز ۱۱ وابسته است و در دیتابیس فعلی
  ۷ بررسی آن FAIL می‌شود؛ این FAIL **رگرسیون نیست** و جای آن با آزمون مهاجرت ایزوله (مرحله ۳) پوشش داده شد.
- آزمون بار هم‌زمان (concurrency) انجام نشد؛ سرور PHP داخلی تک‌رشته‌ای است و اعداد TTFB تحت بار
  واقعی وب‌سرور Production می‌توانند متفاوت باشند.
- کش شیء پایدار (Redis) و کش صفحه در Staging وجود ندارد؛ رفتار اکوسیستم با آن‌ها آزموده نشده است.
- آزمون OTP/2FA با شمارهٔ واقعی و پیامک واقعی انجام نشد (اعتبارنامه‌های Production موجود نبود)؛
  منطق با آزمون‌های `sms-otp` و بررسی کد ارزیابی شد.

---

## ۷. حکم نهایی

| معیار خاتمه | وضعیت |
| --- | --- |
| امنیت با شواهد تست | ✅ (IDOR ۱۹/۱۹، رمزنگاری ۹/۹، REST/CSRF/نفوذ دستی) |
| سازگاری | ✅ (بدون حلقهٔ وابستگی، بدون بارگذاری تکراری، مسیر ارتقا آزموده) |
| پایداری | ✅ (۱۱ سناریوی خاموشی، صفر Fatal/Warning) |
| بازیابی | ✅ (rollback کد + بازیابی دیتابیس هر دو آزموده) |
| بدون تغییر Production | ✅ |

**PASS — اکوسیستم از نظر امنیت، سازگاری، پایداری و بازیابی شواهد معتبر دارد و پس از سه اقدام
عملیاتی بخش ۵ آمادهٔ انتشار Production است.**
