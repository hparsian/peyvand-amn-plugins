<?php
/**
 * Debug Tracker — سیستم مدیریت وضعیت دیباگ فایل‌های پلاگین
 *
 * هر فایل PHP پلاگین را ردیابی می‌کند:
 *  - وضعیت دیباگ (تأیید شده / نیاز به بررسی / بررسی نشده)
 *  - آخرین تاریخ بررسی
 *  - یادداشت (باگ‌های پیدا شده، اصلاحات، نکات)
 *  - نسخه پلاگین هنگام بررسی
 *  - هشدار خودکار وقتی فایل تغییر می‌کند (checksum)
 *
 * داده‌ها در WordPress options ذخیره می‌شوند (psy_debug_tracker_data).
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class Psy_Debug_Tracker {

    const OPTION_KEY   = 'psy_debug_tracker_data';
    const NONCE_ACTION = 'psy_debug_tracker_nonce';

    // وضعیت‌های ممکن
    const STATUS_CLEAN   = 'clean';    // ✅ دیباگ شده و تأیید شده
    const STATUS_NEEDS   = 'needs';    // 🟡 نیاز به بررسی
    const STATUS_PENDING = 'pending';  // ⬜ بررسی نشده
    const STATUS_CHANGED = 'changed';  // 🔴 فایل تغییر کرده — بررسی مجدد لازم

    // ── لیست همه فایل‌های پلاگین ──────────────────────────────────────────
    private static function all_files(): array {
        $base = PSY_DIR;
        $files = [];

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator( $base, RecursiveDirectoryIterator::SKIP_DOTS )
        );

        foreach ( $iterator as $file ) {
            if ( $file->getExtension() !== 'php' ) continue;
            $rel = str_replace( $base, '', $file->getPathname() );
            $rel = ltrim( str_replace( '\\', '/', $rel ), '/' );
            // فایل خود tracker را از لیست حذف می‌کنیم تا loop نشود
            if ( $rel === 'admin/class-psy-debug-tracker.php' ) continue;
            $files[] = $rel;
        }

        sort( $files );
        return $files;
    }

    // ── خواندن داده‌های ذخیره شده ─────────────────────────────────────────
    private static function get_data(): array {
        $data = get_option( self::OPTION_KEY, [] );
        return is_array( $data ) ? $data : [];
    }

    // ── ذخیره داده‌ها ──────────────────────────────────────────────────────
    private static function save_data( array $data ): void {
        update_option( self::OPTION_KEY, $data, false );
    }

    // ── محاسبه md5 فایل (برای تشخیص تغییر) ──────────────────────────────
    private static function file_hash( string $rel ): string {
        $path = PSY_DIR . $rel;
        return file_exists( $path ) ? md5_file( $path ) : '';
    }

    // ── وضعیت واقعی یک فایل (با در نظر گرفتن تغییر checksum) ───────────
    private static function resolve_status( string $rel, array $entry ): string {
        if ( empty( $entry['status'] ) ) return self::STATUS_PENDING;

        // اگر فایل تغییر کرده باشد، وضعیت را override می‌کنیم
        if (
            $entry['status'] === self::STATUS_CLEAN &&
            ! empty( $entry['hash'] ) &&
            self::file_hash( $rel ) !== $entry['hash']
        ) {
            return self::STATUS_CHANGED;
        }

        return $entry['status'];
    }

    // ── پردازش فرم ذخیره ──────────────────────────────────────────────────
    public static function handle_save(): void {
        if ( ! current_user_can( 'manage_options' ) ) return;
        if ( ! isset( $_POST[ self::NONCE_ACTION ] ) ) return;
        if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE_ACTION ] ) ), self::NONCE_ACTION ) ) {
            wp_die( 'خطای امنیتی.' );
        }

        $data    = self::get_data();
        $files   = self::all_files();
        $action  = sanitize_key( wp_unslash( $_POST['tracker_action'] ?? '' ) );
        $target  = sanitize_text_field( wp_unslash( $_POST['target_file'] ?? '' ) );

        // ── ذخیره یک فایل خاص ──────────────────────────────────────────
        if ( $action === 'save_file' && in_array( $target, $files, true ) ) {
            $status = sanitize_key( wp_unslash( $_POST['status'] ?? self::STATUS_PENDING ) );
            $note   = sanitize_textarea_field( wp_unslash( $_POST['note'] ?? '' ) );

            if ( ! in_array( $status, [ self::STATUS_CLEAN, self::STATUS_NEEDS, self::STATUS_PENDING ], true ) ) {
                $status = self::STATUS_PENDING;
            }

            $hash = ( $status === self::STATUS_CLEAN ) ? self::file_hash( $target ) : ( $data[ $target ]['hash'] ?? '' );

            $data[ $target ] = [
                'status'        => $status,
                'note'          => $note,
                'last_reviewed' => current_time( 'Y-m-d H:i' ),
                'version'       => PSY_VERSION,
                'hash'          => $hash,
            ];

            self::save_data( $data );
            wp_safe_redirect( add_query_arg( [
                'page' => 'psy-quiz-debug',
                'saved' => '1',
                'file'  => urlencode( $target ),
            ], admin_url( 'admin.php' ) ) );
            exit;
        }

        // ── علامت زدن همه فایل‌ها به عنوان "نیاز به بررسی" (ریست) ──────
        if ( $action === 'reset_all' ) {
            foreach ( $files as $f ) {
                $data[ $f ] = array_merge( $data[ $f ] ?? [], [
                    'status' => self::STATUS_NEEDS,
                    'note'   => $data[ $f ]['note'] ?? '',
                    'hash'   => '',
                ] );
            }
            self::save_data( $data );
            wp_safe_redirect( add_query_arg( [ 'page' => 'psy-quiz-debug', 'reset' => '1' ], admin_url( 'admin.php' ) ) );
            exit;
        }
    }

    // ── رندر صفحه اصلی ────────────────────────────────────────────────────
    public static function render(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'دسترسی غیرمجاز.' );
        }

        self::handle_save();

        $data  = self::get_data();
        $files = self::all_files();

        // آمار
        $counts = [ self::STATUS_CLEAN => 0, self::STATUS_NEEDS => 0, self::STATUS_PENDING => 0, self::STATUS_CHANGED => 0 ];
        foreach ( $files as $f ) {
            $entry  = $data[ $f ] ?? [];
            $status = self::resolve_status( $f, $entry );
            $counts[ $status ] = ( $counts[ $status ] ?? 0 ) + 1;
        }
        $total = count( $files );

        // فایل انتخاب‌شده برای ویرایش
        $edit_file = isset( $_GET['edit'] ) ? sanitize_text_field( wp_unslash( $_GET['edit'] ) ) : '';
        if ( $edit_file && ! in_array( $edit_file, $files, true ) ) $edit_file = '';

        $status_labels = [
            self::STATUS_CLEAN   => '✅ دیباگ شده',
            self::STATUS_NEEDS   => '🟡 نیاز به بررسی',
            self::STATUS_PENDING => '⬜ بررسی نشده',
            self::STATUS_CHANGED => '🔴 تغییر کرده',
        ];

        $status_colors = [
            self::STATUS_CLEAN   => '#d4edda',
            self::STATUS_NEEDS   => '#fff3cd',
            self::STATUS_PENDING => '#f8f9fa',
            self::STATUS_CHANGED => '#f8d7da',
        ];

        // گروه‌بندی فایل‌ها
        $groups = [
            'هسته اصلی'  => [],
            'ماژول‌ها'   => [],
            'ادمین'       => [],
            'تست‌ها'      => [],
            'سایر'        => [],
        ];
        foreach ( $files as $f ) {
            if ( str_starts_with( $f, 'includes/core/' ) || $f === 'includes/core/interface-test.php' ) {
                $groups['هسته اصلی'][] = $f;
            } elseif ( str_starts_with( $f, 'includes/tests/' ) ) {
                $groups['تست‌ها'][] = $f;
            } elseif ( str_starts_with( $f, 'admin/' ) ) {
                $groups['ادمین'][] = $f;
            } elseif ( str_starts_with( $f, 'includes/' ) ) {
                $groups['ماژول‌ها'][] = $f;
            } else {
                $groups['سایر'][] = $f;
            }
        }

        ?>
        <div class="wrap psy-admin" dir="rtl">
            <h1>
                🔍 Debug Tracker
                <span class="psy-h1-sub">v<?php echo esc_html( PSY_VERSION ); ?></span>
            </h1>

            <?php if ( isset( $_GET['saved'] ) ): ?>
            <div class="notice notice-success is-dismissible">
                <p>✅ وضعیت فایل <strong><?php echo esc_html( sanitize_text_field( urldecode( wp_unslash( $_GET['file'] ?? '' ) ) ) ); ?></strong> ذخیره شد.</p>
            </div>
            <?php endif; ?>

            <?php if ( isset( $_GET['reset'] ) ): ?>
            <div class="notice notice-warning is-dismissible">
                <p>🔄 همه فایل‌ها به «نیاز به بررسی» ریست شدند.</p>
            </div>
            <?php endif; ?>

            <!-- آمار خلاصه -->
            <div class="psy-mini-stats">
                <?php
                $stat_items = [
                    [ '✅', 'دیباگ شده',     $counts[ self::STATUS_CLEAN ],   '#28a745', '#d4edda' ],
                    [ '🟡', 'نیاز به بررسی', $counts[ self::STATUS_NEEDS ],   '#856404', '#fff3cd' ],
                    [ '🔴', 'تغییر کرده',    $counts[ self::STATUS_CHANGED ], '#721c24', '#f8d7da' ],
                    [ '⬜', 'بررسی نشده',    $counts[ self::STATUS_PENDING ], '#495057', '#f8f9fa' ],
                    [ '📁', 'کل فایل‌ها',    $total,                          '#0056b3', '#cce5ff' ],
                ];
                foreach ( $stat_items as [ $icon, $label, $count, $color, $bg ] ):
                ?>
                <div class="psy-mini-stat <?php echo esc_attr( \PsychAssessments\Core\ReportHTML::tone( $color ) ); ?>">
                    <div class="psy-mini-stat-val"><?php echo (int)$count; ?></div>
                    <div class="psy-mini-stat-lbl"><?php echo $icon . ' ' . esc_html($label); ?></div>
                </div>
                <?php endforeach; ?>

                <!-- نوار پیشرفت -->
                <?php if ( $total > 0 ): ?>
                <div class="psy-progress-panel">
                    <div class="psy-progress-caption">پیشرفت دیباگ</div>
                    <div class="psy-progress-track">
                        <?php $clean_pct = (int) round( $counts[ self::STATUS_CLEAN ] / $total * 100 ); ?>
                        <div class="psy-progress-fill psy-w--<?php echo (int) ( round( $clean_pct / 5 ) * 5 ); ?>"></div>
                    </div>
                    <div class="psy-progress-lbl">
                        <?php echo round($counts[self::STATUS_CLEAN]/$total*100); ?>% کامل
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- دکمه ریست -->
            <form method="post" class="psy-inline-form" onsubmit="return confirm('همه وضعیت‌ها به «نیاز به بررسی» تغییر می‌کنند. مطمئنی؟');">
                <?php wp_nonce_field( self::NONCE_ACTION, self::NONCE_ACTION ); ?>
                <input type="hidden" name="tracker_action" value="reset_all">
                <button type="submit" class="button button-secondary psy-mb-16">
                    🔄 ریست همه به «نیاز به بررسی»
                </button>
            </form>

            <div class="psy-grid-side <?php echo $edit_file ? 'psy-grid-side--edit' : ''; ?>">

                <!-- جدول فایل‌ها -->
                <div>
                <?php foreach ( $groups as $group_name => $group_files ):
                    if ( empty( $group_files ) ) continue;
                ?>
                    <h3 class="psy-group-title">
                        <?php echo esc_html( $group_name ); ?>
                        <span class="psy-h1-sub">(<?php echo count($group_files); ?> فایل)</span>
                    </h3>
                    <table class="widefat striped psy-table psy-mb-8">
                        <thead>
                            <tr>
                                <th class="psy-col-40pct">فایل</th>
                                <th class="psy-col-20pct">وضعیت</th>
                                <th class="psy-col-15pct">آخرین بررسی</th>
                                <th class="psy-col-10pct">نسخه</th>
                                <th class="psy-col-15pct">عملیات</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ( $group_files as $f ):
                            $entry  = $data[ $f ] ?? [];
                            $status = self::resolve_status( $f, $entry );
                            $label  = $status_labels[ $status ] ?? '⬜ نامشخص';
                            $bg     = $status_colors[ $status ] ?? '#fff';
                            $is_editing = ( $edit_file === $f );
                        ?>
                            <tr class="psy-row-tint <?php echo esc_attr( \PsychAssessments\Core\ReportHTML::tone( (string) $bg ) ); ?> <?php echo $is_editing ? 'psy-editing-outline' : ''; ?>">
                                <td class="psy-file-cell">
                                    <?php echo esc_html( $f ); ?>
                                    <?php if ( ! empty( $entry['note'] ) ): ?>
                                        <span title="<?php echo esc_attr( $entry['note'] ); ?>" class="psy-help-icon">💬</span>
                                    <?php endif; ?>
                                    <?php if ( $status === self::STATUS_CHANGED ): ?>
                                        <br><small class="psy-file-changed-note">⚠️ فایل از آخرین بررسی تغییر کرده</small>
                                    <?php endif; ?>
                                </td>
                                <td><strong><?php echo esc_html( $label ); ?></strong></td>
                                <td class="psy-td-soft"><?php echo esc_html( $entry['last_reviewed'] ?? '—' ); ?></td>
                                <td class="psy-td-soft"><?php echo esc_html( $entry['version'] ?? '—' ); ?></td>
                                <td>
                                    <a href="<?php echo esc_url( add_query_arg( [ 'page' => 'psy-quiz-debug', 'edit' => urlencode($f) ], admin_url('admin.php') ) ); ?>"
                                       class="button button-small">
                                        ✏️ ویرایش
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endforeach; ?>
                </div>

                <!-- پنل ویرایش -->
                <?php if ( $edit_file ):
                    $entry  = $data[ $edit_file ] ?? [];
                    $status = self::resolve_status( $edit_file, $entry );
                ?>
                <div class="psy-card psy-card-pad psy-sticky-panel">
                    <h3 class="psy-mt-0 psy-person-1-text">✏️ ویرایش وضعیت</h3>
                    <code class="psy-file-path-box">
                        <?php echo esc_html( $edit_file ); ?>
                    </code>

                    <?php if ( $status === self::STATUS_CHANGED ): ?>
                    <div class="psy-notice-plain is-error psy-error-box-tight">
                        ⚠️ <strong>این فایل از آخرین بررسی تغییر کرده.</strong> لطفاً مجدداً بررسی کنید.
                    </div>
                    <?php endif; ?>

                    <form method="post">
                        <?php wp_nonce_field( self::NONCE_ACTION, self::NONCE_ACTION ); ?>
                        <input type="hidden" name="tracker_action" value="save_file">
                        <input type="hidden" name="target_file" value="<?php echo esc_attr( $edit_file ); ?>">

                        <label class="psy-field-label-block">وضعیت فایل:</label>
                        <div class="psy-radio-stack">
                            <?php
                            $options = [
                                self::STATUS_CLEAN   => '✅ دیباگ شده — تأیید شده',
                                self::STATUS_NEEDS   => '🟡 نیاز به بررسی',
                                self::STATUS_PENDING => '⬜ بررسی نشده',
                            ];
                            $current_status = in_array( $entry['status'] ?? '', array_keys($options), true )
                                ? $entry['status']
                                : self::STATUS_PENDING;
                            foreach ( $options as $val => $lbl ):
                            ?>
                            <label class="psy-radio-option psy-bg-tint <?php echo esc_attr( \PsychAssessments\Core\ReportHTML::tone( (string) $status_colors[$val] ) ); ?>">
                                <input type="radio" name="status" value="<?php echo esc_attr($val); ?>" <?php checked($current_status, $val); ?>>
                                <?php echo esc_html($lbl); ?>
                            </label>
                            <?php endforeach; ?>
                        </div>

                        <label class="psy-field-label-block">یادداشت (باگ‌ها، اصلاحات، نکات):</label>
                        <textarea
                            name="note"
                            rows="6"
                            class="psy-textarea-plain"
                            placeholder="مثال: سوال ۳ reversal اشتباه داشت — اصلاح شد در v3.1.4" aria-label="مثال: سوال ۳ reversal اشتباه داشت — اصلاح شد در v3.1.4"
                        ><?php echo esc_textarea( $entry['note'] ?? '' ); ?></textarea>

                        <?php if ( ! empty( $entry['last_reviewed'] ) ): ?>
                        <p class="psy-hint-muted">
                            آخرین بررسی: <?php echo esc_html($entry['last_reviewed']); ?> (نسخه <?php echo esc_html($entry['version'] ?? '—'); ?>)
                        </p>
                        <?php endif; ?>

                        <div class="psy-btn-row">
                            <button type="submit" class="button button-primary">💾 ذخیره</button>
                            <a href="<?php echo esc_url( add_query_arg( ['page'=>'psy-quiz-debug'], admin_url('admin.php') ) ); ?>"
                               class="button button-secondary">انصراف</a>
                        </div>
                    </form>
                </div>
                <?php endif; ?>

            </div><!-- /grid -->

        </div><!-- /wrap -->
        <?php
    }
}
