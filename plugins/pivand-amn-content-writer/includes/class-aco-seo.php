<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * ابزارهای سئو: تحلیل تراکم کلمه کلیدی، طول عنوان/متا، چک‌لیست و امتیاز سئو.
 */
class ACO_SEO {

    const TITLE_MIN        = 50;
    const TITLE_MAX        = 60;
    const META_MIN         = 120;
    const META_MAX         = 160;
    const DENSITY_MIN      = 0.5;
    const DENSITY_MAX      = 2.5;
    const MIN_WORD_COUNT   = 1000;
    const MIN_H2_COUNT     = 4;
    const LONG_KEYWORD_WORD_THRESHOLD = 3;
    const LONG_KEYWORD_OVERLAP_RATIO  = 0.6;
    const EXCERPT_MIN      = 100;
    const EXCERPT_MAX      = 220;

    public static function normalize_persian( string $text ): string {
        $map = [
            'ي' => 'ی', 'ك' => 'ک', 'ة' => 'ه',
            'ٔ' => '', 'ّ' => '', 'ْ' => '', 'ِ' => '', 'َ' => '', 'ُ' => '',
        ];
        $text = strtr( $text, $map );
        $text = preg_replace( '/\x{200C}/u', ' ', $text );
        $text = preg_replace( '/\s+/u', ' ', $text );
        return trim( $text );
    }

    public static function word_count( string $content ): int {
        $plain = self::normalize_persian( wp_strip_all_tags( $content ) );
        if ( $plain === '' ) return 0;
        $words = preg_split( '/\s+/u', $plain, -1, PREG_SPLIT_NO_EMPTY );
        return count( $words );
    }

    public static function count_keyword_occurrences( string $haystack, string $keyword ): int {
        $keyword = trim( $keyword );
        if ( $keyword === '' ) return 0;
        $h = self::normalize_persian( mb_strtolower( wp_strip_all_tags( $haystack ) ) );
        $k = self::normalize_persian( mb_strtolower( $keyword ) );
        return substr_count( $h, $k );
    }

    private static function keyword_words( string $keyword ): array {
        $k = self::normalize_persian( mb_strtolower( trim( $keyword ) ) );
        if ( $k === '' ) return [];
        $words = preg_split( '/\s+/u', $k, -1, PREG_SPLIT_NO_EMPTY );
        return array_values( array_unique( $words ) );
    }

    public static function is_long_keyword( string $keyword ): bool {
        return count( self::keyword_words( $keyword ) ) > self::LONG_KEYWORD_WORD_THRESHOLD;
    }

    public static function keyword_is_present( string $haystack, string $keyword ): bool {
        if ( trim( $keyword ) === '' ) return false;

        if ( ! self::is_long_keyword( $keyword ) ) {
            return self::count_keyword_occurrences( $haystack, $keyword ) > 0;
        }

        $words = self::keyword_words( $keyword );
        if ( empty( $words ) ) return false;

        $h = self::normalize_persian( mb_strtolower( wp_strip_all_tags( $haystack ) ) );
        $matched = 0;
        foreach ( $words as $w ) {
            if ( mb_strlen( $w ) < 2 ) continue;
            if ( substr_count( $h, $w ) > 0 ) $matched++;
        }

        $meaningful = count( array_filter( $words, function( $w ) { return mb_strlen( $w ) >= 2; } ) );
        if ( $meaningful === 0 ) return false;

        return ( $matched / $meaningful ) >= self::LONG_KEYWORD_OVERLAP_RATIO;
    }

    public static function keyword_density( string $content, string $keyword ): float {
        $wc = self::word_count( $content );
        if ( $wc === 0 ) return 0.0;

        if ( self::is_long_keyword( $keyword ) ) {
            $words = array_filter( self::keyword_words( $keyword ), function( $w ) { return mb_strlen( $w ) >= 2; } );
            if ( empty( $words ) ) return 0.0;

            $h = self::normalize_persian( mb_strtolower( wp_strip_all_tags( $content ) ) );
            $total_occ = 0;
            foreach ( $words as $w ) {
                $total_occ += substr_count( $h, $w );
            }
            $avg_occ = $total_occ / count( $words );
            return round( ( $avg_occ / $wc ) * 100, 2 );
        }

        $occ = self::count_keyword_occurrences( $content, $keyword );
        return round( ( $occ / $wc ) * 100, 2 );
    }

    public static function keyword_in_first_paragraph( string $content, string $keyword ): bool {
        // BUG-07 FIX: برای محتوایی که مستقیم با <h2> شروع می‌شود،
        // محتوای قبل از <h2> اول خالی است — ولی کلمه کلیدی ممکن است
        // در اولین <p> داخل همان H2 باشد.
        // راه‌حل: اول <p> قبل از H2 را چک کن؛ اگر نبود، اولین <p> هر جایی را چک کن.
        if ( preg_match( '/<p[^>]*>(.*?)<\/p>/is', $content, $m ) ) {
            $first = $m[1];
            // اگر اولین پاراگراف خیلی کوتاه بود (کمتر از 20 کاراکتر)، 300 کاراکتر اول را هم اضافه کن
            $plain_first = trim( wp_strip_all_tags( $first ) );
            if ( mb_strlen( $plain_first ) < 20 ) {
                $plain_first = mb_substr( wp_strip_all_tags( $content ), 0, 300 );
            }
        } else {
            // هیچ تگ <p> ای در محتوا نیست — از متن plain استفاده کن
            $plain_first = mb_substr( wp_strip_all_tags( $content ), 0, 300 );
        }
        return self::keyword_is_present( $plain_first, $keyword );
    }

    public static function count_h2( string $content ): int {
        preg_match_all( '/<h2[^>]*>.*?<\/h2>/is', $content, $m );
        return count( $m[0] );
    }

    public static function h2_with_keyword_count( string $content, string $keyword ): int {
        preg_match_all( '/<h2[^>]*>(.*?)<\/h2>/is', $content, $m );
        $count = 0;
        foreach ( $m[1] as $heading ) {
            if ( self::keyword_is_present( $heading, $keyword ) ) $count++;
        }
        return $count;
    }

    public static function build_seo_title( string $keyword, string $topic, int $max = self::TITLE_MAX ): string {
        $title = trim( $topic );

        if ( mb_strlen( $title ) <= $max ) {
            if ( ! self::is_long_keyword( $keyword ) && ! self::keyword_is_present( $title, $keyword ) && $keyword !== '' ) {
                $candidate = trim( $keyword ) . ' | ' . $title;
                if ( mb_strlen( $candidate ) <= $max ) {
                    return $candidate;
                }
            }
            return $title;
        }

        $trimmed = mb_substr( $title, 0, $max - 1 );
        $last_space = mb_strrpos( $trimmed, ' ' );
        if ( $last_space !== false && $last_space > $max * 0.5 ) {
            $trimmed = mb_substr( $trimmed, 0, $last_space );
        }
        return rtrim( $trimmed, " \t\n\r\0\x0B،.-" ) . '…';
    }

    public static function truncate_meta_description( string $text, int $max = self::META_MAX ): string {
        $text = trim( wp_strip_all_tags( $text ) );
        if ( mb_strlen( $text ) <= $max ) return $text;

        $trimmed = mb_substr( $text, 0, $max - 1 );
        $last_space = mb_strrpos( $trimmed, ' ' );
        if ( $last_space !== false && $last_space > $max * 0.6 ) {
            $trimmed = mb_substr( $trimmed, 0, $last_space );
        }
        return rtrim( $trimmed, " \t\n\r\0\x0B،.-" ) . '…';
    }

    /**
     * ساخت خودکار چکیده (excerpt) مقاله از روی محتوای HTML تولیدشده —
     * بدون فراخوانی جدید AI (رایگان). اولویت با اولین پاراگراف واقعی متن
     * است (نه سرتیتر)، چون معمولاً همان‌جا موضوع مقاله معرفی می‌شود.
     * اگر پاراگراف اول خیلی کوتاه بود، پاراگراف بعدی هم اضافه می‌شود تا
     * به حداقل طول برسد. خروجی همیشه در بازه‌ی EXCERPT_MIN..EXCERPT_MAX
     * کاراکتر و بدون بریدن وسط کلمه/جمله است.
     */
    public static function build_excerpt_from_content( string $html_content, int $max = self::EXCERPT_MAX ): string {
        // پاراگراف‌های <p> را جدا می‌کنیم تا سرتیترها (h2/h3) وارد چکیده نشوند
        $paragraphs = [];
        if ( preg_match_all( '/<p[^>]*>(.*?)<\/p>/is', $html_content, $matches ) && ! empty( $matches[1] ) ) {
            foreach ( $matches[1] as $p ) {
                $clean = trim( wp_strip_all_tags( $p ) );
                if ( $clean !== '' ) {
                    $paragraphs[] = $clean;
                }
            }
        }

        // فال‌بک: اگر تگ <p> پیدا نشد (مثلاً محتوای بلاکی گوتنبرگ)، کل متن ساده‌شده را بردار
        if ( empty( $paragraphs ) ) {
            $plain = trim( preg_replace( '/\s+/u', ' ', wp_strip_all_tags( $html_content ) ) );
            $paragraphs = $plain !== '' ? [ $plain ] : [];
        }

        if ( empty( $paragraphs ) ) {
            return '';
        }

        $text = $paragraphs[0];
        $i = 1;
        while ( mb_strlen( $text ) < self::EXCERPT_MIN && isset( $paragraphs[ $i ] ) ) {
            $text .= ' ' . $paragraphs[ $i ];
            $i++;
        }

        $text = trim( preg_replace( '/\s+/u', ' ', $text ) );

        if ( mb_strlen( $text ) <= $max ) {
            return $text;
        }

        $trimmed = mb_substr( $text, 0, $max - 1 );
        $last_space = mb_strrpos( $trimmed, ' ' );
        if ( $last_space !== false && $last_space > $max * 0.5 ) {
            $trimmed = mb_substr( $trimmed, 0, $last_space );
        }
        return rtrim( $trimmed, " \t\n\r\0\x0B،.-" ) . '…';
    }

    /**
     * چک‌لیست کامل سئو + امتیاز ۰ تا ۱۰۰
     *
     * FIX: مشکل قبلی: وقتی post_id نداریم، $img_check['pass'] = null بود،
     * اما چک featured_image_alt همیشه در لیست بود و null pass آن را صفر می‌کرد.
     * اکنون وقتی pass=null است، از total_weight هم کم می‌شود (چک بی‌طرف).
     */
    public static function run_audit( string $title, string $content, string $meta_description, string $keyword, array $extra = [] ): array {
        $min_wc    = absint( class_exists( 'ACO_Settings' ) ? ACO_Settings::get( 'min_words' ) : 0 ) ?: self::MIN_WORD_COUNT;
        $title_len = mb_strlen( $title );
        $meta_len  = mb_strlen( $meta_description );
        $density   = self::keyword_density( $content, $keyword );
        $wc        = self::word_count( $content );
        $h2_total  = self::count_h2( $content );
        $h2_kw     = self::h2_with_keyword_count( $content, $keyword );
        $is_long   = self::is_long_keyword( $keyword );

        // شمارش external links
        preg_match_all( '/<a[^>]+href=["\']https?:\/\/[^"\']+["\'][^>]*>/i', $content, $ext_links );
        $ext_link_count = count( $ext_links[0] ?? [] );

        // FIX: img_check با pass=null شروع می‌کند — وقتی post_id نباشد یا تصویر نباشد
        // مقدار null یعنی «چک اجرا نشد» و از امتیازدهی کنار گذاشته می‌شود
        $img_check = [ 'pass' => null, 'detail' => 'چک نشده' ];
        if ( isset( $extra['post_id'] ) ) {
            $thumb_id = get_post_thumbnail_id( (int) $extra['post_id'] );
            if ( $thumb_id ) {
                // تصویر شاخص وجود دارد — alt text را بررسی کن
                $alt = get_post_meta( $thumb_id, '_wp_attachment_image_alt', true );
                $img_check['pass']   = ! empty( trim( (string) $alt ) ); // FIX: empty trim برای جلوگیری از false positive با ' '
                $img_check['detail'] = empty( trim( (string) $alt ) )
                    ? 'تصویر شاخص alt text ندارد'
                    : 'alt text: ' . mb_substr( $alt, 0, 40 );
            } else {
                // post_id داریم اما تصویر شاخص ندارد — این یک مشکل سئو است (pass=false)
                $img_check['pass']   = false;
                $img_check['detail'] = 'تصویر شاخص تنظیم نشده';
            }
        }

        // چک slug
        $slug_check = null;
        if ( isset( $extra['post_id'] ) ) {
            $slug_check = self::check_slug( (int) $extra['post_id'] );
        }
        $has_h1_in_content = self::content_has_h1( $content );
        $readability = self::readability_analysis( $content );

        $checks = [
            [
                'key'    => 'title_has_keyword',
                'weight' => 18,
                'label'  => 'کلمه کلیدی در عنوان سئو',
                'pass'   => self::keyword_is_present( $title, $keyword ),
                'detail' => $is_long ? 'تطبیق بر اساس همپوشانی کلمات (کلمه کلیدی چندعبارتی)' : null,
            ],
            [
                'key'    => 'title_length',
                'weight' => 8,
                'label'  => 'طول عنوان مناسب (' . self::TITLE_MIN . ' تا ' . self::TITLE_MAX . ' کاراکتر)',
                'pass'   => $title_len >= self::TITLE_MIN && $title_len <= self::TITLE_MAX,
                'detail' => $title_len . ' کاراکتر',
            ],
            [
                'key'    => 'keyword_first_paragraph',
                'weight' => 18,
                'label'  => 'کلمه کلیدی در پاراگراف اول',
                'pass'   => self::keyword_in_first_paragraph( $content, $keyword ),
            ],
            [
                'key'    => 'keyword_density',
                'weight' => 9,
                'label'  => 'تراکم کلمه کلیدی طبیعی (' . self::DENSITY_MIN . '٪ تا ' . self::DENSITY_MAX . '٪)',
                'pass'   => $density >= self::DENSITY_MIN && $density <= self::DENSITY_MAX,
                'detail' => $density . '٪' . ( $is_long ? ' (میانگین کلمات عبارت)' : '' ),
            ],
            [
                'key'    => 'keyword_in_h2',
                'weight' => 9,
                'label'  => 'کلمه کلیدی حداقل در یک سرتیتر H2',
                'pass'   => $h2_kw > 0,
                'detail' => $h2_kw . ' از ' . $h2_total . ' سرتیتر',
            ],
            [
                'key'    => 'meta_length',
                'weight' => 8,
                'label'  => 'طول متا دیسکریپشن مناسب (' . self::META_MIN . ' تا ' . self::META_MAX . ' کاراکتر)',
                'pass'   => $meta_len >= self::META_MIN && $meta_len <= self::META_MAX,
                'detail' => $meta_len . ' کاراکتر',
            ],
            [
                'key'    => 'keyword_in_meta',
                'weight' => 13,
                'label'  => 'کلمه کلیدی در متا دیسکریپشن',
                'pass'   => self::keyword_is_present( $meta_description, $keyword ),
            ],
            [
                'key'    => 'min_word_count',
                'weight' => 5,
                'label'  => 'حداقل ' . $min_wc . ' کلمه محتوا',
                'pass'   => $wc >= $min_wc,
                'detail' => $wc . ' کلمه',
            ],
            [
                'key'    => 'min_h2_count',
                'weight' => 4,
                'label'  => 'حداقل ' . self::MIN_H2_COUNT . ' سرتیتر H2',
                'pass'   => $h2_total >= self::MIN_H2_COUNT,
                'detail' => $h2_total . ' سرتیتر',
            ],
            [
                'key'    => 'no_h1_in_content',
                'weight' => 2,
                'label'  => 'تگ H1 داخل محتوا نیست (عنوان پست = H1)',
                'pass'   => ! $has_h1_in_content,
                'detail' => $has_h1_in_content ? 'H1 اضافه در محتوا یافت شد' : 'بدون H1 اضافه',
            ],
            [
                'key'    => 'featured_image_alt',
                'weight' => 4,
                'label'  => 'تصویر شاخص دارای alt text است',
                'pass'   => $img_check['pass'],   // می‌تواند null باشد (چک نشده)
                'detail' => $img_check['detail'],
            ],
            [
                'key'    => 'has_external_links',
                'weight' => 2,
                'label'  => 'حداقل ۱ لینک خارجی به منبع معتبر',
                'pass'   => $ext_link_count >= 1,
                'detail' => $ext_link_count . ' لینک خارجی',
            ],
            [
                'key'    => 'readability',
                'weight' => 6,
                'label'  => 'خوانایی متن (' . $readability['label'] . ')',
                // فقط «متوسط» و «ضعیف» را fail حساب کن — «خوب» با کمی جای بهبود
                // نباید امتیاز سئوی کلی را پایین بکشد، فقط در جزئیات نمایش داده می‌شود
                'pass'   => $readability['score'] >= 60,
                'detail' => implode( ' | ', $readability['feedback'] ),
            ],
        ];

        if ( $slug_check !== null ) {
            $checks[] = [
                'key'    => 'slug_latin',
                'weight' => 2,
                'label'  => 'slug لاتین (بدون حروف فارسی)',
                'pass'   => $slug_check['pass'],
                'detail' => $slug_check['detail'],
            ];
        }

        // امتیازدهی وزن‌دار — pass=null یعنی «چک نشد» و وزنش از total کم می‌شود
        $total_weight  = 0;
        $passed_weight = 0;
        foreach ( $checks as $check ) {
            $w             = $check['weight'] ?? 1;
            $total_weight += $w;
            if ( $check['pass'] === true ) {
                $passed_weight += $w;
            } elseif ( $check['pass'] === null ) {
                $total_weight -= $w; // چک بی‌طرف — از مخرج هم کم می‌شود
            }
            // pass=false: total_weight کم نمی‌شود (امتیاز منفی می‌دهد)
        }
        $score = $total_weight > 0
            ? (int) round( ( $passed_weight / $total_weight ) * 100 )
            : 0;

        return [
            'score'       => $score,
            'checks'      => $checks,
            'density'     => $density,
            'word_count'  => $wc,
            'h2_count'    => $h2_total,
            'readability' => $readability,
        ];
    }

    /**
     * تحلیل خوانایی فارسی — بدون سرویس بیرونی.
     *
     * چون فرمول استاندارد بین‌المللی (Flesch و مشتقاتش) بر اساس هجای انگلیسی
     * طراحی شده و برای فارسی معنا ندارد، این‌جا یک معیار heuristic مخصوص
     * فارسی می‌سازیم بر پایه‌ی سه چیزی که واقعاً روی خوانایی وب‌فارسی اثر دارد:
     *   ۱) میانگین طول جمله (تعداد کلمه) — جمله‌های خیلی طولانی خسته‌کننده‌اند
     *   ۲) نسبت «کلمات پیچیده» (طولانی‌تر از ۶ کاراکتر) — نه دقیق مثل هجاشماری
     *      انگلیسی، ولی در فارسی کلمات مرکب/عربی‌تبار معمولاً طولانی‌ترند
     *   ۳) نسبت جمله‌های مجهول (شد/می‌شود/شده است/گردید و…) — نوشتار معلوم و
     *      مستقیم برای محتوای وب خوانا‌تر است
     *
     * @return array{score:int,label:string,avg_sentence_length:float,long_word_ratio:float,
     *               passive_ratio:float,sentence_count:int,word_count:int,feedback:string[]}
     */
    public static function readability_analysis( string $content ): array {
        $text = wp_strip_all_tags( $content );
        $text = trim( $text );

        if ( $text === '' ) {
            return [
                'score' => 0, 'label' => '—',
                'avg_sentence_length' => 0.0, 'long_word_ratio' => 0.0, 'passive_ratio' => 0.0,
                'sentence_count' => 0, 'word_count' => 0, 'feedback' => [ 'محتوایی برای تحلیل وجود ندارد.' ],
            ];
        }

        // جمله‌بندی: نقطه، علامت تعجب، علامت سوال فارسی/لاتین — پشت‌سرهم چندتا هم یکی حساب می‌شود
        $raw_sentences = preg_split( '/[.!؟?]+/u', $text );
        $sentences = array_values( array_filter( array_map( 'trim', $raw_sentences ), static function ( $s ) {
            return $s !== '';
        } ) );
        $sentence_count = count( $sentences );

        // کلمه‌بندی روی فاصله/نیم‌فاصله؛ نیم‌فاصله (ZWNJ) بخشی از کلمه در نظر گرفته می‌شود، نه جداکننده
        $words = preg_split( '/\s+/u', $text );
        $words = array_values( array_filter( array_map( 'trim', $words ), static function ( $w ) {
            return $w !== '' && preg_match( '/[\p{L}\p{N}]/u', $w ); // فقط چیزی که واقعاً حرف/عدد دارد، نه فقط علامت
        } ) );
        $word_count = count( $words );

        if ( $sentence_count === 0 || $word_count === 0 ) {
            return [
                'score' => 0, 'label' => '—',
                'avg_sentence_length' => 0.0, 'long_word_ratio' => 0.0, 'passive_ratio' => 0.0,
                'sentence_count' => $sentence_count, 'word_count' => $word_count,
                'feedback' => [ 'متن برای تحلیل خوانایی خیلی کوتاه است.' ],
            ];
        }

        $avg_sentence_length = round( $word_count / $sentence_count, 1 );

        $long_words = array_filter( $words, static function ( $w ) {
            return mb_strlen( $w ) > 6;
        } );
        $long_word_ratio = round( count( $long_words ) / $word_count * 100, 1 );

        // نشانه‌های رایج فعل مجهول در فارسی
        $passive_pattern = '/(شده\s*است|شده‌اند|می‌شود|می‌گردد|خواهد\s*شد|گردید|شد\b)/u';
        $passive_sentence_count = 0;
        foreach ( $sentences as $s ) {
            if ( preg_match( $passive_pattern, $s ) ) {
                $passive_sentence_count++;
            }
        }
        $passive_ratio = round( $passive_sentence_count / $sentence_count * 100, 1 );

        // زیرامتیازها — هرکدام روی ۱۰۰، بعد میانگین
        $sub_sentence = max( 0, 100 - max( 0, $avg_sentence_length - 20 ) * 4 );
        $sub_longword = max( 0, 100 - max( 0, $long_word_ratio - 35 ) * 2 );
        $sub_passive  = max( 0, 100 - max( 0, $passive_ratio - 20 ) * 2.5 );
        $score = (int) round( ( $sub_sentence + $sub_longword + $sub_passive ) / 3 );
        $score = max( 0, min( 100, $score ) );

        if ( $score >= 80 ) {
            $label = 'عالی';
        } elseif ( $score >= 60 ) {
            $label = 'خوب';
        } elseif ( $score >= 40 ) {
            $label = 'متوسط';
        } else {
            $label = 'ضعیف';
        }

        $feedback = [];
        if ( $avg_sentence_length > 20 ) {
            $feedback[] = "میانگین طول جمله {$avg_sentence_length} کلمه است — جمله‌ها را کوتاه‌تر کنید (ایده‌آل: زیر ۲۰ کلمه).";
        }
        if ( $long_word_ratio > 35 ) {
            $feedback[] = "{$long_word_ratio}٪ کلمات طولانی/پیچیده‌اند — در صورت امکان از معادل ساده‌تر استفاده کنید.";
        }
        if ( $passive_ratio > 20 ) {
            $feedback[] = "{$passive_ratio}٪ جمله‌ها مجهول‌اند — نوشتار معلوم (فاعل مشخص) معمولاً خواناتر و مستقیم‌تر است.";
        }
        if ( empty( $feedback ) ) {
            $feedback[] = 'خوانایی متن در وضعیت خوبی است.';
        }

        return [
            'score'               => $score,
            'label'               => $label,
            'avg_sentence_length' => $avg_sentence_length,
            'long_word_ratio'     => $long_word_ratio,
            'passive_ratio'       => $passive_ratio,
            'sentence_count'      => $sentence_count,
            'word_count'          => $word_count,
            'feedback'            => $feedback,
        ];
    }

    public static function content_has_h1( string $content ): bool {
        return (bool) preg_match( '/<h1[\s>]/i', $content );
    }

    public static function check_slug( int $post_id ): array {
        $post = get_post( $post_id );
        if ( ! $post ) return [ 'pass' => false, 'detail' => 'پست یافت نشد', 'slug' => '' ];

        $slug = $post->post_name;
        $has_persian = (bool) preg_match( '/[\x{0600}-\x{06FF}]/u', $slug );
        $is_empty    = empty( $slug );

        return [
            'pass'   => ! $has_persian && ! $is_empty,
            'slug'   => $slug,
            'detail' => $is_empty
                ? 'slug خالی است'
                : ( $has_persian ? 'slug حاوی حروف فارسی است — بهتر است انگلیسی باشد' : 'slug مناسب است' ),
        ];
    }

    public static function suggest_internal_links( string $keyword, string $content, int $exclude_id = 0, int $limit = 5 ): array {
        $cache_key = 'aco_published_ids';
        $all_ids   = get_transient( $cache_key );
        if ( $all_ids === false ) {
            // FIX: محدودیت به ۲۰۰۰ پست برای جلوگیری از memory overflow در سایت‌های بزرگ
            $all_ids = get_posts( [
                'post_type'      => 'post',
                'post_status'    => 'publish',
                'posts_per_page' => 2000,
                'fields'         => 'ids',
                'orderby'        => 'date',
                'order'          => 'DESC',
                'no_found_rows'  => true, // بهینه‌سازی: COUNT(*) اجرا نمی‌شود
            ] );
            set_transient( $cache_key, $all_ids, 15 * MINUTE_IN_SECONDS );
        }

        $posts = $exclude_id
            ? array_values( array_diff( $all_ids, [ $exclude_id ] ) )
            : $all_ids;

        if ( empty( $posts ) ) return [];

        $kw_words = array_filter(
            preg_split( '/\s+/u', self::normalize_persian( mb_strtolower( $keyword ) ), -1, PREG_SPLIT_NO_EMPTY ),
            function( $w ) { return mb_strlen( $w ) >= 2; }
        );

        $suggestions = [];
        foreach ( $posts as $pid ) {
            $post_obj  = get_post( $pid );
            // FIX BUG-S5: پست ممکن است در بین اجرا حذف شده باشد — null check ضروری است
            if ( ! $post_obj ) continue;
            $post_kw   = get_post_meta( $pid, '_yoast_wpseo_focuskw', true )
                      ?: get_post_meta( $pid, 'rank_math_focus_keyword', true )
                      ?: $post_obj->post_title;

            $post_kw_n = self::normalize_persian( mb_strtolower( $post_kw ) );
            $title_n   = self::normalize_persian( mb_strtolower( $post_obj->post_title ) );

            $score = 0;
            foreach ( $kw_words as $w ) {
                if ( strpos( $post_kw_n, $w ) !== false ) $score += 2;
                if ( strpos( $title_n, $w ) !== false )   $score += 1;
            }

            $url = get_permalink( $pid );
            if ( $score > 0 && strpos( $content, $url ) === false ) {
                $suggestions[] = [
                    'post_id'   => $pid,
                    'title'     => $post_obj->post_title,
                    'url'       => $url,
                    'focus_kw'  => $post_kw,
                    'score'     => $score,
                ];
            }
        }

        usort( $suggestions, function( $a, $b ) { return $b['score'] - $a['score']; } );
        return array_slice( $suggestions, 0, $limit );
    }

    /**
     * تولید یکپارچه‌ی گراف schema.org برای یک پست.
     *
     * این نسخه (v2.0.0) نتیجه‌ی ادغام با Peyvand Amn SEO Assistant Pro است:
     * برخلاف نسخه‌ی قبلی ACO که فقط Article + یک الگوی محدود FAQ را، آن‌هم
     * فقط برای پست‌های تولیدشده توسط AI (_aco_generated) می‌ساخت، این نسخه
     * برای هر پست/برگه‌ی singular سایت اجرا می‌شود و شامل گره‌های زیر است:
     *   ۱. WebPage یا MedicalWebPage (بسته به تنظیم niche) — فقط بدون Yoast/RankMath
     *   ۲. BreadcrumbList — اگر پست دسته‌بندی داشته باشد
     *   ۳. Article — فقط بدون Yoast/RankMath
     *   ۴. FAQPage — با پارسر قوی‌تر DOMDocument (پورت‌شده از Peyvand Amn)
     *   ۵. VideoObject — برای هر تگ <video> در محتوا (پورت‌شده از Peyvand Amn)
     * همه در یک @graph واحد برگردانده می‌شوند تا خروجی نهایی یک بلاک
     * <script type="application/ld+json"> باشد، نه چند بلاک پراکنده.
     */
    public static function generate_schema( int $post_id, string $content, string $keyword ): array {
        $post = get_post( $post_id );
        // FIX BUG-S6: اگر پست حذف شده یا یافت نشد، آرایه خالی برگردان تا fatal error نشود
        if ( ! $post ) return [];

        $yoast_active    = defined( 'WPSEO_VERSION' );
        $rankmath_active = defined( 'RANK_MATH_VERSION' );
        $has_seo_plugin  = $yoast_active || $rankmath_active;

        $author    = get_the_author_meta( 'display_name', $post->post_author );
        $pub_date  = get_the_date( 'c', $post_id );
        $mod_date  = get_the_modified_date( 'c', $post_id );
        $url       = get_permalink( $post_id );
        $site_name = get_bloginfo( 'name' );
        $desc      = get_post_meta( $post_id, '_yoast_wpseo_metadesc', true )
                  ?: get_post_meta( $post_id, 'rank_math_description', true )
                  ?: mb_substr( wp_strip_all_tags( $content ), 0, 160 );

        $niche     = (string) ( class_exists( 'ACO_Settings' ) ? ACO_Settings::get( 'niche' ) : '' );
        $publisher = [
            '@type' => 'Organization',
            'name'  => $site_name,
            'logo'  => [
                '@type' => 'ImageObject',
                'url'   => get_site_icon_url( 512 ) ?: '',
            ],
        ];

        $graph = [];

        // ۱. گره سطح صفحه — Yoast/RankMath خودشان WebPage schema تولید می‌کنند، پس تکرار نمی‌کنیم
        if ( ! $has_seo_plugin ) {
            $graph[] = [
                '@type'        => self::niche_looks_like_psychology( $niche ) ? 'MedicalWebPage' : 'WebPage',
                '@id'          => $url . '#webpage',
                'url'          => $url,
                'name'         => wp_strip_all_tags( $post->post_title ),
                'description'  => $desc,
                'lastReviewed' => $mod_date,
                'publisher'    => $publisher,
            ];
        }

        // ۲. مسیر ناوبری — فقط اگر پست دسته‌بندی داشته باشد (برگه‌ها معمولاً دسته ندارند)
        $categories = wp_get_post_categories( $post_id );
        if ( ! empty( $categories ) ) {
            $cat_id   = $categories[0];
            $cat_name = get_cat_name( $cat_id );
            $cat_url  = get_category_link( $cat_id );
            $graph[]  = [
                '@type'           => 'BreadcrumbList',
                '@id'             => $url . '#breadcrumb',
                'itemListElement' => [
                    [ '@type' => 'ListItem', 'position' => 1, 'name' => 'خانه', 'item' => home_url( '/' ) ],
                    [ '@type' => 'ListItem', 'position' => 2, 'name' => $cat_name, 'item' => $cat_url ],
                    [ '@type' => 'ListItem', 'position' => 3, 'name' => wp_strip_all_tags( $post->post_title ), 'item' => $url ],
                ],
            ];
        }

        // ۳. Article — فقط بدون Yoast/RankMath (آن‌ها خودشان Article/NewsArticle می‌سازند)
        if ( ! $has_seo_plugin ) {
            $graph[] = [
                '@type'         => 'Article',
                'headline'      => $post->post_title,
                'description'   => $desc,
                'keywords'      => $keyword,
                'url'           => $url,
                'datePublished' => $pub_date,
                'dateModified'  => $mod_date,
                'author'        => [ '@type' => 'Person', 'name' => $author ],
                'publisher'     => $publisher,
                'inLanguage'    => 'fa',
            ];
        }

        // ۴. FAQPage
        $faq_items = self::parse_faq_items( $content );
        if ( ! empty( $faq_items ) ) {
            $graph[] = [
                '@type'      => 'FAQPage',
                '@id'        => $url . '#faq',
                'mainEntity' => $faq_items,
            ];
        }

        // ۵. VideoObject — یک گره برای هر ویدیوی یافت‌شده در محتوا
        $videos = self::extract_videos( $content );
        foreach ( $videos as $index => $vid ) {
            $video_title = wp_strip_all_tags( $post->post_title );
            if ( count( $videos ) > 1 ) {
                $video_title .= ' - بخش ' . ( $index + 1 );
            }
            $video_node  = [
                '@type'        => 'VideoObject',
                'name'         => $video_title,
                'description'  => $desc,
                'thumbnailUrl' => $vid['poster'] ?: ( get_the_post_thumbnail_url( $post_id, 'full' ) ?: '' ),
                'uploadDate'   => get_the_date( 'c', $post_id ),
            ];
            if ( ! empty( $vid['duration'] ) ) {
                $video_node['duration'] = $vid['duration'];
            }
            $is_embed = (bool) preg_match( '/embed|player|aparat\.com|youtube\.com/i', $vid['url'] );
            $video_node[ $is_embed ? 'embedUrl' : 'contentUrl' ] = $vid['url'];
            $graph[] = $video_node;
        }

        return $graph;
    }

    /**
     * استخراج پایدار پرسش‌وپاسخ از محتوا با DOMDocument (پورت‌شده و تعمیم‌یافته
     * از Peyvand_Amn_SEO_Assistant_v5::parse_faq_from_content). برخلاف نسخه‌ی
     * قبلی ACO که فقط داخل یک <h2> حاوی «سوال» جستجو می‌کرد، این نسخه هر
     * h2/h3/h4 که به «؟» یا «?» ختم شود را به‌عنوان سؤال در نظر می‌گیرد —
     * یعنی برای محتوای دستی‌نوشته (بدون الگوی خاص ACO) هم کار می‌کند.
     */
    private static function parse_faq_items( string $content ): array {
        $faq = [];
        if ( trim( $content ) === '' ) return $faq;

        $clean_content = preg_replace( '/<!--(.|\s)*?-->/', '', $content );

        if ( ! class_exists( 'DOMDocument' ) ) {
            if ( preg_match_all( '/<h[234]\b[^>]*>(.*?[؟\?])<\/h[234]>\s*(?:<div[^>]*>)?\s*<p>(.*?)<\/p>/ius', $clean_content, $matches ) ) {
                for ( $i = 0; $i < count( $matches[1] ); $i++ ) {
                    $question = wp_strip_all_tags( $matches[1][ $i ] );
                    $answer   = wp_strip_all_tags( $matches[2][ $i ] );
                    if ( $question && $answer ) {
                        $faq[] = [
                            '@type'          => 'Question',
                            'name'           => $question,
                            'acceptedAnswer' => [ '@type' => 'Answer', 'text' => $answer ],
                        ];
                    }
                }
            }
            return $faq;
        }

        $dom  = new DOMDocument();
        $prev = libxml_use_internal_errors( true );
        // همان دلیل fix در class-aco-gutenberg.php: رشته پیوسته‌ی «<?xml» در سورس
        // PHP روی هاست‌های short_open_tag=On باعث parse error می‌شود.
        $dom->loadHTML( '<' . '?xml encoding="UTF-8">' . $clean_content, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );
        libxml_clear_errors();
        libxml_use_internal_errors( $prev );

        $xpath   = new DOMXPath( $dom );
        $headers = $xpath->query( '//h2 | //h3 | //h4' );

        foreach ( $headers as $header ) {
            $question_text = trim( $header->textContent );
            if ( ! preg_match( '/[؟\?]$/u', $question_text ) ) continue;

            $next_node   = $header->nextSibling;
            $answer_text = '';

            while ( $next_node ) {
                if ( $next_node->nodeType === XML_ELEMENT_NODE ) {
                    $node_name = strtolower( $next_node->nodeName );
                    if ( $node_name === 'p' ) {
                        $answer_text = trim( $next_node->textContent );
                        break;
                    } elseif ( $node_name === 'div' ) {
                        $p_childs = $next_node->getElementsByTagName( 'p' );
                        if ( $p_childs->length > 0 ) {
                            $answer_text = trim( $p_childs->item( 0 )->textContent );
                            break;
                        }
                    } elseif ( in_array( $node_name, [ 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'section', 'article' ], true ) ) {
                        break;
                    }
                }
                $next_node = $next_node->nextSibling;
            }

            if ( $question_text && $answer_text ) {
                $faq[] = [
                    '@type'          => 'Question',
                    'name'           => wp_strip_all_tags( $question_text ),
                    'acceptedAnswer' => [ '@type' => 'Answer', 'text' => wp_strip_all_tags( $answer_text ) ],
                ];
            }
        }

        return $faq;
    }

    /**
     * استخراج ویدیوها از محتوا (پورت‌شده از Peyvand_Amn_SEO_Assistant_v5::compute_seo_data).
     * FIX پیوند امن اصلی حفظ شده: duration فقط وقتی data-duration واقعی موجود
     * باشد ست می‌شود — هیچ مقدار ثابت/جعلی (مثل PT5M) استفاده نمی‌شود، چون
     * duration نادرست ریسک Manual Action در Google Search Console دارد.
     */
    private static function extract_videos( string $content ): array {
        $videos = [];
        if ( ! preg_match_all( '/<video\b[^>]*>/i', $content, $tags ) ) return $videos;

        foreach ( $tags[0] as $tag ) {
            $url = ''; $poster = ''; $duration = '';
            if ( preg_match( '/\bsrc=["\']([^"\']+)["\']/i', $tag, $m ) )            $url      = $m[1];
            if ( preg_match( '/\bposter=["\']([^"\']+)["\']/i', $tag, $m ) )         $poster   = $m[1];
            if ( preg_match( '/\bdata-duration=["\']([^"\']+)["\']/i', $tag, $m ) )  $duration = $m[1];

            if ( $url !== '' ) {
                $videos[] = [
                    'url'      => esc_url_raw( $url ),
                    'poster'   => esc_url_raw( $poster ),
                    'duration' => $duration !== '' ? sanitize_text_field( $duration ) : '',
                ];
            }
        }
        return $videos;
    }

    /**
     * آیا حوزه فعالیت سایت (تنظیمات → niche) روان‌شناسی/سلامت‌روان/مشاوره است؟
     * تابع مشترک — هم در متاباکس تحلیل بلادرنگ و هم در AJAX handler استفاده می‌شود
     * تا تشخیص «حوزه روان‌شناسی» فقط یک‌جا تعریف شده باشد.
     */
    public static function niche_looks_like_psychology( string $niche ): bool {
        if ( trim( $niche ) === '' ) return false;
        $n = mb_strtolower( $niche );
        foreach ( [ 'روان', 'psych', 'مشاوره', 'clinic', 'کلینیک', 'ذهن' ] as $needle ) {
            if ( mb_strpos( $n, mb_strtolower( $needle ) ) !== false ) return true;
        }
        return false;
    }

    /**
     * خوشه‌بندی واژه‌شناختی کلمات هم‌خانواده برای حوزه روان‌شناسی بالینی.
     * پورت‌شده از Peyvand Amn SEO Assistant Pro (get_psychology_semantic_keywords)
     * در زمان ادغام دو پلاگین — این تابع صرفاً به‌عنوان پیشنهاد UI استفاده می‌شود
     * و در امتیازدهی run_audit() هیچ نقشی ندارد.
     */
    public static function psychology_keyword_clusters( string $keyword ): array {
        $keyword = trim( mb_strtolower( $keyword ) );
        if ( $keyword === '' ) return [];

        $clusters = [
            'خیانت'   => [ 'درمان خیانت همسر', 'اعتماد بعد از خیانت', 'نشانه های خیانت عاطفی', 'بخشش بعد از خیانت', 'مشاوره زوج درمانی خیانت', 'روانشناسی خیانت' ],
            'افسردگی' => [ 'درمان افسردگی شدید بدون دارو', 'علائم افسردگی در زنان', 'افسردگی عاطفی چیست', 'مشاوره افسردگی زوجین', 'تست افسردگی علمی', 'افسردگی بالینی' ],
            'اضطراب'  => [ 'راه های کنترل اضطراب عاطفی', 'درمان اضطراب شدید همسر', 'کاهش استرس در رابطه', 'مشاوره اضطراب بالینی', 'علائم فیزیکی اضطراب' ],
            'ازدواج'  => [ 'مشاوره پیش از ازدواج علمی', 'معیارهای ازدواج موفق', 'روانشناسی اختلاف سن در ازدواج', 'حل بحران های پس از ازدواج', 'طلاق عاطفی' ],
            'اعتماد'  => [ 'چگونه دوباره اعتماد کنیم', 'جلب اعتماد همسر بعد از دروغ', 'تست روانشناسی میزان اعتماد', 'اعتماد به نفس در رابطه زناشویی', 'افزایش اعتماد به نفس' ],
        ];

        $clean_keyword = preg_replace( '/(ها|های|ی|ام|ات|تر|ترین)$/u', '', $keyword );
        $clean_keyword = trim( $clean_keyword );

        foreach ( $clusters as $key => $phrases ) {
            if ( mb_strpos( $clean_keyword, $key ) !== false || mb_strpos( $key, $clean_keyword ) !== false ) {
                return $phrases;
            }
        }

        return [
            'راهکارهای روانشناسی برای ' . $keyword,
            'علائم و نشانه‌های اختلال ' . $keyword,
            'درمان شناختی رفتاری (CBT) ' . $keyword,
            'مشاوره تخصصی و فردی ' . $keyword,
        ];
    }

    /**
     * ثبت هوک‌های خروجی و کش schema.
     *
     * تفاوت کلیدی با نسخه‌ی قبلی: خروجی دیگر به get_post_meta('_aco_generated')
     * محدود نیست — روی هر پست/برگه‌ی singular سایت اجرا می‌شود (نه فقط
     * محتوای تولیدشده توسط AI)، دقیقاً مثل رفتار قبلی Peyvand Amn.
     *
     * کش: نتیجه در transient نگه داشته می‌شود (سنگین‌ترین بخش DOMDocument
     * برای FAQ است) و با هوک save_post/delete_post — بدون هیچ وابستگی به
     * نانس فرم ادمین — invalidate می‌شود. این دقیقاً همان باگی را که در
     * Peyvand Amn داشتیم رفع می‌کند: آنجا invalidation فقط داخل تابعی بود که
     * با چک $_POST['nonce'] زودتر return می‌کرد، پس آپدیت‌های خودکار (مثل
     * ACO_Crawler یا ACO_Queue که با wp_update_post برنامه‌نویسی‌شده کار
     * می‌کنند) کش را باطل نمی‌کردند. اینجا invalidation یک هوک کاملاً جدا و
     * بدون‌قید است — هم برای ذخیره‌ی دستی از ادیتور و هم برای هر بازنویسی
     * خودکار به‌یک اندازه کار می‌کند.
     */
    public static function inject_schema_hooks(): void {
        add_action( 'wp_head', [ __CLASS__, 'output_schema' ], 20 );
        add_action( 'save_post', [ __CLASS__, 'invalidate_schema_cache' ], 10, 1 );
        add_action( 'delete_post', [ __CLASS__, 'invalidate_schema_cache' ], 10, 1 );
    }

    public static function invalidate_schema_cache( int $post_id ): void {
        if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) return;
        delete_transient( 'aco_schema_' . $post_id );
    }

    public static function output_schema(): void {
        if ( ! is_singular() ) return;

        $post = get_queried_object();
        if ( ! ( $post instanceof WP_Post ) ) return;

        $post_id = $post->ID;
        $graph   = get_transient( 'aco_schema_' . $post_id );

        if ( false === $graph ) {
            $keyword = get_post_meta( $post_id, '_yoast_wpseo_focuskw', true )
                    ?: get_post_meta( $post_id, 'rank_math_focus_keyword', true )
                    ?: '';
            $content = get_post_field( 'post_content', $post_id );
            $graph   = self::generate_schema( $post_id, $content, $keyword );
            set_transient( 'aco_schema_' . $post_id, $graph, DAY_IN_SECONDS );
        }

        if ( empty( $graph ) ) return;

        $unified = [ '@context' => 'https://schema.org', '@graph' => $graph ];

        echo "\n" . '<!-- ACO Unified Schema -->' . "\n";
        echo '<script type="application/ld+json">' . wp_json_encode(
            $unified,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
        ) . '</script>' . "\n";
    }
}
