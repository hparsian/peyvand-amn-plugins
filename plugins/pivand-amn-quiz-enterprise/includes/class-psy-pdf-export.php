<?php
/**
 * PDF Export — تبدیل گزارش HTML به PDF از طریق mPDF (Pure PHP)
 *
 * نصب mPDF:
 *   composer require mpdf/mpdf
 *   یا دانلود مستقیم از https://github.com/mpdf/mpdf/releases و قرار دادن در libs/mpdf/
 *
 * Endpoint:  GET /wp-json/psy/v1/report/{id}/pdf?_wpnonce=...
 * خروجی:     فایل PDF با Content-Disposition: attachment
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class Psy_Quiz_PDF_Export {

    // ── مسیر autoloader مراجع mPDF ──────────────────────────────────────────
    private static function mpdf_autoloader(): ?string {
        $candidates = [
            // Composer داخل پلاگین
            PSY_DIR . 'vendor/autoload.php',
            // Composer داخل وردپرس (نصب سایت‌وید)
            ABSPATH . 'vendor/autoload.php',
            // نصب دستی standalone
            PSY_DIR . 'libs/mpdf/vendor/autoload.php',
        ];
        foreach ( $candidates as $path ) {
            if ( file_exists( $path ) ) return $path;
        }
        return null;
    }

    public static function is_available(): bool {
        // اگر قبلاً lod شده
        if ( class_exists( '\Mpdf\Mpdf' ) ) return true;
        $loader = self::mpdf_autoloader();
        if ( ! $loader ) return false;
        require_once $loader;
        return class_exists( '\Mpdf\Mpdf' );
    }

    // ── ثبت endpoint ────────────────────────────────────────────────────────
    public static function register_routes(): void {
        register_rest_route( 'psy/v1', '/report/(?P<id>\d+)/pdf', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [ __CLASS__, 'export' ],
            'permission_callback' => function( WP_REST_Request $req ) {
                // ادمین لاگین‌شده همیشه دسترسی دارد
                if ( current_user_can( 'manage_options' ) ) return true;
                // غیر ادمین‌ها باید token داشته باشند (صحت آن در callback بررسی می‌شود)
                return ! empty( $req->get_param( 'token' ) );
            },
            'args'                => [
                'id'    => [ 'required' => true, 'validate_callback' => fn($v) => is_numeric($v) ],
                'token' => [ 'required' => false, 'sanitize_callback' => 'sanitize_text_field' ],
            ],
        ] );
    }

    // ── callback اصلی ────────────────────────────────────────────────────────
    public static function export( WP_REST_Request $req ): void {
        global $wpdb;

        if ( ! self::is_available() ) {
            self::error_response( 'کتابخانه mPDF نصب نشده است. لطفاً راهنمای نصب را مطالعه کنید.', 503 );
        }

        $attempt_id = intval( $req->get_param('id') );
        $token      = sanitize_text_field( $req->get_param('token') ?? '' );

        // ── بارگذاری attempt ──────────────────────────────────────────────
        $attempt = $wpdb->get_row( $wpdb->prepare(
            "SELECT a.*, i.client_name, i.client_phone, i.client_phone AS patient_phone, i.access_token
             FROM {$wpdb->prefix}psy_attempts a
             JOIN {$wpdb->prefix}psy_invitations i ON a.invitation_id = i.id
             WHERE a.id = %d",
            $attempt_id
        ) );

        if ( ! $attempt ) {
            self::error_response( 'گزارش یافت نشد.', 404 );
        }

        // ── تأیید دسترسی ─────────────────────────────────────────────────
        $nonce    = sanitize_text_field( $req->get_header('X-WP-Nonce') ?? $req->get_param('_wpnonce') ?? '' );
        $is_admin = current_user_can( 'manage_options' ) && (
            wp_verify_nonce( $nonce, 'psy_pdf_' . $attempt_id ) ||
            wp_verify_nonce( $nonce, 'wp_rest' )
        );
        if ( ! $is_admin && ( empty( $token ) || ! hash_equals( $attempt->access_token, $token ) ) ) {
            self::error_response( 'دسترسی غیرمجاز.', 403 );
        }

        // ── بارگذاری آزمون و رندر HTML ────────────────────────────────────
        $test = \PsychAssessments\Core\Registry::get( $attempt->quiz_id );
        if ( ! $test ) {
            self::error_response( 'آزمون یافت نشد.', 404 );
        }

        $result_data = pa_decrypt_json( $attempt->result_data );
        $report_html = $test->render_report( $result_data, $attempt );
        $jalali      = function_exists('pa_to_jalali')
            ? pa_to_jalali( $attempt->completed_at, 'Y/m/d' )
            : substr( $attempt->completed_at, 0, 10 );
        $today_jalali = function_exists('pa_to_jalali')
            ? pa_to_jalali( current_time( 'mysql' ), 'Y/m/d' )
            : current_time( 'Y/m/d' );

        // ── تولید PDF ─────────────────────────────────────────────────────
        $pdf = self::html_to_pdf( $report_html, $attempt->client_name, $test->get_name(), $jalali, $today_jalali );

        if ( $pdf === false ) {
            self::error_response( 'خطا در تولید PDF. لطفاً با مدیر سیستم تماس بگیرید.', 500 );
        }

        // ── ارسال فایل ────────────────────────────────────────────────────
        $safe_name = sanitize_title( $attempt->client_name );
        if ( empty( $safe_name ) ) {
            $safe_name = 'client-' . $attempt->invitation_id;
        }
        $filename = sprintf(
            'report-%s-%s-%s.pdf',
            $safe_name,
            $attempt->quiz_id,
            str_replace( '/', '-', $jalali )
        );

        while ( ob_get_level() ) ob_end_clean();
        header( 'Content-Type: application/pdf' );
        header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
        header( 'Content-Length: ' . strlen( $pdf ) );
        header( 'Cache-Control: private, no-cache' );
        echo $pdf;
        exit;
    }

    // ── تولید PDF با mPDF ─────────────────────────────────────────────────────
    private static function html_to_pdf(
        string $report_html,
        string $client_name,
        string $quiz_name,
        string $date,
        string $today_date
    ): string|false {

        // autoload اگر هنوز load نشده
        if ( ! class_exists( '\Mpdf\Mpdf' ) ) {
            $loader = self::mpdf_autoloader();
            if ( $loader ) require_once $loader;
        }

        if ( ! class_exists( '\Mpdf\Mpdf' ) ) return false;

        try {
            // پوشه temp برای mPDF — باید writable باشد
            $tmp_dir = trailingslashit( sys_get_temp_dir() ) . 'mpdf_psy_' . get_current_blog_id() . '/';
            if ( ! is_dir( $tmp_dir ) ) {
                wp_mkdir_p( $tmp_dir );
            }

            $mpdf = new \Mpdf\Mpdf([
                'mode'              => 'utf-8',
                'format'            => 'A4',
                'direction'         => 'rtl',
                'default_font'      => 'dejavusans',   // فونت پیش‌فرض mPDF با یونیکد
                'margin_top'        => 0,
                'margin_bottom'     => 0,
                'margin_left'       => 0,
                'margin_right'      => 0,
                'tempDir'           => $tmp_dir,
                'autoLangToFont'    => true,
                'autoScriptToLang'  => true,
            ]);

            // هدر و فوتر
            $site_name   = esc_html( get_bloginfo('name') );
            $client_safe = esc_html( $client_name );
            $quiz_safe   = esc_html( $quiz_name );
            $date_safe   = esc_html( $date );
            $today_safe  = esc_html( $today_date );

            $mpdf->SetHTMLHeader( self::build_header( $quiz_safe, $client_safe, $date_safe, $site_name ) );
            $mpdf->SetHTMLFooter( self::build_footer( $site_name, $today_safe ) );

            // CSS پایه برای mPDF
            $css = self::get_base_css();

            // HTML بدنه گزارش — mPDF نیازی به ساختار کامل HTML ندارد
            $body_html = '<div class="pdf-body">' . $report_html . '</div>';

            $mpdf->WriteHTML( $css, \Mpdf\HTMLParserMode::HEADER_CSS );
            $mpdf->WriteHTML( $body_html, \Mpdf\HTMLParserMode::HTML_BODY );

            return $mpdf->Output( '', 'S' ); // 'S' = string return

        } catch ( \Throwable $e ) {
            psy_log( 'error', 'pdf_export', 'mPDF error', [ 'error' => $e->getMessage() ] );
            return false;
        }
    }

    // ── HTML هدر صفحه PDF ────────────────────────────────────────────────────
    private static function build_header(
        string $quiz_name,
        string $client_name,
        string $date,
        string $site_name
    ): string {
        return <<<HTML
<style>
    .pdf-head { width: 100%; background: linear-gradient(135deg, #2b6cb0 0%, #553c9a 100%); color: #fff; padding: 14px 20px; font-family: dejavusans, Tahoma, sans-serif; }
    .pdf-head-title { font-size: 16px; font-weight: bold; }
    .pdf-head-sub { font-size: 10px; opacity: 0.8; margin-top: 2px; }
    .pdf-head-meta { font-size: 11px; text-align: left; line-height: 1.8; }
</style>
<table class="pdf-head"><tr>
    <td>
        <div class="pdf-head-title">{$quiz_name}</div>
        <div class="pdf-head-sub">{$site_name}</div>
    </td>
    <td class="pdf-head-meta">
        <div>نام مراجع: <strong>{$client_name}</strong></div>
        <div>تاریخ تکمیل: {$date}</div>
    </td>
</tr></table>
HTML;
    }

    // ── HTML فوتر صفحه PDF ───────────────────────────────────────────────────
    private static function build_footer( string $site_name, string $today_date ): string {
        return <<<HTML
<style>
    .pdf-foot { width: 100%; border-top: 1px solid #e2e8f0; padding: 6px 20px; font-size: 9px; color: #a0aec0; font-family: dejavusans, Tahoma, sans-serif; }
    .pdf-foot-note { text-align: right; }
    .pdf-foot-meta { text-align: left; }
</style>
<table class="pdf-foot"><tr>
    <td class="pdf-foot-note">این گزارش صرفاً جنبه کمک‌تشخیصی دارد و جایگزین ارزیابی بالینی نمی‌شود.</td>
    <td class="pdf-foot-meta">{$site_name} — {$today_date} — صفحه {PAGENO} از {nbpg}</td>
</tr></table>
HTML;
    }

    // ── CSS پایه برای mPDF ───────────────────────────────────────────────────
    private static function get_base_css(): string {
        return <<<CSS
body {
    font-family: dejavusans, Tahoma, Arial, sans-serif;
    direction: rtl;
    font-size: 12px;
    color: #2d3748;
    margin: 0;
    padding: 0;
}
.pdf-body {
    padding: 16px 20px 20px;
}
/* کارت‌ها و ردیف‌ها در mPDF به‌صورت بلوک چیده می‌شوند (بدون flex) */
.psy-report-row,
.psy-report-card,
.psy-report-card--tint,
.psy-report-item,
.psy-clinical__head,
.psy-report-trio {
    display: block;
}
.psy-report-card,
.psy-report-card--tint,
.psy-report-item,
.psy-report-trio__cell {
    margin-bottom: 8px;
}
/* بوم‌های Chart.js در PDF نمایش داده نمی‌شوند */
.psy-report-chart-panel,
.psy-chart-panel {
    display: none !important;
}
/* جدول‌ها */
table {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 12px;
    font-size: 11px;
}
th, td {
    padding: 6px 8px;
    border: 1px solid #e2e8f0;
    text-align: right;
}
thead tr {
    background: #edf2f7;
}
/* progress bar ها */
canvas {
    display: none !important;
}
/* نمودارهای Chart.js در PDF نشان داده نمی‌شوند */
h3 {
    font-size: 13px;
    margin: 16px 0 8px;
}
CSS;
    }

    // ── خروجی خطا ────────────────────────────────────────────────────────────
    private static function error_response( string $msg, int $code ): never {
        while ( ob_get_level() ) ob_end_clean();
        http_response_code( $code );
        header( 'Content-Type: application/json; charset=utf-8' );
        echo wp_json_encode( [ 'error' => $msg ] );
        exit;
    }
}
