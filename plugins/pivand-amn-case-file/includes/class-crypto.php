<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * این کلاس قبلاً پیاده‌سازی مستقل رمزنگاری داشت. حالا فقط یک لایه‌ی نازک
 * روی Pas_Core_Crypto است — رمزنگاری به هسته‌ی مشترک منتقل شد تا
 * quiz-enterprise و پرونده مراجعین (و در آینده client-portal) از یک کلید
 * و یک پیاده‌سازی استفاده کنند، نه هرکدام کلید جدای خودشان.
 *
 * توجه: از این پس افزونه‌ی «هسته مشترک» یک وابستگی سخت (نه اختیاری) برای
 * قابلیت یادداشت‌های رمزنگاری‌شده است — بدون آن، ثبت/نمایش یادداشت غیرفعال
 * می‌ماند (پیام خطای واضح نمایش داده می‌شود، نه شکست ساکت).
 */
final class Pas_CF_Crypto
{
    public static function is_configured(): bool
    {
        return class_exists('Pas_Core_Crypto') && Pas_Core_Crypto::is_configured();
    }

    public static function generate_suggested_key(): string
    {
        return class_exists('Pas_Core_Crypto') ? Pas_Core_Crypto::generate_suggested_key() : '';
    }

    public static function encrypt(string $plaintext): string
    {
        if (!class_exists('Pas_Core_Crypto')) {
            throw new \RuntimeException('افزونه‌ی «هسته مشترک» فعال نیست — رمزنگاری در دسترس نیست.');
        }

        return Pas_Core_Crypto::encrypt($plaintext);
    }

    public static function decrypt(string $encoded): string
    {
        if (!class_exists('Pas_Core_Crypto')) {
            return '⚠️ افزونه‌ی «هسته مشترک» فعال نیست — رمزگشایی ممکن نیست.';
        }

        return Pas_Core_Crypto::decrypt($encoded);
    }

    /**
     * رمزگشایی با تحملِ داده‌های قدیمی: ردیف‌هایی که پیش از فعال شدن کلید
     * (یا با نسخه‌های اولیه) به‌صورت متن ساده ذخیره شده‌اند، به‌جای پیام
     * «داده خراب» همان‌طور که هستند برگردانده می‌شوند — بدون این، مهاجرت از
     * نسخه‌های اولیه یادداشت‌های قدیمی را ناخوانا نشان می‌داد (باگ پیدا شده
     * در آزمون مهاجرت فاز ۱۱).
     *
     * تشخیص: ciphertext معتبر همیشه base64 با طول دست‌کم nonce(24)+tag(16)
     * است؛ هر چیز دیگر متن ساده‌ی قدیمی تلقی می‌شود. داده‌ی دستکاری‌شده‌ی
     * با base64 معتبر همچنان از مسیر سخت‌گیرانه‌ی decrypt رد می‌شود.
     */
    public static function decrypt_or_plain(string $value): string
    {
        if ($value === '') {
            return '';
        }

        $raw = base64_decode($value, true);
        if ($raw === false || strlen($raw) < 40) { // 24 (nonce) + 16 (tag)
            return $value;
        }

        return self::decrypt($value);
    }
}
