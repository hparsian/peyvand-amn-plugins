<?php
/**
 * پیام نتیجهٔ عمل — پس از redirect (POST/Redirect/GET).
 *
 * در transient کوتاه‌عمرِ هر کاربر ذخیره می‌شود تا رفرش صفحه پیام را تکرار
 * نکند و عملیات دوباره اجرا نشود.
 */

defined( 'ABSPATH' ) || exit;

final class PAS_Dashboard_Flash {

	const TTL = 90;

	public static function set( $message, $type = 'success' ) {
		$type = in_array( $type, array( 'success', 'warning', 'error', 'info' ), true ) ? $type : 'info';

		set_transient(
			self::key(),
			array(
				'message' => (string) $message,
				'type'    => $type,
			),
			self::TTL
		);
	}

	/** @return array{message:string,type:string}|null */
	public static function take() {
		$key  = self::key();
		$data = get_transient( $key );

		if ( ! is_array( $data ) || empty( $data['message'] ) ) {
			return null;
		}

		delete_transient( $key );

		return array(
			'message' => (string) $data['message'],
			'type'    => (string) ( $data['type'] ?? 'info' ),
		);
	}

	public static function render() {
		$flash = self::take();

		if ( null === $flash ) {
			return '';
		}

		return '<div class="pas-notice pas-notice-' . esc_attr( $flash['type'] ) . '" role="status"><p>'
			. esc_html( $flash['message'] ) . '</p></div>';
	}

	private static function key() {
		return 'pas_dash_flash_' . get_current_user_id();
	}
}
