<?php
/**
 * اعتبارسنجی و پاک‌سازی ورودی‌ها — همه‌ی مقادیر ورودی از این‌جا می‌گذرند.
 */

defined( 'ABSPATH' ) || exit;

final class PAS_Dashboard_Validation {

	/** شناسه‌ی مثبت؛ نامعتبر یا منفی → 0 (منفی هرگز به شناسه‌ی دیگر نگاشت نمی‌شود) */
	public static function id( $value ) {
		if ( ! is_scalar( $value ) ) {
			return 0;
		}

		$id = (int) $value;

		return $id > 0 ? $id : 0;
	}

	/** کلید امن (حروف کوچک، عدد، خط تیره، زیرخط) */
	public static function key( $value, $max = 40 ) {
		$key = sanitize_key( is_scalar( $value ) ? (string) $value : '' );

		return substr( $key, 0, $max );
	}

	/** یک خط متن */
	public static function text( $value, $max = 190 ) {
		$text = sanitize_text_field( is_scalar( $value ) ? (string) $value : '' );

		return mb_substr( $text, 0, $max );
	}

	/** متن چندخطی */
	public static function textarea( $value, $max = 20000 ) {
		$text = sanitize_textarea_field( is_scalar( $value ) ? (string) $value : '' );

		return mb_substr( $text, 0, $max );
	}

	/** تاریخ میلادی YYYY-MM-DD؛ نامعتبر → '' */
	public static function date( $value ) {
		$value = trim( is_scalar( $value ) ? (string) $value : '' );

		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value ) ) {
			return '';
		}

		$parts = array_map( 'intval', explode( '-', $value ) );
		if ( ! checkdate( $parts[1], $parts[2], $parts[0] ) ) {
			return '';
		}

		return $value;
	}

	/** صفحه‌ی جاری */
	public static function page() {
		$page = isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1;

		return max( 1, $page );
	}

	/** تعداد در صفحه */
	public static function per_page( $default = 20 ) {
		$default = max( 5, min( PAS_DASHBOARD_MAX_PER_PAGE, (int) $default ) );

		return $default;
	}

	/** ایمیل */
	public static function email( $value ) {
		$email = sanitize_email( is_scalar( $value ) ? (string) $value : '' );

		return is_email( $email ) ? $email : '';
	}

	/** شماره موبایل ایران؛ نرمال‌شده با هسته در صورت وجود */
	public static function mobile( $value ) {
		$value = is_scalar( $value ) ? (string) $value : '';

		if ( class_exists( 'Pas_Core_Helpers' ) && method_exists( 'Pas_Core_Helpers', 'normalize_mobile' ) ) {
			$value = (string) Pas_Core_Helpers::normalize_mobile( $value );
		}

		return preg_match( '/^09\d{9}$/', $value ) ? $value : '';
	}

	/** چک‌باکس POST — فقط مقدار «۱» پذیرفته می‌شود. */
	public static function checkbox( $key ) {
		if ( ! isset( $_POST[ $key ] ) ) {
			return false;
		}

		return '1' === sanitize_text_field( wp_unslash( $_POST[ $key ] ) );
	}

	/** پرچم GET — فقط برای نمایش پیام «ذخیره شد»؛ مقدار مهم نیست. */
	public static function flag( $key ) {
		return isset( $_GET[ $key ] ) && '1' === sanitize_text_field( wp_unslash( $_GET[ $key ] ) );
	}

	/** وضعیت مجاز (لیست سفید) */
	public static function choice( $value, array $allowed, $default = '' ) {
		$value = self::key( $value );

		return in_array( $value, $allowed, true ) ? $value : $default;
	}
}
