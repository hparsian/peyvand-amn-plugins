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
		self::register_defaults();

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
		if ( 'POST' !== strtoupper( (string) ( $_SERVER['REQUEST_METHOD'] ?? 'GET' ) ) ) {
			wp_die( esc_html__( 'این عملیات فقط با POST مجاز است.', 'peyvand-amn-dashboard' ), '', array( 'response' => 405 ) );
		}

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

		$post   = wp_unslash( $_POST );
		$result = call_user_func( $config['handler'], is_array( $post ) ? $post : array() );

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

	private static function register_defaults() {
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
