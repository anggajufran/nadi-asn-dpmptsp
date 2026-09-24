<?php
namespace App\Modules\Personnel\Http;
use App\Modules\Personnel\Models\Employee;
use App\Modules\Personnel\Services\EmployeeProjection;
use Illuminate\Http\Request;
class EmployeeController
{
    public function index(Request $r, EmployeeProjection $projection)
    {
        $v = $r->validate([
            'search' => 'nullable|string|max:100',
            'unit' => 'nullable|string|max:150',
            'type' => 'nullable|in:PNS,PPPK Penuh Waktu,PPPK Paruh Waktu',
            'status' => 'nullable|in:Aktif,Pensiun,Mutasi,Meninggal',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);
        $rows = Employee::with('assignments.unitReference')
            ->inDukOrder()
            ->get()
            ->map(fn($e) => $projection->project($e, $r->user()))
            ->filter();
        foreach (['unit', 'type', 'status'] as $key) {
            if (!empty($v[$key])) {
                $rows = $rows->where($key, $v[$key]);
            }
        }
        if (!empty($v['search'])) {
            $rows = $rows->filter(
                fn($e) => str_contains(mb_strtolower($e['name']), mb_strtolower($v['search'])) ||
                    str_contains($e['nip'], $v['search']),
            );
        }
        $size = (int) ($v['per_page'] ?? 100);
        $page = (int) ($v['page'] ?? 1);
        return response()->json([
            'data' => $rows
                ->values()
                ->slice(($page - 1) * $size, $size)
                ->values(),
            'total' => $rows->count(),
            'page' => $page,
            'per_page' => $size,
        ]);
    }
}
