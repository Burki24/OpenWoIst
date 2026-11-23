<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/helper/ConfigurationFormHelper.php';
require_once __DIR__ . '/../libs/helper/DebugFlowHelper.php';
require_once __DIR__ . '/../libs/helper/GeofenceHelper.php';
require_once __DIR__ . '/../libs/helper/HttpResponseHelper.php';
require_once __DIR__ . '/../libs/helper/IPSViewHTMLPageHelper.php';
require_once __DIR__ . '/../libs/helper/IPSViewStyleConfigurationHelper.php';
require_once __DIR__ . '/../libs/helper/OpenFreeMapHelper.php';
require_once __DIR__ . '/../libs/helper/ResponsiveVisualizationHelper.php';
require_once __DIR__ . '/../libs/helper/ReverseGeocodingHelper.php';
require_once __DIR__ . '/../libs/helper/VisualizationThemeConfigurationHelper.php';
require_once __DIR__ . '/../libs/IPSViewMap.php';
require_once __DIR__ . '/../libs/ICloudDeviceSelection.php';
require_once __DIR__ . '/../libs/LocationAddresses.php';
require_once __DIR__ . '/../libs/LocationSnapshot.php';
require_once __DIR__ . '/../libs/LocationZones.php';
require_once __DIR__ . '/../libs/MapColorGuide.php';
require_once __DIR__ . '/../libs/MapDesign.php';
require_once __DIR__ . '/../libs/PositionConverter.php';
require_once __DIR__ . '/../libs/VehicleSourceForm.php';

use Burki24\SymconModuleHelper\ConfigurationFormHelper;
use Burki24\SymconModuleHelper\DebugFlowHelper;
use Burki24\SymconModuleHelper\HttpResponseHelper;
use Burki24\SymconModuleHelper\IPSViewHTMLPageHelper;
use Burki24\SymconModuleHelper\IPSViewStyleConfigurationHelper;
use Burki24\SymconModuleHelper\OpenFreeMapHelper;
use Burki24\SymconModuleHelper\ResponsiveVisualizationHelper;
use Burki24\SymconModuleHelper\VisualizationThemeConfigurationHelper;
use OpenWoIst\ICloudDeviceSelection;
use OpenWoIst\IPSViewMap;
use OpenWoIst\LocationAddresses;
use OpenWoIst\LocationSnapshot;
use OpenWoIst\LocationZones;
use OpenWoIst\MapColorGuide;
use OpenWoIst\MapDesign;
use OpenWoIst\PositionConverter;
use OpenWoIst\VehicleSourceForm;

class OpenWoIst extends IPSModuleStrict
{
    use ConfigurationFormHelper;
    use DebugFlowHelper;
    use HttpResponseHelper;
    use IPSViewHTMLPageHelper;
    use IPSViewStyleConfigurationHelper;
    use OpenFreeMapHelper;
    use ResponsiveVisualizationHelper;
    use VisualizationThemeConfigurationHelper;
    use IPSViewMap;
    use ICloudDeviceSelection;
    use LocationAddresses;
    use LocationZones;
    use MapColorGuide;
    use MapDesign;
    use VehicleSourceForm;

    private const ACCOUNT_MODULE = '{10BF9A03-98A3-495A-B0FA-A4FA0B1002D5}';
    private const DEMO_ROUTE = [
        [52.51453, 13.35010],
        [52.51461, 13.35684],
        [52.51466, 13.36335],
        [52.51489, 13.37086]
    ];

    public function Create(): void
    {
        $this->DebugFlowCall(__FUNCTION__, __LINE__, function (): void
        {
            parent::Create();
            $this->RegisterPropertyString('Accounts', '[]');
            $this->RegisterPropertyBoolean('ShowAllDevices', true);
            $this->RegisterPropertyString('SelectedDevices', '[]');
            $this->RegisterPropertyString('Vehicles', '[]');
            $this->RegisterPropertyString('Trackers', '[]');
            $this->RegisterPropertyString('VehicleMode', 'off');
            $this->RegisterPropertyString('VehicleJSONFormat', '');
            $this->RegisterPropertyString('VehicleName', 'Vehicle');
            $this->RegisterPropertyString('VehicleTimeFormat', 'default');
            $this->RegisterPropertyString('VehicleDecimalSeparator', '.');
            $this->RegisterPropertyInteger('VehicleMarkerColor', -1);
            foreach (['VehiclePosition', 'VehicleLatitude', 'VehicleLongitude', 'VehicleTimestamp'] as $ident) {
                $this->RegisterPropertyInteger($ident, 0);
            }
            $this->RegisterPropertyInteger('StaleAfter', 900);
            $this->RegisterAttributeString('Snapshot', '');
            $this->RegisterAttributeInteger('DemoStep', 0);
            $this->RegisterTimer('ReadSources', 0, 'OWIS_Update($_IPS["TARGET"]);');
            $this->registerLocationAddresses();
            $this->registerLocationZones();
            $this->registerMapDesign();
            $this->registerIPSViewMap();
            $this->SetVisualizationType(1);
            $this->registerMapHook();
        }, true);
    }

    public function ApplyChanges(): void
    {
        $this->DebugFlowCall(__FUNCTION__, __LINE__, function (): void
        {
            parent::ApplyChanges();
            $this->SetVisualizationType(1);
            $this->registerMapHook();
            $demo = $this->ReadPropertyString('VehicleMode') === 'demo';
            $this->MaintainVariable('DemoPosition', $this->Translate('Demo vehicle position'), VARIABLETYPE_STRING, '', 0, $demo);
            if ($demo && $this->GetValue('DemoPosition') === '') {
                $this->writeDemoPosition($this->ReadAttributeInteger('DemoStep'));
            }
            $this->syncReferences();
            if (!$this->ReadPropertyBoolean('EnableAddressResolution')) {
                $this->ClearReverseGeocodingCache();
            }
            $this->SetTimerInterval('ReadSources', $this->accountIDs() !== [] || $this->configuredPositions() !== [] ? 5000 : 0);
            $this->publish(true);
            $this->maintainIPSViewMap();
        }, true);
    }

    public function GetConfigurationForm(): string
    {
        return $this->DebugFlowCall(__FUNCTION__, __LINE__, function (): string
        {
            $form = $this->configureDeviceSelection($this->configureVehicleForm($this->LoadConfigurationForm()));
            $form['elements'] = $this->SetFormFieldVisibility($form['elements'], ['AddressConfigurationError'], $this->ReadPropertyBoolean('EnableAddressResolution') && $this->addressConfiguration() === null);
            $invalidZones = false;
            try {
                $this->zoneConfiguration($this->locationMaximumAge());
            } catch (InvalidArgumentException) {
                $invalidZones = true;
            }
            $form['elements'] = $this->SetFormFieldVisibility($form['elements'], ['ZoneConfigurationError'], $invalidZones);
            $form['elements'][] = $this->mapDesigner(false);
            $form['elements'][] = $this->mapDesigner(true);
            return $this->EncodeConfigurationForm($form);
        });
    }

    public function MessageSink(int $TimeStamp, int $SenderID, int $Message, array $Data): void
    {
        $this->DebugFlowCall(__FUNCTION__, __LINE__, function () use ($TimeStamp, $SenderID, $Message, $Data): void
        {
            parent::MessageSink($TimeStamp, $SenderID, $Message, $Data);
            if ($Message === VM_UPDATE && in_array($SenderID, array_column($this->configuredPositions(), 'trigger'), true)) {
                $this->Update();
            }
        }, true);
    }

    /** Reads the assigned local sources and publishes their combined snapshot. */
    public function Update(): void
    {
        $this->DebugFlowCall(__FUNCTION__, __LINE__, function (): void
        {
            $this->publish(false);
        }, true);
    }

    /** Advances the generated demo variable by one position. */
    public function NextDemoPosition(): bool
    {
        return $this->DebugFlowCall(__FUNCTION__, __LINE__, function (): bool
        {
            if ($this->ReadPropertyString('VehicleMode') !== 'demo' || $this->GetIDForIdent('DemoPosition') === false) {
                return false;
            }
            $step = ($this->ReadAttributeInteger('DemoStep') + 1) % count(self::DEMO_ROUTE);
            if (!$this->writeDemoPosition($step)) {
                return false;
            }
            $this->WriteAttributeInteger('DemoStep', $step);
            $this->Update();
            return true;
        });
    }

    public function GetVisualizationTile(): string
    {
        return $this->DebugFlowCall(__FUNCTION__, __LINE__, function (): string
        {
            return $this->renderMap(false);
        });
    }

    public function RequestAction(string $Ident, mixed $Value): void
    {
        $this->DebugFlowCall(__FUNCTION__, __LINE__, function () use ($Ident, $Value): void
        {
            if ($this->HandleIPSViewHTMLPageAction($Ident, $Value) || $this->handleVehicleFormAction($Ident, $Value)) {
                return;
            }
            if ($Ident !== 'GetState') {
                throw new InvalidArgumentException('Unsupported action.');
            }
            $this->publish(true);
        }, true);
    }

    protected function VisualizationAssetDirectory(): string
    {
        return __DIR__ . '/visualization';
    }

    /** Dispatches the public renderer, public IPSView page and authenticated state. */
    protected function ProcessHookData(): void
    {
        $this->DebugFlowCall(__FUNCTION__, __LINE__, function (): void
        {
            $path = $_SERVER['REQUEST_URI'] ?? '';
            $method = $_SERVER['REQUEST_METHOD'] ?? '';
            if ($path !== $this->rendererPath()) {
                $this->serveIPSViewMap($path, $method);
                return;
            }
            if ($method !== 'GET') {
                header('Allow: GET');
                $this->SendPlainTextResponse(405, 'Method not allowed.');
                return;
            }
            try {
                $renderer = $this->OpenFreeMapRendererAsset();
            } catch (RuntimeException) {
                $this->SendPlainTextResponse(503, 'Renderer unavailable.');
                return;
            }
            header('Content-Encoding: gzip');
            $this->SendResponse(200, 'application/javascript', $renderer);
        }, true);
    }

    private function renderMap(bool $ipsView): string
    {
        $assets = $this->OpenFreeMapAssets($this->rendererPath());
        $snapshot = $ipsView ? ['sources' => [], 'staleAfter' => 900] : $this->visualizationState($this->storedSnapshot(), false);
        return $this->RenderVisualizationHTMLPage($ipsView, [
            'title'        => 'OpenWoIst',
            'state'        => $snapshot,
            'language'     => $this->NormalizeHelperTranslationLanguage($this->ResolveHelperTranslationLanguage()),
            'translations' => $this->IPSViewTranslationsFromLocale(),
            'options'      => [
                ...$this->ResponsiveVisualizationLayoutOptions(!$ipsView),
                'viewStateKey' => 'OWIS:' . $this->InstanceID . ($ipsView ? ':ipsview' : ''),
                'statePath'    => $ipsView ? $this->mapHookPath() . '/state' : ''
            ],
            'replacements' => [
                '{{RESPONSIVE_CSS}}' => $this->ResponsiveVisualizationCSS('#openwoist', 'openwoist', true),
                '{{LEAFLET_CSS}}'    => $this->leafletAsset('leaflet.css'),
                '{{LEAFLET_JS}}'     => $this->leafletAsset('leaflet.js'),
                '{{VECTOR_CSS}}'     => $assets['css'],
                '{{VECTOR_JS}}'      => $assets['script']
            ]
        ]);
    }

    private function visualizationState(array $snapshot, bool $ipsView): array
    {
        return [
            'sources'         => $snapshot['sources'],
            'zones'           => $snapshot['zones'] ?? [],
            'addressProvider' => $snapshot['addressProvider'] ?? '',
            'staleAfter'      => $snapshot['staleAfter'],
            'presentation'    => $this->mapPresentation($ipsView)
        ];
    }

    private function locationMaximumAge(): int
    {
        return max(60, min(86400, $this->ReadPropertyInteger('StaleAfter')));
    }

    private function publish(bool $force): void
    {
        $lock = 'OpenWoIst.Snapshot.' . $this->InstanceID;
        if (!IPS_SemaphoreEnter($lock, 1000)) {
            return;
        }
        try {
            $scope = $this->sourceScope();
            $previous = $this->storedSnapshot();
            $old = array_column($previous['sources'], null, 'id');
            $sources = [];
            $now = time();
            $deviceSettings = $this->deviceSettings();
            $selectedDevices = $this->selectedDeviceKeys($deviceSettings);
            foreach ($this->accountIDs() as $id) {
                $key = LocationSnapshot::key('icloud', $id);
                $source = $this->readAccount($id, $key, $old[$key]['objects'] ?? [], $now, $selectedDevices);
                $source['objects'] = array_map(static fn (array $object): array => LocationSnapshot::withMarkerColor(
                    $object,
                    $deviceSettings[$object['id']]['MarkerColor'] ?? null
                ), $source['objects']);
                $sources[] = $source;
            }
            foreach ($this->configuredPositions() as $key => $position) {
                $sources[] = $this->readPositionSource($position, $key, $old[$key]['objects'][0]['location'] ?? null, $now);
            }
            if ($scope !== $this->sourceScope()) {
                return;
            }
            $maxAge = $this->locationMaximumAge();
            $zones = $this->evaluateLocationZones($sources, $previous, $now, $maxAge, $scope);
            $addressProvider = $this->decorateLocationAddresses($sources, $now, $maxAge);
            $snapshot = $zones + [
                'addressProvider' => $addressProvider,
                'sources'         => $sources,
                'staleAfter'      => $maxAge,
                'scope'           => $scope
            ];
            $value = $this->EncodeVisualizationHTMLJSON($snapshot);
            $changed = $value !== $this->ReadAttributeString('Snapshot');
            if ($changed) {
                $this->WriteAttributeString('Snapshot', $value);
            }
            $this->SetStatus($sources === [] ? IS_INACTIVE : (in_array(true, array_column($sources, 'available'), true) ? IS_ACTIVE : 201));
            if ($force || $changed) {
                $payload = $this->EncodeVisualizationHTMLJSON($this->visualizationState($snapshot, false));
                if ($force || $payload !== $this->EncodeVisualizationHTMLJSON($this->visualizationState($previous, false))) {
                    $this->UpdateVisualizationValue($payload);
                }
            }
        } finally {
            IPS_SemaphoreLeave($lock);
        }
    }

    private function storedSnapshot(): array
    {
        $data = json_decode($this->ReadAttributeString('Snapshot'), true);
        return is_array($data['sources'] ?? null) && is_int($data['staleAfter'] ?? null)
            ? $data : ['sources' => [], 'staleAfter' => 900];
    }

    private function accountIDs(): array
    {
        $rows = json_decode($this->ReadPropertyString('Accounts'), true, 8);
        if (!is_array($rows) || !array_is_list($rows)) {
            return [];
        }
        $ids = array_filter(array_column($rows, 'AccountID'), static fn ($id): bool => is_int($id) && $id > 0);
        return array_values(array_unique($ids));
    }

    private function readAccount(int $id, string $key, array $oldObjects, int $now, ?array $selectedDevices): array
    {
        $source = ['id' => $key, 'kind' => 'icloud', 'name' => $this->Translate('iCloud account'), 'available' => false, 'objects' => []];
        if (!$this->isICloudAccount($id)) {
            return $source;
        }
        $source['name'] = IPS_GetName($id);
        if ($selectedDevices !== null) {
            $oldObjects = array_values(array_filter($oldObjects, static fn (array $object): bool => isset($selectedDevices[$object['id']])));
        }
        try {
            $cache = $this->readAccountCache($id);
        } catch (Throwable $exception) {
            $this->DebugFlowEvent(__FUNCTION__, __LINE__, 'FEHLER', 'Der zugewiesene Kontocache konnte nicht gelesen werden.', ['KontoInstanz' => $id, 'Fehlerklasse' => $exception::class]);
            $source['objects'] = $oldObjects;
            return $source;
        }
        if (($cache['state'] ?? '') === 'signedOut' || !is_int($cache['updatedAt'] ?? null) || $cache['updatedAt'] <= 0) {
            return $source;
        }
        $source['available'] = ($cache['success'] ?? false) === true && ($cache['state'] ?? '') === 'authenticated';
        $previous = array_column($oldObjects, 'location', 'id');
        $objects = [];
        foreach ($cache['devices'] as $device) {
            $idValue = $device['id'] ?? null;
            if (!is_string($idValue) || $idValue === '') {
                continue;
            }
            $objectKey = LocationSnapshot::key('icloud', $id, $idValue);
            if (($selectedDevices !== null && !isset($selectedDevices[$objectKey])) || isset($objects[$objectKey])) {
                continue;
            }
            $location = $device['location'] ?? null;
            $fix = LocationSnapshot::fix($location, $now);
            if ($fix !== null && ($location['isInaccurate'] ?? false) === true) {
                $fix['isInaccurate'] = true;
            }
            $name = $device['name'] ?? '';
            $objects[$objectKey] = LocationSnapshot::object(
                $objectKey,
                is_string($name) && $name !== '' ? $name : $this->Translate('Apple device'),
                $fix,
                $previous[$objectKey] ?? null,
                ($location['isOld'] ?? false) !== true
            );
        }
        if (!$source['available']) {
            $objects += array_column($oldObjects, null, 'id');
        }
        $source['objects'] = array_values($objects);
        return $source;
    }

    private function readPositionSource(array $position, string $key, ?array $previous, int $now): array
    {
        $mode = $position['mode'];
        $kind = $position['kind'];
        $name = $position['name'];
        if ($name === '' && IPS_VariableExists($position['trigger'])) {
            $name = IPS_GetName($position['trigger']);
        }
        if ($name === '' || ($kind === 'vehicle' && $name === 'Vehicle')) {
            $name = $this->Translate($kind === 'tracker' ? 'Location device' : 'Vehicle');
        }
        $source = ['id' => $key, 'kind' => $kind, 'name' => $name, 'available' => false, 'objects' => []];
        $ids = $position['ids'];
        $data = null;
        try {
            if ($ids !== [] && count(array_unique($ids)) === count($ids) && array_reduce($ids, static fn ($ok, $id): bool => $ok && IPS_VariableExists($id), true)) {
                $data = $this->readPositionData($position);
                $source['available'] = true;
            }
        } catch (Throwable $exception) {
            $this->DebugFlowEvent(__FUNCTION__, __LINE__, 'FEHLER', 'Die zugewiesenen Positionsvariablen konnten nicht gelesen werden.', ['Fehlerklasse' => $exception::class]);
        }
        $fix = LocationSnapshot::fix($data, $now);
        if ((PositionConverter::supports($mode) || isset(self::VEHICLE_PRESETS[$mode]))
            && $fix !== null && ($previous['timestamp'] ?? null) === $fix['timestamp']) {
            $fix = $previous;
        }
        $source['objects'][] = LocationSnapshot::withMarkerColor(LocationSnapshot::object($key, $name, $fix, $previous, true), $position['markerColor']);
        return $source;
    }

    private function readPositionData(array $position): ?array
    {
        $mode = $position['mode'];
        $ids = $position['ids'];
        if ($mode === 'fields') {
            return $this->readCoordinateVariables($ids);
        }
        if (isset(self::VEHICLE_PRESETS[$mode])) {
            $data = $this->readCoordinateVariables($ids);
            return $data === null ? null : PositionConverter::convertVariables($mode, $data);
        }
        $convert = PositionConverter::supports($mode);
        $value = GetValue($ids[0]);
        if (!is_string($value) || strlen($value) > ($convert ? 1048576 : 4096)) {
            throw new UnexpectedValueException('Invalid JSON position variable.');
        }
        $data = json_decode($value, true, $convert ? 32 : 8, $convert ? JSON_BIGINT_AS_STRING : 0);
        if (!is_array($data)) {
            return null;
        }

        return $convert
            ? PositionConverter::convert($mode, $data, $position['timeFormat'], $position['decimalSeparator'])
            : $data;
    }

    private function readCoordinateVariables(array $ids): ?array
    {
        [$latitude, $longitude, $timestamp] = $ids;
        $start = GetValue($timestamp);
        $lat = GetValue($latitude);
        $lon = GetValue($longitude);
        // The generic writer clears the timestamp first. Provider presets commit by writing it last.
        return $start === GetValue($timestamp) ? ['latitude' => $lat, 'longitude' => $lon, 'timestamp' => $start] : null;
    }

    private function vehicleVariables(): array
    {
        $mode = $this->vehicleMode();
        return match ($mode) {
            'demo'   => [(int) $this->GetIDForIdent('DemoPosition')],
            'fields' => [$this->ReadPropertyInteger('VehicleLatitude'), $this->ReadPropertyInteger('VehicleLongitude'), $this->ReadPropertyInteger('VehicleTimestamp')],
            default  => $this->isJSONVehicleMode($mode) ? [$this->ReadPropertyInteger('VehiclePosition')] : []
        };
    }

    private function legacyVehicle(): array
    {
        $mode = $this->vehicleMode();
        $ids = $this->vehicleVariables();
        $name = trim($this->ReadPropertyString('VehicleName'));
        return [
            'mode'             => $mode,
            'kind'             => $mode === 'demo' ? 'demo' : 'vehicle',
            'name'             => $mode === 'demo' ? $this->Translate('Test vehicle') : ($name !== '' ? $name : 'Vehicle'),
            'ids'              => $ids,
            'trigger'          => $ids[array_key_last($ids)] ?? 0,
            'markerColor'      => $this->ReadPropertyInteger('VehicleMarkerColor'),
            'timeFormat'       => $this->ReadPropertyString('VehicleTimeFormat'),
            'decimalSeparator' => $this->ReadPropertyString('VehicleDecimalSeparator')
        ];
    }

    private function configuredVehicles(): array
    {
        $vehicles = [];
        $legacy = $this->legacyVehicle();
        if ($legacy['mode'] !== 'off') {
            $vehicles[$this->vehicleKey($legacy)] = $legacy;
        }
        $rows = json_decode($this->ReadPropertyString('Vehicles'), true, 8);
        foreach (is_array($rows) && array_is_list($rows) ? $rows : [] as $row) {
            $kind = $row['CarMode'] ?? null;
            $mode = match ($kind) {
                'json'   => $row['CarFormat'] ?? 'json',
                'fields' => $row['CarPreset'] ?? 'fields',
                default  => null
            };
            if (!is_string($mode)
                || ($kind === 'json' && !$this->isJSONVehicleMode($mode))
                || ($kind === 'fields' && $mode !== 'fields' && !isset(self::VEHICLE_PRESETS[$mode]))) {
                continue;
            }
            $ids = $kind === 'json'
                ? [$row['CarPosition'] ?? 0]
                : [$row['CarLatitude'] ?? 0, $row['CarLongitude'] ?? 0, $row['CarTimestamp'] ?? 0];
            if (count(array_filter($ids, static fn ($id): bool => is_int($id) && $id > 0)) !== count($ids) || count(array_unique($ids)) !== count($ids)) {
                continue;
            }
            $vehicle = [
                'mode'             => $mode,
                'kind'             => 'vehicle',
                'name'             => is_string($row['CarName'] ?? null) ? trim($row['CarName']) : '',
                'ids'              => $ids,
                'trigger'          => $ids[array_key_last($ids)],
                'markerColor'      => LocationSnapshot::markerColor($row['CarMarkerColor'] ?? null),
                'timeFormat'       => is_string($row['CarTimeFormat'] ?? null) ? $row['CarTimeFormat'] : 'default',
                'decimalSeparator' => is_string($row['CarDecimalSeparator'] ?? null) ? $row['CarDecimalSeparator'] : '.'
            ];
            $vehicles[$this->vehicleKey($vehicle)] ??= $vehicle;
        }
        return $vehicles;
    }

    private function vehicleKey(array $vehicle): string
    {
        return LocationSnapshot::key('vehicle', $vehicle['trigger'], count($vehicle['ids']) === 3 ? implode(',', $vehicle['ids']) : '');
    }

    private function configuredTrackers(): array
    {
        $trackers = [];
        $rows = json_decode($this->ReadPropertyString('Trackers'), true, 8);
        foreach (is_array($rows) && array_is_list($rows) ? $rows : [] as $row) {
            $id = $row['TrackerPosition'] ?? null;
            $format = $row['TrackerFormat'] ?? 'owntracks';
            if (!is_int($id) || $id <= 0 || !in_array($format, ['owntracks', 'json'], true)) {
                continue;
            }
            $trackers[LocationSnapshot::key('tracker', $id)] ??= [
                'kind'             => 'tracker',
                'mode'             => $format,
                'name'             => is_string($row['TrackerName'] ?? null) ? trim($row['TrackerName']) : '',
                'ids'              => [$id],
                'trigger'          => $id,
                'markerColor'      => LocationSnapshot::markerColor($row['TrackerMarkerColor'] ?? null),
                'timeFormat'       => 'default',
                'decimalSeparator' => '.'
            ];
        }
        return $trackers;
    }

    private function configuredPositions(): array
    {
        return $this->configuredVehicles() + $this->configuredTrackers();
    }

    private function syncReferences(): void
    {
        $positions = $this->configuredPositions();
        $triggers = array_unique(array_column($positions, 'trigger'));
        foreach ($this->GetMessageList() as $sender => $messages) {
            if (!in_array($sender, $triggers, true) && in_array(VM_UPDATE, $messages, true)) {
                $this->UnregisterMessage($sender, VM_UPDATE);
            }
        }
        foreach (array_filter($triggers, static fn (int $id): bool => $id > 0 && IPS_VariableExists($id)) as $trigger) {
            $this->RegisterMessage($trigger, VM_UPDATE);
        }
        $references = array_filter($this->accountIDs(), 'IPS_InstanceExists');
        foreach ($positions as $position) {
            if ($position['kind'] !== 'demo') {
                $references = array_merge($references, array_filter($position['ids'], static fn (int $id): bool => $id > 0 && IPS_VariableExists($id)));
            }
        }
        $references = array_unique($references);
        $registered = $this->GetReferenceList();
        foreach (array_diff($registered, $references) as $id) {
            $this->UnregisterReference($id);
        }
        foreach (array_diff($references, $registered) as $id) {
            $this->RegisterReference($id);
        }
    }

    private function writeDemoPosition(int $step): bool
    {
        [$latitude, $longitude] = self::DEMO_ROUTE[$step];
        return $this->SetValue('DemoPosition', $this->EncodeVisualizationHTMLJSON([
            'latitude' => $latitude, 'longitude' => $longitude, 'timestamp' => time(), 'horizontalAccuracy' => 12.0
        ]));
    }

    private function registerMapHook(): void
    {
        if ($this->GetBuffer('MapHookRegistered') !== '1' && $this->RegisterHook('OpenWoIst' . $this->InstanceID)) {
            $this->SetBuffer('MapHookRegistered', '1');
        }
    }

    private function rendererPath(): string
    {
        return $this->mapHookPath() . '/assets/maplibre-gl.js';
    }

    private function mapHookPath(): string
    {
        return '/hook/OpenWoIst' . $this->InstanceID;
    }

    private function leafletAsset(string $file): string
    {
        $contents = file_get_contents(__DIR__ . '/../libs/vendor/leaflet/' . $file);
        if ($contents === false) {
            throw new RuntimeException('Leaflet asset unavailable.');
        }
        return $contents;
    }
}
