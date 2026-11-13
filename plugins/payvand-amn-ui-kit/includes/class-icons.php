<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * آیکون‌های SVG inline (بدون CDN و بدون فونت آیکون).
 *
 * همه در شبکهٔ 24×24 و با stroke="currentColor" کشیده شده‌اند تا رنگ را از
 * متن بگیرند (کار می‌کند در light و dark، بدون فایل دوم). آیکون تزئینی
 * خودکار aria-hidden می‌گیرد؛ اگر $args['label'] بدهید، role="img" و
 * <title> اضافه می‌شود (برای آیکون‌های معنادار).
 */
final class PUI_Icons
{
    /** @return array<string,string> */
    private static function paths(): array
    {
        return [
            'home'        => '<path d="M4 10.5 12 4l8 6.5V20a1 1 0 0 1-1 1h-4v-6H9v6H5a1 1 0 0 1-1-1z"/>',
            'calendar'    => '<rect x="3.5" y="5" width="17" height="15.5" rx="2.5"/><path d="M8 3.5v3M16 3.5v3M3.5 10h17"/>',
            'clock'       => '<circle cx="12" cy="12" r="8.25"/><path d="M12 7.5V12l3 2"/>',
            'user'        => '<circle cx="12" cy="8.5" r="3.75"/><path d="M4.8 20c1.2-3.6 3.9-5.3 7.2-5.3s6 1.7 7.2 5.3"/>',
            'users'       => '<circle cx="9" cy="8.5" r="3.4"/><path d="M2.8 20c1-3.3 3.3-5 6.2-5s5.2 1.7 6.2 5"/><path d="M16 5.2a3.2 3.2 0 0 1 0 6.2M17.5 15.2c2 .6 3.3 2.2 3.7 4.8"/>',
            'file'        => '<path d="M14 3.5H7.5A1.5 1.5 0 0 0 6 5v14a1.5 1.5 0 0 0 1.5 1.5h9A1.5 1.5 0 0 0 18 19V7.5z"/><path d="M14 3.5V7.5H18M9 12h6M9 16h4"/>',
            'chart'       => '<path d="M4 19.5h16"/><rect x="5.5" y="11" width="3.4" height="6"/><rect x="10.3" y="7" width="3.4" height="10"/><rect x="15.1" y="13" width="3.4" height="4"/>',
            'bell'        => '<path d="M6.5 16.5V11a5.5 5.5 0 0 1 11 0v5.5l1.5 2H5z"/><path d="M10 20.5a2 2 0 0 0 4 0"/>',
            'message'     => '<path d="M20 15.5a2 2 0 0 1-2 2H9l-4.5 3v-3H6a2 2 0 0 1-2-2V6.5a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2z"/>',
            'settings'    => '<circle cx="12" cy="12" r="3"/><path d="M19.4 13.5a7.6 7.6 0 0 0 0-3l1.8-1.2-1.8-3.1-2.1.8a7.6 7.6 0 0 0-2.6-1.5L14.4 3h-4.8l-.3 2.5a7.6 7.6 0 0 0-2.6 1.5l-2.1-.8-1.8 3.1L4.6 10.5a7.6 7.6 0 0 0 0 3l-1.8 1.2 1.8 3.1 2.1-.8a7.6 7.6 0 0 0 2.6 1.5l.3 2.5h4.8l.3-2.5a7.6 7.6 0 0 0 2.6-1.5l2.1.8 1.8-3.1z"/>',
            'shield'      => '<path d="M12 3.5 19 6v5.5c0 4.2-2.8 7.6-7 9-4.2-1.4-7-4.8-7-9V6z"/><path d="m9 12 2 2 4-4"/>',
            'check'       => '<path d="m5 12.5 4.5 4.5L19 7.5"/>',
            'alert'       => '<path d="M12 4.5 21 20H3z"/><path d="M12 10v4.5M12 17.2v.3"/>',
            'x'           => '<path d="m6 6 12 12M18 6 6 18"/>',
            'plus'        => '<path d="M12 5v14M5 12h14"/>',
            'search'      => '<circle cx="11" cy="11" r="6.5"/><path d="m16 16 4 4"/>',
            'arrow-start' => '<path d="M19 12H5M11 6l-6 6 6 6"/>',
            'menu'        => '<path d="M4 7h16M4 12h16M4 17h16"/>',
            'logout'      => '<path d="M14 5.5H7A1.5 1.5 0 0 0 5.5 7v10A1.5 1.5 0 0 0 7 18.5h7"/><path d="M16 8.5 19.5 12 16 15.5M10 12h9"/>',
            'download'    => '<path d="M12 4v10M8 10.5 12 14.5l4-4M5 19h14"/>',
            'upload'      => '<path d="M12 15V4M8 7.5 12 3.5l4 4M5 19h14"/>',
            'link'        => '<path d="M9.5 14.5 14.5 9.5"/><path d="M7.5 11 5.7 12.8a3.8 3.8 0 0 0 5.4 5.4l1.8-1.8M16.5 13l1.8-1.8a3.8 3.8 0 0 0-5.4-5.4L11.1 7.6"/>',
            'phone'       => '<path d="M7 3.8 9.6 4l1.2 4-2 1.4a11.5 11.5 0 0 0 5.8 5.8l1.4-2 4 1.2.2 2.6c0 1.3-1 2.3-2.3 2.4C11.4 20 4 12.6 4.6 6.1 4.7 4.8 5.7 3.8 7 3.8z"/>',
            'mail'        => '<rect x="3.5" y="5.5" width="17" height="13" rx="2"/><path d="m4.5 7 7.5 6 7.5-6"/>',
            'heart'       => '<path d="M12 20s-7.5-4.3-7.5-9.3A4.2 4.2 0 0 1 12 7.8a4.2 4.2 0 0 1 7.5 2.9C19.5 15.7 12 20 12 20z"/>',
            'activity'    => '<path d="M3.5 12h3.2l2.3-6 3.6 12 2.4-6h5.5"/>',
            'layers'      => '<path d="m12 3.8 8 4.2-8 4.2-8-4.2z"/><path d="m4 12.2 8 4.2 8-4.2M4 16.2l8 4.2 8-4.2"/>',
            'sun'         => '<circle cx="12" cy="12" r="4"/><path d="M12 2.5v2M12 19.5v2M2.5 12h2M19.5 12h2M5.2 5.2l1.4 1.4M17.4 17.4l1.4 1.4M18.8 5.2l-1.4 1.4M6.6 17.4l-1.4 1.4"/>',
            'moon'        => '<path d="M20 14.2A8.2 8.2 0 0 1 9.8 4a8.4 8.4 0 1 0 10.2 10.2z"/>',
            'wallet'      => '<rect x="3.5" y="6.5" width="17" height="12" rx="2.5"/><path d="M3.5 10.5h17M16.5 14.5h1.5"/>',
            'clipboard'   => '<rect x="6" y="4.5" width="12" height="16" rx="2"/><path d="M9.5 4.5V3.2h5v1.3M9.5 10h5M9.5 13.5h5M9.5 17h3"/>',
            'star'        => '<path d="m12 4 2.4 4.9 5.4.8-3.9 3.8.9 5.4-4.8-2.6-4.8 2.6.9-5.4L4.2 9.7l5.4-.8z"/>',
            'lock'        => '<rect x="4.5" y="10.5" width="15" height="9.5" rx="2.2"/><path d="M8 10.5V8a4 4 0 0 1 8 0v2.5"/>',
            'refresh'     => '<path d="M19.5 12a7.5 7.5 0 1 1-2.2-5.3"/><path d="M19.5 4.5V9h-4.5"/>',
            'building'    => '<path d="M4.5 20.5V5.2a1.2 1.2 0 0 1 1.2-1.2h7.6a1.2 1.2 0 0 1 1.2 1.2v15.3"/><path d="M14.5 9.5h4.3a1.2 1.2 0 0 1 1.2 1.2v9.8M3 20.5h18"/><path d="M7.5 7.5h3M7.5 11h3M7.5 14.5h3M16.8 13h1M16.8 16.5h1"/>',
            'monitor'     => '<rect x="3" y="4.5" width="18" height="12" rx="2"/><path d="M9 20.5h6M12 16.5v4"/>',
            'inbox'       => '<path d="M4 13.5 6.4 5.4A1.5 1.5 0 0 1 7.8 4.3h8.4a1.5 1.5 0 0 1 1.4 1.1L20 13.5V18a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2z"/><path d="M4 13.5h4l1 2.5h6l1-2.5h4"/>',
        ];
    }

    public static function render(string $name, array $args = []): string
    {
        $paths = self::paths();

        if (!isset($paths[$name])) {
            return '';
        }

        $size    = isset($args['size']) ? (int) $args['size'] : 20;
        $class   = isset($args['class']) ? ' ' . preg_replace('/[^A-Za-z0-9 _-]/', '', (string) $args['class']) : '';
        $label   = isset($args['label']) ? (string) $args['label'] : '';
        $stroke  = isset($args['stroke']) ? (float) $args['stroke'] : 1.7;

        $a11y = $label !== ''
            ? ' role="img" aria-label="' . esc_attr($label) . '"'
            : ' aria-hidden="true" focusable="false"';

        return sprintf(
            '<svg class="pui-icon%s" width="%d" height="%d" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="%s" stroke-linecap="round" stroke-linejoin="round"%s>%s</svg>',
            $class,
            $size,
            $size,
            rtrim(rtrim(number_format($stroke, 2, '.', ''), '0'), '.'),
            $a11y,
            $paths[$name]
        );
    }

    /** @return string[] */
    public static function names(): array
    {
        return array_keys(self::paths());
    }
}
