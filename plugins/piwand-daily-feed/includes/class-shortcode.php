<?php
defined( 'ABSPATH' ) || exit;

class PWDF_Shortcode {

    public static function init() {
        add_shortcode( 'piwand_feed', [ __CLASS__, 'render' ] );
        add_action( 'wp_enqueue_scripts', [ __CLASS__, 'enqueue' ] );

        // کارت‌ها (نوع پست غیرعمومی) و تنظیمات فید مستقیم داخل HTML شورت‌کد
        // چاپ می‌شوند. چون نوع پست public نیست، افزونه‌های کش با انتشار/ویرایش
        // کارت صفحه‌ی حاوی شورت‌کد (مثلاً صفحه‌ی اصلی) را پاک نمی‌کنند؛ پس خودمان
        // پاک می‌کنیم. (بدون وابستگی به سایر افزونه‌های اکوسیستم.)
        add_action( 'transition_post_status', [ __CLASS__, 'maybe_purge_on_transition' ], 10, 3 );
        add_action( 'deleted_post', [ __CLASS__, 'maybe_purge_on_delete' ], 10, 2 );
        add_action( 'update_option_' . PWDF_Settings::OPT, [ __CLASS__, 'purge_pages' ] );
        add_action( 'add_option_' . PWDF_Settings::OPT, [ __CLASS__, 'purge_pages' ] );
    }

    /**
     * فقط وقتی کارت وارد/خارج وضعیت «منتشرشده» می‌شود یا کارت منتشرشده ویرایش
     * می‌شود. تولید پیش‌نویس روزانه (draft → draft) کش را پاک نمی‌کند.
     */
    public static function maybe_purge_on_transition( $new_status, $old_status, $post ) {
        if ( ! $post instanceof WP_Post || $post->post_type !== 'pwdf_card' ) return;
        if ( $new_status === 'publish' || $old_status === 'publish' ) {
            self::purge_pages();
        }
    }

    public static function maybe_purge_on_delete( $post_id, $post = null ) {
        $type = $post instanceof WP_Post ? $post->post_type : get_post_type( $post_id );
        if ( $type === 'pwdf_card' ) {
            self::purge_pages();
        }
    }

    /**
     * پاک‌کردن کش صفحات (LiteSpeed، WP Rocket، W3TC، WP Super Cache، WP Fastest
     * Cache). افزونه‌ی نصب‌نشده نادیده گرفته می‌شود؛ کش CDN بیرونی (مثل
     * Cloudflare) از اینجا قابل پاک‌کردن نیست. در یک درخواست فقط یک‌بار اجرا می‌شود.
     */
    public static function purge_pages() {
        static $done = false;
        if ( $done ) return;
        $done = true;

        do_action( 'litespeed_purge_all' );
        if ( function_exists( 'rocket_clean_domain' ) ) {
            rocket_clean_domain();
        }
        if ( function_exists( 'w3tc_flush_posts' ) ) {
            w3tc_flush_posts();
        } elseif ( function_exists( 'w3tc_pgcache_flush' ) ) {
            w3tc_pgcache_flush();
        }
        if ( function_exists( 'wp_cache_clear_cache' ) ) {
            wp_cache_clear_cache();
        }
        if ( class_exists( 'WpFastestCache' ) ) {
            do_action( 'wpfc_clear_all_cache' );
        }
    }

    public static function enqueue() {
        global $post;
        $load = is_active_widget( false, false, 'pwdf_feed_widget', true );
        if ( ! $load && $post instanceof WP_Post ) {
            $load = has_shortcode( $post->post_content, 'piwand_feed' );
        }
        // fallback برای page builder ها (Elementor, Divi, ACF)
        // add_filter( 'pwdf_force_enqueue', '__return_true' );
        if ( ! $load ) {
            $load = apply_filters( 'pwdf_force_enqueue', false );
        }
        if ( ! $load ) return;

        // فاز ۱۲ / گام ۶: بدون هیچ درخواست به Google Fonts.
        if ( class_exists( 'PUI' ) ) {
            $font_handle = PUI::handle( 'fonts' );
        } elseif ( class_exists( 'Pas_Core_Fonts' ) ) {
            $font_handle = Pas_Core_Fonts::handle();
        } else {
            $font_handle = '';
        }

        wp_enqueue_style(
            'pwdf-feed',
            PWDF_URL . 'assets/css/feed-display.css',
            array_merge( $font_handle !== '' ? [ $font_handle ] : [], class_exists( 'Pas_Core_Tokens' ) ? [ Pas_Core_Tokens::handle() ] : [] ),
            PWDF_VERSION
        );
        wp_enqueue_script(
            'pwdf-feed',
            PWDF_URL . 'assets/js/feed-display.js',
            [ 'jquery' ],
            PWDF_VERSION,
            true
        );
        $s = PWDF_Settings::get();
        wp_localize_script( 'pwdf-feed', 'PWDF', [
            'ajax_url'      => admin_url( 'admin-ajax.php' ),
            'nonce'         => wp_create_nonce( 'pwdf_ajax' ),
            'animation'     => $s['animation'],
            'autoplay'      => $s['autoplay'] ? 'true' : 'false',
            'autoplay_speed'=> (int) $s['autoplay_speed'],
            'display_mode'  => $s['display_mode'],
        ] );
    }

    public static function render( $atts = [] ) {
        $s     = PWDF_Settings::get();
        $cards = self::get_cards( $s );

        if ( empty( $cards ) ) {
            return '<div class="pwdf-empty">هنوز محتوایی منتشر نشده.</div>';
        }

        $mode  = $s['display_mode'];
        $class = 'pwdf-feed pwdf-mode-' . esc_attr( $mode );
        if ( $mode === 'grid' ) {
            $class .= ' pwdf-cols-' . (int) $s['grid_columns'];
        }

        ob_start();
        echo '<div class="pwdf-anim-wrapper">';
        echo '<div class="' . esc_attr( $class ) . '" data-mode="' . esc_attr( $mode ) . '">';

        if ( $mode === 'slider' ) {
            echo '<div class="pwdf-slider-track">';
        }

        $today = current_time( 'Y-m-d' );
        foreach ( $cards as $card ) {
            self::render_card( $card, $s, $today );
        }

        if ( $mode === 'slider' ) {
            echo '</div>';
            echo '<div class="pwdf-slider-nav">';
            echo '<button class="pwdf-prev" aria-label="قبلی">&#10094;</button>';
            echo '<div class="pwdf-dots"></div>';
            echo '<button class="pwdf-next" aria-label="بعدی">&#10095;</button>';
            echo '</div>';
        }

        echo '</div>'; // pwdf-feed
        echo '</div>'; // pwdf-anim-wrapper
        return ob_get_clean();
    }

    /**
     * BUG-2: از دو WP_Query موازی به یک query واحد با meta_query تبدیل شد.
     * کارت‌های پین‌شده از طریق مرتب‌سازی posts_clauses اول نمایش می‌یابند.
     */
    private static function get_cards( $s ) {
        $args = [
            'post_type'      => 'pwdf_card',
            'post_status'    => 'publish',
            'posts_per_page' => (int) $s['card_count'],
            'orderby'        => $s['order'] === 'random' ? 'rand' : 'date',
            'order'          => $s['order'] === 'oldest' ? 'ASC' : 'DESC',
        ];

        // BUG-3: اضافه کردن 'column' => 'post_date' تا date_query با timezone سایت مقایسه کند
        if ( $s['show_days'] === 'today' ) {
            $args['date_query'] = [ [
                'after'     => current_time( 'Y-m-d' ) . ' 00:00:00',
                'inclusive' => true,
                'column'    => 'post_date',
            ] ];
        } elseif ( $s['show_days'] === 'week' ) {
            $args['date_query'] = [ [
                'after'  => '7 days ago',
                'column' => 'post_date',
            ] ];
        } elseif ( $s['show_days'] === 'custom' ) {
            $days = max( 1, (int) $s['custom_days'] );
            $args['date_query'] = [ [
                'after'  => $days . ' days ago',
                'column' => 'post_date',
            ] ];
        }

        // query واحد — کارت‌های پین شده با فیلتر posts_clauses اول می‌آیند
        add_filter( 'posts_clauses', [ __CLASS__, 'pin_first_clauses' ] );
        $query = new WP_Query( $args );
        remove_filter( 'posts_clauses', [ __CLASS__, 'pin_first_clauses' ] );

        return $query->have_posts() ? $query->posts : [];
    }

    /**
     * کارت‌های دارای _pwdf_pin_today=1 را اول مرتب می‌کند.
     * از LEFT JOIN استفاده می‌کند تا کارت‌های بدون meta هم برگردانده شوند.
     */
    public static function pin_first_clauses( $clauses ) {
        global $wpdb;
        $clauses['join'] .= " LEFT JOIN {$wpdb->postmeta} AS pwdf_pin_meta"
            . " ON ({$wpdb->posts}.ID = pwdf_pin_meta.post_id AND pwdf_pin_meta.meta_key = '_pwdf_pin_today')";
        // پین‌شده‌ها اول (1 قبل از NULL/0)، بقیه ترتیب اصلی query را حفظ می‌کنند
        $clauses['orderby'] = "CASE WHEN pwdf_pin_meta.meta_value = '1' THEN 0 ELSE 1 END ASC, " . $clauses['orderby'];
        return $clauses;
    }

    private static function render_card( $post, $s, $today ) {
        $type       = get_post_meta( $post->ID, '_pwdf_type',       true ) ?: 'text';
        $author_tag = get_post_meta( $post->ID, '_pwdf_author_tag', true );
        $pin        = get_post_meta( $post->ID, '_pwdf_pin_today',  true ) === '1';

        $custom_color  = sanitize_hex_color( get_post_meta( $post->ID, '_pwdf_color', true ) );
        $default_color = sanitize_hex_color( $s['colors'][ $type ] ?? '' ) ?: '#5B8DB8';
        $color         = $custom_color ?: $default_color;

        $post_date  = get_the_date( 'Y-m-d', $post );
        $is_today   = ( $post_date === $today );
        $radius     = (int) $s['card_radius'];

        $type_icons = [
            'text'     => '📝',
            'quote'    => '💬',
            'image'    => '🖼️',
            'exercise' => '✅',
            'question' => '❓',
            'book'     => '📚',
        ];
        $type_labels = [
            'text'     => 'متن',
            'quote'    => 'نقل‌قول',
            'image'    => 'تصویر',
            'exercise' => 'تمرین',
            'question' => 'سوال',
            'book'     => 'معرفی کتاب',
        ];

        $card_classes = 'pwdf-card pwdf-type-' . esc_attr( $type );
        if ( $is_today && $s['today_highlight'] ) $card_classes .= ' pwdf-today';
        if ( $pin ) $card_classes .= ' pwdf-pinned';

        $shadow_style = $s['card_shadow']
            ? 'box-shadow:0 4px 20px rgba(0,0,0,.10),0 1px 4px rgba(0,0,0,.06);'
            : '';

        $color_rgba = self::hex_to_rgba( $color, 0.12 );

        echo '<div class="' . esc_attr( $card_classes ) . '" '
           . 'style="' . esc_attr( 'border-radius:' . $radius . 'px;' . $shadow_style ) . '" '
           . 'data-type="' . esc_attr( $type ) . '" '
           . 'data-color="' . esc_attr( $color ) . '">';

        echo '<div class="pwdf-card-accent" style="background:' . esc_attr( $color ) . ';border-radius:' . esc_attr( $radius ) . 'px ' . esc_attr( $radius ) . 'px 0 0"></div>';

        echo '<div class="pwdf-card-header">';
        if ( $s['show_type_badge'] ) {
            echo '<span class="pwdf-badge" style="background:' . esc_attr( $color_rgba ) . ';color:' . esc_attr( $color ) . '">';
            echo esc_html( ( $type_icons[ $type ] ?? '' ) . ' ' . ( $type_labels[ $type ] ?? '' ) );
            echo '</span>';
        }
        if ( $pin ) echo '<span class="pwdf-pin-badge">📌 پین شده</span>';
        if ( $is_today && $s['today_highlight'] ) echo '<span class="pwdf-today-badge">امروز ⭐</span>';
        if ( $s['show_date'] ) {
            echo '<span class="pwdf-date">' . esc_html( self::persian_date( $post ) ) . '</span>';
        }
        echo '</div>';

        echo '<div class="pwdf-card-body">';

        if ( $type === 'image' && has_post_thumbnail( $post ) ) {
            echo '<div class="pwdf-card-image">';
            echo get_the_post_thumbnail( $post, 'large', [ 'style' => 'width:100%;border-radius:' . esc_attr( max(0,$radius-4) ) . 'px' ] );
            echo '</div>';
        }

        if ( $type === 'quote' ) {
            echo '<blockquote class="pwdf-quote" style="border-color:' . esc_attr( $color ) . '">';
            echo wp_kses_post( get_the_content( null, false, $post ) );
            echo '</blockquote>';
        } else {
            echo '<div class="pwdf-content">';
            echo wp_kses_post( get_the_content( null, false, $post ) );
            echo '</div>';
        }

        if ( $author_tag && $s['show_author'] ) {
            echo '<div class="pwdf-author" style="color:' . esc_attr( $color ) . '">— ' . esc_html( $author_tag ) . '</div>';
        }

        echo '</div>'; // body

        if ( $s['show_share'] || $s['show_save'] ) {
            echo '<div class="pwdf-card-footer">';
            if ( $s['show_save'] ) {
                $btn_rgba = self::hex_to_rgba( $color, 0.15 );
                echo '<button class="pwdf-btn pwdf-save" data-id="' . esc_attr( $post->ID ) . '" '
                   . 'style="' . esc_attr( 'color:' . $color . ';border-color:' . $btn_rgba . ';--pwdf-accent:' . $color ) . '">'
                   . '🔖 ذخیره</button>';
            }
            if ( $s['show_share'] ) {
                $btn_rgba = self::hex_to_rgba( $color, 0.15 );
                echo '<button class="pwdf-btn pwdf-share" data-id="' . esc_attr( $post->ID ) . '" '
                   . 'style="' . esc_attr( 'color:' . $color . ';border-color:' . $btn_rgba ) . '">'
                   . '📤 اشتراک‌گذاری</button>';
            }
            echo '</div>';
        }

        echo '</div>'; // card
    }

    private static function persian_date( $post ) {
        $ts = get_post_time( 'U', false, $post );
        if ( function_exists( 'jdate' ) ) {
            return jdate( 'd F Y', $ts );
        }
        return get_the_date( 'j F Y', $post );
    }

    private static function hex_to_rgba( $hex, $alpha = 1.0 ) {
        $hex = ltrim( $hex, '#' );
        if ( strlen( $hex ) === 3 ) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }
        if ( strlen( $hex ) !== 6 ) {
            return 'rgba(91,141,184,' . $alpha . ')';
        }
        $r = hexdec( substr( $hex, 0, 2 ) );
        $g = hexdec( substr( $hex, 2, 2 ) );
        $b = hexdec( substr( $hex, 4, 2 ) );
        return 'rgba(' . $r . ',' . $g . ',' . $b . ',' . $alpha . ')';
    }
}
