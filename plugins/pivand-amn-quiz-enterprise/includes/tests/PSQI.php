<?php
namespace PsychAssessments\Tests;
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * PSQI: Pittsburgh Sleep Quality Index (19 items, 7 components)
 *
 * - Buysse, D.J. et al. (1989). The Pittsburgh Sleep Quality Index.
 *   Psychiatry Research, 28(2), 193–213.
 * - Farrahi, J. et al. (2012). هنجاریابی PSQI در ایران. Sleep and Biological Rhythms, 10, 110–115.
 * - cut-off کلی > 5 = مشکل خواب بالینی (Buysse et al., 1989)
 * - ۷ جزء: کیفیت ذهنی، تأخیر خواب، مدت خواب، راندمان، اختلالات، داروهای خواب، اختلال عملکرد روزانه
 * - هر جزء ۰–۳، کل ۰–۲۱
 *
 * نمره‌گذاری: الگوریتم استاندارد Buysse et al. (1989)
 * - جزء تأخیر خواب (Latency): q2 + q5 به صورت ترکیبی جمع → به 0–3 تبدیل
 * - سایر اجزا: میانگین سوالات جزء → گرد به 0–3
 */
class PSQI extends \PsychAssessments\Core\BaseTest {

    public function get_id(): string        { return 'psqi'; }
    public function get_category(): string      { return 'mental_health'; }
    public function get_name(): string      { return 'کیفیت خواب (PSQI)'; }
    public function needs_chart(): bool   { return true; }  // نمودار Chart.js

    public function get_description(): string { return 'شاخص کیفیت خواب پیتزبورگ — ۱۶ سوال، ۷ جزء، cut-off > ۵ (الگوریتم استاندارد Buysse)'; }
    public function get_version(): string   { return '1.0.0'; }

    public function get_scale(): array {
        return [
            'min'    => 0,
            'max'    => 3,
            'labels' => [
                0 => 'هیچ مشکلی نداشتم / خیلی خوب',
                1 => 'مشکل کمی داشتم / خوب',
                2 => 'مشکل متوسطی داشتم / بد',
                3 => 'مشکل زیادی داشتم / خیلی بد',
            ],
        ];
    }

    private function components(): array {
        return [
            'subj_quality' => ['q'=>[1],         'label'=>'کیفیت ذهنی خواب',         'label_en'=>'Subjective Quality',     'color'=>'#2b6cb0'],
            'latency'      => ['q'=>[2,5],        'label'=>'تأخیر در به خواب رفتن',   'label_en'=>'Sleep Latency',          'color'=>'#553c9a'],
            'duration'     => ['q'=>[3],          'label'=>'مدت خواب',                'label_en'=>'Sleep Duration',         'color'=>'#276749'],
            'efficiency'   => ['q'=>[4],          'label'=>'راندمان خواب',            'label_en'=>'Sleep Efficiency',       'color'=>'#d69e2e'],
            'disturbances' => ['q'=>[6,7,8,9,10,11,12,13],'label'=>'اختلالات خواب',  'label_en'=>'Sleep Disturbances',     'color'=>'#c05621'],
            'medication'   => ['q'=>[14],         'label'=>'داروهای خواب',            'label_en'=>'Sleep Medication',       'color'=>'#c53030'],
            'daytime_dysfx'=> ['q'=>[15,16],      'label'=>'اختلال عملکرد روزانه',   'label_en'=>'Daytime Dysfunction',    'color'=>'#9b2c2c'],
        ];
    }

    public function get_questions(): array {
        return [
            1  => 'در ماه گذشته، به طور کلی کیفیت خواب خود را چطور ارزیابی می‌کنید؟',
            2  => 'در ماه گذشته چقدر طول می‌کشید تا بخوابید (تأخیر در خواب)؟',
            3  => 'در ماه گذشته چند ساعت واقعاً می‌خوابیدید؟ (0=≥7ساعت، 1=6-7، 2=5-6، 3=<5)',
            4  => 'راندمان خواب شما (نسبت ساعات خواب به زمان در رختخواب) چقدر بود؟ (0=≥85%، 1=75-84، 2=65-74، 3=<65)',
            5  => 'در ماه گذشته چقدر نمی‌توانستید در ۳۰ دقیقه اول بخوابید؟',
            6  => 'در ماه گذشته چقدر شب‌ها بیدار می‌شدید؟',
            7  => 'چقدر برای سرویس بهداشتی بیدار می‌شدید؟',
            8  => 'چقدر نفس کشیدنتان راحت نبود؟',
            9  => 'چقدر سرفه یا خرخر می‌کردید؟',
            10 => 'چقدر خیلی سرد بودید؟',
            11 => 'چقدر خیلی گرم بودید؟',
            12 => 'چقدر کابوس می‌دیدید؟',
            13 => 'چقدر درد داشتید؟',
            14 => 'در ماه گذشته چقدر از داروهای خواب (تجویزی یا بدون نسخه) استفاده کردید؟',
            15 => 'در ماه گذشته چقدر در انجام کارها خواب‌آلود بودید؟',
            16 => 'در ماه گذشته چقدر مشکل در حفظ انرژی کافی برای انجام کارها داشتید؟',
        ];
    }

    public function get_schema_labels(): array {
        return array_map(fn($c) => $c['label'], $this->components());
    }
    public function get_domain_labels(): array { return []; }

    public function calculate(array $answers): array {
        $answers = \PsychAssessments\Core\Scoring::sanitize_answers( $answers );
        $comps = $this->components();
        $comp_scores = [];
        $total = 0;

        foreach ($comps as $key => $def) {
            if ( $key === 'latency' ) {
                // ─── جزء ۲: تأخیر خواب — الگوریتم استاندارد Buysse et al. (1989) ───
                // q2: مدت زمان تا به خواب رفتن (0=≤15دقیقه، 1=16-30، 2=31-60، 3=>60)
                // q5: چقدر نمی‌توانستید در 30 دقیقه بخوابید (0=هیچ، 1=کمتر از هفته‌ای یک بار، 2=یک-دو بار، 3=سه بار یا بیشتر)
                $q2 = min(3, max(0, (int)($answers[2] ?? 0)));
                $q5 = min(3, max(0, (int)($answers[5] ?? 0)));
                $latency_sum = $q2 + $q5; // دامنه: 0–6
                // تبدیل مجموع 0–6 به مقیاس 0–3 (Buysse 1989 Table 1):
                if ( $latency_sum === 0 )              { $comp = 0; }
                elseif ( $latency_sum <= 2 )           { $comp = 1; }
                elseif ( $latency_sum <= 4 )           { $comp = 2; }
                else                                   { $comp = 3; }
                $comp_scores['latency'] = $comp;
                $total += $comp;
            } else {
                // سایر اجزا: میانگین سوالات، گرد به 0–3
                $sum = 0;
                foreach ($def['q'] as $q) {
                    $sum += min(3, max(0, (int)($answers[$q] ?? 0)));
                }
                $avg = (int) round($sum / count($def['q']));
                $avg = min(3, $avg);
                $comp_scores[$key] = $avg;
                $total += $avg;
            }
        }

        return ['total' => $total, 'comp_scores' => $comp_scores];
    }

    /**
     * منبع واحد نقاط برش PSQI. ایندکس ۰ = مرز «خوب» / «بد» (max=5) که همون
     * cut-off بالینی استاندارد Buysse et al. (1989) است.
     */
    private function severity_levels(): array {
        return [
            ['max'=>5,  'label'=>'خوب',          'label_en'=>'Good Sleeper',   'color'=>'#276749','bg'=>'#f0fff4','clinical'=>'کیفیت خواب خوب — بدون مشکل بالینی',     'range'=>'0–5'],
            ['max'=>10, 'label'=>'بد',           'label_en'=>'Poor Sleeper',   'color'=>'#c05621','bg'=>'#fffaf0','clinical'=>'بالاتر از cut-off — مشکل خواب بالینی',   'range'=>'6–10'],
            ['max'=>15, 'label'=>'خیلی بد',     'label_en'=>'Very Poor',      'color'=>'#c53030','bg'=>'#fff5f5','clinical'=>'مشکل خواب شدید — ارزیابی تخصصی لازم',   'range'=>'11–15'],
            [           'label'=>'حاد',          'label_en'=>'Severely Poor',  'color'=>'#9b2c2c','bg'=>'#fff5f5','clinical'=>'اختلال خواب حاد — مداخله فوری ضروری',   'range'=>'16–21'],
        ];
    }

    private function get_severity(float $total): array {
        return \PsychAssessments\Core\Scoring::severity($total, $this->severity_levels());
    }

    public function render_report(array $data, object $session): string {
        $total       = $data['total'];
        $comp_scores = $data['comp_scores'];
        $comps       = $this->components();
        $levels       = $this->severity_levels();
        $sev          = \PsychAssessments\Core\Scoring::severity((float)$total, $levels);
        // به‌جای هاردکد جدا (<=5)، مستقیم از همان $levels خونده می‌شه (ایندکس ۰ = مرز خوب/بد)
        $good_sleeper = ! \PsychAssessments\Core\Scoring::is_above_level((float)$total, $levels, 0);
        $completed_date = $session->completed_at ? pa_to_jalali($session->completed_at, 'Y/m/d ساعت H:i') : '---';

        ob_start();
        // باگ #12: نمایش هشدار ساده‌سازی در ابتدای گزارش
        ?>
        <div class="psy-report-alert psy-tone--warning">
            <h3 class="psy-report-alert__title psy-report-alert__title--sm">⚠️ توجه بالینی:</h3>
            <p class="psy-report-alert__text psy-report-alert__text--sm">
                اجزای ۳ (مدت خواب) و ۴ (راندمان خواب) در این نسخه با سوال Likert اندازه‌گیری شده‌اند،
                نه با ساعت واقعی خواب/بیداری. دقت این دو جزء نسبت به نسخه استاندارد Pittsburgh کمتر است.
                برای تصمیم‌گیری بالینی، ارزیابی کمی مستقیم توصیه می‌شود.
            </p>
        </div>

        <div class="psy-report-row">
            <?php foreach ([
                ['#ebf8ff','#2b6cb0','📞','تماس',$session->client_phone ?? $session->patient_phone ?? '---'],
                ['#f0fff4','#2f855a','✅','تاریخ',$completed_date],
                [$sev['bg'],$sev['color'],'😴','PSQI',$total.' از ۲۱'],
                [$sev['bg'],$sev['color'],'✂️','cut-off',$good_sleeper?'≤5 — خواب خوب':'>5 — مشکل خواب'],
            ] as $c) echo \PsychAssessments\Core\ReportHTML::summary_card($c[0],$c[1],$c[2],$c[3],$c[4]); ?>
        </div>

        <div class="psy-report-card--tint <?php echo esc_attr( \PsychAssessments\Core\ReportHTML::tone( (string) $sev['color'] ) ); ?>">
            <div class="psy-clinical__head">
                <div>
                    <h3 class="psy-report-hero__title psy-report-hero__title--lg">
                        😴 کیفیت خواب: <?php echo esc_html((int)$total); ?> از ۲۱
                        — <?php echo $good_sleeper ? esc_html('✅ خواب خوب') : esc_html('⚠️ مشکل خواب'); ?>
                    </h3>
                    <p class="psy-report-hero__text"><?php echo esc_html($sev['clinical']); ?></p>
                </div>
                <?php echo \PsychAssessments\Core\ReportHTML::score_badge((float)$total, $sev); ?>
            </div>
            <?php echo \PsychAssessments\Core\ReportHTML::progress_bar( (float) \PsychAssessments\Core\Scoring::percent($total,0,21), $sev['color'], true ); ?>
            <div class="psy-report-scale">
                <span class="psy-report-scale__mark">۰ خوب</span>
                <span class="psy-tone--danger"><span class="psy-report-scale__mark psy-text">cut-off: <?php echo esc_html(\PsychAssessments\Core\Scoring::level_cutoff($levels, 0)); ?></span></span>
                <span class="psy-report-scale__mark">۲۱ خیلی بد</span>
            </div>
        </div>

        <!-- ۷ جزء -->
        <div class="psy-report-block">
            <?php echo \PsychAssessments\Core\ReportHTML::section_header('📊 ۷ جزء PSQI'); ?>
            <?php foreach ($comps as $key => $def):
                $s = $comp_scores[$key];
                $pct = \PsychAssessments\Core\Scoring::percent($s, 0, 3);
                $comp_labels = ['خوب','کمی بد','بد','خیلی بد'];
                $comp_colors = ['#276749','#d69e2e','#c05621','#9b2c2c'];
            ?>
            <div class="psy-report-item <?php echo esc_attr( \PsychAssessments\Core\ReportHTML::tone( (string) $def['color'] ) ); ?>">
                <div class="psy-clinical__head">
                    <div>
                        <span class="psy-report-item__name"><?php echo esc_html($def['label']); ?></span>
                        <span class="psy-report-en psy-report-en--inline"><?php echo esc_html($def['label_en']); ?></span>
                    </div>
                    <span class="psy-report-tag <?php echo esc_attr( \PsychAssessments\Core\ReportHTML::tone( $comp_colors[$s] ) ); ?>">
                        <?php echo $s; ?>/3 — <?php echo esc_html($comp_labels[$s]); ?>
                    </span>
                </div>
                <?php echo \PsychAssessments\Core\ReportHTML::progress_bar( (float) $pct, (string) $def['color'] ); ?>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="psy-report-alert psy-tone--info">
            <p class="psy-report-alert__text psy-report-alert__text--sm">
                📌 نمره‌گذاری بر اساس الگوریتم استاندارد Buysse et al. (1989): جزء تأخیر خواب با ترکیب q2+q5 محاسبه می‌شود (جمع 0–6 → تبدیل به 0–3). سایر اجزا میانگین مستقیم دارند. cut-off > 5 حساسیت ۸۹.۶% و ویژگی ۸۶.۵% دارد.
            </p>
        </div>

        <!-- نمودار راداری ۷ جزء -->
        <div class="psy-report-card psy-report-card--panel psy-report-chart-panel psy-report-panel--center">
            <h3 class="psy-report-panel__title">نمودار ۷ جزء PSQI</h3>
            <canvas id="pa-psqi-chart" class="psy-report-chart psy-report-chart--gauge"></canvas>
        </div>

        <div class="psy-report-refs psy-report-refs--muted">
            <b>منابع:</b> Buysse et al. (1989). <i>Psychiatry Research, 28</i>(2). | Farrahi et al. (2012) هنجاریابی ایران.<br>
            <b class="psy-tone--danger psy-text">⚠️</b> ابزار کمک‌بالینی — مبنای تشخیص رسمی نیست.
        </div>
        <script>
        (function(){
            function init(){
                if(typeof Chart==='undefined'){setTimeout(init,100);return;}
                var ctx=document.getElementById('pa-psqi-chart');
                if(!ctx)return;
                new Chart(ctx.getContext('2d'),{
                    type:'radar',
                    data:{
                        labels:<?php
                            $comp_labels = array_map(fn($c)=>$c['label'], $this->components());
                            echo wp_json_encode(array_values($comp_labels));
                        ?>,
                        datasets:[{
                            label:'نمره',
                            data:<?php echo wp_json_encode(array_values($comp_scores)); ?>,
                            backgroundColor:'rgba(197,48,48,0.15)',
                            borderColor:'#c53030',borderWidth:2,
                            pointBackgroundColor:'#c53030',pointRadius:5
                        }]
                    },
                    options:{
                        responsive:true,
                        scales:{r:{min:0,max:3,ticks:{stepSize:1},pointLabels:{font:{family:'Tahoma',size:10}}}},
                        plugins:{legend:{display:false}}
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
\PsychAssessments\Core\Registry::register( new PSQI() );
