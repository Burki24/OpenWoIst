# OpenWoIst Entwicklung

## Standortquellen und Visualisierung

Das eigenständige Modul verwendet das HTML SDK für die native Symcon-Kachel.
Es liest den öffentlichen lokalen Cachevertrag von SymconiCloud über
`ICAC_GetDevices`. Das Konto bleibt für den Zugriff auf Apple zuständig.
Fahrzeuge stammen aus Symcon-Variablen. Der Demomodus benutzt denselben
JSON-Lesepfad wie ein zugewiesener String-Wert.

Die Quellen liefern einen gemeinsamen Momentzustand mit opaken Kennungen.
Die letzte übernommene Position wird pro Objekt im Instanzattribut gehalten.
Die native Kachel erhält Positionen über das HTML SDK. Der IPSView-WebHook
liefert eine leere Kartenseite und stellt Standortdaten nach erfolgreicher
Token-Prüfung über einen eigenen Endpunkt bereit.

[ADR zur Quellenanbindung](docs/adr/0001-location-sources.md) ·
[ADR zu Kachel und IPSView](docs/adr/0002-visualization.md)

## Lokale Prüfungen

Die PHP-Integrationstests laden die offiziellen SymconStubs. Der Standardpfad
ist `tests/stubs`. Alternativ verweist `SYMCON_STUBS_PATH` auf einen vorhandenen
Checkout derselben Stub-Version.

```text
php tests/run.php
php tests/validate_structure.php
node --check OpenWoIst/visualization/app.js
node --check OpenWoIst/visualization/frame.js
```

Die PHP-Integration prüft getrennte Gestaltungsprofile, die Übernahme der
Grundkarte, sichere IPSView-Antworten, den Tokenwechsel und die unveränderte
WebContent-Variable bei Positionsupdates. Beide Farbdiagramme werden aus
den tatsächlichen Formularbeschriftungen erzeugt. Der Bundle-Nachweis
deckt die eingebundenen Helper und Assets ab.

Lokale Chromium-Prüfungen mit synthetischen Daten, gebündeltem Renderer
und lokalem Kartenstil waren für Kachel und eingebetteten IPSView-Frame
erfolgreich. Geprüft wurden Quellenfilter, Positionswechsel bei gleicher
Kamera und Auswahl, schmales Layout und Mausradbedienung über Karte und
Liste in beiden Richtungen. Im IPSView-Frame wurden außerdem die
eigenen Markerfarben, Wiederanlauf nach einer Unterbrechung, Widerruf
des Zugriffs und die SVG-Textgrenzen beider Farbdiagramme geprüft.
Die Abnahme in der echten Symcon-Konsole, im Kachelhost und im
verwendeten IPSView-Client steht aus.
