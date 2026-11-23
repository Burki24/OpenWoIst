# Unveröffentlicht

## Fixes

- OpenWoIst-Instanzen lassen sich unter Symcon 9.1 anlegen und konfigurieren.
- Bei geringer Höhe bleiben Karte und scrollbare Objektliste nebeneinander
  sichtbar. Die Bedienelemente passen in eine Zeile.

## Neu

- Das Quellenformular ist in **Fahrzeuge**, **iCloud** und **GoogleCloud**
  gegliedert. Die iCloud-Liste zeigt die Geräte der zugewiesenen Kontocaches.
- Einzelne Geräte und Fahrzeuge erhalten eigene Markerfarben in Kachel und
  IPSView. Die Designer liefern die Standardfarben.
- Die iCloud-Geräteauswahl begrenzt Karte und Objektliste auf ausgewählte
  Geräte aus den zugewiesenen Kontocaches.
- Unter **Weitere Fahrzeuge** lassen sich mehrere Fahrzeuge mit eigenen
  Symcon-Variablen hinzufügen und verwalten.
- Die Fahrzeug-Voreinstellungen für Smartcar und Hyundai Bluelink / Kia Connect
  übernehmen Koordinaten und Messzeiten aus den vorhandenen Symcon-Variablen.
- Weitere Fahrzeug-Voreinstellungen lesen die Positionsvariablen von Volvo,
  Renault Z.E. und Tronity.
- OpenWoIst zeigt Apple-Geräte aus ausgewählten iCloud-Konten und ein Fahrzeug
  aus Symcon-Variablen auf derselben Kachel.
- Ein Testfahrzeug mit vier synthetischen Positionen lässt sich über das
  Instanzformular bewegen.
- Die Fahrzeugquelle liest BMW-CarData-, OwnTracks- und Traccar-JSON
  direkt ein. Das Formular zeigt die benötigten Eingaben zur Quellenart
  und bietet eine Prüfung der übernommenen Zuordnung.
- Die Kachel zeigt Messalter und letzte bekannte Positionen an. Der Quellenfilter
  begrenzt die angezeigten Marker und Listeneinträge.
- Dieselbe Standortkarte steht optional als WebContent für IPSView bereit.
- Kachel und IPSView haben eigene Designer für Schrift, Farben und Marker
  sowie jeweils eine Farbkarte mit Legende. IPSView kann das OpenFreeMap-Profil
  der Kachel übernehmen oder ein eigenes Profil verwenden.
