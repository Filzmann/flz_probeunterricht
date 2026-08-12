# Komponentenregeln: flz_probeunterricht

Zusätzlich gelten die Root-Regeln und die dortigen Skills
`work-in-wordpress-extension` und `test-driven-wordpress-change`.

Dieses Fachplugin verwaltet Schulen und Teilnehmende des Probeunterrichts.
Personenbezogene Daten, Exporte, Berechtigungen und Schemaänderungen sind
Risikogrenzen. Abhängigkeiten zu `flz_wpdb_objects` und `flz_ui_components`
nur über deren öffentliche Verträge verwenden. Relevanter Basistest:
Für Änderungen zuerst einen fokussierten Test ergänzen; aktuell existiert nur
der Root-PHP-Syntaxcheck als Baseline. WordPress-/Datenbankverhalten zusätzlich
in DDEV prüfen.
