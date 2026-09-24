<?php
namespace App\Modules\Radar\Http;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Modules\Personnel\Models\Employee;
use App\Modules\Personnel\Services\EmployeeProjection;
use App\Modules\Radar\Services\RadarCalculator;
use App\Shared\Audit;
class RadarController
{
    private function kind(string $kind): void
    {
        abort_unless(in_array($kind, ['kp', 'kgb']), 404);
    }
    public function index(
        Request $r,
        string $kind,
        EmployeeProjection $projection,
        RadarCalculator $calc,
    ) {
        $this->kind($kind);
        $records = DB::table('radar_records')->where('kind', $kind)->get()->keyBy('employee_id');
        $rows = Employee::inDukOrder()
            ->with('assignments.unitReference')
            ->get()
            ->map(function ($e) use ($r, $projection, $calc, $records, $kind) {
                $p = $projection->project($e, $r->user());
                if (!$p || $p['type'] !== 'PNS' || $p['status'] !== 'Aktif') {
                    return null;
                }
                $record = $records->get($e->id);
                $d = $record ? json_decode($record->data, true) : [];
                if ($record) {
                    $d['employeeRevision'] = $record->employee_revision;
                }
                return [
                    'employee' => array_intersect_key(
                        $p,
                        array_flip([
                            'id',
                            'revision',
                            'name',
                            'nip',
                            'unit',
                            'type',
                            'status',
                            'grade',
                            'position',
                            'positionType',
                            'rankTmt',
                            'education',
                        ]),
                    ),
                    'data' => $d,
                    'revision' => $record?->revision ?? 0,
                    'result' => $calc->calculate($p, $kind, $d),
                ];
            })
            ->filter()
            ->values();
        return response()->json(['rows' => $rows, 'version' => RadarCalculator::VERSION]);
    }
    public function save(
        Request $r,
        string $kind,
        string $id,
        EmployeeProjection $projection,
        RadarCalculator $calc,
    ) {
        $this->kind($kind);
        abort_unless($r->user()->canWrite(), 403);
        $bool = 'nullable|boolean';
        $text = 'nullable|string|max:1500';
        $date = 'nullable|date_format:Y-m-d';
        $v = $r->validate([
            'revision' => 'required|integer|min:0',
            'employeeRevision' => 'required|integer|min:1',
            'data' => 'required|array',
            'data.lastKgbDate' => $date . '|before_or_equal:today',
            'data.lastKgbTmt' => $date . '|before_or_equal:today',
            'data.targetDate' => $date,
            'data.credit' => 'nullable|numeric|min:0|max:1000000',
            'data.cumulativeCredit' => 'nullable|numeric|min:0|max:1000000',
            'data.performance1' => 'nullable|in:Buruk,Kurang,Cukup,Baik,Sangat Baik',
            'data.performance2' => 'nullable|in:Buruk,Kurang,Cukup,Baik,Sangat Baik',
            'data.performanceYear1' => 'nullable|integer|min:1900|max:2200',
            'data.performanceYear2' => 'nullable|integer|min:1900|max:2200',
            'data.documentsVerified' => $bool,
            'data.educationRecognized' => $bool,
            'data.examVerified' => $bool,
            'data.functionalVerified' => $bool,
            'data.managerialVerified' => $bool,
            'data.documentReference' => $text,
            'data.appointmentReference' => $text,
            'data.pakReference' => $text,
            'data.scheduleReference' => $text,
            'data.deadline' => $date,
            'data.correctionDeadline' => $date,
            'data.process' =>
                'required|in:Belum diusulkan,Diusulkan,Perbaikan,Keputusan terbit,Ditolak',
            'data.processNote' => $text,
            'data.reason' => 'required|string|min:5|max:1500',
        ]);
        return DB::transaction(function () use ($r, $kind, $id, $projection, $calc, $v) {
            $e = Employee::lockForUpdate()->findOrFail($id);
            $p = $projection->project($e->load('assignments.unitReference'), $r->user());
            abort_unless(
                $p && $p['type'] === 'PNS' && $p['status'] === 'Aktif',
                422,
                'Radar hanya untuk PNS aktif.',
            );
            abort_unless(
                $e->revision === $v['employeeRevision'],
                409,
                'Data Master berubah. Muat ulang sebelum menyimpan.',
            );
            $record = DB::table('radar_records')
                ->where('employee_id', $id)
                ->where('kind', $kind)
                ->lockForUpdate()
                ->first();
            abort_unless(
                ($record?->revision ?? 0) === $v['revision'],
                409,
                'Data radar telah diubah pengelola lain. Muat ulang terlebih dahulu.',
            );
            $allowed = [
                'lastKgbDate',
                'lastKgbTmt',
                'targetDate',
                'credit',
                'cumulativeCredit',
                'performance1',
                'performance2',
                'performanceYear1',
                'performanceYear2',
                'documentsVerified',
                'educationRecognized',
                'examVerified',
                'functionalVerified',
                'managerialVerified',
                'documentReference',
                'appointmentReference',
                'pakReference',
                'scheduleReference',
                'deadline',
                'correctionDeadline',
                'process',
                'processNote',
                'reason',
            ];
            $d = array_intersect_key($v['data'], array_flip($allowed));
            if ($kind === 'kp' && !empty($d['targetDate'])) {
                abort_unless(
                    substr($d['targetDate'], 8) === '01',
                    422,
                    'Periode KP harus tanggal 1.',
                );
            }
            if (!empty($d['deadline'])) {
                abort_unless(
                    !empty($d['targetDate']) &&
                        !empty($d['scheduleReference']) &&
                        $d['deadline'] < $d['targetDate'],
                    422,
                    'Tenggat khusus memerlukan TMT target, surat acuan, dan tanggal sebelum TMT target.',
                );
            }
            if (($d['process'] ?? '') === 'Perbaikan') {
                abort_unless(
                    !empty($d['correctionDeadline']) && !empty($d['processNote']),
                    422,
                    'Catatan dan tenggat surat perbaikan wajib diisi.',
                );
            }
            if (in_array($d['process'], ['Diusulkan', 'Keputusan terbit', 'Ditolak'])) {
                abort_unless(
                    !empty($d['processNote']),
                    422,
                    'Isi nomor/tanggal usulan atau keputusan pada catatan proses.',
                );
            }
            $before = $record ? json_decode($record->data, true) : [];
            $d['employeeRevision'] = $e->revision;
            // The auto period is frozen when reviewed, so verified performance never silently shifts to another cycle.
            $result = $calc->calculate($p, $kind, $d);
            if ($kind === 'kp' && empty($d['targetDate']) && $result['targetDate']) {
                $d['targetDate'] = $result['targetDate'];
            }
            DB::table('radar_records')->updateOrInsert(
                ['employee_id' => $id, 'kind' => $kind],
                [
                    'data' => json_encode($d),
                    'revision' => $v['revision'] + 1,
                    'employee_revision' => $e->revision,
                    'updated_by' => $r->user()->id,
                    'updated_at' => now(),
                    'created_at' => $record?->created_at ?? now(),
                ],
            );
            Audit::append($r->user(), 'Memperbarui ' . strtoupper($kind) . ' Radar', $p['name'], [
                'employee_id' => $id,
                'kind' => $kind,
                'before' => $before,
                'after' => $d,
                'rules_version' => RadarCalculator::VERSION,
            ]);
            return response()->json([
                'ok' => true,
                'revision' => $v['revision'] + 1,
                'result' => $result,
            ]);
        });
    }
}
