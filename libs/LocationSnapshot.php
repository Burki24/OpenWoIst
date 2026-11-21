<?php

declare(strict_types=1);

namespace OpenWoIst;

final class LocationSnapshot
{
    public static function key(string $kind, int $sourceID, string $objectID = ''): string
    {
        return hash('sha256', $kind . ':' . $sourceID . ':' . $objectID);
    }

    public static function fix(mixed $input, int $now): ?array
    {
        if (!is_array($input)) {
            return null;
        }
        $latitude = $input['latitude'] ?? null;
        $longitude = $input['longitude'] ?? null;
        $timestamp = $input['timestamp'] ?? null;
        if (!self::number($latitude) || !self::number($longitude) || abs($latitude) > 90 || abs($longitude) > 180
            || !is_int($timestamp) || $timestamp <= 0 || $timestamp > $now + 300) {
            return null;
        }
        $accuracy = $input['horizontalAccuracy'] ?? null;
        return [
            'latitude'  => (float) $latitude,
            'longitude' => (float) $longitude,
            'timestamp' => $timestamp,
            'accuracy'  => self::number($accuracy) && $accuracy > 0 ? (float) $accuracy : null
        ];
    }

    public static function object(string $key, string $name, ?array $fix, ?array $previous, bool $current): array
    {
        if ($fix === null || ($previous !== null && $fix['timestamp'] < $previous['timestamp'])) {
            $fix = $previous;
            $current = false;
        }
        return ['id' => $key, 'name' => $name, 'location' => $fix, 'current' => $current];
    }

    private static function number(mixed $value): bool
    {
        return (is_int($value) || is_float($value)) && is_finite($value);
    }
}
