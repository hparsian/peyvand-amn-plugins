<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * رمزگشایی (در صورت فعال بودن Pas_Core_Crypto) + json_decode ستون‌های
 * رمزنگاری‌شده‌ی دیتابیس (result_data / answers).
 *
 * این تابع محل واحدی برای این منطق است — قبلاً در هر فایلی که این ستون‌ها را
 * می‌خواند، جداگانه کپی/فراموش می‌شد (مثلاً صفحه‌ی گزارش تکی، مقایسه‌ی زوجین،
 * و مقایسه‌ی پیشرفت، بدون رمزگشایی مستقیم json_decode می‌زدند و وقتی رمزنگاری
 * فعال بود یا null/کرش می‌گرفتند یا داده خالی/نادرست نمایش می‌دادند).
 * همیشه یک آرایه برمی‌گرداند (هرگز null) تا فراخوان مجبور به چک اضافه نباشد.
 */
if ( ! function_exists( 'pa_decrypt_json' ) ) {
    function pa_decrypt_json( ?string $raw ): array {
        if ( $raw === null || $raw === '' ) return [];

        $json = class_exists( 'Pas_Core_Crypto' )
            ? Pas_Core_Crypto::unwrap_decrypt( $raw )
            : $raw;

        $data = json_decode( $json, true );
        return is_array( $data ) ? $data : [];
    }
}

/**
 * تبدیل تاریخ میلادی به شمسی
 */
if ( ! function_exists( 'pa_to_jalali' ) ) {
    function pa_to_jalali( string $datetime, string $format = 'Y/m/d' ): string {
        if ( empty( $datetime ) || $datetime === '0000-00-00 00:00:00' ) return '---';

        // تاریخ‌ها با current_time('mysql') ذخیره می‌شوند — یعنی timezone وردپرس، نه UTC.
        // پس نباید با wp_date() دوباره timezone-shift بخورند.
        // با DateTimeZone وردپرس parse می‌کنیم تا ساعت محلی درست باشد.
        $wp_tz = new DateTimeZone( wp_timezone_string() );
        $dt = DateTime::createFromFormat( 'Y-m-d H:i:s', $datetime, $wp_tz );
        if ( ! $dt ) return $datetime;

        [ $gy, $gm, $gd ] = [ (int)$dt->format('Y'), (int)$dt->format('n'), (int)$dt->format('j') ];

        $gy2 = $gy - 1600; $gm2 = $gm - 1; $gd2 = $gd - 1;
        $g_d_no = 365 * $gy2 + (int)(($gy2 + 3) / 4) - (int)(($gy2 + 99) / 100) + (int)(($gy2 + 399) / 400);
        for ($i = 0; $i < $gm2; $i++) $g_d_no += [31,28,31,30,31,30,31,31,30,31,30,31][$i];
        if ($gm2 > 1 && (($gy % 4 == 0 && $gy % 100 != 0) || $gy % 400 == 0)) $g_d_no++;
        $g_d_no += $gd2;

        $j_d_no = $g_d_no - 79;
        $j_np = (int)($j_d_no / 12053); $j_d_no %= 12053;
        $jy = 979 + 33 * $j_np + 4 * (int)($j_d_no / 1461); $j_d_no %= 1461;
        if ($j_d_no >= 366) { $jy += (int)(($j_d_no - 1) / 365); $j_d_no = ($j_d_no - 1) % 365; }
        for ($i = 0; $i < 11 && $j_d_no >= [31,31,31,31,31,31,30,30,30,30,30][$i]; $i++) $j_d_no -= [31,31,31,31,31,31,30,30,30,30,30][$i];
        $jm = $i + 1; $jd = $j_d_no + 1;

        $out = strtr( $format, [
            'Y' => $jy,
            'y' => substr( (string)$jy, -2 ),
            'm' => str_pad( $jm, 2, '0', STR_PAD_LEFT ),
            'n' => $jm,
            'd' => str_pad( $jd, 2, '0', STR_PAD_LEFT ),
            'j' => $jd,
            'H' => $dt->format( 'H' ),
            'i' => $dt->format( 'i' ),
            's' => $dt->format( 's' ),
            'A' => $dt->format( 'A' ),
        ] );
        return $out;
    }
}
