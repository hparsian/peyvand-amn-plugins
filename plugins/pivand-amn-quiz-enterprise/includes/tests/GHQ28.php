<?php
namespace PsychAssessments\Tests;
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * GHQ-28: General Health Questionnaire (28 items, 4 subscales)
 *
 * - Goldberg, D.P. & Hillier, V.F. (1979). A scaled version of the General Health Questionnaire.
 *   Psychological Medicine, 9(1), 139–145.
 * - Palahang, H. et al. (1996). هنجاریابی GHQ-28 در ایران. Andishe va Raftar, 2(3), 43–51.
 * - نمره‌گذاری Likert: 0,1,2,3 (استاندارد) یا binary 0,0,1,1 (GHQ scoring)
 * - در این پیاده‌سازی از نمره‌گذاری Likert استفاده می‌شود (حساسیت بیشتر)
 * - cut-off کل ≥ 23 (Goldberg, 1978) — نمره‌گذاری Likert
 * - ۴ زیرمقیاس: جسمانی، اضطراب/بی‌خوابی، اختلال عملکرد اجتماعی، افسردگی شدید
 */
class GHQ28 extends \PsychAssessments\Core\BaseTest {

    public function get_id(): string        { return 'ghq28'; }
    public function get_category(): string      { return 'mental_health'; }
    public function get_name(): string      { return 'سلامت عمومی (GHQ-28)'; }
    public function needs_chart(): bool   { return true; }  // نمودار Chart.js

    public function get_description(): string { return 'پرسشنامه سلامت عمومی گلدبرگ — ۲۸ سوال، ۴ زیرمقیاس، cut-off ≥ ۲۳'; }
    public function get_version(): string   { return '1.0.0'; }

    public function get_scale(): array {
        return [
            'min'    => 0,
            'max'    => 3,
            'labels' => [
                0 => 'بهتر از حد معمول / اصلاً',
                1 => 'مثل همیشه / ندرتاً',
                2 => 'کمتر از حد معمول / گاهی',
                3 => 'خیلی کمتر از حد معمول / اغلب',
            ],
        ];
    }

    private function subscales(): array {
        return [
            'somatic'    => [
                'questions' => [1,2,3,4,5,6,7],
                'label'     => 'علائم جسمانی',
                'label_en'  => 'Somatic Symptoms',
                'color'     => '#c05621',
                'max'       => 21,
                'cutoff'    => 7,
            ],
            'anxiety'    => [
                'questions' => [8,9,10,11,12,13,14],
                'label'     => 'اضطراب و بی‌خوابی',
                'label_en'  => 'Anxiety & Insomnia',
                'color'     => '#c53030',
                'max'       => 21,
                'cutoff'    => 7,
            ],
            'social'     => [
                'questions' => [15,16,17,18,19,20,21],
                'label'     => 'اختلال عملکرد اجتماعی',
                'label_en'  => 'Social Dysfunction',
                'color'     => '#2b6cb0',
                'max'       => 21,
                'cutoff'    => 7,
                // فیکس: نیازی به معکوس‌سازی نیست — برچسب مقیاس مشترک (۰=بهتر از حد معمول
                // ... ۳=خیلی کمتر از حد معمول) از قبل هم‌جهت با سوالات علامتی است.
                // معکوس کردن این زیرمقیاس باعث می‌شد عملکرد خوب نمره بد بگیرد و برعکس.
            ],
            'depression' => [
                'questions' => [22,23,24,25,26,27,28],
                'label'     => 'افسردگی شدید',
                'label_en'  => 'Severe Depression',
                'color'     => '#553c9a',
                'max'       => 21,
                'cutoff'    => 2,
            ],
        ];
    }

    public function get_questions(): array {
        return [
            // علائم جسمانی
            1  => 'آیا احساس سلامت کامل داشته و از نظر جسمی سالم بوده‌اید؟',
            2  => 'آیا احساس نیاز به تقویتی یا ویتامینی داشته‌اید؟',
            3  => 'آیا احساس خستگی و ضعف کرده‌اید؟',
            4  => 'آیا احساس بیماری داشته‌اید؟',
            5  => 'آیا دردی در سرتان داشته‌اید؟',
            6  => 'آیا احساس فشاری در سرتان داشته‌اید؟',
            7  => 'آیا موهایتان ریخته یا احساس کرده‌اید موهایتان می‌ریزد؟',
            // اضطراب و بی‌خوابی
            8  => 'آیا شب‌ها از نگرانی‌ها بی‌خواب مانده‌اید؟',
            9  => 'آیا برای مدتی نتوانسته‌اید کاری انجام دهید چون اعصابتان ناراحت بوده؟',
            10 => 'آیا در کل روز احساس فشار عصبی کرده‌اید؟',
            11 => 'آیا احساس کرده‌اید که قادر نیستید مشکلات را از سر راه بردارید؟',
            12 => 'آیا احساس ناراحتی و دلشوره کرده‌اید؟',
            13 => 'آیا بدون دلیل کافی احساس ترس و وحشت کرده‌اید؟',
            14 => 'آیا احساس کرده‌اید که همه چیز شما را ناراحت می‌کند؟',
            // اختلال عملکرد اجتماعی
            15 => 'آیا مشغول به کارهایی بوده‌اید که برایتان خوشایند است؟',
            16 => 'آیا توانسته‌اید با مشکلاتتان رو به رو شوید؟',
            17 => 'آیا در مجموع از وضعیتتان راضی بوده‌اید؟',
            18 => 'آیا توانسته‌اید تصمیمات لازم را بگیرید؟',
            19 => 'آیا کارهای روزانه‌تان را با موفقیت انجام داده‌اید؟',
            20 => 'آیا از کارهایتان لذت برده‌اید؟',
            21 => 'آیا توانسته‌اید با فعالیت‌های روزمره‌تان کنار بیایید؟',
            // افسردگی شدید
            22 => 'آیا به این فکر افتاده‌اید که واقعاً به هیچ دردی نمی‌خورید؟',
            23 => 'آیا احساس کرده‌اید که زندگی‌تان بی‌معنی است؟',
            24 => 'آیا احساس کرده‌اید که قادر نیستید کاری انجام دهید چون اعصابتان خراب بوده؟',
            25 => 'آیا آرزو کرده‌اید که کاش مرده بودید و از شر همه چیز راحت می‌شدید؟',
            26 => 'آیا فکر کرده‌اید که به خودتان آسیب می‌رسانید؟',
            27 => 'آیا متوجه شده‌اید که آرزوی مرگ خود را دارید؟',
            28 => 'آیا احساس کرده‌اید که میل به مردن دارید؟',
        ];
    }

    public function get_schema_labels(): array {
        return array_map(fn($s) => $s['label'], $this->subscales());
    }
    public function get_domain_labels(): array { return []; }

    public function calculate(array $answers): array {
        $answers = \PsychAssessments\Core\Scoring::sanitize_answers( $answers );
        $subs = $this->subscales();
        $sub_scores = [];
        $total = 0;

        // فیکس: هیچ‌کدام از ۴ زیرمقیاس نیاز به معکوس‌سازی ندارند — تمام ۲۸ سوال
        // (علامتی و توانمندی) روی همان مقیاس مشترک ۰=سالم ... ۳=اختلال نمره‌گذاری می‌شوند.
        foreach ($subs as $key => $def) {
            $sum = 0;
            foreach ($def['questions'] as $q) {
                $sum += min(3, max(0, (int)($answers[$q] ?? 0)));
            }
            $sub_scores[$key] = $sum;
            $total += $sum;
        }

        // هشدار افسردگی شدید
        $depression_alert = $sub_scores['depression'] >= 2;
        // هشدار خودکشی (سوال ۲۵، ۲۶، ۲۷، ۲۸)
        $suicide_alert = false;
        foreach ([25,26,27,28] as $q) {
            if (($answers[$q] ?? 0) >= 2) { $suicide_alert = true; break; }
        }

        return [
            'total'           => $total,
            'sub_scores'      => $sub_scores,
            'depression_alert'=> $depression_alert,
            'suicide_alert'   => $suicide_alert,
        ];
    }

    private function get_total_severity(float $total): array {
        return \PsychAssessments\Core\Scoring::severity($total, [
            ['max'=>22,  'label'=>'سالم',              'label_en'=>'Healthy',    'color'=>'#276749','bg'=>'#f0fff4','clinical'=>'زیر cut-off — سلامت روانی مطلوب',             'range'=>'0–22'],
            ['max'=>29,  'label'=>'مشکوک',             'label_en'=>'Borderline', 'color'=>'#d69e2e','bg'=>'#fffff0','clinical'=>'در محدوده مشکوک — ارزیابی بیشتر لازم',       'range'=>'23–29'],
            ['max'=>39,  'label'=>'اختلال خفیف‌تا متوسط','label_en'=>'Moderate', 'color'=>'#c05621','bg'=>'#fffaf0','clinical'=>'اختلال متوسط — درمان توصیه می‌شود',         'range'=>'30–39'],
            [            'label'=>'اختلال شدید',       'label_en'=>'Severe',     'color'=>'#9b2c2c','bg'=>'#fff5f5','clinical'=>'اختلال شدید — مداخله فوری ضروری',           'range'=>'≥ 40'],
        ]);
    }

    public function render_report(array $data, object $session): string {
        $total            = $data['total'];
        $sub_scores       = $data['sub_scores'];
        $depression_alert = $data['depression_alert'];
        $suicide_alert    = $data['suicide_alert'];
        $subs             = $this->subscales();
        $sev              = $this->get_total_severity((float)$total);
        $completed_date   = $session->completed_at ? pa_to_jalali($session->completed_at, 'Y/m/d ساعت H:i') : '---';

        // داده نمودار
        $chart_labels = array_map(fn($s) => $s['label'], $subs);
        $chart_values = array_values($sub_scores);
        $chart_colors = array_map(fn($s) => $s['color'].'cc', $subs);
        $chart_borders= array_map(fn($s) => $s['color'], $subs);

        $sev_tone = \PsychAssessments\Core\ReportHTML::tone( $sev['color'] );

        ob_start();
        ?>
        <!-- هشدار خودکشی -->
        <?php if ($suicide_alert): ?>
        <div class="psy-report-alert psy-tone--danger">
            <h3 class="psy-report-alert__title">⚠️ هشدار بالینی — افکار آسیب به خود (سوالات ۲۵–۲۸)</h3>
            <p class="psy-report-alert__text">مراجع در سوالات مربوط به آسیب به خود پاسخ نگران‌کننده داده. ارزیابی فوری لازم است.</p>
        </div>
        <?php endif; ?>

        <?php if ($depression_alert): ?>
        <div class="psy-report-alert psy-tone--purple">
            <h3 class="psy-report-alert__title psy-report-alert__title--sm">⚠️ افسردگی شدید — نمره بالاتر از آستانه</h3>
            <p class="psy-report-alert__text psy-report-alert__text--plum">زیرمقیاس افسردگی شدید: <?php echo esc_html((int)$sub_scores['depression']); ?> از ۲۱ — ارزیابی روان‌پزشکی توصیه می‌شود.</p>
        </div>
        <?php endif; ?>

        <!-- خلاصه -->
        <div class="psy-report-row">
            <?php foreach ([
                ['#e9d8fd','#553c9a','👤','نام',$session->client_name ?? $session->patient_name ?? '---'],
                ['#ebf8ff','#2b6cb0','📞','تماس',$session->client_phone ?? $session->patient_phone ?? '---'],
                ['#f0fff4','#2f855a','✅','تاریخ',$completed_date],
                [$sev['bg'],$sev['color'],'🏥','GHQ-28',$total.' از ۸۴'],
                [$sev['bg'],$sev['color'],'🏷','وضعیت',$sev['label']],
                [($total>=23?'#fff5f5':'#f0fff4'),($total>=23?'#c53030':'#276749'),'✂️','cut-off',($total>=23?'≥23 — نیاز به توجه':'<23 — سالم')],
            ] as $c) echo \PsychAssessments\Core\ReportHTML::summary_card($c[0],$c[1],$c[2],$c[3],$c[4]); ?>
        </div>

        <!-- نمره کل -->
        <div class="psy-report-card--tint <?php echo esc_attr( $sev_tone ); ?>">
            <div class="psy-clinical__head">
                <div>
                    <h3 class="psy-report-hero__title psy-report-hero__title--lg">🏥 نمره کل GHQ-28: <?php echo esc_html((int)$total); ?> از ۸۴</h3>
                    <p class="psy-report-hero__text"><?php echo esc_html($sev['clinical']); ?></p>
                </div>
                <?php echo \PsychAssessments\Core\ReportHTML::score_badge((float)$total, $sev); ?>
            </div>
            <?php echo \PsychAssessments\Core\ReportHTML::progress_bar( \PsychAssessments\Core\Scoring::percent($total,0,84), $sev['color'], true ); ?>
            <div class="psy-report-scale">
                <span>0 سالم</span><span class="psy-report-scale__mark psy-tone--danger psy-text">cut-off: 23</span><span>84 شدید</span>
            </div>
        </div>

        <!-- نمودار + زیرمقیاس‌ها -->
        <div class="psy-report-row psy-report-row--loose">
            <div class="psy-report-card psy-report-card--panel psy-report-col">
                <h3 class="psy-report-panel__title psy-report-center">پروفایل ۴ زیرمقیاس GHQ-28</h3>
                <canvas id="pa-bar-chart" class="psy-report-chart psy-report-chart--bar"></canvas>
            </div>
            <div class="psy-report-col psy-report-col--wide">
                <?php foreach ($subs as $key => $def):
                    $s = $sub_scores[$key];
                    $pct = \PsychAssessments\Core\Scoring::percent($s, 0, $def['max']);
                    $cutoff_reached = $s >= $def['cutoff'];
                    $def_tone = \PsychAssessments\Core\ReportHTML::tone( $def['color'] );
                    $cut_tone = \PsychAssessments\Core\ReportHTML::tone( $cutoff_reached ? '#c53030' : '#276749' );
                ?>
                <div class="psy-report-item <?php echo esc_attr( $def_tone ); ?>">
                    <div class="psy-clinical__head">
                        <div>
                            <span class="psy-report-item__name"><?php echo esc_html($def['label']); ?></span>
                            <span class="psy-report-item__en"><?php echo esc_html($def['label_en']); ?></span>
                        </div>
                        <span class="psy-report-item__score psy-report-item__score--lg psy-text <?php echo esc_attr( $cut_tone ); ?>">
                            <?php echo esc_html((int)$s); ?>/<?php echo esc_html($def['max']); ?> <?php echo $cutoff_reached?'⚠️':'✅'; ?>
                        </span>
                    </div>
                    <?php echo \PsychAssessments\Core\ReportHTML::progress_bar( (float) $pct, $def['color'] ); ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- راهنمای تفسیر -->
        <div class="psy-report-panel psy-report-panel--soft">
            <h3 class="psy-report-panel__title">📚 راهنمای تفسیر (Goldberg &amp; Hillier, 1979)</h3>
            <table class="psy-report-table">
                <thead><tr>
                    <th class="psy-report-table__head psy-report-table__head--strong psy-report-table__cell--start">نمره کل</th>
                    <th class="psy-report-table__head psy-report-table__head--strong psy-report-table__cell--start">وضعیت</th>
                    <th class="psy-report-table__head psy-report-table__head--strong psy-report-table__cell--start">توصیه</th>
                </tr></thead>
                <tbody>
                <?php foreach ([
                    ['0–22',  'سالم',                   '#276749','پایش معمول'],
                    ['23–29', 'مشکوک',                  '#d69e2e','ارزیابی بیشتر لازم'],
                    ['30–39', 'اختلال خفیف‌تا متوسط', '#c05621','درمان توصیه می‌شود'],
                    ['≥ 40',  'اختلال شدید',            '#9b2c2c','مداخله فوری ضروری'],
                ] as $r):
                    $r_tone = \PsychAssessments\Core\ReportHTML::tone( $r[2] ); ?>
                <tr>
                    <td class="psy-report-table__cell <?php echo esc_attr( $r_tone ); ?> psy-text psy-report-bold"><?php echo esc_html($r[0]); ?></td>
                    <td class="psy-report-table__cell <?php echo esc_attr( $r_tone ); ?> psy-text psy-report-bold"><?php echo esc_html($r[1]); ?></td>
                    <td class="psy-report-table__cell psy-report-muted"><?php echo esc_html($r[3]); ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="psy-report-refs">
            <b>منابع:</b> Goldberg &amp; Hillier (1979). <i>Psychological Medicine, 9</i>(1). | Palahang et al. (1996) هنجاریابی ایران.<br>
            📌 نمره‌گذاری Likert (0-1-2-3) به‌کار رفته — حساسیت بالاتر نسبت به روش GHQ binary.<br>
            <b class="psy-tone--danger psy-text">⚠️</b> ابزار کمک‌بالینی — مبنای تشخیص رسمی نیست.
        </div>

        <script>
        (function(){
            function init(){
                if(typeof Chart==='undefined'){setTimeout(init,100);return;}
                var ctx=document.getElementById('pa-bar-chart');
                if(!ctx)return;
                new Chart(ctx.getContext('2d'),{
                    type:'bar',
                    data:{
                        labels:<?php echo wp_json_encode(array_values($chart_labels)); ?>,
                        datasets:[{
                            data:<?php echo wp_json_encode($chart_values); ?>,
                            backgroundColor:<?php echo wp_json_encode(array_values($chart_colors)); ?>,
                            borderColor:<?php echo wp_json_encode(array_values($chart_borders)); ?>,
                            borderWidth:2,borderRadius:5
                        }]
                    },
                    options:{
                        responsive:true,
                        plugins:{legend:{display:false}},
                        scales:{
                            y:{min:0,max:21,ticks:{stepSize:7}},
                            x:{ticks:{font:{family:'Tahoma',size:11}}}
                        }
                    }
                });
            }
            document.addEventListener('DOMContentLoaded',init);
        })();
        </script>
        <?php
        return ob_get_clean();
    }
}
\PsychAssessments\Core\Registry::register( new GHQ28() );
