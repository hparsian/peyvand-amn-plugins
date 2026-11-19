<?php

declare(strict_types=1);

namespace ZoojDarman\ClientPortal;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * فانل کوییز عمومی (لید مگنت) + لینک خودکار در مقالات ACO Content Writer.
 *
 * توجه معماری: این ماژول از نظر مسئولیت به psy-quiz-enterprise نزدیک‌تر است
 * تا به پنل مراجع؛ طبق تصمیم صریح شما فعلاً همین‌جا و به‌صورت ماژولار نگه
 * داشته شده تا وقتی فایل‌های psy-quiz-enterprise را برای استانداردسازی
 * بفرستید، بشود این کلاس را عیناً (یا با حداقل تغییر) به آن پلاگین منتقل
 * کرد — به همین دلیل کل منطق در یک کلاس مستقل و بدون وابستگی به بقیه‌ی
 * پلاگین (به‌جز Leads و Security) نگه داشته شده است.
 */
final class Quiz_Funnel
{
    private Security $security;
    private Leads $leads;

    public function __construct(Security $security, Leads $leads)
    {
        $this->security = $security;
        $this->leads    = $leads;

        add_shortcode('zdp_public_quiz', [$this, 'render_quiz_shortcode']);

        add_action('wp_ajax_zdp_quiz_submit_answers', [$this, 'handle_submit_answers']);
        add_action('wp_ajax_nopriv_zdp_quiz_submit_answers', [$this, 'handle_submit_answers']);

        add_action('wp_ajax_zdp_quiz_submit_phone', [$this, 'handle_submit_phone']);
        add_action('wp_ajax_nopriv_zdp_quiz_submit_phone', [$this, 'handle_submit_phone']);

        add_filter('query_vars', function (array $vars): array {
            $vars[] = 'zdp_quiz_landing';
            $vars[] = 'zdp_lead';

            return $vars;
        });
        add_action('template_redirect', [$this, 'render_landing_page']);

        add_action('save_post_post', [$this, 'autolink_quiz_into_aco_article'], 20, 3);
        // نکته‌ی حیاتی: ACO Content Writer در هر سه مسیرش (نوشتن جدید، بازنویسی،
        // بازنویسی مرحله‌ی دوم) اول wp_insert_post()/wp_update_post() را صدا
        // می‌زند (که save_post_post را trigger می‌کند) و «بعدش» متای
        // _aco_generated را ست می‌کند. یعنی روی خودِ هوک save_post_post، این
        // متا هنوز وجود ندارد و autolink_quiz_into_aco_article همیشه زودتر از
        // موعد return می‌کرد — لینک کوییز عملاً هیچ‌وقت درج نمی‌شد (به‌جز حالت
        // تصادفیِ پست‌های status=future که دوباره wp_update_post صدا زده
        // می‌شود). به همین دلیل، علاوه بر save_post_post (برای سازگاری با سایر
        // ابزارهای تولید محتوا که ممکن است ترتیب متفاوتی داشته باشند)، مستقیماً
        // به لحظه‌ی ست‌شدن همین متا هم گوش می‌دهیم.
        add_action('added_post_meta', [$this, 'maybe_autolink_on_meta_change'], 10, 4);
        add_action('updated_post_meta', [$this, 'maybe_autolink_on_meta_change'], 10, 4);
    }

    /**
     * @param mixed $meta_value
     */
    public function maybe_autolink_on_meta_change(int $meta_id, int $post_id, string $meta_key, $meta_value): void
    {
        if ($meta_key !== '_aco_generated' || empty($meta_value)) {
            return;
        }

        $post = get_post($post_id);
        if (!$post instanceof \WP_Post || $post->post_type !== 'post') {
            return;
        }

        $this->autolink_quiz_into_aco_article($post_id, $post, true);
    }

    // ------------------------------------------------------------------
    // Registry
    // ------------------------------------------------------------------

    /** @return array<string,array{title:string,keywords:string[],questions:array,tips:array}> */
    private function registry(): array
    {
        static $registry = null;
        if ($registry !== null) {
            return $registry;
        }

        $registry = [
            'connection' => [
                'title'    => 'تست رایگان: سطح صمیمیت و ارتباط در رابطه شما',
                'keywords' => ['ارتباط', 'گفتگو', 'سوءتفاهم', 'شنیدن فعال', 'همدلی'],
                'questions' => [
                    ['id' => 'q1', 'text' => 'وقتی یکی از شما روز سختی داشته، چقدر راحت با هم درباره‌اش حرف می‌زنید؟', 'options' => ['اصلاً' => 1, 'کم' => 2, 'تا حدی' => 3, 'خیلی راحت' => 4]],
                    ['id' => 'q2', 'text' => 'چقدر احساس می‌کنید طرف مقابل واقعاً حرف‌هاتون رو می‌شنوه؟', 'options' => ['اصلاً' => 1, 'کم' => 2, 'تا حدی' => 3, 'خیلی زیاد' => 4]],
                    ['id' => 'q3', 'text' => 'آخرین باری که با هم به یک تصمیم مشترک رسیدید، چقدر راحت بود؟', 'options' => ['خیلی سخت' => 1, 'نسبتاً سخت' => 2, 'نسبتاً راحت' => 3, 'خیلی راحت' => 4]],
                    ['id' => 'q4', 'text' => 'وقتی اختلاف نظر پیش میاد، معمولاً چطور تموم می‌شه؟', 'options' => ['با قهر/سکوت طولانی' => 1, 'با دلخوری که می‌مونه' => 2, 'با گفتگو ولی دیرهنگام' => 3, 'با گفتگو و رسیدن به تفاهم' => 4]],
                    ['id' => 'q5', 'text' => 'چقدر احساس امنیت می‌کنید نگرانی‌هاتون رو بدون ترس از قضاوت مطرح کنید؟', 'options' => ['اصلاً' => 1, 'کم' => 2, 'تا حدی' => 3, 'خیلی زیاد' => 4]],
                    ['id' => 'q6', 'text' => 'در کل، این روزها احساس نزدیکی‌تون به هم چطوره؟', 'options' => ['دور شده‌ایم' => 1, 'در نوسان است' => 2, 'نسبتاً خوب است' => 3, 'خیلی نزدیک است' => 4]],
                ],
                'tips' => [
                    'attention' => 'در این دوره، گفتگوی صریح و بدون قضاوت می‌تواند نقطه‌ی شروع خوبی باشد. خیلی از زوج‌ها دقیقاً از همین‌جا مسیر بهبود را شروع می‌کنند.',
                    'growing'   => 'پایه‌های خوبی دارید که با کمی توجه بیشتر به شنیدن فعال، می‌تواند قوی‌تر هم بشه.',
                    'strong'    => 'رابطه‌ی شما از پایه‌ی نسبتاً محکمی برخورداره؛ حفظ همین ریتم گفتگو ارزشمنده.',
                ],
            ],
            'trust' => [
                'title'    => 'تست رایگان: سطح اعتماد در رابطه شما',
                'keywords' => ['اعتماد', 'خیانت', 'بی‌اعتمادی', 'بخشش', 'صداقت'],
                'questions' => [
                    ['id' => 'q1', 'text' => 'وقتی طرف مقابل دیر به خونه میاد یا جواب پیام نمی‌ده، اولین فکرتون چیه؟', 'options' => ['حتماً یه چیزی رو ازم پنهون می‌کنه' => 1, 'کمی نگران می‌شم ولی زود می‌گذره' => 2, 'معمولاً دلیل منطقی داره' => 3, 'اصلاً نگران نمی‌شم' => 4]],
                    ['id' => 'q2', 'text' => 'چقدر راحت می‌تونید گوشی/پیام‌های همدیگه رو بدون بازرسی کردن، به حال خودش بذارید؟', 'options' => ['اصلاً نمی‌تونم' => 1, 'به‌سختی' => 2, 'تا حدی' => 3, 'کاملاً راحتم' => 4]],
                    ['id' => 'q3', 'text' => 'وقتی اشتباهی می‌کنید، چقدر راحت به طرف مقابل می‌گید؟', 'options' => ['ترجیح می‌دم پنهون کنم' => 1, 'با تردید می‌گم' => 2, 'معمولاً می‌گم' => 3, 'همیشه شفاف می‌گم' => 4]],
                    ['id' => 'q4', 'text' => 'بعد از یک دروغ کوچیک یا مخفی‌کاری در گذشته، چقدر تونستید دوباره اعتماد کنید؟', 'options' => ['هنوز نتونستم' => 1, 'کمی' => 2, 'تا حد زیادی' => 3, 'کاملاً' => 4]],
                    ['id' => 'q5', 'text' => 'چقدر احساس می‌کنید طرف مقابل واقعاً پشت شماست، حتی وقتی اطراف نیستید؟', 'options' => ['اصلاً' => 1, 'کم' => 2, 'تا حدی' => 3, 'خیلی زیاد' => 4]],
                ],
                'tips' => [
                    'attention' => 'بازسازی اعتماد زمان می‌بره و معمولاً به‌تنهایی سخت پیش می‌ره. گفتگوی ساختاریافته با یک متخصص می‌تونه این مسیر رو کوتاه‌تر و امن‌تر کنه.',
                    'growing'   => 'اعتماد در حال شکل‌گیریه؛ شفافیت بیشتر و گفتگوی منظم می‌تونه این پایه رو محکم‌تر کنه.',
                    'strong'    => 'اعتماد بین شما نسبتاً قویه؛ همین شفافیت و صداقت رو حفظ کنید.',
                ],
            ],
            'attachment' => [
                'title'    => 'تست رایگان: سبک دلبستگی شما در رابطه',
                'keywords' => ['دلبستگی', 'سبک دلبستگی', 'ترس از رهاشدن', 'وابستگی'],
                'questions' => [
                    ['id' => 'q1', 'text' => 'وقتی طرف مقابل کمی فاصله می‌گیره (مثلاً سرش شلوغه)، چه حسی بهتون دست می‌ده؟', 'options' => ['خیلی نگران و ناامن می‌شم' => 1, 'کمی نگران می‌شم' => 2, 'معمولاً آرومم' => 3, 'کاملاً آرومم' => 4]],
                    ['id' => 'q2', 'text' => 'چقدر راحتید که نیازها و احساساتتون رو مستقیم بیان کنید؟', 'options' => ['اصلاً' => 1, 'کم' => 2, 'تا حدی' => 3, 'خیلی راحتم' => 4]],
                    ['id' => 'q3', 'text' => 'وقتی دعوا می‌شه، تمایل دارید نزدیک‌تر بشید یا فاصله بگیرید؟', 'options' => ['کاملاً فاصله می‌گیرم/قهر' => 1, 'کمی فاصله می‌گیرم' => 2, 'معمولاً نزدیک می‌مونم' => 3, 'سعی می‌کنم نزدیک بمونم و حل کنم' => 4]],
                    ['id' => 'q4', 'text' => 'چقدر به ثبات و پایداری رابطه‌تون مطمئنید؟', 'options' => ['اصلاً مطمئن نیستم' => 1, 'کم' => 2, 'تا حدی' => 3, 'کاملاً مطمئنم' => 4]],
                    ['id' => 'q5', 'text' => 'وقتی طرف مقابل با دوستان/خانواده وقت می‌گذرونه، چه حسی دارید؟', 'options' => ['حس رهاشدگی/حسادت زیاد' => 1, 'کمی ناراحت' => 2, 'معمولاً راحتم' => 3, 'کاملاً راحت و خوشحالم' => 4]],
                ],
                'tips' => [
                    'attention' => 'الگوهای دلبستگی معمولاً ریشه در تجربه‌های قبلی دارند؛ گفتگو با یک متخصص می‌تونه کمک کنه این الگو رو بهتر بشناسید.',
                    'growing'   => 'پایه‌ی دلبستگی امن در حال شکل‌گیریه؛ تمرین بیان مستقیم نیازها می‌تونه تقویتش کنه.',
                    'strong'    => 'دلبستگی نسبتاً امنی بین شما شکل گرفته؛ همین ارتباط باز رو حفظ کنید.',
                ],
            ],
            'intimacy' => [
                'title'    => 'تست رایگان: سطح صمیمیت عاطفی و جسمی رابطه شما',
                'keywords' => ['صمیمیت', 'نزدیکی', 'رابطه جنسی', 'سردی رابطه'],
                'questions' => [
                    ['id' => 'q1', 'text' => 'چقدر از نزدیکی عاطفی (نه لزوماً جسمی) با هم راضی هستید؟', 'options' => ['اصلاً' => 1, 'کم' => 2, 'تا حدی' => 3, 'خیلی زیاد' => 4]],
                    ['id' => 'q2', 'text' => 'چقدر راحت در آغوش گرفتن یا نزدیکی جسمی ساده اتفاق میفته؟', 'options' => ['تقریباً هیچ‌وقت' => 1, 'به‌ندرت' => 2, 'گاهی' => 3, 'به‌طور مرتب' => 4]],
                    ['id' => 'q3', 'text' => 'چقدر با کیفیت رابطه‌ی جنسی‌تون راضی هستید؟', 'options' => ['اصلاً' => 1, 'کم' => 2, 'نسبتاً' => 3, 'خیلی' => 4]],
                    ['id' => 'q4', 'text' => 'چقدر راحت درباره‌ی نیازها و مرزهای جنسی/عاطفی‌تون با هم حرف می‌زنید؟', 'options' => ['اصلاً' => 1, 'کم' => 2, 'تا حدی' => 3, 'خیلی راحت' => 4]],
                    ['id' => 'q5', 'text' => 'در کل، احساس نزدیکی این روزهاتون نسبت به قبل چطوره؟', 'options' => ['خیلی کمتر شده' => 1, 'کمی کمتر شده' => 2, 'تقریباً مثل قبل' => 3, 'بیشتر شده' => 4]],
                ],
                'tips' => [
                    'attention' => 'افت صمیمیت موضوع خیلی رایجیه و معمولاً با گفتگوی درست قابل بازسازیه.',
                    'growing'   => 'صمیمیت‌تون در مسیر خوبیه؛ گفتگوی باز درباره‌ی نیازها می‌تونه عمیق‌ترش کنه.',
                    'strong'    => 'صمیمیت خوبی بین شما برقراره؛ حفظ همین ارتباط باز ارزشمنده.',
                ],
            ],
            'anxiety' => [
                'title'    => 'تست رایگان: سطح آرامش و اطمینان شما در رابطه',
                'keywords' => ['اضطراب رابطه', 'نگرانی رابطه', 'عدم اطمینان'],
                'questions' => [
                    ['id' => 'q1', 'text' => 'چقدر به فکر این هستید که رابطه‌تون ممکنه بهم بخوره؟', 'options' => ['مدام' => 1, 'زیاد' => 2, 'گاهی' => 3, 'تقریباً هیچ‌وقت' => 4]],
                    ['id' => 'q2', 'text' => 'وقتی طرف مقابل دیر جواب می‌ده، چقدر ذهنتون درگیر بدترین حالت‌ها می‌شه؟', 'options' => ['خیلی زیاد' => 1, 'نسبتاً زیاد' => 2, 'کم' => 3, 'تقریباً هیچ‌وقت' => 4]],
                    ['id' => 'q3', 'text' => 'چقدر برای اطمینان از علاقه‌ی طرف مقابل نیاز به تأییدهای مکرر دارید؟', 'options' => ['خیلی زیاد' => 1, 'نسبتاً زیاد' => 2, 'کم' => 3, 'تقریباً هیچ‌وقت' => 4]],
                    ['id' => 'q4', 'text' => 'چقدر خواب/تمرکز روزمره‌تون تحت‌تأثیر نگرانی درباره‌ی رابطه قرار می‌گیره؟', 'options' => ['خیلی زیاد' => 1, 'تا حدی' => 2, 'کم' => 3, 'اصلاً' => 4]],
                    ['id' => 'q5', 'text' => 'در کل چقدر احساس آرامش و امنیت در این رابطه دارید؟', 'options' => ['خیلی کم' => 1, 'کم' => 2, 'نسبتاً' => 3, 'خیلی زیاد' => 4]],
                ],
                'tips' => [
                    'attention' => 'نگرانی مداوم درباره‌ی رابطه معمولاً به‌تنهایی حل‌شدنی نیست؛ صحبت با یک متخصص می‌تونه کمک کنه.',
                    'growing'   => 'کمی نوسان در این حس طبیعیه؛ گفتگوی شفاف می‌تونه آرامش بیشتری بیاره.',
                    'strong'    => 'حس امنیت نسبتاً خوبی در رابطه دارید؛ همین رو حفظ کنید.',
                ],
            ],
            'conflict' => [
                'title'    => 'تست رایگان: سبک شما در مدیریت دعوا و تعارض',
                'keywords' => ['دعوا', 'تعارض', 'اختلاف نظر', 'کشمکش'],
                'questions' => [
                    ['id' => 'q1', 'text' => 'وقتی دعوا می‌کنید، معمولاً چقدر طول می‌کشه تا آروم بشید؟', 'options' => ['خیلی طولانی (روزها)' => 1, 'طولانی (ساعت‌ها)' => 2, 'نسبتاً کوتاه' => 3, 'خیلی سریع' => 4]],
                    ['id' => 'q2', 'text' => 'در دعواها، چقدر پیش میاد حرف‌های گذشته رو هم وسط بکشید؟', 'options' => ['همیشه' => 1, 'زیاد' => 2, 'گاهی' => 3, 'تقریباً هیچ‌وقت' => 4]],
                    ['id' => 'q3', 'text' => 'بعد از دعوا، چقدر معمولاً به یک راه‌حل مشترک می‌رسید؟', 'options' => ['تقریباً هیچ‌وقت' => 1, 'به‌ندرت' => 2, 'معمولاً' => 3, 'تقریباً همیشه' => 4]],
                    ['id' => 'q4', 'text' => 'چقدر در دعواها به هم توهین یا تحقیر می‌کنید (حتی جزئی)؟', 'options' => ['زیاد' => 1, 'گاهی' => 2, 'به‌ندرت' => 3, 'اصلاً' => 4]],
                    ['id' => 'q5', 'text' => 'بعد از دعوا، چقدر راحت دوباره نزدیک می‌شید؟', 'options' => ['خیلی سخت' => 1, 'نسبتاً سخت' => 2, 'نسبتاً راحت' => 3, 'خیلی راحت' => 4]],
                ],
                'tips' => [
                    'attention' => 'الگوی دعواهای طولانی معمولاً قابل تغییره با یاد گرفتن چند تکنیک ساده‌ی گفتگو.',
                    'growing'   => 'پایه‌ی خوبی برای حل تعارض دارید؛ تمرین بیشتر روی «توقف به‌موقع» می‌تونه کمک کنه.',
                    'strong'    => 'مدیریت تعارض‌تون نسبتاً سالمه؛ همین الگو رو حفظ کنید.',
                ],
            ],
        ];

        return $registry;
    }

    private function band_from_score(int $score, int $max): string
    {
        $ratio = $max > 0 ? $score / $max : 0;

        return match (true) {
            $ratio <= 0.5  => 'attention',
            $ratio <= 0.75 => 'growing',
            default        => 'strong',
        };
    }

    private function band_label(string $key): string
    {
        return [
            'attention' => 'در حال گذر از یک دوره‌ی چالش‌برانگیز',
            'growing'   => 'در مسیر رشد و تقویت',
            'strong'    => 'دارای پایه‌ی نسبتاً قوی',
        ][$key] ?? 'در مسیر رشد و تقویت';
    }

    private function quiz_url(string $topic): string
    {
        return apply_filters('zdp_quiz_url', home_url('/?zdp_quiz_landing=' . $topic), $topic);
    }

    private function booking_url(): string
    {
        return apply_filters('zdp_leads_booking_url', zdp_panel_url());
    }

    // ------------------------------------------------------------------
    // Shortcode + AJAX
    // ------------------------------------------------------------------

    public function render_quiz_shortcode(array $atts): string
    {
        $atts  = shortcode_atts(['topic' => 'connection'], $atts, 'zdp_public_quiz');
        $topic = sanitize_key($atts['topic']);
        $registry = $this->registry();
        if (!isset($registry[$topic])) {
            $topic = 'connection';
        }
        $quiz = $registry[$topic];

        wp_enqueue_style('zdp-panel');
        wp_enqueue_script('zdp-panel');
        $source = isset($_GET['utm_source']) ? sanitize_text_field(wp_unslash($_GET['utm_source']))
            : (isset($_GET['ref']) ? sanitize_text_field(wp_unslash($_GET['ref'])) : 'direct');

        ob_start();
        ?>
        <div id="zdp-public-quiz" class="zdp-quiz-wrap" data-source="<?php echo esc_attr($source); ?>" data-topic="<?php echo esc_attr($topic); ?>">
            <h2><?php echo esc_html($quiz['title']); ?></h2>
            <form id="zdp-quiz-form">
                <?php foreach ($quiz['questions'] as $i => $q) : ?>
                    <div class="zdp-quiz-question">
                        <p><?php echo esc_html(($i + 1) . '. ' . $q['text']); ?></p>
                        <?php foreach ($q['options'] as $label => $value) : ?>
                            <label>
                                <input type="radio" name="<?php echo esc_attr($q['id']); ?>" value="<?php echo esc_attr((string) $value); ?>" required>
                                <?php echo esc_html($label); ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>
                <button type="submit">مشاهده نتیجه</button>
            </form>
            <div id="zdp-quiz-partial-result" hidden></div>
            <div id="zdp-quiz-full-result" hidden></div>
        </div>
        <?php
        return (string) ob_get_clean();
    }

    public function handle_submit_answers(): void
    {
        $this->security->verify_ajax_nonce();

        $ip = get_client_ip();
        if (!$this->security->allow('quiz_answers_' . md5($ip), 20, 3600)) {
            wp_send_json_error(['message' => 'تعداد درخواست‌های شما زیاد بوده. کمی صبر کنید.']);
        }

        $topic    = isset($_POST['topic']) ? sanitize_key(wp_unslash($_POST['topic'])) : 'connection';
        $registry = $this->registry();
        if (!isset($registry[$topic])) {
            $topic = 'connection';
        }
        $quiz = $registry[$topic];

        $score = 0;
        $max   = 0;
        foreach ($quiz['questions'] as $q) {
            $val = isset($_POST[$q['id']]) ? (int) $_POST[$q['id']] : 0;
            $score += max(0, min(4, $val)); // clamp تا مقدار جعلی خارج از بازه‌ی مجاز امتیاز را منحرف نکند
            $max += max($q['options']);
        }

        $band   = $this->band_from_score($score, $max);
        $source = isset($_POST['source']) ? sanitize_text_field(wp_unslash($_POST['source'])) : 'direct';

        $temp_id = wp_generate_uuid4();
        set_transient('zdp_quiz_tmp_' . $temp_id, [
            'score'  => $score,
            'band'   => $band,
            'source' => $source,
            'topic'  => $topic,
        ], HOUR_IN_SECONDS);

        wp_send_json_success([
            'temp_id'    => $temp_id,
            'band_label' => $this->band_label($band),
        ]);
    }

    public function handle_submit_phone(): void
    {
        $this->security->verify_ajax_nonce();

        $temp_id = isset($_POST['temp_id']) ? sanitize_text_field(wp_unslash($_POST['temp_id'])) : '';
        $phone   = isset($_POST['phone']) ? to_local_format(sanitize_text_field(wp_unslash($_POST['phone']))) : '';

        if (!is_valid_mobile($phone)) {
            wp_send_json_error(['message' => 'شماره موبایل معتبر وارد کنید.']);
        }

        if (!$this->security->allow('quiz_phone_' . $phone, 5, 3600)) {
            wp_send_json_error(['message' => 'تعداد درخواست‌های شما زیاد بوده. کمی صبر کنید.']);
        }

        $tmp = get_transient('zdp_quiz_tmp_' . $temp_id);
        if (!$tmp) {
            wp_send_json_error(['message' => 'زمان جلسه منقضی شده، لطفاً دوباره آزمون را تکمیل کنید.']);
        }

        $this->leads->create($phone, $tmp['source'], $tmp['topic'], $tmp['score'], $tmp['band']);
        delete_transient('zdp_quiz_tmp_' . $temp_id);

        $registry = $this->registry();
        $quiz     = $registry[$tmp['topic']] ?? $registry['connection'];
        $tip      = $quiz['tips'][$tmp['band']] ?? $quiz['tips']['growing'];

        $html = '<h3>تحلیل کامل شما آماده است</h3><p>' . esc_html($tip) . '</p>'
            . '<p><a class="zdp-quiz-cta-button" href="' . esc_url($this->booking_url()) . '">رزرو جلسه مشاوره اولیه</a></p>';

        wp_send_json_success(['html' => $html]);
    }

    // ------------------------------------------------------------------
    // Landing page
    // ------------------------------------------------------------------

    public function render_landing_page(): void
    {
        $topic = get_query_var('zdp_quiz_landing');
        if (empty($topic)) {
            return;
        }

        $lead_id = (int) get_query_var('zdp_lead');
        if ($lead_id > 0) {
            $this->leads->mark_link_clicked_by_id($lead_id);
        }

        $topic    = sanitize_key($topic);
        $registry = $this->registry();
        if (!isset($registry[$topic])) {
            $topic = 'connection';
        }
        $quiz = $registry[$topic];

        status_header(200);
        nocache_headers();
        ?><!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
    <title><?php echo esc_html($quiz['title']); ?></title>
    <link rel="stylesheet" href="<?php echo esc_url(ZDP_PLUGIN_URL . 'assets/css/panel.css'); ?>">
</head>
<body class="zdp-landing-body">
    <?php echo do_shortcode('[zdp_public_quiz topic="' . esc_attr($topic) . '"]'); ?>
    <script src="<?php echo esc_url(ZDP_PLUGIN_URL . 'assets/js/panel.js?ver=' . ZDP_VERSION); ?>"></script>
    <script>window.ZDP = <?php echo wp_json_encode([
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce'    => wp_create_nonce(Security::NONCE_ACTION),
        'i18n'     => ['network_error' => 'خطا در ارتباط با سرور. لطفاً دوباره تلاش کنید.'],
    ]); ?>;</script>
</body>
</html>
        <?php
        exit;
    }

    // ------------------------------------------------------------------
    // Autolink into ACO Content Writer articles
    // ------------------------------------------------------------------

    public function autolink_quiz_into_aco_article(int $post_id, \WP_Post $post, bool $update): void
    {
        if (!get_post_meta($post_id, '_aco_generated', true)) {
            return;
        }
        if (get_post_meta($post_id, '_zdp_quiz_link_inserted', true)) {
            return;
        }

        $keyword = get_post_meta($post_id, 'rank_math_focus_keyword', true);
        if (!$keyword) {
            $keyword = get_post_meta($post_id, '_yoast_wpseo_focuskw', true);
        }
        if (!$keyword) {
            return;
        }

        $topic = $this->match_quiz_topic((string) $keyword);
        if (!$topic) {
            return;
        }

        $registry = $this->registry();
        $quiz     = $registry[$topic];
        $quiz_url = $this->quiz_url($topic);

        $mid_cta = '<p class="zdp-quiz-cta-mid"><span class="zdp-icon-inline">' . zdp_icon('message', 18) . '</span> <a href="' . esc_url($quiz_url) . '"><strong>' . esc_html($quiz['title']) . '</strong></a> — فقط ۲ دقیقه وقت می‌گیره.</p>';
        $end_cta = '<p class="zdp-quiz-cta-end"><span class="zdp-icon-inline">' . zdp_icon('clipboard', 18) . '</span> می‌خواید بدونید الان رابطه‌تون در چه وضعیتیه؟ <a href="' . esc_url($quiz_url) . '"><strong>' . esc_html($quiz['title']) . '</strong></a> را رایگان انجام بدید.</p>';

        $content = $this->insert_html_at_middle_paragraph($post->post_content, $mid_cta) . $end_cta;

        remove_action('save_post_post', [$this, 'autolink_quiz_into_aco_article'], 20);
        wp_update_post(['ID' => $post_id, 'post_content' => $content]);
        add_action('save_post_post', [$this, 'autolink_quiz_into_aco_article'], 20, 3);

        update_post_meta($post_id, '_zdp_quiz_link_inserted', true);
        update_post_meta($post_id, '_zdp_quiz_topic_matched', $topic);
        audit_log('quiz_autolinked_into_article', ['post_id' => $post_id, 'topic' => $topic]);
    }

    private function match_quiz_topic(string $keyword): ?string
    {
        $keyword = mb_strtolower(trim($keyword));
        if ($keyword === '') {
            return null;
        }

        foreach ($this->registry() as $topic => $quiz) {
            foreach ($quiz['keywords'] as $kw) {
                if (mb_strpos($keyword, mb_strtolower($kw)) !== false) {
                    return $topic;
                }
            }
        }

        return null;
    }

    private function insert_html_at_middle_paragraph(string $content, string $html_block): string
    {
        $paragraphs = preg_split('/(<\/p>)/i', $content, -1, PREG_SPLIT_DELIM_CAPTURE);
        if ($paragraphs === false) {
            return $content . $html_block;
        }

        $p_count = (int) floor(count($paragraphs) / 2);
        if ($p_count < 2) {
            // toE: strlen()/substr() روی بایت کار می‌کنند، نه کاراکتر. محتوای
            // فارسی UTF-8 چندبایتی است (هر حرف معمولاً ۲ بایت)، پس شکستن
            // دقیقاً از وسطِ strlen($content)/2 می‌توانست وسط یک کاراکتر
            // چندبایتی بیفتد و متن مقاله را در همان نقطه خراب/نامفهوم کند.
            // با mb_strlen/mb_substr، برش همیشه روی مرز یک کاراکتر کامل
            // انجام می‌شود.
            $half = (int) floor(mb_strlen($content) / 2);

            return mb_substr($content, 0, $half) . $html_block . mb_substr($content, $half);
        }

        $middle_index = (int) floor($p_count / 2);
        $insert_after = ($middle_index * 2) + 1;

        $result = '';
        foreach ($paragraphs as $i => $chunk) {
            $result .= $chunk;
            if ($i === $insert_after - 1) {
                $result .= $html_block;
            }
        }

        return $result;
    }
}
