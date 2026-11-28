<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * ACO_Keyword_Bank
 *
 * مخزن کلمات کلیدی طولانی (long-tail) مرتبط با موضوع سایت.
 *
 * هر روز به‌صورت خودکار:
 *   ۱. با AI فهرست تازه‌ای از کلمات کلیدی پرترافیک/کم‌رقابت (long-tail) برای
 *      حوزه تخصصی سایت پیشنهاد می‌گیرد — بدون تکرار با کلمات قبلی مخزن یا
 *      عناوین مقالات موجود سایت.
 *   ۲. بهترین کلمات (بر اساس امتیاز پتانسیل ترافیک) را به صف تولید محتوای
 *      موجود (ACO_Queue) می‌سپارد تا طبق تنظیمات (پیش‌فرض: ۲ مقاله در روز)
 *      نوشته و منتشر شوند — کل خط لوله تحقیق+نوشتن+ذخیره+تصویر شاخص از
 *      همان مسیر ACO_Queue::process_next() انجام می‌شود، دوباره‌سازی نشده.
 *
 * جدول اختصاصی: {prefix}aco_keyword_bank
 * Cron Hook     : aco_keyword_bank_daily
 */
class ACO_Keyword_Bank {

    const TABLE_SUFFIX  = 'aco_keyword_bank';
    const CRON_HOOK     = 'aco_keyword_bank_daily';
    const CRON_INTERVAL = 'daily';

    // ─────────────────────────────────────────────
    // ساخت/حذف جدول
    // ─────────────────────────────────────────────
    public static function create_table(): void {
        global $wpdb;
        $table   = $wpdb->prefix . self::TABLE_SUFFIX;
        $charset = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table} (
            id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            keyword     VARCHAR(255) NOT NULL,
            topic       VARCHAR(500) NOT NULL,
            score       TINYINT UNSIGNED NOT NULL DEFAULT 50,
            status      VARCHAR(20)  NOT NULL DEFAULT 'pending',
            source      VARCHAR(10)  NOT NULL DEFAULT 'ai',
            post_id     BIGINT UNSIGNED NULL,
            clicks      INT UNSIGNED NULL,
            impressions INT UNSIGNED NULL,
            gsc_position DECIMAL(4,1) NULL,
            traffic_updated_at DATETIME NULL,
            created_at  DATETIME NOT NULL,
            queued_at   DATETIME NULL,
            PRIMARY KEY  (id),
            KEY status (status),
            KEY keyword (keyword(191))
        ) {$charset};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }

    public static function drop_table(): void {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE_SUFFIX;
        $wpdb->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore
    }

    // ─────────────────────────────────────────────
    // تنظیمات
    // ─────────────────────────────────────────────
    public static function settings(): array {
        $s = ACO_Settings::get( 'keyword_bank' );
        if ( ! is_array( $s ) ) $s = [];
        return wp_parse_args( $s, [
            'enabled'       => true,   // فعال/غیرفعال
            'daily_count'   => 2,      // چند مقاله در روز از بانک کلمات ساخته شود
            'refresh_count' => 15,     // هر بار بروزرسانی، چند کلمه جدید از AI درخواست شود
            'run_hour'      => 4,      // ساعت اجرای شبانه (متفاوت از کرم که ساعت ۳ است)
            'post_status'   => 'pending', // SAFETY: پیش‌فرض «در انتظار بازبینی» — سایت YMYL است
        ] );
    }

    // ─────────────────────────────────────────────
    // Cron management
    // ─────────────────────────────────────────────
    public static function schedule_cron(): void {
        self::unschedule_cron();
        $run_hour = self::settings()['run_hour'] ?? 4;
        $next_run = self::next_run_timestamp( (int) $run_hour );
        wp_schedule_event( $next_run, self::CRON_INTERVAL, self::CRON_HOOK );
    }

    public static function unschedule_cron(): void {
        $ts = wp_next_scheduled( self::CRON_HOOK );
        if ( $ts ) {
            wp_unschedule_event( $ts, self::CRON_HOOK );
        }
    }

    private static function next_run_timestamp( int $hour = 4 ): int {
        $hour = max( 0, min( 23, $hour ) );
        try {
            $tz = wp_timezone();
        } catch ( Exception $e ) {
            $tz = new DateTimeZone( 'UTC' );
        }
        $now  = new DateTime( 'now', $tz );
        $next = new DateTime( 'today ' . sprintf( '%02d:00', $hour ), $tz );
        if ( $now >= $next ) {
            $next->modify( '+1 day' );
        }
        return (int) $next->getTimestamp();
    }

    // ─────────────────────────────────────────────
    // اجرای اصلی روزانه (cron callback)
    // ─────────────────────────────────────────────
    public static function run_daily( bool $force = false ): array {
        $cfg = self::settings();

        if ( ! $force && empty( $cfg['enabled'] ) ) {
            ACO_Logger::info( '🔑 بانک کلمات کلیدی غیرفعال است — اجرا نشد.', 'keyword_bank' );
            return [ 'success' => true, 'skipped' => true ];
        }

        if ( function_exists( 'set_time_limit' ) ) {
            @set_time_limit( 0 );
        }

        // سقف بودجه توکن ماهانه — اگر عبور شده، نه بروزرسانی و نه ارسال به صف انجام نشود
        if ( ! $force && ACO_Logger::is_over_monthly_budget() ) {
            ACO_Logger::warning( '🔑 بانک کلمات کلیدی: سقف بودجه توکن ماهانه رد شده — اجرای امروز رد شد.', 'keyword_bank' );
            return [ 'success' => true, 'skipped' => true, 'reason' => 'budget' ];
        }

        // ۱. بروزرسانی روزانه فهرست کلمات کلیدی
        $added = self::refresh( (int) $cfg['refresh_count'] );
        ACO_Logger::info( "🔑 بانک کلمات کلیدی: {$added} کلمه جدید اضافه شد.", 'keyword_bank' );

        // ۲. ارسال بهترین کلمات به صف تولید محتوا
        $sent = self::dispatch_to_queue( (int) $cfg['daily_count'], (string) $cfg['post_status'] );
        ACO_Logger::success( "🔑 بانک کلمات کلیدی: {$sent} مقاله جدید به صف تولید اضافه شد.", 'keyword_bank' );

        // ۳. فیدبک ترافیک واقعی — فقط هفته‌ای یک‌بار (نه هر روز) تا API اضافه فراخوانی نشود
        $last_sync = (int) get_option( 'aco_kb_last_traffic_sync', 0 );
        if ( ( time() - $last_sync ) >= WEEK_IN_SECONDS ) {
            self::sync_traffic_feedback();
            update_option( 'aco_kb_last_traffic_sync', time() );
        }

        return [ 'success' => true, 'added' => $added, 'sent' => $sent ];
    }

    // ─────────────────────────────────────────────
    // بروزرسانی مخزن با AI (کشف کلمات کلیدی تازه)
    // ─────────────────────────────────────────────
    public static function refresh( int $count = 15 ): int {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE_SUFFIX;

        $niche     = ACO_Settings::get( 'niche' ) ?: 'تولید محتوای عمومی';
        $site_name = get_bloginfo( 'name' );

        // کلمات موجود در مخزن (برای پرهیز از تکرار)
        $existing = $wpdb->get_col( "SELECT keyword FROM {$table} ORDER BY id DESC LIMIT 200" ); // phpcs:ignore
        $existing_lower = array_map( 'mb_strtolower', $existing );

        $inserted = 0;

        // ── منبع ۱ (اولویت‌دار): کوئری‌های واقعی «فرصت» از Google Search Console ──
        // این‌ها حدس AI نیستند — گوگل همین الان impression واقعی برایشان به سایت می‌دهد
        // ولی رتبه ۱ تا ۵۰ دارند، یعنی با محتوای بهتر می‌شود رتبه گرفت.
        if ( ACO_Search_Console::is_configured() ) {
            $opportunities = ACO_Search_Console::get_opportunity_queries( 90, 10 );
            foreach ( $opportunities as $opp ) {
                $kw = sanitize_text_field( mb_substr( $opp['keyword'], 0, 255 ) );
                if ( $kw === '' || in_array( mb_strtolower( $kw ), $existing_lower, true ) ) continue;

                $wpdb->insert(
                    $table,
                    [
                        'keyword'     => $kw,
                        'topic'       => $kw, // عنوان دقیق را بعداً هوش مصنوعی حین نوشتن مقاله می‌سازد
                        'score'       => $opp['score'],
                        'status'      => 'pending',
                        'source'      => 'gsc',
                        'impressions' => $opp['impressions'],
                        'clicks'      => $opp['clicks'],
                        'gsc_position'=> $opp['position'],
                        'created_at'  => current_time( 'mysql' ),
                    ],
                    [ '%s', '%s', '%d', '%s', '%s', '%d', '%d', '%f', '%s' ]
                );
                $existing_lower[] = mb_strtolower( $kw );
                $inserted++;
            }
            ACO_Logger::info( "🔑 {$inserted} کلمه واقعی از Search Console (کوئری‌های فرصت) اضافه شد.", 'keyword_bank' );
        }

        // باقی سهم امروز را با ایده‌پردازی AI پر کن (حداقل ۵ تا، حتی اگر GSC بیشتر داده بود)
        $ai_count = max( 5, $count - $inserted );

        $existing_str = ! empty( $existing_lower ) ? implode( '، ', $existing ) : 'هیچ‌کدام';

        // عناوین مقالات موجود سایت (برای پرهیز از تکرار موضوعی)
        $recent_ids = get_posts( [
            'post_type'      => 'post',
            'post_status'    => 'publish',
            'posts_per_page' => 100,
            'orderby'        => 'date',
            'order'          => 'DESC',
            'fields'         => 'ids',
        ] );
        $titles     = array_map( 'get_the_title', $recent_ids );
        $titles_str = ! empty( $titles ) ? implode( '، ', $titles ) : 'هیچ‌کدام';

        $count = max( 5, min( 40, $ai_count ) );

        $system = "شما یک متخصص سئو و تحقیق کلمات کلیدی (keyword research) در حوزه {$niche} هستید. فقط JSON معتبر برگردانید، بدون هیچ توضیح اضافه قبل یا بعد از آن.";

        $user = "سایت: {$site_name}\nحوزه تخصصی: {$niche}\n\n"
              . "{$count} کلمه کلیدی طولانی (long-tail) واقعی و پرجست‌وجو در گوگل فارسی برای این حوزه پیدا کن که:\n"
              . "- بسیار مرتبط با حوزه تخصصی باشند و پتانسیل جذب ترافیک ارگانیک بالایی داشته باشند\n"
              . "- رقابت کمتری نسبت به کلمات کلیدی کوتاه و عمومی داشته باشند (عبارت‌های ۳ تا ۶ کلمه‌ای)\n"
              . "- یک سؤال یا نیاز مشخص کاربر را نشان دهند (مثلاً «علائم اضطراب اجتماعی در نوجوانان» به‌جای فقط «اضطراب»)\n\n"
              . "کلمات کلیدی که قبلاً استفاده شده‌اند — دوباره پیشنهاد نده:\n{$existing_str}\n\n"
              . "عناوین مقالات موجود سایت — موضوع تکراری نساز:\n{$titles_str}\n\n"
              . "برای هرکدام یک «عنوان پیشنهادی مقاله» و یک «امتیاز پتانسیل ترافیک» از ۱ تا ۱۰۰ هم بده.\n\n"
              . "پاسخ را دقیقاً در این فرمت JSON بده، بدون هیچ متن اضافه:\n"
              . "{\"keywords\": [{\"keyword\": \"...\", \"topic\": \"عنوان پیشنهادی مقاله\", \"score\": 85}]}";

        $router = new ACO_AI_Router();
        $result = $router->generate( $system, $user, ACO_AI_Router::TIMEOUT, ACO_AI_Router::MAX_TOKENS );

        if ( ! $result['success'] ) {
            ACO_Logger::error( 'بروزرسانی بانک کلمات کلیدی (بخش AI) ناموفق بود: ' . ( $result['error'] ?? '' ), 'keyword_bank' );
            return $inserted; // کلماتی که از GSC اضافه شدند (اگر بودند) از دست نروند
        }

        $clean  = self::extract_json( $result['text'] );
        $parsed = json_decode( $clean, true );

        if ( json_last_error() !== JSON_ERROR_NONE || empty( $parsed['keywords'] ) || ! is_array( $parsed['keywords'] ) ) {
            ACO_Logger::error( 'پاسخ AI برای بانک کلمات کلیدی قابل پارس نبود.', 'keyword_bank' );
            return $inserted;
        }

        foreach ( $parsed['keywords'] as $row ) {
            if ( ! is_array( $row ) || empty( $row['keyword'] ) ) continue;

            $keyword = sanitize_text_field( mb_substr( (string) $row['keyword'], 0, 255 ) );
            if ( $keyword === '' ) continue;
            if ( in_array( mb_strtolower( $keyword ), $existing_lower, true ) ) continue; // تکراری دقیق — رد شود
            if ( self::is_similar_to_existing( $keyword, $existing_lower ) ) continue;    // تکراری معنایی (هم‌پوشانی بالا) — رد شود

            $topic = ! empty( $row['topic'] ) ? sanitize_text_field( mb_substr( (string) $row['topic'], 0, 500 ) ) : $keyword;
            $score = isset( $row['score'] ) ? max( 1, min( 100, (int) $row['score'] ) ) : 50;

            $wpdb->insert(
                $table,
                [
                    'keyword'    => $keyword,
                    'topic'      => $topic,
                    'score'      => $score,
                    'status'     => 'pending',
                    'source'     => 'ai',
                    'created_at' => current_time( 'mysql' ),
                ],
                [ '%s', '%s', '%d', '%s', '%s', '%s' ]
            );

            $existing_lower[] = mb_strtolower( $keyword );
            $inserted++;
        }

        return $inserted;
    }

    private static function extract_json( string $text ): string {
        $text = trim( $text );
        $text = preg_replace( '/^```(?:json)?/i', '', $text );
        $text = preg_replace( '/```$/', '', $text );
        $start = strpos( $text, '{' );
        $end   = strrpos( $text, '}' );
        if ( $start !== false && $end !== false && $end > $start ) {
            return substr( $text, $start, $end - $start + 1 );
        }
        return trim( (string) $text );
    }

    /**
     * تشخیص تکراری معنایی ساده (بدون نیاز به embedding/AI اضافه):
     * اگر کلمه کلیدی جدید با یکی از کلمات موجود بیش از ۶۰٪ کلمات مشترک
     * داشته باشد (Jaccard روی کلمات)، تکراری معنایی در نظر گرفته می‌شود.
     * مثال: «اضطراب امتحان در نوجوانان» و «استرس امتحان نوجوانان» را می‌گیرد.
     */
    private static function is_similar_to_existing( string $keyword, array $existing_lower, float $threshold = 0.6 ): bool {
        $new_words = array_filter( preg_split( '/\s+/u', mb_strtolower( trim( $keyword ) ) ) );
        if ( count( $new_words ) < 2 ) {
            return false; // کلمات تک‌واژه‌ای را با این روش بررسی نمی‌کنیم — ریسک false-positive بالاست
        }
        $new_set = array_unique( $new_words );

        foreach ( $existing_lower as $existing_kw ) {
            $existing_words = array_filter( preg_split( '/\s+/u', trim( $existing_kw ) ) );
            if ( count( $existing_words ) < 2 ) continue;
            $existing_set = array_unique( $existing_words );

            $intersection = count( array_intersect( $new_set, $existing_set ) );
            $union        = count( array_unique( array_merge( $new_set, $existing_set ) ) );
            if ( $union === 0 ) continue;

            $similarity = $intersection / $union;
            if ( $similarity >= $threshold ) {
                return true;
            }
        }
        return false;
    }

    // ─────────────────────────────────────────────
    // ارسال بهترین کلمات به صف تولید محتوای موجود
    // ─────────────────────────────────────────────
    public static function dispatch_to_queue( int $count = 2, string $post_status = 'pending' ): int {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE_SUFFIX;

        if ( ! in_array( $post_status, [ 'draft', 'publish', 'pending', 'future' ], true ) ) {
            $post_status = 'pending';
        }

        $rows = $wpdb->get_results( $wpdb->prepare( // phpcs:ignore
            "SELECT * FROM {$table} WHERE status = 'pending' ORDER BY score DESC, id ASC LIMIT %d",
            max( 1, $count )
        ) );

        $sent = 0;
        foreach ( $rows as $row ) {
            $id = ACO_Queue::enqueue( $row->keyword, $row->topic, $post_status );
            if ( $id ) {
                $wpdb->update(
                    $table,
                    [ 'status' => 'queued', 'queued_at' => current_time( 'mysql' ) ],
                    [ 'id' => $row->id ],
                    [ '%s', '%s' ],
                    [ '%d' ]
                );
                $sent++;
            }
        }
        return $sent;
    }

    // ─────────────────────────────────────────────
    // برای نمایش در صفحه ادمین
    // ─────────────────────────────────────────────
    public static function get_recent( int $limit = 30 ): array {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE_SUFFIX;
        return $wpdb->get_results( $wpdb->prepare( // phpcs:ignore
            "SELECT * FROM {$table} ORDER BY id DESC LIMIT %d", $limit
        ) ) ?: [];
    }

    public static function get_counts(): array {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE_SUFFIX;
        $rows  = $wpdb->get_results( "SELECT status, COUNT(*) as cnt FROM {$table} GROUP BY status" ); // phpcs:ignore
        $out   = [ 'pending' => 0, 'queued' => 0 ];
        foreach ( $rows as $r ) {
            $out[ $r->status ] = (int) $r->cnt;
        }
        return $out;
    }

    /**
     * حذف یک کلمه کلیدی از مخزن — فقط اگر هنوز 'pending' باشد (چیزی که
     * قبلاً به صف تولید فرستاده شده dispatch نمی‌شود، برای جلوگیری از
     * ناهماهنگی با آیتم صف).
     */
    public static function delete_item( int $id ): bool {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE_SUFFIX;
        return (bool) $wpdb->delete(
            $table,
            [ 'id' => $id, 'status' => 'pending' ],
            [ '%d', '%s' ]
        );
    }

    /**
     * وقتی صف تولید محتوا یک job مربوط به این کلمه کلیدی را با موفقیت
     * تمام کند، اینجا صدا زده می‌شود (از ACO_Queue::process_next) تا
     * ردیف مخزن به 'used' + post_id آپدیت شود — پیش‌نیاز فیدبک ترافیک.
     */
    public static function mark_used_by_keyword( string $keyword, int $post_id ): void {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE_SUFFIX;

        $wpdb->update(
            $table,
            [ 'status' => 'used', 'post_id' => $post_id ],
            [ 'keyword' => $keyword, 'status' => 'queued' ],
            [ '%s', '%d' ],
            [ '%s', '%s' ]
        );
    }

    /**
     * فیدبک ترافیک واقعی: برای مقاله‌های 'used' که حداقل چند روز از انتشارشان
     * گذشته، آمار واقعی کلیک/impression را از Search Console می‌گیرد و در
     * همان ردیف مخزن ذخیره می‌کند. هفتگی صدا زده می‌شود (نه هر روز، چون
     * تغییرات ترافیک روزانه معنادار نیست و در مصرف API صرفه‌جویی می‌شود).
     */
    public static function sync_traffic_feedback(): int {
        if ( ! ACO_Search_Console::is_configured() ) {
            return 0;
        }

        global $wpdb;
        $table = $wpdb->prefix . self::TABLE_SUFFIX;

        // فقط پست‌هایی که حداقل ۷ روز از انتشارشان گذشته (تا GSC داده معنادار داشته باشد)
        $rows = $wpdb->get_results( $wpdb->prepare( // phpcs:ignore
            "SELECT * FROM {$table}
             WHERE status = 'used' AND post_id IS NOT NULL
             AND created_at <= %s
             ORDER BY ( traffic_updated_at IS NULL ) DESC, traffic_updated_at ASC
             LIMIT 20",
            wp_date( 'Y-m-d H:i:s', time() - ( 7 * DAY_IN_SECONDS ) )
        ) );

        $updated = 0;
        foreach ( $rows as $row ) {
            $permalink = get_permalink( (int) $row->post_id );
            if ( ! $permalink ) continue;

            $traffic = ACO_Search_Console::get_url_traffic( $permalink, 28 );
            if ( $traffic === null ) continue;

            $wpdb->update(
                $table,
                [
                    'clicks'             => $traffic['clicks'],
                    'impressions'        => $traffic['impressions'],
                    'gsc_position'       => $traffic['position'],
                    'traffic_updated_at' => current_time( 'mysql' ),
                ],
                [ 'id' => $row->id ],
                [ '%d', '%d', '%f', '%s' ],
                [ '%d' ]
            );
            $updated++;
        }

        if ( $updated > 0 ) {
            ACO_Logger::info( "🔑 فیدبک ترافیک {$updated} مقاله از Search Console بروزرسانی شد.", 'keyword_bank' );
        }

        return $updated;
    }
}
