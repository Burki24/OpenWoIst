<?php

declare(strict_types=1);

$trackerMapID = IPS_CreateInstance($mapModule);
$trackerMap = IPS\InstanceManager::getInstanceInterface($trackerMapID);
IPS_SetProperty($trackerMapID, 'Accounts', $accountsValue);
IPS_SetProperty($trackerMapID, 'VehicleMode', 'demo');
IPS_SetProperty($trackerMapID, 'EnableIPSView', true);
IPS_ApplyChanges($trackerMapID);
$original = tileState($trackerMap)['sources'];
$webContentID = IPS_GetObjectIDByIdent('IPSViewOpenWoIst', $trackerMapID);
$originalAccess = bootstrap($trackerMap->GetIPSViewHTML())['options'];
$path = dirname($originalAccess['page']) . '/state';
check(mapRequest($trackerMap, $path, token: $originalAccess['token'])[0] === 200, 'An empty tracker list preserves the existing IPSView access.');

$form = json_decode($trackerMap->GetConfigurationForm(), true, 64, JSON_THROW_ON_ERROR);
$group = array_column($form['elements'], null, 'name')['LocationDeviceSources'];
$panels = array_column($group['items'], null, 'name');
check(isset($panels['OwnTracksDevicesSection'], $panels['TraccarDevicesSection'], $panels['TrackerSources']), 'OwnTracks, Traccar and generic JSON trackers have separate configuration areas within one group.');
$trackerLists = array_column($panels['TrackerSources']['items'], null, 'name');
check(!isset($trackerLists['TractiveTrackers']), 'The Tractive device selection is outside the generic tracker section.');
$columns = array_column($trackerLists['Trackers']['columns'], null, 'name');
check($columns['TrackerPosition']['edit']['validVariableTypes'] === [VARIABLETYPE_STRING] && $columns['TrackerMarkerColor']['edit']['type'] === 'SelectColor', 'Tracker entries have a JSON variable input and an individual marker color.');
check($columns['TrackerFormat']['add'] === 'json' && !$columns['TrackerFormat']['visible'] && $columns['TrackerFormat']['save'], 'New generic trackers always use OpenWoIst JSON.');

$mainTracker = IPS_CreateVariable(VARIABLETYPE_STRING);
$otherTracker = IPS_CreateVariable(VARIABLETYPE_STRING);
IPS_SetName($mainTracker, 'Phone');
IPS_SetName($otherTracker, 'Phone');
$firstFix = ['latitude' => 51.25, 'longitude' => -1.5, 'timestamp' => $now, 'accuracy' => 15.0];
SetValueString($mainTracker, json_encode(['_type' => 'location', 'lat' => 51.25, 'lon' => -1.5, 'tst' => $now, 'acc' => 15], JSON_THROW_ON_ERROR));
SetValueString($otherTracker, json_encode(['lat' => 52.25, 'lon' => 0.5, 'tst' => $now, 'alt' => 4], JSON_THROW_ON_ERROR));
$trackers = [
    ['TrackerName' => 'Phone', 'TrackerPosition' => $mainTracker, 'TrackerMarkerColor' => 0],
    ['TrackerName' => '', 'TrackerPosition' => $otherTracker, 'TrackerMarkerColor' => -1]
];
$assigned = json_encode([...$trackers, $trackers[0]], JSON_THROW_ON_ERROR);
IPS_SetProperty($trackerMapID, 'Trackers', $assigned);
IPS_ApplyChanges($trackerMapID);
$sources = tileState($trackerMap)['sources'];
check(array_slice($sources, 0, 2) === $original, 'Adding trackers preserves the Apple and vehicle entries.');
$devices = array_values(array_filter($sources, static fn (array $item): bool => $item['kind'] === 'tracker'));
check(count($devices) === 2 && $devices[0]['id'] !== $devices[1]['id'] && array_column($devices, 'name') === ['Phone', 'Phone'], 'Distinct IDs may share a name, and identical assignments are deduplicated.');
check($devices[0]['objects'][0]['location'] === $firstFix && $devices[0]['objects'][0]['markerColor'] === 0, 'The OwnTracks helper preserves the fix, accuracy, and black marker color.');
check($devices[1]['objects'][0]['location']['latitude'] === 52.25 && !isset($devices[1]['objects'][0]['markerColor']), 'The OwnTracks module format uses the designer default without an individual color.');
check($trackerMap->GetReferenceList() === [$accountID, $mainTracker, $otherTracker], 'The tracker variables are referenced individually.');
check(mapRequest($trackerMap, $path, token: $originalAccess['token'])[0] === 403, 'Adding trackers rotates the IPSView access token.');

$embed = $trackerMap->GetIPSViewHTML();
$access = bootstrap($embed)['options'];
SetValueString($mainTracker, json_encode(['_type' => 'transition', 'lat' => 51.5, 'lon' => -1.25, 'tst' => $now + 1], JSON_THROW_ON_ERROR));
$trackerMap->MessageSink(1, $mainTracker, VM_UPDATE, []);
$objects = tileState($trackerMap)['sources'][2]['objects'];
check(!$objects[0]['current'] && $objects[0]['location'] === $firstFix, 'An unsupported OwnTracks message retains the last known fix.');
IPS_SetName($otherTracker, 'Renamed phone');
SetValueString($mainTracker, json_encode(['_type' => 'location', 'lat' => 51.5, 'lon' => -1.25, 'tst' => $now + 2], JSON_THROW_ON_ERROR));
$trackerMap->MessageSink(1, $mainTracker, VM_UPDATE, []);
$moved = tileState($trackerMap)['sources'];
check($moved[2]['objects'][0]['location']['timestamp'] === $now + 2 && $moved[2]['objects'][0]['location']['latitude'] === 51.5, 'A new measurement moves the corresponding tracker.');
check($moved[3]['id'] === $devices[1]['id'] && $moved[3]['name'] === 'Renamed phone' && $moved[3]['objects'][0]['location'] === $devices[1]['objects'][0]['location'], 'Renaming a tracker variable preserves its identity and fix.');
[$status, $response] = mapRequest($trackerMap, $path, token: $access['token']);
check($status === 200 && json_decode($response, true, 32, JSON_THROW_ON_ERROR)['sources'] === $moved, 'IPSView receives the same tracker positions as the tile.');
check(IPS_GetProperty($trackerMapID, 'Trackers') === $assigned && IPS_GetObjectIDByIdent('IPSViewOpenWoIst', $trackerMapID) === $webContentID && $trackerMap->GetIPSViewHTML() === $embed, 'Live updates preserve assignments and the IPSView embedding.');

IPS_SetProperty($trackerMapID, 'Trackers', json_encode([$trackers[1]], JSON_THROW_ON_ERROR));
IPS_ApplyChanges($trackerMapID);
$messages = new ReflectionMethod($trackerMap, 'GetMessageList');
check($trackerMap->GetReferenceList() === [$accountID, $otherTracker] && !isset($messages->invoke($trackerMap)[$mainTracker]), 'Removing a tracker removes its reference and subscription.');
check(mapRequest($trackerMap, $path, token: $access['token'])[0] === 403, 'Removing a tracker revokes access to the old scope.');

$beforeGeneric = tileState($trackerMap)['sources'];
$ownTracksEmbed = $trackerMap->GetIPSViewHTML();
$ownTracksRow = $trackers[1] + ['TrackerFormat' => 'owntracks'];
IPS_SetProperty($trackerMapID, 'Trackers', json_encode([$ownTracksRow], JSON_THROW_ON_ERROR));
IPS_ApplyChanges($trackerMapID);
check($trackerMap->GetIPSViewHTML() === $ownTracksEmbed && tileState($trackerMap)['sources'] === $beforeGeneric, 'Making the default format explicit preserves existing OwnTracks assignments and access.');

$exampleRows = [$ownTracksRow];
$exampleIDs = [];
$payloads = [
    'Home Assistant' => ['latitude' => 52.51453, 'longitude' => 13.35010, 'timestamp' => $now],
    'MQTT GPS'       => ['latitude' => 52.51461, 'longitude' => 13.35684, 'timestamp' => $now, 'horizontalAccuracy' => 12]
];
foreach ($payloads as $name => $payload) {
    $input = IPS_CreateVariable(VARIABLETYPE_STRING);
    SetValueString($input, json_encode($payload, JSON_THROW_ON_ERROR));
    $exampleIDs[] = $input;
    $exampleRows[] = ['TrackerName' => $name, 'TrackerPosition' => $input, 'TrackerFormat' => 'json', 'TrackerMarkerColor' => 0x2256A6];
}
IPS_SetProperty($trackerMapID, 'Trackers', json_encode($exampleRows, JSON_THROW_ON_ERROR));
IPS_ApplyChanges($trackerMapID);
$snapshot = tileState($trackerMap)['sources'];
check(array_slice($snapshot, 0, 3) === $beforeGeneric && $trackerMap->GetReferenceList() === [$accountID, $otherTracker, ...$exampleIDs], 'Adding generic JSON trackers preserves the existing sources.');
$expected = $beforeGeneric;
foreach (array_values($payloads) as $index => $payload) {
    $id = $exampleIDs[$index];
    $sourceIndex = $index + 3;
    $source = $snapshot[$sourceIndex];
    $fix = $source['objects'][0]['location'];
    $accuracy = $index === 1 ? 12.0 : null;
    check($source['kind'] === 'tracker' && $fix === ['latitude' => $payload['latitude'], 'longitude' => $payload['longitude'], 'timestamp' => $now, 'accuracy' => $accuracy], 'Generic JSON uses the original position and measurement time.');
    $expected[] = $source;
    $second = ['latitude' => 52.51469 + $index / 1000, 'longitude' => $payload['longitude'], 'timestamp' => $now + 1];
    SetValueString($id, json_encode($second, JSON_THROW_ON_ERROR));
    $trackerMap->MessageSink(1, $id, VM_UPDATE, []);
    $expected[$sourceIndex]['objects'][0]['location'] = $second + ['accuracy' => null];
    $all = tileState($trackerMap)['sources'];
    check(array_slice($all, 0, count($expected)) === $expected, 'Only the generic JSON source with a new fix moves.');
    unset($second['timestamp']);
    SetValueString($id, json_encode($second, JSON_THROW_ON_ERROR));
    $trackerMap->MessageSink(1, $id, VM_UPDATE, []);
    $expected[$sourceIndex]['objects'][0]['current'] = false;
    check(array_slice(tileState($trackerMap)['sources'], 0, count($expected)) === $expected, 'A missing measurement time retains the previous generic JSON fix.');
}
$genericAccess = bootstrap($trackerMap->GetIPSViewHTML())['options'];
[$status, $response] = mapRequest($trackerMap, $path, token: $genericAccess['token']);
check($status === 200 && json_decode($response, true, 32, JSON_THROW_ON_ERROR)['sources'] === $expected, 'IPSView shows the same Home Assistant and MQTT tracker data as the tile.');

$exampleRows[1]['TrackerFormat'] = 'owntracks';
IPS_SetProperty($trackerMapID, 'Trackers', json_encode($exampleRows, JSON_THROW_ON_ERROR));
IPS_ApplyChanges($trackerMapID);
check(mapRequest($trackerMap, $path, token: $genericAccess['token'])[0] === 403, 'Changing the format of an assigned tracker rotates the token.');
