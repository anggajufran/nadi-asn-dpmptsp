<?php
namespace App\Modules\IdentityAccess\Http;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Encryption\Encrypter;
use PragmaRX\Google2FA\Google2FA;
use App\Modules\IdentityAccess\Models\User;
use App\Shared\Audit;
class ActivationController
{
    private function activation(Request $r)
    {
        $token = $r->header('X-Activation-Token');
        abort_unless(
            is_string($token) && strlen($token) === 64,
            403,
            'Tautan aktivasi tidak valid.',
        );
        foreach (['account_activations', 'account_recoveries'] as $table) {
            $a = DB::table($table)
                ->where('token_hash', hash('sha256', $token))
                ->where('expires_at', '>', now())
                ->first();
            if ($a) {
                $a->table = $table;
                if ($table === 'account_recoveries') {
                    $u = User::find($a->user_id);
                    abort_unless(
                        $u &&
                            $u->role === 'SUPER_ADMIN' &&
                            $u->security_version === $a->security_version,
                        410,
                        'Tautan tidak berlaku lagi.',
                    );
                }
                return $a;
            }
        }
        abort(410, 'Tautan sudah digunakan atau kedaluwarsa.');
    }
    public function show(Request $r)
    {
        $a = $this->activation($r);
        $u = User::findOrFail($a->user_id);
        $key = base64_decode(config('nadi.totp_key'));
        $secret = (new Encrypter($key, 'AES-256-CBC'))->decryptString(
            $a->table === 'account_recoveries' ? $a->totp_secret : $u->totp_secret,
        );
        return response()->json([
            'name' => $u->name,
            'role' => $u->role,
            'login' => $u->login_name,
            'secret' => $secret,
        ]);
    }
    public function store(Request $r)
    {
        $v = $r->validate([
            'password' => 'required|string|min:12|max:200|confirmed',
            'code' => 'required|digits:6',
        ]);
        $a = $this->activation($r);
        DB::transaction(function () use ($a, $r, $v) {
            $u = User::lockForUpdate()->findOrFail($a->user_id);
            $table = $a->table;
            $a = DB::table($table)->where('user_id', $a->user_id)->lockForUpdate()->first();
            abort_unless(
                $a &&
                    $a->expires_at > now() &&
                    hash_equals($a->token_hash, hash('sha256', $r->header('X-Activation-Token'))),
                410,
            );
            $recovery = $table === 'account_recoveries';
            abort_if(
                $recovery &&
                    ($u->role !== 'SUPER_ADMIN' || $u->security_version !== $a->security_version),
                410,
            );
            $encryptedSecret = $recovery ? $a->totp_secret : $u->totp_secret;
            $secret = (new Encrypter(
                base64_decode(config('nadi.totp_key')),
                'AES-256-CBC',
            ))->decryptString($encryptedSecret);
            $step = (new Google2FA())->verifyKeyNewer(
                $secret,
                $v['code'],
                $recovery ? null : $u->last_totp_step,
                1,
            );
            abort_unless($step !== false, 422, 'Kode autentikator tidak sesuai.');
            $u->password = $v['password'];
            if ($recovery) {
                $u->totp_secret = $encryptedSecret;
                $u->security_version++;
            }
            $u->last_totp_step = $step;
            $u->is_active = true;
            $u->save();
            DB::table('account_activations')->where('user_id', $u->id)->delete();
            DB::table('account_recoveries')->where('user_id', $u->id)->delete();
            Audit::append($u, 'Mengaktifkan akun dengan TOTP', 'Akun sendiri');
        });
        return response()->json(['ok' => true]);
    }
}
