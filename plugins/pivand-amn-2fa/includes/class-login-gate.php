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

        $token = wp_generate_password(32, false, false);
        set_transient(self::TRANSIENT_PREFIX . $token, [
            'user_id'  => $user->ID,
            'attempts' => 0,
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

        login_header('تایید دومرحله‌ای', '', null);
        ?>
        <form name="pas2fa" id="loginform" method="post" action="<?php echo esc_url(add_query_arg('action', 'pas_2fa_verify_submit', wp_login_url())); ?>">
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
        $code  = isset($_POST['code']) ? sanitize_text_field(wp_unslash($_POST['code'])) : '';
        $key   = self::TRANSIENT_PREFIX . $token;
        $data  = get_transient($key);

        if (!$data) {
            wp_safe_redirect(wp_login_url());
            exit;
        }

        $data['attempts']++;
        if ($data['attempts'] > self::MAX_ATTEMPTS) {
            delete_transient($key); // ضد brute-force — بعد از ۵ تلاش، توکن باطل می‌شود
            wp_die('تعداد تلاش‌های مجاز تمام شد. دوباره وارد شوید.', 'خطا', ['response' => 429]);
        }

        $secret = get_user_meta((int) $data['user_id'], 'pas_2fa_secret', true);
        $secret = ($secret && class_exists('Pas_Core_Crypto')) ? Pas_Core_Crypto::unwrap_decrypt((string) $secret) : $secret;

        $verified_via = null;
        if ($secret && Pas_2FA_Totp::verify((string) $secret, $code)) {
            $verified_via = 'totp';
        } elseif (Pas_2FA_Backup_Codes::verify_and_consume((int) $data['user_id'], $code)) {
            // کد بازیابی معتبر بود — verify_and_consume خودش آن را باطل کرده
            $verified_via = 'backup_code';
        }

        if ($verified_via !== null) {
            delete_transient($key);
            wp_set_auth_cookie((int) $data['user_id']);
            do_action('wp_login', get_userdata((int) $data['user_id'])->user_login ?? '', get_userdata((int) $data['user_id']));

            if ($verified_via === 'backup_code') {
                // یادآوری برای دفعه‌ی بعد: اگر کد بازیابی مصرف شد و کم مانده،
                // بعد از ورود به داشبورد یک نوتیس نشان بده تا کدهای جدید بسازد.
                $remaining = Pas_2FA_Backup_Codes::remaining_count((int) $data['user_id']);
                if ($remaining <= 2) {
                    set_transient('pas_2fa_low_codes_notice_' . (int) $data['user_id'], $remaining, DAY_IN_SECONDS);
                }
            }

            wp_safe_redirect(admin_url());
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
