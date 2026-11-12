<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * ACO_Queue
 * صف پردازش دسته‌جمعی با WP-Cron
 * جدول: {prefix}aco_queue
 *
 * وضعیت‌های ممکن: pending → processing → done | failed
 */
class ACO_Queue {

    const TABLE_SUFFIX  = 'aco_queue';
    const CRON_HOOK     = 'aco_process_queue';
    const CRON_INTERVAL = 'aco_every_minute';
    const LOCK_KEY      = 'aco_queue_processing';

    // ─────────────────────────────────────────────
    // ساخت جدول
    // ─────────────────────────────────────────────
    public static function create_table(): void {
        global $wpdb;
        $table   = $wpdb->prefix . self::TABLE_SUFFIX;
        $charset = $wpdb->get_charset_collate();

        // نکته‌ی حیاتی dbDelta: بدون "IF NOT EXISTS" (وگرنه dbDelta نمی‌تواند
        // در آپدیت‌های بعدی ستون جدید به جدول موجود اضافه کند) و دقیقاً دو
        // فاصله بعد از PRIMARY KEY — همان کلاس باگی که در pivand-amn-login
        // رفع شد.
        $sql = "CREATE TABLE {$table} (
            id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            keyword         VARCHAR(255) NOT NULL,
            topic           VARCHAR(500) NOT NULL,
            status          VARCHAR(20)  NOT NULL DEFAULT 'pending',
            post_status     VARCHAR(20)  NOT NULL DEFAULT 'draft',
            scheduled_at    DATETIME     NULL,
            started_at      DATETIME     NULL,
            finished_at     DATETIME     NULL,
            post_id         BIGINT UNSIGNED NULL,
            rewrite_post_id BIGINT UNSIGNED NULL,
            job_type        VARCHAR(20)  NOT NULL DEFAULT 'generate',
            seo_score       TINYINT UNSIGNED NULL,
            error_message   TEXT         NULL,
            author_id       BIGINT UNSIGNED NOT NULL DEFAULT 1,
            created_at      DATETIME     NOT NULL,
            PRIMARY KEY  (id),
            KEY status (status),
            KEY scheduled_at (scheduled_at),
            KEY job_type (job_type)
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
    // ثبت interval اختصاصی
    // ─────────────────────────────────────────────
    public static function register_cron_interval(): void {
        add_filter( 'cron_schedules', function( $schedules ) {
            if ( ! isset( $schedules[ self::CRON_INTERVAL ] ) ) {
                $schedules[ self::CRON_INTERVAL ] = [
                    'interval' => 60,
                    'display'  => 'هر یک دقیقه (ACO)',
                ];
            }
            return $schedules;
        } );
    }

    public static function schedule_cron(): void {
        if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
            wp_schedule_event( time(), self::CRON_INTERVAL, self::CRON_HOOK );
        }
    }

    public static function unschedule_cron(): void {
        $ts = wp_next_scheduled( self::CRON_HOOK );
        if ( $ts ) {
            wp_unschedule_event( $ts, self::CRON_HOOK );
        }
    }

    // ─────────────────────────────────────────────
    // اضافه کردن آیتم به صف
    // ─────────────────────────────────────────────
    public static function enqueue( string $keyword, string $topic, string $post_status = 'draft', ?string $scheduled_at = null, int $author_id = 0 ) /* : int|false (PHP 8.0+) */ {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE_SUFFIX;

        if ( ! $author_id ) {
            $author_id = absint( ACO_Settings::get( 'default_author' ) ) ?: 1;
        }

        $result = $wpdb->insert(
            $table,
            [
                'keyword'      => sanitize_text_field( mb_substr( $keyword, 0, 255 ) ),
                'topic'        => sanitize_text_field( mb_substr( $topic, 0, 500 ) ),
                'status'       => 'pending',
                'post_status'  => in_array( $post_status, [ 'draft', 'publish', 'pending', 'future' ], true ) ? $post_status : 'draft',
                'scheduled_at' => $scheduled_at,
                'author_id'    => $author_id,
                'created_at'   => current_time( 'mysql' ),
            ],
            [ '%s', '%s', '%s', '%s', '%s', '%d', '%s' ]
        );

        return $result ? (int) $wpdb->insert_id : false;
    }

    // ─────────────────────────────────────────────
    // افزودن بهبود مقاله به صف
    // ─────────────────────────────────────────────
    public static function enqueue_rewrite( int $post_id, int $author_id = 0 ) /* : int|false (PHP 8.0+) */ {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE_SUFFIX;

        $post = get_post( $post_id );
        if ( ! $post ) return false;

        if ( ! $author_id ) {
            $author_id = absint( ACO_Settings::get( 'default_author' ) ) ?: 1;
        }

        $result = $wpdb->insert(
            $table,
            [
                'keyword'         => '',
                'topic'           => sanitize_text_field( mb_substr( $post->post_title, 0, 500 ) ),
                'status'          => 'pending',
                'post_status'     => $post->post_status,
                'job_type'        => 'rewrite',
                'rewrite_post_id' => $post_id,
                'author_id'       => $author_id,
                'created_at'      => current_time( 'mysql' ),
            ],
            [ '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s' ]
        );

        return $result ? (int) $wpdb->insert_id : false;
    }

    // ─────────────────────────────────────────────
    // پردازش صف — توسط WP-Cron فراخوانی می‌شود
    // ─────────────────────────────────────────────
    public static function process_next(): void {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE_SUFFIX;

        // GET_LOCK اتمیک است — جلوگیری از race condition
        // FIX: timeout=0 یعنی اگر lock موجود باشد فوری برگرد (non-blocking)
        $locked = $wpdb->get_var( "SELECT GET_LOCK('aco_queue_lock', 0)" ); // phpcs:ignore
        if ( ! $locked ) return;

        // آیتم‌های گیرکرده در processing بیش از ۱۰ دقیقه را بازیابی کن
        // BUG-QUEUE-1 FIX: هر دو مقدار باید در timezone یکسان (WP local time) محاسبه شوند.
        // started_at با current_time('mysql') ذخیره می‌شود (WP local time).
        // stale_before باید هم local-time باشد — از current_time('timestamp') استفاده می‌کنیم
        // که WP local timestamp است، نه UTC.
        $stale_threshold = current_time( 'mysql' );
        $stale_before    = date( 'Y-m-d H:i:s', current_time( 'timestamp' ) - 10 * MINUTE_IN_SECONDS );
        $wpdb->query( $wpdb->prepare( // phpcs:ignore
            "UPDATE {$table} SET status='failed', finished_at=%s, error_message='زمان پردازش تمام شد (بازیابی خودکار)'
             WHERE status='processing' AND started_at <= %s",
            $stale_threshold,
            $stale_before
        ) );

        $item = null; // FIX: تعریف قبل از try برای دسترسی در finally

        try {
            $now  = current_time( 'mysql' );
            $item = $wpdb->get_row( $wpdb->prepare( // phpcs:ignore
                "SELECT * FROM {$table}
                 WHERE status = 'pending'
                   AND ( scheduled_at IS NULL OR scheduled_at <= %s )
                 ORDER BY id ASC
                 LIMIT 1",
                $now
            ) );

            if ( ! $item ) return;

            $wpdb->update(
                $table,
                [ 'status' => 'processing', 'started_at' => $now ],
                [ 'id' => $item->id ],
                [ '%s', '%s' ],
                [ '%d' ]
            );

            ACO_Logger::info( "شروع پردازش آیتم #{$item->id}: {$item->keyword}", 'queue' );

            if ( function_exists( 'set_time_limit' ) ) {
                @set_time_limit( 310 );
            }

            $gen = new ACO_Generator();

            // ── job_type: audit_update (از کرم محتوا) ─────────────
            if ( ( $item->job_type ?? 'generate' ) === 'audit_update' ) {
                $rewrite_id = (int) ( $item->rewrite_post_id ?? 0 );
                if ( ! $rewrite_id ) {
                    self::mark_failed( $item->id, 'شناسه پست برای audit_update مشخص نیست.' );
                    return;
                }
                // FIX: بازنویسی خودکار کرم بر اساس تنظیم review_mode یا مستقیم منتشر می‌شود
                // یا فقط به‌عنوان پیشنهاد در انتظار تأیید ادمین ذخیره می‌شود (پیش‌فرض: ذخیره)
                $crawler_cfg    = ACO_Settings::get( 'crawler' );
                $require_review = ! empty( $crawler_cfg['review_mode'] ?? true );

                $write = $gen->rewrite_post( $rewrite_id, $require_review );
                if ( ! $write['success'] ) {
                    $err = $write['error'] ?? 'خطا در بازنویسی توسط کرم';
                    if ( self::is_rate_limit_error( $err ) ) {
                        self::mark_rate_limited( $item->id, $err );
                    } else {
                        self::mark_failed( $item->id, $err );
                    }
                    return;
                }
                $score       = $write['seo_score'] ?? null;
                $update_data = [ 'status' => 'done', 'finished_at' => current_time( 'mysql' ), 'post_id' => $rewrite_id ];
                $update_fmt  = [ '%s', '%s', '%d' ];
                if ( $score !== null ) {
                    $update_data['seo_score'] = (int) $score;
                    $update_fmt[]              = '%d';
                    // فقط وقتی واقعاً منتشر شده (نه صرفاً پیشنهاد در انتظار) این meta را به‌روز کن
                    if ( empty( $write['pending_review'] ) ) {
                        update_post_meta( $rewrite_id, '_aco_crawler_seo_after', (int) $score );
                    }
                }
                $wpdb->update( $table, $update_data, [ 'id' => $item->id ], $update_fmt, [ '%d' ] );

                if ( ! empty( $write['pending_review'] ) ) {
                    ACO_Logger::success( "🐛 کرم: پیشنهاد بازنویسی پست #{$rewrite_id} آماده‌ی بازبینی است — آیتم #{$item->id}", 'queue', [], $write['tokens'] ?? 0 );
                } else {
                    ACO_Logger::success( "🐛 کرم: بازنویسی پست #{$rewrite_id} انجام شد — آیتم #{$item->id}", 'queue', [], $write['tokens'] ?? 0 );
                }
                return;
            }

            // ── job_type: rewrite ──────────────────────────────────
            if ( ( $item->job_type ?? 'generate' ) === 'rewrite' ) {
                $rewrite_id = (int) ( $item->rewrite_post_id ?? 0 );
                if ( ! $rewrite_id ) {
                    self::mark_failed( $item->id, 'شناسه پست برای بهبود مشخص نیست.' );
                    return;
                }
                $write = $gen->rewrite_post( $rewrite_id );
                if ( ! $write['success'] ) {
                    $err = $write['error'] ?? 'خطا در بهبود مقاله';
                    // خطای 429 (rate limit): آیتم به pending با تأخیر برمی‌گردد
                    if ( self::is_rate_limit_error( $err ) ) {
                        self::mark_rate_limited( $item->id, $err );
                    } else {
                        self::mark_failed( $item->id, $err );
                    }
                    return;
                }
                $score         = $write['seo_score'] ?? null;
                $update_data   = [ 'status' => 'done', 'finished_at' => current_time( 'mysql' ), 'post_id' => $rewrite_id ];
                $update_format = [ '%s', '%s', '%d' ];
                if ( $score !== null ) {
                    $update_data['seo_score'] = (int) $score;
                    $update_format[]           = '%d';
                }
                $wpdb->update( $table, $update_data, [ 'id' => $item->id ], $update_format, [ '%d' ] );
                ACO_Logger::success( "بهبود مقاله #{$rewrite_id} انجام شد — آیتم #{$item->id}", 'queue', [], $write['tokens'] ?? 0 );
                return;
            }

            // ── job_type: generate (پیش‌فرض) ──────────────────────
            $result = $gen->run_research_stage( $item->keyword, $item->topic );

            if ( ! $result['success'] ) {
                $err = $result['error'] ?? 'خطا در مرحله تحقیق';
                if ( self::is_rate_limit_error( $err ) ) {
                    self::mark_rate_limited( $item->id, $err );
                } else {
                    self::mark_failed( $item->id, $err );
                }
                return;
            }

            // FIX (DUP-1): قبلاً $result['duplicate'] محاسبه می‌شد ولی هرگز در صف خودکار
            // چک نمی‌شد — نتیجه: کرالر/صف بدون اطلاع کاربر یک پست تکراری برای عنوانی
            // که از قبل وجود داشت می‌ساخت (حتی اگر آن پست با Elementor ساخته شده بود).
            // رفتار جدید:
            //   - تطبیق دقیق (exact=true): به‌جای ساخت پست جدید، همان پست موجود rewrite می‌شود.
            //   - تطبیق فازی (exact=false): فقط skip می‌شود و کاربر باید دستی تصمیم بگیرد،
            //     چون ممکن است موضوع مشابه ولی واقعاً متفاوت باشد.
            $duplicate = $result['duplicate'] ?? null;
            if ( $duplicate ) {
                if ( ! empty( $duplicate['exact'] ) ) {
                    $dup_post_id = (int) $duplicate['id'];
                    ACO_Logger::warning(
                        "آیتم #{$item->id}: عنوان «{$item->topic}» دقیقاً با پست موجود #{$dup_post_id} یکسان است — "
                        . "به‌جای ساخت پست جدید، آن پست بازنویسی می‌شود.",
                        'queue'
                    );
                    $write = $gen->rewrite_post( $dup_post_id );
                    if ( ! $write['success'] ) {
                        $err = $write['error'] ?? 'خطا در بازنویسی پست تکراری';
                        if ( self::is_rate_limit_error( $err ) ) {
                            self::mark_rate_limited( $item->id, $err );
                        } else {
                            self::mark_failed( $item->id, $err );
                        }
                        return;
                    }
                    $score         = $write['seo_score'] ?? null;
                    $update_data   = [
                        'status'          => 'done',
                        'finished_at'     => current_time( 'mysql' ),
                        'post_id'         => $dup_post_id,
                        'rewrite_post_id' => $dup_post_id,
                        'job_type'        => 'rewrite',
                    ];
                    $update_format = [ '%s', '%s', '%d', '%d', '%s' ];
                    if ( $score !== null ) {
                        $update_data['seo_score'] = (int) $score;
                        $update_format[]           = '%d';
                    }
                    $wpdb->update( $table, $update_data, [ 'id' => $item->id ], $update_format, [ '%d' ] );
                    ACO_Logger::success(
                        "آیتم #{$item->id}: به‌جای ساخت پست تکراری، پست #{$dup_post_id} بازنویسی شد.",
                        'queue', [], $write['tokens'] ?? 0
                    );
                    return;
                } else {
                    self::mark_failed(
                        $item->id,
                        "عنوانی مشابه (نه دقیقاً یکسان) با پست #{$duplicate['id']} («{$duplicate['title']}») یافت شد — "
                        . "برای جلوگیری از تکرار احتمالی، این آیتم به‌صورت دستی نیاز به بررسی دارد."
                    );
                    return;
                }
            }

            $tax   = $result['taxonomy'] ?? [];
            $write = $gen->run_write_and_save_stage(
                $item->keyword,
                $item->topic,
                $result['headings'],
                $result['entities'],
                $result['secondary_keywords'] ?? [],
                $result['meta_description'] ?? '',
                $item->post_status,
                $result['tokens'] ?? 0,
                $tax
            );

            if ( ! $write['success'] ) {
                $err = $write['error'] ?? 'خطا در مرحله نوشتن';
                if ( self::is_rate_limit_error( $err ) ) {
                    self::mark_rate_limited( $item->id, $err );
                } else {
                    self::mark_failed( $item->id, $err );
                }
                return;
            }

            // اگر post_status=future بود، تاریخ انتشار را تنظیم کن
            if ( $item->post_status === 'future' && $item->scheduled_at ) {
                // BUG-QUEUE-2 FIX: is_wp_error بررسی می‌شود تا خطای زمان‌بندی در لاگ ثبت شود
                $scheduled_update = wp_update_post( [
                    'ID'            => $write['post_id'],
                    'post_status'   => 'future',
                    'post_date'     => $item->scheduled_at,
                    'post_date_gmt' => get_gmt_from_date( $item->scheduled_at ),
                ], true );
                if ( is_wp_error( $scheduled_update ) ) {
                    ACO_Logger::warning(
                        "زمان‌بندی future برای پست #{$write['post_id']} شکست خورد: " . $scheduled_update->get_error_message(),
                        'queue'
                    );
                }
            }

            // FIX: جدا کردن دو حالت — wpdb با '%d' و null به درستی کنار نمی‌آید
            $score = $write['seo_score'] ?? null;
            if ( $score !== null ) {
                $wpdb->update(
                    $table,
                    [
                        'status'      => 'done',
                        'finished_at' => current_time( 'mysql' ),
                        'post_id'     => $write['post_id'],
                        'seo_score'   => (int) $score,
                    ],
                    [ 'id' => $item->id ],
                    [ '%s', '%s', '%d', '%d' ],
                    [ '%d' ]
                );
            } else {
                $wpdb->update(
                    $table,
                    [
                        'status'      => 'done',
                        'finished_at' => current_time( 'mysql' ),
                        'post_id'     => $write['post_id'],
                    ],
                    [ 'id' => $item->id ],
                    [ '%s', '%s', '%d' ],
                    [ '%d' ]
                );
            }

            ACO_Logger::success( "آیتم #{$item->id} با موفقیت پردازش شد — پست #{$write['post_id']}", 'queue', [
                'seo_score' => $write['seo_score'] ?? 0,
            ] );

            // اگر این job از بانک کلمات کلیدی آمده (کلمه‌ی 'queued' با همین کلمه‌ی کلیدی)،
            // ردیف مخزن را به 'used' + post_id آپدیت کن — برای فیدبک ترافیک بعدی.
            if ( class_exists( 'ACO_Keyword_Bank' ) && ! empty( $item->keyword ) ) {
                ACO_Keyword_Bank::mark_used_by_keyword( $item->keyword, (int) $write['post_id'] );
            }

        } catch ( \Throwable $e ) {
            if ( $item ) {
                self::mark_failed( $item->id, $e->getMessage() );
            }
            ACO_Logger::error( 'خطای غیرمنتظره در queue: ' . $e->getMessage(), 'queue' );
        } finally {
            // FIX: همیشه lock را آزاد کن حتی اگر connection قطع شود
            // GET_LOCK/RELEASE_LOCK session-scoped هستند — با قطع connection خودبه‌خود آزاد می‌شوند
            $wpdb->get_var( "SELECT RELEASE_LOCK('aco_queue_lock')" ); // phpcs:ignore
        }
    }

    private static function mark_failed( int $id, string $error ): void {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE_SUFFIX;
        $wpdb->update(
            $table,
            [
                'status'        => 'failed',
                'finished_at'   => current_time( 'mysql' ),
                'error_message' => mb_substr( $error, 0, 1000 ),
            ],
            [ 'id' => $id ],
            [ '%s', '%s', '%s' ],
            [ '%d' ]
        );
        ACO_Logger::error( "آیتم #{$id} با خطا پردازش شد: {$error}", 'queue' );
    }

    /**
     * Rate limit خطا — آیتم به pending برمی‌گردد با تأخیر ۶۰ ثانیه
     * به جای failed کردن، دوباره در صف قرار می‌گیرد تا بعداً پردازش شود.
     */
    private static function mark_rate_limited( int $id, string $error ): void {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE_SUFFIX;
        // ۶۰ ثانیه تأخیر — بیشتر از RETRY_DELAY_429 router
        $retry_at = date( 'Y-m-d H:i:s', current_time( 'timestamp' ) + 60 );
        $wpdb->query( $wpdb->prepare( // phpcs:ignore
            "UPDATE {$table}
             SET status       = 'pending',
                 started_at   = NULL,
                 scheduled_at = %s,
                 error_message = %s
             WHERE id = %d",
            $retry_at,
            mb_substr( '[Rate Limit - تلاش مجدد خودکار] ' . $error, 0, 1000 ),
            $id
        ) );
        ACO_Logger::error( "آیتم #{$id} rate limit خورد — تلاش مجدد در {$retry_at}", 'queue' );
    }

    /**
     * آیا خطا مربوط به rate limit (429) است؟
     */
    private static function is_rate_limit_error( string $error ): bool {
        return strpos( $error, '429' ) !== false
            || strpos( $error, 'محدودیت نرخ' ) !== false
            || strpos( $error, 'rate limit' ) !== false
            || strpos( $error, 'Rate limit' ) !== false;
    }

    // ─────────────────────────────────────────────
    // خواندن آیتم‌های صف
    // ─────────────────────────────────────────────
    public static function get_items( array $args = [] ): array {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE_SUFFIX;

        $defaults = [ 'status' => '', 'per_page' => 30, 'paged' => 1 ];
        $args = wp_parse_args( $args, $defaults );

        $where  = [ '1=1' ];
        $params = [];

        if ( $args['status'] ) {
            $where[]  = 'status = %s';
            $params[] = $args['status'];
        }

        $where_sql = implode( ' AND ', $where );
        $offset    = ( max( 1, (int) $args['paged'] ) - 1 ) * (int) $args['per_page'];
        $limit     = (int) $args['per_page'];

        $count_sql = "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}";
        $total     = $params
            ? (int) $wpdb->get_var( $wpdb->prepare( $count_sql, $params ) ) // phpcs:ignore
            : (int) $wpdb->get_var( $count_sql ); // phpcs:ignore

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
    // آمار سریع
    // ─────────────────────────────────────────────
    public static function get_counts(): array {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE_SUFFIX;
        $rows  = $wpdb->get_results( "SELECT status, COUNT(*) as cnt FROM {$table} GROUP BY status" ); // phpcs:ignore
        $out   = [ 'pending' => 0, 'processing' => 0, 'done' => 0, 'failed' => 0 ];
        foreach ( $rows as $r ) {
            if ( isset( $out[ $r->status ] ) ) {
                $out[ $r->status ] = (int) $r->cnt;
            }
        }
        return $out;
    }

    /**
     * SECURITY FIX: یک آیتم صف را بر اساس id برمی‌گرداند تا قبل از
     * delete/retry بتوان مالکیت پست مرتبط را چک کرد (جلوگیری از IDOR).
     */
    /**
     * پیدا کردن آخرین job صف مرتبط با یک پست مشخص (بر اساس rewrite_post_id + job_type).
     * استفاده: گزارش کرم برای نمایش وضعیت واقعیِ یک rewrite که به صف سپرده شده
     * (به‌جای یک برچسب ثابت "در صف" که هیچ‌وقت به‌روز نمی‌شد).
     */
    public static function get_latest_job_for_post( int $post_id, string $job_type = '' ) {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE_SUFFIX;

        if ( $job_type !== '' ) {
            return $wpdb->get_row( $wpdb->prepare( // phpcs:ignore
                "SELECT * FROM {$table} WHERE rewrite_post_id = %d AND job_type = %s ORDER BY id DESC LIMIT 1",
                $post_id, $job_type
            ) );
        }
        return $wpdb->get_row( $wpdb->prepare( // phpcs:ignore
            "SELECT * FROM {$table} WHERE rewrite_post_id = %d ORDER BY id DESC LIMIT 1",
            $post_id
        ) );
    }

    /**
     * تاریخچه‌ی کامل کارهای تمام‌شده‌ی صف (done/failed) — برای گزارش یکپارچه.
     * ORDER BY finished_at (یا created_at اگر finished_at خالی باشد) DESC.
     */
    public static function get_resolved_history( array $args = [] ): array {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE_SUFFIX;

        $defaults = [ 'job_type' => '', 'per_page' => 25, 'paged' => 1 ];
        $args     = wp_parse_args( $args, $defaults );

        $where  = [ "status IN ('done','failed')" ];
        $params = [];

        if ( $args['job_type'] !== '' ) {
            $where[]  = 'job_type = %s';
            $params[] = $args['job_type'];
        }

        $where_sql = implode( ' AND ', $where );
        $offset    = ( max( 1, (int) $args['paged'] ) - 1 ) * (int) $args['per_page'];
        $limit     = (int) $args['per_page'];

        $count_sql = "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}";
        $total     = $params
            ? (int) $wpdb->get_var( $wpdb->prepare( $count_sql, $params ) ) // phpcs:ignore
            : (int) $wpdb->get_var( $count_sql ); // phpcs:ignore

        $order_expr = 'COALESCE(finished_at, created_at)';
        $select_sql = "SELECT * FROM {$table} WHERE {$where_sql} ORDER BY {$order_expr} DESC LIMIT %d OFFSET %d";
        $all_params = array_merge( $params, [ $limit, $offset ] );
        $rows       = $wpdb->get_results( $wpdb->prepare( $select_sql, $all_params ) ); // phpcs:ignore

        return [
            'rows'  => $rows ?: [],
            'total' => $total,
            'pages' => $limit > 0 ? (int) ceil( $total / $limit ) : 1,
        ];
    }

    public static function get_item( int $id ) {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE_SUFFIX;
        return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ) ); // phpcs:ignore
    }

    public static function delete_item( int $id ): bool {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE_SUFFIX;
        return (bool) $wpdb->delete( $table, [ 'id' => $id ], [ '%d' ] );
    }

    /**
     * FIX: retry_item — مشکل قبلی: null values در فرمت آرایه
     * wpdb->update با null در data array مشکل دارد. باید از raw query استفاده کرد.
     */
    public static function retry_item( int $id ): bool {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE_SUFFIX;

        // FIX: به جای ارسال null در آرایه data (که wpdb آن را رشته 'NULL' می‌کند)،
        // از raw SQL با IS NULL conditions استفاده می‌کنیم
        $result = $wpdb->query( $wpdb->prepare( // phpcs:ignore
            "UPDATE {$table}
             SET status = 'pending',
                 error_message = NULL,
                 started_at = NULL,
                 finished_at = NULL
             WHERE id = %d
               AND status = 'failed'",
            $id
        ) );

        return $result === 1; // دقیقاً یک ردیف باید آپدیت شده باشد
    }
}
