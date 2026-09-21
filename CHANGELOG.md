# Changelog

Alle wesentlichen Änderungen an `flz_probeunterricht` werden in dieser Datei
dokumentiert. Ein Datum wird erst bei einer tatsächlichen Veröffentlichung
ergänzt.

## Unreleased

## 1.1.1 – 2026-09-21

- Aktivierungslinks verwenden einen festen, signierten Frontend-Endpunkt und
  funktionieren dadurch unabhängig von der Seite mit dem Anmelde-Shortcode.

- Reproduzierbare PR-/Main-CI für PHP 8.1 und 8.5 ergänzt.
- Branchgleicher Checkout beider Shared-Plugins mit sicherem `main`-Fallback
  ergänzt.
- Formale Lizenz- und Abnahmenachweise in den Delivery-Vertrag aufgenommen.
- PHP-No-Regression-Ratsche bei 8,16 Prozent remote enforced.
- Reproduzierbaren Ein-Wurzel-ZIP-Bau mit Manifest, SHA-256 und CI-Prüfung
  ergänzt.

## 1.1.0

- Additive, idempotente Bestandsmigration und datenerhaltende Deaktivierung
  ergänzt.
- Kapazitätsbilanz und parallele Anmeldung transaktional abgesichert.
- Geschützte CSV-Roundtrips mit Dry-Run sowie WordPress-Datenschutzwerkzeuge
  ergänzt.
