<?php
namespace PsychAssessments\Tests;
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * پرسشنامه اضطراب بک (BAI)
 *
 * - Beck, A.T., Epstein, N., Brown, G., & Steer, R.A. (1988). An inventory for measuring
 *   clinical anxiety. Journal of Consulting and Clinical Psychology, 56(6), 893–897.
 * - Kaviani, H. & Mousavi, A.S. (2008). هنجاریابی BAI در ایران.
 *   Tehran University Medical Journal, 66(2), 136–140.
 * - cut-off: 0–7 حداقل | 8–15 خفیف | 16–25 متوسط | 26–63 شدید
 * - ۲۱ سوال، هر سوال ۰–۳
 */
class BAI extends \PsychAssessments\Core\BaseTest {

    public function get_id(): string        { return 'bai'; }
    // باگ R: BAI سنجش اضطراب است نه خلق — category اصلاح شد
    public function get_category(): string      { return 'mental_health'; }
    public function get_name(): string      { return 'اضطراب بک (BAI)'; }
    public function needs_chart(): bool   { return true; }  // نمودار Chart.js

    public function get_description(): string { return 'پرسشنامه اضطراب بک — ۲۱ سوال، محدوده ۰–۶۳، علائم اضطراب هفته گذشته'; }
    public function get_version(): string   { return '1.0.0'; }

    public function get_scale(): array {
        return [
            'min'    => 0,
            'max'    => 3,
            'labels' => [
                0 => 'اصلاً — اصلاً آزارم نداد',
                1 => 'کمی — زیاد آزارم نداد',
                2 => 'متوسط — ناراحت کننده بود ولی تحملش کردم',
                3 => 'شدید — به سختی توانستم تحمل کنم',
            ],
        ];
    }

    public function get_questions(): array {
        return [
            1  => 'بی‌حسی یا گزگز',
            2  => 'احساس گرما',
            3  => 'لرزش پا',
            4  => 'ناتوانی در آرام شدن',
            5  => 'ترس از بدترین اتفاق',
            6  => 'سرگیجه یا سبکی سر',
            7  => 'تپش قلب',
            8  => 'بی‌ثباتی',
            9  => 'احساس وحشت',
            10 => 'عصبی بودن',
            11 => 'احساس خفگی',
            12 => 'دست‌های لرزان',
            13 => 'لرزش',
            14 => 'ترس از کنترل از دست دادن',
            15 => 'مشکل در نفس کشیدن',
            16 => 'ترس از مردن',
            17 => 'وحشت‌زدگی',
            18 => 'ناراحتی معده',
            19 => 'احساس ضعف',
            20 => 'گر گرفتن',
            21 => 'تعریق (نه از گرما)',
        ];
    }

    public function get_schema_labels(): array { return []; }
    public function get_domain_labels(): array { return []; }

    private function get_severity(float $total): array {
        return \PsychAssessments\Core\Scoring::severity($total, [
            ['max'=>7,  'label'=>'حداقل', 'label_en'=>'Minimal',  'color'=>'#276749','bg'=>'#f0fff4','clinical'=>'اضطراب در محدوده طبیعی',                     'range'=>'0–7'],
            ['max'=>15, 'label'=>'خفیف',  'label_en'=>'Mild',     'color'=>'#d69e2e','bg'=>'#fffff0','clinical'=>'اضطراب خفیف — مشاوره مفید است',              'range'=>'8–15'],
            ['max'=>25, 'label'=>'متوسط', 'label_en'=>'Moderate', 'color'=>'#c05621','bg'=>'#fffaf0','clinical'=>'اضطراب متوسط — درمان روان‌شناختی لازم است', 'range'=>'16–25'],
            [           'label'=>'شدید',  'label_en'=>'Severe',   'color'=>'#9b2c2c','bg'=>'#fff5f5','clinical'=>'اضطراب شدید — مداخله فوری ضروری',           'range'=>'26–63'],
        ]);
    }

    public function calculate(array $answers): array {
        $answers = \PsychAssessments\Core\Scoring::sanitize_answers( $answers );
        $total = 0;
        for ($q = 1; $q <= 21; $q++) {
            $total += min(3, max(0, (int)($answers[$q] ?? 0)));
        }
        return ['total' => $total];
    }

    public function render_report(array $data, object $session): string {
        $total  = $data['total'];
        $sev    = $this->get_severity((float)$total);
        $completed_date = $session->completed_at ? pa_to_jalali($session->completed_at, 'Y/m/d ساعت H:i') : '---';
        // باگ U: defensive check — answers ممکن است در برخی contexts موجود نباشد
        $raw_answers = [];
        if ( ! empty( $session->answers ) ) {
            $raw_answers = is_string( $session->answers )
                ? pa_decrypt_json( $session->answers )
                : (array) $session->answers;
        }

        $q_labels = array_values(array_map(fn($q) => 'س'.$q, range(1,21)));
        $q_values = [];
        for ($q=1;$q<=21;$q++) $q_values[] = min(3,max(0,(int)($raw_answers[$q]??0)));

        ob_start();
        ?>
        <div style="display:flex;gap:12px;flex-wrap:wrap;margin-bottom:24px;">
            <?php foreach ([
                ['#ebf8ff','#2b6cb0','📞','شماره تماس',$session->client_phone ?? $session->patient_phone ?? '---'],
                ['#f0fff4','#2f855a','✅','تاریخ تکمیل',$completed_date],
                [$sev['bg'],$sev['color'],'😰','نمره کل BAI',$total.' از ۶۳'],
                [$sev['bg'],$sev['color'],'🏷','سطح اضطراب',$sev['label'].' ('.$sev['label_en'].')'],
            ] as $c) echo \PsychAssessments\Core\ReportHTML::summary_card($c[0],$c[1],$c[2],$c[3],$c[4]); ?>
        </div>

        <div style="background:<?php echo esc_attr($sev['bg']); ?>;border:2px solid <?php echo esc_attr($sev['color']); ?>;border-radius:10px;padding:22px;margin-bottom:24px;">
            <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;">
                <div>
                    <h3 style="margin:0 0 6px;color:<?php echo esc_attr($sev['color']); ?>;font-size:18px;">📊 نمره کل: <?php echo esc_html((int)$total); ?> از ۶۳</h3>
                    <p style="margin:0;font-size:13px;color:#4a5568;"><?php echo esc_html($sev['clinical']); ?></p>
                </div>
                <?php echo \PsychAssessments\Core\ReportHTML::score_badge((float)$total, $sev); ?>
            </div>
            <div style="margin-top:14px;background:#edf2f7;border-radius:6px;height:12px;overflow:hidden;">
                <div style="background:<?php echo esc_attr($sev['color']); ?>;height:100%;width:<?php echo \PsychAssessments\Core\Scoring::percent($total,0,63); ?>%;border-radius:6px;"></div>
            </div>
            <div style="display:flex;justify-content:space-between;font-size:10px;color:#a0aec0;margin-top:4px;">
                <span>0 حداقل</span><span style="color:#d69e2e;">8 خفیف</span><span style="color:#c05621;">16 متوسط</span><span style="color:#9b2c2c;">26 شدید</span><span>63</span>
            </div>
        </div>

        <div style="background:#fff;padding:20px;border-radius:10px;box-shadow:0 2px 10px rgba(0,0,0,0.08);margin-bottom:24px;">
            <h3 style="margin-top:0;text-align:center;font-size:15px;">پروفایل علائم اضطراب</h3>
            <canvas id="pa-bai-chart" style="max-height:300px;"></canvas>
        </div>

        <div style="background:#f7fafc;border:1px solid #e2e8f0;border-radius:10px;padding:20px;margin-bottom:24px;">
            <h3 style="margin-top:0;font-size:15px;">📚 راهنمای تفسیر (Beck et al., 1988)</h3>
            <table style="width:100%;border-collapse:collapse;font-size:12px;">
                <thead><tr style="background:#edf2f7;">
                    <th style="text-align:right;padding:8px;border:1px solid #e2e8f0;">نمره</th>
                    <th style="text-align:right;padding:8px;border:1px solid #e2e8f0;">سطح</th>
                    <th style="text-align:right;padding:8px;border:1px solid #e2e8f0;">توصیه</th>
                </tr></thead>
                <tbody>
                <?php foreach ([
                    ['0–7',   'حداقل','#276749','پایش معمول'],
                    ['8–15',  'خفیف', '#d69e2e','مشاوره مفید است'],
                    ['16–25', 'متوسط','#c05621','CBT یا دارودرمانی'],
                    ['26–63', 'شدید', '#9b2c2c','ارزیابی فوری — روان‌پزشکی'],
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
            <b style="color:#4a5568;">منابع:</b> Beck et al. (1988). <i>J. Consulting & Clinical Psychology, 56</i>(6). | Kaviani & Mousavi (2008). هنجاریابی ایران.<br>
            <b style="color:#c53030;">⚠️</b> ابزار کمک‌بالینی — مبنای تشخیص رسمی نیست.
        </div>

        <script>
        (function(){
            var labels=<?php echo wp_json_encode($q_labels); ?>;
            var values=<?php echo wp_json_encode($q_values); ?>;
            var colors=values.map(function(v){return v===0?'#68d391':v===1?'#f6e05e':v===2?'#f6ad55':'#fc8181';});
            function init(){
                if(typeof Chart==='undefined'){setTimeout(init,100);return;}
                var ctx=document.getElementById('pa-bai-chart');
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
\PsychAssessments\Core\Registry::register( new BAI() );
