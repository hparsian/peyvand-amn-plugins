<?php

declare(strict_types=1);

namespace ZoojDarman\ClientPortal;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * دریافت خطاهای JS سمت کاربر (پنل مراجعین) و ثبتشان در همان audit_log()ی
 * که «لاگ امنیتی» در پیشخوان از قبل نمایش می‌دهد (class-admin.php →
 * render_security_log_tab). عمداً هیچ جدول/صفحه‌ی جدیدی اضافه نشده تا این
 * قابلیت صرفاً یک منبع دیگر برای همان لاگ موجود باشد، نه یک سیستم موازی.
 *
 * نکات امنیتی که این کلاس مراقبشان است (چون این تنها endpoint سمت کلاینت
 * است که ورودی‌اش تقریباً هرچیزی می‌تواند باشد — پیام خطا و stack trace از
 * جاوااسکریپت کاربر می‌آید، نه از فرم کنترل‌شده‌ی ما):
 *  ۱. Rate limit مجزا از بقیه‌ی endpointها، چون یک صفحه‌ی خراب می‌تواند در
 *     حلقه صدها خطا در ثانیه پرتاب کند؛ بدون سقف، همان یک صفحه به‌تنهایی
 *     کل ۵۰۰ رکورد audit_log را با تکرار یک خطا پر می‌کند و رویدادهای
 *     امنیتی واقعی (OTP، پرداخت) از صف بیرون می‌روند.
 *  ۲. طول هر فیلد محدود می‌شود؛ در غیر این صورت یک stack trace یا رشته‌ی
 *     دستکاری‌شده‌ی بزرگ می‌تواند اندازه‌ی گزینه‌ی wp_options را به‌طور
 *     غیرمنطقی رشد دهد (audit_log خودش تعداد رکورد را به ۵۰۰ محدود می‌کند،
 *     اما نه اندازه‌ی هر رکورد را).
 *  ۳. خروجی همیشه از طریق esc_html/wp_json_encode در صفحه‌ی ادمین چاپ
 *     می‌شود (نگاه کن به render_security_log_tab)، پس ذخیره‌ی مقدار خام
 *     اینجا مشکلی در XSS ایجاد نمی‌کند؛ با این حال ورودی‌ها همچنان
 *     sanitize می‌شوند تا لاگ خودش هم تمیز و قابل‌خواندن بماند.
 */
final class Client_Error_Log
{
    private const ACTION = 'zdp_log_client_error';

    /** حداکثر طول مجاز برای فیلدهای متنی آزاد (پیام خطا، URL، user agent). */
    private const MAX_FIELD_LEN = 500;

    /** stack trace معمولاً طولانی‌تر است؛ سقف جداگانه و بزرگ‌تر برایش. */
    private const MAX_STACK_LEN = 2000;

    private Security $security;

    public function __construct(Security $security)
    {
        $this->security = $security;

        add_action('wp_ajax_' . self::ACTION, [$this, 'handle_log_error']);
        add_action('wp_ajax_nopriv_' . self::ACTION, [$this, 'handle_log_error']);
    }

    public function handle_log_error(): void
    {
        $this->security->verify_ajax_nonce();

        // سقف: هر IP حداکثر ۲۰ خطا در هر ۵ دقیقه. کافی برای دیدن الگوی یک
        // باگ واقعی، ولی مانع پر شدنِ کل لاگ توسط یک حلقه‌ی خطای تکراری.
        $rl_key = 'client_js_error_' . md5($this->get_ip());
        if (!$this->security->allow($rl_key, 20, 5 * MINUTE_IN_SECONDS)) {
            // عمداً success برمی‌گردانیم (نه خطا): این یک endpoint گزارش‌دهیِ
            // صرف است، نباید کنسول کاربر را با خطای «rate limited» شلوغ کند
            // یا رفتار fetch سمت کلاینت را پیچیده کند.
            wp_send_json_success();
        }

        $message = $this->clean_field($_POST['message'] ?? '', self::MAX_FIELD_LEN);
        $source  = $this->clean_field($_POST['source'] ?? '', self::MAX_FIELD_LEN);
        $line    = isset($_POST['line']) ? (int) $_POST['line'] : 0;
        $col     = isset($_POST['col']) ? (int) $_POST['col'] : 0;
        $stack   = $this->clean_field($_POST['stack'] ?? '', self::MAX_STACK_LEN);
        $page    = $this->clean_field($_POST['page_url'] ?? '', self::MAX_FIELD_LEN);
        $ua      = $this->clean_field($_POST['user_agent'] ?? '', self::MAX_FIELD_LEN);

        if ($message === '') {
            wp_send_json_success();
        }

        audit_log('js_client_error', [
            'message' => $message,
            'source'  => $source,
            'line'    => $line,
            'col'     => $col,
            'stack'   => $stack,
            'page'    => $page,
            'ua'      => $ua,
        ]);

        wp_send_json_success();
    }

    /**
     * sanitize_textarea_field چندخطی بودن stack trace را حفظ می‌کند (برخلاف
     * sanitize_text_field که newline را به space تبدیل می‌کند)، بعد با
     * mb_substr به سقف طول محدود می‌شود.
     */
    private function clean_field($raw, int $max_len): string
    {
        $value = is_string($raw) ? $raw : '';
        $value = sanitize_textarea_field(wp_unslash($value));

        return mb_substr($value, 0, $max_len);
    }

    private function get_ip(): string
    {
        return get_client_ip();
    }
}
