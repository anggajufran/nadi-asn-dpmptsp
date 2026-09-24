<?php
namespace App\Modules\Analytics\Services;
use App\Modules\Personnel\Models\Employee;
use App\Modules\IdentityAccess\Models\User;
use App\Modules\Personnel\Services\EmployeeProjection;
/** Only grouped counts cross this boundary. No names, NIP, birthdays, source or personnel IDs. */
final class PublicReleaseReader
{
    public function read(array $filters): array
    {
        $rows = Employee::with('assignments.unitReference')
            ->get()
            ->map(fn($e) => (new EmployeeProjection())->project($e, new User()))
            ->filter()
            ->where('status', 'Aktif');
        if (!empty($filters['unit'])) {
            $rows = $rows->where('unit', $filters['unit']);
        }
        if (!empty($filters['jenis'])) {
            $rows = $rows->where('type', $filters['jenis']);
        }
        $asn = $rows->whereIn('type', ['PNS', 'PPPK Penuh Waktu']);
        $chartRows = ($filters['jenis'] ?? '') === 'PPPK Paruh Waktu' ? $rows : $asn;
        $group = fn($data, $key) => $data
            ->groupBy(fn($r) => $r[$key] ?: 'Belum tercatat')
            ->map(fn($g, $label) => ['label' => (string) $label, 'value' => $g->count()])
            ->values()
            ->all();
        return [
            'asOf' => today()->toDateString(),
            'demo' => false,
            'total' => $asn->count(),
            'partTime' => $rows->where('type', 'PPPK Paruh Waktu')->count(),
            'composition' => array_map(
                fn($label) => ['label' => $label, 'value' => $asn->where('type', $label)->count()],
                ['PNS', 'PPPK Penuh Waktu'],
            ),
            'education' => $group($chartRows, 'education'),
            'units' => \App\Modules\References\Services\UnitOrder::sort($group($chartRows, 'unit')),
            'gender' => $group($chartRows, 'gender'),
            'positions' => $group($chartRows, 'positionType'),
            'grades' => $group($asn->where('type', 'PNS'), 'grade'),
            'pppkGrades' => $group($asn->where('type', 'PPPK Penuh Waktu'), 'grade'),
            'functional' => $group($chartRows->where('positionType', 'Fungsional'), 'position'),
            'privacyApplied' => false,
        ];
    }
}
