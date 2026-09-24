<?php
namespace App\Modules\Duk\Http;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Modules\Duk\Services\DukRanking;
use App\Modules\Personnel\Models\Employee;
use App\Modules\Personnel\Services\EmployeeProjection;
use App\Shared\{Audit, CanonicalJson};
use App\Modules\SnapshotsReports\Models\ExportJob;
use App\Modules\SnapshotsReports\Jobs\RenderExport;
class DukController
{
    private function live(Request $r): array
    {
        $rows = Employee::with('assignments.unitReference')
            ->orderBy('id')
            ->get()
            ->map(fn($e) => (new EmployeeProjection())->project($e, $r->user()))
            ->filter()
            ->values()
            ->all();
        return (new DukRanking())->rank($rows, today()->toDateString());
    }
    public function index(Request $r)
    {
        return response()->json($this->live($r));
    }
    public function history()
    {
        return response()->json([
            'candidates' => DB::table('duk_candidates as c')
                ->join('users as u', 'u.id', '=', 'c.maker_id')
                ->leftJoin('published_snapshots as s', 's.duk_candidate_id', '=', 'c.id')
                ->orderByDesc('c.created_at')
                ->limit(30)
                ->get([
                    'c.id',
                    'c.as_of',
                    'c.created_at',
                    'u.name as maker',
                    'c.maker_id',
                    's.id as publicationId',
                    's.version_label as version',
                ]),
            'publications' => DB::table('published_snapshots')
                ->where('kind', 'DUK')
                ->latest('published_at')
                ->limit(30)
                ->get(['id', 'version_label', 'as_of', 'published_at', 'content_hash']),
        ]);
    }
    public function candidate(Request $r)
    {
        $v = $r->validate(['hash' => 'required|string|size:64']);
        return DB::transaction(function () use ($r, $v) {
            DB::statement(
                'LOCK TABLE employees, employee_assignments, reference_values IN SHARE MODE',
            );
            $live = $this->live($r);
            abort_unless(
                hash_equals($live['hash'], $v['hash']),
                409,
                'Data berubah. Perbarui pratinjau sebelum membuat kandidat.',
            );
            abort_unless(
                $live['ready'],
                422,
                'Lengkapi data dan selesaikan seri sebelum membuat kandidat resmi.',
            );
            DB::select('SELECT pg_advisory_xact_lock(9152026)');
            $ruleset = (string) Str::uuid();
            DB::table('rulesets')->insert([
                'id' => $ruleset,
                'kind' => 'DUK',
                'version' => (int) DB::table('rulesets')->where('kind', 'DUK')->max('version') + 1,
                'status' => 'INSTITUTION_POLICY',
                'parameters' => json_encode([
                    'version' => DukRanking::VERSION,
                    'criteria' => DukRanking::LABELS,
                    'positions' => DukRanking::POSITIONS,
                    'training' => DukRanking::TRAINING,
                    'mkg' => 'Elapsed time from rank TMT to DUK date',
                ]),
                'regulatory_basis' =>
                    'Hierarki pemilik instansi melalui instruksi pengguna 15 September 2026; bukan pengesahan regulasi otomatis.',
                'content_hash' => CanonicalJson::hash([
                    DukRanking::VERSION,
                    DukRanking::LABELS,
                    DukRanking::POSITIONS,
                    DukRanking::TRAINING,
                ]),
                'maker_id' => $r->user()->id,
                'effective_date' => $live['asOf'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $id = (string) Str::uuid();
            DB::table('duk_candidates')->insert([
                'id' => $id,
                'maker_id' => $r->user()->id,
                'ruleset_id' => $ruleset,
                'as_of' => $live['asOf'],
                'payload' => json_encode($live),
                'content_hash' => CanonicalJson::hash($live),
                'created_at' => now(),
            ]);
            Audit::append($r->user(), 'Menyiapkan kandidat DUK', $id, [
                'policy' => DukRanking::VERSION,
                'count' => $live['total'],
            ]);
            return response()->json(['id' => $id], 201);
        });
    }
    public function show(string $id)
    {
        $c = DB::table('duk_candidates')->where('id', $id)->first();
        abort_unless($c, 404);
        $payload = json_decode($c->payload, true);
        abort_unless(
            hash_equals($c->content_hash, CanonicalJson::hash($payload)),
            409,
            'Integritas kandidat tidak sesuai.',
        );
        return response()->json([
            'id' => $id,
            'makerId' => $c->maker_id,
            'publicationId' => DB::table('published_snapshots')
                ->where('duk_candidate_id', $id)
                ->value('id'),
            'payload' => $payload,
        ]);
    }
    public function publish(Request $r, string $id)
    {
        abort_unless($r->user()->role === 'SUPER_ADMIN', 403);
        $v = $r->validate(['reason' => 'required|string|min:5|max:1000']);
        return DB::transaction(function () use ($r, $id, $v) {
            DB::statement(
                'LOCK TABLE employees, employee_assignments, reference_values IN SHARE MODE',
            );
            $c = DB::table('duk_candidates')->where('id', $id)->lockForUpdate()->first();
            abort_unless($c, 404);
            abort_if(
                $c->maker_id === $r->user()->id,
                403,
                'Kandidat harus disahkan Super Admin yang berbeda dari pembuatnya.',
            );
            abort_if(
                DB::table('published_snapshots')->where('duk_candidate_id', $id)->exists(),
                409,
                'Kandidat ini sudah diterbitkan.',
            );
            $payload = json_decode($c->payload, true);
            abort_unless(
                hash_equals($c->content_hash, CanonicalJson::hash($payload)),
                409,
                'Integritas kandidat tidak sesuai.',
            );
            $live = $this->live($r);
            abort_unless(
                $live['ready'] && hash_equals($live['hash'], $payload['hash']),
                409,
                'Data atau tanggal DUK berubah. Buat kandidat baru.',
            );
            $publication = (string) Str::uuid();
            $version = 'DUK-' . str_replace('-', '', $c->as_of) . '-' . substr($publication, 0, 8);
            DB::table('published_snapshots')->insert([
                'id' => $publication,
                'kind' => 'DUK',
                'version_label' => $version,
                'as_of' => $c->as_of,
                'ruleset_id' => $c->ruleset_id,
                'payload' => $c->payload,
                'content_hash' => $c->content_hash,
                'template_version' => 'DUK-XLSX-v1',
                'maker_id' => $c->maker_id,
                'publisher_id' => $r->user()->id,
                'published_at' => now(),
                'duk_candidate_id' => $id,
            ]);
            Audit::append($r->user(), 'Mengesahkan DUK', $version, [
                'candidate' => $id,
                'reason' => $v['reason'],
                'hash' => $c->content_hash,
            ]);
            return response()->json(['id' => $publication, 'version' => $version], 201);
        });
    }
    public function export(Request $r, string $id)
    {
        abort_unless($r->user()->role === 'SUPER_ADMIN', 403);
        $s = DB::table('published_snapshots')->where('id', $id)->where('kind', 'DUK')->first();
        abort_unless($s, 404);
        $p = json_decode($s->payload, true);
        abort_unless(hash_equals($s->content_hash, CanonicalJson::hash($p)), 409);
        $job = DB::transaction(function () use ($r, $s, $p) {
            $job = ExportJob::create([
                'owner_id' => $r->user()->id,
                'status' => 'QUEUED',
                'format' => 'xlsx',
                'kind' => 'duk',
                'parameters' => [
                    'rows' => $p['rows'],
                    'actor' => $r->user()->name,
                    'securityVersion' => $r->user()->security_version,
                    'publication' => [
                        'version' => $s->version_label,
                        'asOf' => $s->as_of,
                        'policy' => $p['policy'],
                        'hash' => $s->content_hash,
                        'publishedAt' => $s->published_at,
                    ],
                ],
            ]);
            Audit::append($r->user(), 'Meminta Excel DUK resmi', $s->version_label, [
                'export_id' => $job->id,
            ]);
            RenderExport::dispatch($job->id)->afterCommit();
            return $job;
        });
        return response()->json(['id' => $job->id, 'status' => 'QUEUED'], 202);
    }
}
