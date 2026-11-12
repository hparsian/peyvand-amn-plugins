<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * رجیستری مرکزی جدول‌های دیتابیس سفارشی کل اکوسیستم پیوند امن. اکوسیستم
 * الان چند پیشوند جدول متفاوت دارد (zdp_, psy_, pas_, pas_cf_, aco_, pwdf_,
 * payvand_, zsm_) بدون قاعده‌ی مشترک — یکی‌سازی پیشوندها ریسک شکستن دیتای
 * موجود دارد، پس این کلاس فقط ثبت و نمایش می‌کند، نه تغییر.
 *
 * فایده‌ی عملی: هنگام دیباگ («این ستون کجا تعریف شده؟») یا موقع uninstall
 * کامل یک سایت، یک‌جا می‌شود دید که هر پلاگین دقیقاً چه جدول‌هایی ساخته،
 * به‌جای این‌که هر uninstall.php را جدا خواند.
 *
 * هر پلاگین با register() جدول‌های خودش را معرفی می‌کند. جدول‌های legacy
 * (مثل cpp_* که فقط برای migration یک‌باره نگه داشته شده‌اند) هم با
 * is_legacy=true ثبت می‌شوند تا در پیشخوان به‌وضوح «میراث/موقت» علامت
 * بخورند و کسی اشتباهی حذفشان نکند قبل از اتمام migration.
 *
 * فاز ۹: ثبت تکراری در سطح نام جدول حذف شد — اگر یک جدول در دو گروه ثبت
 * شود، فقط یک‌بار (اولین ثبت) نگه داشته می‌شود تا نمایش پیشخوان و بررسی
 * «جدول غایب» در داشبورد انطباق دوبار شمرده نشود.
 */
final class Pas_Core_Table_Registry
{
    private static array $registered = [];

    /** نام جدول‌هایی که قبلاً ثبت شده‌اند (کلید = نام جدول، بدون پیشوند سایت). */
    private static array $table_names = [];

    /**
     * @param string   $plugin_name نام خوانا برای نمایش
     * @param string   $prefix      پیشوند جدول‌های این پلاگین (بدون $wpdb->prefix سایت)
     * @param string[] $tables      نام کامل جدول‌ها (بدون $wpdb->prefix سایت)، مثل ['zdp_bookings', 'zdp_messages']
     * @param bool     $is_legacy   اگر true باشد، این جدول‌ها میراث/موقت هستند (مثل جدول‌های cpp_* در حال migration)
     */
    public static function register(string $plugin_name, string $prefix, array $tables, bool $is_legacy = false): void
    {
        $fresh = [];

        foreach (array_unique(array_map('strval', $tables)) as $table) {
            $table = trim($table);
            if ($table === '' || isset(self::$table_names[$table])) {
                continue;
            }
            self::$table_names[$table] = true;
            $fresh[]                   = $table;
        }

        if (empty($fresh)) {
            return; // ثبت تکراری: هیچ گروه خالی/تکراری ساخته نمی‌شود
        }

        self::$registered[] = [
            'plugin'    => $plugin_name,
            'prefix'    => $prefix,
            'tables'    => $fresh,
            'is_legacy' => $is_legacy,
        ];
    }

    public static function get_all(): array
    {
        return self::$registered;
    }

    /**
     * فهرست تخت و یکتای همه‌ی نام جدول‌های ثبت‌شده (برای بررسی وجود/نبود).
     *
     * @return string[]
     */
    public static function table_names(): array
    {
        return array_keys(self::$table_names);
    }
}
