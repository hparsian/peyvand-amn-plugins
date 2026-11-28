<?php

if (!defined('ABSPATH')) {
    exit;
}

final class Pas_2FA_Login_Gate
{
    private const TRANSIENT_PREFIX = 'pas_2fa_challenge_';
    private const MAX_ATTEMPTS     = 5;
    private const CHALLENGE_TTL    = 5 * MINUTE_IN_SECONDS;

    public function __construct()
    {
        // اولویت 30 — بعد از این‌که هسته‌ی وردپرس (اولویت 20) رمز عبور را
        // تایید کرد. اگر رمز غلط بود، $user از قبل WP_Error است و اینجا
        // کاری نمی‌کنیم.
        add_filter('authenticate', [$this, 'maybe_challenge'], 30, 3);

        // این هوک همیشه (حتی اگر خطای برگشتی از ما باشد) صدا زده می‌شود —
        // نقطه‌ی امن برای ریدایرکت به صفحه‌ی کد تایید، چون بلافاصله بعدش
        // exit می‌کنیم و ادامه‌ی رندر صفحه‌ی خطای وردپرس هرگز اجرا نمی‌شود.
        add_action('wp_login_failed', [$this, 'maybe_redirect_to_challenge'], 10, 2);

        add_action('login_form_pas_2fa_verify', [$this, 'render_challenge_form']);
        add_action('login_form_pas_2fa_verify_submit', [$this, 'handle_challenge_submit']);
    }

    public function maybe_challenge($user, string $username, string $password)
    {
        if (!($user instanceof \WP_User) || $password === '') {
            return $user; // رمز غلط بود یا این فیلتر بی‌ربط است — دست نمی‌زنیم
        }

        $secret = get_user_meta($user->ID, 'pas_2fa_secret', true);
        if (empty($secret)) {
            return $user; // این کاربر ۲FA را فعال نکرده — رفتار قبلی
        }
        // در این مرحله فقط «آیا خالی است یا نه» مهم است، نه مقدار واقعی —
        // پس نیازی به رمزگشایی اینجا نیست (در handle_challenge_submit انجام می‌شود).

        $rememberme  = !empty($_POST['rememberme']);
        $redirect_to = isset($_REQUEST['redirect_to'])
            ? wp_validate_redirect(esc_url_raw(wp_unslash($_REQUEST['redirect_to'])), admin_url())
            : admin_url();

        $token = wp_generate_password(32, false, false);
        set_transient(self::TRANSIENT_PREFIX . $token, [
            'user_id'     => $user->ID,
            'attempts'    => 0,
            'rememberme'  => $rememberme,
            'redirect_to' => $redirect_to,
        ], self::CHALLENGE_TTL);

        // توکن را داخل کد خطا قاچاق می‌کنیم تا در wp_login_failed در دسترس باشد
        return new \WP_Error('pas_2fa_required_' . $token, 'کد تایید دومرحله‌ای لازم است.');
    }

    public function maybe_redirect_to_challenge(string $username, $error = null): void
    {
        $code = ($error instanceof \WP_Error) ? $error->get_error_code() : '';
        if (strpos($code, 'pas_2fa_required_') !== 0) {
            return; // یک خطای معمولی (رمز غلط و...) — کاری نمی‌کنیم
        }

        $token = substr($code, strlen('pas_2fa_required_'));
        wp_safe_redirect(add_query_arg([
            'action' => 'pas_2fa_verify',
            'token'  => $token,
        ], wp_login_url()));
        exit;
    }

    public function render_challenge_form(): void
    {
        $token = isset($_GET['token']) ? sanitize_text_field(wp_unslash($_GET['token'])) : '';
        $data  = get_transient(self::TRANSIENT_PREFIX . $token);

        if (!$data) {
            wp_safe_redirect(wp_login_url());
            exit;
        }

        $has_error = isset($_GET['pas_err']) && sanitize_text_field(wp_unslash($_GET['pas_err'])) === '1';
        $wp_error  = $has_error ? new \WP_Error('pas_2fa_invalid', 'کد واردشده معتبر یا قابل‌استفاده نیست. لطفاً دوباره تلاش کنید.') : null;

        login_header('تایید دومرحله‌ای', '', $wp_error);
        ?>
        <form name="pas2fa" id="loginform" method="post" action="<?php echo esc_url(add_query_arg('action', 'pas_2fa_verify_submit', wp_login_url())); ?>">
            <?php wp_nonce_field('pas_2fa_verify_' . $token, 'pas_2fa_nonce'); ?>
            <input type="hidden" name="token" value="<?php echo esc_attr($token); ?>">
            <p>
                <label for="pas_2fa_code">کد ۶رقمی اپ احراز هویت</label>
                <input type="text" name="code" id="pas_2fa_code" class="input" autocomplete="one-time-code" autofocus>
            </p>
            <p class="description">گوشی/اپ احراز هویت را در دسترس نداری؟ به‌جای کد بالا، یکی از کدهای بازیابی‌ات (فرمت XXXXX-XXXXX) را همین‌جا وارد کن.</p>
            <p class="submit">
                <input type="submit" class="button button-primary button-large" value="تایید">
            </p>
        </form>
        <?php
        login_footer();
        exit;
    }

    public function handle_challenge_submit(): void
    {
        $token = isset($_POST['token']) ? sanitize_text_field(wp_unslash($_POST['token'])) : '';
        $nonce = isset($_POST['pas_2fa_nonce']) ? sanitize_text_field(wp_unslash($_POST['pas_2fa_nonce'])) : '';
        $code  = isset($_POST['code']) ? sanitize_text_field(wp_unslash($_POST['code'])) : '';
        $key   = self::TRANSIENT_PREFIX . $token;
        $data  = $token !== '' ? get_transient($key) : false;

        if (!$data || !$nonce || !wp_verify_nonce($nonce, 'pas_2fa_verify_' . $token)) {
            wp_safe_redirect(wp_login_url());
            exit;
        }

        $data['attempts']++;
        if ($data['attempts'] > self::MAX_ATTEMPTS) {
            delete_transient($key); // ضد brute-force — بعد از ۵ تلاش، توکن باطل می‌شود
            wp_die('تعداد تلاش‌های مجاز تمام شد. دوباره وارد شوید.', 'خطا', ['response' => 429]);
        }

        $user_id = (int) $data['user_id'];
        $secret  = get_user_meta($user_id, 'pas_2fa_secret', true);
        $secret  = ($secret && class_exists('Pas_Core_Crypto')) ? Pas_Core_Crypto::unwrap_decrypt((string) $secret) : $secret;

        $verified_via = null;
        if ($secret) {
            $matched_step = Pas_2FA_Totp::verify_with_step((string) $secret, $code);
            if ($matched_step !== null) {
                $last_step = (int) get_user_meta($user_id, 'pas_2fa_last_totp_step', true);
                if ($matched_step > $last_step) {
                    update_user_meta($user_id, 'pas_2fa_last_totp_step', $matched_step);
                    $verified_via = 'totp';
                }
            }
        }
        if ($verified_via === null && Pas_2FA_Backup_Codes::verify_and_consume($user_id, $code)) {
            // کد بازیابی معتبر بود — verify_and_consume خودش آن را باطل کرده
            $verified_via = 'backup_code';
        }

        if ($verified_via !== null) {
            delete_transient($key);
            wp_set_auth_cookie($user_id, !empty($data['rememberme']));
            $user_obj = get_userdata($user_id);
            do_action('wp_login', $user_obj ? $user_obj->user_login : '', $user_obj);

            if ($verified_via === 'backup_code') {
                // یادآوری برای دفعه‌ی بعد: اگر کد بازیابی مصرف شد و کم مانده،
                // بعد از ورود به داشبورد یک نوتیس نشان بده تا کدهای جدید بسازد.
                $remaining = Pas_2FA_Backup_Codes::remaining_count($user_id);
                if ($remaining <= 2) {
                    set_transient('pas_2fa_low_codes_notice_' . $user_id, $remaining, DAY_IN_SECONDS);
                }
            }

            $redirect_to = !empty($data['redirect_to'])
                ? wp_validate_redirect((string) $data['redirect_to'], admin_url())
                : admin_url();
            wp_safe_redirect($redirect_to);
            exit;
        }

        set_transient($key, $data, self::CHALLENGE_TTL);
        wp_safe_redirect(add_query_arg([
            'action'  => 'pas_2fa_verify',
            'token'   => $token,
            'pas_err' => '1',
        ], wp_login_url()));
        exit;
    }
}
