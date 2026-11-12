<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Psy_Debug — صفحه دیباگ پلاگین
 *
 * تب ۱: 📋 لاگ خطاها   — نمایش psy_logs با فیلتر + پاک کردن
 * تب ۲: 🧪 تست سریع    — اجرای scoring هر آزمون بدون مراجع
 * تب ۳: 🩺 وضعیت سیستم — schema، mPDF، cron، stuck، orphaned
 * تب ۴: 🗄️ دیتابیس      — آمار جداول + جستجوی مراجع
 */
class Psy_Debug {

    const NONCE = 'psy_debug_nonce';

    // ── رندر اصلی ─────────────────────────────────────────────────────────
    public static function render(): void {
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'دسترسی غیرمجاز.' );

        self::handle_actions();

        $active_tab = sanitize_key( wp_unslash( $_GET['tab'] ?? 'logs' ) );
        $tabs = [
            'logs'    => '📋 لاگ خطاها',
            'scorer'  => '🧪 تست سریع',
            'system'  => '🩺 وضعیت سیستم',
            'db'      => '🗄️ دیتابیس',
            'tracker' => '🔍 Debug Tracker',
        ];
        ?>
        <div class="wrap psy-admin psy-debug-wrap" dir="rtl">
            <h1>
                🛠️ ابزار دیباگ
                <span class="psy-h1-sub">v<?php echo esc_html(PSY_VERSION); ?></span>
            </h1>

            <?php self::render_notices(); ?>

            <!-- تب‌ها -->
            <nav class="nav-tab-wrapper psy-nav-tabs-mb">
                <?php foreach ( $tabs as $slug => $label ): ?>
                <a href="<?php echo esc_url( add_query_arg( [ 'page' => 'psy-quiz-debug', 'tab' => $slug ], admin_url('admin.php') ) ); ?>"
                   class="nav-tab <?php echo $active_tab === $slug ? 'nav-tab-active' : ''; ?>">
                    <?php echo esc_html($label); ?>
                </a>
                <?php endforeach; ?>
            </nav>

            <?php
            match ( $active_tab ) {
                'logs'    => self::render_logs(),
                'scorer'  => self::render_scorer(),
                'system'  => self::render_system(),
                'db'      => self::render_db(),
                'tracker' => Psy_Debug_Tracker::render(),
                default   => self::render_logs(),
            };
            ?>
        </div>
        <?php
    }

    // ── پردازش action های POST/GET ────────────────────────────────────────
    private static function handle_actions(): void {
        if ( ! isset( $_POST[self::NONCE] ) ) return;
        if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[self::NONCE] ) ), self::NONCE ) ) wp_die('خطای امنیتی.');
        if ( ! current_user_can('manage_options') ) return;

        $action = sanitize_key( wp_unslash( $_POST['psy_debug_action'] ?? '' ) );

        switch ( $action ) {

            case 'clear_logs':
                $count = Psy_Logger::clear_all();
                self::redirect( [ 'tab' => 'logs', 'done' => 'logs_cleared', 'count' => $count ] );

            case 'clear_cache':
                $count = self::do_clear_cache();
                self::redirect( [ 'tab' => 'system', 'done' => 'cache_cleared', 'count' => $count ] );

            case 'cleanup_stuck':
                Psy_Quiz_Activator::cleanup_stuck_processing();
                self::redirect( [ 'tab' => 'system', 'done' => 'stuck_cleaned' ] );

            case 'run_scorer':
                // اجرا با AJAX — این action فقط از تب scorer صدا می‌شه
                break;
        }
    }

    private static function redirect( array $args ): void {
        wp_safe_redirect( add_query_arg( array_merge( [ 'page' => 'psy-quiz-debug' ], $args ), admin_url('admin.php') ) );
        exit;
    }

    // ── notices بالای صفحه ────────────────────────────────────────────────
    private static function render_notices(): void {
        $done = sanitize_key( wp_unslash( $_GET['done'] ?? '' ) );
        if ( ! $done ) return;
        $count = (int)( $_GET['count'] ?? 0 );

        $messages = [
            'logs_cleared'  => "🗑️ {$count} لاگ پاک شد.",
            'cache_cleared'  => "🧹 {$count} آیتم کش پاک شد.",
            'stuck_cleaned'  => '✅ invitation های stuck پاک شدند.',
        ];

        if ( isset( $messages[$done] ) ) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html($messages[$done]) . '</p></div>';
        }
    }

    // ════════════════════════════════════════════════════════════════════════
    // تب ۱: لاگ خطاها
    // ════════════════════════════════════════════════════════════════════════
    private static function render_logs(): void {
        $filter_level   = sanitize_key( wp_unslash( $_GET['log_level'] ?? '' ) );
        $filter_context = sanitize_text_field( wp_unslash( $_GET['log_context'] ?? '' ) );
        $limit          = min( 500, max( 20, (int)( $_GET['log_limit'] ?? 100 ) ) );

        $logs   = Psy_Logger::get_logs( [ 'level' => $filter_level, 'context' => $filter_context, 'limit' => $limit ] );
        $counts = Psy_Logger::counts();

        $level_colors = [
            'error'   => [ 'bg' => '#fff5f5', 'border' => '#fc8181', 'badge' => '#c53030', 'text' => '#fff' ],
            'warning' => [ 'bg' => '#fffaf0', 'border' => '#f6ad55', 'badge' => '#c05621', 'text' => '#fff' ],
            'info'    => [ 'bg' => '#ebf8ff', 'border' => '#63b3ed', 'badge' => '#2b6cb0', 'text' => '#fff' ],
        ];
        ?>
        <!-- آمار -->
        <div class="psy-mini-stats">
            <?php foreach ( [
                ['🔴', 'خطا',    $counts['error'],   '#c53030', '#fff5f5'],
                ['🟡', 'هشدار',  $counts['warning'], '#c05621', '#fffaf0'],
                ['🔵', 'اطلاع',  $counts['info'],    '#2b6cb0', '#ebf8ff'],
                ['📦', 'مجموع',  $counts['total'],   '#4a5568', '#f7fafc'],
            ] as [$icon, $label, $cnt, $color, $bg] ): ?>
            <div class="psy-mini-stat" style="--stat-color:<?php echo esc_attr($color); ?>;--stat-bg:<?php echo esc_attr($bg); ?>;">
                <div class="psy-mini-stat-val"><?php echo (int)$cnt; ?></div>
                <div class="psy-mini-stat-lbl"><?php echo $icon . ' ' . esc_html($label); ?></div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- فیلتر -->
        <form method="get" class="psy-filter-form">
            <input type="hidden" name="page" value="psy-quiz-debug">
            <input type="hidden" name="tab"  value="logs">
            <select name="log_level" class="psy-input-h32">
                <option value="">همه سطوح</option>
                <option value="error"   <?php selected($filter_level,'error');   ?>>🔴 خطا</option>
                <option value="warning" <?php selected($filter_level,'warning'); ?>>🟡 هشدار</option>
                <option value="info"    <?php selected($filter_level,'info');    ?>>🔵 اطلاع</option>
            </select>
            <input type="text" name="log_context" value="<?php echo esc_attr($filter_context); ?>"
                   placeholder="فیلتر context..." class="psy-input-h30 psy-input-w180">
            <select name="log_limit" class="psy-input-h32">
                <?php foreach ([50,100,200,500] as $l): ?>
                <option value="<?php echo esc_attr((int)$l); ?>" <?php selected($limit,$l); ?>><?php echo esc_html((int)$l); ?> ردیف</option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="button">🔍 فیلتر</button>
            <a href="<?php echo esc_url(add_query_arg(['page'=>'psy-quiz-debug','tab'=>'logs'], admin_url('admin.php'))); ?>"
               class="button button-secondary">× پاک کردن فیلتر</a>
        </form>

        <!-- دکمه پاک کردن همه -->
        <?php if ( $counts['total'] > 0 ): ?>
        <form method="post" class="psy-mb-16"
              onsubmit="return confirm('همه <?php echo (int)$counts['total']; ?> لاگ پاک می‌شوند. مطمئنی؟');">
            <?php wp_nonce_field( self::NONCE, self::NONCE ); ?>
            <input type="hidden" name="psy_debug_action" value="clear_logs">
            <button type="submit" class="button button-secondary psy-btn-danger-outline">
                🗑️ پاک کردن همه لاگ‌ها
            </button>
        </form>
        <?php endif; ?>

        <!-- جدول لاگ‌ها -->
        <?php if ( empty($logs) ): ?>
        <div class="psy-card-empty psy-empty-panel">
            <div class="psy-card-empty-icon">✅</div>
            <div>هیچ لاگی یافت نشد.</div>
        </div>
        <?php else: ?>
        <div class="psy-card">
            <table class="psy-debug-table is-sm">
                <thead>
                    <tr>
                        <th class="psy-col-60">سطح</th>
                        <th class="psy-col-120">Context</th>
                        <th>پیام</th>
                        <th class="psy-col-60">Data</th>
                        <th class="psy-col-130">زمان</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ( $logs as $i => $log ):
                    $lc = $level_colors[$log->level] ?? $level_colors['info'];
                ?>
                <tr>
                    <td class="psy-va-top">
                        <span class="psy-level-badge" style="--lvl-color:<?php echo esc_attr($lc['badge']); ?>;">
                            <?php echo esc_html($log->level); ?>
                        </span>
                    </td>
                    <td class="psy-va-top-mono">
                        <?php echo esc_html($log->context); ?>
                    </td>
                    <td class="psy-va-top-relaxed">
                        <?php echo esc_html($log->message); ?>
                    </td>
                    <td class="psy-va-top-center">
                        <?php if ( $log->data ): ?>
                        <span class="psy-log-data-toggle psy-json-link" data-json="<?php echo esc_attr($log->data); ?>">JSON</span>
                        <?php else: echo '—'; endif; ?>
                    </td>
                    <td class="psy-va-top-soft">
                        <?php echo esc_html( pa_to_jalali($log->created_at, 'Y/m/d') ); ?>
                        <br><?php echo esc_html( substr($log->created_at, 11, 5) ); ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- modal برای نمایش JSON -->
        <div id="psy-json-modal" class="psy-modal-overlay">
            <div class="psy-modal-box">
                <button onclick="document.getElementById('psy-json-modal').classList.remove('is-open');" class="psy-modal-close">✕</button>
                <h3 class="psy-panel-title-sm">📦 Data</h3>
                <pre id="psy-json-content" class="psy-json-output"></pre>
            </div>
        </div>
        <script>
        document.querySelectorAll('.psy-log-data-toggle').forEach(function(el) {
            el.addEventListener('click', function() {
                var json = this.getAttribute('data-json');
                try { json = JSON.stringify(JSON.parse(json), null, 2); } catch(e) {}
                document.getElementById('psy-json-content').textContent = json;
                document.getElementById('psy-json-modal').classList.add('is-open');
            });
        });
        document.getElementById('psy-json-modal').addEventListener('click', function(e) {
            if (e.target === this) this.classList.remove('is-open');
        });
        </script>
        <?php endif; ?>
        <?php
    }

    // ════════════════════════════════════════════════════════════════════════
    // تب ۲: تست سریع
    // ════════════════════════════════════════════════════════════════════════
    private static function render_scorer(): void {
        $all_tests = \PsychAssessments\Core\Registry::all();

        // فقط آزمون‌هایی که کلاس مستقل دارند (نه stub)
        $scoreable = [];
        foreach ( $all_tests as $id => $test ) {
            // stub‌ها get_questions خالی برمی‌گردانند یا redirect دارند
            try {
                $q = $test->get_questions();
                if ( count($q) > 0 ) $scoreable[$id] = $test;
            } catch ( \Throwable $e ) { /* skip */ }
        }

        $selected_id = sanitize_key( wp_unslash( $_GET['scorer_test'] ?? array_key_first($scoreable) ?? '' ) );
        $selected    = $scoreable[$selected_id] ?? null;
        ?>
        <div class="psy-grid-side psy-grid-280">

            <!-- پنل انتخاب آزمون -->
            <div class="psy-card psy-card-pad">
                <h3 class="psy-panel-title-sm">انتخاب آزمون</h3>
                <form method="get">
                    <input type="hidden" name="page" value="psy-quiz-debug">
                    <input type="hidden" name="tab"  value="scorer">
                    <select name="scorer_test" onchange="this.form.submit()" class="psy-select-block">
                        <?php foreach ( $scoreable as $id => $test ): ?>
                        <option value="<?php echo esc_attr($id); ?>" <?php selected($selected_id,$id); ?>>
                            <?php echo esc_html($test->get_name()); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </form>
                <?php if ($selected): ?>
                <div class="psy-hint-block">
                    <div><strong>ID:</strong> <code><?php echo esc_html($selected->get_id()); ?></code></div>
                    <div><strong>سوالات:</strong> <?php echo count($selected->get_questions()); ?></div>
                    <div><strong>دسته:</strong> <?php echo esc_html($selected->get_category()); ?></div>
                    <div><strong>نسخه:</strong> <?php echo esc_html($selected->get_version()); ?></div>
                </div>
                <?php endif; ?>
            </div>

            <!-- پنل scoring -->
            <?php if ( $selected ): ?>
            <div>
                <?php
                $questions = $selected->get_questions();
                $scale     = $selected->get_scale();
                $s_min     = (int)( $scale['min'] ?? 0 );
                $s_max     = (int)( $scale['max'] ?? 4 );

                // پاسخ‌های ارسال‌شده یا random
                $answers   = [];
                $ran_result = null;

                if ( isset($_GET['scorer_run']) ) {
                    if ( sanitize_key( wp_unslash( $_GET['scorer_run'] ) ) === 'random' ) {
                        foreach ( $questions as $qnum => $_ ) {
                            $answers[$qnum] = wp_rand( $s_min, $s_max );
                        }
                    } else {
                        foreach ( $questions as $qnum => $_ ) {
                            $key = 'q_' . $qnum;
                            if ( isset($_GET[$key]) ) $answers[$qnum] = (int) wp_unslash( $_GET[$key] );
                        }
                    }

                    // اجرای scoring
                    try {
                        $ran_result = $selected->calculate( $answers );
                    } catch ( \Throwable $e ) {
                        $ran_result = [ '_error' => $e->getMessage() ];
                    }
                }
                ?>

                <!-- فرم پاسخ‌ها -->
                <div class="psy-card psy-card-pad psy-mb-16">
                    <div class="psy-row-between">
                        <h3 class="psy-panel-title-flush">پاسخ‌ها</h3>
                        <div class="psy-row-gap-8">
                            <a href="<?php echo esc_url(add_query_arg(['page'=>'psy-quiz-debug','tab'=>'scorer','scorer_test'=>$selected_id,'scorer_run'=>'random'], admin_url('admin.php'))); ?>"
                               class="button button-primary">🎲 پاسخ تصادفی + اجرا</a>
                        </div>
                    </div>

                    <form method="get" class="psy-scroll-panel">
                        <input type="hidden" name="page"        value="psy-quiz-debug">
                        <input type="hidden" name="tab"         value="scorer">
                        <input type="hidden" name="scorer_test" value="<?php echo esc_attr($selected_id); ?>">
                        <input type="hidden" name="scorer_run"  value="manual">

                        <?php $labels = $scale['labels'] ?? []; ?>
                        <table class="psy-debug-table is-sm">
                        <?php foreach ( $questions as $qnum => $qtext ): ?>
                        <tr>
                            <td class="psy-td-pad psy-text-mid psy-col-30 psy-text-bold"><?php echo (int)$qnum; ?></td>
                            <td class="psy-td-pad psy-line-relaxed"><?php echo esc_html($qtext); ?></td>
                            <td class="psy-td-pad psy-col-120">
                                <select name="q_<?php echo (int)$qnum; ?>" class="psy-select-sm-block">
                                    <?php for ($v = $s_min; $v <= $s_max; $v++): ?>
                                    <option value="<?php echo esc_attr((int)$v); ?>" <?php selected($answers[$qnum] ?? '', $v); ?>>
                                        <?php echo $v; ?><?php if (isset($labels[$v])): ?> – <?php echo esc_html($labels[$v]); endif; ?>
                                    </option>
                                    <?php endfor; ?>
                                </select>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        </table>

                        <div class="psy-mt-12">
                            <button type="submit" class="button button-primary">▶️ اجرای scoring</button>
                        </div>
                    </form>
                </div>

                <!-- نتیجه scoring -->
                <?php if ( $ran_result !== null ): ?>
                <div class="psy-dark-panel">
                    <div class="psy-row-between psy-row-between-tight">
                        <span class="psy-result-label">✅ خروجی calculate()</span>
                        <span class="psy-result-meta">آزمون: <?php echo esc_html($selected->get_name()); ?></span>
                    </div>
                    <?php if ( isset($ran_result['_error']) ): ?>
                    <div class="psy-dark-panel-error">
                        ❌ خطا: <?php echo esc_html($ran_result['_error']); ?>
                    </div>
                    <?php else: ?>
                    <pre><?php
                        echo esc_html( json_encode( $ran_result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT ) );
                    ?></pre>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
        <?php
    }

    // ════════════════════════════════════════════════════════════════════════
    // تب ۳: وضعیت سیستم
    // ════════════════════════════════════════════════════════════════════════
    private static function render_system(): void {
        global $wpdb;

        // ── چک‌ها ──
        $db_ver_stored  = get_option( 'psy_quiz_db_version', '—' );
        $db_ver_code    = PSY_DB_VERSION;
        $db_ver_ok      = ( $db_ver_stored === $db_ver_code );

        $pdf_ok         = get_option( 'psy_quiz_pdf_available' ) === '1';

        $col_expires    = $wpdb->get_var("SHOW COLUMNS FROM {$wpdb->prefix}psy_invitations LIKE 'expires_at'");
        $col_started    = $wpdb->get_var("SHOW COLUMNS FROM {$wpdb->prefix}psy_invitations LIKE 'started_at'");
        $tbl_couples    = $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}psy_couples'");
        $tbl_logs       = $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}psy_logs'");

        $stuck_count    = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}psy_invitations WHERE status='processing'"
        );
        $orphaned       = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}psy_attempts a
             LEFT JOIN {$wpdb->prefix}psy_invitations i ON i.id = a.invitation_id
             WHERE i.id IS NULL"
        );

        $cron_next      = wp_next_scheduled('psy_quiz_cleanup_processing');
        $cron_label     = $cron_next ? pa_to_jalali( date('Y-m-d H:i:s', $cron_next), 'Y/m/d' ) . ' ' . date('H:i', $cron_next) : '❌ ثبت نشده';

        $transient_count = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->options}
             WHERE option_name LIKE '_transient_psy_%'"
        );

        // cache options
        $log_count = Psy_Logger::counts()['total'];

        $checks = [
            [ 'نسخه Schema (کد)',    $db_ver_code,                                true ],
            [ 'نسخه Schema (ذخیره)', $db_ver_stored,                              $db_ver_ok ],
            [ 'mPDF',               $pdf_ok ? '✅ نصب شده' : '❌ نصب نشده',     $pdf_ok ],
            [ 'ستون expires_at',    $col_expires ? '✅ وجود دارد' : '❌ ندارد',   (bool)$col_expires ],
            [ 'ستون started_at',    $col_started ? '✅ وجود دارد' : '❌ ندارد',   (bool)$col_started ],
            [ 'جدول psy_couples',   $tbl_couples ? '✅ وجود دارد' : '❌ ندارد',  (bool)$tbl_couples ],
            [ 'جدول psy_logs',      $tbl_logs    ? '✅ وجود دارد' : '❌ ندارد',  (bool)$tbl_logs    ],
            [ 'Stuck processing',   $stuck_count > 0 ? "⚠️ {$stuck_count} مورد" : '✅ صفر',  $stuck_count === 0 ],
            [ 'Orphaned attempts',  $orphaned > 0 ? "⚠️ {$orphaned} رکورد" : '✅ صفر',      $orphaned === 0 ],
            [ 'WP_DEBUG',           (defined('WP_DEBUG') && WP_DEBUG) ? '🟡 فعال' : '✅ غیرفعال', true ],
            [ 'Cron بعدی',          $cron_label,                                  (bool)$cron_next ],
            [ 'Transient های پلاگین', $transient_count . ' آیتم',                true ],
            [ 'لاگ‌های ذخیره شده',  $log_count . ' رکورد',                        true ],
        ];
        ?>
        <div class="psy-grid-2col">

            <!-- جدول چک‌ها -->
            <div class="psy-card">
                <div class="psy-card-header">🩺 وضعیت کلی</div>
                <table class="psy-debug-table is-md">
                    <?php foreach ( $checks as [$label, $value, $ok] ): ?>
                    <tr class="<?php echo $ok ? 'psy-row-white' : 'psy-row-fail'; ?>">
                        <td class="psy-text-mid"><?php echo esc_html($label); ?></td>
                        <td class="psy-text-bold <?php echo $ok ? 'psy-diag-ok' : 'psy-diag-fail'; ?>"><?php echo esc_html($value); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </table>
            </div>

            <!-- Quick Actions -->
            <div class="psy-card psy-card-pad">
                <div class="psy-quick-actions-title">⚡ اقدامات سریع</div>

                <!-- پاک کردن stuck -->
                <?php if ( $stuck_count > 0 ): ?>
                <form method="post" class="psy-mb-12">
                    <?php wp_nonce_field(self::NONCE, self::NONCE); ?>
                    <input type="hidden" name="psy_debug_action" value="cleanup_stuck">
                    <button type="submit" class="button button-primary psy-btn-block">
                        🔓 پاک کردن <?php echo (int)$stuck_count; ?> invitation stuck
                    </button>
                </form>
                <?php else: ?>
                <div class="psy-notice-plain is-success psy-notice-tight-success">
                    ✅ هیچ invitation stuck وجود ندارد.
                </div>
                <?php endif; ?>

                <!-- پاک کردن کش -->
                <form method="post" class="psy-mb-12"
                      onsubmit="return confirm('کش افزونه پاک می‌شود. مطمئنی؟');">
                    <?php wp_nonce_field(self::NONCE, self::NONCE); ?>
                    <input type="hidden" name="psy_debug_action" value="clear_cache">
                    <button type="submit" class="button button-secondary psy-btn-block">
                        🧹 پاک کردن کش افزونه (<?php echo (int)$transient_count; ?> آیتم)
                    </button>
                </form>

                <!-- اجرای migration -->
                <div class="psy-notice-plain is-warning psy-notice-tight-warning">
                    <strong>Migration مجدد Schema:</strong><br>
                    برای اجرا، افزونه را deactivate و activate کنید. این کار جداول جدید را می‌سازد بدون از دست دادن داده.
                </div>
            </div>
        </div>
        <?php
    }

    // ════════════════════════════════════════════════════════════════════════
    // تب ۴: دیتابیس
    // ════════════════════════════════════════════════════════════════════════
    private static function render_db(): void {
        global $wpdb;

        // آمار جداول
        $tables = [
            'psy_invitations' => 'دعوتنامه‌ها',
            'psy_attempts'    => 'نتایج',
            'psy_couples'     => 'زوجین',
            'psy_logs'        => 'لاگ‌ها',
        ];
        $table_stats = [];
        foreach ( $tables as $tbl => $label ) {
            $full = $wpdb->prefix . $tbl;
            $exists = $wpdb->get_var("SHOW TABLES LIKE '{$full}'");
            if ( $exists ) {
                $count = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$full}");
                $size  = $wpdb->get_row("SELECT ROUND((data_length+index_length)/1024,1) AS kb FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='{$full}'");
                $table_stats[$tbl] = [ 'label' => $label, 'count' => $count, 'kb' => $size->kb ?? '—' ];
            } else {
                $table_stats[$tbl] = [ 'label' => $label, 'count' => '—', 'kb' => '—', 'missing' => true ];
            }
        }

        // جستجوی مراجع
        $search = sanitize_text_field( wp_unslash( $_GET['db_search'] ?? '' ) );
        $recent = [];
        if ( $search ) {
            $recent = $wpdb->get_results( $wpdb->prepare(
                "SELECT i.id, i.client_name, i.client_phone, i.quiz_id, i.status, i.created_at,
                        a.id as attempt_id
                 FROM {$wpdb->prefix}psy_invitations i
                 LEFT JOIN {$wpdb->prefix}psy_attempts a ON a.invitation_id = i.id
                 WHERE i.client_name LIKE %s OR i.client_phone LIKE %s OR i.access_token = %s
                 ORDER BY i.id DESC LIMIT 20",
                '%' . $wpdb->esc_like($search) . '%',
                '%' . $wpdb->esc_like($search) . '%',
                $search
            ) );
        } else {
            $recent = $wpdb->get_results(
                "SELECT i.id, i.client_name, i.client_phone, i.quiz_id, i.status, i.created_at,
                        a.id as attempt_id
                 FROM {$wpdb->prefix}psy_invitations i
                 LEFT JOIN {$wpdb->prefix}psy_attempts a ON a.invitation_id = i.id
                 ORDER BY i.id DESC LIMIT 10"
            );
        }

        $status_colors = [
            'unused'     => '#64748b',
            'processing' => '#c05621',
            'used'       => '#0f766e',
            'expired'    => '#9b2c2c',
            'revoked'    => '#475569',
        ];
        ?>
        <!-- آمار جداول -->
        <div class="psy-mini-stats">
            <?php foreach ( $table_stats as $tbl => $stat ): ?>
            <div class="psy-card psy-stat-card-plain <?php echo !empty($stat['missing']) ? 'psy-stat-card-dim' : ''; ?>">
                <div class="psy-stat-val-teal"><?php echo is_numeric($stat['count']) ? number_format((int)$stat['count']) : $stat['count']; ?></div>
                <div class="psy-stat-lbl-mid"><?php echo esc_html($stat['label']); ?></div>
                <div class="psy-stat-table-name"><?php echo esc_html($tbl); ?></div>
                <div class="psy-stat-kb"><?php echo esc_html($stat['kb']); ?> KB</div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- جستجوی مراجع -->
        <form method="get" class="psy-filter-form-tight">
            <input type="hidden" name="page" value="psy-quiz-debug">
            <input type="hidden" name="tab"  value="db">
            <input type="text" name="db_search" value="<?php echo esc_attr($search); ?>"
                   placeholder="جستجو: نام، تلفن، توکن..."
                   class="psy-input-h32 psy-input-flex-300">
            <button type="submit" class="button">🔍 جستجو</button>
            <?php if ($search): ?>
            <a href="<?php echo esc_url(add_query_arg(['page'=>'psy-quiz-debug','tab'=>'db'], admin_url('admin.php'))); ?>"
               class="button button-secondary">× پاک</a>
            <?php endif; ?>
        </form>

        <!-- جدول مراجعین -->
        <div class="psy-card">
            <div class="psy-card-header psy-card-header-plain">
                <?php echo $search ? 'نتایج جستجو برای «' . esc_html($search) . '»' : 'آخرین ۱۰ مراجع'; ?>
                (<?php echo count($recent); ?> ردیف)
            </div>
            <?php if ( empty($recent) ): ?>
            <div class="psy-card-empty">چیزی یافت نشد.</div>
            <?php else: ?>
            <table class="psy-debug-table is-sm">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>نام</th>
                        <th>تلفن</th>
                        <th>آزمون</th>
                        <th class="psy-th-center">وضعیت</th>
                        <th class="psy-th-center">Attempt</th>
                        <th>تاریخ</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ( $recent as $i => $row ):
                    $sc = $status_colors[$row->status] ?? '#475569';
                ?>
                <tr>
                    <td class="psy-text-soft psy-mono"><?php echo (int)$row->id; ?></td>
                    <td class="psy-text-bold psy-text-strong"><?php echo esc_html($row->client_name); ?></td>
                    <td class="psy-text-mid psy-mono"><?php echo esc_html($row->client_phone ?: '—'); ?></td>
                    <td><code class="psy-text-xs"><?php echo esc_html($row->quiz_id); ?></code></td>
                    <td class="psy-th-center">
                        <span class="psy-status-chip" style="background:<?php echo esc_attr($sc); ?>;">
                            <?php echo esc_html($row->status); ?>
                        </span>
                    </td>
                    <td class="psy-th-center <?php echo $row->attempt_id ? 'psy-attempt-ok' : 'psy-attempt-none'; ?>">
                        <?php echo $row->attempt_id ? '#' . (int)$row->attempt_id : '—'; ?>
                    </td>
                    <td class="psy-va-top-soft psy-text-xs">
                        <?php echo esc_html(pa_to_jalali($row->created_at, 'Y/m/d')); ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
        <?php
    }

    // ── پاک کردن کش افزونه ────────────────────────────────────────────────
    private static function do_clear_cache(): int {
        global $wpdb;
        $count = 0;

        // transient های پلاگین
        $rows = (int) $wpdb->query(
            "DELETE FROM {$wpdb->options}
             WHERE option_name LIKE '_transient_psy_%'
                OR option_name LIKE '_transient_timeout_psy_%'"
        );
        $count += $rows;

        // ریست فلگ PDF (تا دوباره چک بشه)
        delete_option( 'psy_quiz_pdf_available' );
        $count++;

        // wp object cache
        wp_cache_flush_group('psy_quiz');

        return $count;
    }
}
