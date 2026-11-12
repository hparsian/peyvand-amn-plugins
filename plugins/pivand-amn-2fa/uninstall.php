<?php
/**
 * اجرا فقط موقع «حذف کامل» افزونه از پیشخوان وردپرس (نه غیرفعال‌سازی ساده).
 *
 * توجه امنیتی: این افزونه هیچ جدول یا آپشنی نمی‌سازد — فقط یک user_meta
 * (pas_2fa_secret) روی هر کاربری که 2FA را فعال کرده ذخیره می‌کند. حذف
 * افزونه یعنی لایه‌ی دوم احراز هویت برای همه‌ی ادمین‌ها غیرفعال می‌شود،
 * پس این متا باید پاک شود تا اگر افزونه دوباره نصب شد، حساب‌ها با یک
 * secret قدیمی/یتیم گیر نکنند.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

global $wpdb;

$wpdb->delete( $wpdb->usermeta, [ 'meta_key' => 'pas_2fa_secret' ] );

// transient های چالش/تایید در حال انتظار (pas_2fa_challenge_*, pas_2fa_pending_*)
// معمولاً خودشان expire می‌شوند، ولی برای پاکسازی فوری:
$wpdb->query(
    "DELETE FROM {$wpdb->options}
     WHERE option_name LIKE '_transient_pas_2fa_%'
        OR option_name LIKE '_transient_timeout_pas_2fa_%'"
);
