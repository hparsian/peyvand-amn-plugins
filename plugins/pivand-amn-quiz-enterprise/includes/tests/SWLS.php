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

        ob_start();
        ?>
        <div style="display:flex;gap:12px;flex-wrap:wrap;margin-bottom:24px;">
            <?php foreach ([
                ['#ebf8ff','#2b6cb0','📞','تماس',$session->client_phone ?? $session->patient_phone ?? '---'],
                ['#f0fff4','#2f855a','✅','تاریخ',$completed_date],
                [$sev['bg'],$sev['color'],'😊','SWLS',$total.' از ۳۵'],
                [$sev['bg'],$sev['color'],'🏷','سطح',$sev['label']],
            ] as $c) echo \PsychAssessments\Core\ReportHTML::summary_card($c[0],$c[1],$c[2],$c[3],$c[4]); ?>
        </div>

        <div style="background:<?php echo esc_attr($sev['bg']); ?>;border:2px solid <?php echo esc_attr($sev['color']); ?>;border-radius:12px;padding:28px 24px;margin-bottom:24px;text-align:center;">
            <h3 style="margin:0 0 8px;color:<?php echo esc_attr($sev['color']); ?>;font-size:16px;">رضایت از زندگی</h3>
            <div style="font-size:64px;font-weight:900;color:<?php echo esc_attr($sev['color']); ?>;line-height:1;margin:12px 0;"><?php echo esc_html((int)$total); ?><span style="font-size:20px;color:#718096;">/ ۳۵</span></div>
            <div style="display:inline-block;background:<?php echo esc_attr($sev['color']); ?>;color:#fff;padding:8px 24px;border-radius:20px;font-size:14px;font-weight:bold;"><?php echo esc_html($sev['label']); ?></div>
            <div style="max-width:400px;margin:16px auto 0;">
                <div style="background:#edf2f7;border-radius:8px;height:14px;overflow:hidden;">
                    <div style="background:<?php echo esc_attr($sev['color']); ?>;height:100%;width:<?php echo \PsychAssessments\Core\Scoring::percent($total,5,35); ?>%;border-radius:8px;"></div>
                </div>
                <div style="display:flex;justify-content:space-between;font-size:10px;color:#a0aec0;margin-top:4px;">
                    <span>5</span><span style="color:#718096;">20 خنثی</span><span>35</span>
                </div>
            </div>
        </div>

        <div style="font-size:11px;color:#a0aec0;background:#fff;border:1px solid #e2e8f0;border-radius:8px;padding:14px 20px;line-height:1.8;">
            <b style="color:#4a5568;">منابع:</b> Diener et al. (1985). <i>Journal of Personality Assessment, 49</i>(1). | Bayani et al. (2007) هنجاریابی ایران.<br>
            <b style="color:#c53030;">⚠️</b> ابزار کمک‌بالینی — مبنای تشخیص رسمی نیست.
        </div>

        <!-- نمودار سوال‌به‌سوال -->
        <div style="background:#fff;padding:20px;border-radius:10px;box-shadow:0 2px 10px rgba(0,0,0,0.08);margin-top:20px;">
            <h3 style="margin-top:0;text-align:center;font-size:14px;">پروفایل پاسخ‌های ۵ سوال</h3>
            <canvas id="pa-swls-chart" style="max-height:200px;"></canvas>
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
