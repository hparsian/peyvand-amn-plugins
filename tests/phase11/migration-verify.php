<?php
/**
 * فاز ۱۱ — آزمون مهاجرت از نسخه‌های اولیه.
 *
 * پیش‌نیاز: نسخه‌های اولیه (زیپ‌های کامیت 3f94739) نصب و داده‌ی قدیمی
 * ساخته شده باشد؛ سپس دایرکتوری افزونه‌ها با نسخه‌های فعلی جایگزین و یک
 * درخواست admin زده شده باشد (روتین‌های ارتقا). این اسکریپت فقط بررسی
 * می‌کند که داده‌ی قدیمی سالم مانده و اسکیمای جدید کامل است.
 *
 * اجرا: php wp-cli.phar --path=web --allow-root eval-file migration-verify.php
 */

if (!defined('ABSPATH')) {
    exit(1);
}

global $wpdb;

final class M
{
    public static $p = 0;
    public static $f = 0;
}

function v(string $n, bool $ok, string $d): void
{
    $ok ? M::$p++ : M::$f++;
    printf("%-5s | %-44s | %s\n", $ok ? 'PASS' : 'FAIL', $n, $d);
}

$cases = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}pas_cf_cases");
$notes = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}pas_cf_notes");
v('cases preserved', $cases === 3, "cases=$cases (expected 3, created by 1.9.0)");
v('notes preserved', $notes === 4, "notes=$notes (expected 4, created by 1.9.0)");

$case1 = (int) $wpdb->get_var("SELECT id FROM {$wpdb->prefix}pas_cf_cases WHERE full_name='مراجع-مهاجرت-1'");
$rows  = $case1 ? Pas_CF_Case_File::get_notes($case1) : [];
$texts = [];
foreach ($rows as $r) {
    $texts[] = (string) $r->content;
}
$joined = implode("\n", $texts);

v('old encrypted note decrypts with new code', strpos($joined, 'یادداشت-قدیمی-1-') !== false, 'notes_on_case=' . count($rows));
v('legacy plaintext note still readable', strpos($joined, 'یادداشت-متن-ساده-قدیمی') !== false, 'found=' . (strpos($joined, 'یادداشت-متن-ساده-قدیمی') !== false ? 'yes' : 'no'));

$case      = $case1 ? Pas_CF_Case_File::get($case1) : null;
$rawmed    = (string) $wpdb->get_var("SELECT medical_history_encrypted FROM {$wpdb->prefix}pas_cf_cases WHERE id=$case1");
$plainmed  = $case ? (string) $case->medical_history : '';
v('medical history decrypts', strlen($plainmed) > 5 && strpos($rawmed, $plainmed) === false, 'plain_len=' . strlen($plainmed));

foreach (['status', 'duration_minutes', 'is_joint', 'session_number'] as $c) {
    $has = (bool) $wpdb->get_var($wpdb->prepare(
        'SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=%s AND column_name=%s',
        $wpdb->prefix . 'pas_cf_notes',
        $c
    ));
    v("new column exists: notes.$c", $has, $has ? 'present' : 'MISSING');
}

foreach (['pas_cf_note_history', 'pas_cf_audit_log'] as $t) {
    $has = (bool) $wpdb->get_var($wpdb->prepare(
        'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=%s',
        $wpdb->prefix . $t
    ));
    v("new table exists: $t", $has, $has ? 'present' : 'MISSING');
}

$audit = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}pas_cf_audit_log");
v('audit log preserved/continues', $audit >= 6, "rows=$audit");
v('probe option survived', is_array(get_option('pas_mig_probe')), 'cases=' . (get_option('pas_mig_probe')['cases'] ?? '?'));
v('no duplicate notes created by upgrade', $notes === 4, "notes=$notes");

// سازگاری با نصب تازه: مجموعهٔ جدول‌ها و ستون‌های یادداشت باید یکی باشد.
$fresh = (int) $wpdb->get_var("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='wp_phase11'");
$mig   = (int) $wpdb->get_var("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE()");
v('same table count as fresh install', $fresh > 0 && $fresh === $mig, "fresh=$fresh migrated=$mig");

echo 'TOTALS: PASS=', M::$p, ' FAIL=', M::$f, "\n";
echo 'wpdb last_error: ', $wpdb->last_error ?: 'none', "\n";
