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

## Plan für weitere Standortquellen

OpenWoIst verarbeitet den iCloud-Cache, generische Symcon-Variablen sowie
BMW-CarData-, OwnTracks- und Traccar-JSON. Smartcar und Hyundai/Kia sind
als Fahrzeug-Voreinstellungen mit simulierten Positionswechseln in Kachel
und IPSView Desktop auf `dev` (`e79ccfd`) abgenommen.

1. [x] **Volvo, Renault Z.E. und Tronity.** Die Voreinstellungen sind auf
   `dev` (`03c49ae`) in der nativen Kachel und in IPSView Desktop abgenommen.
   Alle drei Marker wechselten mit simulierten Daten bei geöffneten Ansichten
   die Position. iCloud-Marker und Testfahrzeug blieben sichtbar. Der
   Vorher-nachher-Abgleich bestätigte die unveränderten Zuordnungen und das
   unveränderte Haussteuerungsprofil.
2. [x] **Traccar-Importweg festgelegt.** Traccar-JSON mit `fixTime` aus
   einer Symcon-String-Variable bleibt der unterstützte Weg. Der Traccar
   Server kann Positionsmeldungen direkt über MQTT weiterleiten. Die
   Kopplung an das Symcon-Store-Modul ist aus dem Plan genommen. Sein
   öffentlich verlinktes GitHub-Repository lieferte am 23.11.2026 HTTP 404.
   Auf `dev` (`aba702d`) bestätigten simulierte Traccar-Meldungen über einen
   MQTT-5-Broker und den Symcon-MQTT-Client den Positionswechsel in Kachel
   und IPSView Desktop. Bei fehlendem `fixTime` blieb der letzte Punkt sichtbar.
   [Symcon Store](https://www.symcon.de/de/module-store/mwlf01-traccar/) ·
   [Einrichtung](OpenWoIst/README.md#traccar-direkt-über-mqtt-einbinden)
3. [x] **Smartphones und GPS-Tracker.** OwnTracks ist nach dem regulären
   `dev`-Update auf `963c45e` in der nativen Kachel und in IPSView Desktop
   abgenommen. Zwei simulierte Positionen und eine Nicht-Positionsmeldung
   bestätigten Positionswechsel, letzte bekannte Position und eigene
   Markerfarbe. iCloud-Marker, Testfahrzeug, Zuordnungen und das
   Haussteuerungsprofil blieben erhalten.

   Nach dem regulären `dev`-Update auf `69bad22` bestätigten simulierte
   Tractive-I/O-Antworten für 1.14 und 1.19 Beta den Positionswechsel mit
   ursprünglicher Messzeit in der nativen Kachel und in IPSView Desktop.
   iCloud-Marker und Testfahrzeug blieben sichtbar. Die Prüfsummen der
   Testkonfiguration und des Haussteuerungsprofils stimmten nach dem Test
   mit dem Ausgangsstand überein. Demel42 stimmte der Nutzung der
   Schnittstelle zu. Die Karteninstanz hat einen eigenen Formularbereich
   **Tractive GPS** für mehrere Geräte aus dem Quellmodul. Auf der
   Entwicklungsinstanz liefert ein deaktivierter Pseudo-Tracker
   simulierte Berichte auf Abruf. Die direkte Auswahl ist lokal mit
   simulierten Daten für 1.14 und 1.19 Beta geprüft. Die Laufzeitabnahme
   des neuen Formulars steht aus.
   [Tractive im Store](https://www.symcon.de/de/module-store/demel41-tractivegps/)
4. [x] **Weitere Quellen über Symcon-MQTT.** Auf `dev` (`aba702d`) zeigten
   Kachel und IPSView Desktop Positionswechsel mit simuliertem OpenWoIst-JSON
   über den Symcon-MQTT-Server. Bei fehlender Messzeit blieb die letzte bekannte
   Position sichtbar. Zuordnungen und Haussteuerungsprofil blieben erhalten.
   [Einrichtung](OpenWoIst/README.md#mqtt-gps-anschließen)

**Garmin LiveTrack.** Direkter Abruf freigegebener Garmin-Connect-Sitzungen
aus Symcon. Mit simulierten Antworten sind Garmin-Messzeit, Positionswechsel,
Sitzungsende und Linkwechsel lokal in Kachel und IPSView geprüft. Beide
Browser-Testframes zeigten den Markerwechsel. Die Abnahme mit einer echten
Freigabesitzung und den nativen Hosts steht aus.

**Formularüberarbeitung.** Die Anbieterlisten stehen in thematischen
Gruppen. Die Links zu den Quellmodulen stehen in ihren jeweiligen Bereichen.
Smartcar, Hyundai/Kia, Volvo, Renault Z.E., Tronity und OwnTracks verwenden
die direkte Instanzauswahl. BMW CarData und Traccar lesen JSON-Variablen.
Die Übernahme bisheriger Zuordnungen ist lokal geprüft. Die Abnahme im
Konsolenformular steht aus.

**Fressnapf Tracker.** Direkte Instanzauswahl unter **Haustiere** neben
Tractive. Lokale Tests prüfen Messzeit, unvollständige Berichte und beide
Kartenausgaben. Die Abnahme mit einem echten Tracker steht aus.

**Adressauflösung und Zonen.** Nach dem regulären `dev`-Update auf
`395aec1` zeigten die native Kachel und IPSView Desktop mit simulierten
Nominatim-Antworten den Adresswechsel beim Verlassen einer Kreiszone.
Der Zonenkreis blieb sichtbar, beide Karten blieben geöffnet.
Der Prüfsummenvergleich bestätigte die unveränderten Zuordnungen und
das unveränderte Haussteuerungsprofil.

**Später bei geklärter Datenquelle.** MySkoda, Tesla und Tessie benötigen
einen zur Position gehörenden Zeitstempel im Quellmodul. Für Google Find Hub
ist eine unterstützte Schnittstelle für Gerätestandorte Voraussetzung.
[Google Find Hub](https://developers.google.com/nearby/fast-pair/landing-page-find-hub)

**Ablauf pro Paket.** Bei „weiter“ gilt der erste offene, ausführbare Schritt.
Auf `dev` mit zwei simulierten Positionen je Quelle prüfen, dass der Marker
wechselt und bei einer ungültigen Meldung die letzte gültige Position
sichtbar bleibt.
Commit-Betreff und -Beschreibung auf Englisch liefern. Nach dem Push und
dem Auftrag zur Abnahme regulär auf der Entwicklungsinstanz aktualisieren.
Den Positionswechsel zusammen mit dem iCloud-Cache in Kachel und IPSView
Desktop prüfen. Bestehende Zuordnungen und das Haussteuerungsprofil vor
und nach der Abnahme abgleichen. Anschließend den Schritt abhaken und den
geprüften Commit festhalten.

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

`node tests/compact-visualization.mjs` prüft mit `OPENWOIST_CHROME` und den
PHP-Testdaten die kompakte Ausgabe bei 288 × 189 px und die normale
Hochkantansicht. Lokal bleiben 85 px Kartenhöhe in der Kachel mit Titel und
143 px in IPSView. Mausradzoom und unabhängiges Listenscrollen funktionieren
in den Browser-Testframes. Die Abnahme der kompakten Ausgabe in den nativen
Hosts steht aus.

## Abnahme auf der Entwicklungsinstanz

Symcon 9.1 hat den Modulstand `dev` mit Commit `6dad97a` geladen. Nach
der Korrektur des Referenzzugriffs war die Testinstanz aktiv. Das Modul
hat die Variablen für das Testfahrzeug und IPSView-WebContent angelegt
und das zugewiesene iCloud-Konto referenziert.

- Die direkte Kachelvorschau aus Symcon zeigte das Testfahrzeug und die
  gespeicherten Apple-Positionen. Die abgelaufene iCloud-Sitzung führte zur
  Kennzeichnung der Apple-Positionen als zuletzt bekannt. Der Quellenfilter
  und die Objektauswahl reagierten wie vorgesehen.
- Die erzeugte IPSView-WebContent-Einbindung lud im Browser ihren geschützten
  Frame. Der autorisierte Zustandsabruf lieferte beide Quellen. Abrufe ohne
  Zugriffsschlüssel wurden mit HTTP 403 beantwortet. Nach dem Bewegen des
  Testfahrzeugs aktualisierte sich der Marker bei gleichem Ausschnitt und
  gleicher Auswahl.
- Kachel- und IPSView-Designer waren in der echten Symcon-Konsole bedienbar.
  Ihre Farbkarten wurden geladen. Getrennte Kartenprofile und Markerfarben
  wurden in den Ausgaben wirksam. Die Testfarben und Profile wurden nach
  der Prüfung zurückgesetzt.
- Echte Mausradaktionen in Chromium prüften Kartenzoom und unabhängiges
  Listenscrollen in beide Richtungen mit den Ausgaben des Entwicklungsservers.

- Auf der Entwicklungsinstanz gibt es getrennte, passwortgeschützte
  Testansichten für die native Kachel und IPSView Desktop. Die Kachel
  verweist auf die OpenWoIst-Testinstanz. Die IPSView-Ansicht bindet deren
  WebContent-Variable in einer HTML-Box ein.
- Der native Live-Push wurde im regulären Symcon-Visualisierungshost
  nachgewiesen. Nach Auswahl und Zoom auf das Testfahrzeug wurde ein
  Positionswechsel serverseitig ausgelöst. Der Fahrzeugmarker wanderte im
  bereits geöffneten Frame. Kartenausschnitt, Auswahl und die zehn als
  zuletzt bekannt gekennzeichneten Apple-Marker blieben erhalten.
- Beim Verbindungsaufbau scheiterte IPSViewConnect zunächst am PHP-Limit
  von 64 MiB. SymconECharts lagert die Renderer seiner IPSView-Ausgaben
  inzwischen über den EChartsGateway aus. Der Live-Snapshot sank dadurch
  von 95,5 auf 27,1 MiB und benötigte beim Einlesen rund 42 statt 110 MiB
  PHP-Speicher. Nach der Umstellung wurde die originale Store-Datei von
  IPSViewConnect wieder eingespielt.
- Für die IPSView-Testansicht wurden die Objekttypen der Gruppen- und
  Kopiereinstellungen korrigiert. IPSView Desktop 6.5.21 lud die HTML-Box
  per ViewConnect mit dem originalen IPSViewConnect. Sie zeigte das
  Testfahrzeug und zehn als zuletzt bekannt markierte Apple-Positionen.
  Beim Fahrzeugwechsel blieben der Kartenframe, der Ausschnitt, die
  Auswahl und die Apple-Marker erhalten.

### ViewConnect-Abnahme nach dem regulären dev-Update am 22.11.2026

Die Modulverwaltung von Symcon 9.1 aktualisierte OpenWoIst aus `dev`.
SymconECharts stand bereits auf dem aktuellen `dev`-Commit mit der
Auslagerung der IPSView-Renderer. Geprüft wurden diese Commits.

- OpenWoIst `4156c0b3d2d3358e7f34d66ab388e16cb188d7d5`
- SymconECharts `67f6becd0fbfb468d94c8978c0c9692e09622820`

Die Prüfsumme von IPSViewConnect bestätigte die originale Store-Datei.
Alle 137 ECharts-WebContent-Ausgaben verwiesen auf den EChartsGateway.
Beim Einlesen des vollständigen Snapshots lag der PHP-Spitzenverbrauch
bei 42 MiB bei einem konfigurierten Limit von 64 MiB.

IPSView Desktop 6.5.21 öffnete die OpenWoIst-Testansicht per ViewConnect.
Die Karte zeigte das Testfahrzeug und zehn zuletzt bekannte
Apple-Positionen aus dem iCloud-Cache. Nach einem Wechsel der Testposition
wanderte der Fahrzeugmarker im geöffneten Kartenframe. Kartenausschnitt,
Auswahl und Apple-Marker blieben erhalten.

### Abnahme der simulierten BMW-CarData-Quelle am 22.11.2026

Auf der Symcon-9.1-Entwicklungsinstanz nutzt OpenWoIst die
**BMW CarData – Testposition (JSON)** als Fahrzeugquelle. Das Skript
**BMW CarData – Testposition wechseln** simuliert zwei BMW-CarData-Meldungen.
Es schreibt Breiten- und Längengrad zusammen mit der gemeinsamen Messzeit
als Unix-Sekunde in eine String-Variable. Als zweite Quelle war der
bestehende iCloud-Cache eingebunden.

In der nativen Kachelvisualisierung und im IPSView-Webclient über
ViewConnect wanderte der Fahrzeugmarker im geöffneten Kartenframe auf
den zweiten Testpunkt. Die Fahrzeugauswahl und alle zehn Apple-Marker
aus dem iCloud-Cache blieben erhalten. Die HTML-Box verwendete dieselbe
WebContent-Variable. Die Prüfsumme von IPSViewConnect bestätigte die
originale Store-Datei. Der IPSView-Nachweis für diesen Positionswechsel
erfolgte im Webclient mit einem separaten Browserprofil.

Nach dem regulären Update über die Modulverwaltung auf `dev` (`9b51c00`)
war der BMW-Positionswechsel in der nativen Kachel und im IPSView-Webclient
über ViewConnect erneut erfolgreich. Alle zehn Apple-Marker aus dem
iCloud-Cache blieben sichtbar. Die SHA-256-Prüfsumme der Desktop-Profildatei
der Haussteuerung war vor und nach dem Test identisch.

## Schutz des IPSView-Profils der Haussteuerung

IPSView-Tests dürfen das produktive Desktop-Verbindungsprofil der
Haussteuerung und die Auswahl der aktiven Verbindung nicht verändern.
Für Webclient-Tests ist ein separates Browserprofil zu verwenden.
Desktop-Tests erfordern eine eigene, vom produktiven Benutzerprofil
getrennte Ablage der IPSView-Verbindungseinstellungen. Die Tests verwenden
ausschließlich die dafür eingerichteten Ansichten der Entwicklungsinstanz.

Ein früherer Desktop-Test stellte die aktive Verbindung auf die
Testansicht um. Der lokale Zugang zur produktiven Ansicht wurde
rekonstruiert und durch Öffnen der Ansicht in IPSView Desktop überprüft.
