<?php

declare(strict_types=1);

$targetID = IPS_CreateVariable(VARIABLETYPE_STRING);
$runConverter = static function (string $source, array|string $payload, array $options = []) use ($targetID): array
{
    $_IPS = [
        'Source'     => $source,
        'PositionID' => $targetID,
        'Payload'    => is_array($payload) ? json_encode($payload, JSON_THROW_ON_ERROR) : $payload,
        ...$options
    ];
    // The stubs put their parameter assignment before a script's strict declaration.
    ob_start();
    try {
        require __DIR__ . '/../libs/PositionConverter.ips.php';
        $output = ob_get_contents();
    } finally {
        ob_end_clean();
    }

    return json_decode($output, true, 8, JSON_THROW_ON_ERROR);
};

$prefix = 'vehicle.cabin.infotainment.navigation.currentLocation.';
$bmw = [
    $prefix . 'latitude'  => ['value' => '52,5201', 'timestamp' => '2025-01-01T12:00:00.123456789Z'],
    $prefix . 'longitude' => ['value' => '13,4051', 'timestamp' => '2025-01-01T13:00:00.123456789+01:00']
];
$result = $runConverter('bmw-cardata', ['data' => $bmw], ['DecimalSeparator' => ',']);
$first = GetValueString($targetID);
check(
    $result === ['status' => 'published']
    && json_decode($first, true) === ['latitude' => 52.5201, 'longitude' => 13.4051, 'timestamp' => 1735732800],
    'BMW coordinates with decimal commas and equivalent ISO timestamps must become one position.'
);

$bmw[$prefix . 'longitude']['timestamp'] = '2025-01-01T12:00:00.123456788Z';
$result = $runConverter('bmw-cardata', $bmw, ['DecimalSeparator' => ',']);
check($result === ['status' => 'ignored', 'reason' => 'unpaired_measurement']
    && GetValueString($targetID) === $first, 'Different BMW times in the same Unix second must not be combined.');

$ownTracks = ['_type' => 'location', 'lat' => '-33.8688', 'lon' => 151.2093, 'tst' => 1735732801, 'acc' => 12.5];
$result = $runConverter('owntracks', $ownTracks);
check(
    $result === ['status' => 'published'] && json_decode(GetValueString($targetID), true)
    === ['latitude' => -33.8688, 'longitude' => 151.2093, 'timestamp' => 1735732801, 'horizontalAccuracy' => 12.5],
    'OwnTracks seconds and accuracy must be preserved without swapping the axes.'
);

$result = $runConverter('owntracks', ['lat' => 0, 'lon' => 0, 'tst' => '1735732802999'], ['TimeFormat' => 'unix-milliseconds']);
$latest = GetValueString($targetID);
check(
    $result === ['status' => 'published']
    && json_decode($latest, true) === ['latitude' => 0, 'longitude' => 0, 'timestamp' => 1735732802],
    'Milliseconds must be truncated, and a valid zero coordinate pair must be accepted.'
);
check(
    \OpenWoIst\LocationSnapshot::fix(json_decode($latest, true), time()) !== null,
    'OpenWoIst must accept the converter output as a vehicle position.'
);

foreach ([
    [$ownTracks, [], 'position_not_newer'],
    [['lat' => 1, 'lon' => 2, 'tst' => 1735732802], [], 'position_not_newer'],
    [['lat' => 1, 'lon' => 2, 'tst' => 1735732803000], [], 'invalid_position'],
    [['lat' => 1, 'lon' => 2, 'tst' => '2025-01-01T12:00:04'], ['TimeFormat' => 'iso8601'], 'invalid_position'],
    [['lat' => 1, 'lon' => 2, 'tst' => '2025-02-30T12:00:04Z'], ['TimeFormat' => 'iso8601'], 'invalid_position'],
    [['lat' => 1, 'lon' => 2], [], 'invalid_position'],
    [['lat' => 91, 'lon' => 2, 'tst' => 1735732804], [], 'invalid_position'],
    [['lat' => 1, 'lon' => '1e1000', 'tst' => 1735732804], [], 'invalid_position'],
    [[...$ownTracks, '_type' => 'transition', 'tst' => 1735732803], [], 'invalid_payload'],
    ['{"private":', [], 'invalid_payload']
] as [$payload, $options, $reason]) {
    $result = $runConverter('owntracks', $payload, $options);
    check($result === ['status' => 'ignored', 'reason' => $reason]
        && GetValueString($targetID) === $latest, 'Rejected input must leave the last position intact: ' . $reason);
}
