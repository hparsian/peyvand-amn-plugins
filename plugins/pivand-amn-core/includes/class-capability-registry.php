<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * رجیستری مرکزی کپابیلیتی‌ها و نقش‌های سفارشی اکوسیستم پیوند امن. هر پلاگین
 * جدا cap خودش را تعریف و روی نقش‌ها اعمال می‌کند (zdp_manage_clients،
 * pas_manage_case_files، pas_view_all_case_files، ...) بدون هیچ نقطه‌ی
 * مرکزی برای دیدن «چه دسترسی‌ای کجا تعریف شده».
 *
 * این کلاس چیزی را خودش add_cap/add_role نمی‌کند — هر پلاگین همچنان مسئول
 * ثبت واقعی cap/role خودش است (تغییرش ریسک دارد). هدف فقط مستندسازی مرکزی
 * و **اعتبارسنجی** است: نقش ثبت‌شده با نقش واقعی وردپرس مقایسه می‌شود و
 * اختلاف (نقش غایب، cap اعلام‌شده‌ی غایب، cap ناخواسته‌ی اضافه) گزارش می‌شود.
 *
 * فاز ۹: ثبت نقش‌ها (register_role) و اعتبارسنجی (role_status) اضافه شد؛
 * ثبت کپابیلیتی تکراری هم نادیده گرفته می‌شود.
 */
final class Pas_Core_Capability_Registry
{
    private static array $registered = [];

    /** کلید = نام کپابیلیتی */
    private static array $capability_names = [];

    /** کلید = نام نقش */
    private static array $roles = [];

    /**
     * @param string $capability   نام دقیق کپابیلیتی (مثل 'zdp_manage_clients')
     * @param string $label        توضیح خوانا برای انسان
     * @param string $plugin_name  پلاگین صاحب این کپابیلیتی
     * @param array  $granted_to   نام نقش‌هایی که فعلاً این کپابیلیتی را دارند (مثل ['administrator'])
     */
    public static function register(string $capability, string $label, string $plugin_name, array $granted_to): void
    {
        $capability = trim($capability);

        if ($capability === '' || isset(self::$capability_names[$capability])) {
            return; // ثبت تکراری نادیده گرفته می‌شود
        }

        self::$capability_names[$capability] = true;

        self::$registered[] = [
            'capability' => $capability,
            'label'      => $label,
            'plugin'     => $plugin_name,
            'granted_to' => array_values(array_unique(array_map('strval', $granted_to))),
        ];
    }

    public static function get_all(): array
    {
        return self::$registered;
    }

    /**
     * ثبت نقش سفارشی (مستندسازی؛ ساخت واقعی نقش همچنان کار پلاگین صاحب است).
     *
     * @param string   $role         نام نقش (مثل 'pas_cf_therapist')
     * @param string   $label        برچسب خوانا
     * @param string   $plugin_name  پلاگین سازنده
     * @param string[] $capabilities کپابیلیتی‌هایی که این نقش باید داشته باشد
     * @param string[] $extra_access دسترسی‌های پایه‌ی وردپرس که عمداً لازم‌اند (مثل 'read')
     */
    public static function register_role(
        string $role,
        string $label,
        string $plugin_name,
        array $capabilities,
        array $extra_access = ['read']
    ): void {
        $role = trim($role);

        if ($role === '' || isset(self::$roles[$role])) {
            return; // ثبت تکراری
        }

        self::$roles[$role] = [
            'role'         => $role,
            'label'        => $label,
            'plugin'       => $plugin_name,
            'capabilities' => array_values(array_unique(array_map('strval', $capabilities))),
            'extra_access' => array_values(array_unique(array_map('strval', $extra_access))),
        ];
    }

    /**
     * @return array<int,array{role:string,label:string,plugin:string,capabilities:array,extra_access:array}>
     */
    public static function get_roles(): array
    {
        return array_values(self::$roles);
    }

    /**
     * مقایسه‌ی نقش ثبت‌شده با نقش واقعی وردپرس — فقط‌خواندنی.
     *
     * @return array{exists:bool,missing:array<int,string>,unexpected:array<int,string>}
     *         missing = cap اعلام‌شده که نقش واقعی ندارد؛
     *         unexpected = cap خارج از فهرست اعلام‌شده که نقش واقعی دارد.
     */
    public static function role_status(string $role): array
    {
        $object = get_role($role);

        if (!$object) {
            return ['exists' => false, 'missing' => [], 'unexpected' => []];
        }

        $declared = self::$roles[$role]['capabilities'] ?? [];
        $allowed  = array_merge($declared, self::$roles[$role]['extra_access'] ?? []);

        $actual = array_keys(array_filter((array) $object->capabilities));

        $missing    = array_values(array_diff($declared, $actual));
        $unexpected = array_values(array_diff($actual, $allowed));

        return ['exists' => true, 'missing' => $missing, 'unexpected' => $unexpected];
    }
}
