<?php
namespace App\Modules\SnapshotsReports\Http;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB, Storage};
use App\Modules\Personnel\Models\Employee;
use App\Modules\Personnel\Services\EmployeeProjection;
use App\Modules\Analytics\Services\PublicReleaseReader;
use App\Modules\SnapshotsReports\Models\ExportJob;
use App\Modules\SnapshotsReports\Jobs\RenderExport;
use App\Shared\{Audit, CanonicalJson};
class ExportController
{
    private function authorize(Request $r, ExportJob $export): void
    {
        abort_unless(
            $export->owner_id === $r->user()->id &&
                $r->user()->role === 'SUPER_ADMIN' &&
                $r->user()->security_version === $export->parameters['securityVersion'],
            403,
            'Laporan tidak tersedia untuk akun ini.',
        );
    }
    public function index(Request $r)
    {
        abort_unless($r->user()->role === 'SUPER_ADMIN', 403);
        return response()->json(
            ExportJob::where('owner_id', $r->user()->id)
                ->where('parameters->securityVersion', $r->user()->security_version)
                ->latest()
                ->limit(50)
                ->get()
                ->map(
                    fn($job) => [
                        'id' => $job->id,
                        'kind' => $job->kind,
                        'format' => $job->format,
                        'status' => $job->expires_at?->isPast() ? 'EXPIRED' : $job->status,
                        'createdAt' => $job->created_at->toIso8601String(),
                        'expiresAt' => $job->expires_at?->toIso8601String(),
                    ],
                ),
        );
    }
    public function store(Request $r, EmployeeProjection $projection, PublicReleaseReader $public)
    {
        abort_unless($r->user()->role === 'SUPER_ADMIN', 403);
        $v = $r->validate([
            'kind' => 'required|in:employees,statistics',
            'unit' => 'nullable|string|max:150',
            'type' => 'nullable|in:PNS,PPPK Penuh Waktu,PPPK Paruh Waktu',
            'positionType' => 'nullable|in:Struktural,Fungsional,Pelaksana',
            'status' => 'nullable|in:Aktif,Pensiun,Mutasi,Meninggal',
            'search' => 'nullable|string|max:150',
            'quality' => 'nullable|boolean',
            'allStatuses' => 'nullable|boolean',
        ]);
        $parameters = [
            'unit' => $v['unit'] ?? '',
            'actor' => $r->user()->name,
            'securityVersion' => $r->user()->security_version,
            'filters' => $v,
        ];
        if ($v['kind'] === 'employees') {
            $rows = Employee::inDukOrder()
                ->with('assignments.unitReference')
                ->get()
                ->map(fn($e) => $projection->project($e, $r->user()))
                ->filter();
            foreach (['unit', 'type', 'positionType', 'status'] as $field) {
                $value =
                    $field === 'status'
                        ? (!empty($v['allStatuses'])
                            ? ''
                            : $v[$field] ?? 'Aktif')
                        : $v[$field] ?? '';
                if ($value !== '') {
                    $rows = $rows->where($field, $value);
                }
            }
            if (!empty($v['search'])) {
                $rows = $rows->filter(
                    fn($row) => str_contains(
                        mb_strtolower($row['name']),
                        mb_strtolower($v['search']),
                    ) || str_contains($row['nip'], $v['search']),
                );
            }
            if (!empty($v['quality'])) {
                $rows = $rows->filter(fn($row) => $row['completeness'] < 100);
            }
            $parameters['rows'] = $rows
                ->map(
                    fn($row) => array_intersect_key(
                        $row,
                        array_flip([
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
                        ]),
                    ),
                )
                ->values()
                ->all();
            $parameters['contentHash'] = CanonicalJson::hash($parameters['rows']);
        } else {
            $parameters['statistics'] = $public->read(['unit' => $v['unit'] ?? '']);
        }
        $job = DB::transaction(function () use ($r, $v, $parameters) {
            $job = ExportJob::create([
                'owner_id' => $r->user()->id,
                'status' => 'QUEUED',
                'format' => $v['kind'] === 'employees' ? 'xlsx' : 'pdf',
                'kind' => $v['kind'],
                'parameters' => $parameters,
            ]);
            Audit::append($r->user(), 'Meminta laporan operasional', $v['kind'], [
                'export_id' => $job->id,
            ]);
            RenderExport::dispatch($job->id)->afterCommit();
            return $job;
        });
        return response()->json(['id' => $job->id, 'status' => $job->status], 202);
    }
    public function show(Request $r, ExportJob $export)
    {
        $this->authorize($r, $export);
        return response()->json([
            'id' => $export->id,
            'status' => $export->status,
            'expiresAt' => $export->expires_at?->toIso8601String(),
        ]);
    }
    public function download(Request $r, ExportJob $export)
    {
        $this->authorize($r, $export);
        abort_unless(
            $export->status === 'READY' &&
                $export->expires_at?->isFuture() &&
                Storage::disk('local')->exists($export->file_path),
            410,
            'Berkas belum siap atau sudah kedaluwarsa.',
        );
        Audit::append($r->user(), 'Mengunduh laporan', $export->kind, ['export_id' => $export->id]);
        return Storage::disk('local')->download(
            $export->file_path,
            'NADI-ASN-Operasional-' . $export->id . '.' . $export->format,
            ['Cache-Control' => 'no-store', 'X-Robots-Tag' => 'noindex'],
        );
    }
}
