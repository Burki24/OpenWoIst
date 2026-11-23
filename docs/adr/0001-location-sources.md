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

Die Kachel erhält ausschließlich einen normalisierten Zustand mit opaken
Objekt- und Quellenkennungen. Quellenstatus und Standortzeit sind getrennt.
Abgemeldete oder abgewählte Konten werden aus dem gespeicherten Zustand
entfernt. Der WebHook stellt den statischen Renderer bereit. Standortdaten
fließen über das native HTML SDK.

OpenWoIst und SymconiCloud verwenden die zentralen Karten-, Layout- und
Interaktions-Helper. Die OpenWoIst-Kachel besitzt eine eigene Oberfläche
für den gemeinsamen Standortzustand.
