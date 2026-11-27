<?php

declare(strict_types=1);

namespace ZoojDarman\ClientPortal;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * تنها نقطه‌ی تماس با psy-quiz-enterprise. عمداً فقط از فیلترهای وردپرسی
 * استفاده می‌کند و هیچ include/require مستقیمی به فایل‌های آن پلاگین ندارد؛
 * یعنی این پلاگین بدون خطا کار می‌کند حتی اگر psy-quiz-enterprise غیرفعال
 * باشد (فیلتر مقدار پیش‌فرض خالی را برمی‌گرداند).
 *
 * قرارداد حفظ‌شده (بدون تغییر نسبت به custom-psychology-portal):
 * - apply_filters('psy_get_pending_quizzes_for_phone', array $default, string $user_login)
 * - apply_filters('psy_get_client_name_for_phone', string $default, string $mobile)
 *
 * user_login در این پلاگین اکنون به‌صورت 'zdp_' . $mobile ساخته می‌شود (نه
 * صرفاً $mobile مثل قبل)، بنابراین اگر psy-quiz-enterprise مستقیماً روی
 * user_login مچ می‌کند، باید هنگام استانداردسازی آن پلاگین این نکته اصلاح
 * شود تا به‌جای user_login از متای zdp_mobile / cpp_billing_phone استفاده
 * کند. فعلاً برای سازگاری، شماره‌ی خام موبایل را (نه user_login جدید) به
 * فیلتر پاس می‌دهیم تا مطابقت با نسخه‌ی فعلی آن پلاگین حفظ شود.
 */
final class Quiz_Bridge
{
    /**
     * @return array<int,object>
     *
     * رفع باگ بالقوه (کشف‌شده هنگام افزودن حساب‌های ایمیلِ مستقل/خودسرویس):
     * قبلاً اگر $mobile خالی بود (کاربری که فقط با ایمیل و بدون هیچ موبایل
     * شناخته‌شده‌ای وارد شده)، همچنان از to_local_format('') عبور می‌کرد که
     * مقدار «0» (نه رشته‌ی خالی) برمی‌گرداند — یعنی یک رشته‌ی بی‌معنی و
     * گمراه‌کننده («0») به فیلتر بین‌افزونه‌ای psy_get_pending_quizzes_for_phone
     * پاس داده می‌شد. حالا این حالت زودتر و صریح [] برمی‌گرداند.
     */
    public function get_pending_quizzes_for_mobile(string $mobile): array
    {
        if ($mobile === '') {
            return [];
        }

        $mobile = to_local_format($mobile);
        $result = apply_filters('psy_get_pending_quizzes_for_phone', [], $mobile);

        return is_array($result) ? $result : [];
    }

    /**
     * نسخه‌ی ایمیلیِ متد بالا — برای مراجعینی که موبایل شناخته‌شده‌ای
     * ندارند (مثلاً مراجعین خارج از کشور که فقط با ایمیل وارد پنل شده‌اند).
     *
     * رفع باگ گزارش‌شده: چنین مراجعی قبلاً هیچ‌وقت آزمونش را در تب
     * «آزمون‌ها» نمی‌دید، چون Client_Panel::render_panel فقط از
     * get_pending_quizzes_for_mobile استفاده می‌کرد و آن، وقتی موبایلی در
     * کار نبود، همیشه [] برمی‌گرداند — مستقل از این‌که درمانگر برای همان
     * ایمیل آزمونی تعریف کرده باشد یا نه. از فیلتر جدید
     * psy_get_pending_quizzes_for_email (نگاه کنید به psy-quiz-enterprise
     * نسخه‌ی ۳.۶.۲ به بعد) استفاده می‌کند؛ روی نسخه‌های قدیمی‌تر آن افزونه
     * (که این فیلتر را ثبت نکرده‌اند)، apply_filters پایین‌تر همان مقدار
     * پیش‌فرض [] را برمی‌گرداند، پس این متد روی نسخه‌ی قدیمی هم بی‌صدا []
     * می‌دهد، بدون خطا — دقیقاً همان تضمینی که get_pending_quizzes_for_mobile
     * بالا هم دارد.
     */
    public function get_pending_quizzes_for_email(string $email): array
    {
        $email = sanitize_email($email);
        if ($email === '' || !is_email($email)) {
            return [];
        }

        $result = apply_filters('psy_get_pending_quizzes_for_email', [], $email);

        return is_array($result) ? $result : [];
    }

    public function is_quiz_plugin_active(): bool
    {
        return has_filter('psy_get_pending_quizzes_for_phone') !== false;
    }
}
