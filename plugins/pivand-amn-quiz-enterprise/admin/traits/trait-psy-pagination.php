<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Trait Psy_Pagination
 * sliding-window pagination — مشترک بین صفحات ادمین
 */
trait Psy_Pagination {

    /**
     * رندر pagination
     *
     * @param int    $paged       صفحه فعلی
     * @param int    $total_pages تعداد کل صفحات
     * @param array  $base_args   آرگومان‌های پایه URL (page, filter_status, ...)
     */
    protected static function render_pagination( int $paged, int $total_pages, array $base_args ): void {
        if ( $total_pages <= 1 ) return;

        echo '<div class="psy-pagination">';

        if ( $paged > 1 ) {
            $prev_url = add_query_arg( array_merge( $base_args, [ 'paged' => $paged - 1 ] ), admin_url('admin.php') );
            echo '<a href="' . esc_url($prev_url) . '" class="button">→ قبلی</a>';
        }

        $window = 2;
        $pages_to_show = [];
        for ( $i = 1; $i <= $total_pages; $i++ ) {
            if ( $i === 1 || $i === $total_pages || abs( $i - $paged ) <= $window ) {
                $pages_to_show[] = $i;
            }
        }

        $prev_p = null;
        foreach ( $pages_to_show as $p ) {
            if ( $prev_p !== null && $p - $prev_p > 1 ) {
                echo '<span class="psy-pagination-ellipsis">…</span>';
            }
            $p_url  = add_query_arg( array_merge( $base_args, [ 'paged' => $p ] ), admin_url('admin.php') );
            $active = ( $p === $paged ) ? 'button-primary' : 'button-secondary';
            echo '<a href="' . esc_url($p_url) . '" class="button ' . $active . '">' . $p . '</a>';
            $prev_p = $p;
        }

        if ( $paged < $total_pages ) {
            $next_url = add_query_arg( array_merge( $base_args, [ 'paged' => $paged + 1 ] ), admin_url('admin.php') );
            echo '<a href="' . esc_url($next_url) . '" class="button">بعدی ←</a>';
        }

        echo '<span class="psy-pagination-count">صفحه ' . $paged . ' از ' . $total_pages . '</span>';
        echo '</div>';
    }
}
