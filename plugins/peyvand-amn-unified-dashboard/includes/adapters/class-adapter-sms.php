<?php
/**
 * آداپتر پیامک الگومحور — فقط رجیستری الگوی پل پیامک.
 *
 * متن آزاد هیچ‌جا ساخته نمی‌شود: bodyId همیشه از `Pas_Patterns` می‌آید و
 * داشبورد فقط slug الگو و مقادیر جای‌نگهدار را می‌فرستد. دامنهٔ پرونده (موبایل
 * مراجعان کاربر) و سقف ارسال ساعتی پیش از فراخوانی مالک بررسی می‌شوند.
 */

defined( 'ABSPATH' ) || exit;

final class PAS_Dashboard_Adapter_Sms {

	/** سقف ارسال هر کاربر در ساعت — برای جلوگیری از سوءاستفاده از حساب پیامک. */
	private const RATE_LIMIT = 30;

	public static function available() {
		return class_exists( 'Pas_Patterns' )
			&& method_exists( 'Pas_Patterns', 'dashboard_patterns' )
			&& method_exists( 'Pas_Patterns', 'send' )
			&& function_exists( 'apply_filters' );
	}

	/** @return array<string,array> الگوهای مجاز داشبورد با bodyId موجود */
	public static function patterns() {
		if ( ! self::available() ) {
			return array();
		}

		try {
			$patterns = \Pas_Patterns::dashboard_patterns();
		} catch ( Throwable $e ) {
			return array();
		}

		return is_array( $patterns ) ? $patterns : array();
	}

	/** برچسب الگو بر اساس bodyId برای نمایش در لاگ. */
	public static function pattern_label( $body_id ) {
		if ( ! self::available() || ! method_exists( 'Pas_Patterns', 'label_for_body_id' ) ) {
			return '';
		}

		try {
			return (string) \Pas_Patterns::label_for_body_id( (int) $body_id );
		} catch ( Throwable $e ) {
			return '';
		}
	}

	/** @return array<int,object> */
	public static function recent( $limit = 30 ) {
		if ( ! class_exists( 'Pas_Log' ) || ! method_exists( 'Pas_Log', 'recent' ) ) {
			return array();
		}

		try {
			$rows = \Pas_Log::recent( max( 1, min( 100, (int) $limit ) ) );
		} catch ( Throwable $e ) {
			return array();
		}

		return is_array( $rows ) ? $rows : array();
	}

	/** آیا لاگ پل پیامک در دسترس است؟ */
	public static function log_available() {
		return class_exists( 'Pas_Log' ) && method_exists( 'Pas_Log', 'recent' );
	}

	/**
	 * ارسال یک پیامک الگومحور.
	 *
	 * @return array|WP_Error
	 */
	public static function send( array $post ) {
		if ( ! self::available() ) {
			return new WP_Error( 'pas_dashboard_no_sms', __( 'قرارداد الگوی پیامک در افزونهٔ پل پیامک در دسترس نیست (نسخهٔ ۱.۱.۹+).', 'peyvand-amn-dashboard' ) );
		}

		$slug    = PAS_Dashboard_Validation::key( $post['pattern'] ?? '', 40 );
		$pattern = $slug !== '' ? self::pattern( $slug ) : null;

		// الگوی ناشناخته یا غیرمجاز برای داشبورد (مثل OTP) هرگز ارسال نمی‌شود.
		if ( ! $pattern ) {
			return new WP_Error( 'pas_dashboard_sms_pattern', __( 'الگوی پیامک انتخاب‌شده معتبر نیست.', 'peyvand-amn-dashboard' ) );
		}

		$mobile = PAS_Dashboard_Validation::mobile( $post['mobile'] ?? '' );

		if ( $mobile === '' ) {
			return new WP_Error( 'pas_dashboard_sms_mobile', __( 'شمارهٔ موبایل معتبر نیست.', 'peyvand-amn-dashboard' ) );
		}

		if ( ! PAS_Dashboard_Adapter_Portal::can_view_phone( $mobile ) ) {
			return new WP_Error( 'pas_dashboard_sms_scope', __( 'این شماره برای مراجعان پرونده‌های شما نیست.', 'peyvand-amn-dashboard' ) );
		}

		if ( self::rate_limited() ) {
			return new WP_Error(
				'pas_dashboard_sms_rate',
				sprintf(
					/* translators: %d: hourly limit */
					__( 'سقف ارسال پیامک در هر ساعت (%d پیام برای هر کاربر) پر شده است.', 'peyvand-amn-dashboard' ),
					self::RATE_LIMIT
				)
			);
		}

		$params = array();

		foreach ( array( 'param1', 'param2', 'param3' ) as $field ) {
			$value = PAS_Dashboard_Validation::text( $post[ $field ] ?? '', 80 );

			if ( $value !== '' ) {
				$params[] = $value;
			}
		}

		try {
			$sent = (bool) \Pas_Patterns::send( $slug, $mobile, $params );
		} catch ( Throwable $e ) {
			$sent = false;
		}

		if ( ! $sent ) {
			return new WP_Error( 'pas_dashboard_sms_failed', __( 'ارسال پیامک ناموفق بود؛ علت دقیق در لاگ پل پیامک ثبت شد.', 'peyvand-amn-dashboard' ) );
		}

		return array(
			'ok'       => true,
			'message'  => sprintf(
				/* translators: %s: pattern label */
				__( 'پیامک با الگوی «%s» ارسال شد.', 'peyvand-amn-dashboard' ),
				(string) $pattern['label']
			),
			'id'       => 0,
			'redirect' => self::module_url(),
		);
	}

	private static function pattern( $slug ) {
		foreach ( self::patterns() as $key => $pattern ) {
			if ( (string) $key === (string) $slug && ! empty( $pattern['body_id'] ) ) {
				return $pattern;
			}
		}

		return null;
	}

	/** سقف ساعتی هر کاربر؛ فقط تلاش‌ها شمرده می‌شوند. */
	private static function rate_limited() {
		$user_id = get_current_user_id();

		if ( $user_id <= 0 ) {
			return true;
		}

		$key   = 'pas_dash_sms_rate_' . $user_id;
		$count = (int) get_transient( $key );

		if ( $count >= self::RATE_LIMIT ) {
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
