# OpenWoIst

## Arbeitsgrundlage

Vor Änderungen gelten [SymconDevelopment/AGENTS.md](../SymconDevelopment/AGENTS.md),
die [Architektur](../SymconDevelopment/ARCHITECTURE.md) und die
[Engineering-Prinzipien](../SymconDevelopment/principles/). Lies anschließend
`PROJECT_CONTEXT.md`, die relevanten ADRs, die betroffenen Modulverträge und
die zugehörigen zentralen Standards.

| Freigegebener Ordner | Verwendung im Projekt |
| --- | --- |
| `../OpenWoIst` | Eigenständige Library und Arbeitsordner für Implementierung, Dokumentation und Tests |
| `../SymconiCloud` | Referenz für den lokalen iCloud-Cachevertrag, die WoIst-Kachel und IPSView |
| `../Symcon_ModuleHelper` | Kanonische Helper, Übersetzungen, Visualisierungsressourcen und Bundle-Erzeugung |
| `../Symcon_ModuleCI` | Gemeinsame CI-Aktionen und Prüfskripte |
| `../SymconDevelopment` | Verbindliche Arbeitsregeln, Standards und Entscheidungshilfen |

## Modul und Visualisierung

- Zielplattform ist IP-Symcon 9 / PHP 8.5 mit `IPSModuleStrict`.
- Lizenz für OpenWoIst ist die PolyForm Noncommercial License 1.0.0 gemäß
  [LICENSE](LICENSE). Integrierte Komponenten führen ihre eigenen Lizenzhinweise.
- OpenWoIst liest zugewiesene Symcon-Variablen und optional den normalisierten Cache zugewiesener SymconiCloud-Konten. Zugangsdaten und Anbieterzugriffe liegen beim jeweiligen Quellmodul.
- Native Kachel und IPSView zeigen denselben Standortzustand. Jede Ausgabe
  hat einen eigenen Designer für Rahmen, Farben, Schrift und Kartenprofil.
  IPSView kann das Kartenprofil der Kachel übernehmen und seine eigene
  Gestaltung der Bedienelemente behalten.
- Verwende `ConfigurationFormHelper`, `VisualizationThemeConfigurationHelper`,
  `IPSViewStyleConfigurationHelper`, `IPSViewHTMLPageHelper`,
  `ResponsiveVisualizationHelper` und `OpenFreeMapHelper` entsprechend
  ihren Verträgen. Verwende für Übersetzungen und Frame-Interaktion die
  zentralen Kataloge und Assets. Prüfe weitere vorhandene Helper vor
  einer neuen Implementierung.
- Beide Farbdesigner erhalten je eine OpenWoIst-spezifische schematische
  Farbkarte und eine lokalisierte Legende gemäß
  [Konfigurationsformular-Standard](../SymconDevelopment/standards/CONFIGURATION_FORM.md).
- `libs/helper` enthält unveränderte Dateien aus dem zentralen Helper-Sync.
  Abhängigkeiten werden über `.helper-sync.json` eingebunden. Zentralisierung
  und zugehörige Consumer-Änderungen folgen dem
  [Helper-Standard](../SymconDevelopment/standards/HELPER_USAGE.md).
  Leaflet wird anhand seiner Prüfsummen geprüft.

## Prüfungen und Übergabe

- Prüfe `php tests/run.php`, `php tests/validate_structure.php`,
  den offiziellen JSON-Style-Check und php-cs-fixer im Prüfmodus.
  Prüfe Mausrad und Scrollen für beide Ausgaben im jeweiligen Host
  gemäß [Teststandard](../SymconDevelopment/standards/TESTING.md).
- Tests arbeiten mit synthetischen Standorten. Debug-Ausgaben folgen
  [DEBUG_OUTPUT.md](../SymconDevelopment/standards/DEBUG_OUTPUT.md)
  und schützen Standortdaten, Tokens und rohe Antworten.
- Bildschirm-, Fenster- und Desktopaufnahmen sowie screenshotbasierte
  Steuerung erfordern die ausdrückliche Zustimmung für die aktuelle
  Aufgabe. Computer-Use und Unified-Computer-Use bleiben deaktiviert,
  bis der Benutzer ihre Wiederaktivierung ausdrücklich verlangt.
- Entwicklung auf `dev`. Commit und Push führt der Benutzer in GitHub Desktop
  aus. Liefere Betreff und Beschreibung auf Englisch. Live-Installation,
  Änderungen an Symcon-Systemen und reale Apple-Anmeldungen erfordern
  einen gesonderten Auftrag.
