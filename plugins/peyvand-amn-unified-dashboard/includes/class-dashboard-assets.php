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

		/*
		 * کلید ذخیره‌سازی و attributes همان چیزی است که کیت رابط (ui.js) هم
		 * می‌خواند؛ وگرنه ui.js در همان بارگذاری مقدار خودش را می‌نشاند و
		 * انتخاب سرور/کاربر را بازنویسی می‌کرد. مقدار اولیه از تم سرور است.
		 */
		echo "<script>try{var r=document.documentElement,t=localStorage.getItem('pui-theme')||'" . esc_js( $default ) . "';r.setAttribute('data-pui-theme-choice',t);r.setAttribute('data-pui-theme',t==='auto'?(window.matchMedia('(prefers-color-scheme: dark)').matches?'dark':'light'):(t==='dark'?'dark':'light'));}catch(e){}</script>\n";
	}

	public static function enqueue( $hook_suffix ) {
		if ( 'toplevel_page_' . PAS_DASHBOARD_SLUG !== $hook_suffix ) {
			return;
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

		/* صفحهٔ گزارش آزمون: CSS و Chart.js مالک آزمون لازم است، وگرنه canvas‌ها
		 * و کلاس‌های psy-report بدون استایل/نمودار می‌مانند. */
		$module  = isset( $_GET['module'] ) ? sanitize_key( wp_unslash( $_GET['module'] ) ) : '';
		$attempt = isset( $_GET['attempt_id'] ) ? absint( wp_unslash( $_GET['attempt_id'] ) ) : 0;

		if ( 'assessments' === $module && $attempt > 0 ) {
			if ( class_exists( 'Psy_Quiz_Assets' ) ) {
				Psy_Quiz_Assets::enqueue_chart_js();
			}

			if ( defined( 'PSY_URL' ) ) {
				wp_enqueue_style(
					'psy-quiz-report-css',
					PSY_URL . 'assets/css/frontend.css',
					array(),
					defined( 'PSY_VERSION' ) ? PSY_VERSION : PAS_DASHBOARD_VERSION
				);
			}
		}

		wp_localize_script(
			'pas-dashboard-admin',
			'PasDashboard',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
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
					'editTitle'     => __( 'ویرایش یادداشت جلسه', 'peyvand-amn-dashboard' ),
					'newTitle'      => __( 'افزودن یادداشت جدید', 'peyvand-amn-dashboard' ),
					'saveChanges'   => __( 'ذخیرهٔ تغییرات', 'peyvand-amn-dashboard' ),
					'addNote'       => __( 'ثبت یادداشت', 'peyvand-amn-dashboard' ),
					'cancel'        => __( 'انصراف', 'peyvand-amn-dashboard' ),
					'ok'            => __( 'تأیید', 'peyvand-amn-dashboard' ),
				),
			)
		);
	}
}
