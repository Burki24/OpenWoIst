<?php

declare(strict_types=1);

$rootPath = '/hook/OpenWoIst' . $mapID;
[$status] = mapRequest($map, $rootPath . '/page');
check($status === 404, 'IPSView is opt-in.');

IPS_SetProperty($mapID, 'VehicleMode', 'demo');
IPS_SetProperty($mapID, 'Accounts', json_encode([['AccountID' => $secondID]], JSON_THROW_ON_ERROR));
IPS_SetProperty($mapID, 'TileFontSize', 17);
IPS_SetProperty($mapID, 'TileVehicleColor', 0xA64721);
IPS_SetProperty($mapID, 'IPSViewVehicleColor', 0x8342E5);
IPS_SetProperty($mapID, 'TileMapLightStyle', 'bright');
IPS_SetProperty($mapID, 'IPSViewMapLightStyle', 'fiord');
IPS_SetProperty($mapID, 'EnableIPSView', true);
IPS_ApplyChanges($mapID);

$htmlID = IPS_GetObjectIDByIdent('IPSViewOpenWoIst', $mapID);
$frame = GetValueString($htmlID);
$frameSettings = bootstrap($frame)['options'];
$token = $frameSettings['token'];
check($frameSettings['page'] === $rootPath . '/page' && preg_match('/^[a-f0-9]{64}$/D', $token) === 1, 'The output uses a same-origin page and a random token.');
check(str_contains($frame, 'name="burki24.wheel.v1"') && !str_contains($frame, $phone['name']), 'The WebContent variable holds only the marked loader and its access configuration.');
$readCount = $second->reads;
[$status, $page] = mapRequest($map, $rootPath . '/page');
$public = bootstrap($page);
check($status === 200 && $public['mode'] === 'ipsview' && $public['state']['sources'] === [] && $public['options']['tileHeaderInset'] === 0, 'The public shell has an empty state and no Symcon title inset.');
check(!str_contains($page, $token) && !str_contains($page, '#8342e5') && !str_contains($page, 'Test iPhone'), 'The public shell publishes no access token, custom style or device data.');
[$status] = mapRequest($map, $rootPath . '/state');
check($status === 403, 'The state requires a token.');
[$status] = mapRequest($map, $rootPath . '/state?token=' . $token);
check($status === 404, 'Token queries do not address the state endpoint.');
[$status] = mapRequest($map, $rootPath . '/state', 'POST', $token);
check($status === 405, 'The IPSView state endpoint is read-only.');

[$status, $body] = mapRequest($map, $rootPath . '/state', token: $token);
$ips = json_decode($body, true, 64, JSON_THROW_ON_ERROR);
$tile = tileState($map);
check($status === 200 && $ips['sources'] === $tile['sources'] && count($ips['sources']) === 2, 'Both outputs share Apple and demo positions.');
check($second->reads === $readCount, 'IPSView reads the stored snapshot without calling the account.');
check(!array_key_exists('scope', $ips), 'The internal source-scope signature is private.');
check($ips['presentation']['basemap']['lightStyle'] === 'bright' && $tile['presentation']['basemap']['lightStyle'] === 'bright', 'IPSView inherits the tile basemap by default.');
check(str_contains($tile['presentation']['css'], '--openwoist-font-size:17px') && str_contains($tile['presentation']['css'], '--openwoist-vehicle:#a64721'), 'The tile has its own font size and marker colors.');
check(str_contains($ips['presentation']['css'], '--ipsview-role-font-family') && str_contains($ips['presentation']['css'], '--openwoist-vehicle:#8342e5'), 'IPSView has its own shared style and marker colors while inheriting the basemap.');
[$status, $unchanged] = mapRequest($map, $rootPath . '/state', token: $token, etag: '"' . hash('sha256', $body) . '"');
check($status === 304 && $unchanged === '', 'A valid conditional request elides unchanged CSS and state.');
[$status] = mapRequest($map, $rootPath . '/state', token: str_repeat('0', 64), etag: '"' . hash('sha256', $body) . '"');
check($status === 403, 'An ETag cannot bypass authentication.');

IPS_SetProperty($mapID, 'IPSViewUseTileMap', false);
IPS_ApplyChanges($mapID);
[$status, $independentBody] = mapRequest($map, $rootPath . '/state', token: $token);
$independent = json_decode($independentBody, true, 64, JSON_THROW_ON_ERROR);
check($status === 200 && $independent['presentation']['basemap']['lightStyle'] === 'fiord'
    && tileState($map)['presentation']['basemap']['lightStyle'] === 'bright', 'Independent profiles preserve the other output’s configuration.');
check(GetValueString($htmlID) === $frame, 'Style changes keep the existing WebContent document and token.');

$form = json_decode($map->GetConfigurationForm(), true, 64, JSON_THROW_ON_ERROR);
$panels = array_column($form['elements'], null, 'caption');
foreach (['Tile designer' => 'Tile', 'IPSView designer' => 'IPSView'] as $label => $prefix) {
    $designer = $panels[$label];
    $items = array_column($designer['items'], null, 'caption');
    $guide = $items['OpenWoIst color guide']['items'];
    $image = $guide[1];
    $svg = base64_decode(explode(',', $image['image'], 2)[1], true);
    check($image['name'] === $prefix . 'MapColorGuide' && $image['width'] === '100%', 'Each designer has a responsive color guide.');
    check(str_contains($svg, $items['Vehicle marker color']['caption']) && str_contains($svg, '<title>' . $label . '</title>'), 'The guide uses the actual field caption and the output name.');
    check(str_contains($svg, 'Helle Kartenfarben') && str_contains($svg, 'Dunkle Kartenfarben'), 'Shared basemap palette captions are taken from the active translation catalog.');
    check(!str_contains($svg, '<script') && !str_contains($svg, $token), 'Color guides consist of synthetic, self-contained graphics.');
}

OWIS_NextDemoPosition($mapID);
[$status, $updatedBody] = mapRequest($map, $rootPath . '/state', token: $token);
$updated = json_decode($updatedBody, true, 64, JSON_THROW_ON_ERROR);
check($status === 200 && $updated['sources'][1]['objects'][0]['location']['longitude'] !== $ips['sources'][1]['objects'][0]['location']['longitude'], 'A demo move reaches the open IPSView page on the next state read.');
check(GetValueString($htmlID) === $frame, 'A location update must not replace the HTML variable.');

IPS_SetProperty($mapID, 'Accounts', '[]');
IPS_ApplyChanges($mapID);
[$status] = mapRequest($map, $rootPath . '/state', token: $token);
check($status === 403 && GetValueString($htmlID) !== $frame, 'Source assignment changes revoke the old token and regenerate the loader.');
$newFrame = GetValueString($htmlID);
$newToken = bootstrap($newFrame)['options']['token'];
[$status, $limitedBody] = mapRequest($map, $rootPath . '/state', token: $newToken);
check($status === 200 && count(json_decode($limitedBody, true, 64, JSON_THROW_ON_ERROR)['sources']) === 1, 'A new token receives only the currently assigned sources.');

IPS_SetProperty($mapID, 'EnableIPSView', false);
IPS_ApplyChanges($mapID);
[$status] = mapRequest($map, $rootPath . '/state', token: $newToken);
check($status === 404 && GetValueString($htmlID) === $newFrame, 'Disable revokes access and keeps the stored WebContent variable.');

IPS_SetProperty($mapID, 'EnableIPSView', true);
IPS_SetProperty($mapID, 'IPSViewServerURL', 'https://server.example/path');
IPS_ApplyChanges($mapID);
check(bootstrap(GetValueString($htmlID))['options']['token'] === '', 'An invalid server URL cannot enable a route or put a token in the loader.');
IPS_SetProperty($mapID, 'IPSViewServerURL', 'http://symcon:3777/');
IPS_ApplyChanges($mapID);
check(bootstrap(GetValueString($htmlID))['options']['origin'] === 'http://symcon:3777', 'A private Symcon server origin with an explicit port is accepted.');
