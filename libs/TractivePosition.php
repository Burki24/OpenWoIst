<?php

declare(strict_types=1);

namespace OpenWoIst;

final class TractivePosition
{
    public static function fromResponse(string $response, string $tracker, int $now): ?array
    {
        $reports = json_decode($response, true, 32);
        if (!is_array($reports) || !array_is_list($reports)) {
            return null;
        }

        $latest = null;
        foreach ($reports as $report) {
            if (!is_array($report) || ($report['_type'] ?? null) !== 'device_pos_report' || ($report['_id'] ?? null) !== $tracker) {
                continue;
            }
            $coordinates = $report['latlong'] ?? null;
            if (!is_array($coordinates) || !array_is_list($coordinates) || count($coordinates) !== 2) {
                continue;
            }
            $fix = LocationSnapshot::fix([
                'latitude'  => $coordinates[0],
                'longitude' => $coordinates[1],
                'timestamp' => $report['time'] ?? null
            ], $now);
            if ($fix !== null && ($latest === null || $fix['timestamp'] > $latest['timestamp'])) {
                unset($fix['accuracy']);
                $latest = $fix;
            }
        }

        return $latest;
    }
}
