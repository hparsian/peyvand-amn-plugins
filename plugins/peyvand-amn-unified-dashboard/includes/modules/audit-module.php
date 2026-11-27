<?php
/**
 * ماژول ۱۱ — لاگ ممیزی (فقط مدیر).
 */

defined( 'ABSPATH' ) || exit;

final class PAS_Dashboard_Module_Audit extends PAS_Dashboard_Module {

	public function __construct() {
		$this->id          = 'audit';
		$this->title       = __( 'ممیزی', 'peyvand-amn-dashboard' );
		$this->description = __( 'رخدادهای داشبورد — بدون محتوای بالینی و بدون کلید.', 'peyvand-amn-dashboard' );
	}

	public function render() {
		if ( ! PAS_Dashboard_Security::can_manage() ) {
			return $this->unavailable( __( 'نمایش لاگ ممیزی فقط برای مدیر سایت مجاز است.', 'peyvand-amn-dashboard' ) );
		}

		$page     = PAS_Dashboard_Validation::page();
		$per_page = 50;
		$offset   = ( $page - 1 ) * $per_page;
		$rows     = PAS_Dashboard_Audit_Log::recent( $per_page, $offset );
		$total    = PAS_Dashboard_Audit_Log::count();

		$html  = $this->card_open( __( 'رخدادهای اخیر', 'peyvand-amn-dashboard' ) );

		if ( empty( $rows ) ) {
			$html .= '<p class="pas-empty">' . esc_html__( 'رخدادی ثبت نشده است.', 'peyvand-amn-dashboard' ) . '</p>';
		} else {
			$html .= '<div class="pas-table-wrap"><table class="pas-table"><thead><tr>';
			$html .= '<th>' . esc_html__( 'زمان', 'peyvand-amn-dashboard' ) . '</th>';
			$html .= '<th>' . esc_html__( 'کاربر', 'peyvand-amn-dashboard' ) . '</th>';
			$html .= '<th>' . esc_html__( 'رخداد', 'peyvand-amn-dashboard' ) . '</th>';
			$html .= '<th>' . esc_html__( 'شیء', 'peyvand-amn-dashboard' ) . '</th>';
			$html .= '<th>' . esc_html__( 'نتیجه', 'peyvand-amn-dashboard' ) . '</th>';
			$html .= '</tr></thead><tbody>';

			foreach ( $rows as $row ) {
				$result = (string) ( $row->result ?? '' );
				$class  = $result === 'ok' ? 'pas-badge-good' : ( $result === 'denied' ? 'pas-badge-danger' : 'pas-badge-warn' );

				$html .= '<tr>';
				$html .= '<td data-label="' . esc_attr__( 'زمان', 'peyvand-amn-dashboard' ) . '">' . esc_html( PAS_Dashboard_Core_Bridge::jalali_datetime( (string) ( $row->created_at ?? '' ) ) ) . '</td>';
				$html .= '<td data-label="' . esc_attr__( 'کاربر', 'peyvand-amn-dashboard' ) . '">' . esc_html( PAS_Dashboard_Core_Bridge::therapist_name( (int) ( $row->user_id ?? 0 ) ) ) . '</td>';
				$html .= '<td data-label="' . esc_attr__( 'رخداد', 'peyvand-amn-dashboard' ) . '"><code>' . esc_html( (string) ( $row->action ?? '' ) ) . '</code></td>';
				$html .= '<td data-label="' . esc_attr__( 'شیء', 'peyvand-amn-dashboard' ) . '">' . esc_html( (string) ( $row->object_type ?? '' ) . ( ! empty( $row->object_id ) ? ' #' . (int) $row->object_id : '' ) ) . '</td>';
				$html .= '<td data-label="' . esc_attr__( 'نتیجه', 'peyvand-amn-dashboard' ) . '"><span class="pas-badge ' . esc_attr( $class ) . '">' . esc_html( $result ) . '</span></td>';
				$html .= '</tr>';
			}

			$html .= '</tbody></table></div>';
			$html .= $this->pagination( $total, $per_page, $page );
		}

		$html .= $this->card_close();
		$html .= $this->notice( 'info', __( 'لاگ ممیزی محتوای یادداشت، پاسخ آزمون، رمز، توکن یا کلید رمزنگاری را ذخیره نمی‌کند. IP به‌صورت هش ذخیره می‌شود.', 'peyvand-amn-dashboard' ) );

		return $html;
	}
}
