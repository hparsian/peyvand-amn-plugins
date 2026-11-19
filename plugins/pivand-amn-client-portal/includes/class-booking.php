<?php

declare(strict_types=1);

namespace ZoojDarman\ClientPortal;

use ZoojDarman\ClientPortal\Gateways\Zarinpal;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * نوبت‌دهی + پرداخت زرین‌پال یا کارت‌به‌کارت دستی.
 * - رزرو هم‌زمان یک بازه‌ی زمانی با یک named lock سطح‌اپلیکیشن (GET_LOCK) و
 *   بررسی نوبت‌های «فعال» (نه لغو/ناموفق) برای همان بازه جلوگیری می‌شود؛
 *   برخلاف یک UNIQUE KEY دیتابیسی، این روش باعث نمی‌شود یک پرداخت شکست‌خورده
 *   آن بازه را برای همیشه قفل کند.
 * - بازگشت از درگاه پرداخت با الگوی PRG (Post/Redirect/Get) انجام می‌شود تا
 *   رفرش صفحه دوباره callback را صدا نزند.
 * - همه‌ی کوئری‌ها prepared statement هستند.
 */
final class Booking
{
    /** طول هر جلسه‌ی نوبت‌دهی به دقیقه — طبق درخواست: ۴۵ دقیقه، ثابت برای کل سیستم. */
    private const SLOT_DURATION_MINUTES = 45;

    /** پیش‌فرض بازه‌ی نمایش جدولِ شورت‌کد عمومیِ نوبت‌دهی (تعداد روز روبه‌جلو). */
    private const GRID_DAYS_AHEAD = 14;

    /**
     * فقط تصویر (jpg/png) برای رسید کارت‌به‌کارت پذیرفته می‌شود — طبق تصمیم
     * صریح، نه PDF. هر دو کلید (پسوند => mime واقعی) برای اعتبارسنجی دوگانه
     * لازم است: پسوند فایل به‌تنهایی قابل‌جعل است (کاربر می‌تواند یک فایل غیر
     * تصویری را دستی .jpg نام‌گذاری کند)؛ mime واقعی با wp_check_filetype_and_ext
     * (که خودِ محتوای فایل را هم چک می‌کند) تایید می‌شود.
     */
    private const RECEIPT_ALLOWED_MIME = ['jpg|jpeg' => 'image/jpeg', 'png' => 'image/png'];

    /** حداکثر حجم مجاز فایل رسید (۵ مگابایت) — جلوگیری از آپلود فایل‌های حجیم/مخرب. */
    private const RECEIPT_MAX_BYTES = 5 * 1024 * 1024;

    /**
     * باگ رفع‌شده: قبلاً نوبتی که مراجع بعد از رفتن به درگاه زرین‌پال هرگز به
     * سایت برنگشت (نه پرداخت را کامل کرد، نه صریحاً چیزی را لغو کرد) برای
     * همیشه در وضعیت «در انتظار پرداخت» می‌ماند. چون already_taken/get_booked_slots
     * فقط وضعیت‌های 'ناموفق' و 'لغو شده' را نادیده می‌گیرند، همان بازه‌ی زمانی
     * تا ابد برای بقیه‌ی مراجعان قفل می‌ماند — حتی برای پرداختی که هرگز کامل
     * نشده. این مقدار طول عمر (به دقیقه) یک نوبتِ «در انتظار پرداخت» است؛
     * بعد از این مدت expire_stale_pending_bookings() آن را 'ناموفق' می‌کند تا
     * بازه آزاد شود. عمداً فقط برای مسیر زرین‌پال است، نه کارت‌به‌کارت
     * («در انتظار تایید واریز» می‌تواند طبیعتاً بیشتر طول بکشد چون مراجع باید
     * اول واریز کند و رسید بگذارد).
     */
    private const PENDING_PAYMENT_EXPIRE_MINUTES = 30;

    private Security $security;
    private Leads $leads;
    private Zarinpal $gateway;

    public function __construct(Security $security, Leads $leads)
    {
        $this->security = $security;
        $this->leads    = $leads;
        $this->gateway  = new Zarinpal();

        add_action('wp_ajax_zdp_create_booking', [$this, 'handle_create_booking']);
        add_action('wp_ajax_nopriv_zdp_create_booking', [$this, 'handle_create_booking']);
        add_action('init', [$this, 'handle_zarinpal_callback']);

        // آپلود رسید کارت‌به‌کارت: عمداً روی admin-ajax.php (نه REST/wp-json)
        // ثبت شده، دقیقاً مثل zdp_create_booking. طبق تجربه‌ی مستندشده‌ی همین
        // پروژه (نگاه کن به یادداشت‌های Client_Panel::register_rest_routes)،
        // یک لایه‌ی امنیتی/فایروال سطح هاست درخواست‌های POST به wp-json را
        // سخت‌گیرانه‌تر بررسی می‌کند؛ آپلود فایل هم که ذاتاً POST است، برای
        // جلوگیری از تکرار همان مشکل روی همان مسیر اثبات‌شده (admin-ajax)
        // قرار گرفت. فقط برای کاربر لاگین‌شده معنا دارد، پس نسخه‌ی nopriv ندارد.
        add_action('wp_ajax_zdp_upload_receipt', [$this, 'handle_upload_receipt']);

        // شورت‌کد عمومی جدول نوبت‌دهی برای صفحه‌ی اصلی/هر صفحه‌ی معمولی سایت —
        // برخلاف فرم داخل پنل، برای مراجعِ لاگین‌نشده هم قابل‌استفاده است.
        add_shortcode('zdp_booking_widget', [$this, 'render_booking_widget_shortcode']);

        // نام/قیمت خدمات مستقیم داخل HTML ویجت چاپ می‌شود و صفحه‌ی عمومی
        // (مثلاً صفحه‌ی اصلی) توسط افزونه‌ی کش ذخیره می‌شود؛ پس با هر تغییر
        // در لیست خدمات باید کش صفحات پاک شود وگرنه قیمت قدیمی نمایش داده می‌شود.
        add_action('update_option_zdp_services_list', [Cache_Guard::class, 'purge_pages']);
        add_action('add_option_zdp_services_list', [Cache_Guard::class, 'purge_pages']);
        add_action('delete_option_zdp_services_list', [Cache_Guard::class, 'purge_pages']);

        // جدول/کارت قیمت خدمات برای صفحات عمومی؛ از همان منبع واحد (zdp_services_list)
        // که ویجت رزرو و تب «نوبت‌ها»ی پنل می‌خوانند، تا هیچ‌جا قیمت دستی تایپ نشود.
        add_shortcode('zdp_services_prices', [$this, 'render_services_prices_shortcode']);

        add_action('rest_api_init', [$this, 'register_rest_routes']);

        // پل بین OTP_Auth و Booking بدون وابستگیِ مستقیم کلاس به کلاس (طبق
        // قرارداد معماری این اکوسیستم: فقط از طریق هوک/فیلتر). وقتی مراجعِ
        // جدول عمومی، بلافاصله بعد از تایید موفق کد OTP یک بازه‌ی درِ-انتظار
        // داشته باشد، OTP_Auth با همین فیلتر و user_id واقعاً تازه‌ساخته‌شده
        // (نه از کوکی/is_user_logged_in، چون کوکی هنوز روی مرورگر ست نشده)
        // از Booking می‌خواهد همان‌جا نوبت را ثبت کند.
        add_filter('zdp_otp_login_pending_booking', [$this, 'create_booking_from_otp_flow'], 10, 3);

        // باگ رفع‌شده (نگاه کن به توضیح PENDING_PAYMENT_EXPIRE_MINUTES بالا):
        // یک کرون ساعتیِ خودکفا برای آزادسازی نوبت‌های «در انتظار پرداخت»یی
        // که رها شده‌اند. عمداً مستقیم اینجا (نه در Cron جداگانه) رجیستر شده
        // چون این وابسته به منطق/جدول همین کلاس است و نیازی به وابستگی
        // متقابل با class-cron.php ندارد. wp_next_scheduled از ثبت تکراری
        // در هر page load جلوگیری می‌کند.
        add_action('zdp_expire_stale_pending_bookings', [$this, 'expire_stale_pending_bookings']);
        if (!wp_next_scheduled('zdp_expire_stale_pending_bookings')) {
            wp_schedule_event(time(), 'hourly', 'zdp_expire_stale_pending_bookings');
        }
    }

    /**
     * ثبت route عمومیِ REST برای گرفتنِ جدول بازه‌های خالی/پر — بدون نیاز به
     * لاگین (مثل send-otp/verify-otp، فقط با nonce استاندارد wp_rest که برای
     * بازدیدکننده‌ی لاگین‌نشده هم معتبر صادر می‌شود).
     */
    public function register_rest_routes(): void
    {
        register_rest_route('zdp/v1', '/booking-grid', [
            'methods'             => 'GET',
            'callback'            => [$this, 'rest_booking_grid'],
            'permission_callback' => [$this, 'rest_permission_check'],
        ]);
    }

    /** همان الگوی تکرارشده‌ی nonce استاندارد REST در OTP_Auth/Client_Panel — عمداً بدون وابستگیِ مستقیم بین کلاس‌ها. */
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

    public function rest_booking_grid(\WP_REST_Request $request): \WP_REST_Response
    {
        Cache_Guard::no_store();

        $days = (int) $request->get_param('days');
        if ($days <= 0 || $days > 60) {
            $days = self::GRID_DAYS_AHEAD;
        }

        // نوع خدمت هرگز مستقیم از کلاینت پذیرفته نمی‌شود (طبق همان اصل امنیتیِ
        // create_booking_internal) — فقط شناسه گرفته می‌شود و نوع واقعی از
        // تعریف رسمی خدمت در پیشخوان بازیابی می‌شود.
        $service_id   = sanitize_text_field((string) $request->get_param('service_id'));
        $service_type = 'both';
        if ($service_id !== '') {
            foreach ($this->get_services() as $s) {
                if (($s['id'] ?? '') === $service_id) {
                    $service_type = (string) ($s['type'] ?? 'both');
                    break;
                }
            }
        }

        // نکته: این پاسخ توسط Cache_Guard::no_store() از کش صفحه/CDN معاف
        // شده (برخلاف HTML اسکلتِ ویجت که ممکن است کش شود)، پس همین‌جا منبع
        // امنِ وضعیتِ واقعیِ لاگین است — نگاه کن به یادداشت‌های
        // render_booking_widget_shortcode برای چرایی این تغییر.
        $logged_in = is_user_logged_in();
        $full_name = $logged_in ? (string) get_user_meta(get_current_user_id(), 'zdp_full_name', true) : '';

        return new \WP_REST_Response([
            'success' => true,
            'data'    => array_merge(
                $this->get_booking_grid($days, $service_type),
                ['logged_in' => $logged_in, 'full_name' => $full_name]
            ),
        ], 200);
    }

    /**
     * فیلتر پل‌زننده به OTP_Auth — نگاه کن به توضیح بالای __construct.
     * $pending شکل ['service_id'=>..,'booking_date'=>..,'booking_time'=>..,'full_name'=>..] دارد.
     *
     * @param array<string, mixed> $result نتیجه‌ی تجمیعی که به پاسخ verify-otp اضافه می‌شود.
     * @param array<string, mixed> $pending
     * @return array<string, mixed>
     */
    public function create_booking_from_otp_flow(array $result, int $user_id, array $pending): array
    {
        $service_id = (string) ($pending['service_id'] ?? '');
        $date       = (string) ($pending['booking_date'] ?? '');
        $time       = (string) ($pending['booking_time'] ?? '');

        // هیچ‌کدام از سه فیلد ارسال نشده: این صرفاً یک ورود معمولی به پنل
        // است (نه از دلِ ویجت رزرو نوبت)، نه یک تلاشِ ناقص برای رزرو. حالت
        // طبیعی است، چیزی لاگ نمی‌شود.
        if ($service_id === '' && $date === '' && $time === '') {
            return $result;
        }

        // باگ رفع‌شده («تایید OTP کار می‌کند ولی نوبت ثبت نمی‌شود، بدون هیچ
        // خطایی»): قبلاً اگر فقط یکی از این سه فیلد خالی بود (نه هرسه)، این
        // متد همچنان همین‌جا بی‌صدا return می‌کرد — مراجع لاگین و به پنل
        // ریدایرکت می‌شد، بدون این‌که نوبتی ساخته شود یا هیچ ردی از این
        // اتفاق در لاگ امنیتی بماند. حالا دقیقاً همین حالت («تلاش برای رزرو
        // انجام شد، ولی یکی از فیلدها موقع رسیدن به سرور خالی بود») ثبت
        // می‌شود؛ کدام فیلد خالی مانده از همین ردیفِ لاگ مشخص است.
        if ($service_id === '' || $date === '' || $time === '') {
            audit_log('otp_pending_booking_incomplete', [
                'user_id'      => $user_id,
                'service_id'   => $service_id,
                'booking_date' => $date,
                'booking_time' => $time,
            ]);

            return $result;
        }

        $booking_result = $this->create_booking_internal($user_id, $pending);
        $result['pending_booking'] = $booking_result;

        return $result;
    }

    /**
     * پیش‌فرض امن اگر ادمین هنوز تنظیمات ساعات‌کاری را ذخیره نکرده باشد:
     * همه‌ی روزها فعال، ۹ تا ۲۱، بدون بازه‌ی استراحت — یعنی رفتار قبلی
     * (بدون محدودیت واقعی) برای نصب‌های موجود به‌طور ناگهانی نمی‌شکند تا
     * خودشان تنظیمش کنند.
     *
     * break_start/break_end (طبق درخواست کارفرما): یک بازه‌ی اختیاریِ
     * «استراحت/عدم مشاوره» داخل همان روزِ کاری (مثلاً ناهار ۱۳:۰۰ تا
     * ۱۴:۰۰) — نه یک روز کامل تعطیل. اگر هر دو مقدار خالی باشند (پیش‌فرض)،
     * یعنی آن روز بازه‌ی استراحتی ندارد و کل بازه‌ی start..end پیوسته باز
     * است (دقیقاً رفتار قبل از این قابلیت).
     */
    public static function default_business_hours(): array
    {
        $hours = [];
        for ($w = 0; $w <= 6; $w++) {
            $hours[$w] = ['enabled' => true, 'start' => '09:00', 'end' => '21:00', 'mode' => 'both', 'break_start' => '', 'break_end' => ''];
        }
        return $hours;
    }

    /**
     * آیا ساعت $time داخل بازه‌ی استراحتِ تعریف‌شده برای این روز است؟ اگر
     * break_start یا break_end خالی/نامعتبر باشد (یا break_start >=
     * break_end)، یعنی آن روز اصلاً بازه‌ی استراحت ندارد — همیشه false.
     */
    private static function is_within_break(array $day_conf, string $time): bool
    {
        $break_start = (string) ($day_conf['break_start'] ?? '');
        $break_end   = (string) ($day_conf['break_end'] ?? '');

        if ($break_start === '' || $break_end === '' || $break_start >= $break_end) {
            return false;
        }

        return $time >= $break_start && $time < $break_end;
    }

    /**
     * اعتبارسنجی سمت سرور ساعات‌کاری/تعطیلی. قبل از این متد، سرور فقط چک
     * می‌کرد بازه قبلاً رزرو نشده باشد؛ هیچ محدودیتی روی معتبربودنِ خودِ
     * بازه (روز تعطیل، خارج از ساعت کاری) نبود.
     *
     * @return string|null پیام خطا در صورت نامعتبر بودن، یا null اگر مجاز است.
     */
    /**
     * @param string $service_type نوع خدمت: 'phone' | 'online' | 'in_person' | 'both' (پیش‌فرض
     *               'both' برای خدمات قدیمی‌تری که هنوز این فیلد را ندارند).
     */
    /**
     * «آنلاین» (مشاوره‌ی تصویری) زیرمجموعه‌ی «تلفنی» حساب می‌شود: خودِ خدمت
     * قیمت و برچسبِ جدای خودش را دارد، اما در تنظیمِ «حالت روز» که فقط بین
     * حضوری/غیرحضوری فرق می‌گذارد (zdp_business_hours[$w]['mode'])، همان
     * روزهایی که «فقط تلفنی» تنظیم شده‌اند برای خدمات آنلاین هم مجازند.
     */
    private static function is_remote_service_type(string $service_type): bool
    {
        return in_array($service_type, ['phone', 'online'], true);
    }

    /**
     * آیا نوعِ خدمتِ انتخابی با حالتِ تنظیم‌شده‌ی آن روز (phone/in_person/both)
     * سازگار است؟ true یعنی مجاز، false یعنی مغایرت (آن روز برای این خدمت بسته است).
     */
    private static function service_type_matches_day_mode(string $service_type, string $day_mode): bool
    {
        if ($day_mode === 'both' || $service_type === 'both') {
            return true;
        }
        if ($day_mode === 'phone' && self::is_remote_service_type($service_type)) {
            return true;
        }
        return $service_type === $day_mode;
    }

    private function validate_business_hours(string $date, string $time, string $service_type = 'both'): ?string
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $time)) {
            return 'فرمت تاریخ یا ساعت نامعتبر است.';
        }

        // باگ رفع‌شده («رزرو نوبت برای تاریخ/ساعت گذشته»): تا قبل از این،
        // این متد فقط تعطیلی/ساعت‌کاری را چک می‌کرد، نه گذشته‌بودنِ خودِ
        // تاریخ را. جدولِ [zdp_booking_widget] (get_booking_grid) خودش
        // سلول‌های گذشته را در UI غیرقابل‌کلیک نشان می‌دهد، اما این فقط یک
        // محدودیتِ ظاهریِ سمت کلاینت بود؛ چون create_booking_internal مستقیماً
        // از AJAX خام (بدون گذر از UI گرید) هم صدا زده می‌شود، یک مراجع
        // می‌توانست با ارسال دستیِ درخواست (بدون هیچ دستکاری در دیتابیس)
        // برای دیروز یا هر تاریخ گذشته‌ای «نوبت» ثبت کند. مقایسه بر اساس
        // current_time (وقت محلی سایت، نه UTC سرور) انجام می‌شود تا با
        // get_booking_grid که همان منطق را برای today/now_time به کار
        // می‌برد یکسان باشد.
        $today_str = current_time('Y-m-d');
        $now_time  = current_time('H:i');
        if ($date < $today_str || ($date === $today_str && $time <= $now_time)) {
            return 'امکان رزرو نوبت برای تاریخ یا ساعتی که گذشته است وجود ندارد.';
        }

        // باگ رفع‌شده («رزرو نوبت روی ساعت‌های غیررند، همپوشان با نوبتِ رند
        // موجود»): طبق طراحیِ مستندشده‌ی سیستم (نگاه کن به توضیح بالای
        // get_booking_grid)، هر جلسه دقیقاً ۴۵ دقیقه است و فقط از یک عدد رند
        // ساعت (HH:00) شروع می‌شود — یعنی «۰۹:۰۰ تا ۰۹:۴۵» یک بازه‌ی معتبر
        // است، ولی «۰۹:۱۵» یا «۰۹:۳۰» نیست. قبل از این، هیچ اعتبارسنجی‌ای
        // روی دقیقه‌ی booking_time وجود نداشت؛ چون تشخیصِ «بازه‌ی اشغال» فقط
        // بر اساس تطابقِ دقیقِ رشته‌ی ساعت است (نه هم‌پوشانیِ واقعیِ بازه‌ها)،
        // یک درخواست با ساعت غیررند (مثلاً ۰۹:۲۰) می‌توانست هم‌زمان با نوبتِ
        // رندِ ۰۹:۰۰ (که کل ۰۹:۰۰ تا ۰۹:۴۵ را اشغال کرده) ثبت شود — یعنی دو
        // مراجع برای یک بازه‌ی هم‌پوشان، هر دو نوبتِ «فعال» می‌گرفتند.
        if (substr($time, 3, 2) !== '00') {
            return 'ساعت انتخابی نامعتبر است؛ فقط ساعت‌های رند (مثلاً ۰۹:۰۰) قابل رزرو هستند.';
        }

        $ts = strtotime($date);
        if ($ts === false) {
            return 'تاریخ نامعتبر است.';
        }

        $blackout = get_option('zdp_blackout_dates', []);
        if (in_array($date, $blackout, true)) {
            return 'در این تاریخ امکان رزرو نوبت وجود ندارد (تعطیل).';
        }

        $hours    = get_option('zdp_business_hours', self::default_business_hours());
        $weekday  = (int) gmdate('w', $ts); // 0=یکشنبه ... 6=شنبه، مطابق date('w') وردپرس
        $day_conf = $hours[$weekday] ?? ['enabled' => false, 'start' => '09:00', 'end' => '21:00', 'mode' => 'both'];

        if (empty($day_conf['enabled'])) {
            return 'در این روز از هفته نوبت‌دهی فعال نیست.';
        }

        if ($time < $day_conf['start'] || $time >= $day_conf['end']) {
            return sprintf('ساعت انتخابی خارج از بازه‌ی کاری است (%s تا %s).', $day_conf['start'], $day_conf['end']);
        }

        if (self::is_within_break($day_conf, $time)) {
            return sprintf('این ساعت در بازه‌ی استراحت است (%s تا %s) و قابل رزرو نیست.', $day_conf['break_start'], $day_conf['break_end']);
        }

        // کنترل ادمین روی نوع مشاوره‌ی هر روز (تلفنی/حضوری/هردو). اگر خدمتِ
        // انتخابی «هردو» باشد (یا برچسب نوع نداشته باشد)، همیشه مجاز است؛
        // فقط وقتی خدمت صراحتاً «تلفنی» یا «حضوری» باشد و آن روز فقط نوع
        // مقابل را پشتیبانی کند، رد می‌شود.
        $day_mode = (string) ($day_conf['mode'] ?? 'both');
        if (!self::service_type_matches_day_mode($service_type, $day_mode)) {
            $day_mode_label = $day_mode === 'phone' ? 'تلفنی/آنلاین' : 'حضوری';
            return sprintf('در این روز فقط جلسات %s برگزار می‌شود.', $day_mode_label);
        }

        return null;
    }

    public function handle_create_booking(): void
    {
        $this->security->verify_ajax_nonce();

        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => 'برای رزرو نوبت ابتدا وارد پنل شوید.']);
        }

        $data = [
            'service_id'   => isset($_POST['service_id']) ? sanitize_text_field(wp_unslash($_POST['service_id'])) : '',
            'booking_date' => isset($_POST['booking_date']) ? sanitize_text_field(wp_unslash($_POST['booking_date'])) : '',
            'booking_time' => isset($_POST['booking_time']) ? sanitize_text_field(wp_unslash($_POST['booking_time'])) : '',
            'full_name'    => isset($_POST['full_name']) ? sanitize_text_field(wp_unslash($_POST['full_name'])) : '',
        ];

        $result = $this->create_booking_internal(get_current_user_id(), $data);

        if ($result['success']) {
            wp_send_json_success($result['data']);
        }

        wp_send_json_error($result['data']);
    }

    /**
     * آپلود رسید کارت‌به‌کارت توسط خودِ مراجع، برای نوبتی که در وضعیت «در
     * انتظار تایید واریز» است. بعد از آپلود موفق، نوبت هنوز به‌صورت خودکار
     * «تایید شده» نمی‌شود — فقط مسیر رسید در دیتابیس ذخیره می‌شود تا ادمین
     * از تب «نوبت‌ها» ببیندش و دستی تایید کند (همان update_status موجود).
     */
    public function handle_upload_receipt(): void
    {
        $this->security->verify_ajax_nonce();

        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => 'برای آپلود رسید ابتدا وارد پنل شوید.']);
        }

        $user_id    = get_current_user_id();
        $booking_id = isset($_POST['booking_id']) ? (int) $_POST['booking_id'] : 0;

        if (!$this->security->allow('receipt_upload_' . $user_id, 10, 600)) {
            wp_send_json_error(['message' => 'تعداد تلاش‌های شما بیش از حد مجاز است. کمی صبر کنید.']);
        }

        $booking = $booking_id ? $this->get_booking($booking_id) : null;

        // مالکیت نوبت + وضعیت هر دو چک می‌شود: نه مراجع دیگری بتواند رسید
        // برای نوبتِ این کاربر آپلود کند، نه بشود برای نوبتی که اصلاً منتظر
        // تایید واریز نیست (مثلاً از قبل تایید شده یا لغو شده) رسید فرستاد.
        if (!$booking || (int) $booking->user_id !== $user_id) {
            wp_send_json_error(['message' => 'نوبت مورد نظر یافت نشد.']);
        }

        if ($booking->status !== 'در انتظار تایید واریز') {
            wp_send_json_error(['message' => 'این نوبت در وضعیت انتظار تایید واریز نیست.']);
        }

        if (empty($_FILES['receipt']) || !is_array($_FILES['receipt'])) {
            wp_send_json_error(['message' => 'فایلی انتخاب نشده است.']);
        }

        $file = $_FILES['receipt'];

        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            wp_send_json_error(['message' => 'خطا در دریافت فایل. لطفاً دوباره تلاش کنید.']);
        }

        if ((int) ($file['size'] ?? 0) > self::RECEIPT_MAX_BYTES) {
            wp_send_json_error(['message' => 'حجم فایل بیش از حد مجاز (۵ مگابایت) است.']);
        }

        // اعتبارسنجی واقعیِ نوع فایل (نه فقط پسوند/mime ارسالی مرورگر که هر دو
        // قابل‌جعل‌اند) — wp_check_filetype_and_ext خودش محتوای فایل را با
        // فهرست مجاز مقایسه می‌کند.
        $checked = wp_check_filetype_and_ext((string) $file['tmp_name'], (string) $file['name'], self::RECEIPT_ALLOWED_MIME);
        if (empty($checked['ext']) || empty($checked['type'])) {
            wp_send_json_error(['message' => 'فقط فایل تصویری (jpg یا png) پذیرفته می‌شود.']);
        }

        // دفاع دوم، مستقل از wp_check_filetype_and_ext: تایید این‌که فایل
        // واقعاً یک تصویر قابل‌دیکد است (getimagesize محتوای واقعی پیکسل‌ها
        // را می‌خواند، نه فقط هدر/پسوند).
        if (@getimagesize((string) $file['tmp_name']) === false) {
            wp_send_json_error(['message' => 'فایل ارسالی یک تصویر معتبر نیست.']);
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';

        $upload_dir_filter = static function (array $dirs): array {
            $dirs['subdir'] = '/zdp-receipts' . $dirs['subdir'];
            $dirs['path']   = $dirs['basedir'] . $dirs['subdir'];
            $dirs['url']    = $dirs['baseurl'] . $dirs['subdir'];

            return $dirs;
        };

        add_filter('upload_dir', $upload_dir_filter);

        // نام فایل نهایی عمداً کاملاً بی‌ربط به نام اصلی است (شناسه‌ی نوبت +
        // یک توکن رندوم)، نه فقط sanitize_file_name روی نام کاربر — چون
        // آدرس نهایی زیر uploads/ عمومی و قابل‌حدس‌زدن نباید باشد.
        $file['name'] = 'receipt-' . $booking_id . '-' . wp_generate_password(12, false, false) . '.' . $checked['ext'];

        $overrides = [
            'test_form' => false,
            'mimes'     => self::RECEIPT_ALLOWED_MIME,
        ];

        $moved = wp_handle_upload($file, $overrides);
        remove_filter('upload_dir', $upload_dir_filter);

        if (empty($moved['file']) || !empty($moved['error'])) {
            wp_send_json_error(['message' => 'ذخیره‌ی فایل ناموفق بود. لطفاً دوباره تلاش کنید.']);
        }

        // اگر مراجع قبلاً یک‌بار رسید (احتمالاً اشتباه) آپلود کرده بود، فایل
        // قبلی حذف می‌شود تا فایل‌های یتیم روی هاست انباشته نشوند.
        if (!empty($booking->receipt_path)) {
            $old_full_path = trailingslashit(wp_upload_dir()['basedir']) . ltrim((string) $booking->receipt_path, '/');
            if (is_file($old_full_path)) {
                @unlink($old_full_path);
            }
        }

        $upload_basedir = wp_upload_dir()['basedir'];
        $relative_path  = ltrim(str_replace($upload_basedir, '', (string) $moved['file']), '/\\');

        // جلوگیری از directory listing روی پوشه‌ی رسیدها (بسیاری از هاست‌های
        // cPanel/LiteSpeed پیش‌فرض listing را برای پوشه‌های بدون index باز
        // می‌گذارند). یک index.php خالی کافی است؛ چون نام فایل رسید هم رندوم
        // است، این فقط یک لایه‌ی دفاعی اضافه است، نه تنها خط دفاع.
        $receipt_dir = dirname((string) $moved['file']);
        if (!is_file($receipt_dir . '/index.php')) {
            @file_put_contents($receipt_dir . '/index.php', "<?php\n// Silence is golden.\n");
        }

        $this->save_receipt($booking_id, $relative_path);

        wp_send_json_success(['message' => 'رسید با موفقیت آپلود شد و در انتظار تایید ادمین است.']);
    }

    /**
     * منطق واقعیِ ساخت نوبت، مستقل از transport. قبلاً این منطق مستقیماً
     * داخل handle_create_booking (که فقط برای مراجعِ از قبل لاگین‌شده در
     * پنل صدا زده می‌شود) بود. حالا به این متد مستقل منتقل شده تا مسیر دومی
     * هم بتواند از همان منطق (اعتبارسنجی ساعت کاری، قفل هم‌زمانی، قیمت امن
     * سمت سرور، پیامک ادمین، کارت‌به‌کارت/زرین‌پال) استفاده کند: شورت‌کد
     * عمومی نوبت‌دهی روی صفحه‌ی اصلی ([zdp_booking_widget]) که مراجعِ هنوز
     * لاگین‌نکرده یک بازه را انتخاب می‌کند و بلافاصله بعد از تایید کد OTP
     * (همان لحظه‌ای که سرور برای اولین‌بار user_id واقعی را دارد، پیش از
     * این‌که مرورگر اصلاً کوکی ورود را داشته باشد) این متد صدا زده می‌شود —
     * نگاه کن به OTP_Auth::process_verify_otp و فیلتر zdp_otp_login_pending_booking
     * پایین همین فایل.
     *
     * @return array{success: bool, data: array<string, mixed>}
     */
    public function create_booking_internal(int $user_id, array $data): array
    {
        $service_id = (string) ($data['service_id'] ?? '');
        $date       = (string) ($data['booking_date'] ?? '');
        $time       = (string) ($data['booking_time'] ?? '');
        $full_name  = (string) ($data['full_name'] ?? '');

        if (empty($service_id) || empty($date) || empty($time)) {
            return ['success' => false, 'data' => ['message' => 'اطلاعات نوبت ناقص است.']];
        }

        if (mb_strlen($full_name) < 2) {
            return ['success' => false, 'data' => ['message' => 'لطفاً نام و نام‌خانوادگی را وارد کنید.']];
        }

        // نام همراه با نوبت وارد می‌شود ولی متعلق به خودِ مراجع است (نه یک
        // نوبت خاص)، پس در پرونده‌ی کاربر (usermeta) ذخیره می‌شود تا در همه‌جای
        // پنل/پیشخوان یکسان دیده شود. مقدار قبلی در صورت وجود بازنویسی می‌شود
        // چون این تنها فرمی است که خودِ مراجع نامش را واردش می‌کند.
        update_user_meta($user_id, 'zdp_full_name', $full_name);

        // امنیت: نام و قیمت هرگز از کلاینت پذیرفته نمی‌شود — فقط شناسه‌ی خدمت
        // از کاربر گرفته می‌شود و نام/قیمت واقعی از لیست خدمات تعریف‌شده توسط
        // ادمین (zdp_services_list) بازیابی می‌شود. قبلاً price مستقیماً از
        // $_POST خوانده و بدون اعتبارسنجی به درگاه پرداخت فرستاده می‌شد —
        // یعنی هر کاربری می‌توانست با ویرایش درخواست، هر مبلغ دلخواهی
        // (حتی صفر یا منفی) پرداخت کند. این باگ اینجا رفع شده است.
        //
        // نکته: تشخیص service قبل از اعتبارسنجی ساعات‌کاری جابه‌جا شد (قبلاً
        // بعدش بود) چون حالا اعتبارسنجی ساعات‌کاری به نوع خدمت (تلفنی/حضوری)
        // هم نیاز دارد — نگاه کن به کنترل «مدیر می‌تواند مشخص کند این روز
        // فقط تلفنی/حضوری/هردو باشد» در validate_business_hours().
        $service = null;
        foreach ($this->get_services() as $s) {
            if (($s['id'] ?? '') === $service_id) {
                $service = $s;
                break;
            }
        }

        if ($service === null) {
            return ['success' => false, 'data' => ['message' => 'خدمت انتخاب‌شده معتبر نیست.']];
        }

        $service_type = (string) ($service['type'] ?? 'both');

        $hours_error = $this->validate_business_hours($date, $time, $service_type);
        if ($hours_error !== null) {
            return ['success' => false, 'data' => ['message' => $hours_error]];
        }

        $service_name = sanitize_text_field((string) $service['name']);
        $price        = preg_replace('/\D+/', '', (string) $service['price']);

        if ($price === '' || (int) $price <= 0) {
            return ['success' => false, 'data' => ['message' => 'خدمت انتخاب‌شده معتبر نیست.']];
        }

        if (!$this->security->allow('booking_' . $user_id, 5, 300)) {
            return ['success' => false, 'data' => ['message' => 'تعداد درخواست‌های رزرو شما بیش از حد مجاز است. کمی صبر کنید.']];
        }

        global $wpdb;
        $table = Installer::table('bookings');

        $payment_method = get_option('zdp_payment_method', 'zarinpal');
        $initial_status = $payment_method === 'manual' ? 'در انتظار تایید واریز' : 'در انتظار پرداخت';

        // قفل نام‌دار MySQL برای جلوگیری از رزرو هم‌زمان یک بازه‌ی زمانی توسط
        // دو مراجع. برخلاف نسخه‌ی قبلی که با UNIQUE KEY دیتابیس این کار را
        // می‌کرد، این روش وضعیت نوبت‌های لغو/ناموفق را نادیده می‌گیرد — یعنی
        // یک پرداخت شکست‌خورده دیگر آن بازه را برای همیشه قفل نمی‌کند.
        $lock_name = 'zdp_slot_' . md5($date . '_' . $time);
        $got_lock  = (int) $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 5)', $lock_name));

        if ($got_lock !== 1) {
            return ['success' => false, 'data' => ['message' => 'سیستم موقتاً شلوغ است، لطفاً چند ثانیه دیگر دوباره تلاش کنید.']];
        }

        $already_taken = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table WHERE booking_date = %s AND booking_time = %s AND status NOT IN ('ناموفق', 'لغو شده')",
            $date,
            $time
        ));

        if ($already_taken > 0) {
            $wpdb->query($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $lock_name));
            return ['success' => false, 'data' => ['message' => 'این بازه‌ی زمانی هم‌اکنون توسط مراجع دیگری رزرو شد. لطفاً زمان دیگری انتخاب کنید.']];
        }

        $inserted = $wpdb->query($wpdb->prepare(
            "INSERT INTO $table (user_id, service_name, price, booking_date, booking_time, status, created_at)
             VALUES (%d, %s, %s, %s, %s, %s, %s)",
            $user_id,
            $service_name,
            $price,
            $date,
            $time,
            $initial_status,
            now_utc()
        ));

        $wpdb->query($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $lock_name));

        if ($inserted === false) {
            return ['success' => false, 'data' => ['message' => 'خطا در ثبت نوبت. لطفاً دوباره تلاش کنید.']];
        }

        $booking_id = (int) $wpdb->insert_id;

        // اگر این مراجع قبلاً از فانل کوییز عمومی به‌عنوان لید ثبت شده بود،
        // همین‌جا وضعیت «رزرو کرده؟» در تب لیدها به‌روزرسانی می‌شود — قبلاً
        // Leads::mark_booked() اصلاً هیچ‌جا صدا زده نمی‌شد و ستون «رزرو کرده؟»
        // همیشه خالی می‌ماند.
        $client_mobile = zdp_get_user_mobile($user_id);
        if ($client_mobile !== '') {
            $this->leads->mark_booked($client_mobile);
        }

        // ── مسیر کارت‌به‌کارت دستی: بدون درگاه، رزرو فوراً ثبت می‌شود و اطلاعات
        // کارت‌های تعریف‌شده در پیشخوان به مراجع نمایش داده می‌شود؛ مدیر بعداً
        // بعد از دیدن رسید، وضعیت را از تب «نوبت‌ها» به «تایید شده» تغییر می‌دهد.
        if ($payment_method === 'manual') {
            $this->notify_admin_new_booking($service_name, $price, $date, $time);
            audit_log('booking_created_manual_payment', ['booking_id' => $booking_id, 'user_id' => $user_id]);

            $cards = get_option('zdp_manual_payment_cards', []);

            // برای مسیر رزروِ صفحه‌ی اصلی (کاربر لاگین‌نشده که همین لحظه با
            // OTP وارد شد): پنل بلافاصله بعد از این ریدایرکت رندر می‌شود، ولی
            // نتیجه‌ی همین AJAX (شامل کارت‌ها) روی همان صفحه‌ی قبلی می‌ماند و
            // به پنل منتقل نمی‌شود. یک ترنزینت کوتاه‌عمر ذخیره می‌شود تا تب
            // نوبت‌های پنل، فقط برای همان بار اول، جعبه‌ی راهنمای پرداخت را
            // خودش نشان دهد. برای رزرو معمولی از داخل پنل (کاربر از قبل لاگین
            // است) هم بی‌ضرر است، چون آن مسیر خودش با جاوااسکریپت همان لحظه
            // کارت‌ها را نشان می‌دهد و هیچ‌وقت به این ترنزینت نگاه نمی‌کند.
            set_transient('zdp_pending_payment_' . $user_id, [
                'booking_id'   => $booking_id,
                'service_name' => $service_name,
                'price'        => $price,
                'cards'        => $cards,
            ], 15 * MINUTE_IN_SECONDS);

            return [
                'success' => true,
                'data'    => [
                    'manual_payment' => true,
                    'booking_id'     => $booking_id,
                    'cards'          => $cards,
                    'message'        => 'نوبت شما ثبت شد. لطفاً مبلغ را به یکی از کارت‌های زیر واریز کرده و رسید را برای پشتیبانی ارسال کنید.',
                ],
            ];
        }

        $payment = $this->gateway->request_payment(
            (int) $price,
            "پرداخت نوبت {$service_name}",
            add_query_arg(['zdp_payment_callback' => $booking_id], zdp_panel_url())
        );

        if (!$payment['success']) {
            $wpdb->delete($table, ['id' => $booking_id], ['%d']);
            return ['success' => false, 'data' => ['message' => $payment['message'] ?? 'خطا در اتصال به درگاه پرداخت.']];
        }

        $wpdb->update($table, ['authority' => $payment['authority']], ['id' => $booking_id], ['%s'], ['%d']);
        audit_log('booking_payment_started', ['booking_id' => $booking_id, 'user_id' => $user_id]);

        return ['success' => true, 'data' => ['redirect' => $payment['gateway_url']]];
    }

    /**
     * بازگشت از درگاه؛ روی 'init' اجرا می‌شود (نه template_redirect) تا حتی
     * قبل از رندر صفحه هم redirect نهایی انجام شود. پس از تایید/رد پرداخت،
     * همیشه با wp_safe_redirect به صفحه‌ی پنل با یک query flag تمیز هدایت
     * می‌کند (الگوی PRG) — کاربر هرگز مستقیماً روی URL callback زرین‌پال
     * باقی نمی‌ماند، پس رفرش صفحه هرگز callback را دوباره صدا نمی‌زند.
     */
    public function handle_zarinpal_callback(): void
    {
        if (!isset($_GET['zdp_payment_callback'])) {
            return;
        }

        $booking_id = (int) $_GET['zdp_payment_callback'];
        $authority  = isset($_GET['Authority']) ? sanitize_text_field(wp_unslash($_GET['Authority'])) : '';
        $status     = isset($_GET['Status']) ? sanitize_text_field(wp_unslash($_GET['Status'])) : '';

        global $wpdb;
        $table   = Installer::table('bookings');
        $booking = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d AND authority = %s", $booking_id, $authority));

        $redirect_base = zdp_panel_url();

        if (!$booking || $status !== 'OK') {
            if ($booking) {
                $wpdb->update($table, ['status' => 'ناموفق'], ['id' => $booking_id], ['%s'], ['%d']);
                audit_log('booking_payment_cancelled', ['booking_id' => $booking_id]);
            }
            wp_safe_redirect(add_query_arg('zdp_payment', 'failed', $redirect_base));
            exit;
        }

        $verification = $this->gateway->verify_payment($authority, (int) $booking->price);

        if ($verification['success']) {
            $wpdb->update(
                $table,
                ['status' => 'تایید شده', 'ref_id' => $verification['ref_id']],
                ['id' => $booking_id],
                ['%s', '%s'],
                ['%d']
            );
            audit_log('booking_payment_verified', ['booking_id' => $booking_id, 'ref_id' => $verification['ref_id']]);
            $this->notify_client_booking_confirmed((int) $booking->user_id, $verification['ref_id']);
            $this->notify_admin_new_booking($booking->service_name, $booking->price, $booking->booking_date, $booking->booking_time);
            wp_safe_redirect(add_query_arg('zdp_payment', 'success', $redirect_base));
        } else {
            $wpdb->update($table, ['status' => 'ناموفق'], ['id' => $booking_id], ['%s'], ['%d']);
            audit_log('booking_payment_verification_failed', ['booking_id' => $booking_id]);
            wp_safe_redirect(add_query_arg('zdp_payment', 'failed', $redirect_base));
        }

        exit;
    }

    /**
     * اجرای ساعتیِ WP-Cron — نگاه کن به توضیح ثابت PENDING_PAYMENT_EXPIRE_MINUTES
     * و رجیستر شدنِ این هوک در __construct. هر نوبتِ «در انتظار پرداخت»یی که
     * بیش از این مدت از created_at آن گذشته و مراجع هرگز از درگاه برنگشته
     * (یا برگشته ولی handle_zarinpal_callback به هر دلیلی هرگز اجرا نشده،
     * مثلاً بستن مرورگر وسط راه) را 'ناموفق' می‌کند تا بازه‌ی زمانی برای
     * مراجعان دیگر دوباره آزاد شود. عمداً وضعیتِ کارت‌به‌کارتِ «در انتظار تایید
     * واریز» را دست نمی‌زند — آن مسیر تایید دستیِ ادمین دارد و مدت‌زمانش
     * طبیعتاً قابل پیش‌بینی نیست.
     */
    public function expire_stale_pending_bookings(): void
    {
        global $wpdb;
        $table  = Installer::table('bookings');
        $cutoff = gmdate('Y-m-d H:i:s', time() - self::PENDING_PAYMENT_EXPIRE_MINUTES * 60);

        // created_at با now_utc() ذخیره می‌شود (نگاه کن به create_booking_internal)،
        // پس مقایسه هم باید بر همان مبنای UTC انجام شود، نه current_time محلی.
        $expired_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT id FROM $table WHERE status = 'در انتظار پرداخت' AND created_at < %s",
            $cutoff
        ));

        if (empty($expired_ids)) {
            return;
        }

        $ids_sql = implode(',', array_map('intval', $expired_ids));
        // شرط status در UPDATE هم عمداً تکرار شده (نه فقط در SELECT بالا) تا
        // اگر بین این دو کوئری وضعیت نوبتی توسط کاربر تغییر کرده باشد
        // (مثلاً همین لحظه پرداخت را با موفقیت کامل کرد)، این کوئری آن را
        // بازنویسی نکند.
        $wpdb->query("UPDATE $table SET status = 'ناموفق' WHERE id IN ($ids_sql) AND status = 'در انتظار پرداخت'");

        foreach ($expired_ids as $id) {
            audit_log('booking_pending_payment_expired', ['booking_id' => (int) $id]);
        }
    }

    /**
     * پیامک تاییدیه‌ی نوبت برای مراجع — از کد الگوی zdp_sms_booking_body_id.
     *
     * قبلاً نتیجه‌ی apply_filters اصلاً بررسی نمی‌شد — یعنی اگر پیامک fail
     * می‌شد (مثلاً اعتبار ملی‌پیامک تمام شده)، مراجع هیچ‌وقت نمی‌فهمید نوبتش
     * تایید شده، و هیچ‌کس هم متوجه نمی‌شد. حالا اگر ارسال ناموفق باشد، یک
     * ایمیل هشدار به ادمین می‌رود (تنها کانال پشتیبان واقعی، چون ایمیل
     * واقعی مراجع در سیستم ذخیره نمی‌شود — نگاه کن به class-cron.php).
     */
    private function notify_client_booking_confirmed(int $user_id, string $ref_id): void
    {
        // به فرم ارزیابی (Intake) خبر می‌دهد تا اگر مراجع هنوز فرم را پر نکرده، پیامک «فرم مراجع را پر کنید» برود.
        do_action('zdp_booking_confirmed_for_client', $user_id);

        $body_id = get_option('zdp_sms_booking_body_id', '');
        $mobile  = zdp_get_user_mobile($user_id);

        if (empty($body_id) || empty($mobile)) {
            return;
        }

        $sent = apply_filters('pas_send_sms', null, $mobile, $body_id, ['REF' => $ref_id]);

        if (!$sent) {
            $admin_email = get_option('admin_email');
            if ($admin_email) {
                wp_mail(
                    $admin_email,
                    'پیامک تاییدیه‌ی نوبت ارسال نشد',
                    sprintf(
                        "پیامک تاییدیه‌ی نوبت برای مراجع با شماره %s (کد پیگیری: %s) ارسال نشد.\n" .
                        "لطفاً اعتبار پنل ملی‌پیامک را بررسی کن و در صورت نیاز خودت با مراجع تماس بگیر تا از تایید نوبتش مطمئن بشه.",
                        $mobile,
                        $ref_id
                    )
                );
            }
            audit_log('booking_confirmation_sms_failed', ['user_id' => $user_id, 'ref_id' => $ref_id]);
        }
    }

    /** پیامک اطلاع‌رسانی نوبت جدید برای مدیر — از کد الگوی zdp_sms_admin_body_id. */
    private function notify_admin_new_booking(string $service_name, string $price, string $date, string $time): void
    {
        $body_id     = get_option('zdp_sms_admin_body_id', '');
        $admin_mobile = get_option('zdp_admin_mobile', '');

        if (empty($body_id) || empty($admin_mobile)) {
            return;
        }

        apply_filters('pas_send_sms', null, $admin_mobile, $body_id, [
            'SERVICE' => $service_name,
            'PRICE'   => $price,
            'DATE'    => Jalali::gregorian_to_jalali_string($date),
            'TIME'    => $time,
        ]);
    }

    /** لیست خدمات قابل رزرو، از تب «خدمات و پرداخت» در پیشخوان. */
    public function get_services(): array
    {
        $list = get_option('zdp_services_list', []);

        return is_array($list) ? array_values($list) : [];
    }

    /**
     * قالب‌بندی واحد قیمت (تومان) برای همه‌ی نمایش‌ها؛ فقط ارقام نگه داشته می‌شود
     * (ارقام فارسی/جداکننده هم تحمل می‌شود) تا مثلاً «1,500,000» به «1» تبدیل نشود.
     */
    public static function format_price($price): string
    {
        $digits = preg_replace('/\D+/', '', normalize_digits((string) $price));

        return number_format((float) $digits);
    }

    /**
     * [zdp_services_prices] — فهرست خدمات و قیمت‌ها از منبع واحد.
     * پارامترها: type="" | phone | online | in_person ، title="" ، cta="رزرو نوبت" ، link="".
     */
    public function render_services_prices_shortcode($atts = []): string
    {
        wp_enqueue_style('zdp-panel');

        $atts = shortcode_atts(['title' => '', 'type' => '', 'cta' => 'رزرو نوبت', 'link' => ''], (array) $atts, 'zdp_services_prices');
        $type = sanitize_key((string) $atts['type']);

        $all      = array_values(array_filter($this->get_services(), 'is_array'));
        $services = array_values(array_filter($all, static function ($s) use ($type) {
            $t = (string) ($s['type'] ?? 'both');

            return $type === '' || $t === 'both' || $t === $type;
        }));
        if (empty($services)) {
            return '';
        }

        $labels = ['both' => 'تلفنی و حضوری', 'phone' => 'تلفنی', 'online' => 'آنلاین (تصویری)', 'in_person' => 'حضوری'];
        $link   = trim((string) $atts['link']) !== '' ? (string) $atts['link'] : zdp_panel_url();
        // اثر انگشت کوتاهِ لیست فعلی: با مقدار همین صفحه مقایسه کنید تا مشخص شود
        // صفحه از کش قدیمی می‌آید یا لیست واقعاً عوض نشده است.
        $rev = substr(md5((string) wp_json_encode($all)), 0, 8);

        ob_start();
        ?>
        <section class="zdp-services-prices" dir="rtl" data-zdp-services-rev="<?php echo esc_attr($rev); ?>">
            <?php if (trim((string) $atts['title']) !== '') : ?>
                <h3 class="zdp-services-prices-title"><?php echo esc_html((string) $atts['title']); ?></h3>
            <?php endif; ?>
            <div class="zdp-services-grid">
                <?php foreach ($services as $s) :
                    $t = (string) ($s['type'] ?? 'both');
                    ?>
                    <article class="zdp-service-card">
                        <h4 class="zdp-service-name"><?php echo esc_html((string) ($s['name'] ?? '')); ?></h4>
                        <span class="zdp-service-type"><?php echo esc_html($labels[$t] ?? $labels['both']); ?></span>
                        <p class="zdp-service-price"><strong><?php echo esc_html(self::format_price($s['price'] ?? 0)); ?></strong> تومان</p>
                        <a class="zdp-service-cta" href="<?php echo esc_url($link); ?>"><?php echo esc_html((string) $atts['cta']); ?></a>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>
        <?php

        return (string) ob_get_clean();
    }

    public function get_booking(int $booking_id): ?object
    {
        global $wpdb;
        $table = Installer::table('bookings');

        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $booking_id));

        return $row ?: null;
    }

    /**
     * نوبتِ در-انتظار-تایید-واریزِ این کاربر (اگر باشد). تا وقتی ادمین این
     * نوبت را از تب «نوبت‌ها» تایید نکرده، پنل مراجع به‌جای جدول رزرو، همین
     * وضعیت + فرم آپلود رسید را نشان می‌دهد — نگاه کن به
     * Client_Panel::render_appointments_tab. بعد از تایید (status از
     * «در انتظار تایید واریز» خارج می‌شود)، این متد دیگر چیزی برنمی‌گرداند
     * و جدول رزرو برای نوبت بعدی دوباره ظاهر می‌شود.
     */
    public function get_pending_manual_payment_booking(int $user_id): ?object
    {
        global $wpdb;
        $table = Installer::table('bookings');

        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE user_id = %d AND status = 'در انتظار تایید واریز' ORDER BY created_at DESC LIMIT 1",
            $user_id
        ));

        return $row ?: null;
    }

    /** ذخیره‌ی مسیر نسبی (نسبت به uploads/) فایل رسید آپلودشده روی یک نوبت. */
    public function save_receipt(int $booking_id, string $relative_path): void
    {
        global $wpdb;
        $table = Installer::table('bookings');

        $wpdb->update(
            $table,
            ['receipt_path' => $relative_path, 'receipt_uploaded_at' => now_utc()],
            ['id' => $booking_id],
            ['%s', '%s'],
            ['%d']
        );

        audit_log('booking_receipt_uploaded', ['booking_id' => $booking_id]);
    }

    /** ساخت URL کامل و قابل‌نمایش رسید از مسیر نسبی ذخیره‌شده در دیتابیس. */
    public static function receipt_url(object $booking): string
    {
        if (empty($booking->receipt_path)) {
            return '';
        }

        return trailingslashit(wp_upload_dir()['baseurl']) . ltrim((string) $booking->receipt_path, '/');
    }

    public function get_bookings_for_user(int $user_id): array
    {
        global $wpdb;
        $table = Installer::table('bookings');

        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table WHERE user_id = %d ORDER BY booking_date DESC, booking_time DESC",
            $user_id
        ));
    }

    /**
     * لیست ساعت‌های اشغال‌شده‌ی یک روز — برای وقتی که UI نهایی از بازه‌های
     * زمانی ثابت (مثل ۱۰:۰۰، ۱۱:۰۰...) به‌جای input آزاد time استفاده کند و
     * بخواهد ساعت‌های پر را غیرفعال نشان دهد. فعلاً به هیچ AJAX endpoint‌ای
     * وصل نیست چون فرم فعلی پنل از input آزاد time استفاده می‌کند.
     */
    public function get_booked_slots(string $date): array
    {
        global $wpdb;
        $table = Installer::table('bookings');

        return $wpdb->get_col($wpdb->prepare(
            "SELECT booking_time FROM $table WHERE booking_date = %s AND status NOT IN ('ناموفق', 'لغو شده')",
            $date
        ));
    }

    /**
     * جدول بازه‌های خالی/پر برای شورت‌کد عمومی نوبت‌دهی — از امروز به مدت
     * $days روز جلوتر. ستون‌های ساعت بر اساس گسترده‌ترین بازه‌ی کاریِ
     * تعریف‌شده در بین همه‌ی روزهای هفته ساخته می‌شود (تا جدول برای هر روز
     * تعداد ستون یکسان داشته باشد)؛ روزهایی که بازه‌ی کاری کوتاه‌تر یا
     * تعطیل دارند، سلول‌های خارج از بازه‌شان را «غیرفعال» نشان می‌دهند، نه
     * این‌که کل ستون را حذف کنند.
     *
     * هر سلول یکی از این وضعیت‌هاست: 'open' (قابل‌رزرو)، 'taken' (رزروشده)،
     * 'past' (برای امروز، ساعتی که گذشته)، 'closed' (خارج از ساعت کاری/تعطیل).
     *
     * @return array{slot_times: string[], days: array<int, array<string, mixed>>}
     */
    public function get_booking_grid(int $days = self::GRID_DAYS_AHEAD, string $service_type = 'both'): array
    {
        $hours    = get_option('zdp_business_hours', self::default_business_hours());
        $blackout = get_option('zdp_blackout_dates', []);
        $duration = self::SLOT_DURATION_MINUTES;

        // گسترده‌ترین بازه‌ی کاریِ تعریف‌شده در بین روزهای هفته‌ی فعال، برای
        // ساخت ستون‌های مشترک جدول.
        $earliest_start = null;
        $latest_end     = null;
        foreach ($hours as $day_conf) {
            if (empty($day_conf['enabled'])) {
                continue;
            }
            $start = (string) ($day_conf['start'] ?? '09:00');
            $end   = (string) ($day_conf['end'] ?? '21:00');
            if ($earliest_start === null || $start < $earliest_start) {
                $earliest_start = $start;
            }
            if ($latest_end === null || $end > $latest_end) {
                $latest_end = $end;
            }
        }
        if ($earliest_start === null) {
            $earliest_start = '09:00';
            $latest_end     = '21:00';
        }

        // نکته‌ی مهم (طبق توضیح صریح کارفرما): جلسه‌ی بعدی از یک عددِ رند
        // ساعت شروع می‌شود، نه بلافاصله ۴۵ دقیقه بعد از قبلی. یعنی اگر یک
        // جلسه ۹:۰۰ تا ۹:۴۵ باشد، جلسه‌ی بعدی ۱۰:۰۰ شروع می‌شود (نه ۹:۴۵) —
        // همیشه ۱۵ دقیقه فاصله‌ی بین دو جلسه. پس ستون‌های جدول روی عددهای
        // رند ساعت (HH:00) هستند، نه پشتِ‌سرهمِ every-45-minutes. اگر ساعت
        // شروع خودش رند نبود (مثلاً ۰۹:۳۰)، به ساعت رندِ بعدی گرد می‌شود.
        $start_hour = (int) explode(':', $earliest_start)[0];
        if ((int) explode(':', $earliest_start)[1] > 0) {
            $start_hour++;
        }
        $end_ts = strtotime('1970-01-01 ' . $latest_end . ':00');

        $slot_times = [];
        for ($h = $start_hour; $h <= 23; $h++) {
            $slot_start_ts = strtotime(sprintf('1970-01-01 %02d:00:00', $h));
            if ($slot_start_ts + $duration * 60 > $end_ts) {
                break;
            }
            $slot_times[] = sprintf('%02d:00', $h);
        }

        $today    = new \DateTimeImmutable('today', wp_timezone());
        $now_time = current_time('H:i');
        $days_out = [];

        for ($i = 0; $i < $days; $i++) {
            $d          = $today->modify(sprintf('+%d day', $i));
            $date_str   = $d->format('Y-m-d');
            $weekday_no = (int) $d->format('w');
            $day_conf   = $hours[$weekday_no] ?? ['enabled' => false, 'start' => '09:00', 'end' => '21:00', 'mode' => 'both'];
            $day_mode   = (string) ($day_conf['mode'] ?? 'both');

            // آیا نوع خدمتِ انتخاب‌شده اصلاً در این روز مجاز است؟ (تلفنی/آنلاین/حضوری/هردو)
            $mode_mismatch = !self::service_type_matches_day_mode($service_type, $day_mode);
            $closed_day    = in_array($date_str, $blackout, true) || empty($day_conf['enabled']) || $mode_mismatch;

            $booked = [];
            if (!$closed_day) {
                foreach ($this->get_booked_slots($date_str) as $t) {
                    $booked[] = substr((string) $t, 0, 5);
                }
            }

            $slots = [];
            foreach ($slot_times as $time) {
                if ($closed_day) {
                    $slots[$time] = 'closed';
                    continue;
                }
                if ($time < (string) $day_conf['start'] || $time >= (string) $day_conf['end']) {
                    $slots[$time] = 'closed';
                    continue;
                }
                if (self::is_within_break($day_conf, $time)) {
                    $slots[$time] = 'closed';
                    continue;
                }
                if ($i === 0 && $time <= $now_time) {
                    $slots[$time] = 'past';
                    continue;
                }
                $slots[$time] = in_array($time, $booked, true) ? 'taken' : 'open';
            }

            [$jy, $jm, $jd] = Jalali::to_jalali((int) $d->format('Y'), (int) $d->format('n'), (int) $d->format('j'));

            $mode_label = null;
            if ($day_mode === 'phone') {
                $mode_label = 'فقط تلفنی/آنلاین';
            } elseif ($day_mode === 'in_person') {
                $mode_label = 'فقط حضوری';
            }

            $days_out[] = [
                'date'         => $date_str,
                'weekday'      => Jalali::weekday_name($weekday_no),
                'jalali_day'   => $jd,
                'jalali_month' => Jalali::month_name($jm),
                'closed'       => $closed_day,
                'mode_label'   => $mode_label,
                'slots'        => $slots,
            ];
        }

        return ['slot_times' => $slot_times, 'days' => $days_out];
    }

    /**
     * شورت‌کد عمومی [zdp_booking_widget]: جدول ریسپانسیوِ روز×ساعت که هر
     * بازدیدکننده (لاگین یا نه) می‌تواند از آن یک نوبت خالی انتخاب کند. اگر
     * لاگین نباشد، بعد از انتخاب بازه، شماره موبایل + کد تایید پیامکی از او
     * گرفته می‌شود (همان مکانیزم OTP پنل)؛ بلافاصله بعد از تایید کد، خودِ
     * نوبت ثبت می‌شود (نگاه کن به create_booking_from_otp_flow) و مراجع با
     * یک ناوبری واقعی (نه AJAX) وارد پنل می‌شود تا کارت‌های پرداخت را ببیند.
     * تمام رندر HTML اینجا فقط اسکلت است؛ داده‌ی جدول با fetch از
     * zdp/v1/booking-grid توسط panel.js پر می‌شود تا جدول همیشه لحظه‌ای
     * (نه کش‌شده‌ی صفحه) باشد.
     */
    public function render_booking_widget_shortcode($atts = []): string
    {
        wp_enqueue_style('zdp-panel');
        wp_enqueue_script('zdp-panel');

        // پارامتر جدید width: طبق درخواست کارفرما، شورت‌کد روی صفحه‌ی اصلی
        // باید تمام عرض ستون محتوا را بگیرد (نه فقط ۷۶۰ پیکسل وسط‌چین
        // مثل قبل). چون همین شورت‌کد ممکن است جای دیگری (مثلاً کنار
        // متن، در یک ستون باریک‌تر) هم استفاده شود، این رفتار پیش‌فرض
        // عوض نشد — با [zdp_booking_widget width="full"] فعال می‌شود.
        $atts = shortcode_atts(['title' => 'رزرو نوبت آنلاین', 'width' => 'normal'], (array) $atts, 'zdp_booking_widget');

        if (empty($this->get_services())) {
            return '';
        }

        $full_width = strtolower(trim((string) $atts['width'])) === 'full';

        // هم‌سان‌سازی با ظاهر تب «نوبت‌ها»ی داخل پنل (نگاه کن به
        // Client_Panel::render_appointments_tab): آنجا عنوان به‌صورت یک
        // h3 ساده *بالای* کادر (داخل .zdp-card.zdp-card-form) چاپ می‌شود،
        // نه به‌عنوان عنوان تزئینیِ وسط‌چین با خط زیرش که داخل خودِ
        // .zdp-booking-widget (نگاه کن به .zdp-booking-widget-title در
        // panel.css) رندر می‌شد. قبلاً این شورت‌کد عنوان را مستقیم به
        // render_grid_widget_markup می‌داد و همان استایل تزئینی داخل کادر
        // را می‌گرفت؛ یعنی طراحی صفحه‌ی اصلی با طراحی پنل یکی نبود. حالا
        // عنوان بیرون از کادر (در همان قالب .zdp-card.zdp-card-form پنل)
        // چاپ می‌شود و '' به render_grid_widget_markup داده می‌شود تا
        // داخل کادر عنوان تکراری رندر نشود.
        ob_start();
        ?>
        <div class="zdp-card zdp-card-form">
            <?php if ($atts['title'] !== '') : ?>
                <h3><?php echo esc_html($atts['title']); ?></h3>
            <?php endif; ?>
            <?php
            // باگ رفع‌شده («فقط ۱ فیلد به‌جای ۲ فیلد نشان داده می‌شود»):
            // اینجا قبلاً is_user_logged_in() مستقیم موقع رندر HTML بررسی و
            // داخل خروجیِ قابل‌کش (data-logged-in و حذف/نمایش فیلد موبایل و
            // مرحله‌ی OTP) ثابت می‌شد. اگر یک پلاگین کش صفحه (WP Rocket/
            // LiteSpeed و مشابه) همین صفحه را یک‌بار وقتی کاربری لاگین بوده
            // کش کند، همان HTML لاگین‌شده (فقط نام، بدون موبایل/OTP) برای
            // همیشه به تمام بازدیدکننده‌های مهمان نشان داده می‌شد؛ سمت سرور
            // (handle_create_booking) واقعاً is_user_logged_in() را دوباره
            // چک می‌کرد و چون مهمان واقعاً لاگین نبود، درخواست رد می‌شد.
            // اصلاح: این اسکلت HTML همیشه با false (حالت مهمان) رندر
            // می‌شود — دقیقاً همان الگویی که [zdp_client_panel] از قبل
            // استفاده می‌کند — و وضعیت واقعیِ لاگین از طریق همان REST گرید
            // (rest_booking_grid) که panel.js صدا می‌زند تشخیص داده و بعداً
            // UI با جاوااسکریپت تنظیم می‌شود، نه موقع رندر PHP.
            echo $this->render_grid_widget_markup(false, '', '', $full_width);
            ?>
        </div>
        <?php
        return (string) ob_get_clean();
    }

    /**
     * بدنه‌ی مشترک جدول گرید نوبت‌دهی (روز×ساعت). این markup قبلاً فقط
     * داخل render_booking_widget_shortcode (برای صفحه‌ی اصلی سایت) وجود
     * داشت. تب «نوبت‌ها»ی داخل خودِ پنل (Client_Panel::render_appointments_tab)
     * یک فرم کاملاً جدا و قدیمی داشت — یک input[type="time"] آزاد که مراجع
     * می‌توانست هر ساعتی (حتی خارج از ساعت‌کاری/۴۵‌دقیقه‌ای/عدد رند) تایپ
     * کند؛ اعتبارسنجی سمت سرور (validate_business_hours) جلوی ثبت نهایی را
     * می‌گرفت، ولی مراجع تا لحظه‌ی خطا هیچ زمان خالی/پری نمی‌دید. یعنی
     * مراجعِ لاگین‌شده (اکثریت مراجعانِ واقعی، چون بعد از اولین نوبت همیشه
     * از طریق پنل وارد می‌شوند نه دوباره از صفحه‌ی اصلی) هیچ‌وقت جدول جدید
     * را نمی‌دید. این متد استخراج شد تا هر دو مصرف‌کننده (شورت‌کد عمومی و
     * تب پنل) از یک markup و یک فایل JS (initBookingWidget در panel.js،
     * روی سلکتور .zdp-booking-widget) استفاده کنند؛ برای پنل با
     * data-logged-in="1" رندر می‌شود که مرحله‌ی موبایل/OTP را کامل حذف
     * می‌کند و مستقیم دکمه‌ی «ثبت نهایی نوبت» را نشان می‌دهد.
     *
     * منوی انتخاب خدمت (زیربخش «خدمت مورد نظر») هم از همین‌جا برای هر دو
     * مصرف‌کننده به‌صورت گرید کارت‌های انتخابی (radiogroup) رندر می‌شود —
     * نگاه کن به CHANGELOG نسخه‌ی ۱.۹.۱۷.۰.
     *
     * @param string $title      اگر خالی باشد، هیچ h3 عنوانی چاپ نمی‌شود
     *                           (تب پنل خودش قبلاً یک h3 «رزرو نوبت جدید» دارد).
     * @param string $full_name  برای پیش‌پرکردن فیلد نام داخل پنل (از usermeta).
     * @param bool   $full_width اگر true باشد، کلاس‌ اصلاح‌کننده‌ی
     *                           .zdp-booking-widget--full اضافه می‌شود (بدون
     *                           حداکثر عرض ۷۶۰ پیکسلی، تمام عرض کانتینر
     *                           والد را پر می‌کند) — نگاه کن به panel.css.
     */
    public function render_grid_widget_markup(bool $logged_in, string $title = '', string $full_name = '', bool $full_width = false): string
    {
        $services = $this->get_services();
        $widget_class = 'zdp-booking-widget' . ($full_width ? ' zdp-booking-widget--full' : '');

        ob_start();
        ?>
        <div class="<?php echo esc_attr($widget_class); ?>" dir="rtl" data-logged-in="<?php echo $logged_in ? '1' : '0'; ?>">
            <?php if ($title !== '') : ?>
                <h3 class="zdp-booking-widget-title"><?php echo esc_html($title); ?></h3>
            <?php endif; ?>

            <?php
            // بازطراحی به یک دراپ‌داون استاندارد: ویجت شیک قبلی (دکمه+منوی
            // سفارشی روی select مخفی) هم به‌خاطر آی‌دی‌های تکراری بین دو
            // نمونه‌ی ویجت (شورت‌کد عمومی + تب پنل) درست کار نمی‌کرد، هم
            // ظاهرش طبق نظر کارفرما نامناسب بود. الان یک select معمولی و
            // کاملاً بومی مرورگر است — همان استایل استاندارد
            // .zdp-booking-widget select (نگاه کن به panel.css) را می‌گیرد،
            // هیچ منطق بازشو/بسته‌شوی جاوااسکریپتی جداگانه‌ای لازم ندارد و
            // نمی‌تواند بین نمونه‌های مختلف ویجت تداخل کند.
            ?>
            <div class="zdp-bw-field">
                <label for="zdp-bw-service">خدمت مورد نظر</label>
                <select id="zdp-bw-service">
                    <?php foreach ($services as $s) : ?>
                        <option value="<?php echo esc_attr($s['id']); ?>">
                            <?php echo esc_html($s['name'] . ' — ' . self::format_price($s['price']) . ' تومان'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <?php
            // یادداشت قوانین رزرو نوبت (طبق درخواست صریح کارفرما): مدت جلسه،
            // مسئولیت تماس تلفنی، مهلت جابه‌جایی، و محرمانگی. عمداً به‌عنوان
            // یک آرایه‌ی خطوط جداگانه نگه داشته شده (نه یک بلوک HTML خام) تا
            // هر خط از esc_html عبور کند و ویرایش/اضافه‌کردن خط جدید بعداً
            // ساده باشد.
            $bw_notice_lines = [
                ['icon' => 'clock',   'text' => 'مدت هر جلسه ۴۵ دقیقه است.'],
                ['icon' => 'phone',   'text' => 'برای جلسات تلفنی، تماس رأس ساعت انتخاب‌شده از طرف مراجع برقرار می‌شود.'],
                ['icon' => 'refresh', 'text' => 'برای جابه‌جایی نوبت، حداقل تا ۲۴ ساعت قبل با پشتیبانی هماهنگ کنید.'],
                ['icon' => 'lock',    'text' => 'تمام جلسات و اطلاعات شما کاملاً محرمانه است.'],
            ];
            ?>
            <ul class="zdp-bw-notice">
                <?php foreach ($bw_notice_lines as $line) : ?>
                    <li><span class="zdp-icon-inline"><?php echo zdp_icon($line['icon'], 16); ?></span><?php echo esc_html($line['text']); ?></li>
                <?php endforeach; ?>
            </ul>

            <div class="zdp-bw-legend" id="zdp-bw-legend">
                <span class="zdp-bw-legend-chip zdp-bw-legend-open"><i class="zdp-bw-dot zdp-bw-dot-open"></i> خالی</span>
                <span class="zdp-bw-legend-chip zdp-bw-legend-taken"><i class="zdp-bw-dot zdp-bw-dot-taken"></i> رزروشده</span>
                <span class="zdp-bw-legend-chip zdp-bw-legend-closed"><i class="zdp-bw-dot zdp-bw-dot-closed"></i> گذشته</span>
            </div>

            <div class="zdp-bw-quickfilters" id="zdp-bw-quickfilters" hidden>
                <button type="button" class="zdp-bw-qf-btn" data-qf="today">امروز</button>
                <button type="button" class="zdp-bw-qf-btn" data-qf="tomorrow">فردا</button>
            </div>

            <div class="zdp-bw-grid-wrap">
                <div class="zdp-bw-grid-loading" id="zdp-bw-skeleton" role="status" aria-live="polite"></div>
                <!-- طبق درخواست کارفرما، جدول/گرید هفتگی کاملاً حذف شد؛ مراجع
                     همیشه نمای «یک‌روزه» (renderDayView در panel.js) را می‌بیند
                     و با دکمه‌های ‹ › روز قبل/بعد (یا فیلترهای سریع امروز/فردا)
                     بین روزها جابه‌جا می‌شود. -->
                <div class="zdp-bw-day-view" id="zdp-bw-day-view" hidden></div>
            </div>

            <div class="zdp-bw-confirm" id="zdp-bw-confirm" hidden>
                <p class="zdp-bw-selected-summary" id="zdp-bw-selected-summary"></p>

                <div class="zdp-bw-step" data-step="name">
                    <label for="zdp-bw-fullname">نام و نام‌خانوادگی</label>
                    <input type="text" id="zdp-bw-fullname" autocomplete="name" value="<?php echo esc_attr($full_name); ?>" placeholder="مثلاً: زهرا احمدی">

                    <?php
                    // هر دو بلوکِ «مهمان» (موبایل+OTP) و «لاگین‌شده» (دکمه‌ی
                    // مستقیم) همیشه در HTML رندر می‌شوند (نه فقط یکی بر اساس
                    // $logged_in) تا وقتی این markup برای شورت‌کد عمومی صفحه‌ی
                    // اصلی استفاده می‌شود (که همیشه با $logged_in=false صدا
                    // زده می‌شود، نگاه کن به render_booking_widget_shortcode)،
                    // جاوااسکریپت بتواند بعد از تشخیص واقعیِ وضعیت لاگین
                    // (از REST، نه از این HTML کش‌شده) بین‌شان سوییچ کند —
                    // بدون نیاز به تزریق HTML جدید. حالت hidden زیر فقط
                    // پیش‌فرضِ نمایش (قبل از پاسخ REST) را تعیین می‌کند.
                    ?>
                    <?php
                    // تغییر رفتار (طبق درخواست صریح کارفرما): مراجعِ مهمان
                    // دیگر موبایل و کد تایید را همین‌جا داخل شورت‌کد وارد
                    // نمی‌کند. بعد از انتخاب زمان + نام، با همین یک دکمه به
                    // صفحه‌ی واقعیِ ورود پنل (/panel/) هدایت می‌شود و آنجا،
                    // در همان فرم استاندارد ورودِ پنل، شماره موبایل و کد
                    // تایید را وارد می‌کند. انتخاب خدمت/تاریخ/ساعت/نام از
                    // طریق querystring همراه او می‌رود (نگاه کن به هندلر
                    // کلیک #zdp-bw-goto-panel در panel.js) و سمت پنل، بعد از تایید
                    // موفق کد، همان نوبت خودکار ثبت می‌شود (همان مکانیزم
                    // filter موجود zdp_otp_login_pending_booking که پیش‌تر
                    // هم برای رزرو لاگین‌شده استفاده می‌شد — نگاه کن به
                    // Client_Panel::render_login_form / initLoginForm).
                    ?>
                    <div class="zdp-bw-guest-fields" <?php echo $logged_in ? 'hidden' : ''; ?>>
                        <button type="button" id="zdp-bw-goto-panel" class="zdp-btn-primary">
                            <span class="zdp-btn-label">ادامه و ورود به پنل</span>
                            <span class="zdp-btn-spinner" aria-hidden="true"></span>
                        </button>
                    </div>
                    <button type="button" id="zdp-bw-book-loggedin" class="zdp-btn-primary" <?php echo $logged_in ? '' : 'hidden'; ?>>
                        <span class="zdp-btn-label">ثبت نهایی نوبت</span>
                        <span class="zdp-btn-spinner" aria-hidden="true"></span>
                    </button>
                </div>

                <p id="zdp-bw-result" role="status" aria-live="polite"></p>
            </div>
        </div>
        <?php
        return (string) ob_get_clean();
    }

    /**
     * برای پیشخوان ادمین: همه‌ی نوبت‌ها به‌همراه نام و موبایل مراجع، بدون N+1.
     * $search (اختیاری) روی نام کامل (usermeta zdp_full_name)، شماره موبایل،
     * یا کد پیگیری (b.ref_id) فیلتر می‌کند — برای جدول مدیریت نوبت‌ها
     * (جست‌وجو/فیلتر).
     *
     * باگ رفع‌شده: کد پیگیری (هم ref_id واقعیِ زرین‌پال، هم کدِ DP-XXXXXX
     * ساخته‌شده برای تایید دستیِ کارت‌به‌کارت — نگاه کن به
     * generate_manual_tracking_code) در پیامکِ تاییدیه به مراجع فرستاده
     * می‌شد، ولی تا این نسخه هیچ‌جای پیشخوان قابل‌جست‌وجو نبود؛ یعنی وقتی
     * مراجع تلفنی کدش را می‌گفت، پشتیبانی هیچ راهی برای پیدا کردن نوبت با
     * همان کد نداشت. حالا ref_id هم به شرط جست‌وجو اضافه شده است.
     */
    public function get_all_bookings_with_client_names(int $limit = 100, string $search = ''): array
    {
        global $wpdb;
        $table      = Installer::table('bookings');
        $users_meta = $wpdb->usermeta;

        // اولویت با cellphone (کلید مشترک اکوسیستم)؛ اگر روی یک کاربرِ قدیمی
        // هنوز ست نشده، از zdp_mobile به‌عنوان fallback استفاده می‌شود.
        $sql = "SELECT b.*, COALESCE(um_c.meta_value, um_z.meta_value) AS mobile, um_name.meta_value AS full_name
             FROM $table b
             LEFT JOIN $users_meta um_c ON um_c.user_id = b.user_id AND um_c.meta_key = 'cellphone'
             LEFT JOIN $users_meta um_z ON um_z.user_id = b.user_id AND um_z.meta_key = 'zdp_mobile'
             LEFT JOIN $users_meta um_name ON um_name.user_id = b.user_id AND um_name.meta_key = 'zdp_full_name'";

        $params = [];
        if ($search !== '') {
            $sql     .= " WHERE um_name.meta_value LIKE %s OR um_c.meta_value LIKE %s OR um_z.meta_value LIKE %s OR b.ref_id LIKE %s";
            $like     = '%' . $wpdb->esc_like($search) . '%';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        $sql     .= " ORDER BY b.created_at DESC LIMIT %d";
        $params[] = $limit;

        return $wpdb->get_results($wpdb->prepare($sql, $params));
    }

    public function update_status(int $booking_id, string $status): void
    {
        global $wpdb;
        $table = Installer::table('bookings');
        $wpdb->update($table, ['status' => $status], ['id' => $booking_id], ['%s'], ['%d']);
        audit_log('booking_status_changed_by_admin', ['booking_id' => $booking_id, 'status' => $status]);

        // اگر مدیر یک نوبت کارت‌به‌کارت را دستی «تایید شده» کرد، همان پیامک
        // تاییدیه‌ای که در مسیر زرین‌پال خودکار ارسال می‌شود، اینجا هم می‌رود
        // تا مراجع بی‌خبر نماند.
        //
        // باگ رفع‌شده («کد رهگیری تولید نمی‌شود»): ستون ref_id فقط در مسیر
        // خودکار زرین‌پال (بعد از verify_payment) پر می‌شد. برای تایید دستیِ
        // کارت‌به‌کارت، ref_id هیچ‌وقت مقداردهی نمی‌شد و اینجا قبلاً به‌جای آن
        // یک خط‌تیره‌ی ثابت ('—') در پیامک مراجع می‌رفت — یعنی مراجع هیچ‌وقت
        // کد پیگیری واقعی دریافت نمی‌کرد. حالا اگر ref_id هنوز خالی باشد، یک
        // کد پیگیری منحصربه‌فرد همین‌جا ساخته و در دیتابیس ذخیره می‌شود، دقیقاً
        // مثل ref_id واقعی زرین‌پال، پیش از ارسال پیامک.
        if ($status === 'تایید شده') {
            $booking = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $booking_id));
            if ($booking) {
                $ref_id = (string) $booking->ref_id;
                if ($ref_id === '') {
                    $ref_id = $this->generate_manual_tracking_code();
                    $wpdb->update($table, ['ref_id' => $ref_id], ['id' => $booking_id], ['%s'], ['%d']);
                    audit_log('booking_manual_ref_id_generated', ['booking_id' => $booking_id, 'ref_id' => $ref_id]);
                }
                $this->notify_client_booking_confirmed((int) $booking->user_id, $ref_id);
            }
        }
    }

    /** کانال‌های مجاز برای «نحوه‌ی رزرو» نوبت‌های ثبت‌شده توسط درمانگر. */
    public const MANUAL_SOURCES = ['phone' => 'تلفنی', 'whatsapp' => 'واتساپ', 'instagram' => 'اینستاگرام', 'in_person' => 'حضوری', 'other' => 'سایر'];

    /**
     * همان MANUAL_SOURCES به‌علاوه‌ی «سایت» — برای جایی که نوبت‌های خودِ سایت
     * (رزرو مستقیم توسط مراجع) هم باید کنار نوبت‌های دستی نمایش/ویرایش شوند
     * (جدول مدیریت نوبت‌ها). فرم «ثبت نوبت جدید» عمداً فقط از MANUAL_SOURCES
     * استفاده می‌کند چون درمانگر هیچ‌وقت یک نوبت دستی را «سایت» علامت نمی‌زند.
     */
    public const ALL_SOURCES = ['site' => 'سایت'] + self::MANUAL_SOURCES;

    /** انواع مجاز جلسه برای نوبت دستی. */
    public const SESSION_TYPES = ['in_person' => 'حضوری', 'online' => 'آنلاین', 'phone' => 'تلفنی'];

    /** وضعیت‌های مجاز نوبت‌های دستی (علاوه بر وضعیت‌های مسیر پرداخت آنلاین). */
    public const MANUAL_STATUSES = ['فعال', 'لغو شده', 'انجام شده', 'عدم حضور'];

    /**
     * ثبت نوبتی که خارج از سایت رزرو شده (تلفنی، واتساپ، اینستاگرام، حضوری)
     * و درمانگر خودش آن را در پیشخوان وارد می‌کند. برخلاف create_booking_internal
     * این مسیر هیچ درگاه پرداخت/قفل هم‌زمانی ندارد — درمانگر خودش مسئول عدم
     * تداخل زمانی است، اما برای جلوگیری از خطای ساده یک هشدار نرم (نه رد
     * قطعی) در صورت تداخل با نوبت فعال دیگری برگردانده می‌شود.
     *
     * @return array{success: bool, data: array<string, mixed>}
     */
    public function create_manual_booking(array $data): array
    {
        $full_name = sanitize_text_field((string) ($data['full_name'] ?? ''));
        $mobile    = to_local_format(sanitize_text_field((string) ($data['mobile'] ?? '')));
        $date      = sanitize_text_field((string) ($data['booking_date'] ?? ''));
        $time      = sanitize_text_field((string) ($data['booking_time'] ?? ''));
        $session   = sanitize_text_field((string) ($data['session_type'] ?? ''));
        $source    = sanitize_text_field((string) ($data['booking_source'] ?? 'other'));
        $note      = sanitize_textarea_field((string) ($data['therapist_note'] ?? ''));

        if (mb_strlen($full_name) < 2) {
            return ['success' => false, 'data' => ['message' => 'لطفاً نام و نام‌خانوادگی مراجع را وارد کنید.']];
        }
        if (!is_valid_mobile($mobile)) {
            return ['success' => false, 'data' => ['message' => 'شماره موبایل معتبر نیست.']];
        }
        if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $date, $dm) || !checkdate((int) $dm[2], (int) $dm[3], (int) $dm[1])
            || !preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $time)) {
            return ['success' => false, 'data' => ['message' => 'تاریخ یا ساعت جلسه نامعتبر است.']];
        }
        if (!array_key_exists($session, self::SESSION_TYPES)) {
            return ['success' => false, 'data' => ['message' => 'نوع جلسه نامعتبر است.']];
        }
        if (!array_key_exists($source, self::MANUAL_SOURCES)) {
            $source = 'other';
        }

        $user_id = zdp_find_or_create_user_by_mobile($mobile);
        if (is_wp_error($user_id)) {
            return ['success' => false, 'data' => ['message' => 'ساخت/یافتن پرونده‌ی مراجع ناموفق بود: ' . $user_id->get_error_message()]];
        }

        update_user_meta($user_id, 'zdp_full_name', $full_name);
        zdp_set_user_mobile($user_id, $mobile);

        global $wpdb;
        $table = Installer::table('bookings');

        $conflict = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table WHERE booking_date = %s AND booking_time = %s AND status NOT IN ('ناموفق', 'لغو شده')",
            $date,
            $time
        ));

        $inserted = $wpdb->query($wpdb->prepare(
            "INSERT INTO $table
             (user_id, service_name, price, booking_date, booking_time, status, booking_source, session_type, therapist_note, created_at)
             VALUES (%d, %s, %s, %s, %s, %s, %s, %s, %s, %s)",
            $user_id,
            self::SESSION_TYPES[$session] . ' (ثبت دستی)',
            '0',
            $date,
            $time,
            'فعال',
            $source,
            $session,
            $note,
            now_utc()
        ));

        if ($inserted === false) {
            return ['success' => false, 'data' => ['message' => 'خطا در ثبت نوبت.']];
        }

        $booking_id = (int) $wpdb->insert_id;
        audit_log('manual_booking_created', ['booking_id' => $booking_id, 'user_id' => $user_id, 'source' => $source]);

        $message = 'نوبت با موفقیت ثبت شد.';
        if ($conflict > 0) {
            $message .= ' توجه: در همین بازه‌ی زمانی، نوبت فعال دیگری هم ثبت شده بود — لطفاً بررسی کنید.';
        }

        return ['success' => true, 'data' => ['message' => $message, 'booking_id' => $booking_id]];
    }

    /**
     * ویرایش نوبت توسط درمانگر: تاریخ/ساعت/نوع جلسه/نحوه‌ی رزرو/یادداشت/وضعیت.
     * فقط فیلدهای ارسال‌شده در $fields به‌روزرسانی می‌شوند. اگر تاریخ یا ساعت
     * تغییر کند، reminder_sent_at پاک می‌شود تا اگر جلسه‌ی جابه‌جاشده هنوز در
     * آینده باشد، یادآوری برایش دوباره ارسال شود.
     */
    public function update_booking_fields(int $booking_id, array $fields): bool
    {
        global $wpdb;
        $table = Installer::table('bookings');

        $data   = [];
        $format = [];

        if (isset($fields['booking_date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $fields['booking_date'])) {
            $data['booking_date'] = $fields['booking_date'];
            $format[]              = '%s';
        }
        if (isset($fields['booking_time']) && preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', (string) $fields['booking_time'])) {
            $data['booking_time'] = $fields['booking_time'];
            $format[]              = '%s';
        }
        if (isset($fields['session_type']) && array_key_exists($fields['session_type'], self::SESSION_TYPES)) {
            $data['session_type'] = $fields['session_type'];
            $format[]              = '%s';
        }
        if (isset($fields['booking_source']) && array_key_exists($fields['booking_source'], self::ALL_SOURCES)) {
            $data['booking_source'] = $fields['booking_source'];
            $format[]                = '%s';
        }
        if (isset($fields['therapist_note'])) {
            $data['therapist_note'] = sanitize_textarea_field((string) $fields['therapist_note']);
            $format[]                = '%s';
        }
        if (isset($fields['status'])) {
            $data['status'] = sanitize_text_field((string) $fields['status']);
            $format[]        = '%s';
        }

        if (empty($data)) {
            return false;
        }

        if (isset($data['booking_date']) || isset($data['booking_time'])) {
            // wpdb->update مقدار null را صرف‌نظر از placeholder فرمت، به‌درستی
            // به‌صورت SQL NULL می‌نویسد؛ پس همان '%s' هم برای این ستون کافی است.
            $data['reminder_sent_at'] = null;
            $format[]                  = '%s';
        }

        $updated = $wpdb->update($table, $data, ['id' => $booking_id], $format, ['%d']);
        if ($updated !== false) {
            audit_log('booking_edited_by_therapist', ['booking_id' => $booking_id, 'fields' => array_keys($data)]);
        }

        return $updated !== false;
    }

    /**
     * نوبت‌های فعالی که ساعت جلسه‌شان در بازه‌ی «الان تا X ساعت دیگر» است و
     * هنوز یادآوری برایشان ارسال نشده — برای کرون یادآوری خودکار. مقایسه بر
     * اساس وقت محلی سایت (wp_timezone) انجام می‌شود چون booking_date/time
     * ساعت واقعی جلسه به وقت کلینیک است، نه UTC.
     */
    public function get_bookings_needing_reminder(int $hours_before): array
    {
        global $wpdb;
        $table = Installer::table('bookings');

        $now    = new \DateTimeImmutable('now', wp_timezone());
        $window = $now->modify(sprintf('+%d hours', max(1, $hours_before)));

        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table
             WHERE status IN ('فعال', 'تایید شده')
               AND reminder_sent_at IS NULL
               AND TIMESTAMP(booking_date, booking_time) BETWEEN %s AND %s",
            $now->format('Y-m-d H:i:s'),
            $window->format('Y-m-d H:i:s')
        ));
    }

    public function mark_reminder_sent(int $booking_id): void
    {
        global $wpdb;
        $wpdb->update(Installer::table('bookings'), ['reminder_sent_at' => now_utc()], ['id' => $booking_id], ['%s'], ['%d']);
    }

    /**
     * حذف کامل یک نوبت. برخلاف تغییر وضعیت به «لغو شده» (که رکورد را برای
     * تاریخچه/لاگ نگه می‌دارد)، این متد ردیف را کاملاً از دیتابیس پاک می‌کند —
     * برای مواردی مثل نوبتِ دستیِ اشتباه‌ثبت‌شده که اصلاً نباید در گزارش‌ها
     * بماند. توصیه: برای نوبت‌های واقعی که فقط لغو شده‌اند، ترجیحاً از تغییر
     * وضعیت استفاده شود، نه حذف؛ حذف بیشتر برای پاک‌سازی خطای ورود داده است.
     */
    public function delete_booking(int $booking_id): bool
    {
        global $wpdb;
        $deleted = $wpdb->delete(Installer::table('bookings'), ['id' => $booking_id], ['%d']);
        if ($deleted) {
            audit_log('booking_deleted_by_therapist', ['booking_id' => $booking_id]);
        }

        return (bool) $deleted;
    }

    /**
     * کد پیگیریِ نوبت‌های تایید دستی (کارت‌به‌کارت) — چون این پرداخت‌ها از
     * هیچ درگاهی عبور نمی‌کنند، شماره‌ی تراکنش واقعی وجود ندارد؛ این کد فقط
     * برای این است که مراجع (و پشتیبانی) یک مرجع کوتاه و قابل‌جست‌وجو برای
     * پیگیری نوبت داشته باشند، نه یک شناسه‌ی امنیتی. فرمت: «DP-» + ۶ کاراکتر
     * حروف‌بزرگ/عدد (چیزی که تلفنی/پیامکی راحت خوانده و گفته شود).
     */
    private function generate_manual_tracking_code(): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; // بدون حروف/ارقام مشابه (O/0، I/1)
        $code     = '';
        for ($i = 0; $i < 6; $i++) {
            $code .= $alphabet[wp_rand(0, strlen($alphabet) - 1)];
        }

        return 'DP-' . $code;
    }
}
