<?php
namespace App\Modules\Rules\Services;
/** User-approved administrative reminders; never an award of entitlement. */
final class CalculationGate
{
    public function explain(string $kind): array
    {
        if (!in_array($kind, ['DUK', 'KP', 'KGB'], true)) {
            throw new \InvalidArgumentException('Unsupported calculation kind');
        }
        if ($kind === 'DUK') {
            return [
                'kind' => 'DUK',
                'status' => 'MANUAL_ORDER',
                'version' => 1,
                'official_order' => null,
                'due_date' => null,
                'message' =>
                    'Urutan mengikuti data sumber dan dapat diubah secara manual oleh Super Admin melalui Edit DUK.',
            ];
        }
        return [
            'kind' => $kind,
            'status' => 'ACTIVE_REMINDER',
            'version' => 1,
            'official_order' => null,
            'due_date' => null,
            'message' =>
                'Radar PNS berdasarkan NADI-ASN-Rules-KP dan KGB.md, versi 2026-09-22.1. Kesiapan memerlukan pemeriksaan dokumen pengelola; bukan penetapan hak.',
        ];
    }
}
