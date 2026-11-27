<?php

if (!defined('ABSPATH')) {
    exit;
}

final class Pas_CF_Security
{
    /**
     * روی فعال‌سازی، capability اختصاصی را فقط به نقش administrator می‌دهد.
     * از manage_options عمومی استفاده نمی‌کنیم تا در آینده بشود این
     * دسترسی را به یک نقش «درمانگر» محدودتر هم داد، بدون این‌که آن نقش
     * بقیه‌ی اختیارات مدیریتی وردپرس را داشته باشد.
     */
    public static function grant_capability_to_administrators(): void
    {
        $role = get_role('administrator');
        if (!$role) {
            return;
        }
        if (!$role->has_cap(PAS_CF_CAP)) {
            $role->add_cap(PAS_CF_CAP);
        }
        // ادمین‌ها به‌طور پیش‌فرض «سرپرست» هستند و همه‌ی پرونده‌ها را می‌بینند.
        // نقش‌های محدودتر (مثل «درمانگر»، در ensure_therapist_role) فقط باید
        // PAS_CF_CAP بگیرند، نه این یکی.
        if (!$role->has_cap(PAS_CF_CAP_ALL)) {
            $role->add_cap(PAS_CF_CAP_ALL);
        }
    }

    /**
     * نقش «درمانگر» را می‌سازد (اگر قبلاً وجود نداشته باشد) — فقط با
     * PAS_CF_CAP (دسترسی به پرونده‌های تخصیص‌داده‌شده به خودش)، بدون
     * PAS_CF_CAP_ALL و بدون هیچ‌کدام از اختیارات مدیریتی وردپرس
     * (manage_options، edit_posts و غیره). قبلاً این نقش باید با ابزار
     * جداگانه‌ای (مثل User Role Editor) یا wp-cli دستی ساخته می‌شد؛ حالا
     * خودِ افزونه آن را روی فعال‌سازی/آپدیت می‌سازد.
     *
     * 'read' => true لازم است تا این نقش اصلاً بتواند وارد wp-admin شود
     * (بدون آن، وردپرس کاربر را به صفحه‌ی ورود سایت هدایت می‌کند، نه
     * داشبورد ادمین).
     *
     * اگر بعداً یک capability جدید به این افزونه اضافه شد که درمانگرهای
     * عادی هم باید داشته باشند، همین‌جا اضافه شود تا نقش‌های موجود هم
     * آپدیت بگیرند (نه فقط نصب‌های تازه).
     */
    public static function ensure_therapist_role(): void
    {
        $role = get_role('pas_cf_therapist');

        if (!$role) {
            add_role('pas_cf_therapist', 'درمانگر', [
                'read'     => true,
                PAS_CF_CAP => true,
            ]);

            return;
        }

        if (!$role->has_cap(PAS_CF_CAP)) {
            $role->add_cap(PAS_CF_CAP);
        }
        // اطمینان از اینکه این نقش هیچ‌وقت (نه امروز، نه با آپدیت‌های
        // بعدی) به‌اشتباه PAS_CF_CAP_ALL نگیرد — تخصیص آن فقط دستی و برای
        // نقش‌های «سرپرست/ادمین کلینیک» است.
        if ($role->has_cap(PAS_CF_CAP_ALL)) {
            $role->remove_cap(PAS_CF_CAP_ALL);
        }
    }

    public static function current_user_can_access(): bool
    {
        return current_user_can(PAS_CF_CAP);
    }

    public static function require_access(): void
    {
        if (!self::current_user_can_access()) {
            wp_die('دسترسی غیرمجاز.', 'دسترسی غیرمجاز', ['response' => 403]);
        }
    }

    /** آیا کاربر جاری اجازه‌ی دیدن پرونده‌ی همه‌ی درمانگرها را دارد؟ */
    public static function current_user_can_view_all(): bool
    {
        return current_user_can(PAS_CF_CAP_ALL);
    }

    /**
     * آیا کاربر جاری اجازه دارد این پرونده‌ی مشخص را ببیند؟ سرپرست‌ها
     * (PAS_CF_CAP_ALL) همیشه بله. در غیر این صورت:
     *   ۱) اگر assigned_therapist همین کاربر باشد → بله.
     *   ۲) اگر پرونده هنوز assigned_therapist=0 دارد (یعنی قبل از ۱.۲.۰
     *      ساخته شده و آن‌موقع اصلاً فیلد تخصیص در فرم وجود نداشت، یا در
     *      نسخه‌ی جدید عمداً «تخصیص‌نیافته» رها شده) → فقط اگر همین کاربر
     *      آن را ساخته باشد (created_by) اجازه دارد. بدون این استثنا،
     *      آپدیت از ۱.۱.۱ باعث می‌شد هر پرونده‌ی قبلاً ساخته‌شده برای
     *      درمانگری که خودش ساخته هم ناگهان غیرقابل‌دسترس شود.
     */
    public static function current_user_can_access_case(object $case): bool
    {
        return self::user_can_access_case_row(
            get_current_user_id(),
            (int) ($case->assigned_therapist ?? 0),
            (int) ($case->created_by ?? 0)
        );
    }

    /**
     * قانون واحد دسترسی به یک پرونده. **همه‌ی** مسیرها باید از همین یک
     * قاعده نتیجه بگیرند:
     *   - بررسی شیء: current_user_can_access_case() → همین متد
     *   - کوئری لیست/گزارش: case_scope_sql() → همان شرط، در SQL
     *   - نمای زوجی: current_user_can_access_case(پرونده‌ی پارتنر)
     *
     * اگر این قاعده تغییر کرد، هر سه جا با هم عوض می‌شوند؛ این متد و
     * case_scope_sql عمداً کنار هم و با یک کامنت نگه داشته شده‌اند.
     */
    public static function user_can_access_case_row(int $user_id, int $assigned_therapist, int $created_by, ?bool $view_all = null): bool
    {
        $view_all = $view_all ?? self::current_user_can_view_all();

        if ($view_all) {
            return true;
        }

        if ($assigned_therapist === $user_id) {
            return true;
        }

        return $assigned_therapist === 0 && $created_by === $user_id;
    }

    /**
     * همان قانون بالا، به شکل یک شرط SQL قابل استفاده در WHERE.
     *
     * @param int    $user_id  کاربری که محدوده برایش محاسبه می‌شود
     * @param string $alias    نام مستعار جدول پرونده‌ها در کوئری ('c' یا '')
     * @param bool   $view_all آیا این کاربر «همه‌ی پرونده‌ها» را می‌بیند
     * @return array{0:string,1:array<int,int>} [شرط, پارامترها]
     */
    public static function case_scope_sql(int $user_id, string $alias = 'c', bool $view_all = false): array
    {
        if ($view_all) {
            return ['1=1', []];
        }

        $prefix = $alias !== '' ? $alias . '.' : '';

        return [
            "({$prefix}assigned_therapist = %d OR ({$prefix}assigned_therapist = 0 AND {$prefix}created_by = %d))",
            [$user_id, $user_id],
        ];
    }

    /**
     * محدوده‌ی کاربر جاری — برای کوئری‌های گزارش/لیست.
     *
     * @return array{0:string,1:array<int,int>}
     */
    public static function current_user_case_scope(string $alias = 'c'): array
    {
        return self::case_scope_sql(
            get_current_user_id(),
            $alias,
            self::current_user_can_view_all()
        );
    }

    public static function require_case_access(object $case): void
    {
        if (!self::current_user_can_access_case($case)) {
            wp_die('این پرونده به شما تخصیص داده نشده است.', 'دسترسی غیرمجاز', ['response' => 403]);
        }
    }

    /**
     * نکته: قبلاً اینجا function_exists('get_client_ip') چک می‌شد به این
     * امید که اگر پنل مراجعان فعال است، از تابعش استفاده شود — ولی آن
     * تابع داخل namespace ZoojDarman\ClientPortal است، نه global، پس آن
     * چک هیچ‌وقت true نمی‌شد و همیشه fallback ساده اجرا می‌شد. حالا به‌جای
     * حدس زدن namespace افزونه‌ی دیگر، از هسته‌ی مشترک (class بدون
     * namespace، برای همین منظور ساخته شده) استفاده می‌شود.
     */
    public static function get_client_ip(): string
    {
        if (class_exists('Pas_Core_Helpers')) {
            return Pas_Core_Helpers::get_client_ip();
        }

        return isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '';
    }
}
