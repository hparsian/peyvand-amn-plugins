<?php
/**
 * دارایی‌ها — فقط در صفحه‌ی داشبورد بارگذاری می‌شوند.
 */

defined( 'ABSPATH' ) || exit;

final class PAS_Dashboard_Assets {

	public static function init() {
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
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
					'cancel'        => __( 'انصراف', 'peyvand-amn-dashboard' ),
					'ok'            => __( 'تأیید', 'peyvand-amn-dashboard' ),
				),
			)
		);
	}
}
