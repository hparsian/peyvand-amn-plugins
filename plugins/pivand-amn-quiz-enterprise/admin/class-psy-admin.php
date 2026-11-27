<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Admin Bootstrap — فقط load فایل‌های صفحات ادمین
 *
 * ساختار:
 *   admin/traits/trait-psy-pagination.php    ← sliding-window مشترک
 *   admin/pages/class-psy-page-clients.php   ← داشبورد + مراجع جدید
 *   admin/pages/class-psy-page-reports.php   ← گزارش‌ها
 *   admin/pages/class-psy-page-couples.php   ← مقایسه زوجین
 *   admin/pages/class-psy-page-progress.php  ← مقایسه‌ی نوبت‌ها (پیشرفت درمانی)
 *   admin/pages/class-psy-page-settings.php  ← تنظیمات
 *   admin/pages/class-psy-page-offline.php   ← آفلاین + ورود دستی
 */
require_once __DIR__ . '/traits/trait-psy-pagination.php';
require_once __DIR__ . '/pages/class-psy-page-clients.php';
require_once __DIR__ . '/pages/class-psy-page-reports.php';
require_once __DIR__ . '/pages/class-psy-page-couples.php';
require_once __DIR__ . '/pages/class-psy-page-progress.php';
require_once __DIR__ . '/pages/class-psy-page-settings.php';
require_once __DIR__ . '/pages/class-psy-page-offline.php';
require_once __DIR__ . '/class-psy-debug-tracker.php';
