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
     * یادآوری پیامکی خودکار قبل از جلسه — طبق تنظیمات «یادآوری نوبت» در
     * پیشخوان: zdp_appointment_reminder_enabled (فعال/غیرفعال)،
     * zdp_appointment_reminder_hours_before (چند ساعت قبل، پیش‌فرض ۲۴) و
     * zdp_sms_reminder_body_id (پترن ملی‌پیامک با متغیرهای DATE/TIME).
     * دقیقاً هم‌الگوی process_followups: هرساعته اجرا می‌شود، هر نوبت را فقط
     * یک‌بار پیامک می‌کند (reminder_sent_at)، و در صورت شکست ارسال، به‌جای
     * سکوت، به ادمین ایمیل هشدار می‌رود (throttled) تا خودش تماس بگیرد.
     */
    public function process_appointment_reminders(): void
    {
        if (!get_option('zdp_appointment_reminder_enabled', false)) {
            return;
        }

        $body_id = (string) get_option('zdp_sms_reminder_body_id', '');
        if ($body_id === '') {
            return;
        }

        $hours_before = (int) get_option('zdp_appointment_reminder_hours_before', 24);

        foreach ($this->booking->get_bookings_needing_reminder($hours_before) as $b) {
            $mobile = zdp_get_user_mobile((int) $b->user_id);
            if ($mobile === '') {
                $this->booking->mark_reminder_sent((int) $b->id);
                continue;
            }

            $sent = apply_filters('pas_send_sms', null, $mobile, $body_id, [
                'DATE' => Jalali::gregorian_to_jalali_string($b->booking_date),
                'TIME' => substr((string) $b->booking_time, 0, 5),
            ]);

            if ($sent) {
                // قبلاً mark_reminder_sent() بدون قید و شرط (چه ارسال موفق بود
                // چه نبود) صدا زده می‌شد؛ یعنی یک خطای گذرا در همان تیک
                // ساعتی (مثلاً قطعی لحظه‌ای اتصال به ملی‌پیامک) نوبت را برای
                // همیشه از چرخه‌ی یادآوری خارج می‌کرد و دیگر هیچ‌وقت دوباره
                // تلاش نمی‌شد — فقط یک ایمیل هشدار یک‌باره به ادمین می‌رفت.
                // حالا فقط روی ارسال موفق علامت زده می‌شود تا اگر تلاش این
                // ساعت شکست بخورد، ساعت بعد دوباره امتحان شود (دقیقاً هم‌الگو
                // با process_followups روی لیدها).
                $this->booking->mark_reminder_sent((int) $b->id);
                audit_log('appointment_reminder_sms_sent', ['booking_id' => $b->id]);
            } else {
                $this->notify_admin_reminder_failed($b, $mobile);
            }
        }
    }

    /** ایمیل هشدار به ادمین وقتی ارسال پیامک یادآوری نوبت ناموفق بود (حداکثر یک‌بار در ۶ ساعت برای هر نوبت). */
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
            '⚠️ پیامک یادآوری نوبت ارسال نشد',
            sprintf(
                "پیامک یادآوری برای نوبت شماره %d (شماره مراجع: %s، تاریخ: %s، ساعت: %s) ارسال نشد.\n" .
                "لطفاً اعتبار پنل پیامکی را بررسی کن و در صورت نیاز خودت با مراجع تماس بگیر.",
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
