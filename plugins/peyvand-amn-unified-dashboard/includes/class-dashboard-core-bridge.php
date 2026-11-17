<?php
/**
 * پل هسته — تنها نقطه‌ی تماس با افزونه‌های دیگر.
 *
 * همه‌ی فراخوانی‌ها با بررسی وجود کلاس/متد انجام می‌شود؛ هیچ متد یا قراردادی
 * اختراع نمی‌شود. اگر افزونه‌ای نباشد، خروجی خالی/null/WP_Error است و
 * داشبورد با پیام روشن degrade می‌شود.
 */

defined( 'ABSPATH' ) || exit;

final class PAS_Dashboard_Core_Bridge {

	/* ── تشخیص وابستگی‌ها ───────────────────────────────────────────────── */

	public static function has_core() {
		return class_exists( 'Pas_Core_Client_Registry' );
	}

	public static function has_case_file() {
		return class_exists( 'Pas_CF_Case_File' );
	}

	public static function has_crypto() {
		return class_exists( 'Pas_Core_Crypto' ) || class_exists( 'Pas_CF_Crypto' );
	}

	public static function crypto_ready() {
		if ( class_exists( 'Pas_Core_Crypto' ) && method_exists( 'Pas_Core_Crypto', 'is_configured' ) ) {
			return (bool) Pas_Core_Crypto::is_configured();
		}
		if ( class_exists( 'Pas_CF_Crypto' ) && method_exists( 'Pas_CF_Crypto', 'is_configured' ) ) {
			return (bool) Pas_CF_Crypto::is_configured();
		}

		return false;
	}

	public static function sms_available() {
		return function_exists( 'has_filter' ) && has_filter( 'pas_send_sms' );
	}

	public static function sms_log_available() {
		return class_exists( 'Pas_Log' ) && method_exists( 'Pas_Log', 'recent' );
	}

	public static function assessments_available() {
		return class_exists( 'PsychAssessments\\Core\\Registry' ) && method_exists( 'PsychAssessments\\Core\\Registry', 'all' );
	}

	public static function portal_available() {
		return class_exists( 'ZDP_Booking' ) || defined( 'ZDP_VERSION' ) || function_exists( 'zdp_get_client_panel_url' );
	}

	/* ── پرونده‌ها ──────────────────────────────────────────────────────── */

	/**
	 * فقط از API لیست مجاز درمانگر استفاده می‌کند (هرگز list_all).
	 *
	 * @return array<int,object>
	 */
	/**
	 * فهرست پرونده‌های قابل نمایش برای کاربر جاری.
	 *
	 * قاعده همان قاعده‌ی صفحه‌ی مدیریتی خود Case File است:
	 *   - دارنده‌ی PAS_CF_CAP_ALL (سرپرست) → همه‌ی پرونده‌ها
	 *   - درمانگر عادی → فقط پرونده‌های خودش (list_for_therapist)
	 *
	 * پیش‌تر همیشه list_for_therapist صدا زده می‌شد؛ نتیجه این بود که مدیر کل
	 * در نمای کلی/گزارش‌ها/خروجی CSV هیچ پرونده‌ای نمی‌دید مگر پرونده‌ای که
	 * به خودش تخصیص یافته بود (نقص عملکردی واقعی، پیدا شده در آزمون CSV).
	 *
	 * list_all فقط پس از احراز current_user_can_view_all() صدا زده می‌شود؛
	 * هرگز برای درمانگر عادی و هرگز بدون بررسی کاپابیلیتی.
	 */
	public static function cases( $therapist_id, $limit = 200 ) {
		if ( ! self::has_case_file() ) {
			return array();
		}

		$limit = max( 1, min( 500, (int) $limit ) );

		if ( self::viewer_can_see_all_cases() && method_exists( 'Pas_CF_Case_File', 'list_all' ) ) {
			$rows = Pas_CF_Case_File::list_all( $limit );

			return is_array( $rows ) ? $rows : array();
		}

		if ( ! method_exists( 'Pas_CF_Case_File', 'list_for_therapist' ) ) {
			return array();
		}

		$rows = Pas_CF_Case_File::list_for_therapist( (int) $therapist_id, $limit );

		return is_array( $rows ) ? $rows : array();
	}

	/** آیا کاربر جاری سرپرست است (همان کاپابیلیتی که Case File استفاده می‌کند)؟ */
	public static function viewer_can_see_all_cases() {
		if ( ! class_exists( 'Pas_CF_Security' ) || ! method_exists( 'Pas_CF_Security', 'current_user_can_view_all' ) ) {
			return false;
		}

		return (bool) Pas_CF_Security::current_user_can_view_all();
	}

	public static function case_get( $case_id ) {
		$case_id = (int) $case_id;
		if ( $case_id <= 0 || ! self::has_case_file() || ! method_exists( 'Pas_CF_Case_File', 'get' ) ) {
			return null;
		}

		$case = Pas_CF_Case_File::get( $case_id );

		return is_object( $case ) ? $case : null;
	}

	public static function can_access_case( $case ) {
		if ( ! is_object( $case ) || ! class_exists( 'Pas_CF_Security' ) ) {
			return false;
		}
		if ( ! method_exists( 'Pas_CF_Security', 'current_user_can_access_case' ) ) {
			return false;
		}

		return (bool) Pas_CF_Security::current_user_can_access_case( $case );
	}

	/**
	 * @return array{partner:object|null,can_view_partner:bool,sessions:array}
	 */
	public static function couple_sessions( $case, $limit = 60 ) {
		$empty = array(
			'partner'          => null,
			'can_view_partner' => false,
			'sessions'         => array(),
		);

		if ( ! is_object( $case ) || ! method_exists( 'Pas_CF_Case_File', 'get_couple_sessions' ) ) {
			return $empty;
		}

		$result = Pas_CF_Case_File::get_couple_sessions( $case, max( 1, min( 200, (int) $limit ) ) );

		if ( ! is_array( $result ) ) {
			return $empty;
		}

		return array_merge(
			$empty,
			array(
				'partner'          => isset( $result['partner'] ) && is_object( $result['partner'] ) ? $result['partner'] : null,
				'can_view_partner' => ! empty( $result['can_view_partner'] ),
				'sessions'         => isset( $result['sessions'] ) && is_array( $result['sessions'] ) ? $result['sessions'] : array(),
			)
		);
	}

	public static function next_session( $case ) {
		if ( ! is_object( $case ) || ! method_exists( 'Pas_CF_Case_File', 'get_next_session' ) ) {
			return null;
		}

		$row = Pas_CF_Case_File::get_next_session( $case );

		return is_object( $row ) ? $row : null;
	}

	public static function reminder_blocker( $case, $next ) {
		if ( ! method_exists( 'Pas_CF_Case_File', 'reminder_blocker' ) ) {
			return 'unknown';
		}

		return (string) Pas_CF_Case_File::reminder_blocker( $case, $next );
	}

	/* ── یادداشت‌ها (فقط پس از احراز دسترسی) ───────────────────────────── */

	/** @return array<int,object> */
	public static function notes( $case_id ) {
		if ( ! self::has_case_file() || ! method_exists( 'Pas_CF_Case_File', 'get_notes' ) ) {
			return array();
		}

		$rows = Pas_CF_Case_File::get_notes( (int) $case_id );

		return is_array( $rows ) ? $rows : array();
	}

	/** @return int|WP_Error */
	public static function add_note( $case_id, $session_date, $content, $session_number = 0, $meta = array() ) {
		if ( ! self::has_case_file() || ! method_exists( 'Pas_CF_Case_File', 'add_note' ) ) {
			return new WP_Error( 'pas_dashboard_no_api', __( 'API یادداشت در افزونه‌ی پرونده‌ها موجود نیست.', 'peyvand-amn-dashboard' ) );
		}

		if ( ! self::crypto_ready() ) {
			return new WP_Error( 'pas_dashboard_no_crypto', __( 'کلید رمزنگاری تنظیم نشده است؛ ثبت یادداشت بالینی مجاز نیست.', 'peyvand-amn-dashboard' ) );
		}

		try {
			$note_id = Pas_CF_Case_File::add_note(
				(int) $case_id,
				(string) $session_date,
				(string) $content,
				(int) $session_number,
				is_array( $meta ) ? $meta : array()
			);
		} catch ( Throwable $e ) {
			return new WP_Error( 'pas_dashboard_note_failed', __( 'ثبت یادداشت ناموفق بود.', 'peyvand-amn-dashboard' ) );
		}

		return (int) $note_id > 0 ? (int) $note_id : new WP_Error( 'pas_dashboard_note_failed', __( 'ثبت یادداشت ناموفق بود.', 'peyvand-amn-dashboard' ) );
	}

	public static function update_note( $note_id, $case_id, $session_date, $content ) {
		if ( ! method_exists( 'Pas_CF_Case_File', 'update_note' ) || ! self::crypto_ready() ) {
			return false;
		}

		try {
			return (bool) Pas_CF_Case_File::update_note( (int) $note_id, (int) $case_id, (string) $session_date, (string) $content );
		} catch ( Throwable $e ) {
			return false;
		}
	}

	public static function delete_note( $note_id, $case_id ) {
		if ( ! method_exists( 'Pas_CF_Case_File', 'delete_note' ) ) {
			return false;
		}

		try {
			return (bool) Pas_CF_Case_File::delete_note( (int) $note_id, (int) $case_id );
		} catch ( Throwable $e ) {
			return false;
		}
	}

	/** @return array<int,object> */
	public static function note_history( $note_id ) {
		if ( ! method_exists( 'Pas_CF_Case_File', 'get_note_history' ) ) {
			return array();
		}

		$rows = Pas_CF_Case_File::get_note_history( (int) $note_id );

		return is_array( $rows ) ? $rows : array();
	}

	public static function restore_note_version( $history_id, $case_id ) {
		if ( ! method_exists( 'Pas_CF_Case_File', 'restore_note_version' ) ) {
			return false;
		}

		return (bool) Pas_CF_Case_File::restore_note_version( (int) $history_id, (int) $case_id );
	}

	/** @return array<int,object> */
	public static function deleted_notes( $case_id ) {
		if ( ! method_exists( 'Pas_CF_Case_File', 'get_deleted_notes' ) ) {
			return array();
		}

		$rows = Pas_CF_Case_File::get_deleted_notes( (int) $case_id );

		return is_array( $rows ) ? $rows : array();
	}

	/* ── مراجعان (رجیستری هویت هسته) ──────────────────────────────────── */

	public static function client_stats() {
		if ( ! self::has_core() || ! method_exists( 'Pas_Core_Client_Registry', 'stats' ) ) {
			return array(
				'installed'    => false,
				'clients'      => 0,
				'identifiers'  => 0,
				'review'       => 0,
				'conflicts_open' => 0,
				'by_type'      => array(),
				'last_rebuild' => '',
			);
		}

		return (array) Pas_Core_Client_Registry::stats();
	}

	/** @return array<int,array> */
	public static function clients_sample( $limit = 30 ) {
		if ( ! self::has_core() || ! method_exists( 'Pas_Core_Client_Registry', 'sample_clients' ) ) {
			return array();
		}

		$rows = Pas_Core_Client_Registry::sample_clients( max( 1, min( 100, (int) $limit ) ) );

		return is_array( $rows ) ? $rows : array();
	}

	public static function client_get( $client_id ) {
		if ( ! self::has_core() || ! method_exists( 'Pas_Core_Client_Registry', 'get_client' ) ) {
			return null;
		}

		$client = Pas_Core_Client_Registry::get_client( (string) $client_id );

		return is_array( $client ) ? $client : null;
	}

	public static function client_by_identifier( $type, $value ) {
		if ( ! self::has_core() || ! method_exists( 'Pas_Core_Client_Registry', 'get_client_by_identifier' ) ) {
			return null;
		}

		$client = Pas_Core_Client_Registry::get_client_by_identifier( (string) $type, (string) $value );

		return is_array( $client ) ? $client : null;
	}

	/**
	 * قرارداد عمومی هسته: apply_filters('pas_get_client', null, $query)
	 * خود هسته دسترسی را بررسی می‌کند؛ خروجی فقط شناسه/نام/وضعیت است.
	 */
	public static function client_via_filter( array $query ) {
		if ( ! function_exists( 'apply_filters' ) ) {
			return null;
		}

		$client = apply_filters( 'pas_get_client', null, $query );

		return is_array( $client ) ? $client : null;
	}

	/* ── نوبت‌ها / آزمون‌ها / پیامک ────────────────────────────────────── */

	/** نوبت‌های ثبت‌شده‌ی پنل مراجعان — فقط‌خواندنی. */
	public static function bookings( $phone ) {
		if ( ! self::has_case_file() || ! method_exists( 'Pas_CF_Case_File', 'get_linked_bookings' ) ) {
			return array();
		}

		$rows = Pas_CF_Case_File::get_linked_bookings( (string) $phone );

		return is_array( $rows ) ? $rows : array();
	}

	/** @return array<int,array{id:string,name:string,category:string}> */
	public static function assessments() {
		if ( ! self::assessments_available() ) {
			return array();
		}

		$out = array();

		try {
			foreach ( (array) PsychAssessments\Core\Registry::all() as $test ) {
				if ( ! is_object( $test ) || ! method_exists( $test, 'get_id' ) ) {
					continue;
				}
				$out[] = array(
					'id'       => (string) $test->get_id(),
					'name'     => method_exists( $test, 'get_name' ) ? (string) $test->get_name() : (string) $test->get_id(),
					'category' => method_exists( $test, 'get_category' ) ? (string) $test->get_category() : '',
				);
			}
		} catch ( Throwable $e ) {
			return array();
		}

		return $out;
	}

	/** @return array<int,object> */
	public static function sms_recent( $limit = 30 ) {
		if ( ! self::sms_log_available() ) {
			return array();
		}

		$rows = Pas_Log::recent( max( 1, min( 100, (int) $limit ) ) );

		return is_array( $rows ) ? $rows : array();
	}

	/* ── ابزار نمایش ───────────────────────────────────────────────────── */

	public static function jalali( $gregorian_date ) {
		$date = (string) $gregorian_date;
		if ( $date === '' || $date === '0000-00-00' ) {
			return '—';
		}

		if ( class_exists( 'Pas_Core_Jalali' ) && method_exists( 'Pas_Core_Jalali', 'to_jalali_string' ) ) {
			try {
				$jalali = (string) Pas_Core_Jalali::to_jalali_string( $date );
				if ( $jalali !== '' ) {
					return $jalali;
				}
			} catch ( Throwable $e ) {
				// به تاریخ میلادی برمی‌گردیم
			}
		}

		return $date;
	}

	public static function therapist_name( $user_id ) {
		$user = get_userdata( (int) $user_id );

		return $user ? (string) $user->display_name : '—';
	}

	/** پوشاندن بخشی از شماره برای نمایش در لیست‌ها. */
	public static function mask_mobile( $phone ) {
		$phone = preg_replace( '/\D+/', '', (string) $phone );

		if ( strlen( $phone ) < 7 ) {
			return '—';
		}

		return substr( $phone, 0, 4 ) . '***' . substr( $phone, -3 );
	}
}
