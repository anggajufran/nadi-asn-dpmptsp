<?php
namespace App\Modules\IdentityAccess\Mail;
use Illuminate\Mail\Mailable;
class RecoveryLink extends Mailable
{
    public function __construct(public string $activationUrl) {}
    public function build()
    {
        return $this->subject('Pemulihan akun Super Admin NADI ASN')->view('mail.recovery', [
            'url' => $this->activationUrl,
        ]);
    }
}
