<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * CRUD نواقص، امتیاز سلامت و آمار.
 */
class GSO_Issues {

	/**
	 * درج یا به‌روزرسانی یک ایراد بر اساس fingerprint.
	 *
	 * @param array $issue داده ایراد.
	 * @return int شناسه ردیف.
	 */
	public static function upsert( array $issue ): int {
		global $wpdb;
		$table = GSO_Install::table( 'issues' );

		$issue = wp_parse_args(
			$issue,
			array(
				'object_id'   => 0,
				'object_type' => 'post',
				'issue_type'  => 'generic',
				'severity'    => 'low',
				'title'       => '',
				'description' => '',
				'suggestion'  => '',
				'fix_type'    => '',
				'fix_payload' => array(),
			)
		);

		$object_id   = absint( $issue['object_id'] );
		$object_type = sanitize_key( $issue['object_type'] );
		$issue_type  = sanitize_key( $issue['issue_type'] );
		$severity    = in_array( $issue['severity'], array( 'critical', 'high', 'medium', 'low' ), true ) ? $issue['severity'] : 'low';
		$fingerprint = md5( $object_type . '|' . $object_id . '|' . $issue_type );
		$payload     = is_array( $issue['fix_payload'] ) ? wp_json_encode( $issue['fix_payload'] ) : (string) $issue['fix_payload'];
		$now         = current_time( 'mysql' );

		$existing = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare( "SELECT * FROM {$wpdb->prefix}gso_issues WHERE fingerprint = %s", $fingerprint )
		);

		if ( $existing ) {
			$status = $existing->status;
			if ( 'fixed' === $status ) {
				$status = 'open';
			}
			$data = array(
				'severity'    => $severity,
				'title'       => sanitize_text_field( $issue['title'] ),
				'description' => wp_kses_post( $issue['description'] ),
				'suggestion'  => wp_kses_post( $issue['suggestion'] ),
				'fix_type'    => sanitize_key( $issue['fix_type'] ),
				'status'      => $status,
				'updated_at'  => $now,
			);
			if ( '' !== $payload && '[]' !== $payload ) {
				$data['fix_payload'] = $payload;
			}
			$wpdb->update( $table, $data, array( 'id' => (int) $existing->id ) );
			return (int) $existing->id;
		}

		$wpdb->insert(
			$table,
			array(
				'fingerprint' => $fingerprint,
				'object_id'   => $object_id,
				'object_type' => $object_type,
				'issue_type'  => $issue_type,
				'severity'    => $severity,
				'title'       => sanitize_text_field( $issue['title'] ),
				'description' => wp_kses_post( $issue['description'] ),
				'suggestion'  => wp_kses_post( $issue['suggestion'] ),
				'fix_type'    => sanitize_key( $issue['fix_type'] ),
				'fix_payload' => $payload,
				'status'      => 'open',
				'detected_at' => $now,
				'updated_at'  => $now,
			),
			array( '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		return (int) $wpdb->insert_id;
	}

	/**
	 * یک ایراد.
	 *
	 * @param int $id شناسه.
	 * @return object|null
	 */
	public static function get( int $id ): ?object {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}gso_issues WHERE id = %d", $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return $row ? $row : null;
	}

	/**
	 * جست‌وجوی ایرادها.
	 *
	 * @param array $args فیلترها.
	 * @return array
	 */
	public static function query( array $args = array() ): array {
		global $wpdb;

		$args = wp_parse_args(
			$args,
			array(
				'status'      => '',
				'severity'    => '',
				'issue_type'  => '',
				'object_type' => '',
				'search'      => '',
				'limit'       => 25,
				'offset'      => 0,
				'orderby'     => 'priority',
				'object_id'   => 0,
			)
		);

		$where  = array( '1=1' );
		$params = array();

		if ( '' !== $args['status'] ) {
			if ( 'open_all' === $args['status'] ) {
				$where[] = "status IN ('open','proposed')";
			} else {
				$where[] = 'status = %s';
				$params[] = sanitize_key( $args['status'] );
			}
		}
		if ( '' !== $args['severity'] ) {
			$where[]  = 'severity = %s';
			$params[] = sanitize_key( $args['severity'] );
		}
		if ( '' !== $args['issue_type'] ) {
			$where[]  = 'issue_type = %s';
			$params[] = sanitize_key( $args['issue_type'] );
		}
		if ( '' !== $args['object_type'] ) {
			$where[]  = 'object_type = %s';
			$params[] = sanitize_key( $args['object_type'] );
		}
		if ( $args['object_id'] > 0 ) {
			$where[]  = 'object_id = %d';
			$params[] = absint( $args['object_id'] );
		}
		if ( '' !== $args['search'] ) {
			$like     = '%' . $wpdb->esc_like( sanitize_text_field( $args['search'] ) ) . '%';
			$where[]  = '(title LIKE %s OR description LIKE %s OR issue_type LIKE %s)';
			$params[] = $like;
			$params[] = $like;
			$params[] = $like;
		}

		$order_map = array(
			'priority' => "CASE severity WHEN 'critical' THEN 0 WHEN 'high' THEN 1 WHEN 'medium' THEN 2 ELSE 3 END, id DESC",
			'newest'   => 'id DESC',
			'oldest'   => 'id ASC',
			'object'   => 'object_type ASC, object_id ASC',
		);
		$order = $order_map[ $args['orderby'] ] ?? $order_map['priority'];

		$limit    = max( 1, min( 200, absint( $args['limit'] ) ) );
		$offset   = max( 0, absint( $args['offset'] ) );
		$params[] = $limit;
		$params[] = $offset;

		// $order فقط از $order_map سفیدلیست بالا مقدار می‌گیرد و ورودی کاربر نیست.
		$sql = $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}gso_issues WHERE " . implode( ' AND ', $where ) . " ORDER BY {$order} LIMIT %d OFFSET %d", $params ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (array) $wpdb->get_results( $sql ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * شمارش با همان فیلترها.
	 *
	 * @param array $args فیلترها.
	 * @return int
	 */
	public static function count( array $args = array() ): int {
		global $wpdb;
		$where       = array( '1=1' );
		$params      = array();
		$status      = (string) ( $args['status'] ?? '' );
		$severity    = (string) ( $args['severity'] ?? '' );
		$object_type = (string) ( $args['object_type'] ?? '' );

		if ( '' !== $status ) {
			if ( 'open_all' === $status ) {
				$where[] = "status IN ('open','proposed')";
			} else {
				$where[]  = 'status = %s';
				$params[] = sanitize_key( $status );
			}
		}
		if ( '' !== ( $args['search'] ?? '' ) ) {
			$like     = '%' . $wpdb->esc_like( sanitize_text_field( (string) $args['search'] ) ) . '%';
			$where[]  = '(title LIKE %s OR description LIKE %s)';
			$params[] = $like;
			$params[] = $like;
		}
		if ( '' !== $severity ) {
			$where[]  = 'severity = %s';
			$params[] = sanitize_key( $severity );
		}
		if ( '' !== $object_type ) {
			$where[]  = 'object_type = %s';
			$params[] = sanitize_key( $object_type );
		}

		if ( $params ) {
			$sql = $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}gso_issues WHERE " . implode( ' AND ', $where ), $params ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			return (int) $wpdb->get_var( $sql ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
		}
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}gso_issues WHERE " . implode( ' AND ', $where ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * آمار به تفکیک سطح و وضعیت.
	 *
	 * @return array
	 */
	public static function stats(): array {
		global $wpdb;
		$rows = (array) $wpdb->get_results( "SELECT severity, status, COUNT(*) AS c FROM {$wpdb->prefix}gso_issues GROUP BY severity, status" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

		$out = array(
			'critical' => 0,
			'high'     => 0,
			'medium'   => 0,
			'low'      => 0,
			'open'     => 0,
			'proposed' => 0,
			'fixed'    => 0,
			'ignored'  => 0,
			'failed'   => 0,
			'total'    => 0,
		);
		foreach ( $rows as $row ) {
			$count = (int) $row->c;
			$out['total'] += $count;
			if ( isset( $out[ $row->status ] ) ) {
				$out[ $row->status ] += $count;
			}
			if ( in_array( $row->status, array( 'open', 'proposed', 'failed' ), true ) && isset( $out[ $row->severity ] ) ) {
				$out[ $row->severity ] += $count;
			}
		}
		return $out;
	}

	/**
	 * امتیاز سلامت سایت (۰ تا ۱۰۰).
	 *
	 * @param array|null $stats آمار آماده.
	 * @return int
	 */
	public static function health_score( ?array $stats = null ): int {
		$stats = $stats ?? self::stats();
		$penalty = ( $stats['critical'] * 6 ) + ( $stats['high'] * 2 ) + ( ( $stats['medium'] ?? 0 ) * 1.5 ) + ( $stats['low'] * 0.5 );
		return (int) max( 0, min( 100, round( 100 - $penalty ) ) );
	}

	/**
	 * تغییر وضعیت.
	 *
	 * @param int    $id     شناسه.
	 * @param string $status وضعیت.
	 * @return bool
	 */
	public static function set_status( int $id, string $status ): bool {
		global $wpdb;
		$allowed = array( 'open', 'proposed', 'fixed', 'ignored', 'failed' );
		if ( ! in_array( $status, $allowed, true ) ) {
			return false;
		}
		return false !== $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			GSO_Install::table( 'issues' ),
			array( 'status' => $status, 'updated_at' => current_time( 'mysql' ) ),
			array( 'id' => $id ),
			array( '%s', '%s' ),
			array( '%d' )
		);
	}

	/**
	 * ذخیره payload اصلاح.
	 *
	 * @param int   $id      شناسه.
	 * @param array $payload داده.
	 * @param bool  $propose تبدیل وضعیت به proposed.
	 * @return bool
	 */
	public static function save_payload( int $id, array $payload, bool $propose = true ): bool {
		global $wpdb;
		$data = array(
			'fix_payload' => wp_json_encode( $payload ),
			'updated_at'  => current_time( 'mysql' ),
		);
		if ( $propose ) {
			$data['status'] = 'proposed';
		}
		return false !== $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			GSO_Install::table( 'issues' ),
			$data,
			array( 'id' => $id )
		);
	}

	/**
	 * payload decode‌شده.
	 *
	 * @param object $issue ردیف.
	 * @return array
	 */
	public static function payload( object $issue ): array {
		$data = json_decode( (string) $issue->fix_payload, true );
		return is_array( $data ) ? $data : array();
	}

	/**
	 * برچسب فارسی شیء.
	 *
	 * @param string $type نوع.
	 * @param int    $id   شناسه.
	 * @return string
	 */
	public static function object_label( string $type, int $id ): string {
		switch ( $type ) {
			case 'post':
			case 'page':
			case 'product':
				$title = get_the_title( $id );
				return '' !== $title ? $title : ( '#' . $id );
			case 'product_variation':
				$parent = (int) wp_get_post_parent_id( $id );
				return $parent > 0 ? sprintf( 'تنوع «%s» (#%d)', get_the_title( $parent ), $id ) : ( 'تنوع #' . $id );
			case 'attachment':
				$title = get_the_title( $id );
				return '' !== $title ? $title : ( 'تصویر #' . $id );
			case 'term':
				$term = get_term( $id );
				return ( $term && ! is_wp_error( $term ) ) ? $term->name : ( '#' . $id );
			case 'menu':
				$menu = wp_get_nav_menu_object( $id );
				return ( $menu && ! is_wp_error( $menu ) ) ? $menu->name : ( 'منو #' . $id );
			case 'site':
				return 'تنظیمات سایت';
			default:
				return '#' . $id;
		}
	}

	/**
	 * لینک ویرایش شیء.
	 *
	 * @param string $type نوع.
	 * @param int    $id   شناسه.
	 * @return string
	 */
	public static function edit_link( string $type, int $id ): string {
		switch ( $type ) {
			case 'attachment':
				return admin_url( 'post.php?post=' . $id . '&action=edit' );
			case 'term':
				$term = get_term( $id );
				if ( $term && ! is_wp_error( $term ) ) {
					return admin_url( 'term.php?taxonomy=' . $term->taxonomy . '&tag_ID=' . $id );
				}
				return '';
			case 'site':
				return admin_url( 'admin.php?page=gso-settings' );
			case 'menu':
				return admin_url( 'nav-menus.php?action=edit&menu=' . $id );
			default:
				return admin_url( 'post.php?post=' . $id . '&action=edit' );
		}
	}

	/**
	 * برچسب فارسی نوع ایراد.
	 *
	 * @param string $type نوع.
	 * @return string
	 */
	public static function issue_label( string $type ): string {
		$labels = array(
			'missing_h1'             => 'نبود H1',
			'multiple_h1'            => 'چند H1',
			'thin_content'           => 'محتوای کم‌حجم',
			'heading_gaps'           => 'ساختار هدینگ ناقص',
			'missing_cta'            => 'نبود فراخوان اقدام',
			'missing_meta_title'     => 'نبود عنوان سئو',
			'meta_title_length'      => 'طول نامناسب عنوان سئو',
			'missing_meta_description' => 'نبود توضیحات متا',
			'meta_description_length'  => 'طول نامناسب توضیحات متا',
			'missing_schema'         => 'نبود اسکیما',
			'image_missing_alt'      => 'تصویر بدون Alt',
			'attachment_missing_alt' => 'تصویر رسانه بدون Alt',
			'broken_link'            => 'لینک شکسته',
			'woo_missing_description' => 'محصول بدون توضیح',
			'woo_missing_short_description' => 'محصول بدون توضیح کوتاه',
			'woo_short_description_short' => 'توضیح کوتاه محصول کم‌حجم',
			'woo_missing_price'      => 'محصول بدون قیمت',
			'woo_missing_image'      => 'محصول بدون تصویر',
			'woo_missing_gallery'    => 'محصول بدون گالری تصویر',
			'woo_stock_unclear'      => 'وضعیت موجودی نامشخص',
			'woo_missing_category'   => 'محصول بدون دسته‌بندی',
			'woo_missing_tag'        => 'محصول بدون برچسب',
			'woo_inconsistent_structure' => 'ساختار ناهمگون معرفی محصول',
			'woo_variation_missing_price' => 'تنوع محصول بدون قیمت',
			'woo_variation_stock_unclear' => 'وضعیت موجودی تنوع نامشخص',
			'duplicate_meta_title'   => 'عنوان سئوی تکراری',
			'duplicate_meta_description' => 'توضیحات متای تکراری',
			'duplicate_content'      => 'محتوای هم‌پوشان',
			'missing_internal_links' => 'نبود لینک داخلی',
			'debug_mode_enabled'     => 'WP_DEBUG فعال',
			'debug_log_large'        => 'لاگ خطای بزرگ',
			'unregistered_shortcode' => 'شورت‌کد ناشناخته',
			'duplicate_html_id'      => 'شناسه تکراری HTML',
			'missing_excerpt'        => 'نبود خلاصه',
			'empty_term_description' => 'دسته بدون توضیح',
			'broken_menu_item'       => 'آیتم منوی شکسته',
			'ai_suggestion'          => 'پیشنهاد هوش مصنوعی',
		);
		return $labels[ $type ] ?? $type;
	}
}
