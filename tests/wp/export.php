<?php
/**
 * آزمون واقعی خروجی CSV — فرایند پس از چاپ CSV با exit پایان می‌یابد،
 * بنابراین خروجی را شل به فایل می‌برد و همان‌جا بررسی می‌کند.
 *
 *   wp eval-file tests/wp/export.php --path=/tmp/wp > /tmp/export.csv
 */

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "Run inside WordPress.\n" );
	exit( 1 );
}

global $wpdb;

$stamp = time();

$therapist = wp_insert_user(
	array(
		'user_login'   => 'pas_csv_' . $stamp,
		'user_pass'    => wp_generate_password( 24 ),
		'user_email'   => 'pas_csv_' . $stamp . '@example.test',
		// نام نمایشی عمداً با = شروع می‌شود تا خنثی‌سازی فرمول آزموده شود.
		'display_name' => '=2+2',
		'role'         => 'pas_cf_therapist',
	)
);

$phone = '0912' . wp_rand( 1000000, 9999999 );

wp_set_current_user( 1 );

$case_id = Pas_CF_Case_File::create(
	array(
		'phone'              => $phone,
		'assigned_therapist' => (int) $therapist,
		'status'             => 'فعال',
	)
);

fwrite( STDERR, "FIXTURE case_id=$case_id phone=$phone therapist=$therapist\n" );

// قلاب admin_post فقط روی admin_init ثبت می‌شود و WP-CLI آن را اجرا نمی‌کند.
PAS_Dashboard_Export::init();

$_REQUEST['_pasnonce'] = PAS_Dashboard_Security::nonce( 'export_cases' );
$_POST['_pasnonce']    = $_REQUEST['_pasnonce'];

// هندلر در پایان exit می‌کند؛ عمداً همین‌جا صدا زده می‌شود.
do_action( 'admin_post_pas_dashboard_export_cases' );
