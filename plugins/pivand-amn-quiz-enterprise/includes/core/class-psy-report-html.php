<?php
namespace PsychAssessments\Core;
if ( ! defined( 'ABSPATH' ) ) exit;

class ReportHTML {

    public static function score_badge( float $score, array $sev ): string {
        return sprintf(
            '<div style="background:%s;color:#fff;border-radius:50px;padding:8px 20px;font-size:15px;font-weight:bold;white-space:nowrap;">%s</div>',
            esc_attr( $sev['color'] ?? '#718096' ),
            esc_html( $sev['label'] ?? '' )
        );
    }

    public static function summary_card( string $bg, string $color, string $icon, string $label, string $value, string $sub = '' ): string {
        return sprintf(
            '<div style="flex:1;min-width:140px;background:%s;border-radius:10px;padding:14px 18px;border-top:4px solid %s;">
                <div style="font-size:11px;color:%s;font-weight:bold;margin-bottom:4px;">%s %s</div>
                <div style="font-size:26px;font-weight:900;color:%s;">%s</div>
                %s
            </div>',
            esc_attr($bg), esc_attr($color), esc_attr($color),
            esc_html($icon), esc_html($label),
            esc_attr($color),
            esc_html($value),
            $sub ? '<div style="font-size:11px;color:#718096;margin-top:4px;">'.esc_html($sub).'</div>' : ''
        );
    }

    public static function progress_bar( float $pct, string $color ): string {
        return sprintf(
            '<div style="background:#edf2f7;border-radius:4px;height:8px;overflow:hidden;margin-top:8px;">
                <div style="background:%s;height:100%%;width:%s%%;border-radius:4px;"></div>
            </div>',
            esc_attr($color), min(100, max(0, round($pct)))
        );
    }

    public static function section_header( string $title ): string {
        return sprintf(
            '<h3 style="margin:24px 0 12px;font-size:15px;color:#2d3748;border-bottom:2px solid #e2e8f0;padding-bottom:8px;">%s</h3>',
            esc_html($title)
        );
    }

    /**
     * کارت بالینی برای نمایش طرحواره‌های فعال (YSQ) و زیرمقیاس‌های بالینی (SCL90R)
     * توجه: wp_kses_post() در clinical_note() فقط برای string های trusted استفاده شود.
     *
     * @param string $label    نام طرحواره / زیرمقیاس
     * @param string $key      کلید انگلیسی (برای data-attribute)
     * @param float  $value    نمره
     * @param array  $sev      آرایه severity شامل color, bg, label, label_en, clinical
     * @param string $desc     توضیح بالینی طرحواره
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
        $color = esc_attr( $sev['color'] ?? '#c05621' );
        $bg    = esc_attr( $sev['bg']    ?? '#fffaf0' );
        $out   = '<div style="background:' . $bg . ';border:1px solid ' . $color . '33;'
               . 'border-right:4px solid ' . $color . ';border-radius:8px;'
               . 'padding:14px 16px;margin-bottom:10px;" data-schema="' . esc_attr($key) . '">';

        // سطر عنوان + نمره
        $out .= '<div style="display:flex;justify-content:space-between;align-items:center;'
              . 'flex-wrap:wrap;gap:8px;margin-bottom:8px;">';
        $out .= '<span style="font-size:14px;font-weight:bold;color:' . $color . ';">'
              . esc_html($label) . '</span>';
        $out .= '<span style="background:' . $color . ';color:#fff;border-radius:20px;'
              . 'padding:4px 14px;font-size:13px;font-weight:bold;white-space:nowrap;">'
              . esc_html( number_format($value, 2) ) . ' — '
              . esc_html( $sev['label'] ?? '' ) . '</span>';
        $out .= '</div>';

        // توضیح
        if ( $desc ) {
            $out .= '<p style="margin:0 0 6px;font-size:12px;color:#4a5568;line-height:1.7;">'
                  . esc_html($desc) . '</p>';
        }

        // توصیه بالینی
        if ( $clinical ) {
            $out .= '<div style="font-size:11px;color:' . $color . ';font-style:italic;margin-top:4px;">'
                  . '💡 ' . esc_html($clinical) . '</div>';
        }

        $out .= '</div>';
        return $out;
    }

    public static function clinical_note( string $text, string $color = '#c05621', string $bg = '#fffaf0' ): string {
        return sprintf(
            '<div style="background:%s;border-left:4px solid %s;border-radius:6px;padding:12px 16px;margin:16px 0;font-size:12px;color:#4a5568;line-height:1.8;">%s</div>',
            esc_attr($bg), esc_attr($color), wp_kses_post($text)
        );
    }
}
