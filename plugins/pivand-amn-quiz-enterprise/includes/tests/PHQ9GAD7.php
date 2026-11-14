<?php
namespace PsychAssessments\Tests;
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * PHQ-9: Patient Health Questionnaire – Depression
 *
 * - Kroenke, K., Spitzer, R.L., & Williams, J.B.W. (2001). The PHQ-9: validity of a brief
 *   depression severity measure. Journal of General Internal Medicine, 16(9), 606–613.
 * - Dadfar, M. & Kalibatseva, Z. (2016). Psychometric properties of PHQ-9 in Iranian sample.
 * - cut-off: 0–4 حداقل | 5–9 خفیف | 10–14 متوسط | 15–19 نسبتاً شدید | 20–27 شدید
 * - ۹ سوال، هر سوال ۰–۳، مقیاس ۰–۲۷
 * - سوال ۹ = آسیب به خود (هشدار بالینی اجباری)
 */
class PHQ9 extends \PsychAssessments\Core\BaseTest {

    // باگ M: PHQ9 فقط progress bar دارد — Chart.js لازم نیست
    public function needs_chart(): bool { return false; }

    public function get_id(): string        { return 'phq9'; }
    public function get_category(): string      { return 'mental_health'; }
    public function get_name(): string      { return 'افسردگی PHQ-9'; }
    public function get_description(): string { return 'پرسشنامه سلامت بیمار (PHQ-9) — ۹ سوال، غربالگری سریع افسردگی، محدوده ۰–۲۷'; }
    public function get_version(): string   { return '1.0.0'; }

    public function get_scale(): array {
        return [
            'min'    => 0,
            'max'    => 3,
            'labels' => [
                0 => 'اصلاً',
                1 => 'چند روز',
                2 => 'بیش از نیمی از روزها',
                3 => 'تقریباً هر روز',
            ],
        ];
    }

    public function get_questions(): array {
        return [
            1 => 'کم علاقگی یا لذت نبردن از انجام کارها',
            2 => 'احساس افسردگی، ناامیدی یا بی‌پناهی',
            3 => 'مشکل در به خواب رفتن، بیدار ماندن یا خواب زیاد',
            4 => 'احساس خستگی یا داشتن انرژی کم',
            5 => 'کم‌اشتهایی یا پراشتهایی',
            6 => 'احساس بدی در مورد خودتان — یا اینکه شکست خورده‌اید یا خودتان یا خانواده‌تان را ناامید کرده‌اید',
            7 => 'مشکل در تمرکز روی چیزهایی مثل خواندن روزنامه یا تماشای تلویزیون',
            8 => 'حرکت یا صحبت کردن آنقدر کند که دیگران متوجه شده باشند — یا برعکس: آنقدر بی‌قرار بوده‌اید که بیشتر از حد معمول راه رفته‌اید',
            9 => 'افکاری مبنی بر اینکه بهتر بود مرده بودید یا می‌خواستید به خودتان آسیب بزنید',
        ];
    }

    public function get_schema_labels(): array { return []; }
    public function get_domain_labels(): array { return []; }

    private function get_severity(float $total): array {
        return \PsychAssessments\Core\Scoring::severity($total, [
            ['max'=>4,  'label'=>'حداقل',        'label_en'=>'Minimal',           'color'=>'#276749','bg'=>'#f0fff4','clinical'=>'نشانه‌های افسردگی در حد طبیعی',                      'range'=>'0–4'],
            ['max'=>9,  'label'=>'خفیف',          'label_en'=>'Mild',              'color'=>'#d69e2e','bg'=>'#fffff0','clinical'=>'افسردگی خفیف — نظارت و پیگیری توصیه می‌شود',         'range'=>'5–9'],
            ['max'=>14, 'label'=>'متوسط',         'label_en'=>'Moderate',          'color'=>'#c05621','bg'=>'#fffaf0','clinical'=>'افسردگی متوسط — برنامه درمانی طراحی شود',            'range'=>'10–14'],
            ['max'=>19, 'label'=>'نسبتاً شدید',  'label_en'=>'Moderately Severe', 'color'=>'#c53030','bg'=>'#fff5f5','clinical'=>'افسردگی نسبتاً شدید — درمان فعال لازم است',          'range'=>'15–19'],
            [           'label'=>'شدید',          'label_en'=>'Severe',            'color'=>'#9b2c2c','bg'=>'#fff5f5','clinical'=>'افسردگی شدید — ارزیابی روان‌پزشکی فوری ضروری',     'range'=>'20–27'],
        ]);
    }

    public function calculate(array $answers): array {
        $answers = \PsychAssessments\Core\Scoring::sanitize_answers( $answers );
        $total = 0;
        for ($q = 1; $q <= 9; $q++) {
            $total += min(3, max(0, (int)($answers[$q] ?? 0)));
        }
        return [
            'total'        => $total,
            'suicide_item' => min(3, max(0, (int)($answers[9] ?? 0))),
        ];
    }

    public function render_report(array $data, object $session): string {
        $total  = $data['total'];
        $sev    = $this->get_severity((float)$total);
        $si     = $data['suicide_item'];
        $completed_date = $session->completed_at ? pa_to_jalali($session->completed_at, 'Y/m/d ساعت H:i') : '---';

        // فاز ۱۴.۴: رنگ سطح با کلاس تُن، نه استایل درون‌خطی
        $tone = \PsychAssessments\Core\ReportHTML::tone( $sev['color'] );

        ob_start();
        ?>
        <?php if ($si >= 1): ?>
        <div class="psy-report-alert psy-tone--danger">
            <h3 class="psy-report-alert__title">⚠️ هشدار — سوال ۹ (افکار آسیب به خود)</h3>
            <p class="psy-report-alert__text">مراجع در سوال ۹ نمره <b><?php echo esc_html((int)$si); ?></b> گزارش داده. ارزیابی خطر خودکشی الزامی است.</p>
        </div>
        <?php endif; ?>

        <div class="psy-report-row">
            <?php foreach ([
                ['#ebf8ff','#2b6cb0','📞','تماس',$session->client_phone ?? $session->patient_phone ?? '---'],
                ['#f0fff4','#2f855a','✅','تاریخ',$completed_date],
                [$sev['bg'],$sev['color'],'📊','PHQ-9',$total.' از ۲۷'],
                [$sev['bg'],$sev['color'],'🏷','سطح',$sev['label']],
            ] as $c) echo \PsychAssessments\Core\ReportHTML::summary_card($c[0],$c[1],$c[2],$c[3],$c[4]); ?>
        </div>

        <div class="psy-report-card--tint <?php echo esc_attr( $tone ); ?>">
            <div class="psy-clinical__head">
                <div>
                    <h3 class="psy-report-hero__title">نمره PHQ-9: <?php echo esc_html((int)$total); ?> از ۲۷</h3>
                    <p class="psy-report-hero__text"><?php echo esc_html($sev['clinical']); ?></p>
                </div>
                <?php echo \PsychAssessments\Core\ReportHTML::score_badge((float)$total, $sev); ?>
            </div>
            <?php echo \PsychAssessments\Core\ReportHTML::progress_bar( \PsychAssessments\Core\Scoring::percent($total,0,27), $sev['color'], true ); ?>
            <div class="psy-report-scale">
                <span>0</span><span class="psy-report-scale__mark psy-tone--warning psy-text">5</span><span class="psy-report-scale__mark psy-tone--accent psy-text">10</span><span class="psy-report-scale__mark psy-tone--danger psy-text">15</span><span class="psy-report-scale__mark psy-tone--danger psy-text">20</span><span>27</span>
            </div>
        </div>

        <div class="psy-report-panel psy-report-panel--soft">
            <h3 class="psy-report-panel__title">📚 راهنمای تفسیر (Kroenke et al., 2001)</h3>
            <table class="psy-report-table">
                <thead><tr>
                    <th class="psy-report-table__head psy-report-table__head--strong psy-report-table__cell--start">نمره</th>
                    <th class="psy-report-table__head psy-report-table__head--strong psy-report-table__cell--start">سطح</th>
                    <th class="psy-report-table__head psy-report-table__head--strong psy-report-table__cell--start">اقدام پیشنهادی</th>
                </tr></thead>
                <tbody>
                <?php foreach ([
                    ['0–4',   'حداقل',       '#276749','نظارت؛ اگه ادامه یافت، درمان کنید'],
                    ['5–9',   'خفیف',         '#d69e2e','برنامه Watch & Wait; CBT در صورت نیاز'],
                    ['10–14', 'متوسط',        '#c05621','CBT و/یا دارودرمانی'],
                    ['15–19', 'نسبتاً شدید', '#c53030','دارودرمانی فعال + روان‌درمانی'],
                    ['20–27', 'شدید',         '#9b2c2c','ارجاع فوری روان‌پزشکی'],
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
            <b>منابع:</b> Kroenke et al. (2001). <i>J. General Internal Medicine, 16</i>(9). | Dadfar &amp; Kalibatseva (2016).<br>
            <b class="psy-tone--danger psy-text">⚠️</b> ابزار غربالگری — مبنای تشخیص DSM نیست.
        </div>
        <?php
        return ob_get_clean();
    }
}
\PsychAssessments\Core\Registry::register( new PHQ9() );

// ─────────────────────────────────────────────────────────────────────────────

/**
 * GAD-7: Generalized Anxiety Disorder Scale
 *
 * - Spitzer, R.L. et al. (2006). A brief measure for assessing generalized anxiety disorder.
 *   Archives of Internal Medicine, 166(10), 1092–1097.
 * - نجفی, م. و همکاران (2013). هنجاریابی GAD-7 در ایران.
 * - cut-off: 0–4 حداقل | 5–9 خفیف | 10–14 متوسط | 15–21 شدید
 */
class GAD7 extends \PsychAssessments\Core\BaseTest {

    // باگ M: GAD7 فقط progress bar دارد — Chart.js لازم نیست
    public function needs_chart(): bool { return false; }

    public function get_id(): string        { return 'gad7'; }
    // باگ N: GAD7 اختلال اضطراب فراگیر است — category اصلاح شد از mood به anxiety
    public function get_category(): string      { return 'mental_health'; }
    public function get_name(): string      { return 'اضطراب GAD-7'; }
    public function get_description(): string { return 'مقیاس اختلال اضطراب فراگیر — ۷ سوال، غربالگری سریع اضطراب، محدوده ۰–۲۱'; }
    public function get_version(): string   { return '1.0.0'; }

    public function get_scale(): array {
        return [
            'min'    => 0,
            'max'    => 3,
            'labels' => [
                0 => 'اصلاً',
                1 => 'چند روز',
                2 => 'بیش از نیمی از روزها',
                3 => 'تقریباً هر روز',
            ],
        ];
    }

    public function get_questions(): array {
        return [
            1 => 'احساس عصبی بودن، اضطراب یا دلشوره',
            2 => 'ناتوانی در متوقف کردن یا کنترل نگرانی',
            3 => 'نگرانی بیش از حد درباره چیزهای مختلف',
            4 => 'مشکل در آرام شدن',
            5 => 'آنقدر بی‌قراری که نتوانید آرام بنشینید',
            6 => 'به راحتی تحریک‌پذیر یا عصبانی شدن',
            7 => 'احساس ترس — انگار که اتفاق بدی خواهد افتاد',
        ];
    }

    public function get_schema_labels(): array { return []; }
    public function get_domain_labels(): array { return []; }

    private function get_severity(float $total): array {
        return \PsychAssessments\Core\Scoring::severity($total, [
            ['max'=>4,  'label'=>'حداقل', 'label_en'=>'Minimal',  'color'=>'#276749','bg'=>'#f0fff4','clinical'=>'اضطراب در محدوده طبیعی',                  'range'=>'0–4'],
            ['max'=>9,  'label'=>'خفیف',  'label_en'=>'Mild',     'color'=>'#d69e2e','bg'=>'#fffff0','clinical'=>'اضطراب خفیف — پایش و مشاوره',             'range'=>'5–9'],
            ['max'=>14, 'label'=>'متوسط', 'label_en'=>'Moderate', 'color'=>'#c05621','bg'=>'#fffaf0','clinical'=>'اضطراب متوسط — درمان لازم است',           'range'=>'10–14'],
            [           'label'=>'شدید',  'label_en'=>'Severe',   'color'=>'#9b2c2c','bg'=>'#fff5f5','clinical'=>'اضطراب شدید — ارزیابی فوری ضروری',       'range'=>'15–21'],
        ]);
    }

    public function calculate(array $answers): array {
        $answers = \PsychAssessments\Core\Scoring::sanitize_answers( $answers );
        $total = 0;
        for ($q = 1; $q <= 7; $q++) {
            $total += min(3, max(0, (int)($answers[$q] ?? 0)));
        }
        return ['total' => $total];
    }

    public function render_report(array $data, object $session): string {
        $total = $data['total'];
        $sev   = $this->get_severity((float)$total);
        $completed_date = $session->completed_at ? pa_to_jalali($session->completed_at, 'Y/m/d ساعت H:i') : '---';

        $tone = \PsychAssessments\Core\ReportHTML::tone( $sev['color'] );

        ob_start();
        ?>
        <div class="psy-report-row">
            <?php foreach ([
                ['#ebf8ff','#2b6cb0','📞','تماس',$session->client_phone ?? $session->patient_phone ?? '---'],
                ['#f0fff4','#2f855a','✅','تاریخ',$completed_date],
                [$sev['bg'],$sev['color'],'😰','GAD-7',$total.' از ۲۱'],
                [$sev['bg'],$sev['color'],'🏷','سطح',$sev['label']],
            ] as $c) echo \PsychAssessments\Core\ReportHTML::summary_card($c[0],$c[1],$c[2],$c[3],$c[4]); ?>
        </div>

        <div class="psy-report-card--tint <?php echo esc_attr( $tone ); ?>">
            <div class="psy-clinical__head">
                <div>
                    <h3 class="psy-report-hero__title">نمره GAD-7: <?php echo esc_html((int)$total); ?> از ۲۱</h3>
                    <p class="psy-report-hero__text"><?php echo esc_html($sev['clinical']); ?></p>
                </div>
                <?php echo \PsychAssessments\Core\ReportHTML::score_badge((float)$total, $sev); ?>
            </div>
            <?php echo \PsychAssessments\Core\ReportHTML::progress_bar( \PsychAssessments\Core\Scoring::percent($total,0,21), $sev['color'], true ); ?>
            <div class="psy-report-scale">
                <span>0</span><span class="psy-report-scale__mark psy-tone--warning psy-text">5</span><span class="psy-report-scale__mark psy-tone--accent psy-text">10</span><span class="psy-report-scale__mark psy-tone--danger psy-text">15</span><span>21</span>
            </div>
        </div>

        <div class="psy-report-panel psy-report-panel--soft">
            <h3 class="psy-report-panel__title">📚 راهنمای تفسیر (Spitzer et al., 2006)</h3>
            <table class="psy-report-table">
                <thead><tr>
                    <th class="psy-report-table__head psy-report-table__head--strong psy-report-table__cell--start">نمره</th>
                    <th class="psy-report-table__head psy-report-table__head--strong psy-report-table__cell--start">سطح</th>
                    <th class="psy-report-table__head psy-report-table__head--strong psy-report-table__cell--start">حساسیت/ویژگی</th>
                </tr></thead>
                <tbody>
                <?php foreach ([
                    ['0–4',   'حداقل','#276749','—'],
                    ['5–9',   'خفیف', '#d69e2e','cut-off ≥5: حساسیت 89%, ویژگی 82%'],
                    ['10–14', 'متوسط','#c05621','cut-off ≥10: حساسیت 82%, ویژگی 90%'],
                    ['15–21', 'شدید', '#9b2c2c','cut-off ≥15: حساسیت 65%, ویژگی 96%'],
                ] as $r):
                    $r_tone = \PsychAssessments\Core\ReportHTML::tone( $r[2] ); ?>
                <tr>
                    <td class="psy-report-table__cell <?php echo esc_attr( $r_tone ); ?> psy-text psy-report-bold"><?php echo esc_html($r[0]); ?></td>
                    <td class="psy-report-table__cell <?php echo esc_attr( $r_tone ); ?> psy-text psy-report-bold"><?php echo esc_html($r[1]); ?></td>
                    <td class="psy-report-table__cell psy-report-cell--soft"><?php echo esc_html($r[3]); ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="psy-report-refs">
            <b>منابع:</b> Spitzer et al. (2006). <i>Archives of Internal Medicine, 166</i>(10). | Najafi et al. (2013) هنجاریابی ایران.<br>
            <b class="psy-tone--danger psy-text">⚠️</b> ابزار غربالگری — مبنای تشخیص رسمی نیست.
        </div>
        <?php
        return ob_get_clean();
    }
}
\PsychAssessments\Core\Registry::register( new GAD7() );
