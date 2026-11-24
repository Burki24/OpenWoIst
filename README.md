# OpenWoIst

[![Symcon PHP-Modul](https://img.shields.io/badge/Symcon-PHP--Modul-blue)](https://www.symcon.de/)
[![Symcon 9.0+](https://img.shields.io/badge/Symcon-9.0%2B-blue)](library.json)
[![License](https://img.shields.io/badge/License-PolyForm%20Noncommercial%201.0.0-brightgreen.svg)](LICENSE)
[![Check Style](https://github.com/Burki24/OpenWoIst/actions/workflows/style.yml/badge.svg?branch=dev)](https://github.com/Burki24/OpenWoIst/actions/workflows/style.yml?query=branch%3Adev)
[![Tests](https://github.com/Burki24/OpenWoIst/actions/workflows/tests.yml/badge.svg?branch=dev)](https://github.com/Burki24/OpenWoIst/actions/workflows/tests.yml?query=branch%3Adev)

OpenWoIst zeigt ausgewählte Apple-Geräte, OwnTracks-Geräte und mehrere Fahrzeuge
auf einer Karte.
Die native Symcon-Kachel und die IPSView-Ausgabe haben eigene Designer.
Als Fahrzeugquelle stehen vorhandene Symcon-Variablen oder ein Testfahrzeug
mit vorbereiteten Positionen zur Verfügung.

## Voraussetzungen und Einrichtung

- IP-Symcon ab Version 9.0.
- Für Apple-Geräte ein eingerichtetes iCloud-Konto aus SymconiCloud.
- Für die Grundkarte benötigt der Visualisierungsclient Zugriff auf OpenFreeMap.

Die Bibliothek wird über **Module Control** aus einem bereitgestellten
Git-Repository eingebunden. Anschließend eine Instanz **OpenWoIst** anlegen
und die Quellen gemäß der [Modulanleitung](OpenWoIst/README.md) auswählen.

Alle Tracker werden in derselben OpenWoIst-Instanz eingerichtet.
Eingerichtete Tractive-Tracker lassen sich dort unter
[Tractive GPS](OpenWoIst/README.md#tractive-gps-geräte-hinzufügen)
direkt als Geräteinstanz auswählen.

## Lizenz

Der OpenWoIst-Quellcode steht unter der [PolyForm Noncommercial License 1.0.0](LICENSE).
Die mitgelieferten Komponenten haben eigene Lizenzhinweise.
[Symcon ModuleHelper](libs/helper/LICENSE) · [Leaflet](libs/vendor/leaflet/LICENSE) · [MapLibre und Leaflet-Anbindung](libs/helper/maps/vendor/NOTICE.json)

[Änderungsverlauf](CHANGELOG.md)
