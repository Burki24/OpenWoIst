# Unveröffentlicht

## Fixes

- Im Formular lassen sich Garmin-LiveTrack-Einträge über **Hinzufügen** anlegen.
- OpenWoIst bietet ein einziges Modul für die Karteninstanz an.
- OpenWoIst-Instanzen lassen sich unter Symcon 9.1 anlegen und konfigurieren.
- Bei geringer Höhe bleiben Karte und scrollbare Objektliste nebeneinander
  sichtbar. Die Bedienelemente passen in eine Zeile.
- Die Fahrzeugliste bietet **Eine JSON-Variable** oder **Drei Positionsvariablen**
  an. Unter **Weitere Standortgeräte** erklärt das Formular, welche Daten
  OpenWoIst-JSON benötigt.
- Die Anbieterbereiche erklären, wo eine Symcon-Instanz, ein Freigabelink
  oder eine Positionsvariable benötigt wird.

## Neu

- Unter **Fahrzeuge → Tessie** genügt die Auswahl einer vorhandenen
  Tessie-Fahrzeuginstanz. Die Karte zeigt den Standort mit seiner Empfangszeit.
- **IPS-Tesla** bietet eine Liste für vorhandene Tesla-Vehicle-Drive-Instanzen.
  OpenWoIst übernimmt Koordinaten und GPS-Messzeit automatisch, sobald der
  Fahrzeugstandort im zugehörigen Tesla Vehicle Connector aktiviert ist.
- **Tesla Fleet Telemetry** übernimmt Position und Zeitstempel aus
  einem vollständigen Fahrzeugbericht in einer Symcon-JSON-Variable.
- **Tessie** übernimmt die Fahrzeugposition aus einer Symcon-JSON-Variable
  mit der GPS-Messzeit `gps_as_of`.
- **PAJ GPS** lädt Geräte aus dem FINDER Portal unter **Tracker und
  Standortgeräte**. Jedes ausgewählte Gerät hat eine eigene Markerfarbe und
  zeigt seine PAJ-Messzeit.
- **Garmin inReach / MapShare** übernimmt je Geräte-IMEI den jüngsten
  Standort mit Garmin-Messzeit aus einem freigegebenen KML-Feed.
- Unter **Tracker und Standortgeräte → Garmin LiveTrack** lassen sich
  Garmin-Connect-Freigaben mit eigener Markerfarbe hinzufügen. Kachel und
  IPSView zeigen den jüngsten gemeldeten Punkt je Sitzung.
- Symcon zeigt für neue Entwicklungsstände aus `dev` eine fortlaufende
  Versionsnummer an.
- Optional ermittelte Adressen und einrichtbare Kreiszonen erscheinen in
  der Kachel und in IPSView. Die Zonenauswertung nutzt die Standortqualität.
- In der OpenWoIst-Instanz gibt es den eigenen Bereich **Tractive GPS**.
  Dort lassen sich mehrere vorhandene Geräteinstanzen direkt auswählen.
  Die Liste zeigt den Haustiernamen und die Karte übernimmt die
  Positionsvariablen automatisch.
- **Haustiere** gruppiert **Tractive GPS** und **Fressnapf Tracker**.
  Fressnapf-Geräte werden direkt aus der vorhandenen Symcon-Instanz ausgewählt.

- Das Quellenformular gruppiert die Quellen unter **Fahrzeuge**,
  **Apple und Google**, **Haustiere** sowie **Tracker und Standortgeräte**.
  Der jeweilige Modulbereich verlinkt zum Modul-Store oder zu GitHub.
- Einzelne Geräte und Fahrzeuge erhalten eigene Markerfarben in Kachel und
  IPSView. Die Designer liefern die Standardfarben.
- Die iCloud-Geräteauswahl begrenzt Karte und Objektliste auf ausgewählte
  Geräte aus den zugewiesenen Kontocaches.
- Unter **Fahrzeuge aus Variablen** lassen sich mehrere Fahrzeuge mit eigenen
  Symcon-Variablen hinzufügen und verwalten.
- Smartcar, Hyundai Bluelink / Kia Connect, Volvo, Renault Z.E., Tronity
  und OwnTracks haben eigene Listen für mehrere Geräteinstanzen. OpenWoIst
  findet ihre Positionsvariablen automatisch. BMW CarData und Traccar
  haben eigene Listen für ihre JSON-Quellen. Gespeicherte Zuordnungen
  erscheinen nach dem Modulupdate in den jeweiligen Listen.
- Standortgeräte können generisches OpenWoIst-JSON lesen. Die Modulanleitung
  zeigt die direkte Kopplung eines MQTT-GPS-Senders mit Symcon.
- OpenWoIst zeigt Apple-Geräte aus ausgewählten iCloud-Konten und ein Fahrzeug
  aus Symcon-Variablen auf derselben Kachel.
- Ein Testfahrzeug mit vier synthetischen Positionen lässt sich über das
  Instanzformular bewegen.
- Die Kachel zeigt Messalter und letzte bekannte Positionen an. Der Quellenfilter
  begrenzt die angezeigten Marker und Listeneinträge.
- Dieselbe Standortkarte steht optional als WebContent für IPSView bereit.
- Kachel und IPSView haben eigene Designer für Schrift, Farben und Marker
  sowie jeweils eine Farbkarte mit Legende. IPSView kann das OpenFreeMap-Profil
  der Kachel übernehmen oder ein eigenes Profil verwenden.
