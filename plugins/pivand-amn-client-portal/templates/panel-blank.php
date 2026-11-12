<?php
/**
 * قالب حداقلی برای صفحه‌ی پنل مراجعان و صفحاتی که شورت‌کد نظرسنجی مستقل
 * دارند — بدون هدر/فوتر قالب فعال سایت. فقط wp_head()/wp_footer() نگه
 * داشته شده تا استایل/اسکریپت‌های enqueue‌شده (این افزونه و افزونه‌های
 * دیگر) و هوک‌های وابسته به آن‌ها همچنان درست کار کنند.
 *
 * انتخاب می‌شود از طریق فیلتر template_include در
 * Plugin::maybe_use_blank_template().
 */

if (!defined('ABSPATH')) {
    exit;
}

?><!DOCTYPE html>
<html <?php language_attributes(); ?> style="color-scheme: light;">
<head>
<meta charset="<?php bloginfo('charset'); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<?php
/* این قالب فقط برای پنل/نظرسنجی مستقل استفاده می‌شود، پس بر خلاف
   Plugin::print_color_scheme_meta() (که مشروط است تا روی کل سایت
   تحمیل نشود) اینجا همیشه و بدون شرط چاپ می‌شود — دفاع اول در برابر
   دارک‌مود اجباری، پیش از این‌که حتی panel.css لود شود. */
?>
<meta name="color-scheme" content="light">
<style>html,body{background:#ffffff;color-scheme:light;}</style>
<title><?php echo esc_html(wp_get_document_title()); ?></title>
<?php wp_head(); ?>
</head>
<body <?php body_class('zdp-panel-blank'); ?> style="background:#ffffff;">
<?php
while (have_posts()) :
    the_post();
    the_content();
endwhile;
?>
<?php wp_footer(); ?>
</body>
</html>
