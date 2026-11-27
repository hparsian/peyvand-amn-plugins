<?php
namespace PsychAssessments\Tests;
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * AUDIT: Alcohol Use Disorders Identification Test (WHO, Babor et al., 2001)
 *
 * - Babor, T.F., Higgins-Biddle, J.C., Saunders, J.B., & Monteiro, M.G. (2001).
 *   AUDIT: The Alcohol Use Disorders Identification Test (2nd ed.). WHO.
 * - ۱۰ سوال — سوالات ۱–۸ نمره ۰–۴، سوالات ۹–۱۰ نمره ۰،۲،۴
 * - cut-off: 0–7 کم‌خطر | 8–15 مصرف مشکل‌ساز | 16–19 مضر | 20+ وابستگی احتمالی
 */
class AUDIT extends \PsychAssessments\Core\BaseTest {

    public function get_id(): string        { return 'audit'; }
    public function get_category(): string      { return 'mental_health'; }
    public function get_name(): string      { return 'غربالگری مصرف الکل (AUDIT)'; }
    public function needs_chart(): bool   { return true; }  // نمودار Chart.js

    public function get_description(): string { return 'آزمون شناسایی اختلالات مصرف الکل WHO — ۱۰ سوال'; }
    public function get_version(): string   { return '1.0.0'; }

    public function get_scale(): array {
        // مقیاس‌ها برای سوالات ۱–۸ (۹ و ۱۰ متفاوت هستند — در render مشخص می‌شود)
        return [
            'min'    => 0,
            'max'    => 4,
            'labels' => [
                0 => 'هیچ‌وقت',
                1 => 'ماهی یک بار یا کمتر',
                2 => 'ماهی ۲–۴ بار',
                3 => 'هفته‌ای ۲–۳ بار',
                4 => 'چهار بار در هفته یا بیشتر',
            ],
        ];
    }

    public function get_questions(): array {
        return [
            1  => 'چقدر مشروب الکلی می‌نوشید؟',
            2  => 'در یک روز معمولی که می‌نوشید، چند واحد الکل مصرف می‌کنید؟',
            3  => 'چند بار در یک نوبت ۶ واحد یا بیشتر مصرف می‌کنید؟',
            4  => 'چند بار در سال گذشته متوجه شدید که نمی‌توانید پس از شروع نوشیدن متوقف شوید؟',
            5  => 'چند بار در سال گذشته به خاطر نوشیدن الکل نتوانستید کاری را که از شما انتظار می‌رفت انجام دهید؟',
            6  => 'چند بار در سال گذشته برای اینکه صبح اول وقت بتوانید کار کنید به نوشیدن الکل نیاز داشتید؟',
            7  => 'چند بار در سال گذشته بعد از نوشیدن دچار احساس گناه یا پشیمانی شدید؟',
            8  => 'چند بار در سال گذشته نتوانستید یک اتفاق شب قبل را به خاطر بیاورید چون نوشیده بودید؟',
            9  => 'آیا تا به حال در اثر نوشیدن الکل به خودتان یا دیگری آسیب رساندید؟ (۰ = خیر | ۲ = بله، ولی نه در سال گذشته | ۴ = بله، در سال گذشته)',
            10 => 'آیا یکی از اقوام، دوست، پزشک یا کارمند مراقبت‌های بهداشتی تا به حال نگران نوشیدن شما شده یا پیشنهاد کاهش آن را داده است؟ (۰ = خیر | ۲ = بله، ولی نه در سال گذشته | ۴ = بله، در سال گذشته)',
        ];
    }

    public function get_schema_labels(): array {
        return [
            'consumption'  => 'مصرف الکل',
            'dependence'   => 'وابستگی',
            'harm'         => 'آسیب ناشی از الکل',
        ];
    }

    public function get_domain_labels(): array { return []; }

    /**
     * باگ P: سوالات ۹ و ۱۰ فقط گزینه‌های ۰، ۲، ۴ دارند نه ۰–۴.
     * بدون این override، فرانت‌اند مقیاس global (۰–۴ با برچسب دفعات مصرف) را
     * به مراجع نشان می‌داد که برای این دو سوال کاملاً اشتباه است.
     */
    public function get_question_scales(): array {
        $scale_9_10 = [
            'min'    => 0,
            'max'    => 4,
            'labels' => [
                0 => 'خیر',
                2 => 'بله، ولی نه در سال گذشته',
                4 => 'بله، در سال گذشته',
            ],
        ];
        return [ 9 => $scale_9_10, 10 => $scale_9_10 ];
    }

    private function severity( int $total ): array {
        if ( $total <= 7  ) return ['label'=>'کم‌خطر',           'color'=>'#2f855a','bg'=>'#f0fff4','clinical'=>'مصرف در محدوده کم‌خطر'];
        if ( $total <= 15 ) return ['label'=>'مشکل‌ساز',         'color'=>'#d69e2e','bg'=>'#fffff0','clinical'=>'مصرف مشکل‌ساز — مشاوره توصیه می‌شود'];
        if ( $total <= 19 ) return ['label'=>'مضر',               'color'=>'#c05621','bg'=>'#fff5f0','clinical'=>'مصرف مضر — مداخله کوتاه لازم است'];
        return                     ['label'=>'وابستگی احتمالی',  'color'=>'#9b2c2c','bg'=>'#fff5f5','clinical'=>'احتمال وابستگی — ارجاع به متخصص'];
    }

    public function calculate( array $answers ): array {
        $answers = \PsychAssessments\Core\Scoring::sanitize_answers( $answers );
        // سوالات ۹ و ۱۰: گزینه‌ها ۰، ۲، ۴ هستند نه ۰–۴
        $total = 0;
        for ( $i = 1; $i <= 8; $i++ ) {
            $total += $answers[$i] ?? 0;
        }
        // سوالات ۹ و ۱۰: نمره‌ها ۰، ۲ یا ۴ (ذخیره شده با همین مقادیر)
        $total += $answers[9]  ?? 0;
        $total += $answers[10] ?? 0;

        $schema_scores = [
            'consumption' => (float)(($answers[1]??0) + ($answers[2]??0) + ($answers[3]??0)),
            'dependence'  => (float)(($answers[4]??0) + ($answers[5]??0) + ($answers[6]??0)),
            'harm'        => (float)(($answers[7]??0) + ($answers[8]??0) + ($answers[9]??0) + ($answers[10]??0)),
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
                ['#ebf8ff','#2b6cb0','📞','شماره تماس',$session->client_phone ?? $session->patient_phone ?? '---'],
                ['#f0fff4','#2f855a','✅','تاریخ تکمیل',$completed],
                [$sev['bg'],$sev['color'],'🍺','نمره کل AUDIT',$scores['total'].' از ۴۰'],
                [$sev['bg'],$sev['color'],'🏷','سطح خطر',$sev['label'],$sev['label_en']],
            ] as $c) echo \PsychAssessments\Core\ReportHTML::summary_card($c[0],$c[1],$c[2],$c[3],$c[4]); ?>
        </div>

        <div class="psy-report-card--tint <?php echo esc_attr( \PsychAssessments\Core\ReportHTML::tone( (string) $sev['color'] ) ); ?>">
            <div class="psy-clinical__head">
                <div>
                    <h3 class="psy-report-hero__title">نمره کل: <?php echo esc_html((int)$scores['total']); ?> از ۴۰</h3>
                    <p class="psy-report-hero__text"><?php echo esc_html($sev['clinical']); ?></p>
                </div>
                <?php echo \PsychAssessments\Core\ReportHTML::score_badge((float)$scores['total'], $sev); ?>
            </div>
            <?php echo \PsychAssessments\Core\ReportHTML::progress_bar( (float) \PsychAssessments\Core\Scoring::percent($scores['total'],0,40), $sev['color'], true ); ?>
            <div class="psy-report-scale">
                <span>۰</span>
                <span class="psy-tone--success"><span class="psy-report-scale__mark psy-text">۷ کم‌خطر</span></span>
                <span class="psy-tone--warning"><span class="psy-report-scale__mark psy-text">۱۵ مشکل‌ساز</span></span>
                <span class="psy-tone--accent"><span class="psy-report-scale__mark psy-text">۱۹ مضر</span></span>
                <span class="psy-tone--danger"><span class="psy-report-scale__mark psy-text">۲۰+ وابستگی</span></span>
            </div>
        </div>

        <?php echo \PsychAssessments\Core\ReportHTML::section_header('سه حوزه ارزیابی'); ?>
        <div class="psy-report-card psy-report-card--panel psy-report-chart-panel">
            <canvas id="pa-audit-bar" class="psy-report-chart psy-report-chart--xs"></canvas>
        </div>

        <div class="psy-report-panel">
            <h3 class="psy-report-panel__title">📋 راهنمای تفسیر (WHO)</h3>
            <table class="psy-report-table">
                <thead>
                    <tr>
                        <th class="psy-report-table__cell--start">نمره</th>
                        <th class="psy-report-table__cell--start">سطح خطر</th>
                        <th class="psy-report-table__cell--start">اقدام پیشنهادی</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ([
                    ['۰–۷',  'کم‌خطر',          '#2f855a', 'آموزش بهداشت'],
                    ['۸–۱۵', 'مشکل‌ساز',        '#d69e2e', 'مشاوره ساده'],
                    ['۱۶–۱۹','مضر',              '#c05621', 'مداخله کوتاه + پیگیری'],
                    ['۲۰+',  'وابستگی احتمالی', '#9b2c2c', 'ارجاع به متخصص اعتیاد'],
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
            <b>منبع:</b> Babor et al. (2001). AUDIT: The Alcohol Use Disorders Identification Test (2nd ed.). WHO.<br>
            <b class="psy-tone--danger psy-text">⚠️</b> ابزار غربالگری — مبنای تشخیص رسمی نیست.
        </div>

        <script>
        (function(){
            function init(){
                if(typeof Chart==='undefined'){setTimeout(init,100);return;}
                var ctx=document.getElementById('pa-audit-bar');
                if(!ctx)return;
                new Chart(ctx.getContext('2d'),{
                    type:'bar',
                    data:{
                        labels:['مصرف الکل','وابستگی','آسیب'],
                        datasets:[{
                            data:[<?php echo intval($scores['schema_scores']['consumption']); ?>,<?php echo intval($scores['schema_scores']['dependence']); ?>,<?php echo intval($scores['schema_scores']['harm']); ?>],
                            backgroundColor:['#90cdf4','#f6ad55','#fc8181'],
                            borderRadius:5
                        }]
                    },
                    options:{responsive:true,plugins:{legend:{display:false}},scales:{y:{min:0,max:16}}}
                });
            }
            document.addEventListener('DOMContentLoaded',init);
        })();
        </script>
        <?php
        return ob_get_clean();
    }
}
\PsychAssessments\Core\Registry::register( new AUDIT() );
