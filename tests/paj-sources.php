<?php

declare(strict_types=1);

final class PajSimulationOpenWoIst extends OpenWoIst
{
    public int $clock = 0;
    public array $responses = [];
    public array $requests = [];
    public array $formChanges = [];

    protected function pajTime(): int
    {
        return $this->clock;
    }

    protected function pajTransport(): Closure
    {
        return function (string $url, array $headers, ?array $json): array
        {
            $this->requests[] = [$url, $headers, $json];
            return array_shift($this->responses) ?? throw new RuntimeException('Unexpected PAJ GPS request.');
        };
    }

    protected function UpdateFormField(string $Field, string $Parameter, mixed $Value): bool
    {
        $this->formChanges[$Field][$Parameter] = $Value;
        return true;
    }

    protected function LoadConfigurationForm(): array
    {
        // The test subclass lives outside the module directory.
        return json_decode(file_get_contents(__DIR__ . '/../OpenWoIst/form.json'), true, 64, JSON_THROW_ON_ERROR);
    }
}

$modules[$mapModule]['Class'] = PajSimulationOpenWoIst::class;
$metadata->setValue(null, $modules);
$pajMapID = IPS_CreateInstance($mapModule);
$modules[$mapModule]['Class'] = OpenWoIst::class;
$metadata->setValue(null, $modules);
$pajMap = IPS\InstanceManager::getInstanceInterface($pajMapID);
$pajMap->clock = time();
$pajForm = static function () use ($pajMap): array
{
    $form = array_column(json_decode($pajMap->GetConfigurationForm(), true, 64, JSON_THROW_ON_ERROR)['elements'], null, 'name');
    $panels = array_column($form['PetSources']['items'], null, 'name');
    check(isset($panels['PajSources']), 'PAJ GPS has its own panel under Pets.');
    return array_column($panels['PajSources']['items'], null, 'name');
};
$pajSetup = $pajForm();
check($pajSetup['PajPassword']['type'] === 'PasswordTextBox' && empty($pajSetup['PajDevices']['values']), 'The PAJ panel masks the password and starts with an empty device list.');
$pajMap->RequestAction('LoadPajDevices', '[]');
check($pajMap->requests === [] && $pajMap->formChanges['PajDiscoveryStatus']['visible'], 'Device discovery requires applied account credentials.');

$pajEmail = 'synthetic-paj@example.invalid';
$pajPassword = 'synthetic-portal-password';
$petA = 7011234;
$petB = 7011235;
$petC = 7011236;
$pajSuccess = static fn (array $data): array => ['status' => 200, 'body' => json_encode(['success' => $data], JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR)];
$pajFixTime = $pajMap->clock - 120;
$pajReport = static fn (int $id, float $latitude, int $time): array => ['iddevice' => $id, 'lat' => $latitude, 'lng' => 13.41, 'dateunix' => $time];
$pajToken = static fn (string $name): array => $pajSuccess(['token' => 'test-paj-token-' . $name, 'refresh_token' => 'test-paj-refresh-' . $name]);
$pajError = static fn (int $status): array => ['status' => $status, 'body' => 'private-error-fixture'];
IPS_SetProperty($pajMapID, 'PajEmail', $pajEmail);
IPS_SetProperty($pajMapID, 'PajPassword', $pajPassword);
IPS_SetProperty($pajMapID, 'Accounts', json_encode([['AccountID' => $accountID]], JSON_THROW_ON_ERROR));
IPS_SetProperty($pajMapID, 'VehicleMode', 'demo');
IPS_SetProperty($pajMapID, 'EnableIPSView', true);
IPS_ApplyChanges($pajMapID);
OWIS_PollPajGPS($pajMapID);
check(empty($pajForm()['PajDevices']['values']) && $pajMap->requests === [], 'Opening the form, applying credentials and polling an empty selection need no network calls.');

$pajDeviceResponse = $pajSuccess([
    ['id' => $petA, 'name' => 'PAJ test dog'], ['id' => $petB, 'name' => 'PAJ test cat'], ['id' => $petC, 'name' => 'PAJ unselected']
]);
$pajMap->responses = [$pajToken('first'), $pajDeviceResponse];
$pajMap->RequestAction('LoadPajDevices', '[]');
check($pajMap->requests === [
    ['https://connect.paj-gps.de/api/v1/login', ['Accept: application/json', 'Content-Type: application/json'], ['email' => $pajEmail, 'password' => $pajPassword]],
    ['https://connect.paj-gps.de/api/v1/device', ['Accept: application/json', 'Content-Type: application/json', 'Authorization: Bearer test-paj-token-first'], null]
], 'Device discovery signs in at the documented PAJ host before reading the account devices.');
$listed = array_column(json_decode($pajMap->formChanges['PajDevices']['values'], true, 8, JSON_THROW_ON_ERROR), null, 'PajDeviceID');
check(count($listed) === 3 && !$listed[$petA]['PajSelected'] && $listed[$petA]['PajMarkerColor'] === -1, 'Discovery lists all three devices without selecting them automatically.');
$listed[$petA]['PajSelected'] = true;
$listed[$petA]['PajMarkerColor'] = 0x123456;
$listed[$petB]['PajSelected'] = true;
$listed[$petB]['PajMarkerColor'] = 0;
$pajMap->responses = [$pajDeviceResponse];
$pajMap->RequestAction('LoadPajDevices', json_encode(array_values($listed), JSON_THROW_ON_ERROR));
$listed = json_decode($pajMap->formChanges['PajDevices']['values'], true, 8, JSON_THROW_ON_ERROR);
check(array_column($listed, 'PajMarkerColor') === [0, 0x123456, -1] && array_column($listed, 'PajSelected') === [true, true, false], 'Reloading the device list preserves unsaved selections and individual colors.');
IPS_SetProperty($pajMapID, 'PajDevices', json_encode($listed, JSON_THROW_ON_ERROR));
IPS_ApplyChanges($pajMapID);
$pajView = bootstrap($pajMap->GetIPSViewHTML())['options'];
$pajSources = static function () use ($pajMap, $pajView, $pajEmail, $pajPassword, $petA, $petB, $petC): array
{
    $tileHTML = $pajMap->GetVisualizationTile();
    $tile = bootstrap($tileHTML)['state']['sources'];
    [$status, $body] = mapRequest($pajMap, dirname($pajView['page']) . '/state', token: $pajView['token']);
    check($status === 200 && json_decode($body, true, 32, JSON_THROW_ON_ERROR)['sources'] === $tile, 'Both visualizations receive the same PAJ fixes.');
    check(array_column($tile, 'kind') === ['icloud', 'demo', 'tracker', 'tracker'], 'Two selected PAJ devices coexist with iCloud and the demo vehicle.');
    foreach ([$pajEmail, $pajPassword, 'test-paj-token', 'test-paj-refresh', (string) $petA, (string) $petB, (string) $petC] as $private) {
        check(!str_contains($tileHTML . $body, $private), 'The map responses keep PAJ credentials and device IDs private.');
    }
    return array_column(array_slice($tile, 2), null, 'name');
};

$pajMap->responses = [$pajSuccess([
    $pajReport($petA, 52.50, $pajFixTime), $pajReport($petA, 52.48, $pajFixTime - 10),
    $pajReport($petB, 52.49, $pajFixTime), $pajReport($petC, 52.40, $pajFixTime)
])];
OWIS_PollPajGPS($pajMapID);
$initialDogSource = $pajSources()['PAJ test dog'];
$initialPaj = $initialDogSource['objects'][0];
check($initialPaj['current'] && $initialPaj['location']['latitude'] === 52.50 && $initialPaj['location']['timestamp'] === $pajFixTime, 'The marker shows the newest valid point and its PAJ measurement time.');
check(end($pajMap->requests)[2] === ['deviceIDs' => [$petA, $petB]], 'The position request includes only selected devices.');
$pajMap->clock += 299;
OWIS_PollPajGPS($pajMapID);
$pajMap->clock++;
$pajMap->responses = [$pajError(401), $pajToken('second'), $pajSuccess([$pajReport($petA, 52.52, $pajFixTime + 60), $pajReport($petB, 200.0, $pajFixTime + 60)])];
OWIS_PollPajGPS($pajMapID);
$renewal = $pajMap->requests[count($pajMap->requests) - 2];
check($renewal[0] === 'https://connect.paj-gps.de/api/v1/updatetoken' && $renewal[2] === ['email' => $pajEmail, 'refresh_token' => 'test-paj-refresh-first'], 'An expired session renews its token before retrying the same position request.');
$updatedPaj = $pajSources();
$updatedDog = $updatedPaj['PAJ test dog']['objects'][0];
$updatedCat = $updatedPaj['PAJ test cat']['objects'][0];
check($updatedDog['id'] === $initialPaj['id'] && $updatedDog['markerColor'] === 0x123456 && $updatedDog['location']['latitude'] === 52.52 && $updatedDog['location']['timestamp'] === $pajFixTime + 60, 'The same colored marker moves when PAJ reports a newer point.');
check(!$updatedCat['current'] && $updatedCat['markerColor'] === 0 && $updatedCat['location']['latitude'] === 52.49, 'An invalid report keeps the previous point and its individual marker color.');
if (in_array('--paj-motion-fixtures', $argv, true)) {
    echo json_encode(['initial' => $initialDogSource, 'updated' => $updatedPaj['PAJ test dog']], JSON_THROW_ON_ERROR);
    exit(0);
}

$pajMap->clock += 300;
$pajMap->responses = [$pajError(503)];
OWIS_PollPajGPS($pajMapID);
$offlinePaj = $pajSources()['PAJ test dog']['objects'][0];
check(!$offlinePaj['current'] && $offlinePaj['location'] === $updatedDog['location'], 'Provider failure keeps the last known valid location.');

IPS_SetProperty($pajMapID, 'PajEmail', 'other-paj@example.invalid');
IPS_ApplyChanges($pajMapID);
OWIS_PollPajGPS($pajMapID);
check(array_column(tileState($pajMap)['sources'], 'kind') === ['icloud', 'demo'] && empty($pajForm()['PajDevices']['values']), 'Changing the account drops all selections and cached locations from the prior account.');
check($pajMap->responses === [], 'All expected PAJ calls were consumed.');
