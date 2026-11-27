<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * داشبورد سلامت اکوسیستم (فاز ۱۰) — فقط‌خواندنی.
 *
 * یک نمای واحد از وضعیت هر افزونهٔ خانواده: نصب/فعال بودن، نسخهٔ هدر و
 * ثابت، جدول‌های اعلام‌شده و موجود بودن‌شان، کپابیلیتی‌های اعلام‌شده و
 * این‌که روی کدام نقش‌ها هستند، و نقش‌های سفارشی مطابق/نامطابق.
 *
 * عمداً هیچ‌چیز را تغییر نمی‌دهد: نه add_cap، نه dbDelta، نه گزینه. داده‌ها
 * از سه منبع موجود خوانده می‌شوند — رجیستری جدول‌ها، رجیستری کپابیلیتی/نقش،
 * و خودِ فایل‌های افزونه‌ها — تا این صفحه به «حقیقت» تبدیل نشود، بلکه
 * اعلام‌ها را با واقعیت مقایسه کند.
 *
 * برای افزودن افزونهٔ جدید به این داشبورد، فقط یک ردیف در plugin_catalog()
 * اضافه کن (برچسب، فایل، ثابت نسخه، و واژه‌های کلیدی برای تطبیق با نام‌های
 * رجیستری).
 */
final class Pas_Core_Health
{
    public const STATUS_OK   = 'ok';
    public const STATUS_WARN = 'warn';
    public const STATUS_FAIL = 'fail';
    public const STATUS_INFO = 'info';

    /**
     * کاتالوگ افزونه‌های خانواده. «keywords» برای تطبیق نام پلاگین در
     * رجیستری‌های موجود است (رجیستری‌ها نام خوانا نگه می‌دارند، نه slug).
     *
     * @return array<string,array<string,mixed>>
     */
    private static function plugin_catalog(): array
    {
        return [
            'pivand-amn-core' => [
                'label'     => 'هسته مشترک',
                'file'      => 'pivand-amn-core/pivand-amn-core.php',
                'const'     => 'PAS_CORE_VERSION',
                'essential' => true,
                'keywords'  => ['هسته'],
            ],
            'pivand-amn-case-file' => [
                'label'     => 'پرونده مراجعین',
                'file'      => 'pivand-amn-case-file/pivand-amn-case-file.php',
                'const'     => 'PAS_CF_VERSION',
                'essential' => false,
                'keywords'  => ['پرونده'],
            ],
            'pivand-amn-client-portal' => [
                'label'     => 'پنل مراجعان',
                'file'      => 'pivand-amn-client-portal/pivand-amn-client-portal.php',
                'const'     => 'ZDP_VERSION',
                'essential' => false,
                'keywords'  => ['پنل مراجعان'],
            ],
            'pivand-amn-quiz-enterprise' => [
                'label'     => 'آزمون‌های روان‌سنجی',
                'file'      => 'pivand-amn-quiz-enterprise/pivand-amn-quiz-enterprise.php',
                'const'     => '',
                'essential' => false,
                'keywords'  => ['آزمون'],
            ],
            'pivand-amn-sms-bridge' => [
                'label'     => 'پل پیامک',
                'file'      => 'pivand-amn-sms-bridge/pivand-amn-sms-bridge.php',
                'const'     => 'PAS_BRIDGE_VERSION',
                'essential' => false,
                'keywords'  => ['پل پیامک'],
            ],
            'pivand-amn-smtp-mailer' => [
                'label'     => 'ارسال ایمیل SMTP',
                'file'      => 'pivand-amn-smtp-mailer/pivand-amn-smtp-mailer.php',
                'const'     => 'ZSM_VERSION',
                'essential' => false,
                'keywords'  => ['SMTP'],
            ],
            'pivand-amn-content-writer' => [
                'label'     => 'تولید محتوا',
                'file'      => 'pivand-amn-content-writer/pivand-amn-content-writer.php',
                'const'     => '',
                'essential' => false,
                'keywords'  => ['تولید محتوا'],
            ],
            'pivand-amn-payvand-contact' => [
                'label'     => 'فرم تماس',
                'file'      => 'pivand-amn-payvand-contact/pivand-amn-payvand-contact.php',
                'const'     => '',
                'essential' => false,
                'keywords'  => ['فرم تماس'],
            ],
            'pivand-amn-2fa' => [
                'label'     => 'احراز دومرحله‌ای ادمین',
                'file'      => 'pivand-amn-2fa/pivand-amn-2fa.php',
                'const'     => '',
                'essential' => false,
                'keywords'  => [],
            ],
        ];
    }

    /**
     * گزارش کامل — هر ردیف یک افزونه.
     *
     * @return array{rows:array<int,array<string,mixed>>,summary:array<string,int>,generated_at:string}
     */
    public static function report(): array
    {
        global $wpdb;

        $catalog = self::plugin_catalog();

        $tables_by_plugin = [];
        $caps_by_plugin   = [];
        $roles_by_plugin  = [];
        $unknown_groups   = [];

        foreach (Pas_Core_Table_Registry::get_all() as $group) {
            $key = self::match_plugin((string) ($group['plugin'] ?? ''), $catalog);

            if ($key === null) {
                $unknown_groups[] = (string) ($group['plugin'] ?? '—');
                continue;
            }

            foreach ((array) ($group['tables'] ?? []) as $table) {
                $tables_by_plugin[$key][] = [
                    'table'   => (string) $table,
                    'full'    => $wpdb->prefix . $table,
                    'exists'  => self::table_exists($wpdb->prefix . $table),
                    'legacy'  => !empty($group['is_legacy']),
                ];
            }
        }

        foreach (Pas_Core_Capability_Registry::get_all() as $cap) {
            $key = self::match_plugin((string) ($cap['plugin'] ?? ''), $catalog);

            if ($key === null) {
                continue; // گروه ناشناخته قبلاً در بخش جدول‌ها گزارش شده
            }

            $caps_by_plugin[$key][] = [
                'capability' => (string) $cap['capability'],
                'label'      => (string) $cap['label'],
                'holders'    => self::capability_holders((string) $cap['capability']),
                'declared'   => (array) $cap['granted_to'],
            ];
        }

        foreach (Pas_Core_Capability_Registry::get_roles() as $role) {
            $key = self::match_plugin((string) ($role['plugin'] ?? ''), $catalog);

            if ($key === null) {
                continue;
            }

            $roles_by_plugin[$key][] = [
                'role'   => (string) $role['role'],
                'label'  => (string) $role['label'],
                'status' => Pas_Core_Capability_Registry::role_status((string) $role['role']),
            ];
        }

        $rows = [];

        foreach ($catalog as $key => $meta) {
            $rows[] = self::build_row(
                $key,
                $meta,
                $tables_by_plugin[$key] ?? [],
                $caps_by_plugin[$key] ?? [],
                $roles_by_plugin[$key] ?? []
            );
        }

        $summary = [
            self::STATUS_OK   => 0,
            self::STATUS_WARN => 0,
            self::STATUS_FAIL => 0,
            self::STATUS_INFO => 0,
        ];

        foreach ($rows as $row) {
            $summary[$row['status']] = ($summary[$row['status']] ?? 0) + 1;
        }

        return [
            'rows'         => $rows,
            'summary'      => $summary,
            'unknown'      => array_values(array_unique($unknown_groups)),
            'generated_at' => current_time('mysql'),
        ];
    }

    /**
     * @param array<string,mixed> $meta
     * @param array<int,array<string,mixed>> $tables
     * @param array<int,array<string,mixed>> $caps
     * @param array<int,array<string,mixed>> $roles
     * @return array<string,mixed>
     */
    private static function build_row(string $key, array $meta, array $tables, array $caps, array $roles): array
    {
        $file      = (string) $meta['file'];
        $full_path = WP_PLUGIN_DIR . '/' . $file;
        $installed = file_exists($full_path);
        $active    = $installed && self::is_plugin_active($file);

        $header_version = '';
        $const_version  = '';

        if ($installed && function_exists('get_file_data')) {
            $data           = get_file_data($full_path, ['Version' => 'Version']);
            $header_version = (string) ($data['Version'] ?? '');
        }

        $const = (string) ($meta['const'] ?? '');
        if ($const !== '' && defined($const)) {
            $const_version = (string) constant($const);
        }

        $missing_tables = [];
        foreach ($tables as $table) {
            if (!$table['exists'] && !$table['legacy']) {
                $missing_tables[] = $table['full'];
            }
        }

        $notes  = [];
        $status = self::STATUS_OK;

        if (!$installed) {
            $notes[] = 'افزونه نصب نشده است.';
            $status  = !empty($meta['essential']) ? self::STATUS_FAIL : self::STATUS_INFO;
        } elseif (!$active) {
            $notes[] = 'نصب شده ولی غیرفعال است.';
            $status  = !empty($meta['essential']) ? self::STATUS_FAIL : self::STATUS_WARN;
        }

        if ($header_version !== '' && $const_version !== '' && $header_version !== $const_version) {
            $notes[] = 'نسخهٔ هدر (' . $header_version . ') با ثابت کد (' . $const_version . ') یکی نیست.';
            if ($status === self::STATUS_OK) {
                $status = self::STATUS_WARN;
            }
        }

        if ($active && !empty($missing_tables)) {
            $notes[] = count($missing_tables) . ' جدول اعلام‌شده ساخته نشده است.';
            if ($status !== self::STATUS_FAIL) {
                $status = self::STATUS_WARN;
            }
        }

        foreach ($caps as $cap) {
            if (empty($cap['holders'])) {
                $notes[] = 'کپابیلیتی ' . $cap['capability'] . ' روی هیچ نقشی نیست.';
                if ($status === self::STATUS_OK) {
                    $status = self::STATUS_INFO;
                }
            }
        }

        foreach ($roles as $role) {
            if (!$role['status']['exists']) {
                $notes[] = 'نقش ' . $role['role'] . ' ساخته نشده است.';
                if ($status !== self::STATUS_FAIL) {
                    $status = self::STATUS_WARN;
                }
                continue;
            }

            if (!empty($role['status']['missing']) || !empty($role['status']['unexpected'])) {
                $notes[] = 'نقش ' . $role['role'] . ' با اعلام رجیستری مطابق نیست.';
                if ($status !== self::STATUS_FAIL) {
                    $status = self::STATUS_WARN;
                }
            }
        }

        if ($active && empty($tables) && empty($caps) && empty($roles)) {
            $notes[] = 'در رجیستری هیچ جدول/کپابیلیتی/نقشی برای این افزونه اعلام نشده.';
            if ($status === self::STATUS_OK) {
                $status = self::STATUS_INFO;
            }
        }

        return [
            'key'            => $key,
            'label'          => (string) $meta['label'],
            'file'           => $file,
            'installed'      => $installed,
            'active'         => $active,
            'essential'      => !empty($meta['essential']),
            'header_version' => $header_version,
            'const_version'  => $const_version,
            'tables'         => $tables,
            'missing_tables' => $missing_tables,
            'caps'           => $caps,
            'roles'          => $roles,
            'status'         => $status,
            'notes'          => $notes,
        ];
    }

    /** @param array<string,array<string,mixed>> $catalog */
    private static function match_plugin(string $name, array $catalog): ?string
    {
        $name = trim($name);

        if ($name === '') {
            return null;
        }

        foreach ($catalog as $key => $meta) {
            foreach ((array) ($meta['keywords'] ?? []) as $keyword) {
                if ($keyword !== '' && mb_stripos($name, (string) $keyword) !== false) {
                    return (string) $key;
                }
            }
        }

        return null;
    }

    private static function table_exists(string $table): bool
    {
        global $wpdb;

        return $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) === $table;
    }

    /**
     * نقش‌هایی که این کپابیلیتی را دارند (فقط‌خواندنی).
     *
     * @return string[]
     */
    private static function capability_holders(string $capability): array
    {
        if (!function_exists('wp_roles')) {
            return [];
        }

        $holders = [];
        $roles   = wp_roles();

        foreach ((array) $roles->roles as $slug => $data) {
            if (!empty($data['capabilities'][$capability])) {
                $holders[] = (string) $slug;
            }
        }

        return $holders;
    }

    private static function is_plugin_active(string $file): bool
    {
        if (function_exists('is_plugin_active')) {
            return (bool) is_plugin_active($file);
        }

        if (in_array($file, (array) get_option('active_plugins', []), true)) {
            return true;
        }

        if (function_exists('is_multisite') && is_multisite()) {
            $network = (array) get_site_option('active_sitewide_plugins', []);
            return isset($network[$file]);
        }

        return false;
    }
}
