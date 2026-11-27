<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * موتور ممیزی کل سایت — دسته‌ای، بدون Timeout.
 *
 * چرخه: posts → attachments → terms → menus → site → finish
 */
class GSO_Auditor {

	const STATE_OPTION = 'gso_scan_state';
	const INDEX_OPTION = 'gso_seo_index';

	/**
	 * پایان بودجه زمانی دسته جاری (timestamp)؛ ۰ = بدون محدودیت.
	 *
	 * @var int
	 */
	private static int $deadline = 0;

	/**
	 * وضعیت فعلی اسکن.
	 *
	 * @return array
	 */
	public static function state(): array {
		$state = get_option( self::STATE_OPTION, array() );
		$state = is_array( $state ) ? $state : array();
		return wp_parse_args(
			$state,
			array(
				'active'    => false,
				'phase'     => 'posts',
				'offset'    => 0,
				'processed' => 0,
				'found'     => 0,
				'errors'    => 0,
				'total'     => 0,
				'totals'    => array(),
				'started_at' => 0,
				'finished_at' => 0,
				'last_tick'  => 0,
				'last_error' => '',
			)
		);
	}

	private static function save_state( array $state ): void {
		update_option( self::STATE_OPTION, $state, false );
	}

	/**
	 * شروع اسکن تازه.
	 *
	 * @param array $args include_attachments, include_terms.
	 * @return array وضعیت.
	 */
	public static function start( array $args = array() ): array {
		if ( self::state()['active'] ) {
			return self::state();
		}

		$state = array(
			'active'      => true,
			'phase'       => 'posts',
			'offset'      => 0,
			'processed'   => 0,
			'found'       => 0,
			'errors'      => 0,
			'total'       => 0,
			'totals'      => array(
				'posts'       => self::count_posts(),
				'attachments' => (int) ( $args['include_attachments'] ?? true ) ? self::count_attachments() : 0,
				'variations'  => ( (int) ( $args['include_terms'] ?? true ) && self::has_product_audit() ) ? self::count_variations() : 0,
				'terms'       => (int) ( $args['include_terms'] ?? true ) ? self::count_terms() : 0,
			),
			'started_at'  => time(),
			'finished_at' => 0,
		);
		$state['total'] = array_sum( $state['totals'] ) + 1;

		self::save_state( $state );
		self::reset_index();
		self::schedule_followup( 10 );
		GSO_Logger::log( 'info', 'audit.start', 'اسکن کل سایت آغاز شد.', $state['totals'] );
		return $state;
	}

	/**
	 * توقف اسکن.
	 *
	 * @return array
	 */
	public static function stop(): array {
		$state            = self::state();
		$state['active']  = false;
		$state['finished_at'] = time();
		self::save_state( $state );
		self::unschedule_followup();
		GSO_Logger::log( 'warning', 'audit.stop', 'اسکن به‌دست اپراتور متوقف شد.', array( 'processed' => $state['processed'] ) );
		return $state;
	}

	/**
	 * درصد پیشرفت.
	 *
	 * @param array|null $state وضعیت.
	 * @return int
	 */
	public static function progress( ?array $state = null ): int {
		$state = $state ?? self::state();
		if ( 'done' === (string) ( $state['phase'] ?? '' ) ) {
			return 100;
		}
		if ( empty( $state['total'] ) ) {
			return 0;
		}
		return (int) min( 100, round( ( $state['processed'] / max( 1, $state['total'] ) ) * 100 ) );
	}

	/**
	 * یک تیک کران: یک دسته پردازش می‌کند.
	 *
	 * @return array
	 */
	public static function tick(): array {
		$state = self::state();
		if ( empty( $state['active'] ) ) {
			return $state;
		}

		$limit = (int) GSO_Settings::get( 'batch_size', 10 );
		$limit = max( 1, min( 50, $limit ) );

		$budget            = max( 5, min( 120, (int) GSO_Settings::get( 'scan_time_budget', 20 ) ) );
		$deadline          = time() + $budget;
		self::$deadline    = $deadline;
		$timed_out         = false;

		switch ( $state['phase'] ) {
			case 'posts':
				$ids = self::post_ids( (int) $state['offset'], $limit );
				if ( empty( $ids ) ) {
					$state['phase']  = 'attachments';
					$state['offset'] = 0;
					break;
				}
				$batch     = self::run_batch( $state, 'audit_post', $ids, $deadline );
				$state['offset'] += $batch['done'];
				$timed_out = $batch['timed_out'];
				break;

			case 'attachments':
				$ids = self::attachment_ids( (int) $state['offset'], $limit );
				if ( empty( $ids ) ) {
					$state['phase']  = self::has_product_audit() ? 'variations' : 'terms';
					$state['offset'] = 0;
					break;
				}
				$batch     = self::run_batch( $state, 'audit_attachment', $ids, $deadline );
				$state['offset'] += $batch['done'];
				$timed_out = $batch['timed_out'];
				break;

			case 'variations':
				$ids = self::variation_ids( (int) $state['offset'], $limit );
				if ( empty( $ids ) ) {
					$state['phase']  = 'terms';
					$state['offset'] = 0;
					break;
				}
				$batch     = self::run_batch( $state, 'audit_variation', $ids, $deadline );
				$state['offset'] += $batch['done'];
				$timed_out = $batch['timed_out'];
				break;

			case 'terms':
				$ids = self::term_ids( (int) $state['offset'], $limit );
				if ( empty( $ids ) ) {
					$state['phase']  = 'menus';
					$state['offset'] = 0;
					break;
				}
				$batch     = self::run_batch( $state, 'audit_term', $ids, $deadline );
				$state['offset'] += $batch['done'];
				$timed_out = $batch['timed_out'];
				break;

			case 'menus':
				$state['found'] += self::audit_menus();
				++$state['processed'];
				$state['phase'] = 'site';
				break;

			case 'site':
				$state['found'] += self::audit_site();
				++$state['processed'];
				$state['phase'] = 'finish';
				break;

			case 'finish':
			default:
				$state['active']      = false;
				$state['phase']       = 'done';
				$state['total']       = max( (int) $state['processed'], (int) $state['total'] );
				$state['finished_at'] = time();
				update_option( 'gso_last_scan', time(), false );
				update_option( 'gso_last_scan_found', (int) $state['found'], false );
				self::flush_duplicate_index();
				self::auto_enqueue_findings();
				GSO_Logger::log(
					'success',
					'audit.finish',
					sprintf( 'اسکن کامل شد: %d مورد بررسی، %d ایراد.', (int) $state['processed'], (int) $state['found'] ),
					array( 'processed' => $state['processed'], 'found' => $state['found'] )
				);
				break;
		}

		$state['last_tick'] = time();
		self::save_state( $state );
		self::$deadline = 0;

		if ( ! empty( $state['active'] ) ) {
			self::schedule_followup( $timed_out ? 12 : 20 );
		}

		return $state;
	}

	/**
	 * اجرای یک دسته از شناسه‌ها با احترام به بودجه زمانی.
	 *
	 * @param array  $state    وضعیت (با مرجع).
	 * @param string $method   متد ممیزی.
	 * @param array  $ids      شناسه‌ها.
	 * @param int    $deadline پایان بودجه.
	 * @return array done, timed_out
	 */
	private static function run_batch( array &$state, string $method, array $ids, int $deadline ): array {
		$done      = 0;
		$timed_out = false;

		foreach ( $ids as $id ) {
			if ( $done > 0 && time() >= $deadline ) {
				$timed_out = true;
				break;
			}
			try {
				$state['found'] += (int) call_user_func( array( __CLASS__, $method ), (int) $id );
			} catch ( \Throwable $e ) {
				++$state['errors'];
				$state['last_error'] = $e->getMessage();
				GSO_Logger::log( 'error', 'audit.item', 'خطا در بررسی یک مورد: ' . $e->getMessage(), array( 'id' => (int) $id ) );
			}
			++$state['processed'];
			++$done;
		}

		return array( 'done' => $done, 'timed_out' => $timed_out );
	}

	/**
	 * نقطه ورود کران: یک دسته و زنجیره‌ی اجرای پیوسته.
	 */
	public static function runner(): void {
		self::tick();
	}

	/**
	 * زمان‌بندی تیک بعدی اسکن (به‌جای انتظار ۵ دقیقه‌ای).
	 *
	 * @param int $delay ثانیه.
	 */
	public static function schedule_followup( int $delay = 20 ): void {
		$state = self::state();
		if ( empty( $state['active'] ) ) {
			return;
		}
		GSO_Queue::schedule_event( 'gso_scan_followup', $delay );
	}

	/**
	 * حذف تیک زمان‌بندی‌شده‌ی اسکن.
	 */
	public static function unschedule_followup(): void {
		GSO_Queue::unschedule_event( 'gso_scan_followup' );
	}

	/**
	 * اطلاعات پیشرفت، سرعت و زمان تقریبی پایان برای داشبورد.
	 *
	 * @param array|null $state وضعیت.
	 * @return array
	 */
	public static function progress_info( ?array $state = null ): array {
		$state    = $state ?? self::state();
		$total    = (int) $state['total'];
		$processed = (int) $state['processed'];
		$started  = (int) $state['started_at'];
		$ended    = ! empty( $state['active'] ) ? time() : (int) $state['finished_at'];
		$elapsed  = $started > 0 ? max( 1, $ended - $started ) : 0;
		$speed    = ( $elapsed > 0 && $processed > 0 ) ? round( $processed / $elapsed * 60, 1 ) : 0;
		$left     = max( 0, $total - $processed );

		return array(
			'active'      => ! empty( $state['active'] ),
			'phase'       => (string) $state['phase'],
			'processed'   => $processed,
			'total'       => $total,
			'remaining'   => $left,
			'percent'     => self::progress( $state ),
			'found'       => (int) $state['found'],
			'errors'      => (int) $state['errors'],
			'speed'       => $speed,
			'elapsed'     => $elapsed,
			'eta_minutes' => $speed > 0 ? (int) ceil( $left / $speed ) : 0,
			'started_at'  => $started,
			'finished_at' => (int) $state['finished_at'],
			'last_tick'   => (int) $state['last_tick'],
		);
	}

	/**
	 * شناسه نوشته‌های عمومی مورد پایش.
	 *
	 * @param int $offset شروع.
	 * @param int $limit  تعداد.
	 * @return array
	 */
	private static function post_ids( int $offset, int $limit ): array {
		$ids = get_posts(
			array(
				'post_type'              => GSO_Settings::watched_post_types(),
				'post_status'            => 'publish',
				'fields'                 => 'ids',
				'posts_per_page'         => $limit,
				'offset'                 => $offset,
				'orderby'                => 'ID',
				'order'                  => 'ASC',
				'no_found_rows'          => true,
				'cache_results'          => false,
				'update_post_term_cache' => false,
				'update_post_meta_cache' => false,
				'suppress_filters'       => false,
			)
		);
		return array_map( 'intval', $ids );
	}

	private static function count_posts(): int {
		$total  = 0;
		foreach ( GSO_Settings::watched_post_types() as $type ) {
			$row = wp_count_posts( $type );
			if ( isset( $row->publish ) ) {
				$total += (int) $row->publish;
			}
		}
		return $total;
	}

	private static function attachment_ids( int $offset, int $limit ): array {
		$ids = get_posts(
			array(
				'post_type'      => 'attachment',
				'post_status'    => 'inherit',
				'post_mime_type' => 'image',
				'fields'         => 'ids',
				'posts_per_page' => $limit,
				'offset'         => $offset,
				'orderby'                => 'ID',
				'order'                  => 'ASC',
				'no_found_rows'          => true,
				'cache_results'          => false,
				'update_post_term_cache' => false,
				'update_post_meta_cache' => false,
			)
		);
		return array_map( 'intval', $ids );
	}

	private static function count_attachments(): int {
		$count = wp_count_posts( 'attachment' );
		return (int) ( $count->inherit ?? 0 );
	}

	/**
	 * ترم‌های قابل بررسی.
	 *
	 * @param int $offset شروع.
	 * @param int $limit  تعداد.
	 * @return array
	 */
	private static function term_ids( int $offset, int $limit ): array {
		$taxes = array( 'category', 'post_tag' );
		if ( GSO_Settings::has_woocommerce() ) {
			$taxes[] = 'product_cat';
			$taxes[] = 'product_tag';
		}
		$terms = get_terms(
			array(
				'taxonomy'   => array_values( array_filter( $taxes, 'taxonomy_exists' ) ),
				'hide_empty' => false,
				'offset'     => $offset,
				'number'     => $limit,
				'orderby'    => 'term_id',
				'order'      => 'ASC',
				'fields'     => 'ids',
			)
		);
		return is_wp_error( $terms ) ? array() : array_map( 'intval', $terms );
	}

	private static function count_terms(): int {
		$taxes = array( 'category', 'post_tag' );
		if ( GSO_Settings::has_woocommerce() ) {
			$taxes[] = 'product_cat';
			$taxes[] = 'product_tag';
		}
		$total = 0;
		foreach ( array_filter( $taxes, 'taxonomy_exists' ) as $tax ) {
			$count = wp_count_terms( array( 'taxonomy' => $tax, 'hide_empty' => false ) );
			if ( ! is_wp_error( $count ) ) {
				$total += (int) $count;
			}
		}
		return $total;
	}

	/**
	 * ممیزی یک نوشته/برگه/محصول.
	 *
	 * @param int $post_id شناسه.
	 * @return int تعداد ایراد کشف‌شده.
	 */
	public static function audit_post( int $post_id ): int {
		$post = get_post( $post_id );
		if ( ! $post || 'trash' === $post->post_status || wp_is_post_revision( $post_id ) ) {
			return 0;
		}
		if ( ! in_array( $post->post_type, GSO_Settings::watched_post_types(), true ) ) {
			return 0;
		}

		$found   = 0;
		$content = (string) $post->post_content;
		$plain   = wp_strip_all_tags( strip_shortcodes( $content ) );
		$words   = self::word_count( $plain );
		$is_page = in_array( $post->post_type, array( 'page', 'product' ), true );
		$base    = array(
			'object_id'   => $post_id,
			'object_type' => $post->post_type,
		);

		// ۱) هدینگ‌ها.
		$headings = self::headings( $content );
		$h1       = (int) ( $headings['counts'][1] ?? 0 );

		if ( $h1 > 1 ) {
			GSO_Issues::upsert(
				$base + array(
					'issue_type'  => 'multiple_h1',
					'severity'    => 'critical',
					'title'       => sprintf( 'چند H1 در «%s»', get_the_title( $post_id ) ),
					'description' => sprintf( 'این صفحه %d تگ H1 دارد؛ فقط یک H1 باید وجود داشته باشد.', $h1 ),
					'suggestion'  => ' H1 اصلی را نگه دار و بقیه را به H2 تبدیل کن (اصلاح خودکار قابل بازگشت است).',
					'fix_type'    => 'normalize_h1',
					'fix_payload' => array( 'handler' => 'normalize_h1', 'safe' => true ),
				)
			);
			++$found;
		} elseif ( 0 === $h1 && $words > 80 ) {
			GSO_Issues::upsert(
				$base + array(
					'issue_type'  => 'missing_h1',
					'severity'    => $is_page ? 'high' : 'low',
					'title'       => sprintf( 'نبود H1 در «%s»', get_the_title( $post_id ) ),
					'description' => 'در محتوای این صفحه H1 وجود ندارد. اگر پوسته عنوان نوشته را به‌صورت H1 رندر می‌کند، این مورد را نادیده بگیر.',
					'suggestion'  => ' یک H1 معنادار با کلیدواژه اصلی اضافه کن (پیشنهاد هوش مصنوعی قابل بازبینی است).',
					'fix_type'    => 'insert_h1',
					'fix_payload' => array( 'handler' => 'insert_h1', 'safe' => false ),
				)
			);
			++$found;
		}

		if ( ! empty( $headings['gap'] ) ) {
			GSO_Issues::upsert(
				$base + array(
					'issue_type'  => 'heading_gaps',
					'severity'    => 'low',
					'title'       => sprintf( 'پرش سطح هدینگ در «%s»', get_the_title( $post_id ) ),
					'description' => 'ترتیب هدینگ‌ها یک سطح را جا انداخته است: ' . $headings['gap'],
					'suggestion'  => 'سطوح هدینگ را پیوسته کن (H2 سپس H3) تا ساختار معنایی درست بماند.',
					'fix_type'    => 'manual',
				)
			);
			++$found;
		}

		// ۲) محتوای کم‌حجم (برای محصولات آستانهٔ اختصاصی ۱۲۰ کلمه در audit_product اعمال می‌شود).
		$min_words = (int) GSO_Settings::get( 'thin_content_words', 300 );
		if ( $words > 0 && $words < $min_words && 'product' !== $post->post_type ) {
			$severity = $words < (int) max( 50, $min_words / 2 ) ? 'high' : 'low';
			GSO_Issues::upsert(
				$base + array(
					'issue_type'  => 'thin_content',
					'severity'    => $severity,
					'title'       => sprintf( 'محتوای کم‌حجم در «%s»', get_the_title( $post_id ) ),
					'description' => sprintf( 'این محتوا تنها %d کلمه دارد (حد مطلوب: %d).', $words, $min_words ),
					'suggestion'  => ' محتوا را با بخش‌های مفید، پرسش‌های متداول و مثال غنی‌سازی کن.',
					'fix_type'    => 'expand_content',
					'fix_payload' => array( 'handler' => 'expand_content', 'safe' => false ),
				)
			);
			++$found;
		}

		// ۳) فراخوان اقدام.
		if ( $words > 60 && ! self::has_cta( $content ) ) {
			GSO_Issues::upsert(
				$base + array(
					'issue_type'  => 'missing_cta',
					'severity'    => $is_page ? 'high' : 'low',
					'title'       => sprintf( 'نبود CTA در «%s»', get_the_title( $post_id ) ),
					'description' => 'در این محتوا دکمه یا لینک فراخوان اقدام مشخصی پیدا نشد.',
					'suggestion'  => ' یک CTA روشن (تماس، ثبت‌نام، خرید یا دانلود) اضافه کن.',
					'fix_type'    => 'add_cta',
					'fix_payload' => array( 'handler' => 'add_cta', 'safe' => false ),
				)
			);
			++$found;
		}

		// ۴) متای سئو (تفکیک ذخیره‌شده از عنوان/توضیح مؤثرِ تولیدشده توسط افزونه سئو).
		$stored_title = GSO_SEO::stored_title( $post_id );
		$stored_desc  = GSO_SEO::stored_description( $post_id );
		$title        = '' !== $stored_title ? $stored_title : GSO_SEO::get_title( $post_id );
		$desc         = '' !== $stored_desc ? $stored_desc : GSO_SEO::get_description( $post_id );
		$min_title  = (int) GSO_Settings::get( 'min_meta_title', 30 );
		$max_title  = (int) GSO_Settings::get( 'max_meta_title', 60 );
		$min_desc   = (int) GSO_Settings::get( 'min_meta_desc', 70 );
		$max_desc   = (int) GSO_Settings::get( 'max_meta_desc', 160 );

		if ( '' === $stored_title ) {
			GSO_Issues::upsert(
				$base + array(
					'issue_type'  => 'missing_meta_title',
					'severity'    => 'high',
					'title'       => sprintf( 'نبود عنوان سئو برای «%s»', get_the_title( $post_id ) ),
					'description' => sprintf( 'عنوان سئوی اختصاصی این صفحه تنظیم نشده است (افزونه فعال: %s) و خروجی به عنوان پیش‌فرض نوشته وابسته است.', GSO_SEO::plugin_label() ),
					'suggestion'  => ' یک عنوان سئوی ۳۰ تا ۶۰ کاراکتری با کلیدواژه اصلی تولید و ذخیره کن.',
					'fix_type'    => 'set_meta_title',
					'fix_payload' => array( 'handler' => 'set_meta_title', 'safe' => true ),
				)
			);
			++$found;
		} elseif ( '' !== $title && ( GSO_SEO::length( $title ) < $min_title || GSO_SEO::length( $title ) > $max_title ) ) {
			GSO_Issues::upsert(
				$base + array(
					'issue_type'  => 'meta_title_length',
					'severity'    => 'low',
					'title'       => sprintf( 'طول نامناسب عنوان سئو در «%s»', get_the_title( $post_id ) ),
					'description' => sprintf( 'طول عنوان %d کاراکتر است؛ بازه مطلوب %d تا %d.', GSO_SEO::length( $title ), $min_title, $max_title ),
					'suggestion'  => ' عنوان را کوتاه/غنی کن و کلیدواژه اصلی را ابتدای آن بگذار.',
					'fix_type'    => 'set_meta_title',
					'fix_payload' => array( 'handler' => 'set_meta_title', 'safe' => true, 'current' => $title ),
				)
			);
			++$found;
		}

		if ( '' === $stored_desc ) {
			GSO_Issues::upsert(
				$base + array(
					'issue_type'  => 'missing_meta_description',
					'severity'    => 'high',
					'title'       => sprintf( 'نبود توضیحات متا برای «%s»', get_the_title( $post_id ) ),
					'description' => sprintf( 'توضیحات متای اختصاصی این صفحه خالی است (افزونه فعال: %s) و موتور جست‌وجو خودش متن انتخاب می‌کند.', GSO_SEO::plugin_label() ),
					'suggestion'  => ' توضیحات متای ۷۰ تا ۱۶۰ کاراکتری با CTA ملایم تولید کن.',
					'fix_type'    => 'set_meta_description',
					'fix_payload' => array( 'handler' => 'set_meta_description', 'safe' => true ),
				)
			);
			++$found;
		} elseif ( GSO_SEO::length( $desc ) < $min_desc || GSO_SEO::length( $desc ) > $max_desc ) {
			GSO_Issues::upsert(
				$base + array(
					'issue_type'  => 'meta_description_length',
					'severity'    => 'low',
					'title'       => sprintf( 'طول نامناسب توضیحات متا در «%s»', get_the_title( $post_id ) ),
					'description' => sprintf( 'طول توضیحات %d کاراکتر است؛ بازه مطلوب %d تا %d.', GSO_SEO::length( $desc ), $min_desc, $max_desc ),
					'suggestion'  => ' توضیحات را بازنویسی کن تا کامل و بدون بریدگی نمایش داده شود.',
					'fix_type'    => 'set_meta_description',
					'fix_payload' => array( 'handler' => 'set_meta_description', 'safe' => true, 'current' => $desc ),
				)
			);
			++$found;
		}

		self::index_meta( $post_id, '' !== $title ? $title : (string) get_the_title( $post_id ), $desc, $content, $words );

		// ۵) اسکیما.
		if ( ! GSO_SEO::has_schema( $post_id ) ) {
			GSO_Issues::upsert(
				$base + array(
					'issue_type'  => 'missing_schema',
					'severity'    => 'low',
					'title'       => sprintf( 'نبود اسکیما در «%s»', get_the_title( $post_id ) ),
					'description' => 'داده ساختاریافته JSON-LD برای این صفحه ثبت نشده است.',
					'suggestion'  => ' اسکیمای Article/WebPage را به‌صورت خودکار و قابل بازگشت تزریق کن.',
					'fix_type'    => 'inject_schema',
					'fix_payload' => array( 'handler' => 'inject_schema', 'safe' => true ),
				)
			);
			++$found;
		}

		// ۶) تصاویر درون‌محتوا.
		$found += self::audit_inline_images( $post_id, $content );

		// ۷) لینک‌ها.
		if ( GSO_Settings::get( 'enable_link_check' ) ) {
			$found += self::audit_links( $post_id, $content );
		}

		// ۷٫۵) لینک داخلی.
		$found += self::audit_internal_links( $post_id, $content, $words );

		// ۸) خلاصه.
		if ( 'post' === $post->post_type && '' === trim( (string) $post->post_excerpt ) ) {
			GSO_Issues::upsert(
				$base + array(
					'issue_type'  => 'missing_excerpt',
					'severity'    => 'low',
					'title'       => sprintf( 'نبود خلاصه برای «%s»', get_the_title( $post_id ) ),
					'description' => 'خلاصه نوشته خالی است و در آرشیوها ممکن است متن نامناسب نمایش داده شود.',
					'suggestion'  => ' یک خلاصه یک تا دو جمله‌ای تولید و ذخیره کن.',
					'fix_type'    => 'set_excerpt',
					'fix_payload' => array( 'handler' => 'set_excerpt', 'safe' => true ),
				)
			);
			++$found;
		}

		// ۹) شناسه تکراری HTML.
		$dupes = self::duplicate_ids( $content );
		if ( ! empty( $dupes ) ) {
			GSO_Issues::upsert(
				$base + array(
					'issue_type'  => 'duplicate_html_id',
					'severity'    => 'low',
					'title'       => sprintf( 'شناسه تکراری HTML در «%s»', get_the_title( $post_id ) ),
					'description' => 'این شناسه‌ها بیش از یک بار در محتوا استفاده شده‌اند: ' . implode( ', ', $dupes ),
					'suggestion'  => ' شناسه‌های تکراری را یکتا کن (اصلاح خودکار قابل بازگشت).',
					'fix_type'    => 'dedupe_ids',
					'fix_payload' => array( 'handler' => 'dedupe_ids', 'safe' => true ),
				)
			);
			++$found;
		}

		// ۱۰) شورت‌کدهای ثبت‌نشده.
		$shortcodes = self::unregistered_shortcodes( $content );
		if ( ! empty( $shortcodes ) ) {
			GSO_Issues::upsert(
				$base + array(
					'issue_type'  => 'unregistered_shortcode',
					'severity'    => 'high',
					'title'       => sprintf( 'شورت‌کد ناشناخته در «%s»', get_the_title( $post_id ) ),
					'description' => 'این شورت‌کدها هیچ هندلری ندارند و به‌صورت متن خام نمایش داده می‌شوند: ' . implode( ', ', $shortcodes ),
					'suggestion'  => ' افزونه مربوطه را فعال کن یا شورت‌کد را از محتوا حذف کن.',
					'fix_type'    => 'manual',
				)
			);
			++$found;
		}

		// ۱۱) ووکامرس — ممیزی تخصصی محصول.
		if ( 'product' === $post->post_type && self::has_product_audit() ) {
			$found += self::audit_product( $post_id, $post, $content, $plain, $words );
		}

		return $found;
	}

	/**
	 * ممیزی تصویر رسانه.
	 *
	 * @param int $attachment_id شناسه.
	 * @return int
	 */
	public static function audit_attachment( int $attachment_id ): int {
		if ( ! wp_attachment_is_image( $attachment_id ) ) {
			return 0;
		}
		// تصاویر داخل محصول را ممیزی تخصصی محصول با payload دقیق‌تر پوشش می‌دهد؛
		// بازنویسی با هندلر عمومی، context محصول را از دست می‌داد.
		if ( self::has_product_audit() && 'product' === get_post_type( (int) wp_get_post_parent_id( $attachment_id ) ) ) {
			return 0;
		}
		$alt = trim( (string) get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ) );
		if ( '' !== $alt && ! self::is_bad_alt( $alt, $attachment_id ) ) {
			return 0;
		}

		GSO_Issues::upsert(
			array(
				'object_id'   => $attachment_id,
				'object_type' => 'attachment',
				'issue_type'  => 'attachment_missing_alt',
				'severity'    => 'high',
				'title'       => sprintf( 'تصویر بدون Alt: %s', get_the_title( $attachment_id ) ),
				'description' => '' === $alt ? 'متن جایگزین این تصویر خالی است.' : 'متن جایگزین نامناسب است: ' . $alt,
				'suggestion'  => ' متن Alt توصیفی و کوتاه تولید و ذخیره کن (کم‌ریسک و قابل بازگشت).',
				'fix_type'    => 'set_alt',
				'fix_payload' => array( 'handler' => 'set_alt', 'safe' => true ),
			)
		);
		return 1;
	}

	/**
	 * ممیزی یک ترم.
	 *
	 * @param int $term_id شناسه.
	 * @return int
	 */
	public static function audit_term( int $term_id ): int {
		$term = get_term( $term_id );
		if ( ! $term || is_wp_error( $term ) || (int) $term->count < 1 ) {
			return 0;
		}
		if ( '' !== trim( (string) $term->description ) ) {
			return 0;
		}
		GSO_Issues::upsert(
			array(
				'object_id'   => $term_id,
				'object_type' => 'term',
				'issue_type'  => 'empty_term_description',
				'severity'    => 'low',
				'title'       => sprintf( 'دسته/برچسب بدون توضیح: %s', $term->name ),
				'description' => 'این ترم توضیح ندارد و صفحه آرشیو آن ارزش سئویی کمتری دارد.',
				'suggestion'  => ' یک توضیح دو تا سه جمله‌ای با کلیدواژه مرتبط تولید کن.',
				'fix_type'    => 'set_term_description',
				'fix_payload' => array( 'handler' => 'set_term_description', 'safe' => true, 'taxonomy' => $term->taxonomy ),
			)
		);
		return 1;
	}

	/**
	 * ممیزی همه منوها.
	 *
	 * @return int
	 */
	public static function audit_menus(): int {
		$menus = wp_get_nav_menus();
		if ( empty( $menus ) || is_wp_error( $menus ) ) {
			return 0;
		}
		$found = 0;
		foreach ( $menus as $menu ) {
			$items = wp_get_nav_menu_items( $menu->term_id );
			if ( empty( $items ) ) {
				GSO_Issues::upsert(
					array(
						'object_id'   => (int) $menu->term_id,
						'object_type' => 'menu',
						'issue_type'  => 'broken_menu_item',
						'severity'    => 'low',
						'title'       => sprintf( 'منوی خالی: %s', $menu->name ),
						'description' => 'این منو هیچ آیتمی ندارد.',
						'suggestion'  => ' آیتم‌های ضروری را به منو اضافه کن یا منو را حذف کن.',
						'fix_type'    => 'manual',
					)
				);
				++$found;
				continue;
			}
			foreach ( $items as $item ) {
				$invalid = false;
				if ( in_array( $item->type, array( 'post_type', 'taxonomy' ), true ) && (int) $item->object_id > 0 ) {
					$target = get_post( (int) $item->object_id );
					$valid_statuses = array( 'publish', 'inherit', 'private' );
					if ( ( 'post_type' === $item->type && ( ! $target || ! in_array( $target->post_status, $valid_statuses, true ) ) )
						|| ( 'taxonomy' === $item->type && ! term_exists( (int) $item->object_id, (string) $item->object ) ) ) {
						$invalid = true;
					}
				}
				if ( ! $invalid && '' === trim( (string) $item->url ) && '' === trim( (string) $item->title ) ) {
					$invalid = true;
				}
				if ( $invalid ) {
					GSO_Issues::upsert(
						array(
							'object_id'   => (int) $menu->term_id,
							'object_type' => 'menu',
							'issue_type'  => 'broken_menu_item_' . (int) $item->ID,
							'severity'    => 'high',
							'title'       => sprintf( 'آیتم شکسته در منوی «%s»', $menu->name ),
							'description' => sprintf( 'آیتم «%s» به محتوایی اشاره می‌کند که وجود ندارد یا حذف شده است.', wp_strip_all_tags( $item->title ) ),
							'suggestion'  => ' آیتم را از منو حذف یا به مقصد معتبر وصل کن.',
							'fix_type'    => 'manual',
						)
					);
					++$found;
				}
			}
		}
		return $found;
	}

	/**
	 * ممیزی سطح سایت (فنی/سلامت).
	 *
	 * @return int
	 */
	public static function audit_site(): int {
		$found = 0;

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			GSO_Issues::upsert(
				array(
					'object_id'   => 0,
					'object_type' => 'site',
					'issue_type'  => 'debug_mode_enabled',
					'severity'    => 'high',
					'title'       => 'حالت WP_DEBUG روی سایت فعال است',
					'description' => 'نمایش خطاها در محیط عملیاتی می‌تواند مسیر فایل‌ها و اطلاعات حساس را افشا کند.',
					'suggestion'  => ' پس از پایان عیب‌یابی، WP_DEBUG را در wp-config.php خاموش کن (اصلاح خودکار انجام نمی‌شود).',
					'fix_type'    => 'manual',
				)
			);
			++$found;
		}

		$log = trailingslashit( WP_CONTENT_DIR ) . 'debug.log';
		if ( file_exists( $log ) ) {
			$size = (int) @filesize( $log );
			if ( $size > MB_IN_BYTES ) {
				GSO_Issues::upsert(
					array(
						'object_id'   => 0,
						'object_type' => 'site',
						'issue_type'  => 'debug_log_large',
						'severity'    => $size > 5 * MB_IN_BYTES ? 'high' : 'low',
						'title'       => 'فایل debug.log بزرگ شده است',
						'description' => sprintf( 'حجم فایل خطا %s است.', size_format( $size ) ),
						'suggestion'  => ' فایل لاگ را پس از بررسی خالی کن (قابل بازگشت در snapshot لاگ ثبت می‌شود).',
						'fix_type'    => 'clear_debug_log',
						'fix_payload' => array( 'handler' => 'clear_debug_log', 'safe' => false ),
					)
				);
				++$found;
			}
		}

		if ( '' === (string) get_option( 'permalink_structure', '' ) ) {
			GSO_Issues::upsert(
				array(
					'object_id'   => 0,
					'object_type' => 'site',
					'issue_type'  => 'plain_permalinks',
					'severity'    => 'high',
					'title'       => 'ساختار پیوند یکتا ساده است',
					'description' => 'پیوندهای ?p=123 نه خوانا هستند و نه بهینه سئو.',
					'suggestion'  => ' از تنظیمات → پیوندهای یکتا، ساختار «نام نوشته» را انتخاب کن.',
					'fix_type'    => 'manual',
				)
			);
			++$found;
		}

		return $found;
	}

	/**
	 * بررسی تصاویر درون محتوا.
	 *
	 * @param int    $post_id شناسه.
	 * @param string $content محتوا.
	 * @return int
	 */
	private static function audit_inline_images( int $post_id, string $content ): int {
		if ( ! preg_match_all( '/<img\b[^>]*>/i', $content, $matches ) ) {
			return 0;
		}
		$found = 0;
		$seen  = array();
		foreach ( $matches[0] as $tag ) {
			$alt = '';
			if ( preg_match( '/\balt\s*=\s*"([^"]*)"/i', $tag, $m ) ) {
				$alt = trim( $m[1] );
			} elseif ( preg_match( "/\\balt\\s*=\\s*'([^']*)'/i", $tag, $m ) ) {
				$alt = trim( $m[1] );
			}
			$src = '';
			if ( preg_match( '/\bsrc\s*=\s*["\']([^"\']+)["\']/i', $tag, $m ) ) {
				$src = $m[1];
			}
			$attachment_id = 0;
			if ( preg_match( '/wp-image-(\d+)/', $tag, $m ) ) {
				$attachment_id = (int) $m[1];
			}
			if ( '' === $alt && $attachment_id > 0 ) {
				$alt = trim( (string) get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ) );
			}
			if ( '' === $alt ) {
				$key = md5( $tag );
				if ( isset( $seen[ $key ] ) ) {
					continue;
				}
				$seen[ $key ] = true;

				GSO_Issues::upsert(
					array(
						'object_id'   => $post_id,
						'object_type' => get_post_type( $post_id ) ? get_post_type( $post_id ) : 'post',
						'issue_type'  => 'image_missing_alt_' . substr( $key, 0, 8 ),
						'severity'    => 'high',
						'title'       => sprintf( 'تصویر بدون Alt در «%s»', get_the_title( $post_id ) ),
						'description' => 'یک تصویر در محتوا متن جایگزین ندارد' . ( $src ? ' (منبع: ' . esc_url_raw( $src ) . ')' : '' ) . '.',
						'suggestion'  => ' متن Alt توصیفی تولید و در همان تگ تصویر جایگزین کن.',
						'fix_type'    => 'set_inline_alt',
						'fix_payload' => array(
							'handler'       => 'set_inline_alt',
							'safe'          => true,
							'attachment_id' => $attachment_id,
							'src'           => esc_url_raw( $src ),
							'tag'           => $tag,
						),
					)
				);
				++$found;
			}
		}
		return $found;
	}

	/**
	 * بررسی لینک‌های محتوا (با کش هفت‌روزه).
	 *
	 * @param int    $post_id شناسه.
	 * @param string $content محتوا.
	 * @return int
	 */
	private static function audit_links( int $post_id, string $content ): int {
		$urls = self::extract_links( $content );
		if ( empty( $urls ) ) {
			return 0;
		}
		$limit = (int) GSO_Settings::get( 'link_check_limit', 12 );
		$urls  = array_slice( $urls, 0, $limit );

		$found = 0;
		foreach ( $urls as $url ) {
			if ( self::$deadline > 0 && time() >= self::$deadline ) {
				break;
			}
			$status = self::link_status( $url );
			if ( false !== $status ) {
				continue;
			}
			GSO_Issues::upsert(
				array(
					'object_id'   => $post_id,
					'object_type' => get_post_type( $post_id ) ? get_post_type( $post_id ) : 'post',
					'issue_type'  => 'broken_link_' . substr( md5( $url ), 0, 10 ),
					'severity'    => 'critical',
					'title'       => sprintf( 'لینک شکسته در «%s»', get_the_title( $post_id ) ),
					'description' => 'این لینک پاسخ معتبر برنگرداند: ' . esc_url_raw( $url ),
					'suggestion'  => ' لینک را اصلاح یا حذف کن (حذف لینک شکسته با تاییدیه اپراتور انجام می‌شود).',
					'fix_type'    => 'remove_broken_link',
					'fix_payload' => array( 'handler' => 'remove_broken_link', 'safe' => false, 'url' => esc_url_raw( $url ) ),
				)
			);
			++$found;
		}
		return $found;
	}

	/**
	 * استخراج لینک‌های قابل بررسی.
	 *
	 * @param string $content محتوا.
	 * @return array
	 */
	public static function extract_links( string $content ): array {
		if ( ! preg_match_all( '/<a\b[^>]*href\s*=\s*["\']([^"\']+)["\']/i', $content, $matches ) ) {
			return array();
		}
		$urls = array();
		foreach ( $matches[1] as $url ) {
			$url = trim( html_entity_decode( $url ) );
			if ( '' === $url || 0 === strpos( $url, '#' ) || preg_match( '#^(mailto|tel|javascript|data):#i', $url ) ) {
				continue;
			}
			if ( 0 === strpos( $url, '/' ) ) {
				$url = home_url( $url );
			}
			$url = esc_url_raw( $url );
			if ( ! $url || ! wp_http_validate_url( $url ) ) {
				continue;
			}
			$urls[ $url ] = $url;
		}
		return array_values( $urls );
	}

	/**
	 * وضعیت لینک: true سالم، false شکسته، null نامشخص.
	 *
	 * @param string $url نشانی.
	 * @return bool|null
	 */
	public static function link_status( string $url ): ?bool {
		$cache_key = 'gso_link_' . md5( $url );
		$cached    = get_transient( $cache_key );
		if ( is_array( $cached ) && isset( $cached['ok'] ) ) {
			return (bool) $cached['ok'];
		}

		$response = wp_remote_head(
			$url,
			array(
				'timeout'     => 4,
				'redirection' => 3,
				'user-agent'  => 'GeminiSiteOperator/' . GSO_VERSION . '; ' . home_url(),
			)
		);

		$code = is_wp_error( $response ) ? 0 : (int) wp_remote_retrieve_response_code( $response );
		if ( in_array( $code, array( 403, 405, 501 ), true ) ) {
			$response = wp_remote_get( $url, array( 'timeout' => 4, 'redirection' => 3 ) );
			$code     = is_wp_error( $response ) ? 0 : (int) wp_remote_retrieve_response_code( $response );
		}

		if ( $code >= 200 && $code < 400 ) {
			set_transient( $cache_key, array( 'ok' => true ), 7 * DAY_IN_SECONDS );
			return true;
		}
		if ( in_array( $code, array( 404, 410, 500, 502, 503 ), true ) ) {
			set_transient( $cache_key, array( 'ok' => false ), DAY_IN_SECONDS );
			return false;
		}
		return null;
	}

	/**
	 * تحلیل هوشمند یک صفحه با Gemini (اختیاری و پرداختی به‌ازای هر فراخوانی).
	 *
	 * @param int $post_id شناسه.
	 * @return array|WP_Error تعداد پیشنهادها.
	 */
	public static function ai_review_post( int $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return new WP_Error( 'gso_missing', 'صفحه پیدا نشد.' );
		}

		$content = wp_strip_all_tags( strip_shortcodes( (string) $post->post_content ) );
		$content = trim( preg_replace( '/\s+/u', ' ', $content ) );
		$content = mb_substr( $content, 0, 6000 );

		$object_type = in_array( $post->post_type, GSO_Contract::object_types(), true ) ? $post->post_type : 'post';
		$prompt      = "این صفحه وردپرس را از نظر سئو، تجربه کاربری، ساختار محتوا و نرخ تبدیل بررسی کن.\n"
			. 'عنوان: ' . $post->post_title . "\n"
			. 'نوع: ' . $post->post_type . "\n"
			. "متن:\n" . $content . "\n\n"
			. 'حداکثر ۶ ایراد واقعی و قابل‌اجرا بده و هر ایراد را با فیلدهای قرارداد در آرایه issues ثبت کن. از کلی‌گویی پرهیز کن.';

		$schema = GSO_Contract::envelope_schema( 'audit', $object_type );
		$result = GSO_Gemini::json(
			$prompt,
			$schema,
			array(
				'model_kind'  => 'bulk',
				'contract'    => true,
				'mode'        => 'audit',
				'object_type' => $object_type,
			)
		);
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$count = 0;
		foreach ( (array) ( $result['issues'] ?? array() ) as $item ) {
			$type = sanitize_key( (string) ( $item['issue_id'] ?? 'ai_suggestion' ) );
			if ( '' === $type ) {
				$type = 'ai_suggestion';
			}
			$severity = in_array( $item['severity'] ?? '', GSO_Contract::severities(), true ) ? $item['severity'] : 'low';
			$title    = (string) ( $item['title'] ?? '' );
			if ( '' === $title ) {
				$title = sprintf( 'پیشنهاد هوش مصنوعی برای «%s»', $post->post_title );
			}
			$suggestion = (string) ( $item['recommended_action'] ?? '' );
			if ( '' === $suggestion ) {
				$suggestion = (string) ( $item['current_value_excerpt'] ?? '' );
			}
			GSO_Issues::upsert(
				array(
					'object_id'   => $post_id,
					'object_type' => $object_type,
					'issue_type'  => 'ai_' . $type,
					'severity'    => $severity,
					'title'       => $title,
					'description' => (string) ( $item['description'] ?? '' ),
					'suggestion'  => $suggestion,
					'fix_type'    => 'manual',
					'fix_payload' => array(
						'category'     => (string) ( $item['category'] ?? '' ),
						'field_path'   => (string) ( $item['field_path'] ?? '' ),
						'auto_fix_safe' => ! empty( $item['auto_fix_safe'] ),
					),
				)
			);
			++$count;
		}

		GSO_Logger::log( 'info', 'audit.ai_review', sprintf( 'تحلیل هوشمند «%s» انجام شد: %d پیشنهاد.', $post->post_title, $count ), array( 'post_id' => $post_id ) );
		return array( 'count' => $count );
	}

	/**
	 * آیا ممیزی تخصصی محصولات فعال است؟
	 *
	 * @return bool
	 */
	private static function has_product_audit(): bool {
		return GSO_Settings::has_woocommerce() && (bool) GSO_Settings::get( 'woo_audit_enabled', true );
	}

	/**
	 * شناسه تنوع‌های محصول.
	 *
	 * @param int $offset شروع.
	 * @param int $limit  تعداد.
	 * @return array
	 */
	private static function variation_ids( int $offset, int $limit ): array {
		$ids = get_posts(
			array(
				'post_type'              => 'product_variation',
				'post_status'            => 'publish',
				'fields'                 => 'ids',
				'posts_per_page'         => $limit,
				'offset'                 => $offset,
				'orderby'                => 'ID',
				'order'                  => 'ASC',
				'no_found_rows'          => true,
				'cache_results'          => false,
				'update_post_term_cache' => false,
				'update_post_meta_cache' => false,
			)
		);
		return array_map( 'intval', $ids );
	}

	/**
	 * شمارش تنوع‌های منتشرشده.
	 *
	 * @return int
	 */
	private static function count_variations(): int {
		$count = wp_count_posts( 'product_variation' );
		return (int) ( $count->publish ?? 0 );
	}

	/**
	 * ممیزی یک تنوع محصول (قیمت و موجودی).
	 *
	 * @param int $post_id شناسه تنوع.
	 * @return int تعداد ایراد.
	 */
	public static function audit_variation( int $post_id ): int {
		$post = get_post( $post_id );
		if ( ! $post || 'product_variation' !== $post->post_type || 'trash' === $post->post_status || 'publish' !== $post->post_status ) {
			return 0;
		}
		$parent = (int) $post->post_parent;
		$label  = $parent > 0 ? sprintf( '«%s» (تنوع #%d)', get_the_title( $parent ), $post_id ) : ( 'تنوع #' . $post_id );
		$base   = array(
			'object_id'   => $post_id,
			'object_type' => 'product_variation',
		);
		$found  = 0;

		$price = (string) get_post_meta( $post_id, '_price', true );
		if ( '' === $price ) {
			$price = (string) get_post_meta( $post_id, '_regular_price', true );
		}
		if ( '' === trim( $price ) ) {
			GSO_Issues::upsert(
				$base + array(
					'issue_type'  => 'woo_variation_missing_price',
					'severity'    => 'critical',
					'title'       => sprintf( 'نبود قیمت برای تنوع %s', $label ),
					'description' => 'قیمت این تنوع محصول ثبت نشده و قابل خرید نیست.',
					'suggestion'  => ' قیمت تنوع را دستی در ویرایشگر محصول تنظیم کن؛ افزونه قیمت حدس نمی‌زند.',
					'fix_type'    => 'manual',
				)
			);
			++$found;
		}

		$stock = (string) get_post_meta( $post_id, '_stock_status', true );
		if ( ! in_array( $stock, array( 'instock', 'outofstock', 'onbackorder' ), true ) ) {
			GSO_Issues::upsert(
				$base + array(
					'issue_type'  => 'woo_variation_stock_unclear',
					'severity'    => 'high',
					'title'       => sprintf( 'وضعیت موجودی نامشخص تنوع %s', $label ),
					'description' => 'وضعیت موجودی این تنوع یکی از instock/outofstock/onbackorder نیست.',
					'suggestion'  => ' وضعیت موجودی تنوع را دستی تعیین کن.',
					'fix_type'    => 'manual',
				)
			);
			++$found;
		}

		return $found;
	}

	/**
	 * ممیزی تخصصی یک محصول ووکامرس.
	 *
	 * @param int     $post_id شناسه.
	 * @param WP_Post $post    محصول.
	 * @param string  $content محتوا.
	 * @param string  $plain   متن ساده.
	 * @param int     $words   تعداد کلمات.
	 * @return int تعداد ایراد.
	 */
	private static function audit_product( int $post_id, WP_Post $post, string $content, string $plain, int $words ): int {
		$title   = get_the_title( $post_id );
		$product = function_exists( 'wc_get_product' ) ? wc_get_product( $post_id ) : null;
		$base    = array(
			'object_id'   => $post_id,
			'object_type' => 'product',
		);
		$found = 0;

		// ۱) توضیحات کامل.
		if ( '' === trim( $plain ) ) {
			GSO_Issues::upsert(
				$base + array(
					'issue_type'  => 'woo_missing_description',
					'severity'    => 'critical',
					'title'       => sprintf( 'محصول بدون توضیحات کامل «%s»', $title ),
					'description' => 'توضیحات کامل محصول خالی است.',
					'suggestion'  => ' توضیحات ساختاریافته (معرفی، مشخصات، مزایا، کاربرد و پرسش‌های متداول) تولید و اعمال کن.',
					'fix_type'    => 'structure_product_content',
					'fix_payload' => array( 'handler' => 'structure_product_content', 'safe' => false ),
				)
			);
			++$found;
		} elseif ( $words < 120 ) {
			GSO_Issues::upsert(
				$base + array(
					'issue_type'  => 'woo_missing_description',
					'severity'    => 'high',
					'title'       => sprintf( 'توضیحات ناقص محصول «%s»', $title ),
					'description' => sprintf( 'توضیحات محصول فقط %d کلمه است؛ حداقل ۱۲۰ کلمه توصیه می‌شود.', $words ),
					'suggestion'  => ' توضیحات کامل با ویژگی‌ها، کاربرد و پرسش‌های متداول تولید کن.',
					'fix_type'    => 'structure_product_content',
					'fix_payload' => array( 'handler' => 'structure_product_content', 'safe' => false ),
				)
			);
			++$found;
		}

		// ۲) توضیحات کوتاه.
		$excerpt     = trim( wp_strip_all_tags( strip_shortcodes( (string) $post->post_excerpt ) ) );
		$min_short   = (int) GSO_Settings::get( 'woo_short_desc_words', 20 );
		$short_words = self::word_count( $excerpt );
		if ( '' === $excerpt ) {
			GSO_Issues::upsert(
				$base + array(
					'issue_type'  => 'woo_missing_short_description',
					'severity'    => 'high',
					'title'       => sprintf( 'نبود توضیح کوتاه محصول «%s»', $title ),
					'description' => 'توضیحات کوتاه محصول خالی است و در فهرست محصولات و نتایج جست‌وجو نمایش مناسبی ندارد.',
					'suggestion'  => ' یک توضیح کوتاه مزیت‌محور تولید و اعمال کن (بدون جعل مشخصات).',
					'fix_type'    => 'set_short_description',
					'fix_payload' => array( 'handler' => 'set_short_description', 'safe' => true ),
				)
			);
			++$found;
		} elseif ( $short_words < $min_short ) {
			GSO_Issues::upsert(
				$base + array(
					'issue_type'  => 'woo_short_description_short',
					'severity'    => 'low',
					'title'       => sprintf( 'توضیح کوتاه کم‌حجم محصول «%s»', $title ),
					'description' => sprintf( 'توضیح کوتاه %d کلمه است؛ حد مطلوب %d کلمه.', $short_words, $min_short ),
					'suggestion'  => ' توضیح کوتاه را با تمرکز بر مزیت خرید بازنویسی کن.',
					'fix_type'    => 'set_short_description',
					'fix_payload' => array( 'handler' => 'set_short_description', 'safe' => true ),
				)
			);
			++$found;
		}

		// ۳) تصویر شاخص و گالری.
		$thumbnail = (int) get_post_thumbnail_id( $post_id );
		if ( $thumbnail < 1 ) {
			GSO_Issues::upsert(
				$base + array(
					'issue_type'  => 'woo_missing_image',
					'severity'    => 'high',
					'title'       => sprintf( 'نبود تصویر شاخص محصول «%s»', $title ),
					'description' => 'این محصول تصویر شاخص ندارد.',
					'suggestion'  => ' یک تصویر شاخص با Alt مناسب اضافه کن.',
					'fix_type'    => 'manual',
				)
			);
			++$found;
		} else {
			$found += self::product_image_alt( $thumbnail, $post_id, $title, true );
		}

		$gallery = self::gallery_ids( $post_id );
		if ( empty( $gallery ) && GSO_Settings::get( 'woo_require_gallery', false ) ) {
			GSO_Issues::upsert(
				$base + array(
					'issue_type'  => 'woo_missing_gallery',
					'severity'    => 'low',
					'title'       => sprintf( 'نبود گالری تصویر محصول «%s»', $title ),
					'description' => 'برای این محصول تصویر گالری ثبت نشده است.',
					'suggestion'  => ' چند تصویر گالری واقعی از محصول اضافه کن (افزونه تصویر نمی‌سازد).',
					'fix_type'    => 'manual',
				)
			);
			++$found;
		}
		foreach ( $gallery as $image_id ) {
			$found += self::product_image_alt( (int) $image_id, $post_id, $title, false );
		}

		// ۴) قیمت (قیمت محصول متغیر از تنوع‌ها می‌آید و اینجا بررسی نمی‌شود).
		$is_variable = $product && $product->is_type( 'variable' );
		$price       = (string) get_post_meta( $post_id, '_price', true );
		if ( '' === trim( $price ) ) {
			$price = (string) get_post_meta( $post_id, '_regular_price', true );
		}
		if ( ! $is_variable && '' === trim( $price ) ) {
			GSO_Issues::upsert(
				$base + array(
					'issue_type'  => 'woo_missing_price',
					'severity'    => 'critical',
					'title'       => sprintf( 'نبود قیمت محصول «%s»', $title ),
					'description' => 'برای این محصول قیمت ثبت نشده و قابل خرید نیست.',
					'suggestion'  => ' قیمت را دستی در ویرایشگر محصول تنظیم کن؛ افزونه قیمت حدس نمی‌زند.',
					'fix_type'    => 'manual',
				)
			);
			++$found;
		}

		// ۵) وضعیت موجودی.
		$stock = (string) get_post_meta( $post_id, '_stock_status', true );
		if ( ! in_array( $stock, array( 'instock', 'outofstock', 'onbackorder' ), true ) ) {
			GSO_Issues::upsert(
				$base + array(
					'issue_type'  => 'woo_stock_unclear',
					'severity'    => 'high',
					'title'       => sprintf( 'وضعیت موجودی نامشخص محصول «%s»', $title ),
					'description' => '' === $stock ? 'وضعیت موجودی محصول ثبت نشده است.' : ( 'مقدار نامعتبر برای موجودی: ' . $stock ),
					'suggestion'  => ' وضعیت موجودی را دستی تعیین کن؛ افزونه موجودی را حدس نمی‌زند.',
					'fix_type'    => 'manual',
				)
			);
			++$found;
		}

		// ۶) دسته‌بندی و برچسب.
		$cats = wp_get_post_terms( $post_id, 'product_cat', array( 'fields' => 'ids' ) );
		if ( is_wp_error( $cats ) || empty( $cats ) ) {
			GSO_Issues::upsert(
				$base + array(
					'issue_type'  => 'woo_missing_category',
					'severity'    => 'high',
					'title'       => sprintf( 'محصول بدون دسته‌بندی «%s»', $title ),
					'description' => 'این محصول در هیچ دسته‌بندی محصولی قرار ندارد.',
					'suggestion'  => ' بر اساس ساختار موجود فروشگاه دسته‌بندی مرتبط را پیشنهاد/اعمال کن.',
					'fix_type'    => 'assign_product_terms',
					'fix_payload' => array( 'handler' => 'assign_product_terms', 'safe' => false, 'kind' => 'category' ),
				)
			);
			++$found;
		}

		$tags = wp_get_post_terms( $post_id, 'product_tag', array( 'fields' => 'ids' ) );
		if ( is_wp_error( $tags ) || empty( $tags ) ) {
			GSO_Issues::upsert(
				$base + array(
					'issue_type'  => 'woo_missing_tag',
					'severity'    => 'low',
					'title'       => sprintf( 'محصول بدون برچسب «%s»', $title ),
					'description' => 'این محصول برچسب ندارد و یافتن آن از مسیر برچسب‌ها سخت‌تر است.',
					'suggestion'  => ' برچسب‌های مرتبط را پیشنهاد/اعمال کن.',
					'fix_type'    => 'assign_product_terms',
					'fix_payload' => array( 'handler' => 'assign_product_terms', 'safe' => false, 'kind' => 'tag' ),
				)
			);
			++$found;
		}

		// ۷) یکنواختی ساختار معرفی.
		if ( '' !== trim( $plain ) && ! self::product_structure_ok( $content ) ) {
			GSO_Issues::upsert(
				$base + array(
					'issue_type'  => 'woo_inconsistent_structure',
					'severity'    => 'low',
					'title'       => sprintf( 'ساختار معرفی ناهمگون محصول «%s»', $title ),
					'description' => 'توضیحات محصول بخش‌بندی یکنواخت (حداقل دو H2، فهرست مشخصات و بخش مزایا/کاربرد) ندارد.',
					'suggestion'  => ' توضیحات را با ساختار استاندارد معرفی/مشخصات/مزایا/کاربرد بازسازی کن.',
					'fix_type'    => 'structure_product_content',
					'fix_payload' => array( 'handler' => 'structure_product_content', 'safe' => false ),
				)
			);
			++$found;
		}

		return $found;
	}

	/**
	 * بررسی Alt تصویر شاخص/گالری محصول.
	 *
	 * @param int    $attachment_id شناسه پیوست.
	 * @param int    $product_id    شناسه محصول.
	 * @param string $product_title عنوان محصول.
	 * @param bool   $featured      تصویر شاخص.
	 * @return int
	 */
	private static function product_image_alt( int $attachment_id, int $product_id, string $product_title, bool $featured ): int {
		if ( $attachment_id < 1 || ! wp_attachment_is_image( $attachment_id ) ) {
			return 0;
		}
		$alt = trim( (string) get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ) );
		if ( '' !== $alt && ! self::is_bad_alt( $alt, $attachment_id ) ) {
			return 0;
		}

		GSO_Issues::upsert(
			array(
				'object_id'   => $attachment_id,
				'object_type' => 'attachment',
				'issue_type'  => 'attachment_missing_alt',
				'severity'    => 'high',
				'title'       => sprintf( 'تصویر %s محصول «%s» بدون Alt', $featured ? 'شاخص' : 'گالری', $product_title ),
				'description' => '' === $alt ? 'متن جایگزین این تصویر محصول خالی است.' : 'متن جایگزین نامناسب است: ' . $alt,
				'suggestion'  => ' متن Alt توصیفی مرتبط با نام و ویژگی‌های همین محصول تولید و ذخیره کن.',
				'fix_type'    => 'set_product_alt',
				'fix_payload' => array(
					'handler'    => 'set_product_alt',
					'safe'       => true,
					'product_id' => $product_id,
					'featured'   => $featured,
				),
			)
		);
		return 1;
	}

	/**
	 * شناسه تصاویر گالری محصول.
	 *
	 * @param int $post_id شناسه.
	 * @return array
	 */
	public static function gallery_ids( int $post_id ): array {
		$raw = (string) get_post_meta( $post_id, '_product_image_gallery', true );
		if ( '' === trim( $raw ) ) {
			return array();
		}
		return array_values( array_filter( array_map( 'absint', explode( ',', $raw ) ) ) );
	}

	/**
	 * آیا ساختار معرفی محصول یکنواخت و کامل است؟
	 *
	 * @param string $content محتوا.
	 * @return bool
	 */
	public static function product_structure_ok( string $content ): bool {
		$headings = self::headings( $content );
		if ( (int) ( $headings['counts'][2] ?? 0 ) < 2 ) {
			return false;
		}
		if ( ! preg_match( '/<(ul|ol|table)\b/i', $content ) ) {
			return false;
		}
		$plain = self::lower( wp_strip_all_tags( $content ) );
		foreach ( array( 'مشخصات', 'ویژگی', 'مزیت', 'کاربرد', 'نحوه استفاده' ) as $needle ) {
			if ( false !== strpos( $plain, $needle ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * بررسی نبود لینک داخلی در محتوای بلند.
	 *
	 * @param int    $post_id شناسه.
	 * @param string $content محتوا.
	 * @param int    $words   تعداد کلمات.
	 * @return int
	 */
	private static function audit_internal_links( int $post_id, string $content, int $words ): int {
		$min = (int) GSO_Settings::get( 'internal_link_min_words', 300 );
		if ( 'product' === get_post_type( $post_id ) ) {
			$min = min( 120, max( 50, $min ) );
		}
		if ( $words < $min ) {
			return 0;
		}

		$internal = 0;
		$home     = home_url( '/' );
		if ( preg_match_all( '/<a\b[^>]*href\s*=\s*["\']([^"\']+)["\']/i', $content, $matches ) ) {
			foreach ( $matches[1] as $url ) {
				$url = trim( html_entity_decode( $url ) );
				if ( 0 === strpos( $url, '/' ) && 0 !== strpos( $url, '//' ) ) {
					++$internal;
				} elseif ( '' !== $home && false !== strpos( $url, $home ) ) {
					++$internal;
				}
			}
		}
		if ( $internal > 0 ) {
			return 0;
		}

		$is_product = 'product' === get_post_type( $post_id );
		GSO_Issues::upsert(
			array(
				'object_id'   => $post_id,
				'object_type' => get_post_type( $post_id ) ? get_post_type( $post_id ) : 'post',
				'issue_type'  => 'missing_internal_links',
				'severity'    => 'low',
				'title'       => sprintf( 'نبود لینک داخلی در «%s»', get_the_title( $post_id ) ),
				'description' => 'این محتوا هیچ لینک داخلی ندارد و ارزش انتقال اعتبار و مسیر کاربر را از دست می‌دهد.',
				'suggestion'  => $is_product ? ' به محصولات یا دسته‌های مکمل لینک داخلی هدفمند اضافه کن.' : ' به صفحات مرتبط لینک داخلی هدفمند اضافه کن.',
				'fix_type'    => $is_product ? 'insert_product_links' : 'insert_internal_links',
				'fix_payload' => array( 'handler' => $is_product ? 'insert_product_links' : 'insert_internal_links', 'safe' => false ),
			)
		);
		return 1;
	}

	/**
	 * پاک‌سازی نمایه تکراری‌ها در شروع اسکن.
	 */
	private static function reset_index(): void {
		update_option( self::INDEX_OPTION, array( 'titles' => array(), 'descs' => array(), 'content' => array() ), false );
	}

	/**
	 * نرمال‌سازی متن برای مقایسه تکراری.
	 *
	 * @param string $text متن.
	 * @return string
	 */
	private static function normalize_index_text( string $text ): string {
		$text = wp_strip_all_tags( strip_shortcodes( $text ) );
		$text = preg_replace( '/[\x{200C}\x{200F}\x{200E}]/u', '', $text );
		$text = preg_replace( '/\s+/u', ' ', (string) $text );
		$text = trim( (string) $text );
		return self::lower( $text );
	}

	/**
	 * حروف کوچک، با یا بدون افزونه mbstring (وردپرس فقط mb_substr/mb_strlen را جایگزین می‌کند).
	 *
	 * @param string $text متن.
	 * @return string
	 */
	private static function lower( string $text ): string {
		return function_exists( 'mb_strtolower' ) ? mb_strtolower( $text ) : strtolower( $text );
	}

	/**
	 * افزودن عنوان/توضیح/محتوای صفحه به نمایه تکراری‌ها.
	 *
	 * @param int    $post_id شناسه.
	 * @param string $title   عنوان سئو.
	 * @param string $desc    توضیحات متا.
	 * @param string $content محتوا.
	 * @param int    $words   تعداد کلمات.
	 */
	private static function index_meta( int $post_id, string $title, string $desc, string $content, int $words ): void {
		if ( ! GSO_Settings::get( 'seo_duplicate_check', true ) ) {
			return;
		}
		$index = get_option( self::INDEX_OPTION, array() );
		$index = is_array( $index ) ? wp_parse_args( $index, array( 'titles' => array(), 'descs' => array(), 'content' => array() ) ) : array( 'titles' => array(), 'descs' => array(), 'content' => array() );

		$title_key = self::normalize_index_text( $title );
		if ( function_exists( 'mb_strlen' ) ? mb_strlen( $title_key ) >= 10 : strlen( $title_key ) >= 10 ) {
			$key = 't' . md5( $title_key );
			if ( isset( $index['titles'][ $key ] ) || count( $index['titles'] ) < 20000 ) {
				$index['titles'][ $key ][ $post_id ] = $post_id;
			}
		}

		$desc_key = self::normalize_index_text( $desc );
		if ( function_exists( 'mb_strlen' ) ? mb_strlen( $desc_key ) >= 20 : strlen( $desc_key ) >= 20 ) {
			$key = 'd' . md5( $desc_key );
			if ( isset( $index['descs'][ $key ] ) || count( $index['descs'] ) < 20000 ) {
				$index['descs'][ $key ][ $post_id ] = $post_id;
			}
		}

		if ( $words >= 150 ) {
			$content_key = self::normalize_index_text( $content );
			if ( function_exists( 'mb_strlen' ) ? mb_strlen( $content_key ) >= 200 : strlen( $content_key ) >= 200 ) {
				$key = 'c' . md5( $content_key );
				if ( isset( $index['content'][ $key ] ) || count( $index['content'] ) < 20000 ) {
					$index['content'][ $key ][ $post_id ] = $post_id;
				}
			}
		}

		update_option( self::INDEX_OPTION, $index, false );
	}

	/**
	 * تبدیل نمایه به ایرادهای «تکراری» در پایان اسکن.
	 */
	private static function flush_duplicate_index(): void {
		$index = get_option( self::INDEX_OPTION, array() );
		delete_option( self::INDEX_OPTION );
		if ( ! is_array( $index ) ) {
			return;
		}

		$maps = array(
			'titles'  => array(
				'issue_type' => 'duplicate_meta_title',
				'fix_type'   => 'set_meta_title',
				'label'      => 'عنوان سئو',
				'suggestion' => ' برای هر صفحه عنوان سئوی یکتا و مرتبط با نیت جست‌وجو تولید و اعمال کن.',
			),
			'descs'   => array(
				'issue_type' => 'duplicate_meta_description',
				'fix_type'   => 'set_meta_description',
				'label'      => 'توضیحات متا',
				'suggestion' => ' توضیحات متای یکتا با تمرکز بر محتوای همان صفحه تولید کن.',
			),
			'content' => array(
				'issue_type' => 'duplicate_content',
				'fix_type'   => 'expand_content',
				'label'      => 'محتوا',
				'suggestion' => ' محتوا را متمایز و غنی کن یا صفحه تکراری را ادغام/حذف کن.',
			),
		);

		foreach ( $maps as $map_key => $meta ) {
			if ( empty( $index[ $map_key ] ) || ! is_array( $index[ $map_key ] ) ) {
				continue;
			}
			foreach ( $index[ $map_key ] as $ids ) {
				$ids = array_values( array_filter( array_map( 'intval', (array) $ids ) ) );
				if ( count( $ids ) < 2 ) {
					continue;
				}
				$others = count( $ids ) - 1;
				foreach ( $ids as $post_id ) {
					$post = get_post( $post_id );
					if ( ! $post || 'trash' === $post->post_status ) {
						continue;
					}
					GSO_Issues::upsert(
						array(
							'object_id'   => $post_id,
							'object_type' => $post->post_type,
							'issue_type'  => $meta['issue_type'],
							'severity'    => 'low',
							'title'       => sprintf( '%s تکراری در «%s»', $meta['label'], get_the_title( $post_id ) ),
							'description' => sprintf( 'این %s در %d صفحه دیگر هم استفاده شده است.', $meta['label'], $others ),
							'suggestion'  => $meta['suggestion'],
							'fix_type'    => $meta['fix_type'],
							'fix_payload' => array( 'handler' => $meta['fix_type'], 'safe' => 'expand_content' !== $meta['fix_type'] ),
						)
					);
				}
			}
		}
	}

	/**
	 * شمارش کلمات (سازگار با فارسی).
	 *
	 * @param string $text متن.
	 * @return int
	 */
	public static function word_count( string $text ): int {
		$text = trim( preg_replace( '/\s+/u', ' ', $text ) );
		if ( '' === $text ) {
			return 0;
		}
		$words = preg_split( '/\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY );
		return is_array( $words ) ? count( $words ) : 0;
	}

	/**
	 * تحلیل هدینگ‌ها.
	 *
	 * @param string $content محتوا.
	 * @return array counts, gap
	 */
	public static function headings( string $content ): array {
		$counts = array();
		$levels = array();
		if ( preg_match_all( '/<h([1-6])\b[^>]*>.*?<\/h\1>/is', $content, $matches, PREG_SET_ORDER ) ) {
			foreach ( $matches as $match ) {
				$level = (int) $match[1];
				$counts[ $level ] = ( $counts[ $level ] ?? 0 ) + 1;
				$levels[] = $level;
			}
		}

		$gap      = '';
		$previous = 0;
		foreach ( $levels as $level ) {
			if ( $previous > 0 && $level > $previous + 1 ) {
				$gap = sprintf( 'H%d بعد از H%d', $level, $previous );
				break;
			}
			$previous = $level;
		}

		return array( 'counts' => $counts, 'gap' => $gap );
	}

	/**
	 * آیا محتوا CTA دارد؟
	 *
	 * @param string $content محتوا.
	 * @return bool
	 */
	public static function has_cta( string $content ): bool {
		$keywords = (array) apply_filters(
			'gso_cta_keywords',
			array( 'خرید', 'سفارش', 'تماس', 'مشاوره', 'ثبت‌نام', 'ثبت نام', 'دانلود', 'رزرو', 'شروع', 'اشتراک', 'دریافت', 'پشتیبانی', 'buy', 'contact', 'signup', 'download', 'order', 'book' )
		);

		if ( preg_match_all( '/<a\b[^>]*>(.*?)<\/a>/is', $content, $matches ) ) {
			foreach ( $matches[1] as $text ) {
				$text = self::lower( wp_strip_all_tags( $text ) );
				foreach ( $keywords as $keyword ) {
					if ( '' !== $keyword && false !== strpos( $text, self::lower( (string) $keyword ) ) ) {
						return true;
					}
				}
			}
		}
		if ( preg_match( '/<button\b[^>]*>/i', $content ) ) {
			return true;
		}
		if ( preg_match( '/class\s*=\s*["\'][^"\']*(btn|button|cta)[^"\']*["\']/i', $content ) ) {
			return true;
		}
		return false;
	}

	/**
	 * شناسه‌های تکراری HTML.
	 *
	 * @param string $content محتوا.
	 * @return array
	 */
	public static function duplicate_ids( string $content ): array {
		if ( ! preg_match_all( '/\bid\s*=\s*["\']([^"\']+)["\']/i', $content, $matches ) ) {
			return array();
		}
		$seen  = array();
		$dupes = array();
		foreach ( $matches[1] as $id ) {
			$id = trim( $id );
			if ( '' === $id ) {
				continue;
			}
			if ( isset( $seen[ $id ] ) ) {
				$dupes[ $id ] = $id;
			}
			$seen[ $id ] = true;
		}
		return array_values( $dupes );
	}

	/**
	 * شورت‌کدهای بدون هندلر.
	 *
	 * @param string $content محتوا.
	 * @return array
	 */
	public static function unregistered_shortcodes( string $content ): array {
		$cleaned = preg_replace( '#<!--.*?-->#s', '', $content );
		$cleaned = preg_replace( '#<(script|style|pre|code)\b[^>]*>.*?</\1>#is', '', (string) $cleaned );
		if ( ! preg_match_all( '/(?<!\[)\[([a-zA-Z][a-zA-Z0-9_\-]*)(?=[\s\]])/', (string) $cleaned, $matches ) ) {
			return array();
		}
		$registered = array_keys( (array) $GLOBALS['shortcode_tags'] );
		$builtin    = array( 'caption', 'gallery', 'embed', 'playlist', 'audio', 'video' );
		$known      = array_merge( $registered, $builtin );
		$found      = array();
		foreach ( $matches[1] as $tag ) {
			$tag = strtolower( $tag );
			if ( ! in_array( $tag, $known, true ) ) {
				$found[ $tag ] = $tag;
			}
		}
		return array_values( $found );
	}

	/**
	 * Alt نامناسب؟
	 *
	 * @param string $alt           متن.
	 * @param int    $attachment_id شناسه.
	 * @return bool
	 */
	public static function is_bad_alt( string $alt, int $attachment_id = 0 ): bool {
		$alt = trim( self::lower( $alt ) );
		if ( '' === $alt ) {
			return true;
		}
		if ( preg_match( '/\.(jpe?g|png|gif|webp|svg|avif)$/i', $alt ) ) {
			return true;
		}
		if ( in_array( $alt, array( 'image', 'img', 'photo', 'picture', 'تصویر', 'عکس' ), true ) ) {
			return true;
		}
		if ( mb_strlen( $alt ) < 4 ) {
			return true;
		}
		return false;
	}

	/**
	 * در پایان اسکن، بر اساس سطح خودکارسازی موارد مهم را به صف می‌فرستد.
	 */
	private static function auto_enqueue_findings(): void {
		$level = (string) GSO_Settings::get( 'automation_level', 'review_required' );
		if ( 'report_only' === $level ) {
			return;
		}

		$issues = GSO_Issues::query(
			array(
				'status'   => 'open',
				'limit'    => 8,
				'orderby'  => 'priority',
			)
		);

		$added = 0;
		foreach ( $issues as $issue ) {
			if ( 'low' === $issue->severity ) {
				continue;
			}
			$payload = GSO_Issues::payload( $issue );
			$handler = (string) ( $payload['handler'] ?? $issue->fix_type );
			if ( '' === $handler || 'manual' === $handler ) {
				continue;
			}
			if ( GSO_Queue::enqueue( (int) $issue->id, 'auto' ) ) {
				++$added;
			}
		}

		if ( $added > 0 ) {
			GSO_Logger::log( 'info', 'audit.auto_enqueue', sprintf( '%d مورد مهم برای اصلاح خودکار/پیشنهاد به صف رفت.', $added ), array( 'level' => $level ) );
		}
	}
}
