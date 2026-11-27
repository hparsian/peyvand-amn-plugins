<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * پورت‌شده از cpp_send_sms_pattern در custom-psychology-portal، با یک رفع
 * باگ مهم: وب‌سرویس ملی پیامک تقریباً همیشه HTTP 200 برمی‌گرداند، حتی وقتی
 * ارسال واقعاً شکست خورده (اعتبار ناکافی، پترن نامعتبر، شماره مسدود و...)؛
 * نتیجه‌ی واقعی داخل بدنه‌ی JSON پاسخ، در فیلد RetStatus است (۱ = موفق).
 * نسخه‌ی قبلی فقط کد HTTP را چک می‌کرد، یعنی خطاهای منطقی ملی پیامک را
 * «موفق» تلقی می‌کرد — که یعنی کد OTP در واقع هیچ‌وقت به دست مراجع نمی‌رسید
 * ولی سیستم فکر می‌کرد ارسال شده.
 */
final class Pas_Melipayamak
{
    private const API_URL = 'https://rest.payamak-panel.com/api/SendSMS/BaseServiceNumber';

    /**
     * SHA-256 اعتبارنامه‌ی پیش‌فرضی که در نسخه‌های ≤1.1.5 داخل کد هاردکد
     * شده بود. مقدار اصلی عمداً هیچ‌جا نگه داشته نمی‌شود؛ فقط هش، تا بتوان
     * تشخیص داد یک نصب قدیمی هنوز همان مقدار لو‌رفته را استفاده می‌کند و
     * در پیشخوان هشدار داد. (مقایسه با hash_equals انجام می‌شود.)
     */
    private const LEAKED_PASSWORD_SHA256 = '8dd4e3df519540af95839fb0b92501d72b913f9d7a177cf9a74816a62726ba52';

    /**
     * مرجع کدهای RetStatus ملی پیامک (طبق مستندات رسمی وب‌سرویس).
     */
    private const RET_STATUS_MESSAGES = [
        1  => 'موفق',
        2  => 'اعتبار کافی نیست',
        3  => 'محدودیت در ارسال روزانه',
        4  => 'محدودیت در حجم ارسال',
        5  => 'کد بازگشتی متن نامعتبر است',
        6  => 'شماره فرستنده نامعتبر است',
        7  => 'یکی از شماره‌های گیرنده نامعتبر است',
        9  => 'خط ارسالی فعال نیست',
        10 => 'کاربری غیرفعال است',
        11 => 'کاربری در وضعیت تعلیق است',
        12 => 'محدودیت به دلیل ارسال شکایت',
        14 => 'متن حاوی کلمه‌ی فیلترشده است',
        15 => 'محدودیت روزانه‌ی خط',
        16 => 'خطای داخلی -۱۶',
        17 => 'محدودیت زمانی بین دو ارسال از یک IP',
        35 => 'پترن یافت نشد یا تایید نشده است',
    ];

    /**
     * @param mixed $default
     * @param mixed $mobile
     * @param mixed $body_id
     * @param mixed $params
     */
    public static function handle_send_sms_filter($default, $mobile, $body_id, $params = []): bool
    {
        $mobile  = self::normalize_mobile((string) $mobile);
        $body_id = (int) $body_id;
        $params  = is_array($params) ? $params : [];

        if ($mobile === '' || $body_id <= 0) {
            Pas_Log::record($mobile, $body_id, false, 'شماره یا کد پترن نامعتبر است.');

            return false;
        }

        // فاز ۰ (1.1.6): اعتبارنامه از ثابت‌های wp-config.php یا آپشن‌های
        // پیشخوان خوانده می‌شود — دیگر هیچ مقدار پیش‌فرضی در کد نیست.
        [$username, $password] = self::credentials();

        if ($username === '' || $password === '') {
            Pas_Log::record($mobile, $body_id, false, 'نام‌کاربری/رمز ملی پیامک تنظیم نشده.');

            return false;
        }

        // ملی پیامک برای پترن‌ها placeholder نام‌دار ندارد؛ فقط ترتیب مقادیر
        // مهم است، دقیقاً همان ترتیبی که خودِ پترن در پنل ملی پیامک تعریف
        // شده. پس فقط VALUEها را (نه کلیدهای CODE/TOPIC/...) به ترتیب با
        // semicolon به هم وصل می‌کنیم.
        $text_value = implode(';', array_map('strval', array_values($params)));

        $response = wp_remote_post(self::API_URL, [
            'timeout'     => 15,
            'headers'     => ['Content-Type' => 'application/json'],
            'data_format' => 'body',
            'body'        => wp_json_encode([
                'username' => $username,
                'password' => $password,
                'text'     => $text_value,
                'to'       => $mobile,
                'bodyId'   => $body_id,
            ]),
        ]);

        if (is_wp_error($response)) {
            Pas_Log::record($mobile, $body_id, false, 'خطای اتصال: ' . $response->get_error_message());

            return false;
        }

        $http_code = wp_remote_retrieve_response_code($response);
        $body_raw  = wp_remote_retrieve_body($response);
        $decoded   = json_decode($body_raw, true);

        // ساختار پاسخ BaseServiceNumber ملی پیامک: { "Value": "...", "RetStatus": N, "StrRetStatus": "..." }
        $ret_status = isset($decoded['RetStatus']) ? (int) $decoded['RetStatus'] : null;

        if ($http_code < 200 || $http_code >= 300) {
            Pas_Log::record($mobile, $body_id, false, "کد HTTP غیرمنتظره: {$http_code}");

            return false;
        }

        if ($ret_status === 1) {
            Pas_Log::record($mobile, $body_id, true, 'ارسال موفق');

            return true;
        }

        $reason = self::RET_STATUS_MESSAGES[$ret_status] ?? ('کد نامشخص RetStatus=' . ($ret_status ?? 'null'));
        Pas_Log::record($mobile, $body_id, false, $reason);

        return false;
    }

    /**
     * اعتبارنامه‌ی در حال استفاده. ترتیب اولویت:
     *   ۱. ثابت‌های PAS_SMS_USERNAME / PAS_SMS_PASSWORD در wp-config.php
     *   ۲. آپشن‌های pas_sms_username / pas_sms_password (پیشخوان)
     * اگر فقط یکی از دو ثابت تعریف شده باشد، هر دو از آپشن‌ها خوانده می‌شوند
     * تا هرگز نام‌کاربری و رمزِ دو منبع مختلف با هم ترکیب نشوند.
     *
     * @return array{0:string,1:string}
     */
    public static function credentials(): array
    {
        $constant_user = defined('PAS_SMS_USERNAME') ? trim((string) constant('PAS_SMS_USERNAME')) : '';
        $constant_pass = defined('PAS_SMS_PASSWORD') ? trim((string) constant('PAS_SMS_PASSWORD')) : '';

        if ($constant_user !== '' && $constant_pass !== '') {
            return [$constant_user, $constant_pass];
        }

        $username = (string) get_option('pas_sms_username', '');
        $password = (string) get_option('pas_sms_password', '');

        // unwrap_decrypt مقادیر قدیمیِ رمزنگاری‌نشده را دست‌نخورده برمی‌گرداند،
        // پس نیازی به migration دستی رمزهای فعلی نیست.
        if ($password !== '' && class_exists('Pas_Core_Crypto')) {
            $password = Pas_Core_Crypto::unwrap_decrypt($password);
        }

        return [$username, $password];
    }

    /**
     * منبع اعتبارنامه‌ی فعلی: 'constants' | 'options' | 'missing'.
     * فقط برای نمایش وضعیت در پیشخوان — هیچ مقدار حساسی برنمی‌گرداند.
     */
    public static function credentials_source(): string
    {
        $constant_user = defined('PAS_SMS_USERNAME') ? trim((string) constant('PAS_SMS_USERNAME')) : '';
        $constant_pass = defined('PAS_SMS_PASSWORD') ? trim((string) constant('PAS_SMS_PASSWORD')) : '';

        if ($constant_user !== '' && $constant_pass !== '') {
            return 'constants';
        }

        [$username, $password] = self::credentials();

        return ($username === '' || $password === '') ? 'missing' : 'options';
    }

    public static function has_credentials(): bool
    {
        return self::credentials_source() !== 'missing';
    }

    /**
     * آیا رمز در حال استفاده همان مقدار پیش‌فرض لو‌رفته‌ی نسخه‌های ≤1.1.5 است؟
     * فقط هش مقایسه می‌شود؛ مقدار رمز نه خوانده می‌شود نه چاپ.
     */
    public static function uses_leaked_credentials(): bool
    {
        [, $password] = self::credentials();

        if ($password === '') {
            return false;
        }

        return hash_equals(self::LEAKED_PASSWORD_SHA256, hash('sha256', $password));
    }

    /**
     * تنظیمات غیرحساس اتصال برای فرم داشبورد یکپارچه (1.1.10).
     *
     * فقط نام‌کاربری و «رمز تنظیم شده/نشده» برمی‌گردد؛ رمز هرگز از این متد
     * بیرون نمی‌آید.
     *
     * @return array{username:string,has_password:bool,source:string}
     */
    public static function dashboard_settings(): array
    {
        return [
            'username'     => (string) get_option('pas_sms_username', ''),
            'has_password' => (string) get_option('pas_sms_password', '') !== '',
            'source'       => self::credentials_source(),
        ];
    }

    /**
     * ذخیرهٔ اعتبارنامهٔ ملی پیامک از داشبورد یکپارچه (1.1.10).
     *
     * کپابیلیتی، nonce، ممیزی و ریدایرکت کار Action Bus داشبورد است. فیلد رمز
     * تهی یعنی «رمز فعلی را حفظ کن» (همان قاعده‌ی صفحهٔ خود پل پیامک). اگر
     * اعتبارنامه از ثابت‌های wp-config.php بیاید، ثابت‌ها اولویت دارند و ذخیرهٔ
     * آپشن بی‌اثر است — پس صریحاً رد می‌شود تا کاربر گمراه نشود.
     *
     * @param array<string,mixed> $input
     * @return array{ok:bool,message:string}
     */
    public static function dashboard_save_settings(array $input): array
    {
        if (self::credentials_source() === 'constants') {
            return [
                'ok'      => false,
                'message' => 'اعتبارنامهٔ پیامک از ثابت‌های PAS_SMS_USERNAME/PAS_SMS_PASSWORD در wp-config.php خوانده می‌شود؛ تا وقتی آن‌ها تعریف شده‌اند، ذخیره از داشبورد اثری ندارد.',
            ];
        }

        if (array_key_exists('username', $input)) {
            update_option('pas_sms_username', sanitize_text_field((string) $input['username']));
        }

        $new_password = trim((string) ($input['password'] ?? ''));

        if ($new_password !== '') {
            if (class_exists('Pas_Core_Crypto') && Pas_Core_Crypto::is_configured()) {
                $new_password = Pas_Core_Crypto::wrap_encrypted($new_password);
            }

            update_option('pas_sms_password', $new_password);
        }

        return ['ok' => true, 'message' => 'تنظیمات اتصال پیامک ذخیره شد.'];
    }

    private static function normalize_mobile(string $mobile): string
    {
        if (class_exists('Pas_Core_Helpers')) {
            $local = Pas_Core_Helpers::normalize_mobile($mobile);
            return preg_match('/^09[0-9]{9}$/', $local) ? $local : '';
        }

        $digits = preg_replace('/\D+/', '', $mobile) ?? '';

        if (str_starts_with($digits, '0098')) {
            $digits = substr($digits, 4);
        } elseif (str_starts_with($digits, '98')) {
            $digits = substr($digits, 2);
        } elseif (str_starts_with($digits, '0')) {
            $digits = substr($digits, 1);
        }

        $local = '0' . $digits;

        return preg_match('/^09[0-9]{9}$/', $local) ? $local : '';
    }
}
