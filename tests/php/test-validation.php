<?php
/**
 * تست‌های واحد PHP — برای محیطی که PHP دارد.
 *
 * این فایل بدون وردپرس هم اجرا می‌شود: توابع لازم حداقلی شبیه‌سازی شده‌اند و
 * فقط منطق خالص (اعتبارسنجی) آزموده می‌شود. اجرا:
 *
 *   php tests/php/test-validation.php
 */

define( 'ABSPATH', __DIR__ . '/' );

/* ثابت‌هایی که فایل اصلی افزونه تعریف می‌کند و اعتبارسنجی به آن‌ها تکیه دارد. */
define( 'PAS_DASHBOARD_VERSION', '2.0.0' );
define( 'PAS_DASHBOARD_FILE', __DIR__ . '/plugin.php' );
define( 'PAS_DASHBOARD_DIR', __DIR__ . '/' );
define( 'PAS_DASHBOARD_URL', 'http://example.test/' );
define( 'PAS_DASHBOARD_SLUG', 'pas-unified-dashboard' );
define( 'PAS_DASHBOARD_CAP', 'pas_dashboard_access' );
define( 'PAS_DASHBOARD_OPTION', 'pas_dashboard_settings' );
define( 'PAS_DASHBOARD_DB_VERSION', '1.0.0' );
define( 'PAS_DASHBOARD_MAX_PER_PAGE', 100 );

function sanitize_text_field( $value ) {
	return trim( preg_replace( '/[\r\n\t]+/', ' ', strip_tags( (string) $value ) ) );
}
function sanitize_textarea_field( $value ) {
	return trim( strip_tags( (string) $value ) );
}
function sanitize_key( $value ) {
	return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $value ) );
}
function sanitize_email( $value ) {
	return filter_var( (string) $value, FILTER_SANITIZE_EMAIL );
}
function is_email( $value ) {
	return (bool) filter_var( $value, FILTER_VALIDATE_EMAIL );
}
function absint( $value ) {
	return abs( (int) $value );
}
function esc_html__( $text, $domain = '' ) {
	return $text;
}
function esc_html( $text ) {
	return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
}

require_once dirname( __DIR__, 2 ) . '/plugins/peyvand-amn-unified-dashboard/includes/class-dashboard-validation.php';

$passed = 0;
$failed = 0;

function check( $label, $condition ) {
	global $passed, $failed;

	if ( $condition ) {
		$passed++;
		echo "PASS  $label\n";
	} else {
		$failed++;
		echo "FAIL  $label\n";
	}
}

/* id() */
check( 'id positive', PAS_Dashboard_Validation::id( '42' ) === 42 );
check( 'id zero on negative', PAS_Dashboard_Validation::id( '-5' ) === 0 );
check( 'id zero on junk', PAS_Dashboard_Validation::id( 'abc' ) === 0 );
check( 'id zero on array', PAS_Dashboard_Validation::id( array( 1 ) ) === 0 );

/* key() */
check( 'key strips unsafe', PAS_Dashboard_Validation::key( 'case_files!<x>' ) === 'case_filesx' );
check( 'key truncates', strlen( PAS_Dashboard_Validation::key( str_repeat( 'a', 100 ), 10 ) ) === 10 );

/* text() */
check( 'text strips tags', PAS_Dashboard_Validation::text( '<script>alert(1)</script>سلام' ) === 'alert(1)سلام' );
check( 'text truncates', mb_strlen( PAS_Dashboard_Validation::text( str_repeat( 'ب', 300 ), 20 ) ) === 20 );

/* date() */
check( 'date valid', PAS_Dashboard_Validation::date( '2026-11-17' ) === '2026-11-17' );
check( 'date invalid month', PAS_Dashboard_Validation::date( '2026-13-01' ) === '' );
check( 'date invalid day', PAS_Dashboard_Validation::date( '2026-02-30' ) === '' );
check( 'date injection rejected', PAS_Dashboard_Validation::date( '2026-11-17 OR 1=1' ) === '' );

/* mobile() */
check( 'mobile valid', PAS_Dashboard_Validation::mobile( '09121234567' ) === '09121234567' );
check( 'mobile invalid', PAS_Dashboard_Validation::mobile( '12345' ) === '' );

/* choice() */
check( 'choice allowed', PAS_Dashboard_Validation::choice( 'client_id', array( 'client_id', 'mobile' ), 'mobile' ) === 'client_id' );
check( 'choice fallback', PAS_Dashboard_Validation::choice( 'evil', array( 'client_id' ), 'client_id' ) === 'client_id' );

/* page()/per_page() */
$_GET['paged'] = '3';
check( 'page from query', PAS_Dashboard_Validation::page() === 3 );
check( 'per_page clamps low', PAS_Dashboard_Validation::per_page( 1 ) === 5 );

echo "\nPASSED: $passed\nFAILED: $failed\n";
exit( $failed > 0 ? 1 : 0 );
