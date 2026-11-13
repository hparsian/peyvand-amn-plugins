# فاز ۱۲ — گام ۱: کیت رابط کاربری (`payvand-amn-ui-kit`)

تاریخ: ۱۴۰۵/۰۸/۲۲ · وضعیت: **آماده برای بازبینی** · Commit: گام ۱ (پایین همین سند)

پیش‌نیازهای این گام (کامیت‌شده در `4621ede`):
[`docs/phase12-ui-audit.md`](phase12-ui-audit.md) و
[`docs/phase12-design-proposal.md`](phase12-design-proposal.md).

---

## ۱. چه چیزی ساخته شد

یک افزونهٔ مستقل و بدون وابستگی که تنها منبع حقیقت برای رنگ، فونت، فاصله،
آیکون و کامپوننت‌های چهار محیط است. **هیچ CSS/JS سراسری enqueue نمی‌شود**؛
هر افزونهٔ مصرف‌کننده باید صریحاً `PUI::enqueue('auth' | 'panel' | 'admin')`
یا `PUI::enqueue_shell('auth' | 'panel')` را صدا بزند.

| فایل | نقش |
| --- | --- |
| `payvand-amn-ui-kit.php` | هدر، ثابت‌ها (`PUI_VERSION`, `PUI_DIR`, `PUI_URL`)، بارگذاری، `PUI_Preview_Page::boot()` |
| `includes/class-ui-kit.php` | `PUI::enqueue()`, `enqueue_shell()`, `handle()`, `theme_attr()` — ثبت هندل‌ها بدون بارگذاری |
| `includes/class-icons.php` | ۳۶ آیکون inline SVG (بدون CDN، بدون فونت آیکون، `aria-hidden` پیش‌فرض) |
| `includes/components.php` | `pui_icon, pui_card_open/close, pui_stat, pui_badge, pui_alert, pui_field, pui_table_open/close, pui_steps, pui_empty, pui_tabs…` |
| `includes/class-preview-page.php` | صفحهٔ «ابزارها → کیت رابط کاربری»: توکن‌ها، همهٔ کامپوننت‌ها و ۴ طرح اولیه |
| `assets/css/tokens.css` | پالت برند + نقش‌های معنایی، Light/Dark، تایپ، فاصله، شعاع، سایه، حرکت |
| `assets/css/components.css` | کامپوننت‌ها در `@layer`، همه با پیشوند `pui-` و Logical Properties |
| `assets/css/shell.css` | پوستهٔ ورود، پوستهٔ پنل (سایدبار دسکتاپ / نوار پایین موبایل)، پیشخوان، تقویم، نمودار میله‌ای |
| `assets/js/ui.js` | حالت رنگ (light/dark/auto + localStorage)، تب ARIA، مدال `<dialog>`، توست، busy — بدون jQuery |
| `assets/fonts/vazirmatn/` | ۴ وزن woff2 + `OFL.txt` (سلف‌هاست؛ Google Fonts حذف شد) |

## ۲. تصمیم‌های قابل دفاع

- **پالت:** `#F4F0E8` زمینه، `#51483F` متن اصلی (کنتراست ۷٫۸۷ — AAA)،
  `#C8B48A` فقط تأکید، `#B8ADA0` فقط مرز. سفید روی طلا (۲٫۰۳) و طلا روی کرم
  ممنوع اعلام شد.
- **حالت تاریک: opt-in.** پیش‌فرض روشن است؛ انتخاب کاربر در `localStorage`
  ذخیره و پیش از رندر (بدون پرش رنگ) اعمال می‌شود (`early_theme`).
- **بدون Bootstrap/Tabler:** فقط الگوهای Tabler بازتولید شد تا با Astra و
  Elementor تداخل و حجم اضافه ایجاد نشود.
- **بدون ApexCharts در این گام:** طبق نقشهٔ گام‌ها، نمودار واقعی در گام ۵؛
  در طرح مرکز مدیریت فقط نمودار میله‌ای CSS با برچسب «نمونه» است تا هیچ دادهٔ
  نمایشی به‌عنوان وضعیت واقعی نشان داده نشود.

## ۳. فایل‌های تغییرکرده

```
new  plugins/payvand-amn-ui-kit/**              (افزونهٔ کامل، ۳۸۰KB شامل ۴ فونت)
new  docs/phase12-step1-ui-kit.md               (همین سند)
new  docs/evidence/phase12/step1/*.png          (۹ تصویر، ۲٫۶MB)
```

هیچ فایلی از افزونه‌های موجود تغییر نکرد؛ هیچ جدول/ستون/قرارداد API لمس نشد.

## ۴. تست‌ها (اجرا شده در Staging محلی، `$HOME/phase11`)

| آزمون | نتیجه |
| --- | --- |
| `php -l` روی همهٔ فایل‌های PHP افزونه | ✅ بدون خطا |
| فعال‌سازی افزونه با WP-CLI (`plugin activate`) | ✅ `active, 1.0.0` |
| `GET /wp-admin/tools.php?page=pui-kit` با کوکی مدیر | ✅ ۲۰۰، ۱۳۳KB، بدون Fatal/Warning |
| پاسخ دارایی‌ها (۳ CSS، ۱ JS، CSS فونت، یک woff2) | ✅ همه ۲۰۰ |
| Playwright — دسکتاپ ۱۴۴۰ / موبایل ۳۹۰ × light/dark | ✅ ۴ بار ۲۰۰، `errors=0`، بدون سرریز افقی (`scrollW == viewport`) |
| فونت واقعی اعمال‌شده | ✅ `font-family: Vazirmatn, …` |
| تعامل: تغییر حالت رنگ → light→dark | ✅ |
| تعامل: تب ARIA (`aria-selected`, پنل نمایش) | ✅ |
| تعامل: مدال `<dialog>` باز/بسته با Esc | ✅ |
| تعامل: توست | ✅ یک نود `.pui-toast` |
| رگرسیون فاز ۱۱ — `encryption.php` | ✅ `PASS=9 FAIL=0` |
| رگرسیون فاز ۱۱ — `idor-roles.php` | ✅ `PASS=19 FAIL=0`، یک `ACCEPTED` (رفتار مستند قبلی) |
| `migration-verify.php` | ⚠️ در این نصب اجرا نشد؛ مخصوص نصب دوم (`$HOME/phase11m`) است و با نصب اول نتیجهٔ FAIL می‌دهد. باید در گام بعد روی نصب دوم اجرا شود. |

### تصاویر

| فایل | توضیح |
| --- | --- |
| `preview-desktop-light.png` | کل صفحهٔ پیش‌نمایش، روشن، ۱۴۴۰px |
| `preview-desktop-dark.png` | همان، تاریک |
| `auth-light.png` / `auth-dark.png` | طرح ورود |
| `panel-light.png` / `panel-dark.png` | طرح پنل مراجع |
| `panel-mobile.png` / `auth-mobile.png` | نمای موبایل ۳۹۰px |
| `preview-mobile-top.png` | بالای صفحه در موبایل |
| `preview-desktop-light-tabs.png` | پس از تعامل (تب/مدال) |

«قبل» برای این گام وجود ندارد چون صفحهٔ پیش‌نمایش تازه ساخته شده است؛
تصاویر «قبل» هر محیط در گام مربوط به همان محیط (۲ تا ۵) ثبت می‌شود.

## ۵. مشکلات باقی‌مانده و محدودیت‌ها

1. **`<dialog>` + `backdrop-filter`:** در Chromium headless همین محیط، رندر
   هر کنترل ورودی متنی (`input`, `textarea`) پروسه را crash می‌کند (حتی در یک
   صفحهٔ خالی بدون CSS ما). به همین دلیل اسکرین‌شات‌ها با **Firefox** گرفته شد؛
   تست بصری کروم در گام ۸ روی محیط CI/مرورگر واقعی تکرار می‌شود.
2. فونت‌های `piwand-daily-feed` و `payvand-contact` هنوز از Google Fonts
   می‌آیند؛ در گام ۲/۳ به `vazirmatn.css` همین کیت منتقل می‌شوند (تا امروز هیچ
   فایلی از آن افزونه‌ها تغییر نکرده).
3. `tokens.css` قدیمی هسته (`#2563eb`) هنوز فعال است؛ نگاشت آن به `--pui-*`
   در گام‌های محیط‌ها انجام می‌شود، نه در این گام.
4. بررسی دسترس‌پذیری در این گام محدود به ساختار ARIA، `:focus-visible`،
   `prefers-reduced-motion` و کنتراست محاسبه‌شده بود؛ ممیزی کامل WCAG با
   ابزار (axe) در گام ۸.
5. صفحهٔ پیش‌نمایش فقط برای مدیر (`manage_options`) باز می‌شود و هیچ دادهٔ
   واقعی مراجع نمایش نمی‌دهد (فقط شمارش `safe_count` برای جداول هسته).

## ۶. گام بعد

گام ۲ — **صفحهٔ ورود مراجع** روی همین کیت: حفظ کامل `#zdp-*` ها و `panel.js`،
حفظ REST `zdp/v1/send-otp|verify-otp` و transient/TTL/قفل تلاش، افزودن
شمارش معکوس، پیام خطای قابل فهم، حالت‌های loading/error، و تصاویر قبل/بعد.
