<?php
defined( 'ABSPATH' ) || exit;

class PWDF_Widget {

    public static function init() {
        add_action( 'widgets_init', function() {
            register_widget( 'PWDF_Feed_Widget' );
        } );
    }
}

class PWDF_Feed_Widget extends WP_Widget {

    public function __construct() {
        parent::__construct(
            'pwdf_feed_widget',
            'پیوند امن — فید روزانه',
            [ 'description' => 'نمایش فید روزانه کارت‌های روانشناسی' ]
        );
    }

    public function widget( $args, $instance ) {
        echo wp_kses_post( $args['before_widget'] );
        if ( ! empty( $instance['title'] ) ) {
            echo wp_kses_post( $args['before_title'] )
               . esc_html( $instance['title'] )
               . wp_kses_post( $args['after_title'] );
        }
        echo do_shortcode( '[piwand_feed]' );
        echo wp_kses_post( $args['after_widget'] );
    }

    public function form( $instance ) {
        $title = $instance['title'] ?? 'مطالب روزانه';
        ?>
        <p>
            <label for="<?php echo esc_attr( $this->get_field_id('title') ); ?>">عنوان ویجت:</label>
            <input class="widefat" type="text"
                   id="<?php echo esc_attr( $this->get_field_id('title') ); ?>"
                   name="<?php echo esc_attr( $this->get_field_name('title') ); ?>"
                   value="<?php echo esc_attr( $title ); ?>">
        </p>
        <?php
    }

    public function update( $new, $old ) {
        return [ 'title' => sanitize_text_field( $new['title'] ?? '' ) ];
    }
}
