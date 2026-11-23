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

    /** A null selection includes all devices. An empty explicit selection includes none. */
    private function selectedDeviceKeys(bool $includeAllMode = false): ?array
    {
        if (!$includeAllMode && $this->ReadPropertyBoolean('ShowAllDevices')) {
            return null;
        }
        $rows = json_decode($this->ReadPropertyString('SelectedDevices'), true, 8);
        $keys = [];
        foreach (is_array($rows) && array_is_list($rows) ? $rows : [] as $row) {
            $key = $row['DeviceKey'] ?? null;
            if (is_string($key) && preg_match('/\A[a-f0-9]{64}\z/', $key) === 1) {
                $keys[$key] = true;
            }
        }
        ksort($keys, SORT_STRING);
        return $keys;
    }

    private function configureDeviceSelection(array $form): array
    {
        $choices = [];
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
                $choices[$key] = ['caption' => $caption . ' — ' . $account, 'value' => $key];
            }
        }
        foreach ($this->selectedDeviceKeys(true) as $key => $_) {
            $choices[$key] ??= ['caption' => $this->Translate('Unavailable Apple device'), 'value' => $key];
        }
        uasort($choices, static fn (array $left, array $right): int => strnatcasecmp($left['caption'], $right['caption']));

        foreach ($form['elements'] as &$element) {
            if (($element['name'] ?? '') !== 'DeviceSelectionPanel') {
                continue;
            }
            foreach ($element['items'] as &$field) {
                if (($field['name'] ?? '') === 'SelectedDevices') {
                    array_push($field['columns'][0]['edit']['options'], ...array_values($choices));
                }
            }
            unset($field);
        }
        unset($element);
        return $form;
    }
}
