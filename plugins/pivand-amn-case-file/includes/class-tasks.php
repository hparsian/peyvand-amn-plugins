<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * وظایف درمانگر (فاز ۵) — لیست «کاری که باید انجام شود».
 *
 * وظیفه از رویدادهای معتبر ساخته می‌شود (مثل «آزمون تکمیل شد») یا دستی.
 * تفاوتش با هشدار: هشدار یعنی «یک نشانهٔ بالینی نیاز به بررسی دارد»؛ وظیفه
 * یعنی «یک کار اداری/بالینی روی میز است». هر هشدار یک وظیفه هم می‌سازد، ولی
 * هر وظیفه لزوماً هشدار نیست (مثلاً بررسی یک نتیجهٔ عادی).
 *
 * قواعد:
 *  - عنوان و توضیح وظیفه هرگز متن بالینی یا پاسخ آزمون نیست.
 *  - ضدتکرار بر اساس (type + source + source_ref) تا وقتی وظیفه باز است.
 *  - دید وظایف همان محدودهٔ دسترسی پرونده‌هاست (`Pas_CF_Reports::scope`).
 */
final class Pas_CF_Tasks
{
    public const STATUSES = [
        'open'        => 'باز',
        'in_progress' => 'در حال انجام',
        'done'        => 'انجام‌شده',
        'cancelled'   => 'لغوشده',
    ];

    public const PRIORITIES = [
        'critical' => 'بحرانی',
        'high'     => 'بالا',
        'medium'   => 'متوسط',
        'low'      => 'پایین',
    ];

    public static function table(): string
    {
        return Pas_CF_Installer::table('tasks');
    }

    public static function install(): void
    {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset = $wpdb->get_charset_collate();
        $table   = self::table();

        dbDelta("CREATE TABLE $table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            case_id bigint(20) unsigned NOT NULL DEFAULT 0,
            client_id char(29) NOT NULL DEFAULT '',
            title varchar(190) NOT NULL DEFAULT '',
            description varchar(500) NOT NULL DEFAULT '',
            type varchar(40) NOT NULL DEFAULT '',
            priority varchar(20) NOT NULL DEFAULT 'medium',
            assignee bigint(20) unsigned NOT NULL DEFAULT 0,
            due_at datetime NULL DEFAULT NULL,
            status varchar(20) NOT NULL DEFAULT 'open',
            source varchar(40) NOT NULL DEFAULT '',
            source_ref varchar(64) NOT NULL DEFAULT '',
            created_at datetime NOT NULL DEFAULT '1970-01-01 00:00:00',
            updated_at datetime NOT NULL DEFAULT '1970-01-01 00:00:00',
            completed_at datetime NULL DEFAULT NULL,
            PRIMARY KEY  (id),
            KEY case_id (case_id),
            KEY status (status),
            KEY priority (priority),
            KEY source_ref (source_ref)
        ) $charset;");
    }

    /**
     * @param array{case_id?:int,title:string,type:string,source:string,description?:string,priority?:string,client_id?:string,source_ref?:string,assignee?:int,due_at?:string} $args
     * @return int شناسهٔ وظیفه (جدید یا باز موجود)؛ ۰ = ورودی نامعتبر
     */
    public static function create(array $args): int
    {
        global $wpdb;

        $title  = mb_substr(sanitize_text_field((string) ($args['title'] ?? '')), 0, 190);
        $type   = sanitize_key((string) ($args['type'] ?? ''));
        $source = sanitize_key((string) ($args['source'] ?? ''));
        if ($title === '' || $type === '' || $source === '') {
            return 0;
        }

        $case_id    = (int) ($args['case_id'] ?? 0);
        $source_ref = mb_substr(sanitize_text_field((string) ($args['source_ref'] ?? '')), 0, 64);
        $priority   = (string) ($args['priority'] ?? 'medium');
        if (!isset(self::PRIORITIES[$priority])) {
            $priority = 'medium';
        }

        $table = self::table();

        $existing = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table
             WHERE type = %s AND source = %s AND source_ref = %s AND status IN ('open', 'in_progress')
             LIMIT 1",
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
            'title'       => $title,
            'description' => mb_substr(sanitize_textarea_field((string) ($args['description'] ?? '')), 0, 500),
            'type'        => $type,
            'priority'    => $priority,
            'assignee'    => (int) ($args['assignee'] ?? 0),
            'due_at'      => !empty($args['due_at']) ? (string) $args['due_at'] : null,
            'status'      => 'open',
            'source'      => $source,
            'source_ref'  => $source_ref,
            'created_at'  => $now,
            'updated_at'  => $now,
        ]);

        return (int) $wpdb->insert_id;
    }

    /**
     * وظایف باز در محدودهٔ دید کاربر جاری.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function open_visible(int $limit = 20): array
    {
        global $wpdb;

        $tasks = self::table();
        $cases = Pas_CF_Installer::table('cases');
        [$where, $params] = Pas_CF_Reports::scope();

        $sql = "SELECT t.*, c.full_name,
                       (t.case_id = 0) AS no_case
                FROM $tasks t
                LEFT JOIN $cases c ON c.id = t.case_id
                WHERE t.status IN ('open', 'in_progress')
                  AND (t.case_id = 0 OR $where)
                ORDER BY FIELD(t.priority, 'critical', 'high', 'medium', 'low'), t.id DESC
                LIMIT %d";

        $params  = array_merge($params, [$limit]);
        $results = $wpdb->get_results($wpdb->prepare($sql, ...$params), ARRAY_A);

        return is_array($results) ? $results : [];
    }

    /**
     * فهرست صفحهٔ مدیریت وظایف.
     *
     * @param string $filter open|done|all
     * @return array<int,array<string,mixed>>
     */
    public static function list_visible(string $filter = 'open', int $limit = 200): array
    {
        global $wpdb;

        $tasks = self::table();
        $cases = Pas_CF_Installer::table('cases');
        [$where, $params] = Pas_CF_Reports::scope();

        $status_sql = match ($filter) {
            'done'  => "t.status IN ('done', 'cancelled')",
            'all'   => '1=1',
            default => "t.status IN ('open', 'in_progress')",
        };

        $limit = max(1, min(500, $limit));
        $sql   = "SELECT t.*, c.full_name
                  FROM $tasks t
                  LEFT JOIN $cases c ON c.id = t.case_id
                  WHERE $status_sql AND (t.case_id = 0 OR $where)
                  ORDER BY FIELD(t.priority, 'critical', 'high', 'medium', 'low'), t.id DESC
                  LIMIT %d";

        $params  = array_merge($params, [$limit]);
        $results = $wpdb->get_results($wpdb->prepare($sql, ...$params), ARRAY_A);

        return is_array($results) ? $results : [];
    }

    /** آیا کاربر جاری به پروندهٔ این وظیفه دسترسی دارد؟ (وظیفهٔ بی‌پرونده: بله) */
    public static function can_access(int $task_id): bool
    {
        global $wpdb;

        if ($task_id <= 0) {
            return false;
        }

        $row = $wpdb->get_row($wpdb->prepare('SELECT id, case_id FROM ' . self::table() . ' WHERE id = %d', $task_id));
        if (!$row) {
            return false;
        }
        if ((int) $row->case_id === 0) {
            return true;
        }

        $case = Pas_CF_Case_File::get((int) $row->case_id);

        return $case ? Pas_CF_Security::current_user_can_access_case($case) : false;
    }

    /** تغییر وضعیت وظیفه (open|in_progress|done|cancelled). */
    public static function update_status(int $task_id, string $status): bool
    {
        global $wpdb;

        if ($task_id <= 0 || !isset(self::STATUSES[$status])) {
            return false;
        }

        $now  = current_time('mysql', true);
        $data = ['status' => $status, 'updated_at' => $now];
        if (in_array($status, ['done', 'cancelled'], true)) {
            $data['completed_at'] = $now;
        }

        $wpdb->update(self::table(), $data, ['id' => $task_id]);

        return true;
    }

    public static function count_open_visible(): int
    {
        global $wpdb;

        $tasks = self::table();
        $cases = Pas_CF_Installer::table('cases');
        [$where, $params] = Pas_CF_Reports::scope();

        $sql = "SELECT COUNT(*) FROM $tasks t LEFT JOIN $cases c ON c.id = t.case_id
                WHERE t.status IN ('open', 'in_progress') AND (t.case_id = 0 OR $where)";

        return (int) $wpdb->get_var($wpdb->prepare($sql, ...$params));
    }

    public static function complete(int $task_id): bool
    {
        global $wpdb;

        if ($task_id <= 0) {
            return false;
        }

        $now = current_time('mysql', true);

        $wpdb->update(
            self::table(),
            ['status' => 'done', 'updated_at' => $now, 'completed_at' => $now],
            ['id' => $task_id]
        );

        return true;
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

        $rows = $wpdb->get_results($wpdb->prepare(
            'SELECT * FROM ' . self::table() . " WHERE case_id = %d AND status IN ('open', 'in_progress') ORDER BY id DESC LIMIT %d",
            $case_id,
            $limit
        ), ARRAY_A);

        return is_array($rows) ? $rows : [];
    }
}
