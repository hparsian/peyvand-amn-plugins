<?php
/**
 * اجرا فقط موقع «حذف کامل» افزونه از پیشخوان وردپرس (نه غیرفعال‌سازی ساده).
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

// حذف تمام پست‌های نوع pwdf_card (کارت‌های فید روزانه) به‌همراه متای آن‌ها
$posts = get_posts( [
    'post_type'      => 'pwdf_card',
    'post_status'    => 'any',
    'numberposts'    => -1,
    'fields'         => 'ids',
] );

foreach ( $posts as $post_id ) {
    wp_delete_post( $post_id, true );
}

delete_option( 'pwdf_settings' );
