<?php

declare(strict_types=1);

namespace OpenWoIst;

trait TractiveSources
{
    private function tractiveTrackerRows(): array
    {
        $rows = json_decode($this->ReadPropertyString('TractiveTrackers'), true, 8);
        return is_array($rows) && array_is_list($rows) ? array_values(array_filter($rows, 'is_array')) : [];
    }

    private function configureTractiveForm(array $form): array
    {
        $values = [];
        foreach ($this->tractiveTrackerRows() as $row) {
            $id = is_int($row['TractiveInstance'] ?? null) ? $row['TractiveInstance'] : 0;
            $name = is_string($row['TractivePetName'] ?? null) ? trim($row['TractivePetName']) : '';
            if ($name === '' && TractivePosition::isDevice($id)) {
                $name = IPS_GetName($id);
            }
            $values[] = [
                'TractiveInstance'    => $id,
                'TractivePetName'     => $name,
                'TractiveMarkerColor' => LocationSnapshot::markerColor($row['TractiveMarkerColor'] ?? null) ?? -1
            ];
        }
        foreach ($form['elements'] as &$element) {
            if (($element['name'] ?? '') !== 'TractiveSources') {
                continue;
            }
            foreach ($element['items'] as &$field) {
                if (($field['name'] ?? '') === 'TractiveTrackers') {
                    $field['values'] = $values;
                }
            }
            unset($field);
        }
        unset($element);
        return $form;
    }

    private function configuredTractiveTrackers(): array
    {
        $trackers = [];
        foreach ($this->tractiveTrackerRows() as $row) {
            $id = $row['TractiveInstance'] ?? null;
            if (is_int($id) && $id > 0) {
                $trackers[$id] ??= LocationSnapshot::markerColor($row['TractiveMarkerColor'] ?? null) ?? -1;
            }
        }
        return $trackers;
    }

    private function tractiveBindings(): array
    {
        return json_decode($this->GetBuffer('TractiveBindings'), true) ?? [];
    }

    private function refreshTractiveBindings(): array
    {
        $bindings = [];
        foreach (array_keys($this->configuredTractiveTrackers()) as $id) {
            if (TractivePosition::isDevice($id)) {
                $bindings[$id] = TractivePosition::variables($id);
            }
        }
        $old = $this->tractiveBindings();
        if ($bindings !== $old) {
            $pending = json_decode($this->GetBuffer('TractiveReports'), true) ?? [];
            foreach (array_keys($pending) as $id) {
                if (($bindings[$id] ?? null) !== ($old[$id] ?? null)) {
                    unset($pending[$id]);
                }
            }
            $this->SetBuffer('TractiveBindings', json_encode($bindings, JSON_THROW_ON_ERROR));
            $this->SetBuffer('TractiveReports', json_encode($pending, JSON_THROW_ON_ERROR));
        }
        return $bindings;
    }

    private function captureTractiveUpdate(int $sender, int $observedAt, mixed $value, array $configured, int $now): ?array
    {
        foreach ($this->tractiveBindings() as $id => $fields) {
            $field = array_search($sender, $fields, true);
            if ($field === false || !isset($configured[$id])) {
                continue;
            }
            $reports = json_decode($this->GetBuffer('TractiveReports'), true) ?? [];
            $pending = $reports[$id] ?? [];
            [$next, $fix] = TractivePosition::receive($pending, $field, $value, $observedAt, $now);
            if ($next !== $pending) {
                if ($next === []) {
                    unset($reports[$id]);
                } else {
                    $reports[$id] = $next;
                }
                $this->SetBuffer('TractiveReports', json_encode($reports, JSON_THROW_ON_ERROR));
            }
            return $fix === null ? null : ['tracker' => $id, 'location' => $fix];
        }
        return null;
    }

    private function readTractiveSources(array $configured, array $old, ?array $update): array
    {
        $bindings = $this->tractiveBindings();
        $sources = [];
        foreach ($configured as $id => $color) {
            $key = LocationSnapshot::key('tractive', $id);
            $previous = $old[$key]['objects'][0] ?? [];
            $previousFix = $previous['location'] ?? null;
            $known = isset($bindings[$id]) && IPS_InstanceExists($id);
            $available = $known && $bindings[$id] !== [];
            $name = $known ? IPS_GetName($id) : $this->Translate('Tractive GPS device');
            $fix = $available && ($previous['current'] ?? false) ? $previousFix : null;
            if ($available && ($update['tracker'] ?? null) === $id && $update['location']['timestamp'] > ($previousFix['timestamp'] ?? 0)) {
                $fix = $update['location'];
            }
            $object = LocationSnapshot::withMarkerColor(LocationSnapshot::object($key, $name, $fix, $previousFix, true), $color);
            $sources[] = ['id' => $key, 'kind' => 'tracker', 'name' => $name, 'available' => $available, 'objects' => [$object]];
        }
        return $sources;
    }
}
