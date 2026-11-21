<?php

declare(strict_types=1);

namespace OpenWoIst;

trait MapColorGuide
{
    private const TILE_COLOR_AREAS = [
        'Background' => [1, 'Map frame and toolbar background'],
        'Text'       => [2, 'Object names and controls, with derived surfaces and borders'],
        'Heading'    => [3, 'Object list heading'],
        'Subheading' => [4, 'Position time and object details'],
        'Accent'     => [5, 'Selection and keyboard focus'],
        'Warning'    => [6, 'Connection or source message']
    ];

    private const IPSVIEW_COLOR_AREAS = [
        'ViewBackground'          => [1, 'Map frame and toolbar background'],
        'PageBackground'          => [3, 'Object list and map fallback background'],
        'ControlBackground'       => [7, 'Toolbar and zoom buttons'],
        'ControlActiveBackground' => [5, 'Selected row background'],
        'Text'                    => [2, 'Object names and controls'],
        'TextActive'              => [5, 'Selected object name'],
        'LabelText'               => [3, 'Object list heading'],
        'TextInactive'            => [4, 'Position time and object details'],
        'Icon'                    => [7, 'Zoom symbols'],
        'Border'                  => [1, 'Map, list and control borders'],
        'Line'                    => [7, 'Line between zoom buttons'],
        'Accent'                  => [5, 'Selection and keyboard focus'],
        'Warning'                 => [6, 'Connection or source message'],
        'Shadow'                  => [1, 'Map frame shadow']
    ];

    private function mapColorGuide(bool $ipsView, array $style, array $markers, array $basemap): array
    {
        $stylePrefix = $ipsView ? 'IPSViewStyle' : 'VisualizationTheme';
        $areas = $ipsView ? self::IPSVIEW_COLOR_AREAS : self::TILE_COLOR_AREAS;
        $legend = [];
        $unused = [];
        foreach ($this->mapColorFields($style) as $name => $caption) {
            $role = substr($name, strlen($stylePrefix), -5);
            if (isset($areas[$role])) {
                [$number, $area] = $areas[$role];
                $legend[$number . ' · ' . $caption] = $this->Translate($area);
            } else {
                $unused[] = $caption;
            }
        }
        $markerAreas = ['Current Apple position', 'Current vehicle position', 'Last valid position of either source'];
        foreach (array_values($this->mapColorFields($markers)) as $i => $caption) {
            $legend[($i + 8) . ' · ' . $caption] = $this->Translate($markerAreas[$i]);
        }
        $palettes = [];
        foreach ($basemap['items'] as $panel) {
            $fields = $panel['type'] === 'ExpansionPanel' ? $this->mapColorFields($panel['items']) : [];
            if ($fields !== []) {
                $palettes[$panel['caption']] = array_values($fields);
            }
        }

        $title = $this->Translate($ipsView ? 'IPSView designer' : 'Tile designer');
        $items = [
            ['type' => 'Label', 'caption' => $this->Translate('Schematic OpenWoIst example with sample colors.')],
            ['type'     => 'Image', 'name' => $ipsView ? 'IPSViewMapColorGuide' : 'TileMapColorGuide', 'width' => '100%',
                'image' => 'data:image/svg+xml;base64,' . base64_encode($this->mapColorIllustration($title, array_keys($legend), $palettes, $ipsView))]
        ];
        foreach ($legend as $caption => $area) {
            $items[] = ['type' => 'Label', 'caption' => $caption . ' – ' . $area];
        }
        foreach ($palettes as $title => $fields) {
            $items[] = ['type' => 'Label', 'caption' => $title . ' – ' . implode(', ', $fields)];
        }
        $items[] = ['type' => 'Label', 'caption' => $this->Translate('The light and dark palettes color the corresponding visible vector layers when custom map colors are enabled.')];
        if ($unused !== []) {
            $items[] = ['type' => 'Label', 'caption' => $this->Translate('Additional shared style colors without an assigned element in this view') . ' – ' . implode(', ', $unused)];
        }
        return ['type' => 'ExpansionPanel', 'caption' => 'OpenWoIst color guide', 'items' => $items];
    }

    private function mapColorFields(array $items): array
    {
        $fields = [];
        foreach ($items as $item) {
            if ($item['type'] === 'SelectColor') {
                $fields[$item['name']] = $this->Translate($item['caption']);
            }
            if (isset($item['items'])) {
                $fields += $this->mapColorFields($item['items']);
            }
        }
        return $fields;
    }

    private function mapColorIllustration(string $title, array $roles, array $palettes, bool $ipsView): string
    {
        $label = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_XML1 | ENT_SUBSTITUTE, 'UTF-8');
        $title = $label($title);
        $filter = $label($this->Translate('All sources'));
        $objects = $label($this->Translate('Objects'));
        $device = $label($this->Translate('Apple device'));
        $vehicle = $label($this->Translate('Test vehicle'));
        $old = $label($this->Translate('Last known position'));
        $warning = $label($this->Translate('Source unavailable'));
        $frame = $ipsView ? '#e9edf2' : '#f5f7f7';
        $colorRows = (int) ceil(count($roles) / 2);
        $paletteY = 222 + $colorRows * 18;
        $height = $paletteY + 138;
        $svg = <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 $height" role="img">
  <title>$title</title>
  <rect width="640" height="$height" rx="14" fill="#ffffff"/>
  <g font-family="Arial, sans-serif" fill="#19292e">
    <text x="18" y="26" font-size="19" font-weight="bold">$title</text>
    <rect x="18" y="40" width="604" height="163" rx="10" fill="$frame" stroke="#b7c7ce"/>
    <rect x="28" y="47" width="178" height="24" rx="5" fill="#fff" stroke="#ccd7da"/>
    <text x="36" y="64" font-size="12">$filter</text>
    <rect x="28" y="80" width="383" height="111" rx="7" fill="#d6e5ce" stroke="#ccd7da"/>
    <path d="M34 138 Q112 109 230 139 T405 136" fill="none" stroke="#a8cde6" stroke-width="18"/>
    <path d="M55 168 L180 113 L275 122 L383 106" fill="none" stroke="#f6edaf" stroke-width="6"/>
    <path d="M231 149l55 3 24 29-64 2z" fill="#b1cd97"/>
    <path d="M116 110 L131 151 M316 108 L327 134" fill="none" stroke="#c6cdc2" stroke-width="12"/>
    <text x="51" y="102" font-size="11" fill="#374249">Berlin</text>
    <rect x="379" y="86" width="20" height="38" rx="4" fill="#fff" stroke="#ccd7da"/>
    <path d="M381 105h16" stroke="#ccd7da"/>
    <text x="384" y="100" font-size="13">+</text><text x="384" y="118" font-size="13">−</text>
    <circle cx="140" cy="128" r="7" fill="#17636c" stroke="#fff" stroke-width="2"/>
    <rect x="266" y="117" width="14" height="14" rx="4" fill="#ae4e25" stroke="#fff" stroke-width="2"/>
    <circle cx="336" cy="164" r="7" fill="#626f74" stroke="#fff" stroke-width="2"/>
    <rect x="422" y="80" width="187" height="111" rx="7" fill="#fff" stroke="#ccd7da"/>
    <text x="435" y="101" font-size="13" font-weight="bold">$objects</text>
    <text x="435" y="118" font-size="12">$device</text>
    <rect x="429" y="124" width="171" height="36" rx="4" fill="#dff1f2" stroke="#17636c"/>
    <text x="435" y="139" font-size="12">$vehicle</text>
    <text x="435" y="153" font-size="10" fill="#506268">$old</text>
    <path d="M433 166v16" stroke="#b47521" stroke-width="3"/>
    <text x="439" y="177" font-size="11" fill="#506268">$warning</text>
SVG;
        $targets = [1 => [605, 52], [594, 114], [594, 96], [594, 150], [594, 132], [594, 175], [368, 93], [155, 124], [287, 114], [352, 160]];
        $used = array_unique(array_map(static fn (string $role): int => (int) $role, $roles));
        foreach ($used as $number) {
            [$cx, $cy] = $targets[$number];
            $svg .= '<circle cx="' . $cx . '" cy="' . $cy . '" r="8" fill="#193b46"/>';
            $svg .= '<text x="' . $cx . '" y="' . ($cy + 3) . '" text-anchor="middle" font-size="9" fill="#fff">' . $number . '</text>';
        }
        foreach ($roles as $i => $caption) {
            $x = $i < $colorRows ? 20 : 320;
            $y = 222 + ($i % $colorRows) * 18;
            $svg .= '<text x="' . $x . '" y="' . $y . '" font-size="13">' . $label($caption) . '</text>';
        }
        $swatches = ['#d6e5ce', '#a8cde6', '#b1cd97', '#f6edaf', '#c6cdc2', '#374249'];
        $x = 20;
        foreach ($palettes as $caption => $fields) {
            $svg .= '<text x="' . $x . '" y="' . ($paletteY + 5) . '" font-size="14" font-weight="bold">' . $label($caption) . '</text>';
            foreach ($fields as $i => $field) {
                $y = $paletteY + 25 + $i * 16;
                $color = $x === 20 ? $swatches[$i] : ['#293233', '#264957', '#395741', '#817960', '#4d5255', '#d7dee1'][$i];
                $svg .= '<rect x="' . $x . '" y="' . ($y - 11) . '" width="12" height="12" rx="2" fill="' . $color . '"/>';
                $svg .= '<text x="' . ($x + 20) . '" y="' . $y . '" font-size="13">' . $label($field) . '</text>';
            }
            $x += 300;
        }
        return $svg . '</g></svg>';
    }
}
