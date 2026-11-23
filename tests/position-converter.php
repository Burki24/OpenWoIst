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
$configuredFields = static function () use ($converterMap): array
{
    $form = json_decode($converterMap->GetConfigurationForm(), true, 64, JSON_THROW_ON_ERROR);
    $groups = array_column($form['elements'], null, 'caption');
    return array_column($groups['Vehicle variable assignments']['items'], null, 'name');
};
check(!$configuredFields()['VehicleTimeFormat']['visible'], 'Converter settings must be hidden for existing non-converter sources.');

IPS_SetProperty($converterMapID, 'VehicleMode', 'bmw-cardata');
IPS_SetProperty($converterMapID, 'VehiclePosition', $bmwID);
IPS_SetProperty($converterMapID, 'VehicleDecimalSeparator', ',');
IPS_SetProperty($converterMapID, 'EnableIPSView', true);
IPS_ApplyChanges($converterMapID);
check(
    $configuredFields()['VehicleTimeFormat']['visible'] && $configuredFields()['VehicleDecimalSeparator']['visible'],
    'Both converter settings must be available for a BMW source.'
);
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

$bmw[$prefix . 'longitude']['timestamp'] = '2025-01-01T12:00:00.123456788Z';
$vehicle = $setPosition($bmwID, $bmw);
check(!$vehicle['current'] && $vehicle['location'] === $bmwFix, 'Different BMW times within one Unix second must keep the last fix.');

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
