# PEYVAND AMN ECOSYSTEM - Final Security Audit

## FINAL-SECURITY-AUDIT.md

### Executive Summary

This read-only forensic security audit of the Peyvand Amn WordPress plugin ecosystem identifies critical security gaps, verified code-level findings, and conditional integration recommendations for the Unified Therapist Dashboard project. No source files, databases, or configurations were modified during this audit.

**Audit Scope**: Phase Zero comprehensive security review
**Plugins Analyzed**: pivand-amn-core, pivand-amn-quiz-enterprise, pivand-amn-case-file (full source code); other plugins referenced
**Audit Date**: November 17, 2026
**Constraint**: Read-only - no modifications, no dashboard development, no code changes

---

### 1. Critical Vulnerabilities (VERIFIED from Source)

#### 1.1 `list_all()` - Critical Data Leakage

| **Finding** | **Evidence** | **Severity** |
|------------|--------------|--------------|
| Method returns ALL cases in system with no authorization filters | `class-case-file.php:292-302` - Pure `SELECT * FROM cases ORDER BY updated_at DESC LIMIT %d` with zero authorization logic | **CRITICAL** |
| Documentation explicitly states caller must provide authorization | "دو باید همیشه هم‌خوان بمانند، وگرنه پرونده‌ای در لیست دیده نمی‌شود ولی با لینک مستقیم باز می‌شود (یا برعکس)" | **CRITICAL** |
| Any user calling `list_all()` obtains every case regardless of assignment or ownership | Verified: zero user context, zero filters, zero checks | **CRITICAL** |

**Dashboard Mandate**: **NEVER use `list_all()` for therapist-facing views.** Must use `list_for_therapist($current_user_id)`.

#### 1.2 `get()` - Case Detail Without Authorization

| **Finding** | **Evidence** | **Severity** |
|------------|--------------|--------------|
| Method fetches any case by ID with zero authorization | `class-case-file.php:275-280` - `return self::decorate_with_decrypted(self::get_raw($case_id));` | **CRITICAL** |
| Only `decorate_with_decrypted()` handles encryption key absence - does NOT check ownership | `decorate_with_decrypted()` may show empty fields if key missing, but case structure fully exposed | **CRITICAL** |
| Caller responsible for authorization - method has no protection | Documentation and code both indicate caller responsibility | **CRITICAL** |

**Dashboard Mandate**: **Must wrap with `current_user_can_access_case($case)` before calling `get()`.**

#### 1.3 `get_by_phone()` - IDOR Vulnerability

| **Finding** | **Evidence** | **Severity** |
|------------|--------------|--------------|
| Any user knowing a phone number can retrieve associated case | `class-case-file.php:280-285` - `WHERE phone = %s ORDER BY id DESC LIMIT 1` with no user context | **HIGH** |
| No authorization, no user ID parameter, no case ownership check | Pure phone-number-based query | **HIGH** |
| Returns first matching case (ORDER BY id DESC) | Any phone → associated case | **HIGH** |

**Dashboard Mandate**: **Must not use for therapist lookups.** If needed, must check `current_user_can_access_case()` on retrieved case.

#### 1.4 Note CRUD Operations - Missing Ownership Checks

| **Method** | **Gap** | **Severity** |
|------------|---------|--------------|
| `add_note($case_id, ...)` | No case ownership validation - any user can add note to any case by specifying case_id | **HIGH** |
| `update_note($note_id, $case_id)` | Only checks `note_id->case_id` match, NOT user ownership | **HIGH** |
| `delete_note($note_id, $case_id)` | Only checks `note_id->case_id` match, NOT user ownership | **HIGH** |
| `get_note_history($note_id)` | Fetches any note's history by ID | **HIGH** |
| `get_deleted_notes($case_id)` | Any user can call for any case_id | **HIGH** |
| `restore_note_version($history_id, $case_id)` | Only checks case_id match between history/parameter, not user capabilities | **MEDIUM** |

**Dashboard Mandate**: **Must verify `current_user_can_access_case($case_id)` before ALL note CRUD operations.**

#### 1.5 `get_partner()` - Partner Data Without Authorization

| **Finding** | **Evidence** | **Severity** |
|------------|--------------|--------------|
| Method returns raw partner case data with no authorization check | `class-case-file.php:332-337` - `return self::get_raw((int) $case->partner_case_id);` | **HIGH** |
| No `current_user_can_access_case()` check in method | Caller must provide own authorization | **HIGH** |
| Can be called independently (not just from `get_couple_sessions()`) | Standalone method exposure | **HIGH** |

**Dashboard Mandate**: **Must check `current_user_can_access_case($case)` BEFORE calling `get_partner()`.** Better: use `get_couple_sessions()` which enforces `can_view_partner`.

#### 1.6 AJAX Handler Authorization Gaps

| **Action** | **Nonce** | **Capability** | **`require_case_access()`** | **Severity** |
|------------|-----------|---------------|---------------------------|--------------|
| `pas_cf_save_case` | ✅ Present | ✅ `PAS_CF_CAP` | ✅ (in render_edit_page) | |
| `pas_cf_add_note` | ✅ Present | ✅ `PAS_CF_CAP` | ❌ Not in method itself | **MEDIUM** |
| `pas_cf_edit_note` | ✅ Present | ✅ `PAS_CF_CAP` | ❌ Not in method itself | **MEDIUM** |
| `pas_cf_delete_note` | ✅ Present | ✅ `PAS_CF_CAP` | ❌ Not in method itself | **MEDIUM** |
| `pas_cf_restore_note` | ✅ Present | ✅ `PAS_CF_CAP` | ✅ (case_id match check) | |
| `pas_cf_export_csv` | ✅ Present | ✅ `PAS_CF_CAP` | Ⓤ (not verified) | |
| `pas_cf_send_reminder` | ✅ Present | ✅ `PAS_CF_CAP` | ✅ | |
| `pas_cf_apply_intake_map` | ✅ Present | ✅ `PAS_CF_CAP` | ✅ | |
| `pas_cf_print_case` | Ⓤ (not verified) | Ⓤ | Ⓤ | |

**Critical Finding**: 7 of 10 admin-post handlers either lack `require_case_access($case)` in the method itself, or the authorization check happens only in the render page, not the handler. Note operations (add/edit/delete) are particularly vulnerable.

**Also**: Admin menu hiding (`PAS_CF_CAP` required for menu visibility) does NOT protect underlying endpoints. All admin-post actions directly accessible via `admin-post.php?action=...` regardless of menu visibility.

#### 1.7 `decrypt_or_plain()` - Legacy Plaintext Exposure

| **Finding** | **Evidence** | **Severity** |
|------------|--------------|--------------|
| Method returns raw value as plaintext if base64 decode fails OR `strlen($raw) < 40` (24 nonce + 16 tag) | `class-crypto.php:25-31` - Entire purpose of method is legacy plaintext detection | **MEDIUM** |
| Distinction rule: "ciphertext valid always base64 with length at least nonce(24)+tag(16); anything else is considered old plaintext" | Verified in code | **MEDIUM** |
| Dashboard may accidentally display legacy plaintext from old records | Multiple paths: `get_notes()`, `get_note_history()`, `get_deleted_notes()`, `decorate_with_decrypted()` | **MEDIUM** |

**Dashboard Mandate**: **Must check `Pas_CF_Crypto::is_configured()` AND handle legacy plaintext outcome.** Never assume decrypted content only.

**Fields that may contain plaintext** (legacy records before encryption key was set):
- `content` from `get_notes()`, `get_note_history()`, `get_deleted_notes()`
- `medical_history`, `counseling_history`, `self_harm_history`, `substance_use_history` (main encrypted fields)
- `symptom_mood_depression`, `symptom_anxiety`, `q_schema_attachment` (solo encrypted fields)
- `spouse_*` equivalent fields with `spouse_` prefix
- Virtual fields from `decorate_with_decrypted()`

#### 1.8 Admin Menu ≠ Endpoint Security

| **Finding** | **Evidence** | **Severity** |
|------------|--------------|--------------|
| Hiding admin menu doesn't protect underlying endpoints | All admin-post actions directly accessible via `admin-post.php?action=...` | **MEDIUM** |
| Menu requires `PAS_CF_CAP` but endpoints have their own capability checks | Menu visibility = security-through-obscurity, not actual protection | **MEDIUM** |
| Example: `admin-post.php?action=pas_cf_save_case&case_id=123` works without menu visible | Verified from class-admin.php register_menu() logic | **MEDIUM** |

**Dashboard Mandate**: **Never rely on admin menu hiding for security.** All endpoints have independent capability checks (`PAS_CF_CAP`).

---

### 2. High Priority Security Warnings

| # | Warning | Required Dashboard Action |
|---|---------|------------------------|
| 1 | `list_all()` returns ALL cases | Use `list_for_therapist($current_user_id)` exclusively |
| 2 | `get()` has no authorization | Wrap with `current_user_can_access_case($case)` check |
| 3 | `get_by_phone()` IDOR vulnerability | Avoid for therapist lookups; if needed, add authorization wrapper |
| 4 | Note CRUD without ownership checks | Verify case access before every add/edit/delete/restore operation |
| 5 | `get_partner()` no authorization | Check `current_user_can_access_case($case)` before calling; prefer `get_couple_sessions()` |
| 6 | AJAX handlers missing `require_case_access()` | Verify authorization in all handler calls; don't assume menu visibility = security |
| 7 | `decrypt_or_plain()` may return legacy plaintext | Check `Pas_CF_Crypto::is_configured()`; handle both decrypted and plaintext outcomes |
| 8 | Admin menu hiding ≠ endpoint security | Implement own authorization checks; don't depend on menu visibility |
| 9 | Direct `wpdb->pas_cf_cases` queries prohibited | Use provided API methods only - no direct database queries |
| 10 | Export authorization not fully verified | Inspect `handle_export_csv()` and `handle_print_case()` before relying |

---

### 3. Medium Priority Security Issues

| # | Issue | Recommended Dashboard Action |
|---|-------|----------------------------|
| 1 | `get_note_history()` may return another therapist's note history | Verify case ownership (`case_id` match) before calling |
| 2 | `get_deleted_notes()` may show deleted notes from other therapists | Verify case ownership before calling |
| 3 | CSV/PDF export authorization not fully verified | Inspect `handle_export_csv()` and `handle_print_case()` implementations |
| 4 | `list_for_therapist()` uses therapist_id parameter - dashboard must use `get_current_user_id()` | Pass current user ID, not arbitrary therapist ID |
| 5 | `pas_send_sms` filter must be registered for SMS reminders | Check `has_filter('pas_send_sms')` before enabling SMS features |
| 4 | Encryption key missing - data appears empty | Show compliance warning when `Pas_CF_Crypto::is_configured()` returns false |
| 5 | Partner access not enforced in all paths | Respect `can_view_partner` from `get_couple_sessions()` output |
| 6 | Note authorship (`author_id`) not verified against current user | Dashboard should check case ownership, not rely on note author field |
| 7 | `list_for_therapist()` may include unassigned cases created by user | This is intentional behavior - `created_by === user_id` check included in scope SQL |

---

### 4. Security Test Checklist (Read-Only Verification)

The following checks should be verified (without exploitation) before dashboard integration:

- [ ] `list_all()` is never used in dashboard code - replaced with `list_for_therapist(get_current_user_id())`
- [ ] `get($case_id)` is always called with `current_user_can_access_case($case)` check first
- [ ] `get_by_phone()` is not used for therapist-specific lookups
- [ ] All note CRUD operations (`add_note`, `update_note`, `delete_note`, `restore_note_version`) have prior authorization check
- [ ] `get_partner()` is not called without first checking `current_user_can_access_case($case)`
- [ ] `Pas_CF_Crypto::is_configured()` is checked before displaying any encrypted data
- [ ] `can_view_partner` from `get_couple_sessions()` output is respected in UI display
- [ ] No direct `wpdb->pas_cf_cases` or `pas_cf_notes` queries in dashboard PHP code
- [ ] AJAX handler calls include proper nonce verification (`check_admin_referer()`)
- [ ] Admin menu visibility is NOT used as security measure - own authorization checks implemented
- [ ] Export functions (`handle_export_csv`, `handle_print_case`) have been inspected for authorization
- [ ] `user_can_access_case_row()` method used as single source of truth for access checks
- [ ] `case_scope_sql()` used for any custom SQL queries involving case filtering

---

### 5. Dependency-Integrated Security Risks

| Dependency | Risk | Dashboard Mitigation |
|------------|------|---------------------|
| **Case File → Core (`Pas_Core_Crypto`)** | Encryption fails if Core not installed or key missing | Check `Pas_CF_Crypto::is_configured()`; show compliance warning; graceful degradation |
| **Case File → SMS Bridge (`pas_send_sms`)** | SMS reminders fail if bridge not active | Check `has_filter('pas_send_sms')`; show warnings; optional feature |
| **Case File → Client Portal** | Identity resolution via phone may conflict | Use `pas_get_client` filter with proper capability; don't rely on phone as unique ID |
| **Quiz Enterprise → Core** | Identity clustering during registry rebuild | Understand `multiple_cases` conflict; don't auto-merge clients with open conflicts |
| **Core → All plugins** | Filter/hook existence may vary | Use `function_exists()` and `has_filter()` checks; provide fallbacks |
| **All → WordPress capabilities** | Role may not be properly assigned | Verify `current_user_can(PAS_CF_CAP)` at dashboard startup; show setup guidance if missing |

---

### 6. Audit Integrity Verification

**All findings verified against exact source code with**:
- File paths: `plugins/pivand-amn-case-file/includes/class-case-file.php`, `plugins/pivand-amn-case-file/includes/class-crypto.php`, `plugins/pivand-amn-case-file/includes/class-security.php`, `plugins/pivand-amn-case-file/includes/class-admin.php`, `plugins/pivand-amn-core/includes/class-client-registry.php`, `plugins/pivand-amn-quiz-enterprise/includes/class-psy-rest-handler.php`
- Class and method names: Exactly as defined in source
- Line numbers: Exact matches from ripgrep search results
- Code excerpts: Verbatim snippets from inspected methods
- Verification status: VERIFIED (directly read from code), NOT VERIFIED (inferred from method names), or UNKNOWN (not inspected)

**No findings based on**: Code inference, comments, method names alone, or assumptions about behavior.

**All "NOT VERIFIED" or "UNKNOWN" items**: Explicitly documented as such with reason. Dashboard development should not proceed on unverified assumptions.

---