<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Psy_Quiz_Invitation_Manager {

    /**
     * مدت انقضای پیش‌فرض لینک (روز) — از Settings خوانده می‌شود
     */
    public static function default_expiry_days(): int {
        // BUG-04: max(0,...) به جای max(1,...) — مقدار ۰ یعنی بدون انقضا
        return max( 0, (int) get_option( 'psy_quiz_link_expiry_days', 30 ) );
    }

    /**
     * ساخت دعوتنامه جدید و لینک اختصاصی مراجع
     *
     * @param int|null $expiry_days  null = از تنظیمات سیستم | 0 = بدون انقضا
     */
    public static function create( string $quiz_id, string $client_name, string $client_phone = '', ?int $expiry_days = null, string $client_email = '' ): array {
        global $wpdb;

        $token = wp_generate_password( 40, false, false );

        // محاسبه زمان انقضا
        if ( $expiry_days === null ) {
            $expiry_days = self::default_expiry_days();
        }
        $expires_at = ( $expiry_days > 0 )
            ? gmdate( 'Y-m-d H:i:s', time() + $expiry_days * DAY_IN_SECONDS )
            : null;

        // BUG-22 (پیدا شده هنگام یکپارچه‌سازی با هسته مشترک): قبلاً اینجا
        // فقط sanitize_text_field می‌شد، نه to_local_format — یعنی اگر
        // درمانگر شماره را با فاصله/+۹۸ وارد می‌کرد، مقدار ذخیره‌شده با
        // فرمتی که get_pending_for_phone/get_known_name_for_phone موقع
        // جستجو انتظار دارند یکی نبود و تطبیق ساکت fail می‌شد.
        $wpdb->insert( $wpdb->prefix . 'psy_invitations', [
            'quiz_id'      => sanitize_key( $quiz_id ),
            'client_name'  => sanitize_text_field( $client_name ),
            'client_phone' => $client_phone !== '' ? self::to_local_format( $client_phone ) : '',
            'client_email' => $client_email !== '' ? sanitize_email( $client_email ) : '',
            'access_token' => $token,
            'status'       => 'unused',
            'expires_at'   => $expires_at,
            // مقدار صریح به‌جای تکیه بر DEFAULT CURRENT_TIMESTAMP دیتابیس: آن
            // مقدار از تایم‌زون سرور MySQL می‌آید (که لزوماً UTC یا تایم‌زون
            // وردپرس نیست)، در حالی که pa_to_jalali() هنگام نمایش، created_at
            // را با فرض تایم‌زون وردپرس (wp_timezone_string) تفسیر می‌کند. اگر
            // این دو با هم فرق داشته باشند، تاریخ/ساعت «ثبت دعوت‌نامه» در
            // پیشخوان می‌تواند چند ساعت جابه‌جا نمایش داده شود (و نزدیک نیمه‌شب
            // حتی روز اشتباه). current_time('mysql') همان مبنایی است که
            // psy_logs هم استفاده می‌کند.
            'created_at'   => current_time( 'mysql' ),
        ] );

        $link = home_url( '/?psy_exam=1&token=' . $token );

        return [
            'id'         => $wpdb->insert_id,
            'token'      => $token,
            'link'       => $link,
            'expires_at' => $expires_at,
        ];
    }

    /**
     * اعتبارسنجی توکن ورودی
     * فقط توکن‌های unused و منقضی‌نشده معتبرند
     */
    public static function validate( string $token ): ?object {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}psy_invitations
             WHERE access_token = %s
               AND status = 'unused'
               AND ( expires_at IS NULL OR expires_at > %s )",
            sanitize_text_field( $token ),
            gmdate( 'Y-m-d H:i:s' )
        ) );
    }

    /**
     * آخرین نامی که برای این شماره موبایل در دعوت‌نامه‌ها ثبت شده (صرف‌نظر
     * از وضعیت دعوت‌نامه — حتی تکمیل‌شده/منقضی هم مهم است چون درمانگر قبلاً
     * نام واقعی مراجع را موقع تعریف آزمون ثبت کرده). برای فیلتر
     * psy_get_client_name_for_phone استفاده می‌شود (نگاه کنید به
     * psy-quiz-enterprise.php).
     */
    public static function get_known_name_for_phone( string $phone ): string {
        global $wpdb;

        $local = self::to_local_format( $phone );
        if ( $local === '' ) {
            return '';
        }

        $name = $wpdb->get_var( $wpdb->prepare(
            "SELECT client_name FROM {$wpdb->prefix}psy_invitations
             WHERE client_phone = %s AND client_name != ''
             ORDER BY id DESC LIMIT 1",
            $local
        ) );

        return $name ? (string) $name : '';
    }

    /**
     * نتایج تکمیل‌شده‌ی این شماره — برای فیلتر
     * psy_get_completed_results_for_phone (مصرف‌کننده: افزونه‌ی پرونده
     * مراجعین). به‌جای بازسازی/تفسیر ساختار داخلی result_data (که هر آزمون
     * شکل خودش را دارد)، فقط به همان صفحه‌ی گزارش موجود در پیشخوان لینک
     * می‌دهیم — همان صفحه‌ای که send_report_email هم استفاده می‌کند.
     *
     * @return array<int, array{label:string, url:string, completed_at:string}>
     */
    public static function get_completed_results_for_phone( string $phone ): array {
        global $wpdb;

        $local = self::to_local_format( $phone );
        if ( $local === '' ) {
            return array();
        }

        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT i.quiz_id, a.id AS attempt_id, a.completed_at
             FROM {$wpdb->prefix}psy_invitations i
             INNER JOIN {$wpdb->prefix}psy_attempts a ON a.id = i.attempt_id
             WHERE i.client_phone = %s AND i.status = 'completed'
             ORDER BY a.completed_at DESC",
            $local
        ) );

        $out = array();
        foreach ( $rows as $row ) {
            $test = \PsychAssessments\Core\Registry::get( (string) $row->quiz_id );
            $name = $test ? $test->get_name() : $row->quiz_id;

            $out[] = array(
                'label'        => $name . ' — ' . $row->completed_at,
                'url'          => admin_url( 'admin.php?page=psy-quiz-reports&attempt=' . (int) $row->attempt_id ),
                'completed_at' => (string) $row->completed_at,
            );
        }

        return $out;
    }

    /**
     * نمرات آزمون‌های تکمیل‌شده‌ی این شماره برای "روند درمان" — قرارداد
     * psy_get_assessment_scores_for_phone (مصرف‌کننده: افزونه‌ی پرونده).
     *
     * بر خلاف get_completed_results_for_phone که فقط لینک به گزارش می‌دهد،
     * این‌جا نمره‌ی کل هم استخراج می‌شود — ولی همچنان فقط از جدول‌های خودِ
     * همین افزونه و با همان رمزگشایی استاندارد (pa_decrypt_json)، نه
     * خواندن خام ciphertext.
     *
     * @return array<int, array{quiz_id:string,label:string,total:float|null,completed_at:string,url:string,attempt_id:int}>
     */
    public static function get_assessment_scores_for_phone( string $phone ): array {
        global $wpdb;

        $local = self::to_local_format( $phone );
        if ( $local === '' ) {
            return array();
        }

        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT i.quiz_id, a.id AS attempt_id, a.result_data, a.completed_at
             FROM {$wpdb->prefix}psy_invitations i
             INNER JOIN {$wpdb->prefix}psy_attempts a ON a.id = i.attempt_id
             WHERE i.client_phone = %s AND i.status = 'completed'
             ORDER BY a.completed_at ASC",
            $local
        ) );

        $out = array();
        foreach ( $rows as $row ) {
            $test = \PsychAssessments\Core\Registry::get( (string) $row->quiz_id );
            $name = $test ? $test->get_name() : (string) $row->quiz_id;

            $data = function_exists( 'pa_decrypt_json' )
                ? pa_decrypt_json( (string) $row->result_data )
                : json_decode( (string) $row->result_data, true );

            $score = null;
            if ( is_array( $data ) ) {
                // آزمون‌های مختلف کلید متفاوتی برای نمره‌ی کل دارند؛ به ترتیب
                // اولین کلید عددی شناخته‌شده برداشته می‌شود.
                foreach ( array( 'total', 'score', 'percentage', 'percent' ) as $key ) {
                    if ( isset( $data[ $key ] ) && is_numeric( $data[ $key ] ) ) {
                        $score = (float) $data[ $key ];
                        break;
                    }
                }
            }

            $out[] = array(
                'quiz_id'      => (string) $row->quiz_id,
                'label'        => $name,
                'total'        => $score,
                'completed_at' => (string) $row->completed_at,
                'url'          => admin_url( 'admin.php?page=psy-quiz-reports&attempt=' . (int) $row->attempt_id ),
                'attempt_id'   => (int) $row->attempt_id,
            );
        }

        return $out;
    }

    /**
     * شماره موبایلِ متصل به یک ایمیل — آخرین دعوت‌نامه‌ای که برای این ایمیل
     * ثبت شده (صرف‌نظر از وضعیت). مصرف‌کننده: فیلتر psy_get_mobile_for_email
     * (نگاه کنید به pivand-amn-quiz-enterprise.php) — پنل مراجع OTP وقتی
     * مراجع بخواهد با ایمیل به‌جای پیامک وارد شود، از این فیلتر استفاده
     * می‌کند تا با اطمینان (چون ایمیل را قبلاً خودِ درمانگر ثبت کرده، نه
     * چیزی که مراجع لحظه‌ی ورود آزادانه ادعا کند) شماره‌ی واقعی را پیدا کند.
     */
    public static function get_mobile_for_email( string $email ): string {
        global $wpdb;

        $email = sanitize_email( $email );
        if ( $email === '' || ! is_email( $email ) ) {
            return '';
        }

        $phone = $wpdb->get_var( $wpdb->prepare(
            "SELECT client_phone FROM {$wpdb->prefix}psy_invitations
             WHERE client_email = %s AND client_phone != ''
             ORDER BY id DESC LIMIT 1",
            $email
        ) );

        return $phone ? (string) $phone : '';
    }

    /**
     * رزرو atomic توکن — جلوگیری از race condition
     * با یک UPDATE شرطی، توکن را از 'unused' به 'processing' تغییر می‌دهد
     * اگر دو request همزمان بیایند، فقط یکی موفق می‌شود
     */
    public static function claim( int $invitation_id ): bool {
        global $wpdb;
        $rows = $wpdb->query( $wpdb->prepare(
            "UPDATE {$wpdb->prefix}psy_invitations SET status = 'processing', started_at = %s
             WHERE id = %d AND status = 'unused'
               AND ( expires_at IS NULL OR expires_at > %s )",
            gmdate( 'Y-m-d H:i:s' ),
            $invitation_id,
            gmdate( 'Y-m-d H:i:s' )
        ) );
        return $rows === 1;
    }

    /**
     * ثبت نهایی attempt_id پس از درج موفق
     */
    public static function complete( int $invitation_id, int $attempt_id ): void {
        global $wpdb;
        $wpdb->update(
            $wpdb->prefix . 'psy_invitations',
            [ 'status' => 'completed', 'attempt_id' => $attempt_id ],
            [ 'id'     => $invitation_id ],
            [ '%s', '%d' ],
            [ '%d' ]
        );
    }

    /**
     * اطلاع‌رسانیِ خودکارِ «آزمون آماده است» به مراجع، بلافاصله پس از
     * ساخت دعوت‌نامه (نگاه کنید به Psy_Page_Clients::handle_new_client).
     *
     * $client_phone همیشه پر است (در handle_new_client الزامی شده — نگاه
     * کنید به توضیح آنجا درباره‌ی وابستگیِ get_mobile_for_email به وجود
     * client_phone). آنچه واقعاً کانال ارسال را تعیین می‌کند، وجود یا عدم
     * وجود $client_email است:
     * - $client_email خالی است (مراجع داخل کشور، حالت پیش‌فرض) → فقط پیامک.
     * - $client_email پر است (یعنی درمانگر صراحتاً گفته این مراجع نمی‌تواند
     *   پیامک ایرانی دریافت کند — مثلاً مراجع خارج از کشور) → فقط ایمیل؛
     *   عمداً پیامکی هم به آن شماره فرستاده نمی‌شود، چون خودِ وجود ایمیل در
     *   این فرم دقیقاً یعنی «این شماره پیامک را دریافت نمی‌کند» (نگاه کنید
     *   به توضیح فیلد ایمیل در Psy_Page_Clients::render_new_client).
     *
     * پیام حاوی *لینک اختصاصی آزمون* نیست — عمداً مراجع را به ورود از
     * طریق پنل (با کد یک‌بارمصرف پیامکی/ایمیلی) هدایت می‌کند، چون لینک
     * اختصاصی توکن‌دار می‌تواند بدون احراز هویت توسط هرکسی که آن را ببیند
     * استفاده شود؛ همان لینک مستقیم هنوز از طریق دکمه‌ی «ارسال واتساپ» در
     * پیشخوان، به تشخیص و مسئولیت درمانگر، در دسترس است.
     *
     * برخلاف خودِ Invitation_Manager::create، این متد به‌عمد چیزی throw
     * نمی‌کند و خطای ارسال را فقط لاگ می‌کند (نه بیشتر) — چون این یک
     * اطلاع‌رسانیِ جانبی است، نه بخشی از ثبت دعوت‌نامه؛ شکست ارسال پیامک/ایمیل
     * نباید باعث شود درمانگر لینک آزمونی را که تازه ساخته شده از دست بدهد
     * (همان الگوی notify_client_booking_confirmed در client-portal).
     *
     * محدودیت «حداکثر یک اطلاع‌رسانی در روز»: چون لینک همیشه ثابت (پنل
     * مراجعین) است نه لینک اختصاصی هر آزمون، اگر برای یک مراجع چند آزمون
     * پشت‌سرهم در همان روز ثبت شود، فرستادن چند پیامک/ایمیل یکسان («آزمونی
     * که برایتان تعریف شده آماده است») صرفاً مزاحم مراجع است — همه‌ی
     * آزمون‌های در انتظار او با ورود به همان یک پنل قابل مشاهده‌اند. پس
     * دومین و باقی درخواست‌های همان روز، برای همان شماره، سایلنت رد می‌شوند
     * (status هر دو کانال null و 'throttled' => true برمی‌گردد) — نه اینکه
     * failed تلقی شوند. مرز «روز» بر اساس تنظیم منطقه‌زمانی خود سایت
     * (current_time) است، نه UTC، تا با انتظار ادمین (که به‌وقت ایران فکر
     * می‌کند) یکی باشد.
     *
     * @param bool $bypass_throttle اگر true باشد، محدودیت «یک بار در روز»
     *        نادیده گرفته می‌شود — فقط برای ارسال دستیِ عمدی توسط ادمین
     *        (دکمه‌ی «ارسال اجباری» در لیست مراجعین)، نه برای مسیر خودکار.
     * @return array{sms: ?bool, email: ?bool, throttled: bool} برای هر
     *         کانال: true = ارسال موفق، false = تلاش شد ولی ناموفق بود،
     *         null = اصلاً تلاش نشد (چون آن کانال بر اساس قاعده‌ی بالا
     *         انتخاب نشده بود، یا چون throttled شد). خروجی برای نمایش پیام
     *         دقیق (نه صرفاً خوش‌بینانه‌ی «ارسال شد») به تماس‌گیرنده
     *         برمی‌گردد — نگاه کنید به Psy_Page_Clients::handle_new_client.
     */
    public static function notify_client( string $client_name, string $client_phone, string $client_email, bool $bypass_throttle = false ): array {
        $status = [ 'sms' => null, 'email' => null, 'throttled' => false ];

        // شناسه‌ی throttle: چون شماره موبایل در فرم اجباری است (برخلاف
        // ایمیل که اختیاری است)، همیشه به‌عنوان کلید یکتای «این مراجع»
        // استفاده می‌شود — صرف‌نظر از این‌که پیام از کدام کانال می‌رود.
        $throttle_key = $client_phone !== ''
            ? 'psy_quiz_notif_' . md5( $client_phone ) . '_' . current_time( 'Ymd' )
            : '';

        $once_per_day = (bool) apply_filters( 'psy_quiz_ready_notify_once_per_day', true );

        if ( ! $bypass_throttle && $once_per_day && $throttle_key !== '' && get_transient( $throttle_key ) ) {
            $status['throttled'] = true;
            psy_log( 'info', 'invitation', 'quiz-ready notification throttled (already sent today)', [ 'phone' => $client_phone ] );
            return $status;
        }

        $panel_url = apply_filters( 'zdp_get_client_panel_url', home_url( '/panel/' ) );
        $use_email = ( $client_email !== '' && is_email( $client_email ) );

        if ( $use_email ) {
            $site_name = trim( (string) preg_replace( '/[\r\n]+/', ' ', wp_strip_all_tags( (string) get_bloginfo( 'name' ) ) ) );
            $subject   = 'آزمون شما آماده است — ' . $site_name;
            $body      = "سلام {$client_name} عزیز\n\n"
                . "آزمونی که برایتان تعریف شده آماده است.\n"
                . "برای مشاهده و پاسخ‌دهی، لطفاً به پنل مراجعین وارد شوید:\n"
                . $panel_url . "\n\n"
                . "برای ورود، کد یک‌بارمصرف به همین ایمیل ارسال می‌شود.\n\n"
                . "در صورت هرگونه سؤال در خدمتیم.";
            $status['email'] = wp_mail( $client_email, $subject, $body );
            if ( ! $status['email'] ) {
                psy_log( 'warning', 'invitation', 'quiz-ready email not sent', [ 'email' => $client_email ] );
            }
        } elseif ( $client_phone !== '' ) {
            $body_id = (string) get_option( 'psy_sms_quiz_ready_body_id', '' );
            // مثل notify_client_booking_confirmed در client-portal: اگر هنوز
            // پترنی تنظیم نشده، اصلاً فراخوانی نمی‌کنیم (نه این‌که به‌امید
            // no-op بودن handler، فراخوانی خالی بفرستیم).
            if ( $body_id !== '' ) {
                $status['sms'] = (bool) apply_filters( 'pas_send_sms', null, $client_phone, $body_id, [
                    'NAME' => $client_name,
                    'LINK' => $panel_url,
                ] );
                if ( ! $status['sms'] ) {
                    psy_log( 'warning', 'invitation', 'quiz-ready SMS not sent', [ 'phone' => $client_phone ] );
                }
            } else {
                $status['sms'] = false;
                psy_log( 'warning', 'invitation', 'quiz-ready SMS skipped — psy_sms_quiz_ready_body_id not set' );
            }
        }

        // فقط وقتی واقعاً ارسال موفق بوده، امروز را «مصرف‌شده» علامت بزن —
        // اگر پیامک/ایمیل شکست خورده، منطقی است که تلاش بعدی (برای همان روز
        // با آزمون دوم) دوباره امتحان کند، نه این‌که با یک شکست کل روز قفل
        // شود.
        if ( $throttle_key !== '' && ( $status['sms'] === true || $status['email'] === true ) ) {
            // انقضای ۲ روزه فقط برای پاکسازی خودکار wp_options است؛ چون
            // خودِ کلید شامل تاریخ امروز است، فردا به‌طور طبیعی کلید دیگری
            // ساخته می‌شود و این transient قدیمی دیگر هرگز خوانده نمی‌شود.
            set_transient( $throttle_key, 1, 2 * DAY_IN_SECONDS );
        }

        return $status;
    }

    /**
     * آماده‌سازی لینک واتساپ
     */
    /**
     * تبدیل شماره ایرانی به فرمت بین‌المللی برای واتساپ
     * 09xxxxxxxxx  →  989xxxxxxxxx
     * +989xxxxxxxx →  989xxxxxxxxx
     */
    public static function normalize_phone( string $phone ): string {
        if ( class_exists( 'Pas_Core_Helpers' ) ) {
            return \Pas_Core_Helpers::to_whatsapp_international( $phone );
        }

        $digits = preg_replace( '/\D/', '', $phone ); // فقط عدد
        if ( strlen( $digits ) === 11 && str_starts_with( $digits, '0' ) ) {
            // 09xxxxxxxxx → 989xxxxxxxxx
            return '98' . substr( $digits, 1 );
        }
        if ( strlen( $digits ) === 12 && str_starts_with( $digits, '98' ) ) {
            // 989xxxxxxxxx — درست است
            return $digits;
        }
        if ( strlen( $digits ) === 13 && str_starts_with( $digits, '980' ) ) {
            // +9809xxxxxxxxx (نادر اما ممکن) → 989xxxxxxxxx
            // BUG-17: باید substr از ایندکس 3 باشد تا '0' حذف شود: '980...' → '98' + '9...'
            return '98' . substr( $digits, 3 );
        }
        // فرمت ناشناخته — همان را برمی‌گردانیم
        return $digits;
    }

    public static function whatsapp_url( string $client_name, string $quiz_name, string $link, string $phone = '' ): string {
        $text  = "✨ سلام {$client_name} عزیز\n\n";
        $text .= "امیدوارم حالتون خوب باشه 🌿\n\n";
        $text .= "آزمون شما آماده شده است.\n";
        $text .= "این آزمون به بهتر شناخته شدن وضعیت شما کمک می‌کند و نتایج آن کاملاً محرمانه خواهد بود.\n\n";
        $text .= "🔗 لینک اختصاصی شما:\n";
        $text .= $link . "\n\n";
        $text .= "⏱ لطفاً در فضایی آرام و بدون عجله به سؤالات پاسخ دهید.\n\n";
        $text .= "در صورت هرگونه سؤال در خدمتم 🙏";

        $base = $phone
            ? 'https://api.whatsapp.com/send?phone=' . self::normalize_phone( $phone ) . '&text='
            : 'https://api.whatsapp.com/send?text=';

        return $base . rawurlencode( $text );
    }

    /**
     * بررسی انقضای یک دعوتنامه و برگرداندن پیام مناسب
     */
    public static function expiry_message( ?string $expires_at ): string {
        if ( ! $expires_at ) return 'بدون انقضا';
        $ts   = strtotime( $expires_at . ' UTC' );
        $diff = $ts - time();
        if ( $diff < 0 ) return 'منقضی شده';
        $days = ceil( $diff / DAY_IN_SECONDS );
        return "انقضا: {$days} روز دیگر";
    }

    /**
     * دعوتنامه‌های فعال (unused و منقضی‌نشده) برای یک شماره موبایل مشخص.
     *
     * برای اتصال به پنل مراجع افزونه‌های دیگر (مثلاً پنل OTP یک افزونه دیگر)
     * از طریق فیلتر psy_get_pending_quizzes_for_phone استفاده می‌شود؛ این متد
     * هرگز مستقیماً توسط افزونه‌ی دیگر include نمی‌شود.
     *
     * @return array<int, array{invitation_id:int, quiz_id:string, quiz_name:string, link:string, expires_at:?string}>
     */
    public static function get_pending_for_phone( string $phone ): array {
        global $wpdb;

        $local = self::to_local_format( $phone );
        if ( $local === '' ) {
            return array();
        }

        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT id, quiz_id, client_name, access_token, expires_at
             FROM {$wpdb->prefix}psy_invitations
             WHERE status = 'unused'
               AND client_phone = %s
               AND ( expires_at IS NULL OR expires_at > %s )
             ORDER BY id DESC",
            $local,
            gmdate( 'Y-m-d H:i:s' )
        ) );

        $out = array();
        foreach ( $rows as $row ) {
            $test = \PsychAssessments\Core\Registry::get( $row->quiz_id );
            $out[] = array(
                'invitation_id' => (int) $row->id,
                'quiz_id'       => $row->quiz_id,
                'quiz_name'     => $test ? $test->get_name() : $row->quiz_id,
                'link'          => home_url( '/?psy_exam=1&token=' . $row->access_token ),
                'expires_at'    => $row->expires_at,
            );
        }
        return $out;
    }

    /**
     * دعوتنامه‌های فعال (unused و منقضی‌نشده) برای یک ایمیل مشخص — نسخه‌ی
     * ایمیلیِ get_pending_for_phone بالا، برای مراجعینِ خارج از کشور که
     * شماره‌ی ایرانی ندارند و درمانگر موقع تعریف آزمون فقط client_email
     * را ثبت کرده (client_phone خالی مانده — نگاه کنید به ستون client_email
     * که در PSY_DB_VERSION 3.9 اضافه شد، دقیقاً برای همین حالت).
     *
     * قبل از این متد، چنین مراجعی که با ایمیل وارد پنل می‌شد (zoojdarman-
     * client-portal → حساب مستقلِ بدون موبایل) هیچ راهی نداشت آزمونش را در
     * تب «آزمون‌ها» ببیند: psy_get_pending_quizzes_for_phone فقط بر مبنای
     * client_phone جست‌وجو می‌کرد و برای این مراجعین همیشه [] برمی‌گرداند —
     * باگ گزارش‌شده («مراجع خارج از کشور با ایمیل وارد شده که آزمون بدهد،
     * آزمون براش نبود»). برای فیلتر جدید psy_get_pending_quizzes_for_email
     * استفاده می‌شود (نگاه کنید به pivand-amn-quiz-enterprise.php).
     *
     * عمداً به client_phone == '' محدود نشده: اگر درمانگر هم موبایل هم
     * ایمیل را روی یک دعوتنامه ثبت کرده باشد، مسیر موبایل (که در کل
     * اکوسیستم کلید اصلی است) از قبل توسط get_pending_for_phone پوشش داده
     * می‌شود؛ این متد فقط یک مسیر جایگزین برای زمانی است که موبایلی در کار
     * نیست یا کاربر با همان ایمیل مستقیماً وارد شده.
     *
     * @return array<int, array{invitation_id:int, quiz_id:string, quiz_name:string, link:string, expires_at:?string}>
     */
    public static function get_pending_for_email( string $email ): array {
        global $wpdb;

        $email = sanitize_email( $email );
        if ( $email === '' || ! is_email( $email ) ) {
            return array();
        }

        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT id, quiz_id, client_name, access_token, expires_at
             FROM {$wpdb->prefix}psy_invitations
             WHERE status = 'unused'
               AND client_email = %s
               AND ( expires_at IS NULL OR expires_at > %s )
             ORDER BY id DESC",
            $email,
            gmdate( 'Y-m-d H:i:s' )
        ) );

        $out = array();
        foreach ( $rows as $row ) {
            $test = \PsychAssessments\Core\Registry::get( $row->quiz_id );
            $out[] = array(
                'invitation_id' => (int) $row->id,
                'quiz_id'       => $row->quiz_id,
                'quiz_name'     => $test ? $test->get_name() : $row->quiz_id,
                'link'          => home_url( '/?psy_exam=1&token=' . $row->access_token ),
                'expires_at'    => $row->expires_at,
            );
        }
        return $out;
    }

    /**
     * تبدیل هر فرمت شماره موبایل ایرانی به فرمت لوکال 09xxxxxxxxx
     * (همان فرمتی که فیلد client_phone در فرم «مراجع جدید» با آن پر می‌شود
     * و همان فرمتی که به‌عنوان user_login در افزونه‌های ورود OTP دیگر ذخیره می‌شود).
     */
    public static function to_local_format( string $phone ): string {
        // شماره‌ی صریحاً خارجی (با + یا 00 و کد کشور غیر از ۹۸) همیشه با
        // منطق محلی همین پلاگین پردازش می‌شود — مستقل از اینکه
        // Pas_Core_Helpers روی سایت وجود دارد یا نه.
        //
        // چرا: Pas_Core_Helpers متعلق به یک پلاگین دیگرِ اکوسیستم است که
        // از قبل از افزوده‌شدنِ پشتیبانی شماره‌ی خارجی به این پلاگین وجود
        // داشته و ظاهراً فقط فرمت‌های ایرانی را می‌شناسد. تا این نسخه، اگر
        // آن کلاس روی سایت موجود بود (حالت رایج، چون اکثر نصب‌های این
        // پلاگین کنار بقیه‌ی اکوسیستم پیوند امن هستند)، شماره‌ی خارجی کلاً
        // به آن سپرده می‌شد و به‌جای فرمتِ E.164 مورد انتظار، خروجی
        // نامعتبر/خالی برمی‌گشت — دقیقاً باگِ «شماره‌ی خارج از کشور رد
        // می‌شود». برای شماره‌های ایرانی همچنان به Pas_Core_Helpers سپرده
        // می‌شود (بدون تغییر) تا سازگاری با بقیه‌ی اکوسیستم حفظ شود.
        $intl_local = self::to_local_format_intl_only( $phone );
        if ( $intl_local !== null ) {
            return $intl_local;
        }

        if ( class_exists( 'Pas_Core_Helpers' ) ) {
            return \Pas_Core_Helpers::normalize_mobile( $phone );
        }

        $trimmed  = trim( $phone );
        $has_plus = str_starts_with( $trimmed, '+' );
        $digits   = preg_replace( '/\D/', '', $phone ) ?? '';

        // مراجع خارج از کشور: ورودی باید صراحتاً با «+» یا «00» شروع شده
        // باشد (همان قرارداد پلاگین پنل مراجعین/to_local_format در
        // functions-helpers.php). کد کشور 98 صریح همچنان به فرمت محلی
        // 09xxxxxxxxx تبدیل می‌شود؛ هر کد کشور دیگری به فرمت E.164
        // (+<کدکشور><شماره>) نگه داشته می‌شود، چون client_phone اینجا دیگر
        // همیشه یک شماره‌ی ایرانی نیست.
        if ( $has_plus || str_starts_with( $digits, '00' ) ) {
            $intl_digits = $has_plus ? $digits : substr( $digits, 2 );

            if ( ! str_starts_with( $intl_digits, '98' ) ) {
                return $intl_digits !== '' ? ( '+' . $intl_digits ) : '';
            }

            $digits = $intl_digits;
        }

        if ( strlen( $digits ) === 11 && str_starts_with( $digits, '0' ) ) {
            return $digits; // already 09xxxxxxxxx
        }
        if ( strlen( $digits ) === 12 && str_starts_with( $digits, '98' ) ) {
            return '0' . substr( $digits, 2 ); // 989xxxxxxxxx -> 09xxxxxxxxx
        }
        if ( strlen( $digits ) === 10 && str_starts_with( $digits, '9' ) ) {
            return '0' . $digits; // 9xxxxxxxxx -> 09xxxxxxxxx
        }
        if ( strlen( $digits ) === 13 && str_starts_with( $digits, '980' ) ) {
            return '0' . substr( $digits, 3 ); // +9809xxxxxxxxx -> 09xxxxxxxxx (BUG-17 pattern)
        }
        return $digits;
    }

    /**
     * اگر ورودی صراحتاً یک شماره‌ی خارجی باشد (با «+» یا «00» شروع شده و
     * کد کشورش ۹۸ نیست)، فرمت E.164 آن (+<کدکشور><شماره>) را برمی‌گرداند.
     * در غیر این صورت (شماره‌ی ایرانی یا مبهم/بدون پیشوند صریح) null
     * برمی‌گرداند تا caller (to_local_format) طبق روال قبلی‌اش تصمیم بگیرد
     * (شامل تفویض احتمالی به Pas_Core_Helpers برای حفظ سازگاری اکوسیستم).
     *
     * این تابع مستقل از Pas_Core_Helpers است — چون هدفش دقیقاً پوشاندنِ
     * جایی است که آن کلاس (در صورت وجود) شماره‌ی خارجی را نمی‌شناسد.
     */
    private static function to_local_format_intl_only( string $phone ): ?string {
        $trimmed  = trim( $phone );
        $has_plus = str_starts_with( $trimmed, '+' );
        $digits   = preg_replace( '/\D/', '', $phone ) ?? '';

        if ( ! $has_plus && ! str_starts_with( $digits, '00' ) ) {
            return null;
        }

        $intl_digits = $has_plus ? $digits : substr( $digits, 2 );

        if ( $intl_digits === '' || str_starts_with( $intl_digits, '98' ) ) {
            // کد کشور ۹۸ صریح (یا ورودی خالی بعد از پیشوند) شماره‌ی ایرانی
            // است، نه خارجی — به مسیر قبلی (که آن را به 09xxxxxxxxx تبدیل
            // می‌کند) واگذار می‌شود.
            return null;
        }

        return '+' . $intl_digits;
    }

    /**
     * اعتبارسنجی شماره: خروجی to_local_format() یعنی «شماره‌ی ایرانی محلی
     * (09xxxxxxxxx)» یا «شماره‌ی خارجی به فرمت E.164 (+<کدکشور><شماره>، ۸ تا
     * ۱۵ رقم)» معتبر است.
     *
     * ورودی اول با همان to_local_format نرمال می‌شود (که برای شماره‌های
     * ایرانی به Pas_Core_Helpers::normalize_mobile سپرده می‌شود)؛ وگرنه
     * فراخوان مستقیم با ارقام فارسی/عربی — حتی با کد درست — «شماره معتبر
     * نیست» می‌گرفت. برای شماره‌ی از قبل نرمال‌شده idempotent است.
     */
    public static function is_valid_mobile( string $phone ): bool {
        $local = self::to_local_format( $phone );

        if ( preg_match( '/^09[0-9]{9}$/', $local ) ) {
            return true;
        }

        return (bool) preg_match( '/^\+[1-9][0-9]{7,14}$/', $local );
    }
}
