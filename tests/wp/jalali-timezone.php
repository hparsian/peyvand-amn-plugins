<?php
/**
 * آزمون timezone و زمان‌بندی شمسی داخل وردپرس — با WP-CLI اجرا می‌شود:
 *
 *   wp eval-file tests/wp/jalali-timezone.php --path=/tmp/wp
 *
 * چه چیزی را ثابت می‌کند:
 *   ۱) wp_date() زمان سایت را می‌دهد، در حالی که date() روی سرور UTC
 *      ساعت را جابه‌جا می‌کند — همان دلیلی که تولید محتوا از wp_date
 *      استفاده می‌کند.
 *   ۲) pas_jalali_datetime() روی زمان محلی سایت، همان تاریخ شمسی را
 *      می‌دهد که به‌طور مستقل محاسبه می‌شود.
 *   ۳) ACO_Admin::parse_scheduled_at() ورودی شمسی (ارقام لاتین/فارسی) و
 *      قالب قدیمی datetime-local را می‌پذیرد و مقدار نامعتبر را رد می‌کند.
 *
 * در پایان، timezone سایت به مقدار قبلی برمی‌گردد.
 */

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "This file must run inside WordPress (wp eval-file).\n" );
	exit( 1 );
}

$GLOBALS['tz_pass'] = 0;
$GLOBALS['tz_fail'] = 0;

function tzcheck( string $label, bool $condition, string $detail = '' ): void {
	if ( $condition ) {
		$GLOBALS['tz_pass']++;
		WP_CLI::log( "PASS  $label" );
		return;
	}

	$GLOBALS['tz_fail']++;
	WP_CLI::log( 'FAIL  ' . $label . ( $detail !== '' ? " — $detail" : '' ) );
}

$original_timezone = (string) get_option( 'timezone_string' );
$original_offset   = get_option( 'gmt_offset' );

update_option( 'timezone_string', 'Asia/Tehran' );
update_option( 'gmt_offset', 3.5 );

/* ── ۱) wp_date در برابر date ────────────────────────────────────────── */

$timestamp   = time();
$wp_local    = wp_date( 'Y-m-d H:i:s', $timestamp );
$php_local   = date( 'Y-m-d H:i:s', $timestamp );
$current     = current_time( 'mysql' );
$wp_vs_current = abs( strtotime( $wp_local ) - strtotime( $current ) );

tzcheck( 'wp_date با current_time هم‌زمان است (اختلاف < ۳ ثانیه)', $wp_vs_current <= 3, "اختلاف {$wp_vs_current}s" );
tzcheck( 'date() روی سرور UTC ساعت را جابه‌جا می‌کند (تفاوت با wp_date)', $php_local !== $wp_local, "date=$php_local wp_date=$wp_local" );

$diff_seconds = strtotime( $wp_local ) - strtotime( $php_local );
tzcheck( 'اختلاف date()/wp_date برابر آفست سایت است (۳:۳۰)', 12600 === $diff_seconds, "diff={$diff_seconds}s" );

/* ── ۲) نمایش شمسی روی زمان محلی سایت ────────────────────────────────── */

if ( class_exists( 'Pas_Core_Jalali' ) && function_exists( 'pas_jalali_datetime' ) ) {
	$expected = Pas_Core_Jalali::to_jalali_string( current_time( 'Y-m-d' ) ) . ' ' . current_time( 'H:i' );
	tzcheck(
		'pas_jalali_datetime روی زمان سایت، تاریخ شمسی درست می‌دهد',
		$expected === pas_jalali_datetime( current_time( 'mysql' ) ),
		'expected=' . $expected . ' got=' . pas_jalali_datetime( current_time( 'mysql' ) )
	);
} else {
	tzcheck( 'هستهٔ مشترک در دسترس نیست (رد شد)', true );
}

/* ── ۳) زمان‌بندی شمسی در تولید محتوا ────────────────────────────────── */

if ( class_exists( 'ACO_Admin' ) ) {
	tzcheck(
		'زمان‌بندی شمسی: 1405/08/29 14:30 → 2026-11-20 14:30:00',
		'2026-11-20 14:30:00' === ACO_Admin::parse_scheduled_at( '1405/08/29 14:30' ),
		(string) ACO_Admin::parse_scheduled_at( '1405/08/29 14:30' )
	);
	tzcheck(
		'زمان‌بندی با ارقام فارسی پذیرفته می‌شود',
		'2026-11-20 14:30:00' === ACO_Admin::parse_scheduled_at( '۱۴۰۵/۰۸/۲۹ ۱۴:۳۰' ),
		(string) ACO_Admin::parse_scheduled_at( '۱۴۰۵/۰۸/۲۹ ۱۴:۳۰' )
	);
	tzcheck(
		'قالب قدیمی datetime-local هنوز کار می‌کند',
		'2026-11-20 14:30:00' === ACO_Admin::parse_scheduled_at( '2026-11-20T14:30' ),
		(string) ACO_Admin::parse_scheduled_at( '2026-11-20T14:30' )
	);
	tzcheck(
		'تاریخ شمسی نامعتبر رد می‌شود',
		null === ACO_Admin::parse_scheduled_at( '1405/13/40 10:00' )
	);
	tzcheck(
		'ساعت نامعتبر رد می‌شود',
		null === ACO_Admin::parse_scheduled_at( '1405/08/29 25:00' )
	);
	tzcheck( 'ورودی خالی → null', null === ACO_Admin::parse_scheduled_at( '' ) );
} else {
	tzcheck( 'تولید محتوا فعال نیست — آزمون زمان‌بندی رد شد', true );
}

/* ── بازگرداندن timezone ─────────────────────────────────────────────── */

update_option( 'timezone_string', $original_timezone );
update_option( 'gmt_offset', $original_offset );

WP_CLI::log( '' );
WP_CLI::log( 'PASSED: ' . $GLOBALS['tz_pass'] . '  FAILED: ' . $GLOBALS['tz_fail'] );

exit( $GLOBALS['tz_fail'] > 0 ? 1 : 0 );
