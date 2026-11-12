<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * پیاده‌سازی مستقل TOTP (RFC 6238) — بدون وابستگی به کتابخانه‌ی بیرونی، تا
 * این افزونه هیچ نیاز composer/CDN نداشته باشد. سازگار با Google
 * Authenticator، Authy، و هر اپ استاندارد دیگر.
 */
final class Pas_2FA_Totp
{
    private const PERIOD = 30;
    private const DIGITS = 6;

    public static function generate_secret(int $length = 20): string
    {
        return self::base32_encode(random_bytes($length));
    }

    public static function otpauth_uri(string $secret, string $account_label, string $issuer = 'پیوند امن'): string
    {
        $params = http_build_query([
            'secret' => $secret,
            'issuer' => $issuer,
        ]);

        return 'otpauth://totp/' . rawurlencode($issuer . ':' . $account_label) . '?' . $params;
    }

    /** @param int $window تعداد بازه‌ی ۳۰ثانیه‌ای قبل/بعد که به‌خاطر اختلاف ساعت گوشی قبول می‌شود */
    public static function verify(string $secret, string $code, int $window = 1): bool
    {
        $code = preg_replace('/\D+/', '', $code) ?? '';
        if (strlen($code) !== self::DIGITS) {
            return false;
        }

        $key       = self::base32_decode($secret);
        $timestamp = (int) floor(time() / self::PERIOD);

        for ($i = -$window; $i <= $window; $i++) {
            if (hash_equals(self::code_at($key, $timestamp + $i), $code)) {
                return true;
            }
        }

        return false;
    }

    private static function code_at(string $key, int $counter): string
    {
        $binary_counter = pack('N*', 0, $counter); // 8 بایت big-endian
        $hash           = hash_hmac('sha1', $binary_counter, $key, true);

        $offset = ord($hash[strlen($hash) - 1]) & 0x0F;
        $part   = (
            ((ord($hash[$offset]) & 0x7F) << 24) |
            ((ord($hash[$offset + 1]) & 0xFF) << 16) |
            ((ord($hash[$offset + 2]) & 0xFF) << 8) |
            (ord($hash[$offset + 3]) & 0xFF)
        );

        return str_pad((string) ($part % (10 ** self::DIGITS)), self::DIGITS, '0', STR_PAD_LEFT);
    }

    private const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    private static function base32_encode(string $binary): string
    {
        $bits   = '';
        foreach (str_split($binary) as $byte) {
            $bits .= str_pad(decbin(ord($byte)), 8, '0', STR_PAD_LEFT);
        }

        $bits    .= str_repeat('0', (5 - strlen($bits) % 5) % 5);
        $encoded = '';
        foreach (str_split($bits, 5) as $chunk) {
            $encoded .= self::ALPHABET[bindec($chunk)];
        }

        return $encoded;
    }

    private static function base32_decode(string $encoded): string
    {
        $encoded = strtoupper(preg_replace('/[^A-Z2-7]/i', '', $encoded) ?? '');
        $bits    = '';
        foreach (str_split($encoded) as $char) {
            $pos = strpos(self::ALPHABET, $char);
            if ($pos === false) {
                continue;
            }
            $bits .= str_pad(decbin($pos), 5, '0', STR_PAD_LEFT);
        }

        $binary = '';
        foreach (str_split($bits, 8) as $byte) {
            if (strlen($byte) === 8) {
                $binary .= chr(bindec($byte));
            }
        }

        return $binary;
    }
}
