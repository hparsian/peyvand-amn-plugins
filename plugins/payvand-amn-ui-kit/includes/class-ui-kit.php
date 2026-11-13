<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ثبت و بارگذاری شرطی دارایی‌های کیت رابط کاربری.
 *
 * قواعد سخت:
 *  - هیچ استایلی به‌صورت خودکار روی کل سایت/پیشخوان بارگذاری نمی‌شود؛ فقط
 *    جایی که افزونهٔ مصرف‌کننده صریحاً PUI::enqueue() را صدا بزند.
 *  - ثبت idempotent است (چند افزونه می‌توانند هم‌زمان صدا بزنند).
 *  - هیچ قاعدهٔ سراسری روی html/body در فایل‌های عمومی نیست؛ فقط در پوسته‌های
 *    اختصاصی (.pui-shell) که کل صفحه مالِ ماست.
 */
final class PUI
{
    /** @var array<string,bool> */
    private static $enqueued = [];

    public static function boot(): void
    {
        add_action('admin_enqueue_scripts', [__CLASS__, 'register_all']);
        add_action('wp_enqueue_scripts', [__CLASS__, 'register_all']);
        add_action('admin_init', [__CLASS__, 'register_all']);
    }

    /**
     * ثبت (نه enqueue). امن برای فراخوانی چندباره.
     */
    public static function register_all(): void
    {
        if (wp_style_is('pui-tokens', 'registered')) {
            return;
        }

        $ver = self::asset_version();

        wp_register_style(
            'pui-fonts',
            PUI_URL . 'assets/fonts/vazirmatn/vazirmatn.css',
            [],
            PUI_VERSION
        );
        wp_register_style(
            'pui-tokens',
            PUI_URL . 'assets/css/tokens.css',
            ['pui-fonts'],
            $ver
        );
        wp_register_style(
            'pui-components',
            PUI_URL . 'assets/css/components.css',
            ['pui-tokens'],
            $ver
        );
        wp_register_style(
            'pui-shell',
            PUI_URL . 'assets/css/shell.css',
            ['pui-components'],
            $ver
        );
        wp_register_script(
            'pui-ui',
            PUI_URL . 'assets/js/ui.js',
            [],
            $ver,
            true
        );
    }

    /**
     * بارگذاری شرطی. $context یکی از auth|panel|admin|center است و فقط برای
     * مستندسازی/گسترش آینده نگه داشته می‌شود؛ همهٔ زمینه‌ها یک توکن و یک
     * کامپوننت مشترک دارند و تفاوتشان در انتخاب پوسته است.
     */
    public static function enqueue(string $context = 'admin'): void
    {
        self::register_all();

        if (!isset(self::$enqueued[$context])) {
            self::$enqueued[$context] = true;
        }

        wp_enqueue_style('pui-tokens');
        wp_enqueue_style('pui-components');
        wp_enqueue_script('pui-ui');

        /** امکان افزودن پوستهٔ اختصاصی توسط افزونهٔ مصرف‌کننده. */
        do_action('pui_enqueue_shell', $context);
    }

    /** بارگذاری پوستهٔ تمام‌صفحه (صفحهٔ ورود/پنل) در کنار کامپوننت‌ها. */
    public static function enqueue_shell(string $context = 'panel'): void
    {
        self::enqueue($context);
        wp_enqueue_style('pui-shell');
    }

    public static function handle(string $which = 'components'): string
    {
        self::register_all();

        $map = [
            'fonts'      => 'pui-fonts',
            'tokens'     => 'pui-tokens',
            'components' => 'pui-components',
            'shell'      => 'pui-shell',
            'script'     => 'pui-ui',
        ];

        return $map[$which] ?? 'pui-components';
    }

    /**
     * مقدار attribute حالت رنگ. همیشه از سمت سرور مقدار امن برمی‌گردد؛
     * انتخاب کاربر در localStorage بعد از لود اعمال می‌شود (بدون پرش محسوس،
     * چون اسکریپت تم در <head> زودهنگام اجرا می‌شود).
     *
     * @return string e.g. 'light' | 'dark' | 'auto'
     */
    public static function theme_attr(): string
    {
        $default = (string) get_option('pui_default_theme', 'light');
        $allowed = ['light', 'dark', 'auto'];

        $theme = in_array($default, $allowed, true) ? $default : 'light';

        return (string) apply_filters('pui_default_theme', $theme);
    }

    /** برای حالت WP_DEBUG نسخه را بر اساس زمان تغییر فایل می‌سازد تا کش نشود. */
    private static function asset_version(): string
    {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            $file = PUI_DIR . 'assets/css/components.css';
            if (file_exists($file)) {
                return (string) filemtime($file);
            }
        }

        return PUI_VERSION;
    }
}
