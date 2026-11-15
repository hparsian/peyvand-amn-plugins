<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * رویدادهای اکوسیستم (فاز ۳) — لایهٔ ارتباط استاندارد بین افزونه‌ها.
 *
 * مسئله: هیچ افزونه‌ای به دیگری «چه اتفاقی افتاد» را نمی‌گفت. پرونده از آزمون
 * بی‌خبر بود، داشبورد از رسید و رزرو بی‌خبر، و تنها راه ارتباط، فیلترهای
 * پراکنده با کلید شمارهٔ تلفن بود.
 *
 * تصمیم‌های طراحی (عمدی):
 *  - Outbox سبک: هر رویداد **اول در جدول `pas_events` ثبت می‌شود** و بعد در
 *    همان درخواست با `do_action('pas_event', ...)` و
 *    `do_action('pas_event_{name}', ...)` منتشر می‌شود. اگر شنونده‌ای نباشد،
 *    رویداد در دیتابیس می‌ماند و بعداً می‌شود دید چه اتفاقی افتاده — بی‌صدا
 *    گم نمی‌شود.
 *  - هیچ خطای شنونده‌ای ثبت رویداد را نمی‌شکند: ثبت در Outbox قبل از انتشار
 *    انجام می‌شود و انتشار در try/catch جدا اجرا می‌شود.
 *  - Payload فقط دادهٔ غیرحساس: کلیدهایی مثل پاسخ، متن یادداشت، توکن، تلفن،
 *    ایمیل و هر چیز مشابه در Payload حذف می‌شوند. رویداد «چه شد» را می‌گوید،
 *    نه «چه پاسخی داده شد».
 *  - idempotency_key اختیاری است: اگر یک عملیات دو بار اجرا شود (retry مرورگر،
 *    double-submit)، رویداد دوم ذخیره نمی‌شود و شناسهٔ رویداد اول برمی‌گردد.
 *  - بدون cron و بدون صف پردازش: این لایه فقط ثبت و انتشار است. مصرف‌کننده
 *    (پرونده/داشبورد/وظایف) خودش تصمیم می‌گیرد چه کند.
 */
final class Pas_Core_Events
{
    public const DB_VERSION = '1.0.0';

    /** حداکثر حجم Payload ذخیره‌شده؛ رویداد سنگین یعنی دادهٔ حساس در آن است. */
    public const MAX_PAYLOAD_BYTES = 4096;

    public static function table(): string
    {
        global $wpdb;

        return $wpdb->prefix . 'pas_events';
    }

    // ── نصب جدول (افزایشی، dbDelta) ──────────────────────────────────────

    public static function install(): void
    {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset = $wpdb->get_charset_collate();
        $table   = self::table();

        dbDelta("CREATE TABLE $table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            event_id char(36) NOT NULL DEFAULT '',
            name varchar(80) NOT NULL DEFAULT '',
            contract_version varchar(10) NOT NULL DEFAULT 'v1',
            source varchar(40) NOT NULL DEFAULT '',
            entity_type varchar(40) NOT NULL DEFAULT '',
            entity_id varchar(64) NOT NULL DEFAULT '',
            client_id char(29) NOT NULL DEFAULT '',
            payload longtext NULL,
            status varchar(20) NOT NULL DEFAULT 'dispatched',
            idempotency_key char(32) NOT NULL DEFAULT '',
            created_at datetime NOT NULL DEFAULT '1970-01-01 00:00:00',
            dispatched_at datetime NULL DEFAULT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY event_id (event_id),
            KEY name (name),
            KEY client_id (client_id),
            KEY created_at (created_at),
            KEY idempotency_key (idempotency_key)
        ) $charset;");

        update_option('pas_events_db_version', self::DB_VERSION, false);
    }

    // ── انتشار ───────────────────────────────────────────────────────────

    /**
     * ثبت و انتشار یک رویداد.
     *
     * @param string               $name    نام قراردادی، مثل assessment.completed
     * @param array<string,mixed>  $payload فقط دادهٔ غیرحساس (شناسه‌ها و وضعیت)
     * @param array{source?:string,entity_type?:string,entity_id?:int|string,client_id?:string,idempotency_key?:string} $meta
     * @return string شناسهٔ رویداد (UUID)؛ رشتهٔ خالی یعنی ثبت نشد.
     */
    public static function dispatch(string $name, array $payload = [], array $meta = []): string
    {
        global $wpdb;

        $name = strtolower(trim($name));
        if ($name === '' || !preg_match('/^[a-z0-9_]+(\.[a-z0-9_]+)*$/', $name)) {
            return '';
        }

        $source          = isset($meta['source']) ? sanitize_key((string) $meta['source']) : '';
        $entity_type     = isset($meta['entity_type']) ? sanitize_key((string) $meta['entity_type']) : '';
        $entity_id       = isset($meta['entity_id']) ? (string) $meta['entity_id'] : '';
        $client_id       = isset($meta['client_id']) ? (string) $meta['client_id'] : '';
        $idempotency_key = isset($meta['idempotency_key']) ? md5((string) $meta['idempotency_key']) : '';

        $table = self::table();

        // رکوردهای تکراری: اگر همین رویداد با همین کلید قبلاً ثبت شده، همان را برگردان.
        if ($idempotency_key !== '') {
            $existing = $wpdb->get_var($wpdb->prepare(
                "SELECT event_id FROM $table WHERE name = %s AND idempotency_key = %s LIMIT 1",
                $name,
                $idempotency_key
            ));
            if (is_string($existing) && $existing !== '') {
                return $existing;
            }
        }

        $event_id = wp_generate_uuid4();
        $safe     = self::sanitize_payload($payload);
        $json     = wp_json_encode($safe, JSON_UNESCAPED_UNICODE);
        if (!is_string($json)) {
            $json = '{}';
        }
        if (strlen($json) > self::MAX_PAYLOAD_BYTES) {
            // Payload بزرگ یعنی احتمالاً دادهٔ خام در آن راه یافته؛ ذخیره نمی‌کنیم.
            $json = wp_json_encode(['truncated' => true], JSON_UNESCAPED_UNICODE);
        }

        $inserted = $wpdb->insert($table, [
            'event_id'         => $event_id,
            'name'             => $name,
            'contract_version' => 'v1',
            'source'           => $source,
            'entity_type'      => $entity_type,
            'entity_id'        => $entity_id,
            'client_id'        => $client_id,
            'payload'          => $json,
            'status'           => 'recorded',
            'idempotency_key'  => $idempotency_key,
            'created_at'       => current_time('mysql', true),
        ]);

        if ($inserted === false) {
            return '';
        }

        $context = [
            'event_id'         => $event_id,
            'name'             => $name,
            'contract_version' => 'v1',
            'source'           => $source,
            'entity_type'      => $entity_type,
            'entity_id'        => $entity_id,
            'client_id'        => $client_id,
            'payload'          => $safe,
        ];

        // انتشار: هیچ شنونده‌ای نباید ثبت رویداد را بشکند.
        try {
            do_action('pas_event', $name, $safe, $context);
            do_action('pas_event_' . $name, $safe, $context);
            $wpdb->update(
                $table,
                ['status' => 'dispatched', 'dispatched_at' => current_time('mysql', true)],
                ['event_id' => $event_id],
                ['%s', '%s'],
                ['%s']
            );
        } catch (\Throwable $e) {
            $wpdb->update($table, ['status' => 'failed'], ['event_id' => $event_id], ['%s'], ['%s']);
        }

        return $event_id;
    }

    // ── خواندن (برای داشبورد/عیب‌یابی) ─────────────────────────────────

    /**
     * @return array<int,array<string,mixed>>
     */
    public static function recent(int $limit = 50, string $name = ''): array
    {
        global $wpdb;

        $table  = self::table();
        $limit  = max(1, min(500, $limit));
        $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
        if ($exists !== $table) {
            return [];
        }

        if ($name !== '') {
            $rows = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM $table WHERE name = %s ORDER BY id DESC LIMIT %d",
                $name,
                $limit
            ), ARRAY_A);
        } else {
            $rows = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM $table ORDER BY id DESC LIMIT %d",
                $limit
            ), ARRAY_A);
        }

        return is_array($rows) ? $rows : [];
    }

    /**
     * آمار ساده برای داشبورد سلامت اکوسیستم.
     *
     * @return array{total:int,last_24h:int,failed:int,by_name:array<string,int>}
     */
    public static function stats(): array
    {
        global $wpdb;

        $table  = self::table();
        $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
        if ($exists !== $table) {
            return ['total' => 0, 'last_24h' => 0, 'failed' => 0, 'by_name' => []];
        }

        $by_name = [];
        foreach ((array) $wpdb->get_results("SELECT name, COUNT(*) AS c FROM $table GROUP BY name ORDER BY c DESC LIMIT 50") as $row) {
            $by_name[(string) $row->name] = (int) $row->c;
        }

        return [
            'total'    => (int) $wpdb->get_var("SELECT COUNT(*) FROM $table"),
            'last_24h' => (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $table WHERE created_at >= %s",
                gmdate('Y-m-d H:i:s', time() - DAY_IN_SECONDS)
            )),
            'failed'   => (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table WHERE status = %s", 'failed')),
            'by_name'  => $by_name,
        ];
    }

    // ── پاک‌سازی Payload ─────────────────────────────────────────────────

    /**
     * فقط اسکالرها و آرایه‌های تخت از نوع اسکالر می‌مانند؛ هر کلیدی که بوی
     * دادهٔ بالینی/هویتی بدهد حذف می‌شود. عمداً سخت‌گیرانه است: از دست دادن
     * یک فیلد در رویداد بهتر از نشت پاسخ آزمون یا شمارهٔ مراجع است.
     *
     * @param array<mixed> $payload
     * @return array<string,mixed>
     */
    private static function sanitize_payload(array $payload): array
    {
        $blocked = '/answer|answers|response|note|content|text|body|token|phone|mobile|email|name|address|password|secret|key|iban|card/i';
        $out     = [];

        foreach ($payload as $key => $value) {
            $key = is_string($key) ? $key : (string) $key;
            if ($key === '' || preg_match($blocked, $key)) {
                continue;
            }

            if (is_bool($value) || is_int($value) || is_float($value)) {
                $out[$key] = $value;
                continue;
            }
            if (is_string($value)) {
                $out[$key] = mb_substr(sanitize_text_field($value), 0, 190);
                continue;
            }
            if (is_array($value)) {
                $flat = [];
                foreach ($value as $item) {
                    if (is_scalar($item)) {
                        $flat[] = is_string($item) ? mb_substr(sanitize_text_field($item), 0, 190) : $item;
                    }
                    if (count($flat) >= 20) {
                        break;
                    }
                }
                $out[$key] = $flat;
                continue;
            }
            // object/null → نادیده
        }

        return $out;
    }
}
