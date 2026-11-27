<?php
/**
 * حذف افزونه — محافظه‌کارانه.
 *
 * داده‌های بالینی هرگز حذف نمی‌شوند. فقط در صورتی که مدیر صراحتاً گزینه‌ی
 * «حذف داده‌های داشبورد هنگام حذف افزونه» را فعال کرده باشد، جداول
 * اختصاصی داشبورد (لاگ ممیزی و اعلان‌ها) حذف می‌شوند.
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

$settings = get_option( 'pas_dashboard_settings', array() );

if ( ! empty( $settings['delete_data_on_uninstall'] ) ) {
	global $wpdb;

	$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . 'pas_dashboard_notifications' );
	$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . 'pas_dashboard_audit' );

	delete_option( 'pas_dashboard_settings' );
	delete_option( 'pas_dashboard_db_version' );
	delete_option( 'pas_dashboard_version' );
}

// کپابیلیتی داشبورد از نقش مدیر برداشته می‌شود (بدون حذف هیچ داده‌ای).
$admin = get_role( 'administrator' );
if ( $admin ) {
	$admin->remove_cap( 'pas_dashboard_access' );
}
