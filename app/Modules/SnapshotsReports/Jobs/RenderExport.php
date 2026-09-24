<?php
namespace App\Modules\SnapshotsReports\Jobs;
use App\Modules\SnapshotsReports\Models\ExportJob;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\{Spreadsheet, Writer\Xlsx, Cell\DataType};
use Dompdf\{Dompdf, Options};
class RenderExport implements ShouldQueue
{
    use Queueable;
    public int $tries = 3;
    public int $timeout = 90;
    public function __construct(public readonly string $exportId) {}
    public function handle(): void
    {
        $job = ExportJob::find($this->exportId);
        if (!$job || $job->status === 'READY') {
            return;
        }
        $path = 'exports/' . $job->id . '.' . $job->format;
        $absolute = Storage::disk('local')->path($path);
        Storage::disk('local')->makeDirectory('exports');
        if ($job->format === 'xlsx') {
            $book = new Spreadsheet();
            $sheet = $book->getActiveSheet();
            $sheet->setTitle('Data Pegawai');
            $schema = json_decode(
                file_get_contents(resource_path('js/domain/personnel-schema.json')),
                true,
            );
            if ($job->kind === 'duk') {
                $extra = json_decode(
                    file_get_contents(resource_path('js/domain/duk-schema.json')),
                    true,
                );
                $schema = [
                    ['key' => 'dukRank', 'label' => 'No. DUK'],
                    ...$schema,
                    ['key' => 'mkgYears', 'label' => 'MKG tahun'],
                    ['key' => 'mkgMonths', 'label' => 'MKG bulan'],
                    ['key' => 'mkgDays', 'label' => 'MKG hari'],
                    ...$extra,
                    ['key' => 'dukPositionResolved', 'label' => 'Jenjang jabatan digunakan'],
                    ['key' => 'dukReason', 'label' => 'Pembeda dari urutan sebelumnya'],
                ];
                $sheet->setTitle('DUK Resmi');
            }
            $lastColumn = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(
                count($schema),
            );
            $nipColumn = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(
                array_search('nip', array_column($schema, 'key'), true) + 1,
            );
            $sheet->fromArray(array_column($schema, 'label'), null, 'A1');
            foreach ($job->parameters['rows'] as $i => $row) {
                foreach ($schema as $c => $field) {
                    $sheet->setCellValueExplicit(
                        [$c + 1, $i + 2],
                        (string) ($row[$field['key']] ?? ''),
                        DataType::TYPE_STRING,
                    );
                }
            }
            $sheet
                ->getStyle('A1:' . $lastColumn . '1')
                ->getFont()
                ->setBold(true)
                ->getColor()
                ->setARGB('FFFFFFFF');
            $sheet
                ->getStyle('A1:' . $lastColumn . '1')
                ->getFill()
                ->setFillType('solid')
                ->getStartColor()
                ->setARGB('FF28579A');
            foreach (range(1, count($schema)) as $column) {
                $c = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($column);
                $sheet
                    ->getColumnDimension($c)
                    ->setWidth(
                        in_array($schema[$column - 1]['key'], [
                            'name',
                            'position',
                            'institution',
                            'unit',
                        ])
                            ? 45
                            : 24,
                    );
            }
            $sheet
                ->getStyle(
                    $nipColumn . '2:' . $nipColumn . max(2, count($job->parameters['rows']) + 1),
                )
                ->getNumberFormat()
                ->setFormatCode('@');
            $sheet->freezePane(
                \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(
                    array_search('nip', array_column($schema, 'key'), true) + 2,
                ) . '2',
            );
            $sheet->setAutoFilter(
                'A1:' . $lastColumn . max(1, count($job->parameters['rows']) + 1),
            );
            $book
                ->getProperties()
                ->setCreator($job->parameters['actor'])
                ->setTitle('Direktori Pegawai NADI ASN')
                ->setDescription(
                    'ID ' . $job->id . ' | Masa kerja dan usia dihitung saat laporan diminta',
                );
            if ($job->kind === 'duk') {
                $meta = $book->createSheet();
                $meta->setTitle('Pengesahan');
                $meta->fromArray([
                    ['DUK NADI ASN DPMPTSP Prov. Sulawesi Tengah', ''],
                    ...array_map(
                        fn($k, $v) => [$k, (string) $v],
                        array_keys($job->parameters['publication']),
                        array_values($job->parameters['publication']),
                    ),
                    ['Jumlah PNS', count($job->parameters['rows'])],
                    ['MKG', 'Dihitung dari TMT golongan sampai tanggal DUK'],
                    [
                        'Kebijakan',
                        'Hierarki instansi 15 September 2026; Kepala Dinas lalu Sekretaris; pangkat, jabatan, MKG, diklat, pendidikan, usia.',
                    ],
                ]);
                $meta->getColumnDimension('A')->setWidth(25);
                $meta->getColumnDimension('B')->setWidth(100);
                $meta->getStyle('B1:B12')->getAlignment()->setWrapText(true);
                $book->setActiveSheetIndex(0);
                $book
                    ->getProperties()
                    ->setTitle('DUK Resmi ' . $job->parameters['publication']['version'])
                    ->setDescription(
                        'Salinan tetap DUK yang disahkan dalam NADI ASN; hash pada sheet Pengesahan.',
                    );
            }
            (new Xlsx($book))->save($absolute);
            $book->disconnectWorksheets();
        } else {
            $options = new Options();
            $options->set('isRemoteEnabled', false);
            $options->set('isPhpEnabled', false);
            $options->set('defaultFont', 'DejaVu Sans');
            $pdf = new Dompdf($options);
            $pdf->loadHtml(
                view('reports.statistics', [
                    'stats' => $job->parameters['statistics'],
                    'id' => $job->id,
                ])->render(),
            );
            $pdf->setPaper('A4');
            $pdf->render();
            Storage::disk('local')->put($path, $pdf->output());
        }
        $job->status = 'READY';
        $job->file_path = $path;
        $job->expires_at = now()->addDays(7);
        $job->save();
    }
    public function failed(?\Throwable $e): void
    {
        ExportJob::where('id', $this->exportId)->update(['status' => 'FAILED']);
    }
}
