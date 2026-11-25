<?php

declare(strict_types=1);

$tessieModule = '{3F1F7E31-8BA0-4B8F-9B62-47DAD7A0B6C9}';
$modules = $metadata->getValue();
$modules[$tessieModule] = array_replace($modules[$mapModule], ['Class' => TestPositionProvider::class, 'ModuleID' => $tessieModule, 'Prefix' => 'TESTTESSIE']);
$metadata->setValue(null, $modules);

$directTessieID = IPS_CreateInstance($mapModule);
$directTessie = IPS\InstanceManager::getInstanceInterface($directTessieID);
IPS_SetProperty($directTessieID, 'EnableIPSView', true);
IPS_SetProperty($directTessieID, 'VehicleMode', 'demo');
IPS_SetProperty($directTessieID, 'Accounts', json_encode([['AccountID' => $accountID]], JSON_THROW_ON_ERROR));
$restVariable = IPS_CreateVariable(VARIABLETYPE_STRING);
SetValueString($restVariable, json_encode(['drive_state' => ['latitude' => 51.25, 'longitude' => 7.5, 'gps_as_of' => $now - 40]], JSON_THROW_ON_ERROR));
$restSettings = json_encode([['SourceName' => 'Tessie REST', 'SourcePosition' => $restVariable]], JSON_THROW_ON_ERROR);
IPS_SetProperty($directTessieID, 'TessieVehicles', $restSettings);
IPS_ApplyChanges($directTessieID);
$otherSources = array_column(tileState($directTessie)['sources'], null, 'id');

$tessieRows = $tessieVariables = [];
foreach ([0x1188AA, 0xCC5522] as $index => $color) {
    $instance = IPS_CreateInstance($tessieModule);
    IPS_SetName($instance, 'Tessie test');
    $tessieRows[] = ['SourceInstance' => $instance, 'SourceMarkerColor' => $color];
    foreach (['stat_tel_Location_lat', 'stat_tel_Location_lon', 'stat_tel_Soc'] as $ident) {
        $variable = IPS_CreateVariable(VARIABLETYPE_FLOAT);
        IPS_SetParent($variable, $instance);
        IPS_SetIdent($variable, $ident);
        $tessieVariables[$index][] = $variable;
        SetValueFloat($variable, 1.25);
    }
}
IPS_SetProperty($directTessieID, 'NRGTessieVehicles', json_encode($tessieRows, JSON_THROW_ON_ERROR));
IPS_ApplyChanges($directTessieID);
$tessiePanel = $providerPanels($directTessie)['NRGTessieVehiclesSection'];
$items = array_column($tessiePanel['items'], null, 'name');
$list = $items['NRGTessieVehicles'];
$columns = array_column($list['columns'], null, 'name');
check($tessiePanel['caption'] === 'Tessie' && $list['add'] && $list['delete'] && array_column($list['values'], 'SourceName') === ['Tessie test', 'Tessie test']
    && $columns['SourceInstance']['edit']['validModules'] === [$tessieModule] && !isset($columns['SourcePosition']) && !isset($columns['SourceLatitude'])
    && isset($items['TessieVehiclesSection']), 'Tessie offers direct device selection above the existing JSON configuration.');

$liveSources = static fn (): array => array_values(array_filter(tileState($directTessie)['sources'], static fn (array $source): bool => ($source['timeBasis'] ?? '') === 'receipt'));
$subscriptions = (new ReflectionMethod($directTessie, 'GetMessageList'))->invoke($directTessie);
foreach ($tessieVariables as [$latitude, $longitude, $soc]) {
    check(in_array(VM_UPDATE, $subscriptions[$latitude] ?? [], true) && in_array(VM_UPDATE, $subscriptions[$longitude] ?? [], true)
        && !isset($subscriptions[$soc]), 'Only Tessie coordinate events can change its received position.');
}
check($liveSources()[0]['objects'][0]['location'] === null, 'Existing independent values cannot initialize Tessie as a newly received position.');
$embed = $directTessie->GetIPSViewHTML();
$access = bootstrap($embed)['options'];
$clock = new ReflectionProperty(IPS\TimeManager::class, 'time');
$previousClock = $clock->getValue();
$tessieSequence = 0;
$write = static function (int $vehicle, int $field, float $value, int $at) use ($directTessie, $clock, $tessieVariables, &$tessieSequence): void
{
    $clock->setValue(null, $at);
    $variable = $tessieVariables[$vehicle][$field];
    SetValueFloat($variable, $value);
    $directTessie->MessageSink(++$tessieSequence, $variable, VM_UPDATE, [$value]);
};
try {
    $firstTime = $now - 20;
    $write(0, 0, 51.25, $firstTime);
    $write(1, 0, 52.25, $firstTime);
    $directTessie->Update();
    check($liveSources()[0]['objects'][0]['location'] === null, 'A latitude write and a normal refresh do not assemble a partial position.');
    $write(1, 1, -8.5, $firstTime);
    $write(0, 1, 7.5, $firstTime);
    $first = $liveSources();
    check(
        count(array_unique(array_column($first, 'id'))) === 2
        && $first[0]['objects'][0]['location'] === ['latitude' => 51.25, 'longitude' => 7.5, 'timestamp' => $firstTime, 'accuracy' => null]
        && $first[1]['objects'][0]['location']['longitude'] === -8.5 && $first[1]['objects'][0]['markerColor'] === 0xCC5522,
        'Two interleaved Tesla instances keep their own coordinates, reception time and marker colors.'
    );

    $write(0, 2, 49.0, $now - 15);
    $write(0, 0, 52.75, $now - 14);
    $write(0, 1, 8.0, $now - 13);
    check($liveSources() === $first, 'Charging updates and coordinates written in different seconds keep the last received position.');
    $write(0, 0, 52.75, $now - 10);
    $write(0, 1, 7.5, $now - 10);
    $second = $liveSources();
    check($second[0]['objects'][0]['location'] === ['latitude' => 52.75, 'longitude' => 7.5, 'timestamp' => $now - 10, 'accuracy' => null]
        && $second[1] === $first[1], 'The next pair moves only the updated vehicle, including when one coordinate repeats.');
    $write(0, 0, 52.75, $now - 8);
    $write(0, 1, 7.5, $now - 8);
    check($liveSources()[0]['objects'][0]['location']['timestamp'] === $now - 8, 'A stationary vehicle can report its position again.');
    $write(0, 1, 8.75, $now - 6);
    $directTessie->Update();
    check($liveSources()[0]['objects'][0]['location']['longitude'] === 7.5, 'A longitude-only update keeps the last accepted pair.');
} finally {
    $clock->setValue(null, $previousClock);
}
$state = tileState($directTessie);
[$status, $body] = mapRequest($directTessie, dirname($access['page']) . '/state', token: $access['token']);
check($status === 200 && json_decode($body, true, 32, JSON_THROW_ON_ERROR)['sources'] === $state['sources'], 'Kachel and IPSView use the same Tessie data and distinguish the reception time.');
check($embed === $directTessie->GetIPSViewHTML() && IPS_GetProperty($directTessieID, 'TessieVehicles') === $restSettings
    && array_intersect_key(array_column($state['sources'], null, 'id'), $otherSources) === $otherSources, 'Tessie selection preserves the JSON assignment, iCloud, test vehicle and IPSView embedding.');
