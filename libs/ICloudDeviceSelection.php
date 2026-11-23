<?php

declare(strict_types=1);

namespace OpenWoIst;

use Throwable;
use UnexpectedValueException;

trait ICloudDeviceSelection
{
    private function isICloudAccount(int $id): bool
    {
        return IPS_InstanceExists($id) && (IPS_GetInstance($id)['ModuleInfo']['ModuleID'] ?? '') === self::ACCOUNT_MODULE;
    }

    private function readAccountCache(int $id): array
    {
        $cache = json_decode(@ICAC_GetDevices($id), true, 64, JSON_THROW_ON_ERROR);
        if (!is_array($cache['devices'] ?? null) || !array_is_list($cache['devices'])) {
            throw new UnexpectedValueException('Invalid cache structure.');
        }
        return $cache;
    }

    private function deviceSettings(): array
    {
        $rows = json_decode($this->ReadPropertyString('SelectedDevices'), true, 8);
        $settings = [];
        foreach (is_array($rows) && array_is_list($rows) ? $rows : [] as $row) {
            $key = $row['DeviceKey'] ?? null;
            if (is_string($key) && preg_match('/\A[a-f0-9]{64}\z/', $key) === 1) {
                // Rows saved with the original selection list were included by definition.
                $settings[$key] = [
                    'Included'    => ($row['Included'] ?? true) === true,
                    'MarkerColor' => LocationSnapshot::markerColor($row['MarkerColor'] ?? null) ?? -1
                ];
            }
        }
        return $settings;
    }

    /** A null selection includes all devices. An empty explicit selection includes none. */
    private function selectedDeviceKeys(?array $settings = null): ?array
    {
        if ($this->ReadPropertyBoolean('ShowAllDevices')) {
            return null;
        }
        $keys = array_fill_keys(array_keys(array_filter($settings ?? $this->deviceSettings(), static fn (array $device): bool => $device['Included'])), true);
        ksort($keys, SORT_STRING);
        return $keys;
    }

    private function configureDeviceSelection(array $form): array
    {
        $settings = $this->deviceSettings();
        $rows = [];
        foreach ($this->accountIDs() as $id) {
            if (!$this->isICloudAccount($id)) {
                continue;
            }
            try {
                $cache = $this->readAccountCache($id);
            } catch (Throwable) {
                continue;
            }
            if (($cache['state'] ?? '') === 'signedOut') {
                continue;
            }
            $account = IPS_GetName($id);
            foreach ($cache['devices'] as $device) {
                $deviceID = $device['id'] ?? null;
                if (!is_string($deviceID) || $deviceID === '') {
                    continue;
                }
                $key = LocationSnapshot::key('icloud', $id, $deviceID);
                $name = is_string($device['name'] ?? null) ? trim($device['name']) : '';
                $model = is_string($device['model'] ?? null) ? trim($device['model']) : '';
                $caption = ($name !== '' ? $name : $this->Translate('Apple device')) . ($model !== '' ? ' (' . $model . ')' : '');
                $rows[$key] = ['DeviceKey' => $key, 'DeviceLabel' => $caption . ' — ' . $account]
                    + ($settings[$key] ?? ['Included' => false, 'MarkerColor' => -1]);
            }
        }
        foreach ($settings as $key => $device) {
            if ($device['Included'] || $device['MarkerColor'] !== -1) {
                $rows[$key] ??= ['DeviceKey' => $key, 'DeviceLabel' => $this->Translate('Unavailable Apple device')] + $device;
            }
        }
        usort($rows, static fn (array $left, array $right): int => strnatcasecmp($left['DeviceLabel'], $right['DeviceLabel']));

        foreach ($form['elements'] as &$element) {
            if (($element['name'] ?? '') !== 'ICloudSources') {
                continue;
            }
            foreach ($element['items'] as &$field) {
                if (($field['name'] ?? '') === 'SelectedDevices') {
                    $field['values'] = $rows;
                }
            }
            unset($field);
        }
        unset($element);
        return $form;
    }
}
