<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * داشبورد امنیتی و انطباق (فاز ۸) — فقط‌خواندنی.
 *
 * هشدارهای پراکنده‌ی فاز ۰ تا ۷ را در یک فهرست جمع می‌کند: وضعیت کلید
 * رمزنگاری، اعتبارنامه‌ی لو‌رفته‌ی پیامک، هم‌خوانی نسخه‌ی اسکیمای هر افزونه،
 * وجود جدول‌های ثبت‌شده، و تعارض‌های باز هویت.
 *
 * قواعد عمدی:
 *  - هیچ نوشتنی انجام نمی‌شود.
 *  - هیچ مقدار رمزنگاری‌شده/رمزگشایی‌شده یا کلید چاپ نمی‌شود.
 *  - فقط جدول‌های خودِ هسته شمارش می‌شوند؛ برای سایر افزونه‌ها فقط «وجود/
 *    نبود» جدول بررسی می‌شود، نه تعداد ردیف‌ها (مالکیت داده).
 */
final class Pas_Core_Compliance
{
    public const STATUS_OK   = 'ok';
    public const STATUS_WARN = 'warn';
    public const STATUS_FAIL = 'fail';
    public const STATUS_INFO = 'info';

    /**
     * @return array<int,array{key:string,title:string,status:string,detail:string}>
     */
    public static function checks(): array
    {
        $checks = [];

        $checks[] = self::encryption_check();
        $checks[] = self::sms_credentials_check();

        foreach (self::schema_checks() as $schema) {
            $checks[] = $schema;
        }

        $checks[] = self::tables_check();
        $checks[] = self::identity_conflicts_check();

        /**
         * افزونه‌های دیگر می‌توانند بررسی خودشان را اضافه کنند (بدون دسترسی
         * به کلاس‌های هسته). هر آیتم باید کلیدهای key/title/status/detail
         * داشته باشد و status یکی از ok|warn|fail|info باشد.
         */
        $extra = apply_filters('pas_compliance_checks', []);

        if (is_array($extra)) {
            foreach ($extra as $item) {
                if (!is_array($item) || empty($item['title'])) {
                    continue;
                }
                $checks[] = [
                    'key'    => (string) ($item['key'] ?? md5((string) $item['title'])),
                    'title'  => (string) $item['title'],
                    'status' => in_array(($item['status'] ?? ''), [self::STATUS_OK, self::STATUS_WARN, self::STATUS_FAIL, self::STATUS_INFO], true)
                        ? (string) $item['status']
                        : self::STATUS_INFO,
                    'detail' => (string) ($item['detail'] ?? ''),
                ];
            }
        }

        return $checks;
    }

    /** شمارش وضعیت‌ها برای نمایش خلاصه در بالای صفحه. */
    public static function summary(array $checks): array
    {
        $out = [self::STATUS_OK => 0, self::STATUS_WARN => 0, self::STATUS_FAIL => 0, self::STATUS_INFO => 0];

        foreach ($checks as $check) {
            $status = (string) ($check['status'] ?? self::STATUS_INFO);
            $out[$status] = ($out[$status] ?? 0) + 1;
        }

        return $out;
    }

    private static function encryption_check(): array
    {
        $key = class_exists('Pas_Core_Data_Audit')
            ? Pas_Core_Data_Audit::key_status()
            : ['configured' => class_exists('Pas_Core_Crypto') && Pas_Core_Crypto::is_configured(), 'source' => '—'];

        return [
            'key'    => 'encryption',
            'title'  => 'کلید رمزنگاری اکوسیستم',
            'status' => !empty($key['configured']) ? self::STATUS_OK : self::STATUS_FAIL,
            'detail' => !empty($key['configured'])
                ? 'تنظیم شده — منبع: ' . (string) ($key['source'] ?? '—')
                : 'تنظیم نشده؛ یادداشت‌های بالینی و نتایج آزمون قابل رمزنگاری/رمزگشایی نیستند.',
        ];
    }

    private static function sms_credentials_check(): array
    {
        if (!class_exists('Pas_Melipayamak')) {
            return [
                'key'    => 'sms_credentials',
                'title'  => 'اعتبارنامه‌ی پل پیامک',
                'status' => self::STATUS_INFO,
                'detail' => 'پل پیامک فعال نیست (بررسی‌ای انجام نشد).',
            ];
        }

        $leaked = method_exists('Pas_Melipayamak', 'uses_leaked_credentials')
            && Pas_Melipayamak::uses_leaked_credentials();

        return [
            'key'    => 'sms_credentials',
            'title'  => 'اعتبارنامه‌ی پل پیامک',
            'status' => $leaked ? self::STATUS_FAIL : self::STATUS_OK,
            'detail' => $leaked
                ? 'اعتبارنامه‌ی قدیمیِ لو‌رفته هنوز در استفاده است — رمز را از ملی‌پیامک عوض کن و در wp-config یا تنظیمات پل پیامک بگذار.'
                : 'اعتبارنامه‌ی ذخیره‌شده لو‌رفته نیست.',
        ];
    }

    /**
     * هم‌خوانی نسخه‌ی ثبت‌شده‌ی اسکیما با نسخه‌ی کد هر افزونه.
     *
     * @return array<int,array<string,string>>
     */
    private static function schema_checks(): array
    {
        $targets = [];

        if (class_exists('Pas_Core_Client_Registry')) {
            $targets[] = [
                'key'    => 'schema_core',
                'title'  => 'اسکیمای هسته (رجیستری هویت)',
                'option' => 'pas_core_db_version',
                'code'   => Pas_Core_Client_Registry::DB_VERSION,
            ];
        }

        if (class_exists('Pas_Core_Field_Mapper')) {
            $targets[] = [
                'key'    => 'schema_mapper',
                'title'  => 'اسکیمای نگاشت فیلد (فاز ۲)',
                'option' => 'pas_mapper_db_version',
                'code'   => Pas_Core_Field_Mapper::DB_VERSION,
            ];
        }

        if (defined('PAS_CF_VERSION')) {
            $targets[] = [
                'key'    => 'schema_case_file',
                'title'  => 'اسکیمای پرونده مراجعین',
                'option' => 'pas_cf_db_version',
                'code'   => (string) PAS_CF_VERSION,
            ];
        }

        $checks = [];

        foreach ($targets as $target) {
            $stored = (string) get_option($target['option'], '');
            $code   = (string) $target['code'];

            if ($stored === $code) {
                $status = self::STATUS_OK;
                $detail = 'ثبت‌شده: ' . $stored;
            } elseif ($stored === '') {
                $status = self::STATUS_WARN;
                $detail = 'نسخه‌ی اسکیما ثبت نشده (کد: ' . $code . ') — یک‌بار صفحه‌های افزونه را باز کن تا نصب/ارتقا اجرا شود.';
            } else {
                $status = self::STATUS_WARN;
                $detail = 'ثبت‌شده: ' . $stored . ' — کد: ' . $code . ' (ارتقای دیتابیس اجرا نشده)';
            }

            $checks[] = [
                'key'    => $target['key'],
                'title'  => $target['title'],
                'status' => $status,
                'detail' => $detail,
            ];
        }

        return $checks;
    }

    private static function tables_check(): array
    {
        global $wpdb;

        if (!class_exists('Pas_Core_Table_Registry')) {
            return [
                'key'    => 'tables',
                'title'  => 'جدول‌های ثبت‌شده',
                'status' => self::STATUS_INFO,
                'detail' => 'رجیستری جدول‌ها در دسترس نیست.',
            ];
        }

        $missing = [];
        $total   = 0;

        foreach (Pas_Core_Table_Registry::get_all() as $group) {
            foreach ((array) ($group['tables'] ?? []) as $table) {
                $full = $wpdb->prefix . $table;
                $total++;

                if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $full)) !== $full) {
                    $missing[] = $full;
                }
            }
        }

        return [
            'key'    => 'tables',
            'title'  => 'جدول‌های ثبت‌شده',
            'status' => empty($missing) ? self::STATUS_OK : self::STATUS_WARN,
            'detail' => empty($missing)
                ? $total . ' جدول ثبت‌شده، همه موجود.'
                : count($missing) . ' از ' . $total . ' جدول موجود نیست: ' . implode(', ', array_slice($missing, 0, 5))
                    . (count($missing) > 5 ? ' …' : ''),
        ];
    }

    private static function identity_conflicts_check(): array
    {
        global $wpdb;

        if (!class_exists('Pas_Core_Client_Registry')) {
            return [
                'key'    => 'identity_conflicts',
                'title'  => 'تعارض‌های هویتی باز',
                'status' => self::STATUS_INFO,
                'detail' => 'رجیستری هویت در دسترس نیست.',
            ];
        }

        $table = Pas_Core_Client_Registry::table(Pas_Core_Client_Registry::TABLE_CONFLICTS);

        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) !== $table) {
            return [
                'key'    => 'identity_conflicts',
                'title'  => 'تعارض‌های هویتی باز',
                'status' => self::STATUS_INFO,
                'detail' => 'جدول تعارض‌ها هنوز ساخته نشده.',
            ];
        }

        $open = (int) $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE status = 'open'");

        return [
            'key'    => 'identity_conflicts',
            'title'  => 'تعارض‌های هویتی باز',
            'status' => $open > 0 ? self::STATUS_WARN : self::STATUS_OK,
            'detail' => $open > 0
                ? $open . ' تعارض باز — در بخش «رجیستری هویت مراجع» بررسی کن (ادغام خودکار انجام نمی‌شود).'
                : 'تعارض بازی نیست.',
        ];
    }
}
