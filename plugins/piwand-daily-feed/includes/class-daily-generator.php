<?php
defined( 'ABSPATH' ) || exit;

/**
 * PWDF_Daily_Generator
 *
 * هر روز (در ساعت تنظیم‌شده) یک کارت جدید — «متن/جمله کوتاه» یا «معرفی کتاب» —
 * با کمک هوش مصنوعی (از طریق افزونه «پیوند امن — تولید محتوا») می‌سازد.
 *
 * نکات امنیتی/صداقت محتوا (عمدی، نه فراموش‌شده):
 *  ۱. کارت تولیدشده همیشه با وضعیت «پیش‌نویس» (draft) ساخته می‌شود — این مقدار
 *     هیچ‌جا از تنظیمات یا ورودی کاربر خوانده نمی‌شود، در کد ثابت است. یعنی
 *     حتی اگر کسی تنظیمات را دستکاری کند، امکان دور زدن تایید ادمین نیست.
 *  ۲. برای «متن/جمله»، هوش مصنوعی صراحتاً دستور دارد آن را به هیچ شخص واقعی
 *     نسبت ندهد (تا نقل‌قول جعلی از یک روان‌شناس/نویسنده واقعی ساخته نشود).
 *  ۳. برای «معرفی کتاب»، عنوان و نویسنده از یک لیست از‌قبل‌تایید‌شده توسط
 *     ادمین خوانده می‌شود؛ هوش مصنوعی فقط توضیح تبلیغاتی را بر اساس یادداشت
 *     ادمین بازنویسی می‌کند، نه اینکه خودش کتاب/نویسنده اختراع کند.
 *  ۴. در هر صورت، پیش از انتشار روی سایت، ادمین از پیشخوان ← فید محتوای
 *     روزانه ← همه کارت‌ها، کارت را می‌بیند، در صورت نیاز ویرایش می‌کند و
 *     خودش دستی روی «انتشار» می‌زند.
 */
class PWDF_Daily_Generator {

    const CRON_HOOK = 'pwdf_daily_generate_event';

    public static function init() {
        add_action( 'admin_init', [ __CLASS__, 'maybe_reschedule' ] );
        add_action( self::CRON_HOOK, [ __CLASS__, 'cron_handler' ] );
        add_action( 'admin_post_pwdf_generate_now', [ __CLASS__, 'handle_generate_now' ] );
        add_action( 'admin_notices', [ __CLASS__, 'admin_notices' ] );
    }

    // ─────────────────────────────────────────────────────────────
    // زمان‌بندی cron — بر اساس ساعت انتخاب‌شده در تنظیمات
    // ─────────────────────────────────────────────────────────────
    public static function maybe_reschedule() {
        $s       = PWDF_Settings::get();
        $enabled = ! empty( $s['auto_generate_enabled'] );
        $hour    = (int) $s['auto_generate_hour'];
        $next    = wp_next_scheduled( self::CRON_HOOK );

        if ( ! $enabled ) {
            if ( $next ) {
                wp_unschedule_event( $next, self::CRON_HOOK );
            }
            delete_option( 'pwdf_auto_generate_scheduled_hour' );
            return;
        }

        $stored_hour = get_option( 'pwdf_auto_generate_scheduled_hour', null );
        if ( $next && (int) $stored_hour === $hour ) {
            return; // از قبل درست زمان‌بندی شده — کاری لازم نیست
        }

        if ( $next ) {
            wp_unschedule_event( $next, self::CRON_HOOK );
        }

        $tz    = wp_timezone();
        $now   = new DateTime( 'now', $tz );
        $first = new DateTime( 'now', $tz );
        $first->setTime( $hour, 0, 0 );
        if ( $first <= $now ) {
            $first->modify( '+1 day' );
        }

        wp_schedule_event( $first->getTimestamp(), 'daily', self::CRON_HOOK );
        update_option( 'pwdf_auto_generate_scheduled_hour', $hour );
    }

    public static function cron_handler(): void {
        $s = PWDF_Settings::get();
        if ( empty( $s['auto_generate_enabled'] ) ) {
            return;
        }
        self::generate_and_save();
    }

    // ─────────────────────────────────────────────────────────────
    // دکمه «همین حالا ۵ گزینه پیش‌نویس بساز» در تنظیمات
    // ─────────────────────────────────────────────────────────────
    public static function handle_generate_now(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'دسترسی مجاز نیست.' );
        }
        check_admin_referer( 'pwdf_generate_now' );

        $result = self::generate_and_save();

        $redirect = admin_url( 'edit.php?post_type=pwdf_card&page=pwdf-settings' );
        if ( $result['success'] ) {
            $redirect = add_query_arg( [ 'pwdf_generated' => count( $result['post_ids'] ) ], $redirect );
        } else {
            $redirect = add_query_arg( [ 'pwdf_generate_error' => rawurlencode( $result['error'] ?? 'خطای نامشخص' ) ], $redirect );
        }
        wp_safe_redirect( $redirect );
        exit;
    }

    public static function admin_notices(): void {
        if ( ! empty( $_GET['pwdf_generated'] ) ) {
            $n = absint( wp_unslash( $_GET['pwdf_generated'] ) );
            printf(
                '<div class="notice notice-success is-dismissible"><p>✅ %d گزینه‌ی پیش‌نویس ساخته شد — از «همه کارت‌ها» بهترین را انتخاب، در صورت نیاز ویرایش، و منتشر کن. بقیه را می‌توانی نگه‌داری یا حذف کنی.</p></div>',
                esc_html( $n )
            );
        }
        if ( ! empty( $_GET['pwdf_generate_error'] ) ) {
            $err = sanitize_text_field( wp_unslash( $_GET['pwdf_generate_error'] ) );
            echo '<div class="notice notice-error is-dismissible"><p>❌ تولید کارت ناموفق بود: ' . esc_html( $err ) . '</p></div>';
        }
    }

    // ─────────────────────────────────────────────────────────────
    // هسته اصلی: هر بار ۵ کارت پیش‌نویس می‌سازد تا ادمین از بینشان
    // بهترین را انتخاب و منتشر کند (بقیه پیش‌نویس می‌مانند/قابل حذف‌اند)
    // ─────────────────────────────────────────────────────────────
    const BATCH_SIZE = 5;

    // موضوعات متنوع برای اینکه ۵ متن یک روز شبیه هم از آب درنیایند
    const QUOTE_THEMES = [
        'گوش دادن فعال و همدلانه',
        'حل تعارض بدون سرزنش',
        'ابراز محبت در کارهای روزمره',
        'صبر و آرامش در لحظات سخت رابطه',
        'اعتماد و صداقت بین دو نفر',
    ];

    public static function generate_and_save(): array {
        if ( ! class_exists( 'ACO_AI_Router' ) ) {
            $err = 'افزونه «پیوند امن — تولید محتوا» فعال نیست.';
            self::notify_admin_error( $err );
            return [ 'success' => false, 'error' => $err ];
        }

        $s   = PWDF_Settings::get();
        $mix = ! empty( $s['auto_generate_mix'] ) ? array_values( (array) $s['auto_generate_mix'] ) : [ 'quote' ];

        // به‌نوبت بر اساس روز سال (نه تصادفی) تا الگوی قابل پیش‌بینی و متعادل باشد
        $day_index = (int) wp_date( 'z' );
        $type      = $mix[ $day_index % count( $mix ) ];
        $batch_id  = wp_date( 'Y-m-d' ) . '-' . wp_generate_password( 5, false ); // شناسه یکتای این دسته

        $created = [];
        $errors  = [];

        for ( $i = 0; $i < self::BATCH_SIZE; $i++ ) {
            $result = ( $type === 'book' )
                ? self::generate_book( $s )
                : self::generate_quote( self::QUOTE_THEMES[ $i % count( self::QUOTE_THEMES ) ] );

            if ( empty( $result['success'] ) ) {
                $errors[] = $result['error'] ?? 'خطای نامشخص از سرویس هوش مصنوعی.';
                continue;
            }

            $post_id = wp_insert_post( [
                'post_type'    => 'pwdf_card',
                'post_status'  => 'draft', // ⚠️ عمداً ثابت — نباید از تنظیمات/ورودی خوانده شود
                'post_title'   => wp_strip_all_tags( '(گزینه ' . ( $i + 1 ) . ' از ' . self::BATCH_SIZE . ') ' . $result['title'] ),
                'post_content' => wp_kses_post( $result['content'] ),
            ], true );

            if ( is_wp_error( $post_id ) ) {
                $errors[] = $post_id->get_error_message();
                continue;
            }

            update_post_meta( $post_id, '_pwdf_type', $type );
            update_post_meta( $post_id, '_pwdf_color', PWDF_Settings::get( 'colors' )[ $type ] ?? '' );
            if ( ! empty( $result['author_tag'] ) ) {
                update_post_meta( $post_id, '_pwdf_author_tag', sanitize_text_field( $result['author_tag'] ) );
            }
            update_post_meta( $post_id, '_pwdf_generated_by_ai', '1' );
            update_post_meta( $post_id, '_pwdf_ai_batch', $batch_id ); // برای گروه‌بندی ۵تایی هر روز

            $created[] = $post_id;
        }

        if ( empty( $created ) ) {
            $err = 'هیچ‌کدام از ۵ گزینه ساخته نشد: ' . implode( ' | ', array_unique( $errors ) );
            self::notify_admin_error( $err );
            return [ 'success' => false, 'error' => $err ];
        }

        self::notify_admin_batch_ready( $created, $type, $errors );

        return [ 'success' => true, 'post_ids' => $created, 'partial_errors' => $errors ];
    }

    // ─────────────────────────────────────────────────────────────
    // تولید متن/جمله کوتاه — کاملاً اصیل، بدون نسبت‌دادن به شخص واقعی
    // ─────────────────────────────────────────────────────────────
    private static function generate_quote( string $theme = '' ): array {
        $router = new ACO_AI_Router();

        $system = 'تو دستیار نوشتن متن‌های کوتاه روان‌شناسی برای یک کلینیک زوج‌درمانی به نام «پیوند امن» '
                . 'هستی. فقط و فقط یک متن کوتاه فارسی (۱ تا ۳ جمله) درباره‌ی رابطه، ارتباط، اعتماد یا آرامش '
                . 'ذهنی بنویس. این متن باید کاملاً اصیل و از خودت باشد — هرگز آن را به یک شخص واقعی '
                . '(روان‌شناس، نویسنده، فرد مشهور) نسبت نده و هرگز آن را به‌عنوان نقل‌قول از کسی معرفی نکن. '
                . 'فقط خروجی متن نهایی را بده، بدون توضیح اضافه، بدون گیومه، بدون پیشوندی مثل «متن:».';

        $topic = $theme ?: 'گوش دادن فعال، حل تعارض بدون سرزنش، ابراز محبت روزمره، صبر در لحظات سخت رابطه، یا آرامش ذهنی';
        $user  = "یک متن روزانه‌ی تازه (نه کلیشه‌ای و تکراری) درباره‌ی این موضوع بنویس: {$topic}.";

        $res = $router->generate( $system, $user, 60, 300 );
        if ( empty( $res['success'] ) ) {
            return $res;
        }

        $text  = trim( (string) $res['text'] );
        $title = mb_substr( $text, 0, 40 ) . ( mb_strlen( $text ) > 40 ? '…' : '' );

        return [
            'success'    => true,
            'title'      => 'یادداشت روزانه — ' . $title,
            'content'    => $text,
            'author_tag' => 'یادداشت روزانه پیوند امن', // عمداً بدون نسبت‌دادن به شخص واقعی
        ];
    }

    // ─────────────────────────────────────────────────────────────
    // معرفی کتاب — عنوان/نویسنده از لیست تایید‌شده؛ فقط توضیح از AI
    // ─────────────────────────────────────────────────────────────
    private static function generate_book( array $s ): array {
        $books = self::parse_books( (string) $s['auto_generate_books'] );
        if ( empty( $books ) ) {
            return [ 'success' => false, 'error' => 'لیست کتاب‌ها در تنظیمات خالی است.' ];
        }

        // چرخشی (نه تصادفی) تا همه‌ی کتاب‌ها به‌نوبت معرفی شوند، نه فقط چندتای اول
        $idx  = (int) get_option( 'pwdf_book_rotation_index', 0 );
        $book = $books[ $idx % count( $books ) ];
        update_option( 'pwdf_book_rotation_index', $idx + 1 );

        $router = new ACO_AI_Router();

        $system = 'تو دستیار نوشتن معرفی کوتاه کتاب برای فید یک سایت روان‌شناسی هستی. فقط بر اساس عنوان، '
                . 'نویسنده و یادداشتی که کاربر می‌دهد، یک معرفی ۲ تا ۳ جمله‌ای فارسی و جذاب بنویس. هرگز '
                . 'جزییات، آمار یا نقل‌قول مستقیمی که کاربر نداده است را اختراع نکن — فقط بر پایه‌ی همان '
                . 'یادداشت بنویس. فقط خروجی نهایی، بدون گیومه و بدون توضیح اضافه.';

        $user = "عنوان: {$book['title']}\nنویسنده: {$book['author']}\nیادداشت: {$book['note']}";

        $res = $router->generate( $system, $user, 60, 300 );
        if ( empty( $res['success'] ) ) {
            return $res;
        }

        return [
            'success'    => true,
            'title'      => '📚 معرفی کتاب: ' . $book['title'],
            'content'    => trim( (string) $res['text'] ),
            'author_tag' => $book['author'],
        ];
    }

    private static function parse_books( string $raw ): array {
        $lines = array_filter( array_map( 'trim', explode( "\n", $raw ) ) );
        $books = [];
        foreach ( $lines as $line ) {
            $parts = array_map( 'trim', explode( '|', $line ) );
            if ( ! empty( $parts[0] ) ) {
                $books[] = [
                    'title'  => $parts[0],
                    'author' => $parts[1] ?? '',
                    'note'   => $parts[2] ?? '',
                ];
            }
        }
        return $books;
    }

    // ─────────────────────────────────────────────────────────────
    // اطلاع‌رسانی به ادمین (ایمیل ساده — بدون وابستگی به افزونه پیامک)
    // ─────────────────────────────────────────────────────────────
    private static function notify_admin_batch_ready( array $post_ids, string $type, array $partial_errors = [] ): void {
        $label = $type === 'book' ? 'معرفی کتاب' : 'متن/جمله روزانه';
        $lines = [ 'امروز ' . count( $post_ids ) . ' گزینه‌ی «' . $label . '» به‌صورت پیش‌نویس ساخته شد. یکی را انتخاب و منتشر کن:', '' ];
        foreach ( $post_ids as $i => $pid ) {
            $lines[] = ( $i + 1 ) . '. ' . get_edit_post_link( $pid, '' );
        }
        if ( ! empty( $partial_errors ) ) {
            $lines[] = '';
            $lines[] = 'توجه: ' . ( self::BATCH_SIZE - count( $post_ids ) ) . ' گزینه با خطا مواجه شد و ساخته نشد.';
        }
        $lines[] = '';
        $lines[] = 'تا وقتی خودت یکی را منتشر نکنی، هیچ‌کدام روی سایت نمایش داده نمی‌شود.';

        wp_mail(
            get_option( 'admin_email' ),
            count( $post_ids ) . ' گزینه‌ی جدید (' . $label . ') آماده تایید است — پیوند امن',
            implode( "\n", $lines )
        );
    }

    private static function notify_admin_error( string $error ): void {
        wp_mail(
            get_option( 'admin_email' ),
            'خطا در تولید خودکار کارت روزانه — پیوند امن',
            "تولید خودکار کارت‌های امروز ناموفق بود:\n\n{$error}\n\nمی‌توانید از پیشخوان ← فید محتوای روزانه ← تنظیمات ← تولید خودکار، به‌صورت دستی دوباره امتحان کنید."
        );
    }
}
