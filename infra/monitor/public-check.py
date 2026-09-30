#!/usr/bin/env python3
"""Anonymous production checks. Never prints cookies, employee data or secrets."""
import argparse
import http.cookiejar
import json
import pathlib
import re
import subprocess
import tempfile
from urllib.parse import unquote

parser = argparse.ArgumentParser()
parser.add_argument('--base', default='https://nadiasndpmptspsulteng.online')
parser.add_argument('--rate-limit', action='store_true')
args = parser.parse_args()
checks = {}
with tempfile.TemporaryDirectory(prefix='nadi-public-check-') as directory:
    root = pathlib.Path(directory)
    cookies = root / 'cookies'
    def request(path, payload=None, csrf=False, extra=None):
        command = ['curl', '--silent', '--show-error', '--max-time', '20', '-D', str(root / 'headers'), '-o', str(root / 'body'), '-w', '%{http_code}', '-b', str(cookies), '-c', str(cookies), '-H', 'Accept: application/json']
        config = ''
        if csrf:
            jar = http.cookiejar.MozillaCookieJar(str(cookies))
            jar.load(ignore_discard=True, ignore_expires=True)
            token = next((unquote(c.value) for c in jar if c.name == 'XSRF-TOKEN'), '')
            config = 'header = ' + json.dumps('X-XSRF-TOKEN: ' + token) + '\n'
            command += ['--config', '-']
        for header in extra or []:
            command += ['-H', header]
        if payload is not None:
            (root / 'payload').write_bytes(payload)
            command += ['-H', 'Content-Type: application/json', '--data-binary', '@' + str(root / 'payload')]
        command += [args.base + path]
        r = subprocess.run(command, input=config, capture_output=True, text=True)
        if r.returncode:
            raise RuntimeError('HTTPS request failed; path=' + path)
        return int(r.stdout), (root / 'headers').read_text().lower(), (root / 'body').read_bytes()

    code, headers, body = request('/')
    checks['portal'] = code == 200 and b'<!doctype html' in body.lower()
    asset = re.search(rb'src="(/assets/[^\"]+\.js)"', body)
    if asset:
        code, headers, _ = request(asset[1].decode())
        checks['assets_and_csp'] = code == 200 and "script-src 'self'" in headers and 'x-content-type-options: nosniff' in headers
    else:
        checks['assets_and_csp'] = False
    for path in ['/.env', '/.deploy/app.env', '/.git/config', '/composer.json', '/storage/app/private/test.xlsx', '/vendor/autoload.php']:
        code, _, body = request(path)
        checks['hidden_' + path] = code == 404 and b'APP_KEY=' not in body
    for path in ['/api/employees', '/api/duk', '/api/accounts', '/api/exports', '/api/audit', '/api/radar/kp', '/api/radar/kgb']:
        code, headers, _ = request(path)
        checks['auth_' + path] = code == 401 and 'no-store' in headers
    code, _, body = request('/api/public/statistics')
    stats = json.loads(body)
    checks['public_real_aggregates'] = code == 200 and stats.get('demo') is False and not re.search(rb'\d{18}', body)
    checks['public_no_identity_keys'] = not any(key in body.lower() for key in [b'"nip"', b'"birthdate"', b'"birth_date"', b'"name"', b'"password"'])
    for kind in ['PNS', 'PPPK%20Penuh%20Waktu', 'PPPK%20Paruh%20Waktu']:
        code, _, body = request('/api/public/statistics?jenis=' + kind)
        checks['filter_' + kind] = code == 200 and json.loads(body).get('privacyApplied') is False
    code, _, _ = request('/api/public/statistics?jenis=%3Cscript%3E')
    checks['input_validation'] = code == 422
    code, headers, _ = request('/api/csrf')
    checks['csrf_and_secure_cookie'] = code == 200 and any('nadi_session=' in line and 'secure' in line and 'httponly' in line and 'samesite=strict' in line for line in headers.splitlines())
    payload = json.dumps({'nip': '000000000000000000', 'password': 'invalid-launch-test'}).encode()
    code, _, _ = request('/api/auth/login', payload)
    checks['csrf_rejection'] = code == 419
    code, _, _ = request('/api/activation')
    checks['activation_requires_token'] = code == 403
    code, _, body = request('/api/does-not-exist')
    checks['errors_without_trace'] = code == 404 and b'"trace"' not in body and b'/var/www' not in body
    code, headers, _ = request('/api/imports', b'A' * (7 * 1024 * 1024))
    checks['upload_size_limit'] = code == 413 and 'x-powered-by' not in headers
    if args.rate_limit:
        statuses = [request('/api/auth/login', payload, csrf=True, extra=['X-Forwarded-For: 203.0.113.' + str(i + 1)])[0] for i in range(6)]
        checks['rate_limit_and_no_forwarded_ip_spoof'] = 422 in statuses and statuses[-1] == 429
print(json.dumps(checks, indent=2))
raise SystemExit(0 if all(checks.values()) else 1)
