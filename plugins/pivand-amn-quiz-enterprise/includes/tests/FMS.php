<?php
namespace PsychAssessments\Tests;
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * پرسشنامه ترس از ازدواج (FMS)
 *
 * مبانی علمی:
 * - فرامرزی، م.، عطاری، ع.، و صادقی، م. (۱۳۹۰). ساخت و اعتباریابی پرسشنامه ترس از ازدواج
 *   در جمعیت ایرانی. فصلنامه روان‌شناسی کاربردی، ۵(۴)، ۱۰۳–۱۲۰.
 *
 * ساختار: ۳۵ سوال، ۵ زیرمقیاس، مقیاس لیکرت ۵ درجه‌ای (۱=اصلاً — ۵=خیلی زیاد)
 *
 * زیرمقیاس‌ها:
 *   ۱. ترس از تعهد و از دست دادن آزادی        (سوالات ۱–۷)
 *   ۲. ترس از شکست و طلاق                      (سوالات ۸–۱۴)
 *   ۳. ترس از روابط جنسی                       (سوالات ۱۵–۲۱)
 *   ۴. ترس از قضاوت خانواده و اجتماع           (سوالات ۲۲–۲۸)
 *   ۵. ترس از ناتوانی در نقش همسری            (سوالات ۲۹–۳۵)
 *
 * نمره‌گذاری:
 *   هر سوال: ۱ (اصلاً) تا ۵ (خیلی زیاد) — بدون سوال معکوس
 *   دامنه نمره کل: ۳۵–۱۷۵
 *   cut-off کلی: < ۷۰ ترس ضعیف | ۷۰–۱۰۴ ترس متوسط | ≥ ۱۰۵ ترس شدید
 *   cut-off زیرمقیاس (max=35): < ۱۴ ضعیف | ۱۴–۲۰ متوسط | > ۲۰ شدید
 */

class FMS extends \PsychAssessments\Core\BaseTest {

    public function get_id(): string        { return 'fms'; }
    public function get_category(): string      { return 'relationship'; }
    public function get_name(): string      { return 'ترس از ازدواج (FMS)'; }
    public function needs_chart(): bool   { return true; }  // نمودار Chart.js

    public function get_description(): string {
        return 'پرسشنامه ترس از ازدواج — ۳۵ سوال، ۵ زیرمقیاس، بومی و اعتباریابی‌شده (فرامرزی و همکاران، ۱۳۹۰) — مناسب ارزیابی بالینی پیش از ازدواج';
    }
    public function get_version(): string   { return '1.0.0'; }

    public function get_scale(): array {
        return [
            'min'    => 1,
            'max'    => 5,
            'labels' => [
                1 => 'اصلاً',
                2 => 'کمی',
                3 => 'تاحدی',
                4 => 'زیاد',
                5 => 'خیلی زیاد',
            ],
        ];
    }

    private function subscales(): array {
        return [
            'commitment' => [
                'questions' => [1,2,3,4,5,6,7],
                'label'     => 'ترس از تعهد و از دست دادن آزادی',
                'label_en'  => 'Fear of Commitment & Loss of Freedom',
                'color'     => '#2b6cb0',
                'bg'        => '#ebf8ff',
                'max_raw'   => 35,
                'desc'      => 'نگرانی از محدودشدن استقلال فردی، از دست دادن آزادی انتخاب، و احساس به‌دام‌افتادن در تعهدات پایدار زندگی مشترک',
            ],
            'divorce'    => [
                'questions' => [8,9,10,11,12,13,14],
                'label'     => 'ترس از شکست و طلاق',
                'label_en'  => 'Fear of Failure & Divorce',
                'color'     => '#c53030',
                'bg'        => '#fff5f5',
                'max_raw'   => 35,
                'desc'      => 'نگرانی از ناموفق‌بودن ازدواج، احتمال جدایی، و پیامدهای اجتماعی و عاطفی طلاق',
            ],
            'sexuality'  => [
                'questions' => [15,16,17,18,19,20,21],
                'label'     => 'ترس از روابط جنسی',
                'label_en'  => 'Fear of Sexual Relations',
                'color'     => '#97266d',
                'bg'        => '#fff5f7',
                'max_raw'   => 35,
                'desc'      => 'نگرانی از ابعاد جنسی زندگی مشترک، ناتوانی، و عدم آمادگی برای صمیمیت جسمانی',
            ],
            'judgment'   => [
                'questions' => [22,23,24,25,26,27,28],
                'label'     => 'ترس از قضاوت خانواده و اجتماع',
                'label_en'  => 'Fear of Family & Social Judgment',
                'color'     => '#c05621',
                'bg'        => '#fffaf0',
                'max_raw'   => 35,
                'desc'      => 'نگرانی از انتقاد، عدم تأیید، یا فشار خانواده و اطرافیان در خصوص تصمیم به ازدواج یا انتخاب همسر',
            ],
            'role'       => [
                'questions' => [29,30,31,32,33,34,35],
                'label'     => 'ترس از ناتوانی در نقش همسری',
                'label_en'  => 'Fear of Inadequacy in Spousal Role',
                'color'     => '#276749',
                'bg'        => '#f0fff4',
                'max_raw'   => 35,
                'desc'      => 'نگرانی از عدم توانایی ایفای وظایف و مسئولیت‌های همسری، تأمین نیازهای عاطفی، و برآوردن انتظارات نقش',
            ],
        ];
    }

    public function get_questions(): array {
        return [
            // ۱. ترس از تعهد و از دست دادن آزادی
            1  => 'نگران این هستم که ازدواج آزادی و استقلال فردی‌ام را محدود کند.',
            2  => 'از اینکه باید تمام تصمیم‌های مهمم را با همسرم هماهنگ کنم، احساس فشار می‌کنم.',
            3  => 'می‌ترسم پس از ازدواج دیگر نتوانم برنامه‌های شخصی‌ام را به‌آزادی دنبال کنم.',
            4  => 'ایده متعهدشدن به یک نفر برای تمام عمر برایم دلهره‌آور است.',
            5  => 'نگران از دست دادن روابط دوستی و اجتماعی‌ام پس از ازدواج هستم.',
            6  => 'از اینکه زندگی‌ام به زندگی دیگری گره بخورد، احساس اضطراب می‌کنم.',
            7  => 'می‌ترسم که اگر وارد رابطه زناشویی شوم، راه برگشتی نداشته باشم.',

            // ۲. ترس از شکست و طلاق
            8  => 'نگران این هستم که ازدواجم به طلاق بینجامد.',
            9  => 'از تکرارشدن الگوهای ناموفق ازدواج‌هایی که در خانواده یا اطرافیانم دیده‌ام می‌ترسم.',
            10 => 'می‌ترسم پس از ازدواج، ناسازگاری‌هایی کشف کنم که زندگی مشترک را غیرممکن کند.',
            11 => 'احتمال اینکه ازدواجم شاد و موفق باشد را کم می‌بینم.',
            12 => 'از پیامدهای اجتماعی و اقتصادی طلاق بسیار می‌ترسم.',
            13 => 'نگران این هستم که پس از ازدواج همسرم تغییر کند و دیگر همان فردی که می‌شناختم نباشد.',
            14 => 'از ناتوانی در حل تعارضات و بحران‌های زناشویی می‌ترسم.',

            // ۳. ترس از روابط جنسی
            15 => 'جنبه‌های جنسی زندگی مشترک برایم نگران‌کننده است.',
            16 => 'نگران این هستم که از نظر جنسی نتوانم انتظارات همسرم را برآورده کنم.',
            17 => 'از صمیمیت جسمانی و فیزیکی در یک رابطه نزدیک احساس اضطراب می‌کنم.',
            18 => 'آموزش‌ها یا پیام‌های منفی که در مورد رابطه جنسی دریافت کرده‌ام برایم نگران‌کننده است.',
            19 => 'می‌ترسم که در بیان نیازها و خواسته‌های جنسی‌ام با همسرم دچار مشکل شوم.',
            20 => 'نگران تجربه‌های جنسی ناخوشایند احتمالی در زندگی مشترک هستم.',
            21 => 'از اینکه باید درباره موضوعات جنسی با همسرم صحبت کنم، احساس اضطراب دارم.',

            // ۴. ترس از قضاوت خانواده و اجتماع
            22 => 'نگران این هستم که خانواده‌ام انتخاب همسرم را تأیید نکنند.',
            23 => 'از قضاوت اطرافیان درباره زمان یا نحوه ازدواجم می‌ترسم.',
            24 => 'فشار خانواده برای ازدواج باعث اضطراب من می‌شود.',
            25 => 'نگران این هستم که پس از ازدواج، توقعات خانواده همسرم را برآورده نکنم.',
            26 => 'از مقایسه‌شدن با دیگران درباره انتخاب همسر و زمان ازدواج ناراحت می‌شوم.',
            27 => 'می‌ترسم که جامعه یا اطرافیانم تصمیماتم درباره ازدواج را نقد کنند.',
            28 => 'نگران واکنش دیگران به مراسم، شرایط، یا نحوه زندگی مشترکم هستم.',

            // ۵. ترس از ناتوانی در نقش همسری
            29 => 'می‌ترسم نتوانم نیازهای عاطفی همسرم را به‌درستی برآورده کنم.',
            30 => 'نگران این هستم که از پس مسئولیت‌های مالی زندگی مشترک برنیایم.',
            31 => 'می‌ترسم در ایجاد فضای گرم و صمیمی در خانه ناموفق باشم.',
            32 => 'از اینکه نتوانم در دوران سختی پشتیبان خوبی برای همسرم باشم نگرانم.',
            33 => 'می‌ترسم نقش والدینی را در آینده به‌درستی ایفا نکنم.',
            34 => 'نگران این هستم که توانایی مدیریت اختلافات زناشویی را نداشته باشم.',
            35 => 'از اینکه همسرم از من ناامید یا ناراضی شود می‌ترسم.',
        ];
    }

    public function get_schema_labels(): array {
        $labels = [];
        foreach ( $this->subscales() as $key => $def ) {
            $labels[$key] = $def['label'];
        }
        return $labels;
    }

    public function get_domain_labels(): array {
        return [];
    }

    public function calculate( array $answers ): array {
        $answers    = \PsychAssessments\Core\Scoring::sanitize_answers( $answers );
        $subscales  = $this->subscales();
        $sub_scores = [];
        $grand_total = 0;

        foreach ( $subscales as $key => $def ) {
            $sum = 0;
            foreach ( $def['questions'] as $q ) {
                $raw  = min( 5, max( 1, $answers[$q] ?? 1 ) );
                $sum += $raw;
            }
            $sub_scores[$key] = $sum;
            $grand_total     += $sum;
        }

        return [
            'sub_scores' => $sub_scores,
            'total'      => $grand_total,
        ];
    }

    /**
     * منبع واحد نقاط برش کل FMS (ایندکس ۱ = مرز ۱۰۴/۱۰۵، بالای آن «ترس شدید»).
     * تفسیر ترکیبی FMS+PAM در render_report() هم از همین آرایه استفاده می‌کند
     * به‌جای اینکه عدد ۱۰۵ را دوباره جدا هاردکد کند.
     */
    private function total_severity_levels(): array {
        return [
            ['max'=>69,  'label'=>'ترس ضعیف',  'color'=>'#276749', 'bg'=>'#f0fff4', 'clinical'=>'سطح ترس از ازدواج در این فرد پایین است. این وضعیت نشان‌دهنده آمادگی نسبی عاطفی و شناختی برای ورود به زندگی مشترک است.'],
            ['max'=>104, 'label'=>'ترس متوسط', 'color'=>'#c05621', 'bg'=>'#fffaf0', 'clinical'=>'سطح ترس در محدوده متوسط است. بررسی زیرمقیاس‌های برجسته و گفتگوی بالینی هدفمند توصیه می‌شود.'],
            [            'label'=>'ترس شدید',  'color'=>'#c53030', 'bg'=>'#fff5f5', 'clinical'=>'سطح ترس از ازدواج بالاست. مداخله روان‌درمانی (مثلاً CBT یا طرحواره‌درمانی) پیش از اقدام به ازدواج توصیه می‌شود.'],
        ];
    }

    private function total_severity( int $score ): array {
        return \PsychAssessments\Core\Scoring::severity( (float) $score, $this->total_severity_levels() );
    }

    private function subscale_severity( int $score ): array {
        // max=35 — cut-off: <14 ضعیف | 14–20 متوسط | >20 شدید
        if ( $score <= 13 ) return ['label' => 'ضعیف',  'color' => '#276749', 'bg' => '#f0fff4'];
        if ( $score <= 20 ) return ['label' => 'متوسط', 'color' => '#c05621', 'bg' => '#fffaf0'];
        return                     ['label' => 'شدید',  'color' => '#c53030', 'bg' => '#fff5f5'];
    }

    public function render_report( array $data, object $session ): string {
        $sub_scores    = $data['sub_scores'];
        $total         = $data['total'];
        $subscales     = $this->subscales();
        $total_sev     = $this->total_severity( (int) $total );
        $completed_date = $session->completed_at
            ? pa_to_jalali( $session->completed_at, 'Y/m/d ساعت H:i' )
            : '---';

        // داده‌های نمودار
        $chart_labels = [];
        $chart_vals   = [];
        $chart_colors = [];
        foreach ( $subscales as $key => $def ) {
            $chart_labels[] = $def['label'];
            $chart_vals[]   = $sub_scores[$key];
            $chart_colors[] = $def['color'];
        }
        $chart_id = 'fms_chart_' . $session->id;

        ob_start();
        ?>

        <!-- ===== خلاصه سربرگ ===== -->
        <div class="psy-report-row">
            <?php
            $cards = [
                ['#ebf8ff','#2b6cb0','👤','نام',         esc_html($session->client_name ?? $session->patient_name ?? '')],
                ['#f0fff4','#2f855a','📞','تماس',         esc_html( $session->client_phone ?? $session->patient_phone ?? '---' )],
                ['#fffaf0','#c05621','📅','تاریخ تکمیل',  $completed_date],
                ['#faf5ff','#553c9a','📊','نمره کل',       $total . ' از ۱۷۵'],
                [$total_sev['bg'], $total_sev['color'], '🏷','وضعیت', esc_html($total_sev['label'])],
            ];
            foreach ( $cards as [$bg,$col,$ico,$lbl,$val] ) {
                echo \PsychAssessments\Core\ReportHTML::summary_card($bg, $col, $ico, $lbl, $val);
            }
            ?>
        </div>

        <!-- ===== نمره کل + تفسیر ===== -->
        <?php echo \PsychAssessments\Core\ReportHTML::section_header('نمره کل ترس از ازدواج'); ?>
        <div class="psy-report-card psy-report-card--stack <?php echo esc_attr( \PsychAssessments\Core\ReportHTML::tone( (string) $total_sev['color'] ) ); ?>">
            <div class="psy-report-row psy-report-row--center">
                <?php echo \PsychAssessments\Core\ReportHTML::score_badge((float)$total, $total_sev); ?>
                <div class="psy-report-col">
                    <?php $pct = \PsychAssessments\Core\Scoring::percent($total, 35, 175); ?>
                    <?php echo \PsychAssessments\Core\ReportHTML::progress_bar( (float) $pct, (string) $total_sev['color'], true ); ?>
                    <div class="psy-report-en-like">
                        محدوده: &lt;۷۰ ترس ضعیف | ۷۰–۱۰۴ ترس متوسط | ≥۱۰۵ ترس شدید
                    </div>
                </div>
            </div>
        </div>
        <div class="psy-report-chip psy-report-chip--block <?php echo esc_attr( \PsychAssessments\Core\ReportHTML::tone( (string) $total_sev['color'] ) ); ?>">
            💡 <?php echo esc_html($total_sev['clinical']); ?>
        </div>

        <!-- ===== نمودار زیرمقیاس‌ها ===== -->
        <?php echo \PsychAssessments\Core\ReportHTML::section_header('نمودار زیرمقیاس‌های ترس'); ?>
        <div class="psy-report-card psy-report-card--panel psy-report-chart-panel psy-report-panel--center psy-report-chart-wrap">
            <canvas id="<?php echo esc_attr($chart_id); ?>" class="psy-report-chart psy-report-chart--bar" height="260"></canvas>
        </div>
        <script>
        (function(){
            var el = document.getElementById('<?php echo esc_js($chart_id); ?>');
            if(!el) return;
            function drawChart(){
                if(typeof Chart === 'undefined'){ setTimeout(drawChart,300); return; }
                new Chart(el, {
                    type: 'bar',
                    data: {
                        labels: <?php echo wp_json_encode($chart_labels); ?>,
                        datasets: [{
                            label: 'نمره',
                            data: <?php echo wp_json_encode($chart_vals); ?>,
                            backgroundColor: <?php echo wp_json_encode($chart_colors); ?>,
                            borderWidth: 0,
                            borderRadius: 4
                        }]
                    },
                    options: {
                        responsive: true,
                        plugins: {
                            legend: { display: false },
                            tooltip: { callbacks: { label: function(c){ return ' نمره: ' + c.raw + ' از ۳۵'; } } }
                        },
                        scales: {
                            y: {
                                beginAtZero: true, max: 35,
                                ticks: { font: { family:'Vazirmatn,Tahoma' }, color:'#718096', stepSize:7 },
                                grid: { color:'#e2e8f0' }
                            },
                            x: {
                                ticks: { font: { family:'Vazirmatn,Tahoma', size:10 }, color:'#4a5568', maxRotation:20 },
                                grid: { display:false }
                            }
                        }
                    }
                });
            }
            drawChart();
        })();
        </script>

        <!-- ===== زیرمقیاس‌ها ===== -->
        <?php echo \PsychAssessments\Core\ReportHTML::section_header('تفصیل زیرمقیاس‌ها'); ?>
        <?php foreach ( $subscales as $key => $def ):
            $score = $sub_scores[$key];
            $sev   = $this->subscale_severity($score);
            $pct   = \PsychAssessments\Core\Scoring::percent($score, 7, 35);
        ?>
        <div class="psy-report-item psy-report-item--lg <?php echo esc_attr( \PsychAssessments\Core\ReportHTML::tone( (string) $def['color'] ) ); ?>">
            <div class="psy-clinical__head">
                <div>
                    <span class="psy-report-name-lg"><?php echo esc_html($def['label']); ?></span>
                    <span class="psy-report-en psy-report-en--inline"><?php echo esc_html($def['label_en']); ?></span>
                </div>
                <?php echo \PsychAssessments\Core\ReportHTML::score_badge((float)$score, $sev); ?>
            </div>
            <?php echo \PsychAssessments\Core\ReportHTML::progress_bar( (float) $pct, (string) $def['color'] ); ?>
            <p class="psy-report-desc psy-report-desc--flush"><?php echo esc_html($def['desc']); ?></p>
            <div class="psy-report-chip psy-report-chip--block <?php echo esc_attr( \PsychAssessments\Core\ReportHTML::tone( (string) $sev['color'] ) ); ?>">
                سطح ترس: <strong><?php echo esc_html($sev['label']); ?></strong>
                &nbsp;|&nbsp; نمره: <strong><?php echo esc_html((int)$score); ?> از ۳۵</strong>
            </div>
        </div>
        <?php endforeach; ?>

        <!-- ===== همبستگی با PAM (اگر هر دو در جلسه وجود دارند) ===== -->
        <?php
        // اگر در همان بیمار آزمون PAM هم وجود داشته باشد، یادداشت بالینی نمایش می‌دهیم
        global $wpdb;
        $pam_session = null;
        $phone = $session->client_phone ?? $session->patient_phone ?? '';
        if ( ! empty($phone) ) {
            $pam_session = $wpdb->get_row( $wpdb->prepare(
                "SELECT a.*, i.client_name, i.client_phone
                 FROM {$wpdb->prefix}psy_attempts a
                 JOIN {$wpdb->prefix}psy_invitations i ON a.invitation_id = i.id
                 WHERE a.quiz_id = 'pam'
                   AND i.client_phone = %s
                 ORDER BY a.completed_at DESC LIMIT 1",
                $phone
            ) );
        }
        $client_name_v = $session->client_name ?? $session->patient_name ?? '';
        if ( ! $pam_session && ! empty($client_name_v) ) {
            $base_name = preg_replace('/\s*[\[\(][^\]\)]*[\]\)]\s*$/', '', trim($client_name_v));
            if ( strlen($base_name) > 2 ) {
                $pam_session = $wpdb->get_row( $wpdb->prepare(
                    "SELECT a.*, i.client_name, i.client_phone
                     FROM {$wpdb->prefix}psy_attempts a
                     JOIN {$wpdb->prefix}psy_invitations i ON a.invitation_id = i.id
                     WHERE a.quiz_id = 'pam'
                       AND i.client_name LIKE %s
                       AND a.id != %d
                       AND a.completed_at >= DATE_SUB( NOW(), INTERVAL 90 DAY )
                     ORDER BY a.completed_at DESC LIMIT 1",
                    '%' . $wpdb->esc_like($base_name) . '%',
                    $session->id
                ) );
            }
        }
        if ( $pam_session ):
            $pam_answers_json = $wpdb->get_var( $wpdb->prepare(
                "SELECT answers FROM {$wpdb->prefix}psy_attempts WHERE id = %d",
                $pam_session->id
            ) );
            // ستون answers ممکن است رمزنگاری‌شده باشد؛ با helper مشترک رمزگشایی می‌کنیم
            // (قبلاً json_decode خام روی «enc1:…» نال می‌داد و calculate(null) خطای مهلک می‌شد).
            $pam_answers = pa_decrypt_json( (string) $pam_answers_json );
            // محاسبه نمره PAM با استفاده از ثبات رجیستری
            $pam_test = \PsychAssessments\Core\Registry::get('pam');
            $pam_data = ( $pam_test && ! empty( $pam_answers ) ) ? $pam_test->calculate($pam_answers) : null;
            if ( $pam_data ):
                $pam_total = $pam_data['total'];
                // تفسیر ترکیبی FMS + PAM — هر دو مرز از همان منبع واحد آزمون مربوطه خونده می‌شن
                // (به‌جای هاردکد جدای ۱۰۵ و ۱۱۰) تا با severity/cut-off نمایش‌داده‌شده هر آزمون هم‌خوان بمانند.
                $is_high_fear = \PsychAssessments\Core\Scoring::is_above_level( (float) $total, $this->total_severity_levels(), 1 );
                $is_low_pam   = ( $pam_test && method_exists( $pam_test, 'total_severity_levels' ) )
                    ? ! \PsychAssessments\Core\Scoring::is_above_level( (float) $pam_total, $pam_test->total_severity_levels(), 0 )
                    : $pam_total < 110; // fallback ایمن اگر متد در دسترس نبود
                if ( $is_high_fear && $is_low_pam ) {
                    $combo_color = '#c53030';
                    $combo_text  = 'ترکیب ترس از ازدواج بالا و آمادگی ازدواج پایین — اولویت اول مداخله بالینی است. توصیه می‌شود جلسات روان‌درمانی فردی قبل از هر اقدام انجام شود.';
                } elseif ( $is_high_fear && !$is_low_pam ) {
                    $combo_color = '#c05621';
                    $combo_text  = 'آمادگی ازدواج نسبتاً مناسب است اما ترس بالا می‌تواند مانع تصمیم‌گیری سالم شود. تمرکز بر کاهش ترس و باورهای محدودکننده توصیه می‌شود.';
                } elseif ( !$is_high_fear && $is_low_pam ) {
                    $combo_color = '#c05621';
                    $combo_text  = 'ترس پایین است اما آمادگی ازدواج نیاز به تقویت دارد. آموزش مهارت‌های ارتباطی و آمادگی عاطفی توصیه می‌شود.';
                } else {
                    $combo_color = '#276749';
                    $combo_text  = 'پروفایل مناسبی از نظر ترکیب سطح ترس و آمادگی ازدواج مشاهده می‌شود.';
                }
        ?>
        <?php echo \PsychAssessments\Core\ReportHTML::section_header('تفسیر ترکیبی FMS + PAM'); ?>
        <div class="psy-report-card--tint <?php echo esc_attr( \PsychAssessments\Core\ReportHTML::tone( (string) $combo_color ) ); ?>">
            <div class="psy-report-trio">
                <div class="psy-report-trio__cell">
                    <div class="psy-report-trio__value <?php echo esc_attr( \PsychAssessments\Core\ReportHTML::tone( (string) $total_sev['color'] ) ); ?>"><?php echo esc_html($total); ?></div>
                    <div class="psy-report-trio__label">FMS نمره ترس (از ۱۷۵)</div>
                </div>
                <div class="psy-report-trio__cell">
                    <div class="psy-report-trio__value psy-tone--purple"><?php echo esc_html((int)$pam_total); ?></div>
                    <div class="psy-report-trio__label">PAM نمره آمادگی (از ۲۰۰)</div>
                </div>
            </div>
            <p class="psy-report-text psy-report-text--spaced"><strong>تفسیر بالینی:</strong> <?php echo esc_html($combo_text); ?></p>
        </div>
        <?php endif; endif; ?>

        <!-- ===== منابع علمی ===== -->
        <div class="psy-report-refs psy-report-refs--soft">
            <strong>منابع:</strong><br>
            فرامرزی، م.، عطاری، ع.، و صادقی، م. (۱۳۹۰). ساخت و اعتباریابی پرسشنامه ترس از ازدواج در جمعیت ایرانی.
            <em>فصلنامه روان‌شناسی کاربردی</em>، ۵(۴)، ۱۰۳–۱۲۰.
        </div>

        <?php
        return ob_get_clean();
    }
}

\PsychAssessments\Core\Registry::register( new FMS() );
