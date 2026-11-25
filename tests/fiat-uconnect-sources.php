<?php

declare(strict_types=1);

$fiatModule = '{A0D064E8-44E3-206A-9B5E-87F12B666EA7}';
$modules = $metadata->getValue();
$modules[$fiatModule] = array_replace($modules[$mapModule], ['Class' => TestPositionProvider::class, 'ModuleID' => $fiatModule, 'Prefix' => 'TESTFIAT']);
$metadata->setValue(null, $modules);

$fiatMapID = IPS_CreateInstance($mapModule);
$fiatMap = IPS\InstanceManager::getInstanceInterface($fiatMapID);
IPS_SetProperty($fiatMapID, 'EnableIPSView', true);
$fiatRows = $fiatVariables = [];
foreach (['Fiat one', 'Fiat two'] as $index => $name) {
    $instance = IPS_CreateInstance($fiatModule);
    IPS_SetName($instance, $name);
    $fiatRows[] = ['SourceInstance' => $instance];
    foreach (['CurrentLatitude' => VARIABLETYPE_FLOAT, 'CurrentLongitude' => VARIABLETYPE_FLOAT, 'LastPositionUpdate' => VARIABLETYPE_INTEGER] as $ident => $type) {
        $variable = IPS_CreateVariable($type);
        IPS_SetParent($variable, $instance);
        IPS_SetIdent($variable, $ident);
        $fiatVariables[$index][] = $variable;
    }
}
IPS_SetProperty($fiatMapID, 'FiatUconnectVehicles', json_encode($fiatRows, JSON_THROW_ON_ERROR));
IPS_ApplyChanges($fiatMapID);
$fiatPanel = $providerPanels($fiatMap)['FiatUconnectVehiclesSection'];
$list = array_column($fiatPanel['items'], null, 'name')['FiatUconnectVehicles'];
$columns = array_column($list['columns'], null, 'name');
check($list['add'] && array_column($list['values'], 'SourceName') === ['Fiat one', 'Fiat two']
    && $columns['SourceInstance']['edit']['validModules'] === [$fiatModule] && !isset($columns['SourceLatitude']), 'Fiat uses direct instance selection for each vehicle.');

$fiatState = static fn (): array => array_column(tileState($fiatMap)['sources'], null, 'name');
$fiatFix = static fn (string $name): ?array => $fiatState()[$name]['objects'][0]['location'];
$access = bootstrap($fiatMap->GetIPSViewHTML())['options'];
$clock = new ReflectionProperty(IPS\TimeManager::class, 'time');
$previousClock = $clock->getValue();
$writeFiat = static function (int $vehicle, int $field, int|float $value, int $at) use ($fiatMap, $clock, $fiatVariables): void
{
    $clock->setValue(null, $at);
    $variable = $fiatVariables[$vehicle][$field];
    SetValue($variable, $value);
    $fiatMap->MessageSink($at, $variable, VM_UPDATE, [$value]);
};
try {
    $firstAt = $now - 16;
    $writeFiat(0, 0, 50.0, $firstAt);
    $writeFiat(1, 0, 52.0, $firstAt);
    $writeFiat(0, 1, 8.0, $firstAt);
    check($fiatFix('Fiat one') === null, 'A Fiat fix waits for the source measurement time.');
    $writeFiat(1, 1, 13.0, $firstAt);
    $writeFiat(0, 2, $now - 90, $firstAt + 1);
    $writeFiat(1, 2, $now - 80, $firstAt + 1);
    $firstFiat = $fiatFix('Fiat one');
    check($firstFiat === ['latitude' => 50.0, 'longitude' => 8.0, 'timestamp' => $now - 90, 'accuracy' => null]
        && $fiatFix('Fiat two')['longitude'] === 13.0, 'A report crossing one update second retains its measurement time and its own vehicle.');

    $writeFiat(0, 0, 51.0, $firstAt + 3);
    $writeFiat(0, 1, 9.0, $firstAt + 4);
    $writeFiat(0, 2, $now - 70, $firstAt + 5);
    $writeFiat(0, 1, 9.5, $firstAt + 5);
    $writeFiat(0, 2, $now - 60, $firstAt + 5);
    check($fiatFix('Fiat one') === $firstFiat, 'A two-second span and a partial report in the same second retain the last fix.');

    $writeFiat(0, 0, 50.0, $firstAt + 7);
    $writeFiat(0, 1, 9.5, $firstAt + 7);
    $writeFiat(0, 2, $now - 50, $firstAt + 7);
    $lastFiat = $fiatFix('Fiat one');
    check($lastFiat === ['latitude' => 50.0, 'longitude' => 9.5, 'timestamp' => $now - 50, 'accuracy' => null], 'Fiat accepts a fresh report even when a coordinate is unchanged.');

    $writeFiat(0, 0, 51.0, $firstAt + 8);
    $writeFiat(0, 0, 52.0, $firstAt + 8);
    $writeFiat(0, 1, 10.0, $firstAt + 8);
    $writeFiat(0, 2, $now - 40, $firstAt + 8);
    check($fiatFix('Fiat one') === $lastFiat, 'A repeated coordinate within the same report is treated as ambiguous.');

    $writeFiat(0, 0, 51.0, $firstAt + 10);
    $writeFiat(0, 1, 10.0, $firstAt + 10);
    $writeFiat(0, 2, $now - 50, $firstAt + 10);
    check($fiatFix('Fiat one') === $lastFiat, 'A repeated GPS measurement time cannot replace the last position.');
} finally {
    $clock->setValue(null, $previousClock);
}
[$status, $body] = mapRequest($fiatMap, dirname($access['page']) . '/state', token: $access['token']);
check($status === 200 && json_decode($body, true, 32, JSON_THROW_ON_ERROR)['sources'] === tileState($fiatMap)['sources'], 'Both map outputs show the same accepted Fiat positions.');
