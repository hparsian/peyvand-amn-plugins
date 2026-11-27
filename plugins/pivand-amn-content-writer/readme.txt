=== پیوند امن — تولید محتوا با هوش مصنوعی ===
Contributors: hadiparsaeian
Tags: ai content, openrouter, deepseek, seo, security, encryption
Requires at least: 6.6
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 2.9.17
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

سیستم تولید محتوای هوشمند با هوش مصنوعی، شامل تحلیل بلادرنگ سئو.

== Description ==
سیستم تولید محتوای هوشمند با هوش مصنوعی — پشتیبانی از OpenRouter و DeepSeek. شامل تحلیل بلادرنگ سئو (ادغام‌شده از Peyvand Amn SEO Assistant Pro).

از نسخه‌ی 2.3.1 به بعد، کلید(های) API هوش مصنوعی به‌صورت رمزنگاری‌شده در تنظیمات ذخیره می‌شوند.

== Requirements ==
* وابسته به افزونه‌ی «پیوند امن — هسته مشترک» برای کلاس `Pas_Core_Crypto`.
* حداقل PHP 7.4.

== Changelog ==

= 2.3.1 =
* امنیت: کلید(های) API هوش مصنوعی در تنظیمات اکنون رمزنگاری‌شده ذخیره می‌شوند.
* سازگاری با نسخه‌های قبلی: مقادیر قدیمی plaintext به‌صورت شفاف (unwrap_decrypt) خوانده می‌شوند و با اولین ذخیره‌ی مجدد رمزنگاری خواهند شد.

= 2.3.0 =
* نسخه‌ی پیشین (بدون رمزنگاری کلید API).

== Upgrade Notice ==

= 2.3.1 =
پس از آپدیت، توصیه می‌شود در صفحه‌ی تنظیمات، کلید(های) API را یک‌بار دوباره ذخیره کنید تا به‌صورت رمزنگاری‌شده ثبت شوند.
