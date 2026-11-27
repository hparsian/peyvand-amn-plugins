<?php
/**
 * آداپتر آزمون‌های روان‌سنجی — دعوت، نتیجه، روند و گزارش.
 *
 * همهٔ خواندن/نوشتن از APIهای عمومی افزونهٔ مالک (Psy_Quiz_Invitation_Manager
 * و Registry) انجام می‌شود. توکن دعوت‌نامه هرگز در فهرست برنمی‌گردد.
 */

defined( 'ABSPATH' ) || exit;

final class PAS_Dashboard_Adapter_Quiz {

	/** @var array<int,string>|null */
	private static $scope_phones = null;

	public static function available() {
		return class_exists( 'Psy_Quiz_Invitation_Manager' )
			&& method_exists( 'Psy_Quiz_Invitation_Manager', 'create' )
			&& method_exists( 'Psy_Quiz_Invitation_Manager', 'dashboard_list' );
	}

	/** @return array<int,array<string,mixed>> */
	public static function invitations( $limit = 200, $status = '' ) {
		if ( ! self::available() ) {
			return array();
		}

		try {
			$rows = Psy_Quiz_Invitation_Manager::dashboard_list( (int) $limit, (string) $status );
		} catch ( Throwable $e ) {
			return array();
		}

		return is_array( $rows ) ? $rows : array();
	}

	public static function invitation( $id ) {
		if ( ! self::available() || ! method_exists( 'Psy_Quiz_Invitation_Manager', 'dashboard_get' ) ) {
			return null;
		}

		try {
			$row = Psy_Quiz_Invitation_Manager::dashboard_get( PAS_Dashboard_Validation::id( $id ) );
		} catch ( Throwable $e ) {
			return null;
		}

		return is_object( $row ) ? $row : null;
	}

	public static function create_invitation( array $post ) {
		if ( ! self::available() ) {
			return new WP_Error( 'pas_dashboard_no_quiz', __( 'افزونهٔ آزمون‌ها فعال نیست یا قرارداد دعوت در دسترس نیست.', 'peyvand-amn-dashboard' ) );
		}

		$quiz_id = PAS_Dashboard_Validation::key( $post['quiz_id'] ?? '', 50 );
		$ids     = array();

		foreach ( PAS_Dashboard_Core_Bridge::assessments() as $test ) {
			$ids[] = (string) $test['id'];
		}

		if ( $quiz_id === '' || ! in_array( $quiz_id, $ids, true ) ) {
			return new WP_Error( 'pas_dashboard_quiz_invalid', __( 'آزمون انتخاب‌شده معتبر نیست.', 'peyvand-amn-dashboard' ) );
		}

		$name = PAS_Dashboard_Validation::text( $post['client_name'] ?? '', 190 );

		if ( mb_strlen( $name ) < 2 ) {
			return new WP_Error( 'pas_dashboard_quiz_name', __( 'نام مراجع را وارد کنید.', 'peyvand-amn-dashboard' ) );
		}

		$raw_phone = PAS_Dashboard_Validation::text( $post['client_phone'] ?? '', 30 );
		$phone     = class_exists( 'Psy_Quiz_Invitation_Manager' )
			? (string) Psy_Quiz_Invitation_Manager::to_local_format( $raw_phone )
			: $raw_phone;

		if ( $phone === '' || ! Psy_Quiz_Invitation_Manager::is_valid_mobile( $phone ) ) {
			return new WP_Error( 'pas_dashboard_quiz_phone', __( 'شمارهٔ موبایل مراجع معتبر نیست.', 'peyvand-amn-dashboard' ) );
		}

		$email = PAS_Dashboard_Validation::email( $post['client_email'] ?? '' );

		if ( (string) ( $post['client_email'] ?? '' ) !== '' && $email === '' ) {
			return new WP_Error( 'pas_dashboard_quiz_email', __( 'ایمیل واردشده معتبر نیست.', 'peyvand-amn-dashboard' ) );
		}

		$expiry_raw = $post['expiry_days'] ?? '';
		$expiry     = $expiry_raw === '' ? null : (int) $expiry_raw;

		if ( null !== $expiry ) {
			$expiry = max( 0, min( 90, $expiry ) );
		}

		try {
			$created = Psy_Quiz_Invitation_Manager::create( $quiz_id, $name, $phone, $expiry, $email );
		} catch ( Throwable $e ) {
			return new WP_Error( 'pas_dashboard_quiz_failed', __( 'ساخت دعوت‌نامه ناموفق بود.', 'peyvand-amn-dashboard' ) );
		}

		$id   = (int) ( $created['id'] ?? 0 );
		$link = (string) ( $created['link'] ?? '' );

		if ( $id <= 0 ) {
			return new WP_Error( 'pas_dashboard_quiz_failed', __( 'ساخت دعوت‌نامه ناموفق بود.', 'peyvand-amn-dashboard' ) );
		}

		set_transient(
			self::link_key(),
			array(
				'id'    => $id,
				'name'  => $name,
				'link'  => $link,
				'quiz'  => $quiz_id,
				'expires_at' => (string) ( $created['expires_at'] ?? '' ),
			),
			15 * MINUTE_IN_SECONDS
		);

		$notify  = ! empty( $post['notify'] );
		$message = __( 'دعوت‌نامه ساخته شد.', 'peyvand-amn-dashboard' );

		if ( $notify ) {
			$status = self::notify_client( $name, $phone, $email );

			if ( ! empty( $status['sms'] ) || ! empty( $status['email'] ) ) {
				$message = __( 'دعوت‌نامه ساخته و برای مراجع ارسال شد.', 'peyvand-amn-dashboard' );
			} elseif ( ! empty( $status['throttled'] ) ) {
				$message = __( 'دعوت‌نامه ساخته شد؛ ارسال امروز این مراجع قبلاً انجام شده و تکرار نشد. لینک را می‌توانید از کارت بالای صفحه بردارید.', 'peyvand-amn-dashboard' );
			} else {
				$message = __( 'دعوت‌نامه ساخته شد اما ارسال خودکار ناموفق بود؛ لینک دعوت را از کارت بالای صفحه بردارید.', 'peyvand-amn-dashboard' );
			}
		}

		return array(
			'ok'       => true,
			'message'  => $message,
			'id'       => $id,
			'redirect' => self::module_url(),
		);
	}

	public static function revoke( array $post ) {
		$invitation = self::invitation( $post['invitation_id'] ?? 0 );

		if ( ! $invitation ) {
			return new WP_Error( 'pas_dashboard_quiz_missing', __( 'دعوت‌نامه یافت نشد.', 'peyvand-amn-dashboard' ) );
		}

		if ( ! self::can_view_phone( (string) $invitation->client_phone ) ) {
			return new WP_Error( 'pas_dashboard_quiz_scope', __( 'این دعوت‌نامه برای مراجعان پرونده‌های شما نیست.', 'peyvand-amn-dashboard' ) );
		}

		if ( 'unused' !== (string) $invitation->status ) {
			return new WP_Error( 'pas_dashboard_quiz_state', __( 'فقط دعوت‌نامهٔ استفاده‌نشده قابل ابطال است.', 'peyvand-amn-dashboard' ) );
		}

		$ok = method_exists( 'Psy_Quiz_Invitation_Manager', 'dashboard_revoke' )
			? Psy_Quiz_Invitation_Manager::dashboard_revoke( (int) $invitation->id )
			: false;

		return array(
			'ok'       => (bool) $ok,
			'message'  => $ok ? __( 'دعوت‌نامه باطل شد.', 'peyvand-amn-dashboard' ) : __( 'ابطال دعوت‌نامه ناموفق بود.', 'peyvand-amn-dashboard' ),
			'id'       => (int) $invitation->id,
			'redirect' => self::module_url(),
		);
	}

	public static function notify( array $post ) {
		$invitation = self::invitation( $post['invitation_id'] ?? 0 );

		if ( ! $invitation ) {
			return new WP_Error( 'pas_dashboard_quiz_missing', __( 'دعوت‌نامه یافت نشد.', 'peyvand-amn-dashboard' ) );
		}

		if ( ! self::can_view_phone( (string) $invitation->client_phone ) ) {
			return new WP_Error( 'pas_dashboard_quiz_scope', __( 'این دعوت‌نامه برای مراجعان پرونده‌های شما نیست.', 'peyvand-amn-dashboard' ) );
		}

		// همان قید رابط کاربری در سمت سرور: دعوت باطل‌شده یا تکمیل‌شده
		// دوباره ارسال نمی‌شود تا POST دستکاری‌شده هم نتواند لینک مرده بفرستد.
		$state = (string) $invitation->status;

		if ( ! in_array( $state, array( 'unused', 'processing' ), true ) ) {
			return new WP_Error( 'pas_dashboard_quiz_state', __( 'دعوت‌نامهٔ باطل‌شده یا تکمیل‌شده را نمی‌توان دوباره ارسال کرد.', 'peyvand-amn-dashboard' ) );
		}

		if ( 'unused' === $state && ! empty( $invitation->expires_at ) && $invitation->expires_at <= gmdate( 'Y-m-d H:i:s' ) ) {
			return new WP_Error( 'pas_dashboard_quiz_expired', __( 'این دعوت‌نامه منقضی شده است؛ برای ارسال دوباره دعوت‌نامهٔ تازه بسازید.', 'peyvand-amn-dashboard' ) );
		}

		$status = self::notify_client(
			(string) $invitation->client_name,
			(string) $invitation->client_phone,
			(string) $invitation->client_email
		);

		if ( ! empty( $status['sms'] ) || ! empty( $status['email'] ) ) {
			return array(
				'ok'       => true,
				'message'  => __( 'دعوت‌نامه دوباره ارسال شد.', 'peyvand-amn-dashboard' ),
				'id'       => (int) $invitation->id,
				'redirect' => self::module_url(),
			);
		}

		return new WP_Error( 'pas_dashboard_quiz_notify', __( 'ارسال دعوت‌نامه ناموفق بود؛ الگوی پیامک یا ایمیل مراجع را بررسی کنید.', 'peyvand-amn-dashboard' ) );
	}

	/** @return array<int,array<string,mixed>> */
	public static function results( $phone ) {
		if ( ! self::available() || ! method_exists( 'Psy_Quiz_Invitation_Manager', 'get_completed_results_for_phone' ) ) {
			return array();
		}

		try {
			$rows = Psy_Quiz_Invitation_Manager::get_completed_results_for_phone( (string) $phone );
		} catch ( Throwable $e ) {
			return array();
		}

		return is_array( $rows ) ? $rows : array();
	}

	/** @return array<int,array<string,mixed>> */
	public static function scores( $phone ) {
		if ( ! self::available() || ! method_exists( 'Psy_Quiz_Invitation_Manager', 'get_assessment_scores_for_phone' ) ) {
			return array();
		}

		try {
			$rows = Psy_Quiz_Invitation_Manager::get_assessment_scores_for_phone( (string) $phone );
		} catch ( Throwable $e ) {
			return array();
		}

		return is_array( $rows ) ? $rows : array();
	}

	/** @return array{ok:bool,html:string,error:string} */
	public static function report( $attempt_id, $phone ) {
		if ( ! self::available() || ! method_exists( 'Psy_Quiz_Invitation_Manager', 'dashboard_report' ) ) {
			return array( 'ok' => false, 'html' => '', 'error' => __( 'API گزارش در افزونهٔ آزمون در دسترس نیست.', 'peyvand-amn-dashboard' ) );
		}

		try {
			$report = Psy_Quiz_Invitation_Manager::dashboard_report( (int) $attempt_id, (string) $phone );
		} catch ( Throwable $e ) {
			return array( 'ok' => false, 'html' => '', 'error' => __( 'خواندن گزارش ناموفق بود.', 'peyvand-amn-dashboard' ) );
		}

		return is_array( $report )
			? $report
			: array( 'ok' => false, 'html' => '', 'error' => __( 'خواندن گزارش ناموفق بود.', 'peyvand-amn-dashboard' ) );
	}

	public static function can_view_phone( $phone ) {
		$phone = (string) $phone;

		if ( $phone === '' ) {
			return false;
		}

		if ( PAS_Dashboard_Core_Bridge::viewer_can_see_all_cases() ) {
			return true;
		}

		return in_array( $phone, self::scope_phones(), true );
	}

	public static function status_label( $invitation ) {
		$status = is_object( $invitation ) ? (string) $invitation->status : (string) ( $invitation['status'] ?? '' );
		$expired = is_object( $invitation )
			? ( 'unused' === $status && ! empty( $invitation->expires_at ) && $invitation->expires_at <= gmdate( 'Y-m-d H:i:s' ) )
			: ! empty( $invitation['is_expired'] );

		if ( $expired ) {
			return array( 'label' => __( 'منقضی', 'peyvand-amn-dashboard' ), 'class' => 'pas-badge-danger' );
		}

		$map = array(
			'unused'     => array( __( 'استفاده‌نشده', 'peyvand-amn-dashboard' ), 'pas-badge-warn' ),
			'processing' => array( __( 'در حال پاسخ', 'peyvand-amn-dashboard' ), 'pas-badge-warn' ),
			'completed'  => array( __( 'تکمیل‌شده', 'peyvand-amn-dashboard' ), 'pas-badge-good' ),
			'revoked'    => array( __( 'باطل‌شده', 'peyvand-amn-dashboard' ), 'pas-badge-danger' ),
		);

		if ( isset( $map[ $status ] ) ) {
			return array( 'label' => $map[ $status ][0], 'class' => $map[ $status ][1] );
		}

		return array( 'label' => $status, 'class' => '' );
	}

	/** لینک دعوت تازه را یک‌بار برمی‌دارد (transient کوتاه‌عمر). */
	public static function take_invite_link() {
		$key  = self::link_key();
		$data = get_transient( $key );

		if ( ! is_array( $data ) || empty( $data['link'] ) ) {
			return null;
		}

		delete_transient( $key );

		return $data;
	}

	public static function module_url() {
		return add_query_arg(
			array(
				'page'   => PAS_DASHBOARD_SLUG,
				'module' => 'assessments',
			),
			admin_url( 'admin.php' )
		);
	}

	private static function notify_client( $name, $phone, $email ) {
		try {
			$status = Psy_Quiz_Invitation_Manager::notify_client( (string) $name, (string) $phone, (string) $email, true );
		} catch ( Throwable $e ) {
			return array( 'sms' => false, 'email' => false, 'throttled' => false );
		}

		return is_array( $status ) ? $status : array( 'sms' => false, 'email' => false, 'throttled' => false );
	}

	/** @return array<int,string> */
	private static function scope_phones() {
		if ( null !== self::$scope_phones ) {
			return self::$scope_phones;
		}

		self::$scope_phones = array();

		foreach ( PAS_Dashboard_Core_Bridge::cases( get_current_user_id(), 500 ) as $case ) {
			if ( is_object( $case ) && ! empty( $case->phone ) ) {
				self::$scope_phones[] = (string) $case->phone;
			}
		}

		return self::$scope_phones;
	}

	private static function link_key() {
		return 'pas_dash_invite_link_' . get_current_user_id();
	}
}
