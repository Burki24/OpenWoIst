<?php

declare(strict_types=1);

namespace OpenWoIst;

final class TessiePosition
{
    /** Match the two local variable updates in the order Tessie writes them. */
    public static function receive(array $pending, int $field, mixed $value, int $sequence, array $variables): array
    {
        $variable = $variables[$field];
        $updated = $variable['VariableUpdated'];
        if ((!is_int($value) && !is_float($value)) || !is_finite($value) || $updated <= 0 || (float) $value !== $variable['VariableValue']) {
            return [[], null];
        }
        if ($field === 0) {
            return [['latitude' => $value, 'timestamp' => $updated, 'sequence' => $sequence], null];
        }
        // Update times have second resolution. These variables expose no packet ID.
        $latitude = $variables[0];
        if (($pending['timestamp'] ?? null) !== $updated || $pending['sequence'] >= $sequence
            || $latitude['VariableUpdated'] !== $updated || $latitude['VariableValue'] !== (float) $pending['latitude']) {
            return [[], null];
        }
        return [[], ['latitude' => $pending['latitude'], 'longitude' => $value, 'timestamp' => $updated]];
    }
}
