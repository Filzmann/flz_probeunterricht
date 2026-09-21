# Roadmap: flz_probeunterricht

## Prüfstatus

**Funktionsstand 1.1.1 ohne offene P0-Befunde; Release-Gate in
Übernahmephase 5 bereit.** Nichtdestruktive
Deaktivierung, geschützte CSV-Roundtrips, atomare Kapazitätsbilanz, additive
Legacy-Migration, Datenschutz und Fachvalidierung sind implementiert und durch
fokussierte Smokes abgesichert. Die reale lokale Bestandsmigration sowie
Aktivieren–Deaktivieren–Aktivieren wurden erfolgreich geprüft. PR-/Main-CI,
branchgleiche Shared-Plugins und die PHP-Ratsche von 8,16 Prozent sind remote
enforced. Das reproduzierbare ZIP-Artefakt, Installation, Upgrade,
Deaktivierung und Rückbau wurden abgenommen; das Protokoll liegt unter
`docs/manual-acceptance.md`. Die verbleibenden P2-Verbesserungen sind keine
Releaseblocker für 1.1.1.

## P0

1. **Erledigt:** Deaktivierung erhält Tabellen, Daten, Rolle und Capability;
   lokal blieben 160 Teilnehmende, 78 Schulen und 2 Einstellungen erhalten.
2. **Erledigt:** Exporte werden geschützt direkt gestreamt. Die öffentliche
   Altdatei wurde entfernt und wird nicht erneut erzeugt.
3. **Erledigt:** Gesamt- und Schulkapazität werden unter Transaktion und
   wirksamer Sperre reserviert; letzte-Platz- und Negativfälle sind getestet.
4. **Erledigt:** Löschen, Schulwechsel, Reset und Fehlerfälle gleichen Plätze
   atomar aus und hinterlassen keine negativen Zähler oder Teilstände.

## P1

1. **Erledigt:** Schulen und Teilnehmende besitzen versionierte, geschützte
   CSV-v1-Roundtrips mit obligatorischem Dry-Run, Referenz-/Kapazitätsprüfung
   und atomarem Snapshot-Import. Einstellungen bleiben bewusst außerhalb des
   personenbezogenen CSV-Vertrags.
2. **Erledigt:** DB-Version 2.1 und additive, idempotente Upgradepfade decken
   Frischinstallation, Legacy-Upgrade, Wiederholung und Konfliktfälle ab.
3. **Erledigt:** WordPress-Privacy-Exporter/-Eraser, bestätigte manuelle
   Bereinigung und optionale Aufbewahrung (Standard aus, Vorschlag 24 Monate)
   bilden Auskunft und Löschung ab. Aktivierungstoken sind nicht portabel.
4. **Erledigt:** Direkte Includes der Shared-Plugin-Interna durch verzögerten,
   defensiven Bootstrap mit öffentlichen APIs und Mindestversionen ersetzt.
5. **Erledigt:** Pflichtfelder, E-Mail, Status, Klasse, Schule, Essensauswahl
   und Kapazitäten werden serverseitig validiert.
6. **Weitgehend erledigt:** Smokes decken Exportgrenze, CSV-Vertrag,
   Kapazität, Migration, Datenschutz und Deaktivierung ab. Ein kompletter
   WordPress-Integrationstest für alle manipulierten Requestvarianten bleibt
   als P2-Nachweis offen.

## P2

1. Fachservice, Persistenz, Request-Koordination und Templates trennen; globale
   Funktionen und Konstanten konsistent aus `flz_probeunterricht` ableiten.
2. Alle UI-Texte übersetzbar machen und `Text Domain` im Header ergänzen.
3. E-Mail-Inhalt, Content-Type, Absender und Fehlerverhalten konsistent und
   testbar machen; Tokens nie protokollieren.
4. Formular und Backendtabellen in DDEV responsiv, per Tastatur und mit
   Screenreader-relevanten Labels/Fehlerzuständen prüfen.
