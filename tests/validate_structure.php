<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$json = static fn (string $file): array => json_decode(file_get_contents($root . '/' . $file), true, 64, JSON_THROW_ON_ERROR);
$library = $json('library.json');
$module = $json('OpenWoIst/module.json');
$guid = '/^\{[0-9A-F]{8}(?:-[0-9A-F]{4}){3}-[0-9A-F]{12}\}$/D';
if (!preg_match($guid, $library['id']) || !preg_match($guid, $module['id']) || $library['id'] === $module['id']
    || $library['name'] !== $module['name'] || $library['compatibility']['version'] !== '9.0' || $module['prefix'] !== 'OWIS') {
    throw new RuntimeException('Invalid library/module metadata.');
}

$verify = static function (string $file, string $checksum) use ($root): void
{
    $path = $root . '/' . $file;
    if (!is_file($path) || hash_file('sha256', $path) !== $checksum) {
        throw new RuntimeException('Asset integrity check failed for ' . $file);
    }
};
foreach ($json('libs/helper/manifest.json')['helpers'] as $helper) {
    foreach ([$helper, ...($helper['dependencies'] ?? [])] as $entry) {
        $verify($entry['path'], $entry['sha256']);
        foreach ($entry['assets'] ?? [] as $asset) {
            $verify($asset['path'], $asset['sha256']);
        }
    }
}
foreach ($json('libs/vendor/leaflet/manifest.json')['files'] as $file => $checksum) {
    $verify('libs/vendor/leaflet/' . $file, $checksum);
}

$json('OpenWoIst/form.json');
$json('OpenWoIst/locale.json');
$adapter = $json('OpenWoIstTractive/module.json');
if (!preg_match($guid, $adapter['id']) || in_array($adapter['id'], [$library['id'], $module['id']], true)
    || $adapter['prefix'] !== 'OWIT' || !is_file($root . '/OpenWoIstTractive/README.md')) {
    throw new RuntimeException('Invalid Tractive adapter metadata.');
}
$json('OpenWoIstTractive/form.json');
$json('OpenWoIstTractive/locale.json');
echo "OpenWoIst structure and bundled assets passed.\n";
