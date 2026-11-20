<?php
/**
 * آداپتر یکپارچگی‌ها — وضعیت فقط‌خواندنی سرویس‌های اکوسیستم.
 *
 * هر سرویس با وجود کلاس/تابع عمومی خودش شناسایی می‌شود؛ نسخه از هدر افزونهٔ
 * نصب‌شده (یا ثابت نسخهٔ همان افزونه) خوانده می‌شود. هیچ کلید، رمز، توکن یا
 * متن پیامی نمایش داده نمی‌شود و هیچ کلاس یا جدول افزونهٔ دیگری تغییر نمی‌کند.
 */

defined( 'ABSPATH' ) || exit;

final class PAS_Dashboard_Adapter_Ecosystem {

	/** گروه‌های نمایشی سرویس‌ها. */
	public static function groups() {
		return array(
			'core'        => __( 'هسته و دادهٔ بالینی', 'peyvand-amn-dashboard' ),
			'channels'    => __( 'کانال‌های ارتباطی', 'peyvand-amn-dashboard' ),
			'extras'      => __( 'ابزارهای تکمیلی', 'peyvand-amn-dashboard' ),
		);
	}

	/**
	 * وضعیت همهٔ سرویس‌ها.
	 *
	 * @return array<int,array{id:string,label:string,group:string,required:bool,active:bool,version:string,status:string,detail:string,note:string}>
	 */
	public static function services() {
		$services = array();

		$core = class_exists( 'Pas_Core_Client_Registry' );
		$services[] = self::service(
			'core',
			__( 'هستهٔ مشترک', 'peyvand-amn-dashboard' ),
			'core',
			true,
			$core,
			self::version( 'pivand-amn-core', 'pivand-amn-core.php', 'PAS_CORE_VERSION' ),
			$core && ! PAS_Dashboard_Core_Bridge::crypto_ready()
				? array( 'warn', __( 'کلید رمزنگاری (PAS_CORE_ENCRYPTION_KEY) تنظیم نشده است؛ ثبت و نمایش یادداشت بالینی غیرفعال می‌شود.', 'peyvand-amn-dashboard' ) )
				: null,
			__( 'رجیستری هویت مراجعان، رمزنگاری و تقویم شمسی.', 'peyvand-amn-dashboard' )
		);

		$case = class_exists( 'Pas_CF_Case_File' );
		$services[] = self::service(
			'case_file',
			__( 'پرونده‌های بالینی', 'peyvand-amn-dashboard' ),
			'core',
			true,
			$case,
			self::version( 'pivand-amn-case-file', 'pivand-amn-case-file.php', 'PAS_CF_VERSION' ),
			null,
			__( 'پرونده، یادداشت جلسه، فرم ارزیابی و یادآور.', 'peyvand-amn-dashboard' )
		);

		$portal = class_exists( '\ZoojDarman\ClientPortal\Plugin' );
		$services[] = self::service(
			'portal',
			__( 'پنل مراجعان', 'peyvand-amn-dashboard' ),
			'core',
			false,
			$portal,
			self::version( 'pivand-amn-client-portal', 'pivand-amn-client-portal.php', 'ZDP_VERSION' ),
			$portal ? null : array( 'warn', __( 'نوبت‌های پنل، صندوق پیام، لیدها و نظرسنجی در داشبورد غیرفعال می‌شوند.', 'peyvand-amn-dashboard' ) ),
			__( 'نوبت‌دهی، صندوق پیام، فرم ارزیابی، لید و نظرسنجی.', 'peyvand-amn-dashboard' )
		);

		$quiz = PAS_Dashboard_Adapter_Quiz::available();
		$services[] = self::service(
			'quiz',
			__( 'آزمون‌های روان‌سنجی', 'peyvand-amn-dashboard' ),
			'core',
			false,
			$quiz,
			self::version( 'pivand-amn-quiz-enterprise', 'pivand-amn-quiz-enterprise.php', 'PSY_VERSION' ),
			$quiz ? null : array( 'warn', __( 'دعوت، نتیجه، روند و گزارش آزمون در داشبورد غیرفعال می‌شوند.', 'peyvand-amn-dashboard' ) ),
			__( 'دعوت‌نامه، نتیجه و روند آزمون.', 'peyvand-amn-dashboard' )
		);

		$services[] = self::sms_service();
		$services[] = self::smtp_service();

		$twofa = class_exists( 'Pas_2FA_Login_Gate' );
		$services[] = self::service(
			'2fa',
			__( 'احراز دومرحله‌ای', 'peyvand-amn-dashboard' ),
			'extras',
			false,
			$twofa,
			self::version( 'pivand-amn-2fa', 'pivand-amn-2fa.php', 'PAS_2FA_VERSION' ),
			$twofa ? null : array( 'warn', __( 'ورود پیشخوان فقط با رمز عبور انجام می‌شود.', 'peyvand-amn-dashboard' ) ),
			__( 'اختیاری برای هر کاربر؛ از پروفایل خودش فعال می‌شود.', 'peyvand-amn-dashboard' )
		);

		$content = class_exists( 'ACO_Admin' );
		$services[] = self::service(
			'content',
			__( 'تولید محتوا', 'peyvand-amn-dashboard' ),
			'extras',
			false,
			$content,
			self::version( 'pivand-amn-content-writer', 'pivand-amn-content-writer.php', 'ACO_VERSION' ),
			null,
			__( 'خارج از دامنهٔ بالینی؛ مصرف اعتبار سرویس هوش مصنوعی.', 'peyvand-amn-dashboard' )
		);

		$feed = class_exists( 'PWDF_Daily_Generator' );
		$services[] = self::service(
			'feed',
			__( 'فید محتوای روزانه', 'peyvand-amn-dashboard' ),
			'extras',
			false,
			$feed,
			self::version( 'piwand-daily-feed', 'piwand-daily-feed.php', 'PWDF_VERSION' ),
			null,
			__( 'ابزار محتوایی سایت، مستقل از دادهٔ بالینی.', 'peyvand-amn-dashboard' )
		);

		$contact = function_exists( 'payvand_get_theme_colors' );
		$services[] = self::service(
			'contact',
			__( 'فرم ارتباط سریع', 'peyvand-amn-dashboard' ),
			'extras',
			false,
			$contact,
			self::version( 'payvand-contact', 'payvand-contact.php', 'PAYVAND_VERSION' ),
			null,
			__( 'فرم تماس عمومی سایت و پیام‌های آن.', 'peyvand-amn-dashboard' )
		);

		return $services;
	}

	/** @return array{total:int,connected:int,attention:int,off:int} */
	public static function summary( array $services = array() ) {
		$services = $services ? $services : self::services();
		$summary  = array( 'total' => count( $services ), 'connected' => 0, 'attention' => 0, 'off' => 0 );

		foreach ( $services as $service ) {
			if ( 'ok' === $service['status'] ) {
				$summary['connected']++;
			} elseif ( 'warn' === $service['status'] ) {
				$summary['attention']++;
			} else {
				$summary['off']++;
			}
		}

		return $summary;
	}

	/**
	 * قراردادهای کلیدی بین‌افزونه‌ای — موجود یا غایب، بدون خواندن جدول.
	 *
	 * @return array<int,array{label:string,hook:string,ok:bool}>
	 */
	public static function contracts() {
		return array(
			array(
				'label' => __( 'ارسال پیامک', 'peyvand-amn-dashboard' ),
				'hook'  => 'pas_send_sms',
				'ok'    => PAS_Dashboard_Core_Bridge::sms_available(),
			),
			array(
				'label' => __( 'الگوهای تأییدشدهٔ پیامک', 'peyvand-amn-dashboard' ),
				'hook'  => 'pas_sms_patterns',
				'ok'    => PAS_Dashboard_Adapter_Sms::available(),
			),
			array(
				'label' => __( 'صف و تست ایمیل', 'peyvand-amn-dashboard' ),
				'hook'  => 'zsm_dashboard_queue',
				'ok'    => PAS_Dashboard_Adapter_Smtp::available(),
			),
			array(
				'label' => __( 'صندوق پیام مراجعان', 'peyvand-amn-dashboard' ),
				'hook'  => 'zdp_dashboard_messages',
				'ok'    => PAS_Dashboard_Adapter_Portal::messages_available(),
			),
			array(
				'label' => __( 'نوبت‌های پنل', 'peyvand-amn-dashboard' ),
				'hook'  => 'zdp_dashboard_bookings',
				'ok'    => PAS_Dashboard_Adapter_Portal::available(),
			),
			array(
				'label' => __( 'دعوت و نتیجهٔ آزمون', 'peyvand-amn-dashboard' ),
				'hook'  => 'psy_dashboard_invitations',
				'ok'    => PAS_Dashboard_Adapter_Quiz::available(),
			),
			array(
				'label' => __( 'لید و نظرسنجی', 'peyvand-amn-dashboard' ),
				'hook'  => 'zdp_dashboard_leads',
				'ok'    => PAS_Dashboard_Adapter_Followup::available(),
			),
		);
	}

	/** سرویس پیامک: اعتبارنامه و شمار الگوها بدون نمایش هیچ مقدار حساسی. */
	private static function sms_service() {
		$active = class_exists( 'Pas_Melipayamak' );
		$detail = __( 'ارسال با الگوهای تأییدشده از طریق ملی‌پیامک.', 'peyvand-amn-dashboard' );
		$status = null;

		if ( $active && method_exists( 'Pas_Melipayamak', 'credentials_source' ) ) {
			$source = (string) \Pas_Melipayamak::credentials_source();
			$labels = array(
				'constants' => __( 'اعتبارنامه از ثابت‌های wp-config.php', 'peyvand-amn-dashboard' ),
				'options'   => __( 'اعتبارنامه از تنظیمات پیشخوان', 'peyvand-amn-dashboard' ),
				'missing'   => __( 'اعتبارنامهٔ ملی‌پیامک تنظیم نشده است؛ ارسال پیامک شکست می‌خورد.', 'peyvand-amn-dashboard' ),
			);

			$detail = $labels[ $source ] ?? $detail;

			if ( 'missing' === $source ) {
				$status = array( 'warn', $detail );
			}

			if ( 'missing' !== $source && class_exists( 'Pas_Patterns' ) ) {
				$all       = \Pas_Patterns::all();
				$dashboard = \Pas_Patterns::dashboard_patterns();

				$detail = sprintf(
					/* translators: 1: total patterns, 2: dashboard patterns */
					__( '%1$d الگوی تأییدشده، %2$d قابل ارسال از داشبورد.', 'peyvand-amn-dashboard' ),
					is_array( $all ) ? count( $all ) : 0,
					is_array( $dashboard ) ? count( $dashboard ) : 0
				);
			}
		}

		return self::service(
			'sms',
			__( 'پل پیامک', 'peyvand-amn-dashboard' ),
			'channels',
			false,
			$active,
			self::version( 'pivand-amn-sms-bridge', 'pivand-amn-sms-bridge.php', 'PAS_BRIDGE_VERSION' ),
			$status,
			$detail
		);
	}

	/** سرویس SMTP: آمادگی تنظیمات و شمار صف بدون نمایش آدرس یا متن ایمیل. */
	private static function smtp_service() {
		$active = PAS_Dashboard_Adapter_Smtp::available();
		$detail = __( 'ارسال ایمیل وردپرس از SMTP یا Brevo با صف تلاش دوباره.', 'peyvand-amn-dashboard' );
		$status = null;

		if ( $active ) {
			$state = PAS_Dashboard_Adapter_Smtp::status();
			$queue = PAS_Dashboard_Adapter_Smtp::summary();

			if ( is_array( $state ) ) {
				$method = 'brevo' === (string) ( $state['send_method'] ?? 'smtp' ) ? 'Brevo API' : 'SMTP';

				if ( empty( $state['configured'] ) ) {
					$status = array( 'warn', __( 'روش ارسال انتخاب شده اما تنظیمات آن کامل نیست.', 'peyvand-amn-dashboard' ) );
					$detail = $method;
				} else {
					$detail = $method;

					if ( ! empty( $queue['pending'] ) ) {
						$status = array(
							'warn',
							sprintf(
								/* translators: %d: queued emails */
								__( '%d ایمیل در انتظار تلاش دوباره است.', 'peyvand-amn-dashboard' ),
								(int) $queue['pending']
							),
						);
					}
				}
			}
		}

		return self::service(
			'smtp',
			__( 'ارسال ایمیل (SMTP)', 'peyvand-amn-dashboard' ),
			'channels',
			false,
			$active,
			self::version( 'pivand-amn-smtp-mailer', 'pivand-amn-smtp-mailer.php', 'ZSM_VERSION' ),
			$status,
			$detail
		);
	}

	/**
	 * ساخت یک ردیف سرویس؛ وضعیت پیش‌فرض از فعال‌بودن می‌آید.
	 *
	 * @param array{0:string,1:string}|null $override [status, detail]
	 */
	private static function service( $id, $label, $group, $required, $active, $version, $override, $note ) {
		$status = $active ? 'ok' : ( $required ? 'danger' : 'warn' );
		$detail = $note;

		if ( ! $active ) {
			$detail = $required
				? __( 'این افزونهٔ ضروری فعال نیست؛ ماژول‌های وابسته غیرفعال می‌شوند.', 'peyvand-amn-dashboard' )
				: __( 'افزونه فعال نیست؛ فقط ماژول‌های وابسته به آن غیرفعال می‌شوند.', 'peyvand-amn-dashboard' );
		}

		if ( is_array( $override ) && isset( $override[0] ) ) {
			$status = (string) $override[0];
			$detail = (string) ( $override[1] ?? $detail );
		}

		return array(
			'id'       => (string) $id,
			'label'    => (string) $label,
			'group'    => (string) $group,
			'required' => (bool) $required,
			'active'   => (bool) $active,
			'version'  => (string) $version,
			'status'   => $status,
			'detail'   => $detail,
			'note'     => (string) $note,
		);
	}

	/** نسخه از هدر افزونهٔ نصب‌شده؛ در نبود آن، ثابت نسخهٔ همان افزونه. */
	private static function version( $slug, $file, $constant ) {
		$path = trailingslashit( WP_PLUGIN_DIR ) . $slug . '/' . $file;

		if ( is_readable( $path ) && function_exists( 'get_file_data' ) ) {
			$data = get_file_data( $path, array( 'Version' => 'Version' ) );

			if ( ! empty( $data['Version'] ) ) {
				return (string) $data['Version'];
			}
		}

		return defined( $constant ) ? (string) constant( $constant ) : '';
	}

	public static function status_label( $status ) {
		$labels = array(
			'ok'     => __( 'متصل', 'peyvand-amn-dashboard' ),
			'warn'   => __( 'نیازمند بررسی', 'peyvand-amn-dashboard' ),
			'danger' => __( 'غیرفعال', 'peyvand-amn-dashboard' ),
		);

		return $labels[ $status ] ?? $status;
	}

	public static function status_class( $status ) {
		$classes = array(
			'ok'     => 'pas-badge-good',
			'warn'   => 'pas-badge-warn',
			'danger' => 'pas-badge-danger',
		);

		return $classes[ $status ] ?? 'pas-badge-warn';
	}
}
