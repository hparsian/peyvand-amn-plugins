<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * پایش دائمی: هوک‌های رویدادمحور + صف کوتاه + اسکن زمان‌بندی‌شده.
 */
class GSO_Watcher {

	const DIRTY_OPTION = 'gso_dirty_objects';

	public static function init(): void {
		add_action( 'save_post', array( __CLASS__, 'on_save_post' ), 20, 3 );
		add_action( 'transition_post_status', array( __CLASS__, 'on_transition' ), 20, 3 );
		add_action( 'add_attachment', array( __CLASS__, 'on_attachment' ), 20, 1 );
		add_action( 'edit_attachment', array( __CLASS__, 'on_attachment' ), 20, 1 );
		add_action( 'edited_term', array( __CLASS__, 'on_term' ), 20, 3 );
		add_action( 'created_term', array( __CLASS__, 'on_term' ), 20, 3 );

		if ( class_exists( 'WooCommerce' ) ) {
			add_action( 'woocommerce_update_product', array( __CLASS__, 'on_product' ), 20, 1 );
			add_action( 'woocommerce_new_product', array( __CLASS__, 'on_product' ), 20, 1 );
		}
	}

	/**
	 * ثبت یک شیء برای بررسی در صف کوتاه.
	 *
	 * @param string $type نوع.
	 * @param int    $id   شناسه.
	 */
	public static function mark_dirty( string $type, int $id ): void {
		if ( $id <= 0 ) {
			return;
		}
		$queue = get_option( self::DIRTY_OPTION, array() );
		$queue = is_array( $queue ) ? $queue : array();
		$key   = sanitize_key( $type ) . ':' . $id;
		if ( isset( $queue[ $key ] ) ) {
			return;
		}
		$queue[ $key ] = time();
		if ( count( $queue ) > 200 ) {
			$queue = array_slice( $queue, -200, null, true );
		}
		update_option( self::DIRTY_OPTION, $queue, false );

		GSO_Queue::schedule_event( 'gso_watch_tick', 60 );
	}

	/**
	 * ذخیره نوشته.
	 *
	 * @param int     $post_id شناسه.
	 * @param WP_Post $post    شیء.
	 * @param bool    $update  به‌روزرسانی؟
	 */
	public static function on_save_post( int $post_id, $post, bool $update ): void {
		if ( ! GSO_Settings::get( 'watch_on_save' ) ) {
			return;
		}
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! $post instanceof WP_Post ) {
			return;
		}
		if ( ! in_array( $post->post_type, GSO_Settings::watched_post_types(), true ) ) {
			return;
		}
		if ( in_array( $post->post_status, array( 'auto-draft', 'trash', 'inherit' ), true ) ) {
			return;
		}
		self::mark_dirty( $post->post_type, $post_id );
	}

	/**
	 * تغییر وضعیت انتشار.
	 *
	 * @param string  $new_status وضعیت جدید.
	 * @param string  $old_status وضعیت قبلی.
	 * @param WP_Post $post       نوشته.
	 */
	public static function on_transition( string $new_status, string $old_status, $post ): void {
		if ( ! $post instanceof WP_Post || $new_status === $old_status ) {
			return;
		}
		if ( 'publish' === $new_status && in_array( $post->post_type, GSO_Settings::watched_post_types(), true ) ) {
			self::mark_dirty( $post->post_type, (int) $post->ID );
		}
	}

	/**
	 * پیوست تازه.
	 *
	 * @param int $attachment_id شناسه.
	 */
	public static function on_attachment( int $attachment_id ): void {
		self::mark_dirty( 'attachment', $attachment_id );
	}

	/**
	 * ترم تازه/ویرایش‌شده.
	 *
	 * @param int    $term_id  شناسه.
	 * @param int    $tt_id    شناسه تاکسونومی.
	 * @param string $taxonomy نام.
	 */
	public static function on_term( int $term_id, int $tt_id, string $taxonomy ): void {
		unset( $tt_id );
		if ( in_array( $taxonomy, array( 'category', 'post_tag', 'product_cat', 'product_tag' ), true ) ) {
			self::mark_dirty( 'term', $term_id );
		}
	}

	/**
	 * محصول ووکامرس.
	 *
	 * @param int $product_id شناسه.
	 */
	public static function on_product( int $product_id ): void {
		self::mark_dirty( 'product', $product_id );
	}

	/**
	 * پردازش صف کوتاه بررسی.
	 *
	 * @return int تعداد بررسی‌شده.
	 */
	public static function process_dirty(): int {
		$queue = get_option( self::DIRTY_OPTION, array() );
		if ( ! is_array( $queue ) || empty( $queue ) ) {
			return 0;
		}

		$limit = max( 1, min( 20, (int) GSO_Settings::get( 'batch_size', 10 ) ) );
		$batch = array_slice( $queue, 0, $limit, true );
		$rest  = array_slice( $queue, $limit, null, true );
		update_option( self::DIRTY_OPTION, $rest, false );

		$processed = 0;
		$found     = 0;
		$auto_ids  = array();

		foreach ( $batch as $key => $time ) {
			$parts = explode( ':', (string) $key );
			if ( count( $parts ) !== 2 ) {
				continue;
			}
			$type = $parts[0];
			$id   = (int) $parts[1];

			if ( 'attachment' === $type ) {
				$found += GSO_Auditor::audit_attachment( $id );
			} elseif ( 'term' === $type ) {
				$found += GSO_Auditor::audit_term( $id );
			} else {
				$found += GSO_Auditor::audit_post( $id );
			}
			++$processed;

			$auto_ids = array_merge( $auto_ids, self::issues_for_object( $type, $id ) );
		}

		if ( $processed > 0 ) {
			GSO_Logger::log( 'info', 'watcher.dirty', sprintf( 'پایش رویدادمحور: %d مورد بررسی و %d ایراد.', $processed, $found ), array( 'processed' => $processed ) );
		}

		self::maybe_auto_enqueue( $auto_ids );

		return $processed;
	}

	/**
	 * شناسه ایرادهای باز یک شیء.
	 *
	 * @param string $type نوع.
	 * @param int    $id   شناسه.
	 * @return array
	 */
	private static function issues_for_object( string $type, int $id ): array {
		$issues = GSO_Issues::query(
			array(
				'status'      => 'open_all',
				'object_type' => $type,
				'object_id'   => $id,
				'limit'       => 10,
				'orderby'     => 'priority',
			)
		);
		return array_map(
			static function ( $issue ) {
				return (int) $issue->id;
			},
			$issues
		);
	}

	/**
	 * افزودن خودکار موارد ایمن/مهم به صف بر اساس سطح خودکارسازی.
	 *
	 * @param array $issue_ids شناسه‌ها.
	 */
	private static function maybe_auto_enqueue( array $issue_ids ): void {
		$level = (string) GSO_Settings::get( 'automation_level', 'review_required' );
		if ( 'report_only' === $level || empty( $issue_ids ) ) {
			return;
		}

		$count = 0;
		foreach ( array_unique( $issue_ids ) as $issue_id ) {
			if ( $count >= 3 ) {
				break;
			}
			$issue = GSO_Issues::get( (int) $issue_id );
			if ( ! $issue ) {
				continue;
			}

			// safe_auto: فقط موارد کم‌خطر و کم‌ریسک. review_required: فقط پیشنهاد برای تایید.
			if ( 'safe_auto' === $level && ( 'low' !== $issue->severity || ! GSO_Fixer::is_safe( (int) $issue_id ) ) ) {
				continue;
			}
			if ( 'review_required' === $level && 'low' === $issue->severity && GSO_Fixer::is_safe( (int) $issue_id ) ) {
				continue;
			}

			if ( GSO_Queue::enqueue( (int) $issue_id, 'auto' ) ) {
				++$count;
			}
		}
	}

	/**
	 * اجرای اسکن کامل به‌صورت دستی از هوک (برای استفاده در CLI/تست).
	 *
	 * @param int $batch تعداد دسته.
	 * @return array
	 */
	public static function run_scan_now( int $batch = 5 ): array {
		GSO_Auditor::start();
		$state = GSO_Auditor::state();
		for ( $i = 0; $i < max( 1, $batch ); $i++ ) {
			$state = GSO_Auditor::tick();
			if ( empty( $state['active'] ) ) {
				break;
			}
		}
		return $state;
	}
}
