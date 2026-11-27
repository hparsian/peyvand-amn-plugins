<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * ثبت نسخه پیش از تغییر و بازگشت دقیق به حالت قبل.
 */
class GSO_Snapshot {

	/**
	 * ثبت snapshot.
	 *
	 * @param array $args issue_id, object_id, object_type, label, payload.
	 * @return int
	 */
	public static function capture( array $args ): int {
		global $wpdb;
		$args = wp_parse_args(
			$args,
			array(
				'issue_id'    => 0,
				'object_id'   => 0,
				'object_type' => 'site',
				'label'       => '',
				'payload'     => array(),
			)
		);

		$wpdb->insert(
			GSO_Install::table( 'snapshots' ),
			array(
				'issue_id'    => absint( $args['issue_id'] ),
				'object_id'   => absint( $args['object_id'] ),
				'object_type' => sanitize_key( $args['object_type'] ),
				'label'       => sanitize_text_field( $args['label'] ),
				'payload'     => wp_json_encode( $args['payload'] ),
				'user_id'     => get_current_user_id(),
				'created_at'  => current_time( 'mysql' ),
			),
			array( '%d', '%d', '%s', '%s', '%s', '%d', '%s' )
		);

		return (int) $wpdb->insert_id;
	}

	/**
	 * عکس‌های یک ایراد.
	 *
	 * @param int $issue_id شناسه.
	 * @return array
	 */
	public static function for_issue( int $issue_id ): array {
		global $wpdb;
		$table = GSO_Install::table( 'snapshots' );
		return (array) $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare( 'SELECT * FROM %i WHERE issue_id = %d ORDER BY id DESC', $table, $issue_id )
		);
	}

	/**
	 * آخرین snapshot برای یک شیء.
	 *
	 * @param string $object_type نوع.
	 * @param int    $object_id   شناسه.
	 * @return object|null
	 */
	public static function latest_for_object( string $object_type, int $object_id ): ?object {
		global $wpdb;
		$table = GSO_Install::table( 'snapshots' );
		$row   = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				'SELECT * FROM %i WHERE object_type = %s AND object_id = %d ORDER BY id DESC LIMIT 1',
				$table,
				sanitize_key( $object_type ),
				$object_id
			)
		);
		return $row ? $row : null;
	}

	/**
	 * فهرست اخیر.
	 *
	 * @param int $limit تعداد.
	 * @return array
	 */
	public static function recent( int $limit = 20 ): array {
		global $wpdb;
		$table = GSO_Install::table( 'snapshots' );
		return (array) $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare( 'SELECT * FROM %i ORDER BY id DESC LIMIT %d', $table, max( 1, min( 200, $limit ) ) )
		);
	}

	/**
	 * یک snapshot.
	 *
	 * @param int $id شناسه.
	 * @return object|null
	 */
	public static function get( int $id ): ?object {
		global $wpdb;
		$table = GSO_Install::table( 'snapshots' );
		$row   = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE id = %d', $table, $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return $row ? $row : null;
	}

	/**
	 * وضعیت فعلی یک شیء را برای snapshot جمع می‌کند.
	 *
	 * @param string $object_type نوع.
	 * @param int    $object_id   شناسه.
	 * @param string $fix_type    نوع اصلاح (برای تعیین کلیدهای متا).
	 * @return array
	 */
	public static function collect_state( string $object_type, int $object_id, string $fix_type = '' ): array {
		$state = array( 'object_type' => $object_type, 'object_id' => $object_id );

		if ( in_array( $object_type, array( 'post', 'page', 'product', 'attachment' ), true ) ) {
			$post = get_post( $object_id );
			if ( $post ) {
				$state['post'] = array(
					'post_title'   => $post->post_title,
					'post_content' => $post->post_content,
					'post_excerpt' => $post->post_excerpt,
				);
			}
			$meta_keys = array( '_wp_attachment_image_alt', GSO_SEO::SCHEMA_META, GSO_SEO::FALLBACK_TITLE, GSO_SEO::FALLBACK_DESC );
			$meta_keys = array_merge( $meta_keys, GSO_SEO::title_keys(), GSO_SEO::desc_keys() );
			$meta      = array();
			foreach ( array_unique( $meta_keys ) as $key ) {
				$meta[ $key ] = get_post_meta( $object_id, $key, true );
			}
			$state['meta'] = $meta;

			if ( 'product' === $object_type || 'attachment' === $object_type || 'set_alt' === $fix_type ) {
				$state['product_meta'] = array(
					'_price'                => get_post_meta( $object_id, '_price', true ),
					'_regular_price'        => get_post_meta( $object_id, '_regular_price', true ),
					'_thumbnail_id'         => get_post_meta( $object_id, '_thumbnail_id', true ),
					'_stock_status'         => get_post_meta( $object_id, '_stock_status', true ),
					'_product_image_gallery' => get_post_meta( $object_id, '_product_image_gallery', true ),
				);
			}

			if ( 'product' === $object_type ) {
				$assignments = array();
				foreach ( array( 'product_cat', 'product_tag' ) as $taxonomy ) {
					if ( ! taxonomy_exists( $taxonomy ) ) {
						continue;
					}
					$terms = wp_get_post_terms( $object_id, $taxonomy, array( 'fields' => 'ids' ) );
					$assignments[ $taxonomy ] = is_wp_error( $terms ) ? array() : array_map( 'intval', $terms );
				}
				$state['terms'] = $assignments;
			}
		} elseif ( 'term' === $object_type ) {
			$term = get_term( $object_id );
			if ( $term && ! is_wp_error( $term ) ) {
				$state['term'] = array(
					'taxonomy'    => $term->taxonomy,
					'name'        => $term->name,
					'description' => $term->description,
				);
			}
		} elseif ( 'site' === $object_type ) {
			$state['options'] = array(
				'gso_custom_css' => get_option( 'gso_custom_css', '' ),
			);
		}

		return $state;
	}

	/**
	 * بازگشت به وضعیت ذخیره‌شده.
	 *
	 * @param int $snapshot_id شناسه.
	 * @return true|WP_Error
	 */
	public static function restore( int $snapshot_id ) {
		global $wpdb;

		$snap = self::get( $snapshot_id );
		if ( ! $snap ) {
			return new WP_Error( 'gso_snapshot_missing', 'نسخه پشتیبان پیدا نشد.' );
		}
		if ( '' !== (string) $snap->restored_at && '0000-00-00 00:00:00' !== (string) $snap->restored_at ) {
			return new WP_Error( 'gso_snapshot_used', 'این نسخه قبلاً بازگردانی شده است.' );
		}

		$payload = json_decode( (string) $snap->payload, true );
		if ( ! is_array( $payload ) ) {
			return new WP_Error( 'gso_snapshot_bad', 'داده نسخه پشتیبان معتبر نیست.' );
		}

		$object_type = (string) $snap->object_type;
		$object_id   = (int) $snap->object_id;

		if ( in_array( $object_type, array( 'post', 'page', 'product', 'attachment' ), true ) ) {
			if ( ! empty( $payload['post'] ) && is_array( $payload['post'] ) ) {
				$result = wp_update_post(
					array(
						'ID'           => $object_id,
						'post_title'   => (string) ( $payload['post']['post_title'] ?? '' ),
						'post_content' => (string) ( $payload['post']['post_content'] ?? '' ),
						'post_excerpt' => (string) ( $payload['post']['post_excerpt'] ?? '' ),
					),
					true
				);
				if ( is_wp_error( $result ) ) {
					return $result;
				}
			}
			if ( ! empty( $payload['meta'] ) && is_array( $payload['meta'] ) ) {
				foreach ( $payload['meta'] as $key => $value ) {
					$key = sanitize_key( $key );
					if ( '' === $key ) {
						continue;
					}
					if ( '' === (string) $value ) {
						delete_post_meta( $object_id, $key );
					} else {
						update_post_meta( $object_id, $key, $value );
					}
				}
			}
			if ( ! empty( $payload['product_meta'] ) && is_array( $payload['product_meta'] ) ) {
				foreach ( $payload['product_meta'] as $key => $value ) {
					$meta_key = sanitize_key( $key );
					if ( '' === (string) $value ) {
						delete_post_meta( $object_id, $meta_key );
					} else {
						update_post_meta( $object_id, $meta_key, $value );
					}
				}
			}
			if ( ! empty( $payload['terms'] ) && is_array( $payload['terms'] ) ) {
				foreach ( $payload['terms'] as $taxonomy => $term_ids ) {
					$taxonomy = sanitize_key( $taxonomy );
					if ( ! taxonomy_exists( $taxonomy ) ) {
						continue;
					}
					wp_set_object_terms( $object_id, array_map( 'absint', (array) $term_ids ), $taxonomy, false );
				}
			}
		} elseif ( 'term' === $object_type && ! empty( $payload['term'] ) ) {
			$term = $payload['term'];
			wp_update_term(
				$object_id,
				(string) ( $term['taxonomy'] ?? 'category' ),
				array(
					'name'        => (string) ( $term['name'] ?? '' ),
					'description' => (string) ( $term['description'] ?? '' ),
				)
			);
		} elseif ( 'site' === $object_type && ! empty( $payload['options'] ) && is_array( $payload['options'] ) ) {
			foreach ( $payload['options'] as $option => $value ) {
				update_option( sanitize_key( $option ), $value, false );
			}
		}

		$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			GSO_Install::table( 'snapshots' ),
			array( 'restored_at' => current_time( 'mysql' ) ),
			array( 'id' => $snapshot_id )
		);

		if ( (int) $snap->issue_id > 0 ) {
			GSO_Issues::set_status( (int) $snap->issue_id, 'open' );
			$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				GSO_Install::table( 'issues' ),
				array( 'fix_payload' => '{}' ),
				array( 'id' => (int) $snap->issue_id )
			);
		}

		GSO_Logger::log(
			'success',
			'snapshot.restore',
			sprintf( 'بازگشت به نسخه #%d برای %s %d انجام شد.', $snapshot_id, $object_type, $object_id ),
			array( 'snapshot_id' => $snapshot_id )
		);

		return true;
	}

	/**
	 * پاک‌سازی snapshot‌های قدیمی‌تر از N روز.
	 *
	 * @param int $days روز.
	 */
	public static function prune( int $days = 90 ): void {
		global $wpdb;
		$table  = GSO_Install::table( 'snapshots' );
		$cutoff = gmdate( 'Y-m-d H:i:s', time() - ( max( 1, $days ) * DAY_IN_SECONDS ) );
		$wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE created_at < %s', $table, $cutoff ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}
}
