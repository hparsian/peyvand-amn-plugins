<?php
/**
 * آداپتر پرونده‌های بالینی — فقط APIهای عمومی افزونهٔ مالک.
 *
 * هیچ جدولی خوانده یا نوشته نمی‌شود؛ اگر افزونه یا کلید رمزنگاری نباشد،
 * عملیات با WP_Error برمی‌گردد و ماژول پیام روشن نشان می‌دهد.
 */

defined( 'ABSPATH' ) || exit;

final class PAS_Dashboard_Adapter_Case_File {

	/** همان فیلدهای متنی سادهٔ مالک (باید کامل پاس شوند تا ویرایش داده را پاک نکند). */
	private const PLAIN_FIELDS = array(
		'full_name', 'age', 'marital_status', 'education', 'occupation',
		'residence', 'marriage_type', 'children_count', 'topic',
		'main_conflict_topics', 'q_main_reason', 'q_positive_negative_traits',
		'q_conflict_resolution', 'q_reason_staying', 'q_therapy_goals',
		'relationship_satisfaction_pre', 'relationship_satisfaction_post',
		'prior_therapy', 'failed_marriage_history',
		'spouse_full_name', 'spouse_age', 'spouse_education', 'spouse_occupation', 'spouse_phone',
		'spouse_failed_marriage_history',
	);

	/** فیلدهای رمزنگاری‌شده (هم نسخهٔ مراجع و هم همسر + فیلدهای تک‌نسخه‌ای). */
	private const ENCRYPTED_FIELDS = array(
		'medical_history', 'counseling_history', 'self_harm_history', 'substance_use_history',
		'spouse_medical_history', 'spouse_counseling_history', 'spouse_self_harm_history', 'spouse_substance_use_history',
		'symptom_mood_depression', 'symptom_anxiety', 'q_schema_attachment',
	);

	private const STATUSES = array( 'فعال', 'موقتاً متوقف', 'پایان‌یافته' );

	public static function save_case( array $post ) {
		if ( ! class_exists( 'Pas_CF_Case_File' ) ) {
			return new WP_Error( 'pas_dashboard_no_case_file', __( 'افزونهٔ پرونده‌های بالینی فعال نیست.', 'peyvand-amn-dashboard' ) );
		}

		if ( ! PAS_Dashboard_Core_Bridge::crypto_ready() ) {
			return new WP_Error( 'pas_dashboard_no_crypto', __( 'برای ثبت یا ویرایش پرونده، کلید رمزنگاری باید تنظیم باشد؛ در غیر این صورت داده‌های بالینی موجود ممکن است از دست بروند.', 'peyvand-amn-dashboard' ) );
		}

		$case_id  = PAS_Dashboard_Validation::id( $post['case_id'] ?? 0 );
		$existing = null;

		if ( $case_id > 0 ) {
			$existing = PAS_Dashboard_Security::authorized_case( $case_id );
			if ( is_wp_error( $existing ) ) {
				return $existing;
			}
		} elseif ( ! PAS_Dashboard_Security::can_view_clinical() ) {
			return new WP_Error( 'pas_dashboard_forbidden', __( 'دسترسی به داده‌های بالینی مجاز نیست.', 'peyvand-amn-dashboard' ) );
		}

		$data = self::collect_fields( $post );

		if ( trim( (string) $data['full_name'] ) === '' ) {
			return new WP_Error( 'pas_dashboard_case_name', __( 'نام و نام خانوادگی مراجع الزامی است.', 'peyvand-amn-dashboard' ) );
		}

		$status = (string) ( $post['status'] ?? 'فعال' );
		$data['status'] = in_array( $status, self::STATUSES, true ) ? $status : 'فعال';

		$data['reminder_consent'] = ! empty( $post['reminder_consent'] ) ? '1' : '';
		$channel                  = PAS_Dashboard_Validation::key( $post['reminder_channel'] ?? 'sms' );
		$data['reminder_channel'] = $channel !== '' ? $channel : 'sms';

		$can_assign = PAS_Dashboard_Core_Bridge::viewer_can_see_all_cases();

		if ( $existing ) {
			$data['assigned_therapist'] = $can_assign
				? self::valid_therapist( $post['assigned_therapist'] ?? 0 )
				: (int) $existing->assigned_therapist;

			if ( array_key_exists( 'partner_phone', $post ) ) {
				$partner = self::normalize_phone( $post['partner_phone'] ?? '', false );
				if ( is_wp_error( $partner ) ) {
					return $partner;
				}
				$data['partner_phone'] = $partner;
			}

			Pas_CF_Case_File::update( $case_id, $data );
		} else {
			$phone = self::normalize_phone( $post['phone'] ?? '' );
			if ( is_wp_error( $phone ) ) {
				return $phone;
			}

			$data['phone']              = $phone;
			$data['assigned_therapist'] = $can_assign
				? self::valid_therapist( $post['assigned_therapist'] ?? 0 )
				: get_current_user_id();

			if ( ! empty( $post['partner_phone'] ) ) {
				$partner = self::normalize_phone( $post['partner_phone'], false );
				if ( is_wp_error( $partner ) ) {
					return $partner;
				}
				$data['partner_phone'] = $partner;
			}

			$case_id = (int) Pas_CF_Case_File::create( $data );

			if ( $case_id <= 0 ) {
				return new WP_Error( 'pas_dashboard_case_failed', __( 'ثبت پرونده ناموفق بود.', 'peyvand-amn-dashboard' ) );
			}
		}

		$redirect = add_query_arg(
			array(
				'page'    => PAS_DASHBOARD_SLUG,
				'module'  => 'case_files',
				'case_id' => $case_id,
			),
			admin_url( 'admin.php' )
		);

		return array(
			'ok'       => true,
			'message'  => $existing ? __( 'پرونده به‌روزرسانی شد.', 'peyvand-amn-dashboard' ) : __( 'پروندهٔ جدید ثبت شد.', 'peyvand-amn-dashboard' ),
			'id'       => $case_id,
			'redirect' => $redirect,
		);
	}

	public static function apply_intake( array $post ) {
		$case_id = PAS_Dashboard_Validation::id( $post['case_id'] ?? 0 );
		$case    = PAS_Dashboard_Security::authorized_case( $case_id );

		if ( is_wp_error( $case ) ) {
			return $case;
		}

		$preview = self::intake_preview( $case );

		if ( is_wp_error( $preview ) ) {
			return $preview;
		}

		$rows     = $preview['rows'];
		$overwrite = ! empty( $post['overwrite'] );

		$result = Pas_CF_Case_File::apply_intake_mapping(
			$case_id,
			$rows,
			$overwrite,
			(string) ( $preview['intake']['file_number'] ?? '' ),
			isset( $preview['intake']['submitted_at'] ) ? (string) $preview['intake']['submitted_at'] : null
		);

		$updated = (int) ( $result['updated'] ?? 0 );
		$skipped = (int) ( $result['skipped'] ?? 0 );

		return array(
			'ok'      => true,
			'message' => $updated > 0
				? sprintf(
					/* translators: 1: updated count, 2: skipped count */
					__( '%1$d فیلد از فرم ارزیابی اعمال شد؛ %2$d فیلد رد شد.', 'peyvand-amn-dashboard' ),
					$updated,
					$skipped
				)
				: __( 'هیچ فیلدی برای اعمال پیدا نشد (فرم خالی است یا همهٔ مقادیر یکسان/بدون اجازه بودند).', 'peyvand-amn-dashboard' ),
			'id'      => $case_id,
		);
	}

	/**
	 * پیش‌نمایش نگاشت — داده از همان قرارداد فقط‌خواندنی پنل می‌آید و طرح
	 * نگاشت دوباره در مالک محاسبه می‌شود.
	 *
	 * @return array{rows:array,intake:array}|WP_Error
	 */
	public static function intake_preview( $case ) {
		if ( ! is_object( $case ) || ! method_exists( 'Pas_CF_Case_File', 'intake_mapping_preview' ) ) {
			return new WP_Error( 'pas_dashboard_no_intake_api', __( 'API نگاشت فرم ارزیابی در افزونهٔ پرونده در دسترس نیست.', 'peyvand-amn-dashboard' ) );
		}

		$phone  = (string) ( $case->phone ?? '' );
		$intake = $phone !== '' ? apply_filters( 'zdp_get_intake_for_phone', array(), $phone ) : array();
		$intake = is_array( $intake ) ? $intake : array();

		if ( empty( $intake['data'] ) || ! is_array( $intake['data'] ) ) {
			return new WP_Error( 'pas_dashboard_no_intake', __( 'فرم ارزیابی ثبت‌شده‌ای برای این شماره پیدا نشد (یا افزونهٔ پنل مراجعان فعال نیست).', 'peyvand-amn-dashboard' ) );
		}

		$rows = Pas_CF_Case_File::intake_mapping_preview( $case, $intake );

		return array(
			'rows'   => is_array( $rows ) ? $rows : array(),
			'intake' => $intake,
		);
	}

	public static function send_reminder( array $post ) {
		$case_id = PAS_Dashboard_Validation::id( $post['case_id'] ?? 0 );
		$case    = PAS_Dashboard_Security::authorized_case( $case_id );

		if ( is_wp_error( $case ) ) {
			return $case;
		}

		if ( ! class_exists( 'Pas_CF_Case_File' ) || ! method_exists( 'Pas_CF_Case_File', 'reminder_blocker' ) ) {
			return new WP_Error( 'pas_dashboard_no_reminder_api', __( 'API یادآور در افزونهٔ پرونده در دسترس نیست.', 'peyvand-amn-dashboard' ) );
		}

		$next    = Pas_CF_Case_File::get_next_session( $case );
		$blocker = (string) Pas_CF_Case_File::reminder_blocker( $case, $next );

		if ( 'ok' !== $blocker ) {
			return new WP_Error( 'pas_dashboard_reminder_blocked', self::blocker_message( $blocker ) );
		}

		$name    = (string) ( $case->full_name ?? '' ) !== '' ? (string) $case->full_name : __( 'مراجع گرامی', 'peyvand-amn-dashboard' );
		$jalali  = PAS_Dashboard_Core_Bridge::jalali( (string) $next->next_session_date );
		$channel = (string) ( $case->reminder_channel ?? 'sms' );
		$status  = 'failed';

		if ( 'email' === $channel ) {
			$user = method_exists( 'Pas_CF_Case_File', 'find_linked_wp_user' )
				? Pas_CF_Case_File::find_linked_wp_user( (string) $case->phone )
				: null;

			if ( $user && is_email( $user->user_email ) ) {
				$body  = sprintf(
					/* translators: 1: name, 2: date */
					__( '%1$s، یادآوری جلسهٔ شما در تاریخ %2$s است. — پیوند امن', 'peyvand-amn-dashboard' ),
					$name,
					$jalali
				);
				$status = wp_mail( $user->user_email, __( 'یادآوری جلسه', 'peyvand-amn-dashboard' ), $body ) ? 'sent' : 'failed';
			}
		} else {
			$pattern = (string) apply_filters(
				'pas_cf_reminder_sms_pattern',
				(string) get_option( 'pas_cf_reminder_sms_pattern', '' )
			);

			if ( '' === $pattern || ! has_filter( 'pas_send_sms' ) ) {
				$status = 'sms_not_configured';
			} else {
				$sent   = apply_filters(
					'pas_send_sms',
					null,
					(string) $case->phone,
					$pattern,
					array(
						'NAME' => $name,
						'DATE' => $jalali,
					)
				);
				$status = false === $sent ? 'failed' : 'sent';
			}
		}

		if ( 'sent' !== $status ) {
			return new WP_Error( 'pas_dashboard_reminder_failed', self::reminder_message( $status ) );
		}

		Pas_CF_Case_File::mark_reminder_sent( $case_id );

		return array(
			'ok'      => true,
			'message' => sprintf(
				/* translators: 1: channel, 2: date */
				__( 'یادآور جلسه از طریق %1$s برای تاریخ %2$s ارسال شد.', 'peyvand-amn-dashboard' ),
				'email' === $channel ? __( 'ایمیل', 'peyvand-amn-dashboard' ) : __( 'پیامک', 'peyvand-amn-dashboard' ),
				$jalali
			),
			'id'      => $case_id,
		);
	}

	public static function statuses() {
		return self::STATUSES;
	}

	public static function encrypted_fields() {
		return self::ENCRYPTED_FIELDS;
	}

	public static function plain_fields() {
		return self::PLAIN_FIELDS;
	}

	public static function blocker_label( $blocker ) {
		$labels = array(
			'ok'         => __( 'آمادهٔ ارسال', 'peyvand-amn-dashboard' ),
			'no_consent' => __( 'رضایت یادآور ثبت نشده', 'peyvand-amn-dashboard' ),
			'no_session' => __( 'جلسهٔ بعدی ثبت نشده', 'peyvand-amn-dashboard' ),
			'no_channel' => __( 'کانال یادآور تنظیم نشده', 'peyvand-amn-dashboard' ),
			'cooldown'   => __( 'در دورهٔ انتظار ارسال', 'peyvand-amn-dashboard' ),
		);

		return $labels[ $blocker ] ?? __( 'نامشخص', 'peyvand-amn-dashboard' );
	}

	private static function blocker_message( $blocker ) {
		switch ( $blocker ) {
			case 'no_consent':
				return __( 'مراجع رضایت دریافت یادآور را ثبت نکرده است.', 'peyvand-amn-dashboard' );
			case 'no_session':
				return __( 'برای این پرونده جلسهٔ بعدی ثبت نشده است.', 'peyvand-amn-dashboard' );
			case 'no_channel':
				return __( 'کانال یادآور (پیامک/ایمیل) تنظیم نشده است.', 'peyvand-amn-dashboard' );
			case 'cooldown':
				return __( 'یادآور این جلسه به‌تازگی ارسال شده است؛ تا پایان دورهٔ انتظار صبر کنید.', 'peyvand-amn-dashboard' );
			default:
				return __( 'ارسال یادآور در وضعیت فعلی مجاز نیست.', 'peyvand-amn-dashboard' );
		}
	}

	private static function reminder_message( $status ) {
		if ( 'sms_not_configured' === $status ) {
			return __( 'الگوی پیامک یادآور یا پل پیامک تنظیم نشده است.', 'peyvand-amn-dashboard' );
		}
		if ( 'failed' === $status ) {
			return __( 'ارسال یادآور ناموفق بود؛ لاگ پیامک/ایمیل را بررسی کنید.', 'peyvand-amn-dashboard' );
		}

		return __( 'ارسال یادآور ناموفق بود.', 'peyvand-amn-dashboard' );
	}

	private static function collect_fields( array $post ) {
		$data = array();

		foreach ( self::PLAIN_FIELDS as $field ) {
			$value        = isset( $post[ $field ] ) ? wp_unslash( $post[ $field ] ) : '';
			$data[ $field ] = in_array( $field, array( 'main_conflict_topics', 'q_main_reason', 'q_positive_negative_traits', 'q_conflict_resolution', 'q_reason_staying', 'q_therapy_goals', 'prior_therapy', 'failed_marriage_history', 'spouse_failed_marriage_history' ), true )
				? PAS_Dashboard_Validation::textarea( $value, 5000 )
				: PAS_Dashboard_Validation::text( $value, 190 );
		}

		foreach ( self::ENCRYPTED_FIELDS as $field ) {
			$data[ $field ] = PAS_Dashboard_Validation::textarea( $post[ $field ] ?? '', 20000 );
		}

		return $data;
	}

	private static function valid_therapist( $value ) {
		$user_id = PAS_Dashboard_Validation::id( $value );

		if ( $user_id <= 0 || ! get_userdata( $user_id ) ) {
			return 0;
		}

		// فقط کاربری که واقعاً مجوز پرونده دارد (یا مدیر است) قابل تخصیص است.
		if ( defined( 'PAS_CF_CAP' ) && ! user_can( $user_id, PAS_CF_CAP ) && ! user_can( $user_id, 'manage_options' ) ) {
			return 0;
		}

		return $user_id;
	}

	/** @return string|WP_Error */
	private static function normalize_phone( $value, $required = true ) {
		$raw = PAS_Dashboard_Validation::text( $value, 30 );

		if ( '' === $raw ) {
			if ( ! $required ) {
				return '';
			}
			return new WP_Error( 'pas_dashboard_case_phone', __( 'شمارهٔ موبایل مراجع الزامی است.', 'peyvand-amn-dashboard' ) );
		}

		$phone = method_exists( 'Pas_CF_Case_File', 'normalize_phone' )
			? (string) Pas_CF_Case_File::normalize_phone( $raw )
			: preg_replace( '/\D+/', '', $raw );

		if ( ! preg_match( '/^09\d{9}$/', $phone ) ) {
			return new WP_Error( 'pas_dashboard_case_phone', __( 'شمارهٔ موبایل معتبر نیست (نمونه: 09123456789).', 'peyvand-amn-dashboard' ) );
		}

		return $phone;
	}
}
