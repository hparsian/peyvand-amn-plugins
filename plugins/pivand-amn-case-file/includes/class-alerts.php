<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * هشدارهای بالینی (فاز ۵).
 *
 * مسئله‌ای که این کلاس حل می‌کند: آزمون‌هایی مثل BDI و GHQ-28 از قبل «آیتم
 * خودآسیبی» را محاسبه می‌کردند، ولی فقط داخل صفحهٔ گزارش می‌ماند. هیچ‌جای
 * اکوسیستم نمی‌دانست که یک مراجع پاسخ نگران‌کننده داده است، پس نه پرونده
 * خبردار می‌شد و نه داشبورد.
 *
 * قواعد سخت (عمدی و غیرقابل تغییر بدون تصمیم بالینی):
 *  - هشدار «پرچم نیاز به بررسی انسانی» است، نه تشخیص. متن آن هم همین را
 *    می‌گوید و هیچ تصمیم درمانی‌ای به‌طور خودکار گرفته نمی‌شود.
 *  - نبودِ هشدار به معنی نبودِ خطر نیست؛ این فقط یک قاعدهٔ آستانه‌ای است.
 *  - هیچ پاسخ خام آزمون در هشدار ذخیره نمی‌شود؛ فقط منبع، شناسهٔ تلاش، سطح و
 *    یک «دلیل» کوتاه و غیرحساس.
 *  - هشدار فقط برای پرونده‌ای ساخته می‌شود که شناسه‌اش با شناسهٔ واحد مراجع
 *    (فاز ۳) یا شمارهٔ نرمال‌شده تطبیق کند. بدون پرونده، هشدار بی‌صاحب ساخته
 *    نمی‌شود تا داشبورد شلوغ نشود.
 *  - ضدتکرار: برای همان پرونده + همان منبع + همان نوع، تا وقتی هشدار باز است
 *    هشدار دوم ساخته نمی‌شود؛ تغییر وضعیت در تاریخچه ثبت می‌شود.
 */
final class Pas_CF_Alerts
{
    public const STATUSES = [
        'new'             => 'جدید',
        'seen'            => 'مشاهده‌شده',
        'reviewing'       => 'در حال بررسی',
        'action_required' => 'نیازمند اقدام',
        'referred'        => 'ارجاع‌شده',
        'followed_up'     => 'پیگیری‌شده',
        'closed'          => 'بسته‌شده',
    ];

    public const PRIORITIES = [
        'critical' => 'بحرانی',
        'high'     => 'بالا',
        'medium'   => 'متوسط',
        'low'      => 'پایین',
    ];

    public static function table(string $name): string
    {
        return Pas_CF_Installer::table($name);
    }

    /** نصب افزایشی جدول‌های هشدار و تاریخچهٔ آن. */
    public static function install(): void
    {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset = $wpdb->get_charset_collate();
        $alerts  = self::table('alerts');
        $history = self::table('alert_history');

        dbDelta("CREATE TABLE $alerts (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            case_id bigint(20) unsigned NOT NULL DEFAULT 0,
            client_id char(29) NOT NULL DEFAULT '',
            source varchar(40) NOT NULL DEFAULT '',
            source_ref varchar(64) NOT NULL DEFAULT '',
            type varchar(40) NOT NULL DEFAULT '',
            priority varchar(20) NOT NULL DEFAULT 'medium',
            status varchar(20) NOT NULL DEFAULT 'new',
            reason text NULL,
            assignee bigint(20) unsigned NOT NULL DEFAULT 0,
            closed_reason varchar(190) NOT NULL DEFAULT '',
            created_at datetime NOT NULL DEFAULT '1970-01-01 00:00:00',
            updated_at datetime NOT NULL DEFAULT '1970-01-01 00:00:00',
            closed_at datetime NULL DEFAULT NULL,
            PRIMARY KEY  (id),
            KEY case_id (case_id),
            KEY client_id (client_id),
            KEY status (status),
            KEY priority (priority),
            KEY source_ref (source_ref)
        ) $charset;");

        dbDelta("CREATE TABLE $history (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            alert_id bigint(20) unsigned NOT NULL DEFAULT 0,
            from_status varchar(20) NOT NULL DEFAULT '',
            to_status varchar(20) NOT NULL DEFAULT '',
            note varchar(190) NOT NULL DEFAULT '',
            user_id bigint(20) unsigned NOT NULL DEFAULT 0,
            created_at datetime NOT NULL DEFAULT '1970-01-01 00:00:00',
            PRIMARY KEY  (id),
            KEY alert_id (alert_id)
        ) $charset;");
    }

    /**
     * ساخت هشدار (با ضدتکرار).
     *
     * @param array{case_id:int,type:string,source:string,priority?:string,reason?:string,client_id?:string,source_ref?:string,assignee?:int} $args
     * @return int شناسهٔ هشدار (جدید یا باز موجود)؛ ۰ = ورودی نامعتبر
     */
    public static function create(array $args): int
    {
        global $wpdb;

        $case_id = (int) ($args['case_id'] ?? 0);
        $type    = sanitize_key((string) ($args['type'] ?? ''));
        $source  = sanitize_key((string) ($args['source'] ?? ''));
        if ($case_id <= 0 || $type === '' || $source === '') {
            return 0;
        }

        $source_ref = mb_substr(sanitize_text_field((string) ($args['source_ref'] ?? '')), 0, 64);
        $priority   = (string) ($args['priority'] ?? 'medium');
        if (!isset(self::PRIORITIES[$priority])) {
            $priority = 'medium';
        }

        $table = self::table('alerts');

        // ضدتکرار: هشدار باز/در جریان با همان منبع برای همان پرونده.
        $existing = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table
             WHERE case_id = %d AND type = %s AND source = %s AND source_ref = %s AND status <> 'closed'
             LIMIT 1",
            $case_id,
            $type,
            $source,
            $source_ref
        ));
        if ($existing > 0) {
            return $existing;
        }

        $now = current_time('mysql', true);

        $wpdb->insert($table, [
            'case_id'     => $case_id,
            'client_id'   => (string) ($args['client_id'] ?? ''),
            'source'      => $source,
            'source_ref'  => $source_ref,
            'type'        => $type,
            'priority'    => $priority,
            'status'      => 'new',
            'reason'      => mb_substr(sanitize_textarea_field((string) ($args['reason'] ?? '')), 0, 1000),
            'assignee'    => (int) ($args['assignee'] ?? 0),
            'created_at'  => $now,
            'updated_at'  => $now,
        ]);

        $alert_id = (int) $wpdb->insert_id;
        if ($alert_id > 0) {
            self::record_history($alert_id, '', 'new', __('هشدار ایجاد شد', 'pivand-amn-case-file'));
            if (class_exists('Pas_CF_Audit_Log')) {
                Pas_CF_Audit_Log::record($case_id, 'alert_created', 'alert:' . $alert_id);
            }
        }

        return $alert_id;
    }

    /**
     * هشدارهای باز در محدودهٔ دید کاربر جاری.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function open_visible(int $limit = 20): array
    {
        global $wpdb;

        $alerts = self::table('alerts');
        $cases  = self::table('cases');
        [$where, $params] = Pas_CF_Reports::scope();

        $sql = "SELECT a.*, c.full_name, c.phone
                FROM $alerts a
                INNER JOIN $cases c ON c.id = a.case_id
                WHERE a.status <> 'closed' AND $where
                ORDER BY FIELD(a.priority, 'critical', 'high', 'medium', 'low'), a.id DESC
                LIMIT %d";

        $params   = array_merge($params, [$limit]);
        $results  = $wpdb->get_results($wpdb->prepare($sql, ...$params), ARRAY_A);

        return is_array($results) ? $results : [];
    }

    /**
     * فهرست صفحهٔ مدیریت هشدارها.
     *
     * @param string $filter open|closed|all
     * @return array<int,array<string,mixed>>
     */
    public static function list_visible(string $filter = 'open', int $limit = 200): array
    {
        global $wpdb;

        $alerts = self::table('alerts');
        $cases  = self::table('cases');
        [$where, $params] = Pas_CF_Reports::scope();

        $status_sql = match ($filter) {
            'closed' => "a.status = 'closed'",
            'all'    => '1=1',
            default  => "a.status <> 'closed'",
        };

        $limit = max(1, min(500, $limit));
        $sql   = "SELECT a.*, c.full_name, c.phone
                  FROM $alerts a
                  INNER JOIN $cases c ON c.id = a.case_id
                  WHERE $status_sql AND $where
                  ORDER BY FIELD(a.priority, 'critical', 'high', 'medium', 'low'), a.id DESC
                  LIMIT %d";

        $params  = array_merge($params, [$limit]);
        $results = $wpdb->get_results($params ? $wpdb->prepare($sql, ...$params) : $sql, ARRAY_A);

        return is_array($results) ? $results : [];
    }

    /** آیا کاربر جاری به پروندهٔ این هشدار دسترسی دارد؟ */
    public static function can_access(int $alert_id): bool
    {
        global $wpdb;

        $alert = self::get($alert_id);
        if (!$alert) {
            return false;
        }

        $case = Pas_CF_Case_File::get((int) $alert['case_id']);

        return $case ? Pas_CF_Security::current_user_can_access_case($case) : false;
    }

    public static function count_open_visible(): int
    {
        global $wpdb;

        $alerts = self::table('alerts');
        $cases  = self::table('cases');
        [$where, $params] = Pas_CF_Reports::scope();

        $sql = "SELECT COUNT(*) FROM $alerts a INNER JOIN $cases c ON c.id = a.case_id WHERE a.status <> 'closed' AND $where";

        return (int) $wpdb->get_var($params ? $wpdb->prepare($sql, ...$params) : $sql);
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public static function for_case(int $case_id, int $limit = 20): array
    {
        global $wpdb;

        if ($case_id <= 0) {
            return [];
        }

        $table = self::table('alerts');
        $rows  = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table WHERE case_id = %d ORDER BY id DESC LIMIT %d",
            $case_id,
            $limit
        ), ARRAY_A);

        return is_array($rows) ? $rows : [];
    }

    public static function get(int $alert_id): ?array
    {
        global $wpdb;

        if ($alert_id <= 0) {
            return null;
        }

        $row = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . self::table('alerts') . ' WHERE id = %d', $alert_id), ARRAY_A);

        return is_array($row) ? $row : null;
    }

    /**
     * تغییر وضعیت هشدار + ثبت در تاریخچه.
     */
    public static function update_status(int $alert_id, string $status, string $note = '', string $closed_reason = ''): bool
    {
        global $wpdb;

        $alert = self::get($alert_id);
        if (!$alert || !isset(self::STATUSES[$status])) {
            return false;
        }

        $now  = current_time('mysql', true);
        $data = [
            'status'     => $status,
            'updated_at' => $now,
        ];
        if ($status === 'closed') {
            $data['closed_at']     = $now;
            $data['closed_reason'] = mb_substr(sanitize_text_field($closed_reason), 0, 190);
        }

        $wpdb->update(self::table('alerts'), $data, ['id' => $alert_id]);
        self::record_history($alert_id, (string) $alert['status'], $status, $note);

        if (class_exists('Pas_CF_Audit_Log')) {
            Pas_CF_Audit_Log::record((int) $alert['case_id'], 'alert_status', 'alert:' . $alert_id . ':' . $status);
        }

        return true;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public static function history(int $alert_id): array
    {
        global $wpdb;

        $rows = $wpdb->get_results($wpdb->prepare(
            'SELECT * FROM ' . self::table('alert_history') . ' WHERE alert_id = %d ORDER BY id ASC',
            $alert_id
        ), ARRAY_A);

        return is_array($rows) ? $rows : [];
    }

    private static function record_history(int $alert_id, string $from, string $to, string $note = ''): void
    {
        global $wpdb;

        $wpdb->insert(self::table('alert_history'), [
            'alert_id'    => $alert_id,
            'from_status' => $from,
            'to_status'   => $to,
            'note'        => mb_substr(sanitize_text_field($note), 0, 190),
            'user_id'     => get_current_user_id(),
            'created_at'  => current_time('mysql', true),
        ]);
    }
}
