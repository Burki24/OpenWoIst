<?php

declare(strict_types=1);

final class TestPositionProvider extends IPSModuleStrict
{
}

$providerContracts = [
    'SmartcarVehicles'    => ['{1E1B7C9A-2D4F-4E8A-9C3B-7F6D5A4E2B10}', ['Latitude', 'Longitude', 'Sig_location_preciselocation_OEMUpdatedAt'], VARIABLETYPE_INTEGER],
    'HyundaiKiaVehicles'  => ['{C3D4E5F6-789A-BCDE-F012-3456789ABCDE}', ['Latitude', 'Longitude', 'PositionTimestamp'], VARIABLETYPE_STRING],
    'VolvoVehicles'       => ['{6C6B7979-37AA-69B7-2E19-7E10D92A97E3}', ['CurrentLatitude', 'CurrentLongitude', 'LastPositionMessage'], VARIABLETYPE_INTEGER],
    'RenaultZEVehicles'   => ['{C7AB2E48-7D3A-94B8-E4D6-137CFB91D821}', ['GPSLatitude', 'GPSLongitude', 'GPSUpdate'], VARIABLETYPE_INTEGER],
    'TronityVehicles'     => ['{D041B19E-6D70-A84F-B67C-4FF51CA38D3A}', ['Latitude', 'Longitude', 'Timestamp'], VARIABLETYPE_INTEGER],
    'OwnTracksDevices'    => ['{8398750C-51CE-51C8-97A9-9356BB11ABDD}', ['position'], VARIABLETYPE_STRING]
];
$positionPanelMapID = IPS_CreateInstance($mapModule);
$positionPanelMap = IPS\InstanceManager::getInstanceInterface($positionPanelMapID);
IPS_SetProperty($positionPanelMapID, 'EnableIPSView', true);
$providerPanels = static function (OpenWoIst $map): array
{
    $form = json_decode($map->GetConfigurationForm(), true, 64, JSON_THROW_ON_ERROR);
    $groups = array_column($form['elements'], null, 'name');
    return array_column([...$groups['VehicleSources']['items'], ...$groups['LocationDeviceSources']['items']], null, 'name');
};
$panels = $providerPanels($positionPanelMap);
$modules = $metadata->getValue();
foreach ($providerContracts as $property => [$guid]) {
    $modules[$guid] = array_replace($modules[$mapModule], ['Class' => TestPositionProvider::class, 'ModuleID' => $guid, 'Prefix' => 'TESTPROVIDER']);
    $list = array_column($panels[$property . 'Section']['items'], null, 'name')[$property];
    $columns = array_column($list['columns'], null, 'name');
    check($list['name'] === $property && $list['add'] && $list['delete']
        && $columns['SourceInstance']['edit']['validModules'] === [$guid]
        && $columns['SourceMarkerColor']['edit']['type'] === 'SelectColor', 'Every local provider has a multi-device form filtered by module ID.');
}
$metadata->setValue(null, $modules);

$reports = [];
foreach ($providerContracts as $property => [$guid, $idents, $timeType]) {
    $rows = [];
    // Two devices per module with matching names still produce distinct map objects.
    foreach ([0, 1] as $index) {
        $instance = IPS_CreateInstance($guid);
        IPS_SetName($instance, $property);
        $vars = [];
        $types = count($idents) === 1 ? [VARIABLETYPE_STRING] : [VARIABLETYPE_FLOAT, VARIABLETYPE_FLOAT, $timeType];
        foreach ($idents as $offset => $ident) {
            $variable = IPS_CreateVariable($types[$offset]);
            IPS_SetParent($variable, $instance);
            IPS_SetIdent($variable, $ident);
            $vars[] = $variable;
        }
        $rows[] = ['SourceInstance' => $instance, 'SourceMarkerColor' => $index === 0 ? 0x254499 : 0];
        $reports[] = [$vars, $timeType, $index];
    }
    IPS_SetProperty($positionPanelMapID, $property, json_encode($rows, JSON_THROW_ON_ERROR));
}

$bmwPrefix = 'vehicle.cabin.infotainment.navigation.currentLocation.';
$bmwPayload = static function (float $latitude, int $time) use ($bmwPrefix): array
{
    return [
        $bmwPrefix . 'latitude'  => ['value' => $latitude, 'timestamp' => gmdate('c', $time)],
        $bmwPrefix . 'longitude' => ['value' => 8.25, 'timestamp' => gmdate('c', $time)]
    ];
};
$bmwSource = IPS_CreateVariable(VARIABLETYPE_STRING);
$traccarSource = IPS_CreateVariable(VARIABLETYPE_STRING);
$tessieSource = IPS_CreateVariable(VARIABLETYPE_STRING);
$jsonSources = [
    'BMWCarDataVehicles' => ['BMW', $bmwSource],
    'TraccarDevices'     => ['Traccar', $traccarSource],
    'TessieVehicles'     => ['Tessie', $tessieSource]
];
foreach ($jsonSources as $property => [$name, $variable]) {
    check(isset($panels[$property . 'Section']), 'A dedicated form is available for ' . $name . '.');
    $list = array_column($panels[$property . 'Section']['items'], null, 'name')[$property];
    $inputs = array_column($list['form'], null, 'name');
    check($list['name'] === $property && $list['add'] && $inputs['SourcePosition']['validVariableTypes'] === [VARIABLETYPE_STRING]
        && !isset($inputs['SourceInstance']), 'Each JSON provider has a dedicated list with a string variable selector.');
    IPS_SetProperty($positionPanelMapID, $property, json_encode([['SourceName' => $name, 'SourcePosition' => $variable]], JSON_THROW_ON_ERROR));
}
IPS_ApplyChanges($positionPanelMapID);
$panelEmbed = $positionPanelMap->GetIPSViewHTML();
$panelAccess = bootstrap($panelEmbed)['options'];
$panelPath = dirname($panelAccess['page']) . '/state';
foreach ([0, 1] as $step) {
    $time = $now - 30 + $step;
    foreach ($reports as [$vars, $timeType, $index]) {
        $latitude = 50.25 + $index + $step / 2;
        if (count($vars) === 1) {
            SetValueString($vars[0], json_encode(['lat' => $latitude, 'lon' => 8.25, 'tst' => $time], JSON_THROW_ON_ERROR));
        } else {
            [$lat, $lon, $stamp] = $vars;
            SetValueFloat($lat, $latitude);
            SetValueFloat($lon, 8.25);
            SetValue($stamp, $timeType === VARIABLETYPE_STRING ? date('YmdHis', $time) : $time);
        }
        $positionPanelMap->MessageSink($time, end($vars), VM_UPDATE, []);
    }
    SetValueString($bmwSource, json_encode($bmwPayload(50.25 + $step / 2, $time), JSON_THROW_ON_ERROR));
    SetValueString($traccarSource, json_encode(['latitude' => 50.25 + $step / 2, 'longitude' => 8.25, 'fixTime' => gmdate('c', $time)], JSON_THROW_ON_ERROR));
    $driveState = ['latitude' => 50.25 + $step / 2, 'longitude' => 8.25, 'gps_as_of' => $time, 'timestamp' => ($time + 12) * 1000];
    SetValueString($tessieSource, json_encode($step === 0 ? ['drive_state' => $driveState] : $driveState, JSON_THROW_ON_ERROR));
    foreach ([$bmwSource, $traccarSource, $tessieSource] as $variable) {
        $positionPanelMap->MessageSink($time, $variable, VM_UPDATE, []);
    }
    $sources = tileState($positionPanelMap)['sources'];
    check(count($sources) === 15 && count(array_unique(array_column($sources, 'id'))) === 15, 'All named providers contribute their own uniquely identified positions.');
    foreach ($sources as $entry) {
        $fix = $entry['objects'][0]['location'];
        check($fix['timestamp'] === $time
            && $fix['latitude'] === 50.25 + (($entry['objects'][0]['markerColor'] ?? -1) === 0 ? 1 : 0) + $step / 2, 'Each fix uses the original measurement time and coordinates.');
    }
    [$status, $body] = mapRequest($positionPanelMap, $panelPath, token: $panelAccess['token']);
    check($status === 200 && json_decode($body, true, 32, JSON_THROW_ON_ERROR)['sources'] === $sources, 'Both visualization outputs receive every provider position.');
}
$lastTessie = array_column($sources, null, 'name')['Tessie']['objects'][0];
$driveState['latitude'] = 51.25;
$driveState['timestamp'] += 1000;
SetValueString($tessieSource, json_encode(['drive_state' => $driveState], JSON_THROW_ON_ERROR));
$positionPanelMap->MessageSink($now, $tessieSource, VM_UPDATE, []);
$unchangedTessie = array_column(tileState($positionPanelMap)['sources'], null, 'name')['Tessie']['objects'][0];
check($unchangedTessie === $lastTessie, 'A newer vehicle status with the same GPS time cannot move the Tessie marker.');
unset($driveState['gps_as_of']);
SetValueString($tessieSource, json_encode(['drive_state' => $driveState], JSON_THROW_ON_ERROR));
$positionPanelMap->MessageSink($now, $tessieSource, VM_UPDATE, []);
$tessieWithoutFixTime = array_column(tileState($positionPanelMap)['sources'], null, 'name')['Tessie']['objects'][0];
check(!$tessieWithoutFixTime['current'] && $tessieWithoutFixTime['location'] === $lastTessie['location'], 'Tessie keeps the last known position when the GPS measurement time is missing.');
[$status, $body] = mapRequest($positionPanelMap, $panelPath, token: $panelAccess['token']);
check($status === 200 && json_decode($body, true, 32, JSON_THROW_ON_ERROR)['sources'] === tileState($positionPanelMap)['sources'], 'IPSView also retains the last known Tessie position.');
$populated = $providerPanels($positionPanelMap);
foreach (array_keys($providerContracts) as $property) {
    $values = array_column($populated[$property . 'Section']['items'], null, 'name')[$property]['values'];
    check(count($values) === 2 && array_column($values, 'SourceName') === [$property, $property]
        && $values[0]['SourceInstance'] !== $values[1]['SourceInstance'], 'Each provider list shows both configured devices and their source names.');
}

$oldTimestamp = $reports[0][0][2];
$sourceInstance = IPS_GetParent($oldTimestamp);
IPS_SetIdent($oldTimestamp, 'ReplacedTime');
$positionPanelMap->Update();
check(!in_array($oldTimestamp, $positionPanelMap->GetReferenceList(), true)
    && in_array($sourceInstance, $positionPanelMap->GetReferenceList(), true), 'A missing variable keeps the provider instance reference.');
$replacement = IPS_CreateVariable(VARIABLETYPE_INTEGER);
IPS_SetParent($replacement, $sourceInstance);
IPS_SetIdent($replacement, 'Sig_location_preciselocation_OEMUpdatedAt');
SetValueInteger($replacement, $now);
$positionPanelMap->Update();
check(in_array($replacement, $positionPanelMap->GetReferenceList(), true)
    && $positionPanelMap->GetIPSViewHTML() === $panelEmbed, 'Rediscovered variables preserve the source instance and IPSView access.');

$migrationID = IPS_CreateInstance($mapModule);
$migrationMap = IPS\InstanceManager::getInstanceInterface($migrationID);
IPS_SetProperty($migrationID, 'VehicleMode', 'bmw-cardata');
IPS_SetProperty($migrationID, 'VehiclePosition', $bmwSource);
IPS_SetProperty($migrationID, 'VehicleMarkerColor', 0);
IPS_SetProperty($migrationID, 'EnableIPSView', true);
IPS_SetProperty($migrationID, 'Vehicles', json_encode([[
    'CarMode' => 'json', 'CarFormat' => 'traccar', 'CarPosition' => $traccarSource, 'CarName' => 'Old Traccar'
]], JSON_THROW_ON_ERROR));
IPS_SetProperty($migrationID, 'Trackers', json_encode([['TrackerPosition' => $reports[10][0][0], 'TrackerName' => 'Old OwnTracks']], JSON_THROW_ON_ERROR));
IPS_ApplyChanges($migrationID);
$oldSnapshot = tileState($migrationMap)['sources'];
$oldEmbed = $migrationMap->GetIPSViewHTML();
$persisted = json_encode(['configuration' => json_decode(IPS_GetConfiguration($migrationID)), 'attributes' => (object) []], JSON_THROW_ON_ERROR);
$result = $migrationMap->Migrate($persisted);
$migrated = json_decode($result, true, 32, JSON_THROW_ON_ERROR)['configuration'];
check($migrated['VehicleMode'] === 'off' && $migrated['Vehicles'] === '[]' && $migrated['Trackers'] === '[]'
    && $migrationMap->Migrate($result) === '', 'Known stored assignments move only once.');
IPS_SetConfiguration($migrationID, json_encode($migrated, JSON_THROW_ON_ERROR));
IPS_ApplyChanges($migrationID);
check(tileState($migrationMap)['sources'] === $oldSnapshot && $migrationMap->GetIPSViewHTML() === $oldEmbed, 'Migration preserves source IDs, fixes, colors and IPSView access.');

$oldManual = json_encode(['configuration' => ['Vehicles' => json_encode([
    ['CarMode' => 'fields', 'CarPreset' => 'volvo', 'CarLatitude' => 101, 'CarLongitude' => 102, 'CarTimestamp' => 103, 'CarMarkerColor' => 15],
    ['CarMode' => 'fields', 'CarPreset' => 'fields', 'CarLatitude' => 201, 'CarLongitude' => 202, 'CarTimestamp' => 203]
], JSON_THROW_ON_ERROR), 'Trackers' => 'bad configuration']], JSON_THROW_ON_ERROR);
$manual = json_decode($migrationMap->Migrate($oldManual), true, 32, JSON_THROW_ON_ERROR)['configuration'];
$manualVolvo = json_decode($manual['VolvoVehicles'], true, 16, JSON_THROW_ON_ERROR)[0];
check($manualVolvo === ['SourceKind' => 'vehicle', 'SourceLatitude' => 101, 'SourceLongitude' => 102, 'SourceTimestamp' => 103, 'SourceMarkerColor' => 15]
    && json_decode($manual['Vehicles'], true, 16, JSON_THROW_ON_ERROR)[0]['CarPreset'] === 'fields'
    && $manual['Trackers'] === 'bad configuration', 'Migration preserves exact manual mappings, generic entries, and undecodable configuration.');
