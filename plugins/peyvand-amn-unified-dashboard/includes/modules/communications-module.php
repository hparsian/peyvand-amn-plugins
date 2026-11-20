<?php
/**
 * ماژول ۷ — ارتباطات: صندوق پیام مراجعان، پیامک الگومحور و ایمیل/صف SMTP.
 *
 * هیچ متن آزادی ساخته نمی‌شود: پیامک فقط با الگوهای تأییدشدهٔ پل پیامک
 * (`Pas_Patterns`) و ایمیل فقط با تنظیمات و صف افزونهٔ SMTP ارسال می‌شود.
 * نوشتن از Action Bus می‌گذرد (nonce + کپابیلیتی + دامنه + ممیزی + PRG).
 */

defined( 'ABSPATH' ) || exit;

final class PAS_Dashboard_Module_Communications extends PAS_Dashboard_Module {

	public function __construct() {
		$this->id          = 'communications';
		$this->title       = __( 'ارتباطات', 'peyvand-amn-dashboard' );
		$this->description = __( 'صندوق پیام مراجعان، پیامک الگومحور، ایمیل و صف ارسال.', 'peyvand-amn-dashboard' );
	}

	public function render() {
		$html  = $this->render_messages();
		$html .= $this->render_sms();
		$html .= $this->render_email();
		$html .= $this->notice( 'info', __( 'همهٔ ارسال‌ها فقط از مسیرهای تأییدشده انجام می‌شوند: پیامک با الگوهای ثبت‌شده در پل پیامک و ایمیل با تنظیمات SMTP. متن آزاد پیامک عمداً وجود ندارد و در محیط توسعه هیچ پیامک یا ایمیل واقعی ارسال نمی‌شود.', 'peyvand-amn-dashboard' ) );

		return $html;
	}

	/** پیامک الگومحور + لاگ تلاش‌ها. */
	private function render_sms() {
		$available = PAS_Dashboard_Adapter_Sms::available();
		$patterns  = PAS_Dashboard_Adapter_Sms::patterns();

		$html = $this->card_open( __( 'ارسال پیامک الگومحور', 'peyvand-amn-dashboard' ) );

		if ( ! $available ) {
			$html .= $this->notice( 'warning', __( 'قرارداد الگوی پیامک در افزونهٔ پل پیامک در دسترس نیست (نسخهٔ ۱.۱.۹+).', 'peyvand-amn-dashboard' ) );
			$html .= $this->card_close();
			$html .= $this->render_sms_log();

			return $html;
		}

		$filter_ready = PAS_Dashboard_Core_Bridge::sms_available();

		$html .= '<p>' . esc_html__( 'وضعیت پل:', 'peyvand-amn-dashboard' ) . ' ';
		$html .= $filter_ready
			? '<span class="pas-badge pas-badge-good">' . esc_html__( 'فیلتر pas_send_sms ثبت شده است', 'peyvand-amn-dashboard' ) . '</span>'
			: '<span class="pas-badge pas-badge-warn">' . esc_html__( 'غیرفعال', 'peyvand-amn-dashboard' ) . '</span>';
		$html .= '</p>';

		if ( empty( $patterns ) ) {
			$html .= '<p class="pas-empty">' . esc_html__( 'هیچ الگوی تأییدشده‌ای برای ارسال از داشبورد تنظیم نشده است. ابتدا bodyId الگوها را در پنل ملی‌پیامک و تنظیمات افزونهٔ مالک ثبت کنید.', 'peyvand-amn-dashboard' ) . '</p>';
			$html .= $this->card_close();
			$html .= $this->render_sms_log();

			return $html;
		}

		$html .= '<p class="pas-muted">' . esc_html__( 'متن پیامک از الگوی تأییدشده می‌آید؛ فقط مقادیر جای‌نگهدارهای همان الگو را وارد کنید. شماره باید به یکی از مراجعان پرونده‌های شما تعلق داشته باشد.', 'peyvand-amn-dashboard' ) . '</p>';
		$html .= '<p class="pas-muted">' . esc_html__( 'سقف ارسال: ۳۰ پیامک در ساعت برای هر کاربر.', 'peyvand-amn-dashboard' ) . '</p>';

		$html .= '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="pas-form">';
		$html .= PAS_Dashboard_Actions::fields( 'sms.send', $this->url() );

		$html .= '<p><label for="pas-sms-pattern">' . esc_html__( 'الگوی پیامک', 'peyvand-amn-dashboard' ) . '</label><br />';
		$html .= '<select id="pas-sms-pattern" name="pattern" required>';

		foreach ( $patterns as $slug => $pattern ) {
			$html .= '<option value="' . esc_attr( (string) $slug ) . '">' . esc_html( (string) ( $pattern['label'] ?? $slug ) ) . '</option>';
		}

		$html .= '</select></p>';

		$html .= '<p><label for="pas-sms-mobile">' . esc_html__( 'شمارهٔ موبایل مراجع', 'peyvand-amn-dashboard' ) . '</label><br />';
		$html .= '<input class="pas-input" dir="ltr" id="pas-sms-mobile" name="mobile" type="tel" inputmode="numeric" placeholder="09xxxxxxxxx" required /></p>';

		for ( $i = 1; $i <= 3; $i++ ) {
			$html .= '<p><label for="pas-sms-param-' . esc_attr( (string) $i ) . '">' . esc_html( sprintf(
				/* translators: %s: placeholder number */
				__( 'مقدار جای‌نگهدار %s (اختیاری)', 'peyvand-amn-dashboard' ),
				(string) $i
			) ) . '</label><br />';
			$html .= '<input class="pas-input" id="pas-sms-param-' . esc_attr( (string) $i ) . '" name="param' . esc_attr( (string) $i ) . '" type="text" maxlength="80" /></p>';
		}

		$html .= '<p><button class="pas-btn pas-btn-primary" type="submit">' . esc_html__( 'ارسال پیامک', 'peyvand-amn-dashboard' ) . '</button></p>';
		$html .= '</form>';
		$html .= $this->card_close();

		$html .= $this->render_sms_log();

		return $html;
	}

	/** لاگ ارسال پیامک — فقط‌خواندنی. */
	private function render_sms_log() {
		$html = $this->card_open( __( 'آخرین تلاش‌های ارسال پیامک', 'peyvand-amn-dashboard' ) );

		if ( ! PAS_Dashboard_Adapter_Sms::log_available() ) {
			$html .= '<p class="pas-empty">' . esc_html__( 'لاگ پیامک در دسترس نیست (افزونهٔ پل پیامک فعال نیست).', 'peyvand-amn-dashboard' ) . '</p>';
			$html .= $this->card_close();

			return $html;
		}

		$rows = PAS_Dashboard_Adapter_Sms::recent( 30 );

		if ( empty( $rows ) ) {
			$html .= '<p class="pas-empty">' . esc_html__( 'لاگی ثبت نشده است.', 'peyvand-amn-dashboard' ) . '</p>';
			$html .= $this->card_close();

			return $html;
		}

		$html .= '<div class="pas-table-wrap"><table class="pas-table"><thead><tr>';
		$html .= '<th>' . esc_html__( 'زمان', 'peyvand-amn-dashboard' ) . '</th>';
		$html .= '<th>' . esc_html__( 'گیرنده', 'peyvand-amn-dashboard' ) . '</th>';
		$html .= '<th>' . esc_html__( 'الگو', 'peyvand-amn-dashboard' ) . '</th>';
		$html .= '<th>' . esc_html__( 'وضعیت', 'peyvand-amn-dashboard' ) . '</th>';
		$html .= '<th>' . esc_html__( 'علت', 'peyvand-amn-dashboard' ) . '</th>';
		$html .= '</tr></thead><tbody>';

		foreach ( $rows as $row ) {
			$success = ! empty( $row->success );
			$label   = PAS_Dashboard_Adapter_Sms::pattern_label( (int) ( $row->body_id ?? 0 ) );

			$html .= '<tr>';
			$html .= '<td data-label="' . esc_attr__( 'زمان', 'peyvand-amn-dashboard' ) . '">' . esc_html( PAS_Dashboard_Core_Bridge::jalali_datetime( (string) ( $row->created_at ?? '' ) ) ) . '</td>';
			$html .= '<td data-label="' . esc_attr__( 'گیرنده', 'peyvand-amn-dashboard' ) . '"><span dir="ltr">' . esc_html( PAS_Dashboard_Core_Bridge::mask_mobile( (string) ( $row->mobile ?? '' ) ) ) . '</span></td>';
			$html .= '<td data-label="' . esc_attr__( 'الگو', 'peyvand-amn-dashboard' ) . '">' . esc_html( $label !== '' ? $label : '#' . (string) (int) ( $row->body_id ?? 0 ) ) . '</td>';
			$html .= '<td data-label="' . esc_attr__( 'وضعیت', 'peyvand-amn-dashboard' ) . '">' . ( $success
				? '<span class="pas-badge pas-badge-good">' . esc_html__( 'موفق', 'peyvand-amn-dashboard' ) . '</span>'
				: '<span class="pas-badge pas-badge-danger">' . esc_html__( 'ناموفق', 'peyvand-amn-dashboard' ) . '</span>' ) . '</td>';
			$html .= '<td data-label="' . esc_attr__( 'علت', 'peyvand-amn-dashboard' ) . '">' . esc_html( mb_strimwidth( (string) ( $row->reason ?? '' ), 0, 80, '…' ) ) . '</td>';
			$html .= '</tr>';
		}

		$html .= '</tbody></table></div>';
		$html .= $this->card_close();

		return $html;
	}

	/** ایمیل، تست SMTP و صف ارسال — فقط برای مدیر. */
	private function render_email() {
		$can_manage = defined( 'PAS_DASHBOARD_CAP_ECOSYSTEM' ) && current_user_can( PAS_DASHBOARD_CAP_ECOSYSTEM );

		if ( ! PAS_Dashboard_Adapter_Smtp::available() ) {
			$html  = $this->card_open( __( 'ایمیل و صف ارسال', 'peyvand-amn-dashboard' ) );
			$html .= $this->notice( 'warning', __( 'قرارداد SMTP در افزونهٔ ارسال ایمیل در دسترس نیست (نسخهٔ ۱.۵.۶+).', 'peyvand-amn-dashboard' ) );
			$html .= $this->card_close();

			return $html;
		}

		if ( ! $can_manage ) {
			$html  = $this->card_open( __( 'ایمیل و صف ارسال', 'peyvand-amn-dashboard' ) );
			$html .= '<p class="pas-muted">' . esc_html__( 'وضعیت تنظیمات ایمیل، تست ارسال و صف تلاش دوباره فقط برای مدیر نمایش داده می‌شود.', 'peyvand-amn-dashboard' ) . '</p>';
			$html .= $this->card_close();

			return $html;
		}

		$status  = PAS_Dashboard_Adapter_Smtp::status();
		$summary = PAS_Dashboard_Adapter_Smtp::summary();
		$queue   = PAS_Dashboard_Adapter_Smtp::queue( 30 );

		$html  = $this->card_open( __( 'ایمیل و صف ارسال', 'peyvand-amn-dashboard' ) );

		if ( is_array( $status ) ) {
			$method = 'brevo' === (string) ( $status['send_method'] ?? 'smtp' ) ? __( 'Brevo API', 'peyvand-amn-dashboard' ) : __( 'SMTP', 'peyvand-amn-dashboard' );

			$html .= '<p>' . esc_html__( 'روش ارسال:', 'peyvand-amn-dashboard' ) . ' <strong>' . esc_html( $method ) . '</strong> — ';
			$html .= esc_html__( 'فرستنده:', 'peyvand-amn-dashboard' ) . ' <span dir="ltr">' . esc_html( (string) ( $status['from_email'] ?? '' ) ) . '</span> — ';
			$html .= ! empty( $status['configured'] )
				? '<span class="pas-badge pas-badge-good">' . esc_html__( 'تنظیم‌شده', 'peyvand-amn-dashboard' ) . '</span>'
				: '<span class="pas-badge pas-badge-warn">' . esc_html__( 'تنظیم نشده', 'peyvand-amn-dashboard' ) . '</span>';
			$html .= '</p>';
		}

		$html .= '<div class="pas-grid">';
		$html .= $this->stat( (string) (int) ( $summary['pending'] ?? 0 ), __( 'در انتظار ارسال', 'peyvand-amn-dashboard' ) );
		$html .= $this->stat( (string) (int) ( $summary['sent'] ?? 0 ), __( 'ارسال‌شده', 'peyvand-amn-dashboard' ) );
		$html .= $this->stat( (string) (int) ( $summary['given_up'] ?? 0 ), __( 'ناموفق نهایی', 'peyvand-amn-dashboard' ) );
		$html .= '</div>';

		$html .= '<h3>' . esc_html__( 'تست ارسال', 'peyvand-amn-dashboard' ) . '</h3>';
		$html .= '<p class="pas-muted">' . esc_html__( 'یک ایمیل واقعی با تنظیمات فعلی فرستاده می‌شود تا مسیر ارسال سنجیده شود. سقف: ۱۰ تست در ساعت.', 'peyvand-amn-dashboard' ) . '</p>';

		$html .= '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="pas-inline-form">';
		$html .= PAS_Dashboard_Actions::fields( 'email.test', $this->url() );
		$html .= '<label class="screen-reader-text" for="pas-email-test">' . esc_html__( 'ایمیل گیرندهٔ تست', 'peyvand-amn-dashboard' ) . '</label>';
		$html .= '<input class="pas-input" dir="ltr" id="pas-email-test" name="email" type="email" value="' . esc_attr( (string) get_option( 'admin_email', '' ) ) . '" required />';
		$html .= '<button class="pas-btn pas-btn-sm" type="submit">' . esc_html__( 'ارسال ایمیل تست', 'peyvand-amn-dashboard' ) . '</button>';
		$html .= '</form>';

		$html .= '<h3>' . esc_html__( 'صف و لاگ ایمیل', 'peyvand-amn-dashboard' ) . '</h3>';

		if ( empty( $queue ) ) {
			$html .= '<p class="pas-empty">' . esc_html__( 'ردیفی در صف ایمیل نیست.', 'peyvand-amn-dashboard' ) . '</p>';
			$html .= $this->card_close();

			return $html;
		}

		$status_labels = array(
			'pending'  => __( 'در انتظار تلاش دوباره', 'peyvand-amn-dashboard' ),
			'sent'     => __( 'ارسال‌شده', 'peyvand-amn-dashboard' ),
			'given_up' => __( 'ناموفق نهایی', 'peyvand-amn-dashboard' ),
		);

		$html .= '<div class="pas-table-wrap"><table class="pas-table"><thead><tr>';
		$html .= '<th>' . esc_html__( 'زمان', 'peyvand-amn-dashboard' ) . '</th>';
		$html .= '<th>' . esc_html__( 'گیرنده', 'peyvand-amn-dashboard' ) . '</th>';
		$html .= '<th>' . esc_html__( 'موضوع', 'peyvand-amn-dashboard' ) . '</th>';
		$html .= '<th>' . esc_html__( 'وضعیت', 'peyvand-amn-dashboard' ) . '</th>';
		$html .= '<th>' . esc_html__( 'تلاش', 'peyvand-amn-dashboard' ) . '</th>';
		$html .= '<th>' . esc_html__( 'آخرین خطا', 'peyvand-amn-dashboard' ) . '</th>';
		$html .= '<th>' . esc_html__( 'عملیات', 'peyvand-amn-dashboard' ) . '</th>';
		$html .= '</tr></thead><tbody>';

		foreach ( $queue as $row ) {
			$id       = (int) ( $row->id ?? 0 );
			$state    = (string) ( $row->status ?? '' );
			$retryable = in_array( $state, array( 'pending', 'given_up' ), true );

			$html .= '<tr>';
			$html .= '<td data-label="' . esc_attr__( 'زمان', 'peyvand-amn-dashboard' ) . '">' . esc_html( PAS_Dashboard_Core_Bridge::jalali_datetime( (string) ( $row->created_at ?? '' ) ) ) . '</td>';
			$html .= '<td data-label="' . esc_attr__( 'گیرنده', 'peyvand-amn-dashboard' ) . '"><span dir="ltr">' . esc_html( self::mask_email( (string) ( $row->to_address ?? '' ) ) ) . '</span></td>';
			$html .= '<td data-label="' . esc_attr__( 'موضوع', 'peyvand-amn-dashboard' ) . '">' . esc_html( mb_strimwidth( (string) ( $row->subject ?? '' ), 0, 60, '…' ) ) . '</td>';
			$html .= '<td data-label="' . esc_attr__( 'وضعیت', 'peyvand-amn-dashboard' ) . '">' . esc_html( $status_labels[ $state ] ?? $state ) . '</td>';
			$html .= '<td data-label="' . esc_attr__( 'تلاش', 'peyvand-amn-dashboard' ) . '">' . esc_html( (string) (int) ( $row->attempts ?? 0 ) ) . '</td>';
			$html .= '<td data-label="' . esc_attr__( 'آخرین خطا', 'peyvand-amn-dashboard' ) . '">' . esc_html( mb_strimwidth( (string) ( $row->last_error ?? '' ), 0, 80, '…' ) ) . '</td>';

			$html .= '<td data-label="' . esc_attr__( 'عملیات', 'peyvand-amn-dashboard' ) . '">';

			if ( $retryable && $id > 0 ) {
				$html .= '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="pas-inline-form">';
				$html .= PAS_Dashboard_Actions::fields( 'email.queue.retry', $this->url() );
				$html .= '<input type="hidden" name="queue_id" value="' . esc_attr( (string) $id ) . '" />';
				$html .= '<button class="pas-btn pas-btn-sm" type="submit">' . esc_html__( 'تلاش دوباره', 'peyvand-amn-dashboard' ) . '</button>';
				$html .= '</form>';
			} else {
				$html .= '<span class="pas-muted">—</span>';
			}

			$html .= '</td>';
			$html .= '</tr>';
		}

		$html .= '</tbody></table></div>';
		$html .= '<p class="pas-muted">' . esc_html__( 'حداکثر ۳۰ ردیف آخر نمایش داده می‌شود؛ متن کامل ایمیل‌ها ذخیره و در این صفحه نمایش داده نمی‌شود.', 'peyvand-amn-dashboard' ) . '</p>';
		$html .= $this->card_close();

		return $html;
	}

	/** ماسک آدرس ایمیل در فهرست: حرف اول و دامنه می‌مانند. */
	private static function mask_email( $email ) {
		$email = (string) $email;

		if ( strpos( $email, '@' ) === false ) {
			return $email;
		}

		list( $local, $domain ) = explode( '@', $email, 2 );

		if ( $local === '' ) {
			return $email;
		}

		return mb_substr( $local, 0, 1 ) . str_repeat( '*', max( 2, mb_strlen( $local ) - 1 ) ) . '@' . $domain;
	}

	private function stat( $value, $label ) {
		return '<div class="pas-stat"><span class="pas-stat-value">' . esc_html( (string) $value ) . '</span><span class="pas-stat-label">' . esc_html( (string) $label ) . '</span></div>';
	}

	/** صندوق پیام مراجعان (پنل) و پاسخ ادمین/درمانگر. */
	private function render_messages() {
		if ( ! PAS_Dashboard_Adapter_Portal::messages_available() ) {
			return $this->notice( 'info', __( 'صندوق پیام در دسترس نیست (افزونهٔ پنل مراجعان فعال نیست یا نسخهٔ آن قرارداد پیام را ندارد).', 'peyvand-amn-dashboard' ) );
		}

		$messages = PAS_Dashboard_Adapter_Portal::messages( 100 );
		$unread   = 0;

		foreach ( $messages as $message ) {
			if ( 'خوانده نشده' === (string) ( $message->status ?? '' ) ) {
				$unread++;
			}
		}

		$html  = $this->card_open( __( 'صندوق پیام مراجعان', 'peyvand-amn-dashboard' ) );
		$html .= '<p class="pas-muted">';
		$html .= esc_html( sprintf(
			/* translators: 1: total, 2: unread */
			__( '%1$d پیام، %2$d خوانده‌نشده.', 'peyvand-amn-dashboard' ),
			count( $messages ),
			$unread
		) );
		$html .= '</p>';

		if ( empty( $messages ) ) {
			$html .= '<p class="pas-empty">' . esc_html__( 'پیامی از مراجعان ثبت نشده است.', 'peyvand-amn-dashboard' ) . '</p>';
			$html .= $this->card_close();
			return $html;
		}

		foreach ( array_slice( $messages, 0, 50 ) as $message ) {
			$msg_id   = (int) ( $message->id ?? 0 );
			$status   = (string) ( $message->status ?? '' );
			$is_new   = 'خوانده نشده' === $status;
			$in_scope = PAS_Dashboard_Adapter_Portal::can_view_phone( (string) ( $message->mobile ?? '' ) );

			$html .= '<article class="pas-message-card' . ( $is_new ? ' is-unread' : '' ) . '">';
			$html .= '<header class="pas-message-head">';
			$html .= '<strong dir="ltr">' . esc_html( PAS_Dashboard_Core_Bridge::mask_mobile( (string) ( $message->mobile ?? '' ) ) ) . '</strong>';
			$html .= '<span class="pas-badge ' . ( $is_new ? 'pas-badge-warn' : 'pas-badge-good' ) . '">' . esc_html( $status !== '' ? $status : '—' ) . '</span>';
			$html .= '<span class="pas-muted">' . esc_html( PAS_Dashboard_Core_Bridge::jalali_datetime( (string) ( $message->created_at ?? '' ) ) ) . '</span>';
			$html .= '</header>';
			$html .= '<div class="pas-message-body">' . nl2br( esc_html( (string) ( $message->message_text ?? '' ) ) ) . '</div>';

			if ( ! empty( $message->admin_reply ) ) {
				$html .= '<div class="pas-message-reply"><strong>' . esc_html__( 'پاسخ فعلی:', 'peyvand-amn-dashboard' ) . '</strong> ' . nl2br( esc_html( (string) $message->admin_reply ) ) . '</div>';
			}

			if ( ! $in_scope ) {
				$html .= '<p class="pas-muted">' . esc_html__( 'خارج از دامنهٔ شما — پاسخ فقط برای مراجعان پرونده‌های شما ثبت می‌شود.', 'peyvand-amn-dashboard' ) . '</p>';
			} else {
				$html .= '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="pas-message-form">';
				$html .= PAS_Dashboard_Actions::fields( 'message.reply', $this->url() );
				$html .= '<input type="hidden" name="message_id" value="' . esc_attr( (string) $msg_id ) . '" />';
				$html .= '<label class="screen-reader-text" for="pas-reply-' . esc_attr( (string) $msg_id ) . '">' . esc_html__( 'متن پاسخ', 'peyvand-amn-dashboard' ) . '</label>';
				$html .= '<textarea class="pas-textarea" id="pas-reply-' . esc_attr( (string) $msg_id ) . '" name="reply_text" rows="2" required>' . esc_textarea( (string) ( $message->admin_reply ?? '' ) ) . '</textarea>';
				$html .= '<button class="pas-btn pas-btn-primary pas-btn-sm" type="submit">' . esc_html__( 'ثبت پاسخ', 'peyvand-amn-dashboard' ) . '</button>';
				$html .= '</form>';
			}
			$html .= '</article>';
		}

		if ( count( $messages ) > 50 ) {
			$html .= '<p class="pas-muted">' . esc_html__( 'فقط ۵۰ پیام آخر نمایش داده شده است.', 'peyvand-amn-dashboard' ) . '</p>';
		}

		$html .= $this->card_close();

		return $html;
	}
}
