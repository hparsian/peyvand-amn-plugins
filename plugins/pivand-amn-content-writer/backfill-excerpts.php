<?php
/**
 * اسکریپت یک‌بارمصرف پر کردن چکیده (excerpt) پست‌های قدیمی.
 *
 * چرا لازم است:
 * تا نسخه‌ی ۲.۹.۱۰، افزونه هیچ‌وقت `post_excerpt` را برای مقالات
 * تولیدشده (یا بازنویسی‌شده) پر نمی‌کرد. از نسخه‌ی بعدی، مقالات جدید و
 * بازنویسی‌های پست‌های قدیمی به‌صورت خودکار چکیده می‌گیرند
 * (`ACO_SEO::build_excerpt_from_content`) — اما این کار فقط برای پست‌هایی
 * اتفاق می‌افتد که دوباره تولید/بازنویسی شوند. این اسکریپت همان منطق را
 * یک‌بار برای همه‌ی پست‌های موجودِ فاقد چکیده اجرا می‌کند، بدون نیاز به
 * صبر کردن برای دور بعدی کرم محتوا یا بازنویسی دستی.
 *
 * قوانین:
 *   - فقط پست‌هایی با post_type=post و post_excerpt خالی بررسی می‌شوند.
 *   - چکیده از روی همان post_content فعلی پست ساخته می‌شود (بدون فراخوانی
 *     AI — رایگان و آنی).
 *   - پست‌هایی که از قبل چکیده (حتی دستی/کوتاه) دارند دست‌نخورده می‌مانند.
 *   - در پایان یک گزارش کامل چاپ می‌شود.
 *
 * روش اجرا (یکی از این دو):
 *
 *   ۱. WP-CLI (توصیه‌شده):
 *      wp eval-file wp-content/plugins/pivand-amn-content-writer/backfill-excerpts.php
 *      (برای اجرای واقعی: --live)
 *
 *   ۲. مرورگر (اگر SSH/WP-CLI نداری):
 *      بعد از لاگین به‌عنوان مدیر، به آدرس این فایل با ?pas_run_migration=1
 *      برو (برای اجرای واقعی &live=1 هم اضافه کن). بعد از اجرا حتماً همین
 *      فایل را حذف کن — این اسکریپت نباید دائم روی سایت بماند.
 *
 * ⚠️ قبل از اجرای واقعی از دیتابیس بک‌آپ بگیر. Dry-run پیش‌فرض فعال است.
 */

if (php_sapi_name() !== 'cli' && !defined('ABSPATH')) {
    exit('این اسکریپت باید از داخل وردپرس (WP-CLI یا مرورگر با لاگین ادمین) اجرا شود.');
}

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

if (!class_exists('ACO_SEO')) {
    exit('کلاس ACO_SEO یافت نشد — مطمئن شو افزونه فعال است.');
}

$query = new WP_Query([
    'post_type'      => 'post',
    'post_status'    => 'any',
    'posts_per_page' => -1,
    'fields'         => 'ids',
]);

$filled       = [];
$skipped_has  = [];
$skipped_empty_content = [];

foreach ($query->posts as $post_id) {
    $post = get_post($post_id);
    if (!$post) {
        continue;
    }

    if (trim((string) $post->post_excerpt) !== '') {
        $skipped_has[] = $post_id;
        continue;
    }

    $excerpt = ACO_SEO::build_excerpt_from_content($post->post_content);
    if (trim($excerpt) === '') {
        $skipped_empty_content[] = $post_id;
        continue;
    }

    if ($is_live) {
        wp_update_post([
            'ID'           => $post_id,
            'post_excerpt' => $excerpt,
        ]);
    }
    $filled[] = $post_id;
}

$mode = $is_live ? 'اجرای واقعی (LIVE)' : 'DRY-RUN — چیزی تغییر نکرد';

$report = [];
$report[] = "=== گزارش پر کردن چکیده — حالت: {$mode} ===";
$report[] = 'تعداد کل پست‌های بررسی‌شده: ' . count($query->posts);
$report[] = 'پر شد (یا در dry-run: قابل‌پرشدن): ' . count($filled);
$report[] = 'رد شده — از قبل چکیده داشت (دست‌نخورده ماند): ' . count($skipped_has);
$report[] = 'رد شده — محتوای قابل استفاده برای ساخت چکیده نداشت: ' . count($skipped_empty_content);

if (!empty($filled)) {
    $report[] = '';
    $report[] = 'شناسه پست‌های پرشده: ' . implode(', ', $filled);
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
