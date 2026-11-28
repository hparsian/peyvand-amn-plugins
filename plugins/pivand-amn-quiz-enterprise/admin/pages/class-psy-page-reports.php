<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Psy_Page_Reports — لیست و نمایش گزارش‌های آزمون
 */
class Psy_Page_Reports {

    use Psy_Pagination;

    public static function render_reports(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'دسترسی غیرمجاز.', 'pivand-amn-quiz-enterprise' ), 403 );
        }
        global $wpdb;

        // نمایش گزارش تکی
        if ( isset( $_GET['attempt'] ) ) {
            $attempt_id = intval( $_GET['attempt'] );
            $attempt    = $wpdb->get_row( $wpdb->prepare(
                "SELECT a.*, i.client_name, i.client_phone AS patient_phone, i.access_token FROM {$wpdb->prefix}psy_attempts a
                 JOIN {$wpdb->prefix}psy_invitations i ON a.invitation_id = i.id WHERE a.id = %d",
                $attempt_id
            ) );

            if ( ! $attempt ) {
                echo '<div class="wrap psy-admin" dir="rtl">';
                echo '<h1>📊 گزارش</h1>';
                echo '<div class="psy-notice-plain is-error">گزارش یافت نشد.</div>';
                echo '<p class="psy-mt-16"><a href="' . esc_url( admin_url('admin.php?page=psy-quiz-reports') ) . '" class="button">← بازگشت</a></p>';
                echo '</div>';
                return;
            }

            $test        = \PsychAssessments\Core\Registry::get( $attempt->quiz_id );

            // BUGFIX: رمزگشایی result_data قبل از json_decode (از طریق تابع مشترک
            // pa_decrypt_json در class-psy-helpers.php). بدون این مرحله، وقتی
            // Pas_Core_Crypto فعال است، result_data رمزنگاری‌شده است و json_decode
            // روی آن null برمی‌گرداند؛ چون render_report() امضای «array $data» دارد،
            // پاس دادن null باعث Fatal TypeError و صفحه سفید می‌شد.
            $result_data = pa_decrypt_json( $attempt->result_data );

            $html = $test ? $test->render_report( $result_data, $attempt ) : '<p>آزمون یافت نشد.</p>';
            $jalali      = pa_to_jalali( $attempt->completed_at, 'Y/m/d ساعت H:i' );

            echo '<div class="wrap psy-admin psy-report-wrap" dir="rtl">';
            echo '<h1>📊 گزارش — ' . esc_html($attempt->client_name) . '</h1>';
            echo '<p class="psy-report-meta">آزمون: <strong>' . esc_html($test ? $test->get_name() : $attempt->quiz_id) . '</strong> | تاریخ: <strong>' . esc_html($jalali) . '</strong></p>';
            echo '<p><a href="' . esc_url( admin_url('admin.php?page=psy-quiz-reports') ) . '" class="button">← بازگشت</a> ';
            echo '<button class="button" onclick="window.print()">🖨️ چاپ گزارش</button> ';
            // BUG-07: بررسی وجود $test قبل از نمایش لینک PDF
            if ( $test && get_option( 'psy_quiz_pdf_available' ) === '1' ) {
                $pdf_url = add_query_arg( [
                    '_wpnonce' => wp_create_nonce( 'psy_pdf_' . $attempt_id ),
                ], rest_url( 'psy/v1/report/' . $attempt_id . '/pdf' ) );
                echo '<a href="' . esc_url( $pdf_url ) . '" class="button button-primary" download>📄 دانلود PDF</a>';
            }
            echo '</p>';
            // توجه: $html از test->render_report() می‌آید که trusted server-side code است.
            // wp_kses_post() نمی‌توان اعمال کرد چون <canvas> و <script> Chart.js را حذف می‌کند.
            // امنیت از طریق: ۱) auth check در ابتدا ۲) prepare() در کوئری ۳) esc_html در render_report
            echo '<div class="psy-report-body">' . $html . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            echo '</div>';
            return;
        }

        // ── Pagination لیست گزارش‌ها ────────────────────────────────────
        $per_page    = 50;
        $paged       = max( 1, intval( $_GET['paged'] ?? 1 ) );
        $offset      = ( $paged - 1 ) * $per_page;
        $total_count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}psy_attempts" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $total_pages = $total_count > 0 ? ceil( $total_count / $per_page ) : 1;

        $attempts = $wpdb->get_results( $wpdb->prepare(
            "SELECT a.*, i.client_name, i.client_phone AS patient_phone
             FROM {$wpdb->prefix}psy_attempts a
             JOIN {$wpdb->prefix}psy_invitations i ON a.invitation_id = i.id
             ORDER BY a.id DESC LIMIT %d OFFSET %d",
            $per_page, $offset
        ) );
        ?>
        <div class="wrap psy-admin" dir="rtl">
            <h1>📊 گزارش‌های آزمون</h1>
            <p class="psy-report-meta">مجموع: <?php echo number_format($total_count); ?> گزارش</p>
            <table class="wp-list-table widefat fixed striped psy-table">
                <thead>
                    <tr>
                        <th>نام مراجع</th>
                        <th>آزمون</th>
                        <th>تاریخ تکمیل</th>
                        <th>عملیات</th>
                    </tr>
                </thead>
                <tbody>
                <?php if ( empty($attempts) ): ?>
                    <tr><td colspan="4"><div class="psy-card-empty">هنوز هیچ آزمونی تکمیل نشده.</div></td></tr>
                <?php endif; ?>
                <?php foreach ( $attempts as $a ):
                    $test   = \PsychAssessments\Core\Registry::get( $a->quiz_id );
                    $tname  = $test ? $test->get_name() : $a->quiz_id;
                    $jalali = pa_to_jalali( $a->completed_at, 'Y/m/d' );
                ?>
                <tr>
                    <td><strong><?php echo esc_html($a->client_name); ?></strong></td>
                    <td><?php echo esc_html($tname); ?></td>
                    <td><?php echo esc_html($jalali); ?></td>
                    <td>
                        <a href="<?php echo esc_url( admin_url('admin.php?page=psy-quiz-reports&attempt='.(int)$a->id) ); ?>" class="button button-primary">📊 مشاهده گزارش</a>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>

            <!-- Pagination گزارش‌ها -->
            <?php self::render_pagination( $paged, $total_pages, [ 'page' => 'psy-quiz-reports' ] ); ?>

        </div>
        <?php
    }

    // ── کارت آماری ──────────────────────────────────────────────────────
}
