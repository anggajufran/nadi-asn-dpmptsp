<?php
namespace App\Modules\Duk\Http;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Modules\Personnel\Models\Employee;
use App\Modules\Personnel\Services\EmployeeProjection;
use App\Modules\Governance\Services\ChangeService;
use App\Shared\{Audit, CanonicalJson};
class ManualDukController
{
    public function index(Request $r, EmployeeProjection $projection)
    {
        $employees = Employee::inDukOrder()->with('assignments.unitReference')->get();
        return response()->json([
            'rows' => $employees
                ->map(fn($e) => $projection->project($e, $r->user()))
                ->filter()
                ->values(),
            'order' => $employees->modelKeys(),
            'hash' => CanonicalJson::hash($employees->modelKeys()),
        ]);
    }
    public function save(Request $r, ChangeService $service)
    {
        abort_unless($r->user()->role === 'SUPER_ADMIN', 403);
        $v = $r->validate([
            'order' => 'required|array|min:1|max:10000',
            'order.*' => 'required|uuid|distinct',
            'hash' => 'required|string|size:64',
            'reason' => 'required|string|min:5|max:1000',
            'rows' => 'present|array|max:500',
            'rows.*.employeeId' => 'required|uuid|distinct',
            'rows.*.revision' => 'required|integer|min:1',
            'rows.*.changes' => 'required|array|min:1',
        ]);
        $count = DB::transaction(function () use ($r, $v, $service) {
            DB::statement('LOCK TABLE employees IN SHARE ROW EXCLUSIVE MODE');
            $before = Employee::inDukOrder()->pluck('id')->all();
            abort_unless(
                hash_equals(CanonicalJson::hash($before), $v['hash']),
                409,
                'Urutan berubah. Muat ulang DUK sebelum menyimpan.',
            );
            $expected = $before;
            $submitted = $v['order'];
            sort($expected);
            sort($submitted);
            abort_unless(
                $expected === $submitted,
                422,
                'Urutan harus memuat seluruh pegawai tepat satu kali.',
            );
            foreach ($v['rows'] as $row) {
                $service->submit($r->user(), [...$row, 'reason' => $v['reason']]);
            }
            if ($before !== $v['order']) {
                foreach ($v['order'] as $i => $id) {
                    Employee::whereKey($id)->update(['duk_order' => $i + 1]);
                }
                Audit::append($r->user(), 'Menyimpan urutan DUK manual', 'DUK Digital', [
                    'before' => $before,
                    'after' => $v['order'],
                    'reason' => $v['reason'],
                ]);
            }
            return count($v['rows']);
        });
        return response()->json(['count' => $count, 'saved' => true]);
    }
}
