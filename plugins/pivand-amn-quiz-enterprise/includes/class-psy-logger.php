<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Psy_Logger — سیستم لاگ اختصاصی پلاگین
 *
 * جدول: psy_logs
 * نگهداری: ۳۰ روز (auto-cleanup با cron)
 * سطوح: error, warning, info (info فقط با WP_DEBUG)
 *
 * استفاده:
 *   psy_log( 'error',   'rest_handler', 'insert failed', [ 'db_error' => $wpdb->last_error ] );
 *   psy_log( 'warning', 'invitation',   'email skipped — therapist not set' );
 *   psy_log( 'info',    'scoring',      'AUDIT scored', [ 'total' => 12 ] );
 */
class Psy_Logger {

    const TABLE        = 'psy_logs';
    const RETENTION    = 30; // روز

    // ── ثبت لاگ ───────────────────────────────────────────────────────────
    public static function log( string $level, string $context, string $message, array $data = [] ): void {
        global $wpdb;

        // #L1 fix: اعتبارسنجی سطح اول انجام می‌شود، سپس فیلتر info
        // ترتیب قبلی: چک info → چک validity — اگر level نامعتبر بود و WP_DEBUG=false، سایلنت drop می‌شد
        if ( ! in_array( $level, [ 'error', 'warning', 'info' ], true ) ) $level = 'info';

        // info فقط وقتی WP_DEBUG فعال است
        if ( $level === 'info' && ! ( defined('WP_DEBUG') && WP_DEBUG ) ) return;

        $wpdb->insert(
            $wpdb->prefix . self::TABLE,
            [
                'level'      => $level,
                'context'    => substr( $context, 0, 80 ),
                'message'    => substr( $message, 0, 500 ),
                'data'       => empty( $data ) ? null : wp_json_encode( $data, JSON_UNESCAPED_UNICODE ),
                'created_at' => current_time( 'mysql' ), // timezone وردپرس
            ],
            [ '%s', '%s', '%s', '%s', '%s' ]
        );
    }

    // ── خواندن لاگ‌ها با فیلتر ────────────────────────────────────────────
    public static function get_logs( array $args = [] ): array {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE;

        $level   = sanitize_key( $args['level']   ?? '' );
        $context = sanitize_text_field( $args['context'] ?? '' );
        $limit   = max( 1, min( 500, (int)( $args['limit'] ?? 100 ) ) );

        $where  = [];
        $params = [];

        if ( $level )   { $where[] = 'level = %s';              $params[] = $level; }
        if ( $context ) { $where[] = 'context LIKE %s';         $params[] = '%' . $wpdb->esc_like( $context ) . '%'; }

        $sql = "SELECT * FROM {$table}";
        if ( $where ) $sql .= ' WHERE ' . implode( ' AND ', $where );
        $sql .= ' ORDER BY id DESC LIMIT %d';
        $params[] = $limit;

        return $wpdb->get_results( $wpdb->prepare( $sql, ...$params ) ) ?: [];
    }

    // ── تعداد لاگ‌ها به تفکیک سطح ────────────────────────────────────────
    public static function counts(): array {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE;
        $rows  = $wpdb->get_results( "SELECT level, COUNT(*) as cnt FROM {$table} GROUP BY level" );
        $out   = [ 'error' => 0, 'warning' => 0, 'info' => 0, 'total' => 0 ];
        foreach ( $rows as $r ) {
            $out[ $r->level ] = (int) $r->cnt;
            $out['total']    += (int) $r->cnt;
        }
        return $out;
    }

    // ── پاک کردن همه لاگ‌ها ───────────────────────────────────────────────
    public static function clear_all(): int {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE;
        $wpdb->query( "DELETE FROM {$table}" );
        return (int) $wpdb->rows_affected;
    }

    // ── پاک کردن لاگ‌های قدیمی‌تر از RETENTION روز ───────────────────────
    public static function cleanup_old(): void {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE;
        // created_at با current_time('mysql') (تایم‌زون وردپرس) ذخیره می‌شود؛
        // آستانه هم باید از همان مبنا محاسبه شود، نه NOW() که تایم‌زون سرور
        // MySQL را ملاک قرار می‌دهد و ممکن است با تایم‌زون وردپرس فرق کند.
        $threshold = gmdate( 'Y-m-d H:i:s', current_time( 'timestamp' ) - self::RETENTION * DAY_IN_SECONDS );
        $wpdb->query( $wpdb->prepare(
            "DELETE FROM {$table} WHERE created_at < %s",
            $threshold
        ) );
    }

    // ── ساخت جدول (در activate) ──────────────────────────────────────────
    public static function create_table(): void {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE;
        $c     = $wpdb->get_charset_collate();
        $sql   = "CREATE TABLE {$table} (
            id         bigint(20)   NOT NULL AUTO_INCREMENT,
            level      varchar(10)  NOT NULL DEFAULT 'info',
            context    varchar(80)  NOT NULL DEFAULT '',
            message    varchar(500) NOT NULL DEFAULT '',
            data       longtext     DEFAULT NULL,
            created_at datetime     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY level (level),
            KEY context (context),
            KEY created_at (created_at)
        ) $c;";
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }
}

// ── تابع helper سراسری ────────────────────────────────────────────────────
if ( ! function_exists( 'psy_log' ) ) {
    function psy_log( string $level, string $context, string $message, array $data = [] ): void {
        Psy_Logger::log( $level, $context, $message, $data );
    }
}
