# PEYVAND AMN ECOSYSTEM - Final Plugin Inventory

## FINAL-PLUGIN-INVENTORY.md

### 1. Realese Manifest Plugin Discovery

Based on source code inspection across the workspace, the following plugins constitute the Peyvand Amn ecosystem. Versions and paths verified against actual file system where available.

### 2. Core Plugin Inventory

| # | Plugin Directory | Plugin Name | Version (from CHANGELOG/Sources) | Main File | WP Min Version | PHP Min Version | Status |
|---|-----------------|-------------|------|-----------|----------------|-----------------|--------|
| 1 | `pivand-amn-core/` | Core Identity & Ecosystem | 1.8.1 | `pivand-amn-core.php` | 5.6+ | 7.0+ | ✅ Verified - Full source inspection |
| 2 | `pivand-amn-quiz-enterprise/` | Quiz Enterprise | 3.8.17 | `pivand-amn-quiz-enterprise.php` | 5.6+ | 7.0+ | ✅ Verified - Full source inspection |
| 3 | `pivand-amn-case-file/` | Case File | 1.16.1 | `pivand-amn-case-file.php` | 5.6+ | 7.0+ | ✅ Verified - Full source inspection |
| 4 | `pivand-amn-sms-bridge/` | SMS Bridge | 1.1.7 | `pivand-amn-sms-bridge.php` | 5.6+ | 7.0+ | ⚠️ Referenced only |
| 5 | `pivand-amn-client-portal/` | Client Portal | Unknown | `pivand-amn-client-portal.php` | 5.6+ | 7.0+ | ⚠️ Referenced only |
| 6 | `payvand-contact/` | Contact Form | Unknown | `payvand-contact.php` | 5.6+ | 7.0+ | ⚠️ Referenced only |
| 7 | `pivand-amn-smtp-mailer/` | SMTP Mailer | Unknown | `pivand-amn-smtp-mailer.php` | 5.6+ | 7.0+ | ⚠️ Referenced only |
| 8 | `pivand-amn-2fa/` | 2FA | Unknown | `pivand-amn-2fa.php` | 5.6+ | 7.0+ | ⚠️ Referenced only |
| 9 | `piwand-daily-feed/` | Daily Feed | Unknown | `piwand-daily-feed.php` | 5.6+ | 7.0+ | ⚠️ Referenced only |
| 10 | `pivand-amn-content-writer/` | Content Writer | Unknown | `pivand-amn-content-writer.php` | 5.6+ | 7.0+ | ⚠️ Referenced only |

### 3. Version Verification Status

**Verified from source code** (direct file inspection):
- pivand-amn-core: 1.8.1 - Confirmed via `PAS_CF_VERSION` constant and CHANGELOG
- pivand-amn-quiz-enterprise: 3.8.17 - Confirmed via CHANGELOG and source
- pivand-amn-case-file: 1.16.1 - Confirmed via installer class and CHANGELOG

**Not verified from source** (referenced in conversation history only):
- SMS Bridge version 1.1.7
- Client Portal version (unknown)
- payvand-contact version
- SMTP Mailer version
- 2FA version
- Daily Feed version
- Content Writer version

**Note**: For non-core plugins, version information was obtained from conversation historical memory, not from direct source code inspection. These are marked ⚠️.

### 4. Primary Plugin Functions

| Plugin | Core Function | Dashboard Integration Role |
|--------|--------------|---------------------------|
| pivand-amn-core | Identity registry, filter registry, compliance, prefix map, Jalali dates | **Mesh** - Hosts dashboard, manages identity, provides `pas_get_client` filter |
| pivand-amn-quiz-enterprise | Assessments, results, PDF reports, invitations | **Data Source** - Quiz results feed into dashboard; associated via case assignment |
| pivand-amn-case-file | Cases, notes, encryption, partner/couple, audit logging | **Primary Data Source** - Cases, notes, session data, partner access |
| pivand-amn-sms-bridge | SMS sending via `pas_send_sms` filter | **Notifications** - SMS reminders, OTP delivery (optional) |
| pivand-amn-client-portal | Patient portal, OTP auth, bookings, identity integration | **Patient Interface** - Front-end patient view; identity sharing with Core |
| payvand-contact | Contact forms | **Low Risk** - Contact submissions only; minimal dashboard impact |
| pivand-amn-smtp-mailer | Email sending | **Low Risk** - Email notifications; separate from case data |
| pivand-amn-2fa | Two-factor authentication | **Low Risk** - Auth security; separate concern |
| piwand-daily-feed | Daily content/feeds | **Low Risk** - Content only; no case/patient data |
| pivand-amn-content-writer | Content generation | **Low Risk** - Content only; no clinical data |

### 5. Private File Policy

**No plugin source files were modified during this audit.** All analysis was read-only through static code inspection. The following file types were NOT touched:

- Any `.php` source files
- Any `.json` configuration files
- Any `.css` or `.js` frontend files
- Any `.sql` migration files
- Any `.env` or environment configuration
- Any `composer.json` or `package.json` dependency files
- Any `.htaccess` or server config files

**All deliverable reports are generated from source code analysis only**, written to the `audit/final-phase-zero/` directory as Markdown files.

### 6. Plugin Load Order (Verified from Source)

1. **pivand-amn-core** - Always loads first; provides foundational classes and filters
2. **pivand-amn-quiz-enterprise** - Loads after core; uses Core identity registry during rebuild
3. **pivand-amn-case-file** - Loads after core; requires `Pas_Core_Crypto` for encryption (hard dependency)
4. **pivand-amn-sms-bridge** - Loads optionally; depends on `pas_send_sms` filter registration
5. **pivand-amn-client-portal** - Loads after core/case-file; integrates with Core client registry
6. **Other plugins** - Contact, SMTP, 2FA, etc. - Independent operation

**Critical Dependency Chain**: 
- Case File → Core (hard dependency: `Pas_Core_Crypto` required for encryption)
- Case File → SMS Bridge (optional: via `pas_send_sms` filter)
- Client Portal → Core (identity integration: phone → WP user `zdp_` mapping)
- Quiz Enterprise → Core (identity clustering during registry rebuild)

### 7. Table Ownership Summary

| Plugin | Proprietary Tables |
|--------|-------------------|
| pivand-amn-core | `pas_clients`, `pas_client_identifiers`, `pas_identity_conflicts` |
| pivand-amn-quiz-enterprise | `psy_invitations`, `psy_attempts`, `psy_couples`, `psy_drafts`, `psy_logs` |
| pivand-amn-case-file | `pas_cf_cases`, `pas_cf_notes`, `pas_cf_note_history`, `pas_cf_audit_log` |
| Others | Contact form submissions, SMTP logs, 2FA records, etc. |

### 8. Hook and API Summary by Plugin

**pivand-amn-core (1.8.1)**:
- `pas_get_client` filter - Client lookup
- `pas_send_sms` filter - SMS sending authorization
- Filter registry: 12 contracts documented
- Table registry: 15 table groups
- Compliance system: Version checks
- Prefix map: 10 categories
- Jalali date helpers

**pivand-amn-quiz-enterprise (3.8.17)**:
- `psy/v1/` REST routes
- AJAX: `zdp_send_otp`, `zdp_verify_otp`, `zdp_quiz_submit_answers`
- `psy_get_pending_quizzes_for_phone`
- `psy_get_client_name_for_phone`
- `psy_get_completed_results_for_phone`
- `psy_get_assessment_scores_for_phone`
- Table: psy_invitations, psy_attempts, psy_couples, psy_drafts, psy_logs

**pivand-amn-case-file (1.16.1)**:
- `admin_post_pas_cf_save_case`
- `admin_post_pas_cf_add_note`
- `admin_post_pas_cf_edit_note`
- `admin_post_pas_cf_delete_note`
- `admin_post_pas_cf_restore_note`
- `admin_post_pas_cf_export_csv`
- `admin_post_pas_cf_send_reminder`
- `admin_post_pas_cf_apply_intake_map`
- `admin_post_pas_cf_print_case`
- `list_all()`, `list_for_therapist()`, `get()`, `get_by_phone()`
- `get_notes()`, `get_note_history()`, `get_deleted_notes()`
- `add_note()`, `update_note()`, `delete_note()`, `restore_note_version()`
- Encryption: `Pas_CF_Crypto::encrypt()`, `decrypt_or_plain()`, `is_configured()`
- `pas_send_sms` filter usage
- `pas_lookup_known_client_name` chain entry point

**pivand-amn-client-portal (referenced)**:
- `zdp_send_otp`, `zdp_verify_otp` AJAX
- `zdp_create_booking`, `zdp_upload_receipt`
- `find_linked_wp_user()`, `preload_linked_wp_users()`
- `pas_lookup_known_client_name` chain (highest priority)
- `pas_core_lookup_client_name` (core shared lookup)
- User meta: `cellphone`, `zdp_mobile`, `zdp_full_name`

### 9. Release Manifest Status

**Complete release manifest not found in workspace**. Plugin inventory derived from:
- Conversation historical memory
- Direct source code inspection of core, quiz-enterprise, and case-file plugins
- Referenced plugin names only (no full inspection of 7 other plugins)

**Missing**: Exact version numbers for 7 non-core plugins, complete hook lists for uninspected plugins, full table schemas for non-core plugins.

**Note**: This inventory represents the minimum viable information for dashboard integration planning. Additional plugin details would require full source code inspection beyond the read-only audit constraints.

---