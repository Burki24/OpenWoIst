# ADR 0001 – Karte und Standortquellen

Status angenommen für die erste Ausbaustufe. Der Ausgabeweg wird durch
[ADR 0002](0002-visualization.md) um IPSView ergänzt.

OpenWoIst ist eine eigene Symcon-Bibliothek. Ein zugewiesenes iCloud-Konto
liefert seinen lokalen Gerätestand über `ICAC_GetDevices`. Eine
Fahrzeugintegration kann ihre Messungen über Symcon-Variablen bereitstellen.
Damit hängt die Karteninstanz für Fahrzeugdaten allein von Symcon ab.
Ein Helper in der OpenWoIst-Library normalisiert BMW-CarData-, OwnTracks-
und Traccar-Daten aus zugewiesenen JSON-Variablen beim Einlesen.
Gespeicherte Anbieterwerte in `VehicleMode` gelten weiter, solange kein
eigenes JSON-Format gewählt wird.

Für Tractive sammelt die optionale Adapterinstanz Variablenaktualisierungen
einer ausgewählten Tractive-GPS-Geräteinstanz. `LastUpdate` markiert den
Abschluss. Die Messzeit stammt aus `LastPositionMessage`. Unvollständige
oder überlappende Folgen werden verworfen. Das Quellmodul übernimmt seine
Cloud-Abfragen wie bisher. Austauschformat für die Karte bleibt die
normalisierte Positionsvariable mit derselben ID.

Die Geräteauswahl speichert dieselben kontobezogenen, opaken Kennungen wie
der Kartenstand. Ohne aktivierte Einschränkung erscheinen weiterhin alle Geräte.
Weitere Fahrzeuge haben eigene Variablenzuordnungen. Die bisherige Einzelquelle
und die Testfahrzeug-Variable behalten ihre Eigenschaften und Kennungen.

Die Kachel erhält ausschließlich einen normalisierten Zustand mit opaken
Objekt- und Quellenkennungen. Quellenstatus und Standortzeit sind getrennt.
Abgemeldete oder abgewählte Konten werden aus dem gespeicherten Zustand
entfernt. Der WebHook stellt den statischen Renderer bereit. Standortdaten
fließen über das native HTML SDK.

OpenWoIst und SymconiCloud verwenden die zentralen Karten-, Layout- und
Interaktions-Helper. Die OpenWoIst-Kachel besitzt eine eigene Oberfläche
für den gemeinsamen Standortzustand.

Die optionale Adressauflösung ergänzt diesen Standortzustand aus dem
zentralen Reverse-Geocoding-Cache. Ein eigener Timer löst aktuelle,
zugewiesene Positionen über den gewählten Dienst auf. Die Zonenauswertung
verwendet den zentralen GeofenceHelper und speichert ihren Zustand je
Objekt und Quellkonfiguration. Beide Ausgaben erhalten denselben
Standortzustand. Anbieterzugänge und interne Zonenzustände bleiben auf
dem Server.
