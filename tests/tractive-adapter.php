<?php

declare(strict_types=1);

final class TestTractiveIO extends IPSModule
{
    public string $response = '';
    public array $requests = [];
    public bool $fail = false;

    public function ApplyChanges(): void
    {
        parent::ApplyChanges();
        $this->SetStatus(IS_ACTIVE);
    }

    public function ForwardData($data)
    {
        $this->requests[] = json_decode($data, true, 8, JSON_THROW_ON_ERROR);
        if ($this->fail) {
            throw new RuntimeException('Synthetic transport failure');
        }
        return $this->response;
    }
}

final class TestTractiveTracker extends IPSModule
{
    public function Create(): void
    {
        parent::Create();
        $this->RegisterPropertyString('tracker_id', 'fixture-tracker');
        $this->RegisterPropertyString('pet_id', 'fixture-pet');
    }
}

$tractiveLibrary = '{85B63C47-3DA6-7B2F-6472-537D9CA24360}';
$tractiveIO = '{0661D1B3-4375-1B37-7D59-1592111C8F8D}';
$tractiveDevice = '{A259E80D-C7B4-F5A9-F82B-B9B05F71B4F3}';
$adapterModule = '{51FAD7B1-C71B-4A10-BF6A-72FA62649771}';
$requestID = '{94B20D14-415B-1E19-8EA4-839F948B6CBE}';
$replyID = '{91C54CDA-594C-1D6F-6BD8-57545408677F}';
$modules = $metadata->getValue();
$modules[$tractiveIO] = array_replace($modules[$adapterModule], [
    'Class'             => TestTractiveIO::class,
    'ModuleID'          => $tractiveIO,
    'ModuleType'        => 1,
    'LibraryID'         => $tractiveLibrary,
    'Implemented'       => [$requestID],
    'ParentRequirements'=> [],
    'ChildRequirements' => [$replyID]
]);
$modules[$tractiveDevice] = array_replace($modules[$adapterModule], [
    'Class'     => TestTractiveTracker::class,
    'ModuleID'  => $tractiveDevice,
    'LibraryID' => $tractiveLibrary
]);
$metadata->setValue(null, $modules);
$libraryMetadata = new ReflectionProperty(IPS\ModuleLoader::class, 'libraries');
$libraries = $libraryMetadata->getValue();
$libraries[$tractiveLibrary] = ['Version' => '1.14'];
$libraryMetadata->setValue(null, $libraries);

$ioID = IPS_CreateInstance($tractiveIO);
$wrongIO = IPS_CreateInstance($tractiveIO);
$io = IPS\InstanceManager::getInstanceInterface($ioID);
$trackerID = IPS_CreateInstance($tractiveDevice);
IPS_ConnectInstance($trackerID, $ioID);
IPS_ApplyChanges($trackerID);
$adapterID = IPS_CreateInstance($adapterModule);
$adapter = IPS\InstanceManager::getInstanceInterface($adapterID);
$positionID = IPS_GetObjectIDByIdent('Position', $adapterID);
$pollInterval = new ReflectionMethod($adapter, 'GetTimerInterval');
OWIT_Update($adapterID);
check(IPS_GetInstance($adapterID)['InstanceStatus'] === 201 && GetValueString($positionID) === '', 'The unconfigured adapter publishes nothing.');

IPS_SetProperty($adapterID, 'TrackerInstance', $trackerID);
IPS_ConnectInstance($adapterID, $wrongIO);
IPS_ApplyChanges($adapterID);
OWIT_Update($adapterID);
check(IPS\InstanceManager::getInstanceInterface($wrongIO)->requests === [], 'The adapter must not request the tracker from a different account.');
IPS_DisconnectInstance($adapterID);
IPS_ConnectInstance($adapterID, $ioID);
IPS_ApplyChanges($adapterID);
check($adapter->GetReferenceList() === [$trackerID] && $io->requests === [] && $pollInterval->invoke($adapter, 'ReadPosition') === 0, 'Setup only references the selected tracker and never polls automatically by default.');

$tractiveMapID = IPS_CreateInstance($mapModule);
$tractiveMap = IPS\InstanceManager::getInstanceInterface($tractiveMapID);
$accountAssignment = json_encode([['AccountID' => $accountID]], JSON_THROW_ON_ERROR);
$tractiveRow = json_encode([['TrackerName' => 'Fixture pet', 'TrackerPosition' => $positionID, 'TrackerFormat' => 'json', 'TrackerMarkerColor' => 0x2256A6]], JSON_THROW_ON_ERROR);
IPS_SetProperty($tractiveMapID, 'Accounts', $accountAssignment);
IPS_SetProperty($tractiveMapID, 'VehicleMode', 'demo');
IPS_SetProperty($tractiveMapID, 'EnableIPSView', true);
IPS_ApplyChanges($tractiveMapID);
$beforeTractive = tileState($tractiveMap)['sources'];
IPS_SetProperty($tractiveMapID, 'Trackers', $tractiveRow);
IPS_ApplyChanges($tractiveMapID);
$tractiveEmbed = $tractiveMap->GetIPSViewHTML();
$tractiveAccess = bootstrap($tractiveEmbed)['options'];
$tractivePath = dirname($tractiveAccess['page']) . '/state';

$olderReport = ['_type' => 'device_pos_report', '_id' => 'fixture-tracker', 'latlong' => [51.25, -1.5], 'time' => $now - 120];
$foreignReport = array_replace($olderReport, ['_id' => 'another-tracker', 'time' => $now - 10]);
$missingTime = ['_type' => 'device_pos_report', '_id' => 'fixture-tracker', 'latlong' => [0, 0]];
$reports = [
    '1.14' => ['GetUpdateData', array_replace($olderReport, ['latlong' => [51.5, -1.25], 'time' => $now - 90])],
    '1.19' => ['GetDeviceData', array_replace($olderReport, ['latlong' => [51.75, -1.0], 'time' => $now - 60])]
];
foreach ($reports as $version => [$command, $report]) {
    $libraries[$tractiveLibrary]['Version'] = $version;
    $libraryMetadata->setValue(null, $libraries);
    $io->response = json_encode([$foreignReport, $report, $missingTime, $olderReport], JSON_THROW_ON_ERROR);
    OWIT_Update($adapterID);
    check(end($io->requests) === [
        'DataID'     => $requestID,
        'CallerID'   => $adapterID,
        'Function'   => $command,
        'tracker_id' => 'fixture-tracker',
        'pet_id'     => 'fixture-pet'
    ], 'The adapter sends the read-only command for the installed Tractive release.');
    $fix = ['latitude' => $report['latlong'][0], 'longitude' => $report['latlong'][1], 'timestamp' => $report['time']];
    check(json_decode(GetValueString($positionID), true, 8, JSON_THROW_ON_ERROR) === $fix, 'The newer valid report for the selected tracker is written with its original measurement time.');
    $tractiveMap->MessageSink(1, $positionID, VM_UPDATE, []);
    $sources = tileState($tractiveMap)['sources'];
    $marker = $sources[2]['objects'][0];
    check(array_slice($sources, 0, 2) === $beforeTractive && $marker['location'] === $fix + ['accuracy' => null] && $marker['markerColor'] === 0x2256A6, 'The tile receives the Tractive fix alongside the existing Apple and vehicle sources.');
    [$status, $response] = mapRequest($tractiveMap, $tractivePath, token: $tractiveAccess['token']);
    check($status === 200 && json_decode($response, true, 32, JSON_THROW_ON_ERROR)['sources'] === $sources, 'IPSView receives the same Tractive position as the tile.');
}
check(count($io->requests) === 2 && $tractiveMap->GetIPSViewHTML() === $tractiveEmbed && IPS_GetProperty($tractiveMapID, 'Trackers') === $tractiveRow, 'Position changes preserve assignments and the embedding.');

$lastPosition = GetValueString($positionID);
foreach ([
    [$foreignReport, $missingTime, array_replace($olderReport, ['latlong' => [91, -1.25]])],
    [$olderReport],
    [$foreignReport]
] as $invalidReports) {
    $io->response = json_encode($invalidReports, JSON_THROW_ON_ERROR);
    OWIT_Update($adapterID);
    check(GetValueString($positionID) === $lastPosition, 'Missing, invalid or older measurements preserve the latest fix.');
}
$io->fail = true;
OWIT_Update($adapterID);
check(GetValueString($positionID) === $lastPosition && IPS_GetInstance($adapterID)['InstanceStatus'] === 203, 'A transport failure preserves the last good fix and reports its status.');
$requestCount = count($io->requests);
$libraries[$tractiveLibrary]['Version'] = '2.0';
$libraryMetadata->setValue(null, $libraries);
OWIT_Update($adapterID);
check(count($io->requests) === $requestCount && IPS_GetInstance($adapterID)['InstanceStatus'] === 202, 'Unknown versions are not queried.');

IPS_SetProperty($adapterID, 'PollInterval', 10);
IPS_ApplyChanges($adapterID);
check($pollInterval->invoke($adapter, 'ReadPosition') === 600000 && count($io->requests) === $requestCount, 'Opting into a timer schedules it without sending a request during ApplyChanges.');
IPS_SetProperty($adapterID, 'TrackerInstance', 0);
IPS_ApplyChanges($adapterID);
check($adapter->GetReferenceList() === [] && GetValueString($positionID) === '' && $pollInterval->invoke($adapter, 'ReadPosition') === 0, 'Removing the tracker clears the adapter value, reference and timer.');
echo "OpenWoIst Tractive adapter passed for 1.14 and 1.19 Beta.\n";
