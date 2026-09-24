<?php
// Calculates synthetic fixture results with the same PHP engine. No database or session access.
require __DIR__ . '/../vendor/autoload.php';
date_default_timezone_set('Asia/Makassar');
$input = json_decode(stream_get_contents(STDIN), true, 512, JSON_THROW_ON_ERROR);
$calc = new App\Modules\Radar\Services\RadarCalculator();
echo json_encode(
    array_map(
        fn($row) => [
            ...$row,
            'result' => $calc->calculate(
                $row['employee'],
                $input['kind'],
                $row['data'],
                date('Y-m-d'),
            ),
        ],
        $input['rows'],
    ),
    JSON_THROW_ON_ERROR,
);
