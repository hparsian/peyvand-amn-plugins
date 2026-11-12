<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * کدهای بازیابی یک‌بارمصرف برای ۲FA.
 *
 * چرا لازم بود: قبل از این، اگر ادمینی گوشی/اپ TOTP را گم می‌کرد، تنها راه
 * رفع قفل، دستکاری مستقیم دیتابیس (حذف pas_2fa_secret از usermeta) بود.
 *
 * طراحی:
 *  - هر بار فعال‌سازی (یا «ساخت مجدد») ۱۰ کد یک‌بارمصرف تولید می‌شود.
 *  - کدها فقط *یک بار*، بلافاصله بعد از تولید، به‌صورت متن ساده نمایش داده
 *    می‌شوند (باید ذخیره/دانلود شوند) — بعد از آن دیگر هرگز قابل بازیابی
 *    نیستند، فقط قابل ابطال (ساخت مجدد).
 *  - در دیتابیس فقط هش (HMAC-SHA256 با نمک اختصاصیِ خودِ سایت، wp_salt)
 *    ذخیره می‌شود، نه رمزنگاری برگشت‌پذیر — چون این کدها ماهیتاً مثل رمز
 *    عبورند (فقط برای تایید لازم است، نه بازیابیِ مقدار اصلی). این عمداً
 *    مستقل از Pas_Core_Crypto/PAS_CORE_ENCRYPTION_KEY است تا حتی اگر آن
 *    کلید تنظیم نشده باشد (یا core غیرفعال باشد)، کدهای بازیابی همچنان کار
 *    کنند — نباید مکانیزم رفع قفل، خودش به یک پیش‌نیاز دیگر گره بخورد.
 *  - هر کد فقط یک‌بار مصرف می‌شود: بعد از تایید موفق، هش آن از لیست حذف
 *    می‌شود.
 */
final class Pas_2FA_Backup_Codes
{
    private const META_KEY   = 'pas_2fa_backup_code_hashes';
    private const CODE_COUNT = 10;
    private const CODE_ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789'; // بدون 0/O و 1/I برای خوانایی

    /**
     * ۱۰ کد تصادفی جدید می‌سازد، هش‌شان را ذخیره می‌کند (و کدهای قبلی را
     * باطل می‌کند)، و کدهای متن‌ساده را برای نمایش یک‌باره برمی‌گرداند.
     *
     * @return string[] کدهای متن‌ساده، فرمت XXXXX-XXXXX
     */
    public static function generate(int $user_id): array
    {
        $plain_codes = [];
        $hashes      = [];

        for ($i = 0; $i < self::CODE_COUNT; $i++) {
            $code          = self::random_code();
            $plain_codes[] = $code;
            $hashes[]      = self::hash_code($code);
        }

        update_user_meta($user_id, self::META_KEY, $hashes);

        return $plain_codes;
    }

    public static function remaining_count(int $user_id): int
    {
        $hashes = get_user_meta($user_id, self::META_KEY, true);

        return is_array($hashes) ? count($hashes) : 0;
    }

    public static function has_codes(int $user_id): bool
    {
        return self::remaining_count($user_id) > 0;
    }

    public static function delete_all(int $user_id): void
    {
        delete_user_meta($user_id, self::META_KEY);
    }

    /**
     * اگر کد معتبر و مصرف‌نشده باشد، آن را باطل می‌کند (یک‌بارمصرف) و true
     * برمی‌گرداند. در غیر این صورت false.
     */
    public static function verify_and_consume(int $user_id, string $submitted_code): bool
    {
        $hashes = get_user_meta($user_id, self::META_KEY, true);
        if (!is_array($hashes) || empty($hashes)) {
            return false;
        }

        $submitted_hash = self::hash_code($submitted_code);

        foreach ($hashes as $index => $stored_hash) {
            if (hash_equals($stored_hash, $submitted_hash)) {
                unset($hashes[$index]);
                update_user_meta($user_id, self::META_KEY, array_values($hashes));

                return true;
            }
        }

        return false;
    }

    private static function random_code(): string
    {
        $chars = '';
        for ($i = 0; $i < 10; $i++) {
            $chars .= self::CODE_ALPHABET[random_int(0, strlen(self::CODE_ALPHABET) - 1)];
        }

        return substr($chars, 0, 5) . '-' . substr($chars, 5, 5);
    }

    /**
     * نرمال‌سازی (حذف فاصله/خط‌تیره، بزرگ‌کردن حروف) قبل از هش، تا کاربر
     * چه با خط‌تیره کد را وارد کند چه بدون آن، تایید کار کند.
     */
    private static function normalize(string $code): string
    {
        return strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $code) ?? '');
    }

    private static function hash_code(string $code): string
    {
        return hash_hmac('sha256', self::normalize($code), wp_salt('auth'));
    }
}
