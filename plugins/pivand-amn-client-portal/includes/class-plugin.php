<?php

declare(strict_types=1);

namespace ZoojDarman\ClientPortal;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Composition root. هر ماژول مستقل ساخته و اینجا wire می‌شود؛ ماژول‌ها
 * از هم فقط از طریق فیلتر/اکشن وردپرسی خبر دارند، نه include مستقیم.
 */
final class Plugin
{
    private static ?Plugin $instance = null;

    private Security $security;
    private OTP_Auth $otp_auth;
    private Login_Service $login_service;
    private Client_Panel $client_panel;
    private Booking $booking;
    private Messages $messages;
    private Survey $survey;
    private Intake $intake;
    private Leads $leads;
    private Cron $cron;
    private Quiz_Bridge $quiz_bridge;
    private Quiz_Funnel $quiz_funnel;
    private Admin $admin;
    private Client_Error_Log $client_error_log;

    /**
     * توسط enqueue_frontend_assets() (هوک wp_enqueue_scripts) ست می‌شود و
     * توسط print_color_scheme_meta() (هوک wp_head، اجراشده بلافاصله بعد)
     * خوانده می‌شود، تا متاتگ color-scheme فقط روی همان صفحاتی چاپ شود که
     * واقعاً استایل پنل رویشان enqueue شده — نه بی‌قید و شرط کل سایت.
     */
    private bool $needs_panel_assets = false;

    private function __construct()
    {
    }

    public static function instance(): Plugin
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public static function activate(): void
    {
        Installer::create_or_upgrade_tables();
        Installer::migrate_from_legacy_plugin();
        Cron::schedule_events();
        Installer::ensure_booking_page_exists();
        Installer::seed_default_sms_templates();
    }

    public static function deactivate(): void
    {
        Cron::clear_events();
    }

    public function boot(): void
    {
        $this->security     = new Security();
        // باید پیش از OTP_Auth ساخته شود که در process_verify_otp به
        // Login_Service::build_redirect_url وابسته است (وابستگی فقط از طریق
        // متد استاتیک است، نه constructor injection، اما ترتیب منطقی رعایت
        // شده تا خواندنش گمراه‌کننده نباشد).
        $this->login_service = new Login_Service();
        $this->otp_auth     = new OTP_Auth($this->security);
        $this->leads        = new Leads();
        $this->booking      = new Booking($this->security, $this->leads);
        $this->messages     = new Messages($this->security);
        $this->survey       = new Survey($this->security);
        $this->intake       = new Intake($this->security);
        $this->quiz_bridge  = new Quiz_Bridge();
        $this->quiz_funnel  = new Quiz_Funnel($this->security, $this->leads);
        $this->client_panel = new Client_Panel($this->booking, $this->messages, $this->survey, $this->quiz_bridge, $this->intake);
        $this->cron         = new Cron($this->leads, $this->booking);
        $this->admin        = new Admin($this->booking, $this->messages, $this->survey, $this->quiz_funnel, $this->intake);
        // خطاهای JS پنل مراجعین را مستقیم به همان audit_log() موجود (تب «لاگ
        // امنیتی» در پیشخوان) می‌فرستد؛ نیازی به صفحه/جدول جدید نیست.
        $this->client_error_log = new Client_Error_Log($this->security);

        add_action('init', [$this, 'block_wp_admin_for_clients']);
        add_filter('show_admin_bar', [$this, 'hide_admin_bar_for_clients']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend_assets']);
        add_action('wp_head', [$this, 'print_color_scheme_meta'], 1);
        add_action('wp_footer', [$this, 'print_late_style_fallback'], 1);
        add_filter('template_include', [$this, 'maybe_use_blank_template'], 999);

        // پل به سایر افزونه‌ها (مثلاً پیوند امن — آزمون‌های سازمانی) برای
        // گرفتن آدرس واقعی صفحه‌ی پنل مراجع، دقیقاً به همان روش Quiz_Bridge:
        // فقط از طریق فیلتر، بدون include مستقیم بین پلاگین‌ها.
        add_filter('zdp_get_client_panel_url', [$this, 'filter_client_panel_url']);

        // شبکه‌ی ایمنیِ آپگرید: register_activation_hook فقط موقع فعال‌سازی
        // صریح اجرا می‌شود، نه وقتی فایل‌های پلاگین جای‌گذاری می‌شوند بدون
        // غیرفعال/فعال کردن دستی (دقیقاً همان روشی که فایل‌های آپدیت‌شده تحویل
        // داده می‌شوند). بدون این قلاب، فیکس‌های schema (مثل حذف UNIQUE KEY
        // مشکل‌دار رزرو نوبت) روی سایتی که از قبل نصب شده هیچ‌وقت اجرا نمی‌شد.
        add_action('plugins_loaded', function (): void {
            if (get_option('zdp_db_version') !== ZDP_DB_VERSION) {
                Installer::create_or_upgrade_tables();
                Installer::migrate_from_legacy_plugin();
                Installer::ensure_booking_page_exists();
            }
        }, 20);

        // خودترمیمیِ مستقل از آپگرید نسخه: اگر ادمین صفحه‌ی /panel/ را از
        // داخل وردپرس پاک/Trash کند، دیگر منتظر یک آپدیت جدید (که db_version
        // را عوض کند) نمی‌مانیم. این چک هر بار اجرا می‌شود، اما با یک
        // transient یک‌ساعته محدود شده تا روی هر page-view یک کوئری اضافه
        // به دیتابیس زده نشود.
        add_action('plugins_loaded', function (): void {
            if (get_transient('zdp_panel_page_check')) {
                return;
            }
            set_transient('zdp_panel_page_check', 1, HOUR_IN_SECONDS);
            Installer::ensure_booking_page_exists();
        }, 20);

        // ترمیم مستقل survey برای سایت‌هایی که پیش از رفع باگ ستون‌های
        // migrate_from_legacy_plugin() پرچم zdp_legacy_migrated را با
        // موفقیت کاذب دریافت کرده‌اند؛ آپشن اختصاصی خودش (zdp_surveys_reconciled)
        // را دارد و به db_version یا zdp_legacy_migrated وابسته نیست، پس فقط
        // یک‌بار در کل عمر سایت واقعاً کار می‌کند.
        add_action('plugins_loaded', function (): void {
            if (get_transient('zdp_surveys_reconcile_check')) {
                return;
            }
            set_transient('zdp_surveys_reconcile_check', 1, HOUR_IN_SECONDS);
            Installer::reconcile_legacy_surveys();
        }, 20);

        // ترمیم مستقل کد پترن یادآوری نوبت (zdp_sms_reminder_body_id):
        // add_option در seed_default_sms_templates روی سایت‌های از قبل
        // نصب‌شده اثری ندارد (چون آپشن از قبل با مقدار خالی وجود دارد)، در
        // حالی‌که پترن واقعی («۴۹۸۳۲۸ — الگوی یادآوری») از قبل در پنل
        // ملی‌پیامک ساخته و تایید شده. یک‌بار، فقط اگر هنوز خالی است، مقدار
        // درست را می‌نشانیم؛ اگر درمانگر خودش قبلاً مقدار دیگری ثبت کرده،
        // دست نمی‌زنیم.
        add_action('plugins_loaded', function (): void {
            if (get_option('zdp_reminder_body_id_healed')) {
                return;
            }
            if ( (string) get_option('zdp_sms_reminder_body_id', '') === '' ) {
                update_option('zdp_sms_reminder_body_id', '498328');
            }
            update_option('zdp_reminder_body_id_healed', 1);
        }, 20);

        // نکته (رفع بدهی فنی): load_plugin_textdomain() که قبلاً اینجا بود
        // حذف شد. این افزونه صراحتاً برای یک سایت تک‌زبانه‌ی فارسی نوشته
        // شده؛ تقریباً همه‌ی متن‌های UI (به‌جز دو مورد در Security/Admin)
        // مستقیم فارسی هاردکد شده‌اند، نه با __()/_e() پیچیده شده. پوشه‌ی
        // languages/ هم هرگز وجود نداشته. یعنی این فراخوانی همیشه بی‌اثر
        // بود (هیچ فایل .mo ای برای لود کردن نبود) و صرفاً ظاهر یک زیرساخت
        // ترجمه را می‌ساخت که واقعاً کار نمی‌کرد. اگر روزی نیاز واقعی به
        // چندزبانه‌شدن پیش آمد، باید هم این فراخوانی برگردد و هم کل UI با
        // __()/_e() پیچیده شود — یک تصمیم و کار یک‌جا، نه نیمه‌کاره.
    }

    /**
     * مراجعین (نقش subscriber ساخته‌شده توسط این پلاگین) هرگز نباید به
     * wp-admin دسترسی داشته باشند، حتی با URL مستقیم.
     */
    public function block_wp_admin_for_clients(): void
    {
        if (!is_admin() || wp_doing_ajax() || !is_user_logged_in()) {
            return;
        }

        if (current_user_can('manage_options') || current_user_can('edit_posts')) {
            return;
        }

        if (zdp_get_user_mobile(get_current_user_id()) !== '') {
            wp_safe_redirect(zdp_panel_url());
            exit;
        }
    }

    public function hide_admin_bar_for_clients(bool $show): bool
    {
        if (is_user_logged_in() && zdp_get_user_mobile(get_current_user_id()) !== '') {
            return false;
        }

        return $show;
    }

    /**
     * مصرف‌کننده: فیلتر zdp_get_client_panel_url (نگاه کنید به بالای boot()).
     * آدرس واقعی صفحه‌ی پنل مراجع (permalink صفحه‌ای که شورت‌کد
     * [zdp_client_panel] رویش قرار دارد) را برمی‌گرداند تا افزونه‌های دیگر
     * (مثلاً پیوند امن — آزمون‌های سازمانی) بتوانند مراجع را برای ورود به
     * آنجا هدایت کنند، بدون این‌که آدرس را هاردکد کنند یا از include مستقیم
     * استفاده کنند. اگر صفحه‌ی پنل هنوز ساخته/تنظیم نشده، مقدار پیش‌فرض
     * zdp_panel_url() برگردانده می‌شود (همان مقداری که در جای دیگر این
     * پلاگین هم به‌عنوان fallback استفاده می‌شود).
     */
    public function filter_client_panel_url(string $default_url): string
    {
        $panel_page_id = (int) get_option('zdp_panel_page_id');
        if ($panel_page_id > 0) {
            $link = get_permalink($panel_page_id);
            if ($link) {
                return $link;
            }
        }

        return $default_url !== '' ? $default_url : zdp_panel_url();
    }

    /**
     * صفحه‌ی پنل مراجعان (شناسه‌ی ثابت zdp_panel_page_id) و هر صفحه‌ای که
     * شورت‌کد مستقل نظرسنجی [zdp_survey_page] را داشته باشد، بدون
     * هدر/فوتر قالب رندر می‌شوند — یک قالب حداقلی که فقط wp_head/the_content/
     * wp_footer را نگه می‌دارد تا استایل/اسکریپت افزونه‌ها همچنان کار کنند.
     * صفحه‌ی کوییز (?zdp_quiz_landing=TOPIC) نیازی به این فیلتر ندارد، چون
     * از قبل در Quiz_Funnel::render_landing_page به‌طور کامل مستقل از
     * template_redirect رندر و exit می‌شود.
     */
    public function maybe_use_blank_template(string $template): string
    {
        if (!is_singular()) {
            return $template;
        }

        $panel_page_id = (int) get_option('zdp_panel_page_id');
        $post          = get_post();

        $is_panel_page = $panel_page_id > 0 && is_page($panel_page_id);
        // رفع باگ کشف‌شده (ناهماهنگی با enqueue_frontend_assets): این چک قبلاً
        // فقط has_shortcode() روی post_content خام را می‌دید. یعنی وقتی
        // [zdp_survey_page] از طریق صفحه‌سازی مثل المنتور/بریکس درج می‌شد
        // (که چیدمان را در متای جدا ذخیره می‌کنند، نه post_content)، این متد
        // آن را تشخیص نمی‌داد: panel.css با fallback همان‌جا enqueue می‌شد، ولی
        // قالب همچنان قالب کامل تم (هدر/فوتر) بود — دقیقاً برخلاف قرارداد
        // «صفحه‌ی مستقل ثبت نظر بدون هدر/فوتر تم» که در class-survey.php مستند
        // است. الان از همان تشخیص‌گر مشترک enqueue_frontend_assets استفاده
        // می‌شود تا هر دو متد دقیقاً یک نتیجه بدهند.
        $has_survey = $post && $this->post_has_shortcode($post, ['zdp_survey_page']);

        if (!$is_panel_page && !$has_survey) {
            return $template;
        }

        $custom = ZDP_PLUGIN_DIR . 'templates/panel-blank.php';

        return file_exists($custom) ? $custom : $template;
    }

    /**
     * تشخیص وجود هر یک از شورت‌کدهای داده‌شده در یک پست، هم در post_content
     * خام و هم در دو صفحه‌ساز رایج (المنتور، بریکس بیلدر) که چیدمان را در
     * متای جدا ذخیره می‌کنند. منطق مشترک بین enqueue_frontend_assets() و
     * maybe_use_blank_template() تا این دو هرگز از هم واگرا نشوند.
     *
     * @param array<int,string> $tags
     */
    private function post_has_shortcode(\WP_Post $post, array $tags): bool
    {
        foreach ($tags as $tag) {
            if (has_shortcode($post->post_content, $tag)) {
                return true;
            }
        }

        if (get_post_meta($post->ID, '_elementor_edit_mode', true) === 'builder') {
            $elementor_data = (string) get_post_meta($post->ID, '_elementor_data', true);
            foreach ($tags as $tag) {
                if ($elementor_data !== '' && strpos($elementor_data, '[' . $tag) !== false) {
                    return true;
                }
            }
        }

        global $wpdb;
        $raw_bricks_data = $wpdb->get_var($wpdb->prepare(
            "SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = '_bricks_page_content_2' LIMIT 1",
            $post->ID
        ));

        if ($raw_bricks_data) {
            foreach ($tags as $tag) {
                if (strpos($raw_bricks_data, '[' . $tag) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function enqueue_frontend_assets(): void
    {
        // استایل/اسکریپت اینجا register می‌شوند؛ enqueue واقعی هم همین‌جا (پایین
        // همین تابع، نه داخل callback شورت‌کدها) و به‌صورت شرطی انجام می‌شود تا
        // در صفحاتی که هیچ‌کدام از شورت‌کدهای این خانواده را ندارند، بار اضافه
        // لود نشود — دلیل اینکه enqueue باید همین‌جا باشد نه داخل شورت‌کد را
        // پایین همین تابع ببینید.
        // فونت برند مشترک: اگر پیوند امن — هسته مشترک فعال باشد، از نسخه‌ی
        // سلف‌هاست‌شده‌ی آن استفاده می‌کنیم (به‌جای اتکای بی‌صدا به این‌که
        // یک پلاگین دیگر آن را جایی لود کرده باشد). اگر core فعال نباشد،
        // panel.css همچنان با fallback خودش (Tahoma/Arial) درست کار می‌کند.
        $font_deps = [];
        // اگر کیت رابط کاربری فعال است، فونت وزیرمتن را همان کیت (با فایل‌های
        // واقعی woff2) تأمین می‌کند؛ در این حالت هندل فونت هسته اضافه نمی‌شود
        // تا درخواست‌های ۴۰۴ فایل فونت تکرار نشوند. اگر کیت نباشد، رفتار
        // قبلی (فونت هسته) دست‌نخورده باقی می‌ماند.
        if (class_exists('Pas_Core_Fonts') && !class_exists('PUI')) {
            $font_deps[] = \Pas_Core_Fonts::handle();
        }
        // توکن‌های طراحی مشترک اکوسیستم (رنگ/شعاع/سایه): اگر پیوند امن —
        // هسته مشترک فعال باشد، panel.css به‌جای پالت مستقل خودش از
        // متغیرهای --pivand-* یکپارچه استفاده می‌کند (نگاه کنید به
        // var(--pivand-*, fallback) در ابتدای panel.css).
        if (class_exists('Pas_Core_Tokens')) {
            $font_deps[] = \Pas_Core_Tokens::handle();
        }

        wp_register_style('zdp-panel', ZDP_PLUGIN_URL . 'assets/css/panel.css', $font_deps, ZDP_VERSION);

        // گام ۲ فاز ۱۲ — صفحهٔ ورود روی کیت رابط کاربری.
        // login.css عمداً جدا از panel.css است تا استایل پنلِ پس از ورود دست‌نخورده
        // بماند؛ اگر کیت پیوند امن نصب/فعال نباشد، همین فایل با fallback خودش کار می‌کند.
        $login_deps = ['zdp-panel'];
        if (class_exists('PUI')) {
            $login_deps[] = \PUI::handle('fonts');
            $login_deps[] = \PUI::handle('tokens');
            $login_deps[] = \PUI::handle('components');
        }
        wp_register_style('zdp-login', ZDP_PLUGIN_URL . 'assets/css/login.css', $login_deps, ZDP_VERSION);

        // پوستهٔ برند پنل (گام ۳): فقط ساختار موجود را روی توکن‌های کیت نگاشت
        // می‌کند؛ بعد از panel.css بارگذاری می‌شود و به .zdp-panel-shell محدود است.
        $skin_deps = ['zdp-panel'];
        if (class_exists('PUI')) {
            $skin_deps[] = \PUI::handle('fonts');
            $skin_deps[] = \PUI::handle('tokens');
            $skin_deps[] = \PUI::handle('components');
        }
        wp_register_style('zdp-panel-skin', ZDP_PLUGIN_URL . 'assets/css/panel-skin.css', $skin_deps, ZDP_VERSION);
        wp_register_script('zdp-panel', ZDP_PLUGIN_URL . 'assets/js/panel.js', [], ZDP_VERSION, true);

        // رفع باگ کشف‌شده (ناسازگاری تقویم نوبت‌دهی بین دستگاه‌ها): تقویم
        // جلالی سمت کلاینت (panel.js) قبلاً «امروز» را با new Date() مرورگر
        // محاسبه می‌کرد. چون new Date() به ساعت و منطقه‌زمانی خودِ دستگاه
        // کاربر وابسته است (نه ساعت سایت)، روی گوشی‌هایی که ساعت/تایم‌زون‌شان
        // درست تنظیم نشده (که در موبایل خیلی رایج است) «امروز» اشتباه
        // محاسبه می‌شد و روزهای گذشته/آینده غلط فعال یا غیرفعال می‌شدند —
        // دقیقاً همان چیزی که باعث می‌شد شورت‌کد نوبت‌دهی «هر دستگاه یک‌جور»
        // نمایش داده شود، در حالی که پیشخوان ادمین (که سمت سرور رندر می‌شود)
        // درست کار می‌کرد. راه‌حل: «امروزِ» معتبر را یک‌بار روی سرور، با
        // منطقه‌زمانی سایت (wp_timezone/current_time)، محاسبه و به JS پاس
        // می‌دهیم تا هیچ‌جای این تقویم دیگر به ساعت دستگاه کاربر متکی نباشد.
        $today_gregorian = current_time('Y-m-d');
        [$gy, $gm, $gd]  = array_map('intval', explode('-', $today_gregorian));
        $today_jalali    = Jalali::to_jalali($gy, $gm, $gd);

        wp_localize_script('zdp-panel', 'ZDP', [
            'ajax_url'   => admin_url('admin-ajax.php'),
            'nonce'      => wp_create_nonce('zdp_frontend_nonce'),
            // مسیر REST برای OTP (rest_url ساخته‌شده از home_url، نه siteurl —
            // همان رفتار پیش‌فرض توابع core وردپرس از نسخه‌ی ۴.۴ به بعد).
            'rest_url'   => esc_url_raw(rest_url('zdp/v1/')),
            'rest_nonce' => wp_create_nonce('wp_rest'),
            // «امروز» رسمی سرور — نه دستگاه کاربر. آرایه [jy, jm, jd].
            'today_jalali'    => $today_jalali,
            'today_gregorian' => $today_gregorian,
            // آدرس واقعی صفحه‌ی پنل (نه یک مسیر ثابتِ حدسی '/panel/' در
            // جاوااسکریپت) — برای هدایتِ مراجعِ مهمان از شورت‌کد عمومی
            // نوبت‌دهی به پنل، جایی که موبایل+کد تایید را وارد می‌کند.
            'panel_url'       => zdp_panel_url(),
            'i18n'     => [
                'network_error'   => 'خطا در ارتباط با سرور. لطفاً اتصال اینترنت خود را بررسی کرده و دوباره تلاش کنید.',
                'sending'         => 'در حال ارسال...',
                'checking'        => 'در حال بررسی...',
            ],
        ]);

        // رفع باگ کشف‌شده (تایمینگ enqueue): تا اینجا فقط register + localize
        // انجام شده. enqueue واقعیِ استایل عمداً «اینجا» و به‌صورت شرطی انجام
        // می‌شود، نه داخل خودِ callback شورت‌کدها. دلیل: wp_head() (و در نتیجه
        // wp_print_styles که صف استایل را چاپ می‌کند) همیشه قبل از the_content()
        // اجرا می‌شود — تم اول get_header() را صدا می‌زند، بعد وارد Loop می‌شود.
        // پس اگر wp_enqueue_style('zdp-panel') داخل render() یک شورت‌کد (که در
        // زمان the_content() اجرا می‌شود) صدا زده شود، صفِ چاپ استایل قبلاً
        // بسته شده و panel.css هیچ‌وقت روی صفحه چاپ نمی‌شود — برخلاف اسکریپت
        // (که چون in_footer=true دارد، wp_footer دوباره صف را چک و چاپ می‌کند
        // و بی‌سروصدا کار می‌کند)، هسته‌ی وردپرس معادلی برای چاپ دیرهنگام CSS
        // در فوتر ندارد. نتیجه‌ی عملی: صفحه‌ی [zdp_survey_page] و بلوک
        // [zdp_testimonials] روی صفحات معمولی
        // کاملاً بدون استایل (raw HTML) رندر می‌شدند، هرچند جاوااسکریپت‌شان
        // درست کار می‌کرد. همین مشکل روی [zdp_client_panel]/[custom_client_panel]
        // و [zdp_public_quiz]/[cpp_public_quiz] هم صادق است (صفحه‌ی کوییز از
        // طریق ?zdp_quiz_landing=TOPIC چون کاملاً مستقل و با wp_head دستی خودش
        // رندر می‌شود، این مشکل را ندارد).
        //
        // راه‌حل (نسخه‌ی اول): همان‌جایی که get_post() هنوز محتوای خام
        // post_content را در دسترس دارد (این هوک، پیش از wp_head) با
        // has_shortcode() چک می‌کنیم.
        //
        // رفع باگ گزارش‌شده (این نسخه): محدودیت شناخته‌شده‌ای که تا اینجا
        // فقط در کامنت مستند بود، حالا واقعاً رفع شد. صفحه‌سازهایی مثل
        // المنتور محتوا را در post_content ذخیره نمی‌کنند، بلکه در متای
        // جداگانه‌ی `_elementor_data` (یک رشته‌ی JSON از تمام ویجت‌ها).
        // یعنی has_shortcode($post->post_content, ...) روی چنین صفحه‌ای
        // همیشه false برمی‌گشت — panel.css هرگز enqueue نمی‌شد و
        // [zdp_testimonials]/[zdp_booking_widget]/... به‌صورت کاملاً خام و
        // بی‌استایل رندر می‌شدند (مثلاً مارکیِ نظرات، بدون CSS، فقط یک
        // لیست ساکن به‌نظر می‌رسید — نه یک باگ در خودِ انیمیشن).
        //
        // چون شورت‌کد داخل JSON المنتور هم عیناً به‌صورت رشته‌ی متنی
        // ("[zdp_testimonials]") ذخیره می‌شود (چه در ویجت اختصاصی
        // Shortcode، چه در یک فیلد متنیِ دیگر)، کافی است همان رشته را با
        // strpos() داخل کل JSON جست‌وجو کنیم — نیازی به parse کامل ساختار
        // ویجت‌های المنتور نیست.
        //
        // تکمیل (این بخش): همان فال‌بک برای بریکس بیلدر (متای
        // `_bricks_page_content_2`، رشته‌ی serialize-شده‌ی PHP) هم پایین‌تر
        // اضافه شد؛ قبلاً فقط المنتور پیاده‌سازی شده بود هرچند این کامنت از
        // ابتدا دیوی/بریکس را هم وعده داده بود. دیوی نیازی به فال‌بک جدا
        // ندارد چون شورت‌کد را عیناً در post_content ذخیره می‌کند.
        $post = is_singular() ? get_post() : null;

        $shortcode_tags = [
            'zdp_client_panel', 'zdp_survey_page',
            'zdp_testimonials', 'zdp_booking_widget', 'zdp_public_quiz',
        ];

        // منطق تشخیص (post_content + فال‌بک المنتور/بریکس) به post_has_shortcode()
        // منتقل شد تا همین‌جا و maybe_use_blank_template() همیشه یک نتیجه‌ی
        // یکسان بدهند (رفع باگ کشف‌شده: قبلاً این دو از هم واگرا بودند).
        $post_has_shortcode = $post && $this->post_has_shortcode($post, $shortcode_tags);

        $needs_panel_assets =
            ((int) get_option('zdp_panel_page_id') > 0 && is_page((int) get_option('zdp_panel_page_id')))
            || $post_has_shortcode;

        $this->needs_panel_assets = $needs_panel_assets;

        if ($needs_panel_assets) {
            wp_enqueue_style('zdp-panel');
            wp_enqueue_script('zdp-panel');
        }

        // استایل صفحهٔ ورود (و کیت رابط کاربری) فقط جایی بارگذاری می‌شود که
        // واقعاً پنل مراجع رندر می‌شود — نه روی صفحه‌های آزمون/نوبت‌دهی/نظرسنجی.
        // این‌طور هیچ CSS اضافه‌ای به بقیهٔ سایت تحمیل نمی‌شود.
        $needs_login_assets = $needs_panel_assets && (
            ((int) get_option('zdp_panel_page_id') > 0 && is_page((int) get_option('zdp_panel_page_id')))
            || ($post && $this->post_has_shortcode($post, ['zdp_client_panel']))
        );

        if ($needs_login_assets) {
            wp_enqueue_style('zdp-login');
            wp_enqueue_style('zdp-panel-skin');

            if (class_exists('PUI')) {
                \PUI::enqueue('auth');
            }

            // پوسته‌ی ۲ (Calm Intelligence): بعد از skin بارگذاری می‌شود و
            // فقط داخل .zdp-panel-shell اثر دارد؛ توکن‌های --zdp-* را به پالت
            // برند نگاشت می‌کند تا مارک‌آپ پنل دست‌نخورده بماند.
            wp_enqueue_style(
                'zdp-panel-v2',
                ZDP_PLUGIN_URL . 'assets/css/panel-v2.css',
                ['zdp-panel-skin'],
                ZDP_VERSION
            );
        }

        // یادداشت: enqueue داخل خودِ render()/render_shortcode() هر ماژول
        // (Client_Panel، Quiz_Funnel، Survey) عمداً دست‌نخورده باقی مانده —
        // بی‌ضرر است (wp_enqueue_style/script روی handle از قبل enqueue‌شده
        // نُوآپ است) و به‌عنوان fallback برای مواردی که has_shortcode() بالا
        // شورت‌کد را (مثلاً به‌خاطر صفحه‌ساز) تشخیص نداده، دست‌کم اسکریپت
        // (که در فوتر هنوز چاپ می‌شود) را تضمین می‌کند.
    }

    /**
     * رفع باگ کشف‌شده: روی موبایل با دارک‌مود سیستمی فعال (مخصوصاً کروم/
     * اندروید که یک موتور heuristic «force dark» دارد)، مرورگر وقتی هیچ
     * نشانه‌ی صریحی نبیند که صفحه خودش تم را مدیریت می‌کند، رنگ متن‌ها را
     * خودش حدس می‌زند و تیره می‌کند — حتی وقتی پس‌زمینه هنوز روشن مانده،
     * یعنی متن عملاً غیرقابل‌خواندن می‌شود. تنظیم `color-scheme: light`
     * در CSS (panel.css) این را برای رندر عناصر فرم/جعبه‌های بومی مرورگر
     * حل می‌کند، ولی موتور force-dark کروم برای غیرفعال‌کردن کامل خودش،
     * وجود همین متاتگ HTML را هم چک می‌کند؛ بدون آن، حتی با CSS بالا،
     * ممکن است هنوز heuristic خودش را روی برخی عناصر اعمال کند.
     *
     * فقط وقتی چاپ می‌شود که واقعاً استایل پنل هم enqueue شده (همان شرط
     * enqueue_frontend_assets)، تا بدون نیاز، متاتگی روی کل سایت (که شاید
     * عمداً دارک‌مود قالب خودش را دارد) تحمیل نشود.
     */
    public function print_color_scheme_meta(): void
    {
        if (!$this->needs_panel_assets) {
            return;
        }

        echo '<meta name="color-scheme" content="light">' . "\n";
    }

    /**
     * رفع «محدودیت شناخته‌شده» که چند نسخه در CHANGELOG مستند بود: وقتی
     * شورت‌کد از طریق صفحه‌سازی مثل المنتور/دیوی/بریکس درج شود (که چیدمان
     * را در متای جدا، نه post_content، ذخیره می‌کند)، has_shortcode() در
     * enqueue_frontend_assets() آن را تشخیص نمی‌دهد، پس panel.css هیچ‌وقت
     * در <head> enqueue نمی‌شود — نتیجه: صفحه با HTML خام و کاملاً بی‌استایل
     * رندر می‌شود (زشت، بدون رنگ/فونت/چیدمان).
     *
     * هر ماژول (Client_Panel::render، Survey::render_shortcode/
     * render_testimonials_shortcode، Quiz_Funnel::render_quiz_shortcode)
     * از قبل، مستقل از این تشخیص، wp_enqueue_style('zdp-panel') را به‌عنوان
     * fallback خودش صدا می‌زند — اما چون این فراخوانی داخل the_content()
     * اتفاق می‌افتد، یعنی بعد از این‌که وردپرس صف استایل‌های <head> را قبلاً
     * چاپ و بسته، آن fallback به‌تنهایی کافی نیست (بر خلاف اسکریپت که چون
     * in_footer=true دارد، wp_footer دوباره صف را چاپ می‌کند).
     *
     * این متد همان وضعیت را دیرتر، در wp_footer، دستی جبران می‌کند: اگر
     * handle به‌واسطه‌ی همان fallback داخل render() به صف اضافه شده باشد ولی
     * هنوز چاپ نشده باشد (یعنی <head> آن را نداشته)، لینک استایل را مستقیم
     * در فوتر چاپ می‌کند. دیرتر از حالت ایده‌آل (یک فلش کوتاه محتوای
     * بی‌استایل قبل از رسیدن CSS) ولی بی‌نهایت بهتر از صفحه‌ی کاملاً بدون
     * استایل تا آخر.
     */
    public function print_late_style_fallback(): void
    {
        if (!wp_style_is('zdp-panel', 'enqueued') || wp_style_is('zdp-panel', 'done')) {
            return;
        }

        $registered = wp_styles()->registered['zdp-panel'] ?? null;
        $src        = $registered->src ?? (ZDP_PLUGIN_URL . 'assets/css/panel.css');

        printf(
            '<link rel="stylesheet" id="zdp-panel-css-late-fallback" href="%s">' . "\n",
            esc_url(add_query_arg('ver', ZDP_VERSION, $src))
        );
    }
}
