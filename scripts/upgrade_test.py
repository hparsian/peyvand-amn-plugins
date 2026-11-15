#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
آزمون خودکار ارتقای پیوند امن از release/v1 به release/v2.

چه کاری می‌کند (همه در یک مسیر و یک دیتابیس جدا، بیرون از مخزن):
  ۱. یک وردپرس تازه از روی یک نصب موجود کپی می‌کند (بدون wp-config و پلاگین‌ها).
  ۲. دیتابیس تازه می‌سازد و وردپرس را نصب می‌کند.
  ۳. بسته‌های release/v1 را نصب/فعال می‌کند و با همان کد v1 دادهٔ نمونه می‌سازد:
     سه پرونده، یک یادداشت جلسه، دو دعوت‌نامه و یک ثبت BDI با آیتم خودکشی = ۳.
  ۴. وضعیت پایه را ثبت می‌کند (نسخه‌ها، ستون‌ها، جدول‌ها، شمارش‌ها).
  ۵. بسته‌های release/v2 را جای v1 می‌گذارد و فعال می‌کند.
  ۶. وضعیت بعد از ارتقا + جریان کامل جدید (دعوت‌نامه → ثبت → رویداد → هشدار
     → وظیفه) و صفحات واقعی HTTP را بررسی می‌کند.
  ۷. جدول نتیجه چاپ می‌کند و با کد خروج ۱ تمام می‌شود اگر هر بررسی شکست بخورد.

اجرا (نمونه، همان محیط Staging):
  python3 scripts/upgrade_test.py \
    --php ~/phase11/php/php --wp-cli ~/phase11/wp-cli.phar \
    --wp-source ~/phase11/web --workdir ~/phase17-upgrade \
    --db phase17_upgrade --db-host 127.0.0.1:3307 --db-user root --db-pass '' \
    --port 8099

نکته‌ها:
  * به mysqld/mysql کلاینت نیاز ندارد؛ دیتابیس با mysqli خود PHP ساخته می‌شود.
  * سرور تست با `opcache.enable=0` اجرا می‌شود (وگرنه بعد از تعویض فایل‌ها،
    PHP ممکن است کد قدیمی را سرو کند و نتیجهٔ آزمون گمراه‌کننده شود).
  * هیچ داده‌ای از سایت مبدأ خوانده/تغییر داده نمی‌شود؛ فقط فایل‌های هستهٔ
    وردپرس کپی می‌شوند.
"""

from __future__ import annotations

import argparse
import http.cookiejar
import json
import os
import re
import shutil
import signal
import socket
import subprocess
import sys
import time
import urllib.error
import urllib.parse
import urllib.request
import zipfile
from pathlib import Path

PLUGINS = [
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

IGNORE = shutil.ignore_patterns(
    "wp-config.php", "plugins", "uploads", "cache", "upgrade", "debug.log", "*.log",
)

# ─── قطعه‌های PHP ────────────────────────────────────────────────────────────

PHP_CREATE_DB = r"""
<?php
[$h, $port] = array_pad(explode(':', $argv[1]), 2, '3306');
$m = new mysqli($h, $argv[2], $argv[3], '', (int) $port);
if ($m->connect_errno) { fwrite(STDERR, "connect failed: {$m->connect_error}\n"); exit(1); }
$db = $argv[4];
$m->query("DROP DATABASE IF EXISTS `$db`");
if (!$m->query("CREATE DATABASE `$db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci")) {
    fwrite(STDERR, "create failed: {$m->error}\n"); exit(1);
}
echo "db-ready\n";
"""

PHP_SEED = r"""
<?php
/** دادهٔ نمونه با کد همان نسخه‌ای که الان نصب است (v1) — فقط APIهای پایدار. */
global $wpdb;
$p = $wpdb->prefix;
$o = ['versions' => [
    'core' => defined('PAS_CORE_VERSION') ? PAS_CORE_VERSION : null,
    'case' => defined('PAS_CF_VERSION') ? PAS_CF_VERSION : null,
    'quiz' => defined('PSY_VERSION') ? PSY_VERSION : null,
]];
$o['case_ids'] = [];
foreach ([['09121110000', 'مراجع الف'], ['09122220000', 'مراجع ب'], ['09123330000', 'مراجع ج']] as [$phone, $name]) {
    $o['case_ids'][] = Pas_CF_Case_File::create(['phone' => $phone, 'full_name' => $name]);
}
$o['case_id'] = (int) $o['case_ids'][0];

// یادداشت جلسه — امضای add_note بین نسخه‌ها تفاوت دارد، پس با reflection.
$ref = new ReflectionMethod('Pas_CF_Case_File', 'add_note');
$second = $ref->getParameters()[1] ?? null;
$typed = $second && $second->hasType() ? (string) $second->getType() : '';
$o['note_id'] = (int) ($typed === 'string'
    ? Pas_CF_Case_File::add_note($o['case_id'], current_time('Y-m-d'), 'یادداشت آزمون ارتقا', 1)
    : Pas_CF_Case_File::add_note($o['case_id'], ['session_date' => current_time('Y-m-d'), 'content' => 'یادداشت آزمون ارتقا', 'session_number' => 1]));

add_filter('pre_wp_mail', '__return_true');

// دعوت‌نامهٔ مصرف‌نشده (برای امتحان کردن توکن قدیمی بعد از ارتقا)
$o['unused_token'] = Psy_Quiz_Invitation_Manager::create('csi', 'مراجع الف', '09121110000', 3)['token'];

// یک آزمون کامل‌شده روی کد v1 (بدون رویداد/هشدار، چون آن‌ها در v1 نیستند)
$test = \PsychAssessments\Core\Registry::get('bdi');
$answers = [];
foreach (array_keys($test->get_questions()) as $qid) { $answers[$qid] = 1; }
$answers[9] = 3;
$inv = Psy_Quiz_Invitation_Manager::create('bdi', 'مراجع الف', '09121110000', 3);
$req = new WP_REST_Request('POST', '/psy/v1/submit');
$req->set_header('content-type', 'application/json');
$req->set_body(wp_json_encode(['token' => $inv['token'], 'answers' => $answers]));
$res = rest_do_request($req);
$data = $res->get_data();
$o['submit'] = ['status' => $res->get_status(), 'success' => !empty($data['success']), 'attempt_id' => (int) ($data['attempt_id'] ?? 0)];

echo wp_json_encode($o, JSON_UNESCAPED_UNICODE);
"""

PHP_INSPECT = r"""
<?php
/** وضعیت نسخه/ساختار/داده — طوری نوشته شده که در v1 (بدون جدول/ستون جدید) هم بترکد. */
global $wpdb;
$p = $wpdb->prefix;
$o = [];
$o['versions'] = [
    'core' => defined('PAS_CORE_VERSION') ? PAS_CORE_VERSION : null,
    'case' => defined('PAS_CF_VERSION') ? PAS_CF_VERSION : null,
    'quiz' => defined('PSY_VERSION') ? PSY_VERSION : null,
];
$o['db_options'] = [
    'core'   => get_option('pas_core_db_version'),
    'events' => get_option('pas_events_db_version'),
    'cf'     => get_option('pas_cf_db_version'),
];
$tables = ['pas_cf_cases', 'pas_cf_notes', 'psy_invitations', 'psy_attempts', 'pas_events', 'pas_cf_alerts', 'pas_cf_tasks', 'pas_cf_alert_history'];
$o['tables'] = [];
foreach ($tables as $t) {
    $o['tables'][$t] = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $p . $t)) === $p . $t;
}
$cols = function (string $t) use ($wpdb, $p): array {
    if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $p . $t)) !== $p . $t) { return []; }
    $out = [];
    foreach ((array) $wpdb->get_results("SHOW COLUMNS FROM {$p}{$t}") as $c) { $out[] = (string) $c->Field; }
    return $out;
};
$o['columns'] = [
    'pas_cf_cases'    => $cols('pas_cf_cases'),
    'psy_invitations' => $cols('psy_invitations'),
    'psy_attempts'    => $cols('psy_attempts'),
];
$o['has_client_id'] = [
    'pas_cf_cases'    => in_array('client_id', $o['columns']['pas_cf_cases'], true),
    'psy_invitations' => in_array('client_id', $o['columns']['psy_invitations'], true),
    'psy_attempts'    => in_array('client_id', $o['columns']['psy_attempts'], true),
];
$count = function (string $t) use ($wpdb, $p): int {
    return (int) $wpdb->get_var("SELECT COUNT(*) FROM {$p}{$t}");
};
$o['counts'] = [
    'cases'    => $count('pas_cf_cases'),
    'notes'    => $count('pas_cf_notes'),
    'invites'  => $count('psy_invitations'),
    'attempts' => $count('psy_attempts'),
    'events'   => $o['tables']['pas_events'] ? $count('pas_events') : 0,
    'alerts'   => $o['tables']['pas_cf_alerts'] ? $count('pas_cf_alerts') : 0,
    'tasks'    => $o['tables']['pas_cf_tasks'] ? $count('pas_cf_tasks') : 0,
];
$o['old_rows_without_client_id'] = [];
foreach (['pas_cf_cases', 'psy_invitations', 'psy_attempts'] as $t) {
    $o['old_rows_without_client_id'][$t] = $o['has_client_id'][$t]
        ? (int) $wpdb->get_var("SELECT COUNT(*) FROM {$p}{$t} WHERE client_id = ''")
        : null;
}

// یادداشت نباید در دیتابیس plaintext باشد و باید قابل خواندن بماند
$note = $wpdb->get_row("SELECT id, content_encrypted FROM {$p}pas_cf_notes ORDER BY id LIMIT 1");
$o['note'] = null;
if ($note) {
    $plain_in_db = strpos((string) $note->content_encrypted, 'یادداشت آزمون ارتقا') !== false;
    $decrypted = class_exists('Pas_CF_Crypto') ? Pas_CF_Crypto::decrypt_or_plain((string) $note->content_encrypted) : '';
    $o['note'] = [
        'plaintext_in_db' => $plain_in_db,
        'decrypts_to_expected' => $decrypted === 'یادداشت آزمون ارتقا',
    ];
}

// جریان جدید (فقط نسخه‌ای که این قراردادها را دارد)
$o['flow'] = null;
if (has_filter('psy_get_assessment_risk_flags') && class_exists('Pas_Core_Events')) {
    add_filter('pre_wp_mail', '__return_true');
    $test = \PsychAssessments\Core\Registry::get('bdi');
    $answers = [];
    foreach (array_keys($test->get_questions()) as $qid) { $answers[$qid] = 1; }
    $answers[9] = 3;
    $inv = Psy_Quiz_Invitation_Manager::create('bdi', 'مراجع الف', '09121110000', 3);
    $inv_row = $wpdb->get_row($wpdb->prepare("SELECT client_id FROM {$p}psy_invitations WHERE id = %d", (int) $inv['id']));
    $req = new WP_REST_Request('POST', '/psy/v1/submit');
    $req->set_header('content-type', 'application/json');
    $req->set_body(wp_json_encode(['token' => $inv['token'], 'answers' => $answers]));
    $res = rest_do_request($req);
    $data = $res->get_data();
    $attempt_id = (int) ($data['attempt_id'] ?? 0);
    $att = $wpdb->get_row($wpdb->prepare("SELECT client_id FROM {$p}psy_attempts WHERE id = %d", $attempt_id));
    $event = $wpdb->get_row("SELECT name, status, client_id, payload FROM {$p}pas_events ORDER BY id DESC LIMIT 1", ARRAY_A);
    $alert = $wpdb->get_row("SELECT case_id, type, priority, status FROM {$p}pas_cf_alerts ORDER BY id DESC LIMIT 1", ARRAY_A);
    $tasks = $wpdb->get_col("SELECT type FROM {$p}pas_cf_tasks ORDER BY id");
    $o['flow'] = [
        'submit_status'        => $res->get_status(),
        'submit_success'       => !empty($data['success']),
        'attempt_id'           => $attempt_id,
        'invitation_client_id' => (string) ($inv_row->client_id ?? ''),
        'attempt_client_id'    => (string) ($att->client_id ?? ''),
        'event'                => $event,
        'alert'                => $alert,
        'tasks'                => $tasks,
        'open_alerts'          => Pas_CF_Alerts::count_open_visible(),
        'open_tasks'           => Pas_CF_Tasks::count_open_visible(),
    ];
}

// هویت (فقط نسخه‌ای که سرویس هویت دارد)
$o['identity'] = null;
if (class_exists('Pas_Core_Identity') && class_exists('Pas_Core_Client_Registry')) {
    $rb = Pas_Core_Client_Registry::rebuild();
    $o['identity'] = [
        'clients'      => (int) ($rb['clients'] ?? 0),
        'identifiers'  => (int) ($rb['identifiers'] ?? 0),
        'conflicts'    => (int) ($rb['conflicts'] ?? 0),
        'for_old_phone'=> Pas_Core_Identity::client_id_for_phone('09121110000'),
    ];
}

echo wp_json_encode($o, JSON_UNESCAPED_UNICODE);
"""


# ─── ابزارها ─────────────────────────────────────────────────────────────────

class Runner:
    def __init__(self, php: str, wp_cli: str, site: Path):
        self.php = php
        self.wp_cli = wp_cli
        self.site = site

    def wp(self, *args: str, check: bool = True) -> subprocess.CompletedProcess:
        cmd = [self.php, self.wp_cli, f"--path={self.site}", "--allow-root", *args]
        return subprocess.run(cmd, capture_output=True, text=True, check=check)

    def php_eval(self, code: str) -> str:
        tmp = self.site / ".eval-tmp.php"
        tmp.write_text(code, encoding="utf-8")
        try:
            res = self.wp("eval-file", str(tmp), check=False)
        finally:
            tmp.unlink(missing_ok=True)
        if res.returncode != 0:
            raise RuntimeError(f"PHP failed:\n{res.stdout[-1500:]}\n{res.stderr[-1500:]}")
        return res.stdout


def find_free_port(preferred: int) -> int:
    for candidate in (preferred, 0):
        with socket.socket() as s:
            try:
                s.bind(("127.0.0.1", candidate))
                return s.getsockname()[1]
            except OSError:
                continue
    raise RuntimeError("no free port")


class _NoRedirect(urllib.request.HTTPRedirectHandler):
    """ریدایرکت‌ها را دنبال نمی‌کند تا Set-Cookie پاسخ ۳۰۲ ورود از دست نرود."""

    def redirect_request(self, req, fp, code, msg, headers, newurl):
        return None


class Client:
    """کلاینت HTTP با cookiejar واقعی (ورود وردپرس ریدایرکت ۳۰۲ دارد)."""

    def __init__(self) -> None:
        self.jar = http.cookiejar.CookieJar()
        self.opener = urllib.request.build_opener(
            urllib.request.HTTPCookieProcessor(self.jar), _NoRedirect()
        )

    def request(self, url: str, data: dict | None = None, timeout: int = 25):
        body = urllib.parse.urlencode(data).encode() if data else None
        try:
            with self.opener.open(urllib.request.Request(url, data=body), timeout=timeout) as resp:
                return resp.status, resp.read().decode("utf-8", "replace")
        except urllib.error.HTTPError as e:
            return e.code, e.read().decode("utf-8", "replace")


# ─── مراحل ───────────────────────────────────────────────────────────────────

def prepare_site(args, workdir: Path, site: Path) -> None:
    if site.exists():
        shutil.rmtree(site)
    shutil.copytree(args.wp_source, site, ignore=IGNORE, symlinks=True)
    (site / "wp-content" / "plugins").mkdir(parents=True, exist_ok=True)


def install_wordpress(args, runner: Runner) -> None:
    runner.wp(
        "config", "create",
        f"--dbname={args.db}", f"--dbuser={args.db_user}", f"--dbpass={args.db_pass}",
        f"--dbhost={args.db_host}", "--force",
    )
    runner.wp(
        "core", "install", f"--url={args.url}", "--title=Peyvand Upgrade Test",
        f"--admin_user={args.admin_user}", f"--admin_password={args.admin_pass}",
        "--admin_email=upgrade-test@example.test", "--skip-email",
    )
    for key, value in (("WP_DEBUG", "true"), ("WP_DEBUG_LOG", "true"), ("WP_DEBUG_DISPLAY", "false")):
        runner.wp("config", "set", key, value, "--raw")


def install_plugins(release_dir: Path, site: Path, runner: Runner) -> None:
    zips = sorted((release_dir / "plugins").glob("*.zip"))
    if not zips:
        raise RuntimeError(f"no plugin zips in {release_dir / 'plugins'}")
    for z in zips:
        with zipfile.ZipFile(z) as zf:
            zf.extractall(site / "wp-content" / "plugins")
    for slug in PLUGINS:
        runner.wp("plugin", "activate", slug, check=False)


def remove_plugins(site: Path, runner: Runner) -> None:
    for slug in PLUGINS:
        runner.wp("plugin", "deactivate", slug, check=False)
        shutil.rmtree(site / "wp-content" / "plugins" / slug, ignore_errors=True)


def json_from(output: str) -> dict:
    start = output.find("{")
    if start < 0:
        raise RuntimeError(f"no JSON in output:\n{output[-2000:]}")
    return json.loads(output[start:])


# ─── بررسی‌ها ────────────────────────────────────────────────────────────────

def check(rows: list, name: str, ok: bool, detail: str = "") -> None:
    rows.append((name, bool(ok), detail))


def start_server(php: str, site: Path, url: str, timeout: int = 40):
    port = int(url.rsplit(":", 1)[1])
    proc = subprocess.Popen(
        [php, "-d", "opcache.enable=0", "-S", f"127.0.0.1:{port}", "-t", str(site)],
        stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL, start_new_session=True,
    )
    deadline = time.time() + timeout
    while time.time() < deadline:
        try:
            with urllib.request.urlopen(url + "/", timeout=3):
                return proc
        except Exception:
            if proc.poll() is not None:
                break
            time.sleep(0.5)
    stop_server(proc)
    raise RuntimeError(f"server did not become ready at {url}")


def stop_server(proc) -> None:
    if proc is None or proc.poll() is not None:
        return
    try:
        os.killpg(proc.pid, signal.SIGTERM)
    except ProcessLookupError:
        return
    try:
        proc.wait(timeout=5)
    except subprocess.TimeoutExpired:
        os.killpg(proc.pid, signal.SIGKILL)
        proc.wait(timeout=5)


def run_http_phase(base: str, creds: dict, pages: list[tuple[str, str, str]]) -> dict:
    """pages: (label, path, expected_marker)"""
    client = Client()
    client.request(base + "/wp-login.php")
    login_status, login_body = client.request(
        base + "/wp-login.php",
        {"log": creds["user"], "pwd": creds["pass"], "wp-submit": "Log In",
         "redirect_to": base + "/wp-admin/", "testcookie": "1"},
    )
    out = {"_login": {"status": login_status, "is_error": "login_error" in login_body}}
    for label, path, marker in pages:
        status, body = client.request(base + path)
        out[label] = {
            "path": path,
            "status": status,
            "bytes": len(body),
            "has_marker": marker in body if marker else True,
            "fatal": "fatal error" in body.lower(),
        }
    return out


def main() -> int:
    here = Path(__file__).resolve().parent.parent
    ap = argparse.ArgumentParser(description="آزمون ارتقای v1→v2 پیوند امن")
    ap.add_argument("--php", required=True, help="مسیر باینری php")
    ap.add_argument("--wp-cli", required=True, help="مسیر wp-cli.phar")
    ap.add_argument("--wp-source", required=True, help="یک نصب وردپرس سالم برای کپی هسته")
    ap.add_argument("--workdir", required=True, help="مسیر ساخت سایت تست (بیرون از مخزن)")
    ap.add_argument("--v1", default=str(here / "release" / "v1"))
    ap.add_argument("--v2", default=str(here / "release" / "v2"))
    ap.add_argument("--db", default="peyvand_upgrade_test")
    ap.add_argument("--db-host", default="127.0.0.1:3306")
    ap.add_argument("--db-user", default="root")
    ap.add_argument("--db-pass", default="")
    ap.add_argument("--port", type=int, default=8099)
    ap.add_argument("--admin-user", default="admin")
    ap.add_argument("--admin-pass", default="UpgradeTest!2026")
    ap.add_argument("--json-out", default="", help="مسیر فایل JSON نتیجه (اختیاری)")
    args = ap.parse_args()

    args.url = f"http://127.0.0.1:{find_free_port(args.port)}"
    workdir = Path(args.workdir).expanduser().resolve()
    site = workdir / "site"
    v1, v2 = Path(args.v1).resolve(), Path(args.v2).resolve()

    rows: list[tuple[str, bool, str]] = []
    result: dict = {"url": args.url, "site": str(site), "db": args.db}

    print(f"→ سایت تست: {site}\n→ دیتابیس: {args.db}\n→ URL: {args.url}\n")

    # ۱) دیتابیس + هستهٔ وردپرس
    workdir.mkdir(parents=True, exist_ok=True)
    db_script = workdir / "create-db.php"
    db_script.write_text(PHP_CREATE_DB, encoding="utf-8")
    subprocess.run(
        [args.php, str(db_script), args.db_host, args.db_user, args.db_pass, args.db],
        check=True,
    )

    prepare_site(args, workdir, site)
    runner = Runner(args.php, args.wp_cli, site)
    install_wordpress(args, runner)
    check(rows, "نصب وردپرس روی دیتابیس تازه", True, args.db)

    # ۲) نسخهٔ v1 + دادهٔ نمونه
    install_plugins(v1, site, runner)
    seed = json_from(runner.php_eval(PHP_SEED))
    result["v1"] = seed
    check(rows, "فعال‌سازی ۱۱ افزونهٔ v1",
          (seed.get("versions", {}).get("case") or "") != "", str(seed.get("versions")))
    before = json_from(runner.php_eval(PHP_INSPECT))
    result["before"] = before
    check(rows, "v1: ستون client_id وجود ندارد",
          not any(before["has_client_id"].values()), str(before["has_client_id"]))
    check(rows, "v1: جدول هشدار/وظیفه/رویداد وجود ندارد",
          not any(before["tables"][t] for t in ("pas_events", "pas_cf_alerts", "pas_cf_tasks")), "")
    check(rows, "v1: ثبت آزمون موفق بود",
          seed["submit"]["status"] == 200 and seed["submit"]["success"], str(seed["submit"]))

    # ۳) سنجش HTTP نسخهٔ v1
    creds = {"user": args.admin_user, "pass": args.admin_pass}
    server = start_server(args.php, site, args.url)
    try:
        v1_pages = [
            ("dashboard", "/wp-admin/admin.php?page=pas-case-dashboard", "داشبورد درمانگر"),
            ("case", f"/wp-admin/admin.php?page=pas-case-file-new&case_id={seed['case_id']}", "یادداشت آزمون ارتقا"),
            ("exam", f"/?psy_exam=1&token={seed['unused_token']}", "psy-question-card"),
        ]
        http_before = run_http_phase(args.url, creds, v1_pages)
        result["http_before"] = http_before
        check(rows, "ورود مدیر (نسخهٔ v1)",
              http_before["_login"]["status"] == 302 and not http_before["_login"]["is_error"],
              f"status={http_before['_login']['status']}")
        for label, data in http_before.items():
            if label.startswith("_"):
                continue
            check(rows, f"v1 pages: {label}",
                  data["status"] == 200 and data["has_marker"] and not data["fatal"],
                  f"{data['status']} / {data['bytes']}B")

        # ۴) ارتقا: جای‌گذاری بسته‌های v2
        remove_plugins(site, runner)
        install_plugins(v2, site, runner)
        after = json_from(runner.php_eval(PHP_INSPECT))
        result["after"] = after

        v2_manifest = json.loads((v2 / "manifests" / "release-manifest.json").read_text(encoding="utf-8"))
        expected = {p["slug"]: p.get("version_source") for p in v2_manifest["plugins"]}
        check(rows, "نسخهٔ core روی v2",
              after["versions"]["core"] == expected.get("pivand-amn-core"), str(after["versions"]))
        check(rows, "نسخهٔ case-file روی v2",
              after["versions"]["case"] == expected.get("pivand-amn-case-file"), str(after["versions"]))
        check(rows, "نسخهٔ quiz روی v2",
              after["versions"]["quiz"] == expected.get("pivand-amn-quiz-enterprise"), str(after["versions"]))

        check(rows, "ستون client_id در سه جدول اضافه شد",
              all(after["has_client_id"].values()), str(after["has_client_id"]))
        check(rows, "جدول‌های تازهٔ v2 ساخته شدند",
              all(after["tables"][t] for t in
                  ("pas_events", "pas_cf_alerts", "pas_cf_tasks", "pas_cf_alert_history")),
              str({t: after["tables"][t] for t in after["tables"] if t.startswith("pas_")}))

        for key in ("cases", "notes", "invites", "attempts"):
            check(rows, f"حفظ تعداد {key}",
                  before["counts"][key] == after["counts"][key],
                  f"{before['counts'][key]} → {after['counts'][key]}")

        leftover = after["old_rows_without_client_id"]
        check(rows, "ردیف‌های قدیمی بدون شناسه ماندند (بدون backfill اجباری)",
              leftover["pas_cf_cases"] == before["counts"]["cases"]
              and leftover["psy_invitations"] == before["counts"]["invites"]
              and leftover["psy_attempts"] == before["counts"]["attempts"], str(leftover))

        note = after.get("note") or {}
        check(rows, "یادداشت در دیتابیس رمزنگاری‌شده ماند",
              note.get("plaintext_in_db") is False, str(note))
        check(rows, "یادداشت v1 بعد از ارتقا رمزگشایی می‌شود",
              note.get("decrypts_to_expected") is True, str(note))

        identity = after.get("identity") or {}
        check(rows, "رجیستری هویت روی دادهٔ v1 ساخته شد",
              (identity.get("clients") or 0) >= 3 and bool(identity.get("for_old_phone")),
              str(identity))

        flow = after.get("flow") or {}
        check(rows, "جریان جدید: ثبت آزمون 200",
              flow.get("submit_status") == 200 and flow.get("submit_success") is True, str(flow.get("submit_status")))
        check(rows, "جریان جدید: شناسهٔ دعوت‌نامه و attempt یکی است",
              bool(flow.get("invitation_client_id"))
              and flow.get("invitation_client_id") == flow.get("attempt_client_id"),
              str(flow.get("invitation_client_id")))
        event = flow.get("event") or {}
        check(rows, "رویداد assessment.completed ثبت و منتشر شد",
              event.get("name") == "assessment.completed" and event.get("status") == "dispatched",
              str(event.get("payload")))
        check(rows, "payload رویداد پاسخ/نمره ندارد",
              "answer" not in (event.get("payload") or "").lower()
              and "result" not in (event.get("payload") or "").lower(),
              str(event.get("payload")))
        alert = flow.get("alert") or {}
        check(rows, "هشدار خودآسیبی با اولویت critical ساخته شد",
              alert.get("type") == "self_harm_risk" and alert.get("priority") == "critical",
              str(alert))
        check(rows, "هشدار به پروندهٔ قدیمی وصل شد",
              int(alert.get("case_id") or 0) == int(seed["case_id"]), str(alert.get("case_id")))
        check(rows, "وظیفهٔ بررسی نتیجه و بررسی هشدار ساخته شد",
              "assessment_review" in (flow.get("tasks") or [])
              and "alert_review" in (flow.get("tasks") or []), str(flow.get("tasks")))

        # ۵) سنجش HTTP نسخهٔ v2 (سرور همان است، ولی چون فایل‌ها عوض شده‌اند
        # دیگر opcache فعال نیست و کد تازه سرو می‌شود)
        v2_pages = [
            ("dashboard", "/wp-admin/admin.php?page=pas-case-dashboard", "هشدارهای نیازمند بررسی"),
            ("case", f"/wp-admin/admin.php?page=pas-case-file-new&case_id={seed['case_id']}", "pas-cf-alerts"),
            ("alerts", "/wp-admin/admin.php?page=pas-case-alerts&view=open", "پرچم خودآسیبی"),
            ("list", "/wp-admin/admin.php?page=pas-case-files", "پرونده"),
            ("exam-legacy-token", f"/?psy_exam=1&token={seed['unused_token']}", "psy-question-card"),
        ]
        http_after = run_http_phase(args.url, creds, v2_pages)
        result["http_after"] = http_after
        check(rows, "ورود مدیر (بعد از ارتقا)",
              http_after["_login"]["status"] == 302 and not http_after["_login"]["is_error"],
              f"status={http_after['_login']['status']}")
        for label, data in http_after.items():
            if label.startswith("_"):
                continue
            check(rows, f"v2 pages: {label}",
                  data["status"] == 200 and data["has_marker"] and not data["fatal"],
                  f"{data['status']} / {data['bytes']}B")

        # ۶) خطاهای PHP: فقط Noticeهای هستهٔ وردپرس مجازند
        log_path = site / "wp-content" / "debug.log"
        log_lines = log_path.read_text(encoding="utf-8", errors="replace").splitlines() if log_path.exists() else []
        ours = [ln for ln in log_lines
                if ("Fatal error" in ln)
                or re.search(r"(pivand|payvand|pas_cf|pas_events|psy_)", ln, re.I)]
        check(rows, "debug.log بدون خط مربوط به افزونه‌ها", not ours,
              f"{len(log_lines)} خط، {len(ours)} مربوط به افزونه")
    finally:
        stop_server(server)

    # ۷) جدول نتیجه
    passed = sum(1 for _, ok, _ in rows if ok)
    width = max(len(name) for name, _, _ in rows) + 2
    print(f"\n{'بررسی':<{width}}نتیجه   جزئیات")
    print("-" * (width + 40))
    for name, ok, detail in rows:
        mark = "✅" if ok else "❌"
        print(f"{name:<{width}}{mark}      {detail}")
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
