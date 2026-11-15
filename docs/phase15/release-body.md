# پیوند امن — انتشار v1

اکوسیستم ۱۱ افزونه‌ای «پیوند امن» به‌صورت بسته‌های مستقل و آمادهٔ نصب.
آزمون‌شده روی **WordPress 7.1.4** و **PHP 8.5.8** — حکم: **GO — READY FOR PRODUCTION**.

## بسته‌ها

| افزونه | نسخه | حجم (بایت) |
|---|---|---|
| pivand-amn-core | 1.8.1 | 426,281 |
| pivand-amn-sms-bridge | 1.1.7 | 16,362 |
| pivand-amn-smtp-mailer | 1.5.4 | 20,288 |
| pivand-amn-2fa | 1.3.5 | 16,450 |
| pivand-amn-case-file | 1.16.1 | 71,539 |
| pivand-amn-quiz-enterprise | 3.8.17 | 406,113 |
| pivand-amn-client-portal | 1.13.1.0 | 269,259 |
| payvand-amn-ui-kit | 1.2.0 | 243,248 |
| payvand-contact | 1.4.4 | 25,752 |
| pivand-amn-content-writer | 2.9.18 | 182,631 |
| piwand-daily-feed | 1.1.6 | 36,869 |

جمع: ۱٬۷۱۴٬۷۹۲ بایت. SHA-256 همهٔ بسته‌ها در فایل ضمیمهٔ `checksums.sha256` و در
`release/v1/manifests/checksums.sha256` مخزن آمده است.

## ترتیب نصب

`core → sms-bridge → smtp-mailer → 2fa → case-file → quiz → client-portal → ui-kit → payvand-contact → content-writer → daily-feed`

راهنمای کامل: `release/v1/documentation/INSTALL.md`.

## آزمون‌های انجام‌شده

- ساخت و بازبینی خودکار ۱۱ ZIP (نام پوشه، نسخهٔ هدر، CRC، نبود فایل محرمانه/Staging، `php -l`).
- نصب تازه، ارتقا از quiz 3.6.7 و بازگشت به نسخهٔ قبلی — بدون تغییر داده (۳۷ جدول، ۸۳۹ ردیف).
- امنیت دسترسی: ۴۰۱/۴۰۳ برای مهمان و نقش‌های غیرمجاز، IDOR درمانگر بسته، جداسازی دادهٔ مراجعان.
- رمزنگاری: رفت‌وبرگشت کلید موفق؛ بازیابی بکاپ در دیتابیس مستقل (۱۷۴ دستور، ۰ خطا) با رمزگشایی موفق.
- E2E: ورود مراجع با OTP (سرویس پیامک Mock)، ثبت/محاسبهٔ نمره، پرونده، پنل درمانگر و مراجع.

## موارد آزمون‌نشده (شفاف)

ارسال واقعی پیامک به اپراتور، ایمیل/SMTP، جریان تعاملی 2FA با اپ TOTP و درگاه پرداخت با دادهٔ واقعی
در محیط آزمون قابل بررسی نبودند و باید در پنجرهٔ استقرار Production طبق چک‌لیست `INSTALL.md` تأیید شوند.

## اسناد

`release/v1/documentation/`: INSTALL، UPGRADE، ROLLBACK، SECURITY، RELEASE-NOTES، DEPENDENCIES، DISASTER-RECOVERY
(همه فارسی و فنی).
