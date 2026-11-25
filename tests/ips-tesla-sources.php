<?php

declare(strict_types=1);

final class TestIPSTeslaConnector extends IPSModuleStrict
{
    public function Create(): void
    {
        parent::Create();
        $this->RegisterPropertyBoolean('IncludeLocationData', false);
    }
}

$driveModule = '{83675CDD-3E50-FB21-8AE6-B4FC718A77B6}';
$connectorModule = '{72887112-E270-FFC1-04AC-817D82F42027}';
$toDrive = '{9825E0F7-B5DF-6ED8-4F73-5C7366FBB11F}';
$toConnector = '{675AD8E8-253B-71E3-7304-6C8D9CDCD50B}';
$modules = $metadata->getValue();
$modules[$driveModule] = array_replace($modules[$mapModule], [
    'Class'              => TestPositionProvider::class, 'ModuleID' => $driveModule, 'Prefix' => 'TESTTESLADRIVE',
    'ParentRequirements' => [$toConnector], 'Implemented' => [$toDrive]
]);
$modules[$connectorModule] = array_replace($modules[$mapModule], [
    'Class'             => TestIPSTeslaConnector::class, 'ModuleID' => $connectorModule, 'ModuleType' => 2, 'Prefix' => 'TESTTESLACONNECTOR',
    'ChildRequirements' => [$toDrive], 'Implemented' => [$toConnector]
]);
$metadata->setValue(null, $modules);

$teslaMapID = IPS_CreateInstance($mapModule);
$teslaMap = IPS\InstanceManager::getInstanceInterface($teslaMapID);
IPS_SetProperty($teslaMapID, 'VehicleMode', 'demo');
IPS_SetProperty($teslaMapID, 'EnableIPSView', true);
IPS_SetProperty($teslaMapID, 'Accounts', json_encode([['AccountID' => $accountID]], JSON_THROW_ON_ERROR));
IPS_ApplyChanges($teslaMapID);
$beforeTesla = array_column(tileState($teslaMap)['sources'], null, 'id');
$beforeConfiguration = json_decode(IPS_GetConfiguration($teslaMapID), true, 32, JSON_THROW_ON_ERROR);

$teslaRows = $teslaReports = $connectors = [];
foreach ([0x114466, 0x886622] as $index => $color) {
    $driveID = IPS_CreateInstance($driveModule);
    IPS_SetName($driveID, 'Test Tesla');
    $connectors[$index] = IPS_CreateInstance($connectorModule);
    IPS_ConnectInstance($driveID, $connectors[$index]);
    foreach (['latitude', 'longitude', 'gps_as_of'] as $ident) {
        $variable = IPS_CreateVariable(VARIABLETYPE_STRING);
        IPS_SetParent($variable, $driveID);
        IPS_SetIdent($variable, $ident);
        $teslaReports[$index][] = $variable;
    }
    $teslaRows[] = ['SourceInstance' => $driveID, 'SourceMarkerColor' => $color];
    SetValueString($teslaReports[$index][0], (string) (51.25 + $index));
    SetValueString($teslaReports[$index][1], '-0.75');
    SetValueString($teslaReports[$index][2], (string) ($now - 5));
}
IPS_SetProperty($teslaMapID, 'IPSTeslaVehicles', json_encode($teslaRows, JSON_THROW_ON_ERROR));
IPS_ApplyChanges($teslaMapID);
$teslaEmbed = $teslaMap->GetIPSViewHTML();
$teslaAccess = bootstrap($teslaEmbed)['options'];
$teslaList = array_column($providerPanels($teslaMap)['IPSTeslaVehiclesSection']['items'], null, 'name')['IPSTeslaVehicles'];
check($teslaList['add'] && array_column($teslaList['values'], 'SourceName') === ['Test Tesla', 'Test Tesla']
    && array_column($teslaList['form'], null, 'name')['SourceInstance']['validModules'] === [$driveModule]
    && !isset(array_column($teslaList['columns'], null, 'name')['SourceTimestamp']), 'The Tesla form selects multiple original drive instances without manual variable assignment.');
$teslaVehicles = static fn (): array => array_values(array_filter(tileState($teslaMap)['sources'], static fn (array $source): bool => $source['kind'] === 'vehicle'));
check(count($teslaVehicles()) === 2 && array_column($teslaVehicles(), 'available') === [false, false]
    && $teslaVehicles()[0]['objects'][0]['location'] === null, 'Coordinates from a connector without opted-in GPS publication are ignored.');

foreach ($connectors as $connector) {
    IPS_SetProperty($connector, 'IncludeLocationData', true);
    IPS_ApplyChanges($connector);
}
$teslaMap->Update();
$firstVehicles = $teslaVehicles();
check(count(array_unique(array_column($firstVehicles, 'id'))) === 2
    && $firstVehicles[0]['objects'][0]['location'] === ['latitude' => 51.25, 'longitude' => -0.75, 'timestamp' => $now - 5, 'accuracy' => null]
    && $firstVehicles[1]['objects'][0]['markerColor'] === 0x886622, 'Both original instances use the reported GPS time and distinct marker settings.');

[$teslaLat, $teslaLon, $teslaTime] = $teslaReports[0];
SetValueString($teslaTime, '');
SetValueString($teslaLat, '51.75');
$teslaMap->MessageSink($now, $teslaTime, VM_UPDATE, []);
check(!$teslaVehicles()[0]['objects'][0]['current']
    && $teslaVehicles()[0]['objects'][0]['location'] === $firstVehicles[0]['objects'][0]['location'], 'A partially written GPS report retains the old point.');
SetValueString($teslaLon, '0.25');
SetValueString($teslaTime, (string) ($now - 1));
$teslaMap->MessageSink($now, $teslaTime, VM_UPDATE, []);
$secondVehicles = $teslaVehicles();
check($secondVehicles[0]['objects'][0]['location'] === ['latitude' => 51.75, 'longitude' => 0.25, 'timestamp' => $now - 1, 'accuracy' => null]
    && $secondVehicles[0]['objects'][0]['markerColor'] === 0x114466 && $secondVehicles[1] === $firstVehicles[1], 'A complete new GPS report moves only its own marker.');
SetValueString($teslaTime, (string) ($now - 6));
$teslaMap->MessageSink($now, $teslaTime, VM_UPDATE, []);
$state = tileState($teslaMap);
[$status, $body] = mapRequest($teslaMap, dirname($teslaAccess['page']) . '/state', token: $teslaAccess['token']);
check($teslaVehicles()[0]['objects'][0]['location'] === $secondVehicles[0]['objects'][0]['location'], 'An older GPS report preserves the last known point.');
check($status === 200, 'The IPSView state request succeeds.');
check(json_decode($body, true, 32, JSON_THROW_ON_ERROR)['sources'] === $state['sources'], 'The IPSView state matches the tile for the new and existing sources.');
$afterConfiguration = json_decode(IPS_GetConfiguration($teslaMapID), true, 32, JSON_THROW_ON_ERROR);
unset($afterConfiguration['IPSTeslaVehicles'], $beforeConfiguration['IPSTeslaVehicles']);
check($beforeConfiguration === $afterConfiguration && $teslaEmbed === $teslaMap->GetIPSViewHTML()
    && array_intersect_key(array_column($state['sources'], null, 'id'), $beforeTesla) === $beforeTesla, 'The Tesla list preserves the iCloud and test vehicle sources and their IPSView embedding.');
