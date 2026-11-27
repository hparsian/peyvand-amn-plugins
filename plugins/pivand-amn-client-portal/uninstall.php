<?php

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

global $wpdb;

// جداول اختصاصی
foreach (['bookings', 'messages', 'surveys', 'leads', 'intake'] as $t) {
    $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}zdp_{$t}");
}

// آپشن‌ها
//
// این لیست باید با هر آپشن جدیدی که یک نسخه اضافه می‌کند هم‌گام نگه داشته
// شود؛ لیست قبلی از حدود نسخه‌ی ۱.۶ به بعد به‌روزرسانی نشده بود و آپشن‌های
// نسخه‌های ۱.۷/۱.۸ (ساعات‌کاری، تعطیلی، یادآوری نوبت، سوالات اضافیِ
// نظرسنجی، پرچم reconcile) را جا انداخته بود — یعنی «حذف» افزونه عملاً این
// آپشن‌ها را برای همیشه در دیتابیس باقی می‌گذاشت. دو آپشن مرده‌ی
// `zdp_sms_username`/`zdp_sms_password` هم حذف شدند چون هیچ‌جای کد فعلی
// آن‌ها را نمی‌خواند یا نمی‌نویسد (باقیمانده‌ی یک نسخه‌ی قدیمی‌تر).
$options = [
    'zdp_db_version',
    'zdp_legacy_migrated',
    'zdp_surveys_reconciled',
    'zdp_panel_page_id',
    'zdp_sms_body_id',
    'zdp_sms_booking_body_id',
    'zdp_sms_admin_body_id',
    'zdp_admin_mobile',
    'zdp_support_phone',
    'zdp_zarinpal_merchant_id',
    'zdp_zarinpal_sandbox',
    'zdp_payment_method',
    'zdp_services_list',
    'zdp_manual_payment_cards',
    'zdp_survey_questions',
    'zdp_survey_extra_questions',
    'zdp_sms_intake_body_id',
    'zdp_intake_remind_hours',
    'zdp_intake_max_sms',
    'zdp_lead_followup_24h_template',
    'zdp_lead_followup_72h_template',
    'zdp_business_hours',
    'zdp_blackout_dates',
    'zdp_appointment_reminder_enabled',
    'zdp_appointment_reminder_hours_before',
    'zdp_sms_reminder_body_id',
    'zdp_audit_log',
];
foreach ($options as $option) {
    delete_option($option);
}

// متاهای اختصاصی کاربران. توجه: عمداً 'cellphone' اینجا نیست — آن کلید
// مشترک کل اکوسیستم پیوند امن است (سایر پلاگین‌ها هم آن را می‌خوانند/می‌نویسند)
// و حذف این پلاگین نباید هویت مراجعین را برای بقیه‌ی سیستم از بین ببرد.
$wpdb->query("DELETE FROM {$wpdb->usermeta} WHERE meta_key IN ('zdp_mobile', 'zdp_full_name')");

// ترنزینت‌های OTP/کوییز/rate-limit (کلید و timeout هر دو به‌صورت options ذخیره می‌شوند)
$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '\_transient\_zdp\_%' OR option_name LIKE '\_transient\_timeout\_zdp\_%'");

// رویدادهای کرون
//
// نکته‌ی رفع‌شده: کرون یادآوری نوبت (zdp_appointment_reminder_event، از
// نسخه‌ی ۱.۸.۰) اینجا اصلاً unschedule نمی‌شد. یعنی بعد از «حذف» افزونه،
// این رویداد برای همیشه در جدول کرون وردپرس باقی می‌ماند و هر ساعت بدون
// اینکه هیچ کلاسی برای پردازشش لود شود، فقط به‌عنوان یک ورودی یتیم اجرا/رد
// می‌شد.
foreach (['zdp_lead_followup_event', 'zdp_appointment_reminder_event'] as $hook) {
    $timestamp = wp_next_scheduled($hook);
    if ($timestamp) {
        wp_unschedule_event($timestamp, $hook);
    }
}

// توجه: صرفاً «غیرفعال‌سازی» افزونه هیچ داده‌ای را پاک نمی‌کند؛ فقط انتخاب
// «حذف» از صفحه‌ی افزونه‌ها این پاک‌سازی را اجرا می‌کند (طبق همان قرارداد
// نسخه‌ی قبلی که در custom-psychology-portal/uninstall.php وجود داشت).
