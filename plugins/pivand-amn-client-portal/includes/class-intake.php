<?php

declare(strict_types=1);

namespace ZoojDarman\ClientPortal;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * فرم ارزیابی اولیه‌ی مراجع («کلینیک روانشناسی — اطلاعات فردی»).
 *
 * هر مراجع قبل از جلسه این فرم را از تب «فرم ارزیابی» پنل پر می‌کند.
 *  - ذخیره: یک ردیف برای هر کاربر (جدول zdp_intake). محتوا به‌صورت JSON و در
 *    صورت فعال‌بودن کلید رمزنگاری هسته (Pas_Core_Crypto) رمزنگاری‌شده ذخیره
 *    می‌شود؛ اگر هسته نباشد، متن ساده (با ستون payload_encrypted=0).
 *  - اطلاع‌رسانی: بعد از تأیید نوبت و سپس به‌صورت یادآوری (حداکثر N بار) تا
 *    وقتی فرم پر نشده، پیامک الگوی zdp_sms_intake_body_id فرستاده می‌شود؛ در
 *    پنل هم بنر «لطفاً فرم مراجع را پر کنید» نشان داده می‌شود.
 *  - ادمین: تب «فرم‌های ارزیابی» در پیشخوان (مشاهده، شماره پرونده، تنظیمات پیامک).
 *
 * «شماره پرونده» فقط توسط ادمین پر می‌شود و روی فرم مراجع دیده نمی‌شود.
 * «امضای مراجع» با تیک تأیید صحت اطلاعات (و زمان ثبت) جایگزین شده است.
 */
final class Intake
{
    public const TABLE_KEY = 'intake';

    public const MARITAL = ['مجرد', 'نامزد', 'عقد', 'متاهل', 'ازدواج موقت', 'ازدواج مجدد', 'متارکه', 'مطلقه', 'بیوه'];
    public const EDU     = ['بی‌سواد', 'دبستان', 'متوسطه اول', 'متوسطه دوم', 'دیپلم', 'کاردانی', 'لیسانس', 'فوق لیسانس', 'دکترا'];
    public const GENDER  = ['زن', 'مرد'];

    /**
     * بخش «سوابق سلامت و درمان». هر مورد یک انتخاب (اختیاری) + یک توضیح آزاد دارد.
     * کلیدها داخل همان payload JSON ذخیره می‌شوند؛ تغییر اسکیمای دیتابیس لازم نیست و
     * فرم‌های قدیمی (بدون این کلیدها) بدون مشکل خوانده و به‌روزرسانی می‌شوند.
     */
    public const HEALTH_FIELDS = [
        'med_history' => [
            'label'   => 'سابقه مصرف دارو یا بیماری خاص',
            'options' => ['خیر', 'بله'],
            'none'    => 'خیر',
            'details' => 'med_details',
            'hint'    => 'نام دارو یا بیماری، مدت و دوز (در صورت اطلاع)',
        ],
        'substance_history' => [
            'label'   => 'سابقه مصرف مواد و الکل',
            'options' => ['خیر', 'در گذشته داشته‌ام', 'در حال حاضر دارم'],
            'none'    => 'خیر',
            'details' => 'substance_details',
            'hint'    => 'نوع ماده، مدت مصرف و زمان آخرین مصرف',
        ],
        'counseling_history' => [
            'label'   => 'سابقه مشاوره و روانپزشک',
            'options' => ['خیر', 'مشاوره / روان‌درمانی', 'روانپزشک', 'هر دو'],
            'none'    => 'خیر',
            'details' => 'counseling_details',
            'hint'    => 'مدت، علت مراجعه و داروهای تجویزشده (در صورت وجود)',
        ],
    ];

    private const META_SMS_LAST  = 'zdp_intake_sms_last';
    private const META_SMS_COUNT = 'zdp_intake_sms_count';

    private Security $security;

    public function __construct(Security $security)
    {
        $this->security = $security;

        add_action('wp_ajax_zdp_submit_intake', [$this, 'handle_submit']);
        add_action('admin_post_zdp_intake_save_meta', [$this, 'handle_admin_save_meta']);
        add_action('admin_post_zdp_intake_save_settings', [$this, 'handle_admin_save_settings']);

        // بعد از تأیید نوبت (Booking::notify_client_booking_confirmed) و
        // در همان چرخه‌ی ساعتی یادآوری نوبت (Cron) اجرا می‌شود؛ زمان‌بندی جدا لازم نیست.
        add_action('zdp_booking_confirmed_for_client', [$this, 'on_booking_confirmed'], 10, 1);
        add_action('zdp_appointment_reminder_event', [$this, 'process_reminders']);

        // فیلتر فقط‌خواندنیِ قرارداد اکوسیستم برای نمایش فرم ارزیابی داخل پرونده‌ی
        // مراجع (pivand-amn-case-file)؛ بدون وابستگی مستقیم کلاس به کلاس.
        add_filter('zdp_get_intake_for_phone', [$this, 'filter_intake_for_phone'], 10, 2);
    }

    /**
     * apply_filters('zdp_get_intake_for_phone', array $default, string $phone)
     *
     * خروجی: [] یا ['file_number' => string, 'submitted_at' => string,
     * 'updated_at' => string (UTC), 'data' => array فیلدهای فرم]. فقط برای
     * کاربر واردشده‌ی دارای دسترسی (مدیر یا pas_manage_case_files) و فقط در
     * پیشخوان برمی‌گردد؛ در غیر این‌صورت (یا نبودن مراجع/فرم، یا نبود کلید
     * رمزنگاری هسته برای فرم رمزشده) مقدار پیش‌فرض بدون تغییر برمی‌گردد.
     *
     * @param mixed $default
     * @param mixed $phone
     * @return mixed
     */
    public function filter_intake_for_phone($default, $phone)
    {
        if (!is_admin() || !is_user_logged_in()) {
            return $default;
        }
        if (!current_user_can('manage_options') && !current_user_can('pas_manage_case_files')) {
            return $default;
        }

        $mobile = to_local_format((string) $phone);
        if ($mobile === '' || !is_valid_mobile($mobile)) {
            return $default;
        }

        // همان قاعده‌ی ساخت user_login در OTP_Auth ('zdp_' + شماره بدون '+').
        $user = get_user_by('login', 'zdp_' . str_replace('+', '', $mobile));
        if (!$user) {
            return $default;
        }

        $row = $this->get_row((int) $user->ID);
        if (!$row) {
            return $default;
        }

        $data = $this->decode_payload($row);
        if (empty($data)) {
            return $default;
        }

        return [
            'file_number'  => (string) $row->file_number,
            'submitted_at' => (string) $row->submitted_at,
            'updated_at'   => (string) $row->updated_at,
            'data'         => $data,
        ];
    }

    // ------------------------------------------------------------------
    // ذخیره/خواندن
    // ------------------------------------------------------------------

    public function get_row(int $user_id): ?object
    {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare(
            'SELECT * FROM ' . Installer::table(self::TABLE_KEY) . ' WHERE user_id = %d',
            $user_id
        ));

        return $row ?: null;
    }

    public function has_submitted(int $user_id): bool
    {
        return $this->get_row($user_id) !== null;
    }

    /** @return array<string,mixed> */
    public function decode_payload(object $row): array
    {
        $raw = (string) $row->payload;
        if ((int) $row->payload_encrypted === 1) {
            if (!class_exists('Pas_Core_Crypto')) {
                return [];
            }
            $raw = \Pas_Core_Crypto::unwrap_decrypt($raw);
        }
        $data = json_decode($raw, true);

        return is_array($data) ? $data : [];
    }

    private function encode_payload(array $data): array
    {
        $json = (string) wp_json_encode($data, JSON_UNESCAPED_UNICODE);
        if (class_exists('Pas_Core_Crypto') && \Pas_Core_Crypto::is_configured()) {
            return [\Pas_Core_Crypto::wrap_encrypted($json), 1];
        }

        return [$json, 0];
    }

    // ------------------------------------------------------------------
    // ارسال فرم (AJAX، فقط کاربر واردشده)
    // ------------------------------------------------------------------

    public function handle_submit(): void
    {
        $this->security->verify_ajax_nonce();

        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => 'ابتدا وارد پنل شوید.'], 401);
        }

        $user_id = get_current_user_id();
        if (!$this->security->allow('intake_' . $user_id, 20, 3600)) {
            wp_send_json_error(['message' => 'تعداد تلاش‌های شما زیاد بوده. کمی بعد دوباره امتحان کنید.'], 429);
        }

        $clean = $this->validate($_POST); // phpcs:ignore WordPress.Security.NonceVerification -- nonce در verify_ajax_nonce بررسی شد
        if (is_string($clean)) {
            wp_send_json_error(['message' => $clean]);
        }

        global $wpdb;
        $table    = Installer::table(self::TABLE_KEY);
        $existing = $this->get_row($user_id);
        $now      = now_utc();

        // شماره پرونده فقط ادمین می‌گذارد؛ هنگام ویرایش توسط مراجع حفظ می‌شود.
        $clean['file_number'] = $existing ? (string) $existing->file_number : '';
        [$payload, $enc] = $this->encode_payload($clean);

        if ($existing) {
            $ok = $wpdb->update(
                $table,
                ['payload' => $payload, 'payload_encrypted' => $enc, 'updated_at' => $now],
                ['user_id' => $user_id],
                ['%s', '%d', '%s'],
                ['%d']
            );
        } else {
            $ok = $wpdb->insert($table, [
                'user_id'           => $user_id,
                'file_number'       => '',
                'payload'           => $payload,
                'payload_encrypted' => $enc,
                'submitted_at'      => $now,
                'updated_at'        => $now,
            ], ['%d', '%s', '%s', '%d', '%s', '%s']);
        }

        if ($ok === false) {
            error_log('ZDP intake save failed: ' . $wpdb->last_error);
            wp_send_json_error(['message' => 'خطایی در ذخیره‌ی فرم رخ داد. لطفاً دوباره تلاش کنید.']);
        }

        // نام و موبایل پروفایل مراجع هم هم‌گام شود تا پنل/پیامک از همان استفاده کنند.
        if ($clean['full_name'] !== '') {
            update_user_meta($user_id, 'zdp_full_name', $clean['full_name']);
        }

        audit_log($existing ? 'intake_updated' : 'intake_submitted', ['user_id' => $user_id]);
        do_action('zdp_intake_submitted', $user_id, $clean);

        // تاریخ ثبت به وقت محلی سایت (submitted_at در UTC ذخیره می‌شود) تا هم
        // جمله‌ی تأیید بدون رفرش درست باشد و هم نزدیک نیمه‌شب روز اشتباه نیاید.
        $submitted_local = get_date_from_gmt((string) ($existing ? $existing->submitted_at : $now), 'Y-m-d');

        wp_send_json_success([
            'message'   => 'فرم ارزیابی مراجع شما ثبت شد. سپاسگزاریم.',
            'done_text' => 'فرم شما در تاریخ ' . Jalali::gregorian_to_jalali_string($submitted_local) . ' ثبت شده است. در صورت نیاز می‌توانید اطلاعات را اصلاح کنید.',
        ]);
    }

    /**
     * @param array<string,mixed> $src
     * @return array<string,mixed>|string آرایه‌ی تمیزشده، یا پیام خطا
     */
    private function validate(array $src): array|string
    {
        $get = static function (string $key, int $max = 200) use ($src): string {
            $v = isset($src[$key]) && is_scalar($src[$key]) ? (string) wp_unslash($src[$key]) : '';
            $v = sanitize_text_field($v);

            return mb_substr(trim($v), 0, $max);
        };

        $full_name = $get('full_name', 150);
        if (mb_strlen($full_name) < 3) {
            return 'نام و نام خانوادگی را وارد کنید.';
        }

        $birth = normalize_digits($get('birth_date', 12));
        $birth = str_replace('-', '/', $birth);
        $age   = null;
        if ($birth !== '') {
            $greg = Jalali::jalali_string_to_gregorian_string($birth);
            if ($greg === null) {
                return 'تاریخ تولد معتبر نیست (نمونه: 1370/05/21).';
            }
            // diff() قدر مطلق می‌دهد، پس تاریخ آینده سن ۰ می‌شد و از فیلتر رد
            // می‌شد؛ مقایسه‌ی مستقیم با امروز جلوی این حالت را می‌گیرد.
            if ($greg > wp_date('Y-m-d')) {
                return 'تاریخ تولد نمی‌تواند در آینده باشد.';
            }
            $age = $this->age_from_gregorian($greg);
            if ($age < 0 || $age > 120) {
                return 'تاریخ تولد معتبر نیست.';
            }
        } else {
            return 'تاریخ تولد را وارد کنید.';
        }

        $gender = $get('gender', 20);
        if (!in_array($gender, self::GENDER, true)) {
            return 'جنسیت را انتخاب کنید.';
        }

        $marital = $get('marital', 30);
        if (!in_array($marital, self::MARITAL, true)) {
            return 'وضعیت تاهل را انتخاب کنید.';
        }

        $children_raw = normalize_digits($get('children', 3));
        if ($children_raw === '' || !ctype_digit($children_raw) || (int) $children_raw > 20) {
            return 'تعداد فرزندان را به‌صورت عدد (۰ تا ۲۰) وارد کنید.';
        }

        $edu = $get('education', 30);
        if (!in_array($edu, self::EDU, true)) {
            return 'میزان تحصیلات را انتخاب کنید.';
        }

        $phone_in = $get('phone', 20);
        $phone    = $phone_in !== '' ? to_local_format($phone_in) : '';
        if ($phone === '' || !is_valid_mobile($phone)) {
            return 'شماره تماس معتبر نیست (نمونه: 09121234567).';
        }

        $address = sanitize_textarea_field(isset($src['address']) && is_scalar($src['address']) ? (string) wp_unslash($src['address']) : '');
        $address = mb_substr(trim($address), 0, 500);
        if ($address === '') {
            return 'آدرس محل سکونت را وارد کنید.';
        }

        if (empty($src['confirm'])) {
            return 'برای ثبت فرم، صحت اطلاعات را تأیید کنید.';
        }

        // سوابق سلامت و درمان: انتخاب‌ها اختیاری‌اند، ولی اگر مقداری ارسال شود
        // باید یکی از گزینه‌های تعریف‌شده باشد؛ با انتخاب «خیر» توضیح پاک می‌شود.
        $health = [];
        foreach (self::HEALTH_FIELDS as $hkey => $cfg) {
            $choice = $get($hkey, 40);
            if ($choice !== '' && !in_array($choice, $cfg['options'], true)) {
                return 'گزینه‌ی انتخاب‌شده برای «' . $cfg['label'] . '» معتبر نیست.';
            }
            $dkey    = $cfg['details'];
            $details = sanitize_textarea_field(isset($src[$dkey]) && is_scalar($src[$dkey]) ? (string) wp_unslash($src[$dkey]) : '');
            $details = mb_substr(trim($details), 0, 600);
            if ($choice === $cfg['none']) {
                $details = '';
            }
            $health[$hkey] = $choice;
            $health[$dkey] = $details;
        }

        $jy = Jalali::to_jalali((int) wp_date('Y'), (int) wp_date('n'), (int) wp_date('j'));

        return array_merge([
            'form_date'    => sprintf('%04d/%02d/%02d', $jy[0], $jy[1], $jy[2]),
            'full_name'    => $full_name,
            'father_name'  => $get('father_name', 100),
            'birth_date'   => $birth,
            'age'          => $age,
            'gender'       => $gender,
            'marital'      => $marital,
            'children'     => (int) $children_raw,
            'education'    => $edu,
            'job'          => $get('job', 150),
            'phone'        => $phone,
            'address'      => $address,
            'referral'     => $get('referral', 200),
            'confirmed_at' => now_utc(),
        ], $health);
    }

    private function age_from_gregorian(string $ymd): int
    {
        try {
            $birth = new \DateTimeImmutable($ymd, new \DateTimeZone('UTC'));
            $now   = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        } catch (\Exception $e) {
            return -1;
        }

        return (int) $birth->diff($now)->y;
    }

    // ------------------------------------------------------------------
    // نمایش در پنل مراجع
    // ------------------------------------------------------------------

    public function render_banner(int $user_id): string
    {
        if ($this->has_submitted($user_id)) {
            return '';
        }

        return '<div class="zdp-intake-banner" role="status">'
            . '<span class="zdp-intake-banner-text"><span class="zdp-icon-inline">' . zdp_icon('clipboard', 16) . '</span> لطفاً پیش از جلسه، فرم ارزیابی مراجع را تکمیل کنید.</span>'
            . '<button type="button" class="zdp-btn-primary zdp-intake-banner-btn" data-goto-tab="intake">تکمیل فرم</button>'
            . '</div>';
    }

    public function render_tab(int $user_id): string
    {
        $row  = $this->get_row($user_id);
        $data = $row ? $this->decode_payload($row) : [];

        $v = static function (string $key, string $fallback = '') use ($data): string {
            return isset($data[$key]) ? (string) $data[$key] : $fallback;
        };

        $name   = $v('full_name', (string) get_user_meta($user_id, 'zdp_full_name', true));
        $phone  = $v('phone', zdp_get_user_mobile($user_id));
        $submit = $row ? 'به‌روزرسانی فرم' : 'ثبت فرم';

        ob_start();
        ?>
        <div class="zdp-card zdp-card-form">
            <h3>فرم ارزیابی مراجع</h3>
            <?php if ($row) : ?>
                <p class="zdp-intake-done"><span class="zdp-icon-inline"><?php echo zdp_icon('check', 16); ?></span> فرم شما در تاریخ <?php echo esc_html(Jalali::gregorian_to_jalali_string(get_date_from_gmt((string) $row->submitted_at, 'Y-m-d'))); ?> ثبت شده است. در صورت نیاز می‌توانید اطلاعات را اصلاح کنید.</p>
            <?php else : ?>
                <p class="zdp-intake-lead">لطفاً پیش از اولین جلسه این فرم را کامل کنید. اطلاعات شما محرمانه است و فقط برای درمانگر نمایش داده می‌شود.</p>
            <?php endif; ?>

            <form id="zdp-intake-form" class="zdp-form zdp-intake-form" novalidate>
                <h4 class="zdp-intake-section-title"><span class="zdp-intake-step">۱</span> اطلاعات فردی</h4>
                <div class="zdp-intake-grid">
                    <p>
                        <label for="zdp-in-name">نام و نام خانوادگی <span aria-hidden="true">*</span></label>
                        <input type="text" id="zdp-in-name" name="full_name" autocomplete="name" maxlength="150" required value="<?php echo esc_attr($name); ?>">
                    </p>
                    <p>
                        <label for="zdp-in-father">نام پدر</label>
                        <input type="text" id="zdp-in-father" name="father_name" maxlength="100" value="<?php echo esc_attr($v('father_name')); ?>">
                    </p>
                    <p>
                        <label for="zdp-in-birth">تاریخ تولد (شمسی) <span aria-hidden="true">*</span></label>
                        <input type="text" id="zdp-in-birth" name="birth_date" dir="ltr" inputmode="numeric" maxlength="10" placeholder="1370/05/21" autocomplete="bday" required value="<?php echo esc_attr($v('birth_date')); ?>">
                    </p>
                    <p>
                        <label for="zdp-in-gender">جنسیت <span aria-hidden="true">*</span></label>
                        <select id="zdp-in-gender" name="gender" required>
                            <option value="">انتخاب کنید</option>
                            <?php foreach (self::GENDER as $g) : ?>
                                <option value="<?php echo esc_attr($g); ?>" <?php selected($v('gender'), $g); ?>><?php echo esc_html($g); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </p>
                    <p>
                        <label for="zdp-in-children">تعداد فرزندان <span aria-hidden="true">*</span></label>
                        <input type="text" id="zdp-in-children" name="children" dir="ltr" inputmode="numeric" maxlength="2" required value="<?php echo esc_attr($v('children')); ?>">
                    </p>
                    <p>
                        <label for="zdp-in-phone">شماره تماس <span aria-hidden="true">*</span></label>
                        <input type="tel" id="zdp-in-phone" name="phone" dir="ltr" inputmode="tel" autocomplete="tel" maxlength="20" required value="<?php echo esc_attr($phone); ?>">
                    </p>
                    <p>
                        <label for="zdp-in-job">شغل</label>
                        <input type="text" id="zdp-in-job" name="job" maxlength="150" value="<?php echo esc_attr($v('job')); ?>">
                    </p>
                    <p>
                        <label for="zdp-in-referral">منبع ارجاع</label>
                        <input type="text" id="zdp-in-referral" name="referral" maxlength="200" placeholder="مثلاً: اینستاگرام، معرفی دوستان، جست‌وجوی گوگل" value="<?php echo esc_attr($v('referral')); ?>">
                    </p>
                </div>

                <fieldset class="zdp-intake-fieldset">
                    <legend>وضعیت تاهل <span aria-hidden="true">*</span></legend>
                    <div class="zdp-choice-group">
                        <?php foreach (self::MARITAL as $opt) : ?>
                            <label class="zdp-choice-pill">
                                <input type="radio" name="marital" value="<?php echo esc_attr($opt); ?>" <?php checked($v('marital'), $opt); ?> required>
                                <span><?php echo esc_html($opt); ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </fieldset>

                <fieldset class="zdp-intake-fieldset">
                    <legend>میزان تحصیلات <span aria-hidden="true">*</span></legend>
                    <div class="zdp-choice-group">
                        <?php foreach (self::EDU as $opt) : ?>
                            <label class="zdp-choice-pill">
                                <input type="radio" name="education" value="<?php echo esc_attr($opt); ?>" <?php checked($v('education'), $opt); ?> required>
                                <span><?php echo esc_html($opt); ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </fieldset>

                <p>
                    <label for="zdp-in-address">آدرس محل سکونت <span aria-hidden="true">*</span></label>
                    <textarea id="zdp-in-address" name="address" rows="3" maxlength="500" autocomplete="street-address" required><?php echo esc_textarea($v('address')); ?></textarea>
                </p>

                <h4 class="zdp-intake-section-title"><span class="zdp-intake-step">۲</span> سوابق سلامت و درمان</h4>
                <p class="zdp-intake-privacy"><span class="zdp-icon-inline"><?php echo zdp_icon('lock', 16); ?></span> این بخش کاملاً محرمانه است و فقط درمانگر شما آن را می‌بیند. پاسخ به این بخش اختیاری است، اما به شناخت بهتر شرایط شما کمک می‌کند.</p>
                <?php foreach (self::HEALTH_FIELDS as $hkey => $cfg) :
                    $hid  = 'zdp-in-' . str_replace('_', '-', $cfg['details']);
                    $hval = $v($hkey);
                    ?>
                    <fieldset class="zdp-intake-fieldset zdp-intake-health">
                        <legend><?php echo esc_html($cfg['label']); ?></legend>
                        <div class="zdp-choice-group">
                            <?php foreach ($cfg['options'] as $opt) : ?>
                                <label class="zdp-choice-pill">
                                    <input type="radio" name="<?php echo esc_attr($hkey); ?>" value="<?php echo esc_attr($opt); ?>" <?php checked($hval, $opt); ?>>
                                    <span><?php echo esc_html($opt); ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                        <label class="zdp-intake-sublabel" for="<?php echo esc_attr($hid); ?>">توضیحات (اختیاری)</label>
                        <textarea id="<?php echo esc_attr($hid); ?>" name="<?php echo esc_attr($cfg['details']); ?>" rows="2" maxlength="600" placeholder="<?php echo esc_attr($cfg['hint']); ?>"><?php echo esc_textarea($v($cfg['details'])); ?></textarea>
                    </fieldset>
                <?php endforeach; ?>

                <p class="zdp-intake-confirm">
                    <label>
                        <input type="checkbox" name="confirm" value="1" required <?php checked($row !== null); ?>>
                        صحت اطلاعات بالا را تأیید می‌کنم (به‌جای امضای مراجع).
                    </label>
                </p>

                <button type="submit" class="zdp-btn-primary zdp-intake-submit"><?php echo esc_html($submit); ?></button>
                <p id="zdp-intake-result" role="status" aria-live="polite"></p>
            </form>
        </div>
        <?php

        return (string) ob_get_clean();
    }

    // ------------------------------------------------------------------
    // اطلاع‌رسانی (پیامک)
    // ------------------------------------------------------------------

    public function on_booking_confirmed(int $user_id): void
    {
        $this->maybe_notify($user_id, false);
    }

    /** یادآوری برای کسانی که نوبت تأییدشده‌ی نزدیک دارند ولی فرم را پر نکرده‌اند. */
    public function process_reminders(): void
    {
        if (!$this->sms_enabled()) {
            return;
        }

        global $wpdb;
        $bookings = Installer::table('bookings');
        $window   = max(6, (int) get_option('zdp_intake_remind_hours', 48));
        $today    = gmdate('Y-m-d');
        $until    = gmdate('Y-m-d', time() + ($window * HOUR_IN_SECONDS));

        $user_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT DISTINCT user_id FROM $bookings
             WHERE status = 'تایید شده' AND booking_date BETWEEN %s AND %s",
            $today,
            $until
        ));

        foreach ($user_ids as $uid) {
            $this->maybe_notify((int) $uid, true);
        }
    }

    private function sms_enabled(): bool
    {
        return (string) get_option('zdp_sms_intake_body_id', '') !== '';
    }

    private function maybe_notify(int $user_id, bool $is_reminder): void
    {
        if (!$this->sms_enabled() || $this->has_submitted($user_id)) {
            return;
        }

        $count = (int) get_user_meta($user_id, self::META_SMS_COUNT, true);
        $last  = (int) get_user_meta($user_id, self::META_SMS_LAST, true);
        $max   = max(1, (int) get_option('zdp_intake_max_sms', 3));

        if ($count >= $max) {
            return;
        }
        // فاصله‌ی حداقل ۲۰ ساعت بین دو پیامک (جلوگیری از پیامک پشت‌سرهم).
        if ($is_reminder && $last > 0 && (time() - $last) < 20 * HOUR_IN_SECONDS) {
            return;
        }
        if (!$is_reminder && $last > 0 && (time() - $last) < 2 * HOUR_IN_SECONDS) {
            return;
        }

        $mobile = zdp_get_user_mobile($user_id);
        if ($mobile === '') {
            return;
        }

        $full = (string) get_user_meta($user_id, 'zdp_full_name', true);
        $name = $full !== '' ? $full : 'مراجع گرامی';

        $sent = apply_filters('pas_send_sms', null, $mobile, (string) get_option('zdp_sms_intake_body_id', ''), [
            'NAME' => $name,
        ]);

        if ($sent) {
            update_user_meta($user_id, self::META_SMS_LAST, time());
            update_user_meta($user_id, self::META_SMS_COUNT, $count + 1);
            audit_log('intake_sms_sent', ['user_id' => $user_id, 'n' => $count + 1]);
        } else {
            audit_log('intake_sms_failed', ['user_id' => $user_id]);
        }
    }

    // ------------------------------------------------------------------
    // پیشخوان
    // ------------------------------------------------------------------

    public function render_admin_tab(): void
    {
        global $wpdb;
        $table = Installer::table(self::TABLE_KEY);
        $rows  = $wpdb->get_results("SELECT * FROM $table ORDER BY submitted_at DESC LIMIT 200"); // phpcs:ignore WordPress.DB.PreparedSQL
        $view  = isset($_GET['view']) ? absint($_GET['view']) : 0; // phpcs:ignore WordPress.Security.NonceVerification
        $notice = isset($_GET['zdp_notice']) ? sanitize_key($_GET['zdp_notice']) : ''; // phpcs:ignore WordPress.Security.NonceVerification

        if ($notice === 'intake_saved') {
            echo '<div class="notice notice-success is-dismissible"><p>ذخیره شد.</p></div>';
        }

        $this->render_admin_settings();

        if ($view > 0) {
            $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $view)); // phpcs:ignore WordPress.DB.PreparedSQL
            if ($row) {
                $this->render_admin_detail($row);
            }
        }

        echo '<h3>فرم‌های تکمیل‌شده (' . (int) count($rows) . ')</h3>';
        echo '<table class="widefat striped"><thead><tr><th>نام</th><th>موبایل</th><th>شماره پرونده</th><th>تاریخ ثبت</th><th>عملیات</th></tr></thead><tbody>';
        if (!$rows) {
            echo '<tr><td colspan="5">هنوز فرمی ثبت نشده است.</td></tr>';
        }
        foreach ($rows as $r) {
            $d = $this->decode_payload($r);
            printf(
                '<tr><td>%s</td><td dir="ltr">%s</td><td>%s</td><td>%s</td><td><a class="button button-small" href="%s">مشاهده</a></td></tr>',
                esc_html((string) ($d['full_name'] ?? '—')),
                esc_html((string) ($d['phone'] ?? '')),
                esc_html((string) $r->file_number !== '' ? (string) $r->file_number : '—'),
                esc_html(Jalali::gregorian_to_jalali_string(get_date_from_gmt((string) $r->submitted_at, 'Y-m-d'))),
                esc_url(admin_url('admin.php?page=zdp-admin&tab=intake&view=' . (int) $r->id))
            );
        }
        echo '</tbody></table>';
    }

    private function render_admin_settings(): void
    {
        ?>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin:12px 0 20px;">
            <input type="hidden" name="action" value="zdp_intake_save_settings">
            <?php wp_nonce_field('zdp_intake_settings', 'zdp_intake_nonce'); ?>
            <h3>تنظیمات پیامک «فرم مراجع را پر کنید»</h3>
            <table class="form-table" role="presentation">
                <tr>
                    <th><label for="zdp_sms_intake_body_id">کد الگوی ملی‌پیامک (bodyId)</label></th>
                    <td>
                        <input type="text" id="zdp_sms_intake_body_id" name="zdp_sms_intake_body_id" dir="ltr" class="regular-text" value="<?php echo esc_attr((string) get_option('zdp_sms_intake_body_id', '')); ?>">
                        <p class="description">یک الگو با یک متغیر ({0} = نام مراجع) در پنل ملی‌پیامک بسازید، مثلاً: «{0} عزیز، لطفاً پیش از جلسه فرم مراجع را در پنل مراجعان (بخش فرم ارزیابی) پر کنید. پیوند امن». تا کد خالی است، پیامکی نمی‌رود (بنر داخل پنل همچنان نمایش داده می‌شود).</p>
                    </td>
                </tr>
                <tr>
                    <th><label for="zdp_intake_remind_hours">یادآوری برای نوبت‌های تا چند ساعت آینده</label></th>
                    <td><input type="number" id="zdp_intake_remind_hours" name="zdp_intake_remind_hours" min="6" max="240" value="<?php echo esc_attr((string) (int) get_option('zdp_intake_remind_hours', 48)); ?>"></td>
                </tr>
                <tr>
                    <th><label for="zdp_intake_max_sms">حداکثر تعداد پیامک برای هر مراجع</label></th>
                    <td><input type="number" id="zdp_intake_max_sms" name="zdp_intake_max_sms" min="1" max="10" value="<?php echo esc_attr((string) (int) get_option('zdp_intake_max_sms', 3)); ?>"></td>
                </tr>
            </table>
            <?php submit_button('ذخیره‌ی تنظیمات', 'secondary', 'submit', false); ?>
        </form>
        <?php
    }

    private function render_admin_detail(object $row): void
    {
        $d = $this->decode_payload($row);
        $labels = [
            'form_date' => 'تاریخ تکمیل فرم', 'full_name' => 'نام و نام خانوادگی', 'father_name' => 'نام پدر',
            'birth_date' => 'تاریخ تولد', 'age' => 'سن', 'gender' => 'جنسیت', 'marital' => 'وضعیت تاهل',
            'children' => 'تعداد فرزندان', 'education' => 'میزان تحصیلات', 'job' => 'شغل', 'phone' => 'شماره تماس',
            'address' => 'آدرس محل سکونت', 'referral' => 'منبع ارجاع',
            'med_history' => 'سابقه مصرف دارو یا بیماری خاص', 'med_details' => 'توضیح دارو/بیماری',
            'substance_history' => 'سابقه مصرف مواد و الکل', 'substance_details' => 'توضیح مصرف مواد/الکل',
            'counseling_history' => 'سابقه مشاوره و روانپزشک', 'counseling_details' => 'توضیح سابقه مشاوره/روانپزشک',
        ];
        echo '<div class="card" style="max-width:720px;padding:12px 18px;margin-bottom:20px;"><h3>جزئیات فرم</h3><table class="widefat striped"><tbody>';
        foreach ($labels as $k => $label) {
            printf('<tr><th style="width:180px;">%s</th><td>%s</td></tr>', esc_html($label), esc_html((string) ($d[$k] ?? '—')));
        }
        echo '</tbody></table>';
        ?>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin-top:12px;">
            <input type="hidden" name="action" value="zdp_intake_save_meta">
            <input type="hidden" name="intake_id" value="<?php echo (int) $row->id; ?>">
            <?php wp_nonce_field('zdp_intake_meta_' . (int) $row->id, 'zdp_intake_nonce'); ?>
            <label for="zdp_file_number"><strong>شماره پرونده:</strong></label>
            <input type="text" id="zdp_file_number" name="file_number" dir="ltr" maxlength="50" value="<?php echo esc_attr((string) $row->file_number); ?>">
            <?php submit_button('ذخیره', 'primary', 'submit', false); ?>
        </form></div>
        <?php
    }

    public function handle_admin_save_meta(): void
    {
        $this->security->require_manage_capability();
        $id = isset($_POST['intake_id']) ? absint($_POST['intake_id']) : 0;
        check_admin_referer('zdp_intake_meta_' . $id, 'zdp_intake_nonce');

        global $wpdb;
        $number = sanitize_text_field(wp_unslash($_POST['file_number'] ?? ''));
        $wpdb->update(Installer::table(self::TABLE_KEY), ['file_number' => mb_substr($number, 0, 50)], ['id' => $id], ['%s'], ['%d']);
        audit_log('intake_file_number_set', ['intake_id' => $id]);

        wp_safe_redirect(admin_url('admin.php?page=zdp-admin&tab=intake&view=' . $id . '&zdp_notice=intake_saved'));
        exit;
    }

    public function handle_admin_save_settings(): void
    {
        $this->security->require_manage_capability();
        check_admin_referer('zdp_intake_settings', 'zdp_intake_nonce');

        update_option('zdp_sms_intake_body_id', sanitize_text_field(wp_unslash($_POST['zdp_sms_intake_body_id'] ?? '')));
        update_option('zdp_intake_remind_hours', max(6, min(240, absint($_POST['zdp_intake_remind_hours'] ?? 48))));
        update_option('zdp_intake_max_sms', max(1, min(10, absint($_POST['zdp_intake_max_sms'] ?? 3))));

        wp_safe_redirect(admin_url('admin.php?page=zdp-admin&tab=intake&zdp_notice=intake_saved'));
        exit;
    }
}
