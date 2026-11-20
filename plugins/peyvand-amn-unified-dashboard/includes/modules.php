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

	/**
	 * آیکون SVG درون‌خطی ماژول‌ها — سیستم آیکون اختصاصی، بدون فونت آیکون
	 * عمومی و بدون درخواست شبکه. stroke=currentColor تا با رنگ متن هم‌راه شود.
	 */
	public static function svg( $id, $size = 18, $class = '' ) {
		$paths = array(
			'overview'       => '<circle cx="12" cy="12" r="8.6"/><path d="M15.6 8.4l-2.1 5.1-5.1 2.1 2.1-5.1z"/>',
			'patients'       => '<circle cx="9" cy="8" r="3.2"/><path d="M3.5 19.5v-1.2A4.3 4.3 0 0 1 7.8 14h2.4a4.3 4.3 0 0 1 4.3 4.3v1.2"/><path d="M16.5 5.4a3 3 0 0 1 0 5.6"/><path d="M17.4 14.2a4 4 0 0 1 3.1 3.9v1.4"/>',
			'case_files'     => '<path d="M3.4 7.6A2.6 2.6 0 0 1 6 5h3.1l2.1 2.6h6.8A2.6 2.6 0 0 1 20.6 10v6.6a2.6 2.6 0 0 1-2.6 2.6H6a2.6 2.6 0 0 1-2.6-2.6z"/>',
			'assessments'    => '<path d="M9 4.6h6"/><rect x="5" y="6" width="14" height="14.4" rx="2.4"/><path d="M9.2 13.4l1.9 1.9 3.7-3.9"/>',
			'appointments'   => '<rect x="3.6" y="5.4" width="16.8" height="14.6" rx="2.6"/><path d="M8 3.4v4M16 3.4v4M3.6 10.2h16.8"/>',
			'reports'        => '<path d="M4 19V10M9.4 19V5M14.7 19v-6.4M20 19v-3.4"/>',
			'communications' => '<rect x="3.4" y="6" width="17.2" height="12" rx="2.4"/><path d="M4 7.4l8 5.6 8-5.6"/>',
			'notifications'  => '<path d="M12 4.2a5 5 0 0 1 5 5v3.1l1.5 2.7H5.5L7 12.3V9.2a5 5 0 0 1 5-5z"/><path d="M10 18.6a2 2 0 0 0 4 0"/>',
			'system_health'  => '<path d="M3.2 12h3.9l1.9-4.6 3.2 9.2 2-4.6h6.6"/>',
			'settings'       => '<path d="M4 7.4h16M4 16.6h16"/><circle cx="9.2" cy="7.4" r="2.1"/><circle cx="15.2" cy="16.6" r="2.1"/>',
			'followup'       => '<path d="M12 4.2a7.8 7.8 0 1 0 7.8 7.8"/><path d="M12 8.2a3.8 3.8 0 1 0 3.8 3.8"/><path d="M15.8 12l4.6-4.6"/>',
			'ecosystem'      => '<circle cx="12" cy="5.4" r="2.1"/><circle cx="5.6" cy="17.4" r="2.1"/><circle cx="18.4" cy="17.4" r="2.1"/><path d="M12 7.5v4.4M12 11.9L7 15.6M12 11.9l5 3.7"/>',
			'audit'          => '<path d="M12 3.4l6.6 2.8v5.6c0 3.9-2.7 6.9-6.6 8.8-3.9-1.9-6.6-4.9-6.6-8.8V6.2z"/><path d="M9.4 12.1l1.9 1.9 3.5-3.7"/>',
			'arrow'          => '<path d="M14.5 6.5L9 12l5.5 5.5"/>',
			'lock'           => '<rect x="4.8" y="10.4" width="14.4" height="9.4" rx="2.2"/><path d="M8.2 10.4V8.2a3.8 3.8 0 0 1 7.6 0v2.2"/>',
		);

		if ( ! isset( $paths[ $id ] ) ) {
			return '';
		}

		$size  = (int) $size;
		$class = trim( 'pvn-icon ' . $class );

		return '<svg class="' . esc_attr( $class ) . '" width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24"'
			. ' fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"'
			. ' aria-hidden="true" focusable="false">' . $paths[ $id ] . '</svg>';
	}

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
			'followup'       => 'dashicons-update',
			'ecosystem'      => 'dashicons-networking',
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
