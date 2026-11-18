# سیستم طراحی ۲.۰ — «Calm Intelligence»

این سند تغییرات فاز ۲ (Design System) و اعمال آن روی داشبورد یکپارچه را ثبت
می‌کند. هیچ جدول، هیچ قرارداد API و هیچ منطق تجاری تغییر نکرده است.

## تصمیم‌های تأییدشده

| تصمیم | انتخاب |
|---|---|
| فونت Display | **IBM Plex Sans Arabic** (وزن ۵۰۰/۶۰۰/۷۰۰، سلف‌هاست، OFL) |
| فونت متن | **Vazirmatn** (از قبل در پروژه سلف‌هاست بود — صفر بار دانلود جدید) |
| دامنهٔ اعمال | داشبورد یکپارچه (نقش درمانگر/مدیر) |
| منو | یک منوی واحد، گروه‌بندی‌شده بر اساس وظیفه |
| ماژول «مراجعان» | از `ACCESS_DASHBOARD` به `ACCESS_CLINICAL` منتقل شد |
| تم تاریک | در رابط داشبورد ارائه نمی‌شود؛ توکن‌هایش برای آینده نگه داشته شده |

## توکن‌ها

`payvand-amn-ui-kit/assets/css/tokens.css` بازنویسی شد؛ **نام هیچ متغیری تغییر
نکرد** و فقط مقادیر رنگ/سایه به پالت جدید منتقل شد، تا کامپوننت‌های موجود
(`components.css`, `shell.css`) بدون تغییر کار کنند.

| نقش | مقدار | کنتراست |
|---|---|---|
| پس‌زمینه | `#F4F1EC` | — |
| سطح | `#FCFBF8` | — |
| متن اصلی | `#292D2A` | ۱۲.۴۰ AAA |
| سبز برند | `#344A42` | ۸.۴۶ AAA |
| متن ثانویه | `#5C615D` | ۵.۶۱ AA |
| پیوند | `#2F5D4C` | ۷.۲۷ AAA |
| فوکوس | `#2F6B57` | ۵.۵۵ AA |
| مرز | `#DCD5C9` | ۱.۴۱ (فقط تزئینی) |
| موفق / هشدار / خطا / اطلاع | `#3F6B4F` / `#8A5A18` / `#9B3A2E` / `#4A6B7C` | ۵.۹۲ / ۵.۷۱ / ۶.۶۸ / ۵.۵۱ (AA) |

`#85847E` و `#8FAE9B` عمداً برای متن استفاده نمی‌شوند (AA نمی‌شوند).

## لایه‌های CSS

| فایل | نقش |
|---|---|
| `assets/fonts/plex-arabic/` | فونت Display سلف‌هاست + `plex-arabic.css` |
| `assets/css/tokens.css` | توکن‌های برند (بازنویسی‌شده) |
| `assets/css/calm.css` | لایهٔ افزودنی: تایپوگرافی، پنل نامتقارن، چیپ، فهرست اسکن، ورود نرم |
| `peyvand-amn-unified-dashboard/assets/css/shell-v2.css` | پوستهٔ جدید + بازرنگ‌آمیزی `.pas-*` داخل `.pvn-shell` |

قاعدهٔ ایزولاسیون: `shell-v2.css` فقط داخل `.pvn-shell` اثر دارد و بعد از
`dashboard-admin.css` بارگذاری می‌شود. هیچ استایل قدیمی حذف نشد؛ ۱۱ ماژول موجود
بدون تغییر مارک‌آپ، پالت و تایپوگرافی جدید را می‌گیرند.

## پوسته

- نوار بالا ۶۴px چسبان: برند، crumb ماژول، تاریخ شمسی، اعلان، کاربر.
- ناوبری ۲۴۸px با گروه‌بندی وظیفه‌محور: کار بالینی / پیگیری / مدیریت.
- نشانگر آیتم فعال: نوار ۳px در لبهٔ آغاز + پس‌زمینهٔ سبز کم‌رنگ.
- زیر ۸۶۰px: ناوبری off-canvas با scrim، بسته‌شدن با Esc و کلیک بیرون.
- زیر ۶۰۰px: جدول‌ها به کارت با `data-label` تبدیل می‌شوند.

## آیکون

`PAS_Dashboard_Module::svg()` — ۱۳ آیکون ۲۴×۲۴ درون‌خطی با `stroke: currentColor`.
بدون فونت آیکون عمومی و بدون درخواست شبکه.

## دسترسی

`overview` و `patients` به `ACCESS_CLINICAL` منتقل شدند، چون هر دو نام/شمار
مراجع را نشان می‌دهند. `can_access_dashboard()` دست‌نخورده است؛ فقط دامنهٔ
نمایش این دو ماژول محدودتر شد.

## فایل‌های تغییرکرده

```
plugins/payvand-amn-ui-kit/
  payvand-amn-ui-kit.php                  (نسخه ۲.۰.۰)
  includes/class-ui-kit.php               (ثبت pui-plex و pui-calm)
  assets/css/tokens.css                   (بازنویسی پالت)
  assets/css/calm.css                     (جدید)
  assets/fonts/plex-arabic/               (جدید: ۳ woff2 + css)
plugins/peyvand-amn-unified-dashboard/
  peyvand-amn-unified-dashboard.php       (نسخه ۲.۱.۰)
  includes/class-dashboard-assets.php     (enqueue کیت + shell-v2)
  includes/class-dashboard-permissions.php(overview/patients → clinical)
  includes/modules.php                    (سیستم آیکون SVG)
  includes/modules/overview-module.php    (بازطراحی چیدمان)
  templates/layout.php | header.php | sidebar.php
  assets/css/shell-v2.css                 (جدید)
  assets/js/dashboard-admin.js            (scrim/Esc/فوکوس پرش)
plugins/pivand-amn-client-portal/          (۱.۱۳.۲.۰)
  assets/css/panel-v2.css                 (جدید)
  includes/class-plugin.php               (enqueue بعد از skin)
plugins/pivand-amn-case-file/              (۱.۱۷.۰)
  assets/css/case-file-v2.css             (جدید)
  includes/class-admin.php                (enqueue بعد از skin)
```

## سه تجربهٔ هماهنگ

### ۱) داشبورد درمانگر — «میز کار»

پوستهٔ `.pvn-shell` با نوار بالا، ناوبری گروه‌بندی‌شده و شبکهٔ ۱۲ ستونی
نامتقارن. مدیر سایت همان پوسته را با کلاس `.pvn-shell--console` و تراکم
بیشتر می‌گیرد (عرض ۱۴۴۰px، پدینگ کمتر) — تفاوت فقط چیدمان است، نه مجوز.

### ۲) پنل مراجع — «آستانه»

`panel-v2.css` بعد از `panel-skin.css` و **فقط داخل `.zdp-panel-shell`**.
متغیرهای `--zdp-*` به پالت برند نگاشت شده‌اند، بنابراین تمام قواعد موجود پنل
رنگ جدید می‌گیرند بدون تغییر یک خط مارک‌آپ.

- کارت «نوبت بعدی» دو ستون و دو ردیف می‌گیرد → چیدمان نامتقارن واقعی.
- تب‌ها: نوار زیرین ۲px، بدون کارت و بدون سایه.
- زیر ۷۲۰px: یک ستون، برچسب خروج پنهان، تب‌های قابل اسکرول افقی.

### ۳) پروندهٔ بالینی

`case-file-v2.css` **فقط داخل `.pas-cf-wrap`**: تیتر Display، جدول‌های
`widefat` با هدر کم‌رنگ و سطرهای ۴۴px، اعلان‌های نواری، دکمه‌های
`button`/`button-primary` با پالت برند. زیر ۷۸۲px جدول عریض داخل خودش
اسکرول می‌شود تا صفحه سرریز افقی نگیرد.

## بررسی انجام‌شده

- `php -l` روی ۱۱ فایل تغییرکرده با PHP 8.4.23: بدون خطا.
- باز بودن براکت‌های سه فایل CSS: برابر.
- هیچ‌کدام از فونت‌های ممنوعه (Inter/Roboto/Arial/Open Sans/Space Grotesk) در
  CSS جدید نیست.
- پیش‌نمایش واقعی در مرورگر (Chromium، ۱۰۲۴×۱۶۸۰): پوسته، فونت Display،
  جدول، بج‌ها و بلوک‌های نامتقارن رندر شدند.
- ۳۹۰px: `scrollWidth == clientWidth` (سرریز افقی صفر)، ناوبری off-canvas،
  جدول به کارت تبدیل شد.

## بررسی‌نشده (صادقانه)

- هیچ WordPress/دیتابیسی در این محیط اجرا نشد؛ سوئیت‌های `tests/wp/*` اجرا
  نشدند و رفتار واقعی با دادهٔ زنده تأیید نشده است.
- رندر داخل پیشخوان وردپرس (با نوار مدیریت، RTL ادمین، و تداخل با
  `.wrap`) در مرورگر دیده نشده؛ فقط هارنس مستقل.
- آزمون صفحه‌خوان انجام نشده.

## پیش‌نمایش

`artifacts/design-preview/` — سه هارنس مستقل با همان CSS و همان مارک‌آپ:
`index.html` (داشبورد)، `portal.html` (پنل مراجع)، `case-file.html`
(پروندهٔ بالینی). برای مشاهده: `python3 -m http.server` در همان پوشه.
