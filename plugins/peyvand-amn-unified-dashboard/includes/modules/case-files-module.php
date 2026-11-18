<?php
/**
 * ماژول ۳ — پرونده‌ها و یادداشت‌های جلسه.
 *
 * هر عملیات: احراز هویت → کپابیلیتی بالینی → مجوز پرونده → عملیات API.
 */

defined( 'ABSPATH' ) || exit;

final class PAS_Dashboard_Module_Case_Files extends PAS_Dashboard_Module {

	public function __construct() {
		$this->id          = 'case_files';
		$this->title       = __( 'پرونده‌های بالینی', 'peyvand-amn-dashboard' );
		$this->description = __( 'فقط پرونده‌های تخصیص‌داده‌شده به شما؛ یادداشت‌ها رمزنگاری‌شده‌اند.', 'peyvand-amn-dashboard' );
	}

	public function render() {
		if ( ! PAS_Dashboard_Core_Bridge::has_case_file() ) {
			return $this->unavailable( __( 'افزونه‌ی پرونده‌های بالینی فعال نیست.', 'peyvand-amn-dashboard' ) );
		}

		$case_id = isset( $_GET['case_id'] ) ? PAS_Dashboard_Validation::id( wp_unslash( $_GET['case_id'] ) ) : 0;

		if ( $case_id > 0 ) {
			return $this->render_detail( $case_id );
		}

		return $this->render_list();
	}

	private function render_list() {
		$user_id  = get_current_user_id();
		$cases    = PAS_Dashboard_Core_Bridge::cases( $user_id, 500 );
		$per_page = $this->per_page();
		$page     = PAS_Dashboard_Validation::page();
		$slice    = $this->slice_for_page( $cases, $per_page, $page );

		$html = '';

		if ( ! PAS_Dashboard_Core_Bridge::crypto_ready() ) {
			$html .= $this->notice( 'warning', __( 'کلید رمزنگاری تنظیم نشده است؛ یادداشت‌ها نمایش داده نمی‌شوند.', 'peyvand-amn-dashboard' ) );
		}

		$html .= $this->card_open( __( 'پرونده‌های شما', 'peyvand-amn-dashboard' ) );

		if ( empty( $cases ) ) {
			$html .= '<p class="pas-empty">' . esc_html__( 'پرونده‌ای به شما تخصیص داده نشده است.', 'peyvand-amn-dashboard' ) . '</p>';
			$html .= $this->card_close();
			return $html;
		}

		$ids      = array();
		foreach ( $cases as $case ) {
			if ( is_object( $case ) && ! empty( $case->id ) ) {
				$ids[] = (int) $case->id;
			}
		}

		$last_map = method_exists( 'Pas_CF_Case_File', 'get_last_session_map' )
			? (array) Pas_CF_Case_File::get_last_session_map( $ids )
			: array();

		$html .= '<div class="pas-table-wrap"><table class="pas-table"><thead><tr>';
		$html .= '<th>' . esc_html__( 'شناسه', 'peyvand-amn-dashboard' ) . '</th>';
		$html .= '<th>' . esc_html__( 'مراجع', 'peyvand-amn-dashboard' ) . '</th>';
		$html .= '<th>' . esc_html__( 'وضعیت', 'peyvand-amn-dashboard' ) . '</th>';
		$html .= '<th>' . esc_html__( 'درمانگر', 'peyvand-amn-dashboard' ) . '</th>';
		$html .= '<th>' . esc_html__( 'آخرین جلسه', 'peyvand-amn-dashboard' ) . '</th>';
		$html .= '<th>' . esc_html__( 'به‌روزرسانی', 'peyvand-amn-dashboard' ) . '</th>';
		$html .= '<th>' . esc_html__( 'عملیات', 'peyvand-amn-dashboard' ) . '</th>';
		$html .= '</tr></thead><tbody>';

		foreach ( $slice as $case ) {
			$id = (int) ( $case->id ?? 0 );
			$html .= '<tr>';
			$html .= '<td data-label="' . esc_attr__( 'شناسه', 'peyvand-amn-dashboard' ) . '">#' . esc_html( (string) $id ) . '</td>';
			$html .= '<td data-label="' . esc_attr__( 'مراجع', 'peyvand-amn-dashboard' ) . '">' . esc_html( mb_strimwidth( (string) ( $case->full_name ?? '' ), 0, 36, '…' ) ) . '</td>';
			$html .= '<td data-label="' . esc_attr__( 'وضعیت', 'peyvand-amn-dashboard' ) . '"><span class="pas-badge">' . esc_html( (string) ( $case->status ?? '' ) ) . '</span></td>';
			$html .= '<td data-label="' . esc_attr__( 'درمانگر', 'peyvand-amn-dashboard' ) . '">' . esc_html( PAS_Dashboard_Core_Bridge::therapist_name( (int) ( $case->assigned_therapist ?? 0 ) ) ) . '</td>';
			$html .= '<td data-label="' . esc_attr__( 'آخرین جلسه', 'peyvand-amn-dashboard' ) . '">' . esc_html( PAS_Dashboard_Core_Bridge::jalali( (string) ( $last_map[ $id ] ?? '' ) ) ) . '</td>';
			$html .= '<td data-label="' . esc_attr__( 'به‌روزرسانی', 'peyvand-amn-dashboard' ) . '">' . esc_html( PAS_Dashboard_Core_Bridge::jalali( (string) ( $case->updated_at ?? '' ) ) ) . '</td>';
			$html .= '<td data-label="' . esc_attr__( 'عملیات', 'peyvand-amn-dashboard' ) . '"><a class="pas-btn" href="' . esc_url( $this->url( array( 'case_id' => $id ) ) ) . '">' . esc_html__( 'بازکردن', 'peyvand-amn-dashboard' ) . '</a></td>';
			$html .= '</tr>';
		}

		$html .= '</tbody></table></div>';
		$html .= $this->pagination( count( $cases ), $per_page, $page );
		$html .= $this->card_close();

		return $html;
	}

	private function render_detail( $case_id ) {
		$case = PAS_Dashboard_Security::authorized_case( $case_id );

		if ( is_wp_error( $case ) ) {
			return $this->notice( 'error', $case->get_error_message() );
		}

		PAS_Dashboard_Audit_Log::record( 'case.view', 'case', $case_id, 'ok' );

		$crypto = PAS_Dashboard_Core_Bridge::crypto_ready();
		$can_write = PAS_Dashboard_Security::notes_writable();

		$html  = $this->notice( 'info', __( 'دسترسی شما به این پرونده در سمت سرور تأیید شد.', 'peyvand-amn-dashboard' ) );

		/* تب‌بندی: اطلاعات پایه از یادداشت‌ها جدا می‌شود تا صفحه یک اسکرول
		 * طولانی و غیرقابل‌اسکن نباشد. هر دو پنل در همان درخواست رندر
		 * می‌شوند؛ تب فقط نمایش را عوض می‌کند، نه مجوز را. */
		$html .= '<div class="pvn-case">';
		$html .= '<div class="pvn-case-tabs" role="tablist" aria-label="' . esc_attr__( 'بخش‌های پرونده', 'peyvand-amn-dashboard' ) . '">';
		$html .= '<button type="button" class="pvn-case-tab is-active" role="tab" id="pvn-ctab-summary"'
			. ' aria-controls="pvn-cpanel-summary" aria-selected="true" data-pvn-case-tab="summary">'
			. esc_html__( 'خلاصه', 'peyvand-amn-dashboard' ) . '</button>';
		$html .= '<button type="button" class="pvn-case-tab" role="tab" id="pvn-ctab-notes"'
			. ' aria-controls="pvn-cpanel-notes" aria-selected="false" data-pvn-case-tab="notes">'
			. esc_html__( 'یادداشت‌های جلسه', 'peyvand-amn-dashboard' ) . '</button>';
		$html .= '</div>';

		$html .= '<section class="pvn-cpanel" id="pvn-cpanel-summary" role="tabpanel"'
			. ' aria-labelledby="pvn-ctab-summary" data-pvn-case-panel="summary">';

		$html .= $this->card_open( __( 'مشخصات پرونده', 'peyvand-amn-dashboard' ) );
		$html .= '<div class="pas-grid">';
		$html .= '<div class="pas-stat"><span class="pas-stat-value">#' . esc_html( (string) $case_id ) . '</span><span class="pas-stat-label">' . esc_html__( 'شناسه', 'peyvand-amn-dashboard' ) . '</span></div>';
		$html .= '<div class="pas-stat"><span class="pas-stat-value">' . esc_html( (string) ( $case->status ?? '—' ) ) . '</span><span class="pas-stat-label">' . esc_html__( 'وضعیت', 'peyvand-amn-dashboard' ) . '</span></div>';
		$html .= '<div class="pas-stat"><span class="pas-stat-value">' . esc_html( PAS_Dashboard_Core_Bridge::therapist_name( (int) ( $case->assigned_therapist ?? 0 ) ) ) . '</span><span class="pas-stat-label">' . esc_html__( 'درمانگر', 'peyvand-amn-dashboard' ) . '</span></div>';
		$html .= '<div class="pas-stat"><span class="pas-stat-value">' . esc_html( PAS_Dashboard_Core_Bridge::jalali( (string) ( $case->updated_at ?? '' ) ) ) . '</span><span class="pas-stat-label">' . esc_html__( 'آخرین به‌روزرسانی', 'peyvand-amn-dashboard' ) . '</span></div>';
		$html .= '</div>';
		$html .= '<p class="pas-muted">' . esc_html__( 'برای ویرایش اطلاعات پایه‌ی پرونده از صفحه‌ی افزونه‌ی پرونده‌ها استفاده کنید؛ این داشبورد فقط یادداشت جلسه را مدیریت می‌کند.', 'peyvand-amn-dashboard' ) . '</p>';
		$html .= $this->card_close();

		/* نمای زوجی — فقط متادیتا، و فقط با مجوز پارتنر */
		$couple = PAS_Dashboard_Core_Bridge::couple_sessions( $case, 60 );

		$html .= $this->card_open( __( 'نمای جلسه‌های زوجی', 'peyvand-amn-dashboard' ) );

		if ( empty( $couple['has_partner'] ) ) {
			$html .= '<p class="pas-muted">' . esc_html__( 'برای این پرونده پارتنر ثبت نشده است.', 'peyvand-amn-dashboard' ) . '</p>';
		} elseif ( ! $couple['can_view_partner'] ) {
			$html .= '<p class="pas-empty">' . esc_html__( 'این پرونده پارتنر دارد، اما دسترسی شما به پرونده‌ی پارتنر تأیید نشده است. اطلاعات پارتنر نمایش داده نمی‌شود.', 'peyvand-amn-dashboard' ) . '</p>';
		} else {
			$html .= '<p><strong>' . esc_html__( 'پارتنر:', 'peyvand-amn-dashboard' ) . '</strong> #' . esc_html( (string) (int) ( $couple['partner']->id ?? 0 ) ) . '</p>';

			if ( ! empty( $couple['sessions'] ) ) {
				$html .= '<ul class="pas-timeline">';
				foreach ( $couple['sessions'] as $session ) {
					$side = ( $session['side'] ?? '' ) === 'partner'
						? __( 'پارتنر', 'peyvand-amn-dashboard' )
						: __( 'خود', 'peyvand-amn-dashboard' );

					$html .= '<li>';
					$html .= '<div class="pas-timeline-date">' . esc_html( PAS_Dashboard_Core_Bridge::jalali( (string) ( $session['date'] ?? '' ) ) ) . '</div>';
					$html .= '<div class="pas-timeline-meta">' . esc_html( sprintf(
						/* translators: 1: side, 2: session number, 3: status */
						__( '%1$s — جلسه %2$s — وضعیت %3$s', 'peyvand-amn-dashboard' ),
						$side,
						(string) (int) ( $session['number'] ?? 0 ),
						(string) ( $session['status'] ?? '' )
					) ) . '</div>';
					$html .= '</li>';
				}
				$html .= '</ul>';
			} else {
				$html .= '<p class="pas-muted">' . esc_html__( 'جلسه‌ای ثبت نشده است.', 'peyvand-amn-dashboard' ) . '</p>';
			}
		}

		$html .= $this->card_close();

		$html .= '</section>';

		$html .= '<section class="pvn-cpanel" id="pvn-cpanel-notes" role="tabpanel"'
			. ' aria-labelledby="pvn-ctab-notes" data-pvn-case-panel="notes" hidden>';

		/* یادداشت‌ها */
		$html .= $this->card_open( __( 'یادداشت‌های جلسه', 'peyvand-amn-dashboard' ) );

		if ( ! $crypto ) {
			$html .= $this->notice( 'error', __( 'کلید رمزنگاری تنظیم نشده است؛ یادداشت‌ها قابل نمایش یا ثبت نیستند.', 'peyvand-amn-dashboard' ) );
		} else {
			$notes = PAS_Dashboard_Core_Bridge::notes( $case_id );

			if ( empty( $notes ) ) {
				$html .= '<p class="pas-empty">' . esc_html__( 'یادداشتی ثبت نشده است.', 'peyvand-amn-dashboard' ) . '</p>';
			} else {
				$html .= '<div class="pas-note-list">';

				foreach ( array_slice( $notes, 0, 50 ) as $note ) {
					$note_id  = (int) ( $note->id ?? 0 );
					$content  = (string) ( $note->content ?? '' );
					$history  = PAS_Dashboard_Core_Bridge::note_history( $note_id );

					$html .= '<article class="pas-note">';
					$html .= '<h3 class="pas-note-title">' . esc_html( sprintf(
						/* translators: 1: date, 2: session number */
						__( 'جلسه‌ی %1$s — شماره %2$s', 'peyvand-amn-dashboard' ),
						PAS_Dashboard_Core_Bridge::jalali( (string) ( $note->session_date ?? '' ) ),
						(string) (int) ( $note->session_number ?? 0 )
					) ) . '</h3>';
					$html .= '<div class="pas-note-body">' . nl2br( esc_html( $content ) ) . '</div>';
					$html .= '<p class="pas-timeline-meta">' . esc_html( sprintf(
						/* translators: 1: author, 2: status */
						__( 'نویسنده: %1$s — وضعیت: %2$s', 'peyvand-amn-dashboard' ),
						PAS_Dashboard_Core_Bridge::therapist_name( (int) ( $note->author_id ?? 0 ) ),
						(string) ( $note->status ?? 'done' )
					) ) . '</p>';

					if ( $can_write && $note_id > 0 ) {
						$html .= '<div class="pas-note-actions">';
						$html .= '<button type="button" class="pas-btn" data-pas-edit-note="1"'
							. ' data-note-id="' . esc_attr( (string) $note_id ) . '"'
							. ' data-session-date="' . esc_attr( PAS_Dashboard_Core_Bridge::jalali( (string) ( $note->session_date ?? '' ) ) ) . '"'
							. ' data-session-number="' . esc_attr( (string) (int) ( $note->session_number ?? 0 ) ) . '">'
							. esc_html__( 'ویرایش', 'peyvand-amn-dashboard' ) . '</button>';
						$html .= '<button type="button" class="pas-btn pas-btn-danger" data-pas-delete-note="1" data-case-id="' . esc_attr( (string) $case_id ) . '" data-note-id="' . esc_attr( (string) $note_id ) . '">' . esc_html__( 'حذف یادداشت', 'peyvand-amn-dashboard' ) . '</button>';
						$html .= '</div>';
					}

					if ( ! empty( $history ) ) {
						$html .= '<details class="pas-note-history"><summary>' . esc_html__( 'تاریخچه‌ی نسخه‌ها', 'peyvand-amn-dashboard' ) . '</summary><ul class="pas-list">';
						foreach ( $history as $snapshot ) {
							$html .= '<li>' . esc_html( sprintf(
								/* translators: 1: action, 2: date */
								__( '%1$s — %2$s', 'peyvand-amn-dashboard' ),
								(string) ( $snapshot->action ?? '' ),
								PAS_Dashboard_Core_Bridge::jalali( (string) ( $snapshot->changed_at ?? '' ) )
							) );
							if ( $can_write ) {
								$html .= ' <button type="button" class="pas-btn" data-pas-restore-note="1" data-case-id="' . esc_attr( (string) $case_id ) . '" data-history-id="' . esc_attr( (string) (int) $snapshot->id ) . '" data-label="' . esc_attr__( 'بازگردانی این نسخه', 'peyvand-amn-dashboard' ) . '">' . esc_html__( 'بازگردانی', 'peyvand-amn-dashboard' ) . '</button>';
							}
							$html .= '</li>';
						}
						$html .= '</ul></details>';
					}

					$html .= '</article>';
				}

				$html .= '</div>';
			}

			if ( $can_write ) {
				$html .= '<hr />';
				$html .= '<form id="pas-note-form" class="pas-note-form">';
				$html .= '<h3 class="pas-note-form-title">' . esc_html__( 'افزودن یادداشت جدید', 'peyvand-amn-dashboard' ) . '</h3>';
				$html .= '<input type="hidden" name="case_id" value="' . esc_attr( (string) $case_id ) . '" />';
				$html .= '<input type="hidden" name="note_id" value="0" />';
				$html .= '<div class="pas-form-row"><label for="pas-session-date">' . esc_html__( 'تاریخ جلسه (شمسی)', 'peyvand-amn-dashboard' ) . '</label>';
				$html .= '<input class="pas-input" type="text" id="pas-session-date" name="session_date" dir="ltr" inputmode="numeric"'
					. ' placeholder="1405/08/27" autocomplete="off" required'
					. ' value="' . esc_attr( PAS_Dashboard_Core_Bridge::today_jalali() ) . '"'
					. ' data-default="' . esc_attr( PAS_Dashboard_Core_Bridge::today_jalali() ) . '" />';
				$html .= '<p class="pas-muted pas-field-hint">' . esc_html__( 'مثال: ۱۴۰۵/۰۸/۲۷ — ارقام فارسی هم پذیرفته می‌شود.', 'peyvand-amn-dashboard' ) . '</p></div>';
				$html .= '<div class="pas-form-row"><label for="pas-session-number">' . esc_html__( 'شماره‌ی جلسه', 'peyvand-amn-dashboard' ) . '</label>';
				$html .= '<input class="pas-input" type="number" id="pas-session-number" name="session_number" min="0" max="999" value="0" /></div>';
				$html .= '<div class="pas-form-row"><label for="pas-note-content">' . esc_html__( 'متن یادداشت', 'peyvand-amn-dashboard' ) . '</label>';
				$html .= '<textarea class="pas-textarea" id="pas-note-content" name="content" required></textarea></div>';
				$html .= '<div class="pas-note-form-actions">';
				$html .= '<button class="pas-btn pas-btn-primary" type="submit">' . esc_html__( 'ثبت یادداشت', 'peyvand-amn-dashboard' ) . '</button>';
				$html .= '<button class="pas-btn" type="button" data-pas-cancel-edit hidden>' . esc_html__( 'لغو ویرایش', 'peyvand-amn-dashboard' ) . '</button>';
				$html .= '</div>';
				$html .= '</form>';
			} else {
				$html .= $this->notice( 'info', __( 'ثبت یادداشت برای نقش شما یا در وضعیت فعلی رمزنگاری مجاز نیست.', 'peyvand-amn-dashboard' ) );
			}
		}

		$html .= $this->card_close();
		$html .= '</section>';
		$html .= '</div>'; // .pvn-case

		$html .= '<p><a class="pas-btn" href="' . esc_url( $this->url() ) . '">' . esc_html__( 'بازگشت به فهرست پرونده‌ها', 'peyvand-amn-dashboard' ) . '</a></p>';

		return $html;
	}
}
