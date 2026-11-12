<?php
namespace PsychAssessments\Tests;
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * MDQ: Mood Disorder Questionnaire
 *
 * - Hirschfeld, R.M.A., Williams, J.B.W., Spitzer, R.L., et al. (2000).
 *   Development and validation of a screening instrument for bipolar spectrum disorder:
 *   The Mood Disorder Questionnaire.
 *   American Journal of Psychiatry, 157(11), 1873–1875.
 * - بدون محدودیت استفاده بالینی
 * - هنجاریابی ایران: خدادادی سنگده و همکاران (۱۳۹۰)
 *
 * ساختار:
 *   - بخش A: ۱۳ سوال بله/خیر (نمره: ۱=بله، ۰=خیر)
 *   - بخش B: ۱ سوال — آیا چند مورد از موارد بالا همزمان رخ داده؟ (بله/خیر)
 *   - بخش C: ۱ سوال — شدت اختلال عملکرد (۰=اصلاً، ۱=کم، ۲=متوسط، ۳=زیاد)
 *
 * ملاک مثبت اسکرینینگ:
 *   - حداقل ۷ پاسخ بله در بخش A
 *   - AND پاسخ بله در بخش B
 *   - AND نمره ۲ یا ۳ در بخش C (اختلال عملکرد متوسط/زیاد)
 */
class MDQ extends \PsychAssessments\Core\BaseTest {

    public function get_id(): string        { return 'mdq'; }
    public function get_category(): string      { return 'mental_health'; }
    public function get_name(): string      { return 'اختلال خلقی MDQ'; }
    public function get_description(): string {
        return 'پرسشنامه اختلالات خلقی (MDQ) — ۱۵ سوال، غربالگری طیف دوقطبی، اعتبارسنجی Hirschfeld et al. (2000)';
    }
    public function get_version(): string   { return '1.0.0'; }
    // باگ #M1: صریحاً تعریف می‌شود — MDQ در render_report از canvas/Chart.js استفاده می‌کند
    public function needs_chart(): bool     { return true; }

    public function get_scale(): array {
        // بخش A: بله/خیر | بخش B: بله/خیر | بخش C: ۰–۳
        // برای سادگی فرم، از مقیاس 0/1 برای بخش‌های A و B استفاده می‌شود
        // بخش C با مقیاس 0-3 جداگانه handle می‌شود
        return [
            'min'    => 0,
            'max'    => 1,
            'labels' => [
                0 => 'خیر',
                1 => 'بله',
            ],
        ];
    }

    public function get_questions(): array {
        return [
            // بخش A — ۱۳ سوال بله/خیر
            1  => 'آیا تا به حال دوره‌ای داشته‌اید که در آن احساس می‌کردید روحیه‌تان بسیار بالا، یا سرحال، یا بسرخوشانه بوده، یا اینکه آنقدر بانشاط بوده‌اید که دیگران نگرانتان شده‌اند یا برایتان مشکل ساز شده است؟',
            2  => 'آیا تا به حال دوره‌ای داشته‌اید که در آن احساس می‌کردید بسیار تحریک‌پذیر هستید، به‌طوری‌که با دیگران دعوا می‌کردید یا بر سر مردم داد می‌زدید یا با مردم درگیر می‌شدید؟',
            3  => 'آیا در آن دوره احساس می‌کردید انرژی بیشتری نسبت به همیشه دارید؟',
            4  => 'آیا در آن دوره احساس می‌کردید نسبت به معمول کمتر به خواب نیاز دارید؟',
            5  => 'آیا در آن دوره تکلم شما سریع‌تر از معمول بود؟',
            6  => 'آیا در آن دوره افکارتان تندتر از حد معمول در ذهنتان می‌گذشت یا نمی‌توانستید افکارتان را متوقف کنید؟',
            7  => 'آیا در آن دوره به راحتی حواستان پرت می‌شد؟',
            8  => 'آیا در آن دوره اعتماد به نفس بیشتری از معمول داشتید؟',
            9  => 'آیا در آن دوره بیشتر از معمول پول خرج می‌کردید یا کارهای مالی غیرعادی انجام می‌دادید که بعداً برایتان مشکل ساز می‌شد؟',
            10 => 'آیا در آن دوره فعال‌تر از معمول بودید؟',
            11 => 'آیا در آن دوره بیشتر از معمول فعالیت جنسی داشتید؟',
            12 => 'آیا در آن دوره بی‌ملاحظه‌تر از معمول بودید یا کارهای غیرعادی انجام می‌دادید؟',
            13 => 'آیا در آن دوره بیشتر از معمول در کسب‌وکار یا پروژه‌های دیگر شرکت می‌کردید؟',
            // بخش B — ۱ سوال
            14 => 'اگر بیش از یک مورد از موارد بالا را تجربه کرده‌اید، آیا چندتا از آنها در یک دوره زمانی واحد با هم اتفاق افتادند؟ (۰=خیر | ۱=بله)',
            // بخش C — شدت اختلال عملکرد (پاسخ 0–3 در patient-form)
            15 => 'شدت اختلال عملکرد ناشی از این دوره‌ها چقدر بوده است؟ (۰=اصلاً | ۱=کم | ۲=متوسط | ۳=زیاد)',
        ];
    }

    public function get_schema_labels(): array { return []; }
    public function get_domain_labels(): array { return []; }

    /**
     * مقیاس اختصاصی per-question — سوال ۱۵ (بخش C) مقیاس ۰–۳ دارد
     * @return array<int, array>  کلید = شماره سوال، مقدار = ['min','max','labels']
     */
    public function get_question_scales(): array {
        return [
            14 => [
                'min'    => 0,
                'max'    => 1,
                'labels' => [ 0 => 'خیر', 1 => 'بله' ],
            ],
            15 => [
                'min'    => 0,
                'max'    => 3,
                'labels' => [
                    0 => 'اصلاً — هیچ مشکلی در کار، مراقبت از خانه یا روابطم ایجاد نکرده',
                    1 => 'کم — مشکل جزئی داشتم',
                    2 => 'متوسط — مشکل قابل توجهی داشتم',
                    3 => 'زیاد — مشکل جدی در یک یا چند حوزه ایجاد کرده',
                ],
            ],
        ];
    }

    /**
     * تشخیص مثبت اسکرینینگ بر اساس Hirschfeld et al. (2000)
     * Sensitivity: 73% | Specificity: 90%
     */
    private function is_positive( int $part_a, bool $part_b, int $part_c ): bool {
        return ( $part_a >= 7 ) && $part_b && ( $part_c >= 2 );
    }

    public function calculate( array $answers ): array {
        $answers = \PsychAssessments\Core\Scoring::sanitize_answers( $answers );
        $part_a = 0;
        for ( $q = 1; $q <= 13; $q++ ) {
            $part_a += ( (int)( $answers[$q] ?? 0 ) >= 1 ) ? 1 : 0;
        }
        $part_b = ( (int)( $answers[14] ?? 0 ) >= 1 );
        $part_c = min( 3, max( 0, (int)( $answers[15] ?? 0 ) ) );

        return [
            'part_a'     => $part_a,
            'part_b'     => $part_b ? 1 : 0,
            'part_c'     => $part_c,
            'positive'   => $this->is_positive( $part_a, $part_b, $part_c ) ? 1 : 0,
            'total'      => $part_a, // برای سازگاری با patient-history
            'avg_total'  => (float)$part_a,
        ];
    }

    public function render_report( array $scores, object $session ): string {
        $completed = pa_to_jalali( $session->completed_at ?? '', 'Y/m/d ساعت H:i' );
        $part_a    = (int)$scores['part_a'];
        $part_b    = (bool)$scores['part_b'];
        $part_c    = (int)$scores['part_c'];
        $positive  = (bool)$scores['positive'];
        // باگ J: answers ممکن است در برخی contexts (مثل REST موبایل) موجود نباشد
        $raw_answers = [];
        if ( ! empty( $session->answers ) ) {
            $raw_answers = is_string( $session->answers )
                ? pa_decrypt_json( $session->answers )
                : (array) $session->answers;
        }

        $status_color = $positive ? '#9b2c2c' : '#276749';
        $status_bg    = $positive ? '#fff5f5'  : '#f0fff4';
        $status_label = $positive ? 'اسکرینینگ مثبت — ارزیابی بیشتر لازم است' : 'اسکرینینگ منفی';
        $status_icon  = $positive ? '⚠️' : '✅';

        $part_c_labels = [ 0 => 'اصلاً', 1 => 'کم', 2 => 'متوسط', 3 => 'زیاد' ];

        ob_start(); ?>
        <div style="display:flex;gap:12px;flex-wrap:wrap;margin-bottom:24px;">
            <?php foreach ([
                ['#ebf8ff','#2b6cb0','📞','شماره تماس', $session->client_phone ?? $session->patient_phone ?? '---'],
                ['#f0fff4','#2f855a','✅','تاریخ تکمیل', $completed],
                ['#fffff0','#d69e2e','📋','بخش A (۱۳ سوال)', $part_a . ' از ۱۳ بله'],
                [$status_bg, $status_color, $status_icon, 'نتیجه اسکرینینگ', $positive ? 'مثبت' : 'منفی'],
            ] as $c) echo \PsychAssessments\Core\ReportHTML::summary_card($c[0],$c[1],$c[2],$c[3],$c[4]); ?>
        </div>

        <div style="background:<?php echo esc_attr($status_bg); ?>;border:2px solid <?php echo esc_attr($status_color); ?>;border-radius:10px;padding:22px;margin-bottom:24px;">
            <h3 style="margin:0 0 8px;color:<?php echo esc_attr($status_color); ?>;font-size:16px;">
                <?php echo $status_icon; ?> <?php echo esc_html($status_label); ?>
            </h3>
            <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin-top:14px;font-size:13px;">
                <div style="background:#fff;border-radius:8px;padding:12px;text-align:center;border:1px solid #e2e8f0;">
                    <div style="font-size:24px;font-weight:bold;color:<?php echo $part_a >= 7 ? '#c05621' : '#276749'; ?>;"><?php echo esc_html((int)$part_a); ?>/۱۳</div>
                    <div style="color:#718096;margin-top:4px;">بخش A — موارد بله</div>
                    <div style="font-size:11px;color:<?php echo $part_a >= 7 ? '#c05621' : '#276749'; ?>;margin-top:4px;"><?php echo $part_a >= 7 ? esc_html('آستانه رسیده (≥۷)') : esc_html('زیر آستانه'); ?></div>
                </div>
                <div style="background:#fff;border-radius:8px;padding:12px;text-align:center;border:1px solid #e2e8f0;">
                    <div style="font-size:24px;font-weight:bold;color:<?php echo esc_attr($part_b ? '#c05621' : '#276749'); ?>;"><?php echo $part_b ? esc_html('بله') : esc_html('خیر'); ?></div>
                    <div style="color:#718096;margin-top:4px;">بخش B — همزمانی</div>
                    <div style="font-size:11px;color:<?php echo $part_b ? '#c05621' : '#718096'; ?>;margin-top:4px;"><?php echo $part_b ? esc_html('شرط برآورده شد') : esc_html('شرط برآورده نشد'); ?></div>
                </div>
                <div style="background:#fff;border-radius:8px;padding:12px;text-align:center;border:1px solid #e2e8f0;">
                    <div style="font-size:24px;font-weight:bold;color:<?php echo $part_c >= 2 ? '#c05621' : '#276749'; ?>"><?php echo esc_html($part_c_labels[$part_c]); ?></div>
                    <div style="color:#718096;margin-top:4px;">بخش C — اختلال عملکرد</div>
                    <div style="font-size:11px;color:<?php echo $part_c >= 2 ? '#c05621' : '#718096'; ?>;margin-top:4px;"><?php echo $part_c >= 2 ? esc_html('اختلال معنادار') : esc_html('اختلال کم'); ?></div>
                </div>
            </div>
        </div>

        <?php echo \PsychAssessments\Core\ReportHTML::section_header('پروفایل پاسخ‌های بخش A'); ?>
        <div style="background:#fff;padding:20px;border-radius:10px;box-shadow:0 2px 10px rgba(0,0,0,0.08);margin-bottom:24px;">
            <canvas id="pa-mdq-bar" style="max-height:180px;"></canvas>
        </div>

        <?php if ($positive): ?>
        <div style="background:#fff5f5;border:1px solid #fc8181;border-radius:8px;padding:16px;margin-bottom:20px;">
            <strong style="color:#9b2c2c;">⚠️ تذکر بالینی:</strong>
            <p style="margin:8px 0 0;font-size:13px;color:#4a5568;line-height:1.8;">
                اسکرینینگ MDQ مثبت نشان‌دهنده احتمال وجود اختلال طیف دوقطبی است. ارزیابی روان‌پزشکی کامل
                شامل مصاحبه بالینی ساختاریافته (SCID-I یا MINI) ضروری است. تشخیص صرفاً بر اساس
                این پرسشنامه نباید صورت گیرد.
            </p>
        </div>
        <?php endif; ?>

        <div style="background:#fff;border:1px solid #e2e8f0;border-radius:8px;padding:14px 20px;font-size:11px;color:#a0aec0;line-height:1.8;margin-top:10px;">
            <b style="color:#4a5568;">منبع:</b> Hirschfeld et al. (2000). American Journal of Psychiatry, 157(11), 1873–1875.<br>
            Sensitivity: 73.0% | Specificity: 90.0% برای تشخیص BD-I<br>
            <b style="color:#c53030;">⚠️</b> ابزار کمک‌بالینی — مبنای تشخیص رسمی نیست.
        </div>

        <script>
        (function(){
            function init(){
                if(typeof Chart==='undefined'){setTimeout(init,100);return;}
                var ctx=document.getElementById('pa-mdq-bar');
                if(!ctx)return;
                var answers=[<?php
                    $vals = [];
                    for ($q=1;$q<=13;$q++) $vals[] = (int)($raw_answers[$q] ?? 0);
                    echo implode(',', $vals);
                ?>];
                new Chart(ctx.getContext('2d'),{
                    type:'bar',
                    data:{
                        labels:[<?php echo implode(',', array_map(fn($i)=>"'س$i'", range(1,13))); ?>],
                        datasets:[{
                            data:answers,
                            backgroundColor:answers.map(v=>v?'#fc8181':'#90cdf4'),
                            borderRadius:4
                        }]
                    },
                    options:{
                        responsive:true,
                        plugins:{legend:{display:false},tooltip:{callbacks:{label:ctx=>ctx.raw?'بله':'خیر'}}},
                        scales:{y:{min:0,max:1,ticks:{stepSize:1,callback:v=>v?'بله':'خیر'}}}
                    }
                });
            }
            document.addEventListener('DOMContentLoaded',init);
        })();
        </script>
        <?php
        return ob_get_clean();
    }
}
\PsychAssessments\Core\Registry::register( new MDQ() );


// ============================================================

/**
 * EPDS: Edinburgh Postnatal Depression Scale
 *
 * - Cox, J.L., Holden, J.M., & Sagovsky, R. (1987).
 *   Detection of postnatal depression: Development of the 10-item Edinburgh Postnatal
 *   Depression Scale. British Journal of Psychiatry, 150, 782–786.
 * - صریحاً برای استفاده بالینی و پژوهشی رایگان اعلام شده
 * - هنجاریابی ایران: محمدی و همکاران (۱۳۸۵) | Montazeri et al. (2007)
 *
 * ساختار:
 *   - ۱۰ سوال، هر سوال ۰–۳
 *   - سوالات ۳، ۵، ۶، ۷، ۸، ۹، ۱۰ نمره‌گذاری معکوس
 *   - cut-off بالینی: ≥13 (افسردگی احتمالی) | ≥10 (کم‌خطر)
 *   - سوال ۱۰ (آسیب به خود) — هشدار بالینی اجباری
 */
class EPDS extends \PsychAssessments\Core\BaseTest {

    public function get_id(): string        { return 'epds'; }
    public function get_category(): string      { return 'mental_health'; }
    public function get_name(): string      { return 'افسردگی پس از زایمان EPDS'; }
    public function get_description(): string {
        return 'مقیاس افسردگی پس از زایمان ادینبرا — ۱۰ سوال، محدوده ۰–۳۰، Cox et al. (1987) | ⚠️ این ابزار فقط برای زنان در دوره پره‌ناتال و پست‌ناتال (حین بارداری و تا یک سال پس از زایمان) اعتبارسنجی شده است';
    }
    public function get_version(): string   { return '1.0.0'; }
    // باگ #M1: صریحاً تعریف می‌شود — EPDS در render_report از canvas/Chart.js استفاده می‌کند
    public function needs_chart(): bool     { return true; }

    public function get_scale(): array {
        return [
            'min'    => 0,
            'max'    => 3,
            'labels' => [
                0 => 'نه، اصلاً',
                1 => 'نه، خیلی کم',
                2 => 'بله، تا حدودی',
                3 => 'بله، اغلب',
            ],
        ];
    }

    /**
     * سوالات معکوس (positively worded — نمره بالا = علائم کمتر)
     *
     * Cox, Holden & Sagovsky (1987): تنها سوالات ۱ و ۲ مثبت‌وجهی هستند.
     * هر دو سوال با گزینه‌های "به همان اندازه معمول" (=۰) شروع می‌شوند
     * که با مقیاس یکنواخت فرم (۰=اصلاً=علائم بیشتر) تناقض دارد؛
     * لذا باید وارونه شوند.
     * سوالات ۳–۱۰ منفی‌وجهی هستند و مستقیم نمره‌گذاری می‌شوند.
     */
    private function reversed(): array {
        return [1, 2];
    }

    public function get_questions(): array {
        return [
            1  => 'قادر بوده‌ام بخندم و جنبه شاد زندگی را ببینم',
            2  => 'با اشتیاق به رویدادهای پیش رو نگاه کرده‌ام',
            3  => 'وقتی که کارها اشتباه پیش رفته، بی‌دلیل خودم را سرزنش کرده‌ام',
            4  => 'بدون دلیل واضحی دچار اضطراب یا نگرانی شده‌ام',
            5  => 'بدون دلیل واضحی احساس ترس یا وحشت کرده‌ام',
            6  => 'احساس کرده‌ام که کارها از پسم برنمی‌آیند',
            7  => 'آنقدر ناراحت بوده‌ام که در خواب مشکل داشته‌ام',
            8  => 'احساس غمگینی یا دل‌تنگی کرده‌ام',
            9  => 'آنقدر ناراحت بوده‌ام که گریه کرده‌ام',
            10 => 'فکر آسیب رساندن به خودم به ذهنم خطور کرده است',
        ];
    }

    public function get_schema_labels(): array { return []; }
    public function get_domain_labels(): array { return []; }

    private function get_severity( int $total ): array {
        return \PsychAssessments\Core\Scoring::severity( (float)$total, [
            ['max'=>9,  'label'=>'بدون نگرانی',   'label_en'=>'Not Depressed',      'color'=>'#276749','bg'=>'#f0fff4','clinical'=>'بدون نشانه افسردگی معنادار — پیگیری معمول',                    'range'=>'0–9'],
            ['max'=>12, 'label'=>'در معرض خطر',   'label_en'=>'At Risk',             'color'=>'#d69e2e','bg'=>'#fffff0','clinical'=>'در معرض افسردگی — ارزیابی بیشتر توصیه می‌شود',                 'range'=>'10–12'],
            [           'label'=>'افسردگی احتمالی','label_en'=>'Probable Depression', 'color'=>'#9b2c2c','bg'=>'#fff5f5','clinical'=>'احتمال بالای افسردگی پس از زایمان — مداخله فوری ضروری', 'range'=>'≥13'],
        ]);
    }

    public function calculate( array $answers ): array {
        $answers = \PsychAssessments\Core\Scoring::sanitize_answers( $answers );
        $reversed = $this->reversed();
        $total    = 0;
        for ( $q = 1; $q <= 10; $q++ ) {
            $v = min( 3, max( 0, (int)( $answers[$q] ?? 0 ) ) );
            $total += in_array( $q, $reversed ) ? ( 3 - $v ) : $v;
        }
        // سوال ۱۰ منفی‌وجهی (direct) است — هر پاسخ غیرصفر = هشدار بالینی.
        // چون Q10 در reversed() نیست، مقدار خام مستقیماً به total اضافه شده؛
        // self_harm همان مقدار خام است (بدون وارونگی مجدد).
        $q10_raw = min( 3, max( 0, (int)( $answers[10] ?? 0 ) ) );

        return [
            'total'       => $total,
            'self_harm'   => $q10_raw,   // 0=بدون خطر، ≥1=هشدار (مقدار خام)
            'avg_total'   => (float)$total,
        ];
    }

    public function render_report( array $scores, object $session ): string {
        $completed  = pa_to_jalali( $session->completed_at ?? '', 'Y/m/d ساعت H:i' );
        $total      = (int)$scores['total'];
        $self_harm  = (int)$scores['self_harm'];
        $sev        = $this->get_severity( $total );
        // باگ J: defensive check — answers ممکن است null باشد
        $raw_answers = [];
        if ( ! empty( $session->answers ) ) {
            $raw_answers = is_string( $session->answers )
                ? pa_decrypt_json( $session->answers )
                : (array) $session->answers;
        }

        ob_start(); ?>
        <?php if ( $self_harm >= 1 ): ?>
        <div style="background:#fff5f5;border:2px solid #fc8181;border-radius:10px;padding:18px;margin-bottom:20px;">
            <strong style="color:#9b2c2c;font-size:15px;">🚨 هشدار بالینی — سوال ۱۰ (آسیب به خود)</strong>
            <p style="margin:10px 0 0;font-size:13px;color:#742a2a;line-height:1.8;">
                بیمار به سوال آسیب به خود پاسخ مثبت داده است. ارزیابی ایمنی فوری ضروری است.
                این موضوع باید در اولین فرصت به صورت حضوری بررسی شود.
            </p>
        </div>
        <?php endif; ?>

        <div style="display:flex;gap:12px;flex-wrap:wrap;margin-bottom:24px;">
            <?php foreach ([
                ['#ebf8ff','#2b6cb0','📞','شماره تماس', $session->client_phone ?? $session->patient_phone ?? '---'],
                ['#f0fff4','#2f855a','✅','تاریخ تکمیل', $completed],
                [$sev['bg'],$sev['color'],'🌸','نمره کل EPDS', $total . ' از ۳۰'],
                [$sev['bg'],$sev['color'],'🏷','وضعیت', $sev['label'] . ' (' . $sev['label_en'] . ')'],
            ] as $c) echo \PsychAssessments\Core\ReportHTML::summary_card($c[0],$c[1],$c[2],$c[3],$c[4]); ?>
        </div>

        <div style="background:<?php echo esc_attr($sev['bg']); ?>;border:2px solid <?php echo esc_attr($sev['color']); ?>;border-radius:10px;padding:22px;margin-bottom:24px;">
            <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;">
                <div>
                    <h3 style="margin:0 0 6px;color:<?php echo esc_attr($sev['color']); ?>;">نمره EPDS: <?php echo esc_html((int)$total); ?> از ۳۰</h3>
                    <p style="margin:0;font-size:13px;color:#4a5568;"><?php echo esc_html($sev['clinical']); ?></p>
                </div>
                <?php echo \PsychAssessments\Core\ReportHTML::score_badge((float)$total, $sev); ?>
            </div>
            <div style="margin-top:14px;background:#edf2f7;border-radius:6px;height:10px;overflow:hidden;">
                <div style="background:<?php echo esc_attr($sev['color']); ?>;height:100%;width:<?php echo \PsychAssessments\Core\Scoring::percent($total,0,30); ?>%;border-radius:6px;"></div>
            </div>
            <div style="display:flex;justify-content:space-between;font-size:10px;color:#a0aec0;margin-top:4px;">
                <span>۰</span>
                <span style="color:#d69e2e;">۱۰ در معرض خطر</span>
                <span style="color:#9b2c2c;">۱۳ احتمال افسردگی</span>
                <span>۳۰</span>
            </div>
        </div>

        <?php echo \PsychAssessments\Core\ReportHTML::section_header('پروفایل نمرات ۱۰ سوال'); ?>
        <div style="background:#fff;padding:20px;border-radius:10px;box-shadow:0 2px 10px rgba(0,0,0,0.08);margin-bottom:24px;">
            <canvas id="pa-epds-bar" style="max-height:200px;"></canvas>
        </div>

        <div style="background:#fff;border:1px solid #e2e8f0;border-radius:8px;padding:16px;margin-bottom:20px;font-size:13px;color:#4a5568;line-height:1.8;">
            <strong style="color:#2d3748;">📌 راهنمای تفسیر (Cox et al., 1987):</strong><br>
            <span style="color:#276749;">۰–۹:</span> بدون نگرانی قابل توجه &nbsp;|&nbsp;
            <span style="color:#d69e2e;">۱۰–۱۲:</span> در معرض خطر — پیگیری &nbsp;|&nbsp;
            <span style="color:#9b2c2c;">≥۱۳:</span> افسردگی احتمالی — مداخله<br>
            سوالات معکوس (۱، ۲) قبل از جمع‌بندی وارونه شده‌اند.
        </div>

        <div style="background:#fff;border:1px solid #e2e8f0;border-radius:8px;padding:14px 20px;font-size:11px;color:#a0aec0;line-height:1.8;margin-top:10px;">
            <b style="color:#4a5568;">منبع:</b> Cox, Holden & Sagovsky (1987). British Journal of Psychiatry, 150, 782–786.<br>
            هنجاریابی ایران: Montazeri et al. (2007). BMC Psychiatry, 7:11.<br>
            <b style="color:#c53030;">⚠️</b> ابزار کمک‌بالینی — مبنای تشخیص رسمی نیست.<br>
            <b style="color:#c05621;">👩‍⚕️</b> این مقیاس صرفاً برای زنان در دوره بارداری و تا یک سال پس از زایمان اعتبارسنجی شده است.
        </div>

        <script>
        (function(){
            function init(){
                if(typeof Chart==='undefined'){setTimeout(init,100);return;}
                var ctx=document.getElementById('pa-epds-bar');
                if(!ctx)return;
                var reversed=[1,2];
                var raw=[<?php
                    $vals=[];
                    for($q=1;$q<=10;$q++) $vals[]=(int)($raw_answers[$q]??0);
                    echo implode(',',$vals);
                ?>];
                var scored=raw.map((v,i)=>reversed.includes(i+1)?3-v:v);
                new Chart(ctx.getContext('2d'),{
                    type:'bar',
                    data:{
                        labels:[<?php echo implode(',',array_map(fn($i)=>"'س$i'",range(1,10))); ?>],
                        datasets:[{
                            label:'نمره',
                            data:scored,
                            backgroundColor:scored.map((v,i)=>i===9&&v>0?'#fc8181':'#90cdf4'),
                            borderRadius:5
                        }]
                    },
                    options:{
                        responsive:true,
                        plugins:{legend:{display:false}},
                        scales:{y:{min:0,max:3,ticks:{stepSize:1}}}
                    }
                });
            }
            document.addEventListener('DOMContentLoaded',init);
        })();
        </script>
        <?php
        return ob_get_clean();
    }
}
\PsychAssessments\Core\Registry::register( new EPDS() );


// ============================================================

/**
 * DERS: Difficulties in Emotion Regulation Scale
 *
 * - Gratz, K.L. & Roemer, L. (2004).
 *   Multidimensional assessment of emotion regulation and dysregulation:
 *   Development, factor structure, and initial validation of the DERS.
 *   Journal of Psychopathology and Behavioral Assessment, 26(1), 41–54.
 * - Open Access کامل
 * - هنجاریابی ایران: حسنی (۱۳۸۹) | Khanzadeh et al. (2012)
 *
 * ساختار:
 *   - ۳۶ سوال، مقیاس ۱–۵ (تقریباً هیچ‌وقت تا تقریباً همیشه)
 *   - سوالات معکوس: ۱، ۲، ۶، ۷، ۸، ۱۰، ۱۷، ۲۰، ۲۲، ۲۴، ۳۴
 *   - ۶ زیرمقیاس: عدم پذیرش | اهداف | تکانه | آگاهی | راهبردها | وضوح
 *   - محدوده کل: ۳۶–۱۸۰
 */
class DERS extends \PsychAssessments\Core\BaseTest {

    public function get_id(): string        { return 'ders'; }
    public function get_category(): string      { return 'mental_health'; }
    public function get_name(): string      { return 'تنظیم هیجان DERS'; }
    public function get_description(): string {
        return 'مقیاس دشواری در تنظیم هیجان — ۳۶ سوال، ۶ زیرمقیاس، Gratz & Roemer (2004)';
    }
    public function get_version(): string   { return '1.0.0'; }
    // باگ #M1: صریحاً تعریف می‌شود — DERS در render_report از canvas/Chart.js radar استفاده می‌کند
    public function needs_chart(): bool     { return true; }

    public function get_scale(): array {
        return [
            'min'    => 1,
            'max'    => 5,
            'labels' => [
                1 => 'تقریباً هیچ‌وقت (۰–۱۰٪)',
                2 => 'گاهی اوقات (۱۱–۳۵٪)',
                3 => 'حدود نیمی از اوقات (۳۶–۶۵٪)',
                4 => 'اغلب اوقات (۶۶–۹۰٪)',
                5 => 'تقریباً همیشه (۹۱–۱۰۰٪)',
            ],
        ];
    }

    private function reversed(): array {
        return [1, 2, 6, 7, 8, 10, 17, 20, 22, 24, 34];
    }

    private function subscales(): array {
        return [
            'nonacceptance' => [11, 12, 21, 23, 25, 29],
            'goals'         => [13, 18, 20, 26, 33],
            'impulse'       => [3, 14, 19, 24, 27, 32],
            'awareness'     => [2, 6, 8, 10, 17, 34],
            'strategies'    => [15, 16, 22, 28, 30, 31, 35, 36],
            'clarity'       => [1, 4, 5, 7, 9],
        ];
    }

    public function get_questions(): array {
        return [
            1  => 'وقتی ناراحت هستم، از احساساتم آگاه می‌شوم',
            2  => 'وقتی ناراحت هستم، به احساساتم توجه دارم',
            3  => 'وقتی ناراحت هستم، در تمرکز بر کارهای دیگر مشکل دارم',
            4  => 'وقتی ناراحت هستم، در کنترل رفتارم مشکل ندارم',
            5  => 'وقتی ناراحت هستم، به آسانی احساساتم را می‌شناسم',
            6  => 'وقتی ناراحت هستم، به احساساتم توجه می‌کنم',
            7  => 'وقتی ناراحت هستم، می‌دانم دقیقاً چه احساسی دارم',
            8  => 'وقتی ناراحت هستم، به احساساتم اهمیت می‌دهم',
            9  => 'وقتی ناراحت هستم، در کنترل رفتارم مشکل دارم',
            10 => 'وقتی ناراحت هستم، می‌توانم بر چیزی که باید انجام دهم تمرکز کنم',
            11 => 'وقتی ناراحت هستم، در مورد احساساتم با خودم بد رفتار می‌کنم',
            12 => 'وقتی ناراحت هستم، از خودم خجالت می‌کشم',
            13 => 'وقتی ناراحت هستم، در انجام کارها مشکل دارم',
            14 => 'وقتی ناراحت هستم، کنترل رفتارم را از دست می‌دهم',
            15 => 'وقتی ناراحت هستم، باور دارم که اگر آن را بروز دهم احساس بدتری خواهم داشت',
            16 => 'وقتی ناراحت هستم، باور دارم که در این حالت احساس ضعف می‌کنم',
            17 => 'وقتی ناراحت هستم، سعی می‌کنم فکرم را جای دیگری ببرم',
            18 => 'وقتی ناراحت هستم، در تمرکز بر اهدافم مشکل دارم',
            19 => 'وقتی ناراحت هستم، احساس می‌کنم کنترل ندارم',
            20 => 'وقتی ناراحت هستم، هنوز هم می‌توانم به کارها بپردازم',
            21 => 'وقتی ناراحت هستم، از خودم شرم می‌کشم',
            22 => 'وقتی ناراحت هستم، راه‌هایی برای بهتر احساس کردن پیدا می‌کنم',
            23 => 'وقتی ناراحت هستم، احساس می‌کنم ضعیف هستم',
            24 => 'وقتی ناراحت هستم، رفتارم را کنترل می‌کنم',
            25 => 'وقتی ناراحت هستم، از احساساتم خجالت می‌کشم',
            26 => 'وقتی ناراحت هستم، در انجام هر کاری مشکل دارم',
            27 => 'وقتی ناراحت هستم، احساس می‌کنم دیوانه می‌شوم',
            28 => 'وقتی ناراحت هستم، باور دارم که هیچ راهی برای احساس بهتر وجود ندارد',
            29 => 'وقتی ناراحت هستم، تحریک‌پذیر می‌شوم',
            30 => 'وقتی ناراحت هستم، باور دارم که باید مدت زمان زیادی در حالت بدی بمانم',
            31 => 'وقتی ناراحت هستم، شروع به احساس بدی نسبت به خودم می‌کنم',
            32 => 'وقتی ناراحت هستم، احساس از دست دادن کنترل می‌کنم',
            33 => 'وقتی ناراحت هستم، در فکر کردن به هر چیز دیگری مشکل دارم',
            34 => 'وقتی ناراحت هستم، وقت می‌گیرم تا بفهمم واقعاً چه احساسی دارم',
            35 => 'وقتی ناراحت هستم، مدت زمان زیادی طول می‌کشد تا احساس بهتری داشته باشم',
            36 => 'وقتی ناراحت هستم، احساساتم غیرقابل کنترل به نظر می‌رسند',
        ];
    }

    public function get_schema_labels(): array {
        return [
            'nonacceptance' => 'عدم پذیرش هیجان',
            'goals'         => 'دشواری در انجام رفتار هدفمند',
            'impulse'       => 'دشواری کنترل تکانه',
            'awareness'     => 'فقدان آگاهی هیجانی',
            'strategies'    => 'دسترسی محدود به راهبردها',
            'clarity'       => 'فقدان وضوح هیجانی',
        ];
    }

    public function get_domain_labels(): array { return []; }

    private function get_severity( int $total ): array {
        // نقاط برش بر اساس Gratz & Roemer (2004) + Khanzadeh et al. (2012) برای نمونه ایرانی
        return \PsychAssessments\Core\Scoring::severity( (float)$total, [
            ['max'=>80,  'label'=>'پایین',    'label_en'=>'Low',      'color'=>'#276749','bg'=>'#f0fff4','clinical'=>'دشواری تنظیم هیجان در محدوده طبیعی',                        'range'=>'36–80'],
            ['max'=>110, 'label'=>'متوسط',   'label_en'=>'Moderate', 'color'=>'#d69e2e','bg'=>'#fffff0','clinical'=>'دشواری متوسط — مشاوره مهارت‌های هیجانی مفید است',          'range'=>'81–110'],
            ['max'=>140, 'label'=>'بالا',     'label_en'=>'High',     'color'=>'#c05621','bg'=>'#fffaf0','clinical'=>'دشواری قابل توجه — درمان تنظیم هیجان توصیه می‌شود',       'range'=>'111–140'],
            [            'label'=>'خیلی بالا','label_en'=>'Very High','color'=>'#9b2c2c','bg'=>'#fff5f5','clinical'=>'دشواری شدید — مداخله تخصصی (مثل DBT) ضروری است',         'range'=>'141–180'],
        ]);
    }

    private function subscale_severity( int $score, string $key ): array {
        // نقاط برش تقریبی برای هر زیرمقیاس
        $max = [ 'nonacceptance'=>30, 'goals'=>25, 'impulse'=>30, 'awareness'=>30, 'strategies'=>40, 'clarity'=>25 ];
        $m   = $max[$key] ?? 30;
        $pct = $score / $m;
        if ( $pct < 0.40 ) return ['label'=>'پایین','color'=>'#276749','bg'=>'#f0fff4'];
        if ( $pct < 0.60 ) return ['label'=>'متوسط','color'=>'#d69e2e','bg'=>'#fffff0'];
        if ( $pct < 0.80 ) return ['label'=>'بالا','color'=>'#c05621','bg'=>'#fffaf0'];
        return                    ['label'=>'خیلی بالا','color'=>'#9b2c2c','bg'=>'#fff5f5'];
    }

    public function calculate( array $answers ): array {
        $answers = \PsychAssessments\Core\Scoring::sanitize_answers( $answers );
        $reversed   = $this->reversed();
        $subscales  = $this->subscales();
        $total      = 0;
        $sub_scores = [];

        foreach ( $subscales as $key => $qs ) {
            $sub = 0;
            foreach ( $qs as $q ) {
                $v = min( 5, max( 1, (int)( $answers[$q] ?? 1 ) ) );
                $sub += in_array($q, $reversed) ? (6 - $v) : $v;
            }
            $sub_scores[$key] = $sub;
        }

        // باگ I: total از array_sum محاسبه می‌شود نه loop مجزا — جلوگیری از divergence
        // در صورت تغییر subscale‌ها در آینده، total همیشه با sub_scores هماهنگ می‌ماند.
        $total = array_sum( $sub_scores );

        return [
            'schema_scores' => array_map('floatval', $sub_scores),
            'sub_scores'    => $sub_scores,
            'total'         => $total,
            'avg_total'     => (float)$total,
        ] + $sub_scores;
    }

    public function render_report( array $scores, object $session ): string {
        $completed = pa_to_jalali( $session->completed_at ?? '', 'Y/m/d ساعت H:i' );
        $total     = (int)$scores['total'];
        $sev       = $this->get_severity( $total );
        $labels    = $this->get_schema_labels();
        $subs      = $scores['schema_scores'] ?? [];

        $sub_descs = [
            'nonacceptance' => 'واکنش‌های هیجانی منفی نسبت به احساسات ناخوشایند خود',
            'goals'         => 'مشکل در تمرکز و انجام کارها هنگام ناراحتی',
            'impulse'       => 'مشکل در کنترل رفتار هنگام تجربه هیجانات منفی',
            'awareness'     => 'توجه و آگاهی نسبت به سیگنال‌های هیجانی',
            'strategies'    => 'باور به فقدان راهبردهای مؤثر برای تنظیم هیجان',
            'clarity'       => 'توانایی شناسایی و توصیف دقیق احساسات',
        ];
        $sub_max = [
            'nonacceptance'=>30,'goals'=>25,'impulse'=>30,'awareness'=>30,'strategies'=>40,'clarity'=>25
        ];

        ob_start(); ?>
        <div style="display:flex;gap:12px;flex-wrap:wrap;margin-bottom:24px;">
            <?php foreach ([
                ['#ebf8ff','#2b6cb0','📞','شماره تماس', $session->client_phone ?? $session->patient_phone ?? '---'],
                ['#f0fff4','#2f855a','✅','تاریخ تکمیل', $completed],
                [$sev['bg'],$sev['color'],'🎭','نمره کل DERS', $total . ' از ۱۸۰'],
                [$sev['bg'],$sev['color'],'🏷','سطح دشواری', $sev['label'] . ' (' . $sev['label_en'] . ')'],
            ] as $c) echo \PsychAssessments\Core\ReportHTML::summary_card($c[0],$c[1],$c[2],$c[3],$c[4]); ?>
        </div>

        <div style="background:<?php echo esc_attr($sev['bg']); ?>;border:2px solid <?php echo esc_attr($sev['color']); ?>;border-radius:10px;padding:22px;margin-bottom:24px;">
            <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;">
                <div>
                    <h3 style="margin:0 0 6px;color:<?php echo esc_attr($sev['color']); ?>;">نمره کل: <?php echo esc_html((int)$total); ?> از ۱۸۰</h3>
                    <p style="margin:0;font-size:13px;color:#4a5568;"><?php echo esc_html($sev['clinical']); ?></p>
                </div>
                <?php echo \PsychAssessments\Core\ReportHTML::score_badge((float)$total, $sev); ?>
            </div>
            <div style="margin-top:14px;background:#edf2f7;border-radius:6px;height:10px;overflow:hidden;">
                <div style="background:<?php echo esc_attr($sev['color']); ?>;height:100%;width:<?php echo \PsychAssessments\Core\Scoring::percent($total,36,180); ?>%;border-radius:6px;"></div>
            </div>
            <div style="display:flex;justify-content:space-between;font-size:10px;color:#a0aec0;margin-top:4px;">
                <span>۳۶</span>
                <span style="color:#d69e2e;">۸۱ متوسط</span>
                <span style="color:#c05621;">۱۱۱ بالا</span>
                <span style="color:#9b2c2c;">۱۴۱ خیلی بالا</span>
                <span>۱۸۰</span>
            </div>
        </div>

        <?php echo \PsychAssessments\Core\ReportHTML::section_header('پروفایل ۶ زیرمقیاس تنظیم هیجان'); ?>
        <div style="background:#fff;padding:20px;border-radius:10px;box-shadow:0 2px 10px rgba(0,0,0,0.08);margin-bottom:24px;">
            <canvas id="pa-ders-radar" style="max-height:280px;"></canvas>
        </div>

        <?php foreach ($subs as $key => $val):
            $sub_sev = $this->subscale_severity((int)$val, $key);
            $mx = $sub_max[$key] ?? 30; ?>
        <div style="background:#fff;border:1px solid #e2e8f0;border-radius:10px;padding:16px;margin-bottom:12px;border-right:4px solid <?php echo esc_attr($sub_sev['color']); ?>;">
            <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;margin-bottom:10px;">
                <div>
                    <strong style="font-size:14px;color:<?php echo esc_attr($sub_sev['color']); ?>;"><?php echo esc_html($labels[$key]); ?></strong>
                    <p style="margin:4px 0 0;font-size:12px;color:#718096;"><?php echo esc_html($sub_descs[$key]); ?></p>
                </div>
                <span style="background:<?php echo esc_attr($sub_sev['bg']); ?>;color:<?php echo esc_attr($sub_sev['color']); ?>;padding:4px 14px;border-radius:20px;font-size:12px;font-weight:bold;">
                    <?php echo (int)$val; ?> از <?php echo $mx; ?> — <?php echo esc_html($sub_sev['label']); ?>
                </span>
            </div>
            <div style="background:#edf2f7;border-radius:6px;height:8px;overflow:hidden;">
                <?php
                // باگ O: min هر subscale برابر تعداد سوالاتش است (min per-question=1)
                // بدون min، نمره حداقل به صورت bar خالی نشان داده می‌شد
                $sub_min_map = ['nonacceptance'=>6,'goals'=>5,'impulse'=>6,'awareness'=>6,'strategies'=>8,'clarity'=>5];
                $sub_min = $sub_min_map[$key] ?? 1;
                ?>
                <div style="background:<?php echo esc_attr($sub_sev['color']); ?>;height:100%;width:<?php echo \PsychAssessments\Core\Scoring::percent((float)$val, (float)$sub_min, (float)$mx); ?>%;border-radius:6px;"></div>
            </div>
        </div>
        <?php endforeach; ?>

        <div style="background:#fff;border:1px solid #e2e8f0;border-radius:8px;padding:14px 20px;font-size:11px;color:#a0aec0;line-height:1.8;margin-top:10px;">
            <b style="color:#4a5568;">منبع:</b> Gratz & Roemer (2004). Journal of Psychopathology and Behavioral Assessment, 26(1), 41–54.<br>
            هنجاریابی ایران: Khanzadeh et al. (2012). International Journal of Psychological Studies, 4(1).<br>
            <b style="color:#c53030;">⚠️</b> ابزار کمک‌بالینی — مبنای تشخیص رسمی نیست.
        </div>

        <script>
        (function(){
            function init(){
                if(typeof Chart==='undefined'){setTimeout(init,100);return;}
                var ctx=document.getElementById('pa-ders-radar');
                if(!ctx)return;
                var maxVals=[30,25,30,30,40,25];
                var vals=[
                    <?php
                    $order=['nonacceptance','goals','impulse','awareness','strategies','clarity'];
                    echo implode(',', array_map(fn($k)=>(int)($subs[$k]??0), $order));
                    ?>
                ];
                // normalize to 0-100 percentage
                var norm=vals.map((v,i)=>Math.round(v/maxVals[i]*100));
                new Chart(ctx.getContext('2d'),{
                    type:'radar',
                    data:{
                        labels:['عدم پذیرش','اهداف','تکانه','آگاهی','راهبردها','وضوح'],
                        datasets:[{
                            label:'درصد',
                            data:norm,
                            backgroundColor:'rgba(252,129,129,0.2)',
                            borderColor:'#fc8181',
                            pointBackgroundColor:'#fc8181',
                            pointRadius:4
                        }]
                    },
                    options:{
                        responsive:true,
                        scales:{r:{min:0,max:100,ticks:{stepSize:25,callback:v=>v+'%'}}},
                        plugins:{legend:{display:false}}
                    }
                });
            }
            document.addEventListener('DOMContentLoaded',init);
        })();
        </script>
        <?php
        return ob_get_clean();
    }
}
\PsychAssessments\Core\Registry::register( new DERS() );
