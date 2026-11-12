<?php
namespace PsychAssessments\Tests;
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * BIS-11: Barratt Impulsiveness Scale (Patton et al., 1995)
 *
 * - Patton, J.H., Stanford, M.S., & Barratt, E.S. (1995). Factor structure of the Barratt
 *   Impulsiveness Scale. Journal of Clinical Psychology, 51(6), 768–774.
 * - هنجاریابی ایران: زرگر و همکاران (۱۳۸۷)
 * - ۳۰ سوال، ۳ بعد: توجهی، حرکتی، برنامه‌ریزی نشده
 * - مقیاس: ۱–۴ (به ندرت/هیچ‌وقت تا تقریباً همیشه/همیشه)
 */
class BIS11 extends \PsychAssessments\Core\BaseTest {

    public function get_id(): string        { return 'bis11'; }
    public function get_category(): string      { return 'mental_health'; }
    public function get_name(): string      { return 'تکانشگری بارات (BIS-11)'; }
    public function needs_chart(): bool   { return true; }  // نمودار Chart.js

    public function get_description(): string { return 'مقیاس تکانشگری بارات — ۳۰ سوال، ۳ بعد: توجهی، حرکتی، برنامه‌ریزی نشده'; }
    public function get_version(): string   { return '1.0.0'; }

    public function get_scale(): array {
        return [
            'min'    => 1,
            'max'    => 4,
            'labels' => [
                1 => 'به ندرت / هیچ‌وقت',
                2 => 'گاهی اوقات',
                3 => 'اغلب',
                4 => 'تقریباً همیشه / همیشه',
            ],
        ];
    }

    // سوالات معکوس
    private function reverse(): array {
        // Patton et al. (1995) — آیتم‌های معکوس استاندارد
        return [1, 7, 8, 9, 10, 12, 13, 15, 20, 29, 30];
    }

    // ابعاد سه‌گانه
    private function subscales(): array {
        // فیکس: گروه‌بندی قبلی سوالات ۶،۱۶،۲۱،۲۳،۲۴،۲۶،۳۰ رو اشتباه در np قرار داده بود
        // (باید در att یا mot باشند) — طبق ساختار عاملی رسمی Patton et al. (1995):
        return [
            'att' => [5, 6, 9, 11, 20, 24, 26, 28],              // توجهی (۸ سوال)
            'mot' => [2, 3, 4, 16, 17, 19, 21, 22, 23, 25, 30],  // حرکتی (۱۱ سوال)
            'np'  => [1, 7, 8, 10, 12, 13, 14, 15, 18, 27, 29],  // برنامه‌ریزی نشده (۱۱ سوال)
        ];
    }

    public function get_questions(): array {
        return [
            1  => 'برنامه‌های آینده‌ام را با دقت برنامه‌ریزی می‌کنم',
            2  => 'کارها را بدون فکر کردن انجام می‌دهم',
            3  => 'سریع تصمیم می‌گیرم',
            4  => 'بی‌دغدغه هستم',
            5  => 'افکارم مرتب هستند',
            6  => 'خودانگیخته هستم',
            7  => 'در حین فکر کردن از موضوع منحرف می‌شوم',
            8  => 'تمرکز کردن برایم آسان است',
            9  => 'قبل از انجام هر کار صرفه‌جویی می‌کنم',
            10 => 'قبل از عمل کردن دقیق فکر می‌کنم',
            11 => 'جلسات کسل‌کننده را دوست دارم',
            12 => 'در آینده امنیت کاری دارم',
            13 => 'گفتنی‌هایم را قبل از اینکه تمام شود می‌گویم',
            14 => 'دوست دارم فکر کنم',
            15 => 'دوست دارم پازل‌های پیچیده حل کنم',
            16 => 'آینده‌نگر هستم',
            17 => 'حرف‌هایم را بدون فکر می‌زنم',
            18 => 'عدم تغییر مرا آزار می‌دهد',
            19 => 'بدون فکر عمل می‌کنم',
            20 => 'با فکر قبلی تصمیم می‌گیرم',
            21 => 'عوض کردن علاقه‌هایم برایم آسان است',
            22 => 'بدون فکر کردن خرید می‌کنم',
            23 => 'فقط در لحظه حال فکر می‌کنم',
            24 => 'می‌توانم روی یک موضوع تمرکز کنم',
            25 => 'ناآگاهانه پول خرج می‌کنم',
            26 => 'اغلب بدون فکر صحبت می‌کنم',
            27 => 'آینده‌ام را می‌توانم پیش‌بینی کنم',
            28 => 'هنگام فکر کردن به موضوعات بی‌ربط فکر می‌کنم',
            29 => 'بیشتر به آنچه در لحظه حال است علاقه دارم تا آینده',
            30 => 'ناراحتم وقتی باید به مشکلات پیچیده فکر کنم',
        ];
    }

    public function get_schema_labels(): array {
        return [
            'att' => 'تکانشگری توجهی',
            'mot' => 'تکانشگری حرکتی',
            'np'  => 'تکانشگری برنامه‌ریزی نشده',
        ];
    }

    public function get_domain_labels(): array { return []; }

    private function severity( int $total ): array {
        // نقاط برش اصلی: Patton et al. (1995) + تأییدیه Spinella (2007)
        // cut-off بالینی ≥72 برای جمعیت غیربالینی بزرگسال — برخی پژوهش‌های ایرانی ≥68 پیشنهاد می‌دهند
        if ( $total < 52 ) return ['label'=>'پایین',    'color'=>'#2f855a','bg'=>'#f0fff4','clinical'=>'تکانشگری در محدوده پایین — کنترل خوب (زیر نهک پنجم)'];
        if ( $total < 68 ) return ['label'=>'متوسط',   'color'=>'#d69e2e','bg'=>'#fffff0','clinical'=>'تکانشگری متوسط — در محدوده جمعیت عمومی'];
        if ( $total < 83 ) return ['label'=>'بالا',     'color'=>'#c05621','bg'=>'#fff5f0','clinical'=>'بالاتر از cut-off بالینی (≥68) — پیگیری توصیه می‌شود'];
        return                     ['label'=>'خیلی بالا','color'=>'#9b2c2c','bg'=>'#fff5f5','clinical'=>'تکانشگری شدید (بالاتر از نهک نودم) — مداخله ضروری'];
    }

    public function calculate( array $answers ): array {
        $answers = \PsychAssessments\Core\Scoring::sanitize_answers( $answers );
        $reverse    = $this->reverse();
        $subscales  = $this->subscales();
        $total      = 0;

        $schema_scores = [];
        foreach ( $subscales as $key => $qs ) {
            $sub = 0;
            foreach ( $qs as $q ) {
                $v = $answers[$q] ?? 1;
                $sub += in_array($q, $reverse) ? (5 - $v) : $v;
            }
            $schema_scores[$key] = $sub;
        }

        foreach ( range(1,30) as $i ) {
            $v = $answers[$i] ?? 1;
            $total += in_array($i, $reverse) ? (5 - $v) : $v;
        }

        return [
            'schema_scores' => array_map('floatval', $schema_scores),
            'total'         => $total,
            'att'           => $schema_scores['att'],
            'mot'           => $schema_scores['mot'],
            'np'            => $schema_scores['np'],
            'avg_total'     => (float)$total,
        ];
    }

    public function render_report( array $scores, object $session ): string {
        $completed = pa_to_jalali( $session->completed_at, 'Y/m/d ساعت H:i' );
        $sev       = $this->severity( $scores['total'] );
        $labels    = $this->get_schema_labels();

        ob_start(); ?>
        <div style="display:flex;gap:12px;flex-wrap:wrap;margin-bottom:24px;">
            <?php foreach ([
                ['#e9d8fd','#553c9a','👤','نام',$session->client_name ?? $session->patient_name ?? '---'],
                ['#ebf8ff','#2b6cb0','📞','شماره تماس',$session->client_phone ?? $session->patient_phone ?? '---'],
                ['#f0fff4','#2f855a','✅','تاریخ تکمیل',$completed],
                [$sev['bg'],$sev['color'],'⚡','نمره کل BIS-11',$scores['total'].' از ۱۲۰'],
                [$sev['bg'],$sev['color'],'🏷','سطح تکانشگری',$sev['label']],
            ] as $c) echo \PsychAssessments\Core\ReportHTML::summary_card($c[0],$c[1],$c[2],$c[3],$c[4]); ?>
        </div>

        <div style="background:<?php echo esc_attr($sev['bg']); ?>;border:2px solid <?php echo esc_attr($sev['color']); ?>;border-radius:10px;padding:20px;margin-bottom:24px;">
            <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;">
                <div>
                    <h3 style="margin:0 0 6px;color:<?php echo esc_attr($sev['color']); ?>;">نمره کل: <?php echo esc_html((int)$scores['total']); ?> از ۱۲۰</h3>
                    <p style="margin:0;font-size:13px;color:#4a5568;"><?php echo esc_html($sev['clinical']); ?></p>
                </div>
                <?php echo \PsychAssessments\Core\ReportHTML::score_badge((float)$scores['total'], $sev); ?>
            </div>
            <div style="margin-top:14px;background:#edf2f7;border-radius:6px;height:10px;overflow:hidden;">
                <div style="background:<?php echo esc_attr($sev['color']); ?>;height:100%;width:<?php echo \PsychAssessments\Core\Scoring::percent($scores['total'],30,120); ?>%;border-radius:6px;"></div>
            </div>
            <div style="display:flex;justify-content:space-between;font-size:10px;color:#a0aec0;margin-top:4px;">
                <span>۳۰ پایین</span><span style="color:#d69e2e;">۵۲ متوسط</span><span style="color:#c05621;">۷۱ بالا</span><span style="color:#9b2c2c;">۹۲ خیلی بالا</span><span>۱۲۰</span>
            </div>
        </div>

        <?php echo \PsychAssessments\Core\ReportHTML::section_header('پروفایل سه بعد تکانشگری'); ?>
        <div style="background:#fff;padding:20px;border-radius:10px;box-shadow:0 2px 10px rgba(0,0,0,0.08);margin-bottom:24px;">
            <canvas id="pa-bis-bar" style="max-height:220px;"></canvas>
        </div>

        <?php
        $descs = [
            'att' => 'ناتوانی در تمرکز توجه — حواس‌پرتی، پردازش سریع اطلاعات',
            'mot' => 'عمل کردن بدون فکر — تصمیم‌های سریع و بدون برنامه',
            'np'  => 'نداشتن برنامه برای آینده — توجه به لحظه حال به جای آینده',
        ];
        $maxes = ['att'=>32,'mot'=>44,'np'=>44]; // فیکس: بر اساس تعداد صحیح سوالات هر بعد (۸/۱۱/۱۱ × حداکثر ۴)
        foreach ( $scores['schema_scores'] as $key => $val ):
            $sub_sev = $this->severity( (int)$val * 4 ); ?>
        <div style="background:#fff;border:1px solid #e2e8f0;border-radius:10px;padding:18px;margin-bottom:14px;">
            <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;margin-bottom:10px;">
                <div>
                    <strong style="font-size:14px;"><?php echo esc_html($labels[$key]); ?></strong>
                    <p style="margin:4px 0 0;font-size:12px;color:#718096;"><?php echo esc_html($descs[$key]); ?></p>
                </div>
                <span style="background:<?php echo esc_attr($sub_sev['bg']); ?>;color:<?php echo esc_attr($sub_sev['color']); ?>;padding:4px 12px;border-radius:20px;font-size:12px;font-weight:bold;">
                    <?php echo esc_html((int)$val); ?> از <?php echo esc_html($maxes[$key]); ?>
                </span>
            </div>
            <div style="background:#edf2f7;border-radius:6px;height:8px;overflow:hidden;">
                <div style="background:<?php echo esc_attr($sub_sev['color']); ?>;height:100%;width:<?php echo round(($val/$maxes[$key])*100); ?>%;border-radius:6px;"></div>
            </div>
        </div>
        <?php endforeach; ?>

        <div style="background:#fff;border:1px solid #e2e8f0;border-radius:8px;padding:14px 20px;font-size:11px;color:#a0aec0;line-height:1.8;margin-top:10px;">
            <b style="color:#4a5568;">منبع:</b> Patton et al. (1995). Journal of Clinical Psychology, 51(6). | هنجاریابی ایران: زرگر و همکاران (۱۳۸۷).<br>
            <b style="color:#c53030;">⚠️</b> ابزار کمک‌بالینی — مبنای تشخیص رسمی نیست.
        </div>

        <script>
        (function(){
            function init(){
                if(typeof Chart==='undefined'){setTimeout(init,100);return;}
                var ctx=document.getElementById('pa-bis-bar');
                if(!ctx)return;
                new Chart(ctx.getContext('2d'),{
                    type:'bar',
                    data:{
                        labels:['تکانشگری توجهی','تکانشگری حرکتی','برنامه‌ریزی نشده'],
                        datasets:[{
                            data:[<?php echo intval($scores['att']); ?>,<?php echo intval($scores['mot']); ?>,<?php echo intval($scores['np']); ?>],
                            backgroundColor:['#90cdf4','#f6ad55','#fc8181'],
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
\PsychAssessments\Core\Registry::register( new BIS11() );
