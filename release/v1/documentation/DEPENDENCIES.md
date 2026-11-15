# نقشهٔ وابستگی‌ها — اکوسیستم پیوند امن v1

حداقل نسخهٔ Core مورد نیاز: **1.8.1**. همهٔ ۱۱ افزونه به `PUI` (توکن‌های UI در core) و
`Pas_Core_*` (رمزنگاری/توکن/کمکی) وابسته‌اند؛ بنابراین core پیش‌نیاز سختِ همه است.

## ۱. جدول وابستگی

| افزونه | نیاز سخت | نیاز نرم (بدون آن هم بالا می‌آید) | قرارداد ارائه‌شده |
|---|---|---|---|
| pivand-amn-core | — | — | `pas_get_client` |
| pivand-amn-sms-bridge | core | — | `pas_send_sms` |
| pivand-amn-smtp-mailer | core | — | — |
| pivand-amn-2fa | core | — | — |
| pivand-amn-case-file | core | quiz (کارت روند ارزیابی) | `pas_get_case_summary_for_phone`، نام مراجع روی زنجیرهٔ `pas_lookup_known_client_name` |
| pivand-amn-quiz-enterprise | core | — | `psy_get_pending_quizzes_for_phone`، `psy_get_client_name_for_phone`، `psy_get_completed_results_for_phone`، `psy_get_assessment_scores_for_phone`، `psy_get_mobile_for_email` |
| pivand-amn-client-portal | core | sms-bridge (OTP)، quiz (لیست آزمون‌ها) | `zdp_get_intake_for_phone`، `zdp_get_client_panel_url` |
| payvand-amn-ui-kit | core | — | — |
| payvand-contact | core | — | — |
| pivand-amn-content-writer | core | — | — |
| piwand-daily-feed | core | — | — |

## ۲. قراردادهای مصرف‌شده

| قرارداد | تولیدکننده | مصرف‌کننده |
|---|---|---|
| `pas_send_sms` | sms-bridge | client-portal، case-file، 2fa، core |
| `pas_get_client` | core | case-file، client-portal |
| `pas_lookup_known_client_name` | case-file (اولویت ۱۰) | core (پنل مراجعان) |
| `pas_get_case_summary_for_phone` | case-file | client-portal |
| `pas_cf_timeline_events` | case-file (نقطهٔ توسعه) | سایر افزونه‌ها رویداد اضافه می‌کنند |
| `zdp_get_intake_for_phone` | client-portal | case-file |
| `zdp_get_client_panel_url` | client-portal | quiz (دعوت‌نامهٔ آزمون) |
| `psy_get_pending_quizzes_for_phone` | quiz | client-portal |
| `psy_get_client_name_for_phone` | quiz | case-file، client-portal |
| `psy_get_completed_results_for_phone` | quiz | client-portal، case-file |
| `psy_get_assessment_scores_for_phone` | quiz | case-file (رجیستری پرونده) |
| `psy_get_mobile_for_email` | quiz (Invitation_Manager) | client-portal (ورود با ایمیل) |

راهنمای کامل ثبت/بازیابی: `plugins/pivand-amn-core/includes/class-filter-registry.php` (۱۳ قرارداد).

## ۳. ترتیب نصب و ارتقا

```
نصب:     core → sms-bridge → smtp-mailer → 2fa → case-file → quiz
         → client-portal → ui-kit → payvand-contact → content-writer → daily-feed

ارتقا:   core → 2fa → sms-bridge → smtp-mailer → case-file → quiz
         → client-portal → ui-kit → payvand-contact → content-writer → daily-feed

غیرفعال‌سازی/بازگشت: ترتیب معکوس (core آخر)
```

## ۴. رفتار در نبود وابستگی

| وضعیت | رفتار انتظاری |
|---|---|
| core غیرفعال | هیچ افزونه‌ای نباید فعال بماند؛ ابتدا همه را غیرفعال کنید |
| sms-bridge غیرفعال | OTP مراجع و پیامک پرونده کار نمی‌کند؛ خطای گرفته‌شده و پیام کاربرپسند |
| quiz غیرفعال | کارت روند ارزیابی case-file خالی می‌ماند (بدون Fatal) |
| client-portal غیرفعال | پنل مراجع و ورود با موبایل غیرفعال می‌شود |
| ui-kit غیرفعال | ظاهر ساده، عملکرد سالم |

## ۵. قواعد نگهداری

- هیچ افزونه‌ای را مستقل و بدون بررسی قراردادهای بالا ارتقا ندهید.
- هر تغییر در `class-filter-registry.php` نیازمند به‌روزرسانی همین سند است.
- دو ارائه‌دهندهٔ هم‌زمان برای یک قرارداد ممنوع است.
