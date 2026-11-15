<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * شنوندهٔ رویدادهای بالینی (فاز ۵).
 *
 * تنها کارش این است که رویداد `assessment.completed` (که آزمون در فاز ۳
 * منتشر می‌کند) را به دو چیز قابل اقدام تبدیل کند: یک «وظیفهٔ بررسی نتیجه»
 * و، اگر آزمون پرچم خودآسیبی برگردانده باشد، یک «هشدار بالینی».
 *
 * چرا از رویداد و از فیلتر، نه کوئری مستقیم:
 *  - payload رویداد عمداً فقط شناسه دارد؛ پاسخ/نمره در آن نیست.
 *  - پرچم خطر را خودِ افزونهٔ آزمون محاسبه می‌کند (مالک داده) و از طریق
 *    `psy_get_assessment_risk_flags` می‌دهد. پرونده هیچ‌وقت جدول آزمون را
 *    مستقیم نمی‌خواند.
 *
 * قواعد ایمنی:
 *  - هشدار یک «پرچم بررسی انسانی» است، نه تشخیص و نه دستور اقدام خودکار.
 *  - آستانه: هر پاسخ ≥۱ به آیتم خودآسیبی (پیش‌فرض مصوب: >=1 محافظه‌کارانه).
 *  - اگر پرونده پیدا نشود، هشدار ساخته نمی‌شود (هشدار بی‌صاحب داشبورد را
 *    شلوغ و بی‌اثر می‌کند)؛ فقط وظیفهٔ بدون پرونده ثبت می‌شود تا گم نشود.
 */
final class Pas_CF_Clinical_Events
{
    /** آستانهٔ مصوب: نمرهٔ ۱ یا بیشتر روی آیتم خودآسیبی = نیاز به بررسی. */
    public const SELF_HARM_THRESHOLD = 1;

    /**
     * هندلر `pas_event_assessment.completed`.
     *
     * @param array<string,mixed> $payload
     * @param array<string,mixed> $context
     */
    public static function on_assessment_completed($payload, $context = []): void
    {
        $payload = is_array($payload) ? $payload : [];
        $context = is_array($context) ? $context : [];

        $attempt_id = (int) ($payload['attempt_id'] ?? 0);
        $quiz_id    = (string) ($payload['quiz_id'] ?? '');
        if ($attempt_id <= 0) {
            return;
        }

        if (!class_exists('Pas_CF_Tasks') || !class_exists('Pas_CF_Alerts')) {
            return;
        }

        $client_id = (string) ($context['client_id'] ?? $payload['client_id'] ?? '');

        /** @var array<string,mixed>|null $flags */
        $flags = apply_filters('psy_get_assessment_risk_flags', null, $attempt_id);
        $flags = is_array($flags) ? $flags : [];

        if ($client_id === '' && !empty($flags['client_id'])) {
            $client_id = (string) $flags['client_id'];
        }

        $case = self::find_case($client_id, (string) ($flags['phone'] ?? ''));
        $phone = (string) ($flags['phone'] ?? '');

        $test_label = (string) ($flags['quiz_label'] ?? $quiz_id);
        $risk       = !empty($flags['risk_flagged']);
        $level      = (string) ($flags['level'] ?? ($risk ? 'high' : ''));
        $reasons    = isset($flags['reasons']) && is_array($flags['reasons']) ? array_filter(array_map('strval', $flags['reasons'])) : [];

        // ── ۱) وظیفهٔ همیشگی: بررسی نتیجه ─────────────────────────────────
        Pas_CF_Tasks::create([
            'case_id'     => $case ? (int) $case->id : 0,
            'client_id'   => $client_id,
            'title'       => $risk ? 'بررسی فوری نتیجهٔ آزمون' : 'بررسی نتیجهٔ آزمون',
            'description' => $test_label !== ''
                ? sprintf('آزمون «%s» تکمیل شد؛ نتیجه در پرونده بررسی شود.', $test_label)
                : 'یک آزمون تکمیل شد؛ نتیجه در پرونده بررسی شود.',
            'type'        => 'assessment_review',
            'source'      => 'assessment.completed',
            'source_ref'  => 'attempt:' . $attempt_id,
            'priority'    => $risk ? (self::severity($level) === 'critical' ? 'critical' : 'high') : 'medium',
            'assignee'    => $case ? (int) ($case->assigned_therapist ?? 0) : 0,
        ]);

        // ── ۲) هشدار فقط با پرچم خطر و پروندهٔ مشخص ──────────────────────
        if (!$risk || !$case) {
            return;
        }

        $reason = sprintf(
            'پاسخ به آیتم خودآسیبی/خودکشی در آزمون «%s» بالاتر از آستانهٔ بررسی است (%s). این یک هشدار بررسی انسانی است، نه تشخیص؛ لطفاً ارزیابی تخصصی و اقدام متناسب انجام شود.',
            $test_label !== '' ? $test_label : $quiz_id,
            $reasons ? implode('؛ ', $reasons) : 'نمرهٔ آیتم بحرانی'
        );

        $alert_id = Pas_CF_Alerts::create([
            'case_id'    => (int) $case->id,
            'client_id'  => $client_id,
            'source'     => 'quiz-enterprise',
            'source_ref' => 'attempt:' . $attempt_id,
            'type'       => 'self_harm_risk',
            'priority'   => self::severity($level),
            'reason'     => $reason,
            'assignee'   => (int) ($case->assigned_therapist ?? 0),
        ]);

        if ($alert_id > 0) {
            Pas_CF_Tasks::create([
                'case_id'     => (int) $case->id,
                'client_id'   => $client_id,
                'title'       => 'بررسی هشدار خودآسیبی',
                'description' => 'هشدار بالینی برای این پرونده ثبت شده است؛ وضعیت آن را در فهرست هشدارها به‌روزرسانی کنید.',
                'type'        => 'alert_review',
                'source'      => 'case-file.alerts',
                'source_ref'  => 'alert:' . $alert_id,
                'priority'    => self::severity($level) === 'critical' ? 'critical' : 'high',
                'assignee'    => (int) ($case->assigned_therapist ?? 0),
            ]);
        }
    }

    /** نگاشت سطح هشدار آزمون به اولویت داخلی. */
    private static function severity(string $level): string
    {
        return $level === 'critical' ? 'critical' : 'high';
    }

    /**
     * پروندهٔ متناظر: اول با شناسهٔ واحد (فاز ۳)، بعد با شمارهٔ نرمال‌شده.
     * هیچ‌وقت با نام تطبیق نمی‌دهد.
     */
    private static function find_case(string $client_id, string $phone): ?object
    {
        global $wpdb;

        $table = Pas_CF_Installer::table('cases');

        if ($client_id !== '') {
            $case = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $table WHERE client_id = %s ORDER BY id DESC LIMIT 1",
                $client_id
            ));
            if ($case) {
                return $case;
            }
        }

        if ($phone !== '' && class_exists('Pas_CF_Case_File')) {
            $normalized = Pas_CF_Case_File::normalize_phone($phone);
            if ($normalized !== '') {
                $case = $wpdb->get_row($wpdb->prepare(
                    "SELECT * FROM $table WHERE phone = %s ORDER BY id DESC LIMIT 1",
                    $normalized
                ));
                if ($case) {
                    return $case;
                }
            }
        }

        return null;
    }
}
