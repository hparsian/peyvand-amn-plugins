<?php

declare(strict_types=1);

namespace ZoojDarman\ClientPortal;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * تبدیل امن یک متن وضعیت (status) به اسلاگ برای استفاده در نام کلاس CSS.
 *
 * چرا نه sanitize_html_class(): آن تابع هسته‌ی وردپرس هر کاراکتر غیر ASCII
 * را کامل حذف می‌کند (رجکس /[^A-Za-z0-9_-]/)، یعنی روی متن فارسی («تایید
 * شده» و مشابه) همیشه یک رشته‌ی خالی یا تقریباً خالی برمی‌گرداند. نتیجه‌ی
 * این باگ در این پروژه: کلاس‌هایی مثل `.zdp-status-تایید-شده` که در
 * panel.css/admin.css تعریف شده بودند، هرگز با HTML واقعیِ خروجی‌گرفته از
 * sanitize_html_class() مچ نمی‌شدند — کل سیستم رنگ‌بندی وضعیت (چه در پنل
 * مراجع، چه در پیشخوان مدیریت) از ابتدا بی‌اثر بود، بدون هیچ خطای قابل‌دیدن
 * (فقط بج‌ها بی‌رنگ می‌ماندند).
 *
 * چرا این تابع امن است بدون حذف کاراکتر فارسی: status همیشه از یک لیست
 * ثابتِ هاردکد در کد می‌آید (نگاه کن به Admin::ALL_BOOKING_STATUSES و
 * Booking::MANUAL_STATUSES)، نه از ورودی مستقیم کاربر؛ پس نیازی به حذف
 * تهاجمی کاراکترها نیست. فقط فاصله با خط‌تیره جایگزین می‌شود تا با نام
 * کلاس‌های موجود در CSS یکی شود. در نقطه‌ی echo همیشه باید با esc_attr()
 * پیچیده شود — این تابع خودش escape نمی‌کند.
 */
function zdp_status_slug(string $status): string
{
    return trim(str_replace(' ', '-', $status));
}

/**
 * تبدیل ارقام فارسی/عربی (۰۱۲۳۴۵۶۷۸۹ / ٠١٢٣٤٥٦٧٨٩) به ارقام لاتین معمولی.
 *
 * چرا لازم است: کیبورد پیش‌فرض بسیاری از گوشی‌های اندرویدی/آیفون در حالت
 * زبان فارسی، ارقام را به‌صورت فارسی وارد می‌کند. اگر این تبدیل روی مقادیر
 * عددیِ حساس (به‌خصوص کد OTP) انجام نشود، مقایسه‌ی رشته‌ای (hash_equals) با
 * کدِ لاتینِ ذخیره‌شده هرگز true نمی‌شود — یعنی کاربر کد را دقیقاً مطابق
 * پیامک وارد می‌کند ولی سیستم همیشه «کد اشتباه است» نشان می‌دهد، چون واقعاً
 * چیز دیگری (ارقام فارسی) با چیز دیگری (ارقام لاتین) مقایسه می‌شود.
 * سمت کلاینت (panel.js) از قبل تایپ دستی را تبدیل می‌کند، اما این تابع سمت
 * سرور هم لازم است: هم به‌عنوان لایه‌ی دوم دفاعی (اگر جایی کش قدیمیِ JS باز
 * مانده باشد)، هم برای مسیر REST/AJAX که می‌تواند مستقیماً (بدون فرم) صدا
 * زده شود.
 */
function normalize_digits(string $value): string
{
    static $persian = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
    static $arabic  = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];
    static $latin   = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];

    $value = str_replace($persian, $latin, $value);
    $value = str_replace($arabic, $latin, $value);

    return $value;
}

/**
 * نرمال‌سازی شماره موبایل ایرانی به فرمت محلی یکتای 09XXXXXXXXX.
 * ورودی‌های قابل قبول: 09xxxxxxxxx، 9xxxxxxxxx، +989xxxxxxxxx، 00989xxxxxxxxx،
 * با یا بدون فاصله/خط‌تیره. همان تابع مرجع که psy-quiz-enterprise هم صدا می‌زند
 * (نگاشت مستقیم به to_local_format شناخته‌شده در قرارداد بین‌افزونه‌ای).
 */
function to_local_format(string $mobile): string
{
    // ارقام فارسی/عربی هم قبل از هر پردازش دیگری به لاتین تبدیل شوند —
    // preg_replace('/\D+/', ...) در ادامه (چه اینجا، چه در
    // Pas_Core_Helpers::normalize_mobile) ارقام یونیکد فارسی/عربی را «رقم»
    // نمی‌شناسد و آن‌ها را کامل حذف می‌کرد، نه اینکه به لاتین تبدیل کند.
    $mobile = normalize_digits($mobile);

    // شماره‌ی صریحاً خارجی (با + یا 00 و کد کشور غیر ۹۸) همیشه با منطق
    // محلی همین تابع پردازش می‌شود — مستقل از اینکه Pas_Core_Helpers روی
    // سایت وجود دارد یا نه. آن کلاس متعلق به یک پلاگین قدیمی‌تر اکوسیستم
    // است که از قبل از افزوده‌شدنِ پشتیبانی شماره‌ی خارجی وجود داشته و فقط
    // فرمت‌های ایرانی را می‌شناسد؛ اگر اجازه می‌دادیم شماره‌ی خارجی هم به آن
    // سپرده شود، خروجی نامعتبر/خالی برمی‌گشت (همان باگِ «شماره‌ی خارج از
    // کشور رد می‌شود» که در psy-quiz-enterprise هم پیدا و همین‌طور رفع شد).
    // برای شماره‌های ایرانی همچنان به Pas_Core_Helpers سپرده می‌شود تا
    // سازگاری با بقیه‌ی اکوسیستم حفظ شود.
    $intl_only = to_local_format_intl_only($mobile);
    if ($intl_only !== null) {
        return $intl_only;
    }

    if (class_exists('Pas_Core_Helpers')) {
        return \Pas_Core_Helpers::normalize_mobile($mobile);
    }

    $trimmed  = trim($mobile);
    $has_plus = str_starts_with($trimmed, '+');
    $digits   = preg_replace('/\D+/', '', $mobile) ?? '';

    // شماره‌ی خارج از کشور: کاربر باید صراحتاً با «+» یا «00» شروع کرده
    // باشد (استاندارد بین‌المللی رایج شماره‌گیری). بدون یکی از این دو
    // پیشوند صریح، امکان تشخیص قطعی «این یک شماره‌ی ایرانی ناقص است یا یک
    // شماره‌ی خارجی» وجود ندارد، پس آن حالت مبهم همچنان مسیر ایرانی زیر را
    // طی می‌کند (سازگاری کامل با رفتار قبلی).
    if ($has_plus || str_starts_with($digits, '00')) {
        $intl_digits = $has_plus ? $digits : substr($digits, 2);

        // کد کشور 98 صریح (+98... یا 0098...) همچنان باید به فرمت محلی
        // 09xxxxxxxxx تبدیل شود تا با بقیه‌ی اکوسیستم (که همیشه cellphone
        // ایرانی را به این شکل ذخیره/جستجو می‌کند) یکدست بماند.
        if (str_starts_with($intl_digits, '98')) {
            $digits = $intl_digits;
        } else {
            // شماره‌ی واقعاً خارجی: به فرمت E.164 (+<کدکشور><شماره>) ذخیره
            // می‌شود، نه فرمت محلی ایرانی 09xxxxxxxxx که برای این شماره‌ها
            // بی‌معنی است.
            return $intl_digits !== '' ? ('+' . $intl_digits) : '';
        }
    }

    // پوسته‌کنی مکرر پیشوند کد کشور/صفر اضافه تا زمانی که فقط شماره‌ی
    // ۱۰رقمیِ خالص (9xxxxxxxxx) باقی بماند. حالت قبلی فقط یک بار چک
    // می‌کرد، پس ورودی‌هایی مثل «0989123456789» (صفر اشتباهیِ اضافه قبل
    // از 98) که با «0» شروع می‌شدند ولی «0098» نبودند، هرگز کد کشور
    // تودرتوی 98 را نمی‌دیدند و شماره‌ی نهایی نادرست می‌ماند.
    while (strlen($digits) > 10) {
        if (str_starts_with($digits, '98')) {
            $digits = substr($digits, 2);
        } elseif (str_starts_with($digits, '0')) {
            $digits = substr($digits, 1);
        } else {
            break;
        }
    }

    return '0' . $digits;
}

/**
 * اگر ورودی صراحتاً یک شماره‌ی خارجی باشد (با «+» یا «00» شروع شده و کد
 * کشورش ۹۸ نیست)، فرمت E.164 آن را برمی‌گرداند؛ در غیر این صورت null
 * برمی‌گرداند تا to_local_format طبق روال قبلی‌اش (شامل تفویض احتمالی به
 * Pas_Core_Helpers برای شماره‌های ایرانی) تصمیم بگیرد.
 */
function to_local_format_intl_only(string $mobile): ?string
{
    $trimmed  = trim($mobile);
    $has_plus = str_starts_with($trimmed, '+');
    $digits   = preg_replace('/\D+/', '', $mobile) ?? '';

    if (!$has_plus && !str_starts_with($digits, '00')) {
        return null;
    }

    $intl_digits = $has_plus ? $digits : substr($digits, 2);

    if ($intl_digits === '' || str_starts_with($intl_digits, '98')) {
        return null;
    }

    return '+' . $intl_digits;
}

function is_valid_mobile(string $mobile): bool
{
    if (preg_match('/^09[0-9]{9}$/', $mobile)) {
        return true;
    }

    // شماره‌ی بین‌المللی، خروجی to_local_format() برای شماره‌های غیرایرانی:
    // فرمت E.164 یعنی «+» به‌همراه ۸ تا ۱۵ رقم، بدون صفر در ابتدای کد کشور.
    return (bool) preg_match('/^\+[1-9][0-9]{7,14}$/', $mobile);
}

/**
 * شماره موبایلِ متصل به یک ایمیل — از طریق فیلتر مشترک اکوسیستم
 * psy_get_mobile_for_email (پیاده‌شده در psy-quiz-enterprise::Invitation_Manager).
 * عمداً این افزونه خودش هیچ جدولی برای نگهداری email↔mobile ندارد؛ ایمیل
 * همیشه همان‌جایی ثبت می‌شود که درمانگر آزمون را برای مراجع تعریف می‌کند —
 * این تابع فقط آن رابطه‌ی از‌قبل‌ثبت‌شده را می‌خواند، هرگز چیزی نمی‌نویسد.
 * اگر هیچ پلاگینی این فیلتر را پیاده نکرده باشد (یا ایمیل برای هیچ
 * دعوت‌نامه‌ای ثبت نشده باشد)، رشته‌ی خالی برمی‌گردد.
 *
 * توجه (تغییر نقش این تابع): قبلاً OTP_Auth از رشته‌ی خالیِ این تابع برای
 * *رد کردن کامل* ورود با ایمیل استفاده می‌کرد (یعنی ثبت قبلی توسط درمانگر
 * اجباری بود). از این نسخه به بعد، این تابع فقط برای *تشخیص اختیاریِ* یک
 * اتصال از‌قبل‌موجود استفاده می‌شود (تا مراجعِ شناخته‌شده به همان حساب/
 * نوبت‌های قبلی‌اش وصل شود)؛ نبودِ این اتصال دیگر باعث رد شدن ورود نمی‌شود —
 * نگاه کن به OTP_Auth::process_verify_otp_email.
 */
function find_mobile_by_email(string $email): string
{
    if (!is_email($email)) {
        return '';
    }

    $mobile = apply_filters('psy_get_mobile_for_email', '', $email);

    return is_string($mobile) ? $mobile : '';
}

/**
 * خواندن شماره موبایل یک کاربر.
 *
 * رفع ناهماهنگی: این پلاگین قبلاً فقط از متای اختصاصی `zdp_mobile` استفاده
 * می‌کرد، در حالی که قرارداد پایه‌ی اکوسیستم پیوند امن، `cellphone` را به‌عنوان
 * کلید هویت مشترک بین همه‌ی پلاگین‌ها (پنل مراجعین، پل پیامک، نظرسنجی و...)
 * تعریف کرده. یعنی کاربرهایی که از این پلاگین ساخته می‌شدند، برای بقیه‌ی
 * اکوسیستم قابل‌شناسایی نبودند و برعکس. اولویت خواندن الان با `cellphone`
 * است؛ `zdp_mobile` فقط برای سازگاری عقب‌رو با رکوردهای قدیمی این پلاگین
 * روی نصب‌های موجود چک می‌شود.
 */
function zdp_get_user_mobile(int $user_id): string
{
    $mobile = (string) get_user_meta($user_id, 'cellphone', true);
    if ($mobile !== '') {
        return $mobile;
    }

    return (string) get_user_meta($user_id, 'zdp_mobile', true);
}

/**
 * ذخیره‌ی شماره موبایل یک کاربر. همیشه هر دو کلید را می‌نویسد: `cellphone`
 * به‌عنوان کلید مشترک اکوسیستم (تا سایر پلاگین‌ها این کاربر را پیدا کنند) و
 * `zdp_mobile` برای سازگاری با کوئری‌های قدیمی/باقیمانده‌ی همین پلاگین.
 */
function zdp_set_user_mobile(int $user_id, string $mobile): void
{
    update_user_meta($user_id, 'cellphone', $mobile);
    update_user_meta($user_id, 'zdp_mobile', $mobile);
}

/**
 * آدرس IP واقعی کاربر برای محدودیت نرخ OTP.
 *
 * زیرساخت این سایت قطعی و تاییدشده است: هاست ParsVDS، بدون هیچ
 * CDN/ریورس‌پروکسی/WAF جلویی — دامنه مستقیم به همین سرور اشاره می‌کند.
 * در چنین حالتی، اعتماد به هدرهایی مثل X-Forwarded-For یا X-Real-IP
 * اشتباه است: این هدرها را فقط یک پراکسی/CDN معتبر (که خودش IP واقعی
 * کلاینت را در آن‌ها می‌نویسد) قابل‌اعتماد می‌کند؛ وقتی چنین لایه‌ای وجود
 * ندارد، هر کاربر می‌تواند خودش این هدر را با هر IP دلخواه (متفاوت در هر
 * درخواست) بفرستد و محدودیت نرخ ارسال OTP را کامل دور بزند — یعنی
 * بمباران پیامکیِ هر شماره‌ی دلخواه، بدون اینکه هیچ IP رایتی به آن مسدود شود.
 *
 * برای همین اولویت اول همیشه REMOTE_ADDR است — تنها مقداری که خودِ وب‌سرور
 * از روی اتصال TCP واقعی ست می‌کند و کلاینت نمی‌تواند جعلش کند. هدرهای
 * پراکسی فقط وقتی در نظر گرفته می‌شوند که سایت واقعاً پشت یک پراکسی/CDN
 * قرار بگیرد؛ آن حالت از طریق فیلتر `zdp_trust_proxy_ip_headers` صریحاً
 * فعال می‌شود (پیش‌فرض: false، و باید false بماند تا وقتی زیرساخت واقعاً
 * عوض شود). اگر روزی سایت پشت Cloudflare/آروان یا مشابه رفت، همان‌جا
 * `add_filter('zdp_trust_proxy_ip_headers', '__return_true')` کافی است —
 * نیازی به تغییر این تابع نیست.
 *
 * تاریخچه: در یک پچ میانی (۱.۶.۴.۳) این پیش‌فرض موقتاً به true برگردانده
 * شده بود، صرفاً به‌عنوان یک اقدام احتیاطیِ گسترده هنگام ردیابی باگ «ورود
 * موفق ولی وارد پنل نمی‌شوم» — بدون ارتباط واقعی با علت آن باگ. علت واقعی
 * (پرش از دل wp-admin که با فایروال هاست تداخل داشت؛ و جداگانه، حساسیت
 * لایه‌ی امنیتی هاست به POST روی wp-json) در ۱.۶.۴.۱ و ۱.۶.۴.۴ پیدا و رفع
 * شد. این پیش‌فرض هیچ‌وقت به false برنگشته بود و تا همین نسخه به‌صورت یک
 * حفره‌ی امنیتیِ فعال (دورزدنِ rate-limit با هدر جعلی) باقی مانده بود.
 */
function get_client_ip(): string
{
    if (class_exists('Pas_Core_Helpers')) {
        return \Pas_Core_Helpers::get_client_ip();
    }

    $remote_addr = isset($_SERVER['REMOTE_ADDR'])
        ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR']))
        : '';

    // زیرساخت تایید شده: ParsVDS، بدون هیچ پراکسی/CDN. پیش‌فرض باید false
    // بماند — نگاه کن به تاریخچه‌ی بالای این تابع برای اینکه چرا این مقدار
    // قبلاً (اشتباهاً و بدون ارتباط با علت واقعی یک باگ دیگر) به true
    // تغییر کرده بود.
    $trust_proxy_headers = apply_filters('zdp_trust_proxy_ip_headers', false);

    $candidate_headers = $trust_proxy_headers
        ? ['HTTP_CF_CONNECTING_IP', 'HTTP_X_REAL_IP', 'HTTP_X_FORWARDED_FOR']
        : [];

    $ip = filter_var($remote_addr, FILTER_VALIDATE_IP) ? $remote_addr : '0.0.0.0';

    foreach ($candidate_headers as $header) {
        if (empty($_SERVER[$header])) {
            continue;
        }

        $raw = sanitize_text_field(wp_unslash($_SERVER[$header]));
        // X-Forwarded-For می‌تواند لیستی از چند IP باشد؛ اولین مورد سمت کاربر است.
        $first = trim(explode(',', $raw)[0]);

        if (filter_var($first, FILTER_VALIDATE_IP)) {
            $ip = $first;
            break;
        }
    }

    return apply_filters('zdp_client_ip', $ip);
}

/**
 * زمان فعلی به‌صورت یکدست بر مبنای UTC برای هر مقایسه‌ی زمانی داخل پلاگین.
 * تمام ستون‌های datetime هم با gmdate ذخیره می‌شوند تا هیچ‌گاه تایم‌زون سایت
 * (current_time('mysql') که local است) با UTC مقایسه نشود — این دقیقاً همان
 * باگی بود که در custom-psychology-portal باعث اختلاف ~۵۶۰۰ ثانیه‌ای شمارش
 * معکوس شده بود.
 */
function now_utc(): string
{
    return gmdate('Y-m-d H:i:s');
}

/**
 * لاگ ممیزی سبک برای عملیات حساس (ورود، پرداخت، تغییر وضعیت نوبت توسط ادمین).
 * در یک آپشن آرایه‌ای محدود (آخرین ۵۰۰ رکورد) نگه‌داری می‌شود؛ برای حجم بالا
 * می‌توان بعداً به جدول اختصاصی مهاجرت داد، اما برای این مقیاس کافی است.
 */
function audit_log(string $action, array $context = []): void
{
    $entries   = get_option('zdp_audit_log', []);
    $entries[] = [
        'time'    => now_utc(),
        'action'  => $action,
        'context' => $context,
        'user_id' => get_current_user_id(),
        'ip'      => get_client_ip(),
    ];

    if (count($entries) > 500) {
        $entries = array_slice($entries, -500);
    }

    update_option('zdp_audit_log', $entries, false);
}

/**
 * لایه‌ی نازک روی وضعیت ورودِ وردپرس.
 *
 * این توابع منطق را تغییر نمی‌دهند — is_user_logged_in()/get_current_user_id()
 * همان مرجع واحد باقی می‌مانند (خودِ هسته‌ی وردپرس، از طریق
 * wp_validate_auth_cookie()، مسئول تایید واقعیِ کوکی است). هدف فقط این است
 * که در آینده، اگر لازم شد یک نقطه‌ی تغییر مرکزی داشته باشیم (مثلاً کش‌کردن
 * نتیجه در طول یک ریکوئست، یا اضافه‌شدن یک روش ورود دوم به‌جز OTP)، همه‌ی
 * ماژول‌ها (Booking، Messages، آینده: Cases) به‌جای صدازدن مستقیم توابع
 * هسته، از اینجا عبور کنند.
 */
/**
 * پیدا کردن یا ساختن کاربر بر اساس شماره موبایل — نسخه‌ی مشترک همان منطقی
 * که OTP_Auth::find_or_create_user برای مراجعانی که خودشان از سایت وارد
 * می‌شوند اجرا می‌کند. این نسخه برای «ثبت نوبت دستی توسط درمانگر» هم لازم
 * است: وقتی درمانگر نوبتی را که تلفنی/واتساپ/حضوری گرفته شده ثبت می‌کند،
 * مراجع ممکن است هنوز هیچ‌وقت از طریق OTP سایت وارد نشده باشد، ولی نوبت‌ها
 * در دیتابیس به یک user_id واقعی وردپرس وصل هستند (نه یک نام/شماره‌ی آزاد) —
 * تا همان مراجع بعداً با OTP وارد پنل شود و همین نوبت را در تب «نوبت‌های من»
 * ببیند. مقدار $mobile باید از قبل با to_local_format() نرمال شده باشد.
 *
 * @return int|\WP_Error شناسه‌ی کاربر یا خطا
 */
function zdp_find_or_create_user_by_mobile(string $mobile): int|\WP_Error
{
    $users = get_users(['meta_key' => 'cellphone', 'meta_value' => $mobile, 'number' => 1]);
    if (!empty($users)) {
        if (!get_user_meta($users[0]->ID, 'zdp_mobile', true)) {
            update_user_meta($users[0]->ID, 'zdp_mobile', $mobile);
        }
        return (int) $users[0]->ID;
    }

    $legacy_users = get_users(['meta_key' => 'zdp_mobile', 'meta_value' => $mobile, 'number' => 1]);
    if (!empty($legacy_users)) {
        update_user_meta($legacy_users[0]->ID, 'cellphone', $mobile);
        return (int) $legacy_users[0]->ID;
    }

    $domain          = wp_parse_url(home_url(), PHP_URL_HOST) ?: 'peyvandamn.ir';
    $random_password = wp_generate_password();
    $login           = 'zdp_' . $mobile;

    $user_id = wp_create_user($login, $random_password, $mobile . '@' . $domain);
    if (is_wp_error($user_id)) {
        $existing_id = username_exists($login);
        if (!$existing_id) {
            $existing_id = email_exists($mobile . '@' . $domain);
        }
        if ($existing_id) {
            $existing_user = get_user_by('id', $existing_id);
            if ($existing_user && !$existing_user->has_cap('edit_posts') && !$existing_user->has_cap('manage_options')) {
                zdp_set_user_mobile((int) $existing_id, $mobile);
                return (int) $existing_id;
            }
        }
        return $user_id;
    }

    zdp_set_user_mobile((int) $user_id, $mobile);

    return (int) $user_id;
}

function zdp_is_logged_in(): bool
{
    return is_user_logged_in();
}

function zdp_current_user_id(): int
{
    return get_current_user_id();
}

if (!function_exists('zdp_icon')) {
    /**
     * آیکون SVG درون‌خطی برای سطوحی که مراجع می‌بیند (پنل، فرم ارزیابی، قیف آزمون).
     *
     * هم‌خانواده با آیکون‌های کیت رابط کاربری: شبکهٔ ۲۴×۲۴، stroke=currentColor،
     * بدون فونت آیکون و بدون درخواست خارجی. جای ایموجی می‌نشیند تا ظاهر رابط در
     * همهٔ سیستم‌عامل‌ها یکسان و حرفه‌ای بماند. آیکون تزئینی است و aria-hidden
     * می‌گیرد؛ متن همراه آن، معنای کنترل را می‌رساند.
     */
    function zdp_icon(string $name, int $size = 18, array $args = []): string
    {
        $paths = [
            'check'     => '<path d="m5 12.5 4.5 4.5L19 7.5"/>',
            'clock'     => '<circle cx="12" cy="12" r="8.25"/><path d="M12 7.5V12l3 2"/>',
            'lock'      => '<rect x="4.5" y="10.5" width="15" height="9.5" rx="2.2"/><path d="M8 10.5V8a4 4 0 0 1 8 0v2.5"/>',
            'clipboard' => '<rect x="6" y="4.5" width="12" height="16" rx="2"/><path d="M9.5 4.5V3.2h5v1.3M9.5 10h5M9.5 13.5h5M9.5 17h3"/>',
            'message'   => '<path d="M20 15.5a2 2 0 0 1-2 2H9l-4.5 3v-3H6a2 2 0 0 1-2-2V6.5a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2z"/>',
            'calendar'  => '<rect x="3.5" y="5" width="17" height="15.5" rx="2.5"/><path d="M8 3.5v3M16 3.5v3M3.5 10.5h17"/>',
            'phone'     => '<path d="M7 3.8 9.6 4l1.2 4-2 1.4a11.5 11.5 0 0 0 5.8 5.8l1.4-2 4 1.2.2 2.6c0 1.3-1 2.3-2.3 2.4C11.4 20 4 12.6 4.6 6.1 4.7 4.8 5.7 3.8 7 3.8z"/>',
            'refresh'   => '<path d="M19.5 12a7.5 7.5 0 1 1-2.2-5.3"/><path d="M19.5 4.5V9h-4.5"/>',
            'shield'    => '<path d="M12 3.5 19 6v5.5c0 4.2-2.8 7.6-7 9-4.2-1.4-7-4.8-7-9V6z"/><path d="m9 12 2 2 4-4"/>',
            'mail'      => '<rect x="3.5" y="5.5" width="17" height="13" rx="2"/><path d="m4.5 7 7.5 6 7.5-6"/>',
        ];

        // اگر کیت رابط کاربری فعال باشد، همان آیکون‌های کیت استفاده می‌شوند تا
        // در کل اکوسیستم یک خانوادهٔ آیکون واحد داشته باشیم؛ نقشهٔ بالا فقط
        // fallback برای وقتی است که کیت خاموش است.
        if (function_exists('pui_icon') && class_exists('PUI_Icons') && in_array($name, \PUI_Icons::names(), true)) {
            return \pui_icon($name, ['size' => max(12, $size)]);
        }

        if (!isset($paths[$name])) {
            return '';
        }

        $class = 'zdp-icon' . (!empty($args['class']) ? ' ' . preg_replace('/[^A-Za-z0-9 _-]/', '', (string) $args['class']) : '');

        return sprintf(
            '<svg class="%s" width="%d" height="%d" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">%s</svg>',
            $class,
            max(12, $size),
            max(12, $size),
            $paths[$name]
        );
    }
}
