<?php

declare(strict_types=1);

$converterMapID = IPS_CreateInstance($mapModule);
$converterMap = IPS\InstanceManager::getInstanceInterface($converterMapID);
$bmwID = IPS_CreateVariable(VARIABLETYPE_STRING);
$ownTracksID = IPS_CreateVariable(VARIABLETYPE_STRING);
$getSubscriptions = new ReflectionMethod($converterMap, 'GetMessageList');
$setPosition = static function (int $variableID, array|string $payload) use ($converterMap): array
{
    SetValueString($variableID, is_array($payload) ? json_encode($payload, JSON_THROW_ON_ERROR) : $payload);
    $converterMap->MessageSink(1, $variableID, VM_UPDATE, []);

    return source($converterMap, 'vehicle')['objects'][0];
};
$vehicleForm = static function () use ($converterMap): array
{
    $form = json_decode($converterMap->GetConfigurationForm(), true, 64, JSON_THROW_ON_ERROR);
    $elements = array_column($form['elements'], null, 'name');
    $vehicles = array_column($elements['VehicleSources']['items'], null, 'name');
    return array_column([...$vehicles['ExistingVehicleSettings']['items'], ...$form['actions']], null, 'name');
};
$form = $vehicleForm();
check(
    !$form['VehicleAssignments']['visible'] && !$form['VehicleJSONFormat']['visible']
    && !$form['VehicleAdvanced']['visible'] && !$form['CheckVehicleSource']['visible'],
    'Vehicle assignments, formats and diagnostics must be hidden for a disabled source.'
);

IPS_SetProperty($converterMapID, 'VehicleMode', 'fields');
IPS_ApplyChanges($converterMapID);
$form = $vehicleForm();
$fields = array_column($form['VehicleAssignments']['items'], null, 'name');
check(
    $form['VehicleAssignments']['visible'] && !$form['VehicleJSONFormat']['visible']
    && !$fields['VehiclePosition']['visible'] && $fields['VehicleLatitude']['visible']
    && $fields['VehicleLongitude']['visible'] && $fields['VehicleTimestamp']['visible'],
    'The separate-variable source must show only the three position fields.'
);

IPS_SetProperty($converterMapID, 'VehicleMode', 'bmw-cardata');
IPS_SetProperty($converterMapID, 'VehiclePosition', $bmwID);
IPS_SetProperty($converterMapID, 'VehicleDecimalSeparator', ',');
IPS_SetProperty($converterMapID, 'EnableIPSView', true);
IPS_ApplyChanges($converterMapID);
$configBefore = IPS_GetConfiguration($converterMapID);
$form = $vehicleForm();
$fields = array_column($form['VehicleAssignments']['items'], null, 'name');
check(
    $form['VehicleJSONFormat']['visible'] && $form['VehicleAdvanced']['visible'] && $form['CheckVehicleSource']['visible']
    && $fields['VehiclePosition']['visible'] && !$fields['VehicleLatitude']['visible']
    && !$fields['VehicleLongitude']['visible'] && !$fields['VehicleTimestamp']['visible'],
    'The saved BMW source must show the JSON variable, format and advanced settings.'
);
$sourceOptions = array_column($form['VehicleMode']['options'], 'caption', 'value');
$formatOptions = array_column($form['VehicleJSONFormat']['options'], 'caption', 'value');
check(
    $sourceOptions['bmw-cardata'] === 'One JSON variable' && $formatOptions[''] === 'BMW CarData',
    'A stored BMW mode must appear as JSON with the corresponding format selected.'
);
check(IPS_GetConfiguration($converterMapID) === $configBefore, 'Opening the grouped form must not change saved assignments.');
check(
    $converterMap->GetReferenceList() === [$bmwID] && in_array(VM_UPDATE, $getSubscriptions->invoke($converterMap)[$bmwID] ?? [], true),
    'The BMW input variable must be registered as a reference and update trigger.'
);

$prefix = 'vehicle.cabin.infotainment.navigation.currentLocation.';
$bmw = [
    $prefix . 'latitude'  => ['value' => '52,5201', 'timestamp' => '2025-01-01T12:00:00.123456789Z'],
    $prefix . 'longitude' => ['value' => '13,4051', 'timestamp' => '2025-01-01T13:00:00.123456789+01:00']
];
$bmwFix = ['latitude' => 52.5201, 'longitude' => 13.4051, 'timestamp' => 1735732800, 'accuracy' => null];
$vehicle = $setPosition($bmwID, ['data' => $bmw, 'extra' => str_repeat('x', 4096)]);
check($vehicle['current'] && $vehicle['location'] === $bmwFix, 'The module must read and normalize the raw BMW position.');
$diagnose = static function () use ($converterMap): string
{
    ob_start();
    try {
        $converterMap->RequestAction('CheckVehicleSource', '');
        return ob_get_contents();
    } finally {
        ob_end_clean();
    }
};
check(
    preg_match('/^Valid position\. Measurement age \d+ s\.$/', $diagnose()) === 1,
    'Source diagnostics must report the measurement age without exposing coordinates.'
);

$bmw[$prefix . 'longitude']['timestamp'] = '2025-01-01T12:00:00.123456788Z';
$vehicle = $setPosition($bmwID, $bmw);
check(!$vehicle['current'] && $vehicle['location'] === $bmwFix, 'Different BMW times within one Unix second must keep the last fix.');
check(
    $diagnose() === 'Measurement time is invalid, missing or inconsistent.' && IPS_GetConfiguration($converterMapID) === $configBefore,
    'Source diagnostics must identify a measurement time problem without changing saved assignments.'
);

IPS_SetProperty($converterMapID, 'VehicleMode', 'owntracks');
IPS_SetProperty($converterMapID, 'VehiclePosition', $ownTracksID);
IPS_SetProperty($converterMapID, 'VehicleDecimalSeparator', '.');
IPS_ApplyChanges($converterMapID);
check(
    $converterMap->GetReferenceList() === [$ownTracksID] && !isset($getSubscriptions->invoke($converterMap)[$bmwID]),
    'Changing the input variable must switch the reference and remove the old update trigger.'
);
check(source($converterMap, 'vehicle')['objects'][0]['location'] === null, 'A different input variable must not inherit the BMW position.');

$ownTracks = ['_type' => 'location', 'lat' => '-33.8688', 'lon' => 151.2093, 'tst' => 1735732801, 'acc' => 12.5];
$ownTracksFix = ['latitude' => -33.8688, 'longitude' => 151.2093, 'timestamp' => 1735732801, 'accuracy' => 12.5];
$vehicle = $setPosition($ownTracksID, $ownTracks);
check($vehicle['current'] && $vehicle['location'] === $ownTracksFix, 'The module must retain the OwnTracks position and accuracy.');
$oldFrame = $converterMap->GetIPSViewHTML();
IPS_SetProperty($converterMapID, 'VehicleMode', 'json');
IPS_SetProperty($converterMapID, 'VehicleJSONFormat', 'owntracks');
IPS_ApplyChanges($converterMapID);
check(
    $converterMap->GetIPSViewHTML() === $oldFrame && source($converterMap, 'vehicle')['objects'][0]['location'] === $ownTracksFix,
    'Choosing an equivalent explicit JSON format must preserve the existing IPSView token and position.'
);
$vehicle = $setPosition($ownTracksID, [...$ownTracks, 'lat' => 1]);
check($vehicle['current'] && $vehicle['location'] === $ownTracksFix, 'A repeated output time must not replace the last fix.');

IPS_SetProperty($converterMapID, 'VehicleTimeFormat', 'unix-milliseconds');
IPS_ApplyChanges($converterMapID);
$vehicle = $setPosition($ownTracksID, ['lat' => 0, 'lon' => 0, 'tst' => '1735732802999']);
$latest = ['latitude' => 0.0, 'longitude' => 0.0, 'timestamp' => 1735732802, 'accuracy' => null];
check($vehicle['current'] && $vehicle['location'] === $latest, 'The module must truncate milliseconds and accept zero coordinates.');
$webContent = $converterMap->GetIPSViewHTML();
$token = bootstrap($webContent)['options']['token'];
[$status, $body] = mapRequest($converterMap, '/hook/OpenWoIst' . $converterMapID . '/state', token: $token);
check(
    $status === 200 && json_decode($body, true, 64, JSON_THROW_ON_ERROR)['sources'][0]['objects'][0]['location'] === $latest,
    'The IPSView endpoint must return the same converted position as the tile.'
);

foreach ([
    ['lat' => 1, 'lon' => 2, 'tst' => 1735732801000],
    ['lat' => 1, 'lon' => 2, 'tst' => 1735732803],
    ['lat' => 1, 'lon' => 2],
    ['lat' => 91, 'lon' => 2, 'tst' => 1735732804000],
    ['lat' => 1, 'lon' => '1e1000', 'tst' => 1735732804000],
    [...$ownTracks, '_type' => 'transition', 'tst' => 1735732804000],
    '{"private":'
] as $payload) {
    $vehicle = $setPosition($ownTracksID, $payload);
    check(!$vehicle['current'] && $vehicle['location'] === $latest, 'Invalid or older input must keep the last normalized position.');
}

IPS_SetProperty($converterMapID, 'VehicleTimeFormat', 'iso8601');
IPS_ApplyChanges($converterMapID);
foreach (['2025-01-01T12:00:04', '2025-02-30T12:00:04Z'] as $timestamp) {
    $vehicle = $setPosition($ownTracksID, ['lat' => 1, 'lon' => 2, 'tst' => $timestamp]);
    check(!$vehicle['current'] && $vehicle['location'] === $latest, 'Invalid ISO 8601 input must keep the last normalized position.');
}

$traccarID = IPS_CreateVariable(VARIABLETYPE_STRING);
IPS_SetProperty($converterMapID, 'VehicleJSONFormat', 'traccar');
IPS_SetProperty($converterMapID, 'VehiclePosition', $traccarID);
IPS_SetProperty($converterMapID, 'VehicleTimeFormat', 'default');
IPS_ApplyChanges($converterMapID);
check($converterMap->GetReferenceList() === [$traccarID], 'Traccar must subscribe to its configured raw JSON variable.');
$formats = array_column($vehicleForm()['VehicleJSONFormat']['options'], 'caption', 'value');
check($formats['traccar'] === 'Traccar', 'Traccar must be selectable from the JSON formats.');

$traccar = [
    'latitude'   => 47.7,
    'longitude'  => 8.2,
    'fixTime'    => '2025-01-01T14:00:04.700+02:00',
    'deviceTime' => '2025-01-01T12:00:06Z',
    'serverTime' => '2025-01-01T12:00:07Z',
    'valid'      => true,
    'accuracy'   => 14
];
$vehicle = $setPosition($traccarID, $traccar);
check(
    $vehicle['current'] && $vehicle['location'] === ['latitude' => 47.7, 'longitude' => 8.2, 'timestamp' => 1735732804, 'accuracy' => 14.0],
    'Traccar must use fixTime and retain its accuracy.'
);

$traccar['fixTime'] = '2025-01-01T12:00:05.199Z';
$traccar['latitude'] = 47.8;
unset($traccar['accuracy']);
$vehicle = $setPosition($traccarID, ['position' => $traccar]);
$traccarFix = ['latitude' => 47.8, 'longitude' => 8.2, 'timestamp' => 1735732805, 'accuracy' => null];
check($vehicle['current'] && $vehicle['location'] === $traccarFix, 'The Traccar forwarding envelope must also be accepted.');
$token = bootstrap($converterMap->GetIPSViewHTML())['options']['token'];
[$status, $body] = mapRequest($converterMap, '/hook/OpenWoIst' . $converterMapID . '/state', token: $token);
check(
    $status === 200 && json_decode($body, true, 64, JSON_THROW_ON_ERROR)['sources'][0]['objects'][0]['location'] === $traccarFix,
    'The Traccar position must be identical in the native tile and IPSView state.'
);

$vehicle = $setPosition($traccarID, [...$traccar, 'valid' => false, 'fixTime' => '2025-01-01T12:00:08Z']);
check(!$vehicle['current'] && $vehicle['location'] === $traccarFix, 'An invalid Traccar fix must keep the previous position.');
unset($traccar['fixTime']);
$vehicle = $setPosition($traccarID, $traccar);
check(
    !$vehicle['current'] && $vehicle['location'] === $traccarFix
    && $diagnose() === 'Measurement time is invalid, missing or inconsistent.',
    'Traccar deviceTime and serverTime must not substitute for a missing fixTime.'
);
