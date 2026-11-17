# PEYVAND AMN ECOSYSTEM - Final Dependency Graph

## FINAL-DEPENDENCY-GRAPH.md

### 1. Core Dependency Structure

```mermaid
graph TD
    %% Core is the foundation
    core[pivand-amn-core 1.8.1] -->|Provides:| core-helpers[Pas_Core_Helpers]
    core -->|Provides:| filter-registry[Filter Registry (12 contracts)]
    core -->|Provides:| table-registry[Table Registry (15 table groups)]
    core -->|Provides:| compliance[Compliance System]
    core -->|Provides:| prefix-map[Prefix Map (10 categories)]
    core -->|Provides:| jalali[Jalali Date Helpers]
    core -->|Provides:| client-registry[Client Registry (pas_clients, identifiers, conflicts)]
    
    %% Core dependencies (none critical)
    core-helpers -->|mobile normalization| core-helpers-internal
    
    %% Quiz Enterprise depends on Core for identity
    quiz[pivand-amn-quiz-enterprise 3.8.17] -->|Uses:| core-identity[Core identity registry]
    quiz -->|During:| registry-rebuild[Registry Rebuild (optional)]
    quiz -->|Tables:| psy-invitations[psy_invitations]
    quiz -->|Tables:| psy-attempts[psy_attempts]
    quiz -->|Tables:| psy-couples[psy_couples]
    quiz -->|Tables:| psy-drafts[psy_drafts]
    quiz -->|Tables:| psy-logs[psy_logs]
    quiz -.->|Optional core features| core-optional[Core optional features]
    
    %% Case File has hard dependency on Core for encryption
    case-file[pivand-amn-case-file 1.16.1] -->|HARD:| crypto[Pas_Core_Crypto - encryption]
    case-file -->|Uses:| core-identity[Core client identifiers (during registry rebuild)]
    case-file -->|Tables:| cf-cases[pas_cf_cases]
    case-file -->|Tables:| cf-notes[pas_cf_notes]
    case-file -->|Tables:| cf-note-history[pas_cf_note_history]
    case-file -->|Tables:| cf-audit-log[pas_cf_audit_log]
    case-file -->|Uses:| sms-bridge[SMS Bridge via pas_send_sms filter]
    case-file -->|Dependency:| core-version[Core version compatibility]
    
    %% SMS Bridge - optional dependency
    sms[pivand-amn-sms-bridge 1.1.7] -->|Provides:| sms-filter[pas_send_sms filter]
    sms -.->|Optional:| case-file[Case File (reminder SMS)]
    case-file -->|Requires:| sms-filter[has_filter('pas_send_sms') check]
    sms -.->|Not required:| dashboard[Dashboard (SMS optional)]
    
    %% Client Portal depends on Core for identity
    portal[pivand-amn-client-portal] -->|Uses:| core-identity[Core client registry]
    portal -->|Integration:| phone-to-wp-user[phone → zdp_ WP user mapping]
    portal -->|Chain:| lookup-known-client[pas_lookup_known_client_name chain]
    portal -.->|Priority:| portal-high[Client portal (highest priority)]
    portal -.->|Case file| case-file-low[Case file (lower priority)]
    portal -.->|Quiz enterprise| quiz-low[Quiz enterprise (lowest priority)]
    
    %% Dashboard is the consumer/integrator
    dashboard[Unified Therapist Dashboard] -->|Consumes:| all-plugins[All plugin APIs]
    dashboard -->|Requires:| core[Core operational]
    dashboard -->|Requires:| case-file[Case File with wrappers]
    dashboard -->|Requires:| quiz[Quiz with authorization]
    dashboard -->|Requires:| sms[SMS optional]
    dashboard -->|Requires:| portal[Portal partial]
    
    %% Dashboard requirements
    core -->|Must be:| core-operational[Operationally active]
    case-file -->|Must have:| auth-wrappers[Authorization wrappers implemented]
    case-file -->|Must not:| list-all[Use of list_all() for therapist views]
    case-file -->|Must:| crypto-checked[Pas_CF_Crypto::is_configured() checks]
    quiz -->|Must respect:| therapist-permissions[Therapist case assignment boundaries]
    portal -->|Partial integration| partial[Partial - identity only]
```

### 2. Plugin-to-Plugin Dependencies

| From Plugin | To Plugin | Dependency Type | Criticality |
|-------------|-----------|----------------|-------------|
| pivand-amn-case-file | pivand-amn-core | **HARD** - `Pas_Core_Crypto` required for encryption | **CRITICAL** - Case File non-functional without Core encryption |
| pivand-amn-case-file | pivand-amn-core | Identity registration (phone → client_identifiers) | **SOFT** - During registry rebuild only |
| pivand-amn-quiz-enterprise | pivand-amn-core | Identity clustering (optional) | **SOFT** - During registry rebuild only |
| pivand-amn-client-portal | pivand-amn-core | Identity integration (phone → WP user) | **SOFT** - During registry rebuild; OTP auth |
| pivand-amn-case-file | pivand-amn-sms-bridge | SMS sending (optional) | **SOFT** - `has_filter('pas_send_sms')` check |
| pivand-amn-client-portal | pivand-amn-case-file | Case/note data access | **SOFT** - Via Core registry and case APIs |
| pivand-amn-quiz-enterprise | pivand-amn-case-file | Case association for therapist access | **SOFT** - Therapist sees quiz results for assigned cases only |
| pivot (any) | WordPress core | ABSOLUTE - WordPress hooks, functions, etc. | **CRITICAL** - All plugins depend on WordPress |

### 3. Core Dependency Details

#### 3.1 `Pas_Core_Crypto` - Hard Dependency

**Case File Requirement**: 
- `build_encrypted_columns()` in `class-case-file.php` lines 68-103
- `encrypt()` calls in: `create()` (line 567), `update()` (line 630), `build_encrypted_columns()` (lines 83, 453)
- `decrypt_or_plain()` calls in: `get_notes()` (line 797), `get_note_history()` (line 697), `get_deleted_notes()` (line 777), `decorate_with_decrypted()` (line 186)
- `is_configured()` check required before any encrypted data access

**Failure Mode** if `Pas_Core_Crypto` not available:
- `encrypt()` throws `RuntimeException: 'افزونه‌ی «هسته مشترک» فعال نیست — رمزنگاری در دسترس نیست.'`
- Encrypted columns stored as empty string or omitted from INSERT (data loss, not error)
- `decrypt_or_plain()` returns raw value as plaintext (legacy behavior)
- Form fields disabled when key missing (per `render_edit_page()` logic)
- Dashboard MUST check `Pas_CF_Crypto::is_configured()` and show compliance warning

**Core Version**: `PAS_CF_VERSION` constant defined in `pivand-amn-case-file.php` - must match plugin version expectations.

#### 3.2 Filter Registry - Soft Dependency

**12 Filter Contracts** registered in core:
- `pas_send_sms` - SMS sending authorization
- Other filters for client lookup, quiz data, etc.

**Dashboard**: Check `has_filter('pas_send_sms')` before enabling SMS features. Other filters may exist - inspect actual registration.

#### 3.3 Table Registry - Soft Dependency

**15 Table Groups** registered in core:
- Includes `pas_cf_cases`, `pas_cf_notes`, etc. (case file tables)
- Used for cross-plugin table identification during registry rebuild

**Dashboard**: No direct dependency, but understanding table ownership helps integration planning.

### 4. Case File Internal Dependencies

#### 4.1 `Pas_Core_Crypto` - Hard Requirement (Reiterated)

**Every encrypted field operation**:
1. Check `Pas_CF_Crypto::is_configured()` first
2. If FALSE → show compliance warning; data may be unreadable/plaintext
3. If TRUE → proceed with encryption/decryption operations

**Example Code Flow** (from `class-case-file.php`):
```php
// In build_encrypted_columns():
if ($plain === '') {
    $out[$column] = ''; // Empty field stores fine
} elseif (Pas_CF_Crypto::is_configured()) {
    $out[$column] = Pas_CF_Crypto::encrypt($plain); // Safe to encrypt
} // If key not configured AND plain not empty → column OMITTED from $row
// → Data silently lost, not stored (no error thrown)

// In decorate_with_decrypted():
foreach (ENCRYPTED_FIELDS as $field) {
    foreach (['', 'spouse_'] as $prefix) {
        $column = $prefix . $field . '_encrypted';
        $encoded = $row->$column ?? '';
        $row->$virtual = ($encoded !== '' && Pas_CF_Crypto::is_configured())
            ? Pas_CF_Crypto::decrypt_or_plain($encoded)
            : ''; // Show empty if key missing
    }
}
```

#### 4.2 `pas_send_sms` Filter - Conditional Dependency

**Case File SMS Reminders**:
- `handle_send_reminder()` in `class-admin.php` checks: `has_filter('pas_send_sms')`
- If no filter registered → `$result = 'sms_not_configured'`
- SMS-bridge plugin must register `pas_send_sms` filter for reminders to work

**Dashboard Check**:
```php
if (!has_filter('pas_send_sms')) {
    // SMS bridge not configured - disable/feature flag
    echo '<div class="notice notice-error">' .
         '<p>ارسال پیامک فعال نیست - لطف افزونهyped-amn-sms-bridge را فعال و کانکفت کنید.</p>' .
         '</div>';
}
```

**If SMS bridge not needed**: Dashboard can safely ignore; reminder features should be disabled/hidden.

#### 4.3 Client Portal Integration - Identity Only

**Portal → Case File Integration**:
- Phone numbers from portal users fed into Core `client_identifiers` table during registry rebuild
- `pas_lookup_known_client_name` chain: portal (highest) ← case-file ← quiz-enterprise
- No direct portal-to-case-file table queries
- Identity resolution happens in Core, not in plugin direct queries

**Dashboard Integration**:
- Portal provides patient-facing interface
- Identity data (client_id, display_name) shared via Core registry
- No dashboard need to query portal tables directly
- `pas_get_client` filter requires `PAS_CF_CAP` or `manage_options`

### 5. Dashboard Dependency Requirements

For the Unified Therapist Dashboard to function, the following must be true:

| Dependency | Requirement | Status |
|------------|-------------|--------|
| **pivand-amn-core** | Must be active and operational | **MANDATORY** - Hosts dashboard, provides identity |
| **pivand-amn-case-file** | Must be active; `Pas_Core_Crypto` available | **MANDATORY** - Primary data source |
| **Authorization wrappers** | `list_for_therapist()`, `current_user_can_access_case()` must be used correctly | **MANDATORY** - Security critical |
| **Pas_CF_Crypto::is_configured()** | Must check before displaying encrypted data | **MANDATORY** - Compliance |
| **pivand-amn-quiz-enterprise** | Optional - quiz data accessible via case assignment | **OPTIONAL** - Feature-dependent |
| **pivand-amn-client-portal** | Optional - patient identity integration | **OPTIONAL** - Identity only |
| **pivand-amn-sms-bridge** | Optional - SMS reminders | **OPTIONAL** - `has_filter('pas_send_sms')` check |
| **WordPress** | Must be operational with proper user roles | **MANDATORY** - All plugins depend on WP |

**Failure Cascade** if Core inactive:
- Case File encryption disabled → all encrypted fields show empty/compliance warning
- `pas_get_client` filter non-functional → client lookup unavailable
- Registry rebuild cannot execute → client identifiers not clustered
- Dashboard identity features disabled

**Failure Cascade** if Case File inactive:
- No case/note data available
- No audit logging
- No partner/couple session data
- Dashboard has no case management functionality

### 6. Dependency Graph Summary

**Critical Path**: Dashboard → Case File → Core (encryption) → Identity Registry

**Most Constrained**: Case File requires `Pas_Core_Crypto` - this is the single biggest technical constraint for dashboard integration. Without Core encryption, case file encrypted data becomes unreadable/plaintext, and dashboard must show compliance warnings.

**Most Flexible**: Quiz enterprise and client portal are relatively independent - can be integrated or excluded based on dashboard feature requirements without breaking core case file functionality.

**Optional Components**: SMS bridge, specific quiz features, portal integration can be added/removed without affecting core case file/dashboard operation (though features may be disabled).

---