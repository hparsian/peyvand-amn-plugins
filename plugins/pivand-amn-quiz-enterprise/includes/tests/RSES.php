<?php
namespace PsychAssessments\Tests;
if ( ! defined( 'ABSPATH' ) ) exit;

// ─────────────────────────────────────────────────────────────────────────────

/**
 * RSES: Rosenberg Self-Esteem Scale (10 items)
 *
 * - Rosenberg, M. (1965). Society and the Adolescent Self-Image. Princeton University Press.
 * - Shapurian, R. et al. (1987). هنجاریابی RSES در ایران.
 * - مقیاس ۱–۴، سوالات 2,5,6,8,9 معکوس، محدوده ۱۰–۴۰
 * - cut-off: <15 پایین | 15–25 متوسط | >25 بالا
 */
class RSES extends \PsychAssessments\Core\BaseTest {

    public function get_id(): string        { return 'rses'; }
    public function get_category(): string      { return 'wellbeing'; }
    public function get_name(): string      { return 'عزت‌نفس روزنبرگ (RSES)'; }
    public function needs_chart(): bool   { return true; }  // نمودار Chart.js

    public function get_description(): string { return 'مقیاس عزت‌نفس روزنبرگ — ۱۰ سوال، محدوده ۱۰–۴۰، پرکاربردترین ابزار عزت‌نفس'; }
    public function get_version(): string   { return '1.0.0'; }

    public function get_scale(): array {
        return [
            'min'    => 1,
            'max'    => 4,
            'labels' => [
                1 => 'کاملاً موافقم',
                2 => 'موافقم',
                3 => 'مخالفم',
                4 => 'کاملاً مخالفم',
            ],
        ];
    }

    /**
     * سوالات معکوس RSES
     *
     * مقیاس این پیاده‌سازی: ۱=کاملاً موافقم → ۴=کاملاً مخالفم
     * برای تبدیل به نمره‌ی عزت‌نفس (بالاتر = بهتر)، سوالات مثبت باید معکوس شوند:
     * q1 مثبت: SA(1) → باید نمره ۴ (بالا) بگیرد → reversed
     * q2 منفی: SA(1) → باید نمره ۱ (پایین) بگیرد → مستقیم
     *
     * سوالات مثبت (reversed): 1, 3, 4, 7, 10
     * سوالات منفی (مستقیم):   2, 5, 6, 8, 9
     */
    private function reversed_items(): array { return [1, 3, 4, 7, 10]; }

    public function get_questions(): array {
        return [
            1  => 'در مجموع از خودم راضی هستم.',
            2  => 'گاهی اوقات فکر می‌کنم که اصلاً خوب نیستم. (معکوس)',
            3  => 'فکر می‌کنم که دارای تعدادی خصوصیات خوب هستم.',
            4  => 'می‌توانم کارها را به همان خوبی که اکثر مردم انجام می‌دهند، انجام دهم.',
            5  => 'فکر می‌کنم که چیز زیادی برای مباهات ندارم. (معکوس)',
            6  => 'گاهی اوقات قطعاً احساس بی‌فایدگی می‌کنم. (معکوس)',
            7  => 'احساس می‌کنم فردی باارزشی هستم، حداقل در حد مردم دیگر.',
            8  => 'ای کاش می‌توانستم احترام بیشتری برای خودم داشته باشم. (معکوس)',
            9  => 'در مجموع تمایل دارم که فکر کنم شکست خورده‌ام. (معکوس)',
            10 => 'نسبت به خودم دیدگاه مثبتی دارم.',
        ];
    }

    public function get_schema_labels(): array { return []; }
    public function get_domain_labels(): array { return []; }

    private function get_severity(float $total): array {
        return \PsychAssessments\Core\Scoring::severity($total, [
            ['max'=>14,  'label'=>'پایین',   'label_en'=>'Low',      'color'=>'#9b2c2c','bg'=>'#fff5f5','clinical'=>'عزت‌نفس پایین — هدف درمانی مهم',        'range'=>'10–14'],
            ['max'=>19,  'label'=>'پایین‌تر از متوسط','label_en'=>'Below Average','color'=>'#c53030','bg'=>'#fff5f5','clinical'=>'زیر میانگین — تقویت لازم','range'=>'15–19'],
            ['max'=>25,  'label'=>'متوسط',  'label_en'=>'Average',   'color'=>'#d69e2e','bg'=>'#fffff0','clinical'=>'در محدوده متوسط — تقویت مفید',          'range'=>'20–25'],
            ['max'=>32,  'label'=>'بالا',   'label_en'=>'High',      'color'=>'#276749','bg'=>'#f0fff4','clinical'=>'عزت‌نفس خوب',                           'range'=>'26–32'],
            [            'label'=>'خیلی بالا','label_en'=>'Very High','color'=>'#22543d','bg'=>'#f0fff4','clinical'=>'عزت‌نفس بالا — منبع قوت روانی',        'range'=>'33–40'],
        ]);
    }

    public function calculate(array $answers): array {
        $answers = \PsychAssessments\Core\Scoring::sanitize_answers( $answers );
        $reversed = $this->reversed_items();
        $total = 0;
        for ($q = 1; $q <= 10; $q++) {
            $raw = min(4, max(1, (int)($answers[$q] ?? 2)));
            $total += in_array($q, $reversed) ? (5 - $raw) : $raw;
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
                [$sev['bg'],$sev['color'],'🌟','RSES',$total.' از ۴۰'],
                [$sev['bg'],$sev['color'],'🏷','سطح',$sev['label']],
            ] as $c) echo \PsychAssessments\Core\ReportHTML::summary_card($c[0],$c[1],$c[2],$c[3],$c[4]); ?>
        </div>

        <div class="psy-report-card--tint <?php echo esc_attr( $sev_tone ); ?>">
            <div class="psy-clinical__head">
                <div>
                    <h3 class="psy-report-hero__title psy-report-hero__title--lg">🌟 عزت‌نفس: <?php echo intval($total); ?> از ۴۰</h3>
                    <p class="psy-report-hero__text"><?php echo esc_html($sev['clinical']); ?></p>
                </div>
                <?php echo \PsychAssessments\Core\ReportHTML::score_badge((float)$total, $sev); ?>
            </div>
            <?php echo \PsychAssessments\Core\ReportHTML::progress_bar( \PsychAssessments\Core\Scoring::percent($total,10,40), $sev['color'], true ); ?>
            <div class="psy-report-scale">
                <span>10 پایین</span><span class="psy-report-scale__mark psy-tone--warning psy-text">20 متوسط</span><span class="psy-report-scale__mark psy-tone--success psy-text">26 بالا</span><span>40</span>
            </div>
        </div>

        <!-- نمودار -->
        <div class="psy-report-card psy-report-card--panel psy-report-chart-panel psy-report-chart-panel--center">
            <h3 class="psy-report-panel__title">سطح عزت‌نفس</h3>
            <canvas id="pa-rses-chart" class="psy-report-chart psy-report-chart--sm psy-report-chart--inline"></canvas>
        </div>

        <div class="psy-report-panel psy-report-panel--soft">
            <h3 class="psy-report-panel__title">📚 راهنمای تفسیر (Rosenberg, 1965)</h3>
            <table class="psy-report-table">
                <thead><tr>
                    <th class="psy-report-table__head psy-report-table__head--strong psy-report-table__cell--start">نمره</th>
                    <th class="psy-report-table__head psy-report-table__head--strong psy-report-table__cell--start">سطح</th>
                    <th class="psy-report-table__head psy-report-table__head--strong psy-report-table__cell--start">توصیه</th>
                </tr></thead>
                <tbody>
                <?php foreach ([
                    ['10–14','پایین',           '#9b2c2c','تقویت عزت‌نفس — اولویت درمانی'],
                    ['15–19','پایین‌تر از متوسط','#c53030','تکنیک‌های CBT و پذیرش'],
                    ['20–25','متوسط',           '#d69e2e','تقویت خودپنداره مثبت'],
                    ['26–32','بالا',            '#276749','حفظ و تقویت نقاط قوت'],
                    ['33–40','خیلی بالا',       '#22543d','منبع قوت روانی محافظ'],
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
            <b>منابع:</b> Rosenberg, M. (1965). <i>Society and the Adolescent Self-Image.</i> Princeton UP. | Shapurian et al. (1987) هنجاریابی ایران.<br>
            <b class="psy-tone--danger psy-text">⚠️</b> ابزار کمک‌بالینی — مبنای تشخیص رسمی نیست.
        </div>
        <script>
        (function(){
            function init(){
                if(typeof Chart==='undefined'){setTimeout(init,100);return;}
                var ctx=document.getElementById('pa-rses-chart');
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
                        x.fillText('<?php echo intval($total); ?>', cx, cy);
                        x.font='12px Tahoma'; x.fillStyle='#718096';
                        x.fillText(label, cx, cy+22); x.restore();
                    }}]
                });
            }
            document.addEventListener('DOMContentLoaded',init);
        })();
        </script>
        <?php
        return ob_get_clean();
    }
}
\PsychAssessments\Core\Registry::register( new RSES() );
