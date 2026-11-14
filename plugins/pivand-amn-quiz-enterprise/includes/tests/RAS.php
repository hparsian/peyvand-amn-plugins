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

        // فاز ۱۴.۴: رنگ گیج و نوارها با کلاس تُن
        $sev_tone = \PsychAssessments\Core\ReportHTML::tone( $sev['color'] );

        ob_start();
        ?>
        <!-- خلاصه -->
        <div class="psy-report-row">
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
        <div class="psy-report-hero--center <?php echo esc_attr( $sev_tone ); ?>">
            <h3 class="psy-report-hero__title">📋 نتیجه ارزیابی RAS-7</h3>

            <!-- عدد بزرگ -->
            <div class="psy-report-hero__row">
                <span class="psy-report-hero__number"><?php echo $avg; ?></span>
                <span class="psy-report-hero__unit">/ ۵</span>
            </div>

            <!-- نوار با cut-off -->
            <div class="psy-report-gauge psy-report-gauge--gradient <?php echo esc_attr( $sev_tone ); ?>">
                <div class="psy-report-gauge__track">
                    <div class="psy-report-gauge__fill <?php echo esc_attr( \PsychAssessments\Core\ReportHTML::pct_class( (float) $pct ) ); ?>"></div>
                </div>
                <!-- cut-off نشانگر: 4.0 از 5 = (4-1)/(5-1)*100 = 75% -->
                <div class="psy-report-gauge__cutoff"></div>
                <div class="psy-report-scale">
                    <span>۱ (نارضایتی)</span>
                    <span class="psy-report-scale__mark psy-tone--danger psy-text">cut-off: ۴.۰</span>
                    <span>۵ (رضایت کامل)</span>
                </div>
            </div>

            <div class="psy-report-pill psy-report-pill--spaced"><?php echo esc_html($sev['label']); ?> — <?php echo esc_html($sev['label_en']); ?></div>
            <p class="psy-report-hero__note"><?php echo esc_html($sev['clinical']); ?></p>
        </div>

        <!-- جدول پاسخ‌های سوالات -->
        <div class="psy-report-panel">
            <h3 class="psy-report-panel__title">📝 بررسی پاسخ‌های هر سوال</h3>
            <table class="psy-report-table psy-report-table--zebra">
                <thead><tr>
                    <th class="psy-report-table__head psy-report-table__head--strong psy-report-table__cell--start">#</th>
                    <th class="psy-report-table__head psy-report-table__head--strong psy-report-table__cell--start">سوال</th>
                    <th class="psy-report-table__head psy-report-table__head--strong psy-report-table__cell--center">نمره</th>
                    <th class="psy-report-table__head psy-report-table__head--strong psy-report-table__cell--center">نوار</th>
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
                    $q_tone  = \PsychAssessments\Core\ReportHTML::tone( $q_color );
                ?>
                <tr>
                    <td class="psy-report-table__cell psy-report-table__cell--num"><?php echo esc_html((int)$q); ?></td>
                    <td class="psy-report-table__cell psy-report-table__cell--q"><?php echo esc_html($text); ?></td>
                    <td class="psy-report-table__cell psy-report-table__cell--score <?php echo esc_attr( $q_tone ); ?> psy-text"><?php echo $scored; ?></td>
                    <td class="psy-report-table__cell">
                        <?php echo \PsychAssessments\Core\ReportHTML::progress_bar( (float) $q_pct, $q_color ); ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- کاربرد پایش مکرر -->
        <div class="psy-report-note-box">
            <h3 class="psy-report-note-box__title">🔄 راهنمای استفاده در پایش مکرر</h3>
            <p class="psy-report-note-box__text">
                RAS-7 به‌عنوان ابزار پایش مکرر طراحی شده است. می‌توان آن را هر ۲–۴ هفته یک‌بار تکرار کرد تا پیشرفت درمانی پیگیری شود.<br>
                <b>تغییر معنادار بالینی (RCI):</b> تفاوت ≥ ۰.۵ در میانگین بین دو اندازه‌گیری معنادار در نظر گرفته می‌شود.<br>
                <b>cut-off:</b> میانگین ≥ ۴.۰ نشان‌دهنده رضایت مطلوب است (Hendrick, 1988).
            </p>
        </div>

        <!-- راهنمای تفسیر -->
        <div class="psy-report-panel psy-report-panel--soft">
            <h3 class="psy-report-panel__title">📚 راهنمای تفسیر علمی</h3>
            <p class="psy-report-caption psy-report-desc">Hendrick (1988) | Hendrick et al. (1998) | Davoodvandi et al. (2018)</p>
            <table class="psy-report-table">
                <thead><tr>
                    <th class="psy-report-table__head psy-report-table__head--strong psy-report-table__cell--start">میانگین</th>
                    <th class="psy-report-table__head psy-report-table__head--strong psy-report-table__cell--start">وضعیت</th>
                    <th class="psy-report-table__head psy-report-table__head--strong psy-report-table__cell--start">توصیه</th>
                </tr></thead>
                <tbody>
                <?php foreach ([
                    ['< 2.0',    'نارضایتی شدید',  '#9b2c2c', 'مداخله فوری — بحران رضایت'],
                    ['2.0–2.99', 'ناراضی',          '#c53030', 'زوج‌درمانی اکیداً توصیه می‌شود'],
                    ['3.0–3.49', 'رضایت پایین',    '#c05621', 'ارزیابی عمیق‌تر + پیگیری'],
                    ['3.5–3.99', 'رضایت متوسط',   '#d69e2e', 'تقویت رابطه مفید است'],
                    ['4.0–4.49', 'راضی',           '#276749', 'وضعیت خوب — پایش ادامه داشته باشد'],
                    ['≥ 4.5',    'رضایت بالا',    '#22543d', 'سطح بالای رضایت زناشویی'],
                ] as $r):
                    $r_tone = \PsychAssessments\Core\ReportHTML::tone( $r[2] ); ?>
                <tr>
                    <td class="psy-report-table__cell <?php echo esc_attr( $r_tone ); ?> psy-text psy-report-bold"><?php echo esc_html($r[0]); ?></td>
                    <td class="psy-report-table__cell <?php echo esc_attr( $r_tone ); ?> psy-text psy-report-bold"><?php echo esc_html($r[1]); ?></td>
                    <td class="psy-report-table__cell psy-report-muted"><?php echo esc_html($r[3]); ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- منابع -->
        <div class="psy-report-refs">
            <b>منابع:</b><br>
            Hendrick, S.S. (1988). <i>Journal of Marriage and the Family, 50</i>(1), 93–98.<br>
            Hendrick, S.S., Dicke, A., &amp; Hendrick, C. (1998). <i>Journal of Social and Personal Relationships, 15</i>(1), 137–142.<br>
            Davoodvandi, M. et al. (2018). Psychometric properties of RAS — Iranian sample.<br>
            <br>
            <b class="psy-tone--danger psy-text">⚠️</b> این گزارش ابزار کمک‌بالینی است و به تنهایی مبنای تشخیص رسمی نیست.
        </div>
        <?php
        return ob_get_clean();
    }
}

\PsychAssessments\Core\Registry::register( new RAS() );
