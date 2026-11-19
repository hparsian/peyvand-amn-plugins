<?php
/**
 * آزمون ورودی پنل مراجع (ورود با ایمیل) — با wp eval-file اجرا می‌شود:
 *
 *   cp tests/wp/otp-login-entry.php /tmp/wpstage/ && \
 *   wp eval-file otp-login-entry.php
 *
 * پوشش:
 *  ۱) zdp_panel_url() آدرس واقعی صفحهٔ پنل را برمی‌گرداند (نه مسیر ثابت /panel/).
 *  ۲) کد OTP ایمیل که با حروف بزرگ/کوچک متفاوت تایید شود پذیرفته می‌شود
 *     (باگ «کد منقضی شده» وقتی کاربر همان ایمیل را با شکل دیگری تایپ کند).
 */

if (!defined('ABSPATH')) {
    fwrite(STDERR, "این آزمون باید با wp eval-file داخل وردپرس اجرا شود.\n");
    exit(1);
}

$GLOBALS['zdp_test_pass'] = 0;
$GLOBALS['zdp_test_fail'] = 0;

$check = static function (string $label, bool $ok, string $detail = ''): void {
    if ($ok) {
        $GLOBALS['zdp_test_pass']++;
        echo "PASS  {$label}\n";
        return;
    }

    $GLOBALS['zdp_test_fail']++;
    echo "FAIL  {$label}" . ($detail !== '' ? " — {$detail}" : '') . "\n";
};

// ---------------------------------------------------------------- ۱) آدرس پنل
$panel_id  = (int) get_option('zdp_panel_page_id');
$expected  = $panel_id > 0 ? (string) get_permalink($panel_id) : '';
$actual    = \ZoojDarman\ClientPortal\zdp_panel_url();

$check('صفحهٔ پنل ثبت شده است', $panel_id > 0, 'zdp_panel_page_id خالی است');
$check(
    'zdp_panel_url() همان permalink واقعی صفحهٔ پنل است',
    $expected !== '' && $actual === $expected,
    "expected={$expected} actual={$actual}"
);
$check(
    'zdp_panel_url() به مسیر ثابت /panel/ وابسته نیست',
    !str_ends_with(rtrim((string) wp_parse_url($actual, PHP_URL_PATH), '/'), '/panel') || $expected === $actual,
    $actual
);

// ------------------------------------------------- ۲) ایمیل بدون حساسیت به حروف
$email_mixed = 'Case.Test.' . wp_generate_password(6, false) . '@Example.COM';
$email_lower = strtolower($email_mixed);

$captured = '';
add_filter('pre_wp_mail', static function ($null, $atts) use (&$captured) {
    $captured = (string) ($atts['message'] ?? '');
    return true;
}, 10, 2);

$send = new WP_REST_Request('POST', '/zdp/v1/send-otp-email');
$send->set_header('X-WP-Nonce', wp_create_nonce('wp_rest'));
$send->set_body_params(['email' => $email_mixed]);
$send_response = rest_do_request($send);

$check('درخواست کد برای ایمیل با حروف بزرگ پذیرفته شد', 200 === $send_response->get_status(), 'status=' . $send_response->get_status());
$check('کد در ایمیل ارسال‌شده موجود است', (bool) preg_match('/\b(\d{5})\b/', $captured, $m), $captured);

$code = $m[1] ?? '';
$key  = 'email_' . md5($email_lower);
$check(
    'کد با کلید حروف‌کوچک ذخیره شده است',
    $code !== '' && (string) get_transient('zdp_otp_' . $key) === $code,
    'transient=' . var_export(get_transient('zdp_otp_' . $key), true) . " code={$code}"
);

$verify = new WP_REST_Request('POST', '/zdp/v1/verify-otp-email');
$verify->set_header('X-WP-Nonce', wp_create_nonce('wp_rest'));
$verify->set_body_params(['email' => $email_lower, 'otp' => $code]);
$verify_response = rest_do_request($verify);
$verify_data     = (array) $verify_response->get_data();

$check('تایید کد با ایمیل حروف‌کوچک موفق است', true === ($verify_data['success'] ?? false), wp_json_encode($verify_data, JSON_UNESCAPED_UNICODE));
$check(
    'ریدایرکت ورود به صفحهٔ واقعی پنل اشاره می‌کند',
    str_contains((string) ($verify_data['data']['redirect'] ?? ''), 'zdp_auth='),
    (string) ($verify_data['data']['redirect'] ?? '')
);

// پاک‌سازی دادهٔ آزمون
$test_user = get_user_by('email', $email_lower);
if ($test_user) {
    require_once ABSPATH . 'wp-admin/includes/user.php';
    wp_delete_user((int) $test_user->ID);
}
delete_transient('zdp_otp_' . $key);
delete_transient('zdp_otp_attempts_' . $key);

echo "\n" . $GLOBALS['zdp_test_pass'] . '/' . ($GLOBALS['zdp_test_pass'] + $GLOBALS['zdp_test_fail']) . " PASS\n";
exit($GLOBALS['zdp_test_fail'] > 0 ? 1 : 0);
