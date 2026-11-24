<?php

declare(strict_types=1);

namespace OpenWoIst;

use DateTimeImmutable;

final class PositionConverter
{
    public static function supports(string $source): bool
    {
        return in_array($source, ['bmw-cardata', 'owntracks', 'traccar'], true);
    }

    public static function convert(string $source, array $data, string $format, string $separator): ?array
    {
        if ($format === 'default') {
            $format = $source === 'owntracks' ? 'unix-seconds' : 'iso8601';
        }
        if (!self::supports($source) || !in_array($separator, ['.', ','], true)
            || !in_array($format, ['iso8601', 'unix-seconds', 'unix-milliseconds'], true)) {
            return null;
        }

        $accuracy = null;
        if ($source === 'bmw-cardata') {
            $points = $data['data'] ?? $data['telematicData'] ?? $data;
            $prefix = 'vehicle.cabin.infotainment.navigation.currentLocation.';
            $latitude = $points[$prefix . 'latitude'] ?? [];
            $longitude = $points[$prefix . 'longitude'] ?? [];
            $lat = $latitude['value'] ?? null;
            $lon = $longitude['value'] ?? null;
            $time = self::measurementTime($latitude['timestamp'] ?? null, $format);
            if ($time !== self::measurementTime($longitude['timestamp'] ?? null, $format)) {
                $time = null;
            }
        } elseif ($source === 'owntracks') {
            if (array_key_exists('_type', $data) && $data['_type'] !== 'location') {
                return null;
            }
            $lat = $data['lat'] ?? null;
            $lon = $data['lon'] ?? null;
            $time = self::measurementTime($data['tst'] ?? null, $format);
            $accuracy = self::number($data['acc'] ?? null, $separator);
        } else {
            $position = $data['position'] ?? $data;
            if (!is_array($position) || (array_key_exists('valid', $position) && $position['valid'] !== true)) {
                return null;
            }
            $lat = $position['latitude'] ?? null;
            $lon = $position['longitude'] ?? null;
            $time = self::measurementTime($position['fixTime'] ?? null, $format);
            $accuracy = self::number($position['accuracy'] ?? null, $separator);
        }

        return [
            'latitude'           => self::number($lat, $separator),
            'longitude'          => self::number($lon, $separator),
            'timestamp'          => $time[0] ?? null,
            'horizontalAccuracy' => $accuracy
        ];
    }

    public static function convertVariables(string $source, array $position): array
    {
        $time = $position['timestamp'];
        $position['timestamp'] = match ($source) {
            'smartcar', 'volvo', 'renault-ze', 'tronity' => is_int($time) ? $time : null,
            'hyundai-kia'                                => self::localPositionTime($time),
            'fressnapf'                                  => self::measurementTime($time, 'iso8601')[0] ?? null,
            default                                      => null
        };
        return $position;
    }

    /** Returns Unix seconds and the fractional part for exact measurement ordering. */
    public static function measurementTime(mixed $value, string $format): ?array
    {
        if ($format === 'iso8601') {
            if (!is_string($value) || preg_match(
                '/^(\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2})(?:\.(\d{1,9}))?(Z|[+-](?:[01]\d|2[0-3]):?[0-5]\d)$/D',
                $value,
                $parts
            ) !== 1) {
                return null;
            }
            $date = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i:sP', $parts[1] . $parts[3]);
            if ($date === false || DateTimeImmutable::getLastErrors() !== false) {
                return null;
            }

            return [$date->getTimestamp(), str_pad($parts[2], 9, '0')];
        }

        if (!is_int($value) && !is_string($value)) {
            return null;
        }
        $integer = filter_var($value, FILTER_VALIDATE_INT);
        if ($integer === false || $integer <= 0) {
            return null;
        }

        return $format === 'unix-milliseconds'
            ? [intdiv($integer, 1000), str_pad((string) ($integer % 1000), 3, '0', STR_PAD_LEFT) . '000000']
            : [$integer, '000000000'];
    }

    private static function localPositionTime(mixed $value): ?int
    {
        if (!is_string($value) || preg_match('/^[0-9]{14}$/D', $value) !== 1) {
            return null;
        }
        $date = DateTimeImmutable::createFromFormat('!YmdHis', $value);
        if ($date === false || DateTimeImmutable::getLastErrors() !== false || $date->format('YmdHis') !== $value) {
            return null;
        }

        $timestamp = $date->getTimestamp();
        // A local clock time in the repeated interval identifies two different instants.
        $transitions = $date->getTimezone()->getTransitions($timestamp - 86400, $timestamp + 86400);
        if (isset($transitions[1])) {
            $change = $transitions[1]['ts'];
            $repeated = $transitions[0]['offset'] - $transitions[1]['offset'];
            if ($timestamp >= $change - $repeated && $timestamp < $change + $repeated) {
                return null;
            }
        }
        return $timestamp;
    }

    private static function number(mixed $value, string $decimalSeparator): ?float
    {
        if (is_string($value)) {
            $separator = preg_quote($decimalSeparator, '/');
            $value = trim($value);
            if (preg_match('/^[+-]?(?:\d+(?:' . $separator . '\d*)?|' . $separator . '\d+)(?:[eE][+-]?\d+)?$/D', $value) !== 1) {
                return null;
            }
            $value = (float) str_replace($decimalSeparator, '.', $value);
        }

        return (is_int($value) || is_float($value)) && is_finite($value) ? (float) $value : null;
    }
}
