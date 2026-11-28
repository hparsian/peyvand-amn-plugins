<?php
/**
 * Plugin Name: پیوند امن — آزمون‌های روان‌سنجی
 * Description: پلتفرم جامع آزمون‌های روان‌سنجی با سیستم توکن مراجع، گزارش تخصصی و ارسال واتساپ.
 * Version: 3.8.19
 * Requires at least: 6.6
 * Tested up to: 7.1
 * Requires PHP: 8.1
 * Domain Path: /languages
 * Author: Hadi Parsaeian
 * Author URI: mailto:h.parsian1986@gmail.com
 * Text Domain: pivand-amn-quiz-enterprise
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'PSY_DIR',        plugin_dir_path( __FILE__ ) );
define( 'PSY_URL',        plugin_dir_url( __FILE__ ) );
define( 'PSY_VERSION',    '3.8.19' );
define( 'PSY_DB_VERSION', '3.10' );  // 3.8: BUG-22 — نرمال‌سازی client_phone موقع ثبت + migration یک‌بار برای رکوردهای قدیمی
                                     // 3.9: اضافه‌شدن ستون client_email — ورود جایگزین با ایمیل برای مراجعینی که شماره ایرانی ندارند

// ── Core ───────────────────────────────────────────────────────────────────
require_once PSY_DIR . 'includes/core/class-psy-helpers.php';
require_once PSY_DIR . 'includes/core/interface-test.php';
require_once PSY_DIR . 'includes/core/class-psy-base-test.php';
require_once PSY_DIR . 'includes/core/class-psy-scoring.php';
require_once PSY_DIR . 'includes/core/class-psy-report-html.php';
require_once PSY_DIR . 'includes/core/class-psy-registry.php';

// ── Tests ──────────────────────────────────────────────────────────────────
// هر فایل یک کلاس — glob ساده کافی است.
foreach ( glob( PSY_DIR . 'includes/tests/*.php' ) as $_test_file ) {
    require_once $_test_file;
}
unset( $_test_file );

// ── Plugin layers ──────────────────────────────────────────────────────────
// logger اول لود می‌شود — psy_log() توسط rest-handler، pdf-export و manual-entry استفاده می‌شود
require_once PSY_DIR . 'includes/class-psy-logger.php';
require_once PSY_DIR . 'includes/class-psy-assets.php';
require_once PSY_DIR . 'includes/class-psy-activator.php';
require_once PSY_DIR . 'includes/class-psy-invitation-manager.php';
require_once PSY_DIR . 'includes/class-psy-rest-handler.php';
require_once PSY_DIR . 'includes/class-psy-pdf-export.php';
require_once PSY_DIR . 'includes/class-psy-offline-export.php';
require_once PSY_DIR . 'includes/class-psy-manual-entry.php';
require_once PSY_DIR . 'includes/class-psy-frontend.php';
require_once PSY_DIR . 'admin/class-psy-admin.php';
require_once PSY_DIR . 'admin/class-psy-debug.php';

// ── Activation / Deactivation / Uninstall hooks ────────────────────────────
// Uninstall از طریق uninstall.php مجزا مدیریت می‌شود (WP_UNINSTALL_PLUGIN)
register_activation_hook( __FILE__, [ 'Psy_Quiz_Activator', 'activate' ] );

// ── Deactivation hook ───────────────────────────────────────────────────────
// فقط cleanup موقت — جداول و داده‌های مراجعین حفظ می‌شوند
register_deactivation_hook( __FILE__, function () {
    // پاک کردن transient‌های موقت ساخت مراجع جدید
    global $wpdb;
    $wpdb->query(
        "DELETE FROM {$wpdb->options}
         WHERE option_name LIKE '_transient_psy_new_client_%'
            OR option_name LIKE '_transient_timeout_psy_new_client_%'"
    );
    // لغو cronjob پاکسازی 'processing' گیر کرده
    $ts = wp_next_scheduled( 'psy_quiz_cleanup_processing' );
    if ( $ts ) wp_unschedule_event( $ts, 'psy_quiz_cleanup_processing' );
    // لغو cronjob پاکسازی لاگ‌ها
    $ts2 = wp_next_scheduled( 'psy_quiz_cleanup_logs' );
    if ( $ts2 ) wp_unschedule_event( $ts2, 'psy_quiz_cleanup_logs' );
    // لغو cronjob پاکسازی draft های قدیمی
    $ts3 = wp_next_scheduled( 'psy_quiz_cleanup_drafts' );
    if ( $ts3 ) wp_unschedule_event( $ts3, 'psy_quiz_cleanup_drafts' );
    flush_rewrite_rules();
} );

// ── Cron: پاکسازی دعوتنامه‌های 'processing' گیر کرده (هر ساعت) ────────────
add_action( 'psy_quiz_cleanup_processing', [ 'Psy_Quiz_Activator', 'cleanup_stuck_processing' ] );

// ── Cron: پاکسازی لاگ‌های قدیمی‌تر از ۳۰ روز (روزانه) ──────────────────────
add_action( 'psy_quiz_cleanup_logs', [ 'Psy_Logger', 'cleanup_old' ] );

// ── Cron: پاکسازی draft های قدیمی‌تر از ۳۰ روز (روزانه) ────────────────────
add_action( 'psy_quiz_cleanup_drafts', [ 'Psy_Quiz_Activator', 'cleanup_old_drafts' ] );

// ── Integration hook: اتصال به پنل مراجع افزونه‌های دیگر ──────────────────
// افزونه‌های دیگر (مثلاً پنل ورود OTP) با این فیلتر، آزمون‌های تعیین‌شده توسط
// درمانگر برای یک شماره موبایل مشخص را می‌گیرند؛ بدون include مستقیم و بدون
// دسترسی به جدول psy_invitations.
add_filter( 'psy_get_pending_quizzes_for_phone', function ( $default_value, $phone ) {
    return Psy_Quiz_Invitation_Manager::get_pending_for_phone( (string) $phone );
}, 10, 2 );

// نسخه‌ی ایمیلیِ فیلتر بالا — برای مراجعینِ خارج از کشور که شماره‌ی
// ایرانی ندارند و درمانگر موقع تعریف آزمون فقط client_email را ثبت کرده.
// افزونه‌ی پنل مراجع (zoojdarman-client-portal) وقتی حساب کاربری یک مراجع
// موبایل شناخته‌شده‌ای ندارد (یعنی صرفاً با ایمیل وارد شده)، به‌جای فیلتر
// موبایل‌محور بالا از این استفاده می‌کند.
add_filter( 'psy_get_pending_quizzes_for_email', function ( $default_value, $email ) {
    return Psy_Quiz_Invitation_Manager::get_pending_for_email( (string) $email );
}, 10, 2 );

// افزونه‌ی پنل مراجع (zoojdarman-client-portal) موقع ساخت حساب کاربری جدید
// این فیلتر را صدا می‌زند تا اگر درمانگر قبلاً موقع تعریف یک آزمون نام واقعی
// مراجع را ثبت کرده، همان نام به‌جای «مراجع 09xxxxxxxxx» روی حساب بنشیند.
// این فیلتر قبلاً در این پلاگین پیاده نشده بود — سمت پنل مراجع همیشه مقدار
// پیش‌فرض خالی می‌گرفت و این قابلیت هیچ‌وقت واقعاً کار نمی‌کرد.
add_filter( 'psy_get_client_name_for_phone', function ( $default_value, $phone ) {
    $name = Psy_Quiz_Invitation_Manager::get_known_name_for_phone( (string) $phone );

    return $name !== '' ? $name : $default_value;
}, 10, 2 );

// افزونه‌ی پرونده مراجعین (pivand-amn-case-file) این فیلتر را صدا می‌زند تا
// نتایج آزمون‌های تکمیل‌شده را (به‌صورت لینک به گزارش موجود، نه کپی خام
// داده) توی پرونده‌ی بالینی نشان دهد. قبلاً این فیلتر پیاده نشده بود —
// طبق نقشه‌ی اکوسیستم (ecosystem-map.md) این آخرین قرارداد ناقصی بود که
// باقی مانده بود.
add_filter( 'psy_get_completed_results_for_phone', function ( $default_value, $phone ) {
    $results = Psy_Quiz_Invitation_Manager::get_completed_results_for_phone( (string) $phone );

    return ! empty( $results ) ? $results : $default_value;
}, 10, 2 );

// قرارداد روند درمان (فاز ۵ اکوسیستم): نمره‌ی کل هر آزمون تکمیل‌شده برای
// شماره‌ی این مراجع. مصرف‌کننده: افزونه‌ی پرونده مراجعین، کارت "روند
// ارزیابی‌ها". تفاوت اصلی با فیلتر بالا این است که نمره هم برمی‌گردد؛ داده
// هنوز فقط از جدول‌های همین افزونه و با رمزگشایی استاندارد خوانده می‌شود.
add_filter( 'psy_get_assessment_scores_for_phone', function ( $default_value, $phone ) {
    $rows = Psy_Quiz_Invitation_Manager::get_assessment_scores_for_phone( (string) $phone );

    return ! empty( $rows ) ? $rows : $default_value;
}, 10, 2 );

// قرارداد فیلتری مشترک pas_lookup_known_client_name — اولویت ۱۵ (آخرین در
// زنجیره): اسمی که موقع تعریف یک آزمون برای این شماره ثبت شده، اگر پنل
// مراجعان و پرونده مراجعین چیزی برای این شماره نداشته باشند، به‌عنوان
// آخرین راه‌حل استفاده می‌شود.
add_filter( 'pas_lookup_known_client_name', function ( $default_value, $phone ) {
    if ( ! empty( $default_value ) ) {
        return $default_value;
    }

    $name = Psy_Quiz_Invitation_Manager::get_known_name_for_phone( (string) $phone );

    return $name !== '' ? $name : $default_value;
}, 15, 2 );

// افزونه‌ی پنل مراجع (zoojdarman-client-portal)، وقتی مراجع بخواهد به‌جای
// پیامک با «ایمیل» وارد پنل شود، این فیلتر را صدا می‌زند تا شماره موبایلِ
// متصل به آن ایمیل (که درمانگر موقع تعریف آزمون ثبت کرده) را پیدا کند —
// همان شماره‌ای که کلید اصلی تطبیق آزمون در کل اکوسیستم است. کد OTP فقط به
// همین ایمیلِ از‌قبل‌ثبت‌شده فرستاده می‌شود، نه هر ایمیلی که کاربر لحظه‌ی
// ورود آزادانه تایپ کند — این جلوی سوءاستفاده (ادعای شماره‌ی دیگران) را می‌گیرد.
add_filter( 'psy_get_mobile_for_email', function ( $default_value, $email ) {
    $mobile = Psy_Quiz_Invitation_Manager::get_mobile_for_email( (string) $email );

    return $mobile !== '' ? $mobile : $default_value;
}, 10, 2 );

// ── Migration: اجرای آپدیت‌های DB اگر نسخه قدیمی‌تر باشد ─────────────────
add_action( 'plugins_loaded', function () {
    if ( get_option( 'psy_quiz_db_version' ) !== PSY_DB_VERSION ) {
        Psy_Quiz_Activator::activate();
    }
} );

// ── Admin notice: رمزنگاری داده‌های حساس ────────────────────────────────
add_action( 'admin_notices', function () {
    if ( ! current_user_can( 'manage_options' ) ) return;
    if ( class_exists( 'Pas_Core_Crypto' ) && Pas_Core_Crypto::is_configured() ) return;
    $screen = get_current_screen();
    if ( ! $screen || strpos( $screen->id, 'psy-quiz' ) === false ) return;
    echo '<div class="notice notice-error"><p>';
    if ( ! class_exists( 'Pas_Core_Crypto' ) ) {
        echo '🔓 <strong>Psy Quiz:</strong> افزونه‌ی «پیوند امن — هسته مشترک» فعال نیست. جواب و نتیجه‌ی آزمون‌ها به‌صورت متن ساده (بدون رمزنگاری) در دیتابیس ذخیره می‌شوند.';
    } else {
        echo '🔓 <strong>Psy Quiz:</strong> کلید رمزنگاری (<code>PAS_CORE_ENCRYPTION_KEY</code>) تنظیم نشده. جواب و نتیجه‌ی آزمون‌ها به‌صورت متن ساده (بدون رمزنگاری) در دیتابیس ذخیره می‌شوند — برای تنظیم کلید به پیشخوان افزونه‌ی «پرونده مراجعین» سر بزن (همان‌جا کلید پیشنهادی نمایش داده می‌شود).';
    }
    echo '</p></div>';
} );

// ── Admin notice: mPDF ────────────────────────────────────────────────────
add_action( 'admin_notices', function () {
    if ( ! current_user_can( 'manage_options' ) ) return;
    if ( get_option( 'psy_quiz_pdf_available' ) === '1' ) return;
    // فقط در صفحات پلاگین نشان داده شود
    $screen = get_current_screen();
    if ( ! $screen || strpos( $screen->id, 'psy-quiz' ) === false ) return;
    echo '<div class="notice notice-warning is-dismissible"><p>';
    echo '⚠️ <strong>Psy Quiz:</strong> کتابخانه <code>mPDF</code> نصب نشده است. قابلیت دانلود PDF غیرفعال است. ';
    echo 'برای نصب: در پوشه پلاگین دستور <code>composer require mpdf/mpdf</code> را اجرا کنید. ';
    echo 'راهنمای کامل در فایل <code>INSTALL-MPDF.md</code> داخل پلاگین موجود است.';
    echo '</p></div>';
} );

// ── REST API ───────────────────────────────────────────────────────────────
add_action( 'rest_api_init', function () {
    ( new Psy_Quiz_Rest_Handler() )->register_routes();
    Psy_Quiz_PDF_Export::register_routes();
} );

// ── Offline export hooks
Psy_Quiz_Offline_Export::register_hooks();
Psy_Quiz_Manual_Entry::register_hooks();

// ── Admin menu ─────────────────────────────────────────────────────────────
add_action( 'admin_menu', function () {
    add_menu_page(
        'آزمون‌های روانشناسی',
        'آزمون‌های روانشناسی',
        'manage_options',
        'psy-quiz',
        [ 'Psy_Page_Clients', 'render_dashboard' ],
        'dashicons-clipboard',
        25
    );
    add_submenu_page( 'psy-quiz', 'مراجعین',      'مراجعین',              'manage_options', 'psy-quiz',          [ 'Psy_Page_Clients',  'render_dashboard'  ] );
    add_submenu_page( 'psy-quiz', 'مراجع جدید',  'مراجع جدید + لینک',   'manage_options', 'psy-quiz-new',      [ 'Psy_Page_Clients',  'render_new_client' ] );
    add_submenu_page( 'psy-quiz', 'مقایسه زوجین','💑 مقایسه زوجین',     'manage_options', 'psy-quiz-couples',  [ 'Psy_Page_Couples',  'render_couples'    ] );
    add_submenu_page( 'psy-quiz', 'مقایسه نوبت‌ها','📈 مقایسه نوبت‌ها',  'manage_options', 'psy-quiz-progress', [ 'Psy_Page_Progress', 'render_progress'   ] );
    add_submenu_page( 'psy-quiz', 'گزارش‌ها',    'گزارش‌ها',             'manage_options', 'psy-quiz-reports',  [ 'Psy_Page_Reports',  'render_reports'    ] );
    add_submenu_page( 'psy-quiz', 'تنظیمات',     '⚙️ تنظیمات',           'manage_options', 'psy-quiz-settings', [ 'Psy_Page_Settings', 'render_settings'   ] );
    add_submenu_page( 'psy-quiz', 'دیباگ',       '🛠️ دیباگ',             'manage_options', 'psy-quiz-debug',    [ 'Psy_Debug',         'render'            ] );
    add_submenu_page( 'psy-quiz', 'آزمون آفلاین','📥 آزمون آفلاین',      'manage_options', 'psy-quiz-offline',  [ 'Psy_Page_Offline',  'render_offline'    ] );
    add_submenu_page( 'psy-quiz', 'ورود دستی',   '📋 ورود دستی',         'manage_options', 'psy-quiz-manual',   [ 'Psy_Page_Offline',  'render_manual'     ] );
} );

// ── پردازش فرم «مراجع جدید» پیش از هر خروجی HTML (رفع باگ صفحه سفید) ────────
// این باید زودتر از رندر صفحه اجرا شود چون خود صفحه بعد از هدر ادمین صدا
// زده می‌شود و در آن لحظه دیگر امکان ارسال هدر Location برای redirect نیست.
add_action( 'admin_init', [ 'Psy_Page_Clients', 'process_new_client_submission' ] );

// ── پردازش لغو/حذف/ارسال اجباری پیش از هر خروجی HTML (همان باگ صفحه سفید،
// اینجا به‌صورت «صفحه بعد از حذف آزمون هنگ می‌کند» بروز پیدا کرده بود) ──────
add_action( 'admin_init', [ 'Psy_Page_Clients', 'handle_revoke' ] );
add_action( 'admin_init', [ 'Psy_Page_Couples', 'process_actions' ] );

// ── Settings API ────────────────────────────────────────────────────────────
add_action( 'admin_init', function () {
    register_setting( 'psy_quiz_settings', 'psy_quiz_link_expiry_days', [
        'type'              => 'integer',
        // BUG-04: مقدار ۰ مجاز است (= بدون انقضا)
        'sanitize_callback' => function( $val ) {
            $val = intval( $val );
            return ( $val >= 0 && $val <= 365 ) ? $val : 30;
        },
        'default'           => 30,
    ] );
    register_setting( 'psy_quiz_settings', 'psy_quiz_therapist_email', [
        'type'              => 'string',
        'sanitize_callback' => 'sanitize_email',
        'default'           => 'peyvandamn@gmail.com',
    ] );
    register_setting( 'psy_quiz_settings', 'psy_sms_quiz_ready_body_id', [
        'type'              => 'string',
        'sanitize_callback' => 'sanitize_text_field',
        'default'           => '',
    ] );
    register_setting( 'psy_quiz_settings', 'psy_quiz_popular_tests', [
        'type'              => 'array',
        'sanitize_callback' => function( $val ) {
            if ( ! is_array( $val ) ) return \PsychAssessments\Core\Registry::$popular_default;
            $all_ids = \PsychAssessments\Core\Registry::ids();
            return array_values( array_filter( $val, fn($id) => in_array( $id, $all_ids, true ) ) );
        },
        'default'           => [],
    ] );
    register_setting( 'psy_quiz_settings', 'psy_exam_therapist_name', [
        'type'              => 'string',
        'sanitize_callback' => 'sanitize_text_field',
        'default'           => 'مهناز خویینی',
    ] );
    // همان تنظیمِ zdp_support_phone که پلاگین پنل مراجعین استفاده می‌کند —
    // عمداً یک شماره‌ی پشتیبانی مشترک برای کل اکوسیستم، نه دو تنظیم جدا در
    // دو پلاگین که ممکن است ناهماهنگ شوند.
    register_setting( 'psy_quiz_settings', 'zdp_support_phone', [
        'type'              => 'string',
        'sanitize_callback' => 'sanitize_text_field',
        'default'           => '',
    ] );
    // رفع باگ گزارش‌شده: تا این نسخه، uninstall.php بدون هیچ شرطی جدول‌های
    // مراجعین/نتایج آزمون‌ها را DROP می‌کرد — یعنی هر بار که کاربر برای نصب
    // یک آپدیت (به‌خاطر نبودِ به‌روزرسانی خودکار) مجبور بود افزونه را Delete
    // و دوباره نصب کند، تمام آزمون‌های انجام‌شده برای همیشه از بین می‌رفت.
    // حالا این پاک‌سازی مقصودی/opt-in است: فقط وقتی این تیک صراحتاً زده شده
    // باشد، uninstall.php داده‌ها را پاک می‌کند؛ پیش‌فرض «0» یعنی حذف/نصب
    // مجدد افزونه دیگر داده‌ای را از بین نمی‌برد.
    register_setting( 'psy_quiz_settings', 'psy_quiz_delete_data_on_uninstall', [
        'type'              => 'string',
        'sanitize_callback' => function ( $val ) {
            return $val === '1' ? '1' : '0';
        },
        'default'           => '0',
    ] );
} );

// ── Frontend shortcode ─────────────────────────────────────────────────────
add_shortcode( 'psy_exam', [ 'Psy_Quiz_Frontend', 'render' ] );

// ── Assets ─────────────────────────────────────────────────────────────────
// frontend assets توسط template_redirect در class-psy-frontend.php مدیریت می‌شوند
// (enqueue در آنجا انجام می‌شود — اینجا فقط admin assets لود می‌شوند)

add_action( 'admin_enqueue_scripts', function ( $hook ) {
    // صفحه‌های این منو هم با هوک `toplevel_page_...` و هم با
    // `psy-quiz_page_...` صدا زده می‌شوند؛ چک پارامتر page هر دو را می‌گیرد.
    $psy_page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
    if ( strpos( $psy_page, 'psy-quiz' ) !== 0 && ! str_contains( $hook, 'psy-quiz' ) ) return;
    wp_enqueue_style( 'psy-quiz-admin', PSY_URL . 'assets/css/admin.css', class_exists( 'Pas_Core_Tokens' ) ? [ Pas_Core_Tokens::handle() ] : [], PSY_VERSION );

    // پوسته‌ی برند (فاز ۱۲ / گام ۴): فقط در صفحات همین افزونه.
    $psy_skin_deps = [ 'psy-quiz-admin' ];
    if ( class_exists( 'PUI' ) ) {
        PUI::enqueue( 'admin' );
        $psy_skin_deps[] = PUI::handle( 'tokens' );
    }
    wp_enqueue_style( 'psy-quiz-admin-skin', PSY_URL . 'assets/css/admin-skin.css', $psy_skin_deps, PSY_VERSION );
    wp_enqueue_script( 'psy-quiz-admin', PSY_URL . 'assets/js/admin.js', [ 'jquery' ], PSY_VERSION, true );

    // نمای تک‌گزارش در ادمین از همان مارک‌آپ گزارش سمت کاربر استفاده می‌کند
    // (کلاس‌های psy-report-*)، ولی CSS آن‌ها در frontend.css است. تا فاز ۱۴ این
    // استایل‌ها در wp-admin بارگذاری نمی‌شدند و گزارش بدون قالب و با نمودار
    // تمام‌عرض/نامتعادل دیده می‌شد؛ فقط در همین نما بارگذاری می‌شود.
    if ( $psy_page === 'psy-quiz-reports' && ! empty( $_GET['attempt'] ) ) {
        $psy_report_deps = class_exists( 'Pas_Core_Tokens' ) ? [ Pas_Core_Tokens::handle() ] : [];
        wp_enqueue_style( 'psy-quiz-report-css', PSY_URL . 'assets/css/frontend.css', $psy_report_deps, PSY_VERSION );
    }
    // نانس مشترک برای jax lookup نام مراجع بر اساس شماره موبایل (core:
    // wp_ajax_pas_core_lookup_client_name). فقط در صفحه‌ی «مراجع جدید»
    // واقعاً استفاده می‌شود؛ localize کردن همیشگی‌اش بی‌خطر است چون فقط یک
    // رشته‌ی نانس است.
    wp_localize_script( 'psy-quiz-admin', 'PsyClientLookup', [
        'nonce' => wp_create_nonce( 'pas_core_lookup_client_name' ),
    ] );
    // Chart.js در دو موقعیت نیاز است:
    // ۱. صفحه مقایسه زوجین (نمودار bar)
    // ۲. صفحه گزارش‌ها — برای آزمون‌هایی که needs_chart() = true دارند (مثل LoveStory radar)
    $page   = sanitize_key( $_GET['page']   ?? '' );
    $action = sanitize_key( $_GET['action'] ?? '' );
    $needs_chart = ( $page === 'psy-quiz-couples' && $action === 'compare' )
                || ( $page === 'psy-quiz-reports' );
    if ( $needs_chart ) {
        Psy_Quiz_Assets::enqueue_chart_js();
    }
} );
