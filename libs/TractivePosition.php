<?php

declare(strict_types=1);

namespace OpenWoIst;

final class TractivePosition
{
    public static function record(array $pending, string $field, mixed $value): array
    {
        if ($field === 'timestamp') {
            // A second report has started before the previous LastUpdate. Ignore both cycles.
            return $pending === [] ? ['cycles' => 1, 'timestamp' => is_int($value) ? $value : null]
                : ['cycles' => $pending['cycles'] + 1, 'invalid' => true];
        }
        if ($pending === [] || ($pending['invalid'] ?? false)) {
            return $pending;
        }

        $expected = match (true) {
            !array_key_exists('latitude', $pending)  => 'latitude',
            !array_key_exists('longitude', $pending) => 'longitude',
            default                                  => 'horizontalAccuracy'
        };
        if ($field !== $expected || array_key_exists($field, $pending)) {
            return ['cycles' => $pending['cycles'], 'invalid' => true];
        }
        $pending[$field] = (is_int($value) || is_float($value)) && is_finite($value) ? $value : null;
        return $pending;
    }

    public static function completed(array $pending, int $now): ?array
    {
        if (($pending['cycles'] ?? 0) !== 1 || ($pending['invalid'] ?? false)) {
            return null;
        }
        $fix = LocationSnapshot::fix($pending, $now);
        if ($fix === null) {
            return null;
        }
        if ($fix['accuracy'] !== null) {
            $fix['horizontalAccuracy'] = $fix['accuracy'];
        }
        unset($fix['accuracy']);
        return $fix;
    }
}
