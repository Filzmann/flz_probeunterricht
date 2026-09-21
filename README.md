# flz_probeunterricht

WordPress-Plugin für Grundschulen, Kapazitäten, Teilnehmende,
Aktivierungslinks und geschützte CSV-Portabilität des Probeunterrichts.

Erforderliche Plugins: `flz_wpdb_objects` und `flz_ui_components`. Der aktuelle
Härtungs- und Migrationsstand steht in `ROADMAP.md`.

Prüfung: `./scripts/check-fast`. WordPress-, Datenbank-, Mail- und UI-Verhalten
muss zusätzlich über die lokale DDEV-Instanz verifiziert werden.

Commit-, CI- und Coverage-Gates sind in Übernahmephase 2 enforced. Ein Release
bleibt bis zur ausgefüllten Abnahme sowie zur Installation, zum Upgrade und
zum Rückbau aus dem exakten Artefakt blockiert. Das ausfüllbare
[Abnahmeprotokoll](docs/manual-acceptance.md) führt Installation, Upgrade,
Kapazität, CSV, Datenschutz, Oberfläche und Rückbau zusammen.

Ein sauberer Commit wird reproduzierbar paketiert mit:

```bash
./scripts/build-release /tmp/flz_probeunterricht-release
```

## Neu in 1.1.1

- Aktivierungslinks verarbeiten die Bestätigung über einen festen
  Frontend-Endpunkt und sind nicht mehr von der jeweiligen Anmeldeseite
  abhängig.

## Neu in 1.1.0

- Deaktivierung erhält Tabellen, Daten, Rolle und Capability. Die lokale
  Aktivieren–Deaktivieren–Aktivieren-Abnahme bewahrte 160 Teilnehmende,
  78 Schulen und 2 Einstellungen vollständig.
- DB-Version 2.1 migriert Legacy-Bestände additiv und idempotent. Alte Tabellen
  werden nicht gelöscht und bleiben als Rückfallpfad erhalten.
- Gesamt- und Schulkapazität werden bei Anmeldung, Änderung, Löschung und Reset
  transaktional gesperrt und ausgeglichen; negative Plätze oder eine
  parallele Überbuchung werden abgewiesen.
- Schulen und Teilnehmende besitzen geschützte, versionierte CSV-v1-
  Roundtrips. Ein Import benötigt einen erfolgreichen benutzer- und
  dateigebundenen Dry-Run und wird atomar übernommen. Aktivierungstoken werden
  weder exportiert noch importiert.
- WordPress-Privacy-Exporter und -Eraser sowie eine optionale, standardmäßig
  deaktivierte Aufbewahrung sind integriert. Der konfigurierbare Vorschlag
  beträgt 24 Monate.
- Öffentliche und administrative Eingaben werden serverseitig auf Pflichtwert,
  E-Mail, Klasse, Schule, Status und Kapazität geprüft. UI-Assets werden nur auf
  passenden Plugin-Seiten angefordert.

## CSV-Portabilität

CSV-Dateien werden ausschließlich als Capability- und Nonce-geschützte
Direktdownloads ausgeliefert. Es entsteht keine öffentliche Datei im
Upload-Verzeichnis. Vor dem schreibenden Import muss dieselbe unveränderte
Datei innerhalb des vorgesehenen Prüffensters erfolgreich als Dry-Run geprüft
werden.

Der Teilnehmerimport ist ein vollständiger Snapshot: Schulreferenzen und
Kapazitäten müssen vollständig konsistent sein. Der Status `cancelled` ist kein
Importstatus; eine Abmeldung löscht den Datensatz und gibt den Platz frei.

## Datenschutz

Automatische Löschung ist standardmäßig ausgeschaltet. Wird sie bewusst
aktiviert, sind 1 bis 120 Monate einstellbar; voreingestellt sind 24 Monate.
Zusätzlich steht eine bestätigte manuelle Bereinigung zur Verfügung. Auskunft
und Löschung sind in die WordPress-Datenschutzwerkzeuge eingebunden.
