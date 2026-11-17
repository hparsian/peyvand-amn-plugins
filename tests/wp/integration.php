<?php
/**
 * آزمون یکپارچگی واقعی با Core + Case File — با WP-CLI:
 *
 *   wp eval-file tests/wp/integration.php --path=/tmp/wp
 *
 * پیش‌نیاز: Core و Case File فعال، PAS_CORE_ENCRYPTION_KEY تنظیم‌شده.
 * دو درمانگر و دو پرونده ساخته می‌شود تا جداسازی دسترسی و رمزنگاری واقعی
 * یادداشت آزموده شود.
 */

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "Run inside WordPress.\n" );
	exit( 1 );
}

$GLOBALS['i_pass'] = 0;
$GLOBALS['i_fail'] = 0;

function icheck( $label, $condition, $detail = '' ) {
	if ( $condition ) {
		$GLOBALS['i_pass']++;
		WP_CLI::log( "PASS  $label" );
		return true;
	}

	$GLOBALS['i_fail']++;
	WP_CLI::log( 'FAIL  ' . $label . ( '' !== $detail ? "  ($detail)" : '' ) );
	return false;
}

global $wpdb;

WP_CLI::log( '--- dependencies ---' );

icheck( 'Core detected', PAS_Dashboard_Core_Bridge::has_core() );
icheck( 'Case File detected', PAS_Dashboard_Core_Bridge::has_case_file() );
icheck( 'crypto configured', PAS_Dashboard_Core_Bridge::crypto_ready() );
icheck( 'therapist role exists', null !== get_role( 'pas_cf_therapist' ) );

WP_CLI::log( '--- fixtures ---' );

$stamp = time();

$make_therapist = function ( $label ) use ( $stamp ) {
	$id = wp_insert_user(
		array(
			'user_login'   => 'pas_it_' . $label . '_' . $stamp,
			'user_pass'    => wp_generate_password( 24 ),
			'user_email'   => 'pas_it_' . $label . '_' . $stamp . '@example.test',
			'display_name' => 'درمانگر ' . $label,
			'role'         => 'pas_cf_therapist',
		)
	);

	return is_wp_error( $id ) ? 0 : (int) $id;
};

$therapist_a = $make_therapist( 'a' );
$therapist_b = $make_therapist( 'b' );

icheck( 'two therapists created', $therapist_a > 0 && $therapist_b > 0 );
icheck( 'therapist holds case-file cap', user_can( $therapist_a, PAS_CF_CAP ) );
icheck( 'therapist lacks view-all cap', ! user_can( $therapist_a, PAS_CF_CAP_ALL ) );

wp_set_current_user( 1 );

$phone_a = '0912' . wp_rand( 1000000, 9999999 );
$phone_b = '0913' . wp_rand( 1000000, 9999999 );

$case_a = Pas_CF_Case_File::create( array( 'phone' => $phone_a, 'assigned_therapist' => $therapist_a, 'status' => 'فعال' ) );
$case_b = Pas_CF_Case_File::create( array( 'phone' => $phone_b, 'assigned_therapist' => $therapist_b, 'status' => 'فعال' ) );

icheck( 'two cases created', $case_a > 0 && $case_b > 0, "$case_a/$case_b" );

WP_CLI::log( '--- dashboard authorization ---' );

wp_set_current_user( $therapist_a );

icheck( 'therapist can open dashboard', PAS_Dashboard_Security::can_access_dashboard() );
icheck( 'therapist has clinical access', PAS_Dashboard_Security::can_view_clinical() );
icheck( 'therapist cannot manage settings', ! PAS_Dashboard_Security::can_manage() );

$own = PAS_Dashboard_Security::authorized_case( $case_a );
icheck( 'own case authorized', is_object( $own ), is_wp_error( $own ) ? $own->get_error_code() : gettype( $own ) );

$foreign = PAS_Dashboard_Security::authorized_case( $case_b );
icheck( 'other therapist case denied', is_wp_error( $foreign ), is_object( $foreign ) ? 'LEAKED OBJECT' : '' );
icheck( 'denial is "forbidden"', is_wp_error( $foreign ) && 'pas_dashboard_forbidden' === $foreign->get_error_code(), is_wp_error( $foreign ) ? $foreign->get_error_code() : '' );

$listed = PAS_Dashboard_Core_Bridge::cases( $therapist_a, 50 );
$listed_ids = array_map( static function ( $c ) { return (int) $c->id; }, (array) $listed );
icheck( 'case list contains own case', in_array( $case_a, $listed_ids, true ) );
icheck( 'case list excludes foreign case', ! in_array( $case_b, $listed_ids, true ), implode( ',', $listed_ids ) );

wp_set_current_user( 1 );
icheck( 'admin can open any case', is_object( PAS_Dashboard_Security::authorized_case( $case_b ) ) );

$admin_cases    = (array) PAS_Dashboard_Core_Bridge::cases( 1, 100 );
$admin_case_ids = array_map( static function ( $c ) { return (int) $c->id; }, $admin_cases );

icheck( 'admin list includes the other therapist case', in_array( $case_b, $admin_case_ids, true ), count( $admin_case_ids ) . ' cases' );
icheck( 'admin list includes both fixtures', in_array( $case_a, $admin_case_ids, true ) && in_array( $case_b, $admin_case_ids, true ) );

WP_CLI::log( '--- encrypted note lifecycle (as owning therapist) ---' );

wp_set_current_user( $therapist_a );
icheck( 'notes are writable with key + cap', PAS_Dashboard_Security::notes_writable() );

$secret  = 'متن محرمانه‌ی جلسه ' . $stamp;
$note_id = PAS_Dashboard_Core_Bridge::add_note( $case_a, '2026-11-10', $secret, 1, array() );
icheck( 'note created', is_int( $note_id ) && $note_id > 0, var_export( $note_id, true ) );

$raw = $wpdb->get_var(
	$wpdb->prepare(
		'SELECT content_encrypted FROM ' . Pas_CF_Installer::table( 'notes' ) . ' WHERE id = %d',
		$note_id
	)
);
icheck( 'note stored encrypted at rest', is_string( $raw ) && '' !== $raw && false === strpos( $raw, $secret ), substr( (string) $raw, 0, 24 ) );

$notes = PAS_Dashboard_Core_Bridge::notes( $case_a );
icheck( 'note listed for own case', 1 === count( $notes ), (string) count( $notes ) );
icheck( 'note decrypts for authorized therapist', ! empty( $notes ) && $secret === $notes[0]->content );

$updated = PAS_Dashboard_Core_Bridge::update_note( $note_id, $case_a, '2026-11-11', $secret . ' (ویرایش)' );
icheck( 'note update succeeds', (bool) $updated, var_export( $updated, true ) );

$history = PAS_Dashboard_Core_Bridge::note_history( $note_id );
icheck( 'note history recorded', is_array( $history ) && count( $history ) >= 1, (string) count( (array) $history ) );

$cross = PAS_Dashboard_Core_Bridge::update_note( $note_id, $case_b, '2026-11-11', 'نفوذ' );
icheck( 'update with wrong case id refused', ! $cross, var_export( $cross, true ) );

$deleted = PAS_Dashboard_Core_Bridge::delete_note( $note_id, $case_a );
icheck( 'note delete succeeds', (bool) $deleted, var_export( $deleted, true ) );

$after_delete = PAS_Dashboard_Core_Bridge::notes( $case_a );
icheck( 'deleted note gone from list', 0 === count( $after_delete ), (string) count( $after_delete ) );

WP_CLI::log( '--- REST surface on real cases ---' );

wp_set_current_user( $therapist_a );
$own_res = rest_do_request( new WP_REST_Request( 'GET', '/pas-dashboard/v1/case/' . $case_a . '/notes' ) );
icheck( 'REST own notes 200', 200 === $own_res->get_status(), 'status ' . $own_res->get_status() );

$foreign_res = rest_do_request( new WP_REST_Request( 'GET', '/pas-dashboard/v1/case/' . $case_b . '/notes' ) );
icheck( 'REST foreign notes denied', in_array( $foreign_res->get_status(), array( 403, 404 ), true ), 'status ' . $foreign_res->get_status() );

$summary = rest_do_request( new WP_REST_Request( 'GET', '/pas-dashboard/v1/summary' ) );
icheck( 'REST summary 200 for therapist', 200 === $summary->get_status(), 'status ' . $summary->get_status() );

WP_CLI::log( '--- honest cleanup ---' );

$wpdb->delete( Pas_CF_Installer::table( 'notes' ), array( 'case_id' => $case_a ), array( '%d' ) );
$wpdb->delete( Pas_CF_Installer::table( 'cases' ), array( 'id' => $case_a ), array( '%d' ) );
$wpdb->delete( Pas_CF_Installer::table( 'cases' ), array( 'id' => $case_b ), array( '%d' ) );
require_once ABSPATH . 'wp-admin/includes/user.php';
wp_delete_user( $therapist_a );
wp_delete_user( $therapist_b );

WP_CLI::log( '' );
WP_CLI::log( 'PASSED: ' . $GLOBALS['i_pass'] . '  FAILED: ' . $GLOBALS['i_fail'] );

exit( $GLOBALS['i_fail'] > 0 ? 1 : 0 );
