<?php
/**
 * آزمون دود واقعی داخل وردپرس — با WP-CLI اجرا می‌شود:
 *
 *   wp eval-file tests/wp/smoke.php --path=/tmp/wp
 *
 * پیش‌نیاز: وردپرس نصب‌شده و افزونه فعال، بدون Core/Case File، تا رفتار
 * «وابستگی غایب» و «بسته بودن پیش‌فرض» هم آزموده شود.
 */

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "This file must run inside WordPress (wp eval-file).\n" );
	exit( 1 );
}

$GLOBALS['smoke_pass'] = 0;
$GLOBALS['smoke_fail'] = 0;

function smoke( $label, $condition, $detail = '' ) {
	if ( $condition ) {
		$GLOBALS['smoke_pass']++;
		WP_CLI::log( "PASS  $label" );
		return true;
	}

	$GLOBALS['smoke_fail']++;
	WP_CLI::log( 'FAIL  ' . $label . ( '' !== $detail ? "  ($detail)" : '' ) );
	return false;
}

global $wpdb;

WP_CLI::log( '--- activation & schema ---' );

smoke( 'plugin class loaded', class_exists( 'PAS_Dashboard_Plugin' ) );
smoke( 'plugin is active', is_plugin_active( 'peyvand-amn-unified-dashboard/peyvand-amn-unified-dashboard.php' ) );

$audit_table = $wpdb->prefix . 'pas_dashboard_audit';
$notif_table = $wpdb->prefix . 'pas_dashboard_notifications';
// دو-موتوره: SHOW TABLES LIKE روی MySQL و SQLite drop-in کار می‌کند.
$table_exists = static function ( $table ) use ( $wpdb ) {
	return $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table;
};

smoke( 'audit table created by dbDelta', $table_exists( $audit_table ) );
smoke( 'notifications table created by dbDelta', $table_exists( $notif_table ) );
smoke( 'db version option stored', '1.0.0' === get_option( 'pas_dashboard_db_version' ), var_export( get_option( 'pas_dashboard_db_version' ), true ) );

WP_CLI::log( '--- capabilities ---' );

$admin = get_role( 'administrator' );
smoke( 'administrator holds dashboard cap', $admin && $admin->has_cap( PAS_DASHBOARD_CAP ) );
smoke( 'administrator keeps manage_options', $admin && $admin->has_cap( 'manage_options' ) );

WP_CLI::log( '--- fail-closed without upstream plugins or crypto key ---' );

// این آزمون در دو حالت اجرا می‌شود: با و بدون افزونه‌های بالادستی.
// بنابراین «تشخیص» با «واقعیت کلاس» مقایسه می‌شود، نه با فرض نبودن.
smoke( 'Core detection matches class reality', PAS_Dashboard_Core_Bridge::has_core() === class_exists( 'Pas_Core_Client_Registry' ) );
smoke( 'Case File detection matches class reality', PAS_Dashboard_Core_Bridge::has_case_file() === class_exists( 'Pas_CF_Case_File' ) );

$expected_crypto = ( class_exists( 'Pas_Core_Crypto' ) && Pas_Core_Crypto::is_configured() )
	|| ( class_exists( 'Pas_CF_Crypto' ) && Pas_CF_Crypto::is_configured() );

smoke( 'crypto readiness matches configuration', PAS_Dashboard_Core_Bridge::crypto_ready() === $expected_crypto );
smoke(
	'note writing is never allowed without crypto',
	! PAS_Dashboard_Security::notes_writable() || PAS_Dashboard_Core_Bridge::crypto_ready()
);

$anon_denied = PAS_Dashboard_Security::authorized_case( 1 );
smoke( 'anonymous case access returns WP_Error', is_wp_error( $anon_denied ), is_wp_error( $anon_denied ) ? $anon_denied->get_error_code() : gettype( $anon_denied ) );
smoke( 'dashboard denied to anonymous', ! PAS_Dashboard_Security::can_access_dashboard() );

wp_set_current_user( 1 );
$admin_case = PAS_Dashboard_Security::authorized_case( 1 );
smoke( 'missing case is not leaked to admin', is_wp_error( $admin_case ) && 'pas_dashboard_not_found' === $admin_case->get_error_code(), is_wp_error( $admin_case ) ? $admin_case->get_error_code() : 'object returned' );

$settings = PAS_Dashboard_Permissions::settings();
smoke( 'settings option is an array', is_array( $settings ) );
smoke( 'module matrix exposes 11 modules', 11 === count( PAS_Dashboard_Permissions::matrix() ), (string) count( PAS_Dashboard_Permissions::matrix() ) );
smoke( 'notifications feature defaults on', (bool) PAS_Dashboard_Permissions::feature( 'enable_notifications' ) );
smoke( 'assessments module reachable for admin', PAS_Dashboard_Permissions::can( 'assessments' ) );
smoke( 'export allowed for admin', PAS_Dashboard_Export::can_export() );

WP_CLI::log( '--- hooks, menu, REST ---' );

foreach ( array( 'save_note', 'delete_note', 'restore_note', 'mark_notifications_read' ) as $action ) {
	smoke( "ajax hook registered: $action", false !== has_action( "wp_ajax_pas_dashboard_$action" ) );
	smoke( "no unauthenticated ajax: $action", false === has_action( "wp_ajax_nopriv_pas_dashboard_$action" ) );
}

do_action( 'admin_menu' );

global $menu, $submenu;
$slugs       = wp_list_pluck( (array) $menu, 2 );
$sub_slugs   = isset( $submenu[ PAS_DASHBOARD_SLUG ] ) ? wp_list_pluck( $submenu[ PAS_DASHBOARD_SLUG ], 2 ) : array();

smoke( 'dashboard menu registered', in_array( PAS_DASHBOARD_SLUG, $slugs, true ) );
// طراحی: یک منوی واحد و ناوبری ماژول‌ها درون صفحه (module=...)، نه زیرمنو.
smoke( '11 modules visible to admin', 11 === count( PAS_Dashboard_Permissions::visible_modules() ), (string) count( PAS_Dashboard_Permissions::visible_modules() ) );
smoke( 'no stray submenus under dashboard', 0 === count( $sub_slugs ), (string) count( $sub_slugs ) );

$rest_server     = rest_get_server();
do_action( 'rest_api_init', $rest_server );
$routes          = array_keys( $rest_server->get_routes() );
$dashboard_routes = array_values( preg_filter( '#^/pas-dashboard/v1/#', '$0', $routes ) );

smoke( '3 dashboard REST routes registered', 3 === count( $dashboard_routes ), implode( ',', $dashboard_routes ) );

wp_set_current_user( 0 );
$anon_res = rest_do_request( new WP_REST_Request( 'GET', '/pas-dashboard/v1/summary' ) );
smoke( 'REST summary denies anonymous', in_array( $anon_res->get_status(), array( 401, 403 ), true ), 'status ' . $anon_res->get_status() );

$anon_notes = rest_do_request( new WP_REST_Request( 'GET', '/pas-dashboard/v1/case/1/notes' ) );
smoke( 'REST notes denies anonymous', in_array( $anon_notes->get_status(), array( 401, 403 ), true ), 'status ' . $anon_notes->get_status() );

wp_set_current_user( 1 );
$admin_res = rest_do_request( new WP_REST_Request( 'GET', '/pas-dashboard/v1/health' ) );
smoke( 'REST health answers admin', 200 === $admin_res->get_status(), 'status ' . $admin_res->get_status() );

WP_CLI::log( '--- own-table writes ---' );

PAS_Dashboard_Audit_Log::record( 'smoke_test', 'case', 7, 'ok' );
$rows = $wpdb->get_results( "SELECT * FROM {$audit_table} ORDER BY id DESC LIMIT 1" );
smoke( 'audit write and read', ! empty( $rows ) && 'smoke_test' === $rows[0]->action );
smoke( 'audit stores no raw IP', ! empty( $rows ) && 32 === strlen( (string) $rows[0]->ip_hash ) );

$inserted = $wpdb->insert(
	$notif_table,
	array(
		'user_id'    => 1,
		'dedupe_key' => 'smoke-' . time(),
		'type'       => 'upcoming_session',
		'title'      => 'پیام آزمایشی',
		'ref'        => '7',
		'created_at' => current_time( 'mysql', true ),
		'read_at'    => null,
	),
	array( '%d', '%s', '%s', '%s', '%s', '%s', '%s' )
);
$notification_id = (int) $wpdb->insert_id;

smoke( 'notification insert succeeds', 1 === $inserted && $notification_id > 0, $wpdb->last_error );
smoke( 'unread count reflects insert', 1 === PAS_Dashboard_Notifications::unread_count( 1 ) );

$owner = wp_insert_user(
	array(
		'user_login' => 'pas_smoke_owner_' . wp_rand( 1000, 9999 ),
		'user_pass'  => wp_generate_password( 20 ),
		'user_email' => 'owner' . wp_rand( 1000, 9999 ) . '@example.test',
		'role'       => 'subscriber',
	)
);

$foreign = PAS_Dashboard_Notifications::mark_read( $notification_id, (int) $owner );
smoke( 'mark_read rejects a foreign notification', ! $foreign );
smoke( 'foreign mark_read leaves it unread', 1 === PAS_Dashboard_Notifications::unread_count( 1 ) );

smoke( 'mark_read works for the owner', (bool) PAS_Dashboard_Notifications::mark_read( $notification_id, 1 ) );
smoke( 'unread count drops after read', 0 === PAS_Dashboard_Notifications::unread_count( 1 ) );

WP_CLI::log( '--- CSV cell hardening ---' );

smoke( 'formula cell is neutralised', "'=HYPERLINK(\"http://evil\")" === PAS_Dashboard_Export::csv_cell( '=HYPERLINK("http://evil")' ) );
smoke( 'plus-prefixed cell neutralised', "'+1" === PAS_Dashboard_Export::csv_cell( '+1' ) );
smoke( 'at-prefixed cell neutralised', "'@SUM(1)" === PAS_Dashboard_Export::csv_cell( '@SUM(1)' ) );
smoke( 'plain value untouched', 'فعال' === PAS_Dashboard_Export::csv_cell( 'فعال' ) );
smoke( 'numeric id untouched', '42' === PAS_Dashboard_Export::csv_cell( 42 ) );
smoke( 'empty value untouched', '' === PAS_Dashboard_Export::csv_cell( '' ) );

WP_CLI::log( '' );
WP_CLI::log( 'PASSED: ' . $GLOBALS['smoke_pass'] . '  FAILED: ' . $GLOBALS['smoke_fail'] );

exit( $GLOBALS['smoke_fail'] > 0 ? 1 : 0 );
