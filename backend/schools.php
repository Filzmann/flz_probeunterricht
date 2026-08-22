<?php

defined('ABSPATH') || exit;

// Exception-Texte sind interne Logdaten; HTML-Escaping erfolgt erst an der UI-Grenze.
// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped
// Alle POST-Pfade laufen durch flzpu_assert_admin_request(); der Sniff erkennt die zentrale Nonce-Prüfung nicht.
// phpcs:disable WordPress.Security.NonceVerification.Missing

function flzpu_schools_page(): void
{
	try {
		flzpu_schools_page_content();
	} catch (Throwable $error) {
		flzpu_render_admin_error($error, 'Anzeigen und Verarbeiten der Grundschulen');
	}
}

function flzpu_schools_page_content(): void
{
	flzpu_assert_admin_request();

	$school_csv_notice = flzpu_process_schools_csv_file();

	if (isset($_POST['school_submit'])) {
		$name = isset($_POST['name']) ? sanitize_text_field(wp_unslash($_POST['name'])) : '';
		$capacity = isset($_POST['capacity']) ? max(0, intval(wp_unslash($_POST['capacity']))) : 0;

		if ($name !== '') {
			$school_id = isset($_POST['school_id']) && $_POST['school_id'] !== ''
				? absint(wp_unslash($_POST['school_id']))
				: null;
			flz_wpdb_objects\FlzWpdbTransaction::run(
				static function () use ($school_id, $name, $capacity): void {
					FlzPuSetting::lock_capacity_limit();
					$school = $school_id
						? FlzPuSchool::get_by_id_for_update($school_id)
						: new FlzPuSchool(array('name' => $name, 'capacity' => $capacity, 'available_seats' => $capacity));
					if (!$school instanceof FlzPuSchool) {
						throw new UnexpectedValueException('Die zu speichernde Grundschule wurde nicht gefunden.');
					}
					$participants = $school_id ? FlzPuParticipant::count_by(array('school_id' => $school_id)) : 0;
					if ($capacity < $participants) {
						throw new UnexpectedValueException('Die Gesamtkapazität darf nicht unter der aktuellen Teilnehmerzahl liegen.');
					}
					$school->name = $name;
					$school->capacity = $capacity;
					$school->available_seats = $capacity - $participants;
					$school->save();
				},
				'Speichern einer Grundschule und ihrer Kapazität'
			);
		}
	}

	if (isset($_POST['school_delete'])) {
		$school_id = absint(wp_unslash($_POST['school_delete']));
		flz_wpdb_objects\FlzWpdbTransaction::run(
			static function () use ($school_id): void {
				FlzPuSetting::lock_capacity_limit();
				$school = FlzPuSchool::get_by_id_for_update($school_id);
				if (!$school instanceof FlzPuSchool) {
					throw new UnexpectedValueException('Die zu löschende Grundschule wurde nicht gefunden.');
				}
				if (FlzPuParticipant::count_by(array('school_id' => $school_id)) > 0) {
					throw new UnexpectedValueException('Eine Grundschule mit zugeordneten Teilnehmenden kann nicht gelöscht werden.');
				}
				$school->delete();
			},
			'Löschen einer unbelegten Grundschule'
		);
	}

	$schools = FlzPuSchool::get_all_by(order_by: 'name');
	$school_csv_export_url = wp_nonce_url(
		admin_url('admin-post.php?action=flzpu_export_schools_csv'),
		'flzpu_export_schools_csv'
	);
	$school_search = flz_ui_admin_filter_text('school_search');
	$school_orderby = flz_ui_admin_orderby(array('name', 'capacity'), 'name');
	$school_order = flz_ui_admin_order();
	$school_base_args = array('page' => 'flzpu_schools');
	if ($school_search !== '') {
		$school_base_args['school_search'] = $school_search;
		$schools = array_values(
			array_filter(
				$schools,
				static function (FlzPuSchool $school) use ($school_search): bool {
					return false !== stripos((string) $school->name, $school_search);
				}
			)
		);
	}

	usort(
		$schools,
		static function (FlzPuSchool $left, FlzPuSchool $right) use ($school_orderby, $school_order): int {
			$values = array(
				'name'            => array((string) $left->name, (string) $right->name),
				'capacity' => array((int) $left->capacity, (int) $right->capacity),
			);

			$result = flz_ui_admin_compare($values[$school_orderby][0], $values[$school_orderby][1], $school_order);
			if (0 === $result) {
				return flz_ui_admin_compare((string) $left->name, (string) $right->name, 'asc');
			}

			return $result;
		}
	);

	include dirname(__DIR__) . '/templates/schools.php';
}

/**
 * Sendet die Grundschulen nach expliziter Zugriffskontrolle direkt als CSV.
 */
function flzpu_export_schools_csv(): void
{
	flzpu_assert_csv_export_request('flzpu_export_schools_csv');

	try {
		$schools = FlzPuSchool::get_all_by(order_by: 'name');
		flz_wpdb_objects_send_csv_download(
			FlzPuCsvContract::school_header(),
			FlzPuCsvContract::export_schools($schools),
			'schools.csv'
		);
	} catch (Throwable $error) {
		flzpu_log_error($error, 'Exportieren der Grundschulen');
		wp_die(esc_html__('Die Grundschul-CSV konnte nicht erstellt werden.', 'flz-probeunterricht'));
	}
}

/** @return array{type:string,message:string}|null */
function flzpu_process_schools_csv_file(): ?array
{
	try {
		$is_dry_run = isset($_POST['submit_csv_dry_run']);
		$is_import = isset($_POST['submit_csv']);
		if (!$is_dry_run && !$is_import) {
			return null;
		}

		$school_rows = FlzPuCsvContract::parse_schools(
			flz_wpdb_objects_read_uploaded_csv('schools-csv', 'Importieren der Grundschul-CSV-Datei', false)
		);
		$plan = flzpu_plan_school_csv_import($school_rows);
		$proof = flzpu_school_csv_proof_hash($school_rows);
		if ($is_dry_run) {
			set_transient(flzpu_school_csv_proof_key(), $proof, 15 * MINUTE_IN_SECONDS);

			return array(
				'type' => 'success',
				'message' => sprintf('Dry-Run erfolgreich: %d Grundschulen können sicher übernommen werden.', count($plan)),
			);
		}

		$stored_proof = get_transient(flzpu_school_csv_proof_key());
		if (!is_string($stored_proof) || !hash_equals($stored_proof, $proof)) {
			throw new UnexpectedValueException('Diese Grundschul-CSV muss zuerst erneut erfolgreich als Dry-Run geprüft werden.');
		}
		flz_wpdb_objects\FlzWpdbTransaction::run(
			static function () use ($plan, $proof, $school_rows): void {
				FlzPuSetting::lock_capacity_limit();
				if (!hash_equals($proof, flzpu_school_csv_proof_hash($school_rows))) {
					throw new UnexpectedValueException('Der Grundschulbestand hat sich seit dem Dry-Run verändert.');
				}
				foreach ($plan as $item) {
					$school = $item['school'] instanceof FlzPuSchool
						? FlzPuSchool::get_by_id_for_update((int) $item['school']->id)
						: new FlzPuSchool(array('name' => $item['name']));
					if (!$school instanceof FlzPuSchool) {
						throw new UnexpectedValueException('Eine zu importierende Grundschule wurde nicht gefunden.');
					}
					$school->name = $item['name'];
					$school->capacity = $item['capacity'];
					$school->available_seats = $item['capacity'] - $item['participants'];
					$school->save();
				}
			},
			'Importieren des versionierten Grundschul-Snapshots'
		);
		delete_transient(flzpu_school_csv_proof_key());

		return array('type' => 'success', 'message' => 'Die Grundschul-CSV wurde vollständig importiert.');
	} catch (Throwable $error) {
		flzpu_log_error($error, 'Prüfen oder Importieren der Grundschul-CSV-Datei');

		return array(
			'type' => 'error',
			'message' => $error instanceof UnexpectedValueException
				? $error->getMessage()
				: 'Die Grundschul-CSV konnte nicht sicher verarbeitet werden.',
		);
	}
}

/** @param array<int,array{name:string,capacity:int}> $rows */
function flzpu_plan_school_csv_import(array $rows): array
{
	$plan = array();
	foreach ($rows as $row) {
		$school = FlzPuSchool::get_by_name($row['name']);
		$participants = $school instanceof FlzPuSchool
			? FlzPuParticipant::count_by(array('school_id' => (int) $school->id))
			: 0;
		if ($row['capacity'] < $participants) {
			throw new UnexpectedValueException('Die Kapazität der Grundschule „' . $row['name'] . '“ liegt unter ihrer aktuellen Teilnehmerzahl.');
		}
		$plan[] = array(
			'name' => $row['name'],
			'capacity' => $row['capacity'],
			'participants' => $participants,
			'school' => $school,
		);
	}

	return $plan;
}

/** @param array<int,array{name:string,capacity:int}> $rows */
function flzpu_school_csv_proof_hash(array $rows): string
{
	$current = array_map(
		static fn(FlzPuSchool $school): array => array(
			'id' => (int) $school->id,
			'name' => (string) $school->name,
			'capacity' => (int) $school->capacity,
			'available' => (int) $school->available_seats,
			'participants' => FlzPuParticipant::count_by(array('school_id' => (int) $school->id)),
		),
		FlzPuSchool::get_all_by(order_by: 'id')
	);

	return hash('sha256', serialize(array($rows, $current)));
}

function flzpu_school_csv_proof_key(): string
{
	return 'flzpu_school_csv_proof_' . get_current_user_id();
}
