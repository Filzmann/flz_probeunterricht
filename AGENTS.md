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

## Commit-, Coverage- und Release-Gates

- Der aktuelle Übernahmestand ist Phase 1: PR-/Main-CI, Lizenz, Changelog und
  branchgleiche Provider-Checkouts sind lokal konfiguriert. Bis zum ersten
  grünen Remote-Lauf und dem Coverage-Gate bleiben normale Produkt- und
  Releasecommits blockiert; ausdrücklich beauftragte Quality-Rollout-Commits
  dürfen die fehlende Infrastruktur schrittweise herstellen.
- Vor einem späteren normalen Commit sind Status, Diff-Statistik und vollständige
  Dateiliste zu zeigen; fokussierte Tests, `./scripts/check-fast`, Shared-
  Provider-/Consumer-Tests, CI und Coverage-Gates müssen grün sein. Dateien
  werden einzeln gestaged; `git add .` bleibt verboten.
- PHP-Line-Coverage wird gegen eine gemessene No-Regression-Baseline geprüft.
  Neuer oder wesentlich geänderter Code erreicht mindestens 85 Prozent;
  Sicherheits-, Datenschutz-, Migrations- und Kapazitätsinvarianten sind
  unabhängig davon vollständig abgedeckt.
- Der PHPCOV-/Xdebug-Messjob ist vorbereitet; die PHP-Baseline bleibt bis zum
  ersten reproduzierbaren Remote-Lauf ausdrücklich `pending`.
- Ein Fast- oder Diagnosecheck ist kein Releaseurteil. Ein Release braucht ein
  sauberes Repository, konsistente Version/Changelog/Lizenz, vollständig
  ausgefülltes `docs/manual-acceptance.md`, ein reproduzierbares Ein-Wurzel-
  Archiv, Manifest und SHA-256 sowie geprüfte Installation, Upgrade,
  Deaktivierung, Datenschutz, sichtbare UI und Rückbau aus dem Artefakt.
- Bauen, Signieren, Taggen, Pushen, Publizieren und Deployen bleiben getrennte,
  ausdrücklich zu autorisierende Aktionen.
