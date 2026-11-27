<?php
namespace PsychAssessments\Tests;
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * STAI: State-Trait Anxiety Inventory (Spielberger, 1983)
 *
 * - Spielberger, C.D. (1983). Manual for the State-Trait Anxiety Inventory. Palo Alto, CA.
 * - هنجاریابی ایران: مهرام و همکاران (۱۳۷۹)
 * - ۴۰ سوال: ۲۰ حالت (STAI-S) + ۲۰ صفت (STAI-T)
 * - مقیاس: ۱–۴
 */
class STAI extends \PsychAssessments\Core\BaseTest {

    public function get_id(): string        { return 'stai'; }
    public function get_category(): string      { return 'mental_health'; }
    public function get_name(): string      { return 'اضطراب اشپیلبرگر (STAI)'; }
    public function needs_chart(): bool   { return true; }  // نمودار Chart.js

    public function get_description(): string { return 'پرسشنامه اضطراب حالت-صفت اشپیلبرگر — ۴۰ سوال، حالت + صفت'; }
    public function get_version(): string   { return '1.0.0'; }

    public function get_scale(): array {
        return [
            'min'    => 1,
            'max'    => 4,
            'labels' => [
                1 => 'اصلاً',
                2 => 'کمی',
                3 => 'زیاد',
                4 => 'خیلی زیاد',
            ],
        ];
    }

    // سوالات معکوس STAI-S: 1,2,5,8,10,11,15,16,19,20
    // سوالات معکوس STAI-T: 21,26,27,30,33,36,39
    private function state_reverse(): array  { return [1,2,5,8,10,11,15,16,19,20]; }
    private function trait_reverse(): array  { return [21,26,27,30,33,36,39]; }

    public function get_questions(): array {
        return [
            // STAI-S (حالت — همین الان چه احساسی دارید)
            1  => '[حالت] آرام هستم',
            2  => '[حالت] احساس امنیت می‌کنم',
            3  => '[حالت] تنش دارم',
            4  => '[حالت] تحت فشار هستم',
            5  => '[حالت] آسوده‌خاطر هستم',
            6  => '[حالت] ناراحت هستم',
            7  => '[حالت] در حال حاضر نگرانم که ممکن است اتفاقات بدی رخ دهد',
            8  => '[حالت] احساس راحتی می‌کنم',
            9  => '[حالت] احساس اضطراب می‌کنم',
            10 => '[حالت] احساس آرامش درونی می‌کنم',
            11 => '[حالت] اطمینان به نفس دارم',
            12 => '[حالت] عصبی هستم',
            13 => '[حالت] دلشوره دارم',
            14 => '[حالت] سردرگم هستم',
            15 => '[حالت] آرامش دارم',
            16 => '[حالت] راضی هستم',
            17 => '[حالت] نگران هستم',
            18 => '[حالت] بیش از حد هیجان‌زده و آشفته هستم',
            19 => '[حالت] شاد هستم',
            20 => '[حالت] احساس خوبی دارم',
            // STAI-T (صفت — معمولاً چه احساسی دارید)
            21 => '[صفت] احساس خوبی دارم',
            22 => '[صفت] زود خسته می‌شوم',
            23 => '[صفت] دلم می‌خواهد گریه کنم',
            24 => '[صفت] آرزو می‌کنم بتوانم به اندازه دیگران شاد باشم',
            25 => '[صفت] زمانی که نمی‌توانم تصمیم سریع بگیرم ضرر می‌کنم',
            26 => '[صفت] احساس آرامش می‌کنم',
            27 => '[صفت] آرام، خونسرد و جمع و جور هستم',
            28 => '[صفت] احساس می‌کنم مشکلات روی هم انباشته می‌شوند و نمی‌توانم از پسشان برآیم',
            29 => '[صفت] بیش از حد نگران چیزهایی می‌شوم که اهمیتی ندارند',
            30 => '[صفت] خوشحال هستم',
            31 => '[صفت] تمایل دارم همه چیز را خیلی جدی بگیرم',
            32 => '[صفت] فاقد اعتماد به نفس هستم',
            33 => '[صفت] احساس امنیت می‌کنم',
            34 => '[صفت] سعی می‌کنم از مواجهه با بحران‌ها اجتناب کنم',
            35 => '[صفت] احساس بی‌حالی می‌کنم',
            36 => '[صفت] راضی هستم',
            37 => '[صفت] افکار بی‌اهمیت به ذهنم خطور می‌کند و آزارم می‌دهد',
            38 => '[صفت] ناامیدی‌ها را به قدری جدی می‌گیرم که نمی‌توانم از ذهنم بیرونشان کنم',
            39 => '[صفت] فردی باثبات هستم',
            40 => '[صفت] وقتی به مشکلات و نگرانی‌هایم فکر می‌کنم دچار تنش و آشفتگی می‌شوم',
        ];
    }

    public function get_schema_labels(): array {
        return [
            'state' => 'اضطراب حالت (STAI-S)',
            'trait' => 'اضطراب صفت (STAI-T)',
        ];
    }

    public function get_domain_labels(): array { return []; }

    private function severity( float $score ): array {
        if ( $score < 36 ) return ['label'=>'پایین',   'color'=>'#2f855a','bg'=>'#f0fff4','clinical'=>'اضطراب در محدوده پایین'];
        if ( $score < 45 ) return ['label'=>'متوسط',  'color'=>'#d69e2e','bg'=>'#fffff0','clinical'=>'اضطراب در محدوده متوسط'];
        if ( $score < 55 ) return ['label'=>'بالا',    'color'=>'#c05621','bg'=>'#fff5f0','clinical'=>'اضطراب بالا — پیگیری توصیه می‌شود'];
        return                     ['label'=>'خیلی بالا','color'=>'#9b2c2c','bg'=>'#fff5f5','clinical'=>'اضطراب خیلی بالا — نیاز به مداخله'];
    }

    public function calculate( array $answers ): array {
        $answers = \PsychAssessments\Core\Scoring::sanitize_answers( $answers );
        // محاسبه STAI-S
        $state_score = 0;
        $state_reverse = $this->state_reverse();
        for ( $i = 1; $i <= 20; $i++ ) {
            $v = $answers[$i] ?? 1;
            $state_score += in_array($i, $state_reverse) ? (5 - $v) : $v;
        }

        // محاسبه STAI-T
        $trait_score = 0;
        $trait_reverse = $this->trait_reverse();
        for ( $i = 21; $i <= 40; $i++ ) {
            $v = $answers[$i] ?? 1;
            $trait_score += in_array($i, $trait_reverse) ? (5 - $v) : $v;
        }

        return [
            'schema_scores' => [
                'state' => (float)$state_score,
                'trait' => (float)$trait_score,
            ],
            'state' => $state_score,
            'trait' => $trait_score,
            'avg_total' => round(($state_score + $trait_score) / 2, 1),
        ];
    }

    public function render_report( array $scores, object $session ): string {
        $completed = pa_to_jalali( $session->completed_at, 'Y/m/d ساعت H:i' );
        $s_sev     = $this->severity( $scores['state'] );
        $t_sev     = $this->severity( $scores['trait'] );

        $s_tone = \PsychAssessments\Core\ReportHTML::tone( $s_sev['color'] );
        $t_tone = \PsychAssessments\Core\ReportHTML::tone( $t_sev['color'] );

        ob_start(); ?>
        <div class="psy-report-row">
            <?php foreach ([
                ['#e9d8fd','#553c9a','👤','نام',$session->client_name ?? $session->patient_name ?? '---'],
                ['#ebf8ff','#2b6cb0','📞','شماره تماس',$session->client_phone ?? $session->patient_phone ?? '---'],
                ['#f0fff4','#2f855a','✅','تاریخ تکمیل',$completed],
                [$s_sev['bg'],$s_sev['color'],'⚡','اضطراب حالت (STAI-S)',$scores['state'].' از ۸۰'],
                [$t_sev['bg'],$t_sev['color'],'🔄','اضطراب صفت (STAI-T)',$scores['trait'].' از ۸۰'],
            ] as $c) echo \PsychAssessments\Core\ReportHTML::summary_card($c[0],$c[1],$c[2],$c[3],$c[4]); ?>
        </div>

        <?php echo \PsychAssessments\Core\ReportHTML::section_header('اضطراب حالت (STAI-S) — وضعیت لحظه‌ای'); ?>
        <div class="psy-report-card--tint psy-report-card--tight <?php echo esc_attr( $s_tone ); ?>">
            <div class="psy-clinical__head">
                <div>
                    <h3 class="psy-report-hero__title">نمره: <?php echo esc_html((int)$scores['state']); ?> از ۸۰</h3>
                    <p class="psy-report-hero__text"><?php echo esc_html($s_sev['clinical']); ?></p>
                    <p class="psy-report-hero__note--sm">اضطراب حالت: میزان اضطراب در لحظه انجام آزمون</p>
                </div>
                <?php echo \PsychAssessments\Core\ReportHTML::score_badge((float)$scores['state'], $s_sev); ?>
            </div>
            <?php echo \PsychAssessments\Core\ReportHTML::progress_bar( \PsychAssessments\Core\Scoring::percent($scores['state'],20,80), $s_sev['color'], true ); ?>
        </div>

        <?php echo \PsychAssessments\Core\ReportHTML::section_header('اضطراب صفت (STAI-T) — ویژگی پایدار شخصیت'); ?>
        <div class="psy-report-card--tint psy-report-card--tight <?php echo esc_attr( $t_tone ); ?>">
            <div class="psy-clinical__head">
                <div>
                    <h3 class="psy-report-hero__title">نمره: <?php echo esc_html((int)$scores['trait']); ?> از ۸۰</h3>
                    <p class="psy-report-hero__text"><?php echo esc_html($t_sev['clinical']); ?></p>
                    <p class="psy-report-hero__note--sm">اضطراب صفت: گرایش پایدار به تجربه اضطراب در طول زمان</p>
                </div>
                <?php echo \PsychAssessments\Core\ReportHTML::score_badge((float)$scores['trait'], $t_sev); ?>
            </div>
            <?php echo \PsychAssessments\Core\ReportHTML::progress_bar( \PsychAssessments\Core\Scoring::percent($scores['trait'],20,80), $t_sev['color'], true ); ?>
        </div>

        <div class="psy-report-panel psy-report-panel--tight">
            <h3 class="psy-report-panel__title">📊 مقایسه حالت و صفت</h3>
            <canvas id="pa-stai-bar" class="psy-report-chart psy-report-chart--sm"></canvas>
        </div>

        <div class="psy-report-infobox">
            <b>راهنمای تفسیر:</b><br>
            • نمره پایین‌تر از ۳۶: اضطراب پایین<br>
            • ۳۶ تا ۴۴: متوسط<br>
            • ۴۵ تا ۵۴: بالا<br>
            • ۵۵ و بالاتر: خیلی بالا<br>
            <br>
            <b>تفاوت حالت و صفت:</b> اگر STAI-S بالاتر از STAI-T باشد، اضطراب موقعیتی است. اگر STAI-T بالاتر باشد، اضطراب ویژگی پایدار شخصیتی است.
        </div>

        <div class="psy-report-refs">
            <b>منبع:</b> Spielberger, C.D. (1983). STAI Manual. | هنجاریابی ایران: مهرام و همکاران (۱۳۷۹).<br>
            <b class="psy-tone--danger psy-text">⚠️</b> ابزار کمک‌بالینی — مبنای تشخیص رسمی نیست.
        </div>

        <script>
        (function(){
            function init(){
                if(typeof Chart==='undefined'){setTimeout(init,100);return;}
                var ctx=document.getElementById('pa-stai-bar');
                if(!ctx)return;
                new Chart(ctx.getContext('2d'),{
                    type:'bar',
                    data:{
                        labels:['اضطراب حالت (STAI-S)','اضطراب صفت (STAI-T)'],
                        datasets:[{
                            data:[<?php echo intval($scores['state']); ?>,<?php echo intval($scores['trait']); ?>],
                            backgroundColor:['#90cdf4','#b794f4'],
                            borderRadius:6
                        }]
                    },
                    options:{responsive:true,plugins:{legend:{display:false}},scales:{y:{min:20,max:80}}}
                });
            }
            document.addEventListener('DOMContentLoaded',init);
        })();
        </script>
        <?php
        return ob_get_clean();
    }
}
\PsychAssessments\Core\Registry::register( new STAI() );
