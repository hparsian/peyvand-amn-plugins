<?php
/**
 * ماژول به‌روزرسانی‌ها — وضعیت بسته‌های مخزن گیت‌هاب و اجرای آپدیت.
 *
 * این صفحه فقط برای مدیر سایت است. خواندن وضعیت از GitHub انجام می‌شود و
 * عملیات نوشتنی (ذخیرهٔ تنظیمات مخزن، بررسی دوباره و اجرای آپدیت) از
 * Action Bus داشبورد با nonce، کپابیلیتی و ممیزی می‌گذرد. هیچ توکن یا رمزی
 * در HTML نوشته نمی‌شود و آپدیت فقط از بستهٔ همین مخزن اجرا می‌شود.
 */

defined( 'ABSPATH' ) || exit;

final class PAS_Dashboard_Module_Updates extends PAS_Dashboard_Module {

	public function __construct() {
		$this->id          = 'updates';
		$this->title       = __( 'به‌روزرسانی‌ها', 'peyvand-amn-dashboard' );
		$this->description = __( 'بررسی نسخه‌های تازهٔ افزونه‌های اکوسیستم از مخزن گیت‌هاب و به‌روزرسانی بدون نصب دستی.', 'peyvand-amn-dashboard' );
	}

	public function render() {
		$status  = PAS_Dashboard_Updater::status();
		$summary = $status['summary'];

		$html  = $this->card_open( __( 'خلاصهٔ به‌روزرسانی', 'peyvand-amn-dashboard' ) );
		$html .= '<div class="pas-grid">';
		$html .= $this->stat( (string) $summary['total'], __( 'افزونهٔ اکوسیستم', 'peyvand-amn-dashboard' ) );
		$html .= $this->stat( (string) $summary['update'], __( 'به‌روزرسانی موجود', 'peyvand-amn-dashboard' ) );
		$html .= $this->stat( (string) $summary['current'], __( 'به‌روز', 'peyvand-amn-dashboard' ) );
		$html .= $this->stat( (string) $summary['absent'], __( 'نصب‌نشده', 'peyvand-amn-dashboard' ) );
		$html .= '</div>';

		$html .= '<p class="pas-muted">' . esc_html( sprintf(
			/* translators: 1: repository, 2: branch */
			__( 'مخزن: %1$s — شاخه: %2$s. فهرست بسته‌ها هر ۶ ساعت یک‌بار خوانده می‌شود.', 'peyvand-amn-dashboard' ),
			$status['repo'],
			$status['branch']
		) ) . '</p>';

		if ( (int) $status['fetched_at'] > 0 ) {
			$html .= '<p class="pas-muted pvn-update-checked">' . esc_html( sprintf(
				/* translators: %s: Jalali date and time */
				__( 'آخرین بررسی مخزن: %s', 'peyvand-amn-dashboard' ),
				self::jalali_time( (int) $status['fetched_at'] )
			) ) . '</p>';
		}

		if ( $status['error'] !== '' ) {
			$html .= $this->notice( 'warning', $status['error'] );
		}

		$html .= '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="pas-inline-form">';
		$html .= PAS_Dashboard_Actions::fields( 'updates.refresh', $this->url() );
		$html .= '<button class="pas-btn pas-btn-secondary" type="submit">' . esc_html__( 'بررسی دوبارهٔ مخزن', 'peyvand-amn-dashboard' ) . '</button>';
		$html .= '</form>';

		if ( $summary['update'] > 0 ) {
			$html .= '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="pas-inline-form" data-pas-update-form="1" data-pas-update-all="1">';
			$html .= PAS_Dashboard_Actions::fields( 'updates.run', $this->url() );
			$html .= '<input type="hidden" name="slug" value="" />';
			$html .= '<button class="pas-btn pas-btn-primary" type="submit" data-pas-update-trigger>' . esc_html__( 'به‌روزرسانی همهٔ افزونه‌ها', 'peyvand-amn-dashboard' ) . '</button>';
			$html .= '<span class="pvn-update-state" data-pas-update-state aria-live="polite"></span>';
			$html .= '</form>';
		}

		$html .= '<p class="pas-muted">' . esc_html__( 'به‌روزرسانی با همان آپ‌گریدر رسمی وردپرس انجام می‌شود، اما بسته از پوشهٔ dist مخزن شما خوانده می‌شود؛ نیازی به دانلود و نصب دستی نیست.', 'peyvand-amn-dashboard' ) . '</p>';
		$html .= $this->card_close();

		$html .= $this->render_table( $status );

		if ( PAS_Dashboard_Security::can_manage() ) {
			$html .= $this->render_settings( $status );
		}

		$html .= $this->notice( 'info', __( 'اگر بستهٔ تازه‌ای در مخزن منتشر شود ولی شمارهٔ نسخهٔ هدر افزونه تغییر نکند، وردپرس آن را به‌روزرسانی نمی‌شمارد. هنگام ساخت بسته، نسخه را بالا ببرید. برای مخزن خصوصی یا عبور از سهمیهٔ GitHub API، توکن را در تنظیمات همین صفحه وارد کنید یا ثابت PAS_DASHBOARD_GITHUB_TOKEN را در wp-config.php بگذارید.', 'peyvand-amn-dashboard' ) );

		return $html;
	}

	/** جدول وضعیت همهٔ افزونه‌های فهرست سفید. */
	private function render_table( array $status ) {
		$html  = $this->card_open( __( 'وضعیت نسخه‌ها', 'peyvand-amn-dashboard' ) );
		$html .= '<div class="pas-table-wrap"><table class="pas-table pvn-updates-table"><thead><tr>';
		$html .= '<th>' . esc_html__( 'افزونه', 'peyvand-amn-dashboard' ) . '</th>';
		$html .= '<th>' . esc_html__( 'نسخهٔ نصب‌شده', 'peyvand-amn-dashboard' ) . '</th>';
		$html .= '<th>' . esc_html__( 'آخرین نسخهٔ مخزن', 'peyvand-amn-dashboard' ) . '</th>';
		$html .= '<th>' . esc_html__( 'آخرین به‌روزرسانی', 'peyvand-amn-dashboard' ) . '</th>';
		$html .= '<th>' . esc_html__( 'وضعیت', 'peyvand-amn-dashboard' ) . '</th>';
		$html .= '<th>' . esc_html__( 'عملیات', 'peyvand-amn-dashboard' ) . '</th>';
		$html .= '</tr></thead><tbody>';

		foreach ( $status['items'] as $item ) {
			$slug       = (string) $item['slug'];
			$updated_ts = (int) ( $item['updated_ts'] ?? 0 );
			$updated    = $updated_ts > 0
				? self::jalali_time( $updated_ts )
				: __( 'نامشخص', 'peyvand-amn-dashboard' );

			$html .= '<tr>';
			$html .= '<td data-label="' . esc_attr__( 'افزونه', 'peyvand-amn-dashboard' ) . '"><strong>' . esc_html( (string) $item['name'] ) . '</strong><br /><code dir="ltr">' . esc_html( $slug ) . '</code></td>';
			$html .= '<td data-label="' . esc_attr__( 'نسخهٔ نصب‌شده', 'peyvand-amn-dashboard' ) . '"><span dir="ltr">' . esc_html( $item['current'] !== '' ? (string) $item['current'] : '—' ) . '</span></td>';
			$html .= '<td data-label="' . esc_attr__( 'آخرین نسخهٔ مخزن', 'peyvand-amn-dashboard' ) . '"><span dir="ltr">' . esc_html( $item['latest'] !== '' ? (string) $item['latest'] : '—' ) . '</span></td>';
			$html .= '<td data-label="' . esc_attr__( 'آخرین به‌روزرسانی', 'peyvand-amn-dashboard' ) . '"><time class="pvn-updated" datetime="' . esc_attr( $updated_ts > 0 ? gmdate( 'c', $updated_ts ) : '' ) . '">' . esc_html( $updated ) . '</time></td>';
			$html .= '<td data-label="' . esc_attr__( 'وضعیت', 'peyvand-amn-dashboard' ) . '"><span class="pas-badge ' . esc_attr( PAS_Dashboard_Updater::state_class( (string) $item['state'] ) ) . '" title="' . esc_attr( (string) $item['detail'] ) . '">' . esc_html( PAS_Dashboard_Updater::state_label( (string) $item['state'] ) ) . '</span></td>';
			$html .= '<td data-label="' . esc_attr__( 'عملیات', 'peyvand-amn-dashboard' ) . '">';

			if ( 'update' === $item['state'] ) {
				$html .= '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="pas-inline-form" data-pas-update-form="1" data-slug="' . esc_attr( $slug ) . '">';
				$html .= PAS_Dashboard_Actions::fields( 'updates.run', $this->url() );
				$html .= '<input type="hidden" name="slug" value="' . esc_attr( $slug ) . '" />';
				$html .= '<button class="pas-btn pas-btn-primary" type="submit" data-pas-update-trigger>' . esc_html__( 'به‌روزرسانی', 'peyvand-amn-dashboard' ) . '</button>';
				$html .= '<span class="pvn-update-state" data-pas-update-state aria-live="polite"></span>';
				$html .= '</form>';
			} else {
				$html .= '<span class="pas-muted">—</span>';
			}

			$html .= '</td>';
			$html .= '</tr>';
		}

		$html .= '</tbody></table></div>';
		$html .= $this->card_close();

		return $html;
	}

	/** تاریخ + ساعت شمسی از یک timestamp (وقت محلی سایت). */
	private static function jalali_time( $timestamp ) {
		$timestamp = (int) $timestamp;

		if ( $timestamp <= 0 ) {
			return '—';
		}

		$local = function_exists( 'wp_date' ) ? wp_date( 'Y-m-d H:i:s', $timestamp ) : gmdate( 'Y-m-d H:i:s', $timestamp );

		return PAS_Dashboard_Core_Bridge::jalali_datetime( (string) $local );
	}

	/**
	 * فرم تنظیمات مخزن و به‌روزرسانی خودکار — فقط مدیر سایت.
	 *
	 * مقدار توکن هرگز در HTML بازنویسی نمی‌شود؛ فیلد خالی یعنی «حفظ مقدار
	 * فعلی» و تیک «پاک‌کردن» تنها راه حذف آن است.
	 */
	private function render_settings( array $status ) {
		$token_source = defined( 'PAS_DASHBOARD_GITHUB_TOKEN' ) && PAS_DASHBOARD_GITHUB_TOKEN
			? __( 'توکن از ثابت PAS_DASHBOARD_GITHUB_TOKEN در wp-config.php خوانده می‌شود.', 'peyvand-amn-dashboard' )
			: ( $status['has_token']
				? __( 'توکن ذخیره شده است؛ برای تغییر، مقدار جدید وارد کنید.', 'peyvand-amn-dashboard' )
				: __( 'بدون توکن هم مخزن عمومی خوانده می‌شود؛ برای مخزن خصوصی یا سهمیهٔ بالاتر توکن لازم است.', 'peyvand-amn-dashboard' ) );

		$html  = $this->card_open( __( 'تنظیمات مخزن', 'peyvand-amn-dashboard' ) );
		$html .= '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		$html .= PAS_Dashboard_Actions::fields( 'updates.save', $this->url() );

		$html .= '<div class="pas-form-row"><label for="pas-updates-repo">' . esc_html__( 'مخزن گیت‌هاب (owner/repository)', 'peyvand-amn-dashboard' ) . '</label>';
		$html .= '<input class="pas-input" type="text" id="pas-updates-repo" name="updates_repo" value="' . esc_attr( $status['repo'] ) . '" dir="ltr" /></div>';

		$html .= '<div class="pas-form-row"><label for="pas-updates-branch">' . esc_html__( 'شاخه', 'peyvand-amn-dashboard' ) . '</label>';
		$html .= '<input class="pas-input" type="text" id="pas-updates-branch" name="updates_branch" value="' . esc_attr( $status['branch'] ) . '" dir="ltr" /></div>';

		$html .= '<div class="pas-form-row"><label for="pas-github-token">' . esc_html__( 'توکن گیت‌هاب (اختیاری)', 'peyvand-amn-dashboard' ) . '</label>';
		$html .= '<input class="pas-input" type="password" id="pas-github-token" name="github_token" value="" autocomplete="new-password" dir="ltr" placeholder="' . esc_attr( $status['has_token'] ? '••••••••' : '' ) . '" />';
		$html .= '<p class="pas-muted">' . esc_html( $token_source ) . '</p></div>';

		if ( $status['has_token'] && ! ( defined( 'PAS_DASHBOARD_GITHUB_TOKEN' ) && PAS_DASHBOARD_GITHUB_TOKEN ) ) {
			$html .= '<div class="pas-form-row"><label><input type="checkbox" name="github_token_clear" value="1" /> ' . esc_html__( 'توکن ذخیره‌شده پاک شود', 'peyvand-amn-dashboard' ) . '</label></div>';
		}

		$html .= '<div class="pas-form-row"><label><input type="checkbox" name="enable_auto_updates" value="1"' . checked( $status['auto_updates'], true, false ) . ' /> ' . esc_html__( 'به‌روزرسانی خودکار افزونه‌های همین فهرست در پس‌زمینه', 'peyvand-amn-dashboard' ) . '</label>';
		$html .= '<p class="pas-muted">' . esc_html__( 'با فعال‌بودن این گزینه، وردپرس نسخهٔ تازهٔ همین افزونه‌ها را بدون دخالت دستی نصب می‌کند. فقط بسته‌های فهرست سفید داشبورد و فقط از HTTPS گیت‌هاب پذیرفته می‌شوند.', 'peyvand-amn-dashboard' ) . '</p></div>';

		$html .= '<button class="pas-btn pas-btn-primary" type="submit">' . esc_html__( 'ذخیرهٔ تنظیمات به‌روزرسانی', 'peyvand-amn-dashboard' ) . '</button>';
		$html .= '</form>';
		$html .= $this->card_close();

		return $html;
	}

	private function stat( $value, $label ) {
		return '<div class="pas-stat"><span class="pas-stat-value">' . esc_html( (string) $value ) . '</span><span class="pas-stat-label">' . esc_html( (string) $label ) . '</span></div>';
	}
}
