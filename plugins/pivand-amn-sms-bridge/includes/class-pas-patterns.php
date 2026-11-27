<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * رجیستری الگوهای تأییدشده‌ی ملی‌پیامک.
 *
 * ملی‌پیامک متن آزاد نمی‌پذیرد؛ هر ارسال باید bodyId یک الگوی تأییدشده را
 * داشته باشد. این کلاس همان نگاشت «slug → bodyId» را در یک نقطه جمع می‌کند تا
 * افزونه‌های دیگر (مثلاً داشبورد یکپارچه) بدون خواندن آپشن‌های پراکنده و بدون
 * اختراع شناسه، فقط الگوهای موجود را ببینند و همان‌ها را بفرستند.
 *
 * قرارداد عمومی:
 *   Pas_Patterns::all()               → slug => [slug, label, body_id, dashboard]
 *   Pas_Patterns::dashboard_patterns() → فقط الگوهای قابل استفاده در داشبورد
 *   Pas_Patterns::send($slug, $mobile, $params) → bool
 *   apply_filters('pas_sms_patterns', $patterns) → افزودن الگوی تازه
 */
final class Pas_Patterns
{
    /**
     * الگوهای شناخته‌شده‌ی اکوسیستم. هر کلید، نام آپشنی است که افزونه‌ی مالک
     * همان الگو آن را ذخیره می‌کند؛ اینجا فقط خوانده می‌شود.
     *
     * dashboard=false یعنی الگو عملیاتی حساس است (OTP، پیام خودکار فرم) و
     * نباید از داشبورد با پارامتر دلخواه به هر شماره‌ای فرستاده شود.
     *
     * @return array<string,array{label:string,option:string,dashboard:bool}>
     */
    public static function catalogue(): array
    {
        return [
            'case_reminder' => [
                'label'     => 'یادآور جلسه',
                'option'    => 'pas_cf_reminder_sms_pattern',
                'dashboard' => true,
            ],
            'booking_reminder' => [
                'label'     => 'یادآوری نوبت',
                'option'    => 'zdp_sms_reminder_body_id',
                'dashboard' => true,
            ],
            'booking_confirm' => [
                'label'     => 'تأیید نوبت',
                'option'    => 'zdp_sms_booking_body_id',
                'dashboard' => true,
            ],
            'admin_booking' => [
                'label'     => 'نوبت جدید برای مدیر',
                'option'    => 'zdp_sms_admin_body_id',
                'dashboard' => false,
            ],
            'intake' => [
                'label'     => 'فرم ارزیابی ناقص',
                'option'    => 'zdp_sms_intake_body_id',
                'dashboard' => false,
            ],
            'otp' => [
                'label'     => 'کد ورود (OTP)',
                'option'    => 'zdp_sms_body_id',
                'dashboard' => false,
            ],
        ];
    }

    /**
     * @return array<string,array{slug:string,label:string,body_id:int,dashboard:bool}>
     */
    public static function all(): array
    {
        $patterns = [];

        foreach (self::catalogue() as $slug => $pattern) {
            $body_id = (int) get_option($pattern['option'], 0);

            if ($body_id <= 0) {
                continue;
            }

            $patterns[$slug] = [
                'slug'      => $slug,
                'label'     => $pattern['label'],
                'body_id'   => $body_id,
                'dashboard' => (bool) $pattern['dashboard'],
            ];
        }

        /**
         * افزودن/حذف الگو بدون تغییر این پلاگین. هر ردیف باید حداقل
         * label و body_id داشته باشد؛ ردیف نامعتبر بی‌صدا حذف می‌شود تا
         * ارسال با bodyId صفر هرگز اتفاق نیفتد.
         */
        $filtered = apply_filters('pas_sms_patterns', $patterns);

        if (!is_array($filtered)) {
            return $patterns;
        }

        $clean = [];

        foreach ($filtered as $slug => $row) {
            if (!is_array($row)) {
                continue;
            }

            $slug    = sanitize_key((string) $slug);
            $body_id = (int) ($row['body_id'] ?? 0);

            if ($slug === '' || $body_id <= 0) {
                continue;
            }

            $clean[$slug] = [
                'slug'      => $slug,
                'label'     => (string) ($row['label'] ?? $slug),
                'body_id'   => $body_id,
                'dashboard' => !empty($row['dashboard']),
            ];
        }

        return $clean;
    }

    public static function get(string $slug): ?array
    {
        $all = self::all();

        return $all[$slug] ?? null;
    }

    /** الگوهایی که داشبورد مجاز است با کپابیلیتی و دامنه بفرستد. */
    public static function dashboard_patterns(): array
    {
        return array_filter(self::all(), static fn (array $pattern): bool => !empty($pattern['dashboard']));
    }

    /** برچسب الگو بر اساس bodyId — برای نمایش لاگ ارسال. */
    public static function label_for_body_id(int $body_id): string
    {
        foreach (self::all() as $pattern) {
            if ((int) $pattern['body_id'] === $body_id) {
                return (string) $pattern['label'];
            }
        }

        return '';
    }

    /**
     * ارسال یک الگوی مشخص. bodyId همیشه از رجیستری می‌آید (هرگز از ورودی
     * کاربر)، و ارسال از همان فیلتر مشترک pas_send_sms عبور می‌کند.
     *
     * @param array<int,string> $params مقادیر جای‌نگهدارهای همان الگو، به ترتیب
     */
    public static function send(string $slug, string $mobile, array $params = []): bool
    {
        $pattern = self::get($slug);

        if (!$pattern) {
            return false;
        }

        $params = array_values(array_map(
            static fn ($value): string => (string) $value,
            $params
        ));

        return (bool) apply_filters('pas_send_sms', null, $mobile, (int) $pattern['body_id'], $params);
    }
}
