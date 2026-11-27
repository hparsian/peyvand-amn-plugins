<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class ACO_Generator {

    private ACO_AI_Router $router;

    // کلاس‌های مجاز برای باکس‌های گرافیکی/تزئینی div/span — هر کلاس دیگری
    // که مدل احتمالاً بسازد در restrict_component_classes() حذف می‌شود.
    const ALLOWED_COMPONENT_CLASSES = [
        'aco-infobox',
        'aco-callout',
        'aco-stat-card',
        'aco-divider',
        'aco-divider-animated',
    ];

    public function __construct() {
        $this->router = new ACO_AI_Router();
    }

    /**
     * whitelist تگ‌های مجاز HTML برای محتوای تولید/بازنویسی‌شده — به‌صورت
     * مشترک در save_post، rewrite_post و apply_pending_rewrite استفاده
     * می‌شود تا سه‌جا جدا نگه‌داشته نشود (قبلاً هرکدام کپی جدا داشت).
     * جدول و div/span (برای باکس‌های تزئینی) از نسخه‌ی ۲.۹.۱۳ اضافه شدند.
     */
    private static function allowed_content_html(): array {
        return [
            'h2'     => [ 'id' => [], 'class' => [] ],
            'h3'     => [ 'id' => [], 'class' => [] ],
            'h4'     => [ 'id' => [], 'class' => [] ],
            'p'      => [ 'class' => [] ],
            'ul'     => [ 'class' => [] ],
            'ol'     => [ 'class' => [] ],
            'li'     => [ 'class' => [] ],
            'strong' => [],
            'em'     => [],
            'a'      => [ 'href' => [], 'title' => [], 'rel' => [], 'target' => [] ],
            'br'     => [],
            'blockquote' => [ 'cite' => [] ],
            // جدول — برای داده‌های مقایسه‌ای/ساختاریافته
            'table'   => [ 'class' => [] ],
            'thead'   => [],
            'tbody'   => [],
            'tfoot'   => [],
            'tr'      => [],
            'th'      => [ 'scope' => [], 'colspan' => [], 'rowspan' => [] ],
            'td'      => [ 'colspan' => [], 'rowspan' => [] ],
            'caption' => [],
            // باکس‌های تزئینی — مقدار class بعداً با restrict_component_classes پالایش می‌شود
            'div'  => [ 'class' => [] ],
            'span' => [ 'class' => [] ],
        ];
    }

    /**
     * فقط کلاس‌های شناخته‌شده‌ی افزونه (ALLOWED_COMPONENT_CLASSES) روی
     * div/span باقی می‌مانند — هر class دیگری که مدل ممکن است بسازد
     * (اسم دلخواه/اشتباه/چیزی که با CSS سایت تداخل کند) حذف می‌شود.
     * اگر بعد از فیلتر هیچ کلاس مجازی نماند، خودِ attribute حذف می‌شود
     * (تگ div/span خالی همچنان بی‌خطر باقی می‌ماند).
     */
    private static function restrict_component_classes( string $content ): string {
        return preg_replace_callback(
            '/<(div|span)([^>]*)\sclass=["\']([^"\']*)["\']([^>]*)>/i',
            function( $m ) {
                $tag        = $m[1];
                $before     = $m[2];
                $classes    = preg_split( '/\s+/', trim( $m[3] ), -1, PREG_SPLIT_NO_EMPTY );
                $after      = $m[4];
                $kept       = array_values( array_intersect( $classes, self::ALLOWED_COMPONENT_CLASSES ) );
                if ( empty( $kept ) ) {
                    return "<{$tag}{$before}{$after}>";
                }
                return "<{$tag}{$before} class=\"" . esc_attr( implode( ' ', $kept ) ) . "\"{$after}>";
            },
            $content
        ) ?? $content;
    }

    /**
     * جدول‌ها را داخل یک <div class="aco-table-wrap"> می‌پیچد تا در
     * موبایل بتوان به‌صورت افقی اسکرول کرد (بدون این، جدول پهن باعث
     * شکستن ظاهر صفحه در موبایل می‌شود). فقط ظاهری است، محتوای جدول را
     * تغییر نمی‌دهد.
     */
    private static function wrap_tables_responsive( string $content ): string {
        if ( strpos( $content, '<table' ) === false ) {
            return $content;
        }
        $content = preg_replace( '/<table(\s|>)/i', '<div class="aco-table-wrap"><table$1', $content );
        $content = preg_replace( '/<\/table>/i', '</table></div>', $content );
        return $content ?? $content;
    }

    // ─────────────────────────────────────────────────────────────────
    // مرحله ۱: تحقیق و ساخت آوتلاین
    // ─────────────────────────────────────────────────────────────────
    public function research( string $keyword, string $topic ): array {
        $niche      = ACO_Settings::get( 'niche' ) ?: 'تولید محتوای عمومی';
        $site_name  = get_bloginfo( 'name' );

        // دسته‌بندی‌های موجود سایت — برای ادغام در یک API call
        $existing_cats = get_terms( [ 'taxonomy' => 'category', 'hide_empty' => false, 'fields' => 'names', 'number' => 50 ] );
        $existing_tags = get_terms( [ 'taxonomy' => 'post_tag',  'hide_empty' => false, 'fields' => 'names', 'number' => 100 ] );
        $cats_str = ( is_array( $existing_cats ) && ! empty( $existing_cats ) ) ? implode( '، ', $existing_cats ) : 'هیچ‌کدام';
        $tags_str = ( is_array( $existing_tags ) && ! empty( $existing_tags ) ) ? implode( '، ', $existing_tags ) : 'هیچ‌کدام';

        $system = "شما یک متخصص سئو معنایی و تولید محتوا در حوزه {$niche} هستید. 
پاسخ را فقط به صورت JSON معتبر بدهید. هیچ متن اضافه‌ای قبل یا بعد از JSON ننویسید.";

        $user = "کلمه کلیدی اصلی: {$keyword}
موضوع: {$topic}
حوزه تخصصی: {$niche}
نام سایت: {$site_name}

یک آوتلاین جامع برای مقاله‌ای درباره این موضوع برای سایت در حوزه {$niche} بساز. همچنین کلمات کلیدی فرعی/مرتبط (LSI) که کاربران واقعی در گوگل برای این موضوع جست‌وجو می‌کنند را پیدا کن.

دسته‌بندی‌های موجود سایت: {$cats_str}
تگ‌های موجود سایت: {$tags_str}

پاسخ را دقیقاً در این فرمت JSON بده:
{
  \"headings\": [\"عنوان بخش اول\", \"عنوان بخش دوم\", \"عنوان بخش سوم\", \"عنوان بخش چهارم\", \"سوالات متداول\"],
  \"entities\": [\"مفهوم معنایی ۱\", \"مفهوم معنایی ۲\", \"مفهوم معنایی ۳\"],
  \"secondary_keywords\": [\"کلمه کلیدی فرعی ۱\", \"کلمه کلیدی فرعی ۲\", \"کلمه کلیدی فرعی ۳\", \"کلمه کلیدی فرعی ۴\", \"کلمه کلیدی فرعی ۵\"],
  \"meta_description\": \"توضیحات متا حدوداً ۱۵۵ کاراکتر برای سئو، شامل کلمه کلیدی اصلی\",
  \"category\": \"مناسب‌ترین دسته‌بندی از لیست موجود یا یک دسته جدید\",
  \"category_is_new\": false,
  \"tags\": [\"تگ ۱\", \"تگ ۲\", \"تگ ۳\"]
}

نکات:
- برای category: اگر دسته موجود مناسب است، همان را بنویس و category_is_new=false بگذار؛ در غیر این صورت category_is_new=true
- برای tags: ۳ تا ۶ تگ مرتبط، ترکیبی از موجود و جدید
- برای secondary_keywords: عبارات واقعی و رایج جست‌وجوی فارسی باشند";

        $result = $this->router->generate( $system, $user );

        if ( ! $result['success'] ) {
            return $result;
        }

        // پاک‌سازی و پارس JSON
        $clean = $this->extract_json( $result['text'] );
        $parsed = json_decode( $clean, true );

        if ( json_last_error() !== JSON_ERROR_NONE || empty( $parsed['headings'] ) || ! is_array( $parsed['headings'] ) ) {
            // فال‌بک: آوتلاین پیش‌فرض
            // (شامل حالتی که headings از مدل یک رشته یا مقدار غیرآرایه باشد، نه فقط خالی)
            $parsed = [
                'headings'           => [
                    "مقدمه‌ای بر {$keyword}",
                    "علل و عوامل {$keyword}",
                    "روش‌های درمان {$keyword}",
                    "نقش مشاور در {$keyword}",
                    "سوالات متداول",
                ],
                'entities'           => [ $keyword, $topic ],
                'secondary_keywords' => [ "علائم {$keyword}", "درمان {$keyword} در خانه", "بهترین روش درمان {$keyword}" ],
                'meta_description'   => "مقاله جامع درباره {$keyword} با رویکرد تخصصی و کاربردی.",
            ];
        }

        // اطمینان از آرایه بودن فیلدهای دیگر هم (حتی اگر headings درست بود، بقیه ممکن است نوع نادرست داشته باشند)
        $entities           = is_array( $parsed['entities']           ?? null ) ? $parsed['entities']           : [];
        $secondary_keywords = is_array( $parsed['secondary_keywords'] ?? null ) ? $parsed['secondary_keywords'] : [];
        $meta_description   = is_string( $parsed['meta_description']  ?? null ) ? $parsed['meta_description']  : '';

        // taxonomy از همان JSON — بدون API call جداگانه
        $category      = is_string( $parsed['category']      ?? null ) ? sanitize_text_field( $parsed['category'] ) : '';
        $category_new  = (bool) ( $parsed['category_is_new'] ?? true );
        $tags_raw      = is_array( $parsed['tags']           ?? null ) ? $parsed['tags'] : [];
        $tags          = array_values( array_filter( array_map( 'sanitize_text_field', $tags_raw ), function( $t ) { return $t !== ''; } ) );

        return [
            'success'            => true,
            'headings'           => array_values( array_filter( $parsed['headings'], 'is_string' ) ),
            'entities'           => array_values( array_filter( $entities, 'is_string' ) ),
            'secondary_keywords' => array_values( array_filter( $secondary_keywords, 'is_string' ) ),
            'meta_description'   => $meta_description,
            'tokens'             => $result['tokens'] ?? 0,
            'taxonomy'           => [
                'success'         => true,
                'category'        => $category,
                'category_is_new' => $category_new,
                'tags'            => $tags,
            ],
        ];
    }

    // ─────────────────────────────────────────────────────────────────
    // دستورالعمل ثابت عناصر بصری (جدول/باکس/جداکننده) — هم در نوشتن مقاله‌ی
    // جدید و هم در بازنویسی پست‌های قدیمی استفاده می‌شود.
    // ─────────────────────────────────────────────────────────────────
    private const RICH_CONTENT_PROMPT = "\n\nابزارهای بصری برای بهبود ظاهر مقاله (کاملاً اختیاری — فقط جایی که واقعاً به محتوا کمک می‌کند، نه در همه مقالات و نه به‌زور):\n"
        . "- برای داده‌های قابل مقایسه یا فهرست ساختاریافته (مثلاً تفاوت چند روش، علائم به تفکیک شدت، برنامه زمانی) یک جدول استاندارد HTML بساز:\n"
        . "  <table><thead><tr><th>...</th></tr></thead><tbody><tr><td>...</td></tr></tbody></table>\n"
        . "  حداکثر ۱ جدول در کل مقاله، فقط اگر داده‌ی واقعاً جدولی وجود دارد.\n"
        . "- برای یک نکته یا خلاصه‌ی کلیدی یک بخش (حداکثر یک‌بار در کل مقاله): <div class=\"aco-infobox\"><p>متن نکته</p></div>\n"
        . "- برای هشدار/نکته‌ی احتیاطی مهم (مثلاً توصیه به مراجعه فوری به متخصص در موارد حاد) — فقط اگر موضوع واقعاً نیاز دارد: <div class=\"aco-callout\"><p>متن هشدار</p></div>\n"
        . "- برای یک آمار یا رقم کلیدی مرتبط با موضوع (اگر در متن آمار معتبری آوردی): <div class=\"aco-stat-card\"><p>متن آماری</p></div>\n"
        . "- این عناصر فقط برای class دقیقاً همین نام‌ها (aco-infobox، aco-callout، aco-stat-card) هستند — از class دیگری استفاده نکن و استایل inline اضافه نکن.\n"
        . "- جدول و هر باکس حداکثر یک‌بار در کل مقاله؛ کیفیت و صحت متن همیشه در اولویت است، نه پرکردن مقاله با این عناصر.";

    /**
     * لینک به مقالات مرتبط که خودِ همین سایت (توسط همین افزونه) قبلاً
     * منتشر کرده — علاوه بر صفحات دستی تعریف‌شده در تنظیمات
     * (internal_link_pages)، هر دو منبع در یک بلاک واحد به مدل داده
     * می‌شوند تا هنگام نوشتن به‌طور طبیعی به یک یا چند مورد لینک بدهد.
     */
    private function build_internal_links_prompt( string $keyword, int $exclude_post_id = 0 ): string {
        $lines = [];

        // ۱. صفحات مهم دستی تعریف‌شده توسط کاربر
        $link_pages = (array) ( ACO_Settings::get( 'internal_link_pages' ) ?? [] );
        foreach ( $link_pages as $lp ) {
            $title_lp = $lp['title'] ?? '';
            $url_lp   = $lp['url']   ?? '';
            $desc_lp  = $lp['description'] ?? '';
            if ( empty( $url_lp ) ) continue;
            $line = $title_lp ? "- {$title_lp}: {$url_lp}" : "- {$url_lp}";
            if ( $desc_lp ) $line .= " ({$desc_lp})";
            $lines[] = $line;
        }

        // ۲. مقالات مرتبط که خودِ سیستم قبلاً تولید/منتشر کرده — بر اساس
        // هم‌پوشانی کلمه کلیدی با کلمه کلیدی هدف یا عنوان پست‌های موجود
        // (بدون فراخوانی AI اضافه — همان منطق پیشنهاد لینک داخلی موجود).
        if ( $keyword !== '' ) {
            $related = ACO_SEO::suggest_internal_links( $keyword, '', $exclude_post_id, 3 );
            foreach ( $related as $r ) {
                $lines[] = "- {$r['title']}: {$r['url']} (مقاله‌ی قبلی همین سایت)";
            }
        }

        if ( empty( $lines ) ) {
            return '';
        }

        return "\n\nصفحات/مقالات مرتبط سایت برای لینک داخلی:\n" . implode( "\n", $lines )
            . "\n\nقوانین لینک داخلی:\n- حداکثر ۳ لینک داخلی در کل مقاله (جمع صفحات دستی + مقالات قبلی)\n- فقط در جاهایی که واقعاً مرتبط است لینک بگذار\n- اگر موضوع مقاله با آن ارتباط ندارد، لینکش نکن\n- anchor text باید طبیعی و مرتبط با محتوای اطراف باشد، نه خودِ عنوان کامل صفحه";
    }

    // ─────────────────────────────────────────────────────────────────
    // مرحله ۲: نوشتن مقاله کامل
    // ─────────────────────────────────────────────────────────────────
    public function write( string $keyword, string $topic, array $headings, array $entities, array $secondary_keywords = [] ): array {
        // FIX BUG-G1: array_values تضمین می‌کند کلیدها عددی و پیوسته باشند — از ایندکس اشتباه جلوگیری می‌شود
        $headings_indexed = array_values( $headings );
        $headings_str = implode( "\n", array_map(
            function( $i, $h ) { return ( $i + 1 ) . ". {$h}"; },
            array_keys( $headings_indexed ),
            $headings_indexed
        ) );
        $entities_str = implode( '، ', $entities );
        $secondary_str = implode( '، ', $secondary_keywords );

        $niche     = ACO_Settings::get( 'niche' ) ?: 'تولید محتوای عمومی';
        $tone    = ACO_Settings::get( 'tone' ) ?: 'حرفه‌ای، قابل اعتماد و کاربردی';
        $min_words = absint( ACO_Settings::get( 'min_words' ) ) ?: 1200;

        // لینک‌های داخلی (صفحات دستی تنظیمات + مقالات مرتبط قبلی سایت)
        $link_pages_prompt = $this->build_internal_links_prompt( $keyword );
        $rich_content_prompt = self::RICH_CONTENT_PROMPT;

        $system = "شما یک نویسنده محتوای تخصصی در حوزه {$niche} هستید.
لحن نوشتار: {$tone}.
زبان: فارسی روان و صحیح.
خروجی را به صورت HTML ساده بنویسید (از تگ‌های h2، h3، p، ul، li، و در صورت لزوم table/thead/tbody/tr/th/td و div (فقط برای باکس‌های تزئینی مجاز) استفاده کنید).
هرگز از markdown استفاده نکنید.";

        $user = "کلمه کلیدی اصلی: {$keyword}
موضوع: {$topic}
انتیتی‌های معنایی که باید پوشش داده شوند: {$entities_str}
کلمات کلیدی فرعی که باید طبیعی در متن گنجانده شوند: {$secondary_str}

ساختار مقاله:
{$headings_str}

{$link_pages_prompt}
{$rich_content_prompt}

یک مقاله کامل و جامع با حداقل {$min_words} کلمه بنویس.
- هر بخش را با تگ <h2> شروع کن
- محتوای هر بخش باید حداقل ۲ پاراگراف باشد
- بخش سوالات متداول را با تگ <h2> و هر سوال را با <h3> بنویس
- نتیجه‌گیری کاربردی در انتها بنویس

نکات حیاتی سئو (حتماً رعایت کن):
- کلمه کلیدی اصلی («{$keyword}») باید در همان پاراگراف اول مقاله بیاید
- کلمه کلیدی اصلی باید حداقل در یکی از سرتیترهای H2 ظاهر شود (می‌توانی متن یکی از سرتیترهای بالا را کمی تغییر دهی تا شامل آن شود، بدون اینکه معنا را عوض کنی)
- کلمات کلیدی فرعی را به‌صورت پراکنده و طبیعی در بخش‌های مرتبط بگنجان، نه همه را در یک پاراگراف
- از تکرار غیرطبیعی یا فشرده کلمه کلیدی (کیورد استافینگ) خودداری کن؛ تراکم کلی کلمه کلیدی اصلی در کل متن باید حدود نیم تا دو درصد باشد، نه بیشتر
- حداقل ۲ لینک خارجی به منابع معتبر (Wikipedia، PubMed، سایت‌های دانشگاهی یا سازمان‌های رسمی) اضافه کن — فقط URLهای واقعی و معتبر
- لینک‌های خارجی را به صورت <a href=\"URL\" target=\"_blank\" rel=\"noopener noreferrer\">متن لینک</a> بنویس";

        // مرحله نوشتن خروجی طولانی دارد (۱۲۰۰+ کلمه) و نیاز به timeout بیشتری دارد
        $result = $this->router->generate( $system, $user, ACO_AI_Router::TIMEOUT_WRITE, ACO_AI_Router::MAX_TOKENS_WRITE );

        if ( ! $result['success'] ) {
            return $result;
        }

        return [
            'success' => true,
            'content' => $result['text'],
            'tokens'  => $result['tokens'] ?? 0,
        ];
    }

    // ─────────────────────────────────────────────────────────────────
    // مرحله ۳: ذخیره پست در وردپرس
    // ─────────────────────────────────────────────────────────────────
    public function save_post( array $data ): array {
        $title           = sanitize_text_field( $data['title'] ?? '' );
        // allowlist صریح — جلوگیری از تزریق script/event handler توسط مدل
        $raw_content = wp_kses( $data['content'] ?? '', self::allowed_content_html() );

        // FIX #3: پاکسازی href از پروتکل‌های خطرناک (javascript:، data: و غیره)
        // wp_kses به تنهایی href را محدود نمی‌کند — esc_url باید روی هر href اعمال شود
        $content = preg_replace_callback(
            '/<a\s([^>]*)>/i',
            function( $matches ) {
                $attrs = $matches[1];
                // href را پیدا و با esc_url پاکسازی کن
                $attrs = preg_replace_callback(
                    '/href=["\']([^"\']*)["\']/',
                    function( $m ) {
                        $safe_url = esc_url( $m[1], [ 'http', 'https' ] );
                        return 'href="' . $safe_url . '"';
                    },
                    $attrs
                );
                return '<a ' . $attrs . '>';
            },
            $raw_content
        );
        $content = $content ?? $raw_content;
        $content = self::restrict_component_classes( $content );
        $content = self::wrap_tables_responsive( $content );
        $keyword         = sanitize_text_field( $data['keyword'] ?? '' );
        $meta_description= sanitize_text_field( $data['meta_description'] ?? '' );
        $status          = in_array( $data['status'] ?? '', ['draft','publish','pending','future'], true )
                           ? $data['status']
                           : ACO_Settings::get( 'default_status' );
        $author_id       = absint( $data['author_id'] ?? ACO_Settings::get( 'default_author' ) );

        if ( empty( $title ) || empty( $content ) ) {
            return [ 'success' => false, 'error' => 'عنوان یا محتوا خالی است.' ];
        }

        // خروجی به‌صورت بلاک‌های بومی گوتنبرگ (به‌جای HTML خام classic) —
        // قابل خاموش‌کردن از تنظیمات برای سایت‌هایی که با صفحه‌ساز دیگری کار می‌کنند.
        if ( ACO_Settings::get( 'gutenberg_blocks_enabled' ) ) {
            $content = ACO_Gutenberg::convert_html_to_blocks( $content );
        }

        // کنترل واقعی طول متا دیسکریپشن — در صورتی که مقدار از بیرون truncate نشده باشد
        // (مثلاً فراخوانی مستقیم save_post بدون run_write_and_save_stage)
        // run_write_and_save_stage قبل از فراخوانی این متد، truncate را انجام می‌دهد
        // و مقدار کوتاه‌شده را پاس می‌دهد — این فراخوانی برای آن مسیر idempotent است (FIX #double-truncate)
        $meta_description = ACO_SEO::truncate_meta_description( $meta_description );

        // عنوان سئو: اگر از بیرون (همان عنوانی که در run_audit بررسی شده) پاس داده شده
        // از همان استفاده می‌شود تا چک‌لیست سئو با چیزی که واقعاً ذخیره می‌شود یکی باشد؛
        // در غیر این صورت (مثلاً فراخوانی مستقیم) همین‌جا ساخته می‌شود.
        $seo_title = isset( $data['seo_title'] ) && $data['seo_title'] !== ''
            ? sanitize_text_field( $data['seo_title'] )
            : ACO_SEO::build_seo_title( $keyword, $title );

        // چکیده (post_excerpt): اگر از بیرون پاس داده شده از همان استفاده می‌شود؛
        // در غیر این صورت خودکار از روی پاراگراف اول محتوا ساخته می‌شود — تا
        // ادمین دیگر مجبور نباشد قبل از انتشار دستی چکیده بنویسد.
        $excerpt = isset( $data['excerpt'] ) && trim( (string) $data['excerpt'] ) !== ''
            ? sanitize_textarea_field( wp_strip_all_tags( $data['excerpt'] ) )
            : sanitize_textarea_field( ACO_SEO::build_excerpt_from_content( $content ) );

        // FIX BUG-3: وقتی status=future است، wp_insert_post بدون post_date اجرا می‌شود.
        // وردپرس post_date را current_time می‌گذارد — که یعنی تاریخ گذشته است و پست فوری publish می‌شود.
        // راه‌حل: با status=draft ذخیره می‌کنیم؛ wp_update_post که توسط caller با post_date فراخوانی می‌شود
        // وضعیت را به future تغییر می‌دهد — بدون window of incorrect publish.
        $insert_status = ( $status === 'future' ) ? 'draft' : $status;
        $post_id = wp_insert_post( [
            'post_title'   => $title,
            'post_content' => $content,
            'post_excerpt' => $excerpt,
            'post_status'  => $insert_status,
            'post_author'  => $author_id,
            'post_type'    => 'post',
        ], true );

        if ( is_wp_error( $post_id ) ) {
            return [ 'success' => false, 'error' => $post_id->get_error_message() ];
        }

        // ذخیره متا دیسکریپشن برای Yoast و Rank Math
        if ( $meta_description ) {
            update_post_meta( $post_id, '_yoast_wpseo_metadesc',  $meta_description );
            update_post_meta( $post_id, 'rank_math_description',   $meta_description );
        }

        // ذخیره عنوان سئو برای Yoast و Rank Math
        if ( $seo_title ) {
            update_post_meta( $post_id, '_yoast_wpseo_title', $seo_title );
            update_post_meta( $post_id, 'rank_math_title',    $seo_title );
        }

        // ذخیره کلمه کلیدی هدف (focus keyword) برای Yoast و Rank Math
        if ( $keyword ) {
            update_post_meta( $post_id, '_yoast_wpseo_focuskw',     $keyword );
            update_post_meta( $post_id, 'rank_math_focus_keyword',  $keyword );
        }

        // ذخیره متادیتای تولید محتوا
        update_post_meta( $post_id, '_aco_generated', true );
        update_post_meta( $post_id, '_aco_generated_at', current_time( 'mysql' ) );

        // بازنشانی کش تعداد مقالات تولیدشده
        delete_transient( 'aco_generated_count' );

        return [
            'success'  => true,
            'post_id'  => $post_id,
            'edit_url' => get_edit_post_link( $post_id, 'raw' ),
            'view_url' => get_permalink( $post_id ),
            'seo_title'=> $seo_title,
            'meta_description' => $meta_description,
            'excerpt'  => $excerpt,
        ];
    }

    // ─────────────────────────────────────────────────────────────────
    // مرحله ۱ (AJAX جدا): فقط تحقیق + آوتلاین
    // ─────────────────────────────────────────────────────────────────
    public function run_research_stage( string $keyword, string $topic ): array {
        $log = [];

        // ── چک تکراری نبودن عنوان ──────────────────────────────────
        $duplicate = $this->find_duplicate_title( $topic );
        if ( $duplicate ) {
            $log[] = '⚠️ هشدار: مقاله‌ای با عنوان مشابه قبلاً وجود دارد — «' . $duplicate['title'] . '» (#' . $duplicate['id'] . ')';
        }

        // ── کش نتایج تحقیق ─────────────────────────────────────────
        $cache_key = 'aco_research_' . md5( $keyword . '||' . $topic );
        $cached    = get_transient( $cache_key );
        if ( $cached !== false && is_array( $cached ) && ! empty( $cached['headings'] ) ) {
            $log[] = '⚡ آوتلاین از کش بازیابی شد (' . count( $cached['headings'] ) . ' بخش)';
            if ( ! empty( $cached['taxonomy']['category'] ) ) {
                $log[] = '🏷️ دسته: «' . $cached['taxonomy']['category'] . '» | تگ‌ها: ' . implode( '، ', $cached['taxonomy']['tags'] ?? [] );
            }
            if ( $duplicate ) {
                $cached['duplicate'] = $duplicate;
            }
            $cached['log'] = $log;
            return $cached;
        }

        $log[] = '🔍 در حال تحقیق، ساخت آوتلاین و یافتن کلمات کلیدی مرتبط...';

        $research = $this->research( $keyword, $topic );
        if ( ! $research['success'] ) {
            ACO_Logger::error( 'خطا در مرحله تحقیق: ' . ( $research['error'] ?? '' ), 'research', [ 'keyword' => $keyword ] );
            return array_merge( $research, [ 'log' => $log ] );
        }
        $log[] = '✅ آوتلاین ساخته شد (' . count( $research['headings'] ) . ' بخش، ' . count( $research['secondary_keywords'] ?? [] ) . ' کلمه کلیدی فرعی)';

        // taxonomy از همان JSON تحقیق استخراج شده — API call جداگانه‌ای نیست
        $taxonomy = $research['taxonomy'] ?? null;
        if ( $taxonomy && ! empty( $taxonomy['category'] ) ) {
            $log[] = '🏷️ دسته: «' . $taxonomy['category'] . '» | تگ‌ها: ' . implode( '، ', $taxonomy['tags'] ?? [] );
        }

        $output = [
            'success'            => true,
            'headings'           => $research['headings'],
            'entities'           => $research['entities'],
            'secondary_keywords' => $research['secondary_keywords'] ?? [],
            'meta_description'   => $research['meta_description'] ?? '',
            'tokens'             => $research['tokens'] ?? 0,
            'taxonomy'           => $taxonomy,
        ];

        // ذخیره در کش (۲۴ ساعت) — بدون log و duplicate تا قابل reuse باشد
        set_transient( $cache_key, $output, DAY_IN_SECONDS );

        if ( $duplicate ) {
            $output['duplicate'] = $duplicate;
        }
        $output['log'] = $log;
        return $output;
    }

    // ─────────────────────────────────────────────────────────────────
    // مرحله ۲ (AJAX جدا): نوشتن + تحلیل سئو + ذخیره
    // آوتلاین مرحله ۱ از بیرون (JS) پاس داده می‌شود — دوباره تولید نمی‌شود
    // ─────────────────────────────────────────────────────────────────
    public function run_write_and_save_stage(
        string $keyword,
        string $topic,
        array $headings,
        array $entities,
        array $secondary_keywords,
        string $meta_description_raw,
        string $status,
        int $research_tokens = 0,
        array $taxonomy = []
    ): array {
        $log = [];

        // مرحله نوشتن
        $log[] = '✍️ در حال نوشتن مقاله...';
        $write = $this->write( $keyword, $topic, $headings, $entities, $secondary_keywords );
        if ( ! $write['success'] ) {
            ACO_Logger::error( 'خطا در مرحله نوشتن: ' . ( $write['error'] ?? '' ), 'write', [ 'keyword' => $keyword ] );
            return array_merge( $write, [ 'log' => $log ] );
        }
        $log[] = '✅ مقاله نوشته شد';

        // تحلیل سئو (قبل از ذخیره، روی متا دیسکریپشن کوتاه‌شده و عنوان سئوی نهایی —
        // همان چیزی که واقعاً در save_post() ذخیره می‌شود، نه عنوان خام)
        $meta_description = ACO_SEO::truncate_meta_description( $meta_description_raw );
        $seo_title = ACO_SEO::build_seo_title( $keyword, $topic );
        // ذخیره اول — تا post_id داشته باشیم برای slug check
        $log[] = '💾 در حال ذخیره در وردپرس...';
        // FIX #2: ذخیره با مقدار کوتاه‌شده ($meta_description) نه raw
        // تا مقدار ذخیره‌شده با مقداری که audit روی آن اجرا می‌شود یکی باشد
        // FIX #author: author_id را صریح پاس می‌دهیم تا save_post از همان استفاده کند
        $save = $this->save_post( [
            'title'            => $topic,
            'content'          => $write['content'],
            'keyword'          => $keyword,
            'meta_description' => $meta_description,
            'status'           => $status,
            'seo_title'        => $seo_title,
            'author_id'        => absint( ACO_Settings::get( 'default_author' ) ) ?: 1,
        ] );

        if ( ! $save['success'] ) {
            return array_merge( $save, [ 'log' => $log ] );
        }

        if ( ! empty( $save['excerpt'] ) ) {
            $log[] = '📝 چکیده مقاله به‌صورت خودکار ساخته و ذخیره شد';
        }

        // audit با post_id — slug check فعال می‌شود
        $seo_audit = ACO_SEO::run_audit( $seo_title, $write['content'], $meta_description, $keyword, [ 'post_id' => $save['post_id'] ] );
        $log[] = '📊 امتیاز سئو: ' . $seo_audit['score'] . ' از ۱۰۰';

        update_post_meta( $save['post_id'], '_aco_seo_score', $seo_audit['score'] );
        update_post_meta( $save['post_id'], '_aco_seo_checks', wp_json_encode( $seo_audit['checks'] ) );
        update_post_meta( $save['post_id'], '_aco_readability_score', $seo_audit['readability']['score'] ?? 0 );

        // اعمال دسته‌بندی و تگ
        $applied_taxonomy = [ 'category' => '', 'tags' => [] ];
        if ( ! empty( $taxonomy['category'] ) || ! empty( $taxonomy['tags'] ) ) {
            $applied_taxonomy = $this->apply_taxonomy(
                $save['post_id'],
                $taxonomy['category'] ?? '',
                $taxonomy['tags'] ?? []
            );
            if ( $applied_taxonomy['category'] ) {
                $log[] = '🏷️ دسته‌بندی «' . $applied_taxonomy['category'] . '» اعمال شد';
            }
            if ( ! empty( $applied_taxonomy['tags'] ) ) {
                $log[] = '🔖 ' . count( $applied_taxonomy['tags'] ) . ' تگ اعمال شد';
            }
        }

        // تولید تصویر شاخص (اختیاری — طبق تنظیمات). شکست این مرحله
        // نباید کل فرآیند را متوقف کند؛ فقط در لاگ گزارش می‌شود.
        if ( ACO_Settings::get( 'featured_image_enabled' ) ) {
            $image_result = ACO_Image_Generator::generate_and_attach( $save['post_id'], $topic, $keyword );
            if ( ! empty( $image_result['success'] ) && empty( $image_result['skipped'] ) ) {
                $log[] = '🖼️ تصویر شاخص ساخته و پیوست شد (' . ( $image_result['source'] ?? '' ) . ')';
            } elseif ( empty( $image_result['success'] ) ) {
                $log[] = '⚠️ تولید تصویر شاخص ناموفق بود — مقاله بدون تصویر ذخیره شد';
            }
        }

        $log[] = '✅ مقاله ذخیره شد';
        $total_tokens = $research_tokens + ( $write['tokens'] ?? 0 );

        ACO_Logger::success( "مقاله ایجاد شد — #{$save['post_id']} | سئو: {$seo_audit['score']}/۱۰۰ | توکن: {$total_tokens}", 'write', [
            'keyword'   => $keyword,
            'seo_score' => $seo_audit['score'],
        ], $total_tokens );

        // پیشنهاد internal link
        $internal_links = ACO_SEO::suggest_internal_links( $keyword, $write['content'], $save['post_id'] );
        if ( ! empty( $internal_links ) ) {
            $log[] = '🔗 ' . count( $internal_links ) . ' پیشنهاد لینک داخلی یافت شد';
        }

        // بررسی لینک‌های داخلی که مدل از لیست settings در متن قرار داده
        $placed_links = $this->detect_placed_links( $write['content'], (array) ( ACO_Settings::get( 'internal_link_pages' ) ?? [] ) );
        if ( ! empty( $placed_links ) ) {
            $log[] = '✅ ' . count( $placed_links ) . ' لینک داخلی از لیست شما در متن قرار گرفت';
        } else {
            $link_pages_check = (array) ( ACO_Settings::get( 'internal_link_pages' ) ?? [] );
            if ( ! empty( $link_pages_check ) ) {
                $log[] = '⚠️ هیچ لینک داخلی از لیست شما در متن قرار نگرفت';
            }
        }

        return [
            'success'            => true,
            'post_id'            => $save['post_id'],
            'edit_url'           => $save['edit_url'],
            'view_url'           => $save['view_url'],
            'headings'           => $headings,
            'entities'           => $entities,
            'secondary_keywords' => $secondary_keywords,
            'seo_title'          => $save['seo_title'] ?? '',
            'meta_description'   => $save['meta_description'] ?? '',
            'excerpt'            => $save['excerpt'] ?? '',
            'seo_score'          => $seo_audit['score'],
            'seo_checks'         => $seo_audit['checks'],
            'keyword_density'    => $seo_audit['density'],
            'word_count'         => $seo_audit['word_count'],
            'total_tokens'       => $total_tokens,
            'internal_links'     => $internal_links,
            'placed_links'       => $placed_links,
            'taxonomy'           => $applied_taxonomy,
            'log'                => $log,
        ];
    }

    // ─────────────────────────────────────────────────────────────────
    // فرآیند کامل یکجا
    // @deprecated از نسخه ۱.۵.۵ — به‌جای آن از run_research_stage() + run_write_and_save_stage() استفاده کنید.
    //             این متد فقط برای سازگاری عقب‌رو نگه‌داشته شده و در نسخه آینده حذف خواهد شد.
    // ─────────────────────────────────────────────────────────────────
    public function run_full_pipeline( string $keyword, string $topic, string $status = 'draft' ): array {
        $log = [];

        // مرحله ۱: تحقیق
        $log[] = '🔍 در حال تحقیق، ساخت آوتلاین و یافتن کلمات کلیدی مرتبط...';
        $research = $this->research( $keyword, $topic );
        if ( ! $research['success'] ) {
            return array_merge( $research, [ 'log' => $log ] );
        }
        $log[] = '✅ آوتلاین ساخته شد (' . count( $research['headings'] ) . ' بخش، ' . count( $research['secondary_keywords'] ?? [] ) . ' کلمه کلیدی فرعی)';

        // مرحله ۲: نوشتن
        $log[] = '✍️ در حال نوشتن مقاله...';
        $write = $this->write( $keyword, $topic, $research['headings'], $research['entities'], $research['secondary_keywords'] ?? [] );
        if ( ! $write['success'] ) {
            return array_merge( $write, [ 'log' => $log ] );
        }
        $log[] = '✅ مقاله نوشته شد';

        // FIX #4: ذخیره اول، سپس audit — تا post_id برای slug_check و img_check موجود باشد
        $meta_description = ACO_SEO::truncate_meta_description( $research['meta_description'] );
        $seo_title = ACO_SEO::build_seo_title( $keyword, $topic );

        $log[] = '💾 در حال ذخیره در وردپرس...';
        $save = $this->save_post( [
            'title'            => $topic,
            'content'          => $write['content'],
            'keyword'          => $keyword,
            'meta_description' => $meta_description,
            'status'           => $status,
            'seo_title'        => $seo_title,
            'author_id'        => absint( ACO_Settings::get( 'default_author' ) ) ?: 1,
        ] );

        if ( ! $save['success'] ) {
            return array_merge( $save, [ 'log' => $log ] );
        }

        $seo_audit = ACO_SEO::run_audit( $seo_title, $write['content'], $meta_description, $keyword, [ 'post_id' => $save['post_id'] ] );
        $log[] = '📊 امتیاز سئو: ' . $seo_audit['score'] . ' از ۱۰۰';

        // ثبت نتیجه audit به‌عنوان متادیتا برای مرور بعدی
        update_post_meta( $save['post_id'], '_aco_seo_score', $seo_audit['score'] );
        update_post_meta( $save['post_id'], '_aco_seo_checks', wp_json_encode( $seo_audit['checks'] ) );
        update_post_meta( $save['post_id'], '_aco_readability_score', $seo_audit['readability']['score'] ?? 0 );

        // اعمال taxonomy از JSON تحقیق
        $applied_taxonomy = [ 'category' => '', 'tags' => [] ];
        $tax = $research['taxonomy'] ?? null;
        if ( $tax && ! empty( $tax['category'] ) ) {
            $applied_taxonomy = $this->apply_taxonomy( $save['post_id'], $tax['category'], $tax['tags'] ?? [] );
        }

        if ( ACO_Settings::get( 'featured_image_enabled' ) ) {
            $image_result = ACO_Image_Generator::generate_and_attach( $save['post_id'], $topic, $keyword );
            if ( ! empty( $image_result['success'] ) && empty( $image_result['skipped'] ) ) {
                $log[] = '🖼️ تصویر شاخص ساخته و پیوست شد (' . ( $image_result['source'] ?? '' ) . ')';
            } elseif ( empty( $image_result['success'] ) ) {
                $log[] = '⚠️ تولید تصویر شاخص ناموفق بود — مقاله بدون تصویر ذخیره شد';
            }
        }

        $log[] = '✅ مقاله ذخیره شد';
        $total_tokens = ( $research['tokens'] ?? 0 ) + ( $write['tokens'] ?? 0 );

        return [
            'success'            => true,
            'post_id'            => $save['post_id'],
            'edit_url'           => $save['edit_url'],
            'view_url'           => $save['view_url'],
            'headings'           => $research['headings'],
            'entities'           => $research['entities'],
            'secondary_keywords' => $research['secondary_keywords'] ?? [],
            'seo_title'          => $save['seo_title'] ?? '',
            'meta_description'   => $save['meta_description'] ?? '',
            'seo_score'          => $seo_audit['score'],
            'seo_checks'         => $seo_audit['checks'],
            'keyword_density'    => $seo_audit['density'],
            'word_count'         => $seo_audit['word_count'],
            'total_tokens'       => $total_tokens,
            'log'                => $log,
        ];
    }

    // ─────────────────────────────────────────────────────────────────
    // چک تکراری نبودن عنوان
    // ─────────────────────────────────────────────────────────────────
    private function find_duplicate_title( string $topic ): ?array {
        if ( empty( trim( $topic ) ) ) return null;

        // FIX #2: جستجوی دقیق با عنوان کامل — بدون fuzzy match نادقیق
        // قبلاً ۳۰ کاراکتر اول عنوان برای LIKE استفاده می‌شد که باعث false positive می‌شد
        // (مثلاً «روش‌های درمان اضطراب اجتماعی» با «روش‌های درمان اضطراب شغلی» duplicate تشخیص داده می‌شد)

        // مرحله ۱: تطبیق دقیق عنوان کامل (بهترین حالت)
        global $wpdb;
        $found = $wpdb->get_row( $wpdb->prepare(
            "SELECT ID, post_title FROM {$wpdb->posts}
             WHERE post_title = %s
               AND post_type = 'post'
               AND post_status IN ('publish','draft','pending','future')
             LIMIT 1",
            $topic
        ) );
        if ( $found ) {
            return [ 'id' => (int) $found->ID, 'title' => $found->post_title, 'exact' => true ];
        }

        // مرحله ۲: تطبیق نرمالایز‌شده — بدون تفاوت در فاصله‌ها و کوچکی/بزرگی حروف
        // (نیم‌فاصله، فاصله اضافه، ی/ك عربی را نادیده می‌گیرد)
        $normalized_topic = trim( preg_replace( '/\s+/u', ' ', trim( $topic ) ) );
        $normalized_topic = strtr( $normalized_topic, [ 'ي' => 'ی', 'ك' => 'ک', 'ة' => 'ه' ] );

        // FIX BUG-4: $like_exact حذف شد — dead code بود (در query استفاده نمی‌شد؛ prepare با %s کافی است)
        $found = $wpdb->get_row( $wpdb->prepare(
            "SELECT ID, post_title FROM {$wpdb->posts}
             WHERE TRIM(REPLACE(post_title, '\xE2\x80\x8C', ' ')) = %s
               AND post_type = 'post'
               AND post_status IN ('publish','draft','pending','future')
             LIMIT 1",
            $normalized_topic
        ) );
        if ( $found ) {
            return [ 'id' => (int) $found->ID, 'title' => $found->post_title, 'exact' => true ];
        }

        // مرحله ۳: fuzzy — فقط اگر عنوان بیش از ۴۰ کاراکتر است و substring کامل آن در مقاله موجود باشد
        // (نه ۳۰ کاراکتر اول — که خیلی کوتاه است و false positive می‌دهد)
        // حداقل باید ۶۰٪ طول عنوان (و حداقل ۴۰ کاراکتر) مطابقت داشته باشد
        $topic_len    = mb_strlen( $normalized_topic );
        $fuzzy_length = max( 40, (int) round( $topic_len * 0.6 ) );

        // FIX #perf: fuzzy LIKE روی سایت‌های بزرگ کند است
        // اگر تعداد پست‌ها از ۵۰۰۰ بیشتر است، fuzzy را رد کن
        $post_count = (int) wp_count_posts( 'post' )->publish;
        if ( $topic_len >= 15 && $post_count < 5000 ) {
            $fuzzy_str = mb_substr( $normalized_topic, 0, min( $fuzzy_length, $topic_len ) );
            $like      = '%' . $wpdb->esc_like( $fuzzy_str ) . '%';
            $found     = $wpdb->get_row( $wpdb->prepare(
                "SELECT ID, post_title FROM {$wpdb->posts}
                 WHERE post_title LIKE %s
                   AND post_type = 'post'
                   AND post_status IN ('publish','draft','pending','future')
                 LIMIT 1",
                $like
            ) );
            if ( $found ) {
                return [ 'id' => (int) $found->ID, 'title' => $found->post_title, 'exact' => false ];
            }
        }

        return null;
    }

    // ─────────────────────────────────────────────────────────────────
    // Helper: استخراج JSON از پاسخ مدل
    // ─────────────────────────────────────────────────────────────────
    private function extract_json( string $text ): string {
        // حذف ```json ... ```
        $text = preg_replace( '/```json\s*/i', '', $text );
        $text = preg_replace( '/```\s*/',      '', $text );
        $text = trim( $text );

        // اگر متن خودش JSON معتبر است، همان را برگردان
        if ( json_decode( $text, true ) !== null ) {
            return $text;
        }

        // استخراج محتوای بین اولین { تا متناظرترین } با شمارش عمق
        $start = strpos( $text, '{' );
        if ( $start === false ) {
            return $text;
        }

        $depth = 0;
        $len   = strlen( $text );
        for ( $i = $start; $i < $len; $i++ ) {
            if ( $text[ $i ] === '{' ) {
                $depth++;
            } elseif ( $text[ $i ] === '}' ) {
                $depth--;
                if ( $depth === 0 ) {
                    $candidate = substr( $text, $start, $i - $start + 1 );
                    // فقط در صورتی برگردان که JSON معتبر باشد
                    if ( json_decode( $candidate, true ) !== null ) {
                        return $candidate;
                    }
                    break;
                }
            }
        }

        // fallback: روش قدیمی
        $end = strrpos( $text, '}' );
        if ( $end !== false && $end > $start ) {
            return substr( $text, $start, $end - $start + 1 );
        }

        return $text;
    }
    // ─────────────────────────────────────────────────────────────────
    // فشرده‌سازی محتوا برای بازنویسی
    //
    // هدف: کل ساختار مقاله را با حداقل توکن به مدل نشان بدهیم.
    // روش:
    //   - تمام headings (H2/H3) کامل و به ترتیب حفظ می‌شوند
    //   - برای هر بخش: جمله اول + جمله آخر پاراگراف اول نگه‌داشته می‌شود
    //   - پاراگراف اول کل مقاله کامل نگه‌داشته می‌شود (برای context کلمه کلیدی)
    //   - بخش FAQ کامل نگه‌داشته می‌شود
    //   - بقیه پاراگراف‌های میانی: فقط جمله اول
    //
    // خروجی: یک skeleton ساختاریافته که مدل می‌تواند کل مقاله را از روی آن بازنویسی کند
    // ─────────────────────────────────────────────────────────────────
    private function compress_for_rewrite( string $html_content ): array {
        $word_count = ACO_SEO::word_count( $html_content );

        // اگر مقاله کوتاه است (زیر ۴۰۰۰ کاراکتر plain text)، همه را بده
        $plain_full = wp_strip_all_tags( $html_content );
        if ( mb_strlen( $plain_full ) <= 4000 ) {
            return [
                'text'       => $plain_full,
                'word_count' => $word_count,
                'compressed' => false,
            ];
        }

        // FIX BUG-G3: $lines حذف شد — dead code بود و هیچ‌جا استفاده نمی‌شد
        $output = [];

        // ─── پاراگراف اول کامل ───
        // FIX #4: برخی مقالات مستقیم با H2 شروع می‌شوند — در این حالت اولین پاراگراف
        // ممکن است داخل اولین بخش H2 باشد. ابتدا قبل از H2 را چک می‌کنیم؛
        // اگر چیزی نبود، اولین پاراگراف را از هر جایی که باشد استخراج می‌کنیم.
        $before_first_h2 = preg_split( '/<h2/i', $html_content, 2 )[0] ?? '';
        if ( preg_match( '/<p[^>]*>(.*?)<\/p>/is', $before_first_h2, $first_p ) ) {
            $first_para = trim( wp_strip_all_tags( $first_p[1] ) );
            if ( $first_para ) {
                $output[] = '[مقدمه] ' . $first_para;
            }
        } elseif ( preg_match( '/<p[^>]*>(.*?)<\/p>/is', $html_content, $first_p ) ) {
            // مقاله با H2 شروع می‌شود — اولین پاراگراف موجود را از هر بخشی استخراج کن
            $first_para = trim( wp_strip_all_tags( $first_p[1] ) );
            if ( $first_para ) {
                $output[] = '[مقدمه - داخل اولین بخش] ' . $first_para;
            }
        }

        // ─── پردازش بخش‌به‌بخش با regex ───
        // هر بخش با H2 شروع می‌شود
        preg_match_all(
            '/<h2[^>]*>(.*?)<\/h2>(.*?)(?=<h2|$)/is',
            $html_content,
            $sections,
            PREG_SET_ORDER
        );

        foreach ( $sections as $section ) {
            $h2_text     = trim( wp_strip_all_tags( $section[1] ) );
            $body        = $section[2];
            $is_faq      = mb_stripos( $h2_text, 'سوال' ) !== false
                        || mb_stripos( $h2_text, 'FAQ'  ) !== false;

            $output[] = "
## {$h2_text}";

            // H3 های داخل بخش
            preg_match_all( '/<h3[^>]*>(.*?)<\/h3>/is', $body, $h3s );
            foreach ( $h3s[1] as $h3 ) {
                $output[] = '### ' . trim( wp_strip_all_tags( $h3 ) );
            }

            if ( $is_faq ) {
                // بخش FAQ کامل نگه داشته می‌شود
                $faq_plain = trim( wp_strip_all_tags( $body ) );
                $output[]  = $faq_plain;
                continue;
            }

            // پاراگراف‌های بخش — فقط جمله اول هر پاراگراف
            preg_match_all( '/<p[^>]*>(.*?)<\/p>/is', $body, $paras );
            foreach ( $paras[1] as $idx => $para_html ) {
                $para = trim( wp_strip_all_tags( $para_html ) );
                if ( mb_strlen( $para ) < 20 ) continue;

                // جمله اول: تا اولین نقطه/علامت سوال/تعجب
                preg_match( '/^.+?[.؟!]/u', $para, $first_sent );
                $snippet = $first_sent[0] ?? mb_substr( $para, 0, 120 );
                $output[] = '  • ' . trim( $snippet );
            }

            // آیتم‌های لیست — کامل نگه داشته می‌شوند (اغلب اطلاعات کلیدی)
            preg_match_all( '/<li[^>]*>(.*?)<\/li>/is', $body, $list_items );
            if ( ! empty( $list_items[1] ) ) {
                foreach ( array_slice( $list_items[1], 0, 5 ) as $li ) {
                    $li_text = trim( wp_strip_all_tags( $li ) );
                    if ( $li_text ) $output[] = '  - ' . mb_substr( $li_text, 0, 100 );
                }
            }
        }

        // اگر H2 پیدا نشد (مقاله بی‌ساختار)، پاراگراف‌به‌پاراگراف جمله اول
        if ( empty( $sections ) ) {
            preg_match_all( '/<p[^>]*>(.*?)<\/p>/is', $html_content, $all_paras );
            foreach ( $all_paras[1] as $para_html ) {
                $para = trim( wp_strip_all_tags( $para_html ) );
                if ( mb_strlen( $para ) < 20 ) continue;
                preg_match( '/^.+?[.؟!]/u', $para, $first_sent );
                $output[] = ( $first_sent[0] ?? mb_substr( $para, 0, 120 ) );
            }
        }

        $compressed_text = implode( "
", array_filter( $output ) );

        // اطمینان از اینکه خروجی compress شده از ۶۰۰۰ کاراکتر بیشتر نشود
        // BUG-4 FIX: به‌جای cut وسط کاراکتر، آخرین newline کامل را پیدا کن
        // تا markdown شکسته به مدل نرسد (مثلاً ## عنوان نصفه)
        if ( mb_strlen( $compressed_text ) > 6000 ) {
            $cut    = mb_substr( $compressed_text, 0, 6000 );
            $last_nl = mb_strrpos( $cut, "\n" );
            if ( $last_nl !== false && $last_nl > 4000 ) {
                // برش در آخرین newline — خط فعلی کامل نگه داشته می‌شود
                $cut = mb_substr( $cut, 0, $last_nl );
            }
            $compressed_text = $cut . "\n[... ادامه مقاله ...]";
        }

        return [
            'text'       => $compressed_text,
            'word_count' => $word_count,
            'compressed' => true,
        ];
    }

    // ─────────────────────────────────────────────────────────────────
    // بازنویسی/بهبود مقاله موجود
    // ─────────────────────────────────────────────────────────────────
    public function rewrite_post( int $post_id, bool $require_review = false ): array {
        $post = get_post( $post_id );
        if ( ! $post ) {
            return [ 'success' => false, 'error' => 'پست یافت نشد.' ];
        }

        $keyword = get_post_meta( $post_id, '_yoast_wpseo_focuskw', true )
                ?: get_post_meta( $post_id, 'rank_math_focus_keyword', true )
                ?: '';
        $topic   = $post->post_title;
        $current = $post->post_content;

        // FIX: اگر پست فاقد محتوا بود، بازنویسی معنی ندارد
        if ( empty( trim( wp_strip_all_tags( $current ) ) ) ) {
            return [ 'success' => false, 'error' => 'این پست محتوایی ندارد. ابتدا محتوا اضافه کنید.' ];
        }

        // FIX #6: اگر کلمه کلیدی خالی بود، خالی بماند — استفاده از عنوان کامل به عنوان keyword
        // باعث می‌شود is_long_keyword=true شود و امتیاز سئو به اشتباه محاسبه شود.
        // به جای آن یک warning flag در نتیجه برمی‌گردانیم.
        $missing_keyword = empty( $keyword );
        // FIX #5: اگر keyword خالی است، در نتیجه warning برمی‌گردانیم تا کاربر متوجه شود

        $niche     = ACO_Settings::get( 'niche' ) ?: 'تولید محتوای عمومی';
        $tone      = ACO_Settings::get( 'tone' ) ?: 'حرفه‌ای، قابل اعتماد و کاربردی';
        $min_words = absint( ACO_Settings::get( 'min_words' ) ) ?: 1200;

        // لینک‌های داخلی (صفحات دستی تنظیمات + مقالات مرتبط قبلی سایت — خودِ پست فعلی مستثنی می‌شود)
        $link_pages_prompt   = $this->build_internal_links_prompt( $missing_keyword ? '' : $keyword, $post_id );
        $rich_content_prompt = self::RICH_CONTENT_PROMPT;

        $system = "شما یک ویراستار محتوای تخصصی در حوزه {$niche} هستید.
لحن نوشتار: {$tone}.
زبان: فارسی روان و صحیح.
خروجی را به صورت HTML ساده بنویسید (از تگ‌های h2، h3، p، ul، li، و در صورت لزوم table/thead/tbody/tr/th/td و div (فقط برای باکس‌های تزئینی مجاز) استفاده کنید).
هرگز از markdown استفاده نکنید.";

        // REWRITE FIX: به جای برش خام از ابتدا، ساختار کامل مقاله را compress می‌کنیم
        // تا مدل تمام بخش‌ها را ببیند نه فقط ۳۰۰۰ کاراکتر اول
        $compressed = $this->compress_for_rewrite( $current );

        $user = "عنوان مقاله: {$topic}
کلمه کلیدی: {$keyword}
حوزه تخصصی: {$niche}
تعداد کلمات فعلی: {$compressed['word_count']}{$link_pages_prompt}
{$rich_content_prompt}

ساختار و محتوای فعلی مقاله (فشرده‌شده):
{$compressed['text']}

وظیفه: این مقاله را بازنویسی و بهبود بده. تمام بخش‌های موجود را پوشش بده و هیچ بخشی را حذف نکن.
اهداف:
۱. ساختار بهتر — H2 برای بخش‌های اصلی، H3 برای زیربخش‌ها
۲. پاراگراف اول قوی‌تر با کلمه کلیدی «{$keyword}» در جمله اول
۳. حداقل {$min_words} کلمه (مقاله فعلی {$compressed['word_count']} کلمه دارد — نباید کوتاه‌تر شود)
۴. بخش سوالات متداول (FAQ) با حداقل ۳ سوال و جواب کامل
۵. نتیجه‌گیری کاربردی در انتها
۶. تراکم طبیعی کلمه کلیدی (۰.۵٪ تا ۲٪) — از تکرار غیرطبیعی پرهیز کن
۷. حداقل ۲ لینک خارجی به منابع معتبر (Wikipedia، PubMed، سایت‌های دانشگاهی یا سازمان‌های رسمی) — فقط URLهای واقعی و معتبر
۸. اطلاعات تخصصی و آماری موجود در مقاله اصلی را حفظ کن یا بهبود بده

فقط محتوای HTML بده، بدون توضیح اضافه.";

        $result = $this->router->generate( $system, $user, ACO_AI_Router::TIMEOUT_WRITE, ACO_AI_Router::MAX_TOKENS_WRITE );

        if ( ! $result['success'] ) {
            return $result;
        }

        // فیلتر HTML
        $raw_rewrite = wp_kses( $result['text'], self::allowed_content_html() );
        // FIX #3: پاکسازی href از پروتکل‌های خطرناک
        $new_content = preg_replace_callback(
            '/<a\s([^>]*)>/i',
            function( $matches ) {
                $attrs = preg_replace_callback(
                    '/href=["\']([^"\']*)["\']/',
                    function( $m ) {
                        return 'href="' . esc_url( $m[1], [ 'http', 'https' ] ) . '"';
                    },
                    $matches[1]
                );
                return '<a ' . $attrs . '>';
            },
            $raw_rewrite
        );
        $new_content = $new_content ?? $raw_rewrite;
        $new_content = self::restrict_component_classes( $new_content );
        $new_content = self::wrap_tables_responsive( $new_content );

        // FIX: اگر بعد از فیلتر، محتوا خالی شد — بازنویسی را لغو کن
        if ( empty( trim( $new_content ) ) ) {
            return [ 'success' => false, 'error' => 'محتوای بازنویسی‌شده پس از فیلتر HTML خالی شد. لطفاً دوباره تلاش کنید.' ];
        }

        // تولید/به‌روزرسانی تصویر شاخص برای پست‌های قدیمی که کرم بررسی می‌کند —
        // مستقل از require_review (نیاز به تأیید انسانی ندارد چون فقط تصویر است).
        // force=true یعنی حتی اگر پست از قبل تصویر شاخص داشته باشد هم به‌روزرسانی
        // می‌شود؛ فقط اگر تصویر فعلی دستی/غیر-ACO باشد، برای حفظ کار دستی نادیده گرفته می‌شود.
        if ( ACO_Settings::get( 'featured_image_enabled' ) ) {
            ACO_Image_Generator::generate_and_attach( $post_id, $topic, $keyword ?: $topic, true );
        }

        // ── حالت بازبینی: به‌جای اعمال مستقیم روی پست منتشرشده، پیشنهاد را ذخیره کن ──
        // مهم برای سایت‌های YMYL (سلامت/روانشناسی): محتوای بازنویسی‌شده توسط AI
        // بدون تأیید انسانی روی سایت زنده منتشر نمی‌شود. پست فعلی دست‌نخورده می‌ماند
        // تا وقتی از صفحه‌ی «گزارش به‌روزرسانی» تأیید یا رد شود.
        if ( $require_review ) {
            $meta_description = get_post_meta( $post_id, '_yoast_wpseo_metadesc', true )
                             ?: get_post_meta( $post_id, 'rank_math_description', true ) ?: '';
            $seo_title = get_post_meta( $post_id, '_yoast_wpseo_title', true ) ?: $topic;

            if ( $missing_keyword ) {
                $seo_audit = [ 'score' => 0, 'checks' => [], 'word_count' => ACO_SEO::word_count( $new_content ), 'density' => 0 ];
            } else {
                $seo_audit = ACO_SEO::run_audit( $seo_title, $new_content, $meta_description, $keyword, [ 'post_id' => $post_id ] );
            }

            // تصمیم taxonomy را از قبل محاسبه و ذخیره می‌کنیم (بدون اعمال روی پست زنده)
            // تا هنگام تأیید، دوباره نیازی به API call برای suggest_taxonomy نباشد.
            $pending_taxonomy = null;
            $current_cats     = wp_get_post_categories( $post_id );
            $has_only_uncategorized = count( $current_cats ) === 1 && (int) $current_cats[0] === (int) get_option( 'default_category' );
            if ( empty( $current_cats ) || $has_only_uncategorized ) {
                $tax_kw    = $missing_keyword ? $topic : $keyword;
                $local_cat = $this->find_local_category( $tax_kw );
                if ( $local_cat ) {
                    $pending_taxonomy = [ 'category' => $local_cat, 'tags' => [] ];
                } else {
                    $snippet     = mb_substr( wp_strip_all_tags( $new_content ), 0, 500 );
                    $tax_suggest = $this->suggest_taxonomy( $tax_kw, $topic, $snippet );
                    if ( $tax_suggest['success'] && ! empty( $tax_suggest['category'] ) ) {
                        $pending_taxonomy = [ 'category' => $tax_suggest['category'], 'tags' => $tax_suggest['tags'] ?? [] ];
                    }
                }
            }

            update_post_meta( $post_id, '_aco_pending_rewrite_content', $new_content );
            update_post_meta( $post_id, '_aco_pending_rewrite_score', (int) $seo_audit['score'] );
            update_post_meta( $post_id, '_aco_pending_rewrite_checks', wp_json_encode( $seo_audit['checks'] ) );
            update_post_meta( $post_id, '_aco_pending_rewrite_word_count', (int) $seo_audit['word_count'] );
            update_post_meta( $post_id, '_aco_pending_rewrite_taxonomy', wp_json_encode( $pending_taxonomy ) );
            update_post_meta( $post_id, '_aco_pending_rewrite_date', current_time( 'mysql' ) );
            update_post_meta( $post_id, '_aco_pending_rewrite_score_before', (int) get_post_meta( $post_id, '_aco_seo_score', true ) );

            ACO_Logger::info( "⏸️ پیشنهاد بازنویسی برای پست #{$post_id} در انتظار بازبینی ذخیره شد (منتشر نشد)", 'generator' );

            return [
                'success'        => true,
                'pending_review' => true,
                'post_id'        => $post_id,
                'seo_score'      => $seo_audit['score'],
                'tokens'         => $result['tokens'] ?? 0,
            ];
        }

        // ذخیره revision قبل از بازنویسی
        wp_save_post_revision( $post_id );

        // خروجی به بلاک‌های گوتنبرگ — فقط همین‌جا (مسیر انتشار فوری)؛ در مسیر
        // require_review=true، $new_content هنوز HTML ساده در postmeta ذخیره
        // می‌شود تا ادمین بتواند قبل از تأیید آن را در textarea راحت ویرایش کند
        // — تبدیل نهایی به بلاک در apply_pending_rewrite() انجام می‌شود.
        $content_to_save = ACO_Settings::get( 'gutenberg_blocks_enabled' )
            ? ACO_Gutenberg::convert_html_to_blocks( $new_content )
            : $new_content;

        // FIX: تاریخ انتشار اصلی حفظ شود — post_date تغییر نکند
        // بدون این، وردپرس post_date را به الان تغییر می‌دهد و URL/slug ممکن است عوض شود
        $update_args = [
            'ID'            => $post_id,
            'post_content'  => $content_to_save,
            'post_date'     => $post->post_date,
            'post_date_gmt' => $post->post_date_gmt,
        ];

        // چکیده: فقط اگر پست از قبل چکیده‌ای ندارد پر می‌شود — تا چکیده‌ی
        // دستی نوشته‌شده توسط ادمین دست‌نخورده بماند (همان منطق تصویر شاخص).
        if ( trim( (string) $post->post_excerpt ) === '' ) {
            $update_args['post_excerpt'] = ACO_SEO::build_excerpt_from_content( $new_content );
        }

        $updated = wp_update_post( $update_args, true );

        if ( is_wp_error( $updated ) ) {
            return [ 'success' => false, 'error' => $updated->get_error_message() ];
        }

        // audit سئو بعد از بازنویسی
        $meta_description = get_post_meta( $post_id, '_yoast_wpseo_metadesc', true )
                         ?: get_post_meta( $post_id, 'rank_math_description', true ) ?: '';
        $seo_title = get_post_meta( $post_id, '_yoast_wpseo_title', true )
                  ?: $topic;
        // FIX #6: اگر keyword خالی است، audit را با keyword=topic اجرا نکن
        // (امتیاز سئو بدون keyword معنی ندارد — صفر برمی‌گردانیم)
        if ( $missing_keyword ) {
            $seo_audit = [ 'score' => 0, 'checks' => [], 'word_count' => ACO_SEO::word_count( $new_content ), 'density' => 0 ];
        } else {
            $seo_audit = ACO_SEO::run_audit( $seo_title, $new_content, $meta_description, $keyword, [ 'post_id' => $post_id ] );
        }

        // ذخیره متادیتا
        update_post_meta( $post_id, '_aco_generated', true );
        update_post_meta( $post_id, '_aco_rewritten_at', current_time( 'mysql' ) );
        update_post_meta( $post_id, '_aco_seo_score', $seo_audit['score'] );
        update_post_meta( $post_id, '_aco_seo_checks', wp_json_encode( $seo_audit['checks'] ) );
        delete_transient( 'aco_generated_count' );

        // پیشنهاد taxonomy (اگر پست فاقد دسته‌بندی مناسب باشد)
        // BUG-3 FIX: ابتدا local keyword matching بدون API call امتحان می‌کنیم.
        // فقط اگر هیچ دسته‌ای match نشد، suggest_taxonomy (API call) فراخوانی می‌شود.
        $applied_taxonomy = [ 'category' => '', 'tags' => [] ];
        $current_cats = wp_get_post_categories( $post_id );
        $has_only_uncategorized = count( $current_cats ) === 1 && (int) $current_cats[0] === (int) get_option( 'default_category' );
        if ( empty( $current_cats ) || $has_only_uncategorized ) {
            $tax_kw    = $missing_keyword ? $topic : $keyword;
            $local_cat = $this->find_local_category( $tax_kw );

            if ( $local_cat ) {
                // دسته موجود با keyword مطابقت دارد — API call لازم نیست
                $applied_taxonomy = $this->apply_taxonomy( $post_id, $local_cat, [] );
            } else {
                // هیچ دسته‌ای match نشد — حالا API call توجیه دارد
                $snippet     = mb_substr( wp_strip_all_tags( $new_content ), 0, 500 );
                $tax_suggest = $this->suggest_taxonomy( $tax_kw, $topic, $snippet );
                if ( $tax_suggest['success'] && ! empty( $tax_suggest['category'] ) ) {
                    $applied_taxonomy = $this->apply_taxonomy( $post_id, $tax_suggest['category'], $tax_suggest['tags'] ?? [] );
                }
            }
        }

        // پیشنهاد internal link
        $internal_links = ACO_SEO::suggest_internal_links( $keyword, $new_content, $post_id );

        // بررسی لینک‌های داخلی که مدل از لیست settings در متن قرار داده
        $placed_links = $this->detect_placed_links( $new_content, (array) ( ACO_Settings::get( 'internal_link_pages' ) ?? [] ) );

        return [
            'success'        => true,
            'post_id'        => $post_id,
            'edit_url'       => get_edit_post_link( $post_id, 'raw' ),
            'view_url'       => get_permalink( $post_id ),
            'seo_score'      => $seo_audit['score'],
            'seo_checks'     => $seo_audit['checks'],
            'word_count'     => $seo_audit['word_count'],
            'tokens'         => $result['tokens'] ?? 0,
            'internal_links' => $internal_links,
            'placed_links'   => $placed_links,
            'taxonomy'       => $applied_taxonomy,
            'missing_keyword'=> $missing_keyword,
        ];
    }

    /**
     * تأیید یک پیشنهاد بازنویسیِ در انتظار بازبینی — همان کاری که rewrite_post
     * در حالت عادی (بدون review) بلافاصله انجام می‌داد، اینجا با تأخیر و بعد از
     * تأیید صریح ادمین انجام می‌شود. محتوای پیشنهادی از postmeta خوانده می‌شود
     * (بدون فراخوانی دوباره‌ی AI — توکن اضافه مصرف نمی‌شود).
     */
    public function apply_pending_rewrite( int $post_id, ?string $override_content = null ): array {
        $post = get_post( $post_id );
        if ( ! $post ) {
            return [ 'success' => false, 'error' => 'پست یافت نشد.' ];
        }

        $pending_content = get_post_meta( $post_id, '_aco_pending_rewrite_content', true );
        if ( empty( $pending_content ) ) {
            return [ 'success' => false, 'error' => 'هیچ پیشنهاد بازنویسیِ در انتظاری برای این پست یافت نشد.' ];
        }

        // اگر ادمین قبل از تأیید متن را ویرایش کرده، همان نسخه‌ی ویرایش‌شده منتشر می‌شود
        // (بعد از عبور از همان whitelist تگ‌های HTML که در تولید اولیه استفاده می‌شود)
        $final_content = $pending_content;
        $was_edited    = false;
        if ( $override_content !== null && trim( $override_content ) !== '' ) {
            $sanitized = wp_kses( $override_content, self::allowed_rewrite_html() );
            $sanitized = self::restrict_component_classes( $sanitized );
            $sanitized = self::wrap_tables_responsive( $sanitized );
            if ( trim( $sanitized ) !== '' && trim( $sanitized ) !== trim( $pending_content ) ) {
                $final_content = $sanitized;
                $was_edited    = true;
            }
        }

        // ذخیره revision از محتوای فعلی (قبل از جایگزینی) — قابل بازگشت از تاریخچه‌ی وردپرس
        wp_save_post_revision( $post_id );

        // خروجی به بلاک‌های گوتنبرگ — فقط در متغیر جداگانه‌ی $content_to_save.
        // $final_content عمداً دست‌نخورده می‌ماند چون seo audit زیر (بعد از
        // wp_update_post) روی همان HTML ساده اجرا می‌شود، نه نسخه‌ی بلاکی.
        $content_to_save = ACO_Settings::get( 'gutenberg_blocks_enabled' )
            ? ACO_Gutenberg::convert_html_to_blocks( $final_content )
            : $final_content;

        $update_args = [
            'ID'            => $post_id,
            'post_content'  => $content_to_save,
            'post_date'     => $post->post_date,
            'post_date_gmt' => $post->post_date_gmt,
        ];

        // چکیده: فقط اگر پست از قبل چکیده‌ای ندارد پر می‌شود
        if ( trim( (string) $post->post_excerpt ) === '' ) {
            $update_args['post_excerpt'] = ACO_SEO::build_excerpt_from_content( $final_content );
        }

        $updated = wp_update_post( $update_args, true );
        if ( is_wp_error( $updated ) ) {
            return [ 'success' => false, 'error' => $updated->get_error_message() ];
        }

        // FIX: اگر ادمین متن را ویرایش کرد، امتیاز سئوی ذخیره‌شده دیگر معتبر نیست
        // (طول متن/چگالی کلمه کلیدی عوض شده) — روی نسخه‌ی نهایی دوباره محاسبه می‌کنیم.
        if ( $was_edited ) {
            $keyword = get_post_meta( $post_id, '_yoast_wpseo_focuskw', true )
                    ?: get_post_meta( $post_id, 'rank_math_focus_keyword', true ) ?: '';
            $meta_description = get_post_meta( $post_id, '_yoast_wpseo_metadesc', true )
                             ?: get_post_meta( $post_id, 'rank_math_description', true ) ?: '';
            $seo_title = get_post_meta( $post_id, '_yoast_wpseo_title', true ) ?: $post->post_title;

            if ( $keyword === '' ) {
                $score  = 0;
                $checks = wp_json_encode( [] );
                $readability_score = 0;
            } else {
                $seo_audit = ACO_SEO::run_audit( $seo_title, $final_content, $meta_description, $keyword, [ 'post_id' => $post_id ] );
                $score     = (int) $seo_audit['score'];
                $checks    = wp_json_encode( $seo_audit['checks'] );
                $readability_score = (int) ( $seo_audit['readability']['score'] ?? 0 );
            }
        } else {
            $score  = (int) get_post_meta( $post_id, '_aco_pending_rewrite_score', true );
            $checks = get_post_meta( $post_id, '_aco_pending_rewrite_checks', true );
            // تحلیل خوانایی برای مسیر بدون ویرایش هنوز در pending meta ذخیره نمی‌شود —
            // مقدار قبلی (اگر از تولید اولیه‌ی این پست باقی مانده) حفظ می‌شود، صفر نمی‌شود
            $readability_score = (int) get_post_meta( $post_id, '_aco_readability_score', true );
        }

        update_post_meta( $post_id, '_aco_generated', true );
        update_post_meta( $post_id, '_aco_rewritten_at', current_time( 'mysql' ) );
        update_post_meta( $post_id, '_aco_seo_score', $score );
        update_post_meta( $post_id, '_aco_seo_checks', $checks );
        update_post_meta( $post_id, '_aco_readability_score', $readability_score );
        delete_transient( 'aco_generated_count' );

        // اعمال taxonomy که از قبل تصمیم‌گیری و ذخیره شده بود
        $taxonomy = json_decode( (string) get_post_meta( $post_id, '_aco_pending_rewrite_taxonomy', true ), true );
        if ( ! empty( $taxonomy['category'] ) ) {
            $this->apply_taxonomy( $post_id, $taxonomy['category'], $taxonomy['tags'] ?? [] );
        }

        $this->clear_pending_rewrite_meta( $post_id );

        $log_suffix = $was_edited ? ' (با ویرایش دستی ادمین قبل از انتشار)' : '';
        ACO_Logger::success( "✅ بازنویسی پست #{$post_id} پس از بازبینی تأیید و منتشر شد{$log_suffix}", 'generator' );

        return [ 'success' => true, 'post_id' => $post_id, 'seo_score' => $score, 'was_edited' => $was_edited ];
    }

    /**
     * whitelist تگ‌های مجاز HTML برای محتوای تولید/بازنویسی‌شده — هم در rewrite_post
     * و هم در apply_pending_rewrite (برای پاکسازی ویرایش دستی ادمین) استفاده می‌شود.
     */
    private static function allowed_rewrite_html(): array {
        return self::allowed_content_html();
    }

    /**
     * رد یک پیشنهاد بازنویسیِ در انتظار — پست زنده هیچ‌وقت دست‌نخورده بود،
     * فقط postmeta پیشنهاد پاک می‌شود.
     */
    public function discard_pending_rewrite( int $post_id ): bool {
        $had_pending = (bool) get_post_meta( $post_id, '_aco_pending_rewrite_content', true );
        $this->clear_pending_rewrite_meta( $post_id );
        if ( $had_pending ) {
            ACO_Logger::info( "🗑️ پیشنهاد بازنویسی پست #{$post_id} رد شد", 'generator' );
        }
        return $had_pending;
    }

    private function clear_pending_rewrite_meta( int $post_id ): void {
        foreach ( [
            '_aco_pending_rewrite_content',
            '_aco_pending_rewrite_score',
            '_aco_pending_rewrite_checks',
            '_aco_pending_rewrite_word_count',
            '_aco_pending_rewrite_taxonomy',
            '_aco_pending_rewrite_date',
            '_aco_pending_rewrite_score_before',
        ] as $key ) {
            delete_post_meta( $post_id, $key );
        }
    }

    /**
     * لیست همه‌ی پست‌هایی که یک پیشنهاد بازنویسیِ در انتظار بازبینی دارند.
     * برای نمایش در صفحه‌ی «گزارش به‌روزرسانی».
     */
    public static function get_pending_reviews(): array {
        global $wpdb;
        $post_ids = $wpdb->get_col(
            "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_aco_pending_rewrite_content' AND meta_value != ''"
        );

        $items = [];
        foreach ( $post_ids as $pid ) {
            $pid = (int) $pid;
            $post = get_post( $pid );
            if ( ! $post ) continue; // پست حذف شده — بی‌معنی، نادیده بگیر
            $items[] = [
                'post_id'     => $pid,
                'title'       => $post->post_title,
                'date'        => get_post_meta( $pid, '_aco_pending_rewrite_date', true ),
                'score_before'=> (int) get_post_meta( $pid, '_aco_pending_rewrite_score_before', true ),
                'score_after' => (int) get_post_meta( $pid, '_aco_pending_rewrite_score', true ),
                'word_count'  => (int) get_post_meta( $pid, '_aco_pending_rewrite_word_count', true ),
                'content'     => get_post_meta( $pid, '_aco_pending_rewrite_content', true ),
            ];
        }

        usort( $items, static function( $a, $b ) {
            return strtotime( $b['date'] ?: '1970-01-01' ) <=> strtotime( $a['date'] ?: '1970-01-01' );
        } );

        return $items;
    }

    // ─────────────────────────────────────────────────────────────────
    // بررسی لینک‌های داخلی تعریف‌شده که مدل در متن قرار داده
    // HTML محتوا را parse می‌کند و URLهای موجود در internal_link_pages
    // را شناسایی می‌کند — خروجی: آرایه‌ای از [{title, url}]
    // ─────────────────────────────────────────────────────────────────
    private function detect_placed_links( string $html, array $link_pages ): array {
        if ( empty( $link_pages ) || empty( $html ) ) return [];

        $placed = [];
        // URL‌های تعریف‌شده برای مقایسه سریع
        $defined_urls = [];
        foreach ( $link_pages as $lp ) {
            $url = $lp['url'] ?? '';
            if ( $url ) $defined_urls[ rtrim( $url, '/' ) ] = $lp;
        }

        // استخراج href‌ها با regex ساده — بدون DOMDocument برای سازگاری بیشتر
        preg_match_all( '/<a[^>]+href=[\x22\x27]([^\x22\x27]+)[\x22\x27][^>]*>/i', $html, $matches ); // BUG-G2 FIX: از hex escape برای quote-ها استفاده می‌کنیم
        foreach ( $matches[1] as $href ) {
            $clean = rtrim( $href, '/' );
            if ( isset( $defined_urls[ $clean ] ) ) {
                $lp = $defined_urls[ $clean ];
                $placed[] = [
                    'title' => $lp['title'] ?? $clean,
                    'url'   => $href,
                ];
                unset( $defined_urls[ $clean ] ); // هر URL یک‌بار گزارش شود
            }
        }
        return $placed;
    }

    private function find_local_category( string $keyword ): string {
        if ( empty( trim( $keyword ) ) ) return '';

        $cats = get_terms( [
            'taxonomy'   => 'category',
            'hide_empty' => false,
            'fields'     => 'names',
            'number'     => 50,
        ] );

        if ( ! is_array( $cats ) || empty( $cats ) ) return '';

        // نرمال‌سازی keyword (ی/ک عربی → فارسی، حذف نیم‌فاصله)
        $kw_normalized = ACO_SEO::normalize_persian( mb_strtolower( $keyword ) );
        $kw_words = array_filter(
            preg_split( '/\s+/u', $kw_normalized, -1, PREG_SPLIT_NO_EMPTY ),
            function( $w ) { return mb_strlen( $w ) >= 2; }
        );

        if ( empty( $kw_words ) ) return '';

        $best_cat   = '';
        $best_score = 0;

        foreach ( $cats as $cat_name ) {
            $cat_normalized = ACO_SEO::normalize_persian( mb_strtolower( $cat_name ) );
            $score = 0;
            foreach ( $kw_words as $w ) {
                if ( mb_strpos( $cat_normalized, $w ) !== false ) {
                    $score++;
                }
            }
            if ( $score > $best_score ) {
                $best_score = $score;
                $best_cat   = $cat_name;
            }
        }

        // حداقل یک کلمه باید مطابقت داشته باشد
        return $best_score > 0 ? $best_cat : '';
    }

    // ─────────────────────────────────────────────────────────────────
    // تولید خودکار دسته‌بندی و تگ با AI
    // ─────────────────────────────────────────────────────────────────
    public function suggest_taxonomy( string $keyword, string $topic, string $content_snippet = '' ): array {
        $niche = ACO_Settings::get( 'niche' ) ?: 'تولید محتوای عمومی';

        // دسته‌بندی‌های موجود سایت
        $existing_cats = get_terms( [
            'taxonomy'   => 'category',
            'hide_empty' => false,
            'fields'     => 'names',
            'number'     => 50,
        ] );
        $existing_tags = get_terms( [
            'taxonomy'   => 'post_tag',
            'hide_empty' => false,
            'fields'     => 'names',
            'number'     => 100,
        ] );

        $cats_str = is_array( $existing_cats ) ? implode( '، ', $existing_cats ) : '';
        $tags_str = is_array( $existing_tags ) ? implode( '، ', $existing_tags ) : '';

        $system = "شما یک متخصص دسته‌بندی محتوا در حوزه {$niche} هستید.
پاسخ را فقط به صورت JSON معتبر بدهید. هیچ متن اضافه‌ای قبل یا بعد از JSON ننویسید.";

        $user = "کلمه کلیدی: {$keyword}
عنوان مقاله: {$topic}
حوزه سایت: {$niche}
" . ( $content_snippet ? "خلاصه محتوا: " . mb_substr( $content_snippet, 0, 300 ) . "\n" : "" ) . "
دسته‌بندی‌های موجود سایت: " . ( $cats_str ?: 'هیچ‌کدام' ) . "
تگ‌های موجود سایت: " . ( $tags_str ?: 'هیچ‌کدام' ) . "

وظیفه:
۱. یک دسته‌بندی اصلی مناسب برای این مقاله انتخاب یا پیشنهاد بده (اگر در لیست موجود مناسبی هست، همان را انتخاب کن؛ در غیر این صورت یک دسته جدید پیشنهاد بده)
۲. بین ۳ تا ۶ تگ مرتبط پیشنهاد بده (ترکیبی از موجود و جدید)

پاسخ دقیقاً در این فرمت:
{
  \"category\": \"نام دسته‌بندی\",
  \"category_is_new\": true,
  \"tags\": [\"تگ ۱\", \"تگ ۲\", \"تگ ۳\"],
  \"tags_new\": [\"تگ‌هایی که جدید هستند\"]
}";

        $result = $this->router->generate( $system, $user );

        if ( ! $result['success'] ) {
            return [ 'success' => false, 'error' => $result['error'] ];
        }

        $clean  = $this->extract_json( $result['text'] );
        $parsed = json_decode( $clean, true );

        if ( json_last_error() !== JSON_ERROR_NONE || empty( $parsed['category'] ) ) {
            return [
                'success'          => true,
                'category'         => '',
                'category_is_new'  => false,
                'tags'             => [],
                'tags_new'         => [],
                'fallback'         => true,
            ];
        }

        return [
            'success'         => true,
            'category'        => sanitize_text_field( $parsed['category'] ),
            'category_is_new' => (bool) ( $parsed['category_is_new'] ?? true ),
            'tags'            => array_map( 'sanitize_text_field', (array) ( $parsed['tags'] ?? [] ) ),
            'tags_new'        => array_map( 'sanitize_text_field', (array) ( $parsed['tags_new'] ?? [] ) ),
        ];
    }

    // ─────────────────────────────────────────────────────────────────
    // اعمال دسته‌بندی و تگ روی پست
    // ─────────────────────────────────────────────────────────────────
    public function apply_taxonomy( int $post_id, string $category, array $tags ): array {
        $result = [ 'category' => '', 'tags' => [] ];

        // دسته‌بندی
        if ( $category ) {
            $cat = get_term_by( 'name', $category, 'category' );
            if ( $cat ) {
                $cat_id = $cat->term_id;
            } else {
                // FIX: wp_insert_term می‌تواند WP_Error با کد term_exists برگرداند
                // وقتی term با slug متفاوت اما name یکسان وجود دارد
                $new_cat = wp_insert_term( $category, 'category' );
                if ( is_wp_error( $new_cat ) ) {
                    // بررسی کد term_exists — اگر term قبلاً ثبت است، ID آن را بگیر
                    $existing_id = $new_cat->get_error_data( 'term_exists' );
                    $cat_id = $existing_id ? (int) $existing_id : 0;
                } else {
                    $cat_id = (int) $new_cat['term_id'];
                }
            }
            if ( $cat_id ) {
                wp_set_post_categories( $post_id, [ $cat_id ] );
                $result['category'] = $category;
            }
        }

        // تگ‌ها
        if ( ! empty( $tags ) ) {
            // FIX #7: append=true در rewrite — تگ‌های قدیمی پاک نشوند
            // (در ایجاد پست جدید false هم قابل قبول است چون پست تازه است)
            wp_set_post_tags( $post_id, $tags, true );
            $result['tags'] = $tags;
        }

        return $result;
    }

}
