<?php
/**
 * ماژول ۹ — سلامت سیستم (فقط‌خواندنی، بدون افشای راز).
 */

defined( 'ABSPATH' ) || exit;

final class PAS_Dashboard_Module_System_Health extends PAS_Dashboard_Module {

	public function __construct() {
		$this->id          = 'system_health';
		$this->title       = __( 'سلامت سیستم', 'peyvand-amn-dashboard' );
		$this->description = __( 'بررسی فقط‌خواندنی وابستگی‌ها، نسخه‌ها و تنظیمات رمزنگاری.', 'peyvand-amn-dashboard' );
	}

	public function render() {
		$checks = array();

		$checks[] = $this->check(
			__( 'نسخه‌ی PHP', 'peyvand-amn-dashboard' ),
			version_compare( PHP_VERSION, '7.4', '>=' ),
			PHP_VERSION . ' (حداقل 7.4)'
		);

		$checks[] = $this->check(
			__( 'نسخه‌ی وردپرس', 'peyvand-amn-dashboard' ),
			version_compare( get_bloginfo( 'version' ), '5.8', '>=' ),
			get_bloginfo( 'version' ) . ' (حداقل 5.8)'
		);

		$checks[] = $this->check(
			__( 'هسته‌ی مشترک', 'peyvand-amn-dashboard' ),
			PAS_Dashboard_Core_Bridge::has_core(),
			PAS_Dashboard_Core_Bridge::has_core() ? 'Pas_Core_Client_Registry' : __( 'کلاس هسته یافت نشد', 'peyvand-amn-dashboard' )
		);

		$checks[] = $this->check(
			__( 'پرونده‌های بالینی', 'peyvand-amn-dashboard' ),
			PAS_Dashboard_Core_Bridge::has_case_file(),
			PAS_Dashboard_Core_Bridge::has_case_file() ? 'Pas_CF_Case_File' : __( 'کلاس پرونده یافت نشد', 'peyvand-amn-dashboard' )
		);

		$checks[] = $this->check(
			__( 'کلید رمزنگاری', 'peyvand-amn-dashboard' ),
			PAS_Dashboard_Core_Bridge::crypto_ready(),
			PAS_Dashboard_Core_Bridge::crypto_ready()
				? __( 'تنظیم شده', 'peyvand-amn-dashboard' )
				: __( 'تنظیم نشده (PAS_CORE_ENCRYPTION_KEY)', 'peyvand-amn-dashboard' )
		);

		$checks[] = $this->check(
			__( 'افزونه‌ی آزمون‌ها', 'peyvand-amn-dashboard' ),
			PAS_Dashboard_Core_Bridge::assessments_available(),
			PAS_Dashboard_Core_Bridge::assessments_available() ? 'PsychAssessments\\Core\\Registry' : __( 'رجیستری عمومی یافت نشد', 'peyvand-amn-dashboard' )
		);

		$checks[] = $this->check(
			__( 'پل پیامک', 'peyvand-amn-dashboard' ),
			PAS_Dashboard_Core_Bridge::sms_available(),
			PAS_Dashboard_Core_Bridge::sms_available() ? 'pas_send_sms' : __( 'فیلتر ثبت نشده', 'peyvand-amn-dashboard' )
		);

		$checks[] = $this->check(
			__( 'پنل مراجعان', 'peyvand-amn-dashboard' ),
			PAS_Dashboard_Core_Bridge::portal_available(),
			PAS_Dashboard_Core_Bridge::portal_available() ? __( 'در دسترس', 'peyvand-amn-dashboard' ) : __( 'شناسایی نشد', 'peyvand-amn-dashboard' )
		);

		$checks[] = $this->check(
			__( 'جدول لاگ ممیزی', 'peyvand-amn-dashboard' ),
			PAS_Dashboard_Database::table_exists( PAS_Dashboard_Database::audit_table() ),
			PAS_Dashboard_Database::audit_table()
		);

		$checks[] = $this->check(
			__( 'جدول اعلان‌ها', 'peyvand-amn-dashboard' ),
			PAS_Dashboard_Database::table_exists( PAS_Dashboard_Database::notifications_table() ),
			PAS_Dashboard_Database::notifications_table()
		);

		$checks[] = $this->check(
			__( 'نسخه‌ی شمای داشبورد', 'peyvand-amn-dashboard' ),
			get_option( 'pas_dashboard_db_version' ) === PAS_DASHBOARD_DB_VERSION,
			(string) get_option( 'pas_dashboard_db_version', __( 'ثبت نشده', 'peyvand-amn-dashboard' ) )
		);

		$checks[] = $this->check(
			__( 'فایل‌های دارایی', 'peyvand-amn-dashboard' ),
			is_readable( PAS_DASHBOARD_DIR . 'assets/css/dashboard-admin.css' ) && is_readable( PAS_DASHBOARD_DIR . 'assets/js/dashboard-admin.js' ),
			'assets/css + assets/js'
		);

		$checks[] = $this->check(
			__( 'قالب‌ها', 'peyvand-amn-dashboard' ),
			is_readable( PAS_DASHBOARD_DIR . 'templates/layout.php' ),
			'templates/layout.php'
		);

		$html  = $this->card_open( __( 'نتیجه‌ی بررسی‌ها', 'peyvand-amn-dashboard' ) );
		$html .= '<div class="pas-table-wrap"><table class="pas-table"><thead><tr>';
		$html .= '<th>' . esc_html__( 'بررسی', 'peyvand-amn-dashboard' ) . '</th>';
		$html .= '<th>' . esc_html__( 'وضعیت', 'peyvand-amn-dashboard' ) . '</th>';
		$html .= '<th>' . esc_html__( 'جزئیات', 'peyvand-amn-dashboard' ) . '</th>';
		$html .= '</tr></thead><tbody>';

		foreach ( $checks as $check ) {
			$html .= '<tr>';
			$html .= '<td data-label="' . esc_attr__( 'بررسی', 'peyvand-amn-dashboard' ) . '">' . esc_html( $check['label'] ) . '</td>';
			$html .= '<td data-label="' . esc_attr__( 'وضعیت', 'peyvand-amn-dashboard' ) . '">' . $check['badge'] . '</td>';
			$html .= '<td data-label="' . esc_attr__( 'جزئیات', 'peyvand-amn-dashboard' ) . '"><code>' . esc_html( $check['detail'] ) . '</code></td>';
			$html .= '</tr>';
		}

		$html .= '</tbody></table></div>';
		$html .= '<p class="pas-muted">' . esc_html__( 'این ماژول هیچ کلید، رمز، توکن یا مقدار حساسی را نمایش نمی‌دهد و هیچ تنظیمی را تغییر نمی‌دهد.', 'peyvand-amn-dashboard' ) . '</p>';
		$html .= $this->card_close();

		$html .= $this->card_open( __( 'نقش‌ها و دسترسی‌ها', 'peyvand-amn-dashboard' ) );
		$html .= '<ul class="pas-list">';
		$html .= '<li>' . esc_html__( 'داشبورد:', 'peyvand-amn-dashboard' ) . ' <code>' . esc_html( PAS_DASHBOARD_CAP ) . '</code></li>';
		if ( defined( 'PAS_CF_CAP' ) ) {
			$html .= '<li>' . esc_html__( 'داده‌های بالینی:', 'peyvand-amn-dashboard' ) . ' <code>' . esc_html( PAS_CF_CAP ) . '</code></li>';
		}
		if ( defined( 'PAS_CF_CAP_ALL' ) ) {
			$html .= '<li>' . esc_html__( 'همه‌ی پرونده‌ها:', 'peyvand-amn-dashboard' ) . ' <code>' . esc_html( PAS_CF_CAP_ALL ) . '</code></li>';
		}
		$html .= '</ul>';
		$html .= $this->card_close();

		return $html;
	}

	private function check( $label, $ok, $detail ) {
		return array(
			'label'  => $label,
			'detail' => $detail,
			'badge'  => $ok
				? '<span class="pas-badge pas-badge-good">' . esc_html__( 'سالم', 'peyvand-amn-dashboard' ) . '</span>'
				: '<span class="pas-badge pas-badge-warn">' . esc_html__( 'نیازمند بررسی', 'peyvand-amn-dashboard' ) . '</span>',
		);
	}
}
