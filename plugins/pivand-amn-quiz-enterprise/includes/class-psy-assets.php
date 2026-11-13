<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ثبت/enqueue متمرکز Chart.js برای کل پلاگین (فرانت‌اند و ادمین).
 *
 * قبل از این، Chart.js در ۳ نقطه‌ی جدا از cdnjs.cloudflare.com لود می‌شد،
 * دوتاش با handle واحد 'chart-js' و یکی با 'chartjs' — یعنی هیچ‌کدام
 * واقعاً همدیگر را نمی‌شناختند. برای مخاطب ایرانی، وابستگی به یک CDN
 * خارجی هم یک ریسک کارایی/پایداری واقعی است (اتصال کند/قطع به‌طور
 * متناوب). این کلاس اگر نسخه‌ی local (assets/js/vendor/chart.umd.min.js)
 * موجود باشد از همان استفاده می‌کند؛ در غیر این صورت به همان CDN قبلی
 * برمی‌گردد تا چیزی خراب نشود.
 */
final class Psy_Quiz_Assets
{
    private const HANDLE      = 'psy-chart-js';
    private const LOCAL_RELPATH = 'assets/js/vendor/chart.umd.min.js';

    public static function register_chart_js(): void
    {
        if (wp_script_is(self::HANDLE, 'registered')) {
            return;
        }

        $local_path = PSY_DIR . self::LOCAL_RELPATH;

        // فاز ۱۲ / گام ۶: فقط نسخه‌ی سلف‌هاست. اگر فایل نبود، اسکریپت ثبت
        // نمی‌شود (نمودارها بی‌صدا نمایش داده نمی‌شوند) تا هرگز درخواستی به
        // CDN خارجی زده نشود.
        if (!file_exists($local_path)) {
            return;
        }

        wp_register_script(self::HANDLE, PSY_URL . self::LOCAL_RELPATH, [], '4.4.1', true);
    }

    public static function enqueue_chart_js(): void
    {
        self::register_chart_js();
        wp_enqueue_script(self::HANDLE);
    }

    public static function handle(): string
    {
        self::register_chart_js();

        return self::HANDLE;
    }
}
