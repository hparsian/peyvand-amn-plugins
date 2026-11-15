<?php

if (!defined('ABSPATH')) {
    exit;
}

final class Pas_CF_Admin
{
    public function __construct()
    {
        add_action('admin_menu', [$this, 'register_menu']);
        add_action('admin_post_pas_cf_save_case', [$this, 'handle_save_case']);
        add_action('admin_post_pas_cf_add_note', [$this, 'handle_add_note']);
        add_action('admin_post_pas_cf_edit_note', [$this, 'handle_edit_note']);
        add_action('admin_post_pas_cf_delete_note', [$this, 'handle_delete_note']);
        add_action('admin_post_pas_cf_restore_note', [$this, 'handle_restore_note']);
        add_action('admin_post_pas_cf_export_csv', [$this, 'handle_export_csv']);
        add_action('admin_post_pas_cf_print_case', [$this, 'handle_print_case']);
        add_action('admin_post_pas_cf_apply_intake_map', [$this, 'handle_apply_intake_map']);
        add_action('admin_post_pas_cf_send_reminder', [$this, 'handle_send_reminder']);
        add_action('admin_notices', [$this, 'maybe_show_key_notice']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
    }

    public function register_menu(): void
    {
        add_menu_page(
            'پرونده مراجعین',
            'پرونده مراجعین',
            PAS_CF_CAP,
            'pas-case-files',
            [$this, 'render_list_page'],
            'dashicons-clipboard',
            27
        );

        // برچسب زیرمنو عمداً خنثی است («لیست پرونده‌ها») نه «همه‌ی پرونده‌ها»:
        // برای کاربری که PAS_CF_CAP_ALL ندارد، این صفحه فقط پرونده‌های خودش
        // را نشان می‌دهد و عنوانِ گمراه‌کننده نباید در منو ثابت بماند.
        // داشبورد درمانگر — صفحه‌ی اول منو (فاز ۱۲ / گام ۴). همه‌ی اعداد از
        // همان کوئری‌های محدوده‌ی دید PAS_CF_Reports می‌آید و لینک‌هایش فقط
        // به صفحات موجود با همین کپابیلیتی می‌رود.
        add_submenu_page('pas-case-files', 'داشبورد درمانگر', 'داشبورد', PAS_CF_CAP, 'pas-case-dashboard', ['Pas_CF_Dashboard', 'render']);

        // فاز ۵: یک جای مشخص برای اقدام روی هشدارها و وظایف. برچسب منو
        // تعداد هشدارهای بازِ قابل‌دیدن همین کاربر را نشان می‌دهد تا هشدار
        // بالینی در میان منوها گم نشود؛ برای کاربر بدون دسترسی، هیچ عددی
        // محاسبه/نمایش داده نمی‌شود.
        $open_alerts = 0;
        if (class_exists('Pas_CF_Alerts') && Pas_CF_Security::current_user_can_access()) {
            $open_alerts = Pas_CF_Alerts::count_open_visible();
        }
        $alerts_label = 'هشدارها و وظایف';
        if ($open_alerts > 0) {
            $alerts_label .= sprintf(' <span class="update-plugins count-%1$d"><span class="plugin-count">%1$d</span></span>', $open_alerts);
        }
        add_submenu_page('pas-case-files', 'هشدارها و وظایف', $alerts_label, PAS_CF_CAP, Pas_CF_Alerts_Page::PAGE, ['Pas_CF_Alerts_Page', 'render']);

        add_submenu_page('pas-case-files', 'لیست پرونده‌ها', 'لیست پرونده‌ها', PAS_CF_CAP, 'pas-case-files', [$this, 'render_list_page']);
        add_submenu_page('pas-case-files', 'پرونده‌ی جدید', 'پرونده‌ی جدید', PAS_CF_CAP, 'pas-case-file-new', [$this, 'render_edit_page']);
        add_submenu_page('pas-case-files', 'گزارش‌های بالینی', 'گزارش‌ها', PAS_CF_CAP, 'pas-case-reports', [$this, 'render_reports_page']);
        // صفحه‌ی مشاهده/ویرایش تک پرونده — عمداً در منو نیست، فقط از لیست لینک می‌شود
    }

    public function enqueue_assets(string $hook): void
    {
        // صفحه‌های این منو هم با هوک `toplevel_page_...` و هم با
        // `pas-case-files_page_...` صدا زده می‌شوند؛ چک روی پارامتر page
        // هر دو حالت را می‌گیرد (و هوک هم برای سازگاری نگه داشته شده است).
        $page = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';
        $is_our_page = $page !== '' && strpos($page, 'pas-case-') === 0;

        if (!$is_our_page && strpos($hook, 'pas-case-file') === false) {
            return;
        }
        wp_enqueue_style('pas-cf-admin', PAS_CF_URL . 'assets/css/admin.css', class_exists('Pas_Core_Tokens') ? [\Pas_Core_Tokens::handle()] : [], PAS_CF_VERSION);

        // پوسته‌ی برند (فاز ۱۲): فقط در همین صفحات. اگر کیت رابط کاربری فعال
        // باشد، فونت و توکن‌های آن هم بارگذاری می‌شوند؛ در غیر این صورت
        // admin-skin.css با رنگ‌های برند خودش کار می‌کند.
        $skin_deps = ['pas-cf-admin'];
        if (class_exists('PUI')) {
            PUI::enqueue('admin');
            $skin_deps[] = PUI::handle('tokens');
        }
        wp_enqueue_style('pas-cf-admin-skin', PAS_CF_URL . 'assets/css/admin-skin.css', $skin_deps, PAS_CF_VERSION);

        // فقط برای فرم «پرونده‌ی جدید» — جست‌وجوی خودکار نام مراجع بر اساس
        // شماره موبایل از طریق نقطه‌ی AJAX مشترک هسته (core).
        wp_enqueue_script('pas-cf-admin', PAS_CF_URL . 'assets/js/admin.js', [], PAS_CF_VERSION, true);
        wp_localize_script('pas-cf-admin', 'PasCfClientLookup', [
            'nonce' => wp_create_nonce('pas_core_lookup_client_name'),
        ]);
        // برای هشدار نرم «این تاریخ در آینده است» روی فیلدهای تاریخ جلسه —
        // مقایسه‌ی رشته‌ای کافی است چون فرمت شمسی همیشه Y/m/d با صفر
        // ابتدایی است (مثل مقایسه‌ی ISO date، ترتیب لغوی = ترتیب زمانی).
        wp_localize_script('pas-cf-admin', 'PasCfToday', [
            'jalali' => Pas_CF_Jalali::to_jalali_string(current_time('Y-m-d')),
        ]);
    }

    public function maybe_show_key_notice(): void
    {
        if (!Pas_CF_Security::current_user_can_access()) {
            return;
        }
        if (!class_exists('Pas_Core_Crypto')) {
            ?>
            <div class="notice notice-error">
                <p><strong>پرونده مراجعین:</strong> افزونه‌ی «پیوند امن — هسته مشترک» فعال نیست. رمزنگاری یادداشت‌های بالینی به آن افزونه منتقل شده — لطفاً آن را نصب و فعال کن.</p>
            </div>
            <?php
            return;
        }
        if (Pas_CF_Crypto::is_configured()) {
            return;
        }

        $suggested = Pas_CF_Crypto::generate_suggested_key();
        ?>
        <div class="notice notice-error">
            <p><strong>پرونده مراجعین:</strong> کلید رمزنگاری تنظیم نشده. تا این کار انجام نشود، امکان ثبت یادداشت جلسه‌ی جدید وجود ندارد (یادداشت‌های قبلی هم رمزگشایی نمی‌شوند).</p>
            <p>این خط را <strong>یک‌بار</strong> به فایل <code>wp-config.php</code> (بالاتر از خط «That's all, stop editing!») اضافه کنید و بعد این صفحه را رفرش کنید:</p>
            <p><code style="user-select:all;">define('PAS_CORE_ENCRYPTION_KEY', '<?php echo esc_html($suggested); ?>');</code></p>
            <p>⚠️ این کلید فقط همین یک‌بار نمایش داده می‌شود و در دیتابیس ذخیره نمی‌شود. از wp-config.php یک بک‌آپ امن نگه دارید — اگر این کلید گم شود، یادداشت‌های قبلی برای همیشه غیرقابل‌بازیابی می‌شوند. همین کلید، برای رمزنگاری نتایج آزمون‌های روان‌سنجی در quiz-enterprise هم استفاده می‌شود — یک کلید مشترک برای کل اکوسیستم.</p>
        </div>
        <?php
    }

    /**
     * اعمال نگاشت فرم ارزیابی روی پرونده. سه لایه‌ی کنترل: دسترسی کلی
     * پرونده، nonce فرم، و دسترسی به همین پرونده‌ی مشخص (همان
     * current_user_can_access_case که جای دیگر استفاده می‌شود).
     * داده‌ی فرم از همان قرارداد فقط‌خواندنی zdp_get_intake_for_phone می‌آید
     * و طرح نگاشت دوباره محاسبه می‌شود — به ورودی مرورگر اعتماد نمی‌کنیم.
     */
    public function handle_apply_intake_map(): void
    {
        Pas_CF_Security::require_access();
        check_admin_referer('pas_cf_apply_intake_map');

        $case_id = isset($_POST['case_id']) ? (int) $_POST['case_id'] : 0;
        $case    = $case_id > 0 ? Pas_CF_Case_File::get($case_id) : null;

        if (!$case) {
            wp_die('پرونده پیدا نشد.', 'خطا', ['response' => 404]);
        }

        Pas_CF_Security::require_case_access($case);

        $phone  = (string) $case->phone;
        $intake = $phone !== '' ? apply_filters('zdp_get_intake_for_phone', [], $phone) : [];
        $intake = is_array($intake) ? $intake : [];

        $rows = Pas_CF_Case_File::intake_mapping_preview($case, $intake);

        $updated = 0;
        if (!empty($rows)) {
            $result = Pas_CF_Case_File::apply_intake_mapping(
                $case_id,
                $rows,
                !empty($_POST['overwrite']),
                (string) ($intake['file_number'] ?? ''),
                isset($intake['submitted_at']) ? (string) $intake['submitted_at'] : null
            );
            $updated = (int) $result['updated'];
        }

        wp_safe_redirect(admin_url(
            'admin.php?page=pas-case-file-new&case_id=' . $case_id . '&pas_map=done&updated=' . $updated
        ));
        exit;
    }

    /**
     * صفحه‌ی گزارش‌های بالینی — فقط‌خواندنی، با همان محدوده‌ی دید لیست
     * پرونده‌ها (درمانگر عادی فقط پرونده‌های خودش را می‌بیند، چون همه‌ی
     * کوئری‌های Pas_CF_Reports با همان شرط محدوده اجرا می‌شوند).
     */
    public function render_reports_page(): void
    {
        Pas_CF_Security::require_access();

        $days    = isset($_GET['days']) ? (int) $_GET['days'] : 30;
        $summary = Pas_CF_Reports::summary($days);
        $statuses = Pas_CF_Case_File::session_statuses();
        ?>
        <div class="wrap pas-cf-wrap">
            <h1>گزارش‌های بالینی</h1>
            <p class="description">
                همه‌ی اعداد از پرونده‌های در دسترس همین کاربر محاسبه شده‌اند؛ متن یادداشت‌های بالینی خوانده یا نمایش داده نمی‌شود.
            </p>

            <form method="get" style="margin:1em 0; display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
                <input type="hidden" name="page" value="pas-case-reports">
                <label>بازه‌ی گزارش
                    <select name="days">
                        <?php foreach (Pas_CF_Reports::ALLOWED_WINDOWS as $window) : ?>
                            <option value="<?php echo esc_attr((string) $window); ?>"<?php echo (int) $summary['days'] === $window ? ' selected' : ''; ?>><?php echo esc_html((string) $window); ?> روز</option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <button class="button">اعمال</button>
                <span class="description">از <?php echo esc_html(Pas_CF_Jalali::to_jalali_string((string) $summary['since'])); ?> تا امروز</span>
            </form>

            <h2>خلاصه</h2>
            <table class="widefat striped pas-cf-report-summary">
                <tbody>
                    <tr><th>پرونده‌ها (کل)</th><td><?php echo esc_html((string) $summary['cases_total']); ?></td></tr>
                    <tr><th>پرونده‌های فعال</th><td><?php echo esc_html((string) $summary['cases_active']); ?></td></tr>
                    <tr><th>جلسات در این بازه</th><td><?php echo esc_html((string) $summary['sessions_total']); ?></td></tr>
                    <tr><th>میانگین جلسه به ازای هر پرونده‌ی فعال</th><td><?php echo esc_html((string) $summary['sessions_per_case']); ?></td></tr>
                    <tr><th>میانگین مدت جلسه</th><td><?php echo $summary['avg_duration'] > 0 ? esc_html((string) $summary['avg_duration']) . ' دقیقه' : '—'; ?></td></tr>
                    <tr><th>نرخ عدم حضور</th><td><?php echo esc_html((string) $summary['no_show_rate']); ?>٪</td></tr>
                </tbody>
            </table>

            <h2>جلسات به تفکیک وضعیت</h2>
            <table class="widefat striped">
                <thead><tr><th>وضعیت</th><th>تعداد</th></tr></thead>
                <tbody>
                    <?php if (empty($summary['sessions_by_status'])) : ?>
                        <tr><td colspan="2">در این بازه جلسه‌ای ثبت نشده.</td></tr>
                    <?php else : ?>
                        <?php foreach ($summary['sessions_by_status'] as $status_key => $count) : ?>
                            <tr>
                                <td><?php echo esc_html($statuses[$status_key] ?? $status_key); ?></td>
                                <td><?php echo esc_html((string) $count); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>

            <h2>پرونده‌های راکد</h2>
            <p class="description">پرونده‌ی فعالی که در این بازه جلسه‌ای نداشته (یا هیچ جلسه‌ای ندارد).</p>
            <?php if (empty($summary['stagnant'])) : ?>
                <p>✅ پرونده‌ی راکدی نیست.</p>
            <?php else : ?>
                <table class="widefat striped">
                    <thead><tr><th>نام</th><th>موبایل</th><th>آخرین جلسه</th><th></th></tr></thead>
                    <tbody>
                        <?php foreach ($summary['stagnant'] as $row) : ?>
                            <tr>
                                <td><?php echo esc_html($row->full_name !== '' ? $row->full_name : '—'); ?></td>
                                <td dir="ltr"><?php echo esc_html((string) $row->phone); ?></td>
                                <td><?php echo esc_html(!empty($row->last_session) ? Pas_CF_Jalali::to_jalali_string((string) $row->last_session) : 'بدون جلسه'); ?></td>
                                <td><a class="button button-small" href="<?php echo esc_url(admin_url('admin.php?page=pas-case-file-new&case_id=' . (int) $row->id)); ?>">باز کردن پرونده</a></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>

            <h2>جلسات بعدی ثبت‌شده</h2>
            <?php if (empty($summary['upcoming'])) : ?>
                <p class="description">جلسه‌ی بعدی ثبت نشده.</p>
            <?php else : ?>
                <table class="widefat striped">
                    <thead><tr><th>نام</th><th>تاریخ جلسه‌ی بعد</th><th>شماره‌ی جلسه‌ی قبلی</th><th></th></tr></thead>
                    <tbody>
                        <?php foreach ($summary['upcoming'] as $row) : ?>
                            <tr>
                                <td><?php echo esc_html($row->full_name !== '' ? $row->full_name : '—'); ?></td>
                                <td><?php echo esc_html(Pas_CF_Jalali::to_jalali_string((string) $row->next_session_date)); ?></td>
                                <td><?php echo (int) $row->session_number > 0 ? esc_html((string) (int) $row->session_number) : '—'; ?></td>
                                <td><a class="button button-small" href="<?php echo esc_url(admin_url('admin.php?page=pas-case-file-new&case_id=' . (int) $row->case_id)); ?>">باز کردن پرونده</a></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        <?php
    }

    // ── لیست پرونده‌ها ───────────────────────────────────────────────────

    /** پرونده‌ی «فعال» که بیش از این تعداد روز از آخرین جلسه‌اش گذشته، راکد علامت می‌خورد. */
    private const STALE_DAYS = 30;

    /**
     * ارسال یادآور جلسه — فقط با رضایت صریح مراجع، جلسه‌ی بعدی ثبت‌شده،
     * کانال مشخص و رعایت فاصله‌ی ۱۲ ساعت. متن پیام هیچ‌جا ذخیره نمی‌شود؛
     * فقط زمان ارسال در پرونده و اکشن send_reminder در لاگ می‌نشیند.
     */
    public function handle_send_reminder(): void
    {
        Pas_CF_Security::require_access();
        check_admin_referer('pas_cf_send_reminder');

        $case_id = isset($_POST['case_id']) ? (int) $_POST['case_id'] : 0;
        $case    = $case_id > 0 ? Pas_CF_Case_File::get($case_id) : null;

        if (!$case) {
            wp_die('پرونده پیدا نشد.', 'خطا', ['response' => 404]);
        }

        Pas_CF_Security::require_case_access($case);

        $next   = Pas_CF_Case_File::get_next_session($case);
        $result = Pas_CF_Case_File::reminder_blocker($case, $next);

        if ($result === 'ok') {
            $name     = $case->full_name !== '' ? $case->full_name : 'مراجع گرامی';
            $jalali   = Pas_CF_Jalali::to_jalali_string((string) $next->next_session_date);
            $channel  = (string) $case->reminder_channel;

            if ($channel === 'email') {
                $user = Pas_CF_Case_File::find_linked_wp_user($case->phone);

                if ($user && is_email($user->user_email)) {
                    $body   = sprintf('%s، یادآوری جلسه‌ی شما در تاریخ %s است. — پیوند امن', $name, $jalali);
                    $result = wp_mail($user->user_email, 'یادآوری جلسه', $body) ? 'sent' : 'failed';
                } else {
                    $result = 'failed';
                }
            } else {
                $pattern = (string) apply_filters(
                    'pas_cf_reminder_sms_pattern',
                    (string) get_option('pas_cf_reminder_sms_pattern', '')
                );

                if ($pattern === '' || !has_filter('pas_send_sms')) {
                    $result = 'sms_not_configured';
                } else {
                    $sent   = apply_filters('pas_send_sms', null, $case->phone, $pattern, [
                        'NAME' => $name,
                        'DATE' => $jalali,
                    ]);
                    $result = $sent === false ? 'failed' : 'sent';
                }
            }

            if ($result === 'sent') {
                Pas_CF_Case_File::mark_reminder_sent($case_id);
            }
        }

        wp_safe_redirect(admin_url(
            'admin.php?page=pas-case-file-new&case_id=' . $case_id . '&reminder=' . $result
        ));
        exit;
    }

    public function render_list_page(): void
    {
        Pas_CF_Security::require_access();

        $search    = isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '';
        $can_view_all = Pas_CF_Security::current_user_can_view_all();
        $cases     = $can_view_all
            ? Pas_CF_Case_File::list_all()
            : Pas_CF_Case_File::list_for_therapist(get_current_user_id());

        if ($search !== '') {
            $needle = Pas_CF_Case_File::normalize_phone($search);
            $cases  = array_values(array_filter($cases, function ($c) use ($search, $needle) {
                return str_contains($c->full_name, $search) || str_contains($c->phone, $needle);
            }));
        }

        $last_session_map = Pas_CF_Case_File::get_last_session_map(wp_list_pluck($cases, 'id'));
        // یک کوئری برای همه‌ی حساب‌های OTP این صفحه — به‌جای یک کوئری در هر ردیف
        Pas_CF_Case_File::preload_linked_wp_users(wp_list_pluck($cases, 'phone'));
        $today_ts = strtotime(current_time('Y-m-d'));
        ?>
        <div class="wrap pas-cf-wrap">
            <h1 class="wp-heading-inline"><?php echo $can_view_all ? 'پرونده‌های مراجعین (همه‌ی درمانگرها)' : 'پرونده‌های من'; ?></h1>
            <a href="<?php echo esc_url(admin_url('admin.php?page=pas-case-file-new')); ?>" class="page-title-action">+ پرونده‌ی جدید</a>

            <form method="get" style="margin:1em 0; display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
                <input type="hidden" name="page" value="pas-case-files">
                <input type="search" name="s" value="<?php echo esc_attr($search); ?>" placeholder="جستجو با نام یا شماره موبایل">
                <button class="button">جستجو</button>
                <a class="button" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=pas_cf_export_csv&s=' . rawurlencode($search)), 'pas_cf_export_csv')); ?>">⬇ خروجی CSV</a>
            </form>

            <table class="widefat striped">
                <thead>
                    <tr>
                        <th>نام</th><th>موبایل</th><th>موضوع</th><th>وضعیت</th>
                        <?php if ($can_view_all) : ?><th>درمانگر مسئول</th><?php endif; ?>
                        <th>حساب مراجع (OTP)</th><th>آخرین به‌روزرسانی</th><th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($cases)) : ?>
                        <tr><td colspan="<?php echo $can_view_all ? 8 : 7; ?>">هنوز پرونده‌ای ثبت نشده.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($cases as $case) :
                        $linked_user = Pas_CF_Case_File::find_linked_wp_user($case->phone);
                        $therapist   = $case->assigned_therapist ? get_userdata((int) $case->assigned_therapist) : false;

                        $is_stale = false;
                        if ($case->status === 'فعال') {
                            $reference = $last_session_map[$case->id] ?? substr((string) $case->created_at, 0, 10);
                            if ($reference && $reference !== '0000-00-00') {
                                $days = (int) floor(($today_ts - strtotime($reference)) / DAY_IN_SECONDS);
                                $is_stale = $days > self::STALE_DAYS;
                            }
                        }
                        ?>
                        <tr<?php echo $is_stale ? ' class="pas-cf-stale-row"' : ''; ?>>
                            <td><strong><?php echo esc_html($case->full_name ?: '—'); ?></strong></td>
                            <td dir="ltr"><?php echo esc_html($case->phone); ?></td>
                            <td><?php echo esc_html($case->topic); ?></td>
                            <td>
                                <?php echo esc_html($case->status); ?>
                                <?php if ($is_stale) : ?>
                                    <span class="pas-cf-stale-badge" title="بیش از <?php echo esc_attr(self::STALE_DAYS); ?> روز از آخرین جلسه گذشته">⚠️ راکد</span>
                                <?php endif; ?>
                            </td>
                            <?php if ($can_view_all) : ?>
                                <td><?php echo $therapist ? esc_html($therapist->display_name) : '— تخصیص‌نیافته'; ?></td>
                            <?php endif; ?>
                            <td><?php echo $linked_user ? '✅ متصل' : '— بدون حساب'; ?></td>
                            <td><?php echo esc_html(Pas_CF_Jalali::to_jalali_datetime_string($case->updated_at)); ?></td>
                            <td><a href="<?php echo esc_url(admin_url('admin.php?page=pas-case-file-new&case_id=' . $case->id)); ?>" class="button button-small">مشاهده / ویرایش</a></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    // ── فرم پرونده (جدید یا ویرایش) ─────────────────────────────────────

    public function render_edit_page(): void
    {
        Pas_CF_Security::require_access();

        $case_id = isset($_GET['case_id']) ? (int) $_GET['case_id'] : 0;
        $case    = $case_id ? Pas_CF_Case_File::get($case_id) : null;

        if ($case_id && !$case) {
            wp_die('پرونده یافت نشد.', 'یافت نشد', ['response' => 404]);
        }

        if ($case) {
            // این چک همان‌جایی است که قبلاً نبود: قبلاً هر کاربر با
            // pas_manage_case_files، با همین لینک مستقیم، پرونده‌ی هر
            // درمانگر دیگری را هم می‌دید.
            Pas_CF_Security::require_case_access($case);
            Pas_CF_Audit_Log::record($case_id, 'view');
        }

        $can_view_all = Pas_CF_Security::current_user_can_view_all();

        $linked_user   = $case ? Pas_CF_Case_File::find_linked_wp_user($case->phone) : null;
        $bookings      = $case ? Pas_CF_Case_File::get_linked_bookings($case->phone) : [];
        $quiz_results  = $case ? Pas_CF_Case_File::get_linked_quiz_results($case->phone) : [];
        $notes         = $case ? Pas_CF_Case_File::get_notes($case->id) : [];
        $deleted_notes = $case ? Pas_CF_Case_File::get_deleted_notes($case->id) : [];
        $audit         = $case ? Pas_CF_Audit_Log::for_case($case->id) : [];
        $partner       = $case ? Pas_CF_Case_File::get_partner($case) : null;
        $assessment_trend = $case ? Pas_CF_Case_File::get_assessment_trend($case->phone) : [];
        ?>
        <div class="wrap pas-cf-wrap">
            <h1>
                <?php echo $case ? 'پرونده: ' . esc_html($case->full_name ?: $case->phone) : 'پرونده‌ی جدید'; ?>
                <?php if ($case) : ?>
                    <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=pas_cf_print_case&case_id=' . $case->id), 'pas_cf_print_case')); ?>"
                       target="_blank" rel="noopener" class="page-title-action">🖨 پرینت / ذخیره PDF</a>
                <?php endif; ?>
            </h1>
            <?php if ($case) : ?>
                <p class="pas-cf-session-stats">
                    <?php if (empty($notes)) : ?>
                        هنوز جلسه‌ای ثبت نشده.
                    <?php else : ?>
                        <?php echo esc_html(count($notes)); ?> جلسه ثبت‌شده — آخرین جلسه:
                        <?php echo esc_html(Pas_CF_Jalali::to_jalali_string($notes[0]->session_date)); ?>
                    <?php endif; ?>
                </p>
            <?php endif; ?>

            <div class="pas-cf-columns">
                <div class="pas-cf-main">
                    <h2>مشخصات</h2>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                        <?php wp_nonce_field('pas_cf_save_case'); ?>
                        <input type="hidden" name="action" value="pas_cf_save_case">
                        <input type="hidden" name="case_id" value="<?php echo esc_attr($case_id); ?>">

                        <table class="form-table">
                            <tr>
                                <th><label for="pas_cf_full_name">نام و نام خانوادگی</label></th>
                                <td><input type="text" name="full_name" id="pas_cf_full_name" class="regular-text" value="<?php echo esc_attr($case?->full_name ?? ''); ?>"></td>
                            </tr>
                            <tr>
                                <th><label for="pas_cf_phone">موبایل *</label></th>
                                <td>
                                    <input type="text" name="phone" id="pas_cf_phone" dir="ltr" class="regular-text" required
                                           value="<?php echo esc_attr($case?->phone ?? ''); ?>" <?php echo $case ? 'readonly' : ''; ?>>
                                    <p class="description">کلید اتصال به حساب OTP مراجع در پنل مراجعان — بعد از ثبت قابل تغییر نیست.</p>
                                </td>
                            </tr>
                            <tr>
                                <th><label>سن</label></th>
                                <td><input type="text" name="age" value="<?php echo esc_attr($case?->age ?? ''); ?>"></td>
                            </tr>
                            <tr>
                                <th><label>وضعیت تاهل</label></th>
                                <td><input type="text" name="marital_status" class="regular-text" value="<?php echo esc_attr($case?->marital_status ?? ''); ?>"></td>
                            </tr>
                            <tr>
                                <th><label>تحصیلات</label></th>
                                <td><input type="text" name="education" class="regular-text" value="<?php echo esc_attr($case?->education ?? ''); ?>"></td>
                            </tr>
                            <tr>
                                <th><label>شغل</label></th>
                                <td><input type="text" name="occupation" class="regular-text" value="<?php echo esc_attr($case?->occupation ?? ''); ?>"></td>
                            </tr>
                            <tr>
                                <th><label>محل سکونت</label></th>
                                <td><input type="text" name="residence" class="regular-text" value="<?php echo esc_attr($case?->residence ?? ''); ?>" placeholder="مثلاً: تهران، منطقه ۵"></td>
                            </tr>
                            <tr>
                                <th><label>نوع ازدواج</label></th>
                                <td><input type="text" name="marriage_type" class="regular-text" value="<?php echo esc_attr($case?->marriage_type ?? ''); ?>" placeholder="مثلاً: فامیلی، آشنایی، ترتیبی"></td>
                            </tr>
                            <tr>
                                <th><label>تعداد فرزندان</label></th>
                                <td><input type="text" name="children_count" value="<?php echo esc_attr($case?->children_count ?? ''); ?>"></td>
                            </tr>
                            <tr>
                                <th><label>موضوع کاری</label></th>
                                <td><input type="text" name="topic" class="regular-text" value="<?php echo esc_attr($case?->topic ?? ''); ?>" placeholder="مثلاً: اعتماد و خیانت، دلبستگی"></td>
                            </tr>
                            <tr>
                                <th><label>موضوعات اصلی تعارض</label></th>
                                <td><textarea name="main_conflict_topics" rows="2" class="large-text"><?php echo esc_textarea($case?->main_conflict_topics ?? ''); ?></textarea></td>
                            </tr>
                            <tr>
                                <th><label>درمانگر مسئول</label></th>
                                <td>
                                    <?php
                                    $assigned_id = (int) ($case?->assigned_therapist ?? ($can_view_all ? 0 : get_current_user_id()));
                                    if ($can_view_all) :
                                        $therapists = get_users(['capability' => [PAS_CF_CAP], 'orderby' => 'display_name']);
                                        ?>
                                        <select name="assigned_therapist">
                                            <option value="0" <?php selected($assigned_id, 0); ?>>— تخصیص‌نیافته —</option>
                                            <?php foreach ($therapists as $t) : ?>
                                                <option value="<?php echo esc_attr($t->ID); ?>" <?php selected($assigned_id, $t->ID); ?>><?php echo esc_html($t->display_name); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <p class="description">به‌عنوان سرپرست می‌توانید این پرونده را به هر درمانگری تخصیص دهید.</p>
                                    <?php else : ?>
                                        <input type="hidden" name="assigned_therapist" value="<?php echo esc_attr(get_current_user_id()); ?>">
                                        <p class="description">این پرونده به شما تخصیص دارد. تغییر درمانگر مسئول فقط توسط سرپرست ممکن است.</p>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <th><label>سابقه‌ی درمان قبلی</label></th>
                                <td><textarea name="prior_therapy" rows="3" class="large-text"><?php echo esc_textarea($case?->prior_therapy ?? ''); ?></textarea></td>
                            </tr>
                            <tr>
                                <th><label>سابقه‌ی ازدواج ناموفق</label></th>
                                <td><textarea name="failed_marriage_history" rows="2" class="large-text"><?php echo esc_textarea($case?->failed_marriage_history ?? ''); ?></textarea></td>
                            </tr>
                            <tr>
                                <th><label>سابقه‌ی بیماری خاص یا مصرف دارو</label></th>
                                <td>
                                    <textarea name="medical_history" rows="3" class="large-text"<?php echo Pas_CF_Crypto::is_configured() ? '' : ' disabled'; ?>><?php echo esc_textarea($case?->medical_history ?? ''); ?></textarea>
                                    <p class="description">این فیلد رمزنگاری‌شده ذخیره می‌شود.</p>
                                </td>
                            </tr>
                            <tr>
                                <th><label>سابقه‌ی مشاوره یا روان‌پزشک</label></th>
                                <td>
                                    <textarea name="counseling_history" rows="3" class="large-text"<?php echo Pas_CF_Crypto::is_configured() ? '' : ' disabled'; ?>><?php echo esc_textarea($case?->counseling_history ?? ''); ?></textarea>
                                    <p class="description">این فیلد رمزنگاری‌شده ذخیره می‌شود.</p>
                                </td>
                            </tr>
                            <tr>
                                <th><label>سابقه‌ی خودکشی یا خودآسیب‌رسانی</label></th>
                                <td>
                                    <textarea name="self_harm_history" rows="3" class="large-text"<?php echo Pas_CF_Crypto::is_configured() ? '' : ' disabled'; ?>><?php echo esc_textarea($case?->self_harm_history ?? ''); ?></textarea>
                                    <p class="description">این فیلد رمزنگاری‌شده ذخیره می‌شود — فقط برای برنامه‌ریزی بالینی مراقبت، نه یک تشخیص.</p>
                                </td>
                            </tr>
                            <tr>
                                <th><label>سوء مصرف مواد</label></th>
                                <td>
                                    <textarea name="substance_use_history" rows="2" class="large-text"<?php echo Pas_CF_Crypto::is_configured() ? '' : ' disabled'; ?> placeholder="نوع ماده، الگوی مصرف، سابقه‌ی ترک/درمان (در صورت وجود)"><?php echo esc_textarea($case?->substance_use_history ?? ''); ?></textarea>
                                    <p class="description">این فیلد رمزنگاری‌شده ذخیره می‌شود.</p>
                                </td>
                            </tr>
                            <tr>
                                <th><label>وضعیت پرونده</label></th>
                                <td>
                                    <select name="status">
                                        <?php foreach (['فعال', 'موقتاً متوقف', 'پایان‌یافته'] as $s) : ?>
                                            <option value="<?php echo esc_attr($s); ?>" <?php selected($case?->status ?? 'فعال', $s); ?>><?php echo esc_html($s); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                            </tr>
                        </table>

                        <h2>علائم دو هفته اخیر</h2>
                        <table class="form-table">
                            <tr>
                                <th><label>خلق و افسردگی</label></th>
                                <td>
                                    <textarea name="symptom_mood_depression" rows="2" class="large-text"<?php echo Pas_CF_Crypto::is_configured() ? '' : ' disabled'; ?>><?php echo esc_textarea($case?->symptom_mood_depression ?? ''); ?></textarea>
                                    <p class="description">رمزنگاری‌شده ذخیره می‌شود.</p>
                                </td>
                            </tr>
                            <tr>
                                <th><label>اضطراب</label></th>
                                <td>
                                    <textarea name="symptom_anxiety" rows="2" class="large-text"<?php echo Pas_CF_Crypto::is_configured() ? '' : ' disabled'; ?>><?php echo esc_textarea($case?->symptom_anxiety ?? ''); ?></textarea>
                                    <p class="description">رمزنگاری‌شده ذخیره می‌شود.</p>
                                </td>
                            </tr>
                        </table>

                        <h2>سوالات</h2>
                        <table class="form-table">
                            <tr>
                                <th><label>۱. علت اصلی مراجعه به مشاوره</label></th>
                                <td><textarea name="q_main_reason" rows="2" class="large-text"><?php echo esc_textarea($case?->q_main_reason ?? ''); ?></textarea></td>
                            </tr>
                            <tr>
                                <th><label>۲. نکات مثبت و منفی یکدیگر</label></th>
                                <td><textarea name="q_positive_negative_traits" rows="2" class="large-text"><?php echo esc_textarea($case?->q_positive_negative_traits ?? ''); ?></textarea></td>
                            </tr>
                            <tr>
                                <th><label>۳. چگونه تعارضات را حل می‌کنید؟</label></th>
                                <td><textarea name="q_conflict_resolution" rows="2" class="large-text"><?php echo esc_textarea($case?->q_conflict_resolution ?? ''); ?></textarea></td>
                            </tr>
                            <tr>
                                <th><label>۴. چه چیزی باعث شده همچنان در رابطه بمانید</label></th>
                                <td><textarea name="q_reason_staying" rows="2" class="large-text"><?php echo esc_textarea($case?->q_reason_staying ?? ''); ?></textarea></td>
                            </tr>
                            <tr>
                                <th><label>۵. ارزیابی کوتاه طرحواره و سبک دلبستگی</label></th>
                                <td>
                                    <textarea name="q_schema_attachment" rows="2" class="large-text"<?php echo Pas_CF_Crypto::is_configured() ? '' : ' disabled'; ?>><?php echo esc_textarea($case?->q_schema_attachment ?? ''); ?></textarea>
                                    <p class="description">رمزنگاری‌شده ذخیره می‌شود.</p>
                                </td>
                            </tr>
                            <tr>
                                <th><label>۶. اهداف مراجعه</label></th>
                                <td><textarea name="q_therapy_goals" rows="2" class="large-text"><?php echo esc_textarea($case?->q_therapy_goals ?? ''); ?></textarea></td>
                            </tr>
                        </table>

                        <h2>ارزیابی رابطه (آزمون رضایت زناشویی)</h2>
                        <table class="form-table">
                            <tr>
                                <th><label>نمره — قبل از درمان</label></th>
                                <td><input type="text" name="relationship_satisfaction_pre" value="<?php echo esc_attr($case?->relationship_satisfaction_pre ?? ''); ?>"></td>
                            </tr>
                            <tr>
                                <th><label>نمره — پس از درمان</label></th>
                                <td><input type="text" name="relationship_satisfaction_post" value="<?php echo esc_attr($case?->relationship_satisfaction_post ?? ''); ?>"></td>
                            </tr>
                            <tr>
                                <td colspan="2">
                                    <p class="description">این دو فیلد برای ثبت دستی نمره است. اگر افزونه‌ی «آزمون‌های روان‌سنجی» فعال باشد و مراجع آزمون CSI-16 را حداقل یک‌بار تکمیل کرده باشد، نتیجه‌ی خودکار آن در ستون کناری («ارزیابی رابطه — آزمون») هم نمایش داده می‌شود.</p>
                                </td>
                            </tr>
                        </table>

                        <h2>اطلاعات همسر</h2>
                        <table class="form-table">
                            <tr>
                                <th><label>نام و نام خانوادگی همسر</label></th>
                                <td><input type="text" name="spouse_full_name" class="regular-text" value="<?php echo esc_attr($case?->spouse_full_name ?? ''); ?>"></td>
                            </tr>
                            <tr>
                                <th><label>سن همسر</label></th>
                                <td><input type="text" name="spouse_age" value="<?php echo esc_attr($case?->spouse_age ?? ''); ?>"></td>
                            </tr>
                            <tr>
                                <th><label>تحصیلات همسر</label></th>
                                <td><input type="text" name="spouse_education" class="regular-text" value="<?php echo esc_attr($case?->spouse_education ?? ''); ?>"></td>
                            </tr>
                            <tr>
                                <th><label>شغل همسر</label></th>
                                <td><input type="text" name="spouse_occupation" class="regular-text" value="<?php echo esc_attr($case?->spouse_occupation ?? ''); ?>"></td>
                            </tr>
                            <tr>
                                <th><label>سابقه‌ی ازدواج ناموفق همسر</label></th>
                                <td><textarea name="spouse_failed_marriage_history" rows="2" class="large-text"><?php echo esc_textarea($case?->spouse_failed_marriage_history ?? ''); ?></textarea></td>
                            </tr>
                            <tr>
                                <th><label>سابقه‌ی بیماری/دارو همسر</label></th>
                                <td>
                                    <textarea name="spouse_medical_history" rows="2" class="large-text"<?php echo Pas_CF_Crypto::is_configured() ? '' : ' disabled'; ?>><?php echo esc_textarea($case?->spouse_medical_history ?? ''); ?></textarea>
                                    <p class="description">رمزنگاری‌شده ذخیره می‌شود.</p>
                                </td>
                            </tr>
                            <tr>
                                <th><label>سابقه‌ی مشاوره/روان‌پزشک همسر</label></th>
                                <td>
                                    <textarea name="spouse_counseling_history" rows="2" class="large-text"<?php echo Pas_CF_Crypto::is_configured() ? '' : ' disabled'; ?>><?php echo esc_textarea($case?->spouse_counseling_history ?? ''); ?></textarea>
                                    <p class="description">رمزنگاری‌شده ذخیره می‌شود.</p>
                                </td>
                            </tr>
                            <tr>
                                <th><label>سابقه‌ی خودکشی/خودآسیب‌رسانی همسر</label></th>
                                <td>
                                    <textarea name="spouse_self_harm_history" rows="2" class="large-text"<?php echo Pas_CF_Crypto::is_configured() ? '' : ' disabled'; ?>><?php echo esc_textarea($case?->spouse_self_harm_history ?? ''); ?></textarea>
                                    <p class="description">رمزنگاری‌شده ذخیره می‌شود.</p>
                                </td>
                            </tr>
                            <tr>
                                <th><label>سوء مصرف مواد همسر</label></th>
                                <td>
                                    <textarea name="spouse_substance_use_history" rows="2" class="large-text"<?php echo Pas_CF_Crypto::is_configured() ? '' : ' disabled'; ?>><?php echo esc_textarea($case?->spouse_substance_use_history ?? ''); ?></textarea>
                                    <p class="description">رمزنگاری‌شده ذخیره می‌شود.</p>
                                </td>
                            </tr>
                            <tr>
                                <th><label>موبایل همسر <span class="description">(اختیاری)</span></label></th>
                                <td><input type="text" name="spouse_phone" dir="ltr" class="regular-text" value="<?php echo esc_attr($case?->spouse_phone ?? ''); ?>"></td>
                            </tr>
                        </table>

                        <h2>یادآور جلسه</h2>
                        <table class="form-table">
                            <tr>
                                <th><label>رضایت مراجع برای یادآور</label></th>
                                <td>
                                    <label><input type="checkbox" name="reminder_consent" value="1" <?php checked(!empty($case?->reminder_consent)); ?>> مراجع با دریافت یادآور جلسه موافق است</label>
                                    <p class="description">بدون این تیک، دکمه‌ی ارسال یادآور کار نمی‌کند. رضایت هر زمان قابل لغو است و لغو آن فوراً اعمال می‌شود.</p>
                                </td>
                            </tr>
                            <tr>
                                <th><label>کانال یادآور</label></th>
                                <td>
                                    <select name="reminder_channel">
                                        <?php foreach (Pas_CF_Case_File::REMINDER_CHANNELS as $channel_key => $channel_label) : ?>
                                            <option value="<?php echo esc_attr($channel_key); ?>" <?php selected($case?->reminder_channel ?? 'sms', $channel_key); ?>><?php echo esc_html($channel_label); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <p class="description">پیامک از طریق قرارداد مشترک <code>pas_send_sms</code> ارسال می‌شود (نیازمند الگوی تأییدشده در پل پیامک). ایمیل به آدرس حساب متصل مراجع می‌رود.</p>
                                </td>
                            </tr>
                        </table>

                        <h2>لینک پارتنر / همسر (اگر پرونده‌ی جداگانه دارد)</h2>
                        <table class="form-table">
                            <tr>
                                <th><label>موبایل پرونده‌ی پارتنر</label></th>
                                <td>
                                    <input type="text" name="partner_phone" dir="ltr" class="regular-text"
                                           value="<?php echo esc_attr($partner ? $partner->phone : ''); ?>"
                                           placeholder="مثلاً 09121234567">
                                    <p class="description">اگر همسر/پارتنر این مراجع خودش یک پرونده‌ی جداگانه در همین سیستم دارد، شماره موبایل پرونده‌ی او را اینجا بزن — دو پرونده به‌هم لینک می‌شوند و درمانگر در هر دو پرونده متوجه رابطه‌شان می‌شود. برای حذف لینک، این فیلد را خالی بگذار و ذخیره کن.</p>
                                </td>
                            </tr>
                        </table>

                        <?php submit_button($case ? 'ذخیره‌ی تغییرات' : 'ثبت پرونده‌ی جدید'); ?>
                    </form>

                    <?php if ($case) : ?>
                        <?php $this->render_intake_card($case); ?>
                        <hr>
                        <h2>خلاصه‌ی جلسات (رمزنگاری‌شده)</h2>

                        <?php if (!Pas_CF_Crypto::is_configured()) : ?>
                            <p style="color:#b32d2e;">کلید رمزنگاری تنظیم نشده — ثبت یادداشت جدید غیرفعال است (به هشدار بالای صفحه نگاه کنید).</p>
                        <?php else : ?>
                            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                                <?php wp_nonce_field('pas_cf_add_note'); ?>
                                <input type="hidden" name="action" value="pas_cf_add_note">
                                <input type="hidden" name="case_id" value="<?php echo esc_attr($case->id); ?>">
                                <p>
                                    <label>تاریخ جلسه (شمسی)
                                        <input type="text" name="session_date_jalali" dir="ltr" class="pas-cf-jalali-input"
                                               value="<?php echo esc_attr(Pas_CF_Jalali::to_jalali_string(current_time('Y-m-d'))); ?>"
                                               placeholder="۱۴۰۳/۰۵/۱۲" required>
                                    </label>
                                    <span class="pas-cf-future-date-warning" hidden>⚠️ این تاریخ در آینده است.</span>
                                    <label>شماره‌ی جلسه
                                        <input type="text" name="session_number" dir="ltr" inputmode="numeric" maxlength="3" size="4"
                                               value="<?php echo esc_attr((string) (count($notes) + 1)); ?>"
                                               placeholder="مثلاً ۳">
                                    </label>
                                </p>
                                <p>
                                    <label>مدت جلسه (دقیقه)
                                        <input type="text" name="duration_minutes" dir="ltr" inputmode="numeric" maxlength="3" size="4" placeholder="۵۰">
                                    </label>
                                    <label>وضعیت
                                        <select name="session_status">
                                            <?php foreach (Pas_CF_Case_File::session_statuses() as $status_key => $status_label) : ?>
                                                <option value="<?php echo esc_attr($status_key); ?>"<?php echo $status_key === 'done' ? ' selected' : ''; ?>><?php echo esc_html($status_label); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </label>
                                    <label>جلسه‌ی بعد (شمسی، اختیاری)
                                        <input type="text" name="next_session_date_jalali" dir="ltr" class="pas-cf-jalali-input" placeholder="۱۴۰۳/۰۵/۱۹">
                                    </label>
                                    <label><input type="checkbox" name="is_joint" value="1"> جلسه‌ی مشترک (حضور هر دو نفر)</label>
                                </p>
                                <textarea name="content" rows="4" class="large-text" placeholder="خلاصه‌ی این جلسه…" aria-label="خلاصه‌ی این جلسه…" required></textarea>
                                <?php submit_button('ثبت خلاصه‌ی جلسه', 'secondary'); ?>
                            </form>
                        <?php endif; ?>

                        <div class="pas-cf-sessions">
                            <?php if (empty($notes)) : ?>
                                <p class="description">هنوز یادداشتی ثبت نشده.</p>
                            <?php endif; ?>
                            <?php
                            $recent_notes = array_slice($notes, 0, 20);
                            $older_notes  = array_slice($notes, 20);
                            foreach ($recent_notes as $note) {
                                $this->render_session_card($note, $case->id);
                            }
                            ?>
                        </div>

                        <?php if (!empty($older_notes)) : ?>
                            <details class="pas-cf-older-sessions">
                                <summary>نمایش <?php echo esc_html(count($older_notes)); ?> جلسه‌ی قدیمی‌تر</summary>
                                <div class="pas-cf-sessions">
                                    <?php foreach ($older_notes as $note) {
                                        $this->render_session_card($note, $case->id);
                                    } ?>
                                </div>
                            </details>
                        <?php endif; ?>

                        <?php if (!empty($deleted_notes) && Pas_CF_Crypto::is_configured()) : ?>
                            <details class="pas-cf-deleted-notes">
                                <summary>🗑 یادداشت‌های حذف‌شده (<?php echo esc_html(count($deleted_notes)); ?>)</summary>
                                <div class="pas-cf-sessions">
                                    <?php foreach ($deleted_notes as $d) :
                                        $deleter = get_userdata($d->changed_by);
                                        ?>
                                        <details class="pas-cf-session pas-cf-session-deleted">
                                            <summary>
                                                <?php if ((int) $d->session_number > 0) : ?>
                                                    <span class="pas-cf-session-number">جلسه‌ی <?php echo esc_html((string) (int) $d->session_number); ?></span>
                                                <?php endif; ?>
                                                <span class="pas-cf-session-date"><?php echo esc_html(Pas_CF_Jalali::to_jalali_string($d->session_date)); ?></span>
                                                <span class="pas-cf-session-author">حذف‌شده توسط <?php echo esc_html($deleter ? $deleter->display_name : '—'); ?> — <?php echo esc_html(Pas_CF_Jalali::to_jalali_datetime_string($d->changed_at)); ?></span>
                                            </summary>
                                            <p><?php echo nl2br(esc_html($d->content)); ?></p>
                                            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"
                                                  onsubmit="return confirm('این یادداشت به‌عنوان یک یادداشت جدید در پرونده بازگردانده شود؟');">
                                                <?php wp_nonce_field('pas_cf_restore_note'); ?>
                                                <input type="hidden" name="action" value="pas_cf_restore_note">
                                                <input type="hidden" name="case_id" value="<?php echo esc_attr($case->id); ?>">
                                                <input type="hidden" name="history_id" value="<?php echo esc_attr($d->id); ?>">
                                                <button type="submit" class="button button-small">بازگردانی این یادداشت</button>
                                            </form>
                                        </details>
                                    <?php endforeach; ?>
                                </div>
                            </details>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>

                <?php if ($case) : ?>
                <div class="pas-cf-sidebar">
                    <?php if ($partner) : ?>
                        <div class="pas-cf-box pas-cf-partner-banner" style="border-right:4px solid #b5654a;">
                            <h3>🔗 پارتنر / همسر تحت درمان</h3>
                            <p>این مراجع با <strong><?php echo esc_html($partner->full_name ?: $partner->phone); ?></strong> به‌عنوان پارتنر/همسر لینک شده — هر دو در یک رابطه‌اند و ممکن است جلسه‌ی مشترک داشته باشند.</p>
                            <a href="<?php echo esc_url(admin_url('admin.php?page=pas-case-file-new&case_id=' . $partner->id)); ?>" class="button button-small">مشاهده‌ی پرونده‌ی پارتنر</a>
                        </div>
                    <?php endif; ?>
                    <?php $couple = Pas_CF_Case_File::get_couple_sessions($case); ?>
                    <?php if (!empty($couple['partner'])) : ?>
                        <div class="pas-cf-box">
                            <h3>نمای جلسه‌های زوجی</h3>
                            <?php if (empty($couple['can_view_partner'])) : ?>
                                <p class="description">این پرونده پارتنر/همسر لینک‌شده دارد، ولی آن پرونده به شما تخصیص داده نشده است. برای حفظ محرمانگی، فقط جلسه‌های همین پرونده نمایش داده می‌شود.</p>
                            <?php endif; ?>
                            <?php if (empty($couple['sessions'])) : ?>
                                <p class="description">هنوز جلسه‌ای ثبت نشده.</p>
                            <?php else : ?>
                                <table class="widefat striped pas-cf-couple-sessions">
                                    <thead><tr><th>تاریخ</th><th>جلسه</th><th>طرف</th><th>وضعیت</th><th></th></tr></thead>
                                    <tbody>
                                        <?php foreach ($couple['sessions'] as $couple_session) :
                                            $couple_status = Pas_CF_Case_File::session_statuses()[$couple_session['status']] ?? $couple_session['status'];
                                            ?>
                                            <tr<?php echo $couple_session['is_joint'] ? ' class="pas-cf-joint-row"' : ''; ?>>
                                                <td><?php echo esc_html(Pas_CF_Jalali::to_jalali_string($couple_session['date'])); ?></td>
                                                <td><?php echo $couple_session['number'] > 0 ? esc_html((string) $couple_session['number']) : '—'; ?><?php echo $couple_session['duration'] > 0 ? ' · ' . esc_html((string) $couple_session['duration']) . ' دقیقه' : ''; ?></td>
                                                <td><?php echo $couple_session['side'] === 'self' ? 'همین مراجع' : 'پارتنر'; ?></td>
                                                <td><?php echo esc_html((string) $couple_status); ?></td>
                                                <td><?php echo $couple_session['is_joint'] ? '🤝 مشترک' : ''; ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                                <p class="description">فقط زمان‌بندی و وضعیت جلسه‌ها؛ متن یادداشت‌ها همچنان داخل هر پرونده و رمزنگاری‌شده می‌ماند.</p>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <?php
                    $next_reminder    = Pas_CF_Case_File::get_next_session($case);
                    $reminder_blocker = Pas_CF_Case_File::reminder_blocker($case, $next_reminder);
                    $reminder_notices = [
                        'sent'              => 'یادآور ارسال شد.',
                        'no_consent'        => 'رضایت مراجع برای دریافت یادآور ثبت نشده است.',
                        'no_session'        => 'جلسه‌ی بعدی ثبت نشده است؛ ابتدا تاریخ جلسه‌ی بعد را در یادداشت جلسه ثبت کنید.',
                        'no_channel'        => 'کانال یادآور روی «بدون یادآور» است.',
                        'cooldown'          => 'کمتر از ۱۲ ساعت از ارسال قبلی گذشته است.',
                        'sms_not_configured'=> 'الگوی پیامک یادآور پیکربندی نشده (پل پیامک یا شناسه‌ی الگو).',
                        'failed'            => 'ارسال یادآور ناموفق بود.',
                    ];
                    $reminder_result = isset($_GET['reminder']) ? sanitize_key(wp_unslash($_GET['reminder'])) : '';
                    ?>
                    <div class="pas-cf-box">
                        <h3>یادآور جلسه</h3>
                        <?php if ($reminder_result !== '' && isset($reminder_notices[$reminder_result])) : ?>
                            <div class="notice <?php echo $reminder_result === 'sent' ? 'notice-success' : 'notice-warning'; ?> inline"><p><?php echo esc_html($reminder_notices[$reminder_result]); ?></p></div>
                        <?php endif; ?>
                        <?php if ($next_reminder) : ?>
                            <p>جلسه‌ی بعدی: <strong><?php echo esc_html(Pas_CF_Jalali::to_jalali_string((string) $next_reminder->next_session_date)); ?></strong>
                                <?php if ((int) $next_reminder->session_number > 0) : ?>(جلسه‌ی <?php echo esc_html((string) (int) $next_reminder->session_number); ?>)<?php endif; ?>
                            </p>
                        <?php else : ?>
                            <p class="description">تاریخ جلسه‌ی بعدی ثبت نشده.</p>
                        <?php endif; ?>
                        <p class="description">
                            رضایت: <?php echo !empty($case->reminder_consent) ? '✅ ثبت شده' : '⛔ ثبت نشده'; ?>
                            — کانال: <?php echo esc_html(Pas_CF_Case_File::REMINDER_CHANNELS[(string) ($case->reminder_channel ?? 'sms')] ?? '—'); ?>
                            <?php if (!empty($case->reminder_last_sent_at)) : ?>
                                — آخرین ارسال: <?php echo esc_html(Pas_CF_Jalali::to_jalali_datetime_string((string) $case->reminder_last_sent_at)); ?>
                            <?php endif; ?>
                        </p>
                        <?php if ($reminder_blocker === 'ok') : ?>
                            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"
                                  onsubmit="return confirm('يادآور جلسه براي اين مراجع ارسال شود؟');">
                                <?php wp_nonce_field('pas_cf_send_reminder'); ?>
                                <input type="hidden" name="action" value="pas_cf_send_reminder">
                                <input type="hidden" name="case_id" value="<?php echo esc_attr((string) $case->id); ?>">
                                <?php submit_button('ارسال يادآور جلسه', 'secondary', 'submit', false); ?>
                            </form>
                        <?php else : ?>
                            <p class="description"><?php echo esc_html($reminder_notices[$reminder_blocker] ?? 'ارسال يادآور در حال حاضر ممکن نيست.'); ?></p>
                        <?php endif; ?>
                    </div>

                    <?php $timeline = Pas_CF_Timeline::for_case($case, 20); ?>
                    <?php if (!empty($timeline)) : ?>
                        <div class="pas-cf-box">
                            <h3>تایم‌لاین پرونده</h3>
                            <ul class="pas-cf-timeline">
                                <?php foreach ($timeline as $event) :
                                    $event_user = (int) $event['user_id'] > 0 ? get_userdata((int) $event['user_id']) : null;
                                    ?>
                                    <li class="pas-cf-timeline-item">
                                        <span class="pas-cf-timeline-date"><?php echo esc_html(Pas_CF_Jalali::to_jalali_datetime_string((string) $event['at'])); ?></span>
                                        <strong class="pas-cf-timeline-title"><?php echo esc_html((string) $event['title']); ?></strong>
                                        <?php if ((string) ($event['detail'] ?? '') !== '') : ?>
                                            <span class="pas-cf-timeline-detail"><?php echo esc_html((string) $event['detail']); ?></span>
                                        <?php endif; ?>
                                        <?php if ($event_user) : ?>
                                            <span class="pas-cf-timeline-user"><?php echo esc_html($event_user->display_name); ?></span>
                                        <?php endif; ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <div class="pas-cf-box">
                        <h3>اتصال به افزونه‌ی ورود</h3>
                        <?php if ($linked_user) : ?>
                            <p>✅ حساب OTP متصل است.<br>
                               تاریخ ثبت‌نام: <?php echo esc_html(Pas_CF_Jalali::to_jalali_datetime_string($linked_user->user_registered)); ?></p>
                        <?php else : ?>
                            <p>این شماره هنوز از طریق OTP وارد پنل مراجعان نشده (یا آن افزونه فعال نیست).</p>
                        <?php endif; ?>
                    </div>

                    <div class="pas-cf-box">
                        <h3>نوبت‌های پنل مراجعان</h3>
                        <?php if (empty($bookings)) : ?>
                            <p class="description">نوبتی یافت نشد.</p>
                        <?php else : ?>
                            <ul>
                                <?php foreach ($bookings as $b) : ?>
                                    <li><?php echo esc_html(Pas_CF_Jalali::to_jalali_string($b->booking_date) . ' ' . substr($b->booking_time, 0, 5)); ?> — <?php echo esc_html($b->service_name); ?> (<?php echo esc_html($b->status); ?>)</li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>

                    <?php if (!empty($assessment_trend)) : ?>
                        <div class="pas-cf-box">
                            <h3>روند ارزیابی‌ها</h3>
                            <ul class="pas-cf-assessments">
                                <?php foreach ($assessment_trend as $item) :
                                    $delta = $item['delta'];
                                    ?>
                                    <li class="pas-cf-assessment-item">
                                        <strong><?php echo esc_html((string) $item['label']); ?></strong>
                                        <?php if ($item['first'] === null) : ?>
                                            <span class="description"><?php echo esc_html((string) $item['count']); ?> نوبت ثبت شده (نمره‌ی عددی برای مقایسه ندارد)</span>
                                        <?php elseif ($delta === null) : ?>
                                            <span>نمره: <?php echo esc_html((string) $item['first']); ?>
                                                <?php if ($item['first_at'] !== '') : ?>(<?php echo esc_html(Pas_CF_Jalali::to_jalali_datetime_string((string) $item['first_at'])); ?>)<?php endif; ?>
                                            </span>
                                            <span class="description">برای مقایسه، یک نوبت دیگر لازم است.</span>
                                        <?php else : ?>
                                            <span>
                                                از <strong><?php echo esc_html((string) $item['first']); ?></strong>
                                                به <strong><?php echo esc_html((string) $item['last']); ?></strong>
                                                (<?php echo $delta > 0 ? '+' : ''; ?><?php echo esc_html((string) $delta); ?>)
                                            </span>
                                            <span class="description"><?php echo esc_html((string) $item['count']); ?> نوبت — آخرین: <?php echo esc_html(Pas_CF_Jalali::to_jalali_datetime_string((string) $item['last_at'])); ?></span>
                                        <?php endif; ?>
                                        <?php if ($item['url'] !== '') : ?>
                                            <a href="<?php echo esc_url((string) $item['url']); ?>" target="_blank" rel="noopener">مشاهده‌ی گزارش</a>
                                        <?php endif; ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                            <p class="description">نمره‌ها از افزونه‌ی آزمون‌ها و فقط از طریق قرارداد فیلتری خوانده می‌شوند؛ جدول‌های آن افزونه مستقیماً خوانده نمی‌شود.</p>
                        </div>
                    <?php endif; ?>

                    <?php Pas_CF_Alerts_Page::render_case_section((int) $case->id); ?>

                    <div class="pas-cf-box">
                        <h3>نتایج آزمون‌های روان‌سنجی</h3>
                        <?php if (empty($quiz_results)) : ?>
                            <p class="description">نتیجه‌ای متصل نیست.</p>
                        <?php else : ?>
                            <ul>
                                <?php foreach ($quiz_results as $r) : ?>
                                    <li>
                                        <?php if (is_array($r) && !empty($r['url'])) : ?>
                                            <a href="<?php echo esc_url($r['url']); ?>" target="_blank"><?php echo esc_html($r['label'] ?? 'مشاهده‌ی گزارش'); ?></a>
                                        <?php else : ?>
                                            <?php echo esc_html(is_array($r) ? ($r['label'] ?? '') : $r); ?>
                                        <?php endif; ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>

                    <div class="pas-cf-box">
                        <h3>تاریخچه‌ی دسترسی</h3>
                        <?php if (empty($audit)) : ?>
                            <p class="description">—</p>
                        <?php else : ?>
                            <ul class="pas-cf-audit">
                                <?php foreach (array_slice($audit, 0, 10) as $a) :
                                    $u = get_userdata($a->user_id);
                                    ?>
                                    <li><?php echo esc_html(Pas_CF_Jalali::to_jalali_datetime_string($a->created_at)); ?> — <?php echo esc_html($u ? $u->display_name : '—'); ?> — <?php echo esc_html($a->action); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    /**
     * یک کارت details/summary برای یک یادداشت جلسه — شامل فرم ویرایش، دکمه‌ی
     * حذف (با تایید مرورگری)، و تاریخچه‌ی ویرایش‌های قبلی (اگر باشد). هم در
     * لیست «اخیر» و هم در بخش تاکردنی «قدیمی‌تر» صدا زده می‌شود تا مارک‌آپ
     * تکرار نشود.
     */
    /**
     * فرم ارزیابی اولیه‌ی مراجع (ثبت‌شده در پنل مراجعان) داخل پرونده — فقط‌خواندنی.
     * داده از فیلتر `zdp_get_intake_for_phone` (افزونه‌ی client-portal) می‌آید؛
     * وابستگی مستقیم کلاس به کلاس وجود ندارد و اگر آن افزونه غیرفعال باشد یا مراجع
     * فرم را پر نکرده باشد، چیزی نمایش داده نمی‌شود. هر نمایش در لاگ دسترسی ثبت می‌شود.
     */
    private function render_intake_card(object $case): void
    {
        $phone = (string) ($case->phone ?? '');
        if ($phone === '') {
            return;
        }

        $intake = apply_filters('zdp_get_intake_for_phone', [], $phone);
        if (!is_array($intake) || empty($intake['data']) || !is_array($intake['data'])) {
            return;
        }

        $labels = [
            'full_name'   => 'نام و نام خانوادگی',
            'father_name' => 'نام پدر',
            'birth_date'  => 'تاریخ تولد',
            'age'         => 'سن',
            'gender'      => 'جنسیت',
            'marital'     => 'وضعیت تاهل',
            'children'    => 'تعداد فرزندان',
            'education'   => 'تحصیلات',
            'job'         => 'شغل',
            'phone'       => 'شماره تماس',
            'address'     => 'آدرس',
            'referral'    => 'نحوه‌ی آشنایی',
            'form_date'   => 'تاریخ فرم',
        ];

        $data     = $intake['data'];
        $file_no  = isset($intake['file_number']) ? trim((string) $intake['file_number']) : '';
        $updated  = isset($intake['updated_at']) ? (string) $intake['updated_at'] : '';

        Pas_CF_Audit_Log::record((int) $case->id, 'view_intake');
        ?>
        <details class="pas-cf-intake-card">
            <summary>📝 فرم ارزیابی مراجع (ثبت‌شده در پنل)<?php echo $file_no !== '' ? ' — شماره‌ی پرونده: ' . esc_html($file_no) : ''; ?></summary>
            <table class="widefat striped pas-cf-intake-table">
                <tbody>
                <?php foreach ($labels as $key => $label) :
                    if (!isset($data[$key]) || (string) $data[$key] === '') {
                        continue;
                    }
                    ?>
                    <tr>
                        <th scope="row"><?php echo esc_html($label); ?></th>
                        <td><?php echo nl2br(esc_html((string) $data[$key])); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php if ($updated !== '') : ?>
                <p class="description">آخرین به‌روزرسانی فرم: <?php echo esc_html(Pas_CF_Jalali::to_jalali_datetime_string($updated)); ?></p>
            <?php endif; ?>

            <?php
            $map_rows = class_exists('Pas_Core_Field_Mapper')
                ? Pas_CF_Case_File::intake_mapping_preview($case, $intake)
                : [];

            if (!empty($map_rows)) :
                $labels_by_target = [];
                $has_actionable   = false;

                foreach ($map_rows as $map_row) {
                    $labels_by_target[(string) $map_row['target']] = (string) $map_row['label'];
                    if (in_array($map_row['status'], ['fill', 'conflict'], true)) {
                        $has_actionable = true;
                    }
                }

                $map_done = isset($_GET['pas_map'])
                    && sanitize_key(wp_unslash($_GET['pas_map'])) === 'done';
                ?>
                <h4 class="pas-cf-map-title">انتقال اطلاعات فرم به پرونده</h4>
                <?php if ($map_done) : ?>
                    <div class="notice notice-success inline"><p>اطلاعات فرم به پرونده منتقل شد (<?php echo (int) ($_GET['updated'] ?? 0); ?> فیلد).</p></div>
                <?php endif; ?>
                <p class="description">فقط فیلدهای خالی پر می‌شوند. فیلدهای دارای اختلاف بدون تیک «بازنویسی» تغییر نمی‌کنند و مقدار قبلی هر فیلد در «منبع داده‌های ثبت‌شده» نگه داشته می‌شود.</p>
                <table class="widefat striped pas-cf-map-table">
                    <thead><tr><th>فیلد پرونده</th><th>مقدار در فرم</th><th>مقدار فعلی پرونده</th><th>وضعیت</th></tr></thead>
                    <tbody>
                    <?php foreach ($map_rows as $map_row) :
                        $is_clinical = ($map_row['sensitivity'] ?? '') === 'clinical';
                        $value_text  = $is_clinical
                            ? '•••••• (پس از انتقال، در فرم پرونده دیده می‌شود)'
                            : (string) $map_row['value'];
                        $current_text = $is_clinical
                            ? ((string) ($map_row['current'] ?? '') !== '' ? 'مقدار دارد' : 'خالی')
                            : (string) (
                                ($map_row['current_display'] ?? '') !== ''
                                    ? $map_row['current_display']
                                    : ((string) ($map_row['current'] ?? '') !== '' ? $map_row['current'] : '—')
                            );
                        $status = (string) $map_row['status'];
                        ?>
                        <tr>
                            <th scope="row"><?php echo esc_html($map_row['label']); ?></th>
                            <td><?php echo nl2br(esc_html($value_text)); ?></td>
                            <td><?php echo nl2br(esc_html($current_text)); ?></td>
                            <td>
                                <?php if ($status === 'fill') : ?>✅ پر می‌شود
                                <?php elseif ($status === 'same') : ?>➖ یکسان (رد می‌شود)
                                <?php elseif ($status === 'conflict') : ?>⚠️ اختلاف با مقدار فعلی
                                <?php else : ?>⛔ تاریخ نامعتبر (رد می‌شود)
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>

                <?php if ($has_actionable) : ?>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="pas-cf-map-form">
                        <?php wp_nonce_field('pas_cf_apply_intake_map'); ?>
                        <input type="hidden" name="action" value="pas_cf_apply_intake_map">
                        <input type="hidden" name="case_id" value="<?php echo esc_attr((string) $case->id); ?>">
                        <p><label><input type="checkbox" name="overwrite" value="1"> بازنویسی فیلدهای دارای اختلاف (مقدار قبلی در منبع داده‌ها ثبت می‌شود)</label></p>
                        <?php submit_button('انتقال اطلاعات فرم به پرونده', 'primary', 'submit', false); ?>
                    </form>
                <?php endif; ?>

                <?php $prov_rows = Pas_Core_Field_Mapper::provenance_map('case', (int) $case->id); ?>
                <?php if (!empty($prov_rows)) : ?>
                    <h4>منبع داده‌های ثبت‌شده</h4>
                    <table class="widefat striped pas-cf-prov-table">
                        <thead><tr><th>فیلد</th><th>منبع</th><th>زمان</th><th>توسط</th></tr></thead>
                        <tbody>
                        <?php foreach ($prov_rows as $prov_field => $prov) :
                            $prov_user = get_userdata((int) $prov->set_by);
                            ?>
                            <tr>
                                <td><?php echo esc_html($labels_by_target[$prov_field] ?? $prov_field); ?></td>
                                <td><?php echo esc_html($prov->source === 'intake' ? 'فرم ارزیابی مراجع' : (string) $prov->source); ?></td>
                                <td><?php echo esc_html(Pas_CF_Jalali::to_jalali_datetime_string((string) $prov->set_at)); ?></td>
                                <td><?php echo esc_html($prov_user ? $prov_user->display_name : '—'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            <?php endif; ?>
        </details>
        <?php
    }

    private function render_session_card(object $note, int $case_id): void
    {
        $author  = get_userdata($note->author_id);
        $jalali  = Pas_CF_Jalali::to_jalali_string($note->session_date);
        $history = Pas_CF_Case_File::get_note_history($note->id);
        ?>
        <details class="pas-cf-session">
            <summary>
                <?php if ((int) $note->session_number > 0) : ?>
                    <span class="pas-cf-session-number">جلسه‌ی <?php echo esc_html((string) (int) $note->session_number); ?></span>
                <?php endif; ?>
                <span class="pas-cf-session-date"><?php echo esc_html($jalali ?: $note->session_date); ?></span>
                <span class="pas-cf-session-author"><?php echo esc_html($author ? $author->display_name : '—'); ?></span>
                <?php $status_key = (string) ($note->status ?? 'done'); ?>
                <?php if ($status_key !== 'done') : ?>
                    <span class="pas-cf-session-status"><?php echo esc_html(Pas_CF_Case_File::session_statuses()[$status_key] ?? $status_key); ?></span>
                <?php endif; ?>
                <?php if ((int) ($note->duration_minutes ?? 0) > 0) : ?>
                    <span class="pas-cf-session-duration"><?php echo esc_html((string) (int) $note->duration_minutes); ?> دقیقه</span>
                <?php endif; ?>
                <?php if (!empty($note->next_session_date)) : ?>
                    <span class="pas-cf-session-next">جلسه‌ی بعد: <?php echo esc_html(Pas_CF_Jalali::to_jalali_string((string) $note->next_session_date)); ?></span>
                <?php endif; ?>
                <?php if (!empty($note->is_joint)) : ?>
                    <span class="pas-cf-session-joint">🤝 جلسه‌ی مشترک</span>
                <?php endif; ?>
            </summary>

            <?php if (Pas_CF_Crypto::is_configured()) : ?>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="pas-cf-session-edit">
                    <?php wp_nonce_field('pas_cf_edit_note'); ?>
                    <input type="hidden" name="action" value="pas_cf_edit_note">
                    <input type="hidden" name="case_id" value="<?php echo esc_attr($case_id); ?>">
                    <input type="hidden" name="note_id" value="<?php echo esc_attr($note->id); ?>">
                    <p>
                        <label>تاریخ جلسه (شمسی)
                            <input type="text" name="session_date_jalali" dir="ltr" class="pas-cf-jalali-input"
                                   value="<?php echo esc_attr($jalali); ?>"
                                   placeholder="۱۴۰۳/۰۵/۱۲" required>
                        </label>
                        <span class="pas-cf-future-date-warning" hidden>⚠️ این تاریخ در آینده است.</span>
                        <label>شماره‌ی جلسه
                            <input type="text" name="session_number" dir="ltr" inputmode="numeric" maxlength="3" size="4"
                                   value="<?php echo esc_attr((int) $note->session_number > 0 ? (string) (int) $note->session_number : ''); ?>"
                                   placeholder="مثلاً ۳">
                        </label>
                        <label>مدت (دقیقه)
                            <input type="text" name="duration_minutes" dir="ltr" inputmode="numeric" maxlength="3" size="4"
                                   value="<?php echo esc_attr((int) ($note->duration_minutes ?? 0) > 0 ? (string) (int) $note->duration_minutes : ''); ?>">
                        </label>
                        <label>وضعیت
                            <select name="session_status">
                                <?php foreach (Pas_CF_Case_File::session_statuses() as $status_key => $status_label) : ?>
                                    <option value="<?php echo esc_attr($status_key); ?>"<?php echo (string) ($note->status ?? 'done') === $status_key ? ' selected' : ''; ?>><?php echo esc_html($status_label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label>جلسه‌ی بعد (شمسی)
                            <input type="text" name="next_session_date_jalali" dir="ltr" class="pas-cf-jalali-input"
                                   value="<?php echo esc_attr(!empty($note->next_session_date) ? Pas_CF_Jalali::to_jalali_string((string) $note->next_session_date) : ''); ?>">
                        </label>
                        <label><input type="checkbox" name="is_joint" value="1"<?php echo !empty($note->is_joint) ? ' checked' : ''; ?>> جلسه‌ی مشترک</label>
                    </p>
                    <textarea name="content" rows="4" class="large-text" required><?php echo esc_textarea($note->content); ?></textarea>
                    <?php submit_button('ذخیره‌ی ویرایش', 'secondary', 'submit', false); ?>
                </form>

                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="pas-cf-session-delete"
                      onsubmit="return confirm('این یادداشت جلسه حذف شود؟ این کار قابل بازگشت نیست (فقط برای سرپرست در تاریخچه قابل ردیابی می‌ماند).');">
                    <?php wp_nonce_field('pas_cf_delete_note'); ?>
                    <input type="hidden" name="action" value="pas_cf_delete_note">
                    <input type="hidden" name="case_id" value="<?php echo esc_attr($case_id); ?>">
                    <input type="hidden" name="note_id" value="<?php echo esc_attr($note->id); ?>">
                    <button type="submit" class="button-link-delete pas-cf-delete-note-btn">حذف این یادداشت</button>
                </form>
            <?php else : ?>
                <p><?php echo nl2br(esc_html($note->content)); ?></p>
                <p style="color:#b32d2e;">کلید رمزنگاری تنظیم نشده — ویرایش/حذف این یادداشت غیرفعال است.</p>
            <?php endif; ?>

            <?php if (!empty($history)) : ?>
                <details class="pas-cf-note-history">
                    <summary>تاریخچه‌ی ویرایش (<?php echo esc_html(count($history)); ?>)</summary>
                    <ul>
                        <?php foreach ($history as $h) :
                            $editor = get_userdata($h->changed_by);
                            $label  = $h->action === 'deleted' ? 'حذف شد' : 'ویرایش شد';
                            ?>
                            <li>
                                <strong><?php echo esc_html($label); ?></strong> —
                                <?php echo esc_html(Pas_CF_Jalali::to_jalali_datetime_string($h->changed_at)); ?> —
                                توسط <?php echo esc_html($editor ? $editor->display_name : '—'); ?>
                                <details class="pas-cf-note-history-old-text">
                                    <summary>نمایش متن نسخه‌ی قبلی</summary>
                                    <p><?php echo nl2br(esc_html($h->content)); ?></p>
                                    <?php if (Pas_CF_Crypto::is_configured()) : ?>
                                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"
                                              onsubmit="return confirm('محتوای فعلی این یادداشت با این نسخه‌ی قدیمی جایگزین شود؟');">
                                            <?php wp_nonce_field('pas_cf_restore_note'); ?>
                                            <input type="hidden" name="action" value="pas_cf_restore_note">
                                            <input type="hidden" name="case_id" value="<?php echo esc_attr($case_id); ?>">
                                            <input type="hidden" name="history_id" value="<?php echo esc_attr($h->id); ?>">
                                            <button type="submit" class="button button-small">بازگردانی این نسخه</button>
                                        </form>
                                    <?php endif; ?>
                                </details>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </details>
            <?php endif; ?>
        </details>
        <?php
    }

    // ── هندلرها ──────────────────────────────────────────────────────────

    public function handle_save_case(): void
    {
        Pas_CF_Security::require_access();
        check_admin_referer('pas_cf_save_case');

        $case_id = isset($_POST['case_id']) ? (int) $_POST['case_id'] : 0;
        $data    = wp_unslash($_POST);
        $can_view_all = Pas_CF_Security::current_user_can_view_all();

        if ($case_id) {
            $existing = Pas_CF_Case_File::get($case_id);
            if (!$existing) {
                wp_die('پرونده یافت نشد.', 'یافت نشد', ['response' => 404]);
            }
            // نگهبان اصلی همین‌جاست: بدون این چک، یک درمانگر بدون
            // PAS_CF_CAP_ALL می‌توانست با ساختن دستی همین درخواست POST
            // (نه فقط از طریق UI) پرونده‌ی درمانگر دیگری را ویرایش کند.
            Pas_CF_Security::require_case_access($existing);

            // فیلد assigned_therapist فقط برای سرپرست معتبر است؛ برای بقیه
            // مقدار پستی نادیده گرفته می‌شود و همان مقدار قبلی حفظ می‌شود —
            // یعنی حتی با دستکاری فرم، درمانگر عادی نمی‌تواند پرونده را به
            // کس دیگری منتقل کند.
            if (!$can_view_all) {
                $data['assigned_therapist'] = $existing->assigned_therapist;
            }

            Pas_CF_Case_File::update($case_id, $data);
        } else {
            // پرونده‌ی جدید: سرپرست می‌تواند هر درمانگری را انتخاب کند؛
            // کاربر عادی همیشه پرونده را به خودش تخصیص می‌دهد.
            if (!$can_view_all) {
                $data['assigned_therapist'] = get_current_user_id();
            }

            $case_id = Pas_CF_Case_File::create($data);
        }

        wp_safe_redirect(admin_url('admin.php?page=pas-case-file-new&case_id=' . $case_id . '&saved=1'));
        exit;
    }

    /**
     * متادیتای جلسه از فرم (مدت، وضعیت، تاریخ جلسه‌ی بعد). تاریخ جلسه‌ی
     * بعد شمسی است و اینجا به میلادی تبدیل می‌شود؛ خالی = بدون جلسه‌ی بعد.
     */
    private function session_meta_from_post(): array
    {
        $next_raw  = trim((string) wp_unslash($_POST['next_session_date_jalali'] ?? ''));
        $next_date = '';

        if ($next_raw !== '') {
            $converted = Pas_CF_Jalali::jalali_string_to_gregorian_string($next_raw);
            if ($converted === null) {
                wp_die('تاریخ جلسه‌ی بعد نامعتبر است. فرمت درست: ۱۴۰۳/۰۵/۱۲', 'ورودی نامعتبر', ['response' => 400]);
            }
            $next_date = $converted;
        }

        return [
            'duration_minutes'  => wp_unslash($_POST['duration_minutes'] ?? ''),
            'status'            => wp_unslash($_POST['session_status'] ?? 'done'),
            'next_session_date' => $next_date,
            'is_joint'          => !empty($_POST['is_joint']) ? 1 : 0,
        ];
    }

    public function handle_add_note(): void
    {
        Pas_CF_Security::require_access();
        check_admin_referer('pas_cf_add_note');

        if (!Pas_CF_Crypto::is_configured()) {
            wp_die('کلید رمزنگاری تنظیم نشده است.');
        }

        $case_id       = (int) ($_POST['case_id'] ?? 0);
        $jalali_input  = sanitize_text_field(wp_unslash($_POST['session_date_jalali'] ?? ''));
        $content       = sanitize_textarea_field(wp_unslash($_POST['content'] ?? ''));

        $case = $case_id ? Pas_CF_Case_File::get($case_id) : null;
        if (!$case) {
            wp_die('پرونده یافت نشد.', 'یافت نشد', ['response' => 404]);
        }
        // بدون این چک، درمانگری بدون دسترسی به این پرونده می‌توانست فقط با
        // فرستادن case_id درست، یادداشت بالینی به پرونده‌ی کس دیگری اضافه کند.
        Pas_CF_Security::require_case_access($case);

        $session_date = Pas_CF_Jalali::jalali_string_to_gregorian_string($jalali_input);
        if ($session_date === null) {
            wp_die('تاریخ جلسه نامعتبر است. فرمت درست: ۱۴۰۳/۰۵/۱۲', 'ورودی نامعتبر', ['response' => 400]);
        }

        if ($content !== '') {
            Pas_CF_Case_File::add_note(
                $case_id,
                $session_date,
                $content,
                Pas_CF_Case_File::parse_session_number(wp_unslash($_POST['session_number'] ?? '')),
                $this->session_meta_from_post()
            );
        }

        wp_safe_redirect(admin_url('admin.php?page=pas-case-file-new&case_id=' . $case_id . '&note_added=1'));
        exit;
    }

    public function handle_edit_note(): void
    {
        Pas_CF_Security::require_access();
        check_admin_referer('pas_cf_edit_note');

        if (!Pas_CF_Crypto::is_configured()) {
            wp_die('کلید رمزنگاری تنظیم نشده است.');
        }

        $case_id      = (int) ($_POST['case_id'] ?? 0);
        $note_id      = (int) ($_POST['note_id'] ?? 0);
        $jalali_input = sanitize_text_field(wp_unslash($_POST['session_date_jalali'] ?? ''));
        $content      = sanitize_textarea_field(wp_unslash($_POST['content'] ?? ''));

        $case = $case_id ? Pas_CF_Case_File::get($case_id) : null;
        if (!$case) {
            wp_die('پرونده یافت نشد.', 'یافت نشد', ['response' => 404]);
        }
        // همان نگهبان دسترسی add_note — بدون این، درمانگری بدون دسترسی به
        // این پرونده می‌توانست یادداشت بالینی پرونده‌ی کس دیگری را ویرایش کند.
        Pas_CF_Security::require_case_access($case);

        $session_date = Pas_CF_Jalali::jalali_string_to_gregorian_string($jalali_input);
        if ($session_date === null) {
            wp_die('تاریخ جلسه نامعتبر است. فرمت درست: ۱۴۰۳/۰۵/۱۲', 'ورودی نامعتبر', ['response' => 400]);
        }

        if ($content !== '') {
            // update_note خودش هم چک می‌کند که note_id متعلق به همین case_id
            // باشد (نگاه کن به کامنت داخل Pas_CF_Case_File::update_note).
            $ok = Pas_CF_Case_File::update_note(
                $note_id,
                $case_id,
                $session_date,
                $content,
                Pas_CF_Case_File::parse_session_number(wp_unslash($_POST['session_number'] ?? '')),
                $this->session_meta_from_post()
            );
            if (!$ok) {
                wp_die('این یادداشت به این پرونده تعلق ندارد.', 'دسترسی غیرمجاز', ['response' => 403]);
            }
        }

        wp_safe_redirect(admin_url('admin.php?page=pas-case-file-new&case_id=' . $case_id . '&note_edited=1'));
        exit;
    }

    public function handle_delete_note(): void
    {
        Pas_CF_Security::require_access();
        check_admin_referer('pas_cf_delete_note');

        $case_id = (int) ($_POST['case_id'] ?? 0);
        $note_id = (int) ($_POST['note_id'] ?? 0);

        $case = $case_id ? Pas_CF_Case_File::get($case_id) : null;
        if (!$case) {
            wp_die('پرونده یافت نشد.', 'یافت نشد', ['response' => 404]);
        }
        Pas_CF_Security::require_case_access($case);

        $ok = Pas_CF_Case_File::delete_note($note_id, $case_id);
        if (!$ok) {
            wp_die('این یادداشت به این پرونده تعلق ندارد.', 'دسترسی غیرمجاز', ['response' => 403]);
        }

        wp_safe_redirect(admin_url('admin.php?page=pas-case-file-new&case_id=' . $case_id . '&note_deleted=1'));
        exit;
    }

    public function handle_restore_note(): void
    {
        Pas_CF_Security::require_access();
        check_admin_referer('pas_cf_restore_note');

        $case_id    = (int) ($_POST['case_id'] ?? 0);
        $history_id = (int) ($_POST['history_id'] ?? 0);

        $case = $case_id ? Pas_CF_Case_File::get($case_id) : null;
        if (!$case) {
            wp_die('پرونده یافت نشد.', 'یافت نشد', ['response' => 404]);
        }
        Pas_CF_Security::require_case_access($case);

        $ok = Pas_CF_Case_File::restore_note_version($history_id, $case_id);
        if (!$ok) {
            wp_die('این نسخه به این پرونده تعلق ندارد.', 'دسترسی غیرمجاز', ['response' => 403]);
        }

        wp_safe_redirect(admin_url('admin.php?page=pas-case-file-new&case_id=' . $case_id . '&note_restored=1'));
        exit;
    }

    /**
     * خروجی CSV لیست پرونده‌ها — همان محدوده‌ی دید صفحه‌ی لیست را رعایت
     * می‌کند (درمانگر معمولی فقط پرونده‌های خودش، سرپرست همه)، و همان
     * فیلتر جستجو را اگر در URL بوده اعمال می‌کند. الگوی BOM+fputcsv دقیقاً
     * همان چیزی است که pivand-amn-panel برای سازگاری با اکسل فارسی استفاده
     * می‌کند.
     */
    public function handle_export_csv(): void
    {
        Pas_CF_Security::require_access();
        check_admin_referer('pas_cf_export_csv');

        $search       = isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '';
        $can_view_all = Pas_CF_Security::current_user_can_view_all();
        $cases        = $can_view_all
            ? Pas_CF_Case_File::list_all()
            : Pas_CF_Case_File::list_for_therapist(get_current_user_id());

        if ($search !== '') {
            $needle = Pas_CF_Case_File::normalize_phone($search);
            $cases  = array_values(array_filter($cases, function ($c) use ($search, $needle) {
                return str_contains($c->full_name, $search) || str_contains($c->phone, $needle);
            }));
        }

        $last_session_map = Pas_CF_Case_File::get_last_session_map(wp_list_pluck($cases, 'id'));
        Pas_CF_Case_File::preload_linked_wp_users(wp_list_pluck($cases, 'phone'));

        nocache_headers();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="pivand-amn-case-files-' . current_time('Y-m-d') . '.csv"');

        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF"); // BOM — بدون این، اکسل فارسی متن UTF-8 را درست نمی‌خواند.

        fputcsv($out, ['نام', 'موبایل', 'موضوع', 'وضعیت', 'درمانگر مسئول', 'حساب مراجع (OTP)', 'آخرین جلسه', 'آخرین به‌روزرسانی']);

        foreach ($cases as $case) {
            $linked_user  = Pas_CF_Case_File::find_linked_wp_user($case->phone);
            $therapist    = $case->assigned_therapist ? get_userdata((int) $case->assigned_therapist) : false;
            $last_session = $last_session_map[$case->id] ?? '';

            fputcsv($out, [
                $case->full_name,
                $case->phone,
                $case->topic,
                $case->status,
                $therapist ? $therapist->display_name : '',
                $linked_user ? 'متصل' : 'بدون حساب',
                $last_session ? Pas_CF_Jalali::to_jalali_string($last_session) : '',
                Pas_CF_Jalali::to_jalali_datetime_string($case->updated_at),
            ]);
        }

        fclose($out);

        // case_id=0 چون این خروجی سراسری است و به یک پرونده‌ی خاص مربوط نیست.
        Pas_CF_Audit_Log::record(0, 'export_csv');

        exit;
    }

    /**
     * نمای پرینت — عمداً هیچ چیزی از قالب ادمین وردپرس (منو/هدر/سایدبار)
     * ندارد؛ یک سند HTML مستقل با استایل پرینت خودش خروجی می‌دهد و همان‌جا
     * exit می‌کند، دقیقاً مثل handle_export_csv. «ذخیره به PDF» یک قابلیت
     * جداگانه نیست — مقصد «Save as PDF» در دیالوگ پرینت خودِ مرورگر (روی
     * هر سیستم‌عاملی) دقیقاً همین نمای تمیز را می‌گیرد؛ نیازی به کتابخانه‌ی
     * PDF سمت سرور (که روی هاست ParsVDS این پروژه نصب نیست) نبود.
     */
    public function handle_print_case(): void
    {
        Pas_CF_Security::require_access();
        check_admin_referer('pas_cf_print_case');

        $case_id = (int) ($_GET['case_id'] ?? 0);
        $case    = $case_id ? Pas_CF_Case_File::get($case_id) : null;
        if (!$case) {
            wp_die('پرونده یافت نشد.', 'یافت نشد', ['response' => 404]);
        }
        Pas_CF_Security::require_case_access($case);

        $notes          = Pas_CF_Case_File::get_notes($case->id);
        $partner        = Pas_CF_Case_File::get_partner($case);
        $rel_assessment = Pas_CF_Case_File::get_relationship_assessment($case->phone);
        $before         = $rel_assessment['before'] ?? null;
        $after          = $rel_assessment['after'] ?? null;
        $therapist      = $case->assigned_therapist ? get_userdata((int) $case->assigned_therapist) : false;

        Pas_CF_Audit_Log::record($case->id, 'print_case');

        $row = function (string $label, $value) {
            $value = is_string($value) ? trim($value) : $value;
            echo '<tr><th>' . esc_html($label) . '</th><td>' . ($value !== '' && $value !== null ? nl2br(esc_html((string) $value)) : '<span class="pas-cf-print-empty">—</span>') . '</td></tr>';
        };

        header('Content-Type: text/html; charset=utf-8');
        nocache_headers();
        ?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="utf-8">
<title>پرونده — <?php echo esc_html($case->full_name ?: $case->phone); ?></title>
<style>
    * { box-sizing: border-box; }
    body {
        font-family: Tahoma, "Vazirmatn", "Segoe UI", sans-serif;
        color: #1e293b;
        max-width: 860px;
        margin: 24px auto;
        padding: 0 16px;
        line-height: 1.8;
    }
    .pas-cf-print-toolbar {
        position: sticky; top: 0; background: #f0fdfa; border-bottom: 1px solid #99f6e4;
        padding: 10px 16px; margin: -24px -16px 24px; display: flex; justify-content: space-between; align-items: center;
    }
    .pas-cf-print-toolbar button {
        background: #0f766e; color: #fff; border: none; border-radius: 6px; padding: 8px 18px;
        font-size: 14px; cursor: pointer;
    }
    h1 { font-size: 20px; border-bottom: 2px solid #0f766e; padding-bottom: 8px; }
    h2 { font-size: 15px; color: #0f766e; margin-top: 26px; border-right: 3px solid #14b8a6; padding-right: 8px; }
    table.pas-cf-print-table { width: 100%; border-collapse: collapse; margin-bottom: 6px; }
    table.pas-cf-print-table th, table.pas-cf-print-table td { text-align: right; padding: 6px 8px; border-bottom: 1px solid #e2e8f0; vertical-align: top; }
    table.pas-cf-print-table th { width: 220px; color: #475569; font-weight: 600; white-space: nowrap; }
    .pas-cf-print-empty { color: #94a3b8; }
    .pas-cf-print-meta { color: #64748b; font-size: 12.5px; margin-top: 2px; }
    .pas-cf-print-session { border: 1px solid #e2e8f0; border-radius: 8px; padding: 10px 14px; margin-bottom: 10px; page-break-inside: avoid; }
    .pas-cf-print-session-head { font-weight: 700; display: flex; justify-content: space-between; color: #0f766e; }
    .pas-cf-print-footer { margin-top: 30px; color: #94a3b8; font-size: 11.5px; text-align: center; }
    @media print {
        .pas-cf-print-toolbar { display: none; }
        body { margin: 0; max-width: none; }
        a { color: inherit; text-decoration: none; }
    }
</style>
</head>
<body>
    <div class="pas-cf-print-toolbar">
        <span>این نما فقط برای پرینت/ذخیره PDF است — برای ویرایش به پنل ادمین برگردید.</span>
        <button onclick="window.print()">🖨 پرینت / ذخیره PDF</button>
    </div>

    <h1>پرونده‌ی مراجع: <?php echo esc_html($case->full_name ?: $case->phone); ?></h1>
    <p class="pas-cf-print-meta">
        تاریخ چاپ: <?php echo esc_html(Pas_CF_Jalali::to_jalali_datetime_string(current_time('mysql', true))); ?>
        — درمانگر مسئول: <?php echo esc_html($therapist ? $therapist->display_name : '— تخصیص‌نیافته'); ?>
        — وضعیت: <?php echo esc_html($case->status); ?>
    </p>

    <h2>مشخصات</h2>
    <table class="pas-cf-print-table">
        <?php
        $row('نام و نام خانوادگی', $case->full_name);
        $row('موبایل', $case->phone);
        $row('سن', $case->age);
        $row('وضعیت تاهل', $case->marital_status);
        $row('تحصیلات', $case->education);
        $row('شغل', $case->occupation);
        $row('محل سکونت', $case->residence);
        $row('نوع ازدواج', $case->marriage_type);
        $row('تعداد فرزندان', $case->children_count);
        $row('موضوع کاری', $case->topic);
        $row('موضوعات اصلی تعارض', $case->main_conflict_topics);
        ?>
    </table>

    <h2>سابقه‌ی بالینی</h2>
    <table class="pas-cf-print-table">
        <?php
        $row('سابقه‌ی درمان قبلی', $case->prior_therapy);
        $row('سابقه‌ی ازدواج ناموفق', $case->failed_marriage_history);
        $row('سابقه‌ی بیماری خاص یا مصرف دارو', $case->medical_history);
        $row('سابقه‌ی مشاوره یا روان‌پزشک', $case->counseling_history);
        $row('سابقه‌ی خودکشی یا خودآسیب‌رسانی', $case->self_harm_history);
        $row('سوء مصرف مواد', $case->substance_use_history);
        ?>
    </table>

    <h2>علائم دو هفته اخیر</h2>
    <table class="pas-cf-print-table">
        <?php
        $row('خلق و افسردگی', $case->symptom_mood_depression);
        $row('اضطراب', $case->symptom_anxiety);
        ?>
    </table>

    <h2>سوالات مصاحبه</h2>
    <table class="pas-cf-print-table">
        <?php
        $row('۱. علت اصلی مراجعه به مشاوره', $case->q_main_reason);
        $row('۲. نکات مثبت و منفی یکدیگر', $case->q_positive_negative_traits);
        $row('۳. چگونه تعارضات را حل می‌کنید؟', $case->q_conflict_resolution);
        $row('۴. چه چیزی باعث شده همچنان در رابطه بمانید', $case->q_reason_staying);
        $row('۵. ارزیابی کوتاه طرحواره و سبک دلبستگی', $case->q_schema_attachment);
        $row('۶. اهداف مراجعه', $case->q_therapy_goals);
        ?>
    </table>

    <h2>ارزیابی رابطه (آزمون رضایت زناشویی)</h2>
    <table class="pas-cf-print-table">
        <?php
        $row('نمره — قبل از درمان (ثبت دستی)', $case->relationship_satisfaction_pre);
        $row('نمره — پس از درمان (ثبت دستی)', $case->relationship_satisfaction_post);
        if ($before) {
            $row('نمره — قبل از درمان (آزمون CSI-16)', ($before->total ?? '—') . ' — ' . Pas_CF_Jalali::to_jalali_datetime_string($before->completed_at));
        }
        if ($after) {
            $row('نمره — پس از درمان (آزمون CSI-16)', ($after->total ?? '—') . ' — ' . Pas_CF_Jalali::to_jalali_datetime_string($after->completed_at));
        }
        ?>
    </table>

    <?php if ($case->spouse_full_name || $case->spouse_phone) : ?>
        <h2>اطلاعات همسر</h2>
        <table class="pas-cf-print-table">
            <?php
            $row('نام و نام خانوادگی همسر', $case->spouse_full_name);
            $row('سن همسر', $case->spouse_age);
            $row('تحصیلات همسر', $case->spouse_education);
            $row('شغل همسر', $case->spouse_occupation);
            $row('سابقه‌ی ازدواج ناموفق همسر', $case->spouse_failed_marriage_history);
            $row('سابقه‌ی بیماری/دارو همسر', $case->spouse_medical_history);
            $row('سابقه‌ی مشاوره/روان‌پزشک همسر', $case->spouse_counseling_history);
            $row('سابقه‌ی خودکشی/خودآسیب‌رسانی همسر', $case->spouse_self_harm_history);
            $row('سوء مصرف مواد همسر', $case->spouse_substance_use_history);
            $row('موبایل همسر', $case->spouse_phone);
            ?>
        </table>
    <?php endif; ?>

    <?php if ($partner) : ?>
        <h2>پارتنر / همسر تحت درمان (پرونده‌ی جداگانه)</h2>
        <table class="pas-cf-print-table">
            <?php $row('لینک‌شده به پرونده', ($partner->full_name ?: '') . ' — ' . $partner->phone); ?>
        </table>
    <?php endif; ?>

    <h2>یادداشت‌های جلسه (<?php echo esc_html(count($notes)); ?> جلسه)</h2>
    <?php if (empty($notes)) : ?>
        <p class="pas-cf-print-empty">هنوز یادداشتی ثبت نشده.</p>
    <?php else : ?>
        <?php foreach ($notes as $note) :
            $author = get_userdata($note->author_id);
            ?>
            <div class="pas-cf-print-session">
                <div class="pas-cf-print-session-head">
                    <span><?php echo esc_html(Pas_CF_Jalali::to_jalali_string($note->session_date) ?: $note->session_date); ?></span>
                    <span class="pas-cf-print-meta"><?php echo esc_html($author ? $author->display_name : '—'); ?></span>
                </div>
                <p><?php echo nl2br(esc_html($note->content)); ?></p>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <p class="pas-cf-print-footer">پیوند امن — این سند حاوی اطلاعات بالینی محرمانه است و صرفاً برای استفاده‌ی درمانی مجاز است.</p>
</body>
</html>
        <?php
        exit;
    }
}
