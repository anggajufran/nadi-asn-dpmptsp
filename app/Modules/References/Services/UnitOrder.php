<?php
namespace App\Modules\References\Services;
final class UnitOrder
{
    public static function sort(iterable $rows): array
    {
        $labels = json_decode(file_get_contents(resource_path('js/domain/unit-order.json')), true);
        $normalize = fn($s) => mb_strtoupper(preg_replace('/\s+/u', ' ', trim($s)));
        $ranks = array_flip(array_map($normalize, $labels));
        return collect($rows)
            ->sort(function ($a, $b) use ($ranks, $normalize) {
                $a = $normalize(data_get($a, 'label'));
                $b = $normalize(data_get($b, 'label'));
                return ($ranks[$a] ?? 999) <=> ($ranks[$b] ?? 999) ?: strnatcasecmp($a, $b);
            })
            ->values()
            ->all();
    }
}
