<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * ACO_Gutenberg
 *
 * تبدیل HTML ساده‌ی تولیدشده توسط AI (فقط تگ‌های سفیدلیست‌شده‌ی
 * h2/h3/h4/p/ul/ol/li/strong/em/a/br/blockquote) به مارک‌آپ بلاک‌های
 * بومی گوتنبرگ (wp:heading، wp:paragraph، wp:list، wp:quote).
 *
 * چرا لازم است: قبل از این، post_content فقط HTML خام ذخیره می‌شد.
 * وردپرس آن را به‌عنوان «classic content» نمایش می‌دهد — در ویرایشگر
 * بلاکی به‌صورت یک بلاک Classic تک بزرگ ظاهر می‌شود که قابل ویرایش
 * بلاک‌به‌بلاک نیست. این کلاس همان HTML را به بلاک‌های واقعی تبدیل
 * می‌کند تا در ویرایشگر هم تجربه‌ی طبیعی وردپرس حفظ شود.
 *
 * محدودیت شناخته‌شده: آیتم‌های <li> به‌صورت بلاک‌های تو‌در‌توی
 * core/list-item جداگانه تولید نمی‌شوند (فقط <ul>/<ol> کلاسیک داخل
 * wp:list). این با نسخه‌های وردپرس ۶.x/۷.x درست رندر و ویرایش می‌شود؛
 * فقط اگر کاربر بخواهد هر آیتم لیست را جداگانه به‌عنوان بلاک انتخاب
 * کند، باید یک‌بار در ویرایشگر «تبدیل به بلاک‌ها» را بزند.
 */
class ACO_Gutenberg {

    /**
     * @param string $html HTML سفیدلیست‌شده (خروجی wp_kses با همان allowed_html این افزونه)
     * @return string مارک‌آپ آماده‌ی post_content (با کامنت‌های <!-- wp:... -->)
     */
    public static function convert_html_to_blocks( string $html ): string {
        $html = trim( $html );
        if ( $html === '' ) {
            return '';
        }

        if ( ! class_exists( 'DOMDocument' ) ) {
            // اگر ext-dom روی هاست فعال نیست، بدون تبدیل برگردان —
            // محتوا همچنان به‌صورت classic HTML صحیح ذخیره و نمایش داده می‌شود.
            return $html;
        }

        libxml_use_internal_errors( true );
        $dom = new DOMDocument( '1.0', 'UTF-8' );
        // پیشوند xml-encoding ترفند استاندارد جلوگیری از خراب شدن
        // کاراکترهای فارسی (multi-byte) توسط loadHTML است.
        // توجه: رشته‌ی پیشوند xml هرگز نباید به‌صورت پیوسته در فایل PHP نوشته شود —
        // در هاست‌هایی که short_open_tag روشن است، خود توالی مربوطه به‌عنوان
        // یک تگ باز PHP تشخیص داده می‌شود و باعث parse error می‌شود
        // ("unexpected identifier 'encoding'"). با تکه‌تکه کردن رشته با
        // کانکتیناسیون، این توالی هرگز پشت‌سرهم در سورس ظاهر نمی‌شود.
        $loaded = $dom->loadHTML(
            '<' . '?xml encoding="UTF-8"?>' . '<div id="aco-root">' . $html . '</div>',
            LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_NONET
        );
        libxml_clear_errors();

        if ( ! $loaded ) {
            return $html;
        }

        $root = $dom->getElementById( 'aco-root' );
        if ( ! $root ) {
            return $html;
        }

        $blocks = [];
        foreach ( $root->childNodes as $node ) {
            $block = self::node_to_block( $dom, $node );
            if ( $block !== null && trim( $block ) !== '' ) {
                $blocks[] = $block;
            }
        }

        // اگر به هر دلیلی چیزی تولید نشد (مثلاً کل محتوا فقط متن خام بدون تگ بود)،
        // fallback ایمن: همان HTML اصلی را در یک پاراگراف تنها بپیچ.
        if ( empty( $blocks ) ) {
            return "<!-- wp:paragraph -->\n<p>" . $html . "</p>\n<!-- /wp:paragraph -->";
        }

        return implode( "\n\n", $blocks );
    }

    private static function node_to_block( DOMDocument $dom, DOMNode $node ): ?string {
        if ( $node->nodeType === XML_TEXT_NODE ) {
            $text = trim( $node->textContent );
            if ( $text === '' ) {
                return null; // فاصله/خط جدید بین تگ‌ها
            }
            // متن آزاد خارج از تگ (نادر) — به‌عنوان پاراگراف مستقل نگه‌داشته می‌شود
            return "<!-- wp:paragraph -->\n<p>" . esc_html( $text ) . "</p>\n<!-- /wp:paragraph -->";
        }

        if ( $node->nodeType !== XML_ELEMENT_NODE ) {
            return null;
        }

        $tag        = strtolower( $node->nodeName );
        $inner_html = self::inner_html( $dom, $node );

        switch ( $tag ) {
            case 'h2':
            case 'h3':
            case 'h4':
                $level = (int) substr( $tag, 1 );
                $attrs = $node->getAttribute( 'id' ) !== ''
                    ? [ 'level' => $level, 'anchor' => $node->getAttribute( 'id' ) ]
                    : [ 'level' => $level ];
                $id_attr = $node->getAttribute( 'id' ) !== '' ? ' id="' . esc_attr( $node->getAttribute( 'id' ) ) . '"' : '';
                return sprintf(
                    "<!-- wp:heading %s -->\n<h%d%s>%s</h%d>\n<!-- /wp:heading -->",
                    wp_json_encode( $attrs ),
                    $level, $id_attr, $inner_html, $level
                );

            case 'p':
                return "<!-- wp:paragraph -->\n<p>{$inner_html}</p>\n<!-- /wp:paragraph -->";

            case 'ul':
                return "<!-- wp:list -->\n<ul class=\"wp-block-list\">{$inner_html}</ul>\n<!-- /wp:list -->";

            case 'ol':
                return "<!-- wp:list {\"ordered\":true} -->\n<ol class=\"wp-block-list\">{$inner_html}</ol>\n<!-- /wp:list -->";

            case 'blockquote':
                $inner = trim( $inner_html );
                if ( stripos( $inner, '<p' ) !== 0 ) {
                    $inner = '<p>' . $inner . '</p>';
                }
                return "<!-- wp:quote -->\n<blockquote class=\"wp-block-quote\">{$inner}</blockquote>\n<!-- /wp:quote -->";

            default:
                // تگ خارج از سفیدلیست معمول (نباید پیش بیاید چون wp_kses از قبل فیلتر کرده) —
                // بدون بلاک‌بندی، همان‌طور که هست نگه داشته می‌شود (fallback ایمن).
                return $dom->saveHTML( $node );
        }
    }

    private static function inner_html( DOMDocument $dom, DOMNode $node ): string {
        $html = '';
        foreach ( $node->childNodes as $child ) {
            $html .= $dom->saveHTML( $child );
        }
        return trim( $html );
    }
}
