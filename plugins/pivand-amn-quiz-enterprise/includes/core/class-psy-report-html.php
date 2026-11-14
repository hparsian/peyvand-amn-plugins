<?php
namespace PsychAssessments\Core;
if ( ! defined( 'ABSPATH' ) ) exit;

class ReportHTML {

    /**
     * نقشهٔ رنگ‌های بالینی → تُن.
     * فاز ۱۴.۴: به‌جای `style="background:#…"` رنگ‌ها با کلاس `psy-tone--*` اعمال می‌شوند
     * (تعریف کلاس‌ها در assets/css/frontend.css). رنگ ناشناخته → neutral.
     */
    private const TONE_BY_HEX = [
        // موفق / بهنجار
        '#276749' => 'success', '#2f855a' => 'success', '#38a169' => 'success',
        '#22543d' => 'success', '#68d391' => 'success', '#c6f6d5' => 'success',
        '#f0fff4' => 'success',
        // هشدار
        '#d69e2e' => 'warning', '#f6e05e' => 'warning', '#744210' => 'warning',
        '#975a16' => 'warning', '#f6ad55' => 'warning', '#fefcbf' => 'warning',
        '#fffff0' => 'warning',
        // نارنجی / توجه
        '#c05621' => 'accent',  '#dd6b20' => 'accent',  '#b7791f' => 'accent',
        '#92400e' => 'accent',  '#fef3c7' => 'accent',  '#fff5f0' => 'accent',
        '#ed8936' => 'accent',  '#fffaf0' => 'accent',
        // خطر
        '#c53030' => 'danger',  '#9b2c2c' => 'danger',  '#742a2a' => 'danger',
        '#e53e3e' => 'danger',  '#fc8181' => 'danger',  '#fff5f5' => 'danger',
        '#fff5f7' => 'danger',
        // اطلاع
        '#2b6cb0' => 'info',    '#3182ce' => 'info',    '#1a365d' => 'info',
        '#2c5282' => 'info',    '#4299e1' => 'info',    '#90cdf4' => 'info',
        '#bee3f8' => 'info',    '#ebf8ff' => 'info',    '#2a4365' => 'info',
        // بنفش / طرح‌واره
        '#553c9a' => 'purple',  '#6b46c1' => 'purple',  '#b794f4' => 'purple',
        '#97266d' => 'purple',  '#e9d8fd' => 'purple',  '#f3e8ff' => 'purple',
        '#faf5ff' => 'purple',
        // خنثی
        '#718096' => 'neutral', '#a0aec0' => 'neutral', '#4a5568' => 'neutral',
        '#2d3748' => 'neutral', '#edf2f7' => 'neutral', '#f7fafc' => 'neutral',
        '#e2e8f0' => 'neutral',
    ];

    /** کلاس تُن برای یک رنگ هگز — جایگزین استایل درون‌خطی رنگ */
    public static function tone( string $hex ): string {
        $key = strtolower( trim( $hex ) );
        if ( $key !== '' && $key[0] !== '#' ) {
            $key = '#' . $key;
        }
        return 'psy-tone--' . ( self::TONE_BY_HEX[ $key ] ?? 'neutral' );
    }

    /**
     * کلاس عرض نوار پیشرفت (۰ تا ۱۰۰ در گام‌های ۵٪).
     * جایگزین `style="width:X%"` تا مارک‌آپ گزارش هیچ استایل درون‌خطی نداشته باشد.
     */
    public static function pct_class( float $pct ): string {
        $step = (int) ( round( min( 100, max( 0, $pct ) ) / 5 ) * 5 );
        return 'psy-w--' . $step;
    }

    public static function score_badge( float $score, array $sev ): string {
        return sprintf(
            '<div class="psy-score-badge %s">%s</div>',
            self::tone( (string) ( $sev['color'] ?? '#718096' ) ),
            esc_html( $sev['label'] ?? '' )
        );
    }

    public static function summary_card( string $bg, string $color, string $icon, string $label, string $value, string $sub = '' ): string {
        return sprintf(
            '<div class="psy-report-card %s">
                <div class="psy-report-card__label">%s %s</div>
                <div class="psy-report-card__value">%s</div>
                %s
            </div>',
            self::tone( $color ),
            esc_html( $icon ), esc_html( $label ),
            esc_html( $value ),
            $sub ? '<div class="psy-report-card__sub">' . esc_html( $sub ) . '</div>' : ''
        );
    }

    public static function progress_bar( float $pct, string $color, bool $tall = false ): string {
        return sprintf(
            '<div class="psy-report-progress%s %s"><div class="psy-report-progress__bar %s"></div></div>',
            $tall ? ' psy-report-progress--tall' : '',
            self::tone( $color ),
            self::pct_class( $pct )
        );
    }

    public static function section_header( string $title ): string {
        return '<h3 class="psy-report-section-title">' . esc_html( $title ) . '</h3>';
    }

    /**
     * کارت بالینی برای نمایش طرح‌واره‌های فعال (YSQ) و زیرمقیاس‌های بالینی (SCL90R)
     * توجه: wp_kses_post() در clinical_note() فقط برای string های trusted استفاده شود.
     *
     * @param string $label    نام طرح‌واره / زیرمقیاس
     * @param string $key      کلید انگلیسی (برای data-attribute)
     * @param float  $value    نمره
     * @param array  $sev      آرایه severity شامل color, bg, label, label_en, clinical
     * @param string $desc     توضیح بالینی طرح‌واره
     * @param string $clinical توصیه / تفسیر بالینی
     */
    public static function clinical_card(
        string $label,
        string $key,
        float  $value,
        array  $sev,
        string $desc     = '',
        string $clinical = ''
    ): string {
        $tone  = self::tone( (string) ( $sev['color'] ?? '#c05621' ) );
        $out   = '<div class="psy-clinical ' . $tone . '" data-schema="' . esc_attr( $key ) . '">';

        // سطر عنوان + نمره
        $out .= '<div class="psy-clinical__head">';
        $out .= '<span class="psy-clinical__name">' . esc_html( $label ) . '</span>';
        $out .= '<span class="psy-score-badge psy-score-badge--sm">'
              . esc_html( number_format( $value, 2 ) ) . ' — '
              . esc_html( $sev['label'] ?? '' ) . '</span>';
        $out .= '</div>';

        // توضیح
        if ( $desc ) {
            $out .= '<p class="psy-clinical__desc">' . esc_html( $desc ) . '</p>';
        }

        // توصیه بالینی
        if ( $clinical ) {
            $out .= '<div class="psy-clinical__note">💡 ' . esc_html( $clinical ) . '</div>';
        }

        $out .= '</div>';
        return $out;
    }

    public static function clinical_note( string $text, string $color = '#c05621', string $bg = '#fffaf0' ): string {
        return sprintf(
            '<div class="psy-report-note %s">%s</div>',
            self::tone( $color ),
            wp_kses_post( $text )
        );
    }
}
