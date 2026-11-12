<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class ACO_Settings {

    public static function init() {
        // هیچ action اضافه‌ای فعلاً لازم نیست
    }

    public static function get( string $key = '' ) {
        $defaults = [
            'provider'        => 'openrouter',
            // حالت «فقط هوش مصنوعی رایگان» — وقتی true باشد، صرف‌نظر از تنظیمات provider/
            // fallback_provider پایین، سیستم همیشه فقط از OpenRouter با یک مدل رایگان
            // (پسوند ":free" یا خودِ Free Models Router) استفاده می‌کند و هیچ کلید پولی
            // (DeepSeek/OpenAI/Gemini/Anthropic برای متن، یا OpenAI/OpenRouter پولی برای
            // تصویر) هرگز فراخوانی نمی‌شود — حتی اگر کلیدشان قبلاً وارد شده باشد.
            // این enforcement هم در ACO_AI_Router (متن) و هم در ACO_Image_Generator
            // (تصویر) اعمال می‌شود، نه فقط در رابط کاربری.
            'free_only_mode'  => true,
            // ارائه‌دهنده جایگزین (اختیاری) — اگر ارائه‌دهنده اصلی بالا (برای تولید مقاله)
            // با خطا مواجه شد (کلید نامعتبر، rate limit، سرور در دسترس نیست و...)، به‌طور
            // خودکار همان درخواست با این ارائه‌دهنده دوم دوباره امتحان می‌شود — به شرطی که
            // کلید آن هم تنظیم شده باشد. '' یعنی fallback بین دو ارائه‌دهنده غیرفعال است.
            // توجه: وقتی free_only_mode روشن است، این فیلد کاملاً نادیده گرفته می‌شود.
            'fallback_provider' => '',
            'openrouter_key'  => '',
            'deepseek_key'    => '',
            'openai_key'      => '',
            'gemini_key'      => '',
            'anthropic_key'   => '',
            'gsc_service_account_json' => '', // فایل JSON کلید Service Account گوگل (حساس — مثل کلیدهای API رمزنگاری می‌شود)
            'gsc_site_url'    => '',          // دقیقاً مطابق ثبت در Search Console، مثل sc-domain:example.com یا https://example.com/
            'openrouter_model'=> 'google/gemma-4-31b-it:free',
            'deepseek_model'  => 'deepseek-chat',
            'openai_model'    => 'gpt-4o-mini',
            'gemini_model'    => 'gemini-2.0-flash',
            'anthropic_model' => 'claude-3-5-haiku-20241022',
            // مدل تخصصی تصویرسازی OpenAI — جدا از openai_model (که برای متن/مقاله است).
            // فقط وقتی استفاده می‌شود که کلید openai_key تنظیم شده باشد و در زنجیره
            // تولید تصویر شاخص به‌عنوان fallback تخصصی (بعد از Pollinations) امتحان شود.
            'openai_image_model' => 'gpt-image-1',
            // خروجی محتوای تولیدشده به‌صورت بلاک‌های بومی گوتنبرگ (wp:heading/wp:paragraph/...)
            // به‌جای HTML خام classic. پیش‌فرض روشن — برای سایت‌هایی که از صفحه‌ساز
            // دیگری (Elementor و...) روی پست‌های تولیدشده استفاده می‌کنند می‌توان خاموش کرد.
            'gutenberg_blocks_enabled' => true,
            'default_status'  => 'draft',
            'default_author'  => 1,
            'niche'           => '',          // حوزه تخصصی سایت (مثلاً: روانشناسی، سلامت، فناوری)
            'tone'            => 'حرفه‌ای، قابل اعتماد و کاربردی',  // لحن نوشتار
            'min_words'           => 1200,        // حداقل تعداد کلمات
            'monthly_token_budget' => 0,           // سقف مصرف توکن ماهانه برای اجرای خودکار (کرم + بانک کلمات کلیدی). ۰ = بدون محدودیت
            'featured_image_enabled' => true,     // تولید خودکار تصویر شاخص (Pollinations + fallback OpenRouter)
            'protected_image_keywords' => [ 'مهناز خوینی' ], // اگر alt/عنوان/نام‌فایل تصویر شاخص شامل هرکدام از این‌ها باشد، هرگز بازتولید/جایگزین نمی‌شود
            'internal_link_pages' => [],          // لیست صفحات مهم برای لینک داخلی
            'keyword_bank' => [
                'enabled'       => true,
                'daily_count'   => 2,
                'refresh_count' => 15,
                'run_hour'      => 4,
                'post_status'   => 'pending', // SAFETY: پیش‌فرض «در انتظار بازبینی» نه انتشار مستقیم —
                                               // سایت YMYL است؛ کاربر می‌تواند در تنظیمات به «publish» تغییر دهد.
            ],
            'crawler'             => [
                'enabled'         => false,
                'interval_days'   => 30,
                'seo_threshold'   => 60,
                'min_links'       => 2,
                'stale_months'    => 6,
                'posts_per_night' => 5,
                'run_hour'        => 3,
                // FIX: بازنویسی خودکار کرم به‌جای انتشار مستقیم، برای تأیید ادمین کنار گذاشته می‌شود.
                // پیش‌فرض روشن است — روی سایت‌های YMYL (سلامت/روانشناسی)، محتوای تولیدشده توسط AI
                // نباید بدون بازبینی انسانی مستقیم منتشر شود.
                'review_mode'     => true,
            ],
        ];

        $saved = get_option( 'aco_settings', [] );
        $all   = wp_parse_args( $saved, $defaults );

        // مطابق الگوی رمزنگاری اکوسیستم: unwrap_decrypt مقادیر قدیمیِ
        // رمزنگاری‌نشده را دست‌نخورده برمی‌گرداند، پس migration دستی لازم نیست.
        if ( class_exists( 'Pas_Core_Crypto' ) ) {
            foreach ( self::key_fields() as $key_field ) {
                if ( ! empty( $all[ $key_field ] ) ) {
                    $all[ $key_field ] = Pas_Core_Crypto::unwrap_decrypt( (string) $all[ $key_field ] );
                }
            }
        }

        if ( $key ) {
            return $all[ $key ] ?? null;
        }
        return $all;
    }

    /**
     * فیلدهای کلید API که باید رمزنگاری/رمزگشایی شوند — یک منبع واحد
     * برای get()/save() تا با اضافه‌شدن ارائه‌دهنده جدید فقط اینجا اضافه شود.
     */
    private static function key_fields(): array {
        return [ 'openrouter_key', 'deepseek_key', 'openai_key', 'gemini_key', 'anthropic_key', 'gsc_service_account_json' ];
    }

    /**
     * مقدار خام (رمزنگاری‌شده در صورت فعال بودن core) کلیدهای API را
     * برمی‌گرداند — فقط برای منطق «اگر فیلد خالی فرستاده شد، مقدار قبلی را
     * دست‌نخورده نگه‌دار» در ajax_save_settings. جای دیگری استفاده نکن.
     */
    public static function get_raw_key( string $key_field ): string {
        $saved = get_option( 'aco_settings', [] );
        return (string) ( $saved[ $key_field ] ?? '' );
    }

    public static function save( array $data ): void {
        $current = self::get();

        // کلیدهای API قبل از ذخیره رمزنگاری می‌شوند (اگر core نصب و کلید
        // رمزنگاری تنظیم شده باشد)؛ اگر فیلد خالی فرستاده شده یعنی کاربر
        // نمی‌خواسته کلید را عوض کند — مقدار خامِ فعلی (رمزنگاری‌شده) حفظ
        // می‌شود، نه نسخه‌ی رمزگشایی‌شده‌ی $current.
        foreach ( self::key_fields() as $key_field ) {
            if ( ! array_key_exists( $key_field, $data ) ) {
                continue;
            }
            if ( $data[ $key_field ] === '' ) {
                $data[ $key_field ] = self::get_raw_key( $key_field );
                continue;
            }
            if ( class_exists( 'Pas_Core_Crypto' ) && Pas_Core_Crypto::is_configured() ) {
                $data[ $key_field ] = Pas_Core_Crypto::wrap_encrypted( (string) $data[ $key_field ] );
            }
        }

        $merged = array_merge( $current, $data );
        update_option( 'aco_settings', $merged );
    }
}
