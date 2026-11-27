<?php
/**
 * Plugin Name: Gemini Site Operator — اپراتور هوشمند کل سایت
 * Description: اپراتور هوشمند و ناظر دائمی وردپرس: ممیزی کل سایت، صف اولویت‌بندی، اصلاح گام‌به‌گام یا دسته‌ای با پیش‌نمایش، Snapshot و بازگشت، تولید محتوا/متا/Alt/CSS و پایش رویدادمحور و دوره‌ای — متصل مستقیم و امن به Gemini API با کلید شخصی.
 * Version: 1.6.0
 * Requires at least: 6.6
 * Requires PHP: 8.1
 * Tested up to: 7.1
 * Update URI: https://github.com/hparsian/peyvand-amn-plugins
 * Author: Hadi Parsaeian
 * Author URI: mailto:h.parsian1986@gmail.com
 * Text Domain: gemini-site-operator
 * Domain Path: /languages
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 *
 * ساختار:
 *   includes/class-gso-settings.php    تنظیمات، رمزنگاری کلید API
 *   includes/class-gso-install.php     اسکیمای دیتابیس، فعال/غیرفعال‌سازی، کران
 *   includes/class-gso-logger.php      گزارش عملیات
 *   includes/class-gso-limiter.php     محدودیت نرخ، سهمیه روزانه و Backoff
 *   includes/class-gso-contract.php    قرارداد و اسکیمای JSON خروجی Gemini
 *   includes/class-gso-gemini.php      کلاینت Gemini (wp_remote_post، JSON، سوئیچ مدل)
 *   includes/class-gso-seo.php         پل فیلدهای متای افزونه‌های سئو
 *   includes/class-gso-issues.php      CRUD نواقص، امتیاز سلامت، آمار، صف
 *   includes/class-gso-snapshot.php    ثبت نسخه و بازگشت
 *   includes/class-gso-auditor.php     موتور ممیزی دسته‌ای کل سایت
 *   includes/class-gso-generator.php   تولید محتوا، متا، Alt، لینک داخلی، CSS
 *   includes/class-gso-fixer.php       اعمال اصلاح‌ها (هندلرها)
 *   includes/class-gso-queue.php       صف پس‌زمینه (Action Scheduler / WP-Cron)
 *   includes/class-gso-watcher.php     پایش رویدادمحور و زمان‌بندی‌شده
 *   includes/class-gso-updater.php     به‌روزرسانی خودکار از مخزن GitHub
 *   includes/class-gso-admin.php       منو، دارایی‌ها، فرم‌های admin-post
 *   includes/trait-gso-admin-pages.php صفحات پیشخوان
 *   includes/trait-gso-admin-ajax.php  هندلرهای AJAX
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'GSO_VERSION', '1.6.0' );
define( 'GSO_FILE', __FILE__ );
define( 'GSO_DIR', plugin_dir_path( __FILE__ ) );
define( 'GSO_URL', plugin_dir_url( __FILE__ ) );
define( 'GSO_DB_VERSION', '1.0.0' );

if ( version_compare( PHP_VERSION, '8.1', '<' ) ) {
	add_action(
		'admin_notices',
		static function () {
			echo '<div class="notice notice-error"><p><strong>Gemini Site Operator:</strong> نیازمند PHP 8.1 یا بالاتر است. نسخه فعلی: ' . esc_html( PHP_VERSION ) . '</p></div>';
		}
	);
	return;
}

require_once GSO_DIR . 'includes/class-gso-settings.php';
require_once GSO_DIR . 'includes/class-gso-install.php';
require_once GSO_DIR . 'includes/class-gso-logger.php';
require_once GSO_DIR . 'includes/class-gso-limiter.php';
require_once GSO_DIR . 'includes/class-gso-contract.php';
require_once GSO_DIR . 'includes/class-gso-gemini.php';
require_once GSO_DIR . 'includes/class-gso-seo.php';
require_once GSO_DIR . 'includes/class-gso-issues.php';
require_once GSO_DIR . 'includes/class-gso-snapshot.php';
require_once GSO_DIR . 'includes/class-gso-auditor.php';
require_once GSO_DIR . 'includes/class-gso-generator.php';
require_once GSO_DIR . 'includes/class-gso-fixer.php';
require_once GSO_DIR . 'includes/class-gso-queue.php';
require_once GSO_DIR . 'includes/class-gso-watcher.php';
require_once GSO_DIR . 'includes/class-gso-updater.php';
require_once GSO_DIR . 'includes/trait-gso-admin-pages.php';
require_once GSO_DIR . 'includes/trait-gso-admin-ajax.php';
require_once GSO_DIR . 'includes/class-gso-admin.php';

// بازه کران اختصاصی باید پیش از زمان‌بندی فعال‌سازی ثبت شود.
add_filter( 'cron_schedules', array( 'GSO_Queue', 'schedules' ) );

/**
 * راه‌اندازی افزونه پس از بارگذاری همه افزونه‌ها.
 */
function gso_bootstrap(): void {
	load_plugin_textdomain( 'gemini-site-operator', false, dirname( plugin_basename( GSO_FILE ) ) . '/languages' );

	GSO_Install::maybe_upgrade();
	GSO_Admin::init();
	GSO_Queue::init();
	GSO_Watcher::init();
	GSO_Updater::init();
}
add_action( 'plugins_loaded', 'gso_bootstrap', 5 );

register_activation_hook( __FILE__, array( 'GSO_Install', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'GSO_Install', 'deactivate' ) );
