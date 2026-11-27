# یادداشت ادغام کامل Peyvand Amn SEO Assistant Pro → ACO Content Writer v2.0.0

## وضعیت نهایی: همه‌ی قابلیت‌های واقعی هر دو پلاگین در یک پلاگین واحد

| قابلیت | منبع اصلی | وضعیت در v2.0.0 |
|---|---|---|
| امتیاز/چک‌لیست بلادرنگ سئو حین تایپ | Peyvand Amn (UI) + ACO (موتور) | ✅ `class-aco-live-analyzer.php` — UI از Peyvand، موتور امتیازدهی همان `ACO_SEO::run_audit()` (یک منبع واحد، بدون واگرایی) |
| کلیدواژه تمرکزی | هر دو (متاکی جدا) | ✅ یکی شد — همیشه `_yoast_wpseo_focuskw` (کلید مشترک ACO) |
| خوشه کلیدواژه روان‌شناسی | فقط Peyvand Amn | ✅ `ACO_SEO::psychology_keyword_clusters()` — فقط وقتی تنظیم niche به روان‌شناسی اشاره کند |
| پیشنهاد لینک داخلی | هر دو (Peyvand: رجیستری دستی؛ ACO: امتیازدهی+cache) | ✅ نسخه‌ی ACO (بهتر) نگه داشته شد و حالا در پنل جدید هم نمایش داده می‌شود |
| پیشنهاد متا دیسکریپشن | فقط Peyvand Amn | ✅ با استفاده از `ACO_SEO::truncate_meta_description()` موجود، بدون منطق تکراری |
| Schema: Article/FAQPage | هر دو (جدا) | ✅ یکپارچه شد — `ACO_SEO::generate_schema()` یک `@graph` واحد می‌سازد، نه دو بلاک `<script>` تکراری |
| Schema: MedicalWebPage/WebPage | فقط Peyvand Amn | ✅ اضافه شد — `MedicalWebPage` فقط وقتی niche روان‌شناسی باشد، وگرنه `WebPage` عمومی |
| Schema: BreadcrumbList | فقط Peyvand Amn | ✅ اضافه شد |
| Schema: VideoObject | فقط Peyvand Amn | ✅ اضافه شد (با حفظ فیکس duration واقعی، نه مقدار ثابت جعلی) |
| بهینه‌سازی خودکار alt تصاویر / preload ویدیو | فقط Peyvand Amn | ✅ `class-aco-content-optimizer.php` (فایل جدید) |
| دامنه‌ی اجرای schema | Peyvand: همه‌ی singular / ACO: فقط `_aco_generated` | ✅ حالا همه‌ی singular (post/page) — هم محتوای AI و هم دستی‌نوشته |

## باگ‌هایی که همزمان رفع شدند (نه فقط ادغام)

1. **تضاد FAQPage تکراری** (بین دو پلاگین جدا) — دیگر وجود ندارد چون فقط یک منبع schema هست.
2. **Cache invalidation وابسته به نانس فرم ادمین** (مشکل اصلی Peyvand Amn: آپدیت خودکار
   محتوا توسط Crawler/Queue کش را باطل نمی‌کرد) — در `ACO_SEO::invalidate_schema_cache()`
   و `ACO_Content_Optimizer::invalidate_cache()` این دو هوک کاملاً مستقل از `$_POST` هستند؛
   هر `save_post` (چه از فرم ادمین، چه برنامه‌نویسی‌شده) بلافاصله کش را باطل می‌کند.
3. duration جعلی ویدیو (`PT5M` ثابت در نسخه‌ی اول Peyvand Amn) — دوباره تکرار نشد؛
   فقط با `data-duration` واقعی ست می‌شود.

## فایل‌های جدید در این نسخه

- `includes/class-aco-live-analyzer.php`
- `includes/class-aco-content-optimizer.php`

## فایل‌های تغییریافته

- `includes/class-aco-seo.php` — `generate_schema/inject_schema_hooks` بازنویسی کامل +
  توابع کمکی جدید (`niche_looks_like_psychology`, `psychology_keyword_clusters`,
  `parse_faq_items`, `extract_videos`, `output_schema`, `invalidate_schema_cache`)
- `includes/trait-aco-admin-ajax.php` — AJAX جدید `ajax_seo_live_audit`
- `includes/class-aco-admin.php` — ثبت `ACO_Live_Analyzer::init()` و هوک AJAX جدید
- `aco-content-writer.php` — require فایل‌های جدید، فراخوانی `ACO_Content_Optimizer::init()`،
  نسخه ۲.۰.۰
- `uninstall.php` — پاکسازی transientهای جدید `aco_schema_*`

## کاری که هنوز روی سایت واقعی (نه این zip) لازم است

۱. پلاگین **Peyvand Amn SEO Assistant Pro** را از منوی افزونه‌های وردپرس غیرفعال و حذف
   کنید (نه پاک‌کردن دستی فایل) تا `uninstall.php` خودش اجرا و postmeta/option‌هایش
   پاک شود.
۲. روی staging تست کنید: بعد از غیرفعال‌سازی Peyvand Amn، `view-source` یک پست را چک
   کنید — باید دقیقاً **یک** بلاک `<script type="application/ld+json">` با یک `@graph`
   واحد ببینید (نه صفر، نه دو تا جدا).
۳. کلیدواژه تمرکزی پست‌های قدیمی را مهاجرت بدهید (چون متاکی ذخیره‌سازی از
   `_peyvand_amn_focus_keyword` به `_yoast_wpseo_focuskw` تغییر کرده). دیگر
   نیازی به وارد کردن دستی هر پست نیست — اسکریپت `migrate-focus-keywords.php`
   (کنار همین فایل) این کار را انبوه انجام می‌دهد:
   ```bash
   wp eval-file wp-content/plugins/pivand-amn-content-writer/migrate-focus-keywords.php
   # بعد از بررسی گزارش dry-run:
   wp eval-file wp-content/plugins/pivand-amn-content-writer/migrate-focus-keywords.php --live
   ```
   قبل از اجرای `--live` حتماً از دیتابیس بک‌آپ بگیرید. بعد از اجرا، این فایل
   را از سرور حذف کنید — نباید دائم روی سایت بماند.
