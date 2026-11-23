<?php

declare(strict_types=1);

namespace OpenWoIst;

trait MapDesign
{
    private const POSITION_COLORS = [
        'DeviceColor'  => [0x17636C, 'Device marker color'],
        'VehicleColor' => [0xAE4E25, 'Vehicle marker color'],
        'StaleColor'   => [0x626F74, 'Last known marker color']
    ];

    public function UpdateIPSViewBasemapForm(bool $UseTileMap): void
    {
        $this->DebugFlowCall(__FUNCTION__, __LINE__, function () use ($UseTileMap): void
        {
            foreach ($this->OpenFreeMapPropertyNames() as $name) {
                $this->UpdateFormField('IPSView' . $name, 'enabled', !$UseTileMap);
            }
        }, true);
    }

    /** This HTML map uses the semantic colors of the shared IPSView style. */
    protected function IPSViewStyleNativeFamilyNames(): array
    {
        return [];
    }

    private function registerMapDesign(): void
    {
        $this->RegisterVisualizationThemeProperties();
        $this->RegisterIPSViewStyleProperties();
        $this->RegisterPropertyBoolean('IPSViewUseTileMap', true);
        $this->RegisterPropertyInteger('TileFontSize', 14);
        foreach (['Tile', 'IPSView'] as $prefix) {
            $this->RegisterOpenFreeMapProperties($prefix);
            foreach (self::POSITION_COLORS as $name => [$default]) {
                $this->RegisterPropertyInteger($prefix . $name, $default);
            }
        }
    }

    private function mapDesigner(bool $ipsView): array
    {
        $prefix = $ipsView ? 'IPSView' : 'Tile';
        $output = $ipsView
            ? $this->IPSViewHTMLPageFormItems($this->Translate('Use the generated OpenWoIst variable as WebContent in IPSView.'))
            : [];
        if ($ipsView) {
            $output[] = ['type' => 'ValidationTextBox', 'name' => 'IPSViewServerURL', 'caption' => 'Symcon server URL for IPSView (optional)'];
            $output[] = ['type' => 'Label', 'caption' => 'Set the reachable HTTP(S) server origin if the IPSView client cannot infer it.'];
            $output[] = ['type' => 'CheckBox', 'name' => 'IPSViewUseTileMap', 'caption' => 'Use the tile basemap profile',
                'onChange'      => 'OWIS_UpdateIPSViewBasemapForm($id, $IPSViewUseTileMap);'];
            $output[] = ['type' => 'Label', 'caption' => 'The IPSView style and marker colors are independent of the basemap profile.'];
        }

        $style = $ipsView ? $this->IPSViewStyleFormItems() : $this->VisualizationThemeFormItems();
        if (!$ipsView) {
            $style[] = ['type' => 'NumberSpinner', 'name' => 'TileFontSize', 'caption' => 'Font size in pixels', 'minimum' => 10, 'maximum' => 28];
        }
        $markers = [];
        foreach (self::POSITION_COLORS as $name => [, $caption]) {
            $markers[] = ['type' => 'SelectColor', 'name' => $prefix . $name, 'caption' => $caption];
        }
        $ownBasemap = !$ipsView || !$this->ReadPropertyBoolean('IPSViewUseTileMap');
        $basemap = $this->OpenFreeMapFormSection($prefix, $ownBasemap);
        return [
            'type'    => 'ExpansionPanel',
            'caption' => $ipsView ? 'IPSView designer' : 'Tile designer',
            'items'   => [
                ...$output,
                ['type' => 'ExpansionPanel', 'caption' => $ipsView ? 'IPSView style' : 'Tile style', 'items' => $style],
                ['type' => 'Label', 'caption' => 'These colors apply to devices without an individual marker color.'],
                ...$markers,
                $basemap,
                $this->mapColorGuide($ipsView, $style, $markers, $basemap)
            ]
        ];
    }

    private function mapPresentation(bool $ipsView): array
    {
        $prefix = $ipsView ? 'IPSView' : 'Tile';
        $basemapPrefix = $ipsView && $this->ReadPropertyBoolean('IPSViewUseTileMap') ? 'Tile' : $prefix;
        $css = $ipsView ? $this->IPSViewStyleCSSVariables() : $this->VisualizationThemeCSS();
        $fontSize = $ipsView ? 'var(--ipsview-role-font-size)' : max(10, min(28, $this->ReadPropertyInteger('TileFontSize'))) . 'px';
        $css .= "\n:root{--openwoist-font-size:" . $fontSize . ';';
        foreach (['DeviceColor' => 'device', 'VehicleColor' => 'vehicle', 'StaleColor' => 'old'] as $name => $variable) {
            $value = max(0, min(0xFFFFFF, $this->ReadPropertyInteger($prefix . $name)));
            $css .= sprintf('--openwoist-%s:#%06x;', $variable, $value);
        }
        return ['css' => $css . '}', 'basemap' => $this->ReadOpenFreeMapDesign($basemapPrefix)];
    }
}
