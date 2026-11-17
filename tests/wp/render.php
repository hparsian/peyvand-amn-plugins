<?php
/**
 * آزمون رندر ماژول‌ها — هر ماژول با کاربر واقعی رندر می‌شود تا خطای قالب،
 * متغیر تعریف‌نشده یا استثنا پیدا شود (بدون مرورگر).
 *
 *   wp eval-file tests/wp/render.php --path=/tmp/wp
 */

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "Run inside WordPress.\n" );
	exit( 1 );
}

$GLOBALS['r_pass'] = 0;
$GLOBALS['r_fail'] = 0;
$GLOBALS['r_warn'] = array();

function rcheck( $label, $condition, $detail = '' ) {
	if ( $condition ) {
		$GLOBALS['r_pass']++;
		WP_CLI::log( "PASS  $label" );
		return true;
	}

	$GLOBALS['r_fail']++;
	WP_CLI::log( 'FAIL  ' . $label . ( '' !== $detail ? "  ($detail)" : '' ) );
	return false;
}

set_error_handler(
	function ( $errno, $errstr, $errfile = '', $errline = 0 ) {
		if ( in_array( $errno, array( E_WARNING, E_NOTICE, E_USER_WARNING, E_USER_NOTICE, E_DEPRECATED ), true ) ) {
			$GLOBALS['r_warn'][] = $errstr . ' @ ' . basename( (string) $errfile ) . ':' . $errline;
		}
		return true;
	}
);

$render_all = function ( $label ) {
	$matrix = PAS_Dashboard_Permissions::matrix();
	$count  = 0;

	foreach ( $matrix as $id => $meta ) {
		$class = $meta['class'] ?? '';

		if ( '' === $class || ! class_exists( $class ) ) {
			rcheck( "$label: class exists for $id", false, $class );
			continue;
		}

		try {
			$module = new $class();
			$html   = $module->render();
			$ok     = is_string( $html ) && strlen( $html ) > 20;

			rcheck( "$label: renders $id", $ok, is_string( $html ) ? strlen( $html ) . ' bytes' : gettype( $html ) );
			$count += $ok ? 1 : 0;
		} catch ( Throwable $e ) {
			rcheck( "$label: renders $id", false, get_class( $e ) . ': ' . $e->getMessage() );
		}
	}

	return $count;
};

WP_CLI::log( '--- administrator (all modules) ---' );
wp_set_current_user( 1 );
$admin_rendered = $render_all( 'admin' );
rcheck( 'admin rendered all 11 modules', 11 === $admin_rendered, (string) $admin_rendered );

WP_CLI::log( '--- therapist (restricted modules) ---' );

$therapist = wp_insert_user(
	array(
		'user_login'   => 'pas_render_' . time(),
		'user_pass'    => wp_generate_password( 24 ),
		'user_email'   => 'pas_render_' . time() . '@example.test',
		'display_name' => 'درمانگر رندر',
		'role'         => 'pas_cf_therapist',
	)
);

if ( is_wp_error( $therapist ) ) {
	rcheck( 'therapist created', false, $therapist->get_error_message() );
} else {
	wp_set_current_user( (int) $therapist );
	$visible = PAS_Dashboard_Permissions::visible_modules();
	rcheck( 'therapist sees fewer modules than admin', count( $visible ) < 11, (string) count( $visible ) );
	rcheck( 'therapist cannot see settings', ! isset( $visible['settings'] ) );
	rcheck( 'therapist cannot see audit', ! isset( $visible['audit'] ) );
	rcheck( 'therapist can see case files', isset( $visible['case_files'] ) );

	$matrix             = PAS_Dashboard_Permissions::matrix();
	$therapist_rendered = 0;

	foreach ( array_keys( $visible ) as $id ) {
		$class = $matrix[ $id ]['class'] ?? '';

		if ( '' === $class || ! class_exists( $class ) ) {
			rcheck( "therapist: class exists for $id", false, $class );
			continue;
		}

		try {
			$module = new $class();
			$html   = $module->render();
			$ok     = is_string( $html ) && strlen( $html ) > 20;

			rcheck( "therapist: renders $id", $ok, is_string( $html ) ? strlen( $html ) . ' bytes' : gettype( $html ) );
			$therapist_rendered += $ok ? 1 : 0;
		} catch ( Throwable $e ) {
			rcheck( "therapist: renders $id", false, get_class( $e ) . ': ' . $e->getMessage() );
		}
	}

	rcheck( 'therapist rendered every visible module', $therapist_rendered === count( $visible ), $therapist_rendered . '/' . count( $visible ) );

	require_once ABSPATH . 'wp-admin/includes/user.php';
	wp_delete_user( (int) $therapist );
}

WP_CLI::log( '--- PHP notices during rendering ---' );
$relevant = array_values(
	array_filter(
		$GLOBALS['r_warn'],
		static function ( $w ) {
			return false !== strpos( $w, 'peyvand-amn-unified-dashboard' ) || false !== stripos( $w, 'undefined' );
		}
	)
);

rcheck( 'no plugin warnings/notices while rendering', 0 === count( $relevant ), implode( ' | ', array_slice( $relevant, 0, 5 ) ) );

WP_CLI::log( '' );
WP_CLI::log( 'PASSED: ' . $GLOBALS['r_pass'] . '  FAILED: ' . $GLOBALS['r_fail'] );

exit( $GLOBALS['r_fail'] > 0 ? 1 : 0 );
