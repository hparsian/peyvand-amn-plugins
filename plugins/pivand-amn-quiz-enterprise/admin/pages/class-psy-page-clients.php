<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Psy_Page_Clients — داشبورد مراجعین + فرم مراجع جدید
 */
class Psy_Page_Clients {

    use Psy_Pagination;

    /**
     * نتیجه‌ی پردازش فرم «مراجع جدید» که در admin_init (پیش از هر خروجی HTML)
     * انجام می‌شود — نگاه کنید به Psy_Page_Clients::process_new_client_submission().
     * از یک redirect ناموفق به‌خاطر «headers already sent» جلوگیری می‌کند:
     * چون callback صفحه (render_new_client) بعد از چاپ کامل هدر/منوی ادمین
     * توسط WordPress صدا زده می‌شود، هر wp_safe_redirect() آنجا همیشه دیر
     * است و کاربر با یک صفحه‌ی نیمه‌کاره/سفید مواجه می‌شود.
     */
    private static ?array $early_result = null;

    /**
     * پردازش فرم پیش از هر خروجی — روی admin_init هوک می‌شود.
     */
    public static function process_new_client_submission(): void {
        if ( ! isset( $_GET['page'] ) || $_GET['page'] !== 'psy-quiz-new' ) return;
        if ( ! isset( $_POST['psy_new_client_nonce'] ) ) return;
        if ( ! current_user_can( 'manage_options' ) ) return;

        $result = self::handle_new_client();
        if ( $result === null ) return;

        if ( isset( $result['success'] ) ) {
            $transient_key = 'psy_new_client_' . wp_generate_password( 16, false );
            set_transient( $transient_key, $result, 5 * MINUTE_IN_SECONDS );
            wp_safe_redirect( add_query_arg( [
                'page'     => 'psy-quiz-new',
                'psy_done' => '1',
                'psy_key'  => $transient_key,
            ], admin_url( 'admin.php' ) ) );
            exit;
        }

        // خطا: نیازی به redirect نیست، فقط برای نمایش در render_new_client ذخیره می‌شود
        self::$early_result = $result;
    }

    private static function handle_new_client(): ?array {
        if ( ! isset( $_POST['psy_new_client_nonce'] ) ) return null;
        if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['psy_new_client_nonce'] ) ), 'psy_new_client' ) ) {
            return [ 'error' => 'خطای امنیتی. لطفاً دوباره امتحان کنید.' ];
        }

        $name     = sanitize_text_field( wp_unslash( $_POST['client_name']  ?? '' ) );
        $phone    = sanitize_text_field( wp_unslash( $_POST['client_phone'] ?? '' ) );
        $email    = sanitize_email( wp_unslash( $_POST['client_email'] ?? '' ) );

        // چند-انتخابی: quiz_id از select چندگانه به‌صورت آرایه می‌آید
        // (name="quiz_id[]")؛ برای سازگاری با هر کد قدیمی/فرم دستی که هنوز
        // مقدار تکی می‌فرستد، هر دو حالت پشتیبانی می‌شود.
        $raw_quiz_ids = wp_unslash( $_POST['quiz_id'] ?? [] );
        $quiz_ids     = array_values( array_unique( array_filter( array_map(
            'sanitize_key',
            is_array( $raw_quiz_ids ) ? $raw_quiz_ids : [ $raw_quiz_ids ]
        ) ) ) );

        if ( ! $name || empty( $quiz_ids ) ) {
            return [ 'error' => 'نام مراجع و حداقل یک آزمون الزامی است.' ];
        }

        // شماره موبایل الزامی است — باید معتبر باشد و دقیقاً به فرمت 09xxxxxxxxx
        // نرمالایز شود — همان فرمتی که در پنل مراجع افزونه‌های دیگر (مثل ورود
        // OTP) به‌عنوان شناسه کاربر ذخیره می‌شود. بدون این تطبیق دقیق، آزمون
        // تعیین‌شده در پنل مراجع نمایش داده نمی‌شود.
        //
        // نکته‌ی مهم: این شماره حتی برای مراجعین خارج از کشور هم الزامی
        // می‌ماند (شناسه‌ی هویتی آن‌ها در کل اکوسیستم همچنان همین شماره است)
        // — ایمیل فقط *کانال ورود جایگزین* برای همین شماره است، نه چیزی
        // مستقل از آن. get_mobile_for_email در Invitation_Manager صراحتاً
        // فقط دعوت‌نامه‌هایی را می‌بیند که client_phone خالی نیست؛ اگر اینجا
        // شماره را اختیاری کنیم، مراجع خارج از کشور اصلاً نمی‌تواند با
        // کد ایمیلی هم وارد پنل شود (چون هیچ شماره‌ای برای پیدا کردنش وجود
        // نخواهد داشت).
        if ( $phone === '' ) {
            return [ 'error' => 'شماره موبایل مراجع الزامی است (حتی برای مراجعین خارج از کشور — به‌عنوان شناسه‌ی هویتی سیستم لازم است، هرچند پیامک واقعی برایشان ارسال نشود).' ];
        }
        $phone = Psy_Quiz_Invitation_Manager::to_local_format( $phone );
        if ( ! Psy_Quiz_Invitation_Manager::is_valid_mobile( $phone ) ) {
            return [ 'error' => 'فرمت شماره موبایل نامعتبر است. باید به‌صورت 09xxxxxxxxx (۱۱ رقم) یا برای مراجع خارج از کشور با + یا 00 و کد کشور (مثلاً ‎+14155552671‎ یا ‎0014155552671‎) وارد شود.' ];
        }

        // ایمیل اختیاری است — فقط برای مراجعینی که نمی‌توانند پیامک ایرانی
        // دریافت کنند (مثلاً مراجعین خارج از کشور) و می‌خواهند در پنل با کد
        // ایمیلی به‌جای پیامکی وارد شوند. اگر پر شده، باید فرمت معتبر باشد.
        if ( $email !== '' && ! is_email( $email ) ) {
            return [ 'error' => 'فرمت ایمیل وارد شده معتبر نیست.' ];
        }

        // هر quiz_id باید در رجیستری معتبر باشد — به‌جای رد کل درخواست با
        // یک آزمون نامعتبر، فقط همان‌ها را رد می‌کنیم (مثلاً اگر بین
        // بارگذاری فرم و ثبت، یک آزمون از رجیستری حذف شده باشد).
        $tests = [];
        foreach ( $quiz_ids as $qid ) {
            $test = \PsychAssessments\Core\Registry::get( $qid );
            if ( $test ) {
                $tests[ $qid ] = $test;
            }
        }
        if ( empty( $tests ) ) {
            return [ 'error' => 'هیچ‌کدام از آزمون‌های انتخابی معتبر نیست.' ];
        }

        // یک دعوت‌نامه (توکن/لینک اختصاصی) به‌ازای هر آزمون انتخاب‌شده —
        // مراجع همه را با هم در تب «آزمون‌ها»ی پنل خودش می‌بیند
        // (نگاه کنید به Psy_Quiz_Invitation_Manager::get_pending_for_phone).
        $created = [];
        foreach ( $tests as $qid => $test ) {
            $one_result = Psy_Quiz_Invitation_Manager::create( $qid, $name, $phone, null, $email );
            $created[]  = [
                'quiz_name'   => $test->get_name(),
                'link'        => $one_result['link'],
                'expires_msg' => Psy_Quiz_Invitation_Manager::expiry_message( $one_result['expires_at'] ?? null ),
            ];
        }

        // پیام واحد واتساپ که همه‌ی آزمون‌ها/لینک‌های تازه‌ساخته‌شده را با هم
        // فهرست می‌کند — به‌جای باز کردن چند تب جدا برای هر آزمون.
        $quiz_names_joined = implode( '، ', wp_list_pluck( $created, 'quiz_name' ) );
        $wa_text  = "✨ سلام {$name} عزیز\n\n";
        $wa_text .= "امیدوارم حالتون خوب باشه 🌿\n\n";
        $wa_text .= ( count( $created ) > 1 ? "آزمون‌های زیر برای شما آماده شده است:\n" : "آزمون شما آماده شده است:\n" );
        $wa_text .= "🔗 {$quiz_names_joined}\n\n";
        foreach ( $created as $c ) {
            $wa_text .= "• {$c['quiz_name']}: {$c['link']}\n";
        }
        $wa_text .= "\n⏱ لطفاً در فضایی آرام و بدون عجله به سؤالات پاسخ دهید.\n\n";
        $wa_text .= "در صورت هرگونه سؤال در خدمتم 🙏";
        $w_url = 'https://api.whatsapp.com/send?phone=' . Psy_Quiz_Invitation_Manager::normalize_phone( $phone ) . '&text=' . rawurlencode( $wa_text );

        // اطلاع‌رسانی خودکار به مراجع: اگر ایمیل ثبت شده (یعنی مراجع خارج از
        // کشور / پیامک ایرانی دریافت نمی‌کند) فقط ایمیل می‌رود، وگرنه پیامک —
        // نگاه کنید به مستندات notify_client در class-psy-invitation-manager.php.
        // یک‌بار برای کل ثبت (نه به‌ازای هر آزمون) کافی است چون پیام فقط به
        // پنل عمومی مراجع اشاره می‌کند، نه به لینک یک آزمون خاص.
        $notify_status = Psy_Quiz_Invitation_Manager::notify_client( $name, $phone, $email );

        $sent_channels   = [];
        $failed_channels = [];
        if ( $notify_status['sms'] === true )   $sent_channels[]   = 'پیامک';
        if ( $notify_status['sms'] === false )  $failed_channels[] = 'پیامک';
        if ( $notify_status['email'] === true )  $sent_channels[]   = 'ایمیل';
        if ( $notify_status['email'] === false ) $failed_channels[] = 'ایمیل';

        $notify_msg = '';
        if ( ! empty( $notify_status['throttled'] ) ) {
            // برای این مراجع امروز قبلاً یک پیامک/ایمیل «آزمون آماده است»
            // رفته؛ چون لینک پنل ثابت است، آزمون تازه هم همان‌جا در دسترس
            // اوست — نیازی به اطلاع‌رسانی تکراری نیست.
            $notify_msg = 'ℹ️ امروز قبلاً برای این مراجع پیامک/ایمیل «آزمون آماده است» ارسال شده؛ برای جلوگیری از مزاحمت، دوباره ارسال نشد. آزمون‌های جدید هم در همان پنل مراجع در دسترس اوست.';
        } else {
            if ( $sent_channels ) {
                $notify_msg .= 'اطلاع‌رسانی ورود به پنل از طریق ' . implode( ' و ', $sent_channels ) . ' ارسال شد.';
            }
            if ( $failed_channels ) {
                $notify_msg .= ( $notify_msg ? ' ' : '' ) . '⚠️ ارسال از طریق ' . implode( ' و ', $failed_channels ) . ' ناموفق بود — لینک را دستی برای مراجع ارسال کنید.';
            }
        }

        return [
            'success'     => true,
            'client_name' => $name,
            'quizzes'     => $created,
            'whatsapp'    => $w_url,
            'notify_msg'  => $notify_msg,
        ];
    }

    /**
     * پردازش Revoke / Delete / ارسال اجباری — روی admin_init هوک می‌شود
     * (نگاه کنید به pivand-amn-quiz-enterprise.php)، **نه** از داخل
     * render_dashboard() صدا زده می‌شود.
     *
     * دلیل (همان باگی که قبلاً در process_new_client_submission رفع شده
     * بود، اینجا هنوز وجود داشت): callback صفحه‌ی ادمین (render_dashboard)
     * توسط وردپرس *بعد از* چاپ کامل هدر/منوی ادمین صدا زده می‌شود. اگر این
     * متد همان‌جا (از داخل render_dashboard) فراخوانی شود، wp_safe_redirect()
     * همیشه دیر است — چون HTTP header دیگر قابل ارسال نیست (headers already
     * sent) — و کاربر بعد از حذف/لغو یک آزمون با یک صفحه‌ی نیمه‌کاره یا
     * هنگ‌کرده مواجه می‌شود که فقط با رفرش دستی درست می‌شود. اجرا روی
     * admin_init این مشکل را حل می‌کند چون قبل از هر خروجی HTML اجرا می‌شود.
     */
    public static function handle_revoke(): void {
        if ( ! isset( $_GET['page'] ) || $_GET['page'] !== 'psy-quiz' ) return;
        if ( ! isset( $_GET['psy_action'], $_GET['inv_id'], $_GET['_wpnonce'] ) ) return;
        if ( ! current_user_can( 'manage_options' ) ) return;

        $action = sanitize_key( wp_unslash( $_GET['psy_action'] ) );
        $inv_id = absint( $_GET['inv_id'] );
        // BUG-R1 fix: $paged باید قبل از هر branch تعریف شود — در branch revoke استفاده می‌شود
        $paged  = max( 1, intval( $_GET['paged'] ?? 1 ) );

        if ( ! in_array( $action, [ 'revoke', 'delete_inv', 'delete_client', 'force_notify' ], true ) ) return;
        if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'psy_inv_' . $action . '_' . $inv_id ) ) {
            wp_die( 'خطای امنیتی.', 403 );
        }

        global $wpdb;

        if ( $action === 'force_notify' ) {
            // ارسال اجباریِ اطلاع‌رسانی «آزمون آماده است»: برخلاف مسیر خودکار
            // notify_client() که حداکثر یک‌بار در روز اجرا می‌شود، این عملیات
            // صراحتاً همان محدودیت را دور می‌زند — برای مواردی که ادمین
            // می‌داند مراجع پیامک/ایمیل را نگرفته یا لازم است دوباره یادآوری
            // شود. نتیجه‌ی دقیق (نه فقط «انجام شد») در querystring برگردانده
            // می‌شود تا پیام درست به ادمین نمایش داده شود.
            $inv = $wpdb->get_row( $wpdb->prepare(
                "SELECT client_name, client_phone, client_email FROM {$wpdb->prefix}psy_invitations WHERE id = %d",
                $inv_id
            ) );

            if ( ! $inv ) {
                wp_safe_redirect( add_query_arg( [
                    'page'  => 'psy-quiz',
                    'paged' => $paged,
                    'done'  => 'force_notify_missing',
                ], admin_url( 'admin.php' ) ) );
                exit;
            }

            $notify_status = Psy_Quiz_Invitation_Manager::notify_client(
                (string) $inv->client_name,
                (string) $inv->client_phone,
                (string) $inv->client_email,
                true // bypass_throttle
            );

            $done_action = ( $notify_status['sms'] === true || $notify_status['email'] === true )
                ? 'force_notify'
                : 'force_notify_failed';

            wp_safe_redirect( add_query_arg( [
                'page'  => 'psy-quiz',
                'paged' => $paged,
                'done'  => $done_action,
            ], admin_url( 'admin.php' ) ) );
            exit;
        }

        if ( $action === 'revoke' ) {
            // لغو: وضعیت را به 'revoked' تغییر می‌دهد — داده محفوظ می‌ماند
            // BUG-03: بررسی rows_affected — اگر status دیگر 'unused' نبود، لغو انجام نشده
            $wpdb->update(
                $wpdb->prefix . 'psy_invitations',
                [ 'status' => 'revoked' ],
                [ 'id' => $inv_id, 'status' => 'unused' ]
            );
            $done_action = $wpdb->rows_affected > 0 ? 'revoke' : 'revoke_failed';
            wp_safe_redirect( add_query_arg( [
                'page'  => 'psy-quiz',
                'paged' => $paged,
                'done'  => $done_action,
            ], admin_url( 'admin.php' ) ) );
            exit;
        } elseif ( $action === 'delete_inv' ) {
            // حذف: فقط دعوتنامه‌های تکمیل‌نشده حذف می‌شوند (داده بالینی حفظ می‌شود)
            $wpdb->delete(
                $wpdb->prefix . 'psy_invitations',
                [ 'id' => $inv_id ],
                [ '%d' ]
            );
        } elseif ( $action === 'delete_client' ) {
            // حذف کامل: دعوتنامه + داده بالینی (attempt) با هم حذف می‌شوند
            // BUG-08: ابتدا status را به 'revoked' تغییر می‌دهیم تا اگر REST handler
            // همزمان مشغول complete() باشد، نتواند attempt_id را ست کند.
            $wpdb->update(
                $wpdb->prefix . 'psy_invitations',
                [ 'status' => 'revoked' ],
                [ 'id' => $inv_id ]
            );
            // کوتاه صبر می‌کنیم تا هر request در حال پردازش تمام شود (در محیط سرور عادی کافی است)
            $inv = $wpdb->get_row( $wpdb->prepare(
                "SELECT attempt_id FROM {$wpdb->prefix}psy_invitations WHERE id = %d",
                $inv_id
            ) );
            if ( $inv && $inv->attempt_id ) {
                $wpdb->delete( $wpdb->prefix . 'psy_attempts', [ 'id' => $inv->attempt_id ], [ '%d' ] );
            }
            $wpdb->delete( $wpdb->prefix . 'psy_invitations', [ 'id' => $inv_id ], [ '%d' ] );
        }

        wp_safe_redirect( add_query_arg( [
            'page'  => 'psy-quiz',
            'paged' => $paged,
            'done'  => $action,
        ], admin_url( 'admin.php' ) ) );
        exit;
    }

    // ── داشبورد مراجعین ─────────────────────────────────────────────────
    public static function render_dashboard(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'دسترسی غیرمجاز.', 'pivand-amn-quiz-enterprise' ), 403 );
        }

        // پردازش Revoke/Delete/ارسال اجباری پیش از این در admin_init انجام
        // شده (نگاه کنید به Psy_Page_Clients::handle_revoke) — اجرای آن
        // اینجا (بعد از چاپ هدر ادمین) باعث می‌شد wp_safe_redirect() همیشه
        // دیر باشد و صفحه بعد از حذف/لغو هنگ کند.

        global $wpdb;

        // ── Pagination ──────────────────────────────────────────────────
        $per_page   = 50;
        $paged      = max( 1, intval( $_GET['paged'] ?? 1 ) );
        $offset     = ( $paged - 1 ) * $per_page;

        // فیلتر وضعیت (اختیاری)
        $filter_status = isset( $_GET['filter_status'] ) ? sanitize_key( $_GET['filter_status'] ) : '';
        $allowed_statuses = [ 'unused', 'completed', 'expired', 'revoked', 'processing' ];
        if ( ! in_array( $filter_status, $allowed_statuses, true ) ) {
            $filter_status = '';
        }

        // جستجو (اختیاری): روی نام مراجع، شماره موبایل (نرمالایز‌شده) و
        // ایمیل. برای شماره موبایل همان to_local_format فرم ثبت اعمال
        // می‌شود تا جستجوی «09123456789» یا «+989123456789» یا «989123456789»
        // همه یک نتیجه بدهند — دقیقاً همان‌طور که client_phone در دیتابیس
        // همیشه به این فرمت نرمال ذخیره شده.
        $search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
        $search = trim( $search );

        // ساخت WHERE clause
        $where_parts = [];
        $where_args  = [];
        if ( $filter_status === 'expired' ) {
            $where_parts[] = "i.status = 'unused' AND i.expires_at IS NOT NULL AND i.expires_at <= %s";
            $where_args[]  = gmdate( 'Y-m-d H:i:s' );
        } elseif ( $filter_status ) {
            $where_parts[] = 'i.status = %s';
            $where_args[]  = $filter_status;
        }

        if ( $search !== '' ) {
            $search_phone = Psy_Quiz_Invitation_Manager::to_local_format( $search );
            $like         = '%' . $wpdb->esc_like( $search ) . '%';

            if ( $search_phone !== '' && Psy_Quiz_Invitation_Manager::is_valid_mobile( $search_phone ) ) {
                // ورودی یک شماره موبایل معتبر است (ایرانی یا خارجی): تطابق
                // دقیق روی فرمت نرمال‌شده، تا اعداد جزئی/نیمه‌کاره کاربر را
                // با شماره‌ی اشتباهی مچ نکند.
                $where_parts[] = '(i.client_name LIKE %s OR i.client_phone = %s OR i.client_email LIKE %s)';
                $where_args[]  = $like;
                $where_args[]  = $search_phone;
                $where_args[]  = $like;
            } else {
                $where_parts[] = '(i.client_name LIKE %s OR i.client_phone LIKE %s OR i.client_email LIKE %s)';
                $where_args[]  = $like;
                $where_args[]  = $like;
                $where_args[]  = $like;
            }
        }

        $where = $where_parts ? ( 'WHERE ' . implode( ' AND ', $where_parts ) ) : '';

        // کوئری تعداد کل (برای pagination)
        $count_sql = "SELECT COUNT(*) FROM {$wpdb->prefix}psy_invitations i $where";
        $total_count = $where_args
            ? (int) $wpdb->get_var( $wpdb->prepare( $count_sql, ...$where_args ) )
            : (int) $wpdb->get_var( $count_sql );

        // کوئری اصلی با LIMIT/OFFSET
        $data_sql = "SELECT i.*, a.completed_at as exam_date, a.id as attempt_id
                     FROM {$wpdb->prefix}psy_invitations i
                     LEFT JOIN {$wpdb->prefix}psy_attempts a ON i.attempt_id = a.id
                     $where
                     ORDER BY i.id DESC
                     LIMIT %d OFFSET %d";
        $query_args = array_merge( $where_args, [ $per_page, $offset ] );
        $records = $wpdb->get_results( $wpdb->prepare( $data_sql, ...$query_args ) );

        // آمار کلی (بدون فیلتر، با یک کوئری aggregate)
        // باگ #A4: پارامتر متغیر ندارد — phpcs:ignore برای سکوت linter
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $stats = $wpdb->get_row(
            "SELECT
                COUNT(*) AS total,
                SUM(status = 'completed') AS done,
                SUM(status = 'revoked') AS revoked,
                SUM(status IN ('unused','processing') AND (expires_at IS NULL OR expires_at > NOW())) AS pending,
                SUM(status = 'unused' AND expires_at IS NOT NULL AND expires_at <= NOW()) AS expired
             FROM {$wpdb->prefix}psy_invitations"
        );

        $total_pages = $total_count > 0 ? ceil( $total_count / $per_page ) : 1;
        $tests = \PsychAssessments\Core\Registry::all();

        // نوتیفیکیشن عملیات موفق
        $done_action = sanitize_key( $_GET['done'] ?? '' );
        ?>
        <div class="wrap psy-admin" dir="rtl">
            <h1 class="wp-heading-inline">📋 مدیریت مراجعین</h1>
            <a href="<?php echo admin_url('admin.php?page=psy-quiz-new'); ?>" class="page-title-action">+ مراجع جدید</a>
            <hr class="wp-header-end">

            <?php if ( $done_action === 'revoke' ): ?>
            <div class="notice notice-success is-dismissible"><p>✅ لینک با موفقیت لغو شد.</p></div>
            <?php elseif ( $done_action === 'revoke_failed' ): ?>
            <div class="notice notice-warning is-dismissible"><p>⚠️ لغو انجام نشد — این لینک در حال حاضر در وضعیت «در انتظار» نیست (احتمالاً در حال پردازش است).</p></div>
            <?php elseif ( $done_action === 'delete_inv' ): ?>
            <div class="notice notice-success is-dismissible"><p>🗑️ دعوتنامه حذف شد.</p></div>
            <?php elseif ( $done_action === 'delete_client' ): ?>
            <div class="notice notice-success is-dismissible"><p>🗑️ مراجع و تمام داده‌های بالینی حذف شدند.</p></div>
            <?php elseif ( $done_action === 'force_notify' ): ?>
            <div class="notice notice-success is-dismissible"><p>🔔 پیامک/ایمیل «آزمون آماده است» دوباره برای این مراجع ارسال شد.</p></div>
            <?php elseif ( $done_action === 'force_notify_failed' ): ?>
            <div class="notice notice-error is-dismissible"><p>❌ ارسال اجباری ناموفق بود — شماره موبایل/ایمیل را بررسی کنید یا لینک پنل را دستی برای مراجع بفرستید.</p></div>
            <?php elseif ( $done_action === 'force_notify_missing' ): ?>
            <div class="notice notice-error is-dismissible"><p>❌ این دعوتنامه پیدا نشد (احتمالاً قبلاً حذف شده).</p></div>
            <?php endif; ?>

            <div class="psy-stats-row">
                <?php
                self::stat_card( '📊 کل مراجعین', (int)($stats->total  ?? 0), '#0f766e' );
                self::stat_card( '✅ تکمیل شده',  (int)($stats->done   ?? 0), '#14b8a6' );
                self::stat_card( '⏳ در انتظار',  (int)($stats->pending ?? 0), '#c05621' );
                self::stat_card( '🔒 منقضی',      (int)($stats->expired ?? 0), '#64748b' );
                self::stat_card( '🚫 لغو شده',    (int)($stats->revoked ?? 0), '#e11d48' );
                self::stat_card( '🧪 آزمون‌ها',   count($tests),               '#7c3aed' );
                ?>
            </div>

            <!-- فیلتر وضعیت + جستجو -->
            <div class="psy-filter-bar">
                <span class="psy-text-bold psy-text-xs">فیلتر:</span>
                <?php
                $filter_options = [
                    ''          => 'همه',
                    'unused'    => '⏳ در انتظار',
                    'completed' => '✅ تکمیل‌شده',
                    'expired'   => '🔒 منقضی',
                    'revoked'   => '🚫 لغو‌شده',
                ];
                foreach ( $filter_options as $val => $label ):
                    $active = ( $filter_status === $val ) ? 'button-primary' : 'button-secondary';
                    // جستجوی فعلی هنگام تعویض فیلتر وضعیت حفظ می‌شود.
                    $url    = add_query_arg( array_filter( [
                        'page'          => 'psy-quiz',
                        'filter_status' => $val,
                        's'             => $search !== '' ? $search : null,
                        'paged'         => 1,
                    ] ), admin_url('admin.php') );
                ?>
                <a href="<?php echo esc_url($url); ?>" class="button <?php echo $active; ?>"><?php echo esc_html($label); ?></a>
                <?php endforeach; ?>

                <form method="get" class="psy-search-form">
                    <input type="hidden" name="page" value="psy-quiz">
                    <?php if ( $filter_status ): ?>
                    <input type="hidden" name="filter_status" value="<?php echo esc_attr($filter_status); ?>">
                    <?php endif; ?>
                    <input type="search" name="s" value="<?php echo esc_attr($search); ?>" placeholder="🔎 جستجوی نام، موبایل یا ایمیل مراجع…" class="regular-text">
                    <button type="submit" class="button">جستجو</button>
                    <?php if ( $search !== '' ): ?>
                    <a href="<?php echo esc_url( add_query_arg( array_filter( [ 'page' => 'psy-quiz', 'filter_status' => $filter_status ?: null ] ), admin_url('admin.php') ) ); ?>" class="button-link psy-mr-6">✕ پاک کردن</a>
                    <?php endif; ?>
                </form>

                <span class="psy-push-end">
                    <?php
                    // BUG-10: جلوگیری از نمایش «۱–۰» وقتی نتیجه‌ای نیست
                    $display_from = $total_count > 0 ? $offset + 1 : 0;
                    $display_to   = min( $offset + $per_page, $total_count );
                    echo 'نمایش ' . number_format($display_from) . '–' . number_format($display_to) . ' از ' . number_format($total_count) . ' مراجع';
                    ?>
                </span>
            </div>

            <table class="wp-list-table widefat fixed striped psy-table">
                <thead>
                    <tr>
                        <th width="20%">نام مراجع</th>
                        <th width="18%">آزمون</th>
                        <th width="12%">تاریخ ارسال</th>
                        <th width="12%">وضعیت</th>
                        <th>عملیات</th>
                    </tr>
                </thead>
                <tbody>
                <?php if ( empty($records) ): ?>
                    <tr><td colspan="5" class="psy-empty-state">
                        <?php if ( $search !== '' ): ?>
                        هیچ مراجعی مطابق «<?php echo esc_html($search); ?>» یافت نشد. <a href="<?php echo esc_url( add_query_arg( array_filter( [ 'page' => 'psy-quiz', 'filter_status' => $filter_status ?: null ] ), admin_url('admin.php') ) ); ?>">پاک کردن جستجو ←</a>
                        <?php elseif ( $filter_status ): ?>
                        هیچ مراجعی با این وضعیت یافت نشد. <a href="<?php echo admin_url('admin.php?page=psy-quiz'); ?>">نمایش همه ←</a>
                        <?php else: ?>
                        هنوز مراجعی ثبت نشده. <a href="<?php echo admin_url('admin.php?page=psy-quiz-new'); ?>">اولین مراجع را اضافه کنید ←</a>
                        <?php endif; ?>
                    </td></tr>
                <?php endif; ?>
                <?php foreach ( $records as $row ):
                    $test    = \PsychAssessments\Core\Registry::get( $row->quiz_id );
                    $tname   = $test ? $test->get_name() : $row->quiz_id;
                    $link    = home_url( '/?psy_exam=1&token=' . $row->access_token );
                    $w_url   = Psy_Quiz_Invitation_Manager::whatsapp_url( $row->client_name, $tname, $link, $row->client_phone );
                    $jalali  = pa_to_jalali( $row->created_at, 'Y/m/d' );
                    $is_done       = $row->status === 'completed';
                    $is_processing = $row->status === 'processing';
                    $is_revoked    = $row->status === 'revoked';
                    $is_expired    = ! $is_done && ! $is_processing && ! $is_revoked
                                     && ! empty( $row->expires_at )
                                     && strtotime( $row->expires_at ) < time();
                    $is_pending    = $row->status === 'unused' && ! $is_expired;

                    // nonce های عملیات
                    $nonce_revoke  = wp_create_nonce( 'psy_inv_revoke_' . $row->id );
                    $nonce_delete  = wp_create_nonce( 'psy_inv_delete_inv_' . $row->id );
                    $nonce_delcli  = wp_create_nonce( 'psy_inv_delete_client_' . $row->id );
                    $revoke_url    = add_query_arg( [
                        'page'       => 'psy-quiz',
                        'psy_action' => 'revoke',
                        'inv_id'     => $row->id,
                        '_wpnonce'   => $nonce_revoke,
                        'paged'      => $paged,
                    ], admin_url('admin.php') );
                    $delete_url    = add_query_arg( [
                        'page'       => 'psy-quiz',
                        'psy_action' => 'delete_inv',
                        'inv_id'     => $row->id,
                        '_wpnonce'   => $nonce_delete,
                        'paged'      => $paged,
                    ], admin_url('admin.php') );
                    $delete_cli_url = add_query_arg( [
                        'page'       => 'psy-quiz',
                        'psy_action' => 'delete_client',
                        'inv_id'     => $row->id,
                        '_wpnonce'   => $nonce_delcli,
                        'paged'      => $paged,
                    ], admin_url('admin.php') );
                    $nonce_notify   = wp_create_nonce( 'psy_inv_force_notify_' . $row->id );
                    $force_notify_url = add_query_arg( [
                        'page'       => 'psy-quiz',
                        'psy_action' => 'force_notify',
                        'inv_id'     => $row->id,
                        '_wpnonce'   => $nonce_notify,
                        'paged'      => $paged,
                    ], admin_url('admin.php') );
                ?>
                <tr>
                    <td>
                        <strong><?php echo esc_html($row->client_name); ?></strong>
                        <?php if ( $row->client_phone ): ?>
                        <br><small class="psy-text-soft"><?php echo esc_html($row->client_phone); ?></small>
                        <?php endif; ?>
                    </td>
                    <td><?php echo esc_html($tname); ?></td>
                    <td><?php echo esc_html($jalali); ?></td>
                    <td>
                        <?php if ( $is_done ): ?>
                        <span class="psy-badge psy-badge-done">تکمیل شده</span>
                        <?php elseif ( $is_revoked ): ?>
                        <span class="psy-badge psy-badge-danger-solid">لغو شده</span>
                        <?php elseif ( $is_expired ): ?>
                        <span class="psy-badge psy-badge-expired">منقضی</span>
                        <?php elseif ( $is_processing ): ?>
                        <span class="psy-badge psy-badge-purple-solid">در حال پردازش</span>
                        <?php else: ?>
                        <span class="psy-badge psy-badge-pending">در انتظار</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div class="psy-actions">
                            <?php if ( $is_pending ): ?>
                            <input type="text" readonly value="<?php echo esc_url($link); ?>" id="lnk-<?php echo absint($row->id); ?>" class="psy-link-input">
                            <button class="button button-secondary psy-copy-btn" data-id="<?php echo absint($row->id); ?>">📋 کپی</button>
                            <a href="<?php echo esc_url($w_url); ?>" target="_blank" class="button psy-wa-btn">💬 واتساپ</a>
                            <a href="<?php echo esc_url($force_notify_url); ?>"
                               class="button psy-btn-notify"
                               title="پیامک/ایمیل «آزمون آماده است» را همین حالا دوباره برای این مراجع بفرست — حتی اگر امروز قبلاً یک‌بار برایش ارسال شده باشد (محدودیت روزانه را برای همین یک مورد نادیده می‌گیرد). برای وقتی که مراجع می‌گوید پیامک نگرفته یا اسمش اشتباه ضبط شده و باید دوباره اطلاع‌رسانی شود."
                               onclick="return confirm('پیامک/ایمیل «آزمون آماده است» دوباره برای «<?php echo esc_js($row->client_name); ?>» ارسال شود؟\n\nاین دکمه محدودیتِ «یک پیامک در روز» را فقط برای همین ارسال نادیده می‌گیرد.');">🔔 ارسال اجباری</a>
                            <a href="<?php echo esc_url($revoke_url); ?>"
                               class="button psy-btn-revoke psy-text-danger-outline"
                               onclick="return confirm('لینک این مراجع لغو شود؟ مراجع دیگر نمی‌تواند آزمون دهد.');">🚫 لغو</a>
                            <a href="<?php echo esc_url($delete_url); ?>"
                               class="button psy-text-soft"
                               onclick="return confirm('این دعوتنامه حذف شود؟');">🗑️ حذف</a>

                            <?php elseif ( $is_done ): ?>
                            <?php if ( $row->attempt_id ): ?>
                            <a href="<?php echo esc_url( admin_url('admin.php?page=psy-quiz-reports&attempt=' . (int)$row->attempt_id) ); ?>" class="button button-primary">📊 گزارش</a>
                            <?php endif; ?>
                            <a href="<?php echo esc_url($delete_cli_url); ?>"
                               class="button psy-text-danger-outline"
                               onclick="return confirm('مراجع «<?php echo esc_js($row->client_name); ?>» حذف شود؟') && confirm('⚠️ تمام داده‌های بالینی این مراجع برای همیشه پاک می‌شوند. ادامه می‌دهید؟');">🗑️ حذف مراجع</a>

                            <?php elseif ( $is_processing ): ?>
                            <span class="psy-text-purple psy-text-xs">⏳ آزمون در جریان است...</span>
                            <a href="<?php echo esc_url($delete_cli_url); ?>"
                               class="button psy-text-soft"
                               onclick="return confirm('مراجع «<?php echo esc_js($row->client_name); ?>» حذف شود؟') && confirm('⚠️ تمام داده‌های بالینی این مراجع برای همیشه پاک می‌شوند. ادامه می‌دهید؟');">🗑️ حذف</a>

                            <?php else: // منقضی یا لغو شده ?>
                            <a href="<?php echo esc_url($delete_url); ?>"
                               class="button psy-text-soft"
                               onclick="return confirm('این دعوتنامه حذف شود؟');">🗑️ حذف</a>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>

            <!-- Pagination -->
            <?php
            $base_args = [ 'page' => 'psy-quiz' ];
            if ( $filter_status ) $base_args['filter_status'] = $filter_status;
            if ( $search !== '' ) $base_args['s'] = $search;
            self::render_pagination( $paged, $total_pages, $base_args );
            ?>

        </div>
        <?php
    }

    // ── فرم مراجع جدید ─────────────────────────────────────────────────
    public static function render_new_client(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'دسترسی غیرمجاز.', 'pivand-amn-quiz-enterprise' ), 403 );
        }

        // پردازش فرم و redirect موفقیت‌آمیز پیش از این در admin_init انجام شده
        // (نگاه کنید به process_new_client_submission) — اینجا فقط خطای
        // احتمالی (در صورت وجود) نمایش داده می‌شود.
        $result = self::$early_result;
        if ( isset( $_GET['psy_done'] ) && $_GET['psy_done'] === '1' ) {
            $tkey = sanitize_text_field( wp_unslash( $_GET['psy_key'] ?? '' ) );
            $cached = $tkey ? get_transient( $tkey ) : false;
            if ( $cached ) {
                delete_transient( $tkey );
                $result = $cached;
            }
        }

        $tests = \PsychAssessments\Core\Registry::by_category();
        ?>
        <div class="wrap psy-admin" dir="rtl">
            <h1>➕ ثبت مراجع جدید</h1>

            <?php if ( isset($result['error']) ): ?>
            <div class="notice notice-error"><p><?php echo esc_html($result['error']); ?></p></div>
            <?php endif; ?>

            <?php if ( isset($result['success']) ): ?>
            <div class="psy-success-box">
                <h2>✅ <?php echo count($result['quizzes']) > 1 ? 'لینک‌های اختصاصی ساخته شد' : 'لینک اختصاصی ساخته شد'; ?></h2>
                <p>مراجع: <strong><?php echo esc_html($result['client_name']); ?></strong></p>
                <?php if ( ! empty( $result['notify_msg'] ) ): ?>
                <p class="psy-text-soft">📩 <?php echo esc_html( $result['notify_msg'] ); ?></p>
                <?php endif; ?>
                <?php foreach ( $result['quizzes'] as $i => $q ): ?>
                <div class="psy-link-row">
                    <p class="psy-mb-6"><strong><?php echo esc_html($q['quiz_name']); ?></strong><?php if ( ! empty($q['expires_msg']) ): ?> — <span class="psy-text-amber">⏰ <?php echo esc_html($q['expires_msg']); ?></span><?php endif; ?></p>
                    <input type="text" readonly value="<?php echo esc_url($q['link']); ?>" id="new-link-<?php echo (int) $i; ?>" class="psy-link-input-big">
                    <button class="button button-primary" onclick="psyCopy('new-link-<?php echo (int) $i; ?>', this)">📋 کپی لینک</button>
                </div>
                <?php endforeach; ?>
                <div class="psy-link-row">
                    <a href="<?php echo esc_url($result['whatsapp']); ?>" target="_blank" class="button psy-wa-btn-big">💬 ارسال همه با واتساپ</a>
                </div>
                <hr>
                <p><a href="<?php echo admin_url('admin.php?page=psy-quiz'); ?>" class="button">← بازگشت به لیست مراجعین</a>
                <a href="<?php echo admin_url('admin.php?page=psy-quiz-new'); ?>" class="button button-primary">+ مراجع بعدی</a></p>
            </div>
            <?php endif; ?>

            <div class="psy-form-card">
                <form method="post">
                    <?php wp_nonce_field('psy_new_client', 'psy_new_client_nonce'); ?>
                    <table class="form-table">
                        <tr>
                            <th><label for="client_name">نام مراجع <span class="psy-required">*</span></label></th>
                            <td><input type="text" name="client_name" id="client_name" class="regular-text" required placeholder="مثال: علی احمدی" autofocus></td>
                        </tr>
                        <tr>
                            <th><label for="client_phone">شماره موبایل <span class="psy-required">*</span></label></th>
                            <td>
                                <input type="text" name="client_phone" id="client_phone" class="regular-text" required placeholder="09xxxxxxxxx یا +xxxxxxxxxxx (00 هم قابل قبول است)" maxlength="25" pattern="(09[0-9]{9})|(\+[1-9][0-9]{7,14})|(00[1-9][0-9]{7,14})" dir="ltr" oninput="this.value=this.value.replace(/[\s\-()]+/g,'');">
                                <p class="description">برای ارسال مستقیم واتساپ، نمایش خودکار آزمون در پنل مراجع، و شناسایی سیستمی مراجع لازم است (حتی اگر مراجع خارج از کشور باشد و پیامک واقعی برایش ارسال نشود).</p>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="client_email">ایمیل مراجع (اختیاری)</label></th>
                            <td>
                                <input type="email" name="client_email" id="client_email" class="regular-text" placeholder="client@example.com" dir="ltr">
                                <p class="description">فقط برای مراجعینی که شماره‌ی ایرانی برای دریافت پیامک ندارند (مثلاً مراجع خارج از کشور) پر کنید — با ثبت، به‌جای پیامک، اطلاع‌رسانیِ «آزمون آماده است» به همین ایمیل ارسال می‌شود و می‌توانند در پنل با کد ایمیلی به‌جای پیامکی وارد شوند.</p>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="quiz_id">آزمون <span class="psy-required">*</span></label></th>
                            <td>
                                <?php
                                $popular_tests  = \PsychAssessments\Core\Registry::popular();
                                $all_by_cat     = \PsychAssessments\Core\Registry::by_category();
                                ?>
                                <!-- منوی آبشاری پرکاربرد -->
                                <div class="psy-mb-10">
                                    <label class="psy-field-label-sm">⭐ آزمون‌های پرکاربرد</label>
                                    <div class="psy-quick-pick-group">
                                        <?php foreach ( $popular_tests as $pid => $pt ): ?>
                                        <button type="button"
                                            class="psy-quick-pick button button-secondary psy-pill-input-sm"
                                            data-value="<?php echo esc_attr($pid); ?>">
                                            <?php echo esc_html($pt->get_name()); ?>
                                        </button>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <!-- جستجوی آزمون -->
                                <div class="psy-mb-6">
                                    <input type="text" id="quiz_id_search" class="regular-text psy-min-w-320" placeholder="🔎 جستجوی نام آزمون…" autocomplete="off">
                                </div>
                                <!-- منوی گروه‌بندی‌شده کامل -->
                                <select name="quiz_id[]" id="quiz_id" required multiple class="psy-min-w-320" size="8">
                                    <?php foreach ( $all_by_cat as $cat => $cat_tests ):
                                        $cat_label = \PsychAssessments\Core\Registry::category_label($cat);
                                    ?>
                                    <optgroup label="<?php echo esc_attr($cat_label); ?>">
                                        <?php foreach ( $cat_tests as $t ): ?>
                                        <option value="<?php echo esc_attr($t->get_id()); ?>" data-search="<?php echo esc_attr(function_exists('mb_strtolower') ? mb_strtolower($t->get_name()) : strtolower($t->get_name())); ?>"><?php echo esc_html($t->get_name()); ?></option>
                                        <?php endforeach; ?>
                                    </optgroup>
                                    <?php endforeach; ?>
                                </select>
                                <p class="description psy-mt-6" id="quiz_id_no_results" hidden>هیچ آزمونی با این عبارت پیدا نشد.</p>
                                <p class="description psy-mt-6">می‌توانید چند آزمون هم‌زمان انتخاب کنید: روی دکمه‌های پرکاربرد کلیک کنید (هرکدام اضافه/حذف می‌شود)، یا در لیست پایین با نگه‌داشتن Ctrl (یا Cmd روی مک) چند گزینه را انتخاب کنید.</p>
                            </td>
                        </tr>
                    </table>
                    <p class="submit">
                        <button type="submit" class="button button-primary button-hero">🔗 ساخت لینک اختصاصی</button>
                    </p>
                </form>
            </div>
        </div>
        <?php
    }

    // ── گزارش‌ها ────────────────────────────────────────────────────────

    private static function stat_card( string $label, int $val, string $color ): void {
        $esc_color = esc_attr( $color );
        $esc_label = esc_html( $label );
        echo "<div class='psy-stat-card' style='border-top-color:{$esc_color}'>
            <div class='psy-stat-val' style='color:{$esc_color}'>{$val}</div>
            <div class='psy-stat-lbl'>{$esc_label}</div>
        </div>";
    }

}
// منطق مقایسه زوجین به class-psy-page-couples.php منتقل شده است.
