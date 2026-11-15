<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * صفحهٔ مدیریت هشدارها و وظایف (فاز ۵).
 *
 * چرا این صفحه لازم بود: هشدار بالینی بدون «محل اقدام» بی‌فایده است. داشبورد
 * فقط نشان می‌داد؛ درمانگر نمی‌توانست وضعیت را عوض کند یا یادداشت بگذارد.
 *
 * قواعد:
 *  - دسترسی همان `PAS_CF_CAP` و همان محدودهٔ دید پرونده‌ها؛ برای هر هشدار هم
 *    جداگانه `can_access()` بررسی می‌شود (لینک حدس‌زده‌شده کار نمی‌کند).
 *  - همهٔ تغییرها POST + nonce + ثبت در `pas_cf_alert_history` و audit.
 *  - متن یادداشت صرفاً مدیریتی است (۱۹۰ کاراکتر) و در روزنامه می‌ماند؛ جای
 *    یادداشت بالینی جلسه نیست.
 *  - هیچ داده‌ای حذف نمی‌شود؛ فقط وضعیت عوض می‌شود.
 */
final class Pas_CF_Alerts_Page
{
    public const PAGE = 'pas-case-alerts';

    public static function register(): void
    {
        add_action('admin_post_pas_cf_update_alert', [__CLASS__, 'handle_alert']);
        add_action('admin_post_pas_cf_update_task', [__CLASS__, 'handle_task']);
    }

    // ── هندلرها ──────────────────────────────────────────────────────────

    public static function handle_alert(): void
    {
        Pas_CF_Security::require_access();
        check_admin_referer('pas_cf_update_alert');

        $alert_id = isset($_POST['alert_id']) ? (int) $_POST['alert_id'] : 0;
        $status   = isset($_POST['status']) ? sanitize_key(wp_unslash($_POST['status'])) : '';
        $note     = isset($_POST['note']) ? sanitize_text_field(wp_unslash($_POST['note'])) : '';
        $reason   = isset($_POST['closed_reason']) ? sanitize_text_field(wp_unslash($_POST['closed_reason'])) : '';

        if ($alert_id <= 0 || !Pas_CF_Alerts::can_access($alert_id)) {
            wp_die('هشدار پیدا نشد یا دسترسی ندارید.', 'خطا', ['response' => 403]);
        }

        $ok = Pas_CF_Alerts::update_status($alert_id, $status, $note, $reason);

        self::redirect('alert', $ok ? 'updated' : 'invalid');
    }

    public static function handle_task(): void
    {
        Pas_CF_Security::require_access();
        check_admin_referer('pas_cf_update_task');

        $task_id = isset($_POST['task_id']) ? (int) $_POST['task_id'] : 0;
        $status  = isset($_POST['status']) ? sanitize_key(wp_unslash($_POST['status'])) : '';

        if ($task_id <= 0 || !Pas_CF_Tasks::can_access($task_id)) {
            wp_die('وظیفه پیدا نشد یا دسترسی ندارید.', 'خطا', ['response' => 403]);
        }

        $ok = Pas_CF_Tasks::update_status($task_id, $status);

        self::redirect('task', $ok ? 'updated' : 'invalid');
    }

    private static function redirect(string $what, string $result): void
    {
        $view = isset($_POST['view']) ? sanitize_key(wp_unslash($_POST['view'])) : 'open';

        // فرم‌هایی که از درون صفحهٔ پرونده ارسال شده‌اند، همان‌جا برمی‌گردند.
        $return_case = isset($_POST['return_case']) ? (int) $_POST['return_case'] : 0;
        if ($return_case > 0) {
            wp_safe_redirect(add_query_arg([
                'page'    => 'pas-case-file-new',
                'case_id' => $return_case,
                'changed' => $what,
                'result'  => $result,
            ], admin_url('admin.php')) . '#pas-cf-alerts');
            exit;
        }

        wp_safe_redirect(add_query_arg([
            'page'    => self::PAGE,
            'view'    => in_array($view, ['open', 'closed', 'all'], true) ? $view : 'open',
            'changed' => $what,
            'result'  => $result,
        ], admin_url('admin.php')));
        exit;
    }

    // ── بخش داخل صفحهٔ پرونده ──────────────────────────────────────────

    /**
     * کارت «هشدارها و وظایف این پرونده» — همان داده و همان هندلرهای صفحهٔ
     * مدیریت، فقط محدود به همین پرونده. فرم‌ها با return_case برمی‌گردند به
     * همین صفحه تا درمانگر وسط کار پرونده جابه‌جا نشود.
     */
    public static function render_case_section(int $case_id): void
    {
        $case = Pas_CF_Case_File::get($case_id);
        if (!$case || !Pas_CF_Security::current_user_can_access_case($case)) {
            return;
        }

        $alerts = Pas_CF_Alerts::for_case($case_id, 8);
        $tasks  = Pas_CF_Tasks::for_case($case_id, 8);
        ?>
        <div class="pas-cf-box" id="pas-cf-alerts">
            <h3>هشدارها و وظایف این پرونده</h3>

            <?php if (isset($_GET['changed'])) : ?>
                <div class="notice <?php echo (($_GET['result'] ?? '') === 'updated') ? 'notice-success' : 'notice-warning'; ?> inline">
                    <p><?php echo esc_html((($_GET['result'] ?? '') === 'updated') ? 'وضعیت ثبت شد.' : 'تغییر اعمال نشد.'); ?></p>
                </div>
            <?php endif; ?>

            <?php if (empty($alerts)) : ?>
                <p class="description">هشدار بالینی برای این پرونده ثبت نشده است. نبود هشدار به معنای نبود خطر نیست.</p>
            <?php else : ?>
                <table class="widefat striped">
                    <thead>
                        <tr><th>نوع</th><th>اولویت</th><th>وضعیت</th><th>تاریخ</th><th>تغییر وضعیت</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($alerts as $alert) : ?>
                            <tr>
                                <td><?php echo esc_html(($alert['type'] ?? '') === 'self_harm_risk' ? 'پرچم خودآسیبی' : (string) ($alert['type'] ?? '')); ?></td>
                                <td><?php echo esc_html(Pas_CF_Alerts::PRIORITIES[(string) ($alert['priority'] ?? 'medium')] ?? ''); ?></td>
                                <td><?php echo esc_html(Pas_CF_Alerts::STATUSES[(string) ($alert['status'] ?? 'new')] ?? (string) ($alert['status'] ?? '')); ?></td>
                                <td><?php echo esc_html(Pas_CF_Jalali::to_jalali_string(substr((string) ($alert['created_at'] ?? ''), 0, 10))); ?></td>
                                <td>
                                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:flex;gap:.35em;flex-wrap:wrap;align-items:center;">
                                        <input type="hidden" name="action" value="pas_cf_update_alert" />
                                        <input type="hidden" name="alert_id" value="<?php echo esc_attr((string) (int) $alert['id']); ?>" />
                                        <input type="hidden" name="return_case" value="<?php echo esc_attr((string) $case_id); ?>" />
                                        <?php wp_nonce_field('pas_cf_update_alert'); ?>
                                        <select name="status">
                                            <?php foreach (Pas_CF_Alerts::STATUSES as $key => $label) : ?>
                                                <option value="<?php echo esc_attr($key); ?>" <?php selected((string) ($alert['status'] ?? ''), $key); ?>><?php echo esc_html($label); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <input type="text" name="note" maxlength="190" placeholder="یادداشت" style="max-width:10em;" />
                                        <button class="button button-small" type="submit">ثبت</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>

            <h4>وظایف باز</h4>
            <?php if (empty($tasks)) : ?>
                <p class="description">وظیفهٔ بازی برای این پرونده نیست.</p>
            <?php else : ?>
                <ul class="pas-cf-dash-list">
                    <?php foreach ($tasks as $task) : ?>
                        <li>
                            <span class="pas-cf-dash-name"><?php echo esc_html((string) ($task['title'] ?? '')); ?></span>
                            <span class="pas-cf-dash-meta"><?php echo esc_html(Pas_CF_Tasks::PRIORITIES[(string) ($task['priority'] ?? 'medium')] ?? ''); ?> · <?php echo esc_html(Pas_CF_Tasks::STATUSES[(string) ($task['status'] ?? 'open')] ?? ''); ?></span>
                            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline-flex;gap:.35em;align-items:center;">
                                <input type="hidden" name="action" value="pas_cf_update_task" />
                                <input type="hidden" name="task_id" value="<?php echo esc_attr((string) (int) $task['id']); ?>" />
                                <input type="hidden" name="return_case" value="<?php echo esc_attr((string) $case_id); ?>" />
                                <?php wp_nonce_field('pas_cf_update_task'); ?>
                                <select name="status">
                                    <?php foreach (Pas_CF_Tasks::STATUSES as $key => $label) : ?>
                                        <option value="<?php echo esc_attr($key); ?>" <?php selected((string) ($task['status'] ?? ''), $key); ?>><?php echo esc_html($label); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <button class="button button-small" type="submit">ثبت</button>
                            </form>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
        <?php
    }

    // ── نمایش ────────────────────────────────────────────────────────────

    public static function render(): void
    {
        Pas_CF_Security::require_access();

        $view = isset($_GET['view']) ? sanitize_key(wp_unslash($_GET['view'])) : 'open';
        if (!in_array($view, ['open', 'closed', 'all'], true)) {
            $view = 'open';
        }

        $alerts = Pas_CF_Alerts::list_visible($view);
        $tasks  = Pas_CF_Tasks::list_visible($view === 'closed' ? 'done' : 'open');

        $tabs = [
            'open'   => 'باز',
            'closed' => 'بسته‌شده',
            'all'    => 'همه',
        ];

        $changed = isset($_GET['changed']) ? sanitize_key(wp_unslash($_GET['changed'])) : '';
        $result  = isset($_GET['result']) ? sanitize_key(wp_unslash($_GET['result'])) : '';
        ?>
        <div class="wrap pas-cf-wrap">
            <h1 class="wp-heading-inline">هشدارها و وظایف</h1>

            <?php if ($changed !== '') : ?>
                <div class="notice <?php echo $result === 'updated' ? 'notice-success' : 'notice-warning'; ?> inline">
                    <p>
                        <?php
                        echo esc_html(
                            $result === 'updated'
                                ? ($changed === 'alert' ? 'وضعیت هشدار به‌روزرسانی شد.' : 'وضعیت وظیفه به‌روزرسانی شد.')
                                : 'تغییر اعمال نشد؛ مقدار ارسالی معتبر نبود.'
                        );
                        ?>
                    </p>
                </div>
            <?php endif; ?>

            <p class="description">
                هر هشدار یک پرچم برای بررسی انسانی است، نه تشخیص؛ نبود هشدار به معنای نبود خطر نیست.
                متن یادداشت در تاریخچهٔ هشدار ثبت می‌شود و جای یادداشت بالینی جلسه را نمی‌گیرد.
            </p>

            <ul class="subsubsub">
                <?php foreach ($tabs as $key => $label) : ?>
                    <li>
                        <a href="<?php echo esc_url(add_query_arg(['page' => self::PAGE, 'view' => $key], admin_url('admin.php'))); ?>"
                           class="<?php echo $view === $key ? 'current' : ''; ?>">
                            <?php echo esc_html($label); ?>
                        </a><?php echo $key !== 'all' ? ' |' : ''; ?>
                    </li>
                <?php endforeach; ?>
            </ul>

            <h2>هشدارها</h2>
            <?php if (empty($alerts)) : ?>
                <div class="pas-cf-dash-empty">هشداری در این نما وجود ندارد.</div>
            <?php else : ?>
                <table class="widefat striped">
                    <thead>
                        <tr>
                            <th>مراجع</th>
                            <th>نوع</th>
                            <th>اولویت</th>
                            <th>وضعیت</th>
                            <th>زمان</th>
                            <th>دلیل</th>
                            <th>تغییر وضعیت</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($alerts as $alert) : ?>
                            <?php
                            $alert_id = (int) $alert['id'];
                            $history  = Pas_CF_Alerts::history($alert_id);
                            ?>
                            <tr>
                                <td><?php echo esc_html(($alert['full_name'] ?? '') !== '' ? $alert['full_name'] : '—'); ?></td>
                                <td><?php echo esc_html(($alert['type'] ?? '') === 'self_harm_risk' ? 'پرچم خودآسیبی' : (string) ($alert['type'] ?? '')); ?></td>
                                <td><span class="pas-cf-dash-status pas-cf-dash-status-<?php echo esc_attr((string) ($alert['priority'] ?? 'medium')); ?>"><?php echo esc_html(Pas_CF_Alerts::PRIORITIES[(string) ($alert['priority'] ?? 'medium')] ?? ''); ?></span></td>
                                <td><?php echo esc_html(Pas_CF_Alerts::STATUSES[(string) ($alert['status'] ?? 'new')] ?? (string) ($alert['status'] ?? '')); ?></td>
                                <td><?php echo esc_html(Pas_CF_Jalali::to_jalali_string(substr((string) ($alert['created_at'] ?? ''), 0, 10))); ?></td>
                                <td style="max-width:28em;"><?php echo esc_html((string) ($alert['reason'] ?? '')); ?></td>
                                <td>
                                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:flex;gap:.35em;flex-wrap:wrap;align-items:flex-start;">
                                        <input type="hidden" name="action" value="pas_cf_update_alert" />
                                        <input type="hidden" name="alert_id" value="<?php echo esc_attr((string) $alert_id); ?>" />
                                        <input type="hidden" name="view" value="<?php echo esc_attr($view); ?>" />
                                        <?php wp_nonce_field('pas_cf_update_alert'); ?>
                                        <select name="status">
                                            <?php foreach (Pas_CF_Alerts::STATUSES as $key => $label) : ?>
                                                <option value="<?php echo esc_attr($key); ?>" <?php selected((string) ($alert['status'] ?? ''), $key); ?>><?php echo esc_html($label); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <input type="text" name="note" maxlength="190" placeholder="یادداشت (اختیاری)" style="max-width:11em;" />
                                        <input type="text" name="closed_reason" maxlength="190" placeholder="دلیل بستن" style="max-width:9em;" />
                                        <button class="button button-small" type="submit">ثبت</button>
                                    </form>
                                </td>
                                <td>
                                    <?php if (!empty($history)) : ?>
                                        <details>
                                            <summary><?php echo esc_html((string) count($history)); ?> تغییر</summary>
                                            <ul>
                                                <?php foreach (array_reverse($history) as $h) : ?>
                                                    <li>
                                                        <?php echo esc_html(Pas_CF_Alerts::STATUSES[(string) $h['to_status']] ?? (string) $h['to_status']); ?>
                                                        — <?php echo esc_html(Pas_CF_Jalali::to_jalali_string(substr((string) $h['created_at'], 0, 10))); ?>
                                                        <?php if ((string) $h['note'] !== '') : ?>· <?php echo esc_html((string) $h['note']); ?><?php endif; ?>
                                                    </li>
                                                <?php endforeach; ?>
                                            </ul>
                                        </details>
                                    <?php endif; ?>
                                    <?php if ((int) ($alert['case_id'] ?? 0) > 0) : ?>
                                        <a class="button button-small" href="<?php echo esc_url(admin_url('admin.php?page=pas-case-file-new&case_id=' . (int) $alert['case_id'])); ?>">پرونده</a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>

            <h2>وظایف</h2>
            <?php if (empty($tasks)) : ?>
                <div class="pas-cf-dash-empty">وظیفه‌ای در این نما وجود ندارد.</div>
            <?php else : ?>
                <table class="widefat striped">
                    <thead>
                        <tr><th>عنوان</th><th>مراجع</th><th>اولویت</th><th>وضعیت</th><th>ساخت</th><th>تغییر وضعیت</th><th></th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($tasks as $task) : ?>
                            <tr>
                                <td><?php echo esc_html((string) ($task['title'] ?? '')); ?></td>
                                <td><?php echo esc_html(($task['full_name'] ?? '') !== '' ? $task['full_name'] : '—'); ?></td>
                                <td><?php echo esc_html(Pas_CF_Tasks::PRIORITIES[(string) ($task['priority'] ?? 'medium')] ?? ''); ?></td>
                                <td><?php echo esc_html(Pas_CF_Tasks::STATUSES[(string) ($task['status'] ?? 'open')] ?? (string) ($task['status'] ?? '')); ?></td>
                                <td><?php echo esc_html(Pas_CF_Jalali::to_jalali_string(substr((string) ($task['created_at'] ?? ''), 0, 10))); ?></td>
                                <td>
                                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:flex;gap:.35em;align-items:center;">
                                        <input type="hidden" name="action" value="pas_cf_update_task" />
                                        <input type="hidden" name="task_id" value="<?php echo esc_attr((string) (int) $task['id']); ?>" />
                                        <input type="hidden" name="view" value="<?php echo esc_attr($view); ?>" />
                                        <?php wp_nonce_field('pas_cf_update_task'); ?>
                                        <select name="status">
                                            <?php foreach (Pas_CF_Tasks::STATUSES as $key => $label) : ?>
                                                <option value="<?php echo esc_attr($key); ?>" <?php selected((string) ($task['status'] ?? ''), $key); ?>><?php echo esc_html($label); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <button class="button button-small" type="submit">ثبت</button>
                                    </form>
                                </td>
                                <td>
                                    <?php if ((int) ($task['case_id'] ?? 0) > 0) : ?>
                                        <a class="button button-small" href="<?php echo esc_url(admin_url('admin.php?page=pas-case-file-new&case_id=' . (int) $task['case_id'])); ?>">پرونده</a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        <?php
    }
}
