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
check(array_slice(array_column($configuration['elements'], 'caption'), 0, 4) === ['Vehicles', 'Apple and Google', 'Pets', 'Trackers and location devices'], 'The form starts with thematic source groups.');
$cloudPanels = array_column($fields['CloudSources']['items'], null, 'name');
$petPanels = array_column($fields['PetSources']['items'], null, 'name');
$devicePanels = array_column($fields['LocationDeviceSources']['items'], null, 'name');
$vehiclePanels = array_column($fields['VehicleSources']['items'], null, 'name');
check(array_keys($cloudPanels) === ['ICloudSources', 'GoogleCloudSources']
    && array_keys($petPanels) === ['TractiveSources', 'FressnapfTrackersSection']
    && array_keys($devicePanels) === ['OwnTracksDevicesSection', 'TraccarDevicesSection', 'GarminLiveTrackSources', 'GarminMapShareSources', 'PajSources', 'TrackerSources']
    && array_keys($vehiclePanels) === ['BMWCarDataVehiclesSection', 'SmartcarVehiclesSection', 'HyundaiKiaVehiclesSection', 'VolvoVehiclesSection', 'RenaultZEVehiclesSection', 'TronityVehiclesSection', 'Vehicles', 'ExistingVehicleSettings'], 'Provider panels keep their own lists within the appropriate group.');
$modulePanels = $cloudPanels + $petPanels + $devicePanels + $vehiclePanels;
$links = [
    'ICloudSources'               => 'https://github.com/Burki24/SymconiCloud',
    'TractiveSources'             => 'https://www.symcon.de/de/module-store/demel41-tractivegps/',
    'FressnapfTrackersSection'    => 'https://www.symcon.de/de/module-store/bgersmann-fressnapftracker/',
    'PajSources'                  => 'https://v2.finder-portal.com/',
    'OwnTracksDevicesSection'     => 'https://www.symcon.de/de/module-store/silberstreifen-owntracks/',
    'TraccarDevicesSection'       => 'https://github.com/traccar/traccar',
    'BMWCarDataVehiclesSection'   => 'https://github.com/parcivad/ips-bmw-cardata',
    'SmartcarVehiclesSection'     => 'https://www.symcon.de/de/module-store/smartcar-symcon/',
    'HyundaiKiaVehiclesSection'   => 'https://github.com/da8ter/Bluelink',
    'VolvoVehiclesSection'        => 'https://github.com/demel42/IPSymconVolvo',
    'RenaultZEVehiclesSection'    => 'https://www.symcon.de/de/module-store/net-lorbetzki-zoe/',
    'TronityVehiclesSection'      => 'https://github.com/symcon/Tronity'
];
foreach ($links as $name => $url) {
    $panel = $modulePanels[$name];
    $link = $panel['items'][0];
    check($link['type'] === 'Button' && $link['link'] && str_starts_with($link['caption'], $name === 'ICloudSources' ? 'SymconiCloud' : $panel['caption'])
        && $link['onClick'] === 'echo ' . var_export($url, true) . ';' && !isset($panel['onClick']), 'Each module name is linked inside its panel and opening the panel never launches the link.');
}
$generic = $vehiclePanels['Vehicles'];
check($generic['caption'] === 'Vehicles from variables' && isset($vehiclePanels['ExistingVehicleSettings']), 'The generic vehicle list and the test vehicle remain accessible.');
$picker = array_column($cloudPanels['ICloudSources']['items'], null, 'name')['SelectedDevices'];
$choices = array_column($picker['values'], null, 'DeviceKey');
check(count($choices) === 2 && isset($choices[$phoneKey], $choices[$devices[1]['id']]), 'The list includes every assigned cached device even without a fix.');
check(!$choices[$phoneKey]['Included'] && $choices[$phoneKey]['MarkerColor'] === -1, 'New cached entries can be colored independently of their selection.');
check(!str_contains(json_encode($picker, JSON_THROW_ON_ERROR), $tablet['id']), 'The picker stores opaque device identifiers.');
$vehicleList = array_column($fields['VehicleSources']['items'], null, 'name')['Vehicles'];
$vehicleForm = $vehicleList['form'];
check(array_column($vehicleList['columns'], null, 'name')['CarMarkerColor']['edit']['type'] === 'SelectColor', 'The vehicle list exposes a per-vehicle color.');
$jsonInputs = $vehicleForm[1]['options'][0]['items'] ?? $vehicleForm[2]['items'];
$coordinateInputs = $vehicleForm[1]['options'][1]['items'] ?? $vehicleForm[3]['items'];
check(array_column($jsonInputs, 'name') === ['CarPosition']
    && array_column($coordinateInputs, 'name') === ['CarLatitude', 'CarLongitude', 'CarTimestamp']
    && isset(array_column($devicePanels['TraccarDevicesSection']['items'], null, 'name')['TraccarDevices']), 'The generic vehicle and Traccar source have their own inputs.');
check(IPS_GetConfiguration($selectionID) === $before, 'Opening the picker must not modify assignments.');

$initialAccess = bootstrap($selectionMap->GetIPSViewHTML())['options'];
$account->fail = true;
IPS_SetProperty($selectionID, 'ShowAllDevices', false);
IPS_SetProperty($selectionID, 'SelectedDevices', json_encode([['DeviceKey' => $phoneKey]], JSON_THROW_ON_ERROR));
IPS_ApplyChanges($selectionID);
check(array_column(source($selectionMap, 'icloud')['objects'], 'id') === [$phoneKey], 'A failed cache read must never restore deselected devices.');
$configuration = json_decode($selectionMap->GetConfigurationForm(), true, 64, JSON_THROW_ON_ERROR);
$fields = array_column($configuration['elements'], null, 'name');
$cloudPanels = array_column($fields['CloudSources']['items'], null, 'name');
$picker = array_column($cloudPanels['ICloudSources']['items'], null, 'name')['SelectedDevices'];
check(array_column($picker['values'], 'DeviceKey') === [$phoneKey] && $picker['values'][0]['Included'], 'Legacy rows remain selected and editable during a cache failure.');
$access = bootstrap($selectionMap->GetIPSViewHTML())['options'];
$path = dirname($access['page']) . '/state';
check(mapRequest($selectionMap, $path, token: $initialAccess['token'])[0] === 403, 'Changing device scope invalidates the previous token.');
check(array_column(json_decode(mapRequest($selectionMap, $path, token: $access['token'])[1], true, 32, JSON_THROW_ON_ERROR)['sources'][0]['objects'], 'id') === [$phoneKey], 'IPSView must use the same device selection as the tile.');
IPS_SetProperty($selectionID, 'SelectedDevices', json_encode([
    ['DeviceKey' => $phoneKey, 'Included' => true, 'MarkerColor' => 0],
    ['DeviceKey' => $devices[1]['id'], 'Included' => false, 'MarkerColor' => 0xDA283C]
], JSON_THROW_ON_ERROR));
IPS_ApplyChanges($selectionID);
$colored = source($selectionMap, 'icloud')['objects'];
check(count($colored) === 1 && $colored[0]['markerColor'] === 0, 'A retained offline marker uses the chosen color, including black.');
[$status, $body] = mapRequest($selectionMap, $path, token: $access['token']);
check($status === 200 && json_decode($body, true, 32, JSON_THROW_ON_ERROR)['sources'][0]['objects'] === $colored, 'A color change reaches IPSView without rotating its token.');
$account->fail = false;
IPS_SetProperty($selectionID, 'ShowAllDevices', true);
IPS_ApplyChanges($selectionID);
check(array_column(source($selectionMap, 'icloud')['objects'], 'markerColor') === [0, 0xDA283C], 'In all-devices mode a color does not depend on the selection checkbox.');
IPS_SetProperty($selectionID, 'ShowAllDevices', false);
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
    ['CarName' => 'Same vehicle name', 'CarMode' => 'json', 'CarFormat' => 'owntracks', 'CarPosition' => $carA, 'CarMarkerColor' => 0xAA1133],
    ['CarName' => 'Same vehicle name', 'CarMode' => 'json', 'CarFormat' => 'traccar', 'CarPosition' => $carB, 'CarMarkerColor' => 0x1000000]
];
IPS_SetProperty($selectionID, 'VehicleMarkerColor', 0x1133AA);
IPS_SetProperty($selectionID, 'Vehicles', json_encode([...$cars, $cars[0]], JSON_THROW_ON_ERROR));
IPS_ApplyChanges($selectionID);
$allSources = tileState($selectionMap)['sources'];
check(count($allSources) === 4 && $allSources[1]['id'] === $demoKey, 'Additional vehicles must leave the demo in place and deduplicate by input ID.');
check($allSources[1]['objects'][0]['markerColor'] === 0x1133AA && $allSources[2]['objects'][0]['markerColor'] === 0xAA1133 && !isset($allSources[3]['objects'][0]['markerColor']), 'Demo and vehicle colors are independent, and values outside RGB use the designer default.');
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
$cars[0]['CarMarkerColor'] = -1;
IPS_SetProperty($selectionID, 'Vehicles', json_encode([$cars[0]], JSON_THROW_ON_ERROR));
IPS_ApplyChanges($selectionID);
check(!isset(tileState($selectionMap)['sources'][2]['objects'][0]['markerColor']), 'Clearing the color restores the designer fallback.');
check($selectionMap->GetReferenceList() === [$accountID, $carA] && !isset($getSubscriptions->invoke($selectionMap)[$carB]), 'Deleting a vehicle must remove its reference and subscription.');
