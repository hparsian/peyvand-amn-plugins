<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Psy_Page_Progress — مقایسه‌ی دو نوبت یک آزمون برای یک مراجع (روند تغییر در طول زمان)
 *
 * محدود به آزمون‌های دسته‌بندی «relationship» (زوج‌درمانی): CSI, CTS2, DAS, ECR,
 * FMS, LAS, LoveStory, PAM, RAS.
 *
 * برخلاف class-psy-page-couples.php (که دو *نفر* را در یک آزمون مقایسه می‌کند)،
 * این صفحه دو *نوبت* از یک آزمون توسط یک نفر را مقایسه می‌کند — یعنی مثلاً DAS
 * قبل و بعد درمان برای همان مراجع.
 *
 * چون ساختار calculate() هر آزمون رابطه‌ای فرق می‌کند (بعضی sub_scores+total
 * دارند، بعضی anxiety+avoidance، بعضی sum+avg و ...)، مقایسه‌ی خلاصه‌ی نمره‌ها
 * به‌صورت عمومی و بازگشتی روی result_data انجام می‌شود (هر مقدار عددی یا آرایه‌ی
 * عددی به‌طور خودکار diff می‌شود) — نیازی به کد جداگانه برای هر آزمون نیست.
 * مقایسه‌ی جزئیات (هر سوال) هم روی answers خام انجام می‌شود که ساختارش در همه‌ی
 * آزمون‌ها یکسان است (question_id => مقدار خام پاسخ).
 */
class Psy_Page_Progress {

    /** آیدی آزمون‌های دسته‌بندی «relationship» */
    private static function relationship_quiz_ids(): array {
        $by_cat = \PsychAssessments\Core\Registry::by_category();
        $tests  = $by_cat['relationship'] ?? [];
        return array_map( fn( $t ) => $t->get_id(), $tests );
    }

    public static function render_progress(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'دسترسی غیرمجاز.', 'pivand-amn-quiz-enterprise' ), 403 );
        }

        if ( isset( $_GET['action'] ) && $_GET['action'] === 'compare'
            && isset( $_GET['attempt1'], $_GET['attempt2'] ) ) {
            self::render_compare( absint( $_GET['attempt1'] ), absint( $_GET['attempt2'] ) );
            return;
        }

        self::render_picker();
    }

    /**
     * صفحه‌ی انتخاب: آزمون رابطه‌ای را انتخاب کن، بعد از بین مراجعینی که آن را
     * حداقل ۲ بار کامل کرده‌اند، دو نوبت را برای مقایسه انتخاب کن.
     */
    private static function render_picker(): void {
        global $wpdb;

        $rel_ids = self::relationship_quiz_ids();
        $selected_quiz = sanitize_key( wp_unslash( $_GET['quiz_id'] ?? '' ) );
        if ( ! in_array( $selected_quiz, $rel_ids, true ) ) {
            $selected_quiz = $rel_ids[0] ?? '';
        }

        $back_and_quiz_nav = admin_url( 'admin.php?page=psy-quiz-progress' );

        echo '<div class="wrap psy-admin" dir="rtl">';
        echo '<h1 class="wp-heading-inline">📈 مقایسه‌ی نوبت‌ها (پیشرفت زوج‌درمانی)</h1>';
        echo '<hr class="wp-header-end">';
        echo '<p class="description">مقایسه‌ی دو نوبت از یک آزمون رابطه‌ای برای همان مراجع — مثلاً قبل و بعد درمان.</p>';

        if ( empty( $rel_ids ) ) {
            echo '<div class="notice notice-warning"><p>هیچ آزمون دسته‌بندی «زوجین و روابط» ثبت نشده است.</p></div></div>';
            return;
        }

        // ── فرم انتخاب آزمون ──────────────────────────────────────────
        echo '<form method="get" class="psy-form-card psy-mb-28">';
        echo '<input type="hidden" name="page" value="psy-quiz-progress">';
        echo '<table class="form-table psy-table-max-680"><tr><th><label for="quiz_id">آزمون</label></th><td>';
        echo '<select name="quiz_id" id="quiz_id" onchange="this.form.submit()" class="psy-min-w-340">';
        foreach ( $rel_ids as $qid ) {
            $test = \PsychAssessments\Core\Registry::get( $qid );
            if ( ! $test ) continue;
            printf(
                '<option value="%s"%s>%s</option>',
                esc_attr( $qid ),
                selected( $qid, $selected_quiz, false ),
                esc_html( $test->get_name() )
            );
        }
        echo '</select> <noscript><button type="submit" class="button">نمایش</button></noscript>';
        echo '</td></tr></table>';
        echo '</form>';

        if ( ! $selected_quiz ) {
            echo '</div>';
            return;
        }

        // ── مراجعینی که این آزمون را ≥۲ بار کامل کرده‌اند (بر اساس شماره موبایل) ──
        $clients = $wpdb->get_results( $wpdb->prepare(
            "SELECT i.client_phone, i.client_name, COUNT(*) AS cnt
             FROM {$wpdb->prefix}psy_attempts a
             JOIN {$wpdb->prefix}psy_invitations i ON i.id = a.invitation_id
             WHERE a.quiz_id = %s AND i.client_phone <> ''
             GROUP BY i.client_phone
             HAVING cnt >= 2
             ORDER BY MAX(a.completed_at) DESC
             LIMIT 200",
            $selected_quiz
        ) );

        $test_name = ( \PsychAssessments\Core\Registry::get( $selected_quiz ) )->get_name();

        if ( empty( $clients ) ) {
            echo '<div class="notice notice-info"><p>هنوز هیچ مراجعی «' . esc_html( $test_name ) . '» را ۲ بار یا بیشتر کامل نکرده است.</p></div></div>';
            return;
        }

        echo '<h2 class="psy-section-title">مراجعینی با چند نوبت — ' . esc_html( $test_name ) . '</h2>';
        echo '<table class="wp-list-table widefat fixed striped psy-table-max-900"><thead><tr>';
        echo '<th>نام</th><th class="psy-col-160">موبایل</th><th class="psy-col-80">تعداد نوبت</th><th class="psy-col-160">نوبت اول</th><th class="psy-col-160">نوبت دوم</th><th class="psy-col-160">عملیات</th>';
        echo '</tr></thead><tbody>';

        foreach ( $clients as $c ) {
            $attempts = $wpdb->get_results( $wpdb->prepare(
                "SELECT a.id, a.completed_at
                 FROM {$wpdb->prefix}psy_attempts a
                 JOIN {$wpdb->prefix}psy_invitations i ON i.id = a.invitation_id
                 WHERE a.quiz_id = %s AND i.client_phone = %s
                 ORDER BY a.completed_at ASC",
                $selected_quiz, $c->client_phone
            ) );
            if ( count( $attempts ) < 2 ) continue;

            $row_id = 'psy-prog-' . md5( $c->client_phone . $selected_quiz );
            echo '<tr>';
            echo '<td><strong>' . esc_html( $c->client_name ) . '</strong></td>';
            echo '<td>' . esc_html( $c->client_phone ) . '</td>';
            echo '<td class="psy-text-soft">' . (int) $c->cnt . '</td>';

            foreach ( [ 'a1', 'a2' ] as $slot ) {
                echo '<td><select id="' . esc_attr( $row_id . '-' . $slot ) . '" class="psy-min-w-160">';
                foreach ( $attempts as $a ) {
                    // پیش‌فرض: اولین select روی قدیمی‌ترین نوبت، دومی روی جدیدترین
                    $default_selected = ( $slot === 'a1' )
                        ? ( $a->id === $attempts[0]->id )
                        : ( $a->id === end( $attempts )->id );
                    printf(
                        '<option value="%d"%s>%s [#%d]</option>',
                        (int) $a->id,
                        selected( $default_selected, true, false ),
                        esc_html( pa_to_jalali( $a->completed_at, 'Y/m/d' ) ),
                        (int) $a->id
                    );
                }
                echo '</select></td>';
            }

            $compare_base = add_query_arg( [
                'page'   => 'psy-quiz-progress',
                'action' => 'compare',
            ], admin_url( 'admin.php' ) );

            echo '<td><button type="button" class="button button-primary button-small psy-progress-compare-btn" data-row="' . esc_attr( $row_id ) . '" data-base="' . esc_url( $compare_base ) . '">🔍 مقایسه</button></td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
        echo '</div>';

        // اسکریپت کوچک: ساخت لینک مقایسه از دو select انتخاب‌شده
        ?>
        <script>
        (function () {
            document.querySelectorAll('.psy-progress-compare-btn').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    var row  = btn.getAttribute('data-row');
                    var base = btn.getAttribute('data-base');
                    var a1   = document.getElementById(row + '-a1').value;
                    var a2   = document.getElementById(row + '-a2').value;
                    if (!a1 || !a2) return;
                    if (a1 === a2) {
                        alert('دو نوبت باید متفاوت باشند.');
                        return;
                    }
                    window.location.href = base + '&attempt1=' + a1 + '&attempt2=' + a2;
                });
            });
        })();
        </script>
        <?php
    }

    /**
     * دیف عمومی و بازگشتی روی result_data دو نوبت — بدون نیاز به دانستن ساختار
     * دقیق هر آزمون. برای هر کلید:
     *   - مقدار عددی (int/float) → یک ردیف با قبل/بعد/اختلاف
     *   - آرایه‌ی انجمنی از مقادیر عددی (مثل sub_scores) → هر زیرکلید یک ردیف
     *     (با برچسب از get_schema_labels() اگر موجود بود)
     *   - رشته‌ای که بین دو نوبت فرق کرده (مثل style/dominant/top_story) →
     *     یک ردیف «تغییر وضعیت»
     *   - هر چیز دیگر (آرایه‌ی تودرتوی غیرعددی مثل clinical_flags) → نادیده
     *     گرفته می‌شود؛ چون معنایش مختص هر آزمون است و در گزارش‌های تکی هرکدام
     *     همچنان قابل مشاهده است.
     */
    private static function diff_result_data( array $d1, array $d2, array $labels ): array {
        $numeric_rows = [];
        $text_rows    = [];

        $keys = array_unique( array_merge( array_keys( $d1 ), array_keys( $d2 ) ) );

        foreach ( $keys as $key ) {
            $v1 = $d1[ $key ] ?? null;
            $v2 = $d2[ $key ] ?? null;

            if ( is_numeric( $v1 ) || is_numeric( $v2 ) ) {
                $n1 = is_numeric( $v1 ) ? (float) $v1 : null;
                $n2 = is_numeric( $v2 ) ? (float) $v2 : null;
                $numeric_rows[] = [
                    'label' => $labels[ $key ] ?? $key,
                    'v1'    => $n1,
                    'v2'    => $n2,
                    'delta' => ( $n1 !== null && $n2 !== null ) ? round( $n2 - $n1, 2 ) : null,
                ];
                continue;
            }

            if ( is_array( $v1 ) || is_array( $v2 ) ) {
                $a1 = is_array( $v1 ) ? $v1 : [];
                $a2 = is_array( $v2 ) ? $v2 : [];
                $is_numeric_map = true;
                foreach ( array_merge( $a1, $a2 ) as $vv ) {
                    if ( ! is_numeric( $vv ) ) { $is_numeric_map = false; break; }
                }
                if ( ! $is_numeric_map ) {
                    continue; // آرایه‌ی تودرتوی غیرعددی (مثل clinical_flags) — نادیده
                }
                $sub_keys = array_unique( array_merge( array_keys( $a1 ), array_keys( $a2 ) ) );
                foreach ( $sub_keys as $sk ) {
                    $n1 = isset( $a1[ $sk ] ) ? (float) $a1[ $sk ] : null;
                    $n2 = isset( $a2[ $sk ] ) ? (float) $a2[ $sk ] : null;
                    $numeric_rows[] = [
                        'label' => $labels[ $sk ] ?? $sk,
                        'v1'    => $n1,
                        'v2'    => $n2,
                        'delta' => ( $n1 !== null && $n2 !== null ) ? round( $n2 - $n1, 2 ) : null,
                    ];
                }
                continue;
            }

            // رشته/بولین/غیره
            if ( $v1 !== $v2 && ( $v1 !== null || $v2 !== null ) ) {
                $text_rows[] = [
                    'label' => $labels[ $key ] ?? $key,
                    'v1'    => $v1 !== null ? (string) $v1 : '—',
                    'v2'    => $v2 !== null ? (string) $v2 : '—',
                    'changed' => true,
                ];
            }
        }

        return [ 'numeric' => $numeric_rows, 'text' => $text_rows ];
    }

    private static function render_compare( int $id1, int $id2 ): void {
        global $wpdb;

        $back_url = admin_url( 'admin.php?page=psy-quiz-progress' );

        $fetch = function ( int $id ) use ( $wpdb ) {
            return $wpdb->get_row( $wpdb->prepare(
                "SELECT a.id, a.quiz_id, a.answers, a.result_data, a.completed_at,
                        i.client_name, i.client_phone
                 FROM {$wpdb->prefix}psy_attempts a
                 JOIN {$wpdb->prefix}psy_invitations i ON i.id = a.invitation_id
                 WHERE a.id = %d", $id
            ) );
        };

        $att1 = $fetch( $id1 );
        $att2 = $fetch( $id2 );

        echo '<div class="wrap psy-admin" dir="rtl">';
        echo '<h1>📈 مقایسه‌ی نوبت‌ها</h1>';
        echo '<a href="' . esc_url( $back_url ) . '" class="button psy-mb-20">← بازگشت</a>';
        echo '<hr class="wp-header-end">';

        if ( ! $att1 || ! $att2 ) {
            echo '<div class="psy-notice-plain is-error">یک یا هر دو نوبت یافت نشد.</div></div>';
            return;
        }
        if ( $att1->quiz_id !== $att2->quiz_id ) {
            echo '<div class="psy-notice-plain is-error">دو نوبت باید مربوط به یک آزمون یکسان باشند.</div></div>';
            return;
        }
        $test = \PsychAssessments\Core\Registry::get( $att1->quiz_id );
        if ( ! $test || $test->get_category() !== 'relationship' ) {
            echo '<div class="psy-notice-plain is-error">این ابزار فقط برای آزمون‌های دسته «زوجین و روابط» است.</div></div>';
            return;
        }

        // ترتیب زمانی: قدیمی‌تر = نوبت اول، جدیدتر = نوبت دوم (صرف‌نظر از این‌که
        // کاربر کدام را کجای فرم انتخاب کرده بود)
        if ( strtotime( $att1->completed_at ) > strtotime( $att2->completed_at ) ) {
            [ $att1, $att2 ] = [ $att2, $att1 ];
        }

        // BUGFIX: همان باگ رمزگشایی‌فراموش‌شده — هم result_data و هم answers
        // وقتی Pas_Core_Crypto فعال باشد رمزنگاری‌شده ذخیره می‌شوند.
        $d1 = pa_decrypt_json( $att1->result_data );
        $d2 = pa_decrypt_json( $att2->result_data );
        $ans1 = pa_decrypt_json( $att1->answers );
        $ans2 = pa_decrypt_json( $att2->answers );

        $labels    = $test->get_schema_labels();
        $questions = $test->get_questions();
        $scale     = $test->get_scale();
        $scale_labels = $scale['labels'] ?? [];

        $date1 = pa_to_jalali( $att1->completed_at, 'Y/m/d ساعت H:i' );
        $date2 = pa_to_jalali( $att2->completed_at, 'Y/m/d ساعت H:i' );
        $days_between = round( ( strtotime( $att2->completed_at ) - strtotime( $att1->completed_at ) ) / 86400 );

        $diff = self::diff_result_data( $d1, $d2, $labels );

        // ── کارت‌های معرفی ──────────────────────────────────────────
        echo '<div class="psy-couple-cards">';
        echo '<div class="psy-couple-person-card psy-person-1-bg">';
        echo '<div class="psy-couple-person-label">نوبت اول</div>';
        echo '<div class="psy-couple-person-name psy-person-1-text">' . esc_html( $att1->client_name ) . '</div>';
        echo '<div class="psy-couple-person-meta">📅 ' . esc_html( $date1 ) . '</div>';
        echo '</div>';
        echo '<div class="psy-couple-person-card psy-person-2-bg">';
        echo '<div class="psy-couple-person-label">نوبت دوم</div>';
        echo '<div class="psy-couple-person-name psy-person-2-text">' . esc_html( $att2->client_name ) . '</div>';
        echo '<div class="psy-couple-person-meta">📅 ' . esc_html( $date2 ) . '</div>';
        echo '</div>';
        echo '</div>';
        echo '<p class="psy-text-soft">آزمون: <strong>' . esc_html( $test->get_name() ) . '</strong> — فاصله‌ی دو نوبت: <strong>' . esc_html( $days_between ) . ' روز</strong></p>';

        // ── جدول خلاصه‌ی نمره‌ها ────────────────────────────────────
        echo '<div class="psy-panel psy-panel-clip">';
        echo '<h3 class="psy-panel-title-bordered">📊 خلاصه‌ی نمره‌ها</h3>';
        if ( empty( $diff['numeric'] ) ) {
            echo '<p class="psy-notice-plain">این آزمون نمره‌ی عددی قابل مقایسه‌ای در خروجی خودش ندارد.</p>';
        } else {
            echo '<table class="psy-compare-table"><thead><tr>';
            echo '<th class="psy-col-right">مقیاس</th><th class="psy-col-center psy-col-120 psy-person-1-text">نوبت اول</th><th class="psy-col-center psy-col-120 psy-person-2-text">نوبت دوم</th><th class="psy-col-center psy-col-120">تغییر</th>';
            echo '</tr></thead><tbody>';
            foreach ( $diff['numeric'] as $row ) {
                $delta = $row['delta'];
                if ( $delta === null ) {
                    $delta_html = '<span class="psy-text-soft">—</span>';
                } else {
                    $arrow = $delta > 0 ? '▲' : ( $delta < 0 ? '▼' : '＝' );
                    $mag   = abs( $delta );
                    $cls   = $mag == 0 ? 'psy-text-soft' : ( $mag < ( 0.15 * max( abs( $row['v1'] ?? 1 ), 1 ) ) ? 'psy-diff-good' : 'psy-diff-mid' );
                    $delta_html = '<span class="' . $cls . '">' . $arrow . ' ' . esc_html( number_format( $mag, 2 ) ) . '</span>';
                }
                echo '<tr>';
                echo '<td><strong>' . esc_html( $row['label'] ) . '</strong></td>';
                echo '<td class="psy-col-center psy-cell-bold psy-person-1-text">' . ( $row['v1'] !== null ? esc_html( number_format( $row['v1'], 2 ) ) : '—' ) . '</td>';
                echo '<td class="psy-col-center psy-cell-bold psy-person-2-text">' . ( $row['v2'] !== null ? esc_html( number_format( $row['v2'], 2 ) ) : '—' ) . '</td>';
                echo '<td class="psy-col-center psy-cell-bold">' . $delta_html . '</td>';
                echo '</tr>';
            }
            echo '</tbody></table>';
        }
        if ( ! empty( $diff['text'] ) ) {
            echo '<div class="psy-mt-16">';
            foreach ( $diff['text'] as $row ) {
                echo '<p>🔀 <strong>' . esc_html( $row['label'] ) . ':</strong> ' .
                    '<span class="psy-person-1-text">' . esc_html( $row['v1'] ) . '</span> ← ' .
                    '<span class="psy-person-2-text">' . esc_html( $row['v2'] ) . '</span></p>';
            }
            echo '</div>';
        }
        echo '</div>';

        // ── جزئیات: پاسخ هر سوال در دو نوبت (فقط سوالاتی که فرق کرده‌اند باز پیش‌فرض) ──
        $question_rows = [];
        $changed_count  = 0;
        foreach ( $questions as $qid => $qtext ) {
            $r1 = $ans1[ $qid ] ?? null;
            $r2 = $ans2[ $qid ] ?? null;
            $changed = ( (string) $r1 !== (string) $r2 );
            if ( $changed ) $changed_count++;
            $question_rows[] = [
                'qid' => $qid, 'text' => $qtext,
                'l1'  => $r1 !== null ? ( $scale_labels[ $r1 ] ?? $r1 ) : '—',
                'l2'  => $r2 !== null ? ( $scale_labels[ $r2 ] ?? $r2 ) : '—',
                'changed' => $changed,
            ];
        }

        echo '<details class="psy-panel psy-panel-pad"><summary style="cursor:pointer;font-weight:700;font-size:14px;">';
        echo '📋 جزئیات پاسخ به سوالات (' . (int) $changed_count . ' از ' . count( $question_rows ) . ' سوال تغییر کرده)';
        echo '</summary>';
        echo '<table class="psy-compare-table psy-mt-16"><thead><tr>';
        echo '<th class="psy-col-right">سوال</th><th class="psy-col-center psy-col-160 psy-person-1-text">نوبت اول</th><th class="psy-col-center psy-col-160 psy-person-2-text">نوبت دوم</th>';
        echo '</tr></thead><tbody>';
        foreach ( $question_rows as $row ) {
            $row_style = $row['changed'] ? ' style="background:var(--psy-amber-bg);"' : '';
            echo '<tr' . $row_style . '>';
            echo '<td>' . esc_html( $row['qid'] . '. ' . $row['text'] ) . '</td>';
            echo '<td class="psy-col-center">' . esc_html( $row['l1'] ) . '</td>';
            echo '<td class="psy-col-center">' . esc_html( $row['l2'] ) . '</td>';
            echo '</tr>';
        }
        echo '</tbody></table></details>';

        echo '</div>';
    }
}
