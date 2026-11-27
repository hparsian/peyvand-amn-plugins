<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * قرارداد یکپارچهٔ JSON خروجی Gemini (نسخهٔ ۱.۰).
 *
 * هر فراخوانی تحلیلی یا اصلاحی که با پرچم contract اجرا شود، یک «پاکت» استاندارد
 * برمی‌گرداند تا موتور افزونه بتواند بدون حدس‌زدن، اعتبارسنجی و صف‌بندی کند:
 * schema_version، mode، target، health_score، summary، issues، proposed_changes و warnings.
 *
 * چرا یک کلاس جدا: تعریف اسکیما در یک جا می‌ماند، پرامپت و اعتبارسنجی از همان
 * منبع تغذیه می‌شوند و افزودن فیلد تازه یک نقطهٔ تغییر دارد.
 */
class GSO_Contract {

	const SCHEMA_VERSION = '1.0';

	/**
	 * حالت‌های مجاز گزارش.
	 *
	 * @return string[]
	 */
	public static function modes(): array {
		return array( 'audit', 'fix_preview', 'bulk_fix' );
	}

	/**
	 * نوع آبجکت هدف.
	 *
	 * @return string[]
	 */
	public static function object_types(): array {
		return array( 'post', 'page', 'product', 'product_variation', 'term', 'menu', 'media', 'site' );
	}

	/**
	 * دستهٔ ایراد.
	 *
	 * @return string[]
	 */
	public static function categories(): array {
		return array( 'seo', 'content', 'structure', 'media', 'woocommerce', 'technical' );
	}

	/**
	 * سطح بحران (چهار سطح، هم‌راستا با ستون severity که varchar است).
	 *
	 * @return string[]
	 */
	public static function severities(): array {
		return array( 'critical', 'high', 'medium', 'low' );
	}

	/**
	 * مسیرهای فیلد قابل اصلاح.
	 *
	 * @return string[]
	 */
	public static function field_paths(): array {
		return array(
			'seo.meta_description',
			'seo.title',
			'post_content',
			'post_excerpt',
			'thumbnail',
			'gallery',
			'price',
			'stock_status',
			'headings',
		);
	}

	/**
	 * وزن سطح برای مرتب‌سازی و امتیاز.
	 *
	 * @return array<string,int>
	 */
	public static function severity_weights(): array {
		return array(
			'critical' => 0,
			'high'     => 1,
			'medium'   => 2,
			'low'      => 3,
		);
	}

	/**
	 * متن قرارداد که به انتهای پرامپت اضافه می‌شود.
	 *
	 * @param string $mode        audit|fix_preview|bulk_fix.
	 * @param string $object_type نوع آبجکت.
	 * @return string
	 */
	public static function instruction( string $mode = 'audit', string $object_type = 'post' ): string {
		$mode        = in_array( $mode, self::modes(), true ) ? $mode : 'audit';
		$object_type = in_array( $object_type, self::object_types(), true ) ? $object_type : 'post';

		return "قرارداد خروجی (الزامی و بدون استثنا):\n"
			. "- فقط و فقط یک شیء JSON معتبر برگردان. هیچ متن، توضیح، مقدمه، جملهٔ پایانی یا بلوک ```json پیش و پس از JSON ننویس.\n"
			. "- کلیدها را دقیقاً به همان شکل انگلیسی بنویس و هیچ کلید اضافه‌ای خارج از قرارداد نساز.\n"
			. "- schema_version باید \"1.0\" باشد و mode باید \"{$mode}\" و target.object_type باید \"{$object_type}\" باشد.\n"
			. '- کلیدهای پاکت: schema_version، mode، target، health_score، summary، issues، proposed_changes، warnings.' .
			"\n"
			. "- target شامل object_type، object_id (عدد صحیح) و url است.\n"
			. "- health_score عدد صحیح ۰ تا ۱۰۰؛ summary یک پاراگراف کوتاه فارسی.\n"
			. "- هر آیتم issues باید این کلیدها را داشته باشد: issue_id (اسلاگ انگلیسی با حروف بزرگ و آندرلاین)، category (seo|content|structure|media|woocommerce|technical)، severity (critical|high|medium|low)، priority_rank (از ۱ برای مهم‌ترین)، title، description، field_path، current_value_excerpt، recommended_action، auto_fix_safe (true|false) و requires_manual_approval (true|false).\n"
			. "- issues را بر اساس priority_rank صعودی مرتب کن و حداکثر ۱۰ ایراد بده؛ ایراد تکراری یا کلی ننویس.\n"
			. "- proposed_changes یک شیء با کلیدهای seo، content، media و woocommerce است. در حالت audit مقادیر این بخش را خالی بگذار (شیء خالی، آرایهٔ خالی یا رشتهٔ خالی) و فقط ایرادها را گزارش کن.\n"
			. "- در seo: detected_plugin، seo_title، meta_description (۱۲۰ تا ۱۵۵ کاراکتر)، focus_keyword، canonical_url، robots_index (index|noindex) و robots_follow (follow|nofollow).\n"
			. "- در content: post_title، post_content_html، post_excerpt، headings_audit_note و internal_links (آرایه‌ای از anchor_text، target_object_id، target_url، reason).\n"
			. "- در media: آرایه‌ای از attachment_id و suggested_alt.\n"
			. "- در woocommerce (فقط برای محصول): product_id، short_description_html، description_html، suggested_categories (term_id، name، is_new)، suggested_tags، media_alt_updates (attachment_id، role، suggested_alt)، complementary_product_ids، commercial_checks (has_price، stock_status، missing_data_flags) و unverified_specs_note.\n"
			. "- هیچ مشخصه، عدد، گارانتی، جنس یا ادعای فنی‌ای که در دادهٔ ورودی نبوده نساز؛ هر ابهام را در unverified_specs_note بنویس.\n"
			. "- warnings آرایه‌ای از رشته‌های فارسی کوتاه دربارهٔ دادهٔ ناقص یا مفروضات است.\n"
			. '- اگر داده برای بخشی کافی نیست، همان بخش را خالی بگذار؛ JSON را ناقص یا با کاما/آکولاد نامتوازن رها نکن.';
	}

	/**
	 * اسکیمای پاکت برای responseSchema جمینای.
	 *
	 * @param string $mode        حالت.
	 * @param string $object_type نوع آبجکت.
	 * @return array
	 */
	public static function envelope_schema( string $mode = 'audit', string $object_type = 'post' ): array {
		$mode        = in_array( $mode, self::modes(), true ) ? $mode : 'audit';
		$object_type = in_array( $object_type, self::object_types(), true ) ? $object_type : 'post';

		$issue = array(
			'type'       => 'OBJECT',
			'properties' => array(
				'issue_id'                => array( 'type' => 'STRING' ),
				'category'                => array( 'type' => 'STRING' ),
				'severity'                => array( 'type' => 'STRING' ),
				'priority_rank'           => array( 'type' => 'INTEGER' ),
				'title'                   => array( 'type' => 'STRING' ),
				'description'             => array( 'type' => 'STRING' ),
				'field_path'              => array( 'type' => 'STRING' ),
				'current_value_excerpt'   => array( 'type' => 'STRING' ),
				'recommended_action'      => array( 'type' => 'STRING' ),
				'auto_fix_safe'           => array( 'type' => 'BOOLEAN' ),
				'requires_manual_approval' => array( 'type' => 'BOOLEAN' ),
			),
			'required'   => array(
				'issue_id',
				'category',
				'severity',
				'priority_rank',
				'title',
				'description',
				'field_path',
				'current_value_excerpt',
				'recommended_action',
				'auto_fix_safe',
				'requires_manual_approval',
			),
		);

		$internal_link = array(
			'type'       => 'OBJECT',
			'properties' => array(
				'anchor_text'      => array( 'type' => 'STRING' ),
				'target_object_id' => array( 'type' => 'INTEGER' ),
				'target_url'       => array( 'type' => 'STRING' ),
				'reason'           => array( 'type' => 'STRING' ),
			),
			'required'   => array( 'anchor_text', 'target_object_id', 'target_url', 'reason' ),
		);

		$media_alt = array(
			'type'       => 'OBJECT',
			'properties' => array(
				'attachment_id' => array( 'type' => 'INTEGER' ),
				'suggested_alt' => array( 'type' => 'STRING' ),
			),
			'required'   => array( 'attachment_id', 'suggested_alt' ),
		);

		$product_media = array(
			'type'       => 'OBJECT',
			'properties' => array(
				'attachment_id' => array( 'type' => 'INTEGER' ),
				'role'          => array( 'type' => 'STRING' ),
				'suggested_alt' => array( 'type' => 'STRING' ),
			),
			'required'   => array( 'attachment_id', 'role', 'suggested_alt' ),
		);

		$category = array(
			'type'       => 'OBJECT',
			'properties' => array(
				'term_id' => array( 'type' => 'INTEGER' ),
				'name'    => array( 'type' => 'STRING' ),
				'is_new'  => array( 'type' => 'BOOLEAN' ),
			),
			'required'   => array( 'term_id', 'name', 'is_new' ),
		);

		$commercial = array(
			'type'       => 'OBJECT',
			'properties' => array(
				'has_price'         => array( 'type' => 'BOOLEAN' ),
				'stock_status'      => array( 'type' => 'STRING' ),
				'missing_data_flags' => array(
					'type'  => 'ARRAY',
					'items' => array( 'type' => 'STRING' ),
				),
			),
			'required'   => array( 'has_price', 'stock_status', 'missing_data_flags' ),
		);

		$changes = array(
			'type'       => 'OBJECT',
			'properties' => array(
				'seo'         => array(
					'type'       => 'OBJECT',
					'properties' => array(
						'detected_plugin'  => array( 'type' => 'STRING' ),
						'seo_title'        => array( 'type' => 'STRING' ),
						'meta_description' => array( 'type' => 'STRING' ),
						'focus_keyword'    => array( 'type' => 'STRING' ),
						'canonical_url'    => array( 'type' => 'STRING' ),
						'robots_index'     => array( 'type' => 'STRING' ),
						'robots_follow'    => array( 'type' => 'STRING' ),
					),
				),
				'content'     => array(
					'type'       => 'OBJECT',
					'properties' => array(
						'post_title'          => array( 'type' => 'STRING' ),
						'post_content_html'   => array( 'type' => 'STRING' ),
						'post_excerpt'        => array( 'type' => 'STRING' ),
						'headings_audit_note' => array( 'type' => 'STRING' ),
						'internal_links'      => array(
							'type'  => 'ARRAY',
							'items' => $internal_link,
						),
					),
				),
				'media'       => array(
					'type'  => 'ARRAY',
					'items' => $media_alt,
				),
				'woocommerce' => array(
					'type'       => 'OBJECT',
					'properties' => array(
						'product_id'              => array( 'type' => 'INTEGER' ),
						'short_description_html'  => array( 'type' => 'STRING' ),
						'description_html'        => array( 'type' => 'STRING' ),
						'suggested_categories'    => array(
							'type'  => 'ARRAY',
							'items' => $category,
						),
						'suggested_tags'          => array(
							'type'  => 'ARRAY',
							'items' => array( 'type' => 'STRING' ),
						),
						'media_alt_updates'       => array(
							'type'  => 'ARRAY',
							'items' => $product_media,
						),
						'complementary_product_ids' => array(
							'type'  => 'ARRAY',
							'items' => array( 'type' => 'INTEGER' ),
						),
						'commercial_checks'       => $commercial,
						'unverified_specs_note'   => array( 'type' => 'STRING' ),
					),
				),
			),
		);

		return array(
			'type'       => 'OBJECT',
			'properties' => array(
				'schema_version'   => array( 'type' => 'STRING' ),
				'mode'             => array( 'type' => 'STRING' ),
				'target'           => array(
					'type'       => 'OBJECT',
					'properties' => array(
						'object_type' => array( 'type' => 'STRING' ),
						'object_id'   => array( 'type' => 'INTEGER' ),
						'url'         => array( 'type' => 'STRING' ),
					),
					'required'   => array( 'object_type', 'object_id', 'url' ),
				),
				'health_score'     => array( 'type' => 'INTEGER' ),
				'summary'          => array( 'type' => 'STRING' ),
				'issues'           => array(
					'type'  => 'ARRAY',
					'items' => $issue,
				),
				'proposed_changes' => $changes,
				'warnings'         => array(
					'type'  => 'ARRAY',
					'items' => array( 'type' => 'STRING' ),
				),
			),
			'required'   => array( 'schema_version', 'mode', 'target', 'health_score', 'summary', 'issues', 'proposed_changes', 'warnings' ),
		);
	}

	/**
	 * استانداردسازی پاسخ مدل به پاکت معتبر.
	 *
	 * فیلدهای بیرون از قرارداد حذف، نوع‌ها اصلاح و مقادیر غیرمجاز به مقدار امن
	 * تبدیل می‌شوند؛ نتیجه همیشه قابل ذخیره و مقایسه است.
	 *
	 * @param array  $data        دادهٔ decode‌شده.
	 * @param string $mode        حالت درخواستی.
	 * @param array  $target      هدف درخواستی (object_type, object_id, url).
	 * @return array
	 */
	public static function normalize( array $data, string $mode = 'audit', array $target = array() ): array {
		$mode = in_array( $mode, self::modes(), true ) ? $mode : 'audit';

		$fallback = array(
			'object_type' => in_array( (string) ( $target['object_type'] ?? '' ), self::object_types(), true ) ? (string) $target['object_type'] : 'post',
			'object_id'   => absint( $target['object_id'] ?? 0 ),
			'url'         => esc_url_raw( (string) ( $target['url'] ?? '' ) ),
		);

		$raw_target = is_array( $data['target'] ?? null ) ? $data['target'] : array();
		$type       = (string) ( $raw_target['object_type'] ?? $fallback['object_type'] );
		$object_id  = absint( $raw_target['object_id'] ?? $fallback['object_id'] );
		$url        = esc_url_raw( (string) ( $raw_target['url'] ?? $fallback['url'] ) );

		$out = array(
			'schema_version'   => self::SCHEMA_VERSION,
			'mode'             => in_array( (string) ( $data['mode'] ?? '' ), self::modes(), true ) ? (string) $data['mode'] : $mode,
			'target'           => array(
				'object_type' => in_array( $type, self::object_types(), true ) ? $type : $fallback['object_type'],
				'object_id'   => $object_id,
				'url'         => $url,
			),
			'health_score'     => max( 0, min( 100, (int) ( $data['health_score'] ?? 0 ) ) ),
			'summary'          => sanitize_textarea_field( (string) ( $data['summary'] ?? '' ) ),
			'issues'           => self::normalize_issues( $data['issues'] ?? array() ),
			'proposed_changes' => self::normalize_changes( $data['proposed_changes'] ?? array() ),
			'warnings'         => array(),
		);

		foreach ( (array) ( $data['warnings'] ?? array() ) as $warning ) {
			if ( ! is_scalar( $warning ) ) {
				continue;
			}
			$text = sanitize_text_field( (string) $warning );
			if ( '' !== $text ) {
				$out['warnings'][] = $text;
			}
		}

		return $out;
	}

	/**
	 * استانداردسازی فهرست ایرادها.
	 *
	 * @param mixed $issues ورودی مدل.
	 * @return array
	 */
	private static function normalize_issues( $issues ): array {
		$weights = self::severity_weights();
		$out     = array();
		$seen    = array();

		foreach ( (array) $issues as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}

			$issue_id = sanitize_key( (string) ( $item['issue_id'] ?? '' ) );
			if ( '' === $issue_id ) {
				$issue_id = 'ai_suggestion';
			}

			$category = sanitize_key( (string) ( $item['category'] ?? '' ) );
			if ( ! in_array( $category, self::categories(), true ) ) {
				$category = 'content';
			}

			$severity = sanitize_key( (string) ( $item['severity'] ?? '' ) );
			if ( ! isset( $weights[ $severity ] ) ) {
				$severity = 'low';
			}

			$field_path = (string) ( $item['field_path'] ?? '' );
			if ( ! in_array( $field_path, self::field_paths(), true ) ) {
				$field_path = '';
			}

			$duplicate_key = $issue_id . '|' . $field_path;
			if ( isset( $seen[ $duplicate_key ] ) ) {
				continue;
			}
			$seen[ $duplicate_key ] = true;

			$out[] = array(
				'issue_id'                 => $issue_id,
				'category'                 => $category,
				'severity'                 => $severity,
				'priority_rank'            => max( 1, absint( $item['priority_rank'] ?? ( count( $out ) + 1 ) ) ),
				'title'                    => sanitize_text_field( (string) ( $item['title'] ?? '' ) ),
				'description'              => sanitize_textarea_field( (string) ( $item['description'] ?? '' ) ),
				'field_path'               => $field_path,
				'current_value_excerpt'    => sanitize_textarea_field( (string) ( $item['current_value_excerpt'] ?? '' ) ),
				'recommended_action'       => sanitize_textarea_field( (string) ( $item['recommended_action'] ?? '' ) ),
				'auto_fix_safe'            => ! empty( $item['auto_fix_safe'] ),
				'requires_manual_approval' => ! empty( $item['requires_manual_approval'] ),
			);
		}

		usort(
			$out,
			static function ( $a, $b ) use ( $weights ) {
				$rank = $weights[ $a['severity'] ] <=> $weights[ $b['severity'] ];
				if ( 0 !== $rank ) {
					return $rank;
				}
				return $a['priority_rank'] <=> $b['priority_rank'];
			}
		);

		return $out;
	}

	/**
	 * استانداردسازی changes.
	 *
	 * @param mixed $changes ورودی مدل.
	 * @return array
	 */
	private static function normalize_changes( $changes ): array {
		$changes = is_array( $changes ) ? $changes : array();
		$seo     = is_array( $changes['seo'] ?? null ) ? $changes['seo'] : array();
		$content = is_array( $changes['content'] ?? null ) ? $changes['content'] : array();
		$woo     = is_array( $changes['woocommerce'] ?? null ) ? $changes['woocommerce'] : array();

		$out = array(
			'seo'         => array(
				'detected_plugin'  => sanitize_key( (string) ( $seo['detected_plugin'] ?? '' ) ),
				'seo_title'        => sanitize_text_field( (string) ( $seo['seo_title'] ?? '' ) ),
				'meta_description' => sanitize_textarea_field( (string) ( $seo['meta_description'] ?? '' ) ),
				'focus_keyword'    => sanitize_text_field( (string) ( $seo['focus_keyword'] ?? '' ) ),
				'canonical_url'    => esc_url_raw( (string) ( $seo['canonical_url'] ?? '' ) ),
				'robots_index'     => in_array( (string) ( $seo['robots_index'] ?? '' ), array( 'index', 'noindex' ), true ) ? (string) $seo['robots_index'] : '',
				'robots_follow'    => in_array( (string) ( $seo['robots_follow'] ?? '' ), array( 'follow', 'nofollow' ), true ) ? (string) $seo['robots_follow'] : '',
			),
			'content'     => array(
				'post_title'          => sanitize_text_field( (string) ( $content['post_title'] ?? '' ) ),
				'post_content_html'   => wp_kses_post( (string) ( $content['post_content_html'] ?? '' ) ),
				'post_excerpt'        => sanitize_textarea_field( (string) ( $content['post_excerpt'] ?? '' ) ),
				'headings_audit_note' => sanitize_textarea_field( (string) ( $content['headings_audit_note'] ?? '' ) ),
				'internal_links'      => array(),
			),
			'media'       => array(),
			'woocommerce' => array(
				'product_id'                => absint( $woo['product_id'] ?? 0 ),
				'short_description_html'    => wp_kses_post( (string) ( $woo['short_description_html'] ?? '' ) ),
				'description_html'          => wp_kses_post( (string) ( $woo['description_html'] ?? '' ) ),
				'suggested_categories'      => array(),
				'suggested_tags'            => array(),
				'media_alt_updates'         => array(),
				'complementary_product_ids' => array(),
				'commercial_checks'         => array(
					'has_price'          => ! empty( $woo['commercial_checks']['has_price'] ),
					'stock_status'       => in_array( (string) ( $woo['commercial_checks']['stock_status'] ?? '' ), array( 'instock', 'outofstock', 'onbackorder' ), true ) ? (string) $woo['commercial_checks']['stock_status'] : '',
					'missing_data_flags' => array(),
				),
				'unverified_specs_note'     => sanitize_textarea_field( (string) ( $woo['unverified_specs_note'] ?? '' ) ),
			),
		);

		foreach ( (array) ( $content['internal_links'] ?? array() ) as $link ) {
			if ( ! is_array( $link ) ) {
				continue;
			}
			$anchor = sanitize_text_field( (string) ( $link['anchor_text'] ?? '' ) );
			$url    = esc_url_raw( (string) ( $link['target_url'] ?? '' ) );
			if ( '' === $anchor || '' === $url ) {
				continue;
			}
			$out['content']['internal_links'][] = array(
				'anchor_text'      => $anchor,
				'target_object_id' => absint( $link['target_object_id'] ?? 0 ),
				'target_url'       => $url,
				'reason'           => sanitize_text_field( (string) ( $link['reason'] ?? '' ) ),
			);
		}

		foreach ( (array) ( $changes['media'] ?? array() ) as $media ) {
			if ( ! is_array( $media ) ) {
				continue;
			}
			$attachment = absint( $media['attachment_id'] ?? 0 );
			$alt        = sanitize_text_field( (string) ( $media['suggested_alt'] ?? '' ) );
			if ( $attachment < 1 || '' === $alt ) {
				continue;
			}
			$out['media'][] = array(
				'attachment_id' => $attachment,
				'suggested_alt' => $alt,
			);
		}

		foreach ( (array) ( $woo['suggested_categories'] ?? array() ) as $term ) {
			if ( ! is_array( $term ) ) {
				continue;
			}
			$name = sanitize_text_field( (string) ( $term['name'] ?? '' ) );
			if ( '' === $name ) {
				continue;
			}
			$out['woocommerce']['suggested_categories'][] = array(
				'term_id' => absint( $term['term_id'] ?? 0 ),
				'name'    => $name,
				'is_new'  => ! empty( $term['is_new'] ),
			);
		}

		foreach ( (array) ( $woo['suggested_tags'] ?? array() ) as $tag ) {
			$tag = sanitize_text_field( (string) $tag );
			if ( '' !== $tag ) {
				$out['woocommerce']['suggested_tags'][] = $tag;
			}
		}

		foreach ( (array) ( $woo['media_alt_updates'] ?? array() ) as $media ) {
			if ( ! is_array( $media ) ) {
				continue;
			}
			$attachment = absint( $media['attachment_id'] ?? 0 );
			$alt        = sanitize_text_field( (string) ( $media['suggested_alt'] ?? '' ) );
			if ( $attachment < 1 || '' === $alt ) {
				continue;
			}
			$role = in_array( (string) ( $media['role'] ?? '' ), array( 'featured', 'gallery' ), true ) ? (string) $media['role'] : 'gallery';
			$out['woocommerce']['media_alt_updates'][] = array(
				'attachment_id' => $attachment,
				'role'          => $role,
				'suggested_alt' => $alt,
			);
		}

		foreach ( (array) ( $woo['complementary_product_ids'] ?? array() ) as $product_id ) {
			$product_id = absint( $product_id );
			if ( $product_id > 0 ) {
				$out['woocommerce']['complementary_product_ids'][] = $product_id;
			}
		}

		foreach ( (array) ( $woo['commercial_checks']['missing_data_flags'] ?? array() ) as $flag ) {
			$flag = sanitize_key( (string) $flag );
			if ( '' !== $flag ) {
				$out['woocommerce']['commercial_checks']['missing_data_flags'][] = $flag;
			}
		}

		return $out;
	}
}
