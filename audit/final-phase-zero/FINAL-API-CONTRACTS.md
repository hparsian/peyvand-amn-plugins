# PEYVAND AMN ECOSYSTEM - Final API Contracts

## FINAL-API-CONTRACTS.md

### 1. Core Plugin API Contracts (pivand-amn-core 1.8.1)

#### 1.1 `pas_get_client` Filter

**Hook Name**: `apply_filters('pas_get_client', null, $query)`

**File Location**: `pivand-amn-core/pivand-amn-core.php` line 97-102 + `class-client-registry.php` line 295-318

**Signature**:
```php
pas_get_client($default, $query)
```

**Parameters**:
| Param | Type | Required | Default | Description |
|-------|------|----------|---------|-------------|
| `$default` | mixed | Yes | null | Returned if no match or unauthorized |
| `$query` | array | Yes | [] | Lookup query array |

**Query Array Formats** (at least one required):

| Format | Keys | Description |
|--------|------|-------------|
| `client_id` | `['client_id' => 'pc_abc123...']` | Lookup by Core client ID |
| `type + value` | `['type' => 'mobile', 'value' => '09123456789']` OR `['type' => 'email', 'value' => 'user@example.com']` | Lookup by identifier type and value |
| `wp_user_id` | `['wp_user_id' => 123]` | Lookup by WordPress user ID |

**Return Value**: 
- `mixed` - Client data array on success, `$default` (usually null) on failure/unauthorized

**Client Data Array Structure** (from `get_client()` method):
```php
[
    'client_id' => 'pc_abc123...',
    'display_name' => 'Patient Name',
    'status' => 'active' | 'review',
    'conflict_count' => (int),
    'sources_count' => (int),
    'identifiers' => [
        ['type' => 'mobile', 'value' => '09123456789'],
        ['type' => 'email', 'value' => 'user@example.com'],
    ]
]
```

**Authorization Check** (from `filter_get_client()`):
```php
$allowed = current_user_can('manage_options')
    || (defined('PAS_CF_CAP') && current_user_can(PAS_CF_CAP));
```
- Requires `manage_options` (admin) OR `PAS_CF_CAP` (therapist role)
- Returns `$default` if unauthorized

**Registered Via**: 
```php
add_filter('pas_get_client', ['Pas_Core_Client_Registry', 'filter_get_client'], 10, 2);
```
in `pivand-amn-core.php` line 102.

**Consumers** (from conversation historical memory and code references):
- Client portal identity lookup
- Quiz enterprise client name resolution
- Case file phone-to-client mapping (indirect via registry rebuild)
- Dashboard therapist patient lookup

**Example Usage**:
```php
$query = ['type' => 'mobile', 'value' => '09123456789'];
$client = apply_filters('pas_get_client', null, $query);
if ($client) {
    // $client['client_id'], $client['display_name'], etc.
}
```

#### 1.2 `pas_send_sms` Filter

**Hook Name**: `apply_filters('pas_send_sms', $null, $phone, $pattern, $data)`

**File Location**: Referenced in `class-case-file.php` and `class-admin.php`

**Signature**:
```php
pas_send_sms($null, $phone, $pattern, $data)
```

**Parameters**:
| Param | Type | Required | Description |
|-------|------|----------|-------------|
| `$null` | mixed | Yes | Null - result of previous filter in chain |
| `$phone` | string | Yes | Iranian mobile number (normalized) |
| `$pattern` | string | Yes | SMS pattern template with placeholders |
| `$data` | array | Yes | Additional data (NAME, DATE, etc.) |

**Pattern Placeholders** (from case file reminder logic):
- `{NAME}` - Patient name
- `{DATE}` - Jalali session date

**Return Value**: 
- `false` - SMS not sent (blocked)
- `mixed` - Sent result (truthy = success, false = failure)

**Authorization**: 
- Only registered via `has_filter('pas_send_sms')` check
- SMS-bridge plugin is the **ONLY authorized sender**
- Dashboard must check `has_filter('pas_send_sms')` before enabling SMS features

**Example Usage** (from case file reminder sending):
```php
$pattern = (string) apply_filters(
    'pas_cf_reminder_sms_pattern',
    (string) get_option('pas_cf_reminder_sms_pattern', '')
);

if ($pattern === '' || !has_filter('pas_send_sms')) {
    $result = 'sms_not_configured';
} else {
    $sent = apply_filters('pas_send_sms', null, $case->phone, $pattern, [
        'NAME' => $name,
        'DATE' => $jalali,
    ]);
    $result = $sent === false ? 'failed' : 'sent';
}
```

**Critical**: `has_filter('pas_send_sms')` must return TRUE for SMS to be sent. Dashboard must check this.

#### 1.3 Core Helper Functions (Not Hooks, but API Contracts)

**`Pas_CF_Crypto::is_configured()`**: 
```php
public static function is_configured(): bool
{
    return class_exists('Pas_Core_Crypto') && Pas_Core_Crypto::is_configured();
}
```
- Returns boolean
- Checks both class existence and internal configuration status
- Dashboard MUST call before expecting encrypted data to work

**`Pas_CF_Crypto::encrypt(string $plaintext)`**:
```php
public static function encrypt(string $plaintext): string
{
    if (!class_exists('Pas_Core_Crypto')) {
        throw new \\RuntimeException('افزونه‌ی «هسته مشترک» فعال نیست — رمزنگاری در دسترس نیست.');
    }
    return Pas_Core_Crypto::encrypt($plaintext);
}
```
- Throws RuntimeException if Pas_Core_Crypto not available
- Returns encrypted string on success

**`Pas_CF_Crypto::decrypt_or_plain(string $value)`**:
```php
public static function decrypt_or_plain(string $value): string
{
    if ($value === '') {
        return '';
    }
    $raw = base64_decode($value, true);
    if ($raw === false || strlen($raw) < 40) { // 24 (nonce) + 16 (tag)
        return $value; // Legacy plaintext - return as-is
    }
    return self::decrypt($value);
}
```
- **KEY BEHAVIOR**: Returns raw value as plaintext if base64 decode fails OR length < 40
- **Purpose**: Distinguishes valid ciphertext from legacy plaintext records
- **Distinction rule**: "ciphertext valid always base64 with length at least nonce(24)+tag(16); anything else is considered old plaintext"
- Dashboard must handle both decrypted content AND legacy plaintext display

**`Pas_CF_Crypto::decrypt(string $encoded)`**:
- Depends on `Pas_Core_Crypto::decrypt()`
- Returns decrypted string
- Throws exceptions if key invalid

---

### 2. Quiz Enterprise API Contracts (pivand-amn-quiz-enterprise 3.8.17)

#### 2.1 REST API Routes

**Base Namespace**: `psy/v1`

| Route | HTTP Method | Purpose | Permission Callback |
|-------|------------|---------|---------------------|
| `/quiz` | GET/POST | Quiz questions / submit | Not fully verified in audit |
| `/submit` | POST | Submit quiz answers | Not fully verified |
| `/draft` | POST | Save quiz draft | Not fully verified |
| `/report/(?P<id>\d+)` | GET | Get case report by ID | Not fully verified |
| `/report/(?P<id>\d+)/pdf` | GET | PDF export of report | Not fully verified |

**Route Registration** (from `class-psy-rest-handler.php`):
```php
register_rest_route( 'psy/v1', '/quiz', [ ... ] );
register_rest_route( 'psy/v1', '/submit', [ ... ] );
register_rest_route( 'psy/v1', '/draft', [ ... ] );
register_rest_route( 'psy/v1', '/report/(?P<id>\d+)', [ ... ] );
register_rest_route( 'psy/v1', '/report/(?P<id>\d+)/pdf', [ ... ] );
```

**Details**: Full permission callbacks, nonce requirements, and parameter schemas not fully verified in this read-only audit. Dashboard should inspect actual REST route registration and handler functions.

**Note**: Quiz REST routes are separate from Case File admin-post handlers. Dashboard integration with quiz data would need to authenticate against these routes.

#### 2.2 AJAX Actions

| Action | Hook | Purpose | Nonce | Capability |
|--------|------|---------|-------|------------|
| `zdp_send_otp` | `wp_ajax_zdp_send_otp` / `wp_ajax_nopriv_zdp_send_otp` | Send OTP for portal login | `wp_create_nonce('zdp_send_otp')` | Not specified |
| `zdp_verify_otp` | `wp_ajax_zdp_verify_otp` / `wp_ajax_nopriv_zdp_verify_otp` | Verify OTP code | `wp_create_nonce('zdp_verify_otp')` | Not specified |
| `zdp_submit_survey` | `wp_ajax_zdp_submit_survey` / `wp_ajax_nopriv_zdp_submit_survey` | Submit survey responses | `wp_create_nonce('zdp_submit_survey')` | Not specified |
| `zdp_quiz_submit_answers` | `wp_ajax_zdp_quiz_submit_answers` / `wp_ajax_nopriv_zdp_quiz_submit_answers` | Submit quiz answers | `wp_create_nonce('zdp_quiz_submit_answers')` | Not specified |
| `zdp_quiz_submit_phone` | `wp_ajax_zdp_quiz_submit_phone` / `wp_ajax_nopriv_zdp_quiz_submit_phone` | Submit phone for quiz | `wp_create_nonce('zdp_quiz_submit_phone')` | Not specified |

**Details**: Nonce verification and capability checks not fully verified. Dashboard should verify each AJAX action's security implementation before integration.

#### 2.3 Quiz Data Access APIs (From conversation historical memory)

| API | Purpose | Authorization |
|-----|---------|--------------|
| `psy_get_pending_quizzes_for_phone` | Get pending quizzes by phone | Requires authorization |
| `psy_get_client_name_for_phone` | Lookup client name by phone | Requires authorization |
| `psy_get_completed_results_for_phone` | Get completed quiz results by phone | Requires authorization |
| `psy_get_assessment_scores_for_phone` | Get assessment scores by phone | Requires authorization |

**Details**: Full function signatures, parameter schemas, and authorization requirements not fully verified in read-only audit. Dashboard should inspect actual function implementations.

---

### 3. Case File API Contracts (pivand-amn-case-file 1.16.1)

#### 3.1 Case Access Methods (With Authorization Requirements)

| Method | Signature | Authorization Required | Verified Behavior |
|--------|-----------|----------------------|-------------------|
| `list_all($limit)` | `list_all(int $limit = 200): array` | ❌ NONE in method | Returns ALL cases in system - **CRITICAL: Dashboard MUST NOT use** |
| `list_for_therapist($therapist_id, $limit)` | `list_for_therapist(int $therapist_id, int $limit = 200): array` | ✅ Central scope SQL | Uses `case_scope_sql($therapist_id, '', false)` - correct when used with `get_current_user_id()` |
| `get($case_id)` | `get(int $case_id): ?object` | ❌ NONE in method | Caller MUST check `current_user_can_access_case($case)` first |
| `get_raw($case_id)` | `get_raw(int $case_id): ?object` | ❌ NONE (private) | Internal method - dashboard must NOT call directly |
| `get_by_phone($phone)` | `get_by_phone(string $phone): ?object` | ❌ NONE | Any phone lookup returns case - **IDOR HIGH RISK** |

**`current_user_can_access_case(object $case)`**: 
```php
public static function current_user_can_access_case(object $case): bool
{
    return self::user_can_access_case_row(
        get_current_user_id(),
        (int) ($case->assigned_therapist ?? 0),
        (int) ($case->created_by ?? 0)
    );
}
```
- **REQUIRED** wrapper before `get()`, `get_notes()`, etc.
- Checks `assigned_therapist === $user_id` OR (`assigned_therapist === 0 AND created_by === $user_id`)
- Admin with `PAS_CF_CAP_ALL` always returns true

**`user_can_access_case_row(int $user_id, int $assigned_therapist, int $created_by)`**:
```php
public static function user_can_access_case_row(int $user_id, int $assigned_therapist, int $created_by, ?bool $view_all = null): bool
```
- **Single source of truth** for access logic
- Three-way branch: `view_all` → `assigned_therapist match` → `created_by match for unassigned`

**`case_scope_sql(int $user_id, string $alias = 'c', bool $view_all = false)`**:
```php
public static function case_scope_sql(int $user_id, string $alias = 'c', bool $view_all = false): array
```
- Generates equivalent SQL: `assigned_therapist = %d OR (assigned_therapist = 0 AND created_by = %d)`
- Used by `list_for_therapist()` and report modules

#### 3.2 Note CRUD Methods (With Authorization Requirements)

| Method | Signature | Authorization in Method | Dashboard Requirement |
|--------|-----------|----------------------|----------------------|
| `add_note($case_id, $date, $content, $session_number, $meta)` | `add_note(int $case_id, string $session_date, string $content, int $session_number = 0, array $meta = []): int` | ❌ NONE in method | Dashboard must verify `current_user_can_access_case($case_id)` BEFORE calling |
| `update_note($note_id, $case_id, $date, $content, $session_number, $meta)` | `update_note(int $note_id, int $case_id, string $session_date, string $content, ?int $session_number = null, ?array $meta = null): bool` | ❌ Only note-case ID match | Dashboard must verify ownership + capabilities BEFORE calling |
| `delete_note($note_id, $case_id)` | `delete_note(int $note_id, int $case_id): bool` | ❌ Only note-case ID match | Dashboard must verify ownership + capabilities BEFORE calling |
| `get_note_history($note_id)` | `get_note_history(int $note_id): array` | ❌ NONE in method | Dashboard must verify case ownership BEFORE calling |
| `restore_note_version($history_id, $case_id)` | `restore_note_version(int $history_id, int $case_id): bool` | ❌ Only case_id match between history and parameter | Dashboard must verify case ownership BEFORE calling |
| `get_deleted_notes($case_id)` | `get_deleted_notes(int $case_id): array` | ❌ NONE in method | Dashboard must verify case ownership BEFORE calling |
| `get_notes($case_id)` | `get_notes(int $case_id): array` | ❌ NONE in method | Dashboard must verify case ownership BEFORE calling |

**Critical Pattern**: ALL case file CRUD methods have minimal in-method authorization. Dashboard MUST implement wrapper checks:

```php
// BEFORE calling any case file method:
if (!Pas_CF_Security::current_user_can_access_case($case)) {
    // Access denied - show error or redirect
    wp_die('دسترسی غیرمجاز.');
}

// OR for list operations:
$cases = Pas_CF_Case_File::list_for_therapist(get_current_user_id());
```

#### 3.3 Partner/Couple Access APIs

| Method | Signature | `can_view_partner` Enforcement | Dashboard Requirement |
|--------|-----------|-------------------------------|----------------------|
| `get_couple_sessions($case, $limit)` | `get_couple_sessions(object $case, int $limit = 60): array` | ✅ YES - via `current_user_can_access_case($partner)` | Returned in output: `'can_view_partner' => $can_view` |
| `get_partner($case)` | `get_partner(object $case): ?object` | ❌ NO | No authorization check - dashboard must check first |

**`get_couple_sessions()` Output Structure**:
```php
[
    'partner' => ?object, // Partner case object or null
    'can_view_partner' => bool, // TRUE only if user can access partner case
    'sessions' => array, // Sessions array (empty if !can_view_partner)
]
```

**Critical**: When `can_view_partner` is FALSE, `sessions` array is empty and partner case is NOT included in queries. Dashboard MUST respect this flag.

#### 3.4 Encryption API Methods

| Method | Signature | Behavior | Dashboard Requirement |
|--------|-----------|----------|----------------------|
| `Pas_CF_Crypto::is_configured()` | `is_configured(): bool` | Returns true if `Pas_Core_Crypto` class exists AND configured | **MUST call before displaying encrypted data** |
| `Pas_CF_Crypto::encrypt(string $plaintext)` | `encrypt(string $plaintext): string` | Throws RuntimeException if Pas_Core_Crypto not available | Use only when `is_configured()` returns true |
| `Pas_CF_Crypto::decrypt_or_plain(string $value)` | `decrypt_or_plain(string $value): string` | Returns decrypted OR legacy plaintext based on base64 validity | Handle BOTH outcomes in dashboard |
| `Pas_CF_Crypto::decrypt(string $encoded)` | `decrypt(string $encoded): string` | Depends on Pas_Core_Crypto | Only call when `is_configured()` is true |

**Dashboard Flow** for displaying encrypted fields:
```php
if (!Pas_CF_Crypto::is_configured()) {
    // Show compliance warning - key missing
    echo '<div class="notice notice-error">' .
         '<p><strong>رمزنگاری تنظیم نشده است.</strong> یادداشت‌های قبلی ممکن است رمزگشایی نباشد。</div>';
} else {
    // Safe to decrypt
    $decrypted = Pas_CF_Crypto::decrypt_or_plain($encrypted_value);
    echo esc_html($decrypted);
}
```

#### 3.5 Admin-Post API Actions (from class-admin.php)

All actions require `PAS_CF_CAP` capability and nonce verification via `check_admin_referer('pas_cf_<action>')`.

| Action | Hook | Nonce Name | Capability | `require_access()` | `require_case_access($case)` |
|--------|------|------------|-----------|-------------------|-----------------------------|
| `pas_cf_save_case` | `admin_post_pas_cf_save_case` | `pas_cf_save_case` | `PAS_CF_CAP` | ✅ `handle_save_case` | ✅ `render_edit_page` |
| `pas_cf_add_note` | `admin_post_pas_cf_add_note` | `pas_cf_add_note` | `PAS_CF_CAP` | ✅ `handle_add_note` | ❌ Not in method itself |
| `pas_cf_edit_note` | `admin_post_pas_cf_edit_note` | `pas_cf_edit_note` | `PAS_CF_CAP` | ✅ `handle_edit_note` | ❌ Not in method itself |
| `pas_cf_delete_note` | `admin_post_pas_cf_delete_note` | `pas_cf_delete_note` | `PAS_CF_CAP` | ✅ `handle_delete_note` | ❌ Not in method itself |
| `pas_cf_restore_note` | `admin_post_pas_cf_restore_note` | `pas_cf_restore_note` | `PAS_CF_CAP` | ✅ `handle_restore_note` | ✅ Checks case_id match |
| `pas_cf_export_csv` | `admin_post_pas_cf_export_csv` | `pas_cf_export_csv` | `PAS_CF_CAP` | Ⓤ (not shown) | Ⓤ (not verified) |
| `pas_cf_send_reminder` | `admin_post_pas_cf_send_reminder` | `pas_cf_send_reminder` | `PAS_CF_CAP` | ✅ `handle_send_reminder` | ✅ |
| `pas_cf_apply_intake_map` | `admin_post_pas_cf_apply_intake_map` | `pas_cf_apply_intake_map` | `PAS_CF_CAP` | ✅ `handle_apply_intake_map` | ✅ |
| `pas_cf_print_case` | `admin_post_pas_cf_print_case` | `pas_cf_print_case` | `PAS_CF_CAP` | Ⓤ (not shown) | Ⓤ (not verified) |

**Nonce Verification**: `check_admin_referer('pas_cf_<action>')` in all handlers.

**Critical Gap**: `require_case_access($case)` only called in 3 of 10 handlers. Note add/edit/delete methods themselves don't verify user capabilities - only check note-case ID match.

#### 3.6 Case Scope and Filter APIs

**`Pas_CF_Case_File::list_for_therapist(int $therapist_id, int $limit = 200)`**:
- **DASHBOARD MUST USE**: `list_for_therapist(get_current_user_id())`
- Generates SQL: `assigned_therapist = %d OR (assigned_therapist = 0 AND created_by = %d)`
- Never use `list_all()` for therapist-facing views

**`Pas_CF_Security::current_user_case_scope(string $alias = 'c')`**:
```php
public static function current_user_case_scope(string $alias = 'c'): array
{
    return self::case_scope_sql(
        get_current_user_id(),
        $alias,
        self::current_user_can_view_all()
    );
}
```
- Returns `[condition_sql, $params]` for direct SQL use
- Dashboard can use for custom queries

**`Pas_CF_Security::current_user_can_view_all()`**:
```php
public static function current_user_can_view_all(): bool
{
    return current_user_can(PAS_CF_CAP_ALL);
}
```
- Checks for admin override capability
- Returns true only for admins with `PAS_CF_CAP_ALL`

---

### 4. Client Portal API Contracts (Referenced - Partial)

| API | Purpose | Status |
|-----|---------|--------|
| `find_linked_wp_user(string $phone)` | Lookup WP user by normalized phone | Referenced only |
| `preload_linked_wp_users(array $phones)` | Batch load WP users by phones | Referenced only |
| `zdp_send_otp` AJAX | Send OTP for portal login | Referenced only |
| `zdp_verify_otp` AJAX | Verify OTP code | Referenced only |
| `zdp_create_booking` AJAX | Create booking appointment | Referenced only |
| `zdp_upload_receipt` AJAX | Upload appointment receipt | Referenced only |
| `pas_core_lookup_client_name` | Core shared client name lookup | Referenced only |

**Details**: Full API specifications not verified in this read-only audit. Dashboard should inspect actual portal plugin source if integration needed.

---

### 5. SMS Bridge API Contracts (pivand-amn-sms-bridge 1.1.7 - Referenced)

| API | Purpose | Status |
|-----|---------|--------|
| `pas_send_sms` filter | Send SMS via pattern-based filter | Referenced only |
| `has_filter('pas_send_sms')` | Check if SMS bridge is active | Must check before enabling SMS |
| `pas_cf_reminder_sms_pattern` option | SMS pattern configuration | Read from option |

**Critical Dashboard Check**:
```php
if (!has_filter('pas_send_sms')) {
    // SMS bridge not active - disable reminder features
    echo '<div class="notice notice-error">' .
         '<p>ارسال پیامک فعال نیست - افزونه‌یyped-amn-sms-bridge فعال نیست یا فیلتر pas_send_sms ثبت نشده است.</p>' .
         '</div>';
}
```

**SMS Pattern** (from case file):
```php
$pattern = (string) apply_filters(
    'pas_cf_reminder_sms_pattern',
    (string) get_option('pas_cf_reminder_sms_pattern', '')
);

if ($pattern === '' || !has_filter('pas_send_sms')) {
    $result = 'sms_not_configured';
} else {
    $sent = apply_filters('pas_send_sms', null, $case->phone, $pattern, [
        'NAME' => $name,
        'DATE' => $jalali,
    ]);
    $result = $sent === false ? 'failed' : 'sent';
}
```

---

### 6. Integration Contract Summary

**All dashboard API calls must follow these principles**:

1. **Never call case file `list_all()`** - Use `list_for_therapist(get_current_user_id())`
2. **Always wrap `get($case_id)`** with `current_user_can_access_case($case)` check
3. **Always check `Pas_CF_Crypto::is_configured()`** before displaying encrypted data
4. **Respect `can_view_partner`** from `get_couple_sessions()` output
5. **No direct `wpdb->pas_cf_cases` queries** - Use provided APIs only
6. **All admin-post actions require nonce + `PAS_CF_CAP`** - Verify both
7. **Note CRUD requires dashboard-level authorization** - Methods don't check user capabilities
8. **SMS bridge must check `has_filter('pas_send_sms')`** before enabling SMS features
9. **Client lookup via `pas_get_client` filter** requires `PAS_CF_CAP` or `manage_options`
10. **No direct database queries** across plugin table boundaries

**Violating any of these contracts** will result in security vulnerabilities (IDOR, unauthorized data access, encryption failures, etc.).

---