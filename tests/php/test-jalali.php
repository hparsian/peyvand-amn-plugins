<?php
/**
 * آزمون هم‌ارزی تبدیل شمسی↔میلادی در کل اکوسیستم.
 *
 * این فایل بدون وردپرس اجرا می‌شود و سه پیاده‌سازی را مستقیم مقایسه می‌کند:
 *   - Pas_Core_Jalali            (هستهٔ مشترک)
 *   - Pas_CF_Jalali              (پرونده‌های بالینی)
 *   - ZoojDarman\ClientPortal\Jalali (پنل مراجعان)
 *
 * محدوده: هر روز از ۱۳۰۰/۰۱/۰۱ تا ۱۴۵۰/۱۲/۲۹ (میلادی ۱۹۲۱-۰۳-۲۱ تا
 * ۲۰۷۲-۰۳-۱۹). برای هر روز، تبدیل رفت و برگشت هر سه پیاده‌سازی باید یکی
 * باشد؛ هر اختلاف یعنی تاریخ‌های ذخیره‌شده ممکن است جابه‌جا نمایش داده شوند.
 *
 * اجرا:
 *
 *   php tests/php/test-jalali.php
 *
 * خروجی: تعداد PASS/FAIL و کد خروج ۱ در صورت هر اختلاف.
 */

define( 'ABSPATH', __DIR__ . '/' );

$root = dirname( __DIR__, 2 );

require $root . '/plugins/pivand-amn-core/includes/class-jalali.php';
require $root . '/plugins/pivand-amn-case-file/includes/class-jalali.php';
require $root . '/plugins/pivand-amn-client-portal/includes/class-jalali.php';

$passed = 0;
$failed = 0;

function check( string $label, bool $condition, string $detail = '' ): void {
	global $passed, $failed;

	if ( $condition ) {
		$passed++;
		echo "PASS  $label\n";
		return;
	}

	$failed++;
	echo "FAIL  $label" . ( $detail !== '' ? " — $detail" : '' ) . "\n";
}

/* ── ۱) هم‌ارزی سه پیاده‌سازی روی بازهٔ کامل ─────────────────────────── */

$start = new DateTimeImmutable( '1921-03-21', new DateTimeZone( 'UTC' ) );
$end   = new DateTimeImmutable( '2072-03-19', new DateTimeZone( 'UTC' ) );

$days           = 0;
$g2jDiff        = [];
$j2gDiff        = [];

for ( $d = $start; $d <= $end; $d = $d->modify( '+1 day' ) ) {
	$gregorian = $d->format( 'Y-m-d' );
	$days++;

	$core   = Pas_Core_Jalali::to_jalali_string( $gregorian );
	$cf     = Pas_CF_Jalali::to_jalali_string( $gregorian );
	$portal = \ZoojDarman\ClientPortal\Jalali::gregorian_to_jalali_ymd( $gregorian );

	if ( $core !== $cf || $core !== $portal ) {
		$g2jDiff[] = "$gregorian core=$core cf=$cf portal=$portal";
		continue;
	}

	$backCore   = Pas_Core_Jalali::jalali_string_to_gregorian_string( $core );
	$backCf     = Pas_CF_Jalali::jalali_string_to_gregorian_string( $core );
	$backPortal = \ZoojDarman\ClientPortal\Jalali::jalali_string_to_gregorian_string( $core );

	if ( $backCore !== $gregorian || $backCf !== $gregorian || $backPortal !== $gregorian ) {
		$j2gDiff[] = "$core core=$backCore cf=$backCf portal=$backPortal (انتظار $gregorian)";
	}
}

check(
	sprintf( 'میلادی→شمسی در سه پیاده‌سازی یکسان است (%d روز)', $days ),
	$g2jDiff === [],
	implode( ' | ', array_slice( $g2jDiff, 0, 3 ) )
);

check(
	sprintf( 'شمسی→میلادی رفت‌وبرگشت دقیق است (%d روز)', $days ),
	$j2gDiff === [],
	implode( ' | ', array_slice( $j2gDiff, 0, 3 ) )
);

/* ── ۲) تاریخ‌های مرجع ───────────────────────────────────────────────── */

$references = [
	[ '1979-02-11', '1357/11/22', '۲۲ بهمن ۱۳۵۷' ],
	[ '2001-03-21', '1380/01/01', 'نوروز ۱۳۸۰' ],
	[ '2024-03-20', '1403/01/01', 'نوروز ۱۴۰۳' ],
	[ '2025-12-21', '1404/09/30', 'یلدای ۱۴۰۴' ],
	[ '2026-03-21', '1405/01/01', 'نوروز ۱۴۰۵' ],
	[ '2026-11-18', '1405/08/27', 'تاریخ نمونهٔ staging' ],
];

foreach ( $references as [ $gregorian, $expected, $label ] ) {
	$core   = Pas_Core_Jalali::to_jalali_string( $gregorian );
	$cf     = Pas_CF_Jalali::to_jalali_string( $gregorian );
	$portal = \ZoojDarman\ClientPortal\Jalali::gregorian_to_jalali_ymd( $gregorian );

	check(
		"مرجع: $label ($gregorian → $expected)",
		$core === $expected && $cf === $expected && $portal === $expected,
		"core=$core cf=$cf portal=$portal"
	);
}

/* ── ۳) ورودی کاربر: ارقام فارسی، قالب‌ها و رد نامعتبر ───────────────── */

check( 'ارقام فارسی پذیرفته می‌شود', '2026-11-18' === Pas_Core_Jalali::jalali_string_to_gregorian_string( '۱۴۰۵/۰۸/۲۷' ), (string) Pas_Core_Jalali::jalali_string_to_gregorian_string( '۱۴۰۵/۰۸/۲۷' ) );
check( 'ارقام عربی پذیرفته می‌شود', '2026-11-18' === Pas_Core_Jalali::jalali_string_to_gregorian_string( '١٤٠٥/٠٨/٢٧' ) );
check( 'جداکنندهٔ خط تیره پذیرفته می‌شود', '2026-11-18' === Pas_Core_Jalali::jalali_string_to_gregorian_string( '1405-08-27' ) );
check( 'ماه ۱۳ نامعتبر است', null === Pas_Core_Jalali::jalali_string_to_gregorian_string( '1405/13/01' ) );
check( 'روز ۳۲ نامعتبر است', null === Pas_Core_Jalali::jalali_string_to_gregorian_string( '1405/08/32' ) );
check( 'ورودی خالی نامعتبر است', null === Pas_Core_Jalali::jalali_string_to_gregorian_string( '' ) );
check( 'متن نامعتبر است', null === Pas_Core_Jalali::jalali_string_to_gregorian_string( 'فردا' ) );

/* ── ۴) خروجی تاریخ+ساعت و توابع کمکی ───────────────────────────────── */

check(
	'تاریخ+ساعت شمسی',
	'1405/08/27 12:34' === Pas_Core_Jalali::to_jalali_datetime_string( '2026-11-18 12:34:56' ),
	Pas_Core_Jalali::to_jalali_datetime_string( '2026-11-18 12:34:56' )
);
check( 'تاریخ بدون ساعت، ساعت جعلی نمی‌سازد', '1405/08/27' === Pas_Core_Jalali::to_jalali_datetime_string( '2026-11-18' ) );
check( 'مقدار خالی → رشتهٔ خالی', '' === Pas_Core_Jalali::to_jalali_datetime_string( '' ) );
check( 'pas_jalali_date', '1405/08/27' === pas_jalali_date( '2026-11-18' ) );
check( 'pas_jalali_datetime', '1405/08/27 09:05' === pas_jalali_datetime( '2026-11-18 09:05:00' ) );
check( 'pas_jalali_date نامعتبر را دست‌نخورده برمی‌گرداند', 'نامعتبر' === pas_jalali_date( 'نامعتبر' ) );

echo "\nPASSED: $passed\nFAILED: $failed\n";
exit( $failed > 0 ? 1 : 0 );
