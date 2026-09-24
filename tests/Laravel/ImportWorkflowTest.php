<?php
namespace Tests\Laravel;
use Tests\TestCase;
use App\Modules\Imports\Services\ImportService;
use App\Modules\Imports\Models\ImportBatch;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\{Spreadsheet, Writer\Xlsx, Cell\DataType};
class ImportWorkflowTest extends TestCase
{
    private function workbook(array $names, bool $numericNip = false): UploadedFile
    {
        $book = new Spreadsheet();
        $s = $book->getActiveSheet();
        $s->fromArray(['NIP', 'Nama'], null, 'A1');
        foreach ($names as $i => $name) {
            $s->setCellValueExplicit(
                'A' . ($i + 2),
                '199001012020010001',
                $numericNip ? DataType::TYPE_NUMERIC : DataType::TYPE_STRING,
            );
            $s->setCellValue('B' . ($i + 2), $name);
        }
        $path = tempnam(sys_get_temp_dir(), 'nadi-xlsx-');
        (new Xlsx($book))->save($path);
        $book->disconnectWorksheets();
        $bytes = file_get_contents($path);
        unlink($path);
        return UploadedFile::fake()->createWithContent('pegawai.xlsx', $bytes);
    }
    public function test_preview_does_not_change_employee_and_confirmation_is_idempotent(): void
    {
        Storage::fake('local');
        $this->references();
        $u = $this->user();
        $e = $this->employee($u);
        $service = app(ImportService::class);
        $batch = $service->upload($u, $this->workbook(['Nama hasil import']));
        $batch->mapping = ['nip' => 'NIP', 'name' => 'Nama'];
        $batch->status = 'VALIDATING';
        $batch->save();
        $service->validate($batch);
        $batch->refresh();
        $this->assertSame('PREVIEW', $batch->status);
        $this->assertSame('VALID', $batch->rows()->first()->status);
        $this->assertSame('Pegawai Uji, S.E.', $e->fresh()->name);
        $input = ['previewHash' => $batch->preview_hash, 'reason' => 'Pengajuan import uji'];
        $this->assertSame(1, $service->confirm($u, $batch, $input));
        $this->assertSame(1, $service->confirm($u, $batch, $input));
        $this->assertDatabaseCount('change_requests', 1);
        $this->assertSame('Pegawai Uji, S.E.', $e->fresh()->name);
    }
    public function test_numeric_nip_and_duplicate_rows_cannot_be_submitted(): void
    {
        Storage::fake('local');
        $this->references();
        $u = $this->user();
        $this->employee($u);
        $service = app(ImportService::class);
        $b = $service->upload($u, $this->workbook(['Nama A', 'Nama B'], true));
        $b->mapping = ['nip' => 'NIP', 'name' => 'Nama'];
        $b->status = 'VALIDATING';
        $b->save();
        $service->validate($b);
        $this->assertSame(2, $b->rows()->where('status', 'INVALID')->count());
        $this->assertStringContainsString('teks', implode(' ', $b->rows()->first()->errors));
        $this->assertStringContainsString('duplikat', implode(' ', $b->rows()->first()->errors));
    }
    public function test_stale_preview_rolls_back_whole_confirmation(): void
    {
        Storage::fake('local');
        $this->references();
        $u = $this->user();
        $e = $this->employee($u);
        $service = app(ImportService::class);
        $b = $service->upload($u, $this->workbook(['Nama baru']));
        $b->mapping = ['nip' => 'NIP', 'name' => 'Nama'];
        $b->status = 'VALIDATING';
        $b->save();
        $service->validate($b);
        $b->refresh();
        $e->increment('revision');
        $this->fullSession($u)
            ->postJson('/api/imports/' . $b->id . '/confirm', [
                'previewHash' => $b->preview_hash,
                'reason' => 'Sumber berubah saat review',
            ])
            ->assertConflict();
        $this->assertDatabaseCount('change_requests', 0);
        $this->assertSame('PREVIEW', $b->fresh()->status);
    }
    public function test_other_admin_cannot_read_or_confirm_private_batch(): void
    {
        Storage::fake('local');
        $u = $this->user();
        $service = app(ImportService::class);
        $b = $service->upload($u, $this->workbook(['Nama A']));
        $other = $this->user();
        $this->fullSession($other)
            ->getJson('/api/imports/' . $b->id)
            ->assertForbidden();
    }
}
