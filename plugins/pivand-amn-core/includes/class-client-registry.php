<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * رجیستری هویت مراجع (فاز ۱) — ایندکس فقط‌خواندنی.
 *
 * مسئله: یک انسان در اکوسیستم پیوند امن می‌تواند هم‌زمان در پنج جای مختلف با
 * کلیدهای متفاوت وجود داشته باشد (کاربر پنل با متای cellphone/zdp_mobile،
 * پرونده‌ی بالینی با ستون phone، دعوت‌نامه‌ی آزمون با client_phone/client_email،
 * لید با phone، فرم تماس با phone). این کلاس همه‌ی این‌ها را می‌خواند و به یک
 * شناسه‌ی پایدار (client_id) نگاشت می‌کند، بدون تغییر هیچ داده‌ای در آن
 * افزونه‌ها و بدون ادغام خودکار دو انسان.
 *
 * تصمیم‌های طراحی (عمدی):
 *  - اتصال فقط بر اساس «شناسه‌ی یکسان» است (موبایل نرمال‌شده، ایمیل، حساب
 *    وردپرس). نام، نام خانوادگی و شماره‌ی همسر هرگز باعث اتصال نمی‌شوند.
 *  - موبایل هویت قطعی نیست: اگر یک شماره بین چند نام/چند پرونده/چند حساب
 *    دیده شود، به‌جای ادغام، یک «تعارض» باز ثبت می‌شود و آن مراجع وضعیت
 *    «نیازمند بررسی» می‌گیرد. هیچ ادغامی انجام نمی‌شود.
 *  - هیچ فیلد بالینی خوانده نمی‌شود: فقط شناسه، نام نمایشی، منبع و شماره‌ی
 *    رکورد منبع. یادداشت جلسه، پاسخ آزمون، نتیجه و فرم ارزیابی دست‌نخورده
 *    می‌مانند.
 *  - بازسازی idempotent است و شناسه‌ی مراجعان موجود را پایدار نگه می‌دارد
 *    (ردیف‌های قبلی با last_seen_at تازه می‌شوند؛ رکوردهای حذف‌شده فقط از
 *    ایندکس کنار می‌روند).
 *  - هیچ چیزی روی cron خودکار اجرا نمی‌شود؛ بازسازی فقط با کلیک ادمین
 *    (nonce + manage_options) انجام می‌شود.
 */
final class Pas_Core_Client_Registry
{
    public const DB_VERSION       = '1.0.0';
    public const MAX_OBSERVATIONS = 20000;
    private const BATCH           = 500;

    /** سقف رکوردهایی که در هر منبع خوانده می‌شود؛ عبور از آن = ایندکس ناقص. */
    public const TABLE_CLIENTS     = 'clients';
    public const TABLE_IDENTIFIERS = 'client_identifiers';
    public const TABLE_CONFLICTS   = 'identity_conflicts';

    public static function table(string $name): string
    {
        global $wpdb;

        return $wpdb->prefix . 'pas_' . $name;
    }

    // ── نصب جداول (افزایشی، dbDelta) ─────────────────────────────────────

    public static function install(): void
    {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset = $wpdb->get_charset_collate();

        $clients = self::table(self::TABLE_CLIENTS);
        dbDelta("CREATE TABLE $clients (
            client_id char(29) NOT NULL,
            display_name varchar(190) NOT NULL DEFAULT '',
            primary_mobile varchar(20) NOT NULL DEFAULT '',
            primary_email varchar(190) NOT NULL DEFAULT '',
            status varchar(20) NOT NULL DEFAULT 'active',
            conflict_count smallint(5) unsigned NOT NULL DEFAULT 0,
            sources_count smallint(5) unsigned NOT NULL DEFAULT 0,
            created_at datetime NOT NULL DEFAULT '1970-01-01 00:00:00',
            updated_at datetime NOT NULL DEFAULT '1970-01-01 00:00:00',
            merged_into char(29) NOT NULL DEFAULT '',
            PRIMARY KEY  (client_id),
            KEY primary_mobile (primary_mobile),
            KEY primary_email (primary_email),
            KEY status (status)
        ) $charset;");

        $identifiers = self::table(self::TABLE_IDENTIFIERS);
        dbDelta("CREATE TABLE $identifiers (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            client_id char(29) NOT NULL,
            identifier_type varchar(20) NOT NULL DEFAULT '',
            identifier_value varchar(190) NOT NULL DEFAULT '',
            identifier_hash char(32) NOT NULL DEFAULT '',
            source varchar(40) NOT NULL DEFAULT 'auto',
            source_ref varchar(100) NOT NULL DEFAULT '',
            display_name varchar(190) NOT NULL DEFAULT '',
            created_at datetime NOT NULL DEFAULT '1970-01-01 00:00:00',
            last_seen_at datetime NOT NULL DEFAULT '1970-01-01 00:00:00',
            PRIMARY KEY  (id),
            UNIQUE KEY identifier_hash (identifier_hash),
            KEY client_id (client_id),
            KEY identifier_lookup (identifier_type,identifier_value)
        ) $charset;");

        $conflicts = self::table(self::TABLE_CONFLICTS);
        dbDelta("CREATE TABLE $conflicts (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            conflict_key char(32) NOT NULL DEFAULT '',
            conflict_type varchar(40) NOT NULL DEFAULT '',
            identifier_type varchar(20) NOT NULL DEFAULT '',
            identifier_value varchar(190) NOT NULL DEFAULT '',
            details longtext NULL,
            status varchar(20) NOT NULL DEFAULT 'open',
            detected_at datetime NOT NULL DEFAULT '1970-01-01 00:00:00',
            PRIMARY KEY  (id),
            UNIQUE KEY conflict_key (conflict_key),
            KEY status (status)
        ) $charset;");

        update_option('pas_core_db_version', self::DB_VERSION, false);
    }

    // ── بازسازی ایندکس ───────────────────────────────────────────────────

    /**
     * @return array{clients:int,identifiers:int,conflicts:int,observations:int,truncated:bool,errors:array<int,string>}
     */
    public static function rebuild(): array
    {
        if (get_transient('pas_registry_rebuild_lock')) {
            return [
                'clients'      => 0,
                'identifiers'  => 0,
                'conflicts'    => 0,
                'observations' => 0,
                'truncated'    => false,
                'errors'       => ['بازسازی رجیستری هم‌اکنون در حال اجرا است.'],
            ];
        }
        set_transient('pas_registry_rebuild_lock', 1, 300);

        try {
            global $wpdb;

            self::install();

            $run_start    = current_time('mysql', true);
            $truncated    = false;
            $errors       = [];
            $observations = self::collect_observations($truncated, $errors);

            $clusters = self::cluster($observations);

            $clients_table     = self::table(self::TABLE_CLIENTS);
            $identifiers_table = self::table(self::TABLE_IDENTIFIERS);
            $conflicts_table   = self::table(self::TABLE_CONFLICTS);

            $client_count     = 0;
            $identifier_count = 0;
            $conflict_count   = 0;

            foreach ($clusters as $cluster) {
                $keys = $cluster['keys'];
                if (empty($keys)) {
                    continue;
                }

                $client_id = self::find_existing_client_id($keys);
                if ($client_id === '') {
                    $client_id = self::new_client_id();
                }

                $display_name = '';
                $mobile       = '';
                $email        = '';
                $sources      = [];
                $conflicts    = self::detect_conflicts($cluster['items'], $keys);

                usort($cluster['items'], static function (array $a, array $b): int {
                    return $a['priority'] <=> $b['priority'];
                });

                foreach ($cluster['items'] as $item) {
                    if ($display_name === '' && $item['name'] !== '') {
                        $display_name = $item['name'];
                    }
                    $sources[$item['source']] = ($sources[$item['source']] ?? 0) + 1;

                    foreach ($item['identifiers'] as $identifier) {
                        if ($identifier['type'] === 'mobile' && $mobile === '') {
                            $mobile = $identifier['value'];
                        }
                        if ($identifier['type'] === 'email' && $email === '') {
                            $email = $identifier['value'];
                        }
                    }
                }

                $has_review = false;
                foreach ($conflicts as $conflict) {
                    if (in_array($conflict['type'], ['shared_identifier', 'multiple_wp_users', 'multiple_cases'], true)) {
                        $has_review = true;
                    }
                }

                $row = [
                    'client_id'      => $client_id,
                    'display_name'   => $display_name,
                    'primary_mobile' => $mobile,
                    'primary_email'  => $email,
                    'status'         => $has_review ? 'review' : 'active',
                    'conflict_count' => count($conflicts),
                    'sources_count'  => count($sources),
                    'updated_at'     => $run_start,
                ];

                $exists = $wpdb->get_var($wpdb->prepare(
                    "SELECT client_id FROM $clients_table WHERE client_id = %s",
                    $client_id
                ));

                if ($exists) {
                    $wpdb->update(
                        $clients_table,
                        $row,
                        ['client_id' => $client_id],
                        ['%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s'],
                        ['%s']
                    );
                } else {
                    $row['created_at'] = $run_start;
                    $wpdb->insert(
                        $clients_table,
                        $row,
                        ['%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s']
                    );
                }
                $client_count++;

                foreach ($cluster['items'] as $item) {
                    foreach ($item['identifiers'] as $identifier) {
                        $hash = md5(
                            $identifier['type'] . '|' . $identifier['value'] . '|' . $item['source'] . '|' . $item['ref']
                        );

                        $wpdb->query($wpdb->prepare(
                            "INSERT INTO $identifiers_table
                                (client_id, identifier_type, identifier_value, identifier_hash, source, source_ref, display_name, created_at, last_seen_at)
                             VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s)
                             ON DUPLICATE KEY UPDATE
                                client_id = VALUES(client_id),
                                display_name = VALUES(display_name),
                                last_seen_at = VALUES(last_seen_at)",
                            $client_id,
                            $identifier['type'],
                            $identifier['value'],
                            $hash,
                            $item['source'],
                            $item['ref'],
                            $item['name'],
                            $run_start,
                            $run_start
                        ));
                        $identifier_count++;
                    }
                }

                foreach ($conflicts as $conflict) {
                    $conflict_key = md5($conflict['type'] . '|' . $conflict['identifier_type'] . '|' . $conflict['identifier_value']);

                    $wpdb->query($wpdb->prepare(
                        "INSERT INTO $conflicts_table
                            (conflict_key, conflict_type, identifier_type, identifier_value, details, status, detected_at)
                         VALUES (%s, %s, %s, %s, %s, 'open', %s)
                         ON DUPLICATE KEY UPDATE
                            details = VALUES(details),
                            detected_at = VALUES(detected_at)",
                        $conflict_key,
                        $conflict['type'],
                        $conflict['identifier_type'],
                        $conflict['identifier_value'],
                        wp_json_encode($conflict['details'], JSON_UNESCAPED_UNICODE),
                        $run_start
                    ));
                    $conflict_count++;
                }
            }

            // رکوردهایی که این‌بار دیده نشدند از ایندکس کنار می‌روند (فقط ردیف‌های
            // خودکار؛ هر ردیف دستی آینده با source='manual' دست‌نخورده می‌ماند).
            $wpdb->query($wpdb->prepare(
                "DELETE FROM $identifiers_table WHERE source <> 'manual' AND last_seen_at < %s",
                $run_start
            ));

            // پرونده‌های بدون شناسه (همه‌ی منابعشان حذف شده) هم از فهرست کنار می‌روند.
            $wpdb->query(
                "DELETE c FROM $clients_table c
                 LEFT JOIN $identifiers_table i ON i.client_id = c.client_id
                 WHERE i.id IS NULL AND c.merged_into = ''"
            );

            // تعارض‌های بازِ این‌دوره‌دیده‌نشده پاک می‌شوند؛ تعارض dismiss‌شده آینده
            // (status='dismissed') دست‌نخورده می‌ماند.
            $wpdb->query($wpdb->prepare(
                "DELETE FROM $conflicts_table WHERE status = 'open' AND detected_at < %s",
                $run_start
            ));

            update_option('pas_registry_last_rebuild', current_time('mysql'), false);
            update_option('pas_registry_last_result', [
                'clients'     => $client_count,
                'identifiers' => $identifier_count,
                'conflicts'   => $conflict_count,
                'truncated'   => $truncated,
                'errors'      => $errors,
            ], false);

            return [
                'clients'      => $client_count,
                'identifiers'  => $identifier_count,
                'conflicts'    => $conflict_count,
                'observations' => count($observations),
                'truncated'    => $truncated,
                'errors'       => $errors,
            ];
        } finally {
            delete_transient('pas_registry_rebuild_lock');
        }
    }

    /**
     * خواندن منابع. هیچ ستون بالینی انتخاب نمی‌شود.
     *
     * @return array<int,array{source:string,ref:string,name:string,priority:int,identifiers:array<int,array{type:string,value:string}>}>
     */
    private static function collect_observations(bool &$truncated, array &$errors): array
    {
        global $wpdb;

        $items = [];
        $total = 0;

        $push = static function (array $item) use (&$items, &$total, &$truncated): bool {
            if ($total >= Pas_Core_Client_Registry::MAX_OBSERVATIONS) {
                $truncated = true;

                return false;
            }
            if (!empty($item['identifiers'])) {
                $items[] = $item;
                $total++;
            }

            return true;
        };

        // ۱) کاربران پنل مراجعان — متای cellphone/zdp_mobile + نام zdp_full_name.
        $users_table    = $wpdb->users;
        $usermeta_table = $wpdb->usermeta;
        $offset         = 0;
        $stop           = false;

        while (!$stop) {
            $rows = $wpdb->get_results($wpdb->prepare(
                "SELECT u.ID, u.display_name, m.meta_key, m.meta_value, n.meta_value AS full_name
                 FROM $users_table u
                 INNER JOIN $usermeta_table m ON m.user_id = u.ID
                 LEFT JOIN $usermeta_table n ON n.user_id = u.ID AND n.meta_key = 'zdp_full_name'
                 WHERE m.meta_key IN ('cellphone','zdp_mobile') AND m.meta_value <> ''
                 ORDER BY u.ID ASC
                 LIMIT %d OFFSET %d",
                self::BATCH,
                $offset
            ));

            if ($wpdb->last_error !== '') {
                $errors[] = 'portal_users: ' . $wpdb->last_error;
                break;
            }

            $count = count((array) $rows);
            if ($count === 0) {
                break;
            }

            $grouped = [];
            foreach ($rows as $row) {
                $uid = (int) $row->ID;
                if (!isset($grouped[$uid])) {
                    $grouped[$uid] = [
                        'name'   => self::normalize_name((string) ($row->full_name ?? '') ?: (string) $row->display_name),
                        'mobile' => '',
                    ];
                }
                if ($row->meta_key === 'cellphone' && $row->meta_value !== '') {
                    $grouped[$uid]['mobile'] = (string) $row->meta_value;
                } elseif ($grouped[$uid]['mobile'] === '' && $row->meta_key === 'zdp_mobile' && $row->meta_value !== '') {
                    $grouped[$uid]['mobile'] = (string) $row->meta_value;
                }
            }

            foreach ($grouped as $uid => $data) {
                $identifiers = [];

                $mobile = self::normalize_mobile_value($data['mobile']);
                if ($mobile !== '') {
                    $identifiers[] = ['type' => 'mobile', 'value' => $mobile];
                }
                $identifiers[] = ['type' => 'wp_user', 'value' => (string) $uid];

                if (!$push([
                    'source'      => 'portal_user',
                    'ref'         => (string) $uid,
                    'name'        => $data['name'],
                    'priority'    => 0,
                    'identifiers' => $identifiers,
                ])) {
                    $stop = true;
                    break;
                }
            }

            $offset += self::BATCH;
            if ($count < self::BATCH) {
                break;
            }
        }

        // ۲) پرونده‌های بالینی — phone + full_name. (شماره‌ی همسر عمداً خوانده
        // نمی‌شود: اتصال دو انسان از روی شماره‌ی همسر ممنوع است.)
        if (!$stop && self::table_exists($wpdb->prefix . 'pas_cf_cases')) {
            $table  = $wpdb->prefix . 'pas_cf_cases';
            $offset = 0;

            while (!$stop) {
                $rows = $wpdb->get_results($wpdb->prepare(
                    "SELECT id, phone, full_name FROM $table ORDER BY id ASC LIMIT %d OFFSET %d",
                    self::BATCH,
                    $offset
                ));

                if ($wpdb->last_error !== '') {
                    $errors[] = 'case_file: ' . $wpdb->last_error;
                    break;
                }

                $count = count((array) $rows);
                if ($count === 0) {
                    break;
                }

                foreach ($rows as $row) {
                    $mobile = self::normalize_mobile_value((string) $row->phone);
                    $identifiers = $mobile !== '' ? [['type' => 'mobile', 'value' => $mobile]] : [];

                    if (!$push([
                        'source'      => 'case_file',
                        'ref'         => (string) $row->id,
                        'name'        => self::normalize_name((string) $row->full_name),
                        'priority'    => 1,
                        'identifiers' => $identifiers,
                    ])) {
                        $stop = true;
                        break;
                    }
                }

                $offset += self::BATCH;
                if ($count < self::BATCH) {
                    break;
                }
            }
        }

        // ۳) دعوت‌نامه‌های آزمون — موبایل و ایمیل.
        if (!$stop && self::table_exists($wpdb->prefix . 'psy_invitations')) {
            $table  = $wpdb->prefix . 'psy_invitations';
            $offset = 0;

            while (!$stop) {
                $rows = $wpdb->get_results($wpdb->prepare(
                    "SELECT id, client_name, client_phone, client_email FROM $table ORDER BY id ASC LIMIT %d OFFSET %d",
                    self::BATCH,
                    $offset
                ));

                if ($wpdb->last_error !== '') {
                    $errors[] = 'quiz_invitations: ' . $wpdb->last_error;
                    break;
                }

                $count = count((array) $rows);
                if ($count === 0) {
                    break;
                }

                foreach ($rows as $row) {
                    $identifiers = [];

                    $mobile = self::normalize_mobile_value((string) $row->client_phone);
                    if ($mobile !== '') {
                        $identifiers[] = ['type' => 'mobile', 'value' => $mobile];
                    }
                    $email = self::normalize_email_value((string) $row->client_email);
                    if ($email !== '') {
                        $identifiers[] = ['type' => 'email', 'value' => $email];
                    }

                    if (!$push([
                        'source'      => 'quiz_invitation',
                        'ref'         => (string) $row->id,
                        'name'        => self::normalize_name((string) $row->client_name),
                        'priority'    => 2,
                        'identifiers' => $identifiers,
                    ])) {
                        $stop = true;
                        break;
                    }
                }

                $offset += self::BATCH;
                if ($count < self::BATCH) {
                    break;
                }
            }
        }

        // ۴) لیدها — فقط شماره (بدون نام).
        if (!$stop && self::table_exists($wpdb->prefix . 'zdp_leads')) {
            $table  = $wpdb->prefix . 'zdp_leads';
            $offset = 0;

            while (!$stop) {
                $rows = $wpdb->get_results($wpdb->prepare(
                    "SELECT id, phone FROM $table ORDER BY id ASC LIMIT %d OFFSET %d",
                    self::BATCH,
                    $offset
                ));

                if ($wpdb->last_error !== '') {
                    $errors[] = 'leads: ' . $wpdb->last_error;
                    break;
                }

                $count = count((array) $rows);
                if ($count === 0) {
                    break;
                }

                foreach ($rows as $row) {
                    $mobile = self::normalize_mobile_value((string) $row->phone);
                    if (!$push([
                        'source'      => 'lead',
                        'ref'         => (string) $row->id,
                        'name'        => '',
                        'priority'    => 3,
                        'identifiers' => $mobile !== '' ? [['type' => 'mobile', 'value' => $mobile]] : [],
                    ])) {
                        $stop = true;
                        break;
                    }
                }

                $offset += self::BATCH;
                if ($count < self::BATCH) {
                    break;
                }
            }
        }

        // ۵) فرم تماس — نام، موبایل، ایمیل.
        if (!$stop && self::table_exists($wpdb->prefix . 'payvand_messages')) {
            $table  = $wpdb->prefix . 'payvand_messages';
            $offset = 0;

            while (!$stop) {
                $rows = $wpdb->get_results($wpdb->prepare(
                    "SELECT id, full_name, phone, email FROM $table ORDER BY id ASC LIMIT %d OFFSET %d",
                    self::BATCH,
                    $offset
                ));

                if ($wpdb->last_error !== '') {
                    $errors[] = 'contact_form: ' . $wpdb->last_error;
                    break;
                }

                $count = count((array) $rows);
                if ($count === 0) {
                    break;
                }

                foreach ($rows as $row) {
                    $identifiers = [];

                    $mobile = self::normalize_mobile_value((string) $row->phone);
                    if ($mobile !== '') {
                        $identifiers[] = ['type' => 'mobile', 'value' => $mobile];
                    }
                    $email = self::normalize_email_value((string) $row->email);
                    if ($email !== '') {
                        $identifiers[] = ['type' => 'email', 'value' => $email];
                    }

                    if (!$push([
                        'source'      => 'contact_form',
                        'ref'         => (string) $row->id,
                        'name'        => self::normalize_name((string) $row->full_name),
                        'priority'    => 4,
                        'identifiers' => $identifiers,
                    ])) {
                        $stop = true;
                        break;
                    }
                }

                $offset += self::BATCH;
                if ($count < self::BATCH) {
                    break;
                }
            }
        }

        return $items;
    }

    /**
     * خوشه‌بندی رکوردها بر پایه‌ی شناسه‌ی مشترک (union-find).
     *
     * @return array<int,array{keys:array<int,string>,items:array<int,array>}>
     */
    private static function cluster(array $items): array
    {
        $parent   = [];
        $itemKeys = [];

        foreach ($items as $index => $item) {
            $keys = [];
            foreach ($item['identifiers'] as $identifier) {
                $key = $identifier['type'] . ':' . $identifier['value'];
                self::uf_find($parent, $key);
                $keys[] = $key;
            }

            if (!empty($keys)) {
                $first = $keys[0];
                foreach ($keys as $key) {
                    self::uf_union($parent, $first, $key);
                }
            }

            $itemKeys[$index] = $keys;
        }

        $grouped = [];
        foreach ($itemKeys as $index => $keys) {
            if (empty($keys)) {
                continue;
            }
            $root = self::uf_find($parent, $keys[0]);
            $grouped[$root]['items'][] = $items[$index];
            foreach ($keys as $key) {
                $grouped[$root]['keys'][] = $key;
            }
        }

        foreach ($grouped as $root => $group) {
            $grouped[$root]['keys'] = array_values(array_unique($group['keys']));
        }

        return array_values($grouped);
    }

    private static function uf_find(array &$parent, string $key): string
    {
        if (!isset($parent[$key])) {
            $parent[$key] = $key;

            return $key;
        }

        while ($parent[$key] !== $key) {
            $key = $parent[$key];
        }

        return $key;
    }

    private static function uf_union(array &$parent, string $a, string $b): void
    {
        $rootA = self::uf_find($parent, $a);
        $rootB = self::uf_find($parent, $b);

        if ($rootA !== $rootB) {
            $parent[$rootB] = $rootA;
        }
    }

    /**
     * تشخیص تعارض‌ها — فقط شمارش/نام، بدون هیچ داده‌ی بالینی.
     *
     * @return array<int,array{type:string,identifier_type:string,identifier_value:string,details:array}>
     */
    private static function detect_conflicts(array $items, array $keys): array
    {
        $names     = [];
        $wpUsers   = [];
        $caseCount = 0;
        $mobiles   = [];
        $emails    = [];
        $sources   = [];

        foreach ($items as $item) {
            if ($item['name'] !== '') {
                $names[$item['name']] = true;
            }
            $sources[$item['source']] = ($sources[$item['source']] ?? 0) + 1;

            if ($item['source'] === 'portal_user') {
                $wpUsers[(string) $item['ref']] = true;
            }
            if ($item['source'] === 'case_file') {
                $caseCount++;
            }

            foreach ($item['identifiers'] as $identifier) {
                if ($identifier['type'] === 'mobile') {
                    $mobiles[$identifier['value']] = true;
                }
                if ($identifier['type'] === 'email') {
                    $emails[$identifier['value']] = true;
                }
            }
        }

        $anchorType  = 'mobile';
        $anchorValue = (string) (array_key_first($mobiles) ?: '');
        if ($anchorValue === '') {
            $anchorType  = 'email';
            $anchorValue = (string) (array_key_first($emails) ?: '');
        }

        $conflicts = [];
        $push      = static function (string $type, array $details) use (&$conflicts, $anchorType, $anchorValue): void {
            if ($anchorValue === '') {
                return; // بدون شناسه‌ی لینک‌پذیر، تعارض قابل رسیدگی نیست
            }
            $conflicts[] = [
                'type'              => $type,
                'identifier_type'   => $anchorType,
                'identifier_value'  => $anchorValue,
                'details'           => $details,
            ];
        };

        if (count($names) >= 2) {
            $push('shared_identifier', [
                'names'   => array_keys($names),
                'sources' => $sources,
                'note'    => 'یک شناسه برای بیش از یک نام دیده شده؛ ممکن است شماره مشترک باشد. هیچ ادغامی انجام نشد.',
            ]);
        }

        if (count($wpUsers) > 1) {
            $push('multiple_wp_users', [
                'wp_user_ids' => array_keys($wpUsers),
                'note'        => 'بیش از یک حساب وردپرس با همین شناسه وجود دارد.',
            ]);
        }

        if ($caseCount > 1) {
            $push('multiple_cases', [
                'case_count' => $caseCount,
                'note'       => 'بیش از یک پرونده‌ی بالینی با همین شماره وجود دارد.',
            ]);
        }

        if (!empty($emails) && count($mobiles) > 1) {
            $push('email_multiple_mobiles', [
                'emails'  => array_keys($emails),
                'mobiles' => array_keys($mobiles),
                'note'    => 'یک ایمیل با چند شماره دیده شده؛ ممکن است شماره تغییر کرده باشد. بررسی دستی لازم است.',
            ]);
        }

        if (empty($names) && $anchorValue !== '') {
            $push('missing_name', [
                'sources' => $sources,
                'note'    => 'برای این شناسه هیچ نامی ثبت نشده است.',
            ]);
        }

        return $conflicts;
    }

    private static function find_existing_client_id(array $keys): string
    {
        global $wpdb;

        $table = self::table(self::TABLE_IDENTIFIERS);

        foreach ($keys as $key) {
            $parts = explode(':', $key, 2);
            if (count($parts) !== 2) {
                continue;
            }

            $client_id = $wpdb->get_var($wpdb->prepare(
                "SELECT client_id FROM $table
                 WHERE identifier_type = %s AND identifier_value = %s AND source <> 'manual'
                 ORDER BY id ASC LIMIT 1",
                $parts[0],
                $parts[1]
            ));

            if (!empty($client_id)) {
                return (string) $client_id;
            }
        }

        return '';
    }

    /** ULID-like 26 کاراکتری (Crockford base32) — بدون وابستگی خارجی. */
    private static function new_client_id(): string
    {
        $alphabet = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';
        $time     = (int) floor(microtime(true) * 1000);
        $id       = '';

        for ($i = 0; $i < 10; $i++) {
            $id    = $alphabet[$time % 32] . $id;
            $time  = intdiv($time, 32);
        }

        for ($i = 0; $i < 16; $i++) {
            $id .= $alphabet[random_int(0, 31)];
        }

        return 'pc_' . $id;
    }

    // ── خواندن برای مصرف‌کننده‌ها ────────────────────────────────────────

    public static function stats(): array
    {
        global $wpdb;

        $clients     = self::table(self::TABLE_CLIENTS);
        $identifiers = self::table(self::TABLE_IDENTIFIERS);
        $conflicts   = self::table(self::TABLE_CONFLICTS);

        $empty = [
            'installed'       => false,
            'clients'         => 0,
            'identifiers'     => 0,
            'review'          => 0,
            'conflicts_open'  => 0,
            'by_type'         => [],
            'last_rebuild'    => '',
        ];

        if (!self::table_exists($clients)) {
            return $empty;
        }

        $byType = [];
        foreach ((array) $wpdb->get_results("SELECT identifier_type, COUNT(*) AS n FROM $identifiers GROUP BY identifier_type") as $row) {
            $byType[(string) $row->identifier_type] = (int) $row->n;
        }

        return [
            'installed'      => true,
            'clients'        => (int) $wpdb->get_var("SELECT COUNT(*) FROM $clients"),
            'identifiers'    => (int) $wpdb->get_var("SELECT COUNT(*) FROM $identifiers"),
            'review'         => (int) $wpdb->get_var("SELECT COUNT(*) FROM $clients WHERE status = 'review'"),
            'conflicts_open' => self::table_exists($conflicts)
                ? (int) $wpdb->get_var("SELECT COUNT(*) FROM $conflicts WHERE status = 'open'")
                : 0,
            'by_type'        => $byType,
            'last_rebuild'   => (string) get_option('pas_registry_last_rebuild', ''),
        ];
    }

    /** @return array<int,object> */
    public static function recent_conflicts(int $limit = 100): array
    {
        global $wpdb;

        $table = self::table(self::TABLE_CONFLICTS);
        if (!self::table_exists($table)) {
            return [];
        }

        return (array) $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table WHERE status = 'open' ORDER BY detected_at DESC, id DESC LIMIT %d",
            max(1, $limit)
        ));
    }

    /**
     * @return array<int,array{client_id:string,display_name:string,status:string,identifiers_text:string}>
     */
    public static function sample_clients(int $limit = 30): array
    {
        global $wpdb;

        $clients     = self::table(self::TABLE_CLIENTS);
        $identifiers = self::table(self::TABLE_IDENTIFIERS);

        if (!self::table_exists($clients)) {
            return [];
        }

        $rows = (array) $wpdb->get_results($wpdb->prepare(
            "SELECT client_id, display_name, status FROM $clients WHERE merged_into = '' ORDER BY created_at DESC, client_id ASC LIMIT %d",
            max(1, $limit)
        ));

        if (empty($rows)) {
            return [];
        }

        $ids          = array_map(static fn ($row) => (string) $row->client_id, $rows);
        $placeholders = implode(',', array_fill(0, count($ids), '%s'));

        $map = [];
        $identifierRows = (array) $wpdb->get_results($wpdb->prepare(
            "SELECT client_id, identifier_type, identifier_value FROM $identifiers WHERE client_id IN ($placeholders) ORDER BY id ASC",
            ...$ids
        ));

        foreach ($identifierRows as $row) {
            $map[(string) $row->client_id][] = $row->identifier_type . ': ' . $row->identifier_value;
        }

        $out = [];
        foreach ($rows as $row) {
            $out[] = [
                'client_id'        => (string) $row->client_id,
                'display_name'     => (string) $row->display_name,
                'status'           => (string) $row->status,
                'identifiers_text' => implode('، ', $map[(string) $row->client_id] ?? []),
            ];
        }

        return $out;
    }

    public static function get_client(string $client_id): ?array
    {
        global $wpdb;

        $clients = self::table(self::TABLE_CLIENTS);
        if (!self::table_exists($clients)) {
            return null;
        }

        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT client_id, display_name, status, conflict_count, sources_count
             FROM $clients WHERE client_id = %s AND merged_into = ''",
            $client_id
        ));

        if (!$row) {
            return null;
        }

        $identifiers = self::identifiers_for((string) $row->client_id);

        return [
            'client_id'      => (string) $row->client_id,
            'display_name'   => (string) $row->display_name,
            'status'         => (string) $row->status,
            'conflict_count' => (int) $row->conflict_count,
            'sources_count'  => (int) $row->sources_count,
            'identifiers'    => $identifiers,
        ];
    }

    public static function get_client_by_identifier(string $type, string $value): ?array
    {
        global $wpdb;

        $table = self::table(self::TABLE_IDENTIFIERS);
        if (!self::table_exists($table)) {
            return null;
        }

        $normalized = $type === 'mobile'
            ? self::normalize_mobile_value($value)
            : ($type === 'email' ? self::normalize_email_value($value) : trim($value));

        if ($normalized === '') {
            return null;
        }

        $client_id = $wpdb->get_var($wpdb->prepare(
            "SELECT client_id FROM $table WHERE identifier_type = %s AND identifier_value = %s ORDER BY id ASC LIMIT 1",
            $type,
            $normalized
        ));

        return $client_id ? self::get_client((string) $client_id) : null;
    }

    /**
     * قرارداد مشترک: apply_filters('pas_get_client', null, $query)
     * $query: ['client_id'=>...] یا ['type'=>'mobile|email','value'=>...] یا ['wp_user_id'=>12]
     *
     * فقط برای کاربر واردشده با دسترسی بالینی/مدیریتی برمی‌گردد؛ هیچ داده‌ی
     * بالینی در خروجی نیست (فقط شناسه، نام نمایشی، وضعیت و فهرست شناسه‌ها).
     *
     * @param mixed $default
     * @param mixed $query
     * @return mixed
     */
    public static function filter_get_client($default, $query)
    {
        $allowed = current_user_can('manage_options')
            || (defined('PAS_CF_CAP') && current_user_can(PAS_CF_CAP));

        if (!$allowed || !is_array($query)) {
            return $default;
        }

        if (!empty($query['client_id'])) {
            return self::get_client((string) $query['client_id']) ?? $default;
        }

        if (!empty($query['type']) && isset($query['value'])) {
            return self::get_client_by_identifier((string) $query['type'], (string) $query['value']) ?? $default;
        }

        if (!empty($query['wp_user_id'])) {
            return self::get_client_by_identifier('wp_user', (string) (int) $query['wp_user_id']) ?? $default;
        }

        return $default;
    }

    /** @return array<int,array{type:string,value:string}> */
    private static function identifiers_for(string $client_id): array
    {
        global $wpdb;

        $table = self::table(self::TABLE_IDENTIFIERS);
        $rows  = (array) $wpdb->get_results($wpdb->prepare(
            "SELECT identifier_type, identifier_value FROM $table WHERE client_id = %s ORDER BY identifier_type ASC, id ASC",
            $client_id
        ));

        $out = [];
        foreach ($rows as $row) {
            $out[] = [
                'type'  => (string) $row->identifier_type,
                'value' => (string) $row->identifier_value,
            ];
        }

        return $out;
    }

    // ── ابزارهای داخلی ───────────────────────────────────────────────────

    private static function table_exists(string $table): bool
    {
        global $wpdb;

        return $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) === $table;
    }

    private static function normalize_mobile_value(string $value): string
    {
        if ($value === '') {
            return '';
        }

        $normalized = class_exists('Pas_Core_Helpers')
            ? Pas_Core_Helpers::normalize_mobile($value)
            : preg_replace('/\D+/', '', $value);

        return preg_match('/^09\d{9}$/', (string) $normalized) ? (string) $normalized : '';
    }

    private static function normalize_email_value(string $value): string
    {
        $value = strtolower(trim(sanitize_email($value)));

        return ($value !== '' && is_email($value)) ? mb_substr($value, 0, 190) : '';
    }

    private static function normalize_name(string $value): string
    {
        $value = trim(preg_replace('/\s+/u', ' ', $value) ?? '');

        return mb_substr($value, 0, 190);
    }
}
