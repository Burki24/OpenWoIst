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

Für Tractive übernimmt eine optionale Adapterinstanz Koordinaten und
Messzeit aus derselben Antwort der zugewiesenen Tractive-I/O-Instanz. Die
Karte liest nur die normalisierte Positionsvariable. Der Adapter verwendet
den lokalen Symcon-Datenfluss mit einer expliziten Tracker-Zuordnung.
Ein Abruf verursacht einen zusätzlichen Tractive-Request. Standard ist der
manuelle Abruf. Der Adapter enthält eigenen Code und verwendet die für die
Kompatibilität erforderlichen Schnittstellenkennungen des Quellmoduls.
Das Quellmodul steht unter
[CC BY-NC-SA 4.0](https://github.com/demel42/IPSymconTractiveGPS/blob/main/LICENSE.md).
Die [Tractive-AGB](https://assets.tractive.com/static/legal/de/terms-of-service.pdf)
verlangen eine bestimmungsgemäße Nutzung. Tractive beschreibt
[Smart-Home-Integrationen](https://tractive.com/blog/de/technologie/lassen-sich-haustier-tracker-in-smart-home-geraete-oder-sprachassistenten-integrieren).
Eine ausdrückliche Aussage von Tractive zu diesem konkreten Abrufweg liegt
der Entscheidung nicht zugrunde.

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
