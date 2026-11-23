<?php
/**
 * ماژول ۲ — مراجعان (روی رجیستری هویت هسته؛ بدون پایگاه‌داده‌ی موازی).
 */

defined( 'ABSPATH' ) || exit;

final class PAS_Dashboard_Module_Patients extends PAS_Dashboard_Module {

	public function __construct() {
		$this->id          = 'patients';
		$this->title       = __( 'مراجعان', 'peyvand-amn-dashboard' );
		$this->description = __( 'جست‌وجو در رجیستری هویت مراجعان (شناسه‌ی یکتا، بدون پایگاه‌داده‌ی جداگانه).', 'peyvand-amn-dashboard' );
	}

	public function render() {
		if ( ! PAS_Dashboard_Core_Bridge::has_core() ) {
			return $this->unavailable( __( 'افزونه‌ی هسته‌ی مشترک فعال نیست؛ رجیستری هویت در دسترس نیست.', 'peyvand-amn-dashboard' ) );
		}

		$client_id = isset( $_GET['client_id'] ) ? PAS_Dashboard_Validation::text( sanitize_text_field( wp_unslash( $_GET['client_id'] ) ), 64 ) : '';

		if ( $client_id !== '' ) {
			return $this->render_detail( $client_id );
		}

		return $this->render_search();
	}

	private function render_search() {
		$query = isset( $_GET['q'] ) ? PAS_Dashboard_Validation::text( sanitize_text_field( wp_unslash( $_GET['q'] ) ), 190 ) : '';
		$type  = isset( $_GET['type'] ) ? PAS_Dashboard_Validation::choice( sanitize_key( wp_unslash( $_GET['type'] ) ), array( 'client_id', 'mobile', 'email' ), 'client_id' ) : 'client_id';

		$html  = $this->card_open( __( 'جست‌وجوی مراجع', 'peyvand-amn-dashboard' ) );
		$html .= '<form method="get" class="pas-filter-form">';
		$html .= '<input type="hidden" name="page" value="' . esc_attr( PAS_DASHBOARD_SLUG ) . '" />';
		$html .= '<input type="hidden" name="module" value="' . esc_attr( $this->id ) . '" />';
		$html .= '<div class="pas-form-row"><label for="pas-q">' . esc_html__( 'عبارت جست‌وجو', 'peyvand-amn-dashboard' ) . '</label>';
		$html .= '<input class="pas-input" type="search" id="pas-q" name="q" value="' . esc_attr( $query ) . '" /></div>';
		$html .= '<div class="pas-form-row"><label for="pas-type">' . esc_html__( 'نوع شناسه', 'peyvand-amn-dashboard' ) . '</label>';
		$html .= '<select class="pas-select" id="pas-type" name="type">';
		foreach ( array(
			'client_id' => __( 'شناسه‌ی مراجع', 'peyvand-amn-dashboard' ),
			'mobile'    => __( 'موبایل', 'peyvand-amn-dashboard' ),
			'email'     => __( 'ایمیل', 'peyvand-amn-dashboard' ),
		) as $key => $label ) {
			$html .= '<option value="' . esc_attr( $key ) . '">' . selected( $type, $key, false ) . '>' . esc_html( $label ) . '</option>';
		}
		$html .= '</select></div>';
		$html .= '<button class="pas-btn pas-btn-primary" type="submit">' . esc_html__( 'جست‌وجو', 'peyvand-amn-dashboard' ) . '</button>';
		$html .= '</form>';
		$html .= '<p class="pas-muted">' . esc_html__( 'جست‌وجو بر پایه‌ی شناسه‌ی یکتا انجام می‌شود؛ شماره‌ی موبایل شناسه‌ی اصلی نیست.', 'peyvand-amn-dashboard' ) . '</p>';
		$html .= $this->card_close();

		if ( $query !== '' ) {
			if ( $type === 'client_id' ) {
				$client = PAS_Dashboard_Core_Bridge::client_get( $query );
			} else {
				$client = PAS_Dashboard_Core_Bridge::client_by_identifier( $type, $query );
			}

			$html .= $this->card_open( __( 'نتیجه', 'peyvand-amn-dashboard' ) );

			if ( ! $client ) {
				$html .= '<p class="pas-empty">' . esc_html__( 'مراجعی با این شناسه یافت نشد.', 'peyvand-amn-dashboard' ) . '</p>';
			} else {
				$html .= '<p>' . esc_html__( 'شناسه:', 'peyvand-amn-dashboard' ) . ' <code>' . esc_html( (string) $client['client_id'] ) . '</code></p>';
				$html .= '<p>' . esc_html__( 'نام نمایشی:', 'peyvand-amn-dashboard' ) . ' ' . esc_html( (string) ( $client['display_name'] ?? '—' ) ) . '</p>';
				$html .= '<p><a class="pas-btn" href="' . esc_url( $this->url( array( 'client_id' => (string) $client['client_id'] ) ) ) . '">' . esc_html__( 'مشاهده‌ی جزئیات', 'peyvand-amn-dashboard' ) . '</a></p>';
			}

			$html .= $this->card_close();
		}

		$clients = PAS_Dashboard_Core_Bridge::clients_sample( 50 );

		$html .= $this->card_open( __( 'آخرین مراجعان ایندکس‌شده', 'peyvand-amn-dashboard' ) );

		if ( empty( $clients ) ) {
			$html .= '<p class="pas-empty">' . esc_html__( 'رجیستری خالی است یا هنوز بازسازی نشده است.', 'peyvand-amn-dashboard' ) . '</p>';
		} else {
			$html .= '<div class="pas-table-wrap"><table class="pas-table"><thead><tr>';
			$html .= '<th>' . esc_html__( 'شناسه', 'peyvand-amn-dashboard' ) . '</th>';
			$html .= '<th>' . esc_html__( 'نام نمایشی', 'peyvand-amn-dashboard' ) . '</th>';
			$html .= '<th>' . esc_html__( 'وضعیت', 'peyvand-amn-dashboard' ) . '</th>';
			$html .= '<th>' . esc_html__( 'شناسه‌ها', 'peyvand-amn-dashboard' ) . '</th>';
			$html .= '<th>' . esc_html__( 'عملیات', 'peyvand-amn-dashboard' ) . '</th>';
			$html .= '</tr></thead><tbody>';

			foreach ( $clients as $client ) {
				$html .= '<tr>';
				$html .= '<td data-label="' . esc_attr__( 'شناسه', 'peyvand-amn-dashboard' ) . '"><code>' . esc_html( (string) ( $client['client_id'] ?? '' ) ) . '</code></td>';
				$html .= '<td data-label="' . esc_attr__( 'نام نمایشی', 'peyvand-amn-dashboard' ) . '">' . esc_html( (string) ( $client['display_name'] ?? '—' ) ) . '</td>';
				$html .= '<td data-label="' . esc_attr__( 'وضعیت', 'peyvand-amn-dashboard' ) . '">' . $this->status_badge( (string) ( $client['status'] ?? '' ) ) . '</td>';
				$html .= '<td data-label="' . esc_attr__( 'شناسه‌ها', 'peyvand-amn-dashboard' ) . '">' . esc_html( (string) ( $client['identifiers_text'] ?? '' ) ) . '</td>';
				$html .= '<td data-label="' . esc_attr__( 'عملیات', 'peyvand-amn-dashboard' ) . '"><a class="pas-btn" href="' . esc_url( $this->url( array( 'client_id' => (string) ( $client['client_id'] ?? '' ) ) ) ) . '">' . esc_html__( 'جزئیات', 'peyvand-amn-dashboard' ) . '</a></td>';
				$html .= '</tr>';
			}

			$html .= '</tbody></table></div>';
			$html .= '<p class="pas-muted">' . esc_html__( 'حداکثر ۵۰ رکورد نمایش داده می‌شود؛ برای جست‌وجوی دقیق از فرم بالا استفاده کنید.', 'peyvand-amn-dashboard' ) . '</p>';
		}

		$html .= $this->card_close();

		return $html;
	}

	private function render_detail( $client_id ) {
		$client = PAS_Dashboard_Core_Bridge::client_get( $client_id );

		if ( ! $client ) {
			return $this->notice( 'warning', __( 'مراجع یافت نشد.', 'peyvand-amn-dashboard' ) );
		}

		$html  = $this->card_open( __( 'شناسنامه‌ی مراجع', 'peyvand-amn-dashboard' ) );
		$html .= '<p><strong>' . esc_html__( 'شناسه‌ی یکتا:', 'peyvand-amn-dashboard' ) . '</strong> <code>' . esc_html( (string) $client['client_id'] ) . '</code></p>';
		$html .= '<p><strong>' . esc_html__( 'نام نمایشی:', 'peyvand-amn-dashboard' ) . '</strong> ' . esc_html( (string) ( $client['display_name'] ?? '—' ) ) . '</p>';
		$html .= '<p><strong>' . esc_html__( 'وضعیت:', 'peyvand-amn-dashboard' ) . '</strong> ' . $this->status_badge( (string) ( $client['status'] ?? '' ) ) . '</p>';
		$html .= '<p><strong>' . esc_html__( 'تعداد تعارض:', 'peyvand-amn-dashboard' ) . '</strong> ' . esc_html( (string) (int) ( $client['conflict_count'] ?? 0 ) ) . '</p>';
		$html .= '<p><strong>' . esc_html__( 'تعداد منبع:', 'peyvand-amn-dashboard' ) . '</strong> ' . esc_html( (string) (int) ( $client['sources_count'] ?? 0 ) ) . '</p>';
		$html .= '<p class="pas-muted">' . esc_html__( 'این داشبورد هیچ فیلد بالینی از رجیستری نمی‌خواند؛ فقط شناسه، نام نمایشی و وضعیت.', 'peyvand-amn-dashboard' ) . '</p>';
		$html .= $this->card_close();

		$html .= $this->card_open( __( 'شناسه‌های ثبت‌شده', 'peyvand-amn-dashboard' ) );

		$identifiers = isset( $client['identifiers'] ) && is_array( $client['identifiers'] ) ? $client['identifiers'] : array();

		if ( empty( $identifiers ) ) {
			$html .= '<p class="pas-empty">' . esc_html__( 'شناسه‌ای ثبت نشده است.', 'peyvand-amn-dashboard' ) . '</p>';
		} else {
			$html .= '<ul class="pas-list">';
			foreach ( $identifiers as $identifier ) {
				$type  = (string) ( $identifier['type'] ?? '' );
				$value = (string) ( $identifier['value'] ?? '' );

				if ( $type === 'mobile' ) {
					$value = PAS_Dashboard_Core_Bridge::mask_mobile( $value );
				}

				$html .= '<li><strong>' . esc_html( $type ) . ':</strong> <code>' . esc_html( $value ) . '</code></li>';
			}
			$html .= '</ul>';
		}

		$html .= $this->card_close();
		$html .= '<p><a class="pas-btn" href="' . esc_url( $this->url() ) . '">' . esc_html__( 'بازگشت به فهرست', 'peyvand-amn-dashboard' ) . '</a></p>';

		return $html;
	}

	private function status_badge( $status ) {
		$class = $status === 'review' ? 'pas-badge-warn' : ( $status === 'active' ? 'pas-badge-good' : '' );

		return '<span class="pas-badge ' . esc_attr( $class ) . '">' . esc_html( $status !== '' ? $status : '—' ) . '</span>';
	}
}
