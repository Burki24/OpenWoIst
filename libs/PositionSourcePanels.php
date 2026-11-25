<?php

declare(strict_types=1);

namespace OpenWoIst;

trait PositionSourcePanels
{
    private const GEOFENCY_RECEIVER_MODULE = '{0100CA50-D2DA-11E4-8830-0800200C9A66}';
    private const GEOFENCY_DEVICE_MODULE = '{485D0419-BE97-4548-AA9C-C083EB82E61E}';

    private const POSITION_PANELS = [
        'bmw-cardata'   => ['BMWCarDataVehicles', 'BMW CarData', [], []],
        'ips-tesla'     => ['IPSTeslaVehicles', 'IPS-Tesla', ['{83675CDD-3E50-FB21-8AE6-B4FC718A77B6}'], ['latitude', 'longitude', 'gps_as_of']],
        'tesla-fleet'   => ['TeslaFleetVehicles', 'Tesla Fleet Telemetry', [], []],
        'nrg-tessie'    => ['NRGTessieVehicles', 'Tessie', ['{3F1F7E31-8BA0-4B8F-9B62-47DAD7A0B6C9}'], ['stat_tel_Location_lat', 'stat_tel_Location_lon']],
        'tessie'        => ['TessieVehicles', 'Tessie', [], []],
        'fiat-uconnect' => ['FiatUconnectVehicles', 'Fiat Uconnect', ['{A0D064E8-44E3-206A-9B5E-87F12B666EA7}'], ['CurrentLatitude', 'CurrentLongitude', 'LastPositionUpdate']],
        'smartcar'      => ['SmartcarVehicles', 'Smartcar', ['{1E1B7C9A-2D4F-4E8A-9C3B-7F6D5A4E2B10}'], ['Latitude', 'Longitude', 'Sig_location_preciselocation_OEMUpdatedAt']],
        'hyundai-kia'   => ['HyundaiKiaVehicles', 'Hyundai Bluelink / Kia Connect', ['{C3D4E5F6-789A-BCDE-F012-3456789ABCDE}'], ['Latitude', 'Longitude', 'PositionTimestamp']],
        'volvo'         => ['VolvoVehicles', 'Volvo', ['{6C6B7979-37AA-69B7-2E19-7E10D92A97E3}'], ['CurrentLatitude', 'CurrentLongitude', 'LastPositionMessage']],
        'renault-ze'    => ['RenaultZEVehicles', 'Renault Z.E.', ['{C7AB2E48-7D3A-94B8-E4D6-137CFB91D821}'], ['GPSLatitude', 'GPSLongitude', 'GPSUpdate']],
        'tronity'       => ['TronityVehicles', 'Tronity', ['{D041B19E-6D70-A84F-B67C-4FF51CA38D3A}'], ['Latitude', 'Longitude', 'Timestamp']],
        'owntracks'     => ['OwnTracksDevices', 'OwnTracks', ['{8398750C-51CE-51C8-97A9-9356BB11ABDD}'], ['position']],
        'geofency'      => ['GeofencyDevices', 'Geofency', [self::GEOFENCY_DEVICE_MODULE], ['CurrentLatitude', 'CurrentLongitude', 'Timestamp']],
        'traccar'       => ['TraccarDevices', 'Traccar', [], []],
        'fressnapf'     => ['FressnapfTrackers', 'Fressnapf Tracker', ['{AF92FA56-B339-6D82-B015-DF819C042C29}'], ['Latitude', 'Longitude', 'SampledAt']]
    ];

    private const INSTANCE_ONLY_POSITION_MODES = ['fiat-uconnect', 'fressnapf', 'geofency', 'ips-tesla', 'nrg-tessie'];
    private const TRACKER_POSITION_MODES = ['owntracks', 'geofency', 'fressnapf'];

    private const SOURCE_MODULE_URLS = [
        'bmw-cardata'   => 'https://github.com/parcivad/ips-bmw-cardata',
        'ips-tesla'     => 'https://github.com/Schnittcher/IPS-Tesla',
        'nrg-tessie'    => 'https://github.com/DG65/NRGTessie',
        'tessie'        => 'https://developer.tessie.com/reference/get-vehicle',
        'fiat-uconnect' => 'https://github.com/demel42/IPSymconFiatUconnect',
        'smartcar'      => 'https://www.symcon.de/de/module-store/smartcar-symcon/',
        'hyundai-kia'   => 'https://github.com/da8ter/Bluelink',
        'volvo'         => 'https://github.com/demel42/IPSymconVolvo',
        'renault-ze'    => 'https://www.symcon.de/de/module-store/net-lorbetzki-zoe/',
        'tronity'       => 'https://github.com/symcon/Tronity',
        'owntracks'     => 'https://www.symcon.de/de/module-store/silberstreifen-owntracks/',
        'geofency'      => 'https://github.com/symcon/SymconMisc/tree/master/Geofency',
        'traccar'       => 'https://github.com/traccar/traccar',
        'icloud'        => 'https://github.com/Burki24/SymconiCloud',
        'tractive'      => 'https://www.symcon.de/de/module-store/demel41-tractivegps/',
        'fressnapf'     => 'https://www.symcon.de/de/module-store/bgersmann-fressnapftracker/'
    ];

    private function configurePositionPanels(array $form): array
    {
        $elements = array_column($form['elements'], null, 'name');
        $vehicleFields = array_column(array_column($elements['VehicleSources']['items'], null, 'name')['ExistingVehicleSettings']['items'], null, 'name');
        $vehicleModules = [];
        $deviceModules = [];
        $petModules = [];
        foreach (self::POSITION_PANELS as $mode => [$property, $caption, $modules]) {
            if ($mode === 'tesla-fleet') {
                // Keep reading saved Fleet Telemetry assignments without offering new ones.
                continue;
            }
            $list = $this->positionPanelList($mode, $property, $modules, $vehicleFields);
            $hint = match (true) {
                $mode === 'ips-tesla'     => 'Enable vehicle location in the Tesla Vehicle Connector, then select the Tesla Vehicle Drive instance.',
                $mode === 'nrg-tessie'    => 'Select the Tessie vehicle instance. The Location telemetry data point must be enabled in Tessie.',
                $mode === 'fiat-uconnect' => 'Select the Fiat Uconnect vehicle instance. Its position appears after the next complete position report.',
                $mode === 'geofency'      => 'Enable Send current position in the Geofency webhook settings. Devices become selectable after the first event.',
                $modules === []           => sprintf($this->Translate('Select a Symcon string variable containing %s position JSON.'), $caption),
                default                   => 'Select an existing Symcon device instance.'
            };
            $panel = ['type' => 'ExpansionPanel', 'name' => $property . 'Section', 'caption' => $caption, 'items' => [
                $this->sourceModuleLink($mode, $caption), ['type' => 'Label', 'caption' => $hint], $list
            ]];
            if (in_array($mode, ['owntracks', 'geofency', 'traccar'], true)) {
                $deviceModules[] = $panel;
            } elseif ($mode === 'fressnapf') {
                $petModules[] = $panel;
            } else {
                $vehicleModules[$mode] = $panel;
            }
        }

        $vehicleModules['tessie']['caption'] = 'Tessie JSON';
        $vehicleModules['nrg-tessie']['items'][] = $vehicleModules['tessie'];
        unset($vehicleModules['tessie']);
        $vehicles = $elements['VehicleSources'];
        $vehicles['items'] = [...array_values($vehicleModules), ...$vehicles['items']];
        $iCloud = $elements['ICloudSources'];
        array_unshift($iCloud['items'], $this->sourceModuleLink('icloud', 'SymconiCloud'));
        $tractive = $elements['TractiveSources'];
        array_unshift($tractive['items'], $this->sourceModuleLink('tractive', 'Tractive GPS'));
        $mapShare = $elements['GarminMapShareSources'];
        $mapShare['items'][1]['form'][1]['validate'] = GarminMapShareClient::LINK_PATTERN;
        $clouds = [
            'type'    => 'ExpansionPanel',
            'name'    => 'CloudSources',
            'caption' => 'Apple and Google',
            'items'   => [$iCloud, $elements['GoogleCloudSources']]
        ];
        $devices = [
            'type'    => 'ExpansionPanel',
            'name'    => 'LocationDeviceSources',
            'caption' => 'Trackers and location devices',
            'items'   => [...$deviceModules, $this->garminLiveTrackPanel($elements['GarminLiveTrackSources']), $mapShare, $this->pajPanel($elements['PajSources']), $elements['TrackerSources']]
        ];
        $pets = ['type' => 'ExpansionPanel', 'name' => 'PetSources', 'caption' => 'Pets', 'items' => [$tractive, ...$petModules]];
        unset($elements['VehicleSources'], $elements['ICloudSources'], $elements['GoogleCloudSources'], $elements['TractiveSources'], $elements['PajSources'], $elements['GarminLiveTrackSources'], $elements['GarminMapShareSources'], $elements['TrackerSources']);
        $form['elements'] = [$vehicles, $clouds, $pets, $devices, ...array_values($elements)];
        return $form;
    }

    private function sourceModuleLink(string $mode, string $caption): array
    {
        $url = self::SOURCE_MODULE_URLS[$mode];
        $format = match (true) {
            str_starts_with($url, 'https://developer.')  => '%s API documentation',
            str_starts_with($url, 'https://github.com/') => '%s on GitHub',
            default                                      => '%s in the Module Store'
        };
        return [
            'type'    => 'Button',
            'caption' => sprintf($this->Translate($format), $caption),
            'link'    => true,
            'onClick' => 'echo ' . var_export($url, true) . ';'
        ];
    }

    private function positionPanelList(string $mode, string $property, array $modules, array $vehicleFields): array
    {
        $isJSON = PositionConverter::supports($mode);
        $values = $this->positionPanelValues($mode, $property, $modules);
        $add = true;
        $nameCaption = $mode === 'fressnapf' ? 'Pet name' : 'Name on the map';
        $name = ['type' => 'ValidationTextBox', 'name' => 'SourceName', 'caption' => $nameCaption];
        $color = array_diff_key($vehicleFields['VehicleMarkerColor'], ['visible' => true]);
        $color['name'] = 'SourceMarkerColor';
        $columns = [
            ['name' => 'SourceName', 'caption' => $nameCaption, 'width' => 'auto', 'add' => '', 'save' => true]
        ];
        $dialog = [$name];
        if ($modules !== []) {
            $selector = ['type' => 'SelectInstance', 'validModules' => $modules];
            if ($mode === 'geofency') {
                [$add, $options] = $this->geofencyDeviceOptions($values);
                $selector = ['type' => 'Select', 'options' => $options];
            }
            $columns[] = ['name' => 'SourceInstance', 'caption' => 'Source instance', 'width' => '260px', 'add' => 0, 'edit' => $selector];
            $dialog[] = $selector + ['name' => 'SourceInstance', 'caption' => 'Source instance'];
            $dialog[] = ['type' => 'Label', 'caption' => 'OpenWoIst locates the position variables of the selected instance automatically.'];
        }

        $variables = [];
        $assignments = array_column($vehicleFields['VehicleAssignments']['items'], null, 'name');
        $variableFields = in_array($mode, self::INSTANCE_ONLY_POSITION_MODES, true) ? [] : ($isJSON ? ['Position'] : ['Latitude', 'Longitude', 'Timestamp']);
        foreach ($variableFields as $field) {
            $input = $assignments['Vehicle' . $field];
            $input['name'] = 'Source' . $field;
            unset($input['visible']);
            if ($field === 'Timestamp' && $mode === 'hyundai-kia') {
                $input['validVariableTypes'] = [VARIABLETYPE_STRING];
            }
            $variables[] = $input;
            $column = ['name' => $input['name'], 'add' => 0, 'save' => true];
            $columns[] = $isJSON && $modules === []
                ? $column + ['caption' => $input['caption'], 'width' => '280px', 'edit' => ['type' => 'SelectVariable', 'validVariableTypes' => [VARIABLETYPE_STRING]]]
                : $column + ['visible' => false];
        }
        if ($modules === []) {
            $dialog = [...$dialog, ...$variables];
        } elseif ($variables !== []) {
            $dialog[] = ['type' => 'ExpansionPanel', 'caption' => 'Manual variable assignment', 'items' => [
                ['type' => 'Label', 'caption' => 'Leave the source instance empty to use the variables selected here.'],
                ...$variables
            ]];
        }

        if ($isJSON && $mode !== 'tessie') {
            $advanced = $vehicleFields['VehicleAdvanced'];
            foreach ($advanced['items'] as &$input) {
                $input['name'] = 'Source' . substr($input['name'], strlen('Vehicle'));
                $columns[] = ['name' => $input['name'], 'visible' => false, 'add' => $input['options'][0]['value'], 'save' => true];
            }
            unset($input);
            $dialog[] = ['type' => 'ExpansionPanel', 'caption' => 'Advanced position settings', 'items' => $advanced['items']];
        }
        $columns[] = ['name' => 'SourceMarkerColor', 'caption' => 'Marker color', 'width' => '140px', 'add' => -1, 'edit' => array_diff_key($color, ['name' => true, 'caption' => true])];
        foreach (['SourceKind' => '', 'LegacySingle' => false] as $field => $default) {
            $columns[] = ['name' => $field, 'visible' => false, 'add' => $default, 'save' => true];
        }
        $dialog[] = $color;
        return [
            'type'                        => 'List',
            'name'                        => $property,
            'caption'                     => 'Configured devices',
            'rowCount'                    => 4,
            'add'                         => $add,
            'delete'                      => true,
            'loadValuesFromConfiguration' => false,
            'values'                      => $values,
            'columns'                     => $columns,
            'form'                        => $dialog
        ];
    }

    private function positionPanelValues(string $mode, string $property, array $modules): array
    {
        $values = [];
        foreach ($this->positionRows($this->ReadPropertyString($property)) ?? [] as $row) {
            if (!is_array($row)) {
                continue;
            }
            if (!is_string($row['SourceName'] ?? null) || trim($row['SourceName']) === '') {
                $instance = $row['SourceInstance'] ?? 0;
                if (is_int($instance) && $this->isPositionPanelInstance($instance, $modules)) {
                    $row['SourceName'] = $this->positionPanelInstanceName($mode, $instance);
                } else {
                    $variable = $row['SourcePosition'] ?? $row['SourceTimestamp'] ?? 0;
                    $row['SourceName'] = is_int($variable) && $variable > 0 && IPS_VariableExists($variable) ? IPS_GetName($variable) : '';
                }
            }
            $values[] = $row;
        }
        return $values;
    }

    private function isPositionPanelInstance(int $id, array $modules): bool
    {
        if ($modules === [] || $id <= 0 || !IPS_InstanceExists($id) || !in_array(IPS_GetInstance($id)['ModuleInfo']['ModuleID'], $modules, true)) {
            return false;
        }
        return $modules !== [self::GEOFENCY_DEVICE_MODULE] || $this->isPositionPanelInstance(IPS_GetParent($id), [self::GEOFENCY_RECEIVER_MODULE]);
    }

    private function geofencyDeviceOptions(array $configured): array
    {
        $options = [0 => $this->Translate('Select a Geofency device')];
        foreach (IPS_GetInstanceListByModuleID(self::GEOFENCY_RECEIVER_MODULE) as $receiver) {
            foreach (IPS_GetChildrenIDs($receiver) as $id) {
                if ($this->isPositionPanelInstance($id, [self::GEOFENCY_DEVICE_MODULE])) {
                    $options[$id] = IPS_GetName($id) . ' (#' . $id . ')';
                }
            }
        }
        $add = count($options) > 1;
        foreach ($configured as $row) {
            $id = $row['SourceInstance'] ?? 0;
            if (is_int($id) && $id > 0) {
                $options[$id] ??= sprintf($this->Translate('Unavailable device (#%d)'), $id);
            }
        }
        return [$add, array_map(static fn (int $id, string $name): array => ['caption' => $name, 'value' => $id], array_keys($options), $options)];
    }

    private function positionPanelInstanceName(string $mode, int $id): string
    {
        if ($mode === 'fressnapf') {
            $variable = (int) @IPS_GetObjectIDByIdent('Name', $id);
            if ($variable > 0 && IPS_VariableExists($variable) && IPS_GetVariable($variable)['VariableType'] === VARIABLETYPE_STRING) {
                $name = trim(GetValueString($variable));
                if ($name !== '') {
                    return $name;
                }
            }
        }
        return IPS_GetName($id);
    }

    private function positionPanelSources(string $kind): array
    {
        $positions = [];
        foreach (self::POSITION_PANELS as $mode => [$property, $caption, $modules, $idents]) {
            if ($kind === 'tracker' && !in_array($mode, self::TRACKER_POSITION_MODES, true)) {
                continue;
            }
            foreach ($this->positionRows($this->ReadPropertyString($property)) ?? [] as $row) {
                $rowKind = in_array($mode, self::TRACKER_POSITION_MODES, true) && !($mode === 'owntracks' && ($row['SourceKind'] ?? '') === 'vehicle') ? 'tracker' : 'vehicle';
                if ($rowKind !== $kind) {
                    continue;
                }
                $instance = $row['SourceInstance'] ?? 0;
                $instance = $modules !== [] && is_int($instance) && $instance > 0 ? $instance : 0;
                $known = $this->isPositionPanelInstance($instance, $modules);
                $ids = $this->positionPanelVariableIDs($mode, $row, $instance, $known, $idents);
                if ($instance === 0 && $ids === []) {
                    continue;
                }
                $name = is_string($row['SourceName'] ?? null) ? trim($row['SourceName']) : '';
                $triggerField = match ($mode) {
                    'fressnapf' => 2,
                    'geofency'  => 1,
                    default     => array_key_last($ids)
                };
                $position = [
                    'mode'             => $mode,
                    'kind'             => $kind,
                    'name'             => $name !== '' ? $name : ($known ? $this->positionPanelInstanceName($mode, $instance) : ($instance > 0 ? $this->Translate($caption) : '')),
                    'ids'              => $ids,
                    'trigger'          => $ids[$triggerField] ?? 0,
                    'markerColor'      => LocationSnapshot::markerColor($row['SourceMarkerColor'] ?? null),
                    'timeFormat'       => is_string($row['SourceTimeFormat'] ?? null) ? $row['SourceTimeFormat'] : 'default',
                    'decimalSeparator' => is_string($row['SourceDecimalSeparator'] ?? null) ? $row['SourceDecimalSeparator'] : '.'
                ];
                if ($instance > 0) {
                    $position['sourceInstance'] = $instance;
                    $position['instanceAvailable'] = $known;
                    $key = LocationSnapshot::key($mode, $instance);
                } else {
                    $key = $kind === 'vehicle' ? $this->vehicleKey($position) : LocationSnapshot::key('tracker', $ids[0]);
                }
                if (($row['LegacySingle'] ?? false) === true && $kind === 'vehicle' && $instance === 0) {
                    $position['legacySingle'] = true;
                }
                $positions[$key] ??= $position;
            }
        }
        return $positions;
    }

    private function positionPanelVariableIDs(string $mode, array $row, int $instance, bool $known, array $idents): array
    {
        $isJSON = PositionConverter::supports($mode);
        if ($instance === 0) {
            if (in_array($mode, self::INSTANCE_ONLY_POSITION_MODES, true)) {
                return [];
            }
            $keys = $isJSON ? ['SourcePosition'] : ['SourceLatitude', 'SourceLongitude', 'SourceTimestamp'];
            $ids = array_map(static fn (string $name): mixed => $row[$name] ?? 0, $keys);
            return count(array_filter($ids, static fn (mixed $id): bool => is_int($id) && $id > 0)) === count($ids)
                && count(array_unique($ids, SORT_REGULAR)) === count($ids) ? $ids : [];
        }
        if (!$known) {
            return [];
        }
        $types = $isJSON ? [VARIABLETYPE_STRING] : [VARIABLETYPE_FLOAT, VARIABLETYPE_FLOAT, in_array($mode, ['hyundai-kia', 'fressnapf'], true) ? VARIABLETYPE_STRING : VARIABLETYPE_INTEGER];
        if ($mode === 'ips-tesla') {
            $connector = IPS_GetInstance($instance)['ConnectionID'];
            if (!$this->isPositionPanelInstance($connector, ['{72887112-E270-FFC1-04AC-817D82F42027}'])
                || (json_decode(IPS_GetConfiguration($connector), true)['IncludeLocationData'] ?? false) !== true) {
                return [];
            }
            $types = [VARIABLETYPE_STRING, VARIABLETYPE_STRING, VARIABLETYPE_STRING];
        }
        $ids = [];
        foreach ($idents as $index => $ident) {
            $id = (int) @IPS_GetObjectIDByIdent($ident, $instance);
            if ($id <= 0 || !IPS_VariableExists($id) || IPS_GetVariable($id)['VariableType'] !== $types[$index]) {
                return [];
            }
            $ids[] = $id;
        }
        if ($mode === 'fressnapf') {
            $accuracy = (int) @IPS_GetObjectIDByIdent('Accuracy', $instance);
            if ($accuracy > 0 && IPS_VariableExists($accuracy) && IPS_GetVariable($accuracy)['VariableType'] === VARIABLETYPE_INTEGER) {
                $ids[] = $accuracy;
            }
        }
        return $ids;
    }

    private function migratePositionPanels(string $json): string
    {
        $data = json_decode($json);
        $configuration = $data->configuration ?? null;
        if (!$configuration instanceof \stdClass) {
            return '';
        }
        $changed = false;
        foreach (['Vehicles', 'Trackers'] as $property) {
            $rows = $this->positionRows($configuration->{$property} ?? null);
            $remaining = [];
            foreach ($rows ?? [] as $row) {
                $mode = $property === 'Vehicles'
                    ? match ($row['CarMode'] ?? null) {
                        'json'   => in_array($row['CarFormat'] ?? null, ['bmw-cardata', 'owntracks', 'traccar'], true) ? $row['CarFormat'] : null,
                        'fields' => is_string($row['CarPreset'] ?? null) && isset(self::VEHICLE_PRESETS[$row['CarPreset']]) ? $row['CarPreset'] : null,
                        default  => null
                    }
                : (($row['TrackerFormat'] ?? 'owntracks') === 'owntracks' ? 'owntracks' : null);
                if (!is_string($mode) || !isset(self::POSITION_PANELS[$mode]) || !is_array($row)) {
                    $remaining[] = $row;
                    continue;
                }
                $converted = $property === 'Vehicles' ? $this->vehiclePanelRow($row) : [
                    'SourceName'        => $row['TrackerName'] ?? '',
                    'SourcePosition'    => $row['TrackerPosition'] ?? 0,
                    'SourceMarkerColor' => $row['TrackerMarkerColor'] ?? -1
                ];
                if (!$this->appendPositionPanelRow($configuration, $mode, $converted)) {
                    $remaining[] = $row;
                }
            }
            if ($rows !== null && $remaining !== $rows) {
                $configuration->{$property} = json_encode($remaining, JSON_THROW_ON_ERROR);
                $changed = true;
            }
        }

        $mode = $configuration->VehicleMode ?? 'off';
        $format = $configuration->VehicleJSONFormat ?? '';
        if (is_string($mode) && is_string($format)) {
            $mode = $this->resolveVehicleFormat($mode, $format);
            if (PositionConverter::supports($mode) && is_int($configuration->VehiclePosition ?? null) && $configuration->VehiclePosition > 0) {
                $single = ['LegacySingle' => true];
                foreach (['Name', 'Position', 'MarkerColor', 'TimeFormat', 'DecimalSeparator'] as $suffix) {
                    $original = 'Vehicle' . $suffix;
                    if (isset($configuration->{$original})) {
                        $single['Car' . $suffix] = $configuration->{$original};
                    }
                }
                if (!is_string($single['CarName'] ?? null) || trim($single['CarName']) === '') {
                    $single['CarName'] = 'Vehicle';
                }
                if ($this->appendPositionPanelRow($configuration, $mode, $this->vehiclePanelRow($single), true)) {
                    $configuration->VehicleMode = 'off';
                    $changed = true;
                }
            }
        }
        return $changed ? json_encode($data, JSON_THROW_ON_ERROR) : '';
    }

    private function vehiclePanelRow(array $row): array
    {
        $converted = ['SourceKind' => 'vehicle'];
        foreach (['Name', 'Position', 'Latitude', 'Longitude', 'Timestamp', 'TimeFormat', 'DecimalSeparator', 'MarkerColor'] as $suffix) {
            $old = 'Car' . $suffix;
            if (array_key_exists($old, $row)) {
                $converted['Source' . $suffix] = $row[$old];
            }
        }
        if (($row['LegacySingle'] ?? false) === true) {
            $converted['LegacySingle'] = true;
        }
        return $converted;
    }

    private function appendPositionPanelRow(\stdClass $configuration, string $mode, array $row, bool $first = false): bool
    {
        $property = self::POSITION_PANELS[$mode][0];
        $rows = $this->positionRows($configuration->{$property} ?? '[]');
        if ($rows === null) {
            return false;
        }
        if (!in_array($row, $rows, true)) {
            $configuration->{$property} = json_encode($first ? [$row, ...$rows] : [...$rows, $row], JSON_THROW_ON_ERROR);
        }
        return true;
    }

    /** Reads a list without treating malformed persisted data as an empty assignment. */
    private function positionRows(mixed $value): ?array
    {
        $rows = is_string($value) ? json_decode($value, true, 16) : null;
        return is_array($rows) && array_is_list($rows) ? $rows : null;
    }
}
