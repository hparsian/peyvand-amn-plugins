<?php
namespace PsychAssessments\Tests;
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * آزمون سازگاری زوجین — DAS-32
 *
 * مبانی علمی:
 * - Spanier, G.B. (1976). Measuring dyadic adjustment: New scales for assessing
 *   the quality of marriage and similar dyads. Journal of Marriage and the Family, 38(1), 15–28.
 * - Sharpley, C.F. & Cross, D.G. (1982). A psychometric evaluation of the Spanier DAS.
 *   Journal of Marriage and the Family, 44(3), 739–747.
 * - Panaghi, L. et al. (2010). هنجاریابی DAS در جمعیت ایرانی.
 * - نمره کل ≥ 100: سازگار | < 100: ناسازگار (Spanier, 1976)
 * - سوالات دارای مقیاس‌های متفاوت — برخی 0–5، برخی 0–4، برخی بله/خیر
 */
class DAS extends \PsychAssessments\Core\BaseTest {

    public function get_id(): string        { return 'das'; }
    public function get_category(): string      { return 'relationship'; }
    public function get_name(): string      { return 'سازگاری زوجین (DAS-32)'; }
    public function needs_chart(): bool   { return true; }  // نمودار Chart.js

    public function get_description(): string { return 'مقیاس سازگاری دوتایی اسپنیر — ۳۲ سوال، ۴ زیرمقیاس، cut-off ≥ ۱۰۰'; }
    public function get_version(): string   { return '1.0.0'; }

    /**
     * DAS دارای مقیاس‌های مختلف است — برای سادگی فرم بیمار از مقیاس ۶ درجه‌ای یکپارچه استفاده می‌کنیم
     * و نمره‌گذاری واقعی را در calculate() انجام می‌دهیم.
     * سوالات 1-15: مقیاس توافق (0=همیشه موافق تا 5=همیشه مخالف)
     * سوالات 16-22: مقیاس انسجام (0=هرگز تا 5=همیشه)
     * سوالات 23-28: مقیاس رضایت (0=همیشه خوشحال تا 5=همیشه ناخوشحال / یا معکوس)
     * سوالات 29-32: بیانیه‌ای (متفاوت)
     *
     * برای سهولت پیاده‌سازی در فرم یکپارچه: همه روی مقیاس 0-5
     */
    public function get_scale(): array {
        return [
            'min'    => 0,
            'max'    => 5,
            'labels' => [
                0 => 'هرگز / کاملاً مخالف',
                1 => 'به‌ندرت / مخالف',
                2 => 'گاهی اوقات / کمی مخالف',
                3 => 'اغلب / کمی موافق',
                4 => 'خیلی اوقات / موافق',
                5 => 'همیشه / کاملاً موافق',
            ],
        ];
    }

    private function subscales(): array {
        return [
            'consensus'   => ['questions'=>[1,2,3,4,5,6,7,8,9,10,11,12,13,14,15],
                              'label'=>'توافق دوتایی',    'label_en'=>'Dyadic Consensus',    'color'=>'#2b6cb0', 'max_raw'=>75],
            'cohesion'    => ['questions'=>[16,17,18,19,20,21,22],
                              'label'=>'انسجام دوتایی',   'label_en'=>'Dyadic Cohesion',     'color'=>'#276749', 'max_raw'=>35], // fix: 7 Qs × 5 = 35 (قبلاً 24 بود)
            'satisfaction'=> ['questions'=>[23,24,25,26,27,28,29,30,31],
                              'label'=>'رضایت دوتایی',    'label_en'=>'Dyadic Satisfaction',  'color'=>'#c05621', 'max_raw'=>45], // fix: 9 Qs × 5 = 45 (قبلاً 50 بود)
            'affection'   => ['questions'=>[32],
                              'label'=>'توافق عاطفی',     'label_en'=>'Affectional Expression','color'=>'#553c9a', 'max_raw'=>5],
        ];
    }

    public function get_questions(): array {
        return [
            // توافق دوتایی (1-15) — مقیاس: 5=همیشه موافق، 0=همیشه مخالف
            1  => 'چقدر شما و شریک زندگی‌تان درباره مدیریت مالی خانواده توافق دارید؟',
            2  => 'چقدر شما و شریک زندگی‌تان درباره تفریح و سرگرمی توافق دارید؟',
            3  => 'چقدر شما و شریک زندگی‌تان درباره مسائل مذهبی توافق دارید؟',
            4  => 'چقدر شما و شریک زندگی‌تان درباره نشان دادن محبت توافق دارید؟',
            5  => 'چقدر شما و شریک زندگی‌تان درباره دوستان توافق دارید؟',
            6  => 'چقدر شما و شریک زندگی‌تان درباره روابط جنسی توافق دارید؟',
            7  => 'چقدر شما و شریک زندگی‌تان درباره قراردادهای اجتماعی (رفتار صحیح) توافق دارید؟',
            8  => 'چقدر شما و شریک زندگی‌تان درباره فلسفه زندگی توافق دارید؟',
            9  => 'چقدر شما و شریک زندگی‌تان درباره روابط با خویشاوندان توافق دارید؟',
            10 => 'چقدر شما و شریک زندگی‌تان درباره اهداف، آرمان‌ها و چیزهای مهم توافق دارید؟',
            11 => 'چقدر شما و شریک زندگی‌تان درباره مقدار وقتی که با هم می‌گذرانید توافق دارید؟',
            12 => 'چقدر شما و شریک زندگی‌تان درباره تصمیم‌گیری‌های مهم توافق دارید؟',
            13 => 'چقدر شما و شریک زندگی‌تان درباره کارهای خانه توافق دارید؟',
            14 => 'چقدر شما و شریک زندگی‌تان درباره اوقات فراغت توافق دارید؟',
            15 => 'چقدر شما و شریک زندگی‌تان درباره تصمیمات شغلی توافق دارید؟',
            // انسجام دوتایی (16-22) — مقیاس: 5=همیشه، 0=هرگز
            16 => 'چقدر اوقات شما و شریک زندگی‌تان با هم می‌خندید؟',
            17 => 'چقدر شما و شریک زندگی‌تان با هم کارهای جالب انجام می‌دهید؟',
            18 => 'چقدر شما و شریک زندگی‌تان با هم درباره چیزها به طور آرام بحث می‌کنید؟',
            19 => 'چقدر شما و شریک زندگی‌تان با هم پروژه‌ای را با همکاری انجام می‌دهید؟',
            20 => 'چقدر شما و شریک زندگی‌تان درباره آینده رابطه‌تان صحبت می‌کنید؟',
            21 => 'چقدر شما و شریک زندگی‌تان ایده‌هایی تبادل می‌کنید؟',
            22 => 'چقدر شما و شریک زندگی‌تان با هم کتاب یا مجله می‌خوانید؟',
            // رضایت دوتایی (23-31)
            23 => 'چقدر اوقات درباره جدایی، طلاق یا رهاکردن رابطه فکر کرده‌اید؟ (معکوس)',
            24 => 'چقدر اوقات پیش آمده که آرزو کرده‌اید ازدواج نکرده بودید؟ (معکوس)',
            25 => 'چقدر رابطه‌تان بیشتر اوقات خوب پیش می‌رود؟',
            26 => 'آیا اعتماد دارید که رابطه‌تان ادامه خواهد داشت؟',
            27 => 'چقدر با شریک زندگی‌تان اختلاف نظر دارید؟ (معکوس)',
            28 => 'چقدر اوقات با شریک زندگی‌تان بر سر مسائل کار یا ازدواج دعوا می‌کنید؟ (معکوس)',
            29 => 'آیا با شریک زندگی‌تان خوشحال هستید؟',
            30 => 'آیا کارهایی هستند که دوست دارید در رابطه‌تان بهتر شوند؟',
            31 => 'در مجموع، از رابطه‌تان چقدر راضی هستید؟',
            // توافق عاطفی (32)
            32 => 'چقدر محبت و عاطفه بین شما و شریک زندگی‌تان به خوبی بیان می‌شود؟',
        ];
    }

    public function get_schema_labels(): array {
        return array_map( fn($s) => $s['label'], $this->subscales() );
    }

    public function get_domain_labels(): array {
        return [];
    }

    /** سوالاتی که باید معکوس شوند (5 - x) */
    private function reversed_items(): array {
        return [23, 24, 27, 28]; // نارضایتی → معکوس برای همسویی با جهت نمره‌گذاری
    }

    private function get_total_severity( float $total ): array {
        return \PsychAssessments\Core\Scoring::severity( $total, [
            ['max'=>70,  'label'=>'ناسازگاری شدید',   'label_en'=>'Severely Distressed',  'color'=>'#9b2c2c', 'bg'=>'#fff5f5', 'clinical'=>'بحران زناشویی جدی — مداخله فوری لازم است',                    'range'=>'< 70'],
            ['max'=>85,  'label'=>'ناسازگار',          'label_en'=>'Distressed',            'color'=>'#c53030', 'bg'=>'#fff5f5', 'clinical'=>'پایین‌تر از cut-off — درمان جدی توصیه می‌شود',              'range'=>'70–85'],
            ['max'=>99,  'label'=>'مرزی',              'label_en'=>'Borderline',            'color'=>'#c05621', 'bg'=>'#fffaf0', 'clinical'=>'در آستانه cut-off — ارزیابی دقیق‌تر لازم است',               'range'=>'86–99'],
            ['max'=>114, 'label'=>'سازگار',            'label_en'=>'Adjusted',              'color'=>'#276749', 'bg'=>'#f0fff4', 'clinical'=>'بالاتر از cut-off — رابطه نسبتاً سالم',                      'range'=>'100–114'],
            [            'label'=>'سازگاری بالا',     'label_en'=>'Well-Adjusted',         'color'=>'#22543d', 'bg'=>'#f0fff4', 'clinical'=>'سطح بالایی از سازگاری زناشویی',                             'range'=>'≥ 115'],
        ]);
    }

    private function get_sub_severity( float $score, float $max, string $key ): array {
        $pct = ($max > 0) ? ($score / $max * 100) : 0;
        return \PsychAssessments\Core\Scoring::severity( $pct, [
            ['max'=>39,  'label'=>'پایین',   'label_en'=>'Low',      'color'=>'#c53030', 'bg'=>'#fff5f5', 'clinical'=>'این بُعد نیاز به توجه جدی دارد',    'range'=>'< 40%'],
            ['max'=>59,  'label'=>'متوسط',   'label_en'=>'Moderate', 'color'=>'#d69e2e', 'bg'=>'#fffff0', 'clinical'=>'این بُعد در حد متوسط است',           'range'=>'40–59%'],
            ['max'=>79,  'label'=>'خوب',     'label_en'=>'Good',     'color'=>'#276749', 'bg'=>'#f0fff4', 'clinical'=>'این بُعد در وضعیت مناسبی است',      'range'=>'60–79%'],
            [            'label'=>'عالی',    'label_en'=>'Excellent', 'color'=>'#22543d', 'bg'=>'#f0fff4', 'clinical'=>'این بُعد در سطح بالایی است',        'range'=>'≥ 80%'],
        ]);
    }

    public function calculate( array $answers ): array {
        $answers = \PsychAssessments\Core\Scoring::sanitize_answers( $answers );
        $reversed  = $this->reversed_items();
        $subscales = $this->subscales();

        $sub_scores = [];
        $grand_total = 0;

        foreach ( $subscales as $key => $def ) {
            $sum = 0;
            foreach ( $def['questions'] as $q ) {
                $raw = $answers[$q] ?? 3;
                $sum += in_array($q, $reversed) ? (5 - $raw) : $raw;
            }
            $sub_scores[$key] = $sum;
            $grand_total += $sum;
        }

        return [
            'sub_scores' => $sub_scores,
            'total'      => $grand_total,
        ];
    }

    public function render_report( array $data, object $session ): string {
        $sub_scores    = $data['sub_scores'];
        $total         = $data['total'];
        $subscales     = $this->subscales();
        $total_sev     = $this->get_total_severity( (float)$total );
        $completed_date = $session->completed_at ? pa_to_jalali($session->completed_at, 'Y/m/d ساعت H:i') : '---';
        $max_total     = 160; // fix: 75+35+45+5 = 160 (قبلاً 151 بود)

        $chart_labels = [];
        $chart_values = [];
        $chart_pcts   = [];
        $chart_colors = [];
        foreach ($subscales as $key => $def) {
            $chart_labels[] = $def['label'];
            $chart_values[] = $sub_scores[$key];
            $chart_pcts[]   = round($sub_scores[$key] / $def['max_raw'] * 100, 1);
            $chart_colors[] = $def['color'];
        }

        ob_start();
        ?>
        <!-- خلاصه -->
        <div style="display:flex;gap:12px;flex-wrap:wrap;margin-bottom:24px;">
            <?php
            $cards = [
                ['#ebf8ff','#2b6cb0','📞','شماره تماس',       $session->client_phone ?? $session->patient_phone ?? '---'],
                ['#f0fff4','#2f855a','✅','تاریخ تکمیل',       $completed_date],
                ['#fff5f5','#c53030','📊','نمره کل DAS',        $total . ' از ' . $max_total],
                [($total >= 100 ? '#f0fff4' : '#fff5f5'), ($total >= 100 ? '#276749' : '#c53030'), '🏷','وضعیت', $total_sev['label']],
                [($total >= 100 ? '#f0fff4' : '#fff5f5'), ($total >= 100 ? '#276749' : '#c53030'), '✂️','cut-off', ($total >= 100 ? 'بالاتر از ۱۰۰ — سازگار' : 'پایین‌تر از ۱۰۰ — ناسازگار')],
            ];
            foreach ($cards as $c) {
                echo \PsychAssessments\Core\ReportHTML::summary_card($c[0],$c[1],$c[2],$c[3],$c[4]);
            }
            ?>
        </div>

        <!-- نمره کل -->
        <div style="background:<?php echo esc_attr($total_sev['bg']); ?>;border:2px solid <?php echo esc_attr($total_sev['color']); ?>;border-radius:10px;padding:20px;margin-bottom:24px;">
            <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;">
                <div>
                    <h3 style="margin:0 0 6px;color:<?php echo esc_attr($total_sev['color']); ?>;font-size:17px;">
                        📊 نمره کل سازگاری زوجین: <?php echo esc_html($total); ?> از <?php echo esc_html((int)$max_total); ?>
                    </h3>
                    <p style="margin:0;font-size:13px;color:#4a5568;"><?php echo esc_html($total_sev['clinical']); ?></p>
                </div>
                <?php echo \PsychAssessments\Core\ReportHTML::score_badge((float)$total, $total_sev); ?>
            </div>
            <div style="margin-top:14px;background:#edf2f7;border-radius:4px;height:12px;overflow:hidden;">
                <div style="background:<?php echo esc_attr($total_sev['color']); ?>;height:100%;width:<?php echo \PsychAssessments\Core\Scoring::percent($total, 0, $max_total); ?>%;border-radius:4px;"></div>
            </div>
            <div style="display:flex;justify-content:space-between;font-size:10px;color:#a0aec0;margin-top:4px;">
                <span>0 (ناسازگاری شدید)</span>
                <span style="color:<?php echo esc_attr($total_sev['color']); ?>;font-weight:bold;">cut-off: 100</span>
                <span><?php echo esc_html((int)$max_total); ?> (سازگاری کامل)</span>
            </div>
        </div>

        <!-- نمودار + زیرمقیاس‌ها -->
        <div style="display:flex;flex-wrap:wrap;gap:24px;align-items:flex-start;margin-bottom:30px;">
            <div style="flex:1;min-width:280px;background:#fff;padding:20px;border-radius:10px;box-shadow:0 2px 10px rgba(0,0,0,0.08);">
                <h3 style="margin-top:0;text-align:center;color:#2d3748;font-size:15px;">نمودار ۴ زیرمقیاس DAS</h3>
                <canvas id="pa-bar-chart" style="max-height:280px;"></canvas>
            </div>
            <div style="flex:1.2;min-width:300px;">
                <h3 style="margin-top:0;font-size:15px;">زیرمقیاس‌ها</h3>
                <?php foreach ($subscales as $key => $def):
                    $raw  = $sub_scores[$key];
                    $pct  = round($raw / $def['max_raw'] * 100);
                    $sev  = $this->get_sub_severity($raw, $def['max_raw'], $key);
                ?>
                <div style="background:#fff;border:1px solid #e2e8f0;border-radius:8px;padding:12px 14px;margin-bottom:10px;border-right:4px solid <?php echo esc_attr($def['color']); ?>;">
                    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;">
                        <div>
                            <span style="font-size:14px;font-weight:bold;color:<?php echo esc_attr($def['color']); ?>;"><?php echo esc_html($def['label']); ?></span>
                            <span style="font-size:10px;color:#a0aec0;margin-right:6px;"><?php echo esc_html($def['label_en']); ?></span>
                        </div>
                        <span style="font-size:13px;font-weight:bold;color:<?php echo esc_attr($sev['color']); ?>;"><?php echo $raw . '/' . $def['max_raw']; ?> (<?php echo $pct; ?>%)</span>
                    </div>
                    <div style="margin-top:8px;background:#edf2f7;border-radius:4px;height:8px;overflow:hidden;">
                        <div style="background:<?php echo esc_attr($def['color']); ?>;height:100%;width:<?php echo $pct; ?>%;border-radius:4px;"></div>
                    </div>
                    <div style="margin-top:6px;font-size:11px;color:<?php echo esc_attr($sev['color']); ?>;">💡 <?php echo esc_html($sev['clinical']); ?></div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- راهنمای تفسیر -->
        <div style="background:#f7fafc;border:1px solid #e2e8f0;border-radius:10px;padding:20px;margin-bottom:30px;">
            <h3 style="margin-top:0;font-size:15px;">📚 راهنمای تفسیر علمی</h3>
            <p style="font-size:12px;color:#718096;margin-top:0;">Spanier (1976) | Panaghi et al. (2010)</p>
            <table style="width:100%;border-collapse:collapse;font-size:12px;">
                <thead><tr style="background:#edf2f7;">
                    <th style="text-align:right;padding:8px;border:1px solid #e2e8f0;">نمره کل</th>
                    <th style="text-align:right;padding:8px;border:1px solid #e2e8f0;">وضعیت</th>
                    <th style="text-align:right;padding:8px;border:1px solid #e2e8f0;">توصیه</th>
                </tr></thead>
                <tbody>
                <?php foreach ([
                    ['< 70',    'ناسازگاری شدید', '#9b2c2c', 'مداخله فوری — بحران زناشویی'],
                    ['70–85',   'ناسازگار',        '#c53030', 'زوج‌درمانی ضروری است'],
                    ['86–99',   'مرزی',            '#c05621', 'ارزیابی دقیق‌تر — پیشگیری'],
                    ['100–114', 'سازگار',          '#276749', 'رابطه نسبتاً سالم'],
                    ['≥ 115',   'سازگاری بالا',   '#22543d', 'سطح بالای سازگاری زناشویی'],
                ] as $r): ?>
                <tr>
                    <td style="padding:7px 8px;border:1px solid #e2e8f0;font-weight:bold;color:<?php echo esc_attr($r[2]); ?>;"><?php echo esc_html($r[0]); ?></td>
                    <td style="padding:7px 8px;border:1px solid #e2e8f0;color:<?php echo esc_attr($r[2]); ?>;font-weight:bold;"><?php echo esc_html($r[1]); ?></td>
                    <td style="padding:7px 8px;border:1px solid #e2e8f0;color:#4a5568;"><?php echo esc_html($r[3]); ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- منابع -->
        <div style="background:#fff;border:1px solid #e2e8f0;border-radius:8px;padding:16px 20px;font-size:11px;color:#a0aec0;line-height:1.8;">
            <b style="color:#4a5568;">منابع:</b><br>
            Spanier, G.B. (1976). <i>Journal of Marriage and the Family, 38</i>(1), 15–28.<br>
            Sharpley, C.F. & Cross, D.G. (1982). <i>Journal of Marriage and the Family, 44</i>(3), 739–747.<br>
            Panaghi, L. et al. (2010). Psychometric properties of DAS — Iranian sample.<br>
            <br>
            <b style="color:#c53030;">⚠️</b> این گزارش ابزار کمک‌بالینی است و به تنهایی مبنای تشخیص رسمی نیست.
        </div>

        <script>
        (function() {
            function initCharts() {
                if (typeof Chart === 'undefined') { setTimeout(initCharts, 100); return; }
                var ctx = document.getElementById('pa-bar-chart');
                if (!ctx) return;
                new Chart(ctx.getContext('2d'), {
                    type: 'bar',
                    data: {
                        labels: <?php echo wp_json_encode($chart_labels); ?>,
                        datasets: [{
                            label: 'درصد',
                            data: <?php echo wp_json_encode($chart_pcts); ?>,
                            backgroundColor: <?php echo wp_json_encode(array_map(fn($c)=>$c.'cc',$chart_colors)); ?>,
                            borderColor: <?php echo wp_json_encode($chart_colors); ?>,
                            borderWidth: 2,
                            borderRadius: 5
                        }]
                    },
                    options: {
                        responsive: true,
                        plugins: { legend: { display: false } },
                        scales: {
                            y: { min: 0, max: 100, ticks: { stepSize: 20, callback: function(v){ return v+'%'; } } },
                            x: { ticks: { font: { family: 'Tahoma', size: 11 } } }
                        }
                    }
                });
            }
            document.addEventListener('DOMContentLoaded', initCharts);
        })();
        </script>
        <?php
        return ob_get_clean();
    }
}

\PsychAssessments\Core\Registry::register( new DAS() );
