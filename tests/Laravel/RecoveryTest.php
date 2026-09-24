<?php
namespace Tests\Laravel;
use Tests\TestCase;
use App\Modules\IdentityAccess\Mail\RecoveryLink;
use Illuminate\Support\Facades\{Mail, DB, Hash};
use PragmaRX\Google2FA\Google2FA;
class RecoveryTest extends TestCase
{
    private function setupMail()
    {
        Mail::fake();
        config([
            'nadi.recovery_mail_enabled' => true,
            'mail.from.address' => 'nadi@example.test',
            'nadi.frontend_url' => 'https://nadi.example.test',
        ]);
    }
    public function test_email_recovery_requires_token_new_password_and_new_otp_and_revokes_sessions(): void
    {
        $this->setupMail();
        $u = $this->user('SUPER_ADMIN');
        $u->recovery_email = 'super@example.test';
        $u->save();
        $before = $u->totp_secret;
        $this->postJson('/api/auth/recovery', ['email' => 'super@example.test'])->assertOk();
        $this->assertSame($before, $u->fresh()->totp_secret);
        $this->assertTrue($u->fresh()->is_active);
        $token = '';
        Mail::assertSent(RecoveryLink::class, function ($mail) use (&$token) {
            $token = explode('#', $mail->activationUrl)[1];
            return $mail->hasTo('super@example.test') &&
                str_starts_with($mail->activationUrl, 'https://nadi.example.test/aktivasi#');
        });
        $this->assertSame(64, strlen($token));
        $info = $this->withHeader('X-Activation-Token', $token)
            ->getJson('/api/activation')
            ->assertOk()
            ->json();
        $this->postJson('/api/activation', [
            'password' => 'PemulihanKataSandi-2026',
            'password_confirmation' => 'PemulihanKataSandi-2026',
            'code' => (new Google2FA())->getCurrentOtp($info['secret']),
        ])->assertOk();
        $this->assertTrue(Hash::check('PemulihanKataSandi-2026', $u->fresh()->password));
        $this->assertNotSame($before, $u->fresh()->totp_secret);
        $this->assertSame(2, $u->fresh()->security_version);
        $this->getJson('/api/activation')->assertStatus(410);
        $this->fullSession($u->fresh())->withSession(['security_version'=>1])->getJson('/api/employees')->assertUnauthorized();
    }
    public function test_admin_unknown_email_expired_and_rotated_tokens_are_not_recovery_paths(): void
    {
        $this->setupMail();
        $u = $this->user();
        $u->recovery_email = 'admin@example.test';
        $u->save();
        $this->postJson('/api/auth/recovery', ['email' => 'admin@example.test'])->assertOk();
        Mail::assertNothingSent();
        $this->postJson('/api/auth/recovery', ['email' => 'unknown@example.test'])->assertOk();
        Mail::assertNothingSent();
        $super = $this->user('SUPER_ADMIN');
        $super->recovery_email = 'super@example.test';
        $super->save();
        $this->postJson('/api/auth/recovery', ['email' => 'super@example.test'])->assertOk();
        $token = '';
        Mail::assertSent(RecoveryLink::class, function ($mail) use (&$token) {
            $token = explode('#', $mail->activationUrl)[1];
            return true;
        });
        DB::table('account_recoveries')->update(['expires_at' => now()->subMinute()]);
        $this->withHeader('X-Activation-Token', $token)
            ->getJson('/api/activation')
            ->assertStatus(410);
    }
    public function test_mail_failure_preserves_account_and_rolls_back_recovery(): void
    {
        config(['nadi.recovery_mail_enabled' => true, 'mail.from.address' => 'nadi@example.test']);
        $u = $this->user('SUPER_ADMIN');
        $u->recovery_email = 'super@example.test';
        $u->save();
        Mail::shouldReceive('to')->once()->andThrow(new \RuntimeException('SMTP unavailable'));
        $this->postJson('/api/auth/recovery', ['email' => 'super@example.test'])->assertStatus(503);
        $this->assertDatabaseCount('account_recoveries', 0);
        $this->assertTrue($u->fresh()->is_active);
        $this->assertSame(1, $u->fresh()->security_version);
    }
    public function test_missing_mail_configuration_is_reported_honestly(): void
    {
        config(['nadi.recovery_mail_enabled' => false]);
        $this->postJson('/api/auth/recovery', ['email' => 'super@example.test'])->assertStatus(503);
    }
}
