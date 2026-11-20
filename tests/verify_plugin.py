#!/usr/bin/env python3
"""
Static and integration verification for the Peyvand Amn Unified Dashboard.

This is NOT a PHP interpreter. It performs:
  * file inventory checks
  * a PHP structural parser (balanced braces/parens/brackets, comments and
    strings removed) to catch truncated or malformed files
  * forbidden-pattern checks (list_all, direct foreign tables, nopriv, ...)
  * endpoint audits (AJAX/admin-post/REST authorization wiring)
  * nonce/escaping/input-sanitization audits
  * package structure verification after ZIP extraction

Exit code 0 = all checks passed, 1 = failures.
"""

import os
import re
import sys
import zipfile

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
PLUGIN = os.path.join(ROOT, "plugins", "peyvand-amn-unified-dashboard")

failures = []
passes = []


def ok(name):
    passes.append(name)


def fail(name, detail):
    failures.append((name, detail))


def php_files():
    out = []
    for base, _dirs, files in os.walk(PLUGIN):
        for name in files:
            if name.endswith(".php"):
                out.append(os.path.join(base, name))
    return sorted(out)


def rel(path):
    return os.path.relpath(path, ROOT)


def strip_php(src):
    """Remove comments and strings, keep newlines, for balance checking."""
    out = []
    i, n = 0, len(src)
    state = None
    while i < n:
        ch = src[i]
        nxt = src[i + 1] if i + 1 < n else ""
        if state is None:
            if ch == "/" and nxt == "*":
                state = "block"
                i += 2
                continue
            if ch == "/" and nxt == "/":
                state = "line"
                i += 2
                continue
            if ch == "#":
                state = "line"
                i += 1
                continue
            if ch in ("'", '"'):
                state = ch
                i += 1
                continue
            out.append(ch)
            i += 1
            continue
        if state == "block":
            if ch == "*" and nxt == "/":
                state = None
                i += 2
            else:
                out.append("\n" if ch == "\n" else " ")
                i += 1
            continue
        if state == "line":
            if ch == "\n":
                state = None
                out.append("\n")
            i += 1
            continue
        # inside string
        if ch == "\\":
            i += 2
            continue
        if ch == state:
            state = None
        out.append("\n" if ch == "\n" else " ")
        i += 1
    return "".join(out)


def check_balance(path):
    src = open(path, encoding="utf-8").read()
    stripped = strip_php(src)
    pairs = {"{": "}", "(": ")", "[": "]"}
    stack = []
    for ch in stripped:
        if ch in pairs:
            stack.append(ch)
        elif ch in pairs.values():
            if not stack:
                return f"unexpected closing {ch!r}"
            opening = stack.pop()
            if pairs[opening] != ch:
                return f"mismatched {opening!r} closed by {ch!r}"
    if stack:
        return f"unclosed {stack[-1]!r} (depth {len(stack)})"
    if "<?php" not in src:
        return "missing <?php opening tag"
    return None


def main():
    if not os.path.isdir(PLUGIN):
        fail("plugin directory", "missing " + PLUGIN)
        report()
        return

    # ── 1. inventory ────────────────────────────────────────────────────
    required = [
        "peyvand-amn-unified-dashboard.php",
        "uninstall.php",
        "readme.txt",
        "includes/class-dashboard-loader.php",
        "includes/class-dashboard-security.php",
        "includes/class-dashboard-permissions.php",
        "includes/class-dashboard-router.php",
        "includes/class-dashboard-assets.php",
        "includes/class-dashboard-ajax.php",
        "includes/class-dashboard-rest.php",
        "includes/class-dashboard-notifications.php",
        "includes/class-dashboard-audit-log.php",
        "includes/class-dashboard-export.php",
        "includes/class-dashboard-actions.php",
        "includes/class-dashboard-flash.php",
        "includes/adapters/class-adapter-quiz.php",
        "includes/adapters/class-adapter-portal.php",
        "includes/adapters/class-adapter-followup.php",
        "includes/modules/followup-module.php",
        "includes/class-dashboard-validation.php",
        "includes/class-dashboard-database.php",
        "includes/class-dashboard-core-bridge.php",
        "includes/modules.php",
        "includes/modules/overview-module.php",
        "includes/modules/patients-module.php",
        "includes/modules/case-files-module.php",
        "includes/modules/assessments-module.php",
        "includes/modules/appointments-module.php",
        "includes/modules/reports-module.php",
        "includes/modules/communications-module.php",
        "includes/modules/notifications-module.php",
        "includes/modules/system-health-module.php",
        "includes/modules/settings-module.php",
        "includes/modules/audit-module.php",
        "templates/layout.php",
        "templates/header.php",
        "templates/sidebar.php",
        "templates/footer.php",
        "assets/css/dashboard-admin.css",
        "assets/js/dashboard-admin.js",
        "languages/peyvand-amn-dashboard.pot",
    ]
    for item in required:
        if os.path.isfile(os.path.join(PLUGIN, item)):
            ok("inventory: " + item)
        else:
            fail("inventory", "missing " + item)

    files = php_files()

    # ── 2. structural parse ─────────────────────────────────────────────
    for path in files:
        src = open(path, encoding="utf-8").read()
        problem = check_balance(path)
        if problem:
            fail("structure: " + rel(path), problem)
        elif "defined( 'ABSPATH' )" not in src and "defined( 'WP_UNINSTALL_PLUGIN' )" not in src:
            fail("abspath: " + rel(path), "no ABSPATH/WP_UNINSTALL guard")
        else:
            ok("structure: " + rel(path))

    # ── 3. forbidden patterns ──────────────────────────────────────────
    forbidden = {
        r"\blist_all\s*\(": "list_all() usage",
        r"wp_ajax_nopriv_": "nopriv AJAX endpoint",
        r"admin_post_nopriv_": "nopriv admin-post endpoint",
        r"wpdb->(?:prefix\s*\.\s*['\"]pas_cf_|pas_cf_)": "direct foreign table access",
        r"\b__return_true\b": "permissive REST permission",
        r"\beval\s*\(": "eval()",
        r"base64:[A-Za-z0-9+/=]{20,}": "hardcoded encryption key",
        r"\$wpdb->query\(\s*['\"]\s*(?:DROP|TRUNCATE)": "destructive SQL",
    }
    for path in files:
        src = open(path, encoding="utf-8").read()
        name = rel(path)
        for pattern, label in forbidden.items():
            for m in re.finditer(pattern, src):
                line = src[: m.start()].count("\n") + 1
                line_text = src.splitlines()[line - 1]
                # DROP در uninstall فقط روی جداول اختصاصی داشبورد مجاز است.
                if "destructive SQL" in label and name.endswith("uninstall.php") and "pas_dashboard" in line_text:
                    ok("forbidden-allowed: uninstall drops own table only")
                    continue
                # list_all فقط در پل هسته و فقط با گارد view-all مجاز است.
                if "list_all" in label and name.endswith("class-dashboard-core-bridge.php"):
                    window = "\n".join(src.splitlines()[max(0, line - 25):line + 5])
                    if "current_user_can_view_all" in window or "viewer_can_see_all_cases" in window:
                        ok("forbidden-allowed: list_all behind view-all capability")
                        continue
                fail(f"forbidden [{label}]", f"{name}:{line}")
    ok("forbidden-pattern scan completed")

    # direct table access should only be our own tables
    for path in files:
        src = open(path, encoding="utf-8").read()
        for m in re.finditer(r"\$wpdb->(?:get_var|get_row|get_results|query|insert|update|delete)\(([^\n]*)", src):
            snippet = m.group(0)
            if "pas_" in snippet and "pas_dashboard" not in snippet and "PAS_Dashboard_Database" not in snippet:
                fail("foreign-table sql", f"{rel(path)}:{src[:m.start()].count(chr(10))+1}: {snippet[:90]}")
    ok("SQL ownership scan completed")

    # ── 4. AJAX endpoint audit ─────────────────────────────────────────
    ajax_src = open(os.path.join(PLUGIN, "includes/class-dashboard-ajax.php"), encoding="utf-8").read()
    registered = re.findall(r"add_action\(\s*'wp_ajax_([a-z0-9_]+)'\s*,\s*array\(\s*__CLASS__\s*,\s*'([a-z0-9_]+)'", ajax_src)
    if not registered:
        fail("ajax", "no endpoint registrations found")
    for hook, method in registered:
        m = re.search(r"function\s+" + re.escape(method) + r"\s*\([^)]*\)\s*\{(.*?)\n\t\}", ajax_src, re.S)
        if not m:
            fail("ajax: " + hook, "handler body not found for " + method)
            continue
        body = m.group(1)
        problems = []
        if "self::guard(" not in body:
            problems.append("missing guard()")
        if re.search(r"pas_dashboard_" + re.escape(hook.replace("pas_dashboard_", "")) + r"|pas_dashboard_", body) is None and "guard(" in body:
            pass
        if "note" in method and "authorized_case" not in body:
            problems.append("missing resource authorization")
        if "note" in method and "wp_send_json_error" not in body:
            problems.append("no safe error path")
        if problems:
            fail("ajax: " + hook, ", ".join(problems))
        else:
            ok("ajax: " + hook)

    if "wp_send_json_error" in ajax_src and re.search(r"wp_send_json_error\([^;]*,\s*40[13]\s*\)", ajax_src, re.S) is None:
        fail("ajax", "no explicit 401/403 status codes")
    else:
        ok("ajax: explicit 401/403 responses")

    # guard must verify nonce server-side
    if "wp_verify_nonce" in ajax_src or "PAS_Dashboard_Security::verify_nonce" in ajax_src:
        ok("ajax: nonce verification present")
    else:
        fail("ajax", "nonce verification missing")

    # ── 5. admin-post audit ────────────────────────────────────────────
    export_src = open(os.path.join(PLUGIN, "includes/class-dashboard-export.php"), encoding="utf-8").read()
    for needle in ["verify_nonce( 'export_cases' )", "can_export()", "PAS_Dashboard_Audit_Log::record"]:
        if needle in export_src:
            ok("admin-post export: " + needle)
        else:
            fail("admin-post export", "missing " + needle)

    settings_src = open(os.path.join(PLUGIN, "includes/modules/settings-module.php"), encoding="utf-8").read()
    for needle in ["verify_nonce( 'save_settings' )", "can_manage()", "PAS_Dashboard_Audit_Log::record"]:
        if needle in settings_src:
            ok("admin-post settings: " + needle)
        else:
            fail("admin-post settings", "missing " + needle)

    # ── 6. REST audit ──────────────────────────────────────────────────
    rest_src = open(os.path.join(PLUGIN, "includes/class-dashboard-rest.php"), encoding="utf-8").read()
    routes = re.findall(r"register_rest_route\(", rest_src)
    perms = re.findall(r"'permission_callback'\s*=>", rest_src)
    if len(routes) == 3 and len(perms) >= 3:
        ok(f"rest: {len(routes)} routes each with permission_callback")
    else:
        fail("rest", f"routes={len(routes)} permissions={len(perms)}")
    if re.search(r"'methods'\s*=>\s*'POST'", rest_src):
        fail("rest", "write method registered")
    else:
        ok("rest: no write routes")

    # ── 7. form nonce coverage ─────────────────────────────────────────
    module_dir = os.path.join(PLUGIN, "includes/modules")
    for name in sorted(os.listdir(module_dir)):
        if not name.endswith(".php"):
            continue
        src = open(os.path.join(module_dir, name), encoding="utf-8").read()
        posts = re.findall(r"<form[^>]*method=\"post\"", src, re.I)
        # فرمهای Action Bus با PAS_Dashboard_Actions::fields() ساخته میشوند که
        # خودش PAS_Dashboard_Security::nonce_field() را در فرم قرار میدهد.
        has_nonce = "nonce_field(" in src or "PAS_Dashboard_Actions::fields(" in src
        for _ in posts:
            if not has_nonce:
                fail("form nonce", name + " posts without nonce_field")
        if posts and has_nonce:
            ok("form nonce: " + name)

    # ── 8. input sanitization ──────────────────────────────────────────
    raw_superglobals = []
    for path in files:
        src = open(path, encoding="utf-8").read()
        for m in re.finditer(r"\$_(GET|POST|REQUEST|COOKIE)\[", src):
            line_no = src[: m.start()].count("\n") + 1
            line = src.splitlines()[line_no - 1]
            if not re.search(r"Validation::|absint|sanitize_|wp_unslash|verify_nonce|check_ajax_referer|isset\(|empty\(", line):
                raw_superglobals.append(f"{rel(path)}:{line_no}: {line.strip()[:100]}")
    if raw_superglobals:
        for item in raw_superglobals:
            fail("unsanitized superglobal", item)
    else:
        ok("input: all superglobals pass through validation/sanitization")

    # ── 9. output escaping heuristic ───────────────────────────────────
    suspicious = []
    for path in files:
        if path.endswith("layout.php") or path.endswith("admin-page.php"):
            continue
        src = open(path, encoding="utf-8").read()
        for line_no, line in enumerate(src.splitlines(), 1):
            if re.search(r"echo\s+\$", line) and not re.search(r"esc_|wp_kses|\(int\)|\(string\)|implode|nl2br|checked\(|selected\(", line):
                suspicious.append(f"{rel(path)}:{line_no}: {line.strip()[:100]}")
    if suspicious:
        for item in suspicious:
            fail("unescaped echo", item)
    else:
        ok("output: no unescaped echo of variables")

    # ── 10. module classes exist for matrix ────────────────────────────
    matrix_src = open(os.path.join(PLUGIN, "includes/class-dashboard-permissions.php"), encoding="utf-8").read()
    classes = re.findall(r"'class'\s*=>\s*'([A-Za-z_]+)'", matrix_src)
    defined = set()
    for path in files:
        src = open(path, encoding="utf-8").read()
        defined.update(re.findall(r"class\s+([A-Za-z_][A-Za-z0-9_]*)\s+extends\s+PAS_Dashboard_Module", src))
    missing = [c for c in classes if c not in defined]
    if missing:
        fail("modules", "matrix classes without implementation: " + ", ".join(missing))
    else:
        ok(f"modules: {len(classes)} matrix classes implemented")

    # ── 11. loader requires resolve ────────────────────────────────────
    loader = open(os.path.join(PLUGIN, "includes/class-dashboard-loader.php"), encoding="utf-8").read()
    for m in re.finditer(r"'(includes/[a-z0-9\-/]+\.php)'", loader):
        target = os.path.join(PLUGIN, m.group(1))
        if not os.path.isfile(target):
            fail("loader", "required file missing: " + m.group(1))
    ok("loader: requires resolve")

    # module list in loader matches files
    for m in re.finditer(r"'([a-z\-]+-module\.php)'", loader):
        if not os.path.isfile(os.path.join(module_dir, m.group(1))):
            fail("loader", "module listed but missing: " + m.group(1))
    ok("loader: module list resolves")

    # ── 12. option ownership ───────────────────────────────────────────
    allowed_options = {
        "PAS_DASHBOARD_OPTION",
        "'pas_dashboard_db_version'",
        "'pas_dashboard_version'",
        "'pas_dashboard_settings'",
    }
    for path in files:
        if path.endswith("uninstall.php"):
            continue
        src = open(path, encoding="utf-8").read()
        for m in re.finditer(r"update_option\(\s*([^,]+),", src):
            arg = m.group(1).strip()
            if arg not in allowed_options:
                fail("option ownership", f"{rel(path)}: update_option({arg})")
    ok("options: only dashboard-owned options written")

    # ── 13. encryption fail-closed ─────────────────────────────────────
    bridge = open(os.path.join(PLUGIN, "includes/class-dashboard-core-bridge.php"), encoding="utf-8").read()
    for needle in ["crypto_ready()", "is_configured()"]:
        if needle in bridge:
            ok("crypto: " + needle)
        else:
            fail("crypto", "missing " + needle)
    if re.search(r"function add_note[\s\S]{0,400}?crypto_ready\(\)[\s\S]{0,200}?return new WP_Error", bridge):
        ok("crypto: add_note fails closed without key")
    else:
        fail("crypto", "add_note does not fail closed without key")

    # ── 14. no patient PII in notifications/audit ─────────────────────
    notif = open(os.path.join(PLUGIN, "includes/class-dashboard-notifications.php"), encoding="utf-8").read()
    if "full_name" in notif or "content" in notif:
        fail("notifications", "may include patient data/note content")
    else:
        ok("notifications: no patient name or note content")

    audit = open(os.path.join(PLUGIN, "includes/class-dashboard-audit-log.php"), encoding="utf-8").read()
    if re.search(r"content|password|token|key", audit, re.I) and "ip_hash" not in audit:
        fail("audit", "suspicious fields")
    else:
        ok("audit: no clinical/secret fields")

    # ── 15. package check ──────────────────────────────────────────────
    # dist/ دقیقاً یک بستهٔ نصب برای هر افزونه نگه می‌دارد و نام آن با نسخهٔ هدر همسو است.
    header = open(os.path.join(PLUGIN, "peyvand-amn-unified-dashboard.php"), encoding="utf-8").read()
    zip_version = re.search(r"Version:\s*([0-9.]+)", header)
    zip_name = "peyvand-amn-unified-dashboard-" + (zip_version.group(1) if zip_version else "?") + ".zip"
    zip_path = os.path.join(ROOT, "dist", zip_name)
    if os.path.isfile(zip_path):
        with zipfile.ZipFile(zip_path) as zf:
            bad = zf.testzip()
            names = zf.namelist()
        if bad:
            fail("zip", "corrupt entry " + bad)
        elif not any(n.endswith("peyvand-amn-unified-dashboard/peyvand-amn-unified-dashboard.php") for n in names):
            fail("zip", "plugin main file not at expected path")
        elif any(n.endswith((".env", ".log", ".sql", ".bak")) for n in names):
            fail("zip", "development artifacts included")
        else:
            ok(f"zip: {len(names)} entries, main file at expected path, no dev artifacts")
    else:
        fail("zip", "dist package not built")

    # ── 16. phase-2 object guards ──────────────────────────────────────
    # قیدهای امنیتی فاز ۲ باید در سرور باشند، نه فقط در رابط کاربری.
    quiz = open(os.path.join(PLUGIN, "includes/adapters/class-adapter-quiz.php"), encoding="utf-8").read()
    at = quiz.find("function notify(")
    notify_body = quiz[at : at + 2500] if at >= 0 else ""
    if (
        "array( 'unused', 'processing' )" in notify_body
        and "pas_dashboard_quiz_expired" in notify_body
        and "can_view_phone" in notify_body
    ):
        ok("quiz: notify guards state, expiry and scope")
    else:
        fail("quiz", "notify does not guard state/expiry/scope")

    assess = open(os.path.join(PLUGIN, "includes/modules/assessments-module.php"), encoding="utf-8").read()
    i_scope = assess.find("'خارج از دامنهٔ شما'")
    i_link = assess.find("'نتیجه و روند'")
    if 0 <= i_scope < i_link:
        ok("assessments: scope checked before the result link")
    else:
        fail("assessments", "out-of-scope row may still offer the result link")

    follow = open(os.path.join(PLUGIN, "includes/adapters/class-adapter-followup.php"), encoding="utf-8").read()
    if "can_view_phone" in follow and "pas_dashboard_lead_scope" in follow:
        ok("followup: lead actions check case scope")
    else:
        fail("followup", "mark_followup has no case-scope check")

    actions_src = open(os.path.join(PLUGIN, "includes/class-dashboard-actions.php"), encoding="utf-8").read()
    for needle in ["'lead.followup'", "'survey.moderate'", "'survey.delete'"]:
        if needle in actions_src:
            ok("followup: action " + needle)
        else:
            fail("followup", "action not registered: " + needle)

    portal = open(os.path.join(PLUGIN, "includes/adapters/class-adapter-portal.php"), encoding="utf-8").read()
    at = portal.find("function reply_message(")
    reply_body = portal[at : at + 2000] if at >= 0 else ""
    if "self::can_view_phone(" in reply_body and "pas_dashboard_message_scope" in reply_body:
        ok("messages: reply checks case scope")
    else:
        fail("messages", "reply_message has no case-scope check")

    owner_path = os.path.join(ROOT, "plugins/pivand-amn-client-portal/includes/class-messages.php")
    if os.path.isfile(owner_path):
        owner = open(owner_path, encoding="utf-8").read()
        if re.search(r"public function get_message\(\s*int \$msg_id\s*\)", owner):
            ok("messages: owner get_message() contract present")
        else:
            fail("messages", "owner get_message() missing")
    else:
        fail("messages", "client portal source not found")

    # ── 17. version consistency ────────────────────────────────────────
    main_src = open(os.path.join(PLUGIN, "peyvand-amn-unified-dashboard.php"), encoding="utf-8").read()
    readme = open(os.path.join(PLUGIN, "readme.txt"), encoding="utf-8").read()
    v_main = re.search(r"Version:\s*([0-9.]+)", main_src)
    v_const = re.search(r"PAS_DASHBOARD_VERSION',\s*'([0-9.]+)'", main_src)
    v_readme = re.search(r"Stable tag:\s*([0-9.]+)", readme)
    versions = {v_main.group(1) if v_main else None, v_const.group(1) if v_const else None, v_readme.group(1) if v_readme else None}
    if len(versions) != 1 or None in versions:
        fail("version", f"inconsistent: {versions}")
    else:
        ok("version: consistent " + versions.pop())

    report()


def report():
    print(f"PASS: {len(passes)}")
    print(f"FAIL: {len(failures)}")
    if failures:
        print("\nFailures:")
        for name, detail in failures:
            print(f" - {name}: {detail}")
        sys.exit(1)
    print("\nAll static and integration checks passed.")
    sys.exit(0)


if __name__ == "__main__":
    main()
