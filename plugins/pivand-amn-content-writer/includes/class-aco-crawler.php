<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * ACO_Crawler — کرم محتوا
 *
 * هر شب پست‌های منتشرشده را بررسی می‌کند:
 *   ۱. امتیاز سئو زیر آستانه؟ → rewrite
 *   ۲. لینک داخلی کمتر از حداقل؟ → link_inject
 *   ۳. بیشتر از N ماه بدون ویرایش؟ → rewrite
 *   ۴. همه چیز خوب → فقط تاریخ بررسی آپدیت
 *
 * Cron Hook  : aco_crawler_scan   (روزانه ساعت ۰۳:۰۰)
 * Meta Keys  :
 *   _aco_last_crawled   : timestamp آخرین بررسی
 *   _aco_crawler_action : آخرین اقدام (rewrite|link_inject|none)
 */
class ACO_Crawler {

    const CRON_HOOK     = 'aco_crawler_scan';
    const CRON_INTERVAL = 'daily';
    // CRON_TIME_UTC حذف شد — dead code بود و هیچ‌جا استفاده نمی‌شد

    // ─────────────────────────────────────────────
    // Cron management
    // ─────────────────────────────────────────────

    public static function schedule_cron(): void {
        // اگه قبلاً زمان‌بندی شده، اول حذفش کن تا اگه ساعت تغییر کرده باشه، اعمال شه
        self::unschedule_cron();

        $run_hour = self::settings()['run_hour'] ?? 3;
        $next_run = self::next_run_timestamp( (int) $run_hour );
        wp_schedule_event( $next_run, self::CRON_INTERVAL, self::CRON_HOOK );
    }

    public static function unschedule_cron(): void {
        $timestamp = wp_next_scheduled( self::CRON_HOOK );
        if ( $timestamp ) {
            wp_unschedule_event( $timestamp, self::CRON_HOOK );
        }
    }

    /**
     * timestamp بعدی برای ساعت دلخواه ($hour) به وقت local سایت
     *
     * BUG-NEW-1 FIX: قبلاً فقط از get_option('timezone_string') استفاده می‌شد.
     * سایت‌هایی که timezone را با UTC offset (مثلاً UTC+3:30) تنظیم کرده‌اند،
     * مقدار timezone_string = '' دارند → cron به اشتباه در UTC 03:00 فایر می‌شد.
     * راه‌حل: wp_timezone() هر دو حالت timezone_string و gmt_offset را پوشش می‌دهد (WP 5.3+).
     */
    private static function next_run_timestamp( int $hour = 3 ): int {
        $hour = max( 0, min( 23, $hour ) );
        try {
            // wp_timezone() هم timezone_string و هم gmt_offset را پشتیبانی می‌کند
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
    // تنظیمات پیش‌فرض
    // ─────────────────────────────────────────────

    private static function settings(): array {
        $s = ACO_Settings::get( 'crawler' );
        if ( ! is_array( $s ) ) $s = [];
        return wp_parse_args( $s, [
            'enabled'         => false,      // فعال/غیرفعال
            'interval_days'   => 30,         // هر چند روز یک پست بررسی شود
            'seo_threshold'   => 60,         // امتیاز سئو زیر این → rewrite
            'min_links'       => 2,          // تعداد لینک داخلی کمتر از این → link_inject
            'stale_months'    => 6,          // ماه‌های بدون ویرایش → rewrite
            'posts_per_night' => 5,          // حداکثر پست در هر اجرا
            'run_hour'        => 3,          // ساعت اجرای شبانه (۰ تا ۲۳، به وقت local سایت)
        ] );
    }

    // ─────────────────────────────────────────────
    // اجرای اصلی (cron callback)
    // ─────────────────────────────────────────────

    public static function run( bool $force = false ): void {
        $cfg = self::settings();

        if ( ! $force && empty( $cfg['enabled'] ) ) {
            ACO_Logger::info( '🐛 کرم محتوا غیرفعال است — اجرا نشد.', 'crawler' );
            return;
        }

        // سقف بودجه توکن ماهانه (مشترک با بانک کلمات کلیدی) — اجرای دستی (force) از این چک عبور می‌کند
        if ( ! $force && ACO_Logger::is_over_monthly_budget() ) {
            ACO_Logger::warning( '🐛 کرم محتوا: سقف بودجه توکن ماهانه رد شده — اجرای امشب رد شد.', 'crawler' );
            return;
        }

        $posts_per_night = max( 1, (int) $cfg['posts_per_night'] );
        $interval_days   = max( 1, (int) $cfg['interval_days'] );
        // wp_date() زمان سایت را می‌دهد (date() از timezone سرور استفاده می‌کرد
        // و روی سرورهای غیر-UTC ساعت‌ها جابه‌جا می‌شد). ورودی time() خام UTC است.
        $cutoff          = wp_date( 'Y-m-d H:i:s', time() - $interval_days * DAY_IN_SECONDS );

        global $wpdb;

        // پست‌هایی که نوبتشان است:
        // هنوز بررسی نشده‌اند یا آخرین بررسیشان از interval_days روز پیش گذشته
        $post_ids = $wpdb->get_col( $wpdb->prepare( // phpcs:ignore
            "SELECT p.ID
             FROM {$wpdb->posts} p
             LEFT JOIN {$wpdb->postmeta} pm
               ON pm.post_id = p.ID AND pm.meta_key = '_aco_last_crawled'
             WHERE p.post_status = 'publish'
               AND p.post_type   = 'post'
               AND ( pm.meta_value IS NULL OR pm.meta_value < %s )
             ORDER BY COALESCE( pm.meta_value, '0000-00-00' ) ASC
             LIMIT %d",
            $cutoff,
            $posts_per_night
        ) );

        if ( empty( $post_ids ) ) {
            ACO_Logger::info( '🐛 کرم: هیچ پستی برای بررسی در این نوبت نبود.', 'crawler' );
            return;
        }

        $summary = [
            'scanned'     => 0,
            'rewrite'     => 0,
            'link_inject' => 0,
            'none'        => 0,
            'rows'        => [],
        ];

        foreach ( $post_ids as $post_id ) {
            $post_id = (int) $post_id;
            $result  = self::audit_post( $post_id, $cfg );
            $summary['scanned']++;
            $summary[ $result['action'] ]++;
            $summary['rows'][] = $result;
        }

        // ذخیره گزارش برای نمایش در پنل
        $report = [
            'date'    => current_time( 'mysql' ),
            'summary' => $summary,
        ];
        $history   = (array) get_option( 'aco_crawler_reports', [] );
        array_unshift( $history, $report );
        update_option( 'aco_crawler_reports', array_slice( $history, 0, 30 ) ); // ۳۰ روز نگه دار

        // آمار تجمعی
        $totals = (array) get_option( 'aco_crawler_totals', [ 'scanned' => 0, 'rewrite' => 0, 'link_inject' => 0, 'none' => 0 ] );
        foreach ( [ 'scanned', 'rewrite', 'link_inject', 'none' ] as $k ) {
            $totals[ $k ] = ( $totals[ $k ] ?? 0 ) + ( $summary[ $k ] ?? 0 );
        }
        update_option( 'aco_crawler_totals', $totals );

        ACO_Logger::success(
            sprintf(
                '🐛 کرم: %d بررسی | %d بازنویسی | %d لینک | %d بدون تغییر',
                $summary['scanned'], $summary['rewrite'], $summary['link_inject'], $summary['none']
            ),
            'crawler'
        );
    }

    // ─────────────────────────────────────────────
    // بررسی یک پست و تصمیم‌گیری
    // ─────────────────────────────────────────────

    private static function audit_post( int $post_id, array $cfg ): array {
        $post = get_post( $post_id );
        if ( ! $post ) {
            return [ 'post_id' => $post_id, 'title' => '—', 'action' => 'none', 'seo_before' => 0, 'seo_after' => 0, 'error' => 'پست یافت نشد' ];
        }

        $seo_before = (int) get_post_meta( $post_id, '_aco_seo_score', true );
        $action     = 'none';
        $error      = '';

        // ── چک ۱: امتیاز سئو
        $needs_rewrite_seo   = $seo_before > 0 && $seo_before < (int) $cfg['seo_threshold'];

        // ── چک ۲: تازگی محتوا
        $stale_months        = max( 1, (int) $cfg['stale_months'] );
        // BUG-NEW-3 FIX: post_modified یک رشته WP local time است اما strtotime() آن را
        // با timezone سرور PHP parse می‌کند — اگر server tz != WP tz، مقایسه اشتباه می‌شود.
        // راه‌حل: از post_modified_gmt (UTC واقعی) + time() (UTC واقعی) استفاده می‌کنیم
        // تا مقایسه همیشه در یک مختصات زمانی باشد، صرف‌نظر از timezone سرور یا وردپرس.
        $modified_timestamp  = strtotime( $post->post_modified_gmt . ' UTC' );
        $stale_cutoff        = time() - $stale_months * 30 * DAY_IN_SECONDS;
        $needs_rewrite_stale = $modified_timestamp < $stale_cutoff;

        // ── چک ۳: لینک داخلی
        $min_links         = max( 0, (int) $cfg['min_links'] );
        $internal_count    = self::count_internal_links( $post->post_content );
        $needs_link_inject = $internal_count < $min_links;

        // ── تصمیم
        if ( $needs_rewrite_seo || $needs_rewrite_stale ) {
            $action = 'rewrite';
        } elseif ( $needs_link_inject ) {
            $action = 'link_inject';
        }

        $seo_after = $seo_before;

        if ( $action === 'rewrite' ) {
            // به صف اضافه کن با job_type=audit_update
            $keyword = get_post_meta( $post_id, '_yoast_wpseo_focuskw', true )
                    ?: get_post_meta( $post_id, 'rank_math_focus_keyword', true )
                    ?: '';

            global $wpdb;
            // BUG-CRAWL-3 FIX: از ACO_Queue::TABLE_SUFFIX به‌جای hardcode استفاده می‌شود
            $table = $wpdb->prefix . ACO_Queue::TABLE_SUFFIX;

            // اگر قبلاً در صف در انتظار است، تکرار نکن
            $exists = $wpdb->get_var( $wpdb->prepare( // phpcs:ignore
                "SELECT id FROM {$table} WHERE rewrite_post_id = %d AND status = 'pending' AND job_type = 'audit_update' LIMIT 1",
                $post_id
            ) );

            if ( ! $exists ) {
                $wpdb->insert( $table, [ // phpcs:ignore
                    'keyword'         => $keyword ?: $post->post_title,
                    'topic'           => $post->post_title,
                    'status'          => 'pending',
                    'post_status'     => $post->post_status,
                    'job_type'        => 'audit_update',
                    'rewrite_post_id' => $post_id,
                    'author_id'       => (int) $post->post_author,
                    'created_at'      => current_time( 'mysql' ),
                ] );
            }

        } elseif ( $action === 'link_inject' ) {
            $result = self::inject_links( $post_id, $post, $min_links );
            if ( ! $result['success'] ) {
                $error  = $result['error'] ?? 'خطا در inject لینک';
                $action = 'none'; // اگه inject شکست خورد → none ثبت می‌کنیم
            } elseif ( isset( $result['new_score'] ) ) {
                // FIX: گزارش شب باید امتیاز واقعیِ بعد از inject را نشان دهد، نه seo_before
                $seo_after = (int) $result['new_score'];
            }
        }

        // آپدیت meta بررسی
        update_post_meta( $post_id, '_aco_last_crawled', current_time( 'mysql' ) );
        update_post_meta( $post_id, '_aco_crawler_action', $action );

        return [
            'post_id'    => $post_id,
            'title'      => $post->post_title,
            'action'     => $action,
            'seo_before' => $seo_before,
            'seo_after'  => $seo_after,
            'reason'     => $needs_rewrite_seo
                                ? "سئو {$seo_before} < {$cfg['seo_threshold']}"
                                : ( $needs_rewrite_stale
                                    ? "بیش از {$stale_months} ماه بدون ویرایش"
                                    : ( $needs_link_inject
                                        ? "{$internal_count} لینک داخلی (حداقل {$min_links})"
                                        : 'همه چیز خوب بود' ) ),
            'error'      => $error,
        ];
    }

    // ─────────────────────────────────────────────
    // شمارش لینک‌های داخلی
    // ─────────────────────────────────────────────

    private static function count_internal_links( string $content ): int {
        if ( empty( $content ) ) return 0;
        $home = rtrim( home_url(), '/' );
        preg_match_all( '/<a\s[^>]*href=["\']([^"\']+)["\'][^>]*>/i', $content, $matches );
        $count = 0;
        foreach ( $matches[1] as $href ) {
            if ( str_starts_with_compat( $href, $home ) || str_starts_with_compat( $href, '/' ) ) {
                $count++;
            }
        }
        return $count;
    }

    // ─────────────────────────────────────────────
    // inject لینک داخلی — بدون API call
    // ─────────────────────────────────────────────

    private static function inject_links( int $post_id, WP_Post $post, int $min_links ): array {
        $keyword = get_post_meta( $post_id, '_yoast_wpseo_focuskw', true )
                ?: get_post_meta( $post_id, 'rank_math_focus_keyword', true )
                ?: $post->post_title;

        $suggestions = ACO_SEO::suggest_internal_links( $keyword, $post->post_content, $post_id, 5 );

        if ( empty( $suggestions ) ) {
            return [ 'success' => false, 'error' => 'هیچ پست مرتبطی برای لینک‌دهی پیدا نشد.' ];
        }

        $current_count = self::count_internal_links( $post->post_content );
        $needed        = max( 0, $min_links - $current_count );
        if ( $needed <= 0 ) {
            return [ 'success' => true ]; // چیزی نیاز نیست
        }

        $to_inject   = array_slice( $suggestions, 0, $needed );
        $new_content = $post->post_content;

        foreach ( $to_inject as $link ) {
            $url        = get_permalink( $link['post_id'] );
            $title      = esc_attr( $link['title'] );
            $anchor     = esc_html( $link['title'] );
            $link_html  = '<a href="' . esc_url( $url ) . '" title="' . $title . '">' . $anchor . '</a>';

            // پیدا کردن اولین پاراگراف که anchor text طبیعی داخلش نیست
            $new_content = self::inject_link_in_paragraph( $new_content, $link_html, $link['title'] );
        }

        // ذخیره بدون تغییر تاریخ
        $updated = wp_update_post( [
            'ID'            => $post_id,
            'post_content'  => $new_content,
            'post_date'     => $post->post_date,
            'post_date_gmt' => $post->post_date_gmt,
        ], true );

        if ( is_wp_error( $updated ) ) {
            return [ 'success' => false, 'error' => $updated->get_error_message() ];
        }

        // FIX: بعد از inject لینک، امتیاز سئو را دوباره محاسبه و ذخیره کن.
        // بدون این، دفعه‌ی بعد که audit_post() این پست را چک می‌کند، _aco_seo_score
        // همچنان مقدار قدیمی (قبل از لینک) است — یعنی تصمیم rewrite/link_inject
        // بعدی بر اساس داده‌ی کهنه گرفته می‌شود. همچنین پنل تحلیل بلادرنگ
        // (ACO_Live_Analyzer) مقدار اولیه‌اش را از همین متاکی می‌خواند.
        $meta_description = get_post_meta( $post_id, '_yoast_wpseo_metadesc', true )
                          ?: get_post_meta( $post_id, 'rank_math_description', true )
                          ?: '';
        $seo_audit = ACO_SEO::run_audit( $post->post_title, $new_content, $meta_description, $keyword, [ 'post_id' => $post_id ] );
        update_post_meta( $post_id, '_aco_seo_score', $seo_audit['score'] );
        update_post_meta( $post_id, '_aco_seo_checks', wp_json_encode( $seo_audit['checks'] ) );
        update_post_meta( $post_id, '_aco_readability_score', $seo_audit['readability']['score'] ?? 0 );

        ACO_Logger::info(
            "🔗 لینک inject شد — پست #{$post_id} | {$needed} لینک اضافه شد",
            'crawler'
        );

        return [ 'success' => true, 'injected' => $needed, 'new_score' => $seo_audit['score'] ];
    }

    /**
     * inject یک لینک در انتهای اولین پاراگراف مناسب
     * "مناسب" یعنی: <p> که به اندازه کافی بلند است و لینک از قبل در آن نیست
     */
    private static function inject_link_in_paragraph( string $content, string $link_html, string $anchor_text ): string {
        // پاراگراف‌ها را پیدا کن
        if ( ! preg_match_all( '/<p[^>]*>(.+?)<\/p>/is', $content, $matches, PREG_OFFSET_CAPTURE ) ) {
            // اگه هیچ پاراگرافی پیدا نشد، لینک رو در انتها اضافه کن
            return $content . "\n<p>" . $link_html . '</p>';
        }

        foreach ( $matches[0] as $i => $match ) {
            $p_full   = $match[0];
            $p_offset = $match[1];
            $p_inner  = $matches[1][ $i ][0];

            // پاراگراف حداقل ۱۵۰ کاراکتر متن باشد
            if ( mb_strlen( wp_strip_all_tags( $p_inner ) ) < 150 ) continue;

            // لینک از قبل نباشد
            if ( mb_strpos( $p_inner, $link_html ) !== false ) continue;
            if ( mb_strpos( $p_inner, $anchor_text ) !== false ) continue;

            // inject لینک در انتهای این پاراگراف
            $new_p = str_replace( '</p>', ' ' . $link_html . '</p>', $p_full );
            // IMPORTANT: offset و length از PREG_OFFSET_CAPTURE بایتی هستند (نه کاراکتری)
            // substr_replace هم بایتی کار می‌کند — سازگار است
            return substr_replace( $content, $new_p, $p_offset, strlen( $p_full ) );
        }

        // هیچ پاراگراف مناسبی نبود → در انتها اضافه کن
        return $content . "\n<p>" . $link_html . '</p>';
    }

    // ─────────────────────────────────────────────
    // گزارش کرم برای نمایش در پنل
    // ─────────────────────────────────────────────

    public static function get_reports(): array {
        return (array) get_option( 'aco_crawler_reports', [] );
    }

    public static function get_totals(): array {
        return wp_parse_args(
            (array) get_option( 'aco_crawler_totals', [] ),
            [ 'scanned' => 0, 'rewrite' => 0, 'link_inject' => 0, 'none' => 0 ]
        );
    }

    /**
     * استخراج رویدادهای موفقِ لینک‌سازی خودکار (link_inject) از تاریخچه‌ی گزارش‌ها،
     * برای نمایش در گزارش یکپارچه‌ی به‌روزرسانی‌ها (در کنار کارهای تمام‌شده‌ی صف).
     * برخلاف اقدام 'rewrite' که فقط job در صف می‌سازد و نتیجه‌اش بعداً معلوم می‌شود،
     * 'link_inject' همان لحظه توسط خود کرم انجام و نهایی می‌شود — پس همیشه «قطعی» است.
     */
    public static function get_link_inject_events(): array {
        $events = [];
        foreach ( self::get_reports() as $report ) {
            $date = $report['date'] ?? '';
            $rows = $report['summary']['rows'] ?? [];
            foreach ( $rows as $row ) {
                if ( ( $row['action'] ?? '' ) === 'link_inject' && empty( $row['error'] ) ) {
                    $events[] = [
                        'date'       => $date,
                        'post_id'    => (int) ( $row['post_id'] ?? 0 ),
                        'title'      => $row['title'] ?? '',
                        'seo_before' => $row['seo_before'] ?? null,
                        'seo_after'  => $row['seo_after'] ?? null,
                    ];
                }
            }
        }
        return $events;
    }

    public static function reset_reports(): void {
        delete_option( 'aco_crawler_reports' );
        delete_option( 'aco_crawler_totals' );
    }
}

// PHP 7.4 compat: str_starts_with از PHP 8.0 است
if ( ! function_exists( 'str_starts_with_compat' ) ) {
    function str_starts_with_compat( string $haystack, string $needle ): bool {
        return $needle === '' || strncmp( $haystack, $needle, strlen( $needle ) ) === 0;
    }
}
