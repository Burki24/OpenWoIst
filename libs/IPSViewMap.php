<?php

declare(strict_types=1);

namespace OpenWoIst;

trait IPSViewMap
{
    private const IPSVIEW_MAP_IDENT = 'IPSViewOpenWoIst';

    /** Returns the IPSView WebContent shell for the registered output variable. */
    public function GetIPSViewHTML(): string
    {
        return $this->DebugFlowCall(__FUNCTION__, __LINE__, function (): string
        {
            $origin = $this->ipsViewServerOrigin();
            return $this->RenderVisualizationHTMLPage(true, [
                'title'         => 'OpenWoIst',
                'language'      => $this->NormalizeHelperTranslationLanguage($this->ResolveHelperTranslationLanguage()),
                'templateAsset' => 'frame.html',
                'styleAsset'    => 'frame.css',
                'scriptAsset'   => 'frame.js',
                'options'       => [
                    'origin'   => $origin,
                    'page'     => $this->mapHookPath() . '/page',
                    'token'    => $this->authorizedIPSViewToken(),
                    'error'    => $this->Translate('Set a reachable Symcon server URL for IPSView.'),
                    'inactive' => $this->Translate('IPSView map access is unavailable.')
                ],
                'replacements' => ['{{HTTP_ORIGIN}}' => $this->VisualizationHTTPOriginScript()]
            ]);
        });
    }

    private function registerIPSViewMap(): void
    {
        $this->RegisterIPSViewHTMLPageProperties();
        $this->RegisterPropertyString('IPSViewServerURL', '');
        $this->RegisterAttributeString('IPSViewAccess', '');
    }

    private function maintainIPSViewMap(): void
    {
        $enabled = $this->IsIPSViewHTMLPageEnabled();
        $origin = $this->ipsViewServerOrigin();
        $scope = $enabled && $origin !== null && $this->GetBuffer('MapHookRegistered') === '1' ? $this->ipsViewScope($origin) : '';
        $access = $this->currentIPSViewAccess();
        if ($scope !== ($access['scope'] ?? '')) {
            $this->WriteAttributeString('IPSViewAccess', $scope === '' ? '' : $this->EncodeVisualizationHTMLJSON([
                'scope' => $scope,
                'token' => bin2hex(random_bytes(32))
            ]));
        }

        $this->MaintainIPSViewHTMLVariable(self::IPSVIEW_MAP_IDENT, $this->Translate('OpenWoIst for IPSView'), 100, '', false);
        if ($enabled) {
            $id = $this->IPSViewHTMLVariableID(self::IPSVIEW_MAP_IDENT);
            $html = $this->GetIPSViewHTML();
            if ($id > 0 && GetValueString($id) !== $html) {
                $this->UpdateIPSViewHTMLVariable(self::IPSVIEW_MAP_IDENT, $html);
            }
        }
    }

    private function serveIPSViewMap(string $path, string $method): void
    {
        $page = $this->mapHookPath() . '/page';
        $state = $this->mapHookPath() . '/state';
        if (($path !== $page && $path !== $state) || !$this->IsIPSViewHTMLPageEnabled()) {
            $this->SendPlainTextResponse(404, 'Not found.');
            return;
        }
        if ($method !== 'GET') {
            header('Allow: GET');
            $this->SendPlainTextResponse(405, 'Method not allowed.');
            return;
        }
        $token = $this->authorizedIPSViewToken();
        if ($token === '') {
            $this->SendPlainTextResponse(404, 'Not found.');
            return;
        }
        if ($path === $page) {
            // The public document contains the app and renderer references only.
            header('Referrer-Policy: origin');
            $this->SendResponse(200, 'text/html', $this->renderMap(true));
            return;
        }
        $provided = $_SERVER['HTTP_X_OPENWOIST_TOKEN'] ?? '';
        if (!is_string($provided) || !hash_equals($token, $provided)) {
            $this->SendPlainTextResponse(403, 'Access denied.');
            return;
        }
        $snapshot = $this->storedSnapshot();
        if (($snapshot['scope'] ?? '') !== $this->sourceScope()) {
            $this->SendPlainTextResponse(503, 'Map state not available.');
            return;
        }
        $body = $this->EncodeVisualizationHTMLJSON($this->visualizationState($snapshot, true));
        $etag = '"' . hash('sha256', $body) . '"';
        header('ETag: ' . $etag);
        if (($_SERVER['HTTP_IF_NONE_MATCH'] ?? '') === $etag) {
            header('Cache-Control: no-store, max-age=0');
            http_response_code(304);
            return;
        }
        $this->SendResponse(200, 'application/json', $body);
    }

    private function ipsViewServerOrigin(): ?string
    {
        $value = trim($this->ReadPropertyString('IPSViewServerURL'));
        if ($value === '') {
            return '';
        }
        $parts = parse_url($value);
        if (filter_var($value, FILTER_VALIDATE_URL) === false || !is_array($parts)
            || !in_array($parts['scheme'] ?? '', ['http', 'https'], true) || !array_key_exists('host', $parts)
            || array_intersect(['user', 'pass', 'query', 'fragment'], array_keys($parts)) !== []
            || !in_array($parts['path'] ?? '', ['', '/'], true)) {
            return null;
        }
        return rtrim($value, '/');
    }

    private function sourceScope(): string
    {
        $accounts = $this->accountIDs();
        sort($accounts, SORT_NUMERIC);
        $vehicles = $this->configuredVehicles();
        $legacy = $this->legacyVehicle();
        if ($legacy['mode'] === 'off') {
            foreach ($vehicles as $vehicle) {
                if ($vehicle['legacySingle'] ?? false) {
                    $legacy = $vehicle;
                    break;
                }
            }
        }
        $mode = $legacy['mode'];
        $scope = [
            $accounts, $mode, $mode === 'demo' ? [] : $legacy['ids']
        ];
        if (PositionConverter::supports($mode)) {
            $scope[] = [$legacy['timeFormat'], $legacy['decimalSeparator']];
        }
        $selection = $this->selectedDeviceKeys();
        if ($selection !== null) {
            $scope['devices'] = array_keys($selection);
        }
        if ($mode !== 'off') {
            unset($vehicles[$this->vehicleKey($legacy)]);
        }
        if ($vehicles !== []) {
            ksort($vehicles, SORT_STRING);
            $scope['vehicles'] = array_map(static fn (array $vehicle): array => [
                $vehicle['mode'], isset($vehicle['sourceInstance']) ? ['instance', $vehicle['sourceInstance']] : $vehicle['ids'], $vehicle['timeFormat'], $vehicle['decimalSeparator']
            ], $vehicles);
        }
        $trackers = [];
        foreach ($this->configuredTrackers() as $key => $tracker) {
            // Keep the original scope for existing OwnTracks assignments.
            $trackers[] = $tracker['mode'] === 'owntracks' ? $key : $key . ':' . $tracker['mode'];
        }
        if ($trackers !== []) {
            sort($trackers, SORT_STRING);
            $scope['trackers'] = $trackers;
        }
        $tractive = array_keys($this->configuredTractiveTrackers());
        if ($tractive !== []) {
            sort($tractive, SORT_NUMERIC);
            $scope['tractive'] = $tractive;
        }
        $garmin = array_keys($this->configuredGarminLiveTrack());
        if ($garmin !== []) {
            sort($garmin, SORT_STRING);
            $scope['garmin'] = $garmin;
        }
        $mapShare = array_keys($this->configuredGarminMapShare());
        if ($mapShare !== []) {
            sort($mapShare, SORT_STRING);
            $scope['mapShare'] = $mapShare;
        }
        return hash('sha256', $this->EncodeVisualizationHTMLJSON($scope));
    }

    private function ipsViewScope(string $origin): string
    {
        return hash('sha256', $this->sourceScope() . ':' . $origin);
    }

    private function currentIPSViewAccess(): array
    {
        $access = json_decode($this->ReadAttributeString('IPSViewAccess'), true);
        return is_string($access['scope'] ?? null) && is_string($access['token'] ?? null)
            && preg_match('/^[a-f0-9]{64}$/D', $access['token']) === 1 ? $access : [];
    }

    private function authorizedIPSViewToken(): string
    {
        $origin = $this->ipsViewServerOrigin();
        if (!$this->IsIPSViewHTMLPageEnabled() || $origin === null) {
            return '';
        }
        $access = $this->currentIPSViewAccess();
        return ($access['scope'] ?? '') === $this->ipsViewScope($origin) ? $access['token'] : '';
    }
}
