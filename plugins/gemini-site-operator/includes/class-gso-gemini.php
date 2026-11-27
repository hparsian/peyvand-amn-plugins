<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * کلاینت Gemini API — همه‌ی فراخوانی‌ها از سمت سرور با wp_remote_post.
 *
 * نکات پایدار:
 * - کنترل نرخ و سهمیه از طریق GSO_Limiter (مشترک با صف و کران).
 * - timeout کنترل‌شده و بودجه زمانی برای جلوگیری از Timeout هاست.
 * - سوئیچ خودکار مدل در خطاهای 429/404/503.
 */
class GSO_Gemini {

	const ENDPOINT = 'https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent';

	/**
	 * درخواست متنی ساده.
	 *
	 * @param string $prompt دستور.
	 * @param array  $args   temperature, max_tokens, model_kind, system, deadline.
	 * @return string|WP_Error
	 */
	public static function text( string $prompt, array $args = array() ) {
		$result = self::request( $prompt, $args, false );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return trim( (string) ( $result['text'] ?? '' ) );
	}

	/**
	 * درخواست با خروجی JSON ساختاریافته.
	 *
	 * با پرچم contract، متن قرارداد به پرامپت اضافه و پاسخ به پاکت استاندارد
	 * (GSO_Contract) تبدیل می‌شود. اگر JSON نامعتبر یا ناقص برگردد، یک بار
	 * درخواست ترمیمی با دمای پایین‌تر فرستاده می‌شود و اگر باز هم نامعتبر بود،
	 * خطای gso_json_failed با دلیل ثبت می‌گردد تا صف آن را failed علامت بزند.
	 *
	 * @param string $prompt دستور.
	 * @param array  $schema اسکیمای پاسخ (OpenAPI subset).
	 * @param array  $args   تنظیمات؛ contract، mode و object_type.
	 * @return array|WP_Error آرایه decode‌شده.
	 */
	public static function json( string $prompt, array $schema, array $args = array() ) {
		$contract = ! empty( $args['contract'] );
		$mode     = (string) ( $args['mode'] ?? 'audit' );
		$type     = (string) ( $args['object_type'] ?? 'post' );

		if ( $contract ) {
			$prompt .= "\n\n" . GSO_Contract::instruction( $mode, $type );
		}

		unset( $args['contract'], $args['mode'], $args['object_type'] );
		$args['schema'] = $schema;

		$result = self::request( $prompt, $args, true );

		if ( is_wp_error( $result ) && 'gso_json' === $result->get_error_code() ) {
			$retry                = $args;
			$retry['temperature'] = 0.1;
			$retry['max_tokens']  = min( 8192, (int) ( $args['max_tokens'] ?? GSO_Settings::get( 'max_output_tokens', 4096 ) ) + 1024 );

			$repaired = self::request(
				$prompt . "\n\nپاسخ قبلی JSON معتبر و کامل نبود. همان گزارش را از نو بنویس و فقط یک شیء JSON کامل برگردان؛ بدون متن اضافه و بدون بلوک کد.",
				$retry,
				true
			);

			if ( is_wp_error( $repaired ) ) {
				GSO_Logger::log(
					'error',
					'gemini.json_failed',
					sprintf( 'پاسخ JSON حتی پس از تلاش ترمیمی نامعتبر ماند: %s', $repaired->get_error_message() )
				);
				return new WP_Error(
					'gso_json_failed',
					'پاسخ Gemini پس از یک تلاش ترمیمی هم JSON معتبر نبود.',
					array( 'reason' => $repaired->get_error_message() )
				);
			}

			GSO_Logger::log( 'warning', 'gemini.json_repaired', 'پاسخ JSON نامعتبر بود و در تلاش ترمیمی اصلاح شد.' );
			$result = $repaired;
		}

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$data = $result['data'] ?? null;
		if ( ! is_array( $data ) ) {
			return new WP_Error( 'gso_json', 'پاسخ JSON معتبر نبود.' );
		}

		if ( $contract ) {
			$data = GSO_Contract::normalize( $data, $mode, array( 'object_type' => $type ) );
		}

		return $data;
	}

	/**
	 * فراخوانی اصلی با محدودیت نرخ، Backoff و سوئیچ مدل.
	 *
	 * @param string $prompt   دستور کاربر.
	 * @param array  $args     آرگومان‌ها.
	 * @param bool   $want_json آیا JSON لازم است.
	 * @return array|WP_Error
	 */
	public static function request( string $prompt, array $args = array(), bool $want_json = false ) {
		$key = GSO_Settings::api_key();
		if ( '' === $key ) {
			return new WP_Error( 'gso_no_key', 'کلید Gemini API تنظیم نشده است.' );
		}

		$deadline = isset( $args['deadline'] ) ? (int) $args['deadline'] : 0;
		if ( $deadline > 0 && time() >= $deadline ) {
			return new WP_Error(
				'gso_time_budget',
				'بودجه زمانی این دسته تمام شد؛ درخواست به دسته بعدی سپرده شد.',
				array( 'retryable' => true, 'deferred' => true )
			);
		}

		$check = GSO_Limiter::check( ! empty( $args['block'] ), $deadline );
		if ( is_wp_error( $check ) ) {
			return $check;
		}

		$system = (string) ( $args['system'] ?? GSO_Settings::get( 'system_prompt', '' ) );
		$body   = array(
			'contents'         => array(
				array(
					'role'  => 'user',
					'parts' => array( array( 'text' => $prompt ) ),
				),
			),
			'generationConfig' => array(
				'temperature'     => (float) ( $args['temperature'] ?? GSO_Settings::get( 'temperature', 0.4 ) ),
				'maxOutputTokens' => (int) ( $args['max_tokens'] ?? GSO_Settings::get( 'max_output_tokens', 4096 ) ),
				'candidateCount'  => 1,
			),
		);

		if ( '' !== trim( $system ) ) {
			$body['systemInstruction'] = array( 'parts' => array( array( 'text' => $system ) ) );
		}

		if ( $want_json ) {
			$body['generationConfig']['responseMimeType'] = 'application/json';
			if ( ! empty( $args['schema'] ) && is_array( $args['schema'] ) ) {
				$body['generationConfig']['responseSchema'] = $args['schema'];
			}
		}

		$kind     = (string) ( $args['model_kind'] ?? 'bulk' );
		$fallback = 'deep' === $kind ? 'bulk' : 'deep';
		$tried    = array();
		$last     = null;

		for ( $attempt = 0; $attempt < 2; $attempt++ ) {
			$model = self::model_for( $kind, $args );
			if ( '' === $model || in_array( $model, $tried, true ) ) {
				break;
			}
			$tried[] = $model;

			$result = self::dispatch( $model, self::with_thinking( $body, $model, $kind, $args ), $key, $deadline );

			if ( ! is_wp_error( $result ) ) {
				GSO_Limiter::register_success();
				return self::format_result( $result, $model, $want_json );
			}

			$last = $result;
			$data = $result->get_error_data();
			$data = is_array( $data ) ? $data : array();
			$code = (int) ( $data['status'] ?? 0 );

			// سوئیچ خودکار مدل فقط وقتی مدل در دسترس یا پشتیبانی نمی‌شود؛ خطاهای نرخ با Backoff مدیریت می‌شوند.
			$switchable = 404 === $code
				|| ( 400 === $code && (bool) preg_match( '/model|not found|not supported|unsupported/i', $result->get_error_message() ) );
			if ( ! $switchable || empty( GSO_Settings::get( 'auto_model_switch', true ) ) ) {
				break;
			}
			if ( 'gso_quota' === $result->get_error_code() ) {
				break;
			}
			$kind = $fallback;
		}

		return $last instanceof WP_Error ? $last : new WP_Error( 'gso_api_error', 'تماس با Gemini ناموفق بود.' );
	}

	/**
	 * مدل مناسب برای نوع کار.
	 *
	 * @param string $kind bulk|deep.
	 * @param array  $args آرگومان‌ها (model صریح).
	 * @return string
	 */
	private static function model_for( string $kind, array $args ): string {
		$model = (string) ( $args['model'] ?? GSO_Settings::model( $kind ) );
		return (string) preg_replace( '/[^a-zA-Z0-9._\-]/', '', $model );
	}

	/**
	 * افزودن «بودجهٔ تفکر» مناسب مدل به بدنهٔ درخواست.
	 *
	 * مدل‌های Gemini ۲.۵ به بعد پیش از پاسخ «فکر» می‌کنند و توکن‌های تفکر از همان
	 * maxOutputTokens کم می‌شود؛ با بودجهٔ کوچک (مثل تست اتصال یا JSON کوتاه) متن
	 * خروجی خالی برمی‌گردد و کاربر خطا می‌بیند. برای مدل‌های flash/lite تفکر را
	 * خاموش می‌کنیم و مدل‌های pro (که بودجهٔ صفر را نمی‌پذیرند) دست‌نخورده می‌مانند.
	 *
	 * @param array  $body  بدنهٔ درخواست.
	 * @param string $model نام مدل.
	 * @param string $kind  bulk|deep.
	 * @param array  $args  آرگومان‌ها (thinking_budget صریح).
	 * @return array
	 */
	private static function with_thinking( array $body, string $model, string $kind, array $args ): array {
		$explicit = $args['thinking_budget'] ?? null;
		if ( null === $explicit && ! preg_match( '/gemini-(?:(?:2\.[5-9])|[3-9])/i', $model ) ) {
			// thinkingConfig روی مدل‌های قدیمی‌تر پشتیبانی نمی‌شود.
			return $body;
		}

		if ( preg_match( '/pro/i', $model ) ) {
			if ( null === $explicit ) {
				return $body;
			}
			$budget = max( 128, (int) $explicit );
		} else {
			$budget = null === $explicit ? ( 'deep' === $kind ? 1024 : 0 ) : max( 0, (int) $explicit );
		}

		$body['generationConfig']['thinkingConfig'] = array( 'thinkingBudget' => $budget );
		return $body;
	}

	/**
	 * ارسال یک درخواست به مدل مشخص.
	 *
	 * @param string $model    نام مدل.
	 * @param array  $body     بدنه.
	 * @param string $key      کلید.
	 * @param int    $deadline زمان پایان بودجه.
	 * @return array|WP_Error آرایه code/raw/json.
	 */
	private static function dispatch( string $model, array $body, string $key, int $deadline ) {
		if ( '' === $model ) {
			return new WP_Error( 'gso_no_model', 'مدل Gemini معتبر نیست.' );
		}

		// timeout کنترل‌شده: 45 تا 60 ثانیه به‌طور پیش‌فرض و هرگز بیشتر از بودجه دسته.
		$timeout = (int) GSO_Settings::get( 'request_timeout', 60 );
		$timeout = max( 15, min( 120, $timeout ) );
		if ( $deadline > 0 ) {
			$timeout = max( 5, min( $timeout, $deadline - time() ) );
		}

		$url  = sprintf( self::ENDPOINT, rawurlencode( $model ) );
		$http = wp_remote_post(
			$url,
			array(
				'timeout'     => $timeout,
				'redirection' => 0,
				'headers'     => array(
					'Content-Type'   => 'application/json',
					'x-goog-api-key' => $key,
				),
				'body'        => wp_json_encode( $body ),
			)
		);

		GSO_Limiter::note_request();

		if ( is_wp_error( $http ) ) {
			$delay = GSO_Limiter::register_failure( 0, $http->get_error_message() );
			GSO_Logger::log( 'error', 'gemini.transport', 'خطای شبکه در تماس با Gemini: ' . $http->get_error_message(), array( 'model' => $model, 'retry_after' => $delay ) );
			return new WP_Error(
				'gso_transport',
				'تماس با Gemini ناموفق بود: ' . $http->get_error_message(),
				array( 'retryable' => true, 'retry_after' => $delay )
			);
		}

		$code = (int) wp_remote_retrieve_response_code( $http );
		$raw  = (string) wp_remote_retrieve_body( $http );
		$json = json_decode( $raw, true );

		if ( $code < 200 || $code >= 300 ) {
			$message = is_array( $json ) ? (string) ( $json['error']['message'] ?? '' ) : '';
			if ( '' === $message ) {
				$message = wp_strip_all_tags( substr( $raw, 0, 300 ) );
			}

			$retryable = in_array( $code, array( 408, 429, 500, 502, 503, 504 ), true );
			$delay     = $retryable ? GSO_Limiter::register_failure( $code, $message ) : 0;
			$quota     = 429 === $code && GSO_Limiter::quota_until() > 0;

			GSO_Logger::log(
				'error',
				'gemini.http',
				sprintf( 'Gemini خطای %d برگرداند: %s', $code, $message ),
				array( 'model' => $model, 'retryable' => $retryable, 'retry_after' => $delay )
			);

			$error_code = $quota ? 'gso_quota' : ( $retryable ? 'gso_retry_later' : 'gso_api_error' );
			return new WP_Error(
				$error_code,
				sprintf( 'Gemini (%d): %s', $code, $message ),
				array(
					'status'      => $code,
					'retryable'   => $retryable,
					'retry_after' => $quota ? max( 1, GSO_Limiter::quota_until() - time() ) : $delay,
					'quota'       => $quota,
				)
			);
		}

		return array( 'code' => $code, 'json' => $json, 'raw' => $raw );
	}

	/**
	 * ساخت خروجی نهایی از پاسخ موفق.
	 *
	 * @param array  $response پاسخ.
	 * @param string $model    مدل.
	 * @param bool   $want_json آیا JSON لازم است.
	 * @return array|WP_Error
	 */
	private static function format_result( array $response, string $model, bool $want_json ) {
		$json = $response['json'];
		if ( ! is_array( $json ) ) {
			return new WP_Error( 'gso_bad_json', 'پاسخ Gemini قابل خواندن نبود.', array( 'retryable' => true ) );
		}

		if ( ! empty( $json['promptFeedback']['blockReason'] ) ) {
			return new WP_Error( 'gso_blocked', 'درخواست توسط فیلتر ایمنی Gemini مسدود شد: ' . $json['promptFeedback']['blockReason'] );
		}

		$text   = self::extract_text( $json );
		$finish = (string) ( $json['candidates'][0]['finishReason'] ?? '' );
		if ( '' === trim( $text ) && '' !== $finish ) {
			$message = 'MAX_TOKENS' === $finish
				? 'سقف توکن خروجی پیش از تولید متن تمام شد (MAX_TOKENS)؛ «حداکثر توکن خروجی» را بالا ببرید.'
				: sprintf( 'پاسخ Gemini خالی بود (%s).', $finish );
			return new WP_Error(
				'gso_empty_reply',
				$message,
				array( 'retryable' => 'MAX_TOKENS' !== $finish )
			);
		}
		$usage = array(
			'prompt' => (int) ( $json['usageMetadata']['promptTokenCount'] ?? 0 ),
			'output' => (int) ( $json['usageMetadata']['candidatesTokenCount'] ?? 0 ),
		);
		self::track_usage( $usage['output'] );

		$out = array(
			'text'  => $text,
			'model' => $model,
			'usage' => $usage,
			'data'  => null,
		);

		if ( $want_json ) {
			$data = self::decode_json( $text );
			if ( null === $data ) {
				return new WP_Error( 'gso_json', 'پاسخ Gemini JSON معتبر نبود.' );
			}
			$out['data'] = $data;
		}

		return $out;
	}

	/**
	 * استخراج متن از candidates.
	 *
	 * @param array $json پاسخ.
	 * @return string
	 */
	private static function extract_text( array $json ): string {
		$parts = $json['candidates'][0]['content']['parts'] ?? array();
		if ( ! is_array( $parts ) ) {
			return '';
		}
		$text = '';
		foreach ( $parts as $part ) {
			if ( isset( $part['text'] ) ) {
				$text .= (string) $part['text'];
			}
		}
		return $text;
	}

	/**
	 * decode مقاوم JSON (حذف ```json و متن اضافی).
	 *
	 * @param string $text متن.
	 * @return array|null
	 */
	private static function decode_json( string $text ): ?array {
		$text = trim( $text );
		$text = preg_replace( '/^```(?:json)?\s*/i', '', $text );
		$text = preg_replace( '/\s*```$/', '', (string) $text );

		$data = json_decode( (string) $text, true );
		if ( is_array( $data ) ) {
			return $data;
		}
		$start = strpos( (string) $text, '{' );
		$end   = strrpos( (string) $text, '}' );
		if ( false !== $start && false !== $end && $end > $start ) {
			$data = json_decode( substr( (string) $text, $start, $end - $start + 1 ), true );
			if ( is_array( $data ) ) {
				return $data;
			}
		}
		return null;
	}

	/**
	 * سازگاری عقب‌رو: بررسی محدودیت نرخ.
	 *
	 * @param bool $block صبر در حالت دستی.
	 * @return true|WP_Error
	 */
	public static function throttle( bool $block = false ) {
		return GSO_Limiter::check( $block );
	}

	/**
	 * شمارنده مصرف توکن (برای نمایش در داشبورد).
	 *
	 * @param int $tokens توکن خروجی.
	 */
	private static function track_usage( int $tokens ): void {
		$month = 'gso_usage_' . gmdate( 'Ym' );
		update_option( $month, (int) get_option( $month, 0 ) + max( 0, $tokens ), false );
	}

	/**
	 * مصرف ماه جاری.
	 *
	 * @return int
	 */
	public static function monthly_usage(): int {
		return (int) get_option( 'gso_usage_' . gmdate( 'Ym' ), 0 );
	}

	/**
	 * تست اتصال با یک درخواست کوچک.
	 *
	 * @return array|WP_Error
	 */
	public static function test_connection() {
		$model  = GSO_Settings::model( 'bulk' );
		$result = self::text(
			'فقط بنویس: اتصال برقرار است.',
			array(
				'model'           => $model,
				'model_kind'      => 'bulk',
				'max_tokens'      => 512,
				'temperature'     => 0,
				'thinking_budget' => 0,
				'block'           => true,
			)
		);
		if ( is_wp_error( $result ) ) {
			return self::friendly_error( $result, $model );
		}
		return array( 'message' => $result, 'model' => $model );
	}

	/**
	 * افزودن راهنمای عملی فارسی به خطاهای رایج اتصال/کلید.
	 *
	 * @param WP_Error $error خطا.
	 * @param string   $model مدل استفاده‌شده.
	 * @return WP_Error
	 */
	private static function friendly_error( WP_Error $error, string $model ): WP_Error {
		$data   = $error->get_error_data();
		$status = is_array( $data ) && isset( $data['status'] ) ? (int) $data['status'] : 0;
		$code   = (string) $error->get_error_code();
		$hint   = '';

		if ( 'gso_no_key' === $code ) {
			$hint = 'کلید ذخیره نشده است؛ فیلد کلید را پر کنید و «ذخیره تنظیمات» را بزنید.';
		} elseif ( 400 === $status || 401 === $status || 403 === $status ) {
			$hint = 'کلید یا دسترسی پذیرفته نشد؛ در Google AI Studio یک کلید تازه بسازید، بدون فاصله/نقل‌قول بچسبانید و مطمئن شوید API «Generative Language» برای همان پروژه فعال است.';
		} elseif ( 404 === $status ) {
			$hint = sprintf( 'مدل «%s» با این کلید در دسترس نیست؛ در تنظیمات نام مدل را به gemini-2.5-flash تغییر دهید.', $model );
		} elseif ( 429 === $status || 'gso_quota' === $code || 'gso_rate_limited' === $code ) {
			$hint = 'محدودیت نرخ/سهمیه فعال است؛ چند لحظه صبر کنید یا تنظیمات را دوباره ذخیره کنید تا وضعیت محدودیت پاک شود.';
		} elseif ( 'gso_empty_reply' === $code ) {
			$hint = '«حداکثر توکن خروجی» را در تنظیمات بالا ببرید.';
		} elseif ( 'gso_transport' === $code ) {
			$hint = 'اتصال خروجی سرور به generativelanguage.googleapis.com برقرار نشد؛ فایروال هاست، پروکسی یا گواهی SSL را بررسی کنید.';
		}

		if ( '' === $hint ) {
			return $error;
		}
		return new WP_Error( $code, $error->get_error_message() . ' — ' . $hint, $data );
	}
}
