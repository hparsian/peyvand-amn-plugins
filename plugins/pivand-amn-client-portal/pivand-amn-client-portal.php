<?php
/**
 * Plugin Name: پیوند امن — پنل مراجعان
 * Description: بازطراحی کامل و ماژولار پنل مراجعان OTP، نوبت‌دهی + زرین‌پال، پیام‌ها، نظرسنجی، لید مگنت و اتصال فیلتر-محور به افزونه‌ی آزمون‌ها (psy-quiz-enterprise). جایگزین کامل «custom-psychology-portal».
 * Version: 1.13.1.1
 * Requires at least: 6.6
 * Tested up to: 7.1
 * Author: Hadi.parsaeian
 * Requires PHP: 8.1
 * Domain Path: /languages
 * Text Domain: pivand-amn-client-portal
 * License: GPLv2 or later
 *
 * ==============================================================================
 * چرا این پلاگین جایگزین custom-psychology-portal شد؟
 * آن افزونه یک فایل تک‌قطعه‌ای ۲۸۰۰+ خطی بود که هشت مسئولیت کاملاً متفاوت
 * (احراز هویت، پرداخت، پیام‌رسانی، نظرسنجی، لید، فانل کوییز عمومی، کرون،
 * پیشخوان ادمین) را در یک namespace تخت با نام‌گذاری تابعی (نه کلاسی) قاطی
 * کرده بود. این باعث می‌شد هر تغییر کوچک، ریسک شکستن بخش‌های نامرتبط را
 * داشته باشد و ردیابی باگ‌ها (مثل عدم تطابق تایم‌زون، عدم وجود nonce، یا قفل
 * شدن دکمه OTP روی «در حال بررسی...») به‌شدت زمان‌بر بود.
 *
 * این نسخه هر مسئولیت را در یک کلاس مستقل با namespace واحد قرار داده،
 * تمام مقایسه‌های زمانی را به UTC می‌برد، هر AJAX handler را nonce-محافظت
 * می‌کند، Rate limiting را یکپارچه در یک کلاس امنیتی مشترک پیاده می‌کند و
 * قرارداد فیلترهای بین‌افزونه‌ای (psy_get_pending_quizzes_for_phone،
 * psy_get_client_name_for_phone) را عیناً حفظ می‌کند تا psy-quiz-enterprise
 * بدون هیچ تغییری کار کند.
 * ==============================================================================
 */

if (!defined('ABSPATH')) {
    exit;
}

define('ZDP_VERSION', '1.13.1.1');
define('ZDP_PLUGIN_FILE', __FILE__);
define('ZDP_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('ZDP_PLUGIN_URL', plugin_dir_url(__FILE__));
define('ZDP_DB_VERSION', '1.0.6'); // 1.0.6: جدول فرم ارزیابی (zdp_intake). 1.0.5: افزودن ستون rating (امتیاز ستاره‌ای ۱ تا ۵) به جدول surveys

// --------------------------------------------------------------------------
// اتولودر PSR-4 ساده اختصاصی — بدون composer، چون هاست‌های اشتراکی معمولاً
// vendor/autoload.php را در دسترس ندارند. namespace پایه: ZoojDarman\ClientPortal
// --------------------------------------------------------------------------
spl_autoload_register(function (string $class): void {
    $prefix = 'ZoojDarman\\ClientPortal\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $relative = substr($class, strlen($prefix));
    $relative = str_replace('\\', '/', $relative);

    // تبدیل Class_Name به class-class-name.php مطابق قرارداد فایل‌نویسی وردپرس
    $parts = explode('/', $relative);
    $filename = 'class-' . strtolower(str_replace('_', '-', array_pop($parts))) . '.php';
    $path = ZDP_PLUGIN_DIR . 'includes/' . (count($parts) ? implode('/', array_map('strtolower', $parts)) . '/' : '') . $filename;

    if (is_readable($path)) {
        require_once $path;
    }
});

require_once ZDP_PLUGIN_DIR . 'includes/functions-helpers.php';

use ZoojDarman\ClientPortal\Plugin;

register_activation_hook(__FILE__, [Plugin::class, 'activate']);
register_deactivation_hook(__FILE__, [Plugin::class, 'deactivate']);

add_action('plugins_loaded', static function (): void {
    Plugin::instance()->boot();
});

// ── قرارداد فیلتری مشترک: pas_lookup_known_client_name ──────────────────
// اولویت ۵ (بالاترین) چون نامی که خودِ مراجع موقع ورود OTP روی حساب خودش
// دارد، مطمئن‌ترین منبع است — قبل از پرونده مراجعین و آزمون‌ها چک می‌شود.
// اگر مقدار قبلاً (توسط زنجیره‌ی دیگری) پر شده باشد، دست‌نخورده برمی‌گردد.
add_filter('pas_lookup_known_client_name', function ($default_value, $mobile) {
    if (!empty($default_value)) {
        return $default_value;
    }

    if (!class_exists('Pas_Core_Helpers')) {
        return $default_value;
    }

    $normalized = Pas_Core_Helpers::normalize_mobile((string) $mobile);

    $users = get_users([
        'meta_key'   => 'cellphone',
        'meta_value' => $normalized,
        'number'     => 1,
        'fields'     => ['ID'],
    ]);

    if (empty($users)) {
        // سازگاری عقب‌رو: کاربرهای قدیمی این پلاگین که هنوز cellphone
        // رویشان مهاجرت نکرده (تا مهاجرت خودکار Installer اجرا شود).
        $users = get_users([
            'meta_key'   => 'zdp_mobile',
            'meta_value' => $normalized,
            'number'     => 1,
            'fields'     => ['ID'],
        ]);
    }

    if (empty($users)) {
        return $default_value;
    }

    $name = get_user_meta($users[0]->ID, 'zdp_full_name', true);

    return $name !== '' ? $name : $default_value;
}, 5, 2);
