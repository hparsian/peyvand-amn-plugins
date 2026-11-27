<?php
namespace PsychAssessments\Tests;
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * آزمون رضایت زناشویی — CSI-16
 *
 * مبانی علمی:
 * - Funk, J.L. & Rogge, R.D. (2007). Testing the ruler with item response theory:
 *   Increasing precision of measurement for relationship satisfaction with the
 *   Couples Satisfaction Index. Journal of Family Psychology, 21(4), 572–583.
 * - Rogge, R.D. et al. (2017). Predicting relationship quality via multiple pathways.
 *   Journal of Family Psychology.
 * - نمره کل: حداکثر ۸۱ | cut-off < 51.5 = ناراضی (Funk & Rogge, 2007)
 * - CSI-16 دارای مقیاس‌های مختلف است:
 *   سوال 1-3: مقیاس ۷ درجه‌ای (0-6)
 *   سوال 4: مقیاس ۵ درجه‌ای (0-4) — همیشه شاد تا همیشه ناشاد (معکوس)
 *   سوال 5-16: مقیاس ۵ درجه‌ای (0-4)
 */
class CSI extends \PsychAssessments\Core\BaseTest {

    public function get_id(): string        { return 'csi'; }
    public function get_category(): string      { return 'relationship'; }
    public function get_name(): string      { return 'رضایت زناشویی (CSI-16)'; }
    public function needs_chart(): bool   { return true; }  // نمودار Chart.js

    public function get_description(): string { return 'شاخص رضایت زوجین — ۱۶ سوال، دقیق‌ترین ابزار تک‌بُعدی رضایت زناشویی، cut-off < ۵۱.۵'; }
    public function get_version(): string   { return '1.0.0'; }

    /**
     * CSI-16 دارای مقیاس‌های ترکیبی است.
     * برای یکپارچگی فرم بیمار: همه را روی مقیاس 0–6 استانداردسازی می‌کنیم
     * و در calculate() به نمرات اصلی برمی‌گردانیم.
     */
    public function get_scale(): array {
        return [
            'min'    => 0,
            'max'    => 6,
            'labels' => [
                0 => 'کاملاً نه / خیلی ناراضی',
                1 => 'خیلی کم / ناراضی',
                2 => 'کمی / کمی ناراضی',
                3 => 'متوسط / نه راضی نه ناراضی',
                4 => 'زیاد / کمی راضی',
                5 => 'خیلی زیاد / راضی',
                6 => 'کاملاً / خیلی راضی',
            ],
        ];
    }

    public function get_questions(): array {
        return [
            // سوالات 1-3: مقیاس 0-6 (به‌صورت مستقیم استفاده می‌شود)
            1  => 'چقدر از رابطه‌تان راضی هستید؟ (0=کاملاً ناراضی تا 6=کاملاً راضی)',
            2  => 'چقدر رابطه‌تان خوب است؟ (0=خیلی بد تا 6=عالی)',
            3  => 'چقدر رابطه‌تان نیازهای شما را برآورده می‌کند؟ (0=اصلاً تا 6=کاملاً)',
            // سوال 4: معکوس (0=خیلی شاد → 4 امتیاز، 4=بدبخت → 0 امتیاز) — در فرم: 0=بدبخت 4=خیلی شاد
            4  => 'احساس کلی شما نسبت به رابطه‌تان چیست؟ (0=بدبخت و ناشاد تا 4=بسیار شاد)',
            // سوالات 5-16: مقیاس 0-4 (0=هرگز تا 4=همیشه)
            5  => 'آیا از وقتی که با شریک زندگی‌تان می‌گذرانید لذت می‌برید؟',
            6  => 'آیا از روابط جنسی با شریک زندگی‌تان راضی هستید؟',
            7  => 'آیا شریک زندگی‌تان را دوست صمیمی‌تان می‌دانید؟',
            8  => 'آیا از نحوه حل مشکلات با شریک زندگی‌تان راضی هستید؟',
            9  => 'آیا به شریک زندگی‌تان اعتماد دارید؟',
            10 => 'آیا از نحوه برخورد شریک زندگی‌تان با شما راضی هستید؟',
            11 => 'آیا از نحوه ابراز محبت شریک زندگی‌تان راضی هستید؟',
            12 => 'آیا از توجهی که شریک زندگی‌تان به نظرات شما می‌دهد راضی هستید؟',
            13 => 'آیا از نحوه مدیریت وقت مشترک‌تان راضی هستید؟',
            14 => 'آیا از تصمیماتی که با شریک زندگی‌تان می‌گیرید راضی هستید؟',
            15 => 'آیا رابطه‌تان سالم و بانشاط است؟',
            16 => 'آیا نسبت به آینده رابطه‌تان امیدوار هستید؟',
        ];
    }

    public function get_schema_labels(): array {
        return []; // CSI تک‌بُعدی است
    }

    public function get_domain_labels(): array {
        return [];
    }

    /**
     * منبع واحد نقاط برش CSI — هم برای برچسب شدت و هم برای فلگ boolean «راضی/ناراضی»
     * و هم برای جدول تفسیر در render_report() از همین آرایه استفاده می‌شود تا
     * عدد مرز فقط یک‌بار، در یک جا، نوشته شده باشد (ایندکس ۱ = مرز ۴۴.۴/۴۴.۵).
     */
    private function severity_levels(): array {
        return [
            // فیکس: نقاط برش قبلی (۳۰/۵۱.۴/۶۲/۷۲/۷۳) بر اساس نمرهٔ خام max=81 کالیبره شده بودند.
            // چون سقف واقعی این پیاده‌سازی ۷۰ است، نقاط برش با ضریب ۷۰/۸۱ بازمقیاس شدند
            // تا نسبت‌های اصلی Funk & Rogge (2007) حفظ شود و دستهٔ «رضایت بالا» قابل‌دستیابی بماند.
            ['max'=>25.9, 'label'=>'نارضایتی شدید',  'label_en'=>'Severely Dissatisfied', 'color'=>'#9b2c2c', 'bg'=>'#fff5f5', 'clinical'=>'بحران رضایت — مداخله فوری ضروری',         'range'=>'< 26',      'recommendation'=>'مداخله فوری ضروری — بحران رضایت'],
            ['max'=>44.4, 'label'=>'ناراضی',          'label_en'=>'Dissatisfied',          'color'=>'#c53030', 'bg'=>'#fff5f5', 'clinical'=>'زیر cut-off — زوج‌درمانی توصیه می‌شود', 'range'=>'26–44.4',   'recommendation'=>'زوج‌درمانی اکیداً توصیه می‌شود'],
            ['max'=>53.6, 'label'=>'رضایت متوسط',    'label_en'=>'Moderately Satisfied',  'color'=>'#d69e2e', 'bg'=>'#fffff0', 'clinical'=>'در حد متوسط — تقویت رابطه مفید است',    'range'=>'44.5–53.6', 'recommendation'=>'تقویت رابطه مفید خواهد بود'],
            ['max'=>62.2, 'label'=>'راضی',            'label_en'=>'Satisfied',             'color'=>'#276749', 'bg'=>'#f0fff4', 'clinical'=>'رضایت مناسب — نقاط قوت حفظ شود',       'range'=>'53.7–62.2', 'recommendation'=>'نقاط قوت را حفظ و تقویت کنید'],
            [             'label'=>'رضایت بالا',     'label_en'=>'Highly Satisfied',      'color'=>'#22543d', 'bg'=>'#f0fff4', 'clinical'=>'سطح بالای رضایت زناشویی',               'range'=>'≥ 62.3',    'recommendation'=>'سطح بالای رضایت زناشویی'],
        ];
    }

    private function get_severity( float $total ): array {
        return \PsychAssessments\Core\Scoring::severity( $total, $this->severity_levels() );
    }

    public function calculate( array $answers ): array {
        $answers = \PsychAssessments\Core\Scoring::sanitize_answers( $answers );
        $total = 0;

        // سوالات 1-3: مستقیم روی مقیاس 0-6
        for ($q = 1; $q <= 3; $q++) {
            $total += min(6, max(0, (int)($answers[$q] ?? 3)));
        }

        // سوال 4: 0-4 (ما 0-6 گرفتیم → تبدیل به 0-4)
        $q4_raw = min(6, max(0, (int)($answers[4] ?? 3)));
        $q4 = round($q4_raw * 4 / 6); // نرماله کردن به 0-4
        $total += $q4;

        // سوالات 5-16: 0-4 (ما 0-6 گرفتیم → تبدیل به 0-4)
        for ($q = 5; $q <= 16; $q++) {
            $raw = min(6, max(0, (int)($answers[$q] ?? 3)));
            $total += round($raw * 4 / 6);
        }

        return [
            'total'   => round($total, 1),
            // فیکس: حداکثر واقعی قابل‌محاسبه با این الگوریتم ۷۰ است، نه ۸۱ (نمرهٔ خام
            // اصلی Funk & Rogge). با max=81 قبلی، بالاترین دستهٔ شدت (≥۷۳) هرگز
            // قابل‌دستیابی نبود چون سقف واقعی نمره ۷۰ است.
            'max'     => 70,
            'pct'     => round($total / 70 * 100, 1),
        ];
    }

    public function render_report( array $data, object $session ): string {
        $total         = $data['total'];
        $max           = $data['max'];
        $pct           = $data['pct'];
        $levels        = $this->severity_levels();
        $sev           = \PsychAssessments\Core\Scoring::severity( $total, $levels );
        // فیکس: cut-off باید با نقاط برش بازمقیاس‌شده (max=70) هم‌خوان باشد، نه مقدار خام ۵۱.۵ (max=81 قدیم)
        // این مقدار مستقیماً از همان $levels خوانده می‌شود (ایندکس ۱ = مرز ناراضی/متوسط)
        // تا اگر مرز در $levels تغییر کند، اینجا هم خودکار هماهنگ بماند.
        $satisfied     = \PsychAssessments\Core\Scoring::is_above_level( $total, $levels, 1 );
        $cutoff_value  = \PsychAssessments\Core\Scoring::level_cutoff( $levels, 1 );
        $completed_date = $session->completed_at ? pa_to_jalali($session->completed_at, 'Y/m/d ساعت H:i') : '---';

        ob_start();
        ?>
        <!-- خلاصه -->
        <div class="psy-report-row">
            <?php
            $cards = [
                ['#e9d8fd','#553c9a','👤','نام',               $session->client_name ?? $session->patient_name ?? '---'],
                ['#ebf8ff','#2b6cb0','📞','شماره تماس',       $session->client_phone ?? $session->patient_phone ?? '---'],
                ['#f0fff4','#2f855a','✅','تاریخ تکمیل',       $completed_date],
                ['#fff5f5','#c53030','💯','نمره CSI',           $total . ' از ' . $max . ' (' . $pct . '%)'],
                [$satisfied?'#f0fff4':'#fff5f5', $satisfied?'#276749':'#c53030', $satisfied?'😊':'😟', 'وضعیت', $sev['label']],
                ['#fffff0','#d69e2e','✂️','cut-off','نقطه برش: ' . $cutoff_value . ' — ' . ($satisfied ? 'بالاتر' : 'پایین‌تر')],
            ];
            foreach ($cards as $c) {
                echo \PsychAssessments\Core\ReportHTML::summary_card($c[0],$c[1],$c[2],$c[3],$c[4]);
            }
            ?>
        </div>

        <!-- نمره کل -->
        <div class="psy-report-card--tint <?php echo esc_attr( \PsychAssessments\Core\ReportHTML::tone( (string) $sev['color'] ) ); ?>">
            <div class="psy-clinical__head">
                <div>
                    <h3 class="psy-report-hero__title psy-report-hero__title--lg">
                        💑 نمره رضایت زناشویی: <?php echo esc_html($total); ?> از <?php echo esc_html($max); ?>
                    </h3>
                    <p class="psy-report-hero__text"><?php echo esc_html($sev['clinical']); ?></p>
                </div>
                <div class="psy-report-hero__percent-wrap">
                    <div class="psy-report-hero__percent"><?php echo esc_html($pct); ?>%</div>
                    <div class="psy-report-hero__percent-label"><?php echo esc_html($sev['label_en']); ?></div>
                </div>
            </div>

            <!-- نوار پیشرفت با cut-off -->
            <div class="psy-report-track">
                <?php echo \PsychAssessments\Core\ReportHTML::progress_bar( (float) $pct, (string) $sev['color'] ); ?>
                <!-- نشانگر cut-off (بازمقیاس‌شده) — مقدار از همان $levels خوانده می‌شود -->
                <div class="psy-report-cutoff psy-cutoff--<?php echo esc_attr( (int) ( round( $cutoff_value / 70 * 100 / 5 ) * 5 ) ); ?>"></div>
                <div class="psy-report-scale">
                    <span>۰</span>
                    <span class="psy-tone--danger psy-report-bold"><span class="psy-text">cut-off: <?php echo esc_html($cutoff_value); ?></span></span>
                    <span>۷۰</span>
                </div>
            </div>
        </div>

        <!-- گیج بصری -->
        <div class="psy-report-card psy-report-card--panel psy-report-chart-panel psy-report-panel--center">
            <h3 class="psy-report-panel__title">سطح رضایت زناشویی</h3>
            <canvas id="pa-gauge-chart" class="psy-report-chart psy-report-chart--gauge"></canvas>
        </div>

        <!-- راهنمای تفسیر -->
        <div class="psy-report-panel psy-report-panel--soft">
            <h3 class="psy-report-panel__title">📚 راهنمای تفسیر علمی</h3>
            <p class="psy-report-subtle">Funk &amp; Rogge (2007) — Item Response Theory | دقیق‌ترین ابزار تک‌بُعدی رضایت زناشویی موجود</p>
            <table class="psy-report-table">
                <thead>
                    <tr>
                        <th class="psy-report-table__cell--start">نمره</th>
                        <th class="psy-report-table__cell--start">وضعیت</th>
                        <th class="psy-report-table__cell--start">توصیه</th>
                    </tr>
                </thead>
                <tbody>
                <?php // جدول تفسیر مستقیماً از $levels خونده می‌شه — دیگه اعداد مرز جای سومی هاردکد نمی‌شن ?>
                <?php foreach ( $levels as $lvl ): ?>
                <tr>
                    <td class="psy-report-table__cell--bold <?php echo esc_attr( \PsychAssessments\Core\ReportHTML::tone( (string) $lvl['color'] ) ); ?> psy-text"><?php echo esc_html($lvl['range']); ?></td>
                    <td class="psy-report-table__cell--bold <?php echo esc_attr( \PsychAssessments\Core\ReportHTML::tone( (string) $lvl['color'] ) ); ?> psy-text"><?php echo esc_html($lvl['label']); ?></td>
                    <td><?php echo esc_html($lvl['recommendation']); ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <p class="psy-report-note--sm">
                📌 CSI-16 با استفاده از نظریه سوال‌پاسخ (IRT) طراحی شده و دارای دقت اندازه‌گیری بالاتری نسبت به DAS و MSI است.
            </p>
        </div>

        <!-- منابع -->
        <div class="psy-report-refs psy-report-refs--muted">
            <b>منابع:</b><br>
            Funk, J.L. &amp; Rogge, R.D. (2007). <i>Journal of Family Psychology, 21</i>(4), 572–583.<br>
            Rogge, R.D. et al. (2017). Predicting relationship quality via multiple pathways. <i>Journal of Family Psychology.</i><br>
            <br>
            <b class="psy-tone--danger psy-text">⚠️</b> این گزارش ابزار کمک‌بالینی است و به تنهایی مبنای تشخیص رسمی نیست.
        </div>

        <script>
        (function() {
            function initCharts() {
                if (typeof Chart === 'undefined') { setTimeout(initCharts, 100); return; }
                var ctx = document.getElementById('pa-gauge-chart');
                if (!ctx) return;
                var pct = <?php echo floatval($pct); ?>;
                var color = '<?php echo esc_js($sev['color']); ?>';
                // شبیه‌سازی گیج با doughnut
                new Chart(ctx.getContext('2d'), {
                    type: 'doughnut',
                    data: {
                        datasets: [{
                            data: [pct, 100 - pct],
                            backgroundColor: [color, '#edf2f7'],
                            borderWidth: 0,
                            circumference: 180,
                            rotation: 270
                        }]
                    },
                    options: {
                        responsive: true,
                        plugins: {
                            legend: { display: false },
                            tooltip: { enabled: false }
                        },
                        cutout: '70%'
                    },
                    plugins: [{
                        id: 'centerText',
                        afterDraw: function(chart) {
                            var ctx2 = chart.ctx;
                            var cx = chart.width / 2;
                            var cy = chart.height * 0.85;
                            ctx2.save();
                            ctx2.font = 'bold 28px Tahoma';
                            ctx2.fillStyle = color;
                            ctx2.textAlign = 'center';
                            ctx2.fillText(pct + '%', cx, cy);
                            ctx2.font = '12px Tahoma';
                            ctx2.fillStyle = '#718096';
                            ctx2.fillText('<?php echo esc_js($sev['label']); ?>', cx, cy + 20);
                            ctx2.restore();
                        }
                    }]
                });
            }
            document.addEventListener('DOMContentLoaded', initCharts);
        })();
        </script>
        <?php
        return ob_get_clean();
    }
}

\PsychAssessments\Core\Registry::register( new CSI() );
