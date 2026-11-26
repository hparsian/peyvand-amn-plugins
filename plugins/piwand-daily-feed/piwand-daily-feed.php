<?php
/**
 * Plugin Name: پیوند امن — فید محتوای روزانه (Pivand Daily Feed)
 * Plugin URI:  https://piwandsafe.com
 * Description: فید روزانه محتوای روانشناسی برای سایت پیوند امن
 * Version:     1.1.6
 * Requires at least: 6.6
 * Tested up to: 7.1
 * Requires PHP: 7.4
 * Domain Path: /languages
 * Author: Hadi Parsaeian
 * Author URI: mailto:h.parsian1986@gmail.com
 * Text Domain: piwand-daily-feed
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

defined( 'ABSPATH' ) || exit;

define( 'PWDF_VERSION',  '1.1.6' );
define( 'PWDF_DIR',      plugin_dir_path( __FILE__ ) );
define( 'PWDF_URL',      plugin_dir_url( __FILE__ ) );
define( 'PWDF_BASENAME', plugin_basename( __FILE__ ) );

require_once PWDF_DIR . 'includes/class-post-type.php';
require_once PWDF_DIR . 'includes/class-settings.php';
require_once PWDF_DIR . 'includes/class-widget.php';
require_once PWDF_DIR . 'includes/class-shortcode.php';
require_once PWDF_DIR . 'includes/class-ajax.php';
require_once PWDF_DIR . 'includes/class-daily-generator.php';

add_action( 'plugins_loaded', 'pwdf_init' );
function pwdf_init() {
    PWDF_Post_Type::init();
    PWDF_Settings::init();
    PWDF_Widget::init();
    PWDF_Shortcode::init();
    PWDF_Ajax::init();
    PWDF_Daily_Generator::init(); // 1.1.0: تولید خودکار روزانه (متن/کتاب) — همیشه به‌صورت پیش‌نویس، منتظر تایید ادمین
}

register_activation_hook( __FILE__, 'pwdf_activate' );
function pwdf_activate() {
    PWDF_Post_Type::register();
    flush_rewrite_rules();

    if ( get_option( 'pwdf_settings' ) === false ) {
        update_option( 'pwdf_settings', PWDF_Settings::defaults() );
    }
}

register_deactivation_hook( __FILE__, function() {
    flush_rewrite_rules();

    // پاک‌سازی cron تولید خودکار هنگام غیرفعال‌سازی افزونه، تا رویداد یتیم روی سایت نماند
    $next = wp_next_scheduled( PWDF_Daily_Generator::CRON_HOOK );
    if ( $next ) {
        wp_unschedule_event( $next, PWDF_Daily_Generator::CRON_HOOK );
    }
} );
