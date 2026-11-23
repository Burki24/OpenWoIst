<?php

declare(strict_types=1);

namespace OpenWoIst;

use Burki24\SymconModuleHelper\GeofenceHelper;
use InvalidArgumentException;

trait LocationZones
{
    use GeofenceHelper;

    private function registerLocationZones(): void
    {
        $this->RegisterPropertyString('Zones', '[]');
        $this->RegisterPropertyInteger('ZoneBoundaryBuffer', 25);
        $this->RegisterPropertyInteger('ZoneMaxAccuracy', 100);
        $this->RegisterPropertyInteger('ZoneConfirmationTime', 30);
    }

    private function zoneConfiguration(int $maxAge): array
    {
        $rows = json_decode($this->ReadPropertyString('Zones'), true, 8);
        $settings = [
            'maxAge'         => $maxAge,
            'hysteresis'     => $this->ReadPropertyInteger('ZoneBoundaryBuffer'),
            'maxAccuracy'    => $this->ReadPropertyInteger('ZoneMaxAccuracy'),
            'confirmSeconds' => $this->ReadPropertyInteger('ZoneConfirmationTime')
        ];
        if ($rows === []) {
            return [[], $settings];
        }
        if (!is_array($rows) || !array_is_list($rows)) {
            throw new InvalidArgumentException('Invalid zones.');
        }
        $zones = [];
        foreach ($rows as $index => $row) {
            $zones[] = [
                'id'        => 'zone' . $index,
                'name'      => $row['name'] ?? null,
                'latitude'  => $row['latitude'] ?? null,
                'longitude' => $row['longitude'] ?? null,
                'radius'    => $row['radius'] ?? null
            ];
        }
        $zones = $this->NormalizeGeofenceZones($zones);
        $this->ResolveGeofenceZoneSettings($zones, $settings);
        return [$zones, $settings];
    }

    private function evaluateLocationZones(array &$sources, array $previous, int $now, int $maxAge, string $sourceScope): array
    {
        try {
            [$zones, $settings] = $this->zoneConfiguration($maxAge);
        } catch (InvalidArgumentException) {
            $zones = [];
        }
        $scope = $zones === [] ? '' : hash('sha256', $this->EncodeVisualizationHTMLJSON([$sourceScope, $zones, $settings]));
        $priorStates = $scope === ($previous['zoneScope'] ?? null) ? ($previous['zoneStates'] ?? []) : [];
        $states = [];
        $names = array_column($zones, 'name', 'id');
        foreach ($sources as &$source) {
            foreach ($source['objects'] as &$object) {
                unset($object['zone'], $object['zonePending']);
                if ($zones === []) {
                    continue;
                }
                $location = $source['available'] && $object['current'] ? $object['location'] : null;
                $id = $object['id'];
                $result = $this->EvaluateGeofence($zones, $zones[0]['id'], $location, $priorStates[$id] ?? [], $now, $settings);
                $states[$id] = $result['state'];
                $zone = $result['zone'];
                $object['zone'] = $zone === null || $zone === '' ? $zone : $names[$zone];
                if ($result['pending']) {
                    $object['zonePending'] = true;
                }
            }
            unset($object);
        }
        unset($source);
        return ['zones' => $zones, 'zoneScope' => $scope, 'zoneStates' => $states];
    }
}
