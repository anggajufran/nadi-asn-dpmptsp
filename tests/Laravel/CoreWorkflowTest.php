<?php
namespace Tests\Laravel;
use Tests\TestCase;
use App\Modules\Governance\Services\ChangeService;
use App\Modules\Personnel\Services\EmployeeProjection;
use App\Shared\CanonicalJson;
use Illuminate\Support\Facades\DB;
class CoreWorkflowTest extends TestCase
{
    public function test_anonymous_and_partial_sessions_are_denied(): void
    {
        $this->getJson('/api/employees')->assertUnauthorized();
        $this->actingAs($this->user())->getJson('/api/employees')->assertUnauthorized();
    }
    public function test_removed_role_cannot_access_internal_data(): void
    {
        $this->fullSession($this->user('VIEWER'))->getJson('/api/employees')->assertUnauthorized();
    }
    public function test_approval_is_transactional_and_preserves_history(): void
    {
        $this->references();
        $maker = $this->user();
        $checker = $this->user('SUPER_ADMIN');
        $e = $this->employee($checker);
        $service = app(ChangeService::class);
        $p = $service->submit($maker, [
            'employeeId' => $e->id,
            'revision' => 1,
            'changes' => ['name' => 'Nama Baru, S.E.'],
            'reason' => 'Sumber sudah diperiksa',
        ]);
        $this->assertSame('Pegawai Uji, S.E.', $e->fresh()->name);
        $service->decide($checker, $p->id, 'APPROVED', 'Sesuai sumber');
        $this->assertSame('Nama Baru, S.E.', $e->fresh()->name);
        $this->assertSame(2, $e->fresh()->revision);
        $this->assertDatabaseCount('employee_assignments', 2);
        $this->assertDatabaseHas('employee_assignments', [
            'employee_id' => $e->id,
            'record_status' => 'OFFICIAL',
        ]);
        $this->assertDatabaseHas('outbox_events', ['aggregate_id' => $e->id]);
        $this->fullSession($checker)
            ->postJson('/api/proposals/' . $p->id . '/decide', [
                'decision' => 'APPROVED',
                'reason' => 'Pengulangan',
            ])
            ->assertConflict();
    }
    public function test_super_admin_can_approve_own_request(): void
    {
        $this->references();
        $user = $this->user('SUPER_ADMIN');
        $e = $this->employee($user);
        $p = app(ChangeService::class)->submit($user, [
            'employeeId' => $e->id,
            'revision' => 1,
            'changes' => ['name' => 'Usulan sendiri'],
            'reason' => 'Pengujian sendiri',
        ]);
        $this->fullSession($user)
            ->postJson('/api/proposals/' . $p->id . '/decide', [
                'decision' => 'APPROVED',
                'reason' => 'Tidak diperbolehkan',
            ])
            ->assertOk();
    }
    public function test_stale_revision_is_rejected(): void
    {
        $this->references();
        $user = $this->user();
        $e = $this->employee($user);
        $this->fullSession($user)
            ->postJson('/api/proposals', [
                'employeeId' => $e->id,
                'revision' => 2,
                'changes' => ['name' => 'Stale'],
                'reason' => 'Versi sudah berubah',
            ])
            ->assertConflict();
    }
    public function test_canonical_hash_survives_postgres_jsonb_key_order(): void
    {
        $a = ['z' => 1, 'nested' => ['b' => 2, 'a' => 1], 'list' => [3, 2, 1]];
        $json = DB::selectOne('select ?::jsonb::text as value', [json_encode($a)])->value;
        $this->assertSame(CanonicalJson::hash($a), CanonicalJson::hash(json_decode($json, true)));
    }
    public function test_public_statistics_allow_aggregates_only(): void
    {
        $this->getJson('/api/public/statistics')->assertOk()->assertJsonPath('total', 0);
        $this->getJson('/api/public/statistics?nip=123')->assertUnprocessable();
    }
    public function test_revoked_security_version_blocks_old_session(): void
    {
        $u = $this->user();
        $this->fullSession($u);
        $u->security_version = 2;
        $u->save();
        $this->getJson('/api/employees')->assertUnauthorized();
    }
    public function test_database_audit_trigger_blocks_updates(): void
    {
        $u = $this->user();
        \App\Shared\Audit::append($u, 'Uji audit', 'Fixture');
        $id = DB::table('audit_events')->value('id');
        $this->expectException(\Illuminate\Database\QueryException::class);
        DB::transaction(
            fn() => DB::table('audit_events')
                ->where('id', $id)
                ->update(['action' => 'Diedit']),
        );
    }
    public function test_assignment_resolver_excludes_future_facts(): void
    {
        $u = $this->user();
        $e = $this->employee($u);
        $assignment = $e->assignments()->first()->replicate();
        $assignment->effective_date = today()->addYear();
        $assignment->position = 'Jabatan masa depan';
        $assignment->save();
        $this->assertSame(
            'Pengelola Administrasi',
            app(EmployeeProjection::class)->project($e->load('assignments'), $u)['position'],
        );
    }
}
