<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * تولید و بازنویسی محتوا، متا، Alt، لینک داخلی و CSS.
 */
class GSO_Generator {

	/**
	 * تولید نوشته تازه.
	 *
	 * @param array $args topic, post_type, status, tone, keywords, instructions, length.
	 * @return array|WP_Error
	 */
	public static function generate_content( array $args ) {
		$args = wp_parse_args(
			$args,
			array(
				'topic'        => '',
				'post_type'    => 'post',
				'status'       => 'draft',
				'tone'         => 'حرفه‌ای، روشن و کاربردی',
				'keywords'     => '',
				'instructions' => '',
				'length'       => 900,
			)
		);

		$topic = sanitize_text_field( $args['topic'] );
		if ( '' === $topic ) {
			return new WP_Error( 'gso_topic', 'موضوع محتوا را وارد کنید.' );
		}

		$schema = self::content_schema();
		$prompt = "یک محتوای کامل و بی‌نقص فارسی برای وردپرس تولید کن.\n"
			. "موضوع: {$topic}\n"
			. 'نوع محتوا: ' . sanitize_key( $args['post_type'] ) . "\n"
			. 'لحن: ' . sanitize_text_field( $args['tone'] ) . "\n"
			. 'کلیدواژه‌های هدف: ' . sanitize_text_field( $args['keywords'] ) . "\n"
			. 'حدود کلمات: ' . absint( $args['length'] ) . "\n"
			. 'دستورهای ویژه: ' . sanitize_textarea_field( $args['instructions'] ) . "\n\n"
			. "قواعد: خروجی HTML تمیز و بدون <html>/<body>/<script>/<style>. هیچ H1 نگذار (پوسته عنوان را می‌سازد)؛ از H2 و H3 استفاده کن. "
			. "شروع با یک پاراگراف مقدمه، سپس بخش‌های منطقی، حداقل یک CTA طبیعی و در پایان ۲ تا ۴ پرسش متداول. "
			. "عنوان حداکثر ۷۰ کاراکتر، توضیحات متا ۱۲۰ تا ۱۵۵ کاراکتر و عنوان سئو ۳۰ تا ۶۰ کاراکتر باشد. "
			. "هیچ ادعای پزشکی/مالی/حقوقی بی‌پشتوانه نساز.";

		$result = GSO_Gemini::json( $prompt, $schema, array( 'model_kind' => 'deep' ) );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$title   = sanitize_text_field( (string) ( $result['title'] ?? $topic ) );
		$content = self::prepare_html( (string) ( $result['content_html'] ?? '' ) );
		if ( '' === trim( wp_strip_all_tags( $content ) ) ) {
			return new WP_Error( 'gso_empty', 'Gemini محتوای قابل‌استفاده برنگرداند.' );
		}

		$allowed_types = GSO_Settings::watched_post_types();
		$post_type     = in_array( $args['post_type'], $allowed_types, true ) ? $args['post_type'] : 'post';
		$status        = in_array( $args['status'], array( 'draft', 'pending', 'publish', 'private' ), true ) ? $args['status'] : 'draft';

		$post_id = wp_insert_post(
			array(
				'post_title'   => $title,
				'post_content' => $content,
				'post_excerpt' => sanitize_textarea_field( (string) ( $result['excerpt'] ?? '' ) ),
				'post_status'  => $status,
				'post_type'    => $post_type,
				'post_author'  => get_current_user_id(),
			),
			true
		);
		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		if ( ! empty( $result['meta_title'] ) ) {
			GSO_SEO::set_title( (int) $post_id, (string) $result['meta_title'] );
		}
		if ( ! empty( $result['meta_description'] ) ) {
			GSO_SEO::set_description( (int) $post_id, (string) $result['meta_description'] );
		}
		if ( ! empty( $result['focus_keyword'] ) ) {
			update_post_meta( (int) $post_id, 'gso_focus_keyword', sanitize_text_field( (string) $result['focus_keyword'] ) );
		}

		GSO_Logger::log(
			'success',
			'generator.create',
			sprintf( 'محتوای تازه ساخته شد: «%s» (%s).', $title, $status ),
			array( 'post_id' => (int) $post_id, 'model' => GSO_Settings::model( 'deep' ) )
		);

		return array(
			'post_id'   => (int) $post_id,
			'title'     => $title,
			'content'   => $content,
			'excerpt'   => (string) ( $result['excerpt'] ?? '' ),
			'edit_link' => admin_url( 'post.php?post=' . (int) $post_id . '&action=edit' ),
		);
	}

	/**
	 * بازنویسی پیشنهادی یک نوشته (بدون اعمال).
	 *
	 * @param int    $post_id      شناسه.
	 * @param string $instructions دستور.
	 * @param string $focus        تمرکز (seo|readability|expand|all).
	 * @return array|WP_Error
	 */
	public static function rewrite_post( int $post_id, string $instructions = '', string $focus = 'all' ) {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return new WP_Error( 'gso_missing', 'نوشته پیدا نشد.' );
		}

		$schema = array(
			'type'       => 'OBJECT',
			'properties' => array(
				'title'            => array( 'type' => 'STRING' ),
				'content_html'     => array( 'type' => 'STRING' ),
				'excerpt'          => array( 'type' => 'STRING' ),
				'meta_title'       => array( 'type' => 'STRING' ),
				'meta_description' => array( 'type' => 'STRING' ),
			),
			'required'   => array( 'title', 'content_html', 'excerpt', 'meta_title', 'meta_description' ),
		);

		$prompt = "محتوای زیر را بازنویسی و غنی‌سازی کن. تمرکز: {$focus}. دستور اپراتور: " . sanitize_textarea_field( $instructions ) . "\n\n"
			. 'عنوان فعلی: ' . $post->post_title . "\n"
			. "محتوای فعلی:\n" . mb_substr( (string) $post->post_content, 0, 12000 ) . "\n\n"
			. 'قواعد: لحن و زبان اصلی را حفظ کن، H1 نگذار، ساختار H2/H3 پیوسته، CTA طبیعی، بدون تگ script/style، '
			. 'و هیچ واقعیت جدیدی که در متن نیست اختراع نکن. عنوان، خلاصه و متای سئو را هم بهینه بده.';

		$result = GSO_Gemini::json( $prompt, $schema, array( 'model_kind' => 'deep' ) );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return array(
			'title'            => sanitize_text_field( (string) ( $result['title'] ?? $post->post_title ) ),
			'content'          => self::prepare_html( (string) ( $result['content_html'] ?? '' ) ),
			'excerpt'          => sanitize_textarea_field( (string) ( $result['excerpt'] ?? '' ) ),
			'meta_title'       => sanitize_text_field( (string) ( $result['meta_title'] ?? '' ) ),
			'meta_description' => sanitize_textarea_field( (string) ( $result['meta_description'] ?? '' ) ),
		);
	}

	/**
	 * تولید عنوان و توضیحات متا برای یک صفحه.
	 *
	 * @param int $post_id شناسه.
	 * @return array|WP_Error
	 */
	public static function meta_for_post( int $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return new WP_Error( 'gso_missing', 'نوشته پیدا نشد.' );
		}

		$plain = wp_strip_all_tags( strip_shortcodes( (string) $post->post_content ) );
		$plain = mb_substr( trim( preg_replace( '/\s+/u', ' ', $plain ) ), 0, 2500 );

		$schema = array(
			'type'       => 'OBJECT',
			'properties' => array(
				'meta_title'       => array( 'type' => 'STRING' ),
				'meta_description' => array( 'type' => 'STRING' ),
			),
			'required'   => array( 'meta_title', 'meta_description' ),
		);

		$prompt = "برای صفحه زیر عنوان سئو و توضیحات متای فارسی بنویس.\n"
			. 'عنوان: ' . $post->post_title . "\n"
			. 'خلاصه فعلی: ' . $post->post_excerpt . "\n"
			. "متن:\n{$plain}\n\n"
			. 'عنوان بین ۳۰ تا ۶۰ کاراکتر و با کلیدواژه اصلی در ابتدا؛ توضیحات بین ۱۲۰ تا ۱۵۵ کاراکتر، ترغیب‌کننده و بدون عبارت کلیشه‌ای.';

		$result = GSO_Gemini::json( $prompt, $schema, array( 'model_kind' => 'bulk' ) );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return array(
			'meta_title'       => sanitize_text_field( (string) ( $result['meta_title'] ?? '' ) ),
			'meta_description' => sanitize_textarea_field( (string) ( $result['meta_description'] ?? '' ) ),
		);
	}

	/**
	 * تولید Alt برای یک تصویر.
	 *
	 * @param int    $attachment_id شناسه پیوست (۰ برای تصویر درون‌محتوا).
	 * @param string $src           نشانی تصویر.
	 * @param string $context       متن زمینه (عنوان صفحه).
	 * @return string|WP_Error
	 */
	public static function alt_for_image( int $attachment_id = 0, string $src = '', string $context = '' ) {
		$file = '';
		if ( $attachment_id > 0 ) {
			$file  = (string) get_the_title( $attachment_id );
			$src   = (string) wp_get_attachment_url( $attachment_id );
			$file  = '' !== $file ? $file : basename( (string) parse_url( $src, PHP_URL_PATH ) );
		}
		if ( '' === $src && '' === $file ) {
			return new WP_Error( 'gso_alt', 'داده کافی برای تولید Alt وجود ندارد.' );
		}

		$prompt = "یک متن جایگزین (alt) کوتاه، توصیفی و فارسی برای تصویر بنویس.\n"
			. 'نام فایل: ' . $file . "\n"
			. 'نشانی: ' . $src . "\n"
			. 'صفحه: ' . sanitize_text_field( $context ) . "\n\n"
			. 'قواعد: ۵ تا ۱۲ کلمه، بدون «تصویر» یا «عکس» در ابتدا، بدون نام فایل، بدون کلیدواژه‌انبارانی. فقط خود متن alt را برگردان.';

		$text = GSO_Gemini::text( $prompt, array( 'model_kind' => 'bulk', 'max_tokens' => 120, 'temperature' => 0.3 ) );
		if ( is_wp_error( $text ) ) {
			return $text;
		}
		$text = trim( preg_replace( '/^["\']|["\']$/', '', trim( (string) $text ) ) );
		return sanitize_text_field( mb_substr( $text, 0, 160 ) );
	}

	/**
	 * متن زمینه محصول برای پرامپت‌ها (بدون حدس زدن مشخصات).
	 *
	 * @param int $post_id شناسه محصول.
	 * @return string|null
	 */
	public static function product_context( int $post_id ): ?string {
		if ( ! function_exists( 'wc_get_product' ) ) {
			return null;
		}
		$product = wc_get_product( $post_id );
		if ( ! $product ) {
			return null;
		}

		$lines = array( 'نام محصول: ' . $product->get_name() );
		if ( '' !== $product->get_sku() ) {
			$lines[] = 'کد محصول (SKU): ' . $product->get_sku();
		}

		$attributes = array();
		foreach ( $product->get_attributes() as $attribute ) {
			if ( ! $attribute instanceof WC_Product_Attribute ) {
				continue;
			}
			$name   = wc_attribute_label( $attribute->get_name() );
			$values = $attribute->is_taxonomy()
				? wc_get_product_terms( $post_id, $attribute->get_name(), array( 'fields' => 'names' ) )
				: $attribute->get_options();
			$values = is_wp_error( $values ) ? array() : $values;
			$values = array_filter( array_map( 'sanitize_text_field', (array) $values ) );
			if ( $name && $values ) {
				$attributes[] = $name . ': ' . implode( '، ', $values );
			}
		}
		if ( $attributes ) {
			$lines[] = "مشخصات ثبت‌شده محصول (تنها منبع مجاز اعداد و ویژگی‌ها):\n- " . implode( "\n- ", $attributes );
		}

		$categories = wp_get_post_terms( $post_id, 'product_cat', array( 'fields' => 'names' ) );
		if ( ! is_wp_error( $categories ) && $categories ) {
			$lines[] = 'دسته‌بندی فعلی: ' . implode( '، ', $categories );
		}

		$price = (string) $product->get_price();
		if ( '' !== $price ) {
			$lines[] = 'قیمت ثبت‌شده: ' . $price;
		}

		$excerpt = trim( wp_strip_all_tags( (string) $product->get_short_description() ) );
		if ( '' !== $excerpt ) {
			$lines[] = 'توضیح کوتاه فعلی: ' . mb_substr( $excerpt, 0, 600 );
		}

		$current = trim( wp_strip_all_tags( strip_shortcodes( (string) $product->get_description() ) ) );
		if ( '' !== $current ) {
			$lines[] = "توضیحات کامل فعلی:\n" . mb_substr( $current, 0, 4000 );
		}

		return implode( "\n", $lines );
	}

	/**
	 * تولید توضیح کوتاه متقاعدکننده برای محصول.
	 *
	 * @param int $post_id شناسه محصول.
	 * @return string|WP_Error
	 */
	public static function product_short_description( int $post_id ) {
		$context = self::product_context( $post_id );
		if ( null === $context ) {
			return new WP_Error( 'gso_woo', 'محصول ووکامرس پیدا نشد.' );
		}
		if ( '' === trim( wp_strip_all_tags( $context ) ) ) {
			return new WP_Error( 'gso_context', 'اطلاعات کافی از محصول برای تولید توضیح وجود ندارد.' );
		}

		$prompt = "برای محصول زیر یک «توضیح کوتاه» فارسی و فروش‌محور بنویس.\n"
			. $context . "\n\n"
			. "قواعد: ۱ تا ۲ جمله و حدود " . (int) GSO_Settings::get( 'woo_short_desc_words', 20 ) . ' کلمه؛ تمرکز بر مزیت خرید و مخاطب؛ '
			. 'شروع با نام یا کاربرد اصلی محصول؛ هیچ عدد، ابعاد، جنس، گارانتی یا مشخصات فنی که در اطلاعات بالا نیست نساز؛ '
			. 'بدون عبارت کلیشه‌ای، بدون ایموجی و بدون تگ HTML. فقط خود متن.';

		$text = GSO_Gemini::text( $prompt, array( 'model_kind' => 'bulk', 'max_tokens' => 200, 'temperature' => 0.4 ) );
		if ( is_wp_error( $text ) ) {
			return $text;
		}
		$text = trim( wp_strip_all_tags( (string) $text ) );
		return '' !== $text ? sanitize_textarea_field( mb_substr( $text, 0, 500 ) ) : new WP_Error( 'gso_empty', 'توضیح کوتاهی تولید نشد.' );
	}

	/**
	 * تولید توضیحات کامل و ساختاریافته محصول با حفظ دقت اطلاعات فنی.
	 *
	 * @param int $post_id شناسه محصول.
	 * @return string|WP_Error
	 */
	public static function product_full_description( int $post_id ) {
		$context = self::product_context( $post_id );
		if ( null === $context ) {
			return new WP_Error( 'gso_woo', 'محصول ووکامرس پیدا نشد.' );
		}

		$prompt = "توضیحات کامل و ساختاریافته فارسی برای این محصول بنویس.\n"
			. $context . "\n\n"
			. "ساختار خروجی (HTML تمیز، بدون <html>/<body>/<script>/<style> و بدون H1):\n"
			. "۱) یک پاراگراف معرفی کوتاه (۲ تا ۳ جمله).\n"
			. "۲) <h2>معرفی محصول</h2> با توضیح کاربرد و مخاطب مناسب.\n"
			. "۳) <h2>ویژگی‌ها و مشخصات</h2> به‌صورت <ul> — فقط ویژگی‌هایی که در اطلاعات بالا آمده یا از متن فعلی قابل استخراج است.\n"
			. "۴) <h2>مزایا و نقاط قوت</h2> به‌صورت <ul> با مزایای کاربردی و واقع‌بینانه.\n"
			. "۵) <h2>راهنمای استفاده و نگهداری</h2> با نکات عمومی و بی‌خطر.\n"
			. "۶) <h2>پرسش‌های متداول</h2> با ۲ تا ۳ پرسش و پاسخ کوتاه.\n\n"
			. 'قواعد سخت: هیچ عدد، ابعاد، وزن، جنس، رنگ، گارانتی، کشور سازنده یا مشخصات فنی تازه‌ای نساز؛ '
			. 'اگر داده‌ای نیست درباره‌اش ادعا نکن و به‌جای آن مزیت عمومی بنویس. ادعای پزشکی/مالی/حقوقی نکن. لحن حرفه‌ای و روان باشد.';

		$text = GSO_Gemini::text( $prompt, array( 'model_kind' => 'deep', 'max_tokens' => 2600, 'temperature' => 0.35 ) );
		if ( is_wp_error( $text ) ) {
			return $text;
		}
		$html = self::prepare_html( (string) $text );
		return '' !== trim( wp_strip_all_tags( $html ) ) ? $html : new WP_Error( 'gso_empty', 'توضیحات محصول تولید نشد.' );
	}

	/**
	 * تولید Alt مرتبط با محصول برای تصویر شاخص یا گالری.
	 *
	 * @param int $attachment_id شناسه پیوست.
	 * @param int $product_id    شناسه محصول.
	 * @return string|WP_Error
	 */
	public static function product_alt_for_attachment( int $attachment_id, int $product_id ) {
		$product = function_exists( 'wc_get_product' ) ? wc_get_product( $product_id ) : null;
		if ( ! $product ) {
			return new WP_Error( 'gso_woo', 'محصول ووکامرس پیدا نشد.' );
		}

		$names  = array( $product->get_name() );
		$labels = array();
		foreach ( $product->get_attributes() as $attribute ) {
			if ( $attribute instanceof WC_Product_Attribute ) {
				$label = wc_attribute_label( $attribute->get_name() );
				if ( '' !== $label ) {
					$labels[] = $label;
				}
			}
		}

		$file = $attachment_id > 0 ? (string) get_the_title( $attachment_id ) : '';
		$prompt = "یک متن جایگزین (alt) کوتاه و فارسی برای تصویر محصول بنویس.\n"
			. 'نام محصول: ' . implode( '، ', $names ) . "\n"
			. 'ویژگی‌های ثبت‌شده: ' . ( $labels ? implode( '، ', array_slice( $labels, 0, 6 ) ) : 'نامشخص' ) . "\n"
			. 'نام فایل تصویر: ' . sanitize_text_field( $file ) . "\n\n"
			. 'قواعد: ۵ تا ۱۲ کلمه، توصیفی و مرتبط با همان محصول، بدون شروع با «تصویر» یا «عکس»، بدون نام فایل، بدون عدد یا ویژگی‌ای که در بالا نیست، بدون کلیدواژه‌انبارانی. فقط متن alt.';

		$text = GSO_Gemini::text( $prompt, array( 'model_kind' => 'bulk', 'max_tokens' => 120, 'temperature' => 0.3 ) );
		if ( is_wp_error( $text ) ) {
			return $text;
		}
		$text = trim( preg_replace( '/^["\']|["\']$/', '', trim( (string) $text ) ) );
		return '' !== $text ? sanitize_text_field( mb_substr( $text, 0, 160 ) ) : new WP_Error( 'gso_empty', 'متن Alt تولید نشد.' );
	}

	/**
	 * پیشنهاد دسته‌بندی و برچسب محصول بر اساس ساختار موجود فروشگاه.
	 *
	 * @param int $post_id شناسه محصول.
	 * @return array|WP_Error
	 */
	public static function product_terms( int $post_id ) {
		$product = function_exists( 'wc_get_product' ) ? wc_get_product( $post_id ) : null;
		if ( ! $product ) {
			return new WP_Error( 'gso_woo', 'محصول ووکامرس پیدا نشد.' );
		}

		$cats = get_terms(
			array(
				'taxonomy'   => 'product_cat',
				'hide_empty' => false,
				'number'     => 80,
				'fields'     => 'id=>name',
			)
		);
		$cats = is_wp_error( $cats ) ? array() : $cats;

		$tags = get_terms(
			array(
				'taxonomy'   => 'product_tag',
				'hide_empty' => false,
				'number'     => 80,
				'fields'     => 'names',
			)
		);
		$tags = is_wp_error( $tags ) ? array() : $tags;

		$cat_list = array();
		foreach ( $cats as $id => $name ) {
			$cat_list[] = $id . ' | ' . $name;
		}

		$schema = array(
			'type'       => 'OBJECT',
			'properties' => array(
				'category_ids' => array(
					'type'  => 'ARRAY',
					'items' => array( 'type' => 'INTEGER' ),
				),
				'tags'         => array(
					'type'  => 'ARRAY',
					'items' => array( 'type' => 'STRING' ),
				),
				'reason'       => array( 'type' => 'STRING' ),
			),
			'required'   => array( 'category_ids', 'tags', 'reason' ),
		);

		$prompt = "برای محصول «" . $product->get_name() . "» دسته‌بندی و برچسب مناسب انتخاب کن.\n"
			. "دسته‌های موجود فروشگاه (شناسه | نام):\n" . implode( "\n", $cat_list ) . "\n\n"
			. "برچسب‌های موجود: " . ( $tags ? implode( '، ', $tags ) : 'هیچ' ) . "\n\n"
			. '۱ تا ۳ دسته از میان شناسه‌های موجود انتخاب کن (فقط همان‌ها). '
			. '۳ تا ۶ برچسب بده؛ اولویت با برچسب‌های موجود و در صورت نیاز برچسب تازه و کوتاه. '
			. 'reason یک جمله کوتاه درباره دلیل انتخاب. هیچ داده‌ای جعل نکن.';

		$result = GSO_Gemini::json( $prompt, $schema, array( 'model_kind' => 'bulk' ) );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$category_ids = array();
		foreach ( (array) ( $result['category_ids'] ?? array() ) as $id ) {
			$id = absint( $id );
			if ( $id > 0 && isset( $cats[ $id ] ) ) {
				$category_ids[] = $id;
			}
		}

		$new_tags = array();
		$tag_map  = array();
		foreach ( $tags as $tag ) {
			$tag_map[ self::lower( $tag ) ] = $tag;
		}
		foreach ( (array) ( $result['tags'] ?? array() ) as $tag ) {
			$tag = sanitize_text_field( (string) $tag );
			if ( '' === $tag ) {
				continue;
			}
			$key      = self::lower( $tag );
			$new_tags[] = $tag_map[ $key ] ?? $tag;
		}

		return array(
			'category_ids'   => array_values( array_unique( $category_ids ) ),
			'category_names' => array_map( static fn( $id ) => $cats[ $id ], array_values( array_unique( $category_ids ) ) ),
			'tags'           => array_values( array_unique( $new_tags ) ),
			'reason'         => sanitize_text_field( (string) ( $result['reason'] ?? '' ) ),
		);
	}

	/**
	 * پیشنهاد لینک داخلی هدفمند برای محصول (محصولات/دسته‌های مکمل).
	 *
	 * @param int $post_id شناسه محصول.
	 * @return array|WP_Error
	 */
	public static function product_internal_links( int $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return new WP_Error( 'gso_missing', 'محصول پیدا نشد.' );
		}

		$candidates = array();
		$cat_ids    = wp_get_post_terms( $post_id, 'product_cat', array( 'fields' => 'ids' ) );
		$query      = new WP_Query(
			array(
				'post_type'      => 'product',
				'post_status'    => 'publish',
				'posts_per_page' => 15,
				'post__not_in'   => array( $post_id ),
				'no_found_rows'  => true,
				'fields'         => 'ids',
				'tax_query'      => ( ! is_wp_error( $cat_ids ) && $cat_ids ) ? array(
					array(
						'taxonomy' => 'product_cat',
						'field'    => 'term_id',
						'terms'    => $cat_ids,
					),
				) : array(),
			)
		);
		foreach ( $query->posts as $id ) {
			$candidates[] = array( 'title' => get_the_title( (int) $id ), 'url' => get_permalink( (int) $id ) );
		}
		foreach ( (array) $cat_ids as $cat_id ) {
			$link = get_term_link( (int) $cat_id, 'product_cat' );
			if ( ! is_wp_error( $link ) ) {
				$term = get_term( (int) $cat_id );
				$candidates[] = array( 'title' => $term && ! is_wp_error( $term ) ? 'دسته: ' . $term->name : 'دسته محصول', 'url' => $link );
			}
		}

		$unique = array();
		foreach ( $candidates as $candidate ) {
			if ( '' !== $candidate['url'] ) {
				$unique[ $candidate['url'] ] = $candidate;
			}
		}
		$candidates = array_values( $unique );
		if ( empty( $candidates ) ) {
			return new WP_Error( 'gso_candidates', 'مقصد مکملی برای لینک داخلی پیدا نشد.' );
		}

		$list = array();
		foreach ( array_slice( $candidates, 0, 20 ) as $candidate ) {
			$list[] = '- ' . $candidate['title'] . ' | ' . $candidate['url'];
		}

		$schema = array(
			'type'       => 'OBJECT',
			'properties' => array(
				'links' => array(
					'type'  => 'ARRAY',
					'items' => array(
						'type'       => 'OBJECT',
						'properties' => array(
							'url'    => array( 'type' => 'STRING' ),
							'anchor' => array( 'type' => 'STRING' ),
							'reason' => array( 'type' => 'STRING' ),
						),
						'required'   => array( 'url', 'anchor', 'reason' ),
					),
				),
			),
			'required'   => array( 'links' ),
		);

		$prompt = "برای محصول «{$post->post_title}» حداکثر ۳ لینک داخلی مکمل از فهرست زیر انتخاب کن.\n"
			. "فهرست مجاز:\n" . implode( "\n", $list ) . "\n\n"
			. 'برای هر لینک: url دقیقاً از فهرست، anchor طبیعی و فارسی، reason کوتاه. اگر مناسب نیست آرایه خالی بده.';

		$result = GSO_Gemini::json( $prompt, $schema, array( 'model_kind' => 'bulk' ) );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$allowed = wp_list_pluck( $candidates, 'url' );
		$links   = array();
		foreach ( (array) ( $result['links'] ?? array() ) as $link ) {
			$url = esc_url_raw( (string) ( $link['url'] ?? '' ) );
			if ( ! in_array( $url, $allowed, true ) ) {
				continue;
			}
			$links[] = array(
				'url'    => $url,
				'anchor' => sanitize_text_field( (string) ( $link['anchor'] ?? '' ) ),
				'reason' => sanitize_text_field( (string) ( $link['reason'] ?? '' ) ),
			);
		}

		$html = '';
		foreach ( $links as $link ) {
			$html .= '<li><a href="' . esc_url( $link['url'] ) . '">' . esc_html( $link['anchor'] ) . '</a> — ' . esc_html( $link['reason'] ) . '</li>';
		}
		if ( '' !== $html ) {
			$html = '<h2>محصولات و دسته‌های مرتبط</h2><ul class="gso-internal-links">' . $html . '</ul>';
		}

		return array( 'links' => $links, 'html' => $html );
	}

	/**
	 * پیشنهاد لینک‌سازی داخلی برای یک صفحه.
	 *
	 * @param int $post_id شناسه.
	 * @return array|WP_Error
	 */
	public static function internal_links( int $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return new WP_Error( 'gso_missing', 'نوشته پیدا نشد.' );
		}

		$candidates = self::link_candidates( $post );
		if ( empty( $candidates ) ) {
			return new WP_Error( 'gso_candidates', 'صفحه مرتبطی برای پیشنهاد لینک پیدا نشد.' );
		}

		$list = array();
		foreach ( $candidates as $candidate ) {
			$list[] = '- ' . $candidate['title'] . ' | ' . $candidate['url'];
		}

		$schema = array(
			'type'       => 'OBJECT',
			'properties' => array(
				'links' => array(
					'type'  => 'ARRAY',
					'items' => array(
						'type'       => 'OBJECT',
						'properties' => array(
							'url'    => array( 'type' => 'STRING' ),
							'anchor' => array( 'type' => 'STRING' ),
							'reason' => array( 'type' => 'STRING' ),
						),
						'required'   => array( 'url', 'anchor', 'reason' ),
					),
				),
			),
			'required'   => array( 'links' ),
		);

		$prompt = "برای صفحه «{$post->post_title}» حداکثر ۳ لینک داخلی مرتبط از فهرست زیر انتخاب کن.\n"
			. "فهرست مقصدهای مجاز (فقط از همین‌ها):\n" . implode( "\n", $list ) . "\n\n"
			. 'برای هر لینک: url دقیقاً از فهرست، anchor طبیعی و فارسی، reason کوتاه. اگر لینک مناسبی نیست آرایه خالی بده.';

		$result = GSO_Gemini::json( $prompt, $schema, array( 'model_kind' => 'bulk' ) );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$allowed = wp_list_pluck( $candidates, 'url' );
		$links   = array();
		foreach ( (array) ( $result['links'] ?? array() ) as $link ) {
			$url = esc_url_raw( (string) ( $link['url'] ?? '' ) );
			if ( ! in_array( $url, $allowed, true ) ) {
				continue;
			}
			$links[] = array(
				'url'    => $url,
				'anchor' => sanitize_text_field( (string) ( $link['anchor'] ?? '' ) ),
				'reason' => sanitize_text_field( (string) ( $link['reason'] ?? '' ) ),
			);
		}

		$html = '';
		foreach ( $links as $link ) {
			$html .= '<li><a href="' . esc_url( $link['url'] ) . '">' . esc_html( $link['anchor'] ) . '</a> — ' . esc_html( $link['reason'] ) . '</li>';
		}
		if ( '' !== $html ) {
			$html = '<ul class="gso-internal-links">' . $html . '</ul>';
		}

		return array( 'links' => $links, 'html' => $html );
	}

	/**
	 * تولید CSS از دستور اپراتور.
	 *
	 * @param string $instruction دستور.
	 * @param string $existing    CSS فعلی.
	 * @return string|WP_Error
	 */
	public static function css_from_instruction( string $instruction, string $existing = '' ) {
		$instruction = sanitize_textarea_field( $instruction );
		if ( '' === $instruction ) {
			return new WP_Error( 'gso_css', 'دستور CSS خالی است.' );
		}

		$prompt = "فقط کد CSS معتبر و کوتاه برای وردپرس بنویس (بدون توضیح، بدون ```).\n"
			. 'دستور: ' . $instruction . "\n"
			. "CSS فعلی (در صورت نیاز تکمیل/اصلاح کن):\n" . mb_substr( $existing, 0, 4000 ) . "\n"
			. 'قواعد: بدون @import، بدون url( خارجی، بدون expression، بدون تگ style، فقط سلکتورهای امن.';

		$text = GSO_Gemini::text( $prompt, array( 'model_kind' => 'deep', 'max_tokens' => 1200, 'temperature' => 0.2 ) );
		if ( is_wp_error( $text ) ) {
			return $text;
		}
		return self::sanitize_css( (string) $text );
	}

	/**
	 * پاک‌سازی CSS.
	 *
	 * @param string $css کد.
	 * @return string
	 */
	/**
	 * حروف کوچک، با یا بدون mbstring (وردپرس فقط mb_substr/mb_strlen را جایگزین می‌کند).
	 *
	 * @param string $text متن.
	 * @return string
	 */
	private static function lower( string $text ): string {
		return function_exists( 'mb_strtolower' ) ? mb_strtolower( $text ) : strtolower( $text );
	}

	public static function sanitize_css( string $css ): string {
		$css = preg_replace( '/^```(?:css)?\s*|\s*```$/i', '', trim( $css ) );
		$css = preg_replace( '#</?style[^>]*>#i', '', (string) $css );
		$css = preg_replace( '#<\?php.*?\?>#s', '', (string) $css );
		$css = preg_replace( '/<\/?script[^>]*>/i', '', (string) $css );
		$css = preg_replace( '/expression\s*\(/i', '', (string) $css );
		$css = preg_replace( '/@import[^;]+;/i', '', (string) $css );
		return trim( (string) $css );
	}

	/**
	 * فهرست مقصدهای مجاز لینک داخلی.
	 *
	 * @param WP_Post $post مبدأ.
	 * @return array
	 */
	private static function link_candidates( WP_Post $post ): array {
		$taxes  = wp_get_post_terms( $post->ID, array( 'category', 'post_tag' ), array( 'fields' => 'ids' ) );
		$args   = array(
			'post_type'      => $post->post_type,
			'post_status'    => 'publish',
			'posts_per_page' => 20,
			'post__not_in'   => array( $post->ID ),
			'no_found_rows'  => true,
		);
		if ( ! is_wp_error( $taxes ) && ! empty( $taxes ) ) {
			$args['tax_query'] = array(
				array(
					'taxonomy' => 'category',
					'field'    => 'term_id',
					'terms'    => $taxes,
				),
			);
		}
		$query = new WP_Query( $args );
		$out   = array();
		foreach ( $query->posts as $candidate ) {
			$out[] = array(
				'title' => get_the_title( $candidate ),
				'url'   => get_permalink( $candidate ),
			);
		}
		if ( count( $out ) < 3 ) {
			$fallback = get_posts(
				array(
					'post_type'      => $post->post_type,
					'post_status'    => 'publish',
					'posts_per_page' => 10,
					'post__not_in'   => array( $post->ID ),
					'orderby'        => 'date',
				)
			);
			foreach ( $fallback as $candidate ) {
				$out[] = array(
					'title' => get_the_title( $candidate ),
					'url'   => get_permalink( $candidate ),
				);
			}
		}

		$unique = array();
		foreach ( $out as $item ) {
			$unique[ $item['url'] ] = $item;
		}
		return array_values( $unique );
	}

	/**
	 * آماده‌سازی HTML: پاک‌سازی، حذف H1، تبدیل اختیاری به بلوک گوتنبرگ.
	 *
	 * @param string $html خروجی مدل.
	 * @return string
	 */
	public static function prepare_html( string $html ): string {
		$html = preg_replace( '/^```(?:html)?\s*|\s*```$/i', '', trim( $html ) );
		$html = preg_replace( '#<(script|style|iframe|form|object|embed)\b.*?</\1>#is', '', (string) $html );
		$html = preg_replace( '/<h1\b[^>]*>(.*?)<\/h1>/is', '<h2>$1</h2>', (string) $html );
		$html = wp_kses_post( (string) $html );
		return trim( (string) $html );
	}

	/**
	 * اسکیمای تولید محتوا.
	 *
	 * @return array
	 */
	private static function content_schema(): array {
		return array(
			'type'       => 'OBJECT',
			'properties' => array(
				'title'            => array( 'type' => 'STRING' ),
				'content_html'     => array( 'type' => 'STRING' ),
				'excerpt'          => array( 'type' => 'STRING' ),
				'meta_title'       => array( 'type' => 'STRING' ),
				'meta_description' => array( 'type' => 'STRING' ),
				'focus_keyword'    => array( 'type' => 'STRING' ),
			),
			'required'   => array( 'title', 'content_html', 'excerpt', 'meta_title', 'meta_description' ),
		);
	}
}
