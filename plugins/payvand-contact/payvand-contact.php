<?php
/**
 * Plugin Name: پیوند امن — فرم ارتباط سریع (Pivand Contact)
 * Description: فرم ارتباط سریع تخصصی زوج‌درمانی با ذخیره در پنل و ارسال ایمیل
 * Version: 1.4.4
 * Requires at least: 6.6
 * Tested up to: 7.1
 * Requires PHP: 7.4
 * Domain Path: /languages
 * Author: Hadi.parsaeian
 * Text Domain: payvand-contact
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'PAYVAND_VERSION', '1.4.3' );

// ── Theme Color Detection ────────────────────────────────────────────────────
function payvand_get_theme_colors() {
    $primary   = '';
    $secondary = '';

    // 1) FSE theme.json palette – most reliable for block themes
    if ( function_exists( 'wp_get_global_settings' ) ) {
        $settings = wp_get_global_settings(['color','palette']);
        $palette  = $settings['theme'] ?? $settings['default'] ?? [];
        foreach ( $palette as $swatch ) {
            $slug  = $swatch['slug'] ?? '';
            $color = $swatch['color'] ?? '';
            if ( ! $primary && in_array($slug, ['primary','accent','brand','main','color-1']) ) {
                $primary = $color;
            }
            if ( ! $secondary && in_array($slug, ['secondary','color-2','dark','heading']) ) {
                $secondary = $color;
            }
        }
        // fallback: just take first two palette colors if nothing matched
        if ( ! $primary && isset($palette[0]['color']) )   $primary   = $palette[0]['color'];
        if ( ! $secondary && isset($palette[1]['color']) ) $secondary = $palette[1]['color'];
    }

    // 2) Classic themes – Customizer mods
    if ( ! $primary ) {
        $candidates = [
            get_theme_mod('accent_color'),
            get_theme_mod('primary_color'),
            get_theme_mod('link_color'),
            get_theme_mod('header_background_color'),
        ];
        foreach ( $candidates as $c ) {
            if ( $c && sanitize_hex_color( $c ) ) {
                $primary = $c;
                break;
            }
        }
    }

    // 3) Hardcoded fallback (primary باید hex معتبر باشد؛ رنگ‌های var()/rgb() پالت رد می‌شوند)
    $primary   = sanitize_hex_color( $primary );
    $secondary = sanitize_hex_color( $secondary );
    if ( ! $primary )   $primary   = '#2d6a8f';
    if ( ! $secondary ) $secondary = payvand_darken_hex($primary, 20);

    return [
        'primary'   => sanitize_hex_color($primary)   ?: '#2d6a8f',
        'secondary' => sanitize_hex_color($secondary) ?: '#1a4f70',
        'light'     => payvand_hex_to_rgba($primary, 0.10),
        'medium'    => payvand_hex_to_rgba($primary, 0.18),
        'shadow'    => payvand_hex_to_rgba($primary, 0.12),
    ];
}

/** Darken a hex color by $amount (0-255) */
function payvand_darken_hex( $hex, $amount = 30 ) {
    $hex = ltrim($hex, '#');
    if ( strlen($hex) === 3 ) {
        $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
    }
    $r = max(0, hexdec(substr($hex,0,2)) - $amount);
    $g = max(0, hexdec(substr($hex,2,2)) - $amount);
    $b = max(0, hexdec(substr($hex,4,2)) - $amount);
    return sprintf('#%02x%02x%02x', $r, $g, $b);
}

/** Hex to rgba() string */
function payvand_hex_to_rgba( $hex, $alpha = 1.0 ) {
    $hex = ltrim($hex, '#');
    if ( strlen($hex) === 3 ) {
        $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
    }
    $r = hexdec(substr($hex,0,2));
    $g = hexdec(substr($hex,2,2));
    $b = hexdec(substr($hex,4,2));
    return "rgba($r,$g,$b,$alpha)";
}
define( 'PAYVAND_PATH', plugin_dir_path( __FILE__ ) );
define( 'PAYVAND_URL', plugin_dir_url( __FILE__ ) );
define( 'PAYVAND_TABLE_SUFFIX', 'payvand_messages' );

// ── Helpers ──────────────────────────────────────────────────────────────────
/** تبدیل ارقام فارسی/عربی به انگلیسی (کاربران ایرانی معمولاً با کیبورد فارسی تایپ می‌کنند). */
function payvand_digits_to_en( $str ) {
    return strtr( (string) $str, [
        '۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4','۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9',
        '٠'=>'0','١'=>'1','٢'=>'2','٣'=>'3','٤'=>'4','٥'=>'5','٦'=>'6','٧'=>'7','٨'=>'8','٩'=>'9',
    ] );
}

if ( ! function_exists( 'payvand_icon' ) ) {
    /**
     * آیکون SVG درون‌خطی برای فرم/صفحه‌های عمومی پیوند امن.
     *
     * هم‌خانواده با آیکون‌های کیت (شبکهٔ ۲۴×۲۴، stroke=currentColor)، بدون فونت
     * آیکون و بدون درخواست خارجی؛ جای ایموجی می‌نشیند تا ظاهر فرم روی همهٔ
     * سیستم‌عامل‌ها یکسان بماند. این افزونه مستقل است و به کیت وابسته نمی‌شود.
     */
    function payvand_icon( $name, $size = 16 ) {
        $paths = [
            'building' => '<path d="M4.5 20.5V5.2a1.2 1.2 0 0 1 1.2-1.2h7.6a1.2 1.2 0 0 1 1.2 1.2v15.3"/><path d="M14.5 9.5h4.3a1.2 1.2 0 0 1 1.2 1.2v9.8M3 20.5h18"/><path d="M7.5 7.5h3M7.5 11h3M7.5 14.5h3M16.8 13h1M16.8 16.5h1"/>',
            'monitor'  => '<rect x="3" y="4.5" width="18" height="12" rx="2"/><path d="M9 20.5h6M12 16.5v4"/>',
            'phone'    => '<path d="M7 3.8 9.6 4l1.2 4-2 1.4a11.5 11.5 0 0 0 5.8 5.8l1.4-2 4 1.2.2 2.6c0 1.3-1 2.3-2.3 2.4C11.4 20 4 12.6 4.6 6.1 4.7 4.8 5.7 3.8 7 3.8z"/>',
            'lock'     => '<rect x="4.5" y="10.5" width="15" height="9.5" rx="2.2"/><path d="M8 10.5V8a4 4 0 0 1 8 0v2.5"/>',
            'message'  => '<path d="M20 15.5a2 2 0 0 1-2 2H9l-4.5 3v-3H6a2 2 0 0 1-2-2V6.5a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2z"/>',
            'inbox'    => '<path d="M4 13.5 6.4 5.4A1.5 1.5 0 0 1 7.8 4.3h8.4a1.5 1.5 0 0 1 1.4 1.1L20 13.5V18a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2z"/><path d="M4 13.5h4l1 2.5h6l1-2.5h4"/>',
            'settings' => '<circle cx="12" cy="12" r="3"/><path d="M19.4 13.5a7.6 7.6 0 0 0 0-3l1.8-1.2-1.8-3.1-2.1.8a7.6 7.6 0 0 0-2.6-1.5L14.4 3h-4.8l-.3 2.5a7.6 7.6 0 0 0-2.6 1.5l-2.1-.8-1.8 3.1L4.6 10.5a7.6 7.6 0 0 0 0 3l-1.8 1.2 1.8 3.1 2.1-.8a7.6 7.6 0 0 0 2.6 1.5l.3 2.5h4.8l.3-2.5a7.6 7.6 0 0 0 2.6-1.5l2.1.8 1.8-3.1z"/>',
            'mail'     => '<rect x="3.5" y="5.5" width="17" height="13" rx="2"/><path d="m4.5 7 7.5 6 7.5-6"/>',
            'check'    => '<path d="m5 12.5 4.5 4.5L19 7.5"/>',
            'alert'    => '<path d="M12 4.5 21 20H3z"/><path d="M12 10v4.5M12 17.2v.3"/>',
            'x'        => '<path d="m6 6 12 12M18 6 6 18"/>',
        ];

        if ( ! isset( $paths[ $name ] ) ) {
            return '';
        }

        return sprintf(
            '<svg class="payvand-icon payvand-icon-%s" width="%d" height="%d" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">%s</svg>',
            esc_attr( $name ),
            (int) $size,
            (int) $size,
            $paths[ $name ]
        );
    }
}

/** ساخت/به‌روزرسانی جدول پیام‌ها (idempotent؛ از dbDelta استفاده می‌کند). */
function payvand_install_schema() {
    global $wpdb;
    $table   = $wpdb->prefix . PAYVAND_TABLE_SUFFIX;
    $charset = $wpdb->get_charset_collate();
    $sql = "CREATE TABLE $table (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        full_name varchar(150) NOT NULL,
        phone varchar(20) NOT NULL,
        email varchar(150) NOT NULL DEFAULT '',
        visit_type varchar(30) NOT NULL,
        consult_type varchar(30) NOT NULL,
        message text NOT NULL,
        status varchar(20) NOT NULL DEFAULT 'new',
        ip varchar(45) NOT NULL DEFAULT '',
        created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        KEY status (status),
        KEY created_at (created_at)
    ) $charset;";
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta( $sql );
}

// ── Activation ──────────────────────────────────────────────────────────────
register_activation_hook( __FILE__, 'payvand_activate' );
function payvand_activate() {
    payvand_install_schema();
    add_option( 'payvand_admin_email', get_option('admin_email') );
    add_option( 'payvand_rate_limit', 3 );
    add_option( 'payvand_color_override', '' );
    add_option( 'payvand_force_load_assets', 0 );
    add_option( 'payvand_version', PAYVAND_VERSION ); // skip upgrade routine on fresh installs
}

// ── Deactivation ─────────────────────────────────────────────────────────────
register_deactivation_hook( __FILE__, 'payvand_deactivate' );
function payvand_deactivate() { /* keep data on deactivate */ }

// Uninstall: فقط از طریق uninstall.php انجام می‌شود (قبلاً هوک تکراری هم ثبت شده بود).

// ── Upgrade Routine ──────────────────────────────────────────────────────────
// Runs on every page load but only does work when the stored version is behind.
// This ensures new options/columns added in future versions are backfilled
// automatically on the first request after a plugin file update, without
// requiring the admin to deactivate and reactivate the plugin.
add_action( 'plugins_loaded', 'payvand_maybe_upgrade' );
function payvand_maybe_upgrade() {
    $stored = get_option( 'payvand_version', '0' );
    if ( version_compare( $stored, PAYVAND_VERSION, '>=' ) ) {
        return; // already up to date
    }

    // Backfill any options that may be missing from older installs.
    // add_option() is a no-op if the option already exists, so this is safe.
    add_option( 'payvand_admin_email',       get_option('admin_email') );
    add_option( 'payvand_rate_limit',        3 );
    add_option( 'payvand_color_override',    '' );
    add_option( 'payvand_force_load_assets', 0 );  // added in v1.1.5

    // v1.4.0: ایندکس‌های status/created_at — dbDelta روی جدول موجود امن است.
    payvand_install_schema();

    // Record the current version so this block is skipped on subsequent loads.
    update_option( 'payvand_version', PAYVAND_VERSION );
}

/**
 * FIX: has_shortcode() on $post->post_content misses shortcodes placed inside
 * reusable blocks / synced patterns (stored separately in wp_posts as post_type=wp_block).
 * This helper checks the current post content AND any referenced blocks.
 */
function payvand_page_has_shortcode() {
    global $post;
    if ( ! is_a( $post, 'WP_Post' ) ) {
        return false;
    }
    // Direct match in post content
    if ( has_shortcode( $post->post_content, 'payvand_contact' ) ) {
        return true;
    }
    // Allow a site-wide force-load option (useful when shortcode is in a widget or template part)
    if ( get_option( 'payvand_force_load_assets', 0 ) ) {
        return true;
    }
    // Check reusable blocks referenced by this post (including nested ones)
    if ( function_exists( 'parse_blocks' ) ) {
        return payvand_blocks_have_shortcode( parse_blocks( $post->post_content ) );
    }
    return false;
}


/** جستجوی بازگشتی شورت‌کد در بلوک‌ها (گروه/ستون/بلوک قابل‌استفاده مجدد). */
function payvand_blocks_have_shortcode( array $blocks, $depth = 0 ) {
    if ( $depth > 5 ) {
        return false;
    }
    foreach ( $blocks as $block ) {
        if ( 'core/block' === ( $block['blockName'] ?? '' ) && ! empty( $block['attrs']['ref'] ) ) {
            $ref_post = get_post( (int) $block['attrs']['ref'] );
            if ( $ref_post && has_shortcode( $ref_post->post_content, 'payvand_contact' ) ) {
                return true;
            }
        }
        if ( ! empty( $block['innerBlocks'] ) && payvand_blocks_have_shortcode( $block['innerBlocks'], $depth + 1 ) ) {
            return true;
        }
    }
    return false;
}

add_action( 'wp_enqueue_scripts', 'payvand_enqueue' );
function payvand_enqueue() {
    if ( ! payvand_page_has_shortcode() ) {
        return;
    }
    // فونت برند: اگر پیوند امن — هسته مشترک فعال باشد، از نسخه‌ی سلف‌هاست‌
    // core فعال نباشد، به همان روش قبلی (Google Fonts) برمی‌گردیم تا ظاهر
    // فرم خراب نشود.
    // فاز ۱۲ / گام ۶: هیچ درخواستی به Google Fonts زده نمی‌شود. اولویت با
    // فونت سلف‌هاست کیت رابط کاربری است، بعد فونت سلف‌هاست هسته؛ اگر هیچ‌کدام
    // نبود، فونت از استک سیستمی همان CSS استفاده می‌کند.
    if ( class_exists( 'PUI' ) ) {
        $vazirmatn_handle = PUI::handle( 'fonts' );
    } elseif ( class_exists( 'Pas_Core_Fonts' ) ) {
        $vazirmatn_handle = Pas_Core_Fonts::handle();
    } else {
        $vazirmatn_handle = '';
    }
    if ( $vazirmatn_handle !== '' ) {
        wp_enqueue_style( $vazirmatn_handle );
    }

    // توکن‌های طراحی مشترک اکوسیستم (رنگ/شعاع/سایه): اگر core فعال باشد،
    // form.css به‌جای پالت مستقل خودش از متغیرهای --pivand-* یکپارچه
    // استفاده می‌کند (نگاه کنید به var(--pivand-*, fallback) در form.css).
    $css_deps = $vazirmatn_handle !== '' ? [ $vazirmatn_handle ] : [];
    if ( class_exists( 'Pas_Core_Tokens' ) ) {
        $css_deps[] = Pas_Core_Tokens::handle();
    }

    wp_enqueue_style(  'payvand-css', PAYVAND_URL . 'assets/form.css', $css_deps, PAYVAND_VERSION );
    wp_enqueue_script( 'payvand-js',  PAYVAND_URL . 'assets/form.js',  ['jquery'], PAYVAND_VERSION, true );
    wp_localize_script( 'payvand-js', 'payvandAjax', [
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce'    => wp_create_nonce('payvand_submit'),
    ]);
}

// ── Dynamic CSS (theme colors) ────────────────────────────────────────────────

// رنگ دستی (payvand_color_override) و گزینه‌ی بارگذاری اجباری فایل‌ها مستقیم در
// خروجی صفحه (CSS داخل <head> / enqueue) اثر دارند؛ پس با تغییرشان در پیشخوان
// باید کش صفحات پاک شود وگرنه صفحه‌ی عمومی رنگ/رفتار قدیمی را نشان می‌دهد.
// (مستقل از سایر افزونه‌ها؛ کش CDN بیرونی از اینجا قابل پاک‌کردن نیست.)
foreach ( [ 'payvand_color_override', 'payvand_force_load_assets' ] as $payvand_opt ) {
    add_action( 'update_option_' . $payvand_opt, 'payvand_purge_page_cache' );
    add_action( 'add_option_' . $payvand_opt,    'payvand_purge_page_cache' );
}
unset( $payvand_opt );

function payvand_purge_page_cache() {
    static $done = false;
    if ( $done ) {
        return;
    }
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

add_action( 'wp_head', 'payvand_dynamic_css' );
function payvand_dynamic_css() {
    if ( ! payvand_page_has_shortcode() ) {
        return;
    }

    // Allow manual override from settings
    $override = sanitize_hex_color( get_option('payvand_color_override', '') );

    // اگر هسته‌ی اکوسیستم فعال است و رنگ دستی تنظیم نشده، توکن‌های مشترک
    // (--pivand-*) تعیین‌کننده‌اند؛ خروجی این تابع آن‌ها را بازنویسی می‌کرد.
    if ( ! $override && class_exists( 'Pas_Core_Tokens' ) ) {
        return;
    }

    if ( $override ) {
        $c = [
            'primary'   => $override,
            'secondary' => payvand_darken_hex($override, 25),
            'light'     => payvand_hex_to_rgba($override, 0.10),
            'medium'    => payvand_hex_to_rgba($override, 0.18),
            'shadow'    => payvand_hex_to_rgba($override, 0.12),
        ];
    } else {
        $c = payvand_get_theme_colors();
    }

    // BUG-01 fix: validate every CSS value before output.
    // sanitize_hex_color ensures primary/secondary are safe hex strings.
    // payvand_safe_rgba() ensures rgba() values contain only digits, dots, and commas.
    $primary   = sanitize_hex_color( $c['primary'] )   ?: '#2d6a8f';
    $secondary = sanitize_hex_color( $c['secondary'] ) ?: '#1a4f70';
    $light     = payvand_safe_rgba( $c['light'],  'rgba(45,106,143,0.10)' );
    $medium    = payvand_safe_rgba( $c['medium'], 'rgba(45,106,143,0.18)' );
    $shadow    = payvand_safe_rgba( $c['shadow'], 'rgba(45,106,143,0.12)' );

    echo "<style id='payvand-theme-colors'>\n    :root {\n"
        . "        --pv-primary:   " . esc_html( $primary )   . ";\n"
        . "        --pv-secondary: " . esc_html( $secondary ) . ";\n"
        . "        --pv-light:     " . esc_html( $light )     . ";\n"
        . "        --pv-medium:    " . esc_html( $medium )    . ";\n"
        . "        --pv-shadow:    " . esc_html( $shadow )    . ";\n"
        . "    }\n    </style>\n";
}

/**
 * BUG-01 helper: ensure an rgba() string contains only safe characters.
 * Accepts "rgba(R,G,B,A)" where R/G/B are 0-255 integers and A is 0.0-1.0.
 * Returns $fallback if the value does not match.
 */
function payvand_safe_rgba( $value, $fallback ) {
    if ( preg_match( '/^rgba\(\s*\d{1,3}\s*,\s*\d{1,3}\s*,\s*\d{1,3}\s*,\s*[01](?:\.\d+)?\s*\)$/', $value ) ) {
        return $value;
    }
    return $fallback;
}

// ── Shortcode ────────────────────────────────────────────────────────────────
add_shortcode( 'payvand_contact', 'payvand_render_form' );
function payvand_render_form() {
    ob_start(); ?>
    <div class="payvand-wrap" dir="rtl">
        <div class="payvand-header">
            <h3>ارتباط سریع با ما</h3>
            <p>تیم پیوند امن در کوتاه‌ترین زمان پاسخگوی شما خواهد بود</p>
        </div>

        <div id="payvand-alert" class="payvand-alert" role="alert" aria-live="polite" style="display:none;"></div>

        <form id="payvand-form" novalidate>

            <?php /* honeypot: برای کاربر واقعی نامرئی؛ ربات‌ها پرش می‌کنند */ ?>
            <div aria-hidden="true" style="position:absolute;right:-9999px;width:1px;height:1px;overflow:hidden">
                <label for="pv-website">وب‌سایت</label>
                <input type="text" id="pv-website" name="website" tabindex="-1" autocomplete="off" value="">
            </div>

            <div class="payvand-row">
                <div class="payvand-field">
                    <label for="pv-name">نام و نام خانوادگی <span>*</span></label>
                    <input type="text" id="pv-name" name="full_name" placeholder="مثلاً: علی رضایی" autocomplete="name" maxlength="150" required>
                </div>
                <div class="payvand-field">
                    <label for="pv-phone">شماره موبایل <span>*</span></label>
                    <input type="tel" id="pv-phone" name="phone" placeholder="09XX-XXX-XXXX" autocomplete="tel" inputmode="tel" maxlength="20" required>
                </div>
            </div>

            <div class="payvand-row">
                <div class="payvand-field">
                    <label for="pv-email">ایمیل <small>(اختیاری)</small></label>
                    <input type="email" id="pv-email" name="email" placeholder="example@email.com" autocomplete="email" maxlength="150">
                </div>
                <div class="payvand-field">
                    <label for="pv-visit">نوع مراجعه <span>*</span></label>
                    <select id="pv-visit" name="visit_type" required>
                        <option value="">انتخاب کنید…</option>
                        <option value="couple">زوج‌درمانی</option>
                        <option value="marital_conflict">تعارضات زناشویی</option>
                        <option value="infidelity">خیانت</option>
                        <option value="divorce">طلاق</option>
                        <option value="individual">مشاوره فردی</option>
                        <option value="family">مشاوره خانواده</option>
                        <option value="premarital">مشاوره پیش از ازدواج</option>
                    </select>
                </div>
            </div>

            <div class="payvand-row">
                <div class="payvand-field full">
                    <span class="payvand-label" id="pv-consult-label">روش مشاوره <span>*</span></span>
                    <div class="payvand-radio-group" role="radiogroup" aria-labelledby="pv-consult-label">
                        <label class="radio-card">
                            <input type="radio" name="consult_type" value="in-person"> 
                            <span><?php echo payvand_icon('building'); ?> حضوری</span>
                        </label>
                        <label class="radio-card">
                            <input type="radio" name="consult_type" value="online"> 
                            <span><?php echo payvand_icon('monitor'); ?> آنلاین</span>
                        </label>
                        <label class="radio-card">
                            <input type="radio" name="consult_type" value="phone"> 
                            <span><?php echo payvand_icon('phone'); ?> تلفنی</span>
                        </label>
                    </div>
                </div>
            </div>

            <div class="payvand-row">
                <div class="payvand-field full">
                    <label for="pv-message">پیام شما</label>
                    <textarea id="pv-message" name="message" rows="4" maxlength="2000" placeholder="به طور مختصر موضوع مشاوره یا سوال خود را بنویسید…"></textarea>
                </div>
            </div>

            <div class="payvand-row">
                <div class="payvand-field full payvand-privacy">
                    <label class="checkbox-label">
                        <input type="checkbox" id="pv-privacy" name="privacy" required>
                        <span>با <a href="<?php echo esc_url( get_privacy_policy_url() ?: home_url( '/privacy-policy' ) ); ?>" target="_blank" rel="noopener">قوانین حریم خصوصی</a> پیوند امن موافقم و اطلاعاتم در اختیار تیم مشاوره قرار می‌گیرد.</span>
                    </label>
                </div>
            </div>

            <div class="payvand-submit-row">
                <button type="submit" id="pv-submit" class="payvand-btn">
                    <span class="btn-text">ارسال پیام</span>
                    <span class="btn-loader" style="display:none;">در حال ارسال…</span>
                </button>
                <p class="payvand-secure"><?php echo payvand_icon('lock'); ?> اطلاعات شما کاملاً محرمانه است</p>
            </div>
        </form>
    </div>
    <?php
    return ob_get_clean();
}

// ── AJAX Handler ──────────────────────────────────────────────────────────────
add_action( 'wp_ajax_payvand_submit',        'payvand_handle_submit' );
add_action( 'wp_ajax_nopriv_payvand_submit', 'payvand_handle_submit' );

// nonce تازه برای صفحاتی که کش شده‌اند (nonce داخل HTML کش‌شده منقضی می‌شود)
add_action( 'wp_ajax_payvand_nonce',        'payvand_ajax_nonce' );
add_action( 'wp_ajax_nopriv_payvand_nonce', 'payvand_ajax_nonce' );
function payvand_ajax_nonce() {
    nocache_headers();
    wp_send_json_success( [ 'nonce' => wp_create_nonce( 'payvand_submit' ) ] );
}

function payvand_handle_submit() {
    // Nonce
    check_ajax_referer( 'payvand_submit', 'nonce' );

    // Honeypot: ربات‌ها فیلد مخفی را پر می‌کنند؛ پاسخ موفق جعلی می‌دهیم.
    if ( ! empty( $_POST['website'] ) ) {
        wp_send_json_success( [ 'message' => 'پیام شما با موفقیت ارسال شد. به زودی با شما تماس خواهیم گرفت.' ] );
    }

    // Rate limit: max X submissions per IP per hour
    // این IP-detection قبلاً یک نسخه‌ی جداگانه (چهارمین نسخه‌ی مشابه در کل
    // اکوسیستم) بود؛ حالا از هسته مشترک استفاده می‌کند (که هم ArvanCloud را
    // پوشش می‌دهد، هم با filter_var اعتبارسنجی می‌کند) — اگر هسته فعال
    // نباشد، دقیقاً همان منطق قبلی fallback اجرا می‌شود.
    if ( class_exists( 'Pas_Core_Helpers' ) ) {
        $ip = \Pas_Core_Helpers::get_client_ip();
    } else {
        $ip = '';
        foreach ( [ 'HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR' ] as $header ) {
            $val = $_SERVER[ $header ] ?? '';
            if ( $val ) {
                // X-Forwarded-For may contain a comma-separated list; take the first entry.
                $ip = trim( explode( ',', $val )[0] );
                break;
            }
        }
        $ip = sanitize_text_field( $ip );
    }
    $limit = (int) get_option('payvand_rate_limit', 3);
    $limit = max( 1, $limit );
    $key   = 'payvand_rl_' . md5( $ip );
    $count = (int) get_transient( $key );
    if ( $count >= $limit ) {
        wp_send_json_error(['message' => 'تعداد ارسال‌های شما به حد مجاز رسیده. لطفاً بعداً تلاش کنید.']);
    }

    // Sanitize
    $full_name    = mb_substr( sanitize_text_field( wp_unslash( $_POST['full_name'] ?? '' ) ), 0, 150 );
    $phone        = payvand_digits_to_en( sanitize_text_field( wp_unslash( $_POST['phone'] ?? '' ) ) );
    // نرمال‌سازی به فرمت مشترک اکوسیستم (09xxxxxxxxx) — قبلاً اینجا مقدار
    // خام (با فاصله/خط‌تیره‌ی احتمالی) در دیتابیس ذخیره می‌شد، فقط برای
    // اعتبارسنجی یک نسخه‌ی موقتِ strip-شده ساخته می‌شد که هیچ‌جا ذخیره
    // نمی‌شد. این ناهماهنگی با بقیه‌ی اکوسیستم (که همه‌جا 09xxxxxxxxx ذخیره
    // می‌کنند) بی‌ضرر است چون این افزونه فعلاً به هیچ فیلتر بین‌افزونه‌ای
    // متکی نیست، اما برای یکدستی داده در آینده اصلاح شد.
    if ( class_exists( 'Pas_Core_Helpers' ) && $phone !== '' ) {
        $phone = \Pas_Core_Helpers::normalize_mobile( $phone );
    } else {
        $phone = preg_replace( '/[\s\-]/', '', $phone );
    }
    $email        = substr( sanitize_email( wp_unslash( $_POST['email'] ?? '' ) ), 0, 150 );
    $visit_type   = sanitize_text_field( wp_unslash( $_POST['visit_type']   ?? '' ) );
    $consult_type = sanitize_text_field( wp_unslash( $_POST['consult_type'] ?? '' ) );
    $message      = mb_substr( sanitize_textarea_field( wp_unslash( $_POST['message']  ?? '' ) ), 0, 2000 );
    $privacy      = ! empty( $_POST['privacy'] );

    // Whitelist enumerable fields (BUG-07)
    $allowed_visit_types   = [ 'couple', 'marital_conflict', 'infidelity', 'divorce', 'individual', 'family', 'premarital' ];
    $allowed_consult_types = [ 'in-person', 'online', 'phone' ];

    // Validate required
    if ( ! $full_name || ! $phone || ! $visit_type || ! $consult_type || ! $privacy ) {
        wp_send_json_error(['message' => 'لطفاً تمام فیلدهای ضروری را تکمیل کنید.']);
    }
    if ( ! in_array( $visit_type, $allowed_visit_types, true ) ) {
        wp_send_json_error(['message' => 'نوع مراجعه انتخاب‌شده معتبر نیست.']);
    }
    if ( ! in_array( $consult_type, $allowed_consult_types, true ) ) {
        wp_send_json_error(['message' => 'روش مشاوره انتخاب‌شده معتبر نیست.']);
    }
    if ( ! preg_match('/^09[0-9]{9}$/', $phone ) ) {
        wp_send_json_error(['message' => 'شماره موبایل وارد شده معتبر نیست.']);
    }
    if ( ! empty( $_POST['email'] ) && ! is_email( $email ) ) {
        wp_send_json_error(['message' => 'فرمت ایمیل وارد شده صحیح نیست.']);
    }

    // FIX: increment rate limit counter immediately after all validation passes,
    // so DB errors or mail failures cannot be exploited to bypass the limit.
    set_transient( $key, $count + 1, HOUR_IN_SECONDS );

    // Save to DB
    global $wpdb;
    $inserted = $wpdb->insert(
        $wpdb->prefix . PAYVAND_TABLE_SUFFIX,
        array_merge( compact('full_name','phone','email','visit_type','consult_type','message','ip'), [
            'created_at' => current_time( 'mysql' ),
        ]),
        ['%s','%s','%s','%s','%s','%s','%s','%s']
    );

    if ( ! $inserted ) {
        wp_send_json_error(['message' => 'خطا در ذخیره اطلاعات. لطفاً دوباره تلاش کنید.']);
    }

    // Send email
    payvand_send_email( $full_name, $phone, $email, $visit_type, $consult_type, $message );

    wp_send_json_success(['message' => 'پیام شما با موفقیت ارسال شد. به زودی با شما تماس خواهیم گرفت.']);
}

// ── Email ─────────────────────────────────────────────────────────────────────
function payvand_send_email( $name, $phone, $email, $visit, $consult, $message ) {
    $to      = get_option('payvand_admin_email', get_option('admin_email'));

    $visit_labels   = ['couple'=>'زوج‌درمانی','marital_conflict'=>'تعارضات زناشویی','infidelity'=>'خیانت','divorce'=>'طلاق','individual'=>'مشاوره فردی','family'=>'مشاوره خانواده','premarital'=>'پیش از ازدواج'];
    $consult_labels = ['in-person'=>'حضوری','online'=>'آنلاین','phone'=>'تلفنی'];

    $visit_fa   = $visit_labels[$visit]   ?? $visit;
    $consult_fa = $consult_labels[$consult] ?? $consult;

    $body = '
<!DOCTYPE html><html dir="rtl" lang="fa">
<head><meta charset="UTF-8">
<style>
  body{font-family:Tahoma,sans-serif;background:#f4f7fb;margin:0;padding:20px;direction:rtl}
  .card{background:#fff;border-radius:12px;padding:30px;max-width:560px;margin:0 auto;box-shadow:0 2px 12px rgba(0,0,0,.08)}
  h2{color:#2d6a8f;margin-top:0;border-bottom:2px solid #e8f4fd;padding-bottom:12px}
  .row{display:flex;gap:8px;margin:10px 0}
  .label{color:#666;font-size:13px;min-width:120px}
  .value{color:#222;font-weight:bold}
  .badge{background:#e8f4fd;color:#2d6a8f;padding:3px 10px;border-radius:20px;font-size:13px}
  .msg-box{background:#f8f9fa;border-right:3px solid #2d6a8f;padding:12px;border-radius:6px;color:#333;margin-top:16px}
  .footer{text-align:center;color:#999;font-size:12px;margin-top:20px}
</style>
</head>
<body>
<div class="card">
  <h2>پیام جدید – پیوند امن</h2>
  <div class="row"><span class="label">نام:</span><span class="value">' . esc_html($name) . '</span></div>
  <div class="row"><span class="label">موبایل:</span><span class="value">' . esc_html($phone) . '</span></div>
  <div class="row"><span class="label">ایمیل:</span><span class="value">' . ( $email ? esc_html($email) : '—' ) . '</span></div>
  <div class="row"><span class="label">نوع مراجعه:</span><span class="badge">' . esc_html($visit_fa) . '</span></div>
  <div class="row"><span class="label">روش مشاوره:</span><span class="badge">' . esc_html($consult_fa) . '</span></div>
  ' . ( $message ? '<div class="msg-box">' . nl2br(esc_html($message)) . '</div>' : '' ) . '
  <p class="footer">دریافت‌شده از سایت پیوند امن | ' . wp_date( 'Y/m/d H:i' ) . '</p>
</div>
</body></html>';

    $from_email = get_option('payvand_admin_email', get_option('admin_email'));
    $headers = [
        'Content-Type: text/html; charset=UTF-8',
        'From: پیوند امن <' . $from_email . '>',
    ];
    if ( $email && is_email( $email ) ) {
        $headers[] = 'Reply-To: ' . $email;
    }
    $subject = '[پیوند امن] پیام جدید از ' . $name;
    $sent = wp_mail( $to, $subject, $body, $headers );

    // BUG-08 fix: log mail failures so admin can investigate
    if ( ! $sent ) {
        error_log( '[Payvand Contact] wp_mail failed – could not send notification to ' . $to );
    }
}

// ── Admin Menu ────────────────────────────────────────────────────────────────
// استایل صفحات ادمین قبلاً اینجا (تابع payvand_admin_styles) به‌صورت
// <style> اینلاین چاپ می‌شد. حالا در assets/css/admin.css است و با
// payvand_admin_enqueue() (پایین‌تر) فقط در صفحات خودمان لود می‌شود.

add_action( 'admin_menu', 'payvand_admin_menu' );
function payvand_admin_menu() {
    global $payvand_admin_page_hooks;
    $payvand_admin_page_hooks = [];

    $payvand_admin_page_hooks[] = add_menu_page(
        'پیوند امن – پیام‌ها',
        'پیوند امن',
        'manage_options',
        'payvand-messages',
        'payvand_messages_page',
        'dashicons-email-alt',
        30
    );
    $payvand_admin_page_hooks[] = add_submenu_page(
        'payvand-messages',
        'تنظیمات',
        'تنظیمات',
        'manage_options',
        'payvand-settings',
        'payvand_settings_page'
    );
}

add_action( 'admin_enqueue_scripts', 'payvand_admin_enqueue' );
function payvand_admin_enqueue( $hook ) {
    global $payvand_admin_page_hooks;

    if ( empty( $payvand_admin_page_hooks ) || ! in_array( $hook, $payvand_admin_page_hooks, true ) ) {
        return;
    }

    wp_enqueue_style( 'payvand-admin-css', PAYVAND_URL . 'assets/css/admin.css', class_exists( 'Pas_Core_Tokens' ) ? [ Pas_Core_Tokens::handle() ] : [], PAYVAND_VERSION );
}

// ── Admin actions (قبل از چاپ خروجی) ─────────────────────────────────────────
// قبلاً تغییر وضعیت/حذف داخل خود callback صفحه اجرا می‌شد؛ آن موقع هدر ادمین
// چاپ شده و wp_redirect با «headers already sent» شکست می‌خورد. حالا روی
// admin_init (قبل از هر خروجی) اجرا می‌شود.
add_action( 'admin_init', 'payvand_admin_actions' );
function payvand_admin_actions() {
    if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) {
        return;
    }
    $page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';

    if ( 'payvand-messages' === $page && isset( $_GET['action'], $_GET['id'] ) ) {
        check_admin_referer( 'payvand_action' );
        global $wpdb;
        $table  = $wpdb->prefix . PAYVAND_TABLE_SUFFIX;
        $id     = absint( $_GET['id'] );
        $action = sanitize_key( wp_unslash( $_GET['action'] ) );
        if ( $id && in_array( $action, [ 'new', 'reviewing', 'answered', 'delete' ], true ) ) {
            if ( 'delete' === $action ) {
                if ( isset( $_GET['confirmed'] ) && '1' === $_GET['confirmed'] ) {
                    $wpdb->delete( $table, [ 'id' => $id ], [ '%d' ] );
                } else {
                    // کلیک اول: صفحه‌ی تأیید (nonce سمت سرور دوباره ساخته می‌شود)
                    wp_safe_redirect( admin_url( 'admin.php?page=payvand-messages&delete_confirm=' . $id ) );
                    exit;
                }
            } else {
                $wpdb->update( $table, [ 'status' => $action ], [ 'id' => $id ], [ '%s' ], [ '%d' ] );
            }
        }
        wp_safe_redirect( admin_url( 'admin.php?page=payvand-messages&updated=1' ) );
        exit;
    }

    if ( 'payvand-settings' === $page && isset( $_GET['reset_color'] ) ) {
        check_admin_referer( 'payvand_reset_color' );
        update_option( 'payvand_color_override', '' );
        wp_safe_redirect( admin_url( 'admin.php?page=payvand-settings&updated=1' ) );
        exit;
    }
}

// ── Admin: Messages Page ──────────────────────────────────────────────────────
function payvand_messages_page() {
    // BUG-02: capability guard
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'شما اجازه دسترسی به این صفحه را ندارید.', 'payvand-contact' ) );
    }

    global $wpdb;
    $table = $wpdb->prefix . PAYVAND_TABLE_SUFFIX; // trusted constant – no esc_sql needed

    // BUG-15: whitelist status filter
    $allowed_statuses = [ 'new', 'reviewing', 'answered' ];
    $raw_filter       = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
    $status_filter    = in_array( $raw_filter, $allowed_statuses, true ) ? $raw_filter : '';

    // BUG-01: use fully prepared queries – never interpolate $where into a raw string
    $per_page = 30;
    $paged    = max( 1, absint( $_GET['paged'] ?? 1 ) );
    $offset   = ( $paged - 1 ) * $per_page;
    if ( $status_filter ) {
        $total = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM `{$table}` WHERE status = %s", $status_filter ) );
        $rows  = $wpdb->get_results(
            $wpdb->prepare( "SELECT * FROM `{$table}` WHERE status = %s ORDER BY created_at DESC, id DESC LIMIT %d OFFSET %d", $status_filter, $per_page, $offset )
        );
    } else {
        $total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$table}`" );
        $rows  = $wpdb->get_results(
            $wpdb->prepare( "SELECT * FROM `{$table}` ORDER BY created_at DESC, id DESC LIMIT %d OFFSET %d", $per_page, $offset )
        );
    }
    $counts = $wpdb->get_results( "SELECT status, COUNT(*) as cnt FROM `{$table}` GROUP BY status" );
    $cnt   = array_column($counts, 'cnt', 'status');

    $status_labels  = ['new'=>'جدید','reviewing'=>'در بررسی','answered'=>'پاسخ داده شده'];
    $visit_labels   = ['couple'=>'زوج‌درمانی','marital_conflict'=>'تعارضات زناشویی','infidelity'=>'خیانت','divorce'=>'طلاق','individual'=>'فردی','family'=>'خانواده','premarital'=>'پیش از ازدواج'];
    $consult_labels = ['in-person'=>'حضوری','online'=>'آنلاین','phone'=>'تلفنی'];
    ?>
    <div class="wrap payvand-admin" dir="rtl">
        <h1>
            <?php echo payvand_icon('inbox', 22); ?> پیام‌های دریافتی – پیوند امن
            <span class="payvand-badge-new"><?php echo (int)($cnt['new']??0); ?> جدید</span>
        </h1>

        <?php if ( isset($_GET['updated']) ) echo '<div class="notice notice-success is-dismissible"><p>عملیات با موفقیت انجام شد.</p></div>'; ?>

        <?php
        // FIX: regenerate the confirm URL server-side; never trust a URL passed through GET params.
        if ( isset($_GET['delete_confirm']) ) {
            $confirm_id  = absint( $_GET['delete_confirm'] );
            if ( $confirm_id > 0 ) {
            $confirm_url = wp_nonce_url(
                admin_url( 'admin.php?page=payvand-messages&action=delete&id=' . $confirm_id . '&confirmed=1' ),
                'payvand_action'
            );
            $cancel_url  = esc_url( admin_url('admin.php?page=payvand-messages') );
            echo '<div class="notice notice-warning" style="padding:14px 16px;display:flex;align-items:center;gap:16px;flex-wrap:wrap">'
                . '<span>' . payvand_icon('alert', 18) . ' آیا از حذف پیام شماره <strong>' . $confirm_id . '</strong> مطمئن هستید؟ این عمل غیرقابل بازگشت است.</span>'
                . '<a href="' . esc_url( $confirm_url ) . '" class="button button-primary" style="background:#d63638;border-color:#d63638">بله، حذف کن</a>'
                . '<a href="' . $cancel_url . '" class="button">انصراف</a>'
                . '</div>';
            } // end if $confirm_id > 0
        }
        ?>

        <ul class="subsubsub" style="margin-bottom:12px">
            <li><a href="?page=payvand-messages" <?php if(!$status_filter) echo 'class="current"'; ?>>همه (<?php echo (int) array_sum( array_map( 'intval', $cnt ) ); ?>)</a> |</li>
            <?php foreach($status_labels as $s=>$l): ?>
            <li><a href="?page=payvand-messages&status=<?php echo esc_attr( $s ); ?>" <?php if($status_filter===$s) echo 'class="current"'; ?>><?php echo esc_html( $l ); ?> (<?php echo (int)($cnt[$s]??0); ?>)</a><?php echo $s!=='answered'?' |':''; ?></li>
            <?php endforeach; ?>
        </ul>

        <table class="wp-list-table widefat fixed striped" style="direction:rtl">
            <thead>
                <tr>
                    <th width="30">#</th>
                    <th>نام</th>
                    <th>موبایل</th>
                    <th>نوع مراجعه</th>
                    <th>روش</th>
                    <th>پیام</th>
                    <th>وضعیت</th>
                    <th>تاریخ</th>
                    <th>عملیات</th>
                </tr>
            </thead>
            <tbody>
            <?php if ( empty($rows) ): ?>
                <tr><td colspan="9" style="text-align:center;padding:30px;color:#888">پیامی یافت نشد.</td></tr>
            <?php else:
                $nonce_url = wp_nonce_url(admin_url('admin.php?page=payvand-messages'), 'payvand_action');
                foreach ( $rows as $row ):
                $row_style = $row->status === 'new' ? 'background:#fff8e1;font-weight:bold' : '';
            ?>
                <tr style="<?php echo esc_attr( $row_style ); ?>">
                    <td><?php echo (int)$row->id; ?></td>
                    <td><?php echo esc_html($row->full_name); ?></td>
                    <td style="direction:ltr"><?php echo esc_html($row->phone); ?></td>
                    <td><span class="payvand-tag"><?php echo esc_html($visit_labels[$row->visit_type]??$row->visit_type); ?></span></td>
                    <td><?php echo esc_html($consult_labels[$row->consult_type]??$row->consult_type); ?></td>
                    <td style="max-width:200px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis" title="<?php echo esc_attr($row->message); ?>">
                        <?php echo esc_html(mb_substr($row->message,0,60)); ?>
                    </td>
                    <td>
                        <select onchange="payvandChangeStatus(<?php echo (int)$row->id; ?>,this.value)" style="font-family:Tahoma;font-size:12px">
                            <?php foreach($status_labels as $s=>$l): ?>
                            <option value="<?php echo esc_attr( $s ); ?>" <?php selected($row->status,$s); ?>><?php echo esc_html( $l ); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                    <td style="direction:ltr;white-space:nowrap"><?php echo esc_html( $row->created_at ); ?></td>
                    <td>
                        <a href="<?php echo esc_url( $nonce_url . '&action=delete&id=' . (int) $row->id ); ?>" 
                           style="color:red">حذف</a>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
        <?php
        $total_pages = (int) ceil( $total / $per_page );
        if ( $total_pages > 1 ) {
            $links = paginate_links( [
                'base'      => add_query_arg( 'paged', '%#%', admin_url( 'admin.php?page=payvand-messages' . ( $status_filter ? '&status=' . $status_filter : '' ) ) ),
                'format'    => '',
                'current'   => $paged,
                'total'     => $total_pages,
                'prev_text' => '›',
                'next_text' => '‹',
            ] );
            echo '<div class="tablenav"><div class="tablenav-pages">' . wp_kses_post( $links ) . '</div></div>';
        }
        ?>
    </div>

    <script>
    function payvandChangeStatus(id,status){
        const base='<?php echo esc_js( admin_url("admin.php?page=payvand-messages") ); ?>';
        const nonce='<?php echo esc_js( wp_create_nonce("payvand_action") ); ?>';
        window.location=base+'&action='+status+'&id='+id+'&_wpnonce='+nonce;
    }
    </script>
    <?php
}

// ── Admin: Settings Page ──────────────────────────────────────────────────────
function payvand_settings_page() {
    // BUG-03: capability guard
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'شما اجازه دسترسی به این صفحه را ندارید.', 'payvand-contact' ) );
    }

    if ( isset($_POST['payvand_save_settings']) && check_admin_referer('payvand_settings') ) {
        $new_email = sanitize_email( wp_unslash( $_POST['admin_email'] ?? '' ) );
        update_option('payvand_admin_email',       is_email( $new_email ) ? $new_email : get_option('admin_email'));
        update_option('payvand_rate_limit',        min( 20, max( 1, absint( $_POST['rate_limit'] ?? 3 ) ) ));
        update_option('payvand_color_override',    (string) sanitize_hex_color( wp_unslash( $_POST['color_override'] ?? '' ) ));
        update_option('payvand_force_load_assets', ! empty($_POST['force_load_assets']) ? 1 : 0 );
        echo '<div class="notice notice-success"><p>تنظیمات ذخیره شد.</p></div>';
    }

    // ── Mail Test ──
    $mail_test_result = null;
    if ( isset($_POST['payvand_test_mail']) && check_admin_referer('payvand_test_mail') ) {
        $test_to      = get_option('payvand_admin_email', get_option('admin_email'));
        $test_subject = '[پیوند امن] تست ارسال ایمیل';
        $test_body    = '<div dir="rtl" style="font-family:Tahoma,sans-serif;padding:20px">'
                      . '<h3 style="color:#2d6a8f">&#x2705; ایمیل تست با موفقیت دریافت شد</h3>'
                      . '<p>این ایمیل از پلاگین <strong>پیوند امن — فرم ارتباط سریع</strong> ارسال شده است.</p>'
                      . '<p>زمان ارسال: <strong>' . wp_date('Y/m/d H:i:s') . '</strong></p>'
                      . '<p>ارسال‌شده به: <strong>' . esc_html($test_to) . '</strong></p>'
                      . '<hr style="border:none;border-top:1px solid #eee">'
                      . '<p style="color:#999;font-size:12px">اگر این ایمیل را دریافت کردید، تنظیمات ایمیل سایت شما درست است.</p>'
                      . '</div>';
        $test_headers = ['Content-Type: text/html; charset=UTF-8'];

        // Capture phpmailer error if available
        add_action('wp_mail_failed', function($error) use (&$mail_test_result, $test_to) {
            $mail_test_result = ['success' => false, 'error' => $error->get_error_message(), 'to' => $test_to];
        });

        $sent = wp_mail( $test_to, $test_subject, $test_body, $test_headers );

        if ( $sent ) {
            $mail_test_result = ['success' => true, 'to' => $test_to];
        } elseif ( $mail_test_result === null ) {
            $mail_test_result = ['success' => false, 'error' => 'wp_mail بازگشت false داد — SMTP تنظیم نشده است.', 'to' => $test_to];
        }
    }

    $detected = payvand_get_theme_colors();
    $override = get_option('payvand_color_override', '');
    ?>
    <div class="wrap payvand-admin" dir="rtl">
        <h1><?php echo payvand_icon('settings', 22); ?> تنظیمات پیوند امن</h1>
        <?php if ( isset( $_GET['updated'] ) ) echo '<div class="notice notice-success is-dismissible"><p>عملیات با موفقیت انجام شد.</p></div>'; ?>
        <form method="post">
            <?php wp_nonce_field('payvand_settings'); ?>
            <table class="form-table">
                <tr>
                    <th><label for="admin_email">ایمیل دریافت پیام‌ها</label></th>
                    <td>
                        <input type="email" id="admin_email" name="admin_email" 
                               value="<?php echo esc_attr(get_option('payvand_admin_email')); ?>"
                               class="regular-text">
                        <p class="description">پیام‌های دریافتی به این ایمیل ارسال می‌شود.</p>
                    </td>
                </tr>
                <tr>
                    <th><label for="rate_limit">حداکثر ارسال در ساعت (هر IP)</label></th>
                    <td>
                        <input type="number" id="rate_limit" name="rate_limit" min="1" max="20"
                               value="<?php echo esc_attr(get_option('payvand_rate_limit',3)); ?>"
                               style="width:80px">
                    </td>
                </tr>
                <tr>
                    <th>رنگ شناسایی‌شده از تم</th>
                    <td>
                        <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap">
                            <span style="display:inline-flex;align-items:center;gap:6px">
                                <span style="width:28px;height:28px;border-radius:6px;background:<?php echo esc_attr($detected['primary']); ?>;display:inline-block;border:1px solid #ccc"></span>
                                رنگ اصلی: <code><?php echo esc_html($detected['primary']); ?></code>
                            </span>
                            <span style="display:inline-flex;align-items:center;gap:6px">
                                <span style="width:28px;height:28px;border-radius:6px;background:<?php echo esc_attr($detected['secondary']); ?>;display:inline-block;border:1px solid #ccc"></span>
                                رنگ ثانویه: <code><?php echo esc_html($detected['secondary']); ?></code>
                            </span>
                        </div>
                        <p class="description">این رنگ‌ها به صورت خودکار از تم فعال سایت خوانده می‌شوند.</p>
                    </td>
                </tr>
                <tr>
                    <th><label for="color_override">رنگ دستی (اختیاری)</label></th>
                    <td>
                        <div style="display:flex;align-items:center;gap:10px">
                            <input type="color" id="color_override_picker" value="<?php echo esc_attr($override ?: $detected['primary']); ?>"
                                   oninput="document.getElementById('color_override').value=this.value"
                                   style="width:44px;height:34px;padding:2px;cursor:pointer;border-radius:6px">
                            <input type="text" id="color_override" name="color_override"
                                   value="<?php echo esc_attr($override); ?>"
                                   placeholder="مثلاً #3a7bd5 — خالی بگذارید برای خودکار"
                                   style="width:180px"
                                   oninput="this.previousElementSibling.value=this.value">
                            <?php if($override): ?>
                            <a href="<?php echo esc_url( wp_nonce_url( admin_url('admin.php?page=payvand-settings&reset_color=1'), 'payvand_reset_color' ) ); ?>" style="color:#c62828;font-size:12px">× بازنشانی</a>
                            <?php endif; ?>
                        </div>
                        <p class="description">اگر رنگ خودکار با سایت هماهنگ نبود، اینجا رنگ دلخواه را وارد کنید.</p>
                    </td>
                </tr>
                <tr>
                    <th>بارگذاری اجباری استایل‌ها</th>
                    <td>
                        <label>
                            <input type="checkbox" name="force_load_assets" value="1" <?php checked( get_option('payvand_force_load_assets', 0), 1 ); ?>>
                            همیشه استایل‌ها و اسکریپت‌ها را بارگذاری کن
                        </label>
                        <p class="description">اگر فرم داخل یک بلوک قابل‌استفاده مجدد (Reusable Block) یا قالب قرار دارد و استایل‌ها لود نمی‌شوند، این گزینه را فعال کنید.</p>
                    </td>
                </tr>
                <tr>
                    <th>شورت‌کد فرم</th>
                    <td>
                        <code style="font-size:15px;background:#f0f0f0;padding:5px 12px;border-radius:6px">[payvand_contact]</code>
                        <p class="description">این کد را در هر صفحه یا ویجتی که می‌خواهید فرم نمایش داده شود، قرار دهید.</p>
                    </td>
                </tr>
            </table>
            <?php submit_button('ذخیره تنظیمات', 'primary', 'payvand_save_settings'); ?>
        </form>

        <hr style="margin:32px 0">

        <h2><?php echo payvand_icon('mail', 20); ?> تست ارسال ایمیل</h2>
        <p>با این دکمه یک ایمیل آزمایشی به آدرس تنظیم‌شده ارسال می‌شود تا مطمئن شوید ارسال ایمیل کار می‌کند.</p>

        <?php if ( $mail_test_result !== null ): ?>
            <?php if ( $mail_test_result['success'] ): ?>
                <div class="notice notice-success inline" style="margin:12px 0">
                    <p><?php echo payvand_icon('check', 16); ?> ایمیل تست با موفقیت به <strong><?php echo esc_html($mail_test_result['to']); ?></strong> ارسال شد. صندوق ورودی را بررسی کنید.</p>
                </div>
            <?php else: ?>
                <div class="notice notice-error inline" style="margin:12px 0">
                    <p><?php echo payvand_icon('x', 16); ?> ارسال ایمیل ناموفق بود.</p>
                    <?php if ( ! empty($mail_test_result['error']) ): ?>
                        <p><strong>جزئیات خطا:</strong><br>
                        <code style="display:block;background:#f8f8f8;padding:8px;border-radius:4px;direction:ltr;text-align:left;margin-top:6px"><?php echo esc_html($mail_test_result['error']); ?></code></p>
                    <?php endif; ?>
                    <p style="color:#666;font-size:13px">برای رفع مشکل، یک پلاگین SMTP مانند <strong>WP Mail SMTP</strong> یا <strong>FluentSMTP</strong> نصب و پیکربندی کنید.</p>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <form method="post">
            <?php wp_nonce_field('payvand_test_mail'); ?>
            <?php submit_button('ارسال ایمیل تست', 'secondary', 'payvand_test_mail', false); ?>
        </form>

    </div>
    <?php
}
