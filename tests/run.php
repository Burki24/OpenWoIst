<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$stubs = getenv('SYMCON_STUBS_PATH') ?: __DIR__ . '/stubs';
require_once $stubs . '/autoload.php';

final class TestAccount extends IPSModuleStrict
{
    public array $cache = [];
    public bool $fail = false;
    public int $reads = 0;
}

function ICAC_GetDevices(int $id): string
{
    $account = IPS\InstanceManager::getInstanceInterface($id);
    $account->reads++;
    if ($account->fail) {
        throw new RuntimeException('private raw cache failure');
    }
    return json_encode($account->cache, JSON_THROW_ON_ERROR);
}

function check(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function tileState(OpenWoIst $map): array
{
    return bootstrap($map->GetVisualizationTile())['state'];
}

function bootstrap(string $html): array
{
    check(preg_match('/window\.SymconVisualization = (.+);<\/script>/', $html, $matches) === 1, 'Visualization must have a JSON bootstrap.');
    return json_decode($matches[1], true, 64, JSON_THROW_ON_ERROR);
}

function mapRequest(OpenWoIst $map, string $path, string $method = 'GET', string $token = '', string $etag = ''): array
{
    $_SERVER['REQUEST_METHOD'] = $method;
    $_SERVER['REQUEST_URI'] = $path;
    $_SERVER['HTTP_X_OPENWOIST_TOKEN'] = $token;
    $_SERVER['HTTP_IF_NONE_MATCH'] = $etag;
    ob_start();
    (new ReflectionMethod($map, 'ProcessHookData'))->invoke($map);
    return [http_response_code(), ob_get_clean()];
}

function source(OpenWoIst $map, string $kind): array
{
    $sources = array_column(tileState($map)['sources'], null, 'kind');
    check(isset($sources[$kind]), 'Expected source ' . $kind);
    return $sources[$kind];
}

IPS\Kernel::reset();
IPS\ModuleLoader::loadLibrary($root . '/library.json');

final class NonReentrantOpenWoIst extends OpenWoIst
{
    public function GetReferenceList(): array
    {
        // The stubs normally allow a kernel call back into the executing instance.
        $stack = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 4);
        if (($stack[2]['function'] ?? '') === 'IPS_GetReferenceList' && ($stack[3]['class'] ?? '') === OpenWoIst::class) {
            throw new RuntimeException('The kernel rejects a reentrant reference lookup.');
        }
        return parent::GetReferenceList();
    }
}

$accountModule = '{10BF9A03-98A3-495A-B0FA-A4FA0B1002D5}';
$mapModule = '{B9CA4CB3-31E4-4949-AA86-4580224C862D}';
$metadata = new ReflectionProperty(IPS\ModuleLoader::class, 'modules');
$modules = $metadata->getValue();
$modules[$accountModule] = array_replace($modules[$mapModule], ['Class' => TestAccount::class, 'ModuleID' => $accountModule, 'ModuleName' => 'Test Account']);
$modules[$mapModule]['Class'] = NonReentrantOpenWoIst::class;
$metadata->setValue(null, $modules);
$accountID = IPS_CreateInstance($accountModule);
$secondID = IPS_CreateInstance($accountModule);
$referenceProbe = IPS_CreateInstance($mapModule);
IPS_SetProperty($referenceProbe, 'Accounts', json_encode([['AccountID' => $accountID], ['AccountID' => $accountID]], JSON_THROW_ON_ERROR));
IPS_ApplyChanges($referenceProbe);
check(IPS_GetReferenceList($referenceProbe) === [$accountID], 'ApplyChanges must reference the assigned account exactly once.');
IPS_SetProperty($referenceProbe, 'Accounts', '[]');
IPS_ApplyChanges($referenceProbe);
check(IPS_GetReferenceList($referenceProbe) === [], 'ApplyChanges must remove references to unassigned accounts.');
IPS_DeleteInstance($referenceProbe);
$modules[$mapModule]['Class'] = OpenWoIst::class;
$metadata->setValue(null, $modules);

$account = IPS\InstanceManager::getInstanceInterface($accountID);
$second = IPS\InstanceManager::getInstanceInterface($secondID);
$mapID = IPS_CreateInstance($mapModule);
$map = IPS\InstanceManager::getInstanceInterface($mapID);
$now = time();
$phone = [
    'id'       => 'private-fixture-phone-identity',
    'name'     => 'Test iPhone </script>',
    'location' => ['latitude' => 52.50, 'longitude' => 13.39, 'timestamp' => $now, 'horizontalAccuracy' => 15],
    'token'    => 'private-fixture-token'
];
$account->cache = $second->cache = ['success' => true, 'state' => 'authenticated', 'updatedAt' => $now, 'devices' => [$phone]];

IPS_SetProperty($mapID, 'Accounts', json_encode([['AccountID' => $accountID], ['AccountID' => $accountID]], JSON_THROW_ON_ERROR));
IPS_SetProperty($mapID, 'VehicleMode', 'demo');
IPS_ApplyChanges($mapID);

if (in_array('--tile-fixture', $argv, true) || in_array('--visualization-fixtures', $argv, true)) {
    for ($i = 0; $i < 24; $i++) {
        $device = $phone;
        $device['id'] = 'extra-' . $i;
        $device['name'] = sprintf('Test Device %02d', $i);
        $device['location']['latitude'] += $i * 0.0006;
        if ($i === 0 && in_array('--marker-colors', $argv, true)) {
            $device['location']['isOld'] = true;
        }
        $account->cache['devices'][] = $device;
    }
    if (in_array('--marker-colors', $argv, true)) {
        IPS_SetProperty($mapID, 'SelectedDevices', json_encode([
            ['DeviceKey' => OpenWoIst\LocationSnapshot::key('icloud', $accountID, 'extra-0'), 'MarkerColor' => 0xCF2050],
            ['DeviceKey' => OpenWoIst\LocationSnapshot::key('icloud', $accountID, 'extra-1'), 'MarkerColor' => 0]
        ], JSON_THROW_ON_ERROR));
        IPS_ApplyChanges($mapID);
    }
    if (in_array('--tracker-fixture', $argv, true)) {
        $trackerPosition = IPS_CreateVariable(VARIABLETYPE_STRING);
        SetValueString($trackerPosition, json_encode(['lat' => 52.508, 'lon' => 13.38, 'tst' => $now], JSON_THROW_ON_ERROR));
        IPS_SetProperty($mapID, 'Trackers', json_encode([
            ['TrackerName' => 'OwnTracks test device', 'TrackerPosition' => $trackerPosition, 'TrackerMarkerColor' => 0x2256A6]
        ], JSON_THROW_ON_ERROR));
        IPS_ApplyChanges($mapID);
    }
    OWIS_Update($mapID);
    if (in_array('--tile-fixture', $argv, true)) {
        echo $map->GetVisualizationTile();
        exit(0);
    }
    IPS_SetProperty($mapID, 'EnableIPSView', true);
    IPS_SetProperty($mapID, 'IPSViewVehicleColor', 0x8342E5);
    IPS_ApplyChanges($mapID);
    $frame = $map->GetIPSViewHTML();
    $options = bootstrap($frame)['options'];
    $page = mapRequest($map, $options['page'])[1];
    $state = mapRequest($map, dirname($options['page']) . '/state', token: $options['token'])[1];
    $designers = array_column(json_decode($map->GetConfigurationForm(), true, 64, JSON_THROW_ON_ERROR)['elements'], null, 'caption');
    $guides = [];
    foreach (['Tile designer', 'IPSView designer'] as $name) {
        $designer = array_column($designers[$name]['items'], null, 'caption');
        $guides[] = $designer['OpenWoIst color guide']['items'][1]['image'];
    }
    echo json_encode([
        'tile' => $map->GetVisualizationTile(), 'frame' => $frame, 'page' => $page, 'state' => $state, 'options' => $options, 'guides' => $guides
    ], JSON_THROW_ON_ERROR);
    exit(0);
}

$demo = source($map, 'demo')['objects'][0];
$demoID = IPS_GetObjectIDByIdent('DemoPosition', $mapID);
$data = json_decode(GetValueString($demoID), true, 8, JSON_THROW_ON_ERROR);
check($demo['location']['latitude'] === $data['latitude'] && $demo['location']['timestamp'] === $data['timestamp'], 'Demo must render the value of the real Symcon variable.');
check(count(tileState($map)['sources']) === 2 && $second->reads === 0, 'Read only assigned caches, once per distinct source.');
$tile = $map->GetVisualizationTile();
check(!str_contains($tile, $phone['name']) && !str_contains($tile, $phone['id']) && !str_contains($tile, $phone['token']), 'HTML must escape display data and omit private provider identifiers and metadata.');
$form = json_decode($map->GetConfigurationForm(), true, 64, JSON_THROW_ON_ERROR);
check($form['actions'][0]['visible'], 'Demo control must be visible in demo mode.');
check(OWIS_NextDemoPosition($mapID), 'The public demo action must change the demo variable.');
check(source($map, 'demo')['objects'][0]['location']['longitude'] !== $demo['location']['longitude'], 'Demo action must change the plotted fix.');
$vehicleKey = $demo['id'];
IPS_ApplyChanges($mapID);
check(source($map, 'demo')['objects'][0]['id'] === $vehicleKey, 'Reapplying the same configuration must preserve vehicle identity.');

IPS_SetProperty($mapID, 'Accounts', json_encode([['AccountID' => $accountID], ['AccountID' => $secondID]], JSON_THROW_ON_ERROR));
IPS_ApplyChanges($mapID);
$accounts = array_values(array_filter(tileState($map)['sources'], static fn ($source) => $source['kind'] === 'icloud'));
check($accounts[0]['objects'][0]['id'] !== $accounts[1]['objects'][0]['id'], 'Identical imported IDs and names on separate accounts must remain separate.');

$account->fail = true;
OWIS_Update($mapID);
$state = tileState($map);
check(!$state['sources'][0]['available'] && $state['sources'][0]['objects'][0]['location'] !== null, 'A failed cache read must retain its last known fix.');
check($state['sources'][1]['available'] && $state['sources'][2]['available'], 'A cache failure must be isolated to that source.');
check(!str_contains($map->GetVisualizationTile(), 'private raw cache failure'), 'Raw cache exceptions must stay private.');
$account->fail = false;
$account->cache = ['success' => false, 'state' => 'signedOut', 'updatedAt' => 0, 'devices' => []];
OWIS_Update($mapID);
check(tileState($map)['sources'][0]['objects'] === [], 'An explicit sign-out must clear the account positions.');
IPS_SetProperty($mapID, 'Accounts', '[]');
IPS_ApplyChanges($mapID);
check(count(tileState($map)['sources']) === 1, 'The vehicle-only tile must work independently.');

$position = IPS_CreateVariable(VARIABLETYPE_STRING);
IPS_SetProperty($mapID, 'VehicleMode', 'json');
IPS_SetProperty($mapID, 'VehiclePosition', $position);
IPS_ApplyChanges($mapID);
check(!OWIS_NextDemoPosition($mapID), 'Demo action must be inactive for real variables.');
check(source($map, 'vehicle')['objects'][0]['location'] === null, 'Changing source must not keep the demo fix.');
SetValueString($position, json_encode(['latitude' => 0, 'longitude' => 0, 'timestamp' => $now], JSON_THROW_ON_ERROR));
$map->MessageSink(1, $position, VM_UPDATE, []);
$fix = source($map, 'vehicle')['objects'][0]['location'];
check($fix['latitude'] === 0.0 && $fix['longitude'] === 0.0 && $fix['accuracy'] === null, 'Zero coordinates are valid and unknown precision stays absent.');
SetValueString($position, json_encode(['latitude' => 15, 'longitude' => 12, 'timestamp' => $now - 1], JSON_THROW_ON_ERROR));
OWIS_Update($mapID);
$last = source($map, 'vehicle')['objects'][0];
check($last['location'] === $fix && !$last['current'], 'Out-of-order position cannot overwrite a newer fix.');
SetValueString($position, '{"latitude":99,"longitude":1,"timestamp":1}');
OWIS_Update($mapID);
check(source($map, 'vehicle')['objects'][0]['location'] === $fix, 'Invalid coordinates must keep only the prior valid fix.');

$lat = IPS_CreateVariable(VARIABLETYPE_FLOAT);
$lon = IPS_CreateVariable(VARIABLETYPE_FLOAT);
$time = IPS_CreateVariable(VARIABLETYPE_INTEGER);
IPS_SetProperty($mapID, 'VehicleMode', 'fields');
IPS_SetProperty($mapID, 'VehicleLatitude', $lat);
IPS_SetProperty($mapID, 'VehicleLongitude', $lon);
IPS_SetProperty($mapID, 'VehicleTimestamp', $time);
IPS_ApplyChanges($mapID);
SetValueFloat($lat, 50.1);
SetValueFloat($lon, 7.5);
OWIS_Update($mapID);
check(source($map, 'vehicle')['objects'][0]['location'] === null, 'Coordinates remain undisplayed until the timestamp commits the first fix.');
SetValueInteger($time, $now);
OWIS_Update($mapID);
SetValueInteger($time, 0);
SetValueFloat($lat, 50.2);
OWIS_Update($mapID);
$halfComplete = source($map, 'vehicle')['objects'][0];
check($halfComplete['location']['latitude'] === 50.1 && !$halfComplete['current'], 'An in-progress write cannot mix coordinates.');
SetValueFloat($lon, 7.6);
SetValueInteger($time, $now + 1);
OWIS_Update($mapID);
$committed = source($map, 'vehicle')['objects'][0];
check($committed['location']['latitude'] === 50.2 && $committed['location']['longitude'] === 7.6 && $committed['current'], 'A committed fix replaces both coordinates together.');

$rendererPath = '/hook/OpenWoIst' . $mapID . '/assets/maplibre-gl.js';
foreach ([['GET', $rendererPath, 200], ['POST', $rendererPath, 405], ['GET', '/hook/OpenWoIst' . $mapID, 404]] as [$method, $path, $status]) {
    [$actual, $body] = mapRequest($map, $path, $method);
    check($actual === $status, 'The static renderer remains available while IPSView is disabled.');
    if ($status === 200) {
        check(gzdecode($body) === file_get_contents($root . '/libs/helper/maps/vendor/maplibre-gl.js'), 'The WebHook must serve precisely the bundled renderer.');
    }
}

require __DIR__ . '/design-ipsview.php';
require __DIR__ . '/position-converter.php';
require __DIR__ . '/source-selection.php';
require __DIR__ . '/position-presets.php';
require __DIR__ . '/tracker-sources.php';
require __DIR__ . '/tractive-adapter.php';
echo "OpenWoIst source and tile integration passed.\n";
require __DIR__ . '/validate_structure.php';
