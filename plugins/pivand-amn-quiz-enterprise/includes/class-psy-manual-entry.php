<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Psy_Quiz_Manual_Entry
 * ─────────────────────────────────────────────────────────────
 * ورود دستی پاسخ‌های آفلاین:
 *   ۱. متن ایمیل مراجع را paste می‌کنی
 *   ۲. افزونه quiz_id، نام مراجع و پاسخ‌ها را parse می‌کند
 *   ۳. تحلیل انجام می‌شود و گزارش نمایش داده می‌شود
 *   ۴. نتیجه در دیتابیس ثبت می‌شود
 */
class Psy_Quiz_Manual_Entry {

    /**
     * Parse متن ایمیل آفلاین
     * فرمت انتظاری (فایل‌های جدید):
     *   شناسه آزمون: bdi
     *   مراجع: علی احمدی
     *   سوال 1: 2 — زیاد
     *   سوال 2: 0 — اصلاً
     *
     * برای سازگاری با فایل‌هایی که قبل از این تغییر دانلود شده‌اند، فرمت
     * قدیمی («آزمون: افسردگی بک (bdi)» — نام خوانا + شناسه داخل پرانتز) هم
     * هنوز پشتیبانی می‌شود؛ ولی فایل‌های تازه‌دانلودشده دیگر اصلاً نام آزمون
     * را در خود ندارند، فقط همان شناسه (quiz_id) را.
     */
    public static function parse( string $raw ): array {
        $result = [
            'quiz_id'     => '',
            'client_name' => '',
            'answers'     => [],
            'errors'      => [],
        ];

        // #M3 fix: نرمال‌سازی اعداد فارسی و عربی — \d در PHP/PCRE فقط ASCII می‌خواند
        $raw = str_replace(
            ['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹','٠','١','٢','٣','٤','٥','٦','٧','٨','٩'],
            ['0','1','2','3','4','5','6','7','8','9','0','1','2','3','4','5','6','7','8','9'],
            $raw
        );

        // نرمال‌سازی خط‌ها
        $lines = preg_split( '/\r\n|\r|\n/', $raw );

        foreach ( $lines as $line ) {
            $line = trim( $line );
            if ( empty($line) ) continue;

            // خط شناسه‌ی آزمون — فرمت جدید: "شناسه آزمون: bdi" (بدون نام
            // خوانا، فقط پارامتر quiz_id). این خط را قبل از فرمت قدیمی چک
            // می‌کنیم؛ چون هیچ‌کدام از دو الگو زیرمجموعه‌ی هم نیستند
            // (یکی پرانتز دارد یکی ندارد)، تداخلی پیش نمی‌آید.
            if ( preg_match( '/شناسه\s*آزمون\s*:\s*([a-zA-Z0-9_\-]+)/u', $line, $m ) ) {
                $result['quiz_id'] = strtolower( trim( $m[1] ) );
                continue;
            }

            // خط آزمون — فرمت قدیمی (فایل‌های دانلودشده قبل از این تغییر):
            // "آزمون: افسردگی بک (bdi)"  یا  "آزمون: افسردگی بک (BDI-II)"
            if ( preg_match( '/آزمون\s*:\s*.+\(([a-zA-Z0-9_\-]+)\)/u', $line, $m ) ) {
                $result['quiz_id'] = strtolower( trim( $m[1] ) );
                continue;
            }

            // خط مراجع: "مراجع: علی احمدی"
            if ( preg_match( '/مراجع\s*:\s*(.+)/u', $line, $m ) ) {
                $result['client_name'] = trim( $m[1] );
                continue;
            }

            // خط پاسخ: "سوال 5: 2 — زیاد"  یا  "سوال 5: 2"
            if ( preg_match( '/سوال\s+(\d+)\s*:\s*(\d+)/u', $line, $m ) ) {
                $result['answers'][ intval($m[1]) ] = intval($m[2]);
                continue;
            }
        }

        // بررسی‌های اولیه
        if ( empty($result['quiz_id']) ) {
            $result['errors'][] = 'شناسه آزمون پیدا نشد. مطمئن شوید خط «شناسه آزمون: quiz_id» (یا در فایل‌های قدیمی‌تر «آزمون: ... (quiz_id)») در متن وجود دارد.';
        }
        if ( empty($result['answers']) ) {
            $result['errors'][] = 'هیچ پاسخی پیدا نشد. مطمئن شوید خطوط «سوال X: Y» در متن وجود دارند.';
        }

        return $result;
    }

    /**
     * پردازش و ثبت پاسخ‌های parse‌شده
     * یه invitation موقت می‌سازد (بدون توکن)، پاسخ‌ها را ثبت و تحلیل می‌کند
     */
    public static function process( string $quiz_id, string $client_name, array $answers ): array {
        global $wpdb;

        $test = \PsychAssessments\Core\Registry::get( $quiz_id );
        if ( ! $test ) {
            return [ 'error' => 'آزمون «' . $quiz_id . '» در سیستم یافت نشد.' ];
        }

        // اعتبارسنجی پاسخ‌ها — بررسی همه سوالات
        $expected_ids = array_keys( $test->get_questions() );
        $received_ids = array_keys( $answers );
        sort($expected_ids); sort($received_ids);

        $missing = array_diff( $expected_ids, $received_ids );
        if ( ! empty($missing) ) {
            return [
                'error' => 'پاسخ ' . count($missing) . ' سوال موجود نیست: ' .
                           implode('، ', array_slice($missing, 0, 10)) .
                           ( count($missing) > 10 ? ' و ...' : '' )
            ];
        }
        $extra = array_diff( $received_ids, $expected_ids );
        if ( ! empty($extra) ) {
            return [
                'error' => count($extra) . ' شناسه سوال اضافی در متن وجود دارد: ' .
                           implode('، ', array_slice($extra, 0, 10)) .
                           ( count($extra) > 10 ? ' و ...' : '' )
            ];
        }

        // clamp مقادیر در بازه مجاز
        $global_scale = $test->get_scale();
        $q_scales     = method_exists( $test, 'get_question_scales' ) ? $test->get_question_scales() : [];
        $clean        = [];
        foreach ( $answers as $qid => $val ) {
            $qs         = $q_scales[$qid] ?? $global_scale;
            $clean[$qid] = max( intval($qs['min'] ?? 0), min( intval($qs['max'] ?? PHP_INT_MAX), intval($val) ) );
        }

        // محاسبه نتایج
        try {
            $result_data = $test->calculate( $clean );
        } catch ( \Throwable $e ) {
            return [ 'error' => 'خطا در محاسبه: ' . $e->getMessage() ];
        }

        // ساخت invitation موقت با token آفلاین — پیشوند PSY_OFFLINE_TOKEN_PREFIX برای تمایز از توکن‌های عادی
        $inv_inserted = $wpdb->insert( $wpdb->prefix . 'psy_invitations', [
            'quiz_id'      => sanitize_key( $quiz_id ),
            'client_name'  => sanitize_text_field( $client_name ?: 'نامشخص' ),
            'client_phone' => '',
            'access_token' => 'offline_' . bin2hex( random_bytes( 16 ) ),  // 32 کاراکتر hex — collision-safe
            'status'       => 'completed',
            'created_at'   => current_time( 'mysql' ), // هم‌پایه با pa_to_jalali؛ به DEFAULT دیتابیس تکیه نمی‌کنیم
            'expires_at'   => null,
        ] );
        $inv_id = $wpdb->insert_id;

        if ( $inv_inserted === false || ! $inv_id ) {
            psy_log( 'error', 'manual_entry', 'invitation insert failed', [ 'db_error' => $wpdb->last_error ] );
            return [ 'error' => 'خطا در ذخیره‌سازی. لطفاً دوباره تلاش کنید.' ];
        }

        // ثبت attempt
        // همان رمزنگاری‌ای که در مسیر آزمون آنلاین (class-psy-rest-handler)
        // اعمال می‌شود، اینجا هم برای ثبت دستی توسط درمانگر اعمال می‌شود —
        // تا هر دو مسیر ورودی، سطح حفاظت یکسانی داشته باشند.
        $answers_json = wp_json_encode( $clean );
        $result_json  = wp_json_encode( $result_data );
        if ( class_exists( 'Pas_Core_Crypto' ) && Pas_Core_Crypto::is_configured() ) {
            $answers_json = Pas_Core_Crypto::wrap_encrypted( $answers_json );
            $result_json  = Pas_Core_Crypto::wrap_encrypted( $result_json );
        }

        $att_inserted = $wpdb->insert( $wpdb->prefix . 'psy_attempts', [
            'quiz_id'       => sanitize_key( $quiz_id ),
            'invitation_id' => $inv_id,
            'answers'       => $answers_json,
            'result_data'   => $result_json,
            'completed_at'  => current_time( 'mysql' ),
        ] );
        $attempt_id = $wpdb->insert_id;

        if ( $att_inserted === false || ! $attempt_id ) {
            psy_log( 'error', 'manual_entry', 'attempt insert failed', [ 'db_error' => $wpdb->last_error ] );
            // rollback invitation
            $wpdb->delete( $wpdb->prefix . 'psy_invitations', [ 'id' => $inv_id ], [ '%d' ] );
            return [ 'error' => 'خطا در ذخیره‌سازی. لطفاً دوباره تلاش کنید.' ];
        }

        // BUG-06: آپدیت attempt_id در invitation تا دکمه «گزارش» در dashboard نمایش داده شود
        $wpdb->update(
            $wpdb->prefix . 'psy_invitations',
            [ 'attempt_id' => $attempt_id ],
            [ 'id'         => $inv_id ],
            [ '%d' ],
            [ '%d' ]
        );

        // فاز ۳: رویداد تکمیل آزمون برای ثبت دستی هم منتشر می‌شود — این ورودی
        // هم یک «آزمون تکمیل‌شده» است و نباید از دید پرونده/داشبورد بیفتد.
        // client_id خالی است چون ثبت دستی شمارهٔ مراجع ندارد (offline).
        if ( class_exists( 'Pas_Core_Events' ) ) {
            Pas_Core_Events::dispatch(
                'assessment.completed',
                [
                    'quiz_id'       => (string) sanitize_key( $quiz_id ),
                    'attempt_id'    => (int) $attempt_id,
                    'invitation_id' => (int) $inv_id,
                    'entry_mode'    => 'manual',
                ],
                [
                    'source'          => 'quiz-enterprise',
                    'entity_type'     => 'assessment',
                    'entity_id'       => (int) $attempt_id,
                    'client_id'       => '',
                    'idempotency_key' => 'assessment.completed:' . (int) $attempt_id,
                ]
            );
        }

        return [
            'success'     => true,
            'attempt_id'  => $attempt_id,
            'result_data' => $result_data,
            'test'        => $test,
            'client_name' => $client_name ?: 'نامشخص',
        ];
    }

    /**
     * ثبت hooks ادمین
     */
    public static function register_hooks(): void {
        add_action( 'admin_post_psy_manual_entry', [ __CLASS__, 'handle_post' ] );
    }

    public static function handle_post(): void {
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'دسترسی غیرمجاز.', 403 );
        check_admin_referer( 'psy_manual_entry' );

        $raw = wp_unslash( $_POST['psy_raw_answers'] ?? '' );
        $parsed = self::parse( $raw );

        // override دستی quiz_id و client_name — قبل از بررسی errors اعمال می‌شود
        // تا کاربر بتواند بدون خط آزمون در متن، آزمون را دستی انتخاب کند
        if ( ! empty( $_POST['quiz_id_override'] ) ) {
            $parsed['quiz_id'] = sanitize_key( $_POST['quiz_id_override'] );
            // اگر quiz_id از override آمد، خطای مربوطه را پاک کن
            $parsed['errors'] = array_filter( $parsed['errors'], fn($e) => strpos($e, 'شناسه آزمون') === false );
            $parsed['errors'] = array_values( $parsed['errors'] );
        }
        if ( ! empty( $_POST['client_name_override'] ) ) {
            $parsed['client_name'] = sanitize_text_field( wp_unslash( $_POST['client_name_override'] ) );
        }

        // ذخیره موقت و redirect به صفحه نتیجه
        $key  = 'psy_manual_' . wp_generate_password( 16, false );
        $data = [ 'parsed' => $parsed, 'raw' => $raw ];
        set_transient( $key, $data, 10 * MINUTE_IN_SECONDS );

        wp_safe_redirect( add_query_arg([
            'page'    => 'psy-quiz-manual',
            'psy_key' => $key,
        ], admin_url('admin.php') ) );
        exit;
    }
}
