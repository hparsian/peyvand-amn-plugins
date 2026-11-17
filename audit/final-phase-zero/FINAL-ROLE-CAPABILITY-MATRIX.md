# PEYVAND AMN ECOSYSTEM - Final Role and Capability Matrix

## FINAL-ROLE-CAPABILITY-MATRIX.md

### 1. WordPress Role Definitions (from Source Code)

#### 1.1 Administrator

**Capabilities** (from `class-security.php` and general WordPress):
- `manage_options` - Full admin access
- `PAS_CF_CAP` - Therapist-specific capability (added by `grant_capability_to_administrators()`)
- `PAS_CF_CAP_ALL` - All cases access (added by same method)

**Access Scope** (verified from source):
- `grant_capability_to_administrators()` adds both `PAS_CF_CAP` and `PAS_CF_CAP_ALL` to administrator role
- Administrators have `current_user_can_view_all()` → TRUE always
- Can access all cases regardless of assignment
- Method: `$role->has_cap(PAS_CF_CAP_ALL)` → always true

**Dashboard Implications**:
- Administrators see all cases via `list_all()` (no filtering needed)
- Can access any case via `get()` without additional checks
- Can add notes to any case
- Should still be restricted by dashboard UI/UX design even with full access

#### 1.2 Therapist (Custom Role `pas_cf_therapist`)

**Capabilities** (from `ensure_therapist_role()` in `class-security.php`):
- `PAS_CF_CAP` - Therapist-specific capability
- `read` - Required to access wp-admin (WordPress core)

**Role Creation** (from `ensure_therapist_role()`):
```php
add_role('pas_cf_therapist', 'درمانگر', [
    'read'     => true,
    PAS_CF_CAP => true,
]);
```

**Important**: This role does NOT get `PAS_CF_CAP_ALL` - admins have that, therapists don't.

**Access Scope** (verified from `user_can_access_case_row()`):
- `assigned_therapist === $user_id` → TRUE (therapist sees their own cases)
- `assigned_therapist === 0 AND created_by === $user_id` → TRUE (legacy unassigned cases created by this user)
- Otherwise → FALSE

**`current_user_can_access()`**: 
```php
public static function current_user_can_access(): bool
{
    return current_user_can(PAS_CF_CAP);
}
```
- Returns TRUE if user has `PAS_CF_CAP` (therapist or admin)
- Does NOT distinguish between admin/therapist scope

**`current_user_can_view_all()`**:
```php
public static function current_user_can_view_all(): bool
{
    return current_user_can(PAS_CF_CAP_ALL);
}
```
- Only TRUE for admins with `PAS_CF_CAP_ALL`
- FALSE for therapists (even though they have `PAS_CF_CAP`)

#### 1.3 Reception Operator (No Custom WP Role)

**From conversation historical memory and source analysis**:
- No custom WordPress role defined for reception operator
- Access limited to specific admin-post actions with capability checks
- Typically would have `PAS_CF_CAP` assigned, or operate through admin with proper delegation

**Access Scope** (from audit findings):
- Can view cases visible in list (filtered by `list_for_therapist()`)
- Can perform admin-post actions with proper nonce + capability
- Cannot access restricted case data without proper assignment
- SMS reminder configuration dependent on `pas_send_sms` filter

**Dashboard UI Restrictions** (from security audit):
- Should only see assigned cases + self-created cases
- Should not see partner data without `can_view_partner` enforcement
- Should not have access to encrypted fields without key compliance check

### 2. Capability Matrix (Verified from Source)

| Action | Required Capability | Therapist (pas_cf_therapist) | Administrator |
|--------|--------------------|------------------------------|---------------|
| View case list | `PAS_CF_CAP` | ✅ YES (via `list_for_therapist()`) | ✅ YES (all cases) |
| View case detail (`get()`) | `PAS_CF_CAP` + `current_user_can_access_case()` | ✅ YES (if assigned or self-created) | ✅ YES (all cases) |
| Add note | `PAS_CF_CAP` + case access check | ✅ YES (if case assigned/self-created) | ✅ YES (all cases) |
| Edit note | `PAS_CF_CAP` + case access + note ownership | ✅ YES (if case assigned/self-created + owns note) | ✅ YES (all cases) |
| Delete note | `PAS_CF_CAP` + case access + note ownership | ✅ YES (if case assigned/self-created + owns note) | ✅ YES (all cases) |
| View partner sessions | `PAS_CF_CAP` + `can_view_partner` | ✅ Conditional (if access to both cases) | ✅ YES (all cases) |
| Send reminder | `PAS_CF_CAP` + `has_filter('pas_send_sms')` | ✅ If configured | ✅ If configured |
| Export CSV | `PAS_CF_CAP` | ✅ (cases in current list view) | ✅ (all cases) |
| Access client portal | `PAS_CF_CAP` or portal auth | ✅ Depends on setup | ✅ (admin) |
| View quiz results | `PAS_CF_CAP` + case assignment | ✅ Only assigned cases | ✅ (all cases) |

### 3. Therapist Permission Boundaries (VERIFIED)

| Permission | Granted? | Evidence | Dashboard Implication |
|------------|----------|----------|----------------------|
| View own assigned cases | ✅ YES | `list_for_therapist(get_current_user_id())` works | Standard therapist view |
| View own self-created cases (unassigned) | ✅ YES | `created_by === user_id` check in scope SQL | Included in therapist view |
| View other therapist's cases | ❌ NO | `assigned_therapist !== $user_id` fails access check | Must enforce |
| View unassigned cases created by self | ✅ YES | `assigned_therapist = 0 AND created_by = user_id` included | Part of standard view |
| View all cases (admin override) | ❌ NO (without `PAS_CF_CAP_ALL`) | `current_user_can_view_all()` returns FALSE for therapists | Must not bypass |
| View partner sessions | ✅ Conditional | `get_couple_sessions()` enforces `can_view_partner` | Respect flag in UI |
| Add notes to any case | ❌ NO | Only cases user is assigned to or created | Must verify case access first |
| Edit/delete any note | ❌ NO | Only notes in cases user has access to | Verify case ownership |
| Access client portal | ✅ Depends | Via `pas_get_client` filter with `PAS_CF_CAP` | Requires proper capability |
| View quiz results | ✅ Only assigned | Through case assignment association | Standard boundary |
| Change case therapist | ❌ NO (without admin) | Only admins with `PAS_CF_CAP_ALL` can reassign | Dashboard must not enable this |
| View audit logs | ✅ Limited | `Pas_CF_Audit_Log::for_case($case_id)` - case-specific | Case-by-case access |

### 4. Reception Operator Permissions (From Audit Findings)

| Permission | Status | Evidence |
|------------|--------|----------|
| View assigned cases | ✅ Limited | Only cases in `list_for_therapist(get_current_user_id())` - filtered view |
| View all cases | ❌ NO | No `PAS_CF_CAP_ALL`; limited to visible list |
| Add notes | ❌ NO | Requires case ownership + `PAS_CF_CAP` - not typically assigned to reception |
| Edit/delete notes | ❌ NO | Same as above |
| Send SMS reminders | ⚠️ Conditional | Requires `has_filter('pas_send_sms')` + `PAS_CF_CAP` |
| Export CSV | ⚠️ Limited | Only cases currently visible in list view |
| Access client portal | ✅ Partial | Identity lookup via `pas_get_client` if capability assigned |
| View audit logs | ❌ NO | Case-specific access only via `Pas_CF_Audit_Log::for_case()` |

**Key Boundary**: Reception operator should NOT have access to:
- Another therapist's cases
- Partner data without `can_view_partner` enforcement
- Encrypted fields without key compliance check
- Audit logs of other users' actions
- Quiz results outside assigned cases

### 5. Unified Role Rules (Dashboard Design)

**Rule 1**: Therapist sees only cases where:
- `assigned_therapist === current_user_id` OR
- `assigned_therapist === 0 AND created_by === current_user_id`

**Rule 2**: Reception operator sees only cases in current list view (filtered by same logic as therapist, but typically with different UI permissions)

**Rule 3**: No role (therapist or reception) can:
- Use `list_all()` - returns ALL cases (security bypass)
- Directly query `wpdb->pas_cf_cases` - prohibited
- Access cases without proper assignment - enforced by `current_user_can_access_case()`
- Change case assignment - only admins with `PAS_CF_CAP_ALL`
- Access another therapist's notes - enforced by case ownership checks
- Display encrypted data without key check - `Pas_CF_Crypto::is_configured()` required

**Rule 4**: Admin (with `PAS_CF_CAP_ALL`) can:
- See all cases
- Access any case detail
- Reassign therapists
- All API operations without additional checks (but dashboard should still apply UI restrictions)

**Rule 5**: Dashboard must implement UI-level restrictions regardless of WP capabilities:
- Hide features from users who shouldn't see them
- Show compliance warnings for encryption key status
- Respect `can_view_partner` even when capability technically allows
- Never use `list_all()` regardless of user role

### 6. Capability Verification Checklist

Before dashboard integration, verify:

- [ ] `PAS_CF_CAP` is defined and available in WordPress
- [ ] Therapist role has `PAS_CF_CAP` (via `ensure_therapist_role()`)
- [ ] Administrator role has `PAS_CF_CAP` and `PAS_CF_CAP_ALL` (via `grant_capability_to_administrators()`)
- [ ] `current_user_can(PAS_CF_CAP)` returns expected values for test users
- [ ] `current_user_can_view_all()` returns TRUE for admins, FALSE for therapists
- [ ] `current_user_can_access_case($case)` works correctly for test cases
- [ ] `list_for_therapist(get_current_user_id())` returns expected cases for test users
- [ ] `Pas_CF_Crypto::is_configured()` returns expected value (true/false based on key setup)
- [ ] `has_filter('pas_send_sms')` returns expected value (based on SMS bridge activation)
- [ ] All admin-post handlers verify nonce + capability before processing

---