#!/usr/bin/env python3
"""
Планировщик парсеров 90plus.kz для Plesk (CLI, без HTTP).

Запуск в Plesk → Scheduled Tasks → Run a command:
  /usr/bin/python3 /var/www/vhosts/018.kz/90plus.kz/scripts/plesk_cron.py

Или с полным путём из «Запустить» в Plesk после проверки:
  python3 scripts/plesk_cron.py
  python3 scripts/plesk_cron.py --job articles
  python3 scripts/plesk_cron.py --status
"""

from __future__ import annotations

import argparse
import json
import os
import re
import subprocess
import sys
import time
from datetime import datetime
from pathlib import Path
from typing import Any

# Интервалы как в scripts/plesk-scheduler-runner.php
SCHEDULE: dict[str, dict[str, Any]] = {
    "premier-liga": {"type": "interval", "seconds": 300},
    "world-cup": {"type": "interval", "seconds": 1800},
    "standings": {"type": "interval", "seconds": 1800},
    "articles": {"type": "interval", "seconds": 600},
    "clubs-daily": {"type": "daily", "at": "04:00"},
    "transfers": {"type": "daily", "at": "06:00"},
}

# job_id → (artisan command, опции)
JOBS: dict[str, tuple[str, dict[str, Any]]] = {
    "articles": ("articles:fetch-hourly", {}),
    "standings": ("standings:fetch", {}),
    "world-cup": ("world-cup:sync", {}),
    "premier-liga": ("premier-liga:sync", {}),
    "fixtures-live": ("fixtures:sync", {"live": True}),
    "fixtures-tracked": ("fixtures:sync", {"tracked": True}),
    "transfers": ("transfers:sync", {}),
    "clubs-daily": ("clubs:sync-daily", {"batch": 15}),
}

PHP_CANDIDATES = [
    "/opt/plesk/php/8.3/bin/php",
    "/opt/plesk/php/8.2/bin/php",
    "/opt/plesk/php/8.1/bin/php",
    "php",
]


def find_project_root() -> Path:
    script_dir = Path(__file__).resolve().parent
    for base in (script_dir.parent, script_dir):
        if (base / "artisan").is_file():
            return base
    raise SystemExit("ERROR: Laravel root not found (no artisan)")


def load_env(root: Path) -> dict[str, str]:
    env: dict[str, str] = {}
    path = root / ".env"
    if not path.is_file():
        return env
    for line in path.read_text(encoding="utf-8", errors="replace").splitlines():
        line = line.strip()
        if not line or line.startswith("#") or "=" not in line:
            continue
        key, _, val = line.partition("=")
        env[key.strip()] = val.strip().strip('"').strip("'")
    return env


def find_php(root: Path, env: dict[str, str]) -> str:
    custom = env.get("PLESK_PHP", "").strip()
    if custom and Path(custom).is_file():
        return custom
    for candidate in PHP_CANDIDATES:
        if candidate == "php":
            return candidate
        if Path(candidate).is_file():
            return candidate
    return "php"


def log_line(path: Path, message: str) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)
    line = f"{datetime.now():%Y-%m-%d %H:%M:%S} {message}\n"
    with path.open("a", encoding="utf-8") as f:
        f.write(line)
    print(message)


def load_state(state_file: Path) -> dict[str, Any]:
    if not state_file.is_file():
        return {}
    try:
        return json.loads(state_file.read_text(encoding="utf-8"))
    except json.JSONDecodeError:
        return {}


def save_state(state_file: Path, state: dict[str, Any]) -> None:
    state_file.parent.mkdir(parents=True, exist_ok=True)
    state_file.write_text(
        json.dumps(state, indent=2, ensure_ascii=False) + "\n",
        encoding="utf-8",
    )


def due_jobs(state: dict[str, Any], now: int | None = None) -> dict[str, int]:
    now = now or int(time.time())
    due: dict[str, int] = {}
    today = datetime.now().strftime("%Y-%m-%d")
    now_minutes = datetime.now().hour * 60 + datetime.now().minute

    for job_id, cfg in SCHEDULE.items():
        if cfg["type"] == "interval":
            last = int(state.get(job_id, 0) or 0)
            overdue = now - last - int(cfg["seconds"])
            if overdue >= 0:
                due[job_id] = overdue
        elif cfg["type"] == "daily":
            if state.get(f"{job_id}_date") == today:
                continue
            h, m = map(int, str(cfg["at"]).split(":"))
            target = h * 60 + m
            if now_minutes >= target:
                due[job_id] = 100000 + (now_minutes - target)

    return due


def pick_job(state: dict[str, Any]) -> str | None:
    due = due_jobs(state)
    if not due:
        return None
    return max(due, key=due.get)


def artisan_argv(php: str, root: Path, job_id: str) -> list[str]:
    if job_id not in JOBS:
        raise ValueError(f"unknown job: {job_id}")
    command, opts = JOBS[job_id]
    cmd = [php, str(root / "artisan"), command]
    for key, val in opts.items():
        flag = key.replace("_", "-")
        if val is True:
            cmd.append(f"--{flag}")
        else:
            cmd.extend([f"--{flag}", str(val)])
    return cmd


def run_artisan(root: Path, php: str, job_id: str) -> int:
    log_file = root / "storage" / "logs" / f"cron-{re.sub(r'[^a-z0-9_-]+', '_', job_id, flags=re.I)}.log"
    command, _ = JOBS[job_id]
    log_line(log_file, f"--- job start: {job_id} → artisan {command} ---")

    env = os.environ.copy()
    env["PLESK_CRON"] = "1"
    env.setdefault("APP_ENV", load_env(root).get("APP_ENV", "production"))

    cmd = artisan_argv(php, root, job_id)
    log_line(log_file, f"exec: {' '.join(cmd)}")

    try:
        result = subprocess.run(
            cmd,
            cwd=str(root),
            env=env,
            capture_output=True,
            text=True,
            timeout=3600,
            check=False,
        )
    except subprocess.TimeoutExpired:
        log_line(log_file, "ERROR: timeout 3600s")
        return 1
    except OSError as e:
        log_line(log_file, f"ERROR: {e}")
        return 1

    if result.stdout:
        for line in result.stdout.strip().splitlines()[-30:]:
            log_line(log_file, f"stdout: {line}")
    if result.stderr:
        for line in result.stderr.strip().splitlines()[-30:]:
            log_line(log_file, f"stderr: {line}")

    code = result.returncode
    if code == 0:
        log_line(log_file, f"OK: artisan {command}")
    else:
        log_line(log_file, f"WARN: exit {code}")

    return 0 if code == 0 else 1


class FileLock:
    """Простой lock, чтобы два cron не шли параллельно."""

    def __init__(self, path: Path) -> None:
        self.path = path
        self.fp = None

    def __enter__(self) -> bool:
        self.path.parent.mkdir(parents=True, exist_ok=True)
        self.fp = open(self.path, "a+", encoding="utf-8")
        try:
            if sys.platform == "win32":
                import msvcrt

                try:
                    msvcrt.locking(self.fp.fileno(), msvcrt.LK_NBLCK, 1)
                    return True
                except OSError:
                    return False
            else:
                import fcntl

                try:
                    fcntl.flock(self.fp.fileno(), fcntl.LOCK_EX | fcntl.LOCK_NB)
                    return True
                except BlockingIOError:
                    return False
        except ImportError:
            return True

    def __exit__(self, *args: Any) -> None:
        if self.fp is None:
            return
        try:
            if sys.platform != "win32":
                import fcntl

                fcntl.flock(self.fp.fileno(), fcntl.LOCK_UN)
        except Exception:
            pass
        self.fp.close()


def mark_done(state: dict[str, Any], job_id: str, now: int) -> None:
    cfg = SCHEDULE[job_id]
    if cfg["type"] == "daily":
        state[f"{job_id}_date"] = datetime.now().strftime("%Y-%m-%d")
    else:
        state[job_id] = now


def cmd_status(root: Path) -> int:
    state_file = root / "storage" / "app" / "plesk-scheduler-state.json"
    state = load_state(state_file)
    due = due_jobs(state)
    print(f"root: {root}")
    print(f"state: {state_file}")
    if not due:
        print("nothing due")
    else:
        for job_id in sorted(due, key=due.get, reverse=True):
            print(f"  due: {job_id} (overdue {due[job_id]}s)")
    return 0


def main() -> int:
    parser = argparse.ArgumentParser(description="90plus.kz Plesk cron scheduler")
    parser.add_argument("--job", help="Запустить конкретный job (articles, premier-liga, …)")
    parser.add_argument("--status", action="store_true", help="Показать просроченные задачи")
    parser.add_argument("--root", help="Путь к Laravel (если autodetect не сработал)")
    args = parser.parse_args()

    root = Path(args.root).resolve() if args.root else find_project_root()
    env = load_env(root)
    php = find_php(root, env)

    if not (root / ".env").is_file():
        print("ERROR: .env missing", file=sys.stderr)
        return 1
    if not (root / "vendor").is_dir():
        print("ERROR: vendor/ missing — run composer install", file=sys.stderr)
        return 1

    if args.status:
        return cmd_status(root)

    scheduler_log = root / "storage" / "logs" / "cron-scheduler.log"
    state_file = root / "storage" / "app" / "plesk-scheduler-state.json"
    lock_file = root / "storage" / "app" / "plesk-scheduler.lock"

    with FileLock(lock_file) as acquired:
        if not acquired:
            log_line(scheduler_log, "scheduler: skip (already running)")
            print("scheduler: skip (already running)")
            return 0

        state = load_state(state_file)
        now = int(time.time())

        if args.job:
            job_id = args.job
            if job_id not in JOBS:
                print(f"ERROR: unknown job. Allowed: {', '.join(JOBS)}", file=sys.stderr)
                return 1
        else:
            job_id = pick_job(state)
            if job_id is None:
                log_line(scheduler_log, "scheduler: nothing due")
                print("scheduler: nothing due")
                return 0

        log_line(scheduler_log, f"--- tick: {job_id} (php={php}) ---")
        print(f"scheduler: starting {job_id}")

        code = run_artisan(root, php, job_id)

        if code == 0:
            mark_done(state, job_id, now)
            save_state(state_file, state)
            log_line(scheduler_log, f"OK: finished {job_id}")
        else:
            log_line(scheduler_log, f"WARN: {job_id} failed (exit {code})")

        return code


if __name__ == "__main__":
    sys.exit(main())
