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

        $sev_tone = \PsychAssessments\Core\ReportHTML::tone( $sev['color'] );

        ob_start();
        ?>
        <div class="psy-report-row">
            <?php foreach ([
                ['#ebf8ff','#2b6cb0','📞','شماره تماس',$session->client_phone ?? $session->patient_phone ?? '---'],
                ['#f0fff4','#2f855a','✅','تاریخ تکمیل',$completed_date],
                [$sev['bg'],$sev['color'],'😰','نمره کل BAI',$total.' از ۶۳'],
                [$sev['bg'],$sev['color'],'🏷','سطح اضطراب',$sev['label'].' ('.$sev['label_en'].')'],
            ] as $c) echo \PsychAssessments\Core\ReportHTML::summary_card($c[0],$c[1],$c[2],$c[3],$c[4]); ?>
        </div>

        <div class="psy-report-card--tint <?php echo esc_attr( $sev_tone ); ?>">
            <div class="psy-clinical__head">
                <div>
                    <h3 class="psy-report-hero__title psy-report-hero__title--lg">📊 نمره کل: <?php echo esc_html((int)$total); ?> از ۶۳</h3>
                    <p class="psy-report-hero__text"><?php echo esc_html($sev['clinical']); ?></p>
                </div>
                <?php echo \PsychAssessments\Core\ReportHTML::score_badge((float)$total, $sev); ?>
            </div>
            <?php echo \PsychAssessments\Core\ReportHTML::progress_bar( \PsychAssessments\Core\Scoring::percent($total,0,63), $sev['color'], true ); ?>
            <div class="psy-report-scale">
                <span>0 حداقل</span><span class="psy-report-scale__mark psy-tone--warning psy-text">8 خفیف</span><span class="psy-report-scale__mark psy-tone--accent psy-text">16 متوسط</span><span class="psy-report-scale__mark psy-tone--danger psy-text">26 شدید</span><span>63</span>
            </div>
        </div>

        <div class="psy-report-card psy-report-card--panel psy-report-chart-panel psy-report-chart-panel--center">
            <h3 class="psy-report-panel__title psy-report-center">پروفایل علائم اضطراب</h3>
            <canvas id="pa-bai-chart" class="psy-report-chart psy-report-chart--tall"></canvas>
        </div>

        <div class="psy-report-panel psy-report-panel--soft">
            <h3 class="psy-report-panel__title">📚 راهنمای تفسیر (Beck et al., 1988)</h3>
            <table class="psy-report-table">
                <thead><tr>
                    <th class="psy-report-table__head psy-report-table__head--strong psy-report-table__cell--start">نمره</th>
                    <th class="psy-report-table__head psy-report-table__head--strong psy-report-table__cell--start">سطح</th>
                    <th class="psy-report-table__head psy-report-table__head--strong psy-report-table__cell--start">توصیه</th>
                </tr></thead>
                <tbody>
                <?php foreach ([
                    ['0–7',   'حداقل','#276749','پایش معمول'],
                    ['8–15',  'خفیف', '#d69e2e','مشاوره مفید است'],
                    ['16–25', 'متوسط','#c05621','CBT یا دارودرمانی'],
                    ['26–63', 'شدید', '#9b2c2c','ارزیابی فوری — روان‌پزشکی'],
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
            <b>منابع:</b> Beck et al. (1988). <i>J. Consulting &amp; Clinical Psychology, 56</i>(6). | Kaviani &amp; Mousavi (2008). هنجاریابی ایران.<br>
            <b class="psy-tone--danger psy-text">⚠️</b> ابزار کمک‌بالینی — مبنای تشخیص رسمی نیست.
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
