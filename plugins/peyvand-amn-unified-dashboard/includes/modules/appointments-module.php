<?php
/**
 * ماژول ۵ — نوبت‌ها: فهرست، ثبت دستی، ویرایش، وضعیت، حذف و یادآور.
 *
 * نوشتن فقط از طریق Action Bus و API افزونهٔ پنل مراجعان انجام می‌شود.
 * درمانگر بدون مجوز سرپرستی فقط روی نوبت مراجعان پروندهٔ خودش عمل می‌کند.
 */

defined( 'ABSPATH' ) || exit;

final class PAS_Dashboard_Module_Appointments extends PAS_Dashboard_Module {

	public function __construct() {
		$this->id          = 'appointments';
		$this->title       = __( 'نوبت‌ها', 'peyvand-amn-dashboard' );
		$this->description = __( 'ثبت، ویرایش، تغییر وضعیت و یادآوری نوبت‌های پنل مراجعان.', 'peyvand-amn-dashboard' );
	}

	public function render() {
		$can_manage = current_user_can( defined( 'PAS_DASHBOARD_CAP_APPOINTMENTS' ) ? PAS_DASHBOARD_CAP_APPOINTMENTS : 'manage_options' );
		$portal     = PAS_Dashboard_Adapter_Portal::available();
		$search     = isset( $_GET['s'] ) ? PAS_Dashboard_Validation::text( wp_unslash( $_GET['s'] ), 60 ) : '';

		$html = '';

		$html .= $this->render_bookings( $can_manage, $portal, $search );
		$html .= $this->render_upcoming();

		return $html;
	}

	private function render_bookings( $can_manage, $portal, $search ) {
		$html = '';

		if ( ! $portal ) {
			$html .= $this->unavailable( __( 'افزونهٔ پنل مراجعان فعال نیست یا دسترسی‌دهندهٔ عمومی نوبت‌ها را ارائه نمی‌کند.', 'peyvand-amn-dashboard' ) );
			return $html;
		}

		$bookings = PAS_Dashboard_Adapter_Portal::bookings( 200, $search );
		$today    = current_time( 'Y-m-d' );
		$stats    = array(
			'total'    => count( $bookings ),
			'today'    => 0,
			'upcoming' => 0,
			'pending'  => 0,
		);

		foreach ( $bookings as $booking ) {
			$date = (string) ( $booking->booking_date ?? '' );
			if ( $date === $today ) {
				$stats['today']++;
			}
			if ( $date >= $today && ! in_array( (string) $booking->status, array( 'لغو شده', 'ناموفق', 'انجام شده', 'عدم حضور' ), true ) ) {
				$stats['upcoming']++;
			}
			if ( in_array( (string) $booking->status, array( 'در انتظار پرداخت', 'در انتظار تایید واریز', 'در انتظار بررسی' ), true ) ) {
				$stats['pending']++;
			}
		}

		$html .= $this->card_open( __( 'نمای کلی نوبت‌ها', 'peyvand-amn-dashboard' ) );
		$html .= '<div class="pas-grid">';
		$html .= '<div class="pas-stat"><span class="pas-stat-value">' . esc_html( (string) $stats['total'] ) . '</span><span class="pas-stat-label">' . esc_html__( 'کل نوبت‌ها', 'peyvand-amn-dashboard' ) . '</span></div>';
		$html .= '<div class="pas-stat"><span class="pas-stat-value">' . esc_html( (string) $stats['today'] ) . '</span><span class="pas-stat-label">' . esc_html__( 'امروز', 'peyvand-amn-dashboard' ) . '</span></div>';
		$html .= '<div class="pas-stat"><span class="pas-stat-value">' . esc_html( (string) $stats['upcoming'] ) . '</span><span class="pas-stat-label">' . esc_html__( 'پیش‌رو', 'peyvand-amn-dashboard' ) . '</span></div>';
		$html .= '<div class="pas-stat"><span class="pas-stat-value">' . esc_html( (string) $stats['pending'] ) . '</span><span class="pas-stat-label">' . esc_html__( 'در انتظار تأیید', 'peyvand-amn-dashboard' ) . '</span></div>';
		$html .= '</div>';
		$html .= $this->card_close();

		if ( $can_manage ) {
			$html .= $this->card_open( __( 'ثبت نوبت جدید (خارج از سایت)', 'peyvand-amn-dashboard' ) );
			$html .= $this->render_create_form();
			$html .= $this->card_close();
		}

		$html .= $this->card_open( __( 'فهرست نوبت‌ها', 'peyvand-amn-dashboard' ) );

		$html .= '<form method="get" class="pas-filter-form">';
		$html .= '<input type="hidden" name="page" value="' . esc_attr( PAS_DASHBOARD_SLUG ) . '" />';
		$html .= '<input type="hidden" name="module" value="appointments" />';
		$html .= '<div class="pas-form-row"><label for="pas-booking-search">' . esc_html__( 'جست‌وجو (نام، موبایل، کد پیگیری)', 'peyvand-amn-dashboard' ) . '</label>';
		$html .= '<input class="pas-input" type="search" id="pas-booking-search" name="s" value="' . esc_attr( $search ) . '" /></div>';
		$html .= '<button class="pas-btn" type="submit">' . esc_html__( 'جست‌وجو', 'peyvand-amn-dashboard' ) . '</button>';
		$html .= '</form>';

		if ( empty( $bookings ) ) {
			$html .= '<p class="pas-empty">' . esc_html__( 'نوبتی یافت نشد.', 'peyvand-amn-dashboard' ) . '</p>';
			$html .= $this->card_close();
			return $html;
		}

		$html .= '<div class="pas-table-wrap"><table class="pas-table pas-table-bookings"><thead><tr>';
		$html .= '<th>' . esc_html__( 'تاریخ', 'peyvand-amn-dashboard' ) . '</th>';
		$html .= '<th>' . esc_html__( 'ساعت', 'peyvand-amn-dashboard' ) . '</th>';
		$html .= '<th>' . esc_html__( 'مراجع', 'peyvand-amn-dashboard' ) . '</th>';
		$html .= '<th>' . esc_html__( 'نوع', 'peyvand-amn-dashboard' ) . '</th>';
		$html .= '<th>' . esc_html__( 'وضعیت', 'peyvand-amn-dashboard' ) . '</th>';
		$html .= '<th>' . esc_html__( 'عملیات', 'peyvand-amn-dashboard' ) . '</th>';
		$html .= '</tr></thead><tbody>';

		foreach ( $bookings as $booking ) {
			$html .= $this->render_booking_row( $booking, $can_manage );
		}

		$html .= '</tbody></table></div>';
		$html .= $this->card_close();

		if ( $can_manage ) {
			$html .= $this->notice( 'info', __( 'درمانگر بدون مجوز سرپرستی فقط می‌تواند نوبت مراجعانی را تغییر دهد که پرونده‌شان در دسترس اوست؛ سایر ردیف‌ها فقط‌خواندنی‌اند.', 'peyvand-amn-dashboard' ) );
		}

		return $html;
	}

	private function render_create_form() {
		$types   = PAS_Dashboard_Adapter_Portal::session_types();
		$sources = PAS_Dashboard_Adapter_Portal::sources();

		$html  = '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="pas-form">';
		$html .= PAS_Dashboard_Actions::fields( 'booking.create', PAS_Dashboard_Adapter_Portal::module_url() );
		$html .= '<div class="pas-form-grid">';
		$html .= '<div class="pas-form-row"><label for="pas-b-name">' . esc_html__( 'نام و نام خانوادگی *', 'peyvand-amn-dashboard' ) . '</label><input class="pas-input" type="text" id="pas-b-name" name="full_name" required /></div>';
		$html .= '<div class="pas-form-row"><label for="pas-b-mobile">' . esc_html__( 'موبایل *', 'peyvand-amn-dashboard' ) . '</label><input class="pas-input" type="text" id="pas-b-mobile" name="mobile" dir="ltr" placeholder="09123456789" required /></div>';
		$html .= '<div class="pas-form-row"><label for="pas-b-date">' . esc_html__( 'تاریخ (شمسی) *', 'peyvand-amn-dashboard' ) . '</label><input class="pas-input" type="text" id="pas-b-date" name="booking_date_jalali" dir="ltr" inputmode="numeric" placeholder="1405/08/27" required /></div>';
		$html .= '<div class="pas-form-row"><label for="pas-b-time">' . esc_html__( 'ساعت *', 'peyvand-amn-dashboard' ) . '</label><input class="pas-input" type="text" id="pas-b-time" name="booking_time" dir="ltr" placeholder="09:00" required /></div>';
		$html .= $this->select( 'session_type', __( 'نوع جلسه', 'peyvand-amn-dashboard' ), $types );
		$html .= $this->select( 'booking_source', __( 'نحوهٔ رزرو', 'peyvand-amn-dashboard' ), $sources );
		$html .= '<div class="pas-form-row pas-form-row--full"><label for="pas-b-note">' . esc_html__( 'یادداشت درمانگر', 'peyvand-amn-dashboard' ) . '</label><textarea class="pas-textarea" id="pas-b-note" name="therapist_note" rows="2"></textarea></div>';
		$html .= '</div>';
		$html .= '<p class="pas-note-form-actions"><button class="pas-btn pas-btn-primary" type="submit">' . esc_html__( 'ثبت نوبت', 'peyvand-amn-dashboard' ) . '</button></p>';
		$html .= '</form>';

		return $html;
	}

	private function render_booking_row( $booking, $can_manage ) {
		$id      = (int) ( $booking->id ?? 0 );
		$status  = (string) ( $booking->status ?? '' );
		$editable = $can_manage && PAS_Dashboard_Adapter_Portal::in_scope( $booking );
		$name     = PAS_Dashboard_Adapter_Portal::client_name( $booking );
		$mobile   = (string) ( $booking->mobile ?? '' );
		$type_key = (string) ( $booking->session_type ?? '' );
		$types    = PAS_Dashboard_Adapter_Portal::session_types();
		$ref      = (string) ( $booking->ref_id ?? '' );

		$html  = '<tr>';
		$html .= '<td data-label="' . esc_attr__( 'تاریخ', 'peyvand-amn-dashboard' ) . '">' . esc_html( PAS_Dashboard_Core_Bridge::jalali( (string) ( $booking->booking_date ?? '' ) ) ) . '</td>';
		$html .= '<td data-label="' . esc_attr__( 'ساعت', 'peyvand-amn-dashboard' ) . '">' . esc_html( substr( (string) ( $booking->booking_time ?? '' ), 0, 5 ) ) . '</td>';
		$html .= '<td data-label="' . esc_attr__( 'مراجع', 'peyvand-amn-dashboard' ) . '"><strong>' . esc_html( $name ) . '</strong><br /><span class="pas-muted" dir="ltr">' . esc_html( PAS_Dashboard_Core_Bridge::mask_mobile( $mobile ) ) . '</span>';
		if ( $ref !== '' ) {
			$html .= '<br /><span class="pas-muted">' . esc_html( sprintf( /* translators: %s: ref */ __( 'کد: %s', 'peyvand-amn-dashboard' ), $ref ) ) . '</span>';
		}
		$html .= '</td>';
		$html .= '<td data-label="' . esc_attr__( 'نوع', 'peyvand-amn-dashboard' ) . '">' . esc_html( $types[ $type_key ] ?? (string) ( $booking->service_name ?? '—' ) ) . '</td>';
		$html .= '<td data-label="' . esc_attr__( 'وضعیت', 'peyvand-amn-dashboard' ) . '"><span class="pas-badge ' . esc_attr( self::status_class( $status ) ) . '">' . esc_html( $status ) . '</span></td>';

		$html .= '<td data-label="' . esc_attr__( 'عملیات', 'peyvand-amn-dashboard' ) . '">';

		if ( ! $editable ) {
			$html .= '<span class="pas-muted">' . esc_html( $can_manage ? __( 'خارج از دامنهٔ شما', 'peyvand-amn-dashboard' ) : __( 'فقط‌خواندنی', 'peyvand-amn-dashboard' ) ) . '</span>';
		} else {
			$html .= '<div class="pas-row-actions">';

			$html .= '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="pas-inline-form">';
			$html .= PAS_Dashboard_Actions::fields( 'booking.status', PAS_Dashboard_Adapter_Portal::module_url() );
			$html .= '<input type="hidden" name="booking_id" value="' . esc_attr( (string) $id ) . '" />';
			$html .= '<select class="pas-select pas-select-sm" name="status" aria-label="' . esc_attr__( 'وضعیت نوبت', 'peyvand-amn-dashboard' ) . '">';
			foreach ( PAS_Dashboard_Adapter_Portal::statuses() as $option ) {
				$html .= '<option value="' . esc_attr( $option ) . '"' . selected( $option, $status, false ) . '>' . esc_html( $option ) . '</option>';
			}
			$html .= '</select>';
			$html .= '<button class="pas-btn pas-btn-sm" type="submit">' . esc_html__( 'ثبت وضعیت', 'peyvand-amn-dashboard' ) . '</button>';
			$html .= '</form>';

			$body_id = (string) get_option( 'zdp_sms_reminder_body_id', '' );
			if ( $body_id !== '' ) {
				$html .= '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="pas-inline-form">';
				$html .= PAS_Dashboard_Actions::fields( 'booking.reminder', PAS_Dashboard_Adapter_Portal::module_url() );
				$html .= '<input type="hidden" name="booking_id" value="' . esc_attr( (string) $id ) . '" />';
				$html .= '<button class="pas-btn pas-btn-sm" type="submit" data-pas-confirm="' . esc_attr__( 'یادآور نوبت ارسال شود؟', 'peyvand-amn-dashboard' ) . '">' . esc_html__( 'یادآور', 'peyvand-amn-dashboard' ) . '</button>';
				$html .= '</form>';
			}

			$html .= '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="pas-inline-form">';
			$html .= PAS_Dashboard_Actions::fields( 'booking.delete', PAS_Dashboard_Adapter_Portal::module_url() );
			$html .= '<input type="hidden" name="booking_id" value="' . esc_attr( (string) $id ) . '" />';
			$html .= '<button class="pas-btn pas-btn-sm pas-btn-danger" type="submit" data-pas-confirm="' . esc_attr__( 'این نوبت برای همیشه حذف شود؟ برای نوبت واقعی، تغییر وضعیت به «لغو شده» بهتر است.', 'peyvand-amn-dashboard' ) . '">' . esc_html__( 'حذف', 'peyvand-amn-dashboard' ) . '</button>';
			$html .= '</form>';

			$html .= '</div>';

			$html .= '<details class="pas-row-details"><summary>' . esc_html__( 'ویرایش جزئیات', 'peyvand-amn-dashboard' ) . '</summary>';
			$html .= '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="pas-form pas-inline-form">';
			$html .= PAS_Dashboard_Actions::fields( 'booking.update', PAS_Dashboard_Adapter_Portal::module_url() );
			$html .= '<input type="hidden" name="booking_id" value="' . esc_attr( (string) $id ) . '" />';
			$html .= '<div class="pas-form-row"><label>' . esc_html__( 'تاریخ (شمسی)', 'peyvand-amn-dashboard' ) . '</label><input class="pas-input" type="text" name="booking_date_jalali" dir="ltr" value="' . esc_attr( PAS_Dashboard_Core_Bridge::jalali( (string) ( $booking->booking_date ?? '' ) ) ) . '" /></div>';
			$html .= '<div class="pas-form-row"><label>' . esc_html__( 'ساعت', 'peyvand-amn-dashboard' ) . '</label><input class="pas-input" type="text" name="booking_time" dir="ltr" value="' . esc_attr( substr( (string) ( $booking->booking_time ?? '' ), 0, 5 ) ) . '" /></div>';

			$html .= '<div class="pas-form-row"><label>' . esc_html__( 'نوع جلسه', 'peyvand-amn-dashboard' ) . '</label><select class="pas-select" name="session_type">';
			foreach ( $types as $key => $label ) {
				$html .= '<option value="' . esc_attr( $key ) . '"' . selected( $key, $type_key, false ) . '>' . esc_html( $label ) . '</option>';
			}
			$html .= '</select></div>';

			$html .= '<div class="pas-form-row"><label>' . esc_html__( 'نحوهٔ رزرو', 'peyvand-amn-dashboard' ) . '</label><select class="pas-select" name="booking_source">';
			foreach ( PAS_Dashboard_Adapter_Portal::all_sources() as $key => $label ) {
				$html .= '<option value="' . esc_attr( $key ) . '"' . selected( $key, (string) ( $booking->booking_source ?? '' ), false ) . '>' . esc_html( $label ) . '</option>';
			}
			$html .= '</select></div>';

			$html .= '<div class="pas-form-row"><label>' . esc_html__( 'وضعیت', 'peyvand-amn-dashboard' ) . '</label><select class="pas-select" name="status">';
			foreach ( PAS_Dashboard_Adapter_Portal::statuses() as $option ) {
				$html .= '<option value="' . esc_attr( $option ) . '"' . selected( $option, $status, false ) . '>' . esc_html( $option ) . '</option>';
			}
			$html .= '</select></div>';

			$html .= '<div class="pas-form-row pas-form-row--full"><label>' . esc_html__( 'یادداشت درمانگر', 'peyvand-amn-dashboard' ) . '</label><textarea class="pas-textarea" name="therapist_note" rows="2">' . esc_textarea( (string) ( $booking->therapist_note ?? '' ) ) . '</textarea></div>';
			$html .= '<button class="pas-btn pas-btn-primary" type="submit">' . esc_html__( 'ذخیرهٔ تغییرات', 'peyvand-amn-dashboard' ) . '</button>';
			$html .= '</form>';
			$html .= '</details>';
		}

		$html .= '</td>';
		$html .= '</tr>';

		return $html;
	}

	private function render_upcoming() {
		if ( ! PAS_Dashboard_Core_Bridge::has_case_file() ) {
			return '';
		}

		$cases    = PAS_Dashboard_Core_Bridge::cases( get_current_user_id(), 200 );
		$upcoming = array();

		foreach ( $cases as $case ) {
			if ( ! is_object( $case ) || empty( $case->id ) ) {
				continue;
			}

			$next = PAS_Dashboard_Core_Bridge::next_session( $case );

			if ( $next && ! empty( $next->next_session_date ) ) {
				$upcoming[] = array(
					'case_id' => (int) $case->id,
					'name'    => (string) ( $case->full_name ?? '' ),
					'date'    => (string) $next->next_session_date,
					'number'  => (int) ( $next->session_number ?? 0 ),
				);
			}
		}

		usort(
			$upcoming,
			static function ( $a, $b ) {
				return strcmp( $a['date'], $b['date'] );
			}
		);

		$html  = $this->card_open( __( 'جلسه‌های پیش‌روی پرونده‌ها', 'peyvand-amn-dashboard' ) );

		if ( empty( $upcoming ) ) {
			$html .= '<p class="pas-empty">' . esc_html__( 'جلسهٔ آینده‌ای ثبت نشده است.', 'peyvand-amn-dashboard' ) . '</p>';
		} else {
			$html .= '<div class="pas-table-wrap"><table class="pas-table"><thead><tr>';
			$html .= '<th>' . esc_html__( 'پرونده', 'peyvand-amn-dashboard' ) . '</th>';
			$html .= '<th>' . esc_html__( 'تاریخ (شمسی)', 'peyvand-amn-dashboard' ) . '</th>';
			$html .= '<th>' . esc_html__( 'شماره جلسه', 'peyvand-amn-dashboard' ) . '</th>';
			$html .= '<th>' . esc_html__( 'عملیات', 'peyvand-amn-dashboard' ) . '</th>';
			$html .= '</tr></thead><tbody>';

			foreach ( array_slice( $upcoming, 0, 50 ) as $row ) {
				$html .= '<tr>';
				$html .= '<td data-label="' . esc_attr__( 'پرونده', 'peyvand-amn-dashboard' ) . '">#' . esc_html( (string) $row['case_id'] ) . ' — ' . esc_html( mb_strimwidth( $row['name'], 0, 30, '…' ) ) . '</td>';
				$html .= '<td data-label="' . esc_attr__( 'تاریخ (شمسی)', 'peyvand-amn-dashboard' ) . '">' . esc_html( PAS_Dashboard_Core_Bridge::jalali( $row['date'] ) ) . '</td>';
				$html .= '<td data-label="' . esc_attr__( 'شماره جلسه', 'peyvand-amn-dashboard' ) . '">' . esc_html( (string) $row['number'] ) . '</td>';
				$html .= '<td data-label="' . esc_attr__( 'عملیات', 'peyvand-amn-dashboard' ) . '"><a class="pas-btn pas-btn-sm" href="' . esc_url( add_query_arg( array( 'page' => PAS_DASHBOARD_SLUG, 'module' => 'case_files', 'case_id' => $row['case_id'] ), admin_url( 'admin.php' ) ) ) . '">' . esc_html__( 'پرونده', 'peyvand-amn-dashboard' ) . '</a></td>';
				$html .= '</tr>';
			}

			$html .= '</tbody></table></div>';
		}

		$html .= $this->card_close();

		return $html;
	}

	private function select( $name, $label, array $options ) {
		$html = '<div class="pas-form-row"><label for="pas-' . esc_attr( $name ) . '">' . esc_html( $label ) . '</label>';
		$html .= '<select class="pas-select" id="pas-' . esc_attr( $name ) . '" name="' . esc_attr( $name ) . '">';

		foreach ( $options as $value => $text ) {
			$html .= '<option value="' . esc_attr( (string) $value ) . '">' . esc_html( (string) $text ) . '</option>';
		}

		$html .= '</select></div>';

		return $html;
	}

	private static function status_class( $status ) {
		if ( in_array( $status, array( 'تایید شده', 'فعال', 'انجام شده' ), true ) ) {
			return 'pas-badge-good';
		}
		if ( in_array( $status, array( 'لغو شده', 'ناموفق', 'عدم حضور' ), true ) ) {
			return 'pas-badge-danger';
		}

		return 'pas-badge-warn';
	}
}
