<?php
/**
 * فاز ۱۱ — آزمون نفوذ منطقی بین نقش‌ها و IDOR روی سایت واقعی (MySQL).
 * اجرا: php wp-cli.phar --path=web --allow-root eval-file tests/phase11/idor-roles.php
 * پیش‌نیاز: وب‌سرور روی http://127.0.0.1:8090 در حال اجرا باشد.
 */
if (!defined('ABSPATH')) { exit(1); }
$SITE = 'http://127.0.0.1:8090';
function res($name, $verdict, $detail) {
    static $R = ['PASS'=>0,'FAIL'=>0,'FINDING'=>0,'BLOCKED'=>0,'ACCEPTED'=>0];
    $R[$verdict] = ($R[$verdict] ?? 0) + 1;
    $GLOBALS['p11_totals'] = $R;
    printf("%-8s | %-46s | %s\n", $verdict, $name, $detail);
}
function ensure_user($login, $role, $email) {
    $u = get_user_by('login', $login);
    if ($u) { return (int) $u->ID; }
    $id = wp_insert_user(['user_login'=>$login,'user_pass'=>wp_generate_password(20),'role'=>$role,'user_email'=>$email,'display_name'=>$login]);
    return is_wp_error($id) ? 0 : (int) $id;
}
function cookies_for($uid) {
    $exp = time() + 7200;
    return [
        AUTH_COOKIE               => wp_generate_auth_cookie($uid, $exp, 'auth'),
        SECURE_AUTH_COOKIE        => wp_generate_auth_cookie($uid, $exp, 'secure_auth'),
        LOGGED_IN_COOKIE          => wp_generate_auth_cookie($uid, $exp, 'logged_in'),
    ];
}
function req($method, $url, $cookies, $body = []) {
    $args = ['method'=>$method,'timeout'=>20,'redirection'=>0,'headers'=>['Cookie'=>implode('; ', array_map(fn($k,$v)=>"$k=$v", array_keys($cookies), $cookies))],'sslverify'=>false];
    if ($body) { $args['body'] = $body; }
    $r = wp_remote_request($url, $args);
    if (is_wp_error($r)) { return ['status'=>0,'body'=>'ERR: '.$r->get_error_message()]; }
    return ['status'=>(int) wp_remote_retrieve_response_code($r), 'body'=>(string) wp_remote_retrieve_body($r)];
}
wp_set_current_user(1);

// ── داده‌های آزمون
$admin     = 1;
$therapist = ensure_user('p11_therapist_a', 'pas_cf_therapist', 'ta@example.test');
$other     = ensure_user('p11_therapist_b', 'pas_cf_therapist', 'tb@example.test');
$client    = ensure_user('p11_client', 'subscriber', 'cl@example.test');

$phoneA = '0912' . random_int(1000000, 9999999);
$phoneB = '0912' . random_int(1000000, 9999999);
$caseA = Pas_CF_Case_File::create(['phone'=>$phoneA,'full_name'=>'پرونده-الف-محرمانه','assigned_therapist'=>$therapist,'gender'=>'زن']);
$caseB = Pas_CF_Case_File::create(['phone'=>$phoneB,'full_name'=>'پرونده-ب-محرمانه','assigned_therapist'=>$other,'gender'=>'مرد']);
$noteA = Pas_CF_Case_File::add_note($caseA, date('Y-m-d'), 'یادداشت-محرمانه-الف', 1, ['status'=>'done']);
$noteB = Pas_CF_Case_File::add_note($caseB, date('Y-m-d'), 'یادداشت-محرمانه-ب', 1, ['status'=>'done']);

printf("setup: caseA=%d caseB=%d noteB=%d therapist=%d other=%d client=%d\n%s\n", $caseA,$caseB,$noteB,$therapist,$other,$client,str_repeat('-',110));

$note_row = (int) $GLOBALS['wpdb']->get_var($GLOBALS['wpdb']->prepare("SELECT COUNT(*) FROM {$GLOBALS['wpdb']->prefix}pas_cf_notes WHERE id=%d AND case_id=%d", $noteB, $caseB));
res('regression: add_note returns the note id', $note_row === 1 ? 'PASS' : 'FINDING', "noteB=$noteB present_in_notes=".($note_row?'yes':'no'));


$ckA = cookies_for($therapist); $ckB = cookies_for($other); $ckC = cookies_for($client); $ckAdmin = cookies_for($admin);

// کد وضعیت HTTP: پروندهٔ ناموجود باید 4xx باشد، نه 500
$n404 = nonce_for($therapist, $ckA, 'pas_cf_print_case');
$r = req('GET', "$SITE/wp-admin/admin-post.php?action=pas_cf_print_case&case_id=999999&_wpnonce=$n404", $ckA);
res('http: missing case yields 4xx not 500', ($r['status'] >= 400 && $r['status'] < 500) ? 'PASS' : 'FINDING', "http={$r['status']}");

// nonce به‌عنوان همان کاربر و همان توکن نشست
function nonce_for($uid, $cookies, $action) {
    $prev = $_COOKIE;
    $_COOKIE[LOGGED_IN_COOKIE] = $cookies[LOGGED_IN_COOKIE];
    wp_set_current_user($uid);
    $n = wp_create_nonce($action);
    wp_set_current_user(1);
    $_COOKIE = $prev;
    return $n;
}

// ۱) IDOR صفحهٔ پرونده: درمانگر A نباید پروندهٔ B را ببیند
$r = req('GET', "$SITE/wp-admin/admin.php?page=pas-case-file-new&case_id=$caseB", $ckA);
$leak = strpos($r['body'], 'پرونده-ب-محرمانه') !== false || strpos($r['body'], 'یادداشت-محرمانه-ب') !== false;
$deniedMsg = (strpos($r['body'], 'تخصیص داده نشده') !== false) || (strpos($r['body'], 'دسترسی غیرمجاز') !== false);
res('IDOR: case page of another therapist', (!$leak && ($r['status']===403 || $deniedMsg)) ? 'PASS' : ($leak?'FINDING':'FAIL'), "http={$r['status']} leak=".($leak?'yes':'no')." denied_msg=".($deniedMsg?'yes':'no'));

// ۲) کنترل مثبت: پروندهٔ خودش باز می‌شود
$r = req('GET', "$SITE/wp-admin/admin.php?page=pas-case-file-new&case_id=$caseA", $ckA);
res('control: own case page opens', (strpos($r['body'],'پرونده-الف-محرمانه')!==false && $r['status']===200)?'PASS':'FAIL', "http={$r['status']} own_name=".(strpos($r['body'],'پرونده-الف-محرمانه')!==false?'yes':'no'));

// ۳) فهرست پرونده‌ها فقط پروندهٔ خودش
$r = req('GET', "$SITE/wp-admin/admin.php?page=pas-case-file", $ckA);
$leak = strpos($r['body'], 'پرونده-ب-محرمانه') !== false;
res('scope: case list hides other cases', $leak ? 'FINDING':'PASS', "http={$r['status']} leak=".($leak?'yes':'no'));

// ۴) چاپ پروندهٔ دیگران با nonce معتبر خودش (این هندلر case_id را از GET می‌خواند)
$n = nonce_for($therapist, $ckA, 'pas_cf_print_case');
$r = req('GET', "$SITE/wp-admin/admin-post.php?action=pas_cf_print_case&case_id=$caseB&_wpnonce=$n", $ckA);
$leak = (strpos($r['body'], 'یادداشت-محرمانه-ب') !== false) || (strpos($r['body'], 'پرونده-ب-محرمانه') !== false);
res('IDOR: print other case', ($leak?'FINDING':($r['status']===403?'PASS':'FAIL')), "http={$r['status']} leak=".($leak?'yes':'no')." msg=".(strpos($r['body'],'تخصیص داده نشده')!==false?'denied':'other'));

// ۵) حذف یادداشت پروندهٔ دیگران
$n = nonce_for($therapist, $ckA, 'pas_cf_delete_note');
$r = req('POST', "$SITE/wp-admin/admin-post.php", $ckA, ['action'=>'pas_cf_delete_note','note_id'=>$noteB,'case_id'=>$caseB,'_wpnonce'=>$n]);
$survived = (int) $GLOBALS['wpdb']->get_var($GLOBALS['wpdb']->prepare("SELECT COUNT(*) FROM {$GLOBALS['wpdb']->prefix}pas_cf_notes WHERE id=%d", $noteB)) === 1;
res('IDOR: delete note of other case', ($survived && strpos($r['body'],'محرمانه-ب')===false)?'PASS':'FINDING', "http={$r['status']} note_survived=".($survived?'yes':'no'));

// ۶) ذخیرهٔ پروندهٔ دیگران (تغییر نام)
$n = nonce_for($therapist, $ckA, 'pas_cf_save_case');
$r = req('POST', "$SITE/wp-admin/admin-post.php", $ckA, ['action'=>'pas_cf_save_case','case_id'=>$caseB,'full_name'=>'تغییر-غیرمجاز','status'=>'فعال','_wpnonce'=>$n]);
$after = Pas_CF_Case_File::get($caseB);
res('IDOR: save other case', ($after->full_name === 'پرونده-ب-محرمانه')?'PASS':'FINDING', "http={$r['status']} name_now=".$after->full_name);

// ۷) خروجی CSV فقط پروندهٔ خودش
$n = nonce_for($therapist, $ckA, 'pas_cf_export_csv');
$r = req('POST', "$SITE/wp-admin/admin-post.php", $ckA, ['action'=>'pas_cf_export_csv','_wpnonce'=>$n]);
$leak = strpos($r['body'], 'پرونده-ب-محرمانه') !== false;
res('scope: CSV export', $leak?'FINDING':'PASS', "http={$r['status']} bytes=".strlen($r['body'])." leak=".($leak?'yes':'no'));

// ۸) ارتقای دسترسی: درمانگر به صفحات مدیر
foreach (['pas-core-health'=>'health dashboard','pas-sms-bridge'=>'sms settings','zdp-clients'=>'client portal admin'] as $slug=>$label) {
    $r = req('GET', "$SITE/wp-admin/admin.php?page=$slug", $ckA);
    $body_ok = ($r['status'] === 403) || (strpos($r['body'],'دسترسی غیرمجاز')!==false) || (strpos($r['body'],'Sorry, you are not allowed')!==false);
    res("escalation: therapist -> $label", $body_ok?'PASS':'FINDING', "http={$r['status']}");
}

// ۹) ادمین به پروندهٔ هر دو دسترسی دارد (کنترل)
$r = req('GET', "$SITE/wp-admin/admin.php?page=pas-case-file-new&case_id=$caseB", $ckAdmin);
res('control: admin sees any case', strpos($r['body'],'پرونده-ب-محرمانه')!==false?'PASS':'FAIL', "http={$r['status']}");

// ۱۰) مراجع (subscriber) به پیشخوان پرونده و صفحات مدیر دسترسی ندارد
$r1 = req('GET', "$SITE/wp-admin/admin.php?page=pas-case-file-new&case_id=$caseA", $ckC);
$r2 = req('GET', "$SITE/wp-admin/admin.php?page=pas-case-file", $ckC);
$denied = !in_array($r1['status'],[200],true) && strpos($r1['body'],'پرونده-الف-محرمانه')===false;
res('client cannot open case files', $denied?'PASS':'FINDING', "case_http={$r1['status']} list_http={$r2['status']}");

// ۱۱) مراجع نمی‌تواند از admin-post حذف یادداشت کند
$n = nonce_for($client, $ckC, 'pas_cf_delete_note');
$r = req('POST', "$SITE/wp-admin/admin-post.php", $ckC, ['action'=>'pas_cf_delete_note','note_id'=>$noteA,'case_id'=>$caseA,'_wpnonce'=>$n]);
$survive = (int) $GLOBALS['wpdb']->get_var($GLOBALS['wpdb']->prepare("SELECT COUNT(*) FROM {$GLOBALS['wpdb']->prefix}pas_cf_notes WHERE id=%d", $noteA)) === 1;
res('client cannot delete notes', ($survive && $r['status']!==200)?'PASS':'FINDING', "http={$r['status']} note_survived=".($survive?'yes':'no'));

// ۱۲) مراجع نمی‌تواند از admin-post پرونده بسازد
$r = req('POST', "$SITE/wp-admin/admin-post.php", $ckC, ['action'=>'pas_cf_save_case','case_id'=>$caseA,'full_name'=>'x','_wpnonce'=>wp_create_nonce('pas_cf_save_case')]);
$after = Pas_CF_Case_File::get($caseA);
res('client cannot save case', ($after->full_name === 'پرونده-الف-محرمانه')?'PASS':'FINDING', "http={$r['status']}");

// ۱۳) REST گزارش آزمون دیگری بدون توکن
$attempt = (int) $GLOBALS['wpdb']->get_var("SELECT id FROM {$GLOBALS['wpdb']->prefix}psy_attempts ORDER BY id DESC LIMIT 1");
$r = req('GET', "$SITE/?rest_route=/psy/v1/report/" . max(1,$attempt), $ckA);
res('IDOR: quiz report without token', in_array($r['status'],[401,403])?'PASS':'FINDING', "http={$r['status']} attempt=".$attempt." body=".substr(trim($r['body']),0,40));

// ۱۴) جست‌وجوی نام مراجع (پیشخوان) — درمانگر مجاز است ولی فقط نام
$prev = $_COOKIE; $_COOKIE[LOGGED_IN_COOKIE] = $ckA[LOGGED_IN_COOKIE]; wp_set_current_user($therapist);
$nLookup = wp_create_nonce('pas_core_lookup_client_name'); wp_set_current_user(1); $_COOKIE = $prev;
$r = req('POST', "$SITE/wp-admin/admin-ajax.php", $ckA, ['action'=>'pas_core_lookup_client_name','nonce'=>$nLookup,'phone'=>$phoneB]);
$hasName = strpos($r['body'],'پرونده-ب-محرمانه')!==false;
res('lookup: therapist sees name of any client', $r['status']===200?'ACCEPTED':'PASS', "http={$r['status']} name_returned=".($hasName?'yes':'no')." body=".substr(trim($r['body']),0,60));

// ۱۵) همان جست‌وجو بدون nonce
$r = req('POST', "$SITE/wp-admin/admin-ajax.php", $ckA, ['action'=>'pas_core_lookup_client_name','phone'=>$phoneB]);
res('lookup: without nonce rejected', ($r['status']===400 || strpos($r['body'],'-1')!==false)?'PASS':'FINDING', "http={$r['status']} body=".substr(trim($r['body']),0,20));

// ۱۶) بدون ورود: صفحات پرونده
$r = req('GET', "$SITE/wp-admin/admin.php?page=pas-case-file-new&case_id=$caseA", []);
res('anonymous: case page blocked', $r['status']!==200?'PASS':'FINDING', "http={$r['status']} redirect_to_login=".(strpos($r['body'],'wp-login')!==false?'yes':'no'));

echo str_repeat('-',110)."\n";
printf("TOTALS: %s\n", json_encode($GLOBALS['p11_totals'] ?? []));
echo "wpdb last_error: " . (($GLOBALS['wpdb']->last_error ?? '') ?: 'none') . "\n";
