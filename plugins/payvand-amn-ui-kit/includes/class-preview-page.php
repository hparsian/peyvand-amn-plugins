<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * صفحهٔ پیش‌نمایش کیت (فقط مدیر): مرجع زندهٔ توکن‌ها، کامپوننت‌ها و طرح اولیهٔ
 * چهار محیط. این صفحه هیچ داده‌ای تغییر نمی‌دهد و هیچ چیز را روی سایت
 * نمایش نمی‌دهد.
 */
final class PUI_Preview_Page
{
    public const SLUG = 'pui-kit';

    public static function boot(): void
    {
        add_action('admin_menu', [__CLASS__, 'register']);
        add_action('admin_enqueue_scripts', [__CLASS__, 'assets']);
        add_action('admin_head', [__CLASS__, 'early_theme']);
        add_filter('admin_body_class', [__CLASS__, 'body_class']);
    }

    public static function register(): void
    {
        add_submenu_page(
            'tools.php',
            'کیت رابط کاربری پیوند امن',
            'کیت رابط کاربری',
            'manage_options',
            self::SLUG,
            [__CLASS__, 'render']
        );
    }

    private static function is_our_page(): bool
    {
        if (!function_exists('get_current_screen')) {
            return false;
        }
        $screen = get_current_screen();

        return $screen && strpos((string) $screen->id, self::SLUG) !== false;
    }

    /** کلاس بدنه برای پرکردن ناحیهٔ محتوای پیشخوان با زمینهٔ کیت. */
    public static function body_class($classes)
    {
        if (self::is_our_page()) {
            $classes .= ' pui-admin-page';
        }

        return $classes;
    }

    public static function assets(): void
    {
        if (!self::is_our_page()) {
            return;
        }

        PUI::enqueue_shell('admin');
    }

    /** جلوگیری از پرش رنگ: attribute پیش از رندر اعمال می‌شود. */
    public static function early_theme(): void
    {
        if (!self::is_our_page()) {
            return;
        }

        $attr = esc_js(PUI::theme_attr());
        echo '<script>(function(){try{var s=localStorage.getItem("pui-theme")||"' . $attr . '";'
            . 'var d=document.documentElement;var r=s==="auto"?(window.matchMedia("(prefers-color-scheme: dark)").matches?"dark":"light"):s;'
            . 'd.setAttribute("data-pui-theme",r==="dark"?"dark":"light");d.setAttribute("data-pui-theme-choice",s);}catch(e){}})();</script>';
    }

    public static function render(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die('دسترسی غیرمجاز.', 'دسترسی غیرمجاز', ['response' => 403]);
        }

        $theme_choice = PUI::theme_attr();
        ?>
        <div class="pui-shell pui-admin" dir="rtl">
            <div class="pui-page-head">
                <div>
                    <h1 class="pui-page-head__title">کیت رابط کاربری پیوند امن</h1>
                    <p class="pui-page-head__sub">نسخه <?php echo esc_html(PUI_VERSION); ?> — مرجع زندهٔ توکن‌ها، کامپوننت‌ها و طرح اولیهٔ چهار محیط. این صفحه هیچ چیزی را در سایت تغییر نمی‌دهد.</p>
                </div>
                <div class="pui-page-head__actions">
                    <button type="button" class="pui-btn pui-btn--ghost" data-pui-theme-toggle aria-pressed="false">
                        <?php echo pui_icon('moon', ['size' => 16]); ?><span>تغییر حالت رنگ</span>
                    </button>
                </div>
            </div>

            <?php echo pui_alert('info', 'حالت رنگ پیش‌فرض «' . $theme_choice . '» است و انتخاب کاربر در همین مرورگر ذخیره می‌شود. انتخاب روی کل سایت اعمال نمی‌شود؛ فقط نواحی کیت.', ['title' => 'یادآوری']); ?>

            <?php self::section_tokens(); ?>
            <?php self::section_components(); ?>
            <?php self::section_auth(); ?>
            <?php self::section_panel(); ?>
            <?php self::section_therapist(); ?>
            <?php self::section_center(); ?>
        </div>
        <?php
    }

    /* ─────────────────────────────────────────────── توکن‌ها */
    private static function section_tokens(): void
    {
        $colors = [
            ['نام', 'Light', 'Dark'],
            ['پس‌زمینه', '#F4F0E8', '#2A2521'],
            ['سطح', '#FFFFFF', '#332D28'],
            ['مرز', '#B8ADA0', '#4A423A'],
            ['متن', '#51483F', '#F4F0E8'],
            ['متن کم‌رنگ', '#6B5F52', '#C9BFB2'],
            ['تأکید (طلایی)', '#C8B48A', '#D8C49A'],
            ['موفق', '#3F6B4F', '#86C6A2'],
            ['هشدار', '#8A5A18', '#E2B36B'],
            ['خطر', '#9B3A2E', '#E79A8C'],
        ];

        echo pui_card_open(['title' => 'پالت برند', 'subtitle' => 'اندازه‌گیری کنتراست: متن روی کرم ۷.۸۷ (AAA)، دکمهٔ اصلی ۷.۸۷، طلایی روی قهوه‌ای ۴.۴۱ (فقط متن بزرگ)', 'icon' => 'layers']);
        echo pui_table_open([
            ['label' => 'نقش'],
            ['label' => 'Light'],
            ['label' => 'Dark'],
        ]);
        foreach (array_slice($colors, 1) as $row) {
            echo '<tr>';
            echo '<th scope="row">' . esc_html($row[0]) . '</th>';
            echo '<td dir="ltr"><span class="pui-row"><span style="inline-size:18px;block-size:18px;border-radius:5px;border:1px solid var(--pui-border);background:' . esc_attr($row[1]) . '"></span><code>' . esc_html($row[1]) . '</code></span></td>';
            echo '<td dir="ltr"><span class="pui-row"><span style="inline-size:18px;block-size:18px;border-radius:5px;border:1px solid var(--pui-border);background:' . esc_attr($row[2]) . '"></span><code>' . esc_html($row[2]) . '</code></span></td>';
            echo '</tr>';
        }
        echo pui_table_close();
        echo pui_card_close();

        echo '<div class="pui-grid pui-grid--2">';
        echo pui_card_open(['title' => 'مقیاس تایپوگرافی', 'icon' => 'file']);
        foreach ([['2xl', '۱٬۸۷۵rem — تیتر صفحه'], ['xl', '۱٬۵rem — تیتر کارت'], ['lg', '۱٬۲۵rem — تیتر بخش'], ['md', '۱٬۰۶rem — متن برجسته'], ['base', '۰٬۹۳۷۵rem — متن اصلی (۱۵px)'], ['sm', '۰٬۸۱۲۵rem — متن ثانویه'], ['xs', '۰٬۷۵rem — برچسب']] as $t) {
            echo '<p style="font-size:var(--pui-fs-' . esc_attr($t[0]) . ');margin-block-end:6px">' . esc_html($t[1]) . '</p>';
        }
        echo pui_card_close();

        echo pui_card_open(['title' => 'فونت و آیکون', 'icon' => 'star']);
        echo '<p style="font-weight:700;font-size:var(--pui-fs-lg)">وزیرمتن — سلف‌هاست، ۴ وزن</p>';
        echo '<p class="pui-muted">Regular 400 · Medium 500 · SemiBold 600 · Bold 700</p>';
        echo '<hr class="pui-divider">';
        echo '<div class="pui-row" style="gap:12px;font-size:22px">';
        foreach (PUI_Icons::names() as $name) {
            echo pui_icon($name, ['size' => 22, 'label' => $name]);
        }
        echo '</div>';
        echo pui_card_close();
        echo '</div>';
    }

    /* ─────────────────────────────────────────── کامپوننت‌ها */
    private static function section_components(): void
    {
        echo '<h2 style="font-size:var(--pui-fs-lg);margin-block:8px 0">کامپوننت‌ها</h2>';
        echo '<div class="pui-grid pui-grid--2">';

        echo pui_card_open(['title' => 'دکمه‌ها', 'icon' => 'star']);
        echo '<div class="pui-row">';
        echo '<button type="button" class="pui-btn pui-btn--primary">دکمهٔ اصلی</button>';
        echo '<button type="button" class="pui-btn pui-btn--ghost">دکمهٔ ثانویه</button>';
        echo '<button type="button" class="pui-btn pui-btn--quiet">آرام</button>';
        echo '<button type="button" class="pui-btn pui-btn--danger">حذف</button>';
        echo '<button type="button" class="pui-btn pui-btn--icon pui-btn--ghost" aria-label="جست‌وجو">' . pui_icon('search', ['size' => 18]) . '</button>';
        echo '<button type="button" class="pui-btn pui-btn--primary" disabled>غیرفعال</button>';
        echo '</div>';
        echo pui_card_close();

        echo pui_card_open(['title' => 'بج‌ها و هشدارها', 'icon' => 'shield']);
        echo '<div class="pui-row" style="margin-block-end:12px">';
        foreach (['neutral' => 'خنثی', 'success' => 'موفق', 'warning' => 'هشدار', 'danger' => 'خطا', 'info' => 'اطلاع', 'accent' => 'برجسته'] as $tone => $label) {
            echo pui_badge($label, $tone);
        }
        echo '</div>';
        echo pui_alert('success', 'پرونده با موفقیت ذخیره شد.', ['title' => 'انجام شد']);
        echo '<div style="block-size:8px"></div>';
        echo pui_alert('warning', 'کلید رمزنگاری هنوز تنظیم نشده است.', ['title' => 'توجه']);
        echo pui_card_close();

        echo pui_card_open(['title' => 'فرم', 'icon' => 'clipboard']);
        echo '<div class="pui-stack">';
        echo pui_field(['id' => 'pui-demo-mobile', 'label' => 'شماره موبایل', 'type' => 'tel', 'hint' => 'کد تایید به این شماره ارسال می‌شود.', 'required' => true, 'value' => '']);
        echo pui_field(['id' => 'pui-demo-error', 'label' => 'کد تایید', 'type' => 'text', 'error' => 'کد وارد شده صحیح نیست. ۴ تلاش دیگر باقی مانده است.', 'value' => '۱۲۳۴۵']);
        echo '<label class="pui-check"><input type="checkbox" checked><span>مرا به خاطر بسپار (تا ۳۰ روز)</span></label>';
        echo '<button type="button" class="pui-btn pui-btn--primary pui-btn--block">ارسال کد تایید</button>';
        echo '</div>';
        echo pui_card_close();

        echo pui_card_open(['title' => 'کارت‌های آماری', 'icon' => 'chart']);
        echo '<div class="pui-grid pui-grid--stats">';
        echo pui_stat(['label' => 'نوبت بعدی', 'value' => 'مشاورهٔ فردی', 'meta' => '۱۴۰۵/۰۸/۲۱ — ۱۶:۳۰', 'icon' => 'calendar', 'tone' => 'accent']);
        echo pui_stat(['label' => 'آزمون‌های در انتظار', 'value' => '۲', 'meta' => 'از درمانگر', 'icon' => 'file']);
        echo pui_stat(['label' => 'پیام‌های ثبت‌شده', 'value' => '۵', 'meta' => 'بدون پاسخ', 'icon' => 'message']);
        echo '</div>';
        echo pui_card_close();

        echo pui_card_open(['title' => 'جدول (ریسپانسیو)', 'subtitle' => 'زیر ۷۶۸px به کارت تبدیل می‌شود', 'icon' => 'layers']);
        echo pui_table_open([
            ['label' => 'تاریخ'], ['label' => 'مراجع'], ['label' => 'وضعیت'], ['label' => 'اقدام', 'class' => 'pui-table__num'],
        ], ['class' => 'pui-table--cards']);
        $rows = [
            ['۱۴۰۵/۰۸/۲۱', 'مراجع نمونه ۱', 'success', 'تایید شده'],
            ['۱۴۰۵/۰۸/۲۲', 'مراجع نمونه ۲', 'warning', 'در انتظار'],
            ['۱۴۰۵/۰۸/۲۳', 'مراجع نمونه ۳', 'danger', 'لغو شده'],
        ];
        foreach ($rows as $r) {
            echo '<tr>';
            echo '<td data-label="تاریخ">' . esc_html($r[0]) . '</td>';
            echo '<td data-label="مراجع">' . esc_html($r[1]) . '</td>';
            echo '<td data-label="وضعیت">' . pui_badge($r[3], $r[2]) . '</td>';
            echo '<td data-label="اقدام"><div class="pui-table__actions"><button type="button" class="pui-btn pui-btn--sm pui-btn--ghost">باز کردن</button></div></td>';
            echo '</tr>';
        }
        echo pui_table_close();
        echo pui_card_close();

        echo pui_card_open(['title' => 'تب‌ها، مراحل و تایم‌لاین', 'icon' => 'layers']);
        ?>
        <div class="pui-tabs">
            <button type="button" class="pui-tab" role="tab" id="pui-demo-tab1" aria-controls="pui-demo-panel1" aria-selected="true">نوبت‌ها <span class="pui-tab__badge">۳</span></button>
            <button type="button" class="pui-tab" role="tab" id="pui-demo-tab2" aria-controls="pui-demo-panel2" aria-selected="false">آزمون‌ها</button>
            <button type="button" class="pui-tab" role="tab" id="pui-demo-tab3" aria-controls="pui-demo-panel3" aria-selected="false">پیام‌ها</button>
        </div>
        <div class="pui-tabpanel" id="pui-demo-panel1" role="tabpanel" aria-labelledby="pui-demo-tab1">
            <p class="pui-muted">محتوای تب نوبت‌ها (نمایشی).</p>
        </div>
        <div class="pui-tabpanel" id="pui-demo-panel2" role="tabpanel" aria-labelledby="pui-demo-tab2" hidden>
            <p class="pui-muted">محتوای تب آزمون‌ها (نمایشی).</p>
        </div>
        <div class="pui-tabpanel" id="pui-demo-panel3" role="tabpanel" aria-labelledby="pui-demo-tab3" hidden>
            <p class="pui-muted">محتوای تب پیام‌ها (نمایشی).</p>
        </div>
        <hr class="pui-divider">
        <?php
        echo pui_steps(['شماره موبایل', 'کد تایید', 'ورود به پنل'], 1);
        echo '<hr class="pui-divider">';
        echo '<ol class="pui-timeline">';
        echo '<li class="pui-timeline__item"><span class="pui-timeline__dot"></span><p class="pui-timeline__title">جلسهٔ اول — ارزیابی اولیه</p><p class="pui-timeline__meta">۱۴۰۵/۰۸/۱۴ · درمانگر: نمونه</p></li>';
        echo '<li class="pui-timeline__item"><span class="pui-timeline__dot"></span><p class="pui-timeline__title">ثبت یادداشت جلسه</p><p class="pui-timeline__meta">۱۴۰۵/۰۸/۱۵ · رمزنگاری‌شده</p></li>';
        echo '</ol>';
        echo pui_card_close();

        echo pui_card_open(['title' => 'حالت‌های خالی، بارگذاری و پیشرفت', 'icon' => 'activity']);
        echo pui_empty('هنوز پیامی ثبت نشده', 'اولین پیام را از دکمهٔ بالا بفرستید.', ['icon' => 'message']);
        echo '<div style="block-size:12px"></div>';
        echo '<div class="pui-skeleton" style="block-size:14px;margin-block-end:8px"></div>';
        echo '<div class="pui-skeleton" style="block-size:14px;inline-size:70%"></div>';
        echo '<hr class="pui-divider">';
        echo '<p class="pui-muted" style="margin-block-end:6px">تکمیل پرونده</p>';
        echo '<div class="pui-progress" role="progressbar" aria-valuenow="65" aria-valuemin="0" aria-valuemax="100"><div class="pui-progress__bar" style="inline-size:65%"></div></div>';
        echo pui_card_close();

        echo pui_card_open(['title' => 'مدال و اعلان', 'icon' => 'message']);
        echo '<div class="pui-row">';
        echo '<button type="button" class="pui-btn pui-btn--ghost" data-pui-open="pui-demo-modal">باز کردن مدال</button>';
        echo '<button type="button" class="pui-btn pui-btn--primary" onclick="window.PUI&&window.PUI.toast(\'یک اعلان نمونه\')">نمایش اعلان</button>';
        echo '</div>';
        echo '<dialog class="pui-modal" id="pui-demo-modal" aria-labelledby="pui-demo-modal-title">';
        echo '<div class="pui-modal__head"><h2 id="pui-demo-modal-title" style="font-size:var(--pui-fs-md)">تایید عملیات</h2><button type="button" class="pui-btn pui-btn--quiet pui-btn--icon" data-pui-close aria-label="بستن">' . pui_icon('x', ['size' => 18]) . '</button></div>';
        echo '<div class="pui-modal__body"><p class="pui-muted">این یک مدال نمایشی است؛ هیچ عملیاتی انجام نمی‌شود. Esc هم آن را می‌بندد.</p></div>';
        echo '<div class="pui-modal__foot"><button type="button" class="pui-btn pui-btn--ghost" data-pui-close>انصراف</button><button type="button" class="pui-btn pui-btn--primary" data-pui-close>تایید</button></div>';
        echo '</dialog>';
        echo pui_card_close();

        echo '</div>';
    }

    /* ──────────────────────────────────────────── صفحهٔ ورود */
    private static function section_auth(): void
    {
        echo '<h2 style="font-size:var(--pui-fs-lg);margin-block:8px 0">طرح اولیه ۱ — ورود مراجع</h2>';
        echo pui_alert('info', 'طرح اولیه است: فرم نمایشی است و به سیستم ورود واقعی وصل نیست. منطق OTP در گام ۲ با حفظ کامل کد فعلی اعمال می‌شود.', ['title' => 'طرح اولیه']);
        ?>
        <div class="pui-auth" style="min-block-size:auto;border:1px solid var(--pui-border-soft);border-radius:var(--pui-r-xl);overflow:hidden">
            <div class="pui-auth__brand">
                <div class="pui-auth__brand-inner">
                    <div class="pui-auth__logo">
                        <span class="pui-auth__mark"><?php echo pui_icon('link', ['size' => 22]); ?></span>
                        <span>پیوند امن</span>
                    </div>
                    <div>
                        <p class="pui-auth__headline">فضایی آرام برای پیگیری درمان</p>
                        <p class="pui-auth__lead">نوبت‌ها، آزمون‌ها و پیام‌های شما در یک جای امن و خصوصی.</p>
                    </div>
                    <ul class="pui-auth__points">
                        <li class="pui-auth__point"><?php echo pui_icon('shield', ['size' => 18]); ?><span>دادهٔ بالینی رمزنگاری‌شده</span></li>
                        <li class="pui-auth__point"><?php echo pui_icon('user', ['size' => 18]); ?><span>دسترسی فقط برای خود شما</span></li>
                        <li class="pui-auth__point"><?php echo pui_icon('phone', ['size' => 18]); ?><span>ورود سریع با کد پیامکی</span></li>
                    </ul>
                </div>
            </div>
            <div class="pui-auth__main">
                <div class="pui-auth__card">
                    <div class="pui-auth__mobile-brand">
                        <span class="pui-auth__mark"><?php echo pui_icon('link', ['size' => 18]); ?></span>
                        <span>پیوند امن</span>
                    </div>
                    <h2 class="pui-auth__title">ورود به پنل</h2>
                    <p class="pui-auth__sub">شماره موبایل خود را وارد کنید؛ کد تایید برایتان ارسال می‌شود.</p>
                    <?php echo pui_steps(['شماره موبایل', 'کد تایید'], 1); ?>
                    <div style="block-size:16px"></div>
                    <div class="pui-auth__form">
                        <?php echo pui_field(['id' => 'pui-demo-otp-mobile', 'label' => 'شماره موبایل', 'type' => 'tel', 'hint' => 'مثال: ۰۹۱۲۱۲۳۴۵۶۷']); ?>
                        <?php echo pui_field(['id' => 'pui-demo-otp-code', 'label' => 'کد تایید', 'type' => 'text', 'hint' => 'کد ۵ رقمی پیامک‌شده را وارد کنید.']); ?>
                        <p class="pui-auth__timer">ارسال مجدد تا ۰۰:۵۸</p>
                        <div class="pui-auth__actions">
                            <button type="button" class="pui-btn pui-btn--primary pui-btn--block">تایید و ورود</button>
                            <button type="button" class="pui-btn pui-btn--quiet pui-btn--block">تغییر شماره</button>
                        </div>
                    </div>
                    <p class="pui-auth__support">پشتیبانی: ۰۲۱-۰۰۰۰۰۰۰۰ · ورود با ایمیل</p>
                </div>
            </div>
        </div>
        <?php
    }

    /* ──────────────────────────────────────────── پنل مراجع */
    private static function section_panel(): void
    {
        echo '<h2 style="font-size:var(--pui-fs-lg);margin-block:8px 0">طرح اولیه ۲ — پنل مراجع</h2>';
        ?>
        <div class="pui-app" style="min-block-size:auto;border:1px solid var(--pui-border-soft);border-radius:var(--pui-r-xl);overflow:hidden">
            <aside class="pui-app__sidebar">
                <div class="pui-app__brand">
                    <span class="pui-app__brand-mark"><?php echo pui_icon('link', ['size' => 18]); ?></span>
                    <span>پیوند امن</span>
                </div>
                <nav class="pui-nav" aria-label="بخش‌های پنل">
                    <a class="pui-nav__link" href="#pui-panel" aria-current="page"><?php echo pui_icon('home', ['size' => 18]); ?>داشبورد</a>
                    <a class="pui-nav__link" href="#pui-panel"><?php echo pui_icon('calendar', ['size' => 18]); ?>نوبت‌ها</a>
                    <a class="pui-nav__link" href="#pui-panel"><?php echo pui_icon('file', ['size' => 18]); ?>آزمون‌ها</a>
                    <a class="pui-nav__link" href="#pui-panel"><?php echo pui_icon('message', ['size' => 18]); ?>پیام‌ها</a>
                    <a class="pui-nav__link" href="#pui-panel"><?php echo pui_icon('settings', ['size' => 18]); ?>تنظیمات حساب</a>
                </nav>
            </aside>
            <div class="pui-app__topbar">
                <div class="pui-app__brand"><span class="pui-app__brand-mark"><?php echo pui_icon('link', ['size' => 16]); ?></span><span>پیوند امن</span></div>
                <div class="pui-app__user">
                    <span class="pui-avatar">م</span>
                    <span class="pui-app__user-info"><span class="pui-app__user-name">مراجع نمونه</span><span class="pui-app__user-meta" dir="ltr">0912…</span></span>
                    <button type="button" class="pui-btn pui-btn--quiet pui-btn--icon" aria-label="خروج"><?php echo pui_icon('logout', ['size' => 18]); ?></button>
                </div>
            </div>
            <div class="pui-app__body">
                <div class="pui-grid pui-grid--stats">
                    <?php
                    echo pui_stat(['label' => 'نوبت بعدی', 'value' => 'مشاورهٔ فردی', 'meta' => '۱۴۰۵/۰۸/۲۱ — ۱۶:۳۰', 'icon' => 'calendar', 'tone' => 'accent']);
                    echo pui_stat(['label' => 'آزمون‌های در انتظار', 'value' => '۲', 'meta' => 'برای شما تعیین شده', 'icon' => 'file']);
                    echo pui_stat(['label' => 'پیام‌ها', 'value' => '۵', 'meta' => 'بدون پاسخ', 'icon' => 'message']);
                    echo pui_stat(['label' => 'وضعیت پرونده', 'value' => 'فعال', 'meta' => 'آخرین جلسه: ۱۰ روز پیش', 'icon' => 'activity']);
                    ?>
                </div>
                <?php echo pui_alert('warning', 'فرم ارزیابی مراجع تکمیل نشده است؛ برای شروع درمان تکمیل آن لازم است.', ['title' => 'اقدام لازم']); ?>
                <?php
                echo pui_card_open(['title' => 'نوبت‌های شما', 'icon' => 'calendar', 'actions' => '<button type="button" class="pui-btn pui-btn--primary pui-btn--sm">رزرو نوبت</button>']);
                echo pui_table_open([['label' => 'تاریخ'], ['label' => 'خدمت'], ['label' => 'وضعیت'], ['label' => 'اقدام']], ['class' => 'pui-table--cards']);
                echo '<tr><td data-label="تاریخ">۱۴۰۵/۰۸/۲۱ — ۱۶:۳۰</td><td data-label="خدمت">مشاورهٔ فردی</td><td data-label="وضعیت">' . pui_badge('تایید شده', 'success') . '</td><td data-label="اقدام"><div class="pui-table__actions"><button type="button" class="pui-btn pui-btn--sm pui-btn--ghost">جزئیات</button></div></td></tr>';
                echo '<tr><td data-label="تاریخ">۱۴۰۵/۰۸/۰۵ — ۱۰:۰۰</td><td data-label="خدمت">مشاورهٔ فردی</td><td data-label="وضعیت">' . pui_badge('برگزار شد', 'neutral') . '</td><td data-label="اقدام"><div class="pui-table__actions"><button type="button" class="pui-btn pui-btn--sm pui-btn--ghost">جزئیات</button></div></td></tr>';
                echo pui_table_close();
                echo pui_card_close();
                ?>
                <div class="pui-app__navbar" aria-label="ناوبری موبایل">
                    <span class="pui-app__navlink" aria-current="page"><?php echo pui_icon('home', ['size' => 20]); ?>داشبورد</span>
                    <span class="pui-app__navlink"><?php echo pui_icon('calendar', ['size' => 20]); ?>نوبت‌ها</span>
                    <span class="pui-app__navlink"><?php echo pui_icon('file', ['size' => 20]); ?>آزمون‌ها</span>
                    <span class="pui-app__navlink"><?php echo pui_icon('message', ['size' => 20]); ?>پیام‌ها</span>
                </div>
            </div>
        </div>
        <?php
    }

    /* ─────────────────────────────────────────── پنل درمانگر */
    private static function section_therapist(): void
    {
        echo '<h2 style="font-size:var(--pui-fs-lg);margin-block:8px 0">طرح اولیه ۳ — داشبورد درمانگر</h2>';

        $cases_today = self::safe_count('pas_cf_cases', "DATE(updated_at) = CURDATE()");
        $cases_total = self::safe_count('pas_cf_cases');
        $quizzes     = self::safe_count('psy_attempts');
        ?>
        <div class="pui-stack">
            <div class="pui-grid pui-grid--stats">
                <?php
                echo pui_stat(['label' => 'جلسات امروز', 'value' => (string) $cases_today, 'meta' => 'بر اساس پرونده‌های به‌روزشدهٔ امروز', 'icon' => 'calendar', 'tone' => 'accent']);
                echo pui_stat(['label' => 'پرونده‌های فعال', 'value' => (string) $cases_total, 'meta' => 'کل پرونده‌ها', 'icon' => 'file']);
                echo pui_stat(['label' => 'آزمون‌های ثبت‌شده', 'value' => (string) $quizzes, 'meta' => 'در سامانهٔ آزمون', 'icon' => 'file']);
                echo pui_stat(['label' => 'هشدارهای سیستم', 'value' => '۰', 'meta' => 'در ۲۴ ساعت گذشته', 'icon' => 'bell']);
                ?>
            </div>
            <?php
            echo pui_card_open(['title' => 'تقویم هفته', 'subtitle' => 'طرح اولیه — دادهٔ نمونه؛ در گام ۴ به نوبت‌های واقعی وصل می‌شود', 'icon' => 'calendar']);
            echo '<div class="pui-calendar" aria-hidden="true">';
            $days = ['شنبه', 'یکشنبه', 'دوشنبه', 'سه‌شنبه', 'چهارشنبه', 'پنجشنبه', 'جمعه'];
            foreach ($days as $i => $day) {
                echo '<div class="pui-calendar__day' . ($i === 2 ? ' pui-calendar__day--today' : '') . '">';
                echo '<span class="pui-calendar__label">' . esc_html($day) . '</span>';
                if ($i !== 5) {
                    echo '<span class="pui-calendar__slot">۱۰:۰۰ مراجع</span>';
                }
                if ($i === 2) {
                    echo '<span class="pui-calendar__slot">۱۶:۳۰ مراجع</span>';
                }
                echo '</div>';
            }
            echo '</div>';
            echo pui_card_close();

            echo pui_card_open(['title' => 'جلسات امروز', 'icon' => 'clock']);
            echo pui_table_open([['label' => 'ساعت'], ['label' => 'مراجع'], ['label' => 'وضعیت'], ['label' => 'اقدام']], ['class' => 'pui-table--cards']);
            echo '<tr><td data-label="ساعت">۱۰:۰۰</td><td data-label="مراجع">نمونهٔ الف</td><td data-label="وضعیت">' . pui_badge('تایید شده', 'success') . '</td><td data-label="اقدام"><div class="pui-table__actions"><button type="button" class="pui-btn pui-btn--sm pui-btn--ghost">پرونده</button></div></td></tr>';
            echo '<tr><td data-label="ساعت">۱۶:۳۰</td><td data-label="مراجع">نمونهٔ ب</td><td data-label="وضعیت">' . pui_badge('در انتظار', 'warning') . '</td><td data-label="اقدام"><div class="pui-table__actions"><button type="button" class="pui-btn pui-btn--sm pui-btn--ghost">پرونده</button></div></td></tr>';
            echo pui_table_close();
            echo pui_card_close();
            ?>
        </div>
        <?php
    }

    /* ────────────────────────────────────── مرکز مدیریت */
    private static function section_center(): void
    {
        echo '<h2 style="font-size:var(--pui-fs-lg);margin-block:8px 0">طرح اولیه ۴ — مرکز مدیریت و مانیتورینگ</h2>';

        $health = class_exists('Pas_Core_Health') ? Pas_Core_Health::report() : [];
        $rows   = is_array($health) ? ($health['rows'] ?? []) : [];

        echo pui_alert('info', 'داده‌های جدول زیر واقعی‌اند (از رجیستری سلامت هسته). نمودارها در این گام نمونهٔ طرح‌اند و در گام ۵ با ApexCharts و دادهٔ واقعی جایگزین می‌شوند.', ['title' => 'شفافیت داده']);

        echo pui_card_open(['title' => 'سلامت افزونه‌ها', 'subtitle' => 'منبع: Pas_Core_Health::report()', 'icon' => 'shield']);
        if (empty($rows)) {
            echo pui_empty('داشبورد سلامت در دسترس نیست', 'افزونهٔ هستهٔ مشترک فعال نیست یا کلاس سلامت بارگذاری نشده است.', ['icon' => 'alert']);
        } else {
            echo pui_table_open([['label' => 'افزونه'], ['label' => 'نسخه'], ['label' => 'وضعیت'], ['label' => 'یادداشت']], ['class' => 'pui-table--cards']);
            foreach ($rows as $row) {
                $status = (string) ($row['status'] ?? 'info');
                $version = (string) ($row['header_version'] ?? '');
                if ($version === '') { $version = (string) ($row['const_version'] ?? ''); }
                $detail = implode(' ', array_map('strval', (array) ($row['notes'] ?? [])));
                if (empty($row['active'])) { $detail = trim('غیرفعال. ' . $detail); }
                $tone   = $status === 'ok' ? 'success' : ($status === 'warn' ? 'warning' : ($status === 'fail' ? 'danger' : 'info'));
                $label  = $status === 'ok' ? 'سالم' : ($status === 'warn' ? 'بررسی' : ($status === 'fail' ? 'مشکل' : 'اطلاع'));
                echo '<tr>';
                echo '<td data-label="افزونه">' . esc_html((string) ($row['label'] ?? '')) . '</td>';
                echo '<td data-label="نسخه" class="pui-table__num" dir="ltr">' . esc_html($version !== '' ? $version : '—') . '</td>';
                echo '<td data-label="وضعیت">' . pui_badge($label, $tone) . '</td>';
                echo '<td data-label="یادداشت">' . esc_html($detail) . '</td>';
                echo '</tr>';
            }
            echo pui_table_close();
        }
        echo pui_card_close();

        echo '<div class="pui-grid pui-grid--2">';
        echo pui_card_open(['title' => 'روند جلسات (۱۴ روز)', 'subtitle' => 'نمونهٔ طرح — گام ۵', 'icon' => 'chart']);
        echo '<div class="pui-bars" aria-hidden="true">';
        $heights = [42, 55, 38, 64, 50, 72, 46, 58, 66, 40, 70, 52, 60, 48];
        foreach ($heights as $i => $h) {
            echo '<div class="pui-bars__col"><div class="pui-bars__bar' . ($i > 9 ? ' pui-bars__bar--muted' : '') . '" style="block-size:' . (int) $h . '%"></div><span class="pui-bars__label">' . (int) ($i + 1) . '</span></div>';
        }
        echo '</div>';
        echo pui_card_close();

        echo pui_card_open(['title' => 'سرویس‌ها', 'subtitle' => 'وضعیت تنظیمات، نه وضعیت نمایشی', 'icon' => 'activity']);
        $sms_user = (string) get_option('pas_sms_username', '');
        $smtp_on  = (bool) get_option('zsm_enabled', false);
        echo '<div class="pui-stack">';
        echo '<div class="pui-row pui-row--between"><span>پل پیامک</span>' . pui_badge($sms_user !== '' ? 'تنظیم شده' : 'تنظیم نشده', $sms_user !== '' ? 'success' : 'warning') . '</div>';
        echo '<div class="pui-row pui-row--between"><span>ارسال ایمیل SMTP</span>' . pui_badge($smtp_on ? 'فعال' : 'غیرفعال', $smtp_on ? 'success' : 'neutral') . '</div>';
        echo '<div class="pui-row pui-row--between"><span>کلید رمزنگاری</span>' . pui_badge(class_exists('Pas_Core_Crypto') && Pas_Core_Crypto::is_configured() ? 'تنظیم شده' : 'تنظیم نشده', class_exists('Pas_Core_Crypto') && Pas_Core_Crypto::is_configured() ? 'success' : 'danger') . '</div>';
        echo '</div>';
        echo pui_card_close();
        echo '</div>';
    }

    private static function safe_count(string $table, string $where = ''): int
    {
        global $wpdb;

        $table = $wpdb->prefix . $table;
        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) !== $table) {
            return 0;
        }

        $sql = "SELECT COUNT(*) FROM `$table`" . ($where !== '' ? ' WHERE ' . $where : '');

        return (int) $wpdb->get_var($sql);
    }
}
