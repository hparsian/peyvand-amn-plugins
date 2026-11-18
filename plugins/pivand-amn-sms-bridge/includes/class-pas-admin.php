<?php

if (!defined('ABSPATH')) {
    exit;
}

final class Pas_Admin
{
    private string $page_hook = '';

    public function __construct()
    {
        add_action('admin_menu', [$this, 'register_menu']);
        add_action('admin_post_pas_save_settings', [$this, 'handle_save_settings']);
        add_action('admin_post_pas_test_send', [$this, 'handle_test_send']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('admin_notices', [$this, 'maybe_warn_about_credentials']);
    }

    /**
     * فاز ۰ (1.1.6): هشدار درباره‌ی اعتبارنامه. سه حالت:
     *   ۱. رمز فعلی همان مقدار پیش‌فرض لو‌رفته‌ی نسخه‌های قبلی است → خطا + دستور چرخش
     *   ۲. هیچ اعتبارنامه‌ای تنظیم نشده → هشدار (پیامک کار نمی‌کند)
     *   ۳. وضعیت عادی → هیچ نوتیسی
     * این متد هیچ مقدار حساسی چاپ نمی‌کند و چیزی را تغییر نمی‌دهد.
     */
    public function maybe_warn_about_credentials(): void
    {
        if (!current_user_can('manage_options') || !class_exists('Pas_Melipayamak')) {
            return;
        }

        if (Pas_Melipayamak::uses_leaked_credentials()) {
            printf(
                '<div class="notice notice-error"><p><strong>پیوند امن — پل پیامک:</strong> رمز ملی‌پیامکی که همین حالا استفاده می‌شود، همان مقدار پیش‌فرضی است که در نسخه‌های قبلی داخل کد افزونه قرار داشت و باید لو‌رفته فرض شود. لطفاً از پنل ملی‌پیامک رمز را عوض کن و مقدار جدید را در <a href="%s">تنظیمات پل پیامک</a> یا (بهتر) به‌صورت ثابت‌های <code>PAS_SMS_USERNAME</code>/<code>PAS_SMS_PASSWORD</code> در <code>wp-config.php</code> بگذار. تا آن زمان ارسال پیامک با همان اعتبارنامه ادامه دارد.</p></div>',
                esc_url(admin_url('admin.php?page=pas-sms-bridge'))
            );

            return;
        }

        if (Pas_Melipayamak::credentials_source() === 'missing') {
            printf(
                '<div class="notice notice-warning"><p><strong>پیوند امن — پل پیامک:</strong> هیچ اعتبارنامه‌ای برای ملی‌پیامک تنظیم نشده است؛ ارسال پیامک (OTP، یادآوری نوبت، پیگیری لید) کار نخواهد کرد. آن را در <a href="%s">تنظیمات پل پیامک</a> وارد کن.</p></div>',
                esc_url(admin_url('admin.php?page=pas-sms-bridge'))
            );
        }
    }

    public function enqueue_assets(string $hook): void
    {
        if ($hook !== $this->page_hook) {
            return;
        }

        // فاز ۱۲ / گام ۶: فونت سلف‌هاست (کیت، سپس هسته) — بدون درخواست خارجی.
        $deps = class_exists('Pas_Core_Tokens') ? [\Pas_Core_Tokens::handle()] : [];
        if (class_exists('PUI')) {
            $deps[] = PUI::handle('fonts');
        } elseif (class_exists('Pas_Core_Fonts')) {
            $deps[] = \Pas_Core_Fonts::handle();
        }

        wp_enqueue_style(
            'pas-sms-bridge-admin',
            PAS_BRIDGE_URL . 'assets/css/admin.css',
            $deps,
            PAS_BRIDGE_VERSION
        );
    }

    public function render_credentials_source(): void
    {
        $source = Pas_Melipayamak::credentials_source();

        echo '<p class="description">منبع اعتبارنامه‌ی فعلی: ';

        if ($source === 'constants') {
            echo '✅ ثابت‌های <code>PAS_SMS_USERNAME</code>/<code>PAS_SMS_PASSWORD</code> در <code>wp-config.php</code> (توصیه‌شده) — این دو بر فیلدهای زیر اولویت دارند.';
        } elseif ($source === 'options') {
            echo '⚠️ ذخیره‌شده در دیتابیس (از طریق فرم زیر). برای امنیت بیشتر، ثابت‌ها را در <code>wp-config.php</code> تعریف کن.';
        } else {
            echo '❌ تنظیم نشده — ارسال پیامک کار نمی‌کند.';
        }

        echo '</p>';
    }

    public function register_menu(): void
    {
        $this->page_hook = add_menu_page(
            'پل پیامک (ملی پیامک)',
            'پل پیامک',
            'manage_options',
            'pas-sms-bridge',
            [$this, 'render'],
            'dashicons-email-alt',
            31
        );
    }

    public function render(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die('دسترسی غیرمجاز', 403);
        }

        $notice = isset($_GET['pas_notice']) ? sanitize_key($_GET['pas_notice']) : '';
        ?>
        <div class="wrap pas-sms-admin">
            <h1>پل پیامک — ملی پیامک</h1>
            <p class="description">
                این پلاگین تنها نقطه‌ی اتصال واقعی به وب‌سرویس ملی پیامک برای کل
                اکوسیستم پیوند امن (پنل مراجعان، آزمون‌ها و...) است. سایر پلاگین‌ها فقط
                فیلتر مشترک <code>pas_send_sms</code> را صدا می‌زنند و خودشان
                هیچ اطلاعات ورود ملی پیامک ندارند.
            </p>

            <?php $this->render_credentials_source(); ?>

            <?php if ($notice === 'saved') : ?>
                <div class="notice notice-success is-dismissible"><p>تنظیمات ذخیره شد.</p></div>
            <?php elseif ($notice === 'test_sent') : ?>
                <div class="notice notice-success is-dismissible"><p>درخواست ارسال تست انجام شد — نتیجه را در جدول لاگ زیر ببین.</p></div>
            <?php endif; ?>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="pas_save_settings">
                <?php wp_nonce_field('pas_save_settings', 'pas_settings_nonce'); ?>
                <table class="form-table">
                    <tr>
                        <th><label for="pas_sms_username">نام‌کاربری ملی پیامک</label></th>
                        <td><input type="text" id="pas_sms_username" name="pas_sms_username" value="<?php echo esc_attr((string) get_option('pas_sms_username', '')); ?>" class="regular-text" autocomplete="off"></td>
                    </tr>
                    <tr>
                        <th><label for="pas_sms_password">رمز عبور ملی پیامک</label></th>
                        <td>
                            <input type="password" id="pas_sms_password" name="pas_sms_password" value="" class="regular-text" autocomplete="new-password"
                                   placeholder="<?php echo get_option('pas_sms_password', '') !== '' ? '•••••••• (تنظیم شده — برای تغییر، مقدار جدید وارد کن)' : ''; ?>">
                            <p class="description">
                                <?php
                                // امنیت: رمز ذخیره‌شده قبلاً اینجا با esc_attr() دوباره چاپ می‌شد —
                                // یعنی رمز واقعی توی HTML صفحه (قابل دیدن با view-source) قرار
                                // می‌گرفت. حالا فیلد همیشه خالی نمایش داده می‌شود؛ اگر خالی ذخیره
                                // شود، رمز قبلی دست‌نخورده می‌ماند (نگاه کن به handle_save_settings).
                                echo get_option('pas_sms_password', '') !== '' ? 'برای حفظ رمز فعلی، این فیلد را خالی بگذار.' : 'هنوز رمزی ذخیره نشده.';
                                echo ' اگر ثابت <code>PAS_SMS_PASSWORD</code> در wp-config.php تعریف شده باشد، آن مقدار بر این فیلد اولویت دارد.';
                                ?>
                            </p>
                        </td>
                    </tr>
                </table>
                <?php submit_button('ذخیره تنظیمات'); ?>
            </form>

            <hr>
            <h2>تست ارسال</h2>
            <p class="description">یک شماره و شناسه‌ی پترن (bodyId) از پنل ملی‌پیامکت بده تا واقعاً یک پیامک تست بفرستیم و نتیجه‌ی دقیق (نه فقط موفق/ناموفق، بلکه دلیل) رو تو جدول لاگ ببینی.</p>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="pas_test_send">
                <?php wp_nonce_field('pas_test_send', 'pas_test_nonce'); ?>
                <table class="form-table">
                    <tr>
                        <th><label for="pas_test_mobile">شماره موبایل</label></th>
                        <td><input type="text" id="pas_test_mobile" name="pas_test_mobile" placeholder="09xxxxxxxxx" class="regular-text"></td>
                    </tr>
                    <tr>
                        <th><label for="pas_test_body_id">شناسه‌ی پترن (bodyId)</label></th>
                        <td><input type="text" id="pas_test_body_id" name="pas_test_body_id" class="regular-text"></td>
                    </tr>
                    <tr>
                        <th><label for="pas_test_values">مقادیر متغیرها</label></th>
                        <td><input type="text" id="pas_test_values" name="pas_test_values" placeholder="با کاما جدا کن، مثلاً: 12345" class="regular-text"></td>
                    </tr>
                </table>
                <?php submit_button('ارسال پیامک تست', 'secondary'); ?>
            </form>

            <hr>
            <h2>۵۰ لاگ آخر ارسال</h2>
            <table class="widefat striped">
                <thead><tr><th>زمان</th><th>موبایل</th><th>bodyId</th><th>نتیجه</th><th>توضیح</th></tr></thead>
                <tbody>
                <?php foreach (Pas_Log::recent(50) as $row) : ?>
                    <tr>
                        <td><?php echo esc_html(function_exists('pas_jalali_datetime') ? pas_jalali_datetime((string) $row->created_at) : (string) $row->created_at); ?></td>
                        <td dir="ltr"><?php echo esc_html($row->mobile); ?></td>
                        <td><?php echo esc_html((string) $row->body_id); ?></td>
                        <td><?php echo $row->success ? '<span class="pas-log-ok">موفق</span>' : '<span class="pas-log-fail">ناموفق</span>'; ?></td>
                        <td><?php echo esc_html($row->reason); ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty(Pas_Log::recent(1))) : ?>
                    <tr><td colspan="5">هنوز هیچ پیامکی ارسال نشده.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    public function handle_save_settings(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die('دسترسی غیرمجاز', 403);
        }
        check_admin_referer('pas_save_settings', 'pas_settings_nonce');

        update_option('pas_sms_username', sanitize_text_field(wp_unslash($_POST['pas_sms_username'] ?? '')));

        $new_password = sanitize_text_field(wp_unslash($_POST['pas_sms_password'] ?? ''));
        if ($new_password !== '') {
            // مطابق الگوی رمزنگاری اکوسیستم (نگاه کن به quiz-enterprise/case-file):
            // اگر core نصب و کلید رمزنگاری تنظیم شده باشد، رمز ملی‌پیامک هم
            // مثل بقیه‌ی داده‌های حساس رمزنگاری‌شده ذخیره می‌شود. اگر core
            // نصب نباشد یا کلید تنظیم نشده باشد، بدون خطا به‌صورت متن ساده
            // ذخیره می‌شود (رفتار قبلی) تا چیزی نشکند.
            if (class_exists('Pas_Core_Crypto') && Pas_Core_Crypto::is_configured()) {
                $new_password = Pas_Core_Crypto::wrap_encrypted($new_password);
            }
            update_option('pas_sms_password', $new_password);
        }

        wp_safe_redirect(admin_url('admin.php?page=pas-sms-bridge&pas_notice=saved'));
        exit;
    }

    public function handle_test_send(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die('دسترسی غیرمجاز', 403);
        }
        check_admin_referer('pas_test_send', 'pas_test_nonce');

        $mobile  = sanitize_text_field(wp_unslash($_POST['pas_test_mobile'] ?? ''));
        $body_id = (int) ($_POST['pas_test_body_id'] ?? 0);
        $values  = sanitize_text_field(wp_unslash($_POST['pas_test_values'] ?? ''));
        $params  = array_filter(array_map('trim', explode(',', $values)), static fn ($v) => $v !== '');

        apply_filters('pas_send_sms', null, $mobile, $body_id, array_values($params));

        wp_safe_redirect(admin_url('admin.php?page=pas-sms-bridge&pas_notice=test_sent'));
        exit;
    }
}
