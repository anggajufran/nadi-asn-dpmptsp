<?php
namespace Tests\Laravel;
use Tests\TestCase;
use Illuminate\Support\Facades\DB;
use PragmaRX\Google2FA\Google2FA;
class ActivationTest extends TestCase
{
    public function test_activation_requires_private_token_password_and_totp_and_is_single_use(): void
    {
        $u = $this->user('SUPER_ADMIN');
        $u->is_active = false;
        $u->login_name = '199001012020010099';
        $u->save();
        $token = str_repeat('a', 64);
        DB::table('account_activations')->insert([
            'user_id' => $u->id,
            'token_hash' => hash('sha256', $token),
            'expires_at' => now()->addHour(),
        ]);
        $this->getJson('/api/activation')->assertForbidden();
        $this->withHeader('X-Activation-Token', $token)
            ->getJson('/api/activation')
            ->assertOk()
            ->assertJsonPath('login', $u->login_name);
        $this->postJson('/api/activation', [
            'password' => 'KataSandiBaru-2026',
            'password_confirmation' => 'KataSandiBaru-2026',
            'code' => (new Google2FA())->getCurrentOtp('JBSWY3DPEHPK3PXP'),
        ])->assertOk();
        $this->assertTrue($u->fresh()->is_active);
        $this->getJson('/api/activation')->assertStatus(410);
    }
}
