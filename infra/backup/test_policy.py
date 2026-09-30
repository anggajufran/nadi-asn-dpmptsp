import datetime as dt
import pathlib
import os
import subprocess
import tempfile
import tarfile
import unittest

from policy import backup_due, backup_due_months, older_snapshot


class BackupPolicyTest(unittest.TestCase):
    def test_three_calendar_months_not_ninety_days(self):
        last = '20260930T024747Z'
        self.assertFalse(backup_due_months(last, dt.datetime(2026, 12, 28, 19, tzinfo=dt.timezone.utc)))
        self.assertTrue(backup_due_months(last, dt.datetime(2026, 12, 29, 19, tzinfo=dt.timezone.utc)))

    def test_month_end_clamping_and_year_rollover(self):
        self.assertTrue(backup_due_months('20260831T000000Z', dt.datetime(2026, 11, 30, tzinfo=dt.timezone.utc)))
        self.assertFalse(backup_due_months('20261130T000000Z', dt.datetime(2027, 2, 26, 19, tzinfo=dt.timezone.utc)))
        self.assertTrue(backup_due_months('20261130T000000Z', dt.datetime(2027, 2, 27, 19, tzinfo=dt.timezone.utc)))

    def test_project_cleanup_cannot_match_personnel_backups(self):
        latest = '20260930T024747Z'
        self.assertTrue(older_snapshot('nadi-project-20260929T000000Z/', latest, 'nadi-project-'))
        self.assertFalse(older_snapshot('nadi-20260929T000000Z/', latest, 'nadi-project-'))
        self.assertFalse(older_snapshot('nadi-project-20260929T000000Z/', latest))

    def test_exact_fourteen_calendar_days_across_month_boundary(self):
        last = "20260930T024500Z"
        self.assertFalse(backup_due(last, dt.datetime(2026, 10, 12, 18, tzinfo=dt.timezone.utc)))
        self.assertTrue(backup_due(last, dt.datetime(2026, 10, 13, 18, tzinfo=dt.timezone.utc)))

    def test_first_run_and_bad_clock(self):
        now = dt.datetime(2026, 9, 30, tzinfo=dt.timezone.utc)
        self.assertTrue(backup_due("", now))
        self.assertFalse(backup_due("20261001T000000Z", now))

    def test_only_strictly_older_owned_names_are_removed(self):
        latest = "20260930T024500Z"
        self.assertTrue(older_snapshot("nadi-20260929T024500Z/", latest))
        for name in ["nadi-" + latest, "nadi-20261001T000000Z", "other-folder",
                     "../nadi-20260929T024500Z", "nadi-20260929T024500Z/child",
                     "nadi-20260231T000000Z", "nadi-20260929T024500Z//"]:
            self.assertFalse(older_snapshot(name, latest), name)

    def test_local_cleanup_preserves_latest_foreign_folders_and_symlinks(self):
        with tempfile.TemporaryDirectory() as folder:
            root = pathlib.Path(folder)
            backups = root / "backups"
            backups.mkdir()
            outside = root / "outside"
            outside.mkdir()
            (outside / "keep").write_text("keep")
            for name in ["nadi-20260929T024500Z", "nadi-20260930T024500Z", "foreign"]:
                (backups / name).mkdir()
            (backups / "nadi-20260928T024500Z").symlink_to(outside, target_is_directory=True)
            subprocess.run(["python3", str(pathlib.Path(__file__).with_name("policy.py")),
                            "prune-local", str(backups), "--latest", "20260930T024500Z"],
                           check=True, capture_output=True)
            self.assertFalse((backups / "nadi-20260929T024500Z").exists())
            self.assertTrue((backups / "nadi-20260930T024500Z").exists())
            self.assertTrue((backups / "foreign").exists())
            self.assertTrue((outside / "keep").exists())
            self.assertTrue((backups / "nadi-20260928T024500Z").is_symlink())

    def test_corrupt_marker_is_error_and_does_not_silently_skip_backup(self):
        with tempfile.TemporaryDirectory() as folder:
            marker = pathlib.Path(folder) / "last-success"
            marker.write_text("invalid")
            result = subprocess.run(["python3", str(pathlib.Path(__file__).with_name("policy.py")),
                                     "due", str(marker)], capture_output=True)
            self.assertEqual(result.returncode, 2)

    def test_failed_cloud_verification_preserves_previous_backup(self):
        with tempfile.TemporaryDirectory() as folder:
            project = pathlib.Path(folder)
            state = project / '.deploy/backup'
            tools = state / 'tools/usr/bin'
            tools.mkdir(parents=True)
            backups = state / 'snapshots'
            previous = backups / 'nadi-20200101T000000Z'
            previous.mkdir(parents=True)
            (previous / 'keep.age').write_text('previous encrypted backup')
            fakebin = project / 'fakebin'
            fakebin.mkdir()
            scripts = {
                fakebin / 'flock': '#!/bin/sh\nexit 0\n',
                fakebin / 'sha256sum': '#!/bin/sh\nexec shasum -a 256 "$@"\n',
                fakebin / 'docker': '#!/bin/sh\nif [ "$1" = inspect ]; then echo true; else echo test-dump; fi\n',
                tools / 'age': '#!/usr/bin/env python3\nimport sys,pathlib\npathlib.Path(sys.argv[sys.argv.index("-o")+1]).write_bytes(sys.stdin.buffer.read())\n',
                tools / 'rclone': '#!/bin/sh\ncase " $* " in *" check "*) exit 1;; *" purge "*) touch "'+str(state / 'purge-called')+'";; esac\n',
            }
            for path, content in scripts.items():
                path.write_text(content)
                path.chmod(0o700)
            for directory in ['.deploy/tls', 'infra', 'app', 'bootstrap', 'config',
                              'database', 'resources', 'routes', 'public']:
                (project / directory).mkdir(parents=True, exist_ok=True)
            for name in ['.deploy/app.env', '.deploy/db-owner-password', 'composer.json',
                         'composer.lock', 'package.json', 'package-lock.json', 'artisan']:
                (project / name).write_text('synthetic-test-only')
            (state / 'backup.env').write_text(
                f'AGE_RECIPIENT_FILE={state}/recipient.txt\nRCLONE_CONFIG={state}/rclone.conf\n'
                f'RCLONE_REMOTE=nadibackup:NADI-ASN-Backup\nBACKUP_DIR={backups}\nBACKUP_REPLACE_OLD=true\n')
            env = dict(os.environ, NADI_BACKUP_PROJECT=str(project), PATH=str(fakebin)+':'+os.environ['PATH'])
            result = subprocess.run(['bash', str(pathlib.Path(__file__).with_name('vps-backup.sh'))],
                                    env=env, capture_output=True)
            self.assertNotEqual(result.returncode, 0)
            self.assertTrue((previous / 'keep.age').exists())
            self.assertFalse((state / 'purge-called').exists())
            self.assertFalse((state / 'last-success').exists())
            self.assertIn('FAILED stage=verify', (state / 'status.log').read_text())

    def test_project_archive_excludes_backup_engine_and_failed_verify_keeps_old(self):
        with tempfile.TemporaryDirectory() as folder:
            project = pathlib.Path(folder) / 'nadi-asn'
            state = project / '.deploy/backup'
            tools = state / 'tools/usr/bin'
            tools.mkdir(parents=True)
            (project / 'source.txt').write_text('synthetic source')
            (state / 'rclone.conf').write_text('synthetic OAuth placeholder')
            previous = state / 'project-snapshots/nadi-project-20200101T000000Z'
            previous.mkdir(parents=True)
            (previous / 'keep.age').write_text('previous encrypted project backup')
            fakebin = pathlib.Path(folder) / 'fakebin'
            fakebin.mkdir()
            scripts = {
                fakebin / 'flock': '#!/bin/sh\nexit 0\n',
                fakebin / 'sha256sum': '#!/bin/sh\nexec shasum -a 256 "$@"\n',
                tools / 'age': '#!/usr/bin/env python3\nimport sys,pathlib\npathlib.Path(sys.argv[sys.argv.index("-o")+1]).write_bytes(sys.stdin.buffer.read())\n',
                tools / 'rclone': '#!/bin/sh\ncase " $* " in *" check "*) exit 1;; *" purge "*) touch "'+str(state / 'purge-called')+'";; esac\n',
            }
            for path, content in scripts.items():
                path.write_text(content)
                path.chmod(0o700)
            (state / 'backup.env').write_text(
                f'AGE_RECIPIENT_FILE={state}/recipient.txt\nRCLONE_CONFIG={state}/rclone.conf\nBACKUP_REPLACE_OLD=true\n')
            env = dict(os.environ, NADI_BACKUP_PROJECT=str(project), PATH=str(fakebin)+':'+os.environ['PATH'])
            result = subprocess.run(['bash', str(pathlib.Path(__file__).with_name('project-backup.sh'))],
                                    env=env, capture_output=True)
            self.assertNotEqual(result.returncode, 0)
            self.assertTrue((previous / 'keep.age').exists())
            self.assertFalse((state / 'purge-called').exists())
            self.assertFalse((state / 'project-last-success').exists())
            self.assertIn('FAILED stage=verify', (state / 'project-status.log').read_text())
            archives = list((state / 'project-snapshots').glob('*/project.tar.gz.age'))
            self.assertEqual(len(archives), 1)
            with tarfile.open(archives[0]) as archive:
                names = archive.getnames()
                self.assertIn('nadi-asn/source.txt', names)
                self.assertFalse(any(n.startswith('nadi-asn/.deploy/backup') for n in names))


if __name__ == "__main__":
    unittest.main()
