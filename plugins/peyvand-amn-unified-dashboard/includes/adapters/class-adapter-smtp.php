<?php
/**
 * آداپتر SMTP — وضعیت تنظیمات، تست ارسال، صف ایمیل و تلاش دوباره.
 *
 * همه‌چیز از متدهای عمومی افزونهٔ SMTP خوانده/نوشته می‌شود و هیچ‌گاه ستون
 * `payload` (متن کامل ایمیل) به رابط داشبورد نمی‌رسد؛ فقط آدرس گیرنده، موضوع،
 * وضعیت، تعداد تلاش و آخرین خطا.
 */

defined( 'ABSPATH' ) || exit;

final class PAS_Dashboard_Adapter_Smtp {

	/** سقف تست ارسال هر کاربر در ساعت. */
	private const TEST_RATE_LIMIT = 10;

	public static function available() {
		return class_exists( '\Pivand\SmtpMailer\ZSM_Mail_Queue' )
			&& class_exists( '\Pivand\SmtpMailer\ZSM_Smtp_Mailer' )
			&& method_exists( '\Pivand\SmtpMailer\ZSM_Mail_Queue', 'dashboard_queue' );
	}

	/** @return array{send_method:string,from_email:string,configured:bool,queue_table:bool}|null */
	public static function status() {
		if ( ! self::available() || ! method_exists( '\Pivand\SmtpMailer\ZSM_Smtp_Mailer', 'dashboard_status' ) ) {
			return null;
		}

		try {
			$status = \Pivand\SmtpMailer\ZSM_Smtp_Mailer::dashboard_status();
		} catch ( Throwable $e ) {
			return null;
		}

		return is_array( $status ) ? $status : null;
	}

	/** @return array{pending:int,sent:int,given_up:int} */
	public static function summary() {
		if ( ! self::available() || ! method_exists( '\Pivand\SmtpMailer\ZSM_Mail_Queue', 'dashboard_summary' ) ) {
			return array( 'pending' => 0, 'sent' => 0, 'given_up' => 0 );
		}

		try {
			$summary = \Pivand\SmtpMailer\ZSM_Mail_Queue::dashboard_summary();
		} catch ( Throwable $e ) {
			return array( 'pending' => 0, 'sent' => 0, 'given_up' => 0 );
		}

		return is_array( $summary ) ? $summary : array( 'pending' => 0, 'sent' => 0, 'given_up' => 0 );
	}

	/** @return array<int,object> */
	public static function queue( $limit = 30 ) {
		if ( ! self::available() ) {
			return array();
		}

		try {
			$rows = \Pivand\SmtpMailer\ZSM_Mail_Queue::dashboard_queue( max( 1, min( 100, (int) $limit ) ) );
		} catch ( Throwable $e ) {
			return array();
		}

		return is_array( $rows ) ? $rows : array();
	}

	/** @return array|WP_Error */
	public static function send_test( array $post ) {
		if ( ! self::available() || ! method_exists( '\Pivand\SmtpMailer\ZSM_Smtp_Mailer', 'dashboard_send_test' ) ) {
			return new WP_Error( 'pas_dashboard_no_smtp', __( 'قرارداد SMTP در افزونهٔ ارسال ایمیل در دسترس نیست (نسخهٔ ۱.۵.۶+).', 'peyvand-amn-dashboard' ) );
		}

		$email = PAS_Dashboard_Validation::email( $post['email'] ?? '' );

		if ( $email === '' ) {
			return new WP_Error( 'pas_dashboard_email_invalid', __( 'ایمیل واردشده معتبر نیست.', 'peyvand-amn-dashboard' ) );
		}

		if ( self::rate_limited( 'pas_dash_email_test_rate_', self::TEST_RATE_LIMIT ) ) {
			return new WP_Error(
				'pas_dashboard_email_rate',
				sprintf(
					/* translators: %d: hourly limit */
					__( 'سقف تست ایمیل در هر ساعت (%d بار برای هر کاربر) پر شده است.', 'peyvand-amn-dashboard' ),
					self::TEST_RATE_LIMIT
				)
			);
		}

		try {
			$result = \Pivand\SmtpMailer\ZSM_Smtp_Mailer::dashboard_send_test( $email );
		} catch ( Throwable $e ) {
			$result = new WP_Error( 'pas_dashboard_email_failed', __( 'ارسال ایمیل تست ناموفق بود.', 'peyvand-amn-dashboard' ) );
		}

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return array(
			'ok'       => true,
			'message'  => sprintf(
				/* translators: %s: email address */
				__( 'ایمیل تست به %s ارسال شد.', 'peyvand-amn-dashboard' ),
				$email
			),
			'id'       => 0,
			'redirect' => self::module_url(),
		);
	}

	/** @return array|WP_Error */
	public static function retry( array $post ) {
		if ( ! self::available() || ! method_exists( '\Pivand\SmtpMailer\ZSM_Mail_Queue', 'dashboard_retry' ) ) {
			return new WP_Error( 'pas_dashboard_no_smtp', __( 'قرارداد صف ایمیل در افزونهٔ ارسال ایمیل در دسترس نیست (نسخهٔ ۱.۵.۶+).', 'peyvand-amn-dashboard' ) );
		}

		$id = PAS_Dashboard_Validation::id( $post['queue_id'] ?? 0 );

		if ( $id <= 0 ) {
			return new WP_Error( 'pas_dashboard_queue_missing', __( 'ردیف صف مشخص نشده است.', 'peyvand-amn-dashboard' ) );
		}

		try {
			$result = \Pivand\SmtpMailer\ZSM_Mail_Queue::dashboard_retry( $id );
		} catch ( Throwable $e ) {
			return new WP_Error( 'pas_dashboard_queue_failed', __( 'تلاش دوبارهٔ ارسال ناموفق بود.', 'peyvand-amn-dashboard' ) );
		}

		if ( ! is_array( $result ) || empty( $result['ok'] ) ) {
			$message = is_array( $result ) && ! empty( $result['message'] )
				? (string) $result['message']
				: __( 'تلاش دوبارهٔ ارسال ناموفق بود.', 'peyvand-amn-dashboard' );

			return new WP_Error( 'pas_dashboard_queue_failed', $message );
		}

		return array(
			'ok'       => true,
			'message'  => __( 'ایمیل با موفقیت ارسال شد.', 'peyvand-amn-dashboard' ),
			'id'       => $id,
			'redirect' => self::module_url(),
		);
	}

	private static function rate_limited( $prefix, $limit ) {
		$user_id = get_current_user_id();

		if ( $user_id <= 0 ) {
			return true;
		}

		$key   = $prefix . $user_id;
		$count = (int) get_transient( $key );

		if ( $count >= $limit ) {
			return true;
		}

		set_transient( $key, $count + 1, HOUR_IN_SECONDS );

		return false;
	}

	public static function module_url() {
		return add_query_arg(
			array(
				'page'   => PAS_DASHBOARD_SLUG,
				'module' => 'communications',
			),
			admin_url( 'admin.php' )
		);
	}
}
