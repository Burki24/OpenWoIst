# OpenWoIst

Die Kachel zeigt Geräte aus den zugewiesenen iCloud-Konten und Fahrzeuge aus
Symcon-Variablen. Beide Quellenarten lassen sich auch einzeln verwenden.

## Einrichten und mit dem Testfahrzeug ausprobieren

1. Eine Instanz **OpenWoIst** anlegen. Unter **iCloud → iCloud-Konten** die
   Konto-Instanzen aus SymconiCloud auswählen. Voraussetzung für Apple-Positionen
   ist ein gefüllter Gerätestand im ausgewählten Konto.
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

## OwnTracks-Geräte hinzufügen

Unter **Weitere Standortgeräte → Standortgeräte** pro Gerät einen Eintrag
mit **JSON-Format → OwnTracks** anlegen. Ordne die String-Variable **Position**
einer OwnTracks-Data-Instanz mit aktivierter Positionsübernahme oder eine
String-Variable mit OwnTracks-JSON zu. Das JSON enthält `lat`, `lon`, `tst` als
Unix-Sekunden und optional `acc` in Metern. Geräte können je einen Anzeigenamen
und eine Markerfarbe erhalten.
Ohne ausgewählte Farbe greift die Gerätefarbe aus dem jeweiligen Designer.

## Home Assistant und MQTT-GPS anschließen

Beide Beispiele schreiben die vollständige Position als eine MQTT-Nachricht.
Verwende einen Broker, den der Sender und Symcon erreichen. Bei einem externen
Broker abonniert der [MQTT Client in Symcon](https://www.symcon.de/de/service/dokumentation/modulreferenz/geraete/mqtt/mqtt-client/)
das jeweilige Topic. Beim [MQTT-Server in Symcon](https://www.symcon.de/de/service/dokumentation/modulreferenz/geraete/mqtt/mqtt-server/)
sendet die Quelle direkt dorthin. Sende eine erste Meldung auf das gewünschte
Topic. Lege es über den MQTT-Konfigurator mit einer Nutzdatenvariable vom Typ
**String** an. Wähle in OpenWoIst unter **Weitere Standortgeräte → Standortgeräte**
das JSON-Format **OpenWoIst (Home Assistant / MQTT-GPS)** und ordne die jeweilige
String-Variable zu.

### Home Assistant

Diese Automation setzt voraus, dass eine GPS-Entität `latitude`, `longitude`
und `fix_timestamp` gemeinsam als Attribute liefert. `fix_timestamp` ist die
ursprüngliche Messzeit in Unix-Sekunden. Passe die Entitäts-ID und bei Bedarf
den Namen des Zeitfelds an deine Integration an. Der Zeitpunkt eines
Home-Assistant-Zustandsupdates kann von der GPS-Messzeit abweichen.
[Triggerdaten](https://www.home-assistant.io/docs/automation/trigger-data/) ·
[MQTT](https://www.home-assistant.io/integrations/mqtt/)

```yaml
alias: OpenWoIst GPS-Position weitergeben
triggers:
  - trigger: state
    entity_id: device_tracker.gps_test
conditions:
  - condition: template
    value_template: >-
      {% set p = trigger.to_state.attributes if trigger.to_state is not none else {} %}
      {{ p.latitude is number and p.latitude is not boolean and -90 <= p.latitude <= 90
         and p.longitude is number and p.longitude is not boolean and -180 <= p.longitude <= 180
         and p.fix_timestamp is integer and p.fix_timestamp > 0 }}
actions:
  - action: mqtt.publish
    data:
      topic: openwoist/demo/home_assistant
      payload: >-
        {% set p = trigger.to_state.attributes %}
        {{ {'latitude': p.latitude, 'longitude': p.longitude,
            'timestamp': p.fix_timestamp} | to_json }}
```

Für einen Test kannst du in den [Entwicklerwerkzeugen](https://www.home-assistant.io/docs/tools/dev-tools/#states)
den Zustand der separaten Testentität `device_tracker.gps_test` mit den
drei Attributen setzen, zum Beispiel `latitude: 52.51453`,
`longitude: 13.35010` und `fix_timestamp` als aktuelle ganze Unix-Sekunde.
Sende anschließend einen zweiten Zustand mit anderen Koordinaten und einer
höheren Messzeit. Symcon empfängt die Meldungen auf `openwoist/demo/home_assistant`.

### MQTT-GPS

Ein MQTT-GPS-Sender kann direkt auf `openwoist/demo/gps` veröffentlichen.
Die folgende Nachricht enthält synthetische Testdaten.

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

Unter **Weitere Fahrzeuge** pro Fahrzeug einen Eintrag mit Namen hinzufügen.
Die Liste zeigt die Namen und Quellenarten der hinzugefügten Fahrzeuge.
Die zuvor konfigurierte Einzelquelle steht weiterhin unter **Bisherige
Fahrzeugquelle und Testfahrzeug**.

- **JSON-Variable** zeigt einen Variablenselektor und die Wahl des
  **JSON-Formats**. Das Format **OpenWoIst** erwartet `latitude` und
  `longitude` in Grad, `timestamp` als ganze Unix-Sekunde und optional
  `horizontalAccuracy` in Metern.
- **Einzelne Positionsvariablen** erwartet zwei numerische Koordinatenvariablen
  und eine ganzzahlige Messzeitvariable. Der Datenlieferant setzt die
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

### BMW CarData, OwnTracks und Traccar anschließen

Wähle **JSON-Variable** und das passende **JSON-Format**. Ordne unter
**Positionsvariable (JSON)** die String-Variable mit den Originaldaten zu.
OpenWoIst konvertiert sie beim Einlesen.

Für BMW kann die Variable das Ergebnis von `BMW_getTelematicData`,
eine CarData-REST-Antwort oder eine Stream-Meldung enthalten. Beide
Koordinaten müssen denselben Messzeitpunkt haben. OwnTracks liefert
`lat`, `lon`, `tst` und optional `acc` in Metern.

Traccar unterstützt ein einzelnes Positionsobjekt und Weiterleitungen mit
`position`. OpenWoIst verwendet `fixTime` als Messzeit und übernimmt
optional `accuracy` in Metern. Mit `valid: false` markierte Positionen
werden verworfen.

Unter **Erweiterte Fahrzeugeinstellungen** gilt ISO 8601 mit Zeitzone
als Standard für BMW und Traccar und Unix-Sekunden für OwnTracks.
Unix-Millisekunden und das Dezimalkomma sind bei Bedarf wählbar.
Ungültige oder ältere Meldungen lassen die letzte gültige Position auf
der Karte stehen. Bei gleicher Messzeit bleiben die bisherigen Koordinaten
erhalten.

**Übernommene Quelle prüfen** liest die gespeicherte Zuordnung und
meldet das Messalter oder ein fehlendes bzw. ungültiges Feld.

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
3. Wähle in OpenWoIst unter **Fahrzeuge → Weitere Fahrzeuge** die Quelle
   **JSON-Variable** und das Format **Traccar**. Ordne die String-Variable zu.

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

### Fahrzeug-Voreinstellungen verwenden

Wähle unter **Weitere Fahrzeuge** die passende **Fahrzeugquelle** und
ordne die drei Variablen aus derselben Fahrzeuginstanz zu.

| Voreinstellung | Koordinaten | Messzeit |
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
dieses Zeitformat für die Voreinstellung. Kartenpositionen können je nach
Fahrzeug und Dienst zuletzt gemeldete Parkpositionen sein.

## Öffentliche Funktionen

| Funktion | Verhalten |
| --- | --- |
| `OWIS_Update(int $InstanceID): void` | Liest die zugewiesenen lokalen Quellen und aktualisiert die Kachel bei Änderungen. |
| `OWIS_NextDemoPosition(int $InstanceID): bool` | Schreibt den nächsten synthetischen Standort in die Demo-Variable. Liefert `true`, wenn der Schreibvorgang gelungen ist. |
| `OWIS_GetIPSViewHTML(int $InstanceID): string` | Gibt die HTML-Einbindung mit dem für die Instanz gültigen Zugriffsschlüssel zurück, sofern die Ausgabe aktiv ist. |
