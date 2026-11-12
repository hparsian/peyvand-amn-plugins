<?php
/**
 * Plugin Name: پیوند امن — احراز دومرحله‌ای ادمین
 * Description: احراز هویت دومرحله‌ای (TOTP، سازگار با Google Authenticator/Authy) برای ورود به پیشخوان — مخصوص ادمین‌هایی که به داده‌های حساس (پرونده مراجعین، تنظیمات پرداخت/پیامک) دسترسی دارند.
 * Version: 1.3.4
 * Requires at least: 6.6
 * Tested up to: 7.1
 * Author: Hadi.parsaeian
 * Text Domain: pivand-amn-2fa
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Requires PHP: 8.1
 * Domain Path: /languages
 *
 * ==============================================================================
 * چرا این افزونه لازم بود؟
 * هیچ‌جای اکوسیستم لایه‌ی دومی برای ورود ادمین وجود نداشت — فقط رمز عبور
 * وردپرس. با توجه به این‌که پیشخوان به داده‌ی بالینی (پرونده مراجعین)،
 * اعتبار پرداخت (زرین‌پال)، و اعتبار پیامک (ملی‌پیامک) دسترسی دارد، این
 * یکی از موارد «حیاتی» بود که قبلاً به‌عنوان کمبود شناسایی شده بود.
 *
 * طراحی: اختیاری per-user (نه اجباری سراسری) — هر کاربر خودش از پروفایلش
 * فعال می‌کند. اگر فعال نکرده باشد، ورودش مثل قبل با فقط رمز عبور کار
 * می‌کند (تا کسی خودش را قفل نکند). توصیه می‌شود همه‌ی ادمین‌ها فعالش کنند.
 *
 * نسخه ۱.۲.۰: اضافه‌شدن کدهای بازیابی یک‌بارمصرف (class-backup-codes.php).
 * قبل از این نسخه، تنها راه رفع قفل برای ادمینی که گوشی/اپ TOTP‌اش را گم
 * کرده بود، دستکاری مستقیم دیتابیس بود.
 * ==============================================================================
 */

if (!defined('ABSPATH')) {
    exit;
}

define('PAS_2FA_VERSION', '1.3.4');
define('PAS_2FA_DIR', plugin_dir_path(__FILE__));
define('PAS_2FA_URL', plugin_dir_url(__FILE__));

require_once PAS_2FA_DIR . 'includes/class-totp.php';
require_once PAS_2FA_DIR . 'includes/class-backup-codes.php';
require_once PAS_2FA_DIR . 'includes/class-login-gate.php';
require_once PAS_2FA_DIR . 'includes/class-profile.php';

add_action('plugins_loaded', function (): void {
    load_plugin_textdomain('pivand-amn-2fa');
    new Pas_2FA_Login_Gate();
    new Pas_2FA_Profile();
});
