<?php
namespace App\Modules\Analytics\Http;
use Illuminate\Http\Request;
use App\Modules\Personnel\Models\Employee;
use App\Modules\Personnel\Services\EmployeeProjection;
use App\Modules\Governance\Models\ChangeRequest;
use App\Modules\Audit\Models\AuditEvent;
use App\Modules\Analytics\Services\PublicReleaseReader;
class DashboardController
{
    public function public(Request $r, PublicReleaseReader $reader)
    {
        abort_if(
            array_diff(array_keys($r->query()), ['unit', 'jenis']),
            422,
            'Filter tidak diizinkan.',
        );
        $v = $r->validate([
            'unit' => 'nullable|string|max:150',
            'jenis' => 'nullable|in:PNS,PPPK Penuh Waktu,PPPK Paruh Waktu',
        ]);
        return response()->json($reader->read($v));
    }
    public function internal(Request $r, EmployeeProjection $projection)
    {
        $rows = Employee::with('assignments.unitReference')
            ->get()
            ->map(fn($e) => $projection->project($e, $r->user()))
            ->filter()
            ->where('status', 'Aktif');
        return response()->json([
            'total' => $rows->whereIn('type', ['PNS', 'PPPK Penuh Waktu'])->count(),
            'allEmployees' => $rows->count(),
            'partTime' => $rows->where('type', 'PPPK Paruh Waktu')->count(),
            'pns' => $rows->where('type', 'PNS')->count(),
            'pppk' => $rows->where('type', 'PPPK Penuh Waktu')->count(),
            'pending' => $r->user()->canWrite()
                ? ChangeRequest::where('status', 'PENDING')->count()
                : 0,
            'incomplete' => $rows->where('completeness', '<', 100)->count(),
            'quality' => round($rows->avg('completeness') ?? 0),
            'recent' => $r->user()->canWrite()
                ? AuditEvent::latest('created_at')->limit(6)->get()->map(
                    fn($e) => [
                        'id' => $e->id,
                        'actor' => $e->actor_name,
                        'action' => $e->action,
                        'subject' => $e->subject,
                        'createdAt' => $e->created_at->toIso8601String(),
                    ],
                )
                : [],
            'units' => \App\Modules\References\Services\UnitOrder::sort(
                $rows
                    ->groupBy('unit')
                    ->map(fn($group, $label) => ['label' => $label, 'value' => $group->count()])
                    ->values(),
            ),
            'demo' => false,
        ]);
    }
}
