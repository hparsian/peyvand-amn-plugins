<?php

if (!defined('ABSPATH')) {
    exit;
}

final class Pas_CF_Audit_Log
{
    public static function record(int $case_id, string $action): void
    {
        global $wpdb;

        $wpdb->insert(Pas_CF_Installer::table('audit_log'), [
            'case_id'    => $case_id,
            'user_id'    => get_current_user_id(),
            'action'     => $action, // 'view' | 'create' | 'edit_case' | 'add_note' | 'edit_note' | 'delete_note' | 'restore_note' | 'export_csv' | 'view_intake'
            'ip'         => Pas_CF_Security::get_client_ip(),
            'created_at' => current_time('mysql', true),
        ], ['%d', '%d', '%s', '%s', '%s']);
    }

    public static function for_case(int $case_id, int $limit = 50): array
    {
        global $wpdb;

        return $wpdb->get_results($wpdb->prepare(
            'SELECT * FROM ' . Pas_CF_Installer::table('audit_log') . ' WHERE case_id = %d ORDER BY created_at DESC LIMIT %d',
            $case_id,
            $limit
        ));
    }
}
