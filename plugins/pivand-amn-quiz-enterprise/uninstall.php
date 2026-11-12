<?php
/**
 * Uninstall — اجرا می‌شود فقط وقتی ادمین افزونه را حذف (Delete) می‌کند
 *
 * رفع باگ گزارش‌شده («برای نصب آپدیت مجبورم افزونه را حذف کنم و هر بار
 * تمام آزمون‌های انجام‌شده پاک می‌شود»): تا این نسخه، این فایل بدون هیچ
 * شرطی جدول‌های مراجعین/نتایج آزمون‌ها را DROP می‌کرد — یعنی «حذف برای
 * نصب مجدد» (که هیچ ربطی به قصد واقعیِ ادمین برای از دست دادن داده‌ها
 * ندارد) دقیقاً همان پیامد را داشت که «حذف برای همیشه» دارد.
 *
 * حالا این پاک‌سازی مقصودی/opt-in است: فقط اگر ادمین از قبل، در صفحه‌ی
 * تنظیمات، صراحتاً تیک «حذف کامل داده‌ها هنگام حذف افزونه» را زده باشد
 * (option: psy_quiz_delete_data_on_uninstall = '1')، جدول‌ها/تنظیمات پاک
 * می‌شوند. پیش‌فرض (تیک نخورده) یعنی حذف/نصب مجددِ افزونه هیچ داده‌ای را
 * از بین نمی‌برد — دقیقاً همان چیزی که برای سناریوی «حذف برای آپدیت»
 * لازم است.
 */

// جلوگیری از دسترسی مستقیم — WordPress این ثابت را قبل از اجرا تعریف می‌کند
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

if ( get_option( 'psy_quiz_delete_data_on_uninstall', '0' ) !== '1' ) {
    // ادمین صراحتاً درخواست حذف کامل نکرده — هیچ جدول/داده‌ای دست‌خورده
    // نمی‌شود، فقط از اینجا خارج می‌شویم تا با نصب مجدد افزونه همه‌چیز
    // (مراجعین، نتایج آزمون‌ها، تنظیمات) دست‌نخورده در دسترس باشد.
    return;
}

global $wpdb;

// ── حذف جداول — ترتیب مهم است: فرزندان قبل از والدین حذف شوند ────────────
// psy_couples به psy_attempts وابسته است → اول حذف شود
// psy_attempts به psy_invitations وابسته است → دوم حذف شود
// باگ #U1: psy_couples که در v3.1.5 اضافه شد فراموش شده بود
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}psy_couples" );
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}psy_attempts" );
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}psy_invitations" );
// #L2 fix: جدول لاگ اختصاصی — اضافه‌شده در v3.3.2
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}psy_logs" );
// BUG-U2 fix: جدول drafts که در v3.6 (v3.3.5) اضافه شد فراموش شده بود
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}psy_drafts" );

// ── حذف options ─────────────────────────────────────────────────────────────
delete_option( 'psy_quiz_db_version' );
delete_option( 'psy_quiz_pdf_available' );
delete_option( 'psy_quiz_link_expiry_days' );
// BUG-U3 fix: option ایمیل روانشناس فراموش شده بود
delete_option( 'psy_quiz_therapist_email' );
delete_option( 'psy_quiz_popular_tests' );
// #L3 fix: داده Debug Tracker — اضافه‌شده در v3.3.2
delete_option( 'psy_debug_tracker_data' );
delete_option( 'psy_quiz_delete_data_on_uninstall' );

// ── حذف تمام transient‌های افزونه ───────────────────────────────────────────
$wpdb->query(
    "DELETE FROM {$wpdb->options}
     WHERE option_name LIKE '_transient_psy_%'
        OR option_name LIKE '_transient_timeout_psy_%'"
);

// ── لغو cronjob ─────────────────────────────────────────────────────────────
$ts = wp_next_scheduled( 'psy_quiz_cleanup_processing' );
if ( $ts ) wp_unschedule_event( $ts, 'psy_quiz_cleanup_processing' );
// #L4 fix: cronjob پاکسازی لاگ‌ها — اضافه‌شده در v3.3.2
$ts2 = wp_next_scheduled( 'psy_quiz_cleanup_logs' );
if ( $ts2 ) wp_unschedule_event( $ts2, 'psy_quiz_cleanup_logs' );
// BUG-U4 fix: cronjob پاکسازی drafts — اضافه‌شده در v3.3.5
$ts3 = wp_next_scheduled( 'psy_quiz_cleanup_drafts' );
if ( $ts3 ) wp_unschedule_event( $ts3, 'psy_quiz_cleanup_drafts' );

// ── پاک کردن rewrite rules ──────────────────────────────────────────────────
flush_rewrite_rules();
