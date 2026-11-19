<?php

declare(strict_types=1);

namespace ZoojDarman\ClientPortal;

if (!defined('ABSPATH')) {
    exit;
}

final class Cron
{
    private const HOOK          = 'zdp_lead_followup_event';
    private const REMINDER_HOOK = 'zdp_appointment_reminder_event';

    private Leads $leads;
    private Booking $booking;

    public function __construct(Leads $leads, Booking $booking)
    {
        $this->leads   = $leads;
        $this->booking = $booking;
        add_action(self::HOOK, [$this, 'process_followups']);
        add_action(self::REMINDER_HOOK, [$this, 'process_appointment_reminders']);
    }

    public static function schedule_events(): void
    {
        if (!wp_next_scheduled(self::HOOK)) {
            wp_schedule_event(time() + HOUR_IN_SECONDS, 'hourly', self::HOOK);
        }
        if (!wp_next_scheduled(self::REMINDER_HOOK)) {
            wp_schedule_event(time() + HOUR_IN_SECONDS, 'hourly', self::REMINDER_HOOK);
        }
    }

    public static function clear_events(): void
    {
        $timestamp = wp_next_scheduled(self::HOOK);
        if ($timestamp) {
            wp_unschedule_event($timestamp, self::HOOK);
        }
        $reminder_timestamp = wp_next_scheduled(self::REMINDER_HOOK);
        if ($reminder_timestamp) {
            wp_unschedule_event($reminder_timestamp, self::REMINDER_HOOK);
        }
    }

    /**
     * یادآوری خودکار قبل از جلسه — دو‌کاناله: هم پیامک و هم ایمیل.
     *
     * قواعد (طبق درخواست کارفرما):
     *  - شماره‌ی ایرانی + ایمیل واقعی: هر دو کانال تلاش می‌شوند.
     *  - شماره‌ی خارجی (E.164 با «+»): فقط ایمیل؛ پل پیامک فقط 09xxxxxxxxx
     *    را می‌شناسد و پیامکِ شماره‌ی خارجی همیشه شکست می‌خورد.
     *  - شمارهٔ خالی: فقط ایمیل (حساب‌هایی که فقط با ایمیل ساخته شده‌اند).
     *  - ایمیل ساختگیِ موبایل (`0912...@دامنه`): نادیده گرفته می‌شود، وگرنه
     *    هر یادآوری یک ایمیل باونس می‌شود؛ نگاه کن به zdp_user_email().
     *
     * تنظیمات: zdp_appointment_reminder_enabled،
     * zdp_appointment_reminder_hours_before (پیش‌فرض ۲۴) و
     * zdp_sms_reminder_body_id. نبودِ پترن پیامک دیگر کل یادآوری را متوقف
     * نمی‌کند؛ ایمیل مستقل از آن ارسال می‌شود.
     *
     * هر نوبت فقط یک بار (reminder_sent_at) و تنها وقتی حداقل یک کانال
     * موفق شود علامت می‌خورد، تا خطای گذرای همان ساعت، یادآوری را برای همیشه
     * از دست ندهد؛ اگر هیچ کانالی موفق نشد، ایمیل هشدار (throttled) به ادمین
     * می‌رود تا خودش تماس بگیرد.
     */
    public function process_appointment_reminders(): void
    {
        if (!get_option('zdp_appointment_reminder_enabled', false)) {
            return;
        }

        $body_id = (string) get_option('zdp_sms_reminder_body_id', '');
        $hours_before = (int) get_option('zdp_appointment_reminder_hours_before', 24);

        foreach ($this->booking->get_bookings_needing_reminder($hours_before) as $b) {
            $user_id = (int) $b->user_id;
            $mobile  = zdp_get_user_mobile($user_id);
            $email   = zdp_user_email($user_id);

            // شماره‌ی خارجی با پل پیامک فعلی ارسال نمی‌شود؛ فقط ایمیل.
            $sms_allowed = $mobile !== '' && $body_id !== '' && !str_starts_with($mobile, '+');

            if (!$sms_allowed && $email === '') {
                // هیچ کانال قابل‌استفاده‌ای نیست (نه شماره‌ی ایرانی، نه ایمیل)؛
                // علامت می‌زنیم تا هر ساعت دوباره تلاش بی‌نتیجه نشود.
                $this->booking->mark_reminder_sent((int) $b->id);
                audit_log('appointment_reminder_skipped_no_channel', ['booking_id' => $b->id]);
                continue;
            }

            $date = Jalali::gregorian_to_jalali_string($b->booking_date);
            $time = substr((string) $b->booking_time, 0, 5);

            $sms_sent = $sms_allowed && apply_filters('pas_send_sms', null, $mobile, $body_id, [
                'DATE' => $date,
                'TIME' => $time,
            ]);

            $email_sent = $email !== '' && $this->send_reminder_email($email, $date, $time);

            $sent = $sms_sent || $email_sent;

            if ($sent) {
                if ($sms_allowed && !$sms_sent) {
                    audit_log('appointment_reminder_sms_failed', ['booking_id' => $b->id]);
                }
                if ($email !== '' && !$email_sent) {
                    audit_log('appointment_reminder_email_failed', ['booking_id' => $b->id]);
                }
                $this->booking->mark_reminder_sent((int) $b->id);
                audit_log('appointment_reminder_sent', [
                    'booking_id' => $b->id,
                    'channels'   => array_values(array_filter([
                        $sms_sent ? 'sms' : null,
                        $email_sent ? 'email' : null,
                    ])),
                ]);
                continue;
            }

            $this->notify_admin_reminder_failed($b, $mobile);
        }
    }

    /** ایمیل یادآوری نوبت به مراجع؛ متن هم‌معنی پیامک، با تاریخ و ساعت شمسی. */
    private function send_reminder_email(string $email, string $date, string $time): bool
    {
        $site_name = wp_specialchars_decode((string) get_bloginfo('name'), ENT_QUOTES);
        $subject   = 'یادآوری نوبت شما در ' . $site_name;
        $body      = "سلام،\n\n"
            . "یادآوری می‌کنیم نوبت شما در {$site_name} در تاریخ {$date} ساعت {$time} ثبت شده است.\n"
            . "اگر نیاز به تغییر یا لغو نوبت دارید، پیش از زمان جلسه با ما تماس بگیرید.\n\n"
            . "با احترام\n{$site_name}";

        return (bool) wp_mail($email, $subject, $body);
    }

    /** ایمیل هشدار به ادمین وقتی هیچ کانالی برای یادآوری نوبت موفق نشد (حداکثر یک‌بار در ۶ ساعت برای هر نوبت). */
    private function notify_admin_reminder_failed(object $booking, string $mobile): void
    {
        $throttle_key = 'zdp_reminder_fail_notified_' . $booking->id;
        if (get_transient($throttle_key)) {
            return;
        }
        set_transient($throttle_key, '1', 6 * HOUR_IN_SECONDS);

        $admin_email = get_option('admin_email');
        if (!$admin_email) {
            return;
        }

        wp_mail(
            $admin_email,
            '⚠️ یادآوری نوبت ارسال نشد',
            sprintf(
                "یادآوری نوبت شماره %d (شماره مراجع: %s، تاریخ: %s، ساعت: %s) از هیچ کانالی (پیامک/ایمیل) ارسال نشد.\n" .
                "لطفاً اعتبار پنل پیامکی و تنظیمات ایمیل سایت را بررسی کن و در صورت نیاز خودت با مراجع تماس بگیر.",
                $booking->id,
                $mobile,
                Jalali::gregorian_to_jalali_string($booking->booking_date),
                substr((string) $booking->booking_time, 0, 5)
            )
        );
        audit_log('appointment_reminder_sms_failed_email_sent', ['booking_id' => $booking->id]);
    }

    /**
     * فالوآپ لیدهای کوییز عمومی که هنوز رزرو نکرده‌اند.
     *
     * نکته‌ی مهم: پترن‌های واقعی ثبت‌شده در پنل ملی‌پیامک (۴۸۹۴۳۶ برای ۲۴
     * ساعته، ۴۸۹۴۴۱ برای ۷۲ ساعته) فقط یک متغیر دارند: «کد پیگیری» ({0}) —
     * نه موضوع کوییز و نه لینک. متن پیامک از قبل شماره تماس کلینیک را دارد و
     * از مراجع می‌خواهد خودش تماس بگیرد. پس اینجا فقط همان یک مقدار (شناسه‌ی
     * لید، به‌عنوان کد پیگیری قابل‌جست‌وجو در تب «لیدها») پاس داده می‌شود.
     *
     * زمان‌بندی با created_at بر مبنای UTC مقایسه می‌شود (همان تابع now_utc
     * در کل پلاگین) تا هیچ‌گاه دچار اختلاف تایم‌زون سرور/سایت نشویم.
     *
     * یادآوری ایمیل به مراجع ممکن نیست: هیچ‌جای این افزونه ایمیل واقعی
     * مراجع ذخیره نمی‌شود (فقط شماره موبایل؛ ایمیل حساب OTP هم ساختگی است:
     * mobile@domain، نه ایمیل واقعی). به‌جای آن، وقتی کانال پیامک fail شود
     * (مثلاً اعتبار ملی‌پیامک تمام شده)، یک ایمیل هشدار به ادمین می‌رود تا
     * خودش دستی با مراجع تماس بگیرد — این همان کانال پشتیبان واقعی است که
     * با داده‌ی موجود ممکن است.
     */
    public function process_followups(): void
    {
        foreach ($this->leads->get_leads_needing_followup('followed_up_24h', 24) as $lead) {
            $sent = apply_filters('pas_send_sms', null, $lead->phone, get_option('zdp_lead_followup_24h_template', ''), [
                'TRACKING_CODE' => (int) $lead->id,
            ]);
            if ($sent) {
                $this->leads->mark_followed_up((int) $lead->id, 'followed_up_24h');
                audit_log('lead_followup_24h_sent', ['lead_id' => $lead->id]);
            } else {
                $this->notify_admin_sms_failed($lead, '24 ساعته');
            }
        }

        foreach ($this->leads->get_leads_needing_followup('followed_up_72h', 72) as $lead) {
            $sent = apply_filters('pas_send_sms', null, $lead->phone, get_option('zdp_lead_followup_72h_template', ''), [
                'TRACKING_CODE' => (int) $lead->id,
            ]);
            if ($sent) {
                $this->leads->mark_followed_up((int) $lead->id, 'followed_up_72h');
                audit_log('lead_followup_72h_sent', ['lead_id' => $lead->id]);
            } else {
                $this->notify_admin_sms_failed($lead, '72 ساعته');
            }
        }
    }

    /**
     * ایمیل هشدار به ادمین وقتی ارسال پیامک فالوآپ ناموفق بود. حداکثر یک
     * ایمیل برای هر (lead_id + بازه) در هر ۶ ساعت فرستاده می‌شود — چون این
     * کرون هرساعته اجرا می‌شود و اگر پیامک مدام fail شود (مثلاً اعتبار
     * ملی‌پیامک تمام شده)، بدون این محدودیت هر ساعت یک ایمیل جدید می‌رفت.
     */
    private function notify_admin_sms_failed(object $lead, string $window_label): void
    {
        $throttle_key = 'zdp_sms_fail_notified_' . $lead->id . '_' . $window_label;
        if (get_transient($throttle_key)) {
            return;
        }
        set_transient($throttle_key, '1', 6 * HOUR_IN_SECONDS);

        $admin_email = get_option('admin_email');
        if (!$admin_email) {
            return;
        }

        $subject = sprintf('⚠️ ارسال پیامک یادآوری (%s) ناموفق بود', $window_label);
        $body    = sprintf(
            "پیامک فالوآپ %s برای لید زیر ارسال نشد (احتمالاً مشکل اعتبار/اتصال ملی‌پیامک).\n\n" .
            "شماره: %s\nموضوع کوییز: %s\nکد پیگیری (شناسه لید): %d\n\n" .
            "لطفاً به‌صورت دستی با این مراجع تماس بگیرید، و اعتبار پنل ملی‌پیامک را بررسی کنید.",
            $window_label,
            $lead->phone,
            $lead->quiz_topic,
            (int) $lead->id
        );

        wp_mail($admin_email, $subject, $body);
        audit_log('lead_followup_sms_failed_email_sent', ['lead_id' => $lead->id, 'window' => $window_label]);
    }
}
