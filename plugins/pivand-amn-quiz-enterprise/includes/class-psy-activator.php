<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Psy_Quiz_Activator {

    public static function activate(): void {
        global $wpdb;
        $c = $wpdb->get_charset_collate();

        // نکته‌ی حیاتی: dbDelta() با "IF NOT EXISTS" درست کار نمی‌کند — این تابع
        // با regex ساختار "CREATE TABLE {نام} (" را پارس می‌کند تا ستون‌های
        // موجود را با تعریف جدید مقایسه کند و ALTER لازم را بسازد. با وجود
        // "IF NOT EXISTS"، این تشخیص در برخی نسخه‌های وردپرس/MySQL شکست
        // می‌خورد و روی سایت‌هایی که جدول از قبل ساخته شده، ستون‌های جدیدِ
        // نسخه‌های بعدی هرگز اضافه نمی‌شوند (دقیقاً همان دسته باگ فاصله‌گذاری
        // dbDelta که در pivand-amn-login رفع شد). دیگر نیازی به "IF NOT
        // EXISTS" هم نیست چون dbDelta خودش idempotent است.
        $sql1 = "CREATE TABLE {$wpdb->prefix}psy_invitations (
            id           bigint(20)   NOT NULL AUTO_INCREMENT,
            quiz_id      varchar(50)  NOT NULL,
            client_name  varchar(255) NOT NULL,
            client_phone varchar(30)  NOT NULL DEFAULT '',
            client_email varchar(255) NOT NULL DEFAULT '',
            client_id    char(29)     NOT NULL DEFAULT '',
            access_token varchar(64)  NOT NULL,
            status       varchar(20)  NOT NULL DEFAULT 'unused',
            attempt_id   bigint(20)   DEFAULT NULL,
            created_at   datetime     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            started_at   datetime     DEFAULT NULL,
            expires_at   datetime     DEFAULT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY access_token (access_token),
            KEY quiz_id (quiz_id),
            KEY status (status),
            KEY expires_at (expires_at),
            KEY client_phone (client_phone),
            KEY client_email (client_email),
            KEY client_id (client_id)
        ) $c;";

        $sql2 = "CREATE TABLE {$wpdb->prefix}psy_attempts (
            id            bigint(20)  NOT NULL AUTO_INCREMENT,
            quiz_id       varchar(50) NOT NULL,
            invitation_id bigint(20)  NOT NULL,
            client_id     char(29)    NOT NULL DEFAULT '',
            answers       longtext    NOT NULL,
            result_data   longtext    NOT NULL,
            completed_at  datetime    NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY quiz_id (quiz_id),
            KEY invitation_id (invitation_id),
            KEY client_id (client_id)
        ) $c;";

        // جدول زوجین — هر رکورد دو attempt از آزمون LoveStory را به هم وصل می‌کند
        $sql3 = "CREATE TABLE {$wpdb->prefix}psy_couples (
            id           bigint(20)   NOT NULL AUTO_INCREMENT,
            label        varchar(255) NOT NULL,
            attempt1_id  bigint(20)   NOT NULL,
            attempt2_id  bigint(20)   NOT NULL,
            created_at   datetime     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY attempt1_id (attempt1_id),
            KEY attempt2_id (attempt2_id)
        ) $c;";

        // جدول ذخیره موقت پاسخ‌های نیمه‌تمام (Resume آزمون) — v3.6
        $sql4 = "CREATE TABLE {$wpdb->prefix}psy_drafts (
            id           bigint(20)   NOT NULL AUTO_INCREMENT,
            token        varchar(64)  NOT NULL,
            answers      longtext     NOT NULL,
            last_qnum    int(11)      NOT NULL DEFAULT 1,
            saved_at     datetime     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY token (token)
        ) $c;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql1 );
        dbDelta( $sql2 );
        dbDelta( $sql3 );
        dbDelta( $sql4 );

        // جدول لاگ‌های اختصاصی پلاگین
        Psy_Logger::create_table();

        // migration: اضافه کردن ستون expires_at اگر وجود نداشته باشد (برای سایت‌های قدیمی)
        $col_exists = $wpdb->get_var( "SHOW COLUMNS FROM {$wpdb->prefix}psy_invitations LIKE 'expires_at'" );
        if ( ! $col_exists ) {
            $wpdb->query( "ALTER TABLE {$wpdb->prefix}psy_invitations ADD COLUMN expires_at datetime DEFAULT NULL, ADD KEY expires_at (expires_at)" );
        }

        // migration: اضافه کردن ستون started_at برای ردیابی دقیق زمان شروع processing
        $started_exists = $wpdb->get_var( "SHOW COLUMNS FROM {$wpdb->prefix}psy_invitations LIKE 'started_at'" );
        if ( ! $started_exists ) {
            $wpdb->query( "ALTER TABLE {$wpdb->prefix}psy_invitations ADD COLUMN started_at datetime DEFAULT NULL AFTER created_at" );
        }

        // migration (v3.9): اضافه کردن ستون client_email — برای اتصال مراجعینی
        // که شماره ایرانی برای دریافت پیامک ندارند (مثلاً مراجعین خارج از کشور)
        // به آزمونی که برایشان تعریف شده، از طریق کد ایمیلی به‌جای پیامکی.
        $email_col_exists = $wpdb->get_var( "SHOW COLUMNS FROM {$wpdb->prefix}psy_invitations LIKE 'client_email'" );
        if ( ! $email_col_exists ) {
            $wpdb->query( "ALTER TABLE {$wpdb->prefix}psy_invitations ADD COLUMN client_email varchar(255) NOT NULL DEFAULT '' AFTER client_phone" );
        }

        // migration (DB 3.11 / فاز ۳ اکوسیستم): ستون client_id — شناسهٔ واحد مراجع
        // از رجیستری هسته. افزایشی و nullable-ish (پیش‌فرض رشتهٔ خالی): رکوردهای
        // قدیمی بدون شناسه می‌مانند و مسیر فعلی (تطبیق با شماره) دقیقاً مثل قبل
        // کار می‌کند؛ رکوردهای جدید هر دو را دارند.
        $cid_col_exists = $wpdb->get_var( "SHOW COLUMNS FROM {$wpdb->prefix}psy_invitations LIKE 'client_id'" );
        if ( ! $cid_col_exists ) {
            $wpdb->query( "ALTER TABLE {$wpdb->prefix}psy_invitations ADD COLUMN client_id char(29) NOT NULL DEFAULT '' AFTER client_email, ADD KEY client_id (client_id)" );
        }

        $cid_att_exists = $wpdb->get_var( "SHOW COLUMNS FROM {$wpdb->prefix}psy_attempts LIKE 'client_id'" );
        if ( ! $cid_att_exists ) {
            $wpdb->query( "ALTER TABLE {$wpdb->prefix}psy_attempts ADD COLUMN client_id char(29) NOT NULL DEFAULT '' AFTER invitation_id, ADD KEY client_id (client_id)" );
        }

        // migration (DB 3.10): ایندکس روی client_phone و client_email — پنل مراجع
        // با هر ورود/بازدید بر اساس همین دو ستون جستجو می‌کند و بدون ایندکس
        // کل جدول را اسکن می‌کرد. فقط ایندکس اضافه می‌شود؛ ستون/داده تغییر نمی‌کند.
        foreach ( [ 'client_phone', 'client_email' ] as $idx_col ) {
            $idx_exists = $wpdb->get_var( $wpdb->prepare(
                "SHOW INDEX FROM {$wpdb->prefix}psy_invitations WHERE Key_name = %s",
                $idx_col
            ) );
            if ( ! $idx_exists ) {
                $wpdb->query( "ALTER TABLE {$wpdb->prefix}psy_invitations ADD KEY {$idx_col} ({$idx_col})" );
            }
        }

        // migration (v3.8 / BUG-22): تا قبل از این نسخه، client_phone موقع
        // ثبت دعوت‌نامه نرمال نمی‌شد (فقط sanitize_text_field)، در حالی که
        // جستجوی مبتنی بر فیلتر (psy_get_client_name_for_phone و
        // psy_get_pending_quizzes_for_phone) همیشه با فرمت نرمال‌شده مقایسه
        // می‌کند. یعنی هر رکورد قدیمی که شماره‌اش با فاصله/+۹۸/۰۰۹۸ ثبت شده،
        // در تطبیق فیلتری پیدا نمی‌شود. این یک‌بار همه‌ی رکوردهای قدیمی را
        // نرمال می‌کند. ایمن است: normalize_mobile یک تابع idempotent است
        // (روی مقدار از قبل نرمال هم که اجرا شود همان را برمی‌گرداند)، پس
        // اجرای دوباره‌ی این migration روی رکوردهای قبلاً درست، بی‌ضرر است.
        if ( get_option( 'psy_quiz_phones_normalized_v38' ) !== '1' ) {
            $rows = $wpdb->get_results( "SELECT id, client_phone FROM {$wpdb->prefix}psy_invitations WHERE client_phone != ''" );
            foreach ( $rows as $row ) {
                $normalized = Psy_Quiz_Invitation_Manager::to_local_format( $row->client_phone );
                if ( $normalized !== '' && $normalized !== $row->client_phone ) {
                    $wpdb->update(
                        "{$wpdb->prefix}psy_invitations",
                        [ 'client_phone' => $normalized ],
                        [ 'id' => $row->id ],
                        [ '%s' ],
                        [ '%d' ]
                    );
                }
            }
            update_option( 'psy_quiz_phones_normalized_v38', '1' );
        }

        update_option( 'psy_quiz_db_version', PSY_DB_VERSION );

        // پاکسازی وضعیت 'processing' گیر کرده — اگر درخواستی وسط کار crash کرده باشد،
        // بعد از ۱ ساعت آن را به 'unused' برمی‌گردانیم تا مراجع دوباره بتواند آزمون دهد.
        // این کار هم در activation و هم به صورت scheduled اجرا می‌شود.
        self::cleanup_stuck_processing();

        // ثبت cronjob برای پاکسازی دوره‌ای وضعیت‌های گیر کرده
        if ( ! wp_next_scheduled( 'psy_quiz_cleanup_processing' ) ) {
            wp_schedule_event( time(), 'hourly', 'psy_quiz_cleanup_processing' );
        }

        // ثبت cronjob برای پاکسازی لاگ‌های قدیمی (روزانه)
        if ( ! wp_next_scheduled( 'psy_quiz_cleanup_logs' ) ) {
            wp_schedule_event( time(), 'daily', 'psy_quiz_cleanup_logs' );
        }

        // migration: اضافه کردن وضعیت 'revoked' — نیازی به ALTER TABLE ندارد چون
        // ستون status از نوع varchar است و هر مقداری را می‌پذیرد.
        // اما برای وضوح، ثبت می‌کنیم که v3.3 این وضعیت را معرفی کرده.

        // ثبت cronjob برای پاکسازی draft های قدیمی (روزانه — draft های بیش از ۳۰ روز)
        if ( ! wp_next_scheduled( 'psy_quiz_cleanup_drafts' ) ) {
            wp_schedule_event( time(), 'daily', 'psy_quiz_cleanup_drafts' );
        }

        // بررسی وجود mPDF برای قابلیت PDF
        $pdf_ok = Psy_Quiz_PDF_Export::is_available();
        update_option( 'psy_quiz_pdf_available', $pdf_ok ? '1' : '0' );
    }

    /**
     * پاکسازی دعوتنامه‌هایی که در وضعیت 'processing' گیر کرده‌اند.
     * اگر بیش از ۱ ساعت در این وضعیت مانده باشند، به 'unused' برمی‌گردند.
     */
    public static function cleanup_stuck_processing(): void {
        global $wpdb;
        // باگ C (قبلی): ردیف‌هایی با started_at = NULL هرگز توسط "started_at < ..."
        // گرفته نمی‌شوند چون NULL < ... برابر NULL است. → با شرط IS NULL رفع شده.
        //
        // باگ جدید که همین‌جا رفع شد: started_at با gmdate() (UTC) ذخیره می‌شود
        // (نگاه کنید به Psy_Quiz_Invitation_Manager::claim())، اما این کوئری
        // آن را با DATE_SUB(NOW(), INTERVAL 1 HOUR) مقایسه می‌کرد — NOW() در
        // MySQL از تایم‌زون سرور دیتابیس می‌آید، نه UTC و نه تایم‌زون وردپرس.
        // اگر تایم‌زون MySQL با UTC فرق داشته باشد (مثلاً روی هاست‌های ایرانی
        // که گاهی +۰۳:۳۰ تنظیم شده‌اند)، این مقایسه می‌تواند یک آزمونی که
        // مراجع همین الان در حال پاسخ‌دادن به آن است را اشتباهاً «گیرکرده»
        // تشخیص بدهد و ریست کند. حالا آستانه با gmdate در PHP (UTC) محاسبه و
        // به‌عنوان پارامتر prepared پاس داده می‌شود تا با started_at هم‌پایه باشد.
        $threshold = gmdate( 'Y-m-d H:i:s', time() - HOUR_IN_SECONDS );
        $wpdb->query( $wpdb->prepare(
            "UPDATE {$wpdb->prefix}psy_invitations
             SET status = 'unused'
             WHERE status = 'processing'
               AND (started_at IS NULL OR started_at < %s)",
            $threshold
        ) );
    }

    /**
     * پاکسازی draft های بیش از ۳۰ روز — cron روزانه
     */
    public static function cleanup_old_drafts(): void {
        global $wpdb;
        $wpdb->query(
            "DELETE FROM {$wpdb->prefix}psy_drafts
             WHERE saved_at < DATE_SUB(NOW(), INTERVAL 30 DAY)"
        );
    }
}
