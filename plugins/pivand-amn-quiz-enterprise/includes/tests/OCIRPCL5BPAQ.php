<?php
namespace PsychAssessments\Tests;
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * OCI-R: Obsessive-Compulsive Inventory – Revised (18 items)
 *
 * - Foa, E.B. et al. (2002). The Obsessive-Compulsive Inventory: Development and validation.
 *   Psychological Assessment, 14(4), 485–496.
 * - Abramowitz, J.S. & Deacon, B.J. (2006). cut-off ≥ 21
 * - Ghassemzadeh, H. et al. (2011). هنجاریابی OCI-R در ایران.
 * - ۱۸ سوال، ۶ زیرمقیاس، مقیاس ۰–۴، cut-off کلی ≥ ۲۱
 */
class OCIR extends \PsychAssessments\Core\BaseTest {

    // باگ M: OCIR نمودار Chart.js ندارد — لود بی‌مورد CDN (~200KB) حذف شد
    public function needs_chart(): bool { return false; }

    public function get_id(): string        { return 'ocir'; }
    public function get_category(): string      { return 'mental_health'; }
    public function get_name(): string      { return 'وسواس فکری-عملی (OCI-R)'; }
    public function get_description(): string { return 'پرسشنامه وسواس فکری-عملی — نسخه تجدیدنظرشده — ۱۸ سوال، ۶ زیرمقیاس، cut-off ≥ ۲۱'; }
    public function get_version(): string   { return '1.0.0'; }

    public function get_scale(): array {
        return [
            'min'    => 0,
            'max'    => 4,
            'labels' => [
                0 => 'اصلاً',
                1 => 'کمی',
                2 => 'تا حدی',
                3 => 'زیاد',
                4 => 'خیلی زیاد',
            ],
        ];
    }

    private function subscales(): array {
        return [
            'washing'   => ['questions'=>[5,11,17],  'label'=>'شستشو',       'label_en'=>'Washing',       'color'=>'#2b6cb0'],
            'obsessing' => ['questions'=>[6,12,18],  'label'=>'وسواس',       'label_en'=>'Obsessing',     'color'=>'#553c9a'],
            'hoarding'  => ['questions'=>[1,7,13],   'label'=>'احتکار',      'label_en'=>'Hoarding',      'color'=>'#c05621'],
            'ordering'  => ['questions'=>[3,9,15],   'label'=>'نظم',         'label_en'=>'Ordering',      'color'=>'#276749'],
            'checking'  => ['questions'=>[2,8,14],   'label'=>'بازبینی',     'label_en'=>'Checking',      'color'=>'#d69e2e'],
            'neutralize'=> ['questions'=>[4,10,16],  'label'=>'خنثی‌سازی',  'label_en'=>'Neutralizing',  'color'=>'#c53030'],
        ];
    }

    public function get_questions(): array {
        return [
            1  => 'وسایل بلااستفاده را انباشته می‌کنم تا جایی که راه بیفتد.',
            2  => 'کارهایی که انجام داده‌ام را مکرراً چک می‌کنم.',
            3  => 'اگر اشیاء مرتب نباشند ناراحت می‌شوم.',
            4  => 'فکرهای بدی را با افکار دیگر خنثی می‌کنم.',
            5  => 'بدون دلیل واقعی دستانم را می‌شویم.',
            6  => 'افکار ناراحت‌کننده‌ای دارم که نمی‌توانم از شرشان خلاص شوم.',
            7  => 'چیزهایی را که دیگران دورشان می‌اندازند جمع می‌کنم.',
            8  => 'مکرراً قفل‌ها، پنجره‌ها و درها را چک می‌کنم.',
            9  => 'اگر اشیاء ترتیب خاصی نداشته باشند ناراحت می‌شوم.',
            10 => 'وقتی فکر بدی به ذهنم می‌رسد، باید کار خاصی انجام دهم تا آن را جبران کنم.',
            11 => 'به طور مکرر می‌شویم و تمیز می‌کنم.',
            12 => 'افکاری دارم که ترسناک هستند.',
            13 => 'وسایل غیرضروری جمع می‌کنم چون نمی‌توانم دورشان بیندازم.',
            14 => 'چک می‌کنم که آیا چیزهایی که ممکن است آسیب برسانند را خاموش کرده‌ام.',
            15 => 'باید اشیاء را به شکل خاصی مرتب کنم.',
            16 => 'وقتی فکر بدی به ذهنم می‌رسد باید آن را جبران کنم.',
            17 => 'از آلودگی بیش از حد می‌ترسم.',
            18 => 'افکار وحشتناکی که کنترلشان سخت است اذیتم می‌کنند.',
        ];
    }

    public function get_schema_labels(): array {
        return array_map(fn($s) => $s['label'], $this->subscales());
    }
    public function get_domain_labels(): array { return []; }

    public function calculate(array $answers): array {
        $answers = \PsychAssessments\Core\Scoring::sanitize_answers( $answers );
        $total = 0;
        $subs  = [];
        foreach ($this->subscales() as $key => $def) {
            $s = \PsychAssessments\Core\Scoring::subscale_sum($def['questions'], $answers, 0, 4);
            $subs[$key] = $s;
            $total += $s;
        }
        return ['total' => $total, 'sub_scores' => $subs];
    }

    public function render_report(array $data, object $session): string {
        $total  = $data['total'];
        $subs_s = $data['sub_scores'];
        $subs   = $this->subscales();
        $cutoff = $total >= 21;
        $completed_date = $session->completed_at ? pa_to_jalali($session->completed_at, 'Y/m/d ساعت H:i') : '---';
        $cut_tone = \PsychAssessments\Core\ReportHTML::tone( $cutoff ? '#c53030' : '#276749' );

        ob_start();
        ?>
        <div class="psy-report-row">
            <?php foreach ([
                ['#ebf8ff','#2b6cb0','📞','تماس',$session->client_phone ?? $session->patient_phone ?? '---'],
                ['#f0fff4','#2f855a','✅','تاریخ',$completed_date],
                [$cutoff?'#fff5f5':'#f0fff4',$cutoff?'#c53030':'#276749','🔢','OCI-R',$total.' از ۷۲'],
                [$cutoff?'#fff5f5':'#f0fff4',$cutoff?'#c53030':'#276749','✂️','cut-off',$cutoff?'≥21 — بالای آستانه':'< 21 — زیر آستانه'],
            ] as $c) echo \PsychAssessments\Core\ReportHTML::summary_card($c[0],$c[1],$c[2],$c[3],$c[4]); ?>
        </div>

        <div class="psy-report-card--tint <?php echo esc_attr( $cut_tone ); ?>">
            <h3 class="psy-report-hero__title">
                📊 نمره کل OCI-R: <?php echo esc_html((int)$total); ?> از ۷۲
                — <?php echo $cutoff ? esc_html('⚠️ بالاتر از cut-off (≥21)') : esc_html('✅ زیر cut-off (<21)'); ?>
            </h3>
            <?php echo \PsychAssessments\Core\ReportHTML::progress_bar( (float) \PsychAssessments\Core\Scoring::percent($total,0,72), $cutoff ? '#c53030' : '#276749', true ); ?>
            <div class="psy-report-scale">
                <span>0</span>
                <span class="psy-tone--danger"><span class="psy-report-scale__mark psy-text">cut-off: 21</span></span>
                <span>72</span>
            </div>
        </div>

        <div class="psy-report-block">
            <h3 class="psy-report-title-rule">🔬 ۶ زیرمقیاس OCI-R</h3>
            <?php foreach ($subs as $key => $def):
                $s = $subs_s[$key];
                $pct = \PsychAssessments\Core\Scoring::percent($s, 0, 12);
                $high = $s >= 5;
                $item_tone = \PsychAssessments\Core\ReportHTML::tone( $def['color'] );
            ?>
            <div class="psy-report-item <?php echo esc_attr( $item_tone ); ?>">
                <div class="psy-clinical__head">
                    <span class="psy-report-item__name"><?php echo esc_html($def['label']); ?> <span class="psy-report-item__en"><?php echo esc_html($def['label_en']); ?></span></span>
                    <span class="psy-report-item__score psy-text <?php echo $high ? 'psy-tone--danger' : 'psy-tone--success'; ?>"><?php echo esc_html((int)$s); ?>/12 <?php echo $high?'⚠️':'✅'; ?></span>
                </div>
                <?php echo \PsychAssessments\Core\ReportHTML::progress_bar( (float) $pct, $def['color'] ); ?>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="psy-report-refs">
            <b>منابع:</b> Foa et al. (2002). <i>Psychological Assessment, 14</i>(4). | Abramowitz &amp; Deacon (2006) cut-off. | Ghassemzadeh et al. (2011) هنجاریابی ایران.<br>
            <b class="psy-tone--danger psy-text">⚠️</b> ابزار کمک‌بالینی — مبنای تشخیص رسمی نیست.
        </div>
        <?php
        return ob_get_clean();
    }
}
\PsychAssessments\Core\Registry::register( new OCIR() );

// ─────────────────────────────────────────────────────────────────────────────

/**
 * PCL-5: PTSD Checklist for DSM-5 (20 items)
 *
 * - Weathers, F.W. et al. (2013). PTSD Checklist for DSM-5 (PCL-5). PTSD: National Center for PTSD.
 * - cut-off ≥ 33 (Blevins et al., 2015) برای تشخیص احتمالی PTSD
 * - ۲۰ سوال، مقیاس ۰–۴، ۴ خوشه DSM-5
 */
class PCL5 extends \PsychAssessments\Core\BaseTest {

    // باگ M: PCL5 نمودار Chart.js ندارد — لود بی‌مورد CDN (~200KB) حذف شد
    public function needs_chart(): bool { return false; }

    public function get_id(): string        { return 'pcl5'; }
    public function get_category(): string      { return 'mental_health'; }
    public function get_name(): string      { return 'PTSD چک‌لیست (PCL-5)'; }
    public function get_description(): string { return 'چک‌لیست PTSD برای DSM-5 — ۲۰ سوال، cut-off ≥ ۳۳، ۴ خوشه'; }
    public function get_version(): string   { return '1.0.0'; }

    public function get_scale(): array {
        return [
            'min'    => 0,
            'max'    => 4,
            'labels' => [
                0 => 'اصلاً',
                1 => 'کمی',
                2 => 'تا حدی',
                3 => 'زیاد',
                4 => 'خیلی زیاد',
            ],
        ];
    }

    private function clusters(): array {
        return [
            'B' => ['questions'=>[1,2,3,4,5],      'label'=>'خوشه B — مزاحمت',     'label_en'=>'Intrusion',         'color'=>'#c53030'],
            'C' => ['questions'=>[6,7],             'label'=>'خوشه C — اجتناب',      'label_en'=>'Avoidance',         'color'=>'#d69e2e'],
            'D' => ['questions'=>[8,9,10,11,12,13,14],'label'=>'خوشه D — شناخت/خلق', 'label_en'=>'Cognition/Mood',    'color'=>'#553c9a'],
            'E' => ['questions'=>[15,16,17,18,19,20],'label'=>'خوشه E — برانگیختگی', 'label_en'=>'Arousal/Reactivity','color'=>'#2b6cb0'],
        ];
    }

    public function get_questions(): array {
        return [
            1  => 'خاطرات ناخواسته، ناراحت‌کننده یا ترسناک از تجربه استرس‌زا',
            2  => 'کابوس‌های مرتبط با تجربه استرس‌زا',
            3  => 'ناگهانی احساس یا رفتار کردن انگار تجربه دوباره اتفاق می‌افتد (فلاش‌بک)',
            4  => 'احساس ناراحتی شدید با یادآوری تجربه استرس‌زا',
            5  => 'واکنش فیزیکی شدید با یادآوری تجربه (مثل ضربان قلب سریع، مشکل تنفس)',
            6  => 'اجتناب از خاطرات، افکار یا احساسات مرتبط با تجربه',
            7  => 'اجتناب از یادآورهای بیرونی تجربه (افراد، مکان‌ها، مکالمات، فعالیت‌ها)',
            8  => 'مشکل در به یاد آوردن بخش‌های مهم تجربه استرس‌زا',
            9  => 'باورهای منفی شدید درباره خود، دیگران یا دنیا',
            10 => 'سرزنش خود یا دیگران برای تجربه استرس‌زا',
            11 => 'احساسات منفی شدید (ترس، وحشت، خشم، گناه، شرم)',
            12 => 'از دست دادن علاقه به فعالیت‌های مهم',
            13 => 'احساس دوری یا جدا بودن از دیگران',
            14 => 'مشکل در تجربه احساسات مثبت (ناتوانی از شادی، عشق)',
            15 => 'تحریک‌پذیری، طغیان خشم یا رفتار پرخاشگرانه',
            16 => 'رفتارهای بی‌پروا یا خودآسیب‌رسان',
            17 => 'هوشیاری بیش از حد (احساس خطر مداوم)',
            18 => 'واکنش‌های ترسناک مبالغه‌آمیز',
            19 => 'مشکل در تمرکز',
            20 => 'مشکل در خواب',
        ];
    }

    public function get_schema_labels(): array {
        return array_map(fn($c) => $c['label'], $this->clusters());
    }
    public function get_domain_labels(): array { return []; }

    public function calculate(array $answers): array {
        $answers = \PsychAssessments\Core\Scoring::sanitize_answers( $answers );
        $total = 0;
        $cluster_scores = [];
        foreach ($this->clusters() as $key => $def) {
            $s = \PsychAssessments\Core\Scoring::subscale_sum($def['questions'], $answers, 0, 4);
            $cluster_scores[$key] = $s;
            $total += $s;
        }
        return ['total' => $total, 'cluster_scores' => $cluster_scores];
    }

    public function render_report(array $data, object $session): string {
        $total   = $data['total'];
        $cs      = $data['cluster_scores'];
        $clusters= $this->clusters();
        $probable= $total >= 33;
        $completed_date = $session->completed_at ? pa_to_jalali($session->completed_at, 'Y/m/d ساعت H:i') : '---';
        $cut_tone = \PsychAssessments\Core\ReportHTML::tone( $probable ? '#c53030' : '#276749' );

        ob_start();
        ?>
        <?php if ($probable): ?>
        <div class="psy-report-alert psy-tone--danger">
            <h3 class="psy-report-alert__title psy-report-alert__title--sm">⚠️ هشدار — نمره بالاتر از cut-off PTSD</h3>
            <p class="psy-report-alert__text">نمره <?php echo esc_html((int)$total); ?> بالاتر از cut-off = 33 است. ارزیابی بالینی دقیق‌تر توسط درمانگر آموزش‌دیده الزامی است.</p>
        </div>
        <?php endif; ?>

        <div class="psy-report-row">
            <?php foreach ([
                ['#ebf8ff','#2b6cb0','📞','تماس',$session->client_phone ?? $session->patient_phone ?? '---'],
                ['#f0fff4','#2f855a','✅','تاریخ',$completed_date],
                [$probable?'#fff5f5':'#f0fff4',$probable?'#c53030':'#276749','📊','PCL-5',$total.' از ۸۰'],
                [$probable?'#fff5f5':'#f0fff4',$probable?'#c53030':'#276749','✂️','cut-off',$probable?'≥33 — PTSD احتمالی':'<33 — زیر آستانه'],
            ] as $c) echo \PsychAssessments\Core\ReportHTML::summary_card($c[0],$c[1],$c[2],$c[3],$c[4]); ?>
        </div>

        <div class="psy-report-card--tint <?php echo esc_attr( $cut_tone ); ?>">
            <h3 class="psy-report-hero__title">نمره کل: <?php echo esc_html((int)$total); ?> از ۸۰</h3>
            <?php echo \PsychAssessments\Core\ReportHTML::progress_bar( (float) \PsychAssessments\Core\Scoring::percent($total,0,80), $probable ? '#c53030' : '#276749', true ); ?>
            <div class="psy-report-scale">
                <span>0</span>
                <span class="psy-tone--danger"><span class="psy-report-scale__mark psy-text">cut-off: 33</span></span>
                <span>80</span>
            </div>
        </div>

        <div class="psy-report-block">
            <h3 class="psy-report-title-rule">📊 ۴ خوشه DSM-5</h3>
            <?php foreach ($clusters as $key => $def):
                $s = $cs[$key]; $max = count($def['questions'])*4;
                $pct = \PsychAssessments\Core\Scoring::percent($s, 0, $max);
                $item_tone = \PsychAssessments\Core\ReportHTML::tone( $def['color'] );
            ?>
            <div class="psy-report-item <?php echo esc_attr( $item_tone ); ?>">
                <div class="psy-clinical__head">
                    <span class="psy-report-item__name"><?php echo esc_html($def['label']); ?></span>
                    <span class="psy-report-item__score psy-text"><?php echo esc_html($s); ?>/<?php echo esc_html($max); ?></span>
                </div>
                <?php echo \PsychAssessments\Core\ReportHTML::progress_bar( (float) $pct, $def['color'] ); ?>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="psy-report-refs">
            <b>منابع:</b> Weathers et al. (2013). PTSD Checklist for DSM-5. National Center for PTSD. | Blevins et al. (2015) cut-off ≥33.<br>
            <b class="psy-tone--danger psy-text">⚠️</b> ابزار غربالگری — تشخیص PTSD نیاز به مصاحبه بالینی دارد.
        </div>
        <?php
        return ob_get_clean();
    }
}
\PsychAssessments\Core\Registry::register( new PCL5() );

// ─────────────────────────────────────────────────────────────────────────────

/**
 * BPAQ: Buss-Perry Aggression Questionnaire (29 items, 4 subscales)
 *
 * - Buss, A.H. & Perry, M. (1992). The Aggression Questionnaire.
 *   Journal of Personality and Social Psychology, 63(3), 452–459.
 * - Mohammadi, N. (2007). هنجاریابی BPAQ در ایران.
 * - مقیاس ۱–۵، ۴ زیرمقیاس: پرخاشگری فیزیکی، کلامی، خشم، خصومت
 */
class BPAQ extends \PsychAssessments\Core\BaseTest {

    // باگ M: BPAQ نمودار Chart.js ندارد — لود بی‌مورد CDN (~200KB) حذف شد
    public function needs_chart(): bool { return false; }

    public function get_id(): string        { return 'bpaq'; }
    public function get_category(): string      { return 'personality'; }
    public function get_name(): string      { return 'پرخاشگری (BPAQ)'; }
    public function get_description(): string { return 'پرسشنامه پرخاشگری باس-پری — ۲۹ سوال، ۴ زیرمقیاس: فیزیکی، کلامی، خشم، خصومت'; }
    public function get_version(): string   { return '1.0.0'; }

    public function get_scale(): array {
        return [
            'min'    => 1,
            'max'    => 5,
            'labels' => [
                1 => 'اصلاً شبیه من نیست',
                2 => 'کمی شبیه من است',
                3 => 'تا حدی شبیه من است',
                4 => 'زیاد شبیه من است',
                5 => 'کاملاً شبیه من است',
            ],
        ];
    }

    private function subscales(): array {
        return [
            'physical' => [
                'questions' => [1,5,9,12,17,21,24,26,28,29],
                'reversed'  => [],
                'label'     => 'پرخاشگری فیزیکی',
                'label_en'  => 'Physical Aggression',
                'color'     => '#9b2c2c',
                'max'       => 50,
            ],
            'verbal'   => [
                'questions' => [2,6,13,18,22],
                'reversed'  => [],
                'label'     => 'پرخاشگری کلامی',
                'label_en'  => 'Verbal Aggression',
                'color'     => '#c05621',
                'max'       => 25,
            ],
            'anger'    => [
                'questions' => [3,7,11,14,19,25,27],
                'reversed'  => [],  // BUG-003 fix: q15 در anger نیست — reversed خالی صحیح است
                'label'     => 'خشم',
                'label_en'  => 'Anger',
                'color'     => '#d69e2e',
                'max'       => 35,
            ],
            'hostility'=> [
                'questions' => [4,8,10,15,16,20,23],
                'reversed'  => [15],
                'label'     => 'خصومت',
                'label_en'  => 'Hostility',
                'color'     => '#553c9a',
                'max'       => 35,
            ],
        ];
    }

    public function get_questions(): array {
        return [
            1  => 'گاهی اوقات نمی‌توانم کشش برای ضربه زدن به کسی را کنترل کنم.',
            2  => 'وقتی با کسی مخالفم صریحاً می‌گویم.',
            3  => 'زود از کوره در می‌روم ولی سریع هم آرام می‌شوم.',
            4  => 'گاهی اوقات به نظرم می‌رسد که دیگران پشت سرم حرف می‌زنند.',
            5  => 'اگر کسی مرا کتک بزند، کتکش می‌زنم.',
            6  => 'غالباً با افرادی که با من مخالفند بحث می‌کنم.',
            7  => 'وقتی خسته‌ام، سریع‌تر عصبانی می‌شوم.',
            8  => 'گاهی اوقات حس می‌کنم که زندگی با من عادلانه رفتار نکرده.',
            9  => 'اگر کسی مرا کافی تحریک کند، ممکن است کتکش بزنم.',
            10 => 'گاهی اوقات حس می‌کنم مثل بشکه باروت هستم که آماده انفجار است.',
            11 => 'وقتی مردم آزارم می‌دهند، ممکن است دستم بلند شود.',
            12 => 'دوستانم می‌گویند که دعواجو هستم.',
            13 => 'نمی‌توانم از بحث کردن خودداری کنم وقتی فکر می‌کنم اشتباه می‌شود.',
            14 => 'دیگران همیشه با من موافق به نظر می‌رسند.',
            15 => 'کنترلم را در برابر زیاده‌روی‌ها حفظ می‌کنم. (معکوس)',
            16 => 'مردم می‌توانند مرا خوب بفریبند.',
            17 => 'در درگیری‌های جسمی شرکت می‌کنم بیشتر از میانگین مردم.',
            18 => 'وقتی ناراحتم کلامم تند می‌شود.',
            19 => 'از اینکه کنترلم را از دست می‌دهم عصبانی می‌شوم.',
            20 => 'می‌دانم که دوستانم پشت سرم درباره‌ام حرف می‌زنند.',
            21 => 'بعضی افراد آنقدر مرا اذیت می‌کنند که به دعوا می‌کشد.',
            22 => 'گاهی اوقات داد و فریاد می‌کنم.',
            23 => 'وقتی مردم مهربان هستند، تعجب می‌کنم که چه می‌خواهند.',
            24 => 'بدون دلیل درگیر دعوا شده‌ام.',
            25 => 'اگر کسی مرا آزار دهد، انتظار دارم درک شود که چرا عصبانی می‌شوم.',
            26 => 'من از قدرت جسمانی‌ام مطمئنم.',
            27 => 'عصبانیتم سریع بیرون می‌زند.',
            28 => 'وقتی ناراحت می‌شوم ممکن است به کسی آسیب بزنم.',
            29 => 'می‌توانم دلیل کافی برای کتک زدن کسی پیدا کنم.',
        ];
    }

    public function get_schema_labels(): array {
        return array_map(fn($s) => $s['label'], $this->subscales());
    }
    public function get_domain_labels(): array { return []; }

    public function calculate(array $answers): array {
        $answers = \PsychAssessments\Core\Scoring::sanitize_answers( $answers );
        $subs  = $this->subscales();
        $scores = [];
        $total = 0;
        foreach ($subs as $key => $def) {
            $sum = 0;
            foreach ($def['questions'] as $q) {
                $raw = min(5, max(1, (int)($answers[$q] ?? 3)));
                $sum += in_array($q, $def['reversed']) ? (6 - $raw) : $raw;
            }
            $scores[$key] = $sum;
            $total += $sum;
        }
        return ['scores' => $scores, 'total' => $total];
    }

    public function render_report(array $data, object $session): string {
        $scores = $data['scores'];
        $total  = $data['total'];
        $subs   = $this->subscales();
        $completed_date = $session->completed_at ? pa_to_jalali($session->completed_at, 'Y/m/d ساعت H:i') : '---';

        ob_start();
        ?>
        <div class="psy-report-row">
            <?php foreach ([
                ['#ebf8ff','#2b6cb0','📞','تماس',$session->client_phone ?? $session->patient_phone ?? '---'],
                ['#f0fff4','#2f855a','✅','تاریخ',$completed_date],
                ['#fff5f5','#c53030','😤','BPAQ کل',$total.' از ۱۴۵'],
            ] as $c) echo \PsychAssessments\Core\ReportHTML::summary_card($c[0],$c[1],$c[2],$c[3],$c[4]); ?>
        </div>

        <div class="psy-report-block">
            <?php foreach ($subs as $key => $def):
                $s = $scores[$key];
                $pct = \PsychAssessments\Core\Scoring::percent($s, count($def['questions']), $def['max']);
                $high = $pct >= 70;
                $item_tone = \PsychAssessments\Core\ReportHTML::tone( $def['color'] );
            ?>
            <div class="psy-report-item psy-report-item--lg <?php echo esc_attr( $item_tone ); ?>">
                <div class="psy-clinical__head">
                    <span class="psy-report-item__name"><?php echo esc_html($def['label']); ?> <span class="psy-report-item__en"><?php echo esc_html($def['label_en']); ?></span></span>
                    <span class="psy-report-item__score psy-report-item__score--lg psy-text <?php echo $high ? 'psy-tone--danger' : 'psy-tone--success'; ?>"><?php echo esc_html((int)$s); ?>/<?php echo esc_html((int)$def['max']); ?> (<?php echo esc_html((int)$pct); ?>%)</span>
                </div>
                <?php echo \PsychAssessments\Core\ReportHTML::progress_bar( (float) $pct, $def['color'] ); ?>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="psy-report-refs">
            <b>منابع:</b> Buss &amp; Perry (1992). <i>J. Personality and Social Psychology, 63</i>(3). | Mohammadi, N. (2007) هنجاریابی ایران.<br>
            <b class="psy-tone--danger psy-text">⚠️</b> ابزار کمک‌بالینی — مبنای تشخیص رسمی نیست.
        </div>
        <?php
        return ob_get_clean();
    }
}
\PsychAssessments\Core\Registry::register( new BPAQ() );
