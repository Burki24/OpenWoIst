<?php

declare(strict_types=1);

$fressnapfModule = '{AF92FA56-B339-6D82-B015-DF819C042C29}';
$modules = $metadata->getValue();
$modules[$fressnapfModule] = array_replace($modules[$mapModule], [
    'Class'    => TestPositionProvider::class,
    'ModuleID' => $fressnapfModule,
    'Prefix'   => 'TESTFRT'
]);
$metadata->setValue(null, $modules);
$fressnapfInstance = IPS_CreateInstance($fressnapfModule);
$fressVars = [];
foreach ([
    'Latitude'  => VARIABLETYPE_FLOAT,
    'Longitude' => VARIABLETYPE_FLOAT,
    'SampledAt' => VARIABLETYPE_STRING,
    'Accuracy'  => VARIABLETYPE_INTEGER,
    'Name'      => VARIABLETYPE_STRING
] as $ident => $type) {
    $variable = IPS_CreateVariable($type);
    IPS_SetParent($variable, $fressnapfInstance);
    IPS_SetIdent($variable, $ident);
    $fressVars[$ident] = $variable;
}
SetValueString($fressVars['Name'], 'Milo');
$fressMapID = IPS_CreateInstance($mapModule);
$fressMap = IPS\InstanceManager::getInstanceInterface($fressMapID);
IPS_SetProperty($fressMapID, 'EnableIPSView', true);
IPS_SetProperty($fressMapID, 'FressnapfTrackers', json_encode([
    ['SourceInstance' => $fressnapfInstance, 'SourceMarkerColor' => 0xDA283C]
], JSON_THROW_ON_ERROR));
IPS_ApplyChanges($fressMapID);
$form = json_decode($fressMap->GetConfigurationForm(), true, 64, JSON_THROW_ON_ERROR);
$petGroup = array_column($form['elements'], null, 'name')['PetSources'];
$fressPanel = array_column($petGroup['items'], null, 'name')['FressnapfTrackersSection'];
$fressList = array_column($fressPanel['items'], null, 'name')['FressnapfTrackers'];
$columns = array_column($fressList['columns'], null, 'name');
check($fressList['add'] && $columns['SourceInstance']['edit']['validModules'] === [$fressnapfModule]
    && $columns['SourceName']['caption'] === 'Pet name' && $fressList['values'][0]['SourceName'] === 'Milo', 'Fressnapf uses a filtered device list and suggests the locally stored pet name.');

$writeFress = static function (string $ident, mixed $value, int $at) use ($fressMap, $fressVars): void
{
    $id = $fressVars[$ident];
    SetValue($id, $value);
    $fressMap->MessageSink($at, $id, VM_UPDATE, [$value]);
};
$messageList = (new ReflectionMethod($fressMap, 'GetMessageList'))->invoke($fressMap);
foreach (['Latitude', 'Longitude', 'SampledAt', 'Accuracy'] as $field) {
    check(in_array(VM_UPDATE, $messageList[$fressVars[$field]] ?? [], true), 'The map subscribes to all Fressnapf position variables.');
}
$accessFress = bootstrap($fressMap->GetIPSViewHTML())['options'];
$fressPath = dirname($accessFress['page']) . '/state';
$firstSample = $now - 45;
$localSample = (new DateTimeImmutable('@' . $firstSample))->setTimezone(new DateTimeZone('+02:00'))->format('Y-m-d\TH:i:s.000P');
$writeFress('Latitude', 51.25, $now);
$writeFress('Longitude', 6.25, $now);
$writeFress('Accuracy', 12, $now);
check(source($fressMap, 'tracker')['objects'][0]['location'] === null, 'Coordinates remain pending until the measurement time arrives.');
$writeFress('SampledAt', $localSample, $now + 1);
$firstObject = source($fressMap, 'tracker')['objects'][0];
check($firstObject['name'] === 'Milo' && $firstObject['markerColor'] === 0xDA283C
    && $firstObject['location'] === ['latitude' => 51.25, 'longitude' => 6.25, 'timestamp' => $firstSample, 'accuracy' => 12.0], 'A complete Fressnapf fix uses the timezone offset and the accuracy from its own report.');

$writeFress('Latitude', 50.5, $now + 2);
$writeFress('SampledAt', gmdate('c', $now - 40), $now + 2);
check(source($fressMap, 'tracker')['objects'][0] === $firstObject, 'A partial report cannot use the previous longitude.');
$writeFress('Latitude', 50.5, $now + 3);
$writeFress('Longitude', 7.5, $now + 3);
$writeFress('SampledAt', '2026-11-24T12:10:00', $now + 3);
check(source($fressMap, 'tracker')['objects'][0] === $firstObject, 'A timezone-less measurement cannot replace the accepted fix.');
$writeFress('Latitude', 50.75, $now + 4);
$writeFress('Longitude', 7.75, $now + 4);
$writeFress('SampledAt', gmdate('c', $now - 25), $now + 20);
check(source($fressMap, 'tracker')['objects'][0] === $firstObject, 'Coordinates from an abandoned report cannot be reused by a later timestamp.');
$writeFress('Latitude', 52.5, $now + 21);
$writeFress('Longitude', 8.5, $now + 21);
$writeFress('SampledAt', gmdate('c', $now - 20), $now + 21);
$finalObject = source($fressMap, 'tracker')['objects'][0];
check($finalObject['id'] === $firstObject['id']
    && $finalObject['location'] === ['latitude' => 52.5, 'longitude' => 8.5, 'timestamp' => $now - 20, 'accuracy' => null], 'The second fix updates the same marker and does not reuse old accuracy.');
[$statusFress, $bodyFress] = mapRequest($fressMap, $fressPath, token: $accessFress['token']);
check($statusFress === 200 && json_decode($bodyFress, true, 32, JSON_THROW_ON_ERROR)['sources'][0]['objects'][0] === $finalObject, 'IPSView receives the same Fressnapf marker as the tile.');
