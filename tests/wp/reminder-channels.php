<?php
/**
 * آزمون دو‌کاناله‌شدن یادآوری نوبت — با wp eval-file اجرا می‌شود:
 *
 *   cp tests/wp/reminder-channels.php /tmp/wpstage/ && wp eval-file reminder-channels.php
 *
 * قاعده‌ها:
 *  ۱) شمارهٔ ایرانی + ایمیل واقعی → هم پیامک هم ایمیل.
 *  ۲) شمارهٔ خارجی (+...) → فقط ایمیل (پل پیامک فقط 09xxxxxxxxx را می‌شناسد).
 *  ۳) شماره خالی → فقط ایمیل.
 *  ۴) نه شمارهٔ ایرانی، نه ایمیل قابل‌استفاده → هیچ ارسالی، ولی نوبت از چرخه
 *     خارج می‌شود تا هر ساعت بی‌نتیجه تلاش نکند.
 * پاک‌سازی: کاربران، نوبت‌ها و transientها در پایان حذف می‌شوند.
 */

if (!defined('ABSPATH')) {
    fwrite(STDERR, "این آزمون باید با wp eval-file داخل وردپرس اجرا شود.\n");
    exit(1);
}

use ZoojDarman\ClientPortal\Installer;

$GLOBALS['zdp_rem_pass'] = 0;
$GLOBALS['zdp_rem_fail'] = 0;

$check = static function (string $label, bool $ok, string $detail = ''): void {
    if ($ok) {
        $GLOBALS['zdp_rem_pass']++;
        echo "PASS  {$label}\n";
        return;
    }
    $GLOBALS['zdp_rem_fail']++;
    echo "FAIL  {$label}" . ($detail !== '' ? " — {$detail}" : '') . "\n";
};

$sms_log = [];
$mail_log = [];

add_filter('pas_send_sms', static function ($result, $mobile = '', $body_id = '', $params = []) use (&$sms_log) {
    $sms_log[] = (string) $mobile;
    return true;
}, 10, 4);

add_filter('pre_wp_mail', static function ($null, $atts) use (&$mail_log) {
    $mail_log[] = (string) ($atts['to'] ?? '');
    return true;
}, 10, 2);

// ------------------------------------------------------------- دادهٔ آزمون
$token = wp_generate_password(5, false);
$make_user = static function (string $login_suffix, string $mobile, string $email): int {
    $user_id = wp_create_user('zdp_rem_' . $login_suffix . '_' . wp_generate_password(4, false), wp_generate_password(), $email);
    if (is_wp_error($user_id)) {
        return 0;
    }
    $user = new WP_User((int) $user_id);
    $user->set_role('subscriber');
    if ($mobile !== '') {
        \ZoojDarman\ClientPortal\zdp_set_user_mobile((int) $user_id, $mobile);
    }
    if ($email !== '') {
        update_user_meta((int) $user_id, 'zdp_email', $email);
    }
    return (int) $user_id;
};

$user_both = $make_user('both', '09120000011', 'reminder.both.' . $token . '@example.com');
$user_intl = $make_user('intl', '+4915112345678', 'reminder.intl.' . $token . '@example.com');
$user_mail = $make_user('mail', '', 'reminder.mail.' . $token . '@example.com');
// ایمیل ساختگیِ موبایل (local-part = شماره) + شمارهٔ خارجی ⇒ هیچ کانالی.
// شماره تصادفی است تا اجرای دوبارهٔ آزمون به ایمیل تکراری برخورد نکند.
$none_digits = '49151' . wp_rand(100000, 999999);
$user_none   = $make_user('none', '+' . $none_digits, $none_digits . '@example.com');

$check(
    'چهار کاربر آزمون ساخته شدند',
    $user_both && $user_intl && $user_mail && $user_none,
    'ids=' . wp_json_encode([$user_both, $user_intl, $user_mail, $user_none])
);

// ---------------------------------------------------------- نوبت‌های آزمون
global $wpdb;
$table = Installer::table('bookings');
$when  = (new DateTimeImmutable('now', wp_timezone()))->modify('+2 hours');

$booking_ids = [];
foreach ([$user_both, $user_intl, $user_mail, $user_none] as $uid) {
    $wpdb->insert($table, [
        'user_id'      => $uid,
        'service_name' => 'آزمون یادآوری',
        'price'        => '0',
        'booking_date' => $when->format('Y-m-d'),
        'booking_time' => $when->format('H:i:s'),
        'status'       => 'فعال',
        'ref_id'       => 'TEST-' . wp_generate_password(6, false),
        'created_at'   => \ZoojDarman\ClientPortal\now_utc(),
        'booking_source' => 'site',
    ], ['%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s']);
    $booking_ids[$uid] = (int) $wpdb->insert_id;
}

$check('چهار نوبت آزمون ثبت شدند', count(array_filter($booking_ids)) === 4);

// ------------------------------------------------------------- اجرای کرون
update_option('zdp_appointment_reminder_enabled', true);
update_option('zdp_appointment_reminder_hours_before', 24);
update_option('zdp_sms_reminder_body_id', 'TEST-BODY-ID');

do_action('zdp_appointment_reminder_event');

// ------------------------------------------------------------------ بررسی
$sms_targets  = $sms_log;
$mail_targets = array_map('strtolower', $mail_log);

$check(
    'شمارهٔ ایرانی + ایمیل واقعی: هر دو کانال',
    in_array('09120000011', $sms_targets, true)
        && in_array(strtolower('reminder.both.' . $token . '@example.com'), $mail_targets, true),
    'sms=' . wp_json_encode($sms_log) . ' mail=' . wp_json_encode($mail_log)
);
$check(
    'شمارهٔ خارجی: فقط ایمیل، بدون پیامک',
    !in_array('+4915112345678', $sms_targets, true)
        && in_array(strtolower('reminder.intl.' . $token . '@example.com'), $mail_targets, true),
    'sms=' . wp_json_encode($sms_log)
);
$check(
    'شمارهٔ خالی: فقط ایمیل',
    in_array(strtolower('reminder.mail.' . $token . '@example.com'), $mail_targets, true),
    'mail=' . wp_json_encode($mail_log)
);
$check(
    'بدون هیچ کانال: ارسالی انجام نشد',
    !in_array('+' . $none_digits, $sms_targets, true)
        && !in_array($none_digits . '@example.com', $mail_targets, true),
    'sms=' . wp_json_encode($sms_log) . ' mail=' . wp_json_encode($mail_log)
);

$ids_sql = implode(',', array_map('intval', $booking_ids));
$sent_rows = (int) $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE id IN ($ids_sql) AND reminder_sent_at IS NOT NULL");
$check('همهٔ نوبت‌ها پس از ارسال علامت خورده‌اند', $sent_rows === 4, "sent_rows={$sent_rows}");

// --------------------------------------------------------------- پاک‌سازی
require_once ABSPATH . 'wp-admin/includes/user.php';
$wpdb->query("DELETE FROM $table WHERE id IN ($ids_sql)");
foreach ([$user_both, $user_intl, $user_mail, $user_none] as $uid) {
    if ($uid) {
        wp_delete_user($uid);
    }
}
delete_option('zdp_appointment_reminder_enabled');
delete_option('zdp_sms_reminder_body_id');

echo "\n" . $GLOBALS['zdp_rem_pass'] . '/' . ($GLOBALS['zdp_rem_pass'] + $GLOBALS['zdp_rem_fail']) . " PASS\n";
exit($GLOBALS['zdp_rem_fail'] > 0 ? 1 : 0);
