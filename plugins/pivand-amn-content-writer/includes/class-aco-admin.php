<?php
if ( ! defined( 'ABSPATH' ) ) exit;

require_once __DIR__ . '/trait-aco-admin-pages.php';
require_once __DIR__ . '/trait-aco-admin-ajax.php';

/**
 * ACO_Admin — bootstrap ادمین
 * ARCH-1: منطق render به trait-aco-admin-pages و AJAX به trait-aco-admin-ajax منتقل شد.
 * این کلاس فقط: init، register_menu، enqueue_assets، extend_time_limit را نگه می‌دارد.
 */
class ACO_Admin {
    use ACO_Admin_Pages;
    use ACO_Admin_Ajax;

    public static function init(): void {
        ACO_SEO::inject_schema_hooks();
        ACO_Live_Analyzer::init();
        add_action( 'admin_menu',        [ __CLASS__, 'register_menu' ] );
        add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_assets' ] );
        add_action( 'wp_ajax_aco_research',      [ __CLASS__, 'ajax_research' ] );
        add_action( 'wp_ajax_aco_rewrite',        [ __CLASS__, 'ajax_rewrite' ] );
        add_action( 'wp_ajax_aco_write_save',    [ __CLASS__, 'ajax_write_save' ] );
        add_action( 'wp_ajax_aco_save_settings', [ __CLASS__, 'ajax_save_settings' ] );
        add_action( 'wp_ajax_aco_test_api',      [ __CLASS__, 'ajax_test_api' ] );
        add_action( 'wp_ajax_aco_queue_add',        [ __CLASS__, 'ajax_queue_add' ] );
        add_action( 'wp_ajax_aco_queue_bulk_add',   [ __CLASS__, 'ajax_queue_bulk_add' ] );
        add_action( 'wp_ajax_aco_queue_rewrite',      [ __CLASS__, 'ajax_queue_rewrite' ] );
        add_action( 'wp_ajax_aco_queue_bulk_rewrite', [ __CLASS__, 'ajax_queue_bulk_rewrite' ] );
        add_action( 'wp_ajax_aco_queue_action',  [ __CLASS__, 'ajax_queue_action' ] );
        add_action( 'wp_ajax_aco_logs_clear',    [ __CLASS__, 'ajax_logs_clear' ] );
        add_action( 'wp_ajax_aco_save_crawler_settings',  [ __CLASS__, 'ajax_save_crawler_settings' ] );
        add_action( 'wp_ajax_aco_crawler_run_now',        [ __CLASS__, 'ajax_crawler_run_now' ] );
        add_action( 'wp_ajax_aco_crawler_reset_reports',  [ __CLASS__, 'ajax_crawler_reset_reports' ] );
        add_action( 'wp_ajax_aco_save_keyword_bank_settings', [ __CLASS__, 'ajax_save_keyword_bank_settings' ] );
        add_action( 'wp_ajax_aco_keyword_bank_run_now',       [ __CLASS__, 'ajax_keyword_bank_run_now' ] );
        add_action( 'wp_ajax_aco_keyword_bank_delete',        [ __CLASS__, 'ajax_keyword_bank_delete' ] );
        add_action( 'wp_ajax_aco_test_gsc_connection',        [ __CLASS__, 'ajax_test_gsc_connection' ] );
        add_action( 'wp_ajax_aco_seo_live_audit',         [ __CLASS__, 'ajax_seo_live_audit' ] );
        add_action( 'wp_ajax_aco_review_approve',         [ __CLASS__, 'ajax_review_approve' ] );
        add_action( 'wp_ajax_aco_review_reject',          [ __CLASS__, 'ajax_review_reject' ] );

        // FIX: ajax_queue_rewrite (تک‌پستی، برخلاف نسخه‌ی bulk) کاملاً امن و
        // کامل پیاده‌سازی شده بود ولی هیچ دکمه‌ای تو رابط کاربری صداش نمی‌زد —
        // احتمالاً از refactor نسخه‌ی bulk (v1.9.0) جا مونده. به‌جای حذفش،
        // یک دکمه‌ی «بهبود با AI» تو ردیف عملیات لیست نوشته‌ها اضافه شد تا
        // بشه بدون رفتن به صفحه‌ی صف، تک‌تک مقاله‌ها رو مستقیم بهبود داد.
        add_filter( 'post_row_actions', [ __CLASS__, 'add_improve_row_action' ], 10, 2 );
        add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_row_action_script' ] );
    }

    /**
     * دکمه‌ی «بهبود با AI» در ردیف عملیات هر نوشته (فقط برای کاربرانی که
     * اجازه‌ی ویرایش همان پست را دارند، و فقط برای پست‌های منتشرشده/پیش‌نویس
     * — نه پست‌هایی که همین الان در صف پردازش‌اند).
     *
     * @param array<string,string> $actions
     */
    public static function add_improve_row_action( array $actions, \WP_Post $post ): array {
        if ( $post->post_type !== 'post' || ! current_user_can( 'edit_post', $post->ID ) ) {
            return $actions;
        }

        $actions['aco_improve'] = sprintf(
            '<a href="#" class="aco-improve-row-action" data-post-id="%d">%s</a>',
            $post->ID,
            esc_html__( 'بهبود با AI', 'pivand-amn-content-writer' )
        );

        return $actions;
    }

    /**
     * اسکریپت سبک مخصوص صفحه‌ی لیست نوشته‌ها — جدا از aco-admin.js چون آن
     * فقط روی صفحات خودِ ACO لود می‌شود (enqueue_assets)، نه edit.php.
     */
    public static function enqueue_row_action_script( string $hook ): void {
        if ( $hook !== 'edit.php' || ( $_GET['post_type'] ?? 'post' ) !== 'post' ) {
            return;
        }

        wp_enqueue_script( 'jquery' );
        wp_add_inline_script( 'jquery', "
            jQuery(function($){
                $(document).on('click', '.aco-improve-row-action', function(e){
                    e.preventDefault();
                    var \$link = $(this);
                    var postId = \$link.data('post-id');
                    if (\$link.data('busy')) { return; }
                    \$link.data('busy', true);
                    var originalText = \$link.text();
                    \$link.text('" . esc_js( __( 'در حال افزودن به صف...', 'pivand-amn-content-writer' ) ) . "');

                    $.post(ajaxurl, {
                        action: 'aco_queue_rewrite',
                        nonce: '" . esc_js( wp_create_nonce( 'aco_nonce' ) ) . "',
                        post_id: postId
                    }).done(function(res){
                        \$link.text(res && res.success ? '" . esc_js( __( '✅ به صف اضافه شد', 'pivand-amn-content-writer' ) ) . "' : '" . esc_js( __( '❌ خطا', 'pivand-amn-content-writer' ) ) . "');
                    }).fail(function(){
                        \$link.text('" . esc_js( __( '❌ خطای ارتباط', 'pivand-amn-content-writer' ) ) . "');
                    }).always(function(){
                        \$link.data('busy', false);
                    });
                });
            });
        " );
    }

    // ─────────────────────────────────────
    // منو
    // ─────────────────────────────────────

    public static function register_menu(): void {
        add_menu_page(
            'نویسنده هوشمند',
            'نویسنده هوشمند',
            'edit_posts',
            'aco-writer',
            [ __CLASS__, 'page_generator' ],
            'dashicons-edit-large',
            25
        );

        add_submenu_page(
            'aco-writer',
            'تولید محتوا',
            'تولید محتوا',
            'edit_posts',
            'aco-writer',
            [ __CLASS__, 'page_generator' ]
        );

        add_submenu_page(
            'aco-writer',
            'بهبود مقاله',
            'بهبود مقاله',
            'edit_posts',
            'aco-rewrite',
            [ __CLASS__, 'page_rewrite' ]
        );

        add_submenu_page(
            'aco-writer',
            'تاریخچه مقالات',
            'تاریخچه',
            'edit_posts',
            'aco-history',
            [ __CLASS__, 'page_history' ]
        );

        add_submenu_page(
            'aco-writer',
            'تنظیمات API',
            'تنظیمات API',
            'manage_options',
            'aco-settings',
            [ __CLASS__, 'page_settings' ]
        );

        add_submenu_page(
            'aco-writer',
            'صف پردازش',
            '📋 صف تولید',
            'edit_posts',
            'aco-queue',
            [ __CLASS__, 'page_queue' ]
        );

        add_submenu_page(
            'aco-writer',
            'لاگ رویدادها',
            '📜 لاگ',
            'manage_options',
            'aco-logs',
            [ __CLASS__, 'page_logs' ]
        );

        add_submenu_page(
            'aco-writer',
            'آمار توکن',
            '📊 توکن',
            'manage_options',
            'aco-tokens',
            [ __CLASS__, 'page_tokens' ]
        );

        add_submenu_page(
            'aco-writer',
            'کرم محتوا',
            '🐛 کرم محتوا',
            'manage_options',
            'aco-crawler',
            [ __CLASS__, 'page_crawler' ]
        );

        add_submenu_page(
            'aco-writer',
            'بانک کلمات کلیدی',
            '🔑 بانک کلمات کلیدی',
            'manage_options',
            'aco-keyword-bank',
            [ __CLASS__, 'page_keyword_bank' ]
        );

        add_submenu_page(
            'aco-writer',
            'گزارش به‌روزرسانی',
            '📋 گزارش به‌روزرسانی',
            'manage_options',
            'aco-crawler-report',
            [ __CLASS__, 'page_crawler_report' ]
        );
    }

    // ─────────────────────────────────────
    // استایل و اسکریپت
    // ─────────────────────────────────────

    public static function enqueue_assets( string $hook ): void {
        // FIX: به جای whitelist دقیق، بررسی می‌کنیم hook شامل slug صفحات ACO باشد
        // چون WordPress بسته به capability و ساختار منو، hook name را متفاوت تولید می‌کند
        $is_aco_page = strpos( $hook, 'aco-writer' ) !== false
                    || strpos( $hook, 'aco-settings' ) !== false
                    || strpos( $hook, 'aco-rewrite' ) !== false
                    || strpos( $hook, 'aco-history' ) !== false
                    || strpos( $hook, 'aco-queue' ) !== false
                    || strpos( $hook, 'aco-logs' ) !== false
                    || strpos( $hook, 'aco-tokens' ) !== false
                    || strpos( $hook, 'aco-crawler' ) !== false
                    || strpos( $hook, 'aco-keyword-bank' ) !== false;
        if ( ! $is_aco_page ) {
            return;
        }

        wp_enqueue_style(
            'aco-admin',
            ACO_PLUGIN_URL . 'assets/admin.css',
            class_exists( 'Pas_Core_Tokens' ) ? [ Pas_Core_Tokens::handle() ] : [],
            ACO_VERSION
        );

        wp_enqueue_script(
            'aco-admin',
            ACO_PLUGIN_URL . 'assets/admin.js',
            [ 'jquery' ],
            ACO_VERSION,
            true
        );

        // WP 7.0 COMPAT: جلوگیری از لود شدن WP AI Client و Connectors API روی صفحات ACO
        // WP 7.0 این scriptها را روی همه صفحات ادمین inject می‌کند ولی چون صفحات ACO
        // block editor ندارند، خطای "Angie is not available" / "waitForReady" می‌دهند
        // و jQuery event listener‌های ما را block می‌کنند.
        add_action( 'admin_print_scripts', static function() {
            // WP 6.7+ / 7.0: editor MCP و AI assistant
            wp_dequeue_script( 'wp-editor-mcp' );
            wp_dequeue_script( 'editor-mcp' );
            wp_dequeue_script( 'wp-ai-assistant' );
            // WP 7.0: WP AI Client SDK و Connectors API
            wp_dequeue_script( 'wp-ai-client' );
            wp_dequeue_script( 'wp-connectors' );
            wp_dequeue_script( 'wp-abilities' );
            // WP 7.0: View Transitions — روی صفحات custom ممکن است مشکل ایجاد کند
            wp_dequeue_script( 'wp-view-transitions' );
            // deregister برای جلوگیری از re-enqueue توسط dependency‌ها
            wp_deregister_script( 'wp-editor-mcp' );
            wp_deregister_script( 'editor-mcp' );
            wp_deregister_script( 'wp-ai-client' );
            wp_deregister_script( 'wp-connectors' );
            wp_deregister_script( 'wp-abilities' );
        }, 99 );

        wp_localize_script( 'aco-admin', 'ACO', [
            'ajax_url'  => admin_url( 'admin-ajax.php' ),
            'nonce'     => wp_create_nonce( 'aco_nonce' ),
            'admin_url' => admin_url( 'post.php' ),
        ] );
    }

    // ─────────────────────────────────────
    // FIX #11: Helper مشترک — جلوگیری از قطع شدن توسط هاست
    // ─────────────────────────────────────

    private static function extend_time_limit(): void {
        // بدترین حالت: (MAX_RETRIES+1) × TIMEOUT_WRITE + RETRY_DELAY = (1+1)×150 + 1 = 301 ثانیه
        if ( function_exists( 'set_time_limit' ) ) {
            @set_time_limit( 310 );
        }
    }

    // ─────────────────────────────────────
    // صفحه تولید محتوا
    // ─────────────────────────────────────

}
