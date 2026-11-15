# بازبینی کل مخزن — گزارش خطاها و اصلاحات

> **یادداشت فاز ۱۵ (۱۴۰۵/۰۸/۲۴):** بسته‌هایی که در این گزارش با مسیر `dist/` آمده‌اند بازنشسته و حذف شده‌اند؛ تنها بستهٔ رسمی و بازبینی‌شده، خروجی فاز ۱۵ در `release/v1/plugins/` است. متن زیر سابقهٔ تاریخی همان فاز است و دست‌نخورده مانده.

تاریخ: ۲۱ آبان ۱۴۰۵ (2026-11-12)
دامنه: تمام فایل‌های PHP مخزن (`plugins/` — ۱۵۰ فایل در ۱۰ افزونه) + docs/dist
روش: تحلیل ایستا (توکنایزر PHP + php -l) + اجرای واقعی روی WordPress 7.1.4 / PHP 8.5.8 (SQLite)

## ۱. خلاصه

| مورد | نتیجه |
|---|---|
| خطای نحوی (`php -l` روی ۱۵۰ فایل) | **۰** |
| باگ واقعی پیدا و رفع‌شده | **۲** |
| آسیب‌پذیری امنیتی تأییدشده (نشت/دسترسی غیرمجاز) | **۰** |
| نوتیس/وارنینگ PHP از کد افزونه‌ها در اجرا | **۰** (پس از رفع) |
| Fatal در فعال‌سازی/غیرفعال‌سازی ×۲ و اجرای کران | **۰** |

## ۲. باگ‌های رفع‌شده

### باگ ۱ — نوتیس `wpdb::prepare` در گزارش‌های پرونده (واقعی، هنگام باز کردن صفحه)

- **فایل:** `plugins/pivand-amn-case-file/includes/class-reports.php` → `summary()`
- **علت:** برای سرپرست/ادمین، `Pas_CF_Security::current_user_case_scope()` شرط
  `1=1` و آرایهٔ پارامتر خالی برمی‌گرداند. کوئری «پرونده‌ها به تفکیک وضعیت»
  در این حالت هیچ placeholderی ندارد، ولی همچنان به `$wpdb->prepare($sql, ...[])`
  داده می‌شد؛ وردپرس برای کوئری بدون placeholder نوتیس `_doing_it_wrong`
  تولید می‌کند (در `WP_DEBUG` به‌صورت `PHP Notice` دیده می‌شود).
- **اثر:** فقط نوتیس/نویز لاگ؛ نتیجهٔ گزارش درست بود. در محیط‌هایی که
  `WP_DEBUG_DISPLAY` روشن است، بالای صفحهٔ گزارشها هشدار نشان داده می‌شد.
- **رفع:** ساخت SQL در متغیر و فراخوانی `prepare()` فقط وقتی پارامتر وجود دارد:
  ```php
  $case_sql  = "SELECT c.status ... WHERE $where GROUP BY c.status";
  $case_rows = $scope_params
      ? $wpdb->get_results($wpdb->prepare($case_sql, ...$scope_params))
      : $wpdb->get_results($case_sql);
  ```
- **تأیید:** صفحهٔ `pas-case-reports` قبل از رفع: `issues=1` — بعد از رفع: `issues=0`.

### باگ ۲ — هشدار منسوخ‌شدن `who` در `WP_User_Query`

- **فایل:** `plugins/pivand-amn-content-writer/includes/trait-aco-admin-pages.php`
- **علت:** `get_users(['who' => 'authors'])`؛ آرگومان `who` از وردپرس 5.9
  deprecated است و در هر بار باز کردن تنظیمات، `PHP Deprecated` ثبت می‌کرد.
- **رفع:** جایگزین رسمی: `get_users(['capability' => 'edit_posts', ...])`
  (همان فهرست نویسندگان، بدون هشدار).
- **تأیید:** صفحهٔ `aco-settings` قبل: `issues=1` — بعد: `issues=0`.

### نسخه‌ها

| افزونه | قبل | بعد |
|---|---|---|
| `pivand-amn-case-file` | 1.15.1 | **1.15.2** |
| `pivand-amn-content-writer` | 2.9.17 | **2.9.18** |

هر دو CHANGELOG به‌روزرسانی شد و بسته‌های `dist/` بازساخته شدند
(`case-file-1.15.2.zip`، `content-writer-2.9.18.zip`).

## ۳. چه چیزهایی بررسی و سالم تشخیص داده شد

### ۳.۱. امنیت هندلرها (۶۴ هندلر AJAX/admin-post)

همهٔ هندلرهای `admin_post_*` و `wp_ajax_*` از نظر nonce و capability بررسی شدند.
موارد زیر ابتدا «بدون بررسی» علامت خوردند و با بازخوانی کد **سالم** بودند:

- **client-portal** (۱۲ هندلر: تنظیمات، نوبت دستی، حذف نوبت، سرویس‌ها، کارت‌ها،
  سؤالات نظرسنجی، ساعات کاری): همه `$this->assert_can_manage()` دارند
  (`zdp_manage_clients` یا `manage_options`) + `check_admin_referer`.
- **case-file**: همه `Pas_CF_Security::require_access()` +
  `require_case_access($case)` + nonce.
- **2FA پروفایل**: سرویس self-service است؛ nonce دارد و همیشه روی
  `get_current_user_id()` عمل می‌کند، نه user_id ورودی → امکان تغییر ۲FA
  کاربر دیگر وجود ندارد.
- **payvand-contact**: `check_ajax_referer` + rate limit بر اساس IP + sanitize.
- **daily-feed**: `check_ajax_referer('pwdf_ajax')`؛ `nopriv` بودن عمدی
  (کارت عمومی) و غیرمجاز بودن بدون nonce با curl تأیید شد (پاسخ `-1`).
- **REST** (۱۹ مسیر): تنها دو مسیر با `permission_callback => '__return_true'`
  وجود دارد — `/psy/v1/submit` و `/psy/v1/draft` که ذاتاً عمومی‌اند و با
  توکن دعوت‌نامه کار می‌کنند؛ بقیه `manage_options` یا بررسی توکن دارند.

### ۳.۲. اجرای واقعی

- **۳۱ صفحهٔ ادمین** همهٔ افزونه‌ها رندر شدند: بدون Fatal/Warning/Notice
  (`wpdb->last_error` هم خالی).
- **۸ شورتکد** عمومی اجرا شدند: بدون خطا.
- **چرخهٔ فعال/غیرفعال ×۲** روی ۱۰ افزونه: بدون خطا و بدون داده‌تلف‌شدن.
- **کران**: `psy_quiz_cleanup_*`، `aco_process_queue`، `zsm_process_mail_queue`
  اجرا شدند؛ بدون خطا.
- **HTTP واقعی**: `wp-login.php` / `wp-json` / `/` → 200؛ ارسال فرم تماس با
  nonce واقعی → `{"success":true}` و ردیف در دیتابیس ثبت شد.

### ۳.۳. الگوهای خطرناک

| الگو | تعداد | ارزیابی |
|---|---|---|
| `eval(`, `unserialize(`, `extract(`, `create_function`, `each(` | ۰ | پاک |
| `base64_decode` | ۴ | همه با `strict=true` یا روی دادهٔ API خودی (کلید/تصویر) |
| `system(` | ۲ | نام متد `render_system()` است، نه فراخوانی سیستمی |
| `$wpdb->query` بدون prepare با ورودی متغیر | ۰ | — |
| `shell_exec`, `passthru`, `exec`, `curl_init` | ۰ | — |
| `fopen/fwrite` | روی `php://output` برای CSV | درست |
| SQL با `LIKE '$var%'` بدون escape | ۰ | — |
| `uninstall.php` بدون گارد | ۰ | همه `WP_UNINSTALL_PLUGIN` دارند |

## ۴. آنچه بررسی نشد (شفاف)

- **WPCS/PHPCS**: نصب شد (phpcs 3.13 + WPCS 3.1 + PHPCSUtils + PHPCSExtra) ولی
  ruleset وردپرس در این محیط به‌دلیل نبود چند sniff از استاندارد `Universal`
  خطا داد؛ در نتیجه گزارش «خط‌به‌خط استاندارد وردپرس» تولید نشد. به‌جای آن از
  تحلیل توکنایزر اختصاصی + اجرای واقعی استفاده شد.
- **MySQL/MariaDB**: آزمون‌ها روی SQLite انجام شد؛ تفاوت‌های موتور (مثل
  `GET_LOCK` در کلاس Security پنل مراجعان که فقط MySQL است) پوشش داده نشد.
  (این رفتار عمدی محافظه‌کارانه است: اگر قفل گرفته نشود، درخواست رد می‌شود.)
- **مسیرهای پرداخت/پیامک/ایمیل واقعی**: نیازمند کلید و سرویس بیرونی است.
- **REST/AJAX با کوکی مرورگر**: با curl و nonce واقعی تست شد، ولی نه با
  نشست لاگین ادمین در مرورگر.

## ۵. پیشنهادهای بعدی (بدون تغییر در این مرحله)

1. `GET_LOCK` پنل مراجعان را برای محیط‌های غیر MySQL (یا وقتی قفل در دسترس
   نیست) به یک fallback اتمیک مبتنی بر `INSERT ... ON DUPLICATE KEY` تغییر
   بده تا rate limit روی SQLite/سرویس‌های مدیریت‌شده هم کار کند.
2. یک sniff سفارشی/CI اضافه شود که `prepare()` بدون placeholder و
   `get_users(['who' => ...])` را در آینده بگیرد.
3. تفاوت شمارش جدول‌های legacy در داشبورد سلامت (فاز ۱۰) اصلاح شود
   (شمارش «موجود» بر اساس `exists` واقعی).
