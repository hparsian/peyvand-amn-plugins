<?php

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

global $wpdb;

// عمداً جدول‌ها حذف نمی‌شوند مگر ادمین صراحتاً یک آپشن تایید حذف را فعال
// کرده باشد — چون این حساس‌ترین داده‌ی کل اکوسیستم است و حذف تصادفی هنگام
// غیرفعال/حذف کردن افزونه قابل جبران نیست.
if (get_option('pas_cf_allow_data_removal') === '1') {
    $wpdb->query('DROP TABLE IF EXISTS ' . $wpdb->prefix . 'pas_cf_notes');
    $wpdb->query('DROP TABLE IF EXISTS ' . $wpdb->prefix . 'pas_cf_note_history');
    $wpdb->query('DROP TABLE IF EXISTS ' . $wpdb->prefix . 'pas_cf_cases');
    $wpdb->query('DROP TABLE IF EXISTS ' . $wpdb->prefix . 'pas_cf_audit_log');
    delete_option('pas_cf_db_version');
    delete_option('pas_cf_allow_data_removal');

    $role = get_role('administrator');
    if ($role) {
        $role->remove_cap(PAS_CF_CAP ?? 'pas_manage_case_files');
        $role->remove_cap(PAS_CF_CAP_ALL ?? 'pas_view_all_case_files');
    }

    // نقش «درمانگر» که خودِ افزونه ساخته را هم پاک کن — کاربرانی که این
    // نقش را دارند به نقش پیش‌فرض وردپرس (subscriber) برنمی‌گردند، فقط
    // این نقش دیگر برایشان معنا ندارد؛ ادمین باید نقش تازه‌ای به آن‌ها بدهد.
    remove_role('pas_cf_therapist');
}
