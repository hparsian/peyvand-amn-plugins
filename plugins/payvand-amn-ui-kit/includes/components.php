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

/* ══════════════════════════════════════════════════════════════════════
   کامپوننت‌های پایه — فاز ۱۴ گام ۳
   select · textarea · switch · checkbox · radio-card · tabs · modal
   skeleton · progress · table-toolbar · toast-trigger
   همه با همان قواعد: escape کامل، شناسهٔ یکتا، بدون استایل درون‌خطی.
   ══════════════════════════════════════════════════════════════════════ */

if (!function_exists('pui_select')) {
    /** @param array{id?:string,name?:string,label?:string,options?:array<string,string>,value?:string,hint?:string,error?:string,required?:bool,placeholder?:string} $args */
    function pui_select(array $args): string
    {
        $id       = (string) ($args['id'] ?? '');
        $label    = (string) ($args['label'] ?? '');
        $value    = (string) ($args['value'] ?? '');
        $hint     = (string) ($args['hint'] ?? '');
        $error    = (string) ($args['error'] ?? '');
        $required = !empty($args['required']);
        $name     = (string) ($args['name'] ?? $id);
        $opts     = (array) ($args['options'] ?? []);
        $placeholder = (string) ($args['placeholder'] ?? '');

        if ($id === '') {
            $id = 'pui-' . wp_unique_id();
        }

        ob_start();
        ?>
        <div class="pui-field<?php echo $error !== '' ? ' pui-field--invalid' : ''; ?>">
            <?php if ($label !== '') : ?>
                <label class="pui-field__label" for="<?php echo esc_attr($id); ?>"><?php echo esc_html($label); ?><?php if ($required) : ?><span class="pui-field__req" aria-hidden="true">*</span><?php endif; ?></label>
            <?php endif; ?>
            <select class="pui-select" id="<?php echo esc_attr($id); ?>" name="<?php echo esc_attr($name); ?>" <?php echo $required ? 'required' : ''; ?><?php echo $error !== '' ? ' aria-invalid="true"' : ''; ?>>
                <?php if ($placeholder !== '') : ?><option value=""><?php echo esc_html($placeholder); ?></option><?php endif; ?>
                <?php foreach ($opts as $opt_value => $opt_label) : ?>
                    <option value="<?php echo esc_attr((string) $opt_value); ?>" <?php echo selected($value, (string) $opt_value, false); ?>><?php echo esc_html((string) $opt_label); ?></option>
                <?php endforeach; ?>
            </select>
            <?php if ($hint !== '') : ?><p class="pui-field__hint"><?php echo esc_html($hint); ?></p><?php endif; ?>
            <?php if ($error !== '') : ?><p class="pui-field__error" role="alert"><?php echo esc_html($error); ?></p><?php endif; ?>
        </div>
        <?php
        return (string) ob_get_clean();
    }
}

if (!function_exists('pui_textarea')) {
    /** @param array{id?:string,name?:string,label?:string,value?:string,rows?:int,hint?:string,error?:string,required?:bool} $args */
    function pui_textarea(array $args): string
    {
        $id    = (string) ($args['id'] ?? '');
        $label = (string) ($args['label'] ?? '');
        $value = (string) ($args['value'] ?? '');
        $hint  = (string) ($args['hint'] ?? '');
        $error = (string) ($args['error'] ?? '');
        $rows  = (int) ($args['rows'] ?? 4);
        $name  = (string) ($args['name'] ?? $id);
        $required = !empty($args['required']);

        if ($id === '') {
            $id = 'pui-' . wp_unique_id();
        }

        ob_start();
        ?>
        <div class="pui-field<?php echo $error !== '' ? ' pui-field--invalid' : ''; ?>">
            <?php if ($label !== '') : ?>
                <label class="pui-field__label" for="<?php echo esc_attr($id); ?>"><?php echo esc_html($label); ?><?php if ($required) : ?><span class="pui-field__req" aria-hidden="true">*</span><?php endif; ?></label>
            <?php endif; ?>
            <textarea class="pui-textarea" id="<?php echo esc_attr($id); ?>" name="<?php echo esc_attr($name); ?>" rows="<?php echo esc_attr((string) max(2, $rows)); ?>" <?php echo $required ? 'required' : ''; ?><?php echo $error !== '' ? ' aria-invalid="true"' : ''; ?>><?php echo esc_textarea($value); ?></textarea>
            <?php if ($hint !== '') : ?><p class="pui-field__hint"><?php echo esc_html($hint); ?></p><?php endif; ?>
            <?php if ($error !== '') : ?><p class="pui-field__error" role="alert"><?php echo esc_html($error); ?></p><?php endif; ?>
        </div>
        <?php
        return (string) ob_get_clean();
    }
}

if (!function_exists('pui_switch')) {
    /** @param array{id?:string,name?:string,label?:string,description?:string,checked?:bool,value?:string} $args */
    function pui_switch(array $args): string
    {
        $id      = (string) ($args['id'] ?? '');
        $name    = (string) ($args['name'] ?? $id);
        $label   = (string) ($args['label'] ?? '');
        $desc    = (string) ($args['description'] ?? '');
        $value   = (string) ($args['value'] ?? '1');
        $checked = !empty($args['checked']);

        if ($id === '') {
            $id = 'pui-' . wp_unique_id();
        }

        ob_start();
        ?>
        <div class="pui-switch-field">
            <label class="pui-switch" for="<?php echo esc_attr($id); ?>">
                <input type="checkbox" id="<?php echo esc_attr($id); ?>" name="<?php echo esc_attr($name); ?>" value="<?php echo esc_attr($value); ?>" <?php checked($checked); ?>>
                <span class="pui-switch__track" aria-hidden="true"><span class="pui-switch__thumb"></span></span>
                <span class="pui-switch__label"><?php echo esc_html($label); ?></span>
            </label>
            <?php if ($desc !== '') : ?><p class="pui-field__hint"><?php echo esc_html($desc); ?></p><?php endif; ?>
        </div>
        <?php
        return (string) ob_get_clean();
    }
}

if (!function_exists('pui_checkbox')) {
    /** @param array{id?:string,name?:string,label?:string,checked?:bool,value?:string} $args */
    function pui_checkbox(array $args): string
    {
        $id      = (string) ($args['id'] ?? '');
        $name    = (string) ($args['name'] ?? $id);
        $label   = (string) ($args['label'] ?? '');
        $value   = (string) ($args['value'] ?? '1');
        $checked = !empty($args['checked']);

        if ($id === '') {
            $id = 'pui-' . wp_unique_id();
        }

        return sprintf(
            '<label class="pui-check" for="%1$s"><input type="checkbox" id="%1$s" name="%2$s" value="%3$s"%4$s><span>%5$s</span></label>',
            esc_attr($id),
            esc_attr($name),
            esc_attr($value),
            $checked ? ' checked' : '',
            esc_html($label)
        );
    }
}

if (!function_exists('pui_radio_cards')) {
    /**
     * گروه انتخاب کارتی (جایگزین رادیوهای ساده در فرم‌های عمومی).
     *
     * @param array{name:string,options:array<string,array{label:string,icon?:string,desc?:string}>,value?:string,legend?:string,required?:bool} $args
     */
    function pui_radio_cards(array $args): string
    {
        $name    = (string) ($args['name'] ?? 'pui-choice');
        $value   = (string) ($args['value'] ?? '');
        $legend  = (string) ($args['legend'] ?? '');
        $options = (array) ($args['options'] ?? []);
        $req     = !empty($args['required']) ? ' required' : '';
        $group   = 'pui-rc-' . wp_unique_id();

        ob_start();
        ?>
        <fieldset class="pui-radio-cards" <?php echo $legend !== '' ? 'aria-label="' . esc_attr($legend) . '"' : ''; ?>>
            <?php if ($legend !== '') : ?><legend class="pui-field__label"><?php echo esc_html($legend); ?></legend><?php endif; ?>
            <?php foreach ($options as $opt_value => $opt) :
                $oid  = $group . '-' . sanitize_key((string) $opt_value);
                $icon = (string) ($opt['icon'] ?? '');
                $desc = (string) ($opt['desc'] ?? ''); ?>
                <label class="pui-radio-card" for="<?php echo esc_attr($oid); ?>">
                    <input type="radio" id="<?php echo esc_attr($oid); ?>" name="<?php echo esc_attr($name); ?>" value="<?php echo esc_attr((string) $opt_value); ?>" <?php echo checked($value, (string) $opt_value, false); ?><?php echo $req; ?>>
                    <span class="pui-radio-card__body">
                        <?php if ($icon !== '') : ?><span class="pui-radio-card__icon"><?php echo pui_icon($icon, ['size' => 18]); ?></span><?php endif; ?>
                        <span class="pui-radio-card__label"><?php echo esc_html((string) ($opt['label'] ?? $opt_value)); ?></span>
                        <?php if ($desc !== '') : ?><span class="pui-radio-card__desc"><?php echo esc_html($desc); ?></span><?php endif; ?>
                    </span>
                </label>
            <?php endforeach; ?>
        </fieldset>
        <?php
        return (string) ob_get_clean();
    }
}

if (!function_exists('pui_tabs')) {
    /**
     * تب‌های ARIA با پنل‌های آماده. رفتار (کلیک/کیبورد) را ui.js انجام می‌دهد.
     *
     * @param array<int,array{label:string,badge?:string,content?:string,id?:string}> $tabs
     * @param array{label?:string,class?:string,active?:int} $args
     */
    function pui_tabs(array $tabs, array $args = []): string
    {
        if (empty($tabs)) {
            return '';
        }

        $active = (int) ($args['active'] ?? 0);
        $active = ($active >= 0 && $active < count($tabs)) ? $active : 0;
        $label  = (string) ($args['label'] ?? 'تب‌ها');
        $class  = (string) ($args['class'] ?? '');
        $base   = 'pui-tab-' . wp_unique_id();

        ob_start();
        ?>
        <div class="pui-tabs-wrap <?php echo esc_attr($class); ?>">
            <div class="pui-tabs" role="tablist" aria-label="<?php echo esc_attr($label); ?>">
                <?php foreach ($tabs as $i => $tab) :
                    $tid = $base . '-tab-' . $i;
                    $pid = $base . '-panel-' . $i;
                    $selected = $i === $active; ?>
                    <button type="button" class="pui-tab" role="tab" id="<?php echo esc_attr($tid); ?>"
                        aria-controls="<?php echo esc_attr($pid); ?>" aria-selected="<?php echo $selected ? 'true' : 'false'; ?>"
                        tabindex="<?php echo $selected ? '0' : '-1'; ?>">
                        <?php echo esc_html((string) ($tab['label'] ?? '')); ?>
                        <?php if (!empty($tab['badge'])) : ?><span class="pui-tab__badge"><?php echo esc_html((string) $tab['badge']); ?></span><?php endif; ?>
                    </button>
                <?php endforeach; ?>
            </div>
            <?php foreach ($tabs as $i => $tab) : ?>
                <div class="pui-tabpanel" role="tabpanel" id="<?php echo esc_attr($base . '-panel-' . $i); ?>"
                    aria-labelledby="<?php echo esc_attr($base . '-tab-' . $i); ?>" <?php echo $i === $active ? '' : 'hidden'; ?>>
                    <?php echo isset($tab['content']) ? $tab['content'] : ''; // markup ساخته‌شده توسط فراخوان ?>
                </div>
            <?php endforeach; ?>
        </div>
        <?php
        return (string) ob_get_clean();
    }
}

if (!function_exists('pui_modal')) {
    /**
     * مدال native <dialog> با فوکوس طبیعی مرورگر و بسته‌شدن با Esc.
     *
     * @param array{id?:string,title?:string,body?:string,actions?:string,size?:string} $args
     */
    function pui_modal(array $args): string
    {
        $id      = (string) ($args['id'] ?? 'pui-modal-' . wp_unique_id());
        $title   = (string) ($args['title'] ?? '');
        $body    = (string) ($args['body'] ?? '');
        $actions = (string) ($args['actions'] ?? '');
        $size    = in_array(($args['size'] ?? ''), ['sm', 'lg'], true) ? ' pui-modal--' . $args['size'] : '';
        $title_id = $id . '-title';

        ob_start();
        ?>
        <dialog class="pui-modal<?php echo esc_attr($size); ?>" id="<?php echo esc_attr($id); ?>" <?php echo $title !== '' ? 'aria-labelledby="' . esc_attr($title_id) . '"' : ''; ?>>
            <?php if ($title !== '') : ?>
                <div class="pui-modal__head">
                    <h2 class="pui-modal__title" id="<?php echo esc_attr($title_id); ?>"><?php echo esc_html($title); ?></h2>
                    <button type="button" class="pui-btn pui-btn--quiet pui-btn--icon" data-pui-close aria-label="بستن"><?php echo pui_icon('x', ['size' => 18]); ?></button>
                </div>
            <?php endif; ?>
            <div class="pui-modal__body"><?php echo $body; ?></div>
            <?php if ($actions !== '') : ?><div class="pui-modal__foot"><?php echo $actions; ?></div><?php endif; ?>
        </dialog>
        <?php
        return (string) ob_get_clean();
    }
}

if (!function_exists('pui_skeleton')) {
    /**
     * اسکلتون بارگذاری. اندازه با کلاس انجام می‌شود (بدون استایل درون‌خطی).
     *
     * @param array{lines?:int,size?:string,width?:string,class?:string} $args
     */
    function pui_skeleton(array $args = []): string
    {
        $lines = max(1, (int) ($args['lines'] ?? 1));
        $size  = in_array(($args['size'] ?? ''), ['sm', 'lg', 'title'], true) ? ' pui-skeleton--' . $args['size'] : '';
        $width = in_array(($args['width'] ?? ''), ['60', '70', '85', 'half'], true) ? ' pui-skeleton--w' . $args['width'] : '';
        $class = (string) ($args['class'] ?? '');
        $out   = '';

        for ($i = 0; $i < $lines; $i++) {
            $mod = ($i === $lines - 1 && $lines > 1) ? ' ' . trim($width) : '';
            $out .= '<span class="pui-skeleton' . $size . $mod . ($class !== '' ? ' ' . esc_attr($class) : '') . '" aria-hidden="true"></span>';
        }

        return '<span class="pui-skeleton-stack" role="status" aria-live="polite"><span class="pui-sr-only">در حال بارگذاری…</span>' . $out . '</span>';
    }
}

if (!function_exists('pui_progress')) {
    /** @param array{label?:string,show_value?:bool,tone?:string} $args */
    function pui_progress(int $value, array $args = []): string
    {
        $value = max(0, min(100, $value));
        $label = (string) ($args['label'] ?? '');
        $show  = !array_key_exists('show_value', $args) || !empty($args['show_value']);
        $tone  = in_array(($args['tone'] ?? ''), ['success', 'warning', 'danger'], true) ? ' pui-progress--' . $args['tone'] : '';

        ob_start();
        ?>
        <div class="pui-progress-field">
            <?php if ($label !== '' || $show) : ?>
                <div class="pui-progress-field__head">
                    <?php if ($label !== '') : ?><span class="pui-progress-field__label"><?php echo esc_html($label); ?></span><?php endif; ?>
                    <?php if ($show) : ?><span class="pui-progress-field__value"><?php echo esc_html((string) $value); ?>٪</span><?php endif; ?>
                </div>
            <?php endif; ?>
            <div class="pui-progress<?php echo esc_attr($tone); ?>" role="progressbar" aria-valuenow="<?php echo esc_attr((string) $value); ?>" aria-valuemin="0" aria-valuemax="100" <?php echo $label !== '' ? 'aria-label="' . esc_attr($label) . '"' : ''; ?>>
                <div class="pui-progress__bar" data-pui-progress="<?php echo esc_attr((string) $value); ?>"></div>
            </div>
        </div>
        <?php
        return (string) ob_get_clean();
    }
}

if (!function_exists('pui_table_toolbar')) {
    /**
     * نوار ابزار جدول: جست‌وجو + فیلترها + اکشن‌ها.
     *
     * @param array{search_name?:string,search_value?:string,search_placeholder?:string,action?:string,hidden?:array<string,string>,filters?:string,actions?:string,label?:string} $args
     */
    function pui_table_toolbar(array $args = []): string
    {
        $name        = (string) ($args['search_name'] ?? 's');
        $value       = (string) ($args['search_value'] ?? '');
        $placeholder = (string) ($args['search_placeholder'] ?? 'جست‌وجو…');
        $action      = (string) ($args['action'] ?? '');
        $hidden      = (array) ($args['hidden'] ?? []);
        $filters     = (string) ($args['filters'] ?? '');
        $actions     = (string) ($args['actions'] ?? '');
        $label       = (string) ($args['label'] ?? 'جست‌وجو در جدول');

        ob_start();
        ?>
        <div class="pui-toolbar">
            <form class="pui-toolbar__search" method="get" role="search" <?php echo $action !== '' ? 'action="' . esc_url($action) . '"' : ''; ?>>
                <?php foreach ($hidden as $hk => $hv) : ?>
                    <input type="hidden" name="<?php echo esc_attr((string) $hk); ?>" value="<?php echo esc_attr((string) $hv); ?>">
                <?php endforeach; ?>
                <label class="pui-sr-only" for="<?php echo esc_attr('pui-search-' . wp_unique_id()); ?>"><?php echo esc_html($label); ?></label>
                <span class="pui-toolbar__icon" aria-hidden="true"><?php echo pui_icon('search', ['size' => 16]); ?></span>
                <input class="pui-input pui-toolbar__input" type="search" name="<?php echo esc_attr($name); ?>" value="<?php echo esc_attr($value); ?>" placeholder="<?php echo esc_attr($placeholder); ?>" aria-label="<?php echo esc_attr($label); ?>">
                <?php echo $filters; // markup فیلترهای ساخته‌شده توسط فراخوان ?>
                <button type="submit" class="pui-btn pui-btn--ghost pui-btn--sm">جست‌وجو</button>
            </form>
            <?php if ($actions !== '') : ?><div class="pui-toolbar__actions"><?php echo $actions; ?></div><?php endif; ?>
        </div>
        <?php
        return (string) ob_get_clean();
    }
}

if (!function_exists('pui_toast_trigger')) {
    /** دکمه‌ای که یک اعلان نمونه نشان می‌دهد (برای پیش‌نمایش و آزمون). */
    function pui_toast_trigger(string $message, string $label = 'نمایش اعلان', array $args = []): string
    {
        $variant = in_array(($args['variant'] ?? ''), ['primary', 'ghost', 'quiet'], true) ? ' pui-btn--' . $args['variant'] : ' pui-btn--ghost';

        return sprintf(
            '<button type="button" class="pui-btn%s" data-pui-toast="%s">%s</button>',
            $variant,
            esc_attr($message),
            esc_html($label)
        );
    }
}
