<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * ARCH-1: AJAX handlerها — استخراج‌شده از class-aco-admin.php
 * شامل: ajax_research, ajax_write_save, ajax_rewrite, ajax_save_settings,
 *        ajax_test_api, ajax_queue_add/bulk/rewrite/action, ajax_logs_clear
 */
trait ACO_Admin_Ajax {

    // ─────────────────────────────────────
    // FIX (سویچ خودکار + صف): وقتی تمام مدل‌های رایگان (مدل اصلی + چند دور
    // Free Models Router در ACO_AI_Router) با rate limit مواجه شوند، به‌جای
    // نمایش خطا به کاربر، همین درخواست تولید مقاله به‌صورت خودکار به صف
    // پردازش (ACO_Queue) سپرده می‌شود تا کرون هر دقیقه دوباره امتحان کند
    // (که خودش هم منطق backoff/retry برای rate limit دارد).
    // منطق تبدیل status/scheduled_at دقیقاً همان چیزی است که ajax_queue_add
    // استفاده می‌کند تا رفتار یکسان باشد.
    // ─────────────────────────────────────
    private static function auto_queue_on_rate_limit( string $keyword, string $topic, string $status_raw, string $scheduled_at_raw ) /* : int|false */ {
        $post_status = in_array( $status_raw, [ 'draft', 'publish', 'pending', 'future' ], true ) ? $status_raw : 'draft';

        $scheduled_mysql = null;
        if ( $post_status === 'future' && $scheduled_at_raw ) {
            $scheduled_clean = self::parse_scheduled_at( $scheduled_at_raw );
            $ts_utc = $scheduled_clean ? strtotime( get_gmt_from_date( $scheduled_clean ) ) : false;
            if ( $ts_utc && $ts_utc > time() ) {
                $scheduled_mysql = $scheduled_clean;
            }
        }

        $id = ACO_Queue::enqueue( $keyword, $topic, $post_status, $scheduled_mysql );
        if ( $id ) {
            ACO_Logger::info( "⏳ مدل‌های رایگان موقتاً در دسترس نبودند — آیتم #{$id} خودکار به صف پردازش اضافه شد: {$keyword}", 'queue' );
        }
        return $id;
    }

    public static function ajax_rewrite(): void {
        // FIX #ob-2: همان دلیل ajax_research/ajax_write_save
        ob_start();
        if ( ! check_ajax_referer( 'aco_nonce', 'nonce', false ) ) {
            while ( ob_get_level() > 0 ) { ob_end_clean(); }
            wp_send_json_error( [ 'message' => 'درخواست نامعتبر است. صفحه را رفرش کنید.' ] );
            return;
        }
        if ( ! current_user_can( 'edit_posts' ) ) {
            while ( ob_get_level() > 0 ) { ob_end_clean(); }
            wp_send_json_error( [ 'message' => 'دسترسی غیرمجاز.' ] );
            return;
        }

        $post_id = absint( $_POST['post_id'] ?? 0 );
        if ( ! $post_id || ! get_post( $post_id ) ) {
            while ( ob_get_level() > 0 ) { ob_end_clean(); }
            wp_send_json_error( [ 'message' => 'پست یافت نشد.' ] );
            return;
        }

        // بررسی دسترسی ویرایش این پست خاص
        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            while ( ob_get_level() > 0 ) { ob_end_clean(); }
            wp_send_json_error( [ 'message' => 'دسترسی به ویرایش این پست ندارید.' ] );
            return;
        }

        // FIX #11: متد مشترک
        self::extend_time_limit();

        // lock — مشابه ajax_research: اگر مرحله ۱ تمام شده ولی مرحله ۲ هرگز فراخوانی نشده،
        // lock را آزاد کن تا rewrite بلاک نشود (FIX #rewrite-lock)
        $lock_key = 'aco_lock_' . get_current_user_id();
        if ( get_transient( $lock_key ) ) {
            $stage = get_transient( $lock_key . '_stage' );
            if ( $stage === 'research_done' ) {
                delete_transient( $lock_key );
                delete_transient( $lock_key . '_stage' );
            } else {
                while ( ob_get_level() > 0 ) { ob_end_clean(); }
                wp_send_json_error( [ 'message' => 'یک درخواست در حال پردازش است. لطفاً صبر کنید.' ] );
                return;
            }
        }
        set_transient( $lock_key, 1, 5 * MINUTE_IN_SECONDS );

        $result = [ 'success' => false, 'error' => 'خطای غیرمنتظره.' ]; // FIX: init before try
        try {
            $gen    = new ACO_Generator();
            $result = $gen->rewrite_post( $post_id );
        } finally {
            delete_transient( $lock_key );
        }

        if ( $result['success'] ) {
            while ( ob_get_level() > 0 ) { ob_end_clean(); }
            wp_send_json_success( $result );
        } else {
            // FIX BUG-1: normalize error key — JS انتظار دارد کلید 'message' باشد نه 'error'
            if ( ! isset( $result['message'] ) && isset( $result['error'] ) ) {
                $result['message'] = $result['error'];
            }
            while ( ob_get_level() > 0 ) { ob_end_clean(); }
            wp_send_json_error( $result );
        }
    }

    // ─────────────────────────────────────
    // صفحه تاریخچه مقالات
    // تب ۱: مقالات تولیدشده توسط ACO
    // تب ۲: همه مقالات سایت
    // ─────────────────────────────────────

    public static function ajax_research(): void {
        // FIX #ob-2: از همین ابتدا یک output buffer باز می‌کنیم تا هر warning/notice
        // پراکنده‌ای که مستقیماً echo می‌شود (نه فقط آن‌هایی که در بافر هاست جمع می‌شوند)
        // قبل از ارسال JSON قابل حذف باشد. قبلاً فقط لایه‌های بافر از قبل موجود پاک
        // می‌شدند؛ اگر اصلاً بافری فعال نبود (php.ini بدون output_buffering)، warning
        // مستقیم در پاسخ چاپ می‌شد و JSON را خراب می‌کرد — با اینکه پست ذخیره شده بود.
        ob_start();
        if ( ! check_ajax_referer( 'aco_nonce', 'nonce', false ) ) {
            while ( ob_get_level() > 0 ) { ob_end_clean(); }
            wp_send_json_error( [ 'message' => 'درخواست نامعتبر است. صفحه را رفرش کنید و دوباره تلاش کنید.' ] );
            return;
        }

        if ( ! current_user_can( 'edit_posts' ) ) {
            while ( ob_get_level() > 0 ) { ob_end_clean(); }
            wp_send_json_error( [ 'message' => 'دسترسی غیرمجاز.' ] );
            return;
        }

        // FIX #11: متد مشترک برای extend time limit
        self::extend_time_limit();

        // جلوگیری از ارسال موازی — هر کاربر فقط یک درخواست همزمان مجاز است
        $lock_key = 'aco_lock_' . get_current_user_id();
        if ( get_transient( $lock_key ) ) {
            // FIX #1: سه حالت برای reset سریع lock:
            // الف) مرحله ۱ تمام شده اما مرحله ۲ فراخوانی نشده (JS crash یا tab بسته)
            // ب) lock از یک نشست قدیمی مانده (نیاز به بررسی زمان ایجاد)
            $stage = get_transient( $lock_key . '_stage' );
            if ( $stage === 'research_done' ) {
                // مرحله ۲ هرگز فراخوانی نشده — lock را آزاد کن و ادامه بده
                delete_transient( $lock_key );
                delete_transient( $lock_key . '_stage' );
            } else {
                // lock فعال و معتبر است — درخواست جدید را رد کن
                while ( ob_get_level() > 0 ) { ob_end_clean(); }
                wp_send_json_error( [ 'message' => 'یک درخواست در حال پردازش است. لطفاً چند لحظه صبر کنید یا صفحه را رفرش کنید.' ] );
                return;
            }
        }
        set_transient( $lock_key, 1, 5 * MINUTE_IN_SECONDS );

        $keyword = sanitize_text_field( wp_unslash( $_POST['keyword'] ?? '' ) );
        $topic   = sanitize_text_field( wp_unslash( $_POST['topic']   ?? '' ) );

        if ( empty( $keyword ) || empty( $topic ) ) {
            delete_transient( $lock_key );
            while ( ob_get_level() > 0 ) { ob_end_clean(); }
            wp_send_json_error( [ 'message' => 'کلمه کلیدی و عنوان الزامی هستند.' ] );
            return;
        }

        $gen    = new ACO_Generator();
        $result = [ 'success' => false ]; // FIX: مقدار پیش‌فرض برای جلوگیری از undefined در finally
        try {
            $result = $gen->run_research_stage( $keyword, $topic );
        } finally {
            // FIX #2: lock را اینجا آزاد نمی‌کنیم — مرحله ۲ مسئول آزادسازی است.
            // اگر مرحله ۲ هیچ‌وقت فراخوانی نشود (JS خطا داد)، lock بعد از TTL (5 دقیقه) خودبه‌خود پاک می‌شود.
            // برای اینکه stage 2 بتواند lock را verify کند، یک نشانه مرحله ذخیره می‌شود.
            if ( isset( $result['success'] ) && $result['success'] ) {
                set_transient( $lock_key . '_stage', 'research_done', 5 * MINUTE_IN_SECONDS );
            } else {
                // مرحله ۱ شکست خورد — lock را فوری آزاد کن
                delete_transient( $lock_key );
            }
        }

        if ( $result['success'] ) {
            // FIX #ob: پاک‌سازی output buffer (هاست‌هایی که notice اضافه می‌کنند)
            while ( ob_get_level() > 0 ) { ob_end_clean(); }
            wp_send_json_success( $result );
        } else {
            // FIX (سویچ + صف): اگر همه‌ی مدل‌های رایگان rate-limit شده بودند، به‌جای
            // نمایش خطا، همین درخواست را خودکار به صف پردازش می‌سپاریم.
            if ( ! empty( $result['rate_limit_exhausted'] ) ) {
                $status_raw    = sanitize_text_field( wp_unslash( $_POST['status']       ?? 'draft' ) );
                $scheduled_raw = sanitize_text_field( wp_unslash( $_POST['scheduled_at'] ?? '' ) );
                $queue_id      = self::auto_queue_on_rate_limit( $keyword, $topic, $status_raw, $scheduled_raw );

                if ( $queue_id ) {
                    $log   = $result['log'] ?? [];
                    $log[] = '⏳ مدل‌های رایگان در حال حاضر شلوغ هستند.';
                    $log[] = "📥 این مقاله به‌طور خودکار به صف پردازش اضافه شد (آیتم #{$queue_id}) و به‌محض آزاد شدن ظرفیت رایگان، تولید خواهد شد.";
                    while ( ob_get_level() > 0 ) { ob_end_clean(); }
                    wp_send_json_success( [
                        'queued'   => true,
                        'queue_id' => $queue_id,
                        'log'      => $log,
                        'message'  => 'به دلیل شلوغی موقت مدل‌های رایگان، این مقاله به صف پردازش خودکار اضافه شد.',
                    ] );
                    return;
                }
                // افزودن به صف هم شکست خورد — خطای اصلی زیر نمایش داده می‌شود
            }

            // FIX BUG-1: normalize error key — JS انتظار دارد کلید 'message' باشد نه 'error'
            if ( ! isset( $result['message'] ) && isset( $result['error'] ) ) {
                $result['message'] = $result['error'];
            }
            while ( ob_get_level() > 0 ) { ob_end_clean(); }
            wp_send_json_error( $result );
        }
    }

    // ─────────────────────────────────────
    // AJAX: مرحله ۲ — نوشتن + سئو + ذخیره
    // آوتلاین مرحله ۱ از JS پاس داده می‌شود
    // ─────────────────────────────────────

    public static function ajax_write_save(): void {
        // FIX #ob-2: همان دلیل ajax_research — از ابتدا یک بافر باز می‌کنیم تا warning/notice
        // مستقیم echoشده (بدون بافر از قبل موجود) پاسخ JSON را خراب نکند.
        ob_start();
        // BUG-1 FIX: lock_key را اینجا تعریف می‌کنیم تا در تمام مسیرهای خروجی در دسترس باشد
        $lock_key = 'aco_lock_' . get_current_user_id();

        if ( ! check_ajax_referer( 'aco_nonce', 'nonce', false ) ) {
            // BUG-1 FIX: nonce fail — lock را آزاد کن تا کاربر بتواند دوباره تلاش کند
            delete_transient( $lock_key );
            delete_transient( $lock_key . '_stage' );
            while ( ob_get_level() > 0 ) { ob_end_clean(); }
            wp_send_json_error( [ 'message' => 'درخواست نامعتبر است. صفحه را رفرش کنید و دوباره تلاش کنید.' ] );
            return;
        }

        if ( ! current_user_can( 'edit_posts' ) ) {
            delete_transient( $lock_key );
            delete_transient( $lock_key . '_stage' );
            while ( ob_get_level() > 0 ) { ob_end_clean(); }
            wp_send_json_error( [ 'message' => 'دسترسی غیرمجاز.' ] );
            return;
        }

        // FIX #11: متد مشترک برای extend time limit
        self::extend_time_limit();

        // FIX #2: lock از مرحله ۱ باید هنوز فعال باشد
        // اگر مرحله ۱ موفق بوده، lock وجود دارد — stage token را پاک کن و ادامه بده
        if ( ! get_transient( $lock_key ) ) {
            while ( ob_get_level() > 0 ) { ob_end_clean(); }
            wp_send_json_error( [ 'message' => 'نشست منقضی شده است. لطفاً دوباره از ابتدا تلاش کنید.' ] );
            return;
        }
        delete_transient( $lock_key . '_stage' ); // پاکسازی نشانه مرحله

        $keyword           = sanitize_text_field( wp_unslash( $_POST['keyword'] ?? '' ) );
        $topic             = sanitize_text_field( wp_unslash( $_POST['topic']   ?? '' ) );
        $status            = sanitize_text_field( wp_unslash( $_POST['status']  ?? 'draft' ) );
        $meta_description  = sanitize_text_field( wp_unslash( $_POST['meta_description'] ?? '' ) );
        $research_tokens   = absint( $_POST['research_tokens'] ?? 0 );

        $headings           = self::sanitize_string_array( $_POST['headings']           ?? [] );
        $entities           = self::sanitize_string_array( $_POST['entities']           ?? [] );
        $secondary_keywords = self::sanitize_string_array( $_POST['secondary_keywords'] ?? [] );

        $tax_category = sanitize_text_field( wp_unslash( $_POST['tax_category'] ?? '' ) );
        $tax_tags     = self::sanitize_string_array( $_POST['tax_tags'] ?? [] );
        $taxonomy     = [ 'category' => $tax_category, 'tags' => $tax_tags ];

        // پشتیبانی از زمان‌بندی انتشار
        // BUG-NEW-2 FIX: قبلاً get_date_from_gmt(gmdate(..., $ts)) استفاده می‌شد که
        // فرض می‌کرد $ts یک UTC timestamp است — اما datetime-local از browser،
        // زمان local کاربر را می‌فرستد (بدون timezone info).
        // strtotime() آن را با timezone سرور parse می‌کند → اگر server tz != WP tz،
        // double conversion رخ می‌داد و زمان اشتباه ذخیره می‌شد.
        // راه‌حل: مقدار datetime-local را مستقیماً به WP local time تفسیر می‌کنیم
        // (کاربر تاریخ را در WP admin ایران می‌بیند → مقدار وارد شده هم local WP time است).
        $scheduled_at = sanitize_text_field( wp_unslash( $_POST['scheduled_at'] ?? '' ) );
        $scheduled_mysql = null;
        $status_was_future_invalid = false; // FIX BUG-A1: برای اطلاع‌رسانی به کاربر
        if ( $status === 'future' && $scheduled_at ) {
            // ورودی شمسی ('1405/08/27 14:30') یا قالب قدیمی datetime-local
            $scheduled_clean = self::parse_scheduled_at( $scheduled_at );
            $ts_utc = $scheduled_clean ? strtotime( get_gmt_from_date( $scheduled_clean ) ) : false; // WP local → UTC
            if ( $ts_utc && $ts_utc > time() ) {
                // scheduled_mysql = WP local time برای ذخیره در post_date
                $scheduled_mysql = $scheduled_clean;
            } else {
                $status = 'draft'; // تاریخ نامعتبر یا در گذشته — برگشت به پیش‌نویس
                $status_was_future_invalid = true;
            }
        } elseif ( $status === 'future' && ! $scheduled_at ) {
            // future بدون تاریخ — برگشت به پیش‌نویس
            $status = 'draft';
            $status_was_future_invalid = true;
        }

        if ( ! in_array( $status, [ 'draft', 'publish', 'pending', 'future' ], true ) ) {
            $status = 'draft';
        }

        if ( empty( $keyword ) || empty( $topic ) || empty( $headings ) ) {
            delete_transient( $lock_key );
            while ( ob_get_level() > 0 ) { ob_end_clean(); }
            wp_send_json_error( [ 'message' => 'اطلاعات مرحله تحقیق ناقص است. لطفاً دوباره از ابتدا تلاش کنید.' ] );
            return;
        }

        // FIX #1: lock باید در هر حالتی (موفق/خطا/exception) آزاد شود
        $result = [ 'success' => false, 'error' => 'خطای غیرمنتظره.' ]; // مقدار پیش‌فرض
        try {
            $gen    = new ACO_Generator();
            $result = $gen->run_write_and_save_stage(
                $keyword,
                $topic,
                $headings,
                $entities,
                $secondary_keywords,
                $meta_description,
                $status,
                $research_tokens,
                $taxonomy
            );
        } finally {
            delete_transient( $lock_key );
        }

        if ( $result['success'] ) {
            // اعمال زمان‌بندی انتشار (future)
            if ( $scheduled_mysql && $result['post_id'] ) {
                wp_update_post( [
                    'ID'            => $result['post_id'],
                    'post_status'   => 'future',
                    'post_date'     => $scheduled_mysql,
                    'post_date_gmt' => get_gmt_from_date( $scheduled_mysql ),
                ] );
                $result['scheduled_at'] = $scheduled_mysql;
            }
            // پیشنهاد alt text برای تصویر شاخص به ممبر
            $result['alt_text_tip'] = sprintf(
                '%s — %s',
                sanitize_text_field( wp_unslash( $_POST['keyword'] ?? '' ) ),
                sanitize_text_field( wp_unslash( $_POST['topic']   ?? '' ) )
            );
            // FIX BUG-A1: اطلاع‌رسانی به JS وقتی future → draft تبدیل شده
            if ( $status_was_future_invalid ) {
                $result['status_fallback'] = 'draft';
                $result['status_fallback_reason'] = 'تاریخ زمان‌بندی نامعتبر یا در گذشته بود — مقاله به‌صورت پیش‌نویس ذخیره شد.';
            }
            // FIX #ob: پاک‌سازی تمام سطوح output buffer (nested) قبل از JSON
            // ob_clean() تنها لایه فعال را پاک می‌کند؛ هاست‌هایی با nested buffer نیاز به loop دارند
            while ( ob_get_level() > 0 ) { ob_end_clean(); }
            wp_send_json_success( $result );
        } else {
            // FIX (سویچ + صف): اگر همه‌ی مدل‌های رایگان در مرحله نوشتن هم rate-limit شده
            // بودند، به‌جای نمایش خطا، یک job جدید (از نوع generate) به صف اضافه می‌شود —
            // چون مرحله نوشتن به آوتلاین مرحله ۱ وابسته است و صف خودش هر دو مرحله را از نو
            // (با همان کلمه کلیدی/عنوان، از کش یا تحقیق تازه) انجام می‌دهد.
            if ( ! empty( $result['rate_limit_exhausted'] ) ) {
                $queue_id = self::auto_queue_on_rate_limit( $keyword, $topic, $status, $scheduled_at );

                if ( $queue_id ) {
                    $log   = $result['log'] ?? [];
                    $log[] = '⏳ مدل‌های رایگان در حال حاضر شلوغ هستند.';
                    $log[] = "📥 این مقاله به‌طور خودکار به صف پردازش اضافه شد (آیتم #{$queue_id}) و به‌محض آزاد شدن ظرفیت رایگان، تولید خواهد شد.";
                    while ( ob_get_level() > 0 ) { ob_end_clean(); }
                    wp_send_json_success( [
                        'queued'   => true,
                        'queue_id' => $queue_id,
                        'log'      => $log,
                        'message'  => 'به دلیل شلوغی موقت مدل‌های رایگان، این مقاله به صف پردازش خودکار اضافه شد.',
                    ] );
                    return;
                }
                // افزودن به صف هم شکست خورد — خطای اصلی زیر نمایش داده می‌شود
            }

            // FIX BUG-1: normalize error key — JS انتظار دارد کلید 'message' باشد نه 'error'
            if ( ! isset( $result['message'] ) && isset( $result['error'] ) ) {
                $result['message'] = $result['error'];
            }
            while ( ob_get_level() > 0 ) { ob_end_clean(); }
            wp_send_json_error( $result );
        }
    }

    // ─────────────────────────────────────
    // Helper: پاک‌سازی آرایه‌ای از رشته‌ها که از POST می‌آید
    // ─────────────────────────────────────

    private static function sanitize_string_array( $raw ): array {
        if ( ! is_array( $raw ) ) {
            return [];
        }
        $clean = [];
        foreach ( $raw as $item ) {
            if ( ! is_string( $item ) ) continue;
            $item = sanitize_text_field( wp_unslash( $item ) );
            if ( $item !== '' ) $clean[] = $item;
        }
        return $clean;
    }


    // ─────────────────────────────────────
    // AJAX: ذخیره تنظیمات
    // ─────────────────────────────────────

    public static function ajax_save_settings(): void {
        if ( ! check_ajax_referer( 'aco_nonce', 'nonce', false ) ) {
            wp_send_json_error( [ 'message' => 'درخواست نامعتبر است. صفحه را رفرش کنید و دوباره تلاش کنید.' ] );
            return;
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => 'دسترسی غیرمجاز.' ] );
            return;
        }

        $provider        = sanitize_text_field( wp_unslash( $_POST['provider']         ?? 'openrouter' ) );
        $free_only_mode  = ! empty( $_POST['free_only_mode'] );
        $fallback_provider = sanitize_text_field( wp_unslash( $_POST['fallback_provider'] ?? '' ) );
        $openrouter_key  = sanitize_text_field( wp_unslash( $_POST['openrouter_key']   ?? '' ) );
        $deepseek_key    = sanitize_text_field( wp_unslash( $_POST['deepseek_key']     ?? '' ) );
        $openai_key      = sanitize_text_field( wp_unslash( $_POST['openai_key']       ?? '' ) );
        $gemini_key      = sanitize_text_field( wp_unslash( $_POST['gemini_key']       ?? '' ) );
        $anthropic_key   = sanitize_text_field( wp_unslash( $_POST['anthropic_key']    ?? '' ) );
        $openrouter_model= sanitize_text_field( wp_unslash( $_POST['openrouter_model'] ?? 'google/gemma-4-31b-it:free' ) );
        $deepseek_model  = sanitize_text_field( wp_unslash( $_POST['deepseek_model']   ?? 'deepseek-chat' ) );
        $openai_model    = sanitize_text_field( wp_unslash( $_POST['openai_model']     ?? 'gpt-4o-mini' ) );
        $openai_image_model = sanitize_text_field( wp_unslash( $_POST['openai_image_model'] ?? 'gpt-image-1' ) );
        $gemini_model    = ACO_Settings::normalize_gemini_model( sanitize_text_field( wp_unslash( $_POST['gemini_model'] ?? 'gemini-3.8-flash' ) ) );
        $anthropic_model = sanitize_text_field( wp_unslash( $_POST['anthropic_model']  ?? 'claude-3-5-haiku-20241022' ) );
        $gutenberg_blocks_enabled = ! empty( $_POST['gutenberg_blocks_enabled'] );
        $default_status  = sanitize_text_field( wp_unslash( $_POST['default_status']   ?? 'draft' ) );
        $default_author  = absint( $_POST['default_author'] ?? 1 );
        $niche           = sanitize_text_field( wp_unslash( $_POST['niche']            ?? '' ) );
        $tone            = sanitize_text_field( wp_unslash( $_POST['tone']             ?? '' ) );
        $min_words       = max( 500, min( 5000, absint( $_POST['min_words'] ?? 1200 ) ) );
        $monthly_token_budget = max( 0, absint( $_POST['monthly_token_budget'] ?? 0 ) );
        $featured_image_enabled = ! empty( $_POST['featured_image_enabled'] );

        // Google Search Console — Site URL و JSON کلید Service Account
        $gsc_service_account_json = (string) wp_unslash( $_POST['gsc_service_account_json'] ?? '' );
        $gsc_raw_input   = trim( (string) wp_unslash( $_POST['gsc_site_url'] ?? '' ) );
        // esc_url_raw اسکیم sc-domain: را نمی‌شناسد و ممکن است حذفش کند — جداگانه دست‌نخورده نگه دار
        $gsc_site_url    = ( strpos( $gsc_raw_input, 'sc-domain:' ) === 0 )
            ? sanitize_text_field( $gsc_raw_input )
            : esc_url_raw( $gsc_raw_input, [ 'http', 'https' ] );

        // کلیدواژه‌های محافظت‌شده تصویر — یک نام/کلیدواژه در هر خط
        $protected_raw = (string) wp_unslash( $_POST['protected_image_keywords'] ?? '' );
        $protected_image_keywords = array_values( array_filter( array_map(
            function( $line ) { return sanitize_text_field( trim( $line ) ); },
            preg_split( '/[\r\n]+/', $protected_raw )
        ) ) );

        // internal_link_pages: آرایه‌ای از [{title, url}]
        $raw_link_pages    = isset( $_POST['internal_link_pages'] ) ? (array) $_POST['internal_link_pages'] : [];
        $internal_link_pages = [];
        foreach ( $raw_link_pages as $lp ) {
            if ( ! is_array( $lp ) ) continue;
            $title = sanitize_text_field( wp_unslash( $lp['title'] ?? '' ) );
            $url   = esc_url_raw( wp_unslash( $lp['url'] ?? '' ) );
            $desc  = sanitize_text_field( wp_unslash( $lp['description'] ?? '' ) );
            if ( empty( $url ) ) continue;
            $internal_link_pages[] = [ 'title' => $title, 'url' => $url, 'description' => $desc ];
        }

        // validation
        if ( ! in_array( $provider, ACO_AI_Router::PROVIDERS, true ) ) {
            $provider = 'openrouter';
        }
        // '' یعنی fallback غیرفعال — مقدار مجاز است. هر چیز دیگری باید یکی از ارائه‌دهنده‌های معتبر باشد.
        if ( $fallback_provider !== '' && ! in_array( $fallback_provider, ACO_AI_Router::PROVIDERS, true ) ) {
            $fallback_provider = '';
        }
        if ( ! in_array( $default_status, [ 'draft', 'publish', 'pending' ], true ) ) {
            $default_status = 'draft';
        }
        if ( ! get_user_by( 'id', $default_author ) ) {
            $default_author = 1;
        }
        // جلوگیری از model خالی
        if ( empty( $openrouter_model ) ) $openrouter_model = 'google/gemma-4-31b-it:free';
        if ( empty( $deepseek_model ) )   $deepseek_model   = 'deepseek-chat';
        if ( empty( $openai_model ) )     $openai_model     = 'gpt-4o-mini';
        if ( empty( $openai_image_model ) ) $openai_image_model = 'gpt-image-1';
        if ( empty( $gemini_model ) )     $gemini_model     = 'gemini-3.8-flash';
        if ( empty( $anthropic_model ) )  $anthropic_model  = 'claude-3-5-haiku-20241022';

        ACO_Settings::save( compact(
            'provider', 'free_only_mode', 'fallback_provider', 'openrouter_key', 'deepseek_key', 'openai_key', 'gemini_key', 'anthropic_key',
            'openrouter_model', 'deepseek_model', 'openai_model', 'openai_image_model', 'gemini_model', 'anthropic_model',
            'gutenberg_blocks_enabled',
            'default_status', 'default_author',
            'niche', 'tone', 'min_words', 'internal_link_pages',
            'featured_image_enabled', 'protected_image_keywords',
            'monthly_token_budget', 'gsc_service_account_json', 'gsc_site_url'
        ) );

        wp_send_json_success( [ 'message' => 'تنظیمات با موفقیت ذخیره شد.' ] );
    }

    // ─────────────────────────────────────
    // AJAX: تست API
    // ─────────────────────────────────────

    public static function ajax_test_api(): void {
        if ( ! check_ajax_referer( 'aco_nonce', 'nonce', false ) ) {
            wp_send_json_error( [ 'message' => 'درخواست نامعتبر است. صفحه را رفرش کنید و دوباره تلاش کنید.' ] );
            return;
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => 'دسترسی غیرمجاز.' ] );
            return;
        }

        // FIX #11: متد مشترک
        self::extend_time_limit();

        // اگر مقادیر فرم (هنوز ذخیره‌نشده) ارسال شده باشند، همان‌ها استفاده می‌شوند
        // تا کاربر بتواند طبق راهنمای نصب، قبل از زدن «ذخیره تنظیمات»،
        // کلید تازه‌واردشده را تست کند — نه کلید قدیمیِ ذخیره‌شده در دیتابیس.
        $override = null;
        if ( isset( $_POST['provider'] ) ) {
            $provider = sanitize_text_field( wp_unslash( $_POST['provider'] ) );
            if ( ! in_array( $provider, ACO_AI_Router::PROVIDERS, true ) ) {
                $provider = 'openrouter';
            }

            // فیلدهای کلید دیگر مقدار قبلی را pre-fill نمی‌کنند (رفع نشتی
            // کلید در HTML صفحه)، پس اگر خالی باشند یعنی کاربر می‌خواهد
            // همان کلید ذخیره‌شده را تست کند، نه این‌که کلید خالی است.
            $override = [ 'provider' => $provider ];
            foreach ( ACO_AI_Router::PROVIDERS as $p ) {
                $field  = "{$p}_key";
                $posted = sanitize_text_field( wp_unslash( $_POST[ $field ] ?? '' ) );
                $override[ $field ] = $posted !== '' ? $posted : (string) ACO_Settings::get( $field );
            }
        }

        $router = new ACO_AI_Router( $override );
        $result = $router->generate(
            'You are a helpful assistant. Always respond in the same language as the user.',
            'Reply with exactly: connection successful / اتصال برقرار است'
        );

        if ( $result['success'] ) {
            wp_send_json_success( [
                'message'    => '✅ اتصال برقرار است.',
                'provider'   => $router->get_provider(),
                'model'      => $router->get_model(),
                'response'   => mb_substr( $result['text'], 0, 120 ),
                'tokens'     => $result['tokens'] ?? 0,
                'retries'    => $result['retry_count'] ?? 0,
            ] );
        } else {
            wp_send_json_error( [
                'message'  => '❌ ' . $result['error'],
                'provider' => $router->get_provider(),
                'model'    => $router->get_model(),
            ] );
        }
    }

    // ─────────────────────────────────────
    // صفحه صف پردازش
    // ─────────────────────────────────────

    public static function ajax_queue_add(): void {
        if ( ! check_ajax_referer( 'aco_nonce', 'nonce', false ) ) {
            wp_send_json_error( [ 'message' => 'درخواست نامعتبر.' ] );
            return;
        }
        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_send_json_error( [ 'message' => 'دسترسی غیرمجاز.' ] );
            return;
        }

        $keyword      = sanitize_text_field( wp_unslash( $_POST['keyword'] ?? '' ) );
        $topic        = sanitize_text_field( wp_unslash( $_POST['topic']   ?? '' ) );
        $post_status  = sanitize_text_field( wp_unslash( $_POST['post_status'] ?? 'draft' ) );
        $scheduled_at = sanitize_text_field( wp_unslash( $_POST['scheduled_at'] ?? '' ) );

        if ( empty( $keyword ) || empty( $topic ) ) {
            wp_send_json_error( [ 'message' => 'کلمه کلیدی و عنوان الزامی هستند.' ] );
            return;
        }

        // تبدیل datetime-local به MySQL datetime
        // BUG-NEW-2 FIX: قبلاً get_date_from_gmt(gmdate(..., strtotime())) استفاده می‌شد که
        // double conversion ایجاد می‌کرد (datetime-local = WP local time، نه UTC).
        // راه‌حل: T را با space جایگزین کن و مستقیم به عنوان WP local time ذخیره کن.
        $scheduled_mysql = null;
        if ( $post_status === 'future' && $scheduled_at ) {
            $scheduled_clean = self::parse_scheduled_at( $scheduled_at );
            $ts_utc = $scheduled_clean ? strtotime( get_gmt_from_date( $scheduled_clean ) ) : false;
            if ( $ts_utc && $ts_utc > time() ) {
                $scheduled_mysql = $scheduled_clean;
            }
        }

        $id = ACO_Queue::enqueue( $keyword, $topic, $post_status, $scheduled_mysql );
        if ( $id ) {
            ACO_Logger::info( "آیتم #{$id} به صف اضافه شد: {$keyword}", 'queue' );
            wp_send_json_success( [ 'message' => "✅ آیتم #{$id} به صف اضافه شد.", 'id' => $id ] );
        } else {
            wp_send_json_error( [ 'message' => 'خطا در افزودن به صف.' ] );
        }
    }

    // ─────────────────────────────────────
    // AJAX: افزودن دسته‌جمعی به صف (IMPROVE #2)
    // هر فراخوانی یک آیتم اضافه می‌کند — JS حلقه می‌زند
    // ─────────────────────────────────────

    public static function ajax_queue_bulk_add(): void {
        if ( ! check_ajax_referer( 'aco_nonce', 'nonce', false ) ) {
            wp_send_json_error( [ 'message' => 'درخواست نامعتبر.' ] );
            return;
        }
        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_send_json_error( [ 'message' => 'دسترسی غیرمجاز.' ] );
            return;
        }

        $keyword     = sanitize_text_field( wp_unslash( $_POST['keyword']     ?? '' ) );
        $topic       = sanitize_text_field( wp_unslash( $_POST['topic']       ?? '' ) );
        $post_status = sanitize_text_field( wp_unslash( $_POST['post_status'] ?? 'draft' ) );

        if ( empty( $keyword ) ) {
            wp_send_json_error( [ 'message' => 'کلمه کلیدی خالی است.' ] );
            return;
        }

        // اگر عنوان نداشت از keyword استفاده کن
        if ( empty( $topic ) ) {
            $topic = $keyword;
        }

        $id = ACO_Queue::enqueue( $keyword, $topic, $post_status );
        if ( $id ) {
            wp_send_json_success( [ 'id' => $id, 'keyword' => $keyword ] );
        } else {
            wp_send_json_error( [ 'message' => "خطا در افزودن «{$keyword}» به صف." ] );
        }
    }

    // ─────────────────────────────────────
    // AJAX: افزودن بهبود مقاله به صف
    // ─────────────────────────────────────

    public static function ajax_queue_rewrite(): void {
        if ( ! check_ajax_referer( 'aco_nonce', 'nonce', false ) ) {
            wp_send_json_error( [ 'message' => 'درخواست نامعتبر.' ] );
            return;
        }
        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_send_json_error( [ 'message' => 'دسترسی غیرمجاز.' ] );
            return;
        }

        $post_id = absint( $_POST['post_id'] ?? 0 );
        if ( ! $post_id || ! get_post( $post_id ) ) {
            wp_send_json_error( [ 'message' => 'پست یافت نشد.' ] );
            return;
        }
        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            wp_send_json_error( [ 'message' => 'دسترسی به ویرایش این پست ندارید.' ] );
            return;
        }

        $id = ACO_Queue::enqueue_rewrite( $post_id );
        if ( $id ) {
            ACO_Logger::info( "بهبود پست #{$post_id} به صف اضافه شد — آیتم #{$id}", 'queue' );
            wp_send_json_success( [ 'message' => "✅ بهبود مقاله به صف اضافه شد (آیتم #{$id}). نتیجه را در صفحه صف پردازش دنبال کنید.", 'id' => $id ] );
        } else {
            wp_send_json_error( [ 'message' => 'خطا در افزودن به صف.' ] );
        }
    }

    // ─────────────────────────────────────
    // AJAX: ارسال دسته‌جمعی مقالات به صف بهبود (v1.9.0)
    // ─────────────────────────────────────

    public static function ajax_queue_bulk_rewrite(): void {
        if ( ! check_ajax_referer( 'aco_nonce', 'nonce', false ) ) {
            wp_send_json_error( [ 'message' => 'درخواست نامعتبر.' ] );
            return;
        }
        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_send_json_error( [ 'message' => 'دسترسی غیرمجاز.' ] );
            return;
        }

        // دریافت و sanitize آرایه post_ids
        $raw_ids = isset( $_POST['post_ids'] ) && is_array( $_POST['post_ids'] )
            ? $_POST['post_ids']
            : [];

        $post_ids = array_map( 'absint', $raw_ids );
        $post_ids = array_filter( $post_ids ); // حذف صفرها

        if ( empty( $post_ids ) ) {
            wp_send_json_error( [ 'message' => 'هیچ مقاله‌ای انتخاب نشده.' ] );
            return;
        }

        // محدودیت: حداکثر ۱۰۰ مقاله در هر بار
        if ( count( $post_ids ) > 100 ) {
            wp_send_json_error( [ 'message' => 'حداکثر ۱۰۰ مقاله در هر بار مجاز است.' ] );
            return;
        }

        $queued  = 0;
        $skipped = 0;
        $errors  = 0;

        foreach ( $post_ids as $post_id ) {
            // بررسی دسترسی ویرایش هر پست
            if ( ! current_user_can( 'edit_post', $post_id ) ) {
                $skipped++;
                continue;
            }
            $post = get_post( $post_id );
            if ( ! $post ) {
                $skipped++;
                continue;
            }

            $id = ACO_Queue::enqueue_rewrite( $post_id );
            if ( $id ) {
                ACO_Logger::info( "بهبود پست #{$post_id} (bulk) به صف اضافه شد — آیتم #{$id}", 'queue' );
                $queued++;
            } else {
                $errors++;
            }
        }

        $msg = "✅ {$queued} مقاله به صف بهبود اضافه شد.";
        if ( $skipped ) $msg .= " | {$skipped} مورد رد شد.";
        if ( $errors  ) $msg .= " | {$errors} خطا.";

        wp_send_json_success( [
            'message' => $msg,
            'queued'  => $queued,
            'skipped' => $skipped,
            'errors'  => $errors,
        ] );
    }

    // ─────────────────────────────────────
    // AJAX: عملیات روی آیتم صف (retry/delete)
    // ─────────────────────────────────────

    public static function ajax_queue_action(): void {
        if ( ! check_ajax_referer( 'aco_nonce', 'nonce', false ) ) {
            wp_send_json_error( [ 'message' => 'درخواست نامعتبر.' ] );
            return;
        }
        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_send_json_error( [ 'message' => 'دسترسی غیرمجاز.' ] );
            return;
        }

        $action = sanitize_text_field( wp_unslash( $_POST['queue_action'] ?? '' ) );
        $id     = absint( $_POST['item_id'] ?? 0 );

        if ( ! $id ) {
            wp_send_json_error( [ 'message' => 'آیتم نامعتبر.' ] );
            return;
        }

        // SECURITY FIX (IDOR): current_user_can('edit_posts') فقط دسترسی کلی را چک می‌کند،
        // نه دسترسی به پست خاص مرتبط با این آیتم صف. بدون این چک، کاربری با edit_posts
        // (مثلاً نقش Author) می‌توانست با حدس item_id، آیتم صفِ پست‌های دیگران را
        // حذف یا retry کند حتی اگر به آن پست خاص دسترسی ویرایش نداشت.
        $item = ACO_Queue::get_item( $id );
        if ( ! $item ) {
            wp_send_json_error( [ 'message' => 'آیتم یافت نشد.' ] );
            return;
        }
        $related_post_id = (int) ( $item->rewrite_post_id ?? $item->post_id ?? 0 );
        if ( $related_post_id && ! current_user_can( 'edit_post', $related_post_id ) ) {
            wp_send_json_error( [ 'message' => 'دسترسی غیرمجاز.' ] );
            return;
        }

        if ( $action === 'delete' ) {
            ACO_Queue::delete_item( $id );
            wp_send_json_success( [ 'message' => "آیتم #{$id} حذف شد." ] );
        } elseif ( $action === 'retry' ) {
            $ok = ACO_Queue::retry_item( $id );
            if ( $ok ) {
                wp_send_json_success( [ 'message' => "آیتم #{$id} به صف بازگشت." ] );
            } else {
                wp_send_json_error( [ 'message' => 'فقط آیتم‌های ناموفق قابل تلاش مجدد هستند.' ] );
            }
        } else {
            wp_send_json_error( [ 'message' => 'عملیات نامعتبر.' ] );
        }
    }

    // ─────────────────────────────────────
    // AJAX: پاک‌سازی لاگ‌ها
    // ─────────────────────────────────────

    public static function ajax_logs_clear(): void {
        if ( ! check_ajax_referer( 'aco_nonce', 'nonce', false ) ) {
            wp_send_json_error( [ 'message' => 'درخواست نامعتبر.' ] );
            return;
        }
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => 'دسترسی غیرمجاز.' ] );
            return;
        }
        ACO_Logger::clear_all();
        wp_send_json_success( [ 'message' => 'همه لاگ‌ها پاک شدند.' ] );
    }

    // ─────────────────────────────────────
    // AJAX: ذخیره تنظیمات کرم
    // ─────────────────────────────────────

    public static function ajax_save_crawler_settings(): void {
        if ( ! check_ajax_referer( 'aco_nonce', 'nonce', false ) ) {
            wp_send_json_error( [ 'message' => 'درخواست نامعتبر.' ] );
            return;
        }
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => 'دسترسی غیرمجاز.' ] );
            return;
        }

        $enabled         = ! empty( $_POST['enabled'] ) && $_POST['enabled'] !== 'false';
        $interval_days   = max( 1, absint( $_POST['interval_days']   ?? 30 ) );
        $seo_threshold   = max( 0, min( 100, absint( $_POST['seo_threshold']   ?? 60 ) ) );
        $min_links       = max( 0, absint( $_POST['min_links']       ?? 2  ) );
        $stale_months    = max( 1, absint( $_POST['stale_months']    ?? 6  ) );
        $posts_per_night = max( 1, min( 50, absint( $_POST['posts_per_night'] ?? 5  ) ) );
        $run_hour        = max( 0, min( 23, absint( $_POST['run_hour'] ?? 3 ) ) );
        // FIX: چک‌باکس بازبینی — پیش‌فرض روشن اگر کلید اصلاً ارسال نشده (سازگاری با فرم‌های قدیمی)
        $review_mode     = isset( $_POST['review_mode'] ) ? ( $_POST['review_mode'] !== 'false' && ! empty( $_POST['review_mode'] ) ) : true;

        $crawler_settings = [
            'enabled'         => $enabled,
            'interval_days'   => $interval_days,
            'seo_threshold'   => $seo_threshold,
            'min_links'       => $min_links,
            'stale_months'    => $stale_months,
            'posts_per_night' => $posts_per_night,
            'run_hour'        => $run_hour,
            'review_mode'     => $review_mode,
        ];

        // امنیت: از get_option خام استفاده می‌کنیم نه ACO_Settings::get() —
        // چون ACO_Settings::get() کلیدهای API را رمزگشایی می‌کند و اگر آن
        // نسخه‌ی رمزگشایی‌شده دوباره با update_option ذخیره شود، کلید API
        // به‌صورت متن ساده (رمزنگاری‌نشده) در دیتابیس می‌ماند. اینجا فقط
        // زیرمجموعه‌ی 'crawler' را دست می‌زنیم، کلیدهای API دست‌نخورده باقی می‌مانند.
        $all = get_option( 'aco_settings', [] );
        if ( ! is_array( $all ) ) $all = [];
        $all['crawler'] = $crawler_settings;
        update_option( 'aco_settings', $all );

        // cron رو بر اساس وضعیت و ساعت جدید تنظیم کن
        // (schedule_cron خودش اول unschedule می‌کنه تا ساعت جدید اعمال شه)
        if ( $enabled ) {
            ACO_Crawler::schedule_cron();
        } else {
            ACO_Crawler::unschedule_cron();
        }

        $next = wp_next_scheduled( ACO_Crawler::CRON_HOOK );
        wp_send_json_success( [
            'message'  => 'تنظیمات کرم ذخیره شد.',
            'next_run' => $next ? self::jalali_dt( (string) wp_date( 'Y-m-d H:i:s', (int) $next ) ) : null,
        ] );
    }

    // ─────────────────────────────────────
    // AJAX: اجرای دستی کرم (تست — بدون نیاز به صبر کردن تا ساعت برنامه‌ریزی‌شده)
    // ─────────────────────────────────────

    public static function ajax_crawler_run_now(): void {
        if ( ! check_ajax_referer( 'aco_nonce', 'nonce', false ) ) {
            wp_send_json_error( [ 'message' => 'درخواست نامعتبر.' ] );
            return;
        }
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => 'دسترسی غیرمجاز.' ] );
            return;
        }

        // اجرای دستی ممکن است زمان‌بر باشد (API calls)؛ از timeout پیش‌فرض جلوگیری کن
        if ( function_exists( 'set_time_limit' ) ) {
            @set_time_limit( 0 );
        }

        try {
            // force = true → حتی اگر «فعال‌سازی کرم» هنوز ذخیره نشده، برای تست اجرا شود
            ACO_Crawler::run( true );
            wp_send_json_success( [
                'message' => 'کرم محتوا اجرا شد. نتیجه را در «مشاهده گزارش» یا لاگ‌ها ببینید.',
            ] );
        } catch ( Throwable $e ) {
            ACO_Logger::error( '🐛 اجرای دستی کرم با خطا مواجه شد: ' . $e->getMessage(), 'crawler' );
            wp_send_json_error( [ 'message' => 'خطا در اجرا: ' . $e->getMessage() ] );
        }
    }

    // ─────────────────────────────────────
    // AJAX: تست اتصال به Google Search Console
    // ─────────────────────────────────────

    public static function ajax_test_gsc_connection(): void {
        if ( ! check_ajax_referer( 'aco_nonce', 'nonce', false ) ) {
            wp_send_json_error( [ 'message' => 'درخواست نامعتبر.' ] );
            return;
        }
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => 'دسترسی غیرمجاز.' ] );
            return;
        }

        if ( ! ACO_Search_Console::is_configured() ) {
            wp_send_json_error( [ 'message' => 'ابتدا Site URL و JSON کلید Service Account را ذخیره کنید.' ] );
            return;
        }

        $opportunities = ACO_Search_Console::get_opportunity_queries( 90, 5 );
        if ( empty( $opportunities ) ) {
            wp_send_json_success( [
                'message' => '✅ اتصال برقرار شد، اما فعلاً کوئری «فرصت» (رتبه ۱ تا ۵۰) پیدا نشد — ممکن است سایت داده کافی نداشته باشد.',
            ] );
            return;
        }

        $sample = array_map( function( $o ) {
            return $o['keyword'] . ' (impressions: ' . $o['impressions'] . '، رتبه: ' . $o['position'] . ')';
        }, array_slice( $opportunities, 0, 3 ) );

        wp_send_json_success( [
            'message' => '✅ اتصال موفق! نمونه کوئری‌های فرصت: ' . implode( ' | ', $sample ),
        ] );
    }

    // ─────────────────────────────────────
    // AJAX: ذخیره تنظیمات بانک کلمات کلیدی
    // ─────────────────────────────────────

    public static function ajax_save_keyword_bank_settings(): void {
        if ( ! check_ajax_referer( 'aco_nonce', 'nonce', false ) ) {
            wp_send_json_error( [ 'message' => 'درخواست نامعتبر.' ] );
            return;
        }
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => 'دسترسی غیرمجاز.' ] );
            return;
        }

        $enabled       = ! empty( $_POST['enabled'] ) && $_POST['enabled'] !== 'false';
        $daily_count   = max( 1, min( 20, absint( $_POST['daily_count'] ?? 2 ) ) );
        $refresh_count = max( 5, min( 40, absint( $_POST['refresh_count'] ?? 15 ) ) );
        $run_hour      = max( 0, min( 23, absint( $_POST['run_hour'] ?? 4 ) ) );
        $post_status   = sanitize_text_field( wp_unslash( $_POST['post_status'] ?? 'pending' ) );
        if ( ! in_array( $post_status, [ 'draft', 'publish', 'pending' ], true ) ) {
            $post_status = 'pending';
        }

        $kb_settings = [
            'enabled'       => $enabled,
            'daily_count'   => $daily_count,
            'refresh_count' => $refresh_count,
            'run_hour'      => $run_hour,
            'post_status'   => $post_status,
        ];

        // امنیت: get_option خام (نه ACO_Settings::get()) تا کلید API رمزگشایی‌شده
        // دوباره به‌صورت متن ساده ذخیره نشود — رجوع به توضیح مشابه در ajax_save_crawler_settings.
        $all = get_option( 'aco_settings', [] );
        if ( ! is_array( $all ) ) $all = [];
        $all['keyword_bank'] = $kb_settings;
        update_option( 'aco_settings', $all );

        if ( $enabled ) {
            ACO_Keyword_Bank::schedule_cron();
        } else {
            ACO_Keyword_Bank::unschedule_cron();
        }

        $next = wp_next_scheduled( ACO_Keyword_Bank::CRON_HOOK );
        wp_send_json_success( [
            'message'  => 'تنظیمات بانک کلمات کلیدی ذخیره شد.',
            'next_run' => $next ? self::jalali_dt( (string) wp_date( 'Y-m-d H:i:s', (int) $next ) ) : null,
        ] );
    }

    // ─────────────────────────────────────
    // AJAX: حذف یک کلمه کلیدی از مخزن (فقط pending)
    // ─────────────────────────────────────

    public static function ajax_keyword_bank_delete(): void {
        if ( ! check_ajax_referer( 'aco_nonce', 'nonce', false ) ) {
            wp_send_json_error( [ 'message' => 'درخواست نامعتبر.' ] );
            return;
        }
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => 'دسترسی غیرمجاز.' ] );
            return;
        }

        $id = absint( $_POST['id'] ?? 0 );
        if ( ! $id ) {
            wp_send_json_error( [ 'message' => 'شناسه نامعتبر.' ] );
            return;
        }

        if ( ACO_Keyword_Bank::delete_item( $id ) ) {
            wp_send_json_success( [ 'message' => 'حذف شد.' ] );
        } else {
            wp_send_json_error( [ 'message' => 'حذف نشد — ممکن است این کلمه قبلاً به صف تولید فرستاده شده باشد.' ] );
        }
    }

    // ─────────────────────────────────────
    // AJAX: اجرای دستی بانک کلمات کلیدی (بروزرسانی + ارسال به صف)
    // ─────────────────────────────────────

    public static function ajax_keyword_bank_run_now(): void {
        if ( ! check_ajax_referer( 'aco_nonce', 'nonce', false ) ) {
            wp_send_json_error( [ 'message' => 'درخواست نامعتبر.' ] );
            return;
        }
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => 'دسترسی غیرمجاز.' ] );
            return;
        }

        if ( function_exists( 'set_time_limit' ) ) {
            @set_time_limit( 0 );
        }

        try {
            $result = ACO_Keyword_Bank::run_daily( true );
            wp_send_json_success( [
                'message' => sprintf(
                    '✅ %d کلمه جدید کشف شد، %d مقاله به صف تولید اضافه شد.',
                    (int) ( $result['added'] ?? 0 ),
                    (int) ( $result['sent']  ?? 0 )
                ),
            ] );
        } catch ( Throwable $e ) {
            ACO_Logger::error( 'اجرای دستی بانک کلمات کلیدی با خطا مواجه شد: ' . $e->getMessage(), 'keyword_bank' );
            wp_send_json_error( [ 'message' => 'خطا در اجرا: ' . $e->getMessage() ] );
        }
    }

    // ─────────────────────────────────────
    // AJAX: پاک‌سازی گزارش‌های کرم
    // ─────────────────────────────────────

    public static function ajax_crawler_reset_reports(): void {
        if ( ! check_ajax_referer( 'aco_nonce', 'nonce', false ) ) {
            wp_send_json_error( [ 'message' => 'درخواست نامعتبر.' ] );
            return;
        }
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => 'دسترسی غیرمجاز.' ] );
            return;
        }
        ACO_Crawler::reset_reports();
        wp_send_json_success( [ 'message' => 'گزارش‌ها پاک شدند.' ] );
    }

    // ─────────────────────────────────────
    // AJAX: تحلیل بلادرنگ سئو (ACO_Live_Analyzer)
    //
    // این handler هیچ منطق امتیازدهی مستقلی ندارد؛ فقط ورودی خام ادیتور را
    // sanitize می‌کند و مستقیماً به ACO_SEO::run_audit() پاس می‌دهد — همان
    // تابعی که ACO_Generator/ACO_Crawler برای امتیازدهی محتوای واقعی استفاده
    // می‌کنند. هدف: امتیاز نمایش‌داده‌شده حین تایپ همیشه با امتیاز نهایی
    // ذخیره‌شده در _aco_seo_score یکی باشد.
    // ─────────────────────────────────────

    public static function ajax_seo_live_audit(): void {
        if ( ! check_ajax_referer( 'aco_nonce', 'nonce', false ) ) {
            wp_send_json_error( [ 'message' => 'درخواست نامعتبر است. صفحه را رفرش کنید.' ] );
            return;
        }
        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_send_json_error( [ 'message' => 'دسترسی غیرمجاز.' ] );
            return;
        }

        $post_id = absint( $_POST['post_id'] ?? 0 );
        // اگر post_id فرستاده شده (پست موجود، نه پست جدید در حال ساخت)، دسترسی ویرایش همان پست را هم چک کن
        if ( $post_id && ! current_user_can( 'edit_post', $post_id ) ) {
            wp_send_json_error( [ 'message' => 'دسترسی به ویرایش این پست ندارید.' ] );
            return;
        }

        $title            = sanitize_text_field( wp_unslash( $_POST['title'] ?? '' ) );
        // wp_kses_post: محتوای ادیتور شامل HTML مجاز است (h2/h3/img/...)؛ فقط تگ‌های خطرناک حذف می‌شوند
        $content          = wp_kses_post( wp_unslash( $_POST['content'] ?? '' ) );
        $meta_description = sanitize_textarea_field( wp_unslash( $_POST['meta_description'] ?? '' ) );
        $keyword          = sanitize_text_field( wp_unslash( $_POST['keyword'] ?? '' ) );

        $extra = $post_id ? [ 'post_id' => $post_id ] : [];
        $audit = ACO_SEO::run_audit( $title, $content, $meta_description, $keyword, $extra );

        $response = [
            'score'  => $audit['score'],
            'checks' => $audit['checks'],
        ];

        // پیشنهاد متا دیسکریپشن — فقط وقتی کاربر هنوز چیزی وارد نکرده باشد
        // (از تابع موجود ACO_SEO::truncate_meta_description استفاده می‌شود، نه منطق تکراری)
        if ( trim( $meta_description ) === '' && trim( $content ) !== '' ) {
            $response['suggested_meta'] = ACO_SEO::truncate_meta_description( wp_strip_all_tags( $content ) );
        }

        // پیشنهاد لینک داخلی — از تابع موجود ACO_SEO::suggest_internal_links
        // (که با امتیازدهی تطبیق کلیدواژه/عنوان و transient cache کار می‌کند)
        if ( trim( $keyword ) !== '' ) {
            $response['internal_links'] = ACO_SEO::suggest_internal_links( $keyword, $content, $post_id, 5 );
        }

        $niche = (string) ACO_Settings::get( 'niche' );
        if ( $keyword !== '' && ACO_SEO::niche_looks_like_psychology( $niche ) ) {
            $response['clusters'] = ACO_SEO::psychology_keyword_clusters( $keyword );
        }

        wp_send_json_success( $response );
    }

    // ─────────────────────────────────────
    // AJAX: تأیید/رد پیشنهاد بازنویسیِ در انتظار بازبینی (کرم محتوا)
    // ─────────────────────────────────────

    public static function ajax_review_approve(): void {
        if ( ! check_ajax_referer( 'aco_nonce', 'nonce', false ) ) {
            wp_send_json_error( [ 'message' => 'درخواست نامعتبر است. صفحه را رفرش کنید.' ] );
            return;
        }

        $post_id = absint( $_POST['post_id'] ?? 0 );
        if ( ! $post_id || ! get_post( $post_id ) ) {
            wp_send_json_error( [ 'message' => 'پست یافت نشد.' ] );
            return;
        }
        // تأیید انتشار یعنی ویرایش واقعی همان پست — باید دسترسی ویرایش آن را داشته باشد
        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            wp_send_json_error( [ 'message' => 'دسترسی به ویرایش این پست ندارید.' ] );
            return;
        }

        self::extend_time_limit();

        // FIX: اگر ادمین قبل از تأیید متن پیش‌نویس را در textarea ویرایش کرده، همان نسخه منتشر می‌شود
        $edited_content = isset( $_POST['edited_content'] ) ? wp_unslash( $_POST['edited_content'] ) : null;

        $gen    = new ACO_Generator();
        $result = $gen->apply_pending_rewrite( $post_id, $edited_content );

        if ( ! $result['success'] ) {
            wp_send_json_error( [ 'message' => $result['error'] ?? 'خطا در انتشار پیشنهاد.' ] );
            return;
        }

        wp_send_json_success( [
            'message'  => 'بازنویسی تأیید و منتشر شد.',
            'seo_score'=> $result['seo_score'] ?? null,
            'edit_url' => get_edit_post_link( $post_id, 'raw' ),
        ] );
    }

    public static function ajax_review_reject(): void {
        if ( ! check_ajax_referer( 'aco_nonce', 'nonce', false ) ) {
            wp_send_json_error( [ 'message' => 'درخواست نامعتبر است. صفحه را رفرش کنید.' ] );
            return;
        }

        $post_id = absint( $_POST['post_id'] ?? 0 );
        if ( ! $post_id || ! get_post( $post_id ) ) {
            wp_send_json_error( [ 'message' => 'پست یافت نشد.' ] );
            return;
        }
        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            wp_send_json_error( [ 'message' => 'دسترسی به ویرایش این پست ندارید.' ] );
            return;
        }

        $gen = new ACO_Generator();
        $gen->discard_pending_rewrite( $post_id );

        wp_send_json_success( [ 'message' => 'پیشنهاد بازنویسی رد شد. پست بدون تغییر باقی ماند.' ] );
    }
}

