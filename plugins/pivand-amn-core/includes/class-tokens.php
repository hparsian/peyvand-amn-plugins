<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * توکن‌های طراحی مشترک اکوسیستم پیوند امن (رنگ، شعاع، سایه)، سلف‌هاست‌شده
 * در همین افزونه‌ی core. هر پلاگین دیگر (client-portal، quiz-enterprise،
 * payvand-contact، ...) به‌جای تعریف پالت رنگ مستقل خودش، فقط این تابع را
 * صدا می‌زند:
 *
 *     Pas_Core_Tokens::enqueue();
 *
 * قبل یا به‌عنوان dependency استایل خودش. مطابق همان الگویی که برای
 * Pas_Core_Fonts استفاده شده — چندبار صدا زدن از چند پلاگین مختلف مشکلی
 * ندارد؛ wp_register_style فقط یک‌بار واقعاً ثبت می‌شود.
 */
final class Pas_Core_Tokens
{
    private const HANDLE = 'pas-core-tokens';

    public static function register(): void
    {
        if (wp_style_is(self::HANDLE, 'registered')) {
            return;
        }

        wp_register_style(
            self::HANDLE,
            PAS_CORE_URL . 'assets/css/tokens.css',
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
     * برای پلاگین‌هایی که می‌خواهند توکن‌ها را به‌عنوان dependency استایل
     * خودشان بدهند (تا ترتیب لود تضمین‌شده و متغیرهای --pivand-* پیش از
     * استفاده‌ی محلی تعریف شده باشند) به‌جای enqueue مستقیم.
     */
    public static function handle(): string
    {
        self::register();

        return self::HANDLE;
    }
}
