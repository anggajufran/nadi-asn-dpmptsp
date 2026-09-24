<?php
namespace App\Modules\Governance\Http;
use App\Modules\Governance\Models\ChangeRequest;
use App\Modules\Governance\Services\ChangeService;
use App\Modules\IdentityAccess\Models\User;
use Illuminate\Http\Request;
class ChangeController
{
    public function batch(
        \Illuminate\Http\Request $r,
        \App\Modules\Governance\Services\ChangeService $service,
    ) {
        $v = $r->validate([
            'rows' => 'required|array|min:1|max:500',
            'rows.*.employeeId' => 'required|uuid|distinct',
            'rows.*.revision' => 'required|integer',
            'rows.*.changes' => 'required|array|min:1',
            'reason' => 'required|string|min:5|max:1000',
        ]);
        $ids = \Illuminate\Support\Facades\DB::transaction(function () use ($v, $r, $service) {
            $ids = [];
            foreach ($v['rows'] as $row) {
                $ids[] = $service->submit($r->user(), [...$row, 'reason' => $v['reason']])->id;
            }
            return $ids;
        });
        return response()->json(['count' => count($ids), 'ids' => $ids], 201);
    }

    private function dto(ChangeRequest $p): array
    {
        return [
            'id' => $p->id,
            'employeeId' => $p->employee_id,
            'name' => $p->changes['name'] ?? ($p->before['name'] ?? 'Pegawai baru'),
            'makerId' => $p->maker_id,
            'makerName' => User::find($p->maker_id)?->name,
            'status' => $p->status,
            'createdAt' => $p->created_at->toIso8601String(),
            'reason' => $p->reason,
            'changes' => $p->changes,
            'before' => $p->before,
            'revision' => $p->base_revision,
            'decisionReason' => $p->decision_reason,
        ];
    }
    public function index(Request $r)
    {
        abort_unless($r->user()->canWrite(), 403);
        return response()->json(
            ChangeRequest::latest()->limit(100)->get()->map(fn($p) => $this->dto($p)),
        );
    }
    public function store(Request $r, ChangeService $service)
    {
        $v = $r->validate([
            'employeeId' => 'nullable|uuid',
            'revision' => 'nullable|integer|min:1',
            'changes' => 'required|array|min:1',
            'reason' => 'required|string|min:5|max:1000',
        ]);
        return response()->json($this->dto($service->submit($r->user(), $v)), 201);
    }
    public function decide(Request $r, string $id, ChangeService $service)
    {
        $v = $r->validate([
            'decision' => 'required|in:APPROVED,REJECTED',
            'reason' => 'required|string|min:5|max:1000',
        ]);
        return response()->json(
            $this->dto($service->decide($r->user(), $id, $v['decision'], $v['reason'])),
        );
    }
}
