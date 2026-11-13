<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * توابع قالب کامپوننت‌ها. همه خروجی را escape می‌کنند و می‌توانند در هر
 * افزونه‌ای استفاده شوند:
 *
 *   pui_card_open(['title' => '...', 'icon' => 'file']);
 *   ... محتوا ...
 *   pui_card_close();
 *
 * اگر UI Kit غیرفعال باشد این توابع وجود ندارند؛ افزونه‌های مصرف‌کننده باید
 * با function_exists() گارد بزنند (الگوی fallback).
 */

if (!function_exists('pui_icon')) {
    function pui_icon(string $name, array $args = []): string
    {
        return class_exists('PUI_Icons') ? PUI_Icons::render($name, $args) : '';
    }
}

if (!function_exists('pui_badge')) {
    function pui_badge(string $label, string $tone = 'neutral', array $args = []): string
    {
        $tones = ['neutral', 'success', 'warning', 'danger', 'info', 'accent'];
        $tone  = in_array($tone, $tones, true) ? $tone : 'neutral';
        $icon  = isset($args['icon']) ? pui_icon((string) $args['icon'], ['size' => 14]) : '';

        return sprintf(
            '<span class="pui-badge pui-badge--%s">%s<span>%s</span></span>',
            esc_attr($tone),
            $icon,
            esc_html($label)
        );
    }
}

if (!function_exists('pui_alert')) {
    function pui_alert(string $type, string $text, array $args = []): string
    {
        $types = ['info', 'success', 'warning', 'danger'];
        $type  = in_array($type, $types, true) ? $type : 'info';
        $icons = ['info' => 'bell', 'success' => 'check', 'warning' => 'alert', 'danger' => 'alert'];
        $live  = $type === 'danger' ? 'assertive' : 'polite';
        $title = isset($args['title']) ? (string) $args['title'] : '';

        ob_start();
        ?>
        <div class="pui-alert pui-alert--<?php echo esc_attr($type); ?>" role="<?php echo $type === 'danger' ? 'alert' : 'status'; ?>" aria-live="<?php echo esc_attr($live); ?>">
            <span class="pui-alert__icon"><?php echo pui_icon($icons[$type], ['size' => 18]); ?></span>
            <div class="pui-alert__body">
                <?php if ($title !== '') : ?><strong class="pui-alert__title"><?php echo esc_html($title); ?></strong><?php endif; ?>
                <p class="pui-alert__text"><?php echo esc_html($text); ?></p>
            </div>
        </div>
        <?php
        return (string) ob_get_clean();
    }
}

if (!function_exists('pui_stat')) {
    function pui_stat(array $args): string
    {
        $label  = (string) ($args['label'] ?? '');
        $value  = (string) ($args['value'] ?? '');
        $meta   = (string) ($args['meta'] ?? '');
        $icon   = (string) ($args['icon'] ?? 'activity');
        $tone   = (string) ($args['tone'] ?? '');
        $live   = !empty($args['live']) ? ' aria-live="polite"' : '';

        ob_start();
        ?>
        <div class="pui-stat<?php echo $tone ? ' pui-stat--' . esc_attr($tone) : ''; ?>">
            <span class="pui-stat__icon"><?php echo pui_icon($icon, ['size' => 18]); ?></span>
            <span class="pui-stat__label"><?php echo esc_html($label); ?></span>
            <strong class="pui-stat__value"<?php echo $live; ?>><?php echo esc_html($value); ?></strong>
            <?php if ($meta !== '') : ?><span class="pui-stat__meta"><?php echo esc_html($meta); ?></span><?php endif; ?>
        </div>
        <?php
        return (string) ob_get_clean();
    }
}

if (!function_exists('pui_empty')) {
    function pui_empty(string $title, string $text = '', array $args = []): string
    {
        $icon = (string) ($args['icon'] ?? 'layers');

        ob_start();
        ?>
        <div class="pui-empty">
            <span class="pui-empty__icon"><?php echo pui_icon($icon, ['size' => 26]); ?></span>
            <strong class="pui-empty__title"><?php echo esc_html($title); ?></strong>
            <?php if ($text !== '') : ?><p class="pui-empty__text"><?php echo esc_html($text); ?></p><?php endif; ?>
            <?php if (!empty($args['action'])) : ?>
                <div class="pui-empty__action"><?php echo $args['action']; // markup ساخته‌شده توسط فراخوان ?></div>
            <?php endif; ?>
        </div>
        <?php
        return (string) ob_get_clean();
    }
}

if (!function_exists('pui_field')) {
    function pui_field(array $args): string
    {
        $id       = (string) ($args['id'] ?? '');
        $label    = (string) ($args['label'] ?? '');
        $type     = (string) ($args['type'] ?? 'text');
        $value    = (string) ($args['value'] ?? '');
        $hint     = (string) ($args['hint'] ?? '');
        $error    = (string) ($args['error'] ?? '');
        $required = !empty($args['required']);
        $name     = (string) ($args['name'] ?? $id);
        $extra    = isset($args['attrs']) ? (string) $args['attrs'] : '';

        if ($id === '') {
            $id = 'pui-' . wp_unique_id();
        }

        $describedby = trim(($hint !== '' ? ' ' . $id . '-hint' : '') . ($error !== '' ? ' ' . $id . '-error' : ''));

        ob_start();
        ?>
        <div class="pui-field<?php echo $error !== '' ? ' pui-field--invalid' : ''; ?>">
            <label class="pui-field__label" for="<?php echo esc_attr($id); ?>">
                <?php echo esc_html($label); ?><?php if ($required) : ?><span class="pui-field__req" aria-hidden="true">*</span><?php endif; ?>
            </label>
            <input
                class="pui-input"
                type="<?php echo esc_attr($type); ?>"
                id="<?php echo esc_attr($id); ?>"
                name="<?php echo esc_attr($name); ?>"
                value="<?php echo esc_attr($value); ?>"
                <?php echo $required ? 'required' : ''; ?>
                <?php echo $describedby !== '' ? ' aria-describedby="' . esc_attr(trim($describedby)) . '"' : ''; ?>
                <?php echo $error !== '' ? ' aria-invalid="true"' : ''; ?>
                <?php echo $extra; ?>
            >
            <?php if ($hint !== '') : ?><p class="pui-field__hint" id="<?php echo esc_attr($id . '-hint'); ?>"><?php echo esc_html($hint); ?></p><?php endif; ?>
            <?php if ($error !== '') : ?><p class="pui-field__error" id="<?php echo esc_attr($id . '-error'); ?>" role="alert"><?php echo esc_html($error); ?></p><?php endif; ?>
        </div>
        <?php
        return (string) ob_get_clean();
    }
}

if (!function_exists('pui_card_open')) {
    /** @param array{title?:string,subtitle?:string,icon?:string,class?:string,actions?:string} $args */
    function pui_card_open(array $args = []): string
    {
        $title    = (string) ($args['title'] ?? '');
        $subtitle = (string) ($args['subtitle'] ?? '');
        $icon     = (string) ($args['icon'] ?? '');
        $class    = (string) ($args['class'] ?? '');
        $actions  = (string) ($args['actions'] ?? '');
        $id       = 'pui-card-' . wp_unique_id();

        ob_start();
        ?>
        <section class="pui-card <?php echo esc_attr($class); ?>" <?php echo $title !== '' ? 'aria-labelledby="' . esc_attr($id . '-title') . '"' : ''; ?>>
            <?php if ($title !== '' || $actions !== '') : ?>
            <header class="pui-card__head">
                <div class="pui-card__heading">
                    <?php if ($icon !== '') : ?><span class="pui-card__icon"><?php echo pui_icon($icon, ['size' => 18]); ?></span><?php endif; ?>
                    <div>
                        <?php if ($title !== '') : ?><h2 class="pui-card__title" id="<?php echo esc_attr($id . '-title'); ?>"><?php echo esc_html($title); ?></h2><?php endif; ?>
                        <?php if ($subtitle !== '') : ?><p class="pui-card__subtitle"><?php echo esc_html($subtitle); ?></p><?php endif; ?>
                    </div>
                </div>
                <?php if ($actions !== '') : ?><div class="pui-card__actions"><?php echo $actions; ?></div><?php endif; ?>
            </header>
            <?php endif; ?>
            <div class="pui-card__body">
        <?php
        return (string) ob_get_clean();
    }

    function pui_card_close(): string
    {
        return '</div></section>';
    }
}

if (!function_exists('pui_table_open')) {
    /**
     * @param array<int,array{label:string,class?:string}> $columns
     * @param array{caption?:string,class?:string}         $args
     */
    function pui_table_open(array $columns, array $args = []): string
    {
        $caption = (string) ($args['caption'] ?? '');
        $class   = (string) ($args['class'] ?? '');

        ob_start();
        ?>
        <div class="pui-table-wrap">
            <table class="pui-table <?php echo esc_attr($class); ?>">
                <?php if ($caption !== '') : ?><caption class="pui-table__caption"><?php echo esc_html($caption); ?></caption><?php endif; ?>
                <thead>
                    <tr>
                        <?php foreach ($columns as $col) : ?>
                            <th scope="col" class="<?php echo esc_attr((string) ($col['class'] ?? '')); ?>"><?php echo esc_html((string) ($col['label'] ?? '')); ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
        <?php
        return (string) ob_get_clean();
    }

    function pui_table_close(): string
    {
        return '</tbody></table></div>';
    }
}

if (!function_exists('pui_steps')) {
    /** @param array<int,string> $steps */
    function pui_steps(array $steps, int $current = 0): string
    {
        $total = max(1, count($steps));

        ob_start();
        ?>
        <ol class="pui-steps" aria-label="مراحل">
            <?php foreach ($steps as $i => $label) :
                $state = $i < $current ? 'done' : ($i === $current ? 'current' : 'todo'); ?>
                <li class="pui-step pui-step--<?php echo esc_attr($state); ?>" <?php echo $i === $current ? 'aria-current="step"' : ''; ?>>
                    <span class="pui-step__dot" aria-hidden="true"><?php echo $i < $current ? pui_icon('check', ['size' => 14]) : (string) ($i + 1); ?></span>
                    <span class="pui-step__label"><?php echo esc_html($label); ?></span>
                </li>
            <?php endforeach; ?>
        </ol>
        <p class="pui-sr-only">مرحلهٔ <?php echo (int) ($current + 1); ?> از <?php echo (int) $total; ?></p>
        <?php
        return (string) ob_get_clean();
    }
}
