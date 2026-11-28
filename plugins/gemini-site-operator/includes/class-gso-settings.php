<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * تنظیمات افزونه و نگهداری امن کلید API.
 */
class GSO_Settings {

	const OPTION = 'gso_settings';

	/**
	 * پیش‌فرض‌های تنظیمات.
	 *
	 * @return array
	 */
	public static function defaults(): array {
		return array(
			'api_key'             => '',
			'api_base_url'        => '',
			'force_ipv4'          => false,
			'bulk_model'          => 'gemini-3.8-flash',
			'deep_model'          => 'gemini-4-hydrogen',
			'system_prompt'       => 'تو اپراتور ارشد سئو و کیفیت وب‌سایت هستی. همیشه فارسی، دقیق، کوتاه و قابل‌اجرا پاسخ بده. هیچ ادعای بی‌پشتوانه نساز و فقط بر اساس داده‌ای که به تو می‌دهند تصمیم بگیر.',
			'temperature'         => 0.4,
			'max_output_tokens'   => 4096,
			'request_timeout'     => 60,
			'rate_limit_per_min'  => 8,
			'daily_request_limit' => 400,
			'batch_size'          => 10,
			'max_requests_run'    => 10,
			'queue_batch_size'    => 3,
			'scan_time_budget'    => 20,
			'queue_time_budget'   => 25,
			'backoff_max_seconds' => 600,
			'auto_model_switch'   => true,
			'request_delay_ms'    => 1200,
			'automation_level'    => 'review_required',
			'scan_interval'       => 'daily',
			'post_types'          => array( 'post', 'page' ),
			'include_products'    => true,
			'enable_link_check'   => true,
			'link_check_limit'    => 12,
			'thin_content_words'  => 300,
			'min_meta_title'      => 30,
			'max_meta_title'      => 60,
			'min_meta_desc'       => 70,
			'max_meta_desc'       => 160,
			'woo_audit_enabled'   => true,
			'woo_short_desc_words' => 20,
			'woo_require_gallery' => false,
			'seo_duplicate_check' => true,
			'internal_link_min_words' => 300,
			'watch_on_save'       => true,
			'ai_review_enabled'   => false,
			'css_snippets'        => false,
			'delete_data_on_uninstall' => false,
		);
	}

	/**
	 * همه تنظیمات با پیش‌فرض‌ها.
	 *
	 * @return array
	 */
	public static function all(): array {
		$saved = get_option( self::OPTION, array() );
		if ( ! is_array( $saved ) ) {
			$saved = array();
		}
		return wp_parse_args( $saved, self::defaults() );
	}

	/**
	 * یک تنظیم.
	 *
	 * @param string $key     کلید.
	 * @param mixed  $default مقدار پیش‌فرض.
	 * @return mixed
	 */
	public static function get( string $key, $default = null ) {
		$all = self::all();
		if ( array_key_exists( $key, $all ) ) {
			return $all[ $key ];
		}
		return $default;
	}

	/**
	 * ذخیره اتمیک چند مقدار (بدون عبور از فیلتر کامل فرم).
	 *
	 * @param array $values مقادیر.
	 * @return array
	 */
	public static function update( array $values ): array {
		$all = array_merge( self::all(), $values );
		update_option( self::OPTION, $all, false );
		return $all;
	}

	/**
	 * پاک‌سازی ورودی فرم تنظیمات (فراخوانی‌شده توسط Settings API).
	 *
	 * @param mixed $input ورودی خام.
	 * @return array
	 */
	public static function sanitize( $input ): array {
		$existing = self::all();
		$input    = is_array( $input ) ? $input : array();
		$out      = $existing;

		// کلید API: خالی یعنی «بدون تغییر» تا ذخیره‌ی بقیه‌ی فیلدها کلید را پاک نکند.
		if ( ! empty( $input['clear_api_key'] ) ) {
			$out['api_key'] = '';
		} elseif ( isset( $input['api_key'] ) ) {
			$raw = trim( (string) $input['api_key'], " \t\n\r\0\x0B\"'" );
			if ( '' !== $raw && false === strpos( $raw, '•' ) ) {
				$out['api_key'] = self::encrypt( sanitize_text_field( $raw ) );
			}
		}

		if ( isset( $input['api_base_url'] ) ) {
			$base = trim( (string) $input['api_base_url'] );
			if ( '' === $base ) {
				$out['api_base_url'] = '';
			} else {
				$validated = self::validate_api_base_url( $base );
				$out['api_base_url'] = $validated;
			}
		}
		$out['force_ipv4'] = ! empty( $input['force_ipv4'] );

		$out['bulk_model'] = self::normalize_model( (string) ( $input['bulk_model'] ?? $existing['bulk_model'] ), 'bulk' );
		$out['deep_model'] = self::normalize_model( (string) ( $input['deep_model'] ?? $existing['deep_model'] ), 'deep' );

		if ( isset( $input['system_prompt'] ) ) {
			$out['system_prompt'] = sanitize_textarea_field( (string) $input['system_prompt'] );
		}

		$out['temperature']       = min( 1.0, max( 0.0, (float) ( $input['temperature'] ?? $existing['temperature'] ) ) );
		$out['max_output_tokens'] = min( 8192, max( 256, absint( $input['max_output_tokens'] ?? $existing['max_output_tokens'] ) ) );
		$out['request_timeout']   = min( 120, max( 20, absint( $input['request_timeout'] ?? $existing['request_timeout'] ) ) );
		$out['rate_limit_per_min'] = min( 60, max( 1, absint( $input['rate_limit_per_min'] ?? $existing['rate_limit_per_min'] ) ) );
		$out['daily_request_limit'] = min( 100000, max( 0, absint( $input['daily_request_limit'] ?? $existing['daily_request_limit'] ) ) );
		$out['batch_size']        = min( 50, max( 1, absint( $input['batch_size'] ?? $existing['batch_size'] ) ) );
		$out['max_requests_run']  = min( 50, max( 1, absint( $input['max_requests_run'] ?? $existing['max_requests_run'] ) ) );
		$out['queue_batch_size']  = min( 10, max( 1, absint( $input['queue_batch_size'] ?? $existing['queue_batch_size'] ) ) );
		$out['scan_time_budget']  = min( 120, max( 5, absint( $input['scan_time_budget'] ?? $existing['scan_time_budget'] ) ) );
		$out['queue_time_budget'] = min( 240, max( 5, absint( $input['queue_time_budget'] ?? $existing['queue_time_budget'] ) ) );
		$out['backoff_max_seconds'] = min( 3600, max( 30, absint( $input['backoff_max_seconds'] ?? $existing['backoff_max_seconds'] ) ) );
		$out['auto_model_switch'] = ! empty( $input['auto_model_switch'] );
		$out['request_delay_ms']  = min( 10000, max( 0, absint( $input['request_delay_ms'] ?? $existing['request_delay_ms'] ) ) );

		$out['automation_level'] = in_array( $input['automation_level'] ?? '', array( 'report_only', 'safe_auto', 'review_required' ), true )
			? $input['automation_level']
			: $existing['automation_level'];

		$out['scan_interval'] = in_array( $input['scan_interval'] ?? '', array( 'off', 'hourly', 'daily', 'weekly' ), true )
			? $input['scan_interval']
			: $existing['scan_interval'];

		if ( isset( $input['post_types'] ) && is_array( $input['post_types'] ) ) {
			$allowed = array_keys( get_post_types( array( 'public' => true ) ) );
			$types   = array_values( array_intersect( array_map( 'sanitize_key', $input['post_types'] ), $allowed ) );
			if ( ! empty( $types ) ) {
				$out['post_types'] = $types;
			}
		}

		$out['include_products']  = ! empty( $input['include_products'] );
		$out['enable_link_check'] = ! empty( $input['enable_link_check'] );
		$out['watch_on_save']     = ! empty( $input['watch_on_save'] );
		$out['ai_review_enabled'] = ! empty( $input['ai_review_enabled'] );
		$out['css_snippets']      = ! empty( $input['css_snippets'] );
		$out['delete_data_on_uninstall'] = ! empty( $input['delete_data_on_uninstall'] );

		$out['woo_audit_enabled']  = ! empty( $input['woo_audit_enabled'] );
		$out['woo_require_gallery'] = ! empty( $input['woo_require_gallery'] );
		$out['seo_duplicate_check'] = ! empty( $input['seo_duplicate_check'] );
		$out['woo_short_desc_words'] = min( 120, max( 5, absint( $input['woo_short_desc_words'] ?? $existing['woo_short_desc_words'] ) ) );
		$out['internal_link_min_words'] = min( 3000, max( 50, absint( $input['internal_link_min_words'] ?? $existing['internal_link_min_words'] ) ) );
		$out['link_check_limit']  = min( 50, max( 1, absint( $input['link_check_limit'] ?? $existing['link_check_limit'] ) ) );
		$out['thin_content_words'] = min( 3000, max( 50, absint( $input['thin_content_words'] ?? $existing['thin_content_words'] ) ) );
		$out['min_meta_title']    = min( 100, max( 10, absint( $input['min_meta_title'] ?? $existing['min_meta_title'] ) ) );
		$out['max_meta_title']    = min( 120, max( 20, absint( $input['max_meta_title'] ?? $existing['max_meta_title'] ) ) );
		$out['min_meta_desc']     = min( 200, max( 30, absint( $input['min_meta_desc'] ?? $existing['min_meta_desc'] ) ) );
		$out['max_meta_desc']     = min( 320, max( 60, absint( $input['max_meta_desc'] ?? $existing['max_meta_desc'] ) ) );

		return $out;
	}

	/**
	 * نرمال‌سازی نام مدل (حذف پیشوند models/ و نویسه‌های غیرمجاز).
	 *
	 * @param string $model نام خام مدل.
	 * @param string $kind  bulk|deep.
	 * @return string
	 */
	public static function normalize_model( string $model, string $kind = 'bulk' ): string {
		$model = trim( $model );
		$model = (string) preg_replace( '#^models/#i', '', $model );
		$model = (string) preg_replace( '/[^a-zA-Z0-9._\-]/', '', $model );
		if ( '' === $model ) {
			return 'deep' === $kind ? 'gemini-4-hydrogen' : 'gemini-3.8-flash';
		}
		return $model;
	}

	/**
	 * مدل انتخابی برای نوع کار.
	 *
	 * @param string $kind bulk|deep.
	 * @return string
	 */
	public static function model( string $kind = 'bulk' ): string {
		$model = 'deep' === $kind ? self::get( 'deep_model' ) : self::get( 'bulk_model' );
		return self::normalize_model( (string) $model, $kind );
	}

	public static function validate_api_base_url( string $base ): string {
		$clean = esc_url_raw( trim( $base ), array( 'https' ) );
		if ( '' === $clean ) {
			return '';
		}
		$clean = preg_replace( '#/v1(?:beta|alpha)?(?:/models.*)?$#i', '', rtrim( (string) $clean, '/' ) );
		$clean = rtrim( (string) $clean, '/' );
		if ( '' === $clean ) {
			return '';
		}
		$parts = wp_parse_url( $clean );
		if ( ! is_array( $parts ) || empty( $parts['host'] ) || strtolower( (string) ( $parts['scheme'] ?? '' ) ) !== 'https' ) {
			return '';
		}
		$host = strtolower( trim( (string) $parts['host'], '[]' ) );
		if ( in_array( $host, array( 'localhost', 'ip6-localhost' ), true ) || substr( $host, -6 ) === '.local' || substr( $host, -9 ) === '.internal' ) {
			return '';
		}
		if ( filter_var( $host, FILTER_VALIDATE_IP ) ) {
			if ( false === filter_var( $host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
				return '';
			}
		} elseif ( function_exists( 'wp_http_validate_url' ) && ! wp_http_validate_url( $clean ) ) {
			return '';
		} else {
			$resolved = gethostbyname( $host );
			if ( filter_var( $resolved, FILTER_VALIDATE_IP ) && false === filter_var( $resolved, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
				return '';
			}
		}
		return $clean;
	}

	/**
	 * آدرس پایه API (رسمی یا پروکسی/Worker اختصاصی).
	 *
	 * @return string
	 */
	public static function api_base_url(): string {
		$base = trim( (string) self::get( 'api_base_url', '' ) );
		if ( '' === $base ) {
			return 'https://generativelanguage.googleapis.com';
		}
		$clean = self::validate_api_base_url( $base );
		return '' !== $clean ? $clean : 'https://generativelanguage.googleapis.com';
	}

	/**
	 * کلید API رمزگشایی‌شده.
	 *
	 * @return string
	 */
	public static function api_key(): string {
		$stored = (string) self::get( 'api_key', '' );
		return '' !== $stored ? self::decrypt( $stored ) : '';
	}

	/**
	 * نمایش ماسک‌شده کلید برای فرم.
	 *
	 * @return string
	 */
	public static function masked_key(): string {
		$key = self::api_key();
		if ( '' === $key ) {
			return '';
		}
		return str_repeat( '•', 8 ) . substr( $key, -4 );
	}

	/**
	 * رمزنگاری متقارن با کلید مشتق‌شده از salt وردپرس.
	 *
	 * @param string $plain متن خام.
	 * @return string
	 */
	public static function encrypt( string $plain ): string {
		if ( '' === $plain ) {
			return '';
		}
		if ( class_exists( 'Pas_Core_Crypto' ) && Pas_Core_Crypto::is_configured() ) {
			return Pas_Core_Crypto::wrap_encrypted( $plain );
		}
		if ( ! function_exists( 'openssl_encrypt' ) ) {
			return 'plain:' . $plain;
		}
		$enc_key = hash( 'sha256', wp_salt( 'auth' ) . '|gso-enc', true );
		$mac_key = hash( 'sha256', wp_salt( 'auth' ) . '|gso-mac', true );
		$iv      = random_bytes( 16 );
		$enc     = openssl_encrypt( $plain, 'aes-256-cbc', $enc_key, OPENSSL_RAW_DATA, $iv );
		if ( false === $enc ) {
			return '';
		}
		$mac = hash_hmac( 'sha256', $iv . $enc, $mac_key, true );
		return 'gso2:' . base64_encode( $mac . $iv . $enc );
	}

	/**
	 * رمزگشایی کلید ذخیره‌شده.
	 *
	 * @param string $stored مقدار ذخیره‌شده.
	 * @return string
	 */
	public static function decrypt( string $stored ): string {
		if ( '' === $stored ) {
			return '';
		}
		if ( class_exists( 'Pas_Core_Crypto' ) && 0 === strpos( $stored, Pas_Core_Crypto::PREFIX ) ) {
			return Pas_Core_Crypto::unwrap_decrypt( $stored );
		}
		if ( 0 === strpos( $stored, 'plain:' ) ) {
			return substr( $stored, 6 );
		}
		if ( 0 === strpos( $stored, 'gso2:' ) ) {
			if ( ! function_exists( 'openssl_decrypt' ) ) {
				return '';
			}
			$raw = base64_decode( substr( $stored, 5 ), true );
			if ( false === $raw || strlen( $raw ) < 64 ) {
				return '';
			}
			$mac     = substr( $raw, 0, 32 );
			$payload = substr( $raw, 32 );
			$enc_key = hash( 'sha256', wp_salt( 'auth' ) . '|gso-enc', true );
			$mac_key = hash( 'sha256', wp_salt( 'auth' ) . '|gso-mac', true );
			$calc    = hash_hmac( 'sha256', $payload, $mac_key, true );
			if ( ! hash_equals( $mac, $calc ) ) {
				return '';
			}
			$iv  = substr( $payload, 0, 16 );
			$enc = substr( $payload, 16 );
			$out = openssl_decrypt( $enc, 'aes-256-cbc', $enc_key, OPENSSL_RAW_DATA, $iv );
			return false === $out ? '' : $out;
		}
		if ( 0 !== strpos( $stored, 'gso1:' ) ) {
			return $stored;
		}
		if ( ! function_exists( 'openssl_decrypt' ) ) {
			return '';
		}
		$raw = base64_decode( substr( $stored, 5 ), true );
		if ( false === $raw || strlen( $raw ) < 32 ) {
			return '';
		}
		$key = hash( 'sha256', wp_salt( 'auth' ) . '|gso', true );
		$iv  = substr( $raw, 0, 16 );
		$enc = substr( $raw, 16 );
		$out = openssl_decrypt( $enc, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv );
		return false === $out ? '' : $out;
	}

	/**
	 * آیا ووکامرس فعال است؟
	 *
	 * @return bool
	 */
	public static function has_woocommerce(): bool {
		return class_exists( 'WooCommerce' ) && post_type_exists( 'product' );
	}

	/**
	 * انواع محتوای تحت پایش.
	 *
	 * @return array
	 */
	public static function watched_post_types(): array {
		$types = (array) self::get( 'post_types', array( 'post', 'page' ) );
		if ( self::has_woocommerce() && self::get( 'include_products' ) ) {
			$types[] = 'product';
		}
		return array_values( array_unique( array_filter( array_map( 'sanitize_key', $types ) ) ) );
	}
}
