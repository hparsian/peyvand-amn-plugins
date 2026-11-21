<?php
/**
 * ماژول ۶ — گزارش‌ها، شاخص‌های بالینی/عملیاتی و نمودار روند (فاز ۴).
 *
 * همهٔ اعداد از API عمومی افزونه‌های مالک می‌آیند (پل هسته، آداپتر پنل و
 * آداپتر آزمون) و هیچ جدول افزونهٔ دیگری مستقیماً پرس‌وجو نمی‌شود. نمودار با
 * HTML/CSS ساخته می‌شود تا هیچ کتابخانهٔ بیرونی یا درخواست شبکه لازم نباشد و
 * در RTL و موبایل هم درست دیده شود.
 */

defined( 'ABSPATH' ) || exit;

final class PAS_Dashboard_Module_Reports extends PAS_Dashboard_Module {

	/** تعداد ماه‌های نمودار روند. */
	const TREND_MONTHS = 6;

	/** پنجرهٔ شاخص‌های کوتاه‌مدت (روز). */
	const WINDOW_DAYS = 30;

	public function __construct() {
		$this->id          = 'reports';
		$this->title       = __( 'گزارش‌ها', 'peyvand-amn-dashboard' );
		$this->description = __( 'شاخص‌های بالینی و عملیاتی، روند ماهانه، بار کاری و خروجی CSV — فقط روی دادهٔ مجاز شما.', 'peyvand-amn-dashboard' );
	}

	public function render() {
		if ( ! PAS_Dashboard_Security::can_view_clinical() ) {
			return $this->unavailable( __( 'دسترسی به داده‌های بالینی برای شما مجاز نیست.', 'peyvand-amn-dashboard' ) );
		}

		$user_id     = get_current_user_id();
		$cases       = PAS_Dashboard_Core_Bridge::cases( $user_id, 500 );
		$stats       = PAS_Dashboard_Core_Bridge::client_stats();
		$bookings    = PAS_Dashboard_Adapter_Portal::available() ? PAS_Dashboard_Adapter_Portal::bookings( 300 ) : array();
		$invitations = PAS_Dashboard_Adapter_Quiz::available() ? PAS_Dashboard_Adapter_Quiz::invitations( 300 ) : array();

		$cases      = array_values( array_filter( $cases, 'is_object' ) );
		$last_map   = $this->last_session_map( $cases );
		$by_status  = $this->case_status_counts( $cases );
		$metrics    = $this->metrics( $cases, $last_map, $bookings, $invitations );

		$html = '';

		$html .= $this->render_kpis( $metrics );
		$html .= $this->render_trend( $cases, $bookings, $invitations );
		$html .= $this->render_booking_status( $bookings );

		if ( PAS_Dashboard_Core_Bridge::viewer_can_see_all_cases() ) {
			$html .= $this->render_workload( $cases );
		}

		$html .= $this->render_case_status( $by_status );

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

	/* ── داده ──────────────────────────────────────────────────────────── */

	/** @return array<int,string> شناسهٔ پرونده → تاریخ آخرین جلسه */
	private function last_session_map( array $cases ) {
		$ids = array();

		foreach ( $cases as $case ) {
			if ( ! empty( $case->id ) ) {
				$ids[] = (int) $case->id;
			}
		}

		if ( empty( $ids ) || ! class_exists( 'Pas_CF_Case_File' ) || ! method_exists( 'Pas_CF_Case_File', 'get_last_session_map' ) ) {
			return array();
		}

		return (array) Pas_CF_Case_File::get_last_session_map( $ids );
	}

	/** @return array<string,int> */
	private function case_status_counts( array $cases ) {
		$counts = array();

		foreach ( $cases as $case ) {
			$status = trim( (string) ( $case->status ?? '' ) );
			$status = $status !== '' ? $status : '—';
			$counts[ $status ] = ( $counts[ $status ] ?? 0 ) + 1;
		}

		arsort( $counts );

		return $counts;
	}

	/**
	 * شاخص‌های کلیدی — همه از دادهٔ مجاز کاربر جاری.
	 *
	 * @return array<string,array{value:string,label:string,hint:string}>
	 */
	private function metrics( array $cases, array $last_map, array $bookings, array $invitations ) {
		$now     = strtotime( gmdate( 'Y-m-d' ) );
		$today   = gmdate( 'Y-m-d', $now );
		$since30 = gmdate( 'Y-m-d', $now - self::WINDOW_DAYS * DAY_IN_SECONDS );
		$since60 = gmdate( 'Y-m-d', $now - 60 * DAY_IN_SECONDS );
		$since90 = gmdate( 'Y-m-d', $now - 90 * DAY_IN_SECONDS );
		$until30 = gmdate( 'Y-m-d', $now + self::WINDOW_DAYS * DAY_IN_SECONDS );

		$new_cases  = 0;
		$stale      = 0;
		$sessions30 = 0;

		foreach ( $cases as $case ) {
			$created = (string) ( $case->created_at ?? '' );
			if ( $created !== '' && $created >= $since30 ) {
				$new_cases++;
			}

			$case_id = (int) ( $case->id ?? 0 );
			$last    = isset( $last_map[ $case_id ] ) ? (string) $last_map[ $case_id ] : '';

			if ( $last !== '' && $last >= $since30 ) {
				$sessions30++;
			}

			// پروندهٔ باز بدون جلسه در ۶۰ روز گذشته = نیازمند پیگیری.
			if ( ! $this->is_closed_status( (string) ( $case->status ?? '' ) ) && ( $last === '' || $last < $since60 ) ) {
				$stale++;
			}
		}

		$window_total = 0;
		$cancelled    = 0;
		$upcoming     = 0;

		foreach ( $bookings as $booking ) {
			$date = (string) ( $booking->booking_date ?? '' );
			$state = (string) ( $booking->status ?? '' );

			if ( $date === '' ) {
				continue;
			}

			if ( $date >= $today && $date <= $until30 && ! $this->is_closed_status( $state ) ) {
				$upcoming++;
			}

			if ( $date >= $since90 && $date <= $today ) {
				$window_total++;
				if ( in_array( $state, array( 'لغو شده', 'عدم حضور', 'ناموفق' ), true ) ) {
					$cancelled++;
				}
			}
		}

		$completed = 0;
		foreach ( $invitations as $invitation ) {
			$created = (string) ( $invitation['created_at'] ?? '' );
			if ( $created >= $since90 && (int) ( $invitation['attempt_id'] ?? 0 ) > 0 ) {
				$completed++;
			}
		}

		return array(
			'cases'      => array(
				'value' => (string) count( $cases ),
				'label' => __( 'پروندهٔ مجاز', 'peyvand-amn-dashboard' ),
				'hint'  => __( 'پرونده‌هایی که در دامنهٔ دسترسی شما هستند.', 'peyvand-amn-dashboard' ),
			),
			'new_cases'  => array(
				'value' => (string) $new_cases,
				'label' => __( 'پروندهٔ تازه (۳۰ روز)', 'peyvand-amn-dashboard' ),
				'hint'  => __( 'پرونده‌های ساخته‌شده در ۳۰ روز گذشته.', 'peyvand-amn-dashboard' ),
			),
			'sessions30' => array(
				'value' => (string) $sessions30,
				'label' => __( 'جلسه در ۳۰ روز گذشته', 'peyvand-amn-dashboard' ),
				'hint'  => __( 'پرونده‌هایی که آخرین جلسه‌شان در ۳۰ روز گذشته ثبت شده است.', 'peyvand-amn-dashboard' ),
			),
			'stale'      => array(
				'value' => (string) $stale,
				'label' => __( 'نیازمند پیگیری', 'peyvand-amn-dashboard' ),
				'hint'  => __( 'پروندهٔ باز بدون جلسه در ۶۰ روز گذشته.', 'peyvand-amn-dashboard' ),
			),
			'upcoming'   => array(
				'value' => (string) $upcoming,
				'label' => __( 'نوبت پیش‌رو (۳۰ روز)', 'peyvand-amn-dashboard' ),
				'hint'  => __( 'نوبت‌های فعال از امروز تا ۳۰ روز آینده.', 'peyvand-amn-dashboard' ),
			),
			'cancel'     => array(
				'value' => $window_total > 0 ? $this->percent( $cancelled, $window_total ) . '٪' : '—',
				'label' => __( 'نرخ لغو/عدم حضور (۹۰ روز)', 'peyvand-amn-dashboard' ),
				'hint'  => $window_total > 0
					? sprintf(
						/* translators: 1: cancelled count, 2: total count */
						__( '%1$s از %2$s نوبت گذشته.', 'peyvand-amn-dashboard' ),
						number_format_i18n( $cancelled ),
						number_format_i18n( $window_total )
					)
					: __( 'نوبت گذشته‌ای در این بازه ثبت نشده است.', 'peyvand-amn-dashboard' ),
			),
			'assessments' => array(
				'value' => (string) $completed,
				'label' => __( 'آزمون تکمیل‌شده (۹۰ روز)', 'peyvand-amn-dashboard' ),
				'hint'  => __( 'دعوت‌نامه‌های دارای نتیجه در ۹۰ روز گذشته.', 'peyvand-amn-dashboard' ),
			),
			'clients'    => array(
				'value' => (string) (int) ( $stats['clients'] ?? 0 ),
				'label' => __( 'مراجع در رجیستری', 'peyvand-amn-dashboard' ),
				'hint'  => __( 'هویت‌های ثبت‌شده در هستهٔ مشترک.', 'peyvand-amn-dashboard' ),
			),
		);
	}

	/* ── نمایش ─────────────────────────────────────────────────────────── */

	private function render_kpis( array $metrics ) {
		$html  = $this->card_open( __( 'شاخص‌های کلیدی', 'peyvand-amn-dashboard' ) );
		$html .= '<div class="pas-grid">';

		foreach ( $metrics as $metric ) {
			$html .= '<div class="pas-stat" title="' . esc_attr( (string) $metric['hint'] ) . '">';
			$html .= '<span class="pas-stat-value">' . esc_html( (string) $metric['value'] ) . '</span>';
			$html .= '<span class="pas-stat-label">' . esc_html( (string) $metric['label'] ) . '</span>';
			$html .= '</div>';
		}

		$html .= '</div>';
		$html .= '<p class="pas-muted">' . esc_html__( 'همهٔ اعداد از API عمومی افزونه‌های مالک خوانده می‌شوند و محدود به پرونده‌های مجاز شما هستند.', 'peyvand-amn-dashboard' ) . '</p>';
		$html .= $this->card_close();

		return $html;
	}

	private function render_trend( array $cases, array $bookings, array $invitations ) {
		$months = $this->recent_months( self::TREND_MONTHS );
		$series = array(
			'cases'  => array(
				'label' => __( 'پروندهٔ تازه', 'peyvand-amn-dashboard' ),
				'class' => 'pas-chart-dot-a',
				'bar'   => 'pas-chart-bar-a',
			),
			'bookings' => array(
				'label' => __( 'نوبت', 'peyvand-amn-dashboard' ),
				'class' => 'pas-chart-dot-b',
				'bar'   => 'pas-chart-bar-b',
			),
			'invites'  => array(
				'label' => __( 'دعوت آزمون', 'peyvand-amn-dashboard' ),
				'class' => 'pas-chart-dot-c',
				'bar'   => 'pas-chart-bar-c',
			),
		);

		$rows = array();
		foreach ( $months as $month ) {
			$rows[ $month['key'] ] = array(
				'label'    => $month['label'],
				'short'    => $month['short'],
				'cases'    => 0,
				'bookings' => 0,
				'invites'  => 0,
			);
		}

		foreach ( $cases as $case ) {
			$key = $this->month_key( (string) ( $case->created_at ?? '' ) );
			if ( isset( $rows[ $key ] ) ) {
				$rows[ $key ]['cases']++;
			}
		}

		foreach ( $bookings as $booking ) {
			$key = $this->month_key( (string) ( $booking->booking_date ?? '' ) );
			if ( isset( $rows[ $key ] ) ) {
				$rows[ $key ]['bookings']++;
			}
		}

		foreach ( $invitations as $invitation ) {
			$key = $this->month_key( (string) ( $invitation['created_at'] ?? '' ) );
			if ( isset( $rows[ $key ] ) ) {
				$rows[ $key ]['invites']++;
			}
		}

		$max = 0;
		foreach ( $rows as $row ) {
			$max = max( $max, $row['cases'], $row['bookings'], $row['invites'] );
		}

		$html  = $this->card_open( __( 'روند ۶ ماه گذشته', 'peyvand-amn-dashboard' ) );
		$html .= '<div class="pas-chart-legend">';

		foreach ( $series as $item ) {
			$html .= '<span><span class="pas-chart-dot ' . esc_attr( $item['class'] ) . '" aria-hidden="true"></span>' . esc_html( (string) $item['label'] ) . '</span>';
		}

		$html .= '</div>';

		$html .= '<div class="pas-chart-plot">';

		foreach ( $rows as $row ) {
			$summary = sprintf(
				/* translators: 1: month, 2: cases, 3: bookings, 4: invitations */
				__( '%1$s: %2$s پرونده، %3$s نوبت، %4$s دعوت آزمون', 'peyvand-amn-dashboard' ),
				$row['label'],
				number_format_i18n( $row['cases'] ),
				number_format_i18n( $row['bookings'] ),
				number_format_i18n( $row['invites'] )
			);

			$html .= '<div class="pas-chart-col" role="img" aria-label="' . esc_attr( $summary ) . '" title="' . esc_attr( $summary ) . '">';
			$html .= '<div class="pas-chart-bars">';

			foreach ( $series as $field => $item ) {
				$height = $max > 0 ? (int) round( ( $row[ $field ] / $max ) * 100 ) : 0;

				$html .= '<span class="pas-chart-bar ' . esc_attr( $item['bar'] ) . '" style="--pas-h:' . esc_attr( (string) max( 2, $height ) ) . '%" aria-hidden="true"></span>';
			}

			$html .= '</div>';
			$html .= '<span class="pas-chart-month">' . esc_html( (string) $row['short'] ) . '</span>';
			$html .= '</div>';
		}

		$html .= '</div>';

		$html .= '<div class="pas-table-wrap pas-chart-table"><table class="pas-table"><thead><tr>';
		$html .= '<th>' . esc_html__( 'ماه', 'peyvand-amn-dashboard' ) . '</th>';

		foreach ( $series as $item ) {
			$html .= '<th>' . esc_html( (string) $item['label'] ) . '</th>';
		}

		$html .= '</tr></thead><tbody>';

		foreach ( $rows as $row ) {
			$html .= '<tr>';
			$html .= '<td data-label="' . esc_attr__( 'ماه', 'peyvand-amn-dashboard' ) . '">' . esc_html( (string) $row['label'] ) . '</td>';

			foreach ( array_keys( $series ) as $field ) {
				$html .= '<td data-label="' . esc_attr( (string) $series[ $field ]['label'] ) . '">' . esc_html( number_format_i18n( $row[ $field ] ) ) . '</td>';
			}

			$html .= '</tr>';
		}

		$html .= '</tbody></table></div>';
		$html .= '<p class="pas-muted">' . esc_html__( 'ماه‌ها شمسی‌اند؛ پرونده بر اساس تاریخ ساخت، نوبت بر اساس تاریخ نوبت و دعوت آزمون بر اساس تاریخ دعوت شمرده می‌شود. نمودار با HTML/CSS ساخته شده و هیچ کتابخانهٔ بیرونی ندارد.', 'peyvand-amn-dashboard' ) . '</p>';
		$html .= $this->card_close();

		return $html;
	}

	private function render_booking_status( array $bookings ) {
		$now     = strtotime( gmdate( 'Y-m-d' ) );
		$since60 = gmdate( 'Y-m-d', $now - 60 * DAY_IN_SECONDS );
		$until30 = gmdate( 'Y-m-d', $now + self::WINDOW_DAYS * DAY_IN_SECONDS );

		$counts = array();
		$total  = 0;

		foreach ( $bookings as $booking ) {
			$date = (string) ( $booking->booking_date ?? '' );

			if ( $date === '' || $date < $since60 || $date > $until30 ) {
				continue;
			}

			$status = trim( (string) ( $booking->status ?? '' ) );
			$status = $status !== '' ? $status : '—';
			$counts[ $status ] = ( $counts[ $status ] ?? 0 ) + 1;
			$total++;
		}

		arsort( $counts );

		$html  = $this->card_open( __( 'وضعیت نوبت‌ها (۶۰ روز گذشته تا ۳۰ روز آینده)', 'peyvand-amn-dashboard' ) );

		if ( ! PAS_Dashboard_Adapter_Portal::available() ) {
			$html .= $this->unavailable( __( 'افزونهٔ پنل مراجعان فعال نیست یا قرارداد عمومی نوبت‌ها را ارائه نمی‌کند؛ گزارش نوبت‌ها در دسترس نیست.', 'peyvand-amn-dashboard' ) );
			$html .= $this->card_close();

			return $html;
		}

		if ( $total === 0 ) {
			$html .= '<p class="pas-empty">' . esc_html__( 'نوبتی در این بازه ثبت نشده است.', 'peyvand-amn-dashboard' ) . '</p>';
			$html .= $this->card_close();

			return $html;
		}

		$html .= '<div class="pas-chart-legend"><span><span class="pas-chart-dot pas-chart-dot-a" aria-hidden="true"></span>' . esc_html__( 'سهم از کل نوبت‌های بازه', 'peyvand-amn-dashboard' ) . '</span></div>';

		$html .= '<div class="pas-table-wrap"><table class="pas-table"><thead><tr>';
		$html .= '<th>' . esc_html__( 'وضعیت', 'peyvand-amn-dashboard' ) . '</th>';
		$html .= '<th>' . esc_html__( 'تعداد', 'peyvand-amn-dashboard' ) . '</th>';
		$html .= '<th>' . esc_html__( 'سهم', 'peyvand-amn-dashboard' ) . '</th>';
		$html .= '</tr></thead><tbody>';

		foreach ( $counts as $status => $count ) {
			$share = $this->percent( $count, $total );

			$html .= '<tr>';
			$html .= '<td data-label="' . esc_attr__( 'وضعیت', 'peyvand-amn-dashboard' ) . '"><span class="pas-badge">' . esc_html( $status ) . '</span></td>';
			$html .= '<td data-label="' . esc_attr__( 'تعداد', 'peyvand-amn-dashboard' ) . '">' . esc_html( number_format_i18n( $count ) ) . '</td>';
			$html .= '<td data-label="' . esc_attr__( 'سهم', 'peyvand-amn-dashboard' ) . '">';
			$html .= '<span class="pas-meter" role="img" aria-label="' . esc_attr( sprintf( '%s٪', $share ) ) . '">';
			$html .= '<span class="pas-meter-fill" style="--pas-w:' . esc_attr( (string) max( 1, $share ) ) . '%"></span>';
			$html .= '</span> <span class="pas-muted">' . esc_html( $share . '٪' ) . '</span>';
			$html .= '</td>';
			$html .= '</tr>';
		}

		$html .= '</tbody></table></div>';
		$html .= '<p class="pas-muted">' . esc_html__( 'مبنای درصد، همهٔ نوبت‌های همین بازه است (نه فقط وضعیت‌های نمایش‌داده‌شده).', 'peyvand-amn-dashboard' ) . '</p>';
		$html .= $this->card_close();

		return $html;
	}

	private function render_workload( array $cases ) {
		$counts = array();

		foreach ( $cases as $case ) {
			$name = PAS_Dashboard_Core_Bridge::therapist_name( (int) ( $case->assigned_therapist ?? 0 ) );
			$counts[ $name ] = ( $counts[ $name ] ?? 0 ) + 1;
		}

		arsort( $counts );
		$counts = array_slice( $counts, 0, 10, true );

		$max = $counts ? max( $counts ) : 0;

		$html  = $this->card_open( __( 'بار کاری درمانگران', 'peyvand-amn-dashboard' ) );
		$html .= '<p class="pas-muted">' . esc_html__( 'شمار پرونده‌های هر درمانگر در دامنهٔ دید شما. «تخصیص‌نیافته» یعنی پرونده درمانگر مسئول ندارد.', 'peyvand-amn-dashboard' ) . '</p>';
		$html .= '<div class="pas-table-wrap"><table class="pas-table"><thead><tr>';
		$html .= '<th>' . esc_html__( 'درمانگر', 'peyvand-amn-dashboard' ) . '</th>';
		$html .= '<th>' . esc_html__( 'پرونده', 'peyvand-amn-dashboard' ) . '</th>';
		$html .= '<th>' . esc_html__( 'نسبت به بیشترین', 'peyvand-amn-dashboard' ) . '</th>';
		$html .= '</tr></thead><tbody>';

		foreach ( $counts as $name => $count ) {
			$share = $max > 0 ? $this->percent( $count, $max ) : 0;

			$html .= '<tr>';
			$html .= '<td data-label="' . esc_attr__( 'درمانگر', 'peyvand-amn-dashboard' ) . '">' . esc_html( (string) $name ) . '</td>';
			$html .= '<td data-label="' . esc_attr__( 'پرونده', 'peyvand-amn-dashboard' ) . '">' . esc_html( number_format_i18n( $count ) ) . '</td>';
			$html .= '<td data-label="' . esc_attr__( 'نسبت', 'peyvand-amn-dashboard' ) . '">';
			$html .= '<span class="pas-meter" role="img" aria-label="' . esc_attr( sprintf( '%s٪', $share ) ) . '">';
			$html .= '<span class="pas-meter-fill" style="--pas-w:' . esc_attr( (string) max( 1, $share ) ) . '%"></span>';
			$html .= '</span>';
			$html .= '</td>';
			$html .= '</tr>';
		}

		$html .= '</tbody></table></div>';
		$html .= $this->card_close();

		return $html;
	}

	private function render_case_status( array $by_status ) {
		$total = array_sum( $by_status );

		$html = $this->card_open( __( 'توزیع وضعیت پرونده‌ها', 'peyvand-amn-dashboard' ) );

		if ( empty( $by_status ) ) {
			$html .= '<p class="pas-empty">' . esc_html__( 'داده‌ای برای گزارش وجود ندارد.', 'peyvand-amn-dashboard' ) . '</p>';
			$html .= $this->card_close();

			return $html;
		}

		$html .= '<div class="pas-table-wrap"><table class="pas-table"><thead><tr>';
		$html .= '<th>' . esc_html__( 'وضعیت', 'peyvand-amn-dashboard' ) . '</th>';
		$html .= '<th>' . esc_html__( 'تعداد', 'peyvand-amn-dashboard' ) . '</th>';
		$html .= '<th>' . esc_html__( 'سهم', 'peyvand-amn-dashboard' ) . '</th>';
		$html .= '</tr></thead><tbody>';

		foreach ( $by_status as $status => $count ) {
			$share = $this->percent( $count, $total );

			$html .= '<tr>';
			$html .= '<td data-label="' . esc_attr__( 'وضعیت', 'peyvand-amn-dashboard' ) . '"><span class="pas-badge">' . esc_html( (string) $status ) . '</span></td>';
			$html .= '<td data-label="' . esc_attr__( 'تعداد', 'peyvand-amn-dashboard' ) . '">' . esc_html( number_format_i18n( $count ) ) . '</td>';
			$html .= '<td data-label="' . esc_attr__( 'سهم', 'peyvand-amn-dashboard' ) . '">';
			$html .= '<span class="pas-meter" role="img" aria-label="' . esc_attr( sprintf( '%s٪', $share ) ) . '">';
			$html .= '<span class="pas-meter-fill" style="--pas-w:' . esc_attr( (string) max( 1, $share ) ) . '%"></span>';
			$html .= '</span> <span class="pas-muted">' . esc_html( $share . '٪' ) . '</span>';
			$html .= '</td>';
			$html .= '</tr>';
		}

		$html .= '</tbody></table></div>';
		$html .= $this->card_close();

		return $html;
	}

	/* ── ابزار ─────────────────────────────────────────────────────────── */

	private function is_closed_status( $status ) {
		return in_array( trim( (string) $status ), array( 'لغو شده', 'ناموفق', 'انجام شده', 'عدم حضور', 'بسته', 'آرشیو' ), true );
	}

	private function percent( $part, $total ) {
		$total = (int) $total;

		if ( $total <= 0 ) {
			return 0;
		}

		return (int) round( ( (int) $part / $total ) * 100 );
	}

	/**
	 * کلید ماه شمسی ('1405/08') برای یک تاریخ میلادی؛ در نبود هستهٔ مشترک،
	 * به ماه میلادی برمی‌گردد.
	 */
	private function month_key( $date ) {
		$date = trim( (string) $date );

		if ( $date === '' ) {
			return '';
		}

		$jalali = PAS_Dashboard_Core_Bridge::jalali( $date );

		if ( preg_match( '~^(\d{4})/(\d{2})~', (string) $jalali, $m ) ) {
			return $m[1] . '/' . $m[2];
		}

		return substr( $date, 0, 7 );
	}

	/** @return array<int,array{key:string,label:string,short:string}> */
	private function recent_months( $count ) {
		$count  = max( 1, (int) $count );
		$names  = array( 'فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور', 'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند' );
		$today  = (string) PAS_Dashboard_Core_Bridge::jalali( gmdate( 'Y-m-d' ) );
		$months = array();

		if ( preg_match( '~^(\d{4})/(\d{2})~', $today, $m ) ) {
			$year  = (int) $m[1];
			$month = (int) $m[2];

			for ( $i = $count - 1; $i >= 0; $i-- ) {
				$mm = $month - $i;
				$yy = $year;

				while ( $mm <= 0 ) {
					$mm += 12;
					$yy--;
				}

				$name     = $names[ $mm - 1 ];
				$months[] = array(
					'key'   => sprintf( '%04d/%02d', $yy, $mm ),
					'label' => $name . ' ' . $yy,
					'short' => $name,
				);
			}

			return $months;
		}

		for ( $i = $count - 1; $i >= 0; $i-- ) {
			$ts       = strtotime( gmdate( 'Y-m-01' ) . ' -' . $i . ' month' );
			$key      = gmdate( 'Y-m', $ts );
			$months[] = array(
				'key'   => $key,
				'label' => $key,
				'short' => gmdate( 'm', $ts ),
			);
		}

		return $months;
	}
}
