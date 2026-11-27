<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * فونت برند مشترک اکوسیستم پیوند امن (Vazirmatn)، سلف‌هاست‌شده در همین
 * افزونه‌ی core. هر پلاگین دیگر (client-portal، quiz-enterprise، ...) به‌جای
 * اینکه خودش فونت را از گوگل بگیرد یا فقط در CSS به آن ارجاع بدهد بدون
 * لود واقعی، فقط این تابع را صدا می‌زند:
 *
 *     Pas_Core_Fonts::enqueue();
 *
 * قبل از enqueue استایل خودش (با آن به‌عنوان dependency، یا جدا). چندبار
 * صدا زدن از چند پلاگین مختلف مشکلی ندارد — wp_register_style فقط یک‌بار
 * واقعاً ثبت می‌شود.
 */
final class Pas_Core_Fonts
{
    private const HANDLE = 'pas-core-vazirmatn';

    public static function register(): void
    {
        if (wp_style_is(self::HANDLE, 'registered')) {
            return;
        }

        wp_register_style(
            self::HANDLE,
            PAS_CORE_URL . 'assets/fonts/vazirmatn/vazirmatn.css',
            [],
            PAS_CORE_VERSION
        );
    }

    /**
     * ثبت + enqueue فوری. برای فراخوانی مستقیم داخل هوک wp_enqueue_scripts/
     * admin_enqueue_scripts هر پلاگین مصرف‌کننده.
     */
    public static function enqueue(): void
    {
        self::register();
        wp_enqueue_style(self::HANDLE);
    }

    /**
     * برای پلاگین‌هایی که می‌خواهند فونت را به‌عنوان dependency استایل خودشان
     * بدهند (تا ترتیب لود تضمین‌شده باشد) به‌جای enqueue مستقیم.
     */
    public static function handle(): string
    {
        self::register();

        return self::HANDLE;
    }
}
