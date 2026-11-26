<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * اسکیمای دیتابیس، فعال/غیرفعال‌سازی و زمان‌بندی‌ها.
 */
class GSO_Install {

	const DB_OPTION = 'gso_db_version';

	/**
	 * نام جدول با پیشوند.
	 *
	 * @param string $name نام کوتاه.
	 * @return string
	 */
	public static function table( string $name ): string {
		global $wpdb;
		return $wpdb->prefix . 'gso_' . $name;
	}

	public static function activate(): void {
		self::create_tables();
		update_option( 'gso_db_version', GSO_DB_VERSION, false );

		$existing = get_option( GSO_Settings::OPTION );
		if ( ! is_array( $existing ) ) {
			add_option( GSO_Settings::OPTION, GSO_Settings::defaults(), '', false );
		}

		self::schedule_events();
		GSO_Logger::log( 'info', 'plugin.activate', 'افزونه فعال شد.', array( 'version' => GSO_VERSION ) );
	}

	public static function deactivate(): void {
		foreach ( array( 'gso_queue_tick', 'gso_queue_followup', 'gso_scan_tick', 'gso_scan_followup', 'gso_watch_tick', 'gso_schedule_scan' ) as $hook ) {
			wp_clear_scheduled_hook( $hook );
		}
		delete_transient( 'gso_api_test' );
		delete_transient( GSO_Queue::LOCK );
		delete_option( GSO_Limiter::BACKOFF_OPTION );
		delete_option( GSO_Limiter::QUOTA_OPTION );
		GSO_Logger::log( 'info', 'plugin.deactivate', 'افزونه غیرفعال شد.' );
	}

	/**
	 * ارتقای اسکیما برای نصب‌های موجود.
	 */
	public static function maybe_upgrade(): void {
		$current = get_option( self::DB_OPTION, '0' );
		if ( version_compare( $current, GSO_DB_VERSION, '>=' ) ) {
			return;
		}
		self::create_tables();
		update_option( self::DB_OPTION, GSO_DB_VERSION, false );
		self::schedule_events();
	}

	/**
	 * ساخت/به‌روزرسانی چهار جدول افزونه.
	 */
	public static function create_tables(): void {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset = $wpdb->get_charset_collate();
		$issues  = self::table( 'issues' );
		$queue   = self::table( 'queue' );
		$snaps   = self::table( 'snapshots' );
		$logs    = self::table( 'logs' );

		$sql = array();

		$sql[] = "CREATE TABLE {$issues} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			fingerprint varchar(64) NOT NULL DEFAULT '',
			object_id bigint(20) unsigned NOT NULL DEFAULT 0,
			object_type varchar(30) NOT NULL DEFAULT '',
			issue_type varchar(60) NOT NULL DEFAULT '',
			severity varchar(10) NOT NULL DEFAULT 'low',
			title varchar(255) NOT NULL DEFAULT '',
			description text NOT NULL,
			suggestion text NOT NULL,
			fix_type varchar(60) NOT NULL DEFAULT '',
			fix_payload longtext NOT NULL,
			status varchar(20) NOT NULL DEFAULT 'open',
			detected_at datetime DEFAULT NULL,
			updated_at datetime DEFAULT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY fingerprint (fingerprint),
			KEY object_lookup (object_type,object_id),
			KEY status_severity (status,severity)
		) {$charset};";

		$sql[] = "CREATE TABLE {$queue} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			issue_id bigint(20) unsigned NOT NULL DEFAULT 0,
			action varchar(40) NOT NULL DEFAULT 'fix',
			status varchar(20) NOT NULL DEFAULT 'pending',
			attempts smallint(5) unsigned NOT NULL DEFAULT 0,
			priority varchar(10) NOT NULL DEFAULT 'low',
			message text NOT NULL,
			created_at datetime DEFAULT NULL,
			started_at datetime DEFAULT NULL,
			finished_at datetime DEFAULT NULL,
			PRIMARY KEY  (id),
			KEY issue_id (issue_id),
			KEY status_priority (status,priority)
		) {$charset};";

		$sql[] = "CREATE TABLE {$snaps} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			issue_id bigint(20) unsigned NOT NULL DEFAULT 0,
			object_id bigint(20) unsigned NOT NULL DEFAULT 0,
			object_type varchar(30) NOT NULL DEFAULT '',
			label varchar(190) NOT NULL DEFAULT '',
			payload longtext NOT NULL,
			user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			created_at datetime DEFAULT NULL,
			restored_at datetime DEFAULT NULL,
			PRIMARY KEY  (id),
			KEY object_lookup (object_type,object_id),
			KEY issue_id (issue_id)
		) {$charset};";

		$sql[] = "CREATE TABLE {$logs} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			level varchar(10) NOT NULL DEFAULT 'info',
			action varchar(80) NOT NULL DEFAULT '',
			message text NOT NULL,
			context longtext NOT NULL,
			user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			created_at datetime DEFAULT NULL,
			PRIMARY KEY  (id),
			KEY level (level),
			KEY created_at (created_at)
		) {$charset};";

		foreach ( $sql as $statement ) {
			dbDelta( $statement );
		}
	}

	/**
	 * حذف جدول‌ها (فقط هنگام حذف افزونه و با تأیید تنظیمات).
	 */
	public static function drop_tables(): void {
		global $wpdb;
		foreach ( array( 'issues', 'queue', 'snapshots', 'logs' ) as $name ) {
			$table = self::table( $name );
			$wpdb->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
		}
		delete_option( self::DB_OPTION );
	}

	/**
	 * زمان‌بندی کران‌ها و اسکن دوره‌ای.
	 */
	public static function schedule_events(): void {
		// کران فقط به‌عنوان تور نجات است؛ زنجیره‌ی اصلی با رویدادهای تک‌باره اجرا می‌شود.
		if ( ! wp_next_scheduled( 'gso_queue_tick' ) ) {
			wp_schedule_event( time() + 60, 'gso_five_minutes', 'gso_queue_tick' );
		}
		if ( ! wp_next_scheduled( 'gso_scan_tick' ) ) {
			wp_schedule_event( time() + 120, 'gso_five_minutes', 'gso_scan_tick' );
		}
		if ( ! wp_next_scheduled( 'gso_watch_tick' ) ) {
			wp_schedule_event( time() + 180, 'gso_five_minutes', 'gso_watch_tick' );
		}
		self::schedule_periodic_scan();
	}

	/**
	 * (دوباره) زمان‌بندی اسکن دوره‌ای بر اساس تنظیم.
	 */
	public static function schedule_periodic_scan(): void {
		$existing = wp_next_scheduled( 'gso_schedule_scan' );
		$interval = (string) GSO_Settings::get( 'scan_interval', 'daily' );

		if ( 'off' === $interval ) {
			if ( $existing ) {
				wp_unschedule_event( $existing, 'gso_schedule_scan' );
			}
			return;
		}
		if ( $existing ) {
			return;
		}
		$map = array(
			'hourly' => 'hourly',
			'daily'  => 'daily',
			'weekly' => 'weekly',
		);
		wp_schedule_event( time() + HOUR_IN_SECONDS, $map[ $interval ] ?? 'daily', 'gso_schedule_scan' );
	}
}
