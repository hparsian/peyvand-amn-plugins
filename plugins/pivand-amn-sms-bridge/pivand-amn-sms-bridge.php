<?php
/**
 * Plugin Name: پیوند امن — پل پیامک (ملی پیامک)
 * Description: تنها نقطه‌ی اتصال واقعی به وب‌سرویس ملی پیامک برای کل اکوسیستم پلاگین‌های زوج‌درمان. هیچ پلاگین دیگری (زوج‌درمان، آزمون‌ها، ...) مستقیماً به ملی پیامک وصل نمی‌شود؛ همه از طریق فیلتر مشترک pas_send_sms درخواست ارسال می‌دهند.
 * Version: 1.1.6
 * Requires at least: 6.6
 * Tested up to: 7.1
 * Author: Hadi.parsaeian
 * Text Domain: pivand-amn-sms-bridge
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Requires PHP: 8.1
 * Domain Path: /languages
 *
 * ==============================================================================
 * چرا این پلاگین جدا از بقیه است؟
 * طبق همون الگویی که تو کل اکوسیستم زوج‌درمان/پیوند‌امن جا افتاده: «ارسال
 * پیامک منحصراً از طریق یک فیلتر مشترک». این یعنی اگر روزی سرویس پیامکی عوض
 * شد (مثلاً از ملی پیامک به کاوه‌نگار)، فقط همین یک پلاگین را عوض می‌کنی —
 * نه هر پلاگینی که پیامک می‌فرستد.
 *
 * تغییر 1.1.6 (فاز ۰ — ایمن‌سازی اعتبارنامه):
 *   نسخه‌های ≤1.1.5 اعتبارنامه‌ی پیش‌فرض ملی‌پیامک را به‌صورت متن ساده در
 *   همین فایل و در هوک فعال‌سازی هاردکد می‌کردند. آن مقدار حذف شد و از این
 *   به بعد اعتبارنامه از ثابت‌های wp-config.php یا تنظیمات پیشخوان خوانده
 *   می‌شود. حذف مقدار از کد، مقداری را که قبلاً در دیتابیس ذخیره شده باطل
 *   نمی‌کند؛ اگر آن مقدار لو رفته فرض شود باید از پنل ملی‌پیامک چرخش شود —
 *   افزونه در پیشخوان هشدار می‌دهد ولی هیچ مقدار ذخیره‌شده‌ای را خودکار
 *   پاک/بازنویسی نمی‌کند تا سرویس پیامک بی‌صدا قطع نشود.
 * ==============================================================================
 */

if (!defined('ABSPATH')) {
    exit;
}

define('PAS_BRIDGE_VERSION', '1.1.6');
define('PAS_BRIDGE_DIR', plugin_dir_path(__FILE__));
define('PAS_BRIDGE_URL', plugin_dir_url(__FILE__));

require_once PAS_BRIDGE_DIR . 'includes/class-pas-melipayamak.php';
require_once PAS_BRIDGE_DIR . 'includes/class-pas-log.php';
require_once PAS_BRIDGE_DIR . 'includes/class-pas-admin.php';

add_action('plugins_loaded', function (): void {
    Pas_Log::maybe_create_table();
    new Pas_Admin();

    /**
     * قرارداد مشترک: apply_filters('pas_send_sms', null, $mobile, $body_id, $params)
     * - $mobile: شماره به فرمت محلی 09xxxxxxxxx (یا هر فرمتی؛ اینجا نرمال می‌شود)
     * - $body_id: شناسه‌ی پترن ثبت‌شده در پنل ملی پیامک (bodyId)
     * - $params: آرایه‌ی انجمنی؛ فقط VALUEها (نه کلیدها) به ترتیب، با ; به هم
     *   وصل و به‌عنوان مقدار متغیرهای پترن به ملی پیامک فرستاده می‌شوند —
     *   چون API ملی پیامک placeholder نام‌دار ندارد، فقط ترتیبی.
     * - خروجی: true (ارسال موفق) یا false (شکست) — هرگز چیز دیگری، تا
     *   caller ها (OTP، رزرو نوبت، فالوآپ لید) بتوانند ساده if(!$sent) بزنند.
     */
    add_filter('pas_send_sms', ['Pas_Melipayamak', 'handle_send_sms_filter'], 10, 4);
});

register_activation_hook(__FILE__, ['Pas_Log', 'maybe_create_table']);

/**
 * فاز ۰ (1.1.6) — ترتیب خواندن اعتبارنامه در Pas_Melipayamak::credentials():
 *   ۱. ثابت‌های PAS_SMS_USERNAME / PAS_SMS_PASSWORD در wp-config.php (توصیه‌شده،
 *      چون خارج از دیتابیس و خارج از فایل‌های قابل‌دانلود افزونه است)
 *   ۲. مقادیر ذخیره‌شده در پیشخوان «پل پیامک»
 * اگر هیچ‌کدام تنظیم نشده باشد، ارسال پیامک با پیام واضح در جدول لاگ شکست
 * می‌خورد (بدون خطای PHP) و یک هشدار در پیشخوان نمایش داده می‌شود.
 *
 * روی نصب تازه هیچ اعتبارنامه‌ای seed نمی‌شود. تنها کاری که این هوک می‌کند
 * ثبت وضعیت «تنظیم نشده» برای نمایش هشدار است — هیچ مقدار حساسی نوشته نمی‌شود.
 */
register_activation_hook(__FILE__, function (): void {
    if (class_exists('Pas_Melipayamak') && Pas_Melipayamak::credentials_source() === 'missing') {
        update_option('pas_sms_credentials_missing', '1');
    }
});
