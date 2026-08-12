# Roadmap: flz_probeunterricht

## Prüfstatus

**Nicht regelkonform / P0.** Admin-Schreibpfade haben eine zentrale Capability-
und Nonce-Grenze, Eingaben werden überwiegend bereinigt und die Aktivierung nutzt
ein zeitlich begrenztes Token. Datenverlust, öffentlich persistierte Exporte,
Kapazitätsrennen und fehlende Datenschutzverträge verhindern eine Freigabe.
Für das Plugin existiert noch kein fokussierter Test.

## P0

1. Deaktivierung nichtdestruktiv machen und Tabellen/Rollen/Capabilities
   erhalten. Datenlöschung ausschließlich in einem gesondert genehmigten,
   getesteten Uninstall-Pfad anbieten.
2. Teilnehmerexport nicht als öffentliche `probeunterricht.csv` im
   Upload-Verzeichnis erzeugen. Geschützten Direktdownload verwenden und
   belegen, dass keine Datei oder URL ohne Capability verbleibt.
3. Gesamt- und Schulkapazität atomar reservieren. Zählerprüfung, Anmeldung und
   Sitzabzug müssen unter wirksamer Sperre/Constraint laufen. Abnahme: parallele
   letzte-Platz-Anmeldungen erzeugen höchstens einen Datensatz und nie negative
   Plätze.
4. Platzbilanz für Löschen, Schulwechsel, Reset und fehlgeschlagene Transaktion
   konsistent machen. Das Löschen eines Teilnehmenden muss die fachlich
   definierte Gegenbuchung ausführen; jede Operation braucht Rollbacktests.

## P1

1. Für Schulen und Teilnehmende jeweils versionierten CSV-Import und -Export
   als Roundtrip ergänzen. Teilnehmerimport muss Schulreferenz, Gesamt- und
   Schulkapazität atomar prüfen; Einstellungen erhalten nach fachlicher
   Entscheidung ebenfalls einen Portabilitätspfad.
2. Eigene Schema-Version und idempotente Upgradepfade mit Frischinstallations-,
   Upgrade- und Wiederholungstests einführen.
3. Aufbewahrung, Auskunft, Aktivierungsfrist, Widerruf, Anonymisierung und
   Löschung der Daten minderjähriger Teilnehmender festlegen; WordPress-Privacy-
   Exporter/-Eraser ergänzen.
4. Direkte Includes der Shared-Plugin-Interna durch öffentliche Verträge
   ersetzen und beide Abhängigkeiten defensiv prüfen.
5. Serverseitige Fachvalidierung vervollständigen: Pflichtfelder, E-Mail,
   erlaubte Statuswerte, Klasse, Schule und Essensauswahl nicht allein durch
   HTML oder Typumwandlung begrenzen.
6. Tests für erlaubte/verweigerte Adminaktionen, manipulierte Nonces,
   Aktivierungstokens, CSV-Import, Kapazitätsgrenzen und verbotene
   Nebenwirkungen anlegen.

## P2

1. Fachservice, Persistenz, Request-Koordination und Templates trennen; globale
   Funktionen und Konstanten konsistent aus `flz_probeunterricht` ableiten.
2. Alle UI-Texte übersetzbar machen und `Text Domain` im Header ergänzen.
3. E-Mail-Inhalt, Content-Type, Absender und Fehlerverhalten konsistent und
   testbar machen; Tokens nie protokollieren.
4. Formular und Backendtabellen in DDEV responsiv, per Tastatur und mit
   Screenreader-relevanten Labels/Fehlerzuständen prüfen.
