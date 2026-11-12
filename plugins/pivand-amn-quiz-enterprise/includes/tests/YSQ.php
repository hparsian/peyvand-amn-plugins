<?php
namespace PsychAssessments\Tests;
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * آزمون طرحواره یانگ — نسخه کوتاه (YSQ-S2)
 *
 * مبانی علمی:
 * - Young, J.E. (1998). Young Schema Questionnaire Short Form (YSQ-S2)
 * - Young et al. (2003). Schema Therapy: A Practitioner's Guide. Guilford Press.
 * - Sadeghi et al. (2017). هنجاریابی YSQ در جمعیت ایرانی
 * - Rijkeboer & van den Bergh (2006). cut-off ≥ 3.0
 *
 * فیکس نام‌گذاری: این نسخه در واقع YSQ-S2 است (۷۵ سوال، ۱۵ طرحواره)، نه YSQ-S3.
 * نسخهٔ واقعی YSQ-S3 (Young, 2005) شامل ۹۰ سوال و ۱۸ طرحواره است — سه طرحوارهٔ
 * اضافه‌شده در S3 (تأییدجویی/Approval-Seeking، منفی‌نگری/Negativity-Pessimism،
 * و تنبیه‌گری/Punitiveness) در این پیاده‌سازی وجود ندارند. محاسبات و نمرات
 * ۱۵ طرحوارهٔ موجود صحیح‌اند؛ فقط نام و مستندات اصلاح شد تا واقعیت را منعکس کند.
 */
class YSQ extends \PsychAssessments\Core\BaseTest {

    public function get_id(): string { return 'ysq'; }
    public function get_category(): string      { return 'personality'; }
    public function get_name(): string { return 'آزمون طرحواره یانگ (YSQ-S2)'; }
    public function get_description(): string { return 'پرسشنامه طرحواره یانگ — نسخه کوتاه (YSQ-S2) — ۷۵ سوال، ۱۵ طرحواره، ۵ حوزه'; }
    public function get_version(): string { return '3.1.1'; }
    // باگ #YSQ2: صریحاً تعریف می‌شود — YSQ از bar chart و radar chart استفاده می‌کند
    public function needs_chart(): bool   { return true; }

    public function get_scale(): array {
        return [
            'min'    => 1,
            'max'    => 6,
            'labels' => [
                1 => 'کاملاً غلط',
                2 => 'بیشتر غلط',
                3 => 'تا حدی غلط',
                4 => 'تا حدی درست',
                5 => 'بیشتر درست',
                6 => 'کاملاً درست',
            ],
        ];
    }

    public function get_questions(): array {
        return [
            1  => "اغلب کسی را نداشتم که از من حمایت کند، حرف‌هایش را با من بزند و عمیقاً نگران اتفاقاتی باشد که برایم می‌افتد.",
            2  => "به‌طور کلی، کسی نبوده که به من عاطفه، محبت و صداقت نشان دهد.",
            3  => "در بیشتر اوقات زندگی، این احساس به من دست نداد که برای فرد دیگری، شخص ویژه و ممتازی به‌شمار می‌روم.",
            4  => "در غالب اوقات کسی را نداشتم که واقعاً به دل به من گوش دهد، مرا بفهمد یا این احساس‌ها و نیازهای واقعی مرا درک کند.",
            5  => "وقتی که نمی‌دانستم کاری را چگونه انجام دهم، به‌ندرت شخصی پیدا می‌شد که مرا نصیحت و راهنمایی کند.",
            6  => "من به افراد نزدیک خودم خیلی وابسته‌ام، چون می‌ترسم مرا ترک کنند.",
            7  => "آن‌قدر به دیگران نیازمندم که نگران از دست دادن آنها هستم.",
            8  => "نگرانم مبادا نزدیکانم مرا ترک کنند یا مرا به حال خود رها کنند.",
            9  => "وقتی احساس می‌کنم کسی برایم مهم است که از من دوری می‌کند، مأیوس می‌شوم.",
            10 => "برخی اوقات آن‌قدر نگران آن هستم که دیگران مرا ترک کنند که آنها را از خودم دور می‌کنم.",
            11 => "احساس می‌کنم مردم از من سوءجویی می‌کنند.",
            12 => "احساس می‌کنم باید در حضور دیگران از خودم محافظت کنم، چون در غیر این صورت عمداً به من آسیب می‌زنند.",
            13 => "دیگران دیر یا زود به من خیانت می‌کنند.",
            14 => "نسبت به انگیزه‌های دیگران، سوءظن شدید دارم.",
            15 => "معمولاً به‌طور جدی به انگیزه‌های نهانی مردم فکر می‌کنم.",
            16 => "وصلهٔ ناجور اجتماع هستم.",
            17 => "اساساً با دیگران خیلی فرق دارم.",
            18 => "نمی‌توانم به فردی یا گروهی تعلق خاطر داشته باشم، انسان گوشه‌گیری هستم.",
            19 => "احساس می‌کنم با مردم بیگانه‌ام.",
            20 => "همیشه احساس می‌کنم در جمع، جایی ندارم.",
            21 => "مردانی یا زنانی که دوستشان دارم، وقتی عیب‌های مرا ببینند، نمی‌توانند دوستم داشته باشند.",
            22 => "اگر کسی واقعاً مرا بشناسد، مایل نیست با من رابطهٔ صمیمی برقرار کند.",
            23 => "لیاقت عشق، توجه و احترام دیگران را ندارم.",
            24 => "احساس می‌کنم هیچ‌کس مرا دوست ندارد.",
            25 => "در بسیاری از جنبه‌ها، چنان شخصیت پر از عیب و ایرادی دارم که نمی‌توانم در کنار دیگران راحت باشم.",
            26 => "تقریباً هیچ کاری را نمی‌توانم به خوبی دیگران انجام دهم.",
            27 => "هر زمان پیشرفتی در کارم به‌وجود می‌آید، احساس بی‌کفایتی می‌کنم.",
            28 => "بیشتر مردم در حوزه‌های شغلی و تحصیلی از من تواناترند.",
            29 => "نمی‌توانم مانند اغلب مردم در کارهایم با استعداد باشم.",
            30 => "در کار یا تحصیل، مثل بقیه باهوش نیستم.",
            31 => "احساس می‌کنم نمی‌توانم به‌تنهایی از پس کارهای زندگی روزمره‌ام برآیم.",
            32 => "فکر می‌کنم در انجام کارهای روزمره، آدم وابسته‌ای هستم.",
            33 => "عقل درست و حسابی ندارم.",
            34 => "اصلاً به قضاوت‌های خودم در موقعیت‌های روزمره، اعتماد ندارم.",
            35 => "احساس می‌کنم نمی‌توانم به تنهایی گلیم خودم را از آب بیرون بکشم.",
            36 => "نمی‌توانم از شر این احساس رها شوم که مبادا اتفاق بدی بیفتد.",
            37 => "احساس می‌کنم هر لحظه ممکن است یک فاجعهٔ طبیعی، جنایی، حقوقی یا پزشکی برایم اتفاق بیفتد.",
            38 => "آدم ترسویی هستم.",
            39 => "می‌ترسم تمام سرمایهٔ خود را از دست بدهم و بیچاره شوم.",
            40 => "اغلب نگرانم از این‌که دچار سکتهٔ قلبی یا بیماری سرطان بشوم، حتی وقتی دلایل پزشکی کمی برای این نگرانی وجود دارد.",
            41 => "قادر نیستم از والدینم جدا شوم، کاری که هم‌سن‌وسال‌هایم انجام می‌دهند.",
            42 => "من و والدینم تمایل داریم خود را بیش از حد در زندگی و مسایل یکدیگر درگیر کنیم.",
            43 => "اگر من و والدینم، مسایل جزئی و خصوصی خود را از یکدیگر پنهان کنیم، احساس گناه یا خیانت می‌کنیم.",
            44 => "اغلب احساس می‌کنم که انگار سایهٔ سنگین والدینم دائم بر سر من است، نمی‌توانم زندگی جداگانه‌ای برای خودم داشته باشم.",
            45 => "هویت من چنان با هویت والدینم و همسرم گره خورده است که واقعاً نمی‌دانم کی هستم و چی می‌خواهم.",
            46 => "در کار دیگران دخالت نمی‌کنم، چون از عواقب دخالت در کار آنها می‌ترسم.",
            47 => "احساس می‌کنم چاره‌ای ندارم جز این‌که به خواسته‌های دیگران تن بدهم، چون در غیر این صورت، یا مرا ترک می‌کنند یا در صدد تلافی برمی‌آیند.",
            48 => "در روابطم، به دیگران اجازه می‌دهم که بر من مسلط شوند.",
            49 => "همیشه به دیگران اجازه دادم به‌جای من تصمیم بگیرند، در نتیجه واقعاً نمی‌دانم چی می‌خواهم.",
            50 => "خیلی برایم مشکل است که از دیگران تقاضا کنم حقوقم را رعایت و احساساتم را درک کنند.",
            51 => "در نهایت، کار مراقبت از نزدیکانم روی دوش من است.",
            52 => "آدم خوبی هستم، چون بیش از آن‌که به فکر خودم باشم، به فکر دیگرانم.",
            53 => "مهم نیست که چه‌قدر سرم شلوغ است، من همیشه می‌توانم وقتی را برای دیگران کنار بگذارم.",
            54 => "همیشه به درد دل دیگران گوش می‌دهم.",
            55 => "اطرافیان معتقدند که من برای رفع نیازهای دیگران خیلی تلاش می‌کنم، ولی برای خودم کاری انجام نمی‌دهم.",
            56 => "خجالت می‌کشم که احساسات مثبتم (مثل محبت و توجه) را به دیگران بروز بدهم.",
            57 => "از این‌که احساساتم را به دیگران ابراز کنم، خیلی شرمسار می‌شوم.",
            58 => "برای من خیلی سخت است که صمیمی و خودمانی رفتار کنم.",
            59 => "آن‌قدر خودم را کنترل می‌کنم که مردم فکر می‌کنند آدم بی‌احساسی هستم.",
            60 => "از نظر دیگران من عصبی و ناراحتم.",
            61 => "باید در هر کاری که انجام می‌دهم بهترین باشم؛ نمی‌توانم بپذیرم که نفر دوم باشم.",
            62 => "سعی می‌کنم نهایت تلاش خودم را بکنم؛ خوب بودن نسبی کار، هیچ‌گاه مرا راضی نمی‌کند.",
            63 => "باید به تمام مسئولیت‌هایم عمل کنم.",
            64 => "احساس می‌کنم برای پیشرفت و دستیابی به خواسته‌هایم، همواره تحت فشار هستم.",
            65 => "وقتی که کاری را به اشتباه انجام می‌دهم، نمی‌توانم خودم را ببخشم یا دست به بهانه‌تراشی بزنم.",
            66 => "وقتی از کسی چیزی می‌خواهم، خیلی برایم سخت است که «نه» بشنوم.",
            67 => "آدم خاصی هستم و نمی‌توانم محدودیت‌هایی را که بر سر راه دیگران وجود دارد، بپذیرم.",
            68 => "از این‌که مرا محدود کنند یا جلوی کارم را بگیرند، به شدت متنفرم.",
            69 => "احساس می‌کنم نباید از قوانین و قراردادهای بهنجاری که مردم تابع آنها هستند، اطاعت کنم.",
            70 => "احساس می‌کنم کارها و خواسته‌های من، ارزشمندتر از کارها یا خواسته‌های دیگران است.",
            71 => "حوصلهٔ انجام کارهای متداول زندگی یا کارهای ملال‌آور را ندارم.",
            72 => "اگر به یکی از اهدافم نرسم، زود مأیوس می‌شوم و دست از تلاش برمی‌دارم.",
            73 => "خیلی برای من سخت است که به‌خاطر دستیابی به هدف بلندمدت، از سر خیر رضایتمندی فوری بگذرم.",
            74 => "نمی‌توانم خودم را مجبور کنم که کارهای بدون لذت را انجام بدهم، حتی اگر بدانم که این کارها نتایج خوبی به‌دنبال دارد.",
            75 => "به‌ندرت توانسته‌ام به تصمیم‌ها و راه‌حل‌هایم پای‌بند باشم.",
        ];
    }

    // ---- تعاریف علمی ----

    public function schemas(): array {
        return [
            'Emotional_Deprivation'  => ['questions'=>[1,2,3,4,5],      'label'=>'محرومیت هیجانی'],
            'Abandonment'            => ['questions'=>[6,7,8,9,10],     'label'=>'رهاشدگی / بی‌ثباتی'],
            'Mistrust'               => ['questions'=>[11,12,13,14,15], 'label'=>'بی‌اعتمادی / بدرفتاری'],
            'Social_Isolation'       => ['questions'=>[16,17,18,19,20], 'label'=>'انزوای اجتماعی'],
            'Defectiveness'          => ['questions'=>[21,22,23,24,25], 'label'=>'نقص / شرم'],
            'Failure'                => ['questions'=>[26,27,28,29,30], 'label'=>'شکست'],
            'Dependence'             => ['questions'=>[31,32,33,34,35], 'label'=>'وابستگی / بی‌کفایتی'],
            'Vulnerability'          => ['questions'=>[36,37,38,39,40], 'label'=>'آسیب‌پذیری در برابر ضرر'],
            'Enmeshment'             => ['questions'=>[41,42,43,44,45], 'label'=>'دلبستگی / خود تحول‌نیافته'],
            'Subjugation'            => ['questions'=>[46,47,48,49,50], 'label'=>'اطاعت'],
            'Self_Sacrifice'         => ['questions'=>[51,52,53,54,55], 'label'=>'فداکاری'],
            'Emotional_Inhibition'   => ['questions'=>[56,57,58,59,60], 'label'=>'بازداری هیجانی'],
            'Unrelenting_Standards'  => ['questions'=>[61,62,63,64,65], 'label'=>'معیارهای سرسختانه'],
            'Entitlement'            => ['questions'=>[66,67,68,69,70], 'label'=>'استحقاق / بزرگ‌منشی'],
            'Insufficient_Control'   => ['questions'=>[71,72,73,74,75], 'label'=>'خویشتن‌داری ناکافی'],
        ];
    }

    public function domains(): array {
        return [
            'domain_1' => ['label'=>'بریدگی و طرد',           'label_en'=>'Disconnection & Rejection',       'color'=>'#c53030', 'schemas'=>['Emotional_Deprivation','Abandonment','Mistrust','Social_Isolation','Defectiveness']],
            'domain_2' => ['label'=>'خودگردانی مختل',         'label_en'=>'Impaired Autonomy & Performance', 'color'=>'#c05621', 'schemas'=>['Failure','Dependence','Vulnerability','Enmeshment']],
            'domain_3' => ['label'=>'محدودیت‌های مختل',       'label_en'=>'Impaired Limits',                 'color'=>'#d69e2e', 'schemas'=>['Entitlement','Insufficient_Control']],
            'domain_4' => ['label'=>'دیگرجهت‌مندی',           'label_en'=>'Other-Directedness',              'color'=>'#2b6cb0', 'schemas'=>['Subjugation','Self_Sacrifice']],
            'domain_5' => ['label'=>'گوش‌به‌زنگی و بازداری', 'label_en'=>'Overvigilance & Inhibition',      'color'=>'#553c9a', 'schemas'=>['Emotional_Inhibition','Unrelenting_Standards']],
        ];
    }

    private function clinical_desc(): array {
        return [
            'Emotional_Deprivation'  => 'باور به اینکه نیاز به حمایت عاطفی معمول به‌طور کافی توسط دیگران برآورده نخواهد شد.',
            'Abandonment'            => 'ادراک بی‌ثباتی یا غیرقابل اطمینان بودن ارتباطات با افراد مهم.',
            'Mistrust'               => 'انتظار اینکه دیگران آسیب می‌زنند، تحقیر می‌کنند، دروغ می‌گویند یا دستکاری می‌کنند.',
            'Social_Isolation'       => 'احساس اینکه فرد از بقیه جهان جدا، متفاوت و به هیچ اجتماعی تعلق ندارد.',
            'Defectiveness'          => 'احساس اینکه فرد معیوب، بد، ناخواسته یا حقیر است.',
            'Failure'                => 'باور به اینکه فرد در حوزه‌های عملکردی شکست خورده یا ناگزیر شکست می‌خورد.',
            'Dependence'             => 'باور به اینکه فرد قادر نیست مسئولیت‌های روزانه را بدون کمک دیگران انجام دهد.',
            'Vulnerability'          => 'ترس افراطی از فاجعه‌های ناگهانی غیرقابل کنترل.',
            'Enmeshment'             => 'درگیری عاطفی بیش از حد با افراد مهم به قیمت رشد هویت فردی.',
            'Subjugation'            => 'تسلیم کنترل مفرط به دیگران برای اجتناب از عصبانیت یا رهاشدگی.',
            'Self_Sacrifice'         => 'تمرکز افراطی بر برآوردن نیازهای دیگران به قیمت خود.',
            'Emotional_Inhibition'   => 'بازداری افراطی از اعمال، احساسات یا ارتباط خودانگیخته.',
            'Unrelenting_Standards'  => 'باور زمینه‌ای به اینکه فرد باید به استانداردهای درونی بسیار بالایی برسد.',
            'Entitlement'            => 'باور به اینکه فرد برتر از دیگران است و مستحق امتیازات ویژه است.',
            'Insufficient_Control'   => 'مشکل مستمر در کنترل خود کافی و ناکامی‌پذیری.',
        ];
    }

    // ---- interface: برچسب‌های عمومی ----

    public function get_schema_labels(): array {
        return array_map( fn($d) => $d['label'], $this->schemas() );
    }

    public function get_domain_labels(): array {
        return array_map(
            fn($d) => ['label' => $d['label'], 'color' => $d['color']],
            $this->domains()
        );
    }

    private function get_severity( float $score ): array {
        return \PsychAssessments\Core\Scoring::severity( $score, [
            ['max'=>1.99, 'label'=>'غیربالینی',      'label_en'=>'Non-clinical',           'color'=>'#276749', 'bg'=>'#f0fff4', 'clinical'=>'در محدوده طبیعی جمعیت عمومی',                              'range'=>'< 2.0'],
            ['max'=>2.99, 'label'=>'مرزی',            'label_en'=>'Borderline',             'color'=>'#d69e2e', 'bg'=>'#fffff0', 'clinical'=>'بالاتر از میانگین — در موقعیت استرس‌زا فعال می‌شود',       'range'=>'2.0 – 2.9'],
            ['max'=>3.99, 'label'=>'بالینی معنادار', 'label_en'=>'Clinically Significant', 'color'=>'#c05621', 'bg'=>'#fffaf0', 'clinical'=>'بالاتر از نقطه برش بالینی (≥۳.۰) — پیگیری توصیه می‌شود', 'range'=>'3.0 – 3.9'],
            ['max'=>4.99, 'label'=>'شدید',            'label_en'=>'Severe',                 'color'=>'#c53030', 'bg'=>'#fff5f5', 'clinical'=>'طرحواره فعال — نیاز به کار درمانی مستقیم',                'range'=>'4.0 – 4.9'],
            [             'label'=>'بسیار شدید',      'label_en'=>'Very Severe',            'color'=>'#9b2c2c', 'bg'=>'#fff5f5', 'clinical'=>'طرحواره بسیار فعال — اولویت اول درمان',                  'range'=>'≥ 5.0'],
        ]);
    }

    // ---- calculate ----

    public function calculate( array $answers ): array {
        $answers = \PsychAssessments\Core\Scoring::sanitize_answers( $answers );
        $schemas = $this->schemas();
        $schema_scores = [];
        foreach ( $schemas as $key => $def ) {
            // باگ #YSQ1: مقیاس YSQ 1–6 است — min و max باید صریح پاس شوند
            // بدون min=1، پاسخ missing با 0 جایگزین می‌شود و نمره طرحواره کاهش مصنوعی می‌یابد
            $schema_scores[$key] = \PsychAssessments\Core\Scoring::subscale_avg( $def['questions'], $answers, 1, 6 );
        }

        $domain_scores = [];
        foreach ( $this->domains() as $dk => $d ) {
            $vals = array_map( fn($s) => $schema_scores[$s] ?? 1.0, $d['schemas'] );
            $domain_scores[$dk] = round( array_sum($vals) / count($vals), 2 );
        }

        $avg_total = round( array_sum($schema_scores) / count($schema_scores), 2 );

        return [
            'schema_scores' => $schema_scores,
            'domain_scores' => $domain_scores,
            'avg_total'     => $avg_total,
        ];
    }

    // ---- render_report ----

    public function render_report( array $scores, object $session ): string {
        $schema_scores = $scores['schema_scores'];
        $domain_scores = $scores['domain_scores'];
        $avg_total     = $scores['avg_total'];
        $schemas       = $this->schemas();
        $domains       = $this->domains();
        $clinical_desc = $this->clinical_desc();
        $avg_sev       = $this->get_severity( $avg_total );

        // ترتیب‌بندی
        $sorted = [];
        foreach ( $schemas as $key => $def ) {
            $val      = $schema_scores[$key];
            $sorted[] = ['key'=>$key, 'label'=>$def['label'], 'value'=>$val, 'severity'=>$this->get_severity($val)];
        }
        usort( $sorted, fn($a,$b) => $b['value'] <=> $a['value'] );

        $clinical_count = count( array_filter($schema_scores, fn($v) => $v >= 3.0) );
        $severe_count   = count( array_filter($schema_scores, fn($v) => $v >= 4.0) );

        arsort( $domain_scores );
        $top_domain_key = array_key_first( $domain_scores );
        $top_domain     = $domains[$top_domain_key];

        $completed_date = $session->completed_at ? pa_to_jalali($session->completed_at, 'Y/m/d ساعت H:i') : '---';

        // chart data
        $chart_labels = array_column( $sorted, 'label' );
        $chart_values = array_column( $sorted, 'value' );
        $chart_colors = array_map( fn($s) => $s['severity']['color'], $sorted );

        $domain_labels = array_column( $domains, 'label' );
        $domain_values = array_values( array_map( fn($dk) => $domain_scores[$dk], array_keys($domains) ) );
        $domain_colors = array_column( $domains, 'color' );

        ob_start();
        ?>
        <!-- خلاصه -->
        <div style="display:flex;gap:12px;flex-wrap:wrap;margin-bottom:24px;">
            <?php
            $cards = [
                ['#ebf8ff','#2b6cb0','📞','شماره تماس',           $session->client_phone ?? $session->patient_phone ?? '---'],
                ['#f0fff4','#2f855a','✅','تاریخ تکمیل',           $completed_date],
                ['#fffff0','#d69e2e','📊','میانگین کل',            $avg_total . ' از ۶ (' . $avg_sev['label'] . ')'],
                ['#fff5f5','#c53030','⚠️','طرحواره‌های بالینی (≥۳)', $clinical_count . ' از ۱۵'],
                ['#fef3c7','#92400e','🔴','طرحواره‌های شدید (≥۴)',  $severe_count . ' از ۱۵'],
                ['#f3e8ff','#553c9a','🏷','بالاترین حوزه',          $top_domain['label']],
            ];
            foreach ($cards as $c) {
                echo \PsychAssessments\Core\ReportHTML::summary_card($c[0],$c[1],$c[2],$c[3],$c[4]);
            }
            ?>
        </div>

        <!-- نمودار + جدول -->
        <div style="display:flex;flex-wrap:wrap;gap:24px;align-items:flex-start;margin-bottom:30px;">
            <div style="flex:1.2;min-width:340px;background:#fff;padding:20px;border-radius:10px;box-shadow:0 2px 10px rgba(0,0,0,0.08);">
                <h3 style="margin-top:0;text-align:center;color:#2d3748;font-size:15px;">
                    نمودار شدت ۱۵ طرحواره
                    <span style="display:block;font-size:11px;color:#a0aec0;font-weight:normal;">خط قرمز: نقطه برش بالینی (≥ ۳.۰)</span>
                </h3>
                <canvas id="pa-bar-chart" style="max-height:520px;"></canvas>
            </div>
            <div style="flex:1;min-width:300px;">
                <h3 style="margin-top:0;font-size:15px;">رتبه‌بندی طرحواره‌ها</h3>
                <table class="widefat fixed" style="border-radius:8px;overflow:hidden;font-size:12px;">
                    <thead><tr style="background:#2b6cb0;color:#fff;">
                        <th style="text-align:right;padding:8px;width:8%;">#</th>
                        <th style="text-align:right;padding:8px;">طرحواره</th>
                        <th style="text-align:center;width:16%;">نمره</th>
                        <th style="text-align:right;width:28%;">سطح</th>
                    </tr></thead>
                    <tbody>
                    <?php foreach ($sorted as $i => $item): $cli = $item['value'] >= 3.0; ?>
                    <tr style="background:<?php echo $cli ? esc_attr($item['severity']['bg']) : '#fff'; ?>;">
                        <td style="padding:7px 8px;color:#718096;font-weight:bold;"><?php echo esc_html($i+1); ?></td>
                        <td style="padding:7px 8px;<?php echo $cli ? 'font-weight:bold;' : ''; ?>">
                            <?php echo esc_html($item['label']); ?>
                            <?php if ($cli): ?><span style="color:<?php echo esc_attr($item['severity']['color']); ?>;font-size:10px;"> ●</span><?php endif; ?>
                        </td>
                        <td style="text-align:center;font-size:16px;font-weight:bold;color:<?php echo esc_attr($item['severity']['color']); ?>;"><?php echo esc_html($item['value']); ?></td>
                        <td style="padding:7px 8px;">
                            <span style="color:<?php echo esc_attr($item['severity']['color']); ?>;font-weight:bold;font-size:11px;"><?php echo esc_html($item['severity']['label']); ?></span>
                            <div style="font-size:10px;color:#a0aec0;"><?php echo esc_html($item['severity']['label_en']); ?></div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- حوزه‌ها -->
        <div style="background:#fff;padding:20px;border-radius:10px;box-shadow:0 2px 10px rgba(0,0,0,0.08);margin-bottom:30px;">
            <h3 style="margin-top:0;font-size:15px;">نمودار ۵ حوزه اصلی یانگ</h3>
            <div style="display:flex;flex-wrap:wrap;gap:20px;align-items:center;">
                <div style="flex:1;min-width:300px;max-width:500px;"><canvas id="pa-radar-chart" style="max-height:280px;"></canvas></div>
                <div style="flex:1;min-width:260px;">
                    <?php foreach ($domains as $dk => $d):
                        $ds  = $domain_scores[$dk];
                        $dsev = $this->get_severity($ds);
                        $pct  = \PsychAssessments\Core\Scoring::percent($ds, 1, 6);
                    ?>
                    <div style="margin-bottom:12px;">
                        <div style="display:flex;justify-content:space-between;margin-bottom:4px;">
                            <span style="font-size:12px;font-weight:bold;color:<?php echo esc_attr($d['color']); ?>;"><?php echo esc_html($d['label']); ?></span>
                            <span style="font-size:13px;font-weight:bold;color:<?php echo esc_attr($dsev['color']); ?>;"><?php echo esc_html($ds); ?></span>
                        </div>
                        <div style="background:#edf2f7;border-radius:4px;height:8px;overflow:hidden;">
                            <div style="background:<?php echo esc_attr($d['color']); ?>;height:100%;width:<?php echo $pct; ?>%;border-radius:4px;"></div>
                        </div>
                        <div style="font-size:10px;color:#a0aec0;margin-top:2px;"><?php echo esc_html($d['label_en']); ?> — <?php echo esc_html($dsev['label']); ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- طرحواره‌های فعال -->
        <?php $active = array_filter($sorted, fn($s) => $s['value'] >= 3.0);
        if (!empty($active)): ?>
        <div style="margin-bottom:30px;">
            <h3 style="font-size:15px;border-bottom:2px solid #e2e8f0;padding-bottom:8px;">🔬 تحلیل بالینی طرحواره‌های فعال (نمره ≥ ۳.۰)</h3>
            <?php foreach ($active as $item):
                echo \PsychAssessments\Core\ReportHTML::clinical_card(
                    $item['label'], $item['key'], $item['value'], $item['severity'],
                    $clinical_desc[$item['key']] ?? '',
                    $item['severity']['clinical']
                );
            endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- راهنمای تفسیر -->
        <div style="background:#f7fafc;border:1px solid #e2e8f0;border-radius:10px;padding:20px;margin-bottom:30px;">
            <h3 style="margin-top:0;font-size:15px;">📚 راهنمای تفسیر علمی</h3>
            <p style="font-size:12px;color:#718096;margin-top:0;">Rijkeboer & van den Bergh (2006) | Sadeghi et al. (2017)</p>
            <table style="width:100%;border-collapse:collapse;font-size:12px;">
                <thead><tr style="background:#edf2f7;">
                    <th style="text-align:right;padding:8px 10px;border:1px solid #e2e8f0;">بازه</th>
                    <th style="text-align:right;padding:8px 10px;border:1px solid #e2e8f0;">سطح</th>
                    <th style="text-align:right;padding:8px 10px;border:1px solid #e2e8f0;">توصیه</th>
                </tr></thead>
                <tbody>
                <?php foreach ([
                    ['≥ 5.0',    'بسیار شدید',      '#9b2c2c', 'اولویت اول درمان'],
                    ['4.0–4.9',  'شدید',             '#c53030', 'مداخله درمانی فعال'],
                    ['3.0–3.9',  'بالینی معنادار',  '#c05621', 'بالاتر از cut-off — پیگیری جدی'],
                    ['2.0–2.9',  'مرزی',             '#d69e2e', 'بالاتر از میانگین'],
                    ['< 2.0',    'غیربالینی',        '#276749', 'محدوده طبیعی'],
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
            Young, J.E., Klosko, J.S., & Weishaar, M.E. (2003). <i>Schema Therapy: A Practitioner's Guide.</i> Guilford Press.<br>
            Rijkeboer, M.M., & van den Bergh, H. (2006). <i>Cognitive Therapy and Research, 30</i>(6), 709–723.<br>
            Sadeghi, K. et al. (2017). <i>Iranian Journal of Psychiatry and Behavioral Sciences.</i><br>
            <br>
            <b style="color:#c53030;">⚠️</b> این گزارش ابزار کمک‌بالینی است و به تنهایی مبنای تشخیص رسمی نیست.
        </div>

        <script>
        (function() {
            function initCharts() {
                if (typeof Chart === 'undefined') { setTimeout(initCharts, 100); return; }
                var ctx1 = document.getElementById('pa-bar-chart');
                if (ctx1) {
                    new Chart(ctx1.getContext('2d'), {
                        type: 'bar',
                        data: {
                            labels: <?php echo wp_json_encode($chart_labels); ?>,
                            datasets:[{ label:'نمره', data:<?php echo wp_json_encode($chart_values); ?>,
                                backgroundColor:<?php echo wp_json_encode(array_map(fn($c)=>$c.'cc',$chart_colors)); ?>,
                                borderColor:<?php echo wp_json_encode($chart_colors); ?>, borderWidth:2, borderRadius:5 }]
                        },
                        options:{ indexAxis:'y', responsive:true, plugins:{legend:{display:false}},
                            scales:{ x:{min:1,max:6,ticks:{stepSize:1}}, y:{ticks:{font:{family:'Tahoma',size:11}}} } },
                        plugins:[{ afterDraw:function(chart){
                            var ctx=chart.ctx, x=chart.scales.x.getPixelForValue(3.0);
                            ctx.save(); ctx.beginPath(); ctx.moveTo(x,chart.chartArea.top); ctx.lineTo(x,chart.chartArea.bottom);
                            ctx.lineWidth=2; ctx.strokeStyle='#c53030'; ctx.setLineDash([5,4]); ctx.stroke();
                            ctx.fillStyle='#c53030'; ctx.font='11px Tahoma'; ctx.fillText('cut-off ۳.۰',x+4,chart.chartArea.top+14);
                            ctx.restore();
                        }}]
                    });
                }
                var ctx2 = document.getElementById('pa-radar-chart');
                if (ctx2) {
                    new Chart(ctx2.getContext('2d'), {
                        type:'radar',
                        data:{ labels:<?php echo wp_json_encode($domain_labels); ?>,
                            datasets:[{ label:'حوزه', data:<?php echo wp_json_encode($domain_values); ?>,
                                backgroundColor:'rgba(66,153,225,0.15)', borderColor:'#3182ce', borderWidth:2,
                                pointBackgroundColor:<?php echo wp_json_encode($domain_colors); ?>, pointRadius:5 }] },
                        options:{ responsive:true, scales:{ r:{min:1,max:6,ticks:{stepSize:1},
                            pointLabels:{font:{family:'Tahoma',size:10}}} }, plugins:{legend:{display:false}} }
                    });
                }
            }
            document.addEventListener('DOMContentLoaded', initCharts);
        })();
        </script>
        <?php
        return ob_get_clean();
    }
}

// ثبت در Registry
\PsychAssessments\Core\Registry::register( new YSQ() );
