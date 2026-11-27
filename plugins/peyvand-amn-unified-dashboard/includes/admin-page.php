<?php
/**
 * سازگاری با نسخه‌ی قدیمی.
 *
 * از نسخه‌ی ۲.۰.۰ مسیریابی و رندر ماژول‌ها در
 * includes/class-dashboard-router.php انجام می‌شود. این فایل دیگر بارگذاری
 * نمی‌شود و فقط برای جلوگیری از خطا در صورت ارجاع قدیمی نگه داشته شده است.
 */

defined( 'ABSPATH' ) || exit;

if ( class_exists( 'PAS_Dashboard_Router' ) && is_admin() ) {
	PAS_Dashboard_Router::render();
}
