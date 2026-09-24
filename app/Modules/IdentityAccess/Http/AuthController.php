<?php
namespace App\Modules\IdentityAccess\Http;
use App\Modules\IdentityAccess\Models\User;
use App\Shared\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Auth, DB, Hash};
use Illuminate\Encryption\Encrypter;
use PragmaRX\Google2FA\Google2FA;
class AuthController
{
    public function session(Request $r)
    {
        $u = $r->user();
        $valid =
            $u &&
            $u->is_active &&
            in_array($u->role, ['ADMIN', 'SUPER_ADMIN'], true) &&
            $r->session()->get('totp_completed') &&
            $r->session()->get('security_version') === $u->security_version &&
            now()->timestamp < (int) $r->session()->get('absolute_expires_at', 0);
        return response()->json([
            'user' => $valid ? ['id' => $u->id, 'name' => $u->name, 'role' => $u->role] : null,
            'demo' => false,
        ]);
    }
    public function login(Request $r)
    {
        $v = $r->validate([
            'nip' => 'required|regex:/^\d{18}$/',
            'password' => 'required|string|max:200',
        ]);
        $user = User::where('login_name', $v['nip'])->first();
        if (
            !$user ||
            !$user->is_active ||
            !in_array($user->role, ['ADMIN', 'SUPER_ADMIN'], true) ||
            !Hash::check($v['password'], $user->password)
        ) {
            return response()->json(['message' => 'NIP atau kata sandi tidak valid.'], 422);
        }
        if (!$user->totp_secret) {
            return response()->json(
                ['message' => 'TOTP belum disiapkan. Hubungi Super Admin.'],
                403,
            );
        }
        Auth::logout();
        $r->session()->invalidate();
        $r->session()->regenerateToken();
        $r->session()->put([
            'challenge_user' => $user->id,
            'challenge_expires' => now()->addMinutes(5)->timestamp,
        ]);
        return response()->json(['challenge' => true]);
    }
    public function totp(Request $r)
    {
        $r->validate(['code' => 'required|digits:6']);
        abort_unless(
            $r->session()->get('challenge_user') &&
                now()->timestamp < (int) $r->session()->get('challenge_expires', 0),
            401,
            'Verifikasi telah kedaluwarsa.',
        );
        $user = DB::transaction(function () use ($r) {
            $u = User::lockForUpdate()->findOrFail($r->session()->get('challenge_user'));
            abort_unless($u->is_active, 403);
            $key = base64_decode((string) config('nadi.totp_key'), true);
            abort_unless($key && strlen($key) === 32, 503, 'Konfigurasi TOTP belum tersedia.');
            $secret = (new Encrypter($key, 'AES-256-CBC'))->decryptString($u->totp_secret);
            $step = (new Google2FA())->verifyKeyNewer(
                $secret,
                $r->input('code'),
                $u->last_totp_step,
                1,
            );
            abort_unless($step !== false, 422, 'Kode tidak valid atau sudah digunakan.');
            $u->last_totp_step = $step;
            $u->save();
            Audit::append($u, 'Masuk dengan TOTP', 'Sesi');
            return $u;
        });
        Auth::login($user);
        $r->session()->regenerate();
        $r->session()->forget(['challenge_user', 'challenge_expires']);
        $r->session()->put([
            'totp_completed' => true,
            'security_version' => $user->security_version,
            'absolute_expires_at' => now()->addHours(config('nadi.absolute_session_hours'))
                ->timestamp,
        ]);
        return $this->session($r);
    }
    public function logout(Request $r)
    {
        Auth::logout();
        $r->session()->invalidate();
        $r->session()->regenerateToken();
        return response()->json(['ok' => true]);
    }
}
