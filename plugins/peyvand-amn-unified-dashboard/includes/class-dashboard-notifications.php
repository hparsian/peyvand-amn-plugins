<?php
/**
 * اعلان‌های داشبورد — بدون هیچ داده‌ی شناسایی‌کننده در متن اعلان.
 *
 * متن اعلان فقط «پرونده #۱۲» را ذکر می‌کند، نه نام مراجع یا متن یادداشت.
 */

defined( 'ABSPATH' ) || exit;

final class PAS_Dashboard_Notifications {

	private static function table() {
		return PAS_Dashboard_Database::notifications_table();
	}

	/**
	 * همگام‌سازی اعلان‌های کاربر بر اساس پرونده‌های مجاز او.
	 * idempotent است (dedupe_key یکتا).
	 *
	 * @param int            $user_id
	 * @param array<int,obj> $cases
	 */
	public static function sync( $user_id, array $cases ) {
		global $wpdb;

		$user_id = (int) $user_id;
		$table   = self::table();

		if ( $user_id <= 0 || ! PAS_Dashboard_Database::table_exists( $table ) ) {
			return;
		}

		$now      = current_time( 'mysql', true );
		$limit_ts = time() + ( 7 * DAY_IN_SECONDS );

		foreach ( $cases as $case ) {
			if ( ! is_object( $case ) || empty( $case->id ) ) {
				continue;
			}

			$next = PAS_Dashboard_Core_Bridge::next_session( $case );

			if ( ! $next || empty( $next->next_session_date ) ) {
				continue;
			}

			$ts = strtotime( (string) $next->next_session_date . ' 00:00:00' );

			if ( $ts === false || $ts < time() - DAY_IN_SECONDS || $ts > $limit_ts ) {
				continue;
			}

			$key = md5( 'upcoming|' . $user_id . '|' . (int) $case->id . '|' . (string) $next->next_session_date );

			$wpdb->query(
				$wpdb->prepare(
					"INSERT INTO $table (user_id, dedupe_key, type, title, ref, created_at, read_at)
					 VALUES (%d, %s, %s, %s, %s, %s, NULL)
					 ON DUPLICATE KEY UPDATE title = VALUES(title)",
					$user_id,
					$key,
					'upcoming_session',
					sprintf(
						/* translators: %d: case id */
						__( 'جلسه‌ی آینده برای پرونده #%d', 'peyvand-amn-dashboard' ),
						(int) $case->id
					),
					(string) (int) $case->id,
					$now
				)
			);
		}
	}

	public static function unread_count( $user_id ) {
		global $wpdb;

		$table = self::table();
		if ( ! PAS_Dashboard_Database::table_exists( $table ) ) {
			return 0;
		}

		return (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(*) FROM $table WHERE user_id = %d AND read_at IS NULL", (int) $user_id )
		);
	}

	/** @return array<int,object> */
	public static function recent( $user_id, $limit = 20 ) {
		global $wpdb;

		$table = self::table();
		if ( ! PAS_Dashboard_Database::table_exists( $table ) ) {
			return array();
		}

		return (array) $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, type, title, ref, created_at, read_at FROM $table WHERE user_id = %d ORDER BY id DESC LIMIT %d",
				(int) $user_id,
				max( 1, min( 100, (int) $limit ) )
			)
		);
	}

	/** علامت‌گذاری یک اعلان به‌عنوان خوانده‌شده — با بررسی مالکیت. */
	public static function mark_read( $notification_id, $user_id ) {
		global $wpdb;

		$notification_id = (int) $notification_id;
		$user_id         = (int) $user_id;

		if ( $notification_id <= 0 || $user_id <= 0 ) {
			return false;
		}

		$table = self::table();
		if ( ! PAS_Dashboard_Database::table_exists( $table ) ) {
			return false;
		}

		$owner = (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT user_id FROM $table WHERE id = %d", $notification_id )
		);

		if ( $owner !== $user_id ) {
			PAS_Dashboard_Security::record_denied( 'notification.mark_read', 'notification', $notification_id );
			return false;
		}

		$wpdb->update( $table, array( 'read_at' => current_time( 'mysql', true ) ), array( 'id' => $notification_id ), array( '%s' ), array( '%d' ) );

		return true;
	}

	public static function mark_all_read( $user_id ) {
		global $wpdb;

		$table = self::table();
		if ( ! PAS_Dashboard_Database::table_exists( $table ) ) {
			return 0;
		}

		return (int) $wpdb->query(
			$wpdb->prepare( "UPDATE $table SET read_at = %s WHERE user_id = %d AND read_at IS NULL", current_time( 'mysql', true ), (int) $user_id )
		);
	}
}
