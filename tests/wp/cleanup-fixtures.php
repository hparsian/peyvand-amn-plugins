<?php
/**
 * پاک‌سازی قالب‌های آزمون: پرونده‌های ساخته‌شده با پیشوند موبایل ۰۹۱۲/۰۹۱۳
 * که در آزمون‌ها ساخته شده‌اند و کاربران آزمونی.
 *
 *   wp eval-file tests/wp/cleanup-fixtures.php --path=/tmp/wp
 */

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "Run inside WordPress.\n" );
	exit( 1 );
}

global $wpdb;

$cases_table = $wpdb->prefix . 'pas_cf_cases';
$notes_table = $wpdb->prefix . 'pas_cf_notes';
$history     = $wpdb->prefix . 'pas_cf_note_history';

$rows = $wpdb->get_results( "SELECT id, phone, assigned_therapist FROM $cases_table WHERE phone LIKE '0912%' OR phone LIKE '0913%' LIMIT 50" );

$removed = 0;

foreach ( (array) $rows as $row ) {
	$wpdb->delete( $notes_table, array( 'case_id' => (int) $row->id ), array( '%d' ) );
	$wpdb->delete( $cases_table, array( 'id' => (int) $row->id ), array( '%d' ) );
	$removed++;
}

require_once ABSPATH . 'wp-admin/includes/user.php';

$users = get_users( array( 'search' => 'pas_*', 'search_columns' => array( 'user_login' ), 'number' => 50 ) );

foreach ( $users as $user ) {
	if ( preg_match( '/^pas_(it|ajax|csv|render|smoke)_/', $user->user_login ) ) {
		wp_delete_user( (int) $user->ID );
	}
}

echo "cases_removed=$removed fixtures_users=" . count( $users ) . "\n";
