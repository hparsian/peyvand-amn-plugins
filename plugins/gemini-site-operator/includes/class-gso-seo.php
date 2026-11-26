<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * پل فیلدهای متای افزونه‌های سئو + خروجی متای اختصاصی.
 */
class GSO_SEO {

	const FALLBACK_TITLE = '_gso_meta_title';
	const FALLBACK_DESC  = '_gso_meta_description';
	const SCHEMA_META    = '_gso_schema';

	public static function init(): void {
		add_action( 'wp_head', array( __CLASS__, 'render_head' ), 2 );
		add_filter( 'document_title_parts', array( __CLASS__, 'filter_title' ), 20 );
	}

	/**
	 * افزونه سئوی فعال.
	 *
	 * @return string yoast|rankmath|aioseo|seopress|none
	 */
	public static function plugin(): string {
		if ( defined( 'WPSEO_VERSION' ) ) {
			return 'yoast';
		}
		if ( class_exists( 'RankMath' ) || defined( 'RANK_MATH_VERSION' ) ) {
			return 'rankmath';
		}
		if ( defined( 'AIOSEO_VERSION' ) || function_exists( 'aioseo' ) ) {
			return 'aioseo';
		}
		if ( defined( 'SEOPRESS_VERSION' ) || function_exists( 'seopress_init' ) ) {
			return 'seopress';
		}
		return 'none';
	}

	/**
	 * نام نمایشی افزونه سئوی فعال.
	 *
	 * @return string
	 */
	public static function plugin_label(): string {
		$labels = array(
			'yoast'    => 'Yoast SEO',
			'rankmath' => 'Rank Math',
			'aioseo'   => 'All in One SEO',
			'seopress' => 'SEOPress',
			'none'     => 'متای اختصاصی افزونه',
		);
		return $labels[ self::plugin() ] ?? 'متای اختصاصی افزونه';
	}

	/**
	 * کلیدهای متای عنوان.
	 *
	 * @return array
	 */
	public static function title_keys(): array {
		return array( '_yoast_wpseo_title', 'rank_math_title', '_aioseo_title', '_seopress_titles_title', self::FALLBACK_TITLE );
	}

	/**
	 * کلیدهای متای توضیحات.
	 *
	 * @return array
	 */
	public static function desc_keys(): array {
		return array( '_yoast_wpseo_metadesc', 'rank_math_description', '_aioseo_description', '_seopress_titles_desc', self::FALLBACK_DESC );
	}

	/**
	 * کلید متای افزونه فعال برای نوشتن.
	 *
	 * @param string $kind title|desc.
	 * @return string
	 */
	public static function write_key( string $kind ): string {
		$plugin = self::plugin();
		$map    = array(
			'title' => array(
				'yoast'    => '_yoast_wpseo_title',
				'rankmath' => 'rank_math_title',
				'aioseo'   => '_aioseo_title',
				'seopress' => '_seopress_titles_title',
				'none'     => self::FALLBACK_TITLE,
			),
			'desc'  => array(
				'yoast'    => '_yoast_wpseo_metadesc',
				'rankmath' => 'rank_math_description',
				'aioseo'   => '_aioseo_description',
				'seopress' => '_seopress_titles_desc',
				'none'     => self::FALLBACK_DESC,
			),
		);
		return $map[ $kind ][ $plugin ] ?? ( 'title' === $kind ? self::FALLBACK_TITLE : self::FALLBACK_DESC );
	}

	/**
	 * عنوان سئوی ذخیره‌شده (بدون گروها).
	 *
	 * @param int $post_id شناسه.
	 * @return string
	 */
	public static function stored_title( int $post_id ): string {
		foreach ( self::title_keys() as $key ) {
			$value = (string) get_post_meta( $post_id, $key, true );
			if ( '' !== trim( $value ) ) {
				return trim( wp_strip_all_tags( $value ) );
			}
		}
		return '';
	}

	/**
	 * توضیحات متای ذخیره‌شده (بدون گروها).
	 *
	 * @param int $post_id شناسه.
	 * @return string
	 */
	public static function stored_description( int $post_id ): string {
		foreach ( self::desc_keys() as $key ) {
			$value = (string) get_post_meta( $post_id, $key, true );
			if ( '' !== trim( $value ) ) {
				return trim( wp_strip_all_tags( $value ) );
			}
		}
		return '';
	}

	/**
	 * عنوان سئوی مؤثر.
	 *
	 * @param int $post_id شناسه.
	 * @return string
	 */
	public static function get_title( int $post_id ): string {
		foreach ( self::title_keys() as $key ) {
			$value = (string) get_post_meta( $post_id, $key, true );
			if ( '' !== trim( $value ) ) {
				return trim( wp_strip_all_tags( $value ) );
			}
		}
		if ( 'none' !== self::plugin() ) {
			return trim( (string) get_the_title( $post_id ) );
		}
		return '';
	}

	/**
	 * توضیحات متای مؤثر.
	 *
	 * @param int $post_id شناسه.
	 * @return string
	 */
	public static function get_description( int $post_id ): string {
		foreach ( self::desc_keys() as $key ) {
			$value = (string) get_post_meta( $post_id, $key, true );
			if ( '' !== trim( $value ) ) {
				return trim( wp_strip_all_tags( $value ) );
			}
		}
		return '';
	}

	/**
	 * نوشتن عنوان سئو در کلید افزونه فعال (و کلید اختصاصی).
	 *
	 * @param int    $post_id شناسه.
	 * @param string $value   مقدار.
	 */
	public static function set_title( int $post_id, string $value ): void {
		$value = trim( sanitize_text_field( $value ) );
		update_post_meta( $post_id, self::write_key( 'title' ), $value );
		update_post_meta( $post_id, self::FALLBACK_TITLE, $value );
	}

	/**
	 * نوشتن توضیحات متا.
	 *
	 * @param int    $post_id شناسه.
	 * @param string $value   مقدار.
	 */
	public static function set_description( int $post_id, string $value ): void {
		$value = trim( sanitize_textarea_field( $value ) );
		update_post_meta( $post_id, self::write_key( 'desc' ), $value );
		update_post_meta( $post_id, self::FALLBACK_DESC, $value );
	}

	/**
	 * آیا صفحه اسکیمای JSON-LD دارد؟
	 *
	 * @param int $post_id شناسه.
	 * @return bool
	 */
	public static function has_schema( int $post_id ): bool {
		if ( '' !== (string) get_post_meta( $post_id, self::SCHEMA_META, true ) ) {
			return true;
		}
		$content = (string) get_post_field( 'post_content', $post_id );
		if ( false !== stripos( $content, 'application/ld+json' ) ) {
			return true;
		}
		$plugin = self::plugin();
		// Yoast، Rank Math و AIOSEO به‌صورت پیش‌فرض اسکیمای پایه را چاپ می‌کنند؛
		// نبود متای اختصاصی به معنی نبود اسکیما نیست.
		return 'none' !== $plugin;
	}

	/**
	 * خروجی متای جایگزین، اسکیما و CSS سفارشی.
	 */
	public static function render_head(): void {
		if ( is_singular() ) {
			$post_id = (int) get_queried_object_id();
			if ( $post_id > 0 ) {
				if ( 'none' === self::plugin() ) {
					$desc = (string) get_post_meta( $post_id, self::FALLBACK_DESC, true );
					if ( '' !== $desc ) {
						printf( '<meta name="description" content="%s" />' . "\n", esc_attr( $desc ) );
					}
				}

				$schema = (string) get_post_meta( $post_id, self::SCHEMA_META, true );
				if ( '' !== $schema ) {
					$decoded = json_decode( $schema, true );
					if ( is_array( $decoded ) ) {
						echo '<script type="application/ld+json">' . wp_json_encode( $decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . '</script>' . "\n";
					}
				}
			}
		}

		if ( GSO_Settings::get( 'css_snippets' ) ) {
			$css = (string) get_option( 'gso_custom_css', '' );
			if ( '' !== trim( $css ) ) {
				echo '<style id="gso-custom-css">' . "\n" . $css . "\n" . '</style>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput -- CSS در زمان ذخیره پاک\u200cسازی شده است.
			}
		}
	}

	/**
	 * جایگزینی عنوان سند وقتی افزونه سئو نیست.
	 *
	 * @param array $parts بخش‌های عنوان.
	 * @return array
	 */
	public static function filter_title( array $parts ): array {
		if ( is_singular() && 'none' === self::plugin() ) {
			$post_id = (int) get_queried_object_id();
			$title   = (string) get_post_meta( $post_id, self::FALLBACK_TITLE, true );
			if ( '' !== trim( $title ) ) {
				$parts['title'] = $title;
			}
		}
		return $parts;
	}

	/**
	 * طول مؤثر عنوان (بدون تگ).
	 *
	 * @param string $text متن.
	 * @return int
	 */
	public static function length( string $text ): int {
		return function_exists( 'mb_strlen' ) ? mb_strlen( trim( wp_strip_all_tags( $text ) ) ) : strlen( trim( wp_strip_all_tags( $text ) ) );
	}
}
