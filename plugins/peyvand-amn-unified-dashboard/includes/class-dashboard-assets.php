<?php
/**
 * دارایی‌ها — فقط در صفحه‌ی داشبورد بارگذاری می‌شوند.
 */

defined( 'ABSPATH' ) || exit;

final class PAS_Dashboard_Assets {

	public static function init() {
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
		add_action( 'admin_head', array( __CLASS__, 'early_theme' ) );
	}

	/** آیا صفحهٔ جاری، صفحهٔ داشبورد است؟ */
	private static function is_dashboard_page() {
		if ( ! function_exists( 'get_current_screen' ) ) {
			return false;
		}

		$screen = get_current_screen();

		return $screen && 'toplevel_page_' . PAS_DASHBOARD_SLUG === $screen->id;
	}

	/**
	 * انتخاب رنگ کاربر را پیش از رندر اعمال می‌کند تا صفحه با حالت روشن
	 * نقاشی نشود و بعد به تاریک بپرد (پرش رنگ).
	 */
	public static function early_theme() {
		if ( ! self::is_dashboard_page() ) {
			return;
		}

		$default = class_exists( 'PUI' ) ? PUI::theme_attr() : 'light';
		$default = in_array( $default, array( 'light', 'dark' ), true ) ? $default : 'light';

		echo "<script>try{var t=localStorage.getItem('pvn-theme')||'" . esc_js( $default ) . "';if(t==='auto'){t=window.matchMedia('(prefers-color-scheme: dark)').matches?'dark':'light';}document.documentElement.setAttribute('data-pui-theme',t);}catch(e){}</script>\n";
	}

	public static function enqueue( $hook_suffix ) {
		if ( 'toplevel_page_' . PAS_DASHBOARD_SLUG !== $hook_suffix ) {
			return;
		}

		$theme = 'light';
		if ( class_exists( 'PUI' ) ) {
			$theme = PUI::theme_attr();
		}
		if ( ! in_array( $theme, array( 'light', 'dark' ), true ) ) {
			$theme = 'light';
		}

		/*
		 * کیت رابط کاربری اگر فعال باشد، توکن‌ها/کامپوننت‌ها/فونت Display را
		 * می‌آورد. اگر غیرفعال باشد، پوسته با توکن‌های fallback خودش کار
		 * می‌کند و هیچ صفحه‌ای سفید نمی‌شود.
		 */
		if ( class_exists( 'PUI' ) ) {
			PUI::enqueue( 'admin' );
		}

		$deps = array();

		wp_enqueue_style(
			'pas-dashboard-admin',
			PAS_DASHBOARD_URL . 'assets/css/dashboard-admin.css',
			$deps,
			PAS_DASHBOARD_VERSION
		);

		wp_enqueue_style(
			'pas-dashboard-shell',
			PAS_DASHBOARD_URL . 'assets/css/shell-v2.css',
			array( 'pas-dashboard-admin' ),
			PAS_DASHBOARD_VERSION
		);

		wp_enqueue_script(
			'pas-dashboard-admin',
			PAS_DASHBOARD_URL . 'assets/js/dashboard-admin.js',
			array( 'jquery' ),
			PAS_DASHBOARD_VERSION,
			true
		);

		wp_localize_script(
			'pas-dashboard-admin',
			'PasDashboard',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'theme'   => $theme,
				'nonces'  => array(
					'note_save'         => PAS_Dashboard_Security::nonce( 'note_save' ),
					'note_delete'       => PAS_Dashboard_Security::nonce( 'note_delete' ),
					'note_restore'      => PAS_Dashboard_Security::nonce( 'note_restore' ),
					'notifications_read' => PAS_Dashboard_Security::nonce( 'notifications_read' ),
				),
				'i18n'    => array(
					'confirmDelete' => __( 'این یادداشت از نمای پرونده حذف می‌شود (نسخه‌ی قبلی در تاریخچه می‌ماند). ادامه می‌دهید؟', 'peyvand-amn-dashboard' ),
					'saving'        => __( 'در حال ذخیره…', 'peyvand-amn-dashboard' ),
					'error'         => __( 'خطا در ارتباط با سرور.', 'peyvand-amn-dashboard' ),
					'confirmTitle'  => __( 'تأیید عملیات', 'peyvand-amn-dashboard' ),
					'cancel'        => __( 'انصراف', 'peyvand-amn-dashboard' ),
					'ok'            => __( 'تأیید', 'peyvand-amn-dashboard' ),
				),
			)
		);
	}
}
