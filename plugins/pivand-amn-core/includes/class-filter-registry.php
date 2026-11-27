<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * رجیستری مستندشده‌ی همه‌ی قراردادهای فیلتری بین‌افزونه‌ای اکوسیستم پیوند
 * امن. این فقط مستندسازی است (هیچ چیزی اجرا نمی‌کند) — هدف این است که یک
 * منبع واحد برای «چه فیلتری، کجا تعریف شده، کجا استفاده می‌شود» وجود داشته
 * باشد، به‌جای پراکنده بودن در کامنت هر پلاگین.
 *
 * وقتی قرارداد جدیدی اضافه می‌شود (مثل psy_get_completed_results_for_phone
 * که در پرونده مراجعین آماده نگه داشته شده ولی هنوز پیاده‌سازی نشده)، اینجا
 * هم اضافه شود.
 */
final class Pas_Core_Filter_Registry
{
    public static function contracts(): array
    {
        return [
            [
                'hook'      => 'pas_send_sms',
                'type'      => 'filter',
                'signature' => "apply_filters('pas_send_sms', null, \$mobile, \$body_id, \$params)",
                'provider'  => 'پیوند امن — پل پیامک (sms-bridge)',
                'consumers' => ['پنل مراجعان (OTP، تایید نوبت، یادآوری لید)'],
                'status'    => 'فعال',
                'note'      => 'تنها نقطه‌ی مجاز ارسال پیامک در کل اکوسیستم.',
            ],
            [
                'hook'      => 'psy_get_pending_quizzes_for_phone',
                'type'      => 'filter',
                'signature' => "apply_filters('psy_get_pending_quizzes_for_phone', [], \$mobile)",
                'provider'  => 'آزمون‌های روان‌سنجی (quiz-enterprise)',
                'consumers' => ['پنل مراجعان'],
                'status'    => 'فعال',
                'note'      => 'اگر quiz-enterprise غیرفعال باشد، آرایه‌ی خالی برمی‌گردد — بی‌صدا.',
            ],
            [
                'hook'      => 'psy_get_client_name_for_phone',
                'type'      => 'filter',
                'signature' => "apply_filters('psy_get_client_name_for_phone', '', \$mobile)",
                'provider'  => 'آزمون‌های روان‌سنجی (quiz-enterprise)',
                'consumers' => ['پنل مراجعان (نمایش نام هنگام OTP)'],
                'status'    => 'فعال',
                'note'      => '',
            ],
            [
                'hook'      => 'zdp_quiz_url',
                'type'      => 'filter',
                'signature' => "apply_filters('zdp_quiz_url', \$default_url, \$topic)",
                'provider'  => 'پنل مراجعان',
                'consumers' => ['داخلی — فانل کوییز عمومی'],
                'status'    => 'فعال',
                'note'      => '',
            ],
            [
                'hook'      => 'pas_get_case_summary_for_phone',
                'type'      => 'filter',
                'signature' => "apply_filters('pas_get_case_summary_for_phone', null, \$mobile)",
                'provider'  => 'پرونده مراجعین (case-file)',
                'consumers' => ['پنل مراجعان — تب «لیدها» (ستون «پرونده بالینی»)'],
                'status'    => '✅ فعال',
                'note'      => 'خلاصه‌ی غیرحساس (بدون یادداشت بالینی). اولین مصرف‌کننده‌ی واقعی این فیلتر.',
            ],
            [
                'hook'      => 'zdp_get_intake_for_phone',
                'type'      => 'filter',
                'signature' => "apply_filters('zdp_get_intake_for_phone', [], \$mobile)",
                'provider'  => 'پنل مراجعان (client-portal) 1.10.3.0+',
                'consumers' => ['پرونده مراجعین 1.9.0+ — کارت «فرم ارزیابی مراجع»'],
                'status'    => '✅ فعال',
                'note'      => 'فقط‌خواندنی؛ فقط در پیشخوان و برای مدیر یا دارنده‌ی pas_manage_case_files. خروجی: file_number، submitted_at، updated_at، data.',
            ],
            [
                'hook'      => 'psy_get_completed_results_for_phone',
                'type'      => 'filter',
                'signature' => "apply_filters('psy_get_completed_results_for_phone', [], \$mobile)",
                'provider'  => 'آزمون‌های روان‌سنجی (quiz-enterprise) 3.3.13+',
                'consumers' => ['پرونده مراجعین'],
                'status'    => '✅ فعال',
                'note'      => 'هر نتیجه لینکی به همان صفحه‌ی گزارش موجود در پیشخوان است، نه کپی خام داده.',
            ],
            [
                'hook'      => 'pas_lookup_known_client_name',
                'type'      => 'filter',
                'signature' => "apply_filters('pas_lookup_known_client_name', '', \$mobile)",
                'provider'  => 'زنجیره‌ای: پنل مراجعان (۵) ← پرونده مراجعین (۱۰) ← آزمون‌ها (۱۵)',
                'consumers' => ['فرم «آزمون جدید» (quiz-enterprise)', 'فرم «پرونده جدید» (case-file)', 'AJAX مشترک: wp_ajax_pas_core_lookup_client_name'],
                'status'    => '✅ فعال',
                'note'      => 'هر سه افزونه هم provider هم consumer‌اند — هرکدام اول چک می‌کند مقدار قبلاً پر شده یا نه، اگر نه دیتای خودش را برمی‌گرداند. اولین نام پیداشده در زنجیره برنده است؛ نامی که خود مراجع (پنل) اعلام کرده بیشترین اولویت را دارد.',
            ],
            [
                'hook'      => 'pas_get_client',
                'type'      => 'filter',
                'signature' => "apply_filters('pas_get_client', null, ['client_id'=>...] | ['type'=>'mobile|email','value'=>...] | ['wp_user_id'=>...])",
                'provider'  => 'پیوند امن — هسته مشترک (رجیستری هویت، 1.4.0+)',
                'consumers' => ['آماده برای پرونده، آزمون‌ها و داشبورد — فعلاً هیچ مصرف‌کننده‌ای ندارد'],
                'status'    => '✅ فعال (فقط‌خواندنی)',
                'note'      => 'خروجی: client_id، نام نمایشی، وضعیت، تعداد تعارض و فهرست شناسه‌ها. هیچ داده‌ی بالینی برنمی‌گرداند و فقط برای کاربر دارای manage_options یا PAS_CF_CAP کار می‌کند.',
            ],
            [
                'hook'      => 'pas_cf_timeline_events',
                'type'      => 'filter',
                'signature' => "apply_filters('pas_cf_timeline_events', \$events, \$case)",
                'provider'  => 'پرونده مراجعین (case-file) 1.11.0+',
                'consumers' => ['هر افزونه‌ای که رویداد مرتبط با پرونده دارد (مثلاً نوبت‌دهی) — هنوز مصرف‌کننده‌ای ندارد'],
                'status'    => '✅ فعال',
                'note'      => 'آرایه‌ی رویداد با کلیدهای at (Y-m-d H:i:s میلادی/UTC)، type، title، detail، user_id. تایم‌لاین خودش مرتب/محدود می‌کند.',
            ],
            [
                'hook'      => 'psy_get_assessment_scores_for_phone',
                'type'      => 'filter',
                'signature' => "apply_filters('psy_get_assessment_scores_for_phone', [], \$mobile)",
                'provider'  => 'آزمون‌های روان‌سنجی (quiz-enterprise) 3.6.7+',
                'consumers' => ['پرونده مراجعین 1.13.0+ — کارت «روند ارزیابی‌ها»'],
                'status'    => '✅ فعال',
                'note'      => 'هر ردیف: quiz_id، label، total (نمره‌ی کل یا null)، completed_at، url، attempt_id. نمره با رمزگشایی استاندارد خود quiz-enterprise خوانده می‌شود؛ پرونده هیچ ciphertext یا جدول psy_* را مستقیم نمی‌خواند.',
            ],
            [
                'hook'      => 'pas_cf_reminder_sms_pattern',
                'type'      => 'filter',
                'signature' => "apply_filters('pas_cf_reminder_sms_pattern', \$pattern_id)",
                'provider'  => 'پرونده مراجعین (case-file) 1.15.0+',
                'consumers' => ['هر سایتی که می‌خواهد شناسه‌ی الگوی پیامک یادآور را بدون ذخیره در دیتابیس تعیین کند — فعلاً مصرف‌کننده‌ای ندارد'],
                'status'    => '✅ فعال',
                'note'      => 'پیش‌فرض از آپشن pas_cf_reminder_sms_pattern خوانده می‌شود. پارامترهای ارسال به pas_send_sms: NAME و DATE.',
            ],
        ];
    }
}
