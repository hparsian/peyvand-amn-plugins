# یادداشت‌های انتشار — v1

تاریخ بسته‌بندی: 2026-11-14 · کانال: Production · پایهٔ آزمایش: WordPress 7.1.4 / PHP 8.5.8

## ۱. بسته‌های این انتشار

| # | افزونه | نسخه | حجم (بایت) |
|---|---|---|---|
| 1 | pivand-amn-core | 1.8.1 | 426281 |
| 2 | pivand-amn-sms-bridge | 1.1.7 | 16362 |
| 3 | pivand-amn-smtp-mailer | 1.5.4 | 20288 |
| 4 | pivand-amn-2fa | 1.3.5 | 16450 |
| 5 | pivand-amn-case-file | 1.16.1 | 71539 |
| 6 | pivand-amn-quiz-enterprise | 3.8.17 | 406113 |
| 7 | pivand-amn-client-portal | 1.13.1.0 | 269259 |
| 8 | payvand-amn-ui-kit | 1.2.0 | 243248 |
| 9 | payvand-contact | 1.4.4 | 25752 |
| 10 | pivand-amn-content-writer | 2.9.18 | 182631 |
| 11 | piwand-daily-feed | 1.1.6 | 36869 |

جمع: ۱٫۷۱ مگابایت. SHA-256 هر بسته در `manifests/checksums.sha256` ثبت شده است.

## ۲. تغییرات این نسخه از quiz-enterprise (۳.۸.۱۳ تا ۳.۸.۱۷)

- **۳.۸.۱۳** — حذف کامل استایل درون‌خطی از خروجی افزونه (۵۷ → ۰ مورد `style="`).
- **۳.۸.۱۴** — رفع نمودار خالی «مقایسهٔ زوجین»: اسکریپت پیش از بارگذاری Chart.js اجرا می‌شد.
- **۳.۸.۱۵** — رفع خطای مهلک FMS/PAM در فراخوانی `pa_decrypt_json()`.
- **۳.۸.۱۶** — رادار ECR به میل‌ها منتقل شد؛ `frontend.css` فقط در نمای تک‌گزارش ادمین enqueue می‌شود.
- **۳.۸.۱۷** — تعادل بوم (canvas) نمودارهای FMS/PAM.

## ۳. تغییرات release engineering

- اسکریپت `scripts/build-release.py`: ساخت بازتولیدپذیر ZIPها (زمان ثابت، مرتب‌سازی، حذف فایل‌های غیرمجاز) و بازبینی خودکار هر بسته.
- حذف `.git`, `.github`, `node_modules`, لاگ‌ها، بکاپ‌ها، فایل‌های `tests/` سطح ریشه، فایل‌های محیطی و کلید از بسته‌ها.
- تولید مانیفست‌ها: `release-manifest.json`، `dependency-map.json`، `compatibility-report.json`، `checksums.sha256`.

## ۴. سازگاری

- سه قرارداد حیاتی بین‌افزونه‌ای در محیط واقعی بررسی شد و فعال است:
  `psy_get_assessment_scores_for_phone`، `pas_get_case_summary_for_phone`، `zdp_get_intake_for_phone`.
- نصب تازه از ZIPها، ارتقا از quiz 3.6.7 به 3.8.17 و بازگشت به نسخهٔ قبلی، هر سه بدون تغییر داده انجام شد.

## ۵. نکات ارتقا

- ترتیب نصب/ارتقا را از [DEPENDENCIES.md](DEPENDENCIES.md) بگیرید.
- کلید رمزنگاری تعویض نشود.
- پس از ارتقا، آزمون‌های پذیرش [UPGRADE.md](UPGRADE.md) را اجرا کنید.

## ۶. محدودیت‌های شناخته‌شده

- ارسال واقعی پیامک و ایمیل در محیط آزمایش با Mock بررسی شد؛ تحویل واقعی به اپراتور در این انتشار آزمون نشده است.
- درگاه پرداخت با دادهٔ واقعی آزمون نشده است.
