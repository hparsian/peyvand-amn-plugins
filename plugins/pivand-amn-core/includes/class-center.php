<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * مرکز مدیریت و مانیتورینگ (فاز ۱۲ / گام ۵).
 *
 * یک نگاه بالا به وضعیت اکوسیستم: نسخه و فعال‌بودن افزونه‌ها، سلامت جدول‌ها و
 * نقش‌ها، انطباق/امنیت، کلید رمزنگاری، و وضعیت کانال‌های اطلاع‌رسانی
 * (پیامک/ایمیل). صفحهٔ کاملاً فقط‌خواندنی است: هیچ تنظیمی تغییر نمی‌کند.
 *
 * قواعد:
 *  - هیچ دادهٔ نمایشی/نمونه‌ای وجود ندارد؛ هر عدد از `Pas_Core_Health`،
 *    `Pas_Core_Compliance`، آپشن‌های واقعی و جدول لاگ پیامک می‌آید. اگر چیزی
 *    نصب/تنظیم نشده باشد، «تنظیم نشده» نوشته می‌شود، نه یک مقدار ساختگی.
 *  - لاگ پیامک فقط زمان/وضعیت/دلیل و شمارهٔ ماسک‌شده را نشان می‌دهد؛ متن
 *    پیام یا شمارهٔ کامل نمایش داده نمی‌شود.
 *  - نمودارها با ApexCharts سلف‌هاست (بدون CDN) و فقط در همین صفحه لود می‌شوند.
 */
final class Pas_Core_Center
{
    public static function render(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die('دسترسی غیرمجاز.');
        }

        $report     = Pas_Core_Health::report();
        $summary    = $report['summary'];
        $checks     = class_exists('Pas_Core_Compliance') ? Pas_Core_Compliance::checks() : [];
        $compliance = class_exists('Pas_Core_Compliance') ? Pas_Core_Compliance::summary($checks) : ['ok' => 0, 'warn' => 0, 'fail' => 0];

        $plugins_total  = count($report['rows']);
        $plugins_active = 0;
        $tables_decl    = 0;
        $tables_missing = 0;

        foreach ($report['rows'] as $row) {
            if (!empty($row['active'])) {
                ++$plugins_active;
            }
            $tables_decl    += count($row['tables']);
            $tables_missing += count($row['missing_tables']);
        }

        $sms_user = (string) get_option('pas_sms_username', '');
        $sms_pass = (string) get_option('pas_sms_password', '');
        $sms_ok   = $sms_user !== '' && $sms_pass !== '';

        $smtp          = get_option('zsm_settings', []);
        $smtp          = is_array($smtp) ? $smtp : [];
        $smtp_ok       = (($smtp['host'] ?? '') !== '' && ($smtp['username'] ?? '') !== '')
            || (($smtp['brevo_api_key'] ?? '') !== '');

        $key    = Pas_Core_Data_Audit::key_status();
        $sms_log = self::sms_stats();

        $labels = [
            'ok'   => 'سالم',
            'warn' => 'نیازمند بررسی',
            'fail' => 'مشکل',
            'info' => 'اطلاع',
        ];

        $chart_data = [
            'health' => [
                (int) ($summary['ok'] ?? 0),
                (int) ($summary['warn'] ?? 0),
                (int) ($summary['fail'] ?? 0),
                (int) ($summary['info'] ?? 0),
            ],
            'plugins' => array_map(static function ($row) {
                return [
                    'name'    => (string) $row['label'],
                    'present' => max(0, count($row['tables']) - count($row['missing_tables'])),
                    'missing' => count($row['missing_tables']),
                ];
            }, $report['rows']),
        ];
        ?>
        <div class="wrap pas-core-admin pas-center">
            <h1 class="wp-heading-inline">مرکز مدیریت پیوند امن</h1>
            <p class="description pas-center-lead">
                وضعیت اکوسیستم در یک نگاه — همهٔ اعداد از بررسی مستقیم نصب، جدول‌ها،
                نقش‌ها، تنظیمات و لاگ واقعی ساخته شده‌اند. این صفحه چیزی را تغییر نمی‌دهد.
                آخرین بررسی: <span dir="ltr"><?php echo esc_html((string) $report['generated_at']); ?></span>
            </p>

            <div class="pas-center-stats">
                <div class="pas-center-stat">
                    <span class="pas-center-k">افزونه‌های فعال</span>
                    <span class="pas-center-v"><?php echo esc_html($plugins_active . ' / ' . $plugins_total); ?></span>
                    <span class="pas-center-sub">از کاتالوگ اکوسیستم</span>
                </div>
                <div class="pas-center-stat">
                    <span class="pas-center-k">سلامت اکوسیستم</span>
                    <span class="pas-center-v"><?php echo esc_html((string) ($summary['ok'] ?? 0)); ?></span>
                    <span class="pas-center-sub">
                        <?php echo esc_html((int) ($summary['warn'] ?? 0)); ?> نیازمند بررسی،
                        <?php echo esc_html((int) ($summary['fail'] ?? 0)); ?> مشکل
                    </span>
                </div>
                <div class="pas-center-stat">
                    <span class="pas-center-k">جدول‌های دیتابیس</span>
                    <span class="pas-center-v"><?php echo esc_html((string) ($tables_decl - $tables_missing)); ?></span>
                    <span class="pas-center-sub">
                        <?php echo $tables_missing > 0
                            ? esc_html($tables_missing . ' جدول اعلام‌شده غایب است')
                            : 'همهٔ جدول‌های اعلام‌شده موجودند'; ?>
                    </span>
                </div>
                <div class="pas-center-stat">
                    <span class="pas-center-k">انطباق و امنیت</span>
                    <span class="pas-center-v"><?php echo esc_html((string) ($compliance['ok'] ?? 0)); ?></span>
                    <span class="pas-center-sub">
                        <?php echo esc_html((int) ($compliance['warn'] ?? 0)); ?> هشدار،
                        <?php echo esc_html((int) ($compliance['fail'] ?? 0)); ?> مشکل
                    </span>
                </div>
                <div class="pas-center-stat<?php echo $key['configured'] ? '' : ' is-bad'; ?>">
                    <span class="pas-center-k">کلید رمزنگاری</span>
                    <span class="pas-center-v"><?php echo $key['configured'] ? '✓' : '—'; ?></span>
                    <span class="pas-center-sub">
                        <?php echo $key['configured']
                            ? esc_html('تنظیم شده — منبع: ' . $key['source'])
                            : 'تنظیم نشده'; ?>
                    </span>
                </div>
                <div class="pas-center-stat<?php echo $sms_ok ? '' : ' is-warn'; ?>">
                    <span class="pas-center-k">پیامک</span>
                    <span class="pas-center-v"><?php echo $sms_ok ? '✓' : '—'; ?></span>
                    <span class="pas-center-sub">
                        <?php echo esc_html($sms_ok ? 'اعتبارنامه تنظیم شده' : 'اعتبارنامه تنظیم نشده'); ?>
                        <?php if ($sms_log['exists']) : ?>
                            · <?php echo esc_html((string) $sms_log['last24']); ?> ارسال ۲۴ ساعت اخیر
                        <?php endif; ?>
                    </span>
                </div>
                <div class="pas-center-stat<?php echo $smtp_ok ? '' : ' is-warn'; ?>">
                    <span class="pas-center-k">ایمیل (SMTP)</span>
                    <span class="pas-center-v"><?php echo $smtp_ok ? '✓' : '—'; ?></span>
                    <span class="pas-center-sub">
                        <?php echo esc_html($smtp_ok ? 'تنظیم شده' : 'تنظیم نشده'); ?>
                    </span>
                </div>
            </div>

            <div class="pas-center-charts">
                <div class="pas-center-card">
                    <h2>توزیع وضعیت سلامت</h2>
                    <div id="pas-center-health-chart" class="pas-center-chart" role="img"
                         aria-label="نمودار دونات توزیع وضعیت سلامت افزونه‌ها"></div>
                    <noscript><p class="description">برای دیدن نمودار، جاوااسکریپت لازم است.</p></noscript>
                </div>
                <div class="pas-center-card">
                    <h2>جدول‌های موجود و غایب هر افزونه</h2>
                    <div id="pas-center-tables-chart" class="pas-center-chart" role="img"
                         aria-label="نمودار میله‌ای تعداد جدول‌های موجود و غایب هر افزونه"></div>
                    <noscript><p class="description">برای دیدن نمودار، جاوااسکریپت لازم است.</p></noscript>
                </div>
            </div>

            <h2>افزونه‌ها</h2>
            <table class="widefat striped pas-center-plugins">
                <thead>
                    <tr><th>افزونه</th><th>نصب/فعال</th><th>نسخه</th><th>جدول‌ها</th><th>وضعیت</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($report['rows'] as $row) : ?>
                        <tr>
                            <td>
                                <strong><?php echo esc_html((string) $row['label']); ?></strong><br>
                                <code><?php echo esc_html((string) $row['key']); ?></code>
                            </td>
                            <td>
                                <?php if (!$row['installed']) : ?>
                                    نصب نشده
                                <?php elseif ($row['active']) : ?>
                                    فعال
                                <?php else : ?>
                                    غیرفعال
                                <?php endif; ?>
                            </td>
                            <td><span dir="ltr"><?php echo esc_html($row['header_version'] !== '' ? $row['header_version'] : ($row['const_version'] !== '' ? $row['const_version'] : '—')); ?></span></td>
                            <td>
                                <?php
                                $declared = count($row['tables']);
                                echo $declared === 0
                                    ? '—'
                                    : esc_html($declared - count($row['missing_tables']) . ' از ' . $declared);
                                ?>
                            </td>
                            <td>
                                <span class="pas-center-badge pas-center-badge-<?php echo esc_attr((string) $row['status']); ?>">
                                    <?php echo esc_html($labels[(string) $row['status']] ?? (string) $row['status']); ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <h2>انطباق و امنیت</h2>
            <?php if (empty($checks)) : ?>
                <div class="pas-center-empty">ماژول انطباق در دسترس نیست.</div>
            <?php else : ?>
                <table class="widefat striped pas-center-compliance">
                    <thead><tr><th>بررسی</th><th>وضعیت</th><th>توضیح</th></tr></thead>
                    <tbody>
                        <?php foreach ($checks as $check) : ?>
                            <tr>
                                <td><strong><?php echo esc_html((string) $check['title']); ?></strong></td>
                                <td>
                                    <span class="pas-center-badge pas-center-badge-<?php echo esc_attr((string) $check['status']); ?>">
                                        <?php echo esc_html($labels[(string) $check['status']] ?? (string) $check['status']); ?>
                                    </span>
                                </td>
                                <td><?php echo esc_html((string) $check['detail']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>

            <h2>آخرین تلاش‌های ارسال پیامک</h2>
            <?php if (!$sms_log['exists']) : ?>
                <div class="pas-center-empty">افزونهٔ پیامک نصب نیست یا جدول لاگ آن ساخته نشده است.</div>
            <?php elseif (empty($sms_log['rows'])) : ?>
                <div class="pas-center-empty">هنوز هیچ تلاش ارسالی در لاگ ثبت نشده است.</div>
            <?php else : ?>
                <table class="widefat striped pas-center-sms">
                    <thead><tr><th>زمان</th><th>شماره (ماسک‌شده)</th><th>نتیجه</th><th>دلیل</th></tr></thead>
                    <tbody>
                        <?php foreach ($sms_log['rows'] as $log) : ?>
                            <tr>
                                <td><span dir="ltr"><?php echo esc_html((string) $log->created_at); ?></span></td>
                                <td><span dir="ltr"><?php echo esc_html(self::mask_mobile((string) $log->mobile)); ?></span></td>
                                <td>
                                    <span class="pas-center-badge <?php echo ((int) $log->success === 1) ? 'pas-center-badge-ok' : 'pas-center-badge-fail'; ?>">
                                        <?php echo ((int) $log->success === 1) ? 'موفق' : 'ناموفق'; ?>
                                    </span>
                                </td>
                                <td><?php echo esc_html((string) $log->reason); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>

            <h2>میان‌بُرها</h2>
            <p class="pas-center-links">
                <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=pas-core-health')); ?>">سلامت اکوسیستم</a>
                <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=pas-core-overview')); ?>">زیرساخت و قراردادها</a>
                <a class="button" href="<?php echo esc_url(admin_url('options-general.php?page=zsm-smtp-mailer')); ?>">تنظیمات ایمیل</a>
                <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=pas-sms-bridge')); ?>">تنظیمات پیامک</a>
                <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=pas-case-files')); ?>">پروندهٔ مراجعین</a>
                <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=psy-quiz')); ?>">آزمون‌ها</a>
            </p>
        </div>
        <?php

        wp_add_inline_script(
            'pas-core-center-chart',
            'window.PasCenterCharts = ' . wp_json_encode($chart_data) . ';',
            'before'
        );
    }

    /**
     * آمار جدول لاگ پیامک — اگر جدول نباشد، exists=false برمی‌گردد.
     *
     * @return array{exists:bool,total:int,last24:int,rows:array<int,object>}
     */
    private static function sms_stats(): array
    {
        global $wpdb;

        $table = $wpdb->prefix . 'pas_sms_log';

        $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) === $table;

        if (!$exists) {
            return ['exists' => false, 'total' => 0, 'last24' => 0, 'rows' => []];
        }

        $total  = (int) $wpdb->get_var("SELECT COUNT(*) FROM $table");
        $last24 = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table WHERE created_at >= %s",
            gmdate('Y-m-d H:i:s', current_time('timestamp') - DAY_IN_SECONDS)
        ));
        $rows = (array) $wpdb->get_results("SELECT mobile, success, reason, created_at FROM $table ORDER BY id DESC LIMIT 5");

        return ['exists' => true, 'total' => $total, 'last24' => $last24, 'rows' => $rows];
    }

    private static function mask_mobile(string $mobile): string
    {
        $len = strlen($mobile);

        if ($len <= 6) {
            return $mobile;
        }

        return substr($mobile, 0, 4) . str_repeat('•', max(0, $len - 7)) . substr($mobile, -3);
    }
}
