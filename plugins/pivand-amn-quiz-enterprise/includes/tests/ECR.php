<?php
namespace PsychAssessments\Tests;
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * آزمون سبک دلبستگی — ECR-R
 *
 * مبانی علمی:
 * - Fraley, R.C., Waller, N.G., & Brennan, K.A. (2000). An item response theory analysis of
 *   self-report measures of adult attachment. Journal of Personality and Social Psychology, 78(2), 350–365.
 * - Brennan, K.A., Clark, C.L., & Shaver, P.R. (1998). Self-report measurement of adult romantic attachment.
 *   In J.A. Simpson & W.S. Rholes (Eds.), Attachment Theory and Close Relationships (pp. 46–76). Guilford Press.
 * - Besharat, M.A. (2011). Reliability and validity of the ECR-R in an Iranian sample.
 * - مقیاس ۱–۷ (۱=کاملاً مخالفم، ۷=کاملاً موافقم)
 * - اجتناب: سوالات فرد  (1,3,5,7,9,11,13,15,17,19,21,23,25,27,29,31,33,35)
 *   reversed: q33 (آیتم مثبت → معکوس = راحتی با حمایت)
 * - اضطراب: سوالات زوج (2,4,6,8,10,12,14,16,18,20,22,24,26,28,30,32,34,36)
 *   reversed: q32 (آیتم معکوس اضطراب: "شریک بیشتر از آنچه راحتم می‌خواهد نزدیک باشد")
 * - cut-off اضطراب ≥ 3.50 | اجتناب ≥ 3.50 (بر اساس Fraley et al. 2000)
 */
class ECR extends \PsychAssessments\Core\BaseTest {

    public function get_id(): string        { return 'ecr'; }
    public function get_category(): string      { return 'relationship'; }
    public function get_name(): string      { return 'سبک دلبستگی (ECR-R)'; }
    public function needs_chart(): bool   { return true; }  // نمودار Chart.js

    public function get_description(): string { return 'پرسشنامه تجربیات در روابط نزدیک — نسخه اصلاح‌شده — ۳۶ سوال، ۲ زیرمقیاس اضطراب و اجتناب'; }
    public function get_version(): string   { return '1.0.0'; }

    public function get_scale(): array {
        return [
            'min'    => 1,
            'max'    => 7,
            'labels' => [
                1 => 'کاملاً مخالفم',
                2 => 'مخالفم',
                3 => 'کمی مخالفم',
                4 => 'نه موافق نه مخالف',
                5 => 'کمی موافقم',
                6 => 'موافقم',
                7 => 'کاملاً موافقم',
            ],
        ];
    }

    /** سوالات معکوس بر اساس Fraley et al. (2000) — اصلاح‌شده */
    private function reversed_anxiety(): array   { return [32]; }  // q32: "شریک می‌خواهد بیشتر از آنچه من راحتم نزدیک باشد" → reversed
    private function reversed_avoidance(): array { return [33]; }  // q33: "دشوار است اجازه بدهم حمایت کند" → reversed (نشانه راحتی با نزدیکی)

    public function get_questions(): array {
        return [
            1  => 'ترجیح می‌دهم احساساتم را با شریک زندگی‌ام در میان نگذارم.',
            2  => 'می‌ترسم شریک زندگی‌ام مرا رها کند.',
            3  => 'وقتی شریک زندگی‌ام سعی می‌کند خیلی به من نزدیک شود، احساس ناراحتی می‌کنم.',
            4  => 'نگران این هستم که شریک زندگی‌ام واقعاً به من اهمیت نمی‌دهد.',
            5  => 'ترجیح می‌دهم به شریک زندگی‌ام وابسته نشوم.',
            6  => 'نگران این هستم که شریک زندگی‌ام به اندازه کافی به من اهمیت نمی‌دهد.',
            7  => 'وقتی شریک زندگی‌ام می‌خواهد خیلی نزدیک باشد، احساس ناراحتی می‌کنم.',
            8  => 'نگران این هستم که مبادا شریک زندگی‌ام مرا دوست نداشته باشد.',
            9  => 'دوست ندارم احساسات عمیقم را با شریک زندگی‌ام در میان بگذارم.',
            10 => 'اغلب نگرانم که شریک زندگی‌ام واقعاً نمی‌خواهد با من بماند.',
            11 => 'می‌خواهم از نزدیکی با شریک زندگی‌ام اجتناب کنم.',
            12 => 'گاهی احساس می‌کنم که شریک زندگی‌ام نمی‌خواهد آن‌قدر که من می‌خواهم نزدیک باشیم.',
            13 => 'وقتی شریک زندگی‌ام می‌خواهد خیلی صمیمی باشد، ناراحت می‌شوم.',
            14 => 'نگران این هستم که تنها باشم.',
            15 => 'برایم راحت است که احساسات و افکارم را با شریک زندگی‌ام در میان نگذارم.',
            16 => 'می‌خواهم که شریک زندگی‌ام احساساتش را با من در میان بگذارد.',
            17 => 'سعی می‌کنم از نزدیک شدن بیش از حد به شریک زندگی‌ام اجتناب کنم.',
            18 => 'به طور مکرر نگران این هستم که شریک زندگی‌ام مرا دوست نداشته باشد.',
            19 => 'معمولاً درباره مشکلاتم و نگرانی‌هایم با شریک زندگی‌ام صحبت نمی‌کنم.',
            20 => 'وقتی شریک زندگی‌ام نگرانم می‌شود، احساس ناامنی می‌کنم.',
            21 => 'دوست ندارم به شریک زندگی‌ام نزدیک باشم.',
            22 => 'اغلب نگران این هستم که شریک زندگی‌ام نخواهد کنارم بماند.',
            23 => 'ترجیح می‌دهم زیاد با شریک زندگی‌ام صمیمی نباشم.',
            24 => 'اگر نتوانم شریک زندگی‌ام را متقاعد کنم که به من اهمیت بدهد، ناراحت می‌شوم.',
            25 => 'به شریک زندگی‌ام نشان می‌دهم که به او نیاز ندارم.',
            26 => 'متوجه می‌شوم که شریک زندگی‌ام نمی‌خواهد آن‌قدر که من می‌خواهم نزدیکم باشد.',
            27 => 'معمولاً درباره مسائل و مشکلاتم با شریک زندگی‌ام صحبت نمی‌کنم.',
            28 => 'وقتی شریک زندگی‌ام با من نیست، نگرانم می‌شوم.',
            29 => 'وقتی می‌خواهم با شریک زندگی‌ام احساساتم را در میان بگذارم، ناراحت می‌شوم.',
            30 => 'وقتی شریک زندگی‌ام با من نیست احساس تنهایی می‌کنم.',
            31 => 'ناراحت می‌شوم وقتی نتوانم به آن‌قدر نزدیکی که می‌خواهم با شریک زندگی‌ام برسم.',
            32 => 'گاهی احساس می‌کنم که شریک زندگی‌ام می‌خواهد بیشتر از آنچه من راحتم، نزدیک باشیم.',
            33 => 'دشوار است که به شریک زندگی‌ام اجازه بدهم مرا حمایت کند.',
            34 => 'وقتی شریک زندگی‌ام به اندازه کافی در دسترس نیست ناراحت می‌شوم.',
            35 => 'به کمک خواستن از شریک زندگی‌ام اکراه دارم.',
            36 => 'اگر شریک زندگی‌ام رنجیده شود، نگران می‌شوم.',
        ];
    }

    private function subscales(): array {
        return [
            'avoidance' => [
                'questions' => [1,3,5,7,9,11,13,15,17,19,21,23,25,27,29,31,33,35],
                'label'     => 'اجتناب دلبستگی',
                'label_en'  => 'Attachment Avoidance',
                'color'     => '#2b6cb0',
                'reversed'  => [33], // q33: آیتم مثبت — معکوس می‌شود
            ],
            'anxiety'   => [
                'questions' => [2,4,6,8,10,12,14,16,18,20,22,24,26,28,30,32,34,36],
                'label'     => 'اضطراب دلبستگی',
                'label_en'  => 'Attachment Anxiety',
                'color'     => '#c53030',
                'reversed'  => [32], // q32: آیتم معکوس اضطراب — بالا بودن نشانه اجتناب است نه اضطراب
            ],
        ];
    }

    /**
     * کلید نمره‌گذاری ECR-R — اصلاح‌شده بر اساس Fraley et al. (2000):
     * در این پیاده‌سازی: سوالات فرد = اجتناب | سوالات زوج = اضطراب
     *
     * اجتناب reversed: q33
     *   "دشوار است به شریک اجازه بدهم مرا حمایت کند" → نمره بالا = اجتناب بالا
     *   اما از نظر محتوا این آیتم نزدیکی را توصیف می‌کند → reversed
     *
     * اضطراب reversed: q32
     *   "شریک می‌خواهد بیشتر از آنچه من راحتم نزدیک باشد" → نمره بالا = اجتناب (نه اضطراب)
     *   → معکوس می‌شود تا نمره اضطراب را منعکس کند
     *
     * فرمول معکوس در مقیاس ۷ تایی: reversed_score = 8 - raw_score
     */
    /**
     * @deprecated استفاده نمی‌شود — calculate() مستقیماً از reversed_anxiety/avoidance استفاده می‌کند
     * نگه‌داشته شده برای سازگاری با ابزارهای خارجی که ممکن است به آن اشاره داشته باشند
     */
    private function reversed_items(): array {
        return [32, 33]; // q32 (anxiety sub) و q33 (avoidance sub)
    }

    public function get_schema_labels(): array {
        return [
            'anxiety'   => 'اضطراب دلبستگی',
            'avoidance' => 'اجتناب دلبستگی',
        ];
    }

    public function get_domain_labels(): array {
        return [];
    }

    private function attachment_style( float $anxiety, float $avoidance ): array {
        $hi_anx = $anxiety  >= 3.50;
        $hi_avo = $avoidance >= 3.50;
        if ( ! $hi_anx && ! $hi_avo ) {
            return ['label'=>'ایمن',              'label_en'=>'Secure',             'color'=>'#276749', 'bg'=>'#f0fff4',
                    'desc'=>'احساس راحتی در نزدیکی و استقلال؛ اعتماد به شریک زندگی بدون ترس از رهاشدگی.'];
        }
        if ( $hi_anx && ! $hi_avo ) {
            return ['label'=>'دلبسته/نگران',     'label_en'=>'Preoccupied',        'color'=>'#c53030', 'bg'=>'#fff5f5',
                    'desc'=>'نگرانی شدید درباره ارزش خود و واکنش شریک زندگی؛ نیاز به تأیید مکرر و ترس از طرد.'];
        }
        if ( ! $hi_anx && $hi_avo ) {
            return ['label'=>'اجتنابی/ناایمن',  'label_en'=>'Dismissing',         'color'=>'#2b6cb0', 'bg'=>'#ebf8ff',
                    'desc'=>'ناراحتی از نزدیکی عاطفی و وابستگی؛ تأکید بر استقلال و اتکا به خود.'];
        }
        return ['label'=>'ترسان/آشفته',         'label_en'=>'Fearful-Avoidant',   'color'=>'#553c9a', 'bg'=>'#faf5ff',
                'desc'=>'ترکیب اضطراب بالا و اجتناب بالا؛ میل به نزدیکی همراه با ترس از آسیب‌دیدن.'];
    }

    private function get_severity( float $score, string $subscale ): array {
        // هر دو زیرمقیاس از ۱–۷، cut-off = 3.50
        if ( $subscale === 'anxiety' ) {
            return \PsychAssessments\Core\Scoring::severity( $score, [
                ['max'=>2.49, 'label'=>'پایین',   'label_en'=>'Low',      'color'=>'#276749', 'bg'=>'#f0fff4', 'clinical'=>'اضطراب دلبستگی در سطح طبیعی',               'range'=>'< 2.5'],
                ['max'=>3.49, 'label'=>'متوسط',   'label_en'=>'Moderate', 'color'=>'#d69e2e', 'bg'=>'#fffff0', 'clinical'=>'اضطراب متوسط — ارزیابی بافتاری توصیه می‌شود', 'range'=>'2.5 – 3.49'],
                ['max'=>4.99, 'label'=>'بالا',    'label_en'=>'High',     'color'=>'#c05621', 'bg'=>'#fffaf0', 'clinical'=>'بالاتر از cut-off بالینی — پیگیری لازم است',  'range'=>'3.5 – 4.99'],
                [             'label'=>'بسیار بالا','label_en'=>'Very High','color'=>'#9b2c2c','bg'=>'#fff5f5', 'clinical'=>'اضطراب شدید — مداخله درمانی توصیه می‌شود',  'range'=>'≥ 5.0'],
            ]);
        } else {
            return \PsychAssessments\Core\Scoring::severity( $score, [
                ['max'=>2.49, 'label'=>'پایین',   'label_en'=>'Low',      'color'=>'#276749', 'bg'=>'#f0fff4', 'clinical'=>'اجتناب در سطح طبیعی',                        'range'=>'< 2.5'],
                ['max'=>3.49, 'label'=>'متوسط',   'label_en'=>'Moderate', 'color'=>'#d69e2e', 'bg'=>'#fffff0', 'clinical'=>'اجتناب متوسط — ارزیابی بافتاری توصیه می‌شود','range'=>'2.5 – 3.49'],
                ['max'=>4.99, 'label'=>'بالا',    'label_en'=>'High',     'color'=>'#2b6cb0', 'bg'=>'#ebf8ff', 'clinical'=>'بالاتر از cut-off — الگوی اجتنابی محتمل',    'range'=>'3.5 – 4.99'],
                [             'label'=>'بسیار بالا','label_en'=>'Very High','color'=>'#1a365d','bg'=>'#ebf8ff', 'clinical'=>'اجتناب شدید — مداخله درمانی توصیه می‌شود',  'range'=>'≥ 5.0'],
            ]);
        }
    }

    public function calculate( array $answers ): array {
        $answers = \PsychAssessments\Core\Scoring::sanitize_answers( $answers );
        // کلید نمره‌گذاری ECR-R — Fraley et al. (2000)
        // سوالات فرد = اجتناب | سوالات زوج = اضطراب
        // reversed (8 - score): q32 از اضطراب، q33 از اجتناب
        $reversed_anxiety   = [32]; // q32: آیتم معکوس زیرمقیاس اضطراب
        $reversed_avoidance = [33]; // q33: آیتم معکوس زیرمقیاس اجتناب

        $anxiety_qs   = [2,4,6,8,10,12,14,16,18,20,22,24,26,28,30,32,34,36];
        $avoidance_qs = [1,3,5,7,9,11,13,15,17,19,21,23,25,27,29,31,33,35];

        $anxiety_sum = 0;
        foreach ( $anxiety_qs as $q ) {
            $raw = min(7, max(1, (int)($answers[$q] ?? 4)));
            $anxiety_sum += in_array($q, $reversed_anxiety) ? (8 - $raw) : $raw;
        }
        $anxiety_avg = round( $anxiety_sum / count($anxiety_qs), 2 );

        $avoidance_sum = 0;
        foreach ( $avoidance_qs as $q ) {
            $raw = min(7, max(1, (int)($answers[$q] ?? 4)));
            $avoidance_sum += in_array($q, $reversed_avoidance) ? (8 - $raw) : $raw;
        }
        $avoidance_avg = round( $avoidance_sum / count($avoidance_qs), 2 );

        return [
            'anxiety'   => $anxiety_avg,
            'avoidance' => $avoidance_avg,
            'style'     => $this->attachment_style( $anxiety_avg, $avoidance_avg ),
        ];
    }

    public function render_report( array $scores, object $session ): string {
        $anxiety       = $scores['anxiety'];
        $avoidance     = $scores['avoidance'];
        $style         = $scores['style'];
        $anx_sev       = $this->get_severity( $anxiety,  'anxiety' );
        $avo_sev       = $this->get_severity( $avoidance, 'avoidance' );

        $completed_date = $session->completed_at ? pa_to_jalali($session->completed_at, 'Y/m/d ساعت H:i') : '---';

        // داده‌های نمودار
        $radar_labels = ['اضطراب دلبستگی', 'اجتناب دلبستگی'];
        $radar_values = [$anxiety, $avoidance];
        $radar_colors = [$anx_sev['color'], $avo_sev['color']];

        ob_start();
        ?>
        <!-- خلاصه -->
        <div style="display:flex;gap:12px;flex-wrap:wrap;margin-bottom:24px;">
            <?php
            $cards = [
                ['#e9d8fd','#553c9a','👤','نام',               $session->client_name ?? $session->patient_name ?? '---'],
                ['#ebf8ff','#2b6cb0','📞','شماره تماس',      $session->client_phone ?? $session->patient_phone ?? '---'],
                ['#f0fff4','#2f855a','✅','تاریخ تکمیل',      $completed_date],
                ['#fff5f5','#c53030','😟','اضطراب دلبستگی',  $anxiety . ' از ۷ (' . $anx_sev['label'] . ')'],
                ['#ebf8ff','#2b6cb0','🚪','اجتناب دلبستگی',  $avoidance . ' از ۷ (' . $avo_sev['label'] . ')'],
                ['#f3e8ff','#553c9a','🏷','سبک دلبستگی',      $style['label'] . ' — ' . $style['label_en']],
            ];
            foreach ($cards as $c) {
                echo \PsychAssessments\Core\ReportHTML::summary_card($c[0],$c[1],$c[2],$c[3],$c[4]);
            }
            ?>
        </div>

        <!-- سبک دلبستگی -->
        <div style="background:<?php echo esc_attr($style['bg']); ?>;border:2px solid <?php echo esc_attr($style['color']); ?>;border-radius:10px;padding:20px;margin-bottom:24px;">
            <h3 style="margin-top:0;color:<?php echo esc_attr($style['color']); ?>;font-size:17px;">
                🔷 سبک دلبستگی: <?php echo esc_html($style['label']); ?> (<?php echo esc_html($style['label_en']); ?>)
            </h3>
            <p style="font-size:13px;color:#4a5568;line-height:1.8;margin-bottom:0;"><?php echo esc_html($style['desc']); ?></p>
        </div>

        <!-- نمودار + جزئیات -->
        <div style="display:flex;flex-wrap:wrap;gap:24px;align-items:flex-start;margin-bottom:30px;">
            <div style="flex:1;min-width:280px;background:#fff;padding:20px;border-radius:10px;box-shadow:0 2px 10px rgba(0,0,0,0.08);">
                <h3 style="margin-top:0;text-align:center;color:#2d3748;font-size:15px;">نمودار دو بُعد دلبستگی</h3>
                <canvas id="pa-radar-chart" style="max-height:260px;"></canvas>
            </div>
            <div style="flex:1.2;min-width:300px;">
                <!-- اضطراب -->
                <div style="background:#fff;border:1px solid #e2e8f0;border-radius:8px;padding:16px;margin-bottom:16px;border-right:4px solid <?php echo esc_attr($anx_sev['color']); ?>;">
                    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;">
                        <div>
                            <span style="font-size:15px;font-weight:bold;color:<?php echo esc_attr($anx_sev['color']); ?>;">اضطراب دلبستگی</span>
                            <span style="font-size:11px;color:#a0aec0;margin-right:6px;">Attachment Anxiety</span>
                        </div>
                        <?php echo \PsychAssessments\Core\ReportHTML::score_badge($anxiety, $anx_sev); ?>
                    </div>
                    <div style="margin-top:12px;background:#edf2f7;border-radius:4px;height:10px;overflow:hidden;">
                        <div style="background:<?php echo esc_attr($anx_sev['color']); ?>;height:100%;width:<?php echo \PsychAssessments\Core\Scoring::percent($anxiety,1,7); ?>%;border-radius:4px;"></div>
                    </div>
                    <p style="margin:10px 0 6px;font-size:12px;color:#4a5568;">نگرانی از اینکه شریک زندگی در دسترس نباشد یا پاسخ‌گو نباشد؛ ترس از رهاشدگی و عدم تأیید.</p>
                    <div style="background:<?php echo esc_attr($anx_sev['bg']); ?>;border-radius:5px;padding:7px 10px;font-size:12px;color:<?php echo esc_attr($anx_sev['color']); ?>;">💡 <?php echo esc_html($anx_sev['clinical']); ?></div>
                </div>
                <!-- اجتناب -->
                <div style="background:#fff;border:1px solid #e2e8f0;border-radius:8px;padding:16px;border-right:4px solid <?php echo esc_attr($avo_sev['color']); ?>;">
                    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;">
                        <div>
                            <span style="font-size:15px;font-weight:bold;color:<?php echo esc_attr($avo_sev['color']); ?>;">اجتناب دلبستگی</span>
                            <span style="font-size:11px;color:#a0aec0;margin-right:6px;">Attachment Avoidance</span>
                        </div>
                        <?php echo \PsychAssessments\Core\ReportHTML::score_badge($avoidance, $avo_sev); ?>
                    </div>
                    <div style="margin-top:12px;background:#edf2f7;border-radius:4px;height:10px;overflow:hidden;">
                        <div style="background:<?php echo esc_attr($avo_sev['color']); ?>;height:100%;width:<?php echo \PsychAssessments\Core\Scoring::percent($avoidance,1,7); ?>%;border-radius:4px;"></div>
                    </div>
                    <p style="margin:10px 0 6px;font-size:12px;color:#4a5568;">ناراحتی از نزدیکی و وابستگی عاطفی؛ ترجیح استقلال و اتکا به خود بجای دیگران.</p>
                    <div style="background:<?php echo esc_attr($avo_sev['bg']); ?>;border-radius:5px;padding:7px 10px;font-size:12px;color:<?php echo esc_attr($avo_sev['color']); ?>;">💡 <?php echo esc_html($avo_sev['clinical']); ?></div>
                </div>
            </div>
        </div>

        <!-- ماتریس ۴ سبک -->
        <div style="background:#fff;border:1px solid #e2e8f0;border-radius:10px;padding:20px;margin-bottom:30px;">
            <h3 style="margin-top:0;font-size:15px;">📊 ماتریس چهار سبک دلبستگی (Bartholomew & Horowitz)</h3>
            <table style="width:100%;border-collapse:collapse;font-size:12px;text-align:center;">
                <tr>
                    <td style="width:15%;"></td>
                    <th style="padding:8px;background:#f7fafc;border:1px solid #e2e8f0;color:#2b6cb0;">اضطراب پایین</th>
                    <th style="padding:8px;background:#f7fafc;border:1px solid #e2e8f0;color:#c53030;">اضطراب بالا</th>
                </tr>
                <?php
                $cells = [
                    ['اجتناب پایین', 'ایمن',           '#276749', 'Secure',          'اجتناب پایین + اضطراب پایین',  'اجتنابی/ناایمن', '#2b6cb0', 'Dismissing',       'اجتناب پایین + اضطراب بالا'],
                    ['اجتناب بالا',  'ترسان/آشفته',    '#553c9a', 'Fearful-Avoidant','اجتناب بالا + اضطراب پایین',   'دلبسته/نگران',   '#c53030', 'Preoccupied',      'اجتناب بالا + اضطراب بالا'],
                ];
                foreach ($cells as $row) {
                    echo '<tr>';
                    echo '<th style="padding:8px;background:#f7fafc;border:1px solid #e2e8f0;color:#4a5568;">' . esc_html($row[0]) . '</th>';
                    $cur1 = ($style['label_en'] === $row[3]);
                    $cur2 = ($style['label_en'] === $row[7]);
                    echo '<td style="padding:10px;border:1px solid #e2e8f0;background:' . ($cur1?'#f0fff4':'#fff') . ';font-weight:' . ($cur1?'bold':'normal') . ';color:' . esc_attr($row[2]) . ';">';
                    echo esc_html($row[1]) . ($cur1?' ◀':'') . '<br><span style="color:#a0aec0;font-size:10px;">' . esc_html($row[3]) . '</span></td>';
                    echo '<td style="padding:10px;border:1px solid #e2e8f0;background:' . ($cur2?'#fff5f5':'#fff') . ';font-weight:' . ($cur2?'bold':'normal') . ';color:' . esc_attr($row[6]) . ';">';
                    echo esc_html($row[5]) . ($cur2?' ◀':'') . '<br><span style="color:#a0aec0;font-size:10px;">' . esc_html($row[7]) . '</span></td>';
                    echo '</tr>';
                }
                ?>
            </table>
        </div>

        <!-- راهنمای تفسیر -->
        <div style="background:#f7fafc;border:1px solid #e2e8f0;border-radius:10px;padding:20px;margin-bottom:30px;">
            <h3 style="margin-top:0;font-size:15px;">📚 راهنمای تفسیر علمی</h3>
            <p style="font-size:12px;color:#718096;margin-top:0;">Fraley et al. (2000) | Brennan et al. (1998) | Besharat (2011)</p>
            <table style="width:100%;border-collapse:collapse;font-size:12px;">
                <thead><tr style="background:#edf2f7;">
                    <th style="text-align:right;padding:8px 10px;border:1px solid #e2e8f0;">بازه</th>
                    <th style="text-align:right;padding:8px 10px;border:1px solid #e2e8f0;">سطح</th>
                    <th style="text-align:right;padding:8px 10px;border:1px solid #e2e8f0;">تفسیر</th>
                </tr></thead>
                <tbody>
                <?php foreach ([
                    ['≥ 5.0',     'بسیار بالا', '#9b2c2c', 'اضطراب/اجتناب شدید — مداخله درمانی ضروری'],
                    ['3.5–4.9',   'بالا',       '#c53030', 'بالاتر از cut-off بالینی — پیگیری جدی'],
                    ['2.5–3.49',  'متوسط',      '#d69e2e', 'بالاتر از میانگین — ارزیابی بیشتر مفید است'],
                    ['< 2.5',     'پایین',      '#276749', 'در محدوده بهنجار'],
                ] as $r): ?>
                <tr>
                    <td style="padding:7px 10px;border:1px solid #e2e8f0;font-weight:bold;color:<?php echo esc_attr($r[2]); ?>;"><?php echo esc_html($r[0]); ?></td>
                    <td style="padding:7px 10px;border:1px solid #e2e8f0;color:<?php echo esc_attr($r[2]); ?>;font-weight:bold;"><?php echo esc_html($r[1]); ?></td>
                    <td style="padding:7px 10px;border:1px solid #e2e8f0;color:#4a5568;"><?php echo esc_html($r[3]); ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- منابع -->
        <div style="background:#fff;border:1px solid #e2e8f0;border-radius:8px;padding:16px 20px;font-size:11px;color:#a0aec0;line-height:1.8;">
            <b style="color:#4a5568;">منابع:</b><br>
            Fraley, R.C., Waller, N.G., & Brennan, K.A. (2000). <i>Journal of Personality and Social Psychology, 78</i>(2), 350–365.<br>
            Brennan, K.A., Clark, C.L., & Shaver, P.R. (1998). In Simpson & Rholes (Eds.), <i>Attachment Theory and Close Relationships.</i> Guilford Press.<br>
            Besharat, M.A. (2011). Psychometric properties of ECR-R — Iranian sample.<br>
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
                        labels: <?php echo wp_json_encode($radar_labels); ?>,
                        datasets: [{
                            label: 'نمره',
                            data: <?php echo wp_json_encode($radar_values); ?>,
                            backgroundColor: 'rgba(66,153,225,0.15)',
                            borderColor: '#3182ce',
                            borderWidth: 2,
                            pointBackgroundColor: <?php echo wp_json_encode($radar_colors); ?>,
                            pointRadius: 7
                        }]
                    },
                    options: {
                        responsive: true,
                        scales: { r: { min: 1, max: 7, ticks: { stepSize: 1 },
                            pointLabels: { font: { family: 'Tahoma', size: 12 } } } },
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

\PsychAssessments\Core\Registry::register( new ECR() );
