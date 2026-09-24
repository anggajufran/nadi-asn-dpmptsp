<?php
namespace Tests\Laravel;
use Tests\TestCase;
use Illuminate\Support\Facades\{DB, Queue, Storage};
use App\Modules\SnapshotsReports\Models\ExportJob;
use App\Modules\SnapshotsReports\Jobs\RenderExport;
use PhpOffice\PhpSpreadsheet\IOFactory;
class DukPublicationTest extends TestCase
{
    private function directory($u): array
    {
        $this->references();
        $a = $this->employee($u);
        $a->update(['source_order' => 1]);
        $b = $a->replicate();
        $b->nip = '199001012020010002';
        $b->name = 'Pegawai Kedua';
        $b->source_order = 2;
        $b->save();
        $assignment = $a->assignments()->first()->replicate();
        $assignment->employee_id = $b->id;
        $assignment->save();
        return [$a, $b];
    }
    public function test_manual_order_persists_and_master_excel_matches_without_changing_source(): void
    {
        Queue::fake();
        Storage::fake('local');
        $u = $this->user('SUPER_ADMIN');
        [$a, $b] = $this->directory($u);
        $p = $this->fullSession($u)->getJson('/api/duk')->assertOk()->json();
        $this->assertSame([$a->id, $b->id], $p['order']);
        $this->postJson('/api/duk/order', [
            'order' => [$b->id, $a->id],
            'hash' => $p['hash'],
            'rows' => [],
            'reason' => 'Menyesuaikan urutan manual',
        ])->assertOk();
        $this->assertSame(1, $a->fresh()->source_order);
        $this->assertSame(2, $a->fresh()->duk_order);
        $this->getJson('/api/employees')->assertJsonPath('data.0.id', $b->id);
        $this->getJson('/api/duk')->assertJsonPath('rows.0.id', $b->id);
        $jobId = $this->postJson('/api/exports', ['kind' => 'employees'])
            ->assertAccepted()
            ->json('id');
        (new RenderExport($jobId))->handle();
        $book = IOFactory::load(
            Storage::disk('local')->path(ExportJob::findOrFail($jobId)->file_path),
        );
        $this->assertSame($b->nip, $book->getActiveSheet()->getCell('B2')->getValue());
        $this->assertSame($a->nip, $book->getActiveSheet()->getCell('B3')->getValue());
        $book->disconnectWorksheets();
        $this->assertDatabaseHas('audit_events', [
            'action' => 'Menyimpan urutan DUK manual',
            'actor_id' => $u->id,
        ]);
    }
    public function test_manual_order_rejects_missing_duplicate_stale_and_admin_requests(): void
    {
        $u = $this->user('SUPER_ADMIN');
        [$a, $b] = $this->directory($u);
        $p = $this->fullSession($u)->getJson('/api/duk')->json();
        $body = [
            'order' => [$b->id, $a->id],
            'hash' => $p['hash'],
            'rows' => [],
            'reason' => 'Penyesuaian urutan',
        ];
        $this->postJson('/api/duk/order', [...$body, 'order' => [$a->id]])->assertUnprocessable();
        $this->postJson('/api/duk/order', [
            ...$body,
            'order' => [$a->id, $a->id],
        ])->assertUnprocessable();
        $this->postJson('/api/duk/order', $body)->assertOk();
        $this->postJson('/api/duk/order', $body)->assertConflict();
        $this->fullSession($this->user())->postJson('/api/duk/order', $body)->assertForbidden();
    }
    public function test_data_conflict_rolls_back_order_and_super_can_approve_own_sheet_changes(): void
    {
        $u = $this->user('SUPER_ADMIN');
        [$a, $b] = $this->directory($u);
        $p = $this->fullSession($u)->getJson('/api/duk')->json();
        $body = [
            'order' => [$b->id, $a->id],
            'hash' => $p['hash'],
            'reason' => 'Perbaikan lembar DUK',
            'rows' => [
                [
                    'employeeId' => $a->id,
                    'revision' => 999,
                    'changes' => ['name' => 'Nama diperbaiki'],
                ],
            ],
        ];
        $this->postJson('/api/duk/order', $body)->assertConflict();
        $this->getJson('/api/duk')->assertJsonPath('order', $p['order']);
        $body['rows'][0]['revision'] = 1;
        $this->postJson('/api/duk/order', $body)->assertOk()->assertJsonPath('count', 1);
        $id = DB::table('change_requests')->value('id');
        $this->postJson('/api/proposals/' . $id . '/decide', [
            'decision' => 'APPROVED',
            'reason' => 'Sesuai sumber pegawai',
        ])->assertOk();
        $this->assertSame('Nama diperbaiki', $a->fresh()->name);
        $this->assertSame(2, $a->fresh()->duk_order);
    }
    public function test_anonymous_cannot_read_and_automatic_candidates_retired(): void
    {
        $this->getJson('/api/duk')->assertUnauthorized();
        $this->fullSession($this->user('SUPER_ADMIN'))
            ->postJson('/api/duk/candidates', [])
            ->assertNotFound();
    }
    public function test_statistics_pdf_places_units_first(): void
    {
        $u = $this->user('SUPER_ADMIN');
        $this->employee($u);
        $stats = app(\App\Modules\Analytics\Services\PublicReleaseReader::class)->read([
            'unit' => 'Sekretariat',
        ]);
        $html = view('reports.statistics', ['stats' => $stats, 'id' => 'test'])->render();
        $this->assertMatchesRegularExpression(
            '/<tbody>\s*<tr><td>Unit kerja<\/td><td>Sekretariat<\/td>/',
            $html,
        );
    }
}
