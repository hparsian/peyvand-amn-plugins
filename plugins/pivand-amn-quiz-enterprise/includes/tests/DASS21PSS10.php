<?php
namespace PsychAssessments\Tests;
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * DASS-21: Depression Anxiety Stress Scales – Short Form
 *
 * - Lovibond, S.H. & Lovibond, P.F. (1995). The structure of negative emotional states.
 *   Behaviour Research and Therapy, 33(3), 335–343.
 * - Sahebi, A. et al. (2005). هنجاریابی DASS-21 در ایران.
 * - نمرات باید ×2 شوند (چون نسخه کوتاه است) قبل از تفسیر
 * - cut-off: افسردگی: 0–9 / 10–13 / 14–20 / 21–27 / ≥28
 *            اضطراب:  0–7  / 8–9   / 10–14 / 15–19 / ≥20
 *            استرس:   0–14 / 15–18 / 19–25 / 26–33 / ≥34
 */
class DASS21 extends \PsychAssessments\Core\BaseTest {

    public function get_id(): string        { return 'dass21'; }
    public function get_category(): string      { return 'mental_health'; }
    public function get_name(): string      { return 'سلامت روان (DASS-21)'; }
    public function needs_chart(): bool   { return true; }  // نمودار Chart.js

    public function get_description(): string { return 'مقیاس افسردگی، اضطراب و استرس — ۲۱ سوال، ۳ زیرمقیاس'; }
    public function get_version(): string   { return '1.0.0'; }

    public function get_scale(): array {
        return [
            'min'    => 0,
            'max'    => 3,
            'labels' => [
                0 => 'اصلاً با من مطابقت نداشت',
                1 => 'تا اندازه‌ای یا گاهی با من مطابقت داشت',
                2 => 'بیشتر اوقات یا تا مقدار قابل توجهی با من مطابقت داشت',
                3 => 'کاملاً یا تقریباً همیشه با من مطابقت داشت',
            ],
        ];
    }

    private function subscales(): array {
        return [
            'depression' => [
                'questions' => [3,5,10,13,16,17,21],
                'label'     => 'افسردگی',
                'label_en'  => 'Depression',
                'color'     => '#553c9a',
                'cutoffs'   => [
                    ['max'=>9,  'label'=>'طبیعی',   'label_en'=>'Normal',   'color'=>'#276749','bg'=>'#f0fff4','clinical'=>'در محدوده طبیعی'],
                    ['max'=>13, 'label'=>'خفیف',    'label_en'=>'Mild',     'color'=>'#d69e2e','bg'=>'#fffff0','clinical'=>'خفیف'],
                    ['max'=>20, 'label'=>'متوسط',   'label_en'=>'Moderate', 'color'=>'#c05621','bg'=>'#fffaf0','clinical'=>'متوسط'],
                    ['max'=>27, 'label'=>'شدید',    'label_en'=>'Severe',   'color'=>'#c53030','bg'=>'#fff5f5','clinical'=>'شدید'],
                    [          'label'=>'خیلی شدید','label_en'=>'Ext.Severe','color'=>'#9b2c2c','bg'=>'#fff5f5','clinical'=>'خیلی شدید'],
                ],
            ],
            'anxiety'    => [
                'questions' => [2,4,7,9,15,19,20],
                'label'     => 'اضطراب',
                'label_en'  => 'Anxiety',
                'color'     => '#c53030',
                'cutoffs'   => [
                    ['max'=>7,  'label'=>'طبیعی',   'label_en'=>'Normal',   'color'=>'#276749','bg'=>'#f0fff4','clinical'=>'در محدوده طبیعی'],
                    ['max'=>9,  'label'=>'خفیف',    'label_en'=>'Mild',     'color'=>'#d69e2e','bg'=>'#fffff0','clinical'=>'خفیف'],
                    ['max'=>14, 'label'=>'متوسط',   'label_en'=>'Moderate', 'color'=>'#c05621','bg'=>'#fffaf0','clinical'=>'متوسط'],
                    ['max'=>19, 'label'=>'شدید',    'label_en'=>'Severe',   'color'=>'#c53030','bg'=>'#fff5f5','clinical'=>'شدید'],
                    [          'label'=>'خیلی شدید','label_en'=>'Ext.Severe','color'=>'#9b2c2c','bg'=>'#fff5f5','clinical'=>'خیلی شدید'],
                ],
            ],
            'stress'     => [
                'questions' => [1,6,8,11,12,14,18],
                'label'     => 'استرس',
                'label_en'  => 'Stress',
                'color'     => '#2b6cb0',
                'cutoffs'   => [
                    ['max'=>14, 'label'=>'طبیعی',   'label_en'=>'Normal',   'color'=>'#276749','bg'=>'#f0fff4','clinical'=>'در محدوده طبیعی'],
                    ['max'=>18, 'label'=>'خفیف',    'label_en'=>'Mild',     'color'=>'#d69e2e','bg'=>'#fffff0','clinical'=>'خفیف'],
                    ['max'=>25, 'label'=>'متوسط',   'label_en'=>'Moderate', 'color'=>'#c05621','bg'=>'#fffaf0','clinical'=>'متوسط'],
                    ['max'=>33, 'label'=>'شدید',    'label_en'=>'Severe',   'color'=>'#c53030','bg'=>'#fff5f5','clinical'=>'شدید'],
                    [          'label'=>'خیلی شدید','label_en'=>'Ext.Severe','color'=>'#9b2c2c','bg'=>'#fff5f5','clinical'=>'خیلی شدید'],
                ],
            ],
        ];
    }

    public function get_questions(): array {
        return [
            1  => 'برای آرام کردن خودم سخت بود.',
            2  => 'دهانم خشک شد.',
            3  => 'به نظر می‌رسید که هیچ احساس مثبتی ندارم.',
            4  => 'دچار اختلالات تنفسی شدم (نفس کم آوردم، بدون آنکه ورزش کرده باشم).',
            5  => 'به نظر می‌رسید که شروع به کارها برایم مشکل است.',
            6  => 'بر اثر مشکلاتی که پیش آمد، واکنش افراطی نشان دادم.',
            7  => 'لرزش دست داشتم.',
            8  => 'احساس کردم خیلی اعصابم متشنج است.',
            9  => 'نگران موقعیت‌هایی بودم که در آن‌ها ممکن است دچار وحشت شوم.',
            10 => 'احساس کردم چیزی برای انتظار داشتن نیست.',
            11 => 'می‌یافتم که به راحتی ناراحت می‌شوم.',
            12 => 'احساس کردم خیلی از اعصابم استفاده کرده‌ام.',
            13 => 'احساس دلتنگی و غمگینی کردم.',
            14 => 'متوجه شدم که اگر چیزی مرا از کارم باز می‌داشت، بی‌حوصله می‌شدم.',
            15 => 'احساس کردم در آستانه وحشت هستم.',
            16 => 'نسبت به هیچ چیز احساس اشتیاق نمی‌کردم.',
            17 => 'احساس کردم که به عنوان یک انسان ارزش چندانی ندارم.',
            18 => 'احساس کردم که خیلی حساسیت نشان می‌دهم.',
            19 => 'بدون دلیل واقعی عرق می‌کردم (مثلاً عرق کف دست).',
            20 => 'بدون دلیل واقعی می‌ترسیدم.',
            21 => 'احساس کردم زندگی بی‌معنا است.',
        ];
    }

    public function get_schema_labels(): array {
        return ['depression'=>'افسردگی','anxiety'=>'اضطراب','stress'=>'استرس'];
    }
    public function get_domain_labels(): array { return []; }

    public function calculate(array $answers): array {
        $answers = \PsychAssessments\Core\Scoring::sanitize_answers( $answers );
        $subs = $this->subscales();
        $scores = [];
        foreach ($subs as $key => $def) {
            // نمره خام × 2 = نمره مقیاس کامل
            $raw = \PsychAssessments\Core\Scoring::subscale_sum($def['questions'], $answers, 0, 3);
            $scores[$key] = $raw * 2;
        }
        return ['scores' => $scores];
    }

    public function render_report(array $data, object $session): string {
        $scores = $data['scores'];
        $subs   = $this->subscales();
        $completed_date = $session->completed_at ? pa_to_jalali($session->completed_at, 'Y/m/d ساعت H:i') : '---';

        $sevs = [];
        foreach ($subs as $key => $def) {
            $sevs[$key] = \PsychAssessments\Core\Scoring::severity((float)$scores[$key], $def['cutoffs']);
        }

        $radar_labels = array_map(fn($d)=>$d['label'], $subs);
        $radar_values = [$scores['depression'], $scores['anxiety'], $scores['stress']];
        $radar_max    = [42, 42, 42]; // max برای normalize

        ob_start();
        ?>
        <div class="psy-report-row">
            <?php foreach ([
                ['#ebf8ff','#2b6cb0','📞','تماس',$session->client_phone ?? $session->patient_phone ?? '---'],
                ['#f0fff4','#2f855a','✅','تاریخ',$completed_date],
                ['#fff5f5','#553c9a','😔','افسردگی',$scores['depression'].' ('.$sevs['depression']['label'].')'],
                ['#fff5f5','#c53030','😰','اضطراب',$scores['anxiety'].' ('.$sevs['anxiety']['label'].')'],
                ['#ebf8ff','#2b6cb0','😤','استرس',$scores['stress'].' ('.$sevs['stress']['label'].')'],
            ] as $c) echo \PsychAssessments\Core\ReportHTML::summary_card($c[0],$c[1],$c[2],$c[3],$c[4]); ?>
        </div>

        <!-- نمودار راداری -->
        <div class="psy-report-row psy-report-row--tight">
            <div class="psy-report-card psy-report-card--panel psy-report-chart-panel">
                <h3 class="psy-report-panel__title psy-report-panel__title--center">پروفایل DASS-21</h3>
                <canvas id="pa-radar-chart" class="psy-report-chart psy-report-chart--domains"></canvas>
            </div>
            <div class="psy-report-col--wide">
                <?php foreach ($subs as $key => $def):
                    $s = $scores[$key];
                    $sev = $sevs[$key];
                    $maxs = ['depression'=>42,'anxiety'=>42,'stress'=>42];
                    $pct = \PsychAssessments\Core\Scoring::percent($s, 0, $maxs[$key]);
                    $sub_tone = \PsychAssessments\Core\ReportHTML::tone( (string) $def['color'] );
                ?>
                <div class="psy-report-item psy-report-item--lg <?php echo esc_attr( $sub_tone ); ?>">
                    <div class="psy-clinical__head">
                        <span class="psy-report-name-lg"><?php echo esc_html($def['label']); ?> (×2 اعمال شد)</span>
                        <?php echo \PsychAssessments\Core\ReportHTML::score_badge((float)$s, $sev); ?>
                    </div>
                    <?php echo \PsychAssessments\Core\ReportHTML::progress_bar( (float) $pct, (string) $def['color'] ); ?>
                    <div class="psy-report-chip psy-report-chip--block">
                        💡 <?php echo esc_html($sev['clinical']); ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="psy-report-refs psy-report-refs--muted">
            <b>منابع:</b> Lovibond &amp; Lovibond (1995). <i>Behaviour Research and Therapy, 33</i>(3). | Sahebi et al. (2005) هنجاریابی ایران.<br>
            📌 نمرات DASS-21 باید ×2 شوند — در این گزارش اعمال شده.<br>
            <b class="psy-tone--danger psy-text">⚠️</b> ابزار کمک‌بالینی — مبنای تشخیص رسمی نیست.
        </div>

        <script>
        (function(){
            function init(){
                if(typeof Chart==='undefined'){setTimeout(init,100);return;}
                var ctx=document.getElementById('pa-radar-chart');
                if(!ctx)return;
                new Chart(ctx.getContext('2d'),{
                    type:'radar',
                    data:{
                        labels:<?php echo wp_json_encode(array_values(array_map(fn($d)=>$d['label'].' ('.$d['label_en'].')', $subs))); ?>,
                        datasets:[{
                            data:<?php echo wp_json_encode(array_values($radar_values)); ?>,
                            backgroundColor:'rgba(197,48,48,0.15)',
                            borderColor:'#c53030',borderWidth:2,
                            pointBackgroundColor:<?php echo wp_json_encode(array_values(array_map(fn($d)=>$d['color'],$subs))); ?>,
                            pointRadius:7
                        }]
                    },
                    options:{responsive:true,scales:{r:{min:0,max:42,ticks:{stepSize:14},pointLabels:{font:{family:'Tahoma',size:12}}}},plugins:{legend:{display:false}}}
                });
            }
            document.addEventListener('DOMContentLoaded',init);
        })();
        </script>
        <?php
        return ob_get_clean();
    }
}
\PsychAssessments\Core\Registry::register( new DASS21() );

// ─────────────────────────────────────────────────────────────────────────────

/**
 * PSS-10: Perceived Stress Scale
 *
 * - Cohen, S., Kamarck, T., & Mermelstein, R. (1983). A global measure of perceived stress.
 *   Journal of Health and Social Behavior, 24(4), 385–396.
 * - Maroufizadeh, S. et al. (2014). هنجاریابی PSS-10 در ایران.
 * - cut-off: 0–13 پایین | 14–26 متوسط | 27–40 بالا
 * - سوالات 4,5,7,8 معکوس
 */
class PSS10 extends \PsychAssessments\Core\BaseTest {

    // باگ M: PSS10 فقط progress bar دارد — Chart.js لازم نیست
    public function needs_chart(): bool { return false; }

    public function get_id(): string        { return 'pss10'; }
    public function get_category(): string      { return 'mental_health'; }
    public function get_name(): string      { return 'استرس ادراک‌شده (PSS-10)'; }
    public function get_description(): string { return 'مقیاس استرس ادراک‌شده کوهن — ۱۰ سوال، سنجش استرس ماه گذشته، محدوده ۰–۴۰'; }
    public function get_version(): string   { return '1.0.0'; }

    public function get_scale(): array {
        return [
            'min'    => 0,
            'max'    => 4,
            'labels' => [
                0 => 'هرگز',
                1 => 'تقریباً هرگز',
                2 => 'گاهی اوقات',
                3 => 'اغلب اوقات',
                4 => 'خیلی اوقات',
            ],
        ];
    }

    public function get_questions(): array {
        return [
            1  => 'در ماه گذشته چقدر اوقات ناراحت شدید زیرا اتفاق غیرمنتظره‌ای افتاد؟',
            2  => 'در ماه گذشته چقدر اوقات احساس کردید که نمی‌توانید کارها و مشکلات مهمتان را کنترل کنید؟',
            3  => 'در ماه گذشته چقدر اوقات احساس عصبی بودن و استرس کردید؟',
            4  => 'در ماه گذشته چقدر اوقات با اطمینان توانستید با مشکلات روزانه‌تان کنار بیایید؟ (معکوس)',
            5  => 'در ماه گذشته چقدر اوقات احساس کردید که کارها درست پیش می‌رود؟ (معکوس)',
            6  => 'در ماه گذشته چقدر اوقات متوجه شدید که نمی‌توانید با همه کارهایی که باید انجام دهید کنار بیایید؟',
            7  => 'در ماه گذشته چقدر اوقات توانستید مشکلات آزاردهنده‌تان را کنترل کنید؟ (معکوس)',
            8  => 'در ماه گذشته چقدر اوقات احساس کردید که اوضاع را در دست دارید؟ (معکوس)',
            9  => 'در ماه گذشته چقدر اوقات به خاطر چیزهایی که خارج از کنترل شما بود عصبی شدید؟',
            10 => 'در ماه گذشته چقدر اوقات احساس کردید که مشکلات آنقدر زیاد شده که نمی‌توانید از عهده‌شان بربیایید؟',
        ];
    }

    public function get_schema_labels(): array { return []; }
    public function get_domain_labels(): array { return []; }

    private function reversed_items(): array { return [4,5,7,8]; }

    private function get_severity(float $total): array {
        return \PsychAssessments\Core\Scoring::severity($total, [
            ['max'=>13, 'label'=>'پایین',  'label_en'=>'Low',      'color'=>'#276749','bg'=>'#f0fff4','clinical'=>'سطح استرس در محدوده طبیعی','range'=>'0–13'],
            ['max'=>26, 'label'=>'متوسط', 'label_en'=>'Moderate', 'color'=>'#d69e2e','bg'=>'#fffff0','clinical'=>'استرس متوسط — مدیریت استرس توصیه می‌شود','range'=>'14–26'],
            [           'label'=>'بالا',  'label_en'=>'High',     'color'=>'#9b2c2c','bg'=>'#fff5f5','clinical'=>'استرس بالا — مداخله لازم است','range'=>'27–40'],
        ]);
    }

    public function calculate(array $answers): array {
        $answers = \PsychAssessments\Core\Scoring::sanitize_answers( $answers );
        $reversed = $this->reversed_items();
        $total = 0;
        for ($q = 1; $q <= 10; $q++) {
            $raw = min(4, max(0, (int)($answers[$q] ?? 0)));
            $total += in_array($q, $reversed) ? (4 - $raw) : $raw;
        }
        return ['total' => $total];
    }

    public function render_report(array $data, object $session): string {
        $total = $data['total'];
        $sev   = $this->get_severity((float)$total);
        $completed_date = $session->completed_at ? pa_to_jalali($session->completed_at, 'Y/m/d ساعت H:i') : '---';

        ob_start();
        ?>
        <div class="psy-report-row">
            <?php foreach ([
                ['#ebf8ff','#2b6cb0','📞','تماس',$session->client_phone ?? $session->patient_phone ?? '---'],
                ['#f0fff4','#2f855a','✅','تاریخ',$completed_date],
                [$sev['bg'],$sev['color'],'😤','PSS-10',$total.' از ۴۰'],
                [$sev['bg'],$sev['color'],'🏷','سطح',$sev['label'],$sev['label_en']],
            ] as $c) echo \PsychAssessments\Core\ReportHTML::summary_card($c[0],$c[1],$c[2],$c[3],$c[4]); ?>
        </div>

        <div class="psy-report-card--tint <?php echo esc_attr( \PsychAssessments\Core\ReportHTML::tone( (string) $sev['color'] ) ); ?>">
            <div class="psy-clinical__head">
                <div>
                    <h3 class="psy-report-hero__title">استرس ادراک‌شده: <?php echo esc_html((int)$total); ?> از ۴۰</h3>
                    <p class="psy-report-hero__text"><?php echo esc_html($sev['clinical']); ?></p>
                </div>
                <?php echo \PsychAssessments\Core\ReportHTML::score_badge((float)$total, $sev); ?>
            </div>
            <?php echo \PsychAssessments\Core\ReportHTML::progress_bar( (float) \PsychAssessments\Core\Scoring::percent($total,0,40), $sev['color'], true ); ?>
            <div class="psy-report-scale">
                <span class="psy-report-scale__mark">۰ پایین</span>
                <span class="psy-tone--warning"><span class="psy-report-scale__mark psy-text">۱۴ متوسط</span></span>
                <span class="psy-tone--danger"><span class="psy-report-scale__mark psy-text">۲۷ بالا</span></span>
                <span class="psy-report-scale__mark">۴۰</span>
            </div>
        </div>

        <div class="psy-report-alert psy-tone--info">
            <p class="psy-report-alert__text psy-report-alert__text--sm">
                📌 <b>نکته:</b> سوالات ۴، ۵، ۷ و ۸ معکوس‌شده هستند و در نمره‌گذاری اعمال شده است. PSS-10 استرس <i>ادراک‌شده</i> را می‌سنجد نه رویدادهای استرس‌زا.
            </p>
        </div>

        <div class="psy-report-refs psy-report-refs--muted">
            <b>منابع:</b> Cohen et al. (1983). <i>J. Health and Social Behavior, 24</i>(4). | Maroufizadeh et al. (2014) هنجاریابی ایران.<br>
            <b class="psy-tone--danger psy-text">⚠️</b> ابزار کمک‌بالینی — مبنای تشخیص رسمی نیست.
        </div>
        <?php
        return ob_get_clean();
    }
}
\PsychAssessments\Core\Registry::register( new PSS10() );
