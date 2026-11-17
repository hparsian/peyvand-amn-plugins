<?php
/**
 * ماژول ۱ — نمای کلی.
 */

defined( 'ABSPATH' ) || exit;

final class PAS_Dashboard_Module_Overview extends PAS_Dashboard_Module {

	public function __construct() {
		$this->id          = 'overview';
		$this->title       = __( 'نمای کلی', 'peyvand-amn-dashboard' );
		$this->description = __( 'خلاصه‌ی عملیاتی پرونده‌های مجاز شما.', 'peyvand-amn-dashboard' );
	}

	public function render() {
		$user_id = get_current_user_id();
		// محدود به ۶۰ پرونده برای پرهیز از N+1 سنگین در بارگذاری داشبورد.
		$cases   = PAS_Dashboard_Core_Bridge::cases( $user_id, 60 );
		$stats   = PAS_Dashboard_Core_Bridge::client_stats();

		$upcoming = 0;
		$recent   = array();

		foreach ( $cases as $case ) {
			if ( ! is_object( $case ) ) {
				continue;
			}

			$next = PAS_Dashboard_Core_Bridge::next_session( $case );

			if ( $next && ! empty( $next->next_session_date ) ) {
				$ts = strtotime( (string) $next->next_session_date );
				if ( $ts !== false && $ts >= strtotime( gmdate( 'Y-m-d' ) ) ) {
					$upcoming++;
				}
			}

			if ( count( $recent ) < 6 ) {
				$recent[] = $case;
			}
		}

		$html  = $this->card_open( __( 'شاخص‌های کلیدی', 'peyvand-amn-dashboard' ) );
		$html .= '<div class="pas-grid">';
		$html .= $this->stat( count( $cases ), __( 'پرونده‌ی مجاز', 'peyvand-amn-dashboard' ) );
		$html .= $this->stat( $upcoming, __( 'جلسه‌ی پیش‌رو', 'peyvand-amn-dashboard' ) );
		$html .= $this->stat( (int) ( $stats['clients'] ?? 0 ), __( 'مراجع در رجیستری', 'peyvand-amn-dashboard' ) );
		$html .= $this->stat( (int) ( $stats['review'] ?? 0 ), __( 'نیازمند بررسی هویت', 'peyvand-amn-dashboard' ) );
		$html .= '</div>';
		$html .= $this->card_close();

		$html .= $this->card_open( __( 'پرونده‌های اخیر', 'peyvand-amn-dashboard' ) );

		if ( empty( $recent ) ) {
			$html .= '<p class="pas-empty">' . esc_html__( 'هنوز پرونده‌ای به شما تخصیص داده نشده است.', 'peyvand-amn-dashboard' ) . '</p>';
		} else {
			$html .= '<div class="pas-table-wrap"><table class="pas-table"><thead><tr>';
			$html .= '<th>' . esc_html__( 'شناسه', 'peyvand-amn-dashboard' ) . '</th>';
			$html .= '<th>' . esc_html__( 'مراجع', 'peyvand-amn-dashboard' ) . '</th>';
			$html .= '<th>' . esc_html__( 'وضعیت', 'peyvand-amn-dashboard' ) . '</th>';
			$html .= '<th>' . esc_html__( 'آخرین به‌روزرسانی', 'peyvand-amn-dashboard' ) . '</th>';
			$html .= '<th>' . esc_html__( 'عملیات', 'peyvand-amn-dashboard' ) . '</th>';
			$html .= '</tr></thead><tbody>';

			foreach ( $recent as $case ) {
				$case_id = (int) ( $case->id ?? 0 );
				$html   .= '<tr>';
				$html   .= '<td>#' . esc_html( (string) $case_id ) . '</td>';
				$html   .= '<td>' . esc_html( mb_strimwidth( (string) ( $case->full_name ?? '' ), 0, 40, '…' ) ) . '</td>';
				$html   .= '<td><span class="pas-badge">' . esc_html( (string) ( $case->status ?? '' ) ) . '</span></td>';
				$html   .= '<td>' . esc_html( PAS_Dashboard_Core_Bridge::jalali( (string) ( $case->updated_at ?? '' ) ) ) . '</td>';
				$case_url = add_query_arg(
					array(
						'page'    => PAS_DASHBOARD_SLUG,
						'module'  => 'case_files',
						'case_id' => $case_id,
					),
					admin_url( 'admin.php' )
				);
				$html   .= '<td><a class="pas-btn" href="' . esc_url( $case_url ) . '">' . esc_html__( 'پرونده', 'peyvand-amn-dashboard' ) . '</a></td>';
				$html   .= '</tr>';
			}

			$html .= '</tbody></table></div>';
		}

		$html .= $this->card_close();

		$html .= $this->card_open( __( 'وضعیت اکوسیستم', 'peyvand-amn-dashboard' ) );
		$html .= '<ul class="pas-list">';
		$html .= $this->status_line( __( 'هسته‌ی مشترک', 'peyvand-amn-dashboard' ), PAS_Dashboard_Core_Bridge::has_core() );
		$html .= $this->status_line( __( 'پرونده‌های بالینی', 'peyvand-amn-dashboard' ), PAS_Dashboard_Core_Bridge::has_case_file() );
		$html .= $this->status_line( __( 'کلید رمزنگاری', 'peyvand-amn-dashboard' ), PAS_Dashboard_Core_Bridge::crypto_ready() );
		$html .= $this->status_line( __( 'پل پیامک', 'peyvand-amn-dashboard' ), PAS_Dashboard_Core_Bridge::sms_available() );
		$html .= '</ul>';
		$html .= $this->card_close();

		if ( ! PAS_Dashboard_Core_Bridge::crypto_ready() ) {
			$html = $this->notice(
				'warning',
				__( 'کلید رمزنگاری تنظیم نشده است. یادداشت‌های بالینی نه نمایش داده می‌شوند و نه ثبت می‌شوند.', 'peyvand-amn-dashboard' )
			) . $html;
		}

		return $html;
	}

	private function stat( $value, $label ) {
		return '<div class="pas-stat"><span class="pas-stat-value">' . esc_html( (string) (int) $value ) . '</span>'
			. '<span class="pas-stat-label">' . esc_html( $label ) . '</span></div>';
	}

	private function status_line( $label, $ok ) {
		$badge = $ok
			? '<span class="pas-badge pas-badge-good">' . esc_html__( 'فعال', 'peyvand-amn-dashboard' ) . '</span>'
			: '<span class="pas-badge pas-badge-warn">' . esc_html__( 'غیرفعال', 'peyvand-amn-dashboard' ) . '</span>';

		return '<li>' . esc_html( $label ) . ': ' . $badge . '</li>';
	}
}
