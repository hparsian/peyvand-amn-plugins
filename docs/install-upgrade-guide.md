# راهنمای نصب و ارتقای بسته‌های `dist/` — پیوند امن

این راهنما برای نصب ۱۲ بستهٔ آماده روی سایت است: بررسی محتوای بسته‌ها، بکاپ
قبل از کار، ترتیب نصب، چک‌های بعد از نصب و مسیر بازگشت. شاهد اجرای آزمون‌ها و
هش بسته‌ها در [`staging-test-plan.md`](staging-test-plan.md) و
`docs/evidence/` است.

## ۱. بررسی محتوای بسته‌ها (۲۰۲۶-۱۱-۲۴)

روش بررسی: هر zip باز و فهرست شد؛ فایل اصلی از هدر `Plugin Name`/`Version`
خوانده شد؛ همهٔ فایل‌های PHP با `php -l` آزموده شدند؛ وجود فایل‌های اضافی
(`.git`، `node_modules`، `__MACOSX`، `.env`، `.dev.vars`، `*.log/map/sql/db/pem/key`)
و رمز/کلید جست‌وجو شد (`PRIVATE KEY`، `AKIA…`، `sk_live_…`، `xox…`) و فراخوانی
`eval(` بررسی شد.

| بسته | نسخهٔ هدر | فایل | PHP | lint | وضعیت |
|---|---|---|---|---|---|
| `pivand-amn-core-1.9.1.zip` | 1.9.1 | 35 | 19 | ۰ خطا | سالم |
| `payvand-amn-ui-kit-2.0.0.zip` | 2.0.0 | 22 | 5 | ۰ خطا | سالم |
| `pivand-amn-case-file-1.17.0.zip` | 1.17.0 | 17 | 12 | ۰ خطا | سالم |
| `pivand-amn-smtp-mailer-1.5.7.zip` | 1.5.7 | 5 | 2 | ۰ خطا | سالم |
| `pivand-amn-sms-bridge-1.1.10.zip` | 1.1.10 | 9 | 6 | ۰ خطا | سالم |
| `pivand-amn-2fa-1.3.5.zip` | 1.3.5 | 9 | 6 | ۰ خطا | سالم |
| `payvand-contact-1.4.5.zip` | 1.4.5 | 6 | 2 | ۰ خطا | سالم |
| `piwand-daily-feed-1.1.6.zip` | 1.1.6 | 13 | 8 | ۰ خطا | سالم |
| `pivand-amn-content-writer-2.9.21.zip` | 2.9.21 | 28 | 20 | ۰ خطا | سالم |
| `pivand-amn-quiz-enterprise-3.8.19.zip` | 3.8.19 | 67 | 55 | ۰ خطا | سالم |
| `pivand-amn-client-portal-1.13.2.7.zip` | 1.13.2.7 | 31 | 23 | ۰ خطا | سالم |
| `peyvand-amn-unified-dashboard-2.10.2.zip` | 2.10.2 | 50 | 45 | ۰ خطا | سالم |

نتیجهٔ بررسی:

- هر ۱۴ zip در `dist/` سالم‌اند (`unzip -t`) و فایل اصلی هر ۱۲ بستهٔ جاری
  بایت‌به‌بایت با سورس در `plugins/` یکی است.
- نسخهٔ هدر هر بسته دقیقاً با نام فایل و نسخهٔ نصب‌شده در بدل آزمون یکی است.
- هیچ فایل مخفی/سیستمی، `.git`، `node_modules`، `.env`، فایل لاگ، دیتابیس یا
  کلید خصوصی داخل بسته‌ها نیست؛ هیچ `eval(` و هیچ توکن/رمز شناخته‌شده‌ای
  پیدا نشد (جست‌وجو صفر نتیجه داد).
- فایل‌های اضافی فقط مستندات خود افزونه‌اند (`README.md`، `CHANGELOG.md`،
  `readme.txt`، `MERGE-NOTES.md`) و بی‌خطرند.
- پوشهٔ `pivand-amn-quiz-enterprise/includes/tests/` تست PHPUnit نیست؛ تعریف
  دادهٔ ابزارهای روان‌سنجی (SCL90R، BDI، STAI و…) است و باید بماند.
- چند `error_log(...)` در `payvand-contact` و `client-portal` فقط برای ثبت
  خطاهای واقعی است، نه چاپ داده.
- در `dist/` نسخه‌های قدیمی داشبورد (`2.10.0` و `2.10.1`) هم هست؛ **فقط
  `2.10.2` را نصب کن.**

## ۲. پیش‌نیازها

- WordPress ≥ 6.6 (آزموده‌شده روی 7.1.5) و **PHP ≥ 8.1** (هسته، پرونده، پنل،
  آزمون، پیامک، SMTP و 2FA صریحاً ۸.۱ می‌خواهند؛ بقیه ۷.۴ به بالا).
- `mysqli` و `sodium` فعال؛ MySQL/MariaDB با موتور InnoDB و `utf8mb4`
  (آزموده‌شده روی MariaDB 11.4.5 و Oracle MySQL 8.4.11).
- دسترسی نصب افزونه؛ اگر `DISALLOW_FILE_MODS` روشن است، بارگذاری از پیشخوان
  کار نمی‌کند و باید از `wp plugin install` یا پنل هاست استفاده شود.
- اگر سایت از قبل داده دارد: کلید رمزنگاری موجود (`PAS_CORE_ENCRYPTION_KEY` در
  `wp-config.php` یا `wp-content/pas-encryption-key.php`) باید حفظ شود.

## ۳. بکاپ قبل از نصب/ارتقا

```bash
cd /path/to/wordpress
wp db export pre-install-$(date +%Y%m%d).sql
tar czf plugins-backup-$(date +%Y%m%d).tgz wp-content/plugins
cp wp-config.php ~/wp-config-backup.php            # شامل کلید رمزنگاری
cp wp-content/pas-encryption-key.php ~/key-backup.php 2>/dev/null || true
wp plugin list --fields=name,version,status > plugins-before.txt
```

نسخهٔ قبلی همین ۱۲ بسته را هم نگه دار (برای بازگشت سریع بدون بازگرداندن
دیتابیس). مسیر بکاپ/بازیابی روی MySQL واقعی آزموده شده است: ۳۹/۳۹ جدول، صفر
اختلاف شمارش و checksum، رمزگشایی سالم از دیتابیس بازیابی‌شده.

## ۴. ترتیب نصب (هسته اول، چون بقیه به کلاس‌هایش تکیه دارند)

| # | بسته |
|---|---|
| ۱ | `pivand-amn-core-1.9.1.zip` |
| ۲ | `payvand-amn-ui-kit-2.0.0.zip` |
| ۳ | `pivand-amn-case-file-1.17.0.zip` |
| ۴ | `pivand-amn-smtp-mailer-1.5.7.zip` |
| ۵ | `pivand-amn-sms-bridge-1.1.10.zip` |
| ۶ | `pivand-amn-2fa-1.3.5.zip` |
| ۷ | `payvand-contact-1.4.5.zip` |
| ۸ | `piwand-daily-feed-1.1.6.zip` |
| ۹ | `pivand-amn-content-writer-2.9.21.zip` |
| ۱۰ | `pivand-amn-quiz-enterprise-3.8.19.zip` |
| ۱۱ | `pivand-amn-client-portal-1.13.2.7.zip` |
| ۱۲ | `peyvand-amn-unified-dashboard-2.10.2.zip` |

از پیشخوان: «افزونه‌ها ← افزودن ← بارگذاری افزونه» و به همین ترتیب نصب و فعال
کن. با WP-CLI:

```bash
for z in pivand-amn-core-1.9.1 payvand-amn-ui-kit-2.0.0 pivand-amn-case-file-1.17.0 \
         pivand-amn-smtp-mailer-1.5.7 pivand-amn-sms-bridge-1.1.10 pivand-amn-2fa-1.3.5 \
         payvand-contact-1.4.5 piwand-daily-feed-1.1.6 pivand-amn-content-writer-2.9.21 \
         pivand-amn-quiz-enterprise-3.8.19 pivand-amn-client-portal-1.13.2.7 \
         peyvand-amn-unified-dashboard-2.10.2; do
  wp plugin install "dist/$z.zip" --activate || break
done
```

در ارتقا از نسخهٔ قبلی، همین دستور با `--force` اجرا می‌شود تا فایل‌ها جایگزین
شوند؛ روتین‌های ارتقا با اولین بازدید پیشخوان (یا هر اجرای WP-CLI) خودشان
اجرا می‌شوند. نیازی به حذف/نصب مجدد و پاک‌کردن داده نیست.

## ۵. چک‌های بعد از نصب

1. `wp plugin list --status=active --fields=name,version` → هر ۱۲ مورد فعال با
   همان نسخه‌های جدول بالا.
2. باز کردن پیشخوان و همهٔ منوها یک‌بار؛ هیچ صفحه‌ای نباید خطای Fatal یا
   «critical error» بدهد.
3. ساخت یک پرونده و یک یادداشت آزمایشی؛ متن یادداشت باید در دیتابیس ciphertext
   باشد:
   `wp db query "SELECT LEFT(content_encrypted,24) FROM wp_pas_cf_notes ORDER BY id DESC LIMIT 1"`
   (نباید متن فارسی خوانا برگرداند) و در پیشخوان باید رمزگشایی و خوانده شود.
4. خروجی CSV: فایل باید با BOM `EF BB BF` شروع شود و سرصفحهٔ فارسی داشته باشد
   (`head -c 3 file.csv | od -An -tx1`).
5. `wp-content/debug.log` نباید ساخته شود یا باید خالی بماند (به‌جز هشدارهای
   بی‌ربط شبکه‌ای مثل عدم دسترسی به wordpress.org).
6. جدول‌ها باید InnoDB و `utf8mb4` باشند:
   `wp db query "SHOW TABLE STATUS LIKE 'wp_pas_%'"` → ستون Engine = InnoDB.
7. اگر دادهٔ قبلی داشتی، یک پرونده و یک یادداشت قدیمی را باز کن و مطمئن شو
   متن رمزگشایی می‌شود (کلید عوض نشده باشد).

## ۶. مسیر بازگشت

1. افزونه‌های تازه را غیرفعال کن.
2. پوشهٔ افزونه‌ها را از بکاپ برگردان: `tar xzf plugins-backup-YYYYMMDD.tgz -C wp-content`
   (یا نسخهٔ قبلی همان ۱۲ بسته را با `wp plugin install ... --force` نصب کن؛
   ستون/جدول‌های جدید بی‌آسیب می‌مانند و کد قدیمی آن‌ها را نمی‌بیند).
3. اگر داده هم باید برگردد: `wp db import pre-install-YYYYMMDD.sql` — توجه:
   پرونده‌های ساخته‌شده بعد از بکاپ با این کار از بین می‌روند، پس اول از دادهٔ
   تازه export بگیر.
4. کلید رمزنگاری را دست نزن؛ اگر جای فایل کلید عوض شده، همان فایل قبلی را
   برگردان.
5. بعد از بازگشت: ورود به پیشخوان، باز کردن یک پرونده و یک یادداشت، و بررسی
   `debug.log`.

حذف افزونه به‌صورت پیش‌فرض داده‌ها را نگه می‌دارد؛ فقط گزینهٔ صریح «حذف داده‌ها»
جدول‌های داشبورد را پاک می‌کند (جدول‌های هستهٔ وردپرس دست نمی‌خورند).

## ۷. نکات ارتقا و آپدیت

- داشبورد ۲.۱۰.۲ آپدیت خودکار را به‌صورت پیش‌فرض روشن می‌کند (فقط افزونه‌های
  اکوسیستم و بسته‌های HTTPS گیت‌هاب)؛ خاموش‌کردن دستی مدیر در آپدیت‌های بعدی
  بازنویسی نمی‌شود.
- مهاجرت واقعی ۱.۹.۰ → ۲.۱۰.۲ آزموده شده است: پرونده/یادداشت حفظ می‌شوند،
  ستون‌های جدید اضافه می‌شوند و اسکیما با نصب تازه یکی می‌شود (۱۵/۱۵ PASS).
- ارسال واقعی پیامک/ایمیل هنوز روی محیط واقعی تأیید نشده؛ بعد از نصب،
  اعتبارنامهٔ ملی‌پیامک و SMTP را در تنظیمات همان افزونه وارد و یک ارسال
  آزمایشی بگیر.
