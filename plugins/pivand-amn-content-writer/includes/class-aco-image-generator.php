<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * ACO_Image_Generator
 *
 * تولید خودکار تصویر شاخص برای مقالات تولیدشده با ACO.
 *
 * منبع اصلی: Pollinations.ai — رایگان، بدون نیاز به کلید API یا کارت
 * بانکی خارجی، و بدون بلاک IP ایران (برخلاف DALL-E/OpenAI که از ایران
 * قابل دسترس نیستند مگر با VPN/سرور خارج). ساختار درخواست یک GET ساده است.
 *
 * پارامترهای کیفیت (از نسخه ۲.۹.۱۲): مدل صراحتاً `flux` (مدل باکیفیت
 * پیش‌فرض Pollinations) ست می‌شود، `enhance=true` قبل از تولید تصویر
 * پرامپت را با یک LLM سمت سرور بهبود می‌دهد، و `private=true` تصویر را
 * از فید عمومی سایت Pollinations خارج نگه می‌دارد (محتوای سایت‌های
 * حساس مثل روان‌شناسی نباید در فید عمومی ظاهر شود). قبلاً هیچ‌کدام از
 * این سه پارامتر پاس داده نمی‌شد که باعث کیفیت پایین‌تر و کمتر مرتبط
 * بودن تصاویر تولیدی می‌شد.
 *
 * زنجیره fallback (هر مرحله فقط وقتی امتحان می‌شود که مرحله قبل شکست
 * بخورد؛ اجرای هر مرحله جز اول هم فقط اگر کلید مربوطه تنظیم شده باشد):
 *
 *   ۱. Pollinations.ai — رایگان، بدون کلید (منبع اصلی/پیش‌فرض)
 *   ۲. OpenAI Images API (gpt-image-1 / dall-e-3) — سرویس تخصصیِ خودِ
 *      OpenAI برای تولید تصویر (نه یک مدل چت عمومی)؛ فقط اگر کلید
 *      openai_key در تنظیمات وارد شده باشد. هزینه‌بر است (از اعتبار
 *      حساب OpenAI کاربر کم می‌شود).
 *   ۳. OpenRouter (مدل چت با قابلیت خروجی تصویر) — آخرین fallback،
 *      فقط اگر کلید OpenRouter تنظیم شده باشد. این fallback هزینه‌بر
 *      است (از اعتبار OpenRouter کاربر کم می‌شود).
 *
 * ⚠️ حالت «فقط رایگان» (تنظیمات → free_only_mode، پیش‌فرض: روشن): وقتی
 * فعال است، مراحل ۲ و ۳ بالا (هر دو پولی) هرگز اجرا نمی‌شوند — حتی اگر
 * کلیدشان وارد شده باشد. فقط Pollinations امتحان می‌شود؛ اگر آن هم
 * شکست بخورد، پست بدون تصویر شاخص ذخیره می‌شود.
 *
 * هیچ‌کدام از این منابع نباید کل فرآیند تولید مقاله را متوقف کند —
 * شکست تصویر فقط لاگ می‌شود؛ پست بدون تصویر شاخص همچنان ذخیره می‌ماند.
 */
class ACO_Image_Generator {

    const POLLINATIONS_URL = 'https://image.pollinations.ai/prompt/';
    const POLLINATIONS_MODEL = 'flux'; // مدل کیفیت بالای پیش‌فرض Pollinations — صراحتاً پاس داده می‌شود
    const TIMEOUT           = 90;   // ثانیه — enhance=true یک مرحله LLM اضافه سمت سرور دارد، کمی کندتر است
    const WIDTH             = 1024;
    const HEIGHT            = 576;  // نسبت ۱۶:۹ — مناسب تصویر شاخص وبلاگ

    // مدل fallback در OpenRouter (خروجی تصویر) — فقط وقتی کلید OpenRouter موجود باشد
    const OPENROUTER_IMAGE_MODEL = 'google/gemini-2.5-flash-image-preview';
    const OPENROUTER_URL         = 'https://openrouter.ai/api/v1/chat/completions';

    // سرویس تخصصی تولید تصویر OpenAI — fallback میانی، فقط وقتی کلید openai_key موجود باشد
    const OPENAI_IMAGES_URL         = 'https://api.openai.com/v1/images/generations';
    const OPENAI_IMAGE_MODEL_DEFAULT = 'gpt-image-1';

    // ─────────────────────────────────────────────────────────────────
    // ورودی اصلی
    // ─────────────────────────────────────────────────────────────────
    public static function generate_and_attach( int $post_id, string $title, string $keyword, bool $force = false ): array {

        $existing_thumb_id = (int) get_post_thumbnail_id( $post_id );

        if ( $existing_thumb_id && ! $force ) {
            return [ 'success' => true, 'skipped' => true, 'message' => 'پست از قبل تصویر شاخص دارد.' ];
        }

        if ( $existing_thumb_id && $force && self::is_protected_image( $existing_thumb_id ) ) {
            // تصویر با یکی از نام‌ها/کلیدواژه‌های محافظت‌شده در تنظیمات مطابقت دارد
            // (مثلاً عکس یک شخص خاص) — دست‌نخورده باقی می‌ماند.
            return [ 'success' => true, 'skipped' => true, 'message' => 'تصویر شاخص فعلی محافظت‌شده است — بازتولید انجام نشد.' ];
        }

        $prompt = self::build_prompt( $title, $keyword );

        $image_data = self::fetch_from_pollinations( $prompt );
        $source     = 'pollinations';

        // در حالت «فقط رایگان»، هیچ‌کدام از fallbackهای پولی (OpenAI Images،
        // OpenRouter) امتحان نمی‌شوند — حتی اگر کلیدشان تنظیم شده باشد. اگر
        // Pollinations شکست بخورد، پست بدون تصویر شاخص ذخیره می‌شود (شکست فقط لاگ می‌شود).
        $free_only = (bool) ACO_Settings::get( 'free_only_mode' );

        if ( ! $image_data && ! $free_only ) {
            // fallback تخصصی: سرویس واقعی تصویرسازی OpenAI (نه یک مدل چت عمومی)
            $image_data = self::fetch_from_openai_images( $prompt );
            $source     = 'openai';
        }

        if ( ! $image_data && ! $free_only ) {
            $image_data = self::fetch_from_openrouter_fallback( $prompt );
            $source     = 'openrouter';
        }

        if ( ! $image_data ) {
            $reason = $free_only
                ? 'Pollinations شکست خورد و در «حالت فقط رایگان» fallback پولی امتحان نمی‌شود.'
                : 'Pollinations و همه fallback‌ها شکست خوردند.';
            ACO_Logger::error( "تولید تصویر شاخص برای پست #{$post_id} ناموفق بود ({$reason})", 'image' );
            return [ 'success' => false, 'error' => 'تولید تصویر شاخص ناموفق بود.' ];
        }

        $attach_id = self::attach_image_to_post( $post_id, $image_data, $title );
        if ( is_wp_error( $attach_id ) ) {
            ACO_Logger::error( 'خطا در پیوست تصویر شاخص: ' . $attach_id->get_error_message(), 'image' );
            return [ 'success' => false, 'error' => $attach_id->get_error_message() ];
        }

        if ( $existing_thumb_id ) {
            // اگر تصویر قبلی توسط خودِ ACO ساخته شده بود، از کتابخانه رسانه هم حذف می‌شود
            // (فایل یتیم انباشته نشود). اگر دستی آپلود شده بود، فقط از حالت تصویر شاخص
            // خارج می‌شود ولی خودِ فایل در کتابخانه رسانه باقی می‌ماند (بدون حذف دائمی).
            $was_aco_generated = (bool) get_post_meta( $existing_thumb_id, '_aco_generated_image', true );
            if ( $was_aco_generated ) {
                wp_delete_attachment( $existing_thumb_id, true );
            }
        }

        set_post_thumbnail( $post_id, $attach_id );
        update_post_meta( $attach_id, '_wp_attachment_image_alt', sanitize_text_field( $keyword ?: $title ) );
        update_post_meta( $attach_id, '_aco_generated_image', 1 );

        ACO_Logger::success( "تصویر شاخص پست #{$post_id} ساخته و پیوست شد (منبع: {$source}" . ( $existing_thumb_id ? '، جایگزین نسخه قبلی' : '' ) . ')', 'image' );

        return [ 'success' => true, 'attachment_id' => $attach_id, 'source' => $source, 'replaced' => (bool) $existing_thumb_id ];
    }

    // ─────────────────────────────────────────────────────────────────
    // آیا این تصویر با یکی از کلیدواژه‌های محافظت‌شده در تنظیمات مطابقت دارد؟
    // بررسی روی: عنوان attachment، متن alt، و نام فایل — هرکدام که مطابقت داشت کافی است.
    // ─────────────────────────────────────────────────────────────────
    private static function is_protected_image( int $attachment_id ): bool {
        $keywords = (array) ( ACO_Settings::get( 'protected_image_keywords' ) ?? [] );
        $keywords = array_filter( array_map( 'trim', $keywords ) );
        if ( empty( $keywords ) ) {
            return false;
        }

        $title    = get_the_title( $attachment_id );
        $alt      = (string) get_post_meta( $attachment_id, '_wp_attachment_image_alt', true );
        $filepath = get_attached_file( $attachment_id );
        $filename = $filepath ? basename( $filepath ) : '';

        $haystack = mb_strtolower( $title . ' ' . $alt . ' ' . $filename );

        foreach ( $keywords as $kw ) {
            if ( $kw !== '' && mb_strpos( $haystack, mb_strtolower( $kw ) ) !== false ) {
                return true;
            }
        }
        return false;
    }

    // ─────────────────────────────────────────────────────────────────
    // ساخت پرامپت تصویر
    // ─────────────────────────────────────────────────────────────────
    private static function build_prompt( string $title, string $keyword ): string {
        $niche = (string) ( ACO_Settings::get( 'niche' ) ?: '' );
        $base  = $keyword ?: $title;

        $style = 'clean modern editorial illustration style, soft muted colors, high detail, sharp focus, professional blog header image, no text, no watermark, no logos, no signature';

        // FIX: مدل تصویرساز Pollinations پرامپت انگلیسی را خیلی بهتر از فارسی خام
        // می‌فهمد و تصویر مرتبط‌تری تولید می‌کند — قبلاً موضوع فارسی مستقیم داخل
        // پرامپت قرار می‌گرفت و گاهی باعث تصویر کاملاً بی‌ربط می‌شد. حالا ابتدا با
        // همان AI provider تنظیم‌شده یک توصیف تصویری کوتاه انگلیسی از موضوع ساخته
        // می‌شود؛ اگر این تماس شکست خورد (یا کلید AI تنظیم نشده)، به روش قبلی
        // (موضوع فارسی خام) برمی‌گردیم — شکست این مرحله هم نباید تولید تصویر را
        // متوقف کند.
        $english_subject = self::translate_to_visual_description( $base, $niche );

        if ( $english_subject ) {
            return "A conceptual illustration representing: {$english_subject}. {$style}";
        }

        $subject = self::clean_for_prompt( $base );
        $context = $niche !== '' ? ' in the context of ' . self::clean_for_prompt( $niche ) : '';
        return "A conceptual illustration representing: {$subject}{$context}. {$style}";
    }

    // ─────────────────────────────────────────────────────────────────
    // ساخت توصیف تصویری کوتاه انگلیسی از موضوع فارسی — برای پرامپت بهتر Pollinations
    // ─────────────────────────────────────────────────────────────────
    private static function translate_to_visual_description( string $subject, string $niche ): ?string {
        $subject = trim( $subject );
        if ( $subject === '' ) {
            return null;
        }

        $router = new ACO_AI_Router();
        $system = 'You write short, concrete, visual image-generation prompts in English for an AI image generator. '
                . 'Output ONLY the visual scene description — no quotes, no explanation, max 25 words.';
        $user   = "Persian topic/keyword: \"{$subject}\""
                . ( $niche !== '' ? "\nSite niche/context: \"{$niche}\"" : '' )
                . "\n\nWrite one short English visual scene description suitable for a blog header illustration "
                . "representing this topic. Since this may be a sensitive counseling/psychology/health topic, "
                . "use symbolic or metaphorical imagery (calm nature scenes, abstract shapes, gentle human "
                . "silhouettes from a distance, everyday objects) — never depict distressing, medical, or "
                . "identifiable real-person imagery.";

        // timeout و max_tokens کوچک — فقط یک جمله کوتاه لازم است، نباید کل فرآیند را کند کند
        $result = $router->generate( $system, $user, 30, 80 );

        if ( ! $result['success'] || empty( trim( (string) ( $result['text'] ?? '' ) ) ) ) {
            return null;
        }

        $text = trim( (string) $result['text'] );
        $text = trim( $text, "\"'“”‘’ \t\n\r" ); // حذف quote احتمالی دور جمله
        $text = self::clean_for_prompt( $text );

        return $text !== '' ? $text : null;
    }

    private static function clean_for_prompt( string $text ): string {
        // فقط پاک‌سازی کاراکترهای مخرب برای ساختار URL/JSON — بدون نیاز به ترجمه
        // (مدل‌های تصویرساز زیرین Pollinations و OpenRouter چندزبانه‌اند)
        $text = preg_replace( '/[\r\n"\\\\]+/', ' ', $text );
        return trim( (string) $text );
    }

    // ─────────────────────────────────────────────────────────────────
    // منبع ۱: Pollinations.ai (رایگان، بدون کلید)
    // ─────────────────────────────────────────────────────────────────
    private static function fetch_from_pollinations( string $prompt ): ?string {
        $url = self::POLLINATIONS_URL . rawurlencode( $prompt )
             . '?width=' . self::WIDTH
             . '&height=' . self::HEIGHT
             . '&model=' . self::POLLINATIONS_MODEL
             . '&enhance=true'
             . '&private=true'
             . '&nologo=true'
             . '&seed=' . wp_rand( 1, 999999 );

        $response = wp_remote_get( $url, [
            'timeout'     => self::TIMEOUT,
            'redirection' => 3,
        ] );

        if ( is_wp_error( $response ) ) {
            ACO_Logger::error( 'Pollinations خطا: ' . $response->get_error_message(), 'image' );
            return null;
        }

        $code = (int) wp_remote_retrieve_response_code( $response );
        $body = wp_remote_retrieve_body( $response );

        if ( $code !== 200 || empty( $body ) || strlen( $body ) < 1000 ) {
            return null;
        }

        $content_type = wp_remote_retrieve_header( $response, 'content-type' );
        if ( is_string( $content_type ) && strpos( $content_type, 'image/' ) !== 0 ) {
            return null; // احتمالاً صفحه خطا/HTML برگشته، نه تصویر
        }

        return $body;
    }

    // ─────────────────────────────────────────────────────────────────
    // منبع ۲ (fallback تخصصی): OpenAI Images API — فقط اگر کلید openai_key موجود باشد
    // برخلاف fallback بعدی (OpenRouter که یک مدل چت چندمنظوره است)، این یکی
    // مستقیماً از endpoint اختصاصی تصویرسازی OpenAI (gpt-image-1 / dall-e-3)
    // استفاده می‌کند — یعنی سرویسی که واقعاً برای تولید تصویر ساخته شده.
    // ─────────────────────────────────────────────────────────────────
    private static function fetch_from_openai_images( string $prompt ): ?string {
        $api_key = (string) ACO_Settings::get( 'openai_key' );
        if ( trim( $api_key ) === '' ) {
            return null; // کلید OpenAI تنظیم نشده — این fallback در دسترس نیست
        }

        $model = (string) ( ACO_Settings::get( 'openai_image_model' ) ?: self::OPENAI_IMAGE_MODEL_DEFAULT );

        $body = wp_json_encode( [
            'model'  => $model,
            'prompt' => $prompt,
            'size'   => '1024x1024', // نزدیک‌ترین سایز استاندارد؛ اندازه نهایی هنگام آپلود مدیریت می‌شود
            'n'      => 1,
        ] );

        $response = wp_remote_post( self::OPENAI_IMAGES_URL, [
            'timeout'     => self::TIMEOUT,
            'redirection' => 3,
            'headers'     => [
                'Content-Type'  => 'application/json',
                'Authorization' => 'Bearer ' . $api_key,
            ],
            'body' => $body,
        ] );

        if ( is_wp_error( $response ) ) {
            ACO_Logger::error( 'OpenAI Images (fallback تصویر تخصصی) خطا: ' . $response->get_error_message(), 'image' );
            return null;
        }

        $code = (int) wp_remote_retrieve_response_code( $response );
        $data = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( $code !== 200 || empty( $data['data'][0] ) ) {
            $api_msg = $data['error']['message'] ?? '';
            ACO_Logger::error(
                "OpenAI Images خطای HTTP {$code}" . ( $api_msg !== '' ? ": {$api_msg}" : ' (بدنه پاسخ نامعتبر)' ),
                'image'
            );
            return null;
        }

        $item = $data['data'][0];

        // gpt-image-1 معمولاً b64_json برمی‌گرداند
        if ( ! empty( $item['b64_json'] ) && is_string( $item['b64_json'] ) ) {
            $decoded = base64_decode( $item['b64_json'], true );
            return $decoded ?: null;
        }

        // dall-e-3 (یا حالت‌های دیگر) معمولاً یک url موقت برمی‌گرداند — باید دانلود شود
        if ( ! empty( $item['url'] ) && is_string( $item['url'] ) ) {
            $img_response = wp_remote_get( $item['url'], [ 'timeout' => self::TIMEOUT, 'redirection' => 3 ] );
            if ( is_wp_error( $img_response ) ) {
                ACO_Logger::error( 'OpenAI Images: دانلود تصویر از url ناموفق بود — ' . $img_response->get_error_message(), 'image' );
                return null;
            }
            $img_body = wp_remote_retrieve_body( $img_response );
            if ( empty( $img_body ) || strlen( $img_body ) < 1000 ) {
                return null;
            }
            return $img_body;
        }

        return null;
    }

    // ─────────────────────────────────────────────────────────────────
    // منبع ۳ (fallback عمومی): OpenRouter — فقط اگر کلید موجود باشد
    // ─────────────────────────────────────────────────────────────────
    private static function fetch_from_openrouter_fallback( string $prompt ): ?string {
        $api_key = (string) ACO_Settings::get( 'openrouter_key' );
        if ( trim( $api_key ) === '' ) {
            return null; // کلید تنظیم نشده — fallback در دسترس نیست
        }

        $body = wp_json_encode( [
            'model'      => self::OPENROUTER_IMAGE_MODEL,
            'messages'   => [
                [ 'role' => 'user', 'content' => $prompt ],
            ],
            'modalities' => [ 'image', 'text' ],
        ] );

        $response = wp_remote_post( self::OPENROUTER_URL, [
            'timeout'     => self::TIMEOUT,
            'redirection' => 3,
            'headers'     => [
                'Content-Type'  => 'application/json',
                'Authorization' => 'Bearer ' . $api_key,
                'HTTP-Referer'  => home_url(),
            ],
            'body' => $body,
        ] );

        if ( is_wp_error( $response ) ) {
            ACO_Logger::error( 'OpenRouter (fallback تصویر) خطا: ' . $response->get_error_message(), 'image' );
            return null;
        }

        $code = (int) wp_remote_retrieve_response_code( $response );
        $data = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( $code !== 200 || empty( $data ) ) {
            return null;
        }

        $image_url = $data['choices'][0]['message']['images'][0]['image_url']['url'] ?? null;
        if ( ! is_string( $image_url ) || strpos( $image_url, 'base64,' ) === false ) {
            return null;
        }

        $base64  = substr( $image_url, strpos( $image_url, 'base64,' ) + 7 );
        $decoded = base64_decode( $base64, true );

        return $decoded ?: null;
    }

    // ─────────────────────────────────────────────────────────────────
    // پیوست تصویر به کتابخانه رسانه و اتصال به پست
    // ─────────────────────────────────────────────────────────────────
    private static function attach_image_to_post( int $post_id, string $image_data, string $title ) {
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $filename = 'aco-featured-' . $post_id . '-' . time() . '.jpg';

        $upload = wp_upload_bits( $filename, null, $image_data );
        if ( ! empty( $upload['error'] ) ) {
            return new WP_Error( 'aco_image_upload_failed', $upload['error'] );
        }

        $filetype = wp_check_filetype( $upload['file'], null );

        $attachment = [
            'post_mime_type' => $filetype['type'] ?: 'image/jpeg',
            'post_title'     => sanitize_text_field( $title ),
            'post_content'   => '',
            'post_status'    => 'inherit',
            'post_parent'    => $post_id,
        ];

        $attach_id = wp_insert_attachment( $attachment, $upload['file'], $post_id, true );
        if ( is_wp_error( $attach_id ) ) {
            return $attach_id;
        }

        $attach_data = wp_generate_attachment_metadata( $attach_id, $upload['file'] );
        wp_update_attachment_metadata( $attach_id, $attach_data );

        return $attach_id;
    }
}
