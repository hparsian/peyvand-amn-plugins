<?php

declare(strict_types=1);

namespace ZoojDarman\ClientPortal;

if (!defined('ABSPATH')) {
    exit;
}

final class Survey
{
    private Security $security;

    public function __construct(Security $security)
    {
        $this->security = $security;

        add_action('wp_ajax_zdp_submit_survey', [$this, 'handle_submit']);
        add_action('wp_ajax_nopriv_zdp_submit_survey', [$this, 'handle_submit']);
        add_shortcode('zdp_survey_page', [$this, 'render_shortcode']);

        // شورت‌کد نمایش عمومی نظرات تاییدشده. شورت‌کدهای قدیمی
        // (approved_testimonials / zdp_approved_testimonials) عمداً حذف
        // شده‌اند و دیگر ثبت نمی‌شوند — فقط همین یک نام جدید معتبر است.
        add_shortcode('zdp_testimonials', [$this, 'render_testimonials_shortcode']);

        // شماره‌ی پشتیبانی مستقیم داخل HTML صفحه‌ی عمومی [zdp_survey_page] چاپ
        // می‌شود؛ با تغییرش در پیشخوان باید کش صفحات پاک شود (همان الگوی
        // zdp_services_list در class-booking.php).
        add_action('update_option_zdp_support_phone', [Cache_Guard::class, 'purge_pages']);
        add_action('add_option_zdp_support_phone', [Cache_Guard::class, 'purge_pages']);

        // سوال‌های نظرسنجی هم از پیشخوان ویرایش می‌شوند و داخل HTML فرم صفحه‌ی
        // عمومی چاپ می‌شوند.
        add_action('update_option_zdp_survey_questions', [Cache_Guard::class, 'purge_pages']);
        add_action('add_option_zdp_survey_questions', [Cache_Guard::class, 'purge_pages']);
    }

    /**
     * سوالات پیش‌فرض نظرسنجی — همان سه سوال اصلی custom-psychology-portal
     * (cpp_survey_questions)، اینجا به‌عنوان fallback وقتی هنوز از پیشخوان
     * چیزی تنظیم نشده.
     *
     * فیلد 'type': 'choice' (پیش‌فرض، چندگزینه‌ای — dropdown از options) یا
     * 'text' (تشریحی — کاربر آزادانه تایپ می‌کند، options نادیده گرفته می‌شود).
     *
     * @return array<string,array{label:string,type:string,options:string[]}>
     */
    public static function default_questions_config(): array
    {
        return [
            'q1' => [
                'label'   => 'رضایت شما از برخورد و تخصص مشاور چقدر بود؟',
                'type'    => 'choice',
                'options' => ['عالی', 'خوب', 'متوسط', 'ضعیف'],
            ],
            'q2' => [
                'label'   => 'آیا فضای جلسه (یا پلتفرم مشاوره آنلاین) را امن، آرام و مناسب یافتید؟',
                'type'    => 'choice',
                'options' => ['بله، کاملاً', 'تا حدودی', 'خیر، مناسب نبود'],
            ],
            'q3' => [
                'label'   => 'آیا زمان‌بندی و هماهنگی نوبت‌ها از طرف کلینیک به درستی انجام شد؟',
                'type'    => 'choice',
                'options' => ['بله، بسیار دقیق بود', 'خیر، تاخیر یا تداخل وجود داشت'],
            ],
        ];
    }

    /**
     * @return array<string,array{label:string,type:string,options:string[]}>
     */
    public static function get_questions_config(): array
    {
        $defaults = self::default_questions_config();
        $saved    = get_option('zdp_survey_questions', []);

        if (!is_array($saved)) {
            return $defaults;
        }

        foreach ($defaults as $key => $default_q) {
            if (empty($saved[$key]['label'])) {
                $saved[$key] = $default_q;
                continue;
            }

            // سازگاری عقب‌رو: نسخه‌های قبلی این option هرگز 'type' نداشتند —
            // نبودش یعنی همان رفتار قدیمی (چندگزینه‌ای) حفظ شود.
            $type = in_array($saved[$key]['type'] ?? '', ['choice', 'text'], true)
                ? $saved[$key]['type']
                : 'choice';

            $options = ($type === 'choice' && !empty($saved[$key]['options']) && is_array($saved[$key]['options']))
                ? $saved[$key]['options']
                : $default_q['options'];

            $saved[$key] = [
                'label'   => $saved[$key]['label'],
                'type'    => $type,
                'options' => $options,
            ];
        }

        return $saved;
    }

    /**
     * حداکثر تعداد سوالات اضافیِ قابل تعریف (فراتر از سه سوال اصلی q1-q3).
     * یک سقف ثابت به‌جای افزودن/حذف پویا با جاوااسکریپت انتخاب شده تا فرم
     * تنظیمات ساده و بدون وابستگی به JS اضافی بماند؛ ۷ تای خالی یعنی در
     * مجموع تا ۱۰ سوال (۳ اصلی + ۷ اضافی) قابل تعریف است — برای یک
     * نظرسنجی رضایت‌سنجی مشتری بیش از کافی است.
     */
    public const MAX_EXTRA_QUESTIONS = 7;

    /**
     * سوالات اضافی (فراتر از q1-q3)، در option جدا (zdp_survey_extra_questions)
     * ذخیره می‌شوند تا ساختار قدیمی zdp_survey_questions و ستون‌های ثابت
     * دیتابیس (q1,q2,q3) دست‌نخورده بماند — افزودن این قابلیت به هیچ
     * نصب موجودی آسیب نمی‌زند (اگر تنظیم نشده باشد، آرایه‌ی خالی برمی‌گردد).
     * پاسخ‌های این سوالات در ستون JSON جدید extra_answers ذخیره می‌شوند، نه
     * در ستون‌های ثابت جدول.
     *
     * @return array<int,array{label:string,type:string,options:string[]}> فقط سوالاتی که label دارند (خالی‌ها فیلتر می‌شوند)
     */
    public static function get_extra_questions_config(): array
    {
        $saved = get_option('zdp_survey_extra_questions', []);
        if (!is_array($saved)) {
            return [];
        }

        $result = [];
        foreach (array_slice($saved, 0, self::MAX_EXTRA_QUESTIONS) as $i => $q) {
            if (empty($q['label']) || !is_string($q['label'])) {
                continue;
            }
            $type    = in_array($q['type'] ?? '', ['choice', 'text'], true) ? $q['type'] : 'choice';
            $options = ($type === 'choice' && !empty($q['options']) && is_array($q['options'])) ? array_values($q['options']) : [];

            if ($type === 'choice' && empty($options)) {
                // سوال چندگزینه‌ای بدون هیچ گزینه‌ای قابل نمایش/اعتبارسنجی نیست؛
                // به‌جای نمایش یک dropdown خالی، رد می‌شود.
                continue;
            }

            $result['extra' . $i] = [
                'label'   => sanitize_text_field($q['label']),
                'type'    => $type,
                'options' => $options,
            ];
        }

        return $result;
    }


    public function handle_submit(): void
    {
        $this->security->verify_ajax_nonce();

        $ip = get_client_ip();
        if (!$this->security->allow('survey_' . md5($ip), 5, 3600)) {
            wp_send_json_error(['message' => 'محدودیت ارسال نظرسنجی. لطفاً بعداً تلاش کنید.']);
        }

        $questions = self::get_questions_config();
        $answers   = [];

        foreach ($questions as $key => $q) {
            $raw = isset($_POST[$key]) ? wp_unslash($_POST[$key]) : '';

            if (($q['type'] ?? 'choice') === 'text') {
                // سوال تشریحی: هر متن غیرخالی معتبر است (بدون محدودیت به یک
                // لیست گزینه‌ی ثابت)؛ فقط طول منطقی نگه داشته می‌شود تا کسی
                // با متن بسیار طولانی دیتابیس را پر نکند.
                $value = mb_substr(sanitize_textarea_field($raw), 0, 3000);
                if (trim($value) === '') {
                    wp_send_json_error(['message' => 'لطفاً به تمام سوالات پاسخ دهید.']);
                }
            } else {
                // چندگزینه‌ای: مقدار باید دقیقاً یکی از گزینه‌های همان سوال
                // (طبق تنظیمات فعلی پیشخوان) باشد، وگرنه مقدار دلخواه/جعلی
                // می‌تواند وارد دیتابیس شود.
                $value = sanitize_text_field($raw);
                if ($value === '' || !in_array($value, $q['options'], true)) {
                    wp_send_json_error(['message' => 'لطفاً به تمام سوالات پاسخ دهید.']);
                }
            }

            $answers[$key] = $value;
        }

        // سوالات اضافیِ اختیاری (فراتر از q1-q3، اگر ادمین تعریف کرده باشد).
        // این‌ها برخلاف q1-q3 اجباری نیستند تا افزودن یک سوال جدید، فرم را
        // برای مراجعینی که صفحه را قبل از رفرش باز نگه داشته‌اند خراب نکند؛
        // هرکدام که خالی فرستاده شود ساده رد می‌شود (نه خطا).
        $extra_questions = self::get_extra_questions_config();
        $extra_answers   = [];

        foreach ($extra_questions as $key => $q) {
            $raw = isset($_POST['extra'][$key]) ? wp_unslash($_POST['extra'][$key]) : '';

            if (($q['type'] ?? 'choice') === 'text') {
                $value = mb_substr(sanitize_textarea_field($raw), 0, 3000);
            } else {
                $value = sanitize_text_field($raw);
                if ($value !== '' && !in_array($value, $q['options'], true)) {
                    $value = ''; // گزینه‌ی نامعتبر/جعلی؛ نادیده گرفته می‌شود نه اینکه کل ارسال شکست بخورد
                }
            }

            if (trim($value) !== '') {
                $extra_answers[$key] = ['label' => $q['label'], 'answer' => $value];
            }
        }

        $suggestions = isset($_POST['suggestions']) ? sanitize_textarea_field(wp_unslash($_POST['suggestions'])) : '';
        $consent     = !empty($_POST['consent']) ? 1 : 0;

        // امتیاز ستاره‌ای ۱ تا ۵ — اختیاری (نه اجباری) تا اگر جای دیگری این
        // فرم قدیمی را کش کرده یا خودش را سفارشی‌سازی کرده، ارسال نشدنش کل
        // نظرسنجی را رد نکند. هر مقدار خارج از ۱ تا ۵ (یا خالی/دستکاری‌شده)
        // بی‌صدا نادیده گرفته می‌شود، نه اینکه در دیتابیس یک عدد جعلی بنشیند.
        $rating = null;
        if (isset($_POST['rating']) && $_POST['rating'] !== '') {
            $rating_raw = (int) $_POST['rating'];
            if ($rating_raw >= 1 && $rating_raw <= 5) {
                $rating = $rating_raw;
            }
        }

        global $wpdb;
        $inserted = $wpdb->insert(Installer::table('surveys'), [
            'q1'            => $answers['q1'] ?? '',
            'q2'            => $answers['q2'] ?? '',
            'q3'            => $answers['q3'] ?? '',
            'suggestions'   => $suggestions, // بدون ذخیره IP/شناسه کاربر — نظرسنجی واقعاً بی‌نام می‌ماند
            'rating'        => $rating,
            'extra_answers' => !empty($extra_answers) ? wp_json_encode($extra_answers, JSON_UNESCAPED_UNICODE) : null,
            'consent'       => $consent,
            'approved'      => 0,
            'created_at'    => now_utc(),
        ], ['%s', '%s', '%s', '%s', '%d', '%s', '%d', '%d', '%s']);

        if ($inserted === false) {
            // اگر INSERT واقعاً شکست بخورد (مثلاً ستون ناهماهنگ یا خطای دیتابیس)،
            // قبلاً بدون هیچ بررسی‌ای پیام موفقیت نمایش داده می‌شد و کاربر و
            // مدیر هردو گمان می‌کردند نظر ثبت شده، درحالی‌که هیچ رکوردی در جدول
            // ذخیره نمی‌شد. حالا خطای واقعی دیتابیس لاگ و به کاربر پیام خطا داده می‌شود.
            error_log('ZDP Survey insert failed: ' . $wpdb->last_error);
            wp_send_json_error(['message' => 'خطایی در ثبت نظرسنجی رخ داد. لطفاً دوباره تلاش کنید یا با پشتیبانی تماس بگیرید.']);
        }

        wp_send_json_success(['message' => 'از مشارکت شما سپاسگزاریم.']);
    }

    /**
     * فقط بدنه‌ی فرم نظرسنجی: سوالات، پیشنهاد، رضایت و دکمه‌ی ارسال —
     * بدون هیچ چیدمان صفحه‌ی مستقل (بدون هدر خوش‌آمد/رینگ، بدون فوتر
     * شماره تماس/بازگشت). این متد تنها منبع مارک‌آپ فرم است و هم توسط
     * render_shortcode() (صفحه‌ی مستقل ثبت نظر) و هم توسط
     * Client_Panel::render_survey_tab() (تب نظرسنجی داخل پنل) صدا زده
     * می‌شود، تا:
     *   ۱) ظاهر فرم (کارت سوالات، پیل‌های انتخاب، دکمه‌ها) در هر دو
     *      جا دقیقاً یکسان باشد — یک پیاده‌سازی، نه دو نسخه‌ی موازی؛
     *   ۲) هدر/فوتری که خودِ پنل از قبل دارد (شماره پشتیبانی، لینک
     *      بازگشت) دوباره از داخل این فرم تکرار نشود.
     */
    public function render_form_only(): string
    {
        wp_enqueue_style('zdp-panel');
        wp_enqueue_script('zdp-panel');

        $questions       = self::get_questions_config();
        $extra_questions = self::get_extra_questions_config();
        $qnum            = 0;

        ob_start();
        ?>
        <style>
            /* امتیاز ستاره‌ای — استایل مستقل و بدون تداخل با استایل بقیه فرم؛
               تکنیک استاندارد CSS-only (بدون جاوااسکریپت): ترتیب اینپوت‌ها در
               HTML از ۵ به ۱ است تا سیبلینگ سلکتور ~ درست کار کند. */
            .zdp-star-rating{ display:flex; flex-direction:row; gap:6px; font-size:30px; line-height:1; }
            .zdp-star-rating input{ position:absolute; opacity:0; width:0; height:0; }
            .zdp-star-rating label{ color:#d8d2c2; cursor:pointer; transition:color .15s ease, transform .1s ease; }
            .zdp-star-rating label:hover{ transform:scale(1.08); }
            .zdp-star-rating input:checked ~ label,
            .zdp-star-rating label:hover,
            .zdp-star-rating label:hover ~ label{ color:#e0a52c; }
            .zdp-star-rating input:focus-visible + label{ outline:2px solid currentColor; outline-offset:2px; border-radius:4px; }
        </style>
        <form id="zdp-survey-form" class="zdp-form zdp-survey-form">

            <?php foreach ($questions as $key => $q) : $qnum++; ?>
                <div class="zdp-survey-q">
                    <span class="zdp-survey-qnum" aria-hidden="true"><?php echo (int) $qnum; ?></span>
                    <div class="zdp-survey-qbody">
                        <label class="zdp-survey-qlabel"><?php echo esc_html($q['label']); ?></label>
                        <?php if (($q['type'] ?? 'choice') === 'text') : ?>
                            <textarea name="<?php echo esc_attr($key); ?>" rows="3" required></textarea>
                        <?php else : ?>
                            <div class="zdp-choice-group" role="radiogroup">
                                <?php foreach ($q['options'] as $opt) : ?>
                                    <label class="zdp-choice-pill">
                                        <input type="radio" name="<?php echo esc_attr($key); ?>" value="<?php echo esc_attr($opt); ?>" required>
                                        <span><?php echo esc_html($opt); ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>

            <?php foreach ($extra_questions as $key => $q) : $qnum++; ?>
                <div class="zdp-survey-q">
                    <span class="zdp-survey-qnum" aria-hidden="true"><?php echo (int) $qnum; ?></span>
                    <div class="zdp-survey-qbody">
                        <label class="zdp-survey-qlabel"><?php echo esc_html($q['label']); ?></label>
                        <?php if (($q['type'] ?? 'choice') === 'text') : ?>
                            <textarea name="extra[<?php echo esc_attr($key); ?>]" rows="3"></textarea>
                        <?php else : ?>
                            <div class="zdp-choice-group" role="radiogroup">
                                <?php foreach ($q['options'] as $opt) : ?>
                                    <label class="zdp-choice-pill">
                                        <input type="radio" name="extra[<?php echo esc_attr($key); ?>]" value="<?php echo esc_attr($opt); ?>">
                                        <span><?php echo esc_html($opt); ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>

            <div class="zdp-survey-q">
                <span class="zdp-survey-qnum" aria-hidden="true"><?php echo (int) ($qnum + 1); ?></span>
                <div class="zdp-survey-qbody">
                    <label class="zdp-survey-qlabel">در مجموع چند ستاره به تجربه‌تان می‌دهید؟ <span class="zdp-survey-optional">(اختیاری)</span></label>
                    <div class="zdp-star-rating" role="radiogroup" aria-label="امتیاز ستاره‌ای">
                        <?php for ($s = 5; $s >= 1; $s--) : ?>
                            <input type="radio" name="rating" id="zdp-star-<?php echo (int) $s; ?>" value="<?php echo (int) $s; ?>">
                            <label for="zdp-star-<?php echo (int) $s; ?>" title="<?php echo (int) $s; ?> ستاره">★</label>
                        <?php endfor; ?>
                    </div>
                </div>
            </div>

            <div class="zdp-survey-q">
                <span class="zdp-survey-qnum" aria-hidden="true"><?php echo (int) ($qnum + 2); ?></span>
                <div class="zdp-survey-qbody">
                    <label class="zdp-survey-qlabel">پیشنهاد یا نظر شما <span class="zdp-survey-optional">(اختیاری)</span></label>
                    <textarea name="suggestions" rows="3" placeholder="اگر دوست دارید، نظرتان را اینجا بنویسید..." aria-label="اگر دوست دارید، نظرتان را اینجا بنویسید..."></textarea>
                </div>
            </div>

            <label class="zdp-consent-row">
                <input type="checkbox" name="consent" value="1">
                <span>موافقم که این پیشنهاد (بدون نام) در سایت نمایش داده شود.</span>
            </label>

            <button type="submit" class="zdp-btn-primary zdp-survey-submit">
                <span class="zdp-btn-label">ارسال نظر</span>
                <span class="zdp-btn-spinner" aria-hidden="true"></span>
            </button>
            <div id="zdp-survey-result" role="status" aria-live="polite"></div>
        </form>
        <?php
        return (string) ob_get_clean();
    }

    /**
     * شورت‌کد صفحه‌ی مستقل ثبت نظر ([zdp_survey_page]). چیدمان کلی
     * صفحه (وسط‌چین در کل صفحه، بدون هدر/فوتر قالب) طبیعتاً با تب
     * نظرسنجی‌ی داخل پنل فرق دارد — آنجا از قبل داخل پوسته‌ی پنل
     * (نویگیشن/هدر/فوتر پنل) قرار می‌گیرد و این یک صفحه‌ی کاملاً
     * مستقل است. اما هر عنصر قابل‌مقایسه‌ای — عنوان، کارت سوالات،
     * پیل‌های انتخاب، دکمه‌ها، شماره پشتیبانی و لینک بازگشت — دقیقاً
     * از همان مارک‌آپ/کلاس‌های پنل استفاده می‌کند (از طریق
     * render_form_only() و همان کلاس‌های .zdp-panel-footer/
     * .zdp-panel-support-link/.zdp-panel-back-link)، و هرکدام فقط
     * یک‌بار در صفحه چاپ می‌شود.
     */
    public function render_shortcode(): string
    {
        wp_enqueue_style('zdp-panel');
        wp_enqueue_script('zdp-panel');

        $support_phone = trim((string) get_option('zdp_support_phone', ''));

        // همان هدرِ «خوش‌آمد»ی که صفحه‌ی ورود پنل دارد (لوگوی سایت اگر
        // تنظیم شده باشد، وگرنه نشانِ دو حلقه‌ی متصل) — طبق درخواست
        // کارفرما، تا صفحه‌ی مستقل نظرسنجی هم همان هویت بصری پنل را
        // داشته باشد، نه یک h3 ساده‌ی بی‌هویت.
        $custom_logo_id = get_theme_mod('custom_logo');
        $logo_html      = $custom_logo_id ? wp_get_attachment_image($custom_logo_id, 'medium', false, [
            'class' => 'zdp-welcome-logo-img',
            'alt'   => esc_attr(get_bloginfo('name')),
        ]) : '';

        ob_start();
        ?>
        <div class="zdp-survey-stage">
        <div class="zdp-panel zdp-survey-panel" dir="rtl">
            <div class="zdp-welcome">
                <?php if ($logo_html) : ?>
                    <div class="zdp-welcome-logo"><?php echo $logo_html; ?></div>
                <?php else : ?>
                    <div class="zdp-welcome-mark" aria-hidden="true">
                        <span class="zdp-ring zdp-ring-a"></span>
                        <span class="zdp-ring zdp-ring-b"></span>
                    </div>
                <?php endif; ?>
                <h2 class="zdp-welcome-title">نظرسنجی از جلسه مشاوره</h2>
            </div>
            <div class="zdp-card-form-heading">
                <p class="zdp-survey-anon-note">این نظرسنجی کاملاً ناشناس است و هیچ اطلاعاتی که هویت شما را نشان دهد ذخیره نمی‌شود.</p>
            </div>

            <?php echo $this->render_form_only(); ?>

            <footer class="zdp-panel-footer zdp-login-footer">
                <?php if ($support_phone !== '') : ?>
                    <a href="tel:<?php echo esc_attr(preg_replace('/[^0-9+]/', '', $support_phone)); ?>" class="zdp-panel-support-link">
                        پشتیبانی: <?php echo esc_html($support_phone); ?>
                    </a>
                <?php endif; ?>
                <a href="<?php echo esc_url(home_url('/')); ?>" class="zdp-panel-back-link">
                    <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M15 6L9 12L15 18" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    <span>بازگشت به صفحه اصلی</span>
                </a>
            </footer>
        </div>
        </div>
        <?php
        return (string) ob_get_clean();
    }

    /**
     * فقط نظراتی که هم مراجع صراحتاً برای نمایش عمومی رضایت داده (consent=1)
     * و هم ادمین تایید کرده (approved=1) نمایش داده می‌شوند. نسخه‌ی قبلی این
     * متد فقط approved=1 را چک می‌کرد و شرط consent را فراموش کرده بود —
     * یعنی اگر ادمین اشتباهی approved را بدون توجه به consent می‌زد، نظر
     * بدون رضایت صریح مراجع منتشر می‌شد.
     */
    /**
     * برخلاف قبل که همیشه با ORDER BY created_at DESC بود (یعنی هر بازدیدکننده
     * همیشه دقیقاً همان چند نظر آخر را می‌دید و نظرات قدیمی‌تر هیچ‌وقت دیده
     * نمی‌شدند)، اینجا کل نظرات واجد شرایط را می‌گیریم و ترتیبشان را در PHP
     * به‌صورت تصادفی به‌هم می‌ریزیم — یعنی هر بار صفحه لود شود چیدمان/ترتیب
     * نظرات فرق می‌کند و در طول زمان همه‌ی نظرات (نه فقط جدیدترین‌ها) دیده
     * می‌شوند. shuffle در PHP انجام می‌شود نه ORDER BY RAND() در SQL، چون
     * RAND() روی جدول‌های بزرگ کند است؛ برای این جدول (نظرات تاییدشده) حجم
     * پایین است و شافل در PHP مشکلی ندارد.
     */
    public function get_approved_testimonials(int $limit = 40): array
    {
        global $wpdb;
        $table = Installer::table('surveys');

        // نکته‌ی مهم: قبلاً همین‌جا `ORDER BY created_at DESC LIMIT %d` روی
        // خودِ کوئری اعمال می‌شد و بعد نتیجه شافل می‌شد — یعنی در عمل فقط
        // ۴۰ نظر آخر همیشه کاندیدای نمایش بودند و shuffle صرفاً ترتیب همان
        // ۴۰ تا را به‌هم می‌ریخت؛ نظرات قدیمی‌تر از آن، هیچ‌وقت دیده
        // نمی‌شدند و ادعای بالا («در طول زمان همه‌ی نظرات دیده می‌شوند»)
        // عملاً درست نبود. حالا ابتدا کل نظرات واجد شرایط را می‌گیریم،
        // شافل می‌کنیم و بعد به $limit محدود می‌کنیم — همه شانس نمایش دارند.
        $rows = $wpdb->get_results(
            "SELECT suggestions, rating, created_at FROM $table WHERE approved = 1 AND consent = 1 AND suggestions != ''"
        );

        if (!is_array($rows)) {
            return [];
        }

        shuffle($rows);

        if (count($rows) > $limit) {
            $rows = array_slice($rows, 0, $limit);
        }

        return $rows;
    }

    /**
     * شورت‌کد عمومی نمایش نظرات تاییدشده. برخلاف صفحه‌ی نظرسنجی/ورود،
     * این خروجی داخل محتوای معمولی سایت (کنار هدر/فوتر قالب) قرار
     * می‌گیرد، نه یک صفحه‌ی مستقل — به همین دلیل به‌جای `.zdp-panel`
     * از یک ظرف مستقل (`.zdp-testimonials-section`) استفاده می‌کند که
     * ریست باکس‌مدل/جهت/فونت خودش را جدا تعریف می‌کند.
     *
     * پارامتر اختیاری `title` برای زمانی که بخواهید یک عنوان کوچک بالای
     * بلوک نمایش داده شود: [zdp_testimonials title="تجربه‌ی مراجعین ما"]
     */
    /**
     * خروجی این شورت‌کد یک نوار متحرک بی‌نهایت (marquee) است، نه یک گرید
     * ثابت: کارت‌ها پیوسته و به‌آرامی از راست به چپ عبور می‌کنند (جهت‌دار
     * برای RTL) و با نگه‌داشتن ماوس/لمس مکث می‌کنند. این هم اثر روان‌شناختی
     * «اثبات اجتماعیِ زنده» دارد (نظرات ثابت و راکد به‌نظر نمی‌رسند) و هم
     * چون get_approved_testimonials() ترتیب را رندوم می‌کند، هر بار لود صفحه
     * چیدمان فرق می‌کند. برای حلقه‌ی بصری بی‌درز، لیست کارت‌ها یک‌بار در
     * PHP تکرار می‌شود (دو نسخه‌ی پشت‌سرهم) و انیمیشن CSS دقیقاً نیمی از
     * عرض را جابه‌جا می‌کند؛ کاربر هیچ پرش/قطعی نمی‌بیند.
     */
    public function render_testimonials_shortcode($atts = []): string
    {
        wp_enqueue_style('zdp-panel');
        $testimonials = $this->get_approved_testimonials();

        if (empty($testimonials)) {
            return '';
        }

        $atts = shortcode_atts(['title' => ''], (array) $atts, 'zdp_testimonials');

        // سرعت مارکی طوری تنظیم می‌شود که مستقل از تعداد نظرات، سرعت
        // عبور هر کارت تقریباً یکسان بماند (وگرنه با نظرات کم خیلی تند
        // و با نظرات زیاد خیلی کند می‌شد). هر کارت ~4.5 ثانیه.
        $count           = count($testimonials);
        $duration_seconds = max(18, (int) round($count * 4.5));

        ob_start();
        ?>
        <style>
            .zdp-testimonial-stars{ display:flex; gap:2px; font-size:15px; color:#d8d2c2; margin-bottom:6px; }
            .zdp-testimonial-stars span.filled{ color:#e0a52c; }
        </style>
        <div class="zdp-testimonials-section" dir="rtl">
            <?php if ($atts['title'] !== '') : ?>
                <h3 class="zdp-testimonials-title"><?php echo esc_html($atts['title']); ?></h3>
            <?php endif; ?>
            <div class="zdp-testimonials-viewport">
                <div class="zdp-testimonials-track" style="--zdp-marquee-duration: <?php echo (int) $duration_seconds; ?>s;">
                    <?php
                    // دو حلقه‌ی یکسان پشت‌سرهم برای اسکرول بی‌نهایتِ بدون پرش.
                    for ($copy = 0; $copy < 2; $copy++) :
                        foreach ($testimonials as $t) :
                        ?>
                        <div class="zdp-testimonial-card" <?php echo $copy > 0 ? 'aria-hidden="true"' : ''; ?>>
                            <?php if (!empty($t->rating) && (int) $t->rating >= 1 && (int) $t->rating <= 5) : ?>
                                <div class="zdp-testimonial-stars" aria-label="<?php echo (int) $t->rating; ?> از ۵ ستاره">
                                    <?php for ($i = 1; $i <= 5; $i++) : ?><span<?php echo $i <= (int) $t->rating ? ' class="filled"' : ''; ?>>★</span><?php endfor; ?>
                                </div>
                            <?php endif; ?>
                            <span class="zdp-testimonial-quote-mark" aria-hidden="true">»</span>
                            <blockquote class="zdp-testimonial-text"><?php echo esc_html($t->suggestions); ?></blockquote>
                            <div class="zdp-testimonial-meta">
                                <span class="zdp-testimonial-badge">
                                    <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M12 12.75c2.9 0 5.25-2.35 5.25-5.25S14.9 2.25 12 2.25 6.75 4.6 6.75 7.5s2.35 5.25 5.25 5.25Zm0 2.25c-3.5 0-10.5 1.76-10.5 5.25v2.25h21v-2.25c0-3.49-7-5.25-10.5-5.25Z"/></svg>
                                    مراجع ناشناس
                                </span>
                                <span class="zdp-testimonial-date"><?php echo esc_html(Jalali::gregorian_to_jalali_string(substr($t->created_at, 0, 10))); ?></span>
                            </div>
                        </div>
                        <?php
                        endforeach;
                    endfor;
                    ?>
                </div>
            </div>
        </div>
        <?php
        return (string) ob_get_clean();
    }

    /**
     * متن آماده‌ی کپی برای کپشن اینستاگرام از روی یک نظر تاییدشده — فقط
     * قالب‌بندی متن است، هیچ تصویر/فایلی تولید نمی‌شود. دکمه‌ی «کپی برای
     * اینستاگرام» در پیشخوان (تب نظرسنجی) از این استفاده می‌کند تا ادمین
     * با یک کلیک متن را در کلیپ‌بورد داشته باشد و مستقیم در اپ اینستاگرام
     * پیست کند.
     */
    public static function build_instagram_caption(string $suggestion): string
    {
        $site_name = trim(wp_strip_all_tags(get_bloginfo('name')));

        $lines = [
            '«' . trim($suggestion) . '»',
            '',
            '– مراجع ناشناس' . ($site_name !== '' ? '، ' . $site_name : ''),
        ];

        return implode("\n", $lines);
    }

    public function get_pending_surveys(int $limit = 100): array
    {
        global $wpdb;

        return $wpdb->get_results($wpdb->prepare(
            'SELECT * FROM ' . Installer::table('surveys') . ' ORDER BY created_at DESC LIMIT %d',
            $limit
        ));
    }

    /** یک نظرسنجی مشخص — برای بررسی وجود پیش از تأیید/حذف از داشبورد. */
    public function get_survey(int $survey_id): ?object
    {
        if ($survey_id <= 0) {
            return null;
        }

        global $wpdb;

        $row = $wpdb->get_row($wpdb->prepare(
            'SELECT * FROM ' . Installer::table('surveys') . ' WHERE id = %d',
            $survey_id
        ));

        return $row ?: null;
    }

    public function set_approved(int $survey_id, bool $approved): void
    {
        global $wpdb;
        $wpdb->update(Installer::table('surveys'), ['approved' => $approved ? 1 : 0], ['id' => $survey_id], ['%d'], ['%d']);

        // نظرات تاییدشده مستقیم داخل HTML شورت‌کد [zdp_testimonials] چاپ
        // می‌شوند؛ بدون پاک‌کردن کش صفحات، تایید/لغو تایید در صفحه‌ی عمومی
        // دیده نمی‌شود (همان الگوی باگ قیمت خدمات در class-booking.php).
        Cache_Guard::purge_pages();
    }

    /**
     * حذف کامل و برگشت‌ناپذیر یک رکورد نظرسنجی (چه تاییدشده چه نشده).
     * از پیشخوان (تب نظرسنجی) استفاده می‌شود؛ خودِ این متد هیچ چک
     * دسترسی/nonce ای انجام نمی‌دهد — آن‌ها در handle_delete_survey()
     * در class-admin.php انجام می‌شوند، اینجا فقط عملیات دیتابیس است.
     */
    public function delete_survey(int $survey_id): void
    {
        global $wpdb;
        $wpdb->delete(Installer::table('surveys'), ['id' => $survey_id], ['%d']);

        // اگر نظر حذف‌شده تاییدشده بود، باید از صفحه‌ی عمومی هم برود.
        Cache_Guard::purge_pages();
    }
}
