<?php
/**
 * آداپتر نوبت‌های پنل مراجعان — فقط APIهای عمومی افزونهٔ مالک.
 *
 * کلاس‌های پنل نمونه‌محورند و از طریق دسترسی‌دهندهٔ عمومی Plugin::booking()
 * در دسترس قرار می‌گیرند (قرارداد مالک، بدون دسترسی مستقیم به جدول).
 */

defined( 'ABSPATH' ) || exit;

final class PAS_Dashboard_Adapter_Portal {

	/** همان فهرست وضعیت‌های مالک (Admin::ALL_BOOKING_STATUSES). */
	private const STATUSES = array(
		'در انتظار پرداخت',
		'در انتظار تایید واریز',
		'در انتظار بررسی',
		'تایید شده',
		'فعال',
		'انجام شده',
		'عدم حضور',
		'ناموفق',
		'لغو شده',
	);

	/** @var array<int,string>|null */
	private static $scope_phones = null;

	public static function available() {
		return class_exists( '\\ZoojDarman\\ClientPortal\\Plugin' )
			&& method_exists( '\\ZoojDarman\\ClientPortal\\Plugin', 'booking' );
	}

	public static function statuses() {
		return self::STATUSES;
	}

	public static function messages_available() {
		return class_exists( '\\ZoojDarman\\ClientPortal\\Plugin' )
			&& method_exists( '\\ZoojDarman\\ClientPortal\\Plugin', 'messages' );
	}

	/** @return array<int,object> */
	public static function messages( $limit = 100 ) {
		if ( ! self::messages_available() ) {
			return array();
		}

		try {
			$rows = \ZoojDarman\ClientPortal\Plugin::instance()->messages()->get_all_messages( max( 1, min( 300, (int) $limit ) ) );
		} catch ( Throwable $e ) {
			return array();
		}

		return is_array( $rows ) ? $rows : array();
	}

	public static function reply_message( array $post ) {
		if ( ! self::messages_available() ) {
			return new WP_Error( 'pas_dashboard_no_messages', __( 'صندوق پیام پنل مراجعان در دسترس نیست.', 'peyvand-amn-dashboard' ) );
		}

		$msg_id = PAS_Dashboard_Validation::id( $post['message_id'] ?? 0 );
		$text   = PAS_Dashboard_Validation::textarea( $post['reply_text'] ?? '', 5000 );

		if ( $msg_id <= 0 ) {
			return new WP_Error( 'pas_dashboard_message_missing', __( 'شناسهٔ پیام نامعتبر است.', 'peyvand-amn-dashboard' ) );
		}

		if ( mb_strlen( trim( $text ) ) < 2 ) {
			return new WP_Error( 'pas_dashboard_message_text', __( 'متن پاسخ را وارد کنید.', 'peyvand-amn-dashboard' ) );
		}

		// دامنهٔ شیء: درمانگر فقط به مراجعان پرونده‌های خودش پاسخ می‌دهد.
		$message = self::message( $msg_id );

		if ( ! $message ) {
			return new WP_Error( 'pas_dashboard_message_missing', __( 'ثبت پاسخ ناموفق بود یا پیام یافت نشد.', 'peyvand-amn-dashboard' ) );
		}

		if ( ! self::can_view_phone( (string) ( $message->mobile ?? '' ) ) ) {
			return new WP_Error( 'pas_dashboard_message_scope', __( 'این پیام از مراجعان پرونده‌های شما نیست.', 'peyvand-amn-dashboard' ) );
		}

		try {
			$ok = \ZoojDarman\ClientPortal\Plugin::instance()->messages()->reply( $msg_id, $text );
		} catch ( Throwable $e ) {
			$ok = false;
		}

		return array(
			'ok'       => (bool) $ok,
			'message'  => $ok ? __( 'پاسخ برای مراجع ثبت شد.', 'peyvand-amn-dashboard' ) : __( 'ثبت پاسخ ناموفق بود یا پیام یافت نشد.', 'peyvand-amn-dashboard' ),
			'id'       => $msg_id,
			'redirect' => add_query_arg(
				array( 'page' => PAS_DASHBOARD_SLUG, 'module' => 'communications' ),
				admin_url( 'admin.php' )
			),
		);
	}

	public static function session_types() {
		if ( class_exists( '\\ZoojDarman\\ClientPortal\\Booking' ) && defined( 'ZoojDarman\\ClientPortal\\Booking::SESSION_TYPES' ) ) {
			return (array) \ZoojDarman\ClientPortal\Booking::SESSION_TYPES;
		}

		return array(
			'in_person' => __( 'حضوری', 'peyvand-amn-dashboard' ),
			'online'    => __( 'آنلاین', 'peyvand-amn-dashboard' ),
			'phone'     => __( 'تلفنی', 'peyvand-amn-dashboard' ),
		);
	}

	public static function sources() {
		if ( class_exists( '\\ZoojDarman\\ClientPortal\\Booking' ) && defined( 'ZoojDarman\\ClientPortal\\Booking::MANUAL_SOURCES' ) ) {
			return (array) \ZoojDarman\ClientPortal\Booking::MANUAL_SOURCES;
		}

		return array(
			'phone'     => __( 'تلفنی', 'peyvand-amn-dashboard' ),
			'whatsapp'  => __( 'واتساپ', 'peyvand-amn-dashboard' ),
			'in_person' => __( 'حضوری', 'peyvand-amn-dashboard' ),
			'other'     => __( 'سایر', 'peyvand-amn-dashboard' ),
		);
	}

	public static function all_sources() {
		if ( class_exists( '\\ZoojDarman\\ClientPortal\\Booking' ) && defined( 'ZoojDarman\\ClientPortal\\Booking::ALL_SOURCES' ) ) {
			return (array) \ZoojDarman\ClientPortal\Booking::ALL_SOURCES;
		}

		return self::sources();
	}

	/** @return array<int,object> */
	public static function bookings( $limit = 200, $search = '' ) {
		if ( ! self::available() ) {
			return array();
		}

		try {
			$rows = self::booking()->get_all_bookings_with_client_names( max( 1, min( 300, (int) $limit ) ), (string) $search );
		} catch ( Throwable $e ) {
			return array();
		}

		return is_array( $rows ) ? $rows : array();
	}

	public static function get( $booking_id ) {
		if ( ! self::available() ) {
			return null;
		}

		$booking_id = PAS_Dashboard_Validation::id( $booking_id );

		if ( $booking_id <= 0 ) {
			return null;
		}

		try {
			$row = self::booking()->get_booking( $booking_id );
		} catch ( Throwable $e ) {
			return null;
		}

		return is_object( $row ) ? $row : null;
	}

	public static function create_booking( array $post ) {
		if ( ! self::available() ) {
			return new WP_Error( 'pas_dashboard_no_portal', __( 'افزونهٔ پنل مراجعان فعال نیست.', 'peyvand-amn-dashboard' ) );
		}

		$date = self::to_gregorian( $post['booking_date_jalali'] ?? '' );

		if ( is_wp_error( $date ) ) {
			return $date;
		}

		$time = trim( (string) ( $post['booking_time'] ?? '' ) );

		if ( ! preg_match( '/^([01]\d|2[0-3]):[0-5]\d$/', $time ) ) {
			return new WP_Error( 'pas_dashboard_booking_time', __( 'ساعت جلسه معتبر نیست (نمونه: 09:00).', 'peyvand-amn-dashboard' ) );
		}

		$result = self::booking()->create_manual_booking(
			array(
				'full_name'      => wp_unslash( $post['full_name'] ?? '' ),
				'mobile'         => wp_unslash( $post['mobile'] ?? '' ),
				'booking_date'   => $date,
				'booking_time'   => $time,
				'session_type'   => wp_unslash( $post['session_type'] ?? '' ),
				'booking_source' => wp_unslash( $post['booking_source'] ?? '' ),
				'therapist_note' => wp_unslash( $post['therapist_note'] ?? '' ),
			)
		);

		if ( empty( $result['success'] ) ) {
			return new WP_Error(
				'pas_dashboard_booking_failed',
				(string) ( $result['data']['message'] ?? __( 'ثبت نوبت ناموفق بود.', 'peyvand-amn-dashboard' ) )
			);
		}

		return array(
			'ok'       => true,
			'message'  => (string) ( $result['data']['message'] ?? __( 'نوبت ثبت شد.', 'peyvand-amn-dashboard' ) ),
			'id'       => (int) ( $result['data']['booking_id'] ?? 0 ),
			'redirect' => self::module_url(),
		);
	}

	public static function change_status( array $post ) {
		$booking = self::editable_booking( $post['booking_id'] ?? 0 );

		if ( is_wp_error( $booking ) ) {
			return $booking;
		}

		$status = trim( (string) wp_unslash( $post['status'] ?? '' ) );

		if ( ! in_array( $status, self::STATUSES, true ) ) {
			return new WP_Error( 'pas_dashboard_booking_status', __( 'وضعیت نوبت معتبر نیست.', 'peyvand-amn-dashboard' ) );
		}

		if ( $status === (string) $booking->status ) {
			return array(
				'ok'       => true,
				'message'  => __( 'وضعیت نوبت تغییری نکرد.', 'peyvand-amn-dashboard' ),
				'id'       => (int) $booking->id,
				'redirect' => self::module_url(),
			);
		}

		self::booking()->update_status( (int) $booking->id, $status );

		return array(
			'ok'       => true,
			'message'  => __( 'وضعیت نوبت به‌روزرسانی شد.', 'peyvand-amn-dashboard' ),
			'id'       => (int) $booking->id,
			'redirect' => self::module_url(),
		);
	}

	public static function update_booking( array $post ) {
		$booking = self::editable_booking( $post['booking_id'] ?? 0 );

		if ( is_wp_error( $booking ) ) {
			return $booking;
		}

		$fields = array();
		$time   = trim( (string) ( $post['booking_time'] ?? '' ) );

		if ( $time !== '' ) {
			if ( ! preg_match( '/^([01]\d|2[0-3]):[0-5]\d$/', $time ) ) {
				return new WP_Error( 'pas_dashboard_booking_time', __( 'ساعت جلسه معتبر نیست (نمونه: 09:00).', 'peyvand-amn-dashboard' ) );
			}
			$fields['booking_time'] = $time;
		}

		if ( ! empty( $post['booking_date_jalali'] ) ) {
			$date = self::to_gregorian( $post['booking_date_jalali'] );

			if ( is_wp_error( $date ) ) {
				return $date;
			}

			$fields['booking_date'] = $date;
		}

		$fields['session_type']   = sanitize_text_field( wp_unslash( $post['session_type'] ?? '' ) );
		$fields['booking_source'] = sanitize_text_field( wp_unslash( $post['booking_source'] ?? '' ) );
		$fields['therapist_note'] = PAS_Dashboard_Validation::textarea( $post['therapist_note'] ?? '', 5000 );

		$status = trim( (string) wp_unslash( $post['status'] ?? '' ) );
		if ( $status !== '' && $status !== (string) $booking->status && in_array( $status, self::STATUSES, true ) ) {
			// همان متد مالک: کد پیگیری دستی + پیامک تاییدیه را هم انجام می‌دهد.
			// فقط هنگام تغییر واقعی وضعیت صدا زده می‌شود تا ذخیرهٔ تکراری
			// باعث ارسال پیامک تکراری نشود.
			self::booking()->update_status( (int) $booking->id, $status );
		}

		self::booking()->update_booking_fields( (int) $booking->id, $fields );

		return array(
			'ok'       => true,
			'message'  => __( 'نوبت ویرایش شد.', 'peyvand-amn-dashboard' ),
			'id'       => (int) $booking->id,
			'redirect' => self::module_url(),
		);
	}

	public static function delete_booking( array $post ) {
		$booking = self::editable_booking( $post['booking_id'] ?? 0 );

		if ( is_wp_error( $booking ) ) {
			return $booking;
		}

		$ok = self::booking()->delete_booking( (int) $booking->id );

		return array(
			'ok'       => (bool) $ok,
			'message'  => $ok ? __( 'نوبت حذف شد.', 'peyvand-amn-dashboard' ) : __( 'حذف نوبت ناموفق بود.', 'peyvand-amn-dashboard' ),
			'id'       => (int) $booking->id,
			'redirect' => self::module_url(),
		);
	}

	public static function send_reminder( array $post ) {
		$booking = self::editable_booking( $post['booking_id'] ?? 0 );

		if ( is_wp_error( $booking ) ) {
			return $booking;
		}

		$mobile = self::phone_for( $booking );
		$email  = self::email_for( $booking );
		$body   = (string) get_option( 'zdp_sms_reminder_body_id', '' );
		$date   = PAS_Dashboard_Core_Bridge::jalali( (string) $booking->booking_date );
		$time   = substr( (string) $booking->booking_time, 0, 5 );
		$sms    = false;

		$sms_allowed = $mobile !== '' && $body !== '' && strpos( $mobile, '+' ) !== 0 && has_filter( 'pas_send_sms' );

		if ( $sms_allowed ) {
			$sms = (bool) apply_filters(
				'pas_send_sms',
				null,
				$mobile,
				$body,
				array(
					'DATE' => $date,
					'TIME' => $time,
				)
			);
		}

		$email_sent = false;
		if ( $email !== '' ) {
			$body_mail  = sprintf(
				/* translators: 1: date, 2: time */
				__( 'یادآوری نوبت شما: %1$s ساعت %2$s — پیوند امن', 'peyvand-amn-dashboard' ),
				$date,
				$time
			);
			$email_sent = wp_mail( $email, __( 'یادآوری نوبت', 'peyvand-amn-dashboard' ), $body_mail );
		}

		if ( ! $sms && ! $email_sent ) {
			return new WP_Error( 'pas_dashboard_booking_reminder', __( 'ارسال یادآور ناموفق بود؛ الگوی پیامک، پل پیامک یا ایمیل مراجع را بررسی کنید.', 'peyvand-amn-dashboard' ) );
		}

		self::booking()->mark_reminder_sent( (int) $booking->id );

		return array(
			'ok'      => true,
			'message' => __( 'یادآور نوبت ارسال شد.', 'peyvand-amn-dashboard' ),
			'id'      => (int) $booking->id,
		);
	}

	public static function in_scope( $booking ) {
		if ( ! is_object( $booking ) ) {
			return false;
		}

		return self::can_view_phone( self::phone_for( $booking ) );
	}

	/** آیا شمارهٔ این مراجع در دامنهٔ پرونده‌های کاربر جاری است؟ */
	public static function can_view_phone( $phone ) {
		$phone = (string) $phone;

		if ( $phone === '' ) {
			return false;
		}

		// نقش پذیرش دامنهٔ تماس نوبت و پیام را در سطح کلینیک می‌بیند، بدون آنکه
		// به دادهٔ بالینی دسترسی پیدا کند؛ این خط فقط دامنهٔ شماره را باز می‌کند.
		if ( PAS_Dashboard_Permissions::is_frontdesk() ) {
			return true;
		}

		if ( PAS_Dashboard_Core_Bridge::viewer_can_see_all_cases() ) {
			return true;
		}

		return in_array( $phone, self::scope_phones(), true );
	}

	public static function phone_for( $booking ) {
		if ( ! is_object( $booking ) ) {
			return '';
		}

		if ( function_exists( 'ZoojDarman\\ClientPortal\\zdp_get_user_mobile' ) ) {
			$mobile = (string) \ZoojDarman\ClientPortal\zdp_get_user_mobile( (int) $booking->user_id );
		} else {
			$mobile = (string) get_user_meta( (int) $booking->user_id, 'cellphone', true );
			if ( $mobile === '' ) {
				$mobile = (string) get_user_meta( (int) $booking->user_id, 'zdp_mobile', true );
			}
		}

		return $mobile;
	}

	public static function email_for( $booking ) {
		if ( ! is_object( $booking ) ) {
			return '';
		}

		if ( function_exists( 'ZoojDarman\\ClientPortal\\zdp_user_email' ) ) {
			return (string) \ZoojDarman\ClientPortal\zdp_user_email( (int) $booking->user_id );
		}

		$user = get_userdata( (int) $booking->user_id );

		return $user && is_email( $user->user_email ) ? (string) $user->user_email : '';
	}

	public static function client_name( $booking ) {
		if ( ! is_object( $booking ) ) {
			return '—';
		}

		if ( ! empty( $booking->full_name ) ) {
			return (string) $booking->full_name;
		}

		$user = get_userdata( (int) $booking->user_id );

		return $user ? (string) $user->display_name : '—';
	}

	public static function module_url() {
		return add_query_arg(
			array(
				'page'   => PAS_DASHBOARD_SLUG,
				'module' => 'appointments',
			),
			admin_url( 'admin.php' )
		);
	}

	private static function booking() {
		return \ZoojDarman\ClientPortal\Plugin::instance()->booking();
	}

	/** یک پیام با شمارهٔ موبایل صاحبش (قرارداد عمومی پنل). */
	private static function message( $msg_id ) {
		$messages = \ZoojDarman\ClientPortal\Plugin::instance()->messages();

		if ( method_exists( $messages, 'get_message' ) ) {
			try {
				$row = $messages->get_message( (int) $msg_id );
			} catch ( Throwable $e ) {
				return null;
			}

			return is_object( $row ) ? $row : null;
		}

		// نسخهٔ قدیمی پنل: جست‌وجو در فهرست عمومی پیام‌ها.
		foreach ( self::messages( 300 ) as $row ) {
			if ( is_object( $row ) && (int) ( $row->id ?? 0 ) === (int) $msg_id ) {
				return $row;
			}
		}

		return null;
	}

	/** @return object|WP_Error */
	private static function editable_booking( $booking_id ) {
		$booking = self::get( $booking_id );

		if ( ! $booking ) {
			return new WP_Error( 'pas_dashboard_booking_missing', __( 'نوبت یافت نشد.', 'peyvand-amn-dashboard' ) );
		}

		if ( ! self::in_scope( $booking ) ) {
			return new WP_Error( 'pas_dashboard_booking_scope', __( 'این نوبت برای مراجعی نیست که در پرونده‌های شما باشد.', 'peyvand-amn-dashboard' ) );
		}

		return $booking;
	}

	/** @return array<int,string> */
	private static function scope_phones() {
		if ( null !== self::$scope_phones ) {
			return self::$scope_phones;
		}

		self::$scope_phones = array();

		foreach ( PAS_Dashboard_Core_Bridge::cases( get_current_user_id(), 500 ) as $case ) {
			if ( is_object( $case ) && ! empty( $case->phone ) ) {
				self::$scope_phones[] = (string) $case->phone;
			}
		}

		return self::$scope_phones;
	}

	/** @return string|WP_Error */
	private static function to_gregorian( $value ) {
		$value = trim( (string) $value );

		if ( $value === '' ) {
			return new WP_Error( 'pas_dashboard_booking_date', __( 'تاریخ جلسه (شمسی) الزامی است.', 'peyvand-amn-dashboard' ) );
		}

		if ( function_exists( 'ZoojDarman\\ClientPortal\\normalize_digits' ) ) {
			$value = \ZoojDarman\ClientPortal\normalize_digits( $value );
		}

		$gregorian = null;

		if ( class_exists( '\\ZoojDarman\\ClientPortal\\Jalali' ) && method_exists( '\\ZoojDarman\\ClientPortal\\Jalali', 'jalali_string_to_gregorian_string' ) ) {
			$gregorian = \ZoojDarman\ClientPortal\Jalali::jalali_string_to_gregorian_string( $value );
		}

		if ( ! is_string( $gregorian ) || $gregorian === '' ) {
			$gregorian = PAS_Dashboard_Core_Bridge::to_gregorian( $value );
		}

		if ( ! is_string( $gregorian ) || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $gregorian ) ) {
			return new WP_Error( 'pas_dashboard_booking_date', __( 'تاریخ جلسه معتبر نیست (نمونه: 1405/08/27).', 'peyvand-amn-dashboard' ) );
		}

		return $gregorian;
	}
}
