<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * تایم‌لاین یکپارچه‌ی پرونده (فاز ۳).
 *
 * یک نمای فقط‌خواندنی از همه‌ی رویدادهای مهم یک پرونده، بدون جدول جدید:
 *   - ایجاد پرونده و ارسال فرم ارزیابی
 *   - انتقال اطلاعات فرم به پرونده (از ثبت منبع هسته، تجمیع‌شده)
 *   - ثبت / ویرایش / حذف / بازگردانی جلسه
 *   - خروجی CSV و ویرایش مشخصات (از لاگ دسترسی)
 *
 * هیچ داده‌ی بالینی (متن یادداشت) در این کلاس خوانده یا برگردانده نمی‌شود؛
 * فقط زمان، نوع و شماره‌ی جلسه. افزونه‌های دیگر می‌توانند بدون دسترسی به
 * جدول‌های این افزونه، رویداد خودشان را با فیلتر
 * `pas_cf_timeline_events` اضافه کنند.
 */
final class Pas_CF_Timeline
{
    /** برچسب فارسی اکشن‌های لاگ دسترسی که در تایم‌لاین می‌آیند. */
    private const AUDIT_LABELS = [
        'edit_case'    => 'مشخصات پرونده ویرایش شد',
        'export_csv'   => 'خروجی CSV گرفته شد',
        'view_intake'  => 'فرم ارزیابی مراجع دیده شد',
        'restore_note' => 'نسخه‌ی قبلی یادداشت بازگردانده شد',
    ];

    /** اکشن‌هایی که یا نویز هستند یا از منبع دقیق‌تری (notes/note_history) می‌آیند. */
    private const AUDIT_SKIP = [
        'view', 'add_note', 'edit_note', 'delete_note', 'apply_intake_map',
    ];

    /**
     * @return array<int,array{at:string,type:string,title:string,detail:string,user_id:int}>
     */
    public static function for_case(object $case, int $limit = 50): array
    {
        global $wpdb;

        $case_id = (int) $case->id;
        $events  = [];

        if (!empty($case->created_at)) {
            $events[] = self::event(
                (string) $case->created_at,
                'case_created',
                'پرونده ساخته شد',
                '',
                (int) ($case->created_by ?? 0)
            );
        }

        if (!empty($case->intake_submitted_at)) {
            $events[] = self::event(
                (string) $case->intake_submitted_at,
                'intake_received',
                'فرم ارزیابی مراجع ثبت شد',
                '',
                (int) ($case->created_by ?? 0)
            );
        }

        $events = array_merge($events, self::intake_events($case_id));

        // جلسات (بدون متن یادداشت).
        $notes = (array) $wpdb->get_results($wpdb->prepare(
            'SELECT * FROM ' . Pas_CF_Installer::table('notes') . ' WHERE case_id = %d',
            $case_id
        ));

        foreach ($notes as $note) {
            $number = (int) ($note->session_number ?? 0);
            $when   = (string) ($note->session_date ?? '');
            $detail = $when !== '' ? 'تاریخ جلسه: ' . self::jalali_date($when) : '';

            $extra = [];
            if (!empty($note->status) && $note->status !== 'done') {
                $extra[] = 'وضعیت: ' . self::status_label((string) $note->status);
            }
            if ((int) ($note->duration_minutes ?? 0) > 0) {
                $extra[] = (int) $note->duration_minutes . ' دقیقه';
            }
            if (!empty($note->next_session_date)) {
                $extra[] = 'جلسه‌ی بعد: ' . self::jalali_date((string) $note->next_session_date);
            }
            if (!empty($extra)) {
                $detail = trim($detail . ' — ' . implode(' — ', $extra), ' —');
            }

            $events[] = self::event(
                (string) ($note->created_at ?? ''),
                'session',
                ($number > 0 ? 'جلسه‌ی ' . $number : 'جلسه') . ' ثبت شد',
                $detail,
                (int) ($note->author_id ?? 0)
            );
        }

        // ویرایش / حذف / بازگردانی یادداشت — از تاریخچه، نه از خود یادداشت.
        $history = (array) $wpdb->get_results($wpdb->prepare(
            'SELECT * FROM ' . Pas_CF_Installer::table('note_history') . ' WHERE case_id = %d',
            $case_id
        ));

        $history_labels = [
            'edited'   => 'نسخه‌ی قبلی یادداشت جلسه ذخیره شد',
            'deleted'  => 'یادداشت جلسه حذف شد (قابل بازگردانی)',
            'restored' => 'نسخه‌ی قبلی یادداشت جلسه بازگردانده شد',
        ];

        foreach ($history as $row) {
            $action = (string) ($row->action ?? '');
            if (!isset($history_labels[$action])) {
                continue;
            }
            $number = (int) ($row->session_number ?? 0);
            $events[] = self::event(
                (string) ($row->changed_at ?? ''),
                'note_' . $action,
                $history_labels[$action],
                ($number > 0 ? 'جلسه‌ی ' . $number . ' — ' : '') . self::jalali_date((string) ($row->session_date ?? '')),
                (int) ($row->changed_by ?? 0)
            );
        }

        // لاگ دسترسی — فقط اکشن‌های معنادار و غیرتکراری.
        if (class_exists('Pas_CF_Audit_Log')) {
            foreach (Pas_CF_Audit_Log::for_case($case_id, $limit) as $row) {
                $action = (string) ($row->action ?? '');
                if (in_array($action, self::AUDIT_SKIP, true) || !isset(self::AUDIT_LABELS[$action])) {
                    continue;
                }
                $events[] = self::event(
                    (string) ($row->created_at ?? ''),
                    'audit_' . $action,
                    self::AUDIT_LABELS[$action],
                    '',
                    (int) ($row->user_id ?? 0)
                );
            }
        }

        /**
         * افزونه‌های دیگر (مثلاً نوبت‌دهی) می‌توانند رویداد خودشان را اضافه
         * کنند بدون خواندن جدول‌های این افزونه. هر رویداد باید کلیدهای
         * at/type/title/detail/user_id داشته باشد.
         */
        $events = apply_filters('pas_cf_timeline_events', $events, $case);

        $events = array_values(array_filter((array) $events, static function ($event) {
            return is_array($event) && !empty($event['at']) && !empty($event['title']);
        }));

        usort($events, static function (array $a, array $b): int {
            $cmp = strcmp((string) $b['at'], (string) $a['at']);
            return $cmp !== 0 ? $cmp : strcmp((string) ($b['type'] ?? ''), (string) ($a['type'] ?? ''));
        });

        return array_slice($events, 0, max(1, $limit));
    }

    /**
     * انتقال فرم به پرونده: ثبت منبع هر فیلد، تجمیع‌شده در یک رویداد.
     *
     * @return array<int,array<string,mixed>>
     */
    private static function intake_events(int $case_id): array
    {
        if (!class_exists('Pas_Core_Field_Mapper')) {
            return [];
        }

        $provenance = Pas_Core_Field_Mapper::provenance_map('case', $case_id);

        $intake = [];
        foreach ($provenance as $row) {
            if ((string) ($row->source ?? '') === 'intake') {
                $intake[] = $row;
            }
        }

        if (empty($intake)) {
            return [];
        }

        $latest_at = '';
        $user_id   = 0;
        foreach ($intake as $row) {
            if ((string) $row->set_at > $latest_at) {
                $latest_at = (string) $row->set_at;
                $user_id   = (int) $row->set_by;
            }
        }

        return [self::event(
            $latest_at,
            'intake_mapped',
            sprintf('%d فیلد از فرم ارزیابی به پرونده منتقل شد', count($intake)),
            count($intake) . ' فیلد ثبت منبع دارند',
            $user_id
        )];
    }

    private static function event(string $at, string $type, string $title, string $detail, int $user_id): array
    {
        return [
            'at'      => $at,
            'type'    => $type,
            'title'   => $title,
            'detail'  => $detail,
            'user_id' => $user_id,
        ];
    }

    private static function jalali_date(string $gregorian): string
    {
        if ($gregorian === '') {
            return '';
        }

        $out = Pas_CF_Jalali::to_jalali_string($gregorian);

        return $out !== '' ? $out : $gregorian;
    }

    private static function status_label(string $status): string
    {
        return Pas_CF_Case_File::session_statuses()[$status] ?? $status;
    }
}
