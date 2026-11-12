<?php

declare(strict_types=1);

namespace ZoojDarman\ClientPortal;

if (!defined('ABSPATH')) {
    exit;
}

final class Messages
{
    private Security $security;

    public function __construct(Security $security)
    {
        $this->security = $security;

        add_action('wp_ajax_zdp_send_message', [$this, 'handle_send_message']);
        add_action('wp_ajax_nopriv_zdp_send_message', [$this, 'handle_send_message']);
        add_action('admin_post_zdp_admin_reply', [$this, 'handle_admin_reply']);
    }

    public function handle_send_message(): void
    {
        $this->security->verify_ajax_nonce();

        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => 'ابتدا وارد پنل شوید.']);
        }

        $user_id = get_current_user_id();
        $text    = isset($_POST['message_text']) ? sanitize_textarea_field(wp_unslash($_POST['message_text'])) : '';

        if (mb_strlen(trim($text)) < 3) {
            wp_send_json_error(['message' => 'متن پیام خیلی کوتاه است.']);
        }

        if (!$this->security->allow('message_' . $user_id, 10, 3600)) {
            wp_send_json_error(['message' => 'تعداد پیام‌های ارسالی شما در این ساعت زیاد بوده. کمی صبر کنید.']);
        }

        global $wpdb;
        $inserted = $wpdb->insert(Installer::table('messages'), [
            'user_id'      => $user_id,
            'message_text' => $text,
            'status'       => 'خوانده نشده',
            'created_at'   => now_utc(),
        ], ['%d', '%s', '%s', '%s']);

        if ($inserted === false) {
            // قبلاً نتیجه‌ی insert اصلاً چک نمی‌شد؛ اگر واقعاً شکست می‌خورد
            // (خطای دیتابیس)، کاربر پیام موفقیت می‌دید ولی هیچ رکوردی ذخیره
            // نشده بود و پیام برای همیشه گم می‌شد. همان الگوی اصلاح‌شده در
            // Survey::handle_submit اینجا هم اعمال شد.
            error_log('ZDP Message insert failed: ' . $wpdb->last_error);
            wp_send_json_error(['message' => 'خطایی در ارسال پیام رخ داد. لطفاً دوباره تلاش کنید.']);
        }

        wp_send_json_success(['message' => 'پیام شما ارسال شد.']);
    }

    /**
     * پاسخ ادمین از طریق admin-post.php با nonce اختصاصیِ هر پیام و الگوی
     * PRG (redirect بعد از POST) تا رفرش صفحه‌ی ادمین باعث ارسال دوباره‌ی
     * فرم و پاسخ تکراری نشود — دقیقاً همان باگ resubmission که در
     * pivand-amn-panel رفع شد.
     */
    public function handle_admin_reply(): void
    {
        $this->security->require_manage_capability();

        $msg_id = isset($_POST['msg_id']) ? (int) $_POST['msg_id'] : 0;
        check_admin_referer('zdp_reply_action_' . $msg_id, 'zdp_reply_nonce');

        $reply = isset($_POST['admin_reply_text']) ? sanitize_textarea_field(wp_unslash($_POST['admin_reply_text'])) : '';

        if ($msg_id > 0 && !empty($reply)) {
            global $wpdb;
            $wpdb->update(
                Installer::table('messages'),
                ['admin_reply' => $reply, 'status' => 'پاسخ داده شده'],
                ['id' => $msg_id],
                ['%s', '%s'],
                ['%d']
            );
            audit_log('message_replied', ['msg_id' => $msg_id]);
        }

        wp_safe_redirect(add_query_arg('zdp_notice', 'reply_sent', wp_get_referer() ?: admin_url('admin.php?page=zdp-admin')));
        exit;
    }

    public function get_messages_for_user(int $user_id): array
    {
        global $wpdb;

        return $wpdb->get_results($wpdb->prepare(
            'SELECT * FROM ' . Installer::table('messages') . ' WHERE user_id = %d ORDER BY created_at DESC',
            $user_id
        ));
    }

    public function get_all_messages(int $limit = 100): array
    {
        global $wpdb;
        $table = Installer::table('messages');
        $usermeta = $wpdb->usermeta;

        return $wpdb->get_results($wpdb->prepare(
            "SELECT m.*, COALESCE(um_c.meta_value, um_z.meta_value) AS mobile
             FROM $table m
             LEFT JOIN $usermeta um_c ON um_c.user_id = m.user_id AND um_c.meta_key = 'cellphone'
             LEFT JOIN $usermeta um_z ON um_z.user_id = m.user_id AND um_z.meta_key = 'zdp_mobile'
             ORDER BY m.created_at DESC LIMIT %d",
            $limit
        ));
    }
}
