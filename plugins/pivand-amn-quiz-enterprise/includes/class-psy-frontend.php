<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// نوار ابزار مدیریت وردپرس (وقتی ادمین لاگین باشد) با موقعیت fixed در
// بالای هر صفحه نمایش داده می‌شود — از جمله همین صفحه‌ی مستقل آزمون،
// درست بالای هدر سفارشی (لوگو/نام درمانگر) که قرار بود جای هدر/فوتر کامل
// سایت را بگیرد. باید خیلی زود فیلتر شود (پیش از اجرای template_redirect
// که مقداردهی نوار ابزار در آن اتفاق می‌افتد)، وگرنه دیر است — برای همین
// همین‌جا، در لحظه‌ی لود شدن فایل، انجام می‌شود. مراجع واقعی (که هیچ‌وقت
// وارد پیشخوان نمی‌شود) از اول هم این نوار را نمی‌دید؛ این فیلتر فقط برای
// تمیز ماندن پیش‌نمایشِ ادمین لاگین‌شده است.
if ( isset( $_GET['psy_exam'] ) ) {
    add_filter( 'show_admin_bar', '__return_false' );
}

class Psy_Quiz_Frontend {

    /** کش دعوتنامه validate‌شده برای جلوگیری از دوباره‌کاری DB */
    private static ?object $cached_inv = null;

    /**
     * باگ D: null coalescing (??) نمی‌تواند بین «هنوز cache نشده» و «cache شده ولی
     * نامعتبر (null)» فرق بگذارد. یک flag جداگانه این مشکل را حل می‌کند.
     */
    private static bool $cache_loaded = false;

    /** توسط template_redirect پر می‌شود */
    public static function set_cached_inv( ?object $inv ): void {
        self::$cached_inv  = $inv;
        self::$cache_loaded = true;
    }

    /**
     * رندر صفحه آزمون — قابل استفاده به عنوان shortcode [psy_exam]
     * یا اتوماتیک از طریق ?psy_exam=1&token=...
     */
    public static function render( $atts = [] ): string {
        $token = isset( $_GET['token'] ) ? sanitize_text_field( wp_unslash( $_GET['token'] ) ) : '';

        if ( empty( $token ) ) {
            return self::error_page( 'لینک آزمون معتبر نیست. لطفاً از لینک اختصاصی خود استفاده کنید.' );
        }

        // اگر template_redirect قبلاً validate کرده، از cache استفاده کن
        // باگ D: از flag جداگانه استفاده می‌کنیم تا null معتبر (توکن نامعتبر) از
        // «هنوز cache نشده» تمیز داده شود.
        $inv = self::$cache_loaded
            ? self::$cached_inv
            : Psy_Quiz_Invitation_Manager::validate( $token );
        if ( ! $inv ) {
            // بررسی آیا لینک منقضی شده یا اصلاً وجود ندارد
            global $wpdb;
            $exists = $wpdb->get_var( $wpdb->prepare(
                "SELECT status FROM {$wpdb->prefix}psy_invitations WHERE access_token = %s LIMIT 1",
                sanitize_text_field( $token )
            ) );
            if ( $exists === 'completed' ) {
                return self::error_page( 'این آزمون قبلاً تکمیل شده است. برای اطلاعات بیشتر با درمانگر خود تماس بگیرید.', '✅', 'آزمون تکمیل شده' );
            }
            if ( $exists === 'revoked' ) {
                return self::error_page( 'این لینک توسط درمانگر لغو شده است. لطفاً با درمانگر خود تماس بگیرید.', '🚫', 'لینک لغو شده' );
            }
            if ( $exists === 'processing' ) {
                return self::error_page( 'پاسخ‌های شما در حال پردازش است. لطفاً چند لحظه صبر کنید و دوباره تلاش کنید.', '⏳', 'در حال پردازش' );
            }
            if ( $exists === 'unused' ) {
                // unused ولی validate نشد = منقضی شده
                return self::error_page( 'مهلت این لینک به پایان رسیده است. لطفاً از درمانگر خود لینک جدید بخواهید.', '⏰', 'لینک منقضی شده' );
            }
            return self::error_page( 'این لینک معتبر نیست. لطفاً از لینک اختصاصی خود استفاده کنید.' );
        }

        $test = \PsychAssessments\Core\Registry::get( $inv->quiz_id );
        if ( ! $test ) {
            return self::error_page( 'آزمون مورد نظر در سیستم یافت نشد.' );
        }

        $questions       = $test->get_questions();
        $scale           = $test->get_scale();
        $question_scales = method_exists( $test, 'get_question_scales' ) ? $test->get_question_scales() : [];
        $q_count         = count( $questions );
        // FIX: آخرین کلید واقعی آرایه — برای آزمون‌هایی که شماره‌گذاری gap دارند
        $q_keys          = array_keys( $questions );
        $q_first         = (int) reset( $q_keys );
        $q_last          = (int) end( $q_keys );

        ob_start();
        ?>
        <div id="psy-exam-wrap" dir="rtl" data-token="<?php echo esc_attr($token); ?>" data-total="<?php echo esc_attr((int)$q_count); ?>" data-question-scales="<?php echo esc_attr( wp_json_encode( $question_scales ) ); ?>">

            <!-- هدر آزمون — فقط نوار پیشرفت -->
            <div id="psy-exam-header" class="psy-header-hidden">
                <div id="psy-progress-wrap">
                    <div id="psy-progress-bar"></div>
                </div>
                <div id="psy-progress-text">سوال <span id="psy-q-current">1</span> از <?php echo esc_html((int)$q_count); ?></div>
            </div>

            <!-- صفحه خوشامد -->
            <div id="psy-screen-welcome" class="psy-screen active">
                <div class="psy-welcome-box">
                    <div class="psy-welcome-icon psy-anim-1">📋</div>
                    <h2 class="psy-anim-2">سلام <?php echo esc_html( $inv->client_name ); ?> عزیز</h2>
                    <p class="psy-anim-3">این پرسشنامه شامل <strong><?php echo esc_html((int)$q_count); ?> سوال</strong> است.</p>
                    <p class="psy-note psy-anim-4">
                        لطفاً به هر سوال با دقت و صادقانه پاسخ دهید.<br>
                        پاسخ درست یا غلطی وجود ندارد.
                    </p>
                    <div class="psy-scale-title psy-anim-5">راهنمای پاسخ‌دهی:</div>
                    <div class="psy-scale-preview psy-anim-5">
                        <?php foreach ( $scale['labels'] as $val => $lbl ): ?>
                        <div class="psy-scale-item">
                            <span class="psy-scale-num"><?php echo esc_html((int)$val); ?></span>
                            <span class="psy-scale-lbl"><?php echo esc_html($lbl); ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="psy-anim-6">
                        <button id="psy-btn-start" class="psy-btn psy-btn-primary">شروع ←</button>
                    </div>
                </div>
            </div>

            <!-- صفحه سوالات -->
            <div id="psy-screen-quiz" class="psy-screen">
                <div id="psy-question-area">
                    <?php foreach ( $questions as $qnum => $qdata ): ?>
                    <?php $qtext = is_array( $qdata ) ? ( $qdata['text'] ?? $qdata['q'] ?? "سوال $qnum" ) : $qdata; ?>
                    <div class="psy-question-card psy-is-hidden" data-qnum="<?php echo esc_attr((int)$qnum); ?>">
                        <div class="psy-question-num">سوال <?php echo esc_html((int)$qnum); ?></div>
                        <div class="psy-question-text"><?php echo esc_html( $qtext ); ?></div>
                        <div class="psy-options">
                            <?php
                            $q_scale = isset( $question_scales[ $qnum ] ) ? $question_scales[ $qnum ] : $scale;
                            foreach ( $q_scale['labels'] as $val => $lbl ):
                            ?>
                            <label class="psy-option">
                                <input type="radio" name="q_<?php echo esc_attr((int)$qnum); ?>" value="<?php echo esc_attr((int)$val); ?>">
                                <span class="psy-option-box">
                                    <span class="psy-option-val"><?php echo esc_html((int)$val); ?></span>
                                    <span class="psy-option-lbl"><?php echo esc_html($lbl); ?></span>
                                </span>
                            </label>
                            <?php endforeach; ?>
                        </div>
                        <div class="psy-nav">
                            <?php if ( $qnum > $q_first ): ?>
                            <button class="psy-btn psy-btn-prev" data-qnum="<?php echo esc_attr((int)$qnum); ?>">← قبلی</button>
                            <?php else: ?>
                            <span></span>
                            <?php endif; ?>
                            <?php if ( $qnum < $q_last ): ?>
                            <button class="psy-btn psy-btn-next" data-qnum="<?php echo esc_attr((int)$qnum); ?>" disabled>بعدی ←</button>
                            <?php else: ?>
                            <button class="psy-btn psy-btn-submit" data-qnum="<?php echo esc_attr((int)$qnum); ?>" disabled>ثبت پاسخ‌ها ✓</button>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- صفحه در حال ارسال -->
            <div id="psy-screen-loading" class="psy-screen">
                <div class="psy-loading-box">
                    <div class="psy-spinner"></div>
                    <p>در حال پردازش پاسخ‌ها...</p>
                </div>
            </div>

            <!-- صفحه اتمام آزمون -->
            <div id="psy-screen-result" class="psy-screen">
                <div class="psy-done-box">
                    <div class="psy-done-icon">✅</div>
                    <h2><?php echo esc_html( $inv->client_name ); ?> عزیز، ممنون از وقتی که گذاشتید 🙏</h2>
                    <p>پاسخ‌های شما با موفقیت ثبت شد و در اختیار درمانگرتان قرار گرفت.</p>
                    <p>صادقانه پاسخ دادن به این سوال‌ها قدم مهمی در مسیر شناخت بهتر خودتان است.</p>
                    <p class="psy-done-note">نتایج توسط درمانگر شما بررسی و با شما در میان گذاشته خواهد شد.</p>
                </div>
            </div>

            <!-- صفحه خطا -->
            <div id="psy-screen-error" class="psy-screen">
                <div class="psy-error-box">
                    <div class="psy-error-icon">⚠️</div>
                    <p id="psy-error-msg"></p>
                    <button onclick="location.reload()" class="psy-btn">تلاش مجدد</button>
                </div>
            </div>

        </div>
        <?php
        return ob_get_clean();
    }

    // ───────────────────────────────────────────────────────────────────────
    private static function error_page( string $msg, string $icon = '🔒', string $title = 'دسترسی ممکن نیست' ): string {
        return '<div class="psy-error-standalone" dir="rtl">
            <div class="psy-error-icon">' . $icon . '</div>
            <h2>' . esc_html($title) . '</h2>
            <p>' . esc_html($msg) . '</p>
        </div>';
    }

    /**
     * هدر مینیمالِ صفحه‌ی مستقل آزمون — به‌جای هدر کامل قالب سایت (که منو،
     * سایدبار و کل هویت بصری سایت را با خودش می‌آورد و حواس مراجع را از
     * خودِ آزمون پرت می‌کند)، فقط لوگو + نام درمانگر نمایش داده می‌شود.
     * دقیقاً همان الگویی که صفحه‌ی ورود پنل مراجعین و صفحه‌ی مستقل
     * نظرسنجی (پلاگین پنل مراجعین) هم استفاده می‌کنند — تا هویت بصریِ
     * یکسانی در تمام صفحات مستقلِ رو‌به‌مراجعِ این اکوسیستم وجود داشته باشد.
     */
    public static function render_page_header(): void {
        $custom_logo_id = get_theme_mod( 'custom_logo' );
        // اگر تصویر واقعی لوگو به هر دلیلی روی سرور بارگذاری نشود (لینک
        // شکسته، فایل حذف‌شده از هاست ولی رکورد رسانه هنوز در دیتابیس
        // هست، محدودیت CDN و...)، مرورگر به‌جای عکس متنِ alt را کامل روی
        // صفحه نشان می‌دهد — که چون alt همان نام کامل سایت است، شبیه یک
        // خط متنِ عجیب/تکراری بالای صفحه به‌نظر می‌رسد. onerror این حالت را
        // با مخفی‌کردن <img> خراب و نمایش بی‌صدای نشانِ جایگزین اصلاح می‌کند.
        $logo_html = $custom_logo_id ? wp_get_attachment_image( $custom_logo_id, 'medium', false, [
            'class'   => 'psy-exam-logo-img',
            'alt'     => esc_attr( get_bloginfo( 'name' ) ),
            'onerror' => "this.style.display='none';var f=this.nextElementSibling;if(f){f.style.display='inline-flex';}",
        ] ) : '';
        $therapist_name = trim( (string) get_option( 'psy_exam_therapist_name', 'مهناز خویینی' ) );
        ?>
        <header class="psy-exam-chrome-header">
            <a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="psy-exam-logo-link">
                <?php if ( $logo_html ): ?>
                    <?php echo $logo_html; ?>
                    <span class="psy-exam-logo-mark psy-exam-logo-fallback" aria-hidden="true">
                        <span class="psy-ring psy-ring-a"></span>
                        <span class="psy-ring psy-ring-b"></span>
                    </span>
                <?php else: ?>
                    <span class="psy-exam-logo-mark" aria-hidden="true">
                        <span class="psy-ring psy-ring-a"></span>
                        <span class="psy-ring psy-ring-b"></span>
                    </span>
                <?php endif; ?>
            </a>
            <?php if ( $therapist_name !== '' ): ?>
            <div class="psy-exam-therapist-name"><?php echo esc_html( $therapist_name ); ?></div>
            <?php endif; ?>
        </header>
        <?php
    }

    /**
     * فوتر مینیمالِ صفحه‌ی مستقل آزمون: شماره پشتیبانی (همان تنظیمِ
     * zdp_support_phone که در پنل مراجعین هم استفاده می‌شود — یک شماره‌ی
     * پشتیبانی مشترک برای کل اکوسیستم، نه دو تنظیم جدا در دو پلاگین) و
     * لینک بازگشت به صفحه اصلی سایت.
     */
    public static function render_page_footer(): void {
        $support_phone = trim( (string) get_option( 'zdp_support_phone', '' ) );
        ?>
        <footer class="psy-exam-chrome-footer">
            <?php if ( $support_phone !== '' ): ?>
            <a href="tel:<?php echo esc_attr( preg_replace( '/[^0-9+]/', '', $support_phone ) ); ?>" class="psy-exam-support-link">
                پشتیبانی: <?php echo esc_html( $support_phone ); ?>
            </a>
            <?php endif; ?>
            <a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="psy-exam-back-link">
                <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M15 6L9 12L15 18" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                <span>بازگشت به صفحه اصلی</span>
            </a>
        </footer>
        <?php
    }
}

// ── Fallback enqueue: اگر shortcode [psy_exam] روی صفحه معمولی استفاده شود ─
// (بدون ?psy_exam=1 در URL، template_redirect اجرا نمی‌شود و assets لود نمی‌شوند)
add_action( 'wp_enqueue_scripts', function () {
    // فقط اگر template_redirect قبلاً assets را enqueue نکرده باشد
    if ( wp_style_is( 'psy-quiz-front', 'enqueued' ) ) return;
    // بررسی اینکه آیا صفحه جاری شامل shortcode [psy_exam] است
    global $post;
    if ( ! $post || ! has_shortcode( $post->post_content, 'psy_exam' ) ) return;

    $font_deps = class_exists( 'Pas_Core_Fonts' ) ? [ Pas_Core_Fonts::handle() ] : [];
    // توکن‌های طراحی مشترک اکوسیستم: در frontend.css متغیرهای --psy-*
    // اکنون به var(--pivand-*, fallback) اشاره می‌کنند.
    if ( class_exists( 'Pas_Core_Tokens' ) ) { $font_deps[] = Pas_Core_Tokens::handle(); }
    wp_enqueue_style( 'psy-quiz-front', PSY_URL . 'assets/css/frontend.css', $font_deps, PSY_VERSION );
    // Chart.js را به صورت پیش‌فرض لود می‌کنیم چون در این مرحله test object در دسترس نیست
    Psy_Quiz_Assets::enqueue_chart_js();
    wp_enqueue_script( 'psy-quiz-front', PSY_URL . 'assets/js/frontend.js', [ 'jquery', Psy_Quiz_Assets::handle() ], PSY_VERSION, true );
    wp_localize_script( 'psy-quiz-front', 'PsyQuiz', [
        'rest_url' => esc_url_raw( rest_url( 'psy/v1/' ) ),
        'nonce'    => wp_create_nonce( 'wp_rest' ),
    ] );
} );

// ── تشخیص خودکار صفحه آزمون از طریق query param ──────────────────────────
add_action( 'template_redirect', function () {
    if ( ! isset( $_GET['psy_exam'] ) ) return;

    $token = isset( $_GET['token'] ) ? sanitize_text_field( wp_unslash( $_GET['token'] ) ) : '';

    // validate یک‌بار — نتیجه را cache می‌کنیم تا render() دوباره کوئری نزند
    $inv = $token ? Psy_Quiz_Invitation_Manager::validate( $token ) : null;
    Psy_Quiz_Frontend::set_cached_inv( $inv );

    // تصمیم Chart.js از روی test object — بدون کوئری اضافه
    // باگ #11: از needs_chart() که در BaseTest پیاده‌سازی شده استفاده می‌کنیم.
    // آزمون‌هایی که false برگردانند (مثل ACE) باعث می‌شوند Chart.js لود نشود.
    $_needs_chart = true;
    if ( $inv ) {
        $_test_peek   = \PsychAssessments\Core\Registry::get( $inv->quiz_id );
        $_needs_chart = ! $_test_peek || $_test_peek->needs_chart();
    }

    // بارگذاری assets — باید قبل از رندر header صورت گیرد
    $font_deps = class_exists( 'Pas_Core_Fonts' ) ? [ Pas_Core_Fonts::handle() ] : [];
    // توکن‌های طراحی مشترک اکوسیستم: در frontend.css متغیرهای --psy-*
    // اکنون به var(--pivand-*, fallback) اشاره می‌کنند.
    if ( class_exists( 'Pas_Core_Tokens' ) ) { $font_deps[] = Pas_Core_Tokens::handle(); }
    wp_enqueue_style(
        'psy-quiz-front',
        PSY_URL . 'assets/css/frontend.css',
        $font_deps,
        PSY_VERSION
    );

    if ( $_needs_chart ) {
        Psy_Quiz_Assets::enqueue_chart_js();
    }
    wp_enqueue_script(
        'psy-quiz-front',
        PSY_URL . 'assets/js/frontend.js',
        $_needs_chart ? [ 'jquery', Psy_Quiz_Assets::handle() ] : [ 'jquery' ],
        PSY_VERSION,
        true
    );
    wp_localize_script( 'psy-quiz-front', 'PsyQuiz', [
        'rest_url' => esc_url_raw( rest_url( 'psy/v1/' ) ),
        'nonce'    => wp_create_nonce( 'wp_rest' ),
    ] );

    // رندر مستقیم — بدون هدر/فوتر قالب سایت (منو/سایدبار/فوتر سایت حواس
    // مراجع را از خودِ آزمون پرت می‌کند). به‌جایش یک صفحه‌ی مستقلِ کامل
    // می‌سازیم که فقط شامل لوگو + نام درمانگر (هدر) و شماره پشتیبانی +
    // لینک بازگشت به صفحه اصلی (فوتر) است — دقیقاً هویت بصری همان صفحات
    // مستقلِ رو‌به‌مراجعِ پلاگین پنل مراجعین (ورود/نظرسنجی). wp_head()/
    // wp_footer() عمداً حفظ شده‌اند (برخلاف get_header()/get_footer() که
    // مستقیماً فایل قالب سایت را لود می‌کنند) چون هوک‌های عمومی وردپرس‌اند،
    // نه چیدمان بصری قالب — سایر افزونه‌ها (تحلیل، سئو و...) بدون آن‌ها کار
    // نمی‌کنند.
    ?><!DOCTYPE html>
<html lang="fa-IR" dir="rtl">
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo esc_html( get_bloginfo( 'name' ) ); ?> — آزمون آنلاین</title>
<?php wp_head(); ?>
</head>
<body class="psy-exam-standalone-body">
<?php
    Psy_Quiz_Frontend::render_page_header();
    echo '<div class="psy-exam-page-wrap">';
    echo do_shortcode( '[psy_exam]' );
    echo '</div>';
    Psy_Quiz_Frontend::render_page_footer();
    wp_footer();
?>
</body>
</html>
<?php
    exit;
} );
