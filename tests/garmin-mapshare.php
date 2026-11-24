<?php

declare(strict_types=1);

$mapShareForm = array_column(json_decode($map->GetConfigurationForm(), true, 64, JSON_THROW_ON_ERROR)['elements'], null, 'name');
$mapSharePanel = array_column($mapShareForm['LocationDeviceSources']['items'], null, 'name')['GarminMapShareSources'];
$mapShareList = array_column($mapSharePanel['items'], null, 'name')['GarminMapShareDevices'];
$mapShareInputs = array_column($mapShareList['form'], null, 'name');
check($mapShareList['add'] && $mapShareList['delete'] && $mapShareInputs['MapShareIMEI']['validate'] === '^[0-9]{15}$', 'MapShare provides its own addable device list and validates the IMEI.');
check(!array_filter($mapShareList['columns'], static fn (array $column): bool => !array_key_exists('add', $column)), 'MapShare list columns provide defaults so the console can add an entry.');

$modules[$mapModule]['Class'] = GarminSimulationOpenWoIst::class;
$metadata->setValue(null, $modules);
$mapShareID = IPS_CreateInstance($mapModule);
$modules[$mapModule]['Class'] = OpenWoIst::class;
$metadata->setValue(null, $modules);
$mapShare = IPS\InstanceManager::getInstanceInterface($mapShareID);
$mapShare->garminClock = time();
$mapShareTime = $mapShare->garminClock - 120;
IPS_SetProperty($mapShareID, 'Accounts', json_encode([['AccountID' => $accountID]], JSON_THROW_ON_ERROR));
IPS_SetProperty($mapShareID, 'VehicleMode', 'demo');
IPS_SetProperty($mapShareID, 'EnableIPSView', true);
$imei = '300000000000001';
$secondIMEI = '300000000000002';
foreach (['https://share.garmin.com/SyntheticShare', 'https://inreach.garmin.com/feed/share/SyntheticShare', 'https://explore.garmin.com/feed/share/SyntheticShare'] as $link) {
    check(OpenWoIst\GarminMapShareClient::feed($link, $imei)['url'] === 'https://explore.garmin.com/feed/share/SyntheticShare?imei=' . $imei, 'MapShare aliases normalize to the non-redirecting Garmin Explore feed.');
}
$rows = [
    ['MapShareName' => 'MapShare test device', 'MapShareLink' => 'https://share.garmin.com/SyntheticShare', 'MapShareIMEI' => $imei, 'MapShareUsername' => 'test-user', 'MapSharePassword' => 'test-secret', 'MapShareMarkerColor' => 0x558ACC],
    ['MapShareName' => 'MapShare second device', 'MapShareLink' => 'https://share.garmin.com/Feed/Share/SyntheticShare', 'MapShareIMEI' => $secondIMEI, 'MapShareMarkerColor' => 0],
    ['MapShareName' => 'Invalid host', 'MapShareLink' => 'https://inreach.garmin.com@invalid.example/feed/share/SyntheticShare', 'MapShareIMEI' => $imei]
];
IPS_SetProperty($mapShareID, 'GarminMapShareDevices', json_encode($rows, JSON_THROW_ON_ERROR));
IPS_ApplyChanges($mapShareID);
$mapShareEmbed = $mapShare->GetIPSViewHTML();
$mapShareAccess = bootstrap($mapShareEmbed)['options'];
$mapSharePath = dirname($mapShareAccess['page']) . '/state';
$mapShareMaps = static function () use ($mapShare, $mapSharePath, $mapShareAccess): array
{
    $tileHTML = $mapShare->GetVisualizationTile();
    $tile = bootstrap($tileHTML)['state']['sources'];
    [$status, $body] = mapRequest($mapShare, $mapSharePath, token: $mapShareAccess['token']);
    check($status === 200 && json_decode($body, true, 32, JSON_THROW_ON_ERROR)['sources'] === $tile, 'Both map outputs receive the same MapShare and existing sources.');
    check(array_column($tile, 'kind') === ['icloud', 'demo', 'tracker', 'tracker'], 'Two devices coexist with iCloud and demo sources.');
    foreach (['SyntheticShare', 'test-user', 'test-secret', '300000000000001', '300000000000002'] as $secret) {
        check(!str_contains($tileHTML . $body, $secret), 'Visualization output contains no MapShare access or device identifiers.');
    }
    return array_column(array_slice($tile, 2), null, 'name');
};
$placemark = static function (string $device, float $lat, float $lon, int $time, bool $extended = false): string
{
    $deviceData = '<Data name="IMEI"><value>' . $device . '</value></Data>';
    if ($extended) {
        return '<Placemark><ExtendedData>' . $deviceData . '<Data name="Time UTC"><value>' . gmdate('n/j/Y g:i:s A', $time) . '</value></Data>'
            . '<Data name="Latitude"><value>' . $lat . '</value></Data><Data name="Longitude"><value>' . $lon . '</value></Data></ExtendedData></Placemark>';
    }
    return '<Placemark><TimeStamp><when>' . gmdate('c', $time) . '</when></TimeStamp><ExtendedData>' . $deviceData . '</ExtendedData>'
        . '<Point><coordinates>' . $lon . ',' . $lat . ',30</coordinates></Point></Placemark>';
};
$simulateMapShare = static function (string $placemarks, int $status = 200) use ($mapShare, $mapShareID): void
{
    $mapShare->garminResponses = [['status' => $status, 'body' => '<kml xmlns="http://www.opengis.net/kml/2.2"><Document>' . $placemarks . '</Document></kml>']];
    OWIS_PollGarminLiveTrack($mapShareID);
    check($mapShare->garminResponses === [], 'The shared Garmin timer makes one request for the due MapShare device.');
};

$simulateMapShare($placemark($imei, 52.51, 13.40, $mapShareTime) . $placemark($imei, 52.49, 13.41, $mapShareTime - 10)
    . $placemark($secondIMEI, 50.9, 13.72, $mapShareTime + 1) . $placemark($imei, 99.0, 13.0, $mapShareTime + 2));
$mapShareInitial = $mapShareMaps()['MapShare test device'];
$initialFix = $mapShareInitial['objects'][0];
check($initialFix['current'] && $initialFix['location'] === ['latitude' => 52.51, 'longitude' => 13.40, 'timestamp' => $mapShareTime, 'accuracy' => null], 'The newest valid point for the exact IMEI is used with KML longitude and latitude in the right order.');
check($mapShare->garminRequests[0][0] === '/feed/share/SyntheticShare' && $mapShare->garminRequests[0][1] === 'imei=' . $imei
    && in_array('Authorization: Basic ' . base64_encode('test-user:test-secret'), $mapShare->garminRequests[0][2], true), 'MapShare polls the canonical feed filtered by IMEI and sends optional Basic authentication.');
$simulateMapShare($placemark($secondIMEI, 52.42, 13.39, $mapShareTime + 1));
$secondFix = $mapShareMaps()['MapShare second device']['objects'][0];
check($secondFix['location']['latitude'] === 52.42 && $secondFix['markerColor'] === 0, 'The second row for the same feed has its own position and color.');
$mapShare->garminClock += 299;
OWIS_PollGarminLiveTrack($mapShareID);
check(count($mapShare->garminRequests) === 2, 'MapShare observes its five-minute poll interval.');

$mapShare->garminClock++;
$simulateMapShare($placemark($imei, 52.52, 13.42, $mapShareTime + 60, true));
$mapShareUpdated = $mapShareMaps()['MapShare test device'];
$updatedFix = $mapShareUpdated['objects'][0];
check($updatedFix['current'] && $updatedFix['location']['latitude'] === 52.52 && $updatedFix['location']['timestamp'] === $mapShareTime + 60
    && $updatedFix['id'] === $initialFix['id'] && $updatedFix['markerColor'] === 0x558ACC, 'MapShare uses documented UTC fields and moves the same colored marker.');
if (in_array('--mapshare-motion-fixtures', $argv, true)) {
    echo json_encode(['initial' => $mapShareInitial, 'updated' => $mapShareUpdated], JSON_THROW_ON_ERROR);
    exit(0);
}
$simulateMapShare('', 503);
$secondAfterError = $mapShareMaps()['MapShare second device']['objects'][0];
check(!$secondAfterError['current'] && $secondAfterError['location'] === $secondFix['location'], 'An HTTP error marks the second device last known and preserves its position.');
$mapShare->garminClock += 300;
$simulateMapShare($placemark($imei, 50.0, 13.0, $mapShareTime));
$older = $mapShareMaps()['MapShare test device']['objects'][0];
check(!$older['current'] && $older['location'] === $updatedFix['location'], 'A replayed older response cannot overwrite the current device position.');
$simulateMapShare($placemark($secondIMEI, 52.43, 13.39, $mapShareTime + 61));
check($mapShareMaps()['MapShare second device']['objects'][0]['current'], 'A device recovers automatically after a request error.');

$request = static fn (): array => ['status' => 200, 'body' => '<!DOCTYPE kml [<!ENTITY s "ignored">]><kml xmlns="http://www.opengis.net/kml/2.2"/>'];
$feed = ['url' => 'https://explore.garmin.com/feed/share/SyntheticShare?imei=' . $imei, 'imei' => $imei, 'username' => '', 'password' => ''];
check(OpenWoIst\GarminMapShareClient::poll($feed, null, $mapShareTime, $request) === ['status' => 0], 'MapShare rejects KML with a document type declaration.');
