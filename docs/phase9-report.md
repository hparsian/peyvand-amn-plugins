# فاز ۹ — ترمیم رجیستری‌های هسته

تاریخ: ۲۰ آبان ۱۴۰۵ (2026-11-11)
وضعیت: پیاده‌سازی‌شده — **تأیید نحوی و ایستا انجام شد؛ اجرای زنده انجام نشده (NOT VERIFIED)**

مرجع: `docs/ECOSYSTEM-MASTER-ARCHITECTURE.md`

## ۱. بررسی قبل از تغییر (نتیجه‌ها)

| پرسش | یافته (از کد) |
|---|---|
| Table Registry چه می‌کند؟ | فقط `register()`/`get_all()` روی آرایهٔ استاتیک؛ **هیچ** ساخت/حذف/مهاجرت/اعتبارسنجی جدول انجام نمی‌دهد |
| Capability Registry چه می‌کند؟ | فقط ثبت نام/برچسب/پلاگین/نقش‌های دارنده؛ **هیچ** `add_cap` یا `add_role` انجام نمی‌دهد |
| مالک واقعی جدول‌ها | `pas_field_maps` و `pas_field_provenance` → هسته (`class-field-mapper.php`)؛ `zsm_mail_queue` → افزونهٔ smtp-mailer (`self::table()` خط ۷۵۵) |
| آیا ثبت در رجیستری بر امنیت اثر دارد؟ | **نه.** رجیستری صرفاً شناسایی/نمایش است؛ مرزهای دسترسی از `capability` و `require_case_access` می‌آیند. بنابراین ثبت جدول جدید ریسک رفتاری ندارد |
| نقش `pas_cf_therapist` کجا ساخته می‌شود؟ | `Pas_CF_Security::ensure_therapist_role()` روی activation و در چرخهٔ `plugins_loaded` پرونده؛ فقط `read` + `pas_manage_case_files` و اگر `pas_view_all_case_files` داشته باشد حذف می‌شود |
| سه محل تکرار دسترسی | `Pas_CF_Security::current_user_can_access_case()` (PHP)، `list_for_therapist()` (SQL)، `Pas_CF_Reports::scope()` (SQL) — نمای زوجی از قبل از تابع Security استفاده می‌کرد |

### پشتیبان قبل از تغییر

`backups/pre-phase9-2026-11-11/` شامل ۱۰ فایل درگیر + `SHA256SUMS.txt` (همان
الگوی فاز ۰).

## ۲. فایل‌های تغییرکرده

| فایل | نوع تغییر |
|---|---|
| `plugins/pivand-amn-core/pivand-amn-core.php` | نسخه 1.6.1 + ۲ ثبت جدول جدید + ثبت نقش + `granted_to` |
| `plugins/pivand-amn-core/includes/class-table-registry.php` | dedupe + `table_names()` |
| `plugins/pivand-amn-core/includes/class-capability-registry.php` | dedupe + `register_role()` + `get_roles()` + `role_status()` |
| `plugins/pivand-amn-core/includes/class-admin.php` | جدول «نقش‌های ثبت‌شده» |
| `plugins/pivand-amn-core/CHANGELOG.md` | ورودی 1.6.1 |
| `plugins/pivand-amn-case-file/pivand-amn-case-file.php` | نسخه 1.15.1 |
| `plugins/pivand-amn-case-file/includes/class-security.php` | قرارداد مرکزی دسترسی (۴ متد) |
| `plugins/pivand-amn-case-file/includes/class-case-file.php` | `list_for_therapist()` روی قرارداد مرکزی |
| `plugins/pivand-amn-case-file/includes/class-reports.php` | `scope()` روی قرارداد مرکزی |
| `plugins/pivand-amn-case-file/CHANGELOG.md` | ورودی 1.15.1 |
| `docs/phase9-report.md`, `docs/phase9-test-results.md` | جدید |
| `docs/ECOSYSTEM-MASTER-ARCHITECTURE.md`, `docs/staging-test-plan.md` | به‌روزرسانی نسخه/هش/مغایرت‌ها |

هیچ فایل دیگری (به‌ویژه هیچ افزونهٔ خارج از هسته/پرونده) تغییر نکرد —
با `diff` نسبت به `backups/pre-phase9-2026-11-11/` تأیید شد.

## ۳. تغییرات دقیق کد

### ۲.۱. هسته — `pivand-amn-core` 1.6.0 → **1.6.1**

**`includes/class-table-registry.php`**
- حذف ثبت تکراری **در سطح نام جدول**: اگر یک جدول در دو گروه ثبت شود، فقط
  اولین ثبت نگه داشته می‌شود؛ گروه خالی ساخته نمی‌شود.
- `table_names()`: فهرست تخت و یکتای نام جدول‌ها.
- هیچ تابع ساخت/حذف/مهاجرت اضافه نشد (عمدی).

**`pivand-amn-core.php`** (فقط ثبت، بدون تغییر رفتار):
- `pas_` (هسته، نگاشت فیلد): `pas_field_maps`، `pas_field_provenance`.
- گروه جدید `zsm_`: `zsm_mail_queue` (مالک: smtp-mailer؛ فقط برای شناسایی —
  هیچ `class_exists`/فراخوانی از آن افزونه انجام نمی‌شود).
- `pas_manage_case_files` حالا دارنده‌اش `['administrator', 'pas_cf_therapist']`
  اعلام شده (قبلاً فقط administrator).
- `register_role('pas_cf_therapist', …, ['pas_manage_case_files'], ['read'])`.

**`includes/class-capability-registry.php`**
- `register()`: ثبت تکراری کپابیلیتی نادیده گرفته می‌شود؛ `granted_to` یکتا.
- `register_role()`: ثبت نقش با برچسب، پلاگین سازنده، کپابیلیتی‌های اعلام‌شده
  و دسترسی‌های پایه (`extra_access`، پیش‌فرض `read`).
- `role_status($role)`: مقایسهٔ نقش اعلام‌شده با `get_role()` واقعی →
  `exists`، `missing` (cap اعلام‌شده که نیست)، `unexpected` (cap ناخواسته که
  هست). فقط‌خواندنی؛ هیچ نقشی تغییر نمی‌کند.

**`includes/class-admin.php`**
- بخش «نقش‌های ثبت‌شده» با وضعیت واقعی هر نقش (مطابق/کمبود/اضافه/ساخته‌نشده).

### ۲.۲. پرونده — `pivand-amn-case-file` 1.15.0 → **1.15.1**

**`includes/class-security.php`** — قرارداد مرکزی دسترسی:
- `user_can_access_case_row($user_id, $assigned, $created, ?$view_all)`:
  قانون واحد (`view_all` → همه؛ `assigned = user`؛ یا `assigned = 0 && created = user`).
- `current_user_can_access_case()` فقط روی همین متد سوار است (سازگاری API).
- `case_scope_sql($user_id, $alias = 'c', $view_all = false)`: همان قانون به
  شکل شرط SQL با alias.
- `current_user_case_scope($alias = 'c')`: محدودهٔ کاربر جاری.

**`includes/class-case-file.php`**
- `list_for_therapist()` از `case_scope_sql($therapist_id, '', false)` استفاده
  می‌کند (همان SQL قبلی، ولی از منبع واحد).

**`includes/class-reports.php`**
- `scope()` فقط `Pas_CF_Security::current_user_case_scope('c')` را برمی‌گرداند.

**بدون تغییر:** `get_couple_sessions()` (از قبل `current_user_can_access_case`
روی پروندهٔ پارتنر را چک می‌کند) و همهٔ امضاهای عمومی.

## ۴. جدول‌های ثبت‌شده (وضعیت نهایی)

| گروه | جدول‌ها | مالک واقعی |
|---|---|---|
| `zdp_` | bookings, messages, surveys, leads, intake | client-portal |
| `cpp_` (legacy) | bookings, messages, surveys, leads | client-portal (مهاجرت) |
| `pas_cf_` | cases, notes, note_history, audit_log | case-file |
| `psy_` | invitations, attempts, couples, drafts, logs | quiz-enterprise |
| `pas_` | sms_log | sms-bridge |
| `pas_` | clients, client_identifiers, identity_conflicts | core (فاز ۱) |
| `pas_` | **field_maps, field_provenance** | core (فاز ۲) — **جدید در فاز ۹** |
| `aco_` | queue, logs, keyword_bank | content-writer |
| `payvand_` | messages | payvand-contact |
| `zsm_` | **mail_queue** | smtp-mailer — **جدید در فاز ۹** |

ثبت تکراری: با dedupe جدید، هر نام جدول فقط یک‌بار شمرده می‌شود؛ بنابراین
داشبورد انطباق «n از m جدول غایب است» را درست می‌شمارد.

## ۵. نقش و کپابیلیتی‌ها

| نقش | اعلام‌شده در رجیستری | کد واقعی | هم‌خوانی |
|---|---|---|---|
| `pas_cf_therapist` | `pas_manage_case_files` + `read` | `ensure_therapist_role()` همان را می‌دهد و `pas_view_all_case_files` را حذف می‌کند | ✅ (بررسی ایستا) |

هیچ کپابیلیتی مدیریتی (manage_options، edit_posts، …) به این نقش داده نشده و
در این فاز هم اضافه نشد.

## ۶. تست‌ها

جزئیات کامل: `docs/phase9-test-results.md`.

- موفق (ایستا): پارسر PHP روی ۸ فایل؛ ثبت سه جدول در کد؛ نبود ثبت تکراری در
  منطق؛ استفادهٔ هر سه نقطه از قرارداد مرکزی؛ نبود SQL تکراری باقی‌مانده؛
  `unzip -t` و ساخت بسته‌ها.
- **NOT VERIFIED (نیازمند staging)**: نصب تازه، ارتقای روی نصب موجود، نصب
  بدون ترتیب، نبود Fatal در نبود هسته، ثبت واقعی نقش/دسترسی، شمارش جدول‌های
  غایب در داشبورد، جلوگیری از دسترسی غیرمجاز و عدم افشا در نمای زوجی.

## ۷. ریسک‌های باقی‌مانده

1. نقش `pas_cf_therapist` فقط وقتی ساخته می‌شود که افزونهٔ پرونده فعال/آپدیت
   شود؛ در رجیستری به‌درستی «ساخته‌نشده» نمایش داده می‌شود، ولی ساخت خودکار
   از سمت هسته انجام نمی‌شود (عمدی: مالکیت نقش با پرونده است).
2. `zsm_mail_queue` فقط ثبت شده؛ اگر افزونهٔ SMTP نصب نباشد، داشبورد انطباق
   آن را «جدول غایب» می‌شمارد. این رفتار مورد انتظار است ولی ممکن است نوتیس
   ایجاد کند.
3. خواندن مستقیم جدول افزونه‌های دیگر توسط رجیستری هویت (مستند در معماری)
   در این فاز **رفع نشد**؛ خارج از دامنهٔ «ثبت» بود.
4. هم‌خوانی سه نقطه دسترسی اکنون با کد مشترک تضمین می‌شود، ولی **هیچ تست
   خودکاری** برای جلوگیری از بازگشت به کپی محلی اضافه نشده است.

## ۸. آمادگی staging

- بسته‌های این فاز: `pivand-amn-core-1.6.1.zip` و `pivand-amn-case-file-1.15.1.zip` — هسته در فاز ۱۰ به `dist/pivand-amn-core-1.7.0.zip` ارتقا یافت؛ نسخهٔ پرونده همان 1.15.1 است.
- پیش‌نیاز: ابتدا هسته، سپس پرونده (چون رجیستری نقش/جدول از هسته می‌آید).
- تست کلیدی این فاز در staging: صفحهٔ «زیرساخت پیوند امن» → بخش‌های «جدول‌های
  ثبت‌شده»، «نقش‌های ثبت‌شده» و «داشبورد امنیتی» باید هر سه جدول جدید را
  ببینند و نقش درمانگر «مطابق» باشد.
