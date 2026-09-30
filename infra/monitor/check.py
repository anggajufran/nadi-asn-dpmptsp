#!/usr/bin/env python3
"""VPS health checks with incident/recovery email, without logging secrets."""
import argparse
import calendar
import datetime as dt
import email.message
import fcntl
import json
import logging
import logging.handlers
import os
import pathlib
import shlex
import shutil
import smtplib
import ssl
import subprocess
from zoneinfo import ZoneInfo

PROJECT = pathlib.Path('/home/anggauser/nadi-asn')
UTC = dt.timezone.utc
STAMP = '%Y%m%dT%H%M%SZ'


def transition(previous, failed):
    """Two bad samples trigger an incident; one good sample clears it."""
    state = dict(previous)
    state['consecutive_failures'] = previous.get('consecutive_failures', 0) + 1 if failed else 0
    was = previous.get('incident', False)
    state['incident'] = bool(failed) and (was or state['consecutive_failures'] >= 2)
    event = 'problem' if state['incident'] and not was else 'recovery' if was and not failed else None
    return state, event


def run(args, data=None, timeout=20):
    try:
        result = subprocess.run(args, input=data, capture_output=True, timeout=timeout)
        return result.returncode, result.stdout
    except (subprocess.TimeoutExpired, OSError):
        return 1, b''


def backup_fresh(path, now, months=None, days=None):
    try:
        last = dt.datetime.strptime(path.read_text().strip(), STAMP).replace(tzinfo=UTC)
        if days:
            return dt.timedelta(0) <= now - last <= dt.timedelta(days=days + 1)
        date = last.astimezone(ZoneInfo('Asia/Makassar')).date()
        index = date.year * 12 + date.month - 1 + months
        year, month = divmod(index, 12)
        month += 1
        due = dt.date(year, month, min(date.day, calendar.monthrange(year, month)[1]))
        return last <= now and now.astimezone(ZoneInfo('Asia/Makassar')).date() <= due + dt.timedelta(days=1)
    except (OSError, ValueError):
        return False


def checks():
    now = dt.datetime.now(UTC)
    status = {}
    for service in ['web', 'app', 'worker', 'scheduler', 'db', 'antivirus']:
        code, data = run(['docker', 'inspect', '-f', '{{json .State}}', 'nadi-asn-' + service + '-1'])
        try:
            info = json.loads(data)
            status['container_' + service] = code == 0 and info.get('Running', False) and info.get('Health', {}).get('Status', 'healthy') == 'healthy'
        except ValueError:
            status['container_' + service] = False
    base = 'https://nadiasndpmptspsulteng.online'
    for path, name in [('/up', 'https_health'), ('/api/session', 'https_session')]:
        code, body = run(['curl', '--fail', '--silent', '--show-error', '--max-time', '15', base + path])
        status[name] = code == 0
        if name == 'https_session':
            try:
                status['real_data_mode'] = code == 0 and json.loads(body).get('demo') is False
            except ValueError:
                status['real_data_mode'] = False
    code, data = run(['docker', 'exec', '-i', 'nadi-asn-app-1', 'php'], (PROJECT / 'infra/monitor/probe.php').read_bytes())
    try:
        info = json.loads(data)
    except ValueError:
        info = {}
    status['database'] = code == 0 and info.get('database', False)
    status['scheduler_heartbeat'] = info.get('scheduler_age') is not None and 0 <= info['scheduler_age'] <= 180
    status['queue_age'] = info.get('queue_oldest_age', 10**9) <= 900
    status['no_failed_jobs'] = info.get('failed_jobs', -1) == 0
    for name in ['production_ready', 'debug_off', 'cookie_secure']:
        status[name] = info.get(name, False)
    status['disk_capacity'] = shutil.disk_usage(PROJECT).free / shutil.disk_usage(PROJECT).total >= 0.15
    code, _ = run(['docker', 'exec', 'npm', 'openssl', 'x509', '-in', '/etc/letsencrypt/nadi-asn/live/nadiasndpmptspsulteng.online/fullchain.pem', '-noout', '-checkend', str(14 * 86400)])
    status['tls_expiry'] = code == 0
    code, _ = run(['openssl', 'x509', '-in', str(PROJECT / '.deploy/tls/server.crt'), '-noout', '-checkend', str(14 * 86400)])
    status['upstream_tls_expiry'] = code == 0
    state = PROJECT / '.deploy/backup'
    status['data_backup_fresh'] = backup_fresh(state / 'last-success', now, days=14)
    status['project_backup_fresh'] = backup_fresh(state / 'project-last-success', now, months=3)
    return status


def notify(config, event, failed):
    values = {}
    for line in (PROJECT / '.deploy/app.env').read_text().splitlines():
        if '=' in line and not line.lstrip().startswith('#'):
            key, value = line.split('=', 1)
            if key.startswith('MAIL_'):
                parsed = shlex.split(value, comments=True)
                values[key] = parsed[0] if parsed else ''
    message = email.message.EmailMessage()
    message['From'] = 'NADI ASN <' + values['MAIL_FROM_ADDRESS'] + '>'
    message['To'] = config['recipient']
    titles = {'problem': 'Gangguan layanan', 'recovery': 'Layanan kembali normal', 'test': 'Uji pemantauan layanan berhasil'}
    message['Subject'] = '[NADI ASN] ' + titles[event]
    text = titles[event] + '\n\nhttps://nadiasndpmptspsulteng.online\n'
    text += 'Waktu: ' + dt.datetime.now(ZoneInfo('Asia/Makassar')).strftime('%d-%m-%Y %H:%M WITA') + '\n'
    if failed:
        text += '\nPemeriksaan yang membutuhkan perhatian:\n' + '\n'.join('- ' + name for name in failed)
    text += '\n\nPemantauan berjalan pada VPS. Jika VPS mati total, pemeriksaan ini tidak dapat mengirim email.\n'
    message.set_content(text)
    host = values['MAIL_HOST']
    port = int(values.get('MAIL_PORT', '587'))
    context = ssl.create_default_context()
    if values.get('MAIL_SCHEME') == 'smtps':
        server = smtplib.SMTP_SSL(host, port, timeout=15, context=context)
    else:
        server = smtplib.SMTP(host, port, timeout=15)
        server.ehlo()
        server.starttls(context=context)
        server.ehlo()
    with server:
        server.login(values['MAIL_USERNAME'], values['MAIL_PASSWORD'])
        server.send_message(message)


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument('--test-email', action='store_true')
    args = parser.parse_args()
    os.umask(0o077)
    folder = PROJECT / '.deploy/monitor'
    folder.mkdir(mode=0o700, exist_ok=True)
    with (folder / 'monitor.lock').open('w') as lock:
        try:
            fcntl.flock(lock, fcntl.LOCK_EX | fcntl.LOCK_NB)
        except BlockingIOError:
            return
        config = json.loads((folder / 'config.json').read_text())
        logger = logging.getLogger('nadi-monitor')
        logger.setLevel(logging.INFO)
        handler = logging.handlers.RotatingFileHandler(folder / 'monitor.log', maxBytes=1024 * 1024, backupCount=3)
        handler.setFormatter(logging.Formatter('%(asctime)s %(message)s'))
        logger.addHandler(handler)
        if args.test_email:
            notify(config, 'test', [])
            print('Monitoring test email accepted by SMTP.')
            return
        previous_path = folder / 'status.json'
        previous = json.loads(previous_path.read_text()) if previous_path.exists() else {}
        result = checks()
        failed = sorted(name for name, ok in result.items() if not ok)
        state, event = transition(previous, failed)
        state.update({'checked_at': dt.datetime.now(UTC).isoformat(), 'healthy': not failed, 'checks': result})
        if event:
            state['pending_notification'] = event
        if not state['incident'] and state.get('pending_notification') == 'problem':
            state.pop('pending_notification')
        pending = state.get('pending_notification')
        if pending and config.get('email_enabled', False):
            try:
                notify(config, pending, failed)
                state.pop('pending_notification')
                logger.info('notification=%s sent', pending)
            except Exception:
                logger.warning('notification failed; retry next check')
        logger.info('healthy=%s failed_checks=%s', not failed, ','.join(failed) or 'none')
        temporary = folder / 'status.tmp'
        temporary.write_text(json.dumps(state, indent=2) + '\n')
        temporary.replace(previous_path)
        print('Monitoring:', 'healthy' if not failed else 'needs attention', '; checks:', len(result))


if __name__ == '__main__':
    main()
