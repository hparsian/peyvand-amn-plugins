<?php
defined( 'ABSPATH' ) || exit;

class PWDF_Settings {

    const OPT = 'pwdf_settings';

    public static function init() {
        add_action( 'admin_menu',    [ __CLASS__, 'menu' ] );
        add_action( 'admin_init',    [ __CLASS__, 'register' ] );
        add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue' ] );
    }

    public static function defaults() {
        return [
            // نمایش
            'display_mode'       => 'slider',      // slider | feed | grid | masonry
            'card_count'         => 7,
            'order'              => 'newest',       // newest | oldest | random
            'show_days'          => 'all',          // all | today | week | custom
            'custom_days'        => 7,
            // انیمیشن
            'animation'          => 'fade',         // fade | slide | scale | none
            'autoplay'           => true,
            'autoplay_speed'     => 4000,
            // ظاهر
            'card_radius'        => 16,
            'card_shadow'        => true,
            'show_date'          => true,
            'show_type_badge'    => true,
            'show_author'        => true,
            'show_share'         => true,
            'show_save'          => true,
            'today_highlight'    => true,
            // رنگ‌های پیش‌فرض هر نوع
            'colors' => [
                'text'     => '#5B8DB8',
                'quote'    => '#9B8EC4',
                'image'    => '#7EC8A4',
                'exercise' => '#E8956D',
                'question' => '#C4A0A0',
            ],
            // فونت
            'font_family'        => 'Vazirmatn',
            // گرید
            'grid_columns'       => 3,

            // ─── تولید خودکار روزانه (نیازمند افزونه «پیوند امن — تولید محتوا») ───
            'auto_generate_enabled' => false,   // پیش‌فرض خاموش — عمداً opt-in
            'auto_generate_hour'    => 7,        // ساعت اجرای کرون (۰ تا ۲۳، به وقت سایت)
            'auto_generate_mix'     => [ 'quote', 'book' ], // نوع محتوایی که هر روز به‌نوبت تولید می‌شود
            'auto_generate_status'  => 'draft',  // draft همیشه ثابت است؛ اینجا فقط برای شفافیت نگه داشته شده
            // لیست کتاب‌های واقعی برای معرفی — عنوان|نویسنده|یادداشت کوتاه (هر خط یک کتاب)
            // هوش مصنوعی فقط برای بازنویسیِ توضیح تبلیغاتی این کتاب‌های از‌قبل‌تایید‌شده استفاده می‌شود،
            // نه برای اختراع عنوان/نویسنده — تا از اشتباه یا کتاب ساختگی جلوگیری شود.
            'auto_generate_books'   => "زبان‌های پنج‌گانه عشق|گری چاپمن|چگونه محبت را به زبانی بگوییم که همسرتان واقعاً می‌فهمد.\nمحکم در آغوشم بگیر|سو جانسون|هفت گفت‌وگوی صمیمانه برای ساختن پیوندی امن با همسرتان.\nدلبستگی|آمیر لوین و راشل هلر|سبک دلبستگی شما چطور روی انتخاب و روابطتان اثر می‌گذارد.\nهنر عشق ورزیدن|اریش فروم|عشق به‌عنوان یک مهارت قابل یادگیری، نه فقط یک احساس.\nهفت اصل برای موفقیت زناشویی|جان گاتمن|یافته‌های چند دهه پژوهش گاتمن درباره ازدواج‌های پایدار.",
        ];
    }

    public static function get( $key = null ) {
        $opts = get_option( self::OPT, self::defaults() );
        $opts = array_replace_recursive( self::defaults(), $opts );
        if ( $key ) return $opts[ $key ] ?? null;
        return $opts;
    }

    public static function menu() {
        add_submenu_page(
            'edit.php?post_type=pwdf_card',
            'تنظیمات فید',
            '⚙️ تنظیمات',
            'manage_options',
            'pwdf-settings',
            [ __CLASS__, 'page' ]
        );
    }

    public static function register() {
        register_setting( 'pwdf_group', self::OPT, [ 'sanitize_callback' => [ __CLASS__, 'sanitize' ] ] );
    }

    public static function sanitize( $raw ) {
        $d   = self::defaults();
        $out = [];

        $out['display_mode']    = in_array( $raw['display_mode'] ?? '', ['slider','feed','grid','masonry'], true )
                                   ? $raw['display_mode'] : $d['display_mode'];
        $out['card_count']      = max( 1, absint( $raw['card_count'] ?? $d['card_count'] ) );
        $out['order']           = in_array( $raw['order'] ?? '', ['newest','oldest','random'], true )
                                   ? $raw['order'] : $d['order'];
        $out['show_days']       = in_array( $raw['show_days'] ?? '', ['all','today','week','custom'], true )
                                   ? $raw['show_days'] : $d['show_days'];
        $out['custom_days']     = absint( $raw['custom_days'] ?? $d['custom_days'] );
        $out['animation']       = in_array( $raw['animation'] ?? '', ['fade','slide','scale','none'], true )
                                   ? $raw['animation'] : $d['animation'];
        $out['autoplay']        = ! empty( $raw['autoplay'] );
        $out['autoplay_speed']  = absint( $raw['autoplay_speed'] ?? $d['autoplay_speed'] );
        $out['card_radius']     = min( 32, absint( $raw['card_radius'] ?? $d['card_radius'] ) );
        $out['card_shadow']     = ! empty( $raw['card_shadow'] );
        $out['show_date']       = ! empty( $raw['show_date'] );
        $out['show_type_badge'] = ! empty( $raw['show_type_badge'] );
        $out['show_author']     = ! empty( $raw['show_author'] );
        $out['show_share']      = ! empty( $raw['show_share'] );
        $out['show_save']       = ! empty( $raw['show_save'] );
        $out['today_highlight'] = ! empty( $raw['today_highlight'] );
        $out['font_family']     = sanitize_text_field( $raw['font_family'] ?? $d['font_family'] );
        $out['grid_columns']    = in_array( (int)( $raw['grid_columns'] ?? 3 ), [2,3,4], true )
                                   ? (int)$raw['grid_columns'] : 3;

        $colors = [];
        $types  = ['text','quote','image','exercise','question','book'];
        foreach ( $types as $t ) {
            $c = sanitize_hex_color( $raw['colors'][ $t ] ?? '' );
            $colors[ $t ] = $c ?: $d['colors'][ $t ];
        }
        $out['colors'] = $colors;

        // ─── تولید خودکار روزانه ───
        $out['auto_generate_enabled'] = ! empty( $raw['auto_generate_enabled'] );
        $out['auto_generate_hour']    = max( 0, min( 23, absint( $raw['auto_generate_hour'] ?? $d['auto_generate_hour'] ) ) );
        $mix_allowed = [ 'quote', 'book' ];
        $mix_in      = array_map( 'sanitize_text_field', (array) ( $raw['auto_generate_mix'] ?? [] ) );
        $mix_out     = array_values( array_intersect( $mix_allowed, $mix_in ) );
        $out['auto_generate_mix']   = ! empty( $mix_out ) ? $mix_out : $d['auto_generate_mix'];
        $out['auto_generate_status'] = 'draft'; // همیشه ثابت — تایید ادمین همیشه اجباری است، از تنظیمات قابل دور زدن نیست
        $out['auto_generate_books'] = sanitize_textarea_field( $raw['auto_generate_books'] ?? $d['auto_generate_books'] );

        return $out;
    }

    public static function enqueue( $hook ) {
        if ( strpos( $hook, 'pwdf-settings' ) === false ) return;
        wp_enqueue_style( 'pwdf-admin-css', PWDF_URL . 'assets/css/admin.css', class_exists( 'Pas_Core_Tokens' ) ? [ Pas_Core_Tokens::handle() ] : [], PWDF_VERSION );
        wp_enqueue_style( 'wp-color-picker' );
        wp_enqueue_script( 'wp-color-picker' );
        wp_add_inline_script( 'wp-color-picker',
            'jQuery(function($){ $(".pwdf-color").wpColorPicker(); });'
        );
    }

    public static function page() {
        $s = self::get();
        ?>
        <div class="wrap pwdf-settings-wrap" dir="rtl">
        <h1>⚙️ تنظیمات فید روزانه پیوند امن</h1>

        <div class="pwdf-shortcode-box">
            <span class="pwdf-shortcode-label">📋 شورت‌کد نمایش فید:</span>
            <code id="pwdf_shortcode_text">[piwand_feed]</code>
            <button type="button" class="button" id="pwdf_copy_shortcode">کپی</button>
            <span id="pwdf_copy_msg"></span>
        </div>

        <form method="post" action="options.php">
        <?php settings_fields( 'pwdf_group' ); ?>

        <div class="pwdf-tabs">
            <button type="button" class="pwdf-tab active" data-tab="display">🖥️ نمایش</button>
            <button type="button" class="pwdf-tab" data-tab="animation">✨ انیمیشن</button>
            <button type="button" class="pwdf-tab" data-tab="colors">🎨 رنگ‌بندی</button>
            <button type="button" class="pwdf-tab" data-tab="elements">🧩 المان‌ها</button>
            <button type="button" class="pwdf-tab" data-tab="autogen">🤖 تولید خودکار</button>
        </div>

        <!-- TAB: نمایش -->
        <div class="pwdf-tab-content active" id="tab-display">
            <table class="form-table">
            <tr>
                <th>حالت نمایش</th>
                <td>
                <?php foreach ([
                    'slider'  => '🎠 اسلایدر افقی',
                    'feed'    => '📜 فید پیوسته',
                    'grid'    => '▦ گرید کارتی',
                    'masonry' => '🧱 ماسونری',
                ] as $val => $label ) : ?>
                    <label style="margin-left:16px">
                        <input type="radio" name="<?php echo esc_attr(self::OPT); ?>[display_mode]"
                               value="<?php echo esc_attr($val); ?>"
                               <?php checked($s['display_mode'], $val); ?>>
                        <?php echo esc_html($label); ?>
                    </label>
                <?php endforeach; ?>
                </td>
            </tr>
            <tr>
                <th>تعداد کارت نمایشی</th>
                <td>
                    <input type="number" name="<?php echo esc_attr(self::OPT); ?>[card_count]"
                           value="<?php echo esc_attr($s['card_count']); ?>" min="1" max="50" style="width:80px">
                    <span class="description">کارت</span>
                </td>
            </tr>
            <tr>
                <th>ترتیب نمایش</th>
                <td>
                <?php foreach ([
                    'newest' => '⬇️ جدیدترین اول',
                    'oldest' => '⬆️ قدیمی‌ترین اول',
                    'random' => '🔀 تصادفی',
                ] as $val => $label ) : ?>
                    <label style="margin-left:16px">
                        <input type="radio" name="<?php echo esc_attr(self::OPT); ?>[order]"
                               value="<?php echo esc_attr($val); ?>"
                               <?php checked($s['order'], $val); ?>>
                        <?php echo esc_html($label); ?>
                    </label>
                <?php endforeach; ?>
                </td>
            </tr>
            <tr>
                <th>بازه نمایش</th>
                <td>
                <?php foreach ([
                    'today'  => '📅 فقط امروز',
                    'week'   => '🗓️ هفته جاری',
                    'all'    => '📚 همه کارت‌ها',
                    'custom' => '🔢 تعداد دلخواه روز',
                ] as $val => $label ) : ?>
                    <label style="margin-left:16px">
                        <input type="radio" name="<?php echo esc_attr(self::OPT); ?>[show_days]"
                               value="<?php echo esc_attr($val); ?>"
                               <?php checked($s['show_days'], $val); ?>>
                        <?php echo esc_html($label); ?>
                    </label>
                <?php endforeach; ?>
                <br><br>
                <label>تعداد روز (اگر گزینه دلخواه انتخاب شد):
                    <input type="number" name="<?php echo esc_attr(self::OPT); ?>[custom_days]"
                           value="<?php echo esc_attr($s['custom_days']); ?>" min="1" max="365" style="width:80px">
                </label>
                </td>
            </tr>
            <tr>
                <th>ستون‌های گرید</th>
                <td>
                <?php foreach ([2=>'۲ ستون',3=>'۳ ستون',4=>'۴ ستون'] as $val=>$label): ?>
                    <label style="margin-left:12px">
                        <input type="radio" name="<?php echo esc_attr(self::OPT); ?>[grid_columns]"
                               value="<?php echo esc_attr($val); ?>"
                               <?php checked((int)$s['grid_columns'], $val); ?>>
                        <?php echo esc_html($label); ?>
                    </label>
                <?php endforeach; ?>
                <span class="description">(فقط در حالت گرید)</span>
                </td>
            </tr>
            <tr>
                <th>گوشه‌های کارت</th>
                <td>
                    <input type="range" name="<?php echo esc_attr(self::OPT); ?>[card_radius]"
                           value="<?php echo esc_attr($s['card_radius']); ?>"
                           min="0" max="32" id="pwdf_radius_range">
                    <span id="pwdf_radius_val"><?php echo esc_html($s['card_radius']); ?>px</span>
                    <script>
                    document.getElementById('pwdf_radius_range').addEventListener('input',function(){
                        document.getElementById('pwdf_radius_val').textContent = this.value + 'px';
                    });
                    </script>
                </td>
            </tr>
            </table>
        </div>

        <!-- TAB: انیمیشن -->
        <div class="pwdf-tab-content" id="tab-animation">
            <table class="form-table">
            <tr>
                <th>انیمیشن ورود</th>
                <td>
                <?php foreach ([
                    'fade'  => '🌫️ Fade In',
                    'slide' => '⬆️ Slide Up',
                    'scale' => '🔍 Scale Up',
                    'none'  => '⛔ بدون انیمیشن',
                ] as $val => $label ) : ?>
                    <label style="margin-left:16px">
                        <input type="radio" name="<?php echo esc_attr(self::OPT); ?>[animation]"
                               value="<?php echo esc_attr($val); ?>"
                               <?php checked($s['animation'], $val); ?>>
                        <?php echo esc_html($label); ?>
                    </label>
                <?php endforeach; ?>
                </td>
            </tr>
            <tr>
                <th>پخش خودکار اسلایدر</th>
                <td>
                    <label>
                        <input type="checkbox" name="<?php echo esc_attr(self::OPT); ?>[autoplay]" value="1"
                               <?php checked($s['autoplay']); ?>>
                        فعال باشد
                    </label>
                </td>
            </tr>
            <tr>
                <th>سرعت تعویض (میلی‌ثانیه)</th>
                <td>
                    <input type="number" name="<?php echo esc_attr(self::OPT); ?>[autoplay_speed]"
                           value="<?php echo esc_attr($s['autoplay_speed']); ?>"
                           min="1000" max="10000" step="500" style="width:100px">
                    <span class="description">مثلاً ۴۰۰۰ = ۴ ثانیه</span>
                </td>
            </tr>
            </table>
        </div>

        <!-- TAB: رنگ‌بندی -->
        <div class="pwdf-tab-content" id="tab-colors">
            <p class="description" style="margin-bottom:16px">رنگ پیش‌فرض هر نوع محتوا — قابل تغییر برای هر کارت جداگانه هم هست.</p>
            <table class="form-table">
            <?php
            $type_labels = [
                'text'     => '📝 متن',
                'quote'    => '💬 نقل‌قول',
                'image'    => '🖼️ تصویر',
                'exercise' => '✅ تمرین روزانه',
                'question' => '❓ سوال تأملی',
                'book'     => '📚 معرفی کتاب',
            ];
            foreach ( $type_labels as $key => $label ) :
                $c = $s['colors'][ $key ] ?? '#5B8DB8';
            ?>
            <tr>
                <th><?php echo esc_html($label); ?></th>
                <td>
                    <input type="text" class="pwdf-color"
                           name="<?php echo esc_attr(self::OPT); ?>[colors][<?php echo esc_attr($key); ?>]"
                           value="<?php echo esc_attr($c); ?>">
                    <span style="display:inline-block;width:24px;height:24px;border-radius:50%;background:<?php echo esc_attr($c);?>;vertical-align:middle;margin-right:8px;border:1px solid #ccc"></span>
                </td>
            </tr>
            <?php endforeach; ?>
            </table>
        </div>

        <!-- TAB: المان‌ها -->
        <div class="pwdf-tab-content" id="tab-elements">
            <table class="form-table">
            <?php $checks = [
                'card_shadow'     => '🌑 سایه کارت',
                'show_date'       => '📅 نمایش تاریخ',
                'show_type_badge' => '🏷️ نمایش نوع محتوا',
                'show_author'     => '✍️ نمایش نویسنده/منبع',
                'show_share'      => '📤 دکمه اشتراک‌گذاری',
                'show_save'       => '🔖 دکمه ذخیره',
                'today_highlight' => '⭐ برجسته‌سازی کارت‌های امروز',
            ];
            foreach ( $checks as $key => $label ) : ?>
            <tr>
                <th><?php echo esc_html($label); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="<?php echo esc_attr(self::OPT); ?>[<?php echo esc_attr($key); ?>]"
                               value="1" <?php checked( ! empty($s[$key]) ); ?>>
                        فعال
                    </label>
                </td>
            </tr>
            <?php endforeach; ?>
            </table>
        </div>

        <!-- TAB: تولید خودکار -->
        <div class="pwdf-tab-content" id="tab-autogen">
            <?php
            $has_ai = class_exists( 'ACO_AI_Router' );
            if ( ! $has_ai ) : ?>
                <div class="notice notice-warning" style="padding:12px;margin:0 0 16px;">
                    ⚠️ افزونه «پیوند امن — تولید محتوا» فعال نیست. تولید خودکار به آن نیاز دارد
                    (از همان اتصال هوش مصنوعی که برای مقالات استفاده می‌کند، استفاده می‌شود).
                </div>
            <?php endif; ?>

            <p class="description" style="margin-bottom:16px;">
                هر روز، در ساعت تنظیم‌شده، <b>۵ گزینه‌ی پیش‌نویس</b> (متن/جمله یا معرفی کتاب) ساخته
                می‌شود — هیچ‌کدام روی سایت نمایش داده نمی‌شود تا خودتان از «همه کارت‌ها» بهترین را
                انتخاب، در صورت نیاز ویرایش، و روی «انتشار» بزنید. بقیه‌ی گزینه‌ها را می‌توانید نگه
                دارید (برای روزهای بعد) یا حذف کنید. بدون تایید شما، هیچ‌کدام منتشر نمی‌شود.
            </p>

            <table class="form-table">
            <tr>
                <th>فعال باشد؟</th>
                <td>
                    <label>
                        <input type="checkbox" name="<?php echo esc_attr(self::OPT); ?>[auto_generate_enabled]" value="1"
                               <?php checked( $s['auto_generate_enabled'] ); ?> <?php disabled( ! $has_ai ); ?>>
                        تولید خودکار روزانه فعال باشد
                    </label>
                </td>
            </tr>
            <tr>
                <th>ساعت اجرا</th>
                <td>
                    <select name="<?php echo esc_attr(self::OPT); ?>[auto_generate_hour]">
                        <?php for ( $h = 0; $h < 24; $h++ ) : ?>
                            <option value="<?php echo esc_attr($h); ?>" <?php selected( (int) $s['auto_generate_hour'], $h ); ?>>
                                <?php echo esc_html( sprintf( '%02d:00', $h ) ); ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                    <span class="description">به وقت سایت — کارت جدید نزدیک همین ساعت هر روز ساخته می‌شود</span>
                </td>
            </tr>
            <tr>
                <th>نوع محتوا</th>
                <td>
                    <?php foreach ( [ 'quote' => '💬 متن/جمله کوتاه', 'book' => '📚 معرفی کتاب' ] as $val => $label ) : ?>
                        <label style="margin-left:16px">
                            <input type="checkbox" name="<?php echo esc_attr(self::OPT); ?>[auto_generate_mix][]"
                                   value="<?php echo esc_attr($val); ?>"
                                   <?php checked( in_array( $val, (array) $s['auto_generate_mix'], true ) ); ?>>
                            <?php echo esc_html($label); ?>
                        </label>
                    <?php endforeach; ?>
                    <p class="description">اگر هر دو تیک بخورد، هر روز به‌نوبت یکی از این دو ساخته می‌شود.</p>
                </td>
            </tr>
            <tr>
                <th>لیست کتاب‌ها</th>
                <td>
                    <textarea name="<?php echo esc_attr(self::OPT); ?>[auto_generate_books]" rows="6" style="width:100%;max-width:640px;font-family:monospace;" dir="rtl"><?php echo esc_textarea( $s['auto_generate_books'] ); ?></textarea>
                    <p class="description">
                        هر خط یک کتاب، با این فرمت: <code>عنوان|نویسنده|یادداشت کوتاه</code>.
                        فقط کتاب‌های واقعی که خودتان تایید کرده‌اید اینجا باشند —
                        هوش مصنوعی فقط توضیح تبلیغاتی هر کتاب را (بر اساس همین یادداشت) بازنویسی می‌کند،
                        عنوان و نویسنده را خودش اختراع نمی‌کند.
                    </p>
                </td>
            </tr>
            <tr>
                <th>تست سریع</th>
                <td>
                    <?php if ( $has_ai ) : ?>
                        <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=pwdf_generate_now' ), 'pwdf_generate_now' ) ); ?>" class="button">
                            🪄 همین حالا ۵ گزینه پیش‌نویس بساز
                        </a>
                        <span class="description">برای دیدن نتیجه، قبلش تنظیمات را ذخیره کن.</span>
                    <?php else : ?>
                        <button class="button" disabled>🪄 همین حالا ۵ گزینه پیش‌نویس بساز</button>
                    <?php endif; ?>
                </td>
            </tr>
            </table>
        </div>

        <?php submit_button( 'ذخیره تنظیمات', 'primary large', 'submit', true ); ?>
        </form>
        </div>

        <script>
        (function(){
            var tabs    = document.querySelectorAll('.pwdf-settings-wrap .pwdf-tab');
            var panels  = document.querySelectorAll('.pwdf-settings-wrap .pwdf-tab-content');
            tabs.forEach(function(tab){
                tab.addEventListener('click', function(){
                    tabs.forEach(function(t){ t.classList.remove('active'); });
                    panels.forEach(function(p){ p.classList.remove('active'); });
                    tab.classList.add('active');
                    var target = document.getElementById('tab-' + tab.dataset.tab);
                    if (target) target.classList.add('active');
                });
            });

            var copyBtn = document.getElementById('pwdf_copy_shortcode');
            var msg     = document.getElementById('pwdf_copy_msg');
            if (copyBtn) {
                copyBtn.addEventListener('click', function(){
                    var text = document.getElementById('pwdf_shortcode_text').textContent;
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
            }
        })();
        </script>
        <?php
    }
}
