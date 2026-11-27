<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * تبدیل میلادی <-> جلالی — همان الگوریتم تست‌شده‌ی jalaali-js که در
 * پنل مراجعان (ZoojDarman\ClientPortal\Jalali) استفاده می‌شود، اینجا بدون
 * namespace تکرار شده چون آن کلاس داخل namespace اختصاصی آن پلاگین است و
 * مستقیماً از این پلاگین قابل استفاده نیست. عمداً یک کپی خودکفا نگه داشته
 * شده تا این پلاگین بدون وابستگی به فعال‌بودن پنل مراجعان کار کند (فقط به
 * هسته مشترک برای رمزنگاری وابسته است، نه بیشتر).
 *
 * نسخه‌ی ساده‌ی قبلی (Pas_Core_Jalali در هسته مشترک) فقط یک‌طرفه
 * (میلادی→شمسی) بود و از فرمول تقریبی ۳۳ ساله استفاده می‌کرد؛ برای فیلد
 * «تاریخ جلسه» که ادمین باید بتواند تاریخ شمسی را هم *وارد* کند، مسیر
 * برعکس با همین دقت لازم است.
 */
final class Pas_CF_Jalali
{
    private const BREAKS = [
        -61, 9, 38, 199, 426, 686, 756, 818, 1111, 1181, 1210,
        1635, 2060, 2097, 2192, 2262, 2324, 2394, 2456, 3178,
    ];

    /**
     * ورودی: 'Y-m-d H:i:s' میلادی (یا فقط 'Y-m-d'). خروجی: 'Y/m/d H:i' شمسی
     * — برای فیلدهای datetime مثل تاریخ ثبت‌نام، لاگ دسترسی، تاریخ تکمیل
     * آزمون. اگر بخش زمان در ورودی نباشد، فقط تاریخ شمسی برمی‌گردد.
     */
    public static function to_jalali_datetime_string(string $mysql_datetime): string
    {
        $mysql_datetime = trim($mysql_datetime);
        if ($mysql_datetime === '' || str_starts_with($mysql_datetime, '0000-00-00')) {
            return '';
        }

        $parts = explode(' ', $mysql_datetime, 2);
        $date_part = self::to_jalali_string($parts[0]);
        if ($date_part === '') {
            return '';
        }

        if (isset($parts[1])) {
            $time_part = substr($parts[1], 0, 5); // H:i
            return $date_part . ' ' . $time_part;
        }

        return $date_part;
    }

    /** ورودی: 'Y-m-d' میلادی. خروجی: 'Y/m/d' شمسی (یا رشته‌ی خالی اگر نامعتبر باشد). */
    public static function to_jalali_string(string $gregorian_date): string
    {
        $gregorian_date = trim($gregorian_date);
        if ($gregorian_date === '' || $gregorian_date === '0000-00-00'
            || !preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $gregorian_date, $m)) {
            return '';
        }
        [$gy, $gm, $gd] = [(int) $m[1], (int) $m[2], (int) $m[3]];
        [$jy, $jm, $jd] = self::to_jalali($gy, $gm, $gd);

        return sprintf('%04d/%02d/%02d', $jy, $jm, $jd);
    }

    /**
     * ورودی فرم را ادمین فارسی‌زبان با کیبورد فارسی تایپ می‌کند — یعنی
     * ارقام معمولاً فارسی (۰۱۲۳۴۵۶۷۸۹) یا گاهی عربی (٠١٢٣٤٥٦٧٨٩) هستند، نه
     * لاتین. \d در preg_match فقط ارقام لاتین را می‌شناسد، پس بدون این
     * تبدیل هر تاریخ تایپ‌شده با کیبورد فارسی «نامعتبر» رد می‌شد.
     */
    public static function to_english_digits(string $value): string
    {
        $persian = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
        $arabic  = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];
        $english = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];

        return str_replace($arabic, $english, str_replace($persian, $english, $value));
    }

    /**
     * رشته‌ی شمسی به فرمت YYYY/MM/DD یا YYYY-MM-DD را به رشته‌ی میلادی
     * YYYY-MM-DD (فرمت ستون DATE در دیتابیس) تبدیل می‌کند. ورودی نامعتبر →
     * null (باعث رد شدن ثبت/ویرایش در handler می‌شود، نه ذخیره‌ی تاریخ غلط).
     */
    public static function jalali_string_to_gregorian_string(string $jalali_date): ?string
    {
        $jalali_date = self::to_english_digits(trim($jalali_date));
        if (!preg_match('/^(\d{4})[\/\-](\d{1,2})[\/\-](\d{1,2})$/', $jalali_date, $m)) {
            return null;
        }
        [, $jy, $jm, $jd] = array_map('intval', $m);
        if ($jm < 1 || $jm > 12 || $jd < 1 || $jd > 31) {
            return null;
        }
        [$gy, $gm, $gd] = self::to_gregorian($jy, $jm, $jd);

        return sprintf('%04d-%02d-%02d', $gy, $gm, $gd);
    }

    /** @return array{0:int,1:int,2:int} */
    public static function to_jalali(int $gy, int $gm, int $gd): array
    {
        return self::d2j(self::g2d($gy, $gm, $gd));
    }

    /** @return array{0:int,1:int,2:int} میلادی (gy, gm, gd) */
    public static function to_gregorian(int $jy, int $jm, int $jd): array
    {
        return self::d2g(self::j2d($jy, $jm, $jd));
    }

    private static function j2d(int $jy, int $jm, int $jd): int
    {
        $r = self::jal_cal($jy);

        return self::g2d($r['gy'], 3, $r['march']) + ($jm - 1) * 31 - intdiv($jm, 7) * ($jm - 7) + $jd - 1;
    }

    private static function g2d(int $gy, int $gm, int $gd): int
    {
        $d = intdiv(($gy + intdiv($gm - 8, 6) + 100100) * 1461, 4)
            + intdiv(153 * (($gm + 9) % 12) + 2, 5)
            + $gd - 34840408;
        $d = $d - intdiv(intdiv($gy + 100100 + intdiv($gm - 8, 6), 100) * 3, 4) + 752;

        return $d;
    }

    /** @return array{leap:int,gy:int,march:int} */
    private static function jal_cal(int $jy): array
    {
        $breaks = self::BREAKS;
        $bl     = count($breaks);
        $gy     = $jy + 621;
        $leapJ  = -14;
        $jp     = $breaks[0];

        $jump = 0;
        for ($i = 1; $i < $bl; $i++) {
            $jm   = $breaks[$i];
            $jump = $jm - $jp;
            if ($jy < $jm) {
                break;
            }
            $leapJ = $leapJ + intdiv($jump, 33) * 8 + intdiv($jump % 33, 4);
            $jp    = $jm;
        }

        $n = $jy - $jp;
        $leapJ = $leapJ + intdiv($n, 33) * 8 + intdiv(($n % 33) + 3, 4);
        if ($jump % 33 === 4 && $jump - $n === 4) {
            $leapJ++;
        }

        $leapG = intdiv($gy, 4) - intdiv((intdiv($gy, 100) + 1) * 3, 4) - 150;
        $march = 20 + $leapJ - $leapG;

        if ($jump - $n < 6) {
            $n = $n - $jump + intdiv($jump + 4, 33) * 33;
        }
        $leap = ((($n + 1) % 33) - 1) % 4;
        if ($leap === -1) {
            $leap = 4;
        }

        return ['leap' => $leap, 'gy' => $gy, 'march' => $march];
    }

    /** @return array{0:int,1:int,2:int} گرگوری (gy, gm, gd) از عدد ژولین */
    private static function d2g(int $jdn): array
    {
        $j  = 4 * $jdn + 139361631;
        $j  = $j + intdiv(intdiv(4 * $jdn + 183187720, 146097) * 3, 4) * 4 - 3908;
        $i  = intdiv($j % 1461, 4) * 5 + 308;
        $gd = intdiv($i % 153, 5) + 1;
        $gm = (intdiv($i, 153) % 12) + 1;
        $gy = intdiv($j, 1461) - 100100 + intdiv(8 - $gm, 6);

        return [$gy, $gm, $gd];
    }

    /** @return array{0:int,1:int,2:int} */
    private static function d2j(int $jdn): array
    {
        [$gy] = self::d2g($jdn);
        $jy = $gy - 621;
        $r  = self::jal_cal($jy);

        $jdn1f = self::g2d($gy, 3, $r['march']);
        $k     = $jdn - $jdn1f;

        if ($k >= 0) {
            if ($k <= 185) {
                $jm = 1 + intdiv($k, 31);
                $jd = ($k % 31) + 1;

                return [$jy, $jm, $jd];
            }
            $k -= 186;
        } else {
            $jy -= 1;
            $k  += 179;
            if ($r['leap'] === 1) {
                $k++;
            }
        }

        $jm = 7 + intdiv($k, 30);
        $jd = ($k % 30) + 1;

        return [$jy, $jm, $jd];
    }
}
