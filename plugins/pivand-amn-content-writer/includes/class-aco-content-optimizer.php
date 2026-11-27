<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * ACO_Content_Optimizer
 *
 * بهینه‌سازی خودکار HTML خروجی محتوا: افزودن alt به تصاویر بدون alt، و
 * preload="none" روی ویدیوها (کاهش CLS و بار اولیه صفحه). پورت‌شده از
 * Peyvand_Amn_SEO_Assistant_v5::optimize_content_markup در جریان ادغام دو
 * پلاگین — این قابلیت هیچ معادلی در ACO نداشت.
 *
 * تفاوت مهم با نسخه‌ی اصلی Peyvand Amn: در آنجا کش (_peyvand_amn_optimized_content)
 * فقط داخل save_seo_meta_data() که با چک $_POST['nonce'] شروع می‌شد invalidate
 * می‌شد؛ یعنی هر آپدیت خودکار پست (از طریق ACO_Crawler یا ACO_Queue که با
 * wp_update_post() برنامه‌نویسی‌شده کار می‌کنند) این چک نانس را نداشت و کش را
 * باطل نمی‌کرد — محتوای قدیمی تا ۱ روز (یا بازذخیره‌ی دستی) روی فرانت‌اند
 * می‌ماند. اینجا invalidation یک هوک کاملاً مستقل روی save_post/delete_post
 * است که به هیچ نانس یا فرم ادمینی وابسته نیست.
 */
class ACO_Content_Optimizer {

    const CACHE_META_KEY = '_aco_optimized_content';

    public static function init(): void {
        add_filter( 'the_content', [ __CLASS__, 'get_cached_optimized_content' ], 99 );
        add_action( 'save_post', [ __CLASS__, 'invalidate_cache' ], 10, 1 );
        add_action( 'delete_post', [ __CLASS__, 'invalidate_cache' ], 10, 1 );
        add_action( 'wp_enqueue_scripts', [ __CLASS__, 'enqueue_frontend_assets' ] );
    }

    /**
     * استایل عناصر بصری تولیدشده در متن مقاله (جدول/باکس نکته/باکس هشدار/
     * کارت آماری/جداکننده) — فقط در صفحه‌ی تک‌پست لود می‌شود، نه کل سایت.
     */
    public static function enqueue_frontend_assets(): void {
        if ( ! is_singular( 'post' ) ) return;
        wp_enqueue_style(
            'aco-frontend',
            ACO_PLUGIN_URL . 'assets/frontend.css',
            [],
            ACO_VERSION
        );
    }

    public static function invalidate_cache( int $post_id ): void {
        if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) return;
        delete_post_meta( $post_id, self::CACHE_META_KEY );
    }

    public static function get_cached_optimized_content( string $content ): string {
        if ( is_admin() || ! is_singular() ) {
            return $content;
        }

        $post_id = get_the_ID();
        if ( ! $post_id ) return $content;

        $cached = get_post_meta( $post_id, self::CACHE_META_KEY, true );
        if ( ! empty( $cached ) ) {
            return $cached;
        }

        $post = get_post( $post_id );
        if ( ! $post ) return $content;

        $optimized = self::optimize_markup( $content, $post->post_title );
        update_post_meta( $post_id, self::CACHE_META_KEY, $optimized );
        return $optimized;
    }

    /**
     * بهینه‌سازی HTML به‌صورت XHTML-safe (بدون تداخل با تگ‌های خودبسته).
     */
    public static function optimize_markup( string $content, string $post_title ): string {
        if ( trim( $content ) === '' ) return $content;

        // ۱. alt خودکار برای تصاویر بدون alt یا با alt خالی
        $counter = 1;
        $content = preg_replace_callback( '/<img\b([^>]*?)(\s*\/)?\s*>/i', function( $matches ) use ( $post_title, &$counter ) {
            $attributes = $matches[1];
            $self_close = $matches[2] ?? '';

            $has_alt = preg_match( '/\balt=["\']([^"\']+)["\']/i', $attributes );
            $empty_alt = preg_match( '/\balt=["\']\s*["\']/i', $attributes );

            if ( ! $has_alt || $empty_alt ) {
                $attributes = preg_replace( '/\balt=["\']\s*["\']/i', '', $attributes );
                $suffix = ( $counter > 1 ) ? ' - تصویر ' . $counter : ' - تصویر';
                $attributes = rtrim( $attributes ) . ' alt="' . esc_attr( $post_title . $suffix ) . '"';
                $counter++;
            }
            return '<img' . $attributes . $self_close . '>';
        }, $content );

        // ۲. preload="none" برای ویدیوها (قابل تغییر با فیلتر aco_video_preload_value)
        if ( strpos( $content, '<video' ) !== false ) {
            $preload_value = esc_attr( apply_filters( 'aco_video_preload_value', 'none' ) );
            $content = preg_replace_callback( '/<video\b([^>]*?)(\s*\/)?\s*>/i', function( $matches ) use ( $preload_value ) {
                $attributes = rtrim( $matches[1] );
                $self_close = $matches[2] ?? '';

                if ( preg_match( '/\bpreload=["\']?[^"\'>]*["\']?/i', $attributes ) ) {
                    $attributes = preg_replace( '/\bpreload=["\']?[^"\'>]*["\']?/i', 'preload="' . $preload_value . '"', $attributes );
                } else {
                    $attributes .= ' preload="' . $preload_value . '"';
                }
                return '<video' . $attributes . $self_close . '>';
            }, $content );
        }

        return $content;
    }
}
