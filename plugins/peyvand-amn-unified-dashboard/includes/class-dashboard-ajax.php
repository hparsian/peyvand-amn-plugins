<?php
/**
 * AJAX — هر اکشن nonce، کپابیلیتی و مجوز منبع مستقل خودش را بررسی می‌کند.
 *
 * هیچ اکشن nopriv ثبت نمی‌شود. هر اکشن ورودی خودش را اعتبارسنجی می‌کند و
 * خطاها عمومی هستند (بدون افشای داده‌ی پرونده).
 */

defined( 'ABSPATH' ) || exit;

final class PAS_Dashboard_Ajax {

	public static function init() {
		add_action( 'wp_ajax_pas_dashboard_save_note', array( __CLASS__, 'save_note' ) );
		add_action( 'wp_ajax_pas_dashboard_delete_note', array( __CLASS__, 'delete_note' ) );
		add_action( 'wp_ajax_pas_dashboard_restore_note', array( __CLASS__, 'restore_note' ) );
		add_action( 'wp_ajax_pas_dashboard_mark_notifications_read', array( __CLASS__, 'mark_notifications_read' ) );
		add_action( 'wp_ajax_pas_dashboard_update_plugin', array( __CLASS__, 'update_plugin' ) );
	}

	/**
	 * بررسی‌های مشترک: ورود، nonce اختصاصی اکشن، دسترسی داشبورد.
	 */
	private static function guard( $nonce_action ) {
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'ابتدا وارد شوید.', 'peyvand-amn-dashboard' ) ), 401 );
		}

		if ( ! PAS_Dashboard_Security::verify_nonce( $nonce_action, 'nonce' ) ) {
			PAS_Dashboard_Security::record_denied( 'ajax.' . $nonce_action, 'ajax', 0 );
			wp_send_json_error( array( 'message' => __( 'توکن امنیتی نامعتبر است.', 'peyvand-amn-dashboard' ) ), 403 );
		}

		if ( ! PAS_Dashboard_Security::can_access_dashboard() ) {
			PAS_Dashboard_Security::record_denied( 'ajax.' . $nonce_action, 'ajax', 0 );
			wp_send_json_error( array( 'message' => __( 'دسترسی غیرمجاز.', 'peyvand-amn-dashboard' ) ), 403 );
		}
	}

	private static function post( $key, $default = '' ) {
		// phpcs:ignore WordPress.Security.NonceVerification,WordPress.Security.ValidatedSanitizedInput -- guard() verifies the nonce before any handler runs and every handler sends this value through PAS_Dashboard_Validation.
		return isset( $_POST[ $key ] ) ? wp_unslash( $_POST[ $key ] ) : $default;
	}

	/* ── یادداشت: ثبت/ویرایش ───────────────────────────────────────────── */

	public static function save_note() {
		self::guard( 'note_save' );

		if ( ! PAS_Dashboard_Security::notes_writable() ) {
			PAS_Dashboard_Security::record_denied( 'note.write', 'note', 0 );
			wp_send_json_error( array( 'message' => __( 'ثبت یادداشت بالینی در حال حاضر مجاز نیست (کلید رمزنگاری یا دسترسی).', 'peyvand-amn-dashboard' ) ), 403 );
		}

		$case_id  = PAS_Dashboard_Validation::id( self::post( 'case_id' ) );
		$note_id  = PAS_Dashboard_Validation::id( self::post( 'note_id' ) );
		/* فیلد تاریخ شمسی است؛ ابتدا مسیر میلادی (سازگاری با کلاینت قدیمی)
		 * و بعد تبدیل شمسی → میلادی. ورودی نامعتبر خالی می‌ماند و پیام
		 * الزامی‌بودن تاریخ برمی‌گردد. */
		$raw_date = trim( (string) self::post( 'session_date' ) );
		$date     = PAS_Dashboard_Validation::date( $raw_date );
		if ( $date === '' ) {
			$converted = PAS_Dashboard_Core_Bridge::to_gregorian( $raw_date );
			if ( is_string( $converted ) && $converted !== '' ) {
				$date = PAS_Dashboard_Validation::date( $converted );
			}
		}
		$content  = PAS_Dashboard_Validation::textarea( self::post( 'content' ) );
		$number   = absint( self::post( 'session_number' ) );

		$case = PAS_Dashboard_Security::authorized_case( $case_id );
		if ( is_wp_error( $case ) ) {
			PAS_Dashboard_Security::record_denied( 'note.write', 'note', $note_id );
			wp_send_json_error( array( 'message' => $case->get_error_message() ), 403 );
		}

		if ( $date === '' || $content === '' ) {
			wp_send_json_error( array( 'message' => __( 'تاریخ جلسه (شمسی) و متن یادداشت الزامی است.', 'peyvand-amn-dashboard' ) ), 400 );
		}

		if ( $note_id > 0 ) {
			$ok = PAS_Dashboard_Core_Bridge::update_note( $note_id, $case_id, $date, $content, $number );

			if ( ! $ok ) {
				PAS_Dashboard_Security::record_denied( 'note.update', 'note', $note_id );
				wp_send_json_error( array( 'message' => __( 'ویرایش یادداشت ناموفق بود یا یادداشت به این پرونده تعلق ندارد.', 'peyvand-amn-dashboard' ) ), 403 );
			}

			PAS_Dashboard_Audit_Log::record( 'note.update', 'note', $note_id, 'ok' );
			wp_send_json_success( array( 'message' => __( 'یادداشت به‌روزرسانی شد.', 'peyvand-amn-dashboard' ), 'note_id' => $note_id ) );
		}

		$result = PAS_Dashboard_Core_Bridge::add_note( $case_id, $date, $content, $number );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ), 400 );
		}

		PAS_Dashboard_Audit_Log::record( 'note.create', 'note', (int) $result, 'ok' );
		wp_send_json_success( array( 'message' => __( 'یادداشت ثبت شد.', 'peyvand-amn-dashboard' ), 'note_id' => (int) $result ) );
	}

	/* ── یادداشت: حذف (با snapshot در تاریخچه توسط API هسته) ───────────── */

	public static function delete_note() {
		self::guard( 'note_delete' );

		if ( ! PAS_Dashboard_Security::notes_writable() ) {
			PAS_Dashboard_Security::record_denied( 'note.delete', 'note', 0 );
			wp_send_json_error( array( 'message' => __( 'حذف یادداشت در وضعیت فعلی مجاز نیست.', 'peyvand-amn-dashboard' ) ), 403 );
		}

		$case_id = PAS_Dashboard_Validation::id( self::post( 'case_id' ) );
		$note_id = PAS_Dashboard_Validation::id( self::post( 'note_id' ) );

		$case = PAS_Dashboard_Security::authorized_case( $case_id );
		if ( is_wp_error( $case ) ) {
			PAS_Dashboard_Security::record_denied( 'note.delete', 'note', $note_id );
			wp_send_json_error( array( 'message' => $case->get_error_message() ), 403 );
		}

		if ( $note_id <= 0 ) {
			wp_send_json_error( array( 'message' => __( 'شناسه‌ی یادداشت نامعتبر است.', 'peyvand-amn-dashboard' ) ), 400 );
		}

		$ok = PAS_Dashboard_Core_Bridge::delete_note( $note_id, $case_id );

		if ( ! $ok ) {
			PAS_Dashboard_Security::record_denied( 'note.delete', 'note', $note_id );
			wp_send_json_error( array( 'message' => __( 'حذف ناموفق بود یا یادداشت به این پرونده تعلق ندارد.', 'peyvand-amn-dashboard' ) ), 403 );
		}

		PAS_Dashboard_Audit_Log::record( 'note.delete', 'note', $note_id, 'ok' );
		wp_send_json_success( array( 'message' => __( 'یادداشت حذف شد (نسخه‌ی قبلی در تاریخچه باقی است).', 'peyvand-amn-dashboard' ) ) );
	}

	/* ── یادداشت: بازگردانی نسخه ───────────────────────────────────────── */

	public static function restore_note() {
		self::guard( 'note_restore' );

		if ( ! PAS_Dashboard_Security::notes_writable() ) {
			PAS_Dashboard_Security::record_denied( 'note.restore', 'note', 0 );
			wp_send_json_error( array( 'message' => __( 'بازگردانی نسخه در وضعیت فعلی مجاز نیست.', 'peyvand-amn-dashboard' ) ), 403 );
		}

		$case_id    = PAS_Dashboard_Validation::id( self::post( 'case_id' ) );
		$history_id = PAS_Dashboard_Validation::id( self::post( 'history_id' ) );

		$case = PAS_Dashboard_Security::authorized_case( $case_id );
		if ( is_wp_error( $case ) ) {
			PAS_Dashboard_Security::record_denied( 'note.restore', 'note', $history_id );
			wp_send_json_error( array( 'message' => $case->get_error_message() ), 403 );
		}

		if ( $history_id <= 0 ) {
			wp_send_json_error( array( 'message' => __( 'شناسه‌ی نسخه نامعتبر است.', 'peyvand-amn-dashboard' ) ), 400 );
		}

		$ok = PAS_Dashboard_Core_Bridge::restore_note_version( $history_id, $case_id );

		if ( ! $ok ) {
			PAS_Dashboard_Security::record_denied( 'note.restore', 'note', $history_id );
			wp_send_json_error( array( 'message' => __( 'بازگردانی نسخه ناموفق بود.', 'peyvand-amn-dashboard' ) ), 403 );
		}

		PAS_Dashboard_Audit_Log::record( 'note.restore', 'note', $history_id, 'ok' );
		wp_send_json_success( array( 'message' => __( 'نسخه بازگردانی شد.', 'peyvand-amn-dashboard' ) ) );
	}

	/* ── اعلان‌ها ───────────────────────────────────────────────────────── */

	public static function mark_notifications_read() {
		self::guard( 'notifications_read' );

		$user_id = get_current_user_id();
		$id      = PAS_Dashboard_Validation::id( self::post( 'notification_id' ) );

		if ( $id > 0 ) {
			$ok = PAS_Dashboard_Notifications::mark_read( $id, $user_id );
			if ( ! $ok ) {
				wp_send_json_error( array( 'message' => __( 'اعلان یافت نشد.', 'peyvand-amn-dashboard' ) ), 404 );
			}
		} else {
			PAS_Dashboard_Notifications::mark_all_read( $user_id );
		}

		wp_send_json_success( array( 'unread' => PAS_Dashboard_Notifications::unread_count( $user_id ) ) );
	}

	/* ── به‌روزرسانی یک افزونهٔ اکوسیستم ──────────────────────────────── */

	/**
	 * چرا AJAX و نه یک فرم کامل؟ آپدیت چند بسته در یک درخواست می‌تواند به
	 * سقف زمان اجرای PHP بخورد و پاسخ نیمه‌کاره برگرداند (همان صفحهٔ سفیدی که
	 * مدیر بعد از زدن «به‌روزرسانی» می‌دید). اینجا هر درخواست فقط یک بسته را
	 * آپدیت می‌کند؛ پاسخ همیشه JSON است و خطا به‌جای قطع‌شدن صفحه، پیام
	 * قابل خواندن دارد. کلید و کپابیلیتی از همان Action Bus می‌آید.
	 */
	public static function update_plugin() {
		self::guard( 'updates_run' );

		if ( ! current_user_can( 'manage_options' ) || ! current_user_can( 'update_plugins' ) ) {
			PAS_Dashboard_Security::record_denied( 'updates.run', 'plugin', 0 );
			wp_send_json_error( array( 'message' => __( 'دسترسی به به‌روزرسانی افزونه‌ها برای شما فعال نیست.', 'peyvand-amn-dashboard' ) ), 403 );
		}

		$slug = sanitize_key( (string) self::post( 'slug' ) );

		// خروجی/هشدار احتمالی آپ‌گریدر نباید JSON را خراب کند.
		ob_start();
		$result = PAS_Dashboard_Updater::run( array( 'slug' => $slug ) );
		ob_end_clean();

		$ok = ! empty( $result['ok'] );

		PAS_Dashboard_Audit_Log::record( 'updates.run', 'plugin', 0, $ok ? 'ok' : 'error' );

		$data = array(
			'slug'    => $slug,
			'message' => (string) ( $result['message'] ?? '' ),
			'state'   => $ok ? 'current' : 'error',
		);

		if ( ! $ok ) {
			wp_send_json_error( $data );
		}

		wp_send_json_success( $data );
	}
}
