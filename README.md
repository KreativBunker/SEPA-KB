# SEPA XML Tool (sevdesk to pain.008)
Dieses Projekt ist eine kleine PHP Web App, die aus sevdesk Rechnungen eine SEPA Lastschrift XML Datei (pain.008.001.02) erzeugt.
Die App ist für Shared Hosting gedacht und bringt ein Setup beim ersten Aufruf mit.

## Installation
1. Inhalt dieses Ordners auf dein Webhosting hochladen
2. Webroot muss auf den Ordner `public` zeigen
3. Im Browser die Domain aufrufen, Setup startet automatisch, wenn noch nicht installiert

## Hinweise
- Bei Problemen mit der sevdesk API Token Auth kann in den sevdesk Einstellungen in der App der Header Modus umgestellt werden.
- Exporte liegen im Ordner `storage/exports` und werden nur über die App ausgeliefert.


## Update Hinweis
Wenn du Updates einspielst, überschreibe nicht deine Datei `config/installed.lock`. Darin stehen DB Zugangsdaten und app_key.

## Schreibrechte
Der Ordner `storage/exports` muss existieren und schreibbar sein. Das Projekt legt den Ordner beim Export automatisch an.


## SEPA Version
Dieses Tool erzeugt SEPA-Lastschriften im Format **pain.008.001.08** (ISO 2019 / DK-TVS). Das ältere pain.008.001.02 wurde nur befristet unterstützt und wird von deutschen Banken inzwischen nicht mehr akzeptiert.
