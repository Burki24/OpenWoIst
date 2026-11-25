<?php

declare(strict_types=1);

$geofencyReceiver = '{0100CA50-D2DA-11E4-8830-0800200C9A66}';
$geofencyDummy = '{485D0419-BE97-4548-AA9C-C083EB82E61E}';
$modules = $metadata->getValue();
$modules[$geofencyReceiver] = array_replace($modules[$mapModule], ['Class' => TestPositionProvider::class, 'ModuleID' => $geofencyReceiver, 'Prefix' => 'TESTGEO']);
$modules[$geofencyDummy] = array_replace($modules[$mapModule], ['Class' => TestPositionProvider::class, 'ModuleID' => $geofencyDummy, 'Prefix' => 'TESTGEODEVICE']);
$metadata->setValue(null, $modules);

$geofencyMapID = IPS_CreateInstance($mapModule);
$geofencyMap = IPS\InstanceManager::getInstanceInterface($geofencyMapID);
IPS_SetProperty($geofencyMapID, 'EnableIPSView', true);
IPS_ApplyChanges($geofencyMapID);
$geofencyList = static fn (): array => array_column($providerPanels($geofencyMap)['GeofencyDevicesSection']['items'], null, 'name')['GeofencyDevices'];
check(!$geofencyList()['add'], 'Geofency waits for a receiver to create a device.');

$detached = IPS_CreateInstance($geofencyDummy);
$geofencyRows = $geofencyVariables = [];
foreach (['Test phone', 'Test tablet'] as $index => $name) {
    $receiver = IPS_CreateInstance($geofencyReceiver);
    $instance = IPS_CreateInstance($geofencyDummy);
    IPS_SetParent($instance, $receiver);
    IPS_SetName($instance, $name);
    $geofencyRows[] = ['SourceInstance' => $instance, 'SourceMarkerColor' => 0x225599];
    foreach (['Timestamp' => VARIABLETYPE_INTEGER, 'CurrentLatitude' => VARIABLETYPE_FLOAT, 'CurrentLongitude' => VARIABLETYPE_FLOAT, 'Latitude' => VARIABLETYPE_FLOAT, 'Longitude' => VARIABLETYPE_FLOAT] as $ident => $type) {
        $variable = IPS_CreateVariable($type);
        IPS_SetParent($variable, $instance);
        IPS_SetIdent($variable, $ident);
        $geofencyVariables[$index][$ident] = $variable;
    }
}
$geofencyRows[] = ['SourceInstance' => $detached];
$geofencySettings = json_encode($geofencyRows, JSON_THROW_ON_ERROR);
IPS_SetProperty($geofencyMapID, 'GeofencyDevices', $geofencySettings);
IPS_ApplyChanges($geofencyMapID);
$list = $geofencyList();
$selector = array_column($list['form'], null, 'name')['SourceInstance'];
$options = array_column($selector['options'], 'caption', 'value');
check($list['add'] && $selector['type'] === 'Select' && count($options) === 4
    && str_contains($options[$geofencyRows[0]['SourceInstance']], 'Test phone')
    && str_contains($options[$geofencyRows[1]['SourceInstance']], 'Test tablet')
    && $options[$detached] === 'Unavailable device (#' . $detached . ')'
    && IPS_GetProperty($geofencyMapID, 'GeofencyDevices') === $geofencySettings, 'The picker finds devices across receivers and does not rewrite an unavailable assignment.');

$geofencySources = static fn (): array => array_column(tileState($geofencyMap)['sources'], null, 'name');
$geofencyFix = static fn (string $name): ?array => $geofencySources()[$name]['objects'][0]['location'];
$embed = $geofencyMap->GetIPSViewHTML();
$access = bootstrap($embed)['options'];
$clock = new ReflectionProperty(IPS\TimeManager::class, 'time');
$previousClock = $clock->getValue();
$writeGeofency = static function (int $device, string $ident, int|float $value, int $at) use ($geofencyMap, $clock, $geofencyVariables): void
{
    $clock->setValue(null, $at);
    $variable = $geofencyVariables[$device][$ident];
    SetValue($variable, $value);
    $geofencyMap->MessageSink($at, $variable, VM_UPDATE, [$value]);
};
try {
    $received = $now - 20;
    $writeGeofency(0, 'Latitude', 48.0, $received);
    $writeGeofency(0, 'Longitude', 10.0, $received);
    $writeGeofency(0, 'Timestamp', $now - 100, $received);
    $writeGeofency(1, 'Timestamp', $now - 95, $received);
    $writeGeofency(0, 'CurrentLatitude', 51.0, $received);
    check($geofencyFix('Test phone') === null, 'A Geofency event waits for both current coordinates and ignores the geofence centre.');
    $writeGeofency(1, 'CurrentLatitude', 53.0, $received);
    $writeGeofency(0, 'CurrentLongitude', 8.0, $received + 1);
    $writeGeofency(1, 'CurrentLongitude', 9.0, $received + 1);
    $first = $geofencyFix('Test phone');
    $tabletFix = $geofencyFix('Test tablet');
    check($first === ['latitude' => 51.0, 'longitude' => 8.0, 'timestamp' => $now - 100, 'accuracy' => null]
        && $tabletFix['longitude'] === 9.0 && $tabletFix['timestamp'] === $now - 95, 'Interleaved devices use their own coordinates and event timestamps.');

    $writeGeofency(0, 'Timestamp', $now - 80, $received + 3);
    $writeGeofency(0, 'CurrentLatitude', 0.0, $received + 3);
    $writeGeofency(0, 'CurrentLongitude', 0.0, $received + 3);
    $writeGeofency(0, 'Timestamp', $now - 70, $received + 4);
    $writeGeofency(0, 'CurrentLongitude', 8.5, $received + 4);
    $writeGeofency(0, 'Timestamp', $now - 65, $received + 5);
    $writeGeofency(0, 'CurrentLatitude', 51.25, $received + 5);
    $writeGeofency(0, 'CurrentLongitude', 8.75, $received + 7);
    check($geofencyFix('Test phone') === $first, 'Missing coordinates, the zero pair and delayed updates retain the last Geofency position.');

    $writeGeofency(0, 'Timestamp', $now - 60, $received + 8);
    $writeGeofency(0, 'CurrentLatitude', 51.25, $received + 8);
    $writeGeofency(0, 'CurrentLongitude', 8.75, $received + 8);
    check($geofencyFix('Test phone') === ['latitude' => 51.25, 'longitude' => 8.75, 'timestamp' => $now - 60, 'accuracy' => null]
        && $geofencyFix('Test tablet') === $tabletFix && $geofencySources()['Test phone']['objects'][0]['markerColor'] === 0x225599, 'The next complete event moves only the reporting device.');
} finally {
    $clock->setValue(null, $previousClock);
}
[$status, $body] = mapRequest($geofencyMap, dirname($access['page']) . '/state', token: $access['token']);
check($status === 200 && json_decode($body, true, 32, JSON_THROW_ON_ERROR)['sources'] === tileState($geofencyMap)['sources']
    && $embed === $geofencyMap->GetIPSViewHTML(), 'Kachel and IPSView share Geofency positions and retain the same embedding.');
