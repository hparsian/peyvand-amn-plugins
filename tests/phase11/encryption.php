<?php
/**
 * فاز ۱۱ — بررسی رمزنگاری داده‌های بالینی.
 *
 * اجرا: php wp-cli.phar --path=web --allow-root eval-file encryption.php
 * معیار: متن بالینی در دیتابیس ciphertext است، رفت‌وبرگشت درست است،
 * دستکاری و کلید نادرست رد می‌شوند، و کلید بیرون از دیتابیس نگهداری می‌شود.
 */

if (!defined('ABSPATH')) {
    exit(1);
}

global $wpdb;

final class R
{
    public static $p = 0;
    public static $f = 0;
}

function r(string $n, bool $ok, string $d): void
{
    $ok ? R::$p++ : R::$f++;
    printf("%-5s | %-40s | %s\n", $ok ? 'PASS' : 'FAIL', $n, $d);
}

$secret = 'سابقه-بیماری-محرمانه-' . random_int(100000, 999999);
$note   = 'متن-جلسه-محرمانه-' . random_int(100000, 999999);
$phone  = '0912' . random_int(1000000, 9999999);

$case    = Pas_CF_Case_File::create([
    'phone'              => $phone,
    'full_name'          => 'پرونده-رمزنگاری',
    'assigned_therapist' => 1,
    'medical_history'    => $secret,
    'gender'             => 'زن',
]);
$note_id = Pas_CF_Case_File::add_note($case, date('Y-m-d'), $note, 1, ['status' => 'done']);

$rc = $wpdb->get_var($wpdb->prepare("SELECT medical_history_encrypted FROM {$wpdb->prefix}pas_cf_cases WHERE id=%d", $case));
$rn = $wpdb->get_var($wpdb->prepare("SELECT content_encrypted FROM {$wpdb->prefix}pas_cf_notes WHERE id=%d", $note_id));
$raw = base64_decode($rn, true);

r('clinical text encrypted at rest', $rc !== $secret && strpos($rc, $secret) === false && base64_decode($rc, true) !== false, 'len=' . strlen($rc) . ' plaintext_absent=' . (strpos($rc, $secret) === false ? 'yes' : 'NO'));
r('note text encrypted at rest', $rn !== $note && strpos($rn, $note) === false && $raw !== false, 'len=' . strlen($rn) . ' decoded_len=' . strlen((string) $raw));
r('ciphertext = nonce(24)+box(+16)', $raw !== false && strlen($raw) === 24 + strlen($note) + 16, 'expected=' . (24 + strlen($note) + 16) . ' actual=' . strlen((string) $raw));
r('roundtrip decrypt (case)', Pas_CF_Crypto::decrypt($rc) === $secret, 'equal=' . (Pas_CF_Crypto::decrypt($rc) === $secret ? 'yes' : 'no'));
r('roundtrip decrypt (note)', Pas_CF_Crypto::decrypt($rn) === $note, 'equal=' . (Pas_CF_Crypto::decrypt($rn) === $note ? 'yes' : 'no'));

$t = base64_decode($rn, true);
$t[strlen($t) - 1] = chr(ord($t[strlen($t) - 1]) ^ 0x01);
$tampered = base64_encode($t);
$out      = Pas_CF_Crypto::decrypt($tampered);
r('tampered ciphertext rejected', strpos($out, '⚠️') === 0 && strpos($out, $note) === false, 'msg=' . mb_substr($out, 0, 30));

$other = random_bytes(32);
$raw2  = base64_decode($rn, true);
$wrong = sodium_crypto_secretbox_open(substr($raw2, 24), substr($raw2, 0, 24), $other);
r('different key cannot decrypt', $wrong === false, 'opens=' . ($wrong === false ? 'false' : 'PLAINTEXT'));

$src = class_exists('Pas_Core_Data_Audit') ? Pas_Core_Data_Audit::key_status() : [];
r('key outside the database', Pas_Core_Crypto::is_configured() && !$wpdb->get_var("SELECT option_value FROM {$wpdb->options} WHERE option_name='PAS_CORE_ENCRYPTION_KEY'"), 'source=' . ($src['source'] ?? '?'));

$legacy = 'یادداشت-متن-ساده-قدیمی';
r('legacy plaintext still readable', Pas_CF_Crypto::decrypt_or_plain($legacy) === $legacy, 'passthrough=yes');

echo "case_id=$case note_id=$note_id\n";
echo 'TOTALS: PASS=', R::$p, ' FAIL=', R::$f, "\n";
echo 'wpdb last_error: ', $wpdb->last_error ?: 'none', "\n";
