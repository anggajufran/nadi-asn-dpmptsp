<?php
namespace Tests\Laravel;
use Tests\TestCase;
use PragmaRX\Google2FA\Google2FA;
class AuthenticationTest extends TestCase
{
    public function test_password_only_creates_challenge_and_totp_is_required(): void
    {
        $u = $this->user();
        $u->login_name = '199001012020010099';
        $u->save();
        $this->postJson('/api/auth/login', [
            'nip' => $u->login_name,
            'password' => 'KataSandiUji-12345',
        ])
            ->assertOk()
            ->assertJson(['challenge' => true]);
        $this->getJson('/api/session')->assertJson(['user' => null]);
        $code = (new Google2FA())->getCurrentOtp('JBSWY3DPEHPK3PXP');
        $this->postJson('/api/auth/totp', ['code' => $code])
            ->assertOk()
            ->assertJsonPath('user.id', $u->id);
        $this->getJson('/api/employees')->assertOk();
        $this->postJson('/api/logout')->assertOk();
        $this->getJson('/api/employees')->assertUnauthorized();
    }
    public function test_used_totp_cannot_be_replayed(): void
    {
        $u = $this->user();
        $u->last_totp_step = (new Google2FA())->getTimestamp();
        $u->save();
        $this->withSession([
            'challenge_user' => $u->id,
            'challenge_security_version' => $u->security_version,
            'challenge_expires' => now()->addMinutes(5)->timestamp,
        ]);
        $this->postJson('/api/auth/totp', [
            'code' => (new Google2FA())->getCurrentOtp('JBSWY3DPEHPK3PXP'),
        ])->assertUnprocessable();
    }
    public function test_password_challenge_is_revoked_when_account_security_changes(): void
    {
        $u = $this->user();
        $this->withSession([
            'challenge_user' => $u->id,
            'challenge_security_version' => $u->security_version,
            'challenge_expires' => now()->addMinutes(5)->timestamp,
        ]);
        $u->increment('security_version');
        $this->postJson('/api/auth/totp', [
            'code' => (new Google2FA())->getCurrentOtp('JBSWY3DPEHPK3PXP'),
        ])->assertUnauthorized();
        $this->getJson('/api/employees')->assertUnauthorized();
    }
}
