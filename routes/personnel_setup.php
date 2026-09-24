<?php
use Illuminate\Support\Facades\{Artisan, DB};
use Illuminate\Support\Str;
use App\Modules\IdentityAccess\Models\User;
use App\Modules\Personnel\Models\{Employee, EmployeeAssignment};
use App\Shared\Audit;
Artisan::command('nadi:import-directory {file}', function () {
    abort_unless(
        app()->environment('local'),
        403,
        'Perintah ini khusus migrasi lokal yang diautorisasi.',
    );
    $payload = json_decode(
        file_get_contents($this->argument('file')),
        true,
        512,
        JSON_THROW_ON_ERROR,
    );
    $rows = $payload['records'];
    if (count($rows) !== 168 || count(array_unique(array_column($rows, 'nip'))) !== 168) {
        throw new RuntimeException('Jumlah/keunikan sumber tidak sesuai.');
    }
    $restu = collect($rows)->first(
        fn($e) => str_starts_with($e['name'], 'RESTU ANGGARA PRATAMA JUFRAN'),
    );
    DB::transaction(function () use ($rows, $restu, $payload) {
        $u = User::where('login_name', $restu['nip'])->first();
        if (!$u) {
            $u = new User();
            $u->forceFill([
                'name' => $restu['name'],
                'email' => null,
                'login_name' => $restu['nip'],
                'password' => Str::random(64),
                'role' => 'SUPER_ADMIN',
                'is_active' => false,
                'security_version' => 1,
            ]);
            $u->save();
        }
        foreach ($rows as $row) {
            $ref = DB::table('reference_values')
                ->where('kind', 'unit')
                ->where('label', $row['unit'])
                ->first();
            if (!$ref) {
                $id = (string) Str::uuid();
                DB::table('reference_values')->insert([
                    'id' => $id,
                    'kind' => 'unit',
                    'code' => 'UNIT-' . $id,
                    'label' => $row['unit'],
                    'active' => true,
                    'verified' => true,
                ]);
            } else {
                $id = $ref->id;
            }
            $existing = Employee::where('nip', $row['nip'])->first();
            if ($existing) {
                if (($existing->source_payload['sha256'] ?? null) === $payload['sha256']) {
                    continue;
                }
                throw new RuntimeException(
                    'NIP sudah ada dengan sumber berbeda; rekonsiliasi diperlukan.',
                );
            }
            $e = Employee::create([
                'name' => $row['name'],
                'nip' => $row['nip'],
                'gender' => $row['gender'],
                'education' => $row['education'],
                'birth_place' => $row['birthPlace'],
                'birth_date' => $row['birthDate'] ?: null,
                'source_order' => $row['sourceOrder'],
                'institution' => $row['institution'],
                'graduation_date' => $row['graduationDate'] ?: null,
                'age_at_reference' => $row['ageAtReference'],
                'source_issues' => $row['sourceIssues'],
                'source_payload' => [
                    'sha256' => $payload['sha256'],
                    'page' => $row['sourcePage'],
                    'cells' => $row['sourceCells'],
                ],
                'revision' => 1,
            ]);
            EmployeeAssignment::create([
                'employee_id' => $e->id,
                'type' => $row['type'],
                'unit' => $row['unit'],
                'unit_id' => $id,
                'position' => $row['position'],
                'position_type' => $row['positionType'],
                'grade' => $row['grade'],
                'rank_name' => $row['rankName'],
                'rank_tmt' => $row['rankTmt'] ?: null,
                'effective_date' => today()->toDateString(),
                'position_tmt' => $row['effectiveDate'] ?: null,
                'service_years' => $row['serviceYears'],
                'service_months' => $row['serviceMonths'],
                'service_reference' => '2026-09',
                'status' => 'Aktif',
                'record_status' => 'OFFICIAL',
                'approved_by' => $u->id,
            ]);
        }
        Audit::append(
            $u,
            'Memuat direktori PDF atas instruksi pemilik proyek',
            $payload['source'],
            [
                'sha256' => $payload['sha256'],
                'records' => count($rows),
                'authorization' => 'Instruksi pengguna 14 September 2026; migrasi data awal',
            ],
        );
    });
    $this->info('168 baris sumber dimuat/dikenali. Tidak ada data yang ditebak.');
});
Artisan::command('nadi:activation-link {login}', function () {
    abort_unless(app()->environment('local'), 403);
    $u = User::where('login_name', $this->argument('login'))->firstOrFail();
    abort_if($u->is_active, 409, 'Akun sudah aktif.');
    $token = bin2hex(random_bytes(32));
    $secret = (new \PragmaRX\Google2FA\Google2FA())->generateSecretKey();
    $u->totp_secret = (new \Illuminate\Encryption\Encrypter(
        base64_decode(config('nadi.totp_key')),
        'AES-256-CBC',
    ))->encryptString($secret);
    $u->save();
    DB::table('account_activations')->updateOrInsert(
        ['user_id' => $u->id],
        ['token_hash' => hash('sha256', $token), 'expires_at' => now()->addDay()],
    );
    $url = 'http://127.0.0.1:5173/aktivasi#' . $token;
    file_put_contents(
        base_path('.runtime/aktivasi-super-admin.html'),
        '<!doctype html><html lang="id"><meta charset="utf-8"><title>Aktivasi NADI ASN</title><body style="font-family:system-ui;padding:40px"><h1>Aktivasi Super Admin NADI ASN</h1><p>Tautan pribadi untuk ' .
            htmlspecialchars($u->name) .
            '. Berlaku 24 jam dan hanya satu kali. Jangan bagikan berkas ini.</p><p><a href="' .
            htmlspecialchars($url) .
            '">Buka aktivasi akun</a></p><p>Tentukan kata sandi dan hubungkan aplikasi autentikator Anda pada halaman aktivasi.</p></body></html>',
    );
    chmod(base_path('.runtime/aktivasi-super-admin.html'), 0600);
    $this->info('Tautan pribadi disimpan di .runtime/aktivasi-super-admin.html (24 jam).');
});
