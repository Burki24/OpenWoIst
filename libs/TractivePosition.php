<?php

declare(strict_types=1);

namespace OpenWoIst;

final class TractivePosition
{
    public const DEVICE_MODULE = '{A259E80D-C7B4-F5A9-F82B-B9B05F71B4F3}';
    public const OBJECT_MESSAGES = [OM_CHILDADDED, OM_CHILDREMOVED, OM_UNREGISTER];

    private const MAX_CYCLE_SECONDS = 15;
    private const VARIABLES = [
        'timestamp'          => ['LastPositionMessage', VARIABLETYPE_INTEGER],
        'latitude'           => ['LastLatitude', VARIABLETYPE_FLOAT],
        'longitude'          => ['LastLongitude', VARIABLETYPE_FLOAT],
        'horizontalAccuracy' => ['PositionUncertainty', VARIABLETYPE_FLOAT],
        'completed'          => ['LastUpdate', VARIABLETYPE_INTEGER]
    ];

    public static function isDevice(int $id): bool
    {
        return $id > 0 && IPS_InstanceExists($id) && IPS_GetInstance($id)['ModuleInfo']['ModuleID'] === self::DEVICE_MODULE;
    }

    public static function variables(int $tracker): array
    {
        $fields = [];
        foreach (self::VARIABLES as $field => [$ident, $type]) {
            $id = (int) @IPS_GetObjectIDByIdent($ident, $tracker);
            if ($id > 0 && IPS_VariableExists($id) && IPS_GetVariable($id)['VariableType'] === $type) {
                $fields[$field] = $id;
            } elseif ($field !== 'horizontalAccuracy') {
                return [];
            }
        }
        return $fields;
    }

    /** Returns the pending state, the accepted fix and whether a report reached LastUpdate. */
    public static function receive(array $pending, string $field, mixed $value, int $observedAt, int $now): array
    {
        $started = $pending['started'] ?? $observedAt;
        if ($observedAt < $started || $observedAt - $started > self::MAX_CYCLE_SECONDS) {
            $pending = [];
            $started = $observedAt;
        }
        if ($field !== 'completed') {
            $pending = self::record($pending, $field, $value);
            return [$pending === [] ? [] : $pending + ['started' => $started], null, false];
        }
        $remaining = ($pending['cycles'] ?? 0) - 1;
        $next = $remaining > 0 ? ['cycles' => $remaining, 'invalid' => true, 'started' => $started] : [];
        return [$next, self::completed($pending, $now), $pending !== []];
    }

    private static function record(array $pending, string $field, mixed $value): array
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

    private static function completed(array $pending, int $now): ?array
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
