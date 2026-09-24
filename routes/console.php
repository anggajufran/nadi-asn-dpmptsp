<?php
use Illuminate\Support\Facades\{Artisan, Schedule, DB};
use App\Modules\IdentityAccess\Models\User;
use Illuminate\Encryption\Encrypter;
Artisan::command('nadi:create-admin', function () {
    $email = strtolower($this->ask('Email Super Admin'));
    $name = $this->ask('Nama lengkap');
    $password = $this->secret('Kata sandi (minimal 12 karakter)');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($password) < 12) {
        $this->error('Email tidak valid atau kata sandi terlalu pendek.');
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
    $user->email = $email;
    $user->password = $password;
    $user->role = 'SUPER_ADMIN';
    $user->is_active = true;
    $user->security_version = 1;
    $user->totp_secret = (new Encrypter($key, 'AES-256-CBC'))->encryptString($secret);
    $user->save();
    $this->info('Akun dibuat. TOTP tetap wajib. Buat pemeriksa kedua dengan identitas berbeda.');
});
Artisan::command('nadi:preflight', function () {
    $checks = [
        'PostgreSQL' => DB::connection()->getDriverName() === 'pgsql',
        'APP_DEBUG nonaktif' => !config('app.debug'),
        'Cookie secure' => config('session.secure'),
        'Kunci TOTP tersedia' =>
            strlen(base64_decode((string) config('nadi.totp_key'), true) ?: '') === 32,
        'Dua Super Admin' =>
            User::where('role', 'SUPER_ADMIN')->where('is_active', true)->count() >= 2,
        'Gate produksi disetujui' => config('nadi.production_ready'),
    ];
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
