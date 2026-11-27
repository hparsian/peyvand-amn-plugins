<?php
namespace PsychAssessments\Tests;
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * SCL-90-R: Symptom Checklist-90-Revised
 *
 * - Derogatis, L.R. (1994). SCL-90-R: Administration, scoring and procedures manual (3rd ed.).
 * - هنجاریابی ایران: میرزایی، ۱۳۸۰
 * - ۹۰ سوال، ۹ بعد + ۳ شاخص کلی
 * - مقیاس: ۰ (اصلاً) تا ۴ (خیلی زیاد)
 */
class SCL90R extends \PsychAssessments\Core\BaseTest {

    public function get_id(): string        { return 'scl90r'; }
    public function get_category(): string      { return 'mental_health'; }
    public function get_name(): string      { return 'چک‌لیست نشانه‌های روانی (SCL-90-R)'; }
    public function needs_chart(): bool   { return true; }  // نمودار Chart.js

    public function get_description(): string { return 'پرسشنامه SCL-90-R — ۹۰ سوال، ۹ بعد روان‌شناختی + شاخص‌های کلی'; }
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

    public function get_questions(): array {
        return [
            1  => 'سردردهای مکرر',
            2  => 'عصبی بودن یا لرزش درونی',
            3  => 'افکار ناخواسته، کلمات، یا ایده‌هایی که از ذهنتان نمی‌رود',
            4  => 'ضعف یا سرگیجه',
            5  => 'از دست دادن علاقه جنسی یا لذت',
            6  => 'انتقاد از دیگران',
            7  => 'احساس که دیگران می‌توانند افکار شما را کنترل کنند',
            8  => 'احساس که دیگران مقصر اکثر مشکلات شما هستند',
            9  => 'مشکل در به خاطر سپردن چیزها',
            10 => 'بی‌توجهی یا سهل‌انگاری',
            11 => 'عصبانیت و ناراحتی به راحتی',
            12 => 'دردهای قلبی یا قفسه سینه',
            13 => 'ترس از فضاهای باز یا خیابان',
            14 => 'احساس کم‌انرژی یا کند بودن',
            15 => 'افکار درباره پایان دادن به زندگی',
            16 => 'شنیدن صداهایی که دیگران نمی‌شنوند',
            17 => 'لرزش',
            18 => 'احساس که نمی‌توان به اکثر مردم اعتماد کرد',
            19 => 'بی‌اشتهایی',
            20 => 'گریه کردن به راحتی',
            21 => 'خجالت یا ناراحتی در جمع جنس مخالف',
            22 => 'احساس دام افتادن یا محبوس بودن',
            23 => 'ترس ناگهانی بدون دلیل',
            24 => 'انفجارهای خشم که نمی‌توانید کنترل کنید',
            25 => 'ترس از خروج از خانه به تنهایی',
            26 => 'احساس سرزنش خود برای چیزها',
            27 => 'دردهای کمر',
            28 => 'احساس انجام ندادن کارها',
            29 => 'احساس تنهایی',
            30 => 'احساس افسردگی یا ناراحتی',
            31 => 'نگرانی بیش از حد درباره چیزها',
            32 => 'عدم علاقه به چیزها',
            33 => 'احساس ترس',
            34 => 'احساس آسیب‌پذیری یا حساسیت',
            35 => 'سایرین از اعمال یا افکار شما آگاه هستند',
            36 => 'احساس اینکه دیگران شما را درک نمی‌کنند یا همدردی ندارند',
            37 => 'احساس اینکه مردم دشمن‌خو هستند یا دوستتان ندارند',
            38 => 'باید همه چیز را خیلی آهسته انجام دهید تا اشتباه نکنید',
            39 => 'تپش قلب یا ضربان سریع قلب',
            40 => 'حالت تهوع یا ناراحتی معده',
            41 => 'احساس حقارت نسبت به دیگران',
            42 => 'دردهای عضلانی',
            43 => 'احساس اینکه دیگران شما را تحت نظر دارند یا درباره‌تان صحبت می‌کنند',
            44 => 'مشکل در به خواب رفتن',
            45 => 'باید کارها را دوباره چک کنید',
            46 => 'مشکل در تصمیم‌گیری',
            47 => 'ترس از سوار شدن اتوبوس، قطار یا مترو',
            48 => 'مشکل در نفس کشیدن',
            49 => 'گرما یا سرما ناگهانی',
            50 => 'باید از مکان‌های خاصی اجتناب کنید چون می‌ترسید',
            51 => 'ذهن خالی می‌شود',
            52 => 'بی‌حسی یا گزگز در بدن',
            53 => 'احساس اینکه گلو کارتان را ترک می‌کند',
            54 => 'احساس بدبینی نسبت به آینده',
            55 => 'مشکل در تمرکز',
            56 => 'احساس ضعف در اعضای بدن',
            57 => 'احساس تنش یا زود رنجی',
            58 => 'سنگینی در دست‌ها یا پاها',
            59 => 'افکار درباره مرگ یا مرگ در حال وقوع',
            60 => 'پر خوری',
            61 => 'احساس ناراحتی وقتی مردم نگاهتان می‌کنند یا درباره‌تان صحبت می‌کنند',
            62 => 'داشتن افکاری که متعلق به خودتان نیست',
            63 => 'تکانه‌های آسیب رساندن به دیگران',
            64 => 'بیدار شدن زودتر از موعد',
            65 => 'باید اعمال تکراری مثل لمس کردن، شمردن، یا شستشو انجام دهید',
            66 => 'خواب ناآرام',
            67 => 'تکانه‌هایی برای شکستن یا خرد کردن چیزها',
            68 => 'داشتن باورها یا ایده‌هایی که دیگران با آنها موافق نیستند',
            69 => 'خجالت شدید در محیط اجتماعی',
            70 => 'احساس ناراحتی در ازدحام مثل مغازه‌ها یا سینما',
            71 => 'احساس کند بودن در انجام کارها',
            72 => 'احساس وحشت یا دلهره',
            73 => 'ناراحتی در وقتی غذا می‌خورید یا در جمع می‌نوشید',
            74 => 'درگیر شدن مکرر در مشاجره',
            75 => 'عصبی بودن در تنها بودن',
            76 => 'دیگران ارزش دستاوردهای شما را نمی‌شناسند',
            77 => 'احساس تنهایی حتی در جمع دیگران',
            78 => 'ناراحتی شدید تا حدی که نشستن و آرام بودن سخت است',
            79 => 'احساس بی‌ارزشی',
            80 => 'احساس اینکه چیز بدی با شما اتفاق خواهد افتاد',
            81 => 'فریاد زدن یا پرتاب کردن چیزها',
            82 => 'ترس از غش کردن در جمع',
            83 => 'احساس اینکه مردم اگر اجازه دهید از شما سوء استفاده می‌کنند',
            84 => 'داشتن افکار جنسی که خیلی آزاردهنده هستند',
            85 => 'ایده اینکه باید برای گناهانتان مجازات شوید',
            86 => 'افکار و تصاویری که ترسناک هستند',
            87 => 'ایده اینکه چیزی جدی در بدنتان غلط است',
            88 => 'هرگز احساس نزدیکی به دیگری ندارید',
            89 => 'احساس گناه',
            90 => 'ایده اینکه چیزی در ذهنتان غلط است',
        ];
    }

    private function subscales(): array {
        return [
            'som' => [1,4,12,27,40,42,48,49,52,53,56,58],
            'oc'  => [3,9,10,28,38,45,46,51,55,65],
            'is'  => [6,21,34,36,37,41,61,69,73,],
            'dep' => [5,14,15,20,22,26,29,30,31,32,54,71,79],
            'anx' => [2,17,23,33,39,57,72,78,80,86],
            'hos' => [11,24,63,67,74,81],
            'pho' => [13,25,47,50,70,75,82],
            'par' => [8,18,43,68,76,83],
            // فیکس: q77 ("احساس تنهایی حتی در جمع دیگران") جا افتاده بود —
            // زیرمقیاس روان‌پریشی استاندارد Derogatis شامل ۱۰ سوال است، نه ۹.
            'psy' => [7,16,35,62,77,84,85,87,88,90],
        ];
    }

    public function get_schema_labels(): array {
        return [
            'som' => 'شکایات جسمانی',
            'oc'  => 'وسواس — اجبار',
            'is'  => 'حساسیت میان‌فردی',
            'dep' => 'افسردگی',
            'anx' => 'اضطراب',
            'hos' => 'خصومت',
            'pho' => 'اضطراب فوبیک',
            'par' => 'ایده‌پردازی پارانوئید',
            'psy' => 'روان‌پریشی',
        ];
    }

    public function get_domain_labels(): array { return []; }

    private function severity( float $score ): array {
        if ( $score <= 0.5 ) return ['label'=>'طبیعی',  'color'=>'#2f855a','bg'=>'#f0fff4','clinical'=>'در محدوده طبیعی'];
        if ( $score <= 1.0 ) return ['label'=>'خفیف',   'color'=>'#d69e2e','bg'=>'#fffff0','clinical'=>'نشانه‌های خفیف'];
        if ( $score <= 2.0 ) return ['label'=>'متوسط',  'color'=>'#c05621','bg'=>'#fff5f0','clinical'=>'نیاز به پیگیری بالینی'];
        return                       ['label'=>'شدید',   'color'=>'#9b2c2c','bg'=>'#fff5f5','clinical'=>'نیاز به مداخله فوری'];
    }

    public function calculate( array $answers ): array {
        $answers = \PsychAssessments\Core\Scoring::sanitize_answers( $answers );
        $subscales = $this->subscales();
        $schema_scores = [];
        foreach ( $subscales as $key => $qs ) {
            $schema_scores[$key] = \PsychAssessments\Core\Scoring::subscale_avg( $qs, $answers, 0, 4 );
        }

        // GSI: Global Severity Index — میانگین همه ۹۰ سوال
        $total_sum = 0;
        for ( $i = 1; $i <= 90; $i++ ) {
            $total_sum += $answers[$i] ?? 0;
        }
        $gsi = round( $total_sum / 90, 2 );

        // PST: Positive Symptom Total — تعداد سوالات با نمره > 0
        $pst = 0;
        for ( $i = 1; $i <= 90; $i++ ) {
            if ( ($answers[$i] ?? 0) > 0 ) $pst++;
        }

        // PSDI: Positive Symptom Distress Index
        $psdi = $pst > 0 ? round( $total_sum / $pst, 2 ) : 0;

        return [
            'schema_scores' => $schema_scores,
            'gsi'           => $gsi,
            'pst'           => $pst,
            'psdi'          => $psdi,
            'avg_total'     => $gsi,
        ];
    }

    public function render_report( array $scores, object $session ): string {
        $labels     = $this->get_schema_labels();
        $completed  = pa_to_jalali( $session->completed_at, 'Y/m/d ساعت H:i' );
        $gsi_sev    = $this->severity( $scores['gsi'] );

        $bar_labels = [];
        $bar_values = [];
        foreach ( $scores['schema_scores'] as $key => $val ) {
            $bar_labels[] = $labels[$key];
            $bar_values[] = $val;
        }

        $gsi_tone = \PsychAssessments\Core\ReportHTML::tone( $gsi_sev['color'] );

        ob_start(); ?>
        <div class="psy-report-row">
            <?php foreach ([
                ['#e9d8fd','#553c9a','👤','نام',$session->client_name ?? $session->patient_name ?? '---'],
                ['#ebf8ff','#2b6cb0','📞','شماره تماس',$session->client_phone ?? $session->patient_phone ?? '---'],
                ['#f0fff4','#2f855a','✅','تاریخ تکمیل',$completed],
                [$gsi_sev['bg'],$gsi_sev['color'],'📊','GSI (شاخص کلی)',number_format($scores['gsi'],2)],
                ['#faf5ff','#6b46c1','🔢','PST (تعداد نشانه‌ها)',$scores['pst'].' از ۹۰'],
                ['#fff5f0','#c05621','📈','PSDI (شدت نشانه‌ها)',number_format($scores['psdi'],2)],
            ] as $c) echo \PsychAssessments\Core\ReportHTML::summary_card($c[0],$c[1],$c[2],$c[3],$c[4]); ?>
        </div>

        <div class="psy-report-card--tint <?php echo esc_attr( $gsi_tone ); ?>">
            <h3 class="psy-report-hero__title">
                شاخص کلی شدت (GSI): <?php echo esc_html(number_format($scores['gsi'],2)); ?>
            </h3>
            <p class="psy-report-hero__text"><?php echo esc_html($gsi_sev['clinical']); ?></p>
        </div>

        <?php echo \PsychAssessments\Core\ReportHTML::section_header('پروفایل ۹ بعد بالینی'); ?>
        <div class="psy-report-card psy-report-card--panel psy-report-chart-panel">
            <canvas id="pa-scl-bar" class="psy-report-chart psy-report-chart--xl"></canvas>
        </div>

        <?php echo \PsychAssessments\Core\ReportHTML::section_header('جزئیات ابعاد'); ?>
        <?php
        $descs = [
            'som' => 'ناراحتی ناشی از شکایات جسمانی — دردهای قلبی، سردرد، اختلالات گوارشی',
            'oc'  => 'افکار، تکانه‌ها و اعمال ناخواسته‌ای که تغییرشان دشوار است',
            'is'  => 'احساس حقارت و ناراحتی در مقایسه با دیگران در محیط اجتماعی',
            'dep' => 'طیف کامل علائم افسردگی — بی‌انگیزگی، ناامیدی، غم',
            'anx' => 'علائم عصبی و جسمی اضطراب — تپش قلب، تنش، وحشت',
            'hos' => 'پرخاشگری، تحریک‌پذیری و خشم',
            'pho' => 'ترس‌های مرضی پایدار از اشخاص، مکان‌ها یا موقعیت‌ها',
            'par' => 'تفکر پارانوئید — شک، خصومت، ترس از دست دادن استقلال',
            'psy' => 'طیف روان‌پریشی — انزوا، اختلال تفکر، توهم',
        ];
        foreach ( $scores['schema_scores'] as $key => $val ):
            $sev = $this->severity($val);
        ?>
        <?php echo \PsychAssessments\Core\ReportHTML::clinical_card(
            $labels[$key], $key, $val, $sev,
            $descs[$key],
            'میانگین بالاتر از ۱.۰ نیاز به بررسی بالینی دارد.'
        ); ?>
        <?php endforeach; ?>

        <div class="psy-report-refs psy-report-refs--muted">
            <b>منبع:</b> Derogatis, L.R. (1994). SCL-90-R Manual (3rd ed.). | هنجاریابی ایران: میرزایی (۱۳۸۰).<br>
            <b class="psy-tone--danger psy-text">⚠️</b> ابزار کمک‌بالینی — مبنای تشخیص رسمی نیست.
        </div>

        <script>
        (function(){
            var labels=<?php echo wp_json_encode($bar_labels); ?>;
            var values=<?php echo wp_json_encode($bar_values); ?>;
            function init(){
                if(typeof Chart==='undefined'){setTimeout(init,100);return;}
                var ctx=document.getElementById('pa-scl-bar');
                if(!ctx)return;
                new Chart(ctx.getContext('2d'),{
                    type:'bar',
                    data:{labels:labels,datasets:[{
                        data:values,
                        backgroundColor:values.map(function(v){return v<=0.5?'#68d391':v<=1?'#f6e05e':v<=2?'#f6ad55':'#fc8181';}),
                        borderRadius:5
                    }]},
                    options:{responsive:true,plugins:{legend:{display:false}},scales:{y:{min:0,max:4,ticks:{stepSize:1}}}}
                });
            }
            document.addEventListener('DOMContentLoaded',init);
        })();
        </script>
        <?php
        return ob_get_clean();
    }
}
\PsychAssessments\Core\Registry::register( new SCL90R() );
