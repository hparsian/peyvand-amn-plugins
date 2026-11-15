<?php

if (!defined('ABSPATH')) {
    exit;
}

final class Pas_CF_Installer
{
    public static function table(string $name): string
    {
        global $wpdb;
        return $wpdb->prefix . 'pas_cf_' . $name;
    }

    /**
     * dbDelta حساس به فرمت است: هر ستون در خط خودش، دقیقاً دو فاصله بعد از
     * PRIMARY KEY، بدون کاما اضافه بعد از آخرین ستون — طبق همان قاعده‌ای که
     * در بقیه‌ی افزونه‌های خانواده رعایت شده.
     */
    public static function create_or_upgrade_tables(): void
    {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset_collate = $wpdb->get_charset_collate();

        $cases = self::table('cases');
        dbDelta("CREATE TABLE $cases (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            phone varchar(20) NOT NULL DEFAULT '',
            client_id char(29) NOT NULL DEFAULT '',
            full_name varchar(190) NOT NULL DEFAULT '',
            age varchar(10) NOT NULL DEFAULT '',
            marital_status varchar(100) NOT NULL DEFAULT '',
            education varchar(190) NOT NULL DEFAULT '',
            occupation varchar(190) NOT NULL DEFAULT '',
            residence varchar(190) NOT NULL DEFAULT '',
            marriage_type varchar(100) NOT NULL DEFAULT '',
            children_count varchar(10) NOT NULL DEFAULT '',
            gender varchar(20) NOT NULL DEFAULT '',
            birth_date date NULL,
            father_name varchar(190) NOT NULL DEFAULT '',
            referral_source varchar(190) NOT NULL DEFAULT '',
            intake_submitted_at datetime NULL DEFAULT NULL,
            reminder_consent tinyint(1) NOT NULL DEFAULT 0,
            reminder_channel varchar(10) NOT NULL DEFAULT 'sms',
            reminder_last_sent_at varchar(19) NOT NULL DEFAULT '',
            failed_marriage_history text NULL,
            medical_history_encrypted longtext NULL,
            counseling_history_encrypted longtext NULL,
            self_harm_history_encrypted longtext NULL,
            substance_use_history_encrypted longtext NULL,
            spouse_full_name varchar(190) NOT NULL DEFAULT '',
            spouse_phone varchar(20) NOT NULL DEFAULT '',
            spouse_age varchar(10) NOT NULL DEFAULT '',
            spouse_education varchar(190) NOT NULL DEFAULT '',
            spouse_occupation varchar(190) NOT NULL DEFAULT '',
            spouse_failed_marriage_history text NULL,
            spouse_medical_history_encrypted longtext NULL,
            spouse_counseling_history_encrypted longtext NULL,
            spouse_self_harm_history_encrypted longtext NULL,
            spouse_substance_use_history_encrypted longtext NULL,
            partner_case_id bigint(20) unsigned NOT NULL DEFAULT 0,
            main_conflict_topics text NULL,
            symptom_mood_depression_encrypted longtext NULL,
            symptom_anxiety_encrypted longtext NULL,
            q_main_reason text NULL,
            q_positive_negative_traits text NULL,
            q_conflict_resolution text NULL,
            q_reason_staying text NULL,
            q_schema_attachment_encrypted longtext NULL,
            q_therapy_goals text NULL,
            relationship_satisfaction_pre varchar(20) NOT NULL DEFAULT '',
            relationship_satisfaction_post varchar(20) NOT NULL DEFAULT '',
            prior_therapy text NULL,
            topic varchar(190) NOT NULL DEFAULT '',
            assigned_therapist bigint(20) unsigned NOT NULL DEFAULT 0,
            status varchar(50) NOT NULL DEFAULT 'فعال',
            created_by bigint(20) unsigned NOT NULL DEFAULT 0,
            created_at datetime NOT NULL DEFAULT '1970-01-01 00:00:00',
            updated_at datetime NOT NULL DEFAULT '1970-01-01 00:00:00',
            PRIMARY KEY  (id),
            KEY phone (phone),
            KEY client_id (client_id),
            KEY assigned_therapist (assigned_therapist),
            KEY partner_case_id (partner_case_id)
        ) $charset_collate;");

        // یادداشت‌های جلسه («خلاصه‌ی جلسه») — session_number از نسخه‌ی 1.9.0
        // اضافه شد (0 = شماره‌ی نامشخص/ثبت‌های قدیمی)؛ dbDelta ستون جدید را
        // به‌صورت idempotent به جدول موجود اضافه می‌کند. content_encrypted شامل نانس + متن رمزنگاری‌شده
        // است (نگاه کنید به Pas_CF_Crypto). هیچ‌وقت متن باز اینجا ذخیره نشود.
        $notes = self::table('notes');
        dbDelta("CREATE TABLE $notes (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            case_id bigint(20) unsigned NOT NULL,
            session_date date NOT NULL,
            session_number smallint(5) unsigned NOT NULL DEFAULT 0,
            content_encrypted longtext NOT NULL,
            author_id bigint(20) unsigned NOT NULL DEFAULT 0,
            duration_minutes smallint(5) unsigned NOT NULL DEFAULT 0,
            status varchar(20) NOT NULL DEFAULT 'done',
            next_session_date varchar(10) NOT NULL DEFAULT '',
            is_joint tinyint(1) NOT NULL DEFAULT 0,
            created_at datetime NOT NULL DEFAULT '1970-01-01 00:00:00',
            PRIMARY KEY  (id),
            KEY case_id (case_id)
        ) $charset_collate;");

        // لاگ دسترسی — هر بازدید/ویرایش پرونده اینجا ثبت می‌شود تا معلوم
        // باشد چه کسی، چه زمانی، به پرونده‌ی چه کسی دسترسی داشته.
        $audit = self::table('audit_log');
        dbDelta("CREATE TABLE $audit (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            case_id bigint(20) unsigned NOT NULL,
            user_id bigint(20) unsigned NOT NULL DEFAULT 0,
            action varchar(50) NOT NULL DEFAULT '',
            ip varchar(45) NOT NULL DEFAULT '',
            created_at datetime NOT NULL DEFAULT '1970-01-01 00:00:00',
            PRIMARY KEY  (id),
            KEY case_id (case_id)
        ) $charset_collate;");

        // تاریخچه‌ی ویرایش یادداشت‌های جلسه — قبل از هر ویرایش، نسخه‌ی فعلی
        // یادداشت اینجا snapshot می‌شود (نه بعد از ویرایش)، پس این جدول
        // همیشه شامل نسخه‌های *قبلی* است، نه نسخه‌ی فعلی که در جدول notes
        // می‌ماند. حذف یادداشت هم یک ورودی اینجا می‌گذارد (action='deleted')
        // تا محتوای حذف‌شده برای بازبینی/ادیت قابل بازیابی بماند.
        $note_history = self::table('note_history');
        dbDelta("CREATE TABLE $note_history (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            note_id bigint(20) unsigned NOT NULL,
            case_id bigint(20) unsigned NOT NULL,
            session_date date NOT NULL,
            session_number smallint(5) unsigned NOT NULL DEFAULT 0,
            content_encrypted longtext NOT NULL,
            author_id bigint(20) unsigned NOT NULL DEFAULT 0,
            action varchar(20) NOT NULL DEFAULT 'edited',
            changed_by bigint(20) unsigned NOT NULL DEFAULT 0,
            changed_at datetime NOT NULL DEFAULT '1970-01-01 00:00:00',
            PRIMARY KEY  (id),
            KEY note_id (note_id),
            KEY case_id (case_id)
        ) $charset_collate;");

        update_option('pas_cf_db_version', PAS_CF_VERSION);
    }
}
