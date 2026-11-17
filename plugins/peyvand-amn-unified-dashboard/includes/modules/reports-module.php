<?php
/**
 * ماژول ۶ — گزارش‌ها و آمار عملیاتی (فقط پرونده‌های مجاز).
 */

defined( 'ABSPATH' ) || exit;

final class PAS_Dashboard_Module_Reports extends PAS_Dashboard_Module {

	public function __construct() {
		$this->id          = 'reports';
		$this->title       = __( 'گزارش‌ها', 'peyvand-amn-dashboard' );
		$this->description = __( 'آمار پرونده‌های مجاز شما و خروجی CSV حداقلی.', 'peyvand-amn-dashboard' );
	}

	public function render() {
		if ( ! PAS_Dashboard_Security::can_view_clinical() ) {
			return $this->unavailable( __( 'دسترسی به داده‌های بالینی برای شما مجاز نیست.', 'peyvand-amn-dashboard' ) );
		}

		$user_id = get_current_user_id();
		$cases   = PAS_Dashboard_Core_Bridge::cases( $user_id, 500 );
		$stats   = PAS_Dashboard_Core_Bridge::client_stats();

		$by_status = array();

		$ids = array();
		foreach ( $cases as $case ) {
			if ( ! is_object( $case ) || empty( $case->id ) ) {
				continue;
			}
			$status = (string) ( $case->status ?? '' );
			$status = $status !== '' ? $status : '—';
			$by_status[ $status ] = ( $by_status[ $status ] ?? 0 ) + 1;
			$ids[] = (int) $case->id;
		}

		// آمار جلسه از متادیتای عمومی پرونده — بدون رمزگشایی متن یادداشت.
		$last_map      = method_exists( 'Pas_CF_Case_File', 'get_last_session_map' )
			? (array) Pas_CF_Case_File::get_last_session_map( $ids )
			: array();
		$with_sessions = count( $last_map );

		$html  = $this->card_open( __( 'آمار کلی', 'peyvand-amn-dashboard' ) );
		$html .= '<div class="pas-grid">';
		$html .= '<div class="pas-stat"><span class="pas-stat-value">' . esc_html( (string) count( $cases ) ) . '</span><span class="pas-stat-label">' . esc_html__( 'پرونده‌ی مجاز', 'peyvand-amn-dashboard' ) . '</span></div>';
		$html .= '<div class="pas-stat"><span class="pas-stat-value">' . esc_html( (string) $with_sessions ) . '</span><span class="pas-stat-label">' . esc_html__( 'پرونده دارای جلسه', 'peyvand-amn-dashboard' ) . '</span></div>';
		$html .= '<div class="pas-stat"><span class="pas-stat-value">' . esc_html( (string) (int) ( $stats['clients'] ?? 0 ) ) . '</span><span class="pas-stat-label">' . esc_html__( 'مراجع در رجیستری', 'peyvand-amn-dashboard' ) . '</span></div>';
		$html .= '<div class="pas-stat"><span class="pas-stat-value">' . esc_html( (string) (int) ( $stats['conflicts_open'] ?? 0 ) ) . '</span><span class="pas-stat-label">' . esc_html__( 'تعارض باز هویت', 'peyvand-amn-dashboard' ) . '</span></div>';
		$html .= '</div>';
		$html .= $this->card_close();

		$html .= $this->card_open( __( 'توزیع وضعیت پرونده‌ها', 'peyvand-amn-dashboard' ) );

		if ( empty( $by_status ) ) {
			$html .= '<p class="pas-empty">' . esc_html__( 'داده‌ای برای گزارش وجود ندارد.', 'peyvand-amn-dashboard' ) . '</p>';
		} else {
			$html .= '<div class="pas-table-wrap"><table class="pas-table"><thead><tr>';
			$html .= '<th>' . esc_html__( 'وضعیت', 'peyvand-amn-dashboard' ) . '</th>';
			$html .= '<th>' . esc_html__( 'تعداد', 'peyvand-amn-dashboard' ) . '</th>';
			$html .= '</tr></thead><tbody>';

			foreach ( $by_status as $status => $count ) {
				$html .= '<tr><td>' . esc_html( $status ) . '</td><td>' . esc_html( (string) $count ) . '</td></tr>';
			}

			$html .= '</tbody></table></div>';
		}

		$html .= $this->card_close();

		$html .= $this->card_open( __( 'خروجی CSV', 'peyvand-amn-dashboard' ) );
		$html .= '<p>' . esc_html__( 'خروجی شامل شناسه، وضعیت، درمانگر، تعداد جلسه و تاریخ به‌روزرسانی است. نام و شماره‌ی مراجع در خروجی نیست.', 'peyvand-amn-dashboard' ) . '</p>';
		$html .= '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		$html .= '<input type="hidden" name="action" value="pas_dashboard_export_cases" />';
		$html .= PAS_Dashboard_Security::nonce_field( 'export_cases' );
		$html .= '<button class="pas-btn pas-btn-primary" type="submit">' . esc_html__( 'دریافت CSV', 'peyvand-amn-dashboard' ) . '</button>';
		$html .= '</form>';
		$html .= $this->card_close();

		return $html;
	}
}
