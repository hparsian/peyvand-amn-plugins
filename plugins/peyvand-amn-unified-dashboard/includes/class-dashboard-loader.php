<?php
/**
 * بارگذار افزونه — تک‌نمونه، بدون ثبت هوک تکراری.
 */

defined( 'ABSPATH' ) || exit;

final class PAS_Dashboard_Plugin {

	/** @var self|null */
	private static $instance = null;

	/** @var bool */
	private $booted = false;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
			self::$instance->boot();
		}

		return self::$instance;
	}

	private function __construct() {}

	/**
	 * فایل‌های هسته را بارگذاری می‌کند. ترتیب مهم است: ابزارهای پایه پیش از
	 * سرویس‌هایی که به آن‌ها تکیه می‌کنند.
	 */
	private function requires() {
		$files = array(
			'includes/class-dashboard-validation.php',
			'includes/class-dashboard-database.php',
			'includes/class-dashboard-audit-log.php',
			'includes/class-dashboard-core-bridge.php',
			'includes/class-dashboard-security.php',
			'includes/class-dashboard-permissions.php',
			'includes/class-dashboard-notifications.php',
			'includes/class-dashboard-flash.php',
			'includes/adapters/class-adapter-case-file.php',
			'includes/adapters/class-adapter-portal.php',
			'includes/adapters/class-adapter-quiz.php',
			'includes/class-dashboard-actions.php',
			'includes/class-dashboard-export.php',
			'includes/class-dashboard-ajax.php',
			'includes/class-dashboard-rest.php',
			'includes/class-dashboard-assets.php',
			'includes/modules.php',
			'includes/class-dashboard-router.php',
		);

		foreach ( $files as $file ) {
			$path = PAS_DASHBOARD_DIR . $file;
			if ( is_readable( $path ) ) {
				require_once $path;
			}
		}

		// ماژول‌ها
		$modules = array(
			'overview-module.php',
			'patients-module.php',
			'case-files-module.php',
			'assessments-module.php',
			'appointments-module.php',
			'reports-module.php',
			'communications-module.php',
			'notifications-module.php',
			'system-health-module.php',
			'settings-module.php',
			'audit-module.php',
		);

		foreach ( $modules as $module ) {
			$path = PAS_DASHBOARD_DIR . 'includes/modules/' . $module;
			if ( is_readable( $path ) ) {
				require_once $path;
			}
		}
	}

	private function boot() {
		if ( $this->booted ) {
			return;
		}
		$this->booted = true;

		$this->requires();

		PAS_Dashboard_Database::maybe_upgrade();

		if ( get_option( 'pas_dashboard_version' ) !== PAS_DASHBOARD_VERSION ) {
			PAS_Dashboard_Permissions::grant_default_caps();
			update_option( 'pas_dashboard_version', PAS_DASHBOARD_VERSION );
		}

		PAS_Dashboard_Actions::init();
		PAS_Dashboard_Router::init();
		PAS_Dashboard_Assets::init();
		PAS_Dashboard_Ajax::init();
		PAS_Dashboard_Rest::init();

		add_action( 'admin_notices', array( $this, 'dependency_notices' ) );
	}

	/**
	 * هشدارهای وابستگی — فقط در صفحه‌های داشبورد و فقط برای مدیر.
	 */
	public function dependency_notices() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || false === strpos( (string) $screen->id, PAS_DASHBOARD_SLUG ) ) {
			return;
		}

		$missing = array();

		if ( ! PAS_Dashboard_Core_Bridge::has_core() ) {
			$missing[] = __( 'افزونه‌ی هسته‌ی مشترک (pivand-amn-core) فعال نیست.', 'peyvand-amn-dashboard' );
		}
		if ( ! PAS_Dashboard_Core_Bridge::has_case_file() ) {
			$missing[] = __( 'افزونه‌ی پرونده‌های بالینی (pivand-amn-case-file) فعال نیست.', 'peyvand-amn-dashboard' );
		}
		if ( ! PAS_Dashboard_Core_Bridge::crypto_ready() ) {
			$missing[] = __( 'کلید رمزنگاری (PAS_CORE_ENCRYPTION_KEY) تنظیم نشده است؛ ثبت و نمایش یادداشت‌های بالینی غیرفعال است.', 'peyvand-amn-dashboard' );
		}

		foreach ( $missing as $message ) {
			echo '<div class="notice notice-warning"><p>' . esc_html( $message ) . '</p></div>';
		}
	}

	/* ── چرخه‌ی عمر ─────────────────────────────────────────────────────── */

	public static function activate() {
		PAS_Dashboard_Plugin::instance();

		PAS_Dashboard_Database::install();
		PAS_Dashboard_Permissions::grant_default_caps();

		$defaults = PAS_Dashboard_Permissions::default_settings();
		$current  = get_option( PAS_DASHBOARD_OPTION, array() );
		if ( ! is_array( $current ) ) {
			$current = array();
		}
		update_option( PAS_DASHBOARD_OPTION, array_merge( $defaults, $current ) );
		update_option( 'pas_dashboard_version', PAS_DASHBOARD_VERSION );
	}

	public static function deactivate() {
		// هیچ داده‌ای حذف نمی‌شود. فقط رخدادهای زمان‌بندی‌شده پاک می‌شوند.
		wp_clear_scheduled_hook( 'pas_dashboard_daily_sync' );
		flush_rewrite_rules();
	}
}
