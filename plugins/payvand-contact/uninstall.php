<?php
/**
 * اجرا فقط موقع «حذف کامل» افزونه از پیشخوان وردپرس (نه غیرفعال‌سازی ساده).
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

global $wpdb;

// جدول پیام‌های فرم تماس
$wpdb->query( "DROP TABLE IF EXISTS " . $wpdb->prefix . "payvand_messages" );

// تنظیمات
delete_option( 'payvand_admin_email' );
delete_option( 'payvand_rate_limit' );
delete_option( 'payvand_color_override' );
delete_option( 'payvand_force_load_assets' );
delete_option( 'payvand_version' );
