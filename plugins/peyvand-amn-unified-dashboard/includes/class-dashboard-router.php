<?php
/**
 * مسیریابی — یک نقطه‌ی ورود واحد و انتخاب ماژول مجاز.
 */

defined( 'ABSPATH' ) || exit;

final class PAS_Dashboard_Router {

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );
		add_action( 'admin_init', array( 'PAS_Dashboard_Export', 'init' ) );
		add_action( 'admin_post_pas_dashboard_save_settings', array( 'PAS_Dashboard_Module_Settings', 'handle_save' ) );
	}

	public static function register_menu() {
		$capability = defined( 'PAS_DASHBOARD_CAP' ) ? PAS_DASHBOARD_CAP : 'manage_options';

		add_menu_page(
			__( 'داشبورد یکپارچه درمانگر', 'peyvand-amn-dashboard' ),
			__( 'داشبورد یکپارچه', 'peyvand-amn-dashboard' ),
			$capability,
			PAS_DASHBOARD_SLUG,
			array( __CLASS__, 'render' ),
			'dashicons-heart',
			3
		);
	}

	public static function current_module() {
		$requested = isset( $_GET['module'] ) ? PAS_Dashboard_Validation::key( wp_unslash( $_GET['module'] ) ) : '';
		$visible   = PAS_Dashboard_Permissions::visible_modules();

		if ( $requested !== '' && isset( $visible[ $requested ] ) ) {
			return $requested;
		}

		return PAS_Dashboard_Permissions::default_module();
	}

	public static function render() {
		PAS_Dashboard_Security::require_dashboard();

		$module_id = self::current_module();

		if ( $module_id === '' ) {
			wp_die( esc_html__( 'هیچ ماژولی برای نقش شما فعال نیست.', 'peyvand-amn-dashboard' ), 403 );
		}

		$matrix = PAS_Dashboard_Permissions::matrix();
		$class  = isset( $matrix[ $module_id ]['class'] ) ? $matrix[ $module_id ]['class'] : '';

		if ( $class === '' || ! class_exists( $class ) ) {
			wp_die( esc_html__( 'ماژول درخواست‌شده در دسترس نیست.', 'peyvand-amn-dashboard' ), 404 );
		}

		if ( ! PAS_Dashboard_Permissions::can( $module_id ) ) {
			PAS_Dashboard_Security::record_denied( 'module.' . $module_id, 'module', 0 );
			wp_die( esc_html__( 'دسترسی به این ماژول مجاز نیست.', 'peyvand-amn-dashboard' ), 403 );
		}

		/** @var PAS_Dashboard_Module $module */
		$module = new $class();

		$user_id = get_current_user_id();

		if ( PAS_Dashboard_Permissions::feature( 'enable_notifications' ) && PAS_Dashboard_Permissions::can( 'notifications' ) ) {
			// همگام‌سازی حداکثر هر ۱۵ دقیقه یک‌بار برای هر کاربر (پرهیز از N+1).
			$sync_key = 'pas_notif_sync_' . $user_id;
			if ( ! get_transient( $sync_key ) ) {
				$cases = PAS_Dashboard_Core_Bridge::cases( $user_id, 60 );
				PAS_Dashboard_Notifications::sync( $user_id, $cases );
				set_transient( $sync_key, 1, 15 * MINUTE_IN_SECONDS );
			}
		}

		$nav = array();
		foreach ( PAS_Dashboard_Permissions::visible_modules() as $id => $item ) {
			$nav[ $id ] = array(
				'label'  => $item['label'],
				'url'    => add_query_arg(
					array(
						'page'   => PAS_DASHBOARD_SLUG,
						'module' => $id,
					),
					admin_url( 'admin.php' )
				),
				'active' => $id === $module_id,
			);
		}

		$unread = PAS_Dashboard_Notifications::unread_count( $user_id );

		PAS_Dashboard_Audit_Log::record( 'module.view', 'module', 0, 'ok' );

		$view = array(
			'module' => $module,
			'nav'    => $nav,
			'unread' => $unread,
			'user'   => wp_get_current_user(),
		);

		self::load_template( 'layout.php', $view );
	}

	private static function load_template( $file, array $view ) {
		$path = PAS_DASHBOARD_DIR . 'templates/' . $file;

		if ( ! is_readable( $path ) ) {
			wp_die( esc_html__( 'قالب داشبورد یافت نشد.', 'peyvand-amn-dashboard' ) );
		}

		// متغیرهای قالب
		// phpcs:ignore WordPress.PHP.DontExtract.extract_extract
		extract( $view, EXTR_SKIP );
		include $path;
	}
}
