<?php

declare(strict_types=1);

namespace ZoojDarman\ClientPortal;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * نقطه‌ی واحد برای غیرفعال‌سازی کش روی مسیرهای حساس (OTP، Login Bridge،
 * REST panel-state).
 *
 * قبل از این کلاس، همین منطق عیناً و کلمه‌به‌کلمه در سه فایل جدا تکرار شده
 * بود: class-otp-auth.php::send_nocache_headers()،
 * class-login-service.php::send_nocache_headers()،
 * class-client-panel.php::prevent_page_caching(). هر سه‌شون یک چیز رو حل
 * می‌کردن (همون باگ ریشه‌ای CDN/کش که در CHANGELOG نسخه‌ی ۱.۵.۱۲ مستند شده)
 * ولی هر تغییری (مثلاً اضافه‌کردن یک افزونه‌ی کش جدید به لیست) می‌بایست در
 * سه‌جا همزمان اعمال می‌شد. این کلاس فقط یک تابع استاتیک بدون وابستگی است،
 * نه یک سرویس با state؛ فراخوانی‌اش از هر کلاسی، وابستگی کلاسی جدیدی بین
 * ماژول‌ها نمی‌سازد — همان قرارداد فیلتر/hook-محورِ اکوسیستم دست‌نخورده
 * می‌ماند.
 */
final class Cache_Guard
{
    /**
     * پاسخ جاری را از هر لایه‌ی کشِ شناخته‌شده (کش صفحه‌ی وردپرسی، آبجکت،
     * دیتابیس، و افزونه‌های محبوب کش) معاف می‌کند و هدرهای no-store را
     * می‌فرستد. باید پیش از هر echo/output دیگری صدا زده شود.
     */
    public static function no_store(): void
    {
        if (!defined('DONOTCACHEPAGE')) {
            define('DONOTCACHEPAGE', true); // WP Rocket, W3 Total Cache, WP Super Cache
        }
        if (!defined('DONOTCACHEOBJECT')) {
            define('DONOTCACHEOBJECT', true);
        }
        if (!defined('DONOTCACHEDB')) {
            define('DONOTCACHEDB', true);
        }

        do_action('litespeed_control_set_nocache', 'zdp-no-store');

        if (!headers_sent()) {
            nocache_headers();
            header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0, private');
            header('Pragma: no-cache');
            // دفاع سوم (جدا از no-store و POST-بودن اندپوینت‌ها در
            // class-client-panel.php): اگر یک لایه‌ی میانی با وجود no-store
            // باز هم تصمیم به کش کردن بگیرد (رفتار برخی CDNها با Page Rule
            // دستی)، حداقل بگوید کش باید بر اساس کوکی متفاوت باشد، نه فقط URL.
            header('Vary: Cookie', false);
        }
    }

    /**
     * پاک‌کردن کش صفحات (LiteSpeed، WP Rocket، W3 Total Cache، WP Super Cache،
     * WP Fastest Cache). برای وقتی که داده‌ای که مستقیم داخل HTML صفحه‌ی عمومی
     * چاپ شده (مثل نام/قیمت خدمات در ویجت رزرو) در پیشخوان تغییر می‌کند.
     * افزونه‌ای که نصب نباشد نادیده گرفته می‌شود. کش CDN بیرونی (مثل
     * Cloudflare) از اینجا قابل پاک‌کردن نیست و باید جدا purge شود.
     */
    public static function purge_pages(): void
    {
        do_action('litespeed_purge_all');
        if (function_exists('rocket_clean_domain')) {
            rocket_clean_domain();
        }
        if (function_exists('w3tc_flush_posts')) {
            w3tc_flush_posts();
        } elseif (function_exists('w3tc_pgcache_flush')) {
            w3tc_pgcache_flush();
        }
        if (function_exists('wp_cache_clear_cache')) {
            wp_cache_clear_cache();
        }
        if (class_exists('WpFastestCache')) {
            do_action('wpfc_clear_all_cache');
        }

        // المنتور خروجی شورت‌کدها را در «کش المنت» و فایل‌های CSS/HTML خودش نگه
        // می‌دارد؛ بدون پاک‌کردن آن، قیمت/نام خدمات قدیمی روی صفحه می‌ماند. (best-effort)
        if (class_exists('\\Elementor\\Plugin') && isset(\Elementor\Plugin::$instance->files_manager)) {
            try {
                \Elementor\Plugin::$instance->files_manager->clear_cache();
            } catch (\Throwable $e) {
                // نبود/ناسازگاری نسخه‌ی المنتور نباید ذخیره‌ی خدمات را خراب کند.
            }
        }
    }
}
