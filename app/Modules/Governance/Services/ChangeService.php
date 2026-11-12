<?php
namespace App\Modules\Governance\Services;
use App\Modules\Governance\Models\ChangeRequest;
use App\Modules\Personnel\Models\{Employee, EmployeeAssignment};
use App\Modules\Personnel\Services\EmployeeProjection;
use App\Modules\IdentityAccess\Models\User;
use App\Shared\{Audit, CanonicalJson};
use Illuminate\Support\Facades\{DB, Validator};
use Illuminate\Support\Str;
final class ChangeService
{
    public const FIELD_MAP = [
        'name' => 'name',
        'nip' => 'nip',
        'gender' => 'gender',
        'education' => 'education',
        'birthPlace' => 'birth_place',
        'birthDate' => 'birth_date',
        'driveUrl' => 'drive_url',
        'sourceOrder' => 'source_order',
        'institution' => 'institution',
        'graduationDate' => 'graduation_date',
    ];
    public const ASSIGNMENT_MAP = [
        'dukPositionLevel' => 'duk_position_level',
        'trainingLevel' => 'training_level',
        'trainingDate' => 'training_date',
        'type' => 'type',
        'unit' => 'unit',
        'position' => 'position',
        'positionType' => 'position_type',
        'grade' => 'grade',
        'status' => 'status',
        'effectiveDate' => 'position_tmt',
        'contractStart' => 'contract_start',
        'contractEnd' => 'contract_end',
        'rankName' => 'rank_name',
        'rankTmt' => 'rank_tmt',
        'serviceYears' => 'service_years',
        'serviceMonths' => 'service_months',
        'serviceReference' => 'service_reference',
    ];
    public function validateCandidate(array $data, ?string $except = null): array
    {
        $v = Validator::make($data, [
            'dukPositionLevel' => [
                'nullable',
                \Illuminate\Validation\Rule::in(
                    array_keys(\App\Modules\Duk\Services\DukRanking::POSITIONS),
                ),
            ],
            'trainingLevel' => [
                'nullable',
                \Illuminate\Validation\Rule::in(
                    array_keys(\App\Modules\Duk\Services\DukRanking::TRAINING),
                ),
            ],
            'trainingDate' => 'nullable|date_format:Y-m-d|before_or_equal:today',
            'name' => 'required|string|max:200',
            'nip' => [
                'required',
                'regex:/^\d{18}$/',
                'unique:employees,nip' . ($except ? ',' . $except . ',id' : ''),
            ],
            'gender' => 'required|in:Perempuan,Laki-laki',
            'education' => 'nullable|in:SMA,SMK,SMA / SMK,SD,SMP,D1,D2,D3,D4,S1,S2,S3',
            'type' => 'required|in:PNS,PPPK Penuh Waktu,PPPK Paruh Waktu',
            'unit' => 'required|string|max:150',
            'position' => 'required|string|max:200',
            'positionType' => 'required|in:Struktural,Fungsional,Pelaksana',
            'grade' => 'nullable|string|max:20',
            'status' => 'required|in:Aktif,Pensiun,Mutasi,Meninggal,Mengundurkan Diri',
            'effectiveDate' => 'nullable|date_format:Y-m-d',
            'birthPlace' => 'nullable|string|max:100',
            'birthDate' => 'nullable|date_format:Y-m-d|before_or_equal:today',
            'driveUrl' => [
                'nullable',
                'url:https',
                'regex:~^https://drive\.google\.com/drive/folders/[A-Za-z0-9_-]+(?:\?.*)?$~',
            ],
            'sourceOrder' => 'nullable|integer|min:1',
            'institution' => 'nullable|string|max:300',
            'graduationDate' => 'nullable|date_format:Y-m-d',
            'ageAtReference' => 'nullable|integer|min:0|max:120',
            'rankName' => 'nullable|string|max:200',
            'rankTmt' => 'nullable|date_format:Y-m-d',
            'serviceYears' => 'nullable|integer|min:0|max:70',
            'serviceMonths' => 'nullable|integer|min:0|max:99',
            'serviceReference' => 'nullable|date_format:Y-m',
            'contractStart' => 'nullable|date_format:Y-m-d',
            'contractEnd' => 'nullable|date_format:Y-m-d|after_or_equal:contractStart',
        ]);
        $validated = $v->validate();
        if (!empty($validated['rankName'])) {
            if (preg_match('~/\s*([IVX]+)\s*([a-e])$~i', $validated['rankName'], $m)) {
                $validated['grade'] = strtoupper($m[1]) . '/' . strtolower($m[2]);
            } elseif ($validated['type'] !== 'PNS') {
                $validated['grade'] = $validated['rankName'] === '-' ? '' : $validated['rankName'];
            }
        }
        // Reference classification is deliberately fail-closed until institution verification.
        foreach (['unit'] as $key) {
            abort_unless(
                DB::table('reference_values')
                    ->where('kind', $key)
                    ->where('label', $validated[$key])
                    ->where('verified', true)
                    ->where('active', true)
                    ->exists(),
                422,
                "Referensi {$key} belum diverifikasi.",
            );
        }
        return $validated;
    }
    public function submit(User $maker, array $input): ChangeRequest
    {
        abort_unless($maker->canWrite(), 403, 'Akses hanya baca.');
        return DB::transaction(function () use ($maker, $input) {
            $employee = isset($input['employeeId'])
                ? Employee::lockForUpdate()->findOrFail($input['employeeId'])
                : null;
            abort_if(
                $employee && $employee->revision !== ($input['revision'] ?? null),
                409,
                'Versi data berubah. Muat ulang terlebih dahulu.',
            );
            $before = $employee
                ? (new EmployeeProjection())->project(
                    $employee->load('assignments.unitReference'),
                    $maker,
                )
                : [];
            $changes = $input['changes'];
            unset($changes['ageAtReference']); // Read-only age is derived from birth date.
            if (
                array_key_exists('serviceYears', $changes) ||
                array_key_exists('serviceMonths', $changes)
            ) {
                $changes['serviceReference'] =
                    $changes['serviceReference'] ?? today()->format('Y-m');
            }
            if (
                array_key_exists('serviceMonths', $changes) &&
                $changes['serviceMonths'] !== null &&
                $changes['serviceMonths'] !== ''
            ) {
                Validator::make($changes, ['serviceMonths' => 'integer|min:0|max:11'])->validate();
            }
            $allowed = array_merge(array_keys(self::FIELD_MAP), array_keys(self::ASSIGNMENT_MAP));
            abort_if(array_diff(array_keys($changes), $allowed), 422, 'Field tidak diizinkan.');
            $this->validateCandidate(array_merge($before ?? [], $changes), $employee?->id);
            $proposal = ChangeRequest::create([
                'employee_id' => $employee?->id,
                'maker_id' => $maker->id,
                'before' => $before ?? [],
                'changes' => $changes,
                'base_revision' => $employee?->revision ?? 0,
                'content_hash' => CanonicalJson::hash($changes),
                'reason' => $input['reason'],
                'status' => 'PENDING',
            ]);
            Audit::append($maker, 'Mengajukan perubahan', $changes['name'] ?? $employee->name, [
                'proposal_id' => $proposal->id,
            ]);
            return $proposal;
        });
    }
    public function decide(
        User $checker,
        string $id,
        string $decision,
        string $reason,
    ): ChangeRequest {
        abort_unless($checker->role === 'SUPER_ADMIN', 403, 'Pemeriksa tidak berwenang.');
        return DB::transaction(function () use ($checker, $id, $decision, $reason) {
            $p = ChangeRequest::lockForUpdate()->findOrFail($id);

            abort_unless($p->status === 'PENDING', 409, 'Usulan sudah diputuskan.');
            abort_unless(
                hash_equals($p->content_hash, CanonicalJson::hash($p->changes)),
                409,
                'Isi usulan berubah. Buat usulan baru untuk diperiksa.',
            );
            if ($decision === 'APPROVED') {
                $employee = $p->employee_id
                    ? Employee::lockForUpdate()->findOrFail($p->employee_id)
                    : null;
                abort_if(
                    $employee && $employee->revision !== $p->base_revision,
                    409,
                    'Data berubah setelah pengajuan. Buat usulan baru.',
                );
                $candidate = $this->validateCandidate(
                    array_merge($p->before, $p->changes),
                    $employee?->id,
                );
                abort_if(
                    ($candidate['effectiveDate'] ?? '') > today()->toDateString(),
                    422,
                    'Penerapan perubahan dengan TMT masa depan belum diaktifkan pada versi ini.',
                );
                // Future changes fail closed until the complete effective identity resolver is implemented.
                $identity = [];
                foreach (self::FIELD_MAP as $from => $to) {
                    if (array_key_exists($from, $candidate)) {
                        $identity[$to] =
                            $from === 'education'
                                ? $candidate[$from] ?? ''
                                : ($candidate[$from] === ''
                                    ? null
                                    : $candidate[$from]);
                    }
                }
                if ($employee) {
                    $employee->fill($identity);
                } else {
                    $employee = new Employee();
                    $identity['education'] ??= '';
                    $employee->fill($identity);
                }
                $employee->revision = ($employee->revision ?? 0) + 1;
                $employee->save();
                $assignment = [];
                foreach (self::ASSIGNMENT_MAP as $from => $to) {
                    $assignment[$to] = $candidate[$from] ?? null;
                }
                $assignment['effective_date'] = today()->toDateString();
                $assignment['grade'] = $candidate['grade'] ?? '';
                $assignment['unit_id'] = DB::table('reference_values')
                    ->where('kind', 'unit')
                    ->where('label', $candidate['unit'])
                    ->value('id');
                // Corrections retain superseded records and their audit evidence.
                EmployeeAssignment::where('employee_id', $employee->id)
                    ->whereDate('effective_date', today())
                    ->where('record_status', 'OFFICIAL')
                    ->update(['record_status' => 'SUPERSEDED']);
                EmployeeAssignment::create([
                    ...$assignment,
                    'employee_id' => $employee->id,
                    'record_status' => 'OFFICIAL',
                    'approved_by' => $checker->id,
                ]);
                $p->employee_id = $employee->id;
                $p->applied_at = now();
                DB::table('outbox_events')->insert([
                    'id' => (string) Str::uuid(),
                    'kind' => 'PERSONNEL_CHANGED',
                    'aggregate_id' => $employee->id,
                    'payload' => json_encode(['revision' => $employee->revision]),
                    'created_at' => now(),
                ]);
            }
            $p->checker_id = $checker->id;
            $p->status = $decision;
            $p->decision_reason = $reason;
            $p->save();
            Audit::append(
                $checker,
                $decision === 'APPROVED' ? 'Menyetujui perubahan' : 'Menolak perubahan',
                $p->changes['name'] ?? $p->before['name'],
                ['proposal_id' => $p->id, 'decision' => $decision],
            );
            return $p;
        });
    }
}
