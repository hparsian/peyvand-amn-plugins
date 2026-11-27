<?php
namespace PsychAssessments\Core;
if ( ! defined( 'ABSPATH' ) ) exit;

class Scoring {

    /**
     * پاک‌سازی آرایه پاسخ‌ها — هر مقدار به int تبدیل می‌شود
     * باید در ابتدای هر calculate() فراخوانی شود
     */
    public static function sanitize_answers( array $answers ): array {
        return array_map( 'intval', $answers );
    }

    /**
     * محاسبه نمره زیرمقیاس
     *
     * @param int $min  کف مقیاس (پیش‌فرض 0)
     * @param int $max  سقف مقیاس per-question — اگر null باشد upper clamp اعمال نمی‌شود
     */
    public static function subscale_sum( array $question_ids, array $answers, int $min = 0, ?int $max = null ): int {
        $sum = 0;
        foreach ( $question_ids as $qid ) {
            // BUG-16: سوال بی‌پاسخ به ۰ (نه $min) fallback می‌شود تا نمره inflate نشود
            // clamp بعداً اعمال می‌شود و هر دو را به بازه مجاز می‌کشد
            $val = isset( $answers[$qid] ) ? intval( $answers[$qid] ) : 0;
            $val = max( $min, $val );
            if ( $max !== null ) {
                $val = min( $max, $val );
            }
            $sum += $val;
        }
        return $sum;
    }

    /**
     * محاسبه میانگین نمره زیرمقیاس
     * مورد استفاده در YSQ، LAS، SCL-90-R
     */
    public static function subscale_avg( array $question_ids, array $answers, int $min = 0, ?int $max = null ): float {
        $count = count( $question_ids );
        if ( $count === 0 ) return 0.0;
        return round( self::subscale_sum( $question_ids, $answers, $min, $max ) / $count, 2 );
    }

    /**
     * تعیین سطح شدت بر اساس آستانه‌های تعریف‌شده
     */
    public static function severity( float $score, array $levels ): array {
        foreach ( $levels as $level ) {
            if ( ! isset( $level['max'] ) || $score <= $level['max'] ) {
                return $level;
            }
        }
        return end( $levels );
    }

    /**
     * تبدیل نمره به درصد
     */
    public static function percent( float $score, float $min, float $max ): float {
        if ( $max <= $min ) return 0;
        return round( ( ( $score - $min ) / ( $max - $min ) ) * 100, 1 );
    }

    /**
     * آستانه بالینی دودویی (cut-off) را از همان آرایه‌ی severity استخراج می‌کند،
     * به‌جای اینکه عدد مرز جداگانه و دستی در جای دیگری از کد هاردکد شود.
     *
     * چرا این تابع لازم است:
     * اگر یک آزمون هم از Scoring::severity() برای نمایش سطح شدت استفاده کند
     * و هم جداگانه یک شرط مثل `$total >= 44.5` برای یک برچسب/رنگ دیگر بنویسد،
     * دو منبع مستقل برای یک عدد بالینی به وجود می‌آید. اگر بعداً فقط یکی از
     * این دو جا به‌روزرسانی شود، آن دو با هم ناهم‌خوان می‌شوند — دقیقاً همان
     * دسته باگی که در CSI رخ داد.
     *
     * @param float $score        نمره محاسبه‌شده
     * @param array $levels       همان آرایه سطوحی که به Scoring::severity() داده می‌شود
     *                            (لیستی صعودی از سطوح، هرکدام با کلید 'max' به‌جز آخرین سطح)
     * @param int   $after_index  اندیس سطحی که مرز بالینی بلافاصله بعد از آن قرار دارد؛
     *                            نتیجه true است وقتی نمره از 'max' همین سطح عبور کرده باشد
     * @return bool
     */
    public static function is_above_level( float $score, array $levels, int $after_index ): bool {
        if ( ! isset( $levels[ $after_index ]['max'] ) ) {
            throw new \InvalidArgumentException(
                "Scoring::is_above_level — سطح اندیس {$after_index} فاقد کلید 'max' است."
            );
        }
        return $score > $levels[ $after_index ]['max'];
    }

    /**
     * مقدار عددی مرز (cut-off) را از سطح مشخص‌شده برمی‌گرداند — برای نمایش در متن UI
     * (مثل «نقطه برش: ۴۴.۵») بدون آنکه عدد دوباره جدا نوشته شود.
     */
    public static function level_cutoff( array $levels, int $after_index ): float {
        if ( ! isset( $levels[ $after_index ]['max'] ) ) {
            throw new \InvalidArgumentException(
                "Scoring::level_cutoff — سطح اندیس {$after_index} فاقد کلید 'max' است."
            );
        }
        return (float) $levels[ $after_index ]['max'];
    }
}
