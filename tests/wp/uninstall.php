<?php
/**
 * آزمون واقعی حذف افزونه — با WP-CLI:
 *
 *   wp eval-file tests/wp/uninstall.php --path=/tmp/wp
 *
 * سناریو الف: بدون تیک «حذف داده‌ها» → هیچ جدولی حذف نمی‌شود.
 * سناریو ب: با تیک → فقط جداول اختصاصی داشبورد حذف می‌شوند.
 */

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "Run inside WordPress.\n" );
	exit( 1 );
}

global $wpdb;

$GLOBALS['u_pass'] = 0;
$GLOBALS['u_fail'] = 0;

function ucheck( $label, $condition, $detail = '' ) {
	if ( $condition ) {
		$GLOBALS['u_pass']++;
		WP_CLI::log( "PASS  $label" );
		return;
	}

	$GLOBALS['u_fail']++;
	WP_CLI::log( 'FAIL  ' . $label . ( '' !== $detail ? "  ($detail)" : '' ) );
}

$audit = $wpdb->prefix . 'pas_dashboard_audit';
$notif = $wpdb->prefix . 'pas_dashboard_notifications';
$plugin_file = WP_PLUGIN_DIR . '/peyvand-amn-unified-dashboard/uninstall.php';

function table_present( $name ) {
	global $wpdb;
	// دو-موتوره: روی MySQL و SQLite drop-in یکسان کار می‌کند.
	return $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $name ) ) === $name;
}

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	define( 'WP_UNINSTALL_PLUGIN', 'peyvand-amn-unified-dashboard/peyvand-amn-unified-dashboard.php' );
}

update_option( 'pas_dashboard_version', '2.0.0' );

WP_CLI::log( '--- scenario A: default uninstall keeps data ---' );

ucheck( 'tables exist before uninstall', table_present( $audit ) && table_present( $notif ) );

include $plugin_file;

ucheck( 'audit table survives', table_present( $audit ) );
ucheck( 'notifications table survives', table_present( $notif ) );
ucheck( 'settings survive', is_array( get_option( 'pas_dashboard_settings' ) ) );
ucheck( 'db version survives', '1.0.0' === get_option( 'pas_dashboard_db_version' ) );
ucheck( 'plugin version option survives', '2.0.0' === get_option( 'pas_dashboard_version' ) );
ucheck( 'dashboard cap removed from admin', ! get_role( 'administrator' )->has_cap( 'pas_dashboard_access' ) );

WP_CLI::log( '--- scenario B: opt-in uninstall removes own tables only ---' );

$settings                          = (array) get_option( 'pas_dashboard_settings', array() );
$settings['delete_data_on_uninstall'] = true;
update_option( 'pas_dashboard_settings', $settings );
get_role( 'administrator' )->add_cap( 'pas_dashboard_access' );

include $plugin_file;

ucheck( 'audit table dropped', ! table_present( $audit ) );
ucheck( 'notifications table dropped', ! table_present( $notif ) );
ucheck( 'settings option deleted', false === get_option( 'pas_dashboard_settings' ) );
ucheck( 'db version option deleted', false === get_option( 'pas_dashboard_db_version' ) );
ucheck( 'plugin version option deleted', false === get_option( 'pas_dashboard_version' ) );
ucheck( 'core WordPress tables untouched', table_present( $wpdb->prefix . 'posts' ) && table_present( $wpdb->prefix . 'users' ) );

WP_CLI::log( '' );
WP_CLI::log( 'PASSED: ' . $GLOBALS['u_pass'] . '  FAILED: ' . $GLOBALS['u_fail'] );

exit( $GLOBALS['u_fail'] > 0 ? 1 : 0 );
