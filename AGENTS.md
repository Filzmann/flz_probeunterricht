# Regeln für flz_probeunterricht

Dieses Repository enthält ausschließlich das Fachplugin
`flz_probeunterricht`. Es verwaltet Schulen und minderjährige Teilnehmende.
Personenbezogene Daten, Kapazitäten, CSV, E-Mail, Tokens, Rollen, Capabilities
und Schemaänderungen sind Risikogrenzen.

Harte Abhängigkeiten sind die öffentlichen, versionierten Verträge von
`flz_wpdb_objects` und `flz_ui_components`. Der Header `Requires Plugins` wird
durch defensive Klassen-/Funktions-/Versionsprüfungen ergänzt. Interne Dateien
anderer Repositories werden nie direkt eingebunden.

- Admin-Schreibpfade prüfen Capability und Nonce; öffentliche Anmeldung prüft
  Nonce, Eingaben, Gesamt- und Schulkapazität serverseitig.
- Reservierung, Anmeldung, Schulwechsel, Löschen und Reset halten die
  Platzbilanz atomar konsistent.
- Deaktivierung löscht keine Daten. Uninstall ist ein eigener genehmigter Pfad.
- Schulen und Teilnehmende besitzen jeweils versionierten CSV-Import und
  -Export mit Dry-Run; Exporte werden geschützt direkt gestreamt.
- Aufbewahrung, Auskunft, Anonymisierung und Löschung sind dokumentiert und
  testbar. Logs enthalten keine Personen- oder Tokenwerte.
- Übersetzbare Texte verwenden `flz-probeunterricht`.

Beobachtbare Änderungen testgetrieben umsetzen. Sicherheitsgrenzen brauchen
Allow-/Deny- und Rollbackfälle. Mindestens `./scripts/check-fast` ausführen;
WordPress-/DDEV-Prüfung separat benennen. Keine Commits, Pushes, Aktivierungen,
Imports oder Deployments ohne ausdrückliche Freigabe; nie `git add .` verwenden.
