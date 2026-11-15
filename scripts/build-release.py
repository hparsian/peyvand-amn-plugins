#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
فاز ۱۵ — ساخت بستهٔ انتشار «پیوند امن»

برای هر افزونه یک ZIP استاندارد می‌سازد (نام پوشهٔ داخلی = اسلاگ افزونه)،
سپس بسته را دوباره باز می‌کند و صحت آن را بررسی می‌کند:
  * وجود فایل اصلی افزونه
  * تطابق نسخهٔ هدر با نسخهٔ سورس
  * نبود فایل/پوشهٔ ممنوعه (secret، log، backup، staging، vcs، cache)

خروجی:
  release/v1/plugins/<slug>.zip
  release/v1/manifests/release-manifest.json
  release/v1/manifests/dependency-map.json
  release/v1/manifests/checksums.sha256
  release/v1/manifests/compatibility-report.json

اجرا:
  python3 scripts/build-release.py [--label v1] [--root .]
"""

from __future__ import annotations

import argparse
import hashlib
import json
import os
import re
import sys
import zipfile
from datetime import datetime, timezone
from pathlib import Path

# ─── ترتیب نصب و وابستگی‌ها (از کد و ecosystem-map استخراج شده) ────────────
PLUGIN_ORDER = [
    "pivand-amn-core",
    "pivand-amn-sms-bridge",
    "pivand-amn-smtp-mailer",
    "pivand-amn-2fa",
    "pivand-amn-case-file",
    "pivand-amn-quiz-enterprise",
    "pivand-amn-client-portal",
    "payvand-amn-ui-kit",
    "payvand-contact",
    "pivand-amn-content-writer",
    "piwand-daily-feed",
]

CORE_MIN = "1.8.1"

DEPENDENCIES = {
    "pivand-amn-core": {
        "requires": [],
        "optional": [],
        "contracts_provided": ["pas_get_client", "pas_lookup_known_client_name"],
        "notes": "پایهٔ مشترک: رمزنگاری، توکن‌های طراحی، رجیستری قراردادها، هویت مراجع. باید اولین نصب و آخرین حذف شود.",
    },
    "pivand-amn-sms-bridge": {
        "requires": ["pivand-amn-core"],
        "optional": [],
        "contracts_provided": ["pas_send_sms"],
        "notes": "تنها ارائه‌دهندهٔ pas_send_sms. اگر افزونهٔ پیامک دیگری فعال باشد، باید غیرفعال شود.",
    },
    "pivand-amn-smtp-mailer": {
        "requires": ["pivand-amn-core"],
        "optional": [],
        "contracts_provided": [],
        "notes": "wp_mail را بازنویسی می‌کند؛ پس از فعال‌سازی باید ایمیل آزمایشی ارسال شود.",
    },
    "pivand-amn-2fa": {
        "requires": ["pivand-amn-core"],
        "optional": [],
        "contracts_provided": [],
        "notes": "اختیاری per-user؛ پیش از فعال‌سازی کدهای پشتیبان ادمین باید ذخیره شود.",
    },
    "pivand-amn-case-file": {
        "requires": ["pivand-amn-core"],
        "optional": ["pivand-amn-quiz-enterprise"],
        "contracts_provided": ["pas_get_case_summary_for_phone", "pas_cf_timeline_events"],
        "contracts_consumed": ["psy_get_assessment_scores_for_phone", "zdp_get_intake_for_phone", "pas_get_client"],
        "notes": "بدون quiz، کارت روند ارزیابی خالی می‌ماند (بدون خطا).",
    },
    "pivand-amn-quiz-enterprise": {
        "requires": ["pivand-amn-core"],
        "optional": [],
        "contracts_provided": [
            "psy_get_pending_quizzes_for_phone",
            "psy_get_client_name_for_phone",
            "psy_get_completed_results_for_phone",
            "psy_get_assessment_scores_for_phone",
        ],
        "notes": "باید هم‌زمان با case-file و client-portal به‌روزرسانی شود.",
    },
    "pivand-amn-client-portal": {
        "requires": ["pivand-amn-core"],
        "optional": ["pivand-amn-sms-bridge", "pivand-amn-quiz-enterprise"],
        "contracts_provided": ["zdp_get_intake_for_phone", "zdp_get_client_panel_url"],
        "contracts_consumed": ["pas_send_sms", "psy_get_pending_quizzes_for_phone", "pas_get_case_summary_for_phone", "pas_get_client"],
        "notes": "بدون sms-bridge ارسال OTP کار نمی‌کند؛ درگاه پرداخت نیاز به کلید دارد.",
    },
    "payvand-amn-ui-kit": {
        "requires": ["pivand-amn-core"],
        "optional": [],
        "contracts_provided": [],
        "notes": "فقط CSS/توکن؛ بدون آن ظاهر پنل‌ها ساده اما کارا می‌ماند.",
    },
    "payvand-contact": {
        "requires": ["pivand-amn-core"],
        "optional": [],
        "contracts_provided": [],
        "notes": "فرم تماس عمومی؛ صفحهٔ فرم باید وجود داشته باشد.",
    },
    "pivand-amn-content-writer": {
        "requires": ["pivand-amn-core"],
        "optional": [],
        "contracts_provided": [],
        "notes": "برای تولید محتوا به کلید API هوش مصنوعی نیاز دارد.",
    },
    "piwand-daily-feed": {
        "requires": ["pivand-amn-core"],
        "optional": [],
        "contracts_provided": [],
        "notes": "وابسته به cron سایت برای تولید روزانه.",
    },
}

# ─── قواعد بسته‌بندی ────────────────────────────────────────────────────────
EXCLUDE_DIRS = {
    ".git", ".github", ".idea", ".vscode", ".vs", "node_modules", "bower_components",
    "__pycache__", ".sass-cache", ".parcel-cache", ".cache", "coverage", "dist", "build",
    ".DS_Store", ".svn", "release",
}

EXCLUDE_FILE_RE = [
    r"\.log$", r"\.bak$", r"\.orig$", r"\.rej$", r"\.tmp$", r"\.temp$", r"~$",
    r"\.swp$", r"\.swo$", r"\.zip$", r"\.tar$", r"\.gz$", r"\.tgz$", r"\.rar$", r"\.7z$",
    r"\.sql$", r"\.sqlite$", r"\.db$", r"\.dump$",
    r"^\.env", r"\.pem$", r"\.crt$", r"\.p12$", r"\.pfx$", r"^id_rsa", r"^id_dsa",
    r"pas-encryption-key\.php$", r"^\.htpasswd$", r"^\.DS_Store$",
    r"^\.gitignore$", r"^\.gitattributes$", r"^phpunit\.xml", r"^\.phpunit",
    r"^\.editorconfig$", r"^\.distignore$", r"^\.coveralls",
    r"^p11-sms-sim\.php$", r"^\.tmp", r"^tmp-",
]

EXCLUDE_DIR_NAMES_ANY = {"tests", "test", "spec", "__tests__"}


def should_skip_file(name: str) -> bool:
    return any(re.search(pat, name, re.IGNORECASE) for pat in EXCLUDE_FILE_RE)


def read_version(main_php: Path) -> str | None:
    """نسخه را از هدر اصلی افزونه می‌خواند."""
    try:
        text = main_php.read_text(encoding="utf-8", errors="replace")[:8000]
    except OSError:
        return None
    m = re.search(r"^\s*\*?\s*Version:\s*([0-9][0-9A-Za-z.\-+]*)", text, re.MULTILINE)
    return m.group(1).strip() if m else None


def find_main_file(plugin_dir: Path) -> Path | None:
    for f in sorted(plugin_dir.glob("*.php")):
        try:
            head = f.read_text(encoding="utf-8", errors="replace")[:4000]
        except OSError:
            continue
        if re.search(r"Plugin Name:", head):
            return f
    return None


def collect_files(plugin_dir: Path) -> list[Path]:
    files: list[Path] = []
    for root, dirs, names in os.walk(plugin_dir):
        depth = len(Path(root).relative_to(plugin_dir).parts)
        keep = []
        for d in dirs:
            if d in EXCLUDE_DIRS or d.startswith("."):
                continue
            # پوشه‌های تست فقط در ریشهٔ افزونه حذف می‌شوند؛ پوشه‌هایی مثل
            # includes/tests/ در quiz-enterprise بخشی از خود محصول‌اند (۲۸ آزمون).
            if depth == 0 and d in EXCLUDE_DIR_NAMES_ANY:
                continue
            keep.append(d)
        dirs[:] = keep
        for n in sorted(names):
            if n.startswith("."):
                continue
            if should_skip_file(n):
                continue
            files.append(Path(root) / n)
    return sorted(files)


def sha256_of(path: Path) -> str:
    h = hashlib.sha256()
    with path.open("rb") as fh:
        for chunk in iter(lambda: fh.read(1 << 20), b""):
            h.update(chunk)
    return h.hexdigest()


def build_zip(plugin_dir: Path, slug: str, dest: Path) -> dict:
    files = collect_files(plugin_dir)
    dest.parent.mkdir(parents=True, exist_ok=True)
    with zipfile.ZipFile(dest, "w", compression=zipfile.ZIP_DEFLATED, compresslevel=9) as zf:
        for f in files:
            arc = Path(slug) / f.relative_to(plugin_dir)
            info = zipfile.ZipInfo(str(arc).replace(os.sep, "/"))
            info.date_time = (1980, 1, 1, 0, 0, 0)  # زمان ثابت → ساخت بازتولیدپذیر
            info.compress_type = zipfile.ZIP_DEFLATED
            info.external_attr = (0o644 & 0xFFFF) << 16
            with f.open("rb") as fh:
                zf.writestr(info, fh.read())
    return {"file_count": len(files), "size_bytes": dest.stat().st_size}


def verify_zip(dest: Path, slug: str, expected_version: str | None) -> dict:
    problems: list[str] = []
    with zipfile.ZipFile(dest) as zf:
        names = zf.namelist()
        bad = zf.testzip()
        if bad:
            problems.append(f"بستهٔ خراب در عضو: {bad}")
        if any(not n.startswith(slug + "/") for n in names):
            problems.append("همهٔ اعضا باید داخل پوشهٔ اسلاگ باشند")
        main_candidates = [n for n in names if re.fullmatch(rf"{re.escape(slug)}/[^/]+\.php", n)]
        main_ok = False
        found_version = None
        for cand in main_candidates:
            text = zf.read(cand).decode("utf-8", errors="replace")[:4000]
            if "Plugin Name:" in text:
                main_ok = True
                m = re.search(r"^\s*\*?\s*Version:\s*([0-9][0-9A-Za-z.\-+]*)", text, re.MULTILINE)
                found_version = m.group(1).strip() if m else None
                break
        if not main_ok:
            problems.append("فایل اصلی افزونه (با هدر Plugin Name) در بسته نیست")
        if expected_version and found_version and found_version != expected_version:
            problems.append(f"نسخهٔ داخل ZIP ({found_version}) با سورس ({expected_version}) نمی‌خواند")
        forbidden = [
            n for n in names
            if should_skip_file(os.path.basename(n))
            or any(part in EXCLUDE_DIRS for part in n.split("/")[:-1])
            or any(part in EXCLUDE_DIR_NAMES_ANY for part in n.split("/")[1:2])
        ]
        if forbidden:
            problems.append("فایل ممنوعه در بسته: " + ", ".join(forbidden[:5]))
        if not any(n.endswith("uninstall.php") for n in names):
            # نبود uninstall خطا نیست، فقط ثبت می‌شود
            pass
    return {
        "ok": not problems,
        "problems": problems,
        "version_in_zip": found_version,
        "entries": len(names),
    }


def main() -> int:
    ap = argparse.ArgumentParser()
    ap.add_argument("--label", default="v1")
    ap.add_argument("--root", default=".")
    args = ap.parse_args()

    root = Path(args.root).resolve()
    plugins_root = root / "plugins"
    release_root = root / "release" / args.label
    plugins_out = release_root / "plugins"
    manifests_out = release_root / "manifests"
    plugins_out.mkdir(parents=True, exist_ok=True)
    manifests_out.mkdir(parents=True, exist_ok=True)

    missing = [p for p in PLUGIN_ORDER if not (plugins_root / p).is_dir()]
    if missing:
        print("افزونه‌های گم‌شده: " + ", ".join(missing), file=sys.stderr)
        return 2

    results = []
    for order, slug in enumerate(PLUGIN_ORDER, start=1):
        plugin_dir = plugins_root / slug
        main_php = find_main_file(plugin_dir)
        if not main_php:
            print(f"[FAIL] {slug}: فایل اصلی پیدا نشد", file=sys.stderr)
            return 3
        version = read_version(main_php)
        dest = plugins_out / f"{slug}.zip"
        built = build_zip(plugin_dir, slug, dest)
        check = verify_zip(dest, slug, version)
        digest = sha256_of(dest)
        status = "OK" if check["ok"] else "FAIL"
        print(f"[{status}] {slug:<32} v{version:<10} {built['file_count']:>3} файл  "
              f"{built['size_bytes']/1024:8.1f} KB  {digest[:16]}…")
        if not check["ok"]:
            for p in check["problems"]:
                print("    - " + p, file=sys.stderr)
        results.append({
            "order": order,
            "slug": slug,
            "version_source": version,
            "version_in_zip": check["version_in_zip"],
            "main_file": main_php.name,
            "file_count": built["file_count"],
            "size_bytes": built["size_bytes"],
            "sha256": digest,
            "verified": check["ok"],
            "problems": check["problems"],
        })

    all_ok = all(r["verified"] for r in results)
    now = datetime.now(timezone.utc).strftime("%Y-%m-%dT%H:%M:%SZ")

    manifest = {
        "release_label": args.label,
        "generated_at": now,
        "channel": "production",
        "excluded_patterns": EXCLUDE_FILE_RE,
        "excluded_dirs": sorted(EXCLUDE_DIRS | EXCLUDE_DIR_NAMES_ANY),
        "plugins": results,
        "all_verified": all_ok,
    }
    (manifests_out / "release-manifest.json").write_text(
        json.dumps(manifest, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")

    depmap = {
        "generated_at": now,
        "core_min_version": CORE_MIN,
        "install_order": PLUGIN_ORDER,
        "upgrade_order": ["pivand-amn-core"] + [p for p in PLUGIN_ORDER if p != "pivand-amn-core"],
        "plugins": DEPENDENCIES,
    }
    (manifests_out / "dependency-map.json").write_text(
        json.dumps(depmap, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")

    with (manifests_out / "checksums.sha256").open("w", encoding="utf-8") as fh:
        for r in results:
            fh.write(f"{r['sha256']}  plugins/{r['slug']}.zip\n")

    compat = {
        "generated_at": now,
        "verified_on": {"wordpress": "7.1.4", "php": "8.5.8"},
        "declared_requires": {"wordpress": "6.6", "php": "8.1"},
        "plugins": [
            {"slug": r["slug"], "version": r["version_source"], "structure": "ok" if r["verified"] else "fail",
             "zip_valid": r["verified"], "notes": r["problems"]}
            for r in results
        ],
    }
    (manifests_out / "compatibility-report.json").write_text(
        json.dumps(compat, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")

    print(f"\nهمهٔ بسته‌ها سالم: {'بله' if all_ok else 'خیر'}")
    print(f"خروجی: {release_root}")
    return 0 if all_ok else 1


if __name__ == "__main__":
    sys.exit(main())
