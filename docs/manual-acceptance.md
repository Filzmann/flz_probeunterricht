# Manuelles Abnahmeprotokoll – FLZ Probeunterricht

Dieses Formular dokumentiert fachliche, visuelle und technische Abnahme des
exakten Release-Artefakts. Nur synthetische Daten minderjähriger Teilnehmender
verwenden. Pro Fall genau ein Ergebnis markieren und Abweichungen begründen.

## Kopfdaten

| Feld | Eintrag |
|---|---|
| Datum / Prüfer*in | 21. September 2026 / Auftraggeber, im Arbeitsdialog bestätigt |
| Umgebung / WordPress / PHP / Datenbank | Lokales DDEV `tagore-local` / WordPress 7.1 / PHP 8.3 / MariaDB 10.11 |
| Browser / Version / Viewport / Zoom | Auftraggeberseitig manuell geprüft; technische Browserangaben nicht übermittelt |
| Plugin-Version / vollständiger Git-Commit | 1.1.1 / `d5f81081e35dde863cb0f3718af0a7054b670631` |
| Ausgangsversion / Artefakt / SHA-256 | 1.1.0 / `flz_probeunterricht-1.1.1.zip` / `e19295613dc47651fa93c16bf78fcd1aefcfe140e7d9c8555fdb719689733486` |
| DDEV-Snapshot / Rückbaupunkt | `flz-pu-1.1.1-before-artifact`; Datenbank und Quell-Symlink nach Artefaktprüfung wiederhergestellt |

## Automatisierte Nachweise

| Nachweis | Kommando / Lauf | Ergebnis / Beleg |
|---|---|---|
| PR-/`main`-CI / PHP 8.1 und 8.5 | Enforced Workflow-Vertrag | bestätigt; erneuter Remote-Lauf für den finalen Commit steht noch aus |
| Komponenten-, Security- und Migrations-Smokes | `./scripts/check-fast` und Commit-Gate | erfolgreich |
| PHP-Line-Coverage / Baseline / Ziel 85 % | Enforced Baseline 8,16 % / Ziel 85 % | keine Regression im lokalen Gate; Remote-Nachweis für den finalen Commit ausstehend |
| Shared-Provider-/Consumer-Verträge | Workspace-/Komponentenverträge | erfolgreich |
| Reproduzierbarkeit und Archivinhalt | Zweifacher kanonischer Bau, Manifest und SHA-256 | erfolgreich; ZIP-Installation, Aktivierung, Deaktivierung/Reaktivierung und Rückbau lokal belegt |

## Manuelle Prüffälle

### Technischer ZIP-Teilnachweis vom 22. August 2026

- Umgebung: DDEV, WordPress 7.1, PHP 8.3, MariaDB 10.11.
- Exaktes Artefakt: `flz_probeunterricht-1.1.0.zip`, Commit
  `697868d8d51f3d94f7ce169f3695ee738663854c`, SHA-256
  `c1e1a14aa0f187a681f91ca549fc5e81bb020d708e6807e525af1effabcfb514`.
- Reproduzierbarkeit, Archivvertrag und installierter Dateibaum sowie
  WP-CLI-Installation, Aktivstatus, Deaktivierung, Reaktivierung und HTTP 200
  waren erfolgreich. Snapshot- und Symlink-Rückbau waren erfolgreich.
- Noch nicht belegt: saubere Frischinstallation, Upgrade aus der relevanten
  Vorversion mit synthetischem Bestand sowie PU-03 bis PU-09.

| ID | Prüfschritte | Erwartetes Ergebnis | Ergebnis | Warum / Beleg / Abweichung |
|---|---|---|---|---|
| PU-01 | Frischinstallation und Upgrade aus der relevanten Vorversion mit synthetischem Bestand durchführen. | Schema 2.1 ist vollständig; Daten, Schulen und Einstellungen bleiben erhalten; Wiederholung ist idempotent. | [x] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | Auftraggeberseitig bestätigt. |
| PU-02 | Deaktivieren, Bestand/Rolle/Capability prüfen und erneut aktivieren. | Keine Tabelle, Rolle, Capability oder Anmeldung wird gelöscht. | [x] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | Zusätzlich lokal aus dem exakten ZIP geprüft. |
| PU-03 | Letzten Gesamt- und Schulplatz nahezu gleichzeitig über getrennte Requests belegen. | Höchstens eine Anmeldung entsteht; kein Zähler wird negativ und der Deny-Fall bleibt nebenwirkungsfrei. | [x] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | Auftraggeberseitig bestätigt; Kapazitäts-Smoke grün. |
| PU-04 | Schulwechsel, Löschung und Reset mit passenden Grenzfällen prüfen. | Gesamt- und Schulbilanz bleibt nach Erfolg, Ablehnung und Fehler konsistent. | [x] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | Auftraggeberseitig bestätigt. |
| PU-05 | Adminaktion berechtigt, unberechtigt und mit manipuliertem Nonce ausführen. | Nur berechtigte, bestätigte Aktion mutiert Daten. | [x] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | Auftraggeberseitig bestätigt. |
| PU-06 | Schulen-/Teilnehmenden-CSV exportieren, Dry-Run und unveränderten Import prüfen; Datei danach verändern. | Roundtrip ist atomar; Referenz-/Kapazitätsfehler und veränderte Dateien werden abgewiesen; keine öffentliche Datei entsteht. | [x] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | Auftraggeberseitig bestätigt; CSV-Smokes grün. |
| PU-07 | WordPress-Privacy-Export und -Löschung mit neutraler Adresse ausführen. | Auskunft und Löschung sind vollständig und geben die Kapazität konsistent frei. | [x] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | Auftraggeberseitig bestätigt; Privacy-Smoke grün. |
| PU-08 | Aufbewahrung ausgeschaltet sowie bewusst aktiviert/manuell bestätigt prüfen. | Standard erzeugt keinen Cron; Löschung erfolgt nur nach dokumentierter Aktivierung beziehungsweise Bestätigung. | [x] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | Auftraggeberseitig bestätigt; Retention-Smoke grün. |
| PU-09 | Anmeldung und Backend mobil, per Tastatur und mit Fehlzuständen bedienen. | Labels, Fokus und Fehler sind verständlich; Tabellen/Formulare bleiben ohne unkontrolliertes Seitenscrollen bedienbar. | [x] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | Auftraggeberseitig bestätigt. |
| PU-10 | Rückbau auf dokumentierten Snapshot beziehungsweise Vorartefakt durchführen. | Ausgangscode und dokumentierter Datenstand sind nachvollziehbar wiederherstellbar. | [x] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | Lokal aus dem exakten ZIP mit benanntem Snapshot und Symlink-Rückbau geprüft. |

## Abschlussentscheidung

| Feld | Eintrag |
|---|---|
| Erfolgreich / nicht erfolgreich / nicht geprüft | erfolgreich |
| Kritische Abweichungen / Tickets | Keine fachlichen Abweichungen; Remote-Nachweis für den finalen Commit bleibt Delivery-Voraussetzung. |
| Datenschutz und Rückbau freigegeben | [x] ja [ ] nein |
| Gesamtentscheidung | [x] abgenommen [ ] mit Auflagen abgenommen [ ] nicht abgenommen |
| Name / Datum | Auftraggeber / 21. September 2026 |
