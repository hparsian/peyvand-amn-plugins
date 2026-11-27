<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * ACO_AI_Router
 * مدیریت کامل ارتباط با API های هوش مصنوعی
 * پشتیبانی از: OpenRouter، DeepSeek، OpenAI، Google Gemini، Anthropic Claude
 * ویژگی‌ها: retry خودکار، تشخیص دقیق خطا، validation کلید، timeout مناسب
 *
 * ⚠️ حالت «فقط رایگان» (تنظیمات → free_only_mode، پیش‌فرض: روشن): وقتی فعال
 * است، این کلاس صرف‌نظر از provider/fallback_provider ذخیره‌شده یا حتی
 * override صریح، همیشه فقط OpenRouter با یک مدل رایگان (":free" یا
 * Free Models Router) را فراخوانی می‌کند. هیچ ارائه‌دهنده پولی (DeepSeek،
 * OpenAI، Gemini، Anthropic) در این حالت هرگز درخواستی دریافت نمی‌کند —
 * حتی اگر کلیدشان در تنظیمات وارد شده باشد. جزئیات در __construct().
 */
class ACO_AI_Router {

    // ─── تنظیمات ────────────────────────────────────────────────────
    const TIMEOUT        = 120;  // ثانیه — timeout پیش‌فرض (مرحله تحقیق و تست)
    const TIMEOUT_WRITE  = 150;  // ثانیه — timeout مرحله نوشتن مقاله (خروجی طولانی‌تر)
    const MAX_RETRIES       = 2;    // تعداد تلاش مجدد
    const RETRY_DELAY       = 1;    // ثانیه بین هر تلاش (پیش‌فرض)
    const RETRY_DELAY_429   = 15;   // ثانیه تأخیر مخصوص خطای rate limit (429) — فقط برای retry روی همان مدل
                                     // (باید صبر کند تا پنجره rate-limit همان مدل باز شود)
    const FREE_ROUTER_ROUNDS = 2;   // تعداد تلاش‌های اضافی روی Free Models Router وقتی مدل رایگان اصلی rate-limit شد؛
                                     // هر بار این router خودش یک مدل رایگان (احتمالاً متفاوت) از بین مدل‌های در
                                     // دسترس انتخاب می‌کند — یعنی این عملاً «سویچ بین نسخه‌های رایگان» است.
    const FREE_ROUTER_RETRY_DELAY = 3; // ثانیه تأخیر بین دورهای Free Models Router — کوتاه‌تر از RETRY_DELAY_429
                                        // چون این هر بار روی یک مدل متفاوت (نه لزوماً rate-limit شده) امتحان می‌کند،
                                        // نیازی به صبر طولانی برای باز شدن پنجره‌ی rate-limit همان مدل نیست.
                                        // BUG FIX: قبلاً از RETRY_DELAY_429 (۱۵ ثانیه) در این حلقه هم استفاده می‌شد؛
                                        // با FREE_ROUTER_ROUNDS بالاتر از ۱، جمع تأخیرها (۳۰s مدل اصلی + تا ۴۵s
                                        // این حلقه) از محدودیت زمان اجرای هاست/پراکسی رد می‌شد و اتصال قطع می‌شد
                                        // (کاربر خطای شبکه می‌دید، نه پیام JSON مناسب). با تأخیر کوتاه‌تر این ریسک
                                        // به‌طور قابل توجهی کم می‌شود.
    const MAX_TOKENS       = 4000;   // تحقیق و تست
    const MAX_TOKENS_WRITE = 8000;   // نوشتن مقاله (۱۵۰۰+ کلمه ≈ ۲۲۵۰+ توکن + overhead)
    const TEMPERATURE    = 0.7;

    // ─── endpoints ──────────────────────────────────────────────────
    const OPENROUTER_URL = 'https://openrouter.ai/api/v1/chat/completions';
    const DEEPSEEK_URL   = 'https://api.deepseek.com/chat/completions';
    const OPENAI_URL     = 'https://api.openai.com/v1/chat/completions';
    const GEMINI_URL     = 'https://generativelanguage.googleapis.com/v1beta/models/';
    const ANTHROPIC_URL  = 'https://api.anthropic.com/v1/messages';
    const ANTHROPIC_VERSION = '2023-06-01'; // هدر anthropic-version — ثابت مستندات فعلی Anthropic

    // Free Models Router خود OpenRouter — به‌جای یک مدل خاص، خودش هر بار یکی از
    // مدل‌های رایگانِ در دسترس (که مدام تغییر می‌کنند) را انتخاب می‌کند.
    // به‌عنوان fallback خودکار وقتی مدل رایگان انتخابی کاربر rate-limit/در دسترس نبود استفاده می‌شود.
    const FREE_MODELS_ROUTER = 'openrouter/free';

    // ارائه‌دهندگان معتبر — یک منبع واحد به‌جای تکرار در چند جای کلاس
    const PROVIDERS = [ 'openrouter', 'deepseek', 'openai', 'gemini', 'anthropic' ];

    // ─── مدل‌های پیش‌فرض (افزونه خودش انتخاب می‌کند) ──────────────
    // ⚠️ نام دقیق مدل‌ها (به‌خصوص gemini/anthropic) در طول زمان تغییر می‌کند —
    // قبل از استفاده در سایت واقعی، از صفحه «تست اتصال» تنظیمات مطمئن شوید
    // و در صورت نیاز از تنظیمات، مدل جدیدتر را جایگزین کنید.
    const MODELS = [
        'openrouter' => 'google/gemma-4-31b-it:free',
        'deepseek'   => 'deepseek-chat',        // deepseek-v3 — مدل رسمی پایدار
        'openai'     => 'gpt-4o-mini',          // ارزان و سریع — برای مقاله‌نویسی حجم بالا مناسب
        'gemini'     => 'gemini-3.8-flash',
        'anthropic'  => 'claude-3-5-haiku-20241022',
    ];

    private string $provider;
    private string $model;
    private string $api_key;

    // وقتی true باشد یعنی این نمونه (instance) خودش به‌عنوان «ارائه‌دهنده جایگزین»
    // در حال تلاش است — از فراخوانی دوباره try_fallback_provider() روی همین نمونه
    // جلوگیری می‌کند تا حلقه بی‌نهایت پیش نیاید (حداکثر یک سطح fallback مجاز است).
    private bool $is_fallback_call = false;

    /**
     * @param array|null $override در صورت پاس داده شدن، به‌جای خواندن تنظیمات
     *        ذخیره‌شده در دیتابیس، از این مقادیر موقت استفاده می‌شود
     *        (مثلاً برای تست اتصال با کلیدی که هنوز ذخیره نشده).
     *        کلیدهای قابل قبول: provider, openrouter_key, deepseek_key
     */
    public function __construct( ?array $override = null ) {
        $provider = $override['provider'] ?? ( ACO_Settings::get( 'provider' ) ?: 'openrouter' );
        if ( ! in_array( $provider, self::PROVIDERS, true ) ) {
            $provider = 'openrouter';
        }

        $this->provider = $provider;

        // مدل از تنظیمات خوانده می‌شود؛ در صورت خالی بودن، مدل پیش‌فرض استفاده می‌شود
        if ( $override !== null && ! empty( $override['model'] ) ) {
            $this->model = sanitize_text_field( $override['model'] );
        } else {
            $this->model = ACO_Settings::get( "{$this->provider}_model" ) ?: self::MODELS[ $this->provider ];
        }
        if ( $this->provider === 'gemini' ) {
            $this->model = ACO_Settings::normalize_gemini_model( $this->model );
        }

        $key_field = "{$this->provider}_key";
        if ( $override !== null ) {
            $this->api_key = (string) ( $override[ $key_field ] ?? '' );
        } else {
            $this->api_key = (string) ACO_Settings::get( $key_field );
        }

        // ─── اجرای «حالت فقط رایگان» ────────────────────────────────
        // اگر روشن باشد، صرف‌نظر از هر provider/override بالا (حتی override صریح
        // تست اتصال با یک ارائه‌دهنده پولی)، این نمونه همیشه قفل می‌شود روی
        // OpenRouter + یک مدل رایگان. این enforcement در سطح constructor است تا
        // هیچ مسیر کد دیگری (fallback، تست اتصال، تولید تصویر و...) نتواند از آن
        // عبور کند.
        if ( self::is_free_only_mode() ) {
            $this->provider = 'openrouter';
            $or_model        = $override['openrouter_model'] ?? ( ACO_Settings::get( 'openrouter_model' ) ?: self::MODELS['openrouter'] );
            $this->model     = self::resolve_free_model( (string) $or_model );
            $this->api_key   = (string) ( $override['openrouter_key'] ?? ACO_Settings::get( 'openrouter_key' ) );
        }
    }

    // آیا «حالت فقط رایگان» در تنظیمات روشن است؟
    public static function is_free_only_mode(): bool {
        return (bool) ACO_Settings::get( 'free_only_mode' );
    }

    // اگر مدل داده‌شده رایگان است همان را برگردان؛ در غیر این صورت (کاربر یک مدل
    // پولی روی OpenRouter وارد کرده) به مدل رایگان پیش‌فرض برگرد تا هیچ درخواست
    // پولی در حالت free-only رد نشود.
    private static function resolve_free_model( string $model ): string {
        return self::looks_like_free_model( $model ) ? $model : self::MODELS['openrouter'];
    }

    // بررسی مشترک (static) این‌که آیا یک نام مدل، مدل رایگان OpenRouter است؟
    private static function looks_like_free_model( string $model ): bool {
        return $model === self::FREE_MODELS_ROUTER || substr( $model, -5 ) === ':free';
    }

    // ─────────────────────────────────────────────────────────────────
    // متد اصلی
    // ─────────────────────────────────────────────────────────────────
    public function generate( string $system_prompt, string $user_prompt, int $timeout = self::TIMEOUT, int $max_tokens = self::MAX_TOKENS ): array {

        // validation کلید
        $key_error = $this->validate_api_key();
        if ( $key_error ) {
            // حتی اگر کلید ارائه‌دهنده اصلی اصلاً تنظیم نشده باشد، اگر ارائه‌دهنده
            // جایگزین (مثلاً ChatGPT/OpenAI) تنظیم شده باشد همان درخواست را با آن امتحان کن
            $fallback = $this->try_fallback_provider( $system_prompt, $user_prompt, $timeout, $max_tokens, $key_error );
            return $fallback ?? [ 'success' => false, 'error' => $key_error ];
        }

        // ساخت پیام‌ها
        $messages = [
            [ 'role' => 'system', 'content' => trim( $system_prompt ) ],
            [ 'role' => 'user',   'content' => trim( $user_prompt )   ],
        ];

        // ارسال با retry (شامل fallback داخلی مدل رایگان OpenRouter در صورت وجود)
        $result = $this->send_with_retry( $messages, $timeout, $max_tokens );
        if ( $result['success'] ) {
            return $result;
        }

        // ارائه‌دهنده اصلی (بعد از تمام تلاش‌های داخلی) شکست خورد — اگر یک ارائه‌دهنده
        // جایگزین (fallback_provider) در تنظیمات با کلید معتبر تنظیم شده، همین درخواست
        // (چه تولید مقاله چه هر متن دیگر) با آن دوباره امتحان می‌شود.
        $fallback = $this->try_fallback_provider( $system_prompt, $user_prompt, $timeout, $max_tokens, $result['error'] ?? '' );
        return $fallback ?? $result;
    }

    // ─────────────────────────────────────────────────────────────────
    // تلاش با ارائه‌دهنده جایگزین (fallback_provider در تنظیمات)
    // فقط وقتی واقعاً اجرا می‌شود که:
    //   ۱. یک ارائه‌دهنده جایگزین در تنظیمات انتخاب شده باشد،
    //   ۲. با ارائه‌دهنده اصلی همین درخواست فرق داشته باشد (وگرنه بی‌معنی است)،
    //   ۳. کلید API آن ارائه‌دهنده هم تنظیم شده باشد،
    //   ۴. این نمونه خودش از قبل در «حالت fallback» نباشد (جلوگیری از حلقه).
    // برمی‌گرداند: نتیجه موفق fallback، یا null اگر fallback در دسترس نبود/اجرا نشد
    // (شکست خودِ fallback هم null برمی‌گرداند تا خطای اصلی برای کاربر نمایش داده شود).
    // ─────────────────────────────────────────────────────────────────
    private function try_fallback_provider( string $system_prompt, string $user_prompt, int $timeout, int $max_tokens, string $primary_error ): ?array {
        if ( $this->is_fallback_call ) {
            return null;
        }

        // در حالت «فقط رایگان»، ارائه‌دهنده جایگزین (که همیشه یک ارائه‌دهنده پولی
        // دیگر است) هرگز امتحان نمی‌شود — این نمونه از قبل خودش قفل‌شده روی
        // OpenRouter رایگان است (fallback داخلی مدل رایگان در send_with_retry
        // همچنان کار می‌کند، چون آن هم رایگان می‌ماند).
        if ( self::is_free_only_mode() ) {
            return null;
        }

        $fallback_provider = (string) ( ACO_Settings::get( 'fallback_provider' ) ?: '' );
        if ( $fallback_provider === '' || $fallback_provider === $this->provider ) {
            return null;
        }
        if ( ! in_array( $fallback_provider, self::PROVIDERS, true ) ) {
            return null;
        }

        $fallback_key = (string) ACO_Settings::get( "{$fallback_provider}_key" );
        if ( trim( $fallback_key ) === '' ) {
            return null; // کلید ارائه‌دهنده جایگزین تنظیم نشده — چیزی برای تلاش نیست
        }

        $fallback_model = (string) ( ACO_Settings::get( "{$fallback_provider}_model" ) ?: self::MODELS[ $fallback_provider ] );

        ACO_Logger::info(
            "⤵️ ارائه‌دهنده «{$this->provider}» پاسخ نداد" . ( $primary_error !== '' ? " ({$primary_error})" : '' )
            . " — تلاش خودکار با ارائه‌دهنده جایگزین «{$fallback_provider}»...",
            'ai_router'
        );

        $fallback_router = new self( [
            'provider'                    => $fallback_provider,
            'model'                       => $fallback_model,
            "{$fallback_provider}_key"    => $fallback_key,
        ] );
        $fallback_router->is_fallback_call = true;

        $result = $fallback_router->generate( $system_prompt, $user_prompt, $timeout, $max_tokens );

        if ( $result['success'] ) {
            $result['fallback_provider_used']  = $fallback_provider;
            $result['fallback_model_used']     = $fallback_router->get_model();
            $result['primary_provider_failed'] = $this->provider;
            ACO_Logger::success( "✅ ارائه‌دهنده جایگزین «{$fallback_provider}» با موفقیت پاسخ داد.", 'ai_router' );
            return $result;
        }

        ACO_Logger::error(
            "❌ ارائه‌دهنده جایگزین «{$fallback_provider}» هم شکست خورد: " . ( $result['error'] ?? 'خطای نامشخص' ),
            'ai_router'
        );
        return null;
    }

    // ─────────────────────────────────────────────────────────────────
    // Validation کلید API
    // ─────────────────────────────────────────────────────────────────
    private function validate_api_key(): ?string {
        if ( empty( trim( $this->api_key ) ) ) {
            return 'کلید API تنظیم نشده است. لطفاً از صفحه تنظیمات کلید را وارد کنید.';
        }
        return null;
    }

    // ─────────────────────────────────────────────────────────────────
    // ارسال با retry خودکار + fallback خودکار مدل رایگان (فقط OpenRouter)
    // ─────────────────────────────────────────────────────────────────
    private function send_with_retry( array $messages, int $timeout = self::TIMEOUT, int $max_tokens = self::MAX_TOKENS ): array {
        $result = $this->attempt_with_model( $this->model, $messages, $timeout, $max_tokens, self::MAX_RETRIES );

        if ( $result['success'] ) {
            return $result;
        }

        // FIX: fallback خودکار به «Free Models Router» خود OpenRouter (model slug: openrouter/free).
        // این router رسمی خود OpenRouter است و خودش از بین مدل‌های رایگانِ در دسترس (که مدام
        // عوض می‌شوند) یکی را انتخاب می‌کند — پس نیازی به نگه‌داشتن یک فهرست دستی و شکننده از
        // نام مدل‌ها در کد یا تنظیمات نیست. فقط وقتی امتحان می‌شود که:
        //   ۱. پروایدر OpenRouter باشد،
        //   ۲. مدل فعلی خودش یک مدل رایگان (:free) باشد (اگر کاربر مدل پولی انتخاب کرده، fallback
        //      به رایگان معنی ندارد — کیفیت/رفتار متفاوت است و باید صریح انتخاب شود)،
        //   ۳. خطا مربوط به همان مدل خاص باشد (rate limit یا در دسترس نبودن) — نه مشکل کلید/اعتبار
        //      حساب (401/402) که تعویض مدل هم آن را حل نمی‌کند.
        //
        // FIX (سویچ بین نسخه‌های رایگان): قبلاً فقط یک بار Free Models Router امتحان می‌شد.
        // حالا با FREE_ROUTER_ROUNDS چند بار پشت سر هم امتحان می‌شود؛ چون هر بار احتمالاً یک
        // مدل رایگان متفاوت انتخاب می‌کند (پیاده‌سازی خود attempt_with_model)، این عملاً باعث
        // می‌شود بین چند «نسخه رایگان» مختلف سویچ کنیم تا یکی جواب بدهد، نه فقط یک تلاش تکی.
        $already_using_router = ( $this->model === self::FREE_MODELS_ROUTER );
        if ( $this->provider === 'openrouter' && $this->is_free_model( $this->model ) && ! $already_using_router
             && $this->should_try_free_router( $result ) ) {

            $original_model = $this->model;
            $this->model    = self::FREE_MODELS_ROUTER;

            ACO_Logger::info( "⤵️ مدل «{$original_model}» با خطا مواجه شد — تلاش با Free Models Router (openrouter/free)، حداکثر " . ( self::FREE_ROUTER_ROUNDS + 1 ) . ' بار (سویچ بین نسخه‌های رایگان)...', 'ai_router' );

            $fallback_result = $this->attempt_with_model( $this->model, $messages, $timeout, $max_tokens, self::FREE_ROUTER_ROUNDS, self::FREE_ROUTER_RETRY_DELAY );
            $this->model = $original_model; // بازگردانی مدل اصلی برای فراخوانی‌های بعدی همین شیء

            if ( $fallback_result['success'] ) {
                // model_used از پاسخ واقعی API می‌آید (کدام مدل رایگان واقعاً پاسخ داد)؛
                // فقط اگر به هر دلیل خالی بود، به نام router برمی‌گردیم.
                if ( empty( $fallback_result['model_used'] ) ) {
                    $fallback_result['model_used'] = self::FREE_MODELS_ROUTER;
                }
                $fallback_result['fallback_used']  = true;
                $fallback_result['original_model'] = $original_model;
                ACO_Logger::success( '✅ Free Models Router با موفقیت پاسخ داد (مدل: ' . $fallback_result['model_used'] . ')', 'ai_router' );
                return $fallback_result;
            }

            // همه‌ی نسخه‌های رایگان (مدل اصلی + چند دور Free Models Router) شکست خوردند.
            // اگر علت همچنان rate limit/عدم دسترسی موقت بود (نه مشکل کلید/اعتبار)، این را با
            // پرچم rate_limit_exhausted مشخص می‌کنیم تا لایه‌ی بالاتر (AJAX) به‌جای نمایش خطا
            // به کاربر، درخواست را به صف پردازش خودکار بسپارد.
            $final_error = [ 'success' => false, 'error' => $fallback_result['error'] ?? $result['error'] ];
            if ( $this->should_try_free_router( $fallback_result ) ) {
                $final_error['rate_limit_exhausted'] = true;
            }
            return $final_error;
        }

        $result_out = [ 'success' => false, 'error' => $result['error'] ];
        if ( $this->is_free_model( $this->model ) && $this->should_try_free_router( $result ) ) {
            $result_out['rate_limit_exhausted'] = true;
        }
        return $result_out;
    }

    // آیا این مدل یک مدل رایگان OpenRouter است؟ (پسوند :free یا خودِ router رایگان)
    private function is_free_model( string $model ): bool {
        return self::looks_like_free_model( $model );
    }

    // آیا ارزش امتحان fallback به Free Models Router را دارد؟
    // فقط خطاهای مختص همان مدل (rate limit، مدل پیدا نشد/در دسترس نیست) — نه مشکل کلید/اعتبار حساب.
    private function should_try_free_router( array $result ): bool {
        if ( ! isset( $result['http_code'] ) ) {
            return true; // خطای اتصال (بدون کد) — احتمال دارد مربوط به آن endpoint خاص مدل باشد
        }
        return ! in_array( (int) $result['http_code'], [ 401, 402 ], true );
    }

    // منطق retry قبلی، به یک متد جدا با پارامتر model و max_retries منتقل شد
    // تا هم برای مدل اصلی و هم برای fallback (با تعداد retry متفاوت) قابل استفاده باشد.
    // $retry_delay_429: تأخیر (ثانیه) هنگام retry بعد از خطای 429 — قابل override برای
    // حلقه‌ی Free Models Router که نیازی به تأخیر طولانیِ retry-روی-همان-مدل ندارد.
    private function attempt_with_model( string $model, array $messages, int $timeout, int $max_tokens, int $max_retries, int $retry_delay_429 = self::RETRY_DELAY_429 ): array {
        $prev_model   = $this->model;
        $this->model  = $model;
        $last_result  = [ 'success' => false, 'error' => 'خطای ناشناخته.' ];

        for ( $attempt = 1; $attempt <= $max_retries + 1; $attempt++ ) {

            if ( $attempt > 1 ) {
                // برای خطای rate limit (429) تأخیر بیشتر — ارائه‌دهنده نیاز به زمان دارد
                $delay = ( isset( $last_result['http_code'] ) && $last_result['http_code'] === 429 )
                    ? $retry_delay_429
                    : self::RETRY_DELAY;
                sleep( $delay );
            }

            switch ( $this->provider ) {
                case 'deepseek':
                    $result = $this->call_deepseek( $messages, $timeout, $max_tokens );
                    break;
                case 'openai':
                    $result = $this->call_openai( $messages, $timeout, $max_tokens );
                    break;
                case 'gemini':
                    $result = $this->call_gemini( $messages, $timeout, $max_tokens );
                    break;
                case 'anthropic':
                    $result = $this->call_anthropic( $messages, $timeout, $max_tokens );
                    break;
                default:
                    $result = $this->call_openrouter( $messages, $timeout, $max_tokens );
            }

            if ( $result['success'] ) {
                if ( $attempt > 1 ) {
                    $result['retry_count'] = $attempt - 1;
                }
                $this->model = $prev_model;
                return $result;
            }

            $last_result = $result;

            // خطاهایی که retry فایده ندارد (بر اساس کد HTTP واقعی)
            if ( $this->is_fatal_error( $result ) ) {
                break;
            }
        }

        $this->model = $prev_model;
        return [ 'success' => false, 'error' => $last_result['error'], 'http_code' => $last_result['http_code'] ?? null ];
    }

    // ─────────────────────────────────────────────────────────────────
    // OpenRouter
    // ─────────────────────────────────────────────────────────────────
    private function call_openrouter( array $messages, int $timeout = self::TIMEOUT, int $max_tokens = self::MAX_TOKENS ): array {
        $body = wp_json_encode( [
            'model'       => $this->model,
            'messages'    => $messages,
            'max_tokens'  => $max_tokens,
            'temperature' => self::TEMPERATURE,
        ] );

        $response = wp_remote_post( self::OPENROUTER_URL, [
            'timeout'     => $timeout,
            'redirection' => 3,
            'headers'     => [
                'Content-Type'       => 'application/json',
                'Authorization'      => 'Bearer ' . $this->api_key,
                'HTTP-Referer'       => home_url(),
                'X-OpenRouter-Title' => get_bloginfo( 'name' ),
            ],
            'body' => $body,
        ] );

        return $this->parse_response( $response, 'openrouter' );
    }

    // ─────────────────────────────────────────────────────────────────
    // DeepSeek
    // ─────────────────────────────────────────────────────────────────
    private function call_deepseek( array $messages, int $timeout = self::TIMEOUT, int $max_tokens = self::MAX_TOKENS ): array {
        $body_arr = [
            'model'       => $this->model,
            'messages'    => $messages,
            'max_tokens'  => $max_tokens,
            'temperature' => self::TEMPERATURE,
            'stream'      => false,
        ];

        // thinking فقط برای مدل‌های reasoning (R1) پشتیبانی می‌شود
        // روی deepseek-chat (V3) ارسال این پارامتر باعث خطای 400 می‌شود
        // BUG-08 FIX: str_contains → strpos (PHP 7.4 compatible)
        if ( strpos( $this->model, 'reasoner' ) !== false || strpos( $this->model, 'r1' ) !== false ) {
            $body_arr['thinking'] = [ 'type' => 'disabled' ];
        }

        $body = wp_json_encode( $body_arr );

        $response = wp_remote_post( self::DEEPSEEK_URL, [
            'timeout'     => $timeout,
            'redirection' => 3,
            'headers'     => [
                'Content-Type'  => 'application/json',
                'Authorization' => 'Bearer ' . $this->api_key,
                'Accept'        => 'application/json',
            ],
            'body' => $body,
        ] );

        return $this->parse_response( $response, 'deepseek' );
    }

    // ─────────────────────────────────────────────────────────────────
    // OpenAI — همان فرمت درخواست/پاسخ OpenRouter/DeepSeek (OpenAI-compatible)
    // ─────────────────────────────────────────────────────────────────
    private function call_openai( array $messages, int $timeout = self::TIMEOUT, int $max_tokens = self::MAX_TOKENS ): array {
        $body = wp_json_encode( [
            'model'       => $this->model,
            'messages'    => $messages,
            'max_tokens'  => $max_tokens,
            'temperature' => self::TEMPERATURE,
        ] );

        $response = wp_remote_post( self::OPENAI_URL, [
            'timeout'     => $timeout,
            'redirection' => 3,
            'headers'     => [
                'Content-Type'  => 'application/json',
                'Authorization' => 'Bearer ' . $this->api_key,
            ],
            'body' => $body,
        ] );

        return $this->parse_response( $response, 'openai' );
    }

    // ─────────────────────────────────────────────────────────────────
    // Google Gemini — فرمت درخواست/پاسخ کاملاً متفاوت است:
    //   - کلید API در query string ارسال می‌شود (نه هدر Authorization)
    //   - پیام‌ها زیر contents/parts هستند، نقش «assistant» اینجا «model» است
    //   - system prompt یک فیلد جدا (systemInstruction) است، نه یک پیام role=system
    // ─────────────────────────────────────────────────────────────────
    private function call_gemini( array $messages, int $timeout = self::TIMEOUT, int $max_tokens = self::MAX_TOKENS ): array {
        $system_prompt = '';
        $contents      = [];
        foreach ( $messages as $m ) {
            if ( $m['role'] === 'system' ) {
                $system_prompt = $m['content'];
                continue;
            }
            $contents[] = [
                'role'  => $m['role'] === 'assistant' ? 'model' : 'user',
                'parts' => [ [ 'text' => $m['content'] ] ],
            ];
        }

        $model = ACO_Settings::normalize_gemini_model( $this->model );
        $gen_config = [
            'maxOutputTokens' => $max_tokens,
        ];

        if ( preg_match( '/^gemini-4/i', $model ) ) {
            // مدل‌های Gemini 4 پارامتر temperature را نمی‌پذیرند و به جای thinkingBudget از thinkingLevel (حداقل LOW) استفاده می‌کنند.
            $gen_config['maxOutputTokens'] = max( 768, $max_tokens );
            $gen_config['thinkingConfig']  = [ 'thinkingLevel' => 'LOW' ];
        } elseif ( preg_match( '/^gemini-3/i', $model ) ) {
            $gen_config['temperature']    = self::TEMPERATURE;
            $gen_config['thinkingConfig'] = [ 'thinkingLevel' => 'LOW' ];
        } else {
            $gen_config['temperature'] = self::TEMPERATURE;
        }

        $body_arr = [
            'contents'         => $contents,
            'generationConfig' => $gen_config,
        ];
        if ( $system_prompt !== '' ) {
            $body_arr['systemInstruction'] = [ 'parts' => [ [ 'text' => $system_prompt ] ] ];
        }

        $url = self::GEMINI_URL . rawurlencode( $model ) . ':generateContent?key=' . rawurlencode( $this->api_key );

        $response = wp_remote_post( $url, [
            'timeout'     => $timeout,
            'redirection' => 3,
            'headers'     => [ 'Content-Type' => 'application/json' ],
            'body'        => wp_json_encode( $body_arr ),
        ] );

        return $this->parse_response( $response, 'gemini' );
    }

    // ─────────────────────────────────────────────────────────────────
    // Anthropic (Claude) — احراز هویت با هدر x-api-key (نه Bearer)، و
    // system prompt یک فیلد جدا در بدنه است، نه یک پیام role=system.
    // ─────────────────────────────────────────────────────────────────
    private function call_anthropic( array $messages, int $timeout = self::TIMEOUT, int $max_tokens = self::MAX_TOKENS ): array {
        $system_prompt   = '';
        $anthro_messages = [];
        foreach ( $messages as $m ) {
            if ( $m['role'] === 'system' ) {
                $system_prompt = $m['content'];
                continue;
            }
            $anthro_messages[] = [ 'role' => $m['role'], 'content' => $m['content'] ];
        }

        $body = wp_json_encode( [
            'model'       => $this->model,
            'max_tokens'  => $max_tokens,
            'temperature' => self::TEMPERATURE,
            'system'      => $system_prompt,
            'messages'    => $anthro_messages,
        ] );

        $response = wp_remote_post( self::ANTHROPIC_URL, [
            'timeout'     => $timeout,
            'redirection' => 3,
            'headers'     => [
                'Content-Type'      => 'application/json',
                'x-api-key'         => $this->api_key,
                'anthropic-version' => self::ANTHROPIC_VERSION,
            ],
            'body' => $body,
        ] );

        return $this->parse_response( $response, 'anthropic' );
    }

    // ─────────────────────────────────────────────────────────────────
    // پارس پاسخ با تشخیص دقیق خطا
    // ─────────────────────────────────────────────────────────────────
    private function parse_response( $response, string $provider ): array {

        // خطای اتصال (cURL / شبکه)
        if ( is_wp_error( $response ) ) {
            $msg = $response->get_error_message();
            // حذف URL از پیام خطا تا کلید API در لاگ ظاهر نشود
            $msg = preg_replace( '/https?:\/\/[^\s]+/', '[URL]', $msg );
            return [
                'success' => false,
                'error'   => $this->friendly_connection_error( $msg ),
            ];
        }

        $code = (int) wp_remote_retrieve_response_code( $response );
        $raw  = wp_remote_retrieve_body( $response );
        $data = json_decode( $raw, true );

        // خطاهای HTTP
        if ( $code !== 200 ) {
            return [
                'success'   => false,
                'error'     => $this->friendly_http_error( $code, $data, $provider, $raw ),
                'http_code' => $code,
            ];
        }

        // بدنه خالی یا JSON نامعتبر
        if ( empty( $raw ) || $data === null ) {
            return [ 'success' => false, 'error' => 'پاسخ دریافتی از API خالی یا نامعتبر بود.' ];
        }

        if ( $provider === 'anthropic' ) {
            return $this->parse_anthropic_success( $data );
        }
        if ( $provider === 'gemini' ) {
            return $this->parse_gemini_success( $data );
        }

        // openrouter / deepseek / openai — همگی فرمت choices[0].message.content مشترک OpenAI را دارند
        $text = $data['choices'][0]['message']['content'] ?? '';

        // DeepSeek reasoning models متن را در reasoning_content می‌گذارند
        if ( empty( $text ) ) {
            $text = $data['choices'][0]['message']['reasoning_content'] ?? '';
        }

        if ( empty( trim( $text ) ) ) {
            $finish = $data['choices'][0]['finish_reason'] ?? 'unknown';
            return [
                'success' => false,
                'error'   => "پاسخ خالی دریافت شد (finish_reason: {$finish}). لطفاً دوباره تلاش کنید.",
            ];
        }

        return [
            'success'        => true,
            'text'           => trim( $text ),
            'tokens'         => $data['usage']['total_tokens']      ?? 0,
            'tokens_input'   => $data['usage']['prompt_tokens']     ?? 0,
            'tokens_output'  => $data['usage']['completion_tokens'] ?? 0,
            'model_used'     => $data['model'] ?? $this->model,
            'finish_reason'  => $data['choices'][0]['finish_reason'] ?? '',
        ];
    }

    /**
     * پاسخ Anthropic: content یک آرایه از بلاک‌هاست (معمولاً یک بلاک type=text)،
     * توکن‌ها زیر usage.input_tokens/output_tokens جدا از هم گزارش می‌شوند.
     */
    private function parse_anthropic_success( array $data ): array {
        $text = '';
        foreach ( ( $data['content'] ?? [] ) as $block ) {
            if ( ( $block['type'] ?? '' ) === 'text' ) {
                $text .= $block['text'] ?? '';
            }
        }

        if ( trim( $text ) === '' ) {
            $reason = $data['stop_reason'] ?? 'unknown';
            return [ 'success' => false, 'error' => "پاسخ خالی دریافت شد (stop_reason: {$reason}). لطفاً دوباره تلاش کنید." ];
        }

        $tin  = (int) ( $data['usage']['input_tokens']  ?? 0 );
        $tout = (int) ( $data['usage']['output_tokens'] ?? 0 );

        return [
            'success'       => true,
            'text'          => trim( $text ),
            'tokens'        => $tin + $tout,
            'tokens_input'  => $tin,
            'tokens_output' => $tout,
            'model_used'    => $data['model'] ?? $this->model,
            'finish_reason' => $data['stop_reason'] ?? '',
        ];
    }

    /**
     * پاسخ Gemini: متن زیر candidates[0].content.parts[*].text (ممکن است چند
     * part باشد)، شمارش توکن زیر usageMetadata با نام‌های متفاوت.
     */
    private function parse_gemini_success( array $data ): array {
        $parts = $data['candidates'][0]['content']['parts'] ?? [];
        $text  = '';
        foreach ( $parts as $part ) {
            if ( ! empty( $part['thought'] ) ) {
                continue;
            }
            $text .= $part['text'] ?? '';
        }

        if ( trim( $text ) === '' ) {
            // finishReason=SAFETY یعنی فیلتر ایمنی گوگل محتوا را بلاک کرده — این خیلی رایج است
            // روی موضوعات پزشکی/روان‌شناسی، پس پیام جدا و قابل‌فهم می‌دهیم.
            $finish = $data['candidates'][0]['finishReason'] ?? 'unknown';
            $msg    = $finish === 'SAFETY'
                ? 'پاسخ توسط فیلتر ایمنی Gemini بلاک شد (موضوعات پزشکی/حساس گاهی فیلتر می‌شوند). ارائه‌دهنده دیگری را امتحان کنید یا پرامپت را تغییر دهید.'
                : "پاسخ خالی دریافت شد (finishReason: {$finish}). لطفاً دوباره تلاش کنید.";
            return [ 'success' => false, 'error' => $msg ];
        }

        return [
            'success'       => true,
            'text'          => trim( $text ),
            'tokens'        => (int) ( $data['usageMetadata']['totalTokenCount']      ?? 0 ),
            'tokens_input'  => (int) ( $data['usageMetadata']['promptTokenCount']     ?? 0 ),
            'tokens_output' => (int) ( $data['usageMetadata']['candidatesTokenCount'] ?? 0 ),
            'model_used'    => $this->model,
            'finish_reason' => $data['candidates'][0]['finishReason'] ?? '',
        ];
    }

    // ─────────────────────────────────────────────────────────────────
    // تبدیل خطاهای HTTP به پیام فارسی
    // ─────────────────────────────────────────────────────────────────
    private function friendly_http_error( int $code, ?array $data, string $provider, string $raw_body = '' ): string {
        $api_msg = $data['error']['message'] ?? '';

        // BUG FIX: اگر بدنه JSON نبود یا فیلد error.message نداشت (مثلاً صفحه HTML
        // فایروال/CDN یا ساختار خطای غیرمنتظره)، پیام کاملاً بی‌اطلاع می‌شد.
        // در این حالت، بخشی از متن خام پاسخ را (بعد از حذف کلید/URL احتمالی) نشان می‌دهیم
        // تا حداقل سرنخی از دلیل واقعی خطا در دسترس باشد.
        if ( $api_msg === '' && $raw_body !== '' ) {
            $snippet = trim( wp_strip_all_tags( $raw_body ) );
            $snippet = preg_replace( '/https?:\/\/[^\s]+/', '[URL]', $snippet );
            $snippet = mb_substr( $snippet, 0, 300 );
            if ( $snippet !== '' ) {
                $api_msg = $snippet;
            }
        }

        // خطای رایج مدل‌های رایگان OpenRouter: عدم تطابق با تنظیمات حریم‌خصوصی حساب
        // (نه واقعاً مدل یا endpoint ناموجود — باید جدا تشخیص داده شود)
        // BUG FIX: این خطا را OpenRouter گاهی با کد 404 و گاهی با کد 403 برمی‌گرداند؛
        // قبلاً فقط حالت 404 تشخیص داده می‌شد و حالت 403 با پیام کلی/گمراه‌کننده نشان داده می‌شد.
        if ( in_array( $code, [ 403, 404 ], true ) && $provider === 'openrouter'
             && ( stripos( $api_msg, 'data policy' ) !== false || stripos( $api_msg, 'no endpoints found' ) !== false ) ) {
            return 'تنظیمات حریم‌خصوصی حساب OpenRouter اجازه استفاده از مدل‌های رایگان را نمی‌دهد. به openrouter.ai/settings/privacy بروید و گزینه‌های مربوط به انتشار داده (Free model publication / Enable training and logging) را فعال کنید. پیام اصلی API: ' . $api_msg;
        }

        $known = [
            401 => 'کلید API نامعتبر است یا منقضی شده. لطفاً کلید را بررسی کنید.',
            402 => 'اعتبار حساب کافی نیست. لطفاً حساب را شارژ کنید.',
            403 => 'دسترسی به این مدل مجاز نیست.',
            404 => 'مدل یا endpoint پیدا نشد.',
            408 => 'زمان انتظار پاسخ تمام شد. لطفاً دوباره تلاش کنید.',
            422 => 'درخواست نامعتبر: ' . $api_msg,
            429 => 'محدودیت نرخ درخواست. لطفاً چند ثانیه صبر کنید.',
            500 => 'خطای داخلی سرور API. لطفاً دوباره تلاش کنید.',
            502 => 'سرور API در دسترس نیست (502). لطفاً دوباره تلاش کنید.',
            503 => 'سرور API موقتاً در دسترس نیست. لطفاً بعداً تلاش کنید.',
        ];

        // BUG FIX: پیام‌های کلی بالا خطای واقعی برگشتی از API را پنهان می‌کردند
        // (مثلاً چرا 403 داده شده — کلید محدود شده؟ مدل غیرمجاز؟ محدودیت منطقه‌ای؟).
        // حالا در صورت وجود پیام از API، همیشه به‌عنوان جزئیات اضافه می‌شود.
        if ( isset( $known[ $code ] ) ) {
            $msg = $known[ $code ];
            if ( $api_msg && $code !== 422 ) { // 422 already includes $api_msg above
                $msg .= ' (پیام API: ' . $api_msg . ')';
            }
            return $msg;
        }

        $base = "خطای HTTP {$code}";
        if ( $api_msg ) {
            $base .= ': ' . $api_msg;
        }
        return $base;
    }

    // ─────────────────────────────────────────────────────────────────
    // تبدیل خطاهای اتصال به پیام فارسی
    // ─────────────────────────────────────────────────────────────────
    private function friendly_connection_error( string $msg ): string {
        // BUG-08 FIX: str_contains از PHP 8.0 است — از strpos استفاده می‌کنیم (PHP 7.4 compatible)
        $lower = strtolower( $msg );

        if ( strpos( $lower, 'timed out' ) !== false || strpos( $lower, 'timeout' ) !== false ) {
            return 'زمان اتصال به API تمام شد. سرور پاسخ نداد. لطفاً دوباره تلاش کنید.';
        }
        if ( strpos( $lower, 'could not resolve' ) !== false || strpos( $lower, 'dns' ) !== false ) {
            return 'آدرس سرور API پیدا نشد. اتصال اینترنت سرور را بررسی کنید.';
        }
        if ( strpos( $lower, 'connection refused' ) !== false ) {
            return 'اتصال رد شد. ممکن است سرور API مسدود باشد.';
        }
        if ( strpos( $lower, 'ssl' ) !== false || strpos( $lower, 'certificate' ) !== false ) {
            return 'خطای SSL. گواهینامه سرور معتبر نیست.';
        }

        return 'خطای اتصال: ' . $msg;
    }

    // ─────────────────────────────────────────────────────────────────
    // آیا خطا fatal است و retry فایده ندارد؟
    // ─────────────────────────────────────────────────────────────────
    private function is_fatal_error( array $result ): bool {
        // فقط خطاهای HTTP با کد مشخص بررسی می‌شوند؛ خطاهای اتصال (timeout، DNS و...)
        // و خطاهای بدون کد (بدنه خالی و...) کد HTTP ندارند و باید retry شوند.
        if ( ! isset( $result['http_code'] ) ) {
            return false;
        }

        // کدهایی که retry برایشان فایده ندارد: کلید/دسترسی/درخواست نامعتبر یا مدل ناموجود.
        // ۴۲۲ («Invalid Parameters») عمداً اضافه شده — طبق مستندات DeepSeek نباید retry شود.
        // ۴۰۸/۴۲۹/۵xx در این لیست نیستند چون موقتی‌اند و retry برایشان منطقی است.
        $fatal_codes = [ 400, 401, 402, 403, 404, 422 ];

        return in_array( $result['http_code'], $fatal_codes, true );
    }

    // ─────────────────────────────────────────────────────────────────
    // getter برای نمایش اطلاعات در تست
    // ─────────────────────────────────────────────────────────────────
    public function get_provider(): string { return $this->provider; }
    public function get_model(): string    { return $this->model; }
}
