<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * داشبورد درمانگر (فاز ۱۲ / گام ۴).
 *
 * صفحه‌ی اول منوی «پرونده مراجعین»: خلاصه‌ی امروز و وضعیت جاری پرونده‌ها.
 *
 * قواعد سخت:
 *  - هیچ داده‌ی نمایشی/نمونه‌ای ساخته نمی‌شود؛ همه‌ی اعداد از کوئری واقعی روی
 *    جدول‌های همین افزونه (`pas_cf_cases` / `pas_cf_notes`) می‌آید و برای
 *    کاربری که هیچ داده‌ای ندارد، حالت خالی («چیزی ثبت نشده») نشان داده
 *    می‌شود، نه عدد ساختگی.
 *  - محدوده‌ی دید دقیقاً همان `Pas_CF_Reports::summary()` است (سرپرست: همه،
 *    درمانگر: پرونده‌های خودش)؛ هیچ کوئری جداگانه‌ای بدون همان شرط زده نمی‌شود.
 *  - متن یادداشت‌های بالینی خوانده/رمزگشایی نمی‌شود؛ فقط تاریخ، وضعیت و
 *    شماره‌ی جلسه.
 *  - همه‌ی لینک‌ها به صفحات موجود با همان کپابیلیتی می‌روند؛ این صفحه چیزی
 *    به دسترسی‌ها اضافه نمی‌کند.
 */
final class Pas_CF_Dashboard
{
    /**
     * جلسات ثبت‌شده در یک تاریخ مشخص (پیش‌فرض: امروز) در محدوده‌ی دید کاربر.
     *
     * @return array<int,object>
     */
    public static function sessions_on(string $date): array
    {
        global $wpdb;

        [$where, $scope_params] = Pas_CF_Reports::scope();

        $cases_table = Pas_CF_Installer::table('cases');
        $notes_table = Pas_CF_Installer::table('notes');

        $sql = "SELECT n.id, n.case_id, n.session_number, n.status, n.session_date,
                       c.full_name, c.phone
                FROM $notes_table n
                INNER JOIN $cases_table c ON c.id = n.case_id
                WHERE n.session_date = %s AND $where
                ORDER BY n.session_number ASC, n.id ASC";

        $params = array_merge([$date], $scope_params);

        return (array) $wpdb->get_results($wpdb->prepare($sql, ...$params));
    }

    public static function render(): void
    {
        Pas_CF_Security::require_access();

        $summary  = Pas_CF_Reports::summary(30);
        $statuses = Pas_CF_Case_File::session_statuses();
        $today    = current_time('Y-m-d');
        $sessions = self::sessions_on($today);
        $new_url  = admin_url('admin.php?page=pas-case-file-new');

        // فاز ۵: هشدارها و وظایف باز — همان محدودهٔ دید پرونده‌ها، بدون
        // کوئری جداگانه و بدون دادهٔ ساختگی.
        $open_alerts       = Pas_CF_Alerts::open_visible(6);
        $open_alerts_count = Pas_CF_Alerts::count_open_visible();
        $open_tasks        = Pas_CF_Tasks::open_visible(8);
        $open_tasks_count  = Pas_CF_Tasks::count_open_visible();

        $done_today = 0;
        foreach ($sessions as $row) {
            if ((string) $row->status === 'done') {
                ++$done_today;
            }
        }
        ?>
        <div class="wrap pas-cf-wrap pas-cf-dash">
            <h1 class="wp-heading-inline">داشبورد درمانگر</h1>
            <a href="<?php echo esc_url($new_url); ?>" class="page-title-action">+ پرونده‌ی جدید</a>

            <p class="description pas-cf-dash-today">
                امروز <?php echo esc_html(Pas_CF_Jalali::to_jalali_string($today)); ?>
                — همه‌ی اعداد از <?php echo esc_html($summary['can_view_all'] ? 'پرونده‌های همه‌ی درمانگرها' : 'پرونده‌های شما'); ?>
                محاسبه شده است.
            </p>

            <div class="pas-cf-dash-stats">
                <a class="pas-cf-dash-stat" href="<?php echo esc_url(admin_url('admin.php?page=pas-case-files')); ?>">
                    <span class="pas-cf-dash-k">جلسات امروز</span>
                    <span class="pas-cf-dash-v"><?php echo esc_html((string) count($sessions)); ?></span>
                    <span class="pas-cf-dash-sub"><?php echo esc_html($done_today > 0 ? $done_today . ' انجام‌شده' : 'در انتظار'); ?></span>
                </a>
                <a class="pas-cf-dash-stat" href="<?php echo esc_url(admin_url('admin.php?page=pas-case-files')); ?>">
                    <span class="pas-cf-dash-k">پرونده‌های فعال</span>
                    <span class="pas-cf-dash-v"><?php echo esc_html((string) $summary['cases_active']); ?></span>
                    <span class="pas-cf-dash-sub">از <?php echo esc_html((string) $summary['cases_total']); ?> پرونده</span>
                </a>
                <a class="pas-cf-dash-stat" href="<?php echo esc_url(admin_url('admin.php?page=pas-case-reports&days=30')); ?>">
                    <span class="pas-cf-dash-k">جلسات ۳۰ روز اخیر</span>
                    <span class="pas-cf-dash-v"><?php echo esc_html((string) $summary['sessions_total']); ?></span>
                    <span class="pas-cf-dash-sub"><?php echo esc_html((string) $summary['sessions_per_case']); ?> جلسه به‌ازای هر پرونده‌ی فعال</span>
                </a>
                <a class="pas-cf-dash-stat" href="<?php echo esc_url(admin_url('admin.php?page=pas-case-reports&days=30')); ?>">
                    <span class="pas-cf-dash-k">نرخ عدم حضور</span>
                    <span class="pas-cf-dash-v"><?php echo esc_html((string) $summary['no_show_rate']); ?><small>٪</small></span>
                    <span class="pas-cf-dash-sub"><?php echo esc_html($summary['avg_duration'] > 0 ? 'میانگین ' . $summary['avg_duration'] . ' دقیقه' : 'بدون جلسه در بازه'); ?></span>
                </a>
            </div>

            <div class="pas-cf-dash-stats">
                <div class="pas-cf-dash-stat pas-cf-dash-alert<?php echo $open_alerts_count > 0 ? ' is-warning' : ''; ?>">
                    <span class="pas-cf-dash-k">هشدارهای باز</span>
                    <span class="pas-cf-dash-v"><?php echo esc_html((string) $open_alerts_count); ?></span>
                    <span class="pas-cf-dash-sub"><?php echo esc_html($open_alerts_count > 0 ? 'نیازمند بررسی انسانی' : 'هشداری در انتظار نیست'); ?></span>
                </div>
                <div class="pas-cf-dash-stat">
                    <span class="pas-cf-dash-k">کارهای باز</span>
                    <span class="pas-cf-dash-v"><?php echo esc_html((string) $open_tasks_count); ?></span>
                    <span class="pas-cf-dash-sub"><?php echo esc_html($open_tasks_count > 0 ? 'از رویدادهای اکوسیستم' : 'همه انجام شده'); ?></span>
                </div>
            </div>

            <h2>هشدارهای نیازمند بررسی</h2>
            <p class="description">هر هشدار یک پرچم برای بررسی انسانی است، نه تشخیص؛ نبود هشدار به معنای نبود خطر نیست.</p>
            <?php if (empty($open_alerts)) : ?>
                <div class="pas-cf-dash-empty">هشدار بازی ثبت نشده است.</div>
            <?php else : ?>
                <table class="widefat striped">
                    <thead>
                        <tr><th>مراجع</th><th>نوع</th><th>اولویت</th><th>وضعیت</th><th>زمان</th><th></th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($open_alerts as $alert) : ?>
                            <tr>
                                <td><?php echo esc_html(($alert['full_name'] ?? '') !== '' ? $alert['full_name'] : '—'); ?></td>
                                <td><?php echo esc_html(($alert['type'] ?? '') === 'self_harm_risk' ? 'پرچم خودآسیبی' : (string) ($alert['type'] ?? '')); ?></td>
                                <td><span class="pas-cf-dash-status pas-cf-dash-status-<?php echo esc_attr((string) ($alert['priority'] ?? 'medium')); ?>"><?php echo esc_html(Pas_CF_Alerts::PRIORITIES[(string) ($alert['priority'] ?? 'medium')] ?? (string) ($alert['priority'] ?? '')); ?></span></td>
                                <td><?php echo esc_html(Pas_CF_Alerts::STATUSES[(string) ($alert['status'] ?? 'new')] ?? (string) ($alert['status'] ?? '')); ?></td>
                                <td><?php echo esc_html(Pas_CF_Jalali::to_jalali_string(substr((string) ($alert['created_at'] ?? ''), 0, 10))); ?></td>
                                <td><a class="button button-small" href="<?php echo esc_url(admin_url('admin.php?page=pas-case-file-new&case_id=' . (int) ($alert['case_id'] ?? 0))); ?>">باز کردن پرونده</a></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>

            <h2>کارهای نیازمند اقدام</h2>
            <?php if (empty($open_tasks)) : ?>
                <div class="pas-cf-dash-empty">کاری در انتظار نیست.</div>
            <?php else : ?>
                <ul class="pas-cf-dash-list">
                    <?php foreach ($open_tasks as $task) : ?>
                        <li>
                            <span class="pas-cf-dash-name"><?php echo esc_html((string) ($task['title'] ?? '')); ?></span>
                            <span class="pas-cf-dash-meta">
                                <?php echo esc_html(Pas_CF_Tasks::PRIORITIES[(string) ($task['priority'] ?? 'medium')] ?? ''); ?>
                                <?php if (!empty($task['full_name'])) : ?>· <?php echo esc_html((string) $task['full_name']); ?><?php endif; ?>
                            </span>
                            <?php if ((int) ($task['case_id'] ?? 0) > 0) : ?>
                                <a class="button button-small" href="<?php echo esc_url(admin_url('admin.php?page=pas-case-file-new&case_id=' . (int) $task['case_id'])); ?>">باز کردن</a>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>

            <h2>جلسات امروز</h2>
            <?php if (empty($sessions)) : ?>
                <div class="pas-cf-dash-empty">امروز جلسه‌ای ثبت نشده است.</div>
            <?php else : ?>
                <table class="widefat striped">
                    <thead>
                        <tr><th>مراجع</th><th>شماره‌ی جلسه</th><th>وضعیت</th><th></th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($sessions as $row) : ?>
                            <tr>
                                <td><?php echo esc_html($row->full_name !== '' ? $row->full_name : '—'); ?></td>
                                <td><?php echo (int) $row->session_number > 0 ? esc_html((string) (int) $row->session_number) : '—'; ?></td>
                                <td><span class="pas-cf-dash-status pas-cf-dash-status-<?php echo esc_attr((string) $row->status); ?>"><?php echo esc_html($statuses[(string) $row->status] ?? (string) $row->status); ?></span></td>
                                <td><a class="button button-small" href="<?php echo esc_url(admin_url('admin.php?page=pas-case-file-new&case_id=' . (int) $row->case_id)); ?>">باز کردن پرونده</a></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>

            <h2>جلسات بعدی ثبت‌شده</h2>
            <?php if (empty($summary['upcoming'])) : ?>
                <div class="pas-cf-dash-empty">جلسه‌ی بعدی برای هیچ پرونده‌ای ثبت نشده است.</div>
            <?php else : ?>
                <table class="widefat striped">
                    <thead>
                        <tr><th>مراجع</th><th>تاریخ جلسه‌ی بعد</th><th></th></tr>
                    </thead>
                    <tbody>
                        <?php foreach (array_slice($summary['upcoming'], 0, 8) as $row) : ?>
                            <tr>
                                <td><?php echo esc_html($row->full_name !== '' ? $row->full_name : '—'); ?></td>
                                <td><?php echo esc_html(Pas_CF_Jalali::to_jalali_string((string) $row->next_session_date)); ?></td>
                                <td><a class="button button-small" href="<?php echo esc_url(admin_url('admin.php?page=pas-case-file-new&case_id=' . (int) $row->case_id)); ?>">باز کردن پرونده</a></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>

            <h2>پرونده‌های راکد</h2>
            <p class="description">پرونده‌ی فعالی که در ۳۰ روز اخیر جلسه‌ای نداشته است.</p>
            <?php if (empty($summary['stagnant'])) : ?>
                <div class="pas-cf-dash-empty">پرونده‌ی راکدی وجود ندارد.</div>
            <?php else : ?>
                <ul class="pas-cf-dash-list">
                    <?php foreach (array_slice($summary['stagnant'], 0, 6) as $row) : ?>
                        <li>
                            <span class="pas-cf-dash-name"><?php echo esc_html($row->full_name !== '' ? $row->full_name : '—'); ?></span>
                            <span class="pas-cf-dash-meta"><?php echo esc_html(!empty($row->last_session) ? 'آخرین جلسه: ' . Pas_CF_Jalali::to_jalali_string((string) $row->last_session) : 'بدون جلسه'); ?></span>
                            <a class="button button-small" href="<?php echo esc_url(admin_url('admin.php?page=pas-case-file-new&case_id=' . (int) $row->id)); ?>">باز کردن</a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
        <?php
    }
}
