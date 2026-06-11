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


## Mahnwesen-Automatik
Die App kann Zahlungserinnerungen und Mahnungen automatisch erzeugen und versenden:
- 7 Tage nach Fälligkeit (einstellbar) wird eine Zahlungserinnerung als Mahnbeleg in sevdesk erzeugt und per E-Mail (SMTP/M365) an den Kunden versendet, danach im gleichen Rhythmus 1. und 2. Mahnung.
- Ab der 2. Mahnung erscheint die Rechnung im Mahnwesen zur manuellen Inkasso-Übergabe.
- Rechnungen mit Zahlungsart SEPA-Lastschrift oder aktivem Mandat werden ausgenommen (einstellbar), zusätzlich gibt es eine Ausschlussliste je Rechnung/Kontakt.
- Modus „Mit Freigabe": der Lauf merkt Mahnungen nur vor, der Versand erfolgt nach Freigabe unter „Mahnautomatik". Modus „Vollautomatisch": der Cron-Lauf versendet direkt.

Aktivierung unter Einstellungen → Mahnwesen-Automatik. Der Mahnlauf muss einmal täglich angestoßen werden, wahlweise:

**Variante A – Hosting-Cronjob (CLI):**
```
15 7 * * * php /pfad/zu/SEPA-KB/bin/dunning_cron.php >> /pfad/zu/SEPA-KB/storage/logs/dunning_cron.log 2>&1
```
(Je nach Hoster heißt das PHP-Binary z.B. `php8.2`.)

**Variante B – Webcron:** Die in den Einstellungen angezeigte URL `https://deine-domain.tld/cron/dunning/<token>` täglich aufrufen lassen (Hosting-Cronjob mit `curl -fsS <url>` oder Dienste wie cron-job.org). Das Token wird beim ersten Speichern der Einstellungen erzeugt und kann dort neu generiert werden.

## SEPA Version
Dieses Tool erzeugt SEPA-Lastschriften im Format **pain.008.001.08** (ISO 2019 / DK-TVS). Das ältere pain.008.001.02 wurde nur befristet unterstützt und wird von deutschen Banken inzwischen nicht mehr akzeptiert.
