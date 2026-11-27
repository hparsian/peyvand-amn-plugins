<?php
namespace PsychAssessments\Tests;
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * CTS-2: Revised Conflict Tactics Scales (Straus et al., 1996)
 *
 * - Straus, M.A., Hamby, S.L., Boney-McCoy, S., & Sugarman, D.B. (1996).
 *   The Revised Conflict Tactics Scales (CTS2).
 *   Journal of Family Issues, 17(3), 283–316.
 * - ۳۹ جفت سوال (۷۸ سوال) — ۵ بعد
 * - مقیاس فراوانی: ۰–۶
 */
class CTS2 extends \PsychAssessments\Core\BaseTest {

    public function get_id(): string        { return 'cts2'; }
    public function get_category(): string      { return 'relationship'; }
    public function get_name(): string      { return 'تعارض زوجین (CTS-2)'; }
    public function needs_chart(): bool   { return true; }  // نمودار Chart.js

    public function get_description(): string { return 'مقیاس تاکتیک‌های تعارض — ۳۹ موقعیت، ۵ بعد: مذاکره، روان‌آزاری، حمله جسمی، اجبار جنسی، آسیب'; }
    public function get_version(): string   { return '1.0.0'; }

    public function get_scale(): array {
        return [
            'min'    => 0,
            'max'    => 6,
            'labels' => [
                0 => 'هرگز در سال گذشته',
                1 => 'یک بار در سال گذشته',
                2 => 'دو بار در سال گذشته',
                3 => 'سه تا پنج بار در سال گذشته',
                4 => 'شش تا ده بار در سال گذشته',
                5 => 'یازده تا بیست بار در سال گذشته',
                6 => 'بیش از بیست بار در سال گذشته',
            ],
        ];
    }

    // سوالات فرد: کار من | سوالات زوج: کار شریکم
    // فقط سوالات فرد (رفتار پاسخ‌دهنده) را نمره می‌دهیم
    private function subscales(): array {
        return [
            'neg'   => [1,3,5,7,9,11],        // مذاکره
            'psy'   => [13,15,17,19,21,23,25,27], // روان‌آزاری
            'phy'   => [29,31,33,35,37,39,41,43,45,47,49,51], // حمله جسمی
            'sex'   => [53,55,57,59,61,63],    // اجبار جنسی
            'inj'   => [65,67,69,71,73,75,77], // آسیب جسمی
        ];
    }

    public function get_questions(): array {
        $qs = [];
        $pairs = [
            // مذاکره (Negotiation)
            [1,2,   'برای حل اختلاف، مشکل را با همسرم در میان گذاشتم'],
            [3,4,   'سعی کردم به احساسات همسرم احترام بگذارم، حتی وقتی دعوا داشتیم'],
            [5,6,   'به همسرم توضیح دادم که چرا با نظرش موافق نیستم'],
            [7,8,   'مشکلم را با همسرم مطرح کردم حتی اگر او نمی‌خواست'],
            [9,10,  'به همسرم نشان دادم که مراقبش هستم، حتی در اختلاف'],
            [11,12, 'مطمئن شدم که همسرم می‌داند که حرف‌هایش را می‌شنوم'],
            // روان‌آزاری (Psychological Aggression)
            [13,14, 'به همسرم توهین کردم یا ناسزا گفتم'],
            [15,16, 'کاری کردم که همسرم احساس بدی درباره خودش داشته باشد'],
            [17,18, 'همسرم را تهدید کردم که آسیب بزنم'],
            [19,20, 'داد زدم یا جیغ زدم سر همسرم'],
            [21,22, 'از خانه خارج شدم تا از بحث فرار کنم'],
            [23,24, 'همسرم را به نام‌های زشت صدا زدم'],
            [25,26, 'چیزی گفتم که می‌دانستم آزاردهنده است'],
            [27,28, 'به همسرم گفتم که چاق، زشت یا شبیه بازنده است'],
            // حمله جسمی (Physical Assault)
            [29,30, 'به همسرم سیلی زدم'],
            [31,32, 'چیزی به طرف همسرم پرتاب کردم'],
            [33,34, 'همسرم را هل دادم یا تکان دادم'],
            [35,36, 'با مشت یا چیز سختی همسرم را زدم'],
            [37,38, 'همسرم را گاز گرفتم'],
            [39,40, 'گلوی همسرم را فشار دادم'],
            [41,42, 'سر همسرم را به دیوار یا کف زمین زدم'],
            [43,44, 'همسرم را لگد زدم'],
            [45,46, 'تفنگ یا اسلحه‌ای را روی همسرم نشانه رفتم'],
            [47,48, 'همسرم را سوزاندم یا آب جوش روی او ریختم'],
            [49,50, 'همسرم را خفه کردم'],
            [51,52, 'موهای همسرم را کشیدم'],
            // اجبار جنسی (Sexual Coercion)
            [53,54, 'بدون اجازه به همسرم دست زدم'],
            [55,56, 'همسرم را مجبور کردم بدون رضایت رابطه جنسی داشته باشد'],
            [57,58, 'از تهدید یا زور برای رابطه جنسی استفاده کردم'],
            [59,60, 'همسرم را مجبور کردم با اشخاص دیگر رابطه جنسی داشته باشد'],
            [61,62, 'تصاویر جنسی را بر خلاف میل همسرم نشان دادم'],
            [63,64, 'همسرم را مجبور کردم وقتی خواب بود رابطه جنسی داشته باشیم'],
            // آسیب (Injury)
            [65,66, 'همسرم در اثر کتک خوردن درد داشت'],
            [67,68, 'همسرم به خاطر دعوا به پزشک نیاز داشت'],
            [69,70, 'همسرم در اثر دعوا آسیب دید'],
            [71,72, 'همسرم به دلیل دعوا بیهوش شد'],
            [73,74, 'همسرم کبودی یا بریدگی داشت'],
            [75,76, 'همسرم در اثر دعوا استخوانش شکست'],
            [77,78, 'همسرم در اثر دعوا سوختگی داشت'],
        ];

        foreach ( $pairs as [$odd, $even, $text] ) {
            $qs[$odd]  = '[من] ' . $text;
            $qs[$even] = '[همسرم] ' . $text;
        }
        return $qs;
    }

    public function get_schema_labels(): array {
        return [
            'neg' => 'مذاکره',
            'psy' => 'روان‌آزاری',
            'phy' => 'حمله جسمی',
            'sex' => 'اجبار جنسی',
            'inj' => 'آسیب جسمی',
        ];
    }

    public function get_domain_labels(): array { return []; }

    private function severity_phy( int $score ): array {
        if ( $score === 0 ) return ['label'=>'بدون خشونت',  'color'=>'#2f855a','bg'=>'#f0fff4','clinical'=>'هیچ خشونت جسمی گزارش نشده'];
        if ( $score <= 5  ) return ['label'=>'خفیف',        'color'=>'#d69e2e','bg'=>'#fffff0','clinical'=>'خشونت جسمی خفیف'];
        if ( $score <= 15 ) return ['label'=>'متوسط',       'color'=>'#c05621','bg'=>'#fff5f0','clinical'=>'خشونت جسمی متوسط — مداخله لازم'];
        return                     ['label'=>'شدید',        'color'=>'#9b2c2c','bg'=>'#fff5f5','clinical'=>'خشونت جسمی شدید — اقدام فوری'];
    }

    public function calculate( array $answers ): array {
        $answers = \PsychAssessments\Core\Scoring::sanitize_answers( $answers );
        $subscales     = $this->subscales();
        $schema_scores = [];

        foreach ( $subscales as $key => $qs ) {
            $sum = 0;
            foreach ( $qs as $q ) {
                $sum += $answers[$q] ?? 0;
            }
            $schema_scores[$key] = (float)$sum;
        }

        return [
            'schema_scores' => $schema_scores,
            'total'         => array_sum($schema_scores),
            'avg_total'     => (float)$schema_scores['phy'],
        ];
    }

    public function render_report( array $scores, object $session ): string {
        $completed = pa_to_jalali( $session->completed_at, 'Y/m/d ساعت H:i' );
        $phy_sev   = $this->severity_phy( (int)$scores['schema_scores']['phy'] );
        $labels    = $this->get_schema_labels();

        $colors = [
            'neg' => '#68d391',
            'psy' => '#f6ad55',
            'phy' => '#fc8181',
            'sex' => '#b794f4',
            'inj' => '#fc8181',
        ];

        ob_start(); ?>
        <div class="psy-report-row">
            <?php foreach ([
                ['#e9d8fd','#553c9a','👤','نام',$session->client_name ?? $session->patient_name ?? '---'],
                ['#ebf8ff','#2b6cb0','📞','شماره تماس',$session->client_phone ?? $session->patient_phone ?? '---'],
                ['#f0fff4','#2f855a','✅','تاریخ تکمیل',$completed],
                [$phy_sev['bg'],$phy_sev['color'],'⚠️','خشونت جسمی',$phy_sev['label'],$phy_sev['label_en']],
                ['#faf5ff','#6b46c1','🤝','مذاکره',(int)$scores['schema_scores']['neg'].' امتیاز'],
            ] as $c) echo \PsychAssessments\Core\ReportHTML::summary_card($c[0],$c[1],$c[2],$c[3],$c[4]); ?>
        </div>

        <?php if ( $scores['schema_scores']['phy'] > 0 || $scores['schema_scores']['sex'] > 0 || $scores['schema_scores']['inj'] > 0 ): ?>
        <div class="psy-report-alert psy-tone--danger">
            <h3 class="psy-report-alert__title psy-report-alert__title--sm">🚨 هشدار بالینی:</h3>
            <p class="psy-report-alert__text psy-report-alert__text--sm">
                گزارش خشونت جسمی، جنسی یا آسیب وجود دارد. ارزیابی ایمنی و مداخله فوری توصیه می‌شود.
            </p>
        </div>
        <?php endif; ?>

        <?php echo \PsychAssessments\Core\ReportHTML::section_header('پروفایل پنج بعد تعارض زوجین'); ?>
        <div class="psy-report-card psy-report-card--panel psy-report-chart-panel">
            <canvas id="pa-cts2-bar" class="psy-report-chart psy-report-chart--domains"></canvas>
        </div>

        <?php foreach ( $scores['schema_scores'] as $key => $val ):
            $is_danger = in_array($key, ['phy','sex','inj']);
            $color = $val > 0 && $is_danger ? '#9b2c2c' : ($val > 0 ? '#c05621' : '#2f855a');
        ?>
        <div class="psy-report-item psy-report-item--lg <?php echo esc_attr( \PsychAssessments\Core\ReportHTML::tone( (string) $color ) ); ?>">
            <div class="psy-clinical__head">
                <span class="psy-report-name-lg"><?php echo esc_html($labels[$key]); ?></span>
                <span class="psy-report-tag"><?php echo esc_html((int)$val); ?> امتیاز</span>
            </div>
        </div>
        <?php endforeach; ?>

        <div class="psy-report-refs psy-report-refs--muted">
            <b>منبع:</b> Straus et al. (1996). Journal of Family Issues, 17(3), 283–316.<br>
            <b class="psy-tone--danger psy-text">⚠️</b> ابزار بالینی حساس — اطلاعات کاملاً محرمانه است.
        </div>

        <script>
        (function(){
            function init(){
                if(typeof Chart==='undefined'){setTimeout(init,100);return;}
                var ctx=document.getElementById('pa-cts2-bar');
                if(!ctx)return;
                new Chart(ctx.getContext('2d'),{
                    type:'bar',
                    data:{
                        labels:['مذاکره','روان‌آزاری','حمله جسمی','اجبار جنسی','آسیب جسمی'],
                        datasets:[{
                            data:[
                                <?php echo intval($scores['schema_scores']['neg']); ?>,
                                <?php echo intval($scores['schema_scores']['psy']); ?>,
                                <?php echo intval($scores['schema_scores']['phy']); ?>,
                                <?php echo intval($scores['schema_scores']['sex']); ?>,
                                <?php echo intval($scores['schema_scores']['inj']); ?>
                            ],
                            backgroundColor:['#68d391','#f6ad55','#fc8181','#b794f4','#feb2b2'],
                            borderRadius:5
                        }]
                    },
                    options:{responsive:true,plugins:{legend:{display:false}},scales:{y:{min:0}}}
                });
            }
            document.addEventListener('DOMContentLoaded',init);
        })();
        </script>
        <?php
        return ob_get_clean();
    }
}
\PsychAssessments\Core\Registry::register( new CTS2() );
