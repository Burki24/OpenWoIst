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
$adapterModule = '{51FAD7B1-C71B-4A10-BF6A-72FA62649771}';
$modules = $metadata->getValue();
$modules[$tractiveDevice] = array_replace($modules[$adapterModule], [
    'Class'    => TestTractiveTracker::class,
    'ModuleID' => $tractiveDevice
]);
$metadata->setValue(null, $modules);

$trackerID = IPS_CreateInstance($tractiveDevice);
$trackerFixture = IPS\InstanceManager::getInstanceInterface($trackerID);
$adapterID = IPS_CreateInstance($adapterModule);
$adapter = IPS\InstanceManager::getInstanceInterface($adapterID);
$positionID = IPS_GetObjectIDByIdent('Position', $adapterID);
OWIT_Update($adapterID);
check(IPS_GetInstance($adapterID)['InstanceStatus'] === 201 && GetValueString($positionID) === '', 'The unconfigured adapter publishes nothing.');
IPS_SetProperty($adapterID, 'TrackerInstance', $accountID);
IPS_ApplyChanges($adapterID);
check($adapter->GetReferenceList() === [] && IPS_GetInstance($adapterID)['InstanceStatus'] === 201, 'An unrelated module cannot be selected as a Tractive device.');

IPS_SetProperty($adapterID, 'TrackerInstance', $trackerID);
(new ReflectionMethod($adapter, 'SetTimerInterval'))->invoke($adapter, 'ReadPosition', 300000);
IPS_ApplyChanges($adapterID);
$sourceIDs = [];
foreach (['LastPositionMessage', 'LastLatitude', 'LastLongitude', 'PositionUncertainty', 'LastUpdate'] as $ident) {
    $sourceIDs[$ident] = IPS_GetObjectIDByIdent($ident, $trackerID);
}
$messages = (new ReflectionMethod($adapter, 'GetMessageList'))->invoke($adapter);
foreach ($sourceIDs as $id) {
    check($messages[$id] === [VM_UPDATE], 'An unchanged variable value must still be captured on each completed cycle.');
}
check(
    $adapter->GetReferenceList() === [$trackerID, ...array_values($sourceIDs)]
    && IPS_GetInstance($adapterID)['ConnectionID'] === 0
    && (new ReflectionMethod($adapter, 'GetTimerInterval'))->invoke($adapter, 'ReadPosition') === 0,
    'The adapter subscribes to the assigned device with no I/O connection or old timer.'
);
$form = json_decode(file_get_contents($root . '/OpenWoIstTractive/form.json'), true, 32, JSON_THROW_ON_ERROR);
check(
    array_column($form['elements'], 'name') === ['TrackerInstance'] && $form['elements'][0]['validModules'] === [$tractiveDevice],
    'The form chooses a Tractive device and needs no raw variable, I/O or poll settings.'
);

$emit = static function (string $ident, int|float $value, int $observedAt) use ($adapter, $trackerFixture, $sourceIDs): void
{
    $id = $sourceIDs[$ident];
    $prior = GetValue($id);
    $trackerFixture->WriteFixture($ident, $value);
    $adapter->MessageSink($observedAt, $id, VM_UPDATE, [$value, $prior !== $value, $prior, $observedAt]);
};
$record = static function (array $fix, int $observedAt, ?float $accuracy = null) use ($emit): void
{
    $emit('LastPositionMessage', $fix['timestamp'], $observedAt);
    $emit('LastLatitude', $fix['latitude'], $observedAt);
    $emit('LastLongitude', $fix['longitude'], $observedAt);
    if ($accuracy !== null) {
        $emit('PositionUncertainty', $accuracy, $observedAt);
    }
};

$tractiveMapID = IPS_CreateInstance($mapModule);
$tractiveMap = IPS\InstanceManager::getInstanceInterface($tractiveMapID);
IPS_SetProperty($tractiveMapID, 'Accounts', json_encode([['AccountID' => $accountID]], JSON_THROW_ON_ERROR));
IPS_SetProperty($tractiveMapID, 'VehicleMode', 'demo');
IPS_SetProperty($tractiveMapID, 'EnableIPSView', true);
IPS_ApplyChanges($tractiveMapID);
$beforeTractive = tileState($tractiveMap)['sources'];
$tractiveRow = json_encode([['TrackerName' => 'Fixture pet', 'TrackerPosition' => $positionID, 'TrackerFormat' => 'json', 'TrackerMarkerColor' => 0x2256A6]], JSON_THROW_ON_ERROR);
IPS_SetProperty($tractiveMapID, 'Trackers', $tractiveRow);
IPS_ApplyChanges($tractiveMapID);
$tractiveEmbed = $tractiveMap->GetIPSViewHTML();
$tractiveAccess = bootstrap($tractiveEmbed)['options'];
$tractivePath = dirname($tractiveAccess['page']) . '/state';

// Both Store revisions export the same variable sequence in their respective position decoders.
$reports = [
    '1.14' => ['latitude' => 51.5, 'longitude' => -1.25, 'timestamp' => $now - 90],
    '1.19' => ['latitude' => 51.75, 'longitude' => -1.0, 'timestamp' => $now - 60]
];
foreach ($reports as $version => $fix) {
    $accuracy = $version === '1.14' ? 8.0 : null;
    $before = GetValueString($positionID);
    $record($fix, $now, $accuracy);
    OWIT_Update($adapterID);
    check(GetValueString($positionID) === $before, 'No part of a report may be published before LastUpdate, even on a manual check.');
    $emit('LastUpdate', $now, $now);
    $expected = $accuracy === null ? $fix : $fix + ['horizontalAccuracy' => $accuracy];
    check(json_decode(GetValueString($positionID), true, 8, JSON_THROW_ON_ERROR) === $expected, 'The adapter combines the local report at LastUpdate for ' . $version);
    $tractiveMap->MessageSink($now, $positionID, VM_UPDATE, []);
    $sources = tileState($tractiveMap)['sources'];
    $marker = $sources[2]['objects'][0];
    check(array_slice($sources, 0, 2) === $beforeTractive && $marker['location'] === $fix + ['accuracy' => $accuracy] && $marker['markerColor'] === 0x2256A6, 'Kachel receives the same fix next to the Apple and vehicle sources.');
    [$status, $response] = mapRequest($tractiveMap, $tractivePath, token: $tractiveAccess['token']);
    check($status === 200 && json_decode($response, true, 32, JSON_THROW_ON_ERROR)['sources'] === $sources, 'IPSView receives the same Tractive position as the tile.');
}
check($tractiveMap->GetIPSViewHTML() === $tractiveEmbed && IPS_GetProperty($tractiveMapID, 'Trackers') === $tractiveRow, 'Updates preserve assignments and the embedding.');

$lastPosition = GetValueString($positionID);
$emit('LastUpdate', $now + 1, $now + 1);
check(GetValueString($positionID) === $lastPosition, 'A refresh with no position does not renew the measurement timestamp.');
$stationary = array_replace($reports['1.19'], ['timestamp' => $now - 50]);
$record($stationary, $now + 2);
$emit('LastUpdate', $now + 2, $now + 2);
check(json_decode(GetValueString($positionID), true, 8, JSON_THROW_ON_ERROR) === $stationary, 'A stationary tracker still reports its newer measurement time.');
$lastPosition = GetValueString($positionID);

$emit('LastPositionMessage', $now - 40, $now + 3);
$emit('LastLatitude', 51.9, $now + 3);
$emit('LastUpdate', $now + 3, $now + 3);
check(GetValueString($positionID) === $lastPosition && IPS_GetInstance($adapterID)['InstanceStatus'] === 203, 'An incomplete report preserves the last accepted fix.');
$emit('LastPositionMessage', $now - 35, $now + 4);
$emit('LastPositionMessage', $now - 30, $now + 4);
$emit('LastLatitude', 51.95, $now + 4);
$emit('LastLongitude', -0.25, $now + 4);
$emit('LastUpdate', $now + 4, $now + 4);
$emit('LastLatitude', 52.1, $now + 5);
$emit('LastLongitude', 0.5, $now + 5);
$emit('LastUpdate', $now + 5, $now + 5);
check(GetValueString($positionID) === $lastPosition, 'Two overlapping reports must not pair the earlier coordinates with the later measurement time.');
$emit('LastPositionMessage', $now - 28, $now + 6);
$emit('LastLatitude', 51.8, $now + 6);
$recovered = ['latitude' => 51.85, 'longitude' => -0.5, 'timestamp' => $now - 10];
$record($recovered, $now + 30);
$emit('LastUpdate', $now + 30, $now + 30);
check(json_decode(GetValueString($positionID), true, 8, JSON_THROW_ON_ERROR) === $recovered, 'An abandoned report cannot block future complete updates.');

IPS_SetIdent($sourceIDs['LastUpdate'], 'UnavailableLastUpdate');
OWIT_Update($adapterID);
check($adapter->GetReferenceList() === [$trackerID] && IPS_GetInstance($adapterID)['InstanceStatus'] === 202, 'A tracker with incomplete variables remains assigned and is reported separately.');
IPS_SetIdent($sourceIDs['LastUpdate'], 'LastUpdate');
$adapter->MessageSink($now + 31, 0, IPS_KERNELMESSAGE, [KR_READY]);
check($adapter->GetReferenceList() === [$trackerID, ...array_values($sourceIDs)] && IPS_GetInstance($adapterID)['InstanceStatus'] === IS_ACTIVE, 'The adapter rebinds the current variable IDs when the kernel is ready.');

IPS_SetProperty($adapterID, 'TrackerInstance', 0);
IPS_ApplyChanges($adapterID);
check($adapter->GetReferenceList() === [] && GetValueString($positionID) === '' && IPS_GetObjectIDByIdent('Position', $adapterID) === $positionID, 'Unassigning the tracker clears its data and subscriptions and retains the output variable ID.');
echo "OpenWoIst passive Tractive adapter passed for 1.14 and 1.19 Beta.\n";
