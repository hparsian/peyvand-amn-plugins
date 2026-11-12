<?php
/**
 * فاز ۱۱ — آزمون قطع و وصل ارتباط بین افزونه‌ها.
 *
 * هر افزونه به‌ترتیب غیرفعال می‌شود و همان مسیرهای HTTP که به آن وابسته‌اند
 * دوباره درخواست می‌شوند. معیار قبولی:
 *   - هیچ پاسخ PHP Fatal / «critical error» / Uncaught برنگردد
 *   - صفحه یا باز شود (۲۰۰) یا با پیام شفاف رد شود (۳۰۲/۴۰۳)، نه ۵۰۰
 *   - داده‌ها دست‌نخورده بمانند (شمارش پرونده/یادداشت قبل و بعد برابر)
 *
 * اجرا: php wp-cli.phar --path=web --allow-root eval-file plugin-isolation.php
 */

if (!defined('ABSPATH')) {
    exit(1);
}

global $wpdb;

$SITE   = 'http://127.0.0.1:8090';
$admin  = get_user_by('login', 'admin');
$exp    = time() + 3600;
$cookie = AUTH_COOKIE . '=' . wp_generate_auth_cookie($admin->ID, $exp, 'auth')
        . '; ' . LOGGED_IN_COOKIE . '=' . wp_generate_auth_cookie($admin->ID, $exp, 'logged_in');

$pages = [
    'case-file'   => '/wp-admin/admin.php?page=pas-case-file-new',
    'core-health' => '/wp-admin/admin.php?page=pas-core-health',
    'sms'         => '/wp-admin/admin.php?page=pas-sms-bridge',
    'quiz'        => '/wp-admin/admin.php?page=psy-quiz-reports',
    'portal'      => '/wp-admin/admin.php?page=zdp-admin',
    'front'       => '/',
];

$fatal_markers = ['Fatal error', 'There has been a critical error', 'Uncaught ', 'Parse error', 'Warning:', 'Notice:'];

$plugins = [
    'pivand-amn-core'             => ['case-file', 'core-health', 'quiz', 'portal'],
    'pivand-amn-sms-bridge'       => ['sms', 'case-file'],
    'pivand-amn-quiz-enterprise'  => ['quiz', 'case-file'],
    'pivand-amn-client-portal'    => ['portal', 'case-file'],
    'pivand-amn-case-file'        => ['case-file', 'portal', 'quiz'],
];

$cases_before = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}pas_cf_cases");
$notes_before = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}pas_cf_notes");

$pass = 0;
$fail = 0;
$out  = [];

function p11_get(string $url, string $cookie): array
{
    $r = wp_remote_get($url, ['timeout' => 25, 'redirection' => 0, 'headers' => ['Cookie' => $cookie]]);
    if (is_wp_error($r)) {
        return ['status' => 0, 'body' => $r->get_error_message()];
    }
    return ['status' => (int) wp_remote_retrieve_response_code($r), 'body' => (string) wp_remote_retrieve_body($r)];
}

foreach ($plugins as $plugin => $deps) {
    $file = $plugin . '/' . $plugin . '.php';
    if (!is_plugin_active($file)) {
        $out[] = sprintf('SKIP  | %-28s | already inactive', $plugin);
        continue;
    }

    deactivate_plugins($file);
    // وضعیت فعال/غیرفعال در دیتابیس ذخیره شده؛ هر درخواست HTTP تازه آن را می‌خواند.
    foreach ($deps as $page) {
        $r      = p11_get($SITE . $pages[$page], $cookie);
        $fatal  = false;
        foreach ($fatal_markers as $m) {
            if (stripos($r['body'], $m) !== false) {
                $fatal = true;
                break;
            }
        }
        $notice = (bool) preg_match('/هسته|فعال نیست|غیرفعال|در دسترس نیست|نصب نشده/u', $r['body']);
        $ok     = !$fatal && in_array($r['status'], [200, 302, 403], true);
        $ok ? $pass++ : $fail++;
        $out[] = sprintf(
            '%-5s | %-28s | page=%-11s http=%-3d fatal=%s notice=%s',
            $ok ? 'PASS' : 'FAIL',
            $plugin . ' OFF',
            $page,
            $r['status'],
            $fatal ? 'YES' : 'no',
            $notice ? 'yes' : 'no'
        );
    }
    activate_plugin($file);

    // همان صفحه پس از فعال‌سازی دوباره باید سالم باشد (قطب مقابل آزمون)
    $r     = p11_get($SITE . $pages[$deps[0]], $cookie);
    $fatal = false;
    foreach ($fatal_markers as $m) {
        if (stripos($r['body'], $m) !== false) {
            $fatal = true;
            break;
        }
    }
    $ok = !$fatal && in_array($r['status'], [200, 302, 403], true);
    $ok ? $pass++ : $fail++;
    $out[] = sprintf('%-5s | %-28s | page=%-11s http=%-3d fatal=%s (restored)', $ok ? 'PASS' : 'FAIL', $plugin . ' ON', $deps[0], $r['status'], $fatal ? 'YES' : 'no');
}

$cases_after = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}pas_cf_cases");
$notes_after = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}pas_cf_notes");
$data_ok     = $cases_before === $cases_after && $notes_before === $notes_after;
$data_ok ? $pass++ : $fail++;

echo str_repeat('-', 116), "\n", implode("\n", $out), "\n", str_repeat('-', 116), "\n";
printf(
    "%-5s | %-28s | cases %d→%d notes %d→%d\n",
    $data_ok ? 'PASS' : 'FAIL',
    'data intact across deactivations',
    $cases_before,
    $cases_after,
    $notes_before,
    $notes_after
);
printf("TOTALS: PASS=%d FAIL=%d\n", $pass, $fail);
echo 'wpdb last_error: ', $wpdb->last_error ?: 'none', "\n";
