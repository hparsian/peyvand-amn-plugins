<?php
/**
 * ماژول ۱۰ — تنظیمات داشبورد.
 *
 * فقط تنظیمات متعلق به داشبورد ذخیره می‌شوند؛ هیچ آپشن افزونه‌ی دیگری تغییر
 * نمی‌کند.
 */

defined( 'ABSPATH' ) || exit;

final class PAS_Dashboard_Module_Settings extends PAS_Dashboard_Module {

	public function __construct() {
		$this->id          = 'settings';
		$this->title       = __( 'تنظیمات', 'peyvand-amn-dashboard' );
		$this->description = __( 'تنظیمات ظاهری و رفتاری داشبورد (بدون تغییر تنظیمات افزونه‌های دیگر).', 'peyvand-amn-dashboard' );
	}

	public static function handle_save() {
		if ( ! PAS_Dashboard_Security::can_manage() ) {
			PAS_Dashboard_Security::record_denied( 'settings.save', 'settings', 0 );
			wp_die( esc_html__( 'دسترسی غیرمجاز.', 'peyvand-amn-dashboard' ), 403 );
		}

		if ( ! PAS_Dashboard_Security::verify_nonce( 'save_settings' ) ) {
			PAS_Dashboard_Security::record_denied( 'settings.save', 'settings', 0 );
			wp_die( esc_html__( 'توکن امنیتی نامعتبر است.', 'peyvand-amn-dashboard' ), 403 );
		}

		// مقدار خام ذخیره‌شده (نه نتیجه‌ی فیلتر) تا فیلترها در پایگاه‌داده ذخیره نشوند.
		$current = get_option( PAS_DASHBOARD_OPTION, array() );
		$current = is_array( $current ) ? $current : array();
		$current = array_merge( PAS_Dashboard_Permissions::default_settings(), $current );

		$per_page = isset( $_POST['per_page'] ) ? absint( $_POST['per_page'] ) : 20;
		$range    = isset( $_POST['default_range_days'] ) ? absint( $_POST['default_range_days'] ) : 30;

		$new = array(
			'per_page'                 => max( 5, min( PAS_DASHBOARD_MAX_PER_PAGE, $per_page ) ),
			'enable_notes'             => PAS_Dashboard_Validation::checkbox( 'enable_notes' ),
			'enable_notifications'     => PAS_Dashboard_Validation::checkbox( 'enable_notifications' ),
			'default_range_days'       => max( 1, min( 365, $range ) ),
			'delete_data_on_uninstall' => PAS_Dashboard_Validation::checkbox( 'delete_data_on_uninstall' ),
		);

		update_option( PAS_DASHBOARD_OPTION, array_merge( $current, $new ) );

		PAS_Dashboard_Audit_Log::record( 'settings.save', 'settings', 0, 'ok' );

		$url = add_query_arg(
			array(
				'page'     => PAS_DASHBOARD_SLUG,
				'module'   => 'settings',
				'pas_saved' => 1,
			),
			admin_url( 'admin.php' )
		);

		wp_safe_redirect( $url );
		exit;
	}

	public function render() {
		$settings = PAS_Dashboard_Permissions::settings();

		$html = '';

		if ( PAS_Dashboard_Validation::flag( 'pas_saved' ) ) {
			$html .= $this->notice( 'success', __( 'تنظیمات ذخیره شد.', 'peyvand-amn-dashboard' ) );
		}

		$html .= $this->card_open( __( 'تنظیمات داشبورد', 'peyvand-amn-dashboard' ) );
		$html .= '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		$html .= '<input type="hidden" name="action" value="pas_dashboard_save_settings" />';
		$html .= PAS_Dashboard_Security::nonce_field( 'save_settings' );

		$html .= '<div class="pas-form-row"><label for="pas-per-page">' . esc_html__( 'تعداد ردیف در هر صفحه', 'peyvand-amn-dashboard' ) . '</label>';
		$html .= '<input class="pas-input" type="number" id="pas-per-page" name="per_page" min="5" max="' . esc_attr( (string) PAS_DASHBOARD_MAX_PER_PAGE ) . '" value="' . esc_attr( (string) (int) $settings['per_page'] ) . '" /></div>';

		$html .= '<div class="pas-form-row"><label for="pas-range">' . esc_html__( 'بازه‌ی پیش‌فرض گزارش (روز)', 'peyvand-amn-dashboard' ) . '</label>';
		$html .= '<input class="pas-input" type="number" id="pas-range" name="default_range_days" min="1" max="365" value="' . esc_attr( (string) (int) $settings['default_range_days'] ) . '" /></div>';

		$html .= '<div class="pas-form-row"><label><input type="checkbox" name="enable_notes" value="1"' . checked( ! empty( $settings['enable_notes'] ), true, false ) . ' /> ' . esc_html__( 'فعال‌بودن ثبت یادداشت بالینی', 'peyvand-amn-dashboard' ) . '</label></div>';

		$html .= '<div class="pas-form-row"><label><input type="checkbox" name="enable_notifications" value="1"' . checked( ! empty( $settings['enable_notifications'] ), true, false ) . ' /> ' . esc_html__( 'فعال‌بودن اعلان‌ها', 'peyvand-amn-dashboard' ) . '</label></div>';

		$html .= '<div class="pas-form-row"><label><input type="checkbox" name="delete_data_on_uninstall" value="1"' . checked( ! empty( $settings['delete_data_on_uninstall'] ), true, false ) . ' /> ' . esc_html__( 'حذف جداول اختصاصی داشبورد هنگام حذف افزونه (پیش‌فرض: خیر)', 'peyvand-amn-dashboard' ) . '</label></div>';

		$html .= '<button class="pas-btn pas-btn-primary" type="submit">' . esc_html__( 'ذخیره‌ی تنظیمات', 'peyvand-amn-dashboard' ) . '</button>';
		$html .= '</form>';
		$html .= $this->card_close();

		$html .= $this->card_open( __( 'وضعیت رمزنگاری', 'peyvand-amn-dashboard' ) );
		if ( PAS_Dashboard_Core_Bridge::crypto_ready() ) {
			$html .= '<p><span class="pas-badge pas-badge-good">' . esc_html__( 'فعال', 'peyvand-amn-dashboard' ) . '</span></p>';
			$html .= '<p class="pas-muted">' . esc_html__( 'کلید رمزنگاری تنظیم شده است؛ یادداشت‌های بالینی قابل نمایش و ثبت هستند.', 'peyvand-amn-dashboard' ) . '</p>';
		} else {
			$html .= '<p><span class="pas-badge pas-badge-danger">' . esc_html__( 'تنظیم نشده', 'peyvand-amn-dashboard' ) . '</span></p>';
			$html .= '<p>' . esc_html__( 'برای فعال‌کردن یادداشت‌های بالینی، ثابت PAS_CORE_ENCRYPTION_KEY را در wp-config.php تنظیم کنید. بدون کلید، داشبورد هیچ داده‌ی رمزنگاری‌شده‌ای را نمایش نمی‌دهد و ثبت یادداشت را رد می‌کند.', 'peyvand-amn-dashboard' ) . '</p>';
		}
		$html .= $this->card_close();

		$html .= $this->card_open( __( 'درباره‌ی دامنه‌ی تنظیمات', 'peyvand-amn-dashboard' ) );
		$html .= '<p class="pas-muted">' . esc_html__( 'این صفحه فقط تنظیمات متعلق به داشبورد را ذخیره می‌کند. تنظیمات پرونده‌ها، آزمون‌ها، پیامک و پنل مراجعان در افزونه‌های خودشان مدیریت می‌شود و اینجا بازنویسی نمی‌شود.', 'peyvand-amn-dashboard' ) . '</p>';
		$html .= $this->card_close();

		$html .= $this->render_role_matrix();

		return $html;
	}

	/**
	 * ماتریس دسترسی نقش‌ها — فقط برای مدیر.
	 *
	 * این جدول از `PAS_Dashboard_Permissions::role_matrix()` خوانده می‌شود و
	 * هیچ کپابیلیتی را تغییر نمی‌دهد؛ نقش پذیرش هنگام فعال‌سازی/آپدیت ساخته
	 * می‌شود و کاربرانش فقط نوبت، پیام و اعلان را می‌بینند.
	 */
	private function render_role_matrix() {
		$matrix = PAS_Dashboard_Permissions::matrix();
		$roles  = PAS_Dashboard_Permissions::role_matrix();

		$html  = $this->card_open( __( 'ماتریس دسترسی نقش‌ها', 'peyvand-amn-dashboard' ) );
		$html .= '<p class="pas-muted">' . esc_html__( 'برای افزودن کاربر پذیرش، در کاربر وردپرس نقش «پذیرش (داشبورد)» را انتخاب کنید. این نقش هیچ دادهٔ بالینی نمی‌بیند و فقط نوبت، پیام و اعلان دارد؛ نقش «درمانگر» و مدیر سایت مثل قبل کار می‌کنند.', 'peyvand-amn-dashboard' ) . '</p>';
		$html .= '<div class="pas-table-wrap"><table class="pas-table"><thead><tr>';
		$html .= '<th>' . esc_html__( 'نقش', 'peyvand-amn-dashboard' ) . '</th>';
		$html .= '<th>' . esc_html__( 'دامنهٔ دسترسی', 'peyvand-amn-dashboard' ) . '</th>';
		$html .= '<th>' . esc_html__( 'ماژول‌های قابل مشاهده', 'peyvand-amn-dashboard' ) . '</th>';
		$html .= '</tr></thead><tbody>';

		foreach ( $roles as $slug => $role ) {
			$labels = array();

			foreach ( $role['modules'] as $module_id ) {
				if ( isset( $matrix[ $module_id ] ) ) {
					$labels[] = (string) $matrix[ $module_id ]['label'];
				}
			}

			$html .= '<tr>';
			$html .= '<td data-label="' . esc_attr__( 'نقش', 'peyvand-amn-dashboard' ) . '"><strong>' . esc_html( (string) $role['label'] ) . '</strong><br /><code dir="ltr">' . esc_html( (string) $slug ) . '</code></td>';
			$html .= '<td data-label="' . esc_attr__( 'دامنهٔ دسترسی', 'peyvand-amn-dashboard' ) . '">' . esc_html( (string) $role['access'] ) . '</td>';
			$html .= '<td data-label="' . esc_attr__( 'ماژول‌ها', 'peyvand-amn-dashboard' ) . '">' . esc_html( implode( '، ', $labels ) ) . '</td>';
			$html .= '</tr>';
		}

		$html .= '</tbody></table></div>';
		$html .= $this->card_close();

		return $html;
	}
}
