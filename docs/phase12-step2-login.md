# فاز ۱۲ — گام ۲: بازطراحی صفحهٔ ورود مراجع

تاریخ: ۱۴۰۵/۰۸/۲۲ · نسخهٔ پنل مراجع: `1.12.0.0` · وضعیت: **آماده برای بازبینی**

مرتبط: [کیت رابط کاربری (گام ۱)](phase12-step1-ui-kit.md) · [ممیزی](phase12-ui-audit.md) · [طرح](phase12-design-proposal.md)

---

## ۱. چه چیزی تغییر کرد

صفحهٔ ورود (`[zdp_client_panel]`) از یک کارت تک‌ستونی آبی به یک صفحهٔ
دوستونی روی کیت برند تبدیل شد — **بدون هیچ تغییری در منطق ورود**:

| لایه | تغییر |
| --- | --- |
| `assets/css/login.css` (جدید) | تمام استایل‌های صفحهٔ ورود، محدود به `.zdp-login-stage`؛ فقط توکن‌های `--pui-*` (با fallback کامل) |
| `includes/class-client-panel.php` | بازنویسی فقط markup فرم ورود: ستون برند + کارت فرم، مرحله‌ها با برچسب، سه آیکون تازه (`link`, `shield`, `user`)؛ همهٔ `#zdp-*` و کلاس‌های مورد نیاز `panel.js` حفظ شد |
| `includes/class-plugin.php` | ثبت/بارگذاری شرطی `zdp-login` + `PUI::enqueue('auth')` فقط روی صفحهٔ پنل مراجع |
| `pivand-amn-client-portal.php` / `CHANGELOG.md` | نسخه `1.12.0.0` |

**قراردادهای حفظ‌شده (بررسی‌شده):** `#zdp-mobile`, `#zdp-email`, `#zdp-otp`,
`#zdp-otp-box-0..4` (با `data-otp-index`), `#zdp-mobile-step`, `#zdp-email-step`,
`#zdp-otp-step`, `#zdp-send-otp-btn`, `#zdp-send-otp-email-btn`,
`#zdp-verify-otp-btn`, `#zdp-resend-otp-btn`, `#zdp-change-mobile-btn`,
`#zdp-change-label`, `#zdp-otp-timer`, `#zdp-otp-support`, `#zdp-login-message`,
`#zdp-channel-mobile-btn`, `#zdp-channel-email-btn`, `.zdp-login-channel-tabs`,
`.zdp-login-channel-tab.active`, `.zdp-login-steps[data-step]`, `.zdp-btn-label`,
`.zdp-btn-spinner`, `.zdp-error`.

**امنیت:** فایل `class-otp-auth.php`، مسیرهای REST (`zdp/v1/send-otp`,
`zdp/v1/verify-otp`)، transient `zdp_otp_<mobile>`، TTL ۵ دقیقه، قفل پس از ۵
تلاش و محدودکنندهٔ ۶۰ ثانیه **هیچ تغییری نکرده‌اند**.

## ۲. تصاویر قبل / بعد

| قبل | بعد |
| --- | --- |
| `docs/evidence/phase12/step2/before-login-desktop.png` | `after-login-desktop.png` |
| `before-login-mobile.png` | `after-login-mobile.png` |
| — | `after-login-email-tab.png` (تب ایمیل) |
| — | `after-login-otp-step.png` (خطای واقعی سرویس پیامک در Staging) |
| — | `otp-step-{desktop,mobile}-simulated.png` (نمای مرحلهٔ کد، شبیه‌سازی فقط بصری) |

> نکتهٔ صداقتی: در تصاویر «قبل» فونت فارسی از فونت سیستم خوانده می‌شود
> (سایت هیچ فونت سلف‌هاستی برای این صفحه نداشت)؛ در «بعد» فونت Vazirmatn از
> خود کیت بارگذاری می‌شود. مرحلهٔ «کد تایید» در Staging با ارسال واقعی پیامک
> قابل تولید نبود (سرویس پیامک تنظیم نشده)، بنابراین نمای آن با تغییر موقت
> `display` در مرورگر و بدون دست‌زدن به کد ثبت شد؛ حالت خطای واقعی در
> `after-login-otp-step.png` دیده می‌شود.

## ۳. تست‌ها (Staging محلی، WordPress + MySQL)

| آزمون | نتیجه |
| --- | --- |
| `php -l` روی سه فایل تغییرکردهٔ PHP | ✅ بدون خطا |
| `GET /?page_id=4` (صفحهٔ پنل) | ✅ ۲۰۰؛ `login.css` و `pui-tokens` فقط در همین صفحه |
| صفحهٔ غیر پنل (نمونه) | ✅ بدون `login.css` و بدون دارایی‌های کیت |
| Playwright (Firefox) دسکتاپ ۱۴۴۰ و موبایل ۳۹۰ | ✅ `errors=0`، `scrollWidth == viewport` (بدون سرریز افقی) |
| فونت اعمال‌شده | ✅ `Vazirmatn, Tahoma, Arial, sans-serif` |
| جابه‌جایی تب موبایل/ایمیل | ✅ مرحلهٔ درست نمایش، `aria-selected` و `.active` درست |
| ارسال OTP با شمارهٔ تستی | ✅ درخواست به REST رفت؛ پیام خطای واقعی («خطا در ارتباط با سامانه پیامک…») با استایل خطا در کارت نمایش داده شد |
| دسترس‌پذیری | ✅ ۷ از ۷ ورودی برچسب دارند؛ `role=status aria-live=polite`؛ تب‌ها `role=tab`; `dir=rtl lang=fa-IR`; حلقهٔ فوکوس با صفحه‌کلید دیده می‌شود (`outline 2px`, `:focus-visible` = true) |
| رگرسیون فاز ۱۱ — `encryption.php` | ✅ `PASS=9 FAIL=0` |
| رگرسیون فاز ۱۱ — `idor-roles.php` | ✅ `PASS=19 FAIL=0` (۱ مورد `ACCEPTED` مستند قبلی) |
| `debug.log` | ✅ بدون Notice/Warning/Fatal |

## ۴. مشکلات باقی‌مانده

1. **آزمون OTP سرتاسری** (دریافت پیامک → تایید → ورود) در Staging ممکن نیست
   چون سرویس پیامک تنظیم نشده؛ مسیر فقط تا «درخواست ارسال» و «نمایش خطا» تست شد.
   پیشنهاد: یک‌بار با درگاه پیامک Staging در گام ۸ تکرار شود.
2. **پنل پس از ورود** عمداً دست‌نخورده مانده (گام ۴). کیت روی همان صفحه هم
   بارگذاری می‌شود ولی فقط توکن/فونت اضافه می‌کند و هیچ استایل `.pui-*` روی
   کلاس‌های `zdp-*` اثر ندارد؛ تصویر پنل در گام ۴ «قبل» ثبت می‌شود.
3. بخشی از استایل‌های قدیمی `panel.css` (قواعد `!important` برای دکمه‌ها) هنوز
   وجود دارد؛ در `login.css` با همان سطح انتخابگر خنثی شده و در گام ۴ هنگام
   بازنویسی پنل حذف/جایگزین می‌شود. حذفشان الان ریسک تغییر ظاهر پنل فعلی بود.
4. `migration-verify.php` همچنان روی نصب دوم (`$HOME/phase11m`) اجرا نشده است
   (از گام ۱ باقی‌مانده؛ مربوط به این گام نیست).

## ۵. گام بعد

گام ۳/۴ — **پنل مراجع** روی همین کیت: داشبورد، نوبت بعدی، سوابق، پرونده،
آزمون‌ها، پرداخت، پیام‌ها، رضایت‌نامه‌ها، تنظیمات — با حفظ همهٔ `#zdp-*` و
`panel.js` و همان کنترل دسترسی فعلی.
