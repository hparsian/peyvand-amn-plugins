<?php
/**
 * ماژول ۴ — آزمون‌ها: دعوت، وضعیت، نتیجه، روند و گزارش.
 *
 * دعوت/ابطال/ارسال از API عمومی افزونهٔ آزمون انجام می‌شود؛ گزارش همان
 * render_report مالک است و داخل داشبورد نمایش داده می‌شود.
 */

defined( 'ABSPATH' ) || exit;

final class PAS_Dashboard_Module_Assessments extends PAS_Dashboard_Module {

	public function __construct() {
		$this->id          = 'assessments';
		$this->title       = __( 'آزمون‌ها', 'peyvand-amn-dashboard' );
		$this->description = __( 'دعوت به آزمون، پیگیری وضعیت، مشاهدهٔ نتیجه و روند ارزیابی.', 'peyvand-amn-dashboard' );
	}

	public function render() {
		if ( ! PAS_Dashboard_Core_Bridge::assessments_available() ) {
			return $this->unavailable( __( 'افزونهٔ آزمون‌ها فعال نیست یا رجیستری عمومی آن در دسترس نیست.', 'peyvand-amn-dashboard' ) );
		}

		if ( ! PAS_Dashboard_Adapter_Quiz::available() ) {
			return $this->unavailable( __( 'قرارداد دعوت/گزارش آزمون در افزونهٔ مالک در دسترس نیست.', 'peyvand-amn-dashboard' ) );
		}

		$invitation_id = isset( $_GET['invitation_id'] ) ? PAS_Dashboard_Validation::id( absint( wp_unslash( $_GET['invitation_id'] ) ) ) : 0;
		$attempt_id    = isset( $_GET['attempt_id'] ) ? PAS_Dashboard_Validation::id( absint( wp_unslash( $_GET['attempt_id'] ) ) ) : 0;

		if ( $invitation_id > 0 && $attempt_id > 0 ) {
			return $this->render_report( $invitation_id, $attempt_id );
		}

		if ( $invitation_id > 0 ) {
			return $this->render_results( $invitation_id );
		}

		return $this->render_home();
	}

	private function render_home() {
		$tests       = PAS_Dashboard_Core_Bridge::assessments();
		$status      = isset( $_GET['status'] ) ? PAS_Dashboard_Validation::key( sanitize_key( wp_unslash( $_GET['status'] ) ) ) : '';
		$invitations = PAS_Dashboard_Adapter_Quiz::invitations( 200, $status );
		$link        = PAS_Dashboard_Adapter_Quiz::take_invite_link();

		$completed = 0;
		$pending   = 0;

		foreach ( $invitations as $invitation ) {
			if ( 'completed' === (string) $invitation['status'] ) {
				$completed++;
			} elseif ( in_array( (string) $invitation['status'], array( 'unused', 'processing' ), true ) && ! $invitation['is_expired'] ) {
				$pending++;
			}
		}

		$html = '';

		if ( $link ) {
			$html .= $this->card_open( __( 'لینک دعوت تازه', 'peyvand-amn-dashboard' ) );
			$html .= '<p>' . esc_html( sprintf(
				/* translators: %s: client name */
				__( 'دعوت‌نامه برای %s ساخته شد. این لینک فقط یک‌بار نمایش داده می‌شود؛ در صورت نیاز کپی کنید.', 'peyvand-amn-dashboard' ),
				(string) ( $link['name'] ?? '' )
			) ) . '</p>';
			$html .= '<div class="pas-invite-link"><input class="pas-input" type="text" readonly value="' . esc_attr( (string) $link['link'] ) . '" dir="ltr" aria-label="' . esc_attr__( 'لینک دعوت', 'peyvand-amn-dashboard' ) . '" />';
			$html .= '<button type="button" class="pas-btn" data-pas-copy="' . esc_attr( (string) $link['link'] ) . '">' . esc_html__( 'کپی لینک', 'peyvand-amn-dashboard' ) . '</button></div>';
			$html .= $this->card_close();
		}

		$html .= $this->card_open( __( 'نمای کلی', 'peyvand-amn-dashboard' ) );
		$html .= '<div class="pas-grid">';
		$html .= '<div class="pas-stat"><span class="pas-stat-value">' . esc_html( (string) count( $tests ) ) . '</span><span class="pas-stat-label">' . esc_html__( 'آزمون فعال', 'peyvand-amn-dashboard' ) . '</span></div>';
		$html .= '<div class="pas-stat"><span class="pas-stat-value">' . esc_html( (string) count( $invitations ) ) . '</span><span class="pas-stat-label">' . esc_html__( 'دعوت‌نامه', 'peyvand-amn-dashboard' ) . '</span></div>';
		$html .= '<div class="pas-stat"><span class="pas-stat-value">' . esc_html( (string) $pending ) . '</span><span class="pas-stat-label">' . esc_html__( 'در انتظار پاسخ', 'peyvand-amn-dashboard' ) . '</span></div>';
		$html .= '<div class="pas-stat"><span class="pas-stat-value">' . esc_html( (string) $completed ) . '</span><span class="pas-stat-label">' . esc_html__( 'تکمیل‌شده', 'peyvand-amn-dashboard' ) . '</span></div>';
		$html .= '</div>';
		$html .= $this->card_close();

		$html .= $this->card_open( __( 'دعوت به آزمون', 'peyvand-amn-dashboard' ) );
		$html .= $this->render_invite_form( $tests );
		$html .= $this->card_close();

		$html .= $this->card_open( __( 'دعوت‌نامه‌ها', 'peyvand-amn-dashboard' ) );

		$html .= '<form method="get" class="pas-filter-form">';
		$html .= '<input type="hidden" name="page" value="' . esc_attr( PAS_DASHBOARD_SLUG ) . '" />';
		$html .= '<input type="hidden" name="module" value="assessments" />';
		$html .= '<div class="pas-form-row"><label for="pas-invite-status">' . esc_html__( 'وضعیت', 'peyvand-amn-dashboard' ) . '</label><select class="pas-select" id="pas-invite-status" name="status">';
		$options = array(
			''           => __( 'همه', 'peyvand-amn-dashboard' ),
			'unused'     => __( 'استفاده‌نشده', 'peyvand-amn-dashboard' ),
			'processing' => __( 'در حال پاسخ', 'peyvand-amn-dashboard' ),
			'completed'  => __( 'تکمیل‌شده', 'peyvand-amn-dashboard' ),
			'revoked'    => __( 'باطل‌شده', 'peyvand-amn-dashboard' ),
		);
		foreach ( $options as $value => $label ) {
			$html .= '<option value="' . esc_attr( $value ) . '"' . selected( $status, $value, false ) . '>' . esc_html( $label ) . '</option>';
		}
		$html .= '</select></div>';
		$html .= '<button class="pas-btn" type="submit">' . esc_html__( 'اعمال', 'peyvand-amn-dashboard' ) . '</button>';
		$html .= '</form>';

		if ( empty( $invitations ) ) {
			$html .= '<p class="pas-empty">' . esc_html__( 'دعوت‌نامه‌ای با این فیلتر یافت نشد.', 'peyvand-amn-dashboard' ) . '</p>';
			$html .= $this->card_close();
			return $html;
		}

		$html .= '<div class="pas-table-wrap"><table class="pas-table"><thead><tr>';
		$html .= '<th>' . esc_html__( 'تاریخ', 'peyvand-amn-dashboard' ) . '</th>';
		$html .= '<th>' . esc_html__( 'مراجع', 'peyvand-amn-dashboard' ) . '</th>';
		$html .= '<th>' . esc_html__( 'آزمون', 'peyvand-amn-dashboard' ) . '</th>';
		$html .= '<th>' . esc_html__( 'وضعیت', 'peyvand-amn-dashboard' ) . '</th>';
		$html .= '<th>' . esc_html__( 'انقضا', 'peyvand-amn-dashboard' ) . '</th>';
		$html .= '<th>' . esc_html__( 'عملیات', 'peyvand-amn-dashboard' ) . '</th>';
		$html .= '</tr></thead><tbody>';

		foreach ( $invitations as $invitation ) {
			$id     = (int) $invitation['id'];
			$state  = PAS_Dashboard_Adapter_Quiz::status_label( $invitation );
			$in_scope = PAS_Dashboard_Adapter_Quiz::can_view_phone( (string) $invitation['client_phone'] );

			$html .= '<tr>';
			$html .= '<td data-label="' . esc_attr__( 'تاریخ', 'peyvand-amn-dashboard' ) . '">' . esc_html( PAS_Dashboard_Core_Bridge::jalali_datetime( (string) $invitation['created_at'] ) ) . '</td>';
			$html .= '<td data-label="' . esc_attr__( 'مراجع', 'peyvand-amn-dashboard' ) . '"><strong>' . esc_html( (string) $invitation['client_name'] ) . '</strong><br /><span class="pas-muted" dir="ltr">' . esc_html( PAS_Dashboard_Core_Bridge::mask_mobile( (string) $invitation['client_phone'] ) ) . '</span></td>';
			$html .= '<td data-label="' . esc_attr__( 'آزمون', 'peyvand-amn-dashboard' ) . '">' . esc_html( (string) $invitation['quiz_name'] ) . '</td>';
			$html .= '<td data-label="' . esc_attr__( 'وضعیت', 'peyvand-amn-dashboard' ) . '"><span class="pas-badge ' . esc_attr( $state['class'] ) . '">' . esc_html( $state['label'] ) . '</span></td>';
			$html .= '<td data-label="' . esc_attr__( 'انقضا', 'peyvand-amn-dashboard' ) . '">' . esc_html( ! empty( $invitation['expires_at'] ) ? PAS_Dashboard_Core_Bridge::jalali_datetime( (string) $invitation['expires_at'] ) : '—' ) . '</td>';
			$html .= '<td data-label="' . esc_attr__( 'عملیات', 'peyvand-amn-dashboard' ) . '">';

			$html .= '<div class="pas-row-actions">';

			// دامنه اول بررسی می‌شود؛ ردیف دارای نتیجهٔ خارج از دامنه نباید
			// لینکی بدهد که صفحهٔ مقصدش با خطای دسترسی رد شود.
			if ( ! $in_scope ) {
				$html .= '<span class="pas-muted">' . esc_html__( 'خارج از دامنهٔ شما', 'peyvand-amn-dashboard' ) . '</span>';
			} elseif ( (int) $invitation['attempt_id'] > 0 ) {
				$url = $this->url( array( 'invitation_id' => $id ) );
				$html .= '<a class="pas-btn pas-btn-sm" href="' . esc_url( $url ) . '">' . esc_html__( 'نتیجه و روند', 'peyvand-amn-dashboard' ) . '</a>';
			} else {
				$html .= '<span class="pas-muted">' . esc_html__( 'در انتظار پاسخ', 'peyvand-amn-dashboard' ) . '</span>';
			}

			if ( $in_scope && in_array( (string) $invitation['status'], array( 'unused', 'processing' ), true ) && ! $invitation['is_expired'] ) {
				$html .= '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="pas-inline-form">';
				$html .= PAS_Dashboard_Actions::fields( 'assessment.notify', $this->url() );
				$html .= '<input type="hidden" name="invitation_id" value="' . esc_attr( (string) $id ) . '" />';
				$html .= '<button class="pas-btn pas-btn-sm" type="submit">' . esc_html__( 'ارسال دوباره', 'peyvand-amn-dashboard' ) . '</button>';
				$html .= '</form>';

				if ( 'unused' === (string) $invitation['status'] ) {
					$html .= '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="pas-inline-form">';
					$html .= PAS_Dashboard_Actions::fields( 'assessment.revoke', $this->url() );
					$html .= '<input type="hidden" name="invitation_id" value="' . esc_attr( (string) $id ) . '" />';
					$html .= '<button class="pas-btn pas-btn-sm pas-btn-danger" type="submit" data-pas-confirm="' . esc_attr__( 'این دعوت‌نامه باطل شود؟ لینک قبلی دیگر کار نمی‌کند.', 'peyvand-amn-dashboard' ) . '">' . esc_html__( 'ابطال', 'peyvand-amn-dashboard' ) . '</button>';
					$html .= '</form>';
				}
			}

			$html .= '</div>';
			$html .= '</td>';
			$html .= '</tr>';
		}

		$html .= '</tbody></table></div>';
		$html .= $this->card_close();

		return $html;
	}

	private function render_invite_form( array $tests ) {
		if ( empty( $tests ) ) {
			return '<p class="pas-empty">' . esc_html__( 'آزمونی برای دعوت ثبت نشده است.', 'peyvand-amn-dashboard' ) . '</p>';
		}

		$html  = '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="pas-form">';
		$html .= PAS_Dashboard_Actions::fields( 'assessment.invite', $this->url() );
		$html .= '<div class="pas-form-grid">';

		$html .= '<div class="pas-form-row"><label for="pas-inv-quiz">' . esc_html__( 'آزمون *', 'peyvand-amn-dashboard' ) . '</label><select class="pas-select" id="pas-inv-quiz" name="quiz_id" required>';
		foreach ( $tests as $test ) {
			$html .= '<option value="' . esc_attr( (string) $test['id'] ) . '">' . esc_html( (string) $test['name'] ) . '</option>';
		}
		$html .= '</select></div>';

		$html .= '<div class="pas-form-row"><label for="pas-inv-name">' . esc_html__( 'نام مراجع *', 'peyvand-amn-dashboard' ) . '</label><input class="pas-input" type="text" id="pas-inv-name" name="client_name" required /></div>';
		$html .= '<div class="pas-form-row"><label for="pas-inv-phone">' . esc_html__( 'موبایل *', 'peyvand-amn-dashboard' ) . '</label><input class="pas-input" type="text" id="pas-inv-phone" name="client_phone" dir="ltr" placeholder="09123456789" required /></div>';
		$html .= '<div class="pas-form-row"><label for="pas-inv-email">' . esc_html__( 'ایمیل (اختیاری)', 'peyvand-amn-dashboard' ) . '</label><input class="pas-input" type="email" id="pas-inv-email" name="client_email" dir="ltr" /></div>';
		$default_expiry = method_exists( 'Psy_Quiz_Invitation_Manager', 'default_expiry_days' ) ? (int) Psy_Quiz_Invitation_Manager::default_expiry_days() : 7;
		$html .= '<div class="pas-form-row"><label for="pas-inv-expiry">' . esc_html__( 'اعتبار (روز، ۰ = بدون انقضا)', 'peyvand-amn-dashboard' ) . '</label><input class="pas-input" type="number" id="pas-inv-expiry" name="expiry_days" min="0" max="90" value="' . esc_attr( (string) $default_expiry ) . '" /></div>';
		$html .= '<div class="pas-form-row pas-form-row--full"><label class="pas-check"><input type="checkbox" name="notify" value="1" checked /> ' . esc_html__( 'ارسال خودکار دعوت‌نامه به مراجع', 'peyvand-amn-dashboard' ) . '</label><p class="pas-field-hint">' . esc_html__( 'ارسال از طریق پیامک یا ایمیل، با همان قاعدهٔ مالک آزمون؛ لینک دعوت هم پس از ساخت نمایش داده می‌شود.', 'peyvand-amn-dashboard' ) . '</p></div>';

		$html .= '</div>';
		$html .= '<p class="pas-note-form-actions"><button class="pas-btn pas-btn-primary" type="submit">' . esc_html__( 'ساخت دعوت‌نامه', 'peyvand-amn-dashboard' ) . '</button></p>';
		$html .= '</form>';

		return $html;
	}

	private function render_results( $invitation_id ) {
		$invitation = PAS_Dashboard_Adapter_Quiz::invitation( $invitation_id );

		if ( ! $invitation ) {
			return $this->notice( 'error', __( 'دعوت‌نامه یافت نشد.', 'peyvand-amn-dashboard' ) );
		}

		$phone = (string) $invitation->client_phone;

		if ( ! PAS_Dashboard_Adapter_Quiz::can_view_phone( $phone ) ) {
			return $this->notice( 'error', __( 'این مراجع در پرونده‌های شما نیست؛ مشاهدهٔ نتیجه مجاز نیست.', 'peyvand-amn-dashboard' ) );
		}

		PAS_Dashboard_Audit_Log::record( 'assessment.results', 'assessment', (int) $invitation_id, 'ok' );

		$scores  = PAS_Dashboard_Adapter_Quiz::scores( $phone );
		$results = PAS_Dashboard_Adapter_Quiz::results( $phone );

		$html  = '<p><a class="pas-btn" href="' . esc_url( $this->url() ) . '">' . esc_html__( 'بازگشت به فهرست دعوت‌نامه‌ها', 'peyvand-amn-dashboard' ) . '</a></p>';

		$html .= $this->card_open( __( 'مراجع', 'peyvand-amn-dashboard' ) );
		$html .= '<div class="pas-grid">';
		$html .= '<div class="pas-stat"><span class="pas-stat-value">' . esc_html( (string) $invitation->client_name ) . '</span><span class="pas-stat-label">' . esc_html__( 'نام', 'peyvand-amn-dashboard' ) . '</span></div>';
		$html .= '<div class="pas-stat"><span class="pas-stat-value" dir="ltr">' . esc_html( PAS_Dashboard_Core_Bridge::mask_mobile( $phone ) ) . '</span><span class="pas-stat-label">' . esc_html__( 'موبایل', 'peyvand-amn-dashboard' ) . '</span></div>';
		$state  = PAS_Dashboard_Adapter_Quiz::status_label( $invitation );
		$html .= '<div class="pas-stat"><span class="pas-stat-value">' . esc_html( $state['label'] ) . '</span><span class="pas-stat-label">' . esc_html__( 'وضعیت دعوت', 'peyvand-amn-dashboard' ) . '</span></div>';
		$html .= '<div class="pas-stat"><span class="pas-stat-value">' . esc_html( (string) count( $scores ) ) . '</span><span class="pas-stat-label">' . esc_html__( 'نتیجهٔ ثبت‌شده', 'peyvand-amn-dashboard' ) . '</span></div>';
		$html .= '</div>';
		$html .= $this->card_close();

		$html .= $this->card_open( __( 'روند ارزیابی', 'peyvand-amn-dashboard' ) );

		if ( empty( $scores ) ) {
			$html .= '<p class="pas-empty">' . esc_html__( 'هنوز نتیجه‌ای برای این مراجع ثبت نشده است.', 'peyvand-amn-dashboard' ) . '</p>';
		} else {
			$previous = array();
			$trend    = array();

			foreach ( $scores as $score ) {
				$key  = (string) ( $score['quiz_id'] ?? '' );
				$total = $score['total'];
				$delta = null;

				if ( null !== $total && isset( $previous[ $key ] ) && null !== $previous[ $key ] ) {
					$delta = (float) $total - (float) $previous[ $key ];
				}

				if ( null !== $total ) {
					$previous[ $key ] = $total;
				}

				$trend[] = array(
					'score' => $score,
					'delta' => $delta,
				);
			}

			$html .= '<div class="pas-table-wrap"><table class="pas-table"><thead><tr>';
			$html .= '<th>' . esc_html__( 'آزمون', 'peyvand-amn-dashboard' ) . '</th>';
			$html .= '<th>' . esc_html__( 'نمرهٔ کل', 'peyvand-amn-dashboard' ) . '</th>';
			$html .= '<th>' . esc_html__( 'تغییر', 'peyvand-amn-dashboard' ) . '</th>';
			$html .= '<th>' . esc_html__( 'تاریخ', 'peyvand-amn-dashboard' ) . '</th>';
			$html .= '<th>' . esc_html__( 'گزارش', 'peyvand-amn-dashboard' ) . '</th>';
			$html .= '</tr></thead><tbody>';

			foreach ( array_reverse( $trend ) as $row ) {
				$score = $row['score'];
				$delta = $row['delta'];

				$html .= '<tr>';
				$html .= '<td data-label="' . esc_attr__( 'آزمون', 'peyvand-amn-dashboard' ) . '">' . esc_html( (string) ( $score['label'] ?? $score['quiz_id'] ) ) . '</td>';
				$html .= '<td data-label="' . esc_attr__( 'نمرهٔ کل', 'peyvand-amn-dashboard' ) . '">' . esc_html( null === $score['total'] ? '—' : (string) $score['total'] ) . '</td>';
				$html .= '<td data-label="' . esc_attr__( 'تغییر', 'peyvand-amn-dashboard' ) . '">' . ( null === $delta ? '—' : esc_html( ( $delta > 0 ? '▲ +' : ( $delta < 0 ? '▼ ' : '' ) ) . (string) $delta ) ) . '</td>';
				$html .= '<td data-label="' . esc_attr__( 'تاریخ', 'peyvand-amn-dashboard' ) . '">' . esc_html( PAS_Dashboard_Core_Bridge::jalali_datetime( (string) ( $score['completed_at'] ?? '' ) ) ) . '</td>';
				$html .= '<td data-label="' . esc_attr__( 'گزارش', 'peyvand-amn-dashboard' ) . '"><a class="pas-btn pas-btn-sm" href="' . esc_url( $this->url( array( 'invitation_id' => (int) $invitation_id, 'attempt_id' => (int) ( $score['attempt_id'] ?? 0 ) ) ) ) . '">' . esc_html__( 'مشاهدهٔ گزارش', 'peyvand-amn-dashboard' ) . '</a></td>';
				$html .= '</tr>';
			}

			$html .= '</tbody></table></div>';
		}

		$html .= $this->card_close();

		if ( ! empty( $results ) ) {
			$html .= $this->card_open( __( 'فهرست نتایج تکمیل‌شده', 'peyvand-amn-dashboard' ) );
			$html .= '<ul class="pas-list">';
			foreach ( $results as $result ) {
				$html .= '<li>' . esc_html( (string) ( $result['label'] ?? '' ) ) . '</li>';
			}
			$html .= '</ul>';
			$html .= $this->card_close();
		}

		return $html;
	}

	private function render_report( $invitation_id, $attempt_id ) {
		$invitation = PAS_Dashboard_Adapter_Quiz::invitation( $invitation_id );

		if ( ! $invitation ) {
			return $this->notice( 'error', __( 'دعوت‌نامه یافت نشد.', 'peyvand-amn-dashboard' ) );
		}

		$phone = (string) $invitation->client_phone;

		if ( ! PAS_Dashboard_Adapter_Quiz::can_view_phone( $phone ) ) {
			return $this->notice( 'error', __( 'مشاهدهٔ این گزارش برای شما مجاز نیست.', 'peyvand-amn-dashboard' ) );
		}

		$report = PAS_Dashboard_Adapter_Quiz::report( $attempt_id, $phone );

		PAS_Dashboard_Audit_Log::record( 'assessment.report', 'assessment', (int) $attempt_id, ! empty( $report['ok'] ) ? 'ok' : 'error' );

		$html  = '<p><a class="pas-btn" href="' . esc_url( $this->url( array( 'invitation_id' => (int) $invitation_id ) ) ) . '">' . esc_html__( 'بازگشت به نتیجه و روند', 'peyvand-amn-dashboard' ) . '</a></p>';

		if ( empty( $report['ok'] ) ) {
			$html .= $this->notice( 'error', (string) ( $report['error'] ?? __( 'گزارش در دسترس نیست.', 'peyvand-amn-dashboard' ) ) );

			return $html;
		}

		$html .= $this->card_open( sprintf(
			/* translators: 1: quiz name, 2: client name */
			__( 'گزارش %1$s — %2$s', 'peyvand-amn-dashboard' ),
			(string) ( $report['quiz_name'] ?? '' ),
			(string) ( $report['client_name'] ?? '' )
		) );
		/* HTML گزارش از render_report مالک آزمون می‌آید (شامل canvas و اسکریپت نمودار)؛
		 * همان خروجی است که صفحهٔ گزارش مالک هم چاپ می‌کند و نباید فیلتر شود. */
		$html .= '<div class="pas-report">' . $report['html'] . '</div>';
		$html .= $this->card_close();

		return $html;
	}
}
