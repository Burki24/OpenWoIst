<?php

declare(strict_types=1);

namespace OpenWoIst;

final class OrderedPositionUpdates
{
    private const MAX_UPDATE_SPAN_SECONDS = 1;

    /** The variable indexes always represent latitude, longitude and measurement time. */
    public static function receive(array $pending, int $field, mixed $value, array $variables, array $order): array
    {
        $variable = $variables[$field];
        $updated = $variable['VariableUpdated'];
        $started = $pending['started'] ?? $updated;
        if ($updated < $started || $updated - $started > self::MAX_UPDATE_SPAN_SECONDS || ($field === 2 && $order[0] === 2)) {
            $pending = [];
            $started = $updated;
        }

        $updates = $pending['updates'] ?? [];
        $complete = $field === $order[2];
        if ($updated <= 0 || ($order[count($updates)] ?? null) !== $field
            || ($pending !== [] && !isset($pending['updates'])) || !self::matches($field, $value, $variable['VariableValue'])) {
            return [$complete ? [] : ['started' => $started], null];
        }

        $updates[$field] = ['value' => $value, 'updated' => $updated];
        if (!$complete) {
            return [['started' => $started, 'updates' => $updates], null];
        }
        foreach ($updates as $index => $update) {
            if ($update['updated'] > $updated || $update['updated'] !== $variables[$index]['VariableUpdated']
                || !self::matches($index, $update['value'], $variables[$index]['VariableValue'])) {
                return [[], null];
            }
        }
        return [[], ['latitude' => $updates[0]['value'], 'longitude' => $updates[1]['value'], 'timestamp' => $updates[2]['value']]];
    }

    private static function matches(int $field, mixed $value, mixed $stored): bool
    {
        return $field === 2
            ? is_int($value) && $value === $stored
            : (is_int($value) || is_float($value)) && is_finite($value) && (float) $value === (float) $stored;
    }
}
