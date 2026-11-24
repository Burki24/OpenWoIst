<?php

declare(strict_types=1);

namespace OpenWoIst;

final class FressnapfPosition
{
    private const MAX_REPORT_SECONDS = 3;

    /** Returns the pending variable updates and any completed position. */
    public static function receive(array $pending, int $field, mixed $value, int $observedAt): array
    {
        if ($pending !== [] && ($observedAt < $pending['started'] || $observedAt - $pending['started'] > self::MAX_REPORT_SECONDS)) {
            $pending = [];
        }
        if ($field === 2) {
            if (!array_key_exists('longitude', $pending) || ($pending['invalid'] ?? false)) {
                return [[], null];
            }
            return [[], PositionConverter::convertVariables('fressnapf', $pending + ['timestamp' => $value])];
        }
        if ($field === 0) {
            // The source writes latitude first, then longitude and finally the measurement time.
            return [$pending === [] ? ['started' => $observedAt, 'latitude' => $value] : $pending + ['invalid' => true], null];
        }
        if ($pending === [] || ($pending['invalid'] ?? false)) {
            return [$pending, null];
        }
        if ($field === 1 && !array_key_exists('longitude', $pending)) {
            return [$pending + ['longitude' => $value], null];
        }
        if ($field === 3 && array_key_exists('longitude', $pending) && !array_key_exists('horizontalAccuracy', $pending)) {
            return [$pending + ['horizontalAccuracy' => $value], null];
        }
        return [$pending + ['invalid' => true], null];
    }
}
