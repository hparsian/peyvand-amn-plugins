<?php
/**
 * آزمون سرتاسری CRUD یادداشت از مسیر واقعی AJAX.
 *
 * چرخهٔ ثبت ← ویرایش ← حذف ← بازگردانی را می‌سنجد و در هر گام بررسی می‌کند که
 * رخداد ممیزی با نام قراردادی (`note.create` و نه `notecreate`) ثبت شود و
 * تلاش درمانگر دیگر روی پروندهٔ دیگری با نتیجهٔ `denied` در ممیزی بماند.
 *
 *   wp eval-file tests/wp/note-crud.php --path=/tmp/wp
 */

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "Run inside WordPress.\n" );
	exit( 1 );
}

if ( ! defined( 'DOING_AJAX' ) ) {
	define( 'DOING_AJAX', true );
}

$GLOBALS['nc_pass'] = 0;
$GLOBALS['nc_fail'] = 0;

function ncheck( $label, $condition, $detail = '' ) {
	if ( $condition ) {
		$GLOBALS['nc_pass']++;
		WP_CLI::log( "PASS  $label" );
		return true;
	}

	$GLOBALS['nc_fail']++;
	WP_CLI::log( 'FAIL  ' . $label . ( '' !== $detail ? "  ($detail)" : '' ) );
	return false;
}

function nc_die_handler( $message = '', $title = '', $args = array() ) {
	throw new RuntimeException( 'wp_die' );
}

foreach ( array( 'wp_die_handler', 'wp_die_ajax_handler', 'wp_die_json_handler', 'wp_die_jsonp_handler', 'wp_die_xml_handler', 'wp_die_xmlrpc_handler' ) as $nc_die_filter ) {
	add_filter( $nc_die_filter, static function () { return 'nc_die_handler'; } );
}

/** @return array{success:bool|null,data:mixed,raw:string} */
function nc_ajax( $action, array $post ) {
	$_POST    = $post;
	$_REQUEST = $post;
	$raw      = '';

	ob_start();
	try {
		do_action( 'wp_ajax_' . $action );
	} catch ( Throwable $e ) {
		// wp_send_json پیش از wp_die خروجی را echo کرده است.
	}
	$raw = (string) ob_get_clean();

	$decoded = json_decode( $raw, true );
	$success = ( is_array( $decoded ) && array_key_exists( 'success', $decoded ) ) ? (bool) $decoded['success'] : null;

	return array( 'success' => $success, 'data' => $decoded['data'] ?? null, 'raw' => $raw );
}

/** آخرین ردیف ممیزی برای یک نام رخداد. */
function nc_last_audit( $action ) {
	global $wpdb;

	$table = PAS_Dashboard_Database::audit_table();

	return $wpdb->get_row(
		$wpdb->prepare( "SELECT action, object_type, object_id, result FROM $table WHERE action = %s ORDER BY id DESC LIMIT 1", $action )
	);
}

global $wpdb;

$notes_table   = Pas_CF_Installer::table( 'notes' );
$history_table = Pas_CF_Installer::table( 'note_history' );
$audit_table   = PAS_Dashboard_Database::audit_table();
$stamp         = time();

WP_CLI::log( '--- fixtures ---' );

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

$therapist_a = $make_user( 'pas_nc_a', 'pas_cf_therapist' );
$therapist_b = $make_user( 'pas_nc_b', 'pas_cf_therapist' );

wp_set_current_user( 1 );
$case_a = (int) Pas_CF_Case_File::create( array( 'phone' => '0912' . wp_rand( 1000000, 9999999 ), 'assigned_therapist' => $therapist_a, 'status' => 'فعال' ) );
$case_b = (int) Pas_CF_Case_File::create( array( 'phone' => '0913' . wp_rand( 1000000, 9999999 ), 'assigned_therapist' => $therapist_b, 'status' => 'فعال' ) );

ncheck( 'fixtures created', $therapist_a > 0 && $therapist_b > 0 && $case_a > 0 && $case_b > 0 );

WP_CLI::log( '--- create ---' );

wp_set_current_user( $therapist_a );
$secret = 'یادداشت جلسه ' . $stamp;

$created = nc_ajax(
	'pas_dashboard_save_note',
	array(
		'case_id'      => $case_a,
		'session_date' => '2026-11-12',
		'content'      => $secret,
		'nonce'        => PAS_Dashboard_Security::nonce( 'note_save' ),
	)
);
$note_id = (int) ( $created['data']['note_id'] ?? 0 );

ncheck( 'note created through the AJAX handler', true === $created['success'] && $note_id > 0, $created['raw'] );

$row = $wpdb->get_row( $wpdb->prepare( "SELECT content_encrypted FROM $notes_table WHERE id = %d", $note_id ) );
ncheck( 'note stored as ciphertext', $row && '' !== (string) $row->content_encrypted && false === strpos( (string) $row->content_encrypted, $secret ), $row ? substr( (string) $row->content_encrypted, 0, 24 ) : 'missing row' );

$audit = nc_last_audit( 'note.create' );
ncheck( 'audit records note.create with the contract name', $audit && 'note' === $audit->object_type && (int) $audit->object_id === $note_id && 'ok' === $audit->result, $audit ? $audit->action . '/' . $audit->result : 'no row' );

$legacy = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $audit_table WHERE action = %s", 'notecreate' ) );
ncheck( 'no dot-stripped audit action remains', 0 === (int) $legacy, (string) $legacy );

WP_CLI::log( '--- update ---' );

$updated_text = $secret . ' — ویرایش‌شده';
$updated      = nc_ajax(
	'pas_dashboard_save_note',
	array(
		'case_id'      => $case_a,
		'note_id'      => $note_id,
		'session_date' => '2026-11-12',
		'content'      => $updated_text,
		'nonce'        => PAS_Dashboard_Security::nonce( 'note_save' ),
	)
);
ncheck( 'note updated through the AJAX handler', true === $updated['success'], $updated['raw'] );

$audit = nc_last_audit( 'note.update' );
ncheck( 'audit records note.update', $audit && (int) $audit->object_id === $note_id && 'ok' === $audit->result, $audit ? $audit->action : 'no row' );

$snapshots = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $history_table WHERE note_id = %d AND case_id = %d", $note_id, $case_a ) );
ncheck( 'update leaves a version snapshot', $snapshots > 0, (string) $snapshots );

$row = $wpdb->get_row( $wpdb->prepare( "SELECT content_encrypted FROM $notes_table WHERE id = %d", $note_id ) );
ncheck( 'edited text is not stored in plaintext', $row && false === strpos( (string) $row->content_encrypted, $updated_text ) );

WP_CLI::log( '--- foreign therapist denied and audited ---' );

wp_set_current_user( $therapist_b );

$foreign_save = nc_ajax(
	'pas_dashboard_save_note',
	array(
		'case_id'      => $case_a,
		'note_id'      => $note_id,
		'session_date' => '2026-11-12',
		'content'      => 'نفوذ',
		'nonce'        => PAS_Dashboard_Security::nonce( 'note_save' ),
	)
);
ncheck( 'foreign save refused', false === $foreign_save['success'], $foreign_save['raw'] );
$audit = nc_last_audit( 'note.write' );
ncheck( 'foreign save is audited as denied', $audit && 'denied' === $audit->result && 'note' === $audit->object_type, $audit ? $audit->result : 'no row' );

$foreign_delete = nc_ajax(
	'pas_dashboard_delete_note',
	array(
		'case_id' => $case_a,
		'note_id' => $note_id,
		'nonce'   => PAS_Dashboard_Security::nonce( 'note_delete' ),
	)
);
ncheck( 'foreign delete refused', false === $foreign_delete['success'], $foreign_delete['raw'] );
$audit = nc_last_audit( 'note.delete' );
ncheck( 'foreign delete is audited as denied', $audit && 'denied' === $audit->result, $audit ? $audit->result : 'no row' );

$foreign_restore = nc_ajax(
	'pas_dashboard_restore_note',
	array(
		'case_id'    => $case_a,
		'history_id' => 1,
		'nonce'      => PAS_Dashboard_Security::nonce( 'note_restore' ),
	)
);
ncheck( 'foreign restore refused', false === $foreign_restore['success'], $foreign_restore['raw'] );
$audit = nc_last_audit( 'note.restore' );
ncheck( 'foreign restore is audited as denied', $audit && 'denied' === $audit->result, $audit ? $audit->result : 'no row' );

$still_there = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $notes_table WHERE id = %d AND case_id = %d", $note_id, $case_a ) );
ncheck( 'foreign attempts left the note untouched', 1 === $still_there, (string) $still_there );

WP_CLI::log( '--- delete ---' );

wp_set_current_user( $therapist_a );
$deleted = nc_ajax(
	'pas_dashboard_delete_note',
	array(
		'case_id' => $case_a,
		'note_id' => $note_id,
		'nonce'   => PAS_Dashboard_Security::nonce( 'note_delete' ),
	)
);
ncheck( 'note deleted through the AJAX handler', true === $deleted['success'], $deleted['raw'] );

$audit = nc_last_audit( 'note.delete' );
ncheck( 'audit records note.delete as ok', $audit && 'ok' === $audit->result && (int) $audit->object_id === $note_id, $audit ? $audit->result : 'no row' );

$gone = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $notes_table WHERE id = %d", $note_id ) );
ncheck( 'deleted note is gone from the notes table', 0 === $gone, (string) $gone );

$deleted_rows = PAS_Dashboard_Core_Bridge::deleted_notes( $case_a );
ncheck( 'deleted note is offered for restore', count( $deleted_rows ) > 0, (string) count( $deleted_rows ) );
$history_id = $deleted_rows ? (int) $deleted_rows[0]->id : 0;

WP_CLI::log( '--- restore ---' );

$restored = nc_ajax(
	'pas_dashboard_restore_note',
	array(
		'case_id'    => $case_a,
		'history_id' => $history_id,
		'nonce'      => PAS_Dashboard_Security::nonce( 'note_restore' ),
	)
);
ncheck( 'note restored through the AJAX handler', true === $restored['success'], $restored['raw'] );

$audit = nc_last_audit( 'note.restore' );
ncheck( 'audit records note.restore as ok', $audit && 'ok' === $audit->result && (int) $audit->object_id === $history_id, $audit ? $audit->result : 'no row' );

$restored_row = $wpdb->get_row( $wpdb->prepare( "SELECT content_encrypted FROM $notes_table WHERE case_id = %d ORDER BY id DESC LIMIT 1", $case_a ) );
ncheck( 'restored note is present and encrypted', $restored_row && '' !== (string) $restored_row->content_encrypted && false === strpos( (string) $restored_row->content_encrypted, $updated_text ) );

$ok_audits = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $audit_table WHERE object_type = %s AND action IN ('note.create','note.update','note.delete','note.restore') AND result = 'ok'", 'note' ) );
ncheck( 'all four note operations are audited', $ok_audits >= 4, (string) $ok_audits );

WP_CLI::log( '--- cleanup ---' );

$wpdb->delete( $history_table, array( 'case_id' => $case_a ), array( '%d' ) );
$wpdb->delete( $notes_table, array( 'case_id' => $case_a ), array( '%d' ) );
$wpdb->delete( $notes_table, array( 'case_id' => $case_b ), array( '%d' ) );
$wpdb->delete( $history_table, array( 'case_id' => $case_b ), array( '%d' ) );
$wpdb->delete( Pas_CF_Installer::table( 'cases' ), array( 'id' => $case_a ), array( '%d' ) );
$wpdb->delete( Pas_CF_Installer::table( 'cases' ), array( 'id' => $case_b ), array( '%d' ) );
require_once ABSPATH . 'wp-admin/includes/user.php';
wp_delete_user( $therapist_a );
wp_delete_user( $therapist_b );

WP_CLI::log( '' );
WP_CLI::log( 'PASSED: ' . $GLOBALS['nc_pass'] . '  FAILED: ' . $GLOBALS['nc_fail'] );

exit( $GLOBALS['nc_fail'] > 0 ? 1 : 0 );
