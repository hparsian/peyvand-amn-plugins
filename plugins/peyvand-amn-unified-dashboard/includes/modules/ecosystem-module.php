<?php
/**
 * ماژول یکپارچگی‌ها — وضعیت اتصال سرویس‌های اکوسیستم و قراردادهای کلیدی.
 *
 * وضعیت هر سرویس از شناسایی کلاس/تابع عمومی خودش و هدر نسخهٔ افزونهٔ نصب‌شده
 * می‌آید و هیچ درخواستی به سرویس بیرونی زده نمی‌شود. تنها نوشتن این صفحه
 * فرم «تنظیمات اتصال» است که از Action Bus، با nonce و کپابیلیتی
 * manage_options و ممیزی، فقط به متد عمومی افزونهٔ مالک سپرده می‌شود؛ هیچ
 * کلید یا رمزی نمایش داده نمی‌شود.
 */

defined( 'ABSPATH' ) || exit;

final class PAS_Dashboard_Module_Ecosystem extends PAS_Dashboard_Module {

	public function __construct() {
		$this->id          = 'ecosystem';
		$this->title       = __( 'یکپارچگی‌ها', 'peyvand-amn-dashboard' );
		$this->description = __( 'وضعیت اتصال سرویس‌های اکوسیستم و قراردادهای بین‌افزونه‌ای.', 'peyvand-amn-dashboard' );
	}

	public function render() {
		$services  = PAS_Dashboard_Adapter_Ecosystem::services();
		$summary   = PAS_Dashboard_Adapter_Ecosystem::summary( $services );
		$groups    = PAS_Dashboard_Adapter_Ecosystem::groups();

		$html  = $this->card_open( __( 'خلاصهٔ اتصال‌ها', 'peyvand-amn-dashboard' ) );
		$html .= '<div class="pas-grid">';
		$html .= $this->stat( (string) $summary['total'], __( 'کل سرویس‌ها', 'peyvand-amn-dashboard' ) );
		$html .= $this->stat( (string) $summary['connected'], __( 'متصل', 'peyvand-amn-dashboard' ) );
		$html .= $this->stat( (string) $summary['attention'], __( 'نیازمند بررسی', 'peyvand-amn-dashboard' ) );
		$html .= $this->stat( (string) $summary['off'], __( 'غیرفعال', 'peyvand-amn-dashboard' ) );
		$html .= '</div>';
		$html .= '<p class="pas-muted">' . esc_html( sprintf(
			/* translators: %s: dashboard version */
			__( 'نسخهٔ داشبورد: %s — این صفحه فقط وضعیت را نشان می‌دهد و هیچ تنظیمی را تغییر نمی‌دهد.', 'peyvand-amn-dashboard' ),
			PAS_DASHBOARD_VERSION
		) ) . '</p>';
		$html .= $this->card_close();

		if ( PAS_Dashboard_Security::can_manage() ) {
			$html .= $this->render_settings_form();
		}

		foreach ( $groups as $group_id => $group_label ) {
			$rows = array_values( array_filter( $services, static function ( $service ) use ( $group_id ) {
				return (string) $service['group'] === (string) $group_id;
			} ) );

			if ( empty( $rows ) ) {
				continue;
			}

			$html .= $this->card_open( $group_label );
			$html .= '<div class="pas-table-wrap"><table class="pas-table"><thead><tr>';
			$html .= '<th>' . esc_html__( 'سرویس', 'peyvand-amn-dashboard' ) . '</th>';
			$html .= '<th>' . esc_html__( 'نسخه', 'peyvand-amn-dashboard' ) . '</th>';
			$html .= '<th>' . esc_html__( 'وضعیت', 'peyvand-amn-dashboard' ) . '</th>';
			$html .= '<th>' . esc_html__( 'جزئیات', 'peyvand-amn-dashboard' ) . '</th>';
			$html .= '</tr></thead><tbody>';

			foreach ( $rows as $service ) {
				$html .= '<tr>';
				$html .= '<td data-label="' . esc_attr__( 'سرویس', 'peyvand-amn-dashboard' ) . '"><strong>' . esc_html( (string) $service['label'] ) . '</strong>';
				$html .= $service['required']
					? ' <span class="pas-muted">' . esc_html__( '(ضروری)', 'peyvand-amn-dashboard' ) . '</span>'
					: '';
				$html .= '</td>';
				$html .= '<td data-label="' . esc_attr__( 'نسخه', 'peyvand-amn-dashboard' ) . '"><span dir="ltr">' . esc_html( $service['version'] !== '' ? (string) $service['version'] : '—' ) . '</span></td>';
				$html .= '<td data-label="' . esc_attr__( 'وضعیت', 'peyvand-amn-dashboard' ) . '"><span class="pas-badge ' . esc_attr( PAS_Dashboard_Adapter_Ecosystem::status_class( (string) $service['status'] ) ) . '">' . esc_html( PAS_Dashboard_Adapter_Ecosystem::status_label( (string) $service['status'] ) ) . '</span></td>';
				$html .= '<td data-label="' . esc_attr__( 'جزئیات', 'peyvand-amn-dashboard' ) . '">' . esc_html( (string) $service['detail'] ) . '</td>';
				$html .= '</tr>';
			}

			$html .= '</tbody></table></div>';
			$html .= $this->card_close();
		}

		$html .= $this->card_open( __( 'قراردادهای بین‌افزونه‌ای', 'peyvand-amn-dashboard' ) );
		$html .= '<p class="pas-muted">' . esc_html__( 'هر قرارداد یعنی داشبورد می‌تواند آن کار را از API عمومی افزونهٔ مالک انجام دهد؛ غایب‌بودن یک قرارداد فقط همان ماژول را غیرفعال می‌کند.', 'peyvand-amn-dashboard' ) . '</p>';
		$html .= '<div class="pas-table-wrap"><table class="pas-table"><thead><tr>';
		$html .= '<th>' . esc_html__( 'قرارداد', 'peyvand-amn-dashboard' ) . '</th>';
		$html .= '<th>' . esc_html__( 'قلاب / کلید', 'peyvand-amn-dashboard' ) . '</th>';
		$html .= '<th>' . esc_html__( 'وضعیت', 'peyvand-amn-dashboard' ) . '</th>';
		$html .= '</tr></thead><tbody>';

		foreach ( PAS_Dashboard_Adapter_Ecosystem::contracts() as $contract ) {
			$html .= '<tr>';
			$html .= '<td data-label="' . esc_attr__( 'قرارداد', 'peyvand-amn-dashboard' ) . '">' . esc_html( (string) $contract['label'] ) . '</td>';
			$html .= '<td data-label="' . esc_attr__( 'قلاب / کلید', 'peyvand-amn-dashboard' ) . '"><code dir="ltr">' . esc_html( (string) $contract['hook'] ) . '</code></td>';
			$html .= '<td data-label="' . esc_attr__( 'وضعیت', 'peyvand-amn-dashboard' ) . '">' . ( ! empty( $contract['ok'] )
				? '<span class="pas-badge pas-badge-good">' . esc_html__( 'در دسترس', 'peyvand-amn-dashboard' ) . '</span>'
				: '<span class="pas-badge pas-badge-warn">' . esc_html__( 'در دسترس نیست', 'peyvand-amn-dashboard' ) . '</span>' ) . '</td>';
			$html .= '</tr>';
		}

		$html .= '</tbody></table></div>';
		$html .= $this->card_close();

		$html .= $this->notice( 'info', __( 'این صفحه هیچ کلید، رمز، توکن یا متن پیامی را نمایش نمی‌دهد و هیچ درخواستی به سرویس‌های بیرونی نمی‌فرستد. «متصل» یعنی افزونهٔ مالک و قرارداد عمومی آن در دسترس است، نه اثبات کارکرد وب‌سرویس؛ وضعیت واقعی هر ارسال در لاگ همان افزونه دیده می‌شود.', 'peyvand-amn-dashboard' ) );

		return $html;
	}

	/**
	 * فرم تنظیمات اتصال کانال‌ها — فقط برای مدیر سایت.
	 *
	 * همهٔ مقادیر از متد عمومی مالک پیش‌پر می‌شوند؛ فیلد رمز/کلید همیشه خالی
	 * است تا مقدار ذخیره‌شده هرگز در HTML نیاید. خالی‌ماندن آن‌ها یعنی «حفظ
	 * مقدار فعلی».
	 */
	private function render_settings_form() {
		$model = PAS_Dashboard_Adapter_Ecosystem::settings_model();
		$sms   = $model['sms'];
		$smtp  = $model['smtp'];

		$html  = $this->card_open( __( 'تنظیمات اتصال سرویس‌ها', 'peyvand-amn-dashboard' ) );
		$html .= '<p class="pas-muted">' . esc_html__( 'فقط مدیر سایت این مقادیر را تغییر می‌دهد. مقادیر حساس هیچ‌گاه نمایش داده نمی‌شوند؛ اگر فیلد رمز یا کلید را خالی بگذارید، مقدار فعلی دست‌نخورده می‌ماند.', 'peyvand-amn-dashboard' ) . '</p>';

		$html .= '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		$html .= PAS_Dashboard_Actions::fields( 'ecosystem.save', $this->url() );

		$html .= '<h3 class="pas-card-title">' . esc_html__( 'پل پیامک (ملی پیامک)', 'peyvand-amn-dashboard' ) . '</h3>';

		if ( empty( $sms['available'] ) ) {
			$html .= $this->notice( 'warning', __( 'افزونهٔ پل پیامک در دسترس نیست یا نسخهٔ آن قرارداد داشبورد را ندارد (۱.۱.۱۰+).', 'peyvand-amn-dashboard' ) );
		} else {
			if ( 'constants' === $sms['source'] ) {
				$html .= $this->notice( 'warning', __( 'اعتبارنامهٔ پیامک از ثابت‌های wp-config.php خوانده می‌شود؛ تا وقتی آن‌ها تعریف شده‌اند، ذخیره از این فرم اثری ندارد.', 'peyvand-amn-dashboard' ) );
			}

			$html .= $this->text_row( 'sms_username', __( 'نام‌کاربری ملی پیامک', 'peyvand-amn-dashboard' ), (string) $sms['username'], 'text' );
			$html .= $this->password_row( 'sms_password', __( 'رمز ملی پیامک', 'peyvand-amn-dashboard' ), ! empty( $sms['has_password'] ), empty( $sms['has_password'] ) ? __( 'هنوز رمزی ذخیره نشده است.', 'peyvand-amn-dashboard' ) : __( 'رمز ذخیره شده است؛ برای تغییر، مقدار جدید وارد کنید.', 'peyvand-amn-dashboard' ) );
		}

		$html .= '<h3 class="pas-card-title">' . esc_html__( 'ارسال ایمیل (SMTP / Brevo)', 'peyvand-amn-dashboard' ) . '</h3>';

		if ( empty( $smtp['available'] ) ) {
			$html .= $this->notice( 'warning', __( 'افزونهٔ ارسال ایمیل در دسترس نیست یا نسخهٔ آن قرارداد داشبورد را ندارد (۱.۵.۷+).', 'peyvand-amn-dashboard' ) );
		} else {
			$html .= '<div class="pas-form-row"><label for="pas-smtp-method">' . esc_html__( 'روش ارسال', 'peyvand-amn-dashboard' ) . '</label>';
			$html .= '<select class="pas-input" id="pas-smtp-method" name="smtp_send_method">';
			$html .= '<option value="smtp"' . selected( $smtp['send_method'], 'smtp', false ) . '>' . esc_html__( 'SMTP (پورت ۵۸۷/۴۶۵)', 'peyvand-amn-dashboard' ) . '</option>';
			$html .= '<option value="brevo"' . selected( $smtp['send_method'], 'brevo', false ) . '>' . esc_html__( 'Brevo API (فقط HTTPS)', 'peyvand-amn-dashboard' ) . '</option>';
			$html .= '</select></div>';

			$html .= $this->text_row( 'smtp_host', __( 'میزبان SMTP', 'peyvand-amn-dashboard' ), (string) $smtp['host'], 'text', 'smtp.example.com' );
			$html .= $this->text_row( 'smtp_port', __( 'پورت SMTP', 'peyvand-amn-dashboard' ), (string) $smtp['port'], 'text', '587' );

			$html .= '<div class="pas-form-row"><label for="pas-smtp-encryption">' . esc_html__( 'رمزنگاری', 'peyvand-amn-dashboard' ) . '</label>';
			$html .= '<select class="pas-input" id="pas-smtp-encryption" name="smtp_encryption">';
			$html .= '<option value="tls"' . selected( $smtp['encryption'], 'tls', false ) . '>TLS</option>';
			$html .= '<option value="ssl"' . selected( $smtp['encryption'], 'ssl', false ) . '>SSL</option>';
			$html .= '</select></div>';

			$html .= $this->text_row( 'smtp_username', __( 'نام‌کاربری SMTP', 'peyvand-amn-dashboard' ), (string) $smtp['username'], 'text' );
			$html .= $this->password_row( 'smtp_password', __( 'رمز SMTP / App Password', 'peyvand-amn-dashboard' ), ! empty( $smtp['has_password'] ), empty( $smtp['has_password'] ) ? __( 'هنوز رمزی ذخیره نشده است.', 'peyvand-amn-dashboard' ) : __( 'رمز ذخیره شده است؛ برای تغییر، مقدار جدید وارد کنید.', 'peyvand-amn-dashboard' ) );
			$html .= $this->text_row( 'smtp_from_email', __( 'آدرس فرستنده', 'peyvand-amn-dashboard' ), (string) $smtp['from_email'], 'email', 'you@example.com' );
			$html .= $this->text_row( 'smtp_from_name', __( 'نام فرستنده', 'peyvand-amn-dashboard' ), (string) $smtp['from_name'], 'text', __( 'پیوند امن', 'peyvand-amn-dashboard' ) );
			$html .= $this->password_row( 'smtp_brevo_api_key', __( 'کلید API بریو (Brevo)', 'peyvand-amn-dashboard' ), ! empty( $smtp['has_brevo_key'] ), empty( $smtp['has_brevo_key'] ) ? __( 'فقط برای روش Brevo لازم است.', 'peyvand-amn-dashboard' ) : __( 'کلید ذخیره شده است؛ برای تغییر، مقدار جدید وارد کنید.', 'peyvand-amn-dashboard' ) );
		}

		if ( ! empty( $sms['available'] ) || ! empty( $smtp['available'] ) ) {
			$html .= '<button class="pas-btn pas-btn-primary" type="submit">' . esc_html__( 'ذخیرهٔ تنظیمات اتصال', 'peyvand-amn-dashboard' ) . '</button>';
		}

		$html .= '</form>';
		$html .= $this->card_close();

		return $html;
	}

	/** یک ردیف ورودی متنی با برچسب و توضیح اختیاری. */
	private function text_row( $name, $label, $value, $type = 'text', $placeholder = '' ) {
		$html  = '<div class="pas-form-row"><label for="pas-' . esc_attr( $name ) . '">' . esc_html( $label ) . '</label>';
		$html .= '<input class="pas-input" type="' . esc_attr( $type ) . '" id="pas-' . esc_attr( $name ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '" dir="ltr"';
		$html .= $placeholder !== '' ? ' placeholder="' . esc_attr( $placeholder ) . '"' : '';
		$html .= ' /></div>';

		return $html;
	}

	/** ردیف رمز/کلید — مقدار هرگز در HTML نوشته نمی‌شود. */
	private function password_row( $name, $label, $has_value, $hint ) {
		$html  = '<div class="pas-form-row"><label for="pas-' . esc_attr( $name ) . '">' . esc_html( $label ) . '</label>';
		$html .= '<input class="pas-input" type="password" id="pas-' . esc_attr( $name ) . '" name="' . esc_attr( $name ) . '" value="" autocomplete="new-password" dir="ltr" placeholder="' . esc_attr( $has_value ? '••••••••' : '' ) . '" />';
		$html .= '<p class="pas-muted">' . esc_html( $hint ) . '</p></div>';

		return $html;
	}

	private function stat( $value, $label ) {
		return '<div class="pas-stat"><span class="pas-stat-value">' . esc_html( (string) $value ) . '</span><span class="pas-stat-label">' . esc_html( (string) $label ) . '</span></div>';
	}
}
