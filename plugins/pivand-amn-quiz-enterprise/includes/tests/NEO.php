<?php
namespace PsychAssessments\Tests;
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * آزمون شخصیت — NEO-FFI (نسخه کوتاه پنج عاملی)
 *
 * مبانی علمی:
 * - Costa, P.T. & McCrae, R.R. (1992). Revised NEO Personality Inventory (NEO-PI-R)
 *   and NEO Five-Factor Inventory (NEO-FFI) professional manual. PAR.
 * - McCrae, R.R. & Costa, P.T. (2004). A contemplated revision of the NEO Five-Factor Inventory.
 *   Personality and Individual Differences, 36, 587–596.
 * - Garousi Farshi, M. et al. (2001). هنجاریابی NEO-FFI در ایران.
 * - ۶۰ سوال، ۵ عامل (هر عامل ۱۲ سوال)، مقیاس ۰–۴
 * - معکوس: ۱۲ سوال معکوس در هر عامل (نیمه معکوس)
 */
class NEO extends \PsychAssessments\Core\BaseTest {

    public function get_id(): string        { return 'neo'; }
    public function get_category(): string      { return 'personality'; }
    public function get_name(): string      { return 'شخصیت (NEO-FFI)'; }
    public function needs_chart(): bool   { return true; }  // نمودار Chart.js

    public function get_description(): string { return 'پرسشنامه پنج عاملی NEO — ۶۰ سوال، ۵ بُعد شخصیتی: روان‌رنجوری، برون‌گرایی، گشودگی، توافق‌پذیری، وظیفه‌شناسی'; }
    public function get_version(): string   { return '1.0.0'; }

    public function get_scale(): array {
        return [
            'min'    => 0,
            'max'    => 4,
            'labels' => [
                0 => 'کاملاً مخالفم',
                1 => 'مخالفم',
                2 => 'نه موافق نه مخالف',
                3 => 'موافقم',
                4 => 'کاملاً موافقم',
            ],
        ];
    }

    private function factors(): array {
        return [
            'N' => [
                'questions' => [1,6,11,16,21,26,31,36,41,46,51,56],
                // فیکس: بازبینی محتوای سوالات فارسی نشان داد فقط q36 ("احساس آرامش می‌کنم")
                // واقعاً جهت معکوس دارد. q6,16,26,46,56 جملات مستقیم علامتی هستند
                // (مثلاً "احساس افسردگی می‌کنم") و نباید معکوس شوند.
                'reversed'  => [36],
                'label'     => 'روان‌رنجوری',
                'label_en'  => 'Neuroticism',
                'color'     => '#c53030',
                'desc'      => 'تمایل به تجربه احساسات منفی مانند اضطراب، افسردگی، خشم و آسیب‌پذیری.',
                'high'      => 'حساسیت عاطفی بالا، احساسات منفی مکرر، استرس‌پذیری.',
                'low'       => 'ثبات عاطفی، آرامش، مقاومت در برابر استرس.',
            ],
            'E' => [
                'questions' => [2,7,12,17,22,27,32,37,42,47,52,57],
                // فیکس: q27 ("آدم شاد و بانشاطی هستم") مستقیم است، نه معکوس.
                'reversed'  => [7,17,37,47,57],
                'label'     => 'برون‌گرایی',
                'label_en'  => 'Extraversion',
                'color'     => '#d69e2e',
                'desc'      => 'تمایل به تعاملات اجتماعی، انرژی‌زایی از ارتباطات، خوش‌بینی و جرئت.',
                'high'      => 'اجتماعی، پرانرژی، خوش‌بین، سلطه‌گر در موقعیت‌های اجتماعی.',
                'low'       => 'درونگرا، مستقل، ترجیح آرامش و تنهایی.',
            ],
            'O' => [
                'questions' => [3,8,13,18,23,28,33,38,43,48,53,58],
                'reversed'  => [8,18,28,38,48,58],
                'label'     => 'گشودگی به تجربه',
                'label_en'  => 'Openness to Experience',
                'color'     => '#276749',
                'desc'      => 'کنجکاوی فکری، خلاقیت، تخیل و تمایل به تجربیات جدید و متنوع.',
                'high'      => 'کنجکاو، خلاق، باز به ایده‌های جدید، هنرمند.',
                'low'       => 'محافظه‌کار، عملی، ترجیح آشنایی و روتین.',
            ],
            'A' => [
                'questions' => [4,9,14,19,24,29,34,39,44,49,54,59],
                // فیکس: q4,q24 مستقیم بودند (نباید معکوس شوند)؛ q29,q49 واقعاً معکوس‌اند
                // ("فکر می‌کنند سرد و حسابگرم" / "فکر می‌کنند خودپسندم") ولی معکوس نشده بودند.
                'reversed'  => [14,29,34,44,49,54],
                'label'     => 'توافق‌پذیری',
                'label_en'  => 'Agreeableness',
                'color'     => '#2b6cb0',
                'desc'      => 'تمایل به همکاری، اعتماد، نوع‌دوستی و رقابت‌گریزی.',
                'high'      => 'مهربان، صادق، همکار، دلسوز.',
                'low'       => 'رقابتی، انتقادی، بدبین، خودمحور.',
            ],
            'C' => [
                'questions' => [5,10,15,20,25,30,35,40,45,50,55,60],
                // q10,20,30,60: عبارات منفی/بی‌نظمی → معکوس
                // q40 ("از استانداردهای بالا پیروی می‌کنم") و q50 ("هدف مشخص دارم") در ترجمه فارسی مستقیم نوشته شده‌اند — از reversed حذف شدند
                'reversed'  => [10,20,30,60],
                'label'     => 'وظیفه‌شناسی',
                'label_en'  => 'Conscientiousness',
                'color'     => '#553c9a',
                'desc'      => 'خودنظم‌دهی، هدفمندی، مسئولیت‌پذیری و پشتکار.',
                'high'      => 'منظم، قابل اعتماد، هدف‌محور، مسئولیت‌پذیر.',
                'low'       => 'انعطاف‌پذیر، خودانگیخته، گاهی بی‌دقت.',
            ],
        ];
    }

    public function get_questions(): array {
        return [
            1  => 'اغلب اوقات احساس تنش یا عصبانیت می‌کنم.',
            2  => 'از صحبت کردن با غریبه‌ها لذت می‌برم.',
            3  => 'من یک خیال‌پرداز هستم.',
            4  => 'سعی می‌کنم با همه مؤدبانه رفتار کنم.',
            5  => 'سعی می‌کنم همه وظایفم را به خوبی انجام دهم.',
            6  => 'اغلب اوقات احساس حقارت می‌کنم.',
            7  => 'ترجیح می‌دهم کارهایم را به تنهایی انجام دهم.',
            8  => 'علاقه زیادی به شعر ندارم.',
            9  => 'برای اینکه از مشکل فرار کنم، ترجیح می‌دهم با دیگران همکاری کنم.',
            10 => 'اغلب اوقات اتاق یا میز کارم به هم ریخته است.',
            11 => 'گاهی اوقات ذهنم احساس خستگی یا بی‌حالی می‌کند.',
            12 => 'دوست دارم جمعیت زیادی دورم باشد.',
            13 => 'من یک ذهن کاوشگر دارم.',
            14 => 'اگر کسی اول مهربان نباشد، من هم محبتی نشان نمی‌دهم.',
            15 => 'من یک کارگر سخت‌کوش هستم.',
            16 => 'اغلب اوقات احساس افسردگی یا غم می‌کنم.',
            17 => 'وقتی در اجتماع هستم، ترجیح می‌دهم ساکت بنشینم.',
            18 => 'فکر می‌کنم دانستن الگوهای هنری در موسیقی مهم نیست.',
            19 => 'از دیدن سالمندان و کودکان مراقبت می‌کنم.',
            20 => 'وقتی لازم است، می‌توانم نامنظم باشم.',
            21 => 'گاهی اوقات احساس ترس می‌کنم بدون اینکه دلیل واقعی‌ای داشته باشد.',
            22 => 'در پارتی‌ها اغلب با افراد جدید آشنا می‌شوم.',
            23 => 'به شعر و ادبیات علاقه زیادی دارم.',
            24 => 'اگر کسی مرا آزار دهد، ترجیح می‌دهم بخشیدن را در نظر بگیرم.',
            25 => 'من اهداف روشن و مشخصی دارم.',
            26 => 'اغلب اوقات احساس بی‌قراری می‌کنم.',
            27 => 'من یک آدم شاد و با نشاط هستم.',  // R در اجتناب
            28 => 'گوش دادن به موسیقی کلاسیک برایم جذاب نیست.',
            29 => 'بعضی مردم فکر می‌کنند من سرد و حسابگر هستم.',
            30 => 'وقتی شروع پروژه‌ای می‌کنم، همیشه آن را کامل نمی‌کنم.',
            31 => 'اغلب اوقات احساس می‌کنم همه چیز بی‌ارزش است.',
            32 => 'اغلب اوقات احساس می‌کنم مملو از انرژی هستم.',
            33 => 'به فلسفه علاقه دارم.',
            34 => 'بعضی مواقع با دیگران خشن رفتار می‌کنم.',
            35 => 'سعی می‌کنم همه چیز را در جای خودش بگذارم.',
            36 => 'اغلب اوقات احساس آرامش می‌کنم.',  // R
            37 => 'ترجیح می‌دهم مکالمات کوتاه داشته باشم.',
            38 => 'زیبایی‌شناسی برایم جذاب نیست.',
            39 => 'اگر کسی نیاز داشته باشد، آماده‌ام کمک کنم.',
            40 => 'از استانداردهای بالا پیروی می‌کنم.',  // این C مستقیم است
            41 => 'گاهی اوقات احساس بی‌ارزشی می‌کنم.',
            42 => 'من یک آدم خوش‌مشرب هستم.',
            43 => 'وقتی شعری می‌خوانم یا تصویری می‌بینم، هیجانات قوی احساس می‌کنم.',
            44 => 'در مورد انگیزه‌های دیگران بدبین هستم.',
            45 => 'وقتی وظیفه‌ای به عهده می‌گیرم، مطمئنم که آن را کامل می‌کنم.',
            46 => 'گاهی اوقات احساس می‌کنم که همه چیز بر ضد من است.',
            47 => 'از پارتی‌های پرجمعیت لذت نمی‌برم.',
            48 => 'کنجکاوی در نظریه‌های علمی برایم جذاب نیست.',
            49 => 'بعضی مردم فکر می‌کنند من خودپسند هستم.',
            50 => 'من یک هدف مشخص دارم و برای رسیدن به آن کار می‌کنم.',  // مستقیم
            51 => 'گاهی اوقات احساس می‌کنم که زندگی‌ام بی‌معنا است.',
            52 => 'دوست دارم مرکز توجه باشم.',
            53 => 'علاقه زیادی به تحلیل مسائل پیچیده دارم.',
            54 => 'بر روی رقیبانم پیروزی می‌خواهم.',
            55 => 'برای موفقیت سخت تلاش می‌کنم.',
            56 => 'اغلب اوقات احساس می‌کنم نمی‌توانم با موقعیت‌ها کنار بیایم.',
            57 => 'من یک آدم درونگرا هستم.',
            58 => 'فکر می‌کنم دانستن ارزش‌های اخلاقی مختلف اهمیتی ندارد.',
            59 => 'اغلب اوقات از همکاری با دیگران لذت می‌برم.',
            60 => 'اغلب بدون فکر کردن عمل می‌کنم.',
        ];
    }

    public function get_schema_labels(): array {
        return array_map( fn($f) => $f['label'], $this->factors() );
    }

    public function get_domain_labels(): array {
        return [];
    }

    private function get_severity( float $score ): array {
        // T-score تقریبی: میانگین NEO-FFI معمولاً T=50 (raw≈24 از 48)
        // محدوده raw: 0-48 برای هر عامل (12 سوال × 4)
        return \PsychAssessments\Core\Scoring::severity( $score, [
            ['max'=>11,  'label'=>'خیلی پایین',  'label_en'=>'Very Low',     'color'=>'#2b6cb0', 'bg'=>'#ebf8ff', 'clinical'=>'پایین‌تر از نهک دهم جمعیت',            'range'=>'0–11'],
            ['max'=>19,  'label'=>'پایین',        'label_en'=>'Low',          'color'=>'#4299e1', 'bg'=>'#ebf8ff', 'clinical'=>'پایین‌تر از میانگین (T<45)',           'range'=>'12–19'],
            ['max'=>28,  'label'=>'متوسط',        'label_en'=>'Average',      'color'=>'#d69e2e', 'bg'=>'#fffff0', 'clinical'=>'در محدوده میانگین (T≈50)',             'range'=>'20–28'],
            ['max'=>36,  'label'=>'بالا',         'label_en'=>'High',         'color'=>'#c05621', 'bg'=>'#fffaf0', 'clinical'=>'بالاتر از میانگین (T>55)',             'range'=>'29–36'],
            [            'label'=>'خیلی بالا',   'label_en'=>'Very High',    'color'=>'#9b2c2c', 'bg'=>'#fff5f5', 'clinical'=>'بالاتر از نهک نودم جمعیت (T>65)',     'range'=>'37–48'],
        ]);
    }

    public function calculate( array $answers ): array {
        $answers = \PsychAssessments\Core\Scoring::sanitize_answers( $answers );
        $factors = $this->factors();
        $scores  = [];

        foreach ( $factors as $key => $def ) {
            $sum = 0;
            foreach ( $def['questions'] as $q ) {
                $raw = min(4, max(0, (int)($answers[$q] ?? 2)));
                $sum += in_array($q, $def['reversed']) ? (4 - $raw) : $raw;
            }
            $scores[$key] = $sum; // 0-48
        }

        return ['scores' => $scores];
    }

    public function render_report( array $data, object $session ): string {
        $scores        = $data['scores'];
        $factors       = $this->factors();
        $completed_date = $session->completed_at ? pa_to_jalali($session->completed_at, 'Y/m/d ساعت H:i') : '---';

        $sorted = [];
        foreach ($factors as $key => $def) {
            $sorted[] = [
                'key'      => $key,
                'label'    => $def['label'],
                'label_en' => $def['label_en'],
                'score'    => $scores[$key],
                'color'    => $def['color'],
                'desc'     => $def['desc'],
                'high'     => $def['high'],
                'low'      => $def['low'],
                'sev'      => $this->get_severity($scores[$key]),
            ];
        }

        $chart_labels  = array_column($sorted, 'label');
        $chart_values  = array_column($sorted, 'score');
        $chart_max     = 48;
        $radar_values  = array_map(fn($s) => round($s['score']/$chart_max*100, 1), $sorted);
        $chart_colors  = array_map(fn($s) => $s['color'].'cc', $sorted);
        $chart_borders = array_column($sorted, 'color');

        ob_start();
        ?>
        <!-- خلاصه -->
        <div style="display:flex;gap:12px;flex-wrap:wrap;margin-bottom:24px;">
            <?php
            $cards = [
                ['#ebf8ff','#2b6cb0','📞','شماره تماس',  $session->client_phone ?? $session->patient_phone ?? '---'],
                ['#f0fff4','#2f855a','✅','تاریخ تکمیل',  $completed_date],
                ['#f3e8ff','#553c9a','🧠','پنج عامل',     'NEO-FFI — ۶۰ سوال'],
                ['#fffff0','#d69e2e','📊','محدوده نمره',  'هر عامل: ۰–۴۸'],
            ];
            foreach ($cards as $c) {
                echo \PsychAssessments\Core\ReportHTML::summary_card($c[0],$c[1],$c[2],$c[3],$c[4]);
            }
            ?>
        </div>

        <!-- نمودار راداری -->
        <div style="display:flex;flex-wrap:wrap;gap:24px;align-items:flex-start;margin-bottom:30px;">
            <div style="flex:1;min-width:300px;background:#fff;padding:20px;border-radius:10px;box-shadow:0 2px 10px rgba(0,0,0,0.08);">
                <h3 style="margin-top:0;text-align:center;color:#2d3748;font-size:15px;">نمودار پنج عامل شخصیت</h3>
                <canvas id="pa-radar-chart" style="max-height:320px;"></canvas>
            </div>
            <div style="flex:1;min-width:280px;">
                <h3 style="margin-top:0;font-size:15px;">نمرات خام و سطح</h3>
                <?php foreach ($sorted as $item):
                    $pct = round($item['score']/$chart_max*100);
                ?>
                <div style="background:#fff;border:1px solid #e2e8f0;border-radius:8px;padding:12px 14px;margin-bottom:10px;border-right:4px solid <?php echo esc_attr($item['color']); ?>;">
                    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:6px;">
                        <div>
                            <span style="font-weight:bold;color:<?php echo esc_attr($item['color']); ?>;font-size:13px;"><?php echo esc_html($item['label']); ?></span>
                            <span style="font-size:10px;color:#a0aec0;margin-right:4px;"><?php echo esc_html($item['label_en']); ?></span>
                        </div>
                        <span style="font-weight:bold;font-size:14px;color:<?php echo esc_attr($item['sev']['color']); ?>;">
                            <?php echo esc_html((int)$item['score']); ?>/48
                            <span style="font-size:11px;">(<?php echo esc_html($item['sev']['label']); ?>)</span>
                        </span>
                    </div>
                    <div style="margin-top:6px;background:#edf2f7;border-radius:4px;height:8px;overflow:hidden;">
                        <div style="background:<?php echo esc_attr($item['color']); ?>;height:100%;width:<?php echo (int)$pct; ?>%;border-radius:4px;"></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- تحلیل تفصیلی -->
        <div style="margin-bottom:30px;">
            <h3 style="font-size:15px;border-bottom:2px solid #e2e8f0;padding-bottom:8px;">🔬 تفسیر بالینی هر عامل</h3>
            <?php foreach ($sorted as $item):
                $pct = round($item['score']/$chart_max*100);
                $is_high = $item['score'] >= 29;
            ?>
            <div style="background:#fff;border:1px solid #e2e8f0;border-radius:8px;padding:14px 16px;margin-bottom:12px;border-right:4px solid <?php echo esc_attr($item['color']); ?>;">
                <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:8px;">
                    <div>
                        <span style="font-size:16px;font-weight:bold;color:<?php echo esc_attr($item['color']); ?>;"><?php echo esc_html($item['label']); ?></span>
                        <span style="font-size:12px;color:#718096;margin-right:6px;"><?php echo esc_html($item['label_en']); ?></span>
                    </div>
                    <?php echo \PsychAssessments\Core\ReportHTML::score_badge((float)$item['score'], $item['sev']); ?>
                </div>
                <div style="margin:10px 0 6px;background:#edf2f7;border-radius:4px;height:10px;overflow:hidden;">
                    <div style="background:<?php echo esc_attr($item['color']); ?>;height:100%;width:<?php echo (int)$pct; ?>%;border-radius:4px;"></div>
                </div>
                <p style="margin:8px 0 6px;font-size:12px;color:#4a5568;line-height:1.7;"><?php echo esc_html($item['desc']); ?></p>
                <div style="display:flex;gap:8px;flex-wrap:wrap;">
                    <div style="flex:1;min-width:200px;background:#f0fff4;border-radius:5px;padding:8px 10px;font-size:11px;color:#276749;">
                        ⬇ <b>نمره پایین:</b> <?php echo esc_html($item['low']); ?>
                    </div>
                    <div style="flex:1;min-width:200px;background:<?php echo esc_attr($item['sev']['bg']); ?>;border-radius:5px;padding:8px 10px;font-size:11px;color:<?php echo esc_attr($item['color']); ?>;">
                        ⬆ <b>نمره بالا:</b> <?php echo esc_html($item['high']); ?>
                    </div>
                </div>
                <div style="margin-top:8px;background:#f7fafc;border-radius:5px;padding:7px 10px;font-size:11px;color:#718096;">
                    💡 <?php echo esc_html($item['sev']['clinical']); ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- راهنمای تفسیر -->
        <div style="background:#f7fafc;border:1px solid #e2e8f0;border-radius:10px;padding:20px;margin-bottom:30px;">
            <h3 style="margin-top:0;font-size:15px;">📚 راهنمای تفسیر</h3>
            <p style="font-size:12px;color:#718096;margin-top:0;">Costa & McCrae (1992) | Garousi Farshi et al. (2001)</p>
            <table style="width:100%;border-collapse:collapse;font-size:12px;">
                <thead><tr style="background:#edf2f7;">
                    <th style="text-align:right;padding:8px;border:1px solid #e2e8f0;">نمره خام</th>
                    <th style="text-align:right;padding:8px;border:1px solid #e2e8f0;">سطح</th>
                    <th style="text-align:right;padding:8px;border:1px solid #e2e8f0;">T-score تقریبی</th>
                </tr></thead>
                <tbody>
                <?php foreach ([
                    ['0–11',  'خیلی پایین', '#2b6cb0', 'T < 35'],
                    ['12–19', 'پایین',      '#4299e1', 'T = 35–44'],
                    ['20–28', 'متوسط',      '#d69e2e', 'T = 45–54'],
                    ['29–36', 'بالا',       '#c05621', 'T = 55–64'],
                    ['37–48', 'خیلی بالا', '#9b2c2c', 'T ≥ 65'],
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
            Costa, P.T. & McCrae, R.R. (1992). <i>NEO-PI-R and NEO-FFI professional manual.</i> Psychological Assessment Resources.<br>
            McCrae, R.R. & Costa, P.T. (2004). <i>Personality and Individual Differences, 36,</i> 587–596.<br>
            Garousi Farshi, M. et al. (2001). هنجاریابی NEO-FFI در جمعیت ایرانی.<br>
            <br>
            <b style="color:#c53030;">⚠️</b> این گزارش ابزار کمک‌بالینی است و به تنهایی مبنای تشخیص رسمی نیست.
        </div>

        <script>
        (function() {
            function initCharts() {
                if (typeof Chart === 'undefined') { setTimeout(initCharts, 100); return; }
                var ctx = document.getElementById('pa-radar-chart');
                if (!ctx) return;
                new Chart(ctx.getContext('2d'), {
                    type: 'radar',
                    data: {
                        labels: <?php echo wp_json_encode($chart_labels); ?>,
                        datasets: [{
                            label: 'درصد',
                            data: <?php echo wp_json_encode($radar_values); ?>,
                            backgroundColor: 'rgba(99,102,241,0.15)',
                            borderColor: '#6366f1',
                            borderWidth: 2,
                            pointBackgroundColor: <?php echo wp_json_encode($chart_borders); ?>,
                            pointRadius: 6
                        }]
                    },
                    options: {
                        responsive: true,
                        scales: { r: {
                            min: 0, max: 100,
                            ticks: { stepSize: 25, callback: function(v){ return v+'%'; } },
                            pointLabels: { font: { family: 'Tahoma', size: 11 } }
                        }},
                        plugins: { legend: { display: false } }
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

\PsychAssessments\Core\Registry::register( new NEO() );
