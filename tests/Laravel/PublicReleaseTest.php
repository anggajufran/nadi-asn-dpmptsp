<?php
namespace Tests\Laravel;
use Tests\TestCase;
use Illuminate\Support\Facades\{Queue, Storage};
use App\Modules\SnapshotsReports\Jobs\RenderExport;
use App\Modules\SnapshotsReports\Models\ExportJob;
class PublicReleaseTest extends TestCase
{
    public function test_single_employee_filter_returns_counts_without_identity(): void
    {
        $u = $this->user();
        $e = $this->employee($u);
        $r = $this->getJson('/api/public/statistics?unit=Sekretariat&jenis=PNS')
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('units.0.value', 1);
        $r->assertDontSee($e->nip)
            ->assertDontSee($e->name)
            ->assertDontSee('birthDate')
            ->assertDontSee('source_payload');
    }
    public function test_part_time_is_not_in_asn_total(): void
    {
        $u = $this->user();
        $e = $this->employee($u);
        $e->assignments()->update(['type' => 'PPPK Paruh Waktu']);
        $this->getJson('/api/public/statistics')
            ->assertOk()
            ->assertJsonPath('total', 0)
            ->assertJsonPath('partTime', 1);
        $this->fullSession($u)
            ->getJson('/api/dashboard')
            ->assertOk()
            ->assertJsonPath('total', 0)
            ->assertJsonPath('partTime', 1)
            ->assertJsonPath('pppk', 0);
    }
    public function test_pdf_contains_aggregate_dto_only(): void
    {
        Queue::fake();
        Storage::fake('local');
        $u = $this->user('SUPER_ADMIN');
        $e = $this->employee($u);
        $id = $this->fullSession($u)
            ->postJson('/api/exports', ['kind' => 'statistics'])
            ->assertAccepted()
            ->json('id');
        (new RenderExport($id))->handle();
        $job = ExportJob::findOrFail($id);
        $this->assertStringStartsWith('%PDF-', Storage::disk('local')->get($job->file_path));
        $this->assertStringNotContainsString($e->nip, json_encode($job->parameters));
    }
}
