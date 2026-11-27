<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * موتور نگاشت فیلد (فاز ۲).
 *
 * فرم ارزیابی مراجع (پنل) و پرونده‌ی بالینی دو ساختار متفاوت دارند. طبق اصل
 * «یک‌بار ثبت، همه‌جا استفاده» و «هیچ افزونه‌ای ساختار داخلی دیگری را
 * نمی‌شناسد»، این کلاس فقط یک «نقشه‌ی فیلد» نگه می‌دارد و یک «طرح» (plan)
 * تولید می‌کند؛ نوشتن واقعی همیشه توسط افزونه‌ی صاحب داده (case-file) انجام
 * می‌شود. این کلاس هیچ‌وقت در جداول دیگر نمی‌نویسد.
 *
 * سه قابلیت:
 *  ۱. نگاشت قابل‌تنظیم: جدول `pas_field_maps` (منبع → مقصد + تبدیل + حساسیت).
 *     پیش‌فرض‌ها روی نصب seed می‌شوند (INSERT IGNORE، idempotent) و بعداً
 *     می‌شوند بدون تغییر کد ویرایش/خاموش کرد.
 *  ۲. پیش‌نمایش (dry-run): برای هر فیلد مشخص می‌کند «پر می‌شود»، «یکسان است»
 *     یا «با مقدار فعلی اختلاف دارد» — بدون هیچ نوشتنی.
 *  ۳. ثبت منبع (provenance): جدول `pas_field_provenance` برای هر فیلدی که از
 *     فرم منتقل می‌شود: منبع، مرجع، زمان، کاربر و **هش مقدار قبلی** (نه خود
 *     مقدار) تا هیچ داده‌ای بی‌سابقه بازنویسی نشود.
 */
final class Pas_Core_Field_Mapper
{
    public const DB_VERSION   = '1.0.0';
    public const TABLE_MAPS   = 'field_maps';
    public const TABLE_PROVENANCE = 'field_provenance';

    public const SOURCE_INTAKE = 'zdp_intake/v1';
    public const TARGET_CASE   = 'pas_cf_cases/v1';

    public static function table(string $name): string
    {
        global $wpdb;

        return $wpdb->prefix . 'pas_' . $name;
    }

    public static function install(): void
    {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset = $wpdb->get_charset_collate();

        $maps = self::table(self::TABLE_MAPS);
        dbDelta("CREATE TABLE $maps (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            map_key char(32) NOT NULL DEFAULT '',
            source_schema varchar(60) NOT NULL DEFAULT '',
            source_path varchar(100) NOT NULL DEFAULT '',
            target_schema varchar(60) NOT NULL DEFAULT '',
            target_path varchar(100) NOT NULL DEFAULT '',
            label varchar(190) NOT NULL DEFAULT '',
            transform varchar(30) NOT NULL DEFAULT 'copy',
            sensitivity varchar(20) NOT NULL DEFAULT 'public',
            is_active tinyint(1) NOT NULL DEFAULT 1,
            created_at datetime NOT NULL DEFAULT '1970-01-01 00:00:00',
            updated_at datetime NOT NULL DEFAULT '1970-01-01 00:00:00',
            PRIMARY KEY  (id),
            UNIQUE KEY map_key (map_key),
            KEY target_schema (target_schema)
        ) $charset;");

        $provenance = self::table(self::TABLE_PROVENANCE);
        dbDelta("CREATE TABLE $provenance (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            prov_key char(32) NOT NULL DEFAULT '',
            entity_type varchar(30) NOT NULL DEFAULT '',
            entity_id bigint(20) unsigned NOT NULL DEFAULT 0,
            field_name varchar(100) NOT NULL DEFAULT '',
            source varchar(30) NOT NULL DEFAULT '',
            source_ref varchar(100) NOT NULL DEFAULT '',
            previous_value_hash char(64) NOT NULL DEFAULT '',
            set_at datetime NOT NULL DEFAULT '1970-01-01 00:00:00',
            set_by bigint(20) unsigned NOT NULL DEFAULT 0,
            PRIMARY KEY  (id),
            UNIQUE KEY prov_key (prov_key),
            KEY entity_lookup (entity_type,entity_id)
        ) $charset;");

        self::seed_defaults();
        update_option('pas_mapper_db_version', self::DB_VERSION, false);
    }

    /**
     * نقشه‌ی پیش‌فرض «فرم ارزیابی → پرونده». کلیدهای منبع همان کلیدهای
     * payload فرم در پنل مراجعان هستند (Intake::HEALTH_FIELDS برای سه فیلد
     * سلامت، بقیه در validate همان افزونه).
     *
     * @return array<int,array{source:string,target:string,label:string,transform:string,sensitivity:string}>
     */
    public static function default_maps(): array
    {
        return [
            ['source' => 'full_name', 'target' => 'full_name', 'label' => 'نام و نام خانوادگی', 'transform' => 'copy', 'sensitivity' => 'public'],
            ['source' => 'phone', 'target' => 'phone', 'label' => 'شماره تماس', 'transform' => 'copy', 'sensitivity' => 'public'],
            ['source' => 'age', 'target' => 'age', 'label' => 'سن', 'transform' => 'copy', 'sensitivity' => 'public'],
            ['source' => 'gender', 'target' => 'gender', 'label' => 'جنسیت', 'transform' => 'copy', 'sensitivity' => 'public'],
            ['source' => 'marital', 'target' => 'marital_status', 'label' => 'وضعیت تاهل', 'transform' => 'copy', 'sensitivity' => 'public'],
            ['source' => 'education', 'target' => 'education', 'label' => 'تحصیلات', 'transform' => 'copy', 'sensitivity' => 'public'],
            ['source' => 'job', 'target' => 'occupation', 'label' => 'شغل', 'transform' => 'copy', 'sensitivity' => 'public'],
            ['source' => 'address', 'target' => 'residence', 'label' => 'محل سکونت', 'transform' => 'copy', 'sensitivity' => 'public'],
            ['source' => 'children', 'target' => 'children_count', 'label' => 'تعداد فرزندان', 'transform' => 'copy', 'sensitivity' => 'public'],
            ['source' => 'referral', 'target' => 'referral_source', 'label' => 'نحوه‌ی آشنایی / ارجاع', 'transform' => 'copy', 'sensitivity' => 'public'],
            ['source' => 'father_name', 'target' => 'father_name', 'label' => 'نام پدر', 'transform' => 'copy', 'sensitivity' => 'public'],
            ['source' => 'birth_date', 'target' => 'birth_date', 'label' => 'تاریخ تولد', 'transform' => 'date_jalali', 'sensitivity' => 'public'],
            ['source' => 'med_details', 'target' => 'medical_history', 'label' => 'سابقه‌ی پزشکی / دارو', 'transform' => 'copy', 'sensitivity' => 'clinical'],
            ['source' => 'counseling_details', 'target' => 'counseling_history', 'label' => 'سابقه‌ی مشاوره و روان‌پزشک', 'transform' => 'copy', 'sensitivity' => 'clinical'],
            ['source' => 'substance_details', 'target' => 'substance_use_history', 'label' => 'سابقه‌ی مصرف مواد', 'transform' => 'copy', 'sensitivity' => 'clinical'],
        ];
    }

    public static function seed_defaults(): void
    {
        global $wpdb;

        $table = self::table(self::TABLE_MAPS);
        $now   = current_time('mysql', true);

        foreach (self::default_maps() as $map) {
            $key = md5(self::SOURCE_INTAKE . '|' . $map['source'] . '|' . self::TARGET_CASE . '|' . $map['target']);

            $wpdb->query($wpdb->prepare(
                "INSERT IGNORE INTO $table
                    (map_key, source_schema, source_path, target_schema, target_path, label, transform, sensitivity, is_active, created_at, updated_at)
                 VALUES (%s, %s, %s, %s, %s, %s, %s, %s, 1, %s, %s)",
                $key,
                self::SOURCE_INTAKE,
                $map['source'],
                self::TARGET_CASE,
                $map['target'],
                $map['label'],
                $map['transform'],
                $map['sensitivity'],
                $now,
                $now
            ));
        }
    }

    /**
     * @return array<int,object>
     */
    public static function maps(string $target_schema = self::TARGET_CASE): array
    {
        global $wpdb;

        $table = self::table(self::TABLE_MAPS);
        if (!self::table_exists($table)) {
            return [];
        }

        return (array) $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table WHERE target_schema = %s AND is_active = 1 ORDER BY id ASC",
            $target_schema
        ));
    }

    /**
     * پیش‌نمایش بدون نوشتن. ورودی `$source` همان payload فرم (کلیدها از
     * default_maps) و `$current` مقادیر فعلی پرونده (کلید = نام فیلد مقصد).
     *
     * status: 'fill' (خالی بود)، 'same' (یکسان)، 'conflict' (اختلاف).
     *
     * @return array<int,array{target:string,label:string,value:string,transform:string,sensitivity:string,current:string,status:string}>
     */
    public static function preview(array $source, array $current, string $target_schema = self::TARGET_CASE): array
    {
        $rows = [];

        foreach (self::maps($target_schema) as $map) {
            $source_path = (string) $map->source_path;
            $value       = isset($source[$source_path]) && is_scalar($source[$source_path])
                ? trim((string) $source[$source_path])
                : '';

            if ($value === '') {
                continue; // فیلدهایی که فرم ندارد/خالی است، برای درمانگر باقی می‌ماند
            }

            $target        = (string) $map->target_path;
            $current_value = isset($current[$target]) && is_scalar($current[$target])
                ? trim((string) $current[$target])
                : '';

            if ($current_value === '') {
                $status = 'fill';
            } elseif ($current_value === $value) {
                $status = 'same';
            } else {
                $status = 'conflict';
            }

            $rows[] = [
                'target'      => $target,
                'label'       => (string) $map->label,
                'value'       => $value,
                'transform'   => (string) $map->transform,
                'sensitivity' => (string) $map->sensitivity,
                'current'     => $current_value,
                'status'      => $status,
            ];
        }

        return $rows;
    }

    /**
     * ثبت منبع یک فیلد. `previous_value` فقط برای هش‌گرفتن استفاده می‌شود و
     * هیچ‌جا ذخیره نمی‌شود؛ متن قبلی در همان ستون رمزنگاری‌شده‌ی فیلد می‌ماند
     * (یا در تاریخچه‌ی خودش) — این جدول فقط «چه کسی/چه زمانی/از کجا» را
     * نگه می‌دارد.
     */
    public static function record(
        string $entity_type,
        int $entity_id,
        string $field,
        string $source,
        string $source_ref,
        string $previous_value,
        int $user_id = 0
    ): void {
        global $wpdb;

        $table = self::table(self::TABLE_PROVENANCE);
        if (!self::table_exists($table)) {
            return;
        }

        $user_id  = $user_id > 0 ? $user_id : get_current_user_id();
        $prov_key = md5($entity_type . '|' . $entity_id . '|' . $field . '|' . $source);
        $hash     = $previous_value === '' ? '' : hash('sha256', $previous_value);

        $wpdb->query($wpdb->prepare(
            "INSERT INTO $table
                (prov_key, entity_type, entity_id, field_name, source, source_ref, previous_value_hash, set_at, set_by)
             VALUES (%s, %s, %d, %s, %s, %s, %s, %s, %d)
             ON DUPLICATE KEY UPDATE
                source_ref = VALUES(source_ref),
                previous_value_hash = VALUES(previous_value_hash),
                set_at = VALUES(set_at),
                set_by = VALUES(set_by)",
            $prov_key,
            $entity_type,
            $entity_id,
            $field,
            $source,
            $source_ref,
            $hash,
            current_time('mysql', true),
            $user_id
        ));
    }

    /**
     * @return array<string,object> field_name => row
     */
    public static function provenance_map(string $entity_type, int $entity_id): array
    {
        global $wpdb;

        $table = self::table(self::TABLE_PROVENANCE);
        if (!self::table_exists($table)) {
            return [];
        }

        $rows = (array) $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table WHERE entity_type = %s AND entity_id = %d ORDER BY field_name ASC",
            $entity_type,
            $entity_id
        ));

        $out = [];
        foreach ($rows as $row) {
            $out[(string) $row->field_name] = $row;
        }

        return $out;
    }

    private static function table_exists(string $table): bool
    {
        global $wpdb;

        return $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) === $table;
    }
}
