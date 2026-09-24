<?php
namespace App\Modules\Imports\Http;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Modules\Imports\Models\ImportBatch;
use App\Modules\Imports\Services\ImportService;
use App\Modules\Imports\Jobs\ValidateImport;
class ImportController
{
    private function dto(ImportBatch $batch, bool $withRows = false): array
    {
        $dto = [
            'id' => $batch->id,
            'filename' => $batch->filename,
            'count' => $batch->row_count,
            'status' => $batch->status,
            'createdAt' => $batch->created_at->toIso8601String(),
            'headers' => $batch->headers,
            'mapping' => $batch->mapping,
            'previewHash' => $batch->preview_hash,
            'duplicateOf' => $batch->duplicate_of,
            'error' => $batch->error_message,
        ];
        if ($withRows) {
            $dto['rows'] = $batch
                ->rows()
                ->orderBy('row_number')
                ->get()
                ->map(
                    fn($r) => [
                        'id' => $r->id,
                        'line' => $r->row_number,
                        'status' => $r->status,
                        'errors' => $r->errors ?? [],
                        'changes' => $r->diff['changes'] ?? [],
                        'mapped' => $r->diff['mapped'] ?? [],
                        'existing' => !empty($r->diff['employeeId'])
                            ? $r->diff['before'] ?? null
                            : null,
                    ],
                )
                ->all();
        }
        return $dto;
    }
    public function index(Request $r)
    {
        abort_unless($r->user()->canWrite(), 403);
        return response()->json(
            ImportBatch::where('maker_id', $r->user()->id)
                ->latest()
                ->limit(100)
                ->get()
                ->map(fn($b) => $this->dto($b)),
        );
    }
    public function store(Request $r, ImportService $service)
    {
        $r->validate(['file' => 'required|file|extensions:xlsx|max:5120']);
        return response()->json($this->dto($service->upload($r->user(), $r->file('file'))), 201);
    }
    public function show(Request $r, ImportBatch $batch, ImportService $service)
    {
        $service->authorize($r->user(), $batch);
        return response()->json($this->dto($batch, true));
    }
    public function validate(Request $r, ImportBatch $batch, ImportService $service)
    {
        $service->authorize($r->user(), $batch, true);
        $v = $r->validate(['mapping' => 'required|array', 'mapping.nip' => 'required|string']);
        abort_if(
            array_diff(array_keys($v['mapping']), ImportService::FIELDS),
            422,
            'Field mapping tidak diizinkan.',
        );
        $headers = array_filter($v['mapping']);
        abort_if(
            count(array_unique($headers)) !== count($headers) ||
                array_diff($headers, $batch->headers),
            422,
            'Kolom mapping tidak valid atau dipetakan lebih dari sekali.',
        );
        DB::transaction(function () use ($batch, $v) {
            $b = ImportBatch::lockForUpdate()->findOrFail($batch->id);
            abort_if(
                in_array($b->status, ['CONFIRMED', 'VALIDATING']),
                409,
                'Batch sedang diproses atau sudah diajukan.',
            );
            $b->mapping = $v['mapping'];
            $b->status = 'VALIDATING';
            $b->save();
            ValidateImport::dispatch($b->id)->afterCommit();
        });
        return response()->json(['status' => 'VALIDATING'], 202);
    }
    public function confirm(Request $r, ImportBatch $batch, ImportService $service)
    {
        $v = $r->validate([
            'previewHash' => 'required|string|size:64',
            'reason' => 'required|string|min:5|max:900',
            'confirmDuplicate' => 'boolean',
        ]);
        return response()->json([
            'count' => $service->confirm($r->user(), $batch, $v),
            'status' => 'CONFIRMED',
        ]);
    }
}
