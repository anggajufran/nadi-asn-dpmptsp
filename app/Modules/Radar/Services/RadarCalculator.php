<?php
namespace App\Modules\Radar\Services;
use Carbon\CarbonImmutable as Date;
final class RadarCalculator
{
    public const VERSION = '2026-09-22.1';
    public const GRADES = [
        'I/a',
        'I/b',
        'I/c',
        'I/d',
        'II/a',
        'II/b',
        'II/c',
        'II/d',
        'III/a',
        'III/b',
        'III/c',
        'III/d',
        'IV/a',
        'IV/b',
        'IV/c',
        'IV/d',
        'IV/e',
    ];
    public function calculate(array $e, string $kind, array $d = [], ?string $asOf = null): array
    {
        $now = Date::parse($asOf ?? today()->toDateString())->startOfDay();
        $missing = [];
        $failed = [];
        $checks = [];
        $check = function (string $label, ?bool $ok) use (&$missing, &$failed, &$checks) {
            $checks[] = [
                'label' => $label,
                'state' => $ok === null ? 'missing' : ($ok ? 'pass' : 'fail'),
            ];
            if ($ok === null) {
                $missing[] = $label;
            } elseif (!$ok) {
                $failed[] = $label;
            }
        };
        $verify = function (string $key, string $label) use ($d, $check) {
            $v = $d[$key] ?? null;
            $check($label, $v === null ? null : $v === true);
        };
        $grade = preg_replace('/\s+/', '', $e['grade'] ?? '');
        $index = array_search($grade, self::GRADES, true);
        $position = $e['positionType'] ?? '';
        $target = null;
        $earliest = null;
        $nextGrade = null;
        $level = null;
        $creditNeeded = null;
        $cumulativeNeeded = null;
        $check(
            'Pegawai PNS aktif',
            ($e['type'] ?? '') === 'PNS' && ($e['status'] ?? '') === 'Aktif',
        );
        $check('Golongan tercatat dengan benar', $index === false ? null : true);
        if ($kind === 'kgb') {
            $last = $d['lastKgbTmt'] ?? null;
            $check('Tanggal SK KGB terakhir tersedia', empty($d['lastKgbDate']) ? null : true);
            $check('TMT KGB terakhir tersedia', $last ? true : null);
            if ($last) {
                $earliest = Date::parse($last)->addYearsNoOverflow(2);
                $target = $earliest;
                $check('Dua tahun sejak TMT KGB terakhir telah tercapai', $now->gte($target));
            }
        } else {
            $check(
                'Jenis jabatan dikenali',
                in_array($position, ['Pelaksana', 'Fungsional', 'Struktural']) ? true : null,
            );
            $check('TMT pangkat terakhir tersedia', empty($e['rankTmt']) ? null : true);
            if ($index !== false) {
                $nextGrade = self::GRADES[$index + 1] ?? null;
                $check('Masih tersedia pangkat berikutnya', $nextGrade !== null);
            }
            if ($position === 'Struktural') {
                $verify(
                    'managerialVerified',
                    'Rentang pangkat dan jalur struktural telah diverifikasi',
                );
                $check(
                    'Riwayat pelantikan dan dasar jalur struktural tercatat',
                    empty($d['appointmentReference']) ? null : true,
                );
                $check(
                    'TMT target struktural ditetapkan pengelola',
                    empty($d['targetDate']) ? null : true,
                );
            } elseif (!empty($e['rankTmt']) && in_array($position, ['Pelaksana', 'Fungsional'])) {
                $earliest = Date::parse($e['rankTmt'])->addYearsNoOverflow(
                    $position === 'Pelaksana' ? 4 : 2,
                );
                $target =
                    $earliest->day === 1
                        ? $earliest
                        : $earliest->addMonthNoOverflow()->startOfMonth();
                // Choose the next filing period whose baseline deadline has not elapsed.
                $current = $now->startOfMonth()->addMonthsNoOverflow($now->day > 15 ? 2 : 1);
                if ($target->lt($current)) {
                    $target = $current;
                }
            }
            if (!empty($d['targetDate'])) {
                $target = Date::parse($d['targetDate']);
            }
            if ($target) {
                $check(
                    'Periode KP tanggal 1 sejak Oktober 2025',
                    $target->day === 1 && $target->gte(Date::parse('2025-10-01')),
                );
                if ($earliest) {
                    $check(
                        $position === 'Pelaksana'
                            ? 'Empat tahun dalam pangkat pada TMT target'
                            : 'Dua tahun dalam pangkat pada TMT target',
                        $target->gte($earliest),
                    );
                }
                if (!empty($e['rankTmt'])) {
                    $check(
                        'TMT target sesudah TMT pangkat terakhir',
                        $target->gt(Date::parse($e['rankTmt'])),
                    );
                }
            }
            if ($position === 'Pelaksana') {
                $verify('educationRecognized', 'Ijazah pada Master Pegawai diakui administrasi');
                $edu = strtoupper(preg_replace('/[\s-]/', '', $e['education'] ?? ''));
                $caps = [
                    'SD' => 'II/a',
                    'SMP' => 'II/c',
                    'SLTP' => 'II/c',
                    'SLTPKEJURUAN' => 'II/d',
                    'SMA' => 'III/b',
                    'SMK' => 'III/b',
                    'SMA/SMK' => 'III/b',
                    'SLTA' => 'III/b',
                    'D1' => 'III/b',
                    'DI' => 'III/b',
                    'D2' => 'III/b',
                    'DII' => 'III/b',
                    'D3' => 'III/c',
                    'DIII' => 'III/c',
                    'SGPLB' => 'III/c',
                    'SARJANAMUDA' => 'III/c',
                    'S1' => 'III/d',
                    'D4' => 'III/d',
                    'DIV' => 'III/d',
                    'S2' => 'IV/a',
                    'DOKTER' => 'IV/a',
                    'APOTEKER' => 'IV/a',
                    'SPESIALISI' => 'IV/a',
                    'S3' => 'IV/b',
                    'DOKTOR' => 'IV/b',
                    'SPESIALISII' => 'IV/b',
                ];
                $cap = $caps[$edu] ?? null;
                $check(
                    'Pangkat tujuan tidak melampaui batas pendidikan' .
                        ($cap ? ' (' . $cap . ')' : ''),
                    $cap && $nextGrade
                        ? array_search($nextGrade, self::GRADES) <= array_search($cap, self::GRADES)
                        : null,
                );
                if (in_array($grade, ['II/d', 'III/d'])) {
                    $verify('examVerified', 'Ujian dinas lulus atau pembebasan sah diverifikasi');
                }
            }
            if ($position === 'Fungsional') {
                preg_match('/\bAhli\s+(Pertama|Muda|Madya|Utama)\b/i', $e['position'] ?? '', $m);
                $level = isset($m[1]) ? 'Ahli ' . ucfirst(strtolower($m[1])) : null;
                $check('Jenjang Ahli terbaca pada Nama Jabatan', $level ? true : null);
                $bands = [
                    'III/a' => ['Ahli Pertama', 50],
                    'III/b' => ['Ahli Pertama', 50],
                    'III/c' => ['Ahli Muda', 100],
                    'III/d' => ['Ahli Muda', 100],
                    'IV/a' => ['Ahli Madya', 150],
                    'IV/b' => ['Ahli Madya', 150],
                    'IV/c' => ['Ahli Madya', 150],
                    'IV/d' => ['Ahli Utama', 200],
                    'IV/e' => ['Ahli Utama', 200],
                ];
                $creditNeeded = $bands[$grade][1] ?? null;
                $check(
                    'AK untuk naik pangkat mencukupi' .
                        ($creditNeeded ? ' (' . $creditNeeded . ')' : ''),
                    $creditNeeded === null || ($d['credit'] ?? null) === null
                        ? null
                        : (float) $d['credit'] >= $creditNeeded,
                );
                $check('Nomor/tanggal PAK sah tersedia', empty($d['pakReference']) ? null : true);
                $requiredLevel = $bands[$nextGrade ?? ''][0] ?? null;
                $check(
                    'Jenjang sesuai pangkat tujuan' .
                        ($requiredLevel ? ' (' . $requiredLevel . ')' : ''),
                    $level && $requiredLevel ? $level === $requiredLevel : null,
                );
                $verify(
                    'functionalVerified',
                    'Kesesuaian jabatan dan persyaratan fungsional telah diperiksa',
                );
                $cumulativeNeeded = ['III/b' => 100, 'III/d' => 200, 'IV/c' => 450][$grade] ?? null;
                if ($cumulativeNeeded !== null) {
                    $check(
                        'AK kumulatif untuk naik jenjang mencukupi (' . $cumulativeNeeded . ')',
                        ($d['cumulativeCredit'] ?? null) === null
                            ? null
                            : (float) $d['cumulativeCredit'] >= $cumulativeNeeded,
                    );
                }
            }
        }
        $performance = ['Buruk' => 0, 'Kurang' => 1, 'Cukup' => 2, 'Baik' => 3, 'Sangat Baik' => 4];
        $year = $target?->year ?? $now->year;
        foreach ([1 => $year - 2, 2 => $year - 1] as $slot => $y) {
            $value = $d['performance' . $slot] ?? null;
            $recordYear = $d['performanceYear' . $slot] ?? null;
            $check(
                'Kinerja tahun ' . $y . ' minimal ' . ($kind === 'kgb' ? 'Cukup' : 'Baik'),
                !$value || (int) $recordYear !== $y
                    ? null
                    : ($performance[$value] ?? -1) >= ($kind === 'kgb' ? 2 : 3),
            );
        }
        $check(
            'Referensi dokumen pemeriksaan tersedia',
            empty($d['documentReference']) ? null : true,
        );
        $verify('documentsVerified', 'Dokumen dan seluruh syarat relevan telah diperiksa');
        if (isset($d['employeeRevision']) && $d['employeeRevision'] !== ($e['revision'] ?? 0)) {
            $check('Data Master berubah; verifikasi radar perlu diperbarui', null);
        }
        $open = null;
        $close = null;
        if ($kind === 'kp' && $target) {
            $open = $target->subMonthsNoOverflow(2)->day(16);
            $close = $target->subMonthNoOverflow()->day(15);
        }
        if ($kind === 'kp' && !empty($d['deadline'])) {
            $close = Date::parse($d['deadline']);
            $check(
                'Surat acuan tenggat khusus tersedia',
                empty($d['scheduleReference']) ? null : true,
            );
        }
        if ($close) {
            $check('Tenggat usulan belum lewat', $now->lte($close));
        }
        $status = $failed ? 'Belum memenuhi' : ($missing ? 'Data belum lengkap' : 'Siap diusulkan');
        $remind = $target?->subMonthsNoOverflow(2);
        return [
            'status' => $status,
            'checks' => $checks,
            'reasons' => array_merge($failed, $missing),
            'targetDate' => $target?->toDateString(),
            'earliestDate' => $earliest?->toDateString(),
            'nextGrade' => $nextGrade,
            'level' => $level,
            'creditNeeded' => $creditNeeded,
            'cumulativeNeeded' => $cumulativeNeeded,
            'reminderDate' => $remind?->toDateString(),
            'reminderActive' =>
                $remind &&
                $now->gte($remind) &&
                !in_array($d['process'] ?? 'Belum diusulkan', ['Keputusan terbit', 'Ditolak']),
            'submissionOpen' => $open?->toDateString(),
            'deadline' => $close?->toDateString(),
            'performanceYears' => [$year - 2, $year - 1],
            'asOf' => $now->toDateString(),
            'version' => self::VERSION,
        ];
    }
}
