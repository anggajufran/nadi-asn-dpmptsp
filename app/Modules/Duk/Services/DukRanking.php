<?php
namespace App\Modules\Duk\Services;
use App\Shared\CanonicalJson;
/** Institution ordering explicitly requested on 2026-09-15; not a claim of legal certification. */
final class DukRanking
{
    public const VERSION = 'DPMPTSP-2026-09-15-v1';
    public const LABELS = [
        'Prioritas Kepala Dinas/Sekretaris',
        'Pangkat / golongan',
        'Jabatan',
        'MKG',
        'Tingkat diklat',
        'Tanggal diklat',
        'Pendidikan',
        'Tahun kelulusan',
        'Usia',
    ];
    public const POSITIONS = [
        'JPT Utama' => 140,
        'JPT Madya' => 130,
        'JPT Pratama' => 120,
        'Administrator' => 110,
        'Pengawas' => 100,
        'Fungsional Utama' => 80,
        'Fungsional Madya' => 70,
        'Fungsional Muda' => 60,
        'Fungsional Pertama' => 50,
        'Fungsional Penyelia' => 40,
        'Fungsional Mahir' => 30,
        'Fungsional Terampil' => 20,
        'Fungsional Pemula' => 10,
        'Pelaksana' => 0,
    ];
    public const TRAINING = [
        'Belum pernah' => 0,
        'PIM IV / PKP' => 1,
        'PIM III / PKA' => 2,
        'PIM II / PKN II' => 3,
        'PIM I / PKN I' => 4,
    ];
    private function date(?string $s): bool
    {
        return $s &&
            preg_match('/^\d{4}-\d{2}-\d{2}$/', $s) &&
            \DateTimeImmutable::createFromFormat('!Y-m-d', $s)?->format('Y-m-d') === $s;
    }
    public function position(array $e): ?string
    {
        if (!empty($e['dukPositionLevel'])) {
            return array_key_exists($e['dukPositionLevel'], self::POSITIONS)
                ? $e['dukPositionLevel']
                : null;
        }
        $p = mb_strtolower(trim($e['position'] ?? ''));
        if (preg_match('/^kepala dinas(?:\s|$)/u', $p)) {
            return 'JPT Pratama';
        }
        if (preg_match('/^sekretaris(?: dinas)?$/', $p)) {
            return 'Administrator';
        }
        if (
            str_contains($p, 'kasubag') ||
            str_contains($p, 'kepala subbag') ||
            str_contains($p, 'kepala sub bagian')
        ) {
            return 'Pengawas';
        }
        if (($e['positionType'] ?? '') === 'Fungsional') {
            foreach (
                [
                    'utama' => 'Utama',
                    'madya' => 'Madya',
                    'muda' => 'Muda',
                    'pertama' => 'Pertama',
                    'penyelia' => 'Penyelia',
                    'mahir' => 'Mahir',
                    'terampil' => 'Terampil',
                    'pemula' => 'Pemula',
                ]
                as $word => $level
            ) {
                if (preg_match('/\b' . $word . '\b/u', $p)) {
                    return 'Fungsional ' . $level;
                }
            }
        }
        return ($e['positionType'] ?? '') === 'Pelaksana' ? 'Pelaksana' : null;
    }
    public function rank(array $input, string $asOf): array
    {
        $rows = [];
        $heads = [2 => 0, 1 => 0];
        foreach ($input as $e) {
            if (($e['type'] ?? '') !== 'PNS' || ($e['status'] ?? '') !== 'Aktif') {
                continue;
            }
            $issues = [];
            $p = mb_strtolower(trim($e['position'] ?? ''));
            $head = preg_match('/^kepala dinas(?:\s|$)/u', $p)
                ? 2
                : (preg_match('/^sekretaris(?: dinas)?$/', $p)
                    ? 1
                    : 0);
            if ($head) {
                $heads[$head]++;
            }
            $rank = null;
            $raw = $e['rankName'] ?? '' ?: $e['grade'] ?? '';
            if (preg_match('~(?:^|/)\s*(IV|III|II|I)\s*/?\s*([a-e])\s*$~i', $raw, $m)) {
                $major = ['I' => 1, 'II' => 2, 'III' => 3, 'IV' => 4][strtoupper($m[1])];
                $minor = ord(strtolower($m[2])) - 96;
                if ($minor <= 4 || $major === 4) {
                    $rank = $major * 10 + $minor;
                }
            }
            if ($rank === null) {
                $issues[] = 'Pangkat/golongan PNS belum valid';
            }
            $position = $this->position($e);
            if ($position === null) {
                $issues[] = 'Jenjang jabatan belum dikenali; pilih jenjang DUK';
            }
            $mkg = null;
            $mkgYears = null;
            $mkgMonths = null;
            $mkgDays = null;
            if ($this->date($e['rankTmt'] ?? null) && $e['rankTmt'] <= $asOf) {
                $elapsed = (new \DateTimeImmutable($e['rankTmt']))->diff(
                    new \DateTimeImmutable($asOf),
                );
                $mkg = $elapsed->days;
                $mkgYears = $elapsed->y;
                $mkgMonths = $elapsed->m;
                $mkgDays = $elapsed->d;
            } else {
                $issues[] = 'TMT golongan belum valid untuk menghitung MKG';
            }
            $training = self::TRAINING[$e['trainingLevel'] ?? ''] ?? null;
            $trainingDate = 0;
            if ($training === null) {
                $issues[] = 'Diklat belum dikonfirmasi (pilih Belum pernah bila tidak ada)';
            } elseif ($training > 0) {
                if (!$this->date($e['trainingDate'] ?? null) || $e['trainingDate'] > $asOf) {
                    $issues[] = 'Tanggal lulus diklat belum valid';
                } else {
                    $trainingDate = -(int) str_replace('-', '', $e['trainingDate']);
                }
            }
            $education =
                [
                    'SD' => 1,
                    'SMP' => 2,
                    'SMA' => 3,
                    'SMK' => 3,
                    'SMA/SMK' => 3,
                    'D1' => 4,
                    'D2' => 5,
                    'D3' => 6,
                    'D4' => 7,
                    'S1' => 7,
                    'S2' => 8,
                    'S3' => 9,
                ][str_replace([' ', '-'], '', strtoupper($e['education'] ?? ''))] ?? null;
            if ($education === null) {
                $issues[] = 'Pendidikan belum dikenali';
            }
            $grad = 0;
            if (!$this->date($e['graduationDate'] ?? null) || $e['graduationDate'] > $asOf) {
                $issues[] = 'Tanggal lulus pendidikan belum valid';
            } else {
                $grad = -(int) substr($e['graduationDate'], 0, 4);
            }
            $birth = 0;
            if (!$this->date($e['birthDate'] ?? null) || $e['birthDate'] > $asOf) {
                $issues[] = 'Tanggal lahir belum valid';
            } else {
                $birth = -(int) str_replace('-', '', $e['birthDate']);
            }
            $key = [
                $head,
                $rank ?? -1,
                self::POSITIONS[$position] ?? -1,
                $mkg ?? -1,
                $training ?? -1,
                $trainingDate,
                $education ?? -1,
                $grad,
                $birth,
            ];
            $rows[] = [
                ...$e,
                'mkgYears' => $mkgYears,
                'mkgMonths' => $mkgMonths,
                'mkgDays' => $mkgDays,
                'dukPositionResolved' => $position,
                'dukIssues' => $issues,
                '_key' => $key,
            ];
        }
        usort($rows, fn($a, $b) => $b['_key'] <=> $a['_key'] ?: strcmp($a['id'], $b['id']));
        $global = [];
        foreach ([2 => 'Kepala Dinas', 1 => 'Sekretaris'] as $k => $name) {
            if ($heads[$k] !== 1) {
                $global[] = "Harus ada tepat satu {$name} aktif untuk posisi " . (3 - $k) . '.';
            }
        }
        foreach ($rows as $i => &$row) {
            $same =
                ($i > 0 && $rows[$i - 1]['_key'] === $row['_key']) ||
                (isset($rows[$i + 1]) && $rows[$i + 1]['_key'] === $row['_key']);
            if ($same) {
                $row['dukIssues'][] =
                    'Seri pada seluruh kriteria; nomor resmi belum dapat ditetapkan';
            }
            $reason = $i === 0 ? 'Prioritas Kepala Dinas' : null;
            if ($i > 0) {
                foreach ($row['_key'] as $j => $v) {
                    if ($v !== $rows[$i - 1]['_key'][$j]) {
                        $reason = self::LABELS[$j];
                        break;
                    }
                }
            }
            $row['dukOrder'] = $i + 1;
            $row['dukReason'] = $same
                ? 'Seri; perlu penyelesaian'
                : ($row['_key'][0] === 1
                    ? 'Prioritas Sekretaris'
                    : $reason ?? 'Urutan pratinjau');
        }
        unset($row);
        $issueCount = count(array_filter($rows, fn($r) => count($r['dukIssues'])));
        $ready = count($rows) > 0 && !$global && !$issueCount;
        foreach ($rows as &$row) {
            unset($row['_key']);
            $row['dukRank'] = $ready ? $row['dukOrder'] : null;
        }
        unset($row);
        $result = [
            'policy' => self::VERSION,
            'asOf' => $asOf,
            'ready' => $ready,
            'total' => count($rows),
            'issueCount' => $issueCount,
            'issues' => $global,
            'criteria' => self::LABELS,
            'rows' => $rows,
        ];
        return [...$result, 'hash' => CanonicalJson::hash($result)];
    }
}
