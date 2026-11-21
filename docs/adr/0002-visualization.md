# ADR 0002 – Native Kachel und IPSView

Status angenommen.

Kachel und IPSView rendern denselben normalisierten Standortzustand. Die
native Kachel verwendet das HTML SDK. IPSView bindet eine HTTP(S)-Seite per
WebContent ein und liest den gespeicherten Stand über einen separaten
GET-Endpunkt. Die öffentliche Seite enthält eine leere Ausgangsansicht.
Zugriff auf Positionen und benutzerspezifische Profile erfordert einen
zufälligen Instanzschlüssel, den der Loader im URL-Fragment übergibt. Die
Seite entfernt das Fragment und verwendet den Schlüssel im HTTP-Header.
Änderungen der Quellen oder der Serveradresse sowie das Deaktivieren der
Ausgabe widerrufen den bisherigen Schlüssel.

Kachel und IPSView verwenden die kanonischen Theme-, Stil- und
OpenFreeMap-Helper. Die Grundkartenprofile können unabhängig konfiguriert
werden. Standardmäßig übernimmt IPSView das Profil der Kachel. Schrift,
Bedienelemente und Markerfarben sind ausgabespezifisch.
