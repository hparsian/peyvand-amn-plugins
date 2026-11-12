<?php
/**
 * Plugin Name: پیوند امن — پرونده مراجعین
 * Description: پرونده بالینی متمرکز برای هر مراجع (مشخصات + یادداشت جلسات، رمزنگاری‌شده) با ورود فقط از طریق حساب ادمین وردپرس (بدون OTP)، و اتصال فیلتر-محور به شماره موبایلی که افزونه‌ی ورود مراجعین (پنل مراجعان) با آن حساب می‌سازد.
 * Version: 1.15.3
 * Requires at least: 6.6
 * Tested up to: 7.1
 * Author: Hadi.parsaeian
 * Text Domain: pivand-amn-case-file
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Requires PHP: 8.1
 * Domain Path: /languages
 *
 * ------------------------------------------------------------------------------
 * تغییر ۱.۸.۱:
 *   - نقش «درمانگر» (pas_cf_therapist) حالا خودکار روی فعال‌سازی/آپدیت
 *     ساخته می‌شود — فقط با PAS_CF_CAP (پرونده‌های خودش)، بدون
 *     PAS_CF_CAP_ALL و بدون هیچ اختیار مدیریتی دیگر وردپرس. قبلاً این نقش
 *     باید با ابزار جداگانه (User Role Editor یا wp-cli) دستی ساخته
 *     می‌شد. نگاه کنید به Pas_CF_Security::ensure_therapist_role.
 * ------------------------------------------------------------------------------
 * تغییر ۱.۸.۰:
 *   - دکمه‌ی «🖨 پرینت / ذخیره PDF» — یک نمای مستقل و تمیز از کل پرونده
 *     (مشخصات، سابقه‌ی بالینی، سوالات مصاحبه، آزمون CSI-16، اطلاعات همسر،
 *     همه‌ی یادداشت‌های جلسه) بدون قالب ادمین وردپرس؛ «ذخیره به PDF» از
 *     طریق مقصد Save-as-PDF در دیالوگ پرینت خودِ مرورگر انجام می‌شود — بدون
 *     نیاز به کتابخانه‌ی PDF سمت سرور.
 * ------------------------------------------------------------------------------
 * تغییر ۱.۷.۰:
 *   - بازگردانی نسخه‌ی قبلی یادداشت (هم ویرایش‌شده، هم حذف‌شده) از داخل
 *     تاریخچه — نسخه‌ی فعلی هم قبل از بازنویسی snapshot می‌شود.
 *   - آمار «N جلسه ثبت‌شده، آخرین جلسه: …» زیر سربرگ پرونده.
 *   - هشدار «راکد» در لیست پرونده‌ها برای پرونده‌ی فعالی که بیش از
 *     ۳۰ روز از آخرین جلسه‌اش گذشته (یک کوئری گروه‌بندی‌شده، نه N+1).
 *   - خروجی CSV لیست پرونده‌ها (با BOM برای اکسل فارسی)، با همان محدوده‌ی
 *     دید و فیلتر جستجوی صفحه‌ی لیست.
 * ------------------------------------------------------------------------------
 * تغییر ۱.۶.۰:
 *   - همه‌ی تاریخ‌های نمایشی پرونده (ثبت‌نام OTP، نوبت‌ها، تکمیل آزمون
 *     CSI-16، تاریخچه‌ی دسترسی، آخرین به‌روزرسانی) حالا شمسی‌اند.
 *   - حذف یادداشت جلسه اضافه شد (با تایید مرورگری)؛ محتوای حذف‌شده در
 *     جدول جدید note_history نگه داشته می‌شود، نه واقعاً پاک.
 *   - تاریخچه‌ی ویرایش هر یادداشت (چه زمانی/توسط چه کسی، + متن نسخه‌ی
 *     قبلی) قابل مشاهده است.
 *   - لیست یادداشت‌ها صفحه‌بندی شد: ۲۰ مورد اخیر نمایش مستقیم، بقیه پشت
 *     یک details تاکردنی «نمایش N جلسه‌ی قدیمی‌تر».
 *   - هشدار نرم (غیربلاک‌کننده) اگر تاریخ جلسه در آینده باشد.
 * ------------------------------------------------------------------------------
 * تغییر ۱.۵.۰:
 *   - تاریخ جلسه (ثبت و ویرایش یادداشت) حالا شمسی است — ورودی و نمایش هر دو
 *     جلالی، ذخیره‌سازی در دیتابیس همچنان میلادی (ستون DATE). تبدیل با
 *     Pas_CF_Jalali (پورت مستقل الگوریتم jalaali-js، دو‌طرفه).
 *   - یادداشت‌های جلسات قبلی حالا قابل باز شدن (details/summary) و ویرایش
 *     هستند (قبلاً فقط افزودن یادداشت جدید ممکن بود).
 *   - فیلد جدید «سوء مصرف مواد» (رمزنگاری‌شده، نسخه‌ی مراجع و همسر) به
 *     مشخصات پرونده اضافه شد.
 * ------------------------------------------------------------------------------
 * تغییر ۱.۲.۰ — محدودسازی دسترسی بر اساس درمانگر مسئول
 * قبلاً هر کاربری با pas_manage_case_files همه‌ی پرونده‌ها را می‌دید، با
 * اینکه ستون assigned_therapist از قبل در جدول وجود داشت (ولی هیچ‌جا فیلتر
 * یا حتی در فرم قابل‌تنظیم نبود). حالا طبق همان الگویی که در کامنت
 * class-security.php از قبل به‌عنوان هدف آینده نوشته شده بود:
 *   - یک capability دوم (pas_view_all_case_files) اضافه شد — فقط برای
 *     نقش‌هایی مثل «سرپرست/ادمین کلینیک» که باید همه‌ی پرونده‌ها را ببینند.
 *   - کاربری که فقط pas_manage_case_files دارد (مثلاً نقش آینده‌ی
 *     «درمانگر») فقط پرونده‌های assigned_therapist = خودش را می‌بیند و
 *     نمی‌تواند پرونده‌ی کس دیگری را باز کند (حتی با لینک مستقیم).
 * ==============================================================================
 * چرا افزونه‌ی جدا، نه ماژول داخل پنل مراجعان؟
 * پنل مراجعان (client-portal) مسئول تجربه‌ی مراجع است (OTP، نوبت، پیام) — نه
 * نگهداری پرونده‌ی بالینی. قاطی کردن این دو دقیقاً همان اشتباه معماری
 * custom-psychology-portal قدیمی بود. با جدا نگه‌داشتن این افزونه:
 *   - دسترسی به پرونده‌ی بالینی را می‌توان جدا از نقش‌های دیگر کنترل کرد
 *     (capability اختصاصی pas_manage_case_files، نه manage_options عمومی).
 *   - اگر پنل مراجعان یا آزمون‌ها غیرفعال شوند، پرونده‌ها دست‌نخورده می‌مانند.
 *
 * چرا ادمین بدون OTP وارد می‌شود؟
 * OTP مخصوص احراز هویت مراجع است. ادمین/درمانگر از همان لاگین استاندارد
 * وردپرس + بررسی capability استفاده می‌کند — این دقیقاً همان الگوی خود
 * وردپرس است و نیازی به یک لایه‌ی احراز دوم برای کاربری که از قبل در
 * wp-admin احراز شده نیست.
 *
 * اتصال به افزونه‌ی ورود (پنل مراجعان):
 * پنل مراجعان با ورود OTP یک کاربر وردپرسی با user_login = 'zdp_' + موبایل
 * می‌سازد (نگاه کنید به ZoojDarman\ClientPortal\Otp_Auth::verify). این افزونه
 * از همان قرارداد برای پیدا کردن حساب مرتبط با یک پرونده استفاده می‌کند —
 * بدون کپی کردن داده، فقط لوکاپ.
 * ==============================================================================
 */

if (!defined('ABSPATH')) {
    exit;
}

define('PAS_CF_VERSION', '1.15.3');
define('PAS_CF_DIR', plugin_dir_path(__FILE__));
define('PAS_CF_URL', plugin_dir_url(__FILE__));
define('PAS_CF_CAP', 'pas_manage_case_files'); // ورود به پنل پرونده‌ها (حداقلی)
define('PAS_CF_CAP_ALL', 'pas_view_all_case_files'); // دیدن پرونده‌ی همه‌ی درمانگرها، نه فقط خودش

require_once PAS_CF_DIR . 'includes/class-installer.php';
require_once PAS_CF_DIR . 'includes/class-jalali.php';
require_once PAS_CF_DIR . 'includes/class-crypto.php';
require_once PAS_CF_DIR . 'includes/class-security.php';
require_once PAS_CF_DIR . 'includes/class-case-file.php';
require_once PAS_CF_DIR . 'includes/class-audit-log.php';
require_once PAS_CF_DIR . 'includes/class-timeline.php';
require_once PAS_CF_DIR . 'includes/class-reports.php';
require_once PAS_CF_DIR . 'includes/class-admin.php';

register_activation_hook(__FILE__, function (): void {
    Pas_CF_Installer::create_or_upgrade_tables();
    Pas_CF_Security::grant_capability_to_administrators();
    Pas_CF_Security::ensure_therapist_role();
});

/**
 * برای سایت‌هایی که از نسخه‌ی قدیمی‌تر آپدیت می‌کنند: activation hook
 * دوباره اجرا نمی‌شود چون افزونه از قبل فعال بوده، پس تغییرات مربوط به
 * capability/role (مثل PAS_CF_CAP_ALL در ۱.۲.۰، یا نقش «درمانگر» در
 * ۱.۸.۱) هرگز اعمال نمی‌شد. این چک یک‌بار در plugins_loaded اجرا می‌شود
 * و بی‌خطر است (add_cap/add_role اگر از قبل موجود باشند کاری نمی‌کنند).
 */
add_action('plugins_loaded', function (): void {
    if (get_option('pas_cf_db_version') !== PAS_CF_VERSION) {
        Pas_CF_Installer::create_or_upgrade_tables();
        Pas_CF_Security::grant_capability_to_administrators();
        Pas_CF_Security::ensure_therapist_role();
    }
}, 5);

add_action('plugins_loaded', function (): void {
    load_plugin_textdomain('pivand-amn-case-file');

    new Pas_CF_Admin();

    /**
     * قرارداد فیلتر برای بقیه‌ی اکوسیستم (اختیاری، اگر لازم شد):
     * apply_filters('pas_get_case_summary_for_phone', null, $mobile)
     * فعلاً چیزی این را صدا نمی‌زند، ولی طبق همان الگوی pas_send_sms /
     * psy_get_client_name_for_phone آماده نگه داشته شده تا در آینده مثلاً
     * پنل مراجعان بتواند بدون دسترسی مستقیم به جدول‌های این افزونه یک خلاصه
     * بگیرد.
     */
    add_filter('pas_get_case_summary_for_phone', ['Pas_CF_Case_File', 'get_public_summary_for_phone'], 10, 2);

    /**
     * قرارداد فیلتری مشترک pas_lookup_known_client_name — اولویت ۱۰ (بین
     * پنل مراجعان و آزمون‌ها). اگر یک مراجع در پنل هنوز نام خودش را ثبت
     * نکرده ولی پرونده‌ی بالینی برایش باز شده، آن نام اینجا برگردانده
     * می‌شود.
     */
    add_filter('pas_lookup_known_client_name', function ($default_value, $mobile) {
        if (!empty($default_value)) {
            return $default_value;
        }

        $name = Pas_CF_Case_File::get_known_name_for_phone((string) $mobile);

        return $name !== '' ? $name : $default_value;
    }, 10, 2);
});
