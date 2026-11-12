<?php
namespace PsychAssessments\Tests;
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * آزمون آمادگی برای ازدواج (PAM)
 *
 * مبانی علمی:
 * - خجسته‌مهر، ر.، کوچکی، ع.، و عرب‌ق. (۱۳۸۸). ساخت و اعتباریابی مقیاس آمادگی برای ازدواج.
 *   فصلنامه مشاوره و روان‌درمانی خانواده، ۱(۱)، ۷۲–۹۲.
 * - ثنایی ذاکر، ب. (۱۳۷۹). مقیاس‌های سنجش خانواده و ازدواج. تهران: انتشارات بعثت.
 * - فرح‌بخش، ک.، و موتابی، ف. (۱۳۸۶). اعتباریابی و هنجاریابی مقیاس‌های آمادگی ازدواج
 *   در جمعیت دانشجویی ایران. پایان‌نامه دکترا. دانشگاه علامه طباطبایی.
 *
 * ساختار: ۴۰ سوال، ۵ زیرمقیاس، مقیاس لیکرت ۵ درجه‌ای
 *
 * زیرمقیاس‌ها:
 *   ۱. بلوغ عاطفی و شخصیتی (سوالات ۱–۸)
 *   ۲. آمادگی خانوادگی و اجتماعی (سوالات ۹–۱۶)
 *   ۳. انتظارات و باورهای ازدواج (سوالات ۱۷–۲۴)
 *   ۴. مهارت‌های ارتباطی (سوالات ۲۵–۳۲)
 *   ۵. ارزش‌های مذهبی و فرهنگی (سوالات ۳۳–۴۰)
 *
 * نمره‌گذاری:
 *   هر سوال: ۱ (کاملاً مخالفم) تا ۵ (کاملاً موافقم)
 *   سوالات معکوس: ۳، ۷، ۱۱، ۱۵، ۱۹، ۲۳، ۲۸، ۳۱
 *   نمره کل: ۴۰–۲۰۰
 *   cut-off کلی: ≥ ۱۴۰ آمادگی مناسب | ۱۱۰–۱۳۹ نیاز به مشاوره | < ۱۱۰ آمادگی ضعیف
 *
 * ویژگی زوجی:
 *   هر دو نفر جداگانه آزمون می‌دهند.
 *   در گزارش، اگر هر دو جلسه موجود باشند، مقایسه زوجین نمایش داده می‌شود.
 *   session->patient_name باید شامل نقش باشد: مثلاً "علی رضایی [نفر اول]" و "مریم احمدی [نفر دوم]"
 */

class PAM extends \PsychAssessments\Core\BaseTest {

    public function get_id(): string        { return 'pam'; }
    public function get_category(): string      { return 'relationship'; }
    public function get_name(): string      { return 'آمادگی برای ازدواج (PAM)'; }
    public function needs_chart(): bool   { return true; }  // نمودار Chart.js

    public function get_description(): string {
        return 'مقیاس آمادگی برای ازدواج — ۴۰ سوال، ۵ زیرمقیاس، بومی و اعتباریابی‌شده (خجسته‌مهر و همکاران، ۱۳۸۸) — برای هر دو نفر زوج به‌صورت جداگانه';
    }
    public function get_version(): string   { return '1.0.0'; }

    public function get_scale(): array {
        return [
            'min'    => 1,
            'max'    => 5,
            'labels' => [
                1 => 'کاملاً مخالفم',
                2 => 'مخالفم',
                3 => 'نظری ندارم',
                4 => 'موافقم',
                5 => 'کاملاً موافقم',
            ],
        ];
    }

    /** سوالات معکوس */
    private function reversed(): array {
        return [3, 7, 11, 15, 19, 23, 28, 31];
    }

    private function subscales(): array {
        return [
            'emotional'    => [
                'questions' => [1,2,3,4,5,6,7,8],
                'label'     => 'بلوغ عاطفی و شخصیتی',
                'label_en'  => 'Emotional & Personality Maturity',
                'color'     => '#2b6cb0',
                'bg'        => '#ebf8ff',
                'max_raw'   => 40,
                'desc'      => 'میزان رشد عاطفی، مدیریت احساسات، و ثبات شخصیتی برای ورود به رابطه زناشویی',
            ],
            'family'       => [
                'questions' => [9,10,11,12,13,14,15,16],
                'label'     => 'آمادگی خانوادگی و اجتماعی',
                'label_en'  => 'Family & Social Readiness',
                'color'     => '#276749',
                'bg'        => '#f0fff4',
                'max_raw'   => 40,
                'desc'      => 'کیفیت روابط خانوادگی، استقلال از خانواده اصلی، و شبکه حمایت اجتماعی',
            ],
            'expectations' => [
                'questions' => [17,18,19,20,21,22,23,24],
                'label'     => 'انتظارات و باورهای ازدواج',
                'label_en'  => 'Marriage Expectations & Beliefs',
                'color'     => '#c05621',
                'bg'        => '#fffaf0',
                'max_raw'   => 40,
                'desc'      => 'واقع‌بینی درباره ازدواج، انتظارات منطقی، و باورهای سازگار با زندگی مشترک',
            ],
            'communication'=> [
                'questions' => [25,26,27,28,29,30,31,32],
                'label'     => 'مهارت‌های ارتباطی',
                'label_en'  => 'Communication Skills',
                'color'     => '#553c9a',
                'bg'        => '#faf5ff',
                'max_raw'   => 40,
                'desc'      => 'توانایی ابراز احساسات، حل تعارض، گوش دادن فعال، و برقراری ارتباط مؤثر',
            ],
            'values'       => [
                'questions' => [33,34,35,36,37,38,39,40],
                'label'     => 'ارزش‌های مذهبی و فرهنگی',
                'label_en'  => 'Religious & Cultural Values',
                'color'     => '#97266d',
                'bg'        => '#fff5f7',
                'max_raw'   => 40,
                'desc'      => 'اهمیت باورهای مذهبی، هویت فرهنگی، و همسویی ارزشی با شریک زندگی',
            ],
        ];
    }

    public function get_questions(): array {
        return [
            // ۱. بلوغ عاطفی و شخصیتی
            1  => 'می‌توانم احساسات خود را به‌خوبی شناسایی و مدیریت کنم.',
            2  => 'در موقعیت‌های استرس‌زا، کنترل خود را حفظ می‌کنم.',
            3  => 'اغلب تصمیم‌هایم را تحت تأثیر احساسات آنی می‌گیرم. (معکوس)',
            4  => 'از شخصیت و ارزش‌های خودم تصویر روشنی دارم.',
            5  => 'می‌توانم مستقلانه تصمیم بگیرم و مسئولیت آن را بپذیرم.',
            6  => 'توانایی کنار آمدن با شکست و ناکامی را دارم.',
            7  => 'نیاز مداومی به تأیید دیگران برای احساس خوب بودن دارم. (معکوس)',
            8  => 'انعطاف‌پذیری لازم برای سازگاری با تغییرات زندگی را دارم.',

            // ۲. آمادگی خانوادگی و اجتماعی
            9  => 'روابط صمیمی و رضایت‌بخشی با خانواده‌ام دارم.',
            10 => 'از الگوهای ارتباطی ناسالم در خانواده‌ام آگاهم و سعی می‌کنم آن‌ها را تکرار نکنم.',
            11 => 'هنوز وابستگی شدیدی به تصمیمات والدینم دارم. (معکوس)',
            12 => 'می‌توانم مرزهای سالمی بین زندگی مشترک و خانواده اصلی‌ام حفظ کنم.',
            13 => 'شبکه دوستان و حمایت اجتماعی مناسبی دارم.',
            14 => 'از نظر مالی آمادگی تأمین یک زندگی مشترک را دارم.',
            15 => 'تعارضات حل‌نشده‌ای با والدینم دارم که می‌تواند به زندگی مشترک آسیب بزند. (معکوس)',
            16 => 'اهمیت نقش خانواده همسر در زندگی مشترک را می‌پذیرم.',

            // ۳. انتظارات و باورهای ازدواج
            17 => 'درک واقع‌بینانه‌ای از چالش‌های زندگی مشترک دارم.',
            18 => 'ازدواج را فرایندی می‌دانم که نیاز به تلاش مداوم دارد نه پایان یک داستان عاشقانه.',
            19 => 'انتظار دارم همسرم همه نیازهای عاطفی‌ام را برآورده کند. (معکوس)',
            20 => 'می‌دانم که تفاوت‌های شخصیتی در هر رابطه‌ای طبیعی است.',
            21 => 'آماده پذیرش مسئولیت‌های اقتصادی و خانوادگی ازدواج هستم.',
            22 => 'اهمیت گفتگوی صادقانه قبل از ازدواج را می‌پذیرم.',
            23 => 'فکر می‌کنم عشق به تنهایی برای داشتن ازدواج موفق کافی است. (معکوس)',
            24 => 'درباره نقش‌های جنسیتی در خانواده دیدگاه مشخص و منعطفی دارم.',

            // ۴. مهارت‌های ارتباطی
            25 => 'می‌توانم احساسات و نیازهایم را بدون انتقاد و سرزنش بیان کنم.',
            26 => 'در هنگام اختلاف نظر، به دیدگاه طرف مقابل با احترام گوش می‌دهم.',
            27 => 'توانایی عذرخواهی صادقانه و بخشیدن را دارم.',
            28 => 'وقتی ناراحت می‌شوم، معمولاً سکوت می‌کنم یا موضوع را عوض می‌کنم. (معکوس)',
            29 => 'می‌توانم تعارض را بدون فریاد زدن یا تحقیر طرف مقابل حل کنم.',
            30 => 'درباره موضوعات حساس (مالی، جنسی، فرزند) می‌توانم صادقانه صحبت کنم.',
            31 => 'اغلب سعی می‌کنم در بحث‌ها "برنده" شوم نه به راه‌حل برسم. (معکوس)',
            32 => 'می‌توانم زبان بدن و احساسات طرف مقابل را به‌خوبی درک کنم.',

            // ۵. ارزش‌های مذهبی و فرهنگی
            33 => 'باورهای مذهبی و معنوی‌ام در زندگی روزمره‌ام نقش مهمی دارد.',
            34 => 'ارزش‌های فرهنگی و هویت خانوادگی‌ام برایم اهمیت دارد.',
            35 => 'درباره نحوه انجام مناسک مذهبی در زندگی مشترک فکر کرده‌ام.',
            36 => 'آماده‌ام که درباره تفاوت‌های فرهنگی با همسرم گفتگوی باز داشته باشم.',
            37 => 'درباره تربیت دینی و فرهنگی فرزندان دیدگاه مشخصی دارم.',
            38 => 'احترام به ارزش‌های فرهنگی و مذهبی همسر برایم مهم است.',
            39 => 'درباره تأثیر باورهای مذهبی بر تصمیمات مشترک (مثل اقتصاد، تفریح) فکر کرده‌ام.',
            40 => 'پایبندی به اصول اخلاقی در زندگی مشترک برایم اولویت دارد.',
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
        $answers = \PsychAssessments\Core\Scoring::sanitize_answers( $answers );
        $reversed  = $this->reversed();
        $subscales = $this->subscales();
        $sub_scores = [];
        $grand_total = 0;

        foreach ( $subscales as $key => $def ) {
            $sum = 0;
            foreach ( $def['questions'] as $q ) {
                $raw = $answers[$q] ?? 3;
                $val = in_array($q, $reversed) ? (6 - $raw) : $raw;
                $sum += $val;
            }
            $sub_scores[$key] = $sum;
            $grand_total += $sum;
        }

        return [
            'sub_scores' => $sub_scores,
            'total'      => $grand_total,
        ];
    }

    /**
     * منبع واحد نقاط برش کل PAM (ایندکس ۰ = مرز ۱۱۰، زیر آن «آمادگی ضعیف»).
     * public است چون آزمون‌های دیگر (مثلاً FMS در تفسیر ترکیبی FMS+PAM) از همین
     * آرایه برای مقایسه استفاده می‌کنند، به‌جای اینکه عدد ۱۱۰ را دوباره هاردکد کنند.
     */
    public function total_severity_levels(): array {
        return [
            ['max'=>109, 'label'=>'آمادگی ضعیف',   'color'=>'#c53030', 'bg'=>'#fff5f5', 'clinical'=>'نمرات نشان‌دهنده نیاز جدی به آموزش و مشاوره قبل از اقدام به ازدواج است.'],
            ['max'=>139, 'label'=>'نیاز به مشاوره', 'color'=>'#c05621', 'bg'=>'#fffaf0', 'clinical'=>'برخی حوزه‌ها نیاز به تقویت دارند. مشاوره پیش از ازدواج توصیه می‌شود.'],
            [            'label'=>'آمادگی مناسب',   'color'=>'#276749', 'bg'=>'#f0fff4', 'clinical'=>'نمرات نشان‌دهنده آمادگی مناسب برای ورود به زندگی مشترک است. توصیه می‌شود نکات ارتقایی نیز مرور شود.'],
        ];
    }

    private function total_severity( int $score ): array {
        return \PsychAssessments\Core\Scoring::severity( (float) $score, $this->total_severity_levels() );
    }

    private function subscale_severity( int $score ): array {
        // هر زیرمقیاس max=40 — cut-off: ≥28 مناسب، 22–27 متوسط، <22 ضعیف
        if ( $score >= 28 ) return ['label'=>'مناسب',  'color'=>'#276749', 'bg'=>'#f0fff4'];
        if ( $score >= 22 ) return ['label'=>'متوسط',  'color'=>'#c05621', 'bg'=>'#fffaf0'];
        return                     ['label'=>'نیاز به تقویت', 'color'=>'#c53030', 'bg'=>'#fff5f5'];
    }

    /**
     * جستجو برای جلسه دیگر همین زوج در پایگاه داده
     * نامگذاری: "نام کامل [نفر اول]" و "نام کامل [نفر دوم]"
     * یا "نام کامل [زوجه]" و "نام کامل [زوج]"
     * کافیه phone یکسان باشه یا token مشترک در patient_phone ثبت شده باشه
     */
    private function find_partner_session( object $session ): ?object {
        global $wpdb;

        // v3: جداول psy_invitations + psy_attempts — نه pa_sessions/pa_answers
        // روش ۱: شماره تماس یکسان — دو نفر با یک شماره تلفن ثبت شدن
        $phone = $session->client_phone ?? $session->patient_phone ?? '';
        if ( ! empty($phone) ) {
            $partner = $wpdb->get_row( $wpdb->prepare(
                "SELECT a.*, i.client_name, i.client_phone, i.client_phone AS patient_phone
                 FROM {$wpdb->prefix}psy_attempts a
                 JOIN {$wpdb->prefix}psy_invitations i ON a.invitation_id = i.id
                 WHERE a.quiz_id = 'pam'
                   AND i.client_phone = %s
                   AND a.id != %d
                 ORDER BY a.completed_at DESC LIMIT 1",
                $phone,
                $session->id
            ) );
            if ( $partner ) return $partner;
        }

        // روش ۲: client_name شامل برچسب زوجی — مثلاً «[زوج]» یا «[نفر اول]»
        $client_name = $session->client_name ?? $session->patient_name ?? '';
        $base_name = preg_replace('/\s*[\[\(][^\]\)]*[\]\)]\s*$/', '', trim($client_name));
        if ( strlen($base_name) > 2 ) {
            $partner = $wpdb->get_row( $wpdb->prepare(
                "SELECT a.*, i.client_name, i.client_phone, i.client_phone AS patient_phone
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
            if ( $partner ) return $partner;
        }

        return null;
    }

    public function render_report( array $data, object $session ): string {
        global $wpdb;
        $sub_scores    = $data['sub_scores'];
        $total         = $data['total'];
        $subscales     = $this->subscales();
        $total_sev     = $this->total_severity( (int)$total );
        $completed_date = $session->completed_at
            ? pa_to_jalali( $session->completed_at, 'Y/m/d ساعت H:i' )
            : '---';

        // ===== پیدا کردن جلسه شریک زوجی =====
        $partner_session = $this->find_partner_session( $session );
        $partner_data    = null;
        $partner_scores  = null;
        $partner_total   = null;
        if ( $partner_session ) {
            // v3: پاسخ‌ها در فیلد answers (JSON) در psy_attempts ذخیره شده‌اند
            $partner_answers_json = $wpdb->get_var( $wpdb->prepare(
                "SELECT answers FROM {$wpdb->prefix}psy_attempts WHERE id = %d",
                $partner_session->id
            ) );
            $partner_answers = $partner_answers_json ? json_decode( $partner_answers_json, true ) : [];
            $partner_data   = $this->calculate( $partner_answers );
            $partner_scores = $partner_data['sub_scores'];
            $partner_total  = $partner_data['total'];
        }

        // داده‌های نمودار
        $chart_labels  = [];
        $chart_vals_me = [];
        $chart_vals_p  = [];
        $chart_colors  = [];
        foreach ( $subscales as $key => $def ) {
            $chart_labels[]  = $def['label'];
            $chart_vals_me[] = $sub_scores[$key];
            $chart_vals_p[]  = $partner_scores ? $partner_scores[$key] : null;
            $chart_colors[]  = $def['color'];
        }
        $chart_id = 'pam_chart_' . $session->id;

        ob_start();
        ?>

        <!-- ===== خلاصه سربرگ ===== -->
        <div style="display:flex;gap:12px;flex-wrap:wrap;margin-bottom:24px;">
            <?php
            $cards = [
                ['#ebf8ff','#2b6cb0','👤','نام',          esc_html($session->client_name ?? $session->patient_name ?? '')],
                ['#f0fff4','#2f855a','📞','تماس',          esc_html($session->patient_phone ?: '---')],
                ['#fffaf0','#c05621','📅','تاریخ تکمیل',   $completed_date],
                ['#faf5ff','#553c9a','📊','نمره کل',        $total . ' از ۲۰۰'],
                [$total_sev['bg'], $total_sev['color'], '🏷','وضعیت', esc_html($total_sev['label'])],
            ];
            foreach ( $cards as [$bg,$col,$ico,$lbl,$val] ) {
                echo \PsychAssessments\Core\ReportHTML::summary_card($bg, $col, $ico, $lbl, $val);
            }
            ?>
        </div>

        <!-- ===== نمره کل + تفسیر ===== -->
        <?php echo \PsychAssessments\Core\ReportHTML::section_header('نمره کل آمادگی ازدواج'); ?>
        <div style="display:flex;align-items:center;gap:20px;flex-wrap:wrap;margin-bottom:16px;">
            <?php echo \PsychAssessments\Core\ReportHTML::score_badge((float)$total, $total_sev); ?>
            <div style="flex:1;min-width:200px;">
                <!-- progress bar -->
                <?php $pct = \PsychAssessments\Core\Scoring::percent($total, 40, 200); ?>
                <div style="background:#e2e8f0;border-radius:6px;height:14px;overflow:hidden;margin-bottom:6px;">
                    <div style="width:<?php echo $pct; ?>%;height:100%;background:<?php echo esc_attr($total_sev['color']); ?>;transition:width .4s;"></div>
                </div>
                <div style="font-size:12px;color:#718096;">
                    محدوده: ≥۱۴۰ آمادگی مناسب | ۱۱۰–۱۳۹ نیاز به مشاوره | &lt;۱۱۰ آمادگی ضعیف
                </div>
            </div>
        </div>
        <div style="background:<?php echo esc_attr($total_sev['bg']); ?>;border-right:4px solid <?php echo esc_attr($total_sev['color']); ?>;border-radius:6px;padding:12px 16px;font-size:13px;color:<?php echo esc_attr($total_sev['color']); ?>;margin-bottom:20px;">
            💡 <?php echo esc_html($total_sev['clinical']); ?>
        </div>

        <!-- ===== نمودار رادار / بار ===== -->
        <?php echo \PsychAssessments\Core\ReportHTML::section_header('نمودار زیرمقیاس‌ها' . ($partner_session ? ' — مقایسه زوجین' : '')); ?>
        <div style="max-width:560px;margin:0 auto 28px;">
            <canvas id="<?php echo esc_attr($chart_id); ?>" height="<?php echo $partner_session ? 260 : 220; ?>"></canvas>
        </div>
        <script>
        (function(){
            var el = document.getElementById('<?php echo esc_js($chart_id); ?>');
            if(!el) return;
            function drawChart(){
                if(typeof Chart === 'undefined'){ setTimeout(drawChart,300); return; }
                var labels = <?php echo wp_json_encode($chart_labels); ?>;
                var me     = <?php echo wp_json_encode($chart_vals_me); ?>;
                <?php if ($partner_session): ?>
                var partner = <?php echo wp_json_encode($chart_vals_p); ?>;
                var datasets = [
                    { label: '<?php echo esc_js($session->client_name ?? $session->patient_name ?? ''); ?>', data: me,
                      backgroundColor: 'rgba(43,108,176,0.18)', borderColor:'#2b6cb0',
                      borderWidth:2, pointBackgroundColor:'#2b6cb0' },
                    { label: '<?php echo esc_js($partner_session->client_name ?? $partner_session->patient_name ?? ''); ?>', data: partner,
                      backgroundColor: 'rgba(39,103,73,0.18)', borderColor:'#276749',
                      borderWidth:2, pointBackgroundColor:'#276749' }
                ];
                var chartType = 'radar';
                <?php else: ?>
                var datasets = [
                    { label: 'نمره', data: me,
                      backgroundColor: <?php echo wp_json_encode($chart_colors); ?>,
                      borderWidth: 0 }
                ];
                var chartType = 'bar';
                <?php endif; ?>
                new Chart(el, {
                    type: chartType,
                    data: { labels: labels, datasets: datasets },
                    options: {
                        responsive: true,
                        plugins: { legend: { position:'bottom', labels:{ font:{ family:'Vazirmatn,Tahoma', size:12 }, color:'#4a5568' } } },
                        <?php if (!$partner_session): ?>
                        scales: {
                            y: { beginAtZero:true, max:40,
                                 ticks:{ font:{family:'Vazirmatn,Tahoma'}, color:'#718096' },
                                 grid:{ color:'#e2e8f0' } },
                            x: { ticks:{ font:{family:'Vazirmatn,Tahoma',size:11}, color:'#4a5568' },
                                 grid:{ display:false } }
                        },
                        <?php else: ?>
                        scales: { r: { beginAtZero:true, max:40,
                                       ticks:{ stepSize:8, font:{family:'Vazirmatn,Tahoma',size:10} },
                                       pointLabels:{ font:{family:'Vazirmatn,Tahoma',size:11}, color:'#4a5568' } } },
                        <?php endif; ?>
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
            $pct   = \PsychAssessments\Core\Scoring::percent($score, 8, 40);
            $p_score = $partner_scores[$key] ?? null;
            $p_sev   = $p_score !== null ? $this->subscale_severity($p_score) : null;
            $p_pct   = $p_score !== null ? \PsychAssessments\Core\Scoring::percent($p_score, 8, 40) : null;
        ?>
        <div style="background:#fff;border:1px solid #e2e8f0;border-radius:8px;padding:14px 16px;margin-bottom:12px;border-right:4px solid <?php echo esc_attr($def['color']); ?>;">
            <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;margin-bottom:8px;">
                <div>
                    <span style="font-size:15px;font-weight:bold;color:<?php echo esc_attr($def['color']); ?>;">
                        <?php echo esc_html($def['label']); ?>
                    </span>
                    <span style="font-size:11px;color:#a0aec0;margin-right:6px;"><?php echo esc_html($def['label_en']); ?></span>
                </div>
                <div style="display:flex;gap:8px;align-items:center;">
                    <?php echo \PsychAssessments\Core\ReportHTML::score_badge((float)$score, $sev); ?>
                    <?php if ($p_score !== null): ?>
                    <div style="font-size:11px;color:#718096;text-align:center;">
                        <div style="font-size:16px;font-weight:bold;color:<?php echo esc_attr($p_sev['color']); ?>;"><?php echo $p_score; ?></div>
                        <div>شریک</div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <!-- progress bar من -->
            <div style="font-size:11px;color:#718096;margin-bottom:3px;"><?php echo esc_html($session->client_name ?? $session->patient_name ?? ''); ?></div>
            <div style="background:#e2e8f0;border-radius:4px;height:10px;overflow:hidden;margin-bottom:<?php echo esc_attr($p_score !== null ? '8px' : '10px'); ?>;">
                <div style="width:<?php echo $pct; ?>%;height:100%;background:<?php echo esc_attr($def['color']); ?>;"></div>
            </div>
            <?php if ($p_score !== null && $p_pct !== null): ?>
            <!-- progress bar شریک -->
            <div style="font-size:11px;color:#718096;margin-bottom:3px;"><?php echo esc_html($partner_session->client_name ?? $partner_session->patient_name ?? ''); ?></div>
            <div style="background:#e2e8f0;border-radius:4px;height:10px;overflow:hidden;margin-bottom:10px;">
                <div style="width:<?php echo $p_pct; ?>%;height:100%;background:<?php echo esc_attr($def['color']); ?>;opacity:0.5;"></div>
            </div>
            <?php endif; ?>
            <p style="margin:0 0 8px;font-size:13px;color:#4a5568;line-height:1.7;"><?php echo esc_html($def['desc']); ?></p>
            <div style="background:<?php echo esc_attr($sev['bg']); ?>;border-radius:5px;padding:6px 10px;font-size:12px;color:<?php echo esc_attr($sev['color']); ?>;">
                وضعیت: <strong><?php echo esc_html($sev['label']); ?></strong>
                <?php if ($p_score !== null && $p_sev !== null): ?>
                &nbsp;|&nbsp; شریک: <strong><?php echo esc_html($p_sev['label']); ?></strong>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>

        <!-- ===== مقایسه زوجین (جدول) ===== -->
        <?php if ($partner_session && $partner_scores): ?>
        <?php echo \PsychAssessments\Core\ReportHTML::section_header('مقایسه کامل زوجین'); ?>
        <?php
        $partner_sev   = $this->total_severity((int)$partner_total);
        $agreement_score = 0; // میزان همسویی
        foreach ($subscales as $key => $def) {
            $diff = abs($sub_scores[$key] - $partner_scores[$key]);
            if ($diff <= 4)       $agreement_score += 2;
            elseif ($diff <= 8)   $agreement_score += 1;
        }
        $agreement_pct = round($agreement_score / (count($subscales)*2) * 100);
        ?>
        <div style="overflow-x:auto;margin-bottom:20px;">
            <table style="width:100%;border-collapse:collapse;font-size:13px;">
                <thead>
                    <tr style="background:#f7fafc;">
                        <th style="padding:10px 12px;text-align:right;border-bottom:2px solid #e2e8f0;color:#4a5568;">زیرمقیاس</th>
                        <th style="padding:10px 12px;text-align:center;border-bottom:2px solid #e2e8f0;color:#2b6cb0;"><?php echo esc_html($session->client_name ?? $session->patient_name ?? ''); ?></th>
                        <th style="padding:10px 12px;text-align:center;border-bottom:2px solid #e2e8f0;color:#276749;"><?php echo esc_html($partner_session->client_name ?? $partner_session->patient_name ?? ''); ?></th>
                        <th style="padding:10px 12px;text-align:center;border-bottom:2px solid #e2e8f0;color:#718096;">اختلاف</th>
                        <th style="padding:10px 12px;text-align:center;border-bottom:2px solid #e2e8f0;color:#718096;">همسویی</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($subscales as $key => $def):
                        $s1   = $sub_scores[$key];
                        $s2   = $partner_scores[$key];
                        $diff = abs($s1 - $s2);
                        if ($diff <= 4)      { $align_color='#276749'; $align_label='زیاد'; }
                        elseif ($diff <= 8)  { $align_color='#c05621'; $align_label='متوسط'; }
                        else                 { $align_color='#c53030'; $align_label='کم'; }
                    ?>
                    <tr style="border-bottom:1px solid #edf2f7;">
                        <td style="padding:9px 12px;color:<?php echo esc_attr($def['color']); ?>;font-weight:600;">
                            <?php echo esc_html($def['label']); ?>
                        </td>
                        <td style="padding:9px 12px;text-align:center;font-weight:bold;color:#2b6cb0;"><?php echo esc_html((int)$s1); ?></td>
                        <td style="padding:9px 12px;text-align:center;font-weight:bold;color:#276749;"><?php echo esc_html((int)$s2); ?></td>
                        <td style="padding:9px 12px;text-align:center;color:#718096;"><?php echo esc_html((int)$diff); ?></td>
                        <td style="padding:9px 12px;text-align:center;">
                            <span style="background:<?php echo $align_color; ?>22;color:<?php echo esc_attr($align_color); ?>;border-radius:4px;padding:2px 8px;font-size:11px;font-weight:bold;">
                                <?php echo esc_html($align_label); ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <!-- ردیف جمع -->
                    <tr style="background:#f7fafc;font-weight:bold;">
                        <td style="padding:10px 12px;color:#4a5568;">نمره کل</td>
                        <td style="padding:10px 12px;text-align:center;color:<?php echo esc_attr($total_sev['color']); ?>;">
                            <?php echo esc_html((int)$total); ?> — <?php echo esc_html($total_sev['label']); ?>
                        </td>
                        <td style="padding:10px 12px;text-align:center;color:<?php echo esc_attr($partner_sev['color']); ?>;">
                            <?php echo esc_html((int)$partner_total); ?> — <?php echo esc_html($partner_sev['label']); ?>
                        </td>
                        <td style="padding:10px 12px;text-align:center;color:#718096;"><?php echo esc_html(abs($total - $partner_total)); ?></td>
                        <td style="padding:10px 12px;text-align:center;color:#553c9a;font-size:13px;"><?php echo esc_html((int)$agreement_pct); ?>٪ همسو</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- توصیه زوجی -->
        <?php
        if ($agreement_pct >= 70) {
            $ag_color = '#276749'; $ag_bg = '#f0fff4';
            $ag_text  = 'همسویی این زوج در اکثر حوزه‌ها مناسب است. توصیه می‌شود روی حوزه‌هایی که اختلاف بیشتری دارند، پیش از ازدواج گفتگوی هدفمند داشته باشند.';
        } elseif ($agreement_pct >= 45) {
            $ag_color = '#c05621'; $ag_bg = '#fffaf0';
            $ag_text  = 'تفاوت‌های معناداری بین دو نفر در برخی حوزه‌ها وجود دارد. مشاوره پیش از ازدواج با تمرکز بر مذاکره و تنظیم انتظارات توصیه می‌شود.';
        } else {
            $ag_color = '#c53030'; $ag_bg = '#fff5f5';
            $ag_text  = 'تفاوت‌های قابل توجهی بین دو نفر وجود دارد. پیش از هر تصمیمی، جلسات مشاوره زوجی با متخصص ضروری است.';
        }
        ?>
        <div style="background:<?php echo esc_attr($ag_bg); ?>;border-right:4px solid <?php echo esc_attr($ag_color); ?>;border-radius:6px;padding:14px 16px;font-size:13px;color:<?php echo esc_attr($ag_color); ?>;margin-bottom:20px;line-height:1.8;">
            <strong>توصیه بالینی زوجی (همسویی <?php echo esc_html((int)$agreement_pct); ?>٪):</strong><br>
            <?php echo esc_html($ag_text); ?>
        </div>
        <?php endif; ?>

        <!-- ===== منابع علمی ===== -->
        <div style="margin-top:28px;padding:12px 16px;background:#f7fafc;border-radius:6px;font-size:11px;color:#718096;line-height:1.9;">
            <strong>منابع:</strong><br>
            خجسته‌مهر، ر.، کوچکی، ع.، و عرب، ق. (۱۳۸۸). ساخت و اعتباریابی مقیاس آمادگی برای ازدواج. <em>فصلنامه مشاوره و روان‌درمانی خانواده</em>، ۱(۱)، ۷۲–۹۲.<br>
            ثنایی ذاکر، ب. (۱۳۷۹). <em>مقیاس‌های سنجش خانواده و ازدواج</em>. تهران: انتشارات بعثت.<br>
            فرح‌بخش، ک.، و موتابی، ف. (۱۳۸۶). اعتباریابی مقیاس‌های آمادگی ازدواج در جمعیت دانشجویی ایران. دانشگاه علامه طباطبایی.
        </div>

        <?php
        return ob_get_clean();
    }
}

\PsychAssessments\Core\Registry::register( new PAM() );
