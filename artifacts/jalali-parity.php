<?php
/**
 * مقایسهٔ پیاده‌سازی شمسی هسته با پرونده‌های بالینی و پنل مراجعان.
 * برای هر روز از ۱۳۰۰/۰۱/۰۱ تا ۱۴۵۰/۱۲/۲۹:
 *   - تبدیل میلادی→شمسی در سه پیاده‌سازی باید یکی باشد
 *   - تبدیل برگشت شمسی→میلادی باید همان تاریخ اول باشد
 *
 * اجرا: php artifacts/jalali-parity.php
 */
define( 'ABSPATH', '/tmp/' );

$root = dirname( __DIR__ );

require $root . '/plugins/pivand-amn-core/includes/class-jalali.php';
require $root . '/plugins/pivand-amn-case-file/includes/class-jalali.php';
require $root . '/plugins/pivand-amn-client-portal/includes/class-jalali.php';

$start = new DateTimeImmutable( '1921-03-21', new DateTimeZone( 'UTC' ) );
$end   = new DateTimeImmutable( '2072-03-19', new DateTimeZone( 'UTC' ) );

$days         = 0;
$mismatch     = [];
$backMismatch = [];

for ( $d = $start; $d <= $end; $d = $d->modify( '+1 day' ) ) {
	$g = $d->format( 'Y-m-d' );
	$days++;

	$core   = Pas_Core_Jalali::to_jalali_string( $g );
	$cf     = Pas_CF_Jalali::to_jalali_string( $g );
	$portal = \ZoojDarman\ClientPortal\Jalali::gregorian_to_jalali_ymd( $g );

	if ( $core !== $cf || $core !== $portal ) {
		$mismatch[] = [ $g, $core, $cf, $portal ];
	}

	$bCore   = Pas_Core_Jalali::jalali_string_to_gregorian_string( $core );
	$bCf     = Pas_CF_Jalali::jalali_string_to_gregorian_string( $core );
	$bPortal = \ZoojDarman\ClientPortal\Jalali::jalali_string_to_gregorian_string( $core );

	if ( $bCore !== $g || $bCf !== $g || $bPortal !== $g ) {
		$backMismatch[] = [ $g, $core, $bCore, $bCf, $bPortal ];
	}
}

printf( "روزهای بررسی‌شده: %d\n", $days );
printf( "اختلاف میلادی→شمسی: %d\n", count( $mismatch ) );
printf( "اختلاف شمسی→میلادی: %d\n", count( $backMismatch ) );

foreach ( array_slice( $mismatch, 0, 5 ) as $row ) {
	printf( "  G2J %s | core=%s cf=%s portal=%s\n", ...$row );
}
foreach ( array_slice( $backMismatch, 0, 5 ) as $row ) {
	printf( "  J2G %s | j=%s core=%s cf=%s portal=%s\n", ...$row );
}

$refs = [
	[ '1979-02-11', '1357/11/22', '22 بهمن ۱۳۵۷' ],
	[ '2001-03-21', '1380/01/01', 'نوروز ۱۳۸۰' ],
	[ '2024-03-20', '1403/01/01', 'نوروز ۱۴۰۳' ],
	[ '2026-03-21', '1405/01/01', 'نوروز ۱۴۰۵' ],
	[ '2025-12-21', '1404/09/30', 'یلدای ۱۴۰۴' ],
];
echo "\nتاریخ‌های مرجع:\n";
foreach ( $refs as [ $g, $expect, $label ] ) {
	$core   = Pas_Core_Jalali::to_jalali_string( $g );
	$cf     = Pas_CF_Jalali::to_jalali_string( $g );
	$portal = \ZoojDarman\ClientPortal\Jalali::gregorian_to_jalali_ymd( $g );
	printf( "  %-14s %-12s core=%-11s cf=%-11s portal=%-11s %s\n", $label, $g, $core, $cf, $portal, ( $core === $expect && $cf === $expect && $portal === $expect ) ? 'OK' : 'MISMATCH' );
}
