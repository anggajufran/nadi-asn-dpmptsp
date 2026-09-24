<?php
namespace App\Modules\IdentityAccess\Http;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Encryption\Encrypter;
use PragmaRX\Google2FA\Google2FA;
use App\Modules\IdentityAccess\Models\User;
use App\Modules\Personnel\Models\Employee;
use App\Shared\Audit;
class AccountController
{
    private function authorize(Request $r): void
    {
        abort_unless($r->user()->role === 'SUPER_ADMIN', 403);
    }
    public function index(Request $r)
    {
        $this->authorize($r);
        $pending = DB::table('account_activations')->get()->keyBy('user_id');
        return response()->json(
            User::whereIn('role', ['ADMIN', 'SUPER_ADMIN'])
                ->orderBy('name')
                ->get()
                ->map(function ($u) use ($pending) {
                    $a = $pending->get($u->id);
                    return [
                        'id' => $u->id,
                        'employeeId' => $u->employee_id,
                        'name' => $u->name,
                        'login' => $u->login_name,
                        'role' => $u->role,
                        'status' => $u->is_active
                            ? 'ACTIVE'
                            : ($a
                                ? ($a->expires_at > now()
                                    ? 'PENDING'
                                    : 'EXPIRED')
                                : 'DISABLED'),
                        'expiresAt' => $a?->expires_at,
                    ];
                }),
        );
    }
    private function issue(User $u, User $actor): array
    {
        $token = bin2hex(random_bytes(32));
        $secret = (new Google2FA())->generateSecretKey();
        $u->forceFill([
            'is_active' => false,
            'security_version' => $u->security_version + 1,
            'password' => Str::random(64),
            'last_totp_step' => null,
            'totp_secret' => (new Encrypter(
                base64_decode(config('nadi.totp_key')),
                'AES-256-CBC',
            ))->encryptString($secret),
        ])->save();
        $expiry = now()->addHours(24);
        DB::table('account_activations')->updateOrInsert(
            ['user_id' => $u->id],
            ['token_hash' => hash('sha256', $token), 'expires_at' => $expiry],
        );
        Audit::append($actor, 'Membuat tautan aktivasi / reset Admin Kepegawaian', $u->name, [
            'user_id' => $u->id,
        ]);
        return [
            'id' => $u->id,
            'activationPath' => '/aktivasi#' . $token,
            'expiresAt' => $expiry->toIso8601String(),
        ];
    }
    public function store(Request $r)
    {
        $this->authorize($r);
        $v = $r->validate(['employeeId' => 'required|uuid', 'role' => 'prohibited']);
        $result = DB::transaction(function () use ($r, $v) {
            $e = Employee::lockForUpdate()->findOrFail($v['employeeId']);
            $assignment = $e
                ->assignments()
                ->where('record_status', 'OFFICIAL')
                ->whereDate('effective_date', '<=', today())
                ->orderByDesc('effective_date')
                ->first();
            abort_unless(
                $assignment?->status === 'Aktif',
                422,
                'Akun hanya dapat dibuat untuk pegawai aktif.',
            );
            abort_if(
                User::where('employee_id', $e->id)->orWhere('login_name', $e->nip)->exists(),
                409,
                'Pegawai ini sudah memiliki akun. Gunakan tautan aktivasi/reset pada akun tersebut.',
            );
            $u = new User();
            $u->forceFill([
                'employee_id' => $e->id,
                'name' => $e->name,
                'login_name' => $e->nip,
                'role' => 'ADMIN',
                'security_version' => 0,
            ]);
            return $this->issue($u, $r->user());
        });
        return response()->json($result, 201);
    }
    public function activation(Request $r, string $id)
    {
        $this->authorize($r);
        return response()->json(
            DB::transaction(function () use ($r, $id) {
                $u = User::lockForUpdate()->findOrFail($id);
                abort_unless(
                    $u->role === 'ADMIN' && $u->id !== $r->user()->id,
                    403,
                    'Akun Super Admin tidak dikelola melalui fitur ini.',
                );
                $e = Employee::find($u->employee_id);
                $a = $e
                    ?->assignments()
                    ->where('record_status', 'OFFICIAL')
                    ->whereDate('effective_date', '<=', today())
                    ->orderByDesc('effective_date')
                    ->first();
                abort_unless(
                    $a?->status === 'Aktif',
                    422,
                    'Pegawai harus aktif untuk menerima akses.',
                );
                return $this->issue($u, $r->user());
            }),
        );
    }
    public function disable(Request $r, string $id)
    {
        $this->authorize($r);
        DB::transaction(function () use ($r, $id) {
            $u = User::lockForUpdate()->findOrFail($id);
            abort_unless(
                $u->role === 'ADMIN' && $u->id !== $r->user()->id,
                403,
                'Akun Super Admin tidak dapat dinonaktifkan melalui fitur ini.',
            );
            $u->forceFill([
                'is_active' => false,
                'security_version' => $u->security_version + 1,
            ])->save();
            DB::table('account_activations')->where('user_id', $u->id)->delete();
            Audit::append($r->user(), 'Menonaktifkan akses Admin Kepegawaian', $u->name, [
                'user_id' => $u->id,
            ]);
        });
        return response()->json(['ok' => true]);
    }
}
