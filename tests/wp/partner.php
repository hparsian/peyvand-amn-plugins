<?php
/**
 * آزمون مسیر پرونده‌ی زوج/پارتنر با پرونده‌ی لینک‌شده — با WP-CLI:
 *
 *   wp eval-file tests/wp/partner.php --path=/tmp/wpm
 *
 * پوشش:
 *   1. لینک دوطرفه با partner_phone، اتصال مجدد و جدا کردن لینک
 *   2. مرز دسترسی: درمانگر پرونده‌ی پارتنر را نمی‌بیند (نه از پل، نه از REST)
 *   3. عدم نشت متن یادداشت در نمای زوجی (حتی برای مدیر)
 *   4. رندر داشبورد: پیام «دسترسی تأیید نشده» و نبود PII پارتنر
 *   5. پل هرگز get_partner() را مستقیم صدا نمی‌زند (نگهبان ایستا)
 *
 * پیش‌نیاز: Core و Case File فعال، PAS_CORE_ENCRYPTION_KEY تنظیم‌شده.
 */

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "Run inside WordPress.\n" );
	exit( 1 );
}

$GLOBALS['p_pass'] = 0;
$GLOBALS['p_fail'] = 0;

function pcheck( $label, $condition, $detail = '' ) {
	if ( $condition ) {
		$GLOBALS['p_pass']++;
		WP_CLI::log( "PASS  $label" );
		return true;
	}

	$GLOBALS['p_fail']++;
	WP_CLI::log( 'FAIL  ' . $label . ( '' !== $detail ? "  ($detail)" : '' ) );
	return false;
}

global $wpdb;

WP_CLI::log( '--- dependencies ---' );

pcheck( 'Case File detected', PAS_Dashboard_Core_Bridge::has_case_file() );
pcheck( 'crypto configured', PAS_Dashboard_Core_Bridge::crypto_ready() );
pcheck( 'therapist role exists', null !== get_role( 'pas_cf_therapist' ) );

if ( $GLOBALS['p_fail'] > 0 ) {
	WP_CLI::log( 'ABORTED: prerequisites missing.' );
	exit( 1 );
}

WP_CLI::log( '--- fixtures ---' );

$stamp = time();

$make_therapist = function ( $label ) use ( $stamp ) {
	$id = wp_insert_user(
		array(
			'user_login'   => 'pas_pt_' . $label . '_' . $stamp,
			'user_pass'    => wp_generate_password( 24 ),
			'user_email'   => 'pas_pt_' . $label . '_' . $stamp . '@example.test',
			'display_name' => 'درمانگر پارتنر ' . $label,
			'role'         => 'pas_cf_therapist',
		)
	);

	return is_wp_error( $id ) ? 0 : (int) $id;
};

$therapist_a = $make_therapist( 'a' );
$therapist_b = $make_therapist( 'b' );

wp_set_current_user( 1 );

$phone_a = '0912' . wp_rand( 1000000, 9999999 );
$phone_b = '0913' . wp_rand( 1000000, 9999999 );
$phone_c = '0914' . wp_rand( 1000000, 9999999 );

$name_b = 'پارتنر محرمانه ' . $stamp;
$name_c = 'پرونده سوم ' . $stamp;

/* پرونده‌ی پارتنر باید پیش از لینک‌شدن وجود داشته باشد. */
$case_b = Pas_CF_Case_File::create(
	array(
		'phone'              => $phone_b,
		'full_name'          => $name_b,
		'assigned_therapist' => $therapist_b,
		'status'             => 'فعال',
	)
);

$case_a = Pas_CF_Case_File::create(
	array(
		'phone'              => $phone_a,
		'full_name'          => 'مراجع اصلی ' . $stamp,
		'assigned_therapist' => $therapist_a,
		'status'             => 'فعال',
		'partner_phone'      => $phone_b,
	)
);

$case_c = Pas_CF_Case_File::create(
	array(
		'phone'              => $phone_c,
		'full_name'          => $name_c,
		'assigned_therapist' => $therapist_b,
		'status'             => 'فعال',
	)
);

pcheck( 'three cases created', $case_a > 0 && $case_b > 0 && $case_c > 0, "$case_a/$case_b/$case_c" );

if ( $case_a <= 0 || $case_b <= 0 || $case_c <= 0 ) {
	WP_CLI::log( 'ABORTED: fixtures could not be created.' );
	exit( 1 );
}

WP_CLI::log( '--- link is bidirectional ---' );

$link_a = (int) Pas_CF_Case_File::get( $case_a )->partner_case_id;
$link_b = (int) Pas_CF_Case_File::get( $case_b )->partner_case_id;

pcheck( 'case A points to partner B', $case_b === $link_a, (string) $link_a );
pcheck( 'case B points back to case A', $case_a === $link_b, (string) $link_b );

WP_CLI::log( '--- notes on both sides ---' );

$self_secret   = 'یادداشت خودی ' . $stamp;
$partner_secret = 'یادداشت محرمانه‌ی پارتنر ' . $stamp;

wp_set_current_user( $therapist_a );
$note_a = PAS_Dashboard_Core_Bridge::add_note( $case_a, '2026-11-01', $self_secret, 1, array() );
pcheck( 'note on own case created', is_int( $note_a ) && $note_a > 0, var_export( $note_a, true ) );

wp_set_current_user( $therapist_b );
$note_b = PAS_Dashboard_Core_Bridge::add_note( $case_b, '2026-11-02', $partner_secret, 1, array() );
pcheck( 'note on partner case created', is_int( $note_b ) && $note_b > 0, var_export( $note_b, true ) );

WP_CLI::log( '--- therapist A: partner case is out of scope ---' );

wp_set_current_user( $therapist_a );

pcheck( 'A can open own case', is_object( PAS_Dashboard_Security::authorized_case( $case_a ) ) );

$foreign = PAS_Dashboard_Security::authorized_case( $case_b );
pcheck( 'A cannot open partner case', is_wp_error( $foreign ), is_object( $foreign ) ? 'LEAKED OBJECT' : '' );
pcheck(
	'partner case denial is forbidden',
	is_wp_error( $foreign ) && 'pas_dashboard_forbidden' === $foreign->get_error_code(),
	is_wp_error( $foreign ) ? $foreign->get_error_code() : ''
);
pcheck( 'A cannot access partner case directly', ! PAS_Dashboard_Security::can_access_case( $case_b ) );

$couple_a = PAS_Dashboard_Core_Bridge::couple_sessions( PAS_Dashboard_Core_Bridge::case_get( $case_a ), 60 );

pcheck( 'bridge knows a partner exists', ! empty( $couple_a['has_partner'] ) );
pcheck( 'A is not authorized to view partner', empty( $couple_a['can_view_partner'] ) );
pcheck( 'bridge withholds partner object from A', null === $couple_a['partner'], gettype( $couple_a['partner'] ) );
pcheck( 'security helper agrees: no partner view', ! PAS_Dashboard_Security::can_view_partner( PAS_Dashboard_Core_Bridge::case_get( $case_a ) ) );

$sides_a = array_map( static function ( $s ) { return (string) ( $s['side'] ?? '' ); }, (array) $couple_a['sessions'] );
pcheck( 'A sees only own sessions', ! in_array( 'partner', $sides_a, true ), implode( ',', $sides_a ) );
pcheck( 'A sees own session count = 1', 1 === count( $sides_a ), (string) count( $sides_a ) );

$serialized_a = wp_json_encode( $couple_a );
pcheck( 'couple payload has no note text', false === strpos( (string) $serialized_a, $partner_secret ) );
pcheck( 'couple payload has no partner phone', false === strpos( (string) $serialized_a, $phone_b ) );
pcheck( 'couple payload has no partner name', false === strpos( (string) $serialized_a, $name_b ) );
pcheck(
	'session projection has no content key',
	empty( $couple_a['sessions'] ) || ! array_key_exists( 'content', (array) $couple_a['sessions'][0] )
);

WP_CLI::log( '--- dashboard render as therapist A ---' );

$_GET['case_id'] = $case_a;
$html_a = ( new PAS_Dashboard_Module_Case_Files() )->render();
unset( $_GET['case_id'] );

pcheck( 'render is a string', is_string( $html_a ) && strlen( $html_a ) > 100, is_string( $html_a ) ? strlen( $html_a ) . ' bytes' : gettype( $html_a ) );
pcheck( 'render shows partner-access notice', false !== strpos( (string) $html_a, 'دسترسی شما به پرونده‌ی پارتنر تأیید نشده' ) );
pcheck( 'render never prints partner name', false === strpos( (string) $html_a, $name_b ) );
pcheck( 'render never prints partner phone', false === strpos( (string) $html_a, $phone_b ) );
pcheck( 'render never prints partner note', false === strpos( (string) $html_a, $partner_secret ) );
pcheck( 'render shows own note', false !== strpos( (string) $html_a, $self_secret ) );

WP_CLI::log( '--- REST boundary on partner case ---' );

$rest_own = rest_do_request( new WP_REST_Request( 'GET', '/pas-dashboard/v1/case/' . $case_a . '/notes' ) );
pcheck( 'REST own notes 200', 200 === $rest_own->get_status(), 'status ' . $rest_own->get_status() );

$rest_foreign = rest_do_request( new WP_REST_Request( 'GET', '/pas-dashboard/v1/case/' . $case_b . '/notes' ) );
pcheck( 'REST partner notes denied', in_array( $rest_foreign->get_status(), array( 403, 404 ), true ), 'status ' . $rest_foreign->get_status() );
pcheck( 'REST denial carries no note text', false === strpos( (string) wp_json_encode( $rest_foreign->get_data() ), $partner_secret ) );

WP_CLI::log( '--- therapist B: own case, partner still denied ---' );

wp_set_current_user( $therapist_b );

$couple_b = PAS_Dashboard_Core_Bridge::couple_sessions( PAS_Dashboard_Core_Bridge::case_get( $case_b ), 60 );
pcheck( 'B can open own case', is_object( PAS_Dashboard_Security::authorized_case( $case_b ) ) );
pcheck( 'B cannot open partner case', is_wp_error( PAS_Dashboard_Security::authorized_case( $case_a ) ) );
pcheck( 'B is not authorized to view partner', empty( $couple_b['can_view_partner'] ) );
pcheck( 'B sees no partner session', ! in_array( 'partner', array_map( static function ( $s ) { return (string) ( $s['side'] ?? '' ); }, (array) $couple_b['sessions'] ), true ) );

WP_CLI::log( '--- administrator: full couple view, still metadata only ---' );

wp_set_current_user( 1 );

$couple_admin = PAS_Dashboard_Core_Bridge::couple_sessions( PAS_Dashboard_Core_Bridge::case_get( $case_a ), 60 );
pcheck( 'admin sees partner authorized', ! empty( $couple_admin['can_view_partner'] ) );
pcheck( 'admin receives partner object', is_object( $couple_admin['partner'] ) && (int) $couple_admin['partner']->id === $case_b );

$sides_admin = array_map( static function ( $s ) { return (string) ( $s['side'] ?? '' ); }, (array) $couple_admin['sessions'] );
pcheck( 'admin sees both sides', in_array( 'self', $sides_admin, true ) && in_array( 'partner', $sides_admin, true ), implode( ',', $sides_admin ) );

$serialized_admin = wp_json_encode( $couple_admin );
pcheck( 'couple view never carries note text (even for admin)', false === strpos( (string) $serialized_admin, $partner_secret ) );
pcheck( 'couple view never carries own note text', false === strpos( (string) $serialized_admin, $self_secret ) );

$_GET['case_id'] = $case_a;
$html_admin = ( new PAS_Dashboard_Module_Case_Files() )->render();
unset( $_GET['case_id'] );

pcheck( 'admin render shows partner heading', is_string( $html_admin ) && false !== strpos( $html_admin, 'پارتنر:' ) );
pcheck( 'admin render shows partner case id', is_string( $html_admin ) && false !== strpos( $html_admin, '#' . $case_b ) );

WP_CLI::log( '--- relink, unlink, guards ---' );

wp_set_current_user( $therapist_a );
Pas_CF_Case_File::update( $case_a, array( 'phone' => $phone_a, 'partner_phone' => $phone_c ) );

pcheck( 'relink points A to C', $case_c === (int) Pas_CF_Case_File::get( $case_a )->partner_case_id, (string) (int) Pas_CF_Case_File::get( $case_a )->partner_case_id );
pcheck( 'relink links C back to A', $case_a === (int) Pas_CF_Case_File::get( $case_c )->partner_case_id );
pcheck( 'relink clears old partner B', 0 === (int) Pas_CF_Case_File::get( $case_b )->partner_case_id, (string) (int) Pas_CF_Case_File::get( $case_b )->partner_case_id );

Pas_CF_Case_File::update( $case_a, array( 'phone' => $phone_a, 'partner_phone' => $phone_a ) );
pcheck( 'self-link is ignored', $case_c === (int) Pas_CF_Case_File::get( $case_a )->partner_case_id );

Pas_CF_Case_File::update( $case_a, array( 'phone' => $phone_a, 'partner_phone' => '09990000000' ) );
pcheck( 'unknown phone leaves link untouched', $case_c === (int) Pas_CF_Case_File::get( $case_a )->partner_case_id );

Pas_CF_Case_File::update( $case_a, array( 'phone' => $phone_a, 'partner_phone' => '' ) );
pcheck( 'unlink clears A', 0 === (int) Pas_CF_Case_File::get( $case_a )->partner_case_id );
pcheck( 'unlink clears C', 0 === (int) Pas_CF_Case_File::get( $case_c )->partner_case_id );

$couple_unlinked = PAS_Dashboard_Core_Bridge::couple_sessions( PAS_Dashboard_Core_Bridge::case_get( $case_a ), 60 );
pcheck( 'unlinked case reports no partner', empty( $couple_unlinked['has_partner'] ) && null === $couple_unlinked['partner'] );

WP_CLI::log( '--- static guard: dashboard never calls get_partner() ---' );

$plugin_dir = dirname( __DIR__, 2 ) . '/plugins/peyvand-amn-unified-dashboard';
$offenders  = array();

if ( is_dir( $plugin_dir ) ) {
	$iterator = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $plugin_dir, FilesystemIterator::SKIP_DOTS ) );

	foreach ( $iterator as $file ) {
		if ( ! $file->isFile() || 'php' !== strtolower( $file->getExtension() ) ) {
			continue;
		}

		$body = (string) file_get_contents( $file->getPathname() );
		if ( preg_match( '/\\bget_partner\\s*\\(/', $body ) ) {
			$offenders[] = str_replace( $plugin_dir . '/', '', $file->getPathname() );
		}
	}
}

pcheck( 'no direct get_partner() in dashboard code', empty( $offenders ), implode( ',', $offenders ) );
pcheck( 'couple access goes through bridge', method_exists( 'PAS_Dashboard_Core_Bridge', 'couple_sessions' ) );

WP_CLI::log( '--- honest cleanup ---' );

wp_set_current_user( 1 );

foreach ( array( $case_a, $case_b, $case_c ) as $cleanup_case ) {
	$wpdb->delete( Pas_CF_Installer::table( 'notes' ), array( 'case_id' => $cleanup_case ), array( '%d' ) );
	$wpdb->delete( Pas_CF_Installer::table( 'cases' ), array( 'id' => $cleanup_case ), array( '%d' ) );
}

require_once ABSPATH . 'wp-admin/includes/user.php';
wp_delete_user( $therapist_a );
wp_delete_user( $therapist_b );

WP_CLI::log( '' );
WP_CLI::log( 'PASSED: ' . $GLOBALS['p_pass'] . '  FAILED: ' . $GLOBALS['p_fail'] );

exit( $GLOBALS['p_fail'] > 0 ? 1 : 0 );
