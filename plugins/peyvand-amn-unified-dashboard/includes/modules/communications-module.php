<?php
/**
 * ماژول ۷ — ارتباطات (پیامک): وضعیت پل، لاگ ارسال و آمادگی یادآور.
 *
 * ارسال پیامک از داشبورد انجام نمی‌شود. قرارداد موجود، فیلتر
 * apply_filters('pas_send_sms', null, $mobile, $body_id, $params) است که به
 * شناسه‌ی متن از پیش تأییدشده نیاز دارد؛ ارسال خودکار بدون آن قرارداد
 * اختراع می‌شد، پس عمداً غیرفعال است.
 */

defined( 'ABSPATH' ) || exit;

final class PAS_Dashboard_Module_Communications extends PAS_Dashboard_Module {

	public function __construct() {
		$this->id          = 'communications';
		$this->title       = __( 'ارتباطات', 'peyvand-amn-dashboard' );
		$this->description = __( 'صندوق پیام مراجعان و پاسخ‌دهی، وضعیت پل پیامک و آمادگی یادآور جلسه.', 'peyvand-amn-dashboard' );
	}

	public function render() {
		$available = PAS_Dashboard_Core_Bridge::sms_available();

		$html  = $this->render_messages();
		$html .= $this->card_open( __( 'پل پیامک', 'peyvand-amn-dashboard' ) );
		$html .= '<p>' . esc_html__( 'وضعیت:', 'peyvand-amn-dashboard' ) . ' ';
		$html .= $available
			? '<span class="pas-badge pas-badge-good">' . esc_html__( 'فیلتر pas_send_sms ثبت شده است', 'peyvand-amn-dashboard' ) . '</span>'
			: '<span class="pas-badge pas-badge-warn">' . esc_html__( 'غیرفعال', 'peyvand-amn-dashboard' ) . '</span>';
		$html .= '</p>';
		$html .= '<p class="pas-muted">' . esc_html__( 'ثبت فیلتر یعنی پیش‌نیاز ارسال فراهم است؛ به‌تنهایی اثبات نمی‌کند که وب‌سرویس پیامک در دسترس است. وضعیت واقعی هر ارسال در لاگ زیر دیده می‌شود.', 'peyvand-amn-dashboard' ) . '</p>';
		$html .= $this->card_close();

		/* لاگ پیامک — فقط‌خواندنی */
		$html .= $this->card_open( __( 'آخرین تلاش‌های ارسال', 'peyvand-amn-dashboard' ) );

		if ( ! PAS_Dashboard_Core_Bridge::sms_log_available() ) {
			$html .= '<p class="pas-empty">' . esc_html__( 'لاگ پیامک در دسترس نیست (افزونه‌ی sms-bridge فعال نیست).', 'peyvand-amn-dashboard' ) . '</p>';
		} else {
			$rows = PAS_Dashboard_Core_Bridge::sms_recent( 30 );

			if ( empty( $rows ) ) {
				$html .= '<p class="pas-empty">' . esc_html__( 'لاگی ثبت نشده است.', 'peyvand-amn-dashboard' ) . '</p>';
			} else {
				$html .= '<div class="pas-table-wrap"><table class="pas-table"><thead><tr>';
				$html .= '<th>' . esc_html__( 'زمان', 'peyvand-amn-dashboard' ) . '</th>';
				$html .= '<th>' . esc_html__( 'گیرنده', 'peyvand-amn-dashboard' ) . '</th>';
				$html .= '<th>' . esc_html__( 'وضعیت', 'peyvand-amn-dashboard' ) . '</th>';
				$html .= '<th>' . esc_html__( 'علت', 'peyvand-amn-dashboard' ) . '</th>';
				$html .= '</tr></thead><tbody>';

				foreach ( $rows as $row ) {
					$success = ! empty( $row->success );
					$html   .= '<tr>';
					$html   .= '<td data-label="' . esc_attr__( 'زمان', 'peyvand-amn-dashboard' ) . '">' . esc_html( PAS_Dashboard_Core_Bridge::jalali_datetime( (string) ( $row->created_at ?? '' ) ) ) . '</td>';
					$html   .= '<td data-label="' . esc_attr__( 'گیرنده', 'peyvand-amn-dashboard' ) . '">' . esc_html( PAS_Dashboard_Core_Bridge::mask_mobile( (string) ( $row->mobile ?? '' ) ) ) . '</td>';
					$html   .= '<td data-label="' . esc_attr__( 'وضعیت', 'peyvand-amn-dashboard' ) . '">' . ( $success
						? '<span class="pas-badge pas-badge-good">' . esc_html__( 'موفق', 'peyvand-amn-dashboard' ) . '</span>'
						: '<span class="pas-badge pas-badge-danger">' . esc_html__( 'ناموفق', 'peyvand-amn-dashboard' ) . '</span>' ) . '</td>';
					$html   .= '<td data-label="' . esc_attr__( 'علت', 'peyvand-amn-dashboard' ) . '">' . esc_html( mb_strimwidth( (string) ( $row->reason ?? '' ), 0, 80, '…' ) ) . '</td>';
					$html   .= '</tr>';
				}

				$html .= '</tbody></table></div>';
			}
		}

		$html .= $this->card_close();

		/* آمادگی یادآور */
		if ( PAS_Dashboard_Core_Bridge::has_case_file() ) {
			$html .= $this->card_open( __( 'آمادگی یادآور جلسه', 'peyvand-amn-dashboard' ) );

			$cases = PAS_Dashboard_Core_Bridge::cases( get_current_user_id(), 60 );

			if ( empty( $cases ) ) {
				$html .= '<p class="pas-empty">' . esc_html__( 'پرونده‌ای وجود ندارد.', 'peyvand-amn-dashboard' ) . '</p>';
			} else {
				$labels = array(
					'ok'          => __( 'آماده‌ی ارسال', 'peyvand-amn-dashboard' ),
					'no_consent'  => __( 'بدون رضایت یادآور', 'peyvand-amn-dashboard' ),
					'no_session'  => __( 'بدون جلسه‌ی بعدی', 'peyvand-amn-dashboard' ),
					'no_channel'  => __( 'کانال یادآور تنظیم نشده', 'peyvand-amn-dashboard' ),
					'cooldown'    => __( 'در دوره‌ی انتظار', 'peyvand-amn-dashboard' ),
					'unknown'     => __( 'نامشخص', 'peyvand-amn-dashboard' ),
				);

				$html .= '<div class="pas-table-wrap"><table class="pas-table"><thead><tr>';
				$html .= '<th>' . esc_html__( 'پرونده', 'peyvand-amn-dashboard' ) . '</th>';
				$html .= '<th>' . esc_html__( 'جلسه‌ی بعدی', 'peyvand-amn-dashboard' ) . '</th>';
				$html .= '<th>' . esc_html__( 'وضعیت یادآور', 'peyvand-amn-dashboard' ) . '</th>';
				$html .= '</tr></thead><tbody>';

				foreach ( $cases as $case ) {
					if ( ! is_object( $case ) || empty( $case->id ) ) {
						continue;
					}
					$next    = PAS_Dashboard_Core_Bridge::next_session( $case );
					$blocker = PAS_Dashboard_Core_Bridge::reminder_blocker( $case, $next );

					$html .= '<tr>';
					$html .= '<td data-label="' . esc_attr__( 'پرونده', 'peyvand-amn-dashboard' ) . '">#' . esc_html( (string) (int) $case->id ) . '</td>';
					$html .= '<td data-label="' . esc_attr__( 'جلسه‌ی بعدی', 'peyvand-amn-dashboard' ) . '">' . esc_html( $next && ! empty( $next->next_session_date ) ? PAS_Dashboard_Core_Bridge::jalali( (string) $next->next_session_date ) : '—' ) . '</td>';
					$html .= '<td data-label="' . esc_attr__( 'وضعیت یادآور', 'peyvand-amn-dashboard' ) . '">' . esc_html( $labels[ $blocker ] ?? $blocker ) . '</td>';
					$html .= '</tr>';
				}

				$html .= '</tbody></table></div>';
			}

			$html .= $this->card_close();
		}

		$html .= $this->notice( 'info', __( 'ارسال پیامک فقط با الگوهای تأییدشده انجام می‌شود: یادآور جلسه از الگوی پرونده و یادآور نوبت از الگوی پنل استفاده می‌کند. ارسال متن آزاد عمداً وجود ندارد. در محیط توسعه هیچ پیامک واقعی ارسال نمی‌شود.', 'peyvand-amn-dashboard' ) );

		return $html;
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
