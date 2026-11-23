<?php

declare(strict_types=1);

namespace OpenWoIst;

use Throwable;

trait VehicleSourceForm
{
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
            'MoveTestVehicle'    => $mode === 'demo',
            'CheckVehicleSource' => $json || $fields
        ];
    }

    private function configureVehicleForm(array $form): array
    {
        $selectedMode = $this->ReadPropertyString('VehicleMode');
        $format = $this->ReadPropertyString('VehicleJSONFormat');
        $implicitFormat = $this->isJSONVehicleMode($selectedMode) ? $selectedMode : 'json';
        foreach ($form['elements'] as &$field) {
            // Show saved provider modes as JSON without rewriting the instance configuration.
            if (($field['name'] ?? '') === 'VehicleMode' && $implicitFormat !== 'json') {
                foreach ($field['options'] as &$option) {
                    if ($option['value'] === 'json') {
                        $option['value'] = $implicitFormat;
                    }
                }
                unset($option);
            }
            if (($field['name'] ?? '') === 'VehicleJSONFormat' && $format === '') {
                foreach ($field['options'] as &$option) {
                    if ($option['value'] === $implicitFormat) {
                        $option['value'] = '';
                    }
                }
                unset($option);
            }
        }
        unset($field);

        foreach ($this->vehicleFormVisibility($this->resolveVehicleFormat($selectedMode, $format)) as $name => $visible) {
            $form['elements'] = $this->SetFormFieldVisibility($form['elements'], [$name], $visible);
            $form['actions'] = $this->SetFormFieldVisibility($form['actions'], [$name], $visible);
        }
        return $form;
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
            $data = $this->readVehicleData($mode, $ids);
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
