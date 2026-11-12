<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * سنجش فقط‌خواندنی وضعیت داده‌های رمزنگاری‌شده‌ی کل اکوسیستم (فاز ۰).
 *
 * هدف: قبل از هر مهاجرت، بدانیم چند رکورد رمزنگاری‌شده داریم، با کدام کلید
 * قابل رمزگشایی‌اند، و کدام ستون‌ها مقدار رمزنگاری‌نشده (میراث) دارند.
 *
 * محدودیت‌های عمدی این ابزار:
 *  - هیچ نوشتنی انجام نمی‌دهد: فقط SELECT و COUNT و آزمون رمزگشایی در حافظه.
 *  - هیچ مقدار خام یا رمزگشایی‌شده‌ای برنمی‌گرداند و چاپ نمی‌کند؛ فقط شمارش.
 *  - برای هر ستون حداکثر SAMPLE_LIMIT نمونه آزمون می‌شود (نه کل جدول) تا روی
 *    دیتابیس بزرگ، صفحه‌ی پیشخوان کند نشود. «قابل/غیرقابل رمزگشایی» یعنی
 *    «در این نمونه»؛ ستون «مقدار دار» تعداد واقعی کل است.
 *
 * دو شکل ذخیره‌سازی در اکوسیستم وجود دارد و هر دو پشتیبانی می‌شوند:
 *  ۱. raw  — خروجی مستقیم Pas_Core_Crypto::encrypt() (بدون پیشوند). ستون‌های
 *     پرونده مراجعین (pas_cf_*) از این شکل استفاده می‌کنند؛ چون همیشه
 *     رمزنگاری‌شده‌اند، «متن ساده» برایشان قابل سنجش نیست.
 *  ۲. wrapped — با پیشوند «enc1:» (Pas_Core_Crypto::wrap_encrypted()).
 *     آزمون‌ها (psy_attempts) و فرم ارزیابی پنل (zdp_intake) از این شکل
 *     استفاده می‌کنند؛ مقدار بدون پیشوند = میراث/رمزنگاری‌نشده.
 */
final class Pas_Core_Data_Audit
{
    public const SCAN_NONCE   = 'pas_core_data_audit_scan';
    public const SAMPLE_LIMIT = 200;

    /** وضعیت کلید رمزنگاری — بدون افشای مقدار کلید. */
    public static function key_status(): array
    {
        if (defined('PAS_CORE_ENCRYPTION_KEY')) {
            $source = 'wp-config.php (PAS_CORE_ENCRYPTION_KEY)';
        } elseif (file_exists(WP_CONTENT_DIR . '/pas-encryption-key.php')) {
            $source = 'wp-content/pas-encryption-key.php';
        } else {
            $source = 'تنظیم نشده';
        }

        return [
            'configured' => Pas_Core_Crypto::is_configured(),
            'source'     => $source,
        ];
    }

    /**
     * فهرست ستون‌های حساسی که باید سنجیده شوند. نام جدول‌ها از این فهرست
     * داخلی می‌آید (نه ورودی کاربر) و پیشوند سایت در زمان اجرا اضافه می‌شود.
     *
     * @return array<int,array{table:string,column:string,label:string,mode:string}>
     */
    public static function targets(): array
    {
        $raw     = 'raw';
        $wrapped = 'wrapped';

        return [
            ['table' => 'pas_cf_cases', 'column' => 'medical_history_encrypted', 'label' => 'پرونده — سابقه‌ی پزشکی', 'mode' => $raw],
            ['table' => 'pas_cf_cases', 'column' => 'counseling_history_encrypted', 'label' => 'پرونده — سابقه‌ی مشاوره', 'mode' => $raw],
            ['table' => 'pas_cf_cases', 'column' => 'self_harm_history_encrypted', 'label' => 'پرونده — سابقه‌ی خودآسیبی', 'mode' => $raw],
            ['table' => 'pas_cf_cases', 'column' => 'substance_use_history_encrypted', 'label' => 'پرونده — مصرف مواد', 'mode' => $raw],
            ['table' => 'pas_cf_cases', 'column' => 'spouse_medical_history_encrypted', 'label' => 'پرونده — سابقه‌ی پزشکی همسر', 'mode' => $raw],
            ['table' => 'pas_cf_cases', 'column' => 'spouse_counseling_history_encrypted', 'label' => 'پرونده — سابقه‌ی مشاوره‌ی همسر', 'mode' => $raw],
            ['table' => 'pas_cf_cases', 'column' => 'spouse_self_harm_history_encrypted', 'label' => 'پرونده — خودآسیبی همسر', 'mode' => $raw],
            ['table' => 'pas_cf_cases', 'column' => 'spouse_substance_use_history_encrypted', 'label' => 'پرونده — مصرف مواد همسر', 'mode' => $raw],
            ['table' => 'pas_cf_cases', 'column' => 'symptom_mood_depression_encrypted', 'label' => 'پرونده — علائم خلقی', 'mode' => $raw],
            ['table' => 'pas_cf_cases', 'column' => 'symptom_anxiety_encrypted', 'label' => 'پرونده — علائم اضطراب', 'mode' => $raw],
            ['table' => 'pas_cf_cases', 'column' => 'q_schema_attachment_encrypted', 'label' => 'پرونده — طرحواره/دلبستگی', 'mode' => $raw],
            ['table' => 'pas_cf_notes', 'column' => 'content_encrypted', 'label' => 'یادداشت جلسه', 'mode' => $raw],
            ['table' => 'pas_cf_note_history', 'column' => 'content_encrypted', 'label' => 'تاریخچه‌ی یادداشت جلسه', 'mode' => $raw],
            ['table' => 'psy_attempts', 'column' => 'answers', 'label' => 'آزمون — پاسخ‌ها', 'mode' => $wrapped],
            ['table' => 'psy_attempts', 'column' => 'result_data', 'label' => 'آزمون — نتیجه', 'mode' => $wrapped],
            ['table' => 'zdp_intake', 'column' => 'payload', 'label' => 'فرم ارزیابی مراجع', 'mode' => $wrapped],
        ];
    }

    /**
     * اجرای سنجش. خروجی فقط شمارش است.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function scan(): array
    {
        $rows = [];

        foreach (self::targets() as $target) {
            $rows[] = ($target['mode'] === 'wrapped')
                ? self::audit_wrapped($target)
                : self::audit_raw($target);
        }

        return $rows;
    }

    /**
     * ستون‌هایی که همیشه رمزنگاری‌شده ذخیره می‌شوند (بدون پیشوند نشانه‌گذار).
     */
    private static function audit_raw(array $target): array
    {
        global $wpdb;

        $table = $wpdb->prefix . $target['table'];
        $col   = $target['column'];

        if (!self::table_exists($table)) {
            return self::missing_row($target, $table);
        }

        $total     = (int) $wpdb->get_var("SELECT COUNT(*) FROM `$table`");
        $non_empty = (int) $wpdb->get_var("SELECT COUNT(*) FROM `$table` WHERE `$col` <> ''");

        $readable   = 0;
        $unreadable = 0;
        $sampled    = 0;

        if (Pas_Core_Crypto::is_configured() && $non_empty > 0) {
            $values = $wpdb->get_col($wpdb->prepare(
                "SELECT `$col` FROM `$table` WHERE `$col` <> '' LIMIT %d",
                self::SAMPLE_LIMIT
            ));

            foreach ((array) $values as $value) {
                $sampled++;
                if (self::decrypt_failed(Pas_Core_Crypto::decrypt((string) $value))) {
                    $unreadable++;
                } else {
                    $readable++;
                }
            }
        }

        return [
            'label'      => $target['label'],
            'table'      => $table,
            'exists'     => true,
            'total'      => $total,
            'non_empty'  => $non_empty,
            'encrypted'  => null, // برای این شکل قابل تفکیک نیست
            'plaintext'  => null,
            'readable'   => $readable,
            'unreadable' => $unreadable,
            'sampled'    => $sampled,
        ];
    }

    /**
     * ستون‌هایی که با پیشوند «enc1:» نشانه‌گذاری می‌شوند؛ مقدار بدون پیشوند
     * یعنی رکورد میراث/رمزنگاری‌نشده (که نباید حذف شود، فقط باید شناخته شود).
     */
    private static function audit_wrapped(array $target): array
    {
        global $wpdb;

        $table = $wpdb->prefix . $target['table'];
        $col   = $target['column'];

        if (!self::table_exists($table)) {
            return self::missing_row($target, $table);
        }

        $total     = (int) $wpdb->get_var("SELECT COUNT(*) FROM `$table`");
        $wrapped   = (int) $wpdb->get_var("SELECT COUNT(*) FROM `$table` WHERE `$col` LIKE 'enc1:%'");
        $plaintext = (int) $wpdb->get_var("SELECT COUNT(*) FROM `$table` WHERE `$col` <> '' AND `$col` NOT LIKE 'enc1:%'");

        $readable   = 0;
        $unreadable = 0;
        $sampled    = 0;

        if (Pas_Core_Crypto::is_configured() && $wrapped > 0) {
            $values = $wpdb->get_col($wpdb->prepare(
                "SELECT `$col` FROM `$table` WHERE `$col` LIKE 'enc1:%%' LIMIT %d",
                self::SAMPLE_LIMIT
            ));

            foreach ((array) $values as $value) {
                $sampled++;
                if (self::decrypt_failed(Pas_Core_Crypto::unwrap_decrypt((string) $value))) {
                    $unreadable++;
                } else {
                    $readable++;
                }
            }
        }

        return [
            'label'      => $target['label'],
            'table'      => $table,
            'exists'     => true,
            'total'      => $total,
            'non_empty'  => $wrapped + $plaintext,
            'encrypted'  => $wrapped,
            'plaintext'  => $plaintext,
            'readable'   => $readable,
            'unreadable' => $unreadable,
            'sampled'    => $sampled,
        ];
    }

    private static function missing_row(array $target, string $table): array
    {
        return [
            'label'      => $target['label'],
            'table'      => $table,
            'exists'     => false,
            'total'      => 0,
            'non_empty'  => 0,
            'encrypted'  => null,
            'plaintext'  => null,
            'readable'   => 0,
            'unreadable' => 0,
            'sampled'    => 0,
        ];
    }

    private static function table_exists(string $table): bool
    {
        global $wpdb;

        return $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) === $table;
    }

    /**
     * Pas_Core_Crypto::decrypt() در صورت شکست، رشته‌ای با «⚠️» برمی‌گرداند
     * (قرارداد موجود در همان کلاس). هیچ استثنایی برای «کلید تنظیم نشده»
     * پرتاب نمی‌شود، ولی در scan فقط وقتی کلید تنظیم باشد آزمون انجام می‌شود.
     */
    private static function decrypt_failed(string $result): bool
    {
        return str_starts_with($result, '⚠️');
    }
}
