# OpenWoIst

Die Kachel zeigt Apple-Geräte, Fahrzeuge und weitere Standortgeräte
auf einer gemeinsamen Karte. Die Quellen lassen sich auch einzeln verwenden.

## Einrichten und mit dem Testfahrzeug ausprobieren

1. Eine Instanz **OpenWoIst** anlegen. Unter **Apple und Google → iCloud →
   iCloud-Konten** die Konto-Instanzen aus SymconiCloud auswählen. Voraussetzung
   für Apple-Positionen ist ein gefüllter Gerätestand im ausgewählten Konto.
2. Unter **Fahrzeuge → Bisherige Fahrzeugquelle und Testfahrzeug** den Eintrag
   **Testfahrzeug** auswählen und die Konfiguration übernehmen. Dadurch entsteht
   die String-Variable **Position des Testfahrzeugs** (`DemoPosition`) mit einem
   synthetischen Standort in Berlin.
3. Die Instanz in der Symcon-Kachelvisualisierung öffnen. Mit der Formularaktion
   **Testfahrzeug bewegen** lässt sich die Fahrzeugposition an vier vorbereiteten
   Punkten weiterschalten.

OpenWoIst liest die ausgewählten lokalen Quellen alle fünf Sekunden.
Fahrzeugvariablen melden neue Messungen zusätzlich per Ereignis. Der Zeitpunkt
einer Position stammt aus der jeweiligen Messung. Unter
**Position als veraltet markieren nach** lässt sich die Schwelle ändern
(Standard 900 Sekunden). Bei einem vorübergehenden Quellenausfall zeigt die
Kachel gespeicherte Positionen als **Letzte bekannte Position** an. Beim
Entfernen eines Kontos aus der Auswahl oder bei dessen Abmeldung werden seine
Positionen von der Karte entfernt.

Die Modulbereiche stehen unter **Fahrzeuge**, **Apple und Google**,
**Haustiere** sowie **Tracker und Standortgeräte**. Im jeweiligen Modulbereich
führt die Schaltfläche mit dem Modulnamen zum Modul-Store oder zu GitHub.

Der Quellenfilter steuert die sichtbaren Marker und Listeneinträge.
Kachelzugriff umfasst die Standortdaten aller Quellen, die der Instanz
zugewiesen sind.

Bei geringer Höhe stehen die Karte und die scrollbare Objektliste nebeneinander.
Mit **Alle anzeigen** passt die Karte alle sichtbaren Positionen in den Ausschnitt ein.

## Apple-Geräte auswählen

Standardmäßig erscheinen alle Geräte der zugewiesenen Konten. Um die Anzeige
einzuschränken, unter **iCloud** die Option **Alle Geräte anzeigen** deaktivieren
und die gewünschten Geräte in der Spalte **Ausgewählt** markieren. Nach einer
neuen Kontozuordnung die Konfiguration übernehmen und das Formular erneut öffnen,
um die Geräteliste aus dem Cache zu laden.

Der Formularbereich **GoogleCloud** zeigt den Status der Standortanbindung an.
Für GoogleCloud ist derzeit keine Standortquelle angeschlossen.

## Kachel und IPSView gestalten

Der **Kachel-Designer** bietet Symcon-Farben, Schriftgröße, Markerfarben
und ein eigenes OpenFreeMap-Profil. Im **IPSView-Designer** stehen der
gemeinsame IPSView-Stil mit Schrift und Farben sowie eigene Markerfarben
zur Verfügung. Eine Farbkarte mit Legende zeigt in jedem Designer, wo
die Einstellungen wirken.

In den Gerätelisten unter **iCloud** und **Fahrzeuge** lässt sich pro Eintrag eine
**Markerfarbe** wählen. Sie gilt für Karte und Objektliste in Kachel und IPSView.
Ein gestrichelter Rand kennzeichnet einen individuell gefärbten Marker mit einer
letzten bekannten Position. **Designer-Standard** übernimmt wieder die Farbe
aus dem jeweiligen Designer.

Unter **OpenFreeMap** lassen sich Darstellungsmodus, helle und dunkle
Kartenstile, Detailstufen, Beschriftungssprache und Kartenfarben
einstellen. IPSView nutzt standardmäßig das Grundkartenprofil der Kachel.
Für ein separates Profil im IPSView-Designer **Grundkartenprofil der
Kachel verwenden** abwählen. IPSView-Stil und Markerfarben sind auch bei
gemeinsamer Grundkarte unabhängig.

### Karte in IPSView einbinden

1. Im **IPSView-Designer** die HTML-Ausgabe aktivieren und **Übernehmen**
   wählen.
2. **OpenWoIst für IPSView** (`IPSViewOpenWoIst`) als WebContent in
   IPSView einbinden. Die String-Variable enthält die Karteneinbindung
   mit ihrem Zugriffsschlüssel. Gib sie nur an Personen mit Zugriff auf
   die zugeordneten Standorte weiter.
3. Falls IPSView den Server nicht automatisch findet, unter
   **Symcon-Server-URL für IPSView** die erreichbare HTTP(S)-Serveradresse
   mit optionalem Port eintragen, beispielsweise
   `https://symcon.example.org`.

Die IPSView-Karte liest alle fünf Sekunden den geschützten lokalen
Kartenstand. Bei Positionsupdates bleiben Zoom, Ausschnitt und Auswahl
erhalten. Änderungen der Quellzuordnung erzeugen einen neuen
Zugriffsschlüssel. Beim Deaktivieren wird der Zugriff gesperrt. Der
Ausgabe-Helper bietet für die bestehende Variable eine separate
Löschfunktion im Instanzformular.

## Tractive-GPS-Geräte hinzufügen

Installiere das [Symcon-Modul Tractive GPS](https://www.symcon.de/de/module-store/demel41-tractivegps/)
und richte dort deine Tracker ein. Öffne danach **Haustiere → Tractive GPS**
und wähle unter **Hinzufügen** je Tracker die vorhandene
Geräteinstanz. In der Liste kannst du den **Haustiernamen** und die
**Markerfarbe** je Tracker bearbeiten. Bei leerem Haustiernamen verwendet die
Liste beim Öffnen den Namen der Geräteinstanz. OpenWoIst findet die
Positionsvariablen automatisch und zeigt die Position nach dem
nächsten vollständigen Bericht in Kachel und IPSView an. Geprüft sind die
Variablenverträge von Tractive GPS 1.14 und 1.19 Beta.

Falls du im Entwicklungsstand den früheren OpenWoIst-Tractive-Adapter
ausprobiert hast, ersetze seine JSON-Zuordnung vor dem Update durch die
direkte Geräteauswahl.

## Fressnapf-Tracker hinzufügen

Richte den Tracker im [Symcon-Modul Fressnapf Tracker](https://www.symcon.de/de/module-store/bgersmann-fressnapftracker/)
ein. Füge unter **Haustiere → Fressnapf Tracker** je Gerät einen Eintrag hinzu
und wähle die vorhandene Quellinstanz. Haustiername und Markerfarbe lassen
sich pro Gerät bearbeiten. Der Namensvorschlag stammt aus der lokalen
Variable `Name` oder dem Namen der Geräteinstanz.

Die Position erscheint nach einem vollständigen Bericht mit beiden
Koordinaten und `SampledAt` als ISO-8601-Messzeit mit Zeitzone. Die optionale
Genauigkeit wird aus demselben Bericht übernommen.

## OwnTracks-Geräte hinzufügen

Wähle unter **Tracker und Standortgeräte → OwnTracks → Hinzufügen** je Gerät
die vorhandene OwnTracks-Data-Instanz aus. Aktiviere im Quellmodul die
Positionsübernahme. Wenn der Positionsdatensatz bereits als Symcon-String
vorliegt, verwende **Manuelle Variablenzuordnung**. Das JSON enthält `lat`,
`lon`, `tst` als Unix-Sekunden und optional `acc` in Metern. Geräte können
einen Namen und eine Markerfarbe erhalten.
Ohne ausgewählte Farbe greift die Gerätefarbe aus dem jeweiligen Designer.

## Garmin LiveTrack hinzufügen

1. Starte in Garmin Connect eine LiveTrack-Aktivität und kopiere ihren
   freigegebenen Sitzungslink.
2. Öffne **Tracker und Standortgeräte → Garmin LiveTrack → Hinzufügen**.
   Trage **Name auf der Karte**, **LiveTrack-Freigabelink** und bei Bedarf
   die **Markerfarbe** ein. Übernimm die Konfiguration.
3. Ersetze bei einer neuen Aktivität den Freigabelink im selben Eintrag.

Symcon fragt den jüngsten Garmin-Positionspunkt je Sitzung etwa alle
30 Sekunden ab. Die Karte verwendet die Messzeit von Garmin. Nach Ende der
Sitzung bleibt der letzte Punkt als **Letzte bekannte Position** sichtbar.

Der Freigabelink enthält den Zugriffsschlüssel zur Sitzung. Er wird in der
Symcon-Konfiguration gespeichert und im Eingabefeld maskiert. Garmin LiveTrack
nutzt für diesen Abruf die Schnittstelle der Garmin-Webkarte. Der Datenweg
ist mit simulierten Antworten geprüft. Ein Test mit einer echten
Freigabesitzung steht aus.

[Garmin: LiveTrack in Garmin Connect](https://support.garmin.com/en-CA/?faq=HbqxxbiBGA3mDhlLX4GUw8&searchType=noProduct)

## Garmin inReach / MapShare hinzufügen

1. Aktiviere MapShare in Garmin Explore und kopiere den MapShare-Freigabelink
   oder den direkten KML-Link.
2. Öffne **Tracker und Standortgeräte → Garmin inReach / MapShare →
   Hinzufügen**. Trage Name, Freigabelink und die 15-stellige **Geräte-IMEI**
   ein. Lege bei mehreren Geräten je Gerät eine Zeile mit eigener Markerfarbe
   an. Mehrere Zeilen können denselben Freigabelink verwenden.
3. Falls der Feed geschützt ist, trage unter **Passwortgeschützter MapShare-Feed**
   das MapShare-Passwort und gegebenenfalls den Benutzernamen ein. Übernimm die
   Konfiguration.

Symcon ruft den Feed etwa alle fünf Minuten ab und zeigt den jüngsten
gemeldeten Punkt je Gerät mit Garmin-Messzeit an. Bei einem Abruffehler
bleibt der letzte gültige Punkt als **Letzte bekannte Position** sichtbar.
Freigabelinks, IMEIs und Zugangsdaten bleiben in der Symcon-Konfiguration.

[Garmin: MapShare-KML-Feed und Zugriffsschutz](https://support.garmin.com/en-US/?faq=tdlDCyo1fJ5UxjUbA9rMY8)

## MQTT-GPS anschließen

Der GPS-Sender veröffentlicht die vollständige Position als eine MQTT-Nachricht.
Verwende einen Broker, den der Sender und Symcon erreichen. Bei einem externen
Broker abonniert der [MQTT Client in Symcon](https://www.symcon.de/de/service/dokumentation/modulreferenz/geraete/mqtt/mqtt-client/)
das jeweilige Topic. Beim [MQTT-Server in Symcon](https://www.symcon.de/de/service/dokumentation/modulreferenz/geraete/mqtt/mqtt-server/)
sendet die Quelle direkt dorthin. Sende eine erste Meldung auf das gewünschte
Topic. Lege es über den MQTT-Konfigurator mit einer Nutzdatenvariable vom Typ
**String** an. Ordne sie unter **Tracker und Standortgeräte → Weitere
Standortgeräte** als **Positionsvariable (JSON)** zu.

Verwende für einen Test auf `openwoist/demo/gps` diese synthetische Nachricht.

```json
{
    "latitude": 52.51461,
    "longitude": 13.35684,
    "timestamp": 1795438800,
    "horizontalAccuracy": 12
}
```

`horizontalAccuracy` ist optional und gibt die Genauigkeit in Metern an.
Für den Test ersetze `timestamp` durch eine aktuelle ganze Unix-Sekunde.
Eine zweite Meldung mit neuen Koordinaten und höherer Messzeit bewegt den
Marker. Bei fehlendem `timestamp` bleibt der letzte gültige Standort sichtbar.

## Vorhandene Fahrzeugvariablen anschließen

Unter **Fahrzeuge → Fahrzeuge aus Variablen** pro Fahrzeug einen Eintrag mit
Namen hinzufügen.
Die Liste zeigt die Namen und Quellenarten der hinzugefügten Fahrzeuge.
Die zuvor konfigurierte Einzelquelle steht weiterhin unter **Bisherige
Fahrzeugquelle und Testfahrzeug**.

- **Eine JSON-Variable** erwartet OpenWoIst-JSON mit `latitude` und
  `longitude` in Grad, `timestamp` als ganze Unix-Sekunde und optional
  `horizontalAccuracy` in Metern.
- **Drei Positionsvariablen** erwartet je eine Variable für Breitengrad,
  Längengrad und Messzeit. **Allgemein (Unix-Sekunden)** verwendet eine
  ganzzahlige Messzeitvariable. Der Datenlieferant setzt die
  Messzeitvariable vor dem Schreiben beider Koordinaten auf `0` und schreibt
  zuletzt die neue positive Messzeit. Jede neue Messung erhält eine höhere
  Messzeit. So gehören beide übernommenen Koordinaten zur selben Messung.

Beispiel für das Format einer JSON-Positionsvariable in einem Symcon-Skript

```php
SetValueString($positionsVariableID, json_encode([
    'latitude' => 52.51453,
    'longitude' => 13.35010,
    'timestamp' => time(),
    'horizontalAccuracy' => 12
], JSON_THROW_ON_ERROR));
```

### BMW CarData und Traccar anschließen

Öffne **Fahrzeuge → BMW CarData** oder **Tracker und Standortgeräte → Traccar**
und füge einen Eintrag je Fahrzeug oder Tracker hinzu. Ordne unter
**Positionsvariable (JSON)** die String-Variable mit den Originaldaten zu.
OpenWoIst konvertiert sie beim Einlesen.

Für BMW kann die Variable das Ergebnis von `BMW_getTelematicData`,
eine CarData-REST-Antwort oder eine Stream-Meldung enthalten. Beide
Koordinaten müssen denselben Messzeitpunkt haben.

Traccar unterstützt ein einzelnes Positionsobjekt und Weiterleitungen mit
`position`. OpenWoIst verwendet `fixTime` als Messzeit und übernimmt
optional `accuracy` in Metern. Mit `valid: false` markierte Positionen
werden verworfen.

Unter **Erweiterte Positionseinstellungen** gilt ISO 8601 mit Zeitzone
als Standard für BMW und Traccar und Unix-Sekunden für OwnTracks.
Unix-Millisekunden und das Dezimalkomma sind bei Bedarf wählbar.
Ungültige oder ältere Meldungen lassen die letzte gültige Position auf
der Karte stehen. Bei gleicher Messzeit bleiben die bisherigen Koordinaten
erhalten.

### Traccar direkt über MQTT einbinden

Traccar Server veröffentlicht Positionen per MQTT 5. Verwende einen
MQTT-5-fähigen Broker wie Mosquitto. Der Symcon-MQTT-Client kann dasselbe
Topic per MQTT 3.1.1 abonnieren. [Traccar-Weiterleitung](https://www.traccar.org/forward/)

1. Setze in der Traccar-Konfiguration `forward.type` auf `mqtt`,
   `forward.url` auf die Broker-Adresse mit Port (zum Beispiel
   `mqtt://broker.example.org:1883`) und `forward.topic` auf ein Topic
   wie `openwoist/traccar/test`.
2. Abonniere das Topic mit dem **MQTT Client** in Symcon. Lege ein
   **MQTT Client Device** mit einer Nutzdatenvariable vom Typ **String** an.
3. Wähle unter **Tracker und Standortgeräte → Traccar → Hinzufügen** die
   String-Variable als Positionsvariable aus.

Eine gekürzte Testmeldung enthält die Position samt GPS-Messzeit.

```json
{
    "position": {
        "deviceId": 42,
        "latitude": 52.52,
        "longitude": 13.38,
        "fixTime": "2026-11-23T19:53:19Z",
        "valid": true
    },
    "device": {"id": 42}
}
```

Verwende zum Testen eine aktuelle Messzeit. Bei einem gemeinsamen Topic für
mehrere Tracker teile die Meldungen nach `position.deviceId` auf. Jede
zugewiesene String-Variable enthält die Meldungen eines Trackers.

### Fahrzeuge aus vorhandenen Modulen auswählen

Öffne unter **Fahrzeuge** den Bereich des Quellmoduls und wähle unter
**Hinzufügen** pro Fahrzeug die vorhandene Instanz. OpenWoIst findet die lokalen
Variablen anhand der folgenden Idents. Über **Manuelle Variablenzuordnung** kannst du eigene
Variablen aus derselben Quelle verwenden. Lasse dafür die Quellinstanz leer.

| Quellmodul | Koordinaten | Messzeit |
| --- | --- | --- |
| **Smartcar** | `Latitude`, `Longitude` | `Sig_location_preciselocation_OEMUpdatedAt` als Unix-Sekunden. Aktiviere in Smartcar die zusätzliche OEM-Aktualisierungszeit für das Standortsignal. |
| **Hyundai Bluelink / Kia Connect** | `Latitude`, `Longitude` | `PositionTimestamp` als Text im Format `JJJJMMTThhmmss` in der Symcon-Zeitzone. |
| **Volvo** | `CurrentLatitude`, `CurrentLongitude` | `LastPositionMessage` als Unix-Sekunden. |
| **Renault Z.E.** | `GPSLatitude`, `GPSLongitude` | `GPSUpdate` als Unix-Sekunden. Aktiviere die drei GPS-Variablen im Renault-Modul. Das Modul füllt sie bei Fahrzeugen der Phase 2. |
| **Tronity** | `Latitude`, `Longitude` | `Timestamp` als Unix-Sekunden. Das Tronity-Modul rechnet die Zeit des Positionsdatensatzes bereits von Millisekunden um. |

Die Quellmodule schreiben die Koordinaten vor der neuen Messzeit.
OpenWoIst übernimmt bei einer neuen gültigen Messzeit die vollständige
Position. Bei ungültiger oder durch die Zeitumstellung mehrdeutiger Messzeit
bleibt der letzte gültige Standort sichtbar. Beim Hyundai/Kia-Modul kann eine
fehlende Messzeit als Abrufzeit im ISO-Format erscheinen. OpenWoIst verwirft
dieses Zeitformat für Hyundai/Kia. Kartenpositionen können je nach
Fahrzeug und Dienst zuletzt gemeldete Parkpositionen sein.

Beim Modulupdate erscheinen bisherige Zuordnungen in den jeweiligen
Anbieterlisten. Namen, Variablen und Markerfarben werden übernommen.

## Adressen und Zonen

Unter **Adressauflösung** kannst du einen eigenen Nominatim-Server oder
Geoapify auswählen. Bei Nominatim trägst du die Basis-URL ein. Geoapify
benötigt einen eigenen API-Schlüssel. Das Tageslimit gilt je OpenWoIst-Instanz
und wechselt um Mitternacht UTC. Die Auflösung ist anfangs ausgeschaltet.
Bei Aktivierung erhält der gewählte Dienst die Koordinaten. OpenWoIst
fragt aktuelle Positionen im Hintergrund ab und zeigt gespeicherte Adressen
in der Geräteliste sowie am Marker. Bei unbekannter Genauigkeit wird
höchstens der Ort angezeigt. [Nominatim-Richtlinie](https://operations.osmfoundation.org/policies/nominatim/) · [Geoapify](https://www.geoapify.com/reverse-geocoding-api/)

Unter **Zonen** legst du Kreise mit Name, Breitengrad, Längengrad und Radius
in Metern an. Die Kreise erscheinen in Kachel und IPSView. Bei überlappenden
Zonen hat der erste passende Eintrag Vorrang. Unter **Standortqualität** kannst
du Grenzpuffer, maximale Standortunsicherheit und Bestätigungszeit einstellen.
Der Radius jeder Zone muss größer als der Grenzpuffer sein. Veraltete und zu
ungenaue Positionen oder Positionen ohne gemeldete Genauigkeit ergeben
**Zone unbestimmt**. Zonenwechsel werden anhand neuer Messungen bestätigt.

## Öffentliche Funktionen

| Funktion | Verhalten |
| --- | --- |
| `OWIS_Update(int $InstanceID): void` | Liest die zugewiesenen lokalen Quellen und aktualisiert die Kachel bei Änderungen. |
| `OWIS_ResolveAddresses(int $InstanceID): void` | Verarbeitet bei aktiver Adressauflösung höchstens eine noch nicht aufgelöste aktuelle Position. |
| `OWIS_NextDemoPosition(int $InstanceID): bool` | Schreibt den nächsten synthetischen Standort in die Demo-Variable. Liefert `true`, wenn der Schreibvorgang gelungen ist. |
| `OWIS_GetIPSViewHTML(int $InstanceID): string` | Gibt die HTML-Einbindung mit dem für die Instanz gültigen Zugriffsschlüssel zurück, sofern die Ausgabe aktiv ist. |
