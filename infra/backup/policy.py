"""Calendar scheduling and strictly scoped replacement of NADI backup folders."""
import argparse
import calendar
import datetime as dt
import pathlib
import re
import shutil
import sys
from zoneinfo import ZoneInfo

STAMP = "%Y%m%dT%H%M%SZ"


def backup_due(last: str, now: dt.datetime, days: int = 14) -> bool:
    if not last:
        return True
    previous = dt.datetime.strptime(last, STAMP).replace(tzinfo=dt.timezone.utc)
    zone = ZoneInfo("Asia/Makassar")
    return (now.astimezone(zone).date() - previous.astimezone(zone).date()).days >= days


def backup_due_months(last: str, now: dt.datetime, months: int = 3) -> bool:
    if not last:
        return True
    zone = ZoneInfo("Asia/Makassar")
    previous = dt.datetime.strptime(last, STAMP).replace(tzinfo=dt.timezone.utc).astimezone(zone).date()
    index = previous.year * 12 + previous.month - 1 + months
    year, month = divmod(index, 12)
    month += 1
    target = dt.date(year, month, min(previous.day, calendar.monthrange(year, month)[1]))
    return now.astimezone(zone).date() >= target


def older_snapshot(name: str, latest: str, prefix: str = "nadi-") -> bool:
    match = re.fullmatch(re.escape(prefix) + r"(\d{8}T\d{6}Z)/?", name)
    if not match:
        return False
    try:
        return dt.datetime.strptime(match[1], STAMP) < dt.datetime.strptime(latest, STAMP)
    except ValueError:
        return False


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument("operation", choices=["due", "due-months", "remote-plan", "prune-local"])
    parser.add_argument("path", type=pathlib.Path)
    parser.add_argument("--latest")
    parser.add_argument("--days", type=int, default=14)
    parser.add_argument("--months", type=int, default=3)
    parser.add_argument("--prefix", choices=["nadi-", "nadi-project-"], default="nadi-")
    args = parser.parse_args()
    if args.operation in ["due", "due-months"]:
        if not 1 <= args.days <= 365 or not 1 <= args.months <= 24:
            raise ValueError("Invalid interval")
        last = args.path.read_text().strip() if args.path.exists() else ""
        now = dt.datetime.now(dt.timezone.utc)
        due = backup_due(last, now, args.days) if args.operation == "due" else backup_due_months(last, now, args.months)
        sys.exit(0 if due else 1)
    dt.datetime.strptime(args.latest, STAMP)  # Fail closed on malformed latest snapshot.
    if args.operation == "remote-plan":
        for name in args.path.read_text().splitlines():
            if older_snapshot(name, args.latest, args.prefix):
                print(name.rstrip("/"))
    else:
        root = args.path.resolve(strict=True)
        for path in root.iterdir():
            if not path.is_symlink() and path.is_dir() and older_snapshot(path.name, args.latest, args.prefix):
                shutil.rmtree(path)
                print("Removed old local snapshot:", path.name)


if __name__ == "__main__":
    try:
        main()
    except (ValueError, OSError) as error:
        print("Backup policy error:", type(error).__name__, file=sys.stderr)
        sys.exit(2)
