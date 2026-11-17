<?php
/**
 * لایه‌ی امنیت — احراز هویت، کپابیلیتی، مجوز منبع، nonce و دروازه‌ها.
 *
 * قاعده: nonce هرگز جای مجوز منبع را نمی‌گیرد.
 */

defined( 'ABSPATH' ) || exit;

final class PAS_Dashboard_Security {

	/** دسترسی پایه به داشبورد */
	public static function can_access_dashboard() {
		if ( ! is_user_logged_in() ) {
			return false;
		}

		if ( current_user_can( 'manage_options' ) ) {
			return true;
		}
		if ( defined( 'PAS_DASHBOARD_CAP' ) && current_user_can( PAS_DASHBOARD_CAP ) ) {
			return true;
		}
		if ( defined( 'PAS_CF_CAP' ) && current_user_can( PAS_CF_CAP ) ) {
			return true;
		}

		return false;
	}

	/** مدیریت تنظیمات/سیستم — فقط مدیر سایت */
	public static function can_manage() {
		return is_user_logged_in() && current_user_can( 'manage_options' );
	}

	/** دیدن داده‌های بالینی (پرونده، یادداشت) */
	public static function can_view_clinical() {
		if ( ! is_user_logged_in() ) {
			return false;
		}
		if ( current_user_can( 'manage_options' ) ) {
			return true;
		}
		if ( defined( 'PAS_CF_CAP' ) && current_user_can( PAS_CF_CAP ) ) {
			return true;
		}

		return false;
	}

	/** دیدن پرونده‌ی همه‌ی درمانگرها */
	public static function can_view_all_cases() {
		if ( current_user_can( 'manage_options' ) ) {
			return true;
		}
		if ( defined( 'PAS_CF_CAP_ALL' ) && current_user_can( PAS_CF_CAP_ALL ) ) {
			return true;
		}

		return false;
	}

	public static function require_dashboard() {
		if ( ! self::can_access_dashboard() ) {
			self::record_denied( 'dashboard.access', 'dashboard', 0 );
			wp_die( esc_html__( 'شما اجازه‌ی دسترسی به این داشبورد را ندارید.', 'peyvand-amn-dashboard' ), '', array( 'response' => 403 ) );
		}
	}

	public static function require_clinical() {
		self::require_dashboard();

		if ( ! self::can_view_clinical() ) {
			self::record_denied( 'clinical.access', 'case', 0 );
			wp_die( esc_html__( 'دسترسی به داده‌های بالینی برای شما مجاز نیست.', 'peyvand-amn-dashboard' ), '', array( 'response' => 403 ) );
		}
	}

	/**
	 * مجوز دسترسی به یک پرونده‌ی مشخص — پیش از هر خواندن/نوشتنی.
	 */
	public static function can_access_case( $case_id ) {
		$case_id = (int) $case_id;
		if ( $case_id <= 0 || ! self::can_view_clinical() ) {
			return false;
		}

		$case = PAS_Dashboard_Core_Bridge::case_get( $case_id );
		if ( ! $case ) {
			return false;
		}

		return PAS_Dashboard_Core_Bridge::can_access_case( $case );
	}

	/**
	 * پرونده‌ی مجاز یا WP_Error؛ هرگز داده‌ی پرونده‌ی غیرمجاز برنمی‌گرداند.
	 *
	 * @return object|WP_Error
	 */
	public static function authorized_case( $case_id ) {
		$case_id = (int) $case_id;

		if ( $case_id <= 0 ) {
			return new WP_Error( 'pas_dashboard_bad_case', __( 'شناسه‌ی پرونده نامعتبر است.', 'peyvand-amn-dashboard' ) );
		}

		if ( ! self::can_view_clinical() ) {
			self::record_denied( 'case.access', 'case', $case_id );
			return new WP_Error( 'pas_dashboard_forbidden', __( 'دسترسی به داده‌های بالینی مجاز نیست.', 'peyvand-amn-dashboard' ) );
		}

		$case = PAS_Dashboard_Core_Bridge::case_get( $case_id );

		if ( ! $case ) {
			return new WP_Error( 'pas_dashboard_not_found', __( 'پرونده یافت نشد.', 'peyvand-amn-dashboard' ) );
		}

		if ( ! PAS_Dashboard_Core_Bridge::can_access_case( $case ) ) {
			self::record_denied( 'case.access', 'case', $case_id );
			return new WP_Error( 'pas_dashboard_forbidden', __( 'این پرونده به شما تخصیص داده نشده است.', 'peyvand-amn-dashboard' ) );
		}

		return $case;
	}

	/** آیا اطلاعات پارتنر برای این پرونده قابل نمایش است؟ */
	public static function can_view_partner( $case ) {
		$couple = PAS_Dashboard_Core_Bridge::couple_sessions( $case, 1 );

		return ! empty( $couple['can_view_partner'] );
	}

	/** یادداشت‌نویسی فقط با رمزنگاری فعال و دسترسی بالینی. */
	public static function notes_writable() {
		return self::can_view_clinical()
			&& PAS_Dashboard_Core_Bridge::crypto_ready()
			&& PAS_Dashboard_Permissions::feature( 'enable_notes' );
	}

	/* ── nonce ─────────────────────────────────────────────────────────── */

	public static function nonce( $action ) {
		return wp_create_nonce( 'pas_dashboard_' . $action );
	}

	public static function verify_nonce( $action, $field = '_pasnonce' ) {
		$value = isset( $_REQUEST[ $field ] ) ? sanitize_text_field( wp_unslash( $_REQUEST[ $field ] ) ) : '';

		return $value !== '' && (bool) wp_verify_nonce( $value, 'pas_dashboard_' . $action );
	}

	/** فیلد مخفی nonce برای فرم‌ها */
	public static function nonce_field( $action ) {
		return '<input type="hidden" name="_pasnonce" value="' . esc_attr( self::nonce( $action ) ) . '" />';
	}

	/* ── ابزار ─────────────────────────────────────────────────────────── */

	public static function record_denied( $action, $type, $id = 0 ) {
		if ( class_exists( 'PAS_Dashboard_Audit_Log' ) ) {
			PAS_Dashboard_Audit_Log::record( $action, $type, $id, 'denied' );
		}
	}

	public static function redirect( $url ) {
		wp_safe_redirect( $url );
		exit;
	}
}
