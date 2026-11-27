<?php
/**
 * ماژول ۱ — نمای کلی (پوستهٔ ۲).
 *
 * چیدمان نامتقارن: «امروز» بزرگ (۷ ستون) + «نیازمند توجه» (۵ ستون)،
 * سپس پرونده‌های اخیر (۸ ستون) + وضعیت اکوسیستم (۴ ستون).
 * هیچ دادهٔ ساختگی نمایش داده نمی‌شود؛ همه از bridge می‌آید.
 */

defined( 'ABSPATH' ) || exit;

final class PAS_Dashboard_Module_Overview extends PAS_Dashboard_Module {

	public function __construct() {
		$this->id          = 'overview';
		$this->title       = __( 'نمای کلی', 'peyvand-amn-dashboard' );
		$this->description = __( 'تصویر امروز شما: جلسه‌های پیش‌رو، موارد نیازمند رسیدگی و آخرین پرونده‌ها.', 'peyvand-amn-dashboard' );
	}

	public function render() {
		$user_id = get_current_user_id();
		// محدود به ۶۰ پرونده برای پرهیز از N+1 سنگین در بارگذاری داشبورد.
		$cases   = PAS_Dashboard_Core_Bridge::cases( $user_id, 60 );
		$stats   = PAS_Dashboard_Core_Bridge::client_stats();

		$today    = strtotime( gmdate( 'Y-m-d' ) );
		$upcoming = array();
		$today_n  = 0;

		foreach ( $cases as $case ) {
			if ( ! is_object( $case ) ) {
				continue;
			}

			$next = PAS_Dashboard_Core_Bridge::next_session( $case );
			$date = ( $next && ! empty( $next->next_session_date ) ) ? (string) $next->next_session_date : '';

			if ( $date === '' ) {
				continue;
			}

			$ts = strtotime( $date );
			if ( $ts === false || $ts < $today ) {
				continue;
			}

			if ( $ts === $today ) {
				$today_n++;
			}

			$upcoming[] = array(
				'case' => $case,
				'date' => $date,
				'ts'   => $ts,
			);
		}

		usort(
			$upcoming,
			static function ( $a, $b ) {
				return $a['ts'] <=> $b['ts'];
			}
		);

		$upcoming = array_slice( $upcoming, 0, 5 );
		$recent   = array_slice( array_values( array_filter( $cases, 'is_object' ) ), 0, 6 );

		$html = '';

		if ( ! PAS_Dashboard_Core_Bridge::crypto_ready() ) {
			$html .= $this->notice(
				'warning',
				__( 'کلید رمزنگاری تنظیم نشده است. یادداشت‌های بالینی نه نمایش داده می‌شوند و نه ثبت می‌شوند.', 'peyvand-amn-dashboard' )
			);
		}

		$review = (int) ( $stats['review'] ?? 0 );

		/* ── نوار شاخص‌ها ─────────────────────────────────────── */
		$html .= '<section class="pas-card pvn-rise" style="--pui-i:0">';
		$html .= '<div class="pas-grid">';
		$html .= $this->stat( count( $cases ), __( 'پرونده‌ی مجاز', 'peyvand-amn-dashboard' ) );
		$html .= $this->stat( $today_n, __( 'جلسه‌ی امروز', 'peyvand-amn-dashboard' ) );
		$html .= $this->stat( (int) ( $stats['clients'] ?? 0 ), __( 'مراجع در رجیستری', 'peyvand-amn-dashboard' ) );
		$html .= $this->stat( $review, __( 'نیازمند بررسی هویت', 'peyvand-amn-dashboard' ) );
		$html .= '</div>';
		$html .= '</section>';

		/* ── دک نامتقارن ──────────────────────────────────────── */
		$html .= '<div class="pvn-deck">';

		/* ۱) امروز — بلوک بزرگ */
		$html .= '<section class="pvn-focus pvn-span-7 pvn-rise" style="--pui-i:1" aria-labelledby="pvn-today-title">';
		$html .= '<p class="pui-eyebrow" id="pvn-today-title">' . esc_html__( 'جلسه‌های پیش‌رو', 'peyvand-amn-dashboard' ) . '</p>';
		$html .= '<p class="pvn-focus__figure">'
			. '<span class="pvn-focus__num pui-num">' . esc_html( (string) count( $upcoming ) ) . '</span>'
			. '<span class="pvn-focus__unit">' . esc_html__( 'جلسه', 'peyvand-amn-dashboard' ) . '</span>'
			. '</p>';
		$html .= '<p class="pvn-focus__caption">'
			. esc_html(
				count( $upcoming ) > 0
					? __( 'جلسه در ۶۰ پرونده‌ی مجاز شما زمان‌بندی شده است.', 'peyvand-amn-dashboard' )
					: __( 'برای پرونده‌های مجاز شما جلسه‌ی آینده‌ای ثبت نشده است.', 'peyvand-amn-dashboard' )
			)
			. '</p>';

		if ( ! empty( $upcoming ) ) {
			$html .= '<ul class="pvn-focus__list">';
			foreach ( $upcoming as $row ) {
				$case    = $row['case'];
				$case_id = (int) ( $case->id ?? 0 );
				$name    = (string) ( $case->full_name ?? '' );
				$name    = $name !== '' ? $name : __( 'بدون نام', 'peyvand-amn-dashboard' );

				$html .= '<li><a href="' . esc_url( $this->case_url( $case_id ) ) . '">';
				$html .= '<span>' . esc_html( $name ) . '</span>';
				$html .= '<time datetime="' . esc_attr( $row['date'] ) . '">'
					. esc_html( PAS_Dashboard_Core_Bridge::jalali( $row['date'] ) )
					. '</time>';
				$html .= '</a></li>';
			}
			$html .= '</ul>';
		}

		$html .= '</section>';

		/* ۲) نیازمند توجه */
		$html .= '<aside class="pvn-attention pvn-span-5 pvn-rise" style="--pui-i:2" aria-labelledby="pvn-attention-title">';
		$html .= '<h2 class="pvn-attention__title" id="pvn-attention-title">' . esc_html__( 'نیازمند توجه', 'peyvand-amn-dashboard' ) . '</h2>';

		$flags = 0;

		if ( ! PAS_Dashboard_Core_Bridge::crypto_ready() ) {
			$flags++;
			$html .= '<div class="pvn-attention__item">'
				. '<span class="pvn-attention__mark pvn-attention__mark--danger" aria-hidden="true"></span>'
				. '<span><strong>' . esc_html__( 'رمزنگاری غیرفعال', 'peyvand-amn-dashboard' ) . '</strong>'
				. esc_html__( 'یادداشت بالینی خوانده یا ثبت نمی‌شود.', 'peyvand-amn-dashboard' )
				. '</span></div>';
		}

		if ( $review > 0 ) {
			$flags++;
			$html .= '<div class="pvn-attention__item">'
				. '<span class="pvn-attention__mark" aria-hidden="true"></span>'
				. '<span><strong>' . esc_html( sprintf( /* translators: %d: count */ __( '%d تعارض هویت', 'peyvand-amn-dashboard' ), $review ) ) . '</strong>'
				. esc_html__( 'نیازمند بازبینی انسانی در رجیستری مراجع.', 'peyvand-amn-dashboard' )
				. '</span></div>';
		}

		if ( ! PAS_Dashboard_Core_Bridge::has_case_file() ) {
			$flags++;
			$html .= '<div class="pvn-attention__item">'
				. '<span class="pvn-attention__mark" aria-hidden="true"></span>'
				. '<span><strong>' . esc_html__( 'پروندهٔ بالینی در دسترس نیست', 'peyvand-amn-dashboard' ) . '</strong>'
				. esc_html__( 'افزونهٔ پرونده غیرفعال است یا بارگذاری نشده.', 'peyvand-amn-dashboard' )
				. '</span></div>';
		}

		if ( ! PAS_Dashboard_Core_Bridge::sms_available() ) {
			$flags++;
			$html .= '<div class="pvn-attention__item">'
				. '<span class="pvn-attention__mark pvn-attention__mark--info" aria-hidden="true"></span>'
				. '<span><strong>' . esc_html__( 'پل پیامک فعال نیست', 'peyvand-amn-dashboard' ) . '</strong>'
				. esc_html__( 'یادآوری پیامکی نوبت ارسال نمی‌شود.', 'peyvand-amn-dashboard' )
				. '</span></div>';
		}

		if ( $flags === 0 ) {
			$html .= '<p class="pui-blank__text">' . esc_html__( 'همهٔ سرویس‌های لازم فعال‌اند و موردی برای رسیدگی ثبت نشده است.', 'peyvand-amn-dashboard' ) . '</p>';
		}

		$html .= '</aside>';

		/* ۳) پرونده‌های اخیر */
		$html .= '<section class="pas-card pvn-span-8 pvn-rise" style="--pui-i:3">';
		$html .= '<h2 class="pas-card-title">' . esc_html__( 'پرونده‌های اخیر', 'peyvand-amn-dashboard' ) . '</h2>';

		if ( empty( $recent ) ) {
			$html .= '<p class="pas-empty">' . esc_html__( 'هنوز پرونده‌ای به شما تخصیص داده نشده است.', 'peyvand-amn-dashboard' ) . '</p>';
		} else {
			$html .= '<div class="pas-table-wrap"><table class="pas-table"><thead><tr>';
			$html .= '<th>' . esc_html__( 'مراجع', 'peyvand-amn-dashboard' ) . '</th>';
			$html .= '<th>' . esc_html__( 'شناسه', 'peyvand-amn-dashboard' ) . '</th>';
			$html .= '<th>' . esc_html__( 'وضعیت', 'peyvand-amn-dashboard' ) . '</th>';
			$html .= '<th>' . esc_html__( 'آخرین به‌روزرسانی', 'peyvand-amn-dashboard' ) . '</th>';
			$html .= '<th><span class="screen-reader-text">' . esc_html__( 'عملیات', 'peyvand-amn-dashboard' ) . '</span></th>';
			$html .= '</tr></thead><tbody>';

			foreach ( $recent as $case ) {
				$case_id = (int) ( $case->id ?? 0 );
				$name    = (string) ( $case->full_name ?? '' );
				$name    = $name !== '' ? $name : __( 'بدون نام', 'peyvand-amn-dashboard' );
				$status  = (string) ( $case->status ?? '' );

				$html .= '<tr>';
				$html .= '<td data-label="' . esc_attr__( 'مراجع', 'peyvand-amn-dashboard' ) . '">' . esc_html( $name ) . '</td>';
				$html .= '<td data-label="' . esc_attr__( 'شناسه', 'peyvand-amn-dashboard' ) . '" class="pui-num">#' . esc_html( (string) $case_id ) . '</td>';
				$html .= '<td data-label="' . esc_attr__( 'وضعیت', 'peyvand-amn-dashboard' ) . '">' . $this->status_badge( $status ) . '</td>';
				$html .= '<td data-label="' . esc_attr__( 'آخرین به‌روزرسانی', 'peyvand-amn-dashboard' ) . '">' . esc_html( PAS_Dashboard_Core_Bridge::jalali( (string) ( $case->updated_at ?? '' ) ) ) . '</td>';
				$html .= '<td data-label="' . esc_attr__( 'عملیات', 'peyvand-amn-dashboard' ) . '"><a class="pas-btn" href="' . esc_url( $this->case_url( $case_id ) ) . '">' . esc_html__( 'باز کردن', 'peyvand-amn-dashboard' ) . '</a></td>';
				$html .= '</tr>';
			}

			$html .= '</tbody></table></div>';
		}

		$html .= '</section>';

		/* ۴) وضعیت اکوسیستم */
		$html .= '<section class="pas-card pvn-span-4 pvn-rise" style="--pui-i:4">';
		$html .= '<h2 class="pas-card-title">' . esc_html__( 'وضعیت اکوسیستم', 'peyvand-amn-dashboard' ) . '</h2>';
		$html .= '<ul class="pas-list">';
		$html .= $this->status_line( __( 'هسته‌ی مشترک', 'peyvand-amn-dashboard' ), PAS_Dashboard_Core_Bridge::has_core() );
		$html .= $this->status_line( __( 'پرونده‌های بالینی', 'peyvand-amn-dashboard' ), PAS_Dashboard_Core_Bridge::has_case_file() );
		$html .= $this->status_line( __( 'کلید رمزنگاری', 'peyvand-amn-dashboard' ), PAS_Dashboard_Core_Bridge::crypto_ready() );
		$html .= $this->status_line( __( 'پل پیامک', 'peyvand-amn-dashboard' ), PAS_Dashboard_Core_Bridge::sms_available() );
		$html .= '</ul>';
		$html .= $this->unavailable( __( 'این وضعیت‌ها از رجیستری سلامت هسته و تنظیمات واقعی خوانده می‌شوند؛ وضعیت نمایشی نیست.', 'peyvand-amn-dashboard' ) );
		$html .= '</section>';

		$html .= '</div>'; // .pvn-deck

		return $html;
	}

	private function case_url( $case_id ) {
		return add_query_arg(
			array(
				'page'    => PAS_DASHBOARD_SLUG,
				'module'  => 'case_files',
				'case_id' => (int) $case_id,
			),
			admin_url( 'admin.php' )
		);
	}

	private function stat( $value, $label ) {
		return '<div class="pas-stat"><span class="pas-stat-value pui-num">' . esc_html( (string) (int) $value ) . '</span>'
			. '<span class="pas-stat-label">' . esc_html( $label ) . '</span></div>';
	}

	private function status_badge( $status ) {
		$status = trim( (string) $status );
		$tone   = '';

		if ( $status !== '' && strpos( $status, 'فعال' ) !== false ) {
			$tone = ' pas-badge-good';
		} elseif ( $status !== '' && strpos( $status, 'توقف' ) !== false ) {
			$tone = ' pas-badge-warn';
		}

		$label = $status !== '' ? $status : __( 'نامشخص', 'peyvand-amn-dashboard' );

		return '<span class="pas-badge' . esc_attr( $tone ) . '">' . esc_html( $label ) . '</span>';
	}

	private function status_line( $label, $ok ) {
		$badge = $ok
			? '<span class="pas-badge pas-badge-good">' . esc_html__( 'فعال', 'peyvand-amn-dashboard' ) . '</span>'
			: '<span class="pas-badge pas-badge-warn">' . esc_html__( 'غیرفعال', 'peyvand-amn-dashboard' ) . '</span>';

		return '<li><span>' . esc_html( $label ) . '</span> ' . $badge . '</li>';
	}
}
