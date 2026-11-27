<?php
/**
 * اسکریپت یک‌بارمصرف مهاجرت کلیدواژه تمرکزی.
 *
 * چرا لازم است:
 * نسخه‌ی قبلی «Peyvand Amn SEO Assistant Pro» کلیدواژه تمرکزی را در
 * پست‌متای `_peyvand_amn_focus_keyword` ذخیره می‌کرد. از v2.0.0 به بعد،
 * ACO Content Writer از متاکی مشترک Yoast یعنی `_yoast_wpseo_focuskw`
 * استفاده می‌کند (طبق MERGE-NOTES.md). این مهاجرت خودکار پیاده نشده بود؛
 * این اسکریپت همان کار را یک‌بار برای همه‌ی پست‌های قدیمی انجام می‌دهد.
 *
 * قوانین مهاجرت:
 *   - فقط پست‌هایی که `_peyvand_amn_focus_keyword` غیرخالی دارند بررسی می‌شوند.
 *   - اگر `_yoast_wpseo_focuskw` از قبل مقدار دارد، آن پست دست‌نخورده
 *     می‌ماند (مقدار جدیدتر/دستی کاربر را بی‌سروصدا رونویسی نمی‌کنیم).
 *   - در پایان یک گزارش کامل (چند پست تغییر کرد، چند پست رد شد و چرا)
 *     چاپ می‌شود.
 *
 * روش اجرا (یکی از این دو):
 *
 *   ۱. WP-CLI (توصیه‌شده، امن‌تر، خروجی تمیزتر):
 *      wp eval-file wp-content/plugins/pivand-amn-content-writer/migrate-focus-keywords.php
 *
 *   ۲. مرورگر (اگر SSH/WP-CLI نداری):
 *      این فایل را به‌صورت موقت داخل wp-content/mu-plugins/ کپی کن (یا از
 *      همین مسیر پلاگین با یک لینک مستقیم به آن در پیشخوان اجرا کن) و
 *      یک‌بار در مرورگر به آدرسش با پارامتر ?pas_run_migration=1 و بعد از
 *      لاگین به‌عنوان مدیر برو. بعد از اجرا حتماً همین فایل را حذف کن —
 *      این اسکریپت نباید دائم روی سایت بماند.
 *
 * ⚠️ قبل از اجرا حتماً از دیتابیس بک‌آپ کامل بگیر. Dry-run پیش‌فرض فعال
 * است — برای اجرای واقعی پارامتر --live (در WP-CLI) یا ?live=1 (در مرورگر)
 * را اضافه کن.
 */

if (php_sapi_name() !== 'cli' && !defined('ABSPATH')) {
    exit('این اسکریپت باید از داخل وردپرس (WP-CLI یا مرورگر با لاگین ادمین) اجرا شود.');
}

// حالت مرورگر: فقط مدیر سایت مجاز است، و باید صراحتاً درخواست اجرا کرده باشد.
if (php_sapi_name() !== 'cli') {
    if (!is_user_logged_in() || !current_user_can('manage_options')) {
        wp_die('دسترسی غیرمجاز. باید به‌عنوان مدیر سایت وارد شده باشی.');
    }
    if (!isset($_GET['pas_run_migration'])) {
        exit('برای اجرا، ?pas_run_migration=1 را به آدرس اضافه کن. برای اجرای واقعی (نه dry-run) &live=1 هم اضافه کن.');
    }
}

$is_live = php_sapi_name() === 'cli'
    ? in_array('--live', $GLOBALS['argv'] ?? [], true)
    : isset($_GET['live']);

$OLD_KEY = '_peyvand_amn_focus_keyword';
$NEW_KEY = '_yoast_wpseo_focuskw';

$query = new WP_Query([
    'post_type'      => ['post', 'page'],
    'post_status'    => 'any',
    'posts_per_page' => -1,
    'fields'         => 'ids',
    'meta_query'     => [
        [
            'key'     => $OLD_KEY,
            'value'   => '',
            'compare' => '!=',
        ],
    ],
]);

$migrated = [];
$skipped_has_new = [];
$skipped_empty_old = [];

foreach ($query->posts as $post_id) {
    $old_value = get_post_meta($post_id, $OLD_KEY, true);
    $new_value = get_post_meta($post_id, $NEW_KEY, true);

    if ($old_value === '' || $old_value === null) {
        $skipped_empty_old[] = $post_id;
        continue;
    }

    if ($new_value !== '' && $new_value !== null) {
        $skipped_has_new[] = $post_id;
        continue;
    }

    if ($is_live) {
        update_post_meta($post_id, $NEW_KEY, $old_value);
    }
    $migrated[] = $post_id;
}

$mode = $is_live ? 'اجرای واقعی (LIVE)' : 'DRY-RUN — چیزی تغییر نکرد';

$report = [];
$report[] = "=== گزارش مهاجرت کلیدواژه تمرکزی — حالت: {$mode} ===";
$report[] = 'تعداد کل پست‌های بررسی‌شده: ' . count($query->posts);
$report[] = 'مهاجرت‌شده (یا در dry-run: قابل‌مهاجرت): ' . count($migrated);
$report[] = 'رد شده — قبلاً مقدار جدید داشت (دست‌نخورده ماند): ' . count($skipped_has_new);
$report[] = 'رد شده — مقدار قدیمی خالی بود: ' . count($skipped_empty_old);

if (!empty($migrated)) {
    $report[] = '';
    $report[] = 'شناسه پست‌های مهاجرت‌شده: ' . implode(', ', $migrated);
}
if (!empty($skipped_has_new)) {
    $report[] = '';
    $report[] = 'شناسه پست‌های رد‌شده (مقدار جدید از قبل موجود بود — بررسی دستی کن): ' . implode(', ', $skipped_has_new);
}

$output = implode("\n", $report);

if (php_sapi_name() === 'cli') {
    echo $output . "\n";
    if (!$is_live) {
        echo "\nبرای اجرای واقعی: wp eval-file ... --live\n";
    }
} else {
    echo '<pre style="direction:rtl;text-align:right;font-family:tahoma;padding:20px;background:#fff;border:1px solid #ccc;">' . esc_html($output) . '</pre>';
    if (!$is_live) {
        echo '<p style="direction:rtl">برای اجرای واقعی، <code>&live=1</code> را به آدرس اضافه کن.</p>';
    }
}
