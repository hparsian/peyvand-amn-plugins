<?php
namespace PsychAssessments\Core;
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * BaseTest — کلاس پایه انتزاعی برای تمام آزمون‌ها
 *
 * پیاده‌سازی پیش‌فرض متدهایی را فراهم می‌کند که در اکثر آزمون‌ها یکسان هستند.
 * آزمون‌ها می‌توانند هر متد را override کنند.
 *
 * باگ #11: needs_chart() با مقدار پیش‌فرض true اینجا تعریف شده
 * تا آزمون‌هایی که نمودار ندارند (مثل ACE) بتوانند false برگردانند
 * و Chart.js برای آن‌ها لود نشود.
 */
abstract class BaseTest implements TestInterface {

    /**
     * آیا این آزمون به نمودار رادار/خطی نیاز دارد؟
     * آزمون‌هایی که فقط نمره کلی دارند می‌توانند false برگردانند.
     */
    public function needs_chart(): bool {
        return true;
    }

    /**
     * پیاده‌سازی پیش‌فرض برای آزمون‌هایی که زیرمقیاس ندارند.
     */
    public function get_schema_labels(): array {
        return [];
    }

    public function get_domain_labels(): array {
        return [];
    }
}
