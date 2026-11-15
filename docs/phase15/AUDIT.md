# فاز ۱۵ — ممیزی کامل سورس (گام ۱)

تاریخ: 2026-11-14 · شاخه: `freebuff/662f992a98a3793b148463a5` · HEAD پیش از شروع: `a80686c` · تغییر محلی: صفر

## ۱. ماتریس سازگاری ۱۱ افزونه

| افزونه | نسخهٔ هدف | نسخهٔ سورس | اصلی | PHP | فایل | uninstall | readme | `php -l` |
|---|---|---|---|---|---|---|---|---|
| pivand-amn-core | 1.8.1 | 1.8.1 ✅ | `pivand-amn-core.php` | 19 | 35 | دارد | ندارد | پاس |
| pivand-amn-sms-bridge | 1.1.7 | 1.1.7 ✅ | `pivand-amn-sms-bridge.php` | 5 | 8 | دارد | دارد | پاس |
| pivand-amn-smtp-mailer | 1.5.4 | 1.5.4 ✅ | `pivand-amn-smtp-mailer.php` | 2 | 5 | دارد | دارد | پاس |
| pivand-amn-2fa | 1.3.5 | 1.3.5 ✅ | `pivand-amn-2fa.php` | 6 | 9 | دارد | دارد | پاس |
| pivand-amn-case-file | 1.16.1 | 1.16.1 ✅ | `pivand-amn-case-file.php` | 12 | 16 | دارد | ندارد | پاس |
| pivand-amn-quiz-enterprise | 3.8.17 | 3.8.17 ✅ | `pivand-amn-quiz-enterprise.php` | 55 | 67 | دارد | ندارد | پاس |
| pivand-amn-client-portal | 1.13.1.0 | 1.13.1.0 ✅ | `pivand-amn-client-portal.php` | 23 | 30 | دارد | دارد | پاس |
| payvand-amn-ui-kit | 1.2.0 | 1.2.0 ✅ | `payvand-amn-ui-kit.php` | 5 | 17 | ندارد (CSS/توکن) | دارد | پاس |
| payvand-contact | 1.4.4 | 1.4.4 ✅ | `payvand-contact.php` | 2 | 6 | دارد | ندارد | پاس |
| pivand-amn-content-writer | 2.9.18 | 2.9.18 ✅ | `pivand-amn-content-writer.php` | 20 | 28 | دارد | دارد | پاس |
| piwand-daily-feed | 1.1.6 | 1.1.6 ✅ | `piwand-daily-feed.php` | 8 | 13 | دارد | دارد | پاس |

نکته: فایل اصلی content-writer `pivand-amn-content-writer.php` است (نه `backfill-excerpts.php`).
افزونهٔ quiz سه فایل تست سراسری در ریشه دارد که در بستهٔ Production حذف می‌شوند؛ ۲۸ آزمون داخل
`includes/tests/` **بخشی از محصول** است و در بسته می‌ماند.

## ۲. قراردادهای بین‌افزونه‌ای (`class-filter-registry.php` — ۱۳ قرارداد)

| قرارداد | تولیدکننده | مصرف‌کننده | وضعیت |
|---|---|---|---|
| `pas_send_sms` | sms-bridge | client-portal، case-file، 2fa، core | ثبت‌شده در محیط |
| `pas_get_client` | core | case-file، client-portal | ثبت‌شده |
| `pas_lookup_known_client_name` | case-file | core | ثبت‌شده |
| `pas_get_case_summary_for_phone` | case-file | client-portal | ثبت‌شده |
| `pas_cf_timeline_events` | case-file (نقطهٔ توسعه) | سایر افزونه‌ها | ثبت‌شده |
| `zdp_get_intake_for_phone` | client-portal | case-file | ثبت‌شده |
| `zdp_get_client_panel_url` | client-portal | quiz | ثبت‌شده |
| `psy_get_pending_quizzes_for_phone` | quiz | client-portal | ثبت‌شده |
| `psy_get_client_name_for_phone` | quiz | case-file، client-portal | ثبت‌شده |
| `psy_get_completed_results_for_phone` | quiz | client-portal، case-file | ثبت‌شده |
| **`psy_get_assessment_scores_for_phone`** | quiz | **case-file** (`class-case-file.php:1095`) | ثبت‌شده، ۱ ردیف برای مراجع دارای آزمون |
| `psy_get_mobile_for_email` | quiz (Invitation_Manager) | client-portal | ثبت‌شده |
| `zdp_*` (پنل) | client-portal | core/quiz | ثبت‌شده |

`case-file` هیچ کوئری مستقیمی به جدول‌های `psy_*` ندارد؛ کل ارتباط از طریق قرارداد است. ✅

## ۳. REST API

| افزونه | مسیرها |
|---|---|
| quiz | `psy/v1/quiz`, `/submit`, `/draft`, `/report/(?P<id>\d+)`, `/report/(?P<id>\d+)/pdf` |
| client-portal | `zdp/v1/panel-state`, `/send-otp`, `/verify-otp`, `/send-otp-email`, `/verify-otp-email`, `/booking-grid` |

## ۴. قابلیت‌ها

`manage_options` (۹۹ ارجاع)، `pas_manage_case_files` (۵)، `pas_view_all_case_files` (۳)، `zdp_manage_clients` (۳).

## ۵. Cron

| هوک | تناوب | افزونه |
|---|---|---|
| `zsm_*` (۵ دقیقه) | 5min | content-writer |
| تولید روزانه | daily | daily-feed |
| یادآور پرونده | hourly | case-file |
| `zdp_expire_stale_pending_bookings` | hourly | client-portal |

## ۶. دیتابیس (snapshot: `docs/phase15/db-inventory.json`)

- ۳۷ جدول، ۸۳۹ ردیف.
- نسخهٔ Schema: `pas_core_db_version=1.0.0`، `psy_quiz_db_version=3.10`، `pas_cf_db_version=1.16.1`،
  `zdp_db_version=1.0.6`، `aco_db_version=2.9.0`، `pas_mapper_db_version=1.0.0`،
  `pas_bridge_db_version=1.1.7`، `payvand_version=1.4.3`.
- کلیدهای هویت: `wp_pas_clients` (۳۸)، `wp_pas_client_identifiers` (۴۶)، `wp_pas_cf_cases` (۴۵، با `phone` و `assigned_therapist`).

## ۷. رمزنگاری

- `Pas_Core_Crypto`، پیشوند `enc1:`، کلید از `PAS_CORE_ENCRYPTION_KEY` یا `wp-content/pas-encryption-key.php`.
- آزمون رفت‌وبرگشت با رشتهٔ موقت: **PASS**. مقدار کلید در هیچ گزارشی چاپ نشد.
- دادهٔ موجود: پاسخ‌های آزمون ۱ با ۱۶ کلید رمزگشایی شد (بدون چاپ محتوا).

## ۸. محیط آزمایش

WordPress 7.1.4 · PHP 8.5.8 · theme twentytwentyfive · هر ۱۱ افزونه active ·
mu-plugin `p11-sms-sim.php` مخصوص Staging (در بستهٔ Production نیست).
