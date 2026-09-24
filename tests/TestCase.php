<?php
namespace Tests;
use Illuminate\Foundation\Testing\TestCase as LaravelTestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use App\Modules\IdentityAccess\Models\User;
use App\Modules\Personnel\Models\{Employee, EmployeeAssignment};
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Encryption\Encrypter;
abstract class TestCase extends LaravelTestCase
{
    use DatabaseTransactions;
    protected function setUp(): void
    {
        parent::setUp();
        if (
            config('database.connections.pgsql.database') !== 'nadi_testing' ||
            !app()->environment('testing')
        ) {
            throw new \RuntimeException('Tests must use isolated nadi_testing database.');
        }
        config(['filesystems.disks.local.root' => storage_path('app/private/testing')]);
    }
    protected function user(string $role = 'ADMIN'): User
    {
        $u = new User();
        $u->forceFill([
            'name' => $role . ' Uji',
            'email' => Str::uuid() . '@example.test',
            'password' => 'KataSandiUji-12345',
            'role' => $role,
            'is_active' => true,
            'security_version' => 1,
        ]);
        $key = base64_decode(config('nadi.totp_key'));
        $u->totp_secret = (new Encrypter($key, 'AES-256-CBC'))->encryptString('JBSWY3DPEHPK3PXP');
        $u->save();
        return $u;
    }
    protected function fullSession(User $u): static
    {
        return $this->actingAs($u)->withSession([
            'totp_completed' => true,
            'security_version' => $u->security_version,
            'absolute_expires_at' => now()->addHours(8)->timestamp,
        ]);
    }
    protected function references(): void
    {
        foreach (['unit' => 'Sekretariat', 'grade' => 'III/a'] as $kind => $label) {
            DB::table('reference_values')->insert([
                'id' => (string) Str::uuid(),
                'kind' => $kind,
                'code' => $kind . '-test',
                'label' => $label,
                'verified' => true,
                'active' => true,
            ]);
        }
    }
    protected function employee(User $approver): Employee
    {
        $e = Employee::create([
            'name' => 'Pegawai Uji, S.E.',
            'nip' => '199001012020010001',
            'gender' => 'Perempuan',
            'birth_place' => 'Kota Uji',
            'birth_date' => '1990-01-01',
            'education' => 'S1',
            'revision' => 1,
        ]);
        EmployeeAssignment::create([
            'employee_id' => $e->id,
            'type' => 'PNS',
            'unit' => 'Sekretariat',
            'position' => 'Pengelola Administrasi',
            'position_type' => 'Pelaksana',
            'grade' => 'III/a',
            'status' => 'Aktif',
            'effective_date' => '2024-01-01',
            'position_tmt' => '2024-01-01',
            'approved_by' => $approver->id,
        ]);
        return $e;
    }
}
