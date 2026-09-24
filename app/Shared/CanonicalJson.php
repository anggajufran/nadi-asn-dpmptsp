<?php
namespace App\Shared;
final class CanonicalJson
{
    public static function encode(mixed $value): string
    {
        return json_encode(
            self::sort($value),
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        );
    }
    public static function hash(mixed $value): string
    {
        return hash('sha256', self::encode($value));
    }
    private static function sort(mixed $value): mixed
    {
        if (!is_array($value)) {
            return $value;
        }
        if (!array_is_list($value)) {
            ksort($value, SORT_STRING);
        }
        return array_map(self::sort(...), $value);
    }
}
