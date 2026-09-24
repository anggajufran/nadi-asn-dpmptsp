<?php
namespace Tests\Laravel;
use Tests\TestCase;
use App\Modules\Radar\Services\RadarCalculator;
class RadarTest extends TestCase
{
    private function base(array $extra = []): array
    {
        return array_replace(
            [
                'type' => 'PNS',
                'status' => 'Aktif',
                'grade' => 'III/a',
                'positionType' => 'Pelaksana',
                'position' => 'Pengelola',
                'rankTmt' => '2022-04-01',
                'education' => 'S1',
                'revision' => 1,
            ],
            $extra,
        );
    }
    private function complete(array $extra = []): array
    {
        return array_replace(
            [
                'performance1' => 'Baik',
                'performance2' => 'Baik',
                'performanceYear1' => 2024,
                'performanceYear2' => 2025,
                'documentReference' => 'SK dan SKP sah',
                'documentsVerified' => true,
                'educationRecognized' => true,
                'examVerified' => true,
                'functionalVerified' => true,
                'managerialVerified' => true,
                'process' => 'Belum diusulkan',
                'employeeRevision' => 1,
            ],
            $extra,
        );
    }
    private function calc(
        array $e,
        string $kind,
        array $data = [],
        string $date = '2026-02-16',
    ): array {
        return (new RadarCalculator())->calculate($e, $kind, $data, $date);
    }
    public function test_kgb_missing_tmt_never_uses_total_tenure_or_rank(): void
    {
        $r = $this->calc($this->base(), 'kgb', $this->complete(['lastKgbDate' => '2024-04-01']));
        $this->assertNull($r['targetDate']);
        $this->assertSame('Data belum lengkap', $r['status']);
    }
    public function test_kgb_two_years_reminder_and_performance_for_every_position(): void
    {
        foreach (['Struktural', 'Fungsional', 'Pelaksana'] as $position) {
            $e = $this->base(['positionType' => $position]);
            $d = $this->complete([
                'lastKgbDate' => '2024-03-10',
                'lastKgbTmt' => '2024-04-01',
                'performance1' => 'Cukup',
                'performance2' => 'Cukup',
            ]);
            $r = $this->calc($e, 'kgb', $d, '2026-01-31');
            $this->assertFalse($r['reminderActive']);
            $r = $this->calc($e, 'kgb', $d, '2026-02-01');
            $this->assertTrue($r['reminderActive']);
            $this->assertSame('2026-04-01', $r['targetDate']);
            $this->assertSame('Belum memenuhi', $r['status']);
            $this->assertSame('Siap diusulkan', $this->calc($e, 'kgb', $d, '2026-04-01')['status']);
            $d['performance2'] = 'Kurang';
            $this->assertSame('Belum memenuhi', $this->calc($e, 'kgb', $d, '2026-04-01')['status']);
        }
    }
    public function test_kgb_leap_day_is_clamped(): void
    {
        $this->assertSame(
            '2026-02-28',
            $this->calc($this->base(), 'kgb', ['lastKgbTmt' => '2024-02-29'])['targetDate'],
        );
    }
    public function test_kp_target_uses_full_years_not_calendar_year_difference(): void
    {
        $e = $this->base(['rankTmt' => '2022-04-02']);
        $d = $this->complete(['targetDate' => '2026-04-01']);
        $this->assertSame('Belum memenuhi', $this->calc($e, 'kp', $d)['status']);
        unset($d['targetDate']);
        $r = $this->calc($e, 'kp', $d);
        $this->assertSame('2026-05-01', $r['targetDate']);
        $this->assertSame('Siap diusulkan', $r['status']);
    }
    public function test_kp_submission_window_handles_year_boundary_and_overrides(): void
    {
        $e = $this->base(['rankTmt' => '2020-01-01']);
        $d = $this->complete([
            'targetDate' => '2027-01-01',
            'performanceYear1' => 2025,
            'performanceYear2' => 2026,
        ]);
        $r = $this->calc($e, 'kp', $d, '2026-12-15');
        $this->assertSame('2026-11-16', $r['submissionOpen']);
        $this->assertSame('2026-12-15', $r['deadline']);
        $this->assertSame('Siap diusulkan', $r['status']);
        $this->assertSame('Belum memenuhi', $this->calc($e, 'kp', $d, '2026-12-16')['status']);
        $d['deadline'] = '2026-12-20';
        $d['scheduleReference'] = 'Surat layanan 01';
        $this->assertSame('Siap diusulkan', $this->calc($e, 'kp', $d, '2026-12-16')['status']);
        $r = $this->calc($e, 'kp', [], '2026-12-16');
        $this->assertSame('2027-02-01', $r['targetDate']);
    }
    public function test_education_caps_and_exam_exemption_do_not_override_cap(): void
    {
        foreach (
            [
                ['S1', 'III/d'],
                ['D-IV', 'III/d'],
                ['S2', 'IV/a'],
                ['S3', 'IV/b'],
                ['SMA', 'III/b'],
                ['D3', 'III/c'],
            ]
            as [$edu, $grade]
        ) {
            $r = $this->calc(
                $this->base(['education' => $edu, 'grade' => $grade]),
                'kp',
                $this->complete(['targetDate' => '2026-04-01']),
            );
            $this->assertSame('Belum memenuhi', $r['status']);
        }
        $this->assertSame(
            'Data belum lengkap',
            $this->calc(
                $this->base(['grade' => 'II/d']),
                'kp',
                $this->complete(['examVerified' => null]),
            )['status'],
        );
        $this->assertSame(
            'Data belum lengkap',
            $this->calc($this->base(['education' => 'Tidak dikenal']), 'kp', $this->complete())[
                'status'
            ],
        );
    }
    public function test_functional_credit_is_separate_from_cumulative_and_requires_target_level(): void
    {
        $e = $this->base([
            'grade' => 'III/b',
            'positionType' => 'Fungsional',
            'position' => 'Analis Ahli Pertama',
            'rankTmt' => '2024-04-01',
        ]);
        $d = $this->complete([
            'credit' => 50,
            'cumulativeCredit' => 100,
            'pakReference' => 'PAK 01',
        ]);
        $this->assertSame('Belum memenuhi', $this->calc($e, 'kp', $d)['status']);
        $e['position'] = 'Ahli Muda - Analis Kebijakan';
        $this->assertSame('Siap diusulkan', $this->calc($e, 'kp', $d)['status']);
        $d['credit'] = 49;
        $d['cumulativeCredit'] = 500;
        $this->assertSame('Belum memenuhi', $this->calc($e, 'kp', $d)['status']);
        $d['credit'] = 50;
        $d['cumulativeCredit'] = null;
        $this->assertSame('Data belum lengkap', $this->calc($e, 'kp', $d)['status']);
    }
    public function test_functional_thresholds_and_unknown_level(): void
    {
        foreach (
            [
                ['III/a', 'Ahli Pertama', 50],
                ['III/c', 'Ahli Muda', 100],
                ['IV/a', 'Ahli Madya', 150],
                ['IV/d', 'Ahli Utama', 200],
            ]
            as [$grade, $level, $credit]
        ) {
            $e = $this->base([
                'grade' => $grade,
                'positionType' => 'Fungsional',
                'position' => 'Penata ' . $level,
                'rankTmt' => '2024-04-01',
            ]);
            $d = $this->complete(['credit' => $credit, 'pakReference' => 'PAK sah']);
            $this->assertSame('Siap diusulkan', $this->calc($e, 'kp', $d)['status']);
            $d['credit'] = $credit - 1;
            $this->assertSame('Belum memenuhi', $this->calc($e, 'kp', $d)['status']);
        }
        $this->assertSame(
            'Data belum lengkap',
            $this->calc(
                $this->base(['positionType' => 'Fungsional', 'position' => 'Terampil']),
                'kp',
                $this->complete(),
            )['status'],
        );
    }
    public function test_structural_needs_manual_review_and_no_four_year_formula(): void
    {
        $e = $this->base(['positionType' => 'Struktural', 'rankTmt' => '2025-04-01']);
        $d = $this->complete();
        $this->assertNull($this->calc($e, 'kp', $d)['targetDate']);
        $d += [
            'targetDate' => '2026-04-01',
            'appointmentReference' => 'SK pelantikan dan verifikasi rentang',
        ];
        $this->assertSame('Siap diusulkan', $this->calc($e, 'kp', $d)['status']);
        $d['managerialVerified'] = false;
        $this->assertSame('Belum memenuhi', $this->calc($e, 'kp', $d)['status']);
    }
    public function test_master_revision_and_stale_performance_require_recheck(): void
    {
        $this->assertSame(
            'Data belum lengkap',
            $this->calc($this->base(), 'kp', $this->complete(['employeeRevision' => 2]))['status'],
        );
        $this->assertSame(
            'Data belum lengkap',
            $this->calc($this->base(), 'kp', $this->complete(['performanceYear2' => 2024]))[
                'status'
            ],
        );
    }
    public function test_radar_api_excludes_pppk_and_nonactive_pns(): void
    {
        $u = $this->user();
        $e = $this->employee($u);
        $this->fullSession($u)->getJson('/api/radar/kgb')->assertOk()->assertJsonCount(1, 'rows');
        foreach (['PPPK Penuh Waktu', 'PPPK Paruh Waktu'] as $type) {
            $e->assignments()->update(['type' => $type]);
            $this->getJson('/api/radar/kp')->assertOk()->assertJsonCount(0, 'rows');
            $this->postJson('/api/radar/kp/' . $e->id, [
                'revision' => 0,
                'employeeRevision' => 1,
                'data' => ['process' => 'Belum diusulkan', 'reason' => 'Data uji radar'],
            ])->assertUnprocessable();
        }
        $e->assignments()->update(['type' => 'PNS', 'status' => 'Pensiun']);
        $this->getJson('/api/radar/kgb')->assertOk()->assertJsonCount(0, 'rows');
    }
    public function test_admin_save_is_persistent_audited_and_conflict_safe(): void
    {
        $u = $this->user('ADMIN');
        $e = $this->employee($u);
        $payload = [
            'revision' => 0,
            'employeeRevision' => 1,
            'data' => [
                'lastKgbDate' => '2024-03-10',
                'lastKgbTmt' => '2024-04-01',
                'process' => 'Belum diusulkan',
                'reason' => 'Sesuai SK terakhir',
            ],
        ];
        $this->fullSession($u)
            ->postJson('/api/radar/kgb/' . $e->id, $payload)
            ->assertOk();
        $this->getJson('/api/radar/kgb')
            ->assertJsonPath('rows.0.data.lastKgbTmt', '2024-04-01')
            ->assertJsonPath('rows.0.revision', 1);
        $this->assertDatabaseHas('audit_events', ['action' => 'Memperbarui KGB Radar']);
        $this->postJson('/api/radar/kgb/' . $e->id, $payload)->assertConflict();
        $payload['revision'] = 1;
        $payload['data']['process'] = 'Perbaikan';
        $this->postJson('/api/radar/kgb/' . $e->id, $payload)->assertUnprocessable();
        $payload['data']['processNote'] = 'Surat perbaikan 01';
        $payload['data']['correctionDeadline'] = '2026-10-01';
        $this->postJson('/api/radar/kgb/' . $e->id, $payload)->assertOk();
        $e->increment('revision');
        $payload['revision'] = 2;
        $this->postJson('/api/radar/kgb/' . $e->id, $payload)->assertConflict();
    }
    public function test_invalid_dates_negative_credit_and_decisions_need_evidence(): void
    {
        $u = $this->user('SUPER_ADMIN');
        $e = $this->employee($u);
        $this->fullSession($u);
        $base = [
            'revision' => 0,
            'employeeRevision' => 1,
            'data' => ['process' => 'Belum diusulkan', 'reason' => 'Pemeriksaan data'],
        ];
        foreach (
            [
                ['targetDate' => '2026-10-02'],
                ['credit' => -1],
                ['targetDate' => 'bukan tanggal'],
                ['process' => 'Ditolak'],
                ['deadline' => '2026-09-30'],
            ]
            as $bad
        ) {
            $p = $base;
            $p['data'] = array_merge($p['data'], $bad);
            $this->postJson('/api/radar/kp/' . $e->id, $p)->assertUnprocessable();
        }
        $this->assertDatabaseCount('radar_records', 0);
    }
    public function test_radar_requires_full_login(): void
    {
        $this->getJson('/api/radar/kp')->assertUnauthorized();
    }
    public function test_export_footer_omits_developer_credit(): void
    {
        $this->assertStringNotContainsString(
            'Angga Jufran',
            file_get_contents(resource_path('views/reports/statistics.blade.php')),
        );
        $this->assertStringNotContainsString(
            'Angga Jufran',
            file_get_contents(resource_path('js/modules/reports/export.ts')),
        );
    }
}
