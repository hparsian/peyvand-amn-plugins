<?php
namespace PsychAssessments\Tests;
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * آزمون پایش مکرر رضایت زناشویی — RAS-7
 *
 * مبانی علمی:
 * - Hendrick, S.S. (1988). A generic measure of relationship satisfaction.
 *   Journal of Marriage and the Family, 50(1), 93–98.
 * - Hendrick, S.S., Dicke, A., & Hendrick, C. (1998). The Relationship Assessment Scale.
 *   Journal of Social and Personal Relationships, 15(1), 137–142.
 * - Stackert, R.A. & Bursik, K. (2003). Why am I unsatisfied? Adult attachment style,
 *   gendered irrational relationship beliefs, and young adult romantic relationship satisfaction.
 * - Davoodvandi, M. et al. (2018). هنجاریابی RAS در جمعیت ایرانی.
 * - ۷ سوال، تک‌بُعدی، مقیاس ۱–۵
 * - cut-off < 4.0 = رضایت پایین (Hendrick, 1988)
 * - سوالات 4 و 7 معکوس هستند
 */
class RAS extends \PsychAssessments\Core\BaseTest {

    public function get_id(): string        { return 'ras'; }
    public function get_category(): string      { return 'relationship'; }
    public function get_name(): string      { return 'پایش رضایت زناشویی (RAS-7)'; }
    public function needs_chart(): bool   { return false; } // فقط progress bar — بدون Chart.js
    public function get_description(): string { return 'مقیاس ارزیابی رابطه هندریک — ۷ سوال، ابزار سریع پایش رضایت زناشویی، مناسب برای اندازه‌گیری مکرر'; }
    public function get_version(): string   { return '1.0.0'; }

    public function get_scale(): array {
        return [
            'min'    => 1,
            'max'    => 5,
            'labels' => [
                1 => 'خیلی کم / کاملاً مخالفم',
                2 => 'کم / مخالفم',
                3 => 'متوسط / نه موافق نه مخالف',
                4 => 'زیاد / موافقم',
                5 => 'خیلی زیاد / کاملاً موافقم',
            ],
        ];
    }

    public function get_questions(): array {
        return [
            1 => 'چقدر از رابطه‌تان راضی هستید؟',
            2 => 'چقدر رابطه‌تان نیازهای شما را برآورده می‌کند؟',
            3 => 'چقدر رابطه‌تان را با دیگر روابط خوب مقایسه می‌کنید؟',
            4 => 'چقدر آرزو می‌کنید که هرگز در این رابطه نبودید؟ (معکوس)',
            5 => 'چقدر رابطه‌تان مشکلات شما را برآورده می‌کند؟',
            6 => 'چقدر به شریک زندگی‌تان اعتماد دارید؟',
            7 => 'چقدر احساس می‌کنید رابطه‌تان بهتر از رابطه اکثر مردم است؟',
        ];
    }

    public function get_schema_labels(): array {
        return []; // تک‌بُعدی
    }

    public function get_domain_labels(): array {
        return [];
    }

    /** سوالات معکوس — Hendrick (1988): فقط q4 */
    private function reversed_items(): array {
        // فیکس: q7 («رابطه‌تان بهتر از اکثر مردمه؟») محتوای مثبت دارد و نباید معکوس شود.
        // معکوس کردنش نمرهٔ رضایت رو برعکس می‌کرد. آیتم واقعی معکوس Hendrick («چند مشکل
        // در رابطه دارید؟») در این نسخه از سوالات وجود ندارد؛ محتوای q5,q6,q7 نیاز به
        // بازبینی توسط متخصص دارد تا با نسخهٔ اصلی هم‌خوان بشه.
        return [4];
    }

    private function get_severity( float $avg ): array {
        return \PsychAssessments\Core\Scoring::severity( $avg, [
            ['max'=>1.99, 'label'=>'نارضایتی شدید',  'label_en'=>'Severely Dissatisfied', 'color'=>'#9b2c2c', 'bg'=>'#fff5f5', 'clinical'=>'بحران رضایت — مداخله فوری ضروری',          'range'=>'< 2.0'],
            ['max'=>2.99, 'label'=>'ناراضی',          'label_en'=>'Dissatisfied',          'color'=>'#c53030', 'bg'=>'#fff5f5', 'clinical'=>'زیر cut-off — ارزیابی جدی لازم است',      'range'=>'2.0–2.99'],
            ['max'=>3.49, 'label'=>'رضایت پایین',    'label_en'=>'Low Satisfaction',       'color'=>'#c05621', 'bg'=>'#fffaf0', 'clinical'=>'نزدیک به cut-off — پیگیری توصیه می‌شود', 'range'=>'3.0–3.49'],
            ['max'=>3.99, 'label'=>'رضایت متوسط',   'label_en'=>'Moderate Satisfaction',  'color'=>'#d69e2e', 'bg'=>'#fffff0', 'clinical'=>'در آستانه cut-off — تقویت رابطه مفید',   'range'=>'3.5–3.99'],
            ['max'=>4.49, 'label'=>'راضی',           'label_en'=>'Satisfied',              'color'=>'#276749', 'bg'=>'#f0fff4', 'clinical'=>'رابطه در وضعیت خوب قرار دارد',           'range'=>'4.0–4.49'],
            [             'label'=>'رضایت بالا',    'label_en'=>'Highly Satisfied',       'color'=>'#22543d', 'bg'=>'#f0fff4', 'clinical'=>'سطح بالای رضایت زناشویی',                'range'=>'≥ 4.5'],
        ]);
    }

    public function calculate( array $answers ): array {
        $answers = \PsychAssessments\Core\Scoring::sanitize_answers( $answers );
        $reversed = $this->reversed_items();
        $sum = 0;
        $count = 7;

        for ( $q = 1; $q <= $count; $q++ ) {
            $raw = min(5, max(1, (int)($answers[$q] ?? 3)));
            $sum += in_array($q, $reversed) ? (6 - $raw) : $raw;
        }

        $avg = round( $sum / $count, 2 );

        return [
            'sum'     => $sum,
            'avg'     => $avg,
            'max_sum' => 35,
            'pct'     => round( ($sum - 7) / 28 * 100, 1 ), // نرماله به 0-100%
        ];
    }

    public function render_report( array $data, object $session ): string {
        $avg           = $data['avg'];
        $sum           = $data['sum'];
        $pct           = $data['pct'];
        $sev           = $this->get_severity( $avg );
        $satisfied     = $avg >= 4.0;
        $completed_date = $session->completed_at ? pa_to_jalali($session->completed_at, 'Y/m/d ساعت H:i') : '---';
        $raw_answers = is_string($session->answers) ? pa_decrypt_json($session->answers) : (array)$session->answers;

        // رنگ گیج
        $gauge_color = $sev['color'];

        ob_start();
        ?>
        <!-- خلاصه -->
        <div style="display:flex;gap:12px;flex-wrap:wrap;margin-bottom:24px;">
            <?php
            $cards = [
                ['#ebf8ff','#2b6cb0','📞','شماره تماس',       $session->client_phone ?? $session->patient_phone ?? '---'],
                ['#f0fff4','#2f855a','✅','تاریخ تکمیل',       $completed_date],
                ['#fff5f5','#c53030','💯','نمره کل',           $sum . ' از ۳۵'],
                ['#fffff0','#d69e2e','📊','میانگین',           $avg . ' از ۵'],
                [$satisfied?'#f0fff4':'#fff5f5', $satisfied?'#276749':'#c53030', $satisfied?'😊':'😟', 'وضعیت', $sev['label']],
            ];
            foreach ($cards as $c) {
                echo \PsychAssessments\Core\ReportHTML::summary_card($c[0],$c[1],$c[2],$c[3],$c[4]);
            }
            ?>
        </div>

        <!-- نمره اصلی — طراحی ویژه پایش مکرر -->
        <div style="background:<?php echo esc_attr($sev['bg']); ?>;border:2px solid <?php echo esc_attr($sev['color']); ?>;border-radius:12px;padding:28px 24px;margin-bottom:24px;text-align:center;">
            <h3 style="margin:0 0 8px;color:<?php echo esc_attr($sev['color']); ?>;font-size:16px;">📋 نتیجه ارزیابی RAS-7</h3>

            <!-- عدد بزرگ -->
            <div style="margin:16px 0;display:flex;justify-content:center;align-items:baseline;gap:8px;">
                <span style="font-size:64px;font-weight:900;color:<?php echo esc_attr($sev['color']); ?>;line-height:1;"><?php echo $avg; ?></span>
                <span style="font-size:20px;color:#718096;">/ ۵</span>
            </div>

            <!-- نوار با cut-off -->
            <div style="max-width:400px;margin:0 auto;position:relative;">
                <div style="background:#edf2f7;border-radius:8px;height:18px;overflow:hidden;">
                    <div style="background:linear-gradient(90deg,#c53030,#d69e2e,#276749);height:100%;width:100%;opacity:0.25;border-radius:8px;position:absolute;top:0;left:0;"></div>
                    <div style="background:<?php echo esc_attr($sev['color']); ?>;height:100%;width:<?php echo $pct; ?>%;border-radius:8px;position:relative;z-index:1;"></div>
                </div>
                <!-- cut-off نشانگر: 4.0 از 5 = (4-1)/(5-1)*100 = 75% -->
                <div style="position:absolute;top:-4px;left:75%;width:2px;height:26px;background:#c53030;z-index:2;"></div>
                <div style="display:flex;justify-content:space-between;font-size:10px;color:#a0aec0;margin-top:6px;">
                    <span>۱ (نارضایتی)</span>
                    <span style="color:#c53030;font-weight:bold;">cut-off: ۴.۰</span>
                    <span>۵ (رضایت کامل)</span>
                </div>
            </div>

            <div style="margin-top:16px;display:inline-block;background:<?php echo esc_attr($sev['color']); ?>;color:#fff;padding:8px 24px;border-radius:20px;font-size:14px;font-weight:bold;">
                <?php echo esc_html($sev['label']); ?> — <?php echo esc_html($sev['label_en']); ?>
            </div>
            <p style="margin:14px 0 0;font-size:13px;color:#4a5568;"><?php echo esc_html($sev['clinical']); ?></p>
        </div>

        <!-- جدول پاسخ‌های سوالات -->
        <div style="background:#fff;border:1px solid #e2e8f0;border-radius:10px;padding:20px;margin-bottom:24px;">
            <h3 style="margin-top:0;font-size:15px;">📝 بررسی پاسخ‌های هر سوال</h3>
            <table style="width:100%;border-collapse:collapse;font-size:12px;">
                <thead><tr style="background:#edf2f7;">
                    <th style="text-align:right;padding:8px 10px;border:1px solid #e2e8f0;width:5%;">#</th>
                    <th style="text-align:right;padding:8px 10px;border:1px solid #e2e8f0;">سوال</th>
                    <th style="text-align:center;padding:8px 10px;border:1px solid #e2e8f0;width:12%;">نمره</th>
                    <th style="text-align:center;padding:8px 10px;border:1px solid #e2e8f0;width:30%;">نوار</th>
                </tr></thead>
                <tbody>
                <?php
                $questions = $this->get_questions();
                $reversed  = $this->reversed_items();
                $scale_labels = $this->get_scale()['labels'];
                foreach ($questions as $q => $text):
                    $raw = min(5, max(1, (int)($raw_answers[$q] ?? 3)));
                    $scored = in_array($q, $reversed) ? (6 - $raw) : $raw;
                    $q_pct = ($scored - 1) / 4 * 100;
                    $q_color = $scored >= 4 ? '#276749' : ($scored >= 3 ? '#d69e2e' : '#c53030');
                ?>
                <tr style="<?php echo ($q % 2 === 0) ? 'background:#f7fafc;' : ''; ?>">
                    <td style="padding:8px 10px;border:1px solid #e2e8f0;color:#a0aec0;text-align:center;"><?php echo esc_html((int)$q); ?></td>
                    <td style="padding:8px 10px;border:1px solid #e2e8f0;color:#4a5568;line-height:1.5;"><?php echo esc_html($text); ?></td>
                    <td style="padding:8px 10px;border:1px solid #e2e8f0;text-align:center;font-weight:bold;font-size:14px;color:<?php echo esc_attr($q_color); ?>;"><?php echo $scored; ?></td>
                    <td style="padding:8px 10px;border:1px solid #e2e8f0;">
                        <div style="background:#edf2f7;border-radius:4px;height:8px;overflow:hidden;">
                            <div style="background:<?php echo esc_attr($q_color); ?>;height:100%;width:<?php echo $q_pct; ?>%;border-radius:4px;"></div>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- کاربرد پایش مکرر -->
        <div style="background:#ebf8ff;border:1px solid #90cdf4;border-radius:10px;padding:18px 20px;margin-bottom:24px;">
            <h3 style="margin-top:0;font-size:14px;color:#2b6cb0;">🔄 راهنمای استفاده در پایش مکرر</h3>
            <p style="font-size:12px;color:#4a5568;margin:0;line-height:1.8;">
                RAS-7 به‌عنوان ابزار پایش مکرر طراحی شده است. می‌توان آن را هر ۲–۴ هفته یک‌بار تکرار کرد تا پیشرفت درمانی پیگیری شود.<br>
                <b>تغییر معنادار بالینی (RCI):</b> تفاوت ≥ ۰.۵ در میانگین بین دو اندازه‌گیری معنادار در نظر گرفته می‌شود.<br>
                <b>cut-off:</b> میانگین ≥ ۴.۰ نشان‌دهنده رضایت مطلوب است (Hendrick, 1988).
            </p>
        </div>

        <!-- راهنمای تفسیر -->
        <div style="background:#f7fafc;border:1px solid #e2e8f0;border-radius:10px;padding:20px;margin-bottom:30px;">
            <h3 style="margin-top:0;font-size:15px;">📚 راهنمای تفسیر علمی</h3>
            <p style="font-size:12px;color:#718096;margin-top:0;">Hendrick (1988) | Hendrick et al. (1998) | Davoodvandi et al. (2018)</p>
            <table style="width:100%;border-collapse:collapse;font-size:12px;">
                <thead><tr style="background:#edf2f7;">
                    <th style="text-align:right;padding:8px;border:1px solid #e2e8f0;">میانگین</th>
                    <th style="text-align:right;padding:8px;border:1px solid #e2e8f0;">وضعیت</th>
                    <th style="text-align:right;padding:8px;border:1px solid #e2e8f0;">توصیه</th>
                </tr></thead>
                <tbody>
                <?php foreach ([
                    ['< 2.0',    'نارضایتی شدید',  '#9b2c2c', 'مداخله فوری — بحران رضایت'],
                    ['2.0–2.99', 'ناراضی',          '#c53030', 'زوج‌درمانی اکیداً توصیه می‌شود'],
                    ['3.0–3.49', 'رضایت پایین',    '#c05621', 'ارزیابی عمیق‌تر + پیگیری'],
                    ['3.5–3.99', 'رضایت متوسط',   '#d69e2e', 'تقویت رابطه مفید است'],
                    ['4.0–4.49', 'راضی',           '#276749', 'وضعیت خوب — پایش ادامه داشته باشد'],
                    ['≥ 4.5',    'رضایت بالا',    '#22543d', 'سطح بالای رضایت زناشویی'],
                ] as $r): ?>
                <tr>
                    <td style="padding:7px 8px;border:1px solid #e2e8f0;font-weight:bold;color:<?php echo esc_attr($r[2]); ?>;"><?php echo esc_html($r[0]); ?></td>
                    <td style="padding:7px 8px;border:1px solid #e2e8f0;color:<?php echo esc_attr($r[2]); ?>;font-weight:bold;"><?php echo esc_html($r[1]); ?></td>
                    <td style="padding:7px 8px;border:1px solid #e2e8f0;color:#4a5568;"><?php echo esc_html($r[3]); ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- منابع -->
        <div style="background:#fff;border:1px solid #e2e8f0;border-radius:8px;padding:16px 20px;font-size:11px;color:#a0aec0;line-height:1.8;">
            <b style="color:#4a5568;">منابع:</b><br>
            Hendrick, S.S. (1988). <i>Journal of Marriage and the Family, 50</i>(1), 93–98.<br>
            Hendrick, S.S., Dicke, A., & Hendrick, C. (1998). <i>Journal of Social and Personal Relationships, 15</i>(1), 137–142.<br>
            Davoodvandi, M. et al. (2018). Psychometric properties of RAS — Iranian sample.<br>
            <br>
            <b style="color:#c53030;">⚠️</b> این گزارش ابزار کمک‌بالینی است و به تنهایی مبنای تشخیص رسمی نیست.
        </div>
        <?php
        return ob_get_clean();
    }
}

\PsychAssessments\Core\Registry::register( new RAS() );
