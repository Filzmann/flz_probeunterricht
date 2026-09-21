<?php

defined('ABSPATH') || exit;

// Exception-Texte sind interne Diagnosedaten; Escaping erfolgt erst an der UI-Grenze.
// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped

/**
 * Additiver, idempotenter Upgradepfad für das Probeunterrichtsschema.
 */
final class FlzPuSchemaMigrator
{
	public static function maybe_upgrade(): void
	{
		$current_version = (string) get_option('flzpu_db_version', '0');
		if (version_compare($current_version, FLZPU_DB_VERSION, '>=')) {
			return;
		}

		FlzPuSchool::create_table();
		FlzPuParticipant::create_table();
		FlzPuSetting::create_table();

		if (version_compare($current_version, '2.0.0', '<')) {
			self::migrate_legacy_tables();
		}
		if (version_compare($current_version, '2.1.0', '<')) {
			self::backfill_school_capacity();
		}
		update_option('flzpu_db_version', FLZPU_DB_VERSION, false);
	}

	private static function migrate_legacy_tables(): void
	{
		global $wpdb;

		$legacy = array(
			'participants' => self::identifier($wpdb->prefix . 'flzpuparticipants'),
			'schools'      => self::identifier($wpdb->prefix . 'flzpuschools'),
			'settings'     => self::identifier($wpdb->prefix . 'flzpusettings'),
		);
		if (!self::table_exists($legacy['participants'])) {
			return;
		}
		if (!self::table_exists($legacy['schools']) || !self::table_exists($legacy['settings'])) {
			throw new RuntimeException('Das Legacy-Schema des Probeunterrichts ist unvollständig; die Migration wurde abgebrochen.');
		}

		flz_wpdb_objects\FlzWpdbTransaction::run(
			static function () use ($legacy): void {
				self::migrate_settings($legacy['settings']);
				self::migrate_schools($legacy['schools']);
				self::migrate_participants($legacy['participants'], $legacy['schools']);
			},
			'Migrieren des Probeunterricht-Legacyschemas'
		);
	}

	private static function migrate_settings(string $legacy_table): void
	{
		global $wpdb;

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Interner Tabellenname wurde validiert.
		$rows = $wpdb->get_results("SELECT name, value FROM $legacy_table");
		if (!is_array($rows)) {
			throw new RuntimeException('Die Legacy-Einstellungen konnten nicht gelesen werden.');
		}
		foreach ($rows as $row) {
			if (!is_object($row) || !isset($row->name, $row->value)) {
				throw new RuntimeException('Eine Legacy-Einstellung besitzt ein ungültiges Format.');
			}
			$setting = FlzPuSetting::get_by_fields(array('name' => (string) $row->name));
			if (!$setting instanceof FlzPuSetting) {
				$setting = new FlzPuSetting(array('name' => (string) $row->name, 'value' => (string) $row->value));
			} else {
				$setting->value = (string) $row->value;
			}
			$setting->save();
		}
	}

	private static function migrate_schools(string $legacy_table): void
	{
		global $wpdb;

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Interner Tabellenname wurde validiert.
		$rows = $wpdb->get_results("SELECT name, available_seats FROM $legacy_table ORDER BY id ASC");
		if (!is_array($rows)) {
			throw new RuntimeException('Die Legacy-Grundschulen konnten nicht gelesen werden.');
		}
		foreach ($rows as $row) {
			if (!is_object($row) || !isset($row->name, $row->available_seats)) {
				throw new RuntimeException('Eine Legacy-Grundschule besitzt ein ungültiges Format.');
			}
			$school = FlzPuSchool::get_by_name((string) $row->name);
			if (!$school instanceof FlzPuSchool) {
				$school = new FlzPuSchool(array('name' => (string) $row->name));
			}
			$school->available_seats = max(0, (int) $row->available_seats);
			$school->capacity = max(0, (int) $row->available_seats);
			$school->save();
		}
	}

	private static function backfill_school_capacity(): void
	{
		foreach (FlzPuSchool::get_all_by() as $school) {
			if (!$school instanceof FlzPuSchool || empty($school->id)) {
				continue;
			}
			$participant_count = FlzPuParticipant::count_by(array('school_id' => (int) $school->id));
			$school->capacity = max((int) $school->capacity, (int) $school->available_seats + $participant_count);
			$school->available_seats = max(0, (int) $school->capacity - $participant_count);
			$school->save();
		}
	}

	private static function migrate_participants(string $legacy_participants, string $legacy_schools): void
	{
		global $wpdb;

		$current_participants = self::identifier($wpdb->prefix . 'flz_pu_participants');
		$current_schools = self::identifier($wpdb->prefix . 'flz_pu_schools');
		// Sämtliche interpolierten Identifier stammen aus dem intern validierten Schema.
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Interne Tabellennamen wurden validiert.
		$source_count = (int) $wpdb->get_var("SELECT COUNT(*) FROM $legacy_participants");
		if (0 === $source_count) {
			return;
		}
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Interner Tabellenname wurde validiert.
		$target_count = (int) $wpdb->get_var("SELECT COUNT(*) FROM $current_participants");
		if ($target_count > 0) {
			throw new RuntimeException('Legacy- und aktuelle Teilnehmertabelle enthalten Daten; eine automatische Zusammenführung wäre nicht eindeutig.');
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Interne Tabellennamen wurden validiert.
		$unresolved = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM $legacy_participants p "
			. "LEFT JOIN $legacy_schools old_school ON old_school.id = p.school_id "
			. "LEFT JOIN $current_schools new_school ON new_school.name = old_school.name "
			. 'WHERE new_school.id IS NULL'
		);
		if ($unresolved > 0) {
			throw new RuntimeException('Mindestens eine Legacy-Anmeldung verweist auf keine auflösbare Grundschule.');
		}

		$now = current_time('mysql');
		$sql = "INSERT INTO $current_participants "
			. '(name, firstName, class, lunch, email, school_id, status, activationExpiration, activationToken, created_at, updated_at) '
			. "SELECT p.name, p.firstName, p.class, p.lunch, p.email, new_school.id, p.status, "
			. "NULLIF(p.activationExpiration, '0000-00-00 00:00:00'), p.activationToken, %s, %s "
			. "FROM $legacy_participants p "
			. "INNER JOIN $legacy_schools old_school ON old_school.id = p.school_id "
			. "INNER JOIN $current_schools new_school ON new_school.name = old_school.name";
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Nutzwerte werden vorbereitet; Tabellen sind intern validiert.
		$result = $wpdb->query($wpdb->prepare($sql, $now, $now));
		if (false === $result || $source_count !== (int) $result) {
			throw new RuntimeException('Die Legacy-Teilnehmenden wurden nicht vollständig übernommen.');
		}
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	private static function table_exists(string $table): bool
	{
		global $wpdb;

		$pattern = method_exists($wpdb, 'esc_like') ? $wpdb->esc_like($table) : $table;
		return $table === $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $pattern));
	}

	private static function identifier(string $identifier): string
	{
		if (1 !== preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $identifier)) {
			throw new InvalidArgumentException('Ein interner Probeunterrichts-Tabellenname ist ungültig.');
		}

		return $identifier;
	}
}
