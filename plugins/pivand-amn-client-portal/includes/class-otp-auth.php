<?php

declare(strict_types=1);

namespace ZoojDarman\ClientPortal;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * احراز هویت پیامکی دو مرحله‌ای.
 *
 * رفع باگ‌های شناخته‌شده‌ی نسخه‌ی قبلی:
 * 1) هر دو handler اکنون nonce را با Security::verify_ajax_nonce بررسی می‌کنند
 *    (نسخه‌ی قبلی اصلاً nonce نداشت — یعنی هر سایت دیگری می‌توانست با
 *    درخواست cross-site درخواست OTP جعل کند).
 * 2) کد OTP فقط *پس از* تایید موفق ارسال پیامک در transient ذخیره می‌شود، نه
 *    قبل از آن؛ اگر ارسال پیامک شکست بخورد کد یتیم در سیستم نمی‌ماند.
 * 3) Rate limiting یکپارچه از طریق Security::allow انجام می‌شود (هم بر اساس
 *    IP واقعی از پشت CDN، هم بر اساس شماره موبایل).
 * 4) نقش کاربر جدید صراحتاً subscriber قفل می‌شود، مستقل از تنظیم عمومی
 *    «New User Default Role».
 */
final class OTP_Auth
{
    private Security $security;

    public function __construct(Security $security)
    {
        $this->security = $security;

        // مسیر REST استاندارد (اصلی). فراخوانی‌های جدید panel.js فقط از این
        // مسیر استفاده می‌کنند.
        add_action('rest_api_init', [$this, 'register_rest_routes']);

        // Fallback موقت admin-ajax.php: handlerهای قدیمی دست‌نخورده باقی
        // می‌مانند (استفاده نمی‌شوند ولی حذف هم نشده‌اند) تا اگر کش/اسکریپت
        // قدیمی جایی باقی مانده باشد، چیزی نشکند.
        add_action('wp_ajax_zdp_send_otp', [$this, 'handle_send_otp']);
        add_action('wp_ajax_nopriv_zdp_send_otp', [$this, 'handle_send_otp']);

        add_action('wp_ajax_zdp_verify_otp', [$this, 'handle_verify_otp']);
        add_action('wp_ajax_nopriv_zdp_verify_otp', [$this, 'handle_verify_otp']);

        // رفع کمبود قابلیت تشخیص: تا اینجا وقتی wp_mail() شکست می‌خورد،
        // audit_log فقط می‌گفت «ارسال ایمیل شکست خورد» بدون اینکه دلیل واقعی
        // (مثلاً «اتصال SMTP برقرار نشد»، «احراز هویت رد شد»، «هاست پورت
        // خروجی را بسته») جایی ثبت شود — یعنی تنها راه فهمیدن علتِ واقعی،
        // دسترسی مستقیم به لاگ سرور بود. wp_mail_failed دقیقاً همان خطای
        // PHPMailer/wp_mail را به‌صورت WP_Error در اختیار می‌گذارد؛ همان را
        // در audit_log خودمان هم ذخیره می‌کنیم تا از پیشخوان قابل‌مشاهده باشد.
        add_action('wp_mail_failed', [$this, 'log_mail_failure']);
    }

    public function log_mail_failure(\WP_Error $error): void
    {
        audit_log('wp_mail_failed', [
            'error_code'    => $error->get_error_code(),
            'error_message' => $error->get_error_message(),
        ]);
    }

    /**
     * ثبت route های REST تحت namespace اختصاصی zdp/v1. رفتار و منطق این
     * endpoint ها دقیقاً همان process_send_otp/process_verify_otp است که
     * توسط handlerهای AJAX قدیمی هم صدا زده می‌شود — هیچ منطقی تکرار یا
     * تغییر نکرده، فقط لایه‌ی transport و مکانیزم تایید nonce عوض شده.
     */
    public function register_rest_routes(): void
    {
        register_rest_route('zdp/v1', '/send-otp', [
            'methods'             => 'POST',
            'callback'            => [$this, 'rest_send_otp'],
            'permission_callback' => [$this, 'rest_permission_check'],
            'args'                => [
                'mobile' => [
                    'required'          => true,
                    'type'              => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
            ],
        ]);

        register_rest_route('zdp/v1', '/verify-otp', [
            'methods'             => 'POST',
            'callback'            => [$this, 'rest_verify_otp'],
            'permission_callback' => [$this, 'rest_permission_check'],
            'args'                => [
                'mobile' => [
                    'required'          => true,
                    'type'              => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'otp' => [
                    'required'          => true,
                    'type'              => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
            ],
        ]);

        // مسیر جایگزینِ ورود با ایمیل — برای مراجعینی که شماره‌ی ایرانی برای
        // دریافت پیامک ندارند (مثلاً مراجعین خارج از کشور یا مراجعینی که
        // می‌خواهند فقط برای آزمون وارد شوند).
        //
        // تغییر رفتار (طبق درخواست کارفرما): قبلاً ورود با ایمیل فقط برای
        // ایمیلی کار می‌کرد که از قبل، توسط درمانگر هنگام تعریف یک آزمون، به
        // یک شماره موبایل مشخص وصل شده بود (از طریق فیلتر
        // psy_get_mobile_for_email) — یعنی مراجع بدون ثبت قبلی اصلاً نمی‌توانست
        // با ایمیل وارد پنل شود. حالا اگر چنین اتصالی از قبل وجود داشته باشد
        // همچنان همان مسیر (اتصال به همان شماره/نوبت‌ها/تاریخچه) دنبال
        // می‌شود؛ ولی اگر نبود، به‌جای رد کردن ورود، یک حساب کاربری مستقل و
        // جدید فقط بر مبنای همین ایمیل ساخته می‌شود (دقیقاً همان الگوی
        // خودسرویسِ مسیر موبایل) — نگاه کن به process_verify_otp_email و
        // find_or_create_user_by_email.
        register_rest_route('zdp/v1', '/send-otp-email', [
            'methods'             => 'POST',
            'callback'            => [$this, 'rest_send_otp_email'],
            'permission_callback' => [$this, 'rest_permission_check'],
            'args'                => [
                'email' => [
                    'required'          => true,
                    'type'              => 'string',
                    'sanitize_callback' => 'sanitize_email',
                ],
            ],
        ]);

        register_rest_route('zdp/v1', '/verify-otp-email', [
            'methods'             => 'POST',
            'callback'            => [$this, 'rest_verify_otp_email'],
            'permission_callback' => [$this, 'rest_permission_check'],
            'args'                => [
                'email' => [
                    'required'          => true,
                    'type'              => 'string',
                    'sanitize_callback' => 'sanitize_email',
                ],
                'otp' => [
                    'required'          => true,
                    'type'              => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
            ],
        ]);
    }

    /**
     * جایگزین استاندارد Security::verify_ajax_nonce برای REST: به‌جای
     * پارامتر دستی 'nonce'، هدر توکار وردپرسی X-WP-Nonce بررسی می‌شود.
     * سطح امنیتی دقیقاً همان است (همان action 'wp_rest' که خودِ core وردپرس
     * برای nonce های REST استفاده می‌کند)، فقط استانداردتر و بدون نیاز به
     * پاس‌دادن دستی nonce در بدنه‌ی درخواست.
     */
    public function rest_permission_check(\WP_REST_Request $request): bool|\WP_Error
    {
        $nonce = $request->get_header('X-WP-Nonce');

        if (!$nonce || !wp_verify_nonce($nonce, 'wp_rest')) {
            return new \WP_Error(
                'zdp_rest_forbidden',
                'نشست شما منقضی شده. لطفاً صفحه را رفرش کنید.',
                ['status' => 403]
            );
        }

        return true;
    }

    /**
     * جلوگیری از کش‌شدنِ پاسخ‌های این endpoint توسط افزونه‌های کش صفحه
     * (WP Rocket, LiteSpeed Cache, W3 Total Cache و...). این هوک‌ها معمولاً
     * درخواست‌های admin-ajax.php/REST را کش نمی‌کنند، اما بعضی حالت‌ها
     * (نسخه‌ی از پیش کش‌شده‌ی صفحه‌ی /panel/ که پیش از این پچ ساخته شده، یا
     * افزونه‌هایی که POST را هم کش می‌کنند) می‌توانند باعث شوند که پاسخ
     * موفقِ verify-otp کش شود در حالی که کوکی ورود واقعی هرگز به مرورگر
     * نمی‌رسد — دقیقاً همان باگ «می‌گوید موفق ولی وارد پنل نمی‌شوم».
     */
    /**
     * لاگ موقتِ دیباگ برای پیدا کردن دقیق نقطه‌ی خرابیِ ورود، بعد از پیدا
     * شدن و رفع ریشه‌ی مشکل (Login_Service) حذف شد. اگر لازم شد دوباره
     * دیباگ شود، audit_log() موجود که در همین کلاس همه‌ی رخدادهای موفق/
     * ناموفق را ثبت می‌کند معمولاً کافی است.
     */

    public function rest_send_otp(\WP_REST_Request $request): \WP_REST_Response
    {
        Cache_Guard::no_store();

        $result = $this->process_send_otp((string) $request->get_param('mobile'));

        return new \WP_REST_Response(
            ['success' => $result['success'], 'data' => $result['data']],
            $result['success'] ? 200 : 400
        );
    }

    public function rest_verify_otp(\WP_REST_Request $request): \WP_REST_Response
    {
        Cache_Guard::no_store();

        $result = $this->process_verify_otp(
            (string) $request->get_param('mobile'),
            (string) $request->get_param('otp'),
            $this->extract_pending_booking_from_rest($request)
        );

        return new \WP_REST_Response(
            ['success' => $result['success'], 'data' => $result['data']],
            $result['success'] ? 200 : 400
        );
    }

    public function rest_send_otp_email(\WP_REST_Request $request): \WP_REST_Response
    {
        Cache_Guard::no_store();

        $result = $this->process_send_otp_email((string) $request->get_param('email'));

        return new \WP_REST_Response(
            ['success' => $result['success'], 'data' => $result['data']],
            $result['success'] ? 200 : 400
        );
    }

    public function rest_verify_otp_email(\WP_REST_Request $request): \WP_REST_Response
    {
        Cache_Guard::no_store();

        $result = $this->process_verify_otp_email(
            (string) $request->get_param('email'),
            (string) $request->get_param('otp'),
            $this->extract_pending_booking_from_rest($request)
        );

        return new \WP_REST_Response(
            ['success' => $result['success'], 'data' => $result['data']],
            $result['success'] ? 200 : 400
        );
    }

    /**
     * بازه‌ی رزروِ در-انتظار که شورت‌کد عمومی [zdp_booking_widget] همراه با
     * mobile/otp در همان بدنه‌ی JSON درخواست verify-otp می‌فرستد — پارامترهایی
     * که در register_rest_routes صراحتاً تعریف نشده‌اند، ولی WP REST همچنان
     * از بدنه‌ی JSON قابل‌خواندن‌اند (فقط بدون sanitize خودکارِ schema، پس
     * اینجا صریحاً sanitize می‌شوند).
     *
     * باگ رفع‌شده: قبلاً اگر یکی از سه فیلد (نه هرسه) خالی بود، این متد کل
     * آرایه را [] برمی‌گرداند — یعنی «هیچ رزروی درخواست نشده» با «رزرو
     * درخواست شده ولی یک فیلدش گم است» از دیدِ Booking::create_booking_from_otp_flow
     * کاملاً یکسان به نظر می‌رسیدند و حالت دوم کاملاً بی‌صدا نادیده گرفته
     * می‌شد (نه نوبتی ساخته می‌شد، نه ردی در لاگ می‌ماند). حالا همیشه هرچه
     * واقعاً ارسال شده (even اگر ناقص) برگردانده می‌شود؛ تشخیصِ «ناقص است»
     * و لاگ‌کردنش به‌عهده‌ی create_booking_from_otp_flow است.
     *
     * @return array<string, string>
     */
    private function extract_pending_booking_from_rest(\WP_REST_Request $request): array
    {
        return [
            'service_id'   => sanitize_text_field((string) $request->get_param('service_id')),
            'booking_date' => sanitize_text_field((string) $request->get_param('booking_date')),
            'booking_time' => sanitize_text_field((string) $request->get_param('booking_time')),
            'full_name'    => sanitize_text_field((string) $request->get_param('full_name')),
        ];
    }

    /** همان استخراج بالا، برای مسیر fallback قدیمیِ admin-ajax.php (نگاه کن به handle_verify_otp). */
    private function extract_pending_booking_from_post(): array
    {
        return [
            'service_id'   => isset($_POST['service_id']) ? sanitize_text_field(wp_unslash($_POST['service_id'])) : '',
            'booking_date' => isset($_POST['booking_date']) ? sanitize_text_field(wp_unslash($_POST['booking_date'])) : '',
            'booking_time' => isset($_POST['booking_time']) ? sanitize_text_field(wp_unslash($_POST['booking_time'])) : '',
            'full_name'    => isset($_POST['full_name']) ? sanitize_text_field(wp_unslash($_POST['full_name'])) : '',
        ];
    }

    public function handle_send_otp(): void
    {
        Cache_Guard::no_store();
        $this->security->verify_ajax_nonce();

        $mobile = isset($_POST['mobile']) ? sanitize_text_field(wp_unslash($_POST['mobile'])) : '';
        $result = $this->process_send_otp($mobile);

        if ($result['success']) {
            wp_send_json_success($result['data']);
        }

        wp_send_json_error($result['data']);
    }

    public function handle_verify_otp(): void
    {
        Cache_Guard::no_store();
        $this->security->verify_ajax_nonce();

        $mobile   = isset($_POST['mobile']) ? sanitize_text_field(wp_unslash($_POST['mobile'])) : '';
        $user_otp = isset($_POST['otp']) ? sanitize_text_field(wp_unslash($_POST['otp'])) : '';

        $result = $this->process_verify_otp($mobile, $user_otp, $this->extract_pending_booking_from_post());

        if ($result['success']) {
            wp_send_json_success($result['data']);
        }

        wp_send_json_error($result['data']);
    }

    /**
     * منطق اصلی ارسال OTP، مستقل از transport. هم توسط handle_send_otp
     * (AJAX) و هم rest_send_otp (REST) صدا زده می‌شود تا رفتار عیناً یکی
     * بماند. ترتیب early-return ها همان ترتیب wp_send_json_error های قبلی
     * است (که با exit فوری متوقف می‌شدند)، پس رفتار short-circuit حفظ شده.
     *
     * @return array{success: bool, data: array<string, mixed>}
     */
    private function process_send_otp(string $mobile): array
    {
        $mobile = to_local_format($mobile);

        if (!is_valid_mobile($mobile)) {
            return ['success' => false, 'data' => ['message' => 'شماره موبایل وارد شده معتبر نیست.']];
        }

        // شماره‌ی خارجی (E.164 با «+»): پل پیامک فقط شماره‌ی ایرانی 09xxxxxxxxx را
        // می‌شناسد و شماره‌ی خارجی را همیشه رد می‌کند. به‌جای عبور دادن و دریافت
        // خطای گنگ (و مصرف سهمیه‌ی ۶۰ ثانیه‌ای)، همین‌جا زودتر و شفاف رد می‌شود و
        // کاربر به ورود با ایمیل هدایت می‌شود. اگر روزی سرویس پیامک بین‌المللی
        // اضافه شد: add_filter('zdp_sms_supports_international', '__return_true').
        if (str_starts_with($mobile, '+') && !apply_filters('zdp_sms_supports_international', false)) {
            return ['success' => false, 'data' => [
                'message'        => 'ارسال پیامک برای شماره‌ی خارج از کشور پشتیبانی نمی‌شود. لطفاً با ایمیل وارد شوید.',
                'switch_channel' => 'email',
            ]];
        }

        $ip = get_client_ip();
        if (!$this->security->allow('otp_ip_' . md5($ip), 3, 60)) {
            return ['success' => false, 'data' => ['message' => 'تعداد درخواست‌های ارسالی از سمت شما موقتاً بیش از حد مجاز است. لطفاً یک دقیقه دیگر تلاش کنید.']];
        }

        if (!$this->security->allow('otp_mobile_' . $mobile, 1, 60)) {
            return ['success' => false, 'data' => ['message' => 'لطفاً برای ارسال مجدد کد کمی صبر کنید (یک بار در هر ۶۰ ثانیه).']];
        }

        if (!has_filter('pas_send_sms')) {
            // مسیر جایگزین ورود: بدون سامانه پیامک، مراجع نباید در بن‌بست
            // بماند؛ مثل شماره‌ی خارجی به ورود با ایمیل هدایت می‌شود (panel.js
            // با دیدن switch_channel تب ایمیل را باز و همان ورودی را فوکوس
            // می‌کند).
            return ['success' => false, 'data' => [
                'message'        => 'سامانه پیامک به این سایت متصل نیست. لطفاً با ایمیل وارد شوید.',
                'switch_channel' => 'email',
            ]];
        }

        try {
            $otp_code = random_int(10000, 99999);
        } catch (\Exception $e) {
            $otp_code = wp_rand(10000, 99999);
        }

        // ابتدا پیامک ارسال می‌شود؛ فقط در صورت موفقیت، کد در transient ذخیره
        // می‌شود. این ترتیب برعکسِ نسخه‌ی قبلی است و از وجود کدهای «یتیم»
        // (ذخیره‌شده ولی هرگز به کاربر نرسیده) جلوگیری می‌کند.
        $sms_sent = $this->send_sms_pattern($mobile, (string) get_option('zdp_sms_body_id', ''), (string) $otp_code);

        if (!$sms_sent) {
            $this->security->reset('otp_mobile_' . $mobile);

            // ارسال شکست خورد (اعتبار درگاه، قطعی موقت و...): همان رفتار
            // «شماره‌ی خارجی» — راهنمایی و سوئیچ به ورود با ایمیل تا مراجع
            // منتظر پیامکی نماند که هرگز نمی‌رسد.
            return ['success' => false, 'data' => [
                'message'        => 'ارسال پیامک ناموفق بود. لطفاً با ایمیل وارد شوید یا کمی بعد دوباره تلاش کنید.',
                'switch_channel' => 'email',
            ]];
        }

        // TTL کد از ۱۲۰ به ۳۰۰ ثانیه افزایش یافت (رفع باگ «کد درست وارد
        // می‌کنم ولی قبول نمی‌شود»): برخی گیت‌وی‌های پیامک داخلی (ملی‌پیامک و
        // مشابه) گاهی ۲ تا ۳ دقیقه طول می‌کشند تا پیامک واقعاً به دست کاربر
        // برسد. با TTL قبلی (۱۲۰ ثانیه)، اگر کاربر یک‌بار «ارسال مجدد» هم
        // زده باشد (که خودش یک تایمر ۱۲۰ ثانیه‌ای در پنل.js دارد)، وقتی
        // پیامک بالاخره می‌رسید، کد از قبل منقضی شده بود — بدون این‌که
        // کاربر متوجه شود چرا (پیام قبلی فرقی بین «منقضی» و «اشتباه»
        // نمی‌گذاشت). حالا هم TTL بیشتر شده هم پیام خطا این دو حالت را
        // تفکیک می‌کند (نگاه کن به process_verify_otp).
        set_transient('zdp_otp_' . $mobile, (string) $otp_code, 300);
        set_transient('zdp_otp_attempts_' . $mobile, 0, 300);

        audit_log('otp_sent', ['mobile' => $mobile]);

        return ['success' => true, 'data' => ['message' => 'کد تایید ارسال شد.']];
    }

    /**
     * منطق اصلی تایید OTP، مستقل از transport (نگاه کن به توضیح
     * process_send_otp بالا).
     *
     * @return array{success: bool, data: array<string, mixed>}
     */
    private function process_verify_otp(string $mobile, string $user_otp, array $pending_booking = []): array
    {
        $mobile = to_local_format($mobile);

        if (!is_valid_mobile($mobile)) {
            return ['success' => false, 'data' => ['message' => 'شماره موبایل وارد شده معتبر نیست.']];
        }

        // ارقام فارسی/عربی (کیبورد پیش‌فرض بسیاری از گوشی‌ها در حالت زبان
        // فارسی) به لاتین تبدیل می‌شوند، وگرنه hash_equals با کد لاتین
        // ذخیره‌شده هیچ‌وقت true نمی‌شود — حتی وقتی کاربر دقیقاً همان چیزی
        // که در پیامک آمده را تایپ کرده. panel.js سمت کلاینت هم همین کار را
        // می‌کند؛ این‌جا لایه‌ی دومِ دفاعی است (برای JS کش‌شده‌ی قدیمی یا
        // فراخوانی مستقیم REST).
        $user_otp = normalize_digits(trim($user_otp));

        $guard_result = $this->guard_verify_attempt($mobile, $user_otp);
        if ($guard_result !== null) {
            return $guard_result;
        }

        delete_transient('zdp_otp_' . $mobile);
        delete_transient('zdp_otp_attempts_' . $mobile);

        return $this->finalize_successful_login($mobile, $pending_booking);
    }

    /**
     * مسیر ورود پیامکی: پیدا/ساخت کاربر بر مبنای موبایل، سپس تحویل به
     * finalize_login_for_user (بخش کاملاً مشترک با مسیر ایمیلی).
     *
     * @return array{success: bool, data: array<string, mixed>}
     */
    private function finalize_successful_login(string $mobile, array $pending_booking): array
    {
        $user_id = $this->find_or_create_user($mobile);

        if (is_wp_error($user_id)) {
            audit_log('otp_login_failed', ['mobile' => $mobile, 'error' => $user_id->get_error_message()]);

            return ['success' => false, 'data' => ['message' => 'خطا در ساخت حساب کاربری جدید.']];
        }

        audit_log('otp_login_success', ['mobile' => $mobile, 'user_id' => $user_id]);

        return $this->finalize_login_for_user($user_id, $mobile, $pending_booking);
    }

    /**
     * بخش مشترکِ پس از تایید موفق کد (چه از مسیر پیامکی چه ایمیلی، با یا
     * بدون موبایلِ شناخته‌شده): هم‌گام‌سازی نام از اکوسیستم، صدور توکن ورود و
     * اتصال به نوبتِ در‌انتظار. جدا شد تا هر سه مسیر (موبایل، ایمیلِ
     * متصل‌به‌موبایل، ایمیلِ مستقل/خودسرویس) دقیقاً یک رفتار داشته باشند —
     * نه چند کپیِ ممکن است واگرا شونده.
     *
     * @param string $mobile شماره موبایلِ شناخته‌شده‌ی این کاربر، یا رشته‌ی
     *                       خالی اگر این ورود صرفاً بر مبنای ایمیل (بدون هیچ
     *                       موبایل شناخته‌شده‌ای) بوده باشد. وقتی خالی است،
     *                       جست‌وجوی نام از طریق فیلترهای مبتنی بر موبایل
     *                       (که بی‌معنی‌اند) رد می‌شود.
     *
     * @return array{success: bool, data: array<string, mixed>}
     */
    private function finalize_login_for_user(int $user_id, string $mobile, array $pending_booking): array
    {
        // نکته (رفع باگ «اسم نمیاد، شماره میاد»): این چک قبلاً فقط داخل شاخه‌ی
        // ساخت کاربر جدید بود، یعنی اگر حساب از قبل توسط افزونه‌ی دیگری
        // (مثلاً هنگام تعریف آزمون توسط درمانگر) با متای cellphone ساخته شده
        // بود، find_or_create_user() از همان مسیر «کاربر موجود» برمی‌گشت و این
        // بخش هرگز اجرا نمی‌شد — حتی اگر اسم مراجع در psy-quiz-enterprise ثبت
        // شده باشد. حالا مستقل از مسیر ساخت/یافتن کاربر، برای هر ورود موفق
        // اجرا می‌شود (فقط وقتی zdp_full_name هنوز خالی است). این جست‌وجو فقط
        // وقتی $mobile شناخته‌شده باشد معنا دارد (فیلترهای زیر هر دو بر مبنای
        // شماره موبایل کار می‌کنند)؛ برای حساب‌های ایمیلِ مستقل (بدون موبایل)
        // بی‌صدا رد می‌شود — نامی از این مسیر برایشان پیدا نمی‌شود، همان‌طور
        // که قبلاً هم برای هیچ کاربری بدون شماره ممکن نبود.
        if ($mobile !== '' && empty(get_user_meta($user_id, 'zdp_full_name', true))) {
            $known_name = apply_filters('psy_get_client_name_for_phone', '', $mobile);
            if (!empty($known_name) && is_string($known_name) && mb_strlen(trim($known_name)) >= 2) {
                update_user_meta($user_id, 'zdp_full_name', sanitize_text_field($known_name));
            }
        }

        // منبع دوم (فالبک): قرارداد فیلتری مشترک اکوسیستم برای گرفتن اسم با
        // شماره موبایل (pas_lookup_known_client_name) — همانی که خودِ این
        // پلاگین هم به‌عنوان یکی از تامین‌کننده‌هایش ثبت شده (نگاه کن به
        // pivand-amn-client-portal.php). افزونه‌ی پرونده‌ی بالینی
        // (pivand-amn-case-file) هم روی همین فیلتر ثبت‌نام کرده و فقط
        // full_name پرونده را برمی‌گرداند — هیچ داده‌ی بالینی دیگری منتقل
        // نمی‌شود. توجه: pas_get_case_summary_for_phone عمداً استفاده نشد،
        // چون آن فیلتر اصلاً فیلد اسم ندارد (فقط has_case_file/status/topic).
        if ($mobile !== '' && empty(get_user_meta($user_id, 'zdp_full_name', true))) {
            $known_name = apply_filters('pas_lookup_known_client_name', '', $mobile);
            if (!empty($known_name) && is_string($known_name) && mb_strlen(trim($known_name)) >= 2) {
                update_user_meta($user_id, 'zdp_full_name', sanitize_text_field($known_name));
            }
        }

        // نکته‌ی مهم (رفع باگ ریشه‌ای «موفق می‌گوید ولی وارد پنل نمی‌شوم»):
        // کوکی لاگین دیگر اینجا، داخل پاسخ REST/AJAX، ست نمی‌شود. این پاسخ
        // فقط یک توکن یک‌بارمصرف کوتاه‌عمر از طریق Login_Service صادر می‌کند؛
        // کوکی واقعی وقتی ست می‌شود که مرورگر با همین توکن به‌صورت یک ناوبری
        // واقعی صفحه (نه fetch/XHR) به /panel/ برود. توضیح کامل چرایی این
        // تصمیم داخل Login_Service آمده است.
        $redirect = Login_Service::build_redirect_url($user_id, zdp_panel_url());

        // پل به Booking (نگاه کن به توضیح کامل بالای Booking::__construct):
        // اگر این تایید OTP از شورت‌کد عمومی نوبت‌دهی روی صفحه‌ی اصلی آمده
        // باشد (یعنی $pending_booking خالی نیست)، همین‌جا — درست بعد از این‌که
        // user_id واقعی مشخص شد، پیش از هر ریدایرکتی — خودِ نوبت ثبت می‌شود.
        // نتیجه (موفق/ناموفق + کارت‌های کارت‌به‌کارت در صورت موفقیت) داخل
        // pending_booking به پاسخ اضافه می‌شود تا جاوااسکریپت صفحه‌ی اصلی قبل
        // از ریدایرکت به کاربر پیام مناسب (موفقیت/خطا) نشان دهد. این مسیر
        // فقط با user_id کار می‌کند، نه با موبایل — پس برای حساب‌های ایمیلِ
        // مستقل (بدون موبایل) هم عیناً کار می‌کند.
        $extra = apply_filters('zdp_otp_login_pending_booking', [], $user_id, $pending_booking);

        return [
            'success' => true,
            'data'    => array_merge(
                [
                    'message'  => 'ورود با موفقیت انجام شد.',
                    'redirect' => $redirect,
                ],
                is_array($extra) ? $extra : []
            ),
        ];
    }

    /**
     * منطق ارسال کدِ OTP از طریق ایمیل — برای مراجعینی که شماره‌ی ایرانی
     * برای دریافت پیامک ندارند (مثلاً مراجعین خارج از کشور)، یا صرفاً
     * می‌خواهند بدون هیچ ثبت قبلی‌ای وارد پنل شوند (مثلاً برای دیدن/تکمیل
     * یک آزمون).
     *
     * تغییر رفتار (طبق درخواست کارفرما، رفع محدودیت قبلی): تا این نسخه، کد
     * فقط برای ایمیلی ارسال می‌شد که از قبل توسط درمانگر (هنگام تعریف یک
     * آزمون) به یک شماره موبایل مشخص وصل شده بود؛ هر ایمیل دیگری رد می‌شد.
     * حالا هر ایمیل معتبر کد دریافت می‌کند — نیازی به ثبت قبلی نیست. اگر
     * این ایمیل قبلاً به یک شماره موبایل وصل بوده باشد، مرحله‌ی تایید
     * (process_verify_otp_email) همچنان همان اتصال قدیمی را تشخیص می‌دهد و
     * مراجع را به همان حساب/نوبت‌ها/آزمون‌های قبلی‌اش وصل می‌کند؛ در غیر این
     * صورت یک حساب مستقل و جدید فقط بر مبنای همین ایمیل ساخته می‌شود.
     *
     * نکته‌ی امنیتی: این یعنی دیگر «مالکیت واقعی ایمیل» با ثبتِ قبلیِ
     * درمانگر تایید نمی‌شود، بلکه صرفاً با دسترسی به صندوق ایمیل (توانایی
     * دریافت و خواندن کد) — دقیقاً همان سطح تضمینی که مسیر پیامکی هم دارد
     * (مالکیت واقعی شماره هرگز تایید نمی‌شود، فقط توانایی دریافت پیامک).
     *
     * @return array{success: bool, data: array<string, mixed>}
     */
    private function process_send_otp_email(string $email): array
    {
        // نرمال‌سازی حروف کوچک (رفع باگ «کد منقضی شده»): کلید transient با
        // md5 خود آدرس ساخته می‌شود و ایمیل از نظر RFC در بخش دامنه و عملاً
        // در بخش محلی هم حساس به بزرگی/کوچکی حروف نیست. اگر کاربر همان ایمیل
        // را در مرحله ارسال با حرف بزرگ و در مرحله تایید با حرف کوچک وارد کند
        // (یا مرورگر خودکار lowercase کند)، قبلاً کلید متفاوتی ساخته می‌شد و
        // کد معتبرِ ارسال‌شده «منقضی/استفاده‌شده» گزارش می‌شد. حالا همه‌جا
        // یکسان به حروف کوچک تبدیل می‌شود.
        $email = strtolower(sanitize_email($email));

        if (!is_email($email)) {
            return ['success' => false, 'data' => ['message' => 'ایمیل وارد شده معتبر نیست.']];
        }

        $ip = get_client_ip();
        if (!$this->security->allow('otp_email_ip_' . md5($ip), 3, 60)) {
            return ['success' => false, 'data' => ['message' => 'تعداد درخواست‌های ارسالی از سمت شما موقتاً بیش از حد مجاز است. لطفاً یک دقیقه دیگر تلاش کنید.']];
        }

        if (!$this->security->allow('otp_email_' . md5($email), 1, 60)) {
            return ['success' => false, 'data' => ['message' => 'لطفاً برای ارسال مجدد کد کمی صبر کنید (یک بار در هر ۶۰ ثانیه).']];
        }

        try {
            $otp_code = random_int(10000, 99999);
        } catch (\Exception $e) {
            $otp_code = wp_rand(10000, 99999);
        }

        $site_name = trim((string) preg_replace('/[\r\n]+/', ' ', wp_strip_all_tags((string) get_bloginfo('name'))));
        $subject   = 'کد ورود شما به پنل ' . $site_name;
        $body      = "کد تایید ورود شما: {$otp_code}\n\nاین کد تا ۵ دقیقه معتبر است. اگر درخواست ورود نداده‌اید، این پیام را نادیده بگیرید.";

        // همان ترتیب مسیر پیامکی: کد فقط *پس از* ارسال موفق ایمیل ذخیره
        // می‌شود، تا کد یتیمِ هرگز‌ارسال‌نشده در سیستم نماند.
        $mail_sent = wp_mail($email, $subject, $body);

        if (!$mail_sent) {
            $this->security->reset('otp_email_' . md5($email));
            audit_log('otp_email_send_failed', ['email' => $email]);

            return ['success' => false, 'data' => ['message' => 'خطا در ارسال ایمیل. لطفاً کمی بعد دوباره تلاش کنید.']];
        }

        $key = 'email_' . md5($email);
        set_transient('zdp_otp_' . $key, (string) $otp_code, 300);
        set_transient('zdp_otp_attempts_' . $key, 0, 300);

        audit_log('otp_email_sent', ['email' => $email]);

        return ['success' => true, 'data' => ['message' => 'کد تایید به ایمیل شما ارسال شد.']];
    }

    /**
     * منطق تایید OTP ایمیلی.
     *
     * تغییر رفتار (رفع محدودیت «ثبت قبلی برای آزمون»): این متد دیگر قبل از
     * تایید کد، رد نمی‌کند که آیا ایمیل از قبل به شماره‌ای وصل بوده یا نه —
     * آن چک قبلاً در process_send_otp_email هم حذف شده. اینجا، *بعد* از
     * تایید موفق کد، دو حالت ممکن است:
     *   ۱) این ایمیل قبلاً توسط درمانگر (هنگام تعریف یک آزمون) به یک شماره
     *      موبایل مشخص وصل شده — همان مسیر قدیمی دنبال می‌شود: حساب همان
     *      مراجعِ موبایل‌محور پیدا/استفاده می‌شود تا نوبت‌ها/آزمون‌های قبلی‌اش
     *      را ببیند (بدون تغییر نسبت به رفتار قبلی این پلاگین).
     *   ۲) این ایمیل به هیچ موبایلی وصل نیست — یک حساب کاربری مستقل و جدید
     *      فقط بر مبنای همین ایمیل ساخته/پیدا می‌شود (نگاه کن به
     *      find_or_create_user_by_email)، بدون نیاز به هیچ ثبت قبلی‌ای.
     *      چنین حسابی شماره موبایل ندارد؛ Client_Panel::render_panel و
     *      Quiz_Bridge با نبودِ موبایل (رشته‌ی خالی) درست کنار می‌آیند — تب
     *      «آزمون‌ها» فقط تا وقتی درمانگر بعداً موبایل را هم به همین حساب
     *      وصل کند خالی می‌ماند، نه اینکه خطا بدهد.
     *
     * @return array{success: bool, data: array<string, mixed>}
     */
    private function process_verify_otp_email(string $email, string $user_otp, array $pending_booking = []): array
    {
        // همان نرمال‌سازی حروف کوچک مرحله ارسال (نگاه کن به process_send_otp_email).
        $email = strtolower(sanitize_email($email));

        if (!is_email($email)) {
            return ['success' => false, 'data' => ['message' => 'ایمیل وارد شده معتبر نیست.']];
        }

        $user_otp = normalize_digits(trim($user_otp));
        $key      = 'email_' . md5($email);

        $guard_result = $this->guard_verify_attempt_by_key($key, $user_otp, ['email' => $email]);
        if ($guard_result !== null) {
            return $guard_result;
        }

        delete_transient('zdp_otp_' . $key);
        delete_transient('zdp_otp_attempts_' . $key);

        $known_mobile = find_mobile_by_email($email);

        if ($known_mobile !== '') {
            $user_id = $this->find_or_create_user($known_mobile);
            $mobile  = $known_mobile;
        } else {
            $user_id = $this->find_or_create_user_by_email($email);
            $mobile  = '';
        }

        if (is_wp_error($user_id)) {
            audit_log('otp_login_failed', ['email' => $email, 'error' => $user_id->get_error_message()]);

            return ['success' => false, 'data' => ['message' => 'خطا در ساخت حساب کاربری جدید.']];
        }

        audit_log('otp_login_success', $mobile !== '' ? ['email' => $email, 'mobile' => $mobile, 'user_id' => $user_id] : ['email' => $email, 'user_id' => $user_id]);

        return $this->finalize_login_for_user($user_id, $mobile, $pending_booking);
    }

    /**
     * رفع باگ کشف‌شده (race condition روی شمارنده‌ی تلاش‌های ناموفق OTP):
     * نسخه‌ی قبلی این تابع، چک «attempts >= 5» و افزایش شمارنده را در دو
     * عملیات جدا (get_transient سپس set_transient)، بدون هیچ قفلی انجام
     * می‌داد — دقیقاً همان الگوی race condition ای که در Security::allow()
     * (برای rate-limit ارسال OTP) شناسایی و با GET_LOCK رفع شده بود، اما
     * اینجا (برای شمارنده‌ی تلاش‌های تاییدِ OTP) اعمال نشده بود.
     *
     * نتیجه‌ی عملی این نبود قفل: اگر مهاجم چند حدس را دقیقاً هم‌زمان (موازی)
     * بفرستد، همه‌ی آن‌ها می‌توانستند مقدار قدیمی $attempts (مثلاً ۴) را
     * قبل از این‌که هرکدام مقدار جدید را بنویسد بخوانند؛ آخرین نوشتن برنده
     * می‌شد و مقدار نهایی شمارنده هنوز فقط ۵ می‌ماند — یعنی مهاجم به‌جای
     * «فقط ۵ حدس در کل قبل از قفل‌شدن»، می‌توانست در هر بار موازی‌سازی چند
     * حدسِ اضافه‌تر از سقف واقعی بزند و این کار را در بازه‌های موازیِ متوالی
     * تکرار کند — عملاً محدودیت brute-force کد ۵ رقمی را تضعیف می‌کرد.
     *
     * الان دقیقاً همان الگوی GET_LOCK/RELEASE_LOCK که در Security::allow()
     * استفاده شده، اینجا هم به‌کار رفته تا خواندن+چک+افزایشِ شمارنده یک
     * عملیات اتمیک باشد. اگر قفل گرفته نشود، محافظه‌کارانه (نه «مجاز»)
     * درخواست را رد می‌کنیم — امن‌تر از دور زدن ناخواسته‌ی محدودیت تلاش.
     *
     * @return array{success: bool, data: array<string, mixed>}|null آرایه‌ی
     *         پاسخ خطا اگر نباید ادامه داد، یا null اگر کد درست بود (مسیر
     *         موفقیت باید در process_verify_otp ادامه یابد).
     */
    private function guard_verify_attempt(string $mobile, string $user_otp): ?array
    {
        return $this->guard_verify_attempt_by_key($mobile, $user_otp, ['mobile' => $mobile]);
    }

    /**
     * نسخه‌ی عمومیِ guard_verify_attempt که با هر کلید (نه فقط شماره موبایل)
     * کار می‌کند — از process_verify_otp_email هم با کلید مبتنی بر ایمیل
     * (نگاه کن به process_send_otp_email) استفاده می‌شود. برای مسیر موبایل،
     * کلید همان $mobile است، پس نام‌های transient/lock دقیقاً همان قبلی
     * می‌مانند و رفتار مسیر موبایل هیچ تغییری نکرده.
     *
     * رفع باگ (پیدا شده هنگام بازبینی): $log_context مشخص می‌کند audit_log
     * با چه فیلدی ثبت شود. بدون این پارامتر، همه‌ی فراخوانی‌ها (چه موبایل چه
     * ایمیل) با یک فیلد عمومی 'key' لاگ می‌شدند — یعنی لاگ‌های otp_verify_expired
     * مسیر موبایل دیگر فیلد 'mobile' نداشتند، برخلاف بقیه‌ی رخدادهای این
     * کلاس (otp_sent، otp_login_success و...) که همیشه 'mobile' دارند. حالا
     * caller صریحاً می‌گوید لاگ با کدام فیلد ثبت شود.
     */
    private function guard_verify_attempt_by_key(string $key, string $user_otp, array $log_context = []): ?array
    {
        global $wpdb;

        if ($log_context === []) {
            $log_context = ['key' => $key];
        }

        $lock_name = 'zdp_otp_verify_lock_' . md5($key);
        $got_lock  = (int) $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 2)', $lock_name));

        if ($got_lock !== 1) {
            return ['success' => false, 'data' => ['message' => 'درخواست‌های هم‌زمان بیش از حد مجاز است. لطفاً چند ثانیه صبر کرده و دوباره تلاش کنید.']];
        }

        try {
            $attempts = (int) get_transient('zdp_otp_attempts_' . $key);
            if ($attempts >= 5) {
                delete_transient('zdp_otp_' . $key);

                return ['success' => false, 'data' => ['message' => 'تعداد تلاش‌های ناموفق شما بیش از حد مجاز بود. لطفاً مجدداً درخواست کد کنید.']];
            }

            $saved_otp = get_transient('zdp_otp_' . $key);

            // نکته‌ی مهم (رفع باگ «کد درست را وارد می‌کنم ولی قبول نمی‌شود»):
            // این دو حالت را دیگر با یک پیام یکسان («کد اشتباه است») گزارش
            // نمی‌کنیم. اگر transient اصلاً وجود ندارد (یعنی یا کد قبلاً
            // مصرف شده یا TTL آن (اکنون ۳۰۰ ثانیه) گذشته)، به کاربر می‌گوییم
            // کد منقضی شده — نه اینکه فرض کند اشتباه تایپ کرده و دوباره همان
            // کد را (که دیگر در سیستم وجود ندارد) امتحان کند. این دقیقاً
            // سناریویی است که با تاخیر ارسال پیامک (گیت‌وی‌های ایرانی گاهی
            // ۲-۳ دقیقه طول می‌کشند) و/یا درخواست مجدد کد پیش از رسیدن پیامک
            // اول رخ می‌دهد.
            if (!$saved_otp) {
                audit_log('otp_verify_expired', $log_context);

                return ['success' => false, 'data' => ['message' => 'کد منقضی شده یا قبلاً استفاده شده است. لطفاً دوباره درخواست کد کنید.']];
            }

            if (!hash_equals((string) $saved_otp, $user_otp)) {
                $attempts++;
                set_transient('zdp_otp_attempts_' . $key, $attempts, 300);
                $remaining = 5 - $attempts;

                if ($remaining <= 0) {
                    delete_transient('zdp_otp_' . $key);

                    return ['success' => false, 'data' => ['message' => 'تعداد تلاش‌های ناموفق شما بیش از حد مجاز بود. لطفاً مجدداً درخواست کد کنید.']];
                }

                return ['success' => false, 'data' => ['message' => "کد وارد شده صحیح نیست. {$remaining} تلاش دیگر باقی مانده است."]];
            }

            return null;
        } finally {
            $wpdb->query($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $lock_name));
        }
    }

    private function find_or_create_user(string $mobile): int|\WP_Error
    {
        // اولویت با کلید مشترک اکوسیستم (cellphone) — ممکن است این کاربر
        // قبلاً توسط پلاگین دیگری (مثلاً pivand-amn-panel) ساخته شده باشد.
        $users = get_users([
            'meta_key'   => 'cellphone',
            'meta_value' => $mobile,
            'number'     => 1,
        ]);

        if (!empty($users)) {
            // اگر متای اختصاصی این پلاگین رویش ست نیست (چون از جای دیگری
            // ساخته شده)، همین‌جا هم‌گام می‌کنیم.
            if (!get_user_meta($users[0]->ID, 'zdp_mobile', true)) {
                update_user_meta($users[0]->ID, 'zdp_mobile', $mobile);
            }

            return (int) $users[0]->ID;
        }

        // سازگاری عقب‌رو: رکوردهای قدیمی این پلاگین که هنوز cellphone رویشان ست نشده.
        $legacy_zdp_users = get_users([
            'meta_key'   => 'zdp_mobile',
            'meta_value' => $mobile,
            'number'     => 1,
        ]);
        if (!empty($legacy_zdp_users)) {
            update_user_meta($legacy_zdp_users[0]->ID, 'cellphone', $mobile);

            return (int) $legacy_zdp_users[0]->ID;
        }

        // سازگاری عقب‌رو: اگر هنوز مهاجرت کامل نشده، متای قدیمی افزونه‌ی قبلی را هم چک کن.
        $legacy_users = get_users([
            'meta_key'   => 'cpp_billing_phone',
            'meta_value' => $mobile,
            'number'     => 1,
        ]);
        if (!empty($legacy_users)) {
            zdp_set_user_mobile((int) $legacy_users[0]->ID, $mobile);

            return (int) $legacy_users[0]->ID;
        }

        $domain          = wp_parse_url(home_url(), PHP_URL_HOST) ?: 'peyvandamn.ir';
        $random_password = wp_generate_password();

        // sanitize_user() هسته‌ی وردپرس کاراکتر «+» را حذف می‌کند (خارج از
        // whitelist مجاز آن)؛ برای شماره‌های خارجی که با to_local_format()
        // به فرمت +<کدکشور> ذخیره می‌شوند، اگر همان مقدار خام برای
        // user_login/email استفاده شود، مقداری که واقعاً ساخته می‌شود با
        // آنچه اینجا محاسبه شده فرق می‌کند (مثلاً در چک username_exists/
        // email_exists زیر). پس همین‌جا صریحاً «+» را حذف می‌کنیم تا مقدار
        // پیش‌بینی‌شده و مقدار واقعاً ذخیره‌شده در وردپرس یکی باشند. شماره‌های
        // ایرانی هرگز «+» ندارند، پس این تغییر برای آن‌ها no-op است.
        $mobile_slug = str_replace('+', '', $mobile);
        $login       = 'zdp_' . $mobile_slug; // پیشوند اختصاصی تا با user_login سایر افزونه‌ها تصادم نکند

        $user_id = wp_create_user($login, $random_password, $mobile_slug . '@' . $domain);
        if (is_wp_error($user_id)) {
            audit_log('client_account_create_failed', [
                'mobile'        => $mobile,
                'error_code'    => $user_id->get_error_code(),
                'error_message' => $user_id->get_error_message(),
            ]);

            // خودترمیمی: اگر یوزرنیم یا ایمیل از قبل وجود دارد (معمولاً باقیمانده‌ی
            // یک تلاش ناقص قبلی که متای zdp_mobile رویش ست نشده)، به‌جای شکست
            // کامل ورود، همان حساب را به این شماره وصل می‌کنیم — فقط اگر آن
            // حساب یک حساب مدیریتی/نویسنده نباشد (برای جلوگیری از وصل‌شدن
            // تصادفی به حساب کارکنان).
            $existing_id = username_exists($login);
            if (!$existing_id) {
                $existing_id = email_exists($mobile_slug . '@' . $domain);
            }

            if ($existing_id) {
                $existing_user = get_user_by('id', $existing_id);
                if ($existing_user && !$existing_user->has_cap('edit_posts') && !$existing_user->has_cap('manage_options')) {
                    zdp_set_user_mobile((int) $existing_id, $mobile);
                    audit_log('client_account_relinked', ['mobile' => $mobile, 'user_id' => $existing_id]);

                    return (int) $existing_id;
                }
            }

            return $user_id;
        }

        $user = new \WP_User((int) $user_id);
        $user->set_role('subscriber'); // قفل صریح؛ مستقل از New User Default Role

        zdp_set_user_mobile($user_id, $mobile);
        update_user_meta($user_id, 'nickname', 'مراجع ' . $mobile);
        wp_update_user(['ID' => $user_id, 'display_name' => 'مراجع ' . $mobile]);

        audit_log('client_account_created', ['mobile' => $mobile, 'user_id' => $user_id]);

        return (int) $user_id;
    }

    /**
     * پیدا کردن یا ساختن کاربر بر مبنای *فقط ایمیل* — مسیر خودسرویسِ ورود با
     * ایمیل، برای مراجعینی که هیچ اتصال قبلیِ ایمیل↔موبایل (از طریق تعریف
     * آزمون توسط درمانگر) ندارند. دقیقاً همان الگوی find_or_create_user
     * (نسخه‌ی موبایل) را دنبال می‌کند، فقط با کلید هویت متفاوت: چون این
     * کاربر شماره موبایلی ندارد، کلید مشترک اکوسیستم `cellphone` اینجا
     * بی‌معناست؛ به‌جایش یک متای اختصاصی `zdp_email` برای پیدا کردن دوباره‌ی
     * همین حساب در ورودهای بعدی استفاده می‌شود، و خودِ آدرس ایمیل واقعی
     * (نه یک ایمیل ساختگیِ `mobile@domain`) به‌عنوان user_email واقعی کاربر
     * وردپرس ذخیره می‌شود.
     *
     * نکته: چنین کاربری تا وقتی درمانگر شماره موبایلش را (مثلاً هنگام ثبت
     * نوبت دستی یا تعریف آزمون) هم به همین حساب وصل نکند، در تب «آزمون‌ها»ی
     * پنل چیزی نمی‌بیند — چون تنها قرارداد فعلیِ اتصال به psy-quiz-enterprise
     * (psy_get_pending_quizzes_for_phone) بر مبنای موبایل است، نه ایمیل. این
     * یک محدودیتِ شناخته‌شده‌ی فعلی است، نه یک باگ؛ رفعش نیازمند افزودن یک
     * قرارداد فیلتر جدید مبتنی بر ایمیل در psy-quiz-enterprise است.
     *
     * @return int|\WP_Error شناسه‌ی کاربر یا خطا
     */
    private function find_or_create_user_by_email(string $email): int|\WP_Error
    {
        $users = get_users(['meta_key' => 'zdp_email', 'meta_value' => $email, 'number' => 1]);
        if (!empty($users)) {
            return (int) $users[0]->ID;
        }

        // سازگاری با حساب‌هایی که پیش از نرمال‌سازی حروف کوچک ساخته شده‌اند
        // و متای zdp_email آن‌ها با حرف بزرگ ذخیره شده است؛ بدون این چک،
        // همان کاربر یک حساب تکراری می‌گرفت.
        global $wpdb;
        $legacy_email_user = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = %s AND LOWER(meta_value) = %s LIMIT 1",
            'zdp_email',
            $email
        ));
        if ($legacy_email_user > 0) {
            update_user_meta($legacy_email_user, 'zdp_email', $email);

            return $legacy_email_user;
        }

        // اگر کاربری با همین user_email دقیقاً از قبل روی سایت وجود دارد
        // (مثلاً یک حساب دیگر از همین اکوسیستم که قبلاً با همین ایمیل واقعی
        // ساخته شده)، فقط وقتی آن حساب یک حساب مدیریتی/نویسنده نیست به آن
        // وصل می‌شویم — دقیقاً همان محافظتی که find_or_create_user برای
        // شماره موبایل هم دارد (جلوگیری از وصل‌شدن ناخواسته به حساب کارکنان).
        $existing = get_user_by('email', $email);
        if ($existing) {
            if ($existing->has_cap('edit_posts') || $existing->has_cap('manage_options')) {
                return new \WP_Error('zdp_email_owned', 'این ایمیل قبلاً به یک حساب دیگر تعلق دارد. لطفاً با پشتیبانی تماس بگیرید.');
            }

            if (!get_user_meta($existing->ID, 'zdp_email', true)) {
                update_user_meta($existing->ID, 'zdp_email', $email);
            }

            return (int) $existing->ID;
        }

        $random_password = wp_generate_password();
        $login_base      = 'zdpmail_' . sanitize_user(current(explode('@', $email)) ?: 'client', true);
        $login           = $login_base !== '' ? $login_base : 'zdpmail_client';

        // برخورد نام کاربری: چون بخشِ قبل از @ ایمیل می‌تواند بین چند کاربر
        // مشترک باشد (فرضاً روی دامنه‌های متفاوت)، در صورت تصادم یک پسوند
        // عددی افزایشی اضافه می‌شود تا wp_create_user هرگز فقط به‌خاطر
        // یوزرنیم تکراری (نه ایمیل تکراری، که بالا جدا چک شد) شکست نخورد.
        $suffix = 1;
        while (username_exists($login)) {
            $login = $login_base . '_' . $suffix;
            $suffix++;
        }

        $user_id = wp_create_user($login, $random_password, $email);
        if (is_wp_error($user_id)) {
            audit_log('client_account_create_failed_email', [
                'email'         => $email,
                'error_code'    => $user_id->get_error_code(),
                'error_message' => $user_id->get_error_message(),
            ]);

            // خودترمیمی (هم‌الگو با find_or_create_user نسخه‌ی موبایل، خط
            // ۸۱۱ به بعد): اگر یوزرنیم یا ایمیل از قبل وجود دارد (معمولاً
            // باقیمانده‌ی یک تلاش ناقص قبلی — مثلاً کاربر همان ایمیل را قبلاً
            // امتحان کرده، حساب ساخته شده ولی به هر دلیلی متای zdp_email
            // رویش ست نشده)، به‌جای شکست کامل ورود، همان حساب را (فقط اگر
            // مدیریتی/نویسنده نباشد) به این ایمیل وصل می‌کنیم.
            $existing_id = username_exists($login);
            if (!$existing_id) {
                $existing_id = email_exists($email);
            }

            if ($existing_id) {
                $existing_user = get_user_by('id', $existing_id);
                if ($existing_user && !$existing_user->has_cap('edit_posts') && !$existing_user->has_cap('manage_options')) {
                    if (!get_user_meta($existing_id, 'zdp_email', true)) {
                        update_user_meta($existing_id, 'zdp_email', $email);
                    }
                    audit_log('client_account_relinked_email', ['email' => $email, 'user_id' => $existing_id]);

                    return (int) $existing_id;
                }
            }

            return $user_id;
        }

        $user = new \WP_User((int) $user_id);
        $user->set_role('subscriber');

        update_user_meta($user_id, 'zdp_email', $email);
        update_user_meta($user_id, 'nickname', 'مراجع ' . $email);
        wp_update_user(['ID' => $user_id, 'display_name' => 'مراجع ' . $email]);

        audit_log('client_account_created_email', ['email' => $email, 'user_id' => $user_id]);

        return (int) $user_id;
    }

    /**
     * ارسال پیامک از طریق قرارداد فیلتر مشترک بین همه‌ی پلاگین‌های این
     * اکوسیستم (همان الگویی که در pivand-amn-survey با Notify.php و فیلتر
     * pas_send_sms پیاده شده). این پلاگین خودش هیچ API خاصی (ملی پیامک،
     * کاوه‌نگار و...) را هاردکد نمی‌کند؛ هر پلاگین دیگری که handler واقعی
     * پیامک را دارد (یا یک mu-plugin کوچک) با add_filter('pas_send_sms', ...)
     * به این فراخوانی پاسخ می‌دهد. اگر هیچ handler‌ای ثبت نشده باشد، مقدار
     * پیش‌فرض null برمی‌گردد و اینجا آن را false (شکست ارسال) تفسیر می‌کنیم
     * تا کد OTP هرگز بدون تایید واقعی ارسال، برای کاربر «معتبر» فرض نشود.
     */
    private function send_sms_pattern(string $mobile, string $body_id, string $code): bool
    {
        $result = apply_filters('pas_send_sms', null, $mobile, $body_id, ['CODE' => $code]);

        if ($result === null) {
            audit_log('sms_handler_missing', ['mobile' => $mobile]);

            return false;
        }

        return (bool) $result;
    }
}
