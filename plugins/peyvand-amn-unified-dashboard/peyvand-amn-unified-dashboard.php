<?php
/**
 * Plugin Name: Peyvand Amn Unified Therapist Dashboard
 * Plugin URI: https://peyvandamn.ir
 * Description: داشبورد یکپارچه درمانگر برای اکوسیستم پیوند امن. خواندن و نوشتن فقط از APIهای تأییدشده‌ی افزونه‌های موجود انجام می‌شود و هیچ جدول اختصاصی افزونه‌های دیگر مستقیماً پرس‌وجو نمی‌شود.
 * Version: 2.9.1
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Author: Peyvand Amn
 * Text Domain: peyvand-amn-dashboard
 * Domain Path: /languages
 * License: GPLv2 or later
 */

defined( 'ABSPATH' ) || exit;

define( 'PAS_DASHBOARD_VERSION', '2.9.1' );
define( 'PAS_DASHBOARD_FILE', __FILE__ );
define( 'PAS_DASHBOARD_DIR', plugin_dir_path( __FILE__ ) );
define( 'PAS_DASHBOARD_URL', plugin_dir_url( __FILE__ ) );

/** صفحه‌ی داشبورد (slug) */
define( 'PAS_DASHBOARD_SLUG', 'pas-unified-dashboard' );
/** کپابیلیتی اختصاصی ورود به داشبورد (به administrator داده می‌شود) */
define( 'PAS_DASHBOARD_CAP', 'pas_dashboard_access' );
/** کپابیلیتی مدیریت نوبت‌ها (ثبت/ویرایش/وضعیت/حذف/یادآور) */
define( 'PAS_DASHBOARD_CAP_APPOINTMENTS', 'pas_dashboard_manage_appointments' );
/** کپابیلیتی دعوت/نتیجه/گزارش آزمون */
define( 'PAS_DASHBOARD_CAP_ASSESSMENTS', 'pas_dashboard_manage_assessments' );
/** کپابیلیتی پاسخ به پیام مراجعان */
define( 'PAS_DASHBOARD_CAP_MESSAGES', 'pas_dashboard_send_messages' );
/** کپابیلیتی پیگیری لید و مدیریت نظرسنجی */
define( 'PAS_DASHBOARD_CAP_FOLLOWUP', 'pas_dashboard_manage_followup' );
/** کپابیلیتی مدیریت یکپارچگی‌ها: تست ایمیل و تلاش دوبارهٔ صف SMTP */
define( 'PAS_DASHBOARD_CAP_ECOSYSTEM', 'pas_dashboard_manage_ecosystem' );
/** نام آپشن تنظیمات داشبورد */
define( 'PAS_DASHBOARD_OPTION', 'pas_dashboard_settings' );
/** نسخه‌ی شمای جداول اختصاصی داشبورد */
define( 'PAS_DASHBOARD_DB_VERSION', '1.0.0' );
/** حداکثر ردیف در هر صفحه‌ی لیست */
define( 'PAS_DASHBOARD_MAX_PER_PAGE', 100 );

require_once PAS_DASHBOARD_DIR . 'includes/class-dashboard-loader.php';

register_activation_hook( __FILE__, array( 'PAS_Dashboard_Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'PAS_Dashboard_Plugin', 'deactivate' ) );

add_action( 'plugins_loaded', array( 'PAS_Dashboard_Plugin', 'instance' ), 20 );
