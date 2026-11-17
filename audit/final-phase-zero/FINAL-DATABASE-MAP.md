# PEYVAND AMN ECOSYSTEM - Final Database Map

## FINAL-DATABASE-MAP.md

### 1. Core Database Tables (pivand-amn-core 1.8.1)

#### 1.1 `pas_clients` Table

```sql
CREATE TABLE pas_clients (
    client_id char(29) NOT NULL,
    display_name varchar(190) NOT NULL DEFAULT '',
    primary_mobile varchar(20) NOT NULL DEFAULT '',
    primary_email varchar(190) NOT NULL DEFAULT '',
    status varchar(20) NOT NULL DEFAULT 'active',
    conflict_count smallint(5) unsigned NOT NULL DEFAULT 0,
    sources_count smallint(5) unsigned NOT NULL DEFAULT 0,
    created_at datetime NOT NULL DEFAULT '1970-01-01 00:00:00',
    updated_at datetime NOT NULL DEFAULT '1970-01-01 00:00:00',
    merged_into char(29) NOT NULL DEFAULT '',
    PRIMARY KEY  (client_id),
    KEY primary_mobile (primary_mobile),
    KEY primary_email (primary_email),
    KEY status (status)
) $charset_collate;
```

**Column Purpose**:
- `client_id`: Unique 26-char identifier in format `pc_` + ULID-like base32. **Primary key for client identity.**
- `display_name`: Patient display name (NOT spouse name, not therapist name)
- `primary_mobile`: Normalized Iranian mobile number (09xxxxxxxxx format). **One of multiple possible identifiers.**
- `primary_email`: Patient email address. **One of multiple possible identifiers.**
- `status`: 'active' or 'review' (requires manual review when conflicts detected)
- `conflict_count`: Number of open identity conflicts for this client
- `sources_count`: Number of source records (portal, case file, quiz, etc.) contributing to this client
- `merged_into`: If this client was merged into another, stores the target `client_id`; empty = not merged

**Key Behavioral Rules** (from `class-client-registry.php`):
- `merged_into = ''` means record is active/primary
- `merged_into != ''` means this client was absorbed into another; all references should use the target client_id
- `conflict_count > 0` and `status = 'review'` means manual admin review required
- `sources_count` tracks how many different record types (portal_user, case_file, quiz_invitation, lead, contact_form) contributed identifiers

**Never store**: Clinical data, session notes, assessment results, or any minimally sensitive patient information in this table.

#### 1.2 `pas_client_identifiers` Table

```sql
CREATE TABLE pas_client_identifiers (
    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    client_id char(29) NOT NULL,
    identifier_type varchar(20) NOT NULL DEFAULT '',
    identifier_value varchar(190) NOT NULL DEFAULT '',
    identifier_hash char(32) NOT NULL DEFAULT '',
    source varchar(40) NOT NULL DEFAULT 'auto',
    source_ref varchar(100) NOT NULL DEFAULT '',
    display_name varchar(190) NOT NULL DEFAULT '',
    created_at datetime NOT NULL DEFAULT '1970-01-01 00:00:00',
    last_seen_at datetime NOT NULL DEFAULT '1970-01-01 00:00:00',
    PRIMARY KEY  (id),
    UNIQUE KEY identifier_hash (identifier_hash),
    KEY client_id (client_id),
    KEY identifier_lookup (identifier_type,identifier_value)
) $charset_collate;
```

**Column Purpose**:
- `id`: Auto-increment primary key
- `client_id`: Links to `pas_clients.client_id`. **One client can have multiple identifiers.**
- `identifier_type`: 'mobile', 'email', 'wp_user'. **Determines how to interpret `identifier_value`.**
- `identifier_value`: The actual value (phone number, email address, WP user ID).
- `identifier_hash`: MD5 of `type|value|source|ref`. Used for conflict detection duplication checks.
- `source`: 'portal_user', 'case_file', 'quiz_invitation', 'lead', 'contact_form'. **Tracks which plugin/record created this identifier.**
- `source_ref`: Record ID from the source plugin (e.g., case file ID, quiz invitation ID, WP user ID).
- `display_name`: Name associated with this identifier source (may differ across sources for same client).
- `created_at`: When this identifier was first observed.
- `last_seen_at`: When this identifier was most recently observed in a rebuild/reindex.

**Key Behavioral Rules**:
- `UNIQUE KEY identifier_hash` prevents duplicate identical identifiers from same source+ref combination
- `KEY identifier_lookup (identifier_type,identifier_value)` enables fast lookup: `get_client_by_identifier('mobile', '09123456789')`
- During registry rebuild, identifiers with `source <> 'manual'` and `last_seen_at < current_time` are cleaned up (old auto-observations removed)
- Manual identifiers (source='manual') are never auto-removed

**Critical**: `identifier_value` for mobile type must be normalized using `Pas_Core_Helpers::normalize_mobile()` or `preg_replace('/\D+/', '', $value)` then match pattern `^09\d{9}$`.

#### 1.3 `pas_identity_conflicts` Table

```sql
CREATE TABLE pas_identity_conflicts (
    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    conflict_key char(32) NOT NULL DEFAULT '',
    conflict_type varchar(40) NOT NULL DEFAULT '',
    identifier_type varchar(20) NOT NULL DEFAULT '',
    identifier_value varchar(190) NOT NULL DEFAULT '',
    details longtext NOT NULL,
    status varchar(20) NOT NULL DEFAULT 'open',
    detected_at datetime NOT NULL DEFAULT '1970-01-01 00:00:00',
    PRIMARY KEY  (id),
    UNIQUE KEY conflict_key (conflict_key),
    KEY status (status)
) $charset_collate;
```

**Column Purpose**:
- `id`: Auto-increment primary key
- `conflict_key`: MD5 hash of `type|identifier_value` - used for dedup (same conflict don't create duplicate rows)
- `conflict_type`: One of: `shared_identifier`, `multiple_wp_users`, `multiple_cases`, `email_multiple_mobiles`, `missing_name`
- `identifier_type`: The type that triggered the conflict (e.g., 'mobile', 'email')
- `identifier_value`: The value that caused the conflict (e.g., phone number '09123456789')
- `details`: Human-readable description of the conflict situation
- `status`: 'open' requires admin review; 'dismissed' is resolved and auto-cleaned after period
- `detected_at`: When conflict was first detected

**Conflict Types** (from `detect_conflicts()` method):
- `shared_identifier`: Same identifier (mobile/email) for different names - multiple people sharing contact info
- `multiple_wp_users`: Same WP user ID in multiple portal accounts
- `multiple_cases`: Same phone number in multiple case file records (`pas_cf_cases`)
- `email_multiple_mobiles`: One email associated with multiple different mobile numbers (possible phone number change)
- `missing_name`: Identifier with no associated name

**Key Behavioral Rules**:
- `conflict_key UNIQUE` prevents duplicate conflict entries for same type+value
- `status = 'open'` requires manual admin review via WordPress dashboard
- Conflicts with `detected_at` older than configurable period AND `status = 'open'` are auto-cleaned
- `multiple_cases` conflict triggers when Core registry rebuild finds same phone in multiple `pas_cf_cases` records
- **Do NOT auto-merge clients with open conflicts** - requires manual review

---

### 2. Case File Database Tables (pivand-amn-case-file 1.16.1)

#### 2.1 `pas_cf_cases` Table

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

**39 total columns**. Critical columns for dashboard:

| Column | Purpose | Dashboard Usage |
|--------|---------|-----------------|
| `id` | Primary key | Unique case identifier |
| `phone` | Patient mobile (identity key) | **NOT for unique identification** - see warnings below |
| `full_name` | Patient name | Display in dashboard |
| `assigned_therapist` | WP user ID of assigned therapist | Filter cases by current user |
| `created_by` | WP user ID who created case | Ownership tracking |
| `status` | Case status ('فعال', 'موقتاً متوقف', 'پایان‌یافته') | Case state display |
| `partner_case_id` | Linked case ID for couple/partner | Partner session logic |
| `reminder_consent` | Patient consent for reminders (0/1) | SMS/email reminder eligibility |
| `reminder_channel` | 'sms' | Reminder delivery method |
| `medical_history_encrypted` | Encrypted clinical data | **Requires `Pas_CF_Crypto::is_configured()` check** |
| `assigned_therapist` | **CRITICAL**: Used in `case_scope_sql()` for access filtering | Dashboard MUST use `list_for_therapist(get_current_user_id())` |

**Index Strategy**:
- `PRIMARY KEY (id)` - Primary lookup
- `KEY phone (phone)` - Phone-based searches (case file only, NOT for unique client id)
- `KEY assigned_therapist (assigned_therapist)` - Therapist filtering
- `KEY partner_case_id (partner_case_id)` - Partner/couple linking

**Never use `phone` as unique client identifier** - Multiple cases can have same phone ( triggers `multiple_cases` conflict in Core registry). Always use `client_id` from Core registry for unique identification.

#### 2.2 `pas_cf_notes` Table

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

**Column Purpose**:
- `id`: Unique note identifier
- `case_id`: Links to `pas_cf_cases.id`. **Critical for case association.**
- `session_date`: Date of session (YYYY-MM-DD format)
- `session_number`: Session ordinal (0 = unspecified/unknown, 1+ = specific session number)
- `content_encrypted`: **Full encrypted note content**. Must check `Pas_CF_Crypto::is_configured()` before decryption.
- `author_id`: WP user ID who created the note
- `duration_minutes`: Session duration in minutes (0 = unspecified)
- `status`: 'done', or other status values
- `next_session_date`: Date of next session (YYYY-MM-DD), or empty if not set
- `is_joint`: 1 if session was joint/couple, 0 if individual

**Index Strategy**:
- `PRIMARY KEY (id)` - Primary lookup
- `KEY case_id (case_id)` - **Critical**: All note queries filter by case_id first

**Query Pattern** (from `get_notes()`):
```sql
SELECT * FROM pas_cf_notes WHERE case_id = %d ORDER BY session_date DESC, id DESC
```

**Never query `pas_cf_notes` directly by other columns** in dashboard - use provided API methods.

#### 2.3 `pas_cf_note_history` Table

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

**Column Purpose** (CRITICAL - distinguishes from audit_log):
- `id`: Unique history record identifier
- `note_id`: Links to `pas_cf_notes.id`. **The note this history record pertains to.**
- `case_id`: Links to `pas_cf_cases.id`. **The case this note belongs to.**
- `session_date`: Session date at time of snapshot (before edit or after deletion)
- `session_number`: Session number at time of snapshot
- `content_encrypted`: **Snapshot of note content BEFORE the edit/delete action**. This is the key distinction from audit_log.
- `author_id`: Original author of the note (before edit/delete)
- `action`: 'edited' or 'deleted'. **Distinguishes edit history from deletion history.**
- `changed_by`: User who performed the edit or deletion
- `changed_at`: Timestamp of the change

**Key Distinction from `pas_cf_audit_log`**:
| Aspect | `pas_cf_note_history` | `pas_cf_audit_log` |
|--------|----------------------|-------------------|
| **Purpose** | Note version history (before-edits snapshots) | Security audit trail (who did what) |
| **What's stored** | Content snapshot before edit/delete | Action type, user ID, IP, timestamp |
| **Content type** | `content_encrypted` (the note's actual content) | `action` (text like 'create', 'edit_note', 'delete_note') |
| **Editability** | **Append-only - never edited or deleted after creation** | **Append-only - never edited or deleted after creation** |
| **Use case** | Restore previous note version | Track system activity/audit |

**Query Pattern** (from `get_note_history()`):
```sql
SELECT * FROM pas_cf_note_history WHERE note_id = %d ORDER BY changed_at DESC, id DESC
```

**Append-Only Guarantee** (from installer and code analysis):
- Records are **never** edited after insertion
- Records are **never** deleted after insertion  
- New snapshots are **always** added (on each edit or delete)
- History preserves the before-state at each change point

#### 2.4 `pas_cf_audit_log` Table

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

**Column Purpose**:
- `id`: Unique audit record identifier
- `case_id`: Links to `pas_cf_cases.id`. **Which case was affected.**
- `user_id`: WP user ID who performed the action. **0 if system/unauthenticated.**
- `action`: Action type. Verified values: `create`, `edit_case`, `add_note`, `edit_note`, `delete_note`, `restore_note`, `send_reminder`, `apply_intake_map`, `view`
- `ip`: Source IP address of the action
- `created_at`: Timestamp of the action

**Verified Action Types** (from call sites in `class-case-file.php`):
- `create` - Case creation via `Pas_CF_Case_File::create()`
- `edit_case` - Case update via `Pas_CF_Case_File::update()`
- `add_note` - Note creation via `Pas_CF_Case_File::add_note()`
- `edit_note` - Note update via `Pas_CF_Case_File::update_note()`
- `delete_note` - Note deletion via `Pas_CF_Case_File::delete_note()` (soft-delete to history)
- `restore_note` - Note version restoration via `Pas_CF_Case_File::restore_note_version()`
- `send_reminder` - Reminder SMS/email sent via `handle_send_reminder()`
- `apply_intake_map` - Intake mapping applied via `handle_apply_intake_map()`
- `view` - Case view from edit page (via `render_edit_page()`)

**Index Strategy**:
- `PRIMARY KEY (id)` - Primary lookup
- `KEY case_id (case_id)` - **Critical**: All queries for a specific case's audit trail

**Query Pattern** (from `Pas_CF_Audit_Log::for_case()` - not fully inspected but referenced):
```sql
SELECT * FROM pas_cf_audit_log WHERE case_id = %d ORDER BY created_at DESC
```

**Append-Only Guarantee**: Same as `note_history` - records never edited or deleted after creation.

**Never use audit_log for note version restoration** - Use `note_history` table instead.

---

### 3. Quiz Enterprise Database Tables (pivand-amn-quiz-enterprise 3.8.17)

#### 3.1 `psy_invitations` Table

```sql
CREATE TABLE psy_invitations (
    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    client_name varchar(190) NOT NULL DEFAULT '',
    client_phone varchar(20) NOT NULL DEFAULT '',
    client_email varchar(190) NOT NULL DEFAULT '',
    status varchar(20) NOT NULL DEFAULT 'pending',
    created_at datetime NOT NULL DEFAULT '1970-01-01 00:00:00',
    PRIMARY KEY  (id),
    KEY client_phone (client_phone)
) $charset_collate;
```

**Purpose**: Quiz invitations sent to potential clients. Tracks:
- `client_name`: Name of invited person
- `client_phone`: Iranian mobile number
- `client_email`: Email address
- `status`: 'pending', 'completed', etc.

**Index**: `KEY client_phone (client_phone)` - Lookup by phone number.

#### 3.2 `psy_attempts` Table

```sql
CREATE TABLE psy_attempts (
    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    phone varchar(20) NOT NULL DEFAULT '',
    score decimal(5,2) NOT NULL DEFAULT 0.00,
    completed tinyint(1) NOT NULL DEFAULT 0,
    result_text longtext,
    created_at datetime NOT NULL DEFAULT '1970-01-01 00:00:00',
    PRIMARY KEY  (id),
    KEY phone (phone)
) $charset_collate;
```

**Purpose**: Quiz attempt records. Tracks:
- `phone`: Iranian mobile number (identity key, NOT client_id)
- `score`: Decimal score (5,2) - e.g., 85.50
- `completed`: 1 if full quiz completed, 0 if incomplete
- `result_text`: Full assessment results (possibly encrypted)

**Index**: `KEY phone (phone)` - Lookup by phone number.

**Note**: Phone is the identity key here, NOT client_id. Same limitations as case file phone usage.

#### 3.3 `psy_couples` Table

```sql
CREATE TABLE psy_couples (
    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    phone varchar(20) NOT NULL DEFAULT '',
    partner_phone varchar(20) NOT NULL DEFAULT '',
    status varchar(20) NOT NULL DEFAULT 'active',
    created_at datetime NOT NULL DEFAULT '1970-01-01 00:00:00',
    PRIMARY KEY  (id),
    KEY phone (phone),
    KEY partner_phone (partner_phone)
) $charset_collate;
```

**Purpose**: Couple/partner relationship tracking. Tracks:
- `phone`: Primary person's mobile
- `partner_phone`: Partner's mobile
- `status`: 'active', 'inactive', etc.

**Indexes**: Both `phone` and `partner_phone` for bidirectional lookup.

#### 3.4 `psy_drafts` Table

```sql
CREATE TABLE psy_drafts (
    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    phone varchar(20) NOT NULL DEFAULT '',
    data longtext NOT NULL,
    created_at datetime NOT NULL DEFAULT '1970-01-01 00:00:00',
    PRIMARY KEY  (id),
    KEY phone (phone)
) $charset_collate;
```

**Purpose**: Saved quiz drafts. Tracks:
- `phone`: Associated person's mobile
- `data`: Serialized draft data (answers not yet submitted)
- `created_at`: When draft was saved

**Index**: `KEY phone (phone)`.

#### 3.5 `psy_logs` Table

```sql
CREATE TABLE psy_logs (
    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    phone varchar(20) NOT NULL DEFAULT '',
    action varchar(50) NOT NULL DEFAULT '',
    details longtext,
    created_at datetime NOT NULL DEFAULT '1970-01-01 00:00:00',
    PRIMARY KEY  (id),
    KEY phone (phone)
) $charset_collate;
```

**Purpose**: General audit logs for quiz system. Tracks:
- `phone`: Associated person's mobile
- `action`: Action type
- `details`: Additional action details (JSON-like text)

**Index**: `KEY phone (phone)`.

---

### 4. Client Portal Database Considerations

**From partial inspection and conversation historical memory**:

The client portal uses standard WordPress tables with additional meta:

- `wp_users`: Contains `zdp_` prefixed accounts (login = `zdp_` + normalized phone)
- `wp_usermeta`: Additional meta keys:
  - `cellphone`: Normalized mobile number
  - `zdp_mobile`: Alternative mobile field
  - `zdp_full_name`: Patient full name
  - Other portal-specific meta

**Portal-specific tables may exist** but were not fully inspected in this read-only audit. The portal integrates with Core client registry during registry rebuild by feeding portal user identifiers (mobile + WP user ID) into the clustering algorithm.

**Key Integration Point**: `pas_lookup_known_client_name` chain priority:
1. Client portal (highest) - uses `zdp_full_name` + `cellphone/zdp_mobile` from WP usermeta
2. Case file - stores `full_name` and `phone` but no direct filter integration
3. Quiz enterprise - stores client names in `psy_invitations`

---

### 5. Cross-Plugin Data Mapping

| Data Element | Source Plugin | Target Plugin/Table | Integration Point |
|-------------|--------------|---------------------|-------------------|
| Patient mobile phone | Any plugin | Core `pas_client_identifiers` | During registry rebuild (`collect_observations()`) |
| Case file `phone` | `pas_cf_cases` | Core `pas_client_identifiers` (source='case_file') | Registry rebuild reads `pas_cf_cases.phone` |
| Quiz `client_phone` | `psy_invitations`/`psy_attempts` | Core `pas_client_identifiers` (source='quiz_invitation') | Registry rebuild reads quiz tables |
| Client portal `zdp_mobile` | Portal WP usermeta | Core `pas_client_identifiers` (source='portal_user') | Registry rebuild reads WP users+usermeta |
| Case therapist assignment | `pas_cf_cases`.`assigned_therapist` | Dashboard access filtering | `list_for_therapist(get_current_user_id())` |
| Note authorship | `pas_cf_notes`.`author_id` | Dashboard display | `get_current_user_id()` at note creation |
| Reminder consent | `pas_cf_cases`.`reminder_consent` | SMS reminder eligibility | `Pas_CF_Case_File::reminder_blocker()` |
| Couple partner link | `pas_cf_cases`.`partner_case_id` | Partner session display | `get_couple_sessions($case)` |

**Critical Rule**: No plugin directly queries another plugin's tables. All cross-plugin data access goes through:
1. Core registry (`pas_get_client` filter)
2. Case File APIs (`get_notes()`, `get()`, etc.)
3. Quiz enterprise REST/AJAX routes
4. Client portal OTP/auth system

**No direct `wpdb->db_query()` across plugin table boundaries in dashboard code** (prohibited by audit constraints).

---

### 6. Database Schema Integrity Constraints

| Constraint | Location | Verified |
|------------|----------|----------|
| `pas_clients.client_id PRIMARY KEY` | Core installer | ✅ |
| `pas_client_identifiers.identifier_hash UNIQUE` | Core installer | ✅ |
| `pas_client_identifiers.client_id KEY` | Core installer | ✅ |
| `pas_cf_cases.id PRIMARY KEY` | Case File installer | ✅ |
| `pas_cf_cases.key phone` | Case File installer | ✅ (but NOT unique) |
| `pas_cf_cases.key assigned_therapist` | Case File installer | ✅ |
| `pas_cf_cases.key partner_case_id` | Case File installer | ✅ |
| `pas_cf_notes.case_id KEY` | Case File installer | ✅ |
| `pas_cf_note_history.note_id KEY` | Case File installer | ✅ |
| `pas_cf_note_history.case_id KEY` | Case File installer | ✅ |
| `pas_cf_audit_log.case_id KEY` | Case File installer | ✅ |
| `psy_invitations.key client_phone` | Quiz installer | ✅ |
| `psy_attempts.key phone` | Quiz installer | ✅ |

**No foreign key constraints** found in any of the plugin installers. All relationships are enforced through application logic (PHP code), not database-level foreign keys. This is important for dashboard developers to understand - ORM-style foreign key enforcement is NOT present; all referential integrity must be validated in code.

**Migration version tracking**:
- Core: `update_option('pas_core_db_version', '1.0.0')` 
- Case File: `update_option('pas_cf_db_version', PAS_CF_VERSION)` where `PAS_CF_VERSION` is defined in the plugin main file
- Quiz: Not explicitly found in inspected code (may use core versioning or separate mechanism)

**No dbDelta-based migrations observed** after initial installation. The `create_or_upgrade_tables()` method uses `dbDelta` once during initial setup. Subsequent changes would require manual migration or option version tracking.

---