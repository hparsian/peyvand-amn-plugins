<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Psy_Page_Offline — آزمون آفلاین + ورود دستی پاسخ‌ها
 */
class Psy_Page_Offline {

    public static function render_offline(): void {
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'دسترسی غیرمجاز.', 403 );
        $tests = \PsychAssessments\Core\Registry::by_category();
        ?>
        <div class="wrap psy-admin" dir="rtl">
            <h1>📥 آزمون آفلاین</h1>
            <p class="psy-lead">
                فایل HTML آزمون را برای مراجع بفرستید. مراجع بدون اینترنت پاسخ می‌دهد
                و پاسخ‌ها را از طریق ایمیل برای شما می‌فرستد.
            </p>

            <div class="psy-form-card">
                <h2 class="psy-card-title">⬇️ دانلود فایل آزمون آفلاین</h2>
                <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                    <?php wp_nonce_field('psy_offline_export'); ?>
                    <input type="hidden" name="action" value="psy_offline_export">
                    <table class="form-table">
                        <tr>
                            <th><label for="off_client_name">نام مراجع <span class="psy-required">*</span></label></th>
                            <td><input type="text" name="client_name" id="off_client_name" class="regular-text" required placeholder="مثال: علی احمدی" autofocus></td>
                        </tr>
                        <tr>
                            <th><label for="off_quiz_id">آزمون <span class="psy-required">*</span></label></th>
                            <td>
                                <select name="quiz_id" id="off_quiz_id" required class="psy-min-w-320">
                                    <option value="">— انتخاب آزمون —</option>
                                    <?php foreach ( $tests as $cat => $cat_tests ):
                                        $cat_label = \PsychAssessments\Core\Registry::category_label($cat);
                                    ?>
                                    <optgroup label="<?php echo esc_attr($cat_label); ?>">
                                        <?php foreach ( $cat_tests as $t ): ?>
                                        <option value="<?php echo esc_attr($t->get_id()); ?>">
                                            <?php echo esc_html($t->get_name()); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </optgroup>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                    </table>
                    <?php submit_button( '⬇️ دانلود فایل HTML آزمون', 'primary', 'submit', true ); ?>
                </form>
            </div>

            <div class="psy-form-card psy-mt-24">
                <h2 class="psy-card-title">📋 راهنمای استفاده</h2>
                <ol class="psy-list-loose">
                    <li>نام مراجع و آزمون مورد نظر را انتخاب کنید و فایل HTML را دانلود کنید.</li>
                    <li>فایل را از طریق واتساپ یا ایمیل برای مراجع بفرستید.</li>
                    <li>مراجع فایل را باز کرده و بدون نیاز به اینترنت پاسخ می‌دهد.</li>
                    <li>در پایان آزمون، مراجع دکمه «ارسال» را می‌زند — برنامه ایمیل باز می‌شود و پاسخ‌ها به <strong><?php echo esc_html( Psy_Quiz_Offline_Export::therapist_email() ); ?></strong> ارسال می‌شود.</li>
                    <li>شما ایمیل را دریافت می‌کنید و پاسخ‌ها را از صفحه «ورود دستی» در پنل ادمین ثبت می‌کنید.</li>
                </ol>
            </div>
        </div>
        <?php
    }

    // ── صفحه ورود دستی پاسخ‌های آفلاین ────────────────────────────────────
    public static function render_manual(): void {
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'دسترسی غیرمجاز.', 403 );

        $tests   = \PsychAssessments\Core\Registry::all();
        $psy_key = sanitize_text_field( wp_unslash( $_GET['psy_key'] ?? '' ) );
        $cached  = $psy_key ? get_transient( $psy_key ) : null;

        // ── اگر داده parse‌شده داریم، پردازش کن ─────────────────────────
        $process_result = null;
        $parsed         = null;
        $raw            = '';

        if ( $cached ) {
            $parsed = $cached['parsed'];
            $raw    = $cached['raw'];

            // اگر parse موفق بود، پردازش کن
            if ( empty($parsed['errors']) && ! empty($parsed['quiz_id']) && ! empty($parsed['answers']) ) {
                $process_result = Psy_Quiz_Manual_Entry::process(
                    $parsed['quiz_id'],
                    $parsed['client_name'],
                    $parsed['answers']
                );
                // #M1 fix: transient بلافاصله پس از process() حذف می‌شود — چه موفق چه ناموفق
                // قبلاً فقط در شاخه success حذف می‌شد → هر reload یک invitation یتیم جدید می‌ساخت
                delete_transient( $psy_key );
            }
        }
        ?>
        <div class="wrap psy-admin" dir="rtl">
            <h1>📋 ورود دستی پاسخ‌های آفلاین</h1>

            <?php if ( $process_result && isset($process_result['success']) ): ?>
            <!-- ── نتیجه موفق ── -->
            <div class="notice notice-success"><p>✅ پاسخ‌ها با موفقیت ثبت و تحلیل شدند.</p></div>
            <div class="psy-report-wrap">
                <p>
                    <a href="<?php echo admin_url('admin.php?page=psy-quiz-manual'); ?>" class="button">← ورود پاسخ جدید</a>
                    <a href="<?php echo esc_url( admin_url('admin.php?page=psy-quiz-reports&attempt=' . (int)$process_result['attempt_id']) ); ?>" class="button button-primary">📊 مشاهده گزارش کامل</a>
                    <button class="button" onclick="window.print()">🖨️ چاپ</button>
                </p>
                <h2 class="psy-card-title-dark">
                    <?php echo esc_html( $process_result['client_name'] ); ?> —
                    <?php echo esc_html( $process_result['test']->get_name() ); ?>
                </h2>
                <p class="psy-lead">ورود آفلاین — <?php echo date_i18n('Y/m/d'); ?></p>
                <div class="psy-report-body">
                    <?php
                    $mock_attempt = (object)[
                        'client_name'  => $process_result['client_name'],
                        'patient_phone'=> '',
                        'completed_at' => current_time('mysql'),
                        'id'           => $process_result['attempt_id'],
                        'quiz_id'      => $parsed['quiz_id'],
                    ];
                    echo $process_result['test']->render_report( $process_result['result_data'], $mock_attempt );
                    ?>
                </div>
            </div>
            <?php elseif ( $process_result && isset($process_result['error']) ): ?>
            <!-- ── خطای پردازش ── -->
            <div class="notice notice-error"><p>❌ <?php echo esc_html($process_result['error']); ?></p></div>
            <?php endif; ?>

            <?php if ( $parsed && ! empty($parsed['errors']) ): ?>
            <!-- ── خطاهای parse ── -->
            <div class="notice notice-error">
                <p><strong>مشکل در خواندن متن:</strong></p>
                <ul class="psy-list-disc">
                <?php foreach ( $parsed['errors'] as $e ): ?>
                    <li><?php echo esc_html($e); ?></li>
                <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>

            <?php if ( ! $process_result || isset($process_result['error']) ): ?>
            <!-- ── فرم ورود ── -->
            <div class="psy-form-card">
                <h2 class="psy-card-title">📩 متن ایمیل مراجع را اینجا Paste کنید</h2>
                <p class="psy-lead psy-text-xs">
                    متن کامل ایمیلی که مراجع فرستاده را کپی کرده و در کادر زیر paste کنید.
                </p>
                <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                    <?php wp_nonce_field('psy_manual_entry'); ?>
                    <input type="hidden" name="action" value="psy_manual_entry">

                    <textarea
                        name="psy_raw_answers"
                        id="psy_raw_answers"
                        rows="18"
                        class="psy-textarea-mono"
                        placeholder="مثال:
آزمون آفلاین — پاسخ‌های مراجع
مراجع: علی احمدی
آزمون: افسردگی بک (bdi)
━━━━━━━━━━━━━━━━━━━━━━━━
سوال 1: 2 — زیاد
سوال 2: 0 — اصلاً
سوال 3: 1 — کمی
..."
                    ><?php echo esc_textarea( $raw ); ?></textarea>

                    <div class="psy-field-row">
                        <div>
                            <label class="psy-field-label-sm">آزمون <span class="psy-text-amber psy-text-bold">*</span> (اگر در متن نبود اینجا انتخاب کنید):</label>
                            <select name="quiz_id_override" class="psy-min-w-260">
                                <option value="">— خودکار از متن —</option>
                                <?php foreach ( $tests as $t ): ?>
                                <option value="<?php echo esc_attr($t->get_id()); ?>">
                                    <?php echo esc_html($t->get_name()); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="psy-field-label-sm">نام مراجع (اگر در متن نبود اینجا بنویسید):</label>
                            <input type="text" name="client_name_override" class="regular-text" placeholder="از متن خوانده می‌شود" aria-label="از متن خوانده می‌شود">
                        </div>
                    </div>

                    <p class="psy-mt-20">
                        <?php submit_button( '🔍 تحلیل و ثبت پاسخ‌ها', 'primary large', 'submit', false ); ?>
                    </p>
                </form>
            </div>

            <div class="psy-form-card psy-card-warn">
                <h3>💡 نکته</h3>
                <p>
                    افزونه خط «آزمون: ... (quiz_id)» را برای تشخیص آزمون می‌خواند.<br>
                    اگر متن ایمیل این خط را ندارد، از منوی «تصحیح آزمون» بالا آزمون صحیح را انتخاب کنید.
                </p>
            </div>
            <?php endif; ?>

        </div>
        <?php
    }
}
