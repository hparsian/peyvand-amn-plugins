<?php
namespace PsychAssessments\Tests;
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * آزمون سبک عشق — LAS (Love Attitudes Scale)
 *
 * مبانی علمی:
 * - Lee, J.A. (1973). The Colors of Love. New Press.
 * - Hendrick, C., & Hendrick, S. (1986). A theory and method of love.
 *   Journal of Personality and Social Psychology, 50(2), 392–402.
 * - Hendrick, C., Hendrick, S., & Dicke, A. (1998). The Love Attitudes Scale: Short Form.
 *   Journal of Social and Personal Relationships, 15(1), 147–159.
 * - Neto, F. et al. (2000). Cross-cultural validation of LAS.
 * - ۴۲ سوال، ۶ سبک عشق، مقیاس ۱–۵
 */
class LAS extends \PsychAssessments\Core\BaseTest {

    public function get_id(): string        { return 'las'; }
    public function get_category(): string      { return 'relationship'; }
    public function get_name(): string      { return 'سبک عشق (LAS)'; }
    public function needs_chart(): bool   { return true; }  // نمودار Chart.js

    public function get_description(): string { return 'مقیاس نگرش‌های عشق — ۴۲ سوال، ۶ سبک: اِروس، لودوس، استورگه، پراگما، مانیا، آگاپه'; }
    public function get_version(): string   { return '1.0.0'; }

    public function get_scale(): array {
        return [
            'min'    => 1,
            'max'    => 5,
            'labels' => [
                1 => 'کاملاً مخالفم',
                2 => 'مخالفم',
                3 => 'نه موافق نه مخالف',
                4 => 'موافقم',
                5 => 'کاملاً موافقم',
            ],
        ];
    }

    private function styles(): array {
        return [
            'eros'    => ['questions'=>[1,2,3,4,5,6,7],    'label'=>'اِروس',    'label_en'=>'Eros',    'color'=>'#c53030',
                          'desc'=>'عشق شدید، آنی و بر پایه جذابیت جسمانی و هیجان عاطفی عمیق. احساس "نصیب" بودن شریک زندگی.'],
            'ludus'   => ['questions'=>[8,9,10,11,12,13,14], 'label'=>'لودوس',  'label_en'=>'Ludus',   'color'=>'#d69e2e',
                          'desc'=>'عشق به‌مثابه بازی؛ بدون تعهد عمیق، لذت از فرایند تعقیب و گریز، رابطه با چند نفر هم‌زمان.'],
            'storge'  => ['questions'=>[15,16,17,18,19,20,21],'label'=>'استورگه','label_en'=>'Storge',  'color'=>'#276749',
                          'desc'=>'عشق آرام و دوستانه که از دوستی عمیق و اشتراک رشد می‌کند؛ پایدار و مبتنی بر اعتماد.'],
            'pragma'  => ['questions'=>[22,23,24,25,26,27,28],'label'=>'پراگما', 'label_en'=>'Pragma',  'color'=>'#2b6cb0',
                          'desc'=>'عشق واقع‌بینانه و منطقی؛ انتخاب شریک بر اساس ویژگی‌های عملی و سازگاری بلندمدت.'],
            'mania'   => ['questions'=>[29,30,31,32,33,34,35],'label'=>'مانیا',  'label_en'=>'Mania',   'color'=>'#553c9a',
                          'desc'=>'عشق وسواسی و حسادت‌آمیز؛ نوسان بین اوج شادی و حضیض یأس، نیاز شدید به توجه.'],
            'agape'   => ['questions'=>[36,37,38,39,40,41,42],'label'=>'آگاپه',  'label_en'=>'Agape',   'color'=>'#c05621',
                          'desc'=>'عشق فداکارانه و بی‌قید و شرط؛ اولویت دادن به خوشبختی شریک حتی به قیمت از دست دادن خود.'],
        ];
    }

    public function get_questions(): array {
        return [
            // Eros
            1  => 'شریک زندگی‌ام دقیقاً همان چیزی است که همیشه رویایش را داشتم.',
            2  => 'شریک زندگی‌ام و من از همان لحظه اول به هم جذب شدیم.',
            3  => 'رابطه ما یکی از بهترین و عمیق‌ترین تجربیات زندگی‌ام بوده.',
            4  => 'احساس می‌کنم شریک زندگی‌ام و من برای هم ساخته شده‌ایم.',
            5  => 'شریک زندگی‌ام مثل کسی است که در رویاهایم تصویرش را می‌ساختم.',
            6  => 'از همان ابتدا احساس کردم که عشق ما منحصربه‌فرد است.',
            7  => 'عشق ما خودبه‌خود و ناگهانی رشد کرد.',
            // Ludus
            8  => 'معتقدم چیز خوبی است که یکی را در بند بگذاری و بعد آزادش کنی.',
            9  => 'با چند نفر هم‌زمان می‌توانم رابطه داشته باشم.',
            10 => 'معمولاً خودم را درگیر شریک زندگی‌ام نمی‌کنم.',
            11 => 'فکر می‌کنم ماهیت عشق همان بازی است.',
            12 => 'از عشق‌بازی لذت می‌برم ولی نمی‌خواهم تعهد جدی داشته باشم.',
            13 => 'می‌توانم تأثیرات یک رابطه از دست رفته را سریع فراموش کنم.',
            14 => 'وقتی در حال عاشق شدن هستم می‌دانم چگونه کارم را پیش ببرم.',
            // Storge
            15 => 'بهترین نوع عشقی که می‌توان داشت از یک دوستی طولانی رشد می‌کند.',
            16 => 'دوستی عمیق می‌تواند به عشق واقعی تبدیل شود.',
            17 => 'روابط بهترین وقتی شروع می‌شود که دو نفر مدتی دوست صمیمی بوده باشند.',
            18 => 'عشق ما از یک دوستی محکم ریشه گرفته است.',
            19 => 'قبل از اینکه واقعاً عاشق کسی بشوم، باید مدتی دوستش باشم.',
            20 => 'مهم‌ترین عشق‌های زندگی از دوستی‌های قدیمی رشد می‌کنند.',
            21 => 'دوستی و عشق تفاوت چندانی با هم ندارند.',
            // Pragma
            22 => 'قبل از شروع رابطه جدی، به برنامه زندگی طرف مقابل فکر می‌کنم.',
            23 => 'در انتخاب شریک زندگی معیارهای عملی مثل شغل، تحصیلات و خانواده برایم مهم است.',
            24 => 'مهم است که با کسی آشنا شوم که خانواده‌اش مورد تأیید خانواده من باشد.',
            25 => 'در اول رابطه فهرستی از خصوصیاتی که می‌خواهم در شریک زندگی‌ام داشته باشم ذهنم است.',
            26 => 'سعی می‌کنم شریکی انتخاب کنم که باورهایی شبیه به من داشته باشد.',
            27 => 'برایم مهم است که شریک زندگی‌ام در زندگی آینده‌ام جا داشته باشد.',
            28 => 'عشق بدون برنامه‌ریزی پراگماتیک دوام نمی‌آورد.',
            // Mania
            29 => 'وقتی شریک زندگی‌ام با دیگری حتی نگاه می‌کند حسادتم می‌شود.',
            30 => 'نمی‌توانم آرام باشم وقتی نگران از دست دادن رابطه‌ام هستم.',
            31 => 'وقتی شریک زندگی‌ام به من توجه نمی‌کند واقعاً بیمار می‌شوم.',
            32 => 'نگران این هستم که شریک زندگی‌ام با دیگری باشد.',
            33 => 'اگر احساس کنم شریک زندگی‌ام از من فاصله می‌گیرد خیلی افسرده می‌شوم.',
            34 => 'وقتی شریک زندگی‌ام وقت کافی با من نمی‌گذراند ناراحت می‌شوم.',
            35 => 'گاهی نمی‌توانم احساساتم درباره شریک زندگی‌ام را کنترل کنم.',
            // Agape
            36 => 'ترجیح می‌دهم از خودم بگذرم تا شریک زندگی‌ام موفق باشد.',
            37 => 'اگر شریک زندگی‌ام به عشق دیگری نیاز داشته باشد، ترجیح می‌دهم مانعش نشوم.',
            38 => 'سعادت شریک زندگی‌ام از هر چیزی برایم مهم‌تر است.',
            39 => 'معمولاً نیازهای شریک زندگی‌ام را قبل از نیازهای خودم در نظر می‌گیرم.',
            40 => 'همه چیزم را فدای شریک زندگی‌ام می‌کنم اگر لازم باشد.',
            41 => 'وقتی به شریک زندگی‌ام عشق می‌ورزم احساس مسئولیت عمیقی نسبت به رفاهش دارم.',
            42 => 'اگر شریک زندگی‌ام با من رابطه نداشت، می‌توانستم به راحتی زندگی کنم ولی نمی‌توانم ببینم که خوشبخت نباشد.',
        ];
    }

    public function get_schema_labels(): array {
        return array_map( fn($s) => $s['label'], $this->styles() );
    }

    public function get_domain_labels(): array {
        return [];
    }

    private function get_severity( float $score ): array {
        return \PsychAssessments\Core\Scoring::severity( $score, [
            ['max'=>1.99, 'label'=>'خیلی پایین', 'label_en'=>'Very Low',  'color'=>'#a0aec0', 'bg'=>'#f7fafc', 'clinical'=>'این سبک در رابطه شما نقش اندکی دارد',          'range'=>'< 2.0'],
            ['max'=>2.99, 'label'=>'پایین',       'label_en'=>'Low',       'color'=>'#718096', 'bg'=>'#f7fafc', 'clinical'=>'این سبک کمتر غالب است',                        'range'=>'2.0–2.9'],
            ['max'=>3.49, 'label'=>'متوسط',       'label_en'=>'Moderate',  'color'=>'#d69e2e', 'bg'=>'#fffff0', 'clinical'=>'این سبک در حد متوسط وجود دارد',               'range'=>'3.0–3.49'],
            ['max'=>3.99, 'label'=>'بالا',        'label_en'=>'High',      'color'=>'#c05621', 'bg'=>'#fffaf0', 'clinical'=>'این سبک نقش مهمی در رابطه شما دارد',          'range'=>'3.5–3.99'],
            [             'label'=>'خیلی بالا',  'label_en'=>'Very High',  'color'=>'#9b2c2c', 'bg'=>'#fff5f5', 'clinical'=>'این سبک به‌شدت غالب است — مرکز اصلی توجه',   'range'=>'≥ 4.0'],
        ]);
    }

    public function calculate( array $answers ): array {
        $answers = \PsychAssessments\Core\Scoring::sanitize_answers( $answers );
        $styles = $this->styles();
        $scores = [];
        foreach ( $styles as $key => $def ) {
            $scores[$key] = \PsychAssessments\Core\Scoring::subscale_avg( $def['questions'], $answers, 1, 5 ); // باگ #LAS-1: min=1, max=5 (مقیاس ۱–۵)
        }
        arsort($scores);
        $dominant_key = array_key_first($scores);

        return [
            'scores'       => $scores,
            'dominant'     => $dominant_key,
            'dominant_label' => $styles[$dominant_key]['label'],
        ];
    }

    public function render_report( array $data, object $session ): string {
        $scores        = $data['scores'];
        $dominant      = $data['dominant'];
        $styles        = $this->styles();
        $dom_style     = $styles[$dominant];
        $dom_sev       = $this->get_severity($scores[$dominant]);
        $completed_date = $session->completed_at ? pa_to_jalali($session->completed_at, 'Y/m/d ساعت H:i') : '---';

        // ترتیب نزولی
        $sorted = [];
        foreach ($styles as $key => $def) {
            $sorted[] = ['key'=>$key, 'label'=>$def['label'], 'label_en'=>$def['label_en'],
                         'score'=>$scores[$key], 'color'=>$def['color'], 'desc'=>$def['desc'],
                         'sev'=>$this->get_severity($scores[$key])];
        }
        usort($sorted, fn($a,$b) => $b['score'] <=> $a['score']);

        $chart_labels = array_column($sorted, 'label');
        $chart_values = array_column($sorted, 'score');
        $chart_colors = array_map(fn($s) => $s['color'] . 'cc', $sorted);
        $chart_borders = array_column($sorted, 'color');

        ob_start();
        ?>
        <!-- خلاصه -->
        <div style="display:flex;gap:12px;flex-wrap:wrap;margin-bottom:24px;">
            <?php
            $cards = [
                ['#e9d8fd','#553c9a','👤','نام',               $session->client_name ?? $session->patient_name ?? '---'],
                ['#ebf8ff','#2b6cb0','📞','شماره تماس',   $session->client_phone ?? $session->patient_phone ?? '---'],
                ['#f0fff4','#2f855a','✅','تاریخ تکمیل',   $completed_date],
                ['#fff5f5','#c53030','❤️','سبک غالب',      $dom_style['label'] . ' (' . $dom_style['label_en'] . ')'],
                ['#fffff0','#d69e2e','📊','نمره سبک غالب', $scores[$dominant] . ' از ۵'],
            ];
            foreach ($cards as $c) {
                echo \PsychAssessments\Core\ReportHTML::summary_card($c[0],$c[1],$c[2],$c[3],$c[4]);
            }
            ?>
        </div>

        <!-- سبک غالب -->
        <div style="background:<?php echo esc_attr($dom_sev['bg']); ?>;border:2px solid <?php echo esc_attr($dom_style['color']); ?>;border-radius:10px;padding:20px;margin-bottom:24px;">
            <h3 style="margin-top:0;color:<?php echo esc_attr($dom_style['color']); ?>;font-size:17px;">
                ❤️ سبک عشق غالب: <?php echo esc_html($dom_style['label']); ?> (<?php echo esc_html($dom_style['label_en']); ?>)
            </h3>
            <p style="font-size:13px;color:#4a5568;line-height:1.8;margin-bottom:0;"><?php echo esc_html($dom_style['desc']); ?></p>
        </div>

        <!-- نمودار -->
        <div style="background:#fff;padding:20px;border-radius:10px;box-shadow:0 2px 10px rgba(0,0,0,0.08);margin-bottom:24px;">
            <h3 style="margin-top:0;text-align:center;color:#2d3748;font-size:15px;">نمودار ۶ سبک عشق</h3>
            <canvas id="pa-bar-chart" style="max-height:320px;"></canvas>
        </div>

        <!-- جزئیات هر سبک -->
        <div style="margin-bottom:30px;">
            <h3 style="font-size:15px;border-bottom:2px solid #e2e8f0;padding-bottom:8px;">🔬 تحلیل تفصیلی ۶ سبک</h3>
            <?php foreach ($sorted as $i => $item):
                $pct = \PsychAssessments\Core\Scoring::percent($item['score'], 1, 5);
            ?>
            <div style="background:#fff;border:1px solid #e2e8f0;border-radius:8px;padding:14px 16px;margin-bottom:10px;border-right:4px solid <?php echo esc_attr($item['color']); ?>;">
                <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;">
                    <div>
                        <span style="font-size:15px;font-weight:bold;color:<?php echo esc_attr($item['color']); ?>;"><?php echo esc_html(($i+1) . '. ' . $item['label']); ?></span>
                        <span style="font-size:11px;color:#a0aec0;margin-right:8px;"><?php echo esc_html($item['label_en']); ?></span>
                        <?php if ($i === 0): ?><span style="background:<?php echo esc_attr($item['color']); ?>;color:#fff;font-size:10px;padding:2px 8px;border-radius:10px;margin-right:6px;">غالب</span><?php endif; ?>
                    </div>
                    <?php echo \PsychAssessments\Core\ReportHTML::score_badge($item['score'], $item['sev']); ?>
                </div>
                <div style="margin:10px 0 6px;background:#edf2f7;border-radius:4px;height:8px;overflow:hidden;">
                    <div style="background:<?php echo esc_attr($item['color']); ?>;height:100%;width:<?php echo esc_attr($pct); ?>%;border-radius:4px;"></div>
                </div>
                <p style="margin:8px 0 6px;font-size:12px;color:#4a5568;line-height:1.6;"><?php echo esc_html($item['desc']); ?></p>
                <div style="background:<?php echo esc_attr($item['sev']['bg']); ?>;border-radius:5px;padding:6px 10px;font-size:11px;color:<?php echo esc_attr($item['color']); ?>;">
                    💡 <?php echo esc_html($item['sev']['clinical']); ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- راهنمای تفسیر -->
        <div style="background:#f7fafc;border:1px solid #e2e8f0;border-radius:10px;padding:20px;margin-bottom:30px;">
            <h3 style="margin-top:0;font-size:15px;">📚 توضیح ۶ سبک عشق (Lee, 1973)</h3>
            <p style="font-size:12px;color:#718096;margin-top:0;">Hendrick & Hendrick (1986) | Hendrick et al. (1998) | Neto et al. (2000)</p>
            <table style="width:100%;border-collapse:collapse;font-size:12px;">
                <thead><tr style="background:#edf2f7;">
                    <th style="text-align:right;padding:8px;border:1px solid #e2e8f0;">سبک</th>
                    <th style="text-align:right;padding:8px;border:1px solid #e2e8f0;">ویژگی اصلی</th>
                    <th style="text-align:right;padding:8px;border:1px solid #e2e8f0;">چالش</th>
                </tr></thead>
                <tbody>
                <?php foreach ([
                    ['اِروس',    '#c53030', 'جذابیت فوری، عشق شدید و آنی',          'وابستگی عاطفی شدید، ترس از فقدان'],
                    ['لودوس',   '#d69e2e', 'بازیگوشی، بدون تعهد عمیق',             'دشواری در تعهد بلندمدت'],
                    ['استورگه', '#276749', 'دوستی، آرامش، رشد تدریجی',              'کندی در ابراز عاطفی'],
                    ['پراگما',  '#2b6cb0', 'واقع‌بینی، معیارهای عملی',              'کاهش خودانگیختگی عاطفی'],
                    ['مانیا',   '#553c9a', 'شدت هیجانی، وسواس، حسادت',             'ناپایداری عاطفی، اضطراب'],
                    ['آگاپه',   '#c05621', 'فداکاری بی‌قیدوشرط، ازخودگذشتگی',     'غفلت از نیازهای شخصی'],
                ] as $r): ?>
                <tr>
                    <td style="padding:7px 8px;border:1px solid #e2e8f0;font-weight:bold;color:<?php echo esc_attr($r[1]); ?>;"><?php echo esc_html($r[0]); ?></td>
                    <td style="padding:7px 8px;border:1px solid #e2e8f0;color:#4a5568;"><?php echo esc_html($r[2]); ?></td>
                    <td style="padding:7px 8px;border:1px solid #e2e8f0;color:#718096;"><?php echo esc_html($r[3]); ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- منابع -->
        <div style="background:#fff;border:1px solid #e2e8f0;border-radius:8px;padding:16px 20px;font-size:11px;color:#a0aec0;line-height:1.8;">
            <b style="color:#4a5568;">منابع:</b><br>
            Lee, J.A. (1973). <i>The Colors of Love.</i> New Press.<br>
            Hendrick, C., & Hendrick, S. (1986). <i>Journal of Personality and Social Psychology, 50</i>(2), 392–402.<br>
            Hendrick, C., Hendrick, S., & Dicke, A. (1998). <i>Journal of Social and Personal Relationships, 15</i>(1), 147–159.<br>
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
                            label: 'نمره',
                            data: <?php echo wp_json_encode($chart_values); ?>,
                            backgroundColor: <?php echo wp_json_encode($chart_colors); ?>,
                            borderColor: <?php echo wp_json_encode($chart_borders); ?>,
                            borderWidth: 2,
                            borderRadius: 5
                        }]
                    },
                    options: {
                        responsive: true,
                        plugins: { legend: { display: false } },
                        scales: {
                            y: { min: 1, max: 5, ticks: { stepSize: 1 } },
                            x: { ticks: { font: { family: 'Tahoma', size: 12 } } }
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

\PsychAssessments\Core\Registry::register( new LAS() );
