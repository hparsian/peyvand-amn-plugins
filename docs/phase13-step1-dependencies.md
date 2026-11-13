# فاز ۱۳ — مرحلهٔ ۱: ممیزی وابستگی‌ها

تاریخ: ۱۴۰۵/۰۸/۲۲ · وضعیت: **PASS با ۲ یافتهٔ Medium** · بر پایهٔ `24b542c`

## ۱. نقشهٔ افزونه‌ها (خروجی واقعی `get_plugins()` + `active_plugins`)

| افزونه | نسخه | فعال | `Requires Plugins` | Requires PHP |
| --- | --- | --- | --- | --- |
| payvand-amn-ui-kit | 1.0.0 | ✅ | — | 7.4 |
| payvand-contact | 1.4.3 | ✅ | — | 7.4 |
| pivand-amn-2fa | 1.3.5 | ✅ | — | 8.1 |
| pivand-amn-case-file | 1.16.0 | ✅ | — | 8.1 |
| pivand-amn-client-portal | 1.13.0.0 | ✅ | — | 8.1 |
| pivand-amn-content-writer | 2.9.18 | ✅ | — | 7.4 |
| pivand-amn-core | 1.8.1 | ✅ | — | 8.1 |
| pivand-amn-quiz-enterprise | 3.7.1 | ✅ | — | 8.1 |
| pivand-amn-sms-bridge | 1.1.7 | ✅ | — | 8.1 |
| pivand-amn-smtp-mailer | 1.5.4 | ✅ | — | 8.1 |
| piwand-daily-feed | 1.1.6 | ✅ | — | 7.4 |

## ۲. گراف وابستگی اعلام‌شده (رجیستری خودِ هسته)

```
client-portal ──┬──> sms-bridge
                └──> quiz-enterprise
case-file     ──────> client-portal   (اختیاری: اتصال حساب OTP و نوبت‌ها)
core          ──────> (هیچ)            ← ریشه
ui-kit        ──────> (هیچ)            ← ریشه (فقط در preview به core نگاه می‌کند، با class_exists)
```

- **حلقهٔ وابستگی: هیچ.** گراف درخت است (دو ریشه: `core`, `ui-kit`).
- ارجاع‌های «معکوس» (هسته به بقیه) فقط در سه جای مستند/گاردشده وجود دارد:
  `class-compliance.php` (`class_exists('Pas_Melipayamak')` + `method_exists`)،
  `class-health.php` و `class-prefix-map.php` (فقط کاتالوگ/مستندسازی)، و
  `class-tokens.php`/`class-fonts.php` که خدمت‌رسان‌اند نه وابسته.
- `ui-kit` در `class-preview-page.php` با `class_exists('Pas_Core_Health')` و
  `class_exists('Pas_Core_Crypto')` گارد شده است.

## ۳. توابع/کلاس‌های مشترک

| قرارداد | ارائه‌دهنده | مصرف‌کننده‌ها |
| --- | --- | --- |
| `PUI::enqueue()/handle()/icon()/theme_attr()` | ui-kit | client-portal، case-file، quiz، core، sms، smtp، 2fa، contact، feed |
| `Pas_Core_Tokens::handle()` | core | ۹ افزونه |
| `Pas_Core_Fonts::handle()` | core | contact، feed، case-file (وقتی کیت نیست)، 2fa/sms/smtp (fallback) |
| `Pas_Core_Crypto` / `Pas_Core_Data_Audit` | core | case-file، content-writer، ui-kit (preview) |
| `Pas_Core_Health` / `Pas_Core_Compliance` | core | ui-kit preview، مرکز مدیریت |

## ۴. رفتار هنگام غیرفعال شدن (آزمون واقعی مرورگری)

هر افزونه غیرفعال، پنج صفحه بازدید، سپس دوباره فعال شد. معیار: HTTP،
`Fatal error`/«critical error»، و وجود `Warning/Notice/Deprecated` در متن صفحه.

| سناریو | panel | dashboard | center | quiz | sms | حکم |
| --- | --- | --- | --- | --- | --- | --- |
| UI Kit **خاموش** | 200 | 200 | 200 | 200 | 200 | ✅ بدون Fatal/Warning |
| UI Kit روشن (بازگشت) | 200 | 200 | 200 | 200 | 200 | ✅ |
| Core **خاموش** | 200 | 200 | **403** | 200 | 200 | ✅ بدون Fatal؛ مرکز مدیریت با 403 تمیز رد می‌شود چون کپابیلیتی/صفحهٔ آن متعلق به هسته است |
| Core روشن (بازگشت) | 200 | 200 | 200 | 200 | 200 | ✅ |

نتیجه: هیچ افزونه‌ای با خاموش شدن `core` یا `ui-kit` به Fatal Error نمی‌رسد؛
همهٔ مسیرها گارد `class_exists`/`function_exists` دارند (۵ تا ۲۶ گارد در هر افزونه).

## ۵. بارگذاری دوبارهٔ CSS/JS

با `do_action('wp_enqueue_scripts')` و `do_action('admin_enqueue_scripts')` و
بررسی `WP_Styles`/`WP_Scripts`:

- `styles=222`, `scripts=226` ثبت‌شده.
- **گروه‌های تکراری بر اساس `src`: صفر** (`duplicate_src_groups=0`).
- هندل‌های خانواده، یکتا و با dependency درست:
  `pui-fonts → pui-tokens → pui-components → pui-shell`,
  `zdp-panel → {pas-core-tokens, pui-*} → zdp-panel-skin`, `zdp-login`, `pui-ui`, `zdp-panel` (js).
- `PUI::register_all()` با `wp_style_is(..., 'registered')` idempotent است؛ صدا زدن چندباره از چند افزونه فقط یک‌بار ثبت می‌کند.

## ۶. یافته‌ها

| شناسه | یافته | شدت | جزئیات | پیشنهاد |
| --- | --- | --- | --- | --- |
| DEP-1 | هیچ افزونه‌ای هدر `Requires Plugins:` ندارد | **Medium** | وردپرس ۶.۵+ می‌تواند وابستگی را در سطح نصب اعمال کند؛ الان فقط رجیستری داخلی هسته بررسی می‌کند و اگر کاربر `core` را پاک کند، افزونه‌ها فعال می‌مانند (بدون Fatal، ولی بدون رمزنگاری/توکن). | افزودن `Requires Plugins: pivand-amn-core` به ۸ افزونهٔ وابسته و `payvand-amn-ui-kit` به مصرف‌کننده‌های کیت |
| DEP-2 | رجیستری وابستگی ناقص است | **Low** | فقط ۴ افزونه در `Pas_Core_Dependency_Checker` ثبت شده‌اند؛ core/ui-kit/smtp/2fa/contact/feed/content-writer ثبت نشده‌اند. | تکمیل رجیستری برای یک نمای کامل در مرکز مدیریت |
| DEP-3 | افزونه‌های بدون وابستگی اعلام‌شده به core | Low | `2fa`، `sms`، `smtp` عملاً برای توکن/فونت به core تکیه دارند (با fallback)؛ رفتار بدون core سالم است. | همان DEP-1 |

## ۷. شواهد قابل بازتولید

```bash
# ماتریس نسخه/فعال‌بودن
wp eval-file /tmp/dep-matrix.php           # خروجی در بخش ۱ و ۲

# هندل‌های دارایی و تکراری‌ها
wp eval-file /tmp/assets-audit.php         # خروجی در بخش ۵

# آزمون غیرفعال‌سازی (Staging، سپس فعال‌سازی مجدد)
node $HOME/pwtool/isolation.mjs            # خروجی در بخش ۴
```

## ۸. حکم مرحله

**PASS** — گراف بدون حلقه، بدون بارگذاری دوباره، و با تخریب‌پذیری کنترل‌شده
(graceful degradation). دو یافتهٔ ثبت‌شده (DEP-1 و DEP-2) در مرحلهٔ ۵/۶ برای
اصلاح یا پذیرش تصمیم‌گیری می‌شوند؛ هیچ‌کدام مانع ادامهٔ ممیزی نیست.
