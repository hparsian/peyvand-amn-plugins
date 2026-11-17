# PEYVAND AMN ECOSYSTEM - Final Open Issues

## FINAL-OPEN-ISSUES.md

### 1. Unverified Plugin Source Code

| # | Plugin | Issue | Status | Required Action |
|---|--------|-------|--------|-----------------|
| 1 | payvand-contact | Full source code not inspected | ⚠️ UNKNOWN | Report as open issue; do not infer |
| 2 | pivand-amn-smtp-mailer | Full source code not inspected | ⚠️ UNKNOWN | Report as open issue; do not infer |
| 3 | pivand-amn-2fa | Full source code not inspected | ⚠️ UNKNOWN | Report as open issue; do not infer |
| 4 | piwand-daily-feed | Full source code not inspected | ⚠️ UNKNOWN | Report as open issue; do not infer |
| 5 | pivand-amn-content-writer | Full source code not inspected | ⚠️ UNKNOWN | Report as open issue; do not infer |
| 6 | pivand-amn-sms-bridge | Version and full implementation not verified against file system | ⚠️ Referenced only | Inspect source if SMS features needed |
| 7 | pivand-amn-client-portal | Full source code not inspected in detail | ⚠️ Referenced only | Inspect if portal integration needed |

**Policy**: For unverified plugins, report as UNKNOWN and do not infer security properties or API behaviors. Dashboard integration should not depend on unverified plugin features.

### 2. Code Sections Not Fully Inspected

| # | Plugin/File | Section | Reason | Status |
|---|-------------|---------|--------|--------|
| 1 | `class-admin.php` | `handle_export_csv()` full implementation | Code too large; truncated at 20,000 token limit | ❓ UNKNOWN |
| 2 | `class-admin.php` | `handle_print_case()` full implementation | Code too large; truncated at 20,000 token limit | ❓ UNKNOWN |
| 3 | `pivand-amn-core.php` | Full filter registry implementation | 12 contracts documented but some details unspecified | ⚠️ PARTIAL |
| 4 | `pivand-amn-core.php` | Full client registry rebuild algorithm | Complex multi-source clustering; not fully traced | ⚠️ PARTIAL |
| 5 | `pivand-amn-quiz-enterprise` | Full REST route permission callbacks | Registered but callbacks not inspected | ❓ UNKNOWN |
| 6 | `pivand-amn-quiz-enterprise` | Full AJAX security (nonces, capabilities) | Actions present but security details unspecified | ❓ UNKNOWN |
| 7 | `pivand-amn-sms-bridge` | SMS provider APIs, credential management | Referenced only; no source inspection | ❓ UNKNOWN |
| 8 | `pivand-amn-client-portal` | Full OTP authentication flow | Referenced only; partial inspection only | ❓ UNKNOWN |
| 9 | All non-core plugins | Database schema, API hooks, security properties | Not source-code inspected | ❓ UNKNOWN |

**Policy**: All UNKNOWN items must be explicitly reported. Dashboard development must not proceed on unverified assumptions about these sections.

### 3. API Contract Gaps

| # | API / Hook | Gap | Impact | Required Investigation |
|---|------------|-----|--------|------------------------|
| 1 | `handle_export_csv()` | Full implementation not inspected | Export authorization, data minimization, IDOR risk | Inspect source code or report as limitation |
| 2 | `handle_print_case()` | Full implementation not inspected | PDF export authorization, data leakage risk | Inspect source code or report as limitation |
| 3 | `psy_get_pending_quizzes_for_phone()` | Full function signature and authorization | Quiz data access; therapist permissions | Inspect source or report |
| 4 | `psy_get_client_name_for_phone()` | Full function signature and authorization | Client name lookup; phone as identity | Inspect source or report |
| 5 | `zdp_get_intake_for_phone` filter | All consumers and behavior | Case-file to dashboard data flow | Inspect source or report |
| 6 | `pas_lookup_known_client_name` | Full chain behavior beyond priority | Identity resolution across plugins | Inspect source or report |
| 7 | `psy_get_completed_results_for_phone()` | Full function and authorization | Quiz results access; therapist permissions | Inspect source or report |
| 8 | `psy_get_assessment_scores_for_phone()` | Full function and assessment calculation | Quiz scoring display | Inspect source or report |

**Policy**: API gaps must be reported. Dashboard development should not assume API behavior for these uninspected functions.

### 3. Database Schema Gaps

| # | Table | Gap | Impact | Required Investigation |
|---|-------|-----|--------|------------------------|
| 1 | `pas_cf_cases` | Full column semantics for some text fields | Note which fields are encrypted vs plaintext vs deprecated | Inspect installer or report |
| 2 | `pas_cf_notes` | `content_encrypted` decryption behavior with missing key | Dashboard display logic | Verify from crypto class |
| 3 | `pas_cf_note_history` | Relationship to main notes table and restoration logic | Version history integrity | Verify from code analysis |
| 4 | `pas_cf_audit_log` | Complete list of action types and their meaning | Audit trail interpretation | Verify from call sites |
| 5 | Quiz tables (`psy_*` ) | Full schema and data semantics | Quiz data display in dashboard | Inspect quiz enterprise source |
| 7 | Portal usermeta | Full list of portal-specific meta keys and meanings | Patient identity tracking | Inspect portal source or report |

**Policy**: Database schema gaps must be reported. Dashboard should not assume column meanings or relationships for uninspected tables.

### 4. Security Verification Gaps

| # | Security Area | Gap | Potential Risk | Required Action |
|---|---------------|-----|----------------|-----------------|
| 1 | Export authorization | `handle_export_csv()` and `handle_print_case()` not fully inspected | Data leakage, unauthorized exports | Inspect source; add dashboard-level authorization as safeguard |
| 2 | AJAX handler gaps | 7 of 10 admin-post handlers missing `require_case_access()` in method | Unauthorized case/note operations | Implement dashboard-level wrappers as safeguard |
| 3 | Export data minimization | Not verified what fields are included in CSV/PDF exports | Exposure of sensitive clinical data | Dashboard should minimize exported fields as best practice |
| 4 | `decrypt_or_plain()` legacy handling | Behavior verified but dashboard implementation not specified | Display of legacy plaintext vs protected data | Dashboard must handle both outcomes |
| 5 | Partner data boundaries | `get_partner()` lacks authorization; only `get_couple_sessions()` enforces | Partner data exposure risk | Dashboard must check `can_view_partner` from `get_couple_sessions()` |
| 6 | `list_all()` usage | Documented as critical gap but full dashboard code not inspected | Security bypass risk | Dashboard mandate: never use `list_all()` |
| 7 | Direct DB queries | Assumed absent but not verified across all dashboard code paths | IDOR, data leakage | Enforce "no direct wpdb->pas_cf_* queries" rule |

**Policy**: All security gaps must be reported. Dashboard development must implement the verified security wrappers as minimum protection, with additional safeguards for gap areas.

### 5. Integration Decision Uncertainties

| # | Integration Area | Uncertainty | Potential Impact | Recommended Action |
|---|----------------|-------------|------------------|--------------------|
| 1 | Quiz enterprise integration | Full API and authorization not inspected | Dashboard may miss quiz data features or expose quiz data unauthorized | Inspect quiz enterprise source; design with feature flags |
| 2 | Client portal integration | Identity resolution beyond phone lookup not fully verified | Patient identity errors or data mismatch | Design with identity verification steps; show conflicts |
| 3 | SMS bridge integration | Full SMS provider implementation not verified | SMS reminders may not work as expected; dashboard features broken | Design with `has_filter('pas_send_sms')` check and graceful degradation |
| 4 | Other plugin integration | 7 plugins not source-inspected | Unexpected interactions or missing features | Design with modular integration; test each plugin separately |
| 5 | Cross-plugin data mapping | Assumptions made about data flow between plugins | Data loss or corruption at integration points | Validate data flow with each plugin; add validation checks |
| 6 | Encryption key management | Key absence behavior verified but dashboard UI not designed | User experience issues when key missing | Design compliance warning UI; test with key disabled |
| 6 | Role-based access implementation | Capability matrix verified but dashboard UI implementation not specified | Users may see/access features they shouldn't | Design UI with role checks at each entry point |

**Policy**: All uncertainties must be reported. Dashboard development should use feature flags, capability checks, and graceful degradation to handle unknown areas.

### 6. Required Pre-Integration Investigations

Before dashboard development can proceed safely, the following must be completed:

- [ ] Inspect `handle_export_csv()` and `handle_print_case()` full implementations
- [ ] Inspect quiz enterprise REST routes and AJAX security fully
- [ ] Inspect client portal OTP authentication and booking APIs fully
- [ ] Inspect SMS bridge provider APIs and credential management
- [ ] Verify `Pas_Core_Crypto` implementation details (key storage, rotation, etc.)
- [ ] Confirm `PAS_CF_VERSION` constant value and `pas_cf_db_version` option behavior
- [ ] Test `Pas_CF_Crypto::is_configured()` and `decrypt_or_plain()` with key disabled
- [ ] Test `current_user_can_access_case()` with various user role combinations
- [ ] Verify `list_for_therapist()` behavior with various therapist IDs and case distributions
- [ ] Inspect `pas_get_client` filter behavior with various query formats
- [ ] Validate conflict detection logic from Core registry rebuild

**Policy**: Dashboard development should not begin until these investigations are complete and their findings incorporated. The CONDITIONAL GO recommendation depends on these being addressed.

### 7. Summary of Open Issues Count

| Category | Count | Status |
|----------|-------|--------|
| Unverified plugin source | 7 | MUST report; do not infer |
| Code sections not inspected | 9 | MUST report; do not assume |
| API contract gaps | 8 | MUST investigate before dashboard |
| Database schema gaps | 7 | MUST investigate before dashboard |
| Security verification gaps | 7 | MUST address in dashboard design |
| Integration uncertainties | 6 | MUST resolve before dashboard |
| **Total** | **44** | **All must be addressed or explicitly documented as out-of-scope** |

---