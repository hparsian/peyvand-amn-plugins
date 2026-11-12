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

        ob_start();
        ?>
        <div style="display:flex;gap:12px;flex-wrap:wrap;margin-bottom:24px;">
            <?php foreach ([
                ['#ebf8ff','#2b6cb0','📞','تماس',$session->client_phone ?? $session->patient_phone ?? '---'],
                ['#f0fff4','#2f855a','✅','تاریخ',$completed_date],
                [$sev['bg'],$sev['color'],'💪','GSE',$total.' از ۴۰'],
                [$sev['bg'],$sev['color'],'🏷','سطح',$sev['label'].' ('.$sev['label_en'].')'],
            ] as $c) echo \PsychAssessments\Core\ReportHTML::summary_card($c[0],$c[1],$c[2],$c[3],$c[4]); ?>
        </div>

        <div style="background:<?php echo esc_attr($sev['bg']); ?>;border:2px solid <?php echo esc_attr($sev['color']); ?>;border-radius:10px;padding:22px;margin-bottom:24px;">
            <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;">
                <div>
                    <h3 style="margin:0 0 6px;color:<?php echo esc_attr($sev['color']); ?>;font-size:18px;">💪 خودکارآمدی: <?php echo esc_html((int)$total); ?> از ۴۰</h3>
                    <p style="margin:0;font-size:13px;color:#4a5568;"><?php echo esc_html($sev['clinical']); ?></p>
                </div>
                <?php echo \PsychAssessments\Core\ReportHTML::score_badge((float)$total, $sev); ?>
            </div>
            <div style="margin-top:14px;background:#edf2f7;border-radius:6px;height:12px;overflow:hidden;">
                <div style="background:<?php echo esc_attr($sev['color']); ?>;height:100%;width:<?php echo \PsychAssessments\Core\Scoring::percent($total,10,40); ?>%;border-radius:6px;"></div>
            </div>
            <div style="display:flex;justify-content:space-between;font-size:10px;color:#a0aec0;margin-top:4px;">
                <span>10 پایین</span><span style="color:#d69e2e;">25 متوسط</span><span style="color:#276749;">33 بالا</span><span>40</span>
            </div>
        </div>

        <!-- نمودار -->
        <div style="background:#fff;padding:20px;border-radius:10px;box-shadow:0 2px 10px rgba(0,0,0,0.08);margin-bottom:24px;text-align:center;">
            <h3 style="margin-top:0;font-size:15px;color:#2d3748;">سطح خودکارآمدی عمومی</h3>
            <canvas id="pa-gse-chart" style="max-height:200px;max-width:400px;display:inline-block;"></canvas>
        </div>

        <div style="background:#ebf8ff;border:1px solid #90cdf4;border-radius:8px;padding:14px 20px;margin-bottom:20px;font-size:12px;color:#2b6cb0;">
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

        <div style="background:#f7fafc;border:1px solid #e2e8f0;border-radius:10px;padding:20px;margin-bottom:24px;">
            <h3 style="margin-top:0;font-size:14px;">📚 راهنمای تفسیر (Schwarzer & Jerusalem, 1995)</h3>
            <table style="width:100%;border-collapse:collapse;font-size:12px;">
                <thead><tr style="background:#edf2f7;">
                    <th style="text-align:right;padding:8px;border:1px solid #e2e8f0;">نمره</th>
                    <th style="text-align:right;padding:8px;border:1px solid #e2e8f0;">سطح</th>
                    <th style="text-align:right;padding:8px;border:1px solid #e2e8f0;">توصیه</th>
                </tr></thead>
                <tbody>
                <?php foreach ([
                    ['10–19','خیلی پایین','#9b2c2c','تقویت خودکارآمدی — اولویت درمانی'],
                    ['20–24','پایین',     '#c53030','تکنیک‌های CBT برای تقویت'],
                    ['25–32','متوسط',     '#d69e2e','تقویت با موفقیت‌های تدریجی'],
                    ['33–40','بالا',      '#276749','حفظ و بهره‌گیری از منبع قوت'],
                ] as $r): ?>
                <tr>
                    <td style="padding:7px 8px;border:1px solid #e2e8f0;font-weight:bold;color:<?php echo esc_attr($r[2]); ?>;"><?php echo esc_html($r[0]); ?></td>
                    <td style="padding:7px 8px;border:1px solid #e2e8f0;font-weight:bold;color:<?php echo esc_attr($r[2]); ?>;"><?php echo esc_html($r[1]); ?></td>
                    <td style="padding:7px 8px;border:1px solid #e2e8f0;color:#4a5568;"><?php echo esc_html($r[3]); ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div style="font-size:11px;color:#a0aec0;background:#fff;border:1px solid #e2e8f0;border-radius:8px;padding:14px 20px;line-height:1.8;">
            <b style="color:#4a5568;">منابع:</b> Schwarzer & Jerusalem (1995). In Weinman et al. (Eds.), <i>Measures in Health Psychology.</i> | Rajabi (2006). هنجاریابی ایران.<br>
            <b style="color:#c53030;">⚠️</b> ابزار کمک‌بالینی — مبنای تشخیص رسمی نیست.
        </div>
        <?php // باگ Q: script block دوم تکراری حذف شد — Chart.js روی canvas یکسان دوبار اجرا می‌شد ?>
        <?php
        return ob_get_clean();
    }
}
\PsychAssessments\Core\Registry::register( new GSE() );
