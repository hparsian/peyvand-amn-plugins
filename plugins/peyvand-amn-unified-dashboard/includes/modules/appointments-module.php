<?php
/**
 * ماژول ۵ — نوبت‌ها و جلسه‌های پیش‌رو (فقط‌خواندنی).
 *
 * ایجاد/ویرایش نوبت از داشبورد انجام نمی‌شود، چون API نوشتنی عمومی و تأییدشده
 * برای نوبت در اختیار داشبورد نیست. نمایش از get_next_session و
 * get_linked_bookings (APIهای عمومی پرونده) انجام می‌شود.
 */

defined( 'ABSPATH' ) || exit;

final class PAS_Dashboard_Module_Appointments extends PAS_Dashboard_Module {

	public function __construct() {
		$this->id          = 'appointments';
		$this->title       = __( 'نوبت‌ها', 'peyvand-amn-dashboard' );
		$this->description = __( 'جلسه‌های پیش‌رو و نوبت‌های ثبت‌شده (فقط‌خواندنی).', 'peyvand-amn-dashboard' );
	}

	public function render() {
		if ( ! PAS_Dashboard_Core_Bridge::has_case_file() ) {
			return $this->unavailable( __( 'افزونه‌ی پرونده‌های بالینی فعال نیست.', 'peyvand-amn-dashboard' ) );
		}

		$user_id = get_current_user_id();
		$cases   = PAS_Dashboard_Core_Bridge::cases( $user_id, 200 );

		$upcoming = array();
		$bookings = array();

		foreach ( $cases as $case ) {
			if ( ! is_object( $case ) || empty( $case->id ) ) {
				continue;
			}

			$next = PAS_Dashboard_Core_Bridge::next_session( $case );

			if ( $next && ! empty( $next->next_session_date ) ) {
				$upcoming[] = array(
					'case_id' => (int) $case->id,
					'date'    => (string) $next->next_session_date,
					'number'  => (int) ( $next->session_number ?? 0 ),
				);
			}

			if ( count( $bookings ) < 60 && ! empty( $case->phone ) ) {
				foreach ( PAS_Dashboard_Core_Bridge::bookings( (string) $case->phone ) as $booking ) {
					$bookings[] = array(
						'case_id' => (int) $case->id,
						'booking' => $booking,
					);
					if ( count( $bookings ) >= 60 ) {
						break;
					}
				}
			}
		}

		usort(
			$upcoming,
			static function ( $a, $b ) {
				return strcmp( $a['date'], $b['date'] );
			}
		);

		$html  = $this->card_open( __( 'جلسه‌های پیش‌رو', 'peyvand-amn-dashboard' ) );

		if ( empty( $upcoming ) ) {
			$html .= '<p class="pas-empty">' . esc_html__( 'جلسه‌ی آینده‌ای ثبت نشده است.', 'peyvand-amn-dashboard' ) . '</p>';
		} else {
			$html .= '<div class="pas-table-wrap"><table class="pas-table"><thead><tr>';
			$html .= '<th>' . esc_html__( 'پرونده', 'peyvand-amn-dashboard' ) . '</th>';
			$html .= '<th>' . esc_html__( 'تاریخ (شمسی)', 'peyvand-amn-dashboard' ) . '</th>';
			$html .= '<th>' . esc_html__( 'شماره جلسه', 'peyvand-amn-dashboard' ) . '</th>';
			$html .= '<th>' . esc_html__( 'عملیات', 'peyvand-amn-dashboard' ) . '</th>';
			$html .= '</tr></thead><tbody>';

			foreach ( array_slice( $upcoming, 0, 50 ) as $row ) {
				$html .= '<tr>';
				$html .= '<td data-label="' . esc_attr__( 'پرونده', 'peyvand-amn-dashboard' ) . '">#' . esc_html( (string) $row['case_id'] ) . '</td>';
				$html .= '<td data-label="' . esc_attr__( 'تاریخ (شمسی)', 'peyvand-amn-dashboard' ) . '">' . esc_html( PAS_Dashboard_Core_Bridge::jalali( $row['date'] ) ) . '</td>';
				$html .= '<td data-label="' . esc_attr__( 'شماره جلسه', 'peyvand-amn-dashboard' ) . '">' . esc_html( (string) $row['number'] ) . '</td>';
				$html .= '<td data-label="' . esc_attr__( 'عملیات', 'peyvand-amn-dashboard' ) . '"><a class="pas-btn" href="' . esc_url( add_query_arg( array( 'page' => PAS_DASHBOARD_SLUG, 'module' => 'case_files', 'case_id' => $row['case_id'] ), admin_url( 'admin.php' ) ) ) . '">' . esc_html__( 'پرونده', 'peyvand-amn-dashboard' ) . '</a></td>';
				$html .= '</tr>';
			}

			$html .= '</tbody></table></div>';
		}

		$html .= $this->card_close();

		$html .= $this->card_open( __( 'نوبت‌های پنل مراجعان', 'peyvand-amn-dashboard' ) );

		if ( empty( $bookings ) ) {
			$html .= '<p class="pas-empty">' . esc_html__( 'نوبتی از پنل مراجعان یافت نشد (یا افزونه‌ی پنل فعال نیست).', 'peyvand-amn-dashboard' ) . '</p>';
		} else {
			$html .= '<div class="pas-table-wrap"><table class="pas-table"><thead><tr>';
			$html .= '<th>' . esc_html__( 'پرونده', 'peyvand-amn-dashboard' ) . '</th>';
			$html .= '<th>' . esc_html__( 'اطلاعات نوبت', 'peyvand-amn-dashboard' ) . '</th>';
			$html .= '</tr></thead><tbody>';

			foreach ( $bookings as $row ) {
				$booking = is_object( $row['booking'] ) ? (array) $row['booking'] : (array) $row['booking'];
				$summary = array();

				foreach ( array( 'date', 'time', 'status', 'service' ) as $key ) {
					if ( isset( $booking[ $key ] ) && is_scalar( $booking[ $key ] ) ) {
						$summary[] = $key . ': ' . (string) $booking[ $key ];
					}
				}

				$html .= '<tr>';
				$html .= '<td data-label="' . esc_attr__( 'پرونده', 'peyvand-amn-dashboard' ) . '">#' . esc_html( (string) $row['case_id'] ) . '</td>';
				$html .= '<td data-label="' . esc_attr__( 'اطلاعات نوبت', 'peyvand-amn-dashboard' ) . '">' . esc_html( $summary ? implode( ' — ', $summary ) : '—' ) . '</td>';
				$html .= '</tr>';
			}

			$html .= '</tbody></table></div>';
		}

		$html .= $this->card_close();
		$html .= $this->notice( 'info', __( 'ایجاد، ویرایش و لغو نوبت از داشبورد انجام نمی‌شود؛ این ماژول فقط‌خواندنی است تا از دوباره‌سازی منطق نوبت‌دهی جلوگیری شود.', 'peyvand-amn-dashboard' ) );

		return $html;
	}
}
