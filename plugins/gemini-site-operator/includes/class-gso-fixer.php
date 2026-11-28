<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * آماده‌سازی پیشنهاد اصلاح و اعمال امن آن با Snapshot.
 */
class GSO_Fixer {

	/**
	 * تولید پیشنهاد اصلاح (در صورت نیاز با Gemini) و ذخیره در payload.
	 *
	 * @param int $issue_id شناسه ایراد.
	 * @return array|WP_Error payload آماده‌شده.
	 */
	public static function prepare( int $issue_id ) {
		$issue = GSO_Issues::get( $issue_id );
		if ( ! $issue ) {
			return new WP_Error( 'gso_missing', 'ایراد پیدا نشد.' );
		}
		if ( 'fixed' === $issue->status ) {
			return new WP_Error( 'gso_already', 'این مورد قبلاً اصلاح شده است.' );
		}

		$payload = GSO_Issues::payload( $issue );
		$handler = (string) ( $payload['handler'] ?? $issue->fix_type );
		$post_id = (int) $issue->object_id;

		switch ( $handler ) {
			case 'manual':
			case '':
				return new WP_Error( 'gso_manual', 'این مورد نیاز به اقدام دستی اپراتور دارد.' );

			case 'set_meta_title':
			case 'set_meta_description':
				$meta = GSO_Generator::meta_for_post( $post_id );
				if ( is_wp_error( $meta ) ) {
					return $meta;
				}
				$payload['handler']    = $handler;
				$payload['meta_title'] = $meta['meta_title'];
				$payload['meta_desc']  = $meta['meta_description'];
				break;

			case 'set_alt':
				$alt = GSO_Generator::alt_for_image( $post_id, '', $issue->title );
				if ( is_wp_error( $alt ) ) {
					return $alt;
				}
				if ( '' === trim( (string) $alt ) ) {
					return new WP_Error( 'gso_empty', 'متن Alt خالی تولید شد.' );
				}
				$payload['handler'] = 'set_alt';
				$payload['alt']     = $alt;
				break;

			case 'set_inline_alt':
				$attachment_id = (int) ( $payload['attachment_id'] ?? 0 );
				$alt           = '';
				if ( $attachment_id > 0 ) {
					$alt = trim( (string) get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ) );
				}
				if ( '' === $alt ) {
					$context = get_the_title( $post_id );
					$alt     = GSO_Generator::alt_for_image( $attachment_id, (string) ( $payload['src'] ?? '' ), $context );
					if ( is_wp_error( $alt ) ) {
						return $alt;
					}
				}
				if ( '' === trim( (string) $alt ) ) {
					return new WP_Error( 'gso_empty', 'متن Alt خالی تولید شد.' );
				}
				$payload['handler'] = 'set_inline_alt';
				$payload['alt']     = is_string( $alt ) ? $alt : '';
				break;

			case 'set_short_description':
				$short = GSO_Generator::product_short_description( $post_id );
				if ( is_wp_error( $short ) ) {
					return $short;
				}
				$payload['handler']           = 'set_short_description';
				$payload['short_description'] = $short;
				break;

			case 'structure_product_content':
				$content = GSO_Generator::product_full_description( $post_id );
				if ( is_wp_error( $content ) ) {
					return $content;
				}
				$payload['handler'] = 'structure_product_content';
				$payload['content'] = $content;
				break;

			case 'set_product_alt':
				$product_id = (int) ( $payload['product_id'] ?? 0 );
				if ( $product_id < 1 ) {
					return new WP_Error( 'gso_product', 'محصول مرتبط با تصویر مشخص نیست.' );
				}
				$alt = GSO_Generator::product_alt_for_attachment( $post_id, $product_id );
				if ( is_wp_error( $alt ) ) {
					return $alt;
				}
				$payload['handler'] = 'set_product_alt';
				$payload['alt']     = $alt;
				break;

			case 'assign_product_terms':
				$terms = GSO_Generator::product_terms( $post_id );
				if ( is_wp_error( $terms ) ) {
					return $terms;
				}
				if ( empty( $terms['category_ids'] ) && empty( $terms['tags'] ) ) {
					return new WP_Error( 'gso_terms', 'دسته/برچسب مناسبی پیشنهاد نشد.' );
				}
				$payload['handler']      = 'assign_product_terms';
				$payload['category_ids'] = $terms['category_ids'];
				$payload['category_names'] = $terms['category_names'];
				$payload['tags']         = $terms['tags'];
				$payload['reason']       = $terms['reason'];
				break;

			case 'insert_product_links':
			case 'insert_internal_links':
				$links = 'insert_product_links' === $handler
					? GSO_Generator::product_internal_links( $post_id )
					: GSO_Generator::internal_links( $post_id );
				if ( is_wp_error( $links ) ) {
					return $links;
				}
				if ( '' === trim( (string) ( $links['html'] ?? '' ) ) ) {
					return new WP_Error( 'gso_links', 'لینک داخلی مناسبی پیشنهاد نشد.' );
				}
				$payload['handler'] = $handler;
				$payload['html']    = $links['html'];
				$payload['links']   = $links['links'];
				break;

			case 'expand_content':
				$proposal = GSO_Generator::rewrite_post( $post_id, 'محتوا را کامل‌تر، ساختارمندتر و با بخش‌های مفید بیشتر بازنویسی کن.', 'expand' );
				if ( is_wp_error( $proposal ) ) {
					return $proposal;
				}
				$payload['handler'] = 'expand_content';
				$payload['content'] = $proposal['content'];
				$payload['title']   = $proposal['title'];
				$payload['excerpt'] = $proposal['excerpt'];
				break;

			case 'add_cta':
				$cta = self::generate_cta( $post_id );
				if ( is_wp_error( $cta ) ) {
					return $cta;
				}
				$payload['handler'] = 'add_cta';
				$payload['html']    = $cta;
				break;

			case 'insert_h1':
				$h1 = GSO_Gemini::text(
					'برای صفحه «' . get_the_title( $post_id ) . '» فقط یک تگ H1 کوتاه و کلیدواژه‌محور فارسی بنویس؛ فقط HTML همان تگ، بدون توضیح.',
					array( 'model_kind' => 'bulk', 'max_tokens' => 256 )
				);
				if ( is_wp_error( $h1 ) ) {
					return $h1;
				}
				$clean_h1 = trim( wp_strip_all_tags( (string) $h1 ) );
				if ( '' === $clean_h1 ) {
					return new WP_Error( 'gso_empty', 'متن H1 خالی تولید شد.' );
				}
				$payload['handler'] = 'insert_h1';
				$payload['text']    = '<h1>' . esc_html( $clean_h1 ) . '</h1>';
				break;

			case 'set_excerpt':
				$excerpt = GSO_Gemini::text(
					'خلاصه‌ای یک تا دو جمله‌ای (حداکثر ۳۰ کلمه) برای این محتوا بنویس: ' . mb_substr( wp_strip_all_tags( (string) get_post_field( 'post_content', $post_id ) ), 0, 1500 ),
					array( 'model_kind' => 'bulk', 'max_tokens' => 160 )
				);
				if ( is_wp_error( $excerpt ) ) {
					return $excerpt;
				}
				$payload['handler'] = 'set_excerpt';
				$payload['excerpt'] = sanitize_textarea_field( (string) $excerpt );
				break;

			case 'set_term_description':
				$term = get_term( $post_id );
				if ( ! $term || is_wp_error( $term ) ) {
					return new WP_Error( 'gso_term', 'ترم پیدا نشد.' );
				}
				$text = GSO_Gemini::text(
					'برای دسته/برچسب «' . $term->name . '» یک توضیح دو تا سه جمله‌ای فارسی و سئوپسند بنویس. فقط متن توضیح.',
					array( 'model_kind' => 'bulk', 'max_tokens' => 220 )
				);
				if ( is_wp_error( $text ) ) {
					return $text;
				}
				$payload['handler']    = 'set_term_description';
				$payload['taxonomy']   = $term->taxonomy;
				$payload['description'] = sanitize_textarea_field( (string) $text );
				break;

			case 'normalize_h1':
			case 'dedupe_ids':
			case 'inject_schema':
			case 'remove_broken_link':
			case 'clear_debug_log':
				$payload['handler'] = $handler;
				$payload['safe']    = in_array( $handler, array( 'normalize_h1', 'dedupe_ids', 'inject_schema' ), true );
				break;

			default:
				return new WP_Error( 'gso_unknown_fix', 'نوع اصلاح پشتیبانی نمی‌شود: ' . esc_html( $handler ) );
		}

		if ( ! isset( $payload['safe'] ) ) {
			$payload['safe'] = self::is_safe_handler( $handler );
		}

		$payload['ready'] = true;
		GSO_Issues::save_payload( $issue_id, $payload, true );
		GSO_Logger::log( 'info', 'fixer.prepare', sprintf( 'پیشنهاد اصلاح #%d آماده شد (%s).', $issue_id, $handler ), array( 'issue_id' => $issue_id ) );

		return $payload;
	}

	/**
	 * اعمال اصلاح با ثبت Snapshot.
	 *
	 * @param int  $issue_id شناسه.
	 * @param bool $prepare_if_needed آماده‌سازی خودکار در صورت نبود payload.
	 * @return array|WP_Error
	 */
	public static function apply( int $issue_id, bool $prepare_if_needed = true ) {
		$issue = GSO_Issues::get( $issue_id );
		if ( ! $issue ) {
			return new WP_Error( 'gso_missing', 'ایراد پیدا نشد.' );
		}
		if ( 'fixed' === $issue->status ) {
			return new WP_Error( 'gso_already', 'این مورد قبلاً اصلاح شده است.' );
		}

		$payload = GSO_Issues::payload( $issue );
		$handler = (string) ( $payload['handler'] ?? $issue->fix_type );
		if ( '' === $handler || 'manual' === $handler ) {
			return new WP_Error( 'gso_manual', 'این مورد اصلاح خودکار ندارد و باید دستی انجام شود.' );
		}

		if ( ! self::is_ready( $payload ) ) {
			if ( ! $prepare_if_needed ) {
				return new WP_Error( 'gso_not_prepared', 'ابتدا پیشنهاد اصلاح را تولید کنید.' );
			}
			$prepared = self::prepare( $issue_id );
			if ( is_wp_error( $prepared ) ) {
				return $prepared;
			}
			$issue   = GSO_Issues::get( $issue_id );
			$payload = GSO_Issues::payload( $issue );
			$handler = (string) ( $payload['handler'] ?? $handler );
		}

		$target = self::validate_target( $issue );
		if ( is_wp_error( $target ) ) {
			GSO_Issues::set_status( $issue_id, 'failed' );
			GSO_Logger::log( 'error', 'fixer.target', sprintf( 'اصلاح #%d رد شد: %s', $issue_id, $target->get_error_message() ), array( 'issue_id' => $issue_id ) );
			return $target;
		}

		$method = 'handle_' . $handler;
		if ( ! method_exists( __CLASS__, $method ) ) {
			return new WP_Error( 'gso_no_handler', 'هندلر اصلاح موجود نیست: ' . esc_html( $handler ) );
		}

		$state = GSO_Snapshot::collect_state( (string) $issue->object_type, (int) $issue->object_id, $handler );
		if ( 'clear_debug_log' === $handler ) {
			$log         = trailingslashit( WP_CONTENT_DIR ) . 'debug.log';
			$content_dir = realpath( WP_CONTENT_DIR );
			$log_real    = realpath( $log );
			if ( file_exists( $log ) && ! is_link( $log ) && false !== $content_dir && false !== $log_real
				&& 0 === strpos( $log_real, trailingslashit( $content_dir ) ) && is_readable( $log_real ) ) {
				$size = (int) @filesize( $log_real );
				$fp   = @fopen( $log_real, 'r' );
				if ( $fp ) {
					$read = max( 0, $size - 20000 );
					fseek( $fp, $read );
					$excerpt = (string) fread( $fp, 20000 );
					fclose( $fp );
					$state['options']['debug_log_excerpt'] = $excerpt;
					$state['options']['debug_log_size']    = $size;
				}
			}
		}

		$snapshot_id = GSO_Snapshot::capture(
			array(
				'issue_id'    => $issue_id,
				'object_id'   => (int) $issue->object_id,
				'object_type' => (string) $issue->object_type,
				'label'       => sprintf( 'پیش از اصلاح: %s', GSO_Issues::issue_label( (string) $issue->issue_type ) ),
				'payload'     => $state,
			)
		);

		$result = self::$method( $issue, $payload, $snapshot_id );
		if ( is_wp_error( $result ) ) {
			GSO_Snapshot::delete( $snapshot_id );
			GSO_Issues::set_status( $issue_id, 'failed' );
			GSO_Logger::log( 'error', 'fixer.apply', sprintf( 'اصلاح #%d شکست خورد: %s', $issue_id, $result->get_error_message() ), array( 'issue_id' => $issue_id ) );
			return $result;
		}

		GSO_Issues::set_status( $issue_id, 'fixed' );
		GSO_Logger::log(
			'success',
			'fixer.apply',
			sprintf( 'اصلاح #%d اعمال شد (%s) — قابل بازگشت تا نسخه #%d.', $issue_id, $handler, $snapshot_id ),
			array( 'issue_id' => $issue_id, 'snapshot_id' => $snapshot_id )
		);

		return array( 'issue_id' => $issue_id, 'snapshot_id' => $snapshot_id, 'handler' => $handler );
	}

	/**
	 * آیا payload برای اعمال، داده لازم را دارد؟
	 *
	 * منبع واحد این قاعده است تا صف و اعمال، هر دو یک تعریف داشته باشند.
	 *
	 * @param array $payload داده ایراد.
	 * @return bool
	 */
	public static function is_ready( array $payload ): bool {
		$handler = (string) ( $payload['handler'] ?? '' );
		if ( '' === $handler || 'manual' === $handler ) {
			return false;
		}

		$keys = array(
			'set_meta_title'            => 'meta_title',
			'set_meta_description'      => 'meta_desc',
			'set_alt'                   => 'alt',
			'set_inline_alt'            => 'alt',
			'expand_content'            => 'content',
			'add_cta'                   => 'html',
			'insert_h1'                 => 'text',
			'set_excerpt'               => 'excerpt',
			'set_term_description'      => 'description',
			'set_short_description'     => 'short_description',
			'structure_product_content' => 'content',
			'set_product_alt'           => 'alt',
			'insert_product_links'      => 'html',
			'insert_internal_links'     => 'html',
		);

		if ( isset( $keys[ $handler ] ) ) {
			return ! empty( $payload[ $keys[ $handler ] ] );
		}
		if ( 'assign_product_terms' === $handler ) {
			return ! empty( $payload['category_ids'] ) || ! empty( $payload['tags'] );
		}

		// هندلرهایی که داده از پیش ساخته‌شده لازم ندارند.
		return in_array( $handler, array( 'normalize_h1', 'dedupe_ids', 'inject_schema', 'remove_broken_link', 'clear_debug_log' ), true );
	}

	/**
	 * آیا هندلر به‌طور ذاتی کم‌ریسک است؟
	 *
	 * @param string $handler نام هندلر.
	 * @return bool
	 */
	public static function is_safe_handler( string $handler ): bool {
		return in_array(
			$handler,
			array(
				'set_meta_title',
				'set_meta_description',
				'set_alt',
				'set_inline_alt',
				'set_excerpt',
				'set_term_description',
				'set_short_description',
				'set_product_alt',
				'normalize_h1',
				'dedupe_ids',
				'inject_schema',
			),
			true
		);
	}

	/**
	 * آیا این اصلاح کم‌ریسک است؟
	 *
	 * @param int $issue_id شناسه.
	 * @return bool
	 */
	public static function is_safe( int $issue_id ): bool {
		$issue = GSO_Issues::get( $issue_id );
		if ( ! $issue ) {
			return false;
		}
		$payload = GSO_Issues::payload( $issue );
		if ( array_key_exists( 'safe', $payload ) ) {
			return ! empty( $payload['safe'] );
		}
		$handler = (string) ( $payload['handler'] ?? $issue->fix_type );
		return self::is_safe_handler( $handler );
	}

	/**
	 * کنترل نهایی هدف پیش از نوشتن: وجود شیء، تطبیق نوع و سطح دسترسی.
	 *
	 * در اجرای کران/CLI کاربری وارد نشده است؛ در آن حالت فقط وجود و نوع شیء
	 * بررسی می‌شود و دسترسی از قبل با manage_options در نقطه ورود کنترل شده است.
	 *
	 * @param object $issue ایراد ذخیره‌شده.
	 * @return true|WP_Error
	 */
	private static function validate_target( object $issue ) {
		$type = sanitize_key( (string) $issue->object_type );
		$id   = (int) $issue->object_id;

		if ( 'site' === $type ) {
			if ( is_user_logged_in() && ! current_user_can( 'manage_options' ) ) {
				return new WP_Error( 'gso_no_access', 'دسترسی لازم برای تغییر تنظیمات سایت وجود ندارد.' );
			}
			return true;
		}

		if ( 'term' === $type ) {
			$term = $id > 0 ? get_term( $id ) : null;
			if ( ! $term || is_wp_error( $term ) ) {
				return new WP_Error( 'gso_target_missing', 'دسته/برچسب هدف پیدا نشد؛ اصلاح انجام نشد.' );
			}
			if ( is_user_logged_in() && ! current_user_can( 'edit_term', $id ) ) {
				return new WP_Error( 'gso_no_access', 'دسترسی لازم برای ویرایش این دسته/برچسب وجود ندارد.' );
			}
			return true;
		}

		$post = $id > 0 ? get_post( $id ) : null;
		if ( ! $post ) {
			return new WP_Error( 'gso_target_missing', 'محتوا/محصول هدف پیدا نشد؛ ممکن است حذف شده باشد.' );
		}
		if ( 'post' === $type && ! in_array( $post->post_type, array( 'post', 'page' ), true ) ) {
			return new WP_Error( 'gso_target_type', 'نوع محتوای هدف با نوع ثبت‌شده در ایراد هم‌خوان نیست.' );
		}
		if ( in_array( $type, array( 'page', 'product', 'product_variation', 'attachment' ), true ) && $post->post_type !== $type ) {
			return new WP_Error( 'gso_target_type', 'نوع محتوای هدف با نوع ثبت‌شده در ایراد هم‌خوان نیست.' );
		}
		if ( 'trash' === $post->post_status ) {
			return new WP_Error( 'gso_target_trash', 'هدف در سطل زباله است؛ ابتدا آن را بازیابی کنید.' );
		}
		if ( 'attachment' === $type && ! wp_attachment_is_image( $id ) ) {
			return new WP_Error( 'gso_target_type', 'پیوست هدف تصویر نیست.' );
		}
		if ( is_user_logged_in() && ! current_user_can( 'edit_post', $id ) ) {
			return new WP_Error( 'gso_no_access', 'دسترسی لازم برای ویرایش این محتوا وجود ندارد.' );
		}

		return true;
	}

	/**
	 * پیش‌نمایش قبل/بعد برای یک ایراد.
	 *
	 * @param int $issue_id شناسه.
	 * @return array|WP_Error
	 */
	public static function preview( int $issue_id ) {
		$issue = GSO_Issues::get( $issue_id );
		if ( ! $issue ) {
			return new WP_Error( 'gso_missing', 'ایراد پیدا نشد.' );
		}
		$payload = GSO_Issues::payload( $issue );
		$handler = (string) ( $payload['handler'] ?? $issue->fix_type );
		$before  = '';
		$after   = '';

		$post = get_post( (int) $issue->object_id );

		switch ( $handler ) {
			case 'set_meta_title':
				$before = GSO_SEO::get_title( (int) $issue->object_id );
				$after  = (string) ( $payload['meta_title'] ?? '' );
				break;
			case 'set_meta_description':
				$before = GSO_SEO::get_description( (int) $issue->object_id );
				$after  = (string) ( $payload['meta_desc'] ?? '' );
				break;
			case 'set_alt':
			case 'set_inline_alt':
				$before = 'attachment' === $issue->object_type ? (string) get_post_meta( (int) $issue->object_id, '_wp_attachment_image_alt', true ) : (string) ( $payload['tag'] ?? '' );
				$after  = (string) ( $payload['alt'] ?? '' );
				break;
			case 'expand_content':
			case 'add_cta':
			case 'insert_h1':
			case 'normalize_h1':
			case 'dedupe_ids':
			case 'remove_broken_link':
				$before = $post ? (string) $post->post_content : '';
				$after  = self::projected_content( (string) $handler, $before, $payload );
				break;
			case 'inject_schema':
				$before = (string) get_post_meta( (int) $issue->object_id, GSO_SEO::SCHEMA_META, true );
				$after  = (string) wp_json_encode( self::build_schema( (int) $issue->object_id ), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );
				break;
			case 'set_excerpt':
				$before = $post ? (string) $post->post_excerpt : '';
				$after  = (string) ( $payload['excerpt'] ?? '' );
				break;
			case 'set_short_description':
				$before = $post ? (string) $post->post_excerpt : '';
				$after  = (string) ( $payload['short_description'] ?? '' );
				break;
			case 'structure_product_content':
				$before = $post ? (string) $post->post_content : '';
				$after  = (string) ( $payload['content'] ?? '' );
				break;
			case 'set_product_alt':
				$before = (string) get_post_meta( (int) $issue->object_id, '_wp_attachment_image_alt', true );
				$after  = (string) ( $payload['alt'] ?? '' );
				break;
			case 'assign_product_terms':
				$before = '';
				foreach ( array( 'product_cat', 'product_tag' ) as $taxonomy ) {
					$names = wp_get_post_terms( (int) $issue->object_id, $taxonomy, array( 'fields' => 'names' ) );
					if ( ! is_wp_error( $names ) && $names ) {
						$before .= $taxonomy . ': ' . implode( '، ', $names ) . "\n";
					}
				}
				$before = trim( $before );
				$after  = trim( implode( '، ', (array) ( $payload['category_names'] ?? array() ) ) . "\n" . implode( '، ', (array) ( $payload['tags'] ?? array() ) ) );
				break;
			case 'insert_product_links':
			case 'insert_internal_links':
				$before = $post ? (string) $post->post_content : '';
				$after  = rtrim( $before ) . "\n\n" . (string) ( $payload['html'] ?? '' );
				break;
			case 'set_term_description':
				$term   = get_term( (int) $issue->object_id );
				$before = ( $term && ! is_wp_error( $term ) ) ? (string) $term->description : '';
				$after  = (string) ( $payload['description'] ?? '' );
				break;
			case 'clear_debug_log':
				$before = 'فایل debug.log موجود است.';
				$after  = 'فایل debug.log خالی می‌شود (۲۰ کیلوبایت آخر در Snapshot ذخیره شده است).';
				break;
			default:
				$before = (string) $issue->description;
				$after  = (string) $issue->suggestion;
		}

		return array(
			'handler'  => $handler,
			'before'   => $before,
			'after'    => $after,
			'diff'     => self::simple_diff( $before, $after ),
			'has_fix'  => $after !== '' && $after !== $before,
		);
	}

	/**
	 * اعمال بازنویسی کامل محتوا (از ابزار اپراتور).
	 *
	 * @param int   $post_id شناسه.
	 * @param array $proposal title, content, excerpt, meta_title, meta_description.
	 * @return array|WP_Error
	 */
	public static function apply_rewrite( int $post_id, array $proposal ) {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return new WP_Error( 'gso_missing', 'نوشته پیدا نشد.' );
		}
		if ( empty( $proposal['content'] ) ) {
			return new WP_Error( 'gso_empty', 'محتوای پیشنهادی خالی است.' );
		}

		$state       = GSO_Snapshot::collect_state( (string) $post->post_type, $post_id, 'rewrite' );
		$snapshot_id = GSO_Snapshot::capture(
			array(
				'issue_id'    => 0,
				'object_id'   => $post_id,
				'object_type' => (string) $post->post_type,
				'label'       => 'پیش از بازنویسی دستی محتوا',
				'payload'     => $state,
			)
		);

		$update = array(
			'ID'           => $post_id,
			'post_content' => GSO_Generator::prepare_html( (string) $proposal['content'] ),
		);
		if ( ! empty( $proposal['title'] ) ) {
			$update['post_title'] = sanitize_text_field( (string) $proposal['title'] );
		}
		if ( isset( $proposal['excerpt'] ) ) {
			$update['post_excerpt'] = sanitize_textarea_field( (string) $proposal['excerpt'] );
		}

		$result = wp_update_post( $update, true );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		if ( ! empty( $proposal['meta_title'] ) ) {
			GSO_SEO::set_title( $post_id, (string) $proposal['meta_title'] );
		}
		if ( ! empty( $proposal['meta_description'] ) ) {
			GSO_SEO::set_description( $post_id, (string) $proposal['meta_description'] );
		}

		GSO_Logger::log( 'success', 'fixer.rewrite', sprintf( 'بازنویسی «%s» اعمال شد (نسخه #%d).', get_the_title( $post_id ), $snapshot_id ), array( 'post_id' => $post_id, 'snapshot_id' => $snapshot_id ) );

		return array( 'post_id' => $post_id, 'snapshot_id' => $snapshot_id, 'edit_link' => get_edit_post_link( $post_id, 'raw' ) );
	}

	/**
	 * ذخیره CSS سفارشی با Snapshot.
	 *
	 * @param string $css کد جدید.
	 * @param bool   $snapshot_only ثبت نسخه بدون تغییر.
	 * @return array|WP_Error
	 */
	public static function apply_css( string $css, bool $snapshot_only = false ) {
		$old         = (string) get_option( 'gso_custom_css', '' );
		$snapshot_id = GSO_Snapshot::capture(
			array(
				'issue_id'    => 0,
				'object_id'   => 0,
				'object_type' => 'site',
				'label'       => $snapshot_only ? 'نسخه پشتیبان CSS سفارشی' : 'پیش از تغییر CSS سفارشی',
				'payload'     => array( 'options' => array( 'gso_custom_css' => $old ) ),
			)
		);
		if ( $snapshot_only ) {
			return array( 'snapshot_id' => $snapshot_id, 'css' => $old );
		}

		$css = GSO_Generator::sanitize_css( $css );
		update_option( 'gso_custom_css', $css, false );
		GSO_Logger::log( 'success', 'fixer.css', 'CSS سفارشی به‌روزرسانی شد.', array( 'snapshot_id' => $snapshot_id, 'bytes' => strlen( $css ) ) );

		return array( 'snapshot_id' => $snapshot_id, 'css' => $css );
	}

	/* ---------------------------------------------------------------------
	 * هندلرها
	 * ------------------------------------------------------------------- */

	private static function handle_set_meta_title( object $issue, array $payload, int $snapshot_id ) {
		$value = (string) ( $payload['meta_title'] ?? '' );
		if ( '' === trim( $value ) ) {
			return new WP_Error( 'gso_empty', 'عنوان پیشنهادی خالی است.' );
		}
		GSO_SEO::set_title( (int) $issue->object_id, $value );
		return true;
	}

	private static function handle_set_meta_description( object $issue, array $payload, int $snapshot_id ) {
		$value = (string) ( $payload['meta_desc'] ?? '' );
		if ( '' === trim( $value ) ) {
			return new WP_Error( 'gso_empty', 'توضیحات پیشنهادی خالی است.' );
		}
		GSO_SEO::set_description( (int) $issue->object_id, $value );
		return true;
	}

	private static function handle_set_alt( object $issue, array $payload, int $snapshot_id ) {
		$alt = (string) ( $payload['alt'] ?? '' );
		if ( '' === trim( $alt ) ) {
			return new WP_Error( 'gso_empty', 'متن Alt خالی است.' );
		}
		update_post_meta( (int) $issue->object_id, '_wp_attachment_image_alt', sanitize_text_field( $alt ) );
		return true;
	}

	private static function handle_set_inline_alt( object $issue, array $payload, int $snapshot_id ) {
		$post = get_post( (int) $issue->object_id );
		if ( ! $post ) {
			return new WP_Error( 'gso_missing', 'نوشته پیدا نشد.' );
		}
		$alt           = sanitize_text_field( (string) ( $payload['alt'] ?? '' ) );
		$src           = (string) ( $payload['src'] ?? '' );
		$target_tag    = (string) ( $payload['tag'] ?? '' );
		$attachment_id = (int) ( $payload['attachment_id'] ?? 0 );
		if ( '' === $alt ) {
			return new WP_Error( 'gso_empty', 'متن Alt خالی است.' );
		}

		$norm_target = '' !== $src ? esc_url_raw( html_entity_decode( $src ) ) : '';
		$updated     = 0;

		$content = preg_replace_callback(
			'/<img\b[^>]*>/i',
			static function ( $matches ) use ( $alt, $src, $norm_target, $target_tag, $attachment_id, &$updated ) {
				$tag     = $matches[0];
				$matched = false;

				if ( '' !== $target_tag && $tag === $target_tag ) {
					$matched = true;
				} elseif ( $attachment_id > 0 && false !== strpos( $tag, 'wp-image-' . $attachment_id ) ) {
					$matched = true;
				} elseif ( '' !== $src ) {
					if ( false !== strpos( $tag, $src ) ) {
						$matched = true;
					} elseif ( preg_match( '/\bsrc\s*=\s*["\']([^"\']+)["\']/i', $tag, $m ) ) {
						$norm_src = esc_url_raw( html_entity_decode( $m[1] ) );
						if ( '' !== $norm_target && $norm_src === $norm_target ) {
							$matched = true;
						}
					}
				}

				if ( ! $matched ) {
					return $tag;
				}
				++$updated;
				if ( preg_match( '/\balt\s*=\s*["\'][^"\']*["\']/i', $tag ) ) {
					return preg_replace( '/\balt\s*=\s*["\'][^"\']*["\']/i', 'alt="' . esc_attr( $alt ) . '"', $tag );
				}
				return preg_replace( '/<img\b/i', '<img alt="' . esc_attr( $alt ) . '"', $tag, 1 );
			},
			(string) $post->post_content
		);

		if ( 0 === $updated ) {
			return new WP_Error( 'gso_noop', 'تگ تصویر هدف در محتوا پیدا نشد.' );
		}

		$result = wp_update_post( array( 'ID' => (int) $post->ID, 'post_content' => $content ), true );
		return is_wp_error( $result ) ? $result : true;
	}

	private static function handle_expand_content( object $issue, array $payload, int $snapshot_id ) {
		$content = GSO_Generator::prepare_html( (string) ( $payload['content'] ?? '' ) );
		if ( '' === trim( wp_strip_all_tags( $content ) ) ) {
			return new WP_Error( 'gso_empty', 'محتوای پیشنهادی خالی است.' );
		}
		$update = array( 'ID' => (int) $issue->object_id, 'post_content' => $content );
		if ( ! empty( $payload['title'] ) ) {
			$update['post_title'] = sanitize_text_field( (string) $payload['title'] );
		}
		if ( ! empty( $payload['excerpt'] ) ) {
			$update['post_excerpt'] = sanitize_textarea_field( (string) $payload['excerpt'] );
		}
		$result = wp_update_post( $update, true );
		return is_wp_error( $result ) ? $result : true;
	}

	private static function handle_add_cta( object $issue, array $payload, int $snapshot_id ) {
		$html = wp_kses_post( (string) ( $payload['html'] ?? '' ) );
		if ( '' === trim( wp_strip_all_tags( $html ) ) ) {
			return new WP_Error( 'gso_empty', 'CTA پیشنهادی خالی است.' );
		}
		$post    = get_post( (int) $issue->object_id );
		$content = rtrim( (string) $post->post_content ) . "\n\n" . $html;
		$result  = wp_update_post( array( 'ID' => (int) $issue->object_id, 'post_content' => $content ), true );
		return is_wp_error( $result ) ? $result : true;
	}

	private static function handle_insert_h1( object $issue, array $payload, int $snapshot_id ) {
		$h1 = wp_kses_post( (string) ( $payload['text'] ?? '' ) );
		if ( '' === trim( wp_strip_all_tags( $h1 ) ) ) {
			return new WP_Error( 'gso_empty', 'H1 پیشنهادی خالی است.' );
		}
		$post    = get_post( (int) $issue->object_id );
		$content = (string) $post->post_content;
		if ( preg_match( '/<h2\b/i', $content ) ) {
			$content = preg_replace( '/<h2\b/i', $h1 . "\n\n" . '<h2', $content, 1 );
		} else {
			$content = $h1 . "\n\n" . $content;
		}
		$result = wp_update_post( array( 'ID' => (int) $issue->object_id, 'post_content' => $content ), true );
		return is_wp_error( $result ) ? $result : true;
	}

	private static function handle_normalize_h1( object $issue, array $payload, int $snapshot_id ) {
		$post    = get_post( (int) $issue->object_id );
		$content = (string) $post->post_content;
		$count   = 0;
		$content = preg_replace_callback(
			'/<h1\b([^>]*)>(.*?)<\/h1>/is',
			static function ( $matches ) use ( &$count ) {
				++$count;
				if ( 1 === $count ) {
					return $matches[0];
				}
				return '<h2' . $matches[1] . '>' . $matches[2] . '</h2>';
			},
			$content
		);
		if ( $count < 2 ) {
			return new WP_Error( 'gso_noop', 'بیش از یک H1 برای اصلاح پیدا نشد.' );
		}
		$result = wp_update_post( array( 'ID' => (int) $issue->object_id, 'post_content' => $content ), true );
		return is_wp_error( $result ) ? $result : true;
	}

	private static function handle_dedupe_ids( object $issue, array $payload, int $snapshot_id ) {
		$post    = get_post( (int) $issue->object_id );
		$content = (string) $post->post_content;
		$seen    = array();
		$content = preg_replace_callback(
			'/(\bid\s*=\s*["\'])([^"\']+)(["\'])/i',
			static function ( $matches ) use ( &$seen ) {
				$id = $matches[2];
				if ( ! isset( $seen[ $id ] ) ) {
					$seen[ $id ] = 1;
					return $matches[0];
				}
				++$seen[ $id ];
				return $matches[1] . $id . '-' . $seen[ $id ] . $matches[3];
			},
			$content
		);
		$result = wp_update_post( array( 'ID' => (int) $issue->object_id, 'post_content' => $content ), true );
		return is_wp_error( $result ) ? $result : true;
	}

	private static function handle_inject_schema( object $issue, array $payload, int $snapshot_id ) {
		$schema = self::build_schema( (int) $issue->object_id );
		if ( empty( $schema ) ) {
			return new WP_Error( 'gso_schema', 'اسکیمای قابل ساخت پیدا نشد.' );
		}
		update_post_meta( (int) $issue->object_id, GSO_SEO::SCHEMA_META, wp_json_encode( $schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) );
		return true;
	}

	private static function handle_remove_broken_link( object $issue, array $payload, int $snapshot_id ) {
		$post = get_post( (int) $issue->object_id );
		$url  = (string) ( $payload['url'] ?? '' );
		if ( ! $post || '' === $url ) {
			return new WP_Error( 'gso_missing', 'لینک یا نوشته پیدا نشد.' );
		}
		$content = self::strip_broken_link( (string) $post->post_content, $url );
		if ( $content === $post->post_content ) {
			return new WP_Error( 'gso_noop', 'لینک در محتوا پیدا نشد؛ ممکن است قبلاً حذف شده باشد.' );
		}
		$result = wp_update_post( array( 'ID' => (int) $post->ID, 'post_content' => $content ), true );
		return is_wp_error( $result ) ? $result : true;
	}

	private static function handle_clear_debug_log( object $issue, array $payload, int $snapshot_id ) {
		$log = trailingslashit( WP_CONTENT_DIR ) . 'debug.log';
		if ( ! file_exists( $log ) ) {
			return new WP_Error( 'gso_noop', 'فایل debug.log وجود ندارد.' );
		}
		if ( is_link( $log ) ) {
			return new WP_Error( 'gso_permission', 'فایل debug.log نامعتبر است (symlink مجاز نیست).' );
		}
		$content_dir = realpath( WP_CONTENT_DIR );
		$log_real    = realpath( $log );
		if ( false === $content_dir || false === $log_real || 0 !== strpos( $log_real, trailingslashit( $content_dir ) ) ) {
			return new WP_Error( 'gso_permission', 'مسیر فایل debug.log خارج از محدوده مجاز است.' );
		}
		if ( ! is_writable( $log_real ) ) {
			return new WP_Error( 'gso_permission', 'فایل debug.log قابل نوشتن نیست.' );
		}
		$result = file_put_contents( $log_real, '' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		return false === $result ? new WP_Error( 'gso_write', 'خالی‌کردن فایل لاگ ناموفق بود.' ) : true;
	}

	private static function handle_set_excerpt( object $issue, array $payload, int $snapshot_id ) {
		$excerpt = sanitize_textarea_field( (string) ( $payload['excerpt'] ?? '' ) );
		if ( '' === $excerpt ) {
			return new WP_Error( 'gso_empty', 'خلاصه پیشنهادی خالی است.' );
		}
		$result = wp_update_post( array( 'ID' => (int) $issue->object_id, 'post_excerpt' => $excerpt ), true );
		return is_wp_error( $result ) ? $result : true;
	}

	private static function handle_set_short_description( object $issue, array $payload, int $snapshot_id ) {
		return self::handle_set_excerpt( $issue, array( 'excerpt' => $payload['short_description'] ?? '' ), $snapshot_id );
	}

	private static function handle_structure_product_content( object $issue, array $payload, int $snapshot_id ) {
		$content = GSO_Generator::prepare_html( (string) ( $payload['content'] ?? '' ) );
		if ( '' === trim( wp_strip_all_tags( $content ) ) ) {
			return new WP_Error( 'gso_empty', 'توضیحات محصول تولید نشد.' );
		}
		$result = wp_update_post( array( 'ID' => (int) $issue->object_id, 'post_content' => $content ), true );
		return is_wp_error( $result ) ? $result : true;
	}

	private static function handle_set_product_alt( object $issue, array $payload, int $snapshot_id ) {
		$alt = sanitize_text_field( (string) ( $payload['alt'] ?? '' ) );
		if ( '' === $alt ) {
			return new WP_Error( 'gso_empty', 'متن Alt خالی است.' );
		}
		update_post_meta( (int) $issue->object_id, '_wp_attachment_image_alt', $alt );
		return true;
	}

	private static function handle_assign_product_terms( object $issue, array $payload, int $snapshot_id ) {
		$post_id = (int) $issue->object_id;
		if ( 'product' !== get_post_type( $post_id ) || ! taxonomy_exists( 'product_cat' ) ) {
			return new WP_Error( 'gso_product', 'محصول ووکامرس پیدا نشد.' );
		}

		$category_ids = array_values( array_filter( array_map( 'absint', (array) ( $payload['category_ids'] ?? array() ) ) ) );
		$tags         = array_values( array_filter( array_map( 'sanitize_text_field', (array) ( $payload['tags'] ?? array() ) ) ) );
		if ( empty( $category_ids ) && empty( $tags ) ) {
			return new WP_Error( 'gso_empty', 'دسته یا برچسبی برای اعمال وجود ندارد.' );
		}

		if ( $category_ids ) {
			$result = wp_set_object_terms( $post_id, $category_ids, 'product_cat', true );
			if ( is_wp_error( $result ) ) {
				return $result;
			}
		}
		if ( $tags && taxonomy_exists( 'product_tag' ) ) {
			$result = wp_set_object_terms( $post_id, $tags, 'product_tag', true );
			if ( is_wp_error( $result ) ) {
				return $result;
			}
		}

		if ( function_exists( 'wc_delete_product_transients' ) ) {
			wc_delete_product_transients( $post_id );
		}
		return true;
	}

	private static function handle_insert_product_links( object $issue, array $payload, int $snapshot_id ) {
		return self::append_internal_links( $issue, $payload );
	}

	private static function handle_insert_internal_links( object $issue, array $payload, int $snapshot_id ) {
		return self::append_internal_links( $issue, $payload );
	}

	/**
	 * افزودن بلوک لینک داخلی به انتهای محتوا.
	 *
	 * @param object $issue   ایراد.
	 * @param array  $payload داده.
	 * @return true|WP_Error
	 */
	private static function append_internal_links( object $issue, array $payload ) {
		$html = wp_kses_post( (string) ( $payload['html'] ?? '' ) );
		if ( '' === trim( wp_strip_all_tags( $html ) ) ) {
			return new WP_Error( 'gso_empty', 'بلوک لینک داخلی خالی است.' );
		}
		$post = get_post( (int) $issue->object_id );
		if ( ! $post ) {
			return new WP_Error( 'gso_missing', 'نوشته پیدا نشد.' );
		}
		$result = wp_update_post(
			array(
				'ID'           => (int) $post->ID,
				'post_content' => rtrim( (string) $post->post_content ) . "\n\n" . $html,
			),
			true
		);
		return is_wp_error( $result ) ? $result : true;
	}

	private static function handle_set_term_description( object $issue, array $payload, int $snapshot_id ) {
		$term = get_term( (int) $issue->object_id );
		if ( ! $term || is_wp_error( $term ) ) {
			return new WP_Error( 'gso_term', 'ترم پیدا نشد.' );
		}
		$description = sanitize_textarea_field( (string) ( $payload['description'] ?? '' ) );
		if ( '' === $description ) {
			return new WP_Error( 'gso_empty', 'توضیح پیشنهادی خالی است.' );
		}
		$result = wp_update_term( (int) $term->term_id, $term->taxonomy, array( 'description' => $description ) );
		return is_wp_error( $result ) ? $result : true;
	}

	/* ---------------------------------------------------------------------
	 * کمکی‌ها
	 * ------------------------------------------------------------------- */

	/**
	 * CTA کوتاه با Gemini.
	 *
	 * @param int $post_id شناسه.
	 * @return string|WP_Error
	 */
	private static function generate_cta( int $post_id ) {
		$schema = array(
			'type'       => 'OBJECT',
			'properties' => array( 'html' => array( 'type' => 'STRING' ) ),
			'required'   => array( 'html' ),
		);
		$prompt = 'برای صفحه «' . get_the_title( $post_id ) . '» یک بلوک CTA کوتاه و طبیعی فارسی بساز. '
			. 'خروجی یک قطعه HTML ساده شامل یک پاراگراف ترغیبی و یک لینک دکمه‌ای (کلاس gso-cta) باشد. بدون script و style.';
		$result = GSO_Gemini::json( $prompt, $schema, array( 'model_kind' => 'bulk', 'max_tokens' => 300 ) );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		$html = wp_kses_post( (string) ( $result['html'] ?? '' ) );
		return '' !== trim( wp_strip_all_tags( $html ) ) ? $html : new WP_Error( 'gso_empty', 'CTA تولید نشد.' );
	}

	/**
	 * ساخت JSON-LD.
	 *
	 * @param int $post_id شناسه.
	 * @return array
	 */
	private static function build_schema( int $post_id ): array {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return array();
		}
		$type   = 'product' === $post->post_type ? 'Product' : ( 'page' === $post->post_type ? 'WebPage' : 'Article' );
		$schema = array(
			'@context'      => 'https://schema.org',
			'@type'         => $type,
			'headline'      => wp_strip_all_tags( get_the_title( $post_id ) ),
			'name'          => wp_strip_all_tags( get_the_title( $post_id ) ),
			'description'   => wp_strip_all_tags( '' !== GSO_SEO::get_description( $post_id ) ? GSO_SEO::get_description( $post_id ) : (string) $post->post_excerpt ),
			'url'           => get_permalink( $post_id ),
			'datePublished' => get_post_time( 'c', true, $post_id ),
			'dateModified'  => get_post_modified_time( 'c', true, $post_id ),
			'inLanguage'    => get_bloginfo( 'language' ),
		);
		if ( 'Product' !== $type ) {
			$schema['author']    = array(
				'@type' => 'Person',
				'name'  => get_the_author_meta( 'display_name', (int) $post->post_author ),
			);
			$schema['publisher'] = array(
				'@type' => 'Organization',
				'name'  => get_bloginfo( 'name' ),
				'url'   => home_url( '/' ),
			);
		}
		return array_filter( $schema );
	}

	/**
	 * حذف تگ لینک شکسته با حفظ متن لنگر (پشتیبانی از &amp; و مسیر نسبی).
	 *
	 * @param string $content    محتوای HTML.
	 * @param string $target_url نشانی ثبت‌شده در ایراد.
	 * @return string
	 */
	private static function strip_broken_link( string $content, string $target_url ): string {
		$norm_target = esc_url_raw( html_entity_decode( trim( $target_url ) ) );
		if ( '' === $norm_target ) {
			return $content;
		}
		$result = preg_replace_callback(
			'/<a\b([^>]*)\bhref\s*=\s*(["\'])(.*?)\2([^>]*)>(.*?)<\/a>/is',
			static function ( $matches ) use ( $target_url, $norm_target ) {
				$href = trim( html_entity_decode( $matches[3] ) );
				if ( 0 === strpos( $href, '/' ) ) {
					$href = home_url( $href );
				}
				$norm_href = esc_url_raw( $href );
				if ( $matches[3] === $target_url || ( '' !== $norm_href && $norm_href === $norm_target ) ) {
					return $matches[5];
				}
				return $matches[0];
			},
			$content
		);
		return null === $result ? $content : $result;
	}

	/**
	 * محتوای فرضی پس از اصلاح (برای پیش‌نمایش بدون نوشتن در دیتابیس).
	 *
	 * @param string $handler نوع.
	 * @param string $content محتوای فعلی.
	 * @param array  $payload داده.
	 * @return string
	 */
	private static function projected_content( string $handler, string $content, array $payload ): string {
		switch ( $handler ) {
			case 'expand_content':
				return (string) ( $payload['content'] ?? '' );
			case 'add_cta':
				return rtrim( $content ) . "\n\n" . (string) ( $payload['html'] ?? '' );
			case 'insert_h1':
				$h1 = (string) ( $payload['text'] ?? '' );
				if ( preg_match( '/<h2\b/i', $content ) ) {
					return (string) preg_replace( '/<h2\b/i', $h1 . "\n\n" . '<h2', $content, 1 );
				}
				return $h1 . "\n\n" . $content;
			case 'insert_product_links':
			case 'insert_internal_links':
				return rtrim( $content ) . "\n\n" . (string) ( $payload['html'] ?? '' );
			case 'normalize_h1':
				$count = 0;
				return (string) preg_replace_callback(
					'/<h1\b([^>]*)>(.*?)<\/h1>/is',
					static function ( $matches ) use ( &$count ) {
						++$count;
						return 1 === $count ? $matches[0] : '<h2' . $matches[1] . '>' . $matches[2] . '</h2>';
					},
					$content
				);
			case 'dedupe_ids':
				$seen = array();
				return (string) preg_replace_callback(
					'/(\bid\s*=\s*["\'])([^"\']+)(["\'])/i',
					static function ( $matches ) use ( &$seen ) {
						$id = $matches[2];
						if ( ! isset( $seen[ $id ] ) ) {
							$seen[ $id ] = 1;
							return $matches[0];
						}
						++$seen[ $id ];
						return $matches[1] . $id . '-' . $seen[ $id ] . $matches[3];
					},
					$content
				);
			case 'remove_broken_link':
				return self::strip_broken_link( $content, (string) ( $payload['url'] ?? '' ) );
			default:
				return $content;
		}
	}

	/**
	 * Diff ساده خط‌محور برای نمایش قبل/بعد.
	 *
	 * @param string $before قبل.
	 * @param string $after  بعد.
	 * @return array
	 */
	public static function simple_diff( string $before, string $after ): array {
		$split = static function ( $text ) {
			$lines = preg_split( '/\r\n|\r|\n/', wp_strip_all_tags( $text ) );
			return array_values( array_filter( array_map( 'trim', (array) $lines ), 'strlen' ) );
		};
		$old = $split( $before );
		$new = $split( $after );
		return array(
			'removed' => array_values( array_diff( $old, $new ) ),
			'added'   => array_values( array_diff( $new, $old ) ),
		);
	}
}
