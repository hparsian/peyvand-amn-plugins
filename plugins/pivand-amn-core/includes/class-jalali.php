<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * تبدیل میلادی به شمسی — پیاده‌سازی مشترک، تا هر افزونه نسخه‌ی جدا نسازد.
 * الگوریتم استاندارد جلالی/تقویم ایرانی (بدون وابستگی به افزونه‌ی دیگر).
 */
final class Pas_Core_Jalali
{
    public static function gregorian_to_jalali(int $gy, int $gm, int $gd): array
    {
        $g_days_in_month = [31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];
        $j_days_in_month = [31, 31, 31, 31, 31, 31, 30, 30, 30, 30, 30, 29];

        $gy2 = ($gm > 2) ? ($gy + 1) : $gy;
        $days = 355666 + (365 * $gy) + (int) (($gy2 + 3) / 4) - (int) (($gy2 + 99) / 100) + (int) (($gy2 + 399) / 400) + $gd;
        for ($i = 0; $i < $gm - 1; $i++) {
            $days += $g_days_in_month[$i];
        }
        if ($gm > 2 && (($gy % 4 === 0 && $gy % 100 !== 0) || $gy % 400 === 0)) {
            $days++;
        }

        $jy = -1595 + (33 * (int) ($days / 12053));
        $days %= 12053;
        $jy += 4 * (int) ($days / 1461);
        $days %= 1461;
        if ($days > 365) {
            $jy += (int) (($days - 1) / 365);
            $days = ($days - 1) % 365;
        }

        $jm = 0;
        for ($i = 0; $i < 11 && $days >= $j_days_in_month[$i]; $i++) {
            $days -= $j_days_in_month[$i];
            $jm++;
        }
        $jd = $days + 1;

        return [$jy, $jm + 1, $jd];
    }

    /** ورودی: 'Y-m-d' میلادی. خروجی: رشته‌ی شمسی 'Y/m/d'. */
    public static function to_jalali_string(string $gregorian_date): string
    {
        $parts = explode('-', substr($gregorian_date, 0, 10));
        if (count($parts) !== 3) {
            return $gregorian_date;
        }

        [$jy, $jm, $jd] = self::gregorian_to_jalali((int) $parts[0], (int) $parts[1], (int) $parts[2]);

        return sprintf('%04d/%02d/%02d', $jy, $jm, $jd);
    }
}
