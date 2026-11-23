<?php

declare(strict_types=1);

namespace OpenWoIst;

use Burki24\SymconModuleHelper\ReverseGeocodingHelper;
use InvalidArgumentException;

trait LocationAddresses
{
    use ReverseGeocodingHelper;

    /** Resolves at most one current, uncached position in a serialized timer call. */
    public function ResolveAddresses(): void
    {
        $this->DebugFlowCall(__FUNCTION__, __LINE__, function (): void
        {
            $lock = 'OpenWoIst.Addresses.' . $this->InstanceID;
            if (!\IPS_SemaphoreEnter($lock, 0)) {
                return;
            }
            try {
                $configuration = $this->addressConfiguration();
                if ($configuration === null) {
                    if (!$this->ReadPropertyBoolean('EnableAddressResolution')) {
                        $this->ClearReverseGeocodingCache();
                    }
                    $this->SetTimerInterval('ResolveAddresses', 0);
                    return;
                }
                $snapshot = $this->storedSnapshot();
                if (($snapshot['scope'] ?? '') !== $this->sourceScope()) {
                    return;
                }
                $now = time();
                foreach ($snapshot['sources'] as $source) {
                    foreach ($source['objects'] as $object) {
                        if (!$this->addressCanBeResolved($source, $object, $now, $snapshot['staleAfter'])) {
                            continue;
                        }
                        $location = $object['location'];
                        $zoom = $this->addressPrecision($location)['zoom'];
                        if ($this->readLocationAddress($configuration, $location, $zoom) !== null) {
                            continue;
                        }
                        $this->DebugFlowEvent(__FUNCTION__, __LINE__, 'SENDEN', 'Adressdienst für eine aktuelle Position abfragen.', ['Anbieter' => $configuration['provider']]);
                        $address = $configuration['provider'] === 'geoapify'
                            ? $this->ResolveGeoapifyAddress($configuration['key'], $location['latitude'], $location['longitude'], $configuration['language'], $zoom, $configuration['limit'])
                            : $this->ResolveNominatimAddress($configuration['endpoint'], $location['latitude'], $location['longitude'], $configuration['language'], $zoom);
                        $this->DebugFlowEvent(__FUNCTION__, __LINE__, 'EMPFANG', $address === '' ? 'Keine Adresse vorliegend.' : 'Adresse liegt vor.');
                        if (!$this->ReadPropertyBoolean('EnableAddressResolution')) {
                            $this->ClearReverseGeocodingCache();
                        }
                        $this->publish(false);
                        return;
                    }
                }
                $this->SetTimerInterval('ResolveAddresses', 0);
            } finally {
                \IPS_SemaphoreLeave($lock);
            }
        }, true);
    }

    private function registerLocationAddresses(): void
    {
        $this->RegisterReverseGeocodingCache();
        $this->RegisterPropertyBoolean('EnableAddressResolution', false);
        $this->RegisterPropertyString('AddressProvider', 'nominatim');
        $this->RegisterPropertyString('NominatimServer', '');
        $this->RegisterPropertyString('GeoapifyAPIKey', '');
        $this->RegisterPropertyInteger('GeoapifyDailyLimit', 2500);
        $this->RegisterPropertyString('AddressLanguage', 'de');
        $this->RegisterTimer('ResolveAddresses', 0, 'OWIS_ResolveAddresses($_IPS["TARGET"]);');
    }

    private function addressConfiguration(): ?array
    {
        if (!$this->ReadPropertyBoolean('EnableAddressResolution')) {
            return null;
        }
        $language = trim($this->ReadPropertyString('AddressLanguage'));
        if (preg_match('/^[a-zA-Z]{2,3}(?:-[a-zA-Z0-9]{2,8})?$/D', $language) !== 1) {
            return null;
        }
        $provider = $this->ReadPropertyString('AddressProvider');
        try {
            if ($provider === 'nominatim') {
                return ['provider' => $provider, 'language' => $language,
                    'endpoint'     => $this->NormalizeNominatimServer($this->ReadPropertyString('NominatimServer'))];
            }
            if ($provider === 'geoapify') {
                $limit = $this->ReadPropertyInteger('GeoapifyDailyLimit');
                $this->GeoapifyDailyUsage($limit);
                return ['provider' => $provider, 'language' => $this->NormalizeGeoapifyLanguage($language), 'limit' => $limit,
                    'key'          => $this->NormalizeGeoapifyKey($this->ReadPropertyString('GeoapifyAPIKey'))];
            }
        } catch (InvalidArgumentException) {
            return null;
        }
        return null;
    }

    private function readLocationAddress(array $configuration, array $location, int $zoom): ?string
    {
        return $configuration['provider'] === 'geoapify'
            ? $this->ReadGeoapifyAddress($configuration['key'], $location['latitude'], $location['longitude'], $configuration['language'], $zoom)
            : $this->ReadNominatimAddress($configuration['endpoint'], $location['latitude'], $location['longitude'], $configuration['language'], $zoom);
    }

    private function addressCanBeResolved(array $source, array $object, int $now, int $maxAge): bool
    {
        return $source['kind'] !== 'demo' && $source['available'] && $object['current'] && $object['location'] !== null
            && $object['location']['timestamp'] <= $now + 30 && $now - $object['location']['timestamp'] < $maxAge;
    }

    private function addressPrecision(array $location): array
    {
        return $this->ReverseGeocodingPrecision(($location['isInaccurate'] ?? false) ? null : $location['accuracy']);
    }

    private function decorateLocationAddresses(array &$sources, int $now, int $maxAge): string
    {
        $configuration = $this->addressConfiguration();
        $pending = false;
        foreach ($sources as &$source) {
            foreach ($source['objects'] as &$object) {
                unset($object['address'], $object['addressApproximate']);
                $location = $object['location'];
                if ($configuration === null || $location === null || $source['kind'] === 'demo') {
                    continue;
                }
                $precision = $this->addressPrecision($location);
                $address = $this->readLocationAddress($configuration, $location, $precision['zoom']);
                if ($address === null) {
                    $pending = $pending || $this->addressCanBeResolved($source, $object, $now, $maxAge);
                } elseif ($address !== '') {
                    $object['address'] = $address;
                    $object['addressApproximate'] = $precision['approximate'];
                }
            }
            unset($object);
        }
        unset($source);

        if ($pending && $configuration['provider'] === 'geoapify') {
            $pending = $this->GeoapifyDailyUsage($configuration['limit'])['remaining'] > 0;
        }
        $interval = $pending ? 2000 : 0;
        if ($this->GetTimerInterval('ResolveAddresses') !== $interval) {
            $this->SetTimerInterval('ResolveAddresses', $interval);
        }
        return $configuration['provider'] ?? '';
    }
}
