<?php

declare(strict_types=1);

$selectionID = IPS_CreateInstance($mapModule);
$selectionMap = IPS\InstanceManager::getInstanceInterface($selectionID);
$tablet = array_replace($phone, ['id' => 'private-tablet-id', 'name' => 'Tablet', 'location' => null]);
$account->cache = ['success' => true, 'state' => 'authenticated', 'updatedAt' => $now, 'devices' => [$phone, $tablet]];
$accountsValue = json_encode([['AccountID' => $accountID]], JSON_THROW_ON_ERROR);
IPS_SetProperty($selectionID, 'Accounts', $accountsValue);
IPS_SetProperty($selectionID, 'VehicleMode', 'demo');
IPS_SetProperty($selectionID, 'EnableIPSView', true);
IPS_ApplyChanges($selectionID);
$devices = source($selectionMap, 'icloud')['objects'];
$demoKey = source($selectionMap, 'demo')['id'];
$phoneKey = $devices[0]['id'];
check(count($devices) === 2, 'Existing assignments include all iCloud devices by default.');
$before = IPS_GetConfiguration($selectionID);
$configuration = json_decode($selectionMap->GetConfigurationForm(), true, 64, JSON_THROW_ON_ERROR);
$fields = array_column($configuration['elements'], null, 'name');
$picker = array_column($fields['DeviceSelectionPanel']['items'], null, 'name')['SelectedDevices'];
$choices = array_column($picker['columns'][0]['edit']['options'], 'caption', 'value');
check(count($choices) === 3 && isset($choices[$phoneKey], $choices[$devices[1]['id']]), 'The picker includes assigned cached devices even without a fix.');
check(!str_contains(json_encode($picker, JSON_THROW_ON_ERROR), $tablet['id']), 'The picker stores opaque device identifiers.');
$vehicleForm = $fields['Vehicles']['form'];
$jsonInputs = $vehicleForm[1]['options'][0]['items'] ?? $vehicleForm[2]['items'];
$coordinateInputs = $vehicleForm[1]['options'][1]['items'] ?? $vehicleForm[3]['items'];
check(in_array('traccar', array_column($jsonInputs[0]['options'], 'value'), true) && array_column($coordinateInputs, 'name') === ['CarLatitude', 'CarLongitude', 'CarTimestamp'], 'Each vehicle can configure Traccar or three coordinate inputs.');
check(IPS_GetConfiguration($selectionID) === $before, 'Opening the picker must not modify assignments.');

$initialAccess = bootstrap($selectionMap->GetIPSViewHTML())['options'];
$account->fail = true;
IPS_SetProperty($selectionID, 'ShowAllDevices', false);
IPS_SetProperty($selectionID, 'SelectedDevices', json_encode([['DeviceKey' => $phoneKey]], JSON_THROW_ON_ERROR));
IPS_ApplyChanges($selectionID);
check(array_column(source($selectionMap, 'icloud')['objects'], 'id') === [$phoneKey], 'A failed cache read must never restore deselected devices.');
$configuration = json_decode($selectionMap->GetConfigurationForm(), true, 64, JSON_THROW_ON_ERROR);
$fields = array_column($configuration['elements'], null, 'name');
$picker = array_column($fields['DeviceSelectionPanel']['items'], null, 'name')['SelectedDevices'];
check(array_column($picker['columns'][0]['edit']['options'], 'value') === ['', $phoneKey], 'Saved devices must remain available in the form during a cache failure.');
$access = bootstrap($selectionMap->GetIPSViewHTML())['options'];
$path = dirname($access['page']) . '/state';
check(mapRequest($selectionMap, $path, token: $initialAccess['token'])[0] === 403, 'Changing device scope invalidates the previous token.');
check(array_column(json_decode(mapRequest($selectionMap, $path, token: $access['token'])[1], true, 32, JSON_THROW_ON_ERROR)['sources'][0]['objects'], 'id') === [$phoneKey], 'IPSView must use the same device selection as the tile.');
$account->fail = false;
IPS_SetProperty($selectionID, 'SelectedDevices', '[]');
IPS_ApplyChanges($selectionID);
check(source($selectionMap, 'icloud')['objects'] === [], 'An explicitly empty selection includes no device.');

$carA = IPS_CreateVariable(VARIABLETYPE_STRING);
$carB = IPS_CreateVariable(VARIABLETYPE_STRING);
IPS_SetName($carA, 'Same vehicle input');
IPS_SetName($carB, 'Same vehicle input');
SetValueString($carA, json_encode(['lat' => 51.1, 'lon' => 9.0, 'tst' => $now], JSON_THROW_ON_ERROR));
SetValueString($carB, json_encode(['latitude' => 52.2, 'longitude' => 13.0, 'fixTime' => gmdate('c', $now)], JSON_THROW_ON_ERROR));
$cars = [
    ['CarName' => 'Same vehicle name', 'CarMode' => 'json', 'CarFormat' => 'owntracks', 'CarPosition' => $carA],
    ['CarName' => 'Same vehicle name', 'CarMode' => 'json', 'CarFormat' => 'traccar', 'CarPosition' => $carB]
];
IPS_SetProperty($selectionID, 'Vehicles', json_encode([...$cars, $cars[0]], JSON_THROW_ON_ERROR));
IPS_ApplyChanges($selectionID);
$allSources = tileState($selectionMap)['sources'];
check(count($allSources) === 4 && $allSources[1]['id'] === $demoKey, 'Additional vehicles must leave the demo in place and deduplicate by input ID.');
check($allSources[2]['objects'][0]['location']['latitude'] === 51.1 && $allSources[3]['objects'][0]['location']['latitude'] === 52.2, 'Each vehicle must use its own configured converter.');
check($selectionMap->GetReferenceList() === [$accountID, $carA, $carB], 'Every assigned vehicle input must have its own reference.');
$getSubscriptions = new ReflectionMethod($selectionMap, 'GetMessageList');
foreach ([$carA, $carB] as $id) {
    check(in_array(VM_UPDATE, $getSubscriptions->invoke($selectionMap)[$id] ?? [], true), 'Every assigned JSON vehicle must subscribe to VM_UPDATE.');
}
IPS_SetName($carB, 'Renamed vehicle input');
SetValueString($carB, json_encode(['latitude' => 52.4, 'longitude' => 13.5, 'fixTime' => gmdate('c', $now + 1)], JSON_THROW_ON_ERROR));
$selectionMap->MessageSink(1, $carB, VM_UPDATE, []);
$updated = tileState($selectionMap)['sources'];
check($updated[2] === $allSources[2] && $allSources[3]['id'] === $updated[3]['id'] && $updated[3]['objects'][0]['location']['latitude'] === 52.4, 'Only the updated vehicle moves and renaming the input preserves identity.');
$access = bootstrap($selectionMap->GetIPSViewHTML())['options'];
$view = json_decode(mapRequest($selectionMap, $path, token: $access['token'])[1], true, 32, JSON_THROW_ON_ERROR);
check($view['sources'] === $updated, 'Both outputs must display the same vehicle list and positions.');
IPS_SetProperty($selectionID, 'Vehicles', json_encode([$cars[0]], JSON_THROW_ON_ERROR));
IPS_ApplyChanges($selectionID);
check($selectionMap->GetReferenceList() === [$accountID, $carA] && !isset($getSubscriptions->invoke($selectionMap)[$carB]), 'Deleting a vehicle must remove its reference and subscription.');
