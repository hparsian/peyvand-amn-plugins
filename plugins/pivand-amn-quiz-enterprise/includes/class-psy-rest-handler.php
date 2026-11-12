<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Psy_Quiz_Rest_Handler {

    /**
     * محدودیت نرخ درخواست برای سه endpoint کاملاً عمومی (submit/draft) که
     * permission_callback آن‌ها '__return_true' است. امنیت اصلی این
     * endpointها با توکن دعوت ۴۰ کاراکتری تصادفی تامین می‌شود (حدس زدنش
     * عملاً غیرممکن است)، پس این‌جا هدف جلوگیری از brute-force نیست، بلکه
     * جلوگیری از مصرف بی‌رویه‌ی منابع/دیتابیس با درخواست‌های انبوه است.
     * الگو دقیقاً از PWDF_Ajax::check_rate_limit (پلاگین piwand-daily-feed)
     * کپی شده تا رفتار یکسانی در کل اکوسیستم داشته باشیم.
     */
    private static function get_client_ip(): string {
        if ( class_exists( 'Pas_Core_Helpers' ) ) {
            return \Pas_Core_Helpers::get_client_ip();
        }
        return sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0' ) );
    }

    /**
     * true یعنی مجاز است، false یعنی از حد مجاز رد شده.
     */
    private static function check_rate_limit( string $action, int $max_per_minute ): bool {
        $ip    = self::get_client_ip();
        $key   = 'psy_rl_' . md5( $action . '_' . $ip );
        $count = (int) get_transient( $key );

        if ( $count >= $max_per_minute ) {
            return false;
        }

        set_transient( $key, $count + 1, 60 );
        return true;
    }

    private static function rate_limited_response(): WP_REST_Response {
        return new WP_REST_Response( [ 'error' => 'تعداد درخواست‌های شما بیش از حد مجاز است. کمی صبر کنید.' ], 429 );
    }

    public function register_routes(): void {
        // دریافت اطلاعات آزمون بر اساس توکن
        // این endpoint توسط فرانت‌اند استفاده نمی‌شود (سوالات server-side رندر می‌شوند).
        // دسترسی به ادمین‌های لاگین‌شده محدود شده تا سطح حمله کاهش یابد.
        register_rest_route( 'psy/v1', '/quiz', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [ $this, 'get_quiz_info' ],
            'permission_callback' => function() {
                return current_user_can( 'manage_options' );
            },
            'args'                => [
                'token' => [ 'required' => true, 'sanitize_callback' => 'sanitize_text_field' ],
            ],
        ] );

        // ثبت پاسخ‌های آزمون
        register_rest_route( 'psy/v1', '/submit', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [ $this, 'submit_answers' ],
            'permission_callback' => '__return_true',
        ] );

        // ذخیره موقت پاسخ‌های نیمه‌تمام (auto-save / Resume)
        register_rest_route( 'psy/v1', '/draft', [
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [ $this, 'save_draft' ],
                'permission_callback' => '__return_true',
            ],
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [ $this, 'get_draft' ],
                'permission_callback' => '__return_true',
                'args'                => [
                    'token' => [ 'required' => true, 'sanitize_callback' => 'sanitize_text_field' ],
                ],
            ],
        ] );
        register_rest_route( 'psy/v1', '/report/(?P<id>\d+)', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [ $this, 'get_report' ],
            'permission_callback' => function( WP_REST_Request $req ) {
                // ادمین لاگین‌شده همیشه دسترسی دارد
                if ( current_user_can( 'manage_options' ) ) return true;
                // غیر ادمین‌ها باید token داشته باشند (صحت آن در callback بررسی می‌شود)
                return ! empty( $req->get_param('token') );
            },
            'args'                => [
                'id'    => [ 'required' => true, 'validate_callback' => fn($v) => is_numeric($v) ],
                'token' => [ 'required' => false, 'sanitize_callback' => 'sanitize_text_field' ],
            ],
        ] );
    }

    // ───────────────────────────────────────────────────────────────────────
    public function get_quiz_info( WP_REST_Request $req ): WP_REST_Response {
        $token = $req->get_param( 'token' );
        $inv   = Psy_Quiz_Invitation_Manager::validate( $token );

        if ( ! $inv ) {
            return new WP_REST_Response( [ 'error' => 'لینک نامعتبر یا قبلاً استفاده شده است.' ], 403 );
        }

        $test = \PsychAssessments\Core\Registry::get( $inv->quiz_id );
        if ( ! $test ) {
            return new WP_REST_Response( [ 'error' => 'آزمون یافت نشد.' ], 404 );
        }

        return new WP_REST_Response( [
            'quiz_id'     => $test->get_id(),
            'name'        => $test->get_name(),
            'description' => $test->get_description(),
            'scale'       => $test->get_scale(),
            'questions'   => $test->get_questions(),
            'client_name' => $inv->client_name,
        ], 200 );
    }

    // ───────────────────────────────────────────────────────────────────────
    public function submit_answers( WP_REST_Request $req ): WP_REST_Response {
        global $wpdb;

        if ( ! self::check_rate_limit( 'submit', 10 ) ) {
            return self::rate_limited_response();
        }

        $params  = $req->get_json_params();
        $token   = isset( $params['token'] )   ? sanitize_text_field( $params['token'] ) : '';
        $answers = isset( $params['answers'] )  ? (array) $params['answers']               : [];

        if ( empty( $token ) || empty( $answers ) ) {
            return new WP_REST_Response( [ 'error' => 'داده‌های ارسالی ناقص است.' ], 400 );
        }

        $inv  = Psy_Quiz_Invitation_Manager::validate( $token );
        if ( ! $inv ) {
            return new WP_REST_Response( [ 'error' => 'لینک نامعتبر یا قبلاً استفاده شده است.' ], 403 );
        }

        // رزرو atomic — جلوگیری از ثبت دوتایی در صورت درخواست همزمان
        if ( ! Psy_Quiz_Invitation_Manager::claim( $inv->id ) ) {
            return new WP_REST_Response( [ 'error' => 'لینک نامعتبر یا قبلاً استفاده شده است.' ], 403 );
        }

        $test = \PsychAssessments\Core\Registry::get( $inv->quiz_id );
        if ( ! $test ) {
            return new WP_REST_Response( [ 'error' => 'آزمون یافت نشد.' ], 404 );
        }

        // پاکسازی پاسخ‌ها — فقط qid های عددی مثبت را قبول می‌کنیم
        $clean_answers = [];
        foreach ( $answers as $qid => $val ) {
            $int_qid = intval($qid);
            if ( $int_qid > 0 ) {
                $clean_answers[ $int_qid ] = intval( $val );
            }
        }

        // بررسی اینکه دقیقاً همان شناسه سوالات آزمون پاسخ داده شده (نه فقط تعداد)
        // array_map('intval') تضمین می‌کند مقایسه int vs string باعث شکست نشود
        $expected_ids = array_map( 'intval', array_keys( $test->get_questions() ) );
        $received_ids = array_keys( $clean_answers );
        sort($expected_ids); sort($received_ids);
        if ( $expected_ids !== $received_ids ) {
            // همیشه به 'unused' برمی‌گردیم — admin و frontend منقضی بودن را از
            // expires_at تشخیص می‌دهند، نه از وضعیت 'expired' که در schema وجود ندارد.
            $wpdb->update(
                $wpdb->prefix . 'psy_invitations',
                [ 'status' => 'unused' ],
                [ 'id' => $inv->id, 'status' => 'processing' ]
            );
            // باگ E: پیام دقیق‌تر — تشخیص اینکه آیا مشکل از کلید تکراری است یا تعداد اشتباه.
            // intval() روی کلیدهای مثل "1" و "01" هر دو را 1 می‌کند، پس received_ids کوتاه‌تر است.
            $raw_count = count( (array) $params['answers'] );
            $msg = ( count( $received_ids ) < $raw_count )
                ? 'پاسخ‌های تکراری ارسال شده‌اند (شناسه سوال یکسان).'
                : 'شناسه سوالات ارسال‌شده با این آزمون مطابقت ندارد.';
            return new WP_REST_Response( [ 'error' => $msg ], 400 );
        }

        // بررسی بازه مقادیر پاسخ‌ها — باید در محدوده scale آزمون باشند
        // آزمون‌هایی که get_question_scales() دارند مقیاس per-question دارند
        $global_scale      = $test->get_scale();
        $q_scales          = method_exists( $test, 'get_question_scales' ) ? $test->get_question_scales() : [];
        foreach ( $clean_answers as $qid => $val ) {
            $qs      = $q_scales[$qid] ?? $global_scale;
            $s_min   = isset($qs['min']) ? intval($qs['min']) : 0;
            $s_max   = isset($qs['max']) ? intval($qs['max']) : PHP_INT_MAX;
            // مقادیر خارج از بازه به حد مجاز clamp می‌شوند (در Scoring هم این کار انجام می‌شود،
            // اما اینجا اعمال می‌کنیم تا داده‌های پاکیزه‌تری در DB داشته باشیم)
            $clean_answers[$qid] = max( $s_min, min( $s_max, $val ) );
        }

        // محاسبه نتایج — با try/catch تا در صورت خطا، claim برگردانده شود
        try {
            $result_data = $test->calculate( $clean_answers );
        } catch ( \Throwable $e ) {
            psy_log( 'error', 'rest_handler', 'calculate() failed', [ 'quiz_id' => $inv->quiz_id, 'error' => $e->getMessage() ] );
            // همیشه به 'unused' rollback می‌کنیم. اگر توکن منقضی شده باشد،
            // admin و frontend از expires_at تشخیص می‌دهند — وضعیت 'expired' در schema نیست.
            $wpdb->update(
                $wpdb->prefix . 'psy_invitations',
                [ 'status' => 'unused' ],
                [ 'id' => $inv->id, 'status' => 'processing' ]
            );
            return new WP_REST_Response( [ 'error' => 'خطا در محاسبه نتایج. لطفاً دوباره تلاش کنید.' ], 500 );
        }

        // ذخیره در دیتابیس
        // رمزنگاری داده‌های حساس (پاسخ‌ها و نتیجه‌ی روان‌سنجی) با کلید
        // مشترک اکوسیستم (هسته مشترک). اگر هسته یا کلید تنظیم نشده باشد،
        // به‌جای شکست کل ثبت آزمون (که تجربه‌ی مراجع را خراب می‌کند)، مقدار
        // به‌صورت متن ساده ذخیره می‌شود — دقیقاً رفتار قبلی. توصیه می‌شود
        // هسته مشترک همیشه با کلید تنظیم‌شده فعال باشد.
        $answers_json = wp_json_encode( $clean_answers );
        $result_json  = wp_json_encode( $result_data );
        if ( class_exists( 'Pas_Core_Crypto' ) && Pas_Core_Crypto::is_configured() ) {
            $answers_json = Pas_Core_Crypto::wrap_encrypted( $answers_json );
            $result_json  = Pas_Core_Crypto::wrap_encrypted( $result_json );
        }

        $inserted = $wpdb->insert( $wpdb->prefix . 'psy_attempts', [
            'quiz_id'        => $inv->quiz_id,
            'invitation_id'  => $inv->id,
            'answers'        => $answers_json,
            'result_data'    => $result_json,
            'completed_at'   => current_time( 'mysql' ), // timezone وردپرس، نه UTC سرور MySQL
        ] );
        $attempt_id = $wpdb->insert_id;

        // اگر insert ناموفق بود ($inserted===false) یا insert_id برنگشت
        if ( $inserted === false || ! $attempt_id ) {
            psy_log( 'error', 'rest_handler', 'attempt insert failed', [ 'db_error' => $wpdb->last_error, 'quiz_id' => $inv->quiz_id ] );
            // همیشه به 'unused' rollback می‌کنیم تا رکورد در ادمین قابل مشاهده باشد.
            $wpdb->update(
                $wpdb->prefix . 'psy_invitations',
                [ 'status' => 'unused' ],
                [ 'id' => $inv->id, 'status' => 'processing' ]
            );
            return new WP_REST_Response( [ 'error' => 'خطا در ذخیره‌سازی. لطفاً دوباره تلاش کنید.' ], 500 );
        }

        Psy_Quiz_Invitation_Manager::complete( $inv->id, $attempt_id );

        // حذف draft پس از submit موفق — دیگر نیازی به resume نیست
        $wpdb->delete( $wpdb->prefix . 'psy_drafts', [ 'token' => $token ], [ '%s' ] );

        // ارسال ایمیل گزارش به روانشناس
        self::send_report_email( $inv, $test, $clean_answers, $result_data, $attempt_id );

        return new WP_REST_Response( [
            'success'    => true,
            'attempt_id' => $attempt_id,
            'message'    => 'آزمون با موفقیت ثبت شد.',
        ], 200 );
    }

    // ── ارسال ایمیل گزارش کامل به روانشناس ────────────────────────────────
    // ── ذخیره موقت پاسخ‌های نیمه‌تمام ────────────────────────────────────────
    public function save_draft( WP_REST_Request $req ): WP_REST_Response {
        global $wpdb;

        if ( ! self::check_rate_limit( 'draft_save', 30 ) ) {
            return self::rate_limited_response();
        }

        $params  = $req->get_json_params();
        $token   = isset( $params['token'] )     ? sanitize_text_field( $params['token'] ) : '';
        $answers = isset( $params['answers'] )   ? (array) $params['answers']              : [];
        $last_q  = isset( $params['last_qnum'] ) ? absint( $params['last_qnum'] )          : 1;

        if ( empty( $token ) ) {
            return new WP_REST_Response( [ 'error' => 'توکن الزامی است.' ], 400 );
        }

        // بررسی اینکه توکن هنوز unused است — draft برای توکن تکمیل/لغوشده بی‌معنی است
        $status = $wpdb->get_var( $wpdb->prepare(
            "SELECT status FROM {$wpdb->prefix}psy_invitations WHERE access_token = %s LIMIT 1",
            $token
        ) );
        if ( $status !== 'unused' ) {
            return new WP_REST_Response( [ 'ok' => false ], 200 );
        }

        // سقف اندازه: این endpoint عمومی است و فقط توکن معتبر + rate-limit دارد؛
        // بدون سقف، یک توکن معتبر می‌توانست JSON بسیار بزرگ در دیتابیس بنویسد.
        $answers_json = (string) wp_json_encode( $answers );
        if ( strlen( $answers_json ) > 262144 ) { // 256KB
            return new WP_REST_Response( [ 'error' => 'حجم پاسخ‌ها بیش از حد مجاز است.' ], 413 );
        }

        // UPSERT — اگر draft قبلی وجود داشت update می‌شود
        $wpdb->query( $wpdb->prepare(
            "INSERT INTO {$wpdb->prefix}psy_drafts (token, answers, last_qnum, saved_at)
             VALUES (%s, %s, %d, NOW())
             ON DUPLICATE KEY UPDATE answers = VALUES(answers), last_qnum = VALUES(last_qnum), saved_at = NOW()",
            $token,
            $answers_json,
            $last_q
        ) );

        return new WP_REST_Response( [ 'ok' => true ], 200 );
    }

    // ── بازیابی draft ذخیره‌شده ───────────────────────────────────────────────
    public function get_draft( WP_REST_Request $req ): WP_REST_Response {
        global $wpdb;

        if ( ! self::check_rate_limit( 'draft_get', 30 ) ) {
            return self::rate_limited_response();
        }

        $token = sanitize_text_field( $req->get_param( 'token' ) );
        if ( empty( $token ) ) {
            return new WP_REST_Response( [ 'error' => 'توکن الزامی است.' ], 400 );
        }

        $draft = $wpdb->get_row( $wpdb->prepare(
            "SELECT answers, last_qnum FROM {$wpdb->prefix}psy_drafts WHERE token = %s LIMIT 1",
            $token
        ) );

        if ( ! $draft ) {
            return new WP_REST_Response( [ 'found' => false ], 200 );
        }

        return new WP_REST_Response( [
            'found'     => true,
            'answers'   => json_decode( $draft->answers, true ) ?: [],
            'last_qnum' => (int) $draft->last_qnum,
        ], 200 );
    }

    // ───────────────────────────────────────────────────────────────────────
    private static function send_report_email( object $inv, object $test, array $answers, array $result_data, int $attempt_id ): void {

        $to = sanitize_email( get_option( 'psy_quiz_therapist_email', '' ) );
        if ( empty( $to ) ) {
            psy_log( 'warning', 'rest_handler', 'therapist email not set — skipping report email' );
            return;
        }

        $client_name = $inv->client_name;
        $quiz_name   = $test->get_name();
        $jalali      = function_exists( 'pa_to_jalali' )
            ? pa_to_jalali( current_time( 'mysql' ), 'Y/m/d ساعت H:i' )
            : current_time( 'Y/m/d H:i' );
        $site_name   = get_bloginfo( 'name' );
        $report_url  = esc_url( admin_url( 'admin.php?page=psy-quiz-reports&attempt=' . $attempt_id ) );

        // ── ساخت جدول پاسخ‌ها ──────────────────────────────────────────
        $questions     = $test->get_questions();
        $answers_rows  = '';
        foreach ( $questions as $qid => $q ) {
            $text  = is_array( $q ) ? ( $q['text'] ?? $q['q'] ?? "سوال $qid" ) : $q;
            $val   = $answers[ $qid ] ?? '—';
            $answers_rows .= sprintf(
                '<tr><td style="padding:5px 10px;border:1px solid #e2e8f0;color:#4a5568;">%s</td>'
                . '<td style="padding:5px 10px;border:1px solid #e2e8f0;text-align:center;font-weight:bold;color:#2b6cb0;">%s</td></tr>',
                esc_html( $text ),
                esc_html( (string) $val )
            );
        }

        // ── ساخت جدول نتایج/تحلیل ──────────────────────────────────────
        $result_rows = '';
        foreach ( $result_data as $key => $value ) {
            if ( is_array( $value ) ) {
                $display = implode( '، ', array_map( fn($v) => is_scalar($v) ? $v : json_encode($v, JSON_UNESCAPED_UNICODE), $value ) );
            } else {
                $display = (string) $value;
            }
            $result_rows .= sprintf(
                '<tr><td style="padding:5px 10px;border:1px solid #e2e8f0;color:#4a5568;font-weight:bold;">%s</td>'
                . '<td style="padding:5px 10px;border:1px solid #e2e8f0;color:#2d3748;">%s</td></tr>',
                esc_html( $key ),
                esc_html( $display )
            );
        }

        // ── ساخت HTML ایمیل ────────────────────────────────────────────
        $html = <<<HTML
<!DOCTYPE html>
<html dir="rtl" lang="fa">
<head><meta charset="UTF-8"><title>گزارش آزمون</title></head>
<body style="font-family:Tahoma,Arial,sans-serif;direction:rtl;background:#f7fafc;margin:0;padding:20px;">
  <div style="max-width:700px;margin:0 auto;background:#fff;border-radius:8px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.1);">

    <!-- هدر -->
    <div style="background:linear-gradient(135deg,#2b6cb0,#553c9a);padding:20px 24px;color:#fff;">
      <div style="font-size:18px;font-weight:bold;">📊 گزارش آزمون — {$quiz_name}</div>
      <div style="font-size:13px;margin-top:6px;opacity:.85;">{$site_name}</div>
    </div>

    <!-- اطلاعات مراجع -->
    <div style="padding:16px 24px;background:#edf2f7;border-bottom:1px solid #e2e8f0;">
      <table style="width:100%;border-collapse:collapse;">
        <tr>
          <td style="padding:4px 8px;font-size:13px;color:#4a5568;">👤 نام مراجع:</td>
          <td style="padding:4px 8px;font-size:13px;font-weight:bold;color:#2d3748;">{$client_name}</td>
          <td style="padding:4px 8px;font-size:13px;color:#4a5568;">📅 تاریخ تکمیل:</td>
          <td style="padding:4px 8px;font-size:13px;color:#2d3748;">{$jalali}</td>
        </tr>
      </table>
    </div>

    <div style="padding:20px 24px;">

      <!-- نتایج و تحلیل -->
      <h3 style="font-size:14px;color:#2b6cb0;border-bottom:2px solid #bee3f8;padding-bottom:6px;margin-bottom:12px;">📈 نتایج و تحلیل</h3>
      <table style="width:100%;border-collapse:collapse;margin-bottom:24px;font-size:12px;">
        <thead>
          <tr style="background:#ebf8ff;">
            <th style="padding:7px 10px;border:1px solid #e2e8f0;text-align:right;">شاخص</th>
            <th style="padding:7px 10px;border:1px solid #e2e8f0;text-align:right;">مقدار</th>
          </tr>
        </thead>
        <tbody>{$result_rows}</tbody>
      </table>

      <!-- جدول پاسخ‌ها -->
      <h3 style="font-size:14px;color:#2b6cb0;border-bottom:2px solid #bee3f8;padding-bottom:6px;margin-bottom:12px;">📋 پاسخ‌های مراجع</h3>
      <table style="width:100%;border-collapse:collapse;font-size:11px;">
        <thead>
          <tr style="background:#ebf8ff;">
            <th style="padding:7px 10px;border:1px solid #e2e8f0;text-align:right;">سوال</th>
            <th style="padding:7px 10px;border:1px solid #e2e8f0;width:60px;">پاسخ</th>
          </tr>
        </thead>
        <tbody>{$answers_rows}</tbody>
      </table>

      <!-- لینک گزارش کامل -->
      <div style="margin-top:20px;text-align:center;">
        <a href="{$report_url}" style="display:inline-block;padding:10px 24px;background:#2b6cb0;color:#fff;border-radius:6px;text-decoration:none;font-size:13px;">
          مشاهده گزارش کامل در پنل ←
        </a>
      </div>

    </div>

    <!-- فوتر -->
    <div style="padding:12px 24px;background:#f7fafc;border-top:1px solid #e2e8f0;font-size:10px;color:#a0aec0;text-align:center;">
      این ایمیل به صورت خودکار توسط {$site_name} ارسال شده است. این گزارش صرفاً جنبه کمک‌تشخیصی دارد.
    </div>

  </div>
</body>
</html>
HTML;

        $subject = sprintf( '[%s] گزارش آزمون %s — %s', $site_name, $quiz_name, $client_name );

        // BUG-05: حذف کاراکترهای خطرناک از site_name برای جلوگیری از email header injection
        $safe_from_name = preg_replace( '/[^\p{L}\p{N}\s\-_.@]/u', '', $site_name );
        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $safe_from_name . ' <' . sanitize_email( get_option( 'admin_email' ) ) . '>',
        ];

        $sent = wp_mail( $to, $subject, $html, $headers );
        if ( ! $sent ) {
            psy_log( 'warning', 'rest_handler', 'wp_mail failed', [ 'attempt_id' => $attempt_id, 'to' => $to ] );
        }
    }

    // ───────────────────────────────────────────────────────────────────────
    public function get_report( WP_REST_Request $req ): WP_REST_Response {
        global $wpdb;

        $attempt_id = intval( $req->get_param('id') );
        $token      = sanitize_text_field( $req->get_param('token') ?? '' );

        $attempt    = $wpdb->get_row( $wpdb->prepare(
            "SELECT a.*, i.client_name, i.client_phone AS patient_phone, i.access_token FROM {$wpdb->prefix}psy_attempts a
             JOIN {$wpdb->prefix}psy_invitations i ON a.invitation_id = i.id
             WHERE a.id = %d", $attempt_id
        ) );

        if ( ! $attempt ) {
            return new WP_REST_Response( [ 'error' => 'گزارش یافت نشد.' ], 404 );
        }

        // تأیید که token متعلق به همین attempt است (ادمین لاگین‌شده استثنا دارد)
        $is_admin = current_user_can( 'manage_options' );
        if ( ! $is_admin && ! hash_equals( $attempt->access_token, $token ) ) {
            return new WP_REST_Response( [ 'error' => 'دسترسی غیرمجاز.' ], 403 );
        }

        $test = \PsychAssessments\Core\Registry::get( $attempt->quiz_id );
        if ( ! $test ) {
            return new WP_REST_Response( [ 'error' => 'آزمون یافت نشد.' ], 404 );
        }

        // رمزگشایی (اگر رمزنگاری‌شده بود) یا استفاده‌ی مستقیم (اگر رکورد
        // قدیمی و هنوز متن ساده بود) — pa_decrypt_json هر دو حالت را پوشش
        // می‌دهد، پس نیازی به migration دستی رکوردهای قدیمی نیست.
        $result_data = pa_decrypt_json( $attempt->result_data );
        $html        = $test->render_report( $result_data, $attempt );

        return new WP_REST_Response( [
            'html'        => $html,
            'client_name' => $attempt->client_name,
            'quiz_name'   => $test->get_name(),
            'completed_at'=> $attempt->completed_at,
        ], 200 );
    }
}
