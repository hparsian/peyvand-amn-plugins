<?php
/**
 * Plugin Name: پیوند امن — کیت رابط کاربری
 * Plugin URI:  https://example.com/payvand-amn
 * Description: سیستم طراحی مشترک اکوسیستم پیوند امن: توکن‌های برند (شنی/کرم/قهوه‌ای/طلایی)، فونت وزیرمتن سلف‌هاست، کامپوننت‌های RTL و حالت تاریک اختیاری. هیچ استایلی به‌صورت سراسری بارگذاری نمی‌شود.
 * Version:     2.0.0
 * Author:      Payvand Amn
 * Text Domain: payvand-amn-ui-kit
 * Requires PHP: 7.4
 * Requires at least: 6.0
 *
 * این افزونه «کتابخانه» است، نه یک صفحهٔ کاربری مستقل: کلاس‌ها و توابع را در
 * اختیار بقیهٔ افزونه‌های خانواده می‌گذارد. اگر غیرفعال باشد، افزونه‌های
 * مصرف‌کننده به CSS فعلی خودشان fallback می‌کنند و هیچ صفحه‌ای سفید نمی‌شود.
 *
 * قرارداد عمومی (بعد از انتشار تغییر نمی‌کند):
 *   PUI::enqueue('auth'|'panel'|'admin'|'center')
 *   PUI::handle('tokens'|'components'|'shell')
 *   PUI::icon(string $name, array $args = []): string
 *   PUI::theme_attr(): string
 *   pui_alert(), pui_badge(), pui_stat(), pui_empty(), pui_field(), pui_card_open(), ...
 */

if (!defined('ABSPATH')) {
    exit;
}

define('PUI_VERSION', '2.0.0');
define('PUI_FILE', __FILE__);
define('PUI_DIR', plugin_dir_path(__FILE__));
define('PUI_URL', plugin_dir_url(__FILE__));

require_once PUI_DIR . 'includes/class-icons.php';
require_once PUI_DIR . 'includes/class-ui-kit.php';
require_once PUI_DIR . 'includes/components.php';
require_once PUI_DIR . 'includes/class-preview-page.php';

add_action('plugins_loaded', static function (): void {
    PUI::boot();

    if (is_admin()) {
        PUI_Preview_Page::boot();
    }
}, 5);
