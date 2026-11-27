<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Psy_Page_Settings — تنظیمات پلاگین
 */
class Psy_Page_Settings {

    public static function render_settings(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'دسترسی غیرمجاز.', 'pivand-amn-quiz-enterprise' ), 403 );
        }

        // تمام آزمون‌های ثبت‌شده
        $all_tests     = \PsychAssessments\Core\Registry::all();
        $popular_ids   = array_keys( \PsychAssessments\Core\Registry::popular() );
        // آزمون‌هایی که در لیست پرکاربرد نیستند
        $remaining_ids = array_diff( array_keys( $all_tests ), $popular_ids );
        ?>
        <div class="wrap psy-admin" dir="rtl">
            <h1>⚙️ تنظیمات آزمون‌های روانشناسی</h1>

            <?php settings_errors( 'psy_quiz_settings' ); ?>

            <?php if ( isset( $_GET['settings-updated'] ) ): ?>
            <div class="notice notice-success is-dismissible"><p>✅ تنظیمات با موفقیت ذخیره شد.</p></div>
            <?php endif; ?>

            <form method="post" action="options.php">
                <?php settings_fields( 'psy_quiz_settings' ); ?>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row">
                            <label for="psy_quiz_link_expiry_days">مدت اعتبار لینک (روز)</label>
                        </th>
                        <td>
                            <input
                                type="number"
                                id="psy_quiz_link_expiry_days"
                                name="psy_quiz_link_expiry_days"
                                value="<?php echo esc_attr( get_option( 'psy_quiz_link_expiry_days', 30 ) ); ?>"
                                min="0"
                                max="365"
                                class="psy-w-100"
                            >
                            <p class="description">لینک‌های ارسال‌شده به مراجعین پس از این تعداد روز منقضی می‌شوند. (پیش‌فرض: ۳۰ روز — <strong>۰ = بدون انقضا</strong>)</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="psy_quiz_therapist_email">ایمیل درمانگر (آزمون آفلاین)</label>
                        </th>
                        <td>
                            <input
                                type="email"
                                id="psy_quiz_therapist_email"
                                name="psy_quiz_therapist_email"
                                value="<?php echo esc_attr( get_option( 'psy_quiz_therapist_email', 'peyvandamn@gmail.com' ) ); ?>"
                                class="regular-text"
                            >
                            <p class="description">پاسخ‌های آزمون آفلاین به این ایمیل ارسال می‌شوند.</p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="psy_exam_therapist_name">نام درمانگر (صفحه آزمون آنلاین)</label>
                        </th>
                        <td>
                            <input
                                type="text"
                                id="psy_exam_therapist_name"
                                name="psy_exam_therapist_name"
                                value="<?php echo esc_attr( get_option( 'psy_exam_therapist_name', 'مهناز خویینی' ) ); ?>"
                                class="regular-text"
                            >
                            <p class="description">زیر لوگو، در صفحه‌ی مستقل آزمون آنلاین (همان صفحه‌ای که مراجع با لینک آزمونش باز می‌کند) نمایش داده می‌شود.</p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="zdp_support_phone">شماره تماس پشتیبانی</label>
                        </th>
                        <td>
                            <input
                                type="text"
                                id="zdp_support_phone"
                                name="zdp_support_phone"
                                value="<?php echo esc_attr( get_option( 'zdp_support_phone', '' ) ); ?>"
                                class="regular-text"
                                placeholder="مثلاً 02112345678"
                            >
                            <p class="description">در فوتر صفحه‌ی آزمون آنلاین نمایش داده می‌شود. این یک تنظیمِ مشترک با پلاگین پنل مراجعین است — اگر آنجا هم پر شده باشد، همینجا همان مقدار را می‌بینید.</p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="psy_sms_quiz_ready_body_id">کد الگوی پیامک «آزمون آماده است»</label>
                        </th>
                        <td>
                            <input
                                type="text"
                                id="psy_sms_quiz_ready_body_id"
                                name="psy_sms_quiz_ready_body_id"
                                value="<?php echo esc_attr( get_option( 'psy_sms_quiz_ready_body_id', '' ) ); ?>"
                                class="regular-text"
                                placeholder="مثلاً 487225"
                            >
                            <p class="description">
                                با ثبت هر مراجع جدید، به‌صورت خودکار اطلاع‌رسانی ورود به پنل ارسال می‌شود:
                                اگر فقط شماره موبایل ثبت شده باشد، پیامک (طبق این پترن، متغیرها: <code>NAME</code> و <code>LINK</code>)؛
                                اگر ایمیل هم ثبت شده باشد (یعنی مراجع نمی‌تواند پیامک ایرانی دریافت کند — مثلاً مراجع خارج از کشور)، به‌جای پیامک، ایمیل ارسال می‌شود.
                                ارسال واقعی پیامک از طریق همان فیلتر مشترک <code>pas_send_sms</code> انجام می‌شود که پلاگین «پیوند امن — پل پیامک» پیاده می‌کند؛ تا این پترن در ملی‌پیامک ساخته و اینجا ثبت نشود، این پیام سایلنت رد می‌شود (خطا نمی‌دهد).
                            </p>
                        </td>
                    </tr>

                    <!-- ── آزمون‌های پرکاربرد ─────────────────────────────── -->
                    <tr>
                        <th scope="row" class="psy-th-top">
                            آزمون‌های پرکاربرد
                        </th>
                        <td>
                            <p class="description psy-mb-12">
                                آزمون‌های انتخاب‌شده در بالای لیست ارسال به مراجع نمایش داده می‌شوند.<br>
                                برای تغییر ترتیب <strong>درگ کنید</strong>. برای حذف روی ✕ کلیک کنید.
                            </p>

                            <div class="psy-popular-builder">

                                <!-- لیست انتخاب‌شده‌ها -->
                                <div class="psy-popular-col">
                                    <div class="psy-popular-col-title">✅ لیست پرکاربرد (به ترتیب نمایش)</div>
                                    <ul id="psy-popular-selected" class="psy-popular-list">
                                        <?php foreach ( $popular_ids as $pid ):
                                            $test = $all_tests[$pid] ?? null;
                                            if ( ! $test ) continue;
                                        ?>
                                        <li data-id="<?php echo esc_attr($pid); ?>" class="psy-popular-item">
                                            <span class="psy-popular-item-handle">⠿</span>
                                            <span class="psy-popular-item-name"><?php echo esc_html($test->get_name()); ?></span>
                                            <button type="button" class="psy-pop-remove psy-popular-item-remove"
                                                    data-id="<?php echo esc_attr($pid); ?>"
                                                    title="حذف از لیست پرکاربرد">✕</button>
                                            <input type="hidden" name="psy_quiz_popular_tests[]" value="<?php echo esc_attr($pid); ?>">
                                        </li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>

                                <!-- آزمون‌های موجود برای افزودن -->
                                <div class="psy-popular-col">
                                    <div class="psy-popular-col-title">➕ افزودن آزمون</div>
                                    <input type="text" id="psy-pop-search" placeholder="جستجو…" class="psy-pool-search">
                                    <ul id="psy-popular-pool" class="psy-pool-list">
                                        <?php
                                        // ترتیب نمایش: ابتدا آزمون‌های خارج از لیست، بعد همه (برای search)
                                        $pool_tests = [];
                                        foreach ( $all_tests as $pid => $test ) {
                                            $pool_tests[$pid] = $test;
                                        }
                                        uasort( $pool_tests, fn($a,$b) => strcmp($a->get_name(), $b->get_name()) );
                                        foreach ( $pool_tests as $pid => $test ):
                                            $in_list = in_array( $pid, $popular_ids, true );
                                        ?>
                                        <li data-id="<?php echo esc_attr($pid); ?>"
                                            data-name="<?php echo esc_attr($test->get_name()); ?>"
                                            class="psy-pool-item<?php echo $in_list ? ' psy-pool-added' : ''; ?>">
                                            <span class="psy-pool-item-name"><?php echo esc_html($test->get_name()); ?></span>
                                            <?php if ( $in_list ): ?>
                                                <span class="psy-pool-added-label">✓ در لیست</span>
                                            <?php else: ?>
                                                <button type="button" class="psy-pop-add psy-pool-add-btn"
                                                        data-id="<?php echo esc_attr($pid); ?>"
                                                        data-name="<?php echo esc_attr($test->get_name()); ?>">+ افزودن</button>
                                            <?php endif; ?>
                                        </li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>

                            </div><!-- /psy-popular-builder -->
                        </td>
                    </tr>

                    <!-- ── حفظ داده‌ها هنگام حذف افزونه ──────────────────── -->
                    <tr>
                        <th scope="row">
                            <label for="psy_quiz_delete_data_on_uninstall">حذف کامل داده‌ها هنگام حذف افزونه</label>
                        </th>
                        <td>
                            <label>
                                <input type="hidden" name="psy_quiz_delete_data_on_uninstall" value="0">
                                <input
                                    type="checkbox"
                                    id="psy_quiz_delete_data_on_uninstall"
                                    name="psy_quiz_delete_data_on_uninstall"
                                    value="1"
                                    <?php checked( get_option( 'psy_quiz_delete_data_on_uninstall', '0' ), '1' ); ?>
                                >
                                بله، وقتی افزونه از صفحه‌ی «افزونه‌ها» Delete می‌شود، تمام مراجعین و نتایج آزمون‌ها هم برای همیشه پاک شوند.
                            </label>
                            <p class="description">
                                <strong>پیش‌فرض غیرفعال است.</strong> یعنی اگر برای نصب یک آپدیت مجبور شدید افزونه را
                                Delete و دوباره نصب کنید، هیچ مراجع یا نتیجه‌ی آزمونی پاک نمی‌شود — جدول‌های دیتابیس
                                دست‌نخورده می‌مانند و با نصب مجدد افزونه دوباره در دسترس‌اند. این گزینه را فقط زمانی
                                فعال کنید که واقعاً می‌خواهید افزونه را برای همیشه حذف کنید و دیگر قصد استفاده‌ی
                                دوباره از آن یا داده‌هایش را ندارید.
                            </p>
                        </td>
                    </tr>

                </table>
                <?php submit_button( 'ذخیره تنظیمات' ); ?>
            </form>
        </div>

        <?php
        // فاز ۱۲ / گام ۶: SortableJS سلف‌هاست (assets/js/vendor) — بدون CDN.
        $psy_sortable = PSY_DIR . 'assets/js/vendor/Sortable.min.js';
        if (file_exists($psy_sortable)) :
            ?>
            <script src="<?php echo esc_url(PSY_URL . 'assets/js/vendor/Sortable.min.js?ver=1.15.6'); ?>"></script>
            <?php
        endif;
        ?>
        <script>
        (function(){
            var selected = document.getElementById('psy-popular-selected');
            var pool     = document.getElementById('psy-popular-pool');
            var search   = document.getElementById('psy-pop-search');

            // ── Drag-and-drop روی لیست انتخاب‌شده‌ها
            Sortable.create(selected, {
                animation: 150,
                handle: '.psy-popular-item-handle, li',
                ghostClass: 'sortable-ghost',
                onEnd: function() { rebuildHiddenInputs(); }
            });

            // ── جستجو در pool
            search.addEventListener('input', function(){
                var q = this.value.trim().toLowerCase();
                pool.querySelectorAll('li.psy-pool-item').forEach(function(li){
                    var name = li.getAttribute('data-name') || '';
                    li.style.display = (!q || name.includes(q)) ? '' : 'none';
                });
            });

            // ── افزودن آزمون به لیست
            pool.addEventListener('click', function(e){
                var btn = e.target.closest('.psy-pop-add');
                if (!btn) return;
                var id   = btn.getAttribute('data-id');
                var name = btn.getAttribute('data-name');

                // بررسی تکراری نبودن
                if (selected.querySelector('li[data-id="'+id+'"]')) return;

                var li = document.createElement('li');
                li.setAttribute('data-id', id);
                li.className = 'psy-popular-item';
                li.innerHTML =
                    '<span class="psy-popular-item-handle">⠿</span>' +
                    '<span class="psy-popular-item-name">'+escHtml(name)+'</span>' +
                    '<button type="button" class="psy-pop-remove psy-popular-item-remove" data-id="'+id+'" title="حذف از لیست پرکاربرد">✕</button>' +
                    '<input type="hidden" name="psy_quiz_popular_tests[]" value="'+id+'">';
                selected.appendChild(li);

                // pool آیتم رو به "در لیست" تبدیل کن
                var poolLi = pool.querySelector('li[data-id="'+id+'"]');
                if (poolLi) {
                    poolLi.classList.add('psy-pool-added');
                    var addBtn = poolLi.querySelector('.psy-pop-add');
                    if (addBtn) addBtn.outerHTML = '<span class="psy-pool-added-label">✓ در لیست</span>';
                }
            });

            // ── حذف از لیست
            selected.addEventListener('click', function(e){
                var btn = e.target.closest('.psy-pop-remove');
                if (!btn) return;
                var id = btn.getAttribute('data-id');
                var li = selected.querySelector('li[data-id="'+id+'"]');
                if (li) li.remove();

                // pool آیتم رو به حالت قابل افزودن برگردون
                var poolLi = pool.querySelector('li[data-id="'+id+'"]');
                if (poolLi) {
                    poolLi.classList.remove('psy-pool-added');
                    var inListSpan = poolLi.querySelector('.psy-pool-added-label');
                    if (inListSpan) {
                        var name = poolLi.getAttribute('data-name');
                        inListSpan.outerHTML =
                            '<button type="button" class="psy-pop-add psy-pool-add-btn" data-id="'+id+'" data-name="'+escHtml(name)+'">+ افزودن</button>';
                    }
                }
            });

            function rebuildHiddenInputs() {
                // SortableJS جابجایی DOM رو خودش انجام می‌ده؛
                // فقط باید مطمئن بشیم hidden input ها هم جابجا شدن.
                // چون input ها داخل همون li هستن، با li جابجا می‌شن — نیاز به کار اضافه نیست.
            }

            function escHtml(s) {
                return s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
            }
        })();
        </script>
        <?php
    }
}
