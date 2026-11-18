<?php
/**
 * آزمون اعتبارسنجی فرم ارزیابی مراجع (پنل ورودی مراجع) — با WP-CLI:
 *
 *   wp eval-file tests/wp/intake-validation.php --path=/tmp/wp
 *
 * متد validate() خصوصی است و از طریق Reflection صدا زده می‌شود تا قاعده‌ها
 * بدون ارسال AJAX و بدون ساخت رکورد آزموده شوند. تمرکز روی باگ واقعیِ
 * تاریخ تولد آینده است: diff() قدر مطلق می‌دهد، پس بدون مقایسهٔ مستقیم با
 * امروز، تاریخ آینده سن ۰ برمی‌گرداند و پذیرفته می‌شد.
 */

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "This file must run inside WordPress (wp eval-file).\n" );
	exit( 1 );
}

if ( ! class_exists( 'ZoojDarman\\ClientPortal\\Intake' ) ) {
	WP_CLI::log( 'SKIP  کلاس Intake در دسترس نیست (پنل مراجعان فعال نیست).' );
	exit( 0 );
}

$GLOBALS['iv_pass'] = 0;
$GLOBALS['iv_fail'] = 0;

function ivcheck( string $label, bool $condition, string $detail = '' ): void {
	if ( $condition ) {
		$GLOBALS['iv_pass']++;
		WP_CLI::log( "PASS  $label" );
		return;
	}

	$GLOBALS['iv_fail']++;
	WP_CLI::log( 'FAIL  ' . $label . ( $detail !== '' ? " — $detail" : '' ) );
}

$intake   = new \ZoojDarman\ClientPortal\Intake( new \ZoojDarman\ClientPortal\Security() );
$validate = new ReflectionMethod( \ZoojDarman\ClientPortal\Intake::class, 'validate' );
$validate->setAccessible( true );
$run = static fn( array $overrides ): array|string => $validate->invoke( $intake, array_merge( [
	'full_name' => 'مراجع آزمایشی',
	'birth_date' => '1370/05/21',
	'gender'     => 'زن',
	'marital'    => 'مجرد',
	'children'   => '0',
	'education'  => 'لیسانس',
	'phone'      => '09121234567',
	'address'    => 'تهران',
	'confirm'    => '1',
], $overrides ) );

/* ── تاریخ تولد ──────────────────────────────────────────────────────── */

$result = $run( [ 'birth_date' => '1405/10/01' ] );
ivcheck( 'تاریخ تولد آینده رد می‌شود', is_string( $result ) && str_contains( $result, 'آینده' ), is_string( $result ) ? $result : 'پذیرفته شد' );

$next_year = Pas_Core_Jalali::to_jalali_string( wp_date( 'Y-m-d', strtotime( '+1 year' ) ) );
$result    = $run( [ 'birth_date' => $next_year ] );
ivcheck( 'تاریخ تولد یک سال آینده هم رد می‌شود', is_string( $result ) && str_contains( $result, 'آینده' ), is_string( $result ) ? $result : 'پذیرفته شد' );

$result = $run( [ 'birth_date' => Pas_Core_Jalali::to_jalali_string( wp_date( 'Y-m-d' ) ) ] );
ivcheck( 'تاریخ تولد امروز (سن ۰) پذیرفته می‌شود', is_array( $result ), is_string( $result ) ? $result : '' );

$result = $run( [ 'birth_date' => '1284/01/01' ] );
ivcheck( 'سن بیش از ۱۲۰ سال رد می‌شود', is_string( $result ) );

$result = $run( [ 'birth_date' => '1405/13/40' ] );
ivcheck( 'تاریخ نامعتبر (ماه ۱۳) رد می‌شود', is_string( $result ) && str_contains( $result, 'معتبر نیست' ) );

$result = $run( [ 'birth_date' => '۱۳۷۰/۰۵/۲۱' ] );
ivcheck( 'ارقام فارسی در تاریخ تولد پذیرفته می‌شود', is_array( $result ) && '1370/05/21' === $result['birth_date'] );

$result = $run( [] );
ivcheck( 'سن برای تاریخ معتبر محاسبه می‌شود', is_array( $result ) && is_int( $result['age'] ) && $result['age'] > 30 && $result['age'] < 40, is_array( $result ) ? (string) $result['age'] : '' );

/* ── بقیهٔ فیلدهای اجباری ─────────────────────────────────────────────── */

$cases = [
	'نام کوتاه'             => [ [ 'full_name' => 'ا' ], 'نام و نام خانوادگی' ],
	'جنسیت نامعتبر'         => [ [ 'gender' => 'ناموجود' ], 'جنسیت' ],
	'وضعیت تاهل نامعتبر'    => [ [ 'marital' => 'ناموجود' ], 'وضعیت تاهل' ],
	'فرزندان غیرعددی'       => [ [ 'children' => 'دو' ], 'فرزندان' ],
	'فرزندان بیش از ۲۰'     => [ [ 'children' => '25' ], 'فرزندان' ],
	'تحصیلات نامعتبر'       => [ [ 'education' => 'ناموجود' ], 'تحصیلات' ],
	'موبایل نامعتبر'        => [ [ 'phone' => '12345' ], 'شماره تماس' ],
	'آدرس خالی'             => [ [ 'address' => '' ], 'آدرس' ],
	'بدون تأیید'            => [ [ 'confirm' => '' ], 'تأیید' ],
	'گزینهٔ سلامت نامعتبر'  => [ [ 'med_history' => 'ناموجود' ], 'معتبر نیست' ],
];

foreach ( $cases as $label => [ $override, $needle ] ) {
	$result = $run( $override );
	ivcheck( "رد: $label", is_string( $result ) && str_contains( $result, $needle ), is_string( $result ) ? $result : 'پذیرفته شد' );
}

$result = $run( [ 'med_history' => 'بله', 'med_details' => 'توضیح' ] );
ivcheck( 'توضیح سلامت با انتخاب «بله» نگه داشته می‌شود', is_array( $result ) && 'توضیح' === $result['med_details'] );
$result = $run( [ 'med_history' => 'خیر', 'med_details' => 'باید پاک شود' ] );
ivcheck( 'توضیح سلامت با انتخاب «خیر» پاک می‌شود', is_array( $result ) && '' === $result['med_details'] );

$result = $run( [ 'phone' => '۰۹۱۲۱۲۳۴۵۶۷' ] );
ivcheck( 'موبایل با ارقام فارسی و ۰ ابتدایی ذخیره می‌شود', is_array( $result ) && '09121234567' === $result['phone'] );

$result = $run( [] );
ivcheck( 'تاریخ فرم شمسی و امروزِ سایت است', is_array( $result ) && $result['form_date'] === Pas_Core_Jalali::to_jalali_string( wp_date( 'Y-m-d' ) ) );

WP_CLI::log( '' );
WP_CLI::log( 'PASSED: ' . $GLOBALS['iv_pass'] . '  FAILED: ' . $GLOBALS['iv_fail'] );
exit( $GLOBALS['iv_fail'] > 0 ? 1 : 0 );
