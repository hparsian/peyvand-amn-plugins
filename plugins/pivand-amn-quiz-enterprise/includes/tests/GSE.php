<?php
namespace PsychAssessments\Tests;
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * GSE: General Self-Efficacy Scale (10 items)
 *
 * - Schwarzer, R. & Jerusalem, M. (1995). Generalized Self-Efficacy Scale.
 *   In J. Weinman, S. Wright, & M. Johnston (Eds.), Measures in Health Psychology (pp. 35–37).
 * - Rajabi, G. (2006). هنجاریابی GSE در ایران. Psychological Studies, 2(3–4), 111–122.
 * - مقیاس ۱–۴، محدوده ۱۰–۴۰
 * - cut-off: <25 پایین | 25–32 متوسط | >32 بالا
 */
class GSE extends \PsychAssessments\Core\BaseTest {

    public function get_id(): string        { return 'gse'; }
    public function get_category(): string      { return 'wellbeing'; }
    public function get_name(): string      { return 'خودکارآمدی عمومی (GSE)'; }
    public function needs_chart(): bool   { return true; }  // نمودار Chart.js

    public function get_description(): string { return 'مقیاس خودکارآمدی عمومی شوارتزر — ۱۰ سوال، محدوده ۱۰–۴۰'; }
    public function get_version(): string   { return '1.0.0'; }

    public function get_scale(): array {
        return [
            'min'    => 1,
            'max'    => 4,
            'labels' => [
                1 => 'کاملاً غلط است',
                2 => 'تا حدودی غلط است',
                3 => 'تا حدودی درست است',
                4 => 'کاملاً درست است',
            ],
        ];
    }

    public function get_questions(): array {
        return [
            1  => 'اگر سخت تلاش کنم، می‌توانم همیشه مشکل دشوار را حل کنم.',
            2  => 'اگر کسی مخالف باشد، می‌توانم راهی برای رسیدن به آنچه می‌خواهم پیدا کنم.',
            3  => 'برای من آسان است که به هدف‌هایم بچسبم و به آن‌ها برسم.',
            4  => 'به توانایی خود برای مواجهه با رویدادهای غیرمنتظره اطمینان دارم.',
            5  => 'به لطف نبوغم، می‌توانم از موقعیت‌های غیرمنتظره جان سالم به در ببرم.',
            6  => 'اگر تلاش لازم را بکنم، می‌توانم اکثر مشکلات را حل کنم.',
            7  => 'می‌توانم در موقعیت‌های دشوار آرام باشم، چون به توانایی‌هایم اعتماد دارم.',
            8  => 'وقتی با یک مشکل روبرو می‌شوم، معمولاً می‌توانم چندین راه‌حل پیدا کنم.',
            9  => 'اگر در مخمصه‌ای گیر کنم، معمولاً می‌توانم راهی برای خروج پیدا کنم.',
            10 => 'هر اتفاقی که بیفتد، معمولاً می‌توانم از عهده‌اش بربیایم.',
        ];
    }

    public function get_schema_labels(): array { return []; }
    public function get_domain_labels(): array { return []; }

    private function get_severity(float $total): array {
        return \PsychAssessments\Core\Scoring::severity($total, [
            ['max'=>19,  'label'=>'خیلی پایین', 'label_en'=>'Very Low',  'color'=>'#9b2c2c','bg'=>'#fff5f5','clinical'=>'خودکارآمدی بسیار پایین — هدف درمانی اولیه',   'range'=>'10–19'],
            ['max'=>24,  'label'=>'پایین',      'label_en'=>'Low',       'color'=>'#c53030','bg'=>'#fff5f5','clinical'=>'خودکارآمدی پایین — نیاز به تقویت',            'range'=>'20–24'],
            ['max'=>32,  'label'=>'متوسط',      'label_en'=>'Moderate',  'color'=>'#d69e2e','bg'=>'#fffff0','clinical'=>'خودکارآمدی متوسط — تقویت مفید است',           'range'=>'25–32'],
            [            'label'=>'بالا',       'label_en'=>'High',      'color'=>'#276749','bg'=>'#f0fff4','clinical'=>'خودکارآمدی بالا — منبع مقاومت روانی',         'range'=>'33–40'],
        ]);
    }

    public function calculate(array $answers): array {
        $answers = \PsychAssessments\Core\Scoring::sanitize_answers( $answers );
        $total = 0;
        for ($q = 1; $q <= 10; $q++) {
            $total += min(4, max(1, (int)($answers[$q] ?? 2)));
        }
        return ['total' => $total];
    }

    public function render_report(array $data, object $session): string {
        $total = $data['total'];
        $sev   = $this->get_severity((float)$total);
        $completed_date = $session->completed_at ? pa_to_jalali($session->completed_at, 'Y/m/d ساعت H:i') : '---';

        $sev_tone = \PsychAssessments\Core\ReportHTML::tone( $sev['color'] );

        ob_start();
        ?>
        <div class="psy-report-row">
            <?php foreach ([
                ['#ebf8ff','#2b6cb0','📞','تماس',$session->client_phone ?? $session->patient_phone ?? '---'],
                ['#f0fff4','#2f855a','✅','تاریخ',$completed_date],
                [$sev['bg'],$sev['color'],'💪','GSE',$total.' از ۴۰'],
                [$sev['bg'],$sev['color'],'🏷','سطح',$sev['label'].' ('.$sev['label_en'].')'],
            ] as $c) echo \PsychAssessments\Core\ReportHTML::summary_card($c[0],$c[1],$c[2],$c[3],$c[4]); ?>
        </div>

        <div class="psy-report-card--tint <?php echo esc_attr( $sev_tone ); ?>">
            <div class="psy-clinical__head">
                <div>
                    <h3 class="psy-report-hero__title psy-report-hero__title--lg">💪 خودکارآمدی: <?php echo esc_html((int)$total); ?> از ۴۰</h3>
                    <p class="psy-report-hero__text"><?php echo esc_html($sev['clinical']); ?></p>
                </div>
                <?php echo \PsychAssessments\Core\ReportHTML::score_badge((float)$total, $sev); ?>
            </div>
            <?php echo \PsychAssessments\Core\ReportHTML::progress_bar( \PsychAssessments\Core\Scoring::percent($total,10,40), $sev['color'], true ); ?>
            <div class="psy-report-scale">
                <span>10 پایین</span><span class="psy-report-scale__mark psy-tone--warning psy-text">25 متوسط</span><span class="psy-report-scale__mark psy-tone--success psy-text">33 بالا</span><span>40</span>
            </div>
        </div>

        <!-- نمودار -->
        <div class="psy-report-card psy-report-card--panel psy-report-chart-panel psy-report-chart-panel--center">
            <h3 class="psy-report-panel__title psy-report-center">سطح خودکارآمدی عمومی</h3>
            <canvas id="pa-gse-chart" class="psy-report-chart psy-report-chart--sm psy-report-chart--inline"></canvas>
        </div>

        <div class="psy-report-note-box psy-report-note-box--compact">
            📌 <b>خودکارآمدی عمومی</b> باور فرد به توانایی خود در انجام رفتارها و مقابله با چالش‌هاست. نمره بالاتر نشان‌دهنده باور قوی‌تر به توانایی‌های شخصی است.
        </div>

        <script>
        (function(){
            function init(){
                if(typeof Chart==='undefined'){setTimeout(init,100);return;}
                var ctx=document.getElementById('pa-gse-chart');
                if(!ctx)return;
                var pct=<?php echo \PsychAssessments\Core\Scoring::percent($total,10,40); ?>;
                var color='<?php echo esc_js($sev['color']); ?>';
                var label='<?php echo esc_js($sev['label']); ?>';
                new Chart(ctx.getContext('2d'),{
                    type:'doughnut',
                    data:{datasets:[{data:[pct,100-pct],backgroundColor:[color,'#edf2f7'],borderWidth:0,circumference:180,rotation:270}]},
                    options:{responsive:true,plugins:{legend:{display:false},tooltip:{enabled:false}},cutout:'72%'},
                    plugins:[{id:'ct',afterDraw:function(c){
                        var x=c.ctx; var cx=c.width/2; var cy=c.height*0.88;
                        x.save(); x.font='bold 30px Tahoma'; x.fillStyle=color; x.textAlign='center';
                        x.fillText('<?php echo esc_html((int)$total); ?>', cx, cy);
                        x.font='12px Tahoma'; x.fillStyle='#718096';
                        x.fillText(label, cx, cy+22); x.restore();
                    }}]
                });
            }
            document.addEventListener('DOMContentLoaded',init);
        })();
        </script>

        <div class="psy-report-panel psy-report-panel--soft">
            <h3 class="psy-report-panel__title">📚 راهنمای تفسیر (Schwarzer &amp; Jerusalem, 1995)</h3>
            <table class="psy-report-table">
                <thead><tr>
                    <th class="psy-report-table__head psy-report-table__head--strong psy-report-table__cell--start">نمره</th>
                    <th class="psy-report-table__head psy-report-table__head--strong psy-report-table__cell--start">سطح</th>
                    <th class="psy-report-table__head psy-report-table__head--strong psy-report-table__cell--start">توصیه</th>
                </tr></thead>
                <tbody>
                <?php foreach ([
                    ['10–19','خیلی پایین','#9b2c2c','تقویت خودکارآمدی — اولویت درمانی'],
                    ['20–24','پایین',     '#c53030','تکنیک‌های CBT برای تقویت'],
                    ['25–32','متوسط',     '#d69e2e','تقویت با موفقیت‌های تدریجی'],
                    ['33–40','بالا',      '#276749','حفظ و بهره‌گیری از منبع قوت'],
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
            <b>منابع:</b> Schwarzer &amp; Jerusalem (1995). In Weinman et al. (Eds.), <i>Measures in Health Psychology.</i> | Rajabi (2006). هنجاریابی ایران.<br>
            <b class="psy-tone--danger psy-text">⚠️</b> ابزار کمک‌بالینی — مبنای تشخیص رسمی نیست.
        </div>
        <?php // باگ Q: script block دوم تکراری حذف شد — Chart.js روی canvas یکسان دوبار اجرا می‌شد ?>
        <?php
        return ob_get_clean();
    }
}
\PsychAssessments\Core\Registry::register( new GSE() );
