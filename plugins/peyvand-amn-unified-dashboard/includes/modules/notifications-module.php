<?php
/**
 * ماژول ۸ — اعلان‌ها.
 */

defined( 'ABSPATH' ) || exit;

final class PAS_Dashboard_Module_Notifications extends PAS_Dashboard_Module {

	public function __construct() {
		$this->id          = 'notifications';
		$this->title       = __( 'اعلان‌ها', 'peyvand-amn-dashboard' );
		$this->description = __( 'یادآور جلسه‌های پیش‌رو و رخدادهای مرتبط با پرونده‌های شما.', 'peyvand-amn-dashboard' );
	}

	public function render() {
		if ( ! PAS_Dashboard_Permissions::feature( 'enable_notifications' ) ) {
			return $this->unavailable( __( 'اعلان‌ها در تنظیمات داشبورد غیرفعال شده‌اند.', 'peyvand-amn-dashboard' ) );
		}

		$user_id = get_current_user_id();
		$rows    = PAS_Dashboard_Notifications::recent( $user_id, 50 );
		$unread  = PAS_Dashboard_Notifications::unread_count( $user_id );

		$html  = $this->card_open( __( 'اعلان‌های شما', 'peyvand-amn-dashboard' ) );
		$html .= '<p>' . esc_html__( 'خوانده‌نشده:', 'peyvand-amn-dashboard' ) . ' <strong>' . esc_html( (string) $unread ) . '</strong></p>';

		if ( ! empty( $rows ) ) {
			$html .= '<p><button type="button" class="pas-btn" data-pas-read-notification="0">' . esc_html__( 'همه را خوانده‌شده کن', 'peyvand-amn-dashboard' ) . '</button></p>';
		}

		if ( empty( $rows ) ) {
			$html .= '<p class="pas-empty">' . esc_html__( 'اعلانی وجود ندارد. اعلان‌ها با بازکردن داشبورد از جلسه‌های پیش‌رو ساخته می‌شوند.', 'peyvand-amn-dashboard' ) . '</p>';
		} else {
			$html .= '<div class="pas-table-wrap"><table class="pas-table"><thead><tr>';
			$html .= '<th>' . esc_html__( 'عنوان', 'peyvand-amn-dashboard' ) . '</th>';
			$html .= '<th>' . esc_html__( 'زمان', 'peyvand-amn-dashboard' ) . '</th>';
			$html .= '<th>' . esc_html__( 'وضعیت', 'peyvand-amn-dashboard' ) . '</th>';
			$html .= '<th>' . esc_html__( 'عملیات', 'peyvand-amn-dashboard' ) . '</th>';
			$html .= '</tr></thead><tbody>';

			foreach ( $rows as $row ) {
				$is_read = ! empty( $row->read_at );
				$html   .= '<tr>';
				$html   .= '<td data-label="' . esc_attr__( 'عنوان', 'peyvand-amn-dashboard' ) . '">' . esc_html( (string) $row->title ) . '</td>';
				$html   .= '<td data-label="' . esc_attr__( 'زمان', 'peyvand-amn-dashboard' ) . '">' . esc_html( PAS_Dashboard_Core_Bridge::jalali_datetime( (string) $row->created_at ) ) . '</td>';
				$html   .= '<td data-label="' . esc_attr__( 'وضعیت', 'peyvand-amn-dashboard' ) . '">' . ( $is_read
					? '<span class="pas-badge pas-badge-good">' . esc_html__( 'خوانده‌شده', 'peyvand-amn-dashboard' ) . '</span>'
					: '<span class="pas-badge pas-badge-warn">' . esc_html__( 'نخوانده', 'peyvand-amn-dashboard' ) . '</span>' ) . '</td>';
				$html   .= '<td data-label="' . esc_attr__( 'عملیات', 'peyvand-amn-dashboard' ) . '">';
				if ( ! $is_read ) {
					$html .= '<button type="button" class="pas-btn" data-pas-read-notification="' . esc_attr( (string) (int) $row->id ) . '">' . esc_html__( 'خواندم', 'peyvand-amn-dashboard' ) . '</button>';
				}
				if ( ! empty( $row->ref ) && ctype_digit( (string) $row->ref ) ) {
					$html .= ' <a class="pas-btn" href="' . esc_url( add_query_arg( array( 'page' => PAS_DASHBOARD_SLUG, 'module' => 'case_files', 'case_id' => (int) $row->ref ), admin_url( 'admin.php' ) ) ) . '">' . esc_html__( 'پرونده', 'peyvand-amn-dashboard' ) . '</a>';
				}
				$html .= '</td>';
				$html .= '</tr>';
			}

			$html .= '</tbody></table></div>';
			$html .= '<p class="pas-muted">' . esc_html__( 'متن اعلان‌ها هیچ نام یا داده‌ی بالینی ندارد؛ فقط شناسه‌ی پرونده.', 'peyvand-amn-dashboard' ) . '</p>';
		}

		$html .= $this->card_close();

		return $html;
	}
}
