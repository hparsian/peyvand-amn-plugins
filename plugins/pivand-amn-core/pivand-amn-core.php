<?php
/**
 * Plugin Name: پیوند امن — هسته مشترک
 * Description: زیرساخت مشترک اکوسیستم پیوند امن — توابع تکراری (نرمال‌سازی موبایل، تاریخ شمسی، IP)، بررسی وابستگی بین افزونه‌ها با هشدار واضح در پیشخوان، و رجیستری مستندشده‌ی همه‌ی قراردادهای فیلتری بین‌افزونه‌ای. این افزونه باید همیشه فعال باشد و باید قبل از بقیه‌ی افزونه‌های خانواده لود شود.
 * Version: 1.9.1
 * Requires at least: 6.6
 * Tested up to: 7.1
 * Author: Hadi.parsaeian
 * Text Domain: pivand-amn-core
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Requires PHP: 8.1
 * Domain Path: /languages
 *
 * ==============================================================================
 * چرا این افزونه لازم شد؟
 * با رشد اکوسیستم (client-portal، quiz-enterprise، sms-bridge، smtp-mailer،
 * content-writer، daily-feed، payvand-contact، case-file) چند بی‌نظمی جمع شده
 * بود:
 *   ۱. هفت پیشوند جدول متفاوت (zdp_, psy_, pas_, zsm_, aco_, pwdf_, payvand)
 *      بدون قاعده‌ی مشترک.
 *   ۲. نرمال‌سازی شماره موبایل و گرفتن IP واقعی مراجع، هر افزونه نسخه‌ی
 *      خودش را جدا نوشته بود.
 *   ۳. اگر یک افزونه‌ی وابسته (مثلاً sms-bridge) غیرفعال می‌شد، افزونه‌ی
 *      وابسته به آن (client-portal) بی‌صدا از کار می‌افتاد — apply_filters
 *      بدون پیاده‌سازی، فقط مقدار پیش‌فرض خالی برمی‌گرداند، بدون هیچ هشدار.
 *   ۴. قراردادهای فیلتری (pas_send_sms، psy_get_client_name_for_phone، ...)
 *      فقط در کامنت پراکنده‌ی هر پلاگین مستند بودند، نه یک‌جا.
 *
 * این افزونه این چهار مورد را حل می‌کند. تغییری در افزونه‌های موجود لازم
 * نیست — استفاده از این هسته تدریجی و اختیاری است (هر افزونه هروقت آماده
 * بود، به‌جای کد تکراری خودش، از Pas_Core_Helpers استفاده می‌کند).
 * ==============================================================================
 */

if (!defined('ABSPATH')) {
    exit;
}

define('PAS_CORE_VERSION', '1.9.1');
define('PAS_CORE_DIR', plugin_dir_path(__FILE__));
define('PAS_CORE_URL', plugin_dir_url(__FILE__));

require_once PAS_CORE_DIR . 'includes/class-helpers.php';
require_once PAS_CORE_DIR . 'includes/class-crypto.php';
require_once PAS_CORE_DIR . 'includes/class-data-audit.php';
require_once PAS_CORE_DIR . 'includes/class-client-registry.php';
require_once PAS_CORE_DIR . 'includes/class-field-mapper.php';
require_once PAS_CORE_DIR . 'includes/class-compliance.php';
require_once PAS_CORE_DIR . 'includes/class-health.php';
require_once PAS_CORE_DIR . 'includes/class-fonts.php';
require_once PAS_CORE_DIR . 'includes/class-tokens.php';
require_once PAS_CORE_DIR . 'includes/class-jalali.php';
require_once PAS_CORE_DIR . 'includes/class-dependency-checker.php';
require_once PAS_CORE_DIR . 'includes/class-filter-registry.php';
require_once PAS_CORE_DIR . 'includes/class-table-registry.php';
require_once PAS_CORE_DIR . 'includes/class-capability-registry.php';
require_once PAS_CORE_DIR . 'includes/class-prefix-map.php';
require_once PAS_CORE_DIR . 'includes/class-admin.php';
require_once PAS_CORE_DIR . 'includes/class-center.php';

/**
 * موقع فعال‌سازی، اگر کلید رمزنگاری از قبل جایی (wp-config.php یا فایل
 * قبلی) تنظیم نشده باشد، خودش یک کلید می‌سازد و در
 * wp-content/pas-encryption-key.php می‌نویسد — نیازی به FTP دستی نیست.
 */
register_activation_hook(__FILE__, function (): void {
    Pas_Core_Crypto::ensure_key_file();
    // فاز ۱: جداول رجیستری هویت (افزایشی؛ هیچ جدول موجودی تغییر نمی‌کند).
    Pas_Core_Client_Registry::install();
    // فاز ۲: جداول نگاشت فیلد و ثبت منبع (افزایشی).
    Pas_Core_Field_Mapper::install();
});

/**
 * اگر نسخه‌ی دیتابیس هسته با کد فرق کند (مثلاً فایل‌ها به‌روزرسانی شده ولی
 * افزونه غیرفعال/فعال نشده باشد)، جداول افزایشی یک‌بار ساخته/آپدیت می‌شوند.
 * dbDelta idempotent است و هیچ ستون/داده‌ی موجودی را حذف نمی‌کند.
 */
add_action('plugins_loaded', function (): void {
    if (get_option('pas_mapper_db_version') !== Pas_Core_Field_Mapper::DB_VERSION) {
        Pas_Core_Field_Mapper::install();
    }
}, 4);

add_action('plugins_loaded', function (): void {
    if (get_option('pas_core_db_version') !== Pas_Core_Client_Registry::DB_VERSION) {
        Pas_Core_Client_Registry::install();
    }
}, 4);

add_action('plugins_loaded', function (): void {
    load_plugin_textdomain('pivand-amn-core');
    new Pas_Core_Admin();

    /**
     * قرارداد هویت (فاز ۱): apply_filters('pas_get_client', null, $query)
     * فقط‌خواندنی؛ هیچ داده‌ی بالینی برنمی‌گرداند. برای مصرف‌کننده‌های آینده
     * (پرونده، آزمون، داشبورد) تا بدون کوئری مستقیم به جداول یکدیگر، هویت
     * واحد مراجع را بگیرند.
     */
    add_filter('pas_get_client', ['Pas_Core_Client_Registry', 'filter_get_client'], 10, 2);

    /**
     * ثبت وابستگی‌های شناخته‌شده‌ی اکوسیستم. هر افزونه‌ای که خودش این
     * هسته را می‌شناسد می‌تواند با همین متد وابستگی‌اش را هم ثبت کند —
     * این‌جا فقط وابستگی‌های شناخته‌شده‌ی فعلی به‌عنوان نمونه ثبت شده‌اند
     * تا از روز اول چیزی برای دیدن در پیشخوان باشد.
     */
    Pas_Core_Dependency_Checker::register(
        'پنل مراجعان',
        'pivand-amn-client-portal/pivand-amn-client-portal.php',
        ['پل پیامک (sms-bridge)', 'آزمون‌های روان‌سنجی (quiz-enterprise)']
    );
    Pas_Core_Dependency_Checker::register(
        'پل پیامک',
        'pivand-amn-sms-bridge/pivand-amn-sms-bridge.php',
        [] // خودش به چیزی وابسته نیست؛ فقط باید ثبت بشه تا وقتی غیرفعاله، پنل مراجعان هشدار بده
    );
    Pas_Core_Dependency_Checker::register(
        'آزمون‌های روان‌سنجی',
        'pivand-amn-quiz-enterprise/pivand-amn-quiz-enterprise.php',
        []
    );
    Pas_Core_Dependency_Checker::register(
        'پرونده مراجعین',
        'pivand-amn-case-file/pivand-amn-case-file.php',
        ['پنل مراجعان (اختیاری — برای اتصال حساب OTP و نوبت‌ها)']
    );

    /**
     * رجیستری جدول‌ها — فقط مستندسازی مرکزی، نه لود پویا از هر پلاگین (هنوز
     * هیچ پلاگینی خودش را با این API ثبت نمی‌کند؛ این‌جا فهرست شناخته‌شده‌ی
     * فعلی است تا از روز اول چیزی برای دیدن در پیشخوان باشد). وقتی جدول
     * جدیدی به یکی از پلاگین‌ها اضافه شد، این لیست هم باید به‌روزرسانی شود.
     */
    Pas_Core_Table_Registry::register('پنل مراجعان (client-portal)', 'zdp_', ['zdp_bookings', 'zdp_messages', 'zdp_surveys', 'zdp_leads', 'zdp_intake']);
    Pas_Core_Table_Registry::register('پنل مراجعان — جدول‌های میراث در حال migration', 'cpp_', ['cpp_bookings', 'cpp_messages', 'cpp_surveys', 'cpp_leads'], true);
    Pas_Core_Table_Registry::register('پرونده مراجعین (case-file)', 'pas_cf_', ['pas_cf_cases', 'pas_cf_notes', 'pas_cf_note_history', 'pas_cf_audit_log']);
    Pas_Core_Table_Registry::register('آزمون‌های روان‌سنجی (quiz-enterprise)', 'psy_', ['psy_invitations', 'psy_attempts', 'psy_couples', 'psy_drafts', 'psy_logs']);
    Pas_Core_Table_Registry::register('پل پیامک (sms-bridge)', 'pas_', ['pas_sms_log']);
    Pas_Core_Table_Registry::register('تولید محتوا و سئو (content-writer)', 'aco_', ['aco_queue', 'aco_logs', 'aco_keyword_bank']);
    Pas_Core_Table_Registry::register('فرم تماس (payvand-contact)', 'payvand_', ['payvand_messages']);
    Pas_Core_Table_Registry::register('هسته — رجیستری هویت (core 1.4+)', 'pas_', ['pas_clients', 'pas_client_identifiers', 'pas_identity_conflicts']);
    // فاز ۹: جدول‌های فاز ۲ که قبلاً در رجیستری ثبت نشده بودند.
    Pas_Core_Table_Registry::register('هسته — نگاشت فیلد و ثبت منبع (core 1.5+)', 'pas_', ['pas_field_maps', 'pas_field_provenance']);
    // فاز ۹: صف ایمیل افزونه‌ی SMTP (مالک جدول، افزونه‌ی smtp-mailer است؛
    // اینجا فقط برای شناسایی/بررسی وجود ثبت می‌شود و هیچ وابستگی‌ای به آن
    // افزونه ایجاد نمی‌کند).
    Pas_Core_Table_Registry::register('SMTP و صف ایمیل (smtp-mailer)', 'zsm_', ['zsm_mail_queue']);

    /**
     * رجیستری کپابیلیتی‌ها — مثل بالا، فقط مستندسازی مرکزی. اعمال واقعی
     * add_cap همچنان در همان پلاگین صاحب انجام می‌شود (تغییرش نمی‌کند).
     */
    Pas_Core_Capability_Registry::register('zdp_manage_clients', 'مدیریت مراجعین در پنل مراجعان', 'پنل مراجعان (client-portal)', ['administrator']);
    Pas_Core_Capability_Registry::register('pas_manage_case_files', 'ورود به پنل پرونده‌های بالینی (حداقلی)', 'پرونده مراجعین (case-file)', ['administrator', 'pas_cf_therapist']);
    Pas_Core_Capability_Registry::register('pas_view_all_case_files', 'دیدن پرونده‌ی همه‌ی درمانگرها، نه فقط خودش', 'پرونده مراجعین (case-file)', ['administrator']);
    // فاز ۹: نقش درمانگر — در افزونه‌ی پرونده ساخته می‌شود
    // (Pas_CF_Security::ensure_therapist_role) و فقط همین یک کپابیلیتی را
    // دارد به‌علاوه‌ی read؛ عمداً pas_view_all_case_files به آن داده نمی‌شود.
    Pas_Core_Capability_Registry::register_role(
        'pas_cf_therapist',
        'درمانگر — فقط پرونده‌های تخصیص‌داده‌شده به خودش',
        'پرونده مراجعین (case-file)',
        ['pas_manage_case_files'],
        ['read']
    );
}, 5); // اولویت ۵ — قبل از بقیه‌ی افزونه‌های خانواده لود شود
