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

    private const SOURCE_MISSING = 201;
    private const VARIABLES_MISSING = 202;
    private const POSITION_MISSING = 203;

    public function Create(): void
    {
        $this->DebugFlowCall(__FUNCTION__, __LINE__, function (): void
        {
            parent::Create();
            $this->RegisterPropertyInteger('TrackerInstance', 0);
            $this->RegisterAttributeString('TrackerBinding', '');
            $this->RegisterVariableString('Position', $this->Translate('Position (OpenWoIst JSON)'), '', 0);
            $this->RegisterMessage(0, IPS_KERNELMESSAGE);
            // Keep the timer ident so updates can stop schedules created by the former adapter.
            $this->RegisterTimer('ReadPosition', 0, 'OWIT_Update($_IPS["TARGET"]);');
        }, true);
    }

    public function ApplyChanges(): void
    {
        $this->DebugFlowCall(__FUNCTION__, __LINE__, function (): void
        {
            parent::ApplyChanges();
            $this->SetTimerInterval('ReadPosition', 0);
            $this->Update();
        }, true);
    }

    /** Refreshes the local variable subscriptions of the selected Tractive tracker. */
    public function Update(): void
    {
        $this->DebugFlowCall(__FUNCTION__, __LINE__, function (): void
        {
            $lock = 'OpenWoIstTractive.' . $this->InstanceID;
            if (!IPS_SemaphoreEnter($lock, 1000)) {
                return;
            }
            try {
                $tracker = $this->ReadPropertyInteger('TrackerInstance');
                if (!TractivePosition::isDevice($tracker)) {
                    $this->bindTracker(0, []);
                    $this->SetStatus(self::SOURCE_MISSING);
                    return;
                }
                $fields = TractivePosition::variables($tracker);
                $this->bindTracker($tracker, $fields);
                $this->SetStatus($fields === [] ? self::VARIABLES_MISSING : IS_ACTIVE);
            } finally {
                IPS_SemaphoreLeave($lock);
            }
        }, true);
    }

    public function MessageSink(int $TimeStamp, int $SenderID, int $Message, array $Data): void
    {
        $this->DebugFlowCall(__FUNCTION__, __LINE__, function () use ($TimeStamp, $SenderID, $Message, $Data): void
        {
            parent::MessageSink($TimeStamp, $SenderID, $Message, $Data);
            if ($Message === IPS_KERNELMESSAGE && ($Data[0] ?? null) === KR_READY) {
                $this->Update();
                return;
            }
            if ($SenderID === $this->ReadPropertyInteger('TrackerInstance') && in_array($Message, TractivePosition::OBJECT_MESSAGES, true)) {
                $this->Update();
                return;
            }
            if ($Message !== VM_UPDATE) {
                return;
            }

            $lock = 'OpenWoIstTractive.' . $this->InstanceID;
            if (!IPS_SemaphoreEnter($lock, 1000)) {
                return;
            }
            try {
                $fields = json_decode($this->GetBuffer('TrackerVariables'), true) ?? [];
                $field = array_search($SenderID, $fields, true);
                if ($field === false) {
                    return;
                }
                $pending = json_decode($this->GetBuffer('PendingPosition'), true) ?? [];
                [$pending, $fix, $finished] = TractivePosition::receive($pending, $field, $Data[0] ?? null, $TimeStamp, time());
                $this->SetBuffer('PendingPosition', $pending === [] ? '' : json_encode($pending, JSON_THROW_ON_ERROR));
                if ($finished) {
                    $this->publishPosition($fix);
                }
            } finally {
                IPS_SemaphoreLeave($lock);
            }
        }, true);
    }

    private function bindTracker(int $tracker, array $fields): void
    {
        $identity = $tracker > 0 ? hash('sha256', json_encode([$tracker, $fields], JSON_THROW_ON_ERROR)) : '';
        if ($identity !== $this->ReadAttributeString('TrackerBinding')) {
            $this->SetBuffer('PendingPosition', '');
            if ($this->GetValue('Position') !== '') {
                $this->DebugFlowWriteValue('Position', '', __FUNCTION__, __LINE__, false);
            }
            $this->WriteAttributeString('TrackerBinding', $identity);
        }
        $this->SetBuffer('TrackerVariables', json_encode($fields, JSON_THROW_ON_ERROR));

        $desired = [0 => [IPS_KERNELMESSAGE]];
        if ($tracker > 0) {
            $desired[$tracker] = TractivePosition::OBJECT_MESSAGES;
        }
        foreach ($fields as $id) {
            $desired[$id] = [VM_UPDATE];
        }
        $registered = $this->GetMessageList();
        foreach ($registered as $id => $messages) {
            foreach (array_diff($messages, $desired[$id] ?? []) as $message) {
                $this->UnregisterMessage($id, $message);
            }
        }
        foreach ($desired as $id => $messages) {
            foreach (array_diff($messages, $registered[$id] ?? []) as $message) {
                $this->RegisterMessage($id, $message);
            }
        }
        $references = $this->GetReferenceList();
        $requiredReferences = $tracker > 0 ? [$tracker, ...array_values($fields)] : [];
        foreach (array_diff($references, $requiredReferences) as $id) {
            $this->UnregisterReference($id);
        }
        foreach (array_diff($requiredReferences, $references) as $id) {
            $this->RegisterReference($id);
        }
    }

    private function publishPosition(?array $fix): void
    {
        if ($fix === null) {
            $this->SetStatus(self::POSITION_MISSING);
            $this->DebugFlowEvent(__FUNCTION__, __LINE__, 'AKTION', 'Lokale Positionswerte sind unvollständig oder uneindeutig.');
            return;
        }
        $this->SetStatus(IS_ACTIVE);
        $previous = json_decode($this->GetValue('Position'), true, 8);
        if ($fix['timestamp'] > ($previous['timestamp'] ?? 0)) {
            $this->DebugFlowWriteValue('Position', json_encode($fix, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION), __FUNCTION__, __LINE__, false);
        }
    }
}
