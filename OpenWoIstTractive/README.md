# OpenWoIst Tractive

Der Adapter stellt die Koordinaten und die ursprüngliche Messzeit eines
Tractive-Trackers gemeinsam in einer Symcon-Variable für OpenWoIst bereit.

## Voraussetzungen

- IP-Symcon ab 9.0 und ein bereits eingerichteter Tracker aus
  [Tractive GPS im Symcon Module Store](https://www.symcon.de/de/module-store/demel41-tractivegps/).
- Unterstützte Versionen des Tractive-GPS-Moduls sind 1.14 und 1.19 Beta.
  Die Anbindung verwendet dessen interne I/O-Schnittstelle. Jeder Abruf löst
  eine zusätzliche Anfrage bei Tractive aus.

## Einrichtung

1. Für jeden Tracker eine eigene Instanz **OpenWoIst Tractive** anlegen.
2. Unter **Tractive-Tracker** das bestehende Gerät auswählen. Als übergeordnete
   Instanz dieselbe **Tractive-I/O-Instanz** wie beim Tracker verbinden.
3. Die Einstellungen übernehmen und **Position jetzt abrufen** wählen. Der
   Standard ist **Nur manuell**. Bei Bedarf ein automatisches Abrufintervall
   auswählen.
4. In der OpenWoIst-Karteninstanz unter **Weitere Standortgeräte** einen
   Eintrag mit **OpenWoIst (Home Assistant / MQTT-GPS)** erstellen und
   **Position (OpenWoIst-JSON)** aus der Adapterinstanz zuordnen.

Verwende beim Wechsel zu einem anderen Tracker eine eigene Adapterinstanz
und deren Positionsvariable. OpenWoIst merkt sich die letzte bekannte
Position je zugeordneter Variablen-ID.

## Ausgabe und Bedienung

**Position (OpenWoIst-JSON)** ist eine schreibgeschützte String-Variable
(Ident `Position`). Sie enthält `latitude` und `longitude` in Dezimalgrad
sowie `timestamp` in Unix-Sekunden. Bei ausbleibender oder ungültiger Antwort
bleibt die letzte gültige Position mit ihrer ursprünglichen Messzeit
erhalten.

`OWIT_Update($InstanceID): void` ruft die Position für die konfigurierte
Adapterinstanz ab.

Der Adapter steht unter der [PolyForm Noncommercial License 1.0.0](../LICENSE).
