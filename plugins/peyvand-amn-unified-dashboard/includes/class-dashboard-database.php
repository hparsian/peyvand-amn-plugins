<?php
/**
 * پایگاه‌داده — فقط جداول اختصاصی داشبورد.
 *
 * هیچ جدول متعلق به افزونه‌های دیگر ساخته، تغییر یا حذف نمی‌شود.
 */

defined( 'ABSPATH' ) || exit;

final class PAS_Dashboard_Database {

	public static function audit_table() {
		global $wpdb;

		return $wpdb->prefix . 'pas_dashboard_audit';
	}

	public static function notifications_table() {
		global $wpdb;

		return $wpdb->prefix . 'pas_dashboard_notifications';
	}

	/** نسخه‌بندی‌شده و idempotent — dbDelta فقط در صورت تغییر نسخه اجرا می‌شود. */
	public static function maybe_upgrade() {
		if ( get_option( 'pas_dashboard_db_version' ) === PAS_DASHBOARD_DB_VERSION ) {
			return;
		}

		self::install();

		if ( class_exists( 'PAS_Dashboard_Permissions' ) ) {
			PAS_Dashboard_Permissions::grant_default_caps();
		}
	}

	public static function install() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset = $wpdb->get_charset_collate();
		$audit   = self::audit_table();
		$notify  = self::notifications_table();

		dbDelta(
			"CREATE TABLE $audit (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				user_id bigint(20) unsigned NOT NULL DEFAULT 0,
				action varchar(64) NOT NULL DEFAULT '',
				object_type varchar(32) NOT NULL DEFAULT '',
				object_id bigint(20) unsigned NOT NULL DEFAULT 0,
				result varchar(20) NOT NULL DEFAULT 'ok',
				ip_hash char(32) NOT NULL DEFAULT '',
				created_at datetime NOT NULL DEFAULT '1970-01-01 00:00:00',
				PRIMARY KEY  (id),
				KEY user_id (user_id),
				KEY action (action),
				KEY created_at (created_at)
			) $charset;"
		);

		dbDelta(
			"CREATE TABLE $notify (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				user_id bigint(20) unsigned NOT NULL DEFAULT 0,
				dedupe_key char(64) NOT NULL DEFAULT '',
				type varchar(40) NOT NULL DEFAULT '',
				title varchar(190) NOT NULL DEFAULT '',
				ref varchar(100) NOT NULL DEFAULT '',
				created_at datetime NOT NULL DEFAULT '1970-01-01 00:00:00',
				read_at datetime NULL DEFAULT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY dedupe_key (dedupe_key),
				KEY user_unread (user_id,read_at)
			) $charset;"
		);

		update_option( 'pas_dashboard_db_version', PAS_DASHBOARD_DB_VERSION, false );
	}

	/** بررسی وجود جدول (بدون کوئری نوشتنی) */
	public static function table_exists( $table ) {
		global $wpdb;

		return $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table;
	}
}
