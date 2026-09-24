<?php
namespace App\Modules\Imports\Jobs;
use App\Modules\Imports\Models\ImportBatch;
use App\Modules\Imports\Services\ImportService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
class ValidateImport implements ShouldQueue
{
    use Queueable;
    public int $tries = 3;
    public int $timeout = 90;
    public function __construct(public readonly string $batchId) {}
    public function handle(ImportService $service): void
    {
        $batch = ImportBatch::find($this->batchId);
        if ($batch) {
            $service->validate($batch);
        }
    }
    public function failed(?\Throwable $e): void
    {
        ImportBatch::where('id', $this->batchId)
            ->where('status', 'VALIDATING')
            ->update([
                'status' => 'FAILED',
                'error_message' => 'Validasi berkas gagal. Coba kembali atau hubungi pengelola.',
            ]);
    }
}
