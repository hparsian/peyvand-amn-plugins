<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * محدودکننده نرخ مشترک Gemini — پنجره دقیقه‌ای، سهمیه روزانه، Backoff نمایی و Jitter.
 *
 * همه‌ی شمارنده‌ها در آپشن/ترنزینت نگهداری می‌شوند تا بین کران، AJAX و اجرای دستی مشترک باشند.
 */
class GSO_Limiter {

	const BACKOFF_OPTION = 'gso_backoff_state';
	const QUOTA_OPTION   = 'gso_quota_until';
	const DAILY_PREFIX   = 'gso_daily_';
	const MINUTE_PREFIX  = 'gso_rl_';

	/**
	 * سقف درخواست در دقیقه.
	 *
	 * @return int
	 */
	public static function minute_limit(): int {
		return max( 1, min( 120, (int) GSO_Settings::get( 'rate_limit_per_min', 8 ) ) );
	}

	/**
	 * سقف درخواست در روز (۰ = بی‌نهایت).
	 *
	 * @return int
	 */
	public static function daily_limit(): int {
		return max( 0, min( 100000, (int) GSO_Settings::get( 'daily_request_limit', 400 ) ) );
	}

	/**
	 * حداکثر تأخیر Backoff (ثانیه).
	 *
	 * @return int
	 */
	public static function max_backoff(): int {
		return max( 30, min( 3600, (int) GSO_Settings::get( 'backoff_max_seconds', 600 ) ) );
	}

	/**
	 * کلید شمارنده روز جاری.
	 *
	 * @return string
	 */
	private static function daily_key(): string {
		return self::DAILY_PREFIX . current_time( 'Ymd' );
	}

	/**
	 * کلید شمارنده دقیقه جاری.
	 *
	 * @return string
	 */
	private static function minute_key(): string {
		return self::MINUTE_PREFIX . gmdate( 'YmdHi' );
	}

	/**
	 * درخواست‌های امروز.
	 *
	 * @return int
	 */
	public static function daily_used(): int {
		return (int) get_option( self::daily_key(), 0 );
	}

	/**
	 * درخواست‌های دقیقه جاری.
	 *
	 * @return int
	 */
	public static function minute_used(): int {
		return (int) get_transient( self::minute_key() );
	}

	/**
	 * ثانیه‌های باقی‌مانده تا ریست پنجره دقیقه.
	 *
	 * @return int
	 */
	public static function minute_reset_in(): int {
		return max( 1, 60 - ( (int) gmdate( 's' ) + (int) gmdate( 'i' ) * 60 ) % 60 );
	}

	/**
	 * ثانیه‌های باقی‌مانده تا نیمه‌شب سایت (ریست سهمیه روزانه).
	 *
	 * @return int
	 */
	public static function daily_reset_in(): int {
		$now      = (int) current_time( 'timestamp' );
		$tomorrow = strtotime( 'tomorrow', $now );
		if ( false === $tomorrow ) {
			return DAY_IN_SECONDS;
		}
		return max( 60, $tomorrow - $now );
	}

	/**
	 * زمان پایان مکث سهمیه (timestamp)؛ ۰ یعنی مکثی نیست.
	 *
	 * @return int
	 */
	public static function quota_until(): int {
		$until = (int) get_option( self::QUOTA_OPTION, 0 );
		return $until > time() ? $until : 0;
	}

	/**
	 * زمان مجاز درخواست بعدی بر اساس Backoff فعلی.
	 *
	 * @return int
	 */
	public static function backoff_until(): int {
		$state = get_option( self::BACKOFF_OPTION, array() );
		if ( ! is_array( $state ) || empty( $state['until'] ) ) {
			return 0;
		}
		$until = (int) $state['until'];
		return $until > time() ? $until : 0;
	}

	/**
	 * سطح فعلی Backoff.
	 *
	 * @return int
	 */
	public static function backoff_level(): int {
		$state = get_option( self::BACKOFF_OPTION, array() );
		return is_array( $state ) ? max( 0, (int) ( $state['level'] ?? 0 ) ) : 0;
	}

	/**
	 * زودترین زمان مجاز برای درخواست بعدی.
	 *
	 * @return int
	 */
	public static function next_attempt_at(): int {
		return max( self::quota_until(), self::backoff_until() );
	}

	/**
	 * بررسی مجوز ارسال یک درخواست.
	 *
	 * @param bool $block    آیا در حالت دستی تا رفع فاصله صبر کند.
	 * @param int  $deadline زمان پایان بودجه (timestamp)؛ ۰ = بی‌نهایت.
	 * @return true|WP_Error
	 */
	public static function check( bool $block = false, int $deadline = 0 ) {
		$quota_until = self::quota_until();
		if ( $quota_until > 0 ) {
			return new WP_Error(
				'gso_quota',
				'سهمیه روزانه Gemini تمام شده است؛ صف تا ریست سهمیه متوقف می‌ماند.',
				array( 'retryable' => true, 'quota' => true, 'retry_after' => $quota_until - time() )
			);
		}

		$daily_limit = self::daily_limit();
		if ( $daily_limit > 0 && self::daily_used() >= $daily_limit ) {
			$wait = self::daily_reset_in();
			self::pause_for_quota( $wait );
			return new WP_Error(
				'gso_quota',
				'سقف درخواست روزانه تنظیم‌شده پر شده است؛ صف تا فردا متوقف می‌ماند.',
				array( 'retryable' => true, 'quota' => true, 'retry_after' => $wait )
			);
		}

		$backoff = self::backoff_until();
		if ( $backoff > 0 ) {
			return new WP_Error(
				'gso_rate_limited',
				'Backoff فعال است؛ تلاش بعدی کمی بعد انجام می‌شود.',
				array( 'retryable' => true, 'retry_after' => $backoff - time() )
			);
		}

		$limit = self::minute_limit();
		if ( self::minute_used() >= $limit ) {
			return new WP_Error(
				'gso_rate_limited',
				'سقف درخواست در دقیقه پر شده است؛ چند لحظه بعد دوباره تلاش می‌شود.',
				array( 'retryable' => true, 'retry_after' => self::minute_reset_in() )
			);
		}

		$delay_ms = max( 0, (int) GSO_Settings::get( 'request_delay_ms', 1200 ) );
		$last     = (float) get_option( 'gso_last_request_at', 0 );
		$wait     = ( $last + ( $delay_ms / 1000 ) ) - microtime( true );

		if ( $wait > 0 ) {
			$wait_s = (int) ceil( $wait );
			if ( ! $block || ( $deadline > 0 && ( time() + $wait_s ) > $deadline ) ) {
				return new WP_Error(
					'gso_rate_limited',
					'فاصله لازم بین درخواست‌ها رعایت نشد.',
					array( 'retryable' => true, 'retry_after' => max( 1, $wait_s ) )
				);
			}
			usleep( (int) min( 2000000, $wait * 1000000 ) );
		}

		return true;
	}

	/**
	 * ثبت یک درخواست ارسال‌شده در پنجره دقیقه و روز.
	 */
	public static function note_request(): void {
		$minute = self::minute_key();
		set_transient( $minute, (int) get_transient( $minute ) + 1, 2 * MINUTE_IN_SECONDS );

		$daily_key = self::daily_key();
		update_option( $daily_key, (int) get_option( $daily_key, 0 ) + 1, false );

		// پاک‌سازی شمارنده‌های روزهای گذشته.
		$previous = self::DAILY_PREFIX . gmdate( 'Ymd', time() - DAY_IN_SECONDS );
		if ( $previous !== $daily_key ) {
			delete_option( $previous );
		}

		update_option( 'gso_last_request_at', microtime( true ), false );
	}

	/**
	 * ثبت پاسخ موفق و پاک‌کردن Backoff.
	 */
	public static function register_success(): void {
		if ( get_option( self::BACKOFF_OPTION ) ) {
			delete_option( self::BACKOFF_OPTION );
		}
	}

	/**
	 * ثبت شکست موقت و افزایش نمایی تأخیر با Jitter.
	 *
	 * @param int    $code    کد HTTP.
	 * @param string $message پیام خطا.
	 * @return int ثانیه‌های تأخیر اعمال‌شده.
	 */
	public static function register_failure( int $code = 0, string $message = '' ): int {
		if ( 429 === $code && preg_match( '/(per\s*day|daily|perday|quota)/i', $message ) ) {
			self::pause_for_quota( self::daily_reset_in() );
		}

		$level = min( 8, self::backoff_level() + 1 );
		$base  = min( self::max_backoff(), 2 * (int) pow( 2, $level - 1 ) );
		$delay = min( self::max_backoff(), $base + wp_rand( 0, max( 1, (int) ( $base / 4 ) ) ) );

		update_option(
			self::BACKOFF_OPTION,
			array(
				'level'  => $level,
				'until'  => time() + max( 1, $delay ),
				'code'   => $code,
				'reason' => sanitize_text_field( substr( $message, 0, 200 ) ),
			),
			false
		);

		return max( 1, $delay );
	}

	/**
	 * مکث سهمیه روزانه تا زمان مشخص.
	 *
	 * @param int $seconds ثانیه تا ریست.
	 */
	public static function pause_for_quota( int $seconds = 0 ): void {
		$seconds = $seconds > 0 ? $seconds : self::daily_reset_in();
		update_option( self::QUOTA_OPTION, time() + $seconds, false );
	}

	/**
	 * پاک‌کردن وضعیت محدودیت (پس از تغییر تنظیمات یا موفقیت).
	 */
	public static function clear_backoff(): void {
		delete_option( self::BACKOFF_OPTION );
		delete_transient( self::minute_key() );
		delete_option( self::QUOTA_OPTION );
	}

	/**
	 * وضعیت برای نمایش در پیشخوان و پاسخ AJAX.
	 *
	 * @return array
	 */
	public static function status(): array {
		$backoff = get_option( self::BACKOFF_OPTION, array() );
		$backoff = is_array( $backoff ) ? $backoff : array();
		return array(
			'minute_used'     => self::minute_used(),
			'minute_limit'    => self::minute_limit(),
			'minute_reset_in' => self::minute_reset_in(),
			'daily_used'      => self::daily_used(),
			'daily_limit'     => self::daily_limit(),
			'daily_reset_in'  => self::daily_reset_in(),
			'backoff_level'   => self::backoff_level(),
			'backoff_until'   => self::backoff_until(),
			'backoff_reason'  => (string) ( $backoff['reason'] ?? '' ),
			'quota_until'     => self::quota_until(),
			'next_attempt'    => self::next_attempt_at(),
		);
	}
}
