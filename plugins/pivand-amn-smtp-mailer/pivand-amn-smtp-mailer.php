<?php
/**
 * Plugin Name: پیوند امن — ارسال ایمیل SMTP
 * Description: تمام ایمیل‌های خروجی وردپرس (wp_mail) را به‌جای mail() پیش‌فرض هاست، از طریق SMTP واقعی یا Brevo API ارسال می‌کند تا در اسپم گیر نکنند.
 * Version: 1.5.3
 * Requires at least: 6.6
 * Tested up to: 7.1
 * Author: Hadi.parsaeian
 * Text Domain: pivand-amn-smtp-mailer
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Requires PHP: 8.1
 * Domain Path: /languages
 *
 * تغییرات نسخه‌ی ۱.۵.۰ (بازطراحی برای هاست ایرانی + شرایط تحریم):
 * نسخه‌ی قبلی چهار روش داشت: SMTP، Brevo، Mailgun، SendGrid. بعد از بررسی،
 * دو تای آخر از افزونه حذف شدند، نه به‌خاطر باگ، بلکه چون در عمل برای
 * کاربر ایرانی به دردنخورند:
 *
 * - Mailgun (شرکت Sinch) و SendGrid (شرکت Twilio) هر دو آمریکایی‌اند و
 *   قوانین Export Compliance/OFAC خودشون رو روی ثبت‌نام و استفاده از
 *   حساب‌های مرتبط با ایران اعمال می‌کنن؛ حتی اگه یه بار با فیلترشکن
 *   ثبت‌نام موفق بشی، ریسک بسته‌شدن حساب وسط کار هست. یعنی این دو روش
 *   یک وابستگی غیرقابل‌اتکا به افزونه اضافه می‌کردن.
 * - Brevo یک شرکت فرانسوی/اروپایی است و این محدودیت‌های سطح-حساب رو به
 *   همون شکل اعمال نمی‌کنه؛ کاربرهای ایرانی معمولاً می‌تونن ثبت‌نام کنن و
 *   از API Key استفاده کنن. Brevo فقط با HTTPS (پورت 443) کار می‌کنه، نه
 *   با پورت SMTP — پس حتی وقتی هاست پورت 587/465 رو می‌بنده (که دلیل خطای
 *   «نتوانست به میزبان SMTP متصل شود» در تست قبلی همین بود)، Brevo API
 *   همچنان کار می‌کنه چون از همون HTTPS ای استفاده می‌کنه که خودِ سایت با
 *   آن بالا می‌آید.
 * - خطای «Failed to connect to server» که در تست SMTP جیمیل قبلی دیده شد،
 *   نشونه‌ی معمول بسته‌بودن پورت خروجی SMTP توسط هاست (رایج در هاست‌های
 *   ایرانی مثل ParsVDS برای جلوگیری از سوءاستفاده/اسپم) است، نه مشکل خودِ
 *   جیمیل یا تحریم مستقیم گوگل. اگه با تیکت به ParsVDS باز شدن پورت
 *   587/465 درخواست بشه و باز بشه، همون روش SMTP هم به‌عنوان جایگزین کار
 *   می‌کنه؛ در غیر این‌صورت Brevo تنها روشی می‌مونه که تضمینی کار می‌کنه.
 *
 * بر همین اساس این نسخه فقط دو روش نگه داشته: SMTP (شامل SMTP خودِ هاست یا
 * جیمیل) به‌عنوان روش ۱، و Brevo API به‌عنوان روش ۲ و لایه‌ی پشتیبان اصلی.
 * زنجیره‌ی failover (روش اصلی ← SMTP پشتیبان اختیاری ← mail() بومی هاست
 * اختیاری) و صف retry با wp_cron دست‌نخورده مانده چون این بخش‌ها مستقل از
 * تحریم‌اند و کاربرد واقعی دارند.
 *
 * هیچ رمزی داخل این فایل نوشته نشده — همه از تنظیمات ادمین (wp_options)
 * خوانده می‌شود تا هیچ credential ای داخل کد یا فایل قابل‌دانلود نباشد.
 */

namespace Pivand\SmtpMailer;

if (!defined('ABSPATH')) {
    exit;
}

define('ZSM_VERSION', '1.5.3');
define('ZSM_DIR', plugin_dir_path(__FILE__));
define('ZSM_URL', plugin_dir_url(__FILE__));

final class ZSM_Smtp_Mailer
{
    /** @var string هوک صفحه‌ی تنظیمات (خروجی add_options_page). */
    private static string $page_hook = '';

    /** @var string هوک صفحه‌ی گزارش ایمیل‌ها. */
    private static string $log_page_hook = '';

    /**
     * قفل بازگشتی برای زنجیره‌ی SMTP: وقتی run_delivery_chain() برای امتحان
     * SMTP اصلی/پشتیبان خودش wp_mail() را دوباره صدا می‌زند، این پرچم مانع
     * از این می‌شود که آن فراخوانی‌ی داخلی دوباره وارد dispatch() شود و
     * حلقه‌ی بی‌نهایت بسازد.
     */
    private static bool $in_chain = false;

    /** @var array<string,mixed>|null اگر مقدار داشته باشد، configure_smtp() به‌جای تنظیمات ذخیره‌شده از این استفاده می‌کند (برای امتحان SMTP پشتیبان). */
    private static ?array $override_smtp = null;

    /** @var bool اگر true باشد، configure_smtp() به‌جای isSMTP() از isMail() (ارسال بومی هاست) استفاده می‌کند. */
    private static bool $force_native_mail = false;

    /** @var string|null آخرین پیام خطا در طول یک زنجیره‌ی ارسال — برای لاگ. */
    private static ?string $last_chain_error = null;

    public static function init(): void
    {
        add_action('phpmailer_init', [self::class, 'configure_smtp']);
        add_action('admin_menu', [self::class, 'register_menu']);
        add_action('admin_enqueue_scripts', [self::class, 'enqueue_assets']);
        add_action('admin_post_zsm_save_settings', [self::class, 'handle_save_settings']);
        add_action('admin_post_zsm_send_test', [self::class, 'handle_send_test']);
        // نقطه‌ی ورود یکتا برای هر دو روش (SMTP و Brevo): کل منطق
        // failover/retry اینجا شروع می‌شود.
        add_filter('pre_wp_mail', [self::class, 'dispatch'], 10, 2);

        add_action(ZSM_Mail_Queue::CRON_HOOK, [ZSM_Mail_Queue::class, 'process_due_queue']);
        add_action('admin_post_zsm_retry_now', [ZSM_Mail_Queue::class, 'handle_retry_now']);
    }

    /**
     * فقط توی صفحه‌ی تنظیمات خودمون CSS رو لود کن — نه توی کل ادمین.
     */
    public static function enqueue_assets(string $hook): void
    {
        if ($hook !== self::$page_hook && $hook !== self::$log_page_hook) {
            return;
        }

        wp_enqueue_style('zsm-admin', ZSM_URL . 'assets/css/admin.css', class_exists('Pas_Core_Tokens') ? [\Pas_Core_Tokens::handle()] : [], ZSM_VERSION);
    }

    /**
     * قلب پلاگین: هر بار وردپرس بخواهد ایمیل بفرستد (چه از طریق wp_mail خودش،
     * چه از طریق هر پلاگین دیگری که wp_mail را صدا می‌زند)، این تابع تنظیمات
     * PHPMailer را قبل از ارسال به SMTP تغییر می‌دهد.
     */
    public static function configure_smtp(\PHPMailer\PHPMailer\PHPMailer $phpmailer): void
    {
        // حالت ۳ زنجیره: ارسال بومی هاست (آخرین چاره، بدون SMTP).
        if (self::$force_native_mail) {
            $phpmailer->isMail();
            return;
        }

        // حالت ۲ زنجیره: SMTP پشتیبان — تنظیمات override جای تنظیمات ذخیره‌شده می‌نشیند.
        if (self::$override_smtp !== null) {
            $settings = self::$override_smtp;
        } else {
            $settings = self::get_settings();

            if (($settings['send_method'] ?? 'smtp') !== 'smtp') {
                return; // ادمین Brevo رو انتخاب کرده — این تابع اصلاً نباید اجرا شود
            }
        }

        if (empty($settings['host']) || empty($settings['username']) || empty($settings['password'])) {
            return; // تنظیم نشده — بگذار وردپرس با روش پیش‌فرض خودش تلاش کند
        }

        $phpmailer->isSMTP();
        $phpmailer->Host = $settings['host'];
        // نکته‌ی حیاتی: اگه فیلد پورت یه‌بار خالی ذخیره بشه، مقدارش تو
        // دیتابیس رشته‌ی خالیه، نه غایب. wp_parse_args فقط کلیدهای کاملاً
        // غایب رو با پیش‌فرض پر می‌کنه، نه کلیدهای موجود با مقدار خالی —
        // پس (int) '' می‌شه ۰. اینجا یه فال‌بک ایمن اضافه شده.
        $port = (int) $settings['port'];
        $phpmailer->Port       = $port > 0 ? $port : 587;
        $phpmailer->SMTPAuth   = true;
        $phpmailer->Username   = $settings['username'];
        $phpmailer->Password   = $settings['password'];
        $phpmailer->SMTPSecure = $settings['encryption']; // 'tls' یا 'ssl'

        if (!empty($settings['from_email'])) {
            $phpmailer->setFrom($settings['from_email'], $settings['from_name'] ?: get_bloginfo('name'));
        }
    }

    /**
     * قلاب pre_wp_mail: بر اساس "روش ارسال" انتخاب‌شده، کل ارسال رو از مسیر
     * PHPMailer/SMTP خارج می‌کنه و از طریق HTTPS (پورت 443) به Brevo
     * می‌سپاره. برگردوندن null یعنی "دست نمی‌زنم، بذار مسیر عادی SMTP اجرا بشه".
     *
     * @param mixed $null_value همیشه null است مگر فیلتر دیگری زودتر مقداری برگردانده باشد
     * @param array $atts آرایه‌ی to/subject/message/headers/attachments که خود wp_mail می‌سازد
     */
    public static function dispatch($null_value, array $atts)
    {
        // فراخوانی بازگشتی از داخل run_delivery_chain: دست نمی‌زنیم، بگذار
        // PHPMailer با override اجرا شود.
        if (self::$in_chain) {
            return null;
        }

        return self::run_delivery_chain($atts, self::get_settings());
    }

    /**
     * زنجیره‌ی failover: بسته به «روش ارسال» انتخاب‌شده، لیستی از تلاش‌های
     * پشت‌سرهم می‌سازد (مثلاً SMTP اصلی → SMTP پشتیبان → mail() بومی) و
     * یکی‌یکی امتحان می‌کند. به محض موفقیت اولین تلاش، متوقف می‌شود. اگر
     * همه شکست خوردند، ایمیل برای retry بعدی در صف ذخیره می‌شود.
     */
    private static function run_delivery_chain(array $atts, array $settings): bool
    {
        self::$in_chain = true;
        $sent            = self::execute_chain($atts, $settings, $used_method, $error);
        self::$in_chain  = false;

        if ($sent) {
            ZSM_Mail_Queue::log(true, $atts, (string) $used_method, null);
            return true;
        }

        // همه‌ی تلاش‌ها شکست خوردند: صف retry + یک خطای نهایی برای هر کد
        // دیگری (مثلاً صفحه‌ی «تست ارسال») که منتظر wp_mail_failed است.
        ZSM_Mail_Queue::enqueue($atts, (string) $error);
        do_action('wp_mail_failed', new \WP_Error(
            'zsm_all_methods_failed',
            'همه‌ی روش‌های ارسال شکست خوردند؛ ایمیل برای تلاش مجدد خودکار در صف قرار گرفت. آخرین خطا: ' . $error
        ));

        return false;
    }

    /**
     * نسخه‌ی عمومی که ZSM_Mail_Queue برای retry از صف صدا می‌زند.
     */
    public static function retry_delivery_chain(array $atts, array $settings, ?string &$used_method, ?string &$error): bool
    {
        self::$in_chain = true;
        $sent            = self::execute_chain($atts, $settings, $used_method, $error);
        self::$in_chain  = false;

        return $sent;
    }

    /** هسته‌ی مشترک: زنجیره‌ی تلاش‌ها را می‌سازد و یکی‌یکی امتحان می‌کند. بدون side effect روی صف/لاگ. */
    private static function execute_chain(array $atts, array $settings, ?string &$used_method, ?string &$error): bool
    {
        $attempts = self::build_attempt_chain($settings);

        if (empty($attempts)) {
            $used_method = null;
            $error       = 'هیچ روش ارسالی تنظیم نشده';
            return false;
        }

        $used_method = null;
        $error       = null;
        $sent        = false;

        foreach ($attempts as $attempt) {
            self::$override_smtp    = null;
            self::$force_native_mail = false;

            $captured_error = null;
            $catcher = static function (\WP_Error $err) use (&$captured_error): void {
                $captured_error = $err->get_error_message();
            };
            add_action('wp_mail_failed', $catcher);

            $sent = match ($attempt['type']) {
                'smtp'   => self::attempt_smtp($atts, $attempt['config']),
                'native' => self::attempt_native($atts),
                'brevo'  => self::send_via_brevo($atts, $settings),
                default  => false,
            };

            remove_action('wp_mail_failed', $catcher);

            if ($sent) {
                $used_method = $attempt['label'];
                break;
            }

            $error = $captured_error ?: ('خطای نامشخص در تلاش ' . $attempt['label']);
        }

        self::$override_smtp    = null;
        self::$force_native_mail = false;

        return $sent;
    }

    /**
     * @return array<int,array{type:string,label:string,config?:array}>
     */
    private static function build_attempt_chain(array $settings): array
    {
        $chain  = [];
        $method = $settings['send_method'] ?? 'smtp';

        if ($method === 'smtp') {
            if (!empty($settings['host']) && !empty($settings['username']) && !empty($settings['password'])) {
                $chain[] = ['type' => 'smtp', 'label' => 'SMTP اصلی', 'config' => $settings];
            }
        } elseif ($method === 'brevo') {
            $chain[] = ['type' => 'brevo', 'label' => 'Brevo API'];
        }

        if (!empty($settings['backup_enabled']) && !empty($settings['backup_host']) && !empty($settings['backup_username']) && !empty($settings['backup_password'])) {
            $chain[] = [
                'type'   => 'smtp',
                'label'  => 'SMTP پشتیبان',
                'config' => [
                    'host'       => $settings['backup_host'],
                    'port'       => $settings['backup_port'],
                    'encryption' => $settings['backup_encryption'],
                    'username'   => $settings['backup_username'],
                    'password'   => $settings['backup_password'],
                    'from_email' => $settings['from_email'],
                    'from_name'  => $settings['from_name'],
                ],
            ];
        }

        if (!empty($settings['native_fallback_enabled'])) {
            $chain[] = ['type' => 'native', 'label' => 'ارسال بومی هاست (mail)'];
        }

        return $chain;
    }

    /** یک تلاش SMTP واحد را با یک نمونه‌ی تازه از wp_mail اجرا می‌کند (با override تنظیمات). */
    private static function attempt_smtp(array $atts, array $config): bool
    {
        self::$override_smtp = $config;

        return (bool) wp_mail(
            $atts['to'] ?? '',
            $atts['subject'] ?? '',
            $atts['message'] ?? '',
            $atts['headers'] ?? '',
            $atts['attachments'] ?? []
        );
    }

    /** آخرین چاره: ارسال با mail() خودِ هاست، بدون SMTP. */
    private static function attempt_native(array $atts): bool
    {
        self::$force_native_mail = true;

        return (bool) wp_mail(
            $atts['to'] ?? '',
            $atts['subject'] ?? '',
            $atts['message'] ?? '',
            $atts['headers'] ?? '',
            $atts['attachments'] ?? []
        );
    }

    /**
     * لیست گیرنده‌ها رو از فرمت آزاد wp_mail (رشته‌ی جدا با کاما یا آرایه)
     * به یه آرایه‌ی تمیز از ایمیل‌های معتبر تبدیل می‌کنه.
     *
     * @return string[]
     */
    private static function extract_recipients(array $atts): array
    {
        $to_raw   = $atts['to'] ?? '';
        $to_parts = is_array($to_raw) ? $to_raw : explode(',', (string) $to_raw);

        $recipients = [];
        foreach ($to_parts as $address) {
            $address = trim((string) $address);
            if ($address !== '' && is_email($address)) {
                $recipients[] = $address;
            }
        }

        return $recipients;
    }

    private static function send_via_brevo(array $atts, array $settings): bool
    {
        if (empty($settings['brevo_api_key'])) {
            do_action('wp_mail_failed', new \WP_Error('zsm_brevo_no_key', 'روش ارسال روی Brevo API تنظیم شده ولی API Key وارد نشده.'));
            return false;
        }

        $recipients = self::extract_recipients($atts);
        if (empty($recipients)) {
            do_action('wp_mail_failed', new \WP_Error('zsm_brevo_bad_to', 'آدرس گیرنده معتبر نیست.'));
            return false;
        }

        $sender_email = $settings['from_email'] !== '' ? $settings['from_email'] : get_bloginfo('admin_email');
        $sender_name  = $settings['from_name']  !== '' ? $settings['from_name']  : get_bloginfo('name');

        $response = wp_remote_post('https://api.brevo.com/v3/smtp/email', [
            'timeout' => 15,
            'headers' => [
                'accept'       => 'application/json',
                'api-key'      => $settings['brevo_api_key'],
                'content-type' => 'application/json',
            ],
            // فقط textContent می‌فرستیم چون wp_mail به‌صورت پیش‌فرض متن ساده
            // می‌فرستد مگر پلاگین دیگری content-type را به text/html تغییر
            // داده باشد.
            'body' => wp_json_encode([
                'sender'      => ['name' => $sender_name, 'email' => $sender_email],
                'to'          => array_map(static fn (string $e): array => ['email' => $e], $recipients),
                'subject'     => (string) ($atts['subject'] ?? ''),
                'textContent' => wp_strip_all_tags((string) ($atts['message'] ?? '')),
            ]),
        ]);

        if (is_wp_error($response)) {
            do_action('wp_mail_failed', new \WP_Error('zsm_brevo_http', $response->get_error_message()));
            return false;
        }

        $code = wp_remote_retrieve_response_code($response);
        if ($code < 200 || $code >= 300) {
            $decoded = json_decode(wp_remote_retrieve_body($response), true);
            $message = is_array($decoded) && isset($decoded['message'])
                ? $decoded['message']
                : ('Brevo HTTP ' . $code);
            do_action('wp_mail_failed', new \WP_Error('zsm_brevo_api', $message));
            return false;
        }

        return true;
    }

    /** @return array{send_method:string,host:string,port:string,encryption:string,username:string,password:string,from_email:string,from_name:string,brevo_api_key:string,backup_enabled:bool,backup_host:string,backup_port:string,backup_encryption:string,backup_username:string,backup_password:string,native_fallback_enabled:bool} */
    public static function get_settings(): array
    {
        $settings = wp_parse_args(get_option('zsm_settings', []), self::default_settings());

        // migration از نسخه‌ی قبلی: اگه از قبل روی این سایت send_method روی
        // mailgun یا sendgrid ذخیره شده بود (روش‌هایی که در این نسخه حذف
        // شدند)، بی‌صدا خالی نمون؛ برگرد به smtp تا ادمین یک بار وارد صفحه‌ی
        // تنظیمات بشه و Brevo رو انتخاب کنه.
        if (!in_array($settings['send_method'] ?? 'smtp', ['smtp', 'brevo'], true)) {
            $settings['send_method'] = 'smtp';
        }

        // مطابق الگوی رمزنگاری اکوسیستم: unwrap_decrypt مقادیر قدیمیِ
        // رمزنگاری‌نشده را دست‌نخورده برمی‌گرداند، پس هیچ migration دستی
        // لازم نیست و اگر core نصب نباشد رفتار قبلی (متن ساده) حفظ می‌شود.
        if ($settings['password'] !== '' && class_exists('Pas_Core_Crypto')) {
            $settings['password'] = \Pas_Core_Crypto::unwrap_decrypt($settings['password']);
        }
        if ($settings['brevo_api_key'] !== '' && class_exists('Pas_Core_Crypto')) {
            $settings['brevo_api_key'] = \Pas_Core_Crypto::unwrap_decrypt($settings['brevo_api_key']);
        }
        if ($settings['backup_password'] !== '' && class_exists('Pas_Core_Crypto')) {
            $settings['backup_password'] = \Pas_Core_Crypto::unwrap_decrypt($settings['backup_password']);
        }

        return $settings;
    }

    /** مثل get_settings() ولی رمز را رمزگشایی نمی‌کند — فقط برای منطق ذخیره‌سازی داخلی. */
    private static function get_raw_settings(): array
    {
        return wp_parse_args(get_option('zsm_settings', []), self::default_settings());
    }

    private static function default_settings(): array
    {
        return [
            'send_method'      => 'smtp', // 'smtp' | 'brevo'
            'host'             => '',
            'port'             => '587',
            'encryption'       => 'tls',
            'username'         => '',
            'password'         => '',
            'from_email'       => '',
            'from_name'        => '',
            'brevo_api_key'    => '',
            'backup_enabled'   => false,
            'backup_host'      => '',
            'backup_port'      => '587',
            'backup_encryption' => 'tls',
            'backup_username'  => '',
            'backup_password'  => '',
            'native_fallback_enabled' => false,
        ];
    }

    public static function register_menu(): void
    {
        $hook = add_options_page(
            'ارسال ایمیل SMTP',
            'ارسال ایمیل SMTP',
            'manage_options',
            'zsm-smtp-mailer',
            [self::class, 'render_settings_page']
        );

        self::$page_hook = (string) $hook;

        $log_hook = add_options_page(
            'گزارش ایمیل‌ها',
            'گزارش ایمیل‌ها (پیوند امن)',
            'manage_options',
            'zsm-mail-log',
            [ZSM_Mail_Queue::class, 'render_log_page']
        );

        self::$log_page_hook = (string) $log_hook;
    }

    public static function render_settings_page(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die('دسترسی غیرمجاز', 403);
        }

        $s      = self::get_settings();
        $notice = isset($_GET['zsm_notice']) ? sanitize_key($_GET['zsm_notice']) : '';
        ?>
        <div class="wrap zsm-admin">
            <h1>ارسال ایمیل SMTP</h1>
            <p class="description">این تنظیمات جایگزین ارسال ایمیل پیش‌فرض هاست می‌شود — برای همه‌ی ایمیل‌های وردپرس (اعلان مدیر، بازیابی رمز، هر پلاگین دیگری که wp_mail را صدا می‌زند).</p>
            <p class="description">
                <strong>چرا فقط دو روش؟</strong> Mailgun و SendGrid آمریکایی‌اند و به‌خاطر محدودیت‌های تحریمی روی حساب‌های
                مرتبط با ایران قابل‌اتکا نیستند، برای همین از این نسخه حذف شدند. Brevo اروپایی است و این محدودیت را ندارد، و
                چون فقط از HTTPS استفاده می‌کند (نه پورت SMTP)، حتی اگر هاست پورت 587/465 را ببندد هم کار می‌کند.
            </p>

            <?php if ($notice === 'saved') : ?>
                <div class="notice notice-success is-dismissible"><p>تنظیمات ذخیره شد.</p></div>
            <?php elseif ($notice === 'test_ok') : ?>
                <div class="notice notice-success is-dismissible"><p>ایمیل تست ارسال شد — اینباکس (و پوشه اسپم) را چک کن.</p></div>
            <?php elseif ($notice === 'test_fail') : ?>
                <div class="notice notice-error is-dismissible"><p>ارسال تست شکست خورد: <?php echo esc_html(get_transient('zsm_last_error') ?: 'خطای نامشخص'); ?></p></div>
            <?php endif; ?>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="zsm_save_settings">
                <?php wp_nonce_field('zsm_save_settings', 'zsm_nonce'); ?>
                <table class="form-table">
                    <tr>
                        <th>روش ارسال</th>
                        <td>
                            <label style="display:block;margin-bottom:6px;">
                                <input type="radio" name="send_method" value="smtp" <?php checked($s['send_method'], 'smtp'); ?>>
                                SMTP (روش ۱ — به پورت خروجی ۵۸۷/۴۶۵ روی هاست نیاز دارد؛ اگه هاست این پورت را بسته باشد، کار نمی‌کند)
                            </label>
                            <label style="display:block;">
                                <input type="radio" name="send_method" value="brevo" <?php checked($s['send_method'], 'brevo'); ?>>
                                Brevo API (روش ۲ — پیشنهادی برای هاست ایرانی؛ فقط از HTTPS استفاده می‌کند، تأیید فقط با یک ایمیل، بدون نیاز به دامنه)
                            </label>
                        </td>
                    </tr>
                </table>

                <h2 class="title">روش ۱: تنظیمات SMTP</h2>
                <p class="description">این بخش فقط وقتی «روش ارسال» بالا روی SMTP باشد استفاده می‌شود.</p>
                <table class="form-table">
                    <tr>
                        <th><label for="zsm_host">SMTP Host</label></th>
                        <td><input type="text" id="zsm_host" name="host" value="<?php echo esc_attr($s['host']); ?>" class="regular-text" placeholder="smtp.gmail.com" dir="ltr"></td>
                    </tr>
                    <tr>
                        <th><label for="zsm_port">SMTP Port</label></th>
                        <td><input type="text" id="zsm_port" name="port" value="<?php echo esc_attr($s['port']); ?>" class="small-text" placeholder="587"></td>
                    </tr>
                    <tr>
                        <th><label for="zsm_encryption">Encryption</label></th>
                        <td>
                            <select id="zsm_encryption" name="encryption">
                                <option value="tls" <?php selected($s['encryption'], 'tls'); ?>>TLS</option>
                                <option value="ssl" <?php selected($s['encryption'], 'ssl'); ?>>SSL</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="zsm_username">SMTP Username</label></th>
                        <td><input type="text" id="zsm_username" name="username" value="<?php echo esc_attr($s['username']); ?>" class="regular-text" placeholder="you@gmail.com" autocomplete="off" dir="ltr"></td>
                    </tr>
                    <tr>
                        <th><label for="zsm_password">SMTP Password / App Password</label></th>
                        <td><input type="password" id="zsm_password" name="password" value="" class="regular-text" autocomplete="new-password"
                                   placeholder="<?php echo $s['password'] !== '' ? '•••••••• (تنظیم شده — برای تغییر، مقدار جدید وارد کن)' : ''; ?>">
                            <p class="description">برای جیمیل، این باید App Password ۱۶ کاراکتری باشد، نه رمز عبور اصلی حساب.</p></td>
                    </tr>
                    <tr>
                        <th><label for="zsm_from_email">آدرس فرستنده (From)</label></th>
                        <td><input type="email" id="zsm_from_email" name="from_email" value="<?php echo esc_attr($s['from_email']); ?>" class="regular-text" placeholder="you@gmail.com" dir="ltr">
                            <p class="description">معمولاً باید همان SMTP Username باشد وگرنه بعضی سرویس‌ها ایمیل را رد می‌کنند.</p></td>
                    </tr>
                    <tr>
                        <th><label for="zsm_from_name">نام فرستنده</label></th>
                        <td><input type="text" id="zsm_from_name" name="from_name" value="<?php echo esc_attr($s['from_name']); ?>" class="regular-text" placeholder="پیوند امن">
                            <p class="description">این «نام و آدرس فرستنده» برای هر دو روش (SMTP و Brevo) استفاده می‌شود.</p></td>
                    </tr>
                </table>

                <h2 class="title">روش ۲: تنظیمات Brevo API</h2>
                <p class="description">
                    این بخش فقط وقتی «روش ارسال» بالا روی Brevo باشد استفاده می‌شود — نیازی به باز بودن پورت SMTP روی هاست ندارد.
                    یک حساب رایگان (۳۰۰ ایمیل در روز) توی
                    <a href="https://app.brevo.com/settings/keys/api" target="_blank" rel="noopener">app.brevo.com/settings/keys/api</a>
                    بساز، یک API Key بگیر، و ایمیل «آدرس فرستنده» بالا رو توی حساب Brevo تأیید کن (Single Sender Verification — فقط یک لینک تأییدیه که به همون ایمیل می‌فرستد).
                </p>
                <table class="form-table">
                    <tr>
                        <th><label for="zsm_brevo_api_key">Brevo API Key</label></th>
                        <td><input type="password" id="zsm_brevo_api_key" name="brevo_api_key" value="" class="regular-text" autocomplete="new-password" dir="ltr"
                                   placeholder="<?php echo $s['brevo_api_key'] !== '' ? '•••••••• (تنظیم شده — برای تغییر، مقدار جدید وارد کن)' : 'xkeysib-...'; ?>"></td>
                    </tr>
                </table>

                <h2 class="title">لایه‌ی پشتیبان (برای هر دو روش بالا)</h2>
                <p class="description">
                    اگر روش اصلی بالا شکست بخورد، این افزونه خودکار این‌ها را به‌ترتیب امتحان می‌کند: <strong>SMTP پشتیبان</strong>
                    (اگر فعال باشد) و سپس <strong>ارسال بومی هاست</strong> (اگر فعال باشد). اگر همه شکست بخورند، ایمیل در صف
                    قرار می‌گیرد و بعداً خودکار دوباره تلاش می‌شود — در
                    «<a href="<?php echo esc_url(admin_url('options-general.php?page=zsm-mail-log')); ?>">گزارش ایمیل‌ها</a>» قابل‌پیگیری است.
                </p>
                <table class="form-table">
                    <tr>
                        <th><label for="zsm_backup_enabled">فعال‌سازی SMTP پشتیبان</label></th>
                        <td><label><input type="checkbox" id="zsm_backup_enabled" name="backup_enabled" value="1" <?php checked(!empty($s['backup_enabled'])); ?>> اگه روش اصلی شکست خورد، این حساب دوم رو امتحان کن</label></td>
                    </tr>
                    <tr>
                        <th><label for="zsm_backup_host">Backup SMTP Host</label></th>
                        <td><input type="text" id="zsm_backup_host" name="backup_host" value="<?php echo esc_attr($s['backup_host']); ?>" class="regular-text" placeholder="smtp.example.com" dir="ltr"></td>
                    </tr>
                    <tr>
                        <th><label for="zsm_backup_port">Backup SMTP Port</label></th>
                        <td><input type="text" id="zsm_backup_port" name="backup_port" value="<?php echo esc_attr($s['backup_port']); ?>" class="small-text" placeholder="587"></td>
                    </tr>
                    <tr>
                        <th><label for="zsm_backup_encryption">Encryption</label></th>
                        <td>
                            <select id="zsm_backup_encryption" name="backup_encryption">
                                <option value="tls" <?php selected($s['backup_encryption'], 'tls'); ?>>TLS</option>
                                <option value="ssl" <?php selected($s['backup_encryption'], 'ssl'); ?>>SSL</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="zsm_backup_username">Backup SMTP Username</label></th>
                        <td><input type="text" id="zsm_backup_username" name="backup_username" value="<?php echo esc_attr($s['backup_username']); ?>" class="regular-text" placeholder="backup@example.com" autocomplete="off" dir="ltr"></td>
                    </tr>
                    <tr>
                        <th><label for="zsm_backup_password">Backup SMTP Password</label></th>
                        <td><input type="password" id="zsm_backup_password" name="backup_password" value="" class="regular-text" autocomplete="new-password"
                                   placeholder="<?php echo $s['backup_password'] !== '' ? '•••••••• (تنظیم شده — برای تغییر، مقدار جدید وارد کن)' : ''; ?>"></td>
                    </tr>
                    <tr>
                        <th><label for="zsm_native_fallback_enabled">ارسال بومی هاست به‌عنوان آخرین چاره</label></th>
                        <td>
                            <label><input type="checkbox" id="zsm_native_fallback_enabled" name="native_fallback_enabled" value="1" <?php checked(!empty($s['native_fallback_enabled'])); ?>> اگه همه‌ی روش‌های بالا شکست خوردند، با mail() پیش‌فرض خودِ هاست امتحان کن</label>
                            <p class="description">قابل‌اعتماد نیست (احتمال اسپم‌شدن بالاست) ولی برای مواردی مثل OTP که تأخیر قابل‌قبول نیست، بهتر از هیچی است.</p>
                        </td>
                    </tr>
                </table>
                <?php submit_button('ذخیره تنظیمات'); ?>
            </form>

            <hr>
            <h2>تست ارسال</h2>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="zsm_send_test">
                <?php wp_nonce_field('zsm_send_test', 'zsm_test_nonce'); ?>
                <input type="email" name="test_email" placeholder="ایمیل خودت برای تست" class="regular-text" required value="<?php echo esc_attr(wp_get_current_user()->user_email); ?>" dir="ltr">
                <?php submit_button('ارسال ایمیل تست', 'secondary'); ?>
            </form>
        </div>
        <?php
    }

    public static function handle_save_settings(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die('دسترسی غیرمجاز', 403);
        }
        check_admin_referer('zsm_save_settings', 'zsm_nonce');

        // امنیت (رفع نشتی رمز): فیلد رمز دیگر مقدار قبلی را echo نمی‌کند، پس
        // اگر خالی فرستاده شود یعنی ادمین نمی‌خواسته تغییرش بدهد — رمز قبلی
        // باید دست‌نخورده بماند.
        $submitted_password = trim(wp_unslash($_POST['password'] ?? ''));
        $existing            = self::get_raw_settings();

        $password_to_store = $existing['password'];
        if ($submitted_password !== '') {
            $password_to_store = (class_exists('Pas_Core_Crypto') && \Pas_Core_Crypto::is_configured())
                ? \Pas_Core_Crypto::wrap_encrypted($submitted_password)
                : $submitted_password;
        }

        $submitted_brevo_key = trim(wp_unslash($_POST['brevo_api_key'] ?? ''));
        $brevo_key_to_store  = $existing['brevo_api_key'];
        if ($submitted_brevo_key !== '') {
            $brevo_key_to_store = (class_exists('Pas_Core_Crypto') && \Pas_Core_Crypto::is_configured())
                ? \Pas_Core_Crypto::wrap_encrypted($submitted_brevo_key)
                : $submitted_brevo_key;
        }

        $submitted_backup_password = trim(wp_unslash($_POST['backup_password'] ?? ''));
        $backup_password_to_store  = $existing['backup_password'];
        if ($submitted_backup_password !== '') {
            $backup_password_to_store = (class_exists('Pas_Core_Crypto') && \Pas_Core_Crypto::is_configured())
                ? \Pas_Core_Crypto::wrap_encrypted($submitted_backup_password)
                : $submitted_backup_password;
        }

        update_option('zsm_settings', [
            'send_method'      => in_array(sanitize_key(wp_unslash($_POST['send_method'] ?? '')), ['smtp', 'brevo'], true) ? sanitize_key(wp_unslash($_POST['send_method'])) : 'smtp',
            'host'             => sanitize_text_field(wp_unslash($_POST['host'] ?? '')),
            'port'             => preg_replace('/\D+/', '', wp_unslash($_POST['port'] ?? '587')),
            'encryption'       => in_array(sanitize_key(wp_unslash($_POST['encryption'] ?? '')), ['tls', 'ssl'], true) ? sanitize_key(wp_unslash($_POST['encryption'])) : 'tls',
            'username'         => sanitize_text_field(wp_unslash($_POST['username'] ?? '')),
            'password'         => $password_to_store, // sanitize_text_field فاصله‌های داخلی رمز را می‌تواند به‌هم بزند
            'from_email'       => sanitize_email(wp_unslash($_POST['from_email'] ?? '')),
            'from_name'        => sanitize_text_field(wp_unslash($_POST['from_name'] ?? '')),
            'brevo_api_key'    => $brevo_key_to_store,
            'backup_enabled'   => isset($_POST['backup_enabled']) ? true : false,
            'backup_host'      => sanitize_text_field(wp_unslash($_POST['backup_host'] ?? '')),
            'backup_port'      => preg_replace('/\D+/', '', wp_unslash($_POST['backup_port'] ?? '587')),
            'backup_encryption' => in_array(sanitize_key(wp_unslash($_POST['backup_encryption'] ?? '')), ['tls', 'ssl'], true) ? sanitize_key(wp_unslash($_POST['backup_encryption'])) : 'tls',
            'backup_username'  => sanitize_text_field(wp_unslash($_POST['backup_username'] ?? '')),
            'backup_password'  => $backup_password_to_store,
            'native_fallback_enabled' => isset($_POST['native_fallback_enabled']) ? true : false,
        ]);

        wp_safe_redirect(admin_url('options-general.php?page=zsm-smtp-mailer&zsm_notice=saved'));
        exit;
    }

    public static function handle_send_test(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die('دسترسی غیرمجاز', 403);
        }
        check_admin_referer('zsm_send_test', 'zsm_test_nonce');

        $to = sanitize_email(wp_unslash($_POST['test_email'] ?? ''));

        if (!$to) {
            set_transient('zsm_last_error', 'ایمیل واردشده معتبر نیست.', 60);
            wp_safe_redirect(admin_url('options-general.php?page=zsm-smtp-mailer&zsm_notice=test_fail'));
            exit;
        }

        // پاک کردن خطای قبلی تا اگه این تلاش موفق بود، خطای تلاش قبلی رو دوباره
        // نشون نده (چون transient تا ۶۰ ثانیه زنده می‌مونه).
        delete_transient('zsm_last_error');

        add_action('wp_mail_failed', static function (\WP_Error $error): void {
            set_transient('zsm_last_error', $error->get_error_message(), 60);
        });

        $sent = wp_mail($to, 'ایمیل تست از پیوند امن', 'اگه این ایمیل رو می‌بینی، ارسال درست کار می‌کنه.');

        wp_safe_redirect(admin_url('options-general.php?page=zsm-smtp-mailer&zsm_notice=' . ($sent ? 'test_ok' : 'test_fail')));
        exit;
    }
}

/**
 * صف retry + جدول لاگ ارسال + صفحه‌ی گزارش ادمین.
 * هدف: وقتی همه‌ی روش‌های ارسال (اصلی/پشتیبان/native) شکست خوردند، ایمیل
 * گم نشود؛ ذخیره شود و با فاصله‌ی افزایشی دوباره امتحان شود، و کل ماجرا
 * بدون نیاز به داشبورد یک سرویس خارجی، از داخل خودِ وردپرس دیده شود.
 */
final class ZSM_Mail_Queue
{
    public const CRON_HOOK = 'zsm_process_mail_queue';

    /** فاصله‌ی هر تلاش مجدد بر حسب دقیقه، به ترتیب شماره‌ی تلاش (index 0 = بعد از اولین شکست). */
    private const BACKOFF_MINUTES = [5, 30, 120, 240, 480];

    private const MAX_ATTEMPTS = 5;

    /** مدت نگه‌داری ردیف‌های پایان‌یافته در جدول صف/لاگ (روز). */
    private const RETENTION_DAYS = 30;

    private static function table(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'zsm_mail_queue';
    }

    public static function create_table(): void
    {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $table           = self::table();
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            created_at DATETIME NOT NULL,
            next_retry_at DATETIME NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'pending',
            attempts SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            last_method VARCHAR(40) NULL,
            last_error TEXT NULL,
            to_address TEXT NOT NULL,
            subject TEXT NOT NULL,
            payload LONGTEXT NOT NULL,
            PRIMARY KEY  (id),
            KEY status (status),
            KEY next_retry_at (next_retry_at)
        ) {$charset_collate};";

        dbDelta($sql);

        if (!wp_next_scheduled(self::CRON_HOOK)) {
            wp_schedule_event(time() + 300, 'zsm_five_minutes', self::CRON_HOOK);
        }
    }

    public static function drop_table_and_cron(): void
    {
        wp_clear_scheduled_hook(self::CRON_HOOK);
    }

    public static function register_interval(array $schedules): array
    {
        $schedules['zsm_five_minutes'] = [
            'interval' => 300,
            'display'  => 'هر ۵ دقیقه (پیوند امن)',
        ];

        return $schedules;
    }

    /**
     * یک ارسال موفق یا نهایتاً ناموفق (بدون نیاز به retry، مثلاً چون هیچ
     * روشی تنظیم نشده) را فقط برای دیده‌شدن در گزارش، لاگ می‌کند.
     */
    public static function log(bool $sent, array $atts, ?string $method, ?string $error): void
    {
        global $wpdb;

        $wpdb->insert(self::table(), [
            'created_at'  => current_time('mysql'),
            'status'      => $sent ? 'sent' : 'given_up',
            'attempts'    => 1,
            'last_method' => $method,
            'last_error'  => $error,
            'to_address'  => self::stringify_to($atts),
            'subject'     => (string) ($atts['subject'] ?? ''),
            'payload'     => wp_json_encode($atts),
        ]);
    }

    /** ذخیره‌ی ایمیلی که همه‌ی تلاش‌های فوری‌اش شکست خورد، برای retry توسط cron. */
    public static function enqueue(array $atts, string $last_error): void
    {
        global $wpdb;

        $wpdb->insert(self::table(), [
            'created_at'    => current_time('mysql'),
            'next_retry_at' => self::next_retry_time(0),
            'status'        => 'pending',
            'attempts'      => 1,
            'last_error'    => $last_error,
            'to_address'    => self::stringify_to($atts),
            'subject'       => (string) ($atts['subject'] ?? ''),
            'payload'       => wp_json_encode($atts),
        ]);
    }

    private static function next_retry_time(int $attempts_so_far): string
    {
        $index   = min($attempts_so_far, count(self::BACKOFF_MINUTES) - 1);
        $minutes = self::BACKOFF_MINUTES[$index];

        return gmdate('Y-m-d H:i:s', time() + ($minutes * 60));
    }

    private static function stringify_to(array $atts): string
    {
        $to = $atts['to'] ?? '';
        return is_array($to) ? implode(', ', $to) : (string) $to;
    }

    /** توسط wp_cron هر ۵ دقیقه صدا زده می‌شود؛ ردیف‌های سررسیده را دوباره امتحان می‌کند. */
    public static function process_due_queue(): void
    {
        self::prune_old_rows();

        global $wpdb;
        $table = self::table();

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table} WHERE status = 'pending' AND next_retry_at <= %s ORDER BY id ASC LIMIT 20",
            current_time('mysql')
        ));

        foreach ($rows as $row) {
            self::retry_row($row);
        }
    }

    /**
     * پاکسازی دوره‌ای لاگ ارسال (1.5.3).
     *
     * جدول `zsm_mail_queue` هم صف retry است و هم لاگ ارسال: هر ایمیل موفق و
     * هر ایمیل شکست‌خورده یک ردیف با `payload` کامل (شامل متن/HTML ایمیل) در آن
     * نوشته می‌شود و **هیچ پاکسازی‌ای نداشت** — یعنی رشد بی‌پایان دیتابیس و
     * نگه‌داری بی‌مدت متن ایمیل‌ها (که می‌تواند داده‌ی شخصی داشته باشد).
     *
     * حالا حداکثر یک بار در روز، ردیف‌های **پایان‌یافته** (`sent`/`given_up`)
     * قدیمی‌تر از ۳۰ روز حذف می‌شوند. ردیف‌های `pending` (در انتظار retry)
     * هرچقدر قدیمی باشند دست‌نخورده می‌مانند تا ایمیلی گم نشود.
     */
    private static function prune_old_rows(): void
    {
        if (get_transient('zsm_queue_pruned')) {
            return;
        }
        set_transient('zsm_queue_pruned', 1, DAY_IN_SECONDS);

        global $wpdb;
        $cutoff = wp_date('Y-m-d H:i:s', time() - (self::RETENTION_DAYS * DAY_IN_SECONDS));

        $wpdb->query($wpdb->prepare(
            'DELETE FROM ' . self::table() . " WHERE status IN ('sent','given_up') AND created_at < %s",
            $cutoff
        ));
    }

    private static function retry_row(object $row): void
    {
        global $wpdb;

        $atts = json_decode((string) $row->payload, true);
        if (!is_array($atts)) {
            $wpdb->update(self::table(), ['status' => 'given_up', 'last_error' => 'payload خراب بود'], ['id' => $row->id]);
            return;
        }

        $settings = ZSM_Smtp_Mailer::get_settings();
        $sent     = ZSM_Smtp_Mailer::retry_delivery_chain($atts, $settings, $used_method, $error);

        $attempts = (int) $row->attempts + 1;

        if ($sent) {
            $wpdb->update(self::table(), [
                'status'      => 'sent',
                'attempts'    => $attempts,
                'last_method' => $used_method,
                'last_error'  => null,
            ], ['id' => $row->id]);
            return;
        }

        if ($attempts >= self::MAX_ATTEMPTS) {
            $wpdb->update(self::table(), [
                'status'     => 'given_up',
                'attempts'   => $attempts,
                'last_error' => $error,
            ], ['id' => $row->id]);
            return;
        }

        $wpdb->update(self::table(), [
            'attempts'      => $attempts,
            'next_retry_at' => self::next_retry_time($attempts),
            'last_error'    => $error,
        ], ['id' => $row->id]);
    }

    /** دکمه‌ی «امتحان دوباره الان» توی صفحه‌ی گزارش. */
    public static function handle_retry_now(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die('دسترسی غیرمجاز', 403);
        }
        check_admin_referer('zsm_retry_now');

        global $wpdb;
        $id  = (int) ($_GET['id'] ?? 0);
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM " . self::table() . " WHERE id = %d", $id));

        if ($row) {
            self::retry_row($row);
        }

        wp_safe_redirect(admin_url('options-general.php?page=zsm-mail-log&zsm_notice=retried'));
        exit;
    }

    public static function render_log_page(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die('دسترسی غیرمجاز', 403);
        }

        global $wpdb;
        $rows   = $wpdb->get_results('SELECT * FROM ' . self::table() . ' ORDER BY id DESC LIMIT 100');
        $notice = isset($_GET['zsm_notice']) ? sanitize_key($_GET['zsm_notice']) : '';
        ?>
        <div class="wrap zsm-admin">
            <h1>گزارش ایمیل‌ها</h1>
            <p class="description">۱۰۰ تلاش ارسال اخیر — چه از طریق SMTP/Brevo موفق شدند، چه در صف retry هستند، چه نهایتاً شکست خوردند.</p>

            <?php if ($notice === 'retried') : ?>
                <div class="notice notice-info is-dismissible"><p>تلاش مجدد انجام شد — وضعیت ردیف را در جدول زیر ببین.</p></div>
            <?php endif; ?>

            <table class="widefat striped">
                <thead>
                    <tr>
                        <th>زمان</th>
                        <th>گیرنده</th>
                        <th>موضوع</th>
                        <th>وضعیت</th>
                        <th>روش موفق / آخرین خطا</th>
                        <th>تلاش‌ها</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($rows)) : ?>
                    <tr><td colspan="7">هنوز هیچ ایمیلی لاگ نشده.</td></tr>
                <?php endif; ?>
                <?php foreach ($rows as $row) : ?>
                    <tr>
                        <td><?php echo esc_html($row->created_at); ?></td>
                        <td dir="ltr"><?php echo esc_html($row->to_address); ?></td>
                        <td><?php echo esc_html($row->subject); ?></td>
                        <td>
                            <?php if ($row->status === 'sent') : ?>
                                <span style="color:#1a7e33;font-weight:600;">ارسال شد</span>
                            <?php elseif ($row->status === 'pending') : ?>
                                <span style="color:#996800;font-weight:600;">در انتظار تلاش مجدد (<?php echo esc_html($row->next_retry_at); ?>)</span>
                            <?php else : ?>
                                <span style="color:#a30000;font-weight:600;">نهایتاً شکست خورد</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo esc_html($row->last_method ?: ($row->last_error ?: '—')); ?></td>
                        <td><?php echo esc_html((string) $row->attempts); ?></td>
                        <td>
                            <?php if ($row->status !== 'sent') : ?>
                                <a class="button button-small" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=zsm_retry_now&id=' . (int) $row->id), 'zsm_retry_now')); ?>">امتحان دوباره الان</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }
}

// این فیلتر عمداً بدون قید plugins_loaded و در سطح فایل ثبت می‌شود: در لحظه‌ی
// فعال‌سازی پلاگین، activate_plugin() این فایل را مستقیم include می‌کند، در
// حالی که plugins_loaded آن request از قبل اجرا شده — پس اگر این فیلتر داخل
// init() (هوک‌شده به plugins_loaded) می‌بود، create_table() که بلافاصله بعدش
// اجرا می‌شود اصلاً بازه‌ی «هر ۵ دقیقه» را نمی‌شناخت و wp_schedule_event
// بی‌صدا شکست می‌خورد.
add_filter('cron_schedules', [__NAMESPACE__ . '\\ZSM_Mail_Queue', 'register_interval']);

register_activation_hook(__FILE__, [__NAMESPACE__ . '\\ZSM_Mail_Queue', 'create_table']);
register_deactivation_hook(__FILE__, [__NAMESPACE__ . '\\ZSM_Mail_Queue', 'drop_table_and_cron']);

add_action('plugins_loaded', [__NAMESPACE__ . '\\ZSM_Smtp_Mailer', 'init']);
