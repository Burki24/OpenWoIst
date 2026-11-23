<?php

declare(strict_types=1);

$number = static function (mixed $value, string $decimalSeparator): ?float
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
};

$measurementTime = static function (mixed $value, string $format): ?array
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
};

$convert = static function (array $arguments) use ($number, $measurementTime): array
{
    $source = $arguments['Source'] ?? '';
    $positionID = filter_var($arguments['PositionID'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $format = $arguments['TimeFormat'] ?? ($source === 'bmw-cardata' ? 'iso8601' : 'unix-seconds');
    $separator = $arguments['DecimalSeparator'] ?? '.';
    if (!in_array($source, ['bmw-cardata', 'owntracks'], true)
        || !in_array($format, ['iso8601', 'unix-seconds', 'unix-milliseconds'], true)
        || !in_array($separator, ['.', ','], true)
        || $positionID === false || !IPS_VariableExists($positionID)
        || IPS_GetVariable($positionID)['VariableType'] !== VARIABLETYPE_STRING) {
        return ['status' => 'ignored', 'reason' => 'invalid_arguments'];
    }

    $payload = $arguments['Payload'] ?? null;
    $data = is_string($payload) ? json_decode($payload, true, 32, JSON_BIGINT_AS_STRING) : null;
    if (!is_array($data)) {
        return ['status' => 'ignored', 'reason' => 'invalid_payload'];
    }

    $accuracy = null;
    if ($source === 'bmw-cardata') {
        $points = $data['data'] ?? $data;
        $prefix = 'vehicle.cabin.infotainment.navigation.currentLocation.';
        $latitude = $points[$prefix . 'latitude'] ?? [];
        $longitude = $points[$prefix . 'longitude'] ?? [];
        $lat = $latitude['value'] ?? null;
        $lon = $longitude['value'] ?? null;
        $time = $measurementTime($latitude['timestamp'] ?? null, $format);
        $longitudeTime = $measurementTime($longitude['timestamp'] ?? null, $format);
        if ($time !== $longitudeTime) {
            return ['status' => 'ignored', 'reason' => 'unpaired_measurement'];
        }
    } else {
        if (array_key_exists('_type', $data) && $data['_type'] !== 'location') {
            return ['status' => 'ignored', 'reason' => 'invalid_payload'];
        }
        $lat = $data['lat'] ?? null;
        $lon = $data['lon'] ?? null;
        $time = $measurementTime($data['tst'] ?? null, $format);
        $accuracy = $number($data['acc'] ?? null, $separator);
    }

    $lat = $number($lat, $separator);
    $lon = $number($lon, $separator);
    $now = time();
    if ($time === null || $time[0] <= 0 || $time[0] > $now + 300
        || $lat === null || abs($lat) > 90 || $lon === null || abs($lon) > 180) {
        return ['status' => 'ignored', 'reason' => 'invalid_position'];
    }

    $position = ['latitude' => $lat, 'longitude' => $lon, 'timestamp' => $time[0]];
    if ($accuracy !== null && $accuracy > 0) {
        $position['horizontalAccuracy'] = $accuracy;
    }

    $lock = 'OpenWoIst.PositionConverter.' . $positionID;
    if (!IPS_SemaphoreEnter($lock, 1000)) {
        return ['status' => 'ignored', 'reason' => 'target_busy'];
    }
    try {
        $previous = json_decode(GetValueString($positionID), true, 8);
        $timestamp = $previous['timestamp'] ?? null;
        if (is_int($timestamp) && $timestamp <= $now + 300 && $timestamp >= $position['timestamp']) {
            return ['status' => 'ignored', 'reason' => 'position_not_newer'];
        }
        if (!SetValueString($positionID, json_encode($position, JSON_THROW_ON_ERROR))) {
            return ['status' => 'ignored', 'reason' => 'write_failed'];
        }

        return ['status' => 'published'];
    } finally {
        IPS_SemaphoreLeave($lock);
    }
};

echo json_encode($convert($_IPS ?? []), JSON_THROW_ON_ERROR);
