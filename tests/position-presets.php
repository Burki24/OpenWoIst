<?php

declare(strict_types=1);

$presetID = IPS_CreateInstance($mapModule);
$presetMap = IPS\InstanceManager::getInstanceInterface($presetID);
IPS_SetProperty($presetID, 'Accounts', $accountsValue);
IPS_SetProperty($presetID, 'VehicleMode', 'demo');
IPS_SetProperty($presetID, 'EnableIPSView', true);
IPS_ApplyChanges($presetID);
$existing = tileState($presetMap)['sources'];
$webContent = IPS_GetObjectIDByIdent('IPSViewOpenWoIst', $presetID);

$smartcar = [IPS_CreateVariable(VARIABLETYPE_FLOAT), IPS_CreateVariable(VARIABLETYPE_FLOAT), IPS_CreateVariable(VARIABLETYPE_INTEGER)];
$hyundai = [IPS_CreateVariable(VARIABLETYPE_FLOAT), IPS_CreateVariable(VARIABLETYPE_FLOAT), IPS_CreateVariable(VARIABLETYPE_STRING)];
$additionalPresets = ['volvo' => 'Volvo', 'renault-ze' => 'RenaultZE', 'tronity' => 'Tronity'];
$rows = [
    [
        'CarName'           => 'Smartcar test',
        'CarMode'           => 'smartcar',
        'SmartcarLatitude'  => $smartcar[0],
        'SmartcarLongitude' => $smartcar[1],
        'SmartcarTimestamp' => $smartcar[2],
        'CarMarkerColor'    => 0x117744
    ],
    [
        'CarName'          => 'Hyundai test',
        'CarMode'          => 'hyundai-kia',
        'HyundaiLatitude'  => $hyundai[0],
        'HyundaiLongitude' => $hyundai[1],
        'HyundaiTimestamp' => $hyundai[2]
    ]
];

$originalTimezone = date_default_timezone_get();
date_default_timezone_set('Europe/Berlin');
try {
    SetValueFloat($smartcar[0], 51.1);
    SetValueFloat($smartcar[1], -9.2);
    SetValueInteger($smartcar[2], 1735732800);
    SetValueFloat($hyundai[0], 52.1);
    SetValueFloat($hyundai[1], 13.2);
    SetValueString($hyundai[2], '20250101130000');
    $assigned = json_encode($rows, JSON_THROW_ON_ERROR);
    IPS_SetProperty($presetID, 'Vehicles', $assigned);
    IPS_ApplyChanges($presetID);

    $config = IPS_GetConfiguration($presetID);
    $form = json_decode($presetMap->GetConfigurationForm(), true, 64, JSON_THROW_ON_ERROR);
    $vehicleList = array_column($form['elements'][0]['items'], null, 'name')['Vehicles'];
    $options = array_column($vehicleList['form'][1]['options'], null, 'value');
    check(isset($options['smartcar'], $options['hyundai-kia']), 'The vehicle list offers both provider presets.');
    $formInputs = array_column(array_slice($vehicleList['form'], 2), 'items', 'caption');
    $columns = array_column($vehicleList['columns'], null, 'name');
    foreach ($additionalPresets as $mode => $prefix) {
        check(isset($options[$mode]), 'The vehicle list offers the ' . $prefix . ' preset.');
        $inputs = $options[$mode]['items'] ?? $formInputs[$options[$mode]['caption']];
        $keys = [$prefix . 'Latitude', $prefix . 'Longitude', $prefix . 'Timestamp'];
        check(array_column($inputs, 'name') === $keys && $inputs[2]['validVariableTypes'] === [VARIABLETYPE_INTEGER], 'Each new preset selects the two coordinates and an integer position time.');
        check(array_keys(array_filter(array_intersect_key($columns, array_flip($keys)), static fn (array $column): bool => $column['save'] === true)) === $keys, 'Each new preset stores its three variable IDs.');
    }
    foreach (['smartcar' => ['SmartcarTimestamp', VARIABLETYPE_INTEGER], 'hyundai-kia' => ['HyundaiTimestamp', VARIABLETYPE_STRING]] as $mode => [$name, $type]) {
        $inputs = array_column($options[$mode]['items'] ?? $formInputs[$options[$mode]['caption']], null, 'name');
        check($inputs[$name]['validVariableTypes'] === [$type], 'Each preset offers a selector of the appropriate timestamp type.');
    }
    check(IPS_GetConfiguration($presetID) === $config && IPS_GetProperty($presetID, 'Vehicles') === $assigned, 'Reading the form must preserve saved assignments.');

    $sources = tileState($presetMap)['sources'];
    check(array_slice($sources, 0, 2) === $existing, 'Adding the presets must preserve the iCloud and demo sources.');
    $first = $sources[2]['objects'][0];
    $second = $sources[3]['objects'][0];
    check($first['location'] === ['latitude' => 51.1, 'longitude' => -9.2, 'timestamp' => 1735732800, 'accuracy' => null] && $first['markerColor'] === 0x117744, 'Smartcar uses its OEM measurement time and selected color.');
    check($second['location'] === ['latitude' => 52.1, 'longitude' => 13.2, 'timestamp' => 1735732800, 'accuracy' => null], 'Hyundai interprets the reported time in the Symcon time zone.');
    check($presetMap->GetReferenceList() === [$accountID, ...$smartcar, ...$hyundai], 'Both presets register all assigned variables by ID.');
    $subscriptions = new ReflectionMethod($presetMap, 'GetMessageList');
    $triggers = array_keys(array_filter($subscriptions->invoke($presetMap), static fn (array $messages): bool => in_array(VM_UPDATE, $messages, true)));
    check(count($triggers) === 3 && array_diff([$smartcar[2], $hyundai[2], IPS_GetObjectIDByIdent('DemoPosition', $presetID)], $triggers) === [], 'Position variables subscribe only to the timestamp written last.');

    $embed = $presetMap->GetIPSViewHTML();
    SetValueFloat($smartcar[0], 51.3);
    $presetMap->Update();
    check(tileState($presetMap)['sources'][2]['objects'][0] === $first, 'Polling during a coordinate-only update must retain the previous Smartcar fix.');
    SetValueFloat($smartcar[1], -9.4);
    SetValueInteger($smartcar[2], 1735732801);
    $presetMap->MessageSink(1, $smartcar[2], VM_UPDATE, []);
    check(tileState($presetMap)['sources'][2]['objects'][0]['location'] === ['latitude' => 51.3, 'longitude' => -9.4, 'timestamp' => 1735732801, 'accuracy' => null], 'Smartcar moves after the complete position is published.');

    foreach (['', '20250230140000', '20250330023000', '20251026023000', '2025-07-01T14:00:00+02:00'] as $invalid) {
        SetValueString($hyundai[2], $invalid);
        $presetMap->MessageSink(1, $hyundai[2], VM_UPDATE, []);
        $object = tileState($presetMap)['sources'][3]['objects'][0];
        check(!$object['current'] && $object['location'] === $second['location'], 'Invalid or ambiguous Hyundai timestamps retain the last known fix.');
    }
    SetValueFloat($hyundai[0], 52.3);
    SetValueFloat($hyundai[1], 13.4);
    SetValueString($hyundai[2], '20250701140000');
    $presetMap->MessageSink(1, $hyundai[2], VM_UPDATE, []);
    $updated = tileState($presetMap)['sources'];
    check($updated[3]['objects'][0]['current'] && $updated[3]['objects'][0]['location'] === ['latitude' => 52.3, 'longitude' => 13.4, 'timestamp' => 1751371200, 'accuracy' => null], 'Hyundai moves and accounts for summer time.');
    $presetAccess = bootstrap($embed)['options'];
    [$status, $body] = mapRequest($presetMap, dirname($presetAccess['page']) . '/state', token: $presetAccess['token']);
    check($status === 200 && json_decode($body, true, 32, JSON_THROW_ON_ERROR)['sources'] === $updated, 'Both presets deliver identical fixes to the tile and IPSView.');
    check(IPS_GetObjectIDByIdent('IPSViewOpenWoIst', $presetID) === $webContent && $presetMap->GetIPSViewHTML() === $embed, 'Position changes must preserve the IPSView variable and embedding.');

    $fixtures = [];
    $time = 1735732900;
    foreach ($additionalPresets as $mode => $prefix) {
        $ids = [IPS_CreateVariable(VARIABLETYPE_FLOAT), IPS_CreateVariable(VARIABLETYPE_FLOAT), IPS_CreateVariable(VARIABLETYPE_INTEGER)];
        $initial = ['latitude' => 53.0 + count($fixtures), 'longitude' => -1.25, 'timestamp' => $time, 'accuracy' => null];
        SetValueFloat($ids[0], $initial['latitude']);
        SetValueFloat($ids[1], $initial['longitude']);
        SetValueInteger($ids[2], $initial['timestamp']);
        $rows[] = [
            'CarName'              => $prefix . ' test',
            'CarMode'              => $mode,
            $prefix . 'Latitude'   => $ids[0],
            $prefix . 'Longitude'  => $ids[1],
            $prefix . 'Timestamp'  => $ids[2]
        ];
        $fixtures[] = ['ids' => $ids, 'initial' => $initial, 'longitudeFirst' => $mode === 'volvo'];
    }
    $savedVehicles = json_encode($rows, JSON_THROW_ON_ERROR);
    IPS_SetProperty($presetID, 'Vehicles', $savedVehicles);
    IPS_ApplyChanges($presetID);
    $all = tileState($presetMap)['sources'];
    check(count($all) === 7 && array_slice($all, 0, 4) === $updated, 'Existing iCloud, demo, and vehicle assignments survive adding the new presets.');
    $embed = $presetMap->GetIPSViewHTML();
    $expected = $all;
    foreach ($fixtures as $index => $fixture) {
        [$latitude, $longitude, $timestamp] = $fixture['ids'];
        $sourceIndex = $index + 4;
        check($all[$sourceIndex]['objects'][0]['location'] === $fixture['initial'], 'The initial source uses the original Unix position time.');
        $secondFix = ['latitude' => $fixture['initial']['latitude'] + 0.5, 'longitude' => -0.75, 'timestamp' => $time + 1, 'accuracy' => null];
        $coordinates = $fixture['longitudeFirst']
            ? [$longitude => $secondFix['longitude'], $latitude => $secondFix['latitude']]
            : [$latitude => $secondFix['latitude'], $longitude => $secondFix['longitude']];
        $firstCoordinate = array_key_first($coordinates);
        SetValueFloat($firstCoordinate, $coordinates[$firstCoordinate]);
        $presetMap->Update();
        check(tileState($presetMap)['sources'] === $expected, 'A partial coordinate write with the previous time must not move any vehicle.');
        $lastCoordinate = array_key_last($coordinates);
        SetValueFloat($lastCoordinate, $coordinates[$lastCoordinate]);
        SetValueInteger($timestamp, 0);
        $presetMap->MessageSink(1, $timestamp, VM_UPDATE, []);
        $expected[$sourceIndex]['objects'][0]['current'] = false;
        check(tileState($presetMap)['sources'] === $expected, 'A missing position time retains the last known fix.');

        SetValueInteger($timestamp, $secondFix['timestamp']);
        $presetMap->MessageSink(1, $timestamp, VM_UPDATE, []);
        $expected[$sourceIndex]['objects'][0]['location'] = $secondFix;
        $expected[$sourceIndex]['objects'][0]['current'] = true;
        check(tileState($presetMap)['sources'] === $expected, 'Only the matching marker moves once the provider writes the new position time.');
    }
    $presetAccess = bootstrap($embed)['options'];
    [$status, $body] = mapRequest($presetMap, dirname($presetAccess['page']) . '/state', token: $presetAccess['token']);
    check($status === 200 && json_decode($body, true, 32, JSON_THROW_ON_ERROR)['sources'] === $expected, 'IPSView shows the same new fixes as the tile.');
    check(IPS_GetProperty($presetID, 'Vehicles') === $savedVehicles && IPS_GetObjectIDByIdent('IPSViewOpenWoIst', $presetID) === $webContent && $presetMap->GetIPSViewHTML() === $embed, 'Live positions preserve the saved assignments and IPSView embedding.');
} finally {
    date_default_timezone_set($originalTimezone);
}
