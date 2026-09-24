<?php
namespace Tests\Laravel;
use Tests\TestCase;
use App\Modules\SnapshotsReports\Models\ExportJob;
use App\Modules\SnapshotsReports\Jobs\RenderExport;
use Illuminate\Support\Facades\{Storage, Queue};
use PhpOffice\PhpSpreadsheet\IOFactory;
class ExportTest extends TestCase
{
    public function test_export_is_frozen_owner_scoped_and_preserves_nip_as_text(): void
    {
        Queue::fake();
        Storage::fake('local');
        $u = $this->user('SUPER_ADMIN');
        $e = $this->employee($u);
        $id = $this->fullSession($u)
            ->postJson('/api/exports', ['kind' => 'employees'])
            ->assertAccepted()
            ->json('id');
        Queue::assertPushed(RenderExport::class);
        $job = ExportJob::findOrFail($id);
        $e->name = 'Nama setelah permintaan';
        $e->save();
        (new RenderExport($id))->handle();
        $job->refresh();
        $book = IOFactory::load(Storage::disk('local')->path($job->file_path));
        $this->assertSame('Pegawai Uji, S.E.', $book->getActiveSheet()->getCell('A2')->getValue());
        $this->assertSame('199001012020010001', $book->getActiveSheet()->getCell('B2')->getValue());
        $this->assertSame('s', $book->getActiveSheet()->getCell('B2')->getDataType());
        $book->disconnectWorksheets();
        $this->get('/api/exports/' . $id . '/download')->assertOk();
        $this->fullSession($this->user())
            ->getJson('/api/exports/' . $id . '/download')
            ->assertForbidden();
    }
    public function test_expired_export_and_admin_creation_are_blocked(): void
    {
        Queue::fake();
        Storage::fake('local');
        $u = $this->user('SUPER_ADMIN');
        $id = $this->fullSession($u)
            ->postJson('/api/exports', ['kind' => 'employees'])
            ->assertAccepted()
            ->json('id');
        (new RenderExport($id))->handle();
        ExportJob::where('id', $id)->update(['expires_at' => now()->subMinute()]);
        $this->getJson('/api/exports/' . $id . '/download')->assertStatus(410);
        $this->fullSession($this->user('ADMIN'))
            ->postJson('/api/exports', ['kind' => 'employees'])
            ->assertForbidden();
    }
    public function test_export_honors_search_type_and_status_filters(): void
    {
        Queue::fake();
        $u = $this->user('SUPER_ADMIN');
        $this->employee($u);
        $id = $this->fullSession($u)
            ->postJson('/api/exports', ['kind' => 'employees', 'search' => 'Tidak cocok'])
            ->assertAccepted()
            ->json('id');
        $this->assertSame([], ExportJob::findOrFail($id)->parameters['rows']);
        $id = $this->postJson('/api/exports', ['kind' => 'employees', 'type' => 'PPPK Penuh Waktu'])
            ->assertAccepted()
            ->json('id');
        $this->assertSame([], ExportJob::findOrFail($id)->parameters['rows']);
        $id = $this->postJson('/api/exports', [
            'kind' => 'employees',
            'search' => 'pegawai uji',
            'status' => 'Aktif',
        ])
            ->assertAccepted()
            ->json('id');
        $this->assertCount(1, ExportJob::findOrFail($id)->parameters['rows']);
    }
    public function test_position_type_filter_combines_with_other_filters(): void
    {
        Queue::fake();
        $u = $this->user('SUPER_ADMIN');
        $e = $this->employee($u);
        $this->fullSession($u);
        foreach (['Struktural', 'Fungsional', 'Pelaksana'] as $positionType) {
            $e->assignments()->update(['position_type' => $positionType]);
            foreach (['Struktural', 'Fungsional', 'Pelaksana', ''] as $filter) {
                $id = $this->postJson('/api/exports', [
                    'kind' => 'employees',
                    'positionType' => $filter,
                    'unit' => 'Sekretariat',
                    'type' => 'PNS',
                    'search' => 'pegawai uji',
                ])
                    ->assertAccepted()
                    ->json('id');
                $this->assertCount(
                    $filter === '' || $filter === $positionType ? 1 : 0,
                    ExportJob::findOrFail($id)->parameters['rows'],
                );
            }
        }
        $id = $this->postJson('/api/exports', [
            'kind' => 'employees',
            'positionType' => 'Pelaksana',
            'type' => 'PPPK Penuh Waktu',
        ])
            ->assertAccepted()
            ->json('id');
        $this->assertSame([], ExportJob::findOrFail($id)->parameters['rows']);
        $this->postJson('/api/exports', [
            'kind' => 'employees',
            'positionType' => 'Lainnya',
        ])->assertUnprocessable();
    }
}
