<?php

declare(strict_types=1);

namespace ZoojDarman\ClientPortal;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * تبدیل میلادی <-> جلالی، پورت دقیق الگوریتم jalaali-js (Kazimierz
 * Borkowski / roozbehp) که در بسیاری از سایت‌های فارسی استفاده می‌شود.
 *
 * نسخه‌ی قبلی این کلاس (فرمول ساده‌ی div/mod بدون چرخه‌ی ۲۸۲۰ ساله) اشتباه
 * بود — مثلاً ۲۰۲۴-۰۳-۲۰ (نوروز ۱۴۰۳) را ۱۴۰۲/۱۲/۲ نشان می‌داد، یعنی نزدیک
 * یک سال و یک ماه جابه‌جا! این نسخه با چند تاریخ مرجع شناخته‌شده تست و تایید
 * شده (نوروز ۱۳۸۰، ۲۲ بهمن ۱۳۵۷، یلدای ۱۴۰۴).
 *
 * نکته‌ی مهم پیاده‌سازی: تابع div در الگوریتم اصلی جاوااسکریپت به‌صورت
 * truncate-toward-zero عمل می‌کند (~~(a/b))، دقیقاً همان رفتار intdiv() در
 * PHP — پس همه‌جا از intdiv() استفاده شده، نه floor division.
 */
final class Jalali
{
    private const BREAKS = [
        -61, 9, 38, 199, 426, 686, 756, 818, 1111, 1181, 1210,
        1635, 2060, 2097, 2192, 2262, 2324, 2394, 2456, 3178,
    ];

    public static function gregorian_to_jalali_string(string $gregorian_date): string
    {
        // قبلاً فقط empty/'0000-00-00' چک می‌شد؛ یک رشته‌ی ناقص/بدفرمت (مثلاً
        // بدون هر دو خط‌تیره) می‌توانست به آرایه‌ای با کمتر از ۳ عضو resolve
        // شود و [$gy, $gm, $gd] = ... باعث «Undefined array key» شود. این‌جا
        // با همان الگوی regex که gregorian_to_jalali_ymd از قبل استفاده
        // می‌کند، فرمت هم چک می‌شود.
        if (empty($gregorian_date) || $gregorian_date === '0000-00-00' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $gregorian_date)) {
            return '-';
        }

        [$gy, $gm, $gd] = array_map('intval', explode('-', $gregorian_date));
        [$jy, $jm, $jd] = self::to_jalali($gy, $gm, $gd);

        $months = ['فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور', 'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند'];

        return sprintf('%d %s %d', $jd, $months[$jm - 1], $jy);
    }

    /** نام روز هفته از عدد date('w') وردپرس/PHP: 0=یکشنبه ... 6=شنبه. برای جدول عمومی نوبت‌دهی. */
    public static function weekday_name(int $w): string
    {
        $names = ['یکشنبه', 'دوشنبه', 'سه‌شنبه', 'چهارشنبه', 'پنجشنبه', 'جمعه', 'شنبه'];

        return $names[$w] ?? '';
    }

    /** نام ماه شمسی (۱ تا ۱۲) — برای جدول عمومی نوبت‌دهی که فقط روز+ماه کوتاه لازم دارد، نه رشته‌ی کامل با سال. */
    public static function month_name(int $m): string
    {
        $months = ['فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور', 'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند'];

        return $months[$m - 1] ?? '';
    }

    /** فرمت عددی 1405/01/01 — برای فیلدهای فرم (برخلاف gregorian_to_jalali_string که اسم ماه می‌آورد). */
    public static function gregorian_to_jalali_ymd(string $gregorian_date): string
    {
        if (empty($gregorian_date) || $gregorian_date === '0000-00-00' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $gregorian_date)) {
            return '';
        }
        [$gy, $gm, $gd] = array_map('intval', explode('-', $gregorian_date));
        [$jy, $jm, $jd] = self::to_jalali($gy, $gm, $gd);

        return sprintf('%04d/%02d/%02d', $jy, $jm, $jd);
    }

    /** @return array{0:int,1:int,2:int} */
    public static function to_jalali(int $gy, int $gm, int $gd): array
    {
        return self::d2j(self::g2d($gy, $gm, $gd));
    }

    /**
     * جهت برعکس (شمسی → میلادی) که قبلاً در این کلاس وجود نداشت — چون
     * همه‌جای این پلاگین فقط برای *نمایش* از جلالی استفاده می‌کرد، نه
     * برای *دریافت* ورودی جلالی از کاربر/ادمین. برای فیلدهایی مثل
     * «تاریخ‌های تعطیل» که ادمین باید بتواند شمسی تایپ کند، این جهت لازم است.
     *
     * @return array{0:int,1:int,2:int} میلادی (gy, gm, gd)
     */
    public static function to_gregorian(int $jy, int $jm, int $jd): array
    {
        return self::d2g(self::j2d($jy, $jm, $jd));
    }

    /**
     * رشته‌ی شمسی به فرمت YYYY/MM/DD یا YYYY-MM-DD را به رشته‌ی میلادی
     * YYYY-MM-DD (همان فرمتی که ستون DATE در دیتابیس نیاز دارد) تبدیل
     * می‌کند. اگر فرمت نامعتبر باشد null برمی‌گرداند.
     */
    public static function jalali_string_to_gregorian_string(string $jalali_date): ?string
    {
        $jalali_date = trim($jalali_date);
        if (!preg_match('/^(\d{4})[\/\-](\d{1,2})[\/\-](\d{1,2})$/', $jalali_date, $m)) {
            return null;
        }
        [, $jy, $jm, $jd] = array_map('intval', $m);
        if ($jm < 1 || $jm > 12 || $jd < 1 || $jd > 31) {
            return null;
        }
        [$gy, $gm, $gd] = self::to_gregorian($jy, $jm, $jd);

        // اعتبارسنجی رفت‌وبرگشتی: قبلاً فقط jd بین ۱ تا ۳۱ چک می‌شد، بدون
        // توجه به اینکه ماه مقصد چند روز دارد (اسفندِ غیرکبیسه ۲۹ روزه است،
        // بقیه‌ی ماه‌های نیمه‌ی دوم سال ۳۰ روزه‌اند). یعنی مثلاً «۱۴۰۳/۱۲/۳۰»
        // بی‌سروصدا و بدون خطا به یک تاریخ کاملاً متفاوت (اول فروردین سال
        // بعد) تبدیل می‌شد. اینجا مقدار میلادی محاسبه‌شده دوباره به جلالی
        // برگردانده می‌شود؛ اگر با ورودی اصلی یکی نبود (یعنی jd از بازه‌ی
        // واقعی ماه رد شده)، ورودی نامعتبر تلقی و null برگردانده می‌شود.
        [$check_jy, $check_jm, $check_jd] = self::to_jalali($gy, $gm, $gd);
        if ($check_jy !== $jy || $check_jm !== $jm || $check_jd !== $jd) {
            return null;
        }

        return sprintf('%04d-%02d-%02d', $gy, $gm, $gd);
    }

    /** عدد ژولین از تاریخ شمسی — تکمیل‌کننده‌ی d2j برای مسیر برعکس. */
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

    /** @return array{gy:int,march:int,leap:int} */
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
