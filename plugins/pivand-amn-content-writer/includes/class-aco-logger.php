<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * ACO_Logger
 * ذخیره لاگ خطاها و رویدادها در جدول اختصاصی
 * جدول: {prefix}aco_logs
 */
class ACO_Logger {

    const TABLE_SUFFIX = 'aco_logs';
    const MAX_ROWS     = 500; // حداکثر تعداد ردیف در جدول

    // ─────────────────────────────────────────────
    // ساخت جدول (در activation hook فراخوانی می‌شود)
    // ─────────────────────────────────────────────
    public static function create_table(): void {
        global $wpdb;
        $table   = $wpdb->prefix . self::TABLE_SUFFIX;
        $charset = $wpdb->get_charset_collate();

        // بدون IF NOT EXISTS و با دو فاصله بعد از PRIMARY KEY — همان دلایل
        // ذکرشده در ACO_Queue::create_table().
        $sql = "CREATE TABLE {$table} (
            id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            level       VARCHAR(10)  NOT NULL DEFAULT 'info',
            context     VARCHAR(100) NOT NULL DEFAULT '',
            message     TEXT         NOT NULL,
            tokens      INT UNSIGNED NOT NULL DEFAULT 0,
            data        LONGTEXT     NULL,
            user_id     BIGINT UNSIGNED NOT NULL DEFAULT 0,
            created_at  DATETIME     NOT NULL,
            PRIMARY KEY  (id),
            KEY level (level),
            KEY created_at (created_at)
        ) {$charset};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }

    // ─────────────────────────────────────────────
    // حذف جدول (در uninstall)
    // ─────────────────────────────────────────────
    public static function drop_table(): void {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE_SUFFIX;
        $wpdb->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore
    }

    // ─────────────────────────────────────────────
    // ثبت لاگ
    // ─────────────────────────────────────────────
    public static function log( string $message, string $level = 'info', string $context = '', array $data = [], int $tokens = 0 ): void {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE_SUFFIX;

        $wpdb->insert(
            $table,
            [
                'level'      => in_array( $level, [ 'info', 'warning', 'error', 'success' ], true ) ? $level : 'info',
                'context'    => sanitize_text_field( mb_substr( $context, 0, 100 ) ),
                'message'    => $message,
                'tokens'     => max( 0, $tokens ),
                'data'       => ! empty( $data ) ? wp_json_encode( $data, JSON_UNESCAPED_UNICODE ) : null,
                'user_id'    => get_current_user_id(),
                'created_at' => current_time( 'mysql' ),
            ],
            [ '%s', '%s', '%s', '%d', '%s', '%d', '%s' ]
        );

        // پاکسازی خودکار وقتی از حد مجاز گذشت
        self::maybe_prune();
    }

    public static function info( string $message, string $context = '', array $data = [], int $tokens = 0 ): void {
        self::log( $message, 'info', $context, $data, $tokens );
    }

    public static function error( string $message, string $context = '', array $data = [], int $tokens = 0 ): void {
        self::log( $message, 'error', $context, $data, $tokens );
    }

    public static function success( string $message, string $context = '', array $data = [], int $tokens = 0 ): void {
        self::log( $message, 'success', $context, $data, $tokens );
    }

    public static function warning( string $message, string $context = '', array $data = [], int $tokens = 0 ): void {
        self::log( $message, 'warning', $context, $data, $tokens );
    }

    // ─────────────────────────────────────────────
    // خواندن لاگ‌ها با صفحه‌بندی و فیلتر
    // ─────────────────────────────────────────────
    public static function get_logs( array $args = [] ): array {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE_SUFFIX;

        $defaults = [
            'level'    => '',
            'context'  => '',
            'per_page' => 50,
            'paged'    => 1,
        ];
        $args = wp_parse_args( $args, $defaults );

        $where  = [ '1=1' ];
        $params = [];

        if ( $args['level'] ) {
            $where[]  = 'level = %s';
            $params[] = $args['level'];
        }
        if ( $args['context'] ) {
            $where[]  = 'context = %s';
            $params[] = $args['context'];
        }

        $where_sql = implode( ' AND ', $where );
        $offset    = ( max( 1, (int) $args['paged'] ) - 1 ) * (int) $args['per_page'];
        $limit     = (int) $args['per_page'];

        // total count
        $count_sql = "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}";
        $total     = $params
            ? (int) $wpdb->get_var( $wpdb->prepare( $count_sql, $params ) ) // phpcs:ignore
            : (int) $wpdb->get_var( $count_sql ); // phpcs:ignore

        // rows
        $select_sql = "SELECT * FROM {$table} WHERE {$where_sql} ORDER BY id DESC LIMIT %d OFFSET %d";
        $all_params = array_merge( $params, [ $limit, $offset ] );
        $rows       = $wpdb->get_results( $wpdb->prepare( $select_sql, $all_params ) ); // phpcs:ignore

        return [
            'rows'  => $rows ?: [],
            'total' => $total,
            'pages' => $limit > 0 ? (int) ceil( $total / $limit ) : 1,
        ];
    }

    // ─────────────────────────────────────────────
    // پاکسازی ردیف‌های قدیمی
    // ─────────────────────────────────────────────
    public static function maybe_prune(): void {
        // PERF-01 FIX: throttle با transient — هر 50 insert یکبار COUNT می‌زنیم
        // بدون throttle: هر log call یک COUNT(*) اضافه می‌زند (در pipeline 10+ بار)
        $prune_key = 'aco_log_prune_counter';
        $counter   = (int) get_transient( $prune_key );
        $counter++;
        if ( $counter < 50 ) {
            set_transient( $prune_key, $counter, HOUR_IN_SECONDS );
            return;
        }
        delete_transient( $prune_key ); // reset counter

        global $wpdb;
        $table = $wpdb->prefix . self::TABLE_SUFFIX;

        $count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ); // phpcs:ignore
        if ( $count <= self::MAX_ROWS ) return;

        $delete = $count - self::MAX_ROWS;
        $wpdb->query( $wpdb->prepare( // phpcs:ignore
            "DELETE FROM {$table} ORDER BY id ASC LIMIT %d",
            $delete
        ) );
    }

    // ─────────────────────────────────────────────
    // آمار مصرف توکن
    // ─────────────────────────────────────────────
    public static function get_token_stats(): array {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE_SUFFIX;

        // BUG-LOG-1 FIX: current_time('timestamp') از WP 5.3 deprecated است.
        // BUG-LOG-2,3 FIX: ترکیب gmdate() + current_time('timestamp') با timezone وردپرس ناسازگار بود.
        // راه‌حل: current_time('mysql') برای local WP datetime — سازگار با ستون created_at
        $now_mysql = current_time( 'mysql' );
        $today     = substr( $now_mysql, 0, 10 ); // YYYY-MM-DD
        $month     = substr( $now_mysql, 0, 7 );  // YYYY-MM

        // BUG-LOG-4 FIX: wp_date() با time() خام UTC، زمان سایت را می‌دهد.
        // قبلاً date() با current_time('timestamp') روی سرورهای غیر-UTC ساعت‌ها را جابه‌جا می‌کرد.
        $seven_days_ago = wp_date( 'Y-m-d H:i:s', time() - 7 * DAY_IN_SECONDS );

        $total_all   = (int) $wpdb->get_var( "SELECT SUM(tokens) FROM {$table}" ); // phpcs:ignore
        $total_today = (int) $wpdb->get_var( $wpdb->prepare( // phpcs:ignore
            "SELECT SUM(tokens) FROM {$table} WHERE DATE(created_at) = %s",
            $today
        ) );
        $total_month = (int) $wpdb->get_var( $wpdb->prepare( // phpcs:ignore
            "SELECT SUM(tokens) FROM {$table} WHERE DATE_FORMAT(created_at, '%%Y-%%m') = %s",
            $month
        ) );
        // BUG-LOG-3 FIX: seven_days_ago حالا با timezone وردپرس محاسبه می‌شود
        $daily_raw = $wpdb->get_results( $wpdb->prepare( // phpcs:ignore
            "SELECT DATE(created_at) as day, SUM(tokens) as tokens
             FROM {$table}
             WHERE created_at >= %s
             GROUP BY DATE(created_at)
             ORDER BY day ASC",
            $seven_days_ago
        ) );

        $daily_map = [];
        foreach ( $daily_raw ?: [] as $row ) {
            $daily_map[ $row->day ] = (int) $row->tokens;
        }
        $daily = [];
        $now_ts = time();
        for ( $i = 6; $i >= 0; $i-- ) {
            $day_str = wp_date( 'Y-m-d', $now_ts - $i * DAY_IN_SECONDS );
            $daily[] = (object) [
                'day'    => $day_str,
                'tokens' => $daily_map[ $day_str ] ?? 0,
            ];
        }

        return [
            'total_all'   => $total_all,
            'total_today' => $total_today,
            'total_month' => $total_month,
            'daily'       => $daily,
        ];
    }

    public static function clear_all(): void {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE_SUFFIX;
        $wpdb->query( "TRUNCATE TABLE {$table}" ); // phpcs:ignore
    }

    // ─────────────────────────────────────────────
    // بررسی سقف بودجه توکن ماهانه — برای اجرای خودکار (کرم، بانک کلمات کلیدی)
    // ۰ یا خالی = بدون محدودیت
    // ─────────────────────────────────────────────
    public static function is_over_monthly_budget(): bool {
        $budget = (int) ACO_Settings::get( 'monthly_token_budget' );
        if ( $budget <= 0 ) {
            return false;
        }
        $stats = self::get_token_stats();
        return (int) $stats['total_month'] >= $budget;
    }
}
