<?php

declare(strict_types=1);

namespace ZoojDarman\ClientPortal;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * تنها مسیر رسمی «ورود» در کل اکوسیستم پیوند امن.
 *
 * طبق اصل «ورود فقط از یک مسیر»: مسئولیت این کلاس دقیقاً و فقط همین ۴ قدم
 * است — صدور/مصرف توکن یک‌بارمصرف → wp_set_current_user() →
 * wp_set_auth_cookie() → do_action('wp_login') → ریدایرکت تمیز. هیچ HTML،
 * هیچ منطق پنل/داشبورد، و هیچ داده‌ی مربوط به پرونده/نوبت/پیام اینجا نیست
 * و نباید اضافه شود؛ اگر روش ورود دومی (لینک جادویی، رمز عبور، و...) در
 * آینده اضافه شد، باید همان‌طور که OTP_Auth اکنون فقط از طریق
 * Login_Service::build_redirect_url وارد این کلاس می‌شود، از همین کلاس
 * عبور کند — نه اینکه خودش مستقیماً wp_set_auth_cookie صدا بزند.
 *
 * (این کلاس قبلاً Login_Bridge نام داشت؛ در نسخه‌ی ۱.۶.۲ صرفاً برای شفافیتِ
 * نقش، بدون هیچ تغییری در منطق داخلی، به Login_Service تغییر نام یافت.)
 *
 * چرا این کلاس لازم شد (ریشه‌ی باگ «موفق می‌گوید ولی وارد پنل نمی‌شوم»):
 * نسخه‌ی قبلی wp_set_auth_cookie() را مستقیماً داخل پاسخ REST/AJAX درخواست
 * verify-otp صدا می‌زد. یعنی هدر Set-Cookie باید از دلِ یک پاسخ XHR/fetch به
 * مرورگر می‌رسید، نه یک ناوبری واقعی صفحه. در عمل چند چیز می‌تواند این
 * Set-Cookie را در راه گم کند، بدون اینکه هیچ خطایی به کاربر یا به لاگ سرور
 * برسد:
 *   - یک لایه‌ی کش/پراکسی جلوی سایت (CDN، WAF، یا حتی کش سطح خودِ وب‌سرور)
 *     که پاسخ درخواست‌های POST به wp-json/admin-ajax را کش یا پراکسی می‌کند
 *     و هدر Set-Cookie را در این مسیر حذف می‌کند (رفتاری که در پاسخ‌های
 *     ناوبری معمولی رخ نمی‌دهد).
 *   - عدم تطابق دامنه‌ی واقعی صفحه با دامنه‌ای که COOKIE_DOMAIN/siteurl روی آن
 *     تنظیم شده (www در برابر بدون www، یا http در برابر https).
 *   - سیاست‌های محدودکننده‌ی مرورگر/افزونه‌های امنیتی که کوکی‌های صادرشده از
 *     دل یک XHR را متفاوت از کوکی صادرشده در یک ناوبری واقعی برخورد می‌کنند.
 * دیباگ‌کردن این حالت هم گمراه‌کننده بود چون چک‌کردن is_user_logged_in()
 * بلافاصله بعد از wp_set_auth_cookie() در همان request فقط global سمت PHP را
 * می‌بیند، نه اینکه کوکی واقعاً به مرورگر رسیده یا نه.
 *
 * راه‌حل ساختاری (نه پچ موقت): verify-otp دیگر خودش کوکی لاگین را ست نمی‌کند.
 * فقط یک توکن یک‌بارمصرف کوتاه‌عمر صادر می‌کند و کلاینت (که از قبل، حتی در
 * نسخه‌ی قبلی، بعد از موفقیت یک window.location.href کامل انجام می‌داد) با
 * همان توکن ناوبری می‌کند.
 *
 * -- تاریخچه‌ی مهم (چرا این‌جا فقط یک هوک init ساده روی /panel/ می‌بینید،
 * نه عبور از wp-admin/admin-post.php) --
 * در یک نسخه‌ی میانی (۱.۶.۴) به‌خاطر ریسک کش صفحه روی مسیر تمیز /panel/
 * (افزونه‌های کش/CDN معمولاً querystring را در کلید کش نادیده می‌گیرند)،
 * مقصد ناوبری موقتاً به wp-admin/admin-post.php تغییر داده شده بود — مسیری
 * که اکثر افزونه‌های کش به‌طور پیش‌فرض از قوانین کش صفحه مستثنا می‌کنند.
 * این تغییر خودش یک باگ جدی‌تر ساخت: روی سایت‌هایی که یک لایه‌ی سخت‌سازی/
 * فایروال دسترسیِ کاربر مهمان به هر مسیر داخل /wp-admin/ را مسدود می‌کند
 * (تنظیم بسیار رایج امنیتی)، دقیقاً همان لحظه که این توکن مصرف می‌شود
 * کاربر هنوز از نظر سرور مهمان است — یعنی درخواست مسدود می‌شد، کوکی هرگز
 * ست نمی‌شد و ورود همیشه شکست می‌خورد (نگاه کن به CHANGELOG نسخه‌ی ۱.۶.۴.۱).
 * برای همین در ۱.۶.۴.۱/۱.۶.۴.۳ این پرش عمداً برداشته و به همین حالت فعلی
 * (مستقیم روی مقصد نهایی، بدون عبور از wp-admin) بازگردانده شد.
 *
 * پیامد پذیرفته‌شده: ریسک اصلیِ اولیه (کش‌شدنِ احتمالی /panel/ توسط یک
 * افزونه‌ی کش صفحه یا CDN) دیگر با هیچ کوئری‌آرگ ضدکشی خنثی نمی‌شود — این
 * پلاگین به‌طور پیش‌فرض فرض می‌کند مسیر /panel/ از قوانین کش صفحه مستثنا
 * شده (چون محتوایش همیشه به‌ازای کاربر متفاوت است). اگر روی سایتی واقعاً
 * علائم بازگشتِ همان باگ («تایید موفق ولی وارد پنل نمی‌شوم») دیده شد و
 * دلیلش کش صفحه بود (نه فایروال wp-admin)، اول باید /panel/ به‌طور صریح در
 * تنظیمات افزونه‌ی کش/CDN از cache-bypass استثنا شود؛ اگر آن کافی نبود،
 * می‌شود یک کوئری‌آرگ یک‌بارمصرف تصادفی روی فرود نهایی در
 * maybe_complete_login() اضافه کرد (الگویی که قبلاً این‌جا بود و در
 * ۱.۶.۴.۳ حذف شد) — به‌شرط اینکه سمت کلاینت هم برای پاک‌کردنش از نوار آدرس
 * (history.replaceState) اضافه شود.
 */
final class Login_Service
{
    private const TOKEN_TTL_SECONDS = 60;
    private const TRANSIENT_PREFIX  = 'zdp_login_bridge_';
    private const QUERY_ARG         = 'zdp_auth';
    private const ERROR_QUERY_ARG   = 'zdp_auth_error';
    private const ADMIN_POST_ACTION = 'zdp_complete_login';
    // نکته: این کوئری‌آرگ آخرین لایه‌ی دفاعی در برابر کشِ استاتیک (فایلی یا
    // CDN) روی مسیر تمیز /panel/ است — نگاه کن به توضیح بالای کلاس. مقدارش
    // در build_landing_url هر بار تصادفی تولید می‌شود، پس خودش هرگز هدف کش
    // نمی‌شود.
    private const LANDING_QUERY_ARG = 'zdp_ok';

    public function __construct()
    {
        // ------------------------------------------------------------------
        // پچ محلی (بعد از گزارش کاربر مبنی بر «مشکل ورود»): مسیر
        // admin-post.php روی این سایت مشکل‌ساز بود — به احتمال زیاد چون یک
        // لایه‌ی امنیتی/فایروال یا قانون سخت‌سازی wp-admin (که روی خیلی از
        // سایت‌های وردپرسی هست) دسترسی به هر فایل داخل /wp-admin/ را برای
        // کاربر مهمان (لاگین‌نشده) مسدود می‌کند — و درست همان لحظه‌ای که این
        // توکن مصرف می‌شود، کاربر از نظر سرور هنوز مهمان است. بنابراین
        // هوک‌های admin_post_* حذف شدند و دوباره فقط 'init' روی خودِ صفحه‌ی
        // مقصد (یک صفحه‌ی عادی سایت، نه wp-admin) استفاده می‌شود — همان چیزی
        // که در نسخه‌ی قبلی افزونه بدون مشکل کار می‌کرد. مزایای این نسخه
        // (پیام خطای واضح‌تر، توکن یک‌بارمصرف، فرود ضدکش با
        // LANDING_QUERY_ARG) همه حفظ شده‌اند؛ فقط پرش میانی از دل wp-admin
        // حذف شده.
        // ------------------------------------------------------------------
        add_action('init', [$this, 'maybe_complete_login'], 1);
    }

    /**
     * صدا زده می‌شود از OTP_Auth بلافاصله بعد از تایید صحیح کد پیامکی.
     * کوکی لاگین اینجا ست نمی‌شود؛ فقط یک توکن یک‌بارمصرف صادر و در transient
     * ذخیره می‌شود که مقصد نهایی (URL پنل) را هم با خودش حمل می‌کند.
     */
    public static function issue_token(int $user_id, string $redirect_to): string
    {
        $token = bin2hex(random_bytes(32));

        set_transient(self::TRANSIENT_PREFIX . $token, [
            'user_id'      => $user_id,
            'redirect_to'  => $redirect_to,
            'issued_at'    => now_utc(),
            'issued_ip'    => get_client_ip(),
        ], self::TOKEN_TTL_SECONDS);

        return $token;
    }

    /**
     * ساخت URL نهایی‌ای که کلاینت باید به آن ناوبری کند (شامل توکن).
     *
     * پچ محلی: دوباره مستقیم $redirect_to (یعنی /panel/) است — نه
     * admin-post.php — تا وارد /wp-admin/ نشویم (نگاه کن به توضیح بالای
     * سازنده‌ی کلاس).
     */
    public static function build_redirect_url(int $user_id, string $redirect_to): string
    {
        $token = self::issue_token($user_id, $redirect_to);

        return add_query_arg(self::QUERY_ARG, $token, $redirect_to);
    }

    /**
     * روی هر بارگذاری صفحه (init) بررسی می‌کند آیا این یک ناوبریِ حامل توکن
     * ورود است. اگر باشد، توکن را (چه معتبر چه نامعتبر) بلافاصله مصرف/حذف
     * می‌کند تا رفرش یا اشتراک‌گذاری تصادفیِ همان URL دوباره قابل استفاده
     * نباشد، سپس کوکی واقعی را همین‌جا ست کرده و به یک URL تمیز (بدون توکن در
     * querystring) ریدایرکت می‌کند.
     */
    public function maybe_complete_login(): void
    {
        if (empty($_GET[self::QUERY_ARG]) || !is_string($_GET[self::QUERY_ARG])) {
            return;
        }

        $token = sanitize_text_field(wp_unslash($_GET[self::QUERY_ARG]));

        // فرمت توکن همیشه ۶۴ کاراکتر هگزادسیمال است (random_bytes(32)); هر
        // چیز دیگری قطعاً جعلی/خراب است و حتی نیازی به کوئری transient ندارد.
        if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
            $this->redirect_with_error(zdp_panel_url());

            return;
        }

        $payload = get_transient(self::TRANSIENT_PREFIX . $token);

        // یک‌بارمصرف بودن واقعی: حذف بلافاصله، مستقل از معتبر بودن محتوا —
        // این‌طوری حتی اگر دو تب هم‌زمان همین لینک را باز کنند، فقط اولی
        // موفق می‌شود.
        delete_transient(self::TRANSIENT_PREFIX . $token);

        if (!is_array($payload) || empty($payload['user_id'])) {
            audit_log('otp_login_bridge_token_invalid_or_expired', ['token_prefix' => substr($token, 0, 8)]);
            $this->redirect_with_error(zdp_panel_url());

            return;
        }

        $user_id     = (int) $payload['user_id'];
        $redirect_to = is_string($payload['redirect_to'] ?? null) && $payload['redirect_to'] !== ''
            ? $payload['redirect_to']
            : zdp_panel_url();

        $user = get_user_by('id', $user_id);
        if (!$user) {
            audit_log('otp_login_bridge_user_missing', ['user_id' => $user_id]);
            $this->redirect_with_error(zdp_panel_url());

            return;
        }

        Cache_Guard::no_store();

        // این‌جا، برخلاف نسخه‌ی قبلی، این خودِ درخواستِ ناوبریِ اصلیِ صفحه است
        // (نه یک XHR/fetch) — پس هدر Set-Cookie که wp_set_auth_cookie تولید
        // می‌کند از همان مسیری به مرورگر می‌رسد که وردپرس/CDN/افزونه‌های کش
        // برایش طراحی شده‌اند.
        wp_set_current_user($user_id);
        wp_set_auth_cookie($user_id, true);
        do_action('wp_login', $user->user_login, $user);

        audit_log('otp_login_cookie_set', ['user_id' => $user_id]);

        // پچ محلی: فرود نهایی دوباره کاملاً تمیز است (بدون هیچ کوئری‌آرگ
        // اضافه‌ای) — دقیقاً مثل نسخه‌ی ۱.۵.۶. کوئری‌آرگ ضدکش zdp_ok حذف شد
        // چون بعد از رفع پرش wp-admin هنوز مشکل ورود گزارش شد؛ برای حذف کامل
        // هر تفاوت رفتاریِ باقی‌مانده نسبت به نسخه‌ی شناخته‌شده‌ی سالم، این
        // لایه هم برداشته شد. اگر بعداً معلوم شد سایت واقعاً از کش صفحه روی
        // مسیر /panel/ رنج می‌برد، می‌شود این ترفند را در build_landing_url
        // دوباره برگرداند.
        wp_safe_redirect(remove_query_arg([self::QUERY_ARG, self::ERROR_QUERY_ARG, self::LANDING_QUERY_ARG], $redirect_to));
        exit;
    }

    private function redirect_with_error(string $panel_url): void
    {
        Cache_Guard::no_store();
        wp_safe_redirect(add_query_arg(self::ERROR_QUERY_ARG, '1', remove_query_arg(self::QUERY_ARG, $panel_url)));
        exit;
    }
}
