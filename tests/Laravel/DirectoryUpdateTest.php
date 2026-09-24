<?php
namespace Tests\Laravel;
use Tests\TestCase;
use App\Modules\Governance\Services\ChangeService;
use App\Modules\Personnel\Services\EmployeeProjection;
use App\Modules\SnapshotsReports\Models\ExportJob;
use App\Modules\SnapshotsReports\Jobs\RenderExport;
use App\Modules\Imports\Services\ImportService;
use Illuminate\Support\Facades\{DB, Queue, Storage};
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\IOFactory;
class DirectoryUpdateTest extends TestCase
{
    public function test_completeness_updates_only_after_approval(): void
    {
        $this->references();
        $maker = $this->user();
        $checker = $this->user('SUPER_ADMIN');
        $e = $this->employee($checker);
        $projection = app(EmployeeProjection::class);
        $before = $projection->project($e, $maker);
        $this->assertLessThan(100, $before['completeness']);
        $this->assertContains('Nama pendidikan', $before['missingFields']);
        $p = app(ChangeService::class)->submit($maker, [
            'employeeId' => $e->id,
            'revision' => 1,
            'changes' => [
                'rankName' => 'Penata Muda / III a',
                'rankTmt' => '2024-01-01',
                'serviceYears' => 0,
                'serviceMonths' => 0,
                'institution' => 'Universitas Uji',
                'graduationDate' => '2019-01-01',
            ],
            'reason' => 'Melengkapi sumber pegawai',
        ]);
        $this->assertSame(
            $before['completeness'],
            $projection->project($e->fresh(), $maker)['completeness'],
        );
        app(ChangeService::class)->decide($checker, $p->id, 'APPROVED', 'Data telah sesuai');
        $after = $projection->project($e->fresh(), $maker);
        $this->assertSame(100, $after['completeness']);
        $this->assertSame([], $after['missingFields']);
    }
    public function test_only_super_admin_can_manage_units_and_rename_keeps_membership(): void
    {
        $this->references();
        $admin = $this->user();
        $this->employee($admin);
        $id = DB::table('reference_values')->where('kind', 'unit')->value('id');
        $this->fullSession($admin)
            ->postJson('/api/units', ['label' => 'Unit Baru'])
            ->assertForbidden();
        $this->fullSession($this->user('SUPER_ADMIN'))
            ->patchJson('/api/units/' . $id, ['label' => 'Sekretariat Baru'])
            ->assertOk();
        $this->getJson('/api/employees')
            ->assertOk()
            ->assertJsonPath('data.0.unit', 'Sekretariat Baru');
        $this->getJson('/api/public/statistics?unit=Sekretariat%20Baru')->assertJsonPath(
            'total',
            1,
        );
        $this->patchJson('/api/units/' . $id, ['active' => false])->assertUnprocessable();
        $new = $this->postJson('/api/units', ['label' => 'Unit Baru'])
            ->assertCreated()
            ->json('id');
        $this->patchJson('/api/units/' . $new, ['active' => false])->assertOk();
    }
    public function test_sheet_batch_rolls_back_on_later_revision_conflict(): void
    {
        $this->references();
        $u = $this->user();
        $a = $this->employee($u);
        $b = $a->replicate();
        $b->nip = '199001012020010002';
        $b->save();
        $assignment = $a->assignments()->first()->replicate();
        $assignment->employee_id = $b->id;
        $assignment->save();
        $this->fullSession($u)
            ->postJson('/api/proposals/batch', [
                'rows' => [
                    [
                        'employeeId' => $a->id,
                        'revision' => 1,
                        'changes' => ['name' => 'Nama baru A'],
                    ],
                    [
                        'employeeId' => $b->id,
                        'revision' => 999,
                        'changes' => ['name' => 'Nama baru B'],
                    ],
                ],
                'reason' => 'Penyimpanan lembar data',
            ])
            ->assertConflict();
        $this->assertDatabaseCount('change_requests', 0);
        $this->postJson('/api/proposals/batch', [
            'rows' => [
                ['employeeId' => $a->id, 'revision' => 1, 'changes' => ['name' => 'Nama baru A']],
            ],
            'reason' => 'Penyimpanan lembar data',
        ])
            ->assertCreated()
            ->assertJsonPath('count', 1);
        $this->assertSame('Pegawai Uji, S.E.', $a->fresh()->name);
    }
    public function test_export_18_columns_can_be_imported_without_changing_values(): void
    {
        Queue::fake();
        Storage::fake('local');
        $this->references();
        $u = $this->user('SUPER_ADMIN');
        $this->employee($u);
        $id = $this->fullSession($u)
            ->postJson('/api/exports', ['kind' => 'employees'])
            ->assertAccepted()
            ->json('id');
        (new RenderExport($id))->handle();
        $job = ExportJob::findOrFail($id);
        $path = Storage::disk('local')->path($job->file_path);
        $book = IOFactory::load($path);
        $this->assertSame('R', $book->getActiveSheet()->getHighestColumn());
        $this->assertNotContains('No. sumber', $book->getActiveSheet()->rangeToArray('A1:R1')[0]);
        $this->assertSame('NIP', $book->getActiveSheet()->getCell('B1')->getValue());
        $this->assertSame('s', $book->getActiveSheet()->getCell('B2')->getDataType());
        $book->disconnectWorksheets();
        $service = app(ImportService::class);
        $b = $service->upload(
            $u,
            UploadedFile::fake()->createWithContent('roundtrip.xlsx', file_get_contents($path)),
        );
        $fields = json_decode(
            file_get_contents(resource_path('js/domain/personnel-schema.json')),
            true,
        );
        $b->mapping = array_column($fields, 'label', 'key');
        $b->status = 'VALIDATING';
        $b->save();
        $service->validate($b);
        $row = $b->rows()->first();
        $this->assertSame([], $row->errors);
        $this->assertSame('UNCHANGED', $row->status);
    }
}
