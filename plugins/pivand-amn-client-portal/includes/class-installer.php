<?php

declare(strict_types=1);

namespace ZoojDarman\ClientPortal;

if (!defined('ABSPATH')) {
    exit;
}

final class Installer
{
    public static function table(string $name): string
    {
        global $wpdb;

        return $wpdb->prefix . 'zdp_' . $name;
    }

    /**
     * ساخت/آپدیت جداول با dbDelta. نکته‌ی حیاتی dbDelta: دقیقاً دو فاصله بعد
     * از PRIMARY KEY، هر ستون در خط خودش، و بدون کاما اضافه در انتهای آخرین
     * ستون — در غیر این صورت dbDelta به‌صورت خاموش تغییرات را اعمال نمی‌کند
     * (این دقیقاً همان باگ فاصله‌گذاری بود که در pivand-amn-login رفع شد).
     */
    public static function create_or_upgrade_tables(): void
    {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset_collate = $wpdb->get_charset_collate();

        $bookings = self::table('bookings');
        dbDelta("CREATE TABLE $bookings (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            service_name varchar(255) NOT NULL DEFAULT '',
            price varchar(100) NOT NULL DEFAULT '',
            booking_date date NOT NULL,
            booking_time varchar(20) NOT NULL DEFAULT '',
            status varchar(50) NOT NULL DEFAULT 'در انتظار بررسی',
            authority varchar(100) NOT NULL DEFAULT '',
            ref_id varchar(100) NOT NULL DEFAULT '',
            created_at datetime NOT NULL DEFAULT '1970-01-01 00:00:00',
            booking_source varchar(30) NOT NULL DEFAULT 'site',
            session_type varchar(20) NOT NULL DEFAULT '',
            therapist_note text NULL,
            reminder_sent_at datetime NULL DEFAULT NULL,
            receipt_path varchar(255) NOT NULL DEFAULT '',
            receipt_uploaded_at datetime NULL DEFAULT NULL,
            PRIMARY KEY  (id),
            KEY user_id (user_id),
            KEY booking_slot (booking_date,booking_time)
        ) $charset_collate;");

        // اگر از نسخه‌ای که uniq_slot را به‌عنوان UNIQUE KEY داشت آپگرید شده،
        // آن ایندکس را حذف کن. آن قید یک باگ جدی داشت: چون هیچ نوبت لغو/ناموفقی
        // هیچ‌وقت از جدول حذف نمی‌شود (فقط status تغییر می‌کند)، همان
        // UNIQUE(booking_date, booking_time) باعث می‌شد یک بازه‌ی زمانی که
        // پرداختش یک‌بار شکست خورده، برای همیشه غیرقابل‌رزرو بماند — حتی برای
        // مراجع دیگر. dbDelta ایندکس‌های اضافی را خودش حذف نمی‌کند، پس دستی
        // چک و DROP می‌شود. کنترل هم‌زمانی حالا با یک named lock در سطح
        // اپلیکیشن انجام می‌شود (نگاه کنید به Booking::handle_create_booking).
        $existing_index = $wpdb->get_row("SHOW INDEX FROM $bookings WHERE Key_name = 'uniq_slot'");
        if ($existing_index) {
            $wpdb->query("ALTER TABLE $bookings DROP INDEX uniq_slot");
        }

        $messages = self::table('messages');
        dbDelta("CREATE TABLE $messages (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            message_text text NOT NULL,
            admin_reply text NULL,
            status varchar(50) NOT NULL DEFAULT 'خوانده نشده',
            created_at datetime NOT NULL DEFAULT '1970-01-01 00:00:00',
            PRIMARY KEY  (id),
            KEY user_id (user_id)
        ) $charset_collate;");

        $surveys = self::table('surveys');
        dbDelta("CREATE TABLE $surveys (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            q1 text NOT NULL,
            q2 text NOT NULL,
            q3 text NOT NULL,
            suggestions text NULL,
            rating tinyint(1) unsigned NULL DEFAULT NULL,
            extra_answers longtext NULL,
            consent tinyint(1) NOT NULL DEFAULT 0,
            approved tinyint(1) NOT NULL DEFAULT 0,
            created_at datetime NOT NULL DEFAULT '1970-01-01 00:00:00',
            PRIMARY KEY  (id)
        ) $charset_collate;");

        $intake = self::table('intake');
        dbDelta("CREATE TABLE $intake (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            file_number varchar(50) NOT NULL DEFAULT '',
            payload longtext NOT NULL,
            payload_encrypted tinyint(1) NOT NULL DEFAULT 0,
            submitted_at datetime NOT NULL DEFAULT '1970-01-01 00:00:00',
            updated_at datetime NOT NULL DEFAULT '1970-01-01 00:00:00',
            PRIMARY KEY  (id),
            UNIQUE KEY user_id (user_id)
        ) $charset_collate;");

        $leads = self::table('leads');
        dbDelta("CREATE TABLE $leads (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            phone varchar(20) NOT NULL DEFAULT '',
            source varchar(100) NOT NULL DEFAULT '',
            quiz_topic varchar(50) NOT NULL DEFAULT '',
            quiz_score int(11) NOT NULL DEFAULT 0,
            quiz_band varchar(50) NOT NULL DEFAULT '',
            followed_up_24h tinyint(1) NOT NULL DEFAULT 0,
            followed_up_72h tinyint(1) NOT NULL DEFAULT 0,
            link_clicked tinyint(1) NOT NULL DEFAULT 0,
            booked tinyint(1) NOT NULL DEFAULT 0,
            created_at datetime NOT NULL DEFAULT '1970-01-01 00:00:00',
            PRIMARY KEY  (id),
            KEY phone (phone)
        ) $charset_collate;");

        update_option('zdp_db_version', ZDP_DB_VERSION);
        Security::register_capability();
    }

    /**
     * مهاجرت یک‌باره‌ی داده‌های واقعی مراجعین از جداول قدیمی wp_cpp_* (اگر
     * وجود داشته باشند) به جداول جدید wp_zdp_*. فقط یک‌بار اجرا می‌شود
     * (پرچم option) تا اجرای مجدد activation باعث تکرار رکوردها نشود.
     * هیچ داده‌ای از جداول قدیمی حذف نمی‌شود — صرفاً کپی می‌شود، تا در صورت
     * بروز مشکل بتوان به افزونه‌ی قبلی برگشت.
     */
    public static function migrate_from_legacy_plugin(): void
    {
        if (get_option('zdp_legacy_migrated')) {
            return;
        }

        global $wpdb;
        $legacy_bookings = $wpdb->prefix . 'cpp_bookings';
        $legacy_messages = $wpdb->prefix . 'cpp_messages';
        $legacy_surveys  = $wpdb->prefix . 'cpp_surveys';
        $legacy_leads    = $wpdb->prefix . 'cpp_leads';

        // phpcs:disable WordPress.DB -- جداول dynamic هستند و از پیش با dbDelta ساخته شده‌اند.
        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $legacy_bookings)) === $legacy_bookings) {
            $wpdb->query(
                "INSERT IGNORE INTO {$wpdb->prefix}zdp_bookings
                 (id,user_id,service_name,price,booking_date,booking_time,status,authority,ref_id,created_at)
                 SELECT id,user_id,service_name,price,booking_date,booking_time,status,authority,ref_id,created_at
                 FROM $legacy_bookings"
            );
            self::log_migration_error('bookings');
        }

        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $legacy_messages)) === $legacy_messages) {
            $wpdb->query(
                "INSERT IGNORE INTO {$wpdb->prefix}zdp_messages
                 (id,user_id,message_text,admin_reply,status,created_at)
                 SELECT id,user_id,message_text,admin_reply,status,created_at
                 FROM $legacy_messages"
            );
            self::log_migration_error('messages');
        }

        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $legacy_surveys)) === $legacy_surveys) {
            $wpdb->query(
                "INSERT IGNORE INTO {$wpdb->prefix}zdp_surveys
                 (id,q1,q2,q3,suggestions,consent,approved,created_at)
                 SELECT id,q1,q2,q3,suggestions,consent,approved,created_at
                 FROM $legacy_surveys"
            );
            self::log_migration_error('surveys');
        }

        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $legacy_leads)) === $legacy_leads) {
            $wpdb->query(
                "INSERT IGNORE INTO {$wpdb->prefix}zdp_leads
                 (id,phone,source,quiz_topic,quiz_score,quiz_band,followed_up_24h,followed_up_72h,link_clicked,booked,created_at)
                 SELECT id,phone,source,quiz_topic,quiz_score,quiz_band,followed_up_24h,followed_up_72h,link_clicked,booked,created_at
                 FROM $legacy_leads"
            );
            self::log_migration_error('leads');
        }
        // phpcs:enable

        // مهاجرت متای کاربران: cpp_billing_phone / cpp_full_name -> zdp_mobile / zdp_full_name
        // بدون حذف متای قدیمی، تا سازگاری با هر کد باقی‌مانده حفظ شود.
        $users = get_users(['meta_key' => 'cpp_billing_phone', 'fields' => ['ID']]);
        foreach ($users as $u) {
            $mobile = get_user_meta($u->ID, 'cpp_billing_phone', true);
            if ($mobile && !get_user_meta($u->ID, 'zdp_mobile', true)) {
                zdp_set_user_mobile((int) $u->ID, to_local_format((string) $mobile));
            }
            $full_name = get_user_meta($u->ID, 'cpp_full_name', true);
            if ($full_name && !get_user_meta($u->ID, 'zdp_full_name', true)) {
                update_user_meta($u->ID, 'zdp_full_name', $full_name);
            }
        }

        // رفع ناهماهنگی کشف‌شده: این پلاگین تا نسخه‌ی ۱.۴.۵ فقط متای اختصاصی
        // `zdp_mobile` را می‌ساخت، نه `cellphone` — کلید هویت مشترکی که
        // pivand-amn-panel و pivand-amn-sms-bridge و بقیه‌ی اکوسیستم برایش
        // انتظار دارند. یعنی مراجعینی که از طریق این پلاگین حساب ساخته بودند
        // برای بقیه‌ی افزونه‌ها نامرئی بودند. این مهاجرت یک‌باره، برای هر
        // کاربری که zdp_mobile دارد ولی هنوز cellphone ندارد، آن را پر می‌کند.
        $zdp_only_users = get_users(['meta_key' => 'zdp_mobile', 'fields' => ['ID']]);
        foreach ($zdp_only_users as $u) {
            if (get_user_meta($u->ID, 'cellphone', true)) {
                continue;
            }
            $mobile = get_user_meta($u->ID, 'zdp_mobile', true);
            if ($mobile) {
                update_user_meta((int) $u->ID, 'cellphone', $mobile);
            }
        }

        update_option('zdp_legacy_migrated', gmdate('Y-m-d H:i:s'));
        audit_log('legacy_migration_completed');
    }

    /**
     * اگر آخرین query روی $wpdb خطا داشته باشد، در audit log ثبت می‌کند.
     * چون migrate_from_legacy_plugin() فقط یک‌بار اجرا می‌شود، بی‌صدا رد شدن
     * از کنار خطای یکی از ۴ کوئری یعنی آن بخش برای همیشه ناقص می‌ماند بدون
     * این‌که جایی ثبت شده باشد.
     */
    private static function log_migration_error(string $table): void
    {
        global $wpdb;
        if ($wpdb->last_error !== '') {
            audit_log('legacy_migration_error', [
                'table' => $table,
                'error' => $wpdb->last_error,
            ]);
        }
    }

    /**
     * ترمیم مستقل برای سایت‌هایی که قبل از رفع باگ ستون‌های survey (نبود id
     * در SELECT که باعث خطای «تعداد ستون‌ها مطابقت ندارد» و شکست کامل کوئری
     * می‌شد) پرچم zdp_legacy_migrated را با موفقیت کاذب دریافت کرده‌اند.
     * چون آن پرچم دیگر هرگز اجازه‌ی اجرای مجدد کل migrate_from_legacy_plugin()
     * را نمی‌دهد، این تابع مستقل از آن پرچم صرفاً وضعیت surveys را می‌سنجد و
     * فقط در صورت ناقص بودن، همان یک جدول را دوباره migrate می‌کند. باید در
     * admin_init یا یک‌بار به‌صورت دستی هوک شود.
     */
    public static function reconcile_legacy_surveys(): void
    {
        if (get_option('zdp_surveys_reconciled')) {
            return;
        }

        global $wpdb;
        $legacy_surveys = $wpdb->prefix . 'cpp_surveys';

        // phpcs:disable WordPress.DB
        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $legacy_surveys)) !== $legacy_surveys) {
            update_option('zdp_surveys_reconciled', gmdate('Y-m-d H:i:s'));
            return;
        }

        $legacy_count = (int) $wpdb->get_var("SELECT COUNT(*) FROM $legacy_surveys");
        $new_count    = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}zdp_surveys");

        if ($new_count < $legacy_count) {
            $wpdb->query(
                "INSERT IGNORE INTO {$wpdb->prefix}zdp_surveys
                 (id,q1,q2,q3,suggestions,consent,approved,created_at)
                 SELECT id,q1,q2,q3,suggestions,consent,approved,created_at
                 FROM $legacy_surveys"
            );
            self::log_migration_error('surveys_reconcile');
            audit_log('legacy_surveys_reconciled', [
                'before' => $new_count,
                'legacy' => $legacy_count,
            ]);
        }

        update_option('zdp_surveys_reconciled', gmdate('Y-m-d H:i:s'));
    }

    /**
     * جدول‌های موقتِ افزونه‌ی قدیمی که فقط برای migrate خوانده می‌شوند و
     * هیچ‌جای کد روی‌شان نمی‌نویسد. نگه‌داشتن دائمی‌شان روی دیتابیس فایده‌ای
     * ندارد، ولی حذف خودکار و بی‌قیدوشرط‌شان هم خطرناک است — اگر migration
     * ناقص بوده و کسی متوجه نشده باشد، تنها منبع بازیابی از بین می‌رود.
     * برای همین حذف این جداول هرگز خودکار (نه در activation، نه در هیچ هوکی)
     * انجام نمی‌شود؛ فقط از پنل ادمین و با تایید دستی، و فقط وقتی parity
     * کامل تایید شده باشد.
     */
    private const LEGACY_TABLES = [
        'cpp_bookings' => 'zdp_bookings',
        'cpp_messages' => 'zdp_messages',
        'cpp_surveys'  => 'zdp_surveys',
        'cpp_leads'    => 'zdp_leads',
    ];

    /**
     * گزارش parity بین هر جدول قدیمی و معادل جدیدش، برای نمایش در پنل ادمین
     * قبل از این‌که ادمین اجازه‌ی حذف بدهد. هر ردیف شامل:
     * - exists: آیا جدول قدیمی اصلاً روی این سایت وجود دارد
     * - legacy_count / new_count: تعداد ردیف هر طرف
     * - safe_to_drop: true فقط وقتی new_count >= legacy_count (یعنی هیچ
     *   ردیفی در معادل جدید کم نیست)
     */
    public static function legacy_table_parity(): array
    {
        global $wpdb;
        $report = [];

        foreach (self::LEGACY_TABLES as $legacy_suffix => $new_suffix) {
            $legacy_table = $wpdb->prefix . $legacy_suffix;
            $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $legacy_table)) === $legacy_table;

            $legacy_count = $exists ? (int) $wpdb->get_var("SELECT COUNT(*) FROM $legacy_table") : 0;
            $new_count    = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}{$new_suffix}");

            $report[$legacy_suffix] = [
                'legacy_table' => $legacy_table,
                'new_table'    => $wpdb->prefix . $new_suffix,
                'exists'       => $exists,
                'legacy_count' => $legacy_count,
                'new_count'    => $new_count,
                'safe_to_drop' => !$exists || $new_count >= $legacy_count,
            ];
        }

        return $report;
    }

    /**
     * DROP قطعی جداول قدیمی. فقط جداولی که در زمان فراخوانی safe_to_drop
     * هستند (parity کامل شده) واقعاً حذف می‌شوند؛ بقیه بی‌صدا رد می‌شوند تا
     * فراخوانی از پنل ادمین بتواند به کاربر بگوید کدام‌ها حذف نشدند.
     * پیش‌نیاز اضافه: هر دو پرچم migration باید قطعی شده باشند، وگرنه اصلاً
     * parity معنی ندارد (ممکن است migration هنوز در حال انجام باشد).
     *
     * @return array<string,bool> نگاشت نام‌جدول => آیا واقعاً DROP شد
     */
    public static function drop_legacy_tables(): array
    {
        $results = [];

        if (!get_option('zdp_legacy_migrated') || !get_option('zdp_surveys_reconciled')) {
            audit_log('legacy_cleanup_blocked', ['reason' => 'migration_not_finalized']);
            return $results;
        }

        global $wpdb;
        $parity = self::legacy_table_parity();

        foreach ($parity as $legacy_suffix => $row) {
            if (!$row['exists']) {
                continue;
            }
            if (!$row['safe_to_drop']) {
                $results[$legacy_suffix] = false;
                continue;
            }

            // phpcs:ignore WordPress.DB -- نام جدول از لیست ثابت داخلی می‌آید، نه ورودی کاربر.
            $wpdb->query("DROP TABLE IF EXISTS {$row['legacy_table']}");
            $results[$legacy_suffix] = true;
        }

        audit_log('legacy_tables_dropped', [
            'dropped_by' => get_current_user_id(),
            'results'    => $results,
        ]);

        return $results;
    }

    /**
     * اطمینان از وجود صفحه‌ی «/panel/» حاوی شورت‌کد پنل مراجع. اگر قبلاً از
     * افزونه‌ی قدیمی صفحه‌ای با شورت‌کد custom_client_panel وجود دارد، آن را
     * دست نمی‌زنیم (چون احتمالاً لینک‌های تبلیغاتی/پیامکی به همان صفحه اشاره
     * دارند)؛ فقط اگر هیچ صفحه‌ای با هیچ‌کدام از دو شورت‌کد پیدا نشد، صفحه
     * جدید می‌سازیم.
     *
     * باگ رفع‌شده: نسخه‌ی قبلی فقط چک می‌کرد که option زدپ_panel_page_id ست
     * شده یا نه — نه اینکه صفحه‌ی متناظر با آن شناسه واقعاً هنوز وجود دارد.
     * یعنی اگر ادمین صفحه‌ی پنل را از داخل وردپرس پاک (یا حتی فقط به Trash
     * منتقل) می‌کرد، این option همچنان مقدار قدیمی را نگه می‌داشت و تابع
     * همیشه زودهنگام return می‌شد — پس هیچ صفحه‌ی جایگزینی، نه با فعال/
     * غیرفعال‌کردن پلاگین و نه با هیچ کار دیگری، دوباره ساخته نمی‌شد.
     * الان به‌جای اعتماد کورکورانه به وجود option، با get_post() بررسی
     * می‌شود که آن شناسه هنوز به یک صفحه‌ی publish واقعی اشاره می‌کند؛ اگر
     * نه (پاک شده، Trash شده، یا نوع پستش عوض شده)، option قدیمی دور ریخته
     * می‌شود و مسیر جست‌وجو/ساختِ صفحه‌ی جدید از نو اجرا می‌شود.
     */
    public static function ensure_booking_page_exists(): void
    {
        $stored_id = (int) get_option('zdp_panel_page_id');

        if ($stored_id) {
            $stored_page = get_post($stored_id);

            if ($stored_page && $stored_page->post_type === 'page' && $stored_page->post_status === 'publish') {
                return;
            }

            // شناسه‌ی ذخیره‌شده دیگر معتبر نیست (صفحه پاک/Trash شده)؛ باید از نو ساخته شود.
            delete_option('zdp_panel_page_id');
            audit_log('panel_page_missing_recreating', ['old_page_id' => $stored_id]);
        }

        global $wpdb;
        $existing = $wpdb->get_var(
            "SELECT ID FROM {$wpdb->posts}
             WHERE (post_content LIKE '%custom_client_panel%' OR post_content LIKE '%zdp_client_panel%')
             AND post_status = 'publish' AND post_type = 'page' LIMIT 1"
        );

        if ($existing) {
            update_option('zdp_panel_page_id', (int) $existing);

            return;
        }

        $page_id = wp_insert_post([
            'post_title'   => 'پنل مراجعان',
            'post_name'    => 'panel',
            'post_content' => '[zdp_client_panel]',
            'post_status'  => 'publish',
            'post_type'    => 'page',
        ]);

        if (!is_wp_error($page_id) && $page_id) {
            update_option('zdp_panel_page_id', (int) $page_id);
            audit_log('panel_page_created', ['page_id' => $page_id]);
        }
    }

    /**
     * مقداردهی اولیه‌ی کد الگوهای پیامکی تأییدشده در ملی‌پیامک برای دامنه‌ی
     * peyvandamn.ir. از add_option استفاده می‌شود (نه update_option) تا اگر
     * روی سایتی از قبل مقدار دیگری دستی ست شده، این مقادیر آن را بازنویسی
     * نکنند — فقط نصب تازه یا فیلد خالی را پر می‌کنند.
     *
     * نگاشت کد الگو ↔ کاربرد (طبق پنل ملی‌پیامک، تاریخ تایید ۱۴۰۵/۰۴/۲۲):
     * 493403 = «کد ورود»            → OTP ورود
     * 493405 = «تایید نوبت»          → تاییدیه‌ی نوبت برای مراجع
     * 493406 = «منشی»               → اطلاع نوبت جدید به مدیر
     * 493408 = «الگوی ۲۴ ساعته»     → پیگیری لید ۲۴ ساعته
     * 493411 = «الگوی ۷۲ ساعته»     → پیگیری لید ۷۲ ساعته
     */
    public static function seed_default_sms_templates(): void
    {
        add_option('zdp_sms_body_id', '493403');
        add_option('zdp_sms_booking_body_id', '493405');
        add_option('zdp_sms_admin_body_id', '493406');
        add_option('zdp_lead_followup_24h_template', '493408');
        add_option('zdp_lead_followup_72h_template', '493411');
        // باگ رفع‌شده: قبلاً این مقدار با پیشوند بین‌المللی «۰۰۹۸» seed می‌شد،
        // در حالی‌که هر شماره‌ی موبایل دیگر در این پلاگین همیشه با
        // to_local_format() به فرمت 09xxxxxxxxx نرمال می‌شود (همان فرمتی که
        // placeholder فیلد در پیشخوان هم نشان می‌دهد و handler واقعی pas_send_sms
        // انتظارش را دارد) — نگاه کن به رفع مشابه در Admin::handle_save_settings.
        add_option('zdp_admin_mobile', '09199087264');
        add_option('zdp_appointment_reminder_enabled', false);
        add_option('zdp_appointment_reminder_hours_before', 24);
        add_option('zdp_sms_reminder_body_id', '498328');
    }
}
