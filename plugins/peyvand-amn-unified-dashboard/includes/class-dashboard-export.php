<?php
/**
 * خروجی CSV — فقط داده‌ی حداقلی و فقط پرونده‌های مجاز کاربر.
 *
 * نام و شماره‌ی مراجع در خروجی نیست (کاهش افشا). خروجی شامل شناسه، وضعیت،
 * درمانگر، تاریخ به‌روزرسانی و تعداد جلسه است.
 */

defined( 'ABSPATH' ) || exit;

final class PAS_Dashboard_Export {

	public static function init() {
		add_action( 'admin_post_pas_dashboard_export_cases', array( __CLASS__, 'handle_cases_csv' ) );
	}

	public static function can_export() {
		return PAS_Dashboard_Security::can_view_clinical();
	}

	/**
	 * خنثی‌سازی فرمول در CSV (CSV / formula injection).
	 *
	 * Excel/LibreOffice مقادیری را که با = + - @ یا tab/CR شروع شوند به‌عنوان
	 * فرمول اجرا می‌کنند؛ نام نمایشی درمانگر یا وضعیت پرونده از پایگاه‌داده
	 * می‌آید و می‌تواند چنین پیشوندی داشته باشد. یک آپاستروف پیشوندی مقدار را
	 * متن نگه می‌دارد و در Excel بدون آپاستروف نمایش داده می‌شود.
	 */
	public static function csv_cell( $value ) {
		$value = (string) $value;

		if ( $value !== '' && false !== strpos( "=+-@\t\r", $value[0] ) ) {
			return "'" . $value;
		}

		return $value;
	}

	public static function handle_cases_csv() {
		if ( ! is_user_logged_in() ) {
			wp_die( esc_html__( 'دسترسی غیرمجاز.', 'peyvand-amn-dashboard' ), 403 );
		}

		if ( ! PAS_Dashboard_Security::verify_nonce( 'export_cases' ) ) {
			PAS_Dashboard_Security::record_denied( 'export.cases', 'export', 0 );
			wp_die( esc_html__( 'توکن امنیتی نامعتبر است.', 'peyvand-amn-dashboard' ), 403 );
		}

		if ( ! self::can_export() ) {
			PAS_Dashboard_Security::record_denied( 'export.cases', 'export', 0 );
			wp_die( esc_html__( 'خروجی گرفتن برای شما مجاز نیست.', 'peyvand-amn-dashboard' ), 403 );
		}

		$user_id = get_current_user_id();
		$cases   = PAS_Dashboard_Core_Bridge::cases( $user_id, 500 );

		PAS_Dashboard_Audit_Log::record( 'export.cases', 'export', 0, 'ok' );

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="pas-cases-' . gmdate( 'Ymd-His' ) . '.csv"' );

		$out = fopen( 'php://output', 'w' );

		// BOM برای نمایش درست فارسی در Excel
		fwrite( $out, "\xEF\xBB\xBF" );

		// پارامتر $escape صریح است: از PHP 8.4 به بعد حذف آن منسوخ است و
		// پیش‌فرضش هم تغییر می‌کند؛ رشته‌ی خالی رفتار سازگار RFC 4180 می‌دهد.
		fputcsv(
			$out,
			array_map(
				array( __CLASS__, 'csv_cell' ),
				array(
					__( 'شناسه پرونده', 'peyvand-amn-dashboard' ),
					__( 'وضعیت', 'peyvand-amn-dashboard' ),
					__( 'درمانگر', 'peyvand-amn-dashboard' ),
					__( 'آخرین جلسه', 'peyvand-amn-dashboard' ),
					__( 'آخرین به‌روزرسانی', 'peyvand-amn-dashboard' ),
				)
			),
			',',
			'"',
			''
		);

		// یک کوئری متادیتا برای همه‌ی پرونده‌ها — بدون رمزگشایی و بدون متن یادداشت.
		$ids      = array();
		foreach ( $cases as $case ) {
			if ( is_object( $case ) && ! empty( $case->id ) ) {
				$ids[] = (int) $case->id;
			}
		}

		$last_map = method_exists( 'Pas_CF_Case_File', 'get_last_session_map' )
			? (array) Pas_CF_Case_File::get_last_session_map( $ids )
			: array();

		foreach ( $cases as $case ) {
			if ( ! is_object( $case ) ) {
				continue;
			}

			$case_id = (int) ( $case->id ?? 0 );
			if ( $case_id <= 0 ) {
				continue;
			}

			fputcsv(
				$out,
				array_map(
					array( __CLASS__, 'csv_cell' ),
					array(
						$case_id,
						(string) ( $case->status ?? '' ),
						PAS_Dashboard_Core_Bridge::therapist_name( (int) ( $case->assigned_therapist ?? 0 ) ),
						(string) ( $last_map[ $case_id ] ?? '' ),
						(string) ( $case->updated_at ?? '' ),
					)
				),
				',',
				'"',
				''
			);
		}

		fclose( $out );
		exit;
	}
}
