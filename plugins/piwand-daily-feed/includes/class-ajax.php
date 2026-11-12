<?php
defined( 'ABSPATH' ) || exit;

class PWDF_Ajax {

    public static function init() {
        add_action( 'wp_ajax_pwdf_save_card',        [ __CLASS__, 'save_card' ] );
        add_action( 'wp_ajax_nopriv_pwdf_save_card', [ __CLASS__, 'save_card' ] );
        add_action( 'wp_ajax_pwdf_share_card',        [ __CLASS__, 'share_card' ] );
        add_action( 'wp_ajax_nopriv_pwdf_share_card', [ __CLASS__, 'share_card' ] );
    }

    /**
     * Rate limit: حداکثر 30 درخواست در 60 ثانیه از هر IP
     * BUG-1: در محیط‌های پشت reverse proxy (Nginx, Cloudflare)، REMOTE_ADDR
     * همیشه IP پروکسی است. برای دقت بیشتر می‌توان HTTP_X_FORWARDED_FOR را
     * بررسی کرد، اما چون قابل جعل است به صورت پیش‌فرض غیرفعال است.
     * برای فعال‌سازی: add_filter( 'pwdf_use_forwarded_ip', '__return_true' );
     */
    private static function get_client_ip() {
        if ( class_exists( 'Pas_Core_Helpers' ) ) {
            return \Pas_Core_Helpers::get_client_ip();
        }

        if ( apply_filters( 'pwdf_use_forwarded_ip', false ) ) {
            $forwarded = sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '' ) );
            if ( $forwarded ) {
                $parts = explode( ',', $forwarded );
                return trim( $parts[0] );
            }
        }
        return sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0' ) );
    }

    private static function check_rate_limit( $action ) {
        $ip  = self::get_client_ip();
        $key = 'pwdf_rl_' . md5( $action . $ip );
        $count = (int) get_transient( $key );

        if ( $count >= 30 ) {
            wp_send_json_error( [ 'code' => 'rate_limit' ], 429 );
            exit;
        }

        set_transient( $key, $count + 1, 60 );
    }

    public static function save_card() {
        check_ajax_referer( 'pwdf_ajax', 'nonce' );
        self::check_rate_limit( 'save' );

        // BUG-6: wp_unslash() قبل از absint
        $post_id = absint( wp_unslash( $_POST['post_id'] ?? 0 ) );
        if ( ! $post_id ) {
            wp_send_json_error( 'invalid' );
            return;
        }

        if ( get_post_type( $post_id ) !== 'pwdf_card' ) {
            wp_send_json_error( 'invalid_type' );
            return;
        }

        $saved = get_post_meta( $post_id, '_pwdf_saved', true ) ?: [];
        if ( ! is_array( $saved ) ) $saved = [];

        // BUG-5 + BUG-6: wp_unslash + محدودیت طول 64 کاراکتر
        $session_id = substr( sanitize_text_field( wp_unslash( $_POST['session'] ?? '' ) ), 0, 64 );
        if ( empty( $session_id ) ) {
            wp_send_json_error( 'no_session' );
            return;
        }

        if ( in_array( $session_id, $saved, true ) ) {
            $saved = array_values( array_diff( $saved, [ $session_id ] ) );
            update_post_meta( $post_id, '_pwdf_saved', $saved );
            wp_send_json_success( [ 'saved' => false ] );
        } else {
            $saved[] = $session_id;
            if ( count( $saved ) > 5000 ) {
                $saved = array_slice( $saved, -5000 );
            }
            update_post_meta( $post_id, '_pwdf_saved', $saved );
            wp_send_json_success( [ 'saved' => true ] );
        }
    }

    public static function share_card() {
        check_ajax_referer( 'pwdf_ajax', 'nonce' );
        self::check_rate_limit( 'share' );

        // BUG-6: wp_unslash() قبل از absint
        $post_id = absint( wp_unslash( $_POST['post_id'] ?? 0 ) );
        if ( ! $post_id ) {
            wp_send_json_error( 'invalid' );
            return;
        }

        if ( get_post_type( $post_id ) !== 'pwdf_card' ) {
            wp_send_json_error( 'invalid_type' );
            return;
        }

        $post = get_post( $post_id );
        if ( ! $post || $post->post_status !== 'publish' ) {
            wp_send_json_error( 'not_found' );
            return;
        }

        $text    = wp_strip_all_tags( $post->post_content );
        $excerpt = mb_substr( $text, 0, 200 );
        $url     = home_url( '/' );

        wp_send_json_success( [
            'text' => $excerpt,
            'url'  => $url,
        ] );
    }
}
