<?php
/**
 * اجرا فقط موقع «حذف کامل» افزونه از پیشخوان وردپرس (نه غیرفعال‌سازی ساده).
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

global $wpdb;

$wpdb->query( "DROP TABLE IF EXISTS " . $wpdb->prefix . "pas_sms_log" );

delete_option( 'pas_bridge_db_version' );
delete_option( 'pas_sms_username' );
delete_option( 'pas_sms_password' );
