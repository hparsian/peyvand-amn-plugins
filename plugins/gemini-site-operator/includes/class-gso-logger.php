<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * گزارش عملیات در جدول اختصاصی.
 */
class GSO_Logger {

	/**
	 * ثبت یک رویداد.
	 *
	 * @param string $level   info|warning|error|success.
	 * @param string $action  نام ماشینی رویداد.
	 * @param string $message پیام فارسی.
	 * @param array  $context داده تکمیلی (بدون توکن/کلید).
	 */
	public static function log( string $level, string $action, string $message, array $context = array() ): void {
		global $wpdb;
		$table = GSO_Install::table( 'logs' );

		$wpdb->insert(
			$table,
			array(
				'level'      => substr( sanitize_key( $level ), 0, 10 ),
				'action'     => substr( sanitize_text_field( $action ), 0, 80 ),
				'message'    => wp_kses_post( $message ),
				'context'    => wp_json_encode( self::scrub( $context ) ),
				'user_id'    => get_current_user_id(),
				'created_at' => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%s', '%s', '%d', '%s' )
		);
	}

	/**
	 * حذف مقادیر حساس از context.
	 *
	 * @param array $context داده.
	 * @return array
	 */
	private static function scrub( array $context ): array {
		foreach ( $context as $key => $value ) {
			if ( preg_match( '/key|token|secret|authorization/i', (string) $key ) ) {
				$context[ $key ] = '[redacted]';
			} elseif ( is_array( $value ) ) {
				$context[ $key ] = self::scrub( $value );
			}
		}
		return $context;
	}

	/**
	 * آخرین رویدادها.
	 *
	 * @param int    $limit تعداد.
	 * @param string $level فیلتر سطح.
	 * @return array
	 */
	public static function recent( int $limit = 50, string $level = '' ): array {
		global $wpdb;
		$table = GSO_Install::table( 'logs' );
		$limit = max( 1, min( 500, $limit ) );

		if ( '' !== $level ) {
			return (array) $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$wpdb->prepare( "SELECT * FROM {$table} WHERE level = %s ORDER BY id DESC LIMIT %d", $level, $limit )
			);
		}
		return (array) $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare( "SELECT * FROM {$table} ORDER BY id DESC LIMIT %d", $limit )
		);
	}

	/**
	 * شمارش لاگ‌ها.
	 *
	 * @return int
	 */
	public static function count(): int {
		global $wpdb;
		$table = GSO_Install::table( 'logs' );
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	/**
	 * پاک‌سازی لاگ‌ها.
	 */
	public static function clear(): void {
		global $wpdb;
		$table = GSO_Install::table( 'logs' );
		$wpdb->query( "TRUNCATE TABLE {$table}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}
}
