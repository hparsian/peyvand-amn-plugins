<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * لاگ سبک هر تلاش ارسال پیامک — چون تجربه‌ی قبلی نشون داده که «پیامک نرسید
 * ولی چرا؟» بدون این لاگ خیلی سخت دیباگ می‌شه. فقط آخرین ۲۰۰ رکورد نگه
 * داشته می‌شود (جدول با یک DELETE دوره‌ای کوچک نگه داشته می‌شود).
 */
final class Pas_Log
{
    public static function table(): string
    {
        global $wpdb;

        return $wpdb->prefix . 'pas_sms_log';
    }

    public static function maybe_create_table(): void
    {
        // نسخه‌بندی شِما تا dbDelta() فقط وقتی واقعاً لازمه اجرا بشه (فعال‌سازی
        // یا آپگرید نسخه)، نه رو هر بار بارگذاری پلاگین. dbDelta خودش سبک
        // نیست — SHOW TABLES + DESCRIBE + مقایسه‌ی کامل شِما انجام می‌ده؛
        // صداش‌زدن رو هر page load (چه فرانت، چه ادمین، چه هر AJAX request)
        // فقط بار اضافه‌ی بی‌دلیل روی هر درخواسته.
        if (get_option('pas_bridge_db_version') === PAS_BRIDGE_VERSION) {
            return;
        }

        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $table   = self::table();
        $charset = $wpdb->get_charset_collate();

        // بدون "IF NOT EXISTS" — dbDelta با آن به‌درستی کار نمی‌کند.
        dbDelta("CREATE TABLE $table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            mobile varchar(20) NOT NULL DEFAULT '',
            body_id int(11) NOT NULL DEFAULT 0,
            success tinyint(1) NOT NULL DEFAULT 0,
            reason varchar(255) NOT NULL DEFAULT '',
            created_at datetime NOT NULL DEFAULT '1970-01-01 00:00:00',
            PRIMARY KEY  (id),
            KEY mobile (mobile),
            KEY created_at (created_at)
        ) $charset;");

        update_option('pas_bridge_db_version', PAS_BRIDGE_VERSION);
    }

    public static function record(string $mobile, int $body_id, bool $success, string $reason): void
    {
        global $wpdb;
        $wpdb->insert(self::table(), [
            'mobile'     => $mobile,
            'body_id'    => $body_id,
            'success'    => $success ? 1 : 0,
            'reason'     => mb_substr(sanitize_text_field($reason), 0, 250),
            'created_at' => current_time('mysql'),
        ], ['%s', '%d', '%d', '%s', '%s']);

        // پاکسازی سبک: فقط ۲۰۰ رکورد آخر نگه داشته می‌شود.
        $count = (int) $wpdb->get_var('SELECT COUNT(*) FROM ' . self::table());
        if ($count > 200) {
            $wpdb->query($wpdb->prepare(
                'DELETE FROM ' . self::table() . ' ORDER BY id ASC LIMIT %d',
                $count - 200
            ));
        }
    }

    /** @return array<int,object> */
    public static function recent(int $limit = 50): array
    {
        global $wpdb;
        $limit = max(1, min(500, $limit));

        return $wpdb->get_results($wpdb->prepare(
            'SELECT * FROM ' . self::table() . ' ORDER BY id DESC LIMIT %d',
            $limit
        ));
    }
}
