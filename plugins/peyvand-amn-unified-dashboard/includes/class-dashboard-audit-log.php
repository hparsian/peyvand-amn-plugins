<?php
/**
 * لاگ ممیزی داشبورد.
 *
 * هرگز محتوای یادداشت، پاسخ آزمون، رمز، توکن، کلید رمزنگاری یا متن پیام
 * ذخیره نمی‌شود. فقط شناسه، نوع رخداد، نتیجه و هش IP (بدون IP خام).
 */

defined( 'ABSPATH' ) || exit;

final class PAS_Dashboard_Audit_Log {

	/**
	 * @param string $action      رخداد (مثل 'note.create')
	 * @param string $object_type نوع شیء (case|note|client|export|settings)
	 * @param int    $object_id   شناسه‌ی شیء (اختیاری)
	 * @param string $result      ok|denied|error
	 */
	public static function record( $action, $object_type = '', $object_id = 0, $result = 'ok' ) {
		global $wpdb;

		if ( ! PAS_Dashboard_Database::table_exists( PAS_Dashboard_Database::audit_table() ) ) {
			return;
		}

		$user_id = get_current_user_id();
		$ip      = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';

		$wpdb->insert(
			PAS_Dashboard_Database::audit_table(),
			array(
				'user_id'     => (int) $user_id,
				'action'      => substr( sanitize_key( (string) $action ), 0, 64 ),
				'object_type' => substr( sanitize_key( (string) $object_type ), 0, 32 ),
				'object_id'   => absint( $object_id ),
				'result'      => substr( sanitize_key( (string) $result ), 0, 20 ),
				'ip_hash'     => $ip !== '' ? md5( $ip . ( defined( 'AUTH_SALT' ) ? AUTH_SALT : 'pas' ) ) : '',
				'created_at'  => current_time( 'mysql', true ),
			),
			array( '%d', '%s', '%s', '%d', '%s', '%s', '%s' )
		);
	}

	/**
	 * @return array<int,object>
	 */
	public static function recent( $limit = 50, $offset = 0 ) {
		global $wpdb;

		$table = PAS_Dashboard_Database::audit_table();
		if ( ! PAS_Dashboard_Database::table_exists( $table ) ) {
			return array();
		}

		$limit  = max( 1, min( 200, (int) $limit ) );
		$offset = max( 0, (int) $offset );

		return (array) $wpdb->get_results(
			$wpdb->prepare(
				'SELECT id, user_id, action, object_type, object_id, result, created_at FROM %i ORDER BY id DESC LIMIT %d OFFSET %d',
				$table,
				$limit,
				$offset
			)
		);
	}

	public static function count() {
		global $wpdb;

		$table = PAS_Dashboard_Database::audit_table();
		if ( ! PAS_Dashboard_Database::table_exists( $table ) ) {
			return 0;
		}

		return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i', $table ) );
	}

	/** رخدادهای یک کاربر مشخص — برای بررسی سوءاستفاده. */
	public static function for_user( $user_id, $limit = 50 ) {
		global $wpdb;

		$table = PAS_Dashboard_Database::audit_table();
		if ( ! PAS_Dashboard_Database::table_exists( $table ) ) {
			return array();
		}

		return (array) $wpdb->get_results(
			$wpdb->prepare(
				'SELECT id, action, object_type, object_id, result, created_at FROM %i WHERE user_id = %d ORDER BY id DESC LIMIT %d',
				$table,
				(int) $user_id,
				max( 1, min( 200, (int) $limit ) )
			)
		);
	}
}
