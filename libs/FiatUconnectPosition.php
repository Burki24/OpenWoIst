<?php

declare(strict_types=1);

namespace OpenWoIst;

final class FiatUconnectPosition
{
    private const MAX_UPDATE_SPAN_SECONDS = 1;

    /** A Fiat report writes latitude, longitude and then the measurement time. */
    public static function receive(array $pending, int $field, mixed $value, array $variables): array
    {
        $variable = $variables[$field];
        $updated = $variable['VariableUpdated'];
        $started = $pending['started'] ?? $updated;
        if ($updated < $started || $updated - $started > self::MAX_UPDATE_SPAN_SECONDS) {
            $pending = [];
            $started = $updated;
        }

        $coordinates = $pending['coordinates'] ?? [];
        if ($field === 2) {
            if (!is_int($value) || $value !== $variable['VariableValue'] || count($coordinates) !== 2) {
                return [[], null];
            }
            foreach ($coordinates as $index => $coordinate) {
                if ($coordinate['updated'] > $updated || $coordinate['updated'] !== $variables[$index]['VariableUpdated']
                    || (float) $coordinate['value'] !== (float) $variables[$index]['VariableValue']) {
                    return [[], null];
                }
            }
            return [[], ['latitude' => $coordinates[0]['value'], 'longitude' => $coordinates[1]['value'], 'timestamp' => $value]];
        }

        if ($updated > 0 && (is_int($value) || is_float($value)) && is_finite($value) && (float) $value === (float) $variable['VariableValue']
            && ($pending === [] || isset($pending['coordinates'])) && $field === count($coordinates)) {
            $coordinates[] = ['value' => $value, 'updated' => $updated];
            return [['started' => $started, 'coordinates' => $coordinates], null];
        }
        // An unexpected or repeated coordinate invalidates this sequence until the timestamp arrives.
        return [['started' => $started], null];
    }
}
