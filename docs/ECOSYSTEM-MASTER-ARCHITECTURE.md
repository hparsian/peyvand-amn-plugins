# معماری مادر اکوسیستم پیوند امن

نسخهٔ سند: ۱.۰ — تاریخ: ۲۰ آبان ۱۴۰۵ (2026-11-11)

این سند **مرجع معماری** اکوسیستم ۱۰ افزونه‌ای «پیوند امن» است: چه چیزی به چه
چیزی وصل است، از چه راهی، با چه مرزهای دسترسی، و کجاها بین مستندات و کد
فاصله افتاده.

## روش کار و معنای وضعیت‌ها

سند **از روی کد واقعی مخزن** نوشته شده است: نسخه‌ها از هدر و ثابت هر افزونه،
هوک‌ها از فراخوانی‌های `apply_filters/do_action` و `add_filter/add_action`،
جدول‌ها از `CREATE TABLE` و رجیستری جدول‌ها، و مرزهای دسترسی از
`current_user_can` / `require_case_access` / `permission_callback`.

برچسب‌ها:

- **✅ تأییدشده در کد** — خط کد یا ساختار موجود در مخزن دیده شده است.
- **⚠️ مغایرت** — مستندات/رجیستری با کد هم‌خوان نیست.
- **NOT VERIFIED** — رفتار زمان اجرا تأیید نشده است. PHP CLI، وردپرس و MySQL
  در محیط توسعه در دسترس نبودند؛ هیچ افزونه‌ای اجرا نشده است.

> هیچ ادعایی در این سند دربارهٔ «کار می‌کند» بر پایهٔ اجرا نیست؛ فقط دربارهٔ
> «کد چه می‌گوید».

---

## ۱. نمای کلی

### ۱.۱. فهرست افزونه‌ها و نسخه‌های واقعی کد

| افزونه | نسخه (هدر) | ثابت نسخه | نقش |
|---|---|---|---|
| `pivand-amn-core` | 1.7.0 | `PAS_CORE_VERSION` | هسته: رمزنگاری، تقویم شمسی، رجیستری‌ها، هویت مراجع، نگاشت فیلد، داشبورد انطباق |
| `pivand-amn-case-file` | 1.15.3 | `PAS_CF_VERSION` | پروندهٔ بالینی: مشخصات، جلسه‌ها، تایم‌لاین، گزارش، یادآور، نمای زوجی |
| `pivand-amn-client-portal` | 1.11.1.0 | `ZDP_VERSION` | پنل مراجع: ورود OTP، نوبت، پیام، فرم ارزیابی، لیدها |
| `pivand-amn-quiz-enterprise` | 3.6.7 | `PSY_VERSION` | آزمون‌های روان‌سنجی: دعوت، پاسخ، نتیجه، گزارش |
| `pivand-amn-sms-bridge` | 1.1.6 | `PAS_BRIDGE_VERSION` | تنها نقطهٔ اتصال به ملی‌پیامک + لاگ پیامک |
| `pivand-amn-2fa` | 1.3.4 | (ثابت نسخه ندارد) | ورود دومرحله‌ای ادمین (TOTP + کد پشتیبان) |
| `pivand-amn-smtp-mailer` | 1.5.3 | `ZSM_VERSION` | SMTP و صف ایمیل |
| `pivand-amn-content-writer` | 2.9.18 | `ACO_VERSION` | تولید محتوا/سئو (خارج از دامنهٔ بالینی) |
| `piwand-daily-feed` | 1.1.5 | `PWDF_VERSION` | فید روزانه (خارج از دامنهٔ بالینی) |
| `payvand-contact` | 1.4.2 | `PAYVAND_VERSION` | فرم تماس (کانال ورودی لید) |

### ۱.۲. لایه‌بندی

```
                       ┌──────────────────────────────┐
                       │  هسته (core) 1.7.0           │
                       │  Crypto · Jalali · Helpers   │
                       │  رجیستری‌ها · هویت · نگاشت   │
                       │  داشبورد انطباق              │
                       └───────┬──────────────┬───────┘
              کلاس‌های مشترک   │              │  فیلترها/آکشن‌ها
                               ▼              ▼
   ┌───────────────────┐   ┌───────────────────────┐   ┌──────────────────┐
   │ پنل مراجع (zdp_)  │◄─►│ پرونده (pas_cf_)      │◄─►│ آزمون‌ها (psy_)  │
   │ OTP · نوبت · فرم  │   │ جلسه · تایم‌لاین      │   │ دعوت · نتیجه     │
   └─────────┬─────────┘   │ گزارش · یادآور · زوجی │   └────────┬─────────┘
             │             └───────────┬───────────┘            │
             │  pas_send_sms           │                        │
             ▼                         ▼                        ▼
        ┌────────────────────────────────────────────────────────────┐
        │ پل پیامک (pas_sms_log)  ·  SMTP (zsm_mail_queue)           │
        └────────────────────────────────────────────────────────────┘
```

قاعدهٔ معماری هدف: **هر افزونه فقط در جدول‌های خودش می‌نویسد** و ارتباط
بین‌افزونه‌ای از طریق فیلتر/آکشن انجام می‌شود. این قاعده در عمل دو استثنا
مستند دارد (بخش ۵).

---

## ۲. قراردادهای ارتباطی (استخراج‌شده از کد)

### ۲.۱. قراردادهای فعال با provider و consumer

| هوک | نوع | provider (کد) | consumer (کد) | امضا |
|---|---|---|---|---|
| `pas_send_sms` | filter | `sms-bridge` (`Pas_Melipayamak::handle_send_sms_filter`) | پنل مراجع (OTP/نوبت)، آزمون‌ها (دعوت)، پرونده (یادآور) | `apply_filters('pas_send_sms', null, $mobile, $body_id, $params)` |
| `pas_lookup_known_client_name` | filter | زنجیره: پنل (۱۰) ← پرونده (۱۰) ← آزمون‌ها (۱۵) | پنل (ساخت/ورود کاربر)، آزمون‌ها، آژاکس مشترک هسته | `($default, $mobile)` |
| `pas_get_client` | filter | هسته، رجیستری هویت | **هیچ مصرف‌کننده‌ای ندارد** | `($default, $query)` |
| `pas_get_case_summary_for_phone` | filter | پرونده | **هیچ مصرف‌کننده‌ای ندارد** | `($default, $mobile)` |
| `zdp_get_intake_for_phone` | filter | پنل مراجع | پرونده (کارت فرم ارزیابی) | `($default, $mobile)` |
| `psy_get_completed_results_for_phone` | filter | آزمون‌ها | پرونده (فهرست لینک نتایج) | `($default, $phone)` |
| `psy_get_assessment_scores_for_phone` | filter | آزمون‌ها (3.6.7+) | پرونده (کارت روند ارزیابی) | `($default, $mobile)` |
| `psy_get_client_name_for_phone` | filter | آزمون‌ها | پنل (نام نمایشی)، پرونده (نام شناخته‌شده) | `($default, $phone)` |
| `psy_get_pending_quizzes_for_phone` / `..._for_email` | filter | آزمون‌ها | پنل مراجع | `($default, $phone|$email)` |
| `psy_get_mobile_for_email` | filter | آزمون‌ها | پنل (ورود ایمیلی) | `($default, $email)` |
| `pas_cf_timeline_events` | filter | پرونده (1.11.0+) | **هیچ مصرف‌کننده‌ای ندارد** | `($events, $case)` |
| `pas_cf_reminder_sms_pattern` | filter | پرونده (1.15.0+) | **هیچ مصرف‌کننده‌ای ندارد** (خودش در همان درخواست مصرف می‌شود) | `($pattern_id)` |
| `pas_compliance_checks` | filter | هسته (1.6.0+) | **هیچ مصرف‌کننده‌ای ندارد** | `($checks)` |
| `pas_core_trusted_proxy_cidrs` / `pas_core_client_ip` | filter | هسته | همهٔ افزونه‌ها (IP کاربر) | — |
| `zdp_quiz_url` | filter | پنل | آزمون‌ها/فانل | `($default, $topic)` |
| `zdp_intake_submitted` | action | پنل | پرونده/آزمون‌ها (گوش‌دهندهٔ ثبت‌شده دیده نشد) | — |
| `zdp_booking_confirmed_for_client` | action | پنل | — | — |
| `zdp_otp_login_pending_booking` | filter | پنل | نوبت‌دهی همان افزونه | `($extra, $user_id, $booking)` |
| `zdp_sms_supports_international` | filter | — | پنل (ارسال OTP بین‌المللی) | — |

نکته: `pas_cf_reminder_sms_pattern` خودش provider و consumer است (پرونده
مقدار پیش‌فرض را از آپشن می‌خواند و فیلتر را روی همان اعمال می‌کند)؛ در جدول
به‌عنوان «قرارداد قابل تزریق» ثبت شده است.

### ۲.۲. قراردادهای ثبت‌شده در رجیستری ولی بدون مصرف‌کننده

از دید توسعه، این‌ها **قراردادهای آماده‌ی آینده** هستند، نه جریان فعال:
`pas_get_client`، `pas_get_case_summary_for_phone`، `pas_cf_timeline_events`،
`pas_compliance_checks`. تا وقتی مصرف‌کننده اضافه نشود، تغییر امضایشان هزینهٔ
کم دارد — ولی بعد از اولین مصرف‌کننده باید ثابت بمانند.

### ۲.۳. آکشن‌های وردپرس که نقش قرارداد داخلی دارند

| هوک | provider | کاربرد |
|---|---|---|
| `wp_ajax_pas_core_lookup_client_name` | هسته | جست‌وجوی نام مراجع با موبایل (غیر admin-post) |
| `admin_post_pas_cf_*` (۹ اکشن) | پرونده | save_case، add/edit/delete/restore note، export_csv، print_case، apply_intake_map، send_reminder |
| `admin_post_pas_core_registry_rebuild` | هسته | بازسازی رجیستری هویت |
| `admin_post_zdp_admin_reply` | پنل | پاسخ ادمین به پیام |
| `wp_ajax_zdp_*` (و `nopriv`) | پنل | OTP، نوبت، پیام، فرم ارزیابی، آپلود رسید |
| `psy/v1/*` (REST) | آزمون‌ها | quiz، submit، draft، report، report/pdf |

---

## ۳. نقشهٔ دیتابیس

### ۳.۱. جدول‌ها به تفکیک افزونه

| افزونه | جدول‌ها (با پیشوند سایت) | ثبت در رجیستری هسته |
|---|---|---|
| هسته | `pas_clients`، `pas_client_identifiers`، `pas_identity_conflicts`، `pas_field_maps`، `pas_field_provenance` | سه جدول اول ✅ — **دو جدول فاز ۲ ثبت نشده‌اند ⚠️** |
| پرونده | `pas_cf_cases`، `pas_cf_notes`، `pas_cf_note_history`، `pas_cf_audit_log` | ✅ |
| پنل | `zdp_bookings`، `zdp_messages`، `zdp_surveys`، `zdp_intake`، `zdp_leads` + `cpp_*` (میراث) | ✅ (cpp_* با `is_legacy`) |
| آزمون‌ها | `psy_invitations`، `psy_attempts`، `psy_couples`، `psy_drafts`، `psy_logs` | ✅ |
| پل پیامک | `pas_sms_log` | ✅ |
| SMTP | `zsm_mail_queue` | **ثبت نشده ⚠️** |
| تولید محتوا | `aco_queue`، `aco_logs`، `aco_keyword_bank` | ✅ |
| فرم تماس | `payvand_messages` | ✅ |
| 2FA | **بدون جدول** (فقط `user_meta`) | — |
| فید روزانه | در گرپ `CREATE TABLE` جدولی دیده نشد | — (NOT VERIFIED) |

### ۳.۲. ستون‌های کلیدی و رمزنگاری

**`pas_cf_cases`** (پرونده): هویت (`phone`, `full_name`)، مشخصات
(`age`, `gender`, `marital_status`, `education`, `occupation`, `residence`,
`children_count`, `father_name`, `referral_source`, `birth_date` میلادی)،
پرچم‌های فاز ۲–۷ (`intake_submitted_at`, `reminder_consent`,
`reminder_channel`, `reminder_last_sent_at`)، لینک (`partner_case_id`)،
مالکیت/دسترسی (`assigned_therapist`, `created_by`, `status`), و ستون‌های
رمزنگاری‌شده: `*_encrypted` برای سوابق پزشکی/مشاوره/خودآسیب/مصرف مواد +
`symptom_*` + `q_schema_attachment` + نسخهٔ `spouse_*`.

**`pas_cf_notes`** (جلسه): `session_date` (DATE میلادی، نمایش شمسی)،
`session_number`، `content_encrypted`، `duration_minutes`، `status`
(`done|scheduled|cancelled|no_show`)، `next_session_date` (varchar تاریخ)،
`is_joint` (جلسهٔ مشترک).

**`pas_cf_note_history`**: snapshot نسخهٔ قبلی + `action`
(`edited|deleted|restored`) — هیچ‌وقت نسخهٔ فعلی.

**`pas_cf_audit_log`**: `case_id`, `user_id`, `action`, `ip`, `created_at`.

**`psy_invitations`**: `quiz_id`, `client_name`, `client_phone`,
`client_email`, `access_token` (یکتا), `status`, `attempt_id`, `expires_at`.
**`psy_attempts`**: `quiz_id`, `invitation_id`, `answers` و `result_data` —
هر دو **رمزنگاری‌شده** (wrapped با `Pas_Core_Crypto`).

**`zdp_intake`**: `payload` (wrapped) + متادیتای ثبت؛ قرارداد خواندن آن
فیلتر `zdp_get_intake_for_phone` است.

**`pas_field_maps`**: نگاشت منبع→مقصد با `transform` (`copy|date_jalali`) و
`sensitivity` (`public|clinical`). **`pas_field_provenance`**: منبع/زمان/کاربر
و **هش** مقدار قبلی هر فیلد (نه خود متن).

### ۳.۳. رجیستری‌هایی که فقط مستندسازی می‌کنند

`Pas_Core_Table_Registry`، `Pas_Core_Capability_Registry`،
`Pas_Core_Filter_Registry` هیچ‌کدام رفتار را تغییر نمی‌دهند: فقط نام/مالک/
وضعیت را برای پیشخوان ثبت می‌کنند. بنابراین «در رجیستری نیست» به معنی «کار
نمی‌کند» نیست، ولی یعنی در پیشخوان دیده نمی‌شود (بخش ۶).

---

## ۴. وابستگی‌های متقابل

### ۴.۱. وابستگی کد (کلاس/ثابت)

| افزونه | به هسته وابسته است؟ | جزئیات |
|---|---|---|
| پرونده | ✅ سخت | `Pas_Core_Crypto`, `Pas_Core_Helpers`؛ بدون هسته، نوتیس خطا و رمزنگاری غیرفعال |
| پنل | ⚠️ نرم | IP و برخی helperها؛ بود/نبود هسته را با `class_exists` مدیریت می‌کند |
| آزمون‌ها | ⚠️ نرم | رمزنگاری `Pas_Core_Crypto` (fallback متن ساده دارد — ریسک بخش ۷) |
| پل پیامک | ✅ نرم | `Pas_Core_Crypto` برای رمز اعتبارنامه/لاگ |
| 2FA | ✅ نرم | `Pas_Core_*` برای هش/تنظیمات (NOT VERIFIED جزئیات) |
| SMTP / محتوا / فید / تماس | مستقل | بدون وابستگی به هسته در گرپ‌ها |

### ۴.۲. وابستگی داده — خواندن مستقیم جدول افزونهٔ دیگر

این‌ها **نقض قاعدهٔ معماری** هستند و باید در نقشهٔ بدهی فنی بمانند:

| خواننده | جدول خوانده‌شده | فایل | نوع |
|---|---|---|---|
| هسته (رجیستری هویت) | `pas_cf_cases` | `class-client-registry.php:397` | خواندن برای ساخت ایندکس هویت |
| هسته (رجیستری هویت) | `psy_invitations` | `class-client-registry.php:442` | همان |
| هسته (رجیستری هویت) | `zdp_leads` | `class-client-registry.php:495` | همان |
| هسته (رجیستری هویت) | `payvand_messages` | `class-client-registry.php:538` | همان |
| پرونده | `zdp_bookings` | `class-case-file.php:1005` | نمایش نوبت‌ها (فقط‌خواندنی) |
| هسته (سنجش داده) | `psy_attempts`, `zdp_intake` | `class-data-audit.php:75-77` | شمارش/آزمون رمزگشایی نمونه‌ای |

پرونده **دیگر** جدول `psy_*` را نمی‌خواند (فاز ۵): روند آزمون‌ها از فیلتر
می‌آید ✅.

---

## ۵. مسیر کاربر (End-to-End)

```
بازدیدکننده
  │  ۱) شماره موبایل → درخواست کد
  ▼
[پنل] send-otp (REST/AJAX، nopriv) ── pas_send_sms ──► ملی‌پیامک
  │  ۲) کد ۵ دقیقه‌ای (transient) + شمارش تلاش
  ▼
[پنل] verify-otp → find_or_create_user(mobile)
  │      user_login = "zdp_" + موبایل (قاعدهٔ مستندشده در کد پرونده)
  │      نام: zdp_full_name ← psy_get_client_name_for_phone
  │                        ← pas_lookup_known_client_name (پنل ← پرونده ← آزمون)
  │      ورود واقعی: توکن یک‌بارمصرف → ناوبری به /panel/ (نه کوکی در پاسخ AJAX)
  ▼
[پنل] نوبت / پیام / فرم ارزیابی  ──►  zdp_bookings, zdp_messages, zdp_intake
  │      فرم ارزیابی (payload رمزنگاری‌شده)
  ▼
[پرونده] درمانگر پرونده را می‌سازد/باز می‌کند
  │  فیلتر zdp_get_intake_for_phone  →  کارت «فرم ارزیابی مراجع»
  │  پیش‌نمایش نگاشت (fill/same/conflict) → تأیید درمانگر → apply
  │  نوشتن در pas_cf_cases + ثبت منبع در pas_field_provenance
  ▼
[پرونده] جلسه‌ها (content رمزنگاری‌شده، متادیتای مدت/وضعیت/جلسهٔ بعد/مشترک)
  │  تایم‌لاین: ایجاد، فرم، نگاشت، جلسه، ویرایش/حذف، خروجی، یادآور
  ▼
[آزمون‌ها] درمانگر دعوت می‌فرستد (توکن یکتا) → مراجع پاسخ می‌دهد (REST عمومی)
  │  result_data رمزنگاری‌شده → گزارش در پیشخوان
  ▼
[پرونده] روند ارزیابی‌ها ← psy_get_assessment_scores_for_phone
  │  ارزیابی درمانگر: علائم، سوابق، CSI-16 (قبل/بعد)
  ▼
[پرونده] نمای زوجی: جلسه‌های دو پروندهٔ لینک‌شده، فقط متادیتا، مشروط به دسترسی هر دو
  ▼
[پرونده] یادآور جلسه (رضایت + کانال + فاصلهٔ ۱۲ ساعت) ── pas_send_sms / wp_mail
```

**وضعیت تأیید (به‌روزرسانی بازبینی):** مسیر بالا در اجرای واقعی روی
WordPress 7.1.4 + PHP 8.5.8 + SQLite در ۱۷ گام سرتاسری اجرا و **همه PASS**
شد — از جمله خواندن فرم ارزیابی با فیلتر، نگاشت به پرونده با ثبت منبع،
رمزنگاری/رمزگشایی یادداشت جلسه، دعوت و پاسخ آزمون و رسیدن نتیجه به پرونده،
نمای زوجی، ارسال یادآور تا لاگ پل پیامک، و مرز دسترسی درمانگر.
سناریو و خروجی خام: `docs/ecosystem-scenario.md` و
`docs/evidence/ecosystem-scenario-run.txt`؛ اسکریپت: `tests/ecosystem-scenario.php`.
موارد باقی‌ماندهٔ تأییدنشده (پیامک/ایمیل واقعی، تولید محتوای AI، درگاه پرداخت،
MySQL واقعی، جریان مرورگری 2FA) در همان سند فهرست شده‌اند.

---

## ۶. مدل دسترسی

### ۶.۱. capabilityها

| capability | مالک | معنا | ثبت در رجیستری |
|---|---|---|---|
| `pas_manage_case_files` | پرونده | ورود به پنل پرونده‌ها | ✅ |
| `pas_view_all_case_files` | پرونده | دیدن پرونده‌های همهٔ درمانگرها | ✅ |
| `zdp_manage_clients` | پنل | مدیریت مراجعین | ✅ |
| نقش `pas_cf_therapist` | پرونده | نقش «درمانگر» با فقط `pas_manage_case_files` (+`read`) | **⚠️ نقش در رجیستری کپابیلیتی نیامده** |

### ۶.۲. قانون دسترسی به پرونده (تک‌منبع در سه جا)

`Pas_CF_Security::current_user_can_access_case()`:
`PAS_CF_CAP_ALL` → همه؛ وگرنه `assigned_therapist = من` یا
(`assigned_therapist = 0` و `created_by = من`).

این قانون در **یک قرارداد مرکزی** پیاده شده است (فاز ۹):

1. `Pas_CF_Security::user_can_access_case_row()` — منطق PHP؛
   `current_user_can_access_case()` روی همین سوار است.
2. `Pas_CF_Security::case_scope_sql()` / `current_user_case_scope()` —
   همان قانون در SQL؛ `list_for_therapist()` و `Pas_CF_Reports::scope()`
   هر دو از همین می‌آیند.
3. نمای زوجی از `current_user_can_access_case(پروندهٔ پارتنر)` استفاده می‌کند.

✅ تکرار سه‌جای قبلی حذف شد. ⚠️ هنوز هیچ تست خودکاری برای جلوگیری از
بازگشت به کپی محلی وجود ندارد.

### ۶.۳. نقاط ورود و کنترل‌ها

| نقطهٔ ورود | احراز دسترسی | nonce/CSRF | یادداشت |
|---|---|---|---|
| ۹ اکشن `admin_post_pas_cf_*` | `require_access` + (برای پرونده‌های خاص) `require_case_access` | `check_admin_referer` روی همه ✅ | CSV فقط پرونده‌های مجاز همان کاربر ✅ |
| `admin_post_pas_core_registry_rebuild` | `manage_options` (NOT VERIFIED جزئیات) | دارد (۴۶ مورد nonce در گرپ) | — |
| `wp_ajax_pas_core_lookup_client_name` | `manage_options`/`PAS_CF_CAP` (در کد ثبت قرارداد ذکر شده) | nonce اختصاصی | — |
| `zdp/v1/send-otp`، `verify-otp` | **بدون احراز** (nopriv) | `X-WP-Nonce` با اکشن `wp_rest` | برای کاربر ناشناس nonce امنیت واقعی نیست؛ بار اصلی روی rate-limit/transient است (NOT VERIFIED) |
| `zdp/v1/booking-grid` | ناشناس | `wp_rest` nonce | همان نکته |
| `psy/v1/submit` و `psy/v1/draft` | `permission_callback => __return_true` | توکن دعوت ۴۰ کاراکتری | سطح حملهٔ عمومی؛ محافظت اصلی توکن + rate-limit |
| `psy/v1/report/{id}` | ادمین یا وجود `token` | صحت توکن در callback | NOT VERIFIED |
| `psy/v1/report/{id}/pdf` | permission_callback آن دیده نشد | — | NOT VERIFIED |

---

## ۷. تحلیل امنیت و احتمال نشت بین کاربران

### ۷.۱. کنترل‌های موجود (تأییدشده در کد)

- **جداسازی پرونده**: هر مسیر خواندن/نوشتن پرونده (نمایش، ویرایش، یادداشت،
  CSV، پرینت، نگاشت فرم، یادآور) از `require_case_access` می‌گذرد ✅.
- **رمزنگاری داده‌های بالینی**: یادداشت‌های جلسه، سوابق، علائم، نتیجهٔ آزمون
  و payload فرم ارزیابی رمزنگاری‌شده‌اند؛ کلید بیرون دیتابیس است ✅.
- **ثبت منبع بدون افشا**: `pas_field_provenance` فقط هش مقدار قبلی را نگه
  می‌دارد ✅.
- **نمای زوجی**: بدون متن یادداشت و مشروط به دسترسی به هر دو پرونده ✅.
- **پیش‌نمایش نگاشت**: مقادیر بالینی ماسک می‌شوند ✅.
- **لاگ دسترسی**: `pas_cf_audit_log` برای view/create/edit/note/export/print/
  apply/send_reminder ✅.

### ۷.۲. ریسک‌ها و نقاط ضعف (به‌ترتیب اهمیت)

1. **نشت بالقوه از رجیستری هویت (بالا)**: هسته برای ساخت ایندکس،
   `pas_cf_cases`/`psy_invitations`/`zdp_leads`/`payvand_messages` را مستقیم
   می‌خواند. اگر خروجی این رجیستری روزی به REST/فیلتر عمومی وصل شود، مرز
   «چه کسی چه پرونده‌ای را می‌بیند» دور زده می‌شود. امروز فیلتر
   `pas_get_client` فقط با `manage_options`/`PAS_CF_CAP` داده می‌دهد ✅، ولی
   این وابستگی داده باید در آینده به provider-filter تبدیل شود.
2. **REST عمومی آزمون (متوسط-بالا)**: `/submit` و `/draft` با
   `__return_true` باز هستند. امنیت بر توکن دعوت تکیه دارد؛ نبود rate-limit
   کافی یا نبود قفل پس از N تلاش نامعتبر، امکان brute-force/مصرف منابع می‌دهد
   (وجود rate-limit در `submit` NOT VERIFIED).
3. **nonce برای کاربر ناشناس (متوسط)**: `wp_rest` nonce برای ناشناس فقط CSRF
   است، نه احراز. اگر rate-limit OTP ضعیف باشد، امکان ارسال انبوه پیامک با
   هزینهٔ مالک سایت وجود دارد (NOT VERIFIED).
4. **کلید رمزنگاری، تک‌نقطهٔ شکست (متوسط)**: نبود کلید = غیرقابل‌خواندن شدن
   یادداشت‌های قبلی؛ گم‌شدن کلید = از دست رفتن دائمی داده. هیچ مسیر بازیابی
   خودکار وجود ندارد (عمدی).
5. **اعتبارنامهٔ پیامک (متوسط)**: اگر `uses_leaked_credentials()` مثبت شود،
   پیامک‌ها از حساب لو‌رفته ارسال می‌شوند. نوتیس + داشبورد انطباق وجود دارد
   ✅ ولی چرخش، دستی است.
6. **CSV لیست پرونده‌ها (پایین)**: شامل نام/موبایل/موضوع/درمانگر است — دادهٔ
   بالینی ندارد ✅، ولی خروجی فایل بدون رمز، ریسک عملیاتی دارد.
7. **پرینت/PDF پرونده (پایین)**: خروجی کامل بالینی با nonce و کنترل دسترسی؛
   ریسک اصلی، ذخیرهٔ فایل روی دستگاه مشترک است.
8. **نمای زوجی (پایین)**: نام پارتنر در بنر پرونده نمایش داده می‌شود، حتی اگر
   کاربر به پروندهٔ او دسترسی نداشته باشد (فقط نام/لینک، بدون جلسه). اگر
   سیاست «نام هم نباید دیده شود» باشد، این باید تغییر کند.
9. **آزمون‌ها بدون هسته (پایین)**: fallback متن ساده در نبود
   `Pas_Core_Crypto` — در سایت بدون هسته، نتایج آزمون رمزنگاری نمی‌شوند
   (نوتیس هشدار دارد).

---

## ۸. مغایرت‌ها و بدهی فنی (کد در برابر مستند/رجیستری)

| # | مورد | وضعیت |
|---|---|---|
| ۱ | `pas_field_maps` و `pas_field_provenance` در `Pas_Core_Table_Registry` ثبت نشده‌اند (فاز ۲) | ✅ رفع شد (فاز ۹ — هسته 1.6.1) |
| ۲ | `zsm_mail_queue` در رجیستری جدول‌ها نیست | ✅ رفع شد (فاز ۹ — فقط ثبت، بدون وابستگی) |
| ۳ | نقش `pas_cf_therapist` در `Pas_Core_Capability_Registry` نیست | ✅ رفع شد (فاز ۹ — `register_role` + اعتبارسنجی) |
| ۴ | `pas_cf_reminder_sms_pattern`، `pas_compliance_checks`، `pas_cf_timeline_events` در رجیستری فیلترها هستند ولی مصرف‌کنندهٔ بیرونی ندارند | ⚠️ قرارداد بدون مصرف‌کننده (مستند شده) |
| ۵ | قاعدهٔ دسترسی پرونده در سه نقطه تکرار شده (Security، Reports، couple) | ✅ رفع شد (فاز ۹ — قرارداد مرکزی در `Pas_CF_Security`) |
| ۶ | هسته ۴ جدول بیرونی را مستقیم می‌خواند (بخش ۴.۲) | ⚠️ نقض قاعدهٔ معماری |
| ۷ | پرونده `zdp_bookings` را مستقیم می‌خواند | ⚠️ نقض قاعدهٔ معماری (فقط‌خواندنی) |
| ۸ | `get_relationship_assessment()` برای سازگاری پرینت باقی مانده؛ منطق تکراری با `get_assessment_trend()` | ⚠️ بدهی فنی کوچک |
| ۹ | ستون‌های `next_session_date`/`reminder_last_sent_at` از نوع varchar هستند (نه date/datetime) برای جلوگیری از خطای strict mode | ✅ آگاهانه، مستند در فاز ۳/۷ |
| ۱۰ | 2FA ثابت نسخه ندارد (فقط هدر) — چک نسخه/مهاجرت ندارد | ⚠️ مغایرت جزئی |
| ۱۱ | گزارش‌های فازها (`docs/phase*-report.md`) اعداد/hash نسخه‌های قدیمی‌تر را دارند؛ سند حاضر مرجع نسخه‌هاست | ⚠️ تاریخچه، نه خطا |
| ۱۲ | هیچ تست خودکار (PHPUnit/E2E) در مخزن نیست | ⚠️ بدهی تست |

---

### ۸.۱. به‌روزرسانی فاز ۹ (ترمیم رجیستری)

- سه جدول جاافتاده ثبت شدند؛ ثبت تکراری در سطح نام جدول حذف شد.
- نقش‌ها حالا در رجیستری قابل ثبت و **اعتبارسنجی** در برابر نقش واقعی
  وردپرس هستند (`role_status`).
- قانون دسترسی پرونده از سه نسخه به یک قرارداد مرکزی رسید.
- **رجیستری همچنان فقط شناسایی است**: هیچ ساخت/حذف/مهاجرت/`add_cap` از
  رجیستری‌ها انجام نمی‌شود (تأیید کد).
- باقی‌ماندهٔ آگاهانه: خواندن مستقیم جدول افزونه‌های دیگر توسط رجیستری هویت
  (بخش ۴.۲) در این فاز رفع نشد.

### ۸.۲. فاز ۱۰ — داشبورد سلامت اکوسیستم

- صفحهٔ `pas-core-health` (زیرمنوی «زیرساخت پیوند امن»): وضعیت نصب/فعال بودن،
  نسخهٔ هدر/ثابت، جدول‌های اعلام‌شده و غایب، کپابیلیتی‌ها با نقش‌های دارنده،
  و نقش‌های مطابق/نامطابق — همه در یک جدول.
- منبع داده همان رجیستری‌های فاز ۹ است؛ هیچ فهرست موازی از جدول/کپابیلیتی
  ساخته نشد (کاتالوگ فقط slug/فایل/نسخه را نگه می‌دارد).
- کاملاً فقط‌خواندنی: نه `add_cap`، نه `dbDelta`، نه نوشتن گزینه.

## ۹. موارد NOT VERIFIED (صریح)

هیچ‌کدام از موارد زیر اجرا نشده‌اند؛ فقط وجود کد/ساختار تأیید شده است:

- اجرای `dbDelta` برای هر جدول/ستون جدید (فازهای ۱–۷) و بی‌اثر بودنش روی
  دادهٔ موجود.
- ساخت جداول با کالیشن/موتور واقعی سایت و رفتار `date/datetime NULL`.
- کل مسیر ورود OTP (ارسال واقعی پیامک، transient، `find_or_create_user`،
  `Login_Service`، کوکی نهایی).
- ارسال واقعی پیامک (ملی‌پیامک + الگو) و ایمیل (`wp_mail`/SMTP).
- رمزگشایی واقعی `result_data`/`content_encrypted` روی دادهٔ سایت.
- درستی اعداد گزارش‌ها و سازگاری `GROUP BY … HAVING` با `ONLY_FULL_GROUP_BY`.
- نمایش واقعی کارت‌های تایم‌لاین/روند/زوجی/یادآور/داشبورد انطباق و چیدمان RTL.
- رفتار صفحات با نقش «درمانگر» و با کاربر بدون `pas_view_all_case_files`.
- عملکرد rate-limit در `psy/v1/submit`/`draft` و endpointهای nopriv پنل.
- وجود/نبود جدول سفارشی در `piwand-daily-feed` (در گرپ دیده نشد).
- هم‌خوانی `pas_send_sms` با پارامترهای `NAME/DATE` در الگوی ملی‌پیامک.
- ثبت واقعی جدول‌ها/نقش‌ها پس از ارتقا (فاز ۹): اینکه در پیشخوان هر سه جدول
  جدید دیده شوند، نقش درمانگر «مطابق» باشد، و شمارش جدول‌های غایب درست باشد.
- رندر و درستی تطبیق نام‌ها در داشبورد سلامت (فاز ۱۰) روی نصب واقعی،
  به‌ویژه وقتی چند افزونه غیرفعال/نصب‌نشده باشند.
- هر رفتار WP-Cron (هیچ یادآور خودکاری وجود ندارد، ولی کران‌های دیگر افزونه‌ها
  بررسی نشده‌اند).

---

## ۱۰. جدول جامع وضعیت

| افزونه | نسخه | جدول‌ها | وابستگی به هسته | وابستگی دادهٔ بیرونی (می‌خواند) | ریسک اصلی | آمادگی انتشار |
|---|---|---|---|---|---|---|
| `pivand-amn-core` | 1.7.0 | ۵ جدول (`pas_*`) | — | `pas_cf_cases`, `psy_invitations`, `zdp_leads`, `payvand_messages` | نقض مرز داده در رجیستری هویت؛ کلید رمزنگاری تک‌نقطه | **آماده برای staging** — رجیستری کامل + داشبورد سلامت (فاز ۱۰) |
| `pivand-amn-case-file` | 1.15.3 | ۴ جدول (`pas_cf_*`) | سخت | `zdp_bookings` | CSV/پرینت حاوی داده‌ی شناسایی | **آماده برای staging** — قرارداد دسترسی یکپارچه (فاز ۹) + رفع شناسهٔ یادداشت و کد وضعیت HTTP (فاز ۱۱) |
| `pivand-amn-client-portal` | 1.11.1.0 | ۵ جدول (`zdp_*`) + `cpp_*` میراث | نرم | — | endpointهای nopriv OTP/نوبت؛ nonce ناشناس | **آماده برای staging** — rate-limit باید تأیید شود |
| `pivand-amn-quiz-enterprise` | 3.6.7 | ۵ جدول (`psy_*`) | نرم | — | `/submit` و `/draft` عمومی؛ fallback متن ساده بدون هسته | **آماده برای staging** — تست سوءاستفاده از توکن |
| `pivand-amn-sms-bridge` | 1.1.6 | ۱ جدول (`pas_sms_log`) | نرم | — | اعتبارنامهٔ لو‌رفته تا چرخش دستی | **آماده برای staging** — چرخش اعتبارنامه شرط انتشار |
| `pivand-amn-2fa` | 1.3.4 | بدون جدول | نرم | — | نبود ثابت نسخه/مهاجرت | **آماده برای staging** |
| `pivand-amn-smtp-mailer` | 1.5.2 | ۱ جدول (`zsm_mail_queue`) | مستقل | — | جدول در رجیستری نیست | آماده (خارج دامنهٔ بالینی) |
| `pivand-amn-content-writer` | 2.9.18 | ۳ جدول (`aco_*`) | مستقل | — | خارج دامنه | آماده |
| `piwand-daily-feed` | 1.1.4 | جدول سفارشی دیده نشد | مستقل | — | خارج دامنه | NOT VERIFIED |
| `payvand-contact` | 1.4.2 | ۱ جدول (`payvand_messages`) | مستقل | — | کانال ورودی لید؛ دادهٔ هویتی | آماده |

**جمع‌بندی آمادگی**: هیچ‌کدام از ۱۰ افزونه «تأییدشده در اجرا» نیستند. پنج
افزونهٔ دامنهٔ بالینی از نظر ساختار کد و مرزهای دسترسی «آماده برای staging»
هستند، مشروط به: اجرای `docs/staging-test-plan.md`، تکمیل رجیستری‌ها، و
تصمیم دربارهٔ دو نقض خواندن مستقیم جدول بیرونی.

---

## ۱۱. مراجع

- تحلیل اولیه: `docs/phase1-ecosystem-analysis.md`
- گزارش فازها: `docs/phase0-report.md` … `docs/phase8-report.md`
- نقشهٔ اکوسیستم: `plugins/pivand-amn-core/ecosystem-map.md`
- رجیستری قراردادها: `plugins/pivand-amn-core/includes/class-filter-registry.php`
- چک‌لیست تست: `docs/staging-test-plan.md`
