<?php
/**
 * ماژول یکپارچگی‌ها — وضعیت اتصال سرویس‌های اکوسیستم و قراردادهای کلیدی.
 *
 * فقط‌خواندنی است: هیچ تنظیمی ذخیره نمی‌شود، هیچ درخواستی به سرویس بیرونی
 * زده نمی‌شود و هیچ کلید، رمز یا توکنی نمایش داده نمی‌شود. وضعیت هر سرویس از
 * شناسایی کلاس/تابع عمومی خودش و هدر نسخهٔ افزونهٔ نصب‌شده می‌آید.
 */

defined( 'ABSPATH' ) || exit;

final class PAS_Dashboard_Module_Ecosystem extends PAS_Dashboard_Module {

	public function __construct() {
		$this->id          = 'ecosystem';
		$this->title       = __( 'یکپارچگی‌ها', 'peyvand-amn-dashboard' );
		$this->description = __( 'وضعیت اتصال سرویس‌های اکوسیستم و قراردادهای بین‌افزونه‌ای.', 'peyvand-amn-dashboard' );
	}

	public function render() {
		$services  = PAS_Dashboard_Adapter_Ecosystem::services();
		$summary   = PAS_Dashboard_Adapter_Ecosystem::summary( $services );
		$groups    = PAS_Dashboard_Adapter_Ecosystem::groups();

		$html  = $this->card_open( __( 'خلاصهٔ اتصال‌ها', 'peyvand-amn-dashboard' ) );
		$html .= '<div class="pas-grid">';
		$html .= $this->stat( (string) $summary['total'], __( 'کل سرویس‌ها', 'peyvand-amn-dashboard' ) );
		$html .= $this->stat( (string) $summary['connected'], __( 'متصل', 'peyvand-amn-dashboard' ) );
		$html .= $this->stat( (string) $summary['attention'], __( 'نیازمند بررسی', 'peyvand-amn-dashboard' ) );
		$html .= $this->stat( (string) $summary['off'], __( 'غیرفعال', 'peyvand-amn-dashboard' ) );
		$html .= '</div>';
		$html .= '<p class="pas-muted">' . esc_html( sprintf(
			/* translators: %s: dashboard version */
			__( 'نسخهٔ داشبورد: %s — این صفحه فقط وضعیت را نشان می‌دهد و هیچ تنظیمی را تغییر نمی‌دهد.', 'peyvand-amn-dashboard' ),
			PAS_DASHBOARD_VERSION
		) ) . '</p>';
		$html .= $this->card_close();

		foreach ( $groups as $group_id => $group_label ) {
			$rows = array_values( array_filter( $services, static function ( $service ) use ( $group_id ) {
				return (string) $service['group'] === (string) $group_id;
			} ) );

			if ( empty( $rows ) ) {
				continue;
			}

			$html .= $this->card_open( $group_label );
			$html .= '<div class="pas-table-wrap"><table class="pas-table"><thead><tr>';
			$html .= '<th>' . esc_html__( 'سرویس', 'peyvand-amn-dashboard' ) . '</th>';
			$html .= '<th>' . esc_html__( 'نسخه', 'peyvand-amn-dashboard' ) . '</th>';
			$html .= '<th>' . esc_html__( 'وضعیت', 'peyvand-amn-dashboard' ) . '</th>';
			$html .= '<th>' . esc_html__( 'جزئیات', 'peyvand-amn-dashboard' ) . '</th>';
			$html .= '</tr></thead><tbody>';

			foreach ( $rows as $service ) {
				$html .= '<tr>';
				$html .= '<td data-label="' . esc_attr__( 'سرویس', 'peyvand-amn-dashboard' ) . '"><strong>' . esc_html( (string) $service['label'] ) . '</strong>';
				$html .= $service['required']
					? ' <span class="pas-muted">' . esc_html__( '(ضروری)', 'peyvand-amn-dashboard' ) . '</span>'
					: '';
				$html .= '</td>';
				$html .= '<td data-label="' . esc_attr__( 'نسخه', 'peyvand-amn-dashboard' ) . '"><span dir="ltr">' . esc_html( $service['version'] !== '' ? (string) $service['version'] : '—' ) . '</span></td>';
				$html .= '<td data-label="' . esc_attr__( 'وضعیت', 'peyvand-amn-dashboard' ) . '"><span class="pas-badge ' . esc_attr( PAS_Dashboard_Adapter_Ecosystem::status_class( (string) $service['status'] ) ) . '">' . esc_html( PAS_Dashboard_Adapter_Ecosystem::status_label( (string) $service['status'] ) ) . '</span></td>';
				$html .= '<td data-label="' . esc_attr__( 'جزئیات', 'peyvand-amn-dashboard' ) . '">' . esc_html( (string) $service['detail'] ) . '</td>';
				$html .= '</tr>';
			}

			$html .= '</tbody></table></div>';
			$html .= $this->card_close();
		}

		$html .= $this->card_open( __( 'قراردادهای بین‌افزونه‌ای', 'peyvand-amn-dashboard' ) );
		$html .= '<p class="pas-muted">' . esc_html__( 'هر قرارداد یعنی داشبورد می‌تواند آن کار را از API عمومی افزونهٔ مالک انجام دهد؛ غایب‌بودن یک قرارداد فقط همان ماژول را غیرفعال می‌کند.', 'peyvand-amn-dashboard' ) . '</p>';
		$html .= '<div class="pas-table-wrap"><table class="pas-table"><thead><tr>';
		$html .= '<th>' . esc_html__( 'قرارداد', 'peyvand-amn-dashboard' ) . '</th>';
		$html .= '<th>' . esc_html__( 'قلاب / کلید', 'peyvand-amn-dashboard' ) . '</th>';
		$html .= '<th>' . esc_html__( 'وضعیت', 'peyvand-amn-dashboard' ) . '</th>';
		$html .= '</tr></thead><tbody>';

		foreach ( PAS_Dashboard_Adapter_Ecosystem::contracts() as $contract ) {
			$html .= '<tr>';
			$html .= '<td data-label="' . esc_attr__( 'قرارداد', 'peyvand-amn-dashboard' ) . '">' . esc_html( (string) $contract['label'] ) . '</td>';
			$html .= '<td data-label="' . esc_attr__( 'قلاب / کلید', 'peyvand-amn-dashboard' ) . '"><code dir="ltr">' . esc_html( (string) $contract['hook'] ) . '</code></td>';
			$html .= '<td data-label="' . esc_attr__( 'وضعیت', 'peyvand-amn-dashboard' ) . '">' . ( ! empty( $contract['ok'] )
				? '<span class="pas-badge pas-badge-good">' . esc_html__( 'در دسترس', 'peyvand-amn-dashboard' ) . '</span>'
				: '<span class="pas-badge pas-badge-warn">' . esc_html__( 'در دسترس نیست', 'peyvand-amn-dashboard' ) . '</span>' ) . '</td>';
			$html .= '</tr>';
		}

		$html .= '</tbody></table></div>';
		$html .= $this->card_close();

		$html .= $this->notice( 'info', __( 'این صفحه هیچ کلید، رمز، توکن یا متن پیامی را نمایش نمی‌دهد و هیچ درخواستی به سرویس‌های بیرونی نمی‌فرستد. «متصل» یعنی افزونهٔ مالک و قرارداد عمومی آن در دسترس است، نه اثبات کارکرد وب‌سرویس؛ وضعیت واقعی هر ارسال در لاگ همان افزونه دیده می‌شود.', 'peyvand-amn-dashboard' ) );

		return $html;
	}

	private function stat( $value, $label ) {
		return '<div class="pas-stat"><span class="pas-stat-value">' . esc_html( (string) $value ) . '</span><span class="pas-stat-label">' . esc_html( (string) $label ) . '</span></div>';
	}
}
