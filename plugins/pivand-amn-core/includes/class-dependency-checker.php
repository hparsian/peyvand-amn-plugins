<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ثبت و بررسی وابستگی بین افزونه‌های خانواده. هر افزونه‌ای که وابسته به
 * افزونه‌ی دیگری است، خودش را با register() معرفی می‌کند؛ اگر افزونه‌ی
 * وابسته غیرفعال باشد، یک هشدار واضح در پیشخوان نشان داده می‌شود — به‌جای
 * این‌که آن قابلیت بی‌صدا از کار بیفتد (همان مشکلی که الان با
 * client-portal + sms-bridge وجود دارد).
 */
final class Pas_Core_Dependency_Checker
{
    private static array $registered = [];

    /**
     * @param string   $plugin_name       نام خوانا برای نمایش
     * @param string   $plugin_basename   مسیر نسبی فایل اصلی پلاگین (مثل 'x/x.php')
     * @param string[] $soft_dependencies نام‌های خوانای وابستگی‌ها (فقط برای نمایش پیام)
     */
    public static function register(string $plugin_name, string $plugin_basename, array $soft_dependencies = []): void
    {
        self::$registered[] = [
            'name'         => $plugin_name,
            'basename'     => $plugin_basename,
            'dependencies' => $soft_dependencies,
        ];

        add_action('admin_notices', function () use ($plugin_name, $plugin_basename): void {
            self::maybe_warn_if_inactive($plugin_name, $plugin_basename);
        });
    }

    private static function maybe_warn_if_inactive(string $plugin_name, string $plugin_basename): void
    {
        if (!function_exists('is_plugin_active')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        if (is_plugin_active($plugin_basename)) {
            return; // خودش فعال است، مشکلی نیست
        }

        // این افزونه اصلاً نصب/فعال نیست — به کاربر نمی‌گوییم مگر واقعاً
        // یک افزونه‌ی دیگر به آن وابسته باشد و آن هم فعال باشد (چک زیر).
        foreach (self::$registered as $entry) {
            if ($entry['basename'] === $plugin_basename) {
                continue;
            }
            if (!is_plugin_active($entry['basename'])) {
                continue;
            }
            foreach ($entry['dependencies'] as $dep_label) {
                if (str_contains($dep_label, $plugin_name)) {
                    printf(
                        '<div class="notice notice-warning"><p><strong>هشدار وابستگی اکوسیستم پیوند امن:</strong> افزونه‌ی «%s» فعال است ولی به «%s» وابسته است که الان غیرفعال/نصب‌نشده به‌نظر می‌رسد. برخی قابلیت‌ها ممکن است بی‌صدا کار نکنند.</p></div>',
                        esc_html($entry['name']),
                        esc_html($plugin_name)
                    );
                }
            }
        }
    }

    public static function get_all(): array
    {
        return self::$registered;
    }
}
