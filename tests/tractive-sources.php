<?php

declare(strict_types=1);

final class TestTractiveTracker extends IPSModuleStrict
{
    public function Create(): void
    {
        parent::Create();
        $this->RegisterVariableInteger('LastPositionMessage', 'Measurement time', '', 0);
        $this->RegisterVariableFloat('LastLatitude', 'Latitude', '', 1);
        $this->RegisterVariableFloat('LastLongitude', 'Longitude', '', 2);
        $this->RegisterVariableFloat('PositionUncertainty', 'Position uncertainty', '', 3);
        $this->RegisterVariableInteger('LastUpdate', 'Update completed', '', 4);
    }

    public function WriteFixture(string $ident, int|float $value): void
    {
        $this->SetValue($ident, $value);
    }
}

$tractiveDevice = '{A259E80D-C7B4-F5A9-F82B-B9B05F71B4F3}';
$directMapID = IPS_CreateInstance($mapModule);
$directMap = IPS\InstanceManager::getInstanceInterface($directMapID);
$tractivePanel = static function (OpenWoIst $map): array
{
    $form = json_decode($map->GetConfigurationForm(), true, 64, JSON_THROW_ON_ERROR);
    $group = array_column($form['elements'], null, 'name')['LocationDeviceSources'];
    return array_column($group['items'], null, 'name')['TractiveSources'];
};
$beforeSetup = $tractivePanel($directMap);
$beforeItems = array_column($beforeSetup['items'], null, 'name');
check($beforeSetup['caption'] === 'Tractive GPS' && $beforeSetup['expanded'] && $beforeItems['TractiveTrackers']['type'] === 'List'
    && $beforeItems['TractiveSetupRequired']['visible'], 'The standalone Tractive section exposes the device list and explains an empty selector.');

$modules = $metadata->getValue();
$modules[$tractiveDevice] = array_replace($modules[$mapModule], [
    'Class'    => TestTractiveTracker::class,
    'ModuleID' => $tractiveDevice,
    'Prefix'   => 'TESTTRACTIVE'
]);
$metadata->setValue(null, $modules);

$trackerID = IPS_CreateInstance($tractiveDevice);
$directItems = array_column($tractivePanel($directMap)['items'], null, 'name');
$directList = $directItems['TractiveTrackers'];
$directColumns = array_column($directList['columns'], null, 'name');
check(array_keys($directColumns) === ['TractiveInstance', 'TractivePetName', 'TractiveMarkerColor']
    && $directColumns['TractiveInstance']['edit']['type'] === 'SelectInstance'
    && $directColumns['TractiveInstance']['edit']['validModules'] === [$tractiveDevice]
    && $directColumns['TractivePetName']['edit']['type'] === 'ValidationTextBox'
    && $directList['add'] && $directList['delete']
    && !$directItems['TractiveSetupRequired']['visible'], 'The map offers multiple original Tractive devices with optional marker colors.');

$sourceIDs = [];
foreach (['LastPositionMessage', 'LastLatitude', 'LastLongitude', 'PositionUncertainty', 'LastUpdate'] as $ident) {
    $sourceIDs[$ident] = IPS_GetObjectIDByIdent($ident, $trackerID);
}
$reports = [
    '1.14' => ['latitude' => 51.5, 'longitude' => -1.25, 'timestamp' => $now - 90],
    '1.19' => ['latitude' => 51.75, 'longitude' => -1.0, 'timestamp' => $now - 60]
];

$secondTrackerID = IPS_CreateInstance($tractiveDevice);
IPS_SetName($trackerID, 'Test tracker');
IPS_SetName($secondTrackerID, 'Test tracker');
IPS_SetProperty($directMapID, 'Accounts', json_encode([['AccountID' => $accountID]], JSON_THROW_ON_ERROR));
IPS_SetProperty($directMapID, 'VehicleMode', 'demo');
IPS_SetProperty($directMapID, 'EnableIPSView', true);
IPS_ApplyChanges($directMapID);
$directBase = tileState($directMap)['sources'];
$secondSources = [];
foreach (array_keys($sourceIDs) as $ident) {
    $secondSources[$ident] = IPS_GetObjectIDByIdent($ident, $secondTrackerID);
}
$rows = [
    ['TractiveInstance' => $trackerID, 'TractiveMarkerColor' => 0x2256A6],
    ['TractiveInstance' => $secondTrackerID, 'TractivePetName' => 'Test pet', 'TractiveMarkerColor' => 0],
    ['TractiveInstance' => $accountID]
];
$trackerConfiguration = json_encode([...$rows, $rows[0]], JSON_THROW_ON_ERROR);
IPS_SetProperty($directMapID, 'TractiveTrackers', $trackerConfiguration);
IPS_ApplyChanges($directMapID);
$list = array_column($tractivePanel($directMap)['items'], null, 'name')['TractiveTrackers'];
check(!$list['loadValuesFromConfiguration'] && $list['values'][0]['TractivePetName'] === 'Test tracker'
    && $list['values'][1] === $rows[1] && $list['values'][2]['TractivePetName'] === ''
    && IPS_GetProperty($directMapID, 'TractiveTrackers') === $trackerConfiguration, 'The list suggests the source instance name, keeps a custom pet name, and does not rewrite saved rows.');
$watch = (new ReflectionMethod($directMap, 'GetMessageList'))->invoke($directMap);
foreach ([...array_values($sourceIDs), ...array_values($secondSources)] as $id) {
    check(in_array(VM_UPDATE, $watch[$id], true), 'The map discovers and subscribes to the original local variables.');
}
check(!isset($watch[$accountID])
    && in_array($trackerID, $directMap->GetReferenceList(), true)
    && in_array($secondTrackerID, $directMap->GetReferenceList(), true), 'The map uses both original devices and ignores an unrelated source.');
$directEmbed = $directMap->GetIPSViewHTML();
$directAccess = bootstrap($directEmbed)['options'];
$directPath = dirname($directAccess['page']) . '/state';

$send = static function (array $ids, string $ident, int|float $value) use ($directMap, $now): void
{
    $id = $ids[$ident];
    $old = GetValue($id);
    IPS\InstanceManager::getInstanceInterface(IPS_GetParent($id))->WriteFixture($ident, $value);
    $directMap->MessageSink($now, $id, VM_UPDATE, [$value, $value !== $old, $old, $now]);
};
$reportValues = [
    'LastPositionMessage' => 'timestamp',
    'LastLatitude'        => 'latitude',
    'LastLongitude'       => 'longitude'
];
foreach ($reportValues as $ident => $key) {
    $send($sourceIDs, $ident, $reports['1.14'][$key]);
    $send($secondSources, $ident, $reports['1.19'][$key]);
}
$send($sourceIDs, 'PositionUncertainty', 8.0);
$directMap->Update();
check(tileState($directMap)['sources'][2]['objects'][0]['location'] === null, 'A timer or manual refresh cannot take coordinates from an unfinished report.');
$send($secondSources, 'LastUpdate', $now);
check(tileState($directMap)['sources'][2]['objects'][0]['location'] === null, 'A different tracker cannot complete the first tracker’s report.');
$send($sourceIDs, 'LastUpdate', $now);
$directSources = tileState($directMap)['sources'];
[$firstDirect, $secondDirect, $unrelated] = array_slice($directSources, 2);
check(array_slice($directSources, 0, 2) === $directBase
    && count($directSources) === 5 && !$unrelated['available']
    && $firstDirect['id'] !== $secondDirect['id'] && $firstDirect['name'] === $secondDirect['name'], 'Distinct tracker instances can share a name; duplicate rows and unrelated source variables are ignored.');
check($firstDirect['objects'][0]['location'] === $reports['1.14'] + ['accuracy' => 8.0]
    && $secondDirect['objects'][0]['location'] === $reports['1.19'] + ['accuracy' => null]
    && $firstDirect['objects'][0]['markerColor'] === 0x2256A6
    && $secondDirect['objects'][0]['markerColor'] === 0, 'Both Store contracts update the correct marker and preserve its color.');
[$status, $response] = mapRequest($directMap, $directPath, token: $directAccess['token']);
check($status === 200 && json_decode($response, true, 32, JSON_THROW_ON_ERROR)['sources'] === $directSources
    && $directMap->GetIPSViewHTML() === $directEmbed, 'The same direct Tractive positions reach IPSView without replacing the embedding.');

$send($sourceIDs, 'LastPositionMessage', $now - 15);
$send($sourceIDs, 'LastLatitude', 51.7);
$send($sourceIDs, 'LastUpdate', $now);
check(tileState($directMap)['sources'][2] === $firstDirect, 'An incomplete direct update retains its own last fix and measurement time.');
$send($sourceIDs, 'LastPositionMessage', $now - 35);
$send($sourceIDs, 'LastPositionMessage', $now - 30);
$send($sourceIDs, 'LastLatitude', 51.95);
$send($sourceIDs, 'LastLongitude', -0.25);
$send($sourceIDs, 'LastUpdate', $now);
$send($sourceIDs, 'LastLatitude', 52.1);
$send($sourceIDs, 'LastLongitude', 0.5);
$send($sourceIDs, 'LastUpdate', $now);
check(tileState($directMap)['sources'][2] === $firstDirect, 'Overlapping reports cannot mix the coordinates and measurement times.');
IPS_SetProperty($directMapID, 'TractiveTrackers', json_encode([$rows[1]], JSON_THROW_ON_ERROR));
IPS_ApplyChanges($directMapID);
$remaining = tileState($directMap)['sources'];
$watch = (new ReflectionMethod($directMap, 'GetMessageList'))->invoke($directMap);
check(count($remaining) === 3 && $remaining[2] === $secondDirect
    && !isset($watch[$sourceIDs['LastUpdate']]) && !in_array($trackerID, $directMap->GetReferenceList(), true)
    && mapRequest($directMap, $directPath, token: $directAccess['token'])[0] === 403, 'Removing one instance removes its position and subscriptions and revokes the old view token.');

$formerCompletion = $secondSources['LastUpdate'];
IPS_SetIdent($formerCompletion, 'PreviousLastUpdate');
$directMap->Update();
$missing = tileState($directMap)['sources'][2];
check(!$missing['available'] && !$missing['objects'][0]['current']
    && $missing['objects'][0]['location'] === $secondDirect['objects'][0]['location'], 'A missing original variable leaves the last known position visible.');
$replacement = IPS_CreateVariable(VARIABLETYPE_INTEGER);
IPS_SetParent($replacement, $secondTrackerID);
IPS_SetIdent($replacement, 'LastUpdate');
$directMap->MessageSink($now, $secondTrackerID, OM_CHILDADDED, [$replacement]);
$secondSources['LastUpdate'] = $replacement;
$watch = (new ReflectionMethod($directMap, 'GetMessageList'))->invoke($directMap);
check(isset($watch[$replacement]) && !isset($watch[$formerCompletion]), 'The map subscribes to the replacement variable automatically.');
$newDirectFix = ['latitude' => 52.0, 'longitude' => -0.5, 'timestamp' => $now - 10];
foreach ($reportValues as $ident => $key) {
    $send($secondSources, $ident, $newDirectFix[$key]);
}
$send($secondSources, 'LastUpdate', $now);
$rebound = tileState($directMap)['sources'][2];
check($rebound['id'] === $secondDirect['id'] && $rebound['objects'][0]['current']
    && $rebound['objects'][0]['location'] === $newDirectFix + ['accuracy' => null], 'A complete report on the replacement binding moves the same marker.');
echo "OpenWoIst direct Tractive selection passed for 1.14 and 1.19 Beta.\n";
