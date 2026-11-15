#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
آزمون خودکار «نصب تازه» اکوسیستم پیوند امن (release/v2).

چه کاری می‌کند (در یک سایت و دیتابیس جدا، بیرون از مخزن):
  ۱. دیتابیس تازه می‌سازد و وردپرس را از یک نصب موجود کپی/نصب می‌کند.
  ۲. ۱۱ بستهٔ release/v2 را نصب و فعال می‌کند (بدون هیچ نسخهٔ قبلی).
  ۳. با کد v2 دادهٔ نمونه می‌سازد و کل زنجیره را می‌سنجد:
     پرونده → شناسهٔ مراجع → دعوت‌نامه → ثبت آزمون → رویداد → هشدار → وظیفه.
  ۴. صفحات واقعی HTTP را با ورود مدیر بررسی می‌کند و خطاهای PHP را می‌شمارد.
  ۵. جدول نتیجه چاپ می‌کند و با کد خروج ۱ تمام می‌شود اگر هر بررسی شکست بخورد.

اجرا:
  python3 scripts/fresh_install_test.py \
    --php ~/phase11/php/php --wp-cli ~/phase11/wp-cli.phar \
    --wp-source ~/phase11/web --workdir ~/phase17-fresh \
    --db peyvand_fresh_test --db-host 127.0.0.1:3307 --db-user root --db-pass '' \
    --port 8098

این اسکریپت از همان ابزارهای scripts/upgrade_test.py استفاده می‌کند تا هر دو
آزمون یک رفتار داشته باشند (کپی/نصب وردپرس، فعال‌سازی بسته‌ها، بررسی صفحات،
خواندن وضعیت با PHP_INSPECT).
"""

from __future__ import annotations

import argparse
import json
import re
import subprocess
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent))

import upgrade_test as ut  # noqa: E402  (ابزارهای مشترک دو آزمون)


def main() -> int:
    here = Path(__file__).resolve().parent.parent
    ap = argparse.ArgumentParser(description="آزمون نصب تازهٔ پیوند امن (release/v2)")
    ap.add_argument("--php", required=True)
    ap.add_argument("--wp-cli", required=True)
    ap.add_argument("--wp-source", required=True, help="نصب سالم وردپرس برای کپی هسته")
    ap.add_argument("--workdir", required=True)
    ap.add_argument("--release", default=str(here / "release" / "v2"))
    ap.add_argument("--db", default="peyvand_fresh_test")
    ap.add_argument("--db-host", default="127.0.0.1:3306")
    ap.add_argument("--db-user", default="root")
    ap.add_argument("--db-pass", default="")
    ap.add_argument("--port", type=int, default=8098)
    ap.add_argument("--admin-user", default="admin")
    ap.add_argument("--admin-pass", default="FreshTest!2026")
    ap.add_argument("--json-out", default="")
    args = ap.parse_args()

    args.url = f"http://127.0.0.1:{ut.find_free_port(args.port)}"
    workdir = Path(args.workdir).expanduser().resolve()
    site = workdir / "site"
    release = Path(args.release).resolve()

    rows: list[tuple[str, bool, str]] = []
    result: dict = {"url": args.url, "site": str(site), "db": args.db, "release": str(release)}
    print(f"→ سایت تست: {site}\n→ دیتابیس: {args.db}\n→ URL: {args.url}\n")

    # ۱) دیتابیس + وردپرس خالی
    workdir.mkdir(parents=True, exist_ok=True)
    db_script = workdir / "create-db.php"
    db_script.write_text(ut.PHP_CREATE_DB, encoding="utf-8")
    subprocess.run(
        [args.php, str(db_script), args.db_host, args.db_user, args.db_pass, args.db],
        check=True,
    )

    ut.prepare_site(args, workdir, site)
    runner = ut.Runner(args.php, args.wp_cli, site)
    ut.install_wordpress(args, runner)
    ut.check(rows, "نصب وردپرس روی دیتابیس خالی", True, args.db)

    # ۲) نصب ۱۱ بستهٔ v2 از صفر
    ut.install_plugins(release, site, runner)
    manifest = json.loads((release / "manifests" / "release-manifest.json").read_text(encoding="utf-8"))
    expected = {p["slug"]: p.get("version_source") for p in manifest["plugins"]}

    active = runner.wp("plugin", "list", "--status=active", "--field=name", check=False).stdout
    active_slugs = [s for s in re.split(r"\s+", active) if s in ut.PLUGINS]
    ut.check(rows, "فعال‌سازی ۱۱ افزونه", len(active_slugs) == len(ut.PLUGINS),
             f"{len(active_slugs)}/{len(ut.PLUGINS)}")

    # ۳) دادهٔ نمونه + بررسی ساختار/رفتار
    seed = ut.json_from(runner.php_eval(ut.PHP_SEED))
    result["seed"] = seed
    ut.check(rows, "ساخت دادهٔ نمونه روی نصب تازه",
             bool(seed.get("case_id")) and seed["submit"]["success"], str(seed["versions"]))

    state = ut.json_from(runner.php_eval(ut.PHP_INSPECT))
    result["state"] = state

    ut.check(rows, "نسخهٔ core", state["versions"]["core"] == expected.get("pivand-amn-core"),
             str(state["versions"]))
    ut.check(rows, "نسخهٔ case-file", state["versions"]["case"] == expected.get("pivand-amn-case-file"),
             str(state["versions"]))
    ut.check(rows, "نسخهٔ quiz", state["versions"]["quiz"] == expected.get("pivand-amn-quiz-enterprise"),
             str(state["versions"]))

    required_tables = ("pas_cf_cases", "pas_cf_notes", "psy_invitations", "psy_attempts",
                       "pas_events", "pas_cf_alerts", "pas_cf_tasks", "pas_cf_alert_history")
    ut.check(rows, "همهٔ جدول‌های لازم ساخته شدند",
             all(state["tables"].get(t) for t in required_tables),
             str({t: state["tables"].get(t) for t in required_tables}))
    ut.check(rows, "ستون client_id در سه جدول",
             all(state["has_client_id"].values()), str(state["has_client_id"]))
    ut.check(rows, "db_versionها ثبت شدند",
             bool(state["db_options"]["events"]) and state["db_options"]["cf"] == expected.get("pivand-amn-case-file"),
             str(state["db_options"]))

    note = state.get("note") or {}
    ut.check(rows, "یادداشت جلسه رمزنگاری‌شده است", note.get("plaintext_in_db") is False, str(note))
    ut.check(rows, "یادداشت جلسه رمزگشایی می‌شود", note.get("decrypts_to_expected") is True, str(note))

    identity = state.get("identity") or {}
    ut.check(rows, "هویت مراجع ساخته و پایدار شد",
             (identity.get("clients") or 0) >= 1 and bool(identity.get("for_old_phone")),
             str(identity))

    flow = state.get("flow") or {}
    ut.check(rows, "ثبت آزمون از مسیر واقعی REST",
             flow.get("submit_status") == 200 and flow.get("submit_success") is True,
             f"status={flow.get('submit_status')}")
    ut.check(rows, "شناسهٔ دعوت‌نامه به attempt منتقل شد",
             bool(flow.get("invitation_client_id"))
             and flow.get("invitation_client_id") == flow.get("attempt_client_id"),
             str(flow.get("invitation_client_id")))
    event = flow.get("event") or {}
    ut.check(rows, "رویداد assessment.completed منتشر شد",
             event.get("name") == "assessment.completed" and event.get("status") == "dispatched",
             str(event.get("payload")))
    ut.check(rows, "payload رویداد بدون پاسخ/نمره",
             "answer" not in (event.get("payload") or "").lower()
             and "result" not in (event.get("payload") or "").lower(),
             str(event.get("payload")))
    alert = flow.get("alert") or {}
    ut.check(rows, "هشدار self_harm_risk با اولویت critical",
             alert.get("type") == "self_harm_risk" and alert.get("priority") == "critical",
             str(alert))
    ut.check(rows, "وظیفهٔ بررسی نتیجه و بررسی هشدار",
             "assessment_review" in (flow.get("tasks") or [])
             and "alert_review" in (flow.get("tasks") or []),
             str(flow.get("tasks")))
    ut.check(rows, "شمارندهٔ هشدار/وظیفهٔ باز برای مدیر",
             (flow.get("open_alerts") or 0) >= 1 and (flow.get("open_tasks") or 0) >= 2,
             f"alerts={flow.get('open_alerts')} tasks={flow.get('open_tasks')}")

    # ۴) صفحات واقعی HTTP
    creds = {"user": args.admin_user, "pass": args.admin_pass}
    pages = [
        ("dashboard", "/wp-admin/admin.php?page=pas-case-dashboard", "هشدارهای نیازمند بررسی"),
        ("case", f"/wp-admin/admin.php?page=pas-case-file-new&case_id={seed['case_id']}", "pas-cf-alerts"),
        ("alerts", "/wp-admin/admin.php?page=pas-case-alerts&view=open", "پرچم خودآسیبی"),
        ("list", "/wp-admin/admin.php?page=pas-case-files", "پرونده"),
        ("exam", f"/?psy_exam=1&token={seed['unused_token']}", "psy-question-card"),
    ]
    server = ut.start_server(args.php, site, args.url)
    try:
        http_result = ut.run_http_phase(args.url, creds, pages)
    finally:
        ut.stop_server(server)
    result["http"] = http_result

    login = http_result["_login"]
    ut.check(rows, "ورود مدیر", login["status"] == 302 and not login["is_error"],
             f"status={login['status']}")
    for label, data in http_result.items():
        if label.startswith("_"):
            continue
        ut.check(rows, f"صفحهٔ {label}",
                 data["status"] == 200 and data["has_marker"] and not data["fatal"],
                 f"{data['status']} / {data['bytes']}B")

    # ۵) خطاهای PHP
    log_path = site / "wp-content" / "debug.log"
    log_lines = log_path.read_text(encoding="utf-8", errors="replace").splitlines() if log_path.exists() else []
    ours = [ln for ln in log_lines
            if ("Fatal error" in ln) or re.search(r"(pivand|payvand|pas_cf|pas_events|psy_)", ln, re.I)]
    ut.check(rows, "debug.log بدون خط مربوط به افزونه‌ها", not ours,
             f"{len(log_lines)} خط، {len(ours)} مربوط به افزونه")

    # ۶) جدول نتیجه
    passed = sum(1 for _, ok, _ in rows if ok)
    width = max(len(name) for name, _, _ in rows) + 2
    print(f"\n{'بررسی':<{width}}نتیجه   جزئیات")
    print("-" * (width + 40))
    for name, ok, detail in rows:
        print(f"{name:<{width}}{'✅' if ok else '❌'}      {detail}")
    print("-" * (width + 40))
    print(f"نتیجه: {passed}/{len(rows)} بررسی موفق — {'PASS' if passed == len(rows) else 'FAIL'}")

    result["checks"] = [{"name": n, "ok": ok, "detail": d} for n, ok, d in rows]
    result["passed"] = passed
    result["total"] = len(rows)
    result["status"] = "PASS" if passed == len(rows) else "FAIL"
    if args.json_out:
        out = Path(args.json_out).expanduser()
        out.parent.mkdir(parents=True, exist_ok=True)
        out.write_text(json.dumps(result, ensure_ascii=False, indent=2), encoding="utf-8")
        print(f"JSON: {out}")

    return 0 if passed == len(rows) else 1


if __name__ == "__main__":
    sys.exit(main())
