<?php
namespace Tests\Laravel;
use Tests\TestCase;
use App\Modules\Imports\Services\UploadScanner;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\{Spreadsheet, Writer\Xlsx};
class UploadSecurityTest extends TestCase
{
    private function workbook(?string $entry = null, string|int $content = 'unsafe'): UploadedFile
    {
        $book = new Spreadsheet();
        $book->getActiveSheet()->fromArray([['Nama'], ['Pegawai uji']]);
        $path = tempnam(sys_get_temp_dir(), 'nadi-security-');
        (new Xlsx($book))->save($path);
        $book->disconnectWorksheets();
        if ($entry) {
            $zip = new \ZipArchive();
            $zip->open($path);
            if (is_int($content)) {
                $large = tmpfile();
                for ($i = 0; $i < $content; $i++) {
                    fwrite($large, str_repeat('A', 1024 * 1024));
                }
                $zip->addFile(stream_get_meta_data($large)['uri'], $entry);
            } else {
                $zip->addFromString($entry, $content);
            }
            $zip->close();
            if (isset($large)) {
                fclose($large);
            }
        }
        $bytes = file_get_contents($path);
        unlink($path);
        return UploadedFile::fake()->createWithContent('data.xlsx', $bytes);
    }
    public function test_scanner_failure_never_creates_import_batch(): void
    {
        config([
            'uploads.scan_enabled' => true,
            'uploads.clamav_host' => '127.0.0.1',
            'uploads.clamav_port' => 1,
        ]);
        $this->fullSession($this->user())
            ->post('/api/imports', ['file' => $this->workbook()])
            ->assertStatus(503);
        $this->assertDatabaseCount('import_batches', 0);
    }
    public function test_production_cannot_disable_scanning(): void
    {
        $this->app->instance('env', 'production');
        config(['uploads.scan_enabled' => false]);
        try {
            app(UploadScanner::class)->assertClean(__FILE__);
            $this->fail('Production must reject uploads without a scanner.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            $this->assertSame(503, $e->getStatusCode());
        }
    }
    public function test_infected_file_is_rejected_before_parsing_or_storage(): void
    {
        $this->mock(UploadScanner::class)
            ->shouldReceive('assertClean')
            ->once()
            ->andReturnUsing(fn() => abort(422, 'Berkas ditolak oleh pemeriksaan keamanan.'));
        $this->fullSession($this->user())
            ->post('/api/imports', ['file' => $this->workbook()])
            ->assertStatus(422);
        $this->assertDatabaseCount('import_batches', 0);
    }
    public function test_active_archive_content_is_rejected(): void
    {
        $this->fullSession($this->user());
        foreach (
            [
                ['xl/VBAPROJECT.bin', 'macro'],
                ['xl/embeddings/oleObject1.bin', 'embedded'],
                ['../outside.xml', 'traversal'],
                ['xl/externalLinks/externalLink1.xml', 'external'],
                [
                    'xl/worksheets/_rels/sheet1.xml.rels',
                    '<Relationships><Relationship TargetMode="External" Target="https://example.test"/></Relationships>',
                ],
                ['xl/unsafe.xml', '<!DOCTYPE x [<!ENTITY x SYSTEM "file:///etc/passwd">]>'],
                ['xl/large.xml', 26],
            ]
            as [$entry, $content]
        ) {
            $this->post('/api/imports', [
                'file' => $this->workbook($entry, $content),
            ])->assertStatus(422);
        }
        $this->assertDatabaseCount('import_batches', 0);
    }
    public function test_fake_xlsx_and_oversized_upload_are_rejected(): void
    {
        $this->fullSession($this->user());
        $this->post('/api/imports', [
            'file' => UploadedFile::fake()->createWithContent('fake.xlsx', '<?php echo "x";'),
        ])->assertStatus(422);
        $this->post('/api/imports', [
            'file' => UploadedFile::fake()->create('large.xlsx', 5121),
        ])->assertStatus(422);
        $this->assertDatabaseCount('import_batches', 0);
    }
}
