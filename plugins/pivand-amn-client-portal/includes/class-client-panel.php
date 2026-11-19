<?php

declare(strict_types=1);

namespace ZoojDarman\ClientPortal;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * شورت‌کد اصلی پنل مراجع: [zdp_client_panel]
 * اگر کاربر لاگین نباشد، فرم OTP نمایش داده می‌شود؛ در غیر این صورت تب‌های
 * نوبت‌ها، آزمون‌ها (از طریق Quiz_Bridge)، پیام‌ها نمایش داده می‌شوند.
 *
 * تمام درخواست‌های تغییردهنده‌ی وضعیت از طریق AJAX + nonce انجام می‌شوند؛
 * هیچ فرمی مستقیماً به همان صفحه POST نمی‌کند، بنابراین مشکل «رفرش صفحه بعد
 * از رزرو نوبت باعث رزرو تکراری می‌شود» که در نسخه‌ی قبلی وجود داشت، ساختاراً
 * دیگر امکان‌پذیر نیست.
 */
final class Client_Panel
{
    private Booking $booking;
    private Messages $messages;
    private Survey $survey;
    private Quiz_Bridge $quiz_bridge;
    private Intake $intake;

    public function __construct(Booking $booking, Messages $messages, Survey $survey, Quiz_Bridge $quiz_bridge, Intake $intake)
    {
        $this->booking     = $booking;
        $this->messages    = $messages;
        $this->survey      = $survey;
        $this->quiz_bridge = $quiz_bridge;
        $this->intake      = $intake;

        add_shortcode('zdp_client_panel', [$this, 'render']);

        add_action('rest_api_init', [$this, 'register_rest_routes']);

        // توجه: قبلاً اینجا یک هوک template_redirect جداگانه برای غیرفعال‌سازی
        // کش وجود داشت که فقط برای صفحاتی که شورت‌کد مستقیماً در post_content
        // آن‌ها بود کار می‌کرد (نه صفحات ساخته‌شده با صفحه‌ساز). این منطق حالا
        // مستقیماً داخل render() اجرا می‌شود — نگاه کن به Cache_Guard::no_store().
    }

    /**
     * ریشه‌ی واقعی باگ «بدون وارد کردن کد تایید، پنل باز است»:
     * صفحه‌ی /panel/ یک بار (وقتی کاربری لاگین بوده) توسط لایه‌ی کشِ سرور
     * (WP Rocket و/یا LiteSpeed Cache) کش شده و از آن به بعد همان HTML شخصی
     * برای همه‌ی بازدیدکننده‌ها — از جمله کاربرهای لاگین‌نشده — سرو می‌شده.
     * تنظیم درست استثنا در تنظیمات کش راه‌حل است، اما یک وابستگیِ بیرون از
     * کد است و اگر یک‌بار دیگر هم فراموش/ریست شود همین باگ برمی‌گردد.
     *
     * راه‌حل ساختاری: render() دیگر هرگز HTML وابسته به کاربر (فرم ورود یا
     * محتوای پنل) را مستقیم چاپ نمی‌کند. فقط یک قالب خالیِ کاملاً یکسان برای
     * همه برمی‌گرداند — پس حتی اگر لایه‌ی کش همین خروجی را ابدی کش کند، هیچ
     * داده‌ی شخصی‌ای درونش نیست و کش‌شدنش بی‌خطر است. تشخیصِ لاگین/عدم لاگین
     * و ساخت HTML واقعی به‌جای آن از طریق یک درخواست REST مجزا (POST
     * zdp/v1/panel-state) انجام می‌شود که همیشه در لحظه‌ی واقعیِ بازدید در
     * مرورگر کاربر اجرا می‌شود، نه در لحظه‌ی کش‌شدنِ صفحه.
     */
    public function register_rest_routes(): void
    {
        // پچ محلی: اندپوینت به GET برگردانده شد — دقیقاً مثل نسخه‌ی ۱.۵.۶
        // (که مشکل ورود نداشت). فرض «اکثر لایه‌های کش فقط GET را کش می‌کنند»
        // درست بود، اما این سایت اصلاً پشت هیچ لایه‌ی کش/CDN‌ای نیست (تأیید
        // شد)؛ در عوض به نظر می‌رسد یک لایه‌ی امنیتی/فایروال سطح هاست دقیقاً
        // درخواست‌های POST به wp-json را سخت‌گیرانه‌تر بررسی می‌کند (تا حدی که
        // یک صفحه‌ی چالش/خطای «کوکی» برمی‌گرداند) — یعنی همان تغییری که قرار
        // بود یک ریسک را کم کند، یک ریسک واقعی و بزرگ‌تر (مسدود شدن کامل
        // بارگذاری پنل) ساخته بود. Cache_Guard::no_store() به‌عنوان دفاع
        // اصلی در برابر کش شدن باقی می‌ماند.
        register_rest_route('zdp/v1', '/panel-state', [
            'methods'             => 'GET',
            'callback'            => [$this, 'rest_panel_state'],
            'permission_callback' => [$this, 'rest_permission_check'],
        ]);

        // پچ محلی: اندپوینت bootstrap-nonce (که در ۱.۵.۶ اصلاً وجود نداشت و
        // فقط برای یک سناریوی فرضیِ کش CDN اضافه شده بود — سناریویی که روی
        // این سایت اصلاً مصداق ندارد چون سایت پشت هیچ CDN‌ای نیست) کامل حذف
        // شد؛ یک درخواست POST اضافه‌ی دیگر بود که می‌توانست توسط همان لایه‌ی
        // امنیتی مسدود شود، بدون هیچ فایده‌ای برای این سایت مشخص.
    }

    /**
     * همان الگوی nonce استاندارد REST وردپرس که OTP_Auth هم استفاده می‌کند
     * (X-WP-Nonce با action ثابت wp_rest). عمداً اینجا هم تکرار شده تا
     * Client_Panel به OTP_Auth وابستگی مستقیم پیدا نکند — طبق قرارداد
     * معماری این اکوسیستم، کلاس‌ها فقط از طریق هوک/فیلتر به هم وصل می‌شوند.
     */
    public function rest_permission_check(\WP_REST_Request $request): bool|\WP_Error
    {
        $nonce = $request->get_header('X-WP-Nonce');

        if (!$nonce || !wp_verify_nonce($nonce, 'wp_rest')) {
            // نکته: لاگ تشخیصی موقتی که قبلاً اینجا بود (برای پیدا کردن ریشه‌ی
            // باگ «نشست منقضی شده بعد از ورود موفق») حذف شد؛ ریشه پیدا شد
            // (Login_Service روی /panel/ کش‌شونده توسط CDN تکیه می‌کرد) و در
            // class-login-service.php رفع شده (نگاه کن به توضیحات آن کلاس).
            // audit_log('otp_login_success'/'otp_login_cookie_set') در همان
            // کلاس برای رهگیری آینده کافی است.
            audit_log('zdp_rest_permission_denied', ['route' => $request->get_route()]);

            return new \WP_Error(
                'zdp_rest_forbidden',
                'نشست شما منقضی شده. لطفاً صفحه را رفرش کنید.',
                ['status' => 403]
            );
        }

        return true;
    }

    /**
     * این پاسخ همیشه دینامیک است (REST GET با نیازِ nonce معتبر)؛ لایه‌های
     * کش صفحه/CDN معمولاً درخواست‌های wp-json را کش نمی‌کنند، ولی برای
     * اطمینان کامل هدرهای no-cache صریح هم اینجا ست می‌شود.
     */
    public function rest_panel_state(\WP_REST_Request $request): \WP_REST_Response
    {
        Cache_Guard::no_store();

        $logged_in = is_user_logged_in();

        $html = $logged_in
            ? $this->render_panel(get_current_user_id())
            : $this->render_login_form();

        return new \WP_REST_Response([
            'success' => true,
            'data'    => [
                'logged_in' => $logged_in,
                'html'      => $html,
            ],
        ], 200);
    }

    /**
     * توجه مهم (باگ کشف‌شده): این تابع قبلاً فقط روی template_redirect و با
     * چک has_shortcode($post->post_content, ...) اجرا می‌شد. این روش وقتی
     * شورت‌کد مستقیم توی ویرایشگر کلاسیک/بلوکی نوشته شده باشد کار می‌کند،
     * ولی وقتی شورت‌کد داخل یک ویجت صفحه‌ساز (المنتور، دیوی، بریکس و...)
     * قرار بگیرد، آن صفحه‌ساز چیدمان را در یک متای جدا (مثلاً _elementor_data)
     * ذخیره می‌کند، نه در post_content — پس has_shortcode() هرگز شورت‌کد را
     * پیدا نمی‌کند، محافظت کش هرگز فعال نمی‌شود، و افزونه‌ی کش صفحه، صفحه‌ی
     * ورود (یا حتی نسخه‌ی لاگین‌شده) را مثل یک صفحه‌ی معمولی کش می‌کند.
     * برای همین این محافظت الان به‌جای تکیه بر متن خامِ صفحه، مستقیماً همان
     * لحظه‌ای که خودِ شورت‌کد واقعاً در حال اجراست (ابتدای render()) صدا زده
     * می‌شود — مستقل از این‌که شورت‌کد از کجا رندر شده.
     */
    public function render(): string
    {
        // مهم: این خروجی دیگر عمداً به is_user_logged_in() یا هر داده‌ی
        // شخصیِ دیگری وابسته نیست — قالب همیشه برای هر بازدیدکننده‌ای
        // (لاگین یا نه) دقیقاً یکسان است. محتوای واقعی بعداً توسط panel.js
        // از طریق GET zdp/v1/panel-state (نگاه کن به rest_panel_state)
        // گرفته و جایگزین می‌شود. این‌طور اگر همین HTML توسط CDN/کش صفحه‌ای
        // کش شود (که همیشه ممکن است، حتی با بهترین تنظیمات) هیچ نشتی داده‌ی
        // شخصی و هیچ بایپس ورود رخ نمی‌دهد.
        Cache_Guard::no_store();

        wp_enqueue_style('zdp-panel');
        wp_enqueue_script('zdp-panel');

        ob_start();
        ?>
        <script>
        // نکته: این اسکریپت اینلاین است (نه در panel.js) و همیشه، حتی اگر
        // فایل‌های داخل wp_enqueue_script به هر دلیلی (کش دارایی‌ها، CDN،
        // خطای شبکه) لود نشوند، بلافاصله همراه خودِ HTML اجرا می‌شود.
        //
        // وضعیت فعلی: از نسخه‌ی ۱.۶.۴.۳، Login_Service دیگر هیچ‌وقت کوئری‌آرگ
        // zdp_ok را به فرود نهایی اضافه نمی‌کند (نگاه کن به توضیح تاریخچه
        // بالای کلاس Login_Service)، پس این بلوک عملاً هیچ‌وقت اجرا نمی‌شود.
        // عمداً حذف نشده و به‌عنوان یک پاک‌سازی دفاعیِ بی‌ضرر نگه داشته شده:
        // اگر در آینده به‌خاطر کش صفحه/CDN دوباره لازم شد Login_Service آن
        // کوئری‌آرگ ضدکش را تولید کند، این کد سمت کلاینت از قبل آماده است و
        // نیازی به تغییر همزمان اینجا نیست.
        (function () {
            try {
                var url = new URL(window.location.href);
                if (url.searchParams.has('zdp_ok')) {
                    url.searchParams.delete('zdp_ok');
                    window.history.replaceState(null, '', url.pathname + url.search + url.hash);
                }
            } catch (e) {
                // مرورگرهای خیلی قدیمی بدون URL/URLSearchParams: بی‌خیالش
                // می‌شویم، فقط یک کوئری‌آرگ اضافه در نوار آدرس می‌ماند، هیچ
                // عملکردی خراب نمی‌شود.
            }
        })();
        </script>
        <div id="zdp-panel-app" class="zdp-panel-loading" dir="rtl" data-loaded="0">
            <p class="zdp-panel-loading-text">در حال بارگذاری...</p>
        </div>
        <?php
        return (string) ob_get_clean();
    }

    private function render_login_form(): string
    {
        // پیام مخصوص حالتی که Login_Service توکن ورود را نامعتبر/منقضی تشخیص
        // داده (مثلاً کاربر بیش از ۶۰ ثانیه بعد از تایید کد، لینک را باز کرده،
        // یا همان لینک را دوباره در تب دیگری استفاده کرده). بدون این پیام،
        // کاربر فقط می‌دید که دوباره به فرم شماره موبایل برگشته بدون هیچ
        // توضیحی — همان تجربه‌ی گیج‌کننده‌ای که این بازطراحی برایش انجام شد.
        $bridge_error = isset($_GET['zdp_auth_error'])
            ? 'نشست ورود شما منقضی شده یا نامعتبر است. لطفاً دوباره کد تایید را دریافت کنید.'
            : '';

        // اگر سایت یک لوگوی واقعی در تنظیمات وردپرس (سفارشی‌ساز → هویت سایت)
        // دارد، همان استفاده می‌شود؛ در غیر این صورت یک نشان جایگزین (دو حلقه‌ی
        // متصل، نمادِ «پیوند») که خودِ این افزونه ترسیم می‌کند نمایش داده
        // می‌شود — پس صفحه‌ی ورود هرگز بدون هویت بصری نیست.
        $custom_logo_id = get_theme_mod('custom_logo');
        $logo_html      = $custom_logo_id ? wp_get_attachment_image($custom_logo_id, 'medium', false, [
            'class' => 'zdp-welcome-logo-img',
            'alt'   => esc_attr(get_bloginfo('name')),
        ]) : '';

        $support_phone = trim((string) get_option('zdp_support_phone', ''));

        ob_start();
        ?>
        <div class="zdp-login-stage" dir="rtl" lang="fa-IR">
        <div class="zdp-login-shell">
            <aside class="zdp-login-brand">
                <div class="zdp-login-brand-logo">
                    <?php if ($logo_html) : ?>
                        <?php echo $logo_html; ?>
                    <?php else : ?>
                        <span class="zdp-login-brand-mark" aria-hidden="true"><?php echo $this->icon('link'); ?></span>
                        <span>پیوند امن</span>
                    <?php endif; ?>
                </div>
                <h2 class="zdp-login-brand-title">به پیوند امن خوش آمدید</h2>
                <p class="zdp-login-brand-lead">نوبت‌ها، آزمون‌ها و پیام‌های شما در یک جای امن و خصوصی؛ ورود با یک کد یک‌بارمصرف.</p>
                <ul class="zdp-login-points">
                    <li class="zdp-login-point"><?php echo $this->icon('shield'); ?><span>دادهٔ بالینی رمزنگاری‌شده</span></li>
                    <li class="zdp-login-point"><?php echo $this->icon('user'); ?><span>دسترسی فقط برای خود شما</span></li>
                    <li class="zdp-login-point"><?php echo $this->icon('phone'); ?><span>ورود سریع با کد پیامکی</span></li>
                </ul>
            </aside>

            <div class="zdp-login-main">
            <div class="zdp-panel zdp-login" dir="rtl">
            <div class="zdp-login-mobile-brand">
                <span class="zdp-login-brand-mark" aria-hidden="true"><?php echo $this->icon('link'); ?></span>
                <span>پیوند امن</span>
            </div>

            <div class="zdp-login-head">
                <h2 class="zdp-login-title">ورود به پنل مراجع</h2>
                <p class="zdp-login-sub">برای ورود، شماره موبایل یا ایمیل خود را وارد کنید؛ کد تأیید برایتان ارسال می‌شود.</p>
            </div>

            <?php if ($bridge_error) : ?>
                <p class="zdp-login-bridge-error" role="alert"><?php echo $this->icon('alert'); ?><?php echo esc_html($bridge_error); ?></p>
            <?php endif; ?>

            <div class="zdp-login-channel-tabs" role="tablist" aria-label="روش ورود">
                <button type="button" id="zdp-channel-mobile-btn" class="zdp-login-channel-tab active" role="tab" aria-selected="true">ورود با موبایل</button>
                <button type="button" id="zdp-channel-email-btn" class="zdp-login-channel-tab" role="tab" aria-selected="false">ورود با ایمیل</button>
            </div>

            <div class="zdp-login-steps" data-step="mobile" aria-hidden="true">
                <span class="zdp-step-dot zdp-step-dot-1"><span class="zdp-step-dot__n">۱</span><span class="zdp-step-dot__t">شماره</span></span>
                <span class="zdp-step-dot zdp-step-dot-2"><span class="zdp-step-dot__n">۲</span><span class="zdp-step-dot__t">کد تایید</span></span>
            </div>

            <div id="zdp-mobile-step">
                <label for="zdp-mobile">شماره موبایل</label>
                <div class="zdp-input-affix">
                    <span class="zdp-input-affix-icon" aria-hidden="true"><?php echo $this->icon('phone'); ?></span>
                    <input type="tel" id="zdp-mobile" inputmode="tel" placeholder="0912 345 6789" maxlength="16" autocomplete="tel">
                </div>
                <button type="button" id="zdp-send-otp-btn" class="zdp-btn-primary">
                    <span class="zdp-btn-label">دریافت کد تایید</span>
                    <span class="zdp-btn-spinner" aria-hidden="true"></span>
                </button>
            </div>

            <div id="zdp-email-step" style="display:none;">
                <label for="zdp-email">ایمیل</label>
                <div class="zdp-input-affix">
                    <span class="zdp-input-affix-icon" aria-hidden="true"><?php echo $this->icon('mail'); ?></span>
                    <input type="email" id="zdp-email" placeholder="you@example.com" autocomplete="email" dir="ltr">
                </div>
                <p class="zdp-field-hint">کد تایید به همین ایمیل ارسال می‌شود؛ نیازی به ثبت قبلی نیست.</p>
                <button type="button" id="zdp-send-otp-email-btn" class="zdp-btn-primary">
                    <span class="zdp-btn-label">دریافت کد تایید</span>
                    <span class="zdp-btn-spinner" aria-hidden="true"></span>
                </button>
            </div>

            <div id="zdp-otp-step" style="display:none;">
                <label id="zdp-otp-label" for="zdp-otp-box-0">کد تایید</label>
                <div class="zdp-otp-boxes" data-otp-boxes dir="ltr" role="group" aria-labelledby="zdp-otp-label">
                    <?php for ($i = 0; $i < 5; $i++) : ?>
                        <input type="tel" inputmode="numeric" id="zdp-otp-box-<?php echo (int) $i; ?>" aria-label="<?php echo esc_attr( sprintf( 'رقم %d از 5 کد تایید', $i + 1 ) ); ?>" maxlength="<?php echo $i === 0 ? 5 : 1; ?>" class="zdp-otp-box" data-otp-index="<?php echo (int) $i; ?>" <?php echo $i === 0 ? 'autocomplete="one-time-code"' : 'autocomplete="off"'; ?>>
                    <?php endfor; ?>
                </div>
                <input type="hidden" id="zdp-otp" autocomplete="one-time-code">
                <button type="button" id="zdp-verify-otp-btn" class="zdp-btn-primary">
                    <span class="zdp-btn-label">ورود</span>
                    <span class="zdp-btn-spinner" aria-hidden="true"></span>
                </button>
                <div class="zdp-otp-resend-row">
                    <button type="button" id="zdp-change-mobile-btn" class="zdp-link-btn"><?php echo $this->icon('edit'); ?><span id="zdp-change-label">تغییر شماره</span></button>
                    <span class="zdp-otp-resend-wrap">
                        <span id="zdp-otp-timer" class="zdp-otp-timer"></span>
                        <button type="button" id="zdp-resend-otp-btn" class="zdp-link-btn" style="display:none;">ارسال مجدد کد</button>
                    </span>
                </div>
                <?php if ($support_phone !== '') : ?>
                    <p id="zdp-otp-support" class="zdp-otp-support" hidden>
                        کد دریافت نکردید؟ پشتیبانی:
                        <a href="tel:<?php echo esc_attr(preg_replace('/[^0-9+]/', '', $support_phone)); ?>"><?php echo esc_html($support_phone); ?></a>
                    </p>
                <?php endif; ?>
            </div>

            <p id="zdp-login-message" role="status" aria-live="polite"></p>

            <footer class="zdp-panel-footer zdp-login-footer">
                <a href="<?php echo esc_url(home_url('/')); ?>" class="zdp-panel-back-link">
                    <?php echo $this->icon('arrow-end'); ?>
                    <span>بازگشت به سایت</span>
                </a>
                <?php if ($support_phone !== '') : ?>
                    <span class="zdp-login-support">پشتیبانی: <a href="tel:<?php echo esc_attr(preg_replace('/[^0-9+]/', '', $support_phone)); ?>"><?php echo esc_html($support_phone); ?></a></span>
                <?php endif; ?>
            </footer>
            </div>
            </div>
        </div>
        </div>
        <?php
        return (string) ob_get_clean();
    }

    /**
     * آیکن‌های SVG درون‌خطی سبک (بدون فونت/کتابخانه‌ی خارجی، بدون درخواست
     * HTTP اضافه) که فقط برای این پوسته‌ی جدید UI استفاده می‌شوند. عمداً در
     * همین کلاس presentation نگه داشته شده‌اند و هیچ منطق کسب‌وکاری را لمس
     * نمی‌کنند؛ اگر آیکن ناشناخته‌ای خواسته شود رشته‌ی خالی برمی‌گردد (هیچ‌وقت
     * خروجی را نمی‌شکند).
     */
    private function icon(string $name): string
    {
        $icons = [
            'appointments' => '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><rect x="3.5" y="5" width="17" height="15.5" rx="3" stroke="currentColor" stroke-width="1.6"/><path d="M3.5 9.5H20.5" stroke="currentColor" stroke-width="1.6"/><path d="M8 3V6.5M16 3V6.5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/><circle cx="8" cy="13.5" r="1.15" fill="currentColor"/><circle cx="12" cy="13.5" r="1.15" fill="currentColor"/></svg>',
            'quizzes'      => '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><rect x="5" y="3.5" width="14" height="17" rx="2.5" stroke="currentColor" stroke-width="1.6"/><path d="M8.5 8.5H15.5M8.5 12H15.5M8.5 15.5H12.5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>',
            'messages'     => '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M4 6.5C4 5.12 5.12 4 6.5 4H17.5C18.88 4 20 5.12 20 6.5V14.5C20 15.88 18.88 17 17.5 17H10L6 20.5V17H6.5C5.12 17 4 15.88 4 14.5V6.5Z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/></svg>',
            'intake'       => '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M7 3.5h7l4 4V20a.5.5 0 0 1-.5.5h-10.5A.5.5 0 0 1 6.5 20V4A.5.5 0 0 1 7 3.5Z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/><path d="M9.5 12h5M9.5 15.5h5M14 3.5V8h4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>',
            'survey'       => '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M12 3.5L14.47 8.63L20 9.44L16 13.44L16.94 19.06L12 16.35L7.06 19.06L8 13.44L4 9.44L9.53 8.63L12 3.5Z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/></svg>',
            'logout'       => '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M9 5H6.5C5.4 5 4.5 5.9 4.5 7V17C4.5 18.1 5.4 19 6.5 19H9" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/><path d="M14.5 15.5L19 12L14.5 8.5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/><path d="M19 12H9.5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>',
            'phone'        => '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M6.6 10.8C7.9 13.4 10 15.5 12.6 16.8L14.6 14.8C14.85 14.55 15.22 14.47 15.55 14.58C16.62 14.94 17.76 15.14 18.94 15.14C19.45 15.14 19.86 15.55 19.86 16.06V19.14C19.86 19.65 19.45 20.06 18.94 20.06C10.66 20.06 3.94 13.34 3.94 5.06C3.94 4.55 4.35 4.14 4.86 4.14H7.94C8.45 4.14 8.86 4.55 8.86 5.06C8.86 6.24 9.06 7.38 9.42 8.45C9.53 8.78 9.45 9.15 9.2 9.4L6.6 10.8Z" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round"/></svg>',
            'mail'         => '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><rect x="3.5" y="5.5" width="17" height="13" rx="2.3" stroke="currentColor" stroke-width="1.5"/><path d="M4.2 6.5L12 12.5L19.8 6.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>',
            'edit'         => '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M4 20L4.7 16.9L15.6 6C16.2 5.4 17.2 5.4 17.8 6L18.9 7.1C19.5 7.7 19.5 8.7 18.9 9.3L8 20.2L4 20Z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/></svg>',
            'alert'        => '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M12 3.5L21.5 20H2.5L12 3.5Z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/><path d="M12 9.5V13.5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/><circle cx="12" cy="16.3" r="0.9" fill="currentColor"/></svg>',
            'arrow-end'    => '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M15 6L9 12L15 18" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>',
            'link'         => '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M9.5 14.5L14.5 9.5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/><path d="M8 12L6.2 13.8C4.9 15.1 4.9 17.2 6.2 18.5C7.5 19.8 9.6 19.8 10.9 18.5L12.7 16.7" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/><path d="M16 12L17.8 10.2C19.1 8.9 19.1 6.8 17.8 5.5C16.5 4.2 14.4 4.2 13.1 5.5L11.3 7.3" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>',
            'shield'       => '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M12 3.5L19 6V11.5C19 15.6 16.1 19 12 20.5C7.9 19 5 15.6 5 11.5V6L12 3.5Z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/><path d="M9 12L11.2 14.2L15.2 10.2" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>',
            'user'         => '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="8.5" r="3.5" stroke="currentColor" stroke-width="1.6"/><path d="M5.5 20C5.5 16.6 8.4 14.5 12 14.5C15.6 14.5 18.5 16.6 18.5 20" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>',
            'clock'        => '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="12" r="8.25" stroke="currentColor" stroke-width="1.6"/><path d="M12 7.5V12L15 14" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>',
            'check'        => '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M5 12.5L9.5 17L19 7.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>',
        ];

        return $icons[$name] ?? '';
    }

    private function render_panel(int $user_id): string
    {
        $mobile        = zdp_get_user_mobile($user_id);
        $full_name     = (string) get_user_meta($user_id, 'zdp_full_name', true);
        $bookings      = $this->booking->get_bookings_for_user($user_id);
        $msgs          = $this->messages->get_messages_for_user($user_id);
        $next_booking  = $this->find_next_upcoming_booking($bookings);
        $support_phone = trim((string) get_option('zdp_support_phone', ''));

        // باگ رفع‌شده (حساب‌های خودسرویسِ ایمیل، بدون هیچ موبایلِ شناخته‌شده):
        // قبلاً وقتی $mobile خالی بود، to_local_format('') به‌جای رشته‌ی
        // خالی مقدار «0» برمی‌گرداند (چون preg_replace روی رشته‌ی خالی چیزی
        // حذف نمی‌کند و کد همیشه با '0' + ارقام باقی‌مانده می‌ساخت) — یعنی
        // مراجعی که فقط با ایمیل وارد شده بود، به‌جای نامش یا ایمیلش، عدد
        // بی‌معنیِ «۰» را در بالای پنل می‌دید. حالا هویتِ نمایشی صریحاً بین
        // موبایل (اگر موجود) و ایمیلِ واقعیِ کاربر (اگر موبایلی نیست) انتخاب
        // می‌شود.
        $email_identity = $mobile === '' ? (string) (get_userdata($user_id)->user_email ?? '') : '';
        $identity        = $mobile !== '' ? to_local_format($mobile) : $email_identity;
        $display_name    = $full_name ?: ($identity !== '' ? $identity : 'مراجع');

        // باگ رفع‌شده (گزارش‌شده: «مراجع خارج از کشور با ایمیل وارد شده که
        // آزمون بدهد، آزمون براش نبود»): قبلاً همیشه فقط از
        // get_pending_quizzes_for_mobile استفاده می‌شد که وقتی $mobile خالی
        // است (دقیقاً حالت مراجعینِ خارج از کشور بدون شماره‌ی ایرانی) بدون
        // هیچ تلاش دیگری [] برمی‌گرداند — حتی اگر درمانگر آزمونی برای همان
        // ایمیل تعریف کرده باشد. حالا برای این حالت از مسیر ایمیلی
        // (psy_get_pending_quizzes_for_email، نگاه کنید به Quiz_Bridge)
        // استفاده می‌شود.
        $quizzes = $mobile !== ''
            ? $this->quiz_bridge->get_pending_quizzes_for_mobile($mobile)
            : $this->quiz_bridge->get_pending_quizzes_for_email($email_identity);


        // برای آواتار حرفی: اگر اسم موجود است اولین حرف آن؛ وگرنه دو رقم آخر
        // شماره موبایل؛ وگرنه (حساب ایمیلِ مستقل بدون نام) حرف اول ایمیل —
        // همیشه چیزی برای نمایش وجود دارد، هرگز خالی نیست.
        if ($full_name !== '') {
            $avatar_text = mb_substr($full_name, 0, 1);
        } elseif ($mobile !== '') {
            $avatar_text = mb_substr($mobile, -2);
        } elseif ($email_identity !== '') {
            $avatar_text = mb_strtoupper(mb_substr($email_identity, 0, 1));
        } else {
            $avatar_text = '؟';
        }

        ob_start();
        ?>
        <div class="zdp-panel zdp-panel-shell" dir="rtl" data-user-mobile="<?php echo esc_attr($mobile); ?>">

            <header class="zdp-shell-topbar">
                <div class="zdp-shell-brand">
                    <span class="zdp-shell-brand-mark" aria-hidden="true">
                        <span class="zdp-ring zdp-ring-a"></span>
                        <span class="zdp-ring zdp-ring-b"></span>
                    </span>
                    <span class="zdp-shell-brand-name">پیوند امن</span>
                </div>

                <div class="zdp-shell-user">
                    <span class="zdp-shell-user-avatar" aria-hidden="true"><?php echo esc_html($avatar_text); ?></span>
                    <span class="zdp-shell-user-info">
                        <strong class="zdp-shell-user-name"><?php echo esc_html($display_name); ?></strong>
                        <?php if ($full_name && $identity !== '') : ?>
                            <span class="zdp-shell-user-mobile" dir="ltr"><?php echo esc_html($identity); ?></span>
                        <?php endif; ?>
                    </span>
                    <a href="<?php echo esc_url(wp_logout_url(zdp_panel_url())); ?>" class="zdp-logout" title="خروج">
                        <?php echo $this->icon('logout'); ?>
                        <span class="zdp-shell-logout-label">خروج</span>
                    </a>
                </div>
            </header>

            <nav class="zdp-tabs" role="tablist">
                <button type="button" class="zdp-tab active" data-tab="appointments" role="tab" aria-selected="true">
                    <span class="zdp-tab-icon"><?php echo $this->icon('appointments'); ?></span>
                    <span class="zdp-tab-label">نوبت‌ها</span>
                </button>
                <button type="button" class="zdp-tab" data-tab="quizzes" role="tab" aria-selected="false">
                    <span class="zdp-tab-icon"><?php echo $this->icon('quizzes'); ?></span>
                    <span class="zdp-tab-label">آزمون‌ها</span>
                    <?php if (count($quizzes)) : ?><span class="zdp-tab-badge"><?php echo (int) count($quizzes); ?></span><?php endif; ?>
                </button>
                <button type="button" class="zdp-tab" data-tab="messages" role="tab" aria-selected="false">
                    <span class="zdp-tab-icon"><?php echo $this->icon('messages'); ?></span>
                    <span class="zdp-tab-label">پیام‌ها</span>
                </button>
                <button type="button" class="zdp-tab" data-tab="intake" role="tab" aria-selected="false">
                    <span class="zdp-tab-icon"><?php echo $this->icon('intake'); ?></span>
                    <span class="zdp-tab-label">فرم ارزیابی مراجع</span>
                    <?php if (!$this->intake->has_submitted($user_id)) : ?><span class="zdp-tab-badge" aria-label="تکمیل نشده">!</span><?php endif; ?>
                </button>
                <button type="button" class="zdp-tab" data-tab="survey" role="tab" aria-selected="false">
                    <span class="zdp-tab-icon"><?php echo $this->icon('survey'); ?></span>
                    <span class="zdp-tab-label">نظرسنجی</span>
                </button>
            </nav>

            <div class="zdp-shell-main">
                <?php echo $this->intake->render_banner($user_id); ?>
                <div class="zdp-stats-grid">
                    <div class="zdp-stat-card zdp-stat-card-highlight">
                        <span class="zdp-stat-icon"><?php echo $this->icon('clock'); ?></span>
                        <span class="zdp-stat-label">نوبت بعدی شما</span>
                        <?php if ($next_booking) : ?>
                            <strong class="zdp-stat-value"><?php echo esc_html($next_booking->service_name); ?></strong>
                            <span class="zdp-stat-sub">
                                <?php echo esc_html(Jalali::gregorian_to_jalali_string($next_booking->booking_date)); ?>
                                — ساعت <?php echo esc_html($next_booking->booking_time); ?>
                            </span>
                            <span class="zdp-status zdp-status-<?php echo esc_attr(zdp_status_slug($next_booking->status)); ?>"><?php echo esc_html($next_booking->status); ?></span>
                        <?php else : ?>
                            <strong class="zdp-stat-value zdp-stat-value-muted">نوبتی ثبت نشده</strong>
                            <span class="zdp-stat-sub">از تب «نوبت‌ها» یک وقت جدید رزرو کنید.</span>
                        <?php endif; ?>
                    </div>

                    <div class="zdp-stat-card">
                        <span class="zdp-stat-icon"><?php echo $this->icon('quizzes'); ?></span>
                        <span class="zdp-stat-label">آزمون‌های در انتظار</span>
                        <strong class="zdp-stat-value"><?php echo (int) count($quizzes); ?></strong>
                    </div>

                    <div class="zdp-stat-card">
                        <span class="zdp-stat-icon"><?php echo $this->icon('messages'); ?></span>
                        <span class="zdp-stat-label">پیام‌های ثبت‌شده</span>
                        <strong class="zdp-stat-value"><?php echo (int) count($msgs); ?></strong>
                    </div>
                </div>

                <section class="zdp-tab-panel" data-panel="appointments">
                    <?php echo $this->render_appointments_tab($bookings, $full_name, $user_id); ?>
                </section>

                <section class="zdp-tab-panel" data-panel="quizzes" hidden>
                    <?php echo $this->render_quizzes_tab($quizzes); ?>
                    <?php if (empty($quizzes) && !$this->quiz_bridge->is_quiz_plugin_active() && current_user_can('manage_options')) : ?>
                        <p class="zdp-admin-hint" style="margin-top:10px;font-size:12px;color:var(--zdp-soft);">
                            (فقط برای مدیر قابل مشاهده: افزونه‌ی آزمون به این پنل وصل نیست — فیلتر
                            <code>psy_get_pending_quizzes_for_phone</code> ثبت نشده. از پیشخوان → پیوند امن بررسی کنید.)
                        </p>
                    <?php endif; ?>
                </section>

                <section class="zdp-tab-panel" data-panel="messages" hidden>
                    <?php echo $this->render_messages_tab($msgs); ?>
                </section>

                <section class="zdp-tab-panel" data-panel="intake" hidden>
                    <?php echo $this->intake->render_tab($user_id); ?>
                </section>

                <section class="zdp-tab-panel" data-panel="survey" hidden>
                    <?php echo $this->render_survey_tab(); ?>
                </section>

                <footer class="zdp-panel-footer">
                    <?php if ($support_phone !== '') : ?>
                        <a href="tel:<?php echo esc_attr(preg_replace('/[^0-9+]/', '', $support_phone)); ?>" class="zdp-panel-support-link">
                            پشتیبانی: <?php echo esc_html($support_phone); ?>
                        </a>
                    <?php endif; ?>
                    <a href="<?php echo esc_url(home_url('/')); ?>" class="zdp-panel-back-link">بازگشت به سایت</a>
                </footer>
            </div>
        </div>
        <?php
        return (string) ob_get_clean();
    }

    /**
     * نزدیک‌ترین نوبتِ فعال (نه لغو/ناموفق) از همین لحظه به بعد؛ برای کارت
     * خلاصه‌ی بالای پنل. get_bookings_for_user نزولی مرتب می‌دهد، پس اینجا
     * فقط فیلتر و انتخاب کمینه‌ی تاریخ/ساعت لازم است، نه یک کوئری جدا به
     * دیتابیس.
     *
     * باگ رفع‌شده: قبلاً فقط booking_date با «امروز» مقایسه می‌شد، نه ساعتِ
     * دقیقِ نوبت. یعنی نوبتِ امروزِ ساعت ۹ صبح که «تایید شده» مانده (هیچ‌جای
     * مسیر آنلاین/زرین‌پال به‌صورت خودکار وضعیت را بعد از گذشتنِ زمانِ جلسه
     * به چیزی غیر از 'تایید شده' تغییر نمی‌دهد — این کار فقط دستی توسط
     * درمانگر از پیشخوان انجام می‌شود)، تا آخرِ همان روز — حتی ساعت‌ها بعد
     * از برگزاری واقعیِ جلسه — همچنان به‌عنوان «نوبت بعدیِ شما» در کارتِ
     * خلاصه‌ی بالای پنل نمایش داده می‌شد. حالا مقایسه بر اساسِ
     * تاریخ+ساعتِ کامل (نه فقط تاریخ) و current_time (وقت محلی سایت) است.
     */
    private function find_next_upcoming_booking(array $bookings): ?object
    {
        $now_datetime      = current_time('Y-m-d H:i');
        $inactive_statuses = ['ناموفق', 'لغو شده'];

        $upcoming = array_filter($bookings, static function ($b) use ($now_datetime, $inactive_statuses) {
            // booking_time از دیتابیس ممکن است به‌صورت 'HH:MM:SS' برگردد (ستون
            // TIME در MySQL)؛ substr همان الگوی به‌کاررفته در
            // Booking::get_booking_grid برای هم‌شکل‌کردن با 'HH:MM' است.
            $booking_datetime = $b->booking_date . ' ' . substr((string) $b->booking_time, 0, 5);

            return $booking_datetime >= $now_datetime && !in_array($b->status, $inactive_statuses, true);
        });

        if (empty($upcoming)) {
            return null;
        }

        usort($upcoming, static fn ($a, $b) => strcmp($a->booking_date . $a->booking_time, $b->booking_date . $b->booking_time));

        return $upcoming[0];
    }

    private function render_appointments_tab(array $bookings, string $full_name = '', int $user_id = 0): string
    {
        $services = $this->booking->get_services();

        // نوبتی که همین چند لحظه‌ی پیش از شورت‌کد عمومیِ صفحه‌ی اصلی
        // ([zdp_booking_widget]) ساخته شده — مراجع تازه با OTP وارد شده و
        // این اولین بار است که واقعاً وارد پنل می‌شود، پس JS آن لحظه (که
        // معمولاً کارت‌های کارت‌به‌کارت را همان‌جا نشان می‌دهد) اصلاً اجرا
        // نشده بود. نگاه کن به Booking::create_booking_internal برای جایی که
        // این ترنزینت ساخته می‌شود. one-time: بلافاصله پاک می‌شود تا با
        // رفرش بعدی صفحه دوباره نمایش داده نشود. توجه: این فقط پیام خوش‌آمد
        // «با موفقیت ثبت شد» است؛ کارت‌های بانکی و فرم آپلود رسید همیشه از
        // $pending_manual (پایین) می‌آیند، نه از این ترنزینت — تا با رفرش
        // صفحه یا ورود دوباره هم گم نشوند.
        $pending_payment = $user_id > 0 ? get_transient('zdp_pending_payment_' . $user_id) : false;
        if ($pending_payment) {
            delete_transient('zdp_pending_payment_' . $user_id);
        }

        // نوبتِ در-انتظار-تایید-واریزِ همین مراجع (اگر باشد) — نگاه کن به
        // Booking::get_pending_manual_payment_booking. تا وقتی ادمین از تب
        // «نوبت‌ها»ی پیشخوان این نوبت را تایید نکرده، به‌جای جدول رزرو،
        // وضعیت فعلی + فرم آپلود رسید نشان داده می‌شود — چون تا این نوبت
        // مشخص نشده، مراجع نباید بتواند یک نوبت دیگر هم‌زمان رزرو کند.
        $pending_manual = $user_id > 0 ? $this->booking->get_pending_manual_payment_booking($user_id) : null;

        ob_start();
        ?>
        <?php if ($pending_payment) : ?>
            <div class="zdp-card zdp-pending-payment-box">
                <h3><span class="zdp-icon-inline"><?php echo $this->icon('check'); ?></span> نوبت شما با موفقیت ثبت شد</h3>
                <p>جزئیات پرداخت و وضعیت تایید را در کادر زیر ببینید.</p>
            </div>
        <?php endif; ?>
        <div class="zdp-card zdp-card-form">
            <?php if ($pending_manual) : ?>
                <h3><span class="zdp-icon-inline"><?php echo $this->icon('clock'); ?></span> در انتظار تایید واریز</h3>
                <p>
                    <?php echo esc_html($pending_manual->service_name); ?> —
                    مبلغ قابل‌پرداخت: <strong><?php echo esc_html(number_format((float) $pending_manual->price)); ?> تومان</strong>
                </p>
                <p>مبلغ بالا را به یکی از کارت‌های زیر واریز کرده و رسید را از طریق فرم زیر آپلود کنید. بعد از تایید ادمین، نوبت شما قطعی می‌شود و می‌توانید دوباره از این تب نوبت جدید رزرو کنید.</p>
                <?php $cards = get_option('zdp_manual_payment_cards', []); ?>
                <?php if (!empty($cards)) : ?>
                    <ul class="zdp-card-list">
                        <?php foreach ((array) $cards as $card) : ?>
                            <li>
                                <strong dir="ltr"><?php echo esc_html($card['card_number'] ?? ''); ?></strong>
                                <span><?php echo esc_html(($card['bank_name'] ?? '') . ' — ' . ($card['holder_name'] ?? '')); ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>

                <?php if (empty($pending_manual->receipt_path)) : ?>
                    <form id="zdp-receipt-upload-form" class="zdp-receipt-upload-form" enctype="multipart/form-data">
                        <input type="hidden" name="booking_id" value="<?php echo (int) $pending_manual->id; ?>">
                        <label for="zdp-receipt-file">تصویر رسید واریز (jpg یا png)</label>
                        <input type="file" id="zdp-receipt-file" name="receipt" accept="image/png,image/jpeg" required>
                        <button type="submit" id="zdp-receipt-submit-btn" class="zdp-btn-primary zdp-receipt-submit">
                            <span class="zdp-btn-label">آپلود رسید</span>
                            <span class="zdp-btn-spinner" aria-hidden="true"></span>
                        </button>
                        <p id="zdp-receipt-result" role="status"></p>
                    </form>
                <?php else : ?>
                    <div class="zdp-receipt-uploaded-box">
                        <p><span class="zdp-icon-inline"><?php echo $this->icon('check'); ?></span> رسید شما آپلود شد و در انتظار بررسی ادمین است.</p>
                        <p>
                            <a href="<?php echo esc_url(Booking::receipt_url($pending_manual)); ?>" target="_blank" rel="noopener noreferrer">مشاهده‌ی رسید ارسالی</a>
                        </p>
                        <details>
                            <summary style="cursor:pointer;">آپلود رسید جدید (جایگزین رسید قبلی)</summary>
                            <form id="zdp-receipt-upload-form" class="zdp-receipt-upload-form" enctype="multipart/form-data" style="margin-top:8px;">
                                <input type="hidden" name="booking_id" value="<?php echo (int) $pending_manual->id; ?>">
                                <input type="file" id="zdp-receipt-file" name="receipt" accept="image/png,image/jpeg" required>
                                <button type="submit" id="zdp-receipt-submit-btn" class="zdp-btn-primary zdp-receipt-submit">
                                    <span class="zdp-btn-label">آپلود رسید جدید</span>
                                    <span class="zdp-btn-spinner" aria-hidden="true"></span>
                                </button>
                                <p id="zdp-receipt-result" role="status"></p>
                            </form>
                        </details>
                    </div>
                <?php endif; ?>
            <?php elseif (empty($services)) : ?>
                <h3>رزرو نوبت جدید</h3>
                <p>در حال حاضر خدمتی برای رزرو تعریف نشده — لطفاً با کلینیک تماس بگیرید.</p>
            <?php else : ?>
                <h3>رزرو نوبت جدید</h3>
                <?php
                // همان جدول گرید روز×ساعت شورت‌کد عمومی [zdp_booking_widget]،
                // نه فرم قدیمی input[type="time"] آزاد — نگاه کن به توضیح
                // Booking::render_grid_widget_markup برای اینکه چرا این تغییر
                // لازم بود. data-logged-in همیشه true است چون این markup فقط
                // داخل خودِ پنل (پس از احراز هویت) رندر می‌شود؛ مرحله‌ی
                // موبایل/OTP اینجا بی‌معنی است و توسط همان پرچم حذف می‌شود.
                //
                // این جدول عمداً وقتی $pending_manual پر است رندر نمی‌شود:
                // تا وقتی نوبتِ در-انتظار-تایید-واریز این مراجع تعیین‌تکلیف
                // نشده (نگاه کن به Booking::get_pending_manual_payment_booking)،
                // نباید بتواند هم‌زمان یک نوبت دیگر هم رزرو کند.
                echo $this->booking->render_grid_widget_markup(true, '', $full_name);
                ?>
            <?php endif; ?>
        </div>
        <h3 class="zdp-section-title">نوبت‌های من</h3>
        <?php if (empty($bookings)) : ?>
            <div class="zdp-empty-state">
                <span class="zdp-empty-state-icon" aria-hidden="true"><?php echo $this->icon('appointments'); ?></span>
                <p>هنوز نوبتی ثبت نکرده‌اید. از فرم بالا یک نوبت جدید رزرو کنید.</p>
            </div>
        <?php else : ?>
            <ul class="zdp-booking-list">
                <?php foreach ($bookings as $b) : ?>
                    <li>
                        <div class="zdp-list-item-icon" aria-hidden="true"><?php echo $this->icon('appointments'); ?></div>
                        <div class="zdp-list-item-body">
                            <strong><?php echo esc_html($b->service_name); ?></strong>
                            <span class="zdp-list-item-meta">
                                <?php echo esc_html(Jalali::gregorian_to_jalali_string($b->booking_date)); ?>
                                — ساعت <?php echo esc_html($b->booking_time); ?>
                            </span>
                        </div>
                        <span class="zdp-status zdp-status-<?php echo esc_attr(zdp_status_slug($b->status)); ?>"><?php echo esc_html($b->status); ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
        <?php
        return (string) ob_get_clean();
    }

    private function render_quizzes_tab(array $quizzes): string
    {
        if (empty($quizzes)) {
            return '<div class="zdp-empty-state"><span class="zdp-empty-state-icon" aria-hidden="true">' . $this->icon('quizzes') . '</span><p>در حال حاضر آزمونی برای شما تعریف نشده است.</p></div>';
        }

        ob_start();
        ?>
        <h3 class="zdp-section-title">آزمون‌های در انتظار شما</h3>
        <ul class="zdp-quiz-list">
            <?php foreach ($quizzes as $q) : ?>
                <?php
                // شکل واقعی داده‌ای که psy-quiz-enterprise از طریق فیلتر
                // psy_get_pending_quizzes_for_phone برمی‌گرداند: آرایه‌ی انجمنی
                // با کلیدهای quiz_name/link (نه یک object با title/url). قبلاً
                // این تب اشتباهاً دنبال $q->title و $q->url می‌گشت که همیشه
                // خالی بود و لیست آزمون‌ها هرگز درست نمایش داده نمی‌شد.
                $name = is_array($q) ? ($q['quiz_name'] ?? 'آزمون') : ($q->quiz_name ?? $q->title ?? 'آزمون');
                $url  = is_array($q) ? ($q['link'] ?? '') : ($q->link ?? $q->url ?? '');
                ?>
                <li>
                    <div class="zdp-list-item-icon" aria-hidden="true"><?php echo $this->icon('quizzes'); ?></div>
                    <div class="zdp-list-item-body">
                        <strong><?php echo esc_html($name); ?></strong>
                    </div>
                    <?php if (!empty($url)) : ?>
                        <a href="<?php echo esc_url($url); ?>" class="zdp-list-item-cta">شروع آزمون</a>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
        <?php
        return (string) ob_get_clean();
    }

    /**
     * تب نظرسنجی داخل پنل، از همان مارک‌آپ/منطق فرم شورت‌کد مستقل
     * [zdp_survey_page] استفاده می‌کند (Survey::render_form_only) تا دو
     * پیاده‌سازی جدا برای یک فرم وجود نداشته باشد. اتصال AJAX (زدن دکمه‌ی
     * ارسال) هم از قبل در panel.js آماده بود (initSurveyForm) و فقط منتظر
     * وجود #zdp-survey-form در صفحه بود.
     *
     * قبلاً اینجا از Survey::render_shortcode() استفاده می‌شد که علاوه بر
     * فرم، هدر خوش‌آمد و یک فوتر کامل (شماره پشتیبانی + لینک بازگشت) هم
     * تولید می‌کرد — همان‌هایی که پنل خودش از قبل (در هدر/فوتر بیرونی‌اش)
     * نمایش می‌دهد؛ نتیجه این بود که شماره پشتیبانی و لینک سایت در صفحه‌ی
     * نظرسنجی داخل پنل دوبار دیده می‌شد. با render_form_only() فقط بدنه‌ی
     * فرم گرفته می‌شود و هیچ‌کدام تکرار نمی‌شود.
     */
    private function render_survey_tab(): string
    {
        ob_start();
        ?>
        <div class="zdp-card zdp-card-form">
            <h3>نظرسنجی از جلسه مشاوره</h3>
            <?php echo $this->survey->render_form_only(); ?>
        </div>
        <?php
        return (string) ob_get_clean();
    }

    private function render_messages_tab(array $msgs): string
    {
        ob_start();
        ?>
        <div class="zdp-card zdp-card-form">
            <div id="zdp-message-form">
                <h3>پیام جدید</h3>
                <textarea id="zdp-message-text" placeholder="پیام خود را بنویسید..." rows="3"></textarea>
                <button type="button" id="zdp-send-message-btn" class="zdp-btn-primary">
                    <span class="zdp-btn-label">ارسال پیام</span>
                    <span class="zdp-btn-spinner" aria-hidden="true"></span>
                </button>
                <p id="zdp-message-result" role="status" aria-live="polite"></p>
            </div>
        </div>
        <?php if (!empty($msgs)) : ?>
            <h3 class="zdp-section-title">پیام‌های شما</h3>
            <ul class="zdp-message-list">
                <?php foreach ($msgs as $m) : ?>
                    <li>
                        <p><?php echo esc_html($m->message_text); ?></p>
                        <?php if (!empty($m->admin_reply)) : ?>
                            <p class="zdp-admin-reply"><span class="zdp-admin-reply-label">پاسخ کلینیک</span><?php echo esc_html($m->admin_reply); ?></p>
                        <?php else : ?>
                            <p class="zdp-message-pending">در انتظار پاسخ...</p>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php else : ?>
            <div class="zdp-empty-state">
                <span class="zdp-empty-state-icon" aria-hidden="true"><?php echo $this->icon('messages'); ?></span>
                <p>هنوز پیامی رد و بدل نشده. اولین پیام را از فرم بالا برای درمانگر خود بفرستید.</p>
            </div>
        <?php endif; ?>
        <?php
        return (string) ob_get_clean();
    }
}
