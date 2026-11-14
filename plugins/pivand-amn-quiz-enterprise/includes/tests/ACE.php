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
        <div class="psy-report-row">
            <?php foreach ([
                ['#e9d8fd','#553c9a','👤','نام',$session->client_name ?? $session->patient_name ?? '---'],
                ['#ebf8ff','#2b6cb0','📞','شماره تماس',$session->client_phone ?? $session->patient_phone ?? '---'],
                ['#f0fff4','#2f855a','✅','تاریخ تکمیل',$completed],
                [$sev['bg'],$sev['color'],'🧒','نمره ACE',$scores['total'].' از ۱۰'],
                [$sev['bg'],$sev['color'],'⚠️','سطح خطر',$sev['label'],$sev['label_en']],
            ] as $c) echo \PsychAssessments\Core\ReportHTML::summary_card($c[0],$c[1],$c[2],$c[3],$c[4]); ?>
        </div>

        <div class="psy-report-card--tint <?php echo esc_attr( \PsychAssessments\Core\ReportHTML::tone( (string) $sev['color'] ) ); ?>">
            <div class="psy-clinical__head">
                <div>
                    <h3 class="psy-report-hero__title">نمره ACE: <?php echo esc_html((int)$scores['total']); ?> از ۱۰</h3>
                    <p class="psy-report-hero__text"><?php echo esc_html($sev['clinical']); ?></p>
                </div>
                <?php echo \PsychAssessments\Core\ReportHTML::score_badge((float)$scores['total'], $sev); ?>
            </div>
            <?php echo \PsychAssessments\Core\ReportHTML::progress_bar( (float) ( (int)$scores['total'] * 10 ), (string) $sev['color'], true ); ?>
            <div class="psy-report-scale">
                <span>۰</span>
                <span class="psy-tone--success"><span class="psy-report-scale__mark psy-text">۱</span></span>
                <span class="psy-tone--warning"><span class="psy-report-scale__mark psy-text">۴</span></span>
                <span class="psy-tone--accent"><span class="psy-report-scale__mark psy-text">۷</span></span>
                <span>۱۰</span>
            </div>
        </div>

        <?php echo \PsychAssessments\Core\ReportHTML::section_header('سه حوزه تجربیات ناگوار'); ?>
        <div class="psy-report-card psy-report-card--panel psy-report-chart-panel">
            <canvas id="pa-ace-bar" class="psy-report-chart psy-report-chart--xs"></canvas>
        </div>

        <div class="psy-report-alert psy-tone--warning">
            <h3 class="psy-report-alert__title psy-report-alert__title--sm">⚠️ توجه بالینی:</h3>
            <p class="psy-report-alert__text psy-report-alert__text--sm">
                تحقیقات نشان می‌دهد نمره ACE ≥ ۴ با افزایش معنادار خطر افسردگی، اضطراب، بیماری‌های قلبی، دیابت و اختلالات مصرف مواد در بزرگسالی همراه است. این یک ابزار غربالگری است و نیاز به ارزیابی بالینی جامع دارد.
            </p>
        </div>

        <div class="psy-report-panel">
            <h3 class="psy-report-panel__title">📊 ارتباط نمره ACE با خطرات بهداشتی</h3>
            <table class="psy-report-table">
                <thead>
                    <tr>
                        <th class="psy-report-table__cell--start">نمره</th>
                        <th class="psy-report-table__cell--start">سطح</th>
                        <th class="psy-report-table__cell--start">اقدام</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ([
                    ['۰',   'بدون خطر',     '#2f855a', 'اطلاع‌رسانی پیشگیرانه'],
                    ['۱',   'خطر کم',       '#38a169', 'حمایت و آموزش'],
                    ['۲–۳', 'خطر متوسط',   '#d69e2e', 'پایش و مشاوره'],
                    ['۴–۶', 'خطر بالا',     '#c05621', 'مداخله روان‌شناختی'],
                    ['۷+',  'خطر خیلی بالا','#9b2c2c', 'درمان تخصصی تروما'],
                ] as $r): ?>
                <tr>
                    <td class="psy-report-table__cell--bold <?php echo esc_attr( \PsychAssessments\Core\ReportHTML::tone( $r[2] ) ); ?> psy-text"><?php echo esc_html($r[0]); ?></td>
                    <td class="<?php echo esc_attr( \PsychAssessments\Core\ReportHTML::tone( $r[2] ) ); ?> psy-text"><?php echo esc_html($r[1]); ?></td>
                    <td><?php echo esc_html($r[3]); ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="psy-report-refs psy-report-refs--muted">
            <b>منبع:</b> Felitti et al. (1998). Am J Prev Med, 14(4), 245–258. CDC/Kaiser ACE Study.<br>
            <b class="psy-tone--danger psy-text">⚠️</b> ابزار غربالگری — مبنای تشخیص رسمی نیست. محرمانه نگه داشته شود.
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
