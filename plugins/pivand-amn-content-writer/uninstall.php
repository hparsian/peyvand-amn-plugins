<?php
/**
 * ACO Content Writer — Uninstall
 * این فایل هنگام حذف کامل افزونه (نه غیرفعال‌سازی) توسط وردپرس اجرا می‌شود.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

global $wpdb;

// حذف جداول اختصاصی
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}aco_logs" );   // phpcs:ignore
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}aco_queue" );  // phpcs:ignore
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}aco_keyword_bank" ); // phpcs:ignore

// حذف تنظیمات از wp_options
delete_option( 'aco_settings' );
delete_option( 'aco_db_version' );

// حذف transientها
delete_transient( 'aco_generated_count' );
delete_transient( 'aco_published_ids' );

// حذف تمام lockهای باقی‌مانده
// ISSUE-2 FIX: استفاده از $wpdb->esc_like برای escape صحیح underscore در MySQL LIKE
// (underscore در MySQL یک wildcard است — بدون escape، بیشتر از آنچه باید حذف می‌شود)
$lock_pattern         = $wpdb->esc_like( '_transient_aco_lock_' ) . '%';
$lock_timeout_pattern = $wpdb->esc_like( '_transient_timeout_aco_lock_' ) . '%';
$wpdb->query( $wpdb->prepare( // phpcs:ignore
    "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
    $lock_pattern,
    $lock_timeout_pattern
) );

// حذف transientهای تحقیق کش‌شده
// ISSUE-2 FIX: استفاده از esc_like برای escape صحیح underscore
$research_pattern         = $wpdb->esc_like( '_transient_aco_research_' ) . '%';
$research_timeout_pattern = $wpdb->esc_like( '_transient_timeout_aco_research_' ) . '%';
$wpdb->query( $wpdb->prepare( // phpcs:ignore
    "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
    $research_pattern,
    $research_timeout_pattern
) );

// FIX v2.0.0: حذف transientهای کش schema (یکی به‌ازای هر post_id — aco_schema_{id})
$schema_pattern         = $wpdb->esc_like( '_transient_aco_schema_' ) . '%';
$schema_timeout_pattern = $wpdb->esc_like( '_transient_timeout_aco_schema_' ) . '%';
$wpdb->query( $wpdb->prepare( // phpcs:ignore
    "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
    $schema_pattern,
    $schema_timeout_pattern
) );

// FIX #UNINSTALL-1: حذف postmetaهای ACO از همه پست‌ها
// کلیدها: _aco_generated, _aco_generated_at, _aco_rewritten_at, _aco_seo_score, _aco_seo_checks
$wpdb->query( "DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE '\\_aco\\_%'" ); // phpcs:ignore

// FIX #UNINSTALL-2 (الف): حذف transient شمارنده prune لاگ
delete_transient( 'aco_log_prune_counter' );

// FIX #UNINSTALL-2 (ب): unschedule کردن cronها در صورتی که بدون deactivate حذف شود
wp_clear_scheduled_hook( 'aco_process_queue' );
wp_clear_scheduled_hook( 'aco_crawler_scan' );
wp_clear_scheduled_hook( 'aco_keyword_bank_daily' );

// FIX #UNINSTALL-3: حذف گزینه‌های کرم محتوا
delete_option( 'aco_crawler_reports' );
delete_option( 'aco_crawler_totals' );
