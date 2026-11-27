/* Payvand Contact – form.js */
(function ($) {
    'use strict';

    // ارقام فارسی/عربی → انگلیسی
    function toEnDigits(str) {
        return String(str || '')
            .replace(/[۰-۹]/g, function (d) { return d.charCodeAt(0) - 1776; })
            .replace(/[٠-٩]/g, function (d) { return d.charCodeAt(0) - 1632; });
    }

    $(document).ready(function () {

        $('#payvand-form').on('submit', function (e) {
            e.preventDefault();

            var $form   = $(this);
            var $btn    = $('#pv-submit');
            var $alert  = $('#payvand-alert');

            // Clear previous alert
            $alert.hide().removeClass('success error');

            // Client-side validation
            var name    = String($('#pv-name').val() || '').trim();
            var phone   = toEnDigits(String($('#pv-phone').val() || '').trim());
            var visit   = $('#pv-visit').val();
            var consult = $('input[name="consult_type"]:checked').val();
            var privacy = $('#pv-privacy').is(':checked');

            if (!name) {
                return showAlert('لطفاً نام و نام خانوادگی را وارد کنید.', 'error');
            }
            var phoneClean = phone.replace(/[\s\-]/g, '');
            if (!/^09[0-9]{9}$/.test(phoneClean)) {
                return showAlert('شماره موبایل وارد شده معتبر نیست (مثال: 09121234567)', 'error');
            }
            if (!visit) {
                return showAlert('لطفاً نوع مراجعه را انتخاب کنید.', 'error');
            }
            if (!consult) {
                return showAlert('لطفاً روش مشاوره را انتخاب کنید.', 'error');
            }
            var email   = String($('#pv-email').val() || '').trim();
            if (email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
                return showAlert('فرمت ایمیل وارد شده صحیح نیست.', 'error');
            }
            if (!privacy) {
                return showAlert('موافقت با قوانین حریم خصوصی الزامی است.', 'error');
            }

            // Disable button
            $btn.prop('disabled', true);
            $btn.find('.btn-text').hide();
            $btn.find('.btn-loader').show();

            var payload = {
                action:       'payvand_submit',
                nonce:        payvandAjax.nonce,
                full_name:    name,
                phone:        phoneClean,
                email:        email,
                visit_type:   visit,
                consult_type: consult,
                message:      String($('#pv-message').val() || '').trim(),
                privacy:      privacy ? '1' : '',
                website:      $('#pv-website').val() || ''
            };

            // AJAX — اگر صفحه کش شده و nonce منقضی شده باشد (پاسخ 403)،
            // یک nonce تازه می‌گیریم و یک بار دوباره تلاش می‌کنیم.
            var retried = false, retrying = false;
            function send() {
            retrying = false;
            $.ajax({
                url:    payvandAjax.ajax_url,
                method: 'POST',
                data:   payload,
                success: function (res) {
                    if (res.success) {
                        showAlert('✅ ' + res.data.message, 'success');
                        $form[0].reset();
                        // Smooth scroll to alert
                        $('html,body').animate({ scrollTop: $alert.offset().top - 80 }, 400);
                    } else {
                        showAlert('⚠️ ' + (res.data.message || 'خطایی رخ داد.'), 'error');
                    }
                },
                error: function (xhr) {
                    if (xhr && xhr.status === 403 && !retried) {
                        retried = true;
                        retrying = true;
                        $.post(payvandAjax.ajax_url, { action: 'payvand_nonce' }, function (r) {
                            if (r && r.success && r.data && r.data.nonce) {
                                payvandAjax.nonce = payload.nonce = r.data.nonce;
                                send();
                            } else {
                                showAlert('⚠️ نشست صفحه منقضی شده؛ لطفاً صفحه را رفرش کنید.', 'error');
                                resetBtn();
                            }
                        }).fail(function () {
                            showAlert('⚠️ خطا در اتصال به سرور. لطفاً دوباره تلاش کنید.', 'error');
                            resetBtn();
                        });
                        return;
                    }
                    showAlert('⚠️ خطا در اتصال به سرور. لطفاً دوباره تلاش کنید.', 'error');
                },
                complete: function (xhr) {
                    if (retrying) { return; }
                    resetBtn();
                }
            });
            }
            function resetBtn() {
                $btn.prop('disabled', false);
                $btn.find('.btn-text').show();
                $btn.find('.btn-loader').hide();
            }
            send();
        });

        function showAlert(msg, type) {
            var $alert = $('#payvand-alert');
            $alert.removeClass('success error').addClass(type).text(msg).fadeIn(300);
        }

    });

})(jQuery);
