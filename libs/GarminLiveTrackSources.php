<?php

declare(strict_types=1);

namespace OpenWoIst;

use Closure;

trait GarminLiveTrackSources
{
    /** Polls one due Garmin Connect LiveTrack session. */
    public function PollGarminLiveTrack(): void
    {
        $lock = 'OpenWoIst.Garmin.' . $this->InstanceID;
        if (!\IPS_SemaphoreEnter($lock, 0)) {
            return;
        }
        try {
            $rows = $this->configuredGarminLiveTrack();
            $stored = $this->garminLiveTrackPolls();
            $polls = array_intersect_key($stored, $rows);
            $now = $this->garminLiveTrackTime();
            $due = [];
            foreach ($rows as $key => $row) {
                $poll = $polls[$key] ?? [];
                if (($poll['binding'] ?? null) !== $row['binding']) {
                    unset($polls[$key]);
                    $poll = [];
                }
                if ($row['session'] !== null && !($poll['terminal'] ?? false) && ($poll['next'] ?? 0) <= $now) {
                    $due[$key] = $poll['next'] ?? 0;
                }
            }
            if ($due === []) {
                if ($stored !== $polls) {
                    $this->SetBuffer('GarminLiveTrackPolls', json_encode($polls, JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR));
                }
                return;
            }
            asort($due, SORT_NUMERIC);
            $key = array_key_first($due);
            $binding = $rows[$key]['binding'];
            $previous = $polls[$key] ?? [];
            $this->DebugFlowEvent(__FUNCTION__, __LINE__, 'SENDEN', 'Garmin-LiveTrack-Sitzung abfragen.');
            $result = GarminLiveTrackClient::poll($rows[$key]['session'], $previous['cursor'] ?? null, $now, $this->garminLiveTrackTransport());
            $configured = $this->configuredGarminLiveTrack();
            if (($configured[$key]['binding'] ?? null) !== $binding) {
                return;
            }

            $status = $result['status'];
            $terminal = ($result['ended'] ?? false) || in_array($status, [404, 410], true);
            $delay = match ($status) {
                200     => 30,
                429     => 120,
                default => 60
            };
            $polls[$key] = [
                'binding'  => $binding,
                'next'     => $this->garminLiveTrackTime() + $delay,
                'terminal' => $terminal,
                'active'   => $status === 200 && !$terminal,
                'cursor'   => $result['cursor'] ?? $previous['cursor'] ?? null,
                'fix'      => $result['fix'] ?? $previous['fix'] ?? null
            ];
            $this->DebugFlowEvent(__FUNCTION__, __LINE__, 'EMPFANG', 'Garmin-LiveTrack-Sitzung ausgewertet.', [
                'HTTPStatus' => $status, 'SitzungBeendet' => $terminal, 'NeuerStandort' => isset($result['fix'])
            ]);
            $this->SetBuffer('GarminLiveTrackPolls', json_encode(array_intersect_key($polls, $configured), JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR));
            $this->publish(false);
        } finally {
            \IPS_SemaphoreLeave($lock);
        }
    }

    protected function garminLiveTrackTransport(): Closure
    {
        return GarminLiveTrackClient::transport();
    }

    protected function garminLiveTrackTime(): int
    {
        return time();
    }

    private function registerGarminLiveTrack(): void
    {
        $this->RegisterPropertyString('GarminLiveTrackSessions', '[]');
        $this->RegisterTimer('ReadGarminLiveTrack', 0, 'OWIS_PollGarminLiveTrack($_IPS["TARGET"]);');
    }

    private function maintainGarminLiveTrack(): void
    {
        $rows = $this->configuredGarminLiveTrack();
        $this->SetTimerInterval('ReadGarminLiveTrack', array_filter(array_column($rows, 'session')) === [] ? 0 : 5000);
        if ($rows === []) {
            $this->SetBuffer('GarminLiveTrackPolls', '');
        }
    }

    private function garminLiveTrackPanel(array $panel): array
    {
        $list = &$panel['items'][1];
        $list['form'][2]['validate'] = GarminLiveTrackClient::SHARE_LINK_PATTERN;
        foreach ($list['form'] as &$field) {
            $field['caption'] = $this->Translate($field['caption']);
        }
        unset($field);
        // The dialog owns the ID so no list column default can replace it on add.
        $list['form'] = '$fields = ' . var_export($list['form'], true) . '; '
            . '$fields[0]["value"] = ($GarminLiveTrackSessions["GarminEntryID"] ?? "") ?: bin2hex(random_bytes(16)); return $fields;';
        return $panel;
    }

    private function configuredGarminLiveTrack(): array
    {
        $rows = json_decode($this->ReadPropertyString('GarminLiveTrackSessions'), true, 8);
        $sources = [];
        foreach (is_array($rows) ? $rows : [] as $row) {
            $entry = $row['GarminEntryID'] ?? null;
            if (!is_string($entry) || preg_match('/^[a-f0-9]{32}$/D', $entry) !== 1) {
                continue;
            }
            $link = $row['GarminShareLink'] ?? null;
            $session = is_string($link) ? GarminLiveTrackClient::session($link) : null;
            $name = is_string($row['GarminName'] ?? null) ? trim($row['GarminName']) : '';
            $key = LocationSnapshot::key('garmin', $this->InstanceID, $entry);
            $sources[$key] ??= [
                'name'        => $name === '' ? 'Garmin LiveTrack' : $name,
                'markerColor' => LocationSnapshot::markerColor($row['GarminMarkerColor'] ?? null),
                'session'     => $session,
                'binding'     => $session === null ? null : hash('sha256', $session['url'])
            ];
        }
        return $sources;
    }

    private function garminLiveTrackPolls(): array
    {
        return json_decode($this->GetBuffer('GarminLiveTrackPolls'), true, 16) ?? [];
    }

    private function readGarminLiveTrackSources(array $previous): array
    {
        $polls = $this->garminLiveTrackPolls();
        $sources = [];
        foreach ($this->configuredGarminLiveTrack() as $key => $row) {
            $poll = $polls[$key] ?? [];
            if ($row['binding'] !== ($poll['binding'] ?? null)) {
                $poll = [];
            }
            $active = $poll['active'] ?? false;
            $object = LocationSnapshot::object($key, $row['name'], $poll['fix'] ?? null, $previous[$key]['objects'][0]['location'] ?? null, $active);
            $sources[] = [
                'id'        => $key,
                'kind'      => 'tracker',
                'name'      => $row['name'],
                'available' => $active,
                'objects'   => [LocationSnapshot::withMarkerColor($object, $row['markerColor'])]
            ];
        }
        return $sources;
    }
}
