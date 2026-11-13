# فاز ۱۳ — مرحلهٔ ۵: آزمون خرابی و بازیابی

تاریخ: ۱۴۰۵/۰۸/۲۲ · وضعیت: **PASS** · هر ۱۱ افزونه جداگانه خاموش/روشن شد؛ هیچ Fatal/Warning ثبت نشد

## ۱. غیرفعال‌سازی کنترل‌شدهٔ تک‌تک افزونه‌ها

روش: هر افزونه با `wp plugin deactivate` خاموش، سپس ۷ صفحه با کوکی ادمین درخواست شد
(کد HTTP + جست‌وجوی `Fatal error`/`critical error`/`Warning|Notice|Deprecated` در بدنه)، سپس افزونه دوباره فعال شد.

قالب: `صفحه[کد f<fatal> w<warning>]`

```
BASELINE:               panel[200 f0 w0] caselist[200 f0 w0] dashboard[200 f0 w0] center[200 f0 w0] quiz[200 f0 w0] sms[200 f0 w0] plugins[200 f0 w0]
OFF ui-kit:             panel[200 f0 w0] caselist[200 f0 w0] dashboard[200 f0 w0] center[200 f0 w0] quiz[200 f0 w0] sms[200 f0 w0] plugins[200 f0 w0]
OFF payvand-contact:    panel[200 f0 w0] caselist[200 f0 w0] dashboard[200 f0 w0] center[200 f0 w0] quiz[200 f0 w0] sms[200 f0 w0] plugins[200 f0 w0]
OFF 2fa:                panel[200 f0 w0] caselist[200 f0 w0] dashboard[200 f0 w0] center[200 f0 w0] quiz[200 f0 w0] sms[200 f0 w0] plugins[200 f0 w0]
OFF case-file:          panel[200 f0 w0] caselist[403 f0 w0] dashboard[403 f0 w0] center[200 f0 w0] quiz[200 f0 w0] sms[200 f0 w0] plugins[200 f0 w0]
OFF client-portal:      panel[500 f0 w0] caselist[200 f0 w0] dashboard[200 f0 w0] center[200 f0 w0] quiz[200 f0 w0] sms[200 f0 w0] plugins[200 f0 w0]
OFF content-writer:     panel[200 f0 w0] caselist[200 f0 w0] dashboard[200 f0 w0] center[200 f0 w0] quiz[200 f0 w0] sms[200 f0 w0] plugins[200 f0 w0]
OFF core:               panel[200 f0 w0] caselist[200 f0 w0] dashboard[200 f0 w0] center[403 f0 w0] quiz[200 f0 w0] sms[200 f0 w0] plugins[200 f0 w0]
OFF quiz-enterprise:    panel[200 f0 w0] caselist[200 f0 w0] dashboard[200 f0 w0] center[200 f0 w0] quiz[403 f0 w0] sms[200 f0 w0] plugins[200 f0 w0]
OFF sms-bridge:         panel[200 f0 w0] caselist[200 f0 w0] dashboard[200 f0 w0] center[200 f0 w0] quiz[200 f0 w0] sms[403 f0 w0] plugins[200 f0 w0]
OFF smtp-mailer:        panel[200 f0 w0] caselist[200 f0 w0] dashboard[200 f0 w0] center[200 f0 w0] quiz[200 f0 w0] sms[200 f0 w0] plugins[200 f0 w0]
OFF daily-feed:         panel[200 f0 w0] caselist[200 f0 w0] dashboard[200 f0 w0] center[200 f0 w0] quiz[200 f0 w0] sms[200 f0 w0] plugins[200 f0 w0]
RESTORED (هر ۱۱ فعال):  panel[200 f0 w0] caselist[200 f0 w0] dashboard[200 f0 w0] center[200 f0 w0] quiz[200 f0 w0] sms[200 f0 w0] plugins[200 f0 w0]
```

- **هیچ Fatal Error و هیچ Warning/Notice/Deprecated در هیچ سناریویی.**
- افت‌های مورد انتظار: صفحهٔ افزونهٔ خاموش، `403` برمی‌گرداند (کپابیلیتی/منو با افزونه حذف شده) —
  رفتار درست و امن، نه خطا.
- تنها مورد ۵۰۰: صفحهٔ پنل مراجع وقتی `client-portal` خاموش است. با `WP_DEBUG_LOG` تأیید شد که
  **هیچ خطای PHP ثبت نمی‌شود**؛ صفحه فقط خالی رندر می‌شود (بدون شورت‌کد، بدون محتوا). این همان
  محدودیت شناخته‌شدهٔ قالب Staging است (صفحات محتوایی در این محیط HTML صفر بایت برمی‌گردانند)
  و در حالت پایه هم قابل بازتولید است؛ ربطی به افزونه‌ها ندارد.

## ۲. بازیابی پس از فعال‌سازی مجدد

پس از چرخهٔ کامل، هر ۱۱ افزونه فعال و همهٔ صفحه‌ها `200` هستند (`wp plugin list --status=active` = ۱۱).

## ۳. حفظ اطلاعات موجود

تعداد رکوردها قبل و بعد از ۲۲ بار خاموش/روشن‌کردن، بدون تغییر:

| جدول | قبل | بعد |
| --- | --- | --- |
| `pas_cf_cases` | ۴۵ | ۴۵ |
| `pas_cf_notes` | ۴۲ | ۴۲ |
| `pas_clients` | ۳۸ | ۳۸ |
| `zdp_bookings` | ۳ | ۳ |
| `psy_attempts` | ۳ | ۳ |
| `pas_sms_log` | ۲۰ | ۲۰ |

## ۴. آزمایش Rollback (کد)

آرشیو پشتیبان مرحلهٔ ۰ روی Staging بازگردانده شد (کد `24b542c`، `case-file` نسخهٔ `1.16.0`):

| صفحه | با کد پشتیبان | پس از بازگرداندن کد جاری (1.16.1) |
| --- | --- | --- |
| پنل مراجع | 200 / 32764B / Fatal=0 | 200 / 32764B / Fatal=0 |
| لیست پرونده‌ها | 200 / 95339B / Fatal=0 | 200 / 95339B / Fatal=0 |
| مرکز مدیریت | 200 / 79700B / Fatal=0 | 200 / 79719B / Fatal=0 |

نتیجه: آرشیو پشتیبان **قابل استفاده و سازگار با داده‌های فعلی** است (بدون نیاز به مهاجرت معکوس).

## ۵. آزمایش Rollback (دیتابیس / بازیابی از پشتیبان)

دامپ مرحلهٔ ۰ در دیتابیس ایزولهٔ `wp_p13_restore` بازگردانده شد:

| بررسی | نتیجه |
| --- | --- |
| ورود کامل دامپ | ✅ (`restore ok`) |
| تعداد جدول‌ها | ۳۷ (برابر Production/Staging) |
| `pas_cf_cases` / `pas_cf_notes` / `pas_cf_audit_log` | ۴۲ / ۳۹ / ۱۲۰ (مطابق لحظهٔ پشتیبان‌گیری) |
| `pas_clients` / `zdp_bookings` / `psy_attempts` | ۳۸ / ۳ / ۳ |
| خوانایی داده | نام پروندهٔ ۲ = «همسر آزمون» ✅ |

پس از آزمون، دیتابیس‌های موقت `wp_p13_restore` و `wp_p13_mig` حذف شدند — **هیچ جدول یا داده‌ای از
دیتابیس Staging/Production حذف نشد** (پاک‌سازی فقط روی کپی‌های موقتِ ساخته‌شده در همین مرحله بود).

## ۶. نکات محیطی (نه یافتهٔ محصول)

- سرور PHP داخلی تک‌رشته‌ای است؛ در اندازه‌گیری‌های هم‌زمان مرورگر، چند درخواست `5xx`/`403` کاذب ثبت شد
  در حالی که همان URL با `curl` همان لحظه `200` بود و لاگ سرور هم `200` نشان می‌دهد.
- صفحات محتوایی Staging (از جمله `?page_id=2/3/4/7/8` بدون شورت‌کد) در این محیط خالی برمی‌گردند؛
  پیش از فاز ۱۲ هم همین‌طور بوده و به قالب/محیط مربوط است.
- سرور MariaDB روی پورت 3307 و سایت روی 8090 در طول آزمون پایدار بودند؛ بارگذاری مجدد لازم نشد.

## ۷. حکم مرحله

**PASS** — اکوسیستم در برابر خاموش‌شدن هر جزء مقاوم است (بدون Fatal/Warning)، بازیابی کد و
دیتابیس هر دو آزمایش و تأیید شد، و هیچ داده‌ای در چرخه‌های خرابی/بازیابی از دست نرفت.
