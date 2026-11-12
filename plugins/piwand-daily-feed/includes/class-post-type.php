<?php
defined( 'ABSPATH' ) || exit;

class PWDF_Post_Type {

    const TYPE = 'pwdf_card';

    public static function init() {
        add_action( 'init', [ __CLASS__, 'register' ] );
        add_action( 'add_meta_boxes', [ __CLASS__, 'meta_boxes' ] );
        add_action( 'save_post_' . self::TYPE, [ __CLASS__, 'save_meta' ] );
        add_filter( 'manage_' . self::TYPE . '_posts_columns', [ __CLASS__, 'columns' ] );
        add_action( 'manage_' . self::TYPE . '_posts_custom_column', [ __CLASS__, 'column_content' ], 10, 2 );
        add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_admin' ] );
        add_action( 'admin_notices', [ __CLASS__, 'shortcode_notice' ] );
    }

    public static function shortcode_notice() {
        $screen = get_current_screen();
        if ( ! $screen || $screen->post_type !== self::TYPE || $screen->base !== 'edit' ) return;
        ?>
        <div class="notice notice-info" style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;padding:10px 14px;">
            <span style="font-weight:600">📋 شورت‌کد نمایش فید:</span>
            <code id="pwdf_list_shortcode_text" style="direction:ltr;display:inline-block;background:#f0f0f1;padding:3px 8px;border-radius:4px">[piwand_feed]</code>
            <button type="button" class="button button-small" id="pwdf_list_copy_shortcode">کپی</button>
            <span id="pwdf_list_copy_msg" style="color:#2a9d4a;font-size:12px;font-weight:600"></span>
        </div>
        <script>
        (function(){
            var btn = document.getElementById('pwdf_list_copy_shortcode');
            var msg = document.getElementById('pwdf_list_copy_msg');
            if (!btn) return;
            btn.addEventListener('click', function(){
                var text = document.getElementById('pwdf_list_shortcode_text').textContent;
                var done = function(){
                    msg.textContent = '✅ کپی شد';
                    setTimeout(function(){ msg.textContent = ''; }, 2000);
                };
                if (navigator.clipboard) {
                    navigator.clipboard.writeText(text).then(done).catch(function(){
                        msg.textContent = '⚠️ کپی نشد، دستی کپی کنید';
                    });
                } else {
                    var ta = document.createElement('textarea');
                    ta.value = text;
                    document.body.appendChild(ta);
                    ta.select();
                    document.execCommand('copy');
                    document.body.removeChild(ta);
                    done();
                }
            });
        })();
        </script>
        <?php
    }

    public static function register() {
        register_post_type( self::TYPE, [
            'label'               => 'کارت‌های روزانه',
            'labels'              => [
                'name'          => 'کارت‌های روزانه',
                'singular_name' => 'کارت روزانه',
                'add_new'       => 'افزودن کارت',
                'add_new_item'  => 'افزودن کارت جدید',
                'edit_item'     => 'ویرایش کارت',
                'all_items'     => 'همه کارت‌ها',
                'search_items'  => 'جستجو در کارت‌ها',
                'not_found'     => 'کارتی یافت نشد',
            ],
            'public'              => false,
            'show_ui'             => true,
            'show_in_menu'        => true,
            'menu_position'       => 25,
            'menu_icon'           => 'dashicons-format-status',
            'supports'            => [ 'title', 'editor', 'thumbnail' ],
            'show_in_rest'        => true,
            'capability_type'     => 'post',
            'exclude_from_search' => true,
        ] );
    }

    public static function meta_boxes() {
        add_meta_box(
            'pwdf_card_meta',
            'تنظیمات کارت',
            [ __CLASS__, 'render_meta_box' ],
            self::TYPE,
            'side',
            'high'
        );
    }

    public static function render_meta_box( $post ) {
        wp_nonce_field( 'pwdf_save_meta', 'pwdf_nonce' );
        $type       = get_post_meta( $post->ID, '_pwdf_type',       true ) ?: 'text';
        $raw_color  = get_post_meta( $post->ID, '_pwdf_color',      true );
        $color      = sanitize_hex_color( $raw_color ) ?: '';
        $author_tag = get_post_meta( $post->ID, '_pwdf_author_tag', true ) ?: '';
        $pin_today  = get_post_meta( $post->ID, '_pwdf_pin_today',  true );

        $types = [
            'text'     => '📝 متن',
            'quote'    => '💬 نقل‌قول',
            'image'    => '🖼️ تصویر',
            'exercise' => '✅ تمرین روزانه',
            'question' => '❓ سوال تأملی',
            'book'     => '📚 معرفی کتاب',
        ];

        $default_colors = [
            'text'     => '#5B8DB8',
            'quote'    => '#9B8EC4',
            'image'    => '#7EC8A4',
            'exercise' => '#E8956D',
            'question' => '#C4A0A0',
            'book'     => '#B5854E',
        ];
        ?>

        <div class="pwdf-meta-row">
            <label>نوع محتوا</label>
            <select name="pwdf_type" id="pwdf_type">
                <?php foreach ( $types as $val => $label ) : ?>
                    <option value="<?php echo esc_attr( $val ); ?>" <?php selected( $type, $val ); ?>>
                        <?php echo esc_html( $label ); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="pwdf-meta-row">
            <label>رنگ کارت</label>
            <div class="pwdf-color-row">
                <input type="color" id="pwdf_color_picker" value="<?php echo esc_attr( $color ?: '#5B8DB8' ); ?>">
                <input type="text"  name="pwdf_color" id="pwdf_color" value="<?php echo esc_attr( $color ); ?>" placeholder="خودکار">
            </div>
            <div id="pwdf_default_colors">
                <?php foreach ( $default_colors as $dc ) : ?>
                    <div class="pwdf-swatch" style="background:<?php echo esc_attr( $dc ); ?>" data-color="<?php echo esc_attr( $dc ); ?>"></div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="pwdf-meta-row">
            <label>برچسب نویسنده / منبع</label>
            <input type="text" name="pwdf_author_tag" value="<?php echo esc_attr( $author_tag ); ?>" placeholder="مثلاً: دکتر پیوند امن">
        </div>

        <div class="pwdf-meta-row">
            <label>
                <input type="checkbox" name="pwdf_pin_today" value="1" <?php checked( $pin_today, '1' ); ?>>
                📌 پین به عنوان کارت امروز
            </label>
        </div>

        <script>
        (function(){
            var picker = document.getElementById('pwdf_color_picker');
            var text   = document.getElementById('pwdf_color');
            var swatches = document.querySelectorAll('.pwdf-swatch');
            var typeSelect = document.getElementById('pwdf_type');

            var defaults = <?php echo wp_json_encode( $default_colors ); ?>;

            picker.addEventListener('input', function(){ text.value = picker.value; });
            text.addEventListener('change', function(){ if(text.value) picker.value = text.value; });

            swatches.forEach(function(sw){
                sw.addEventListener('click', function(){
                    var c = sw.dataset.color;
                    picker.value = c;
                    text.value   = c;
                });
            });

            typeSelect.addEventListener('change', function(){
                var def = defaults[typeSelect.value] || '#5B8DB8';
                if(!text.value){ picker.value = def; }
            });
        })();
        </script>
        <?php
    }

    public static function save_meta( $post_id ) {
        if ( ! isset( $_POST['pwdf_nonce'] ) ||
             ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['pwdf_nonce'] ) ), 'pwdf_save_meta' ) ) {
            return;
        }
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
        if ( ! current_user_can( 'edit_post', $post_id ) ) return;

        // اعتبارسنجی نوع محتوا در برابر لیست مجاز
        if ( isset( $_POST['pwdf_type'] ) ) {
            $allowed_types = [ 'text', 'quote', 'image', 'exercise', 'question', 'book' ];
            $type = sanitize_text_field( wp_unslash( $_POST['pwdf_type'] ) );
            update_post_meta( $post_id, '_pwdf_type', in_array( $type, $allowed_types, true ) ? $type : 'text' );
        }

        // BUG-8 FIX: رنگ باید با sanitize_hex_color اعتبارسنجی بشه نه sanitize_text_field
        // (قبلاً هر متن دلخواه به‌عنوان رنگ در دیتابیس ذخیره می‌شد و فقط در نمایش فیلتر می‌شد)
        if ( isset( $_POST['pwdf_color'] ) ) {
            $color = sanitize_hex_color( wp_unslash( $_POST['pwdf_color'] ) );
            update_post_meta( $post_id, '_pwdf_color', $color ?: '' );
        }

        if ( isset( $_POST['pwdf_author_tag'] ) ) {
            update_post_meta( $post_id, '_pwdf_author_tag', sanitize_text_field( wp_unslash( $_POST['pwdf_author_tag'] ) ) );
        }
        $pin = isset( $_POST['pwdf_pin_today'] ) ? '1' : '0';
        update_post_meta( $post_id, '_pwdf_pin_today', $pin );
    }

    public static function columns( $cols ) {
        $new = [];
        foreach ( $cols as $k => $v ) {
            $new[ $k ] = $v;
            if ( $k === 'title' ) {
                $new['pwdf_type']  = 'نوع';
                $new['pwdf_color'] = 'رنگ';
                $new['pwdf_pin']   = 'پین';
            }
        }
        return $new;
    }

    public static function column_content( $col, $post_id ) {
        if ( $col === 'pwdf_type' ) {
            $map = [ 'text'=>'📝 متن','quote'=>'💬 نقل‌قول','image'=>'🖼️ تصویر','exercise'=>'✅ تمرین','question'=>'❓ سوال','book'=>'📚 کتاب' ];
            $t   = get_post_meta( $post_id, '_pwdf_type', true ) ?: 'text';
            echo esc_html( $map[ $t ] ?? $t );
        }
        if ( $col === 'pwdf_color' ) {
            $c = sanitize_hex_color( get_post_meta( $post_id, '_pwdf_color', true ) ) ?: '#5B8DB8';
            // BUG-10 FIX: علامت % خام در رشته‌ی printf باید به‌صورت %% نوشته بشه
            // وگرنه PHP اون رو به‌اشتباه به‌عنوان شروع یه format specifier تفسیر می‌کنه
            // و border-radius:50% در خروجی خراب می‌شه.
            printf(
                '<span style="display:inline-block;width:20px;height:20px;border-radius:50%%;background:%s;border:1px solid #ccc;" aria-hidden="true"></span>',
                esc_attr( $c )
            );
        }
        if ( $col === 'pwdf_pin' ) {
            echo get_post_meta( $post_id, '_pwdf_pin_today', true ) === '1' ? '📌' : '—';
        }
    }

    public static function enqueue_admin( $hook ) {
        if ( ! in_array( $hook, [ 'post.php', 'post-new.php' ], true ) ) return;
        global $post;
        if ( ! $post || $post->post_type !== self::TYPE ) return;
        wp_enqueue_style( 'pwdf-admin-css', PWDF_URL . 'assets/css/admin.css', class_exists( 'Pas_Core_Tokens' ) ? [ Pas_Core_Tokens::handle() ] : [], PWDF_VERSION );
        // color picker built-in via inline script in meta box
    }
}
