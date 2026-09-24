<?php
namespace App\Modules\Personnel\Services;
use App\Modules\Personnel\Models\Employee;
use App\Modules\IdentityAccess\Models\User;
final class EmployeeProjection
{
    public function project(Employee $employee, User $user, ?string $asOf = null): ?array
    {
        $a = $employee->assignments
            ->where('record_status', 'OFFICIAL')
            ->filter(
                fn($a) => $a->effective_date->toDateString() <= ($asOf ?? today()->toDateString()),
            )
            ->sortByDesc('effective_date')
            ->first();
        if (!$a) {
            return null;
        }
        [$years, $months, $reference] = PersonnelClock::service(
            $a->service_years,
            $a->service_months,
            $a->service_reference,
            $asOf,
        );
        $data = [
            'id' => $employee->id,
            'revision' => $employee->revision,
            'name' => $employee->name,
            'nip' => $employee->nip,
            'gender' => $employee->gender,
            'education' => $employee->education,
            'birthPlace' => $employee->birth_place,
            'birthDate' => $employee->birth_date?->toDateString(),
            'driveUrl' => $employee->drive_url,
            'dukPositionLevel' => $a->duk_position_level,
            'trainingLevel' => $a->training_level,
            'trainingDate' => $a->training_date?->toDateString(),
            'sourceOrder' => $employee->source_order,
            'dukOrder' => $employee->duk_order,
            'institution' => $employee->institution,
            'graduationDate' => $employee->graduation_date?->toDateString(),
            'ageAtReference' => PersonnelClock::age($employee->birth_date?->toDateString(), $asOf),
            'sourceIssues' => $employee->source_issues ?? [],
            'type' => $a->type,
            'unit' => $a->unit_id ? $a->unitReference?->label ?? $a->unit : $a->unit,
            'position' => $a->position,
            'positionType' => $a->position_type,
            'grade' => $a->grade,
            'rankName' => $a->rank_name,
            'rankTmt' => $a->rank_tmt?->toDateString(),
            'serviceYears' => $years,
            'serviceMonths' => $months,
            'serviceReference' => $reference,
            'status' => $a->status,
            'effectiveDate' => $a->position_tmt?->toDateString(),
            'contractStart' => $a->contract_start,
            'contractEnd' => $a->contract_end,
            'history' => $employee->assignments
                ->map(
                    fn($a) => [
                        'date' => $a->effective_date->toDateString(),
                        'label' => $a->unit . ' · ' . $a->position . ' · ' . $a->record_status,
                    ],
                )
                ->values()
                ->all(),
        ];
        return [...$data, ...self::quality($data)];
    }
    public static function quality(array $data): array
    {
        $fields = json_decode(
            file_get_contents(resource_path('js/domain/personnel-schema.json')),
            true,
        );
        $excluded = ['sourceOrder', 'ageAtReference'];
        if (($data['type'] ?? '') === 'PPPK Paruh Waktu') {
            $excluded = [...$excluded, 'rankName', 'serviceYears', 'serviceMonths'];
        }
        $required = array_values(array_filter($fields, fn($f) => !in_array($f['key'], $excluded)));
        $missing = [];
        foreach ($required as $f) {
            $v = $data[$f['key']] ?? null;
            if ($v === null || trim((string) $v) === '' || $v === '-') {
                $missing[] = $f['label'];
            }
        }
        return [
            'completeness' => (int) round(
                (100 * (count($required) - count($missing))) / count($required),
            ),
            'missingFields' => $missing,
        ];
    }
}
