<?php

if (!defined('ABSPATH')) {
    exit;
}

final class Pas_2FA_Profile
{
    private const PENDING_TRANSIENT_PREFIX = 'pas_2fa_pending_';
    private const REVEAL_CODES_TRANSIENT_PREFIX = 'pas_2fa_reveal_codes_';

    public function __construct()
    {
        add_action('show_user_profile', [$this, 'render_section']);
        add_action('edit_user_profile', [$this, 'render_section']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);

        // عمداً از admin_post استفاده می‌کنیم، نه ذخیره‌ی خودکار پروفایل —
        // چون فعال‌سازی ۲FA باید با تایید کد انجام شود، نه فقط ذخیره‌ی فرم.
        add_action('admin_post_pas_2fa_generate', [$this, 'handle_generate']);
        add_action('admin_post_pas_2fa_confirm', [$this, 'handle_confirm']);
        add_action('admin_post_pas_2fa_disable', [$this, 'handle_disable']);
        add_action('admin_post_pas_2fa_regenerate_codes', [$this, 'handle_regenerate_codes']);
        add_action('admin_notices', [$this, 'maybe_show_low_codes_notice']);
    }

    /**
     * فقط توی صفحه‌ی پروفایل خود کاربر یا ویرایش کاربر دیگر لود کن —
     * نه توی کل ادمین.
     */
    public function enqueue_assets(string $hook): void
    {
        if (!in_array($hook, ['profile.php', 'user-edit.php'], true)) {
            return;
        }

        wp_enqueue_style('pas-2fa-admin', PAS_2FA_URL . 'assets/css/admin.css', class_exists('Pas_Core_Tokens') ? [\Pas_Core_Tokens::handle()] : [], PAS_2FA_VERSION);
    }

    /**
     * توجه امنیتی: همیشه روی get_current_user_id() عمل می‌کنیم، نه روی
     * user_id ورودی از URL/فرم — یعنی حتی اگر ادمینی صفحه‌ی پروفایل کاربر
     * دیگری را باز کند، دکمه‌های این‌جا فقط ۲FA خودش را تغییر می‌دهند، نه
     * کاربر دیگر. (۲FA یک تنظیم شخصی امنیتی است، نه چیزی که دیگری برایت
     * تنظیم کند.)
     */
    public function render_section(\WP_User $profile_user): void
    {
        if ($profile_user->ID !== get_current_user_id()) {
            return;
        }

        $enabled = (bool) get_user_meta($profile_user->ID, 'pas_2fa_secret', true);
        $pending = get_transient(self::PENDING_TRANSIENT_PREFIX . $profile_user->ID);
        $reveal_codes = get_transient(self::REVEAL_CODES_TRANSIENT_PREFIX . $profile_user->ID);
        if ($reveal_codes) {
            delete_transient(self::REVEAL_CODES_TRANSIENT_PREFIX . $profile_user->ID);
        }
        ?>
        <div class="pas-2fa-section">
        <h2>احراز هویت دومرحله‌ای (پیوند امن)</h2>

        <?php if ($reveal_codes) : ?>
            <div class="pas-2fa-backup-codes-reveal" style="background:#fff8e1;border:1px solid #e0c14d;padding:12px 16px;margin-bottom:16px;max-width:480px;">
                <p><strong>⚠️ کدهای بازیابی — همین الان ذخیره کن، دوباره نشان داده نمی‌شوند.</strong></p>
                <p class="description">اگر گوشی/اپ احراز هویتت را گم کردی، هرکدام از این ۱۰ کد را (فقط یک‌بار قابل استفاده) به‌جای کد اپ وارد کن تا وارد شوی.</p>
                <pre class="pas-2fa-codes-list" style="font-size:14px;line-height:1.9;user-select:all;"><?php echo esc_html(implode("\n", $reveal_codes)); ?></pre>
                <p class="description">این پیام فقط همین یک‌بار نمایش داده می‌شود؛ صفحه را رفرش کنی دیگر نیست.</p>
            </div>
        <?php endif; ?>

        <table class="form-table">
            <tr>
                <th>وضعیت</th>
                <td>
                    <?php if ($enabled) : ?>
                        <p>✅ فعال است.</p>
                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" onsubmit="return confirm('غیرفعال کردن ۲FA امنیت حساب را کاهش می‌دهد. مطمئنی؟');">
                            <?php wp_nonce_field('pas_2fa_disable'); ?>
                            <input type="hidden" name="action" value="pas_2fa_disable">
                            <?php submit_button('غیرفعال‌سازی', 'delete', 'submit', false); ?>
                        </form>

                        <hr style="margin:16px 0;max-width:480px;">
                        <p>
                            <strong>کدهای بازیابی:</strong>
                            <?php
                            $remaining = Pas_2FA_Backup_Codes::remaining_count($profile_user->ID);
                            if ($remaining > 0) {
                                echo esc_html($remaining) . ' کد باقی‌مانده.';
                                if ($remaining <= 2) {
                                    echo ' <span style="color:#c62828;">(کم مانده — بهتر است کدهای جدید بسازی.)</span>';
                                }
                            } else {
                                echo '<span style="color:#c62828;">هیچ کد بازیابی‌ای نداری — اگر گوشی‌ات را گم کنی، راهی برای ورود نداری.</span>';
                            }
                            ?>
                        </p>
                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" onsubmit="return confirm('کدهای بازیابی قبلی برای همیشه باطل می‌شوند و ۱۰ کد جدید ساخته می‌شود. مطمئنی؟');">
                            <?php wp_nonce_field('pas_2fa_regenerate_codes'); ?>
                            <input type="hidden" name="action" value="pas_2fa_regenerate_codes">
                            <?php submit_button($remaining > 0 ? 'ساخت مجدد کدهای بازیابی' : 'ساخت کدهای بازیابی', 'secondary', 'submit', false); ?>
                        </form>
                    <?php elseif ($pending) : ?>
                        <p>یک اپ احراز هویت (Google Authenticator، Authy، و مشابه) نصب کن و این کد را دستی وارد کن:</p>
                        <p><code class="pas-2fa-secret"><?php echo esc_html((string) $pending['secret']); ?></code></p>
                        <p class="description">یا این لینک را در اپی که از otpauth پشتیبانی می‌کند باز کن: <br><code class="pas-2fa-otpauth-uri"><?php echo esc_html(Pas_2FA_Totp::otpauth_uri((string) $pending['secret'], $profile_user->user_login)); ?></code></p>

                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                            <?php wp_nonce_field('pas_2fa_confirm'); ?>
                            <input type="hidden" name="action" value="pas_2fa_confirm">
                            <p>
                                <label>کد ۶رقمی نمایش‌داده‌شده در اپ را برای تایید وارد کن:
                                    <input type="text" name="code" inputmode="numeric" pattern="[0-9]*">
                                </label>
                            </p>
                            <?php submit_button('تایید و فعال‌سازی', 'primary', 'submit', false); ?>
                        </form>
                    <?php else : ?>
                        <p>❌ غیرفعال است. با توجه به این‌که این حساب به داده‌ی حساس مراجعین و تنظیمات پرداخت/پیامک دسترسی دارد، فعال کردن این قابلیت به‌شدت توصیه می‌شود.</p>
                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                            <?php wp_nonce_field('pas_2fa_generate'); ?>
                            <input type="hidden" name="action" value="pas_2fa_generate">
                            <?php submit_button('شروع فعال‌سازی', 'secondary', 'submit', false); ?>
                        </form>
                    <?php endif; ?>
                </td>
            </tr>
        </table>
        </div>
        <?php
    }

    /**
     * بعد از ورود با یکی از آخرین ۲ کد بازیابی (نگاه کن به
     * Pas_2FA_Login_Gate::handle_challenge_submit)، یک‌بار در پیشخوان
     * یادآوری می‌شود که کدهای جدید بسازد.
     */
    public function maybe_show_low_codes_notice(): void
    {
        $uid = get_current_user_id();
        if (!$uid) {
            return;
        }

        $key = 'pas_2fa_low_codes_notice_' . $uid;
        $remaining = get_transient($key);
        if ($remaining === false) {
            return;
        }
        delete_transient($key);

        printf(
            '<div class="notice notice-warning is-dismissible"><p>⚠️ فقط %d کد بازیابی ۲FA برایت باقی مانده. از <a href="%s">پروفایلت</a> کدهای جدید بساز.</p></div>',
            (int) $remaining,
            esc_url(get_edit_profile_url($uid) . '#pas-2fa')
        );
    }

    public function handle_generate(): void
    {
        check_admin_referer('pas_2fa_generate');
        $uid = get_current_user_id();

        // secret فقط موقت (pending) ذخیره می‌شود — تا زمانی که با یک کد
        // معتبر تایید نشود، در user_meta ثبت نمی‌شود (تا کسی با یه secret
        // تست‌نشده خودش را قفل نکند).
        set_transient(self::PENDING_TRANSIENT_PREFIX . $uid, [
            'secret' => Pas_2FA_Totp::generate_secret(),
        ], 15 * MINUTE_IN_SECONDS);

        wp_safe_redirect(get_edit_profile_url($uid) . '#pas-2fa');
        exit;
    }

    public function handle_confirm(): void
    {
        check_admin_referer('pas_2fa_confirm');
        $uid     = get_current_user_id();
        $pending = get_transient(self::PENDING_TRANSIENT_PREFIX . $uid);
        $code    = sanitize_text_field(wp_unslash($_POST['code'] ?? ''));

        if ($pending && Pas_2FA_Totp::verify((string) $pending['secret'], $code)) {
            $secret_to_store = $pending['secret'];
            // مطابق الگوی رمزنگاری اکوسیستم: سیکرت TOTP هم مثل رمز پیامک/SMTP
            // و کلید API حساس است و رمزنگاری‌شده ذخیره می‌شود.
            if (class_exists('Pas_Core_Crypto') && Pas_Core_Crypto::is_configured()) {
                $secret_to_store = Pas_Core_Crypto::wrap_encrypted((string) $secret_to_store);
            }
            update_user_meta($uid, 'pas_2fa_secret', $secret_to_store);
            delete_transient(self::PENDING_TRANSIENT_PREFIX . $uid);

            // کدهای بازیابی همین لحظه‌ی فعال‌سازی ساخته می‌شوند تا کاربر
            // مجبور نباشد بلافاصله دوباره وارد پروفایل شود؛ برای نمایشِ
            // یک‌باره در transient کوتاه‌مدت (۵ دقیقه) نگه داشته می‌شوند.
            $codes = Pas_2FA_Backup_Codes::generate($uid);
            set_transient(self::REVEAL_CODES_TRANSIENT_PREFIX . $uid, $codes, 5 * MINUTE_IN_SECONDS);

            wp_safe_redirect(add_query_arg('pas_2fa', 'enabled', get_edit_profile_url($uid)));
        } else {
            wp_safe_redirect(add_query_arg('pas_2fa', 'invalid_code', get_edit_profile_url($uid)));
        }
        exit;
    }

    public function handle_disable(): void
    {
        check_admin_referer('pas_2fa_disable');
        $uid = get_current_user_id();
        delete_user_meta($uid, 'pas_2fa_secret');
        Pas_2FA_Backup_Codes::delete_all($uid);
        wp_safe_redirect(add_query_arg('pas_2fa', 'disabled', get_edit_profile_url($uid)));
        exit;
    }

    /**
     * کدهای بازیابی قبلی را برای همیشه باطل و ۱۰ کد جدید می‌سازد — فقط
     * برای کاربری که ۲FA را از قبل فعال کرده (منطقی نیست بدون سیکرت TOTP
     * کد بازیابی داشته باشی).
     */
    public function handle_regenerate_codes(): void
    {
        check_admin_referer('pas_2fa_regenerate_codes');
        $uid = get_current_user_id();

        if (!get_user_meta($uid, 'pas_2fa_secret', true)) {
            wp_safe_redirect(get_edit_profile_url($uid) . '#pas-2fa');
            exit;
        }

        $codes = Pas_2FA_Backup_Codes::generate($uid);
        set_transient(self::REVEAL_CODES_TRANSIENT_PREFIX . $uid, $codes, 5 * MINUTE_IN_SECONDS);

        wp_safe_redirect(get_edit_profile_url($uid) . '#pas-2fa');
        exit;
    }
}
