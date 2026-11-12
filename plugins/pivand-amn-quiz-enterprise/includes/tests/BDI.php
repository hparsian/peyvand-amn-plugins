<?php
namespace PsychAssessments\Tests;
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * پرسشنامه افسردگی بک — نسخه دوم (BDI-II)
 *
 * مبانی علمی:
 * - Beck, A.T., Steer, R.A., & Brown, G.K. (1996). Manual for the Beck Depression
 *   Inventory-II. Psychological Corporation.
 * - Ghassemzadeh, H. et al. (2005). هنجاریابی BDI-II در جمعیت ایرانی.
 *   Psychological Reports, 96(1), 143–150.
 * - cut-off: 0–13 حداقل | 14–19 خفیف | 20–28 متوسط | 29–63 شدید
 * - ۲۱ سوال، هر سوال ۰–۳، مقیاس کلی ۰–۶۳
 * - سوالات ۱۶ و ۱۸ دارای دو بخش هستند (خواب/اشتها) — فرم A (افزایش) پیش‌فرض
 */
class BDI extends \PsychAssessments\Core\BaseTest {

    public function get_id(): string        { return 'bdi'; }
    public function get_category(): string      { return 'mental_health'; }
    public function get_name(): string      { return 'افسردگی بک (BDI-II)'; }
    public function needs_chart(): bool   { return true; }  // نمودار Chart.js

    public function get_description(): string { return 'پرسشنامه افسردگی بک — نسخه دوم — ۲۱ سوال، محدوده ۰–۶۳'; }
    public function get_version(): string   { return '1.0.0'; }

    public function get_scale(): array {
        return [
            'min'    => 0,
            'max'    => 3,
            'labels' => [
                0 => 'اصلاً',
                1 => 'کمی',
                2 => 'زیاد',
                3 => 'خیلی زیاد',
            ],
        ];
    }

    /** گروه‌بندی شناختی / جسمانی برای تحلیل */
    private function subscales(): array {
        return [
            'cognitive' => [
                'questions' => [1,2,3,4,5,6,7,8,9,14],
                'label'     => 'علائم شناختی-عاطفی',
                'label_en'  => 'Cognitive-Affective',
                'color'     => '#553c9a',
            ],
            'somatic'   => [
                'questions' => [10,11,12,13,15,16,17,18,19,20,21],
                'label'     => 'علائم جسمانی',
                'label_en'  => 'Somatic',
                'color'     => '#c05621',
            ],
        ];
    }

    public function get_questions(): array {
        return [
            1  => 'غم و اندوه: چقدر احساس غم و ناراحتی می‌کنید؟',
            2  => 'بدبینی: چقدر نسبت به آینده‌تان بدبین هستید؟',
            3  => 'شکست: چقدر احساس می‌کنید شکست خورده‌اید؟',
            4  => 'از دست دادن لذت: چقدر از کارهایی که قبلاً لذت می‌بردید لذت می‌برید؟ (0=مثل قبل، 3=اصلاً نه)',
            5  => 'احساس گناه: چقدر احساس گناه می‌کنید؟',
            6  => 'احساس تنبیه: آیا احساس می‌کنید باید تنبیه شوید؟',
            7  => 'نارضایتی از خود: چقدر از خودتان ناراضی هستید؟',
            8  => 'انتقاد از خود: چقدر خودتان را سرزنش می‌کنید؟',
            9  => 'افکار خودکشی: آیا افکاری درباره آسیب رساندن به خود دارید؟',
            10 => 'گریه: چقدر گریه می‌کنید؟',
            11 => 'بی‌قراری: چقدر احساس بی‌قراری می‌کنید؟',
            12 => 'از دست دادن علاقه: چقدر علاقه‌تان به دیگران کاهش یافته؟',
            13 => 'بلاتکلیفی: چقدر در تصمیم‌گیری دچار مشکل هستید؟',
            14 => 'احساس بی‌ارزشی: چقدر احساس می‌کنید بی‌ارزش هستید؟',
            15 => 'از دست دادن انرژی: چقدر انرژی‌تان کاهش یافته؟',
            16 => 'تغییر خواب: خوابتان چقدر تغییر کرده؟ (0=مثل قبل تا 3=خیلی زیاد)',
            17 => 'تحریک‌پذیری: چقدر تحریک‌پذیر و زودرنج شده‌اید؟',
            18 => 'تغییر اشتها: اشتهایتان چقدر تغییر کرده؟ (0=مثل قبل تا 3=خیلی زیاد)',
            19 => 'مشکل تمرکز: چقدر در تمرکز مشکل دارید؟',
            20 => 'خستگی: چقدر خسته‌تر از حد معمول هستید؟',
            21 => 'از دست دادن علاقه جنسی: چقدر علاقه جنسی‌تان کاهش یافته؟',
        ];
    }

    public function get_schema_labels(): array {
        return [
            'cognitive' => 'علائم شناختی-عاطفی',
            'somatic'   => 'علائم جسمانی',
        ];
    }

    public function get_domain_labels(): array { return []; }

    private function get_severity( float $total ): array {
        return \PsychAssessments\Core\Scoring::severity( $total, [
            ['max'=>13,  'label'=>'حداقل',          'label_en'=>'Minimal',        'color'=>'#276749', 'bg'=>'#f0fff4', 'clinical'=>'نشانه‌های افسردگی در حد طبیعی',                  'range'=>'0–13'],
            ['max'=>19,  'label'=>'خفیف',            'label_en'=>'Mild',           'color'=>'#d69e2e', 'bg'=>'#fffff0', 'clinical'=>'افسردگی خفیف — پایش و مشاوره توصیه می‌شود',       'range'=>'14–19'],
            ['max'=>28,  'label'=>'متوسط',           'label_en'=>'Moderate',       'color'=>'#c05621', 'bg'=>'#fffaf0', 'clinical'=>'افسردگی متوسط — درمان روان‌شناختی لازم است',      'range'=>'20–28'],
            [            'label'=>'شدید',            'label_en'=>'Severe',         'color'=>'#9b2c2c', 'bg'=>'#fff5f5', 'clinical'=>'افسردگی شدید — ارزیابی فوری و درمان ضروری',      'range'=>'29–63'],
        ]);
    }

    public function calculate( array $answers ): array {
        $answers = \PsychAssessments\Core\Scoring::sanitize_answers( $answers );
        $total = 0;
        for ($q = 1; $q <= 21; $q++) {
            $total += min(3, max(0, (int)($answers[$q] ?? 0)));
        }

        $sub_scores = [];
        foreach ($this->subscales() as $key => $def) {
            $sub_scores[$key] = \PsychAssessments\Core\Scoring::subscale_sum($def['questions'], $answers, 0, 3);
        }

        // بررسی سوال ۹ (افکار خودکشی) — هشدار بالینی
        $suicide_item = min(3, max(0, (int)($answers[9] ?? 0)));

        return [
            'total'        => $total,
            'sub_scores'   => $sub_scores,
            'suicide_item' => $suicide_item,
        ];
    }

    public function render_report( array $data, object $session ): string {
        $total         = $data['total'];
        $sub_scores    = $data['sub_scores'];
        $suicide_item  = $data['suicide_item'];
        $sev           = $this->get_severity((float)$total);
        $subscales     = $this->subscales();
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
        <!-- هشدار خودکشی -->
        <?php
        // باگ T: BDI-II گزینه ۱ سوال ۹ = «افکار آسیب به خود اما عملش نمی‌کنم» —
        // از نظر بالینی حتی نمره ۱ نیاز به پیگیری دارد. threshold از ≥2 به ≥1 تغییر یافت.
        ?>
        <?php if ($suicide_item >= 1): ?>
        <div style="background:#fff5f5;border:2px solid <?php echo esc_attr($suicide_item >= 2 ? '#9b2c2c' : '#f6ad55'); ?>;border-radius:10px;padding:16px 20px;margin-bottom:20px;">
            <h3 style="margin:0 0 8px;color:#9b2c2c;font-size:16px;"><?php echo $suicide_item >= 2 ? '🚨' : '⚠️'; ?> هشدار بالینی — افکار خودآسیبی</h3>
            <p style="margin:0;font-size:13px;color:#742a2a;line-height:1.8;">
                مراجع در سوال ۹ (افکار خودکشی) نمره <b><?php echo esc_html((int)$suicide_item); ?> از ۳</b> گزارش داده است.
                <?php if ($suicide_item === 1): ?>
                افکار آسیب به خود گزارش شده اما قصد اجرا ذکر نشده — پیگیری و بررسی در اولین جلسه ضروری است.
                <?php else: ?>
                ارزیابی فوری خطر خودکشی و اقدامات لازم توسط درمانگر الزامی است.
                <?php endif; ?>
            </p>
        </div>
        <?php endif; ?>

        <!-- خلاصه -->
        <div style="display:flex;gap:12px;flex-wrap:wrap;margin-bottom:24px;">
            <?php
            $cards = [
                ['#ebf8ff','#2b6cb0','📞','شماره تماس',   $session->client_phone ?? $session->patient_phone ?? '---'],
                ['#f0fff4','#2f855a','✅','تاریخ تکمیل',   $completed_date],
                [$sev['bg'],$sev['color'],'😔','نمره کل BDI-II', $total . ' از ۶۳'],
                [$sev['bg'],$sev['color'],'🏷','سطح افسردگی',    $sev['label'] . ' (' . $sev['label_en'] . ')'],
            ];
            foreach ($cards as $c) { echo \PsychAssessments\Core\ReportHTML::summary_card($c[0],$c[1],$c[2],$c[3],$c[4]); }
            ?>
        </div>

        <!-- نمره کل -->
        <div style="background:<?php echo esc_attr($sev['bg']); ?>;border:2px solid <?php echo esc_attr($sev['color']); ?>;border-radius:10px;padding:22px;margin-bottom:24px;">
            <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;">
                <div>
                    <h3 style="margin:0 0 6px;color:<?php echo esc_attr($sev['color']); ?>;font-size:18px;">
                        📊 نمره کل: <?php echo esc_html((int)$total); ?> از ۶۳
                    </h3>
                    <p style="margin:0;font-size:13px;color:#4a5568;"><?php echo esc_html($sev['clinical']); ?></p>
                </div>
                <?php echo \PsychAssessments\Core\ReportHTML::score_badge((float)$total, $sev); ?>
            </div>
            <div style="margin-top:14px;background:#edf2f7;border-radius:6px;height:14px;overflow:hidden;position:relative;">
                <div style="background:linear-gradient(90deg,#276749,#d69e2e,#c05621,#9b2c2c);height:100%;width:100%;opacity:0.2;position:absolute;"></div>
                <div style="background:<?php echo esc_attr($sev['color']); ?>;height:100%;width:<?php echo \PsychAssessments\Core\Scoring::percent($total,0,63); ?>%;border-radius:6px;position:relative;z-index:1;"></div>
            </div>
            <div style="display:flex;justify-content:space-between;font-size:10px;color:#a0aec0;margin-top:4px;">
                <span>0 حداقل</span><span style="color:#d69e2e;">14 خفیف</span><span style="color:#c05621;">20 متوسط</span><span style="color:#9b2c2c;">29 شدید</span><span>63</span>
            </div>
        </div>

        <!-- زیرمقیاس‌ها -->
        <div style="display:flex;gap:16px;flex-wrap:wrap;margin-bottom:24px;">
            <?php foreach ($subscales as $key => $def):
                $s = $sub_scores[$key];
                $max = count($def['questions']) * 3;
                $pct = round($s / $max * 100);
            ?>
            <div style="flex:1;min-width:220px;background:#fff;border:1px solid #e2e8f0;border-radius:8px;padding:16px;border-top:4px solid <?php echo esc_attr($def['color']); ?>;">
                <div style="font-weight:bold;color:<?php echo esc_attr($def['color']); ?>;font-size:13px;"><?php echo esc_html($def['label']); ?></div>
                <div style="font-size:11px;color:#a0aec0;margin-bottom:10px;"><?php echo esc_html($def['label_en']); ?></div>
                <div style="font-size:28px;font-weight:900;color:<?php echo esc_attr($def['color']); ?>;"><?php echo $s; ?><span style="font-size:14px;color:#718096;">/ <?php echo $max; ?></span></div>
                <div style="margin-top:8px;background:#edf2f7;border-radius:4px;height:8px;overflow:hidden;">
                    <div style="background:<?php echo esc_attr($def['color']); ?>;height:100%;width:<?php echo $pct; ?>%;border-radius:4px;"></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- نمودار -->
        <div style="background:#fff;padding:20px;border-radius:10px;box-shadow:0 2px 10px rgba(0,0,0,0.08);margin-bottom:24px;">
            <h3 style="margin-top:0;text-align:center;font-size:15px;">نمودار پروفایل BDI-II</h3>
            <canvas id="pa-bdi-chart" style="max-height:300px;"></canvas>
        </div>

        <!-- راهنمای تفسیر -->
        <div style="background:#f7fafc;border:1px solid #e2e8f0;border-radius:10px;padding:20px;margin-bottom:24px;">
            <h3 style="margin-top:0;font-size:15px;">📚 راهنمای تفسیر (Beck et al., 1996)</h3>
            <table style="width:100%;border-collapse:collapse;font-size:12px;">
                <thead><tr style="background:#edf2f7;">
                    <th style="text-align:right;padding:8px;border:1px solid #e2e8f0;">نمره</th>
                    <th style="text-align:right;padding:8px;border:1px solid #e2e8f0;">سطح</th>
                    <th style="text-align:right;padding:8px;border:1px solid #e2e8f0;">توصیه بالینی</th>
                </tr></thead>
                <tbody>
                <?php foreach ([
                    ['0–13',  'حداقل', '#276749', 'پایش معمول — مداخله ضروری نیست'],
                    ['14–19', 'خفیف',  '#d69e2e', 'مشاوره روان‌شناختی مفید است'],
                    ['20–28', 'متوسط', '#c05621', 'روان‌درمانی (CBT/دارودرمانی) لازم است'],
                    ['29–63', 'شدید',  '#9b2c2c', 'ارزیابی فوری — روان‌پزشکی ضروری'],
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

        <div style="background:#fff;border:1px solid #e2e8f0;border-radius:8px;padding:14px 20px;font-size:11px;color:#a0aec0;line-height:1.8;">
            <b style="color:#4a5568;">منابع:</b><br>
            Beck, A.T., Steer, R.A., & Brown, G.K. (1996). <i>Manual for the BDI-II.</i> Psychological Corporation.<br>
            Ghassemzadeh, H. et al. (2005). <i>Psychological Reports, 96</i>(1), 143–150.<br>
            <b style="color:#c53030;">⚠️</b> ابزار کمک‌بالینی — مبنای تشخیص رسمی نیست.
        </div>

        <script>
        (function(){
            var labels = <?php echo wp_json_encode(array_values(array_map(fn($q) => 'س'.$q, range(1,21)))); ?>;
            var values = <?php
                $vals = [];
                for($q=1;$q<=21;$q++) $vals[] = min(3,max(0,(int)($raw_answers[$q]??0)));
                echo wp_json_encode($vals);
            ?>;
            var colors = values.map(function(v){
                return v===0?'#68d391':v===1?'#f6e05e':v===2?'#f6ad55':'#fc8181';
            });
            function init(){
                if(typeof Chart==='undefined'){setTimeout(init,100);return;}
                var ctx=document.getElementById('pa-bdi-chart');
                if(!ctx)return;
                new Chart(ctx.getContext('2d'),{type:'bar',data:{labels:labels,datasets:[{data:values,backgroundColor:colors,borderRadius:4}]},options:{responsive:true,plugins:{legend:{display:false}},scales:{y:{min:0,max:3,ticks:{stepSize:1}}}}});
            }
            document.addEventListener('DOMContentLoaded',init);
        })();
        </script>
        <?php
        return ob_get_clean();
    }
}
\PsychAssessments\Core\Registry::register( new BDI() );
