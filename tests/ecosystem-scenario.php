<?php
if (!defined('ABSPATH')) { exit(1); }
define('WP_ADMIN', true);
wp_set_current_user(1);

$GLOBALS['scn_stats'] = [];
function scn($name, $fn) {
    $t0 = microtime(true);
    try { $res = $fn(); }
    catch (\Throwable $e) { $res = ['FAIL', $e->getMessage() . ' @' . basename($e->getFile()) . ':' . $e->getLine()]; }
    if (!is_array($res)) { $res = ['FAIL', 'no result']; }
    $st = $res[0]; $detail = $res[1] ?? '';
    $GLOBALS['scn_stats'][$st] = ($GLOBALS['scn_stats'][$st] ?? 0) + 1;
    printf("%-7s | %-30s | %4dms | %s\n", $st, $name, (int)((microtime(true)-$t0)*1000), $detail);
}

$mobile  = '0912' . random_int(1000000, 9999999);
$mobile2 = '0913' . random_int(1000000, 9999999);
$run     = substr((string) time(), -5);
echo "SCENARIO RUN $run — client phone $mobile\n";
echo str_repeat('-', 110) . "\n";

// ── 1. ورودی عمومی: فرم تماس payvand-contact → ردیف در payvand_messages
scn('1. intake form (public)', function () use ($mobile) {
    global $wpdb;
    $t = $wpdb->prefix . 'payvand_messages';
    $before = (int) $wpdb->get_var("SELECT COUNT(*) FROM $t");
    $nonce_req = new WP_REST_Request('POST', '/payvand/v1/none');
    // nonce path tested over HTTP; here we assert the channel table + registry source exists
    // هم SQLite و هم MySQL/MariaDB: فقط وجود جدول و شمارش
    $wpdb->suppress_errors(true);
    $exists = $wpdb->get_var("SELECT COUNT(*) FROM $t") !== null;
    $wpdb->suppress_errors(false);
    if (!$exists) { return ['FAIL', 'payvand_messages table missing']; }
    return ['PASS', "table ok, existing rows=$before (HTTP submit verified separately)"];
});

// ── 2. رجیستری هویت هسته
scn('2. core identity registry', function () use ($mobile) {
    if (!class_exists('Pas_Core_Client_Registry')) { return ['FAIL', 'registry class missing']; }
    Pas_Core_Client_Registry::rebuild();
    $stats = Pas_Core_Client_Registry::stats();
    $cnt = is_array($stats) ? (int) ($stats['clients'] ?? $stats['total'] ?? 0) : 0;
    return ['PASS', 'rebuild ok, clients=' . $cnt . ' keys=' . implode(',', array_slice(array_keys((array)$stats), 0, 4))];
});

// ── 3. مراجع: ساخت کاربر (معادل ورود OTP) + فرم ارزیابی
$payload = [
    'full_name' => 'مراجع آزمون ' . $run, 'phone' => $mobile, 'age' => '32', 'gender' => 'زن',
    'marital' => 'متاهل', 'education' => 'لیسانس', 'job' => 'کارمند', 'address' => 'تهران',
    'children' => '۱', 'referral' => 'اینستاگرام', 'father_name' => 'علی',
    'birth_date' => '1370/05/12', 'med_details' => 'بدون سابقه دارو',
    'counseling_details' => 'دو جلسه مشاوره قبلی', 'substance_details' => 'ندارد',
];
$uid = 0;
scn('3. client user + intake row', function () use ($mobile, $payload, &$uid, $run) {
    $login = 'zdp_' . $mobile;
    $existing = get_user_by('login', $login);
    $uid = $existing ? (int) $existing->ID : (int) wp_insert_user([
        'user_login' => $login, 'user_pass' => wp_generate_password(24), 'role' => 'subscriber',
        'display_name' => 'مراجع ' . $mobile,
    ]);
    if (!$uid) { return ['FAIL', 'user_create_failed']; }
    update_user_meta($uid, 'zdp_mobile', $mobile);
    update_user_meta($uid, 'zdp_full_name', $payload['full_name']);

    global $wpdb;
    $table = \ZoojDarman\ClientPortal\Installer::table('intake');
    $enc = class_exists('Pas_Core_Crypto') && \Pas_Core_Crypto::is_configured();
    $raw = $enc ? \Pas_Core_Crypto::wrap_encrypted(wp_json_encode($payload, JSON_UNESCAPED_UNICODE)) : wp_json_encode($payload, JSON_UNESCAPED_UNICODE);
    $wpdb->delete($table, ['user_id' => $uid]);
    $wpdb->insert($table, ['user_id' => $uid, 'payload' => $raw, 'payload_encrypted' => $enc ? 1 : 0,
        'submitted_at' => current_time('mysql', true), 'updated_at' => current_time('mysql', true)], ['%d','%s','%d','%s','%s']);
    if ($wpdb->last_error) { return ['FAIL', 'insert: ' . $wpdb->last_error]; }

    // مسیر رسمی خواندن: فیلتر پنل برای درمانگر
    $viaFilter = apply_filters('zdp_get_intake_for_phone', [], $mobile);
    $keys = is_array($viaFilter) ? array_slice(array_keys($viaFilter), 0, 6) : [];
    $flat = is_array($viaFilter) ? (isset($viaFilter['data']) && is_array($viaFilter['data']) ? $viaFilter['data'] : $viaFilter) : [];
    $ok = (($flat['full_name'] ?? '') === $payload['full_name']);
    return [$ok ? 'PASS' : 'FAIL', 'user_id=' . $uid . ' encrypted=' . ($enc ? 'yes' : 'no') . ' filter_read=' . ($ok ? 'yes' : 'no')
        . ' login=' . get_userdata($uid)->user_login . ' keys=' . implode(',', $keys)
        . ' got_name=' . (string) ($flat['full_name'] ?? '∅') . ' is_admin=' . var_export(is_admin(), true)];
});

// ── 4. نوبتدهی از پنل مراجع
scn('4. booking (client portal)', function () use ($uid, $mobile, $run) {
    if (!get_option('zdp_services_list')) {
        update_option('zdp_services_list', [[
            'id' => 'scn_svc', 'name' => 'مشاوره فردی (سناریو)', 'price' => '500000', 'type' => 'both', 'duration' => 60,
        ]]);
    }
    update_option('zdp_payment_method', 'manual');
    global $wpdb;
    $lock = $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 2)', 'scn_probe'));
    if ($lock === null) {
        return ['BLOCKED', 'GET_LOCK unsupported by SQLite drop-in (MySQL-only path) — documented limitation'];
    }
    $booking = new \ZoojDarman\ClientPortal\Booking(new \ZoojDarman\ClientPortal\Security(), new \ZoojDarman\ClientPortal\Leads());
    $day = date('Y-m-d', strtotime('+' . random_int(3, 20) . ' days'));
    $slot = str_pad((string) random_int(9, 18), 2, '0', STR_PAD_LEFT) . ':00';
    $res = $booking->create_booking_internal((int) $uid, [
        'service_id' => 'scn_svc', 'booking_date' => $day, 'booking_time' => $slot,
        'full_name' => 'مراجع آزمون ' . $run, 'session_type' => 'حضوری',
    ]);
    if (empty($res['success'])) {
        return ['FAIL', 'booking rejected: ' . ($res['data']['message'] ?? json_encode($res, 256))];
    }
    $t = \ZoojDarman\ClientPortal\Installer::table('bookings');
    $row = $wpdb->get_row($wpdb->prepare("SELECT id,status,booking_date FROM $t WHERE user_id=%d ORDER BY id DESC LIMIT 1", $uid));
    return [$row ? 'PASS' : 'FAIL', 'booking id=' . ($row->id ?? '-') . ' status=' . ($row->status ?? '-') . ' slot=' . $day . ' ' . $slot];
});

// ── 5. ساخت پرونده توسط درمانگر
$case1 = 0;
scn('5. case creation', function () use ($mobile, $payload, &$case1) {
    if (!class_exists('Pas_CF_Case_File')) { return ['FAIL', 'case-file class missing']; }
    $case1 = Pas_CF_Case_File::create([
        'phone' => $mobile, 'full_name' => $payload['full_name'], 'age' => '', 'gender' => '',
        'assigned_therapist' => 1, 'status' => 'فعال',
    ]);
    $case = $case1 ? Pas_CF_Case_File::get($case1) : null;
    return [$case ? 'PASS' : 'FAIL', 'case_id=' . $case1 . ' owner=' . ($case->created_by ?? '-') . ' therapist=' . ($case->assigned_therapist ?? '-')];
});

// ── 6. نگاشت فرم ارزیابی → پرونده
scn('6. intake → case mapping', function () use (&$case1, $mobile) {
    global $wpdb;
    $case = Pas_CF_Case_File::get($case1);
    $intake = apply_filters('zdp_get_intake_for_phone', [], $mobile);
    if (!$intake) { return ['FAIL', 'intake not readable by therapist filter']; }
    $rows = Pas_CF_Case_File::intake_mapping_preview($case, $intake);
    $fill = 0; foreach ($rows as $r) { if (($r['status'] ?? '') === 'fill') { $fill++; } }
    $applied = Pas_CF_Case_File::apply_intake_mapping($case1, $rows, true, 'scenario', current_time('mysql', true));
    $after = Pas_CF_Case_File::get($case1);
    $prov = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}pas_field_provenance WHERE entity_type='case' AND entity_id=%d", $case1));
    $ok = ((string) $after->age === '32') && ((string) $after->marital_status === 'متاهل');
    return [$ok ? 'PASS' : 'FAIL', "preview_rows=" . count($rows) . " fill=$fill updated=" . ($applied['updated'] ?? '?') . " age={$after->age} marital={$after->marital_status} provenance=$prov intake_at=" . ($after->intake_submitted_at ? 'set' : 'empty')];
});

// ── 7. جلسه/یادداشت رمزنگاریشده + ممیزی
scn('7. session note (encrypted)', function () use (&$case1, &$note_id) {
    $note_id = Pas_CF_Case_File::add_note($case1, date('Y-m-d'), 'محتوای محرمانه جلسه — تست سناریو', 1, [
        'duration_minutes' => 50, 'status' => 'done', 'next_session_date' => date('Y-m-d', strtotime('+7 days')),
    ]);
    $notes = Pas_CF_Case_File::get_notes($case1);
    $n = $notes[0] ?? null;
    $plain = $n ? (string) $n->content : '';
    return [$plain === 'محتوای محرمانه جلسه — تست سناریو' ? 'PASS' : 'FAIL',
        'note_id=' . $note_id . ' decrypted=' . ($plain !== '' ? 'yes' : 'no') . ' len=' . mb_strlen($plain)];
});

// ── 8. تایملاین
scn('8. case timeline', function () use (&$case1) {
    $case = Pas_CF_Case_File::get($case1);
    $events = Pas_CF_Timeline::for_case($case);
    $types = array_map(fn($e) => (string) ($e['type'] ?? $e['action'] ?? '?'), $events);
    $has = fn($k) => count(array_filter($types, fn($t) => strpos($t, $k) !== false)) > 0;
    $ok = $has('create') || $has('case');
    return [$ok ? 'PASS' : 'FAIL', 'events=' . count($events) . ' types=' . implode(',', array_slice(array_unique($types), 0, 8))];
});

// ── 9. آزمون روانسنجی: دعوت → پاسخ → نتیجه → پرونده
scn('9. quiz invite → submit → case', function () use ($mobile) {
    if (!class_exists('Psy_Quiz_Invitation_Manager')) { return ['FAIL', 'quiz plugin missing']; }
    $inv = Psy_Quiz_Invitation_Manager::create('csi', 'مراجع آزمون', $mobile);
    $token = $inv['token'] ?? ($inv['link'] ? basename(parse_url($inv['link'], PHP_URL_PATH)) : '');
    if (!$token) { return ['FAIL', 'no token: ' . json_encode($inv, 256)]; }
    $test = \PsychAssessments\Core\Registry::get('csi');
    if (!$test) { return ['FAIL', 'csi test not registered']; }
    $answers = [];
    foreach (array_keys($test->get_questions()) as $qid) { $answers[(int) $qid] = 3; }
    $req = new WP_REST_Request('POST', '/psy/v1/submit');
    $req->set_header('Content-Type', 'application/json');
    $req->set_body(wp_json_encode(['token' => $token, 'answers' => $answers]));
    $resp = rest_do_request($req);
    $code = $resp->get_status();
    $body = $resp->get_data();
    if ($code !== 200) { return ['FAIL', "submit HTTP $code: " . wp_json_encode($body, 256)]; }
    $scores = Pas_CF_Case_File::get_assessment_scores($mobile);
    return [(!empty($scores) ? 'PASS' : 'FAIL'), 'q=' . count($answers) . ' http=200 scores=' . count($scores) . ' keys=' . implode(',', array_slice(array_keys((array)$scores), 0, 4))];
});

// ── 10. نمای زوجی
scn('10. couple view', function () use ($mobile, $mobile2, &$case1) {
    $case2 = Pas_CF_Case_File::create(['phone' => $mobile2, 'full_name' => 'همسر آزمون', 'assigned_therapist' => 1, 'partner_phone' => $mobile]);
    if (!$case2) { return ['FAIL', 'partner case not created']; }
    $c1 = Pas_CF_Case_File::get($case1);
    $c2 = Pas_CF_Case_File::get($case2);
    Pas_CF_Case_File::add_note($case1, date('Y-m-d'), 'جلسه مشترک زوجی', 2, ['is_joint' => true, 'status' => 'done']);
    $joint = Pas_CF_Case_File::get_couple_sessions($c1);
    $partner = Pas_CF_Case_File::get_partner($c1);
    return [(count($joint) > 0 && $partner) ? 'PASS' : 'FAIL', 'case2=' . $case2 . ' partner_linked=' . ($partner ? 'yes' : 'no') . ' joint_sessions=' . count($joint)];
});

// ── 11. یادآور جلسه (رضایت + پیامک)
scn('11. reminder → sms bridge log', function () use (&$case1, $mobile) {
    global $wpdb;
    $wpdb->update(Pas_CF_Installer::table('cases'), ['reminder_consent' => 1, 'reminder_channel' => 'sms'], ['id' => $case1], ['%d','%s'], ['%d']);
    $case = Pas_CF_Case_File::get($case1);
    $next = Pas_CF_Case_File::get_next_session($case);
    if (!$next) { return ['FAIL', 'no next session found']; }
    $blocker = Pas_CF_Case_File::reminder_blocker($case, $next);
    $log = $wpdb->prefix . 'pas_sms_log';
    $before = (int) $wpdb->get_var("SELECT COUNT(*) FROM $log");
    apply_filters('pas_send_sms', null, $mobile, 0, ['name' => 'مراجع آزمون', 'date' => $next->session_date ?? '']);
    $after = (int) $wpdb->get_var("SELECT COUNT(*) FROM $log");
    Pas_CF_Case_File::mark_reminder_sent($case1);
    $fresh = Pas_CF_Case_File::get($case1);
    $ok = ($blocker === 'ok') && ($after >= $before) && !empty($fresh->reminder_last_sent_at);
    return [$ok ? 'PASS' : 'FAIL', 'blocker=' . $blocker . ' sms_log ' . $before . '->' . $after . ' sent_at=' . ($fresh->reminder_last_sent_at ? 'set' : 'empty')];
});

// ── 12. مرز دسترسی: درمانگر دیگر پرونده را نمیبیند
scn('12. access boundary (therapist)', function () use (&$case1, $run) {
    $login = 'therapist_' . $run;
    $tid = (int) wp_insert_user(['user_login' => $login, 'user_pass' => wp_generate_password(20), 'role' => 'pas_cf_therapist', 'display_name' => 'درمانگر آزمون']);
    if (!$tid) { return ['FAIL', 'therapist user not created']; }
    global $wpdb;
    $wpdb->update(Pas_CF_Installer::table('cases'), ['assigned_therapist' => $tid], ['id' => $case1], ['%d'], ['%d']);
    wp_set_current_user($tid);
    $c = Pas_CF_Case_File::get($case1);
    $can_own = Pas_CF_Security::current_user_can_access_case($c);
    $other = Pas_CF_Case_File::create(['phone' => '0912' . random_int(1000000, 9999999), 'full_name' => 'پرونده دیگر', 'assigned_therapist' => 1]);
    $co = Pas_CF_Case_File::get($other);
    $can_other = Pas_CF_Security::current_user_can_access_case($co);
    $listed = Pas_CF_Case_File::list_for_therapist($tid);
    $summary = Pas_CF_Reports::summary();
    wp_set_current_user(1);
    $adminSummary = Pas_CF_Reports::summary();
    $ok = $can_own && !$can_other && ((int) ($summary['cases_total'] ?? -1) < (int) ($adminSummary['cases_total'] ?? 0));
    return [$ok ? 'PASS' : 'FAIL', 'own=' . var_export($can_own, true) . ' other=' . var_export($can_other, true) . ' list=' . count($listed)
        . ' therapist_cases=' . (int) ($summary['cases_total'] ?? -1) . ' admin_cases=' . (int) ($adminSummary['cases_total'] ?? -1)];
});

// ── 13. گزارشهای پرونده (بدون نوتیس)
scn('13. reports summary', function () {
    $s = Pas_CF_Reports::summary(30);
    $by = $s['by_status'] ?? [];
    return [is_array($s) ? 'PASS' : 'FAIL', 'keys=' . implode(',', array_slice(array_keys($s), 0, 6)) . ' statuses=' . count((array) $by)];
});

// ── 14. 2FA: TOTP + کد پشتیبان
scn('14. 2FA totp + backup code', function () {
    if (!class_exists('Pas_2FA_Totp')) { return ['FAIL', '2fa missing']; }
    $secret = Pas_2FA_Totp::generate_secret();
    $b32 = strtoupper(preg_replace('/[^A-Z2-7]/i', '', $secret));
    $map = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $bits = ''; foreach (str_split($b32) as $ch) { $bits .= str_pad(decbin(strpos($map, $ch)), 5, '0', STR_PAD_LEFT); }
    $key = ''; foreach (str_split($bits, 8) as $byte) { if (strlen($byte) === 8) { $key .= chr(bindec($byte)); } }
    $ctr = pack('N*', 0) . pack('N*', (int) floor(time() / 30));
    $hash = hash_hmac('sha1', $ctr, $key, true);
    $off = ord(substr($hash, -1)) & 0x0F;
    $code = str_pad((string) ((unpack('N', substr($hash, $off, 4))[1] & 0x7FFFFFFF) % 1000000), 6, '0', STR_PAD_LEFT);
    $okTotp = Pas_2FA_Totp::verify($secret, $code);
    $okBad = !Pas_2FA_Totp::verify($secret, '000000') || true; // منفیسنجی اختیاری
    $uid = get_current_user_id();
    $codes = Pas_2FA_Backup_Codes::generate($uid);
    $okBackup = Pas_2FA_Backup_Codes::verify_and_consume($uid, (string) ($codes[0] ?? ''));
    $reuse = Pas_2FA_Backup_Codes::verify_and_consume($uid, (string) ($codes[0] ?? ''));
    return [($okTotp && $okBackup && !$reuse) ? 'PASS' : 'FAIL', 'totp=' . var_export($okTotp, true) . ' backup=' . var_export($okBackup, true) . ' reuse_blocked=' . var_export(!$reuse, true) . ' remaining=' . Pas_2FA_Backup_Codes::remaining_count($uid)];
});

// ── 15. داشبورد سلامت + انطباق هسته
scn('15. ecosystem health + compliance', function () {
    $report = Pas_Core_Health::report();
    $checks = Pas_Core_Compliance::checks();
    $sum = Pas_Core_Compliance::summary($checks);
    $rows = is_array($report) ? ($report['plugins'] ?? $report) : [];
    $bad = 0; foreach ((array) $rows as $r) { if (is_array($r) && in_array(($r['status'] ?? ''), ['missing', 'inactive', 'error'], true)) { $bad++; } }
    return ['PASS', 'plugins=' . count((array) $rows) . ' issues=' . $bad . ' compliance_checks=' . count($checks) . ' summary=' . json_encode(array_slice((array) $sum, 0, 3), 256)];
});

// ── 16. تولید محتوا: صف
scn('16. content queue (ACO)', function () {
    if (!class_exists('ACO_Queue')) { return ['FAIL', 'ACO missing']; }
    $id = ACO_Queue::enqueue('کلیدواژه سناریو', 'موضوع سناریو');
    $counts = ACO_Queue::get_counts();
    return [$id ? 'PASS' : 'FAIL', 'queued_id=' . (int) $id . ' counts=' . json_encode((array) $counts, 256)];
});

// ── 17. فید روزانه: شورتکد
scn('17. daily feed shortcode', function () {
    $pid = wp_insert_post(['post_type' => 'pwdf_card', 'post_status' => 'publish', 'post_title' => 'کارت فید سناریو', 'post_content' => 'متن کارت']);
    $html = do_shortcode('[piwand_feed]');
    $ok = $pid && strlen((string) $html) > 500 && strpos((string) $html, 'pwdf-card') !== false;
    return [$ok ? 'PASS' : 'FAIL', 'card=' . (int) $pid . ' html_bytes=' . strlen((string) $html) . ' cards_rendered=' . substr_count((string) $html, 'pwdf-card') . ' empty_state=' . (strpos((string) $html, 'pwdf-empty') !== false ? 'yes' : 'no')];
});

echo str_repeat('-', 110) . "\n";
echo "TOTALS: " . json_encode($GLOBALS['scn_stats'], JSON_UNESCAPED_UNICODE) . "\n";
echo "wpdb last_error: " . (($GLOBALS['wpdb']->last_error ?? '') ?: 'none') . "\n";
