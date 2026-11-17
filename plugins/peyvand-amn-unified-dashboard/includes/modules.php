<?php
/**
 * کلاس پایه‌ی ماژول‌ها — منطق مشترک نمایش.
 */

defined( 'ABSPATH' ) || exit;

abstract class PAS_Dashboard_Module {

	/** @var string */
	public $id = '';

	/** @var string */
	public $title = '';

	/** @var string */
	public $description = '';

	/** هر ماژول محتوای خودش را برمی‌گرداند (escape شده). */
	abstract public function render();

	/** آیکون داشبورد برای هر ماژول (کلاس dashicons). */
	public static function icon_for( $id ) {
		$icons = array(
			'overview'       => 'dashicons-chart-pie',
			'patients'       => 'dashicons-groups',
			'case_files'     => 'dashicons-portfolio',
			'assessments'    => 'dashicons-clipboard',
			'appointments'   => 'dashicons-calendar-alt',
			'reports'        => 'dashicons-chart-line',
			'communications' => 'dashicons-email-alt',
			'notifications'  => 'dashicons-bell',
			'system_health'  => 'dashicons-heart',
			'settings'       => 'dashicons-admin-generic',
			'audit'          => 'dashicons-shield-alt',
		);

		return isset( $icons[ $id ] ) ? $icons[ $id ] : 'dashicons-marker';
	}

	/** آیکون همین ماژول */
	public function icon() {
		return self::icon_for( $this->id );
	}

	/** آدرس صفحه‌ی همین ماژول */
	public function url( array $args = array() ) {
		$args = array_merge(
			array(
				'page'   => PAS_DASHBOARD_SLUG,
				'module' => $this->id,
			),
			$args
		);

		return add_query_arg( $args, admin_url( 'admin.php' ) );
	}

	public function notice( $type, $message ) {
		$type = in_array( $type, array( 'success', 'warning', 'error', 'info' ), true ) ? $type : 'info';

		return '<div class="pas-notice pas-notice-' . esc_attr( $type ) . '"><p>' . esc_html( $message ) . '</p></div>';
	}

	public function unavailable( $reason ) {
		return $this->notice( 'warning', $reason );
	}

	public function card_open( $title = '' ) {
		$html = '<section class="pas-card">';
		if ( $title !== '' ) {
			$html .= '<h2 class="pas-card-title">' . esc_html( $title ) . '</h2>';
		}

		return $html;
	}

	public function card_close() {
		return '</section>';
	}

	/** صفحه‌بندی ساده (لینک‌محور، بدون AJAX) */
	public function pagination( $total, $per_page, $current ) {
		$total = (int) $total;
		$per_page = max( 1, (int) $per_page );
		$pages = (int) ceil( $total / $per_page );

		if ( $pages <= 1 ) {
			return '';
		}

		$current = max( 1, min( $pages, (int) $current ) );

		$html = '<nav class="pas-pagination" aria-label="' . esc_attr__( 'صفحه‌بندی', 'peyvand-amn-dashboard' ) . '">';

		if ( $current > 1 ) {
			$html .= '<a class="pas-page" href="' . esc_url( $this->url( array( 'paged' => $current - 1 ) ) ) . '">' . esc_html__( 'قبلی', 'peyvand-amn-dashboard' ) . '</a>';
		}

		$html .= '<span class="pas-page-current">' . esc_html( sprintf( '%d / %d', $current, $pages ) ) . '</span>';

		if ( $current < $pages ) {
			$html .= '<a class="pas-page" href="' . esc_url( $this->url( array( 'paged' => $current + 1 ) ) ) . '">' . esc_html__( 'بعدی', 'peyvand-amn-dashboard' ) . '</a>';
		}

		$html .= '</nav>';

		return $html;
	}

	public function per_page() {
		$per_page = (int) PAS_Dashboard_Permissions::setting( 'per_page', 20 );

		return max( 5, min( PAS_DASHBOARD_MAX_PER_PAGE, $per_page ) );
	}

	public function slice_for_page( array $rows, $per_page, $page ) {
		$offset = ( max( 1, (int) $page ) - 1 ) * max( 1, (int) $per_page );

		return array_slice( $rows, $offset, max( 1, (int) $per_page ) );
	}
}
