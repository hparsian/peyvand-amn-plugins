<?php

if (!defined('ABSPATH')) {
    exit;
}

final class Pas_CF_Case_File
{
    /**
     * اگر افزونه‌ی «هسته مشترک» فعال باشد، از پیاده‌سازی مرجع آن استفاده
     * می‌شود (همان منطقی که پنل مراجعان دارد). اگر فعال نباشد، یک fallback
     * محلی ساده‌تر اجرا می‌شود تا این افزونه بدون هسته هم از کار نیفتد —
     * ولی توصیه می‌شود هسته مشترک همیشه فعال باشد.
     */
    public static function normalize_phone(string $phone): string
    {
        if (class_exists('Pas_Core_Helpers')) {
            return Pas_Core_Helpers::normalize_mobile($phone);
        }

        $digits = preg_replace('/\D+/', '', $phone) ?? '';
        if (str_starts_with($digits, '0098')) {
            $digits = substr($digits, 4);
        } elseif (str_starts_with($digits, '98')) {
            $digits = substr($digits, 2);
        } elseif (str_starts_with($digits, '0')) {
            $digits = substr($digits, 1);
        }

        return '0' . $digits;
    }

    // ── CRUD پرونده ──────────────────────────────────────────────────────

    /**
     * فیلدهای حساس بالینی که رمزنگاری‌شده ذخیره می‌شوند. هم برای «مراجع»
     * و هم برای «همسر» یک نسخه‌ی جدا وجود دارد (پیشوند spouse_).
     */
    private const ENCRYPTED_FIELDS = ['medical_history', 'counseling_history', 'self_harm_history', 'substance_use_history'];

    /**
     * فیلدهای حساس بالینی رمزنگاری‌شده که فقط برای مراجع اصلی هستند (نسخه‌ی
     * همسر ندارند) — بر خلاف ENCRYPTED_FIELDS که هم نسخه‌ی خودِ مراجع و هم
     * نسخه‌ی spouse_ دارد.
     */
    private const ENCRYPTED_SOLO_FIELDS = ['symptom_mood_depression', 'symptom_anxiety', 'q_schema_attachment'];

    /**
     * ستون‌های متنی ساده (غیررمزنگاری‌شده) که در create/update مشترک‌اند.
     */
    private static function plain_fields(): array
    {
        return [
            'full_name', 'age', 'marital_status', 'education', 'occupation',
            'residence', 'marriage_type', 'children_count', 'topic',
            'reminder_consent', 'reminder_channel',
            'main_conflict_topics', 'q_main_reason', 'q_positive_negative_traits',
            'q_conflict_resolution', 'q_reason_staying', 'q_therapy_goals',
            'relationship_satisfaction_pre', 'relationship_satisfaction_post',
        ];
    }

    private static function spouse_plain_fields(): array
    {
        return ['spouse_full_name', 'spouse_age', 'spouse_education', 'spouse_occupation'];
    }

    private static function build_encrypted_columns(array $data, string $prefix = '', ?array $fields = null): array
    {
        $fields = $fields ?? self::ENCRYPTED_FIELDS;
        $out = [];
        foreach ($fields as $field) {
            $key    = $prefix . $field;
            $plain  = (string) ($data[$key] ?? '');
            $column = $prefix . $field . '_encrypted';
            // اگر خالی باشد یا کلید رمزنگاری تنظیم نشده، چیزی رمزنگاری
            // نمی‌کنیم (برای جلوگیری از خطا در سایت‌هایی که هنوز کلید
            // Pas_Core_Crypto را تنظیم نکرده‌اند اما می‌خواهند بقیه‌ی فرم
            // را ذخیره کنند).
            if ($plain === '') {
                $out[$column] = '';
            } elseif (Pas_CF_Crypto::is_configured()) {
                $out[$column] = Pas_CF_Crypto::encrypt($plain);
            }
            // اگر کلید تنظیم نشده و متن غیرخالی است، مقدار قبلی دست‌نخورده
            // می‌ماند (ستون از آرایه‌ی $out حذف می‌شود تا در wpdb->update
            // اصلاً فرستاده نشود).
        }

        return $out;
    }

    public static function create(array $data): int
    {
        global $wpdb;

        $row = [
            'phone'                        => self::normalize_phone($data['phone'] ?? ''),
            'failed_marriage_history'       => sanitize_textarea_field($data['failed_marriage_history'] ?? ''),
            'spouse_phone'                  => self::normalize_phone($data['spouse_phone'] ?? ''),
            'spouse_failed_marriage_history' => sanitize_textarea_field($data['spouse_failed_marriage_history'] ?? ''),
            'prior_therapy'                 => sanitize_textarea_field($data['prior_therapy'] ?? ''),
            'assigned_therapist'            => (int) ($data['assigned_therapist'] ?? 0),
            'status'                        => sanitize_text_field($data['status'] ?? 'فعال'),
            'created_by'                    => get_current_user_id(),
            'created_at'                    => current_time('mysql', true),
            'updated_at'                    => current_time('mysql', true),
        ];

        foreach (self::plain_fields() as $f) {
            $row[$f] = sanitize_text_field($data[$f] ?? '');
        }
        foreach (self::spouse_plain_fields() as $f) {
            $row[$f] = sanitize_text_field($data[$f] ?? '');
        }
        $row += self::build_encrypted_columns($data, '');
        $row += self::build_encrypted_columns($data, 'spouse_');
        $row += self::build_encrypted_columns($data, '', self::ENCRYPTED_SOLO_FIELDS);

        $formats = array_fill(0, count($row), '%s');
        // ستون‌های عددی را اصلاح کن
        foreach (['assigned_therapist', 'created_by'] as $int_col) {
            $idx = array_search($int_col, array_keys($row), true);
            if ($idx !== false) {
                $formats[$idx] = '%d';
            }
        }

        $wpdb->insert(Pas_CF_Installer::table('cases'), $row, $formats);

        $case_id = (int) $wpdb->insert_id;
        Pas_CF_Audit_Log::record($case_id, 'create');

        if (!empty($data['partner_phone'])) {
            self::sync_partner_link($case_id, (string) $data['partner_phone']);
        }

        return $case_id;
    }

    public static function update(int $case_id, array $data): void
    {
        global $wpdb;

        $row = [
            'failed_marriage_history'       => sanitize_textarea_field($data['failed_marriage_history'] ?? ''),
            'spouse_phone'                  => self::normalize_phone($data['spouse_phone'] ?? ''),
            'spouse_failed_marriage_history' => sanitize_textarea_field($data['spouse_failed_marriage_history'] ?? ''),
            'prior_therapy'                 => sanitize_textarea_field($data['prior_therapy'] ?? ''),
            'assigned_therapist'            => (int) ($data['assigned_therapist'] ?? 0),
            'status'                        => sanitize_text_field($data['status'] ?? 'فعال'),
            'updated_at'                    => current_time('mysql', true),
        ];

        foreach (self::plain_fields() as $f) {
            $row[$f] = sanitize_text_field($data[$f] ?? '');
        }
        foreach (self::spouse_plain_fields() as $f) {
            $row[$f] = sanitize_text_field($data[$f] ?? '');
        }
        $row += self::build_encrypted_columns($data, '');
        $row += self::build_encrypted_columns($data, 'spouse_');
        $row += self::build_encrypted_columns($data, '', self::ENCRYPTED_SOLO_FIELDS);

        $formats = array_fill(0, count($row), '%s');
        foreach (['assigned_therapist'] as $int_col) {
            $idx = array_search($int_col, array_keys($row), true);
            if ($idx !== false) {
                $formats[$idx] = '%d';
            }
        }

        $wpdb->update(Pas_CF_Installer::table('cases'), $row, ['id' => $case_id], $formats, ['%d']);

        Pas_CF_Audit_Log::record($case_id, 'edit_case');

        if (isset($data['partner_phone'])) {
            self::sync_partner_link($case_id, (string) $data['partner_phone']);
        }
    }

    /**
     * فیلدهای رمزنگاری‌شده را برای نمایش در فرم/پرونده رمزگشایی و به‌عنوان
     * ویژگی‌های مجازی (مثلاً ->medical_history) روی همان آبجکت اضافه می‌کند.
     */
    private static function decorate_with_decrypted(?object $row): ?object
    {
        if (!$row) {
            return null;
        }

        foreach (self::ENCRYPTED_FIELDS as $field) {
            foreach (['', 'spouse_'] as $prefix) {
                $column   = $prefix . $field . '_encrypted';
                $virtual  = $prefix . $field;
                $encoded  = $row->$column ?? '';
                $row->$virtual = ($encoded !== '' && Pas_CF_Crypto::is_configured())
                    ? Pas_CF_Crypto::decrypt_or_plain($encoded)
                    : '';
            }
        }

        foreach (self::ENCRYPTED_SOLO_FIELDS as $field) {
            $column  = $field . '_encrypted';
            $encoded = $row->$column ?? '';
            $row->$field = ($encoded !== '' && Pas_CF_Crypto::is_configured())
                ? Pas_CF_Crypto::decrypt_or_plain($encoded)
                : '';
        }

        return $row;
    }

    /**
     * دو پرونده را به‌عنوان پارتنر/همسر به‌هم لینک می‌کند (دوطرفه). اگر
     * شماره خالی باشد، لینک قبلی (در هر دو طرف) پاک می‌شود. اگر پرونده‌ای
     * با آن شماره پیدا نشود، هیچ خطایی رخ نمی‌دهد — فقط لینک اعمال نمی‌شود.
     */
    private static function sync_partner_link(int $case_id, string $partner_phone): void
    {
        global $wpdb;

        $case = self::get_raw($case_id);
        if (!$case) {
            return;
        }

        $old_partner_id = (int) $case->partner_case_id;

        $partner_phone = trim($partner_phone);
        if ($partner_phone === '') {
            if ($old_partner_id) {
                $wpdb->update(Pas_CF_Installer::table('cases'), ['partner_case_id' => 0], ['id' => $old_partner_id], ['%d'], ['%d']);
                $wpdb->update(Pas_CF_Installer::table('cases'), ['partner_case_id' => 0], ['id' => $case_id], ['%d'], ['%d']);
            }
            return;
        }

        $target = self::get_by_phone($partner_phone);
        if (!$target || (int) $target->id === $case_id) {
            return; // شماره پیدا نشد یا کاربر شماره‌ی خودش را زده
        }

        // اگر قبلاً به پرونده‌ی دیگری لینک بوده، آن لینک قدیمی را پاک کن
        if ($old_partner_id && $old_partner_id !== (int) $target->id) {
            $wpdb->update(Pas_CF_Installer::table('cases'), ['partner_case_id' => 0], ['id' => $old_partner_id], ['%d'], ['%d']);
        }

        $wpdb->update(Pas_CF_Installer::table('cases'), ['partner_case_id' => $target->id], ['id' => $case_id], ['%d'], ['%d']);
        $wpdb->update(Pas_CF_Installer::table('cases'), ['partner_case_id' => $case_id], ['id' => $target->id], ['%d'], ['%d']);
    }

    public static function get_partner(object $case): ?object
    {
        if (empty($case->partner_case_id)) {
            return null;
        }

        return self::get_raw((int) $case->partner_case_id);
    }

    /** بدون رمزگشایی — برای عملیات داخلی مثل sync_partner_link. */
    private static function get_raw(int $case_id): ?object
    {
        global $wpdb;

        $row = $wpdb->get_row($wpdb->prepare(
            'SELECT * FROM ' . Pas_CF_Installer::table('cases') . ' WHERE id = %d',
            $case_id
        ));

        return $row ?: null;
    }

    public static function get(int $case_id): ?object
    {
        return self::decorate_with_decrypted(self::get_raw($case_id));
    }

    public static function get_by_phone(string $phone): ?object
    {
        global $wpdb;

        $row = $wpdb->get_row($wpdb->prepare(
            'SELECT * FROM ' . Pas_CF_Installer::table('cases') . ' WHERE phone = %s ORDER BY id DESC LIMIT 1',
            self::normalize_phone($phone)
        ));

        return $row ?: null;
    }

    public static function list_all(int $limit = 200): array
    {
        global $wpdb;

        return $wpdb->get_results($wpdb->prepare(
            'SELECT * FROM ' . Pas_CF_Installer::table('cases') . ' ORDER BY updated_at DESC LIMIT %d',
            $limit
        ));
    }

    /**
     * فقط پرونده‌های تخصیص‌داده‌شده به یک درمانگر مشخص، به‌اضافه‌ی
     * پرونده‌های قدیمی تخصیص‌نیافته (assigned_therapist=0) که خودِ همین
     * کاربر ساخته — دقیقاً همان قانونی که در
     * Pas_CF_Security::current_user_can_access_case پیاده شده. این دو باید
     * همیشه هم‌خوان بمانند، وگرنه پرونده‌ای در لیست دیده نمی‌شود ولی با
     * لینک مستقیم باز می‌شود (یا برعکس).
     */
    public static function list_for_therapist(int $therapist_id, int $limit = 200): array
    {
        global $wpdb;

        // فاز ۹: شرط از قرارداد مرکزی دسترسی می‌آید (نه کپی محلی)، تا با
        // current_user_can_access_case و گزارش‌ها هم‌یشه یکی بماند.
        [$scope_sql, $scope_params] = Pas_CF_Security::case_scope_sql($therapist_id, '', false);

        return $wpdb->get_results($wpdb->prepare(
            'SELECT * FROM ' . Pas_CF_Installer::table('cases') . "
             WHERE $scope_sql
             ORDER BY updated_at DESC LIMIT %d",
            ...array_merge($scope_params, [$limit])
        ));
    }

    // ── نگاشت فرم ارزیابی به پرونده (فاز ۲) ─────────────────────────────

    /**
     * مقادیر فعلی پرونده به تفکیک نام فیلد مقصد — برای مقایسه در پیش‌نمایش.
     * فیلدهای رمزنگاری‌شده از ویژگی‌های مجازی رمزگشایی‌شده خوانده می‌شوند.
     */
    private static function intake_current_values(object $case): array
    {
        return [
            'full_name'              => (string) ($case->full_name ?? ''),
            'phone'                  => (string) ($case->phone ?? ''),
            'age'                    => (string) ($case->age ?? ''),
            'gender'                 => (string) ($case->gender ?? ''),
            'marital_status'         => (string) ($case->marital_status ?? ''),
            'education'              => (string) ($case->education ?? ''),
            'occupation'             => (string) ($case->occupation ?? ''),
            'residence'              => (string) ($case->residence ?? ''),
            'children_count'         => (string) ($case->children_count ?? ''),
            'referral_source'        => (string) ($case->referral_source ?? ''),
            'father_name'            => (string) ($case->father_name ?? ''),
            'birth_date'             => (string) ($case->birth_date ?? ''),
            'medical_history'        => (string) ($case->medical_history ?? ''),
            'counseling_history'     => (string) ($case->counseling_history ?? ''),
            'substance_use_history'  => (string) ($case->substance_use_history ?? ''),
        ];
    }

    /**
     * پیش‌نمایش نگاشت فرم → پرونده. هیچ چیزی نمی‌نویسد.
     * تاریخ تولد در پرونده میلادی ذخیره می‌شود؛ برای مقایسه‌ی درست، مقدار
     * شمسی فرم به میلادی تبدیل و وضعیت دوباره محاسبه می‌شود، ولی برای نمایش
     * همان شمسی می‌ماند.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function intake_mapping_preview(object $case, array $intake): array
    {
        if (!class_exists('Pas_Core_Field_Mapper')) {
            return [];
        }

        $data = isset($intake['data']) && is_array($intake['data']) ? $intake['data'] : [];
        if (empty($data)) {
            return [];
        }

        $rows = Pas_Core_Field_Mapper::preview($data, self::intake_current_values($case));

        foreach ($rows as $index => $row) {
            if (($row['target'] ?? '') !== 'birth_date') {
                continue;
            }

            $gregorian = Pas_CF_Jalali::jalali_string_to_gregorian_string((string) ($row['value'] ?? ''));
            $rows[$index]['value_gregorian']  = $gregorian ?? '';
            $rows[$index]['current_display']  = ($row['current'] ?? '') !== ''
                ? Pas_CF_Jalali::to_jalali_string((string) $row['current'])
                : '';

            if ($gregorian === null) {
                $rows[$index]['status'] = 'invalid';
            } elseif (($row['current'] ?? '') === '') {
                $rows[$index]['status'] = 'fill';
            } elseif ((string) $row['current'] === $gregorian) {
                $rows[$index]['status'] = 'same';
            } else {
                $rows[$index]['status'] = 'conflict';
            }
        }

        return $rows;
    }

    /**
     * اعمال طرح نگاشت روی پرونده. فقط فیلدهای خالی (و در صورت درخواست صریح،
     * فیلدهای دارای اختلاف) نوشته می‌شوند. برای هر فیلد نوشته‌شده، منبع و هش
     * مقدار قبلی ثبت می‌شود. خروجی: تعداد نوشته‌شده/ردشده.
     *
     * @param array<int,array<string,mixed>> $rows خروجی intake_mapping_preview
     * @return array{updated:int,skipped:int}
     */
    public static function apply_intake_mapping(
        int $case_id,
        array $rows,
        bool $overwrite = false,
        string $source_ref = '',
        ?string $submitted_at = null
    ): array {
        global $wpdb;

        $case = self::get($case_id);
        if (!$case) {
            return ['updated' => 0, 'skipped' => 0];
        }

        $data       = [];
        $provenance = [];

        foreach ($rows as $row) {
            $status = (string) ($row['status'] ?? '');

            if (!in_array($status, ['fill', 'conflict'], true)) {
                continue;
            }
            if ($status === 'conflict' && !$overwrite) {
                continue;
            }

            $target = (string) ($row['target'] ?? '');
            $value  = (string) ($row['value'] ?? '');

            if ($target === '' || $value === '') {
                continue;
            }

            if (($row['transform'] ?? '') === 'date_jalali') {
                $gregorian = Pas_CF_Jalali::jalali_string_to_gregorian_string($value);
                if ($gregorian === null) {
                    continue; // تاریخ نامعتبر: هیچ چیزی نوشته نمی‌شود
                }
                $value = $gregorian;
            }

            if (($row['sensitivity'] ?? '') === 'clinical') {
                if (!Pas_CF_Crypto::is_configured()) {
                    continue; // بدون کلید رمزنگاری، داده‌ی بالینی ذخیره نمی‌شود
                }
                $data[$target . '_encrypted'] = Pas_CF_Crypto::encrypt(sanitize_textarea_field($value));
            } else {
                $data[$target] = sanitize_text_field($value);
            }

            $provenance[$target] = (string) ($case->{$target} ?? '');
        }

        if (!empty($submitted_at) && preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $submitted_at)) {
            $data['intake_submitted_at'] = $submitted_at;
        }

        if (empty($data)) {
            return ['updated' => 0, 'skipped' => count($rows)];
        }

        $data['updated_at'] = current_time('mysql', true);
        $formats            = array_fill(0, count($data), '%s');

        $wpdb->update(Pas_CF_Installer::table('cases'), $data, ['id' => $case_id], $formats, ['%d']);

        if (class_exists('Pas_Core_Field_Mapper')) {
            foreach ($provenance as $field => $previous) {
                Pas_Core_Field_Mapper::record('case', $case_id, $field, 'intake', $source_ref, $previous);
            }
        }

        Pas_CF_Audit_Log::record($case_id, 'apply_intake_map');

        return [
            'updated' => count($provenance),
            'skipped' => max(0, count($rows) - count($provenance)),
        ];
    }

    // ── یادداشت‌های جلسه (رمزنگاری‌شده) ───────────────────────────────────

    /** شماره‌ی جلسه: فقط عدد صحیح 0..999 (0 = نامشخص). */
    public static function clamp_session_number(int $n): int
    {
        return max(0, min(999, $n));
    }

    /**
     * ورودی خام فرم (ممکن است ارقام فارسی/عربی یا خالی باشد) → عدد 0..999.
     * مقدار نامعتبر/خالی 0 می‌شود (یعنی «نامشخص») نه خطا.
     */
    public static function parse_session_number($raw): int
    {
        $digits = Pas_CF_Jalali::to_english_digits(trim((string) $raw));

        return ctype_digit($digits) ? self::clamp_session_number((int) $digits) : 0;
    }

    /** وضعیت‌های مجاز جلسه (برای انتخاب در فرم و برچسب در تایم‌لاین). */
    public static function session_statuses(): array
    {
        return [
            'done'      => 'برگزار شد',
            'scheduled' => 'زمان‌بندی شده',
            'cancelled' => 'لغو شد',
            'no_show'   => 'عدم حضور',
        ];
    }

    /** وضعیت نامعتبر → 'done' (نه خطا، تا ثبت جلسه‌ی قدیمی سد نشود). */
    public static function parse_session_status($raw): string
    {
        $status = sanitize_key((string) $raw);

        return isset(self::session_statuses()[$status]) ? $status : 'done';
    }

    /** مدت جلسه: دقیقه، عدد 0..600 (0 = نامشخص). */
    public static function parse_duration($raw): int
    {
        $digits = Pas_CF_Jalali::to_english_digits(trim((string) $raw));

        return ctype_digit($digits) ? max(0, min(600, (int) $digits)) : 0;
    }

    /** نرمال‌سازی متادیتای جلسه برای درج/به‌روزرسانی. */
    private static function session_meta_columns(array $meta): array
    {
        $out = [];

        if (array_key_exists('duration_minutes', $meta)) {
            $out['duration_minutes'] = self::parse_duration($meta['duration_minutes']);
        }
        if (array_key_exists('status', $meta)) {
            $out['status'] = self::parse_session_status($meta['status']);
        }
        if (array_key_exists('is_joint', $meta)) {
            $out['is_joint'] = !empty($meta['is_joint']) ? 1 : 0;
        }
        if (array_key_exists('next_session_date', $meta)) {
            $next = (string) $meta['next_session_date'];
            // فقط تاریخ میلادی معتبر (YYYY-MM-DD) پذیرفته می‌شود؛ خالی = حذف.
            // ستون varchar است نه date، تا «خالی» بدون NULL/خطای strict mode
            // قابل ذخیره باشد.
            $out['next_session_date'] = preg_match('/^\d{4}-\d{2}-\d{2}$/', $next) ? $next : '';
        }

        return $out;
    }

    public static function add_note(int $case_id, string $session_date, string $content, int $session_number = 0, array $meta = []): int
    {
        global $wpdb;

        $row = [
            'case_id'           => $case_id,
            'session_date'      => $session_date,
            'session_number'    => self::clamp_session_number($session_number),
            'content_encrypted' => Pas_CF_Crypto::encrypt($content),
            'author_id'         => get_current_user_id(),
            'created_at'        => current_time('mysql', true),
        ] + self::session_meta_columns($meta);

        $formats = ['%d', '%s', '%d', '%s', '%d', '%s'];
        foreach (array_keys(self::session_meta_columns($meta)) as $column) {
            $formats[] = in_array($column, ['duration_minutes', 'is_joint'], true) ? '%d' : '%s';
        }

        $wpdb->insert(Pas_CF_Installer::table('notes'), $row, $formats);

        // شناسهٔ یادداشت باید همین‌جا برداشته شود. قبلاً مقدار برگشتی
        // `$wpdb->insert_id` بعد از `Pas_CF_Audit_Log::record()` خوانده می‌شد و
        // آن متد خودش یک INSERT در جدول لاگ انجام می‌دهد؛ بنابراین تابع id
        // **ردیف لاگ** را برمی‌گرداند، نه id یادداشت. هر مصرف‌کننده‌ای که از
        // مقدار برگشتی برای لینک/ویرایش استفاده کند روی رکورد اشتباه کار
        // می‌کرد (باگ واقعی، پیدا شده در آزمون فاز ۱۱).
        $note_id = (int) $wpdb->insert_id;

        $wpdb->update(Pas_CF_Installer::table('cases'), ['updated_at' => current_time('mysql', true)], ['id' => $case_id], ['%s'], ['%d']);

        Pas_CF_Audit_Log::record($case_id, 'add_note');

        return $note_id;
    }

    /**
     * ویرایش یادداشت جلسه‌ی قبلاً ثبت‌شده. case_id ورودی (که از فرم می‌آید)
     * باید دقیقاً با case_id ذخیره‌شده روی خود یادداشت برابر باشد — بدون
     * این چک، کسی که به پرونده‌ی A دسترسی دارد می‌توانست با ساختن دستی
     * یک note_id متعلق به پرونده‌ی B، یادداشت پرونده‌ی دیگری را ویرایش کند
     * (همان کلاس باگی که در handle_add_note/handle_save_case هم رفع شده
     * بود). بازگشت false یعنی یادداشت پیدا نشد یا به این پرونده تعلق ندارد.
     */
    public static function update_note(int $note_id, int $case_id, string $session_date, string $content, ?int $session_number = null, ?array $meta = null): bool
    {
        global $wpdb;

        $table = Pas_CF_Installer::table('notes');
        $existing = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $note_id));

        if (!$existing || (int) $existing->case_id !== $case_id) {
            return false;
        }

        // نسخه‌ی *فعلی* (قبل از این ویرایش) را snapshot می‌کنیم تا اگر لازم
        // شد بشود دید قبلاً چه چیزی ثبت شده بود — بدون این، ویرایش یعنی
        // نسخه‌ی قبلی برای همیشه گم می‌شود.
        $wpdb->insert(Pas_CF_Installer::table('note_history'), [
            'note_id'           => $note_id,
            'case_id'           => $case_id,
            'session_date'      => $existing->session_date,
            'session_number'    => (int) $existing->session_number,
            'content_encrypted' => $existing->content_encrypted,
            'author_id'         => $existing->author_id,
            'action'            => 'edited',
            'changed_by'        => get_current_user_id(),
            'changed_at'        => current_time('mysql', true),
        ], ['%d', '%d', '%s', '%d', '%s', '%d', '%s', '%d', '%s']);

        $update_data    = [
            'session_date'      => $session_date,
            'content_encrypted' => Pas_CF_Crypto::encrypt($content),
        ];
        $update_formats = ['%s', '%s'];
        if ($session_number !== null) { // null = شماره‌ی جلسه را تغییر نده
            $update_data['session_number'] = self::clamp_session_number($session_number);
            $update_formats[]              = '%d';
        }

        // متادیتای جلسه فقط وقتی فرستاده شود که صریحاً خواسته شده باشد
        // (null = دست نزن) — قاعده‌ی همان session_number.
        if ($meta !== null) {
            foreach (self::session_meta_columns($meta) as $column => $value) {
                $update_data[$column] = $value;
                $update_formats[]     = in_array($column, ['duration_minutes', 'is_joint'], true) ? '%d' : '%s';
            }
        }
        $wpdb->update($table, $update_data, ['id' => $note_id], $update_formats, ['%d']);

        $wpdb->update(Pas_CF_Installer::table('cases'), ['updated_at' => current_time('mysql', true)], ['id' => $case_id], ['%s'], ['%d']);

        Pas_CF_Audit_Log::record($case_id, 'edit_note');

        return true;
    }

    /**
     * حذف یادداشت جلسه. مثل update_note، ابتدا محتوای فعلی را (این‌بار با
     * action='deleted') در note_history نگه می‌داریم — یعنی حذف یادداشت
     * داده را واقعاً از بین نمی‌برد، فقط از نمای اصلی پرونده خارج می‌کند؛
     * برای یک پرونده‌ی بالینی این محافظه‌کاری عمدی است.
     */
    public static function delete_note(int $note_id, int $case_id): bool
    {
        global $wpdb;

        $table = Pas_CF_Installer::table('notes');
        $existing = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $note_id));

        if (!$existing || (int) $existing->case_id !== $case_id) {
            return false;
        }

        $wpdb->insert(Pas_CF_Installer::table('note_history'), [
            'note_id'           => $note_id,
            'case_id'           => $case_id,
            'session_date'      => $existing->session_date,
            'session_number'    => (int) $existing->session_number,
            'content_encrypted' => $existing->content_encrypted,
            'author_id'         => $existing->author_id,
            'action'            => 'deleted',
            'changed_by'        => get_current_user_id(),
            'changed_at'        => current_time('mysql', true),
        ], ['%d', '%d', '%s', '%d', '%s', '%d', '%s', '%d', '%s']);

        $wpdb->delete($table, ['id' => $note_id], ['%d']);

        $wpdb->update(Pas_CF_Installer::table('cases'), ['updated_at' => current_time('mysql', true)], ['id' => $case_id], ['%s'], ['%d']);

        Pas_CF_Audit_Log::record($case_id, 'delete_note');

        return true;
    }

    /**
     * تاریخچه‌ی یک یادداشت (نسخه‌های قبلی + رویداد حذف، اگر باشد) با متن
     * رمزگشایی‌شده، جدیدترین اول — فقط برای نمایش داخل details هر یادداشت.
     */
    public static function get_note_history(int $note_id): array
    {
        global $wpdb;

        $rows = $wpdb->get_results($wpdb->prepare(
            'SELECT * FROM ' . Pas_CF_Installer::table('note_history') . ' WHERE note_id = %d ORDER BY changed_at DESC, id DESC',
            $note_id
        ));

        foreach ($rows as $row) {
            $row->content = Pas_CF_Crypto::decrypt_or_plain($row->content_encrypted);
        }

        return $rows;
    }

    /**
     * یک نسخه‌ی قبلی (ویرایش‌شده یا حذف‌شده) را از note_history برمی‌گرداند.
     * دو حالت:
     *   ۱) یادداشت هنوز در جدول notes وجود دارد (نسخه‌ی «ویرایش‌شده»‌ی
     *      قبلی) → قبل از بازنویسی، نسخه‌ی *فعلی* هم snapshot می‌شود (پس
     *      بازگردانی خودش هم قابل واگرد است)، سپس محتوای history جایگزین می‌شود.
     *   ۲) یادداشت حذف شده (دیگر در notes نیست) → یک ردیف جدید در notes
     *      ساخته می‌شود (id جدید می‌گیرد، id قبلی قابل بازیابی نیست چون
     *      AUTO_INCREMENT است — ولی محتوا/تاریخ/نویسنده همان قبلی است).
     * در هر دو حالت، ciphertext مستقیم کپی می‌شود (نه decrypt+encrypt
     * دوباره) چون همان کلید/nonce معتبر است.
     */
    public static function restore_note_version(int $history_id, int $case_id): bool
    {
        global $wpdb;

        $history_table = Pas_CF_Installer::table('note_history');
        $notes_table   = Pas_CF_Installer::table('notes');

        $snapshot = $wpdb->get_row($wpdb->prepare("SELECT * FROM $history_table WHERE id = %d", $history_id));
        if (!$snapshot || (int) $snapshot->case_id !== $case_id) {
            return false;
        }

        $current_note = $wpdb->get_row($wpdb->prepare("SELECT * FROM $notes_table WHERE id = %d", $snapshot->note_id));

        if ($current_note) {
            // نسخه‌ی فعلی را قبل از بازنویسی snapshot کن — مثل update_note.
            $wpdb->insert($history_table, [
                'note_id'           => $current_note->id,
                'case_id'           => $case_id,
                'session_date'      => $current_note->session_date,
                'session_number'    => (int) $current_note->session_number,
                'content_encrypted' => $current_note->content_encrypted,
                'author_id'         => $current_note->author_id,
                'action'            => 'edited',
                'changed_by'        => get_current_user_id(),
                'changed_at'        => current_time('mysql', true),
            ], ['%d', '%d', '%s', '%d', '%s', '%d', '%s', '%d', '%s']);

            $wpdb->update($notes_table, [
                'session_date'      => $snapshot->session_date,
                'session_number'    => (int) $snapshot->session_number,
                'content_encrypted' => $snapshot->content_encrypted,
            ], ['id' => $current_note->id], ['%s', '%d', '%s'], ['%d']);
        } else {
            // یادداشت حذف شده بود — دوباره می‌سازیمش.
            $wpdb->insert($notes_table, [
                'case_id'           => $case_id,
                'session_date'      => $snapshot->session_date,
                'session_number'    => (int) $snapshot->session_number,
                'content_encrypted' => $snapshot->content_encrypted,
                'author_id'         => $snapshot->author_id,
                'created_at'        => current_time('mysql', true),
            ], ['%d', '%s', '%d', '%s', '%d', '%s']);
        }

        $wpdb->update(Pas_CF_Installer::table('cases'), ['updated_at' => current_time('mysql', true)], ['id' => $case_id], ['%s'], ['%d']);
        Pas_CF_Audit_Log::record($case_id, 'restore_note');

        return true;
    }

    /** یادداشت‌های حذف‌شده‌ی این پرونده (فقط آخرین snapshot هر note_id حذف‌شده)، جدیدترین اول. */
    public static function get_deleted_notes(int $case_id): array
    {
        global $wpdb;

        $history_table = Pas_CF_Installer::table('note_history');
        $notes_table    = Pas_CF_Installer::table('notes');

        // فقط رویدادهای 'deleted' که یادداشتشان الان واقعاً در notes وجود
        // ندارد (اگر بعداً restore شده باشد، یک ردیف جدید در notes ساخته
        // می‌شود ولی همان note_id قدیمی هرگز دوباره در notes ظاهر نمی‌شود —
        // پس این فیلتر برای جلوگیری از نمایش دوباره‌ی چیزی که قبلاً
        // بازگردانی شده لازم نیست، چون note_id قدیمی هیچ‌وقت واقعاً به
        // notes برنمی‌گردد؛ ولی برای اطمینان همچنان چک می‌کنیم).
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT h.* FROM $history_table h
             WHERE h.case_id = %d AND h.action = 'deleted'
               AND NOT EXISTS (SELECT 1 FROM $notes_table n WHERE n.id = h.note_id)
             ORDER BY h.changed_at DESC",
            $case_id
        ));

        foreach ($rows as $row) {
            $row->content = Pas_CF_Crypto::decrypt_or_plain($row->content_encrypted);
        }

        return $rows;
    }

    /**
     * برای هشدار «پرونده‌ی راکد» در لیست: آخرین تاریخ جلسه‌ی هر پرونده در
     * یک کوئری گروه‌بندی‌شده (نه یک کوئری جداگانه به‌ازای هر پرونده).
     * خروجی: [case_id => 'Y-m-d آخرین جلسه'].
     */
    public static function get_last_session_map(array $case_ids): array
    {
        global $wpdb;

        $case_ids = array_values(array_unique(array_map('intval', $case_ids)));
        if (empty($case_ids)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($case_ids), '%d'));
        $table = Pas_CF_Installer::table('notes');

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT case_id, MAX(session_date) AS last_session FROM $table WHERE case_id IN ($placeholders) GROUP BY case_id",
            ...$case_ids
        ));

        $map = [];
        foreach ($rows as $row) {
            $map[(int) $row->case_id] = $row->last_session;
        }

        return $map;
    }

    /** یادداشت‌ها را با متن رمزگشایی‌شده برمی‌گرداند (فقط برای نمایش در پرونده). */
    public static function get_notes(int $case_id): array
    {
        global $wpdb;

        $rows = $wpdb->get_results($wpdb->prepare(
            'SELECT * FROM ' . Pas_CF_Installer::table('notes') . ' WHERE case_id = %d ORDER BY session_date DESC, id DESC',
            $case_id
        ));

        foreach ($rows as $row) {
            $row->content = Pas_CF_Crypto::decrypt_or_plain($row->content_encrypted);
        }

        return $rows;
    }

    // ── نمای زوجی / جلسه‌ی مشترک (فاز ۶) ────────────────────────────────

    /**
     * جلسه‌های دو پرونده‌ی لینک‌شده در یک نمای زمانی. **هیچ متن یادداشتی
     * برنمی‌گردد** — فقط تاریخ، شماره، وضعیت، مدت، نویسنده و پرچم «مشترک».
     *
     * مرز دسترسی: جلسه‌های پرونده‌ی پارتنر فقط وقتی اضافه می‌شوند که کاربر
     * جاری به همان پرونده هم دسترسی داشته باشد؛ در غیر این صورت فقط
     * جلسه‌های خودِ این پرونده برمی‌گردد و `can_view_partner=false` تا UI
     * به‌جای نمایش داده، پیام بدهد (هیچ شمارشی هم لو نمی‌رود).
     *
     * @return array{partner:object|null,can_view_partner:bool,sessions:array<int,array<string,mixed>>}
     */
    public static function get_couple_sessions(object $case, int $limit = 60): array
    {
        global $wpdb;

        $partner  = self::get_partner($case);
        $can_view = $partner ? Pas_CF_Security::current_user_can_access_case($partner) : false;

        $case_ids = [(int) $case->id];
        if ($partner && $can_view) {
            $case_ids[] = (int) $partner->id;
        }

        $placeholders = implode(',', array_fill(0, count($case_ids), '%d'));

        $rows = (array) $wpdb->get_results($wpdb->prepare(
            'SELECT case_id, session_date, session_number, status, duration_minutes, is_joint, author_id
             FROM ' . Pas_CF_Installer::table('notes') . "
             WHERE case_id IN ($placeholders)
             ORDER BY session_date ASC, id ASC
             LIMIT %d",
            ...array_merge($case_ids, [max(1, $limit)])
        ));

        $sessions = [];
        foreach ($rows as $row) {
            $sessions[] = [
                'side'      => (int) $row->case_id === (int) $case->id ? 'self' : 'partner',
                'date'      => (string) $row->session_date,
                'number'    => (int) ($row->session_number ?? 0),
                'status'    => (string) ($row->status ?? 'done'),
                'duration'  => (int) ($row->duration_minutes ?? 0),
                'is_joint'  => !empty($row->is_joint),
                'author_id' => (int) ($row->author_id ?? 0),
            ];
        }

        return [
            'partner'          => $partner,
            'can_view_partner' => $can_view,
            'sessions'         => $sessions,
        ];
    }

    // ── یادآور جلسه (فاز ۷) ─────────────────────────────────────────────

    /** کانال‌های مجاز یادآور → برچسب فارسی. */
    public const REMINDER_CHANNELS = [
        'sms'   => 'پیامک',
        'email' => 'ایمیل',
        'none'  => 'بدون یادآور',
    ];

    /**
     * نزدیکترین جلسه‌ی بعدی ثبت‌شده برای این پرونده (یا null). اولویت به
     * تاریخ‌های آینده داده می‌شود؛ اگر هیچ تاریخ آینده‌ای نباشد، آخرین
     * تاریخ ثبت‌شده برمی‌گردد تا درمانگر بتواند وضعیت را ببیند.
     */
    public static function get_next_session(object $case): ?object
    {
        global $wpdb;

        $row = $wpdb->get_row($wpdb->prepare(
            'SELECT id, next_session_date, session_number, session_date
             FROM ' . Pas_CF_Installer::table('notes') . "
             WHERE case_id = %d AND next_session_date <> ''
             ORDER BY (next_session_date >= %s) DESC, next_session_date ASC
             LIMIT 1",
            (int) $case->id,
            current_time('Y-m-d')
        ));

        return $row ?: null;
    }

    /**
     * چرا یادآور قابل ارسال نیست؟ یکی از:
     * ok | no_consent | no_session | no_channel | cooldown
     */
    public static function reminder_blocker(object $case, ?object $next): string
    {
        if (empty($case->reminder_consent)) {
            return 'no_consent';
        }

        if (!$next || empty($next->next_session_date)) {
            return 'no_session';
        }

        $channel = (string) ($case->reminder_channel ?? 'sms');
        if (!isset(self::REMINDER_CHANNELS[$channel]) || $channel === 'none') {
            return 'no_channel';
        }

        $last = (string) ($case->reminder_last_sent_at ?? '');
        if ($last !== '' && (time() - (int) strtotime($last . ' UTC')) < 12 * HOUR_IN_SECONDS) {
            return 'cooldown';
        }

        return 'ok';
    }

    /**
     * ثبت زمان ارسال یادآور — فقط متادیتا. متن پیام هیچ‌جا ذخیره نمی‌شود و
     * در لاگ دسترسی فقط اکشن «send_reminder» می‌نشیند.
     */
    public static function mark_reminder_sent(int $case_id): void
    {
        global $wpdb;

        $wpdb->update(
            Pas_CF_Installer::table('cases'),
            ['reminder_last_sent_at' => current_time('mysql', true)],
            ['id' => $case_id],
            ['%s'],
            ['%d']
        );

        Pas_CF_Audit_Log::record($case_id, 'send_reminder');
    }

    // ── اتصال به افزونه‌ی ورود (پنل مراجعان) ─────────────────────────────

    /**
     * پنل مراجعان با OTP، یک کاربر وردپرسی با user_login = 'zdp_' + موبایل
     * می‌سازد. اینجا فقط لوکاپ می‌کنیم — هیچ داده‌ای کپی/دوباره ذخیره
     * نمی‌شود.
     */
    public static function find_linked_wp_user(string $phone): ?\WP_User
    {
        $login = 'zdp_' . self::normalize_phone($phone);

        if (array_key_exists($login, self::$linked_user_cache)) {
            return self::$linked_user_cache[$login];
        }

        $user = get_user_by('login', $login);

        self::$linked_user_cache[$login] = $user ?: null;

        return $user ?: null;
    }

    /** @var array<string,\WP_User|null> کش lookup حساب‌های OTP (جلوگیری از N+1 در لیست‌ها) */
    private static array $linked_user_cache = [];

    /**
     * پیش‌بارگذاری حساب‌های OTP با یک کوئری برای همه‌ی شماره‌های یک صفحه.
     * بدون این، هر ردیف لیست پرونده‌ها یک کوئری جدا می‌زد (N+1).
     *
     * @param array<int,string> $phones
     */
    public static function preload_linked_wp_users(array $phones): void
    {
        $logins = [];

        foreach ($phones as $phone) {
            $login = 'zdp_' . self::normalize_phone((string) $phone);

            if ($login !== 'zdp_' && !array_key_exists($login, self::$linked_user_cache)) {
                $logins[$login] = true;
            }
        }

        if (!$logins) {
            return;
        }

        $users = get_users([
            'login__in' => array_keys($logins),
            'number'    => count($logins),
            'fields'    => 'all_with_meta',
        ]);

        $found = [];

        foreach ($users as $user) {
            $found[$user->user_login] = $user;
        }

        foreach (array_keys($logins) as $login) {
            self::$linked_user_cache[$login] = $found[$login] ?? null;
        }
    }

    /**
     * نوبت‌های ثبت‌شده در پنل مراجعان (zdp_bookings) را به‌صورت read-only
     * برای همین شماره می‌آورد — فقط اگر جدول پنل مراجعان روی همین دیتابیس
     * موجود باشد (یعنی آن افزونه فعال/نصب است). این افزونه هرگز چیزی در
     * جدول‌های آن افزونه نمی‌نویسد.
     */
    public static function get_linked_bookings(string $phone): array
    {
        global $wpdb;

        $user = self::find_linked_wp_user($phone);
        if (!$user) {
            return [];
        }

        $table = $wpdb->prefix . 'zdp_bookings';
        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) !== $table) {
            return []; // افزونه‌ی پنل مراجعان فعال نیست
        }

        return $wpdb->get_results($wpdb->prepare(
            "SELECT service_name, booking_date, booking_time, status FROM $table WHERE user_id = %d ORDER BY booking_date DESC LIMIT 20",
            $user->ID
        ));
    }

    /**
     * نتایج آزمون‌های روان‌سنجی: قرارداد فیلتری آماده نگه داشته شده طبق
     * الگوی psy_get_client_name_for_phone. اگر quiz-enterprise این فیلتر
     * را پیاده‌سازی کند (فعلاً نمی‌کند)، خودکار وصل می‌شود؛ در غیر این
     * صورت آرایه‌ی خالی برمی‌گردد و هیچ خطایی رخ نمی‌دهد.
     */
    public static function get_linked_quiz_results(string $phone): array
    {
        return apply_filters('psy_get_completed_results_for_phone', [], self::normalize_phone($phone));
    }

    /**
     * نمره‌ی آزمون‌های تکمیل‌شده از افزونه‌ی آزمون‌ها — فقط از طریق فیلتر
     * `psy_get_assessment_scores_for_phone`. این افزونه هیچ‌وقت جدول
     * `psy_*` را مستقیم نمی‌خواند و ciphertext را خودش رمزگشایی نمی‌کند.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function get_assessment_scores(string $phone): array
    {
        $rows = apply_filters('psy_get_assessment_scores_for_phone', [], self::normalize_phone($phone));

        if (!is_array($rows)) {
            return [];
        }

        return array_values(array_filter($rows, static function ($row) {
            return is_array($row) && !empty($row['quiz_id']);
        }));
    }

    /**
     * روند هر آزمون برای یک مراجع: اولین و آخرین نمره‌ی قابل مقایسه و تفاضل.
     * آزمون‌هایی که نمره‌ی عددی ندارند (فقط تفسیر کیفی) با total=null و
     * delta=null می‌آیند تا فقط تعداد نوبت/لینک نمایش داده شود.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function get_assessment_trend(string $phone): array
    {
        $groups = [];

        foreach (self::get_assessment_scores($phone) as $row) {
            $quiz_id = (string) $row['quiz_id'];
            $groups[$quiz_id]['label']    = (string) ($row['label'] ?? $quiz_id);
            $groups[$quiz_id]['attempts'][] = $row;
        }

        $trend = [];

        foreach ($groups as $quiz_id => $group) {
            $all     = $group['attempts'];
            $scored  = array_values(array_filter($all, static function ($attempt) {
                return isset($attempt['total']) && $attempt['total'] !== null;
            }));
            $last    = $all[count($all) - 1];

            if (empty($scored)) {
                $trend[] = [
                    'quiz_id' => $quiz_id,
                    'label'   => $group['label'],
                    'first'   => null,
                    'last'    => null,
                    'delta'   => null,
                    'count'   => count($all),
                    'url'     => (string) ($last['url'] ?? ''),
                ];
                continue;
            }

            $first_attempt = $scored[0];
            $last_attempt  = $scored[count($scored) - 1];
            $has_compare   = count($scored) > 1;

            $trend[] = [
                'quiz_id'  => $quiz_id,
                'label'    => $group['label'],
                'first'    => (float) $first_attempt['total'],
                'last'     => (float) $last_attempt['total'],
                'delta'    => $has_compare
                    ? round((float) $last_attempt['total'] - (float) $first_attempt['total'], 1)
                    : null,
                'first_at' => (string) ($first_attempt['completed_at'] ?? ''),
                'last_at'  => (string) ($last_attempt['completed_at'] ?? ''),
                'count'    => count($all),
                'url'      => (string) ($last_attempt['url'] ?? ''),
            ];
        }

        return $trend;
    }

    /**
     * ارزیابی رابطه (CSI-16، quiz_id='csi') — قبل/بعد، از همان قرارداد
     * فیلتری. برای سازگاری با فراخوانی‌های قبلی (نمای پرینت) نگه داشته
     * شده؛ منبع داده دیگر جدول‌های افزونه‌ی آزمون‌ها نیست.
     *
     * @return array{before:object|null,after:object|null}
     */
    public static function get_relationship_assessment(string $phone): array
    {
        $csi = [];

        foreach (self::get_assessment_scores($phone) as $row) {
            if (($row['quiz_id'] ?? '') === 'csi') {
                $csi[] = $row;
            }
        }

        if (empty($csi)) {
            return [];
        }

        $to_object = static function (array $row): object {
            return (object) [
                'total'        => $row['total'] ?? null,
                'completed_at' => (string) ($row['completed_at'] ?? ''),
            ];
        };

        $first = $to_object($csi[0]);
        $last  = count($csi) > 1 ? $to_object($csi[count($csi) - 1]) : null;

        return ['before' => $first, 'after' => $last];
    }

    /** برای فیلتر عمومی pas_get_case_summary_for_phone — فقط خلاصه‌ی غیرحساس. */
    public static function get_public_summary_for_phone($default_value, string $phone)
    {
        $case = self::get_by_phone($phone);
        if (!$case) {
            return $default_value;
        }

        return [
            'has_case_file' => true,
            'status'        => $case->status,
            'topic'         => $case->topic,
        ];
    }

    /**
     * برای قرارداد فیلتری مشترک pas_lookup_known_client_name. فقط نام را
     * برمی‌گرداند (نه هیچ داده‌ی بالینی دیگری) چون این فیلتر در فرم‌های
     * «مراجع جدید» افزونه‌های دیگر (quiz-enterprise) هم مصرف می‌شود و آن‌ها
     * فقط برای پیشنهاد خودکار نام به آن نیاز دارند.
     */
    public static function get_known_name_for_phone(string $phone): string
    {
        $case = self::get_by_phone($phone);

        return ($case && !empty($case->full_name)) ? (string) $case->full_name : '';
    }
}
