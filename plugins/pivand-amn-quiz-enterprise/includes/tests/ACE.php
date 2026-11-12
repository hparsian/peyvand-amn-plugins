<?php
namespace PsychAssessments\Tests;
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * ACE: Adverse Childhood Experiences Study Questionnaire (CDC/Kaiser, 1998)
 *
 * - Felitti, V.J., Anda, R.F., et al. (1998). Relationship of childhood abuse and household
 *   dysfunction to many of the leading causes of death in adults.
 *   American Journal of Preventive Medicine, 14(4), 245–258.
 * - ۱۰ سوال بله/خیر — نمره ۰–۱۰
 * - هر سوال معرف یک دسته تجربه ناگوار دوران کودکی
 */
class ACE extends \PsychAssessments\Core\BaseTest {

    public function needs_chart(): bool { return true; } // باگ #ACE-1: chart bar سه حوزه نیاز به Chart.js دارد

    public function get_id(): string        { return 'ace'; }
    public function get_category(): string      { return 'mental_health'; }
    public function get_name(): string      { return 'تجربیات ناگوار دوران کودکی (ACE)'; }
    public function get_description(): string { return 'پرسشنامه ACE — ۱۰ سوال بله/خیر، ارزیابی تجربیات آسیب‌زای دوران کودکی'; }
    public function get_version(): string   { return '1.0.0'; }

    public function get_scale(): array {
        return [
            'min'    => 0,
            'max'    => 1,
            'labels' => [
                0 => 'خیر',
                1 => 'بله',
            ],
        ];
    }

    public function get_questions(): array {
        return [
            1  => 'آیا والدین یا بزرگسالان در خانه شما را به گونه‌ای که آسیب‌رسان بود ناسزا می‌گفتند، تحقیر می‌کردند، خوار می‌شمردند یا می‌ترساندند؟',
            2  => 'آیا والدین یا بزرگسالان در خانه شما را به شکلی که آسیب‌رسان بود هل می‌دادند، چنگ می‌زدند، سیلی می‌زدند، پرتاب می‌کردند یا گاهی کبود می‌شدید؟',
            3  => 'آیا بزرگسالی حداقل ۵ سال از شما بزرگتر تا ۱۸ سالگی با شما لمس جنسی داشت یا سعی کرد؟',
            4  => 'آیا اغلب احساس می‌کردید هیچ‌کس در خانواده‌تان شما را دوست ندارد؟',
            5  => 'آیا اغلب احساس می‌کردید به اندازه کافی غذا نمی‌خورید، لباس کثیف می‌پوشید یا از شما مراقبت نمی‌شد؟',
            6  => 'آیا والدینتان جدا شدند یا طلاق گرفتند؟',
            7  => 'آیا مادرتان (یا نامادری) اغلب هل داده، قاپیده، سیلی خورده یا با چیزی کتک خورده یا لگد خورده است؟',
            8  => 'آیا با کسی زندگی می‌کردید که مشکل نوشیدن الکل یا مصرف مواد مخدر داشت؟',
            9  => 'آیا عضوی از خانواده‌تان افسرده یا بیمار روانی بود یا اقدام به خودکشی کرد؟',
            10 => 'آیا عضوی از خانواده‌تان به زندان رفت؟',
        ];
    }

    public function get_schema_labels(): array {
        return [
            'abuse'      => 'آزار (جسمی، عاطفی، جنسی)',
            'neglect'    => 'غفلت',
            'household'  => 'اختلال در خانواده',
        ];
    }

    public function get_domain_labels(): array { return []; }

    private function severity( int $total ): array {
        if ( $total === 0 ) return ['label'=>'بدون خطر',    'color'=>'#2f855a','bg'=>'#f0fff4','clinical'=>'هیچ تجربه ناگواری گزارش نشده'];
        if ( $total <= 1  ) return ['label'=>'خطر کم',      'color'=>'#38a169','bg'=>'#f0fff4','clinical'=>'خطر پایین — یک تجربه ناگوار'];
        if ( $total <= 3  ) return ['label'=>'خطر متوسط',  'color'=>'#d69e2e','bg'=>'#fffff0','clinical'=>'خطر متوسط — پایش و حمایت توصیه می‌شود'];
        if ( $total <= 6  ) return ['label'=>'خطر بالا',    'color'=>'#c05621','bg'=>'#fff5f0','clinical'=>'خطر بالا — مداخله و پیگیری لازم است'];
        return                     ['label'=>'خطر خیلی بالا','color'=>'#9b2c2c','bg'=>'#fff5f5','clinical'=>'خطر خیلی بالا — مداخله تخصصی ضروری است'];
    }

    public function calculate( array $answers ): array {
        $answers = \PsychAssessments\Core\Scoring::sanitize_answers( $answers );
        $total = 0;
        for ( $i = 1; $i <= 10; $i++ ) {
            $total += (int)(($answers[$i] ?? 0) >= 1);
        }

        $schema_scores = [
            'abuse'     => (float)((int)(($answers[1]??0)>=1) + (int)(($answers[2]??0)>=1) + (int)(($answers[3]??0)>=1)),
            'neglect'   => (float)((int)(($answers[4]??0)>=1) + (int)(($answers[5]??0)>=1)),
            'household' => (float)((int)(($answers[6]??0)>=1) + (int)(($answers[7]??0)>=1) + (int)(($answers[8]??0)>=1) + (int)(($answers[9]??0)>=1) + (int)(($answers[10]??0)>=1)),
        ];

        return [
            'schema_scores' => $schema_scores,
            'total'         => $total,
            'avg_total'     => (float)$total,
        ];
    }

    public function render_report( array $scores, object $session ): string {
        $completed = pa_to_jalali( $session->completed_at, 'Y/m/d ساعت H:i' );
        $sev       = $this->severity( $scores['total'] );

        ob_start(); ?>
        <div style="display:flex;gap:12px;flex-wrap:wrap;margin-bottom:24px;">
            <?php foreach ([
                ['#e9d8fd','#553c9a','👤','نام',$session->client_name ?? $session->patient_name ?? '---'],
                ['#ebf8ff','#2b6cb0','📞','شماره تماس',$session->client_phone ?? $session->patient_phone ?? '---'],
                ['#f0fff4','#2f855a','✅','تاریخ تکمیل',$completed],
                [$sev['bg'],$sev['color'],'🧒','نمره ACE',$scores['total'].' از ۱۰'],
                [$sev['bg'],$sev['color'],'⚠️','سطح خطر',$sev['label']],
            ] as $c) echo \PsychAssessments\Core\ReportHTML::summary_card($c[0],$c[1],$c[2],$c[3],$c[4]); ?>
        </div>

        <div style="background:<?php echo esc_attr($sev['bg']); ?>;border:2px solid <?php echo esc_attr($sev['color']); ?>;border-radius:10px;padding:20px;margin-bottom:24px;">
            <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;">
                <div>
                    <h3 style="margin:0 0 6px;color:<?php echo esc_attr($sev['color']); ?>;">نمره ACE: <?php echo esc_html((int)$scores['total']); ?> از ۱۰</h3>
                    <p style="margin:0;font-size:13px;color:#4a5568;"><?php echo esc_html($sev['clinical']); ?></p>
                </div>
                <?php echo \PsychAssessments\Core\ReportHTML::score_badge((float)$scores['total'], $sev); ?>
            </div>
            <div style="margin-top:14px;background:#edf2f7;border-radius:6px;height:10px;overflow:hidden;">
                <div style="background:<?php echo esc_attr($sev['color']); ?>;height:100%;width:<?php echo esc_attr((int)$scores['total'] * 10); ?>%;border-radius:6px;"></div>
            </div>
            <div style="display:flex;justify-content:space-between;font-size:10px;color:#a0aec0;margin-top:4px;">
                <span>۰</span><span style="color:#38a169;">۱</span><span style="color:#d69e2e;">۴</span><span style="color:#c05621;">۷</span><span>۱۰</span>
            </div>
        </div>

        <?php echo \PsychAssessments\Core\ReportHTML::section_header('سه حوزه تجربیات ناگوار'); ?>
        <div style="background:#fff;padding:20px;border-radius:10px;box-shadow:0 2px 10px rgba(0,0,0,0.08);margin-bottom:24px;">
            <canvas id="pa-ace-bar" style="max-height:200px;"></canvas>
        </div>

        <div style="background:#fefce8;border:1px solid #f6e05e;border-radius:10px;padding:16px 20px;margin-bottom:20px;font-size:13px;color:#744210;">
            <strong>⚠️ توجه بالینی:</strong><br>
            تحقیقات نشان می‌دهد نمره ACE ≥ ۴ با افزایش معنادار خطر افسردگی، اضطراب، بیماری‌های قلبی، دیابت و اختلالات مصرف مواد در بزرگسالی همراه است. این یک ابزار غربالگری است و نیاز به ارزیابی بالینی جامع دارد.
        </div>

        <div style="background:#fff;border:1px solid #e2e8f0;border-radius:10px;padding:16px 20px;margin-bottom:20px;">
            <h3 style="margin-top:0;font-size:15px;">📊 ارتباط نمره ACE با خطرات بهداشتی</h3>
            <table style="width:100%;border-collapse:collapse;font-size:12px;">
                <thead><tr style="background:#edf2f7;">
                    <th style="padding:8px;border:1px solid #e2e8f0;text-align:right;">نمره</th>
                    <th style="padding:8px;border:1px solid #e2e8f0;text-align:right;">سطح</th>
                    <th style="padding:8px;border:1px solid #e2e8f0;text-align:right;">اقدام</th>
                </tr></thead>
                <tbody>
                <?php foreach ([
                    ['۰',   'بدون خطر',     '#2f855a', 'اطلاع‌رسانی پیشگیرانه'],
                    ['۱',   'خطر کم',       '#38a169', 'حمایت و آموزش'],
                    ['۲–۳', 'خطر متوسط',   '#d69e2e', 'پایش و مشاوره'],
                    ['۴–۶', 'خطر بالا',     '#c05621', 'مداخله روان‌شناختی'],
                    ['۷+',  'خطر خیلی بالا','#9b2c2c', 'درمان تخصصی تروما'],
                ] as $r): ?>
                <tr>
                    <td style="padding:7px 8px;border:1px solid #e2e8f0;font-weight:bold;color:<?php echo esc_attr($r[2]); ?>;"><?php echo esc_html($r[0]); ?></td>
                    <td style="padding:7px 8px;border:1px solid #e2e8f0;color:<?php echo esc_attr($r[2]); ?>;"><?php echo esc_html($r[1]); ?></td>
                    <td style="padding:7px 8px;border:1px solid #e2e8f0;color:#4a5568;"><?php echo esc_html($r[3]); ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div style="background:#fff;border:1px solid #e2e8f0;border-radius:8px;padding:14px 20px;font-size:11px;color:#a0aec0;line-height:1.8;">
            <b style="color:#4a5568;">منبع:</b> Felitti et al. (1998). Am J Prev Med, 14(4), 245–258. CDC/Kaiser ACE Study.<br>
            <b style="color:#c53030;">⚠️</b> ابزار غربالگری — مبنای تشخیص رسمی نیست. محرمانه نگه داشته شود.
        </div>

        <script>
        (function(){
            function init(){
                if(typeof Chart==='undefined'){setTimeout(init,100);return;}
                var ctx=document.getElementById('pa-ace-bar');
                if(!ctx)return;
                new Chart(ctx.getContext('2d'),{
                    type:'bar',
                    data:{
                        labels:['آزار (جسمی/عاطفی/جنسی)','غفلت','اختلال در خانواده'],
                        datasets:[{
                            data:[<?php echo intval($scores['schema_scores']['abuse']); ?>,<?php echo intval($scores['schema_scores']['neglect']); ?>,<?php echo intval($scores['schema_scores']['household']); ?>],
                            backgroundColor:['#fc8181','#f6ad55','#90cdf4'],
                            borderRadius:5
                        }]
                    },
                    options:{responsive:true,plugins:{legend:{display:false}},scales:{y:{min:0,max:5,ticks:{stepSize:1}}}}
                });
            }
            document.addEventListener('DOMContentLoaded',init);
        })();
        </script>
        <?php
        return ob_get_clean();
    }
}
\PsychAssessments\Core\Registry::register( new ACE() );
