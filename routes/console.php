<?php
use Illuminate\Support\Facades\{Artisan, Schedule, DB};
use App\Modules\IdentityAccess\Models\User;
use Illuminate\Encryption\Encrypter;
Artisan::command('nadi:create-admin', function () {
    $email = strtolower($this->ask('Email Super Admin'));
    $name = $this->ask('Nama lengkap');
    $nip = $this->ask('NIP (18 digit)');
    $password = $this->secret('Kata sandi (minimal 12 karakter)');
    if (
        !filter_var($email, FILTER_VALIDATE_EMAIL) ||
        mb_strlen($password) < 12 ||
        !preg_match('/^\d{18}$/', $nip) ||
        User::where('login_name', $nip)->exists()
    ) {
        $this->error('Email/NIP tidak valid, NIP sudah terdaftar, atau kata sandi terlalu pendek.');
        return 1;
    }
    $secret = $this->secret('Secret TOTP base32 dari proses enrollment yang disetujui');
    if (!preg_match('/^[A-Z2-7]{16,64}$/', $secret)) {
        $this->error('Secret TOTP tidak valid.');
        return 1;
    }
    $key = base64_decode((string) config('nadi.totp_key'), true);
    if (!$key || strlen($key) !== 32) {
        $this->error('NADI_TOTP_KEY harus berupa 32 byte acak dalam base64.');
        return 1;
    }
    $user = new User();
    $user->name = $name;
    $user->login_name = $nip;
    $user->email = $email;
    $user->password = $password;
    $user->role = 'SUPER_ADMIN';
    $user->is_active = true;
    $user->security_version = 1;
    $user->totp_secret = (new Encrypter($key, 'AES-256-CBC'))->encryptString($secret);
    $user->save();
    $this->info('Akun dibuat. Login menggunakan NIP dan TOTP wajib.');
});
Artisan::command('nadi:preflight', function () {
    $checks = [
        'Environment produksi' => app()->environment('production'),
        'PostgreSQL' => DB::connection()->getDriverName() === 'pgsql',
        'APP_DEBUG nonaktif' => !config('app.debug'),
        'Cookie secure' => config('session.secure'),
        'Kunci TOTP tersedia' =>
            strlen(base64_decode((string) config('nadi.totp_key'), true) ?: '') === 32,
        'Super Admin aktif dengan NIP' => User::where('role', 'SUPER_ADMIN')
            ->where('is_active', true)
            ->whereNotNull('login_name')
            ->exists(),
        'URL aplikasi dan aktivasi HTTPS' =>
            str_starts_with(config('app.url'), 'https://') &&
            str_starts_with(config('nadi.frontend_url'), 'https://'),
        'Domain publik telah ditentukan' =>
            ($host = parse_url(config('app.url'), PHP_URL_HOST)) &&
            str_contains($host, '.') &&
            !str_ends_with($host, '.localhost') &&
            !filter_var($host, FILTER_VALIDATE_IP) &&
            parse_url(config('nadi.frontend_url'), PHP_URL_HOST) === $host,
        'Pemindai unggahan diaktifkan' => (bool) config('uploads.scan_enabled'),
        'Email pemulihan diaktifkan' =>
            (bool) config('nadi.recovery_mail_enabled') &&
            config('mail.default') === 'smtp' &&
            (bool) config('mail.from.address'),
        'Gate produksi disetujui' => config('nadi.production_ready'),
    ];
    $role = DB::selectOne(
        'SELECT rolsuper, rolcreatedb, rolcreaterole, rolbypassrls FROM pg_roles WHERE rolname = current_user',
    );
    $checks['Role database terbatas'] =
        $role &&
        !$role->rolsuper &&
        !$role->rolcreatedb &&
        !$role->rolcreaterole &&
        !$role->rolbypassrls &&
        !DB::selectOne("SELECT has_schema_privilege(current_user, 'public', 'CREATE') AS allowed")
            ->allowed;
    try {
        $sample = tmpfile();
        fwrite($sample, 'NADI ASN scanner health check');
        app(\App\Modules\Imports\Services\UploadScanner::class)->assertClean(
            stream_get_meta_data($sample)['uri'],
        );
        $checks['Pemindai dapat memeriksa berkas'] = true;
    } catch (\Throwable $e) {
        $checks['Pemindai dapat memeriksa berkas'] = false;
    } finally {
        if (isset($sample) && is_resource($sample)) {
            fclose($sample);
        }
    }
    foreach ($checks as $label => $ok) {
        $this->line(($ok ? '[OK] ' : '[BELUM] ') . $label);
    }
    return in_array(false, $checks, true) ? 1 : 0;
});
Schedule::call(function () {
    DB::table('cache')->updateOrInsert(
        ['key' => 'nadi_scheduler_heartbeat'],
        [
            'value' => serialize(now()->toIso8601String()),
            'expiration' => now()->addMinutes(5)->timestamp,
        ],
    );
})
    ->name('nadi-scheduler-heartbeat')
    ->everyMinute()
    ->withoutOverlapping();

require __DIR__ . '/personnel_setup.php';
