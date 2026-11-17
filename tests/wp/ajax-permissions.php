<?php
/**
 * آزمون زنده‌ی مجوزهای AJAX — بدون مرورگر و بدون exit.
 *
 * هندلر wp_die را موقتاً به استثنا تبدیل می‌کند تا wp_send_json کشته نشود و
 * بتوانیم پاسخ JSON را در همان فرایند بررسی کنیم.
 *
 *   wp eval-file tests/wp/ajax-permissions.php --path=/tmp/wp
 */

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "Run inside WordPress.\n" );
	exit( 1 );
}

// wp_send_json() وقتی DOING_AJAX false باشد مستقیم die می‌کند و هندلر wp_die
// هرگز صدا زده نمی‌شود؛ برای همین در همین فرایند آزمون آن را true می‌کنیم.
if ( ! defined( 'DOING_AJAX' ) ) {
	define( 'DOING_AJAX', true );
}

$GLOBALS['a_pass'] = 0;
$GLOBALS['a_fail'] = 0;

function acheck( $label, $condition, $detail = '' ) {
	if ( $condition ) {
		$GLOBALS['a_pass']++;
		WP_CLI::log( "PASS  $label" );
		return true;
	}

	$GLOBALS['a_fail']++;
	WP_CLI::log( 'FAIL  ' . $label . ( '' !== $detail ? "  ($detail)" : '' ) );
	return false;
}

/** هندلر wp_die که به‌جای exit استثنا می‌دهد. */
function pas_test_die_handler( $message = '', $title = '', $args = array() ) {
	throw new RuntimeException( 'wp_die' );
}

// همه‌ی مسیرهای wp_die (ajax/json/jsonp/xml/xmlrpc) باید به استثنا تبدیل شوند،
// وگرنه WP-CLI ممکن است یکی از آن‌ها را انتخاب کند و فرایند exit شود.
foreach ( array( 'wp_die_handler', 'wp_die_ajax_handler', 'wp_die_json_handler', 'wp_die_jsonp_handler', 'wp_die_xml_handler', 'wp_die_xmlrpc_handler' ) as $pas_die_filter ) {
	add_filter( $pas_die_filter, static function () { return 'pas_test_die_handler'; } );
}

/**
 * اجرای یک اکشن AJAX و برگرداندن JSON رمزگشایی‌شده.
 *
 * @return array{success:bool|null,data:mixed,raw:string}
 */
function pas_ajax_call( $action, array $post ) {
	$_POST    = $post;
	$_REQUEST = $post;
	$raw      = '';
	$success  = null;

	ob_start();
	try {
		do_action( 'wp_ajax_' . $action );
	} catch ( Throwable $e ) {
		// wp_send_json پیش از wp_die خروجی را echo کرده است.
	}
	$raw = (string) ob_get_clean();

	$decoded = json_decode( $raw, true );
	if ( is_array( $decoded ) && array_key_exists( 'success', $decoded ) ) {
		$success = (bool) $decoded['success'];
	}

	return array( 'success' => $success, 'data' => $decoded['data'] ?? null, 'raw' => $raw );
}

global $wpdb;

WP_CLI::log( '--- fixtures ---' );

$stamp = time();

$make_user = static function ( $login, $role ) use ( $stamp ) {
	$id = wp_insert_user(
		array(
			'user_login'   => $login . '_' . $stamp,
			'user_pass'    => wp_generate_password( 24 ),
			'user_email'   => $login . '_' . $stamp . '@example.test',
			'display_name' => $login,
			'role'         => $role,
		)
	);

	return is_wp_error( $id ) ? 0 : (int) $id;
};

$therapist_a = $make_user( 'pas_ajax_a', 'pas_cf_therapist' );
$therapist_b = $make_user( 'pas_ajax_b', 'pas_cf_therapist' );
$subscriber  = $make_user( 'pas_ajax_sub', 'subscriber' );

wp_set_current_user( 1 );
$case_a = Pas_CF_Case_File::create( array( 'phone' => '0912' . wp_rand( 1000000, 9999999 ), 'assigned_therapist' => $therapist_a, 'status' => 'فعال' ) );
$case_b = Pas_CF_Case_File::create( array( 'phone' => '0913' . wp_rand( 1000000, 9999999 ), 'assigned_therapist' => $therapist_b, 'status' => 'فعال' ) );

acheck( 'fixtures created', $therapist_a > 0 && $therapist_b > 0 && $subscriber > 0 && $case_a > 0 && $case_b > 0 );

WP_CLI::log( '--- unauthenticated / nonce / capability ---' );

wp_set_current_user( 0 );
$anon = pas_ajax_call( 'pas_dashboard_save_note', array( 'case_id' => $case_a, 'session_date' => '2026-11-12', 'content' => 'x', 'nonce' => 'nope' ) );
acheck( 'anonymous AJAX denied', false === $anon['success'], $anon['raw'] );

wp_set_current_user( $therapist_a );
$bad_nonce = pas_ajax_call( 'pas_dashboard_save_note', array( 'case_id' => $case_a, 'session_date' => '2026-11-12', 'content' => 'x', 'nonce' => 'invalid' ) );
acheck( 'invalid nonce denied', false === $bad_nonce['success'], $bad_nonce['raw'] );

wp_set_current_user( $subscriber );
$no_cap = pas_ajax_call( 'pas_dashboard_save_note', array( 'case_id' => $case_a, 'session_date' => '2026-11-12', 'content' => 'x', 'nonce' => PAS_Dashboard_Security::nonce( 'note_save' ) ) );
acheck( 'logged-in user without dashboard cap denied', false === $no_cap['success'], $no_cap['raw'] );

WP_CLI::log( '--- live note authorization ---' );

wp_set_current_user( $therapist_a );
$secret = 'یادداشت آزمون AJAX ' . $stamp;

$own = pas_ajax_call(
	'pas_dashboard_save_note',
	array(
		'case_id'      => $case_a,
		'session_date' => '2026-11-12',
		'content'      => $secret,
		'nonce'        => PAS_Dashboard_Security::nonce( 'note_save' ),
	)
);
acheck( 'own case note created via AJAX', true === $own['success'] && ! empty( $own['data']['note_id'] ), $own['raw'] );
$note_id = (int) ( $own['data']['note_id'] ?? 0 );

$foreign_save = pas_ajax_call(
	'pas_dashboard_save_note',
	array(
		'case_id'      => $case_b,
		'session_date' => '2026-11-12',
		'content'      => 'نفوذ',
		'nonce'        => PAS_Dashboard_Security::nonce( 'note_save' ),
	)
);
acheck( 'foreign case note refused via AJAX', false === $foreign_save['success'], $foreign_save['raw'] );

wp_set_current_user( $therapist_b );
$foreign_delete = pas_ajax_call(
	'pas_dashboard_delete_note',
	array(
		'case_id' => $case_a,
		'note_id' => $note_id,
		'nonce'   => PAS_Dashboard_Security::nonce( 'note_delete' ),
	)
);
acheck( 'foreign note delete refused via AJAX', false === $foreign_delete['success'], $foreign_delete['raw'] );

wp_set_current_user( $therapist_a );
$own_delete = pas_ajax_call(
	'pas_dashboard_delete_note',
	array(
		'case_id' => $case_a,
		'note_id' => $note_id,
		'nonce'   => PAS_Dashboard_Security::nonce( 'note_delete' ),
	)
);
acheck( 'own note delete allowed via AJAX', true === $own_delete['success'], $own_delete['raw'] );

$malformed = pas_ajax_call(
	'pas_dashboard_save_note',
	array(
		'case_id'      => 'abc',
		'session_date' => 'not-a-date',
		'content'      => 'x',
		'nonce'        => PAS_Dashboard_Security::nonce( 'note_save' ),
	)
);
acheck( 'malformed input refused via AJAX', false === $malformed['success'], $malformed['raw'] );

WP_CLI::log( '--- cleanup ---' );

$wpdb->delete( Pas_CF_Installer::table( 'notes' ), array( 'case_id' => $case_a ), array( '%d' ) );
$wpdb->delete( Pas_CF_Installer::table( 'notes' ), array( 'case_id' => $case_b ), array( '%d' ) );
$wpdb->delete( Pas_CF_Installer::table( 'cases' ), array( 'id' => $case_a ), array( '%d' ) );
$wpdb->delete( Pas_CF_Installer::table( 'cases' ), array( 'id' => $case_b ), array( '%d' ) );
require_once ABSPATH . 'wp-admin/includes/user.php';
wp_delete_user( $therapist_a );
wp_delete_user( $therapist_b );
wp_delete_user( $subscriber );

WP_CLI::log( '' );
WP_CLI::log( 'PASSED: ' . $GLOBALS['a_pass'] . '  FAILED: ' . $GLOBALS['a_fail'] );

exit( $GLOBALS['a_fail'] > 0 ? 1 : 0 );
