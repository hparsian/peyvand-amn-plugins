<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Psy_Page_Couples — مقایسه زوجین (قصه عشق)
 */
class Psy_Page_Couples {

    private static function handle_new_couple(): ?array {
        if ( ! isset( $_POST['psy_new_couple_nonce'] ) ) return null;
        if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['psy_new_couple_nonce'] ) ), 'psy_new_couple' ) ) {
            return [ 'error' => 'خطای امنیتی. لطفاً دوباره امتحان کنید.' ];
        }

        $label   = sanitize_text_field( wp_unslash( $_POST['couple_label']  ?? '' ) );
        $att1_id = absint( $_POST['attempt1_id'] ?? 0 );
        $att2_id = absint( $_POST['attempt2_id'] ?? 0 );

        if ( ! $label ) return [ 'error' => 'نام زوج الزامی است.' ];
        if ( ! $att1_id || ! $att2_id ) return [ 'error' => 'انتخاب هر دو مراجع الزامی است.' ];
        if ( $att1_id === $att2_id ) return [ 'error' => 'دو مراجع باید متفاوت باشند.' ];

        global $wpdb;

        // بررسی وجود هر دو attempt با quiz_id = love_story
        $ok1 = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}psy_attempts WHERE id = %d AND quiz_id = 'love_story'", $att1_id
        ) );
        $ok2 = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}psy_attempts WHERE id = %d AND quiz_id = 'love_story'", $att2_id
        ) );

        if ( ! $ok1 || ! $ok2 ) {
            return [ 'error' => 'یک یا هر دو attempt انتخاب‌شده معتبر نیستند یا از آزمون قصه عشق نمی‌باشند.' ];
        }

        // بررسی تکراری نبودن
        $dup = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}psy_couples
             WHERE (attempt1_id = %d AND attempt2_id = %d)
                OR (attempt1_id = %d AND attempt2_id = %d)",
            $att1_id, $att2_id, $att2_id, $att1_id
        ) );
        if ( $dup ) return [ 'error' => 'این زوج قبلاً ثبت شده است.' ];

        $inserted = $wpdb->insert( $wpdb->prefix . 'psy_couples', [
            'label'       => $label,
            'attempt1_id' => $att1_id,
            'attempt2_id' => $att2_id,
            'created_at'  => current_time( 'mysql' ), // هم‌پایه با pa_to_jalali؛ به DEFAULT دیتابیس تکیه نمی‌کنیم
        ] );
        $couple_id = $wpdb->insert_id;

        if ( $inserted === false || ! $couple_id ) {
            psy_log( 'error', 'couples', 'insert failed', [ 'db_error' => $wpdb->last_error ] );
            return [ 'error' => 'خطا در ذخیره‌سازی. لطفاً دوباره تلاش کنید.' ];
        }

        // PRG: redirect بعد از موفقیت تا F5 دوباره POST نکند
        wp_safe_redirect( add_query_arg( [
            'page' => 'psy-quiz-couples',
            'done' => 'created',
            'cid'  => $couple_id,
        ], admin_url( 'admin.php' ) ) );
        exit;
    }

    /**
     * پردازش حذف زوج
     */
    private static function handle_delete_couple(): void {
        if ( ! isset( $_GET['psy_action'], $_GET['couple_id'], $_GET['_wpnonce'] ) ) return;
        if ( sanitize_key( wp_unslash( $_GET['psy_action'] ) ) !== 'delete_couple' ) return;
        if ( ! current_user_can( 'manage_options' ) ) return;

        $couple_id = absint( $_GET['couple_id'] );
        if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'psy_delete_couple_' . $couple_id ) ) {
            wp_die( 'خطای امنیتی.', 403 );
        }

        global $wpdb;
        $wpdb->delete( $wpdb->prefix . 'psy_couples', [ 'id' => $couple_id ], [ '%d' ] );

        wp_safe_redirect( add_query_arg( [ 'page' => 'psy-quiz-couples', 'done' => 'deleted' ], admin_url( 'admin.php' ) ) );
        exit;
    }

    /**
     * صفحه اصلی زوجین — لیست + فرم ثبت
     */
    public static function render_couples(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'دسترسی غیرمجاز.', 'pivand-amn-quiz-enterprise' ), 403 );
        }

        // اگر درخواست مقایسه بود، صفحه مقایسه را نشان بده
        if ( isset( $_GET['action'] ) && $_GET['action'] === 'compare' && isset( $_GET['couple_id'] ) ) {
            self::render_couple_compare( absint( $_GET['couple_id'] ) );
            return;
        }

        self::handle_delete_couple();

        $result = self::handle_new_couple();

        global $wpdb;

        // لیست attempt های LoveStory تکمیل‌شده — برای dropdown
        // باگ #A2: هرچند quiz_id ثابت است، $wpdb->prepare() استاندارد WP را رعایت می‌کند
        $love_attempts = $wpdb->get_results( $wpdb->prepare(
            "SELECT a.id, a.completed_at, i.client_name, i.client_phone
             FROM {$wpdb->prefix}psy_attempts a
             JOIN {$wpdb->prefix}psy_invitations i ON i.id = a.invitation_id
             WHERE a.quiz_id = %s
             ORDER BY a.id DESC
             LIMIT 500",
            'love_story'
        ) );

        // لیست زوج‌های ثبت‌شده
        // بدون پارامتر کاربری — table prefix توسط WP تضمین‌شده است.
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $couples = $wpdb->get_results(
            "SELECT c.*,
                    COALESCE(i1.client_name,  '(حذف‌شده)') AS name1,
                    COALESCE(i1.client_phone, '')            AS phone1,
                    COALESCE(i2.client_name,  '(حذف‌شده)') AS name2,
                    COALESCE(i2.client_phone, '')            AS phone2
             FROM {$wpdb->prefix}psy_couples c
             LEFT JOIN {$wpdb->prefix}psy_attempts a1 ON a1.id = c.attempt1_id
             LEFT JOIN {$wpdb->prefix}psy_attempts a2 ON a2.id = c.attempt2_id
             LEFT JOIN {$wpdb->prefix}psy_invitations i1 ON i1.id = a1.invitation_id
             LEFT JOIN {$wpdb->prefix}psy_invitations i2 ON i2.id = a2.invitation_id
             ORDER BY c.id DESC
             LIMIT 200"
        );
        ?>
        <div class="wrap psy-admin" dir="rtl">
            <h1 class="wp-heading-inline">💑 مقایسه زوجین — آزمون قصه عشق</h1>
            <hr class="wp-header-end">

            <?php if ( isset($result['error']) ): ?>
            <div class="notice notice-error is-dismissible"><p><?php echo esc_html($result['error']); ?></p></div>
            <?php elseif ( isset($_GET['done']) && $_GET['done'] === 'created' ): ?>
            <div class="notice notice-success is-dismissible"><p>✅ زوج با موفقیت ثبت شد.</p></div>
            <?php elseif ( isset($_GET['done']) && $_GET['done'] === 'deleted' ): ?>
            <div class="notice notice-success is-dismissible"><p>🗑️ زوج حذف شد.</p></div>
            <?php endif; ?>

            <?php if ( empty($love_attempts) ): ?>
            <div class="notice notice-warning"><p>⚠️ هنوز هیچ آزمون قصه عشق تکمیل‌شده‌ای ثبت نشده است. ابتدا برای مراجعین لینک ارسال کنید.</p></div>
            <?php else: ?>

            <?php // ── فرم ثبت زوج جدید ───────────────────────────────────── ?>
            <div class="psy-form-card psy-mb-28">
                <h2 class="psy-card-title-flush">➕ ثبت زوج جدید</h2>
                <form method="post">
                    <?php wp_nonce_field('psy_new_couple', 'psy_new_couple_nonce'); ?>
                    <table class="form-table psy-table-max-680">
                        <tr>
                            <th><label for="couple_label">نام / برچسب زوج <span class="psy-required">*</span></label></th>
                            <td><input type="text" name="couple_label" id="couple_label" class="regular-text" required placeholder="مثال: علی و مریم" autofocus></td>
                        </tr>
                        <tr>
                            <th><label for="attempt1_id">مراجع اول <span class="psy-required">*</span></label></th>
                            <td>
                                <select name="attempt1_id" id="attempt1_id" required class="psy-min-w-340">
                                    <option value="">— انتخاب مراجع —</option>
                                    <?php foreach ( $love_attempts as $a ): ?>
                                    <option value="<?php echo esc_attr($a->id); ?>">
                                        <?php echo esc_html($a->client_name); ?>
                                        <?php if ($a->client_phone): ?>(<?php echo esc_html($a->client_phone); ?>)<?php endif; ?>
                                        — <?php echo esc_html( pa_to_jalali($a->completed_at, 'Y/m/d') ); ?>
                                        [#<?php echo esc_html($a->id); ?>]
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="attempt2_id">مراجع دوم <span class="psy-required">*</span></label></th>
                            <td>
                                <select name="attempt2_id" id="attempt2_id" required class="psy-min-w-340">
                                    <option value="">— انتخاب مراجع —</option>
                                    <?php foreach ( $love_attempts as $a ): ?>
                                    <option value="<?php echo esc_attr($a->id); ?>">
                                        <?php echo esc_html($a->client_name); ?>
                                        <?php if ($a->client_phone): ?>(<?php echo esc_html($a->client_phone); ?>)<?php endif; ?>
                                        — <?php echo esc_html( pa_to_jalali($a->completed_at, 'Y/m/d') ); ?>
                                        [#<?php echo esc_html($a->id); ?>]
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                    </table>
                    <p class="submit">
                        <button type="submit" class="button button-primary button-hero" id="psy-couple-submit">💑 ثبت زوج</button>
                    </p>
                </form>
                <script>
                (function(){
                    var s1 = document.getElementById('attempt1_id');
                    var s2 = document.getElementById('attempt2_id');
                    var btn = document.getElementById('psy-couple-submit');
                    function check() {
                        if ( s1.value && s2.value && s1.value === s2.value ) {
                            btn.disabled = true;
                            btn.title = 'مراجع اول و دوم نمی‌توانند یکسان باشند';
                            s1.classList.add('psy-select-invalid');
                            s2.classList.add('psy-select-invalid');
                        } else {
                            btn.disabled = false;
                            btn.title = '';
                            s1.classList.remove('psy-select-invalid');
                            s2.classList.remove('psy-select-invalid');
                        }
                    }
                    s1.addEventListener('change', check);
                    s2.addEventListener('change', check);
                })();
                </script>
            </div>
            <?php endif; ?>

            <?php // ── لیست زوج‌های ثبت‌شده ──────────────────────────────── ?>
            <?php if ( ! empty($couples) ): ?>
            <h2 class="psy-section-title">📋 زوج‌های ثبت‌شده</h2>
            <table class="wp-list-table widefat fixed striped psy-table-max-900">
                <thead>
                    <tr>
                        <th class="psy-col-40">#</th>
                        <th>نام زوج</th>
                        <th>مراجع اول</th>
                        <th>مراجع دوم</th>
                        <th class="psy-col-120">تاریخ ثبت</th>
                        <th class="psy-col-160">عملیات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $couples as $c ):
                        $compare_url = add_query_arg([
                            'page'      => 'psy-quiz-couples',
                            'action'    => 'compare',
                            'couple_id' => $c->id,
                        ], admin_url('admin.php'));
                        $delete_url = wp_nonce_url( add_query_arg([
                            'page'       => 'psy-quiz-couples',
                            'psy_action' => 'delete_couple',
                            'couple_id'  => $c->id,
                        ], admin_url('admin.php')), 'psy_delete_couple_' . $c->id );
                    ?>
                    <tr>
                        <td class="psy-text-soft"><?php echo esc_html($c->id); ?></td>
                        <td><strong><?php echo esc_html($c->label); ?></strong></td>
                        <td>
                            <?php echo esc_html($c->name1); ?>
                            <?php if ($c->phone1): ?><br><small class="psy-text-soft"><?php echo esc_html($c->phone1); ?></small><?php endif; ?>
                        </td>
                        <td>
                            <?php echo esc_html($c->name2); ?>
                            <?php if ($c->phone2): ?><br><small class="psy-text-soft"><?php echo esc_html($c->phone2); ?></small><?php endif; ?>
                        </td>
                        <td class="psy-text-soft psy-text-xs"><?php echo esc_html( pa_to_jalali($c->created_at, 'Y/m/d') ); ?></td>
                        <td>
                            <a href="<?php echo esc_url($compare_url); ?>" class="button button-primary button-small">🔍 مشاهده مقایسه</a>
                            <a href="<?php echo esc_url($delete_url); ?>" class="button button-small"
                               onclick="return confirm('زوج حذف شود؟')">🗑️</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
            <div class="psy-notice-plain">هنوز زوجی ثبت نشده است.</div>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * صفحه مقایسه side-by-side دو مراجع
     */
    private static function render_couple_compare( int $couple_id ): void {
        global $wpdb;

        $couple = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}psy_couples WHERE id = %d", $couple_id
        ) );

        if ( ! $couple ) {
            echo '<div class="wrap psy-admin" dir="rtl">';
            echo '<h1>💑 مقایسه زوجین</h1>';
            echo '<div class="psy-notice-plain is-error">زوج یافت نشد.</div>';
            echo '<p class="psy-mt-16"><a href="' . esc_url( add_query_arg( [ 'page' => 'psy-quiz-couples' ], admin_url('admin.php') ) ) . '" class="button">← بازگشت به لیست</a></p>';
            echo '</div>';
            return;
        }

        // بارگذاری داده‌های دو attempt
        $att1 = $wpdb->get_row( $wpdb->prepare(
            "SELECT a.result_data, a.completed_at, i.client_name, i.client_phone
             FROM {$wpdb->prefix}psy_attempts a
             JOIN {$wpdb->prefix}psy_invitations i ON i.id = a.invitation_id
             WHERE a.id = %d", $couple->attempt1_id
        ) );
        $att2 = $wpdb->get_row( $wpdb->prepare(
            "SELECT a.result_data, a.completed_at, i.client_name, i.client_phone
             FROM {$wpdb->prefix}psy_attempts a
             JOIN {$wpdb->prefix}psy_invitations i ON i.id = a.invitation_id
             WHERE a.id = %d", $couple->attempt2_id
        ) );

        if ( ! $att1 || ! $att2 ) {
            echo '<div class="wrap psy-admin" dir="rtl">';
            echo '<h1>💑 مقایسه: ' . esc_html( $couple->label ) . '</h1>';
            echo '<div class="psy-notice-plain is-error">داده‌های یک یا هر دو مراجع یافت نشد.</div>';
            echo '<p class="psy-mt-16"><a href="' . esc_url( add_query_arg( [ 'page' => 'psy-quiz-couples' ], admin_url('admin.php') ) ) . '" class="button">← بازگشت به لیست</a></p>';
            echo '</div>';
            return;
        }

        // BUGFIX: همان باگ رمزگشایی‌فراموش‌شده که در صفحه گزارش تکی بود، اینجا هم
        // وجود داشت — json_decode مستقیم روی result_data رمزنگاری‌شده شکست می‌خورد
        // (بی‌کرش چون نتیجه‌اش با ?? [] پوشش داده می‌شد، اما داده‌ها بی‌صدا خالی می‌ماندند).
        $scores1 = pa_decrypt_json( $att1->result_data );
        $scores2 = pa_decrypt_json( $att2->result_data );

        $schema1 = $scores1['schema_scores'] ?? [];
        $schema2 = $scores2['schema_scores'] ?? [];
        $top1    = $scores1['top_story']    ?? '';
        $top2    = $scores2['top_story']    ?? '';
        $flags1  = $scores1['clinical_flags'] ?? [];
        $flags2  = $scores2['clinical_flags'] ?? [];

        // تمام story label ها از LoveStory — برای نمودار
        // (کلیدها را از داده واقعی می‌گیریم تا با هر نسخه سازگار باشیم)
        $all_keys = array_unique( array_merge( array_keys($schema1), array_keys($schema2) ) );

        // برچسب‌ها و رنگ‌ها مستقیماً از نمونه LoveStory در Registry خوانده می‌شوند
        // تا با render_report() و گزارش فردی همیشه همخوان باشند.
        $story_labels = [];
        $_ls = \PsychAssessments\Core\Registry::get( 'love_story' );
        if ( $_ls ) {
            foreach ( $_ls->get_schema_labels() as $_key => $_lbl ) {
                $story_labels[ $_key ] = [
                    'label' => $_lbl,
                    'color' => $_ls->get_story_color( $_key ),
                ];
            }
        }

        // مرتب‌سازی بر اساس میانگین دو نفر (نزولی)
        $sorted_keys = $all_keys;
        usort( $sorted_keys, function($a, $b) use ($schema1, $schema2) {
            $avg_a = ( ($schema1[$a] ?? 0) + ($schema2[$a] ?? 0) ) / 2;
            $avg_b = ( ($schema1[$b] ?? 0) + ($schema2[$b] ?? 0) ) / 2;
            return $avg_b <=> $avg_a;
        });

        $back_url = add_query_arg( [ 'page' => 'psy-quiz-couples' ], admin_url('admin.php') );

        // ── داده‌های bar chart برای Chart.js ──
        $chart_labels = [];
        $chart_d1     = [];
        $chart_d2     = [];
        foreach ( $sorted_keys as $k ) {
            $chart_labels[] = $story_labels[$k]['label'] ?? $k;
            $chart_d1[]     = round( $schema1[$k] ?? 0, 2 );
            $chart_d2[]     = round( $schema2[$k] ?? 0, 2 );
        }
        ?>
        <div class="wrap psy-admin" dir="rtl">
            <h1>💑 مقایسه: <?php echo esc_html($couple->label); ?></h1>
            <a href="<?php echo esc_url($back_url); ?>" class="button psy-mb-20">← بازگشت به لیست</a>
            <hr class="wp-header-end">

            <?php // ── کارت‌های معرفی دو مراجع ── ?>
            <div class="psy-couple-cards">
                <?php foreach ([
                    [ $att1->client_name, $att1->client_phone, $att1->completed_at, $top1, 1 ],
                    [ $att2->client_name, $att2->client_phone, $att2->completed_at, $top2, 2 ],
                ] as $i => $p ):
                    $top_label = $story_labels[$p[3]]['label'] ?? $p[3];
                    $pn        = $p[4]; // شماره‌ی مراجع: ۱ یا ۲ — تعیین‌کننده‌ی رنگ برند (teal/rose)
                ?>
                <div class="psy-couple-person-card psy-person-<?php echo $pn; ?>-bg">
                    <div class="psy-couple-person-label">مراجع <?php echo esc_html((int)($i+1)); ?></div>
                    <div class="psy-couple-person-name psy-person-<?php echo $pn; ?>-text"><?php echo esc_html($p[0]); ?></div>
                    <?php if ($p[1]): ?><div class="psy-couple-person-meta">📱 <?php echo esc_html($p[1]); ?></div><?php endif; ?>
                    <div class="psy-couple-person-meta psy-couple-person-meta-tight">📅 <?php echo esc_html( pa_to_jalali($p[2], 'Y/m/d') ); ?></div>
                    <div class="psy-couple-person-badge psy-person-<?php echo $pn; ?>-solid">💘 قصه غالب: <?php echo esc_html($top_label); ?></div>
                </div>
                <?php endforeach; ?>
            </div>

            <?php // ── پرچم‌های بالینی مشترک یا خاص هر کدام ── ?>
            <?php if ( !empty($flags1) || !empty($flags2) ): ?>
            <div class="psy-flags-box">
                <h3>⚠️ پرچم‌های بالینی</h3>
                <div class="psy-flags-row">
                    <?php foreach ([
                        [ $att1->client_name, $flags1, 1 ],
                        [ $att2->client_name, $flags2, 2 ],
                    ] as $pf ):
                        if ( empty($pf[1]) ) continue; ?>
                    <div class="psy-flags-col">
                        <strong class="psy-person-<?php echo $pf[2]; ?>-text"><?php echo esc_html($pf[0]); ?>:</strong>
                        <?php foreach ( $pf[1] as $flag ): ?>
                        <p class="psy-flags-msg">• <?php echo esc_html($flag['message']); ?></p>
                        <?php endforeach; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <?php // ── نمودار bar مقایسه‌ای ── ?>
            <div class="psy-panel psy-panel-pad">
                <h3 class="psy-panel-title">📊 نمودار مقایسه‌ای (مرتب‌شده بر اساس میانگین)</h3>
                <canvas id="psy-couple-chart" class="psy-chart-tall"></canvas>
            </div>

            <?php // ── جدول مقایسه کامل ── ?>
            <div class="psy-panel psy-panel-clip">
                <h3 class="psy-panel-title-bordered">📋 جدول مقایسه تمام قصه‌ها</h3>
                <table class="psy-compare-table">
                    <thead>
                        <tr>
                            <th class="psy-col-right">قصه</th>
                            <th class="psy-col-center psy-col-120 psy-person-1-text"><?php echo esc_html($att1->client_name); ?></th>
                            <th class="psy-col-center psy-col-120 psy-person-2-text"><?php echo esc_html($att2->client_name); ?></th>
                            <th class="psy-col-center psy-col-80">اختلاف</th>
                            <th class="psy-col-right">مقایسه بصری</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $sorted_keys as $k ):
                            $s1  = round( $schema1[$k] ?? 0, 1 );
                            $s2  = round( $schema2[$k] ?? 0, 1 );
                            $diff = round( abs($s1 - $s2), 1 );
                            $sl  = $story_labels[$k] ?? ['label'=>$k,'color'=>'#718096'];
                            // رنگ اختلاف: teal = همسو، amber = متوسط، قرمز = زیاد
                            $diff_class = $diff <= 1.5 ? 'psy-diff-good' : ( $diff <= 3.0 ? 'psy-diff-mid' : 'psy-diff-high' );
                            $is_top1 = ($k === $top1);
                            $is_top2 = ($k === $top2);
                        ?>
                        <tr>
                            <td>
                                <span class="psy-story-dot" style="background:<?php echo esc_attr($sl['color']); ?>;"></span>
                                <strong><?php echo esc_html($sl['label']); ?></strong>
                                <?php if ($is_top1): ?><span class="psy-top-badge psy-person-1-bg">غالب ۱</span><?php endif; ?>
                                <?php if ($is_top2): ?><span class="psy-top-badge psy-person-2-bg">غالب ۲</span><?php endif; ?>
                            </td>
                            <td class="psy-col-center psy-cell-bold psy-person-1-text"><?php echo number_format( (float)$s1, 1 ); ?></td>
                            <td class="psy-col-center psy-cell-bold psy-person-2-text"><?php echo number_format( (float)$s2, 1 ); ?></td>
                            <td class="psy-col-center psy-cell-bold <?php echo $diff_class; ?>"><?php echo number_format( (float)$diff, 1 ); ?></td>
                            <td>
                                <div class="psy-compare-bar-row">
                                    <div class="psy-compare-bar-track psy-person-1-bg">
                                        <div class="psy-compare-bar-fill psy-person-1-solid" style="width:<?php echo round(($s1/9)*100); ?>%;"></div>
                                    </div>
                                    <div class="psy-compare-bar-track psy-person-2-bg">
                                        <div class="psy-compare-bar-fill psy-person-2-solid" style="width:<?php echo round(($s2/9)*100); ?>%;"></div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php // ── تحلیل همسویی ── ?>
            <?php
            $shared_top = ( $top1 === $top2 );
            $high_conflicts = [];
            foreach ( $sorted_keys as $k ) {
                $d = abs( ($schema1[$k] ?? 0) - ($schema2[$k] ?? 0) );
                if ( $d >= 3.5 ) {
                    $high_conflicts[] = [ 'key' => $k, 'label' => ($story_labels[$k]['label'] ?? $k), 'diff' => round($d,1) ];
                }
            }
            ?>
            <div class="psy-summary-box <?php echo $shared_top ? 'is-aligned' : 'is-diverging'; ?>">
                <h3>
                    <?php echo $shared_top ? esc_html('✅ همسویی قصه غالب') : esc_html('⚠️ قصه‌های غالب متفاوت'); ?>
                </h3>
                <p>
                    <?php if ($shared_top): ?>
                    هر دو مراجع قصه غالب یکسانی دارند: <strong><?php echo esc_html($story_labels[$top1]['label'] ?? $top1); ?></strong>. این همسویی می‌تواند پایه مشترکی در نگرش به رابطه ایجاد کند.
                    <?php else: ?>
                    قصه غالب مراجعین متفاوت است: <strong><?php echo esc_html($att1->client_name); ?></strong> با قصه «<?php echo esc_html($story_labels[$top1]['label'] ?? $top1); ?>» و <strong><?php echo esc_html($att2->client_name); ?></strong> با قصه «<?php echo esc_html($story_labels[$top2]['label'] ?? $top2); ?>». بررسی تعامل این دو چارچوب در جلسه درمانی توصیه می‌شود.
                    <?php endif; ?>
                </p>
                <?php if ( !empty($high_conflicts) ): ?>
                <p class="psy-summary-conflict-label">اختلاف قابل توجه (≥۳.۵) در قصه‌های زیر:</p>
                <?php foreach ($high_conflicts as $hc): ?>
                <span class="psy-conflict-chip"><?php echo esc_html($hc['label']); ?> (<?php echo $hc['diff']; ?>)</span>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>

        </div>

        <?php // ── Chart.js bar chart ── ?>
        <script>
        (function() {
            var ctx = document.getElementById('psy-couple-chart');
            if (!ctx || !window.Chart) return;
            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: <?php echo json_encode($chart_labels, JSON_UNESCAPED_UNICODE); ?>,
                    datasets: [
                        {
                            label: <?php echo json_encode($att1->client_name, JSON_UNESCAPED_UNICODE); ?>,
                            data: <?php echo json_encode($chart_d1); ?>,
                            backgroundColor: 'rgba(15,118,110,0.75)',
                            borderColor: '#0f766e',
                            borderWidth: 1,
                            borderRadius: 4,
                        },
                        {
                            label: <?php echo json_encode($att2->client_name, JSON_UNESCAPED_UNICODE); ?>,
                            data: <?php echo json_encode($chart_d2); ?>,
                            backgroundColor: 'rgba(225,29,72,0.75)',
                            borderColor: '#e11d48',
                            borderWidth: 1,
                            borderRadius: 4,
                        }
                    ]
                },
                options: {
                    indexAxis: 'y',
                    responsive: true,
                    plugins: {
                        legend: { position: 'top' },
                        tooltip: {
                            callbacks: {
                                label: function(ctx) {
                                    return ctx.dataset.label + ': ' + ctx.parsed.x.toFixed(1) + ' از ۹';
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            min: 0,
                            max: 9,
                            ticks: { stepSize: 1 },
                            grid: { color: 'rgba(0,0,0,0.06)' }
                        },
                        y: {
                            ticks: { font: { size: 11 }, color: '#4a5568' }
                        }
                    }
                }
            });
        })();
        </script>
        <?php
    }

    // ── صفحه تنظیمات ─────────────────────────────────────────────────────
}
