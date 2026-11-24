<?php

declare(strict_types=1);

namespace OpenWoIst;

use Closure;

trait PajSources
{
    public function PollPajGPS(): void
    {
        $this->DebugFlowCall(__FUNCTION__, __LINE__, function (): void
        {
            $this->updatePaj(false);
        }, true);
    }

    protected function pajTransport(): Closure
    {
        return LocationHttpTransport::create();
    }

    protected function pajTime(): int
    {
        return time();
    }

    private function registerPajSources(): void
    {
        $this->RegisterPropertyString('PajEmail', '');
        $this->RegisterPropertyString('PajPassword', '');
        $this->RegisterPropertyString('PajDevices', '[]');
        $this->RegisterTimer('ReadPajGPS', 0, 'OWIS_PollPajGPS($_IPS["TARGET"]);');
    }

    private function maintainPajSources(): void
    {
        $this->SetTimerInterval('ReadPajGPS', $this->configuredPajSources() === [] ? 0 : 5000);
        $lock = 'OpenWoIst.PAJ.' . $this->InstanceID;
        if (\IPS_SemaphoreEnter($lock, 0)) {
            try {
                if ($this->pajState($this->pajAccount()) === []) {
                    $this->SetBuffer('PajState', '');
                }
            } finally {
                \IPS_SemaphoreLeave($lock);
            }
        }
    }

    private function pajAccount(): ?array
    {
        $email = trim($this->ReadPropertyString('PajEmail'));
        $password = $this->ReadPropertyString('PajPassword');
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false && $password !== '' ? ['email' => $email, 'password' => $password] : null;
    }

    private function pajState(?array $account): array
    {
        $state = json_decode($this->GetBuffer('PajState'), true, 16);
        return $account !== null && ($state['binding'] ?? null) === $this->pajSessionBinding($account) ? $state : [];
    }

    private function pajSessionBinding(array $account): string
    {
        return hash('sha256', json_encode($account, JSON_THROW_ON_ERROR));
    }

    private function pajRows(mixed $rows, string $accountKey): array
    {
        $devices = [];
        foreach (is_array($rows) ? $rows : [] as $row) {
            $id = $row['PajDeviceID'] ?? null;
            if (!is_int($id) || $id <= 0 || ($row['PajAccountKey'] ?? null) !== $accountKey) {
                continue;
            }
            $name = is_string($row['PajDeviceName'] ?? null) ? trim($row['PajDeviceName']) : '';
            $devices[$id] = [
                'PajDeviceID'    => $id,
                'PajAccountKey'  => $accountKey,
                'PajDeviceName'  => $name === '' ? $this->Translate('PAJ GPS device') : $name,
                'PajSelected'    => ($row['PajSelected'] ?? false) === true,
                'PajMarkerColor' => LocationSnapshot::markerColor($row['PajMarkerColor'] ?? null) ?? -1
            ];
        }
        return $devices;
    }

    private function pajFormValues(array $account, array $names, mixed $rows): array
    {
        $accountKey = hash('sha256', $account['email']);
        $saved = $this->pajRows($rows, $accountKey);
        $values = array_filter($saved, static fn (array $row): bool => $row['PajSelected'] || $row['PajMarkerColor'] !== -1);
        foreach ($names as $id => $name) {
            $values[$id] = [
                'PajDeviceID'    => $id,
                'PajAccountKey'  => $accountKey,
                'PajDeviceName'  => $name === '' ? $this->Translate('PAJ GPS device') : $name,
                'PajSelected'    => $saved[$id]['PajSelected'] ?? false,
                'PajMarkerColor' => $saved[$id]['PajMarkerColor'] ?? -1
            ];
        }
        usort($values, static fn (array $left, array $right): int => strnatcasecmp($left['PajDeviceName'], $right['PajDeviceName']));
        return $values;
    }

    private function pajPanel(array $panel): array
    {
        $account = $this->pajAccount();
        foreach ($panel['items'] as &$item) {
            if (($item['name'] ?? null) === 'PajDevices' && $account !== null) {
                $item['values'] = $this->pajFormValues($account, $this->pajState($account)['devices'] ?? [], json_decode($this->ReadPropertyString('PajDevices'), true, 8));
            }
        }
        unset($item);
        return $panel;
    }

    private function configuredPajSources(?array $account = null): array
    {
        $account ??= $this->pajAccount();
        if ($account === null) {
            return [];
        }
        $accountKey = hash('sha256', $account['email']);
        $sources = [];
        foreach ($this->pajRows(json_decode($this->ReadPropertyString('PajDevices'), true, 8), $accountKey) as $id => $row) {
            if ($row['PajSelected']) {
                $sources[LocationSnapshot::key('paj', $this->InstanceID, $accountKey . ':' . $id)] = $row;
            }
        }
        return $sources;
    }

    private function handlePajAction(string $ident, mixed $value): bool
    {
        if ($ident !== 'LoadPajDevices') {
            return false;
        }
        $rows = is_string($value) ? json_decode($value, true, 8) : null;
        $this->updatePaj(true, $rows);
        return true;
    }

    private function updatePaj(bool $discover, mixed $formRows = null): void
    {
        $lock = 'OpenWoIst.PAJ.' . $this->InstanceID;
        if (!\IPS_SemaphoreEnter($lock, 0)) {
            if ($discover) {
                $this->pajFormStatus('PAJ GPS is updating. Try again shortly.');
            }
            return;
        }
        try {
            $account = $this->pajAccount();
            if ($account === null) {
                if ($discover) {
                    $this->pajFormStatus('Apply the FINDER Portal credentials first.');
                }
                return;
            }
            $state = $this->pajState($account);
            $now = $this->pajTime();
            $ids = array_column($this->configuredPajSources($account), 'PajDeviceID');
            sort($ids, SORT_NUMERIC);
            if (!$discover && ($ids === [] || (($state['ids'] ?? []) === $ids && ($state['next'] ?? 0) > $now))) {
                return;
            }
            $this->DebugFlowEvent(__FUNCTION__, __LINE__, 'SENDEN', 'PAJ GPS abfragen.', ['Geräteliste' => $discover, 'AusgewählteGeräte' => count($ids)]);
            $request = $this->pajTransport();
            $session = $state['session'] ?? [];
            $result = $discover ? PajGPSClient::devices($account, $session, $request) : PajGPSClient::positions($ids, $now, $account, $session, $request);
            if ($account !== $this->pajAccount()) {
                return;
            }
            $status = $result['status'];
            $this->DebugFlowEvent(__FUNCTION__, __LINE__, 'EMPFANG', 'PAJ-Antwort ausgewertet.', [
                'HTTPStatus' => $status, 'Geräteliste' => $discover, 'GültigeEinträge' => count($result['devices'] ?? $result['fixes'])
            ]);
            $state['binding'] = $this->pajSessionBinding($account);
            $state['session'] = $result['session'];
            if ($discover && $status === 200) {
                $state['devices'] = $result['devices'];
            } elseif (!$discover) {
                $state['ids'] = $ids;
                $state['next'] = $this->pajTime() + ($status === 429 ? 600 : 300);
                $state['fixes'] = $result['fixes'];
            }
            $this->SetBuffer('PajState', json_encode($state, JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR));
            if (!$discover) {
                $this->publish(false);
                return;
            }
            if ($status === 200) {
                $rows = is_array($formRows) ? $formRows : json_decode($this->ReadPropertyString('PajDevices'), true, 8);
                $values = $this->pajFormValues($account, $result['devices'], $rows);
                $this->UpdateFormField('PajDevices', 'values', json_encode($values, JSON_THROW_ON_ERROR));
            }
            $this->pajFormStatus(match (true) {
                $status === 200                    => $result['devices'] === [] ? 'No devices found in the FINDER Portal.' : 'Select the devices you want to display and apply the changes.',
                $status === 401 || $status === 403 => 'Login failed. Check the FINDER Portal credentials.',
                default                            => 'The FINDER Portal device list could not be loaded.'
            });
        } finally {
            \IPS_SemaphoreLeave($lock);
        }
    }

    private function pajFormStatus(string $caption): void
    {
        $this->UpdateFormField('PajDiscoveryStatus', 'caption', $this->Translate($caption));
        $this->UpdateFormField('PajDiscoveryStatus', 'visible', true);
    }

    private function readPajSources(array $previous): array
    {
        $account = $this->pajAccount();
        $fixes = $this->pajState($account)['fixes'] ?? [];
        $sources = [];
        foreach ($this->configuredPajSources($account) as $key => $row) {
            $fix = $fixes[$row['PajDeviceID']] ?? null;
            $name = $row['PajDeviceName'];
            $object = LocationSnapshot::object($key, $name, $fix, $previous[$key]['objects'][0]['location'] ?? null, $fix !== null);
            $sources[] = [
                'id'        => $key,
                'kind'      => 'tracker',
                'name'      => $name,
                'available' => $object['current'],
                'objects'   => [LocationSnapshot::withMarkerColor($object, $row['PajMarkerColor'])]
            ];
        }
        return $sources;
    }
}
