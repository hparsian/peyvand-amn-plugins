<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * رمزنگاری مشترک برای داده‌های حساس کل اکوسیستم (یادداشت‌های بالینی در
 * پرونده مراجعین، جواب/نتیجه‌ی آزمون‌های روان‌سنجی در quiz-enterprise، و
 * در آینده احتمالاً نظرسنجی‌ها). قبلاً این کلاس فقط داخل افزونه‌ی پرونده
 * مراجعین بود (Pas_CF_Crypto) — به اینجا منتقل شد تا یک کلید و یک
 * پیاده‌سازی برای کل اکوسیستم کافی باشد، نه یک کلید جدا برای هر افزونه.
 *
 * با libsodium (secretbox — XSalsa20-Poly1305). کلید از wp-config.php
 * خوانده می‌شود، نه از دیتابیس:
 *
 *   define('PAS_CORE_ENCRYPTION_KEY', 'base64:...');
 */
final class Pas_Core_Crypto
{
    public static function is_configured(): bool
    {
        return self::get_key() !== null;
    }

    /**
     * دو راه برای تنظیم کلید (به ترتیب اولویت):
     *   ۱. ثابت PAS_CORE_ENCRYPTION_KEY در wp-config.php
     *   ۲. فایل wp-content/pas-encryption-key.php که فقط یک خط
     *      `return 'base64:...';` دارد — برای کسانی که نمی‌خواهند
     *      wp-config.php (فایل حیاتی و پرخطر برای دست‌کاری) را ویرایش کنند.
     *      چون پسوند فایل .php است، اگر کسی مستقیم آدرسش را توی مرورگر باز
     *      کند چیزی جز صفحه‌ی خالی نمی‌بیند (PHP اجرا می‌شود، محتوا خروجی
     *      داده نمی‌شود) — هیچ‌وقت این فایل را txt/json نسازید.
     */
    private static function get_key(): ?string
    {
        $raw = null;

        if (defined('PAS_CORE_ENCRYPTION_KEY')) {
            $raw = constant('PAS_CORE_ENCRYPTION_KEY');
        } else {
            $key_file = WP_CONTENT_DIR . '/pas-encryption-key.php';
            if (file_exists($key_file)) {
                $raw = include $key_file;
            }
        }

        if (!is_string($raw) || strpos($raw, 'base64:') !== 0) {
            return null;
        }

        $key = base64_decode(substr($raw, 7), true);
        if ($key === false || strlen($key) !== SODIUM_CRYPTO_SECRETBOX_KEYBYTES) {
            return null;
        }

        return $key;
    }

    /** یک کلید تصادفی امن برای پیشنهاد به ادمین (فقط نمایش، ذخیره نمی‌شود). */
    public static function generate_suggested_key(): string
    {
        return 'base64:' . base64_encode(random_bytes(SODIUM_CRYPTO_SECRETBOX_KEYBYTES));
    }

    public static function encrypt(string $plaintext): string
    {
        $key = self::get_key();
        if ($key === null) {
            throw new \RuntimeException('کلید رمزنگاری (PAS_CORE_ENCRYPTION_KEY) در wp-config.php تنظیم نشده است.');
        }

        $nonce      = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $ciphertext = sodium_crypto_secretbox($plaintext, $nonce, $key);

        return base64_encode($nonce . $ciphertext);
    }

    public static function decrypt(string $encoded): string
    {
        $key = self::get_key();
        if ($key === null) {
            return '⚠️ کلید رمزنگاری تنظیم نشده — امکان رمزگشایی نیست.';
        }

        $raw = base64_decode($encoded, true);
        if ($raw === false || strlen($raw) < SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
            return '⚠️ داده خراب یا نامعتبر است.';
        }

        $nonce      = substr($raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $ciphertext = substr($raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $plaintext  = sodium_crypto_secretbox_open($ciphertext, $nonce, $key);

        if ($plaintext === false) {
            return '⚠️ رمزگشایی ناموفق بود (کلید نادرست یا داده دستکاری‌شده).';
        }

        return $plaintext;
    }

    /**
     * برای مقادیری که ممکن است هنوز رمزنگاری نشده باشند (مهاجرت تدریجی):
     * اگر مقدار با پیشوند شناخته‌شده‌ی رمزنگاری‌شده (enc1:) شروع نشود، همان
     * را بدون تغییر برمی‌گرداند. کدهای write-side جدید باید همیشه از
     * wrap_encrypted() برای نشانه‌گذاری استفاده کنند.
     */
    public static function wrap_encrypted(string $plaintext): string
    {
        return 'enc1:' . self::encrypt($plaintext);
    }

    public static function is_wrapped(string $value): bool
    {
        return strpos($value, 'enc1:') === 0;
    }

    public static function unwrap_decrypt(string $value): string
    {
        if (!self::is_wrapped($value)) {
            return $value; // مقدار قدیمی/رمزنگاری‌نشده — همان‌طور برگردانده می‌شود
        }

        return self::decrypt(substr($value, 5));
    }

    /**
     * موقع فعال‌سازی افزونه صدا زده می‌شود. اگر نه ثابت wp-config.php و نه
     * فایل کلید از قبل موجود باشد، خودش یک کلید تصادفی می‌سازد و مستقیم در
     * wp-content/pas-encryption-key.php می‌نویسد — دیگر نیازی به FTP دستی
     * نیست. اگر wp-content قابل‌نوشتن نباشد (بعضی هاست‌های محدود)، بی‌صدا
     * شکست می‌خورد و راهنمای دستی در پیشخوان همچنان نمایش داده می‌شود.
     */
    public static function ensure_key_file(): void
    {
        if (self::is_configured()) {
            return; // کلید از قبل (wp-config.php یا فایل قبلی) تنظیم شده — دست نمی‌زنیم
        }

        $key_file = WP_CONTENT_DIR . '/pas-encryption-key.php';
        if (file_exists($key_file)) {
            return; // فایل هست ولی نامعتبره (مثلاً دستی خراب شده) — عمداً رونویسی نمی‌کنیم، خطرناکه
        }

        if (!is_writable(WP_CONTENT_DIR)) {
            update_option('pas_core_key_autogen_failed', '1');
            return;
        }

        $key     = self::generate_suggested_key();
        $content = "<?php\n"
            . "// این فایل به‌صورت خودکار موقع فعال‌سازی «پیوند امن — هسته مشترک» ساخته شد.\n"
            . "// فقط باید همین یک خط را داشته باشد. یک نسخه از این فایل را جای امنی\n"
            . "// (پسورد منیجر) هم نگه دار — اگر گم شود، داده‌های رمزنگاری‌شده برای همیشه\n"
            . "// غیرقابل‌بازیابی می‌شوند. مستقیم توی مرورگر باز نکن (چیزی نشان نمی‌دهد، عادی است).\n"
            . "return '" . addslashes($key) . "';\n";

        $written = @file_put_contents($key_file, $content, LOCK_EX);

        if ($written === false) {
            update_option('pas_core_key_autogen_failed', '1');
            return;
        }

        @chmod($key_file, 0440); // فقط خواندنی — کمی سخت‌تر برای دستکاری تصادفی
        update_option('pas_core_key_autogenerated_at', current_time('mysql'));
        delete_option('pas_core_key_autogen_failed');
    }
}
