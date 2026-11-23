<?php

declare(strict_types=1);

namespace OpenWoIst;

use Throwable;

trait VehicleSourceForm
{
    private const VEHICLE_PRESETS = [
        'smartcar'     => 'Smartcar',
        'hyundai-kia'  => 'Hyundai Bluelink / Kia Connect',
        'volvo'        => 'Volvo',
        'renault-ze'   => 'Renault Z.E.',
        'tronity'      => 'Tronity'
    ];

    private function vehicleMode(): string
    {
        return $this->resolveVehicleFormat(
            $this->ReadPropertyString('VehicleMode'),
            $this->ReadPropertyString('VehicleJSONFormat')
        );
    }

    private function resolveVehicleFormat(string $mode, string $format): string
    {
        return $this->isJSONVehicleMode($mode) && $this->isJSONVehicleMode($format) ? $format : $mode;
    }

    private function isJSONVehicleMode(string $mode): bool
    {
        return $mode === 'json' || PositionConverter::supports($mode);
    }

    private function vehicleFormVisibility(string $mode): array
    {
        $json = $this->isJSONVehicleMode($mode);
        $fields = $mode === 'fields';
        return [
            'VehicleJSONFormat'  => $json,
            'VehicleAssignments' => $json || $fields,
            'VehiclePosition'    => $json,
            'VehicleLatitude'    => $fields,
            'VehicleLongitude'   => $fields,
            'VehicleTimestamp'   => $fields,
            'VehicleAdvanced'    => PositionConverter::supports($mode),
            'VehicleMarkerColor' => $json || $fields || $mode === 'demo',
            'MoveTestVehicle'    => $mode === 'demo',
            'CheckVehicleSource' => $json || $fields
        ];
    }

    private function configureVehicleForm(array $form): array
    {
        $selectedMode = $this->ReadPropertyString('VehicleMode');
        $format = $this->ReadPropertyString('VehicleJSONFormat');
        foreach ($form['elements'] as &$panel) {
            if (($panel['name'] ?? '') !== 'VehicleSources') {
                continue;
            }
            $items = array_column($panel['items'], null, 'name');
            $legacy = $items['ExistingVehicleSettings']['items'];
            $items['Vehicles'] = $this->additionalVehicleForm($items['Vehicles'], array_column($legacy, null, 'name'));
            $items['ExistingVehicleSettings']['items'] = $this->legacyVehicleOptions($legacy, $selectedMode, $format);
            $panel['items'] = array_values($items);
            break;
        }
        unset($panel);

        foreach ($this->vehicleFormVisibility($this->resolveVehicleFormat($selectedMode, $format)) as $name => $visible) {
            $form['elements'] = $this->SetFormFieldVisibility($form['elements'], [$name], $visible);
            $form['actions'] = $this->SetFormFieldVisibility($form['actions'], [$name], $visible);
        }
        return $form;
    }

    private function legacyVehicleOptions(array $fields, string $mode, string $format): array
    {
        // Show saved provider modes as JSON without rewriting the instance configuration.
        $implicitFormat = $this->isJSONVehicleMode($mode) ? $mode : 'json';
        foreach ($fields as &$field) {
            if ($field['name'] === 'VehicleMode' && $implicitFormat !== 'json') {
                $field['options'] = $this->replaceOptionValue($field['options'], 'json', $implicitFormat);
            }
            if ($field['name'] === 'VehicleJSONFormat' && $format === '') {
                $field['options'] = $this->replaceOptionValue($field['options'], $implicitFormat, '');
            }
        }
        unset($field);
        return $fields;
    }

    private function replaceOptionValue(array $options, string $previous, string $replacement): array
    {
        foreach ($options as &$option) {
            if ($option['value'] === $previous) {
                $option['value'] = $replacement;
            }
        }
        unset($option);
        return $options;
    }

    private function additionalVehicleForm(array $list, array $fields): array
    {
        $modes = array_values(array_filter($fields['VehicleMode']['options'], static fn (array $option): bool => in_array($option['value'], ['json', 'fields'], true)));
        $presets = [['caption' => 'Generic (Unix seconds)', 'value' => 'fields']];
        foreach (self::VEHICLE_PRESETS as $preset => $caption) {
            $presets[] = ['caption' => $caption, 'value' => $preset];
        }
        $markerColor = ['name' => 'CarMarkerColor'] + array_diff_key($fields['VehicleMarkerColor'], ['name' => true, 'visible' => true]);
        $list['columns'] = [
            ['name' => 'CarName', 'caption' => 'Vehicle name', 'width' => 'auto', 'add' => '', 'save' => true],
            ['name' => 'CarMode', 'caption' => 'Vehicle source', 'width' => '200px', 'add' => 'json', 'edit' => ['type' => 'Select', 'options' => $modes]],
            ['name' => 'CarMarkerColor', 'caption' => 'Marker color', 'width' => '140px', 'add' => -1, 'edit' => array_diff_key($markerColor, ['name' => true, 'caption' => true])]
        ];
        foreach (['CarFormat' => 'json', 'CarPosition' => 0, 'CarPreset' => 'fields', 'CarLatitude' => 0, 'CarLongitude' => 0, 'CarTimestamp' => 0,
            'CarTimeFormat'   => 'default', 'CarDecimalSeparator' => '.'] as $name => $default) {
            $list['columns'][] = ['name' => $name, 'visible' => false, 'add' => $default, 'save' => true];
        }

        $inputs = [];
        foreach ([$fields['VehicleJSONFormat'], ...$fields['VehicleAssignments']['items'], ...$fields['VehicleAdvanced']['items']] as $field) {
            $name = $field['name'] === 'VehicleJSONFormat' ? 'CarFormat' : 'Car' . substr($field['name'], strlen('Vehicle'));
            $inputs[$name] = ['name' => $name] + array_diff_key($field, array_flip(['name', 'visible', 'onChange']));
        }
        $inputs['CarName']['validate'] = '^.*\S.*$';
        $json = [$inputs['CarFormat'], $inputs['CarPosition'], [
            'type'    => 'ExpansionPanel',
            'caption' => 'Advanced vehicle settings',
            'items'   => [$inputs['CarTimeFormat'], $inputs['CarDecimalSeparator']]
        ]];
        $coordinates = [
            ['type' => 'Select', 'name' => 'CarPreset', 'caption' => 'Position preset', 'options' => $presets],
            $inputs['CarLatitude'],
            $inputs['CarLongitude'],
            array_replace($inputs['CarTimestamp'], ['caption' => 'Measurement time', 'validVariableTypes' => [VARIABLETYPE_INTEGER, VARIABLETYPE_STRING]]),
            ['type' => 'Label', 'caption' => 'Select the original measurement time in Unix seconds. Hyundai/Kia provides it as text.']
        ];
        $list['form'] = [$inputs['CarName'], ['type' => 'Select', 'name' => 'CarMode', 'caption' => 'Vehicle source', 'options' => $modes]];
        $positionInputs = ['json' => $json, 'fields' => $coordinates];
        if (version_compare(IPS_GetKernelVersion(), '9.1', '>=')) {
            foreach ($modes as $index => $option) {
                $list['form'][1]['options'][$index]['items'] = $positionInputs[$option['value']];
            }
        } else {
            foreach ($modes as $option) {
                $list['form'][] = ['type' => 'ExpansionPanel', 'caption' => $option['caption'], 'expanded' => $option['value'] === 'json', 'items' => $positionInputs[$option['value']]];
            }
        }
        $list['form'][] = $markerColor;
        return $list;
    }

    private function handleVehicleFormAction(string $ident, mixed $value): bool
    {
        if ($ident === 'CheckVehicleSource') {
            echo $this->checkVehicleSource();
            return true;
        }
        if ($ident !== 'UpdateVehicleForm') {
            return false;
        }
        $selection = is_string($value) ? json_decode($value, true, 4) : null;
        if (is_string($selection[0] ?? null) && is_string($selection[1] ?? null)) {
            $mode = $this->resolveVehicleFormat($selection[0], $selection[1]);
            foreach ($this->vehicleFormVisibility($mode) as $name => $visible) {
                $this->UpdateFormField($name, 'visible', $visible);
            }
        }
        return true;
    }

    private function checkVehicleSource(): string
    {
        $mode = $this->vehicleMode();
        if ($mode !== 'fields' && !$this->isJSONVehicleMode($mode)) {
            return $this->Translate('Select and apply a vehicle source first.');
        }
        $ids = $this->vehicleVariables();
        $labels = $mode === 'fields' ? ['Latitude', 'Longitude', 'Position time (Unix seconds)'] : ['JSON position variable'];
        foreach ($ids as $index => $id) {
            if ($id <= 0 || !IPS_VariableExists($id)) {
                return sprintf($this->Translate('Missing variable for %s.'), $this->Translate($labels[$index]));
            }
        }
        if (count(array_unique($ids)) !== count($ids)) {
            return $this->Translate('Select a separate variable for each position field.');
        }
        try {
            $data = $this->readPositionData($this->legacyVehicle());
        } catch (Throwable) {
            $data = null;
        }
        $now = time();
        $field = LocationSnapshot::invalidField($data, $now);
        return match ($field) {
            'latitude'  => $this->Translate('Latitude is invalid or missing.'),
            'longitude' => $this->Translate('Longitude is invalid or missing.'),
            'timestamp' => $this->Translate('Measurement time is invalid, missing or inconsistent.'),
            'position'  => $this->Translate($mode === 'fields' ? 'No complete position is available.' : 'The JSON position is unreadable or does not match the selected format.'),
            default     => sprintf($this->Translate('Valid position. Measurement age %d s.'), max(0, $now - $data['timestamp']))
        };
    }
}
