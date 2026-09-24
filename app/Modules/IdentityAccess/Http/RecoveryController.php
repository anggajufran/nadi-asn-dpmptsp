<?php
namespace App\Modules\IdentityAccess\Http;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB, Mail};
use Illuminate\Encryption\Encrypter;
use App\Modules\IdentityAccess\Models\User;
use App\Modules\IdentityAccess\Mail\RecoveryLink;
use App\Shared\Audit;
use PragmaRX\Google2FA\Google2FA;
class RecoveryController
{
    public function store(Request $r)
    {
        $v = $r->validate(['email' => 'required|email|max:200']);
        abort_unless(
            config('nadi.recovery_mail_enabled') &&
                config('mail.from.address') &&
                config('nadi.frontend_url'),
            503,
            'Pengiriman email pemulihan belum dikonfigurasi. Hubungi pengelola aplikasi.',
        );
        $email = strtolower(trim($v['email']));
        try {
            DB::transaction(function () use ($email) {
                $u = User::where('role', 'SUPER_ADMIN')
                    ->whereRaw('lower(recovery_email)=?', [$email])
                    ->lockForUpdate()
                    ->first();
                if (!$u) {
                    return;
                }
                $previous = DB::table('account_recoveries')->where('user_id', $u->id)->first();
                if (
                    $previous &&
                    \Carbon\Carbon::parse($previous->created_at)->gt(now()->subMinute())
                ) {
                    return;
                }
                $token = bin2hex(random_bytes(32));
                $secret = (new Encrypter(
                    base64_decode(config('nadi.totp_key')),
                    'AES-256-CBC',
                ))->encryptString((new Google2FA())->generateSecretKey());
                DB::table('account_recoveries')->updateOrInsert(
                    ['user_id' => $u->id],
                    [
                        'token_hash' => hash('sha256', $token),
                        'totp_secret' => $secret,
                        'security_version' => $u->security_version,
                        'expires_at' => now()->addMinutes(30),
                        'created_at' => now(),
                    ],
                );
                Mail::to($u->recovery_email)->send(
                    new RecoveryLink(
                        rtrim(config('nadi.frontend_url'), '/') . '/aktivasi#' . $token,
                    ),
                );
                Audit::append($u, 'Meminta pemulihan akun melalui email', 'Akun Super Admin', [
                    'channel' => 'email',
                ]);
            });
        } catch (\Throwable $e) {
            // Never log the mail body or link; an SMTP failure must not leave a usable token behind.
            return response()->json(
                [
                    'message' =>
                        'Email belum berhasil dikirim. Silakan coba lagi atau hubungi pengelola aplikasi.',
                ],
                503,
            );
        }
        return response()->json([
            'message' =>
                'Jika email sesuai dengan akun Super Admin yang terdaftar, tautan aktivasi akan dikirim. Periksa kotak masuk dan folder spam.',
        ]);
    }
}
