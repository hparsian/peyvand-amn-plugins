<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * ACO_Search_Console
 *
 * اتصال به Google Search Console API با Service Account (JWT/OAuth2 —
 * بدون نیاز به کتابخانه‌ی خارجی؛ امضای JWT با تابع داخلی openssl_sign انجام می‌شود).
 *
 * دو کاربرد:
 *   ۱. get_opportunity_queries(): کوئری‌های واقعی سایت که الان impression
 *      می‌گیرند ولی رتبه‌شان بین ۱ تا ۵۰ است — یعنی گوگل قبلاً موضوع را
 *      مرتبط تشخیص داده، فقط محتوای بهتر/بیشتر لازم دارد. این کلمات با
 *      امتیاز واقعی (نه حدس AI) به بانک کلمات کلیدی اضافه می‌شوند.
 *   ۲. get_url_traffic(): آمار واقعی کلیک/impression یک URL مشخص — برای
 *      فیدبک ترافیک روی مقاله‌های تولیدشده توسط بانک کلمات کلیدی.
 *
 * نیازمندی‌ها (در تنظیمات): فایل JSON کلید Service Account + site URL
 * دقیقاً همان‌طور که در Search Console ثبت شده (مثلاً 'sc-domain:example.com'
 * یا 'https://example.com/').
 */
class ACO_Search_Console {

    const TOKEN_URL   = 'https://oauth2.googleapis.com/token';
    const API_BASE    = 'https://www.googleapis.com/webmasters/v3/sites/';
    const SCOPE       = 'https://www.googleapis.com/auth/webmasters.readonly';
    const TOKEN_CACHE = 'aco_gsc_access_token';

    // ─────────────────────────────────────────────
    // آیا تنظیمات کامل است؟
    // ─────────────────────────────────────────────
    public static function is_configured(): bool {
        return self::get_service_account() !== null && self::get_site_url() !== '';
    }

    private static function get_site_url(): string {
        return trim( (string) ACO_Settings::get( 'gsc_site_url' ) );
    }

    private static function get_service_account(): ?array {
        $raw = (string) ACO_Settings::get( 'gsc_service_account_json' );
        $raw = trim( $raw );
        if ( $raw === '' ) {
            return null;
        }
        if ( class_exists( 'Pas_Core_Crypto' ) ) {
            $raw = Pas_Core_Crypto::unwrap_decrypt( $raw );
        }
        $data = json_decode( $raw, true );
        if ( json_last_error() !== JSON_ERROR_NONE || empty( $data['client_email'] ) || empty( $data['private_key'] ) ) {
            return null;
        }
        return $data;
    }

    private static function base64url_encode( string $data ): string {
        return rtrim( strtr( base64_encode( $data ), '+/', '-_' ), '=' );
    }

    // ─────────────────────────────────────────────
    // ساخت JWT و دریافت access token (کش‌شده تا ۵۰ دقیقه)
    // ─────────────────────────────────────────────
    private static function get_access_token(): ?string {
        $cached = get_transient( self::TOKEN_CACHE );
        if ( $cached ) {
            return (string) $cached;
        }

        if ( ! function_exists( 'openssl_sign' ) ) {
            ACO_Logger::error( 'افزونه openssl در PHP سرور فعال نیست — اتصال به Search Console ممکن نیست.', 'gsc' );
            return null;
        }

        $sa = self::get_service_account();
        if ( ! $sa ) {
            return null;
        }

        $now = time();
        $header  = self::base64url_encode( wp_json_encode( [ 'alg' => 'RS256', 'typ' => 'JWT' ] ) );
        $claims  = self::base64url_encode( wp_json_encode( [
            'iss'   => $sa['client_email'],
            'scope' => self::SCOPE,
            'aud'   => self::TOKEN_URL,
            'exp'   => $now + 3600,
            'iat'   => $now,
        ] ) );

        $signing_input = $header . '.' . $claims;
        $signature     = '';
        $ok = openssl_sign( $signing_input, $signature, $sa['private_key'], 'sha256WithRSAEncryption' );
        if ( ! $ok ) {
            ACO_Logger::error( 'امضای JWT برای Search Console ناموفق بود — کلید private_key را بررسی کنید.', 'gsc' );
            return null;
        }

        $jwt = $signing_input . '.' . self::base64url_encode( $signature );

        $response = wp_safe_remote_post( self::TOKEN_URL, [
            'timeout' => 30,
            'body'    => [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion'  => $jwt,
            ],
        ] );

        if ( is_wp_error( $response ) ) {
            ACO_Logger::error( 'خطا در دریافت توکن Search Console: ' . $response->get_error_message(), 'gsc' );
            return null;
        }

        $data = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( empty( $data['access_token'] ) ) {
            ACO_Logger::error( 'پاسخ توکن Search Console نامعتبر بود: ' . ( $data['error_description'] ?? wp_remote_retrieve_body( $response ) ), 'gsc' );
            return null;
        }

        $ttl = max( 60, (int) ( $data['expires_in'] ?? 3600 ) - 300 ); // ۵ دقیقه حاشیه اطمینان
        set_transient( self::TOKEN_CACHE, $data['access_token'], $ttl );

        return (string) $data['access_token'];
    }

    // ─────────────────────────────────────────────
    // فراخوانی عمومی searchAnalytics.query
    // ─────────────────────────────────────────────
    private static function query( array $body ): ?array {
        $token = self::get_access_token();
        $site  = self::get_site_url();
        if ( ! $token || ! $site ) {
            return null;
        }

        $url = self::API_BASE . rawurlencode( $site ) . '/searchAnalytics/query';

        $response = wp_safe_remote_post( $url, [
            'timeout' => 30,
            'headers' => [
                'Content-Type'  => 'application/json',
                'Authorization' => 'Bearer ' . $token,
            ],
            'body' => wp_json_encode( $body ),
        ] );

        if ( is_wp_error( $response ) ) {
            ACO_Logger::error( 'خطا در فراخوانی Search Console API: ' . $response->get_error_message(), 'gsc' );
            return null;
        }

        $code = (int) wp_remote_retrieve_response_code( $response );
        $data = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( $code !== 200 ) {
            $raw_body     = wp_remote_retrieve_body( $response );
            $error_detail = $data['error']['message'] ?? '';
            if ( $error_detail === '' ) {
                $error_detail = $raw_body !== '' ? $raw_body : '(بدنه‌ی پاسخ خالی بود)';
            }
            ACO_Logger::error( 'Search Console API خطای ' . $code . ': ' . $error_detail, 'gsc' );
            return null;
        }

        return $data;
    }

    // ─────────────────────────────────────────────
    // کوئری‌های «فرصت» — impression دارند، رتبه ۱ تا ۵۰ (نزدیک صفحه اول ولی نه دقیقاً)
    // ─────────────────────────────────────────────
    public static function get_opportunity_queries( int $days = 90, int $limit = 20 ): array {
        $end   = gmdate( 'Y-m-d', strtotime( '-3 days' ) ); // داده GSC معمولاً ۲-۳ روز تأخیر دارد
        $start = gmdate( 'Y-m-d', strtotime( "-{$days} days", strtotime( $end ) ) );

        $data = self::query( [
            'startDate'  => $start,
            'endDate'    => $end,
            'dimensions' => [ 'query' ],
            'rowLimit'   => 250,
        ] );

        if ( ! $data || empty( $data['rows'] ) ) {
            ACO_Logger::info( '🔍 Search Console: API با موفقیت پاسخ داد ولی هیچ ردیفی برنگرداند (بازه: ' . $start . ' تا ' . $end . ').', 'gsc' );
            return [];
        }

        $raw_count = count( $data['rows'] );

        $opportunities = [];
        foreach ( $data['rows'] as $row ) {
            $position    = (float) ( $row['position']    ?? 100 );
            $impressions = (int)   ( $row['impressions'] ?? 0 );
            $clicks      = (int)   ( $row['clicks']      ?? 0 );
            $query_text  = $row['keys'][0] ?? '';

            if ( $query_text === '' || $impressions < 5 ) continue;
            if ( $position < 1 || $position > 50 ) continue; // فقط بازه‌ی فرصت واقعی

            $opportunities[] = [
                'keyword'     => $query_text,
                'position'    => round( $position, 1 ),
                'impressions' => $impressions,
                'clicks'      => $clicks,
                // امتیاز واقعی: impression بالا + رتبه نزدیک‌تر به ۱۰ = اولویت بیشتر
                'score'       => max( 1, min( 100, (int) round( ( $impressions / 10 ) + ( 20 - $position ) * 2 ) ) ),
            ];
        }

        ACO_Logger::info( "🔍 Search Console: {$raw_count} ردیف خام از API دریافت شد، " . count( $opportunities ) . ' مورد بعد از فیلتر impressions/position باقی ماند (بازه: ' . $start . ' تا ' . $end . ').', 'gsc' );

        usort( $opportunities, function( $a, $b ) { return $b['score'] <=> $a['score']; } );

        return array_slice( $opportunities, 0, max( 1, $limit ) );
    }

    // ─────────────────────────────────────────────
    // آمار واقعی کلیک/impression یک URL مشخص (برای فیدبک ترافیک)
    // ─────────────────────────────────────────────
    public static function get_url_traffic( string $url, int $days = 28 ): ?array {
        $end   = gmdate( 'Y-m-d', strtotime( '-3 days' ) );
        $start = gmdate( 'Y-m-d', strtotime( "-{$days} days", strtotime( $end ) ) );

        $data = self::query( [
            'startDate'  => $start,
            'endDate'    => $end,
            'dimensions' => [ 'page' ],
            'dimensionFilterGroups' => [
                [ 'filters' => [
                    [ 'dimension' => 'page', 'operator' => 'equals', 'expression' => $url ],
                ] ],
            ],
            'rowLimit' => 1,
        ] );

        if ( ! $data || empty( $data['rows'][0] ) ) {
            return [ 'clicks' => 0, 'impressions' => 0, 'position' => null ];
        }

        $row = $data['rows'][0];
        return [
            'clicks'      => (int) ( $row['clicks'] ?? 0 ),
            'impressions' => (int) ( $row['impressions'] ?? 0 ),
            'position'    => isset( $row['position'] ) ? round( (float) $row['position'], 1 ) : null,
        ];
    }
}
