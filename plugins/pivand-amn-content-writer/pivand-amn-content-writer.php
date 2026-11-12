<?php
/**
 * Plugin Name: پیوند امن — تولید محتوا با هوش مصنوعی
 * Description: سیستم تولید محتوای هوشمند با هوش مصنوعی — پشتیبانی از OpenRouter و DeepSeek. شامل تحلیل بلادرنگ سئو (ادغام‌شده از Peyvand Amn SEO Assistant Pro)، تولید خودکار تصویر شاخص، بانک کلمات کلیدی روزانه، و اتصال اختیاری به Google Search Console.
 * Version: 2.9.17
 * Requires at least: 6.6
 * Requires PHP: 7.4
 * Domain Path: /languages
 * Tested up to: 7.1
 * Author: Hadi.parsaeian
 * Text Domain: pivand-amn-content-writer
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// حداقل PHP 7.4 مورد نیاز است
if ( version_compare( PHP_VERSION, '7.4', '<' ) ) {
    add_action( 'admin_notices', function() {
        echo '<div class="notice notice-error"><p><strong>ACO Content Writer:</strong> نیازمند PHP 7.4 یا بالاتر است. نسخه فعلی: ' . esc_html( PHP_VERSION ) . '</p></div>';
    } );
    return;
}

// WP 7.0 COMPAT: جلوگیری از تداخل با WP AI Client داخلی وردپرس
add_filter( 'wp_ai_client_enabled', '__return_false', 5 );

define( 'ACO_VERSION',    '2.9.17' );
define( 'ACO_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'ACO_PLUGIN_URL', plugin_dir_url( __FILE__ ) );


// PHP 7.4 COMPAT: str_contains از PHP 8.0 است — polyfill برای PHP 7.4
if ( ! function_exists( 'str_contains' ) ) {
    function str_contains( string $haystack, string $needle ): bool {
        return $needle === '' || strpos( $haystack, $needle ) !== false;
    }
}

require_once ACO_PLUGIN_DIR . 'includes/class-aco-settings.php';
require_once ACO_PLUGIN_DIR . 'includes/class-aco-logger.php';
require_once ACO_PLUGIN_DIR . 'includes/class-aco-queue.php';
require_once ACO_PLUGIN_DIR . 'includes/class-aco-ai-router.php';
require_once ACO_PLUGIN_DIR . 'includes/class-aco-seo.php';
require_once ACO_PLUGIN_DIR . 'includes/class-aco-gutenberg.php';
require_once ACO_PLUGIN_DIR . 'includes/class-aco-generator.php';
require_once ACO_PLUGIN_DIR . 'includes/class-aco-crawler.php';
require_once ACO_PLUGIN_DIR . 'includes/class-aco-live-analyzer.php';
require_once ACO_PLUGIN_DIR . 'includes/class-aco-content-optimizer.php';
require_once ACO_PLUGIN_DIR . 'includes/class-aco-image-generator.php';
require_once ACO_PLUGIN_DIR . 'includes/class-aco-keyword-bank.php';
require_once ACO_PLUGIN_DIR . 'includes/class-aco-search-console.php';
require_once ACO_PLUGIN_DIR . 'includes/class-aco-admin.php';

function aco_init() {
    ACO_Settings::init();
    ACO_Queue::register_cron_interval();
    ACO_Admin::init();
    ACO_Content_Optimizer::init();
    add_action( ACO_Queue::CRON_HOOK, [ 'ACO_Queue', 'process_next' ] );
    add_action( ACO_Crawler::CRON_HOOK, [ 'ACO_Crawler', 'run' ] );
    add_action( ACO_Keyword_Bank::CRON_HOOK, [ 'ACO_Keyword_Bank', 'run_daily' ] );

    // ISSUE-4 FIX: وقتی پست حذف می‌شود، transient پیشنهاد لینک داخلی را invalidate کن
    // بدون این، suggest_internal_links تا ۱۵ دقیقه پست حذف‌شده را پیشنهاد می‌داد.
    add_action( 'delete_post', function() {
        delete_transient( 'aco_published_ids' );
    } );
}
add_action( 'plugins_loaded', 'aco_init' );

// آپگرید جدول صف برای نصب‌های قبلی
add_action( 'plugins_loaded', function() {
    $db_version = get_option( 'aco_db_version', '0' );
    // نکته‌ی مهم: نسخه‌ی هدف از '1.9.8' به '2.2.2' تغییر کرد. اگر همون
    // '1.9.8' می‌موند، این بلوک هیچ‌وقت روی سایتی که از قبل به 1.9.8 رسیده
    // دوباره اجرا نمی‌شد — یعنی رفع باگ dbDelta (حذف IF NOT EXISTS و اصلاح
    // فاصله‌ی PRIMARY KEY) هیچ‌وقت به سایت‌های واقعی نمی‌رسید، فقط رو
    // نصب‌های کاملاً تازه اثر می‌کرد. dbDelta روی جدول از قبل موجود با شِمای
    // یکسان کاملاً بی‌خطر و idempotent است — داده‌ای پاک نمی‌شود.
    if ( version_compare( $db_version, '2.2.2', '<' ) ) {
        ACO_Queue::create_table();
        ACO_Logger::create_table();
        update_option( 'aco_db_version', '2.2.2' );
    }

    // بانک کلمات کلیدی — برای نصب‌های موجود که با آپلود zip آپدیت می‌شوند
    // (register_activation_hook دوباره اجرا نمی‌شود، پس اینجا هم باید ساخته شود)
    if ( version_compare( $db_version, '2.5.0', '<' ) ) {
        ACO_Keyword_Bank::create_table();
        $kb_settings = ACO_Settings::get( 'keyword_bank' );
        $kb_enabled  = ! is_array( $kb_settings ) || ( $kb_settings['enabled'] ?? true );
        if ( $kb_enabled && ! wp_next_scheduled( ACO_Keyword_Bank::CRON_HOOK ) ) {
            ACO_Keyword_Bank::schedule_cron();
        }
        update_option( 'aco_db_version', '2.5.0' );
    }

    // ستون‌های جدید Search Console (source/clicks/impressions/gsc_position) — dbDelta ستون اضافه می‌کند
    if ( version_compare( $db_version, '2.9.0', '<' ) ) {
        ACO_Keyword_Bank::create_table();
        update_option( 'aco_db_version', '2.9.0' );
    }
}, 20 );

register_activation_hook( __FILE__, function() {
    $defaults = [
        'provider'         => 'openrouter',
        'openrouter_key'   => '',
        'deepseek_key'     => '',
        'openrouter_model' => 'google/gemma-4-31b-it:free',
        'deepseek_model'   => 'deepseek-chat',
        'default_status'   => 'draft',
        'default_author'   => 1,
        'niche'            => '',
        'tone'             => 'حرفه‌ای، قابل اعتماد و کاربردی',
        'min_words'        => 1200,
    ];
    // BUG-01 FIX: ابتدا تنظیمات موجود را بخوان، سپس با defaults merge کن و ذخیره کن.
    // در نصب اول: get_option = [] → wp_parse_args([], $defaults) = $defaults → ذخیره صحیح.
    // در reactivation: get_option = مقادیر قدیمی → کلیدهای جدید از $defaults اضافه می‌شوند.
    $existing = get_option( 'aco_settings', [] );
    update_option( 'aco_settings', wp_parse_args( $existing, $defaults ) );

    ACO_Logger::create_table();
    ACO_Queue::create_table();
    ACO_Keyword_Bank::create_table();

    ACO_Queue::register_cron_interval();
    ACO_Queue::schedule_cron();
    ACO_Crawler::schedule_cron();
    ACO_Keyword_Bank::schedule_cron();
} );

register_deactivation_hook( __FILE__, function() {
    delete_transient( 'aco_generated_count' );
    delete_transient( 'aco_published_ids' );

    ACO_Queue::unschedule_cron();
    ACO_Crawler::unschedule_cron();
    ACO_Keyword_Bank::unschedule_cron();

    global $wpdb;
    // BUG-MAIN-2 FIX: از $wpdb->prepare + $wpdb->esc_like استفاده می‌کنیم تا:
    // ۱) underscore در '_transient_aco_lock_' به درستی escape شود (wildcard MySQL)
    // ۲) فقط transient‌های lock حذف شوند — نه aco_research_ یا سایر transient‌های ACO
    // قبلاً raw SQL بدون prepare بود که هم ناامن بود و هم _ را escape نمی‌کرد.
    $lock_pattern         = $wpdb->esc_like( '_transient_aco_lock_' ) . '%';
    $lock_timeout_pattern = $wpdb->esc_like( '_transient_timeout_aco_lock_' ) . '%';
    $wpdb->query( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
        $lock_pattern,
        $lock_timeout_pattern
    ) );
} );
