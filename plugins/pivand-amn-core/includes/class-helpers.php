<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * توابع مشترک اکوسیستم. هدف: هر افزونه به‌جای نوشتن نسخه‌ی خودش، از اینجا
 * استفاده کند تا رفتار (مثلاً فرمت نرمال‌سازی موبایل) در کل اکوسیستم یکسان
 * بماند.
 */
final class Pas_Core_Helpers
{
    /**
     * نرمال‌سازی شماره موبایل ایرانی به فرمت محلی یکتای 09xxxxxxxxx.
     * این نسخه، همان منطق اثبات‌شده‌ی to_local_format در پنل مراجعان است —
     * فقط اینجا به‌عنوان مرجع مشترک منتقل شده تا افزونه‌های دیگر (پرونده
     * مراجعین، و در آینده خود پنل مراجعان) به‌جای نسخه‌ی محلی خودشان از
     * همین استفاده کنند.
     * قبول می‌کند: 09xxxxxxxxx ، 9xxxxxxxxx ، +989xxxxxxxxx ، 00989xxxxxxxxx
     * و ارقام فارسی/عربی (۰۹۱۲…/٠٩١٢…). ارقام پیش از حذف نویسه‌های غیرعددی
     * به ASCII تبدیل می‌شوند، وگرنه `\D` آن‌ها را هم دور می‌ریخت و خروجی
     * فقط «0» می‌شد.
     */
    public static function normalize_mobile(string $phone): string
    {
        $phone = strtr(
            $phone,
            [
                '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
                '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
                '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
                '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
            ]
        );

        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        if (str_starts_with($digits, '0098')) {
            $digits = substr($digits, 4);
        } elseif (str_starts_with($digits, '98')) {
            $digits = substr($digits, 2);
        } elseif (str_starts_with($digits, '0')) {
            $digits = substr($digits, 1);
        }

        return '0' . $digits;
    }

    public static function is_valid_iranian_mobile(string $phone): bool
    {
        return (bool) preg_match('/^09\d{9}$/', self::normalize_mobile($phone));
    }

    /**
     * فرمت بین‌المللی برای لینک واتساپ (989xxxxxxxxx، بدون +). این همان
     * نیاز جداگانه‌ای است که quiz-enterprise دارد (متفاوت از فرمت محلی
     * ذخیره‌سازی) — پس تابع جدا، نه یک گزینه‌ی اضافه روی همان تابع.
     */
    public static function to_whatsapp_international(string $phone): string
    {
        $local = self::normalize_mobile($phone); // 09xxxxxxxxx
        return '98' . substr($local, 1);
    }

    /**
     * IP واقعی مراجع، با در نظر گرفتن CDN/پراکسی‌های رایج (ArvanCloud,
     * Cloudflare).
     *
     * فیکس امنیتی: هدرهای CDN (X-Forwarded-For و مشابه) فقط زمانی خوانده
     * می‌شوند که REMOTE_ADDR واقعی درخواست داخل یکی از رنج‌های CIDR
     * پراکسی‌های مورد اعتماد باشد. بدون این چک، هر کاربری می‌توانست با ست
     * کردن هدر X-Forwarded-For جعلی، IP دلخواه خودش را جا بزند و
     * rate-limitهای IP-محور (OTP، فرم تماس، فید) را دور بزند — این دقیقاً
     * همان چیزی بود که قبلاً به‌عنوان فیکس امنیتی روی نسخه‌ی پنل مراجعان
     * اعمال شده بود ولی موقع تجمیع در هسته‌ی مشترک، از قلم افتاده بود.
     *
     * رنج‌ها با فیلتر pas_core_trusted_proxy_cidrs یا آپشن
     * pas_core_trusted_proxy_cidrs (رشته‌ی جدا‌شده با کاما) قابل تنظیم‌اند؛
     * پیش‌فرض، رنج‌های منتشرشده‌ی Cloudflare است.
     */
    public static function get_client_ip(): string
    {
        $remote_addr = isset($_SERVER['REMOTE_ADDR'])
            ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR']))
            : '';

        if ($remote_addr !== '' && self::is_trusted_proxy($remote_addr)) {
            $candidate_headers = [
                'HTTP_CF_CONNECTING_IP',   // Cloudflare
                'HTTP_X_REAL_IP',          // ArvanCloud / Nginx proxy رایج
                'HTTP_X_FORWARDED_FOR',
            ];

            foreach ($candidate_headers as $header) {
                if (empty($_SERVER[$header])) {
                    continue;
                }

                $raw   = sanitize_text_field(wp_unslash($_SERVER[$header]));
                $first = trim(explode(',', $raw)[0]);

                if (filter_var($first, FILTER_VALIDATE_IP)) {
                    return apply_filters('pas_core_client_ip', $first);
                }
            }
        }

        $ip = filter_var($remote_addr, FILTER_VALIDATE_IP) ? $remote_addr : '0.0.0.0';

        return apply_filters('pas_core_client_ip', $ip);
    }

    /**
     * آیا این IP داخل یکی از رنج‌های پراکسی مورد اعتماد است؟
     */
    private static function is_trusted_proxy(string $ip): bool
    {
        foreach (self::trusted_proxy_cidrs() as $cidr) {
            if (self::ip_in_cidr($ip, $cidr)) {
                return true;
            }
        }

        return false;
    }

    /**
     * لیست CIDR پراکسی‌های مورد اعتماد. اولویت با آپشن دستی ادمین
     * (pas_core_trusted_proxy_cidrs، جدا‌شده با کاما)؛ در غیر این صورت
     * رنج‌های منتشرشده‌ی Cloudflare به‌عنوان پیش‌فرض امن.
     */
    private static function trusted_proxy_cidrs(): array
    {
        $option = get_option('pas_core_trusted_proxy_cidrs', '');

        if (is_string($option) && trim($option) !== '') {
            $custom = array_filter(array_map('trim', explode(',', $option)));
            return apply_filters('pas_core_trusted_proxy_cidrs', array_values($custom));
        }

        $defaults = [
            '173.245.48.0/20', '103.21.244.0/22', '103.22.200.0/22', '103.31.4.0/22',
            '141.101.64.0/18', '108.162.192.0/18', '190.93.240.0/20', '188.114.96.0/20',
            '197.234.240.0/22', '198.41.128.0/17', '162.158.0.0/15', '104.16.0.0/13',
            '104.24.0.0/14', '172.64.0.0/13', '131.0.72.0/22',
        ];

        return apply_filters('pas_core_trusted_proxy_cidrs', $defaults);
    }

    /**
     * بررسی عضویت یک IPv4 در یک بلوک CIDR. IPv6 عمداً پشتیبانی نمی‌شود
     * (این سایت‌ها پشت CDN با IPv4 سرو می‌شوند)؛ در صورت نیاز بعداً گسترش
     * داده شود.
     */
    private static function ip_in_cidr(string $ip, string $cidr): bool
    {
        if (strpos($ip, ':') !== false) {
            return false;
        }

        if (strpos($cidr, '/') === false) {
            return $ip === $cidr;
        }

        [$subnet, $bits] = explode('/', $cidr, 2);
        $bits = (int) $bits;

        $ip_long     = ip2long($ip);
        $subnet_long = ip2long($subnet);

        if ($ip_long === false || $subnet_long === false || $bits < 0 || $bits > 32) {
            return false;
        }

        $mask = $bits === 0 ? 0 : (-1 << (32 - $bits));

        return ($ip_long & $mask) === ($subnet_long & $mask);
    }

    /** آماده برای گسترش‌های آینده (مثلاً پشتیبانی شماره‌ی غیرایرانی). */
    public static function version(): string
    {
        return PAS_CORE_VERSION;
    }
}
