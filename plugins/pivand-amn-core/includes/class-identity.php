<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * سرویس هویت واحد (فاز ۲/۳) — تنها راه مجاز گرفتن `client_id` برای یک مراجع.
 *
 * رابطه با `Pas_Core_Client_Registry`:
 *  - رجیستری، ایندکسِ فقط‌خواندنی است و با «بازسازی» از دادهٔ افزونه‌ها ساخته
 *    می‌شود؛ هیچ‌وقت در زمان درخواست مراجع نوشته نمی‌شد.
 *  - این کلاس لایهٔ بالای همان رجیستری است: اول از ایندکس می‌خواند؛ فقط اگر
 *    آن شماره **هیچ‌جا** ایندکس نشده باشد، یک هویت «موقت» با یک شناسهٔ یکتا
 *    می‌سازد تا همان لحظه هم شناسه داشته باشیم و رکوردها با شمارهٔ خام به هم
 *    گره نخورند.
 *
 * قواعد غیرقابل مذاکره (هم‌راستا با طراحی رجیستری):
 *  - **هیچ ادغامی انجام نمی‌شود.** اگر یک شماره به بیش از یک `client_id` اشاره
 *    کند، تابع رشتهٔ خالی برمی‌گرداند؛ یعنی «نامشخص»، نه «یکی از آن‌ها».
 *  - اتصال فقط با شناسهٔ یکسان است (موبایل نرمال‌شده / ایمیل). نام و شمارهٔ
 *    همسر هرگز باعث اتصال نمی‌شوند.
 *  - هیچ فیلد بالینی خوانده یا نوشته نمی‌شود؛ فقط شناسه، نام نمایشی و زمان.
 *  - اگر جداول رجیستری نباشند (هستهٔ قدیمی/غیرفعال)، همه‌چیز بی‌خطا رشتهٔ
 *    خالی برمی‌گرداند و افزونهٔ مصرف‌کننده مثل قبل با شمارهٔ تلفن کار می‌کند.
 */
final class Pas_Core_Identity
{
    public const IDENTIFIER_MOBILE = 'mobile';
    public const IDENTIFIER_EMAIL  = 'email';

    /** منبع ردیف‌هایی که این سرویس خودش می‌سازد (قابل تفکیک از دادهٔ بازسازی). */
    private const PROVISIONAL_SOURCE = 'identity-service';

    // ── نرمال‌سازی ───────────────────────────────────────────────────────

    public static function normalize_mobile(string $phone): string
    {
        if (class_exists('Pas_Core_Helpers') && method_exists('Pas_Core_Helpers', 'normalize_mobile')) {
            return (string) Pas_Core_Helpers::normalize_mobile($phone);
        }

        $digits = preg_replace('/\D+/', '', $phone);
        if (strlen((string) $digits) === 11 && str_starts_with((string) $digits, '0')) {
            return (string) $digits;
        }
        if (strlen((string) $digits) === 12 && str_starts_with((string) $digits, '98')) {
            return '0' . substr((string) $digits, 2);
        }
        if (strlen((string) $digits) === 10 && str_starts_with((string) $digits, '9')) {
            return '0' . (string) $digits;
        }

        return '';
    }

    public static function normalize_email(string $email): string
    {
        $email = strtolower(trim($email));

        return is_email($email) ? $email : '';
    }

    // ── خواندن شناسه ────────────────────────────────────────────────────

    /**
     * شناسهٔ مراجع از روی موبایل. رشتهٔ خالی = نامشخص یا دارای تعارض.
     */
    public static function client_id_for_phone(string $phone): string
    {
        $mobile = self::normalize_mobile($phone);
        if ($mobile === '') {
            return '';
        }

        $ids = self::client_ids_for(self::IDENTIFIER_MOBILE, $mobile);

        return count($ids) === 1 ? $ids[0] : '';
    }

    /**
     * شناسهٔ مراجع از روی ایمیل. رشتهٔ خالی = نامشخص یا دارای تعارض.
     */
    public static function client_id_for_email(string $email): string
    {
        $email = self::normalize_email($email);
        if ($email === '') {
            return '';
        }

        $ids = self::client_ids_for(self::IDENTIFIER_EMAIL, $email);

        return count($ids) === 1 ? $ids[0] : '';
    }

    /**
     * هویت کامل از طریق قرارداد رسمی هسته — برای مصرف‌کننده‌هایی که به
     * شناسه و ایمیل/موبایل با هم نیاز دارند. بدون کوئری مستقیم به جداول
     * رجیستری در آن افزونه‌ها.
     *
     * @param array{client_id?:string,type?:string,value?:string,wp_user_id?:int} $query
     * @return array<string,mixed>|null
     */
    public static function resolve(array $query): ?array
    {
        $client = apply_filters('pas_get_client', null, $query);

        return is_array($client) ? $client : null;
    }

    // ── ساختن هویت موقت (فقط وقتی هیچ ایندکسی وجود ندارد) ──────────────

    /**
     * شناسهٔ پایدار برای یک موبایل؛ اگر لازم باشد هویت موقت می‌سازد.
     *
     * @return string شناسهٔ مراجع یا رشتهٔ خالی (تعارض/نبود جدول/شمارهٔ نامعتبر)
     */
    public static function ensure_for_phone(string $phone, string $display_name = ''): string
    {
        global $wpdb;

        $mobile = self::normalize_mobile($phone);
        if ($mobile === '') {
            return '';
        }

        $ids = self::client_ids_for(self::IDENTIFIER_MOBILE, $mobile);
        if (count($ids) === 1) {
            return $ids[0];
        }
        if (count($ids) > 1) {
            // تعارض هویت: عمداً هیچ شناسه‌ای انتخاب نمی‌شود.
            return '';
        }

        $clients     = Pas_Core_Client_Registry::table(Pas_Core_Client_Registry::TABLE_CLIENTS);
        $identifiers = Pas_Core_Client_Registry::table(Pas_Core_Client_Registry::TABLE_IDENTIFIERS);

        // هستهٔ قدیمی/غیرفعال: جداول موجود نیستند → بی‌صدا رشتهٔ خالی.
        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $clients)) !== $clients) {
            return '';
        }
        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $identifiers)) !== $identifiers) {
            return '';
        }

        $client_id = self::new_client_id();
        $now       = current_time('mysql', true);
        $name      = mb_substr(sanitize_text_field($display_name), 0, 190);

        $wpdb->insert($clients, [
            'client_id'      => $client_id,
            'display_name'   => $name,
            'primary_mobile' => $mobile,
            'primary_email'  => '',
            'status'         => 'active',
            'conflict_count' => 0,
            'sources_count'  => 1,
            'created_at'     => $now,
            'updated_at'     => $now,
        ]);

        $hash = md5(self::IDENTIFIER_MOBILE . '|' . $mobile . '|' . self::PROVISIONAL_SOURCE . '|provisional');

        // INSERT IGNORE: اگر هم‌زمان رجیستری همین شناسه را ساخته باشد،
        // ردیف او دست‌نخورده می‌ماند و ما شناسهٔ او را می‌خوانیم.
        $wpdb->query($wpdb->prepare(
            "INSERT IGNORE INTO $identifiers
                (client_id, identifier_type, identifier_value, identifier_hash, source, source_ref, display_name, created_at, last_seen_at)
             VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s)",
            $client_id,
            self::IDENTIFIER_MOBILE,
            $mobile,
            $hash,
            self::PROVISIONAL_SOURCE,
            'provisional',
            $name,
            $now,
            $now
        ));

        $ids = self::client_ids_for(self::IDENTIFIER_MOBILE, $mobile);

        return count($ids) === 1 ? $ids[0] : '';
    }

    // ── کمکی‌ها ─────────────────────────────────────────────────────────

    /**
     * همهٔ شناسه‌های یکتا برای یک شناسهٔ مشخص (بدون ادغام).
     *
     * @return string[]
     */
    private static function client_ids_for(string $type, string $value): array
    {
        global $wpdb;

        if ($type === '' || $value === '') {
            return [];
        }

        $identifiers = Pas_Core_Client_Registry::table(Pas_Core_Client_Registry::TABLE_IDENTIFIERS);
        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $identifiers)) !== $identifiers) {
            return [];
        }

        $ids = $wpdb->get_col($wpdb->prepare(
            "SELECT DISTINCT client_id FROM $identifiers WHERE identifier_type = %s AND identifier_value = %s LIMIT 5",
            $type,
            $value
        ));

        return array_values(array_filter(array_map('strval', (array) $ids), static fn (string $id): bool => $id !== ''));
    }

    private static function new_client_id(): string
    {
        // char(29) در رجیستری: پیشوند pc_ + ۲۶ کاراکتر کوچک.
        return 'pc_' . strtolower(wp_generate_password(26, false, false));
    }
}
