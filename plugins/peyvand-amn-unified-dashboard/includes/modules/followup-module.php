<?php
/**
 * ماژول پیگیری — لیدهای فرم/کوییز و نظرسنجی/توصیه‌نامه‌های پنل مراجعان.
 *
 * نوشتن فقط از Action Bus و API عمومی مالک انجام می‌شود. لیدها با شمارهٔ
 * موبایل به پرونده گره می‌خورند، پس دامنهٔ پرونده اعمال می‌شود؛ نظرسنجی‌ها
 * ناشناس‌اند و مدیریتشان در سطح کلینیک است.
 */

defined( 'ABSPATH' ) || exit;

final class PAS_Dashboard_Module_Followup extends PAS_Dashboard_Module {

	public function __construct() {
		$this->id          = 'followup';
		$this->title       = __( 'پیگیری و نظرسنجی', 'peyvand-amn-dashboard' );
		$this->description = __( 'پیگیری لیدهای کوییز و مدیریت نظرسنجی و توصیه‌نامه‌های عمومی.', 'peyvand-amn-dashboard' );
	}

	public function render() {
		if ( ! PAS_Dashboard_Adapter_Followup::available() ) {
			return $this->unavailable( __( 'قرارداد لید و نظرسنجی در افزونهٔ پنل مراجعان در دسترس نیست (نسخهٔ ۱.۱۳.۲.۷+).', 'peyvand-amn-dashboard' ) );
		}

		return $this->render_leads() . $this->render_surveys();
	}

	private function render_leads() {
		$can_all = PAS_Dashboard_Core_Bridge::viewer_can_see_all_cases();
		$leads   = array();
		$stats   = array( 'total' => 0, '24h' => 0, '72h' => 0, 'booked' => 0 );

		foreach ( PAS_Dashboard_Adapter_Followup::leads( 200 ) as $lead ) {
			if ( ! is_object( $lead ) || empty( $lead->id ) ) {
				continue;
			}

			if ( ! $can_all && ! PAS_Dashboard_Adapter_Portal::can_view_phone( (string) ( $lead->phone ?? '' ) ) ) {
				continue;
			}

			$leads[] = $lead;
			$stats['total']++;

			if ( ! empty( $lead->booked ) ) {
				$stats['booked']++;
			}

			$stage = PAS_Dashboard_Adapter_Followup::needed_stage( $lead );

			if ( '24h' === $stage ) {
				$stats['24h']++;
			} elseif ( '72h' === $stage ) {
				$stats['72h']++;
			}
		}

		$html  = $this->card_open( __( 'پیگیری لیدهای کوییز', 'peyvand-amn-dashboard' ) );

		if ( ! $can_all ) {
			$html .= '<p class="pas-muted">' . esc_html__( 'فقط لیدهای مراجعان پرونده‌های شما نمایش داده می‌شود.', 'peyvand-amn-dashboard' ) . '</p>';
		}

		$html .= '<div class="pas-grid">';
		$html .= $this->stat( (string) $stats['total'], __( 'کل لیدها', 'peyvand-amn-dashboard' ) );
		$html .= $this->stat( (string) $stats['24h'], __( 'نیازمند پیگیری ۲۴ ساعته', 'peyvand-amn-dashboard' ) );
		$html .= $this->stat( (string) $stats['72h'], __( 'نیازمند پیگیری ۷۲ ساعته', 'peyvand-amn-dashboard' ) );
		$html .= $this->stat( (string) $stats['booked'], __( 'نوبت‌گرفته', 'peyvand-amn-dashboard' ) );
		$html .= '</div>';

		if ( empty( $leads ) ) {
			$html .= '<p class="pas-empty">' . esc_html__( 'لیدی برای پیگیری ثبت نشده است.', 'peyvand-amn-dashboard' ) . '</p>';
			$html .= $this->card_close();

			return $html;
		}

		$html .= '<div class="pas-table-wrap"><table class="pas-table"><thead><tr>';
		$html .= '<th>' . esc_html__( 'تاریخ', 'peyvand-amn-dashboard' ) . '</th>';
		$html .= '<th>' . esc_html__( 'موبایل', 'peyvand-amn-dashboard' ) . '</th>';
		$html .= '<th>' . esc_html__( 'منبع', 'peyvand-amn-dashboard' ) . '</th>';
		$html .= '<th>' . esc_html__( 'موضوع', 'peyvand-amn-dashboard' ) . '</th>';
		$html .= '<th>' . esc_html__( 'امتیاز / باند', 'peyvand-amn-dashboard' ) . '</th>';
		$html .= '<th>' . esc_html__( 'وضعیت', 'peyvand-amn-dashboard' ) . '</th>';
		$html .= '<th>' . esc_html__( 'عملیات', 'peyvand-amn-dashboard' ) . '</th>';
		$html .= '</tr></thead><tbody>';

		foreach ( $leads as $lead ) {
			$id            = (int) $lead->id;
			$stage         = PAS_Dashboard_Adapter_Followup::needed_stage( $lead );
			$booked        = ! empty( $lead->booked );
			$followed_any  = ! empty( $lead->followed_up_24h ) || ! empty( $lead->followed_up_72h );

			$html .= '<tr>';
			$html .= '<td data-label="' . esc_attr__( 'تاریخ', 'peyvand-amn-dashboard' ) . '">' . esc_html( PAS_Dashboard_Core_Bridge::jalali_datetime( (string) ( $lead->created_at ?? '' ) ) ) . '</td>';
			$html .= '<td data-label="' . esc_attr__( 'موبایل', 'peyvand-amn-dashboard' ) . '"><span dir="ltr">' . esc_html( PAS_Dashboard_Core_Bridge::mask_mobile( (string) ( $lead->phone ?? '' ) ) ) . '</span></td>';
			$html .= '<td data-label="' . esc_attr__( 'منبع', 'peyvand-amn-dashboard' ) . '">' . esc_html( (string) ( $lead->source ?? '—' ) ) . '</td>';
			$html .= '<td data-label="' . esc_attr__( 'موضوع', 'peyvand-amn-dashboard' ) . '">' . esc_html( (string) ( $lead->quiz_topic ?? '—' ) ) . '</td>';
			$html .= '<td data-label="' . esc_attr__( 'امتیاز / باند', 'peyvand-amn-dashboard' ) . '">' . esc_html( (string) (int) ( $lead->quiz_score ?? 0 ) ) . ' / ' . esc_html( (string) ( $lead->quiz_band ?? '—' ) ) . '</td>';

			$html .= '<td data-label="' . esc_attr__( 'وضعیت', 'peyvand-amn-dashboard' ) . '">';
			if ( $booked ) {
				$html .= '<span class="pas-badge pas-badge-good">' . esc_html__( 'نوبت‌گرفته', 'peyvand-amn-dashboard' ) . '</span>';
			} elseif ( $stage !== '' ) {
				$html .= '<span class="pas-badge pas-badge-warn">' . esc_html( '24h' === $stage ? __( 'نیازمند پیگیری ۲۴ ساعته', 'peyvand-amn-dashboard' ) : __( 'نیازمند پیگیری ۷۲ ساعته', 'peyvand-amn-dashboard' ) ) . '</span>';
			} elseif ( $followed_any ) {
				$html .= '<span class="pas-badge pas-badge-good">' . esc_html__( 'پیگیری‌شده', 'peyvand-amn-dashboard' ) . '</span>';
			} else {
				$html .= '<span class="pas-muted">' . esc_html__( 'بدون پیگیری لازم', 'peyvand-amn-dashboard' ) . '</span>';
			}
			$html .= '</td>';

			$html .= '<td data-label="' . esc_attr__( 'عملیات', 'peyvand-amn-dashboard' ) . '">';

			if ( $stage !== '' ) {
				$html .= '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="pas-inline-form">';
				$html .= PAS_Dashboard_Actions::fields( 'lead.followup', $this->url() );
				$html .= '<input type="hidden" name="lead_id" value="' . esc_attr( (string) $id ) . '" />';
				$html .= '<input type="hidden" name="stage" value="' . esc_attr( $stage ) . '" />';
				$html .= '<button class="pas-btn pas-btn-sm" type="submit">' . esc_html( '24h' === $stage ? __( 'ثبت پیگیری ۲۴س', 'peyvand-amn-dashboard' ) : __( 'ثبت پیگیری ۷۲س', 'peyvand-amn-dashboard' ) ) . '</button>';
				$html .= '</form>';
			} else {
				$html .= '<span class="pas-muted">—</span>';
			}

			$html .= '</td>';
			$html .= '</tr>';
		}

		$html .= '</tbody></table></div>';
		$html .= '<p class="pas-muted">' . esc_html__( 'حداکثر ۲۰۰ لید آخر نمایش داده می‌شود؛ فالوآپ خودکار پیامکی از همان چرخهٔ پنل اجرا می‌شود.', 'peyvand-amn-dashboard' ) . '</p>';
		$html .= $this->card_close();

		return $html;
	}

	private function render_surveys() {
		$surveys  = PAS_Dashboard_Adapter_Followup::surveys( 100 );
		$approved = 0;

		foreach ( $surveys as $survey ) {
			if ( ! empty( $survey->approved ) ) {
				$approved++;
			}
		}

		$html  = $this->card_open( __( 'نظرسنجی و توصیه‌نامه', 'peyvand-amn-dashboard' ) );
		$html .= '<p class="pas-muted">' . esc_html__( 'نظرسنجی‌ها ناشناس ثبت می‌شوند و به پرونده گره نمی‌خورند؛ تأیید و حذف در سطح کلینیک انجام می‌شود. فقط نظرسنجی تأییدشده و دارای رضایت در صفحهٔ عمومی دیده می‌شود.', 'peyvand-amn-dashboard' ) . '</p>';

		$html .= '<div class="pas-grid">';
		$html .= $this->stat( (string) count( $surveys ), __( 'کل نظرسنجی‌ها', 'peyvand-amn-dashboard' ) );
		$html .= $this->stat( (string) $approved, __( 'تأییدشده', 'peyvand-amn-dashboard' ) );
		$html .= $this->stat( (string) ( count( $surveys ) - $approved ), __( 'در انتظار بررسی', 'peyvand-amn-dashboard' ) );
		$html .= '</div>';

		if ( empty( $surveys ) ) {
			$html .= '<p class="pas-empty">' . esc_html__( 'نظرسنجی‌ای ثبت نشده است.', 'peyvand-amn-dashboard' ) . '</p>';
			$html .= $this->card_close();

			return $html;
		}

		$html .= '<div class="pas-table-wrap"><table class="pas-table"><thead><tr>';
		$html .= '<th>' . esc_html__( 'تاریخ', 'peyvand-amn-dashboard' ) . '</th>';
		$html .= '<th>' . esc_html__( 'امتیاز', 'peyvand-amn-dashboard' ) . '</th>';
		$html .= '<th>' . esc_html__( 'پاسخ‌ها', 'peyvand-amn-dashboard' ) . '</th>';
		$html .= '<th>' . esc_html__( 'پیشنهاد', 'peyvand-amn-dashboard' ) . '</th>';
		$html .= '<th>' . esc_html__( 'وضعیت', 'peyvand-amn-dashboard' ) . '</th>';
		$html .= '<th>' . esc_html__( 'عملیات', 'peyvand-amn-dashboard' ) . '</th>';
		$html .= '</tr></thead><tbody>';

		foreach ( $surveys as $survey ) {
			$id      = (int) ( $survey->id ?? 0 );
			$rating  = (int) ( $survey->rating ?? 0 );
			$public  = PAS_Dashboard_Adapter_Followup::is_public_testimonial( $survey );
			$extras  = array();

			if ( ! empty( $survey->extra_answers ) ) {
				$decoded = json_decode( (string) $survey->extra_answers, true );
				if ( is_array( $decoded ) ) {
					$extras = $decoded;
				}
			}

			$html .= '<tr>';
			$html .= '<td data-label="' . esc_attr__( 'تاریخ', 'peyvand-amn-dashboard' ) . '">' . esc_html( PAS_Dashboard_Core_Bridge::jalali_datetime( (string) ( $survey->created_at ?? '' ) ) ) . '</td>';
			$html .= '<td data-label="' . esc_attr__( 'امتیاز', 'peyvand-amn-dashboard' ) . '">' . esc_html( $rating > 0 ? str_repeat( '★', min( 5, $rating ) ) . str_repeat( '☆', max( 0, 5 - $rating ) ) : '—' ) . '</td>';

			$html .= '<td data-label="' . esc_attr__( 'پاسخ‌ها', 'peyvand-amn-dashboard' ) . '">';
			foreach ( array( 'q1' => '۱.', 'q2' => '۲.', 'q3' => '۳.' ) as $field => $label ) {
				$value = (string) ( $survey->{$field} ?? '' );
				if ( $value !== '' ) {
					$html .= '<div><strong>' . esc_html( $label ) . '</strong> ' . esc_html( mb_strimwidth( $value, 0, 120, '…' ) ) . '</div>';
				}
			}
			foreach ( $extras as $extra ) {
				if ( ! is_array( $extra ) || (string) ( $extra['answer'] ?? '' ) === '' ) {
					continue;
				}
				$html .= '<div><strong>' . esc_html( (string) ( $extra['label'] ?? '' ) ) . ':</strong> ' . esc_html( mb_strimwidth( (string) $extra['answer'], 0, 120, '…' ) ) . '</div>';
			}
			$html .= '</td>';

			$html .= '<td data-label="' . esc_attr__( 'پیشنهاد', 'peyvand-amn-dashboard' ) . '">' . ( (string) ( $survey->suggestions ?? '' ) !== '' ? nl2br( esc_html( mb_strimwidth( (string) $survey->suggestions, 0, 200, '…' ) ) ) : '—' ) . '</td>';
			$html .= '<td data-label="' . esc_attr__( 'وضعیت', 'peyvand-amn-dashboard' ) . '">' . ( ! empty( $survey->approved )
				? '<span class="pas-badge pas-badge-good">' . esc_html__( 'تأییدشده', 'peyvand-amn-dashboard' ) . '</span>'
				: '<span class="pas-badge pas-badge-warn">' . esc_html__( 'در انتظار بررسی', 'peyvand-amn-dashboard' ) . '</span>' ) . '</td>';

			$html .= '<td data-label="' . esc_attr__( 'عملیات', 'peyvand-amn-dashboard' ) . '">';
			$html .= '<div class="pas-row-actions">';

			$html .= '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="pas-inline-form">';
			$html .= PAS_Dashboard_Actions::fields( 'survey.moderate', $this->url() );
			$html .= '<input type="hidden" name="survey_id" value="' . esc_attr( (string) $id ) . '" />';
			$html .= '<input type="hidden" name="approved" value="' . ( ! empty( $survey->approved ) ? '0' : '1' ) . '" />';
			$html .= '<button class="pas-btn pas-btn-sm" type="submit">' . esc_html( ! empty( $survey->approved ) ? __( 'لغو تأیید', 'peyvand-amn-dashboard' ) : __( 'تأیید نمایش عمومی', 'peyvand-amn-dashboard' ) ) . '</button>';
			$html .= '</form>';

			if ( $public ) {
				$html .= '<button type="button" class="pas-btn pas-btn-sm" data-pas-copy="' . esc_attr( PAS_Dashboard_Adapter_Followup::instagram_caption( (string) $survey->suggestions ) ) . '">' . esc_html__( 'کپی برای اینستاگرام', 'peyvand-amn-dashboard' ) . '</button>';
			}

			$html .= '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="pas-inline-form">';
			$html .= PAS_Dashboard_Actions::fields( 'survey.delete', $this->url() );
			$html .= '<input type="hidden" name="survey_id" value="' . esc_attr( (string) $id ) . '" />';
			$html .= '<button class="pas-btn pas-btn-sm pas-btn-danger" type="submit" data-pas-confirm="' . esc_attr__( 'این نظرسنجی برای همیشه حذف می‌شود و قابل بازگشت نیست. ادامه می‌دهید؟', 'peyvand-amn-dashboard' ) . '">' . esc_html__( 'حذف', 'peyvand-amn-dashboard' ) . '</button>';
			$html .= '</form>';

			$html .= '</div>';
			$html .= '</td>';
			$html .= '</tr>';
		}

		$html .= '</tbody></table></div>';
		$html .= '<p class="pas-muted">' . esc_html__( 'حداکثر ۱۰۰ نظرسنجی آخر نمایش داده می‌شود.', 'peyvand-amn-dashboard' ) . '</p>';
		$html .= $this->card_close();

		return $html;
	}

	private function stat( $value, $label ) {
		return '<div class="pas-stat"><span class="pas-stat-value">' . esc_html( (string) $value ) . '</span><span class="pas-stat-label">' . esc_html( (string) $label ) . '</span></div>';
	}
}
