<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * گزارش‌های بالینی (فاز ۴) — فقط‌خواندنی.
 *
 * همه‌ی اعداد از دو جدول خودِ این افزونه (`pas_cf_cases` و `pas_cf_notes`)
 * محاسبه می‌شوند؛ هیچ کوئری‌ای به جدول‌های افزونه‌های دیگر زده نمی‌شود و
 * متن بالینی (یادداشت‌ها) خوانده یا رمزگشایی نمی‌شود.
 *
 * محدوده‌ی دید دقیقاً همان قانون دسترسی پرونده است: سرپرست
 * (PAS_CF_CAP_ALL) همه‌چیز را می‌بیند، درمانگر عادی فقط پرونده‌های
 * تخصیص‌داده‌شده به خودش و پرونده‌های تخصیص‌نیافته‌ای که خودش ساخته.
 */
final class Pas_CF_Reports
{
    /** بازه‌های مجاز گزارش (روز). هر مقدار دیگری به ۳۰ برمی‌گردد. */
    public const ALLOWED_WINDOWS = [30, 90, 365];

    /**
     * شرط محدوده‌ی دید، با alias `c` برای جدول پرونده‌ها.
     *
     * @return array{0:string,1:array<int,int>}
     */
    public static function scope(): array
    {
        // فاز ۹: این‌جا هم از همان قرارداد مرکزی می‌آید؛ گزارش‌ها دیگر نسخهٔ
        // جداگانه‌ای از قاعدهٔ دسترسی نگه نمی‌دارند.
        return Pas_CF_Security::current_user_case_scope('c');
    }

    /**
     * @return array<string,mixed>
     */
    public static function summary(int $days = 30): array
    {
        global $wpdb;

        $days = in_array($days, self::ALLOWED_WINDOWS, true) ? $days : 30;

        [$where, $scope_params] = self::scope();

        $cases_table = Pas_CF_Installer::table('cases');
        $notes_table = Pas_CF_Installer::table('notes');

        $today_dt = new \DateTimeImmutable('today', wp_timezone());
        $today    = $today_dt->format('Y-m-d');
        $since    = $today_dt->modify('-' . $days . ' days')->format('Y-m-d');

        // ۱) پرونده‌ها به تفکیک وضعیت.
        // برای سرپرست/ادمین، scope برابر 1=1 است و هیچ placeholder‌ای در کوئری
        // نیست؛ wpdb::prepare() با کوئری بدون placeholder نوتیس PHP می‌دهد،
        // پس فقط وقتی پارامتر داریم prepare می‌زنیم.
        $case_sql  = "SELECT c.status AS status, COUNT(*) AS total FROM $cases_table c WHERE $where GROUP BY c.status";
        $case_rows = (array) ($scope_params
            ? $wpdb->get_results($wpdb->prepare($case_sql, ...$scope_params))
            : $wpdb->get_results($case_sql));

        $cases_by_status = [];
        $cases_total     = 0;
        foreach ($case_rows as $row) {
            $status = (string) $row->status;
            $cases_by_status[$status] = (int) $row->total;
            $cases_total             += (int) $row->total;
        }

        // ۲) جلسات بازه، به تفکیک وضعیت + میانگین مدت.
        $session_rows = (array) $wpdb->get_results($wpdb->prepare(
            "SELECT n.status AS status, COUNT(*) AS total, AVG(NULLIF(n.duration_minutes, 0)) AS avg_duration
             FROM $notes_table n
             INNER JOIN $cases_table c ON c.id = n.case_id
             WHERE n.session_date >= %s AND $where
             GROUP BY n.status",
            $since,
            ...$scope_params
        ));

        $sessions_by_status = [];
        $sessions_total     = 0;
        $duration_weighted  = 0.0;
        $duration_count     = 0;

        foreach ($session_rows as $row) {
            $status = (string) $row->status;
            $total  = (int) $row->total;

            $sessions_by_status[$status] = $total;
            $sessions_total             += $total;

            if ($row->avg_duration !== null) {
                $duration_weighted += (float) $row->avg_duration * $total;
                $duration_count    += $total;
            }
        }

        $active_cases = (int) ($cases_by_status['فعال'] ?? 0);

        // ۳) پرونده‌های راکد: فعال، ولی آخرین جلسه قبل از شروع بازه (یا هیچ جلسه‌ای).
        $stagnant = (array) $wpdb->get_results($wpdb->prepare(
            "SELECT c.id, c.full_name, c.phone, c.updated_at, MAX(n.session_date) AS last_session
             FROM $cases_table c
             LEFT JOIN $notes_table n ON n.case_id = c.id
             WHERE c.status = %s AND $where
             GROUP BY c.id, c.full_name, c.phone, c.updated_at
             HAVING last_session IS NULL OR last_session < %s
             ORDER BY last_session ASC
             LIMIT 25",
            'فعال',
            ...array_merge($scope_params, [$since])
        ));

        // ۴) جلسات بعدی ثبت‌شده (از فاز ۳) — نزدیک‌ترین تاریخ هر پرونده.
        $upcoming_rows = (array) $wpdb->get_results($wpdb->prepare(
            "SELECT n.case_id, n.next_session_date, n.session_number, c.full_name, c.phone
             FROM $notes_table n
             INNER JOIN $cases_table c ON c.id = n.case_id
             WHERE n.next_session_date <> '' AND n.next_session_date >= %s AND $where
             ORDER BY n.next_session_date ASC
             LIMIT 60",
            $today,
            ...$scope_params
        ));

        $upcoming = [];
        foreach ($upcoming_rows as $row) {
            $case_id = (int) $row->case_id;
            if (isset($upcoming[$case_id])) {
                continue; // فقط نزدیک‌ترین جلسه‌ی بعدی هر پرونده
            }
            $upcoming[$case_id] = $row;
        }
        $upcoming = array_slice(array_values($upcoming), 0, 15);

        $done    = (int) ($sessions_by_status['done'] ?? 0);
        $no_show = (int) ($sessions_by_status['no_show'] ?? 0);

        return [
            'days'               => $days,
            'since'              => $since,
            'can_view_all'       => Pas_CF_Security::current_user_can_view_all(),
            'cases_total'        => $cases_total,
            'cases_active'       => $active_cases,
            'cases_by_status'    => $cases_by_status,
            'sessions_total'     => $sessions_total,
            'sessions_done'      => $done,
            'sessions_no_show'   => $no_show,
            'sessions_by_status' => $sessions_by_status,
            'avg_duration'       => $duration_count > 0 ? round($duration_weighted / $duration_count) : 0,
            'no_show_rate'       => ($done + $no_show) > 0 ? round($no_show * 100 / ($done + $no_show), 1) : 0.0,
            'sessions_per_case'  => $active_cases > 0 ? round($sessions_total / $active_cases, 1) : 0.0,
            'stagnant'           => $stagnant,
            'upcoming'           => $upcoming,
        ];
    }
}
