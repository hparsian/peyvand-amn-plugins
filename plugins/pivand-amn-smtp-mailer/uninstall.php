<?php
/**
 * اجرا فقط موقع «حذف کامل» افزونه از پیشخوان وردپرس (نه غیرفعال‌سازی ساده).
 *
 * zsm_settings شامل credential های SMTP (مثلاً پسورد اپلیکیشن جیمیل) است —
 * حتماً باید موقع حذف کامل پاک شود.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

delete_option( 'zsm_settings' );

// جدول صف/گزارش ایمیل (شامل payload ایمیل‌های ناموفق) هم موقع حذف کامل پاک می‌شود.
global $wpdb;
$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . 'zsm_mail_queue' );
wp_clear_scheduled_hook( 'zsm_process_mail_queue' );
