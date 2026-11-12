<?php

declare(strict_types=1);

namespace ZoojDarman\ClientPortal;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * لیدهای گرفته‌شده از فانل کوییز عمومی (لید مگنت) — کاملاً مستقل از
 * psy-quiz-enterprise، فقط برای پیگیری فروش/فالوآپ.
 */
final class Leads
{
    public function create(string $phone, string $source, string $quiz_topic, int $score, string $band): int
    {
        global $wpdb;
        $phone = to_local_format($phone);

        $inserted = $wpdb->insert(Installer::table('leads'), [
            'phone'      => $phone,
            'source'     => $source,
            'quiz_topic' => $quiz_topic,
            'quiz_score' => $score,
            'quiz_band'  => $band,
            'created_at' => now_utc(),
        ], ['%s', '%s', '%s', '%d', '%s', '%s']);

        if ($inserted === false) {
            // قبلاً نتیجه‌ی insert چک نمی‌شد. Quiz_Funnel::handle_submit_phone
            // نتیجه‌ی create() را نادیده می‌گیرد و به کاربر همیشه پیام موفقیت
            // نشان می‌دهد؛ پس اگر insert واقعاً شکست بخورد، لید بدون هیچ اثری
            // گم می‌شد. حداقل با لاگ‌کردن خطا، این حالت قابل‌ردیابی می‌شود.
            error_log('ZDP Lead insert failed: ' . $wpdb->last_error);

            return 0;
        }

        return (int) $wpdb->insert_id;
    }

    public function mark_link_clicked(string $phone): void
    {
        global $wpdb;
        $wpdb->update(Installer::table('leads'), ['link_clicked' => 1], ['phone' => to_local_format($phone)], ['%d'], ['%s']);
    }

    /** نسخه‌ی دقیق‌تر برای لینک‌های شخصی‌سازی‌شده (یک لید مشخص، نه هر لیدی با آن شماره). */
    public function mark_link_clicked_by_id(int $lead_id): void
    {
        global $wpdb;
        $wpdb->update(Installer::table('leads'), ['link_clicked' => 1], ['id' => $lead_id], ['%d'], ['%d']);
    }

    public function mark_booked(string $phone): void
    {
        global $wpdb;
        $wpdb->update(Installer::table('leads'), ['booked' => 1], ['phone' => to_local_format($phone)], ['%d'], ['%s']);
    }

    private const FOLLOWUP_COLUMNS = ['followed_up_24h', 'followed_up_72h'];

    /** @return array<int,object> */
    public function get_leads_needing_followup(string $column, int $hours_since_creation): array
    {
        if (!in_array($column, self::FOLLOWUP_COLUMNS, true)) {
            return [];
        }

        global $wpdb;
        $table = Installer::table('leads');

        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table WHERE $column = 0 AND booked = 0 AND created_at <= %s LIMIT 200",
            gmdate('Y-m-d H:i:s', time() - $hours_since_creation * HOUR_IN_SECONDS)
        ));
    }

    public function mark_followed_up(int $lead_id, string $column): void
    {
        if (!in_array($column, self::FOLLOWUP_COLUMNS, true)) {
            return;
        }

        global $wpdb;
        $wpdb->update(Installer::table('leads'), [$column => 1], ['id' => $lead_id], ['%d'], ['%d']);
    }

    public function get_all(int $limit = 200): array
    {
        global $wpdb;

        return $wpdb->get_results($wpdb->prepare(
            'SELECT * FROM ' . Installer::table('leads') . ' ORDER BY created_at DESC LIMIT %d',
            $limit
        ));
    }
}
