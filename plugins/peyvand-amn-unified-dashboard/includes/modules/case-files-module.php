<?php
/**
 * ماژول ۳ — پرونده‌ها، یادداشت‌های جلسه، فرم ارزیابی و یادآور.
 *
 * هر عملیات: احراز هویت → کپابیلیتی بالینی → مجوز پرونده → API مالک.
 * نوشتن فقط از طریق Action Bus انجام می‌شود.
 */

defined( 'ABSPATH' ) || exit;

final class PAS_Dashboard_Module_Case_Files extends PAS_Dashboard_Module {

	public function __construct() {
		$this->id          = 'case_files';
		$this->title       = __( 'پرونده‌های بالینی', 'peyvand-amn-dashboard' );
		$this->description = __( 'فقط پرونده‌های تخصیص‌داده‌شده به شما؛ ثبت، ویرایش، فرم ارزیابی و یادآور در همین‌جا.', 'peyvand-amn-dashboard' );
	}

	public function render() {
		if ( ! PAS_Dashboard_Core_Bridge::has_case_file() ) {
			return $this->unavailable( __( 'افزونه‌ی پرونده‌های بالینی فعال نیست.', 'peyvand-amn-dashboard' ) );
		}

		if ( isset( $_GET['new'] ) ) {
			return $this->render_detail( 0 );
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
			$html .= $this->notice( 'warning', __( 'کلید رمزنگاری تنظیم نشده است؛ یادداشت‌ها نمایش داده نمی‌شوند و ثبت/ویرایش پرونده غیرفعال است.', 'peyvand-amn-dashboard' ) );
		}

		$html .= $this->card_open( __( 'پرونده‌های شما', 'peyvand-amn-dashboard' ) );

		$html .= '<p class="pas-actions"><a class="pas-btn pas-btn-primary" href="'
			. esc_url( $this->url( array( 'new' => 1 ) ) ) . '">'
			. esc_html__( 'پروندهٔ جدید', 'peyvand-amn-dashboard' ) . '</a></p>';

		if ( empty( $cases ) ) {
			$html .= '<p class="pas-empty">' . esc_html__( 'پرونده‌ای به شما تخصیص داده نشده است.', 'peyvand-amn-dashboard' ) . '</p>';
			$html .= $this->card_close();
			return $html;
		}

		$ids = array();
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
		$is_new = 0 === (int) $case_id;
		$case   = null;

		if ( $is_new ) {
			if ( ! PAS_Dashboard_Security::can_view_clinical() ) {
				return $this->notice( 'error', __( 'دسترسی به داده‌های بالینی مجاز نیست.', 'peyvand-amn-dashboard' ) );
			}
			if ( ! PAS_Dashboard_Core_Bridge::crypto_ready() ) {
				return $this->notice( 'error', __( 'برای ثبت پرونده، ابتدا کلید رمزنگاری (PAS_CORE_ENCRYPTION_KEY) را تنظیم کنید.', 'peyvand-amn-dashboard' ) );
			}
		} else {
			$case = PAS_Dashboard_Security::authorized_case( $case_id );

			if ( is_wp_error( $case ) ) {
				return $this->notice( 'error', $case->get_error_message() );
			}

			PAS_Dashboard_Audit_Log::record( 'case.view', 'case', $case_id, 'ok' );
		}

		$crypto    = PAS_Dashboard_Core_Bridge::crypto_ready();
		$can_write = $crypto && PAS_Dashboard_Security::can_view_clinical();
		$can_assign = PAS_Dashboard_Core_Bridge::viewer_can_see_all_cases();

		$couple = $case ? PAS_Dashboard_Core_Bridge::couple_sessions( $case, 60 ) : array(
			'partner'          => null,
			'has_partner'      => false,
			'can_view_partner' => false,
			'sessions'         => array(),
		);

		$partner_phone  = '';
		$partner_edit   = $is_new || $can_assign || empty( $couple['has_partner'] ) || ! empty( $couple['can_view_partner'] );

		if ( $case && $partner_edit && ! empty( $couple['partner'] ) ) {
			$partner_phone = (string) ( $couple['partner']->phone ?? '' );
		}

		$html  = $this->notice( 'info', $is_new
			? __( 'پروندهٔ جدید: شمارهٔ موبایل کلید اتصال به حساب مراجع است و بعد از ثبت قابل تغییر نیست.', 'peyvand-amn-dashboard' )
			: __( 'دسترسی شما به این پرونده در سمت سرور تأیید شد.', 'peyvand-amn-dashboard' ) );

		$tabs = array(
			'summary'  => __( 'خلاصه و مشخصات', 'peyvand-amn-dashboard' ),
			'notes'    => __( 'یادداشت‌های جلسه', 'peyvand-amn-dashboard' ),
			'intake'   => __( 'فرم ارزیابی', 'peyvand-amn-dashboard' ),
			'reminder' => __( 'یادآور', 'peyvand-amn-dashboard' ),
		);

		$html .= '<div class="pvn-case">';
		$html .= '<div class="pvn-case-tabs" role="tablist" aria-label="' . esc_attr__( 'بخش‌های پرونده', 'peyvand-amn-dashboard' ) . '">';

		$first = true;
		foreach ( $tabs as $tab_id => $label ) {
			$html .= '<button type="button" class="pvn-case-tab' . ( $first ? ' is-active' : '' ) . '" role="tab"'
				. ' id="pvn-ctab-' . esc_attr( $tab_id ) . '"'
				. ' aria-controls="pvn-cpanel-' . esc_attr( $tab_id ) . '"'
				. ' aria-selected="' . ( $first ? 'true' : 'false' ) . '"'
				. ' data-pvn-case-tab="' . esc_attr( $tab_id ) . '">'
				. esc_html( $label ) . '</button>';
			$first = false;
		}

		$html .= '</div>';

		/* ── خلاصه و مشخصات ─────────────────────────────────────────── */
		$html .= '<section class="pvn-cpanel" id="pvn-cpanel-summary" role="tabpanel" aria-labelledby="pvn-ctab-summary" data-pvn-case-panel="summary">';

		if ( ! $is_new ) {
			$html .= $this->card_open( __( 'مشخصات پرونده', 'peyvand-amn-dashboard' ) );
			$html .= '<div class="pas-grid">';
			$html .= '<div class="pas-stat"><span class="pas-stat-value">#' . esc_html( (string) $case_id ) . '</span><span class="pas-stat-label">' . esc_html__( 'شناسه', 'peyvand-amn-dashboard' ) . '</span></div>';
			$html .= '<div class="pas-stat"><span class="pas-stat-value">' . esc_html( (string) ( $case->status ?? '—' ) ) . '</span><span class="pas-stat-label">' . esc_html__( 'وضعیت', 'peyvand-amn-dashboard' ) . '</span></div>';
			$html .= '<div class="pas-stat"><span class="pas-stat-value">' . esc_html( PAS_Dashboard_Core_Bridge::therapist_name( (int) ( $case->assigned_therapist ?? 0 ) ) ) . '</span><span class="pas-stat-label">' . esc_html__( 'درمانگر', 'peyvand-amn-dashboard' ) . '</span></div>';
			$html .= '<div class="pas-stat"><span class="pas-stat-value">' . esc_html( PAS_Dashboard_Core_Bridge::jalali( (string) ( $case->updated_at ?? '' ) ) ) . '</span><span class="pas-stat-label">' . esc_html__( 'آخرین به‌روزرسانی', 'peyvand-amn-dashboard' ) . '</span></div>';
			$html .= '</div>';
			$html .= $this->card_close();
		}

		$html .= $this->card_open( $is_new ? __( 'ثبت پروندهٔ جدید', 'peyvand-amn-dashboard' ) : __( 'ویرایش مشخصات', 'peyvand-amn-dashboard' ) );

		if ( ! $can_write ) {
			$html .= $this->notice( 'warning', __( 'ثبت یا ویرایش پرونده برای نقش شما یا در وضعیت فعلی رمزنگاری مجاز نیست.', 'peyvand-amn-dashboard' ) );
		} else {
			$html .= $this->render_case_form( $case, $can_assign, $partner_phone, $partner_edit );
		}

		$html .= $this->card_close();

		/* نمای زوجی */
		if ( $case ) {
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
						$side = ( $session['side'] ?? '' ) === 'partner' ? __( 'پارتنر', 'peyvand-amn-dashboard' ) : __( 'خود', 'peyvand-amn-dashboard' );

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
		}

		$html .= '</section>';

		/* ── یادداشت‌ها ──────────────────────────────────────────────── */
		$html .= '<section class="pvn-cpanel" id="pvn-cpanel-notes" role="tabpanel" aria-labelledby="pvn-ctab-notes" data-pvn-case-panel="notes" hidden>';
		$html .= $this->render_notes( $case_id, $is_new, $crypto );
		$html .= '</section>';

		/* ── فرم ارزیابی ─────────────────────────────────────────────── */
		$html .= '<section class="pvn-cpanel" id="pvn-cpanel-intake" role="tabpanel" aria-labelledby="pvn-ctab-intake" data-pvn-case-panel="intake" hidden>';
		$html .= $this->render_intake( $case );
		$html .= '</section>';

		/* ── یادآور ─────────────────────────────────────────────────── */
		$html .= '<section class="pvn-cpanel" id="pvn-cpanel-reminder" role="tabpanel" aria-labelledby="pvn-ctab-reminder" data-pvn-case-panel="reminder" hidden>';
		$html .= $this->render_reminder( $case );
		$html .= '</section>';

		$html .= '</div>'; // .pvn-case

		$html .= '<p><a class="pas-btn" href="' . esc_url( $this->url() ) . '">' . esc_html__( 'بازگشت به فهرست پرونده‌ها', 'peyvand-amn-dashboard' ) . '</a></p>';

		return $html;
	}

	private function render_case_form( $case, $can_assign, $partner_phone, $partner_edit ) {
		$redirect = $case
			? $this->url( array( 'case_id' => (int) $case->id ) )
			: $this->url( array( 'new' => 1 ) );

		$html  = '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="pas-form">';
		$html .= PAS_Dashboard_Actions::fields( 'case.save', $redirect );
		$html .= '<input type="hidden" name="case_id" value="' . esc_attr( (string) ( $case ? (int) $case->id : 0 ) ) . '" />';

		$html .= '<div class="pas-form-grid">';

		$html .= $this->input_row( 'full_name', __( 'نام و نام خانوادگی', 'peyvand-amn-dashboard' ), $case );
		$html .= 0 === (int) ( $case->id ?? 0 )
			? $this->input_row( 'phone', __( 'موبایل *', 'peyvand-amn-dashboard' ), $case, 'text', 'dir="ltr" placeholder="09123456789" required' )
			: '<div class="pas-form-row"><label>' . esc_html__( 'موبایل', 'peyvand-amn-dashboard' ) . '</label><p class="pas-readonly" dir="ltr">' . esc_html( (string) ( $case->phone ?? '—' ) ) . '</p><p class="pas-field-hint">' . esc_html__( 'کلید اتصال حساب مراجع؛ قابل تغییر نیست.', 'peyvand-amn-dashboard' ) . '</p></div>';

		$html .= $this->input_row( 'age', __( 'سن', 'peyvand-amn-dashboard' ), $case );
		$html .= $this->input_row( 'marital_status', __( 'وضعیت تأهل', 'peyvand-amn-dashboard' ), $case );
		$html .= $this->input_row( 'education', __( 'تحصیلات', 'peyvand-amn-dashboard' ), $case );
		$html .= $this->input_row( 'occupation', __( 'شغل', 'peyvand-amn-dashboard' ), $case );
		$html .= $this->input_row( 'residence', __( 'محل سکونت', 'peyvand-amn-dashboard' ), $case );
		$html .= $this->input_row( 'marriage_type', __( 'نوع ازدواج', 'peyvand-amn-dashboard' ), $case );
		$html .= $this->input_row( 'children_count', __( 'تعداد فرزندان', 'peyvand-amn-dashboard' ), $case );
		$html .= $this->input_row( 'topic', __( 'موضوع کاری', 'peyvand-amn-dashboard' ), $case );

		$html .= $this->select_row(
			'status',
			__( 'وضعیت پرونده', 'peyvand-amn-dashboard' ),
			(string) ( $case->status ?? 'فعال' ),
			array_combine( PAS_Dashboard_Adapter_Case_File::statuses(), PAS_Dashboard_Adapter_Case_File::statuses() )
		);

		if ( $can_assign ) {
			$therapists = get_users( array( 'capability' => array( defined( 'PAS_CF_CAP' ) ? PAS_CF_CAP : 'manage_options' ), 'orderby' => 'display_name' ) );
			$options    = array( '0' => __( '— تخصیص‌نیافته —', 'peyvand-amn-dashboard' ) );

			foreach ( $therapists as $therapist ) {
				$options[ (string) $therapist->ID ] = (string) $therapist->display_name;
			}

			$html .= $this->select_row( 'assigned_therapist', __( 'درمانگر مسئول', 'peyvand-amn-dashboard' ), (string) (int) ( $case->assigned_therapist ?? 0 ), $options );
		}

		$html .= $this->textarea_row( 'main_conflict_topics', __( 'موضوعات اصلی تعارض', 'peyvand-amn-dashboard' ), $case, 2, true );

		if ( $partner_edit ) {
			$html .= '<div class="pas-form-row"><label for="pas-partner-phone">' . esc_html__( 'موبایل پارتنر (اختیاری)', 'peyvand-amn-dashboard' ) . '</label>';
			$html .= '<input class="pas-input" type="text" id="pas-partner-phone" name="partner_phone" dir="ltr" value="' . esc_attr( $partner_phone ) . '" placeholder="09123456789" />';
			$html .= '<p class="pas-field-hint">' . esc_html__( 'اگر پرونده‌ای با این شماره وجود داشته باشد، دوطرفه لینک می‌شود؛ خالی‌گذاشتن یعنی قطع لینک.', 'peyvand-amn-dashboard' ) . '</p></div>';
		}

		$html .= $this->select_row(
			'reminder_channel',
			__( 'کانال یادآور', 'peyvand-amn-dashboard' ),
			(string) ( $case->reminder_channel ?? 'sms' ),
			self::reminder_channels()
		);

		$html .= '<div class="pas-form-row pas-form-row--full"><label class="pas-check">'
			. '<input type="checkbox" name="reminder_consent" value="1" ' . checked( ! empty( $case->reminder_consent ), true, false ) . ' />'
			. esc_html__( 'مراجع با دریافت یادآور جلسه موافق است', 'peyvand-amn-dashboard' )
			. '</label></div>';

		$html .= '</div>'; // grid

		$html .= '<details class="pas-form-section"><summary>' . esc_html__( 'سوابق و علائم بالینی (رمزنگاری‌شده)', 'peyvand-amn-dashboard' ) . '</summary><div class="pas-form-grid">';
		$html .= $this->textarea_row( 'medical_history', __( 'سابقهٔ بیماری خاص یا مصرف دارو', 'peyvand-amn-dashboard' ), $case, 3 );
		$html .= $this->textarea_row( 'counseling_history', __( 'سابقهٔ مشاوره یا روان‌پزشک', 'peyvand-amn-dashboard' ), $case, 3 );
		$html .= $this->textarea_row( 'self_harm_history', __( 'سابقهٔ خودکشی یا خودآسیب‌رسانی', 'peyvand-amn-dashboard' ), $case, 3 );
		$html .= $this->textarea_row( 'substance_use_history', __( 'سوءمصرف مواد', 'peyvand-amn-dashboard' ), $case, 2 );
		$html .= $this->textarea_row( 'symptom_mood_depression', __( 'خلق و افسردگی (دو هفتهٔ اخیر)', 'peyvand-amn-dashboard' ), $case, 2 );
		$html .= $this->textarea_row( 'symptom_anxiety', __( 'اضطراب (دو هفتهٔ اخیر)', 'peyvand-amn-dashboard' ), $case, 2 );
		$html .= $this->textarea_row( 'q_schema_attachment', __( 'ارزیابی طرحواره و سبک دلبستگی', 'peyvand-amn-dashboard' ), $case, 2 );
		$html .= '</div></details>';

		$html .= '<details class="pas-form-section"><summary>' . esc_html__( 'اطلاعات همسر', 'peyvand-amn-dashboard' ) . '</summary><div class="pas-form-grid">';
		$html .= $this->input_row( 'spouse_full_name', __( 'نام همسر', 'peyvand-amn-dashboard' ), $case );
		$html .= $this->input_row( 'spouse_age', __( 'سن همسر', 'peyvand-amn-dashboard' ), $case );
		$html .= $this->input_row( 'spouse_education', __( 'تحصیلات همسر', 'peyvand-amn-dashboard' ), $case );
		$html .= $this->input_row( 'spouse_occupation', __( 'شغل همسر', 'peyvand-amn-dashboard' ), $case );
		$html .= $this->input_row( 'spouse_phone', __( 'موبایل همسر', 'peyvand-amn-dashboard' ), $case, 'text', 'dir="ltr" placeholder="09123456789"' );
		$html .= $this->textarea_row( 'spouse_failed_marriage_history', __( 'سابقهٔ ازدواج ناموفق همسر', 'peyvand-amn-dashboard' ), $case, 2, true );
		$html .= $this->textarea_row( 'spouse_medical_history', __( 'سابقهٔ بیماری/داروی همسر', 'peyvand-amn-dashboard' ), $case, 2 );
		$html .= $this->textarea_row( 'spouse_counseling_history', __( 'سابقهٔ مشاورهٔ همسر', 'peyvand-amn-dashboard' ), $case, 2 );
		$html .= $this->textarea_row( 'spouse_self_harm_history', __( 'سابقهٔ خودآسیب‌رسانی همسر', 'peyvand-amn-dashboard' ), $case, 2 );
		$html .= $this->textarea_row( 'spouse_substance_use_history', __( 'سوءمصرف مواد همسر', 'peyvand-amn-dashboard' ), $case, 2 );
		$html .= '</div></details>';

		$html .= '<details class="pas-form-section"><summary>' . esc_html__( 'سؤالات و اهداف درمان', 'peyvand-amn-dashboard' ) . '</summary><div class="pas-form-grid">';
		$html .= $this->textarea_row( 'q_main_reason', __( '۱. علت اصلی مراجعه', 'peyvand-amn-dashboard' ), $case, 2, true );
		$html .= $this->textarea_row( 'q_positive_negative_traits', __( '۲. نکات مثبت و منفی یکدیگر', 'peyvand-amn-dashboard' ), $case, 2, true );
		$html .= $this->textarea_row( 'q_conflict_resolution', __( '۳. شیوهٔ حل تعارض', 'peyvand-amn-dashboard' ), $case, 2, true );
		$html .= $this->textarea_row( 'q_reason_staying', __( '۴. دلیل ماندن در رابطه', 'peyvand-amn-dashboard' ), $case, 2, true );
		$html .= $this->textarea_row( 'q_therapy_goals', __( '۵. اهداف مراجعه', 'peyvand-amn-dashboard' ), $case, 2, true );
		$html .= $this->input_row( 'relationship_satisfaction_pre', __( 'نمرهٔ رابطه — قبل از درمان', 'peyvand-amn-dashboard' ), $case );
		$html .= $this->input_row( 'relationship_satisfaction_post', __( 'نمرهٔ رابطه — پس از درمان', 'peyvand-amn-dashboard' ), $case );
		$html .= $this->textarea_row( 'prior_therapy', __( 'سابقهٔ درمان قبلی', 'peyvand-amn-dashboard' ), $case, 2, true );
		$html .= $this->textarea_row( 'failed_marriage_history', __( 'سابقهٔ ازدواج ناموفق', 'peyvand-amn-dashboard' ), $case, 2, true );
		$html .= '</div></details>';

		$html .= '<p class="pas-note-form-actions"><button class="pas-btn pas-btn-primary" type="submit">'
			. esc_html( $case ? __( 'ذخیرهٔ تغییرات', 'peyvand-amn-dashboard' ) : __( 'ثبت پرونده', 'peyvand-amn-dashboard' ) )
			. '</button></p>';
		$html .= '</form>';

		return $html;
	}

	private function render_notes( $case_id, $is_new, $crypto ) {
		$html = $this->card_open( __( 'یادداشت‌های جلسه', 'peyvand-amn-dashboard' ) );

		if ( $is_new ) {
			$html .= '<p class="pas-empty">' . esc_html__( 'پس از ثبت پرونده می‌توانید یادداشت جلسه اضافه کنید.', 'peyvand-amn-dashboard' ) . '</p>';
			$html .= $this->card_close();
			return $html;
		}

		if ( ! $crypto ) {
			$html .= $this->notice( 'error', __( 'کلید رمزنگاری تنظیم نشده است؛ یادداشت‌ها قابل نمایش یا ثبت نیستند.', 'peyvand-amn-dashboard' ) );
			$html .= $this->card_close();
			return $html;
		}

		$can_write = PAS_Dashboard_Security::notes_writable();
		$notes     = PAS_Dashboard_Core_Bridge::notes( $case_id );

		if ( empty( $notes ) ) {
			$html .= '<p class="pas-empty">' . esc_html__( 'یادداشتی ثبت نشده است.', 'peyvand-amn-dashboard' ) . '</p>';
		} else {
			$html .= '<div class="pas-note-list">';

			foreach ( array_slice( $notes, 0, 50 ) as $note ) {
				$note_id = (int) ( $note->id ?? 0 );
				$content = (string) ( $note->content ?? '' );
				$history = PAS_Dashboard_Core_Bridge::note_history( $note_id );

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
			$html .= '<input class="pas-input" type="text" id="pas-session-date" name="session_date" dir="ltr" inputmode="numeric" placeholder="1405/08/27" autocomplete="off" required value="' . esc_attr( PAS_Dashboard_Core_Bridge::today_jalali() ) . '" data-default="' . esc_attr( PAS_Dashboard_Core_Bridge::today_jalali() ) . '" />';
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

		$html .= $this->card_close();

		return $html;
	}

	private function render_intake( $case ) {
		$html = $this->card_open( __( 'فرم ارزیابی مراجع', 'peyvand-amn-dashboard' ) );

		if ( ! $case ) {
			$html .= '<p class="pas-empty">' . esc_html__( 'ابتدا پرونده را ثبت کنید تا فرم ارزیابی مراجع بررسی شود.', 'peyvand-amn-dashboard' ) . '</p>';
			$html .= $this->card_close();
			return $html;
		}

		$preview = PAS_Dashboard_Adapter_Case_File::intake_preview( $case );

		if ( is_wp_error( $preview ) ) {
			$html .= $this->notice( 'info', $preview->get_error_message() );
			$html .= $this->card_close();
			return $html;
		}

		$rows    = $preview['rows'];
		$applied = array_filter( $rows, static function ( $row ) {
			return in_array( (string) ( $row['status'] ?? '' ), array( 'fill', 'conflict' ), true );
		} );

		$html .= '<p class="pas-muted">' . esc_html__( 'پیش‌نمایش نگاشت فرم ارزیابی مراجع روی پرونده؛ فقط فیلدهای خالی پر می‌شوند مگر گزینهٔ بازنویسی را بزنید.', 'peyvand-amn-dashboard' ) . '</p>';

		if ( empty( $rows ) ) {
			$html .= '<p class="pas-empty">' . esc_html__( 'فیلد قابل نگاشتی پیدا نشد.', 'peyvand-amn-dashboard' ) . '</p>';
			$html .= $this->card_close();
			return $html;
		}

		$status_labels = array(
			'fill'     => __( 'پر می‌شود', 'peyvand-amn-dashboard' ),
			'same'     => __( 'یکسان (رد می‌شود)', 'peyvand-amn-dashboard' ),
			'conflict' => __( 'اختلاف با مقدار فعلی', 'peyvand-amn-dashboard' ),
			'invalid'  => __( 'نامعتبر', 'peyvand-amn-dashboard' ),
		);

		$html .= '<div class="pas-table-wrap"><table class="pas-table"><thead><tr>';
		$html .= '<th>' . esc_html__( 'فیلد', 'peyvand-amn-dashboard' ) . '</th>';
		$html .= '<th>' . esc_html__( 'مقدار فعلی', 'peyvand-amn-dashboard' ) . '</th>';
		$html .= '<th>' . esc_html__( 'مقدار جدید', 'peyvand-amn-dashboard' ) . '</th>';
		$html .= '<th>' . esc_html__( 'نتیجه', 'peyvand-amn-dashboard' ) . '</th>';
		$html .= '</tr></thead><tbody>';

		foreach ( $rows as $row ) {
			$clinical = (string) ( $row['sensitivity'] ?? '' ) === 'clinical';
			$status   = (string) ( $row['status'] ?? '' );
			$current  = (string) ( $row['current_display'] ?? $row['current'] ?? '' );
			$value    = (string) ( $row['value'] ?? '' );

			$html .= '<tr>';
			$html .= '<td data-label="' . esc_attr__( 'فیلد', 'peyvand-amn-dashboard' ) . '"><code>' . esc_html( (string) ( $row['target'] ?? '' ) ) . '</code></td>';
			$html .= '<td data-label="' . esc_attr__( 'مقدار فعلی', 'peyvand-amn-dashboard' ) . '">' . esc_html( $clinical && $current !== '' ? '••••' : ( mb_strimwidth( $current, 0, 60, '…' ) ) ) . '</td>';
			$html .= '<td data-label="' . esc_attr__( 'مقدار جدید', 'peyvand-amn-dashboard' ) . '">' . esc_html( $clinical ? '••••' : mb_strimwidth( $value, 0, 60, '…' ) ) . '</td>';
			$html .= '<td data-label="' . esc_attr__( 'نتیجه', 'peyvand-amn-dashboard' ) . '"><span class="pas-badge ' . ( $status === 'fill' ? 'pas-badge-good' : ( $status === 'conflict' ? 'pas-badge-warn' : ( $status === 'invalid' ? 'pas-badge-danger' : '' ) ) ) . '">' . esc_html( $status_labels[ $status ] ?? $status ) . '</span></td>';
			$html .= '</tr>';
		}

		$html .= '</tbody></table></div>';

		if ( ! empty( $applied ) ) {
			$html .= '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="pas-form pas-inline-form">';
			$html .= PAS_Dashboard_Actions::fields( 'case.intake', $this->url( array( 'case_id' => (int) $case->id ) ) );
			$html .= '<input type="hidden" name="case_id" value="' . esc_attr( (string) (int) $case->id ) . '" />';
			$html .= '<label class="pas-check"><input type="checkbox" name="overwrite" value="1" /> ' . esc_html__( 'مقادیر دارای اختلاف هم بازنویسی شوند', 'peyvand-amn-dashboard' ) . '</label>';
			$html .= '<button class="pas-btn pas-btn-primary" type="submit">' . esc_html__( 'اعمال روی پرونده', 'peyvand-amn-dashboard' ) . '</button>';
			$html .= '</form>';
		}

		$html .= $this->card_close();

		return $html;
	}

	private function render_reminder( $case ) {
		$html = $this->card_open( __( 'یادآور جلسه', 'peyvand-amn-dashboard' ) );

		if ( ! $case ) {
			$html .= '<p class="pas-empty">' . esc_html__( 'ابتدا پرونده را ثبت کنید.', 'peyvand-amn-dashboard' ) . '</p>';
			$html .= $this->card_close();
			return $html;
		}

		$next    = PAS_Dashboard_Core_Bridge::next_session( $case );
		$blocker = PAS_Dashboard_Core_Bridge::reminder_blocker( $case, $next );

		$html .= '<div class="pas-grid">';
		$html .= '<div class="pas-stat"><span class="pas-stat-value">' . esc_html( $next && ! empty( $next->next_session_date ) ? PAS_Dashboard_Core_Bridge::jalali( (string) $next->next_session_date ) : '—' ) . '</span><span class="pas-stat-label">' . esc_html__( 'جلسهٔ بعدی', 'peyvand-amn-dashboard' ) . '</span></div>';
		$html .= '<div class="pas-stat"><span class="pas-stat-value">' . esc_html( ! empty( $case->reminder_consent ) ? __( 'دارد', 'peyvand-amn-dashboard' ) : __( 'ندارد', 'peyvand-amn-dashboard' ) ) . '</span><span class="pas-stat-label">' . esc_html__( 'رضایت یادآور', 'peyvand-amn-dashboard' ) . '</span></div>';
		$html .= '<div class="pas-stat"><span class="pas-stat-value">' . esc_html( self::reminder_channels()[ (string) ( $case->reminder_channel ?? 'sms' ) ] ?? '—' ) . '</span><span class="pas-stat-label">' . esc_html__( 'کانال', 'peyvand-amn-dashboard' ) . '</span></div>';
		$html .= '<div class="pas-stat"><span class="pas-stat-value">' . esc_html( PAS_Dashboard_Adapter_Case_File::blocker_label( $blocker ) ) . '</span><span class="pas-stat-label">' . esc_html__( 'وضعیت', 'peyvand-amn-dashboard' ) . '</span></div>';
		$html .= '</div>';

		if ( ! empty( $case->reminder_last_sent_at ) ) {
			$html .= '<p class="pas-muted">' . esc_html( sprintf(
				/* translators: %s: date */
				__( 'آخرین یادآور ارسال‌شده: %s', 'peyvand-amn-dashboard' ),
				PAS_Dashboard_Core_Bridge::jalali_datetime( (string) $case->reminder_last_sent_at )
			) ) . '</p>';
		}

		if ( 'ok' === $blocker ) {
			$html .= '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="pas-form pas-inline-form">';
			$html .= PAS_Dashboard_Actions::fields( 'case.reminder', $this->url( array( 'case_id' => (int) $case->id ) ) );
			$html .= '<input type="hidden" name="case_id" value="' . esc_attr( (string) (int) $case->id ) . '" />';
			$html .= '<button class="pas-btn pas-btn-primary" type="submit" data-pas-confirm="' . esc_attr__( 'یادآور برای مراجع ارسال شود؟', 'peyvand-amn-dashboard' ) . '">' . esc_html__( 'ارسال یادآور', 'peyvand-amn-dashboard' ) . '</button>';
			$html .= '<span class="pas-muted">' . esc_html__( 'ارسال واقعی از طریق پل پیامک یا SMTP انجام می‌شود و در لاگ ثبت می‌گردد.', 'peyvand-amn-dashboard' ) . '</span>';
			$html .= '</form>';
		} else {
			$html .= $this->notice( 'warning', PAS_Dashboard_Adapter_Case_File::blocker_label( $blocker ) );
		}

		$html .= $this->card_close();

		return $html;
	}

	private function input_row( $name, $label, $case, $type = 'text', $attrs = '' ) {
		$value = $case && isset( $case->{$name} ) ? (string) $case->{$name} : '';

		return '<div class="pas-form-row"><label for="pas-' . esc_attr( $name ) . '">' . esc_html( $label ) . '</label>'
			. '<input class="pas-input" type="' . esc_attr( $type ) . '" id="pas-' . esc_attr( $name ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '" ' . $attrs . ' /></div>';
	}

	private function textarea_row( $name, $label, $case, $rows = 2, $full = false ) {
		$value = $case && isset( $case->{$name} ) ? (string) $case->{$name} : '';

		return '<div class="pas-form-row' . ( $full ? ' pas-form-row--full' : '' ) . '"><label for="pas-' . esc_attr( $name ) . '">' . esc_html( $label ) . '</label>'
			. '<textarea class="pas-textarea" id="pas-' . esc_attr( $name ) . '" name="' . esc_attr( $name ) . '" rows="' . esc_attr( (string) $rows ) . '">' . esc_textarea( $value ) . '</textarea></div>';
	}

	private function select_row( $name, $label, $current, array $options ) {
		$html = '<div class="pas-form-row"><label for="pas-' . esc_attr( $name ) . '">' . esc_html( $label ) . '</label>';
		$html .= '<select class="pas-select" id="pas-' . esc_attr( $name ) . '" name="' . esc_attr( $name ) . '">';

		foreach ( $options as $value => $text ) {
			$html .= '<option value="' . esc_attr( (string) $value ) . '"' . selected( (string) $current, (string) $value, false ) . '>' . esc_html( (string) $text ) . '</option>';
		}

		$html .= '</select></div>';

		return $html;
	}

	private static function reminder_channels() {
		if ( class_exists( 'Pas_CF_Case_File' ) && defined( 'Pas_CF_Case_File::REMINDER_CHANNELS' ) ) {
			return (array) Pas_CF_Case_File::REMINDER_CHANNELS;
		}

		return array(
			'sms'   => __( 'پیامک', 'peyvand-amn-dashboard' ),
			'email' => __( 'ایمیل', 'peyvand-amn-dashboard' ),
		);
	}
}
