<?php
namespace App\Modules\References\Http;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Shared\Audit;
class UnitController
{
    public function index()
    {
        return response()->json(
            \App\Modules\References\Services\UnitOrder::sort(
                DB::table('reference_values')
                    ->where('kind', 'unit')
                    ->where('active', true)
                    ->orderBy('label')
                    ->get(['id', 'label']),
            ),
        );
    }
    public function store(Request $r)
    {
        abort_unless($r->user()->role === 'SUPER_ADMIN', 403);
        $v = $r->validate(['label' => 'required|string|max:150']);
        $label = trim($v['label']);
        abort_if(
            DB::table('reference_values')
                ->where('kind', 'unit')
                ->whereRaw('lower(label)=?', [mb_strtolower($label)])
                ->exists(),
            422,
            'Nama unit sudah ada.',
        );
        $id = (string) Str::uuid();
        DB::transaction(function () use ($id, $label, $r) {
            DB::table('reference_values')->insert([
                'id' => $id,
                'kind' => 'unit',
                'code' => 'UNIT-' . $id,
                'label' => $label,
                'active' => true,
                'verified' => true,
            ]);
            Audit::append($r->user(), 'Menambah unit kerja', $label);
        });
        return response()->json(['id' => $id, 'label' => $label], 201);
    }
    public function update(Request $r, string $id)
    {
        abort_unless($r->user()->role === 'SUPER_ADMIN', 403);
        $v = $r->validate([
            'label' => 'sometimes|required|string|max:150',
            'active' => 'sometimes|boolean',
        ]);
        DB::transaction(function () use ($r, $id, $v) {
            $unit = DB::table('reference_values')
                ->where('id', $id)
                ->where('kind', 'unit')
                ->lockForUpdate()
                ->first();
            abort_unless($unit, 404);
            $occupied = DB::table('employee_assignments')
                ->where(function ($q) use ($id, $unit) {
                    $q->where('unit_id', $id)->orWhere('unit', $unit->label);
                })
                ->where('record_status', 'OFFICIAL')
                ->exists();
            abort_if(
                isset($v['active']) && !$v['active'] && $occupied,
                422,
                'Unit masih digunakan pegawai. Pindahkan pegawai terlebih dahulu sebelum menonaktifkan unit.',
            );
            if (isset($v['label'])) {
                $v['label'] = trim($v['label']);
                abort_if(
                    DB::table('reference_values')
                        ->where('kind', 'unit')
                        ->where('id', '!=', $id)
                        ->whereRaw('lower(label)=?', [mb_strtolower($v['label'])])
                        ->exists(),
                    422,
                    'Nama unit sudah ada.',
                );
                DB::table('employee_assignments')
                    ->where('unit', $unit->label)
                    ->whereNull('unit_id')
                    ->update(['unit_id' => $id]);
            }
            DB::table('reference_values')->where('id', $id)->update($v);
            Audit::append($r->user(), 'Mengubah unit kerja', $v['label'] ?? $unit->label, [
                'before' => $unit->label,
                'active' => $v['active'] ?? $unit->active,
            ]);
        });
        return response()->json(['ok' => true]);
    }
}
