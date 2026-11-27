<?php
/**
 * حذف افزونه — پاک‌سازی محافظت‌شده.
 *
 * داده‌ها تنها زمانی حذف می‌شوند که گزینه «حذف داده‌ها هنگام حذف افزونه» فعال باشد.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

foreach ( array( 'gso_queue_tick', 'gso_queue_followup', 'gso_scan_tick', 'gso_scan_followup', 'gso_watch_tick', 'gso_schedule_scan' ) as $hook ) {
	wp_clear_scheduled_hook( $hook );
	if ( function_exists( 'as_unschedule_all_actions' ) ) {
		as_unschedule_all_actions( $hook, array(), 'gemini-site-operator' );
	}
}

delete_transient( 'gso_api_test' );
delete_transient( 'gso_queue_lock' );

$settings = get_option( 'gso_settings', array() );
$purge    = is_array( $settings ) && ! empty( $settings['delete_data_on_uninstall'] );

if ( $purge ) {
	global $wpdb;
	foreach ( array( 'issues', 'queue', 'snapshots', 'logs' ) as $table ) {
		$name = $wpdb->prefix . 'gso_' . $table;
		$wpdb->query( "DROP TABLE IF EXISTS {$name}" ); // phpcs:ignore WordPress.DB
	}

	$options = array(
		'gso_settings',
		'gso_db_version',
		'gso_plugin_version',
		'gso_scan_state',
		'gso_seo_index',
		'gso_queue_state',
		'gso_backoff_state',
		'gso_quota_until',
		'gso_last_request_at',
		'gso_last_scan',
		'gso_last_scan_found',
		'gso_dirty_objects',
		'gso_custom_css',
		'gso_usage_' . gmdate( 'Ym' ),
	);
	foreach ( $options as $option ) {
		delete_option( $option );
	}

	delete_post_meta_by_key( '_gso_meta_title' );
	delete_post_meta_by_key( '_gso_meta_description' );
	delete_post_meta_by_key( '_gso_schema' );
	delete_post_meta_by_key( 'gso_focus_keyword' );

	$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE 'gso\\_daily\\_%' OR option_name LIKE 'gso\\_usage\\_%' OR option_name LIKE '_transient_gso\\_%' OR option_name LIKE '_transient_timeout_gso\\_%'" ); // phpcs:ignore WordPress.DB
}
