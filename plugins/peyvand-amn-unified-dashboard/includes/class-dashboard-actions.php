<?php
/**
 * لایهٔ عملیات (Action Bus) — تنها مسیر نوشتن در داشبورد.
 *
 * هر عمل: nonce → کپابیلیتی → دامنهٔ شیء (در هندلر) → API افزونهٔ مالک →
 * ممیزی → پیام فلش → redirect. هیچ عمل نوشتنی در GET اجرا نمی‌شود.
 */

defined( 'ABSPATH' ) || exit;

final class PAS_Dashboard_Actions {

	/** @var array<string,array> */
	private static $actions = array();

	public static function init() {
		// برچسب عمل‌ها با __() ساخته می‌شوند؛ ثبت روی init انجام می‌شود تا
		// وردپرس ۶.۷+ هشدار «Translation loading … too early» در debug.log ندهد.
		// init پیش از admin_post و پیش از رندر ماژول‌ها اجرا می‌شود، پس هیچ
		// عمل نوشتنی بی‌ثبت نمی‌ماند.
		add_action( 'init', array( __CLASS__, 'register_defaults' ), 5 );

		add_action( 'admin_post_pas_dash_action', array( __CLASS__, 'dispatch' ) );
	}

	/**
	 * @param array{label:string,cap:string,nonce:string,handler:callable,audit:string,object:string} $config
	 */
	public static function register( $id, array $config ) {
		$id = sanitize_key( (string) $id );

		if ( $id === '' || empty( $config['handler'] ) ) {
			return;
		}

		self::$actions[ $id ] = array_merge(
			array(
				'label'   => $id,
				'cap'     => 'manage_options',
				'nonce'   => $id,
				'audit'   => 'action.' . $id,
				'object'  => '',
				'confirm' => false,
			),
			$config
		);
	}

	public static function get( $id ) {
		return isset( self::$actions[ $id ] ) ? self::$actions[ $id ] : null;
	}

	/** آدرس admin-post برای یک عمل */
	public static function url( $id, array $args = array() ) {
		return add_query_arg(
			array_merge(
				array(
					'action'     => 'pas_dash_action',
					'pas_action' => $id,
				),
				$args
			),
			admin_url( 'admin-post.php' )
		);
	}

	/** فیلدهای مخفی فرم: action، شناسهٔ عمل، nonce و مقصد بازگشت */
	public static function fields( $id, $redirect = '' ) {
		$config = self::get( $id );

		if ( ! $config ) {
			return '';
		}

		$html  = '<input type="hidden" name="action" value="pas_dash_action" />';
		$html .= '<input type="hidden" name="pas_action" value="' . esc_attr( $id ) . '" />';
		$html .= PAS_Dashboard_Security::nonce_field( $config['nonce'] );
		$html .= '<input type="hidden" name="_pas_redirect" value="' . esc_attr( $redirect ) . '" />';

		return $html;
	}

	public static function dispatch() {
		if ( 'POST' !== strtoupper( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ?? 'GET' ) ) ) ) {
			wp_die( esc_html__( 'این عملیات فقط با POST مجاز است.', 'peyvand-amn-dashboard' ), '', array( 'response' => 405 ) );
		}

		// phpcs:ignore WordPress.Security.NonceVerification -- The nonce for this action id is verified a few lines below.
		$id     = isset( $_POST['pas_action'] ) ? sanitize_key( wp_unslash( $_POST['pas_action'] ) ) : '';
		$config = self::get( $id );

		if ( ! $config ) {
			wp_die( esc_html__( 'عملیات ناشناخته است.', 'peyvand-amn-dashboard' ), '', array( 'response' => 400 ) );
		}

		if ( ! PAS_Dashboard_Security::verify_nonce( $config['nonce'] ) ) {
			PAS_Dashboard_Security::record_denied( $config['audit'], $config['object'], 0 );
			wp_die( esc_html__( 'توکن امنیتی نامعتبر یا منقضی شده است.', 'peyvand-amn-dashboard' ), '', array( 'response' => 403 ) );
		}

		if ( ! current_user_can( $config['cap'] ) ) {
			PAS_Dashboard_Security::record_denied( $config['audit'], $config['object'], 0 );
			wp_die( esc_html__( 'دسترسی به این عملیات مجاز نیست.', 'peyvand-amn-dashboard' ), '', array( 'response' => 403 ) );
		}

		if ( ! PAS_Dashboard_Security::can_access_dashboard() ) {
			PAS_Dashboard_Security::record_denied( $config['audit'], $config['object'], 0 );
			wp_die( esc_html__( 'دسترسی به داشبورد مجاز نیست.', 'peyvand-amn-dashboard' ), '', array( 'response' => 403 ) );
		}

		// phpcs:ignore WordPress.Security.NonceVerification -- Nonce, capability and dashboard access were verified above.
		$post = wp_unslash( $_POST );
		$post = is_array( $post ) ? $post : array();

		// اگر خطای مرگبار PHP رخ دهد، به‌جای صفحهٔ سفید پیام خوانا نشان داده شود.
		self::guard_fatals( isset( $post['_pas_redirect'] ) ? (string) $post['_pas_redirect'] : '' );

		/*
		 * هر خروجی پراکندهٔ هندلر (هشدار PHP، پیام آپ‌گریدر وردپرس) گرفته و
		 * دور ریخته می‌شود. پیش از این، آپدیت افزونه چند بایت خروجی تولید
		 * می‌کرد؛ سپس wp_safe_redirect نمی‌توانست header بفرستد و مرورگر پس
		 * از زدن «به‌روزرسانی» صفحهٔ سفید/خالی نشان می‌داد.
		 */
		$buffer = ob_get_level();
		ob_start();

		try {
			$result = call_user_func( $config['handler'], $post );
		} catch ( Throwable $e ) {
			$result = array(
				'ok'      => false,
				'message' => sprintf(
					/* translators: %s: PHP error message */
					__( 'عملیات با خطا متوقف شد: %s', 'peyvand-amn-dashboard' ),
					$e->getMessage()
				),
				'id'      => 0,
			);
		}

		while ( ob_get_level() > $buffer ) {
			ob_end_clean();
		}

		if ( is_wp_error( $result ) ) {
			$result = array(
				'ok'      => false,
				'message' => $result->get_error_message(),
				'id'      => 0,
			);
		}

		$ok      = ! empty( $result['ok'] );
		$message = (string) ( $result['message'] ?? ( $ok ? __( 'عملیات انجام شد.', 'peyvand-amn-dashboard' ) : __( 'عملیات ناموفق بود.', 'peyvand-amn-dashboard' ) ) );
		$obj_id  = absint( $result['id'] ?? 0 );

		PAS_Dashboard_Audit_Log::record( $config['audit'], $config['object'], $obj_id, $ok ? 'ok' : 'error' );
		PAS_Dashboard_Flash::set( $message, $ok ? 'success' : 'error' );

		$fallback = wp_get_referer();
		$redirect = isset( $post['_pas_redirect'] ) ? esc_url_raw( (string) $post['_pas_redirect'] ) : '';
		if ( ! empty( $result['redirect'] ) ) {
			$redirect = esc_url_raw( (string) $result['redirect'] );
		}

		$redirect = wp_validate_redirect( $redirect, $fallback ? $fallback : admin_url( 'admin.php?page=' . PAS_DASHBOARD_SLUG ) );

		wp_safe_redirect( $redirect );
		exit;
	}

	/**
	 * اگر عملیات به خطای مرگبار PHP (سقف زمان/حافظه) خورد، به‌جای صفحهٔ سفید
	 * یک پیام خوانا نشان داده می‌شود تا مدیر بداند چه رخ داده است.
	 *
	 * @param string $fallback نشانی بازگشت که فرم فرستاده است.
	 */
	private static function guard_fatals( $fallback = '' ) {
		register_shutdown_function(
			static function () use ( $fallback ) {
				$error = error_get_last();
				$fatal = array( E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR, E_RECOVERABLE_ERROR );

				if ( ! is_array( $error ) || ! in_array( (int) $error['type'], $fatal, true ) ) {
					return;
				}

				while ( ob_get_level() > 0 ) {
					ob_end_clean();
				}

				if ( ! headers_sent() ) {
					status_header( 500 );
					header( 'Content-Type: text/html; charset=utf-8' );
				}

				$url = wp_validate_redirect(
					esc_url_raw( (string) $fallback ),
					admin_url( 'admin.php?page=' . PAS_DASHBOARD_SLUG . '&module=updates' )
				);

				echo '<!doctype html><html dir="rtl" lang="fa"><meta charset="utf-8"><title>عملیات نیمه‌تمام ماند</title><body style="font-family:Tahoma,sans-serif;padding:32px;line-height:2">';
				echo '<h1>عملیات نیمه‌تمام ماند</h1>';
				echo '<p>سرور هنگام اجرای این عملیات متوقف شد (احتمالاً سقف زمان اجرا یا حافظهٔ هاست). اگر به‌روزرسانی افزونه بود، فایل‌ها ممکن است نیمه‌جایگزین شده باشند؛ یک‌بار دیگر «به‌روزرسانی» را بزنید یا بسته را دستی نصب کنید.</p>';
				echo '<p dir="ltr" style="color:#b32d2e">' . esc_html( (string) $error['message'] ) . '</p>';
				echo '<p><a href="' . esc_url( $url ) . '">بازگشت به داشبورد</a></p>';
				echo '</body></html>';
			}
		);
	}

	public static function register_defaults() {
		$case_cap  = defined( 'PAS_CF_CAP' ) ? PAS_CF_CAP : 'manage_options';
		$appt_cap  = defined( 'PAS_DASHBOARD_CAP_APPOINTMENTS' ) ? PAS_DASHBOARD_CAP_APPOINTMENTS : 'manage_options';
		$quiz_cap  = defined( 'PAS_DASHBOARD_CAP_ASSESSMENTS' ) ? PAS_DASHBOARD_CAP_ASSESSMENTS : 'manage_options';
		$msg_cap   = defined( 'PAS_DASHBOARD_CAP_MESSAGES' ) ? PAS_DASHBOARD_CAP_MESSAGES : 'manage_options';
		$flow_cap  = defined( 'PAS_DASHBOARD_CAP_FOLLOWUP' ) ? PAS_DASHBOARD_CAP_FOLLOWUP : 'manage_options';
		$eco_cap   = defined( 'PAS_DASHBOARD_CAP_ECOSYSTEM' ) ? PAS_DASHBOARD_CAP_ECOSYSTEM : 'manage_options';

		self::register(
			'case.save',
			array(
				'label'   => __( 'ثبت/ویرایش پرونده', 'peyvand-amn-dashboard' ),
				'cap'     => $case_cap,
				'nonce'   => 'case_save',
				'handler' => array( 'PAS_Dashboard_Adapter_Case_File', 'save_case' ),
				'audit'   => 'case.save',
				'object'  => 'case',
			)
		);

		self::register(
			'case.intake',
			array(
				'label'   => __( 'اعمال فرم ارزیابی', 'peyvand-amn-dashboard' ),
				'cap'     => $case_cap,
				'nonce'   => 'case_intake',
				'handler' => array( 'PAS_Dashboard_Adapter_Case_File', 'apply_intake' ),
				'audit'   => 'case.intake',
				'object'  => 'case',
			)
		);

		self::register(
			'case.reminder',
			array(
				'label'   => __( 'ارسال یادآور جلسه', 'peyvand-amn-dashboard' ),
				'cap'     => $case_cap,
				'nonce'   => 'case_reminder',
				'handler' => array( 'PAS_Dashboard_Adapter_Case_File', 'send_reminder' ),
				'audit'   => 'case.reminder',
				'object'  => 'case',
			)
		);

		self::register(
			'booking.create',
			array(
				'label'   => __( 'ثبت نوبت', 'peyvand-amn-dashboard' ),
				'cap'     => $appt_cap,
				'nonce'   => 'booking_create',
				'handler' => array( 'PAS_Dashboard_Adapter_Portal', 'create_booking' ),
				'audit'   => 'booking.create',
				'object'  => 'booking',
			)
		);

		self::register(
			'booking.status',
			array(
				'label'   => __( 'تغییر وضعیت نوبت', 'peyvand-amn-dashboard' ),
				'cap'     => $appt_cap,
				'nonce'   => 'booking_status',
				'handler' => array( 'PAS_Dashboard_Adapter_Portal', 'change_status' ),
				'audit'   => 'booking.status',
				'object'  => 'booking',
			)
		);

		self::register(
			'booking.update',
			array(
				'label'   => __( 'ویرایش نوبت', 'peyvand-amn-dashboard' ),
				'cap'     => $appt_cap,
				'nonce'   => 'booking_update',
				'handler' => array( 'PAS_Dashboard_Adapter_Portal', 'update_booking' ),
				'audit'   => 'booking.update',
				'object'  => 'booking',
			)
		);

		self::register(
			'booking.delete',
			array(
				'label'   => __( 'حذف نوبت', 'peyvand-amn-dashboard' ),
				'cap'     => $appt_cap,
				'nonce'   => 'booking_delete',
				'handler' => array( 'PAS_Dashboard_Adapter_Portal', 'delete_booking' ),
				'audit'   => 'booking.delete',
				'object'  => 'booking',
			)
		);

		self::register(
			'booking.reminder',
			array(
				'label'   => __( 'یادآور نوبت', 'peyvand-amn-dashboard' ),
				'cap'     => $appt_cap,
				'nonce'   => 'booking_reminder',
				'handler' => array( 'PAS_Dashboard_Adapter_Portal', 'send_reminder' ),
				'audit'   => 'booking.reminder',
				'object'  => 'booking',
			)
		);

		self::register(
			'assessment.invite',
			array(
				'label'   => __( 'دعوت به آزمون', 'peyvand-amn-dashboard' ),
				'cap'     => $quiz_cap,
				'nonce'   => 'assessment_invite',
				'handler' => array( 'PAS_Dashboard_Adapter_Quiz', 'create_invitation' ),
				'audit'   => 'assessment.invite',
				'object'  => 'assessment',
			)
		);

		self::register(
			'assessment.notify',
			array(
				'label'   => __( 'ارسال دوبارهٔ دعوت', 'peyvand-amn-dashboard' ),
				'cap'     => $quiz_cap,
				'nonce'   => 'assessment_notify',
				'handler' => array( 'PAS_Dashboard_Adapter_Quiz', 'notify' ),
				'audit'   => 'assessment.notify',
				'object'  => 'assessment',
			)
		);

		self::register(
			'assessment.revoke',
			array(
				'label'   => __( 'ابطال دعوت', 'peyvand-amn-dashboard' ),
				'cap'     => $quiz_cap,
				'nonce'   => 'assessment_revoke',
				'handler' => array( 'PAS_Dashboard_Adapter_Quiz', 'revoke' ),
				'audit'   => 'assessment.revoke',
				'object'  => 'assessment',
			)
		);

		self::register(
			'lead.followup',
			array(
				'label'   => __( 'ثبت پیگیری لید', 'peyvand-amn-dashboard' ),
				'cap'     => $flow_cap,
				'nonce'   => 'lead_followup',
				'handler' => array( 'PAS_Dashboard_Adapter_Followup', 'mark_followup' ),
				'audit'   => 'lead.followup',
				'object'  => 'lead',
			)
		);

		self::register(
			'survey.moderate',
			array(
				'label'   => __( 'تأیید/لغو نظرسنجی', 'peyvand-amn-dashboard' ),
				'cap'     => $flow_cap,
				'nonce'   => 'survey_moderate',
				'handler' => array( 'PAS_Dashboard_Adapter_Followup', 'moderate_survey' ),
				'audit'   => 'survey.moderate',
				'object'  => 'survey',
			)
		);

		self::register(
			'survey.delete',
			array(
				'label'   => __( 'حذف نظرسنجی', 'peyvand-amn-dashboard' ),
				'cap'     => $flow_cap,
				'nonce'   => 'survey_delete',
				'handler' => array( 'PAS_Dashboard_Adapter_Followup', 'delete_survey' ),
				'audit'   => 'survey.delete',
				'object'  => 'survey',
			)
		);

		self::register(
			'sms.send',
			array(
				'label'   => __( 'ارسال پیامک الگومحور', 'peyvand-amn-dashboard' ),
				'cap'     => $msg_cap,
				'nonce'   => 'sms_send',
				'handler' => array( 'PAS_Dashboard_Adapter_Sms', 'send' ),
				'audit'   => 'sms.send',
				'object'  => 'sms',
			)
		);

		self::register(
			'email.test',
			array(
				'label'   => __( 'تست ارسال ایمیل', 'peyvand-amn-dashboard' ),
				'cap'     => $eco_cap,
				'nonce'   => 'email_test',
				'handler' => array( 'PAS_Dashboard_Adapter_Smtp', 'send_test' ),
				'audit'   => 'email.test',
				'object'  => 'email',
			)
		);

		self::register(
			'email.queue.retry',
			array(
				'label'   => __( 'تلاش دوبارهٔ ارسال ایمیل', 'peyvand-amn-dashboard' ),
				'cap'     => $eco_cap,
				'nonce'   => 'email_queue_retry',
				'handler' => array( 'PAS_Dashboard_Adapter_Smtp', 'retry' ),
				'audit'   => 'email.queue.retry',
				'object'  => 'email',
			)
		);

		self::register(
			'ecosystem.save',
			array(
				'label'   => __( 'ذخیرهٔ تنظیمات اتصال سرویس‌ها', 'peyvand-amn-dashboard' ),
				// تغییر اعتبارنامهٔ پیامک و تنظیمات SMTP کار مدیر سایت است؛
				// نه کپابیلیتی مدیریت اکوسیستم که فقط تست و صف را باز می‌کند.
				'cap'     => 'manage_options',
				'nonce'   => 'ecosystem_save',
				'handler' => array( 'PAS_Dashboard_Adapter_Ecosystem', 'save_settings' ),
				'audit'   => 'ecosystem.save',
				'object'  => 'settings',
			)
		);

		self::register(
			'menus.save',
			array(
				'label'   => __( 'ذخیرهٔ پنهان‌سازی منوها', 'peyvand-amn-dashboard' ),
				'cap'     => 'manage_options',
				'nonce'   => 'menus_save',
				'handler' => array( 'PAS_Dashboard_Permissions', 'save_hidden_menus' ),
				'audit'   => 'menus.save',
				'object'  => 'settings',
			)
		);

		// به‌روزرسانی افزونه‌ها از مخزن گیت‌هاب — فقط مدیر سایت.
		self::register(
			'updates.refresh',
			array(
				'label'   => __( 'بررسی دوبارهٔ مخزن گیت‌هاب', 'peyvand-amn-dashboard' ),
				'cap'     => 'manage_options',
				'nonce'   => 'updates_refresh',
				'handler' => array( 'PAS_Dashboard_Updater', 'refresh' ),
				'audit'   => 'updates.refresh',
				'object'  => 'updates',
			)
		);

		self::register(
			'updates.save',
			array(
				'label'   => __( 'ذخیرهٔ تنظیمات به‌روزرسانی', 'peyvand-amn-dashboard' ),
				'cap'     => 'manage_options',
				'nonce'   => 'updates_save',
				'handler' => array( 'PAS_Dashboard_Updater', 'save_settings' ),
				'audit'   => 'updates.save',
				'object'  => 'settings',
			)
		);

		self::register(
			'updates.run',
			array(
				'label'   => __( 'به‌روزرسانی افزونه از مخزن', 'peyvand-amn-dashboard' ),
				// علاوه بر این کپابیلیتی، هندلر خودش `update_plugins` را هم می‌سنجد.
				'cap'     => 'manage_options',
				'nonce'   => 'updates_run',
				'handler' => array( 'PAS_Dashboard_Updater', 'run' ),
				'audit'   => 'updates.run',
				'object'  => 'plugin',
				'confirm' => true,
			)
		);

		self::register(
			'message.reply',
			array(
				'label'   => __( 'پاسخ به پیام مراجع', 'peyvand-amn-dashboard' ),
				'cap'     => $msg_cap,
				'nonce'   => 'message_reply',
				'handler' => array( 'PAS_Dashboard_Adapter_Portal', 'reply_message' ),
				'audit'   => 'message.reply',
				'object'  => 'message',
			)
		);
	}
}
