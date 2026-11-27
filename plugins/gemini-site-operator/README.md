# Gemini Site Operator

اپراتور هوشمند و ناظر دائمی کل سایت وردپرس — متصل مستقیم از سمت سرور به Gemini API با کلید شخصی کاربر.

## ساختار فایل‌ها

```
gemini-site-operator/
├── gemini-site-operator.php          بوت‌استرپ، ثابت‌ها، بارگذاری، هوک فعال‌سازی
├── uninstall.php                     حذف محافظت‌شده (اختیاری با تنظیم)
├── readme.txt                        توضیحات استاندارد وردپرس
├── README.md                         همین سند
├── CHANGELOG.md
├── includes/
│   ├── class-gso-settings.php        تنظیمات، رمزنگاری/رمزگشایی کلید API، مدل‌ها
│   ├── class-gso-install.php         اسکیمای ۴ جدول، dbDelta، فعال/غیرفعال، کران‌ها
│   ├── class-gso-logger.php          لاگ عملیات در جدول اختصاصی + حذف داده حساس
│   ├── class-gso-limiter.php         محدودیت نرخ، سهمیه روزانه، Backoff نمایی + Jitter
│   ├── class-gso-contract.php        قرارداد JSON خروجی Gemini: پاکت، اسکیما، استانداردسازی
│   ├── class-gso-gemini.php          کلاینت Gemini: wp_remote_post، JSON schema، سوئیچ مدل
│   ├── class-gso-seo.php             پل Yoast/Rank Math/AIOSEO + متای جایگزین + JSON-LD
│   ├── class-gso-issues.php          CRUD نواقص، آمار، امتیاز سلامت، برچسب‌ها
│   ├── class-gso-snapshot.php        جمع‌آوری وضعیت، ثبت، بازگردانی
│   ├── class-gso-auditor.php         موتور ممیزی دسته‌ای (posts→attachments→terms→menus→site)
│   ├── class-gso-generator.php       تولید محتوا، متا، Alt، لینک داخلی، CSS
│   ├── class-gso-fixer.php           هندلرهای اصلاح + پیش‌نمایش/Diff
│   ├── class-gso-queue.php           صف پس‌زمینه: بچ، بودجه زمانی، Pause/Resume/Cancel/Retry
│   ├── class-gso-watcher.php         هوک‌های رویدادمحور + صف کوتاه + اسکن دوره‌ای
│   ├── class-gso-updater.php         به‌روزرسانی خودکار از مخزن GitHub (نسخه + بستهٔ dist)
│   ├── class-gso-admin.php           منو، assets، register_setting، admin-post
│   ├── trait-gso-admin-pages.php     داشبورد، صف، ابزارها، تنظیمات، گزارش‌ها
│   └── trait-gso-admin-ajax.php      هندلرهای AJAX
└── assets/
    ├── css/admin.css                 رابط RTL
    └── js/admin.js                   fetch بدون jQuery، مودال Diff، پولینگ اسکن
```

## جریان کاری

```
[هوک ذخیره/انتشار]  ─┐
[اسکن دوره‌ای]       ─┼→ صف کوتاه بررسی ─→ GSO_Auditor (دسته‌ای)
[شروع اسکن دستی]    ─┘                          │
                                                ▼
                                   جدول gso_issues (fingerprint یکتا)
                                                │
                     ┌──────────────────────────┼─────────────────────────┐
                     ▼                          ▼                         ▼
             پیش‌نمایش قبل/بعد          پیشنهاد با Gemini        اعمال با Snapshot
                     │                          │                         │
                     └────────────► صف gso_queue (تکی/دسته‌ای) ◄──────────┘
                                                │
                                                ▼
                                   gso_snapshots → بازگشت یک‌کلیکی
```

### چرخه اسکن

`posts` → `attachments` → `variations` (ووکامرس) → `terms` → `menus` → `site` → `finish`

وضعیت در آپشن `gso_scan_state` ذخیره می‌شود؛ هر تیک یک دسته (پیش‌فرض ۱۰ آیتم) را پردازش می‌کند. پس از هر دسته، رویداد تک‌باره `gso_scan_followup` (۱۵ تا ۲۰ ثانیه بعد) دسته بعدی را اجرا می‌کند و `gso_scan_tick` هر ۵ دقیقه فقط نقش تور نجات دارد.

- **بودجه زمانی:** اگر پردازش یک دسته از `scan_time_budget` (پیش‌فرض ۲۰ ثانیه) بگذرد، دسته تمیز بسته می‌شود و `offset` دقیقاً به تعداد آیتم‌های بررسی‌شده جلو می‌رود؛ ادامه از همان نقطه در تیک بعد.
- **حافظه:** فهرست شناسه‌ها با `fields=ids`، `no_found_rows`، `cache_results=false` و پیمایش صفحه‌ای گرفته می‌شود؛ هر آیتم در لحظه پردازش خوانده می‌شود.
- **امنیت اجرا:** خطای یک آیتم، کل اسکن را متوقف نمی‌کند و در `errors` / `last_error` ثبت می‌شود.

### صف پس‌زمینه و کنترل نرخ

- صف کارهای سنگین در بچ‌های `queue_batch_size` (پیش‌فرض ۳) اجرا می‌شود و هر اجرا سقف `queue_time_budget` (پیش‌فرض ۲۵ ثانیه) دارد.
- زنجیره‌ی اجرا: پس از هر دسته، رویداد تک‌باره `gso_queue_followup` زمان‌بندی می‌شود؛ دیگر لازم نیست تا تیک پنج‌دقیقه‌ای صبر شود.
- قفل هم‌زمانی: ترنزینت `gso_queue_lock` با TTL ۱۲۰ ثانیه و توکن مالکیت؛ قفل فقط توسط همان اجرا آزاد می‌شود.
- وضعیت‌های قلم: `pending` → `running` → `done` / `failed` / `skipped` و شمارنده `attempts`.
- **Pause / Resume / Cancel / Retry** در داشبورد و صفحه صف؛ Resume اقلام کنارگذاشته‌شده با Cancel را دوباره به صف برمی‌گرداند.
- **سطوح خودکارسازی:** `report_only` فقط گزارش، `safe_auto` فقط موارد `low` و `safe` را خودکار اعمال می‌کند و `review_required` هیچ اصلاحی را خودکار نمی‌نویسد (پیشنهاد آماده و منتظر تایید می‌ماند).
- **Action Scheduler:** اگر نصب باشد رویدادهای تک‌بارهٔ صف/اسکن/پایش با `as_schedule_single_action` در گروه `gemini-site-operator` زمان‌بندی می‌شوند؛ در غیر این صورت WP-Cron.
- **کنترل پیش از نوشتن:** `GSO_Fixer::validate_target()` وجود شیء، تطبیق نوع، خارج نبودن از سطل زباله و دسترسی فعلی کاربر را قبل از Snapshot بررسی می‌کند.
- با اتمام سهمیه روزانه، صف به `paused_quota` می‌رود و زمان از سرگیری (نیمه‌شب سایت) نمایش داده می‌شود.
- پیشرفت واقعی (`processed / total`)، سرعت آیتم در دقیقه، تعداد خطا، ETA و آخرین لاگ در داشبورد به‌صورت زنده به‌روزرسانی می‌شوند.

## جدول‌های دیتابیس

| جدول | نقش | کلیدها |
|---|---|---|
| `wp_gso_issues` | ایرادها و payload اصلاح | `UNIQUE(fingerprint)`، `(object_type,object_id)`، `(status,severity)` |
| `wp_gso_queue` | اقلام اصلاح | `(status,priority)`، `issue_id` |
| `wp_gso_snapshots` | نسخه قبل از تغییر | `(object_type,object_id)`، `issue_id` |
| `wp_gso_logs` | گزارش عملیات | `level`، `created_at` |

`fingerprint = md5(object_type|object_id|issue_type)`؛ اسکن مجدد ردیف را به‌روزرسانی می‌کند و اگر موردی قبلاً «اصلاح‌شده» بوده و دوباره پیدا شود، به «باز» برمی‌گردد (تشخیص بازگشت مشکل).

## سطوح خودکارسازی

| سطح | رفتار صف خودکار |
|---|---|
| `report_only` | فقط کشف و گزارش؛ هیچ اصلاحی اجرا نمی‌شود |
| `safe_auto` | موارد `safe` (Alt، متا، خلاصه، توضیح دسته، اسکیما، شناسه تکراری) اعمال می‌شوند |
| `review_required` | پیشنهاد آماده می‌شود و تا تأیید اپراتور اعمال نمی‌شود |

اقلامی که اپراتور دستی به صف می‌فرستد (`action=fix`) همیشه با Snapshot اعمال می‌شوند.

## هندلرهای اصلاح

`set_meta_title`، `set_meta_description`، `set_alt`، `set_inline_alt`، `expand_content`، `add_cta`، `insert_h1`، `normalize_h1`، `inject_schema`، `set_excerpt`، `set_term_description`، `dedupe_ids`، `remove_broken_link`، `clear_debug_log`، `set_short_description`، `structure_product_content`، `set_product_alt`، `assign_product_terms`، `insert_product_links`، `insert_internal_links` و `manual` (فقط گزارش).

هر هندلر پیش از اجرا `GSO_Snapshot::collect_state()` را ثبت می‌کند: محتوا/عنوان/خلاصه، متاهای سئو و Alt، متای محصول (قیمت، موجودی، تصویر شاخص و گالری)، تخصیص دسته/برچسب محصول، توضیح ترم، یا CSS سفارشی.

## ممیزی تخصصی محصول و فیلدهای سئو

### ووکامرس (در صورت فعال بودن)

| بررسی | نوع ایراد | اصلاح |
|---|---|---|
| نبود/کم‌حجمی توضیحات کامل | `woo_missing_description` | `structure_product_content` |
| نبود/کم‌حجمی توضیح کوتاه | `woo_missing_short_description`، `woo_short_description_short` | `set_short_description` |
| تصویر شاخص/گالری و `Alt` تصاویر محصول | `woo_missing_image`، `woo_missing_gallery`، `attachment_missing_alt` | `set_product_alt` / دستی |
| قیمت و وضعیت موجودی (محصول و تنوع) | `woo_missing_price`، `woo_stock_unclear`، `woo_variation_*` | دستی (افزونه حدس نمی‌زند) |
| دسته‌بندی و برچسب | `woo_missing_category`، `woo_missing_tag` | `assign_product_terms` |
| یکنواختی ساختار معرفی | `woo_inconsistent_structure` | `structure_product_content` |
| نبود لینک داخلی | `missing_internal_links` | `insert_product_links` |

تولید محتوای محصول فقط از دادهٔ موجود (نام، SKU، ویژگی‌های ثبت‌شده، دسته و متن فعلی) استفاده می‌کند و در پرامپت صریحاً جعل عدد/ابعاد/جنس/گارانتی ممنوع شده است. قیمت و موجودی هرگز خودکار پر یا حدس زده نمی‌شوند.

### فیلدهای سئو

- افزونه فعال به‌صورت خودکار شناسایی می‌شود: Yoast (`_yoast_wpseo_*`)، Rank Math (`rank_math_*`)، AIOSEO (`_aioseo_*`)، SEOPress (`_seopress_titles_*`). در نبود همه، از متای اختصاصی `_gso_meta_title` / `_gso_meta_description` استفاده و در `wp_head` چاپ می‌شود.
- «نبود متای اختصاصی» جدا از عنوان مؤثری که افزونه سئو از عنوان نوشته می‌سازد تشخیص داده می‌شود.
- تکراری بودن عنوان، توضیحات متا و محتوا با نمایهٔ هش‌محور در طول اسکن شناسایی و در پایان آن گزارش می‌شود.
- هر نوشتن متا با پیش‌نمایش قبل/بعد، طول مجاز و Snapshot قابل بازگشت انجام می‌شود.

## Gemini API

- Endpoint: `https://generativelanguage.googleapis.com/v1beta/models/{model}:generateContent`
- کلید در هدر `x-goog-api-key` (نه در URL و لاگ).
- `systemInstruction` از تنظیمات، `responseMimeType: application/json` و `responseSchema` برای خروجی ساختاریافته.
- محدودیت نرخ در `GSO_Limiter`: پنجره دقیقه‌ای (`rate_limit_per_min`)، سقف روزانه (`daily_request_limit`، ۰ = بی‌نهایت) و فاصله `request_delay_ms`.
- خطاهای ۴۲۹/۵۰۰/۵۰۲/۵۰۳/۵۰۴ به‌صورت `retryable` علامت می‌خورند و **Backoff نمایی با Jitter** (۲→۴→۸… تا `backoff_max_seconds`) اعمال می‌شود؛ آیتم به `pending` برمی‌گردد (حداکثر ۵ تلاش).
- خطای ۴۲۹ مربوط به سهمیه روزانه، صف را به `paused_quota` می‌برد تا درخواست بی‌نتیجه ارسال نشود.
- **سوئیچ خودکار مدل:** اسکن و اصلاح سریع با `bulk_model`، تولید/بازنویسی با `deep_model`؛ در صورت در دسترس نبودن مدل (۴۰۴/۴۰۰) یک‌بار مدل دیگر امتحان می‌شود (قابل خاموش‌کردن با `auto_model_switch`).
- `timeout` هر درخواست بین ۲۰ تا ۱۲۰ ثانیه (پیشنهاد ۴۵–۶۰) و هرگز بیشتر از بودجه زمانی دسته نیست.

### قرارداد JSON خروجی (نسخهٔ ۱.۰)

- `GSO_Contract` تک‌منبع اسکیما، متن قرارداد و اعتبارسنجی است؛ با پرچم `contract`، متن قرارداد به پرامپت افزوده و پاسخ به پاکت استاندارد تبدیل می‌شود:
  `schema_version`، `mode` (`audit | fix_preview | bulk_fix`)، `target` (`object_type`، `object_id`، `url`)، `health_score`، `summary`، `issues`، `proposed_changes` و `warnings`.
- هر آیتم `issues` شامل `issue_id`، `category` (`seo | content | structure | media | woocommerce | technical`)، `severity` (`critical | high | medium | low`)، `priority_rank`، `title`، `description`، `field_path`، `current_value_excerpt`، `recommended_action`، `auto_fix_safe` و `requires_manual_approval` است.
- `proposed_changes` چهار بخش دارد: `seo` (عنوان/توضیح متا، کلیدواژه، canonical، robots)، `content` (عنوان، HTML، خلاصه، یادداشت هدینگ‌ها، لینک‌های داخلی)، `media` (Alt تصاویر) و `woocommerce` (توضیح کوتاه/کامل، دسته و برچسب، Alt شاخص/گالری، محصولات مکمل، بررسی قیمت و موجودی، `unverified_specs_note`).
- اگر JSON ناقص یا نامعتبر برگردد، یک بار درخواست ترمیمی با دمای پایین‌تر و توکن بیشتر فرستاده می‌شود؛ در صورت شکست دوم، خطای `gso_json_failed` با دلیل ثبت و آیتم `failed` علامت می‌خورد و بچ بعدی ادامه می‌یابد.
- `GSO_Contract::normalize()` کلیدهای ناشناخته را حذف، نوع‌ها را اصلاح، مقادیر غیرمجاز را به مقدار امن تبدیل و ایرادها را بر اساس سطح و `priority_rank` مرتب می‌کند؛ ورودی خام مدل هرگز بی‌واسطه ذخیره نمی‌شود.

## به‌روزرسانی از GitHub

- منبع: شاخهٔ `main` مخزن `hparsian/peyvand-amn-plugins` (هدر `Update URI` در فایل اصلی، بدون وابستگی به wordpress.org).
- نسخهٔ تازه از `plugins/gemini-site-operator/gemini-site-operator.php` (خط `Version:`) خوانده می‌شود و بسته از `dist/gemini-site-operator-<version>.zip` همان شاخه نصب می‌گردد.
- نتیجه شش ساعت کش می‌شود (`gso_github_release`)؛ اگر نسخهٔ ریموت بزرگ‌تر باشد و بسته در دسترس باشد، در فهرست به‌روزرسانی‌ها و برای **به‌روزرسانی خودکار** قرار می‌گیرد. اگر بسته پیدا نشود، چیزی پیشنهاد نمی‌شود و دلیل در گزارش عملیات ثبت می‌شود.
- هنگام فعال‌سازی، این افزونه به گزینهٔ `auto_update_plugins` اضافه می‌شود؛ برای خاموش‌کردن خودکار: فیلتر `gso_github_auto_update` را `false` کنید.
- برای فورک: در `wp-config.php` مقدار `GSO_GITHUB_REPO` (به شکل `owner/repo`) و در صورت نیاز `GSO_GITHUB_BRANCH` را تعریف کنید.
- انتشار نسخهٔ تازه: `Version` و `GSO_VERSION` را بالا ببرید، `dist/gemini-site-operator-<version>.zip` را بازسازی و کامیت کنید و به `main` پوش کنید. تا زمانی که zip هم‌نام نسخه نباشد، آپدیت پیشنهاد نمی‌شود.

## AJAX tasks

`scan_start`, `scan_stop`, `scan_status`, `queue_run`, `queue_status`, `queue_pause`, `queue_resume`, `queue_cancel`, `queue_retry`, `queue_retry_item`, `test_api`, `fix_preview`, `fix_prepare`, `fix_apply`, `issue_ignore`, `issue_reopen`, `bulk_fix`, `bulk_enqueue`, `bulk_filter`, `rollback`, `ai_review` — همه از `admin-ajax.php?action=gso_ajax` با nonce `gso_ajax`.

## هوک‌های وردپرس

- Actionهای افزونه: `gso_queue_tick`، `gso_queue_followup`، `gso_scan_tick`، `gso_scan_followup`، `gso_watch_tick`، `gso_schedule_scan`
- فیلتر: `gso_cta_keywords` برای سنجش CTA
- Menu slugها: `gso-dashboard`، `gso-queue`، `gso-tools`، `gso-settings`، `gso-logs`

## امنیت

- `ABSPATH` در همه فایل‌ها، `manage_options` در منوها و AJAX.
- `check_admin_referer` برای فرم‌های admin-post و `check_ajax_referer` برای AJAX.
- `sanitize_*` برای ورودی‌ها، `esc_html/esc_attr/esc_url/wp_kses_post` برای خروجی‌ها.
- `$wpdb->prepare` در همه کوئری‌های دارای متغیر.
- کلید API با AES-256-CBC و کلید مشتق‌شده از `wp_salt('auth')` رمزنگاری می‌شود؛ در لاگ‌ها با `scrub()` حذف می‌شود.

## نیازمندی‌ها

WordPress 6.6+، PHP 8.1+، اختیاری: WooCommerce، Yoast SEO / Rank Math / AIOSEO.
