<?php

declare(strict_types=1);

$garminForm = array_column(json_decode($map->GetConfigurationForm(), true, 64, JSON_THROW_ON_ERROR)['elements'], null, 'name');
$garminPanel = array_column($garminForm['LocationDeviceSources']['items'], null, 'name')['GarminLiveTrackSources'];
$garminList = array_column($garminPanel['items'], null, 'name')['GarminLiveTrackSessions'];
check($garminList['add'] && $garminList['delete'] && !isset($garminForm['GarminLiveTrackSources']), 'Garmin has its own list under location devices.');
$garminDialog = static function (array $GarminLiveTrackSessions) use ($garminList): array
{
    return array_column(eval($garminList['form']), null, 'name');
};
$firstDialog = $garminDialog([]);
$entryID = $firstDialog['GarminEntryID']['value'];
$otherID = $garminDialog([])['GarminEntryID']['value'];
check($entryID !== $otherID && $garminDialog(['GarminEntryID' => $entryID])['GarminEntryID']['value'] === $entryID, 'New rows have independent IDs and editing preserves the row ID.');
check($firstDialog['GarminShareLink']['type'] === 'PasswordTextBox' && !array_column($garminList['columns'], null, 'name')['GarminShareLink']['visible'], 'The share link is masked and excluded from the list overview.');

final class GarminSimulationOpenWoIst extends OpenWoIst
{
    public int $garminClock = 0;
    public array $garminResponses = [];
    public array $garminRequests = [];

    protected function garminLiveTrackTime(): int
    {
        return $this->garminClock;
    }

    protected function garminLiveTrackTransport(): Closure
    {
        return function (string $url, array $headers): array
        {
            $this->garminRequests[] = [parse_url($url, PHP_URL_PATH), parse_url($url, PHP_URL_QUERY), $headers];
            return array_shift($this->garminResponses) ?? throw new RuntimeException('Unexpected Garmin request.');
        };
    }
}

$modules[$mapModule]['Class'] = GarminSimulationOpenWoIst::class;
$metadata->setValue(null, $modules);
$garminMapID = IPS_CreateInstance($mapModule);
$modules[$mapModule]['Class'] = OpenWoIst::class;
$metadata->setValue(null, $modules);
$garminMap = IPS\InstanceManager::getInstanceInterface($garminMapID);
$garminMap->garminClock = time();
$pointTime = $garminMap->garminClock - 90;
IPS_SetProperty($garminMapID, 'Accounts', json_encode([['AccountID' => $accountID]], JSON_THROW_ON_ERROR));
IPS_SetProperty($garminMapID, 'VehicleMode', 'demo');
IPS_SetProperty($garminMapID, 'EnableIPSView', true);
$setGarminRows = static function (array $rows) use ($garminMapID): void
{
    IPS_SetProperty($garminMapID, 'GarminLiveTrackSessions', json_encode($rows, JSON_THROW_ON_ERROR));
    IPS_ApplyChanges($garminMapID);
};
$garminRows = [
    ['GarminEntryID' => $entryID, 'GarminName' => 'Garmin test ride', 'GarminMarkerColor' => 0x558ACC, 'GarminShareLink' => 'https://livetrack.garmin.com/session/TestRide/token/synthetic-A'],
    ['GarminEntryID' => $otherID, 'GarminName' => 'Garmin test run', 'GarminMarkerColor' => 0, 'GarminShareLink' => 'https://livetrack.garmin.com/session/TestRun?token=synthetic-B']
];
$setGarminRows($garminRows);
$garminEmbed = $garminMap->GetIPSViewHTML();
$garminAccess = bootstrap($garminEmbed)['options'];
$garminPath = dirname($garminAccess['page']) . '/state';
$verifyGarminMaps = static function () use ($garminMap, $garminPath, $garminAccess): array
{
    $tile = tileState($garminMap)['sources'];
    [$status, $json] = mapRequest($garminMap, $garminPath, token: $garminAccess['token']);
    check($status === 200 && $tile === json_decode($json, true, 32, JSON_THROW_ON_ERROR)['sources'], 'Garmin and all existing sources are equal in both maps.');
    check(!str_contains($json, 'synthetic-') && !str_contains($garminMap->GetVisualizationTile(), 'livetrack.garmin.com'), 'Neither map exposes Garmin share URLs or tokens.');
    check(array_column($tile, 'kind') === ['icloud', 'demo', 'tracker', 'tracker'] && $tile[0]['objects'] !== [] && $tile[1]['objects'] !== [], 'Garmin appears alongside the iCloud cache and demo vehicle.');
    return array_column(array_slice($tile, 2), null, 'name');
};
$garminJSON = static fn (array $data): array => ['status' => 200, 'body' => json_encode($data, JSON_THROW_ON_ERROR)];
$garminPoint = static fn (float $latitude, int $time, string $fraction = '100'): array => [
    'position' => ['lat' => $latitude, 'lon' => 8.25], 'dateTime' => gmdate('Y-m-d\TH:i:s', $time) . '.' . $fraction . 'Z'
];
$simulateGarmin = static function (string $session, array $points, ?int $end = null) use ($garminMap, $garminMapID, $garminJSON, $pointTime): void
{
    $garminMap->garminResponses = [
        ['status' => 200, 'body' => '<meta content="synthetic-csrf" name="livetrack-csrf-token">'],
        $garminJSON(['data' => ['sessionId' => $session, 'start' => gmdate('c', $pointTime), 'end' => $end === null ? null : gmdate('c', $end)]]),
        $garminJSON(['trackPoints' => $points])
    ];
    OWIS_PollGarminLiveTrack($garminMapID);
    check($garminMap->garminResponses === [], 'The Garmin timer consumes the simulated page, metadata and trackpoint responses.');
};

check($verifyGarminMaps()['Garmin test ride']['objects'][0]['location'] === null && $garminMap->garminRequests === [], 'Applying the configuration does not make a synchronous Garmin request.');
$simulateGarmin('TestRide', [$garminPoint(50.1, $pointTime), $garminPoint(50.2, $pointTime, '200'), $garminPoint(51.0, $pointTime - 1), $garminPoint(91.0, $pointTime + 1)]);
$rideSource = $verifyGarminMaps()['Garmin test ride'];
$ride = $rideSource['objects'][0];
check($ride['current'] && $ride['location']['latitude'] === 50.2 && $ride['location']['timestamp'] === $pointTime && $ride['markerColor'] === 0x558ACC, 'The newest valid Garmin fix preserves its measurement time and marker color.');
check(array_column($garminMap->garminRequests, 0) === ['/session/TestRide/token/synthetic-A', '/api/v2/sessions/TestRide', '/api/sessions/TestRide/track-points/common']
    && in_array('Livetrack-Csrf-Token: synthetic-csrf', $garminMap->garminRequests[2][2], true), 'The direct Garmin flow passes the session context to both JSON requests.');
$simulateGarmin('TestRun', [$garminPoint(48.2, $pointTime)]);
check($verifyGarminMaps()['Garmin test run']['objects'][0]['markerColor'] === 0, 'A second configured session is independent and keeps black as its selected color.');
$garminMap->garminClock += 31;
$simulateGarmin('TestRide', [$garminPoint(50.3, $pointTime + 30), ['dateTime' => gmdate('c', $pointTime + 31), 'eventTypes' => ['END']]]);
parse_str(end($garminMap->garminRequests)[1], $garminQuery);
$ended = $verifyGarminMaps()['Garmin test ride']['objects'][0];
check($garminQuery['begin'] === gmdate('Y-m-d\TH:i:s', $pointTime) . '.200000Z' && !$ended['current']
    && $ended['location']['latitude'] === 50.3 && $ended['location']['timestamp'] === $pointTime + 30, 'Incremental polling retains and marks the final valid position when the session ends.');

$garminMap->garminResponses = [['status' => 410, 'body' => '']];
OWIS_PollGarminLiveTrack($garminMapID);
check(!$verifyGarminMaps()['Garmin test run']['objects'][0]['current'], 'An expired share keeps its most recent position as last known.');
$garminRequestCount = count($garminMap->garminRequests);
$garminMap->garminClock += 180;
OWIS_PollGarminLiveTrack($garminMapID);
check(count($garminMap->garminRequests) === $garminRequestCount, 'Expired and ended sessions are no longer polled.');

$garminRows[0]['GarminShareLink'] = 'https://livetrack.garmin.com/session/NextRide/token/synthetic-C';
$garminRows[1]['GarminShareLink'] = 'https://livetrack.garmin.com@invalid.example/session/TestRun/token/synthetic-D';
$setGarminRows($garminRows);
check($verifyGarminMaps()['Garmin test ride']['objects'][0] === $ended && $garminMap->GetIPSViewHTML() === $garminEmbed, 'Replacing the link preserves the retained point, marker identity and IPSView access.');
$simulateGarmin('NextRide', []);
check($verifyGarminMaps()['Garmin test ride']['objects'][0] === $ended, 'A new session without a fix keeps the old point marked as last known.');
$garminMap->garminClock += 31;
$simulateGarmin('NextRide', [$garminPoint(50.4, $pointTime + 60)]);
$nextRideSource = $verifyGarminMaps()['Garmin test ride'];
$nextRide = $nextRideSource['objects'][0];
check($nextRide['location']['latitude'] === 50.4 && $nextRide['current'] && $nextRide['id'] === $ride['id'] && $nextRide['markerColor'] === $ride['markerColor'], 'A simulated position change in the replacement session moves the same marker.');
if (in_array('--garmin-motion-fixtures', $argv, true)) {
    echo json_encode(['initial' => $rideSource, 'updated' => $nextRideSource], JSON_THROW_ON_ERROR);
    exit(0);
}

$garminMap->garminClock += 31;
$garminMap->garminResponses = [['status' => 429, 'body' => '']];
OWIS_PollGarminLiveTrack($garminMapID);
$throttled = $verifyGarminMaps()['Garmin test ride']['objects'][0];
check(!$throttled['current'] && $throttled['location'] === $nextRide['location'], 'Throttling retains the last fix and marks it as last known.');
$garminRequestCount = count($garminMap->garminRequests);
$garminMap->garminClock += 119;
OWIS_PollGarminLiveTrack($garminMapID);
check(count($garminMap->garminRequests) === $garminRequestCount, 'Backoff prevents network requests during throttling and rejects the foreign share URL.');
$garminMap->garminClock++;
$simulateGarmin('NextRide', []);
check($verifyGarminMaps()['Garmin test ride']['objects'][0] === $nextRide, 'The active session recovers after a transient transport failure.');
$garminMap->garminClock += 31;
$simulateGarmin('NextRide', [], $pointTime + 61);
check($verifyGarminMaps()['Garmin test ride']['objects'][0] === $throttled, 'An elapsed Garmin end timestamp also keeps the point as last known.');
$setGarminRows([]);
check(mapRequest($garminMap, $garminPath, token: $garminAccess['token'])[0] === 403 && array_column(tileState($garminMap)['sources'], 'kind') === ['icloud', 'demo'], 'Removing Garmin entries clears their markers and revokes the old IPSView access.');
