<?php
namespace Tests\Laravel;
use App\Modules\Duk\Services\DukRanking;
use PHPUnit\Framework\TestCase;
class DukRankingTest extends TestCase
{
    private function row(string $id, array $extra = []): array
    {
        return array_merge(
            [
                'id' => $id,
                'name' => $id,
                'nip' => str_pad($id, 18, '0'),
                'type' => 'PNS',
                'status' => 'Aktif',
                'rankName' => 'Pembina / IV a',
                'rankTmt' => '2020-01-15',
                'position' => 'Analis Ahli Madya',
                'positionType' => 'Fungsional',
                'trainingLevel' => 'Belum pernah',
                'education' => 'S1',
                'graduationDate' => '2005-01-01',
                'birthDate' => '1980-01-01',
            ],
            $extra,
        );
    }
    private function order(array $rows): array
    {
        return array_column((new DukRanking())->rank($rows, '2026-09-15')['rows'], 'id');
    }
    public function test_heads_override_rank_and_pppk_and_inactive_are_excluded(): void
    {
        $rows = [
            $this->row('f', ['rankName' => 'IV/e']),
            $this->row('s', [
                'position' => 'Sekretaris Dinas',
                'positionType' => 'Struktural',
                'rankName' => 'III/a',
            ]),
            $this->row('k', [
                'position' => 'Kepala Dinas',
                'positionType' => 'Struktural',
                'rankName' => 'III/a',
            ]),
            $this->row('p', ['type' => 'PPPK Penuh Waktu']),
            $this->row('q', ['type' => 'PPPK Paruh Waktu']),
            $this->row('x', ['status' => 'Pensiun']),
        ];
        $this->assertSame(['k', 's', 'f'], $this->order($rows));
    }
    public function test_user_kasubag_examples(): void
    {
        $k = $this->row('kasubag', [
            'position' => 'Kepala Sub Bagian Kepegawaian',
            'positionType' => 'Struktural',
        ]);
        $this->assertSame(
            ['kasubag', 'muda'],
            $this->order([
                $this->row('muda', ['rankName' => 'III/d', 'position' => 'Ahli Muda']),
                $k,
            ]),
        );
        $this->assertSame(
            ['madya', 'kasubag'],
            $this->order([$k, $this->row('madya', ['rankName' => 'IV/b'])]),
        );
        $this->assertSame(['kasubag', 'madya'], $this->order([$this->row('madya'), $k]));
    }
    public function test_mkg_comes_from_rank_tmt_not_total_service(): void
    {
        $a = $this->row('a', ['rankTmt' => '2020-03-16', 'serviceYears' => 40]);
        $b = $this->row('b', ['rankTmt' => '2020-03-15', 'serviceYears' => 1]);
        $this->assertSame(['b', 'a'], $this->order([$a, $b]));
        $r = (new DukRanking())->rank([$a], '2026-09-15')['rows'][0];
        $this->assertSame(6, $r['mkgYears']);
        $this->assertSame(5, $r['mkgMonths']);
        $this->assertSame(30, $r['mkgDays']);
    }
    public function test_training_level_then_date_then_education_then_graduation_year_then_age(): void
    {
        foreach (
            [
                [
                    ['trainingLevel' => 'PIM III / PKA', 'trainingDate' => '2021-01-01'],
                    ['trainingLevel' => 'PIM IV / PKP', 'trainingDate' => '2010-01-01'],
                ],
                [
                    ['trainingLevel' => 'PIM III / PKA', 'trainingDate' => '2010-01-01'],
                    ['trainingLevel' => 'PIM III / PKA', 'trainingDate' => '2021-01-01'],
                ],
                [['education' => 'S2'], ['education' => 'S1']],
                [['graduationDate' => '2004-12-31'], ['graduationDate' => '2005-01-01']],
                [
                    ['graduationDate' => '2005-12-01', 'birthDate' => '1979-01-01'],
                    ['graduationDate' => '2005-01-01', 'birthDate' => '1980-01-01'],
                ],
            ]
            as [$a, $b]
        ) {
            $this->assertSame(['a', 'b'], $this->order([$this->row('b', $b), $this->row('a', $a)]));
        }
    }
    public function test_missing_training_is_not_treated_as_never_and_complete_ties_are_unresolved(): void
    {
        $r = (new DukRanking())->rank(
            [$this->row('a', ['trainingLevel' => null]), $this->row('b'), $this->row('c')],
            '2026-09-15',
        );
        $this->assertFalse($r['ready']);
        $this->assertNull($r['rows'][0]['dukRank']);
        $this->assertStringContainsString('Seri', implode(' ', $r['rows'][0]['dukIssues']));
        $this->assertStringContainsString('Diklat belum', implode(' ', $r['rows'][2]['dukIssues']));
    }
    public function test_invalid_dates_and_unknown_position_prevent_official_rank(): void
    {
        $r = (new DukRanking())->rank(
            [
                $this->row('a', [
                    'position' => 'Jabatan tidak dipetakan',
                    'rankTmt' => '2026-10-01',
                    'birthDate' => '1980-02-30',
                ]),
            ],
            '2026-09-15',
        );
        $this->assertFalse($r['ready']);
        $this->assertCount(3, $r['rows'][0]['dukIssues']);
    }
    public function test_known_head_pair_with_complete_data_is_ready_and_hash_is_independent_of_input_order(): void
    {
        $rows = [
            $this->row('k', ['position' => 'Kepala Dinas']),
            $this->row('s', ['position' => 'Sekretaris Dinas']),
        ];
        $a = (new DukRanking())->rank($rows, '2026-09-15');
        $b = (new DukRanking())->rank(array_reverse($rows), '2026-09-15');
        $this->assertTrue($a['ready']);
        $this->assertSame([1, 2], array_column($a['rows'], 'dukRank'));
        $this->assertSame($a['hash'], $b['hash']);
    }
}
