# FINAL PERFORMANCE REPORT

## Design choices that bound cost

| Area | Choice |
|---|---|
| Case list | one `list_for_therapist` query (500 cap) + one grouped `get_last_session_map` |
| Overview | limited to 60 cases, so `get_next_session` runs at most 60 times |
| Appointments | 60 cases, bookings capped at 60 rows |
| Reports | one metadata map, **no note decryption loop** (fixed during review) |
| Export | streams rows, no buffering; metadata only |
| Notifications | sync throttled by 15-minute per-user transient |
| Assets | loaded only on `toplevel_page_pas-unified-dashboard` |
| Settings | single autoloaded option |
| Module loading | plain `require_once`, no reflection/autoloader |

## Known costs

1. `get_next_session` per case on Overview/Appointments/Notifications — O(cases),
   bounded at 60.
2. Case detail renders up to 50 notes, each escaped and `nl2br`-ed, and loads
   history per note. This is the heaviest screen; acceptable for one case.
3. `authorized_case()` calls `get()`, which decrypts encrypted case columns
   before the ownership check. Cost is per page view, not per row.
4. Registry stats run three `COUNT(*)` queries per Overview/Reports view.

## Not measured

No profiling was executed (no PHP runtime). No claims of wall-clock improvement
are made. Staging should measure: dashboard TTFB, query count per module, and
case-detail render time with 50 notes.
