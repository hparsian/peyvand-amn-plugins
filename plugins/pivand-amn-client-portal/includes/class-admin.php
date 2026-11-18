<?php

declare(strict_types=1);

namespace ZoojDarman\ClientPortal;

if (!defined('ABSPATH')) {
    exit;
}

final class Admin
{
    private Booking $booking;
    private Messages $messages;
    private Survey $survey;
    private Quiz_Funnel $quiz_funnel;
    private Intake $intake;

    public function __construct(Booking $booking, Messages $messages, Survey $survey, Quiz_Funnel $quiz_funnel, Intake $intake)
    {
        $this->booking     = $booking;
        $this->messages    = $messages;
        $this->survey      = $survey;
        $this->quiz_funnel = $quiz_funnel;
        $this->intake      = $intake;

        add_action('admin_menu', [$this, 'register_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        add_action('admin_post_zdp_save_settings', [$this, 'handle_save_settings']);
        add_action('admin_post_zdp_update_booking_status', [$this, 'handle_update_booking_status']);
        add_action('admin_post_zdp_create_manual_booking', [$this, 'handle_create_manual_booking']);
        add_action('admin_post_zdp_update_booking_details', [$this, 'handle_update_booking_details']);
        add_action('admin_post_zdp_delete_booking', [$this, 'handle_delete_booking']);
        add_action('admin_post_zdp_set_survey_approved', [$this, 'handle_set_survey_approved']);
        add_action('admin_post_zdp_delete_survey', [$this, 'handle_delete_survey']);
        add_action('admin_post_zdp_add_service', [$this, 'handle_add_service']);
        add_action('admin_post_zdp_update_service', [$this, 'handle_update_service']);
        add_action('admin_post_zdp_delete_service', [$this, 'handle_delete_service']);
        add_action('admin_post_zdp_add_card', [$this, 'handle_add_card']);
        add_action('admin_post_zdp_delete_card', [$this, 'handle_delete_card']);
        add_action('admin_post_zdp_save_survey_questions', [$this, 'handle_save_survey_questions']);
        add_action('admin_post_zdp_clear_security_log', [$this, 'handle_clear_security_log']);
        add_action('admin_post_zdp_save_business_hours', [$this, 'handle_save_business_hours']);
        add_action('admin_post_zdp_drop_legacy_tables', [$this, 'handle_drop_legacy_tables']);
    }

    public function register_menu(): void
    {
        add_menu_page(
            'پیوند امن — مدیریت زوج‌درمانی',
            'پیوند امن — زوج‌درمانی',
            Security::CAPABILITY,
            'zdp-admin',
            [$this, 'render_dashboard'],
            'dashicons-groups',
            30
        );
    }

    public function enqueue_admin_assets(string $hook_suffix): void
    {
        if (strpos($hook_suffix, 'zdp-admin') === false) {
            return;
        }
        wp_enqueue_style(
            'zdp-admin',
            ZDP_PLUGIN_URL . 'assets/css/admin.css',
            class_exists('Pas_Core_Tokens') ? [\Pas_Core_Tokens::handle()] : [],
            ZDP_VERSION
        );
    }

    public function render_dashboard(): void
    {
        if (!current_user_can(Security::CAPABILITY) && !current_user_can('manage_options')) {
            wp_die(esc_html('شما مجاز به دسترسی به این بخش نیستید.'), 403);
        }

        $tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'bookings';
        $notice = isset($_GET['zdp_notice']) ? sanitize_key($_GET['zdp_notice']) : '';

        // دلیل دقیق خطای ثبت/ویرایش نوبت در یک transient کوتاه‌عمر ذخیره می‌شود
        // (به‌ازای هر کاربر) تا بعد از ریدایرکت هم قابل نمایش باشد؛ بدون آن
        // فقط یک پیام کلی باقی می‌ماند.
        $flash = '';
        if (in_array($notice, ['manual_booking_failed', 'booking_update_invalid'], true)) {
            $flash = $this->take_flash_notice();
        }
        ?>
        <div class="wrap zdp-admin">
            <h1>پیوند امن — پنل مدیریت زوج‌درمان</h1>
            <?php if ($notice === 'reply_sent') : ?>
                <div class="notice notice-success is-dismissible"><p>پاسخ شما ارسال شد.</p></div>
            <?php elseif ($notice === 'saved') : ?>
                <div class="notice notice-success is-dismissible"><p>تنظیمات ذخیره شد.</p></div>
            <?php elseif ($notice === 'manual_booking_created') : ?>
                <div class="notice notice-success is-dismissible"><p>نوبت با موفقیت ثبت شد.</p></div>
            <?php elseif ($notice === 'manual_booking_failed') : ?>
                <div class="notice notice-error is-dismissible"><p><?php echo esc_html($flash !== '' ? $flash : 'ثبت نوبت ناموفق بود — لطفاً فیلدها (به‌خصوص شماره موبایل و تاریخ) را بررسی کنید.'); ?></p></div>
            <?php elseif ($notice === 'booking_updated') : ?>
                <div class="notice notice-success is-dismissible"><p>نوبت به‌روزرسانی شد.</p></div>
            <?php elseif ($notice === 'booking_update_invalid') : ?>
                <div class="notice notice-error is-dismissible"><p><?php echo esc_html($flash !== '' ? $flash : 'تاریخ یا ساعت نامعتبر بود؛ تغییری در تاریخ و ساعت اعمال نشد.'); ?></p></div>
            <?php elseif ($notice === 'booking_deleted') : ?>
                <div class="notice notice-success is-dismissible"><p>نوبت حذف شد.</p></div>
            <?php elseif ($notice === 'booking_delete_failed') : ?>
                <div class="notice notice-error is-dismissible"><p>حذف نوبت ناموفق بود.</p></div>
            <?php endif; ?>

            <?php
            // هشدار وضعیت اتصال به افزونه‌ی آزمون (psy-quiz-enterprise): تب
            // «آزمون‌ها»ی داخل پنل مراجع از طریق فیلتر
            // psy_get_pending_quizzes_for_phone پر می‌شود (نگاه کن به
            // Quiz_Bridge). اگر آن افزونه غیرفعال باشد یا این فیلتر را ثبت
            // نکرده باشد، پنل بی‌صدا لیست خالی نشان می‌دهد و از بیرون
            // «آزمون به پنل وصل نیست» به نظر می‌رسد — بدون این هشدار هیچ
            // نشانه‌ای در پیشخوان برای تشخیص این تفاوت (بین «آزمونی تعریف
            // نشده» و «افزونه اصلاً وصل نیست») وجود نداشت.
            if (!has_filter('psy_get_pending_quizzes_for_phone')) :
                ?>
                <div class="notice notice-warning">
                    <p>
                        ⚠️ افزونه‌ی «آزمون» (psy-quiz-enterprise) به این پنل وصل نیست — فیلتر
                        <code>psy_get_pending_quizzes_for_phone</code> ثبت نشده، پس تب «آزمون‌ها» در
                        پنل مراجعان همیشه خالی نمایش داده می‌شود. مطمئن شوید افزونه‌ی آزمون
                        فعال است و نسخه‌اش این فیلتر را ثبت می‌کند.
                    </p>
                </div>
            <?php endif; ?>

            <h2 class="nav-tab-wrapper">
                <?php foreach (['bookings' => 'نوبت‌ها', 'messages' => 'پیام‌ها', 'surveys' => 'نظرسنجی', 'intake' => 'فرم‌های ارزیابی مراجع', 'leads' => 'لیدها', 'services' => 'خدمات و پرداخت', 'security_log' => 'لاگ امنیتی', 'legacy_cleanup' => 'پاک‌سازی جداول قدیمی', 'settings' => 'تنظیمات'] as $key => $label) : ?>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=zdp-admin&tab=' . $key)); ?>" class="nav-tab <?php echo $tab === $key ? 'nav-tab-active' : ''; ?>"><?php echo esc_html($label); ?></a>
                <?php endforeach; ?>
            </h2>

            <?php if ($notice === 'log_cleared') : ?>
                <div class="notice notice-success is-dismissible"><p>لاگ امنیتی پاک شد.</p></div>
            <?php elseif ($notice === 'legacy_dropped') : ?>
                <div class="notice notice-success is-dismissible"><p>جداول قدیمی که parity کامل داشتند حذف شدند.</p></div>
            <?php elseif ($notice === 'legacy_not_ready') : ?>
                <div class="notice notice-error is-dismissible"><p>هنوز migration نهایی نشده (پرچم‌های zdp_legacy_migrated / zdp_surveys_reconciled). چیزی حذف نشد.</p></div>
            <?php elseif ($notice === 'service_invalid') : ?>
                <div class="notice notice-error is-dismissible"><p>خدمت اضافه نشد — نام یا قیمت خالی بود (اگر قیمت را با اعداد فارسی وارد کرده‌اید، لطفاً دوباره امتحان کنید).</p></div>
            <?php elseif ($notice === 'card_invalid') : ?>
                <div class="notice notice-error is-dismissible"><p>کارت اضافه نشد — یکی از فیلدها (بانک، شماره کارت، به‌نام) خالی بود.</p></div>
            <?php endif; ?>

            <?php
            match ($tab) {
                'messages'       => $this->render_messages_tab(),
                'surveys'        => $this->render_surveys_tab(),
                'intake'         => $this->intake->render_admin_tab(),
                'leads'          => $this->render_leads_tab(),
                'services'       => $this->render_services_tab(),
                'security_log'   => $this->render_security_log_tab(),
                'legacy_cleanup' => $this->render_legacy_cleanup_tab(),
                'settings'       => $this->render_settings_tab(),
                default          => $this->render_bookings_tab(),
            };
            ?>
        </div>
        <?php
    }

    /**
     * نمایش zdp_audit_log که تابع audit_log() در functions-helpers.php ثبت
     * می‌کند (تلاش‌های OTP، لغو/تایید پرداخت، ورود/خروج، کنسلی نوبت و...).
     * قبل از این نسخه، این داده فقط نوشته می‌شد و هیچ صفحه‌ای برای دیدنش
     * نبود — این تب اولین مصرف‌کننده‌ی واقعی get_option('zdp_audit_log') است.
     */
    private function render_security_log_tab(): void
    {
        $entries = array_reverse(get_option('zdp_audit_log', []));
        $filter  = isset($_GET['action_filter']) ? sanitize_text_field(wp_unslash($_GET['action_filter'])) : '';

        if ($filter !== '') {
            $entries = array_values(array_filter(
                $entries,
                static fn($e) => ($e['action'] ?? '') === $filter
            ));
        }

        $actions = array_values(array_unique(array_map(
            static fn($e) => $e['action'] ?? '',
            get_option('zdp_audit_log', [])
        )));
        sort($actions);
        ?>
        <p class="description">
            آخرین ۵۰۰ رویداد امنیتی ثبت‌شده در سیستم (تلاش‌های OTP، پرداخت، ورود/خروج، کنسلی نوبت و غیره).
            این لاگ در گزینه‌ی <code>zdp_audit_log</code> نگه داشته می‌شود و خودکار به ۵۰۰ مورد آخر محدود می‌شود.
        </p>

        <form method="get" style="margin-bottom:12px;">
            <input type="hidden" name="page" value="zdp-admin">
            <input type="hidden" name="tab" value="security_log">
            <select name="action_filter" onchange="this.form.submit()">
                <option value="">همه‌ی عملیات‌ها</option>
                <?php foreach ($actions as $a) : ?>
                    <option value="<?php echo esc_attr($a); ?>" <?php selected($filter, $a); ?>><?php echo esc_html($a); ?></option>
                <?php endforeach; ?>
            </select>
        </form>

        <table class="widefat striped">
            <thead><tr><th>زمان (UTC)</th><th>عملیات</th><th>شناسه کاربر</th><th>IP</th><th>جزئیات</th></tr></thead>
            <tbody>
            <?php if (empty($entries)) : ?>
                <tr><td colspan="5">هنوز رویدادی ثبت نشده.</td></tr>
            <?php endif; ?>
            <?php foreach ($entries as $e) : ?>
                <tr>
                    <td dir="ltr"><?php echo esc_html($e['time'] ?? ''); ?></td>
                    <td><?php echo esc_html($e['action'] ?? ''); ?></td>
                    <td><?php echo esc_html((string) ($e['user_id'] ?? 0)); ?></td>
                    <td dir="ltr"><?php echo esc_html($e['ip'] ?? ''); ?></td>
                    <td><code style="font-size:11px;"><?php echo esc_html(wp_json_encode($e['context'] ?? [], JSON_UNESCAPED_UNICODE)); ?></code></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin-top:16px;"
              onsubmit="return confirm('کل لاگ امنیتی پاک شود؟ این کار قابل بازگشت نیست.');">
            <?php wp_nonce_field('zdp_clear_security_log'); ?>
            <input type="hidden" name="action" value="zdp_clear_security_log">
            <button type="submit" class="button button-secondary">پاک کردن کل لاگ</button>
        </form>
        <?php
    }

    /** همه‌ی وضعیت‌های مجاز نوبت که در دراپ‌داون مدیریت نمایش داده می‌شوند — هم مسیر پرداخت آنلاین، هم نوبت‌های دستی. */
    private const ALL_BOOKING_STATUSES = ['در انتظار پرداخت', 'در انتظار تایید واریز', 'در انتظار بررسی', 'تایید شده', 'فعال', 'انجام شده', 'عدم حضور', 'ناموفق', 'لغو شده'];

    /**
     * رفع خلأ بصری: تا این نسخه، وضعیت‌های نوبت/پیام/نظرسنجی در پیشخوان
     * فقط متن ساده بودند (بدون رنگ). CSS رنگی معادلش (panel.css →
     * .zdp-status-*) سال‌ها پیش برای پنل مراجع نوشته شده بود، ولی همان
     * زمان به‌خاطر sanitize_html_class() که کاراکترهای فارسی را حذف
     * می‌کرد، عملاً هیچ‌وقت فعال نبود (رفع شده در همین نسخه —
     * zdp_status_slug() در functions-helpers.php). این متد از همان تابع
     * درست‌شده استفاده می‌کند تا مستقیم به `.zdp-badge-*` در admin.css
     * وصل شود.
     */
    private static function status_badge(string $status): string
    {
        return sprintf('<span class="zdp-badge zdp-badge-%s">%s</span>', esc_attr(zdp_status_slug($status)), esc_html($status));
    }

    private function render_bookings_tab(): void
    {
        $search   = isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '';
        $bookings = $this->booking->get_all_bookings_with_client_names(200, $search);
        ?>
        <details class="zdp-manual-booking" open>
            <summary style="cursor:pointer; font-weight:600; font-size:15px; margin-bottom:8px;">➕ ثبت نوبت جدید (خارج از سایت: تلفنی، واتساپ، اینستاگرام، حضوری و...)</summary>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin:12px 0 20px; max-width:640px;">
                <input type="hidden" name="action" value="zdp_create_manual_booking">

                <?php wp_nonce_field('zdp_create_manual_booking', 'zdp_manual_booking_nonce'); ?>
                <table class="form-table">
                    <tr>
                        <th><label for="zdp_mb_name">نام و نام‌خانوادگی مراجع</label></th>
                        <td><input type="text" id="zdp_mb_name" name="full_name" class="regular-text" required></td>
                    </tr>
                    <tr>
                        <th><label for="zdp_mb_mobile">شماره موبایل</label></th>
                        <td><input type="text" id="zdp_mb_mobile" name="mobile" class="regular-text" dir="ltr" placeholder="09xxxxxxxxx یا +xxxxxxxxxxx" required></td>
                    </tr>
                    <tr>
                        <th><label for="zdp_mb_date">تاریخ جلسه (شمسی)</label></th>
                        <td><input type="text" id="zdp_mb_date" name="booking_date_jalali" class="regular-text" dir="ltr" placeholder="1405/05/01" required></td>
                    </tr>
                    <tr>
                        <th><label for="zdp_mb_time">ساعت جلسه</label></th>
                        <td><input type="time" id="zdp_mb_time" name="booking_time" required></td>
                    </tr>
                    <tr>
                        <th><label for="zdp_mb_session">نوع جلسه</label></th>
                        <td>
                            <select id="zdp_mb_session" name="session_type">
                                <?php foreach (Booking::SESSION_TYPES as $key => $label) : ?>
                                    <option value="<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="zdp_mb_source">نحوه رزرو</label></th>
                        <td>
                            <select id="zdp_mb_source" name="booking_source">
                                <?php foreach (Booking::MANUAL_SOURCES as $key => $label) : ?>
                                    <option value="<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="zdp_mb_note">یادداشت درمانگر</label></th>
                        <td><textarea id="zdp_mb_note" name="therapist_note" class="large-text" rows="2"></textarea></td>
                    </tr>
                </table>
                <?php submit_button('ثبت نوبت'); ?>
            </form>
        </details>

        <form method="get" style="margin:16px 0;">
            <input type="hidden" name="page" value="zdp-admin">
            <input type="hidden" name="tab" value="bookings">
            <input type="text" name="s" value="<?php echo esc_attr($search); ?>" placeholder="جست‌وجو بر اساس نام، شماره موبایل یا کد پیگیری" class="regular-text">
            <button class="button">جست‌وجو</button>
            <?php if ($search !== '') : ?>
                <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=zdp-admin&tab=bookings')); ?>">پاک کردن</a>
            <?php endif; ?>
        </form>

        <table class="widefat striped">
            <thead><tr><th>مراجع</th><th>موبایل</th><th>خدمت</th><th>تاریخ</th><th>ساعت</th><th>نوع جلسه</th><th>نحوه رزرو</th><th>کد پیگیری</th><th>یادآوری</th><th>رسید</th><th>وضعیت</th><th>عملیات</th></tr></thead>
            <tbody>
            <?php if (empty($bookings)) : ?>
                <tr><td colspan="12">نوبتی یافت نشد.</td></tr>
            <?php endif; ?>
            <?php foreach ($bookings as $b) : ?>
                <tr>
                    <td><?php echo esc_html($b->full_name ?? '-'); ?></td>
                    <td dir="ltr"><?php echo esc_html($b->mobile ?? '-'); ?></td>
                    <td><?php echo esc_html($b->service_name); ?></td>
                    <td><?php echo esc_html(Jalali::gregorian_to_jalali_string($b->booking_date)); ?></td>
                    <td><?php echo esc_html(substr((string) $b->booking_time, 0, 5)); ?></td>
                    <td><?php echo esc_html(Booking::SESSION_TYPES[$b->session_type] ?? '-'); ?></td>
                    <td><?php echo esc_html(Booking::ALL_SOURCES[$b->booking_source] ?? '-'); ?></td>
                    <td dir="ltr"><?php echo !empty($b->ref_id) ? esc_html($b->ref_id) : '—'; ?></td>
                    <td><?php echo $b->reminder_sent_at ? '✅ ارسال شد' : '—'; ?></td>
                    <td>
                        <?php if (!empty($b->receipt_path)) : ?>
                            <a href="<?php echo esc_url(Booking::receipt_url($b)); ?>" target="_blank" rel="noopener noreferrer">
                                <img src="<?php echo esc_url(Booking::receipt_url($b)); ?>" alt="رسید واریز" style="max-width:48px; max-height:48px; border-radius:4px; border:1px solid #dcdcde; display:block;">
                            </a>
                        <?php else : ?>
                            —
                        <?php endif; ?>
                    </td>
                    <td><?php echo self::status_badge($b->status); ?></td>
                    <td>
                        <?php if ($b->status === 'در انتظار تایید واریز' && !empty($b->receipt_path)) : ?>
                            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin-bottom:6px;">
                                <input type="hidden" name="action" value="zdp_update_booking_status">
                                <input type="hidden" name="booking_id" value="<?php echo (int) $b->id; ?>">
                                <input type="hidden" name="status" value="تایید شده">
                                <?php wp_nonce_field('zdp_update_booking_' . $b->id, 'zdp_booking_nonce'); ?>
                                <button class="button button-primary">✅ تایید واریز</button>
                            </form>
                        <?php endif; ?>
                        <details>
                            <summary style="cursor:pointer;">ویرایش</summary>
                            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin-top:8px; min-width:220px;">
                                <input type="hidden" name="action" value="zdp_update_booking_details">
                                <input type="hidden" name="booking_id" value="<?php echo (int) $b->id; ?>">
                                <?php wp_nonce_field('zdp_edit_booking_' . $b->id, 'zdp_edit_nonce'); ?>

                                <label>تاریخ (شمسی)</label>
                                <input type="text" name="booking_date_jalali" dir="ltr" style="width:100%;" value="<?php echo esc_attr(Jalali::gregorian_to_jalali_ymd($b->booking_date)); ?>"><br>

                                <label>ساعت</label>
                                <input type="time" name="booking_time" style="width:100%;" value="<?php echo esc_attr(substr((string) $b->booking_time, 0, 5)); ?>"><br>

                                <label>نوع جلسه</label>
                                <select name="session_type" style="width:100%;">
                                    <option value="">—</option>
                                    <?php foreach (Booking::SESSION_TYPES as $key => $label) : ?>
                                        <option value="<?php echo esc_attr($key); ?>" <?php selected($b->session_type, $key); ?>><?php echo esc_html($label); ?></option>
                                    <?php endforeach; ?>
                                </select><br>

                                <label>نحوه رزرو</label>
                                <select name="booking_source" style="width:100%;">
                                    <?php foreach (Booking::ALL_SOURCES as $key => $label) : ?>
                                        <option value="<?php echo esc_attr($key); ?>" <?php selected($b->booking_source, $key); ?>><?php echo esc_html($label); ?></option>
                                    <?php endforeach; ?>
                                </select><br>

                                <label>یادداشت درمانگر</label>
                                <textarea name="therapist_note" style="width:100%;" rows="2"><?php echo esc_textarea($b->therapist_note ?? ''); ?></textarea><br>

                                <label>وضعیت</label>
                                <select name="status" style="width:100%;">
                                    <?php foreach (self::ALL_BOOKING_STATUSES as $s) : ?>
                                        <option value="<?php echo esc_attr($s); ?>" <?php selected($b->status, $s); ?>><?php echo esc_html($s); ?></option>
                                    <?php endforeach; ?>
                                </select><br>

                                <button class="button button-primary" style="margin-top:6px;">ذخیره</button>
                            </form>

                            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"
                                  style="margin-top:8px;"
                                  onsubmit="return confirm('این نوبت کاملاً حذف شود؟ این کار قابل بازگشت نیست — اگر فقط می‌خواهید لغوش کنید، بهتر است از فیلد «وضعیت» بالا استفاده کنید.');">
                                <input type="hidden" name="action" value="zdp_delete_booking">
                                <input type="hidden" name="booking_id" value="<?php echo (int) $b->id; ?>">
                                <?php wp_nonce_field('zdp_delete_booking_' . $b->id, 'zdp_delete_nonce'); ?>
                                <button class="button button-link-delete" style="color:#b32d2e;">حذف کامل نوبت</button>
                            </form>
                        </details>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }

    public function handle_update_booking_status(): void
    {
        if (!current_user_can(Security::CAPABILITY) && !current_user_can('manage_options')) {
            wp_die('دسترسی غیرمجاز', 403);
        }
        $booking_id = (int) ($_POST['booking_id'] ?? 0);
        check_admin_referer('zdp_update_booking_' . $booking_id, 'zdp_booking_nonce');

        // باگ رفع‌شده: برخلاف handle_update_booking_details (که $status را در
        // برابر self::ALL_BOOKING_STATUSES می‌سنجد)، این هندلر — که پشتِ دکمه‌ی
        // یک‌کلیکیِ «✅ تایید واریز» است — تا این نسخه هر مقداری را برای
        // status می‌پذیرفت و مستقیم در دیتابیس می‌نوشت. چون این مسیر فقط با
        // nonce + قابلیتِ مدیریت محافظت می‌شود (نه ورودیِ عمومی)، این یک حفره‌ی
        // امنیتیِ قابل بهره‌برداری از بیرون نبود، ولی برای هم‌خوانی و جلوگیری
        // از ثبت یک وضعیتِ نامعتبر/تایپی که رنگ/برچسبِ status_badge و منطقِ
        // «تولید کد پیگیری موقع تایید» را می‌شکند، همان اعتبارسنجی اینجا هم
        // اضافه شد.
        $status = sanitize_text_field(wp_unslash($_POST['status'] ?? ''));
        if ($booking_id && $status !== '' && in_array($status, self::ALL_BOOKING_STATUSES, true)) {
            $this->booking->update_status($booking_id, $status);
        }

        wp_safe_redirect(admin_url('admin.php?page=zdp-admin&tab=bookings'));
        exit;
    }

    /** پیام خطای دقیق را برای نمایش بعد از ریدایرکت نگه می‌دارد (۶۰ ثانیه، به‌ازای هر کاربر). */
    private function set_flash_notice(string $message): void
    {
        set_transient('zdp_admin_flash_' . get_current_user_id(), $message, 60);
    }

    private function take_flash_notice(): string
    {
        $key     = 'zdp_admin_flash_' . get_current_user_id();
        $message = (string) get_transient($key);
        if ($message !== '') {
            delete_transient($key);
        }

        return $message;
    }

    /** ثبت نوبتی که درمانگر برای رزروی خارج از سایت (تلفنی/واتساپ/اینستاگرام/حضوری) وارد کرده. */
    public function handle_create_manual_booking(): void
    {
        $this->assert_can_manage();
        check_admin_referer('zdp_create_manual_booking', 'zdp_manual_booking_nonce');

        // ارقام فارسی هم پذیرفته شود؛ بدون این، ورودی «۱۴۰۵/۰۵/۰۱» بی‌صدا
        // نامعتبر شمرده می‌شد و درمانگر فقط پیام کلی خطا می‌دید.
        $jalali_date = normalize_digits(sanitize_text_field(wp_unslash($_POST['booking_date_jalali'] ?? '')));
        $gregorian   = Jalali::jalali_string_to_gregorian_string($jalali_date);

        if ($gregorian === null) {
            $this->set_flash_notice(sprintf('تاریخ «%s» معتبر نیست (نمونه: 1405/05/01)؛ نوبتی ثبت نشد.', $jalali_date !== '' ? $jalali_date : '—'));
            wp_safe_redirect(admin_url('admin.php?page=zdp-admin&tab=bookings&zdp_notice=manual_booking_failed'));
            exit;
        }

        $time_in = sanitize_text_field(wp_unslash($_POST['booking_time'] ?? ''));
        if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $time_in)) {
            $this->set_flash_notice(sprintf('ساعت «%s» معتبر نیست (نمونه: 09:00)؛ نوبتی ثبت نشد.', $time_in !== '' ? $time_in : '—'));
            wp_safe_redirect(admin_url('admin.php?page=zdp-admin&tab=bookings&zdp_notice=manual_booking_failed'));
            exit;
        }

        $result = $this->booking->create_manual_booking([
            'full_name'      => wp_unslash($_POST['full_name'] ?? ''),
            'mobile'         => wp_unslash($_POST['mobile'] ?? ''),
            'booking_date'   => $gregorian,
            'booking_time'   => $time_in,
            'session_type'   => wp_unslash($_POST['session_type'] ?? ''),
            'booking_source' => wp_unslash($_POST['booking_source'] ?? ''),
            'therapist_note' => wp_unslash($_POST['therapist_note'] ?? ''),
        ]);

        $notice = $result['success'] ? 'manual_booking_created' : 'manual_booking_failed';
        if (!$result['success']) {
            // دلیل دقیق اعتبارسنجی (تاریخ/ساعت/موبایل/نوع جلسه) به‌جای پیام کلی.
            $this->set_flash_notice((string) ($result['data']['message'] ?? 'ثبت نوبت ناموفق بود.'));
        }
        wp_safe_redirect(admin_url('admin.php?page=zdp-admin&tab=bookings&zdp_notice=' . $notice));
        exit;
    }

    /** ویرایش نوبت موجود توسط درمانگر: تاریخ/ساعت/نوع جلسه/نحوه رزرو/یادداشت/وضعیت. */
    public function handle_update_booking_details(): void
    {
        $this->assert_can_manage();
        $booking_id = (int) ($_POST['booking_id'] ?? 0);
        check_admin_referer('zdp_edit_booking_' . $booking_id, 'zdp_edit_nonce');

        if ($booking_id) {
            // تغییر وضعیت جداگانه از طریق همان متد قبلی صدا زده می‌شود تا منطق
            // تولید کد پیگیری دستی + پیامک تاییدیه (وقتی وضعیت به «تایید شده»
            // تغییر می‌کند) دوباره‌نویسی نشود.
            $status = sanitize_text_field(wp_unslash($_POST['status'] ?? ''));
            if ($status !== '' && in_array($status, self::ALL_BOOKING_STATUSES, true)) {
                $this->booking->update_status($booking_id, $status);
            }

            $jalali_date = normalize_digits(sanitize_text_field(wp_unslash($_POST['booking_date_jalali'] ?? '')));
            $gregorian   = $jalali_date !== '' ? Jalali::jalali_string_to_gregorian_string($jalali_date) : null;
            $time_in     = sanitize_text_field(wp_unslash($_POST['booking_time'] ?? ''));

            // خطاها جمع می‌شوند تا ادمین بداند چه چیزی رد شد؛ اگر یکی از
            // تاریخ/ساعت نامعتبر باشد هیچ‌کدام اعمال نمی‌شود (نه اعمالِ نصفه)
            // ولی بقیهٔ فیلدها ذخیره می‌شوند.
            $invalid = [];
            if ($jalali_date !== '' && $gregorian === null) {
                $invalid[] = sprintf('تاریخ «%s» معتبر نیست', $jalali_date);
            }
            if ($time_in !== '' && !preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $time_in)) {
                $invalid[] = sprintf('ساعت «%s» معتبر نیست (نمونه: 09:00)', $time_in);
            }

            $fields = [
                'session_type'   => sanitize_text_field(wp_unslash($_POST['session_type'] ?? '')),
                'booking_source' => sanitize_text_field(wp_unslash($_POST['booking_source'] ?? '')),
                'therapist_note' => wp_unslash($_POST['therapist_note'] ?? ''),
            ];
            if ($invalid === []) {
                $fields['booking_time'] = $time_in;
                if ($gregorian !== null) {
                    $fields['booking_date'] = $gregorian;
                }
            }

            $this->booking->update_booking_fields($booking_id, $fields);

            if ($invalid !== []) {
                $this->set_flash_notice(implode('؛ ', $invalid) . '. تاریخ و ساعت نوبت تغییر نکرد؛ بقیهٔ فیلدها ذخیره شد.');
                wp_safe_redirect(admin_url('admin.php?page=zdp-admin&tab=bookings&zdp_notice=booking_update_invalid'));
                exit;
            }
        }

        wp_safe_redirect(admin_url('admin.php?page=zdp-admin&tab=bookings&zdp_notice=booking_updated'));
        exit;
    }

    /** حذف کامل یک نوبت — نگاه کن به توضیح Booking::delete_booking برای تفاوتش با تغییر وضعیت به «لغو شده». */
    public function handle_delete_booking(): void
    {
        $this->assert_can_manage();
        $booking_id = (int) ($_POST['booking_id'] ?? 0);
        check_admin_referer('zdp_delete_booking_' . $booking_id, 'zdp_delete_nonce');

        $notice = 'booking_updated';
        if ($booking_id) {
            $notice = $this->booking->delete_booking($booking_id) ? 'booking_deleted' : 'booking_delete_failed';
        }

        wp_safe_redirect(admin_url('admin.php?page=zdp-admin&tab=bookings&zdp_notice=' . $notice));
        exit;
    }

    private function render_messages_tab(): void
    {
        $msgs = $this->messages->get_all_messages();
        ?>
        <?php foreach ($msgs as $m) : ?>
            <div class="card zdp-message-card">
                <div class="zdp-message-card-head">
                    <strong><?php echo esc_html($m->mobile ?? '-'); ?></strong>
                    <?php echo self::status_badge((string) $m->status); ?>
                </div>
                <p><?php echo esc_html($m->message_text); ?></p>
                <?php if (!empty($m->admin_reply)) : ?>
                    <p class="zdp-message-card-reply">پاسخ فعلی: <?php echo esc_html($m->admin_reply); ?></p>
                <?php endif; ?>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <input type="hidden" name="action" value="zdp_admin_reply">
                    <input type="hidden" name="msg_id" value="<?php echo (int) $m->id; ?>">
                    <?php wp_nonce_field('zdp_reply_action_' . $m->id, 'zdp_reply_nonce'); ?>
                    <textarea name="admin_reply_text" rows="2" style="width:100%;"></textarea>
                    <button class="button button-primary">ارسال پاسخ</button>
                </form>
            </div>
        <?php endforeach; ?>
        <?php
    }

    private function render_surveys_tab(): void
    {
        $surveys = $this->survey->get_pending_surveys();
        ?>
        <table class="widefat striped">
            <thead><tr><th>سوال ۱</th><th>سوال ۲</th><th>سوال ۳</th><th>سوالات اضافی</th><th>امتیاز</th><th>پیشنهاد</th><th>تاییدشده؟</th><th>عملیات</th></tr></thead>
            <tbody>
            <?php foreach ($surveys as $s) : ?>
                <?php
                $extra = [];
                if (!empty($s->extra_answers)) {
                    $decoded = json_decode($s->extra_answers, true);
                    if (is_array($decoded)) {
                        $extra = $decoded;
                    }
                }
                ?>
                <tr>
                    <td><?php echo esc_html($s->q1); ?></td>
                    <td><?php echo esc_html($s->q2); ?></td>
                    <td><?php echo esc_html($s->q3); ?></td>
                    <td>
                        <?php if (empty($extra)) : ?>
                            —
                        <?php else : ?>
                            <?php foreach ($extra as $item) : ?>
                                <div><strong><?php echo esc_html($item['label'] ?? ''); ?>:</strong> <?php echo esc_html($item['answer'] ?? ''); ?></div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </td>
                    <td><?php echo !empty($s->rating) ? str_repeat('★', (int) $s->rating) . str_repeat('☆', 5 - (int) $s->rating) : '—'; ?></td>
                    <td><?php echo esc_html($s->suggestions); ?></td>
                    <td><?php echo $s->approved ? self::status_badge('تایید شده') : self::status_badge('در انتظار بررسی'); ?></td>
                    <td>
                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                            <input type="hidden" name="action" value="zdp_set_survey_approved">
                            <input type="hidden" name="survey_id" value="<?php echo (int) $s->id; ?>">
                            <input type="hidden" name="approved" value="<?php echo $s->approved ? 0 : 1; ?>">
                            <?php wp_nonce_field('zdp_survey_approve_' . $s->id, 'zdp_survey_nonce'); ?>
                            <button class="button"><?php echo $s->approved ? 'لغو تایید' : 'تایید نمایش عمومی'; ?></button>
                        </form>
                        <?php
                        // دکمه‌ی کپی برای اینستاگرام فقط برای همان نظراتی نشان داده
                        // می‌شود که واقعاً در شورت‌کد عمومی [zdp_testimonials]
                        // هم نمایش داده می‌شوند (approved=1 + consent=1 + متن غیرخالی)؛
                        // یعنی دقیقاً چیزی که قرار است رونمایی/منتشر شود.
                        $is_public = $s->approved && !empty($s->consent) && trim((string) $s->suggestions) !== '';
                        if ($is_public) :
                            $ig_caption = Survey::build_instagram_caption((string) $s->suggestions);
                        ?>
                            <button type="button" class="button zdp-ig-copy-btn" data-ig-text="<?php echo esc_attr($ig_caption); ?>">کپی برای اینستاگرام</button>
                        <?php endif; ?>
                        <a
                            class="button button-small"
                            style="color:#b91c1c;"
                            href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=zdp_delete_survey&survey_id=' . (int) $s->id), 'zdp_delete_survey_' . $s->id)); ?>"
                            onclick="return confirm('این نظرسنجی برای همیشه حذف می‌شود و قابل بازگشت نیست. ادامه می‌دهید؟');"
                        >حذف</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <script>
        (function () {
            function fallbackCopy(text, done) {
                var ta = document.createElement('textarea');
                ta.value = text;
                ta.style.position = 'fixed';
                ta.style.opacity = '0';
                document.body.appendChild(ta);
                ta.focus();
                ta.select();
                try { document.execCommand('copy'); } catch (err) {}
                document.body.removeChild(ta);
                done();
            }
            document.addEventListener('click', function (e) {
                var btn = e.target.closest('.zdp-ig-copy-btn');
                if (!btn) { return; }
                var text = btn.getAttribute('data-ig-text') || '';
                var done = function () {
                    var original = btn.textContent;
                    btn.textContent = 'کپی شد!';
                    btn.disabled = true;
                    setTimeout(function () {
                        btn.textContent = original;
                        btn.disabled = false;
                    }, 1500);
                };
                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(text).then(done).catch(function () {
                        fallbackCopy(text, done);
                    });
                } else {
                    fallbackCopy(text, done);
                }
            });
        })();
        </script>
        <?php
    }

    public function handle_set_survey_approved(): void
    {
        if (!current_user_can(Security::CAPABILITY) && !current_user_can('manage_options')) {
            wp_die('دسترسی غیرمجاز', 403);
        }
        $survey_id = (int) ($_POST['survey_id'] ?? 0);
        check_admin_referer('zdp_survey_approve_' . $survey_id, 'zdp_survey_nonce');

        if ($survey_id) {
            $this->survey->set_approved($survey_id, !empty($_POST['approved']));
        }

        wp_safe_redirect(admin_url('admin.php?page=zdp-admin&tab=surveys'));
        exit;
    }

    public function handle_delete_survey(): void
    {
        if (!current_user_can(Security::CAPABILITY) && !current_user_can('manage_options')) {
            wp_die('دسترسی غیرمجاز', 403);
        }
        $survey_id = (int) ($_GET['survey_id'] ?? 0);
        check_admin_referer('zdp_delete_survey_' . $survey_id);

        if ($survey_id) {
            $this->survey->delete_survey($survey_id);
            audit_log('survey_deleted', ['id' => $survey_id]);
        }

        wp_safe_redirect(admin_url('admin.php?page=zdp-admin&tab=surveys'));
        exit;
    }

    public function handle_clear_security_log(): void
    {
        if (!current_user_can(Security::CAPABILITY) && !current_user_can('manage_options')) {
            wp_die('دسترسی غیرمجاز', 403);
        }
        check_admin_referer('zdp_clear_security_log');

        // کل لاگ پاک می‌شود و بلافاصله یک ورودی تازه برای خودِ این عملیات
        // ثبت می‌شود، تا مشخص بماند لاگ کِی و توسط چه کاربری پاک شده است.
        update_option('zdp_audit_log', [], false);
        audit_log('security_log_cleared', ['cleared_by' => get_current_user_id()]);

        wp_safe_redirect(admin_url('admin.php?page=zdp-admin&tab=security_log&zdp_notice=log_cleared'));
        exit;
    }

    /**
     * تب پاک‌سازی جداول قدیمی (cpp_bookings, cpp_messages, cpp_surveys,
     * cpp_leads). این جداول از افزونه‌ی قبلی مانده‌اند و از migrate_from_legacy_plugin()
     * / reconcile_legacy_surveys() فقط خوانده می‌شوند، هیچ‌جای کد دیگری
     * روی‌شان نمی‌نویسد. حذف‌شان هیچ‌وقت خودکار انجام نمی‌شود — این تب گزارش
     * parity هر جدول را نشان می‌دهد و فقط با کلیک صریح ادمین، پس از یک
     * confirm جاوااسکریپتی، درخواست DROP ارسال می‌شود.
     */
    private function render_legacy_cleanup_tab(): void
    {
        $migrated   = (bool) get_option('zdp_legacy_migrated');
        $reconciled = (bool) get_option('zdp_surveys_reconciled');
        $ready      = $migrated && $reconciled;
        $parity     = Installer::legacy_table_parity();
        ?>
        <p class="description">
            این ۴ جدول باقی‌مانده‌ی افزونه‌ی قدیمی هستند و فقط برای migration خوانده می‌شوند؛
            چیزی دیگر روی‌شان نمی‌نویسد. حذف‌شان قابل بازگشت نیست — parity هر جدول را قبل از حذف بررسی کنید.
        </p>

        <?php if (!$ready) : ?>
            <div class="notice notice-warning inline"><p>
                migration هنوز نهایی نشده
                (<code>zdp_legacy_migrated</code>: <?php echo $migrated ? 'بله' : 'خیر'; ?>,
                <code>zdp_surveys_reconciled</code>: <?php echo $reconciled ? 'بله' : 'خیر'; ?>).
                تا نهایی نشدن هر دو، دکمه‌ی حذف غیرفعال است.
            </p></div>
        <?php endif; ?>

        <table class="widefat striped">
            <thead><tr><th>جدول قدیمی</th><th>جدول جدید</th><th>وجود دارد؟</th><th>تعداد ردیف قدیمی</th><th>تعداد ردیف جدید</th><th>وضعیت</th></tr></thead>
            <tbody>
            <?php foreach ($parity as $legacy_suffix => $row) : ?>
                <tr>
                    <td><code><?php echo esc_html($row['legacy_table']); ?></code></td>
                    <td><code><?php echo esc_html($row['new_table']); ?></code></td>
                    <td><?php echo $row['exists'] ? 'بله' : 'خیر (قبلاً حذف شده)'; ?></td>
                    <td><?php echo (int) $row['legacy_count']; ?></td>
                    <td><?php echo (int) $row['new_count']; ?></td>
                    <td>
                        <?php if (!$row['exists']) : ?>
                            —
                        <?php elseif ($row['safe_to_drop']) : ?>
                            <span style="color:#0f766e;">✔ parity کامل — امن برای حذف</span>
                        <?php else : ?>
                            <span style="color:#b91c1c;">✘ ناقص — حذف نمی‌شود</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin-top:16px;"
              onsubmit="return confirm('فقط جداولی که parity کامل دارند DROP می‌شوند و این کار قابل بازگشت نیست. مطمئنید؟');">
            <?php wp_nonce_field('zdp_drop_legacy_tables'); ?>
            <input type="hidden" name="action" value="zdp_drop_legacy_tables">
            <label style="display:block;margin-bottom:8px;">
                <input type="checkbox" name="confirm" value="1" required>
                از پشتیبان (backup) کامل دیتابیس مطمئنم و می‌خواهم جداول واجد شرایط را برای همیشه حذف کنم.
            </label>
            <button type="submit" class="button button-secondary" <?php disabled(!$ready); ?>>حذف جداول قدیمیِ واجد شرایط</button>
        </form>
        <?php
    }

    public function handle_drop_legacy_tables(): void
    {
        if (!current_user_can(Security::CAPABILITY) && !current_user_can('manage_options')) {
            wp_die('دسترسی غیرمجاز', 403);
        }
        check_admin_referer('zdp_drop_legacy_tables');

        if (empty($_POST['confirm'])) {
            wp_safe_redirect(admin_url('admin.php?page=zdp-admin&tab=legacy_cleanup'));
            exit;
        }

        $migrated   = (bool) get_option('zdp_legacy_migrated');
        $reconciled = (bool) get_option('zdp_surveys_reconciled');

        if (!$migrated || !$reconciled) {
            wp_safe_redirect(admin_url('admin.php?page=zdp-admin&tab=legacy_cleanup&zdp_notice=legacy_not_ready'));
            exit;
        }

        Installer::drop_legacy_tables();

        wp_safe_redirect(admin_url('admin.php?page=zdp-admin&tab=legacy_cleanup&zdp_notice=legacy_dropped'));
        exit;
    }

    private function render_leads_tab(): void
    {
        global $wpdb;
        $leads = $wpdb->get_results('SELECT * FROM ' . Installer::table('leads') . ' ORDER BY created_at DESC LIMIT 200');
        ?>
        <table class="widefat striped">
            <thead><tr><th>موبایل</th><th>منبع</th><th>موضوع</th><th>امتیاز</th><th>باند</th><th>فالوآپ ۲۴س</th><th>فالوآپ ۷۲س</th><th>رزرو کرده؟</th><th>پرونده بالینی</th></tr></thead>
            <tbody>
            <?php foreach ($leads as $l) :
                /**
                 * مصرف‌کننده‌ی قرارداد فیلتری pas_get_case_summary_for_phone
                 * (تولیدشده در pivand-amn-case-file). تا این نسخه هیچ‌جای
                 * اکوسیستم این فیلتر را صدا نمی‌زد؛ این‌جا اولین مصرف‌کننده‌ی
                 * واقعی آن است — نمایش وضعیت پرونده بالینی (اگر وجود داشته
                 * باشد) کنار همان لیدی که از فرم/کوییز عمومی وارد شده.
                 * فقط خلاصه‌ی غیرحساس (status/topic) است، نه یادداشت بالینی.
                 */
                $case_summary = apply_filters('pas_get_case_summary_for_phone', null, $l->phone);
            ?>
                <tr>
                    <td><?php echo esc_html($l->phone); ?></td>
                    <td><?php echo esc_html($l->source); ?></td>
                    <td><?php echo esc_html($l->quiz_topic); ?></td>
                    <td><?php echo esc_html((string) $l->quiz_score); ?></td>
                    <td><?php echo esc_html($l->quiz_band); ?></td>
                    <td><?php echo $l->followed_up_24h ? '✓' : '—'; ?></td>
                    <td><?php echo $l->followed_up_72h ? '✓' : '—'; ?></td>
                    <td><?php echo $l->booked ? '✓' : '—'; ?></td>
                    <td>
                        <?php if (is_array($case_summary) && !empty($case_summary['has_case_file'])) : ?>
                            <span class="zdp-badge zdp-badge-has-case" title="<?php echo esc_attr($case_summary['topic'] ?? ''); ?>">
                                📁 <?php echo esc_html($case_summary['status'] ?? ''); ?>
                            </span>
                        <?php else : ?>
                            <span class="zdp-badge zdp-badge-no-case">—</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }

    private function render_services_tab(): void
    {
        $services = get_option('zdp_services_list', []);
        $cards    = get_option('zdp_manual_payment_cards', []);
        $method   = get_option('zdp_payment_method', 'zarinpal');

        $edit_id      = isset($_GET['edit_service']) ? sanitize_text_field(wp_unslash($_GET['edit_service'])) : '';
        $editing      = null;
        if ($edit_id !== '') {
            foreach ($services as $s) {
                if (($s['id'] ?? '') === $edit_id) {
                    $editing = $s;
                    break;
                }
            }
        }
        ?>
        <h2>روش پرداخت فعال</h2>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <input type="hidden" name="action" value="zdp_save_settings">
            <input type="hidden" name="zdp_payment_form" value="1">
            <?php wp_nonce_field('zdp_save_settings', 'zdp_settings_nonce'); ?>
            <label><input type="radio" name="zdp_payment_method" value="zarinpal" <?php checked($method, 'zarinpal'); ?>> درگاه آنلاین زرین‌پال</label>
            &nbsp;&nbsp;
            <label><input type="radio" name="zdp_payment_method" value="manual" <?php checked($method, 'manual'); ?>> کارت‌به‌کارت دستی</label>
            <?php submit_button('ذخیره روش پرداخت'); ?>
        </form>

        <hr>
        <h2>خدمات قابل رزرو</h2>
        <table class="widefat striped" style="max-width:700px;">
            <thead><tr><th>نام خدمت</th><th>قیمت (تومان)</th><th>نوع</th><th>عملیات</th></tr></thead>
            <tbody>
            <?php foreach ($services as $s) : ?>
                <tr>
                    <td><?php echo esc_html($s['name']); ?></td>
                    <td><?php echo esc_html(number_format((float) $s['price'])); ?></td>
                    <td><?php
                        $type_labels = ['both' => 'تلفنی و حضوری', 'phone' => 'تلفنی', 'online' => 'آنلاین (تصویری)', 'in_person' => 'حضوری'];
                        echo esc_html($type_labels[$s['type'] ?? 'both'] ?? 'تلفنی و حضوری');
                    ?></td>
                    <td>
                        <a class="button button-small" href="<?php echo esc_url(admin_url('admin.php?page=zdp-admin&tab=services&edit_service=' . urlencode($s['id']))); ?>">ویرایش</a>
                        <a class="button button-small" style="color:#b91c1c;" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=zdp_delete_service&service_id=' . urlencode($s['id'])), 'zdp_delete_service_' . $s['id'])); ?>" onclick="return confirm('حذف این خدمت؟');">حذف</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($services)) : ?>
                <tr><td colspan="4">هنوز هیچ خدمتی تعریف نشده — بدون این لیست، فرم رزرو نوبت در پنل مراجع خالی می‌ماند.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>

        <?php if ($editing !== null) : ?>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin-top:12px;">
                <input type="hidden" name="action" value="zdp_update_service">
                <input type="hidden" name="service_id" value="<?php echo esc_attr($editing['id']); ?>">
                <?php wp_nonce_field('zdp_update_service_' . $editing['id'], 'zdp_service_edit_nonce'); ?>
                <strong>ویرایش خدمت</strong><br>
                <input type="text" name="service_name" value="<?php echo esc_attr($editing['name']); ?>" class="regular-text" required>
                <input type="text" name="service_price" value="<?php echo esc_attr($editing['price']); ?>" required>
                <select name="service_type">
                    <option value="both" <?php selected($editing['type'] ?? 'both', 'both'); ?>>تلفنی و حضوری</option>
                    <option value="phone" <?php selected($editing['type'] ?? 'both', 'phone'); ?>>فقط تلفنی</option>
                    <option value="online" <?php selected($editing['type'] ?? 'both', 'online'); ?>>فقط آنلاین (تصویری)</option>
                    <option value="in_person" <?php selected($editing['type'] ?? 'both', 'in_person'); ?>>فقط حضوری</option>
                </select>
                <button class="button button-primary">ذخیره تغییرات</button>
                <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=zdp-admin&tab=services')); ?>">انصراف</a>
            </form>
        <?php else : ?>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin-top:12px;">
                <input type="hidden" name="action" value="zdp_add_service">
                <?php wp_nonce_field('zdp_add_service', 'zdp_service_nonce'); ?>
                <input type="text" name="service_name" placeholder="نام خدمت (مثلاً: مشاوره فردی ۴۵ دقیقه)" aria-label="نام خدمت (مثلاً: مشاوره فردی ۴۵ دقیقه)" class="regular-text" required>
                <input type="text" name="service_price" placeholder="قیمت به تومان" aria-label="قیمت به تومان" required>
                <select name="service_type">
                    <option value="both">تلفنی و حضوری</option>
                    <option value="phone">فقط تلفنی</option>
                    <option value="online">فقط آنلاین (تصویری)</option>
                    <option value="in_person">فقط حضوری</option>
                </select>
                <button class="button button-primary">افزودن خدمت</button>
            </form>
        <?php endif; ?>

        <hr>
        <h2>کارت‌های بانکی برای پرداخت کارت‌به‌کارت</h2>
        <p class="description">فقط وقتی «کارت‌به‌کارت دستی» بالا انتخاب باشد، این کارت‌ها به مراجع نمایش داده می‌شوند.</p>
        <table class="widefat striped" style="max-width:700px;">
            <thead><tr><th>بانک</th><th>شماره کارت</th><th>شبا</th><th>به‌نام</th><th>عملیات</th></tr></thead>
            <tbody>
            <?php foreach ($cards as $c) : ?>
                <tr>
                    <td><?php echo esc_html($c['bank_name']); ?></td>
                    <td dir="ltr"><?php echo esc_html($c['card_number']); ?></td>
                    <td dir="ltr"><?php echo esc_html($c['iban'] ?? ''); ?></td>
                    <td><?php echo esc_html($c['holder_name']); ?></td>
                    <td>
                        <a class="button button-small" style="color:#b91c1c;" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=zdp_delete_card&card_id=' . urlencode($c['id'])), 'zdp_delete_card_' . $c['id'])); ?>" onclick="return confirm('حذف این کارت؟');">حذف</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($cards)) : ?>
                <tr><td colspan="5">هنوز هیچ کارتی ثبت نشده.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin-top:12px;">
            <input type="hidden" name="action" value="zdp_add_card">
            <?php wp_nonce_field('zdp_add_card', 'zdp_card_nonce'); ?>
            <input type="text" name="card_bank_name" placeholder="نام بانک" aria-label="نام بانک" required>
            <input type="text" name="card_number" placeholder="شماره کارت ۱۶ رقمی" aria-label="شماره کارت ۱۶ رقمی" required>
            <input type="text" name="card_iban" placeholder="شماره شبا (اختیاری)" aria-label="شماره شبا (اختیاری)">
            <input type="text" name="card_holder_name" placeholder="به‌نام" aria-label="به‌نام" required>
            <button class="button button-primary">افزودن کارت</button>
        </form>
        <?php
    }

    public function handle_add_service(): void
    {
        $this->assert_can_manage();
        check_admin_referer('zdp_add_service', 'zdp_service_nonce');

        $name  = sanitize_text_field(wp_unslash($_POST['service_name'] ?? ''));
        $price = preg_replace('/\D+/', '', normalize_digits(wp_unslash($_POST['service_price'] ?? '')));
        $type  = sanitize_text_field(wp_unslash($_POST['service_type'] ?? 'both'));
        if (!in_array($type, ['both', 'phone', 'online', 'in_person'], true)) {
            $type = 'both';
        }

        if ($name !== '' && $price !== '') {
            $services   = get_option('zdp_services_list', []);
            $services[] = ['id' => uniqid('svc_', true), 'name' => $name, 'price' => $price, 'type' => $type];
            update_option('zdp_services_list', $services);
            audit_log('service_added', ['name' => $name]);

            wp_safe_redirect(admin_url('admin.php?page=zdp-admin&tab=services'));
            exit;
        }

        wp_safe_redirect(admin_url('admin.php?page=zdp-admin&tab=services&zdp_notice=service_invalid'));
        exit;
    }

    /**
     * ویرایش نام/قیمت یک خدمت موجود بر اساس id (نه حذف+افزودن مجدد، تا id
     * ثابت بماند — نوبت‌های قبلاً ثبت‌شده snapshot مستقل از service_name/price
     * خودشان را در دیتابیس دارند، پس ویرایش اینجا روی نوبت‌های گذشته اثر
     * نمی‌گذارد؛ فقط روی رزروهای بعدی اعمال می‌شود).
     */
    public function handle_update_service(): void
    {
        $this->assert_can_manage();

        $service_id = sanitize_text_field(wp_unslash($_POST['service_id'] ?? ''));
        check_admin_referer('zdp_update_service_' . $service_id, 'zdp_service_edit_nonce');

        $name  = sanitize_text_field(wp_unslash($_POST['service_name'] ?? ''));
        $price = preg_replace('/\D+/', '', normalize_digits(wp_unslash($_POST['service_price'] ?? '')));
        $type  = sanitize_text_field(wp_unslash($_POST['service_type'] ?? 'both'));
        if (!in_array($type, ['both', 'phone', 'online', 'in_person'], true)) {
            $type = 'both';
        }

        if ($service_id !== '' && $name !== '' && $price !== '') {
            $services = get_option('zdp_services_list', []);
            $found    = false;

            foreach ($services as &$s) {
                if (($s['id'] ?? '') === $service_id) {
                    $s['name']  = $name;
                    $s['price'] = $price;
                    $s['type']  = $type;
                    $found      = true;
                    break;
                }
            }
            unset($s);

            if ($found) {
                update_option('zdp_services_list', $services);
                audit_log('service_updated', ['id' => $service_id, 'name' => $name]);

                wp_safe_redirect(admin_url('admin.php?page=zdp-admin&tab=services&zdp_notice=saved'));
                exit;
            }
        }

        wp_safe_redirect(admin_url('admin.php?page=zdp-admin&tab=services&zdp_notice=service_invalid'));
        exit;
    }

    public function handle_delete_service(): void
    {
        $this->assert_can_manage();
        $service_id = sanitize_text_field(wp_unslash($_GET['service_id'] ?? ''));
        check_admin_referer('zdp_delete_service_' . $service_id);

        $services = get_option('zdp_services_list', []);
        $services = array_values(array_filter($services, static fn ($s) => $s['id'] !== $service_id));
        update_option('zdp_services_list', $services);
        audit_log('service_deleted', ['id' => $service_id]);

        wp_safe_redirect(admin_url('admin.php?page=zdp-admin&tab=services'));
        exit;
    }

    public function handle_add_card(): void
    {
        $this->assert_can_manage();
        check_admin_referer('zdp_add_card', 'zdp_card_nonce');

        $bank   = sanitize_text_field(wp_unslash($_POST['card_bank_name'] ?? ''));
        $number = preg_replace('/\D+/', '', normalize_digits(wp_unslash($_POST['card_number'] ?? '')));
        $iban   = strtoupper(preg_replace('/[^0-9A-Za-z]/', '', normalize_digits(wp_unslash($_POST['card_iban'] ?? ''))));
        $holder = sanitize_text_field(wp_unslash($_POST['card_holder_name'] ?? ''));

        if ($bank !== '' && $number !== '' && $holder !== '') {
            $cards   = get_option('zdp_manual_payment_cards', []);
            $cards[] = ['id' => uniqid('card_', true), 'bank_name' => $bank, 'card_number' => $number, 'iban' => $iban, 'holder_name' => $holder];
            update_option('zdp_manual_payment_cards', $cards);
            audit_log('card_added', ['bank' => $bank]);

            wp_safe_redirect(admin_url('admin.php?page=zdp-admin&tab=services'));
            exit;
        }

        wp_safe_redirect(admin_url('admin.php?page=zdp-admin&tab=services&zdp_notice=card_invalid'));
        exit;
    }

    public function handle_delete_card(): void
    {
        $this->assert_can_manage();
        $card_id = sanitize_text_field(wp_unslash($_GET['card_id'] ?? ''));
        check_admin_referer('zdp_delete_card_' . $card_id);

        $cards = get_option('zdp_manual_payment_cards', []);
        $cards = array_values(array_filter($cards, static fn ($c) => $c['id'] !== $card_id));
        update_option('zdp_manual_payment_cards', $cards);
        audit_log('card_deleted', ['id' => $card_id]);

        wp_safe_redirect(admin_url('admin.php?page=zdp-admin&tab=services'));
        exit;
    }

    private function render_settings_tab(): void
    {
        ?>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <input type="hidden" name="action" value="zdp_save_settings">
            <input type="hidden" name="zdp_settings_form" value="1">
            <?php wp_nonce_field('zdp_save_settings', 'zdp_settings_nonce'); ?>

            <h2>سامانه پیامک</h2>
            <p class="description">ارسال واقعی پیامک از طریق فیلتر مشترک <code>pas_send_sms</code> انجام می‌شود که پلاگین جداگانه‌ی <strong>«پیوند امن — پل پیامک»</strong> پیاده می‌کند؛ نام‌کاربری/رمز ملی پیامک آنجا تنظیم می‌شود، نه اینجا. این پلاگین فقط باید بداند برای هر پیام از <em>کدام پترن</em> (bodyId) استفاده کند.</p>
            <table class="form-table">
                <tr>
                    <th><label for="zdp_sms_body_id">کد الگوی پیامک OTP (کد ورود)</label></th>
                    <td><input type="text" id="zdp_sms_body_id" name="zdp_sms_body_id" value="<?php echo esc_attr((string) get_option('zdp_sms_body_id', '')); ?>" class="regular-text" placeholder="مثلاً 487221"></td>
                </tr>
                <tr>
                    <th><label for="zdp_sms_booking_body_id">کد الگوی پیامک تایید نوبت برای مراجع</label></th>
                    <td><input type="text" id="zdp_sms_booking_body_id" name="zdp_sms_booking_body_id" value="<?php echo esc_attr((string) get_option('zdp_sms_booking_body_id', '')); ?>" class="regular-text">
                        <p class="description">هنوز پترنی برای این پیام تو ملی‌پیامک نساختی — تا نسازی، این پیام سایلنت رد می‌شه (خطا نمی‌ده، فقط ارسال نمی‌شه).</p></td>
                </tr>
                <tr>
                    <th><label for="zdp_sms_admin_body_id">کد الگوی پیامک نوبت جدید برای مدیر</label></th>
                    <td><input type="text" id="zdp_sms_admin_body_id" name="zdp_sms_admin_body_id" value="<?php echo esc_attr((string) get_option('zdp_sms_admin_body_id', '')); ?>" class="regular-text">
                        <p class="description">این هم هنوز پترن نداره.</p></td>
                </tr>
                <tr>
                    <th><label for="zdp_admin_mobile">شماره موبایل مدیر (برای اعلان نوبت جدید)</label></th>
                    <td><input type="text" id="zdp_admin_mobile" name="zdp_admin_mobile" value="<?php echo esc_attr((string) get_option('zdp_admin_mobile', '')); ?>" class="regular-text" placeholder="09xxxxxxxxx"></td>
                </tr>
                <tr>
                    <th><label for="zdp_support_phone">شماره تماس پشتیبانی (نمایش در پنل و صفحه ورود)</label></th>
                    <td><input type="text" id="zdp_support_phone" name="zdp_support_phone" value="<?php echo esc_attr((string) get_option('zdp_support_phone', '')); ?>" class="regular-text" placeholder="مثلاً 02112345678">
                        <p class="description">اگر خالی بماند، هیچ شماره‌ای در پنل یا صفحه‌ی ورود نمایش داده نمی‌شود. در صفحه‌ی ورود فقط وقتی «ارسال مجدد کد» فعال شود نشان داده می‌شود؛ داخل پنل همیشه در فوتر است.</p></td>
                </tr>
                <tr>
                    <th><label for="zdp_lead_followup_24h_template">کد الگوی پیامک فالوآپ ۲۴ ساعته لید کوییز</label></th>
                    <td><input type="text" id="zdp_lead_followup_24h_template" name="zdp_lead_followup_24h_template" value="<?php echo esc_attr((string) get_option('zdp_lead_followup_24h_template', '')); ?>" class="regular-text" placeholder="مثلاً 489436">
                        <p class="description">این پترن فقط یک متغیر «کد پیگیری» دارد — همان شناسه‌ی لید فرستاده می‌شود، نه لینک یا موضوع.</p></td>
                </tr>
                <tr>
                    <th><label for="zdp_lead_followup_72h_template">کد الگوی پیامک فالوآپ ۷۲ ساعته لید کوییز</label></th>
                    <td><input type="text" id="zdp_lead_followup_72h_template" name="zdp_lead_followup_72h_template" value="<?php echo esc_attr((string) get_option('zdp_lead_followup_72h_template', '')); ?>" class="regular-text" placeholder="مثلاً 489441"></td>
                </tr>
            </table>

            <h2>یادآوری خودکار نوبت</h2>
            <p class="description">
                پیامک یادآوری به‌صورت خودکار قبل از هر نوبتِ «فعال» یا «تایید شده» ارسال می‌شود (نوبت‌های ثبت‌شده هم از سایت، هم دستی توسط درمانگر).
                درگاه پیامکی (ملی‌پیامک و غیره) همان‌جایی تنظیم می‌شود که بالاتر اشاره شد (پلاگین «پل پیامک»)؛ اینجا فقط پترن و زمان‌بندی مشخص می‌شود.
            </p>
            <table class="form-table">
                <tr>
                    <th><label for="zdp_appointment_reminder_enabled">فعال‌سازی یادآوری خودکار</label></th>
                    <td><input type="checkbox" id="zdp_appointment_reminder_enabled" name="zdp_appointment_reminder_enabled" value="1" <?php checked((bool) get_option('zdp_appointment_reminder_enabled', false)); ?>></td>
                </tr>
                <tr>
                    <th><label for="zdp_appointment_reminder_hours_before">چند ساعت قبل از جلسه ارسال شود</label></th>
                    <td>
                        <input type="number" id="zdp_appointment_reminder_hours_before" name="zdp_appointment_reminder_hours_before" min="1" max="168" value="<?php echo esc_attr((string) get_option('zdp_appointment_reminder_hours_before', 24)); ?>" style="width:100px;"> ساعت
                        <p class="description">کرون این پلاگین هرساعته اجرا می‌شود، پس دقت زمان‌بندی حدود ±۱ ساعت است.</p>
                    </td>
                </tr>
                <tr>
                    <th><label for="zdp_sms_reminder_body_id">کد الگوی پیامک یادآوری نوبت</label></th>
                    <td><input type="text" id="zdp_sms_reminder_body_id" name="zdp_sms_reminder_body_id" value="<?php echo esc_attr((string) get_option('zdp_sms_reminder_body_id', '')); ?>" class="regular-text" placeholder="مثلاً 493420">
                        <p class="description">پترن باید دو متغیر <code>DATE</code> و <code>TIME</code> داشته باشد. تا وقتی این کد خالی باشد، یادآوری—حتی اگر فعال شده باشد—ارسال نمی‌شود.</p></td>
                </tr>
            </table>

            <h2>زرین‌پال</h2>
            <table class="form-table">
                <tr>
                    <th><label for="zdp_zarinpal_merchant_id">Merchant ID زرین‌پال</label></th>
                    <td><input type="text" id="zdp_zarinpal_merchant_id" name="zdp_zarinpal_merchant_id" value="<?php echo esc_attr((string) get_option('zdp_zarinpal_merchant_id', '')); ?>" class="regular-text"></td>
                </tr>
                <tr>
                    <th><label for="zdp_zarinpal_sandbox">حالت آزمایشی (Sandbox)</label></th>
                    <td><input type="checkbox" id="zdp_zarinpal_sandbox" name="zdp_zarinpal_sandbox" value="1" <?php checked((bool) get_option('zdp_zarinpal_sandbox', false)); ?>></td>
                </tr>
            </table>
            <p class="description">روش پرداخت (زرین‌پال / کارت‌به‌کارت) و مدیریت خدمات و کارت‌ها در تب «خدمات و پرداخت» است.</p>
            <?php submit_button('ذخیره تنظیمات'); ?>
        </form>

        <hr>
        <h2>ساعات‌کاری و تعطیلی رزرو نوبت</h2>
        <p class="description">
            قبل از این نسخه، سرور فقط چک می‌کرد یک بازه‌ی زمانی قبلاً رزرو نشده باشد —
            هیچ محدودیتی روی ساعات مجاز کلینیک یا روزهای تعطیل نبود (فقط UI فرانت جلوش را
            می‌گرفت، که قابل دور زدن بود). این بخش همان محدودیت را سمت سرور هم اعمال می‌کند.
        </p>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <input type="hidden" name="action" value="zdp_save_business_hours">
            <?php wp_nonce_field('zdp_save_business_hours', 'zdp_hours_nonce'); ?>
            <table class="widefat striped">
                <thead><tr><th>روز</th><th>فعال</th><th>از ساعت</th><th>تا ساعت</th><th>نوع جلسات این روز</th><th>شروع استراحت</th><th>پایان استراحت</th></tr></thead>
                <tbody>
                <?php
                $hours = get_option('zdp_business_hours', Booking::default_business_hours());
                $days  = ['شنبه' => 6, 'یکشنبه' => 0, 'دوشنبه' => 1, 'سه‌شنبه' => 2, 'چهارشنبه' => 3, 'پنجشنبه' => 4, 'جمعه' => 5];
                foreach ($days as $label => $w) :
                    $d    = $hours[$w] ?? ['enabled' => false, 'start' => '09:00', 'end' => '21:00', 'mode' => 'both', 'break_start' => '', 'break_end' => ''];
                    $mode = (string) ($d['mode'] ?? 'both');
                ?>
                    <tr>
                        <td><?php echo esc_html($label); ?></td>
                        <td><input type="checkbox" name="zdp_hours[<?php echo (int) $w; ?>][enabled]" value="1" <?php checked(!empty($d['enabled'])); ?>></td>
                        <td><input type="time" name="zdp_hours[<?php echo (int) $w; ?>][start]" value="<?php echo esc_attr($d['start']); ?>"></td>
                        <td><input type="time" name="zdp_hours[<?php echo (int) $w; ?>][end]" value="<?php echo esc_attr($d['end']); ?>"></td>
                        <td>
                            <select name="zdp_hours[<?php echo (int) $w; ?>][mode]">
                                <option value="both" <?php selected($mode, 'both'); ?>>تلفنی و حضوری</option>
                                <option value="phone" <?php selected($mode, 'phone'); ?>>فقط تلفنی</option>
                                <option value="in_person" <?php selected($mode, 'in_person'); ?>>فقط حضوری</option>
                            </select>
                        </td>
                        <td><input type="time" name="zdp_hours[<?php echo (int) $w; ?>][break_start]" value="<?php echo esc_attr($d['break_start'] ?? ''); ?>"></td>
                        <td><input type="time" name="zdp_hours[<?php echo (int) $w; ?>][break_end]" value="<?php echo esc_attr($d['break_end'] ?? ''); ?>"></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <p class="description">
                این ستون کنترل می‌کند که در آن روز از هفته، مراجعان فقط بتوانند نوبت «تلفنی»، فقط «حضوری»، یا هر دو را رزرو کنند —
                به‌شرطی که خودِ «خدمت» هم در تب «خدمات و پرداخت» به یکی از این دو نوع مشخص شده باشد (خدمت‌های «هردو» همیشه در هر روزی قابل‌رزرو هستند).
            </p>
            <p class="description">
                <strong>شروع/پایان استراحت</strong> (اختیاری): یک بازه‌ی داخل همان روز کاری که نوبت‌دهی در آن بسته می‌شود — مثلاً استراحت ناهار
                ۱۳:۰۰ تا ۱۴:۰۰ — بدون این‌که کل آن روز تعطیل شود. اگر خالی بگذارید، آن روز بازه‌ی استراحتی ندارد و کل بازه‌ی «از ساعت» تا «تا ساعت» پیوسته باز است.
            </p>

            <h3>تاریخ‌های تعطیل (استثنا)</h3>
            <p class="description">هر تاریخ در یک خط، به‌فرمت <strong>شمسی</strong> <code>1405/01/01</code> (دقیقاً مثل بقیه‌ی تاریخ‌هایی که در این پنل می‌بینید — تاریخ‌ها همیشه در دیتابیس میلادی ذخیره می‌شوند، اما ورودی/نمایش همه‌جا شمسی است). در این تاریخ‌ها حتی اگر آن روز هفته فعال باشد، رزرو بسته می‌شود.</p>
            <textarea name="zdp_blackout_dates" rows="5" class="large-text" dir="ltr" placeholder="1405/01/01&#10;1405/09/30" aria-label="1405/01/01&#10;1405/09/30"><?php
                $jalali_lines = array_map(
                    static fn($g) => Jalali::gregorian_to_jalali_ymd($g),
                    get_option('zdp_blackout_dates', [])
                );
                echo esc_textarea(implode("\n", $jalali_lines));
            ?></textarea>

            <?php submit_button('ذخیره ساعات‌کاری'); ?>
        </form>

        <hr>
        <h2>سوالات نظرسنجی</h2>
        <?php $this->render_survey_questions_form(); ?>

        <hr>
        <h3>لاگ ممیزی (۵۰ رویداد آخر)</h3>
        <ul>
            <?php foreach (array_reverse(array_slice(get_option('zdp_audit_log', []), -50)) as $entry) : ?>
                <li><code><?php echo esc_html($entry['time']); ?></code> — <?php echo esc_html($entry['action']); ?></li>
            <?php endforeach; ?>
        </ul>
        <?php
    }

    private function render_survey_questions_form(): void
    {
        $questions       = Survey::get_questions_config();
        $extra_saved     = get_option('zdp_survey_extra_questions', []);
        if (!is_array($extra_saved)) {
            $extra_saved = [];
        }
        ?>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <input type="hidden" name="action" value="zdp_save_survey_questions">
            <?php wp_nonce_field('zdp_save_survey_questions', 'zdp_survey_q_nonce'); ?>

            <h4>سوالات اصلی</h4>
            <?php foreach ($questions as $key => $q) : ?>
                <table class="form-table">
                    <tr>
                        <th><label for="<?php echo esc_attr($key); ?>_label">متن سوال <?php echo esc_html(substr($key, 1)); ?></label></th>
                        <td><input type="text" id="<?php echo esc_attr($key); ?>_label" name="questions[<?php echo esc_attr($key); ?>][label]" value="<?php echo esc_attr($q['label']); ?>" class="large-text"></td>
                    </tr>
                    <tr>
                        <th>نوع سوال</th>
                        <td>
                            <label><input type="radio" name="questions[<?php echo esc_attr($key); ?>][type]" value="choice" <?php checked(($q['type'] ?? 'choice'), 'choice'); ?>> چندگزینه‌ای</label>
                            &nbsp;&nbsp;
                            <label><input type="radio" name="questions[<?php echo esc_attr($key); ?>][type]" value="text" <?php checked(($q['type'] ?? 'choice'), 'text'); ?>> تشریحی (پاسخ آزاد)</label>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="<?php echo esc_attr($key); ?>_options">گزینه‌ها (هرکدام در یک خط — فقط برای نوع چندگزینه‌ای)</label></th>
                        <td><textarea id="<?php echo esc_attr($key); ?>_options" name="questions[<?php echo esc_attr($key); ?>][options]" rows="4" class="large-text"><?php echo esc_textarea(implode("\n", $q['options'])); ?></textarea></td>
                    </tr>
                </table>
            <?php endforeach; ?>

            <h4>سوالات اضافی (اختیاری)</h4>
            <p class="description">
                هر سطر خالی نادیده گرفته می‌شود. تا <?php echo (int) Survey::MAX_EXTRA_QUESTIONS; ?> سوال اضافی
                (چندگزینه‌ای یا تشریحی) می‌توانید اینجا تعریف کنید — این‌ها اجباری نیستند.
            </p>
            <?php for ($i = 0; $i < Survey::MAX_EXTRA_QUESTIONS; $i++) : ?>
                <?php $eq = $extra_saved[$i] ?? ['label' => '', 'type' => 'choice', 'options' => []]; ?>
                <table class="form-table">
                    <tr>
                        <th><label for="extra_<?php echo $i; ?>_label">سوال اضافی <?php echo $i + 1; ?></label></th>
                        <td><input type="text" id="extra_<?php echo $i; ?>_label" name="extra_questions[<?php echo $i; ?>][label]" value="<?php echo esc_attr($eq['label'] ?? ''); ?>" class="large-text" placeholder="خالی بگذارید تا نادیده گرفته شود"></td>
                    </tr>
                    <tr>
                        <th>نوع سوال</th>
                        <td>
                            <label><input type="radio" name="extra_questions[<?php echo $i; ?>][type]" value="choice" <?php checked(($eq['type'] ?? 'choice'), 'choice'); ?>> چندگزینه‌ای</label>
                            &nbsp;&nbsp;
                            <label><input type="radio" name="extra_questions[<?php echo $i; ?>][type]" value="text" <?php checked(($eq['type'] ?? 'choice'), 'text'); ?>> تشریحی (پاسخ آزاد)</label>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="extra_<?php echo $i; ?>_options">گزینه‌ها (هرکدام در یک خط — فقط برای نوع چندگزینه‌ای)</label></th>
                        <td><textarea id="extra_<?php echo $i; ?>_options" name="extra_questions[<?php echo $i; ?>][options]" rows="3" class="large-text"><?php echo esc_textarea(implode("\n", $eq['options'] ?? [])); ?></textarea></td>
                    </tr>
                </table>
            <?php endfor; ?>

            <?php submit_button('ذخیره سوالات نظرسنجی'); ?>
        </form>
        <?php
    }

    public function handle_save_survey_questions(): void
    {
        $this->assert_can_manage();
        check_admin_referer('zdp_save_survey_questions', 'zdp_survey_q_nonce');

        $raw       = wp_unslash($_POST['questions'] ?? []);
        $defaults  = Survey::default_questions_config();
        $sanitized = [];

        foreach ($defaults as $key => $default_q) {
            $label = isset($raw[$key]['label']) ? sanitize_text_field($raw[$key]['label']) : $default_q['label'];
            $type  = in_array($raw[$key]['type'] ?? '', ['choice', 'text'], true) ? $raw[$key]['type'] : 'choice';
            $options = isset($raw[$key]['options'])
                ? array_values(array_filter(array_map('sanitize_text_field', explode("\n", str_replace("\r", '', $raw[$key]['options'])))))
                : $default_q['options'];

            $sanitized[$key] = [
                'label'   => $label !== '' ? $label : $default_q['label'],
                'type'    => $type,
                // گزینه‌ها فقط برای نوع چندگزینه‌ای واقعاً لازم‌اند؛ برای نوع
                // تشریحی هم ذخیره می‌شوند (بی‌ضرر) تا اگر بعداً به چندگزینه‌ای
                // برگردد، چیزی گم نشده باشد.
                'options' => !empty($options) ? $options : $default_q['options'],
            ];
        }

        update_option('zdp_survey_questions', $sanitized);

        $raw_extra   = wp_unslash($_POST['extra_questions'] ?? []);
        $sanitized_extra = [];

        foreach (array_slice((array) $raw_extra, 0, Survey::MAX_EXTRA_QUESTIONS) as $eq) {
            $label = isset($eq['label']) ? sanitize_text_field($eq['label']) : '';
            if ($label === '') {
                continue; // سطر خالی؛ نادیده گرفته می‌شود
            }

            $type    = in_array($eq['type'] ?? '', ['choice', 'text'], true) ? $eq['type'] : 'choice';
            $options = isset($eq['options'])
                ? array_values(array_filter(array_map('sanitize_text_field', explode("\n", str_replace("\r", '', $eq['options'])))))
                : [];

            if ($type === 'choice' && empty($options)) {
                continue; // چندگزینه‌ای بدون هیچ گزینه‌ای قابل استفاده نیست
            }

            $sanitized_extra[] = ['label' => $label, 'type' => $type, 'options' => $options];
        }

        update_option('zdp_survey_extra_questions', $sanitized_extra);

        audit_log('survey_questions_saved');

        wp_safe_redirect(admin_url('admin.php?page=zdp-admin&tab=settings&zdp_notice=saved'));
        exit;
    }

    private function assert_can_manage(): void
    {
        if (!current_user_can(Security::CAPABILITY) && !current_user_can('manage_options')) {
            wp_die('دسترسی غیرمجاز', 403);
        }
    }

    public function handle_save_settings(): void
    {
        $this->assert_can_manage();
        check_admin_referer('zdp_save_settings', 'zdp_settings_nonce');

        if (!empty($_POST['zdp_settings_form'])) {
            $fields = [
                'zdp_sms_body_id',
                'zdp_sms_booking_body_id', 'zdp_sms_admin_body_id',
                'zdp_support_phone',
                'zdp_zarinpal_merchant_id',
                'zdp_lead_followup_24h_template', 'zdp_lead_followup_72h_template',
                'zdp_sms_reminder_body_id',
            ];
            foreach ($fields as $field) {
                if (isset($_POST[$field])) {
                    update_option($field, sanitize_text_field(wp_unslash($_POST[$field])));
                }
            }

            // باگ رفع‌شده: قبلاً zdp_admin_mobile عیناً همراه بقیه‌ی فیلدهای
            // متنی فقط با sanitize_text_field ذخیره می‌شد — برخلاف هر شماره‌ی
            // موبایل دیگری در این پلاگین (مراجعین) که همیشه از to_local_format()
            // رد می‌شود تا فرمت نهایی همیشه 09xxxxxxxxx باشد (همان فرمتی که
            // handler واقعی pas_send_sms روی این اکوسیستم دریافت می‌کند). چون
            // مقدار پیش‌فرضِ seed‌شده در Installer::seed_default_sms_templates با
            // پیشوند بین‌المللی «۰۰۹۸» بود، پیامک «نوبت جدید» به مدیر روی هر نصب
            // تازه‌ای که ادمین هنوز این فیلد را دستی دوباره ذخیره نکرده، می‌توانست
            // بی‌صدا با فرمت اشتباه به فیلتر پیامک پاس داده شود. حالا اینجا هم
            // دقیقاً همان نرمال‌سازی/اعتبارسنجی مسیر مراجعین اجرا می‌شود؛ اگر بعد
            // از نرمال‌سازی معتبر نبود، مقدار قبلی دست‌نخورده می‌ماند (به‌جای
            // ذخیره‌ی یک مقدار خراب/خالی).
            if (isset($_POST['zdp_admin_mobile'])) {
                $admin_mobile = to_local_format(sanitize_text_field(wp_unslash($_POST['zdp_admin_mobile'])));
                if (is_valid_mobile($admin_mobile)) {
                    update_option('zdp_admin_mobile', $admin_mobile);
                }
            }

            update_option('zdp_zarinpal_sandbox', !empty($_POST['zdp_zarinpal_sandbox']));
            update_option('zdp_appointment_reminder_enabled', !empty($_POST['zdp_appointment_reminder_enabled']));

            $hours_before = (int) ($_POST['zdp_appointment_reminder_hours_before'] ?? 24);
            if ($hours_before < 1 || $hours_before > 168) {
                $hours_before = 24;
            }
            update_option('zdp_appointment_reminder_hours_before', $hours_before);
        }

        if (!empty($_POST['zdp_payment_form']) && isset($_POST['zdp_payment_method']) && in_array($_POST['zdp_payment_method'], ['zarinpal', 'manual'], true)) {
            update_option('zdp_payment_method', sanitize_key($_POST['zdp_payment_method']));
        }

        audit_log('settings_saved');
        $redirect_tab = !empty($_POST['zdp_payment_form']) ? 'services' : 'settings';
        wp_safe_redirect(admin_url('admin.php?page=zdp-admin&tab=' . $redirect_tab . '&zdp_notice=saved'));
        exit;
    }

    public function handle_save_business_hours(): void
    {
        $this->assert_can_manage();
        check_admin_referer('zdp_save_business_hours', 'zdp_hours_nonce');

        $raw   = (array) ($_POST['zdp_hours'] ?? []);
        $clean = [];
        for ($w = 0; $w <= 6; $w++) {
            $row   = $raw[$w] ?? [];
            $start = sanitize_text_field(wp_unslash($row['start'] ?? '09:00'));
            $end   = sanitize_text_field(wp_unslash($row['end'] ?? '21:00'));
            // اگر فرمت HH:MM نبود، به پیش‌فرض امن برمی‌گردیم تا یک مقدار
            // خراب کل آن روز را به‌طور ناخواسته "همیشه باز" یا خراب نکند.
            if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $start)) {
                $start = '09:00';
            }
            if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $end)) {
                $end = '21:00';
            }
            $mode = sanitize_text_field(wp_unslash($row['mode'] ?? 'both'));
            if (!in_array($mode, ['both', 'phone', 'in_person'], true)) {
                $mode = 'both';
            }

            // بازه‌ی استراحت اختیاری است: اگر یکی از دو مقدار خالی/نامعتبر
            // باشد، یا break_start >= break_end باشد، هر دو را خالی در نظر
            // می‌گیریم (یعنی آن روز اصلاً استراحتی ندارد) — به‌جای این‌که یک
            // مقدار نصفه‌ونیمه ذخیره شود که بعداً رفتار غیرمنتظره بدهد.
            $break_start = sanitize_text_field(wp_unslash($row['break_start'] ?? ''));
            $break_end   = sanitize_text_field(wp_unslash($row['break_end'] ?? ''));
            $valid_time  = '/^([01]\d|2[0-3]):[0-5]\d$/';
            if (
                $break_start === '' || $break_end === ''
                || !preg_match($valid_time, $break_start)
                || !preg_match($valid_time, $break_end)
                || $break_start >= $break_end
            ) {
                $break_start = '';
                $break_end   = '';
            }

            $clean[$w] = [
                'enabled'     => !empty($row['enabled']),
                'start'       => $start,
                'end'         => $end,
                'mode'        => $mode,
                'break_start' => $break_start,
                'break_end'   => $break_end,
            ];
        }
        update_option('zdp_business_hours', $clean);

        $blackout_raw = (string) wp_unslash($_POST['zdp_blackout_dates'] ?? '');
        $blackout     = [];
        foreach (preg_split('/[\r\n]+/', $blackout_raw) as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $gregorian = Jalali::jalali_string_to_gregorian_string($line);
            if ($gregorian !== null) {
                $blackout[] = $gregorian;
            }
        }
        update_option('zdp_blackout_dates', array_values(array_unique($blackout)));

        audit_log('business_hours_saved');
        wp_safe_redirect(admin_url('admin.php?page=zdp-admin&tab=settings&zdp_notice=saved'));
        exit;
    }
}
