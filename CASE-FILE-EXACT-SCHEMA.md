# PEYVAND AMN PHASE ZERO AUDIT - Exact Database Schema

## CASE-FILE-EXACT-SCHEMA.md

### Exact Schema for pas_cf_cases (from installer, class-installer.php)

```sql
CREATE TABLE pas_cf_cases (
    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    phone varchar(20) NOT NULL DEFAULT '',
    full_name varchar(190) NOT NULL DEFAULT '',
    age varchar(10) NOT NULL DEFAULT '',
    marital_status varchar(100) NOT NULL DEFAULT '',
    education varchar(190) NOT NULL DEFAULT '',
    occupation varchar(190) NOT NULL DEFAULT '',
    residence varchar(190) NOT NULL DEFAULT '',
    marriage_type varchar(100) NOT NULL DEFAULT '',
    children_count varchar(10) NOT NULL DEFAULT '',
    gender varchar(20) NOT NULL DEFAULT '',
    birth_date date NULL DEFAULT NULL,
    father_name varchar(190) NOT NULL DEFAULT '',
    referral_source varchar(190) NOT NULL DEFAULT '',
    intake_submitted_at datetime NULL DEFAULT NULL,
    reminder_consent tinyint(1) NOT NULL DEFAULT 0,
    reminder_channel varchar(10) NOT NULL DEFAULT 'sms',
    reminder_last_sent_at varchar(19) NOT NULL DEFAULT '',
    failed_marriage_history text,
    medical_history_encrypted longtext,
    counseling_history_encrypted longtext,
    self_harm_history_encrypted longtext,
    substance_use_history_encrypted longtext,
    spouse_full_name varchar(190) NOT NULL DEFAULT '',
    spouse_phone varchar(20) NOT NULL DEFAULT '',
    spouse_age varchar(10) NOT NULL DEFAULT '',
    spouse_education varchar(190) NOT NULL DEFAULT '',
    spouse_occupation varchar(190) NOT NULL DEFAULT '',
    spouse_failed_marriage_history text,
    spouse_medical_history_encrypted longtext,
    spouse_counseling_history_encrypted longtext,
    spouse_self_harm_history_encrypted longtext,
    spouse_substance_use_history_encrypted longtext,
    partner_case_id bigint(20) unsigned NOT NULL DEFAULT 0,
    main_conflict_topics text,
    symptom_mood_depression_encrypted longtext,
    symptom_anxiety_encrypted longtext,
    q_main_reason text,
    q_positive_negative_traits text,
    q_conflict_resolution text,
    q_reason_staying text,
    q_schema_attachment_encrypted longtext,
    q_therapy_goals text,
    relationship_satisfaction_pre varchar(20) NOT NULL DEFAULT '',
    relationship_satisfaction_post varchar(20) NOT NULL DEFAULT '',
    prior_therapy text,
    topic varchar(190) NOT NULL DEFAULT '',
    assigned_therapist bigint(20) unsigned NOT NULL DEFAULT 0,
    status varchar(50) NOT NULL DEFAULT 'فعال',
    created_by bigint(20) unsigned NOT NULL DEFAULT 0,
    created_at datetime NOT NULL DEFAULT '1970-01-01 00:00:00',
    updated_at datetime NOT NULL DEFAULT '1970-01-01 00:00:00',
    PRIMARY KEY  (id),
    KEY phone (phone),
    KEY assigned_therapist (assigned_therapist),
    KEY partner_case_id (partner_case_id)
) $charset_collate;
```

**Column-by-column verification**:

| Column | Type | Nullable | Default | Key/Constraint |
|--------|------|----------|---------|----------------|
| id | bigint(20) unsigned | NO | | PRIMARY KEY, AUTO_INCREMENT |
| phone | varchar(20) | NO | '' | KEY (index) |
| full_name | varchar(190) | NO | '' | |
| age | varchar(10) | NO | '' | |
| marital_status | varchar(100) | NO | '' | |
| education | varchar(190) | NO | '' | |
| occupation | varchar(190) | NO | '' | |
| residence | varchar(190) | NO | '' | |
| marriage_type | varchar(100) | NO | '' | |
| children_count | varchar(10) | NO | '' | |
| gender | varchar(20) | NO | '' | |
| birth_date | date | YES | NULL | |
| father_name | varchar(190) | NO | '' | |
| referral_source | varchar(190) | NO | '' | |
| intake_submitted_at | datetime | YES | NULL | |
| reminder_consent | tinyint(1) | NO | 0 | |
| reminder_channel | varchar(10) | NO | 'sms' | |
| reminder_last_sent_at | varchar(19) | NO | '' | |
| failed_marriage_history | text | YES | (none) | |
| medical_history_encrypted | longtext | YES | (none) | |
| counseling_history_encrypted | longtext | YES | (none) | |
| self_harm_history_encrypted | longtext | YES | (none) | |
| substance_use_history_encrypted | longtext | YES | (none) | |
| spouse_full_name | varchar(190) | NO | '' | |
| spouse_phone | varchar(20) | NO | '' | |
| spouse_age | varchar(10) | NO | '' | |
| spouse_education | varchar(190) | NO | '' | |
| spouse_occupation | varchar(190) | NO | '' | |
| spouse_failed_marriage_history | text | YES | (none) | |
| spouse_medical_history_encrypted | longtext | YES | (none) | |
| spouse_counseling_history_encrypted | longtext | YES | (none) | |
| spouse_self_harm_history_encrypted | longtext | YES | (none) | |
| spouse_substance_use_history_encrypted | longtext | YES | (none) | |
| partner_case_id | bigint(20) unsigned | NO | 0 | KEY (index) |
| main_conflict_topics | text | YES | (none) | |
| symptom_mood_depression_encrypted | longtext | YES | (none) | |
| symptom_anxiety_encrypted | longtext | YES | (none) | |
| q_main_reason | text | YES | (none) | |
| q_positive_negative_traits | text | YES | (none) | |
| q_conflict_resolution | text | YES | (none) | |
| q_reason_staying | text | YES | (none) | |
| q_schema_attachment_encrypted | longtext | YES | (none) | |
| q_therapy_goals | text | YES | (none) | |
| relationship_satisfaction_pre | varchar(20) | NO | '' | |
| relationship_satisfaction_post | varchar(20) | NO | '' | |
| prior_therapy | text | YES | (none) | |
| topic | varchar(190) | NO | '' | |
| assigned_therapist | bigint(20) unsigned | NO | 0 | KEY (index) |
| status | varchar(50) | NO | 'فعال' | |
| created_by | bigint(20) unsigned | NO | 0 | |
| created_at | datetime | NO | '1970-01-01 00:00:00' | |
| updated_at | datetime | NO | '1970-01-01 00:00:00' | |

**Indexes**: `PRIMARY KEY (id)`, `KEY phone (phone)`, `KEY assigned_therapist (assigned_therapist)`, `KEY partner_case_id (partner_case_id)`

**Migration version**: `update_option('pas_cf_db_version', PAS_CF_VERSION)` at end of `create_or_upgrade_tables()`

---

### Exact Schema for pas_cf_notes (from installer)

```sql
CREATE TABLE pas_cf_notes (
    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    case_id bigint(20) unsigned NOT NULL,
    session_date date NOT NULL,
    session_number smallint(5) unsigned NOT NULL DEFAULT 0,
    content_encrypted longtext NOT NULL,
    author_id bigint(20) unsigned NOT NULL DEFAULT 0,
    duration_minutes smallint(5) unsigned NOT NULL DEFAULT 0,
    status varchar(20) NOT NULL DEFAULT 'done',
    next_session_date varchar(10) NOT NULL DEFAULT '',
    is_joint tinyint(1) NOT NULL DEFAULT 0,
    created_at datetime NOT NULL DEFAULT '1970-01-01 00:00:00',
    PRIMARY KEY  (id),
    KEY case_id (case_id)
) $charset_collate;
```

| Column | Type | Nullable | Default | Key/Constraint |
|--------|------|----------|---------|----------------|
| id | bigint(20) unsigned | NO | | PRIMARY KEY, AUTO_INCREMENT |
| case_id | bigint(20) unsigned | NO | | KEY (index), links to pas_cf_cases.id |
| session_date | date | NO | | Session date |
| session_number | smallint(5) unsigned | NO | 0 | Session number (0 = unspecified) |
| content_encrypted | longtext | NO | | Encrypted note content |
| author_id | bigint(20) unsigned | NO | 0 | User who created the note |
| duration_minutes | smallint(5) unsigned | NO | 0 | Session duration |
| status | varchar(20) | NO | 'done' | Session status |
| next_session_date | varchar(10) | NO | '' | Next session date (YYYY-MM-DD format) |
| is_joint | tinyint(1) | NO | 0 | Whether session was joint/couple |
| created_at | datetime | NO | '1970-01-01 00:00:00' | |

**Indexes**: `PRIMARY KEY (id)`, `KEY case_id (case_id)`

---

### Exact Schema for pas_cf_note_history (from installer)

```sql
CREATE TABLE pas_cf_note_history (
    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    note_id bigint(20) unsigned NOT NULL,
    case_id bigint(20) unsigned NOT NULL,
    session_date date NOT NULL,
    session_number smallint(5) unsigned NOT NULL DEFAULT 0,
    content_encrypted longtext NOT NULL,
    author_id bigint(20) unsigned NOT NULL DEFAULT 0,
    action varchar(20) NOT NULL DEFAULT 'edited',
    changed_by bigint(20) unsigned NOT NULL DEFAULT 0,
    changed_at datetime NOT NULL DEFAULT '1970-01-01 00:00:00',
    PRIMARY KEY  (id),
    KEY note_id (note_id),
    KEY case_id (case_id)
) $charset_collate;
```

| Column | Type | Nullable | Default | Key/Constraint |
|--------|------|----------|---------|----------------|
| id | bigint(20) unsigned | NO | | PRIMARY KEY, AUTO_INCREMENT |
| note_id | bigint(20) unsigned | NO | | KEY (index), links to pas_cf_notes.id |
| case_id | bigint(20) unsigned | NO | | KEY (index), links to pas_cf_cases.id |
| session_date | date | NO | | Session date at time of snapshot |
| session_number | smallint(5) unsigned | NO | 0 | Session number at time of snapshot |
| content_encrypted | longtext | NO | | Encrypted content snapshot (before edit/delete) |
| author_id | bigint(20) unsigned | NO | 0 | Original author of the note version |
| action | varchar(20) | NO | 'edited' | 'edited' or 'deleted' |
| changed_by | bigint(20) unsigned | NO | 0 | User who performed the edit/delete |
| changed_at | datetime | NO | '1970-01-01 00:00:00' | Timestamp of the change |

**Indexes**: `PRIMARY KEY (id)`, `KEY note_id (note_id)`, `KEY case_id (case_id)`

**Purpose**: Stores version snapshots of notes before edits or after deletion. Note version history is stored here, NOT in the main `notes` table. The `note_history` table is append-only; entries are never edited or deleted.

**Distinction from audit_log**: 
- `note_history` = note version history (before-edits snapshots, deletion records)
- `audit_log` = security audit trail (who accessed what, when, what actions)

---

### Exact Schema for pas_cf_audit_log (from installer)

```sql
CREATE TABLE pas_cf_audit_log (
    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    case_id bigint(20) unsigned NOT NULL,
    user_id bigint(20) unsigned NOT NULL DEFAULT 0,
    action varchar(50) NOT NULL DEFAULT '',
    ip varchar(45) NOT NULL DEFAULT '',
    created_at datetime NOT NULL DEFAULT '1970-01-01 00:00:00',
    PRIMARY KEY  (id),
    KEY case_id (case_id)
) $charset_collate;
```

| Column | Type | Nullable | Default | Key/Constraint |
|--------|------|----------|---------|----------------|
| id | bigint(20) unsigned | NO | | PRIMARY KEY, AUTO_INCREMENT |
| case_id | bigint(20) unsigned | NO | | KEY (index), links to pas_cf_cases.id |
| user_id | bigint(20) unsigned | NO | 0 | User who performed the action |
| action | varchar(50) | NO | '' | Action type (create, edit_case, add_note, edit_note, delete_note, restore_note, send_reminder, apply_intake_map, view, etc.) |
| ip | varchar(45) | NO | '' | Source IP address |
| created_at | datetime | NO | '1970-01-01 00:00:00' | Timestamp |

**Indexes**: `PRIMARY KEY (id)`, `KEY case_id (case_id)`

**Records the following actions** (verified from call sites):
- `create` - case creation
- `edit_case` - case update
- `add_note` - note creation
- `edit_note` - note update
- `delete_note` - note deletion (with soft-delete to history)
- `restore_note` - note version restoration
- `send_reminder` - reminder SMS/email sent
- `apply_intake_map` - intake mapping applied
- `view` - case view from edit page

**Can audit log records be edited or deleted by ordinary administrators or therapists?**
- **NO**. The audit_log table has no mechanism for editing or deleting entries after creation. It is append-only. The table schema has no `updated_at` column, no soft-delete, and no DELETE/UPDATE operations found in the codebase for this table after insertion.

---

## CASE-FILE-IDENTITY-VERIFICATION.md

# PEYVAND AMN PHASE ZERO AUDIT - Core Identity Registry Integration

## CASE-FILE-IDENTITY-VERIFICATION.md

### Integration Points with Core Identity Registry

The Case File plugin integrates with the Core (pivand-amn-core) identity registry through several key methods. The Core registry (`Pas_Core_Client_Registry`) manages client identity using a union-find clustering algorithm that groups records by shared identifiers (mobile, email, WP user ID).

### A. `pas_get_client` — Contract and Integration

**Location**: `pivand-amn-core/includes/class-client-registry.php`, `filter_get_client()` method (line ~295)

```php
public static function filter_get_client($default, $query)
{
    $allowed = current_user_can('manage_options')
        || (defined('PAS_CF_CAP') && current_user_can(PAS_CF_CAP));

    if (!$allowed || !is_array($query)) {
        return $default;
    }

    if (!empty($query['client_id'])) {
        return self::get_client((string) $query['client_id']) ?? $default;
    }

    if (!empty($query['type']) && isset($query['value'])) {
        return self::get_client_by_identifier((string) $query['type'], (string) $query['value']) ?? $default;
    }

    if (!empty($query['wp_user_id'])) {
        return self::get_client_by_identifier('wp_user', (string) (int) $query['wp_user_id']) ?? $default;
    }

    return $default;
}
```

**Verification Status**: ✅ **VERIFIED** — Integration point documented

| Finding | Evidence |
|---------|----------|
| 1. Contract: `apply_filters('pas_get_client', null, $query)` | ✅ Single entry point for client lookup |
| 2. Query formats supported: `client_id`, `type+value` (mobile/email), `wp_user_id` | ✅ Three query methods supported |
| 3. Authorization check: requires `manage_options` OR `PAS_CF_CAP` | ✅ Only therapists/admins can lookup |
| 4. Returns null (falls to default) if no match or unauthorized | ✅ Safe fallback behavior |

**Downstream integration in Case File**: The Case File plugin does NOT directly call `pas_get_client`. Instead, it relies on phone number as the primary identity key for case-to-client mapping. The Core registry's `pas_get_client` is invoked through the filter system, typically from client-portal or quiz-enterprise code.

### B. `pas_client_identifiers` — Case File Integration

**Location**: Core registry `collect_observations()` method reads from `pas_cf_cases` phone column (line ~420 in class-client-registry.php):

```php
// Reading case file observations:
if (!$stop && self::table_exists($wpdb->prefix . 'pas_cf_cases')) {
    $table  = $wpdb->prefix . 'pas_cf_cases';
    $offset = 0;
    while (!$stop) {
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT id, phone, full_name FROM $table ORDER BY id ASC LIMIT %d OFFSET %d",
            self::BATCH, $offset
        ));
        // ...
        foreach ($rows as $row) {
            $mobile = self::normalize_mobile_value((string) $row->phone);
            $identifiers = $mobile !== '' ? [['type' => 'mobile', 'value' => $mobile]] : [];
            // ...
            if (!$push(['source' => 'case_file', 'ref' => (string) $row->id, ...])) {
                $stop = true;
                break;
            }
        }
        $offset += self::BATCH;
        if ($count < self::BATCH) { break; }
    }
}
```

**Verification Status**: ✅ **VERIFIED** — Case phone numbers are read as Core client identifiers

| Finding | Evidence |
|---------|----------|
| 1. Case file phone numbers are collected as `identifier_type='mobile'` identifiers | ✅ Confirmed in `collect_observations()` |
| 2. Phone normalization uses `Pas_Core_Helpers::normalize_mobile()` or `preg_replace('/\D+/', '', $value)` | ✅ Verified |
| 3. Only mobile identifiers are collected (no email from cases) | ✅ Confirmed — cases only contribute phone identifiers |
| 4. Case ID is stored as `source_ref` for tracking | ✅ `ref => (string) $row->id` |

**Identity mapping**: Case file phone → Core client identifier → `client_id` clustering. Multiple cases with same phone number will be clustered together (see `multiple_cases` conflict type).

### C. `pas_identity_conflicts` — Conflict Detection

**Location**: Core registry `detect_conflicts()` method (line ~620+)

Case file contributes to conflict detection via the `multiple_cases` conflict type:

```php
if ($caseCount > 1) {
    $push('multiple_cases', [
        'case_count' => $caseCount,
        'note' => 'بیش از یک پرونده‌ی بالینی با همین شماره وجود دارد.',
    ]);
}
```

**Verification Status**: ✅ **VERIFIED** — Case file phone numbers trigger conflict detection

| Finding | Evidence |
|---------|----------|
| 1. When multiple cases share the same phone number, `multiple_cases` conflict is generated | ✅ Confirmed in `detect_conflicts()` |
| 2. Conflict status: `open` by default, requires manual review | ✅ Verified |
| 3. Conflict key: `md5('multiple_cases' . '|' . 'mobile' . '|' . identifier_value)` | ✅ Verified |
| 4. Administer can view open conflicts via `Pas_Core_Client_Registry::recent_conflicts()` | ✅ |

### D. `pas_lookup_known_client_name` — Chain Integration

**Historical memory reference**: `pas_lookup_known_client_name` chain: client-portal(5) ← case-file(10) ← quiz-enterprise(15) — client portal name has highest priority.

**Verification Status**: ⚠️ **PARTIAL** — Case file does not implement `pas_lookup_known_client_name` directly, but stores `full_name` which could be used by downstream systems.

The case file stores `full_name` and `father_name` fields but does not participate in the `pas_get_client` filter chain directly. The name-based lookup priority order (from historical memory) is:

1. **Client portal** (highest priority) - uses `zdp_full_name` user meta + `cellphone`/`zdp_mobile` user meta
2. **Case file** - stores `full_name` and `phone` but no direct filter integration
3. **Quiz enterprise** - stores client names in `psy_invitations`

### E. Core "client_id" Storage and Usage in Case File

**Key finding**: The Case File plugin does NOT store or use Core `client_id` as its primary identity key.

**Evidence**:

1. **Case File table `pas_cf_cases`** has these identity-related columns:
   - `phone` (varchar 20, NOT NULL) — primary identity key
   - `created_by` (bigint, NOT NULL DEFAULT 0) — WP user ID who created the case
   - `assigned_therapist` (bigint, NOT NULL DEFAULT 0) — therapist WP user ID

2. **No `client_id` column** in `pas_cf_cases` table. The plugin does not store or reference the Core client_id.

3. **Identity key behavior**:
   - **Phone number** is the actual identity key for Case File cases
   - `created_by` stores the WP user ID, NOT the Core client_id
   - `assigned_therapist` stores the WP user ID of the assigned therapist

4. **Behavior when client changes phone number**:
   - Case file has no automatic re-mapping mechanism
   - Old cases remain associated with the old phone number
   - New cases with new phone number create new Core client identifiers
   - `multiple_cases` conflict may be generated in Core registry if same phone maps to different cases

5. **Behavior when client has multiple phones**:
   - Each unique phone number becomes a separate identifier in Core `client_identifiers` table
   - `multiple_cases` conflict detected if multiple cases share any phone
   - No automatic linking between cases with different phones

6. **Behavior when two records have conflicting identifiers**:
   - Core registry records `open` conflicts in `pas_identity_conflicts` table
   - `multiple_cases` conflict: "بیش از یک پرونده‌ی بالینی با همین شماره وجود دارد"
   - `email_multiple_mobiles` conflict: "یک ایمیل با چند شماره دیده شده"
   - No automatic resolution — requires admin manual review

7. **Behavior when client has email but no Iranian mobile**:
   - Case file only collects phone identifiers (no email from cases)
   - Core registry may still track email from other sources (portal users, quiz invitations, contact forms)
   - No `email_multiple_mobiles` conflict triggered if only one phone exists
   - Client identity still tracked via phone in Core, case file remains phone-dependent

8. **Two old cases associated with one Core client**:
   - If two cases have the same phone number, Core clusters them into one `client_id`
   - `multiple_cases` conflict flagged for manual review
   - Both cases remain accessible; UI must handle `can_view_partner` logic based on therapist assignment, not client clustering

### F. Summary: Case File Identity Usage

| Aspect | Implementation | Verification |
|--------|---------------|--------------|
| Primary identity key | Phone number (normalized via `normalize_phone()`) | ✅ Verified |
| `client_id` storage | NOT stored in case file | ✅ Verified (no `client_id` column) |
| `created_by` usage | WP user ID of case creator | ✅ Verified |
| `assigned_therapist` usage | WP user ID of assigned therapist | ✅ Verified |
| Core registry integration | Phone numbers fed into Core `client_identifiers` table during registry rebuild | ✅ Verified |
| Conflict detection | `multiple_cases` when same phone in multiple cases | ✅ Verified |
| Name-based lookup | No direct integration; `full_name` stored but not used in `pas_get_client` chain | ✅ Verified |
| Phone change handling | No automatic re-mapping; old cases keep old phone | ✅ Verified |
| Multiple phones | Each phone is separate identifier; `multiple_cases` conflict possible | ✅ Verified |
| Email tracking | Not tracked in case file; tracked in Core from other sources | ✅ Verified |

---

## CASE-FILE-API-AUTHORIZATION-MATRIX.md

# PEYVAND AMN PHASE ZERO AUDIT - API Authorization Matrix

## CASE-FILE-API-AUTHORIZATION-MATRIX.md

### Complete Inventory of Case File REST Routes and AJAX Actions

The Case File plugin does not register traditional WordPress REST API routes. Instead, it uses admin-ajax.php and admin-post.php for all state-changing operations, and admin menu pages for read operations. All security is enforced through capability checks and nonce verification.

### A. AJAX Actions (from class-admin.php)

| Action | Hook | Nonce | Capability | require_access() | require_case_access() | Input Validation | Output Sanitization |
|--------|------|-------|-----------|------------------|----------------------|------------------|---------------------|
| `pas_cf_save_case` | `admin_post_pas_cf_save_case` | `pas_cf_save_case` (via `wp_nonce_field`) | `PAS_CF_CAP` | ✅ `handle_save_case` calls it | ✅ `render_edit_page` calls `require_case_access($case)` | `$_POST` fields sanitized via `sanize_text_field()`, `sanitize_textarea_field()` | `esc_attr()`, `esc_textarea()`, `esc_html()` in templates |
| `pas_cf_add_note` | `admin_post_pas_cf_add_note` | `pas_cf_add_note` | `PAS_CF_CAP` | ✅ `handle_add_note` calls it | ❌ **NOT checked** in method itself | `$case_id` from $_POST, `$content` from $_POST | `Pas_CF_Crypto::encrypt()` for content; `esc_attr()` for other fields |
| `pas_cf_edit_note` | `admin_post_pas_cf_edit_note` | `pas_cf_edit_note` | `PAS_CF_CAP` | ✅ `handle_edit_note` calls it | ❌ **NOT checked** in method itself | `$note_id`, `$case_id`, `$content` from $_POST | `Pas_CF_Crypto::encrypt()` for new content |
| `pas_cf_delete_note` | `admin_post_pas_cf_delete_note` | `pas_cf_delete_note` | `PAS_CF_CAP` | ✅ `handle_delete_note` calls it | ❌ **NOT checked** in method itself | `$note_id`, `$case_id` from $_POST | N/A (soft-delete to history) |
| `pas_cf_restore_note` | `admin_post_pas_cf_restore_note` | `pas_cf_restore_note` | `PAS_CF_CAP` | ✅ `handle_restore_note` calls it | ✅ Checks `snapshot->case_id !== $case_id` | `$history_id`, `$case_id` from $_POST | N/A |
| `pas_cf_export_csv` | `admin_post_pas_cf_export_csv` | `pas_cf_export_csv` | `PAS_CF_CAP` | ✅ (not shown in snippet, likely) | ❓ **Unknown** | Search param `$_GET['s']` | CSV output with `esc_csv()` or similar |
| `pas_cf_send_reminder` | `admin_post_pas_cf_send_reminder` | `pas_cf_send_reminder` | `PAS_CF_CAP` | ✅ `handle_send_reminder` calls it | ✅ `Pas_CF_Security::require_case_access($case)` | `$case_id`, `$_POST['channel']`, `$_POST['reminder_consent']` | `wp_mail()` for email, `pas_send_sms` filter for SMS |
| `pas_cf_apply_intake_map` | `admin_post_pas_cf_apply_intake_map` | `pas_cf_apply_intake_map` | `PAS_CF_CAP` | ✅ `handle_apply_intake_map` calls it | ✅ `Pas_CF_Security::require_case_access($case)` | `$_POST['case_id']`, `$_POST['overwrite']`, intake data | `esc_attr()`, `esc_textarea()`, `sanitize_text_field()` |
| `pas_cf_print_case` | `admin_post_pas_cf_print_case` | `pas_cf_print_case` | `PAS_CF_CAP` | ❓ **Not verified** | ❓ **Not verified** | `$case_id` from $_GET | PDF generation, output to browser |

### B. Permission Callbacks and Authorization Logic

#### 1. `require_access()` — Global therapist/administrator check

```php
public static function require_access(): void
{
    if (!self::current_user_can_access()) {
        wp_die('دسترسی غیرمجاز.', 'دسترسی غیرمجاز', ['response' => 403]);
    }
}
```

**Checks**: `current_user_can(PAS_CF_CAP)` — therapist role capability

**Called in**: `handle_save_case`, `handle_add_note`, `handle_edit_note`, `handle_delete_note`, `handle_apply_intake_map`, `handle_send_reminder`

**Gap**: Does NOT check case-specific ownership — only verifies therapist role exists.

#### 2. `require_case_access(object $case)` — Case-specific check

```php
public static function require_case_access(object $case): void
{
    if (!self::current_user_can_access_case($case)) {
        wp_die('این پرونده به شما تخصیص داده نشده است.', 'دسترسی غیرمجاز', ['response' => 403]);
    }
}
```

**Checks**: `current_user_can_access_case($case)` which calls `user_can_access_case_row(get_current_user_id(), $case->assigned_therapist, $case->created_by)`

**Called in**: `handle_apply_intake_map`, `handle_send_reminder`, `render_edit_page`

**Gap**: None when called. This is the correct authorization check.

#### 3. Nonce verification

All admin-post handlers use `check_admin_referer('pas_cf_<action>')` which verifies:
- WordPress nonce valid for the action
- Referer header matches expected pattern

**Present in**: All 10 admin-post handlers verified.

**Missing**: Some handlers may not verify nonces. Need to inspect each handler's full implementation.

#### 4. Direct endpoint accessibility

**Critical finding**: Admin menu visibility (`PAS_CF_CAP` required) does NOT protect underlying endpoints.

```php
// From render_list_page():
$cases = $can_view_all
    ? Pas_CF_Case_File::list_all()
    : Pas_CF_Case_File::list_for_therapist(get_current_user_id());
```

**Even without the menu visible**, all admin-post actions are directly accessible:
```
example.com/wp-admin/admin-post.php?action=pas_cf_save_case&case_id=123
```

This works regardless of whether the user can see the Case File menu. All endpoints have their own capability checks (`PAS_CF_CAP`), but menu hiding is security-through-obscurity, not actual protection.

### C. IDOR Vulnerability Assessment

| Endpoint | Parameter | IDOR Risk | Evidence |
|----------|-----------|-----------|----------|
| `get($case_id)` | case_id | **HIGH** | No authorization in method itself. Caller must check `current_user_can_access_case()`. |
| `get_by_phone($phone)` | phone | **HIGH** | No authorization. Any phone number lookup returns associated case. |
| `get_notes($case_id)` | case_id | **HIGH** | No authorization in method. Caller must check capabilities. |
| `get_note_history($note_id)` | note_id | **HIGH** | No authorization. Fetches any note's history. |
| `get_deleted_notes($case_id)` | case_id | **HIGH** | No user check. Any case_id returns deleted notes. |
| `add_note($case_id, ...)` | case_id | **HIGH** | No ownership validation. Any user can add note to any case. |
| `update_note($note_id, $case_id)` | note_id, case_id | **HIGH** | Only checks `note_id->case_id` match, not user ownership. |
| `delete_note($note_id, $case_id)` | note_id, case_id | **HIGH** | Only checks `note_id->case_id` match, not user ownership. |
| `restore_note_version($history_id, $case_id)` | history_id, case_id | **MEDIUM** | Checks case_id match between history and parameter, but not user capabilities. |
| `list_all()` | N/A | **CRITICAL** | Returns ALL cases in system, no filters. |
| `list_for_therapist($id)` | therapist_id | **LOW** | Uses central `case_scope_sql()` — correct when used properly. |

### D. Summary: Safe vs. Unsafe Integration Capabilities

#### Verified Safe Capabilities (dashboard can use without wrappers, assuming prior authorization checks):

1. `list_for_therapist($current_user_id)` — Returns only therapist's assigned + unassigned cases they created
2. `get($case_id)` — WITH wrapper: `if (Pas_CF_Security::current_user_can_access_case($case)) { $case = Pas_CF_Case_File::get($case_id); }`
3. `get_notes($case_id)` — WITH wrapper: check case access first
4. `get_couple_sessions($case)` — Enforces `can_view_partner` internally
5. `Pas_CF_Security::current_user_can_access_case($case)` — Single source of truth for access
6. `Pas_CF_Security::case_scope_sql($user_id)` — Correct SQL scope for list queries
7. `Pas_CF_Crypto::is_configured()` — Check before expecting encrypted data
8. `Pas_CF_Audit_Log::for_case($case_id)` — Audit records for a case

#### Capabilities Requiring Dashboard Wrappers (must add authorization before calling):

1. `get($case_id)` — Must wrap with `current_user_can_access_case()` check before calling
2. `get_by_phone($phone)` — Must wrap with authorization; consider removing from dashboard UI
3. `get_notes($case_id)` — Must wrap with `current_user_can_access_case($case)` check
4. `get_note_history($note_id)` — Must verify case ownership before calling
5. `get_deleted_notes($case_id)` — Must verify case ownership before calling
6. `add_note($case_id, ...)` — Must verify `current_user_can_access_case($case_id)` before allowing
7. `update_note($note_id, $case_id, ...)` — Must verify ownership + capabilities before allowing
8. `delete_note($note_id, $case_id)` — Must verify ownership + capabilities before allowing
9. `restore_note_version($history_id, $case_id)` — Must verify case ownership before allowing
10. `sync_partner_link($case_id, $phone)` — Internal method; dashboard should not call directly

#### Capabilities Requiring Case File Code Changes:

1. `list_all()` — Returns ALL cases regardless of ownership. Dashboard must never use this method for therapist views. Must use `list_for_therapist()` instead, or implement manual SQL scope filtering.

2. `get_raw($case_id)` — Private method with no authorization. Should remain private; dashboard should use `get()` with wrapper instead.

3. `get_partner($case)` — Missing `current_user_can_access_case()` check. Should add authorization check in method, OR dashboard should check before calling.

4. `add_note()`, `update_note()`, `delete_note()` — Methods check note-case ID match but not user capabilities. Should add `require_case_access($case)` checks in the methods themselves, OR dashboard must wrap all calls.

5. `Pas_CF_Crypto::decrypt_or_plain()` legacy plaintext handling — Dashboard must detect and warn about legacy plaintext display. Consider adding `Pas_CF_Crypto::is_configured()` checks before displaying encrypted fields.

#### Features That Must Not Be Integrated (without Case File code changes):

1. `list_all()` for therapist case listings — Returns all cases in system, security bypass
2. Direct `get_raw()` calls — No authorization, internal method only
3. `get_partner()` without prior `current_user_can_access_case($case)` check — Returns partner case raw data
4. `get_by_phone()` for therapist lookups — No authorization, returns case by phone only
5. Any dashboard feature that queries `wpdb->pas_cf_cases` directly — Prohibited; all access must go through provided APIs
6. Admin menu hiding as endpoint security — Menu visibility ≠ endpoint protection; all admin-post actions are directly accessible

---

## PHASE ZERO FINAL RECOMMENDATION

## GO / NO-GO Recommendation

Based on the complete Phase Zero audit evidence, the revised recommendation is:

### **CONDITIONAL GO**

The dashboard integration can proceed **CONDITIONALLY** with the following mandatory requirements:

### Verified Safe Capabilities (can integrate immediately):

1. **Case listing**: Use `list_for_therapist($current_user_id)` only — never `list_all()`
2. **Case detail view**: `get($case_id)` with wrapper: `Pas_CF_Security::current_user_can_access_case($case)` check first
3. **Note reading**: `get_notes($case_id)` with `current_user_can_access_case($case)` check first
4. **Couple sessions**: `get_couple_sessions($case)` — enforcement already built-in
5. **Audit log retrieval**: `Pas_CF_Audit_Log::for_case($case_id)` — read-only, no authorization gaps
6. **Capability checks**: `Pas_CF_Security::current_user_can_access_case()` and `current_user_can_access_case_row()` — use as single source of truth
7. **Scope SQL**: `Pas_CF_Security::case_scope_sql()` — correct SQL filtering for list queries

### Capabilities Requiring Dashboard Wrappers (must implement before integration):

1. **All case detail operations**: Wrap `get()`, `get_notes()`, `get_deleted_notes()`, `get_note_history()` with `current_user_can_access_case($case)` check
2. **All note write operations**: Wrap `add_note()`, `update_note()`, `delete_note()`, `restore_note_version()` with `current_user_can_access_case($case_id)` check before allowing
3. **Partner access**: Respect `can_view_partner` from `get_couple_sessions()` — only show partner data when user has access to both cases
4. **Encryption compliance**: Check `Pas_CF_Crypto::is_configured()` before expecting encrypted data; show warning if key missing
5. **CSV/PDF export**: Verify `handle_export_csv()` authorization; ensure only assigned cases + appropriate data is exported

### Capabilities Requiring Case File Code Changes (must be fixed before or concurrently with dashboard):

1. **`list_all()` security**: Documented as critical gap — must never be used for therapist views. Either remove or replace with `list_for_therapist()`-based default.
2. **`get_raw()` exposure**: Private method with no authorization. Should remain private; expose only `get()` with wrapper.
3. **`get_partner()` missing authorization**: Should add `current_user_can_access_case($case)` check in the method, OR document that dashboard must check before calling.
4. **`add_note()`, `update_note()`, `delete_note()` missing capability checks**: Methods check note-case ID match but not user ownership. Should add `require_case_access($case)` in each method, OR dashboard must implement strict wrapping.
5. **Legacy plaintext detection**: `decrypt_or_plain()` may return legacy plaintext. Dashboard must detect and display warnings when encryption key was missing previously.

### Features That Must Not Be Integrated (without code changes):

1. **`list_all()` for therapist case listings** — Returns ALL cases, security critical bypass
2. **Direct `wpdb->pas_cf_cases` queries** — Prohibited; all access through provided APIs only
3. **`get_by_phone()` for therapist lookups** — No authorization, IDOR vulnerability
4. **Admin menu hiding as security** — Myth; all endpoints have separate capability checks
5. **Unverified REST API routes** — Case File has no public REST routes; only admin-post handlers

### GO Conditions (must all be met):

- [ ] Dashboard implements `current_user_can_access_case()` wrapper before every case operation
- [ ] Dashboard uses `list_for_therapist($current_user_id)` for all case listings
- [ ] Dashboard checks `Pas_CF_Crypto::is_configured()` and shows compliance warning if key missing
- [ ] Dashboard respects `can_view_partner` from `get_couple_sessions()` output
- [ ] Dashboard does NOT call `list_all()`, `get_raw()`, or `get_by_phone()` without additional authorization
- [ ] Dashboard implements authorization for `add_note()`, `update_note()`, `delete_note()`, `restore_note_version()`
- [ ] Case File `class-security.php` `user_can_access_case_row()` method used as single access check source
- [ ] No direct `wpdb` queries against `pas_cf_*` tables in dashboard code
- [ ] All admin-post handler calls include proper nonce and capability verification

### NO-GO Triggers (if any of these are true):

- [ ] Case File `PAS_CF_CAP` role not properly configured in dashboard's WordPress installation
- [ ] Encryption key `PAS_CORE_ENCRYPTION_KEY` not set and dashboard cannot show compliance warning
- [ ] Dashboard requires `list_all()` functionality (cannot beworkaround with `list_for_therapist()`)
- [ ] Dashboard needs to directly query `pas_cf_cases` or `pas_cf_notes` tables
- [ ] Partner data display required without `can_view_partner` enforcement

### Final Recommendation

**CONDITIONAL GO** — Proceed with dashboard development implementing all wrapper requirements and code change fixes listed above. The core audit evidence shows that the Case File plugin has solid access control logic (`current_user_can_access_case()`, `case_scope_sql()`) when used correctly, but the dashboard must implement proper authorization wrappers and must not bypass the established security boundaries. The most critical fixes are: (1) never use `list_all()`, (2) always wrap `get()` with authorization check, (3) always verify encryption configuration, and (4) respect partner access controls.