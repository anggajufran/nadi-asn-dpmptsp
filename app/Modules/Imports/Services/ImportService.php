<?php
namespace App\Modules\Imports\Services;
use App\Modules\Imports\Models\{ImportBatch, ImportRow};
use App\Modules\Personnel\Models\Employee;
use App\Modules\Personnel\Services\EmployeeProjection;
use App\Modules\Governance\Services\ChangeService;
use App\Modules\IdentityAccess\Models\User;
use App\Shared\{Audit, CanonicalJson};
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{DB, Storage};
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
final class ImportService
{
    public const FIELDS = [
        'sourceOrder',
        'name',
        'nip',
        'rankName',
        'rankTmt',
        'position',
        'effectiveDate',
        'serviceYears',
        'serviceMonths',
        'institution',
        'graduationDate',
        'education',
        'birthPlace',
        'birthDate',
        'ageAtReference',
        'gender',
        'type',
        'positionType',
        'unit',
        'grade',
        'status',
        'driveUrl',
        'contractStart',
        'contractEnd',
    ];
    public function authorize(User $user, ImportBatch $batch, bool $write = false): void
    {
        abort_unless(
            $user->canWrite() &&
                ($batch->maker_id === $user->id || (!$write && $user->role === 'SUPER_ADMIN')),
            403,
            'Batch ini tidak dapat diakses oleh akun Anda.',
        );
    }
    public function upload(User $user, UploadedFile $file): ImportBatch
    {
        abort_unless($user->canWrite(), 403);
        abort_unless(
            strtolower($file->getClientOriginalExtension()) === 'xlsx' &&
                $file->getSize() > 0 &&
                $file->getSize() <= 5 * 1024 * 1024,
            422,
            'Gunakan berkas XLSX maksimal 5 MB.',
        );
        app(UploadScanner::class)->assertClean($file->getRealPath());
        $zip = new \ZipArchive();
        abort_unless($zip->open($file->getRealPath()) === true, 422, 'Berkas XLSX tidak valid.');
        $expanded = 0;
        try {
            abort_if($zip->numFiles > 2000, 422, 'Struktur berkas terlalu besar.');
            foreach (['[Content_Types].xml', 'xl/workbook.xml', '_rels/.rels'] as $required) {
                abort_if($zip->locateName($required) === false, 422, 'Struktur XLSX tidak valid.');
            }
            $seen = [];
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $entry = $zip->statIndex($i);
                $name = strtolower(str_replace('\\', '/', $entry['name']));
                abort_if(
                    isset($seen[$name]) ||
                        str_starts_with($name, '/') ||
                        in_array('..', explode('/', $name), true) ||
                        ($entry['encryption_method'] ?? 0) !== 0,
                    422,
                    'Struktur arsip tidak diizinkan.',
                );
                $seen[$name] = true;
                $expanded += $entry['size'];
                abort_if(
                    str_contains($name, 'externallinks/') ||
                        str_contains($name, 'vbaproject') ||
                        str_contains($name, 'embeddings/'),
                    422,
                    'Tautan eksternal dan makro tidak didukung.',
                );
                abort_if(
                    $expanded > 25 * 1024 * 1024,
                    422,
                    'Ukuran terurai berkas melebihi 25 MB.',
                );
            }
            // Read XML only after every uncompressed size has been bounded.
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $name = strtolower($zip->getNameIndex($i));
                if (!str_ends_with($name, '.xml') && !str_ends_with($name, '.rels')) {
                    continue;
                }
                $xml = $zip->getFromIndex($i);
                abort_if(
                    $xml === false ||
                        preg_match(
                            '/<!DOCTYPE|<!ENTITY|TargetMode\s*=\s*[\x22\x27]External|macroEnabled/i',
                            $xml,
                        ),
                    422,
                    'Konten aktif atau referensi eksternal tidak diizinkan.',
                );
            }
        } finally {
            $zip->close();
        }
        $reader = new Xlsx();
        try {
            $info = $reader->listWorksheetInfo($file->getRealPath());
        } catch (\Throwable $e) {
            abort(422, 'Berkas XLSX rusak atau tidak dapat dibaca.');
        }
        abort_unless(count($info) === 1, 422, 'Gunakan satu worksheet data pada berkas import.');
        abort_if(
            $info[0]['totalRows'] > 1001 || $info[0]['totalColumns'] > 50,
            422,
            'Maksimal 1.000 baris dan 50 kolom.',
        );
        $reader->setReadDataOnly(false);
        try {
            $sheet = $reader->load($file->getRealPath());
        } catch (\Throwable $e) {
            abort(422, 'Berkas XLSX rusak atau tidak dapat dibaca.');
        }
        $worksheet = $sheet->getActiveSheet();
        $headers = [];
        $sources = [];
        try {
            for ($column = 1; $column <= $info[0]['totalColumns']; $column++) {
                $cell = $worksheet->getCell([$column, 1]);
                abort_if(
                    $cell->getDataType() === DataType::TYPE_FORMULA,
                    422,
                    'Header formula tidak diizinkan.',
                );
                $label = trim((string) $cell->getValue());
                abort_if(
                    $label === '' || in_array($label, $headers, true),
                    422,
                    'Header kosong atau duplikat.',
                );
                $headers[] = $label;
            }
            for ($line = 2; $line <= $info[0]['totalRows']; $line++) {
                $cells = [];
                foreach ($headers as $index => $label) {
                    $cell = $worksheet->getCell([$index + 1, $line]);
                    $value = $cell->getValue();
                    if (
                        is_numeric($value) &&
                        \PhpOffice\PhpSpreadsheet\Shared\Date::isDateTime($cell)
                    ) {
                        $value = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject(
                            $value,
                        )->format('Y-m-d');
                    }
                    $cells[$label] = [
                        'value' => is_scalar($value) ? trim((string) $value) : '',
                        'type' => $cell->getDataType(),
                    ];
                }
                if (count(array_filter($cells, fn($cell) => $cell['value'] !== ''))) {
                    $sources[$line] = $cells;
                }
            }
            abort_if(!$sources, 422, 'Tidak ada baris data pada berkas.');
        } finally {
            $sheet->disconnectWorksheets();
        }
        $checksum = hash_file('sha256', $file->getRealPath());
        $duplicate = ImportBatch::where('checksum', $checksum)->latest()->first();
        $path = $file->store('imports', 'local');
        try {
            return DB::transaction(function () use (
                $user,
                $file,
                $headers,
                $sources,
                $checksum,
                $duplicate,
                $path,
            ) {
                $batch = ImportBatch::create([
                    'maker_id' => $user->id,
                    'filename' => mb_substr(basename($file->getClientOriginalName()), 0, 200),
                    'file_path' => $path,
                    'checksum' => $checksum,
                    'status' => 'UPLOADED',
                    'headers' => $headers,
                    'row_count' => count($sources),
                    'duplicate_of' => $duplicate?->id,
                    'expires_at' => now()->addDays(config('nadi.import_retention_days')),
                ]);
                foreach ($sources as $line => $source) {
                    ImportRow::create([
                        'batch_id' => $batch->id,
                        'row_number' => $line,
                        'status' => 'STAGED',
                        'source' => $source,
                    ]);
                }
                Audit::append($user, 'Mengunggah berkas import', $batch->filename, [
                    'batch_id' => $batch->id,
                    'checksum' => $checksum,
                ]);
                return $batch;
            });
        } catch (\Throwable $e) {
            Storage::disk('local')->delete($path);
            throw $e;
        }
    }
    public function validate(ImportBatch $batch): void
    {
        DB::transaction(function () use ($batch) {
            $batch = ImportBatch::lockForUpdate()->findOrFail($batch->id);
            if ($batch->status !== 'VALIDATING') {
                return;
            }
            $maker = User::findOrFail($batch->maker_id);
            $rows = $batch->rows()->orderBy('row_number')->get();
            $nipCounts = [];
            foreach ($rows as $row) {
                $nip = $row->source[$batch->mapping['nip']]['value'] ?? '';
                $nipCounts[$nip] = ($nipCounts[$nip] ?? 0) + 1;
            }
            foreach ($rows as $row) {
                $mapped = [];
                $errors = [];
                foreach ($batch->mapping as $key => $header) {
                    if (!$header) {
                        continue;
                    }
                    $cell = $row->source[$header] ?? ['value' => '', 'type' => 'null'];
                    if ($cell['type'] === DataType::TYPE_FORMULA) {
                        $errors[] = 'Formula tidak diterima. Gunakan nilai teks.';
                    }
                    if ($key === 'nip' && $cell['type'] === DataType::TYPE_NUMERIC) {
                        $errors[] = 'NIP harus disimpan sebagai teks, bukan angka Excel.';
                    }
                    $mapped[$key] = $cell['value'];
                }
                $nip = $mapped['nip'] ?? '';
                if (!preg_match('/^\d{18}$/', $nip)) {
                    $errors[] = 'NIP harus terdiri dari 18 digit.';
                }
                if (($nipCounts[$nip] ?? 0) > 1) {
                    $errors[] = 'NIP duplikat dalam berkas.';
                }
                $employee = preg_match('/^\d{18}$/', $nip)
                    ? Employee::with('assignments.unitReference')->where('nip', $nip)->first()
                    : null;
                $before = $employee ? (new EmployeeProjection())->project($employee, $maker) : [];
                $changes = $employee ? [] : ['status' => 'Aktif'];
                foreach ($mapped as $key => $value) {
                    if ($key === 'ageAtReference') {
                        continue;
                    }
                    if ($value !== '' && $value !== (string) ($before[$key] ?? '')) {
                        $changes[$key] = in_array($key, [
                            'sourceOrder',
                            'serviceYears',
                            'serviceMonths',
                            'ageAtReference',
                        ])
                            ? (int) $value
                            : $value;
                    }
                }
                if (!$errors) {
                    try {
                        (new ChangeService())->validateCandidate(
                            array_merge($before ?? [], $changes),
                            $employee?->id,
                        );
                    } catch (ValidationException $e) {
                        $errors = array_merge($errors, collect($e->errors())->flatten()->all());
                    } catch (HttpExceptionInterface $e) {
                        $errors[] = $e->getMessage();
                    }
                }
                $row->diff = [
                    'employeeId' => $employee?->id,
                    'revision' => $employee?->revision,
                    'before' => $before,
                    'changes' => $changes,
                    'mapped' => $mapped,
                ];
                $row->errors = $errors;
                $row->status = $errors ? 'INVALID' : ($changes ? 'VALID' : 'UNCHANGED');
                $row->save();
            }
            $batch->preview_hash = CanonicalJson::hash(
                $rows
                    ->map(fn($r) => ['id' => $r->id, 'status' => $r->status, 'diff' => $r->diff])
                    ->all(),
            );
            $batch->status = 'PREVIEW';
            $batch->error_message = null;
            $batch->save();
            Audit::append($maker, 'Memvalidasi import', $batch->filename, [
                'batch_id' => $batch->id,
            ]);
        });
    }
    public function confirm(User $user, ImportBatch $batch, array $input): int
    {
        $this->authorize($user, $batch, true);
        return DB::transaction(function () use ($user, $batch, $input) {
            $batch = ImportBatch::lockForUpdate()->findOrFail($batch->id);
            if (
                $batch->status === 'CONFIRMED' &&
                hash_equals($batch->preview_hash, $input['previewHash'])
            ) {
                return $batch->rows()->whereNotNull('change_request_id')->count();
            }
            abort_unless(
                $batch->status === 'PREVIEW' &&
                    hash_equals($batch->preview_hash ?? '', $input['previewHash']),
                409,
                'Preview berubah. Jalankan validasi kembali.',
            );
            abort_if(
                $batch->duplicate_of && !($input['confirmDuplicate'] ?? false),
                409,
                'Berkas identik pernah diunggah. Konfirmasi pengulangan secara eksplisit.',
            );
            $rows = $batch->rows()->where('status', 'VALID')->lockForUpdate()->get();
            abort_if($rows->isEmpty(), 422, 'Tidak ada baris valid yang berubah.');
            foreach ($rows as $row) {
                $diff = $row->diff;
                $proposal = (new ChangeService())->submit($user, [
                    'employeeId' => $diff['employeeId'],
                    'revision' => $diff['revision'],
                    'changes' => $diff['changes'],
                    'reason' => $input['reason'] . ' | Batch ' . $batch->id,
                ]);
                $row->change_request_id = $proposal->id;
                $row->status = 'SUBMITTED';
                $row->save();
            }
            $batch->status = 'CONFIRMED';
            $batch->confirmed_at = now();
            $batch->save();
            Audit::append($user, 'Mengajukan batch import', $batch->filename, [
                'batch_id' => $batch->id,
                'count' => $rows->count(),
            ]);
            return $rows->count();
        });
    }
}
