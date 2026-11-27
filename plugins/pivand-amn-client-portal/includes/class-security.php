<?php

declare(strict_types=1);

namespace ZoojDarman\ClientPortal;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * تمام nonce، capability و rate-limiting در این کلاس متمرکز شده تا هیچ AJAX
 * handler جدیدی به‌اشتباه بدون بررسی امنیتی نوشته نشود — برخلاف پلاگین قبلی
 * که handlerهای OTP اصلاً nonce نداشتند.
 */
final class Security
{
    public const NONCE_ACTION = 'zdp_frontend_nonce';
    public const CAPABILITY   = 'zdp_manage_clients';

    /**
     * بررسی nonce ارسالی در درخواست AJAX. در صورت نامعتبر بودن، پاسخ JSON
     * خطا برمی‌گرداند و اجرای اسکریپت را متوقف می‌کند (wp_send_json_error خودش exit می‌زند).
     */
    public function verify_ajax_nonce(): void
    {
        $nonce = isset($_REQUEST['nonce']) ? sanitize_text_field(wp_unslash($_REQUEST['nonce'])) : '';

        if (!wp_verify_nonce($nonce, self::NONCE_ACTION)) {
            wp_send_json_error(['message' => 'نشست شما منقضی شده. لطفاً صفحه را رفرش کنید.'], 403);
        }
    }

    /**
     * Rate limiter عمومی مبتنی بر transient. کلید را خودِ caller می‌سازد
     * (مثلاً 'otp_ip_' . md5($ip)) تا هر ماژول فضای نام مستقل داشته باشد.
     * برمی‌گرداند: true یعنی مجاز به ادامه، false یعنی محدود شده.
     *
     * رفع باگ race condition: نسخه‌ی قبلی get_transient و set_transient را
     * پشت‌سرهم و بدون قفل صدا می‌زد. اگر دو درخواست هم‌زمان (مثلاً دو تب باز
     * یا یک اسکریپت خودکار) دقیقاً در همان لحظه به این تابع می‌رسیدند، هر دو
     * می‌توانستند همان مقدار قدیمی $count را بخوانند و هر دو مجاز شمرده
     * شوند — یعنی تحت بار همزمان، سقف واقعی کمی بیشتر از max_attempts
     * می‌شد. همان الگوی GET_LOCK که در Booking::handle_create_booking برای
     * جلوگیری از رزرو هم‌زمان یک بازه استفاده شده، اینجا هم به‌کار رفته تا
     * خواندن+نوشتنِ شمارنده یک عملیات اتمیک باشد. اگر قفل به هر دلیلی گرفته
     * نشود (مثلاً بار فوق‌سنگین)، به‌جای فرض «مجاز»، محافظه‌کارانه false
     * برمی‌گردانیم — امن‌تر از دور زدن ناخواسته‌ی محدودیت نرخ.
     */
    public function allow(string $key, int $max_attempts, int $window_seconds): bool
    {
        global $wpdb;

        $lock_name = 'zdp_rl_lock_' . md5($key);
        $got_lock  = (int) $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 2)', $lock_name));

        if ($got_lock !== 1) {
            return false;
        }

        $transient_key = 'zdp_rl_' . $key;
        $now           = time();
        $state         = $this->read_state($transient_key, $window_seconds, $now);
        $count         = $state['n'];
        $start         = $state['t'];

        if ($count >= $max_attempts) {
            $wpdb->query($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $lock_name));

            return false;
        }

        // زمان شروع پنجره را داخل خود مقدار هم ذخیره می‌کنیم؛ اگر کش اشیای هاست
        // (Redis/Memcached/LiteSpeed) انقضای transient را رعایت نکند، پنجره‌ی ۶۰ثانیه‌ای
        // باز هم به‌درستی تمام می‌شود و کاربر برای همیشه قفل نمی‌ماند.
        $remaining = max(1, $window_seconds - ($now - $start));
        set_transient($transient_key, ['n' => $count + 1, 't' => $start], $remaining);
        $wpdb->query($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $lock_name));

        return true;
    }

    /**
     * وضعیت فعلی محدودکننده را می‌خواند. مقدار قدیمی (عدد ساده، از نسخه‌های قبل)
     * یا منقضی‌شده به‌عنوان «پنجره‌ی تازه» حساب می‌شود.
     *
     * @return array{n:int,t:int}
     */
    private function read_state(string $transient_key, int $window_seconds, int $now): array
    {
        $raw = get_transient($transient_key);

        if (is_array($raw) && isset($raw['n'], $raw['t'])) {
            $start = (int) $raw['t'];
            if ($start > 0 && $start <= $now && ($now - $start) < $window_seconds) {
                return ['n' => (int) $raw['n'], 't' => $start];
            }
        }

        return ['n' => 0, 't' => $now];
    }

    public function reset(string $key): void
    {
        delete_transient('zdp_rl_' . $key);
    }

    public function current_attempts(string $key): int
    {
        $raw = get_transient('zdp_rl_' . $key);

        return is_array($raw) ? (int) ($raw['n'] ?? 0) : (int) $raw;
    }

    /**
     * قابلیت اختصاصی مدیریت مراجعین، مشابه pap_manage_clients در
     * pivand-amn-panel، تا بتوان بعداً نقش «منشی» را بدون دسترسی کامل
     * manage_options تعریف کرد.
     */
    public static function register_capability(): void
    {
        $role = get_role('administrator');
        if ($role && !$role->has_cap(self::CAPABILITY)) {
            $role->add_cap(self::CAPABILITY);
        }
    }

    public function current_user_can_manage(): bool
    {
        return current_user_can(self::CAPABILITY) || current_user_can('manage_options');
    }

    public function require_manage_capability(): void
    {
        if (!$this->current_user_can_manage()) {
            wp_die(esc_html('شما مجاز به دسترسی به این بخش نیستید.'), 403);
        }
    }
}
