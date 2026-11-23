<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/helper/DebugFlowHelper.php';
require_once __DIR__ . '/../libs/LocationSnapshot.php';
require_once __DIR__ . '/../libs/TractivePosition.php';

use Burki24\SymconModuleHelper\DebugFlowHelper;
use OpenWoIst\TractivePosition;

class OpenWoIstTractive extends IPSModuleStrict
{
    use DebugFlowHelper;

    private const TRACTIVE_LIBRARY = '{85B63C47-3DA6-7B2F-6472-537D9CA24360}';
    private const TRACTIVE_IO = '{0661D1B3-4375-1B37-7D59-1592111C8F8D}';
    private const TRACTIVE_DEVICE = '{A259E80D-C7B4-F5A9-F82B-B9B05F71B4F3}';
    private const TRACTIVE_REQUEST = '{94B20D14-415B-1E19-8EA4-839F948B6CBE}';
    private const SOURCE_MISSING = 201;
    private const VERSION_UNSUPPORTED = 202;
    private const POSITION_MISSING = 203;

    public function Create(): void
    {
        $this->DebugFlowCall(__FUNCTION__, __LINE__, function (): void
        {
            parent::Create();
            $this->RegisterPropertyInteger('TrackerInstance', 0);
            $this->RegisterPropertyInteger('PollInterval', 0);
            $this->RegisterAttributeString('TrackerBinding', '');
            $this->RegisterVariableString('Position', $this->Translate('Position (OpenWoIst JSON)'), '', 0);
            $this->RegisterTimer('ReadPosition', 0, 'OWIT_Update($_IPS["TARGET"]);');
        }, true);
    }

    public function ApplyChanges(): void
    {
        $this->DebugFlowCall(__FUNCTION__, __LINE__, function (): void
        {
            parent::ApplyChanges();
            $tracker = $this->ReadPropertyInteger('TrackerInstance');
            $assigned = $tracker > 0 && IPS_InstanceExists($tracker)
                && IPS_GetInstance($tracker)['ModuleInfo']['ModuleID'] === self::TRACTIVE_DEVICE;
            foreach ($this->GetReferenceList() as $reference) {
                if (!$assigned || $reference !== $tracker) {
                    $this->UnregisterReference($reference);
                }
            }
            if ($assigned) {
                $this->RegisterReference($tracker);
            }

            if ($this->syncBinding($this->binding()) !== null) {
                $this->SetStatus(IS_ACTIVE);
            }
            $interval = $this->ReadPropertyInteger('PollInterval');
            $this->SetTimerInterval('ReadPosition', $assigned && in_array($interval, [5, 10, 15], true) ? $interval * 60000 : 0);
        }, true);
    }

    /** Retrieves the selected tracker position through its connected Tractive I/O. */
    public function Update(): void
    {
        $this->DebugFlowCall(__FUNCTION__, __LINE__, function (): void
        {
            $lock = 'OpenWoIstTractive.' . $this->InstanceID;
            if (!IPS_SemaphoreEnter($lock, 0)) {
                return;
            }
            try {
                $binding = $this->binding();
                $command = $this->syncBinding($binding);
                if ($command === null) {
                    return;
                }
                if (!$this->HasActiveParent()) {
                    $this->SetStatus(self::POSITION_MISSING);
                    return;
                }

                $this->DebugFlowEvent(__FUNCTION__, __LINE__, 'SENDEN', 'Position über die zugewiesene Tractive-I/O-Instanz anfragen.', ['Befehl' => $command]);
                try {
                    $response = $this->SendDataToParent(json_encode([
                        'DataID'     => self::TRACTIVE_REQUEST,
                        'CallerID'   => $this->InstanceID,
                        'Function'   => $command,
                        'tracker_id' => $binding['tracker'],
                        'pet_id'     => $binding['pet']
                    ], JSON_THROW_ON_ERROR));
                } catch (Throwable $exception) {
                    $this->DebugFlowEvent(__FUNCTION__, __LINE__, 'FEHLER', 'Die Positionsanfrage ist fehlgeschlagen.', ['Fehlerklasse' => $exception::class]);
                    $response = '';
                }

                $current = $this->binding();
                if ($current !== $binding) {
                    $this->syncBinding($current);
                    return;
                }
                $fix = TractivePosition::fromResponse($response, $binding['tracker'], time());
                if ($fix === null) {
                    $this->SetStatus(self::POSITION_MISSING);
                    $this->DebugFlowEvent(__FUNCTION__, __LINE__, 'EMPFANG', 'Gemeldete Position fehlt oder ist ungültig. Die letzte Messung bleibt erhalten.');
                    return;
                }

                $this->SetStatus(IS_ACTIVE);
                $previous = json_decode($this->GetValue('Position'), true, 8);
                if ($fix['timestamp'] > ($previous['timestamp'] ?? 0)) {
                    $this->DebugFlowWriteValue('Position', json_encode($fix, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION), __FUNCTION__, __LINE__, false);
                }
            } finally {
                IPS_SemaphoreLeave($lock);
            }
        }, true);
    }

    private function binding(): ?array
    {
        $trackerInstance = $this->ReadPropertyInteger('TrackerInstance');
        $io = IPS_GetInstance($this->InstanceID)['ConnectionID'];
        if ($trackerInstance <= 0 || $io <= 0 || !IPS_InstanceExists($trackerInstance) || !IPS_InstanceExists($io)) {
            return null;
        }
        $device = IPS_GetInstance($trackerInstance);
        if ($device['ModuleInfo']['ModuleID'] !== self::TRACTIVE_DEVICE || $device['ConnectionID'] !== $io
            || IPS_GetInstance($io)['ModuleInfo']['ModuleID'] !== self::TRACTIVE_IO || !IPS_LibraryExists(self::TRACTIVE_LIBRARY)) {
            return null;
        }
        try {
            $tracker = IPS_GetProperty($trackerInstance, 'tracker_id');
            $pet = IPS_GetProperty($trackerInstance, 'pet_id');
        } catch (Throwable $exception) {
            $this->DebugFlowEvent(__FUNCTION__, __LINE__, 'FEHLER', 'Die zugewiesene Tracker-Konfiguration ist nicht lesbar.', ['Fehlerklasse' => $exception::class]);
            return null;
        }
        if (!is_string($tracker) || $tracker === '' || !is_string($pet)) {
            return null;
        }

        return [
            'identity' => hash('sha256', json_encode([$io, $trackerInstance, $tracker, $pet], JSON_THROW_ON_ERROR)),
            'version'  => (string) IPS_GetLibrary(self::TRACTIVE_LIBRARY)['Version'],
            'tracker'  => $tracker,
            'pet'      => $pet
        ];
    }

    private function syncBinding(?array $binding): ?string
    {
        $identity = $binding['identity'] ?? '';
        if ($identity !== $this->ReadAttributeString('TrackerBinding')) {
            if ($this->GetValue('Position') !== '') {
                $this->DebugFlowWriteValue('Position', '', __FUNCTION__, __LINE__, false);
            }
            $this->WriteAttributeString('TrackerBinding', $identity);
        }
        $command = match ($binding['version'] ?? '') {
            '1.14'  => 'GetUpdateData',
            '1.19'  => 'GetDeviceData',
            default => null
        };
        if ($command === null) {
            $this->SetStatus($binding === null ? self::SOURCE_MISSING : self::VERSION_UNSUPPORTED);
        }
        return $command;
    }
}
