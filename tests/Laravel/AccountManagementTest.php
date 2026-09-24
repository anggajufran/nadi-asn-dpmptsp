<?php
namespace Tests\Laravel;
use Tests\TestCase;
use App\Modules\IdentityAccess\Models\User;
use Illuminate\Support\Facades\{DB, Hash};
use PragmaRX\Google2FA\Google2FA;
class AccountManagementTest extends TestCase
{
    public function test_multiple_admins_activation_reset_disable_and_status(): void
    {
        $super = $this->user('SUPER_ADMIN');
        $e = $this->employee($super);
        $new = $this->fullSession($super)
            ->postJson('/api/accounts', ['employeeId' => $e->id])
            ->assertCreated()
            ->json();
        $admin = User::findOrFail($new['id']);
        $this->assertSame('ADMIN', $admin->role);
        $this->assertFalse($admin->is_active);
        $this->assertSame($e->nip, $admin->login_name);
        $this->postJson('/api/accounts', ['employeeId' => $e->id])->assertConflict();
        $e2 = $e->replicate();
        $e2->nip = '199001012020010002';
        $e2->save();
        $a = $e->assignments()->first()->replicate();
        $a->employee_id = $e2->id;
        $a->save();
        $this->postJson('/api/accounts', ['employeeId' => $e2->id])->assertCreated();
        $this->assertSame(2, User::where('role', 'ADMIN')->count());
        $list = $this->getJson('/api/accounts')->assertOk()->json();
        $this->assertSame('PENDING', collect($list)->firstWhere('id', $admin->id)['status']);
        $this->assertArrayNotHasKey('totp_secret', $list[0]);
        $token = explode('#', $new['activationPath'])[1];
        $info = $this->withHeader('X-Activation-Token', $token)
            ->getJson('/api/activation')
            ->assertOk()
            ->json();
        $this->postJson('/api/activation', [
            'password' => 'KataSandiPegawai-2026',
            'password_confirmation' => 'KataSandiPegawai-2026',
            'code' => (new Google2FA())->getCurrentOtp($info['secret']),
        ])->assertOk();
        $this->assertTrue($admin->fresh()->is_active);
        $this->assertTrue(Hash::check('KataSandiPegawai-2026', $admin->fresh()->password));
        $this->getJson('/api/activation')->assertStatus(410);
        $this->fullSession($admin->fresh())->getJson('/api/employees')->assertOk();
        $version = $admin->fresh()->security_version;
        $this->travel(1)->minutes();
        $reset = $this->fullSession($super)
            ->postJson('/api/accounts/' . $admin->id . '/activation')
            ->assertOk()
            ->json();
        $this->assertGreaterThan($version, $admin->fresh()->security_version);
        $this->assertFalse($admin->fresh()->is_active);
        $this->assertFalse(Hash::check('KataSandiPegawai-2026', $admin->fresh()->password));
        $this->withHeader('X-Activation-Token', explode('#', $reset['activationPath'])[1])
            ->getJson('/api/activation')
            ->assertOk();
        $this->assertNotSame($info['secret'], $this->getJson('/api/activation')->json('secret'));
        $this->postJson('/api/accounts/' . $admin->id . '/disable')->assertOk();
        $this->getJson('/api/activation')->assertStatus(410);
        $list = $this->getJson('/api/accounts')->json();
        $this->assertSame('DISABLED', collect($list)->firstWhere('id', $admin->id)['status']);
        $this->assertSame(
            0,
            DB::table('audit_events')
                ->whereRaw('metadata::text LIKE ?', ['%' . $token . '%'])
                ->count(),
        );
    }
    public function test_admin_cannot_manage_accounts_and_super_account_is_protected(): void
    {
        $super = $this->user('SUPER_ADMIN');
        $e = $this->employee($super);
        $admin = $this->user();
        $this->fullSession($admin)->getJson('/api/accounts')->assertForbidden();
        $this->postJson('/api/accounts', ['employeeId' => $e->id])->assertForbidden();
        $this->postJson('/api/accounts/' . $super->id . '/disable')->assertForbidden();
        $this->fullSession($super)
            ->postJson('/api/accounts/' . $super->id . '/disable')
            ->assertForbidden();
        $this->postJson('/api/accounts/' . $super->id . '/activation')->assertForbidden();
        $this->postJson('/api/accounts', [
            'employeeId' => $e->id,
            'role' => 'SUPER_ADMIN',
        ])->assertUnprocessable();
        $e->assignments()->update(['status' => 'Pensiun']);
        $this->postJson('/api/accounts', ['employeeId' => $e->id])->assertUnprocessable();
    }
    public function test_expired_and_rotated_links_cannot_be_used(): void
    {
        $super = $this->user('SUPER_ADMIN');
        $e = $this->employee($super);
        $new = $this->fullSession($super)
            ->postJson('/api/accounts', ['employeeId' => $e->id])
            ->assertCreated()
            ->json();
        $this->postJson('/api/accounts/' . $new['id'] . '/activation')->assertOk();
        $this->withHeader('X-Activation-Token', explode('#', $new['activationPath'])[1])
            ->getJson('/api/activation')
            ->assertStatus(410);
        DB::table('account_activations')
            ->where('user_id', $new['id'])
            ->update(['expires_at' => now()->subMinute()]);
        $this->assertSame(
            'EXPIRED',
            collect($this->getJson('/api/accounts')->json())->firstWhere('id', $new['id'])[
                'status'
            ],
        );
    }
}
