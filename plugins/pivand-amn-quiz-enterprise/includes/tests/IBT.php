<?php
namespace PsychAssessments\Tests;
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * آزمون باورهای غیرمنطقی — IBT
 *
 * مبانی علمی:
 * - Ellis, A. (1962). Reason and Emotion in Psychotherapy. Lyle Stuart.
 * - Jones, R.G. (1968). A factored measure of Ellis' irrational belief system.
 *   Wichita State University. (پرسشنامه اصلی IBT — 100 سوال)
 * - نسخه کوتاه ۵۰ سوال، ۱۰ زیرمقیاس (هر زیرمقیاس ۵ سوال)، مقیاس ۱–۵
 * - Fulkerson, K.F. et al. (1983). Short version of IBT.
 * - Ganji, H. & Molavi, H. (1997). هنجاریابی IBT برای جمعیت ایرانی.
 * - cut-off کلی: نمره ≥ 250 = باورهای غیرمنطقی بالا (از حداکثر 250 — نسخه کوتاه ۵۰ سوال)
 */
class IBT extends \PsychAssessments\Core\BaseTest {

    public function get_id(): string        { return 'ibt'; }
    public function get_category(): string      { return 'personality'; }
    public function get_name(): string      { return 'باورهای غیرمنطقی (IBT)'; }
    public function needs_chart(): bool   { return true; }  // نمودار Chart.js

    public function get_description(): string { return 'آزمون باورهای غیرمنطقی الیس — ۵۰ سوال، ۱۰ زیرمقیاس بر اساس REBT'; }
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

    private function subscales(): array {
        return [
            'demand_approval'    => [
                'questions' => [1,2,3,4,5],
                'label'     => 'نیاز به تأیید',
                'label_en'  => 'Demand for Approval',
                'color'     => '#c53030',
                'desc'      => 'باور به اینکه انسان باید از همه تأیید و پذیرش دریافت کند و رد شدن غیرقابل تحمل است.',
                'clinical'  => 'این باور اضطراب اجتماعی و وابستگی به ارزیابی دیگران را افزایش می‌دهد.',
            ],
            'high_self_expect'   => [
                'questions' => [6,7,8,9,10],
                'label'     => 'توقع بالا از خود',
                'label_en'  => 'High Self-Expectations',
                'color'     => '#d69e2e',
                'desc'      => 'باور به اینکه انسان باید در همه زمینه‌ها کاملاً کفایت داشته باشد وگرنه بی‌ارزش است.',
                'clinical'  => 'پرفکشنیسم ناسالم و افسردگی ناشی از شکست را تقویت می‌کند.',
            ],
            'blame_proneness'    => [
                'questions' => [11,12,13,14,15],
                'label'     => 'تمایل به سرزنش',
                'label_en'  => 'Blame Proneness',
                'color'     => '#c05621',
                'desc'      => 'باور به اینکه انسان‌های بد باید به شدت مجازات و سرزنش شوند.',
                'clinical'  => 'خشم مزمن، کینه و دشمنی بین‌فردی را تغذیه می‌کند.',
            ],
            'frustration_react'  => [
                'questions' => [16,17,18,19,20],
                'label'     => 'واکنش به ناکامی',
                'label_en'  => 'Frustration Reactivity',
                'color'     => '#553c9a',
                'desc'      => 'باور به اینکه وقتی زندگی آنطور که می‌خواهیم نمی‌شود، این فاجعه است و غیرقابل تحمل.',
                'clinical'  => 'تحمل پایین ناکامی (LFT) و واکنش‌های هیجانی شدید را ایجاد می‌کند.',
            ],
            'emotional_irrespon' => [
                'questions' => [21,22,23,24,25],
                'label'     => 'بی‌مسئولیتی هیجانی',
                'label_en'  => 'Emotional Irresponsibility',
                'color'     => '#2b6cb0',
                'desc'      => 'باور به اینکه احساسات ما توسط عوامل بیرونی کنترل می‌شوند و ما قادر نیستیم آن‌ها را تغییر دهیم.',
                'clinical'  => 'درماندگی آموخته شده و اجتناب از مسئولیت هیجانی را تقویت می‌کند.',
            ],
            'anxious_overconcern'=> [
                'questions' => [26,27,28,29,30],
                'label'     => 'نگرانی اضطرابی',
                'label_en'  => 'Anxious Overconcern',
                'color'     => '#276749',
                'desc'      => 'باور به اینکه انسان باید مدام نگران خطرات احتمالی باشد.',
                'clinical'  => 'اضطراب فراگیر و نشخوار فکری را حفظ و تقویت می‌کند.',
            ],
            'problem_avoidance'  => [
                'questions' => [31,32,33,34,35],
                'label'     => 'اجتناب از مشکل',
                'label_en'  => 'Problem Avoidance',
                'color'     => '#22543d',
                'desc'      => 'باور به اینکه اجتناب از مشکلات و مسئولیت‌های زندگی آسان‌تر از مواجهه با آن‌هاست.',
                'clinical'  => 'اهمال‌کاری مزمن و اجتناب تجربی را تثبیت می‌کند.',
            ],
            'dependence'         => [
                'questions' => [36,37,38,39,40],
                'label'     => 'وابستگی',
                'label_en'  => 'Dependence',
                'color'     => '#805ad5',
                'desc'      => 'باور به اینکه انسان برای خوشبختی به دیگران وابسته است و نمی‌تواند مستقل باشد.',
                'clinical'  => 'ناامنی در روابط و سبک دلبستگی ناایمن را تقویت می‌کند.',
            ],
            'helplessness'       => [
                'questions' => [41,42,43,44,45],
                'label'     => 'درماندگی',
                'label_en'  => 'Helplessness for Change',
                'color'     => '#744210',
                'desc'      => 'باور به اینکه گذشته تعیین‌کننده حال است و تغییر غیرممکن یا خیلی سخت است.',
                'clinical'  => 'مقاومت در برابر درمان و درماندگی آموخته شده را تقویت می‌کند.',
            ],
            'perfectionism'      => [
                'questions' => [46,47,48,49,50],
                'label'     => 'کمال‌گرایی',
                'label_en'  => 'Perfectionism',
                'color'     => '#9b2c2c',
                'desc'      => 'باور به اینکه باید راه‌حل کامل برای همه مشکلات وجود داشته باشد و نیافتن آن فاجعه است.',
                'clinical'  => 'اضطراب تصمیم‌گیری و وسواس فکری را تقویت می‌کند.',
            ],
        ];
    }

    public function get_questions(): array {
        return [
            // نیاز به تأیید
            1  => 'برایم خیلی مهم است که دیگران کارهایم را تأیید کنند.',
            2  => 'اگر کسی مرا دوست نداشته باشد، احساس می‌کنم اشتباهی مرتکب شده‌ام.',
            3  => 'وقتی فکر می‌کنم کسی از من خوشش نمی‌آید، ناراحتم می‌کند.',
            4  => 'اگر نتوانم دیگران را خوشحال کنم، احساس می‌کنم شکست خورده‌ام.',
            5  => 'برایم مهم است که همه مردم از من خوش‌شان بیاید.',
            // توقع بالا از خود
            6  => 'فکر می‌کنم باید در هر کاری که انجام می‌دهم کاملاً موفق باشم.',
            7  => 'من نباید هیچ اشتباهی مرتکب شوم.',
            8  => 'اگر نتوانم به خوبی کاری را انجام دهم، بهتر است اصلاً آن را انجام ندهم.',
            9  => 'یک شخص ارزشمند باید در همه کارهایش موفق باشد.',
            10 => 'شکست در هر کاری نشانه بی‌کفایتی است.',
            // تمایل به سرزنش
            11 => 'کسانی که کار بدی انجام می‌دهند باید به شدت تنبیه شوند.',
            12 => 'وقتی کسی کار بدی می‌کند، باید از او متنفر بود.',
            13 => 'اگر کسی بدون دلیل به من آسیب بزند، حق دارم از او کینه داشته باشم.',
            14 => 'بعضی مردم واقعاً بد هستند و باید برای اعمالشان تاوان بدهند.',
            15 => 'آدم‌های فاسد اخلاقی لایق هیچ بخشایشی نیستند.',
            // واکنش به ناکامی
            16 => 'وقتی چیزی آنطور که می‌خواهم پیش نمی‌رود، وحشتناک است.',
            17 => 'وقتی ناکام می‌شوم، تحمل این احساس برایم غیرممکن است.',
            18 => 'زندگی وقتی ارزش دارد که همه چیز آنطور که می‌خواهم باشد.',
            19 => 'اگر زندگی آنطور که می‌خواهم نباشد، غیرقابل تحمل است.',
            20 => 'ناکامی در زندگی فاجعه است.',
            // بی‌مسئولیتی هیجانی
            21 => 'دیگران مسئول احساسات بد من هستند.',
            22 => 'من کنترلی بر احساساتم ندارم.',
            23 => 'احساسات من ناشی از شرایط بیرونی است و نه از افکار من.',
            24 => 'اگر کسی مرا ناراحت می‌کند، مقصر او است نه من.',
            25 => 'من نمی‌توانم کاری برای کنترل احساساتم انجام دهم.',
            // نگرانی اضطرابی
            26 => 'نگران بودن از خطرات احتمالی کمکم می‌کند از آن‌ها دور بمانم.',
            27 => 'اگر مشکلی ممکن است پیش بیاید، باید دائماً نگرانش باشم.',
            28 => 'اگر نگران نباشم، اتفاق بدی خواهد افتاد.',
            29 => 'بهتر است آدم همیشه برای بدترین‌ها آماده باشد.',
            30 => 'نگرانی درباره آینده طبیعی و ضروری است.',
            // اجتناب از مشکل
            31 => 'اجتناب از مشکلات بهتر از روبرو شدن با آن‌هاست.',
            32 => 'ترجیح می‌دهم از موقعیت‌های سخت فرار کنم تا اینکه با آن‌ها روبرو شوم.',
            33 => 'مسئولیت‌های سخت زندگی را باید تا آنجا که ممکن است به تأخیر انداخت.',
            34 => 'بهتر است از کارهایی که ممکن است شکست بخورم اجتناب کنم.',
            35 => 'راحت‌ترین راه این است که از دردسر دور بمانم.',
            // وابستگی
            36 => 'برای خوشبخت بودن به دیگران وابسته هستم.',
            37 => 'بدون کمک دیگران نمی‌توانم از پس مشکلاتم بربیایم.',
            38 => 'نمی‌توانم بدون اینکه کسی روی او حساب باز کنم، تصمیم بگیرم.',
            39 => 'به یک نفر قوی‌تر از خودم نیاز دارم که به من تکیه کنم.',
            40 => 'تنهایی برایم بسیار ترسناک است.',
            // درماندگی
            41 => 'تجربیات گذشته‌ام تعیین می‌کنند که الان چطور باشم.',
            42 => 'اگر در گذشته چیزی بر من تأثیر گذاشته، همیشه بر من تأثیر خواهد گذاشت.',
            43 => 'تغییر الگوهای رفتاری برایم تقریباً غیرممکن است.',
            44 => 'گذشته‌ام نشان می‌دهد که آینده‌ام هم همین خواهد بود.',
            45 => 'بعضی عادت‌ها آنقدر ریشه دارند که تغییرشان ممکن نیست.',
            // کمال‌گرایی
            46 => 'باید راه‌حل کاملی برای هر مشکل وجود داشته باشد.',
            47 => 'نگرانم اگر راه‌حل درستی پیدا نکنم اوضاع بدتر شود.',
            48 => 'وقتی جواب کاملی برای مشکلم پیدا نمی‌کنم، آشفته می‌شوم.',
            49 => 'هر مسئله‌ای باید یک جواب قطعی و کامل داشته باشد.',
            50 => 'اگر نتوانم بهترین تصمیم را بگیرم، ترجیح می‌دهم اصلاً تصمیم نگیرم.',
        ];
    }

    public function get_schema_labels(): array {
        return array_map( fn($s) => $s['label'], $this->subscales() );
    }

    public function get_domain_labels(): array {
        return [];
    }

    private function get_sub_severity( float $score ): array {
        // هر زیرمقیاس: 5-25
        return \PsychAssessments\Core\Scoring::severity( $score, [
            ['max'=>10,  'label'=>'پایین',      'label_en'=>'Low',      'color'=>'#276749', 'bg'=>'#f0fff4', 'clinical'=>'این باور در حد طبیعی است',                     'range'=>'5–10'],
            ['max'=>15,  'label'=>'متوسط',      'label_en'=>'Moderate', 'color'=>'#d69e2e', 'bg'=>'#fffff0', 'clinical'=>'این باور کمی فعال است — ارزیابی بیشتر مفید',   'range'=>'11–15'],
            ['max'=>20,  'label'=>'بالا',       'label_en'=>'High',     'color'=>'#c05621', 'bg'=>'#fffaf0', 'clinical'=>'این باور غیرمنطقی فعال — هدف درمانی',          'range'=>'16–20'],
            [            'label'=>'خیلی بالا', 'label_en'=>'Very High', 'color'=>'#9b2c2c', 'bg'=>'#fff5f5', 'clinical'=>'باور غیرمنطقی شدید — اولویت اول در درمان',    'range'=>'21–25'],
        ]);
    }

    private function get_total_severity( float $total ): array {
        // کل: 50-250
        return \PsychAssessments\Core\Scoring::severity( $total, [
            ['max'=>100, 'label'=>'پایین',          'label_en'=>'Low',      'color'=>'#276749', 'bg'=>'#f0fff4', 'clinical'=>'سطح کلی باورهای غیرمنطقی پایین است',           'range'=>'50–100'],
            ['max'=>150, 'label'=>'متوسط',          'label_en'=>'Moderate', 'color'=>'#d69e2e', 'bg'=>'#fffff0', 'clinical'=>'برخی باورهای غیرمنطقی فعال هستند',             'range'=>'101–150'],
            ['max'=>200, 'label'=>'بالا',           'label_en'=>'High',     'color'=>'#c05621', 'bg'=>'#fffaf0', 'clinical'=>'باورهای غیرمنطقی متعدد — REBT توصیه می‌شود', 'range'=>'151–200'],
            [            'label'=>'خیلی بالا',     'label_en'=>'Very High', 'color'=>'#9b2c2c', 'bg'=>'#fff5f5', 'clinical'=>'باورهای غیرمنطقی فراگیر — مداخله فوری',       'range'=>'201–250'],
        ]);
    }

    public function calculate( array $answers ): array {
        $answers = \PsychAssessments\Core\Scoring::sanitize_answers( $answers );
        $subscales = $this->subscales();
        $sub_scores = [];
        $total = 0;

        foreach ( $subscales as $key => $def ) {
            $sum = \PsychAssessments\Core\Scoring::subscale_sum( $def['questions'], $answers, 1, 5 ); // fix: min=1 (مقیاس ۱–۵), قبلاً min=3 بود که پاسخ‌های ۱ و ۲ را inflate می‌کرد
            $sub_scores[$key] = $sum;
            $total += $sum;
        }

        arsort($sub_scores);

        return [
            'sub_scores' => $sub_scores,
            'total'      => $total,
        ];
    }

    public function render_report( array $data, object $session ): string {
        $sub_scores    = $data['sub_scores'];
        $total         = $data['total'];
        $subscales     = $this->subscales();
        $total_sev     = $this->get_total_severity( (float)$total );
        $completed_date = $session->completed_at ? pa_to_jalali($session->completed_at, 'Y/m/d ساعت H:i') : '---';

        // ترتیب نزولی
        $sorted = [];
        foreach ($sub_scores as $key => $score) {
            $sorted[] = [
                'key'   => $key,
                'score' => $score,
                'sev'   => $this->get_sub_severity((float)$score),
            ] + $subscales[$key];
        }

        $chart_labels = array_map(fn($s) => $s['label'], $sorted);
        $chart_values = array_map(fn($s) => $s['score'], $sorted);
        $chart_colors = array_map(fn($s) => $s['color'].'cc', $sorted);
        $chart_borders= array_map(fn($s) => $s['color'], $sorted);

        ob_start();
        ?>
        <!-- خلاصه -->
        <div style="display:flex;gap:12px;flex-wrap:wrap;margin-bottom:24px;">
            <?php
            $cards = [
                ['#e9d8fd','#553c9a','👤','نام',               $session->client_name ?? $session->patient_name ?? '---'],
                ['#ebf8ff','#2b6cb0','📞','شماره تماس',    $session->client_phone ?? $session->patient_phone ?? '---'],
                ['#f0fff4','#2f855a','✅','تاریخ تکمیل',    $completed_date],
                ['#fff5f5','#c53030','🧠','نمره کل IBT',    $total . ' از ۲۵۰'],
                [$total_sev['bg'], $total_sev['color'], '🏷','سطح کلی', $total_sev['label']],
                ['#fffff0','#d69e2e','🎯','غالب‌ترین باور', $subscales[array_key_first($sub_scores)]['label']],
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
                        🧠 نمره کل باورهای غیرمنطقی: <?php echo esc_html($total); ?> از ۲۵۰
                    </h3>
                    <p style="margin:0;font-size:13px;color:#4a5568;"><?php echo esc_html($total_sev['clinical']); ?></p>
                </div>
                <?php echo \PsychAssessments\Core\ReportHTML::score_badge((float)$total, $total_sev); ?>
            </div>
            <div style="margin-top:14px;background:#edf2f7;border-radius:4px;height:12px;overflow:hidden;">
                <div style="background:<?php echo esc_attr($total_sev['color']); ?>;height:100%;width:<?php echo \PsychAssessments\Core\Scoring::percent($total,50,250); ?>%;border-radius:4px;"></div>
            </div>
        </div>

        <!-- نمودار + جزئیات -->
        <div style="background:#fff;padding:20px;border-radius:10px;box-shadow:0 2px 10px rgba(0,0,0,0.08);margin-bottom:24px;">
            <h3 style="margin-top:0;text-align:center;color:#2d3748;font-size:15px;">نمودار ۱۰ زیرمقیاس (ترتیب نزولی)</h3>
            <canvas id="pa-bar-chart" style="max-height:350px;"></canvas>
        </div>

        <!-- تفصیلی -->
        <div style="margin-bottom:30px;">
            <h3 style="font-size:15px;border-bottom:2px solid #e2e8f0;padding-bottom:8px;">🔬 تحلیل تفصیلی ۱۰ باور غیرمنطقی</h3>
            <?php foreach ($sorted as $i => $item):
                $pct = \PsychAssessments\Core\Scoring::percent($item['score'], 5, 25);
            ?>
            <div style="background:#fff;border:1px solid #e2e8f0;border-radius:8px;padding:14px 16px;margin-bottom:10px;border-right:4px solid <?php echo esc_attr($item['color']); ?>;">
                <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:8px;">
                    <div>
                        <span style="font-size:14px;font-weight:bold;color:<?php echo esc_attr($item['color']); ?>;">
                            <?php echo esc_html(($i+1) . '. ' . $item['label']); ?>
                        </span>
                        <span style="font-size:10px;color:#a0aec0;margin-right:6px;"><?php echo esc_html($item['label_en']); ?></span>
                        <?php if ($i === 0): ?><span style="background:<?php echo esc_attr($item['color']); ?>;color:#fff;font-size:10px;padding:1px 7px;border-radius:10px;margin-right:4px;">غالب</span><?php endif; ?>
                    </div>
                    <?php echo \PsychAssessments\Core\ReportHTML::score_badge((float)$item['score'], $item['sev']); ?>
                </div>
                <div style="margin:8px 0 4px;background:#edf2f7;border-radius:4px;height:8px;overflow:hidden;">
                    <div style="background:<?php echo esc_attr($item['color']); ?>;height:100%;width:<?php echo esc_attr($pct); ?>%;border-radius:4px;"></div>
                </div>
                <p style="margin:8px 0 5px;font-size:12px;color:#4a5568;line-height:1.6;"><?php echo esc_html($item['desc']); ?></p>
                <div style="background:<?php echo esc_attr($item['sev']['bg']); ?>;border-radius:5px;padding:6px 10px;font-size:11px;color:<?php echo esc_attr($item['color']); ?>;">
                    💡 <?php echo esc_html($item['clinical']); ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- راهنمای تفسیر -->
        <div style="background:#f7fafc;border:1px solid #e2e8f0;border-radius:10px;padding:20px;margin-bottom:30px;">
            <h3 style="margin-top:0;font-size:15px;">📚 راهنمای تفسیر علمی</h3>
            <p style="font-size:12px;color:#718096;margin-top:0;">Ellis (1962) | Jones (1968) | Ganji & Molavi (1997)</p>
            <table style="width:100%;border-collapse:collapse;font-size:12px;">
                <thead><tr style="background:#edf2f7;">
                    <th style="text-align:right;padding:8px;border:1px solid #e2e8f0;">نمره زیرمقیاس</th>
                    <th style="text-align:right;padding:8px;border:1px solid #e2e8f0;">سطح</th>
                    <th style="text-align:right;padding:8px;border:1px solid #e2e8f0;">اولویت درمانی</th>
                </tr></thead>
                <tbody>
                <?php foreach ([
                    ['5–10',  'پایین',       '#276749', 'باور در حد طبیعی — پایش معمول'],
                    ['11–15', 'متوسط',       '#d69e2e', 'قابل توجه — بررسی بیشتر'],
                    ['16–20', 'بالا',        '#c05621', 'هدف درمانی اولیه'],
                    ['21–25', 'خیلی بالا',  '#9b2c2c', 'اولویت اول در REBT'],
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
            Ellis, A. (1962). <i>Reason and Emotion in Psychotherapy.</i> Lyle Stuart.<br>
            Jones, R.G. (1968). <i>A factored measure of Ellis' irrational belief system.</i> Wichita State University.<br>
            Ganji, H. & Molavi, H. (1997). هنجاریابی آزمون باورهای غیرمنطقی برای جمعیت ایرانی.<br>
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
                        indexAxis: 'y',
                        responsive: true,
                        plugins: { legend: { display: false } },
                        scales: {
                            x: { min: 5, max: 25, ticks: { stepSize: 5 } },
                            y: { ticks: { font: { family: 'Tahoma', size: 11 } } }
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

\PsychAssessments\Core\Registry::register( new IBT() );
