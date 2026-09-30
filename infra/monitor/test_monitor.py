import datetime as dt
import pathlib
import tempfile
import unittest
from check import transition, backup_fresh


class MonitorTest(unittest.TestCase):
    def test_transient_failure_does_not_alert(self):
        state, event = transition({}, ['https_health'])
        self.assertFalse(state['incident'])
        self.assertIsNone(event)
        state, event = transition(state, [])
        self.assertTrue(state['consecutive_failures'] == 0)
        self.assertIsNone(event)

    def test_incident_alerts_once_and_recovery_once(self):
        state, _ = transition({}, ['database'])
        state, event = transition(state, ['database'])
        self.assertEqual(event, 'problem')
        state, event = transition(state, ['database'])
        self.assertIsNone(event)
        state, event = transition(state, [])
        self.assertEqual(event, 'recovery')
        state, event = transition(state, [])
        self.assertIsNone(event)

    def test_backup_age_uses_correct_calendar_and_missing_marker_fails(self):
        with tempfile.TemporaryDirectory() as directory:
            path = pathlib.Path(directory) / 'marker'
            self.assertFalse(backup_fresh(path, dt.datetime.now(dt.timezone.utc), days=14))
            path.write_text('20260930T000000Z')
            self.assertTrue(backup_fresh(path, dt.datetime(2026, 10, 14, tzinfo=dt.timezone.utc), days=14))
            self.assertFalse(backup_fresh(path, dt.datetime(2026, 10, 16, tzinfo=dt.timezone.utc), days=14))
            self.assertTrue(backup_fresh(path, dt.datetime(2026, 12, 30, tzinfo=dt.timezone.utc), months=3))
            self.assertFalse(backup_fresh(path, dt.datetime(2027, 1, 2, tzinfo=dt.timezone.utc), months=3))


if __name__ == '__main__':
    unittest.main()
