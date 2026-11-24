# OpenWoIst Tractive

Der Adapter übernimmt Koordinaten und Messzeit aus den lokalen Variablen
einer vorhandenen Tractive-GPS-Geräteinstanz und fasst sie für die Karte
in einer Symcon-Variable zusammen.

## Voraussetzungen

- IP-Symcon ab 9.0 und ein bereits eingerichteter Tracker aus
  [Tractive GPS im Symcon Module Store](https://www.symcon.de/de/module-store/demel41-tractivegps/).
- Geprüfte Variablenverträge des Tractive-GPS-Moduls sind 1.14 und 1.19 Beta.

## Einrichtung

1. Für jeden Tracker eine eigene Instanz **OpenWoIst Tractive** anlegen.
2. Unter **Tractive-GPS-Geräteinstanz** das vorhandene Gerät auswählen
   und die Einstellungen übernehmen. Die erste Position erscheint nach
   dem nächsten vollständigen Positionsbericht.
3. In der OpenWoIst-Karteninstanz unter **Weitere Standortgeräte** einen
   Eintrag mit **OpenWoIst-JSON** erstellen und
   **Position (OpenWoIst-JSON)** aus der Adapterinstanz zuordnen.

Tractive GPS bestimmt das Abrufintervall. OpenWoIst reagiert auf die lokal
geschriebenen Variablen. Beim Umstieg vom bisherigen Adapter bleiben
Gerätezuordnung und ID der Ausgabevariable erhalten. Alte Timer werden
deaktiviert und die Ausgabe füllt sich beim nächsten vollständigen Bericht neu.

Verwende beim Wechsel zu einem anderen Tracker eine eigene Adapterinstanz
und deren Positionsvariable. OpenWoIst merkt sich die letzte bekannte
Position je zugeordneter Variablen-ID.

## Ausgabe und Bedienung

**Position (OpenWoIst-JSON)** ist eine schreibgeschützte String-Variable
(Ident `Position`). Sie enthält `latitude` und `longitude` in Dezimalgrad
sowie `timestamp` in Unix-Sekunden. `LastUpdate` schließt den Bericht ab.
Die Messzeit stammt aus `LastPositionMessage`. Eine im selben Bericht
gemeldete `PositionUncertainty` wird als `horizontalAccuracy` übernommen.
Bei unvollständigen oder überlappenden Berichten bleibt die letzte gültige
Position mit ihrer ursprünglichen Messzeit erhalten.

**Zugewiesenes Gerät prüfen** und `OWIT_Update($InstanceID): void`
aktualisieren die lokalen Variablenzuordnungen des Adapters.

Der Adapter steht unter der [PolyForm Noncommercial License 1.0.0](../LICENSE).
