<?php

declare(strict_types=1);

final class FixtureAddressMap extends OpenWoIst
{
    public array $nominatimRequests = [];
    public int $geoapifyRequests = 0;

    protected function LoadConfigurationForm(): array
    {
        return json_decode(file_get_contents(__DIR__ . '/../OpenWoIst/form.json'), true, 64, JSON_THROW_ON_ERROR);
    }

    protected function RequestNominatim(string $url): array
    {
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
        $this->nominatimRequests[] = (int) $query['zoom'];
        return ['status' => 200, 'body' => '{"address":{"road":"Test Street","house_number":"7","postcode":"10000","city":"Test City","country":"Test Country"}}'];
    }

    protected function RequestGeoapify(string $url): array
    {
        $this->geoapifyRequests++;
        return ['status' => 200, 'body' => '{"results":[{"street":"Test Street","housenumber":"7","city":"Test City","country":"Test Country"}]}'];
    }
}

$modules = $metadata->getValue();
$modules[$mapModule]['Class'] = FixtureAddressMap::class;
$metadata->setValue(null, $modules);
$detailsID = IPS_CreateInstance($mapModule);
$detailsMap = IPS\InstanceManager::getInstanceInterface($detailsID);
$modules[$mapModule]['Class'] = OpenWoIst::class;
$metadata->setValue(null, $modules);

$positionID = IPS_CreateVariable(VARIABLETYPE_STRING);
$measurement = time() - 75;
$fix = ['latitude' => 52.5, 'longitude' => 13.39, 'timestamp' => $measurement, 'horizontalAccuracy' => 8];
$setLocation = static function (array $position) use ($detailsMap, $positionID): void
{
    SetValueString($positionID, json_encode($position, JSON_THROW_ON_ERROR));
    $detailsMap->Update();
};
$detailsPhone = array_replace($phone, ['location' => array_replace($fix, ['longitude' => 13.3901])]);
$account->cache = ['success' => true, 'state' => 'authenticated', 'updatedAt' => time(), 'devices' => [$detailsPhone]];
$setLocation($fix);
IPS_SetProperty($detailsID, 'Accounts', json_encode([['AccountID' => $accountID]], JSON_THROW_ON_ERROR));
IPS_SetProperty($detailsID, 'VehicleMode', 'json');
IPS_SetProperty($detailsID, 'VehiclePosition', $positionID);
IPS_SetProperty($detailsID, 'EnableIPSView', true);
IPS_SetProperty($detailsID, 'NominatimServer', 'https://nominatim.example.invalid');
IPS_SetProperty($detailsID, 'ZoneConfirmationTime', 15);
$home = ['name' => 'Test Home', 'latitude' => 52.5, 'longitude' => 13.39, 'radius' => 100];
IPS_SetProperty($detailsID, 'Zones', json_encode([$home, array_replace($home, ['name' => 'Overlapping zone'])], JSON_THROW_ON_ERROR));
IPS_ApplyChanges($detailsID);

$vehicle = static fn (): array => source($detailsMap, 'vehicle')['objects'][0];
$detailsMap->ResolveAddresses();
check($detailsMap->nominatimRequests === [] && !isset($vehicle()['address']), 'Address lookups require explicit opt-in.');
check($vehicle()['zone'] === 'Test Home' && count(tileState($detailsMap)['zones']) === 2, 'The first matching zone has priority and both circles are published.');
IPS_SetProperty($detailsID, 'EnableAddressResolution', true);
IPS_ApplyChanges($detailsID);
$detailsMap->GetVisualizationTile();
check($detailsMap->nominatimRequests === [], 'Applying settings and rendering the map perform no HTTP.');
$detailsMap->ResolveAddresses();
$detailsMap->ResolveAddresses();
$detailsMap->ResolveAddresses();
check($detailsMap->nominatimRequests === [18, 18] && str_contains($vehicle()['address'], 'Test Street 7') && !$vehicle()['addressApproximate'], 'The background worker resolves one uncached position per call and reuses its precise cache.');

$outside = array_replace($fix, ['longitude' => 13.394, 'timestamp' => $measurement + 20]);
$setLocation($outside);
check($vehicle()['zone'] === 'Test Home' && $vehicle()['zonePending'] && !isset($vehicle()['address']), 'Moving starts a pending zone change and drops the previous coordinate address.');
$detailsMap->Update();
check($vehicle()['zonePending'], 'An unchanged measurement cannot confirm a zone transition.');
$outside['timestamp'] += 20;
$setLocation($outside);
check($vehicle()['zone'] === '' && !isset($vehicle()['zonePending']), 'A second fresh observation confirms the exit.');
$outside['timestamp']++;
unset($outside['horizontalAccuracy']);
$setLocation($outside);
$detailsMap->ResolveAddresses();
check(array_slice($detailsMap->nominatimRequests, -1) === [10] && $vehicle()['zone'] === null && $vehicle()['addressApproximate'] && !str_contains($vehicle()['address'], 'Test Street'), 'Unknown accuracy gives an approximate city and no confirmed zone.');

$access = bootstrap($detailsMap->GetIPSViewHTML())['options'];
$statePath = dirname($access['page']) . '/state';
check(mapRequest($detailsMap, $statePath)[0] === 403 && !str_contains(mapRequest($detailsMap, $access['page'])[1], 'Test Home'), 'The public IPSView page and unauthenticated state do not disclose zones or addresses.');
[$status, $body] = mapRequest($detailsMap, $statePath, token: $access['token']);
$state = json_decode($body, true, 64, JSON_THROW_ON_ERROR);
$tile = tileState($detailsMap);
check($status === 200 && $state['sources'] === $tile['sources'] && $state['zones'] === $tile['zones'] && !isset($state['zoneStates']), 'IPSView receives the same addresses and zones as the tile without evaluation internals.');

IPS_SetProperty($detailsID, 'AddressProvider', 'geoapify');
IPS_SetProperty($detailsID, 'GeoapifyAPIKey', 'synthetic-private-key');
IPS_SetProperty($detailsID, 'GeoapifyDailyLimit', 1);
IPS_ApplyChanges($detailsID);
$detailsMap->ResolveAddresses();
$detailsMap->ResolveAddresses();
check($detailsMap->geoapifyRequests === 1 && !str_contains($detailsMap->GetVisualizationTile(), 'synthetic-private-key'), 'The Geoapify daily budget is enforced and its key never enters HTML.');

IPS_SetProperty($detailsID, 'EnableAddressResolution', false);
IPS_SetProperty($detailsID, 'ZoneBoundaryBuffer', 150);
IPS_ApplyChanges($detailsID);
$detailsMap->ResolveAddresses();
$form = json_decode($detailsMap->GetConfigurationForm(), true, 64, JSON_THROW_ON_ERROR);
$zonePanel = array_column($form['elements'], null, 'name')['ZoneSettings'];
$error = array_column($zonePanel['items'], null, 'name')['ZoneConfigurationError'];
check($error['visible'] && tileState($detailsMap)['zones'] === [] && !array_key_exists('zone', $vehicle()) && !isset($vehicle()['address']), 'Invalid zones and disabled address resolution remove earlier details and keep the map available.');
check($detailsMap->geoapifyRequests === 1 && IPS_GetProperty($detailsID, 'VehiclePosition') === $positionID, 'No additional requests occur after disable and the source assignment is preserved.');
