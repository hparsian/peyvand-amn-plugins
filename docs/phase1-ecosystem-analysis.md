# پیوند امن — گزارش مرحله ۱: تحلیل اکوسیستم و معماری یکپارچه‌سازی

> **وضعیت سند:** فقط تحلیل و طراحی. **هیچ فایل افزونه‌ای تغییر نکرده، حذف نشده و بازنویسی نشده است.**
> نسخه‌های اصلی (zip) دست‌نخورده‌اند. برای بررسی، نسخه‌ی خواندنیِ افزونه‌ها در پوشه‌ی
> `_analysis/plugins/` استخراج شده است (فقط کپیِ خواندنی، خارج از کنترل نسخه).
>
> **دامنه‌ی بررسی:** هدر همه‌ی ۱۰ افزونه، اسکیمای دیتابیس (نصب‌کننده‌ها)، کلاس‌های
> یکپارچه‌سازی و فیلترها، احراز هویت/دسترسی، و ساختار فرم‌ها. **این سند ادعای
> ممیزی امنیتی خط‌به‌خط ۵۶٬۰۰۰ خط کد نیست.** مواردی که بررسی نشده‌اند در
> بخش «آنچه بررسی نشده» صریحاً فهرست شده‌اند.

---

## خلاصه‌ی اجرایی

خبر خوب: اکوسیستم شما **از صفر شروع نمی‌شود**. یک هسته‌ی مشترک واقعی
(`pivand-amn-core` 1.3.7) با رمزنگاری مشترک، توکن طراحی، تاریخ شمسی،
رجیستری وابستگی/جدول/کپابیلیتی و **۷ قرارداد فیلتری فعال** وجود دارد و
چند اتصال واقعی هم کار می‌کند (فرم ارزیابی → پرونده، نتایج آزمون → پرونده،
پیامک → پنل).

خبر مهم: مشکل اصلی شما «کمبود قابلیت» نیست، **«نبود هویت واحد مراجع»** است.
در حال حاضر یک انسان می‌تواند هم‌زمان در ۵ جای مختلف با ۵ کلید متفاوت وجود
داشته باشد:

| محل | کلید هویت فعلی |
|---|---|
| کاربر وردپرس (پنل مراجعان) | `user_login = zdp_<mobile>` + متای `zdp_mobile` + متای `cellphone` |
| فرم ارزیابی (پنل) | `zdp_intake.user_id` (JSON رمزشده) |
| پرونده بالینی | `pas_cf_cases.phone` (+ `spouse_phone`، `partner_case_id`) |
| آزمون‌ها | `psy_invitations.client_phone` / `client_email` |
| نوبت‌ها | `zdp_bookings.user_id` (بدون `case_id`) |
| نظرسنجی | **هیچ کلیدی ندارد** — نه user، نه نوبت، نه پرونده |
| لیدها | `zdp_leads.phone` |
| فرم تماس | `payvand_messages.phone` (بدون اتصال به لید) |

بنابراین معماری پیشنهادی، **«افزونه‌ی یازدهم» نیست**؛ توسعه‌ی همان هسته‌ی
موجود (01) با یک **رجیستری هویت مراجع (Client Registry)**، **دفتر روابط
(زوج/پرونده مشترک)**، **گذرگاه رویداد (Event Bus)** و **لایه‌ی قراردادهای
نسخه‌دار** است. هیچ جدولی حذف یا تغییر نام داده نمی‌شود؛ همه‌چیز **افزایشی
(additive)** و پشت Feature Flag است.

سه ریسک فوری که مستقل از معماری باید بسته شوند:

1. **اعتبارنامه‌ی ملی‌پیامک به‌صورت متن ساده داخل کد افزونه‌ی sms-bridge**
   (هوک فعال‌سازی) قرار دارد. باید فوراً باطل/چرخش (rotate) شود و از
   `wp-config.php` یا محیط خوانده شود. مقدار آن در این سند تکرار نشده است.
2. **اتصال خودکار «زوج» بر اساس یکسان بودن شماره تلفن** در
   `Pas_CF_Case_File::sync_partner_link()` — دقیقاً همان چیزی که شما ممنوع
   کرده‌اید و یک نشتی حریم خصوصی جدی است (دو نفر با شماره مشترک/اشتباه،
   پرونده‌ی هم را می‌بینند).
3. **`partner_case_id` دوطرفه بدون رضایت و بدون تفکیک سطح دید** — پرونده‌ی
   همسر با تمام فیلدهای حساس در یک رکورد نگه داشته می‌شود؛ این با اصل
   «اطلاعات خصوصی هر عضو زوج نباید به‌خاطر اتصال در اختیار دیگری باشد»
   در تضاد است.

---

## بخش اول: وضعیت فعلی هر ۱۰ افزونه

### ۱.۱ جدول کلی

| # | افزونه | نسخه | نقش | جدول‌ها | کپابیلیتی | وضعیت یکپارچگی |
|---|---|---|---|---|---|---|
| 01 | `pivand-amn-core` | 1.3.7 | زیرساخت مشترک | ندارد (رجیستری متنی) | — | ✅ باید همیشه فعال |
| 02 | `pivand-amn-2fa` | 1.3.3 (ثابت کد: 1.3.1 ⚠️) | TOTP ادمین | ندارد (user_meta) | — | مستقل |
| 03 | `pivand-amn-smtp-mailer` | 1.5.2 | SMTP/Brevo برای `wp_mail` | ندارد (options) | — | مستقل |
| 04 | `pivand-amn-sms-bridge` | 1.1.5 | تنها درگاه پیامک | `pas_sms_log` | — | ✅ provider `pas_send_sms` |
| 05 | `pivand-amn-case-file` | 1.9.0 | پرونده بالینی | `pas_cf_cases`, `pas_cf_notes`, `pas_cf_note_history`, `pas_cf_audit_log` | `pas_manage_case_files`, `pas_view_all_case_files`؛ نقش `pas_cf_therapist` | ⚠️ نیمه‌یکپارچه |
| 06 | `pivand-amn-quiz-enterprise` | 3.6.6 (DB 3.10) | ۲۸ آزمون روان‌سنجی | `psy_invitations`, `psy_attempts`, `psy_couples`, `psy_drafts`, `psy_logs` | فقط `manage_options` | ⚠️ نیمه‌یکپارچه |
| 07 | `pivand-amn-client-portal` | 1.11.1.0 (DB 1.0.6) | پنل مراجع/نوبت/نظرسنجی | `zdp_bookings`, `zdp_messages`, `zdp_surveys`, `zdp_intake`, `zdp_leads` + میراث `cpp_*` | `zdp_manage_clients` | ✅ مرکز تعامل |
| 08 | `payvand-contact` | 1.4.2 | فرم تماس | `payvand_messages` | — | ❌ جزیره |
| 09 | `pivand-amn-content-writer` | 2.9.17 | تولید محتوا/سئو (AI) | `aco_queue`, `aco_logs`, `aco_keyword_bank` ⚠️ ثبت‌نشده در رجیستری | — | ❌ جزیره (مصرف‌کننده‌ی daily-feed) |
| 10 | `piwand-daily-feed` | 1.1.4 | فید محتوای روزانه | ندارد (CPT `pwdf_card`) | — | ❌ جزیره |

### ۱.۲ جزئیات هر افزونه

#### 01 — هسته مشترک (`PAS_CORE_VERSION 1.3.7`)
- **کلاس‌ها:** `Pas_Core_Helpers` (نرمال‌سازی موبایل، IP واقعی)، `Pas_Core_Crypto`
  (libsodium secretbox؛ کلید از `PAS_CORE_ENCRYPTION_KEY` یا
  `wp-content/pas-encryption-key.php`؛ پیشوند `enc1:` برای مهاجرت تدریجی)،
  `Pas_Core_Fonts` (Vazirmatn سلف‌هاست)، `Pas_Core_Tokens` (`tokens.css`)،
  `Pas_Core_Jalali`, `Pas_Core_Dependency_Checker`, `Pas_Core_Filter_Registry`,
  `Pas_Core_Table_Registry`, `Pas_Core_Capability_Registry`, `Pas_Core_Prefix_Map`,
  `Pas_Core_Admin`.
- **نقاط قوت:** رمزنگاری واحد برای کل اکوسیستم؛ رجیستری قراردادها؛ هشدار
  وابستگی غیرفعال؛ تولید خودکار کلید؛ توکن طراحی مشترک.
- **نقاط ضعف:**
  - `tokens.css` پالت **آبی** (`#2563eb`) دارد، در حالی که هویت بصری مورد
    درخواست شما قهوه‌ای/کرم (`#51483F`, `#F4F0E8`, `#B8ADA0`, `#C8B48A`) است
    → تناقض مستقیم با بخش ۱۲ درخواست.
  - رجیستری جدول‌ها **ناقص/کهنه** است: `zdp_intake`، `pas_cf_note_history` و
    `aco_keyword_bank` در آن نیستند.
  - «هسته» فقط مستندسازی می‌کند؛ هیچ سرویس runtime (هویت، رویداد، اعلان) ندارد.
  - هیچ کلاس/جدول `client` یا `case` مرکزی وجود ندارد.

#### 02 — احراز دومرحله‌ای (`1.3.3`)
- TOTP + کدهای بازیابی؛ اختیاری per-user (`pas_2fa_secret` در user_meta)؛
  دروازه‌ی ورود + صفحه‌ی پروفایل.
- **ضعف:** ناهماهنگی نسخه (هدر 1.3.3، ثابت 1.3.1)؛ **اجبار سراسری وجود ندارد**؛
  هر ادمینی که فعال نکند، با یک رمز به کل داده‌های بالینی می‌رسد. برای نقش
  «درمانگر» هیچ الزامی تعریف نشده.

#### 03 — SMTP (`1.5.2`)
- مسیر SMTP + Brevo API + صف retry با cron + failover به `mail()`.
- کلیدها در `wp_options` (خارج از کد — خوب). هیچ جدول اختصاصی ندارد.
- **نقش در اکوسیستم:** تنها مسیر ایمیل قابل اتکا؛ اما هیچ مصرف‌کننده‌ی
  رویدادمحور ندارد (پنل و آزمون مستقیم `wp_mail` می‌زنند).

#### 04 — پل پیامک (`1.1.5`)
- تنها ارائه‌دهنده‌ی `pas_send_sms`; جدول `pas_sms_log`; صفحه‌ی ادمین و لاگ.
- **ریسک بحرانی:** اعتبارنامه‌ی پیش‌فرض ملی‌پیامک به‌صورت متن ساده در
  هوک فعال‌سازی هاردکد شده است → چرخش فوری + خواندن از ثابت/env.
- هیچ صف/تلاش مجدد/وضعیت تحویل (delivery report) ندارد؛ خروجی فقط boolean.

#### 05 — پرونده مراجعین (`1.9.0`)
- **جدول `pas_cf_cases`:** هویت پایه (`phone`, `full_name`, `age`,
  `marital_status`, `education`, `occupation`, `residence`, `marriage_type`,
  `children_count`)، فیلدهای زوج (`spouse_*` ~۱۲ ستون)، `partner_case_id`،
  موضوع (`topic`, `main_conflict_topics`)، سوابق رمزشده
  (`medical_history_encrypted`, `counseling_history_encrypted`,
  `self_harm_history_encrypted`, `substance_use_history_encrypted` + نسخه‌ی
  `spouse_`)، علائم (`symptom_mood_depression`, `symptom_anxiety`)، پرسش‌های
  مصاحبه (`q_*`)، رضایت رابطه pre/post، `assigned_therapist`, `status`,
  `created_by`, `created_at`, `updated_at`.
- **`pas_cf_notes`:** یادداشت جلسه (رمزنگاری‌شده) + `session_date` +
  `session_number` → **این عملاً «جلسه» است، نه یک موجودیت مستقل**.
- **`pas_cf_note_history`:** نسخه‌های قبلی + حذف‌شده (بازگردانی‌پذیر) —
  الگوی بسیار خوبی که باید در کل اکوسیستم تکرار شود.
- **`pas_cf_audit_log`:** هر مشاهده/ویرایش ثبت می‌شود (case_id, user, ip).
- **قراردادها:** `pas_get_case_summary_for_phone` (خلاصه‌ی غیرحساس)،
  `pas_lookup_known_client_name` (اولویت ۱۰).
- **اتصال به فرم ارزیابی:** کارت `render_intake_card()` با فیلتر
  `zdp_get_intake_for_phone` — فقط نمایشی، **بدون نگاشت فیلد و بدون ثبت
  منبع/تاریخ هر فیلد**.
- **ضعف‌ها:** `sync_partner_link()` با شماره تلفن، دو پرونده را خودکار زوج
  می‌کند (بدون رضایت، بدون تأیید هویت)؛ اطلاعات همسر داخل همان رکورد است؛
  هیچ `gender`/`birth_date`/`referral_source` ندارد؛ جلسه موجودیت مستقل
  ندارد و «فردی/زوجی» بودن جلسه تعریف نشده؛ دسترسی درمانگر خوب است ولی
  فقط در همین افزونه.

#### 06 — آزمون‌های روان‌سنجی (`3.6.6`)
- **۲۸ آزمون:** ACE, AUDIT, BAI, BDI, BIS-11, CSI, CTS2, DAS, DASS21/PSS10,
  ECR, FMS, GHQ28, GSE, IBT, LAS, LoveStory, MDQ/EPDS/DERS, NEO,
  OCI-R/PCL-5/BPAQ, PAM, PHQ9/GAD7, PSQI, RAS, RSES, SCL-90-R, STAI, SWLS, YSQ.
- **دیتابیس:** `psy_invitations` (توکن، نام/موبایل/ایمیل، وضعیت،
  expires_at, started_at), `psy_attempts` (answers + result_data, **رمزنگاری
  با `Pas_Core_Crypto` در صورت تنظیم کلید**), `psy_couples` (اتصال دو
  attempt)، `psy_drafts`، `psy_logs`.
- **REST:** `psy/v1/quiz` (فقط `manage_options`)، `submit`/`draft` (عمومی +
  توکن ۴۰ کاراکتری + rate limit)، `report/{id}` (ادمین یا توکن).
- **پنل ادمین:** مراجعین/دعوت، گزارش‌ها، مقایسه‌ی زوجین، پیشرفت درمانی،
  تنظیمات، ورود آفلاین/دستی، دیباگ.
- **قراردادها:** `psy_get_pending_quizzes_for_phone`, `_email`,
  `psy_get_client_name_for_phone`, `psy_get_completed_results_for_phone`,
  `pas_lookup_known_client_name` (اولویت ۱۵).
- **ضعف‌ها:** نقش «درمانگر» در این افزونه وجود ندارد (همه‌چیز `manage_options`)
  → درمانگر بدون ادمین شدن نمی‌تواند گزارش ببیند؛ «زوج» در اینجا یعنی دو
  attempt، نه دو انسان/دو حساب؛ دعوت‌نامه نقش «رجیستری مراجع» موازی را بازی
  می‌کند؛ لینک گزارش به `admin.php` است (کپی داده نیست — خوب).

#### 07 — پنل مراجعان (`1.11.1.0`)
- namespace `ZoojDarman\ClientPortal`; composition root در `Plugin`.
- **دیتابیس:** `zdp_bookings` (user_id, service, date/time, status,
  authority/ref_id زرین‌پال, `booking_source`, `session_type`
  = حضوری/آنلاین/تلفنی, `therapist_note`, `reminder_sent_at`, رسید),
  `zdp_messages`, `zdp_surveys` (**بدون user_id/booking_id/case_id**),
  `zdp_intake` (JSON رمزشده، `file_number` فقط ادمین، `unique user_id`),
  `zdp_leads`. میراث `cpp_*` با parity report و DROP دستی — **الگوی مهاجرت
  بسیار خوب**.
- **احراز:** OTP پیامکی و OTP ایمیلی؛ ساخت کاربر `zdp_<mobile>` با نقش
  `subscriber`؛ متای `zdp_mobile` + `cellphone` + `zdp_full_name`.
  مسیر ایمیل می‌تواند **حساب مستقل دوم** برای همان انسان بسازد (ریسک تکرار).
- **قابلیت‌ها:** نوبت‌دهی + زرین‌پال + آپلود رسید، پیام‌ها، نظرسنجی عمومی
  (`[zdp_survey_page]` + `[zdp_testimonials]`), لید مگنت و پیگیری ۲۴/۷۲ ساعته،
  فانل کوییز عمومی، فرم ارزیابی، یادآوری نوبت با cron، مسدودسازی wp-admin
  برای مراجع، قالب خالی پنل، لاگ خطای JS.
- **تب‌های پنل مراجع:** نوبت‌ها، آزمون‌ها، پیام‌ها، فرم ارزیابی، نظرسنجی.
- **قراردادها:** ارائه‌دهنده‌ی `zdp_get_intake_for_phone`,
  `pas_lookup_known_client_name` (اولویت ۵), `zdp_get_client_panel_url`;
  مصرف‌کننده‌ی `pas_send_sms`, `psy_get_pending_quizzes_for_phone/_email`,
  `psy_get_client_name_for_phone`, `pas_get_case_summary_for_phone`.
- **ضعف‌ها:** نظرسنجی **قابل انتساب نیست** (هیچ کلید مراجع/جلسه) و خودکار
  نیست؛ نوبت به پرونده وصل نیست؛ `therapist_note` یک یادداشت موازی خارج از
  پرونده‌ی بالینی است؛ `audit_log()` در `wp_options` و سقف ۵۰۰ رکورد دارد
  (نه مقیاس‌پذیر، نه ضد‌دستکاری)؛ فرم ارزیابی JSON بدون ایندکس‌پذیری فیلد.

#### 08 — فرم تماس (`1.4.2`)
- فیلدها: `full_name`, `phone`, `email`, `visit_type`, `consult_type`
  (حضوری/آنلاین/تلفنی), `message`, `privacy`, honeypot `website`.
- `payvand_messages` + ایمیل به ادمین + rate limit + رنگ‌بندی خودکار از تم.
- **ضعف:** هیچ اتصالی به `zdp_leads` یا پنل مراجعان ندارد → هر تماس یک
  سرنخ مرده در جدول جدا است.

#### 09 — تولید محتوا (`2.9.17`)
- ~۱۵ فایل `ACO_*`; AI Router با ۵ ارائه‌دهنده (OpenRouter, DeepSeek, OpenAI,
  Gemini, Anthropic) + fallback؛ تولید تصویر؛ سئو؛ crawler؛ Search Console؛
  Gutenberg؛ تحلیل زنده؛ صف و لاگ. جدول `aco_keyword_bank` **در رجیستری هسته
  ثبت نشده**.
- کاملاً مستقل؛ فقط daily-feed از آن استفاده می‌کند.

#### 10 — فید روزانه (`1.1.4`)
- CPT `pwdf_card`; ویجت/شورت‌کد/AJAX؛ تنظیمات غنی (حالت نمایش، انیمیشن،
  رنگ هر نوع کارت، فونت، ستون‌ها)؛ تولید خودکار روزانه از content-writer
  (همیشه پیش‌نویس).
- مستقل؛ هیچ ارتباطی با داده‌ی درمانی ندارد.

---

## آنچه بررسی نشده (صریح، بدون حدس)

- ممیزی امنیتی خط‌به‌خط همه‌ی handlerهای ادمین (به‌ویژه `class-admin.php` پنل
  و `trait-aco-admin-ajax.php`). فقط الگوها و نقاط کلیدی بررسی شد.
- صحت کامل محاسبات نمره‌گذاری/تفسیر ۲۸ آزمون بررسی نشده است.
- وضعیت واقعی داده‌ی سایت شما (تعداد پرونده، نوبت، آزمون، داده‌ی رمزنگاری‌شده
  یا نشده) در دسترس نیست؛ گزارش بر پایه‌ی کد است، نه دیتابیس زنده.
- اینکه کلید `PAS_CORE_ENCRYPTION_KEY` روی سایت شما تنظیم است یا نه، و اینکه
  کدام رکوردها رمزنگاری‌نشده‌اند (`enc1:` ندارند) باید در فاز ۰ سنجیده شود.
- جزئیات UI ادمین پنل مراجعان (۱۴۸۴ خط) و content-writer به‌طور کامل بازبینی
  نشد؛ فهرست قابلیت‌ها از هدر/ساختار استخراج شده است.
- عملکرد واقعی افزونه‌ها روی هاست شما (نسخه‌ی PHP/MySQL، محدودیت پورت SMTP،
  اجرای cron) بررسی نشده است.

---

## بخش دوم: نقشه‌ی ارتباطات

### ۲.۱ ماتریس تولیدکننده/مصرف‌کننده

| داده | منبع اصلی (Owner) | تولیدکننده | مصرف‌کننده‌های فعلی | مصرف‌کننده‌های موردنیاز |
|---|---|---|---|---|
| هویت مراجع (نام/موبایل/ایمیل) | **باید هسته باشد** (فعلاً پنل) | پنل (کاربر + intake) | پرونده، آزمون (فیلتر نام) | همه |
| فرم ارزیابی | پنل (`zdp_intake`) | پنل | پرونده (فقط نمایش) | پرونده (نگاشت + منبع)، داشبورد |
| پرونده بالینی | case-file | case-file | پنل (خلاصه) | داشبورد، آزمون، نظرسنجی، نوبت |
| یادداشت جلسه | case-file | case-file | — | تایم‌لاین، داشبورد |
| نتیجه آزمون | quiz | quiz | پرونده (لینک) | داشبورد، تایم‌لاین، پنل مراجع |
| نوبت/جلسه | پنل (`zdp_bookings`) | پنل | — | پرونده، تایم‌لاین، نظرسنجی، یادآوری |
| نظرسنجی | پنل | پنل (عمومی) | — | جلسه، پرونده، داشبورد |
| پیام مراجع | پنل | پنل | — | داشبورد، پرونده (اختیاری) |
| سرنخ/لید | پنل | پنل + فرم تماس (باید) | — | داشبورد، پیگیری |
| پیامک | sms-bridge | sms-bridge | پنل، آزمون | اعلان مرکزی |
| ایمیل | smtp-mailer | smtp-mailer | همه | اعلان مرکزی |
| پرداخت | پنل (زرین‌پال) | پنل | — | پرونده (مالی)، داشبورد |
| رضایت‌نامه | **وجود ندارد** | — | — | هسته |
| لاگ دسترسی | case-file (خوب) / پنل (options) | هر دو جدا | — | هسته (Audit) |

### ۲.۲ قراردادهای موجود (تأییدشده در کد)

| قرارداد | ارائه‌دهنده | مصرف‌کننده | وضعیت |
|---|---|---|---|
| `pas_send_sms($default,$mobile,$body_id,$params)` | sms-bridge | پنل، آزمون | ✅ فعال |
| `psy_get_pending_quizzes_for_phone` | quiz | پنل | ✅ |
| `psy_get_pending_quizzes_for_email` | quiz | پنل | ✅ |
| `psy_get_client_name_for_phone` | quiz | پنل | ✅ |
| `psy_get_completed_results_for_phone` | quiz | case-file | ✅ |
| `psy_get_mobile_for_email` | quiz | پنل (OTP ایمیل) | ✅ |
| `pas_get_case_summary_for_phone` | case-file | پنل (لیدها) | ✅ |
| `zdp_get_intake_for_phone` | پنل | case-file | ✅ |
| `zdp_get_client_panel_url` | پنل | آزمون | ✅ |
| `pas_lookup_known_client_name` | زنجیره‌ای ۵←۱۰←۱۵ | فرم‌های «مراجع جدید» | ✅ |

**تحلیل:** این قراردادها «فیلتر مقدار» هستند، نه «قرارداد داده». نه نسخه
دارند، نه نوع، نه مجوز، نه لاگ. `pas_lookup_known_client_name` نام مراجع را
بدون هیچ بررسی سطح دسترسی برمی‌گرداند (مصرف آن فعلاً فقط فرم‌های ادمین است،
ولی این یک نقص طراحی است). `pas_get_case_summary_for_phone` هم بررسی
دسترسی ندارد و به مصرف‌کننده اعتماد می‌کند.

### ۲.۳ شکاف‌های ارتباطی (چه چیزی باید اضافه شود)

| # | شکاف | ارتباط لازم |
|---|---|---|
| G1 | مراجع در پرونده و آزمون و پنل سه هویت جدا دارد | `pas_get_client_id($identifier)` + `pas_link_identity()` |
| G2 | فرم ارزیابی به پرونده «منتقل» نمی‌شود | `pas_map_intake_to_case()` + جدول نگاشت + provenance |
| G3 | نوبت به پرونده وصل نیست | `pas_link_booking_to_case($booking_id,$case_id)` |
| G4 | آزمون به پرونده/جلسه وصل نیست | `pas_link_attempt_to_case()` (اختیاری، بدون کپی داده) |
| G5 | نظرسنجی قابل انتساب نیست | افزودن `client_id`,`session_id`,`survey_type` |
| G6 | فرم تماس به لید تبدیل نمی‌شود | `pas_create_lead_from_contact()` |
| G7 | زوج در پرونده و آزمون دو مفهوم جدا | `pas_couple_id` واحد + رضایت + دعوت |
| G8 | هیج رویداد/اعلان مرکزی نیست | `pas_emit('session.completed', ...)` → Notification |
| G9 | لاگ‌ها پراکنده و غیرقابل‌رهگیری | `pas_events` مرکزی + `trace_id` |
| G10 | هیچ رضایت‌نامه‌ای ثبت نمی‌شود | `pas_consents` (نوع، نسخه، زمان، IP) |
| G11 | داشبورد یکپارچه وجود ندارد | Read-model از قراردادها، نه کوئری مستقیم |
| G12 | فرم تماس/فید/محتوا هیچ ارتباطی ندارند | فقط لید (تماس)؛ بقیه عمداً مستقل بمانند |

---

## بخش سوم: معماری پیشنهادی

### ۳.۱ اصل حاکم

> **یک منبع حقیقت برای هر داده، یک گذرگاه برای هر ارتباط، صفر کوئری مستقیم
> بین‌افزونه‌ای.**

- **مالکیت داده** دست نمی‌خورد: پرونده → case-file، آزمون → quiz، نوبت/پیام/
  نظرسنجی → portal، پیامک → sms-bridge، ایمیل → smtp-mailer.
- **هسته (01)** مالک سه چیز جدید می‌شود: **هویت مراجع**، **روابط (زوج/پرونده
  مشترک)**، **رویداد/رضایت/نگاشت فیلد**. این‌ها داده‌ی «فرابرنامه‌ای» هستند و
  جای دیگری نمی‌توانند زندگی کنند بدون ایجاد دور.
- ارتباط فقط از سه راه: **قرارداد فیلتر نسخه‌دار** (خواندن)، **اکشن رویداد**
  (نوشتن/اعلان)، **REST داخلی با nonce/capability** (برای UIهای آینده).

### ۳.۲ لایه‌ها

```
┌──────────────────────────────────────────────────────────────┐
│ Presentation: Therapist Dashboard │ Client Panel │ Quiz UI   │
│              (Design System مشترک: pivand-*)                  │
├──────────────────────────────────────────────────────────────┤
│ Integration Layer (core 1.4):                                 │
│  Client Registry │ Relationship/Couple │ Field Mapping        │
│  Event Bus │ Notification Router │ Consent │ Audit │ Contracts│
├──────────────────────────────────────────────────────────────┤
│ Data Owners (بدون تغییر مالکیت):                              │
│  case-file │ quiz │ portal │ sms-bridge │ smtp │ contact      │
├──────────────────────────────────────────────────────────────┤
│ Storage: MySQL (wp_*) + libsodium (Pas_Core_Crypto)           │
└──────────────────────────────────────────────────────────────┘
```

### ۳.۳ چرا «افزونه‌ی یازدهم» نه؟

- یک افزونه‌ی جدید یعنی یک بوت‌استرپ دیگر، یک مسیر آپدیت دیگر، و ریسک
  «کدام اول لود می‌شود؟».
- هسته از قبل بارگذاری تضمینی (اولویت ۵) و رمزنگاری/توکن/رجیستری دارد.
- توسعه‌ی هسته **Backward Compatible** است: هیچ افزونه‌ای مجبور نیست همان روز
  تغییر کند؛ همه‌ی APIهای جدید «اختیاری» و «فقط در صورت وجود» مصرف می‌شوند
  (همان الگوی `class_exists('Pas_Core_Helpers')` که امروز کار می‌کند).

### ۳.۴ قراردادهای پیشنهادی (نسخه‌دار، فقط‌خواندنی)

```php
// هویت
apply_filters('pas_get_client', null, ['client_id'=>...] | ['phone'=>...] | ['email'=>...] | ['user_id'=>...]);
// خروجی: ['client_id'=>'pc_...', 'display_name'=>..., 'identifiers'=>[...], 'cases'=>[...]]

// پرونده
apply_filters('pas_get_case', null, $case_id);           // فقط فیلدهای مجاز مصرف‌کننده
apply_filters('pas_get_case_for_client', [], $client_id);

// جلسات
apply_filters('pas_get_sessions_for_case', [], $case_id, ['type'=>'individual|couple']);

// آزمون‌ها
apply_filters('pas_get_assessments_for_client', [], $client_id);

// نوبت‌ها
apply_filters('pas_get_appointments_for_client', [], $client_id, $from, $to);

// نگاشت
apply_filters('pas_map_fields', [], $source_schema, $target_schema, $payload);
```

قواعد الزامی هر قرارداد:
1. **نسخه** (`v1`) در نام یا در payload.
2. **ورودی مجوز:** `$context = ['actor_id'=>..., 'actor_role'=>..., 'purpose'=>...]`
   و provider خودش بررسی می‌کند — نه مصرف‌کننده.
3. **حداقل داده:** هر provider فقط فیلدهای لازم برای آن purpose را برمی‌گرداند
   (مثلاً `dashboard` نام و وضعیت، ولی نه یادداشت).
4. **بدون کشوی رمزگشایی خودکار:** فیلدهای بالینی فقط با `purpose=clinical`.
5. **ثبت در `Pas_Core_Filter_Registry`** با `status` و `since`.

### ۳.۵ گذرگاه رویداد

```php
do_action('pas_event', 'session.completed', [
  'event_id' => wp_generate_uuid4(),
  'client_id'=> 'pc_...', 'case_id'=> 12, 'session_id'=> 340,
  'actor_id' => 5, 'occurred_at' => gmdate('c'), 'trace_id' => '...'
]);
```
رویدادهای MVP: `client.created`, `intake.submitted`, `case.created`,
`case.assigned`, `assessment.completed`, `session.scheduled`,
`session.completed`, `booking.confirmed`, `survey.submitted`,
`consent.granted/revoked`, `couple.linked/unlinked`.

مصرف‌کننده‌ها: Notification Router، Audit، Timeline، Automation. **هیچ
مصرف‌کننده‌ای نباید رویداد را برای نوشتن در جدول افزونه‌ی دیگر استفاده کند** —
فقط برای اعلان/لاگ/همگام‌سازی هویت.

### ۳.۶ Notification Manager

لایه‌ی نازک در هسته، نه بازنویسی:
- `Pas_Core_Notify::send($template_key, $client_id, $channel, $params)`
- کانال‌ها: `sms` (از طریق `pas_send_sms`)، `email` (از طریق `wp_mail` که
  smtp-mailer می‌گیرد)، `inapp` (پیام در پنل).
- قالب‌ها در options با کلید (`intake_reminder`, `session_reminder`,
  `survey_request`, `welcome`, `followup`).
- صف + لاگ + تلاش مجدد + «توقف» per-template و per-client (opt-out).
- **هیچ ارسال مستقیمی از آزمون/پنل جدید اضافه نشود؛ همه از اینجا.**

### ۳.۷ Field Mapping Engine

- جدول `pas_field_maps`: `source_schema` (`zdp_intake/v1`), `source_path`
  (`med_details`), `target_schema` (`pas_cf_cases/v1`), `target_path`
  (`medical_history`), `transform` (`copy|date_jalali|enum_map|concat`),
  `sensitivity` (`public|clinical|secret`), `is_active`.
- اجرای نگاشت **همیشه با پیش‌نمایش** (`dry_run`) و ثبت `provenance`.
- نسخه‌بندی: نگاشت برای `zdp_intake/v1` و `v2` جدا؛ فرم‌های قدیمی هرگز
  دوباره‌نگاشت نمی‌شوند مگر دستی.

### ۳.۸ Permission Manager

نقش‌های پیشنهادی (روی وردپرس، بدون افزونه‌ی جدید):

| نقش | دسترسی |
|---|---|
| `administrator` | همه |
| `pas_clinic_manager` | همه‌ی پرونده‌ها + گزارش‌های مدیریتی + تخصیص |
| `pas_cf_therapist` (موجود) | پرونده‌های خودش + آزمون‌های مراجعان خودش |
| `pas_receptionist` | نوبت/تماس/لید، **بدون** داده‌ی بالینی |
| `subscriber` (مراجع) | فقط پنل خودش |

کپابیلیتی‌های جدید: `pas_view_clinical_notes`, `pas_manage_couples`,
`pas_manage_consents`, `pas_view_audit_log`, `pas_export_data`,
`pas_send_surveys`, `pas_manage_field_maps`.
**هیچ فیلد بالینی نباید فقط با `manage_options` محافظت شود** (وضعیت فعلی quiz).

---

## بخش چهارم: مدل اطلاعات

### ۴.۱ شناسه‌ها

| شناسه | قالب | مالک | توضیح |
|---|---|---|---|
| `client_id` | `pc_` + ULID (۲۶ کاراکتر) | core | **هویت انسانی**، مستقل از موبایل/ایمیل |
| `case_id` | عدد (موجود) | case-file | پرونده‌ی بالینی، N پرونده برای یک client مجاز است |
| `therapist_id` | `wp user_id` | WordPress | نقش/کپابیلیتی تعیین‌کننده |
| `session_id` | `ps_` + ULID | core (index) / case-file (محتوا) | جلسه‌ی فردی یا زوجی |
| `couple_id` | `cp_` + ULID | core | رابطه‌ی دو client با رضایت |
| `booking_id` | عدد (موجود) | portal | نوبت؛ می‌تواند به session_id وصل شود |
| `assessment_attempt_id` | عدد (موجود) | quiz | نتیجه؛ به client/case وصل می‌شود |
| `survey_id` | عدد (موجود) | portal | نظرسنجی؛ باید client/session بگیرد |
| `idempotency_key` | UUID | همه | جلوگیری از ثبت دوباره |

**شماره تلفن هرگز شناسه نیست** — یک «شناسه‌ی قابل اثبات» (identifier) است با
وضعیت `verified|unverified|retired`.

### ۴.۲ جداول جدید (همه افزایشی، با پیشوند `pas_`)

```sql
-- هویت
pas_clients (
  client_id char(29) PK, display_name, gender, birth_date date NULL,
  created_at, updated_at, merged_into char(29) NULL, status
)
pas_client_identifiers (
  id PK, client_id, type ENUM('mobile','email','national_id','wp_user'),
  value_normalized, is_primary, verified_at, verified_by,
  retired_at, created_at, UNIQUE(type, value_normalized, retired_at)
)
-- رابطه
pas_couples (
  couple_id char(29) PK, client_a, client_b, status
  ENUM('invited','active','paused','dissolved'),
  created_by, created_at, closed_at, notes
)
pas_couple_consents (
  id PK, couple_id, client_id, scope ENUM('link','share_basic','share_clinical'),
  granted_at, revoked_at, ip, document_version
)
-- اتصال موجودیت‌ها (بدون کپی داده)
pas_links (
  id PK, client_id, entity_type ENUM('case','booking','assessment','survey','message','lead'),
  entity_id, relation ENUM('owner','subject','participant'), created_at,
  UNIQUE(entity_type, entity_id)
)
-- نگاشت فیلد
pas_field_maps (...)
pas_field_provenance (
  id PK, entity_type, entity_id, field_name, source ENUM('client','therapist','intake','import','system'),
  source_ref, confidence, set_at, set_by, previous_value_hash
)
-- رویداد/رضایت/اعلان/لاگ
pas_events (event_id PK, name, client_id NULL, case_id NULL, actor_id, payload_json, trace_id, created_at)
pas_consents (id PK, client_id, type, document_version, granted_at, revoked_at, ip, meta_json)
pas_notifications (id PK, client_id, template_key, channel, status, attempts, scheduled_at, sent_at, error, payload_json)
pas_audit (id PK, actor_id, action, entity_type, entity_id, client_id, ip, ua, meta_json, created_at)
```

**نکته‌ی مهم:** `pas_field_provenance` جایگزین «حذف/بازنویسی بی‌سابقه» است.
هیچ فیلدی overwrite نمی‌شود بدون اینکه مقدار قبلی هش/تاریخ/منبعش ثبت شود.

### ۴.۳ نگاشت فرم ارزیابی → پرونده (پیشنهاد v1)

| intake (پنل) | case-file | تبدیل | حساسیت |
|---|---|---|---|
| `full_name` | `full_name` | copy | public |
| `phone` | `phone` | normalize | public |
| `age` (محاسبه‌شده) | `age` | copy | public |
| `marital` | `marital_status` | enum map | public |
| `education` | `education` | copy | public |
| `job` | `occupation` | copy | public |
| `address` | `residence` | copy | public |
| `children` | `children_count` | copy | public |
| `med_details` | `medical_history_encrypted` | encrypt | clinical |
| `counseling_details` | `counseling_history_encrypted` | encrypt | clinical |
| `substance_details` | `substance_use_history_encrypted` | encrypt | clinical |
| `referral` | **ستون جدید** `referral_source` | copy | public |
| `gender` | **ستون جدید** `gender` | copy | public |
| `birth_date` | **ستون جدید** `birth_date` | jalali→greg | public |
| `father_name` | **ستون جدید** `father_name` | copy | public |
| `form_date`/`confirmed_at` | `intake_submitted_at` (جدید) | copy | audit |
| — | `topic`, `main_conflict_topics`, `q_*` | **دست درمانگر** | clinical |

اضافه‌شدن ۵ ستون به `pas_cf_cases` با `dbDelta` (idempotent، بدون از دست رفتن
داده) — نه جدول جدید، نه بازسازی.

### ۴.۴ مدل زوج (سه حالت درخواستی)

```
مجرد:            client A ── case A (زوجی ندارد)
متأهل/انفرادی:   client A ── case A ── couple? (invited، بدون پرونده‌ی B)
                 اطلاعات همسر: در case A به‌عنوان "spouse_*" (بدون حساب)
هم‌زمان:         client A ── case A ─┐
                 client B ── case B ─┴─ couple (active) ── couple_session
```

قواعد:
1. ساخت couple **فقط** با `invite → accept` (توکن یک‌بارمصرف + OTP/ایمیل) یا
   تأیید صریح هر دو طرف توسط درمانگر با ثبت رضایت.
2. هیچ‌گاه بر اساس شماره/نام خانوادگی.
3. `share_basic` (نام/سن/وضعیت) و `share_clinical` (یادداشت، آزمون) جدا.
4. جدول نمای مشترک فقط فیلدهای `share_basic` را نشان می‌دهد؛ بقیه با قفل.
5. جلسه‌ی مشترک (`session_type='couple'`) در نمای هر دو، جلسه‌ی فردی فقط در
   نمای صاحبش.
6. `dissolved` → دسترسی متقابل فوراً قطع، داده حفظ می‌شود.

---

## بخش پنجم: سناریوهای عملیاتی (۱۲ سناریو)

1. **ثبت‌نام و هویت:** مراجع با موبایل OTP وارد می‌شود → `client_id` ساخته
   می‌شود → `wp_user` و `cellphone` به‌عنوان identifier ثبت می‌شوند.
2. **مراجع با ایمیل:** همان انسان بعداً با ایمیل وارد می‌شود → سیستم
   «identifier مشکوک» نشان می‌دهد و **ادغام پیشنهاد** می‌دهد، نه خودکار.
3. **تکمیل فرم ارزیابی:** `intake.submitted` → verdict «ناقص/کامل» → پرونده
   خودکار «در انتظار پذیرش» با نگاشت فیلدها و provenance.
4. **پذیرش توسط درمانگر:** درمانگر پرونده را می‌بیند؛ فیلدهای تکمیل‌شده سبز،
   خالی زرد؛ فقط خالی‌ها را پر می‌کند؛ تأیید نهایی → snapshot + audit.
5. **جست‌وجوی مراجع:** با نام/موبایل/client_id/شماره پرونده/نام همسر →
   یک نتیجه با «همه‌ی پرونده‌ها، آزمون‌ها، نوبت‌ها» در یک صفحه.
6. **تعریف آزمون:** درمانگر از پنل آزمون برای client تعریف می‌کند → پیامک/
   ایمیل از Notification Manager → مراجع در تب آزمون‌ها می‌بیند.
7. **تکمیل آزمون:** `assessment.completed` → نتیجه در پرونده (لینک گزارش،
   بدون کپی) + تایم‌لاین + بج «بررسی‌نشده».
8. **بررسی نتیجه:** درمانگر تفسیر خودش را در یادداشت جلسه می‌نویسد (تفکیک
   «تفسیر سیستم» از «تفسیر درمانگر»).
9. **جلسه‌ی فردی:** ثبت جلسه با شماره/تاریخ → یادداشت رمزشده → رویداد
   `session.completed`.
10. **جلسه‌ی زوجی:** درمانگر جلسه را «مشترک» علامت می‌زند → در تایم‌لاین هر
    دو ثبت می‌شود، محتوای یادداشت فقط برای درمانگر.
11. **نظرسنجی پس از جلسه:** قاعده‌ی «۲۴ ساعت بعد از جلسه‌ی مشترک» → ارسال
    لینک اختصاصی → پاسخ به `session_id`/`client_id` وصل می‌شود → کارت در
    داشبورد.
12. **قطع رابطه/لغو رضایت:** مراجع رضایت `share_clinical` را لغو می‌کند →
    دسترسی طرف مقابل فوراً قطع، audit ثبت، داده حفظ.

---

## بخش ششم: طراحی تجربه کاربری و Design System

### ۶.۱ Design System (بازتعریف توکن‌ها)

پالت درخواستی شما جایگزین پالت آبی فعلی `tokens.css` شود (فاز UI):

```
--pivand-primary:        #51483F;   /* قهوه‌ای گرم — اصلی */
--pivand-primary-dark:   #3E362F;
--pivand-primary-soft:   #6B6156;
--pivand-bg:             #F4F0E8;   /* پس‌زمینه */
--pivand-surface:        #FBF8F2;   /* کارت */
--pivand-neutral:        #B8ADA0;   /* خنثی/خط */
--pivand-accent:         #C8B48A;   /* مکمل — فقط لهجه، نه سطح بزرگ */
--pivand-ink:            #2E2A25;
--pivand-ink-mid:        #5A534B;
--pivand-success:        #4F7A5B;
--pivand-warning:        #B07D3A;
--pivand-danger:         #A34A45;
```
قواعد: حداکثر یک سطح `accent` در هر صفحه؛ سایه‌ها بسیار ملایم؛ شعاع ۱۴–۲۰px؛
فونت Vazirmatn (از `Pas_Core_Fonts`)؛ `direction: rtl`؛ `color-scheme: light`.

**کامپوننت‌های مشترک:** Card، StatCard، Badge (وضعیت)، Tabs، Stepper
(فرم چندمرحله‌ای)، Field (با حالت filled/empty/conflict)، Timeline،
EmptyState، Modal تأیید، Toast، ProgressBar، Table با جست‌وجو.
هر کامپوننت یک کلاس `pivand-*` و بدون وابستگی به فریم‌ورک.

### ۶.۲ داشبورد درمانگر (یک صفحه، بدون جابه‌جایی)

```
[ جست‌وجوی هوشمند: نام | موبایل | client_id | شماره پرونده | نام همسر ]
────────────────────────────────────────────────────────────────
KPI: مراجعان جدید | پرونده‌های فعال | جلسات امروز | آزمون بررسی‌نشده
────────────────────────────────────────────────────────────────
ستون ۱: امروز         │ ستون ۲: نیازمند اقدام      │ ستون ۳: فعالیت
- جلسات امروز          │ - فرم‌های ناقص              │ - تایم‌لاین رویدادها
- جلسات آینده          │ - پرونده‌های پذیرش‌نشده     │
- یادآوری‌ها           │ - آزمون‌های بررسی‌نشده      │
                       │ - نظرسنجی‌های جدید          │
                       │ - پیام‌های بی‌پاسخ           │
────────────────────────────────────────────────────────────────
جدول مراجعان: نام | client_id | وضعیت | درمانگر | آخرین جلسه | هشدار راکد
```
- فیلترها: فعال/زوجی/راکد/بدون جلسه/فرم ناقص.
- کلیک روی مراجع → پرونده با تب‌های: هویت، زوج، فرم ارزیابی، آزمون‌ها،
  جلسات، یادداشت‌ها، برنامه درمان، تکالیف، فایل‌ها، مالی، نظرسنجی، تایم‌لاین،
  تاریخچه تغییرات.

### ۶.۳ پنل مراجع (ساده، آرام)

تب‌های موجود حفظ شود (نوبت‌ها، آزمون‌ها، پیام‌ها، فرم ارزیابی، نظرسنجی) +
«اطلاعات من» و «جلسات آینده». هیچ اصطلاح بالینی، هیچ عددی از پرونده، هیچ
یادداشتی. نشانگر پیشرفت پروفایل (٪ کامل بودن). امکان ویرایش اطلاعات با
«درخواست اصلاح» برای فیلدهای حساس (نه ویرایش مستقیم).

### ۶.۴ بازطراحی آزمون (بدون حذف قابلیت)

نوار پیشرفت چسبان + شماره سؤال + کارت سؤال + «قبلی/بعدی» + ذخیره‌ی خودکار
(موجود: `psy_drafts`) + صفحه‌ی مرور پاسخ‌ها + انیمیشن ملایم انتقال + حالت
موبایل تک‌ستونه. همه‌ی ۲۸ آزمون و منطق نمره‌گذاری دست‌نخورده.

---

## بخش هفتم: امنیت و محرمانگی

### ۷.۱ یافته‌ها (بر پایه‌ی کد، با شدت)

| # | یافته | شدت | محل | اقدام |
|---|---|---|---|---|
| S1 | اعتبارنامه‌ی ملی‌پیامک هاردکد در کد | **بحرانی** | sms-bridge، هوک فعال‌سازی | چرخش فوری + خواندن از `wp-config`/env |
| S2 | اتصال خودکار زوج با شماره تلفن | **بحرانی (حریم خصوصی)** | `Pas_CF_Case_File::sync_partner_link` | حذف اتصال خودکار؛ فقط دعوت/رضایت |
| S3 | فیلدهای همسر داخل همان رکورد پرونده | **بالا** | `pas_cf_cases.spouse_*` | تفکیک به پرونده‌ی مستقل یا جدول `spouse_profile` با کنترل دسترسی |
| S4 | گزارش آزمون فقط با `manage_options` | بالا | quiz REST/Admin | افزودن capability درمانگر + بررسی مالکیت مراجع |
| S5 | `audit_log` در `wp_options` با سقف ۵۰۰ | بالا | portal | مهاجرت به `pas_audit` |
| S6 | لاگ‌های امنیتی پراکنده و غیرقابل‌همبستگی | متوسط | همه | `trace_id` + لاگ مرکزی |
| S7 | `pas_lookup_known_client_name` بدون بررسی مجوز نام برمی‌گرداند | متوسط | case-file/quiz/portal | افزودن context و بررسی نقش |
| S8 | 2FA اختیاری، بدون سیاست اجباری برای نقش‌های حساس | متوسط | 2fa | اجبار برای `administrator`/`therapist` |
| S9 | نظرسنجی عمومی بدون هیچ اتصال هویتی → پاسخ قابل انتساب نیست | متوسط | portal | لینک یک‌بارمصرف + `client_id` |
| S10 | مسیر ایمیل می‌تواند حساب دوم برای همان انسان بسازد | متوسط | OTP ایمیل | ادغام پیشنهادی + هشدار |
| S11 | فایل رسید/آپلود: مسیر و نوع فایل نیاز به بازبینی دارد | متوسط | portal | MIME whitelist + خروج از webroot + نام تصادفی |
| S12 | عدم وجود FK و سیاست حذف | متوسط | همه | ایندکس + پاکسازی یتیم در job |
| S13 | `uninstall.php` برخی افزونه‌ها داده را DROP می‌کند | متوسط | case-file/contact/sms/feed | محافظت با پرچم صریح «حذف داده» |
| S14 | ناهماهنگی نسخه (2fa) و رجیستری جدول ناقص | پایین | 2fa/core | یکسان‌سازی |

### ۷.۲ مدل دسترسی (خلاصه)

| داده | مراجع | منشی | درمانگر خودش | سرپرست |
|---|---|---|---|---|
| نام/موبایل | خودش | ✅ | ✅ | ✅ |
| فرم ارزیابی | خودش | ❌ | ✅ | ✅ |
| یادداشت جلسه | ❌ | ❌ | ✅ | ✅ |
| نتیجه آزمون | خلاصه (اختیاری) | ❌ | ✅ | ✅ |
| آزمون خام/پاسخ‌ها | ❌ | ❌ | ✅ | ✅ |
| اطلاعات همسر | فقط `share_basic` | ❌ | ✅ (با رضایت) | ✅ |
| مالی | خودش | ✅ | ✅ | ✅ |
| لاگ دسترسی | ❌ | ❌ | ❌ | ✅ |

### ۷.۳ کنترل‌های الزامی برای هر endpoint جدید
nonce + capability + مالکیت رکورد + rate limit + `sanitize_*` + prepared
statement + لاگ دسترسی + پاسخ `no-store` برای داده‌ی شخصی.

---

## بخش هشتم: حفظ داده و برنامه‌ی مهاجرت

### ۸.۱ قوانین غیرقابل مذاکره (تطبیق با ۱۴ بند شما)
1. هیچ `DROP`/`RENAME`/`MODIFY` مخرب. فقط `CREATE`/`ADD COLUMN`/`ADD INDEX`.
2. همه‌ی تغییرات با `dbDelta` و `option` نسخه‌ی DB.
3. هر ستون جدید `NOT NULL DEFAULT` یا `NULL`.
4. Feature Flag: `pas_integration_v1` (خاموش/روشن/فقط‌خواندنی).
5. پشتیبان: دیتابیس + فایل کلید رمزنگاری.
6. مهاجرت ابتدا روی staging با کپی داده‌ی واقعی.
7. Backfill هویت **فقط پیشنهاد** می‌دهد؛ ادغام دستی.
8. هیچ داده‌ی «ناقص/بی‌تطابق» حذف نمی‌شود؛ در `pas_migration_log` ثبت می‌شود.
9. Rollback: خاموش‌کردن flag = بازگشت رفتار قبلی (کد قدیمی همچنان کار می‌کند).
10. قبل از انتشار: ماتریس تست (بخش ۱۰).

### ۸.۲ مراحل مهاجرت (Dry-run → Apply)

| گام | کار | ریسک | بازگشت |
|---|---|---|---|
| M0 | پشتیبان کامل + سنجش کلید رمزنگاری + شمارش رکوردها | پایین | بازگردانی فایل |
| M1 | ساخت جداول جدید core | پایین | `DROP` جداول خالی (فقط قبل از نوشتن) |
| M2 | Backfill هویت از ۵ منبع (portal user, case, quiz, lead, contact) | متوسط | جدول فقط‌خواندنی؛ حذف بی‌خطر |
| M3 | گزارش تعارض (`conflict_report`) و ادغام دستی | متوسط | برگشت‌پذیر (soft merge) |
| M4 | افزودن ۵ ستون به `pas_cf_cases` + ایندکس‌ها | پایین | ستون‌ها بی‌اثر |
| M5 | ثبت رویدادها از این پس (نه backfill انبوه) | پایین | flag |
| M6 | نگاشت فرم ارزیابی (فقط پیش‌نمایش) | متوسط | هیچ نوشتنی |
| M7 | نگاشت با تأیید درمانگر + provenance | متوسط | snapshot قابل بازگردانی |
| M8 | لینک نوبت/آزمون به client (فقط `pas_links`) | پایین | حذف ردیف‌ها |
| M9 | افزودن `client_id` به نظرسنجی (nullable) | پایین | nullable |
| M10 | نظرسنجی هدفمند + صف اعلان | متوسط | flag |

### ۸.۳ تشخیص داده‌ی قدیمی
هر رکورد جدید `source_version` دارد. رکوردهای قبل از مهاجرت با
`source='legacy'` و بدون `provenance` علامت می‌خورند و در UI «اطلاعات
قدیمی — تأیید نشده» نشان داده می‌شوند.

---

## بخش نهم: پیشنهادهای من (۱۸ مورد، اولویت‌دار)

| # | پیشنهاد | مشکل | ارزش | افزونه‌ها | پیچیدگی | نسخه |
|---|---|---|---|---|---|---|
| I1 | Client Registry با ادغام کنترل‌شده | هویت پراکنده | یک مراجع، یک پرونده، یک جست‌وجو | core+همه | متوسط | MVP |
| I2 | تشکیل خودکار پرونده پس از فرم ارزیابی | پرونده دستی/فراموش‌شده | صفر ورود دوباره | portal+case | متوسط | MVP |
| I3 | نگاشت فیلد + provenance | دوباره‌پرسیدن + تداخل | شفافیت منبع/تاریخ | core | متوسط | MVP |
| I4 | تشخیص فرم ناقص + یادآوری | فرم نیمه‌کاره | نرخ تکمیل بالاتر | portal+notify | کم | MVP |
| I5 | داشبورد یکپارچه درمانگر | جابه‌جایی بین ۴ افزونه | صرفه‌جویی روزانه | core+همه | بالا | MVP |
| I6 | تایم‌لاین پرونده | از دست رفتن توالی اتفاقات | دید کلنگر | core+همه | متوسط | MVP |
| I7 | جلسه به‌عنوان موجودیت (فردی/زوجی) | جلسه = یادداشت | نظرسنجی/یادآوری/گزارش | case+portal | متوسط | MVP |
| I8 | جست‌وجوی هوشمند (نام/موبایل/همسر) | چند جست‌وجوی جدا | سرعت | core | کم | MVP |
| I9 | نظرسنجی هدفمند + خودکار | نظرسنجی بی‌انتساب | بازخورد واقعی | portal+notify | متوسط | فاز ۲ |
| I10 | زوج با دعوت/رضایت + نمای مقایسه‌ای | اتصال با شماره | اعتماد و قانون‌مندی | core+case | بالا | فاز ۲ |
| I11 | تجزیه‌ی «تفسیر سیستم» و «تفسیر درمانگر» | مرز نامشخص | مسئولیت بالینی | quiz+case | کم | فاز ۲ |
| I12 | رضایت‌نامه‌ی نسخه‌دار و لغو | هیچ رضایتی ثبت نمی‌شود | انطباق | core | کم | فاز ۲ |
| I13 | اعلان مرکزی + opt-out | ارسال‌های پراکنده | کنترل و گزارش | core | متوسط | فاز ۲ |
| I14 | گزارش‌های مدیریتی (روند مراجعه/راکد/تبدیل لید) | تصمیم بدون داده | مدیریت | core | متوسط | فاز ۲ |
| I15 | یکپارچه‌سازی فرم تماس با لید | سرنخ گم‌شده | تبدیل بیشتر | contact+portal | کم | فاز ۲ |
| I16 | مدیریت تکالیف بین جلسات | پیگیری دستی | مشارکت مراجع | case | متوسط | فاز ۲ |
| I17 | مدیریت اسناد/فایل با کنترل دسترسی | فایل پراکنده | نظم و امنیت | case | متوسط | فاز ۳ |
| I18 | REST API برای اپ موبایل | وابستگی به پنل وب | دسترسی آسان‌تر | core | بالا | آینده |

سه پیشنهاد اضافه‌ی من که در درخواست نبود: **کارت «پرونده‌ی راکد»**
(موجود در case-file، باید به داشبورد بیاید)، **گزارش تبدیل لید→نوبت→جلسه**،
و **سیاست نگهداری/حذف داده (retention & erasure)** مطابق الزامات قانونی.

---

## بخش دهم: نقشه‌ی اجرایی (فازهای کوچک، تست‌پذیر، بازگشت‌پذیر)

> هر فاز فقط با تأیید شما شروع می‌شود و پایان هر فاز گزارش + نقطه‌ی بازگشت دارد.

### فاز ۰ — ایمن‌سازی و خط مبنا (بدون تغییر رفتار)
- **افزونه‌ها:** sms-bridge، core، مستندات.
- **فایل‌ها:** `pivand-amn-sms-bridge.php` (حذف اعتبارنامه‌ی هاردکد)،
  `class-filter-registry.php`/`class-table-registry.php` (تکمیل)، `CHANGELOG`.
- **کار:** چرخش اعتبارنامه، پشتیبان، شمارش رکوردها، اسکن `enc1:`، همسان‌سازی نسخه‌ی 2fa.
- **تست:** ارسال پیامک با کلید جدید؛ فعال/غیرفعال‌سازی بدون خطا؛ مقایسه‌ی تعداد رکوردها قبل/بعد.
- **معیار موفقیت:** صفر خطای PHP؛ پیامک کار می‌کند؛ هیچ داده‌ای تغییر نکرده.
- **ریسک:** توقف پیامک اگر کلید جدید اشتباه باشد → تست با شماره‌ی خودتان.

### فاز ۱ — رجیستری هویت (فقط‌خواندنی)
- **افزونه‌ها:** core (جداول + API)، بدون تغییر در مصرف‌کننده‌ها.
- **فایل‌ها:** `includes/class-client-registry.php` (جدید)،
  `includes/class-installer.php` (جدید)، `pivand-amn-core.php`، `ecosystem-map.md`.
- **کار:** `pas_clients`, `pas_client_identifiers`, `pas_links`; backfill;
  گزارش تعارض; فیلتر `pas_get_client`.
- **تست:** تطبیق ۱۰۰٪ رکوردهای portal↔case با شماره؛ هیچ ادغام خودکار؛
  دو کاربر با شماره‌ی یکسان → تعارض گزارش شود.
- **معیار:** هر مراجع ≥۱ `client_id`؛ صفر رکورد گم‌شده.
- **ریسک:** شماره‌های نرمال‌نشده → اول نرمال‌سازی، بعد تطبیق.

### فاز ۲ — نگاشت فرم ارزیابی به پرونده
- **افزونه‌ها:** case-file، portal.
- **فایل‌ها:** `class-case-file.php`, `class-installer.php`, `class-admin.php`
  (کارت پیش‌نمایش)، `class-intake.php` (نسخه‌ی schema)، جداول نگاشت در core.
- **کار:** ۵ ستون جدید، جدول نگاشت، پیش‌نمایش، تأیید درمانگر، provenance.
- **تست:** فرم با ۲۰ فیلد → ۱۵ فیلد پر، ۵ خالی؛ هیچ overwrite بی‌سابقه؛
  بازگردانی نسخه‌ی قبلی.
- **معیار:** صفر ورود دوباره برای فیلدهای موجود.
- **ریسک:** اختلاف واحد سن/تاریخ → تست با ۱۰ رکورد واقعی (کپی).

### فاز ۳ — جلسه و تایم‌لاین
- **افزونه‌ها:** case-file (مدل جلسه)، portal (نوبت)، core (رویداد).
- **کار:** `session_type`, `couple_session_id`, `booking_id`, رویداد
  `session.*`, تایم‌لاین در پرونده.
- **تست:** جلسه‌ی فردی/زوجی/نوبت بدون جلسه؛ ترتیب زمانی؛ عدم نمایش یادداشت
  به عضو دیگر.
- **معیار:** تایم‌لاین کامل برای ۵ پرونده‌ی نمونه.

### فاز ۴ — داشبورد یکپارچه (MVP)
- **افزونه‌ها:** core (read-model) + یک صفحه‌ی واحد.
- **کار:** KPI، جست‌وجو، «نیازمند اقدام»، جدول مراجعان، فیلترها.
- **تست:** زمان بارگذاری < ۲ ثانیه با ۵۰۰ مراجع (ایندکس‌ها)؛ مجوزها.
- **معیار:** درمانگر بدون باز کردن ۴ افزونه کار روزانه را انجام دهد.

### فاز ۵ — زوج و رضایت
- **کار:** `pas_couples`, دعوت، پذیرش، نمای مقایسه‌ای، قطع رابطه.
- **تست:** دعوت نادرست، رد، لغو رضایت، دسترسی پس از قطع.
- **معیار:** صفر اتصال بدون رضایت.

### فاز ۶ — نظرسنجی هدفمند + اعلان
- **کار:** `survey_targets` (client/session/type), قواعد تأخیر، صف، لاگ، opt-out.
- **تست:** ۲۴/۷۲ ساعت، توقف، تکرار ارسال، لینک یک‌بارمصرف.
- **معیار:** هر پاسخ به یک جلسه/مراجع قابل انتساب.

### فاز ۷ — Design System و بازطراحی UI
- **کار:** توکن‌های برند، کامپوننت‌ها، داشبورد، پنل مراجع، آزمون.
- **تست:** RTL، موبایل ۳۶۰px، کنتراست AA، بدون حذف قابلیت.
- **معیار:** ظاهر یکدست در ۵ افزونه؛ هیچ رگرسیون عملکردی.

### فاز ۸ — API موبایل (آینده)
- **کار:** REST با احراز توکن، مجوز per-scope، rate limit.
- **تست:** نشت داده بین دو حساب؛ انقضای توکن.

---

## بخش یازدهم: اولویت‌بندی

**MVP (نسخه اول):** I1, I2, I3, I4, I5, I6, I7, I8 + فاز ۰ و ۱ و ۲ و ۳ و ۴.
**فاز دوم:** I9, I10, I11, I12, I13, I14, I15, I16 + فاز ۵ و ۶.
**آینده:** I17, I18, گزارش‌های پیشرفته، retention/erasure tự động، اپ موبایل.

**خارج از MVP (عمداً):** یکپارچه‌سازی content-writer و daily-feed با داده‌ی
درمانی (هیچ ارزش بالینی ندارد و سطح حمله را زیاد می‌کند). فرم تماس فقط
«لید» می‌شود.

---

## بخش دوازدهم: تصمیم‌های موردنیاز از شما (به ترتیب اهمیت)

1. **مالکیت هویت:** آیا `client_id` در هسته (پیشنهاد من) یا در پنل مراجعان؟
2. **ادغام حساب:** وقتی موبایل و ایمیل یک انسان دو حساب ساختند، ادغام
   **خودکار با تأیید مراجع** یا **فقط دستی توسط ادمین**؟
3. **اطلاعات همسر:** نگه‌داشتن در همان پرونده (`spouse_*` فعلی) یا انتقال
   تدریجی به پرونده/پروفایل مستقل با دسترسی جدا؟ (پیشنهاد: دومی، مرحله‌ای)
4. **حداقل داده‌ی نمای مشترک زوج:** کدام فیلدها؟ (پیشنهاد: نام، سن، وضعیت
   تأهل، وضعیت فرم، تعداد جلسات — بدون آزمون و بدون یادداشت)
5. **اثبات هویت برای اتصال زوج:** OTP پیامکی هر دو طرف، یا تأیید دستی
   درمانگر با رضایت مکتوب، یا هر دو؟
6. **دسترسی درمانگر به آزمون:** آیا درمانگر باید گزارش کامل ببیند یا خلاصه؟
   آیا آزمون‌های «حساس» (ACE, AUDIT, MDQ) لایه‌ی مجوز جدا می‌خواهند؟
7. **اجبار 2FA:** برای administrator و therapist اجباری شود؟
8. **نظرسنجی:** چه کسی مجاز به ارسال است و آیا مراجع می‌تواند opt-out کند؟
9. **نگهداری داده:** چند سال؟ آیا «حذف به درخواست مراجع» باید داده‌ی بالینی
   را هم (با الزام قانونی) پاک کند یا فقط ناشناس‌سازی؟
10. **پالت بصری:** تأیید پالت `#51483F/#F4F0E8/#B8ADA0/#C8B48A` به‌جای آبی
    فعلی `tokens.css`؟
11. **محدوده‌ی MVP:** تأیید فهرست بخش ۱۱؟
12. **روش تحویل:** فایل zip هر فاز (مثل گذشته) یا مخزن گیت با شاخه‌ی جدا؟

---

## پیوست: ریسک‌های پروژه

| ریسک | اثر | کاهش |
|---|---|---|
| رمزگشایی نشدن داده‌های قدیمی (کلید گم‌شده) | از دست رفتن دسترسی به یادداشت‌ها | فاز ۰: سنجش و پشتیبان کلید |
| ادغام اشتباه دو انسان | نقض حریم خصوصی | فقط پیشنهاد + تأیید دستی |
| تغییر رفتار پرونده برای درمانگر | نارضایتی | feature flag + رابط آشنای قبلی |
| کندی داشبورد با داده‌ی زیاد | تجربه‌ی ضعیف | read-model + ایندکس + کش کوتاه |
| ارسال انبوه پیامک/ایمیل | هزینه و اسپم | صف + سقف + opt-out + لاگ |
| وابستگی به افزونه‌ی غیرفعال | خرابی بی‌صدا | `Pas_Core_Dependency_Checker` + پیام واضح |

---

*پایان گزارش مرحله ۱ — منتظر تأیید شما برای شروع فاز ۰. هیچ کدی تغییر نکرده است.*
