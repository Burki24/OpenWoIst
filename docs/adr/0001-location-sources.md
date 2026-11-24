# ADR 0001 – Karte und Standortquellen

Status angenommen für die erste Ausbaustufe. Der Ausgabeweg wird durch
[ADR 0002](0002-visualization.md) um IPSView ergänzt.

OpenWoIst ist eine eigene Symcon-Bibliothek mit einem Modul. Alle
Standortquellen einer Karte werden in derselben OpenWoIst-Instanz eingerichtet.
Ein zugewiesenes iCloud-Konto
liefert seinen lokalen Gerätestand über `ICAC_GetDevices`. Eine
Fahrzeugintegration kann ihre Messungen über Symcon-Variablen bereitstellen.
Damit hängt die Karteninstanz für Fahrzeugdaten allein von Symcon ab.
Ein Helper in der OpenWoIst-Library normalisiert BMW-CarData-, OwnTracks-
und Traccar-Daten aus zugewiesenen JSON-Variablen beim Einlesen.
Gespeicherte Anbieterwerte in `VehicleMode` gelten weiter, solange kein
eigenes JSON-Format gewählt wird.

Standortquellen werden direkt an Symcon oder über Symcon-Quellmodule
angebunden. Konkurrierende Hausautomationssysteme wie Home Assistant
sind weder als Datenquelle noch als Vermittler vorgesehen. MQTT dient
als Transport zwischen Standortanbieter oder Gerät und Symcon.

Für Garmin Connect LiveTrack ruft ein Library-eigener Helper die ausdrücklich
freigegebene Sitzung direkt ab. Ein eigener Timer trennt die HTTP-Abfrage vom
Lesen lokaler Quellen. Die gespeicherte Zeilenkennung verbindet aufeinander-
folgende Sitzungen mit demselben Kartenobjekt. Freigabelinks und Zugriffstoken
bleiben in der Instanzkonfiguration und der Anfrage an Garmin. Kachel und
IPSView erhalten nur den normalisierten Standortzustand. Der Webabruf nutzt
die undokumentierte Garmin-Webschnittstelle. [Referenz zum Webabruf](https://github.com/tjuuljensen/ha-garmin-livetrack/blob/main/docs/GARMIN_FETCH_STRATEGY.md)

Bei bekannten Quellmodulen mit verlässlichem Variablenvertrag wählen Benutzer
die vorhandenen Geräteinstanzen in einer Liste. OpenWoIst ermittelt die
Variablen-IDs anhand der Idents und prüft die Typen. Je Instanz sind eigene
Einstellungen wie die Markerfarbe möglich. Das Quellmodul betreibt den
Anbieterzugriff.

Die Quellmodule haben eigene Listen im Konfigurationsformular. Die bisherigen
benannten Einträge aus den gemeinsamen Listen und der einzelnen Fahrzeugquelle
werden beim Laden der gespeicherten Konfiguration den neuen Listen zugeordnet.
Dabei bleiben die Variablen-IDs und die Kartenidentität erhalten. BMW CarData
und Traccar übernehmen vollständige Positionsberichte aus JSON-Variablen.

Für Tractive verarbeitet die Karte Variablenaktualisierungen pro ausgewählter
Geräteinstanz. `LastUpdate` markiert den Abschluss, die Messzeit stammt aus
`LastPositionMessage`. Unvollständige oder überlappende Folgen werden verworfen.
Der frühere Tractive-Adapter aus dem Entwicklungsstand entfällt. Dessen
Testzuordnungen können vor dem Update auf die direkte Geräteauswahl umgestellt
werden. Der allgemeine Import aus zugewiesenen Variablen bleibt für Quellen
ohne verlässlichen Modulvertrag verfügbar.

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
