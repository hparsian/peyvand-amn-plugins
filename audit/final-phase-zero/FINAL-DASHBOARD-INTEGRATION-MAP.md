# PEYVAND AMN ECOSYSTEM - Final Dashboard Integration Map

## FINAL-DASHBOARD-INTEGRATION-MAP.md

### 1. Dashboard Module Architecture

The Unified Therapist Dashboard is organized into 10 modules, each with defined data sources, access levels, and integration requirements. No dashboard code is written - this map represents the integration plan based on verified source code analysis.

### 2. Module Specifications

#### Module 1: Overview

| **Aspect** | **Specification** |
|------------|-------------------|
| **Primary Data Source** | `pivand-amn-case-file` via `list_for_therapist(get_current_user_id())` |
| **Data Scope** | Cases assigned to current therapist + self-created unassigned cases |
| **API Calls** | `Pas_CF_Case_File::list_for_therapist()`, `Pas_CF_Security::current_user_can_access_case()` |
| **Display Items** | Case count, recent cases, system health summary |
| **Access Level** | Therapist: assigned + self-created cases; Admin: all cases |
| **Security Wrappers Required** | `current_user_can_access_case()` for any case detail links |
| **Dependencies** | Core operational, Case File active |
| **Compliance Check** | `Pas_CF_Crypto::is_configured()` - show warning if key missing |
| **UI Restrictions** | Hide case count from unauthorized roles; respect `can_view_partner` |

#### Module 2: Assessments

| **Aspect** | **Specification** |
|------------|-------------------|
| **Primary Data Source** | `pivand-amn-quiz-enterprise` via case assignment association |
| **Data Scope** | Quiz results for cases assigned to current therapist |
| **API Calls** | REST routes `psy/v1/report/(?P<id>\d+)`, AJAX `zdp_quiz_submit_answers` |
| **Display Items** | List of completed quizzes, scores, assessment trends |
| **Access Level** | Only cases where `assigned_therapist === current_user_id` OR (`assigned_therapist === 0 AND created_by === current_user_id`) |
| **Security Wrappers Required** | Verify case access before each quiz data call; `current_user_can_access_case($case)` |
| **Dependencies** | Core + Case File + Quiz Enterprise |
| **Compliance Check** | Encryption of assessment data - check `Pas_CF_Crypto::is_configured()` |
| **UI Restrictions** | Hide quiz results for cases not assigned to current therapist; no direct phone-to-quiz lookup without case association |

**Quiz Data Access Flow**:
1. Dashboard lists therapist's cases via `list_for_therapist(get_current_user_id())`
2. For each case, if case has associated quiz data, fetch via quiz APIs
3. Quiz APIs may use phone number lookup - must verify case ownership first
4. Display results only for authorized cases

#### Module 3: Case Files

| **Aspect** | **Specification** |
|------------|-------------------|
| **Primary Data Source** | `pivand-amn-case-file` - PRIMARY dashboard data source |
| **Data Scope** | All cases therapist has access to (assigned + self-created unassigned) |
| **API Calls** | `Pas_CF_Case_File::list_for_therapist()`, `Pas_CF_Case_File::get()`, `Pas_CF_Case_File::get_notes()` |
| **Display Items** | Case details, patient info, session history, notes, partner sessions |
| **Access Level** | Therapist: assigned + self-created; Admin: all cases |
| **Security Wrappers REQUIRED** | CRITICAL - See below |
| **Dependencies** | Core + Case File (mandatory) |
| **Compliance Check** | `Pas_CF_Crypto::is_configured()` BEFORE displaying any encrypted fields |
| **UI Restrictions** | Absolute - see security mandates below |

**CRITICAL Security Mandates for Case File Module**:

1. **NEVER use `list_all()`** - Must use `list_for_therapist(get_current_user_id())`
2. **Always wrap `get($case_id)`** with `current_user_can_access_case($case)` check
3. **Check `Pas_CF_Crypto::is_configured()`** before displaying any encrypted field (`medical_history_encrypted`, `counseling_history_encrypted`, `content_encrypted`, etc.)
4. **Respect `can_view_partner`** from `get_couple_sessions()` - don't display partner data when FALSE
5. **No direct `wpdb->pas_cf_cases` queries** - use provided APIs only
6. **All note CRUD operations** must have prior `current_user_can_access_case($case_id)` check
7. **`get_by_phone()` must not be used** for therapist lookups - IDOR vulnerability
8. **Admin menu hiding is NOT security** - implement own authorization checks

**Case File Data Flow** (dashboard to user):
```
Dashboard Request
    ↓
list_for_therapist(get_current_user_id())  [FILTERED]
    ↓
For each case: current_user_can_access_case($case)  [AUTHORIZATION CHECK]
    ↓
get($case_id)  [WITH decryption check]
    ↓
decrypt_or_plain() + is_configured() check  [ENCRYPTION HANDLING]
    ↓
Display with proper access controls
```

#### Module 4: Patients

| **Aspect** | **Specification** |
|------------|-------------------|
| **Primary Data Source** | `pivand-amn-core` client registry + `pivand-amn-case-file` case-phone mapping |
| **Data Scope** | Patients (clients) associated with therapist's cases |
| **API Calls** | `pas_get_client` filter, `Pas_CF_Case_File::get()` (for phone), `Pas_CF_Case_File::find_linked_wp_user()` |
| **Display Items** | Patient names, contact info, status, conflict indicators, source tracking |
| **Access Level** | Therapist: patients in their assigned cases; Admin: all patients |
| **Security Wrappers Required** | `pas_get_client` requires `PAS_CF_CAP` or `manage_options`; case access checks for phone lookups |
| **Dependencies** | Core + Case File (for case-phone mapping) + Client Portal (optional) |
| **Compliance Check** | None specific - identity data is non-clinical but still sensitive |
| **UI Restrictions** | Show conflict indicators (`multiple_cases`, `shared_identifier`) when present; do not merge clients with open conflicts |

**Patient Identity Integration** (from verified source):
- Core client registry stores identifiers from multiple sources (portal, case file, quiz, contact form)
- Dashboard accesses patient data via `pas_get_client` filter
- Phone number is NOT the primary unique identifier - use `client_id` from Core registry
- `multiple_cases` conflict may appear if same phone in multiple case file records
- Dashboard should display conflict status but not auto-merge

**`pas_get_client` Filter Usage**:
```php
$query = ['type' => 'mobile', 'value' => '09123456789'];
$client = apply_filters('pas_get_client', null, $query);
```
- Requires `PAS_CF_CAP` or `manage_options`
- Returns client data array or null
- Dashboard must check capability before calling

#### Module 5: Appointments

| **Aspect** | **Specification** |
|------------|-------------------|
| **Primary Data Source** | `pivand-amn-client-portal` bookings + `pivand-amn-case-file` case assignment |
| **Data Scope** | Appointments/sessions for therapist's assigned patients |
| **API Calls** | Portal AJAX: `zdp_create_booking`, `zdp_upload_receipt`; Case File: `get_couple_sessions()` |
| **Display Items** | Upcoming sessions, past sessions, session status, couple/partner info, cancellation status |
| **Access Level** | Therapist: sessions for assigned cases; Reception: limited view; Admin: all |
| **Security Wrappers Required** | Case access checks (`current_user_can_access_case()`) for case-associated appointments; portal OTP authentication separate |
| **Dependencies** | Client Portal (mandatory for booking data) + Case File (for assignment verification) |
| **Compliance Check** | None specific - appointment scheduling data |
| **UI Restrictions** | Only show appointments for cases therapist has access to; respect `can_view_partner` for couple sessions; OTP auth for portal actions |

**Appointment Data Flow**:
1. Dashboard identifies therapist's cases via `list_for_therapist(get_current_user_id())`
2. For each case, check associated portal bookings (requires portal integration)
3. Filter bookings by case access - only show sessions for authorized cases
4. Display couple session data with `can_view_partner` enforcement
5. OTP authentication for any portal-mediated actions

#### Module 6: Reports

| **Aspect** | **Specification** |
|------------|-------------------|
| **Primary Data Source** | `pivand-amn-case-file` via `Pas_CF_Reports` + quiz data via enterprise APIs |
| **Data Scope** | Clinical reports for therapist's assigned cases |
| **API Calls** | `Pas_CF_Reports::summary()`, REST quiz routes, case note exports |
| **Display Items** | Summary statistics, session trends, progress charts, export options |
| **Access Level** | Therapist: reports for assigned cases; Admin: all reports |
| **Security Wrappers Required** | Case access before each report query; `current_user_can_access_case()`; export authorization inspection |
| **Dependencies** | Core + Case File + Quiz Enterprise (for full reports) |
| **Compliance Check** | `Pas_CF_Crypto::is_configured()` for any clinical data in reports; export data minimization |
| **UI Restrictions** | Only include data from authorized cases; minimize exported fields; respect partner data boundaries |

**Report Security** (from audit findings):
- CSV export authorization not fully verified - dashboard must inspect `handle_export_csv()`
- PDF export (`handle_print_case`) - similar verification needed
- Only include clinical data from cases therapist has access to
- Minimize exported fields - do not include all clinical notes unless specifically needed
- Check `Pas_CF_Crypto::is_configured()` before including encrypted data in reports

#### Module 7: Communications

| **Aspect** | **Specification** |
|------------|-------------------|
| **Primary Data Source** | `pivand-amn-sms-bridge` (SMS) + `pivand-amn-smtp-mailer` (email) + case note data |
| **Data Scope** | Reminder SMS/email logs and configuration for therapist's cases |
| **API Calls** | `pas_send_sms` filter, SMS sending logic, reminder blocker checks |
| **Display Items** | SMS send status, reminder consent status, channel configuration, send history |
| **Access Level** | Therapist: own cases; Admin: all cases |
| **Security Wrappers Required** | `has_filter('pas_send_sms')` check; `current_user_can_access_case()` for case-specific SMS; reminder blocker logic |
| **Dependencies** | SMS Bridge (optional - via filter check) + Case File (for case association) |
| **Compliance Check** | None specific - communication configuration data |
| **UI Restrictions** | Show warnings if SMS bridge not active; respect reminder consent (`reminder_consent` field); show cooldown status (12-hour minimum between sends) |

**SMS Reminder Workflow** (from verified source):
```php
// In handle_send_reminder():
$next = Pas_CF_Case_File::get_next_session($case);
$result = Pas_CF_Case_File::reminder_blocker($case, $next);

if ($result === 'ok') {
    // Check consent
    if (empty($case->reminder_consent)) {
        $result = 'no_consent';
    }
    
    // Check channel
    $channel = ($case->reminder_channel ?? 'sms');
    if (!isset(Pas_CF_Case_File::REMINDER_CHANNELS[$channel]) || $channel === 'none') {
        $result = 'no_channel';
    }
    
    // Check cooldown (12 hours)
    $last = ($case->reminder_last_sent_at ?? '');
    if ($last !== '' && (time() - (int) strtotime($last)) < 12 * HOUR_IN_SECONDS) {
        $result = 'cooldown';
    }
    
    // Send SMS if all checks pass
    if ($result === 'ok') {
        $pattern = (string) apply_filters('pas_cf_reminder_sms_pattern', ...);
        if ($pattern === '' || !has_filter('pas_send_sms')) {
            $result = 'sms_not_configured';
        } else {
            $sent = apply_filters('pas_send_sms', null, $case->phone, $pattern, [...]);
            $result = $sent === false ? 'failed' : 'sent';
        }
    }
}

// Mark sent if successful
if ($result === 'sent') {
    Pas_CF_Case_File::mark_reminder_sent($case_id);
}
```

**Dashboard Display** should show:
- Reminder consent status per case
- Last reminder send date/time
- Cooldown status (if within 12-hour window)
- SMS bridge active/inactive warning
- Channel configuration

#### Module 8: Notifications

| **Aspect** | **Specification** |
|------------|-------------------|
| **Primary Data Source** | `pivand-amn-case-file` reminder settings + Core client registry |
| **Data Scope** | Notification preferences and status for therapist's cases |
| **API Calls** | `Pas_CF_Case_File::reminder_blocker()`, `Pas_CF_Case_File::mark_reminder_sent()`, case field queries |
| **Display Items** | Consent status, channel, cooldown status, last sent date, configuration options |
| **Access Level** | Therapist: own cases; Admin: all cases |
| **Security Wrappers Required** | `current_user_can_access_case()` per case; `Pas_CF_Crypto::is_configured()` not directly relevant but good practice |
| **Dependencies** | Case File (mandatory) |
| **Compliance Check** | None specific - notification preferences data |
| **UI Restrictions** | Show warning if reminder_consent not given; show cooldown timer; SMS bridge status warning |

#### Module 9: System Health

| **Aspect** | **Specification** |
|------------|-------------------|
| **Primary Data Source** | All plugins - core, case file, quiz, portal, sms |
| **Data Scope** | System-wide status, plugin health, database integrity, dependency status |
| **API Calls** | Core `stats()`, `recent_conflicts()`; Case File audit log queries; Portal stats (partial) |
| **Display Items** | Plugin status, database tables count, conflict status, version numbers, dependency health |
| **Access Level** | Admin only (typically) |
| **Security Wrappers Required** | `PAS_CF_CAP_ALL` for full view; `PAS_CF_CAP` for therapist-limited view |
| **Dependencies** | All plugins active + Core operational + Database accessible |
| **Compliance Check** | Conflict status in client registry (`multiple_cases`, etc.) |
| **UI Restrictions** | Show conflict warnings; plugin health status; dependency warnings; last rebuild date |

**System Health Indicators** (from verified source):
- Core `stats()`: client count, identifier count, conflict open count, last rebuild date
- Case File: plugin version, table status, encryption key status
- Quiz: table existence, data count (if inspected)
- Portal: user count (if inspected)
- SMS: `has_filter('pas_send_sms')` check result

**Conflict Display** (from Core registry):
- `recent_conflicts(100)`: returns open identity conflicts
- `multiple_cases` conflicts: "بیش از یک پرونده‌ی بالینی با همین شماره وجود دارد"
- Dashboard should display these as warnings, not auto-resolve

#### Module 10: Unified Settings

| **Aspect** | **Specification** |
|------------|-------------------|
| **Primary Data Source** | Core configuration + Case File settings + Quiz settings |
| **Data Scope** | System-wide configuration options accessible to dashboard administrator |
| **API Calls** | Core option reads (`pas_core_db_version`, `pas_registry_last_rebuild`); Case File options (`pas_cf_db_version`, `pas_cf_reminder_sms_pattern`); Quiz options (partial) |
| **Display Items** | Version numbers, database compatibility, conflict settings, SMS pattern configuration, encryption key status |
| **Access Level** | Admin with `PAS_CF_CAP_ALL` |
| **Security Wrappers Required** | Admin capability checks; `PAS_CF_CAP_ALL` verification; no therapist-level access to system settings |
| **Dependencies** | All plugins active + Core operational + Database accessible |
| **Compliance Check** | Database version compatibility (`pas_cf_db_version` vs `PAS_CF_VERSION`); encryption key status |
| **UI Restrictions** | Admin-only access; show warnings for version mismatches; show encryption key setup guidance; do not expose raw configuration values |

**Critical Settings** (from verified source):
- `pas_cf_db_version` must match `PAS_CF_VERSION` constant - compliance warning if mismatched
- `PAS_CORE_ENCRYPTION_KEY` in wp-config.php - must be set for encrypted data to work
- `pas_cf_reminder_sms_pattern` - SMS pattern configuration; dashboard should show if not set
- Core `pas_core_db_version` - core version tracking
- `pas_registry_last_rebuild` - last client registry rebuild date

**Settings Validation Flow**:
1. On dashboard load, check `pas_cf_db_version` vs `PAS_CF_VERSION`
2. If mismatched → show compliance warning in settings UI
3. Check `Pas_CF_Crypto::is_configured()` → show key status
4. Check `has_filter('pas_send_sms')` → show SMS bridge status
5. Check Core registry conflict count → display if > 0
6. Display all values with appropriate warnings/icons

---