<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * ARCH-1: صفحات رندر ادمین — استخراج‌شده از class-aco-admin.php
 * شامل: page_generator, page_rewrite, page_history, page_settings,
 *        page_queue, page_logs, page_tokens, render_stats
 */
trait ACO_Admin_Pages {

    public static function page_generator(): void {
        ?>
        <div class="wrap aco-wrap" dir="rtl">
            <h1 class="aco-page-title">
                <span class="dashicons dashicons-edit-large"></span>
                نویسنده هوشمند محتوا
            </h1>

            <?php if ( empty( ACO_Settings::get( 'openrouter_key' ) ) && empty( ACO_Settings::get( 'deepseek_key' ) ) ) : ?>
            <div class="notice notice-warning aco-notice">
                <p>⚠️ کلید API تنظیم نشده است. ابتدا به <a href="<?php echo esc_url( admin_url('admin.php?page=aco-settings') ); ?>">تنظیمات</a> بروید.</p>
            </div>
            <?php endif; ?>

            <div class="aco-grid">

                <!-- فرم تولید -->
                <div class="aco-card aco-form-card">
                    <h2 class="aco-card-title">📝 تولید مقاله جدید</h2>

                    <div class="aco-field">
                        <label for="aco-keyword">کلمه کلیدی اصلی <span class="required">*</span></label>
                        <input type="text" id="aco-keyword" placeholder="مثال: درمان اضطراب اجتماعی" />
                    </div>

                    <div class="aco-field">
                        <label for="aco-topic">عنوان مقاله <span class="required">*</span></label>
                        <input type="text" id="aco-topic" placeholder="مثال: درمان اختلال اضطراب اجتماعی با روش CBT" />
                    </div>

                    <div class="aco-field">
                        <label for="aco-status">وضعیت انتشار</label>
                        <select id="aco-status">
                            <option value="draft" <?php selected( ACO_Settings::get('default_status'), 'draft' ); ?>>پیش‌نویس</option>
                            <option value="pending" <?php selected( ACO_Settings::get('default_status'), 'pending' ); ?>>در انتظار بررسی</option>
                            <option value="publish" <?php selected( ACO_Settings::get('default_status'), 'publish' ); ?>>منتشر شده</option>
                            <option value="future">زمان‌بندی شده</option>
                        </select>
                    </div>

                    <div class="aco-field aco-hidden1" id="aco-scheduled-at-field">
                        <label for="aco-scheduled-at">تاریخ و ساعت انتشار (شمسی)</label>
                        <input type="text" id="aco-scheduled-at" dir="ltr" inputmode="numeric"
                               placeholder="1405/08/27 14:30" autocomplete="off" class="aco-full2">
                        <p class="description">مثال: ۱۴۰۵/۰۸/۲۷ ۱۴:۳۰ — مقاله در این تاریخ به‌صورت خودکار منتشر می‌شود.</p>
                    </div>

                    <div class="aco-actions">
                        <button id="aco-btn-generate" class="button button-primary aco-btn-generate">
                            <span class="btn-text">🚀 تولید مقاله</span>
                            <span class="btn-loading aco-hidden1">⏳ در حال پردازش...</span>
                        </button>
                    </div>
                </div>

                <!-- لاگ -->
                <div class="aco-card aco-log-card aco-hidden1" id="aco-log-card">
                    <h2 class="aco-card-title">📊 وضعیت پردازش</h2>
                    <div id="aco-log-output" class="aco-log"></div>
                </div>

                <!-- نتیجه -->
                <div class="aco-card aco-result-card aco-hidden1" id="aco-result-card">
                    <h2 class="aco-card-title">✅ مقاله ایجاد شد</h2>
                    <div id="aco-result-content"></div>
                </div>

            </div><!-- .aco-grid -->

            <!-- آمار کلی -->
            <?php self::render_stats(); ?>

        </div>
        <?php
    }

    // ─────────────────────────────────────
    // آمار
    // ─────────────────────────────────────

    private static function render_stats(): void {
        $count = get_transient( 'aco_generated_count' );
        if ( $count === false ) {
            // FIX #QA-4: استفاده از WP_Query با posts_per_page=1 برای دریافت found_posts
            // به این شکل WordPress از ایندکس موجود postmeta استفاده می‌کند و INNER JOIN سنگین نمی‌زند.
            $q = new WP_Query( [
                'post_type'      => 'post',
                'post_status'    => 'any',
                'meta_key'       => '_aco_generated',
                'posts_per_page' => 1,
                'fields'         => 'ids',
                'no_found_rows'  => false,
            ] );
            $count = (int) $q->found_posts;
            set_transient( 'aco_generated_count', $count, 5 * MINUTE_IN_SECONDS );
        }
        ?>
        <div class="aco-stats" dir="rtl">
            <div class="aco-stat">
                <span class="stat-number"><?php echo esc_html( $count ); ?></span>
                <span class="stat-label">مقاله تولید شده</span>
            </div>
            <div class="aco-stat">
                <?php
                $provider_labels = [
                    'openrouter' => 'OpenRouter',
                    'deepseek'   => 'DeepSeek',
                    'openai'     => 'OpenAI',
                    'gemini'     => 'Gemini',
                    'anthropic'  => 'Anthropic',
                ];
                $active_provider = ACO_Settings::get( 'provider' );
                ?>
                <span class="stat-number"><?php echo esc_html( $provider_labels[ $active_provider ] ?? 'OpenRouter' ); ?></span>
                <span class="stat-label">سرویس فعال</span>
            </div>
            <div class="aco-stat">
                <?php
                $active_model = ACO_Settings::get( "{$active_provider}_model" );
                $active_model = $active_model ?: '—';
                // نمایش کوتاه‌شده مدل (بعد از آخرین /)
                $model_short = strpos( $active_model, '/' ) !== false
                    ? substr( $active_model, strrpos( $active_model, '/' ) + 1 )
                    : $active_model;
                ?>
                <span class="stat-number" title="<?php echo esc_attr( $active_model ); ?>"><?php echo esc_html( $model_short ); ?></span>
                <span class="stat-label">مدل فعال</span>
            </div>
        </div>
        <?php
    }

    // ─────────────────────────────────────
    // صفحه بهبود مقاله موجود (v1.9.0 — جدول کامل + bulk queue)
    // ─────────────────────────────────────

    public static function page_rewrite(): void {
        global $wpdb;

        // ── پارامترهای فیلتر ──────────────────────────────────────
        $search       = sanitize_text_field( wp_unslash( $_GET['s']        ?? '' ) );
        $paged_rw     = max( 1, absint( wp_unslash( $_GET['rw_paged']      ?? 1  ) ) );
        $score_filter = sanitize_text_field( wp_unslash( $_GET['seo']       ?? '' ) );
        $cat_filter   = absint( wp_unslash( $_GET['cat']                    ?? 0  ) );
        $per_rw       = 20;

        $allowed_score = [ '', 'low', 'mid', 'good' ];
        if ( ! in_array( $score_filter, $allowed_score, true ) ) $score_filter = '';

        // ── WP_Query ─────────────────────────────────────────────
        $query_args = [
            'post_type'      => 'post',
            'post_status'    => [ 'publish', 'draft', 'pending' ],
            'posts_per_page' => $per_rw,
            'paged'          => $paged_rw,
            'orderby'        => 'date',
            'order'          => 'DESC',
        ];
        if ( $search )     $query_args['s']         = $search;
        if ( $cat_filter ) $query_args['cat']        = $cat_filter;

        // فیلتر امتیاز سئو با meta_query
        if ( $score_filter === 'good' ) {
            $query_args['meta_query'] = [ [ 'key' => '_aco_seo_score', 'value' => 70, 'compare' => '>=', 'type' => 'NUMERIC' ] ];
        } elseif ( $score_filter === 'mid' ) {
            $query_args['meta_query'] = [ [ 'key' => '_aco_seo_score', 'value' => [ 40, 69 ], 'compare' => 'BETWEEN', 'type' => 'NUMERIC' ] ];
        } elseif ( $score_filter === 'low' ) {
            $query_args['meta_query'] = [ [ 'key' => '_aco_seo_score', 'value' => 40, 'compare' => '<', 'type' => 'NUMERIC' ] ];
        }

        $rw_query = new WP_Query( $query_args );
        $posts    = $rw_query->posts;
        $total_rw = $rw_query->found_posts;
        $pages_rw = (int) ceil( $total_rw / $per_rw );

        // ── آیتم‌های pending/processing در صف (برای نشان دادن وضعیت در جدول) ──
        $queue_table   = $wpdb->prefix . 'aco_queue';
        $queued_ids    = [];
        if ( ! empty( $posts ) ) {
            // BUG-13 FIX: post_ids_in با absint sanitize شده — هر مقدار عدد صحیح مثبت است.
            // $wpdb->prepare نمی‌تواند لیست‌های IN() متغیر را handle کند.
            // استفاده از absint + implode روش استاندارد WordPress برای این pattern است.
            $post_ids_in = implode( ',', array_map( 'absint', wp_list_pluck( $posts, 'ID' ) ) );
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery
            $rows = $wpdb->get_results(
                "SELECT rewrite_post_id FROM {$queue_table}
                 WHERE job_type='rewrite'
                   AND status IN ('pending','processing')
                   AND rewrite_post_id IN ({$post_ids_in})"
            );
            foreach ( $rows as $row ) {
                $queued_ids[ (int) $row->rewrite_post_id ] = true;
            }
        }

        // ── دسته‌بندی‌ها برای فیلتر ──────────────────────────────
        $categories = get_categories( [ 'hide_empty' => true, 'number' => 200 ] );

        // ── رنگ امتیاز سئو ────────────────────────────────────────
        $score_color = static function( $score ) {
            if ( $score === '' || $score === null ) return '#94a3b8';
            $s = (int) $score;
            if ( $s >= 70 ) return '#16a34a';
            if ( $s >= 40 ) return '#d97706';
            return '#dc2626';
        };

        $statuses_map = [ 'publish' => 'منتشر', 'draft' => 'پیش‌نویس', 'pending' => 'در انتظار' ];

        // URL پایه برای فیلترها
        $base_url = admin_url( 'admin.php?page=aco-rewrite' );
        ?>
        <div class="wrap aco-wrap" dir="rtl">
            <h1 class="aco-page-title">
                <span class="dashicons dashicons-update"></span>
                بهبود دسته‌جمعی مقالات
                <span class="aco-soft3"><?php echo esc_html( $total_rw ); ?> مقاله</span>
            </h1>

            <?php /* ── نوار فیلتر ── */ ?>
            <div class="aco-rw-filters aco-flex4">

                <form method="get" class="aco-u5">
                    <input type="hidden" name="page" value="aco-rewrite" />

                    <div class="aco-flex6">
                        <label class="aco-soft7">جستجو در عنوان</label>
                        <div class="aco-flex8">
                            <input type="text" name="s" value="<?php echo esc_attr( $search ); ?>"
                                   placeholder="جستجو..." class="aco-text9">
                            <button type="submit" class="button aco-u10">🔍</button>
                        </div>
                    </div>

                    <div class="aco-flex11">
                        <label class="aco-soft7">امتیاز سئو</label>
                        <select name="seo" onchange="this.form.submit()" class="aco-text12">
                            <option value="" <?php selected( $score_filter, '' ); ?>>همه</option>
                            <option value="low"  <?php selected( $score_filter, 'low'  ); ?>>زیر ۴۰ (ضعیف)</option>
                            <option value="mid"  <?php selected( $score_filter, 'mid'  ); ?>>۴۰–۷۰ (متوسط)</option>
                            <option value="good" <?php selected( $score_filter, 'good' ); ?>>بالای ۷۰ (خوب)</option>
                        </select>
                    </div>

                    <div class="aco-flex11">
                        <label class="aco-soft7">دسته‌بندی</label>
                        <select name="cat" onchange="this.form.submit()" class="aco-text12">
                            <option value="0">همه دسته‌ها</option>
                            <?php foreach ( $categories as $cat ) : ?>
                            <option value="<?php echo esc_attr( $cat->term_id ); ?>" <?php selected( $cat_filter, $cat->term_id ); ?>>
                                <?php echo esc_html( $cat->name ); ?> (<?php echo esc_html( $cat->count ); ?>)
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <?php if ( $search || $score_filter || $cat_filter ) : ?>
                    <div class="aco-flex13">
                        <a href="<?php echo esc_url( $base_url ); ?>" class="button aco-text14">✕ پاک کردن فیلتر</a>
                    </div>
                    <?php endif; ?>
                </form>
            </div>

            <?php /* ── نوار اقدام دسته‌جمعی ── */ ?>
            <div class="aco-rw-bulk-bar aco-hidden15" id="aco-rw-bulk-bar"
                >
                <span id="aco-rw-selected-count" class="aco-text16">۰ مقاله انتخاب‌شده</span>
                <button id="aco-btn-bulk-queue" class="button button-primary aco-u17">
                    📋 ارسال انتخاب‌شده‌ها به صف بهبود
                </button>
                <span id="aco-bulk-msg" class="aco-hidden18"></span>
            </div>

            <?php /* ── جدول مقالات ── */ ?>
            <div class="aco-card aco-u19">
                <table class="wp-list-table widefat fixed striped aco-u20" id="aco-rw-table">
                    <thead>
                        <tr class="aco-u21">
                            <th class="aco-u22">
                                <input type="checkbox" id="aco-rw-check-all" title="انتخاب همه" />
                            </th>
                            <th class="aco-right23">عنوان مقاله</th>
                            <th class="aco-right24">دسته‌بندی</th>
                            <th class="aco-center25">امتیاز سئو</th>
                            <th class="aco-right26">تاریخ</th>
                            <th class="aco-center27">وضعیت</th>
                            <th class="aco-center27">در صف</th>
                            <th class="aco-center28">عملیات</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if ( empty( $posts ) ) : ?>
                        <tr><td colspan="8" class="aco-soft-center29">مقاله‌ای یافت نشد.</td></tr>
                    <?php else : ?>
                        <?php foreach ( $posts as $p ) :
                            $score     = get_post_meta( $p->ID, '_aco_seo_score', true );
                            $score_val = $score !== '' && $score !== false ? (int) $score : '';
                            $color     = $score_color( $score_val );
                            $score_lbl = $score_val !== '' ? $score_val . '/۱۰۰' : '—';
                            $cats      = wp_get_post_categories( $p->ID, [ 'fields' => 'names' ] );
                            $cat_lbl   = ! empty( $cats ) ? implode( '، ', array_slice( $cats, 0, 2 ) ) : '—';
                            $status_lbl = $statuses_map[ $p->post_status ] ?? $p->post_status;
                            $in_queue  = ! empty( $queued_ids[ $p->ID ] );
                            $date_lbl  = self::jalali_dt( (string) $p->post_date );
                        ?>
                        <tr class="aco-rw-row aco-u30" data-id="<?php echo esc_attr( $p->ID ); ?>">
                            <td class="aco-u31">
                                <?php if ( $in_queue ) : ?>
                                <input type="checkbox" class="aco-rw-cb" value="<?php echo esc_attr( $p->ID ); ?>" disabled title="در صف است" />
                                <?php else : ?>
                                <input type="checkbox" class="aco-rw-cb" value="<?php echo esc_attr( $p->ID ); ?>" />
                                <?php endif; ?>
                            </td>
                            <td class="aco-u31">
                                <strong>
                                    <a href="<?php echo esc_url( get_edit_post_link( $p->ID ) ); ?>" class="aco-u32">
                                        <?php echo esc_html( mb_strlen( $p->post_title, 'UTF-8' ) > 70 ? mb_substr( $p->post_title, 0, 70, 'UTF-8' ) . '…' : $p->post_title ); ?>
                                    </a>
                                </strong>
                            </td>
                            <td class="aco-soft33"><?php echo esc_html( $cat_lbl ); ?></td>
                            <td class="aco-center34">
                                <span style="display:inline-block;background:<?php echo esc_attr( $color ); ?>1a;color:<?php echo esc_attr( $color ); ?>;border:1px solid <?php echo esc_attr( $color ); ?>55;border-radius:20px;padding:2px 10px;font-size:12px;font-weight:700;min-width:55px;text-align:center">
                                    <?php echo esc_html( $score_lbl ); ?>
                                </span>
                            </td>
                            <td class="aco-soft-right35"><?php echo esc_html( $date_lbl ); ?></td>
                            <td class="aco-center34">
                                <span class="aco-soft36"><?php echo esc_html( $status_lbl ); ?></span>
                            </td>
                            <td class="aco-center34">
                                <?php if ( $in_queue ) : ?>
                                <span class="aco-text37">⏳ در صف</span>
                                <?php else : ?>
                                <span class="aco-text38">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="aco-center39">
                                <button class="button aco-btn-instant-rewrite aco-text40"
                                        data-id="<?php echo esc_attr( $p->ID ); ?>"
                                        data-title="<?php echo esc_attr( $p->post_title ); ?>"
                                        <?php echo $in_queue ? 'disabled title="در صف است"' : ''; ?>>
                                    🔄 فوری
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php /* ── صفحه‌بندی ── */ ?>
            <?php if ( $pages_rw > 1 ) : ?>
            <div class="aco-center41">
                <?php
                $paginate_args = [
                    'base'      => add_query_arg( 'rw_paged', '%#%' ),
                    'format'    => '',
                    'current'   => $paged_rw,
                    'total'     => $pages_rw,
                    'prev_text' => 'قبلی &laquo;',
                    'next_text' => '&raquo; بعدی',
                    'type'      => 'plain',
                ];
                // حفظ سایر query paramها در pagination
                foreach ( [ 's', 'seo', 'cat' ] as $qp ) {
                    if ( isset( $_GET[ $qp ] ) && $_GET[ $qp ] !== '' && $_GET[ $qp ] !== '0' ) {
                        $paginate_args['add_args'][ $qp ] = sanitize_text_field( wp_unslash( $_GET[ $qp ] ) );
                    }
                }
                echo paginate_links( $paginate_args ); // phpcs:ignore WordPress.Security.EscapeOutput
                ?>
            </div>
            <?php endif; ?>

        </div><!-- end .wrap -->

        <?php /* ── Modal بهبود فوری ── */ ?>
        <div id="aco-rw-modal-overlay" class="aco-hidden42">
            <div id="aco-rw-modal" dir="rtl" class="aco-flex43">

                <div class="aco-flex44">
                    <div>
                        <div class="aco-text45">🔄 بهبود فوری مقاله</div>
                        <div id="aco-rw-modal-title" class="aco-soft46"></div>
                    </div>
                    <button id="aco-rw-modal-close" title="بستن" class="aco-soft47">✕</button>
                </div>

                <div class="aco-u48">
                    <div id="aco-rw-modal-idle" class="aco-soft-center49">
                        آماده برای بهبود.<br>
                        <small class="aco-soft50">این فرآیند ۳۰ تا ۱۵۰ ثانیه طول می‌کشد.</small>
                    </div>
                    <div id="aco-rw-modal-log" class="aco-hidden1">
                        <div id="aco-rw-modal-log-inner" class="aco-soft51"></div>
                    </div>
                    <div id="aco-rw-modal-result" class="aco-hidden1">
                        <div class="aco-flex52">
                            <span class="aco-text53">✅</span>
                            <div>
                                <div class="aco-text54">مقاله با موفقیت بهبود یافت</div>
                                <div id="aco-rw-modal-wordcount" class="aco-text55"></div>
                            </div>
                        </div>
                        <div id="aco-rw-modal-score-wrap" class="aco-u56"></div>
                        <div id="aco-rw-modal-links" class="aco-u56"></div>
                    </div>
                    <div id="aco-rw-modal-error" class="aco-hidden57"></div>
                </div>

                <div class="aco-flex58">
                    <button id="aco-rw-modal-start" class="button button-primary aco-text59">
                        <span class="btn-text">🔄 شروع بهبود</span>
                        <span class="btn-loading aco-hidden1">⏳ در حال پردازش...</span>
                    </button>
                    <a id="aco-rw-modal-edit-link" href="#" target="_blank" rel="noopener noreferrer" class="button aco-hidden60">✏️ ویرایش مقاله</a>
                    <a id="aco-rw-modal-view-link" href="#" target="_blank" rel="noopener noreferrer" class="button aco-hidden60">👁 مشاهده</a>
                    <button id="aco-rw-modal-close2" class="button aco-u61">بستن</button>
                </div>
            </div>
        </div>
        <?php
    }

    // ─────────────────────────────────────
    // AJAX: بازنویسی مقاله موجود
    // ─────────────────────────────────────

    public static function page_history(): void {
        $paged    = max( 1, absint( $_GET['paged'] ?? 1 ) );
        $per_page = 20;

        // ── تب منبع (aco / all) ──────────────────────────────────
        $allowed_sources = [ 'aco', 'all' ];
        $source_raw      = sanitize_text_field( wp_unslash( $_GET['source'] ?? 'aco' ) );
        $source          = in_array( $source_raw, $allowed_sources, true ) ? $source_raw : 'aco';

        // ── فیلتر امتیاز سئو (فقط برای تب ACO) ─────────────────
        $allowed_score_filters = [ '', 'low', 'mid', 'good', 'none' ];
        $score_filter_raw      = sanitize_text_field( wp_unslash( $_GET['seo'] ?? '' ) );
        $score_filter          = ( $source === 'aco' && in_array( $score_filter_raw, $allowed_score_filters, true ) )
                                 ? $score_filter_raw : '';

        // ── جستجو ───────────────────────────────────────────────
        $search = sanitize_text_field( wp_unslash( $_GET['s'] ?? '' ) );

        // ── ساخت query ──────────────────────────────────────────
        $query_args = [
            'post_type'      => 'post',
            'post_status'    => 'any',
            'posts_per_page' => $per_page,
            'paged'          => $paged,
            'orderby'        => 'date',
            'order'          => 'DESC',
        ];

        if ( $search ) {
            $query_args['s'] = $search;
        }

        if ( $source === 'aco' ) {
            // فقط مقالات تولیدشده توسط ACO
            $meta_query = [ [ 'key' => '_aco_generated', 'compare' => 'EXISTS' ] ];

            if ( $score_filter === 'good' ) {
                $meta_query[] = [ 'key' => '_aco_seo_score', 'value' => 80, 'compare' => '>=', 'type' => 'NUMERIC' ];
            } elseif ( $score_filter === 'mid' ) {
                $meta_query[] = [ 'key' => '_aco_seo_score', 'value' => [ 50, 79 ], 'compare' => 'BETWEEN', 'type' => 'NUMERIC' ];
            } elseif ( $score_filter === 'low' ) {
                $meta_query[] = [ 'key' => '_aco_seo_score', 'value' => 50, 'compare' => '<', 'type' => 'NUMERIC' ];
            } elseif ( $score_filter === 'none' ) {
                $meta_query[] = [ 'key' => '_aco_seo_score', 'compare' => 'NOT EXISTS' ];
            }

            $query_args['meta_query'] = $meta_query;
        }
        // source=all: بدون meta_query — همه پست‌ها

        $query = new WP_Query( $query_args );
        $total = $query->found_posts;
        $pages = (int) ceil( $total / $per_page );

        $score_filter_labels = [
            ''     => 'همه',
            'good' => '🟢 خوب (۸۰+)',
            'mid'  => '🟡 متوسط (۵۰–۷۹)',
            'low'  => '🔴 ضعیف (زیر ۵۰)',
            'none' => '⚪ بدون امتیاز',
        ];
        $statuses = [ 'publish' => 'منتشر', 'draft' => 'پیش‌نویس', 'pending' => 'در انتظار', 'future' => 'زمان‌بندی', 'trash' => 'زباله‌دان' ];
        ?>
        <div class="wrap aco-wrap" dir="rtl">
            <h1 class="aco-page-title">
                <span class="dashicons dashicons-list-view"></span>
                تاریخچه مقالات
                <span class="aco-soft3">(<?php echo esc_html( $total ); ?> مقاله)</span>
            </h1>

            <!-- ── تب‌های منبع ── -->
            <div class="aco-flex62">
                <?php
                $tabs = [ 'aco' => '🤖 تولیدشده توسط ACO', 'all' => '📄 همه مقالات سایت' ];
                foreach ( $tabs as $tab_key => $tab_label ) :
                    $tab_url    = add_query_arg( [ 'source' => $tab_key, 'seo' => '', 's' => '', 'paged' => 1 ] );
                    $tab_active = $source === $tab_key;
                ?>
                <a href="<?php echo esc_url( $tab_url ); ?>"
                   style="flex:1;padding:10px 16px;text-align:center;font-size:13px;font-weight:<?php echo $tab_active ? '600' : '400'; ?>;
                          background:<?php echo $tab_active ? '#f8faff' : '#fff'; ?>;
                          color:<?php echo $tab_active ? '#2563eb' : '#64748b'; ?>;
                          border-bottom:<?php echo $tab_active ? '2px solid #2563eb' : '2px solid transparent'; ?>;
                          text-decoration:none;display:block">
                    <?php echo esc_html( $tab_label ); ?>
                </a>
                <?php endforeach; ?>
            </div>

            <!-- ── جستجو ── -->
            <div class="aco-flex63">
                <form method="get" class="aco-flex64">
                    <input type="hidden" name="page"   value="aco-history" />
                    <input type="hidden" name="source" value="<?php echo esc_attr( $source ); ?>" />
                    <input type="hidden" name="seo"    value="<?php echo esc_attr( $score_filter ); ?>" />
                    <input type="text" name="s" value="<?php echo esc_attr( $search ); ?>"
                           placeholder="🔍 جستجو در عنوان..."
                           class="aco-text65">
                    <button type="submit" class="button">جستجو</button>
                    <?php if ( $search ) : ?>
                    <a href="<?php echo esc_url( add_query_arg( [ 's' => '', 'paged' => 1 ] ) ); ?>" class="button">✕</a>
                    <?php endif; ?>
                </form>
            </div>

            <!-- ── فیلتر سئو (فقط تب ACO) ── -->
            <?php if ( $source === 'aco' ) : ?>
            <div class="aco-flex66">
                <span class="aco-soft67">فیلتر سئو:</span>
                <?php foreach ( $score_filter_labels as $sf => $sf_label ) :
                    $sf_url    = add_query_arg( [ 'seo' => $sf, 'paged' => 1 ] );
                    $sf_active = $score_filter === $sf;
                ?>
                <a href="<?php echo esc_url( $sf_url ); ?>"
                   class="button <?php echo $sf_active ? 'button-primary' : ''; ?> aco-text68"
                  >
                    <?php echo esc_html( $sf_label ); ?>
                </a>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <?php if ( $query->have_posts() ) : ?>
            <div class="aco-card aco-u19">
                <table class="widefat striped aco-u69">
                    <thead>
                        <tr>
                            <th class="aco-right70">عنوان مقاله</th>
                            <th class="aco-right70">دسته‌بندی</th>
                            <th class="aco-right70">وضعیت</th>
                            <th class="aco-right71">امتیاز سئو</th>
                            <th class="aco-right70">تاریخ</th>
                            <th class="aco-right70">منبع / بهبود</th>
                            <th class="aco-right70">عملیات</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php while ( $query->have_posts() ) : $query->the_post();
                        $pid          = get_the_ID();
                        $is_aco       = (bool) get_post_meta( $pid, '_aco_generated', true );
                        $rewritten_at = get_post_meta( $pid, '_aco_rewritten_at', true ); // تاریخ آخرین بهبود
                        $is_rewritten = ! empty( $rewritten_at );
                        // مقاله دستی که توسط ACO بهبود یافته: is_aco=false, is_rewritten=true
                        $is_manual_improved = ( ! $is_aco && $is_rewritten );

                        $seo_score   = get_post_meta( $pid, '_aco_seo_score', true );
                        $score_int   = ( $seo_score !== false && $seo_score !== '' ) ? (int) $seo_score : null;

                        if ( $score_int === null ) {
                            $score_bg    = '#e2e8f0';
                            $score_color = '#94a3b8';
                            $score_label = '—';
                        } elseif ( $score_int >= 80 ) {
                            $score_bg    = '#dcfce7';
                            $score_color = '#16a34a';
                            $score_label = $score_int . '/۱۰۰';
                        } elseif ( $score_int >= 50 ) {
                            $score_bg    = '#fef9c3';
                            $score_color = '#ca8a04';
                            $score_label = $score_int . '/۱۰۰';
                        } else {
                            $score_bg    = '#fee2e2';
                            $score_color = '#dc2626';
                            $score_label = $score_int . '/۱۰۰';
                        }
                        $status_label = $statuses[ get_post_status() ] ?? get_post_status();
                    ?>
                        <tr>
                            <td><strong><?php echo esc_html( get_the_title() ); ?></strong></td>
                            <td>
                                <?php
                                $cats = get_the_category( $pid );
                                // BUG-5 FIX: echo HTML literal با wp_kses محافظت می‌شود — XSS جلوگیری
                                echo $cats
                                    ? esc_html( $cats[0]->name )
                                    : wp_kses( '<span class="aco-soft72">—</span>', [ 'span' => [ 'style' => [] ] ] );
                                ?>
                            </td>
                            <td><?php echo esc_html( $status_label ); ?></td>
                            <td>
                                <?php if ( $score_int !== null ) : ?>
                                <div class="aco-flex73">
                                    <div class="aco-u74">
                                        <div style="height:100%;width:<?php echo esc_attr( $score_int ); ?>%;background:<?php echo esc_attr( $score_color ); ?>;border-radius:4px;transition:width .3s"></div>
                                    </div>
                                    <span style="background:<?php echo esc_attr( $score_bg ); ?>;color:<?php echo esc_attr( $score_color ); ?>;padding:2px 8px;border-radius:20px;font-size:12px;font-weight:600;white-space:nowrap">
                                        <?php echo esc_html( $score_label ); ?>
                                    </span>
                                </div>
                                <?php else : ?>
                                <span class="aco-soft75">بدون امتیاز</span>
                                <?php endif; ?>
                            </td>
                            <td class="aco-soft36">
                                <?php echo esc_html( get_the_date( 'Y/m/d' ) ); ?>
                            </td>
                            <td>
                                <div class="aco-flex76">
                                    <?php if ( $is_aco && ! $is_rewritten ) : ?>
                                        <!-- تولیدشده توسط ACO، بهبود نیافته -->
                                        <span class="aco-text77">🤖 ACO</span>
                                    <?php elseif ( $is_aco && $is_rewritten ) : ?>
                                        <!-- تولیدشده و بهبودیافته توسط ACO -->
                                        <span class="aco-text77">🤖 ACO</span>
                                        <span title="بهبود: <?php echo esc_attr( $rewritten_at ); ?>" class="aco-text78">✅ بهبود یافته</span>
                                        <span class="aco-soft79"><?php echo esc_html( substr( $rewritten_at, 0, 10 ) ); ?></span>
                                    <?php elseif ( $is_manual_improved ) : ?>
                                        <!-- دستی نوشته‌شده، بهبودیافته توسط ACO -->
                                        <span class="aco-soft80">✍️ دستی</span>
                                        <span title="بهبود: <?php echo esc_attr( $rewritten_at ); ?>" class="aco-text81">🔄 بهبود ACO</span>
                                        <span class="aco-soft79"><?php echo esc_html( substr( $rewritten_at, 0, 10 ) ); ?></span>
                                    <?php else : ?>
                                        <!-- دستی، بهبود نیافته -->
                                        <span class="aco-soft80">✍️ دستی</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td class="aco-u82">
                                <a href="<?php echo esc_url( get_edit_post_link() ); ?>" class="button button-small">ویرایش</a>
                                <a href="<?php echo esc_url( get_permalink() ); ?>" class="button button-small" target="_blank" rel="noopener noreferrer">مشاهده</a>
                            </td>
                        </tr>
                    <?php endwhile; wp_reset_postdata(); ?>
                    </tbody>
                </table>
            </div>

            <?php if ( $pages > 1 ) : ?>
            <div class="aco-center41">
                <?php
                echo paginate_links( [
                    'base'      => add_query_arg( 'paged', '%#%' ),
                    'format'    => '',
                    'current'   => $paged,
                    'total'     => $pages,
                    'prev_text' => '&raquo;',
                    'next_text' => '&laquo;',
                ] );
                ?>
            </div>
            <?php endif; ?>

            <?php else : ?>
            <div class="aco-card aco-soft-center83">
                <?php if ( $search ) : ?>
                <p>نتیجه‌ای برای «<?php echo esc_html( $search ); ?>» یافت نشد.</p>
                <a href="<?php echo esc_url( add_query_arg( 's', '' ) ); ?>" class="button">پاک کردن جستجو</a>
                <?php elseif ( $score_filter ) : ?>
                <p>مقاله‌ای با این فیلتر یافت نشد.</p>
                <a href="<?php echo esc_url( add_query_arg( 'seo', '' ) ); ?>" class="button">نمایش همه</a>
                <?php elseif ( $source === 'aco' ) : ?>
                <p>هنوز هیچ مقاله‌ای با ACO تولید نشده است.</p>
                <a href="<?php echo esc_url( admin_url('admin.php?page=aco-writer') ); ?>" class="button button-primary">تولید اولین مقاله</a>
                <?php else : ?>
                <p>هیچ مقاله‌ای در سایت یافت نشد.</p>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
        <?php
    }

    // ─────────────────────────────────────
    // صفحه تنظیمات
    // ─────────────────────────────────────

    public static function page_settings(): void {
        $s = ACO_Settings::get();
        ?>
        <div class="wrap aco-wrap" dir="rtl">
            <h1 class="aco-page-title">
                <span class="dashicons dashicons-admin-settings"></span>
                تنظیمات API
            </h1>

            <div class="aco-card aco-u84">

                <div class="aco-field aco-free-only-box" style="background:#eafaf1;border:1px solid #34a853;border-radius:6px;padding:14px;margin-bottom:18px;">
                    <label style="font-weight:700;font-size:14px;">
                        <input type="checkbox" id="aco-free-only-mode" <?php checked( ! empty( $s['free_only_mode'] ) ); ?>>
                        🆓 فقط از هوش مصنوعی رایگان استفاده شود (توصیه‌شده)
                    </label>
                    <p class="description">
                        وقتی روشن است، کل افزونه — چه تولید مقاله، چه تولید تصویر شاخص — همیشه فقط از OpenRouter با یک مدل
                        رایگان (پسوند <code>:free</code>) استفاده می‌کند. هیچ ارائه‌دهنده پولی (DeepSeek، OpenAI، Gemini،
                        Anthropic برای متن؛ OpenAI Images یا OpenRouter برای تصویر) هرگز فراخوانی نمی‌شود — حتی اگر کلیدشان
                        قبلاً وارد شده باشد. برای تصویر شاخص هم فقط Pollinations.ai (رایگان) امتحان می‌شود.
                        این محدودیت در خودِ کد اعمال می‌شود، نه فقط این صفحه — پس مطمئن باشید هزینه‌ای ایجاد نمی‌شود.
                        اگر خاموشش کنید، بخش‌های ارائه‌دهنده پولی پایین‌تر دوباره فعال می‌شوند.
                    </p>
                </div>

                <div id="aco-paid-providers-wrap">

                <div class="aco-field">
                    <label>سرویس هوش مصنوعی</label>
                    <select id="aco-provider">
                        <option value="openrouter" <?php selected( $s['provider'], 'openrouter' ); ?>>OpenRouter (توصیه شده)</option>
                        <option value="deepseek"   <?php selected( $s['provider'], 'deepseek'   ); ?>>DeepSeek</option>
                        <option value="openai"     <?php selected( $s['provider'], 'openai'     ); ?>>OpenAI</option>
                        <option value="gemini"     <?php selected( $s['provider'], 'gemini'     ); ?>>Google Gemini</option>
                        <option value="anthropic"  <?php selected( $s['provider'], 'anthropic'  ); ?>>Anthropic Claude</option>
                    </select>
                    <p class="description">OpenRouter چند مدل رایگان دارد. DeepSeek هم از ایران قابل دسترس است. برای OpenAI/Gemini/Anthropic احتمالاً به VPN نیاز دارید.</p>
                </div>

                <div class="aco-field">
                    <label>ارائه‌دهنده جایگزین (Fallback) — اختیاری</label>
                    <select id="aco-fallback-provider">
                        <option value="" <?php selected( $s['fallback_provider'] ?? '', '' ); ?>>هیچکدام (fallback غیرفعال)</option>
                        <option value="openrouter" <?php selected( $s['fallback_provider'] ?? '', 'openrouter' ); ?>>OpenRouter</option>
                        <option value="deepseek"   <?php selected( $s['fallback_provider'] ?? '', 'deepseek'   ); ?>>DeepSeek</option>
                        <option value="openai"     <?php selected( $s['fallback_provider'] ?? '', 'openai'     ); ?>>OpenAI (ChatGPT)</option>
                        <option value="gemini"     <?php selected( $s['fallback_provider'] ?? '', 'gemini'     ); ?>>Google Gemini</option>
                        <option value="anthropic"  <?php selected( $s['fallback_provider'] ?? '', 'anthropic'  ); ?>>Anthropic Claude</option>
                    </select>
                    <p class="description">
                        اگر ارائه‌دهنده اصلی بالا در تولید مقاله جواب نداد (کلید نامعتبر، rate limit، سرور در دسترس نیست و...)،
                        همان درخواست به‌طور خودکار با این ارائه‌دهنده دوم دوباره امتحان می‌شود — به شرط این‌که کلید API آن هم
                        در بخش مربوطه پایین‌تر وارد شده باشد. مثال رایج: ارائه‌دهنده اصلی = OpenRouter، جایگزین = OpenAI (ChatGPT).
                    </p>
                </div>

                <!-- OpenRouter -->
                <div id="aco-section-openrouter" class="aco-provider-section">
                    <div class="aco-field">
                        <label>کلید API — OpenRouter</label>
                        <input type="password" id="aco-openrouter-key" value="" autocomplete="new-password" placeholder="<?php echo $s['openrouter_key'] !== '' ? '•••••••• (تنظیم شده — برای تغییر، مقدار جدید وارد کن)' : 'sk-or-v1-...'; ?>" class="aco-full85">
                        <p class="description aco-key-set-note" data-has-key="<?php echo $s['openrouter_key'] !== '' ? '1' : '0'; ?>"><?php echo $s['openrouter_key'] !== '' ? 'برای حفظ کلید فعلی، این فیلد را خالی بگذار.' : ''; ?></p>
                        <p class="description">
                            ثبت‌نام رایگان در <a href="https://openrouter.ai/keys" target="_blank" rel="noopener noreferrer">openrouter.ai/keys</a> &larr; کلید بسازید
                        </p>
                    </div>
                    <div class="aco-field">
                        <label>مدل OpenRouter</label>
                        <input type="text" id="aco-openrouter-model" value="<?php echo esc_attr( $s['openrouter_model'] ); ?>" placeholder="google/gemma-4-31b-it:free" class="aco-full85">
                        <p class="description">لیست مدل‌های رایگان: <a href="https://openrouter.ai/models?order=top-weekly&supported_parameters=free" target="_blank" rel="noopener noreferrer">openrouter.ai/models</a></p>
                    </div>
                </div>

                <!-- DeepSeek -->
                <div id="aco-section-deepseek" class="aco-provider-section aco-hidden1">
                    <div class="aco-field">
                        <label>کلید API — DeepSeek</label>
                        <input type="password" id="aco-deepseek-key" value="" autocomplete="new-password" placeholder="<?php echo $s['deepseek_key'] !== '' ? '•••••••• (تنظیم شده — برای تغییر، مقدار جدید وارد کن)' : 'sk-...'; ?>" class="aco-full85">
                        <p class="description aco-key-set-note" data-has-key="<?php echo $s['deepseek_key'] !== '' ? '1' : '0'; ?>"><?php echo $s['deepseek_key'] !== '' ? 'برای حفظ کلید فعلی، این فیلد را خالی بگذار.' : ''; ?></p>
                        <p class="description">
                            ثبت‌نام در <a href="https://platform.deepseek.com/api_keys" target="_blank" rel="noopener noreferrer">platform.deepseek.com</a> — ۵ میلیون توکن رایگان اولیه
                        </p>
                    </div>
                    <div class="aco-field">
                        <label>مدل DeepSeek</label>
                        <input type="text" id="aco-deepseek-model" value="<?php echo esc_attr( $s['deepseek_model'] ); ?>" placeholder="deepseek-chat" class="aco-full85">
                        <p class="description"><code>deepseek-chat</code> = V3 &nbsp;|&nbsp; <code>deepseek-reasoner</code> = R1</p>
                    </div>
                </div>

                <!-- OpenAI -->
                <div id="aco-section-openai" class="aco-provider-section aco-hidden1">
                    <div class="aco-field">
                        <label>کلید API — OpenAI</label>
                        <input type="password" id="aco-openai-key" value="" autocomplete="new-password" placeholder="<?php echo $s['openai_key'] !== '' ? '•••••••• (تنظیم شده — برای تغییر، مقدار جدید وارد کن)' : 'sk-...'; ?>" class="aco-full85">
                        <p class="description aco-key-set-note" data-has-key="<?php echo $s['openai_key'] !== '' ? '1' : '0'; ?>"><?php echo $s['openai_key'] !== '' ? 'برای حفظ کلید فعلی، این فیلد را خالی بگذار.' : ''; ?></p>
                        <p class="description">
                            کلید از <a href="https://platform.openai.com/api-keys" target="_blank" rel="noopener noreferrer">platform.openai.com/api-keys</a>
                        </p>
                    </div>
                    <div class="aco-field">
                        <label>مدل OpenAI (متن/مقاله)</label>
                        <input type="text" id="aco-openai-model" value="<?php echo esc_attr( $s['openai_model'] ); ?>" placeholder="gpt-4o-mini" class="aco-full85">
                        <p class="description">پیش‌فرض <code>gpt-4o-mini</code> — ارزان و برای مقاله‌نویسی مناسب.</p>
                    </div>
                    <div class="aco-field">
                        <label>مدل تصویرسازی OpenAI</label>
                        <input type="text" id="aco-openai-image-model" value="<?php echo esc_attr( $s['openai_image_model'] ?? 'gpt-image-1' ); ?>" placeholder="gpt-image-1" class="aco-full85">
                        <p class="description">
                            جدا از مدل بالا (که برای متن است) — سرویس تخصصی تصویرسازی OpenAI. پیش‌فرض <code>gpt-image-1</code>؛
                            گزینه دیگر: <code>dall-e-3</code>. فقط وقتی استفاده می‌شود که «تولید خودکار تصویر شاخص» روشن باشد
                            و منبع رایگان Pollinations جواب نداده باشد.
                        </p>
                    </div>
                </div>

                <!-- Gemini -->
                <div id="aco-section-gemini" class="aco-provider-section aco-hidden1">
                    <div class="aco-field">
                        <label>کلید API — Google Gemini</label>
                        <input type="password" id="aco-gemini-key" value="" autocomplete="new-password" placeholder="<?php echo $s['gemini_key'] !== '' ? '•••••••• (تنظیم شده — برای تغییر، مقدار جدید وارد کن)' : 'AIza...'; ?>" class="aco-full85">
                        <p class="description aco-key-set-note" data-has-key="<?php echo $s['gemini_key'] !== '' ? '1' : '0'; ?>"><?php echo $s['gemini_key'] !== '' ? 'برای حفظ کلید فعلی، این فیلد را خالی بگذار.' : ''; ?></p>
                        <p class="description">
                            کلید رایگان از <a href="https://aistudio.google.com/apikey" target="_blank" rel="noopener noreferrer">aistudio.google.com/apikey</a>
                        </p>
                    </div>
                    <div class="aco-field">
                        <label>مدل Gemini</label>
                        <input type="text" id="aco-gemini-model" value="<?php echo esc_attr( $s['gemini_model'] ); ?>" placeholder="gemini-3.8-flash" class="aco-full85">
                        <p class="description">پیشنهاد: <code>gemini-3.8-flash</code> یا <code>gemini-4-hydrogen</code>. ⚠️ برای محتوای پزشکی/روان‌شناسی، فیلتر ایمنی Gemini گاهی پاسخ را بلاک می‌کند.</p>
                    </div>
                </div>

                <!-- Anthropic -->
                <div id="aco-section-anthropic" class="aco-provider-section aco-hidden1">
                    <div class="aco-field">
                        <label>کلید API — Anthropic Claude</label>
                        <input type="password" id="aco-anthropic-key" value="" autocomplete="new-password" placeholder="<?php echo $s['anthropic_key'] !== '' ? '•••••••• (تنظیم شده — برای تغییر، مقدار جدید وارد کن)' : 'sk-ant-...'; ?>" class="aco-full85">
                        <p class="description aco-key-set-note" data-has-key="<?php echo $s['anthropic_key'] !== '' ? '1' : '0'; ?>"><?php echo $s['anthropic_key'] !== '' ? 'برای حفظ کلید فعلی، این فیلد را خالی بگذار.' : ''; ?></p>
                        <p class="description">
                            کلید از <a href="https://console.anthropic.com/settings/keys" target="_blank" rel="noopener noreferrer">console.anthropic.com</a>
                        </p>
                    </div>
                    <div class="aco-field">
                        <label>مدل Anthropic</label>
                        <input type="text" id="aco-anthropic-model" value="<?php echo esc_attr( $s['anthropic_model'] ); ?>" placeholder="claude-3-5-haiku-20241022" class="aco-full85">
                        <p class="description">نام دقیق مدل را از <a href="https://docs.claude.com/en/docs/about-claude/models" target="_blank" rel="noopener noreferrer">مستندات مدل‌های Claude</a> بررسی کنید — این شناسه‌ها به‌روزرسانی می‌شوند.</p>
                    </div>
                </div>

                </div><!-- /#aco-paid-providers-wrap -->

                <hr / class="aco-u86">
                <h3 class="aco-u87">⚙️ تنظیمات محتوا</h3>

                <div class="aco-field">
                    <label>
                        <input type="checkbox" id="aco-gutenberg-blocks-enabled" <?php checked( $s['gutenberg_blocks_enabled'] ); ?>>
                        خروجی به‌صورت بلاک‌های گوتنبرگ
                    </label>
                    <p class="description">وقتی روشن است، مقاله در post_content به‌صورت بلاک‌های واقعی (heading/paragraph/list/quote) ذخیره می‌شود که در ویرایشگر بلاکی قابل ویرایش تک‌به‌تک است. اگر از صفحه‌ساز دیگری (Elementor و…) روی این پست‌ها استفاده می‌کنید، خاموش کنید.</p>
                </div>

                <div class="aco-field">
                    <label>حوزه تخصصی سایت</label>
                    <input type="text" id="aco-niche" value="<?php echo esc_attr( $s['niche'] ); ?>" placeholder="مثال: روانشناسی و سلامت روان" / class="aco-full85">
                    <p class="description">این متن در prompt به مدل داده می‌شود تا محتوا متناسب با حوزه شما باشد.</p>
                </div>

                <div class="aco-field">
                    <label>لحن نوشتار</label>
                    <input type="text" id="aco-tone" value="<?php echo esc_attr( $s['tone'] ); ?>" placeholder="حرفه‌ای، قابل اعتماد و کاربردی" / class="aco-full85">
                    <p class="description">مثال: صمیمی و ساده، آکادمیک و رسمی، ژورنالیستی</p>
                </div>

                <div class="aco-field">
                    <label>حداقل تعداد کلمات مقاله</label>
                    <input type="number" id="aco-min-words" value="<?php echo esc_attr( $s['min_words'] ); ?>" min="500" max="5000" step="100" / class="aco-u88">
                    <p class="description">پیشنهاد: ۱۲۰۰ تا ۲۵۰۰ کلمه</p>
                </div>

                <div class="aco-field">
                    <label>سقف مصرف توکن ماهانه (کرم + بانک کلمات کلیدی)</label>
                    <input type="number" id="aco-monthly-token-budget" value="<?php echo esc_attr( $s['monthly_token_budget'] ?? 0 ); ?>" min="0" step="10000" class="aco-u88" />
                    <p class="description">
                        اگر مصرف توکن این ماه از این عدد بیشتر شود، اجرای خودکار شبانه (کرم و بانک کلمات کلیدی) تا ماه بعد متوقف می‌شود.
                        اجرای دستی («اجرای دستی الان») از این سقف عبور می‌کند. ۰ = بدون محدودیت.
                        مصرف فعلی این ماه را در «📊 توکن» ببینید.
                    </p>
                </div>

                <hr class="aco-u86" />
                <h3 class="aco-u143">🔗 اتصال به Google Search Console (اختیاری)</h3>
                <p class="description">
                    با اتصال این بخش، بانک کلمات کلیدی به‌جای حدس AI، از کوئری‌های واقعی سایت شما در گوگل
                    (که الان impression می‌گیرند ولی رتبه ۱ تا ۵۰ دارند) استفاده می‌کند، و برای مقاله‌های
                    منتشرشده، آمار واقعی کلیک/impression را هم پیگیری می‌کند.
                </p>

                <div class="aco-field">
                    <label>Site URL (دقیقاً مطابق ثبت در Search Console)</label>
                    <input type="text" id="aco-gsc-site-url" value="<?php echo esc_attr( $s['gsc_site_url'] ?? '' ); ?>" class="aco-u85" placeholder="sc-domain:example.com یا https://example.com/" dir="ltr" />
                    <p class="description">این مقدار را دقیقاً از داشبورد Search Console کپی کنید (پراپرتی دامنه یا پراپرتی URL-prefix).</p>
                </div>

                <div class="aco-field">
                    <label>JSON کلید Service Account</label>
                    <textarea id="aco-gsc-service-account" rows="4" class="aco-full85" dir="ltr" placeholder="محتوای فایل JSON دانلودشده از Google Cloud Console را اینجا Paste کنید"></textarea>
                    <p class="description">
                        این کلید مثل کلید API رمزنگاری‌شده ذخیره می‌شود. اگر خالی بگذارید و قبلاً ذخیره شده باشد، مقدار قبلی دست‌نخورده می‌ماند.
                        <?php if ( ! empty( $s['gsc_service_account_json'] ) ) : ?>
                        <br />✅ یک کلید از قبل ذخیره شده است.
                        <?php endif; ?>
                    </p>
                </div>
                <div class="aco-actions aco-flex149">
                    <button id="aco-btn-test-gsc" class="button">🔎 تست اتصال</button>
                </div>
                <div id="aco-gsc-msg" class="aco-hidden99"></div>

                <div class="aco-field">
                    <label>
                        <input type="checkbox" id="aco-featured-image-enabled" <?php checked( ! empty( $s['featured_image_enabled'] ) ); ?> />
                        تولید خودکار تصویر شاخص برای مقالات جدید
                    </label>
                    <p class="description">
                        منبع اصلی: Pollinations.ai (رایگان، بدون کلید). در صورت شکست، به ترتیب: اگر کلید OpenAI تنظیم شده باشد از
                        سرویس تخصصی تصویرسازی OpenAI (gpt-image-1/dall-e-3)، و در نهایت اگر کلید OpenRouter تنظیم شده باشد از آن،
                        به‌عنوان fallback هزینه‌بر استفاده می‌شود. <strong>در «حالت فقط رایگان» (بالای صفحه)، این دو fallback پولی
                        غیرفعال می‌شوند و فقط Pollinations امتحان می‌شود.</strong>
                    </p>
                </div>

                <div class="aco-field">
                    <label>نام‌ها/کلیدواژه‌های محافظت‌شده تصویر (هر خط یکی)</label>
                    <textarea id="aco-protected-image-keywords" rows="3" class="aco-full85" placeholder="مثال: مهناز خوینی"><?php
                        echo esc_textarea( implode( "\n", (array) ( $s['protected_image_keywords'] ?? [] ) ) );
                    ?></textarea>
                    <p class="description">
                        اگر عنوان، alt، یا نام فایل تصویر شاخص یک مقاله شامل هرکدام از این نام‌ها باشد، آن تصویر هرگز توسط کرم یا بازتولید دستی جایگزین نمی‌شود.
                    </p>
                </div>

                <div class="aco-field">
                    <label>صفحات مهم برای لینک داخلی</label>
                    <p class="description aco-u89">
                        این صفحات در prompt بازنویسی به مدل معرفی می‌شوند تا در جاهای مناسب متن لینک بگذارد.
                    </p>
                    <div id="aco-link-pages-list" class="aco-flex90">
                        <?php
                        $link_pages = $s['internal_link_pages'] ?? [];
                        // BUG-P1 FIX: get_pages() را یک‌بار فراخوانی می‌کنیم و cache می‌کنیم
                        // قبلاً برای هر ردیف موجود و همچنین برای template جداگانه فراخوانی می‌شد — N+1 query
                        $all_site_pages = get_pages( [ 'sort_column' => 'post_title', 'sort_order' => 'ASC' ] );
                        if ( ! empty( $link_pages ) ) :
                            foreach ( $link_pages as $idx => $lp ) :
                                $lp_title = esc_attr( $lp['title'] ?? '' );
                                $lp_url   = esc_attr( $lp['url']   ?? '' );
                        ?>
                        <div class="aco-link-page-row aco-flex91">
                            <select class="aco-link-page-select aco-u92">
                                <option value="">— انتخاب صفحه —</option>
                                <?php
                                foreach ( $all_site_pages as $pg ) {
                                    $sel = selected( get_permalink( $pg->ID ), urldecode( $lp['url'] ), false );
                                    printf( '<option value="%s" %s>%s</option>',
                                        esc_attr( get_permalink( $pg->ID ) ),
                                        $sel,
                                        esc_html( $pg->post_title )
                                    );
                                }
                                ?>
                            </select>
                            <span class="aco-soft93">یا</span>
                            <input type="text" class="aco-link-page-title aco-u94" placeholder="عنوان لینک" value="<?php echo esc_attr( $lp_title ); ?>" />
                            <input type="url" class="aco-link-page-url aco-u95" placeholder="https://..." value="<?php echo esc_attr( $lp_url ); ?>" />
                            <input type="text" class="aco-link-page-desc aco-u96" placeholder="توضیح کوتاه (مثلاً: صفحه خدمات روانشناسی)" value="<?php echo esc_attr( $lp['description'] ?? '' ); ?>" />
                            <button type="button" class="button aco-link-page-remove aco-danger97">✕</button>
                        </div>
                        <?php endforeach; endif; ?>
                    </div>
                    <button type="button" id="aco-btn-add-link-page" class="button">➕ افزودن صفحه</button>
                    <!-- template row — hidden -->
                    <template id="aco-link-page-row-tpl">
                        <div class="aco-link-page-row aco-flex91">
                            <select class="aco-link-page-select aco-u92">
                                <option value="">— انتخاب صفحه —</option>
                                <?php
                                foreach ( $all_site_pages as $pg ) {
                                    printf( '<option value="%s">%s</option>',
                                        esc_attr( get_permalink( $pg->ID ) ),
                                        esc_html( $pg->post_title )
                                    );
                                }
                                ?>
                            </select>
                            <span class="aco-soft93">یا</span>
                            <input type="text" class="aco-link-page-title aco-u94" placeholder="عنوان لینک" aria-label="عنوان لینک" />
                            <input type="url" class="aco-link-page-url aco-u95" placeholder="https://..." aria-label="https://..." />
                            <input type="text" class="aco-link-page-desc aco-u96" placeholder="توضیح کوتاه (مثلاً: صفحه خدمات روانشناسی)" aria-label="توضیح کوتاه (مثلاً: صفحه خدمات روانشناسی)" />
                            <button type="button" class="button aco-link-page-remove aco-danger97">✕</button>
                        </div>
                    </template>
                </div>

                <div class="aco-field">
                    <label>وضعیت پیش‌فرض پست‌ها</label>
                    <select id="aco-default-status">
                        <option value="draft"   <?php selected( $s['default_status'], 'draft'   ); ?>>پیش‌نویس</option>
                        <option value="pending" <?php selected( $s['default_status'], 'pending' ); ?>>در انتظار بررسی</option>
                        <option value="publish" <?php selected( $s['default_status'], 'publish' ); ?>>منتشر شده</option>
                    </select>
                </div>

                <div class="aco-field">
                    <label>نویسنده پیش‌فرض پست‌ها</label>
                    <select id="aco-default-author">
                        <?php
                        // آرگومان 'who' از وردپرس 5.9 deprecated است؛ جایگزین رسمی آن capability است.
                        $authors    = get_users( [ 'capability' => 'edit_posts', 'fields' => [ 'ID', 'display_name' ] ] );
                        $current_id = absint( $s['default_author'] ?? 1 );
                        foreach ( $authors as $u ) {
                            printf(
                                '<option value="%d" %s>%s</option>',
                                $u->ID,
                                selected( $current_id, $u->ID, false ),
                                esc_html( $u->display_name )
                            );
                        }
                        ?>
                    </select>
                    <p class="description">پست‌های تولیدشده به این کاربر نسبت داده می‌شوند.</p>
                </div>

                <div class="aco-actions aco-flex98">
                    <button id="aco-btn-save-settings" class="button button-primary">💾 ذخیره تنظیمات</button>
                    <button id="aco-btn-test-api" class="button">🔌 تست اتصال</button>
                </div>

                <div id="aco-settings-msg" class="aco-hidden99"></div>

            </div>
        </div>
        <?php
    }

    // ─────────────────────────────────────
    // AJAX: تولید محتوا
    // ─────────────────────────────────────
    /**
     * @deprecated استفاده نمی‌شود — به‌جای آن از ajax_research + ajax_write_save استفاده کنید.
     *             این متد حذف شد تا از timeout دو API call پشت‌سرهم جلوگیری شود.
     */
    // public static function ajax_generate(): void { ... }

    // ─────────────────────────────────────
    // AJAX: مرحله ۱ — فقط تحقیق و آوتلاین
    // (سریع — برای جلوگیری از قطع شدن توسط max_execution_time هاست)
    // ─────────────────────────────────────

    public static function page_queue(): void {
        $counts = ACO_Queue::get_counts();
        // FIX: فقط statusهای معتبر قبول می‌شوند — جلوگیری از XSS در add_query_arg
        $allowed_statuses = [ '', 'pending', 'processing', 'done', 'failed' ];
        $filter_raw = sanitize_text_field( wp_unslash( $_GET['status'] ?? '' ) );
        $filter = in_array( $filter_raw, $allowed_statuses, true ) ? $filter_raw : '';
        $paged  = max( 1, absint( $_GET['qpaged'] ?? 1 ) );
        $items  = ACO_Queue::get_items( [ 'status' => $filter, 'per_page' => 20, 'paged' => $paged ] );
        $status_labels = [ 'pending' => 'در انتظار', 'processing' => 'در حال پردازش', 'done' => 'انجام شده', 'failed' => 'خطا' ];
        $status_colors = [ 'pending' => '#d97706', 'processing' => '#0f766e', 'done' => '#14b8a6', 'failed' => '#e11d48' ];
        ?>
        <div class="wrap aco-wrap" dir="rtl">
            <h1 class="aco-page-title">
                <span class="dashicons dashicons-list-view"></span>
                صف تولید محتوا
            </h1>

            <!-- آمار -->
            <div class="aco-stats aco-u100">
                <?php foreach ( $counts as $st => $cnt ) : ?>
                <div class="aco-stat" style="border-top:3px solid <?php echo esc_attr( $status_colors[ $st ] ?? '#94a3b8' ); ?>">
                    <span class="stat-number"><?php echo esc_html( $cnt ); ?></span>
                    <span class="stat-label"><?php echo esc_html( $status_labels[ $st ] ?? $st ); ?></span>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- فرم افزودن به صف -->
            <div class="aco-card aco-u101">
                <h2 class="aco-card-title">➕ افزودن به صف</h2>

                <!-- IMPROVE #2: تب تکی / دسته‌جمعی -->
                <div class="aco-flex102">
                    <button id="aco-q-tab-single" class="aco-q-tab aco-q-tab--active aco-text103">
                        ✏️ یک مقاله
                    </button>
                    <button id="aco-q-tab-bulk" class="aco-q-tab aco-soft104">
                        📋 دسته‌جمعی
                    </button>
                </div>

                <!-- فرم تکی -->
                <div id="aco-q-panel-single">
                    <div class="aco-field">
                        <label>کلمه کلیدی</label>
                        <input type="text" id="aco-q-keyword" placeholder="مثال: درمان اضطراب" / class="aco-full2">
                    </div>
                    <div class="aco-field">
                        <label>عنوان مقاله</label>
                        <input type="text" id="aco-q-topic" placeholder="مثال: روش‌های درمان اضطراب اجتماعی" / class="aco-full2">
                    </div>
                    <div class="aco-field aco-flex98">
                        <div class="aco-u105">
                            <label>وضعیت انتشار</label>
                            <select id="aco-q-status" class="aco-full85">
                                <option value="draft">پیش‌نویس</option>
                                <option value="publish">منتشر شده</option>
                                <option value="future">زمان‌بندی شده</option>
                            </select>
                        </div>
                        <div id="aco-q-schedule-wrap" class="aco-hidden106">
                            <label>تاریخ انتشار</label>
                            <input type="text" id="aco-q-scheduled-at" dir="ltr" inputmode="numeric"
                                   placeholder="1405/08/27 14:30" autocomplete="off" class="aco-full2">
                        </div>
                    </div>
                    <div class="aco-actions">
                        <button id="aco-btn-queue-add" class="button button-primary">📋 افزودن به صف</button>
                    </div>
                </div>

                <!-- IMPROVE #2: فرم دسته‌جمعی -->
                <div id="aco-q-panel-bulk" class="aco-hidden1">
                    <p class="aco-soft107">
                        هر خط یک مقاله — فرمت: <code>کلمه کلیدی | عنوان مقاله</code><br>
                        اگر عنوان ننویسی، از کلمه کلیدی به عنوان عنوان استفاده می‌شود.
                    </p>
                    <div class="aco-field">
                        <label>لیست مقالات</label>
                        <textarea id="aco-q-bulk-lines" rows="8" placeholder="درمان اضطراب | روش‌های درمان اضطراب اجتماعی
افسردگی فصلی | علائم و درمان افسردگی فصلی
بیماری قند | همه چیز درباره دیابت نوع ۲" class="aco-full108"></textarea>
                    </div>
                    <div class="aco-field aco-flex109">
                        <div class="aco-u105">
                            <label>وضعیت انتشار (برای همه)</label>
                            <select id="aco-q-bulk-status" class="aco-full85">
                                <option value="draft">پیش‌نویس</option>
                                <option value="publish">منتشر شده</option>
                            </select>
                        </div>
                        <div>
                            <button id="aco-btn-queue-bulk" class="button button-primary">📋 افزودن همه به صف</button>
                        </div>
                    </div>
                    <div id="aco-bulk-progress" class="aco-hidden110">
                        <div class="aco-u111">
                            <div id="aco-bulk-bar" class="aco-u112"></div>
                        </div>
                        <p id="aco-bulk-status-text" class="aco-soft113"></p>
                    </div>
                </div>

                <div id="aco-queue-msg" class="aco-hidden114"></div>
            </div>

            <!-- جدول صف -->
            <div class="aco-card aco-u19">
                <!-- فیلتر وضعیت -->
                <div class="aco-flex115">
                    <?php
                    $all_filters = array_merge( [ '' => 'همه' ], array_combine( array_keys( $status_labels ), array_values( $status_labels ) ) );
                    foreach ( $all_filters as $st => $label ) :
                        $url = add_query_arg( [ 'page' => 'aco-queue', 'status' => $st, 'qpaged' => 1 ], admin_url( 'admin.php' ) );
                        $active = $filter === $st;
                    ?>
                    <a href="<?php echo esc_url( $url ); ?>" class="button <?php echo $active ? 'button-primary' : ''; ?> aco-text68">
                        <?php echo esc_html( $label ); ?>
                        <?php if ( $st && isset( $counts[ $st ] ) ) echo '(' . absint( $counts[ $st ] ) . ')'; ?>
                    </a>
                    <?php endforeach; ?>
                </div>

                <?php if ( $items['rows'] ) : ?>
                <table class="widefat striped aco-u69">
                    <thead>
                        <tr>
                            <th class="aco-right70">#</th>
                            <th class="aco-right70">کلمه کلیدی / عنوان</th>
                            <th class="aco-right70">وضعیت</th>
                            <th class="aco-right70">زمان‌بندی</th>
                            <th class="aco-right70">سئو</th>
                            <th class="aco-right70">پست</th>
                            <th class="aco-right70">عملیات</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ( $items['rows'] as $item ) :
                        $color = $status_colors[ $item->status ] ?? '#94a3b8';
                    ?>
                        <tr>
                            <td><?php echo esc_html( $item->id ); ?></td>
                            <td>
                                <strong><?php echo esc_html( $item->keyword ); ?></strong><br>
                                <small class="aco-soft116"><?php echo esc_html( $item->topic ); ?></small>
                                <?php if ( $item->error_message ) : ?>
                                <br><small class="aco-danger97">⚠️ <?php echo esc_html( mb_substr( $item->error_message, 0, 80 ) ); ?></small>
                                <?php endif; ?>
                            </td>
                            <td><span style="background:<?php echo esc_attr( $color ); ?>;color:#fff;padding:2px 8px;border-radius:12px;font-size:12px"><?php echo esc_html( $status_labels[ $item->status ] ?? $item->status ); ?></span></td>
                            <td><?php echo $item->scheduled_at ? esc_html( self::jalali_dt( (string) $item->scheduled_at ) ) : wp_kses( '<span class="aco-soft72">—</span>', [ 'span' => [ 'style' => [] ] ] ); ?></td>
                            <td><?php echo $item->seo_score !== null ? esc_html( $item->seo_score ) . '/۱۰۰' : wp_kses( '<span class="aco-soft72">—</span>', [ 'span' => [ 'style' => [] ] ] ); ?></td>
                            <td>
                                <?php if ( $item->post_id ) : ?>
                                <a href="<?php echo esc_url( get_edit_post_link( $item->post_id ) ); ?>" target="_blank" rel="noopener noreferrer" class="button button-small">ویرایش</a>
                                <?php else : ?>
                                <span class="aco-soft72">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="aco-flex117">
                                <?php if ( $item->status === 'failed' ) : ?>
                                <button class="button button-small aco-q-retry" data-id="<?php echo esc_attr( $item->id ); ?>">🔁 تلاش مجدد</button>
                                <?php endif; ?>
                                <button class="button button-small aco-q-delete aco-danger97" data-id="<?php echo esc_attr( $item->id ); ?>">🗑 حذف</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>

                <?php if ( $items['pages'] > 1 ) : ?>
                <div class="aco-center118">
                    <?php echo paginate_links( [
                        'base'      => add_query_arg( 'qpaged', '%#%' ),
                        'format'    => '',
                        'current'   => $paged,
                        'total'     => $items['pages'],
                        'prev_text' => '&raquo;',
                        'next_text' => '&laquo;',
                    ] ); ?>
                </div>
                <?php endif; ?>

                <?php else : ?>
                <div class="aco-soft-center119">
                    <p>هیچ آیتمی در صف وجود ندارد.</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    // ─────────────────────────────────────
    // صفحه لاگ رویدادها
    // ─────────────────────────────────────

    public static function page_logs(): void {
        // FIX: فقط levelهای معتبر قبول می‌شوند
        $allowed_levels = [ '', 'info', 'success', 'warning', 'error' ];
        $filter_level_raw = sanitize_text_field( wp_unslash( $_GET['level'] ?? '' ) );
        $filter_level = in_array( $filter_level_raw, $allowed_levels, true ) ? $filter_level_raw : '';
        $paged        = max( 1, absint( $_GET['lpaged'] ?? 1 ) );
        $logs         = ACO_Logger::get_logs( [ 'level' => $filter_level, 'per_page' => 50, 'paged' => $paged ] );
        $level_colors = [ 'info' => '#0f766e', 'success' => '#14b8a6', 'warning' => '#d97706', 'error' => '#e11d48' ];
        $level_labels = [ 'info' => 'اطلاعات', 'success' => 'موفق', 'warning' => 'هشدار', 'error' => 'خطا' ];
        ?>
        <div class="wrap aco-wrap" dir="rtl">
            <h1 class="aco-page-title">
                <span class="dashicons dashicons-text-page"></span>
                لاگ رویدادها
                <button id="aco-btn-logs-clear" class="button aco-text120">🗑 پاک‌سازی همه</button>
            </h1>

            <div class="aco-card aco-u19">
                <!-- فیلتر سطح -->
                <div class="aco-flex115">
                    <?php
                    $all_levels = array_merge( [ '' => 'همه' ], $level_labels );
                    foreach ( $all_levels as $lv => $lbl ) :
                        $url = add_query_arg( [ 'page' => 'aco-logs', 'level' => $lv, 'lpaged' => 1 ], admin_url( 'admin.php' ) );
                        $active = $filter_level === $lv;
                    ?>
                    <a href="<?php echo esc_url( $url ); ?>" class="button <?php echo $active ? 'button-primary' : ''; ?> aco-text68"><?php echo esc_html( $lbl ); ?></a>
                    <?php endforeach; ?>
                </div>

                <?php if ( $logs['rows'] ) : ?>
                <table class="widefat aco-text121">
                    <thead>
                        <tr>
                            <th class="aco-right122">زمان</th>
                            <th class="aco-right123">سطح</th>
                            <th class="aco-right124">موضوع</th>
                            <th class="aco-right70">پیام</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ( $logs['rows'] as $row ) :
                        $color = $level_colors[ $row->level ] ?? '#94a3b8';
                    ?>
                        <tr>
                            <td class="aco-soft125"><?php echo esc_html( $row->created_at ); ?></td>
                            <td><span style="background:<?php echo esc_attr( $color ); ?>;color:#fff;padding:2px 8px;border-radius:12px;font-size:11px"><?php echo esc_html( $level_labels[ $row->level ] ?? $row->level ); ?></span></td>
                            <td class="aco-soft116"><?php echo esc_html( $row->context ); ?></td>
                            <td>
                                <?php echo esc_html( $row->message ); ?>
                                <?php if ( $row->data ) : ?>
                                <details class="aco-u126">
                                    <summary class="aco-soft127">جزئیات</summary>
                                    <pre class="aco-text128"><?php echo esc_html( $row->data ); ?></pre>
                                </details>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>

                <?php if ( $logs['pages'] > 1 ) : ?>
                <div class="aco-center118">
                    <?php echo paginate_links( [
                        'base'      => add_query_arg( 'lpaged', '%#%' ),
                        'format'    => '',
                        'current'   => $paged,
                        'total'     => $logs['pages'],
                        'prev_text' => '&raquo;',
                        'next_text' => '&laquo;',
                    ] ); ?>
                </div>
                <?php endif; ?>

                <?php else : ?>
                <div class="aco-soft-center119"><p>هیچ لاگی ثبت نشده است.</p></div>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    // ─────────────────────────────────────
    // AJAX: افزودن به صف
    // ─────────────────────────────────────

    public static function page_tokens(): void {
        $stats = ACO_Logger::get_token_stats();
        $daily = $stats['daily'];
        ?>
        <div class="wrap aco-wrap" dir="rtl">
            <h1 class="aco-page-title">
                <span class="dashicons dashicons-chart-bar"></span>
                آمار مصرف توکن هوش مصنوعی
            </h1>

            <!-- کارت‌های خلاصه -->
            <div class="aco-stats aco-u129">
                <div class="aco-stat aco-u130">
                    <span class="stat-number"><?php echo esc_html( number_format( (int) $stats['total_today'] ) ); ?></span>
                    <span class="stat-label">توکن امروز</span>
                </div>
                <div class="aco-stat aco-u131">
                    <span class="stat-number"><?php echo esc_html( number_format( (int) $stats['total_month'] ) ); ?></span>
                    <span class="stat-label">توکن این ماه</span>
                </div>
                <div class="aco-stat aco-u132">
                    <span class="stat-number"><?php echo esc_html( number_format( (int) $stats['total_all'] ) ); ?></span>
                    <span class="stat-label">کل توکن‌های مصرفی</span>
                </div>
            </div>

            <!-- نمودار ۷ روز اخیر -->
            <?php if ( ! empty( $daily ) ) : ?>
            <div class="aco-card aco-u84">
                <h2 class="aco-card-title">📈 مصرف ۷ روز اخیر</h2>
                <?php
                $max_tokens = max( array_column( (array) $daily, 'tokens' ) );
                foreach ( $daily as $row ) :
                    $pct = $max_tokens > 0 ? round( ( $row->tokens / $max_tokens ) * 100 ) : 0;
                ?>
                <div class="aco-flex133">
                    <span class="aco-soft-right134"><?php echo esc_html( $row->day ); ?></span>
                    <div class="aco-u135">
                        <div style="width:<?php echo intval( $pct ); ?>%;background:#0f766e;height:100%;border-radius:4px;transition:width .3s"></div>
                    </div>
                    <span class="aco-text136"><?php echo number_format( (int) $row->tokens ); ?></span>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else : ?>
            <div class="aco-card aco-soft-center83">
                <p>هنوز اطلاعات توکنی ثبت نشده. بعد از تولید اولین مقاله اینجا نمایش داده می‌شود.</p>
            </div>
            <?php endif; ?>
        </div>
        <?php
    }

    // ─────────────────────────────────────
    // صفحه تنظیمات کرم محتوا
    // ─────────────────────────────────────

    public static function page_crawler(): void {
        $s   = ACO_Settings::get( 'crawler' );
        if ( ! is_array( $s ) ) $s = [];
        $s   = wp_parse_args( $s, [
            'enabled'         => false,
            'interval_days'   => 30,
            'seo_threshold'   => 60,
            'min_links'       => 2,
            'stale_months'    => 6,
            'posts_per_night' => 5,
            'run_hour'        => 3,
            'review_mode'     => true,
        ] );

        $next_run = wp_next_scheduled( ACO_Crawler::CRON_HOOK );
        ?>
        <div class="wrap aco-wrap" dir="rtl">
            <h1 class="aco-page-title">
                <span class="dashicons dashicons-update"></span>
                کرم محتوا
            </h1>

            <div class="aco-card aco-u84">
                <p class="aco-u137">
                    کرم محتوا هر شب ساعت ۰۳:۰۰ به‌صورت خودکار پست‌های منتشرشده را بررسی می‌کند،
                    مقالاتی که سئوی ضعیف دارند یا قدیمی شده‌اند را بازنویسی می‌کند،
                    و به مقالاتی که کم‌لینک هستند لینک داخلی اضافه می‌کند.
                </p>

                <div class="aco-field">
                    <label class="aco-flex138">
                        <input type="checkbox" id="aco-crawler-enabled" <?php checked( ! empty( $s['enabled'] ) ); ?> class="aco-u139" />
                        <span class="aco-text140">فعال‌سازی کرم محتوا</span>
                    </label>
                    <?php if ( $next_run ) : ?>
                    <p class="description aco-u141">
                        ⏰ اجرای بعدی: <?php echo esc_html( get_date_from_gmt( gmdate( 'Y-m-d H:i:s', $next_run ), 'j F Y — H:i' ) ); ?>
                    </p>
                    <?php else : ?>
                    <p class="description aco-u142">⚠️ cron زمان‌بندی نشده. پس از ذخیره فعال می‌شود.</p>
                    <?php endif; ?>
                </div>

                <hr class="aco-u86" />
                <h3 class="aco-u143">🔁 چرخه بررسی</h3>

                <div class="aco-field">
                    <label>هر پست هر چند روز بررسی شود</label>
                    <input type="number" id="aco-crawler-interval-days"
                           value="<?php echo esc_attr( $s['interval_days'] ); ?>"
                           min="1" max="365" step="1" class="aco-u144" />
                    <span class="aco-soft145">روز</span>
                    <p class="description">پیشنهاد: ۳۰ روز. در سایت‌های بزرگ می‌توانید ۶۰–۹۰ روز تنظیم کنید.</p>
                </div>

                <div class="aco-field">
                    <label>ساعت اجرای شبانه</label>
                    <input type="number" id="aco-crawler-run-hour"
                           value="<?php echo esc_attr( $s['run_hour'] ); ?>"
                           min="0" max="23" step="1" class="aco-u144" />
                    <span class="aco-soft145">(۰ تا ۲۳، به وقت سایت)</span>
                    <p class="description">پیش‌فرض: ۳ (یعنی ۰۳:۰۰ بامداد). بعد از تغییر، حتماً «ذخیره تنظیمات کرم» را بزنید.</p>
                </div>

                <div class="aco-field">
                    <label>حداکثر تعداد پست در هر شب</label>
                    <input type="number" id="aco-crawler-posts-per-night"
                           value="<?php echo esc_attr( $s['posts_per_night'] ); ?>"
                           min="1" max="50" step="1" class="aco-u144" />
                    <span class="aco-soft145">پست</span>
                    <p class="description">برای کنترل مصرف API. پیشنهاد: ۵–۱۰ پست.</p>
                </div>

                <hr class="aco-u86" />
                <h3 class="aco-u143">📊 معیارهای بازنویسی</h3>

                <div class="aco-field">
                    <label>بازنویسی اگر امتیاز سئو زیر این باشد</label>
                    <input type="number" id="aco-crawler-seo-threshold"
                           value="<?php echo esc_attr( $s['seo_threshold'] ); ?>"
                           min="0" max="100" step="5" class="aco-u144" />
                    <span class="aco-soft145">از ۱۰۰</span>
                    <p class="description">پیشنهاد: ۶۰. مقالاتی که امتیاز سئو ندارند (صفر) نادیده گرفته می‌شوند.</p>
                </div>

                <div class="aco-field">
                    <label>بازنویسی اگر بیشتر از این مدت ویرایش نشده باشد</label>
                    <input type="number" id="aco-crawler-stale-months"
                           value="<?php echo esc_attr( $s['stale_months'] ); ?>"
                           min="1" max="60" step="1" class="aco-u144" />
                    <span class="aco-soft145">ماه</span>
                    <p class="description">پیشنهاد: ۶ ماه. محتوای قدیمی حتی با سئوی خوب بازنویسی می‌شود.</p>
                </div>

                <hr class="aco-u86" />
                <h3 class="aco-u143">🔗 معیار لینک داخلی</h3>

                <div class="aco-field">
                    <label>اگر تعداد لینک داخلی کمتر از این بود → لینک inject</label>
                    <input type="number" id="aco-crawler-min-links"
                           value="<?php echo esc_attr( $s['min_links'] ); ?>"
                           min="0" max="20" step="1" class="aco-u144" />
                    <span class="aco-soft145">لینک</span>
                    <p class="description">لینک inject رایگان است (بدون API call). صفر = این چک غیرفعال.</p>
                </div>

                <hr class="aco-u86" />
                <h3 class="aco-u143">✅ بازبینی قبل از انتشار</h3>

                <div class="aco-field aco-u146">
                    <label class="aco-flex138">
                        <input type="checkbox" id="aco-crawler-review-mode" <?php checked( ! empty( $s['review_mode'] ) ); ?> class="aco-u139" />
                        <span class="aco-text147">بازنویسی خودکار کرم مستقیم منتشر نشود — منتظر تأیید من بماند</span>
                    </label>
                    <p class="description aco-u148">
                        وقتی روشن است، کرم فقط یک <strong>پیشنهاد بازنویسی</strong> آماده می‌کند و محتوای زنده‌ی سایت
                        تغییر نمی‌کند تا شما آن را از صفحه‌ی «گزارش به‌روزرسانی» تأیید یا رد کنید.
                        وقتی خاموش است، بازنویسی بلافاصله جای محتوای فعلی را می‌گیرد (بدون بازبینی).
                        <br>لینک‌سازی داخلی (link inject) همیشه فوری انجام می‌شود — چون فقط یک لینک اضافه می‌کند، نه کل متن.
                    </p>
                </div>

                <div class="aco-actions aco-flex149">
                    <button id="aco-btn-save-crawler" class="button button-primary">💾 ذخیره تنظیمات کرم</button>
                    <button id="aco-btn-run-crawler-now" class="button">▶️ اجرای دستی الان (تست)</button>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=aco-crawler-report' ) ); ?>" class="button">📋 مشاهده گزارش</a>
                </div>

                <div id="aco-crawler-msg" class="aco-hidden99"></div>
            </div>
        </div>
        <?php
    }

    // ─────────────────────────────────────
    // صفحه گزارش کرم محتوا
    // ─────────────────────────────────────

    public static function page_crawler_report(): void {
        $reports = ACO_Crawler::get_reports();
        $totals  = ACO_Crawler::get_totals();
        $latest  = $reports[0] ?? null;
        ?>
        <div class="wrap aco-wrap" dir="rtl">
            <h1 class="aco-page-title">
                <span class="dashicons dashicons-chart-area"></span>
                گزارش به‌روزرسانی مقالات
            </h1>
            <p class="aco-soft150">
                همه‌ی به‌روزرسانی‌های خودکار — چه از کرم محتوا، چه از صف پردازش — اینجا یک‌جا و با وضعیت واقعی نمایش داده می‌شود.
            </p>

            <?php
            // ── در انتظار بازبینی — بازنویسی‌های خودکار کرم که هنوز منتشر نشده‌اند ──
            $pending_reviews = ACO_Generator::get_pending_reviews();
            if ( ! empty( $pending_reviews ) ) :
            ?>
            <div class="aco-card aco-u151">
                <h2 class="aco-card-title aco-u152">
                    ⏸️ در انتظار بازبینی شما
                    <span class="aco-text153">(<?php echo count( $pending_reviews ); ?> مورد — کرم منتشرشان نکرده تا شما تأیید کنید)</span>
                </h2>

                <?php foreach ( $pending_reviews as $pr ) : ?>
                <div class="aco-pending-review-item aco-u154" data-post-id="<?php echo esc_attr( $pr['post_id'] ); ?>"
                    >
                    <div class="aco-flex155">
                        <div>
                            <a href="<?php echo esc_url( get_edit_post_link( $pr['post_id'] ) ); ?>" target="_blank" rel="noopener noreferrer" class="aco-text156">
                                <?php echo esc_html( $pr['title'] ); ?>
                            </a>
                            <p class="aco-soft157">
                                پیشنهاد شده: <?php echo esc_html( $pr['date'] ? self::jalali_dt( (string) $pr['date'] ) : '—' ); ?>
                                · تعداد کلمات: <?php echo esc_html( $pr['word_count'] ); ?>
                                · سئو:
                                <strong class="aco-soft116"><?php echo esc_html( $pr['score_before'] ); ?></strong>
                                ←
                                <strong style="color:<?php echo $pr['score_after'] >= 70 ? '#10b981' : '#f59e0b'; ?>"><?php echo esc_html( $pr['score_after'] ); ?></strong>
                            </p>
                        </div>
                        <div class="aco-flex158">
                            <button class="button aco-btn-toggle-preview">✏️ نمایش و ویرایش پیش‌نویس</button>
                            <button class="button button-primary aco-btn-approve-rewrite aco-success159">✅ تأیید و انتشار</button>
                            <button class="button aco-btn-reject-rewrite aco-danger160">❌ رد کردن</button>
                        </div>
                    </div>
                    <div class="aco-pending-preview aco-hidden161">
                        <p class="aco-soft162">
                            می‌توانید متن را قبل از انتشار ویرایش کنید. اگر با ویرایش هم درست نشد، همین‌جا «رد کردن» را بزنید.
                        </p>
                        <textarea class="aco-pending-edit-textarea aco-full163" rows="18"
                                 
                        ><?php echo esc_textarea( $pr['content'] ); ?></textarea>
                    </div>
                    <div class="aco-pending-review-msg aco-hidden164"></div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <!-- آمار کلی -->
            <div class="aco-flex165">
                <?php
                $stats = [
                    [ 'label' => 'کل بررسی‌شده',     'val' => $totals['scanned'],     'color' => '#0f766e', 'icon' => '🔍' ],
                    [ 'label' => 'بازنویسی‌شده',      'val' => $totals['rewrite'],     'color' => '#8b5cf6', 'icon' => '✏️' ],
                    [ 'label' => 'لینک inject',        'val' => $totals['link_inject'], 'color' => '#0f766e', 'icon' => '🔗' ],
                    [ 'label' => 'بدون تغییر',        'val' => $totals['none'],        'color' => '#64748b', 'icon' => '✅' ],
                ];
                foreach ( $stats as $st ) : ?>
                <div class="aco-card aco-center166">
                    <div class="aco-text167"><?php echo esc_html( $st['icon'] ); ?></div>
                    <div style="font-size:26px;font-weight:700;color:<?php echo esc_attr( $st['color'] ); ?>"><?php echo number_format( (int) $st['val'] ); ?></div>
                    <div class="aco-soft168"><?php echo esc_html( $st['label'] ); ?></div>
                </div>
                <?php endforeach; ?>
            </div>

            <?php
            // ── تاریخچه‌ی یکپارچه‌ی به‌روزرسانی‌ها (صف + کرم، همه در یک جدول) ──────
            // این بخش دقیقاً پاسخ به «کدام مقاله‌ها به‌روز شدند» است — بدون نیاز به
            // چک‌کردن دو صفحه‌ی جدا (صف پردازش + گزارش کرم).
            $hpaged   = max( 1, absint( $_GET['hpaged'] ?? 1 ) );
            $per_page = 20;

            $queue_hist  = ACO_Queue::get_resolved_history( [ 'per_page' => 300 ] );
            $link_events = ACO_Crawler::get_link_inject_events();

            $badge_map = [
                'generate'     => [ 'label' => '✍️ تولید جدید (صف)',        'color' => '#0f766e' ],
                'rewrite'      => [ 'label' => '🔧 بهبود دستی (صف)',        'color' => '#8b5cf6' ],
                'audit_update' => [ 'label' => '🐛 بازنویسی خودکار (کرم)',  'color' => '#f59e0b' ],
            ];

            $merged = [];
            foreach ( $queue_hist['rows'] as $q ) {
                $target_post_id = ( $q->job_type === 'generate' ) ? (int) $q->post_id : (int) $q->rewrite_post_id;
                $live_title      = $target_post_id ? get_the_title( $target_post_id ) : '';
                $merged[] = [
                    'date'    => $q->finished_at ?: $q->created_at,
                    'post_id' => $target_post_id,
                    'title'   => $live_title !== '' ? $live_title : ( $q->topic ?: '—' ),
                    'badge'   => $badge_map[ $q->job_type ] ?? [ 'label' => $q->job_type, 'color' => '#64748b' ],
                    'status'  => $q->status,
                    'seo'     => $q->seo_score,
                    'error'   => $q->error_message,
                ];
            }
            foreach ( $link_events as $e ) {
                $live_title = $e['post_id'] ? get_the_title( $e['post_id'] ) : '';
                $merged[] = [
                    'date'    => $e['date'],
                    'post_id' => $e['post_id'],
                    'title'   => $live_title !== '' ? $live_title : ( $e['title'] ?: '—' ),
                    'badge'   => [ 'label' => '🔗 لینک‌سازی خودکار (کرم)', 'color' => '#0f766e' ],
                    'status'  => 'done',
                    'seo'     => $e['seo_after'],
                    'error'   => '',
                ];
            }

            usort( $merged, static function( $a, $b ) {
                return strtotime( $b['date'] ?: '1970-01-01 00:00:00' ) <=> strtotime( $a['date'] ?: '1970-01-01 00:00:00' );
            } );

            $total_merged = count( $merged );
            $total_hpages = max( 1, (int) ceil( $total_merged / $per_page ) );
            $hpaged       = min( $hpaged, $total_hpages );
            $page_slice   = array_slice( $merged, ( $hpaged - 1 ) * $per_page, $per_page );
            ?>

            <div class="aco-card aco-u129">
                <h2 class="aco-card-title aco-u169">
                    📚 تاریخچه‌ی کامل به‌روزرسانی‌ها
                    <span class="aco-soft170">(<?php echo esc_html( $total_merged ); ?> مورد)</span>
                </h2>

                <?php if ( empty( $page_slice ) ) : ?>
                <p class="aco-soft-center171">هنوز هیچ به‌روزرسانی‌ای (نه از صف، نه از کرم) ثبت نشده.</p>
                <?php else : ?>
                <table class="widefat striped aco-text121">
                    <thead>
                        <tr>
                            <th class="aco-u172">تاریخ</th>
                            <th class="aco-u173">عنوان مقاله</th>
                            <th class="aco-u174">دسته</th>
                            <th class="aco-u175">سئو</th>
                            <th class="aco-u176">وضعیت</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ( $page_slice as $row ) : ?>
                        <tr>
                            <td class="aco-text177">
                                <?php echo esc_html( $row['date'] ? self::jalali_dt( (string) $row['date'] ) : '—' ); ?>
                            </td>
                            <td>
                                <?php if ( $row['post_id'] ) : ?>
                                <a href="<?php echo esc_url( get_edit_post_link( $row['post_id'] ) ); ?>" target="_blank" rel="noopener noreferrer" class="aco-u178">
                                    <?php echo esc_html( $row['title'] ); ?>
                                </a>
                                <?php else : ?>
                                <?php echo esc_html( $row['title'] ); ?> <span class="aco-soft179">(پست حذف‌شده)</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span style="color:<?php echo esc_attr( $row['badge']['color'] ); ?>;font-weight:600;font-size:12px">
                                    <?php echo esc_html( $row['badge']['label'] ); ?>
                                </span>
                            </td>
                            <td>
                                <?php if ( $row['seo'] !== null ) :
                                    $s = (int) $row['seo'];
                                    $c = $s >= 70 ? '#10b981' : ( $s >= 50 ? '#f59e0b' : '#ef4444' );
                                ?>
                                <span style="font-weight:600;color:<?php echo esc_attr( $c ); ?>"><?php echo $s; ?></span>
                                <?php else : ?>
                                <span class="aco-soft72">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ( $row['status'] === 'done' ) : ?>
                                <span class="aco-success180">✓ انجام شد</span>
                                <?php elseif ( $row['status'] === 'failed' ) : ?>
                                <span class="aco-danger181">❌ ناموفق<?php echo $row['error'] ? ': ' . esc_html( mb_substr( $row['error'], 0, 50 ) ) : ''; ?></span>
                                <?php else : ?>
                                <span class="aco-soft125"><?php echo esc_html( $row['status'] ); ?></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>

                <?php if ( $total_hpages > 1 ) : ?>
                <div class="aco-center182">
                    <?php echo paginate_links( [
                        'base'      => add_query_arg( 'hpaged', '%#%' ),
                        'format'    => '',
                        'current'   => $hpaged,
                        'total'     => $total_hpages,
                        'prev_text' => '&raquo;',
                        'next_text' => '&laquo;',
                    ] ); ?>
                </div>
                <?php endif; ?>
                <?php endif; ?>
            </div>

            <h2 class="aco-text183">🐛 جزئیات اجراهای شبانه‌ی کرم (چرا هر تصمیم گرفته شد)</h2>

            <?php if ( empty( $reports ) ) : ?>
            <div class="aco-card aco-soft-center83">
                <p class="aco-text184">هنوز هیچ گزارشی ثبت نشده.</p>
                <p>کرم محتوا هر شب ساعت ۰۳:۰۰ اجرا می‌شود و گزارش اینجا ظاهر می‌شود.</p>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=aco-crawler' ) ); ?>" class="button button-primary aco-u185">⚙️ تنظیمات کرم</a>
            </div>
            <?php else : ?>

            <?php foreach ( $reports as $ridx => $report ) :
                $date    = $report['date'] ?? '';
                $summary = $report['summary'] ?? [];
                $rows    = $summary['rows'] ?? [];
                $is_first = $ridx === 0;
                $label   = $is_first ? '📅 آخرین اجرا' : "📅 اجرای قبلی #{$ridx}";
            ?>
            <div class="aco-card aco-u186">
                <div class="aco-flex187">
                    <h3 class="aco-u188">
                        <?php echo esc_html( $label ); ?> —
                        <?php echo esc_html( $date ? self::jalali_dt( (string) $date ) : '—' ); ?>
                    </h3>
                    <div class="aco-flex189">
                        <span>🔍 <?php echo (int) ( $summary['scanned'] ?? 0 ); ?> بررسی</span>
                        <span>✏️ <?php echo (int) ( $summary['rewrite'] ?? 0 ); ?> بازنویسی</span>
                        <span>🔗 <?php echo (int) ( $summary['link_inject'] ?? 0 ); ?> لینک</span>
                        <span>✅ <?php echo (int) ( $summary['none'] ?? 0 ); ?> بدون تغییر</span>
                    </div>
                </div>

                <?php if ( ! empty( $rows ) ) : ?>
                <table class="widefat striped aco-text121">
                    <thead>
                        <tr>
                            <th class="aco-u190">عنوان مقاله</th>
                            <th class="aco-u191">اقدام</th>
                            <th class="aco-u191">دلیل</th>
                            <th class="aco-u192">سئو قبل</th>
                            <th class="aco-u193">وضعیت</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ( $rows as $row ) :
                        $action_labels = [
                            'rewrite'     => '<span class="aco-u194">✏️ بازنویسی</span>',
                            'link_inject' => '<span class="aco-success195">🔗 لینک inject</span>',
                            'none'        => '<span class="aco-soft116">✅ بدون تغییر</span>',
                        ];
                        $action_html = $action_labels[ $row['action'] ?? 'none' ] ?? '—';
                        $seo_before  = (int) ( $row['seo_before'] ?? 0 );
                        $seo_color   = $seo_before >= 70 ? '#10b981' : ( $seo_before >= 50 ? '#f59e0b' : '#ef4444' );
                    ?>
                        <tr>
                            <td>
                                <?php if ( ! empty( $row['post_id'] ) ) : ?>
                                <a href="<?php echo esc_url( get_edit_post_link( $row['post_id'] ) ); ?>" target="_blank" rel="noopener noreferrer">
                                    <?php echo esc_html( $row['title'] ?? '—' ); ?>
                                </a>
                                <?php else : ?>
                                <?php echo esc_html( $row['title'] ?? '—' ); ?>
                                <?php endif; ?>
                            </td>
                            <td><?php echo $action_html; ?></td>
                            <td class="aco-text196"><?php echo esc_html( $row['reason'] ?? '—' ); ?></td>
                            <td>
                                <?php if ( $seo_before > 0 ) : ?>
                                <span style="font-weight:600;color:<?php echo esc_attr( $seo_color ); ?>"><?php echo (int) $seo_before; ?></span>
                                <?php else : ?>
                                <span class="aco-soft72">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ( ! empty( $row['error'] ) ) : ?>
                                <span class="aco-danger181">❌ <?php echo esc_html( $row['error'] ); ?></span>
                                <?php elseif ( $row['action'] === 'rewrite' ) :
                                    // FIX: به‌جای برچسب ثابت «در صف»، وضعیت واقعیِ همان job را از جدول صف می‌خوانیم
                                    $job = ACO_Queue::get_latest_job_for_post( (int) ( $row['post_id'] ?? 0 ), 'audit_update' );
                                    if ( ! $job ) : ?>
                                        <span class="aco-soft75">—</span>
                                    <?php elseif ( $job->status === 'done' ) : ?>
                                        <span class="aco-success180">✓ بازنویسی انجام شد<?php echo $job->seo_score !== null ? ' (امتیاز ' . (int) $job->seo_score . ')' : ''; ?></span>
                                    <?php elseif ( $job->status === 'failed' ) : ?>
                                        <span class="aco-danger181">❌ ناموفق<?php echo $job->error_message ? ': ' . esc_html( mb_substr( $job->error_message, 0, 60 ) ) : ''; ?></span>
                                    <?php elseif ( $job->status === 'processing' ) : ?>
                                        <span class="aco-text197">⏳ در حال پردازش</span>
                                    <?php else : ?>
                                        <span class="aco-text198">⏳ در صف پردازش</span>
                                    <?php endif; ?>
                                <?php else : ?>
                                <span class="aco-success180">✓ انجام شد</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else : ?>
                <p class="aco-soft-center199">جزئیاتی برای این اجرا ثبت نشده.</p>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>

            <div class="aco-u200">
                <button id="aco-btn-reset-crawler-reports" class="button aco-danger160">
                    🗑️ پاک کردن همه گزارش‌ها
                </button>
                <span id="aco-crawler-report-msg" class="aco-hidden201"></span>
            </div>

            <script>
            jQuery(function($){
                $('#aco-btn-reset-crawler-reports').on('click', function(){
                    if ( ! confirm('همه گزارش‌های کرم پاک شوند؟') ) return;
                    var $btn = $(this).prop('disabled', true).text('در حال پاک‌سازی...');
                    $.post(ACO.ajax_url, { action: 'aco_crawler_reset_reports', nonce: ACO.nonce }, function(res){
                        if ( res.success ) {
                            $('#aco-crawler-report-msg').text('گزارش‌ها پاک شدند.').css('color','#0f766e').show();
                            setTimeout(function(){ location.reload(); }, 1200 );
                        } else {
                            $('#aco-crawler-report-msg').text(res.data || 'خطا').css('color','#e11d48').show();
                            $btn.prop('disabled', false).text('🗑️ پاک کردن همه گزارش‌ها');
                        }
                    });
                });
            });
            </script>
            <?php endif; ?>
        </div>
        <?php
    }

    // ─────────────────────────────────────
    // AJAX: پاک‌سازی لاگ‌ها
    // ─────────────────────────────────────

    // ─────────────────────────────────────
    // صفحه بانک کلمات کلیدی
    // ─────────────────────────────────────

    public static function page_keyword_bank(): void {
        $s = ACO_Keyword_Bank::settings();
        $next_run = wp_next_scheduled( ACO_Keyword_Bank::CRON_HOOK );
        $rows     = ACO_Keyword_Bank::get_recent( 40 );
        $counts   = ACO_Keyword_Bank::get_counts();
        ?>
        <div class="wrap aco-wrap" dir="rtl">
            <h1 class="aco-page-title">
                <span class="dashicons dashicons-tag"></span>
                بانک کلمات کلیدی
            </h1>

            <div class="aco-card aco-u84">
                <p class="aco-u137">
                    هر روز به‌صورت خودکار، فهرست کلمات کلیدی طولانی (long-tail) پرترافیک و کم‌رقابت
                    برای موضوع سایت شما با AI بروزرسانی می‌شود، و بهترین کلمات (بر اساس امتیاز
                    پتانسیل ترافیک) به‌صورت مقاله کامل نوشته و منتشر می‌شوند.
                </p>

                <div class="aco-field">
                    <label class="aco-flex138">
                        <input type="checkbox" id="aco-kb-enabled" <?php checked( ! empty( $s['enabled'] ) ); ?> class="aco-u139" />
                        <span class="aco-text140">فعال‌سازی بانک کلمات کلیدی</span>
                    </label>
                    <?php if ( $next_run ) : ?>
                    <p class="description aco-u141">
                        ⏰ اجرای بعدی: <?php echo esc_html( get_date_from_gmt( gmdate( 'Y-m-d H:i:s', $next_run ), 'j F Y — H:i' ) ); ?>
                    </p>
                    <?php else : ?>
                    <p class="description aco-u142">⚠️ cron زمان‌بندی نشده. پس از ذخیره فعال می‌شود.</p>
                    <?php endif; ?>
                </div>

                <hr class="aco-u86" />
                <h3 class="aco-u143">⚙️ تنظیمات</h3>

                <div class="aco-field">
                    <label>تعداد مقاله در روز از این مسیر</label>
                    <input type="number" id="aco-kb-daily-count" value="<?php echo esc_attr( $s['daily_count'] ); ?>" min="1" max="20" step="1" class="aco-u144" />
                    <span class="aco-soft145">مقاله</span>
                    <p class="description">پیش‌فرض: ۲ مقاله در روز.</p>
                </div>

                <div class="aco-field">
                    <label>ساعت اجرای شبانه</label>
                    <input type="number" id="aco-kb-run-hour" value="<?php echo esc_attr( $s['run_hour'] ); ?>" min="0" max="23" step="1" class="aco-u144" />
                    <span class="aco-soft145">(۰ تا ۲۳، به وقت سایت)</span>
                    <p class="description">پیش‌فرض: ۴ (یک ساعت بعد از کرم محتوا، تا هم‌زمان API را اشغال نکنند).</p>
                </div>

                <div class="aco-field">
                    <label>تعداد کلمه کلیدی جدید در هر بروزرسانی</label>
                    <input type="number" id="aco-kb-refresh-count" value="<?php echo esc_attr( $s['refresh_count'] ); ?>" min="5" max="40" step="1" class="aco-u144" />
                    <span class="aco-soft145">کلمه</span>
                    <p class="description">هر روز این تعداد کلمه کلیدی جدید از AI درخواست می‌شود (بدون تکرار با موارد قبلی).</p>
                </div>

                <div class="aco-field">
                    <label>وضعیت انتشار مقالات این مسیر</label>
                    <select id="aco-kb-post-status" class="aco-u144">
                        <option value="pending" <?php selected( $s['post_status'], 'pending' ); ?>>در انتظار بازبینی (پیشنهادی)</option>
                        <option value="publish" <?php selected( $s['post_status'], 'publish' ); ?>>منتشرشده (بلافاصله)</option>
                        <option value="draft"   <?php selected( $s['post_status'], 'draft' ); ?>>پیش‌نویس</option>
                    </select>
                    <p class="description">
                        پیش‌فرض: «در انتظار بازبینی» — چون سایت شما حوزه بالینی/روانشناسی است، پیشنهاد می‌شود
                        قبل از انتشار خودکار، حداقل چند هفته اول محتوای این مسیر را دستی بررسی کنید
                        (از منوی «نوشته‌ها» ← «در انتظار بررسی»). هروقت مطمئن شدید، می‌توانید روی «منتشرشده» بگذارید.
                    </p>
                </div>

                <div class="aco-actions aco-flex149">
                    <button id="aco-btn-save-kb" class="button button-primary">💾 ذخیره تنظیمات</button>
                    <button id="aco-btn-run-kb-now" class="button">▶️ اجرای دستی الان (بروزرسانی + تولید)</button>
                </div>

                <div id="aco-kb-msg" class="aco-hidden99"></div>
            </div>

            <div class="aco-card aco-u84">
                <h2 class="aco-card-title">
                    📋 کلمات کلیدی اخیر
                    <span class="aco-text153">(<?php echo (int) $counts['pending']; ?> در انتظار · <?php echo (int) $counts['queued']; ?> در صف تولید)</span>
                </h2>

                <?php if ( empty( $rows ) ) : ?>
                <p class="description">هنوز کلمه‌ای در بانک ثبت نشده. بعد از اولین اجرا (خودکار یا دستی) اینجا نمایش داده می‌شود.</p>
                <?php else : ?>
                <table class="widefat striped">
                    <thead>
                        <tr>
                            <th>کلمه کلیدی</th>
                            <th>عنوان پیشنهادی</th>
                            <th>منبع</th>
                            <th>امتیاز</th>
                            <th>وضعیت</th>
                            <th>ترافیک واقعی (۲۸ روز)</th>
                            <th>تاریخ</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $rows as $row ) :
                            $status_label = [ 'queued' => '📤 در صف تولید', 'used' => '✅ منتشرشده', 'pending' => '⏳ در انتظار' ][ $row->status ] ?? esc_html( $row->status );
                            $source_label = ( $row->source ?? 'ai' ) === 'gsc' ? '📊 GSC (واقعی)' : '🤖 AI';
                            $traffic_label = '—';
                            if ( $row->status === 'used' && $row->traffic_updated_at ) {
                                $traffic_label = sprintf( '%d کلیک / %d نمایش', (int) $row->clicks, (int) $row->impressions );
                            }
                        ?>
                        <tr data-kb-id="<?php echo esc_attr( $row->id ); ?>">
                            <td><?php echo esc_html( $row->keyword ); ?></td>
                            <td><?php echo esc_html( $row->topic ); ?></td>
                            <td><?php echo esc_html( $source_label ); ?></td>
                            <td><?php echo esc_html( $row->score ); ?>/100</td>
                            <td><?php echo esc_html( $status_label ); ?></td>
                            <td><?php echo esc_html( $traffic_label ); ?></td>
                            <td><?php echo esc_html( $row->created_at ); ?></td>
                            <td>
                                <?php if ( $row->status === 'pending' ) : ?>
                                <a href="#" class="aco-kb-delete-row aco-danger160" data-id="<?php echo esc_attr( $row->id ); ?>">🗑️ حذف</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

}
