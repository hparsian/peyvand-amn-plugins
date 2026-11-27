<?php
namespace PsychAssessments\Tests;
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * SWLS: Satisfaction With Life Scale (5 items)
 *
 * - Diener, E. et al. (1985). The Satisfaction With Life Scale.
 *   Journal of Personality Assessment, 49(1), 71–75.
 * - Bayani, A.A. et al. (2007). هنجاریابی SWLS در ایران.
 * - مقیاس ۱–۷، محدوده ۵–۳۵
 * - cut-off: 5–9 خیلی ناراضی | 10–14 ناراضی | 15–19 کمی ناراضی | 20 خنثی | 21–25 راضی | 26–30 خیلی راضی | 31–35 بسیار راضی
 */
class SWLS extends \PsychAssessments\Core\BaseTest {

    public function get_id(): string        { return 'swls'; }
    public function get_category(): string      { return 'wellbeing'; }
    public function get_name(): string      { return 'رضایت از زندگی (SWLS)'; }
    public function needs_chart(): bool   { return true; }  // نمودار Chart.js

    public function get_description(): string { return 'مقیاس رضایت از زندگی دینر — ۵ سوال، محدوده ۵–۳۵، رایج‌ترین ابزار رفاه ذهنی'; }
    public function get_version(): string   { return '1.0.0'; }

    public function get_scale(): array {
        return [
            'min'    => 1,
            'max'    => 7,
            'labels' => [
                1 => 'کاملاً مخالفم',
                2 => 'مخالفم',
                3 => 'کمی مخالفم',
                4 => 'نه موافق نه مخالف',
                5 => 'کمی موافقم',
                6 => 'موافقم',
                7 => 'کاملاً موافقم',
            ],
        ];
    }

    public function get_questions(): array {
        return [
            1 => 'زندگی‌ام در بیشتر جهات به ایده‌آل‌هایم نزدیک است.',
            2 => 'شرایط زندگی‌ام عالی است.',
            3 => 'از زندگی‌ام راضی هستم.',
            4 => 'تاکنون چیزهای مهمی را که در زندگی می‌خواستم به دست آورده‌ام.',
            5 => 'اگر می‌توانستم زندگی‌ام را دوباره شروع کنم، تقریباً هیچ چیز را تغییر نمی‌دادم.',
        ];
    }

    public function get_schema_labels(): array { return []; }
    public function get_domain_labels(): array { return []; }

    private function get_severity(float $total): array {
        return \PsychAssessments\Core\Scoring::severity($total, [
            ['max'=>9,  'label'=>'خیلی ناراضی',  'label_en'=>'Extremely Dissatisfied','color'=>'#9b2c2c','bg'=>'#fff5f5','clinical'=>'نارضایتی شدید از زندگی — مداخله لازم',    'range'=>'5–9'],
            ['max'=>14, 'label'=>'ناراضی',        'label_en'=>'Dissatisfied',          'color'=>'#c53030','bg'=>'#fff5f5','clinical'=>'نارضایتی از زندگی — ارزیابی بیشتر',       'range'=>'10–14'],
            ['max'=>19, 'label'=>'کمی ناراضی',   'label_en'=>'Slightly Below Average','color'=>'#c05621','bg'=>'#fffaf0','clinical'=>'کمتر از میانگین — توجه بیشتر مفید',       'range'=>'15–19'],
            ['max'=>20, 'label'=>'خنثی',          'label_en'=>'Neutral',               'color'=>'#718096','bg'=>'#f7fafc','clinical'=>'نه راضی نه ناراضی',                       'range'=>'20'],
            ['max'=>25, 'label'=>'راضی',          'label_en'=>'Satisfied',             'color'=>'#d69e2e','bg'=>'#fffff0','clinical'=>'رضایت متوسط از زندگی',                    'range'=>'21–25'],
            ['max'=>30, 'label'=>'خیلی راضی',    'label_en'=>'Highly Satisfied',      'color'=>'#276749','bg'=>'#f0fff4','clinical'=>'رضایت بالا از زندگی',                     'range'=>'26–30'],
            [           'label'=>'بسیار راضی',   'label_en'=>'Extremely Satisfied',   'color'=>'#22543d','bg'=>'#f0fff4','clinical'=>'رضایت بسیار بالا از زندگی',              'range'=>'31–35'],
        ]);
    }

    public function calculate(array $answers): array {
        $answers = \PsychAssessments\Core\Scoring::sanitize_answers( $answers );
        $total = 0;
        for ($q = 1; $q <= 5; $q++) {
            $total += min(7, max(1, (int)($answers[$q] ?? 4)));
        }
        return ['total' => $total];
    }

    public function render_report(array $data, object $session): string {
        $total = $data['total'];
        $sev   = $this->get_severity((float)$total);
        $completed_date = $session->completed_at ? pa_to_jalali($session->completed_at, 'Y/m/d ساعت H:i') : '---';
        // باگ U: defensive check — answers ممکن است در برخی contexts موجود نباشد
        $raw_answers = [];
        if ( ! empty( $session->answers ) ) {
            $raw_answers = is_string( $session->answers )
                ? pa_decrypt_json( $session->answers )
                : (array) $session->answers;
        }

        $sev_tone = \PsychAssessments\Core\ReportHTML::tone( $sev['color'] );

        ob_start();
        ?>
        <div class="psy-report-row">
            <?php foreach ([
                ['#ebf8ff','#2b6cb0','📞','تماس',$session->client_phone ?? $session->patient_phone ?? '---'],
                ['#f0fff4','#2f855a','✅','تاریخ',$completed_date],
                [$sev['bg'],$sev['color'],'😊','SWLS',$total.' از ۳۵'],
                [$sev['bg'],$sev['color'],'🏷','سطح',$sev['label']],
            ] as $c) echo \PsychAssessments\Core\ReportHTML::summary_card($c[0],$c[1],$c[2],$c[3],$c[4]); ?>
        </div>

        <div class="psy-report-hero--center <?php echo esc_attr( $sev_tone ); ?>">
            <h3 class="psy-report-hero__title">رضایت از زندگی</h3>
            <div class="psy-report-hero__row">
                <span class="psy-report-hero__number"><?php echo esc_html((int)$total); ?></span>
                <span class="psy-report-hero__unit">/ ۳۵</span>
            </div>
            <div class="psy-report-pill"><?php echo esc_html($sev['label']); ?></div>
            <div class="psy-report-gauge psy-report-gauge--spaced <?php echo esc_attr( $sev_tone ); ?>">
                <div class="psy-report-gauge__track psy-report-gauge__track--sm">
                    <div class="psy-report-gauge__fill <?php echo esc_attr( \PsychAssessments\Core\ReportHTML::pct_class( (float) \PsychAssessments\Core\Scoring::percent($total,5,35) ) ); ?>"></div>
                </div>
                <div class="psy-report-scale">
                    <span>5</span><span class="psy-report-scale__neutral">20 خنثی</span><span>35</span>
                </div>
            </div>
        </div>

        <div class="psy-report-refs">
            <b>منابع:</b> Diener et al. (1985). <i>Journal of Personality Assessment, 49</i>(1). | Bayani et al. (2007) هنجاریابی ایران.<br>
            <b class="psy-tone--danger psy-text">⚠️</b> ابزار کمک‌بالینی — مبنای تشخیص رسمی نیست.
        </div>

        <!-- نمودار سوال‌به‌سوال -->
        <div class="psy-report-card psy-report-card--panel psy-report-chart-panel psy-report-chart-panel--top psy-report-chart-panel--center">
            <h3 class="psy-report-panel__title">پروفایل پاسخ‌های ۵ سوال</h3>
            <canvas id="pa-swls-chart" class="psy-report-chart psy-report-chart--sm"></canvas>
        </div>
        <script>
        (function(){
            function init(){
                if(typeof Chart==='undefined'){setTimeout(init,100);return;}
                var ctx=document.getElementById('pa-swls-chart');
                if(!ctx)return;
                var vals=<?php
                    $sv=[];
                    for($q=1;$q<=5;$q++) $sv[]=min(7,max(1,(int)($raw_answers[$q]??4)));
                    echo wp_json_encode($sv);
                ?>;
                var color='<?php echo esc_js($sev['color']); ?>';
                var colors=vals.map(function(v){return v>=5?'#276749':v>=3?'#d69e2e':'#c53030';});
                new Chart(ctx.getContext('2d'),{
                    type:'bar',
                    data:{
                        labels:['س۱','س۲','س۳','س۴','س۵'],
                        datasets:[{data:vals,backgroundColor:colors,borderRadius:5,borderWidth:0}]
                    },
                    options:{responsive:true,plugins:{legend:{display:false}},scales:{y:{min:1,max:7,ticks:{stepSize:1}}}}
                });
            }
            document.addEventListener('DOMContentLoaded',init);
        })();
        </script>
        <?php
        return ob_get_clean();
    }
}
\PsychAssessments\Core\Registry::register( new SWLS() );
