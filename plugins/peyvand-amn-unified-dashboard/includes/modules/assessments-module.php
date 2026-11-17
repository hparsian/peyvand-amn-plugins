<?php
/**
 * ماژول ۴ — آزمون‌ها.
 *
 * فقط فهرست آزمون‌ها از رجیستری عمومی خوانده می‌شود. نتیجه/نمره از داشبورد
 * خوانده نمی‌شود، چون API عمومی خواندن نتیجه در افزونه‌ی آزمون‌ها وجود ندارد
 * و دسترسی مستقیم به جدول آن ممنوع است.
 */

defined( 'ABSPATH' ) || exit;

final class PAS_Dashboard_Module_Assessments extends PAS_Dashboard_Module {

	public function __construct() {
		$this->id          = 'assessments';
		$this->title       = __( 'آزمون‌ها', 'peyvand-amn-dashboard' );
		$this->description = __( 'فهرست آزمون‌های ثبت‌شده در افزونه‌ی ارزیابی.', 'peyvand-amn-dashboard' );
	}

	public function render() {
		$tests = PAS_Dashboard_Core_Bridge::assessments();

		$html = '';

		if ( ! PAS_Dashboard_Core_Bridge::assessments_available() ) {
			$html .= $this->unavailable( __( 'افزونه‌ی آزمون‌ها فعال نیست یا رجیستری عمومی آن در دسترس نیست.', 'peyvand-amn-dashboard' ) );
		}

		$html .= $this->card_open( __( 'کاتالوگ آزمون‌ها', 'peyvand-amn-dashboard' ) );

		if ( empty( $tests ) ) {
			$html .= '<p class="pas-empty">' . esc_html__( 'آزمونی یافت نشد.', 'peyvand-amn-dashboard' ) . '</p>';
		} else {
			$html .= '<div class="pas-table-wrap"><table class="pas-table"><thead><tr>';
			$html .= '<th>' . esc_html__( 'شناسه', 'peyvand-amn-dashboard' ) . '</th>';
			$html .= '<th>' . esc_html__( 'نام آزمون', 'peyvand-amn-dashboard' ) . '</th>';
			$html .= '<th>' . esc_html__( 'دسته', 'peyvand-amn-dashboard' ) . '</th>';
			$html .= '</tr></thead><tbody>';

			foreach ( $tests as $test ) {
				$html .= '<tr>';
				$html .= '<td><code>' . esc_html( (string) $test['id'] ) . '</code></td>';
				$html .= '<td>' . esc_html( (string) $test['name'] ) . '</td>';
				$html .= '<td>' . esc_html( (string) $test['category'] ) . '</td>';
				$html .= '</tr>';
			}

			$html .= '</tbody></table></div>';
			$html .= '<p class="pas-muted">' . esc_html( sprintf(
				/* translators: %d: count */
				__( 'مجموعاً %d آزمون ثبت شده است.', 'peyvand-amn-dashboard' ),
				count( $tests )
			) ) . '</p>';
		}

		$html .= $this->card_close();

		$html .= $this->notice(
			'info',
			__( 'نمایش نتیجه و نمره‌ی آزمون‌ها در این داشبورد عمداً غیرفعال است: افزونه‌ی آزمون‌ها API عمومی خواندن نتیجه ندارد و دسترسی مستقیم به جدول آن مجاز نیست. برای مشاهده‌ی گزارش‌ها از صفحه‌ی «گزارش‌ها»ی همان افزونه استفاده کنید.', 'peyvand-amn-dashboard' )
		);

		return $html;
	}
}
