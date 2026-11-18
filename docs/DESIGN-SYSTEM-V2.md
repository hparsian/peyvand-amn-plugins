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

## حالت تاریک

- کلید تغییر رنگ در `templates/header.php` (`.pvn-theme-toggle`) با دو آیکون
  خورشید/ماه و `aria-pressed`.
- منبع حقیقت، `data-pui-theme` روی `<html>` است و انتخاب در
  `localStorage['pui-theme']` می‌ماند — همان کلیدی که کیت رابط (`ui.js`)
  استفاده می‌کند.
- کیت رابط `ui.js` خودش روی `data-pui-theme-choice` / `localStorage` مقدار
  می‌نشاند. برای اینکه دو سیستم تم همدیگر را خنثی نکنند، دکمه
  `data-pui-theme-toggle` دارد و وقتی `window.PUI` موجود است، مدیریت کلیک
  ۱۰۰٪ به کیت می‌رسد؛ كد داشبورد فقط در حالت تنزل (کیت غیرفعال) کار می‌کند.
- حالت اولیه **از تم سرور** می‌آید (`PUI::theme_attr()` از آپشن
  `pui_default_theme`). هم `aria-pressed` دکمه و هم `data-pui-theme-default`
  سمت سرور رندر می‌شوند؛ اسکریپت زودهنگام هم همان مقدار را روی
  `data-pui-theme` و `data-pui-theme-choice` می‌نشاند، پس `ui.js` در ادامه
  آن را بازنویسی نمی‌کند.
  نتیجه‌ی آزمون: سرور `dark` + حافظه‌ی خالی ← صفحه تاریک با `aria-pressed="true"`
  پیش از هر کلیک؛ سرور `light` ← روشن با `aria-pressed="false"`.
- `PAS_Dashboard_Assets::early_theme()` در `admin_head` اسکریپت کوچکی چاپ
  می‌کند تا attribute پیش از رندر ست شود؛ بدون آن، صفحه با حالت روشن نقاشی
  می‌شد و بعد می‌پرید (FOUC). مقدار پیش‌فرض از `PUI::theme_attr()` می‌آید و
  `auto` به `prefers-color-scheme` نگاشت می‌شود.
- نگاشت تیرهٔ `--pas-*` در `shell-v2.css` تا ماژول‌های قدیمی هم بدون تغییر
  مارک‌آپ تیره شوند.
- پس‌زمینهٔ خود پیشخوان (`#wpcontent`/`#wpbody-content`/`#wpfooter`) با
  `:has(.pvn-shell)` تیره می‌شود؛ یعنی فقط روی صفحه‌ی داشبورد و نه باقی
  پیشخوان.

## صفحهٔ تک‌پرونده تَب‌دار

- دو تَب: «خلاصه» (مشخصات + نمای جلسه‌های زوجی) و «یادداشت‌های جلسه»
  (فهرست + فرم افزودن).
- `role="tablist"`/`role="tab"`/`role="tabpanel"`، `aria-selected`،
  `aria-controls` و `tabindex` مدیریت‌شده در JS؛ کلیدهای ArrowLeft/ArrowRight
  بین تَب‌ها می‌چرخند. تَب فعال در `location.hash` می‌ماند و پس از reload
  برمی‌گردد.
- هر دو پنل در همان یک درخواست رندر می‌شوند. تَب فقط نمایش را عوض می‌کند؛
  مجوز همچنان در سرور با `PAS_Dashboard_Security::authorized_case()` و
  `notes_writable()` تصمیم گرفته می‌شود. چیزی از سمت کلاینت مجاز نمی‌شود.
- مرز «یادداشت خصوصی»: یادداشت‌ها در تَب جدا هستند، نه مخلوط با مشخصات
  پرونده.

## بررسی انجام‌شده (staging واقعی وردپرس)

محیط: WordPress 7.1.4 + SQLite drop-in در `/tmp/wpstage`، هر چهار افزونه
فعال، کاربر `dr.sara` با نقش `pas_cf_therapist`، ۵ پرونده و ۴ یادداشت.

- **یک خطای مرگ‌آور واقعی پیدا و رفع شد**: `templates/layout.php` متد
  `PAS_Dashboard_Permissions::can_manage()` را صدا می‌زد که وجود ندارد؛ صفحهٔ
  پرونده در staging با `Fatal error` سفید می‌شد. اکنون
  `PAS_Dashboard_Security::can_manage()` است.
- **سرریز افقی**: در پیشخوان RTL، مارجین منفی پوسته ۲۰px سرریز می‌ساخت و در
  ۳۷۵px هم ۱۰px می‌ماند. هر دو با اندازه‌گیری `scrollWidth - clientWidth` به
  صفر رسیدند (دسکتاپ ۱۴۴۰ و موبایل ۳۷۵).
- **اسکرول**: در موبایل اسکرول عمودی کار می‌کند (`scrollY = 900` پس از wheel)
  و نوار بالا با ارتفاع واقعی نوار مدیریت (۴۶px زیر ۷۸۲px) می‌چسبد.
- **تب‌ها**: با `#notes` فقط پنل یادداشت‌ها باز است (`summary hidden=true`) و
  تَب فعال درست است؛ برعکس هم بررسی شد.
- **حالت تاریک**: `data-pui-theme=dark`، `data-pui-theme-choice=dark`،
  `localStorage['pui-theme']=dark`، `shell #171B18`، `card #1E231F`، متن
  `#F1EDE5`، و پس از reload پایدار. یک کلیک دقیقاً یک بار تغییر می‌دهد
  (بدون خنثی‌شدن دو هندلر).
- **هم‌گامی با تم سرور**: با `wp option update pui_default_theme dark` و
  حافظه‌ی خالی مرورگر، صفحه بدون هیچ کلیکی تاریک بالا آمد و
  `aria-pressed="true"` بود؛ با `light` برعکس. `PasDashboard.theme` از
  localization حذف شد (مقدار بی‌استفاده بود) و بررسی شد که دیگر کلید
  `theme` در آبجکت کلاینت وجود ندارد.
- **ثبت و حذف واقعی یادداشت** (AJAX + nonce، روی همان staging):
  یادداشت با شماره‌ی جلسه ۷ برای پرونده #1 ثبت شد؛ رکورد با شناسه ۵
  ساخته شد، محتوا هنگام بازخوانی از رمزنگاری درست بازگشت و در بالای
  فهرست قرار گرفت. سپس همان رکورد از دکمه‌ی حذف پاک شد و پس از reload
  دیگر در فهرست نبود (`count: 2 → 1`). آزمون دوم روی پرونده #2: ثبت
  رکورد ۶، حذف با تأیید دیالوگ، و `waitForNavigation` نشان داد خودکار
  reload می‌شود؛ پس از آن ردیف حذف‌شده حاضر نبود.
- `php -l` روی فایل‌های تغییرکرده با PHP 8.4.23: بدون خطا؛
  `node --check` روی JS: سالم؛ تراز `{`/`}` در `shell-v2.css` برابر (۱۶۳/۱۶۳).
- فونت محاسبه‌شده در heading: `IBM Plex Sans Arabic`.

## بررسی‌نشده (صادقانه)

- بازگردانی نسخه‌های قدیمی یادداشت (تاریخچه) در staging آزموده نشد؛
  ثبت و حذف آزموده شد.
- پنل مراجع و پروندهٔ بالینی فقط با هارنس مستقل دیده شده‌اند، نه داخل
  پیشخوان وردپرس با دادهٔ زنده.
- صفحه‌خان (screen reader)، Firefox/Safari، حالت چاپ و چیدمان راست‌به‌چپ
  با زبان غیرفارسی آزموده نشد.
- سوئیت‌های `tests/wp/*` اجرا نشدند.

## پیش‌نمایش و شواهد

- `artifacts/design-preview/` — سه هارنس مستقل: `index.html` (داشبورد)،
  `portal.html` (پنل مراجع)، `case-file.html` (پروندهٔ بالینی).
- اسکرین‌شات‌های staging واقعی: `staging-dashboard.png` (روشن)،
  `staging-dark.png` (تاریک)، `staging-case-tabs.png` و
  `staging-case-notes.png` (تک‌پرونده با تب‌ها)، `staging-mobile.png`.
- برای اجرای مجدد staging: PHP در `/tmp/tools/php`، سایت در `/tmp/wpstage`،
  `php -S 0.0.0.0:3001 -t /tmp/wpstage` با `wp-config.php` که `WP_HOME` را از
  `HTTP_HOST` می‌سازد (تا هم پروب ۱۲۷.۰.۰.۱ و هم دامنهٔ پیش‌نمایش بدون
  ریدایرکت کار کند).
