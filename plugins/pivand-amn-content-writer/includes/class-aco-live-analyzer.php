<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * ACO_Live_Analyzer
 *
 * پنل تحلیل بلادرنگ سئو در صفحه ویرایش نوشته/برگه (اضافه‌شده در جریان ادغام با
 * Peyvand Amn SEO Assistant Pro).
 *
 * تصمیم معماری کلیدی: این کلاس هیچ الگوریتم امتیازدهی مستقلی ندارد. نسخه‌ی
 * قبلی (Peyvand Amn) یک موتور امتیازدهی کامل در جاوااسکریپت سمت کاربر داشت که
 * کاملاً از منطق سرور جدا بود — با آستانه‌های متفاوت (مثلاً طول عنوان ۴۵-۶۵ در
 * برابر ۵۰-۶۰ در ACO_SEO)، یعنی همان محتوا بسته به این‌که کجا نمایش داده می‌شد
 * دو امتیاز متفاوت می‌گرفت. برای جلوگیری از این واگرایی، این کلاس فقط UI است؛
 * تمام محاسبات از طریق AJAX روی سرور و با فراخوانی مستقیم ACO_SEO::run_audit()
 * انجام می‌شود — همان تابعی که ACO_Generator و ACO_Crawler برای امتیازدهی
 * محتوای تولید/بازنویسی‌شده استفاده می‌کنند. یک منبع واحد حقیقت.
 *
 * کلمه کلیدی تمرکزی از همان متاکی مشترکی خوانده/نوشته می‌شود که در سراسر ACO
 * استفاده می‌شود: _yoast_wpseo_focuskw (کلید سازگار با Yoast که ACO حتی بدون
 * فعال بودن Yoast به‌عنوان محل ذخیره‌ی داخلی خودش از آن استفاده می‌کند — به این
 * ترتیب نیازی به یک متاکی/رجیستری جداگانه شبیه _peyvand_amn_focus_keyword
 * نیست و ACO_Generator، ACO_SEO::suggest_internal_links و این پنل همیشه از
 * یک مقدار واحد می‌خوانند).
 */
class ACO_Live_Analyzer {

    const NONCE_ACTION = 'aco_nonce';

    public static function init(): void {
        add_action( 'add_meta_boxes', [ __CLASS__, 'register_meta_box' ] );
        add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_assets' ] );
    }

    // ─────────────────────────────────────────────────────────────────
    // متاباکس
    // ─────────────────────────────────────────────────────────────────

    public static function register_meta_box(): void {
        foreach ( [ 'post', 'page' ] as $screen ) {
            add_meta_box(
                'aco_live_seo_analyzer',
                'تحلیل بلادرنگ سئو (ACO)',
                [ __CLASS__, 'render_meta_box' ],
                $screen,
                'normal',
                'high'
            );
        }
    }

    public static function render_meta_box( $post ): void {
        $keyword = get_post_meta( $post->ID, '_yoast_wpseo_focuskw', true );

        $stored_score  = get_post_meta( $post->ID, '_aco_seo_score', true );
        $stored_checks = get_post_meta( $post->ID, '_aco_seo_checks', true );
        $stored_checks = $stored_checks ? json_decode( $stored_checks, true ) : [];
        if ( ! is_array( $stored_checks ) ) $stored_checks = [];

        $niche         = (string) ACO_Settings::get( 'niche' );
        $show_clusters = ACO_SEO::niche_looks_like_psychology( $niche );
        ?>
        <div class="aco-live-seo-box">

            <div class="aco-live-focus-box">
                <label for="aco_live_focus_keyword">کلمه کلیدی تمرکزی:</label>
                <input type="text" id="aco_live_focus_keyword" value="<?php echo esc_attr( $keyword ); ?>" placeholder="مثال: درمان اضطراب اجتماعی" />
                <p class="description">این مقدار در همان فیلدی ذخیره می‌شود که تولید و بازنویسی خودکار محتوا (ACO Generator/Crawler) و پیشنهاد لینک داخلی از آن استفاده می‌کنند.</p>
            </div>

            <?php if ( $show_clusters ) : ?>
            <div id="aco_live_clusters_container" class="aco-live-clusters-box">
                <strong>کلیدواژه‌های هم‌خانواده پیشنهادی:</strong>
                <div id="aco_live_clusters"></div>
            </div>
            <?php endif; ?>

            <div id="aco_live_meta_container" class="aco-live-meta-box">
                <strong>توضیحات متای پیشنهادی (چون متا دیسکریپشن هنوز خالی است):</strong>
                <p id="aco_live_meta_text"></p>
            </div>

            <div class="aco-live-score-row">
                <span class="label">امتیاز سئوی لحظه‌ای:</span>
                <div id="aco_live_score">
                    <?php echo ( $stored_score !== '' && $stored_score !== false ) ? (int) $stored_score : '..'; ?>
                </div>
            </div>

            <ul id="aco_live_checklist">
                <?php if ( ! empty( $stored_checks ) ) : ?>
                    <?php foreach ( $stored_checks as $check ) : ?>
                        <?php
                        $pass       = $check['pass'] ?? null;
                        $pass_class = $pass === true ? 'aco-live-check-pass' : ( $pass === false ? 'aco-live-check-fail' : 'aco-live-check-neutral' );
                        $icon       = $pass === true ? '✓ ' : ( $pass === false ? '✗ ' : '– ' );
                        ?>
                        <li class="<?php echo esc_attr( $pass_class ); ?>">
                            <?php echo esc_html( $icon . ( $check['label'] ?? '' ) ); ?>
                            <?php if ( ! empty( $check['detail'] ) ) : ?>
                                <span class="detail"> — <?php echo esc_html( $check['detail'] ); ?></span>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                <?php else : ?>
                    <li class="aco-live-check-neutral">در حال ارزیابی محتوای نوشته...</li>
                <?php endif; ?>
            </ul>

            <div id="aco_live_links_container" class="aco-live-links-box">
                <h4>فرصت‌های لینک‌سازی داخلی:</h4>
                <ul id="aco_live_links"></ul>
            </div>

            <p class="aco-live-footnote">
                امتیاز و چک‌لیست بالا دقیقاً با همان الگوریتمی محاسبه می‌شود که هنگام تولید/بازنویسی خودکار مقاله استفاده می‌شود (ACO_SEO::run_audit) — نه یک موتور امتیازدهی جدا.
            </p>
        </div>
        <?php
    }

    // ─────────────────────────────────────────────────────────────────
    // اسکریپت
    // ─────────────────────────────────────────────────────────────────

    public static function enqueue_assets( $hook ): void {
        if ( ! in_array( $hook, [ 'post.php', 'post-new.php' ], true ) ) {
            return;
        }

        $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
        if ( $screen && ! in_array( $screen->post_type, [ 'post', 'page' ], true ) ) {
            return;
        }
        // وابستگی wp-data فقط در ویرایشگر بلوک (وردپرس 7.x: ویرایشگر داخل iframe است،
        // ولی متاباکس‌ها در سند والد می‌مانند و wp.data در همان سند در دسترس است).
        $deps = [];
        if ( $screen && method_exists( $screen, 'is_block_editor' ) && $screen->is_block_editor() ) {
            $deps[] = 'wp-data';
        }

        wp_enqueue_style( 'aco-live-analyzer', ACO_PLUGIN_URL . 'assets/live-analyzer.css', [], ACO_VERSION );

        wp_register_script( 'aco-live-analyzer', false, $deps, ACO_VERSION, true );
        wp_localize_script( 'aco-live-analyzer', 'acoLiveAnalyzer', [
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( self::NONCE_ACTION ),
            'postId'  => get_the_ID() ?: 0,
            'i18n'    => [
                'evaluating' => 'در حال ارزیابی...',
                'error'      => 'خطا در دریافت تحلیل سئو از سرور.',
            ],
        ] );
        wp_add_inline_script( 'aco-live-analyzer', self::get_js() );
        wp_enqueue_script( 'aco-live-analyzer' );
    }

    private static function get_js(): string {
        return <<<'JS'
        document.addEventListener('DOMContentLoaded', function() {
            const scoreEl       = document.getElementById('aco_live_score');
            const checklistEl   = document.getElementById('aco_live_checklist');
            const kwInput       = document.getElementById('aco_live_focus_keyword');
            const clustersBox   = document.getElementById('aco_live_clusters_container');
            const clustersEl    = document.getElementById('aco_live_clusters');
            const metaBox       = document.getElementById('aco_live_meta_container');
            const metaTextEl    = document.getElementById('aco_live_meta_text');
            const linksBox      = document.getElementById('aco_live_links_container');
            const linksEl       = document.getElementById('aco_live_links');
            if (!scoreEl || !checklistEl) return;

            function debounce(fn, wait) {
                let t;
                return function(...args) {
                    clearTimeout(t);
                    t = setTimeout(() => fn.apply(this, args), wait);
                };
            }

            function getEditorFields() {
                let title = '', content = '', metaDescription = '';
                try {
                    if (typeof wp !== 'undefined' && wp.data && wp.data.select('core/editor')) {
                        const editor = wp.data.select('core/editor');
                        title   = editor.getEditedPostAttribute('title')   || '';
                        content = editor.getEditedPostAttribute('content') || '';
                    }
                } catch (e) {}

                if (!title) {
                    const titleField = document.getElementById('title');
                    title = titleField ? titleField.value : '';
                }
                if (!content) {
                    if (typeof tinyMCE !== 'undefined' && tinyMCE.get('content') && !tinyMCE.get('content').isHidden()) {
                        content = tinyMCE.get('content').getContent();
                    } else {
                        const contentField = document.getElementById('content');
                        content = contentField ? contentField.value : '';
                    }
                }

                // متادیسکریپشن Yoast یا RankMath را در صورت وجود می‌خوانیم (بدون وابستگی سخت به آن‌ها)
                const yoastMeta   = document.getElementById('yoast_wpseo_metadesc') || document.querySelector('#wpseo-metabox-content textarea[name*="metadesc"]');
                const rankmathMeta = document.getElementById('rank_math_editor_description') || document.querySelector('textarea.rank-math-description');
                if (yoastMeta) metaDescription = yoastMeta.value || '';
                else if (rankmathMeta) metaDescription = rankmathMeta.value || '';

                return { title, content, metaDescription };
            }

            function renderScore(score) {
                scoreEl.innerText = score;
                let scoreClass = 'is-low';
                if (score >= 80) scoreClass = 'is-good';
                else if (score >= 50) scoreClass = 'is-mid';
                scoreEl.className = scoreClass;
            }

            function renderChecklist(checks) {
                let html = '';
                checks.forEach(function(c) {
                    let cls = 'aco-live-check-neutral', icon = '– ';
                    if (c.pass === true)  { cls = 'aco-live-check-pass'; icon = '✓ '; }
                    if (c.pass === false) { cls = 'aco-live-check-fail'; icon = '✗ '; }
                    html += '<li class="' + cls + '">' + icon + c.label;
                    if (c.detail) html += ' <span class="detail"> — ' + c.detail + '</span>';
                    html += '</li>';
                });
                checklistEl.innerHTML = html || '<li class="aco-live-check-neutral">داده‌ای برای نمایش نیست.</li>';
            }

            function renderClusters(clusters) {
                if (!clustersBox || !clustersEl) return;
                if (!clusters || !clusters.length) {
                    clustersBox.style.display = 'none';
                    return;
                }
                clustersEl.innerHTML = clusters.map(function(phrase) {
                    return '<span class="aco-live-cluster-chip">' + phrase + '</span>';
                }).join('');
                clustersBox.style.display = 'block';
            }

            function renderSuggestedMeta(text) {
                if (!metaBox || !metaTextEl) return;
                if (!text) {
                    metaBox.style.display = 'none';
                    return;
                }
                metaTextEl.innerText = '"' + text + '"';
                metaBox.style.display = 'block';
            }

            function renderInternalLinks(links) {
                if (!linksBox || !linksEl) return;
                if (!links || !links.length) {
                    linksBox.style.display = 'none';
                    return;
                }
                linksEl.innerHTML = links.map(function(link) {
                    return '<li><a href="' + link.url + '" target="_blank">' + link.title + '</a></li>';
                }).join('');
                linksBox.style.display = 'block';
            }

            function runAnalysis() {
                const fields = getEditorFields();
                const keyword = kwInput ? kwInput.value : '';

                checklistEl.innerHTML = '<li class="aco-live-check-neutral">' + acoLiveAnalyzer.i18n.evaluating + '</li>';

                const formData = new FormData();
                formData.append('action', 'aco_seo_live_audit');
                formData.append('nonce', acoLiveAnalyzer.nonce);
                formData.append('post_id', acoLiveAnalyzer.postId);
                formData.append('title', fields.title);
                formData.append('content', fields.content);
                formData.append('meta_description', fields.metaDescription);
                formData.append('keyword', keyword);

                fetch(acoLiveAnalyzer.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: formData })
                    .then(r => r.json())
                    .then(json => {
                        if (!json.success) {
                            checklistEl.innerHTML = '<li class="aco-live-error">' + (json.data && json.data.message ? json.data.message : acoLiveAnalyzer.i18n.error) + '</li>';
                            return;
                        }
                        renderScore(json.data.score);
                        renderChecklist(json.data.checks || []);
                        renderClusters(json.data.clusters || []);
                        renderSuggestedMeta(json.data.suggested_meta || '');
                        renderInternalLinks(json.data.internal_links || []);
                    })
                    .catch(() => {
                        checklistEl.innerHTML = '<li class="aco-live-error">' + acoLiveAnalyzer.i18n.error + '</li>';
                    });
            }

            const debounced = debounce(runAnalysis, 600);

            if (kwInput) kwInput.addEventListener('input', debounced);

            if (typeof wp !== 'undefined' && wp.data && wp.data.subscribe) {
                let prevTitle = '', prevContent = '';
                wp.data.subscribe(function() {
                    const editor = wp.data.select('core/editor');
                    if (!editor) return;
                    const t = editor.getEditedPostAttribute('title')   || '';
                    const c = editor.getEditedPostAttribute('content') || '';
                    if (t !== prevTitle || c !== prevContent) {
                        prevTitle = t; prevContent = c;
                        debounced();
                    }
                });
            } else {
                const titleField   = document.getElementById('title');
                const contentField = document.getElementById('content');
                if (titleField)   titleField.addEventListener('input', debounced);
                if (contentField) contentField.addEventListener('input', debounced);
            }

            setTimeout(runAnalysis, 1200);
        });
        JS;
    }
}
