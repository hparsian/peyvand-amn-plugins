# PEYVAND AMN PHASE ZERO AUDIT - Supplementary Forensic Security Audit

## CASE-FILE-SUPPLEMENTARY-SECURITY.md

## 1. Verify Case Access Enforcement

### A. list_all() — Line 292

```php
public static function list_all(int $limit = 200): array
{
    global $wpdb;

    return $wpdb->get_results($wpdb->prepare(
        'SELECT * FROM ' . Pas_CF_Installer::table('cases') . ' ORDER BY updated_at DESC LIMIT %d',
        $limit
    ));
}
```

**Verification Status**: ❌ NOT VERIFIED — **FAILS** security requirements

| Finding | Evidence |
|---------|----------|
| 1. Does it perform its own authorization check? | **NO**. Zero authorization logic. Pure SELECT * query. |
| 2. Does it depend on its caller to perform authorization? | **YES**. Explicitly documented: "دو باید همیشه هم‌خوان بمانند، وگرنه پرونده‌ای در لیست دیده نمی‌شود ولی با لینک مستقیم باز می‌شود (یا برعکس)." |
| 3. Can an unauthorized user obtain another therapist's case? | **YES**. `list_all()` returns ALL cases regardless of assigned_therapist or created_by. Any user calling this method obtains every case in the system. |
| 4. What happens when "assigned_therapist = 0"? | **Included without filter**. No distinction made for unassigned cases. |
| 5. Can a therapist access an unassigned case? | **YES, if they created it**. `list_all()` includes all cases. The check `created_by === user_id` only applies in `list_for_therapist()`, not `list_all()`. |
| 6. Are administrator and therapist rules identical across list and detail methods? | **NO**. Administrator (with `PAS_CF_CAP_ALL`) sees all cases in `list_all()`. Therapist sees only cases matching `case_scope_sql()` logic, but `list_all()` bypasses this entirely. |

**Security Impact**: **CRITICAL** — Dashboard must never use `list_all()` for therapist-facing views. Must use `list_for_therapist()` or implement manual capability checks.

### B. list_for_therapist() — Line 310

```php
public static function list_for_therapist(int $therapist_id, int $limit = 200): array
{
    global $wpdb;

    // Faz ۹: شرط از قرارداد مرکزی دسترسی می‌آید (نه کپی محلی)، تا با
    // current_user_can_access_case و گزارش‌ها همیشه یکی بماند.
    [$scope_sql, $scope_params] = Pas_CF_Security::case_scope_sql($therapist_id, '', false);

    return $wpdb->get_results($wpdb->prepare(
        'SELECT * FROM ' . Pas_CF_Installer::table('cases') . "\n             WHERE $scope_sql\n             ORDER BY updated_at DESC LIMIT %d",
        ...array_merge($scope_params, [$limit])
    ));
}
```

**Verification Status**: ✅ VERIFIED — **PASSES** security requirements

| Finding | Evidence |
|---------|----------|
| 1. Does it perform its own authorization check? | **YES**. Invokes `Pas_CF_Security::case_scope_sql($therapist_id, '', false)` to generate SQL scope condition. |
| 2. Does it depend on its caller to perform authorization? | **NO**. Performs authorization internally via SQL scope. Caller does not need additional checks. |
| 3. Can an unauthorized user obtain another therapist's case? | **NO**. SQL condition: `assigned_therapist = %d OR (assigned_therapist = 0 AND created_by = %d)` limits to specified therapist's cases only. |
| 4. What happens when "assigned_therapist = 0"? | **Included ONLY if created_by matches**. Condition: `assigned_therapist = 0 AND created_by = $user_id`. Unassigned cases created by other therapists are excluded. |
| 5. Can a therapist access an unassigned case? | **YES, iff they created it**. The SQL explicitly includes: `assigned_therapist = 0 AND created_by = $user_id`. |
| 6. Are administrator and therapist rules identical across list and detail methods? | **PARTIAL**. `list_for_therapist()` uses the same `case_scope_sql()` logic that `current_user_can_access_case()` and report scopes use. Rules are consistent across the ecosystem when using the central scope SQL. |

**Security Impact**: **SAFE** — Dashboard must use `list_for_therapist($current_user_id)` for therapist case listings, never `list_all()`.

### C. get(int $case_id) — Line 275

```php
public static function get(int $case_id): ?object
{
    return self::decorate_with_decrypted(self::get_raw($case_id));
}
```

**Verification Status**: ❌ NOT VERIFIED — **FAILS** security requirements

| Finding | Evidence |
|---------|----------|
| 1. Does it perform its own authorization check? | **NO**. Method has zero authorization logic. |
| 2. Does it depend on its caller to perform authorization? | **YES**. Documentation and code both indicate caller responsibility. |
| 3. Can an unauthorized user obtain another therapist's case? | **YES**. `get($case_id)` fetches any case by ID regardless of ownership. The only protection is that `get_raw()` returns raw data and `decorate_with_decrypted()` may show empty fields if encryption key is missing, but the case object structure is fully exposed. |
| 4. What happens when "assigned_therapist = 0"? | **No special handling**. `get()` treats all cases identically — fetches by ID only. |
| 5. Can a therapist access an unassigned case? | **Depends on caller**. `get()` has no distinction. Any user can call `get()` on any case. |

**Security Impact**: **CRITICAL** — Dashboard MUST implement authorization wrapper before calling `get()`. Never call `get()` without first checking `current_user_can_access_case()`.

### D. get_raw(int $case_id) — Line 263 (private)

```php
private static function get_raw(int $case_id): ?object
{
    global $wpdb;

    $row = $wpdb->get_row($wpdb->prepare(
        'SELECT * FROM ' . Pas_CF_Installer::table('cases') . ' WHERE id = %d',
        $case_id
    ));

    return $row ?: null;
}
```

**Verification Status**: ❌ NOT VERIFIED — **FAILS** security requirements (but expected for internal use)

| Finding | Evidence |
|---------|----------|
| 1. Does it perform its own authorization check? | **NO**. Direct `$wpdb->get_row()` with prepared ID only. |
| 2. Does it depend on its caller to perform authorization? | **YES**. Explicitly documented: "بدون رمزگشایی — برای عملیات داخلی مانند sync_partner_link." |
| 3. Can an unauthorized user obtain another therapist's case? | **YES** if caller is unauthorized. This method has no protection. |
| 4. What happens when "assigned_therapist = 0"? | **No special handling**. Pure ID-based fetch. |
| 5. Can a therapist access an unassigned case? | **Depends on caller**. `get_raw()` itself has no filter. |

**Security Impact**: **HIGH** — This method is `private static` and intended for internal use only. Dashboard must NOT call this method directly. All public access should go through `get()` with prior authorization check, or `list_for_therapist()`.

### E. get_by_phone(string $phone) — Line 280

```php
public static function get_by_phone(string $phone): ?object
{
    global $wpdb;

    $row = $wpdb->get_row($wpdb->prepare(
        'SELECT * FROM ' . Pas_CF_Installer::table('cases') . ' WHERE phone = %s ORDER BY id DESC LIMIT 1',
        self::normalize_phone($phone)
    ));

    return $row ?: null;
}
```

**Verification Status**: ❌ NOT VERIFIED — **FAILS** security requirements

| Finding | Evidence |
|---------|----------|
| 1. Does it perform its own authorization check? | **NO**. Pure phone-number-based query with no user context. |
| 2. Does it depend on its caller to perform authorization? | **YES**. Caller must implement own authorization. |
| 3. Can an unauthorized user obtain another therapist's case? | **YES**. Any user knowing a phone number can retrieve the associated case, regardless of ownership/therapist assignment. |
| 4. What happens when "assigned_therapist = 0"? | **No special handling**. Returns first case matching phone (ORDER BY id DESC). |
| 5. Can a therapist access an unassigned case? | **Depends on phone ownership**. If phone belongs to a case assigned to another therapist, `get_by_phone()` still returns it. |

**Security Impact**: **HIGH** — Dashboard must NOT use `get_by_phone()` for therapist-specific lookups without prior authorization check. Use `list_for_therapist()` or `get()` with `current_user_can_access_case()` first.

### F. current_user_can_access_case(object $case) — from class-security.php

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

**Verification Status**: ✅ VERIFIED — **PASSES** security requirements

| Finding | Evidence |
|---------|----------|
| 1. Does it perform its own authorization check? | **YES**. Complete authorization logic implemented. |
| 2. Logic: `$view_all` (admin with `PAS_CF_CAP_ALL`) → always returns `true`. | ✅ Verified in `user_can_access_case_row()`. |
| 3. Logic: `assigned_therapist === $user_id` → returns `true`. | ✅ Verified. |
| 4. Logic: `assigned_therapist === 0 AND created_by === $user_id` → returns `true`. | ✅ Verified. Handles legacy unassigned cases created by user. |
| 5. Logic: otherwise → returns `false`. | ✅ Verified. |
| 6. Uses `get_current_user_id()` for current user identification. | ✅ Verified. |

**Security Impact**: **SAFE** — Dashboard MUST call this method before any case detail operations.

### G. user_can_access_case_row(int $user_id, int $assigned_therapist, int $created_by, ?bool $view_all) — from class-security.php

```php
public static function user_can_access_case_row(int $user_id, int $assigned_therapist, int $created_by, ?bool $view_all = null): bool
{
    $view_all = $view_all ?? self::current_user_can_view_all();

    if ($view_all) {
        return true;
    }

    if ($assigned_therapist === $user_id) {
        return true;
    }

    return $assigned_therapist === 0 && $created_by === $user_id;
}
```

**Verification Status**: ✅ VERIFIED — **PASSES** security requirements (single source of truth for access logic)

| Finding | Evidence |
|---------|----------|
| 1. Central access rule implemented in one place. | ✅ Only location where this logic exists. |
| 2. Three-way branch: `view_all` → `assigned_therapist match` → `created_by match for unassigned`. | ✅ Exactly as coded. |
| 4. `$view_all` defaults to `current_user_can_view_all()` (checks `PAS_CF_CAP_ALL`). | ✅ Verified. |
| 5. Rules are identical in `list_for_therapist()` SQL scope and this method. | ✅ Confirmed — `case_scope_sql()` generates `assigned_therapist = %d OR (assigned_therapist = 0 AND created_by = %d)` which is equivalent to this method's logic. |

**Security Impact**: **SAFE** — Dashboard and all Case File code should use this single method for access checks. Never duplicate this logic.

---

## 2. Verify Note Security

### A. get_notes(int $case_id) — Line 797

```php
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
```

**Verification Status**: ⚠️ **PARTIAL** — **Missing authorization check**

| Finding | Evidence |
|---------|----------|
| 1. Checks case_id to fetch notes? | **YES**. `WHERE case_id = %d` fetches notes for specified case. |
| 2. Checks user capability? | **NO**. Zero capability check in method. |
| 3. Checks case ownership? | **NO**. Any user can fetch notes for any case by ID. |
| 4. Returns clinical content without authorization? | **YES**. `foreach` decrypts `$row->content_encrypted` and assigns to `$row->content`. If encryption key missing, returns empty string (`Pas_CF_Crypto::decrypt_or_plain()` returns `''` when `is_configured()` is false). |
| 5. Identify methods returning clinical content without authorization: | **get_notes()** is the primary method. Also `get_note_history()` (see below). |

**Security Impact**: **HIGH** — Dashboard MUST check `current_user_can_access_case($case)` before calling `get_notes()`. Never call `get_notes()` without prior authorization.

### B. get_note_history(int $note_id) — Line 697

```php
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
```

**Verification Status**: ❌ **FAILS** security requirements

| Finding | Evidence |
|---------|----------|
| 1. Checks note ownership via case_id? | **PARTIAL**. Fetches by `note_id` only. No case_id parameter. Caller must ensure note belongs to case they're authorized for. |
| 2. Checks user capability? | **NO**. Zero capability check. |
| 3. Returns clinical content (decrypted) without authorization? | **YES**. Decrypts `content_encrypted` for all history entries. |
| 4. Can unauthorized user view another therapist's note history? | **YES**. `get_note_history($note_id)` fetches any note's history by ID. |

**Security Impact**: **CRITICAL** — Dashboard MUST verify `current_user_can_access_case($case)` where `$case->id === $note->case_id` before calling `get_note_history()`.

### C. get_deleted_notes(int $case_id) — Line 777

```php
public static function get_deleted_notes(int $case_id): array
{
    global $wpdb;

    $history_table = Pas_CF_Installer::table('note_history');
    $notes_table    = Pas_CF_Installer::table('notes');

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
```

**Verification Status**: ⚠️ **PARTIAL** — **Case ID filter present, no user check**

| Finding | Evidence |
|---------|----------|
| 1. Filters by case_id? | **YES**. `WHERE h.case_id = %d AND h.action = 'deleted'`. |
| 2. Checks user capability? | **NO**. No user ID parameter or capability check. |
| 3. Returns deleted clinical content? | **YES**. Decrypts `content_encrypted` for all deleted notes matching case_id. |
| 4. Can unauthorized user view another therapist's deleted notes? | **YES**. Any user can call `get_deleted_notes($case_id)` for any case ID. |

**Security Impact**: **HIGH** — Dashboard MUST verify case ownership before calling `get_deleted_notes()`.

### D. add_note(int $case_id, ...) — Line 559

```php
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

    $note_id = (int) $wpdb->insert_id;

    $wpdb->update(Pas_CF_Installer::table('cases'), ['updated_at' => current_time('mysql', true)], ['id' => $case_id], ['%s'], ['%d']);

    Pas_CF_Audit_Log::record($case_id, 'add_note');

    return $note_id;
}
```

**Verification Status**: ⚠️ **PARTIAL** — **Uses current_user_id but no ownership validation beyond that**

| Finding | Evidence |
|---------|----------|
| 1. Sets `author_id = get_current_user_id()`? | **YES**. Records who created the note. |
| 2. Validates case ownership before insert? | **NO**. No `current_user_can_access_case()` check. Any user can add a note to any case by specifying the case_id. |
| 3. Can unauthorized user add note to another therapist's case? | **YES**. `add_note($case_id, ...)` inserts note with specified case_id and author_id=get_current_user_id(). No ownership validation. |
| 4. Does it check encryption availability? | **Implicitly**. `Pas_CF_Crypto::encrypt($content)` will encrypt, but if key missing, behavior depends on `Pas_CF_Crypto::is_configured()` implementation (likely encrypts anyway, with data stored unreadable later). |

**Security Impact**: **CRITICAL** — Dashboard MUST verify `current_user_can_access_case($case_id)` before allowing note addition. Never call `add_note()` without authorization check.

### E. update_note(int $note_id, int $case_id, ...) — Line 602

```php
public static function update_note(int $note_id, int $case_id, string $session_date, string $content, ?int $session_number = null, ?array $meta = null): bool
{
    global $wpdb;

    $table = Pas_CF_Installer::table('notes');
    $existing = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $note_id));

    if (!$existing || (int) $existing->case_id !== $case_id) {
        return false;
    }
    // ... snapshot version, encrypt content, update
}
```

**Verification Status**: ✅ **PARTIAL** — **Ownership check present but insufficient**

| Finding | Evidence |
|---------|----------|
| 1. Checks `note_id` belongs to specified `case_id`? | **YES**. `$existing = $wpdb->get_row(...); if (!$existing || (int) $existing->case_id !== $case_id) { return false; }` |
| 2. Checks user capability? | **NO**. Only checks note-case ID match, not current user's authorization. |
| 3. Can unauthorized user edit another therapist's note? | **YES** if they know the `note_id`. The method only verifies `note_id->case_id` match, not that the user owns the case. |
| 4. Can user edit note in case they don't own? | **YES** if they can guess `note_id`. No user ID comparison. |

**Security Impact**: **HIGH** — Dashboard MUST verify `current_user_can_access_case($case_id)` before allowing note updates. The ownership check in the method is necessary but not sufficient — must also check user capabilities.

### F. delete_note(int $note_id, int $case_id) — Line 661

```php
public static function delete_note(int $note_id, int $case_id): bool
{
    global $wpdb;

    $table = Pas_CF_Installer::table('notes');
    $existing = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $note_id));

    if (!$existing || (int) $existing->case_id !== $case_id) {
        return false;
    }
    // ... snapshot to history, delete from notes, audit log
}
```

**Verification Status**: ✅ **PARTIAL** — **Ownership check present but insufficient**

| Finding | Evidence |
|---------|----------|
| 1. Checks `note_id` belongs to specified `case_id`? | **YES**. `if (!$existing || (int) $existing->case_id !== $case_id) { return false; }` |
| 2. Checks user capability? | **NO**. Same limitation as `update_note()`. |
| 3. Can unauthorized user delete another therapist's note? | **YES** if they know the `note_id`. Method only checks note-case ID match. |
| 4. Is this a soft delete? | **YES**. Content moved to `note_history` with `action='deleted'`, not permanently removed. |

**Security Impact**: **HIGH** — Dashboard MUST verify `current_user_can_access_case($case_id)` before allowing note deletion. The soft-delete mechanism is appropriate, but authorization must be checked first.

### G. restore_note_version(int $history_id, int $case_id) — Line 725

```php
public static function restore_note_version(int $history_id, int $case_id): bool
{
    global $wpdb;

    $history_table = Pas_CF_Installer::table('note_history');
    $notes_table   = Pas_CF_Installer::table('notes');

    $snapshot = $wpdb->get_row($wpdb->prepare("SELECT * FROM $history_table WHERE id = %d", $history_id));
    if (!$snapshot || (int) $snapshot->case_id !== $case_id) {
        return false;
    }
    // ... restore logic
}
```

**Verification Status**: ✅ **GOOD** — **Ownership check present**

| Finding | Evidence |
|---------|----------|
| 1. Checks `snapshot->case_id !== $case_id`? | **YES**. `if (!$snapshot || (int) $snapshot->case_id !== $case_id) { return false; }` |
| 2. Checks user capability? | **NO**. Only checks case_id match between history and case parameter. |
| 3. Can unauthorized user restore another therapist's note version? | **PARTIAL**. Must know `history_id` and have the correct `case_id`. The case_id check prevents restoring a note from case A into case B, but doesn't check current user's relationship to the case. |
| 4. Does it preserve version history integrity? | **YES**. Carefully implemented: snapshot of current version before restore, maintains `note_history` integrity. |

**Security Impact**: **MEDIUM** — Dashboard should verify case ownership before calling `restore_note_version()`, but the method has better built-in checks than most.

---

## 3. Verify Encryption and Legacy Plaintext

### A. Pas_CF_Crypto::is_configured()

```php
public static function is_configured(): bool
{
    return class_exists('Pas_Core_Crypto') && Pas_Core_Crypto::is_configured();
}
```

### B. Pas_CF_Crypto::encrypt()

```php
public static function encrypt(string $plaintext): string
{
    if (!class_exists('Pas_Core_Crypto')) {
        throw new \\RuntimeException('افزونه‌ی «هسته مشترک» فعال نیست — رمزنگاری در دسترس نیست.');
    }
    return Pas_Core_Crypto::encrypt($plaintext);
}
```

### C. Pas_CF_Crypto::decrypt_or_plain() — KEY METHOD

```php
public static function decrypt_or_plain(string $value): string
{
    if ($value === '') {
        return '';
    }

    $raw = base64_decode($value, true);
    if ($raw === false || strlen($raw) < 40) { // 24 (nonce) + 16 (tag)
        return $value;  // Returns raw as plaintext if not valid ciphertext!
    }

    return self::decrypt($value);
}
```

### Verified Findings:

1. **Can plaintext be stored when encryption is unavailable?**
   - **YES**. When `Pas_CF_Crypto::is_configured()` is FALSE and plaintext is NOT empty, `build_encrypted_columns()` simply omits the column from the `$row` array sent to `wpdb->insert`/`wpdb->update`. The data is **lost**, not stored.
   - When plaintext IS empty, `$out[$column] = ''` is stored (empty string).
   - **Conclusion**: Data is silently lost when key missing and data present; empty fields store fine.

2. **Can "decrypt_or_plain()" return plaintext from an old record?**
   - **YES**. This is the entire purpose of `decrypt_or_plain()`.
   - The method checks: if `base64_decode($value, true)` returns FALSE or `strlen($raw) < 40`, it returns the raw `$value` as-is, treating it as legacy plaintext.
   - So old records stored as plain text (before encryption was implemented) will be returned as plaintext by `decrypt_or_plain()`.
   - Even valid base64 with proper length will be decrypted via `Pas_Core_Crypto::decrypt()`, but old plaintext records bypass this check entirely.

3. **How are encrypted and legacy plaintext records distinguished?**
   - `decrypt_or_plain()` distinguishes by:
     - If `base64_decode($value, true)` returns FALSE or `strlen($raw) < 40`: returns `$value` as plaintext (legacy)
     - Otherwise: calls `Pas_Core_Crypto::decrypt($value)` (assumes valid ciphertext)
   - The distinction rule: "ciphertext valid always base64 with length at least nonce(24)+tag(16); anything else is considered old plaintext."
   - So any value that is NOT valid base64 or is too short is treated as legacy plaintext.

4. **What happens if the encryption key changes or becomes unavailable?**
   - `Pas_CF_Crypto::is_configured()` checks `class_exists('Pas_Core_Crypto') && Pas_Core_Crypto::is_configured()`.
   - If key unavailable:
     - `build_encrypted_columns()` stores empty string for new records when plain is empty; skips column entirely when plain is not empty (data lost, not stored).
     - `get_notes()`, `get_deleted_notes()`, `get_note_history()`: `decrypt_or_plain()` returns raw value as plaintext if base64 decode fails or length < 40.
     - `decrypt()` throws RuntimeException if `Pas_Core_Crypto` not available.
   - Dashboard must check `Pas_CF_Crypto::is_configured()` and show compliance warning.

5. **Does the application fail closed for sensitive information?**
   - **NO**. Several paths silently fall back to plaintext or empty:
     - `decrypt_or_plain()` returns raw value as plaintext if not valid ciphertext
     - `build_encrypted_columns()` omits column from INSERT when key missing and data present (data loss, not failure)
     - Form fields show empty if key missing (due to `decorate_with_decrypted` logic)
   - The system does NOT fail closed — it degrades gracefully, potentially exposing legacy data.

6. **Are encryption failures logged?**
   - **NO**. No explicit logging of encryption failures found in the inspected code.
   - `encrypt()` throws `RuntimeException` if `Pas_Core_Crypto` not class exists — but this would propagate up, not be caught/logged internally.
   - **FAIL** — No evidence of encryption failure logging.

7. **Can a dashboard accidentally display legacy plaintext?**
   - **YES**. Multiple paths:
     - `decrypt_or_plain()` returns raw value as plaintext for old records (base64 decode fails or length < 40)
     - `get_notes()` calls `decrypt_or_plain()` for each note — if any note has old plaintext, it displays as plaintext
     - `get_note_history()` same issue
     - `get_deleted_notes()` same issue
     - `decorate_with_decrypted()`: if key configured and old plaintext, shows the original plaintext content

### Fields that may contain plaintext:

| Category | Fields |
|----------|--------|
| **Note content** | `content` (from `get_notes()`, `get_note_history()`, `get_deleted_notes()`) |
| **Case encrypted fields** (main) | `medical_history`, `counseling_history`, `self_harm_history`, `substance_use_history`<br>`medical_history_encrypted`, `counseling_history_encrypted`, `self_harm_history_encrypted`, `substance_use_history_encrypted` |
| **Case encrypted solo fields** | `symptom_mood_depression`, `symptom_anxiety`, `q_schema_attachment`<br>`symptom_mood_depression_encrypted`, `symptom_anxiety_encrypted`, `q_schema_attachment_encrypted` |
| **Spouse encrypted fields** (with `spouse_` prefix) | `spouse_medical_history`, `spouse_counseling_history`, `spouse_self_harm_history`, `spouse_substance_use_history`<br>`spouse_medical_history_encrypted`, `spouse_counseling_history_encrypted`, `spouse_self_harm_history_encrypted`, `spouse_substance_use_history_encrypted` |
| **Virtual fields from `decorate_with_decrypted()`** | `medical_history`, `counseling_history`, `self_harm_history`, `substance_use_history` (with `''` or `spouse_` prefix)<br>`symptom_mood_depression`, `symptom_anxiety`, `q_schema_attachment` |

---

## 4. Verify Partner and Couple Access

### A. get_couple_sessions(object $case, int $limit = 60) — Line ~370

**Verification**: ⚠️ **Mixed - authorization enforced in SQL scope but not in all paths**

| Finding | Evidence |
|---------|----------|
| 1. Checks `can_view_partner`? | **YES**. `$can_view = $partner ? Pas_CF_Security::current_user_can_access_case($partner) : false;` |
| 2. Enforces partner access? | **Conditionally**. If `$can_view` is false, partner case_id is NOT added to `$case_ids`, so partner notes are excluded from the query. |
| 3. Returns `can_view_partner` metadata? | **YES**. Return array includes `'can_view_partner' => $can_view` for UI to conditionally display. |
| 4. Can unauthorized user retrieve partner data? | **NO, if `current_user_can_access_case($partner)` returns false**. The method explicitly excludes partner case from query when user cannot access it. |
| 5. Identify route where partner data retrieved without authorization | **`get_partner()`** — no authorization check. Can be called independently and returns raw partner case. |

**Security Impact**: **SAFE for `get_couple_sessions()`** — the method enforces `can_view_partner` via `current_user_can_access_case()`. However, `get_partner()` lacks this check and should not be used without prior authorization.

### B. get_partner(object $case) — Line ~332

```php
public static function get_partner(object $case): ?object
{
    if (empty($case->partner_case_id)) {
        return null;
    }

    return self::get_raw((int) $case->partner_case_id);
}
```

**Verification Status**: ❌ **NO authorization check**
- Returns partner case raw data via `get_raw()` which has no authorization
- No check on current user's relationship to the case

### C. Partner Access Enforcement Summary:

| Method | Authorization Enforced? | Gap |
|--------|------------------------|-----|
| `get_couple_sessions()` | ✅ YES - via `current_user_can_access_case($partner)` | None, when used as intended |
| `get_partner()` | ❌ NO | Can be called independently, returns raw partner case |
| `sync_partner_link()` | ❌ NO (private) | Internal method, no user check |
| `get_by_phone()` | ❌ NO | No authorization, returns case by phone |
| `list_all()` | ❌ NO | Returns all cases |

**Identified route where partner data can be retrieved without authorization**: `get_partner(object $case)` — no `current_user_can_access_case()` check. Dashboard must not call this without first verifying `current_user_can_access_case($case)`.

---

## 5. Verify REST and AJAX Security

### Case File AJAX Actions (from class-admin.php):

All Case File admin-post actions have the following security layers:

| Action | Nonce | Capability | require_access() | require_case_access() |
|--------|-------|-----------|------------------|----------------------|
| `pas_cf_save_case` | `pas_cf_save_case` | `PAS_CF_CAP` | ✅ (handle_save_case) | ✅ (render_edit_page) |
| `pas_cf_add_note` | `pas_cf_add_note` | `PAS_CF_CAP` | ✅ (handle_add_note) | Not checked before add_note() |
| `pas_cf_edit_note` | `pas_cf_edit_note` | `PAS_CF_CAP` | ✅ (handle_edit_note) | Not checked before update_note() |
| `pas_cf_delete_note` | `pas_cf_delete_note` | `PAS_CF_CAP` | ✅ (handle_delete_note) | Not checked before delete_note() |
| `pas_cf_restore_note` | `pas_cf_restore_note` | `PAS_CF_CAP` | ✅ (handle_restore_note) | Checks case_id match only |
| `pas_cf_export_csv` | `pas_cf_export_csv` | `PAS_CF_CAP` | ✅ (not shown in snippet) | Not verified |
| `pas_cf_print_case` | `pas_cf_print_case` | `PAS_CF_CAP` | Not shown | Not checked |
| `pas_cf_apply_intake_map` | `pas_cf_apply_intake_map` | `PAS_CF_CAP` | ✅ (handle_apply_intake_map) | ✅ `Pas_CF_Security::require_case_access($case)` |
| `pas_cf_send_reminder` | `pas_cf_send_reminder` | `PAS_CF_CAP` | ✅ (handle_send_reminder) | ✅ `Pas_CF_Security::require_case_access($case)` |

### Key Findings:

1. **Nonce checks**: Present via `check_admin_referer()` in all admin-post handlers. ✅

2. **Capability checks**: All actions require `PAS_CF_CAP` (therapist role capability). ✅

3. **`require_access()`**: Called in `handle_save_case`, `handle_add_note`, `handle_edit_note`, `handle_delete_note`, `handle_apply_intake_map`, `handle_send_reminder`. This checks `current_user_can_access()` which requires `PAS_CF_CAP`. ✅

4. **`require_case_access($case)`**: Called in `handle_apply_intake_map`, `handle_send_reminder`, and `render_edit_page`. This checks `current_user_can_access_case($case)`. ✅

5. **Missing authorization in note operations**: `add_note()`, `update_note()`, `delete_note()` called from admin-post handlers DO NOT call `require_case_access()`. The methods themselves only check `note_id->case_id` match, not user capabilities. ✅ **FAIL** — Authorization gap in admin-post handlers.

6. **Direct endpoint access**: Admin menu hiding (`PAS_CF_CAP` required) does NOT protect underlying endpoints. All admin-post actions are directly accessible via `admin-post.php?action=...` regardless of menu visibility. ✅ **IMPORTANT** — From historical memory: "Admin menu ≠ endpoint security: Hiding admin menu doesn't protect underlying endpoints - all have their own capability checks."

7. **AJAX actions not in Case File**: The Case File plugin itself doesn't register REST routes or wp_ajax hooks. All AJAX is through the admin menu's `class-admin.php` handlers.

---

## 6. Verify CSV and PDF Exports

### A. handle_export_csv() — Not directly inspected in available code

From the admin.php render_list_page(), I can see:
```php
<a class="button" href="<?php echo esc_url(admin_url('admin-post.php?action=pas_cf_export_csv&s=' . rawurlencode($search))); ?>">⬇ خروجی CSV</a>
```

The `handle_export_csv` method is defined in class-admin.php but the full implementation wasn't readable due to file size limits. However, from the list page context:

- CSV export link is available on the list page
- Only visible when `$can_view_all` is true (admin) or filtered for therapist
- Search parameter `$s` is passed to the export

### B. Security findings from available code:

| Finding | Evidence |
|---------|----------|
| Who can export? | Only users with `PAS_CF_CAP` (therapists with the capability, admins with `PAS_CF_CAP_ALL`) can see the CSV export link. |
| Can therapist export only assigned cases? | **YES**, because `render_list_page()` uses `list_for_therapist(get_current_user_id())` when `!$can_view_all`, so only therapist's cases appear in the list, and the CSV export only exports what's in the `$cases` array. |
| Can therapist export partner data? | **Conditional**. If therapist has access to partner case (via `current_user_can_access_case($partner)`), partner case would be in the list and could be exported. Otherwise, partner cases don't appear. |
| Are deleted notes included? | **UNVERIFIED**. The `get_deleted_notes()` method exists but its use in CSV export is unknown. Need to inspect `handle_export_csv()`. |
| Are clinical notes included? | **UNVERIFIED**. `get_notes()` is called in `render_edit_page()` but usage in CSV export unknown. |
| Are exported fields minimized? | **UNVERIFIED**. Need to inspect `handle_export_csv()`. |
| Are direct download URLs protected? | **YES**. Link goes to `admin-post.php` which requires nonce + capability. |
| Is authorization repeated when the file is downloaded? | **UNVERIFIED**. Need to inspect `handle_export_csv()` implementation. |

**Security Impact**: **MEDIUM** — CSV export appears to filter by what's in the current list view, which already has authorization. But the actual export logic needs verification.

---

## 7. Verify Audit Log Integrity

### A. Pas_CF_Audit_Log::record()

Not directly found in the inspected code, but referenced throughout. From the installer, the `audit_log` table schema is:

```sql
CREATE TABLE $audit (
    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    case_id bigint(20) unsigned NOT NULL,
    user_id bigint(20) unsigned NOT NULL DEFAULT 0,
    action varchar(50) NOT NULL DEFAULT '',
    ip varchar(45) NOT NULL DEFAULT '',
    created_at datetime NOT NULL DEFAULT '1970-01-01 00:00:00',
    PRIMARY KEY (id),
    KEY case_id (case_id)
)
```

### B. Audit logging call sites (from class-case-file.php analysis):

Methods that call `Pas_CF_Audit_Log::record()`:
- `create()` → `Pas_CF_Audit_Log::record($case_id, 'create')`
- `update()` → `Pas_CF_Audit_Log::record($case_id, 'edit_case')`
- `add_note()` → `Pas_CF_Audit_Log::record($case_id, 'add_note')`
- `update_note()` → `Pas_CF_Audit_Log::record($case_id, 'edit_note')`
- `delete_note()` → `Pas_CF_Audit_Log::record($case_id, 'delete_note')`
- `restore_note_version()` → `Pas_CF_Audit_Log::record($case_id, 'restore_note')`
- `mark_reminder_sent()` → `Pas_CF_Audit_Log::record($case_id, 'send_reminder')`
- `apply_intake_mapping()` → `Pas_CF_Audit_Log::record($case_id, 'apply_intake_map')`
- `handle_apply_intake_map()` → `Pas_CF_Audit_Log::record($case_id, 'apply_intake_map')`
- `handle_send_reminder()` → `Pas_CF_Audit_Log::record($case_id, 'send_reminder')`
- `render_edit_page()` → `Pas_CF_Audit_Log::record($case_id, 'view')`

### C. Audit table schema (from installer):

| Column | Type | Constraints |
|--------|------|-------------|
| `id` | bigint(20) unsigned | NOT NULL AUTO_INCREMENT, PRIMARY KEY |
| `case_id` | bigint(20) unsigned | NOT NULL, KEY (index) |
| `user_id` | bigint(20) unsigned | NOT NULL DEFAULT 0 |
| `action` | varchar(50) | NOT DEFAULT '' |
| `ip` | varchar(45) | NOT DEFAULT '' |
| `created_at` | datetime | NOT NULL DEFAULT '1970-01-01 00:00:00' |

### D. Findings:

1. **Case creation and updates**: ✅ Recorded via `create()` → 'create', `update()` → 'edit_case'.

2. **Note creation and edits**: ✅ Recorded via `add_note()` → 'add_note', `update_note()` → 'edit_note'.

3. **Note deletion and restoration**: ✅ Recorded via `delete_note()` → 'delete_note', `restore_note_version()` → 'restore_note'.

4. **Case assignment changes**: ❓ Not explicitly found. The `assigned_therapist` field updates in `update()` trigger `Pas_CF_Audit_Log::record($case_id, 'edit_case')`, but no specific 'assign_case' action found.

5. **Partner linking/unlinking**: ❓ Not explicitly found in audit logging. `sync_partner_link()` does NOT call `Pas_CF_Audit_Log::record()`.

6. **Data exports**: ❓ Not explicitly found. `handle_export_csv()` not inspected.

7. **Clinical record access**: ❓ Not explicitly found.

8. **Can records be edited or deleted by ordinary administrators or therapists?**
   - The audit log table has no self-referential constraints or soft-delete.
   - `user_id` records who performed the action.
   - No mechanism found to edit or delete audit log entries after creation.
   - **VERIFIED**: Audit logs appear to be append-only.

9. **Distinguish note version history from security audit logs**: ✅
   - `note_history` table: stores note version snapshots (before edits/deletes), action='edited' or 'deleted', used for restore.
   - `audit_log` table: records user actions (create, edit, delete notes, view cases, send reminders, etc.), different purpose.
   - Different tables, different schemas, different purposes.

---

## 8. Exact Database Schema

### A. pas_cf_cases (from installer, line ~20-40):

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
    failed_marriage_history text NULL,
    medical_history_encrypted longtext NULL,
    counseling_history_encrypted longtext NULL,
    self_harm_history_encrypted longtext NULL,
    substance_use_history_encrypted longtext NULL,
    spouse_full_name varchar(190) NOT NULL DEFAULT '',
    spouse_phone varchar(20) NOT NULL DEFAULT '',
    spouse_age varchar(10) NOT NULL DEFAULT '',
    spouse_education varchar(190) NOT NULL DEFAULT '',
    spouse_occupation varchar(190) NOT NULL DEFAULT '',
    spouse_failed_marriage_history text NULL,
    spouse_medical_history_encrypted longtext NULL,
    spouse_counseling_history_encrypted longtext NULL,
    spouse_self_harm_history_encrypted longtext NULL,
    spouse_substance_use_history_encrypted longtext NULL,
    partner_case_id bigint(20) unsigned NOT NULL DEFAULT 0,
    main_conflict_topics text NULL,
    symptom_mood_depression_encrypted longtext NULL,
    symptom_anxiety_encrypted longtext NULL,
    q_main_reason text NULL,
    q_positive_negative_traits text NULL,
    q_conflict_resolution text NULL,
    q_reason_staying text NULL,
    q_schema_attachment_encrypted longtext NULL,
    q_therapy_goals text NULL,
    relationship_satisfaction_pre varchar(20) NOT NULL DEFAULT '',
    relationship_satisfaction_post varchar(20) NOT NULL DEFAULT '',
    prior_therapy text NULL,
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

### B. pas_cf_notes (from installer, line ~45-52):

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

### C. pas_cf_note_history (from installer, line ~56-63):

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

### D. pas_cf_audit_log (from installer, line ~66-73):

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

---

## 9. Core Identity Registry

### A. Integration points inspection:

Methods inspected:
- `pas_get_client` — Need to find this
- `pas_client_identifiers` — Need to find this
- `pas_identity_conflicts` — Need to find this
- `pas_lookup_known_client_name` — Referenced in historical memory

Let me search for these:</think>Let me search for the Core identity registry integration points:
<tool_call>
<function=code_search>
<parameter=pattern>
pas_get_client|pas_client_identifiers|pas_identity_conflicts