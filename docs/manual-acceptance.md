# Manuelles Abnahmeprotokoll – FLZ Probeunterricht

Dieses Formular dokumentiert fachliche, visuelle und technische Abnahme des
exakten Release-Artefakts. Nur synthetische Daten minderjähriger Teilnehmender
verwenden. Pro Fall genau ein Ergebnis markieren und Abweichungen begründen.

## Kopfdaten

| Feld | Eintrag |
|---|---|
| Datum / Prüfer*in | |
| Umgebung / WordPress / PHP / Datenbank | |
| Browser / Version / Viewport / Zoom | |
| Plugin-Version / vollständiger Git-Commit | |
| Ausgangsversion / Artefakt / SHA-256 | |
| DDEV-Snapshot / Rückbaupunkt | |

## Automatisierte Nachweise

| Nachweis | Kommando / Lauf | Ergebnis / Beleg |
|---|---|---|
| PR-/`main`-CI / PHP 8.1 und 8.5 | Workflow-Lauf / vollständiger Commit | |
| Komponenten-, Security- und Migrations-Smokes | `./scripts/check-fast` | |
| PHP-Line-Coverage / Baseline / Ziel 85 % | | |
| Shared-Provider-/Consumer-Verträge | | |
| Reproduzierbarkeit und Archivinhalt | | |

## Manuelle Prüffälle

| ID | Prüfschritte | Erwartetes Ergebnis | Ergebnis | Warum / Beleg / Abweichung |
|---|---|---|---|---|
| PU-01 | Frischinstallation und Upgrade aus der relevanten Vorversion mit synthetischem Bestand durchführen. | Schema 2.1 ist vollständig; Daten, Schulen und Einstellungen bleiben erhalten; Wiederholung ist idempotent. | [ ] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | |
| PU-02 | Deaktivieren, Bestand/Rolle/Capability prüfen und erneut aktivieren. | Keine Tabelle, Rolle, Capability oder Anmeldung wird gelöscht. | [ ] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | |
| PU-03 | Letzten Gesamt- und Schulplatz nahezu gleichzeitig über getrennte Requests belegen. | Höchstens eine Anmeldung entsteht; kein Zähler wird negativ und der Deny-Fall bleibt nebenwirkungsfrei. | [ ] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | |
| PU-04 | Schulwechsel, Löschung und Reset mit passenden Grenzfällen prüfen. | Gesamt- und Schulbilanz bleibt nach Erfolg, Ablehnung und Fehler konsistent. | [ ] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | |
| PU-05 | Adminaktion berechtigt, unberechtigt und mit manipuliertem Nonce ausführen. | Nur berechtigte, bestätigte Aktion mutiert Daten. | [ ] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | |
| PU-06 | Schulen-/Teilnehmenden-CSV exportieren, Dry-Run und unveränderten Import prüfen; Datei danach verändern. | Roundtrip ist atomar; Referenz-/Kapazitätsfehler und veränderte Dateien werden abgewiesen; keine öffentliche Datei entsteht. | [ ] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | |
| PU-07 | WordPress-Privacy-Export und -Löschung mit neutraler Adresse ausführen. | Auskunft und Löschung sind vollständig und geben die Kapazität konsistent frei. | [ ] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | |
| PU-08 | Aufbewahrung ausgeschaltet sowie bewusst aktiviert/manuell bestätigt prüfen. | Standard erzeugt keinen Cron; Löschung erfolgt nur nach dokumentierter Aktivierung beziehungsweise Bestätigung. | [ ] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | |
| PU-09 | Anmeldung und Backend mobil, per Tastatur und mit Fehlzuständen bedienen. | Labels, Fokus und Fehler sind verständlich; Tabellen/Formulare bleiben ohne unkontrolliertes Seitenscrollen bedienbar. | [ ] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | |
| PU-10 | Rückbau auf dokumentierten Snapshot beziehungsweise Vorartefakt durchführen. | Ausgangscode und dokumentierter Datenstand sind nachvollziehbar wiederherstellbar. | [ ] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | |

## Abschlussentscheidung

| Feld | Eintrag |
|---|---|
| Erfolgreich / nicht erfolgreich / nicht geprüft | |
| Kritische Abweichungen / Tickets | |
| Datenschutz und Rückbau freigegeben | [ ] ja [ ] nein |
| Gesamtentscheidung | [ ] abgenommen [ ] mit Auflagen abgenommen [ ] nicht abgenommen |
| Name / Datum | |
