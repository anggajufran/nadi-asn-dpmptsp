<?php
namespace App\Modules\Personnel\Services;
use Carbon\CarbonImmutable;
final class PersonnelClock
{
    public static function service($years, $months, ?string $reference, ?string $asOf = null): array
    {
        $now = CarbonImmutable::parse($asOf ?? today()->toDateString());
        if (
            $years === null ||
            $months === null ||
            !preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $reference ?? '')
        ) {
            return [$years, $months, $reference];
        }
        $base = CarbonImmutable::parse($reference . '-01');
        $elapsed = max(0, ($now->year - $base->year) * 12 + $now->month - $base->month);
        // Keep source anomalies visible until the officer corrects them.
        if ($months < 0 || $months > 11) {
            return [$years, $months, $reference];
        }
        $total = (int) $years * 12 + (int) $months + $elapsed;
        return [intdiv($total, 12), $total % 12, $now->format('Y-m')];
    }
    public static function age(?string $birth, ?string $asOf = null): ?int
    {
        if (!$birth) {
            return null;
        }
        $now = CarbonImmutable::parse($asOf ?? today()->toDateString());
        $dob = CarbonImmutable::parse($birth);
        if ($dob->isAfter($now)) {
            return null;
        }
        return (int) $dob->diffInYears($now);
    }
}
